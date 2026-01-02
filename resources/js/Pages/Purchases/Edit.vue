<script setup>
import { ref, watch, computed, onMounted } from 'vue';
import { useForm, Link } from '@inertiajs/vue3';
import axios from 'axios';
import AppLayout from '@/Layouts/AppLayout.vue';
import { 
    NForm, NFormItem, NInput, NButton, NCard, NIcon, NGrid, NGridItem, 
    NSelect, NDatePicker, NInputNumber, NDataTable, NDivider, createDiscreteApi, 
    NSpin, NModal, NTag, NAvatar
} from 'naive-ui';
import { 
    SaveOutline, ArrowBackOutline, CartOutline, CalendarOutline, 
    DocumentTextOutline, AddOutline, TrashOutline, CubeOutline, 
    SearchOutline, LinkOutline, CloseCircleOutline
} from '@vicons/ionicons5';

// Props: Recibimos la orden a editar y la lista de proveedores
const props = defineProps({
    suppliers: {
        type: Array,
        required: true
    },
    order: {
        type: Object,
        required: true
    }
});

const { notification } = createDiscreteApi(['notification']);
const formRef = ref(null);

// Estado de Productos del Proveedor
const assignedProducts = ref([]); 
const isLoadingProducts = ref(false);

// Estado del Modal de Asignación Rápida
const showAssignModal = ref(false);
const candidateProducts = ref([]); 
const isSearchingCandidates = ref(false);
const searchTimeout = ref(null);

// Formulario de Asignación Rápida
const quickAssignForm = ref({
    product_id: null,
    product_obj: null,
    purchase_price: 0,
    currency: 'MXN',
    delivery_days: 1,
    supplier_sku: ''
});

// --- INICIALIZACIÓN DEL FORMULARIO CON DATOS EXISTENTES ---
const form = useForm({
    supplier_id: props.order.supplier_id,
    // Formatear fecha si viene como string completo ISO
    expected_date: props.order.expected_date ? props.order.expected_date.split('T')[0] : null, 
    currency: props.order.currency, 
    notes: props.order.notes,
    // Mapear los items existentes añadiendo un temp_id para el v-for
    items: props.order.items.map(item => ({
        product_id: item.product_id,
        quantity: parseFloat(item.quantity),
        unit_cost: parseFloat(item.unit_cost),
        temp_id: Date.now() + Math.random() // ID único temporal
    })), 
});

const rules = {
    supplier_id: { required: true, message: 'Selecciona un proveedor', trigger: ['blur', 'change'], type: 'number' },
    currency: { required: true, message: 'Selecciona la moneda', trigger: 'change' },
    items: { required: true, type: 'array', min: 1, message: 'Agrega productos', trigger: 'change' }
};

const currencyOptions = [
    { label: 'MXN - Pesos Mexicanos', value: 'MXN' },
    { label: 'USD - Dólares Americanos', value: 'USD' }
];

// --- 1. CARGA DE PRODUCTOS (LÓGICA SEPARADA) ---

// Función reutilizable para obtener productos
const fetchSupplierProducts = async (supplierId) => {
    if (!supplierId) {
        assignedProducts.value = [];
        return;
    }
    
    isLoadingProducts.value = true;
    try {
        const response = await axios.get(route('suppliers.products.assigned', supplierId));
        
        assignedProducts.value = response.data.map(p => ({
            label: p.name,
            value: p.id,
            sku: p.sku,
            pivot_price: parseFloat(p.purchase_price || 0),
            pivot_currency: p.currency,
            image_url: p.image_url
        }));
    } catch (error) {
        console.error(error);
        notification.error({ title: 'Error', content: 'No se pudo cargar el catálogo del proveedor.' });
    } finally {
        isLoadingProducts.value = false;
    }
};

// AL MONTAR: Cargar productos SIN borrar los items del form
onMounted(() => {
    if (form.supplier_id) {
        fetchSupplierProducts(form.supplier_id);
    }
});

