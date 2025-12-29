<script setup>
import { ref, computed } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import TaskGanttChart from '@/Components/MyComponents/TaskGanttChart.vue'; 
import { 
    NButton, NTag, NCard, NGrid, NGridItem, NDescriptions, NDescriptionsItem, 
    NTabs, NTabPane, NIcon, NThing, NAvatar, NProgress, NStatistic, NNumberAnimation,
    createDiscreteApi, NEmpty, NPopselect, NModal, NForm, NFormItem, NInput, NSelect, NInputNumber,
    NPopconfirm, NUpload, NImageGroup, NImage
} from 'naive-ui';
import { 
    ArrowBackOutline, CreateOutline, TrashOutline, LocationOutline, 
    CalendarOutline, PersonOutline, CashOutline, ReceiptOutline, 
    DocumentTextOutline, CheckmarkCircleOutline, TimeOutline, ImagesOutline,
    AddOutline, RemoveCircleOutline, CloudUploadOutline
} from '@vicons/ionicons5';

const props = defineProps({
    order: Object,
    diagram_data: Array,
    stats: Object,
    assignable_users: Array,
    available_products: Array
});

const { dialog, notification } = createDiscreteApi(['dialog', 'notification']);

// --- ESTADO Y UTILIDADES ---
const showProductModal = ref(false);

