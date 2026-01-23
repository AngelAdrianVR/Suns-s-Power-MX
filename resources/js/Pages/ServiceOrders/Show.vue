<script setup>
import { ref, computed } from 'vue';
import { usePermissions } from '@/Composables/usePermissions'; // Importar hook de permisos
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import TaskGanttChart from '@/Components/MyComponents/TaskGanttChart.vue'; 
import { 
    NButton, NTag, NCard, NGrid, NGridItem, NDescriptions, NDescriptionsItem, 
    NTabs, NTabPane, NIcon, NThing, NAvatar, NProgress, NStatistic, NNumberAnimation,
    createDiscreteApi, NEmpty, NPopselect, NModal, NForm, NFormItem, NInput, NSelect, NInputNumber,
    NPopconfirm, NUpload, NImageGroup, NImage, NTooltip
} from 'naive-ui';
import { 
    ArrowBackOutline, CreateOutline, TrashOutline, LocationOutline, 
    CalendarOutline, PersonOutline, CashOutline, ReceiptOutline, 
    DocumentTextOutline, CheckmarkCircleOutline, TimeOutline, ImagesOutline,
    AddOutline, RemoveCircleOutline, CloudUploadOutline, DocumentOutline, CloudDownloadOutline,
    ChevronDownOutline, FlashOutline, PricetagOutline // Nuevos iconos importados
} from '@vicons/ionicons5';

const props = defineProps({
    order: Object,
    diagram_data: Array,
    stats: Object,
    assignable_users: Array,
    available_products: Array,
    can_view_financials: Boolean 
});

// Inicializar permisos
const { hasPermission } = usePermissions();

const { dialog, notification } = createDiscreteApi(['dialog', 'notification']);

// --- ESTADO Y UTILIDADES ---
const showProductModal = ref(false);

