<script setup>
import { ref, computed } from 'vue';
import { usePermissions } from '@/Composables/usePermissions'; 
import axios from 'axios'; 
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { 
    NButton, NCard, NIcon, NTag, NGrid, NGi, NAvatar, NInput, NInputNumber, NSelect, 
    NModal, NForm, NFormItem, NEmpty, NScrollbar, createDiscreteApi, NSpin, NBadge, NTooltip,
    NList, NListItem, NThing
} from 'naive-ui';
import { 
    ArrowBackOutline, StorefrontOutline, PersonOutline, MailOutline, CallOutline, 
    SearchOutline, ArrowForwardOutline, TrashOutline, CubeOutline, PricetagOutline, 
    TimeOutline, SaveOutline, AddOutline, CreateOutline, CloudDownloadOutline,
    GlobeOutline, BriefcaseOutline, Star, CopyOutline, LogoWhatsapp,
    CardOutline, LocationOutline, DocumentTextOutline, ReceiptOutline
} from '@vicons/ionicons5';

const props = defineProps({
    supplier: Object,
    assigned_products: Array,
    documents: Array // Nueva prop para archivos
});

const { hasPermission } = usePermissions();
const { notification, dialog } = createDiscreteApi(['notification', 'dialog']);

// --- ESTADO LOCAL ---
const availableProducts = ref([]); 
const isLoadingProducts = ref(false);
const showEditorModal = ref(false); 
const productsLoaded = ref(false); 
const isEditing = ref(false); 

// Filtros y formularios
const searchQuery = ref('');
const showConfigModal = ref(false); 
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

// --- UTILIDADES ---
const copyToClipboard = (text) => {
    if (!text) return;
    navigator.clipboard.writeText(text);
    notification.success({ content: 'Copiado al portapapeles', duration: 1500 });
};

const goToWebsite = (url) => {
    if (!url) return;
    const target = url.startsWith('http') ? url : `https://${url}`;
    window.open(target, '_blank');
};

const downloadFile = (url) => {
    window.open(url, '_blank');
};

// --- LÓGICA DE CARGA ASÍNCRONA (Productos) ---
const openProductEditor = async () => {
    showEditorModal.value = true;
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
    isEditing.value = false;
    selectedProduct.value = product;
    assignForm.product_id = product.id;
    assignForm.purchase_price = product.purchase_price || 0; 
    assignForm.supplier_sku = product.sku;
    assignForm.currency = 'MXN';
    assignForm.delivery_days = 1;
    showConfigModal.value = true;
};

const openEditModal = (product) => {
    isEditing.value = true;
    selectedProduct.value = product;
    assignForm.product_id = product.id;
    assignForm.purchase_price = parseFloat(product.purchase_price); 
    assignForm.supplier_sku = product.supplier_sku;
    assignForm.currency = product.currency;
    assignForm.delivery_days = product.delivery_days;
    showConfigModal.value = true;
};

