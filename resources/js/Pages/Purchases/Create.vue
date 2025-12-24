<script setup>
import { ref, watch, computed } from 'vue';
import { useForm, Link } from '@inertiajs/vue3';
import axios from 'axios';
import AppLayout from '@/Layouts/AppLayout.vue';
import { 
    NForm, NFormItem, NInput, NButton, NCard, NIcon, NGrid, NGridItem, 
    NSelect, NDatePicker, NInputNumber, NDataTable, NDivider, createDiscreteApi, NSpin
} from 'naive-ui';
import { 
    SaveOutline, ArrowBackOutline, CartOutline, CalendarOutline, 
    DocumentTextOutline, AddOutline, TrashOutline, CubeOutline 
} from '@vicons/ionicons5';

const props = defineProps({
    suppliers: {
        type: Array,
        required: true
    }
});

const { notification } = createDiscreteApi(['notification']);
const formRef = ref(null);
const availableProducts = ref([]);
const isLoadingProducts = ref(false);

// Configuración del Formulario
const form = useForm({
    supplier_id: null,
    expected_date: null,
    notes: '',
    items: [], // Array de { product_id, quantity, unit_cost, ...detalles_visuales }
});

// Reglas de validación
const rules = {
    supplier_id: { 
        required: true, 
        message: 'Selecciona un proveedor', 
        trigger: ['blur', 'change'],
        type: 'number'
    },
    items: {
        required: true,
        type: 'array',
        min: 1,
        message: 'Agrega al menos un producto a la orden',
        trigger: 'change'
    }
};

// --- LÓGICA DE NEGOCIO ---

// 1. Cargar productos al cambiar proveedor
watch(() => form.supplier_id, async (newSupplierId) => {
    form.items = []; // Limpiar items anteriores para evitar inconsistencias
    availableProducts.value = [];
    
    if (!newSupplierId) return;

    isLoadingProducts.value = true;
    try {
        // Reutilizamos la API del SupplierController que ya filtra por branch y retorna el catálogo
        const response = await axios.get(route('suppliers.products.fetch', newSupplierId));
        
        // Mapeamos para el Select de Naive UI
        availableProducts.value = response.data.map(p => ({
            label: `${p.name} - Stock: ${p.sku}`,
            value: p.id,
            ...p // Guardamos todo el objeto para extraer precios
        }));
    } catch (error) {
        notification.error({ title: 'Error', content: 'No se pudo cargar el catálogo del proveedor.' });
    } finally {
        isLoadingProducts.value = false;
    }
});

// 2. Gestión de Items (Filas)
const addItem = () => {
    form.items.push({
        product_id: null,
        quantity: 1,
        unit_cost: 0,
        temp_id: Date.now() // ID temporal para keys de Vue
    });
};

const removeItem = (index) => {
    form.items.splice(index, 1);
};

// 3. Auto-completar costo al seleccionar producto
const handleProductChange = (val, index) => {
    const product = availableProducts.value.find(p => p.id === val);
    if (product) {
        // Asignamos el precio de compra pactado o el default
        form.items[index].unit_cost = parseFloat(product.purchase_price || 0);
    }
};

// 4. Cálculos Totales
const totalOrder = computed(() => {
    return form.items.reduce((acc, item) => {
        return acc + (item.quantity * item.unit_cost);
    }, 0);
});

const formatCurrency = (amount) => {
    return new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(amount);
};

// 5. Envío
const submit = () => {
    formRef.value?.validate((errors) => {
        if (!errors) {
            // Validar validación manual de items vacíos
            if(form.items.some(i => !i.product_id || i.quantity <= 0)) {
                notification.warning({ title: 'Datos incompletos', content: 'Revisa que todos los productos tengan cantidad y nombre.' });
                return;
            }

            form.post(route('purchases.store'), {
                onSuccess: () => {
                    notification.success({
                        title: 'Orden Creada',
                        content: 'La orden de compra se ha generado exitosamente.'
                    });
                },
                onError: () => {
                    notification.error({
                        title: 'Error',
                        content: 'Revisa los campos del formulario.'
                    });
                }
            });
        }
    });
};

// Opciones para Selects
const supplierOptions = computed(() => props.suppliers.map(s => ({
    label: s.company_name,
    value: s.id
})));
</script>

