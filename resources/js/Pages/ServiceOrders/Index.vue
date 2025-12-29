<script setup>
import { ref, watch, h } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { 
    NButton, NDataTable, NInput, NSpace, NTag, NAvatar, NIcon, NEmpty, NPagination, 
    createDiscreteApi, NTooltip, NProgress, NSelect, NPopselect 
} from 'naive-ui';
import { 
    SearchOutline, AddOutline, EyeOutline, CreateOutline, TrashOutline, 
    ConstructOutline, CalendarOutline, PersonOutline, LocationOutline, 
    CheckmarkCircleOutline, ChevronDownOutline 
} from '@vicons/ionicons5';

const props = defineProps({
    orders: Object, // Paginado
    filters: Object,
    statuses: Array
});

// Configuración de Notificaciones
const { notification, dialog } = createDiscreteApi(['notification', 'dialog']);

// Lógica de búsqueda y filtrado
const search = ref(props.filters.search || '');
const statusFilter = ref(props.filters.status || null);
let searchTimeout;

const applyFilters = () => {
    router.get(route('service-orders.index'), { 
        search: search.value,
        status: statusFilter.value 
    }, { preserveState: true, replace: true });
};

watch(search, (value) => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(applyFilters, 300);
});

watch(statusFilter, applyFilters);

// Acciones de Navegación
const goToEdit = (id) => router.visit(route('service-orders.edit', id));
const goToShow = (id) => router.visit(route('service-orders.show', id));

// Lógica para cambiar estatus (NUEVO)
const handleStatusUpdate = (row, newStatus) => {
    // Optimistic UI update o simplemente esperar la recarga
    router.patch(route('service-orders.update-status', row.id), {
        status: newStatus
    }, {
        preserveScroll: true,
        onSuccess: () => {
            notification.success({ 
                title: 'Estatus Actualizado', 
                content: `La orden #${row.id} ahora está en: ${newStatus}`,
                duration: 2000 
            });
        },
        onError: () => {
            notification.error({ title: 'Error', content: 'No se pudo actualizar el estatus.' });
        }
    });
};

const confirmDelete = (order) => {
    dialog.warning({
        title: 'Eliminar Orden',
        content: `¿Estás seguro de eliminar la orden #${order.id}?`,
        positiveText: 'Eliminar',
        negativeText: 'Cancelar',
        onPositiveClick: () => {
            router.delete(route('service-orders.destroy', order.id), {
                onSuccess: () => notification.success({ title: 'Éxito', content: 'Orden eliminada', duration: 3000 }),
                onError: () => notification.error({ title: 'Error', content: 'No se puede eliminar esta orden.', duration: 4000 })
            });
        }
    });
};

// Utilidades
const formatCurrency = (amount) => new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(amount);

const getStatusColor = (status) => {
    const map = {
        'Cotización': 'default',
        'Aceptado': 'info',
        'En Proceso': 'warning',
        'Instalado': 'success',
        'Facturado': 'success',
        'Cancelado': 'error'
    };
    return map[status] || 'default';
};

// Opciones para el select de filtro y cambio de estado
const statusOptions = props.statuses.map(s => ({ label: s, value: s }));