const formatCurrency = (amount) => new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(amount);
const formatDate = (dateString) => {
    if(!dateString) return 'Sin definir';
    const date = new Date(dateString);
    return date.toLocaleDateString('es-MX', { year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' });
};

// --- NUEVO: Propiedades Computadas para Dirección ---
const formattedAddress = computed(() => {
    const o = props.order;
    
    // Intentar construir dirección atomizada
    const parts = [
        o.installation_street ? (o.installation_street + (o.installation_exterior_number ? ` #${o.installation_exterior_number}` : '')) : null,
        o.installation_interior_number ? `Int. ${o.installation_interior_number}` : null,
        o.installation_neighborhood ? `Col. ${o.installation_neighborhood}` : null,
        o.installation_zip_code ? `CP ${o.installation_zip_code}` : null,
        o.installation_municipality,
        o.installation_state
    ];
    
    const atomized = parts.filter(Boolean).join(', ');
    
    // Si no hay datos atomizados (legacy), intentar mostrar el campo antiguo si existe
    if (!atomized && o.installation_address) return o.installation_address;
    
    return atomized || 'Sin dirección registrada';
});

const googleMapsUrl = computed(() => {
    const o = props.order;
    
    // Construir query de búsqueda
    const addressQuery = [
        o.installation_street,
        o.installation_exterior_number,
        o.installation_neighborhood,
        o.installation_municipality,
        o.installation_state,
        o.installation_country || 'México'
    ].filter(Boolean).join(', ');

    // Fallback a campo antiguo si es necesario
    const finalQuery = addressQuery || o.installation_address;

    if (!finalQuery) return null;
    
    // Usamos 'dir' (direcciones) para que trace la ruta desde la ubicación del usuario
    return `https://www.google.com/maps/dir/?api=1&destination=${encodeURIComponent(finalQuery)}`;
});
// ----------------------------------------------------

const generalProgress = computed(() => {
    if (!props.stats || props.stats.total_tasks === 0) return 0;
    return Math.round((props.stats.completed_tasks / props.stats.total_tasks) * 100);
});

// --- ESTATUS DE ORDEN ---
const orderStatusOptions = [
    { label: 'Cotización', value: 'Cotización' },
    { label: 'Aceptado', value: 'Aceptado' },
    { label: 'En Proceso', value: 'En Proceso' },
    { label: 'Completado', value: 'Completado' }, 
    { label: 'Facturado', value: 'Facturado' },
    { label: 'Cancelado', value: 'Cancelado' }
];

const getStatusType = (status) => {
    const map = { 'Cotización': 'default', 'Aceptado': 'info', 'En Proceso': 'warning', 'Completado': 'success', 'Facturado': 'success', 'Cancelado': 'error' };
    return map[status] || 'default';
};

const handleStatusUpdate = (newStatus) => {
    // Validación extra en cliente
    if (!hasPermission('service_orders.change_status')) return;

    router.patch(route('service-orders.update-status', props.order.id), { status: newStatus }, {
        preserveScroll: true,
        onSuccess: () => notification.success({ title: 'Estatus Actualizado', content: `Orden cambió a ${newStatus}`, duration: 3000 })
    });
};

// --- GESTIÓN DE PRODUCTOS ---
const productForm = useForm({
    product_id: null,
    quantity: 1
});

const productOptions = computed(() => {
    return props.available_products.map(p => ({
        // Condicional en la etiqueta si no tiene permisos financieros
        label: props.can_view_financials 
            ? `${p.name} (${p.sku}) - ${formatCurrency(p.sale_price)}` 
            : `${p.name} (${p.sku})`,
        value: p.id
    }));
});

const addProduct = () => {
    productForm.post(route('service-orders.add-items', props.order.id), {
        onSuccess: () => { 
            showProductModal.value = false; 
            productForm.reset();
            notification.success({ title: 'Producto Agregado', duration: 3000 }); 
        },
        preserveScroll: true
    });
};

const formattedTotal = computed(() =>
  new Intl.NumberFormat('es-MX', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  }).format(props.order.total_amount ?? 0)
)

const removeProduct = (itemId) => {
    router.delete(route('service-orders.remove-item', itemId), {
        preserveScroll: true,
        onSuccess: () => notification.success({title: 'Producto removido', duration: 3000})
    });
};

// --- GESTIÓN DE EVIDENCIAS (ARCHIVOS) ---
const fileInput = ref(null);
const triggerFileInput = () => {
    fileInput.value.click();
};

const handleFileChange = (event) => {
    const file = event.target.files[0];
    if (!file) return;

    const form = useForm({
        file: file
    });

    form.post(route('service-orders.upload-media', props.order.id), {
        onSuccess: () => {
            notification.success({ title: 'Archivo subido', content: 'Evidencia guardada.', duration: 3000 });
            router.reload({ only: ['order'] });
            event.target.value = null; // Resetear input
        },
        onError: () => {
            notification.error({ title: 'Error', content: 'No se pudo subir el archivo.' });
        }
    });
};

// --- ACCIONES GENERALES ---
const confirmDelete = () => {
    dialog.warning({
        title: 'Eliminar Orden',
        content: '¿Estás seguro? Esta acción eliminará todo el historial.',
        positiveText: 'Sí, Eliminar',
        negativeText: 'Cancelar',
        onPositiveClick: () => router.delete(route('service-orders.destroy', props.order.id))
    });
};

// Función auxiliar para saber si es imagen
const isImage = (file) => {
    if (file.mime_type) {
        return file.mime_type.startsWith('image/');
    }
    return /\.(jpg|jpeg|png|gif|webp)$/i.test(file.file_name);
};

</script>

<template>
    <AppLayout :title="`Orden de servicio #${order.id}`">
        <template #header>
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <div class="flex items-center gap-3">
                    <Link :href="route('service-orders.index')">
                        <n-button circle secondary size="small">
                            <template #icon><n-icon><ArrowBackOutline /></n-icon></template>
                        </n-button>
                    </Link>
                    <div>
                        <h2 class="font-bold text-xl text-gray-800 leading-tight flex items-center gap-2">
                            Orden de Servicio <span class="text-indigo-600 font-mono">#{{ order.id }}</span>
                        </h2>
                        <div class="flex items-center gap-2 mt-1">
                            
                            <!-- ESTATUS: Editable solo con permiso 'service_orders.change_status' -->
                            <n-popselect 
                                v-if="hasPermission('service_orders.change_status')"
                                :options="orderStatusOptions" 
                                :value="order.status" 
                                @update:value="handleStatusUpdate"
                                trigger="click"
                            >
                                <n-tag :type="getStatusType(order.status)" round size="small" class="cursor-pointer hover:opacity-80">
                                    {{ order.status }} <n-icon class="ml-1"><ChevronDownOutline /></n-icon>
                                </n-tag>
                            </n-popselect>

                            <!-- ESTATUS: Solo lectura -->
                            <n-tag v-else :type="getStatusType(order.status)" round size="small" :bordered="false">
                                {{ order.status }}
                            </n-tag>
                            
                            <span class="text-xs text-gray-400 border-l pl-2 ml-2 border-gray-300">
                                Creado {{ formatDate(order.created_at) }}
                            </span>
                        </div>
                    </div>
                </div>

                <div class="flex gap-2">
                    <!-- BOTÓN EDITAR -->
                    <n-button 
                        v-if="hasPermission('service_orders.edit')" 
                        quaternary 
                        type="warning" 
                        @click="() => router.visit(route('service-orders.edit', order.id))"
                    >
                        <template #icon><n-icon><CreateOutline /></n-icon></template>
                        Editar
                    </n-button>

                    <!-- BOTÓN ELIMINAR -->
                    <n-button 
                        v-if="hasPermission('service_orders.delete')" 
                        quaternary 
                        type="error" 
                        @click="confirmDelete"
                    >
                        <template #icon><n-icon><TrashOutline /></n-icon></template>
                        Eliminar
                    </n-button>
                </div>
            </div>
        </template>

        <div class="py-8 min-h-screen">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
                
                <!-- KPI Cards -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                    <n-card size="small" class="rounded-2xl shadow-sm md:col-span-3">
                        <n-grid cols="2 md:4" item-responsive responsive="screen">
                            <n-grid-item>
                                <div class="p-2">
                                    <div class="text-gray-400 text-xs uppercase font-bold mb-1">Cliente</div>
                                    <div class="font-bold text-gray-800 text-base truncate">{{ order.client?.name }}</div>
                                </div>
                            </n-grid-item>
                            <n-grid-item>
                                <div class="p-2 border-l border-gray-100">
                                    <div class="text-gray-400 text-xs uppercase font-bold mb-1">Progreso General</div>
                                    <div class="flex items-center gap-2">
                                        <n-progress type="circle" :percentage="generalProgress" :size="40" :stroke-width="8" status="success">
                                            <span class="text-[10px]">{{ generalProgress }}%</span>
                                        </n-progress>
                                        <div class="text-xs text-gray-500">
                                            {{ stats.completed_tasks }} / {{ stats.total_tasks }} Tareas
                                        </div>
                                    </div>
                                </div>
                            </n-grid-item>
                            <!-- VISIBILIDAD CONDICIONAL: Total Proyecto (Solo si puede ver finanzas) -->
                            <n-grid-item v-if="can_view_financials">
                                <div class="p-2 border-l border-gray-100">
                                    <div class="text-gray-400 text-xs uppercase font-bold mb-1">Total Proyecto</div>
                                    <n-statistic :value="formattedTotal">
                                        <template #prefix>$</template>
                                    </n-statistic>
                                </div>
                            </n-grid-item>
                            <n-grid-item>
                                <div class="p-2 border-l border-gray-100">
                                    <div class="text-gray-400 text-xs uppercase font-bold mb-1">Técnico Líder</div>
                                    <div v-if="order.technician" class="flex items-center gap-2 mt-1">
                                        <n-avatar round size="small" :src="order.technician.profile_photo_path" :fallback-src="'https://ui-avatars.com/api/?name='+order.technician.name" />
                                        <span class="text-sm font-medium">{{ order.technician.name }}</span>
                                    </div>
                                    <div v-else class="text-sm text-amber-500 italic">Sin asignar</div>
                                </div>
                            </n-grid-item>
                        </n-grid>
                    </n-card>
                    
                    <!-- Tarjeta de Ubicación Actualizada -->
                    <n-card size="small" class="rounded-2xl shadow-sm bg-blue-50/30 border-blue-100">
                        <div class="flex flex-col h-full justify-between">
                            <div>
                                <div class="flex items-center gap-2 text-blue-800 font-semibold mb-2">
                                    <n-icon><LocationOutline /></n-icon> Ubicación
                                </div>
                                <!-- Usamos la propiedad computada para la dirección formateada -->
                                <p class="text-sm text-gray-600 line-clamp-3 leading-snug">
                                    {{ formattedAddress }}
                                </p>
                            </div>
                            <!-- Usamos la propiedad computada para el enlace -->
                            <a 
                                v-if="googleMapsUrl"
                                :href="googleMapsUrl" 
                                target="_blank"
                                class="mt-3 text-xs font-bold text-blue-600 hover:underline flex items-center gap-1"
                            >
                                Cómo llegar <n-icon size="10" class="-rotate-45"><ArrowBackOutline/></n-icon>
                            </a>
                            <div v-else class="mt-3 text-xs text-gray-400 italic">
                                Sin ubicación precisa
                            </div>
                        </div>
                    </n-card>
                </div>

                <div class="bg-white rounded-3xl shadow-lg border border-gray-100 overflow-hidden min-h-[500px]">
                    <n-tabs type="line" size="large" animated class="px-6 pt-4">
                        
                        <!-- TAB GANTT: Protegido por 'tasks.view_board' -->
                        <n-tab-pane v-if="hasPermission('tasks.view_board')" name="gantt" tab="Cronograma y Tareas">
                            <div class="py-4 space-y-6">
                                <TaskGanttChart 
                                    :tasks="diagram_data" 
                                    :order-id="order.id"
                                    :assignable-users="assignable_users"
                                />
                            </div>
                        </n-tab-pane>

                        <n-tab-pane name="items" tab="Materiales y Productos">
                             <div class="p-4">
                                <div class="flex justify-between items-center mb-4">
                                    <h3 class="font-bold text-gray-700">Productos Asignados</h3>
                                    <!-- Botón agregar producto: Requiere 'service_orders.edit' -->
                                    <n-button 
                                        v-if="hasPermission('service_orders.edit')"
                                        type="primary" 
                                        size="small" 
                                        @click="showProductModal = true"
                                    >
                                        <template #icon><n-icon><AddOutline /></n-icon></template>
                                        Agregar Producto
                                    </n-button>
                                </div>

                                <div class="border rounded-lg overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Producto</th>
                                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Cant.</th>
                                                <!-- VISIBILIDAD CONDICIONAL: Columnas de Precio -->
                                                <th v-if="can_view_financials" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">P. Unit (Ref)</th>
                                                <th v-if="can_view_financials" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Costo Total</th>
                                                <th class="px-6 py-3"></th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            <tr v-for="item in order.items" :key="item.id">
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                    {{ item.product.name }} <span class="text-gray-400 text-xs">({{ item.product.sku }})</span>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">{{ item.quantity }}</td>
                                                <!-- VISIBILIDAD CONDICIONAL: Celdas de Precio -->
                                                <td v-if="can_view_financials" class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-right">{{ formatCurrency(item.product.purchase_price) }}</td>
                                                <td v-if="can_view_financials" class="px-6 py-4 whitespace-nowrap text-sm font-bold text-gray-800 text-right">{{ formatCurrency(item.product.purchase_price * item.quantity) }}</td>
                                                <td class="px-6 py-4 whitespace-nowrap text-right">
                                                    <!-- Botón quitar: Requiere 'service_orders.edit' -->
                                                    <n-popconfirm v-if="hasPermission('service_orders.edit')" @positive-click="removeProduct(item.id)">
                                                        <template #trigger>
                                                            <n-button circle size="tiny" type="error" tertiary>
                                                                <template #icon><n-icon><RemoveCircleOutline /></n-icon></template>
                                                            </n-button>
                                                        </template>
                                                        ¿Quitar producto y devolver stock?
                                                    </n-popconfirm>
                                                </td>
                                            </tr>
                                            <tr v-if="!order.items?.length">
                                                <td colspan="5" class="px-6 py-8 text-center text-gray-400 text-sm">No hay materiales asignados.</td>
                                            </tr>
                                        </tbody>
                                        <!-- VISIBILIDAD CONDICIONAL: Footer -->
                                        <tfoot v-if="can_view_financials" class="bg-gray-50 font-bold">
                                            <tr>
                                                <td colspan="3" class="px-6 py-3 text-right">Total Materiales (Costo Interno):</td>
                                                <td class="px-6 py-3 text-right text-indigo-600">
                                                    {{ formatCurrency(order.items?.reduce((sum, i) => sum + (i.product.purchase_price * i.quantity), 0) || 0) }}
                                                </td>
                                                <td></td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                             </div>
                        </n-tab-pane>

                        <n-tab-pane name="details" tab="Detalles Operativos">
                            <div class="p-4">
                                <n-descriptions label-placement="top" bordered column="3">
                                    <n-descriptions-item label="Fecha de Inicio Real">
                                        <div class="flex items-center gap-2">
                                            <n-icon class="text-gray-400"><CalendarOutline /></n-icon>
                                            {{ formatDate(order.start_date) }}
                                        </div>
                                    </n-descriptions-item>
                                    <n-descriptions-item label="Fecha de Finalización">
                                        <div class="flex items-center gap-2" :class="order.completion_date ? 'text-green-600 font-medium' : 'text-gray-400'">
                                            <n-icon><CheckmarkCircleOutline /></n-icon>
                                            {{ order.completion_date ? formatDate(order.completion_date) : 'En progreso' }}
                                        </div>
                                    </n-descriptions-item>
                                    <n-descriptions-item label="Representante de Ventas">
                                        <div v-if="order.sales_rep" class="flex items-center gap-3">
                                            <n-avatar size="small" round :src="order.sales_rep.profile_photo_url" :fallback-src="'https://ui-avatars.com/api/?name='+order.sales_rep.name" />
                                            <span>{{ order.sales_rep.name }}</span>
                                        </div>
                                        <span v-else class="text-gray-400 italic">No asignado</span>
                                    </n-descriptions-item>
                                    
                                    <!-- --- NUEVOS CAMPOS --- -->
                                    <n-descriptions-item label="Número de Servicio">
                                        <div v-if="order.service_number" class="flex items-center gap-2 font-mono text-indigo-700 bg-indigo-50 px-2 py-1 rounded w-fit">
                                            <n-icon><FlashOutline /></n-icon> {{ order.service_number }}
                                        </div>
                                        <span v-else class="text-gray-400 italic">No especificado</span>
                                    </n-descriptions-item>

                                    <n-descriptions-item label="Tipo de Tarifa">
                                         <div v-if="order.rate_type" class="flex items-center gap-2">
                                            <n-icon class="text-gray-500"><PricetagOutline /></n-icon> {{ order.rate_type }}
                                        </div>
                                        <span v-else class="text-gray-400 italic">N/A</span>
                                    </n-descriptions-item>
                                    <!-- --------------------- -->

                                    <n-descriptions-item label="Notas">
                                        {{ order.notes || 'Sin notas registradas.' }}
                                    </n-descriptions-item>
                                </n-descriptions>
                            </div>
                        </n-tab-pane>

                        <n-tab-pane name="files" tab="Evidencias">
                            <div class="p-4">
                                <!-- Subida de archivos: Requiere 'service_orders.edit' -->
                                <div v-if="hasPermission('service_orders.edit')" class="bg-gray-50 border border-dashed border-gray-300 rounded-lg p-6 mb-6 flex flex-col items-center justify-center">
                                    <input 
                                        type="file" 
                                        ref="fileInput" 
                                        class="hidden" 
                                        @change="handleFileChange" 
                                        accept="image/*,application/pdf,.doc,.docx,.xls,.xlsx"
                                    />
                                    <n-button dashed type="primary" size="large" @click="triggerFileInput" class="h-20 w-full md:w-1/2">
                                        <div class="flex flex-col items-center gap-2">
                                            <n-icon size="24"><CloudUploadOutline /></n-icon>
                                            <span>Seleccionar archivo para subir</span>
                                        </div>
                                    </n-button>
                                    <p class="text-xs text-gray-400 mt-2">Formatos aceptados: Imágenes, PDF y Documentos (Max 10MB)</p>
                                </div>

                                <div v-if="order.media?.length">
                                    <h4 class="font-bold text-gray-700 mb-3">Archivos Adjuntos</h4>
                                    <!-- Si es imagen, usamos n-image-group. Si no, lo mostramos aparte para no romper el layout -->
                                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                                        <div v-for="file in order.media" :key="file.id" class="relative group">
                                            
                                            <!-- Caso 1: Imagen -->
                                            <n-image
                                                v-if="isImage(file)"
                                                :src="file.original_url"
                                                class="rounded-lg shadow-sm border border-gray-200 overflow-hidden w-full h-40 object-cover"
                                                object-fit="cover"
                                            />

                                            <!-- Caso 2: Documento (PDF, etc) -->
                                            <div v-else class="w-full h-40 rounded-lg shadow-sm border border-gray-200 bg-gray-100 flex flex-col items-center justify-center gap-2 p-2">
                                                <n-icon size="40" class="text-gray-400"><DocumentOutline /></n-icon>
                                                <a :href="file.original_url" target="_blank" class="text-xs text-indigo-600 hover:underline flex items-center gap-1 font-bold text-center break-all">
                                                    Abrir Archivo <n-icon><CloudDownloadOutline/></n-icon>
                                                </a>
                                            </div>

                                            <div class="mt-1 text-xs text-gray-500 truncate">{{ file.file_name }}</div>
                                            
                                            <!-- Eliminar archivo: Requiere 'service_orders.edit' (o delete si quisieras separar) -->
                                            <n-popconfirm 
                                                v-if="hasPermission('service_orders.edit')"
                                                @positive-click="router.delete(route('media.delete-file', file.id), { preserveScroll: true, onSuccess: () => router.reload({only: ['order']}) })"
                                            >
                                                <template #trigger>
                                                    <button class="absolute top-2 right-2 bg-white/90 p-1 rounded-full shadow-sm opacity-0 group-hover:opacity-100 transition-opacity text-red-500 hover:text-red-700 z-10">
                                                        <n-icon><TrashOutline /></n-icon>
                                                    </button>
                                                </template>
                                                ¿Eliminar evidencia?
                                            </n-popconfirm>
                                        </div>
                                    </div>
                                </div>

                                <div class="p-8 text-center" v-else>
                                    <n-empty description="No se han cargado evidencias fotográficas o documentos aún." />
                                </div>
                            </div>
                        </n-tab-pane>

                    </n-tabs>
                </div>
            </div>

            <!-- MODAL: Agregar Producto -->
            <n-modal v-model:show="showProductModal" preset="card" title="Asignar Material/Producto" style="width: 500px;">
                <n-form>
                    <n-form-item label="Producto">
                        <n-select 
                            v-model:value="productForm.product_id" 
                            :options="productOptions" 
                            filterable 
                            placeholder="Buscar producto..."
                        />
                    </n-form-item>
                    <n-form-item label="Cantidad">
                        <n-input-number v-model:value="productForm.quantity" :min="1" />
                    </n-form-item>
                    <div class="flex justify-end mt-4">
                        <n-button type="primary" @click="addProduct" :loading="productForm.processing" :disabled="!productForm.product_id">
                            Asignar y Descontar
                        </n-button>
                    </div>
                </n-form>
            </n-modal>

        </div>
    </AppLayout>
</template>