<script setup>
import { ref, computed, onMounted } from 'vue';
import { usePermissions } from '@/Composables/usePermissions'; 
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import TaskGanttChart from '@/Components/MyComponents/TaskGanttChart.vue'; 
// Importar componentes factorizados (Ajusta la ruta según donde los ubiques)
import OrderItemsTab from './Components/OrderItemsTab.vue';
import OrderDetailsTab from './Components/OrderDetailsTab.vue';
import OrderFilesTab from './Components/OrderFilesTab.vue';

import { 
    NButton, NTag, NCard, NGrid, NGridItem, NTabs, NTabPane, 
    NIcon, NAvatar, NProgress, NStatistic, createDiscreteApi, NPopselect 
} from 'naive-ui';
import { 
    ArrowBackOutline, CreateOutline, TrashOutline, LocationOutline, ChevronDownOutline 
} from '@vicons/ionicons5';

const props = defineProps({
    order: Object,
    diagram_data: Array,
    stats: Object,
    assignable_users: Array,
    available_products: Array,
    can_view_financials: Boolean 
});

const { hasPermission } = usePermissions();
const { dialog, notification } = createDiscreteApi(['dialog', 'notification']);

// --- LÓGICA DE PESTAÑAS Y URL ---
const activeTab = ref('gantt');

onMounted(() => {
    const urlParams = new URLSearchParams(window.location.search);
    const tab = urlParams.get('tab');
    if (tab) {
        activeTab.value = tab;
    }
});

const handleTabChange = (name) => {
    activeTab.value = name;
    // Modificar URL sin recargar la página
    const url = new URL(window.location.href);
    url.searchParams.set('tab', name);
    window.history.replaceState({}, '', url);
};

// --- ESTADO Y UTILIDADES ---
const formatCurrency = (amount) => new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(amount);
const formatDate = (dateString) => {
    if(!dateString) return 'Sin definir';
    const date = new Date(dateString);
    return date.toLocaleDateString('es-MX', { year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' });
};

// --- Propiedades Computadas ---
const formattedAddress = computed(() => {
    const o = props.order;
    const parts = [
        o.installation_street ? (o.installation_street + (o.installation_exterior_number ? ` #${o.installation_exterior_number}` : '')) : null,
        o.installation_interior_number ? `Int. ${o.installation_interior_number}` : null,
        o.installation_neighborhood ? `Col. ${o.installation_neighborhood}` : null,
        o.installation_zip_code ? `CP ${o.installation_zip_code}` : null,
        o.installation_municipality,
        o.installation_state
    ];
    const atomized = parts.filter(Boolean).join(', ');
    if (!atomized && o.installation_address) return o.installation_address;
    return atomized || 'Sin dirección registrada';
});

const googleMapsUrl = computed(() => {
    const o = props.order;
    const addressQuery = [
        o.installation_street,
        o.installation_exterior_number,
        o.installation_neighborhood,
        o.installation_municipality,
        o.installation_state,
        o.installation_country || 'México'
    ].filter(Boolean).join(', ');
    const finalQuery = addressQuery || o.installation_address;
    if (!finalQuery) return null;
    return `https://www.google.com/maps/dir/?api=1&destination=${encodeURIComponent(finalQuery)}`;
});

const generalProgress = computed(() => {
    if (!props.stats || props.stats.total_tasks === 0) return 0;
    return Math.round((props.stats.completed_tasks / props.stats.total_tasks) * 100);
});

const formattedTotal = computed(() =>
  new Intl.NumberFormat('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(props.order.total_amount ?? 0)
);

// --- ESTATUS DE ORDEN ---
const orderStatusOptions = [
    { label: 'Cotización', value: 'Cotización' }, { label: 'Aceptado', value: 'Aceptado' },
    { label: 'En Proceso', value: 'En Proceso' }, { label: 'Completado', value: 'Completado' }, 
    { label: 'Facturado', value: 'Facturado' }, { label: 'Cancelado', value: 'Cancelado' }
];

const getStatusType = (status) => {
    const map = { 'Cotización': 'default', 'Aceptado': 'info', 'En Proceso': 'warning', 'Completado': 'success', 'Facturado': 'success', 'Cancelado': 'error' };
    return map[status] || 'default';
};

const handleStatusUpdate = (newStatus) => {
    if (!hasPermission('service_orders.change_status')) return;
    router.patch(route('service-orders.update-status', props.order.id), { status: newStatus }, {
        preserveScroll: true,
        onSuccess: () => notification.success({ title: 'Estatus Actualizado', content: `Orden cambió a ${newStatus}`, duration: 3000 })
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
                    <n-button 
                        v-if="hasPermission('service_orders.edit')" 
                        quaternary type="warning" 
                        @click="() => router.visit(route('service-orders.edit', order.id))"
                    >
                        <template #icon><n-icon><CreateOutline /></n-icon></template> Editar
                    </n-button>
                    <n-button 
                        v-if="hasPermission('service_orders.delete') && !['Completado', 'Facturado'].includes(order.status)" 
                        quaternary type="error" 
                        @click="confirmDelete"
                    >
                        <template #icon><n-icon><TrashOutline /></n-icon></template> Eliminar
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
                    
                    <n-card size="small" class="rounded-2xl shadow-sm bg-blue-50/30 border-blue-100">
                        <div class="flex flex-col h-full justify-between">
                            <div>
                                <div class="flex items-center gap-2 text-blue-800 font-semibold mb-2">
                                    <n-icon><LocationOutline /></n-icon> Ubicación
                                </div>
                                <p class="text-sm text-gray-600 line-clamp-3 leading-snug">
                                    {{ formattedAddress }}
                                </p>
                            </div>
                            <a v-if="googleMapsUrl" :href="googleMapsUrl" target="_blank" class="mt-3 text-xs font-bold text-blue-600 hover:underline flex items-center gap-1">
                                Cómo llegar <n-icon size="10" class="-rotate-45"><ArrowBackOutline/></n-icon>
                            </a>
                            <div v-else class="mt-3 text-xs text-gray-400 italic">
                                Sin ubicación precisa
                            </div>
                        </div>
                    </n-card>
                </div>

                <div class="bg-white rounded-3xl shadow-lg border border-gray-100 overflow-hidden min-h-[500px]">
                    <!-- Implementación de sincronización de tabs con :value y @update:value -->
                    <n-tabs type="line" size="large" animated class="px-6 pt-4" :value="activeTab" @update:value="handleTabChange">
                        
                        <n-tab-pane v-if="hasPermission('tasks.view_board')" name="gantt" tab="Cronograma y Tareas">
                            <div class="py-4 space-y-6">
                                <TaskGanttChart :tasks="diagram_data" :order-id="order.id" :assignable-users="assignable_users" />
                            </div>
                        </n-tab-pane>

                        <n-tab-pane name="items" tab="Materiales y Productos">
                             <OrderItemsTab 
                                :order="order" 
                                :available_products="available_products" 
                                :can_view_financials="can_view_financials" 
                             />
                        </n-tab-pane>

                        <n-tab-pane name="details" tab="Detalles Operativos">
                            <OrderDetailsTab :order="order" />
                        </n-tab-pane>

                        <n-tab-pane name="files" tab="Evidencias">
                            <OrderFilesTab :order="order" />
                        </n-tab-pane>

                    </n-tabs>
                </div>
            </div>
        </div>
    </AppLayout>
</template>