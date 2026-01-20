<script setup>
import { ref, watch, h, computed } from 'vue';
import { usePermissions } from '@/Composables/usePermissions';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { 
    NButton, NDataTable, NInput, NSpace, NTag, NAvatar, NIcon, NModal, NEmpty, NPagination, createDiscreteApi, NSelect
} from 'naive-ui';
import { 
    SearchOutline, AddOutline, CreateOutline, TrashOutline, 
    TicketOutline, PersonOutline, ConstructOutline, TimeOutline, AlertCircleOutline, LocationOutline
} from '@vicons/ionicons5';
import { format, parse } from 'date-fns';
import { es } from 'date-fns/locale';

const { hasPermission } = usePermissions();

// Props desde el controlador
const props = defineProps({
    tickets: Object,
    filters: Object,
    municipalities: Array, // Lista de municipios disponibles
    states: Array,         // Lista de estados disponibles
});

// Configuración de Notificaciones
const { notification, dialog } = createDiscreteApi(['notification', 'dialog']);

// Estado para filtros
const search = ref(props.filters.search || '');
const statusFilter = ref(props.filters.status || null);
const priorityFilter = ref(props.filters.priority || null);
const municipalityFilter = ref(props.filters.municipality || null);
const stateFilter = ref(props.filters.state || null);

// Opciones para selectores estáticos
const statusOptions = [
    { label: 'Todos los Estatus', value: null },
    { label: 'Abierto', value: 'Abierto' },
    { label: 'En Análisis', value: 'En Análisis' },
    { label: 'Resuelto', value: 'Resuelto' },
    { label: 'Cerrado', value: 'Cerrado' }
];

const priorityOptions = [
    { label: 'Todas las Prioridades', value: null },
    { label: 'Baja', value: 'Baja' },
    { label: 'Media', value: 'Media' },
    { label: 'Alta', value: 'Alta' },
    { label: 'Urgente', value: 'Urgente' }
];

// Opciones dinámicas para Municipios y Estados
const municipalityOptions = computed(() => {
    const opts = props.municipalities.map(m => ({ label: m, value: m }));
    return [{ label: 'Todos los Municipios', value: null }, ...opts];
});

const stateOptions = computed(() => {
    const opts = props.states.map(s => ({ label: s, value: s }));
    return [{ label: 'Todos los Estados', value: null }, ...opts];
});

// Función para recargar la vista con filtros (Debounce solo para search)
let timeout = null;

const applyFilters = () => {
    router.get(route('tickets.index'), { 
        search: search.value,
        status: statusFilter.value,
        priority: priorityFilter.value,
        municipality: municipalityFilter.value,
        state: stateFilter.value
    }, { preserveState: true, replace: true });
};

watch(search, () => {
    clearTimeout(timeout);
    timeout = setTimeout(applyFilters, 300);
});

watch([statusFilter, priorityFilter, municipalityFilter, stateFilter], () => {
    applyFilters();
});

// Confirmar eliminación
const confirmDelete = (ticket) => {
    dialog.warning({
        title: 'Eliminar Ticket',
        content: `¿Estás seguro de que deseas eliminar el ticket #${ticket.id}? Esta acción no se puede deshacer.`,
        positiveText: 'Eliminar',
        negativeText: 'Cancelar',
        onPositiveClick: () => {
            router.delete(route('tickets.destroy', ticket.id), {
                onSuccess: () => {
                    notification.success({
                        title: 'Éxito',
                        content: 'Ticket eliminado correctamente',
                        duration: 3000
                    });
                }
            });
        }
    });
};

const goToEdit = (id) => {
    router.get(route('tickets.edit', id));
};

// --- Helpers Visuales ---
const getPriorityColor = (priority) => {
    switch (priority) {
        case 'Urgente': return 'error';
        case 'Alta': return 'warning';
        case 'Media': return 'info';
        default: return 'default';
    }
};

