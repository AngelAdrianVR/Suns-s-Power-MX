<script setup>
import { computed, ref, watch, h } from 'vue';
import { usePermissions } from '@/Composables/usePermissions';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import AppLayout from '@/Layouts/AppLayout.vue';
import { 
    NCard, NButton, NIcon, NTag, NNumberAnimation, NStatistic, NGrid, NGi, NDivider, NSpace, NSelect, NAvatar, NSpin,
    NTimeline, NTimelineItem, NModal, NForm, NFormItem, NInput, NInputNumber, createDiscreteApi, NDatePicker, NSkeleton,
    NConfigProvider, esAR, dateEsAR, NList, NListItem, NThing, NEmpty, NPopconfirm
} from 'naive-ui';
import { 
    ArrowBackOutline, CubeOutline, PricetagOutline, LocationOutline, AlertCircleOutline, CreateOutline, SearchOutline,
    TimeOutline, SwapHorizontalOutline, BuildOutline, ClipboardOutline, CalendarOutline, AttachOutline, 
    DocumentTextOutline, CloudDownloadOutline, ImageOutline, TrashOutline
} from '@vicons/ionicons5';

defineOptions({
    name: 'ProductShow'
});

const props = defineProps({
    product: {
        type: Object,
        required: true
    },
    initial_movements: {
        type: Array,
        default: () => []
    },
    server_date: {
        type: Number,
        default: Date.now()
    }
});

// Lógica de Notificaciones
const { notification } = createDiscreteApi(['notification']);
const { hasPermission } = usePermissions();

// --- Data Refs (convertido de data()) ---
const searchQuery = ref(null);
const searchOptions = ref([]);
const loadingSearch = ref(false);

// --- Lógica del Historial por Mes ---
const selectedMonth = ref(props.server_date);
const movements = ref(props.initial_movements);
const loadingHistory = ref(false);

// Configuración de localización
const locale = esAR; 
const dateLocale = dateEsAR; 

// Función para cargar historial
const fetchHistory = async (timestamp) => {
    const targetDate = timestamp ? new Date(timestamp) : new Date();
    
    if (!timestamp) {
        const now = Date.now();
        selectedMonth.value = now;
        return; 
    }

    loadingHistory.value = true;
    const month = targetDate.getMonth() + 1; 
    const year = targetDate.getFullYear();

    try {
        const response = await axios.get(route('products.history', props.product.id), {
            params: { month, year }
        });
        movements.value = response.data;
    } catch (error) {
        console.error("Error cargando historial:", error);
        notification.error({ 
            title: 'Error de conexión', 
            content: 'No se pudo cargar el historial. Verifica tu conexión.' 
        });
    } finally {
        loadingHistory.value = false;
    }
};

watch(selectedMonth, (newValue) => {
    if (newValue) {
        fetchHistory(newValue);
    }
});

// --- Lógica del Modal de Ajuste ---
const showAdjustmentModal = ref(false);
const adjustmentFormRef = ref(null);

const adjustmentForm = useForm({
    current_stock: props.product.stock, 
    adjustment_note: '', 
});

const adjustmentRules = {
    current_stock: { required: true, type: 'number', message: 'Ingresa la cantidad real', trigger: 'blur' },
    adjustment_note: { required: true, message: 'La nota es obligatoria para auditoría', trigger: 'blur' }
};

const openAdjustmentModal = () => {
    adjustmentForm.current_stock = props.product.stock;
    adjustmentForm.adjustment_note = '';
    showAdjustmentModal.value = true;
};

const submitAdjustment = () => {
    adjustmentFormRef.value?.validate((errors) => {
        if (!errors) {
            adjustmentForm.post(route('products.adjust_stock', props.product.id), {
                preserveScroll: true,
                onSuccess: () => {
                    showAdjustmentModal.value = false;
                    notification.success({
                        title: 'Inventario Ajustado',
                        content: 'El stock se ha actualizado y registrado en el historial.',
                        duration: 3000
                    });
                    fetchHistory(selectedMonth.value);
                },
                onError: () => {
                    notification.error({ title: 'Error', content: 'No se pudo procesar el ajuste.' });
                }
            });
        }
    });
};

// --- Computed ---
const formattedPrice = computed(() => {
    return new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(props.product.sale_price);
});