const formatCurrency = (amount) => new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(amount);
const formatDate = (dateString) => {
    if(!dateString) return 'Sin definir';
    const date = new Date(dateString);
    return date.toLocaleDateString('es-MX', { year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' });
};

// CÁLCULO DE PROGRESO CORREGIDO
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
    router.patch(route('service-orders.update-status', props.order.id), { status: newStatus }, {
        preserveScroll: true,
        onSuccess: () => notification.success({ title: 'Estatus Actualizado', content: `Orden cambió a ${newStatus}` })
    });
};

// --- GESTIÓN DE PRODUCTOS ---
const productForm = useForm({
    product_id: null,
    quantity: 1
});

const productOptions = computed(() => {
    return props.available_products.map(p => ({
        label: `${p.name} (${p.sku}) - ${formatCurrency(p.sale_price)}`,
        value: p.id
    }));
});

const addProduct = () => {
    productForm.post(route('service-orders.add-items', props.order.id), {
        onSuccess: () => { 
            showProductModal.value = false; 
            productForm.reset();
            notification.success({ title: 'Producto Agregado' }); 
        },
        preserveScroll: true
    });
};

const removeProduct = (itemId) => {
    router.delete(route('service-orders.remove-item', itemId), {
        preserveScroll: true,
        onSuccess: () => notification.success({title: 'Producto removido'})
    });
};

// --- GESTIÓN DE EVIDENCIAS (ARCHIVOS) ---
const handleUploadFinish = ({ file, event }) => {
    notification.success({ title: 'Archivo subido', content: 'La evidencia se ha guardado correctamente.' });
    router.reload({ only: ['order'] }); // Recargar orden para ver nueva imagen
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

</script>

<template>
    <AppLayout :title="`Orden #${order.id}`">
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
                        <!-- SELECTOR DE ESTATUS -->
                        <div class="flex items-center gap-2 mt-1">
                            <n-popselect 
                                :options="orderStatusOptions" 
                                :value="order.status" 
                                @update:value="handleStatusUpdate"
                                trigger="click"
                            >
                                <n-tag :type="getStatusType(order.status)" round size="small" class="cursor-pointer hover:opacity-80">
                                    {{ order.status }} <n-icon class="ml-1"><CreateOutline /></n-icon>
                                </n-tag>
                            </n-popselect>
                            
                            <span class="text-xs text-gray-400 border-l pl-2 ml-2 border-gray-300">
                                Creado {{ formatDate(order.created_at) }}
                            </span>
                        </div>
                    </div>
                </div>

                <div class="flex gap-2">
                    <n-button quaternary type="warning" @click="() => router.visit(route('service-orders.edit', order.id))">
                        <template #icon><n-icon><CreateOutline /></n-icon></template>
                        Editar
                    </n-button>
                    <n-button quaternary type="error" @click="confirmDelete">
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
                                        <!-- PROGRESO ARREGLADO -->
                                        <n-progress type="circle" :percentage="generalProgress" :size="40" :stroke-width="8" status="success">
                                            <span class="text-[10px]">{{ generalProgress }}%</span>
                                        </n-progress>
                                        <div class="text-xs text-gray-500">
                                            {{ stats.completed_tasks }} / {{ stats.total_tasks }} Tareas
                                        </div>
                                    </div>
                                </div>
                            </n-grid-item>
                            <n-grid-item>
                                <div class="p-2 border-l border-gray-100">
                                    <div class="text-gray-400 text-xs uppercase font-bold mb-1">Total Proyecto</div>
                                    <n-statistic :value="order.total_amount">
                                        <template #prefix>$</template>
                                    </n-statistic>
                                </div>
                            </n-grid-item>
                            <n-grid-item>
                                <div class="p-2 border-l border-gray-100">
                                    <div class="text-gray-400 text-xs uppercase font-bold mb-1">Técnico Líder</div>
                                    <!-- AVATAR ARREGLADO -->
                                    <div v-if="order.technician" class="flex items-center gap-2 mt-1">
                                        <n-avatar round size="small" :src="order.technician.profile_photo_path" :fallback-src="'https://ui-avatars.com/api/?name='+order.technician.name" />
                                        <span class="text-sm font-medium">{{ order.technician.name }}</span>
                                    </div>
                                    <div v-else class="text-sm text-amber-500 italic">Sin asignar</div>
                                </div>
                            </n-grid-item>
                        </n-grid>
                    </n-card>
                    <!-- Mapa FIXED -->
                    <n-card size="small" class="rounded-2xl shadow-sm bg-blue-50/30 border-blue-100">
                        <div class="flex flex-col h-full justify-between">
                            <div>
                                <div class="flex items-center gap-2 text-blue-800 font-semibold mb-2">
                                    <n-icon><LocationOutline /></n-icon> Ubicación
                                </div>
                                <p class="text-sm text-gray-600 line-clamp-3">{{ order.installation_address }}</p>
                            </div>
                            <a 
                                :href="`https://www.google.com/maps/dir/?api=1&destination=${encodeURIComponent(order.installation_address)}`" 
                                target="_blank"
                                class="mt-3 text-xs font-bold text-blue-600 hover:underline flex items-center gap-1"
                            >
                                Cómo llegar <n-icon size="10" class="-rotate-45"><ArrowBackOutline/></n-icon>
                            </a>
                        </div>
                    </n-card>
                </div>

                <!-- Contenido Principal -->
                <div class="bg-white rounded-3xl shadow-lg border border-gray-100 overflow-hidden min-h-[500px]">
                    <n-tabs type="line" size="large" animated class="px-6 pt-4">
                        
                        <!-- TAB 1: DIAGRAMA PMS (Funcionalidad Completa) -->
                        <n-tab-pane name="gantt" tab="Cronograma y Tareas">
                            <div class="py-4 space-y-6">
                                <!-- Componente GANTT actualizado con todas las funciones -->
                                <TaskGanttChart 
                                    :tasks="diagram_data" 
                                    :order-id="order.id"
                                    :assignable-users="assignable_users"
                                />
                            </div>
                        </n-tab-pane>

                        <!-- TAB 2: MATERIALES -->
                        <n-tab-pane name="items" tab="Materiales y Productos">
                             <div class="p-4">
                                <div class="flex justify-between items-center mb-4">
                                    <h3 class="font-bold text-gray-700">Productos Asignados</h3>
                                    <n-button type="primary" size="small" @click="showProductModal = true">
                                        <template #icon><n-icon><AddOutline /></n-icon></template>
                                        Agregar Producto
                                    </n-button>
                                </div>

                                <div class="border rounded-lg overflow-hidden">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Producto</th>
                                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Cant.</th>
                                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">P. Unit (Ref)</th>
                                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Costo Total</th>
                                                <th class="px-6 py-3"></th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            <tr v-for="item in order.items" :key="item.id">
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                    {{ item.product.name }} <span class="text-gray-400 text-xs">({{ item.product.sku }})</span>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">{{ item.quantity }}</td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-right">{{ formatCurrency(item.price) }}</td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-gray-800 text-right">{{ formatCurrency(item.price * item.quantity) }}</td>
                                                <td class="px-6 py-4 whitespace-nowrap text-right">
                                                    <n-popconfirm @positive-click="removeProduct(item.id)">
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
                                        <tfoot class="bg-gray-50 font-bold">
                                            <tr>
                                                <td colspan="3" class="px-6 py-3 text-right">Total Materiales (Costo Interno):</td>
                                                <td class="px-6 py-3 text-right text-indigo-600">
                                                    {{ formatCurrency(order.items?.reduce((sum, i) => sum + (i.price * i.quantity), 0) || 0) }}
                                                </td>
                                                <td></td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                             </div>
                        </n-tab-pane>

                        <!-- TAB 3: DETALLES -->
                        <n-tab-pane name="details" tab="Detalles Operativos">
                            <div class="p-4">
                                <n-descriptions label-placement="top" bordered column="3">
                                    <n-descriptions-item label="Fecha de Inicio Real">
                                        <div class="flex items-center gap-2">
                                            <n-icon class="text-gray-400"><CalendarOutline /></n-icon>
                                            {{ formatDate(order.start_date) }}
                                        </div>
                                    </n-descriptions-item>
                                    <!-- FECHA FINALIZACION -->
                                    <n-descriptions-item label="Fecha de Finalización">
                                        <div class="flex items-center gap-2" :class="order.completion_date ? 'text-green-600 font-medium' : 'text-gray-400'">
                                            <n-icon><CheckmarkCircleOutline /></n-icon>
                                            {{ order.completion_date ? formatDate(order.completion_date) : 'En progreso' }}
                                        </div>
                                    </n-descriptions-item>
                                    <!-- REPRESENTANTE DE VENTAS CON AVATAR -->
                                    <n-descriptions-item label="Representante de Ventas">
                                        <div v-if="order.sales_rep" class="flex items-center gap-3">
                                            <n-avatar size="small" round :src="order.sales_rep.profile_photo_url" :fallback-src="'https://ui-avatars.com/api/?name='+order.sales_rep.name" />
                                            <span>{{ order.sales_rep.name }}</span>
                                        </div>
                                        <span v-else class="text-gray-400 italic">No asignado</span>
                                    </n-descriptions-item>
                                    <n-descriptions-item label="Notas">
                                        {{ order.notes || 'Sin notas registradas.' }}
                                    </n-descriptions-item>
                                </n-descriptions>
                            </div>
                        </n-tab-pane>

                        <!-- TAB 4: EVIDENCIAS -->
                        <n-tab-pane name="files" tab="Evidencias">
                            <div class="p-4">
                                <!-- Uploader de Evidencias (AGREGADO) -->
                                <div class="bg-gray-50 border border-dashed border-gray-300 rounded-lg p-6 mb-6">
                                    <n-upload
                                        :action="route('service-orders.upload-media', order.id)"
                                        :headers="{ 'X-CSRF-TOKEN': $page.props.csrf_token }"
                                        name="file"
                                        @finish="handleUploadFinish"
                                        list-type="image-card"
                                        :show-file-list="false"
                                    >
                                        <n-button dashed block class="h-20">
                                            <div class="flex flex-col items-center gap-2 text-gray-500">
                                                <n-icon size="24"><CloudUploadOutline /></n-icon>
                                                <span>Clic o arrastra para subir evidencias</span>
                                            </div>
                                        </n-button>
                                    </n-upload>
                                </div>

                                <!-- Galería de Evidencias -->
                                <div v-if="order.media?.length">
                                    <h4 class="font-bold text-gray-700 mb-3">Archivos Adjuntos</h4>
                                    <n-image-group>
                                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                                            <div v-for="file in order.media" :key="file.id" class="relative group">
                                                <n-image
                                                    :src="file.original_url"
                                                    class="rounded-lg shadow-sm border border-gray-200 overflow-hidden w-full h-40 object-cover"
                                                    object-fit="cover"
                                                />
                                                <div class="mt-1 text-xs text-gray-500 truncate">{{ file.file_name }}</div>
                                                
                                                <!-- Botón eliminar archivo -->
                                                <n-popconfirm @positive-click="router.delete(route('media.delete-file', file.id), { preserveScroll: true, onSuccess: () => router.reload({only: ['order']}) })">
                                                    <template #trigger>
                                                        <button class="absolute top-2 right-2 bg-white/90 p-1 rounded-full shadow-sm opacity-0 group-hover:opacity-100 transition-opacity text-red-500 hover:text-red-700">
                                                            <n-icon><TrashOutline /></n-icon>
                                                        </button>
                                                    </template>
                                                    ¿Eliminar evidencia?
                                                </n-popconfirm>
                                            </div>
                                        </div>
                                    </n-image-group>
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