const getStatusType = (status) => {
    switch (status) {
        case 'Resuelto': return 'success';
        case 'Cerrado': return 'default';
        case 'En Análisis': return 'warning';
        default: return 'info'; // Abierto
    }
};

// Función para formatear fechas
const formatDateLong = (dateStr) => {
    if (!dateStr) return 'Fecha desconocida';
    let dateObj = new Date(dateStr);

    if (typeof dateStr === 'string' && dateStr.includes('/')) {
        const parsed = parse(dateStr, 'd/M/yyyy HH:mm', new Date());
        if (!isNaN(parsed.getTime())) {
            dateObj = parsed;
        }
    }
    
    if (isNaN(dateObj.getTime())) return dateStr; 

    return format(dateObj, "d 'de' MMMM yyyy, h:mm a", { locale: es });
};

// --- Configuración de Columnas (Naive UI DataTable) ---
const createColumns = () => [
    {
        title: 'Folio',
        key: 'id',
        width: 80,
        align: 'center',
        render(row) {
            return h('span', { class: 'font-mono text-gray-500 font-bold' }, `#${row.id}`);
        }
    },
    {
        title: 'Asunto / Descripción',
        key: 'title',
        render(row) {
            return h('div', { class: 'flex flex-col gap-1' }, [
                h('span', { class: 'font-bold text-gray-800 text-sm' }, row.title),
                h('span', { class: 'text-xs text-gray-400 truncate max-w-xs' }, row.description_preview),
                row.service_order_id ? h(NTag, { type: 'info', size: 'tiny', bordered: false, class: 'w-max mt-1' }, {
                    default: () => h('div', { class: 'flex items-center gap-1' }, [
                        h(NIcon, { component: ConstructOutline }),
                        h('span', `Orden #${row.service_order_id}`)
                    ])
                }) : null
            ]);
        }
    },
    {
        title: 'Cliente / Ubicación',
        key: 'client',
        render(row) {
            if (!row.client) return h('span', { class: 'text-gray-400 italic' }, 'Sin Cliente');
            
            const locationText = [row.client.municipality, row.client.state].filter(Boolean).join(', ');

            return h('div', { class: 'flex items-center gap-2' }, [
                h(NIcon, { component: PersonOutline, class: 'text-gray-400' }),
                h('div', { class: 'flex flex-col' }, [
                    h('span', { class: 'text-gray-700 text-sm font-medium' }, row.client.name),
                    locationText ? h('span', { class: 'text-[10px] text-gray-400 flex items-center gap-1' }, [
                        h(NIcon, { component: LocationOutline, class: 'text-xs' }),
                        locationText
                    ]) : null
                ])
            ]);
        }
    },
    {
        title: 'Estatus',
        key: 'status',
        width: 120,
        render(row) {
            return h(NTag, { type: getStatusType(row.status), size: 'small', bordered: false, round: true }, { default: () => row.status });
        }
    },
    {
        title: 'Prioridad',
        key: 'priority',
        width: 120,
        render(row) {
            return h(NTag, { type: getPriorityColor(row.priority), size: 'small', bordered: true }, { 
                default: () => h('div', { class: 'flex items-center gap-1' }, [
                    row.priority === 'Urgente' ? h(NIcon, { component: AlertCircleOutline }) : null,
                    h('span', row.priority)
                ])
            });
        }
    },
    {
        title: 'Fecha',
        key: 'created_at',
        render(row) {
            return h('div', { class: 'flex items-center gap-1 text-gray-500 text-xs' }, [
                h(NIcon, { component: TimeOutline }),
                h('span', { class: 'capitalize' }, formatDateLong(row.created_at))
            ]);
        }
    },
    {
        title: '',
        key: 'actions',
        width: 100,
        render(row) {
            return h(NSpace, { justify: 'end' }, () => [
                h(NButton, {
                        circle: true, size: 'small', type: 'warning', ghost: true,
                        onClick: (e) => { e.stopPropagation(); goToEdit(row.id); }
                    },
                    { icon: () => h(NIcon, null, { default: () => h(CreateOutline) }) }
                ),
                h(NButton, {
                        circle: true, size: 'small', type: 'error', ghost: true,
                        onClick: (e) => { e.stopPropagation(); confirmDelete(row); }
                    },
                    { icon: () => h(NIcon, null, { default: () => h(TrashOutline) }) }
                )
            ]);
        }
    }
];