// WATCHER: Si el usuario CAMBIA el proveedor manualmente, recargamos y limpiamos items
watch(() => form.supplier_id, async (newSupplierId, oldVal) => {
    // Solo actuamos si hay un cambio real y no es la carga inicial (aunque el watcher por defecto es lazy)
    if (newSupplierId !== oldVal) {
        form.items = []; // Limpiar items porque pertenecen al proveedor anterior
        await fetchSupplierProducts(newSupplierId);
    }
});

// --- 2. GESTIÓN DE ITEMS ---
const addItem = (preselectedProductId = null) => {
    let initialCost = 0;
    
    if (preselectedProductId) {
        const product = assignedProducts.value.find(p => p.value === preselectedProductId);
        if (product) {
            initialCost = product.pivot_price;
            if (product.pivot_currency !== form.currency) {
                notification.warning({ 
                    title: 'Diferencia de Moneda', 
                    content: `Producto en ${product.pivot_currency}, orden en ${form.currency}.`,
                    duration: 4000 
                });
            }
        }
    }

    form.items.push({
        product_id: preselectedProductId,
        quantity: 1,
        unit_cost: initialCost,
        temp_id: Date.now() + Math.random()
    });
};

const removeItem = (index) => {
    form.items.splice(index, 1);
};

const handleProductChange = (val, index) => {
    const product = assignedProducts.value.find(p => p.value === val);
    if (product) {
        form.items[index].unit_cost = product.pivot_price;
        if (product.pivot_currency && product.pivot_currency !== form.currency) {
             notification.info({ 
                content: `Precio base en ${product.pivot_currency}. Ajustado a ${form.currency}.`,
                duration: 2000
            });
        }
    }
};

// --- 3. ASIGNACIÓN RÁPIDA (Igual que en Create) ---
const openAssignModal = () => {
    if (!form.supplier_id) {
        notification.warning({ content: 'Selecciona un proveedor.' });
        return;
    }
    quickAssignForm.value = {
        product_id: null,
        product_obj: null,
        purchase_price: 0,
        currency: form.currency,
        delivery_days: 1,
        supplier_sku: ''
    };
    candidateProducts.value = [];
    showAssignModal.value = true;
    searchGlobalProducts('');
};

const searchGlobalProducts = (query) => {
    if (searchTimeout.value) clearTimeout(searchTimeout.value);
    
    searchTimeout.value = setTimeout(async () => {
        isSearchingCandidates.value = true;
        try {
            const response = await axios.get(route('suppliers.products.fetch', form.supplier_id), {
                params: { query: query }
            });
            
            let results = response.data;
            if(query) {
                const lower = query.toLowerCase();
                results = results.filter(p => p.name.toLowerCase().includes(lower) || p.sku.toLowerCase().includes(lower));
            }
            
            candidateProducts.value = results.map(p => ({
                label: p.name,
                value: p.id,
                sku: p.sku,
                image_url: p.image_url,
                raw: p 
            }));
        } catch (e) {
            console.error(e);
        } finally {
            isSearchingCandidates.value = false;
        }
    }, 400);
};

const selectCandidate = (val, option) => {
    quickAssignForm.value.product_id = val;
    quickAssignForm.value.product_obj = option;
    quickAssignForm.value.purchase_price = parseFloat(option.raw.price || 0);
    quickAssignForm.value.supplier_sku = option.sku;
};

const submitQuickAssign = async () => {
    if (!quickAssignForm.value.product_id) return;

    try {
        await axios.post(route('suppliers.products.assign', form.supplier_id), {
            product_id: quickAssignForm.value.product_id,
            purchase_price: quickAssignForm.value.purchase_price,
            currency: quickAssignForm.value.currency,
            supplier_sku: quickAssignForm.value.supplier_sku,
            delivery_days: quickAssignForm.value.delivery_days
        });

        notification.success({ title: 'Éxito', content: 'Producto asignado.', duration: 3000 });

        const newProduct = {
            label: quickAssignForm.value.product_obj.label,
            value: quickAssignForm.value.product_id,
            sku: quickAssignForm.value.product_obj.sku,
            image_url: quickAssignForm.value.product_obj.image_url,
            pivot_price: quickAssignForm.value.purchase_price,
            pivot_currency: quickAssignForm.value.currency
        };
        assignedProducts.value.push(newProduct);
        addItem(newProduct.value);
        showAssignModal.value = false;

    } catch (error) {
        notification.error({ title: 'Error', content: 'Error al asignar.' });
    }
};