// --- Configuración de Columnas Desktop ---
const createColumns = () => [
    {
        title: 'Folio',
        key: 'id',
        width: 80,
        render(row) {
            return h('span', { class: 'font-mono text-gray-500 font-bold' }, `#${row.id}`);
        }
    },
    {
        title: 'Cliente / Ubicación',
        key: 'client',
        render(row) {
            return h('div', { class: 'flex flex-col' }, [
                h('span', { class: 'font-bold text-gray-800 text-sm' }, row.client?.name || 'Cliente Eliminado'),
                h('div', { class: 'flex items-center gap-1 text-xs text-gray-400 mt-1' }, [
                    h(NIcon, { component: LocationOutline }),
                    h('span', { class: 'truncate max-w-[200px]' }, row.installation_address)
                ])
            ]);
        }
    },
    {
        title: 'Estado & Avance',
        key: 'status',
        width: 220, // Aumentado ligeramente para mejor espacio
        render(row) {
            return h('div', { class: 'flex flex-col gap-2 w-full' }, [
                // Popselect para cambiar estatus al hacer click
                h(NPopselect, {
                    options: statusOptions,
                    trigger: 'click',
                    onUpdateValue: (val) => handleStatusUpdate(row, val)
                }, {
                    // CORRECCIÓN AQUÍ: Usamos 'default' en lugar de 'trigger'
                    default: () => h(NTag, { 
                        type: getStatusColor(row.status), 
                        size: 'small', 
                        bordered: false, 
                        round: true,
                        style: { cursor: 'pointer', width: 'fit-content' },
                        // Detenemos la propagación aquí para que el clic no llegue a la fila
                        onClick: (e) => e.stopPropagation(),
                    }, { 
                        default: () => [
                            row.status,
                            h(NIcon, { class: 'ml-1 text-xs opacity-70' }, { default: () => h(ChevronDownOutline) })
                        ] 
                    })
                }),
                
                // Barra de progreso corregida (width 100% y bloque contenedor)
                h('div', { class: 'w-full pr-4' }, [
                    h(NProgress, { 
                        type: 'line', 
                        percentage: row.progress, 
                        color: row.status === 'Cancelado' ? '#ff4d4f' : undefined,
                        height: 10,
                        'indicator-placement': 'inside'
                    })
                ])
            ]);
        }
    },
    {
        title: 'Técnico / Fecha',
        key: 'technician',
        render(row) {
            return h('div', { class: 'flex flex-col text-xs' }, [
                row.technician ? h('div', { class: 'flex items-center gap-2 mb-1' }, [
                    // Se usa .photo que ahora trae URL completa
                    h(NAvatar, { size: 24, src: row.technician.photo, round: true, fallbackSrc: 'https://ui-avatars.com/api/?name='+row.technician.name }),
                    h('span', { class: 'text-gray-600 font-medium' }, row.technician.name)
                ]) : h('span', { class: 'text-amber-500 italic' }, 'Sin asignar'),
                
                row.start_date ? h('div', { class: 'flex items-center gap-1 text-gray-400' }, [
                    h(NIcon, { component: CalendarOutline }),
                    h('span', row.start_date)
                ]) : null
            ]);
        }
    },
    {
        title: 'Total',
        key: 'total_amount',
        align: 'right',
        render(row) {
            return h('span', { class: 'font-mono text-gray-700 font-medium' }, formatCurrency(row.total_amount));
        }
    },
    {
        title: '',
        key: 'actions',
        width: 140,
        render(row) {
            return h(NSpace, { justify: 'end' }, () => [
                h(NTooltip, { trigger: 'hover' }, {
                    trigger: () => h(NButton, {
                        circle: true, size: 'small', quaternary: true, type: 'info',
                        onClick: (e) => { e.stopPropagation(); goToShow(row.id); }
                    }, { icon: () => h(NIcon, null, { default: () => h(EyeOutline) }) }),
                    default: () => 'Ver Detalles'
                }),
                h(NButton, {
                    circle: true, size: 'small', quaternary: true, type: 'warning',
                    onClick: (e) => { e.stopPropagation(); goToEdit(row.id); }
                }, { icon: () => h(NIcon, null, { default: () => h(CreateOutline) }) })
            ]);
        }
    }
];

const columns = createColumns();

const handlePageChange = (page) => {
    router.get(route('service-orders.index'), { page, search: search.value, status: statusFilter.value }, { preserveState: true });
};

const rowProps = (row) => ({
    style: 'cursor: pointer;',
    onClick: () => goToShow(row.id)
});

</script>