const columns = createColumns();

const handlePageChange = (page) => {
    router.get(props.tickets.path + '?page=' + page, { 
        search: search.value,
        status: statusFilter.value,
        priority: priorityFilter.value,
        municipality: municipalityFilter.value,
        state: stateFilter.value
    }, { preserveState: true });
};

const rowProps = (row) => {
  return {
    style: 'cursor: pointer;',
    onClick: () => router.get(route('tickets.show', row.id))
  }
}

</script>

<template>
    <AppLayout title="Soporte y Tickets">
        <template #header>
            <div class="lg:flex flex-col md:flex-row justify-between items-center w-11/12 mx-auto gap-4">
                <div>
                    <h2 class="font-semibold text-2xl text-gray-800 leading-tight">
                        Tickets de Soporte
                    </h2>
                    <p class="text-sm text-gray-500 mt-1">Gestiona reportes e incidencias de clientes</p>
                </div>
                <Link v-if="hasPermission('tickets.create')" :href="route('tickets.create')">
                    <n-button type="primary" round size="large" class="mt-2 lg:mt-0 shadow-md hover:shadow-lg transition-shadow duration-300">
                        <template #icon><n-icon><AddOutline /></n-icon></template>
                        Nuevo Ticket
                    </n-button>
                </Link>
            </div>
        </template>

        <div class="py-8 min-h-screen">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                
                <!-- Barra de filtros -->
                <div class="mb-6 px-4 sm:px-0 space-y-4">
                    <div class="flex flex-col md:flex-row gap-3">
                        <!-- Buscador -->
                        <div class="w-full md:flex-1">
                            <n-input 
                                v-model:value="search" 
                                type="text" 
                                placeholder="Buscar por folio, asunto o cliente..." 
                                class="shadow-sm rounded-full"
                                clearable
                                round
                                size="large"
                            >
                                <template #prefix><n-icon :component="SearchOutline" class="text-gray-400" /></template>
                            </n-input>
                        </div>
                        
                        <!-- Filtros Rápidos (Estatus/Prioridad) -->
                        <div class="flex gap-2 w-full md:w-auto">
                            <n-select 
                                v-model:value="statusFilter" 
                                :options="statusOptions" 
                                placeholder="Estatus" 
                                clearable
                                class="w-1/2 md:w-40"
                            />
                            <n-select 
                                v-model:value="priorityFilter" 
                                :options="priorityOptions" 
                                placeholder="Prioridad" 
                                clearable
                                class="w-1/2 md:w-40"
                            />
                        </div>
                    </div>

                    <!-- Filtros de Ubicación (Segunda Fila) -->
                    <div class="flex flex-col md:flex-row gap-3">
                         <n-select 
                            v-model:value="stateFilter" 
                            :options="stateOptions" 
                            placeholder="Filtrar por Estado" 
                            filterable
                            clearable
                            class="w-full md:w-1/3"
                        >
                            <template #prefix><n-icon :component="LocationOutline" /></template>
                        </n-select>

                        <n-select 
                            v-model:value="municipalityFilter" 
                            :options="municipalityOptions" 
                            placeholder="Filtrar por Municipio" 
                            filterable
                            clearable
                            class="w-full md:w-1/3"
                        >
                            <template #prefix><n-icon :component="LocationOutline" /></template>
                        </n-select>
                    </div>
                </div>

                <!-- TABLA (Escritorio) -->
                <div class="hidden md:block bg-white/80 backdrop-blur-xl rounded-3xl shadow-lg border border-gray-100 overflow-hidden">
                    <n-data-table
                        :columns="columns"
                        :data="tickets.data"
                        :pagination="false"
                        :bordered="false"
                        single-column
                        :row-props="rowProps"
                        class="custom-table"
                    />
                    <div class="p-4 flex justify-end border-t border-gray-100" v-if="tickets.total > 0">
                        <n-pagination
                            :page="tickets.current_page"
                            :page-count="tickets.last_page"
                            :on-update:page="handlePageChange"
                        />
                    </div>
                </div>

                <!-- CARDS (Móvil) -->
                <div class="md:hidden space-y-4 px-4 sm:px-0">
                    <div v-if="tickets.data.length === 0" class="flex justify-center mt-10">
                        <n-empty description="No se encontraron tickets" />
                    </div>

                    <div 
                        v-for="ticket in tickets.data" 
                        :key="ticket.id" 
                        class="bg-white rounded-2xl p-4 shadow-sm border border-gray-100 flex flex-col gap-3 relative overflow-hidden"
                        @click="router.get(route('tickets.show', ticket.id))"
                    >
                        <!-- Header Card -->
                        <div class="flex justify-between items-start">
                            <div class="flex items-center gap-2">
                                <span class="bg-gray-100 text-gray-600 font-mono text-xs px-2 py-1 rounded-md">#{{ ticket.id }}</span>
                                <n-tag :type="getPriorityColor(ticket.priority)" size="small" :bordered="false">{{ ticket.priority }}</n-tag>
                            </div>
                            <div class="flex gap-1">
                                <button v-if="hasPermission('tickets.edit')" @click.stop="goToEdit(ticket.id)" class="text-amber-500 hover:bg-amber-50 p-1.5 rounded-full transition">
                                    <n-icon size="18"><CreateOutline /></n-icon>
                                </button>
                                <button v-if="hasPermission('tickets.delete')" @click.stop="confirmDelete(ticket)" class="text-red-500 hover:bg-red-50 p-1.5 rounded-full transition">
                                    <n-icon size="18"><TrashOutline /></n-icon>
                                </button>
                            </div>
                        </div>

                        <!-- Info -->
                        <div class="pr-2">
                            <h3 class="text-base font-bold text-gray-800 leading-tight">{{ ticket.title }}</h3>
                            <p class="text-xs text-gray-400 mt-1 line-clamp-2">{{ ticket.description_preview }}</p>
                        </div>

                        <!-- Cliente y Ubicación -->
                        <div class="flex items-center gap-2 mt-1 border-t border-gray-50 pt-3">
                            <n-avatar size="small" round class="bg-blue-50 text-blue-500">
                                <n-icon><PersonOutline/></n-icon>
                            </n-avatar>
                            <div class="flex flex-col w-full">
                                <span class="text-xs font-semibold text-gray-700">{{ ticket.client?.name || 'Sin Cliente' }}</span>
                                <div v-if="ticket.client?.municipality || ticket.client?.state" class="flex items-center gap-1 text-[10px] text-gray-400">
                                    <n-icon><LocationOutline /></n-icon>
                                    {{ [ticket.client?.municipality, ticket.client?.state].filter(Boolean).join(', ') }}
                                </div>
                            </div>
                            <div class="ml-auto">
                                <n-tag :type="getStatusType(ticket.status)" size="small" round>{{ ticket.status }}</n-tag>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-center mt-6" v-if="tickets.total > 0">
                        <n-pagination simple :page="tickets.current_page" :page-count="tickets.last_page" :on-update:page="handlePageChange" />
                    </div>
                </div>

            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
:deep(.n-data-table .n-data-table-th) {
    background-color: transparent;
    font-weight: 600;
    color: #6b7280;
    border-bottom: 1px solid #f3f4f6;
    text-transform: uppercase;
    font-size: 0.75rem;
    letter-spacing: 0.05em;
}
:deep(.n-data-table .n-data-table-td) {
    background-color: transparent;
    border-bottom: 1px solid #f9fafb;
    padding-top: 12px;
    padding-bottom: 12px;
}
:deep(.n-data-table:hover .n-data-table-td) {
    background-color: rgba(249, 250, 251, 0.5);
}
</style>