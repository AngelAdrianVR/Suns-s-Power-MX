<script setup>
import { ref, computed } from 'vue';
import axios from 'axios'; // Importar Axios para la petición async
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { 
    NButton, NCard, NIcon, NTag, NGrid, NGi, NAvatar, NInput, NInputNumber, NSelect, 
    NModal, NForm, NFormItem, NEmpty, NScrollbar, createDiscreteApi, NSpin, NBadge
} from 'naive-ui';
import { 
    ArrowBackOutline, StorefrontOutline, PersonOutline, MailOutline, CallOutline, 
    SearchOutline, ArrowForwardOutline, TrashOutline, CubeOutline, PricetagOutline, 
    TimeOutline, SaveOutline, AddOutline, CreateOutline, CloudDownloadOutline
} from '@vicons/ionicons5';

const props = defineProps({
    supplier: Object,
    assigned_products: Array, // Lista inicial ligera
});

const { notification, dialog } = createDiscreteApi(['notification', 'dialog']);

// --- ESTADO LOCAL ---
const availableProducts = ref([]); // Se llenará asíncronamente
const isLoadingProducts = ref(false);
const showEditorModal = ref(false); // Controla la visibilidad del componente de edición
const productsLoaded = ref(false); // Flag para no recargar si ya se cargaron

// Filtros y formularios
const searchQuery = ref('');
const showConfigModal = ref(false); // Modal pequeño para precio/días
const selectedProduct = ref(null);

const assignForm = useForm({
    product_id: null,
    purchase_price: 0,
    currency: 'MXN',
    supplier_sku: '',
    delivery_days: 1,
});

const currencyOptions = [
    { label: 'MXN - Pesos Mexicanos', value: 'MXN' },
    { label: 'USD - Dólares Americanos', value: 'USD' }
];

// --- LÓGICA DE CARGA ASÍNCRONA ---
const openProductEditor = async () => {
    showEditorModal.value = true;

    // Si ya cargamos los productos antes, no volvemos a llamar a la API
    // (Opcional: puedes quitar este if si quieres refrescar siempre)
    if (productsLoaded.value) return;

    isLoadingProducts.value = true;
    try {
        const url = route('suppliers.products.fetch', props.supplier.id);
        const response = await axios.get(url);
        availableProducts.value = response.data;
        productsLoaded.value = true;
    } catch (error) {
        console.error("Error cargando productos:", error);
        notification.error({
            title: 'Error de Conexión',
            content: 'No se pudo cargar el catálogo de productos disponibles.'
        });
        showEditorModal.value = false;
    } finally {
        isLoadingProducts.value = false;
    }
};

// --- Lógica del Editor (Filtros) ---
const filteredAvailable = computed(() => {
    if (!searchQuery.value) return availableProducts.value;
    const lowerQuery = searchQuery.value.toLowerCase();
    return availableProducts.value.filter(p => 
        p.name.toLowerCase().includes(lowerQuery) || 
        p.sku.toLowerCase().includes(lowerQuery)
    );
});

// --- ACCIONES DE ASIGNACIÓN ---

const openConfigModal = (product) => {
    selectedProduct.value = product;
    assignForm.product_id = product.id;
    assignForm.purchase_price = product.purchase_price || 0; 
    assignForm.supplier_sku = product.sku;
    assignForm.currency = 'MXN';
    assignForm.delivery_days = 1;
    showConfigModal.value = true;
};

const submitAssignment = () => {
    assignForm.post(route('suppliers.products.assign', props.supplier.id), {
        preserveScroll: true,
        onSuccess: () => {
            showConfigModal.value = false;
            notification.success({
                title: 'Producto Agregado',
                content: `Se agregó ${selectedProduct.value.name} correctamente.`,
                duration: 2500
            });
            
            // Actualizar localmente la lista disponible (quitar el asignado)
            availableProducts.value = availableProducts.value.filter(p => p.id !== selectedProduct.value.id);
            
            selectedProduct.value = null;
            assignForm.reset();
        },
        onError: () => {
            notification.error({ title: 'Error', content: 'Revisa los datos del formulario.' });
        }
    });
};