// --- CÁLCULOS Y ENVÍO ---
const totalOrder = computed(() => {
    return form.items.reduce((acc, item) => acc + (item.quantity * item.unit_cost), 0);
});

const formatCurrency = (amount) => {
    return new Intl.NumberFormat('es-MX', { style: 'currency', currency: form.currency }).format(amount);
};

const submit = () => {
    formRef.value?.validate((errors) => {
        if (!errors) {
            if(form.items.some(i => !i.product_id || i.quantity <= 0)) {
                notification.warning({ title: 'Incompleto', content: 'Revisa las cantidades.' });
                return;
            }
            // CAMBIO: Usamos PUT y la ruta update con el ID de la orden
            form.put(route('purchases.update', props.order.id), {
                onSuccess: () => notification.success({ title: 'Orden Actualizada', duration: 3000 }),
                onError: () => notification.error({ title: 'Error al actualizar', duration: 3000 })
            });
        }
    });
};

const supplierOptions = computed(() => props.suppliers.map(s => ({ label: s.company_name, value: s.id })));
</script>

<template>
    <AppLayout :title="`Editar Orden #${props.order.id}`">
        <template #header>
            <div class="flex items-center gap-4">
                <Link :href="route('purchases.index')">
                    <n-button circle secondary type="default">
                        <template #icon><n-icon><ArrowBackOutline /></n-icon></template>
                    </n-button>
                </Link>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Editar Orden de Compra #{{ props.order.id }}
                </h2>
                <!-- Badge de Estado para contexto visual -->
                <n-tag :type="props.order.status === 'Borrador' ? 'warning' : 'default'" size="small">
                    {{ props.order.status }}
                </n-tag>
            </div>
        </template>

        <div class="py-8">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
                
                <n-form
                    ref="formRef"
                    :model="form"
                    :rules="rules"
                    label-placement="top"
                    require-mark-placement="right-hanging"
                    size="large"
                >
                    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
                        
                        <!-- Columna Izquierda: Datos Generales -->
                        <div class="lg:col-span-4 space-y-6">
                            <n-card title="Datos de la Orden" :bordered="false" class="shadow-sm rounded-2xl">
                                <template #header-extra><n-icon size="20" class="text-blue-500"><DocumentTextOutline /></n-icon></template>
                                
                                <n-form-item label="Proveedor" path="supplier_id">
                                    <n-select 
                                        v-model:value="form.supplier_id" 
                                        :options="supplierOptions" 
                                        placeholder="Seleccione..."
                                        filterable
                                    />
                                </n-form-item>

                                <div class="grid grid-cols-2 gap-4">
                                    <n-form-item label="Moneda" path="currency">
                                        <n-select v-model:value="form.currency" :options="currencyOptions" />
                                    </n-form-item>
                                    
                                    <n-form-item label="Fecha Entrega" path="expected_date">
                                        <n-date-picker 
                                            v-model:formatted-value="form.expected_date"
                                            value-format="yyyy-MM-dd"
                                            type="date"
                                            class="w-full"
                                            placeholder="Opcional"
                                        />
                                    </n-form-item>
                                </div>

                                <n-form-item label="Notas / Referencia" path="notes">
                                    <n-input 
                                        v-model:value="form.notes" 
                                        type="textarea" 
                                        placeholder="Ej. Proyecto Alpha" 
                                        :autosize="{ minRows: 3, maxRows: 5 }"
                                    />
                                </n-form-item>
                            </n-card>

                            <!-- Totalizador -->
                            <n-card v-if="form.items.length > 0" class="shadow-sm rounded-2xl bg-indigo-50 border-indigo-100" :bordered="false">
                                <div class="text-center">
                                    <p class="text-gray-500 text-sm uppercase font-bold tracking-wider">Total Estimado</p>
                                    <p class="text-3xl font-black text-indigo-800 mt-1">{{ formatCurrency(totalOrder) }}</p>
                                    <p class="text-xs text-indigo-600 mt-2">{{ form.items.length }} Partidas</p>
                                </div>
                            </n-card>
                        </div>

                        <!-- Columna Derecha: Partidas (Items) -->
                        <div class="lg:col-span-8 space-y-6">
                            <n-card :bordered="false" class="shadow-sm rounded-2xl min-h-[500px]">
                                <template #header>
                                    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                                        <span class="flex items-center gap-2 font-bold text-gray-700">
                                            <n-icon class="text-orange-500"><CartOutline /></n-icon> 
                                            Productos
                                        </span>
                                        <div class="flex gap-2">
                                            <!-- Botón Asignar Nuevo -->
                                            <n-button 
                                                size="small" 
                                                secondary 
                                                type="info" 
                                                @click="openAssignModal"
                                                :disabled="!form.supplier_id"
                                            >
                                                <template #icon><n-icon><LinkOutline /></n-icon></template>
                                                Asignar Nuevo
                                            </n-button>
                                            
                                            <!-- Botón Agregar Fila -->
                                            <n-button 
                                                size="small" 
                                                type="primary" 
                                                secondary 
                                                round 
                                                @click="addItem()" 
                                                :disabled="!form.supplier_id"
                                            >
                                                <template #icon><n-icon><AddOutline /></n-icon></template>
                                                Agregar Fila
                                            </n-button>
                                        </div>
                                    </div>
                                </template>

                                <!-- Estado Vacío -->
                                <div v-if="form.items.length === 0" class="py-12 flex flex-col items-center justify-center text-gray-400 text-center border-2 border-dashed border-gray-100 rounded-xl bg-gray-50/50">
                                    <n-icon size="48" class="mb-2 opacity-50"><CubeOutline /></n-icon>
                                    <p v-if="!form.supplier_id">Selecciona un proveedor para comenzar</p>
                                    <p v-else>Agrega productos a la lista o asigna nuevos.</p>
                                </div>

                                <!-- Spinner Carga -->
                                <div v-if="isLoadingProducts" class="py-8 text-center">
                                    <n-spin size="medium" /> 
                                    <p class="text-xs text-gray-500 mt-2">Cargando catálogo...</p>
                                </div>

                                <!-- Lista de Items -->
                                <div v-else class="space-y-4">
                                    <div 
                                        v-for="(item, index) in form.items" 
                                        :key="item.temp_id"
                                        class="bg-white border border-gray-100 p-4 rounded-xl shadow-sm hover:shadow-md transition-shadow relative group"
                                    >
                                        <div class="grid grid-cols-1 sm:grid-cols-12 gap-4 items-end">
                                            
                                            <!-- Producto (Select con los ASIGNADOS) -->
                                            <div class="sm:col-span-6">
                                                <label class="text-xs font-bold text-gray-500 mb-1 block">Producto</label>
                                                <n-select 
                                                    v-model:value="item.product_id" 
                                                    :options="assignedProducts" 
                                                    filterable 
                                                    placeholder="Buscar..."
                                                    @update:value="(val) => handleProductChange(val, index)"
                                                >
                                                    <template #empty>
                                                        <div class="p-2 text-center text-xs">
                                                            No encontrado.<br>
                                                            <n-button text type="primary" size="tiny" @click="openAssignModal">Asignar nuevo</n-button>
                                                        </div>
                                                    </template>
                                                </n-select>
                                            </div>

                                            <!-- Cantidad -->
                                            <div class="sm:col-span-2">
                                                <label class="text-xs font-bold text-gray-500 mb-1 block">Cant.</label>
                                                <n-input-number v-model:value="item.quantity" :min="0.1" placeholder="0" class="w-full" />
                                            </div>

                                            <!-- Costo Unitario -->
                                            <div class="sm:col-span-3">
                                                <label class="text-xs font-bold text-gray-500 mb-1 block">Costo ({{ form.currency }})</label>
                                                <n-input-number v-model:value="item.unit_cost" :min="0" :precision="2" class="w-full">
                                                    <template #prefix>$</template>
                                                </n-input-number>
                                            </div>

                                            <!-- Botón Eliminar -->
                                            <div class="sm:col-span-1 flex justify-end">
                                                <n-button circle quaternary type="error" @click="removeItem(index)">
                                                    <template #icon><n-icon><TrashOutline /></n-icon></template>
                                                </n-button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </n-card>

                            <!-- Botones Inferiores -->
                            <div class="flex flex-col sm:flex-row gap-4 justify-end mt-6">
                                <Link :href="route('purchases.index')">
                                    <n-button size="large" ghost>Cancelar</n-button>
                                </Link>
                                <!-- CAMBIO: Botón indica Actualizar -->
                                <n-button 
                                    type="primary" 
                                    size="large" 
                                    @click="submit" 
                                    :loading="form.processing"
                                    :disabled="form.items.length === 0 || form.processing"
                                >
                                    <template #icon><n-icon><SaveOutline /></n-icon></template>
                                    Actualizar Orden
                                </n-button>
                            </div>

                        </div>
                    </div>
                </n-form>

                <!-- MODAL: ASIGNACIÓN RÁPIDA (Igual que en Create) -->
                <n-modal v-model:show="showAssignModal">
                    <n-card 
                        style="width: 600px; max-width: 95%" 
                        title="Asignar Nuevo Producto al Proveedor" 
                        :bordered="false" 
                        size="huge" 
                        role="dialog" 
                        aria-modal="true"
                    >
                        <template #header-extra>
                            <n-button circle quaternary @click="showAssignModal = false">
                                <template #icon><n-icon><CloseCircleOutline /></n-icon></template>
                            </n-button>
                        </template>

                        <div class="space-y-4">
                            <p class="text-sm text-gray-500">
                                Busca un producto global y asignalo a este proveedor para añadirlo a la orden actual.
                            </p>
                            
                            <!-- Buscador Global -->
                            <n-form-item label="Buscar Producto Global">
                                <n-select
                                    filterable
                                    remote
                                    placeholder="Escribe para buscar..."
                                    :options="candidateProducts"
                                    :loading="isSearchingCandidates"
                                    @search="searchGlobalProducts"
                                    @update:value="selectCandidate"
                                />
                            </n-form-item>

                            <!-- Formulario Rápido -->
                            <div v-if="quickAssignForm.product_id" class="grid grid-cols-2 gap-4 bg-gray-50 p-4 rounded-xl border border-gray-100">
                                <div class="col-span-2 flex items-center gap-3 border-b border-gray-200 pb-2 mb-2">
                                    <n-avatar :src="quickAssignForm.product_obj?.image_url" size="small" />
                                    <div>
                                        <p class="font-bold text-sm">{{ quickAssignForm.product_obj?.label }}</p>
                                        <p class="text-xs text-gray-400">SKU: {{ quickAssignForm.product_obj?.sku }}</p>
                                    </div>
                                </div>

                                <n-form-item label="Precio Pactado" :show-feedback="false">
                                    <n-input-number v-model:value="quickAssignForm.purchase_price" :min="0">
                                        <template #prefix>$</template>
                                    </n-input-number>
                                </n-form-item>

                                <n-form-item label="Moneda" :show-feedback="false">
                                    <n-select v-model:value="quickAssignForm.currency" :options="currencyOptions" />
                                </n-form-item>
                                
                                <n-form-item label="SKU del Proveedor (Opcional)" :show-feedback="false">
                                    <n-input v-model:value="quickAssignForm.supplier_sku" placeholder="Código externo" />
                                </n-form-item>

                                <n-form-item label="Días Entrega" :show-feedback="false">
                                    <n-input-number v-model:value="quickAssignForm.delivery_days" :min="1" />
                                </n-form-item>
                            </div>

                            <div class="flex justify-end gap-2 mt-4">
                                <n-button @click="showAssignModal = false">Cancelar</n-button>
                                <n-button 
                                    type="primary" 
                                    :disabled="!quickAssignForm.product_id" 
                                    @click="submitQuickAssign"
                                >
                                    Asignar y Agregar
                                </n-button>
                            </div>
                        </div>
                    </n-card>
                </n-modal>

            </div>
        </div>
    </AppLayout>
</template>