<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import TaskGanttChart from '@/Components/MyComponents/TaskGanttChart.vue'; 
import { 
    NButton, NTag, NCard, NGrid, NGridItem, NDescriptions, NDescriptionsItem, 
    NTabs, NTabPane, NIcon, NThing, NAvatar, NProgress, NStatistic, NNumberAnimation,
    createDiscreteApi, NEmpty
} from 'naive-ui';
import { 
    ArrowBackOutline, CreateOutline, TrashOutline, LocationOutline, 
    CalendarOutline, PersonOutline, CashOutline, ReceiptOutline, 
    DocumentTextOutline, CheckmarkCircleOutline, TimeOutline, ImagesOutline
} from '@vicons/ionicons5';

const props = defineProps({
    order: Object,
    diagram_data: Array,
    stats: Object,
    assignable_users: Array // <--- NUEVA PROP RECIBIDA DEL CONTROLLER
});

const { dialog, notification } = createDiscreteApi(['dialog', 'notification']);

// Utilidades de formato
const formatCurrency = (amount) => new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(amount);
const formatDate = (dateString) => {
    if(!dateString) return 'Sin definir';
    const date = new Date(dateString);
    return date.toLocaleDateString('es-MX', { year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' });
};

const getStatusType = (status) => {
    const map = { 'Cotización': 'default', 'Aceptado': 'info', 'En Proceso': 'warning', 'Instalado': 'success', 'Facturado': 'success', 'Cancelado': 'error' };
    return map[status] || 'default';
};

// Acciones
const confirmDelete = () => {
    dialog.warning({
        title: 'Eliminar Orden',
        content: '¿Estás seguro? Esta acción eliminará todo el historial de tareas y evidencias asociadas.',
        positiveText: 'Sí, Eliminar',
        negativeText: 'Cancelar',
        onPositiveClick: () => {
            router.delete(route('service-orders.destroy', props.order.id));
        }
    });
};

const goToEdit = () => router.visit(route('service-orders.edit', props.order.id));

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
                        <div class="flex items-center gap-2 mt-1">
                            <n-tag :type="getStatusType(order.status)" round size="small" :bordered="false">
                                {{ order.status }}
                            </n-tag>
                            <span class="text-xs text-gray-400 border-l pl-2 ml-2 border-gray-300">
                                Creado {{ formatDate(order.created_at) }}
                            </span>
                        </div>
                    </div>
                </div>

                <div class="flex gap-2">
                    <n-button quaternary type="warning" @click="goToEdit">
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
                
                <!-- Sección Superior: Resumen y Progreso -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                    
                    <!-- KPI Cards -->
                    <n-card size="small" class="rounded-2xl shadow-sm md:col-span-3">
                        <n-grid cols="2 md:4" item-responsive responsive="screen">
                            <!-- Cliente -->
                            <n-grid-item>
                                <div class="p-2">
                                    <div class="text-gray-400 text-xs uppercase font-bold mb-1">Cliente</div>
                                    <div class="font-bold text-gray-800 text-base truncate">{{ order.client?.name }}</div>
                                    <div @click="$inertia.visit(route('clients.show', order.client.id))" class="text-xs text-indigo-500 cursor-pointer hover:underline">Ver Expediente</div>
                                </div>
                            </n-grid-item>
                            
                            <!-- Avance -->
                            <n-grid-item>
                                <div class="p-2 border-l border-gray-100">
                                    <div class="text-gray-400 text-xs uppercase font-bold mb-1">Progreso General</div>
                                    <div class="flex items-center gap-2">
                                        <n-progress type="circle" :percentage="order.progress" :size="40" :stroke-width="8" status="success">
                                            <span class="text-[10px]">{{ order.progress }}%</span>
                                        </n-progress>
                                        <div class="text-xs text-gray-500">
                                            {{ stats.completed_tasks }} / {{ stats.total_tasks }} Tareas
                                        </div>
                                    </div>
                                </div>
                            </n-grid-item>

                            <!-- Financiero -->
                            <n-grid-item>
                                <div class="p-2 border-l border-gray-100">
                                    <div class="text-gray-400 text-xs uppercase font-bold mb-1">Total Proyecto</div>
                                    <n-statistic :value="order.total_amount">
                                        <template #prefix>$</template>
                                    </n-statistic>
                                    <div v-if="stats.pending_balance > 0" class="text-xs text-red-500 font-bold mt-1">
                                        Resta: {{ formatCurrency(stats.pending_balance) }}
                                    </div>
                                    <div v-else class="text-xs text-emerald-500 font-bold mt-1">Pagado</div>
                                </div>
                            </n-grid-item>

                            <!-- Técnico -->
                            <n-grid-item>
                                <div class="p-2 border-l border-gray-100">
                                    <div class="text-gray-400 text-xs uppercase font-bold mb-1">Técnico Líder</div>
                                    <div v-if="order.technician" class="flex items-center gap-2 mt-1">
                                        <n-avatar round size="small" :src="order.technician.profile_photo_path" />
                                        <span class="text-sm font-medium">{{ order.technician.name }}</span>
                                    </div>
                                    <div v-else class="text-sm text-amber-500 italic">Sin asignar</div>
                                </div>
                            </n-grid-item>
                        </n-grid>
                    </n-card>

                    <!-- Mapa / Dirección -->
                    <n-card size="small" class="rounded-2xl shadow-sm bg-blue-50/30 border-blue-100">
                        <div class="flex flex-col h-full justify-between">
                            <div>
                                <div class="flex items-center gap-2 text-blue-800 font-semibold mb-2">
                                    <n-icon><LocationOutline /></n-icon> Ubicación
                                </div>
                                <p class="text-sm text-gray-600 line-clamp-3">{{ order.installation_address }}</p>
                            </div>
                            <a 
                                :href="`https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(order.installation_address)}`" 
                                target="_blank"
                                class="mt-3 text-xs font-bold text-blue-600 hover:underline flex items-center gap-1"
                            >
                                Abrir en Google Maps <n-icon size="10" class="-rotate-45"><ArrowBackOutline/></n-icon>
                            </a>
                        </div>
                    </n-card>
                </div>

                <!-- Contenido Principal con Pestañas -->
                <div class="bg-white rounded-3xl shadow-lg border border-gray-100 overflow-hidden min-h-[500px]">
                    <n-tabs type="line" size="large" animated class="px-6 pt-4">
                        
                        <!-- TAB 1: DIAGRAMA PMS (Lo más importante) -->
                        <n-tab-pane name="gantt" tab="Cronograma y Tareas">
                            <div class="py-4 space-y-6">
                                <div class="flex justify-between items-center px-2">
                                    <p class="text-gray-500 text-sm">Visualización gráfica de la ejecución del proyecto.</p>
                                </div>
                                
                                <!-- CORRECCIÓN: PASAMOS LAS PROPS FALTANTES -->
                                <TaskGanttChart 
                                    :tasks="diagram_data" 
                                    :order-id="order.id"
                                    :assignable-users="assignable_users"
                                />

                                <!-- Lista Simple de Tareas (Fallback o detalle) -->
                                <div class="mt-8">
                                    <h3 class="font-bold text-gray-800 mb-4 px-2">Listado Detallado</h3>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div v-for="task in order.tasks" :key="task.id" class="border rounded-xl p-3 hover:shadow-md transition-shadow">
                                            <div class="flex justify-between items-start">
                                                <div class="font-medium text-gray-800">{{ task.title }}</div>
                                                <n-tag size="small" :type="task.status === 'Completado' ? 'success' : 'default'">{{ task.status }}</n-tag>
                                            </div>
                                            <div class="text-xs text-gray-500 mt-1 line-clamp-2">{{ task.description || 'Sin descripción' }}</div>
                                            <div class="flex justify-between items-center mt-3 pt-2 border-t border-gray-50">
                                                <span class="text-xs text-gray-400">Vence: {{ formatDate(task.due_date) }}</span>
                                                <!-- Avatares de asignados -->
                                                <div class="flex -space-x-2">
                                                    <n-avatar v-for="user in task.users" :key="user.id" round size="tiny" :src="user.profile_photo_path" />
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </n-tab-pane>

                        <!-- TAB 2: DETALLES GENERALES -->
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
                                        {{ formatDate(order.completion_date) }}
                                    </n-descriptions-item>
                                    <n-descriptions-item label="Representante de Ventas">
                                        {{ order.sales_rep?.name || 'N/A' }}
                                    </n-descriptions-item>
                                    <n-descriptions-item label="Notas">
                                        {{ order.notes || 'Sin notas registradas.' }}
                                    </n-descriptions-item>
                                </n-descriptions>
                            </div>
                        </n-tab-pane>

                        <!-- TAB 3: EVIDENCIAS (Placeholder) -->
                        <n-tab-pane name="files" tab="Evidencias y Documentos">
                            <template #tab>
                                <span class="flex items-center gap-2">
                                    Evidencias 
                                    <n-tag size="small" round type="info">{{ order.documents?.length || 0 }}</n-tag>
                                </span>
                            </template>
                            
                            <div class="p-8 text-center" v-if="!order.documents?.length">
                                <n-empty description="No se han cargado evidencias fotográficas o documentos." >
                                    <template #extra>
                                        <n-button dashed>
                                            <template #icon><n-icon><ImagesOutline /></n-icon></template>
                                            Subir Evidencia
                                        </n-button>
                                    </template>
                                </n-empty>
                            </div>
                            <!-- Aquí iría tu componente de galería de medialibrary -->
                        </n-tab-pane>

                    </n-tabs>
                </div>

            </div>
        </div>
    </AppLayout>
</template>