const detachProduct = (product) => {
    dialog.warning({
        title: 'Desvincular Producto',
        content: `¿Quitar "${product.name}" de este proveedor?`,
        positiveText: 'Sí, quitar',
        negativeText: 'Cancelar',
        onPositiveClick: () => {
            router.delete(route('suppliers.products.detach', { supplier: props.supplier.id, product: product.id }), {
                preserveScroll: true,
                onSuccess: () => {
                    notification.success({ title: 'Desvinculado', content: 'Producto removido.' });
                    // Opcional: Si quieres que vuelva a aparecer en disponibles, tendrías que recargar o agregarlo manualmente al array
                    // Por simplicidad, seteamos productsLoaded = false para forzar recarga si vuelve a abrir el editor
                    productsLoaded.value = false; 
                }
            });
        }
    });
};

const formatCurrency = (amount, currency) => {
    return new Intl.NumberFormat('es-MX', { style: 'currency', currency: currency }).format(amount);
};
</script>

<template>
    <AppLayout :title="supplier.company_name">
        <div class="py-8 min-h-screen">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                
                <!-- Navegación -->
                <div class="mb-6">
                    <Link :href="route('suppliers.index')">
                        <n-button text class="hover:text-gray-900 text-gray-500 transition-colors">
                            <template #icon><n-icon><ArrowBackOutline /></n-icon></template>
                            Volver al directorio
                        </n-button>
                    </Link>
                </div>

                <!-- Info Proveedor -->
                <div class="bg-white rounded-3xl p-6 md:p-8 shadow-sm border border-gray-100 mb-8">
                    <div class="flex flex-col md:flex-row justify-between items-start gap-6">
                        <div class="flex items-start gap-4">
                            <div class="w-16 h-16 rounded-2xl bg-blue-50 text-blue-600 flex items-center justify-center shadow-inner">
                                <n-icon size="32"><StorefrontOutline /></n-icon>
                            </div>
                            <div>
                                <h1 class="text-2xl md:text-3xl font-black text-gray-800 tracking-tight leading-none mb-2">
                                    {{ supplier.company_name }}
                                </h1>
                                <div class="flex flex-wrap gap-4 text-sm text-gray-500">
                                    <span v-if="supplier.contact_name" class="flex items-center gap-1">
                                        <n-icon class="text-gray-400"><PersonOutline /></n-icon> {{ supplier.contact_name }}
                                    </span>
                                    <span v-if="supplier.email" class="flex items-center gap-1">
                                        <n-icon class="text-indigo-400"><MailOutline /></n-icon> {{ supplier.email }}
                                    </span>
                                    <span v-if="supplier.phone" class="flex items-center gap-1">
                                        <n-icon class="text-green-500"><CallOutline /></n-icon> {{ supplier.phone }}
                                    </span>
                                </div>
                            </div>
                        </div>
                        <Link :href="route('suppliers.edit', supplier.id)">
                            <n-button secondary round type="warning">Editar Datos</n-button>
                        </Link>
                    </div>
                </div>

                <!-- SECCIÓN DE PRODUCTOS (VISTA PRINCIPAL) -->
                <div class="bg-white rounded-3xl shadow-lg border border-gray-100 overflow-hidden min-h-[400px]">
                    
                    <!-- Header Sección -->
                    <div class="p-6 border-b border-gray-100 bg-gray-50/50 flex flex-col md:flex-row justify-between items-center gap-4">
                        <div>
                            <h3 class="font-bold text-lg text-gray-800 flex items-center gap-2">
                                <n-icon class="text-blue-600"><CubeOutline /></n-icon>
                                Catálogo del Proveedor
                            </h3>
                            <p class="text-sm text-gray-500">Productos habilitados para compra</p>
                        </div>
                        
                        <!-- Botón Principal de Acción -->
                        <div v-if="assigned_products.length > 0">
                            <n-button type="info" round @click="openProductEditor">
                                <template #icon><n-icon><CreateOutline /></n-icon></template>
                                Editar Productos
                            </n-button>
                        </div>
                    </div>

                    <!-- Contenido Lista -->
                    <div class="p-6">
                        <!-- CASO 1: Sin Productos Asignados -->
                        <div v-if="assigned_products.length === 0" class="flex flex-col items-center justify-center py-16 text-center">
                            <div class="bg-blue-50 p-4 rounded-full mb-4">
                                <n-icon size="48" class="text-blue-400"><CloudDownloadOutline /></n-icon>
                            </div>
                            <h4 class="text-xl font-bold text-gray-700 mb-2">Sin productos asignados</h4>
                            <p class="text-gray-500 max-w-md mb-8">
                                Este proveedor aún no tiene productos vinculados. Agrega productos de tu inventario general para poder generar órdenes de compra.
                            </p>
                            <n-button type="primary" size="large" round @click="openProductEditor">
                                <template #icon><n-icon><AddOutline /></n-icon></template>
                                Agregar Productos a este Proveedor
                            </n-button>
                        </div>

                        <!-- CASO 2: Lista de Productos (Grid) -->
                        <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            <div 
                                v-for="product in assigned_products" 
                                :key="product.id"
                                class="border border-gray-100 rounded-2xl p-4 flex gap-4 hover:shadow-md transition-shadow bg-white relative group"
                            >
                                <!-- Imagen -->
                                <n-avatar 
                                    shape="square" 
                                    :size="64" 
                                    :src="product.image_url" 
                                    class="bg-gray-50 rounded-xl border border-gray-100 flex-shrink-0"
                                >
                                    <template #fallback><n-icon class="text-gray-300"><CubeOutline /></n-icon></template>
                                </n-avatar>

                                <!-- Info -->
                                <div class="flex-1 min-w-0">
                                    <h5 class="font-bold text-gray-800 text-sm truncate" :title="product.name">{{ product.name }}</h5>
                                    <p class="text-xs text-gray-400 font-mono mb-2">{{ product.sku }}</p>
                                    
                                    <div class="flex items-center gap-2">
                                        <n-tag size="small" type="success" :bordered="false" class="font-bold">
                                            {{ formatCurrency(product.purchase_price, product.currency) }}
                                        </n-tag>
                                        <span class="text-xs text-gray-400 flex items-center gap-1">
                                            <n-icon><TimeOutline /></n-icon> {{ product.delivery_days }}d
                                        </span>
                                    </div>
                                </div>

                                <!-- Botón rápido de desvincular (solo visible en hover) -->
                                <div class="absolute top-2 right-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                    <n-button circle size="tiny" type="error" quaternary @click="detachProduct(product)">
                                        <template #icon><n-icon><TrashOutline /></n-icon></template>
                                    </n-button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- MODAL GRANDE: EDITOR DE PRODUCTOS (TRANSFERENCIA) -->
                <n-modal v-model:show="showEditorModal">
                    <n-card
                        style="width: 1000px; max-width: 95%; height: 85vh; display: flex; flex-direction: column;"
                        class="custom-modal-card"
                        :bordered="false"
                        size="huge"
                        role="dialog"
                        aria-modal="true"
                    >
                        <template #header>
                            <div class="flex items-center gap-3">
                                <n-icon size="24" class="text-indigo-600"><CreateOutline /></n-icon>
                                <span>Administrar Catálogo del Proveedor</span>
                            </div>
                        </template>
                        <template #header-extra>
                            <n-button circle quaternary @click="showEditorModal = false">
                                <template #icon>X</template> <!-- Fallback icon if needed or just use default close -->
                            </n-button>
                        </template>

                        <!-- CONTENIDO DEL MODAL (GRID 2 COLUMNAS) -->
                        <div class="flex flex-col h-full overflow-hidden">
                            
                            <!-- Spinner de Carga -->
                            <div v-if="isLoadingProducts" class="flex flex-col items-center justify-center h-full space-y-4">
                                <n-spin size="large" />
                                <p class="text-gray-500 animate-pulse">Cargando catálogo de productos...</p>
                            </div>

                            <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-6 h-full p-1">
                                
                                <!-- COLUMNA IZQUIERDA: DISPONIBLES -->
                                <div class="bg-gray-50 rounded-2xl border border-gray-200 flex flex-col overflow-hidden h-full">
                                    <div class="p-4 bg-white border-b border-gray-100">
                                        <h4 class="font-bold text-gray-700 text-sm mb-2 flex justify-between">
                                            Disponibles para agregar
                                            <n-badge :value="availableProducts.length" type="info" />
                                        </h4>
                                        <n-input v-model:value="searchQuery" placeholder="Buscar..." size="small" round clearable>
                                            <template #prefix><n-icon :component="SearchOutline" /></template>
                                        </n-input>
                                    </div>
                                    
                                    <div class="flex-1 overflow-y-auto p-2 space-y-2">
                                        <div v-if="filteredAvailable.length === 0" class="text-center py-8 text-gray-400 text-sm">
                                            No hay coincidencias
                                        </div>
                                        <div 
                                            v-for="product in filteredAvailable" 
                                            :key="product.id"
                                            class="bg-white p-2 rounded-xl border border-gray-100 hover:border-blue-300 cursor-pointer flex items-center justify-between group transition-all"
                                            @click="openConfigModal(product)"
                                        >
                                            <div class="flex items-center gap-3 overflow-hidden">
                                                <n-avatar shape="square" :size="40" :src="product.image_url" class="flex-shrink-0 bg-gray-100" />
                                                <div class="min-w-0">
                                                    <p class="font-semibold text-gray-800 text-sm truncate">{{ product.name }}</p>
                                                    <p class="text-xs text-gray-400 font-mono">{{ product.sku }}</p>
                                                </div>
                                            </div>
                                            <n-button circle size="tiny" type="primary" class="opacity-0 group-hover:opacity-100">
                                                <template #icon><n-icon><ArrowForwardOutline /></n-icon></template>
                                            </n-button>
                                        </div>
                                    </div>
                                </div>

                                <!-- COLUMNA DERECHA: ASIGNADOS (Read-Only en el editor, con opción de quitar) -->
                                <div class="bg-blue-50/30 rounded-2xl border border-blue-100 flex flex-col overflow-hidden h-full">
                                    <div class="p-4 bg-white/50 border-b border-blue-100">
                                        <h4 class="font-bold text-blue-800 text-sm mb-2 flex justify-between">
                                            Asignados ({{ assigned_products.length }})
                                        </h4>
                                        <p class="text-xs text-blue-600">Productos actuales de este proveedor</p>
                                    </div>
                                    
                                    <div class="flex-1 overflow-y-auto p-2 space-y-2">
                                        <div 
                                            v-for="product in assigned_products" 
                                            :key="product.id"
                                            class="bg-white p-2 rounded-xl border border-gray-100 flex items-center justify-between"
                                        >
                                            <div class="flex items-center gap-3 overflow-hidden">
                                                <n-avatar shape="square" :size="40" :src="product.image_url" class="flex-shrink-0 bg-gray-100" />
                                                <div class="min-w-0">
                                                    <p class="font-semibold text-gray-800 text-sm truncate">{{ product.name }}</p>
                                                    <div class="flex gap-2">
                                                        <span class="text-xs text-green-600 font-bold">{{ formatCurrency(product.purchase_price, product.currency) }}</span>
                                                    </div>
                                                </div>
                                            </div>
                                            <n-button circle size="tiny" type="error" quaternary @click="detachProduct(product)">
                                                <template #icon><n-icon><TrashOutline /></n-icon></template>
                                            </n-button>
                                        </div>
                                    </div>
                                </div>

                            </div>
                            
                            <div class="p-4 border-t border-gray-100 text-right">
                                <n-button @click="showEditorModal = false">Cerrar Editor</n-button>
                            </div>
                        </div>
                    </n-card>
                </n-modal>

                <!-- MODAL PEQUEÑO: CONFIGURACIÓN DE PIVOTE -->
                <n-modal v-model:show="showConfigModal">
                    <n-card style="width: 400px" title="Configurar Producto" :bordered="false" size="huge" role="dialog" aria-modal="true">
                        <n-form :model="assignForm" label-placement="top" class="p-3">
                            <n-form-item label="Precio de Compra" required>
                                <n-input-number v-model:value="assignForm.purchase_price" :min="0" :precision="2" class="w-full">
                                    <template #prefix>$</template>
                                </n-input-number>
                            </n-form-item>
                            <n-form-item label="Moneda">
                                <n-select v-model:value="assignForm.currency" :options="currencyOptions" />
                            </n-form-item>
                            <n-form-item label="Días de Entrega Promedio">
                                <n-input-number v-model:value="assignForm.delivery_days" :min="0" />
                            </n-form-item>
                            
                            <div class="flex justify-end gap-2 mt-4">
                                <n-button @click="showConfigModal = false">Cancelar</n-button>
                                <n-button type="primary" @click="submitAssignment" :loading="assignForm.processing">Agregar</n-button>
                            </div>
                        </n-form>
                    </n-card>
                </n-modal>

            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
/* Asegura que el contenido del modal ocupe el alto disponible */
:deep(.n-card__content) {
    padding: 0;
    height: 100%;
}
</style>