const stockStatus = computed(() => {
    if (props.product.stock <= 0) return { type: 'error', text: 'Agotado', color: 'bg-red-100 text-red-600' };
    if (props.product.stock <= props.product.min_stock) return { type: 'warning', text: 'Stock Bajo', color: 'bg-amber-100 text-amber-600' };
    return { type: 'success', text: 'Disponible', color: 'bg-green-100 text-green-600' };
});

// --- Methods (convertidos a funciones constantes) ---
const goBack = () => {
    router.visit(route('products.index'));
};

const goToEdit = () => {
    router.visit(route('products.edit', props.product.id));
};

const handleSearch = async (query) => {
    if (!query) {
        searchOptions.value = [];
        return;
    }
    loadingSearch.value = true;
    try {
        const response = await axios.get(route('products.search'), { params: { query } });
        searchOptions.value = response.data;
    } catch (error) {
        console.error("Error buscando productos:", error);
    } finally {
        loadingSearch.value = false;
    }
};

const handleSelectProduct = (id) => {
    router.visit(route('products.show', id));
};

const renderProductOption = (option) => {
    if (!option) return null;
    const imageUrl = option.image_url && option.image_url.length > 0 ? option.image_url : undefined;

    return h('div', { class: 'flex items-center gap-3 p-1' }, [
        h(NAvatar, {
            src: imageUrl,
            shape: 'square',
            size: 40,
            class: 'flex-shrink-0 bg-gray-100 rounded-lg border border-gray-100 block',
            style: { width: '40px', height: '40px' }, 
            objectFit: 'cover',
            fallbackSrc: ''
        }, { 
            default: () => h(NIcon, { class: 'text-gray-300' }, { default: () => h(CubeOutline) }) 
        }),
        h('div', { class: 'flex flex-col text-left' }, [
            h('span', { class: 'font-semibold text-gray-800 text-sm leading-tight' }, option.name),
            h('span', { class: 'text-xs text-gray-400 font-mono mt-0.5' }, option.sku)
        ])
    ]);
};

const getTimelineType = (type) => {
    if (type === 'Entrada') return 'success';
    if (type === 'Salida') return 'error';
    return 'info';
};

const getFileIcon = (mimeType) => {
    if (mimeType && mimeType.includes('image')) return ImageOutline;
    if (mimeType && mimeType.includes('pdf')) return DocumentTextOutline;
    return AttachOutline;
};

// --- Nueva Función para Eliminar Archivo ---
const deleteFile = (fileId) => {
    router.delete(route('media.delete-file', fileId), {
        preserveScroll: true,
        onSuccess: () => {
            notification.success({
                title: 'Archivo Eliminado',
                content: 'El archivo adjunto se ha eliminado correctamente.',
                duration: 3000
            });
        },
        onError: () => {
            notification.error({
                title: 'Error',
                content: 'No se pudo eliminar el archivo. Inténtalo de nuevo.',
                duration: 4000
            });
        }
    });
};
</script>