<template>
    <AppLayout title="Órdenes de Servicio">
        <template #header>
            <div class="flex flex-col md:flex-row justify-between items-center gap-4">
                <div>
                    <h2 class="font-semibold text-2xl text-gray-800 leading-tight">
                        Órdenes de Servicio
                    </h2>
                    <p class="text-sm text-gray-500 mt-1">Gestión operativa e instalaciones</p>
                </div>
                <!-- Botón Crear -->
                <Link :href="route('service-orders.create')">
                    <n-button type="primary" round size="large" class="shadow-md hover:shadow-lg transition-shadow duration-300">
                        <template #icon>
                            <n-icon><AddOutline /></n-icon>
                        </template>
                        Nueva Orden
                    </n-button>
                </Link>
            </div>
        </template>

        <div class="py-8 min-h-screen">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                
                <!-- Filtros -->
                <div class="mb-6 px-4 sm:px-0 flex flex-col md:flex-row justify-between items-center gap-4">
                    <n-input 
                        v-model:value="search" 
                        type="text" 
                        placeholder="Buscar por folio, cliente o dirección..." 
                        class="w-full md:max-w-md shadow-sm rounded-full"
                        clearable round size="large"
                    >
                        <template #prefix><n-icon :component="SearchOutline" class="text-gray-400" /></template>
                    </n-input>

                    <n-select 
                        v-model:value="statusFilter"
                        :options="statusOptions"
                        placeholder="Filtrar por Estado"
                        clearable
                        class="w-full md:w-48"
                    />
                </div>

                <!-- TABLA (Escritorio) -->
                <div class="hidden md:block bg-white/80 backdrop-blur-xl rounded-3xl shadow-lg border border-gray-100 overflow-hidden">
                    <n-data-table
                        :columns="columns"
                        :data="orders.data"
                        :pagination="false"
                        :bordered="false"
                        single-column
                        :row-props="rowProps"
                        class="custom-table"
                    />
                    <!-- Paginación -->
                    <div class="p-4 flex justify-end border-t border-gray-100" v-if="orders.total > 0">
                        <n-pagination
                            :page="orders.current_page"
                            :page-count="orders.last_page"
                            :on-update:page="handlePageChange"
                        />
                    </div>
                </div>

                <!-- CARDS (Móvil) -->
                <div class="md:hidden space-y-4 px-4 sm:px-0">
                    <div v-if="orders.data.length === 0" class="flex justify-center mt-10">
                        <n-empty description="No se encontraron órdenes" />
                    </div>

                    <div 
                        v-for="order in orders.data" 
                        :key="order.id" 
                        class="bg-white rounded-2xl p-4 shadow-sm border border-gray-100 relative overflow-hidden active:bg-gray-50 transition-colors"
                        @click="goToShow(order.id)"
                    >
                        <!-- Header Card -->
                        <div class="flex justify-between items-start mb-3">
                            <div class="flex items-center gap-2">
                                <div class="bg-indigo-50 text-indigo-600 px-2 py-1 rounded-md font-mono font-bold text-xs">
                                    #{{ order.id }}
                                </div>
                                <span class="text-xs text-gray-400">{{ order.created_at_human }}</span>
                            </div>
                            
                            <!-- Estado Cambiable en Móvil también -->
                            <n-popselect 
                                :options="statusOptions" 
                                trigger="click"
                                @update:value="(val) => handleStatusUpdate(order, val)"
                                @click.stop
                            >
                                <n-tag :type="getStatusColor(order.status)" size="small" round :bordered="false">
                                    {{ order.status }}
                                    <template #icon><n-icon :component="ChevronDownOutline" /></template>
                                </n-tag>
                            </n-popselect>
                        </div>

                        <!-- Info Principal -->
                        <div class="mb-3">
                            <h3 class="font-bold text-gray-800 text-base mb-1">{{ order.client?.name }}</h3>
                            <div class="flex items-start gap-1 text-xs text-gray-500">
                                <n-icon :component="LocationOutline" class="mt-0.5"/>
                                <span class="line-clamp-2">{{ order.installation_address }}</span>
                            </div>
                        </div>

                        <!-- Técnico y Progreso -->
                        <div class="bg-gray-50 rounded-xl p-3 mb-3">
                            <div class="flex justify-between items-center mb-2">
                                <span class="text-xs font-semibold text-gray-500">Progreso de Tareas</span>
                                <span class="text-xs font-mono text-gray-700">{{ order.progress }}%</span>
                            </div>
                            <n-progress type="line" :percentage="order.progress" height="4" :show-indicator="false" status="success" />
                            
                            <div class="mt-3 flex items-center gap-2" v-if="order.technician">
                                <n-avatar size="small" round :src="order.technician.photo" />
                                <span class="text-xs text-gray-700">{{ order.technician.name }}</span>
                            </div>
                            <div v-else class="mt-3 text-xs text-amber-600 italic">Sin técnico asignado</div>
                        </div>

                        <!-- Footer Actions -->
                        <div class="flex justify-between items-center border-t border-gray-100 pt-3">
                            <span class="font-bold text-gray-800">{{ formatCurrency(order.total_amount) }}</span>
                            <div class="flex gap-2">
                                <n-button circle size="small" quaternary type="info" @click.stop="goToShow(order.id)">
                                    <template #icon><n-icon :component="EyeOutline" /></template>
                                </n-button>
                                <n-button circle size="small" quaternary type="warning" @click.stop="goToEdit(order.id)">
                                    <template #icon><n-icon :component="CreateOutline" /></template>
                                </n-button>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-center mt-6" v-if="orders.total > 0">
                        <n-pagination simple :page="orders.current_page" :page-count="orders.last_page" :on-update:page="handlePageChange" />
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.custom-table {
    --n-th-font-weight: 600;
    --n-td-padding: 16px;
}
:deep(.n-data-table .n-data-table-th) {
    background-color: transparent;
    color: #6b7280;
    border-bottom: 1px solid #f3f4f6;
    text-transform: uppercase;
    font-size: 0.75rem;
}
</style>