<template>
    <AppLayout title="Nueva Orden de Compra">
        <template #header>
            <div class="flex items-center gap-4">
                <Link :href="route('purchases.index')">
                    <n-button circle secondary type="default">
                        <template #icon><n-icon><ArrowBackOutline /></n-icon></template>
                    </n-button>
                </Link>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Generar Orden de Compra
                </h2>
            </div>
        </template>

        <div class="py-8">
            <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
                
                <n-form
                    ref="formRef"
                    :model="form"
                    :rules="rules"
                    label-placement="top"
                    require-mark-placement="right-hanging"
                    size="large"
                >
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        
                        <!-- Columna Izquierda: Datos Generales -->
                        <div class="lg:col-span-1 space-y-6">
                            <n-card title="Información General" :bordered="false" class="shadow-sm rounded-2xl">
                                <template #header-extra><n-icon size="20" class="text-blue-500"><DocumentTextOutline /></n-icon></template>
                                
                                <n-form-item label="Proveedor" path="supplier_id">
                                    <n-select 
                                        v-model:value="form.supplier_id" 
                                        :options="supplierOptions" 
                                        placeholder="Seleccione..."
                                        filterable
                                    />
                                </n-form-item>

                                <n-form-item label="Fecha Esperada de Entrega" path="expected_date">
                                    <n-date-picker 
                                        v-model:formatted-value="form.expected_date"
                                        value-format="yyyy-MM-dd"
                                        type="date"
                                        class="w-full"
                                        placeholder="Opcional"
                                    />
                                </n-form-item>

                                <n-form-item label="Notas / Referencia" path="notes">
                                    <n-input 
                                        v-model:value="form.notes" 
                                        type="textarea" 
                                        placeholder="Ej. Urgente para proyecto Alpha" 
                                        :autosize="{ minRows: 3, maxRows: 5 }"
                                    />
                                </n-form-item>
                            </n-card>

                            <!-- Resumen Flotante (Solo visible en desktop si hay items) -->
                            <n-card v-if="form.items.length > 0" class="shadow-sm rounded-2xl bg-blue-50 border-blue-100" :bordered="false">
                                <div class="text-center">
                                    <p class="text-gray-500 text-sm uppercase font-bold tracking-wider">Total Estimado</p>
                                    <p class="text-3xl font-black text-blue-800 mt-1">{{ formatCurrency(totalOrder) }}</p>
                                    <p class="text-xs text-blue-600 mt-2">{{ form.items.length }} Partidas</p>
                                </div>
                            </n-card>
                        </div>

                        <!-- Columna Derecha: Partidas (Items) -->
                        <div class="lg:col-span-2 space-y-6">
                            <n-card :bordered="false" class="shadow-sm rounded-2xl min-h-[500px]">
                                <template #header>
                                    <div class="flex justify-between items-center">
                                        <span class="flex items-center gap-2">
                                            <n-icon class="text-orange-500"><CartOutline /></n-icon> 
                                            Partidas de la Orden
                                        </span>
                                        <n-button size="small" type="primary" secondary round @click="addItem" :disabled="!form.supplier_id">
                                            <template #icon><n-icon><AddOutline /></n-icon></template>
                                            Agregar Producto
                                        </n-button>
                                    </div>
                                </template>

                                <!-- Estado Vacío -->
                                <div v-if="form.items.length === 0" class="py-12 flex flex-col items-center justify-center text-gray-400 text-center border-2 border-dashed border-gray-100 rounded-xl">
                                    <n-icon size="48" class="mb-2 opacity-50"><CubeOutline /></n-icon>
                                    <p v-if="!form.supplier_id">Primero selecciona un proveedor</p>
                                    <p v-else>Agrega productos a la lista</p>
                                </div>

                                <!-- Spinner Carga Productos -->
                                <div v-if="isLoadingProducts" class="py-4 text-center">
                                    <n-spin size="small" /> <span class="text-xs text-gray-500 ml-2">Cargando catálogo...</span>
                                </div>

                                <!-- Lista de Items -->
                                <div v-else class="space-y-4">
                                    <div 
                                        v-for="(item, index) in form.items" 
                                        :key="item.temp_id"
                                        class="bg-white border border-gray-100 p-4 rounded-xl shadow-sm hover:shadow-md transition-shadow relative group"
                                    >
                                        <div class="grid grid-cols-1 sm:grid-cols-12 gap-4 items-end">
                                            
                                            <!-- Producto -->
                                            <div class="sm:col-span-6">
                                                <label class="text-xs font-bold text-gray-500 mb-1 block">Producto</label>
                                                <n-select 
                                                    v-model:value="item.product_id" 
                                                    :options="availableProducts" 
                                                    filterable 
                                                    placeholder="Buscar producto..."
                                                    @update:value="(val) => handleProductChange(val, index)"
                                                />
                                            </div>

                                            <!-- Cantidad -->
                                            <div class="sm:col-span-2">
                                                <label class="text-xs font-bold text-gray-500 mb-1 block">Cant.</label>
                                                <n-input-number v-model:value="item.quantity" :min="0.1" placeholder="0" />
                                            </div>

                                            <!-- Costo Unitario -->
                                            <div class="sm:col-span-3">
                                                <label class="text-xs font-bold text-gray-500 mb-1 block">Costo Unit.</label>
                                                <n-input-number v-model:value="item.unit_cost" :min="0" :precision="2">
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

                                        <!-- Subtotal Fila -->
                                        <div class="mt-2 pt-2 border-t border-gray-50 flex justify-end text-xs text-gray-500">
                                            Subtotal: <span class="font-bold ml-1 text-gray-800">{{ formatCurrency(item.quantity * item.unit_cost) }}</span>
                                        </div>
                                    </div>
                                </div>
                            </n-card>

                            <!-- Acciones Finales -->
                            <div class="flex flex-col sm:flex-row gap-4 justify-end mt-6">
                                <Link :href="route('purchases.index')">
                                    <n-button size="large" ghost>Cancelar</n-button>
                                </Link>
                                <n-button 
                                    type="primary" 
                                    size="large" 
                                    @click="submit" 
                                    :loading="form.processing"
                                    :disabled="form.items.length === 0 || form.processing"
                                >
                                    <template #icon><n-icon><SaveOutline /></n-icon></template>
                                    Guardar Orden
                                </n-button>
                            </div>

                        </div>
                    </div>
                </n-form>
            </div>
        </div>
    </AppLayout>
</template>