<template>
    <AppLayout :title="product.name">
        <div class="py-8 min-h-screen">
            <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
                
                <!-- Header de Navegación -->
                <div class="mb-6 flex flex-col md:flex-row items-center justify-between gap-4">
                    <n-button text @click="goBack" class="hover:text-gray-900 text-gray-500 transition-colors self-start md:self-auto">
                        <template #icon>
                            <n-icon size="20"><ArrowBackOutline /></n-icon>
                        </template>
                        Volver
                    </n-button>
                    
                    <div class="w-full md:w-96">
                        <n-select
                            v-model:value="searchQuery"
                            filterable
                            placeholder="Buscar otro producto (Nombre o SKU)..."
                            :options="searchOptions"
                            :loading="loadingSearch"
                            clearable
                            remote
                            size="large"
                            class="shadow-sm rounded-xl"
                            @search="handleSearch"
                            @update:value="handleSelectProduct"
                            :render-label="renderProductOption"
                        >
                            <template #arrow>
                                <n-icon><SearchOutline /></n-icon>
                            </template>
                        </n-select>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                    
                    <!-- Columna Izquierda: Imagen y Stock -->
                    <div class="md:col-span-1 space-y-6">
                        <!-- Imagen -->
                        <div class="bg-white rounded-3xl p-2 shadow-lg border border-gray-100 overflow-hidden relative group">
                            <div class="aspect-square rounded-2xl overflow-hidden bg-gray-50 relative">
                                <img 
                                    v-if="product.image_url" 
                                    :src="product.image_url" 
                                    class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105"
                                    alt="Producto"
                                />
                                <div v-else class="w-full h-full flex flex-col items-center justify-center text-gray-300">
                                    <n-icon size="64"><CubeOutline /></n-icon>
                                    <span class="text-sm mt-2 font-medium">Sin imagen</span>
                                </div>
                                <div class="absolute top-3 right-3 px-3 py-1 rounded-full text-xs font-bold shadow-sm backdrop-blur-md"
                                     :class="stockStatus.color">
                                    {{ stockStatus.text }}
                                </div>
                            </div>
                        </div>

                        <!-- Tarjeta de Inventario -->
                        <div class="bg-white rounded-3xl p-6 shadow-sm border border-gray-100 relative overflow-hidden">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="font-bold text-lg text-gray-800 flex items-center gap-2">
                                    <n-icon class="text-indigo-500"><LocationOutline /></n-icon>
                                    Inventario
                                </h3>
                                <!-- Botón Ajuste Rápido -->
                                <n-button v-if="hasPermission('products.adjust_stock')" size="small" secondary circle type="info" @click="openAdjustmentModal">
                                    <template #icon><n-icon><ClipboardOutline /></n-icon></template>
                                </n-button>
                            </div>

                            <div class="space-y-4">
                                <div class="bg-gray-50 rounded-2xl p-4 border border-gray-100 flex justify-between items-center">
                                    <span class="text-xs text-gray-400 font-semibold uppercase">Actual</span>
                                    <div class="text-2xl font-bold text-gray-800">{{ product.stock }}</div>
                                </div>
                                <div class="bg-gray-50 rounded-2xl p-4 border border-gray-100 flex justify-between items-center">
                                    <span class="text-xs text-gray-400 font-semibold uppercase">Mínimo permitido</span>
                                    <div class="text-lg font-bold text-gray-600">{{ product.min_stock }}</div>
                                </div>
                                <div class="bg-gray-50 rounded-2xl p-4 border border-gray-100">
                                    <span class="text-xs text-gray-400 font-semibold uppercase block mb-1">Ubicación</span>
                                    <div class="text-sm font-medium text-gray-800 truncate" :title="product.location">
                                        {{ product.location }}
                                    </div>
                                </div>
                            </div>
                            <n-button v-if="hasPermission('products.edit')" type="warning" block secondary class="mt-4" @click="goToEdit">
                                Editar Producto
                            </n-button>
                        </div>
                    </div>

                    <!-- Columna Derecha: Información e Historial -->
                    <div class="md:col-span-2 space-y-6">
                        
                        <!-- Tarjeta Info Principal -->
                        <div class="bg-white rounded-3xl p-6 md:p-8 shadow-sm border border-gray-100">
                            <div class="flex flex-col gap-1 mb-4">
                                <div class="flex items-center gap-2">
                                    <n-tag type="default" size="small" round :bordered="false" class="bg-gray-100 text-gray-500 font-mono">
                                        {{ product.sku }}
                                    </n-tag>
                                    <n-tag type="primary" size="small" round :bordered="false" class="bg-blue-50 text-blue-600">
                                        {{ product.category }}
                                    </n-tag>
                                </div>
                                <h1 class="text-3xl md:text-4xl font-black text-gray-800 tracking-tight">{{ product.name }}</h1>
                            </div>

                            <n-statistic label="Precio de Venta" class="mb-6">
                                <template #prefix>$</template>
                                <span class="text-4xl font-bold text-emerald-600">
                                    <n-number-animation 
                                        ref="numberAnimationInstRef"
                                        :from="0" 
                                        :to="product.sale_price" 
                                        :active="true" 
                                        :precision="2" 
                                        show-separator
                                    />
                                </span>
                                <template #suffix>MXN</template>
                            </n-statistic>
                            <n-statistic v-if="hasPermission('products.view_costs')" label="Precio de compra" class="mb-6">
                                <template #prefix>$</template>
                                <span class="text-xl font-bold text-emerald-600">
                                    <n-number-animation 
                                        ref="numberAnimationInstRef"
                                        :from="0" 
                                        :to="product.purchase_price" 
                                        :active="true" 
                                        :precision="2" 
                                        show-separator
                                    />
                                </span>
                                <template #suffix>MXN</template>
                            </n-statistic>

                            <n-divider />

                            <div class="prose prose-sm text-gray-500 max-w-none">
                                <h4 class="text-gray-800 font-semibold mb-2">Descripción</h4>
                                <p>{{ product.description || 'No hay descripción disponible para este producto.' }}</p>
                            </div>
                        </div>

                        <!-- NUEVA SECCIÓN: Documentos Adjuntos -->
                        <div v-if="product.attachments && product.attachments.length > 0" class="bg-white rounded-3xl p-6 shadow-sm border border-gray-100">
                            <h3 class="font-bold text-lg text-gray-800 flex items-center gap-2 mb-4">
                                <n-icon class="text-blue-500"><AttachOutline /></n-icon>
                                Archivos y Documentos
                            </h3>
                            
                            <n-grid-item v-for="file in product.attachments" :key="file.id">
                                <div class="group relative flex items-center gap-3 p-3 rounded-xl border border-gray-100 bg-gray-50/50 hover:bg-blue-50 hover:border-blue-100 transition-all">
                                    <!-- Icono según tipo -->
                                    <div class="w-10 h-10 rounded-lg bg-white flex items-center justify-center text-blue-500 shadow-sm group-hover:scale-110 transition-transform">
                                        <n-icon size="20">
                                            <component :is="getFileIcon(file.mime_type)" />
                                        </n-icon>
                                    </div>
                                    
                                    <!-- Info del archivo -->
                                    <div class="flex-1 min-w-0">
                                        <a :href="file.url" target="_blank" class="block">
                                            <p class="text-sm font-semibold text-gray-700 truncate group-hover:text-blue-700">
                                                {{ file.name }}
                                            </p>
                                            <p class="text-xs text-gray-400">
                                                {{ file.size }}
                                            </p>
                                        </a>
                                    </div>
                                    
                                    <!-- Botones de Acción (Descarga y Eliminar) -->
                                    <div class="flex items-center gap-1">
                                        <a :href="file.url" target="_blank" title="Descargar">
                                            <n-button size="tiny" circle secondary type="info">
                                                <template #icon><n-icon><CloudDownloadOutline /></n-icon></template>
                                            </n-button>
                                        </a>
                                        
                                        <!-- Botón de Eliminar con Confirmación -->
                                        <n-popconfirm
                                            @positive-click="deleteFile(file.id)"
                                            positive-text="Sí, eliminar"
                                            negative-text="Cancelar"
                                        >
                                            <template #trigger>
                                                <n-button size="tiny" circle secondary type="error">
                                                    <template #icon><n-icon><TrashOutline /></n-icon></template>
                                                </n-button>
                                            </template>
                                            ¿Estás seguro de eliminar este archivo permanentemente?
                                        </n-popconfirm>
                                    </div>
                                </div>
                            </n-grid-item>
                        </div>

                        <!-- Sección de Historial de Movimientos -->
                        <div class="bg-white rounded-3xl p-6 shadow-sm border border-gray-100">
                            
                            <!-- Header del Historial con Selector de Fecha -->
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6 border-b border-gray-100 pb-4">
                                <h3 class="font-bold text-xl text-gray-800 flex items-center gap-2">
                                    <n-icon class="text-orange-500"><SwapHorizontalOutline /></n-icon>
                                    Movimientos
                                </h3>
                                
                                <div class="w-full sm:w-48">
                                    <!-- DatePicker envuelto en ConfigProvider para asegurar español CORRECTO -->
                                    <n-config-provider :locale="locale" :date-locale="dateLocale">
                                        <n-date-picker 
                                            v-model:value="selectedMonth" 
                                            type="month" 
                                            clearable
                                            format="MMM yyyy"
                                            :actions="['now', 'confirm']"
                                            placeholder="Filtrar por mes"
                                            class="uppercase-input"
                                        />
                                    </n-config-provider>
                                </div>
                            </div>

                            <!-- Estado de Carga -->
                            <div v-if="loadingHistory" class="py-8 px-4">
                                <n-space vertical>
                                    <n-skeleton text :repeat="3" />
                                    <n-skeleton text style="width: 60%" />
                                </n-space>
                            </div>

                            <!-- Lista de Movimientos -->
                            <div v-else class="max-h-96 overflow-y-auto">
                                <div v-if="movements.length === 0" class="text-center py-12 text-gray-400 bg-gray-50 rounded-2xl border border-dashed border-gray-200">
                                    <n-icon size="48" class="mb-3 opacity-30"><CalendarOutline /></n-icon>
                                    <p class="font-medium">Sin movimientos</p>
                                    <p class="text-xs">No hay registros en el mes seleccionado.</p>
                                </div>

                                <n-timeline v-else class="px-2">
                                    <n-timeline-item
                                        v-for="movement in movements"
                                        :key="movement.id"
                                        :type="getTimelineType(movement.type)"
                                        :title="movement.reference_text"
                                        :time="movement.date"
                                    >
                                        <div class="flex flex-col gap-1 text-sm bg-gray-50/50 p-3 rounded-lg border border-gray-100/50 hover:bg-gray-50 transition-colors">
                                            <div class="flex items-center justify-between font-medium">
                                                <span :class="{
                                                    'text-green-600': movement.type === 'Entrada',
                                                    'text-red-600': movement.type === 'Salida',
                                                    'text-blue-600': movement.type === 'Ajuste'
                                                }">
                                                    {{ movement.type === 'Entrada' ? '+' : (movement.type === 'Salida' ? '-' : '') }}{{ movement.quantity }} uni.
                                                </span>
                                                <span class="text-gray-400 font-normal text-xs">Stock final: {{ movement.stock_after }}</span>
                                            </div>
                                            <div class="text-gray-500 text-xs flex justify-between items-center mt-1">
                                                <span>Por: {{ movement.user_name }}</span>
                                            </div>
                                            <div v-if="movement.notes" class="text-gray-500 text-xs italic mt-1 border-t border-gray-100 pt-1">
                                                "{{ movement.notes }}"
                                            </div>
                                        </div>
                                    </n-timeline-item>
                                </n-timeline>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- Modal de Ajuste de Inventario -->
                <n-modal v-model:show="showAdjustmentModal">
                    <n-card
                        style="width: 500px"
                        title="Ajuste Rápido de Inventario"
                        :bordered="false"
                        size="huge"
                        role="dialog"
                        aria-modal="true"
                    >
                        <template #header-extra>
                            <n-icon size="24" class="text-indigo-500"><ClipboardOutline /></n-icon>
                        </template>
                        
                        <p class="text-gray-500 text-sm mb-6">
                            Estás por realizar un ajuste manual al inventario físico de esta sucursal. 
                            Esta acción quedará registrada en el historial.
                        </p>

                        <n-form
                            ref="adjustmentFormRef"
                            :model="adjustmentForm"
                            :rules="adjustmentRules"
                        >
                            <n-form-item label="Nuevo Stock Real (Físico)" path="current_stock">
                                <n-input-number 
                                    v-model:value="adjustmentForm.current_stock" 
                                    :min="0"
                                    class="w-full text-center font-bold"
                                    size="large"
                                />
                            </n-form-item>

                            <n-form-item label="Motivo del Ajuste (Nota)" path="adjustment_note">
                                <n-input 
                                    v-model:value="adjustmentForm.adjustment_note" 
                                    type="textarea" 
                                    placeholder="Ej. Conteo cíclico, merma por daño, regalo a cliente..."
                                    :rows="3"
                                />
                            </n-form-item>
                        </n-form>

                        <template #footer>
                            <div class="flex justify-end gap-3">
                                <n-button @click="showAdjustmentModal = false" :disabled="adjustmentForm.processing">
                                    Cancelar
                                </n-button>
                                <n-button 
                                    type="primary" 
                                    @click="submitAdjustment" 
                                    :loading="adjustmentForm.processing"
                                >
                                    Guardar Ajuste
                                </n-button>
                            </div>
                        </template>
                    </n-card>
                </n-modal>

            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
/* Estilo para poner mayúsculas en el datepicker (ej. ENE 2024) */
:deep(.uppercase-input .n-input__input-el) {
    text-transform: uppercase;
    font-weight: bold;
    color: #4f46e5; /* Un tono índigo para destacar */
}
</style>