const submitAssignment = () => {
    assignForm.post(route('suppliers.products.assign', props.supplier.id), {
        preserveScroll: true,
        preserveState: true, 
        onSuccess: () => {
            showConfigModal.value = false;
            const message = isEditing.value 
                ? `Datos de ${selectedProduct.value.name} actualizados.`
                : `Se agregó ${selectedProduct.value.name} correctamente.`;

            notification.success({ title: isEditing.value ? 'Actualizado' : 'Agregado', content: message, duration: 2500 });
            
            if (!isEditing.value) {
                availableProducts.value = availableProducts.value.filter(p => p.id !== selectedProduct.value.id);
            }
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
                preserveState: true, 
                onSuccess: () => {
                    notification.success({ title: 'Desvinculado', content: 'Producto removido.', duration: 3000 });
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
        <div class="py-8 min-h-screen bg-gray-50/50">
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

                <!-- CABECERA: Info Principal -->
                <div class="bg-white rounded-3xl p-6 md:p-8 shadow-sm border border-gray-100 mb-6 relative overflow-hidden">
                    <div class="flex flex-col md:flex-row justify-between items-start gap-6 relative z-10">
                        <div class="flex items-start gap-5">
                            <div class="w-20 h-20 rounded-2xl bg-gradient-to-br from-blue-600 to-indigo-700 text-white flex items-center justify-center shadow-lg transform -rotate-3">
                                <n-icon size="40"><StorefrontOutline /></n-icon>
                            </div>
                            <div>
                                <h1 class="text-2xl md:text-3xl font-black text-gray-800 tracking-tight leading-tight mb-2">
                                    {{ supplier.company_name }}
                                </h1>
                                
                                <div class="flex flex-col sm:flex-row gap-4 text-sm text-gray-500">
                                    <div class="flex items-center gap-1.5" v-if="supplier.rfc">
                                        <n-icon class="text-gray-400"><ReceiptOutline /></n-icon>
                                        <span class="font-mono font-medium text-gray-700">{{ supplier.rfc }}</span>
                                    </div>
                                    <div class="hidden sm:block text-gray-300">|</div>
                                    <!-- Link al Sitio Web -->
                                    <div v-if="supplier.website" class="flex items-center gap-2">
                                        <a :href="supplier.website.startsWith('http') ? supplier.website : 'https://' + supplier.website" target="_blank" class="text-blue-600 hover:underline flex items-center gap-1">
                                            <n-icon><GlobeOutline /></n-icon> Sitio Web
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Botón Editar -->
                        <Link v-if="hasPermission('suppliers.edit')" :href="route('suppliers.edit', supplier.id)">
                            <n-button secondary round type="warning">
                                <template #icon><n-icon><CreateOutline /></n-icon></template>
                                Editar Datos
                            </n-button>
                        </Link>
                    </div>
                </div>

                <!-- SECCIÓN: DATOS ADMINISTRATIVOS Y BANCARIOS -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
                    
                    <!-- Columna 1: Dirección y Fiscal -->
                    <div class="lg:col-span-1 space-y-6">
                        <n-card :bordered="false" class="shadow-sm rounded-2xl border border-gray-100 h-full">
                            <template #header>
                                <div class="flex items-center gap-2 text-gray-800">
                                    <n-icon class="text-orange-500"><LocationOutline /></n-icon> 
                                    <span>Ubicación y Fiscal</span>
                                </div>
                            </template>
                            
                            <div class="space-y-4 p-3">
                                <div>
                                    <label class="text-xs font-bold text-gray-400 uppercase">Dirección</label>
                                    <p class="text-sm text-gray-700 whitespace-pre-line mt-1">
                                        {{ supplier.address || 'No registrada' }}
                                    </p>
                                </div>
                                <div v-if="supplier.rfc">
                                    <label class="text-xs font-bold text-gray-400 uppercase">RFC</label>
                                    <p class="text-sm font-mono text-gray-700 mt-1">{{ supplier.rfc }}</p>
                                </div>
                            </div>
                        </n-card>
                    </div>

                    <!-- Columna 2: Datos Bancarios -->
                    <div class="lg:col-span-1">
                        <n-card :bordered="false" class="shadow-sm rounded-2xl border border-gray-100 h-full">
                            <template #header>
                                <div class="flex items-center gap-2 text-gray-800">
                                    <n-icon class="text-emerald-500"><CardOutline /></n-icon> 
                                    <span>Datos Bancarios</span>
                                </div>
                            </template>

                            <div v-if="supplier.bank_name || supplier.clabe || supplier.account_number" class="space-y-4 p-3">
                                <div v-if="supplier.bank_name">
                                    <label class="text-xs font-bold text-gray-400 uppercase">Banco</label>
                                    <p class="text-sm font-semibold text-gray-800">{{ supplier.bank_name }}</p>
                                </div>
                                
                                <div v-if="supplier.bank_account_holder">
                                    <label class="text-xs font-bold text-gray-400 uppercase">Titular</label>
                                    <p class="text-sm text-gray-700">{{ supplier.bank_account_holder }}</p>
                                </div>

                                <div class="grid grid-cols-1 gap-3">
                                    <div v-if="supplier.account_number">
                                        <label class="text-xs font-bold text-gray-400 uppercase">Cuenta</label>
                                        <div class="flex items-center gap-2 group cursor-pointer" @click="copyToClipboard(supplier.account_number)">
                                            <p class="text-sm font-mono text-gray-700">{{ supplier.account_number }}</p>
                                            <n-icon class="text-gray-300 opacity-0 group-hover:opacity-100"><CopyOutline /></n-icon>
                                        </div>
                                    </div>
                                    <div v-if="supplier.clabe">
                                        <label class="text-xs font-bold text-gray-400 uppercase">CLABE</label>
                                        <div class="flex items-center gap-2 group cursor-pointer" @click="copyToClipboard(supplier.clabe)">
                                            <p class="text-sm font-mono text-gray-700">{{ supplier.clabe }}</p>
                                            <n-icon class="text-gray-300 opacity-0 group-hover:opacity-100"><CopyOutline /></n-icon>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div v-else class="h-full flex flex-col items-center justify-center text-gray-400 py-4">
                                <n-icon size="30" class="mb-2 opacity-50"><CardOutline /></n-icon>
                                <span class="text-xs">Sin datos bancarios</span>
                            </div>
                        </n-card>
                    </div>

                    <!-- Columna 3: Documentación -->
                    <div class="lg:col-span-1">
                        <n-card :bordered="false" class="shadow-sm rounded-2xl border border-gray-100 h-full">
                            <template #header>
                                <div class="flex items-center gap-2 text-gray-800">
                                    <n-icon class="text-blue-500"><DocumentTextOutline /></n-icon> 
                                    <span>Documentación</span>
                                    <n-badge :value="documents?.length || 0" type="info" class="ml-1" />
                                </div>
                            </template>
                            
                            <div v-if="documents && documents.length > 0">
                                <n-list hoverable clickable>
                                    <n-list-item v-for="doc in documents" :key="doc.id">
                                        <div class="flex items-center justify-between" @click="downloadFile(doc.url)">
                                            <div class="flex items-center gap-3 overflow-hidden">
                                                <div class="bg-blue-50 p-2 rounded-lg text-blue-600">
                                                    <n-icon><CloudDownloadOutline /></n-icon>
                                                </div>
                                                <div class="min-w-0">
                                                    <p class="text-sm font-medium text-gray-700 truncate">{{ doc.name }}</p>
                                                    <p class="text-[10px] text-gray-400 uppercase">{{ doc.mime_type?.split('/')[1] || 'FILE' }}</p>
                                                </div>
                                            </div>
                                        </div>
                                    </n-list-item>
                                </n-list>
                            </div>
                            <div v-else class="h-full flex flex-col items-center justify-center text-gray-400 py-4">
                                <n-icon size="30" class="mb-2 opacity-50"><DocumentTextOutline /></n-icon>
                                <span class="text-xs">Sin documentos adjuntos</span>
                            </div>
                        </n-card>
                    </div>
                </div>

                <!-- SECCIÓN: CONTACTOS -->
                <div class="mb-8">
                    <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center gap-2">
                        <n-icon class="text-blue-600"><PersonOutline /></n-icon> 
                        Equipo de Contacto
                        <n-badge :value="supplier.contacts?.length || 0" type="info" class="ml-2" />
                    </h3>

                    <n-grid x-gap="16" y-gap="16" cols="1 s:2 m:3 l:4" responsive="screen">
                        <n-gi v-for="contact in supplier.contacts" :key="contact.id">
                            <n-card :bordered="false" class="h-full shadow-sm hover:shadow-md transition-shadow rounded-2xl border border-gray-100 px-3">
                                <div class="flex flex-col h-full">
                                    <!-- Header Contacto -->
                                    <div class="flex justify-between items-start mb-2">
                                        <div class="flex items-center gap-2">
                                            <n-avatar round size="small" class="bg-gray-100 text-gray-500">
                                                {{ contact.name.charAt(0).toUpperCase() }}
                                            </n-avatar>
                                            <div v-if="contact.is_primary">
                                                <n-tag size="tiny" type="primary" round :bordered="false">Principal</n-tag>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Datos -->
                                    <div class="flex-1">
                                        <h4 class="font-bold text-gray-800 text-base leading-tight">{{ contact.name }}</h4>
                                        <p class="text-xs text-gray-500 font-medium mb-3 flex items-center gap-1">
                                            <n-icon v-if="contact.job_title"><BriefcaseOutline /></n-icon>
                                            {{ contact.job_title || 'Sin puesto definido' }}
                                        </p>

                                        <div class="space-y-2 mt-2">
                                            <div v-if="contact.email" class="flex items-center justify-between bg-gray-50 p-1.5 rounded-lg group">
                                                <div class="flex items-center gap-2 overflow-hidden">
                                                    <n-icon class="text-indigo-400 flex-shrink-0"><MailOutline /></n-icon>
                                                    <span class="text-xs text-gray-600 truncate" :title="contact.email">{{ contact.email }}</span>
                                                </div>
                                                <n-button text size="tiny" class="opacity-0 group-hover:opacity-100" @click="copyToClipboard(contact.email)">
                                                    <n-icon><CopyOutline /></n-icon>
                                                </n-button>
                                            </div>

                                            <div v-if="contact.phone" class="flex items-center justify-between bg-gray-50 p-1.5 rounded-lg">
                                                <div class="flex items-center gap-2">
                                                    <n-icon class="text-green-500 flex-shrink-0"><LogoWhatsapp /></n-icon>
                                                    <span class="text-xs text-gray-600">{{ contact.phone }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Notas -->
                                    <div v-if="contact.notes" class="mt-3 pt-2 border-t border-gray-100">
                                        <p class="text-[12px] text-gray-400 italic line-clamp-2" :title="contact.notes">
                                            "{{ contact.notes }}"
                                        </p>
                                    </div>
                                </div>
                            </n-card>
                        </n-gi>
                        
                        <!-- Tarjeta Empty State si no hay contactos -->
                        <n-gi v-if="!supplier.contacts || supplier.contacts.length === 0">
                            <div class="h-full border-2 border-dashed border-gray-200 rounded-2xl flex flex-col items-center justify-center p-6 text-gray-400">
                                <n-icon size="32" class="mb-2 opacity-50"><PersonOutline /></n-icon>
                                <span class="text-sm">Sin contactos registrados</span>
                            </div>
                        </n-gi>
                    </n-grid>
                </div>

                <!-- SECCIÓN: PRODUCTOS (CATÁLOGO) -->
                <div class="bg-white rounded-3xl shadow-lg border border-gray-100 overflow-hidden min-h-[400px]">
                    
                    <!-- Header Sección -->
                    <div class="p-6 border-b border-gray-100 bg-gray-50/50 flex flex-col md:flex-row justify-between items-center gap-4">
                        <div>
                            <h3 class="font-bold text-lg text-gray-800 flex items-center gap-2">
                                <n-icon class="text-blue-600"><CubeOutline /></n-icon>
                                Catálogo de Productos
                            </h3>
                            <p class="text-sm text-gray-500">Artículos disponibles para Órdenes de Compra</p>
                        </div>
                        
                        <!-- Botón Acción -->
                        <div v-if="assigned_products.length > 0 && hasPermission('suppliers.edit')">
                            <n-button type="info" round ghost @click="openProductEditor">
                                <template #icon><n-icon><CreateOutline /></n-icon></template>
                                Administrar Productos
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
                            <h4 class="text-xl font-bold text-gray-700 mb-2">Catálogo Vacío</h4>
                            <p class="text-gray-500 max-w-md mb-8">
                                Asigna productos a este proveedor para agilizar tus compras y controlar costos.
                            </p>
                            <n-button v-if="hasPermission('suppliers.edit')" type="primary" size="large" round @click="openProductEditor">
                                <template #icon><n-icon><AddOutline /></n-icon></template>
                                Asignar Productos Ahora
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
                                        <span class="text-xs text-gray-400 flex items-center gap-1" title="Tiempo de entrega">
                                            <n-icon><TimeOutline /></n-icon> {{ product.delivery_days }}d
                                        </span>
                                    </div>
                                </div>

                                <!-- Botón rápido de desvincular -->
                                <div v-if="hasPermission('suppliers.edit')" class="absolute top-2 right-2 opacity-0 group-hover:opacity-100 transition-opacity flex gap-1">
                                    <n-tooltip trigger="hover">
                                        <template #trigger>
                                            <n-button circle size="tiny" type="info" secondary @click="openEditModal(product)">
                                                <n-icon><CreateOutline /></n-icon>
                                            </n-button>
                                        </template>
                                        Editar Costo/Días
                                    </n-tooltip>
                                    
                                    <n-button circle size="tiny" type="error" quaternary @click="detachProduct(product)">
                                        <n-icon><TrashOutline /></n-icon>
                                    </n-button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- MODAL GRANDE: EDITOR DE PRODUCTOS -->
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
                                <span>Gestión de Catálogo</span>
                            </div>
                        </template>
                        <template #header-extra>
                            <n-button circle quaternary @click="showEditorModal = false">
                                <template #icon>X</template> 
                            </n-button>
                        </template>

                        <!-- CONTENIDO DEL MODAL -->
                        <div class="flex flex-col h-full overflow-hidden">
                            <div v-if="isLoadingProducts" class="flex flex-col items-center justify-center h-full space-y-4">
                                <n-spin size="large" />
                                <p class="text-gray-500 animate-pulse">Cargando inventario global...</p>
                            </div>

                            <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-6 h-full p-1">
                                <!-- Columna Izquierda: Global -->
                                <div class="bg-gray-50 rounded-2xl border border-gray-200 flex flex-col overflow-hidden h-full">
                                    <div class="p-4 bg-white border-b border-gray-100">
                                        <h4 class="font-bold text-gray-700 text-sm mb-2 flex justify-between">
                                            Inventario Global
                                            <n-badge :value="availableProducts.length" type="info" />
                                        </h4>
                                        <n-input v-model:value="searchQuery" placeholder="Buscar producto..." size="small" round clearable>
                                            <template #prefix><n-icon :component="SearchOutline" /></template>
                                        </n-input>
                                    </div>
                                    
                                    <div class="flex-1 overflow-y-auto p-2 space-y-2">
                                        <div v-if="filteredAvailable.length === 0" class="text-center py-8 text-gray-400 text-sm">
                                            Sin resultados
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

                                <!-- Columna Derecha: Asignados -->
                                <div class="bg-blue-50/30 rounded-2xl border border-blue-100 flex flex-col overflow-hidden h-full">
                                    <div class="p-4 bg-white/50 border-b border-blue-100">
                                        <h4 class="font-bold text-blue-800 text-sm mb-2">
                                            Asignados a este proveedor
                                        </h4>
                                        <p class="text-xs text-blue-600">Click en editar para modificar costos</p>
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
                                                    <span class="text-xs text-green-600 font-bold">{{ formatCurrency(product.purchase_price, product.currency) }}</span>
                                                </div>
                                            </div>
                                            <div class="flex items-center gap-1">
                                                <n-button circle size="tiny" type="info" secondary @click="openEditModal(product)">
                                                    <template #icon><n-icon><CreateOutline /></n-icon></template>
                                                </n-button>
                                                <n-button circle size="tiny" type="error" quaternary @click="detachProduct(product)">
                                                    <template #icon><n-icon><TrashOutline /></n-icon></template>
                                                </n-button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="p-4 border-t border-gray-100 text-right">
                                <n-button @click="showEditorModal = false">Finalizar</n-button>
                            </div>
                        </div>
                    </n-card>
                </n-modal>

                <!-- MODAL PEQUEÑO: CONFIGURACIÓN -->
                <n-modal v-model:show="showConfigModal">
                    <n-card style="width: 400px" :title="isEditing ? 'Editar Costos' : 'Agregar Producto'" :bordered="false" size="huge" role="dialog" aria-modal="true">
                        <n-form :model="assignForm" label-placement="top" class="p-1">
                            <n-form-item label="Precio de Compra Pactado" required>
                                <n-input-number v-model:value="assignForm.purchase_price" :min="0" :precision="2" class="w-full">
                                    <template #prefix>$</template>
                                </n-input-number>
                            </n-form-item>
                            
                            <div class="grid grid-cols-2 gap-3">
                                <n-form-item label="Moneda">
                                    <n-select v-model:value="assignForm.currency" :options="currencyOptions" />
                                </n-form-item>
                                <n-form-item label="Días Entrega">
                                    <n-input-number v-model:value="assignForm.delivery_days" :min="0" placeholder="Ej. 3" />
                                </n-form-item>
                            </div>

                            <n-form-item label="SKU del Proveedor (Opcional)">
                                <n-input v-model:value="assignForm.supplier_sku" placeholder="Código interno del proveedor" />
                            </n-form-item>
                            
                            <div class="flex justify-end gap-2 mt-4">
                                <n-button @click="showConfigModal = false" ghost>Cancelar</n-button>
                                <n-button type="primary" @click="submitAssignment" :loading="assignForm.processing">
                                    {{ isEditing ? 'Guardar Cambios' : 'Asignar Producto' }}
                                </n-button>
                            </div>
                        </n-form>
                    </n-card>
                </n-modal>

            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
:deep(.n-card__content) {
    padding: 0;
    height: 100%;
}
</style>