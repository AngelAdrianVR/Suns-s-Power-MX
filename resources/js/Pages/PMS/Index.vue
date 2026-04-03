<script setup>
import { ref, watch, computed, h, reactive } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import draggable from 'vuedraggable';
import TaskCard from './Components/TaskCard.vue';
import TaskForm from './Components/TaskForm.vue';
import TaskDetailModal from './Components/TaskDetailModal.vue';
import { 
    NButton, NIcon, NCard, NTag, NAvatar, NBadge, 
    NModal, NForm, NFormItem, NSelect, NDatePicker, createDiscreteApi, NEmpty,
    NRadioGroup, NRadioButton, NDataTable, NInput, NDrawer, NDrawerContent,
    NList, NListItem, NThing, NProgress, NSwitch
} from 'naive-ui';
import { 
    ArrowBackOutline, ArrowForwardOutline, AddOutline,
    GridOutline, ListOutline, StatsChartOutline, SearchOutline,
    CalendarOutline
} from '@vicons/ionicons5';
import { format, parseISO } from 'date-fns';
import { es } from 'date-fns/locale';

// Composables de Permisos
import { usePermissions } from '@/Composables/usePermissions';

const props = defineProps({
    week_start: String,
    prev_week: String,
    next_week: String,
    days: Array,
    assigned_tasks: Object,
    unassigned_tasks: Array,
    all_tasks: Array,
    assignable_users: Array,
    service_orders: Array,
    tickets: Array,
});

const { hasPermission } = usePermissions();
const { notification } = createDiscreteApi(['notification']);

// --- ESTADOS DE VISTA ---
const viewMode = ref('kanban'); // 'kanban' o 'list'
const showMetricsDrawer = ref(false);
const listSearch = ref('');
const showCompletedTasks = ref(false); // Toggle para ocultar tareas completadas en Kanban
const showMobileBacklog = ref(false); // Toggle para mostrar/ocultar backlog en móvil

// --- FILTRO DE PRIORIDAD PARA KANBAN ---
const kanbanPriorityFilter = ref(null);
const priorityFilterOptions = [
    { label: 'Alta', value: 'Alta' },
    { label: 'Media', value: 'Media' },
    { label: 'Baja', value: 'Baja' }
];

const boardColumns = ref({});

// --- SEPARACIÓN DE BACKLOG (Sin Asignar vs Sin Fecha) ---
const unassignedBacklog = ref([]);
const noDateBacklog = ref([]);

// --- MODAL DETALLE DE TAREA ---
const detailModalOpen = ref(false);
const selectedTask = ref(null);

// Para el calendario seleccionable de semana
const datePickerValue = ref(parseISO(props.week_start).getTime());

watch(() => props.week_start, (newVal) => {
    datePickerValue.value = parseISO(newVal).getTime();
});

// Reactividad de Columnas y Tarea Seleccionada
watch(() => props.assigned_tasks, (newVal) => {
    const cols = {};
    props.days.forEach(day => {
        cols[day.date] = newVal[day.date] || [];
    });
    boardColumns.value = cols;

    if (selectedTask.value) {
        let found = null;
        for (const date in newVal) {
            found = newVal[date].find(t => t.id === selectedTask.value.id);
            if (found) break;
        }
        if (found) selectedTask.value = found;
    }
}, { immediate: true, deep: true });

watch(() => props.unassigned_tasks, (newVal) => {
    unassignedBacklog.value = newVal.filter(t => (!t.assignees || t.assignees.length === 0) && t.status !== 'Completado' && t.status !== 'Cancelado');
    noDateBacklog.value = newVal.filter(t => (t.assignees && t.assignees.length > 0 && !t.start_date) && t.status !== 'Completado' && t.status !== 'Cancelado');
    
    if (selectedTask.value) {
        const found = newVal.find(t => t.id === selectedTask.value.id);
        if (found) selectedTask.value = found;
    }
}, { immediate: true, deep: true });

// --- CÁLCULO PARA EL HEADER DEL BACKLOG ---
const backlogUnassignedCount = computed(() => {
    if (!kanbanPriorityFilter.value) return unassignedBacklog.value.length;
    return unassignedBacklog.value.filter(t => t.priority === kanbanPriorityFilter.value).length;
});

const backlogNoDateCount = computed(() => {
    if (!kanbanPriorityFilter.value) return noDateBacklog.value.length;
    return noDateBacklog.value.filter(t => t.priority === kanbanPriorityFilter.value).length;
});

const changeWeek = (dateStr) => {
    router.get(route('tasks.index'), { week_start: dateStr }, { preserveState: true, preserveScroll: true });
};

const onDatePickerChange = (timestamp) => {
    if (timestamp) {
        changeWeek(format(new Date(timestamp), 'yyyy-MM-dd'));
    }
};

// --- MODAL DE CREACIÓN / EDICIÓN ---
const formModalOpen = ref(false);
const taskToEdit = ref(null);

const openCreateForm = () => {
    taskToEdit.value = null; 
    formModalOpen.value = true;
};

const openEditForm = (task) => {
    detailModalOpen.value = false;
    taskToEdit.value = task;
    formModalOpen.value = true;
};

const onFormSaved = () => {
    formModalOpen.value = false;
    notification.success({ title: 'Éxito', content: 'Operación realizada correctamente.', duration: 3000 });
};

// --- DRAG & DROP LOGIC ---
const assignModalOpen = ref(false);
const taskToAssign = ref(null);
const targetDateForAssign = ref(null);

const assignForm = useForm({
    user_ids: [],
    start_date: null
});

const userOptions = computed(() => props.assignable_users.map(u => ({ label: u.name, value: u.id })));

const onBoardChange = (evt, targetDate) => {
    if (evt.added) {
        const task = evt.added.element;
        
        if (!task.assignees || task.assignees.length === 0) {
            taskToAssign.value = task;
            targetDateForAssign.value = targetDate;
            assignForm.user_ids = [];
            assignForm.start_date = targetDate;
            assignModalOpen.value = true;
            return;
        }

        router.put(route('tasks.update', task.id), { start_date: targetDate }, { 
            preserveScroll: true, 
            preserveState: true,
            onSuccess: () => notification.success({ title: 'Reprogramada', content: 'Tarea movida exitosamente.', duration: 2000 })
        });
    }
};

const submitAssignment = () => {
    assignForm.put(route('tasks.update', taskToAssign.value.id), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            notification.success({ title: 'Asignada', content: 'Tarea asignada y programada.', duration: 3000 });
            assignModalOpen.value = false;
            taskToAssign.value = null;
        },
        onError: () => router.reload({ preserveScroll: true })
    });
};

const cancelAssignment = () => {
    assignModalOpen.value = false;
    taskToAssign.value = null;
    router.reload({ preserveScroll: true }); 
};

// Al arrastrar de regreso a la columna única de Backlog
const onBacklogChange = (evt) => {
    if (evt.added) {
        const task = evt.added.element;
        const currentUsers = task.assignees ? task.assignees.map(u => u.id) : [];

        router.put(route('tasks.update', task.id), {
            user_ids: currentUsers,         
            start_date: null,     
            status: task.status === 'Pendiente' ? undefined : 'Pendiente' 
        }, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                notification.success({ title: 'Movida al Backlog', content: 'La tarea ha sido retirada del calendario.', duration: 3000 });
            },
            onError: () => {
                router.reload({ preserveScroll: true }); 
            }
        });
    }
};

// --- ABRIR DETALLE ---
const openDetail = (task) => {
    selectedTask.value = task;
    detailModalOpen.value = true;
};

// ==========================================
// VISTA DE LISTA (TABLA NAIVE UI MEJORADA)
// ==========================================

const filteredAllTasks = computed(() => {
    if (!listSearch.value) return props.all_tasks;
    const s = listSearch.value.toLowerCase();
    return props.all_tasks.filter(t => {
        const titleMatch = t.title?.toLowerCase().includes(s);
        const userMatch = t.assignees?.some(u => u.name.toLowerCase().includes(s));
        let typeText = 'general';
        if (t.taskable_type?.includes('ServiceOrder')) typeText = 'orden de servicio';
        if (t.taskable_type?.includes('Ticket')) typeText = 'ticket';
        
        return titleMatch || userMatch || typeText.includes(s) || t.status.toLowerCase().includes(s);
    });
});

const paginationReactive = reactive({
    page: 1,
    pageSize: 15,
    showSizePicker: true,
    pageSizes: [15, 30, 50],
    onChange: (page) => {
        paginationReactive.page = page;
    },
    onUpdatePageSize: (pageSize) => {
        paginationReactive.pageSize = pageSize;
        paginationReactive.page = 1;
    },
    prefix({ itemCount }) {
        return `Total: ${itemCount} tareas`;
    }
});

const listColumns = computed(() => [
    {
        title: 'Referencia',
        key: 'type',
        minWidth: 140, 
        sorter: (a, b) => {
            const valA = a.taskable_type || 'General';
            const valB = b.taskable_type || 'General';
            return valA.localeCompare(valB);
        },
        filterOptions: [
            { label: 'General', value: 'General' },
            { label: 'Orden de Servicio', value: 'ServiceOrder' },
            { label: 'Ticket de Soporte', value: 'Ticket' }
        ],
        filter(value, row) {
            if (value === 'General') return !row.taskable_type;
            return row.taskable_type?.includes(value);
        },
        render(row) {
            let type = 'General';
            let id = '';
            if (row.taskable_type?.includes('ServiceOrder')) { type = 'Ord. Servicio'; id = `#${row.taskable_id}`; }
            else if (row.taskable_type?.includes('Ticket')) { type = 'Ticket'; id = `#${row.taskable_id}`; }
            return h('div', [
                h('div', { class: 'font-bold text-xs text-gray-600 uppercase tracking-wide' }, type),
                h('div', { class: 'text-blue-600 font-mono text-[11px]' }, id)
            ]);
        }
    },
    {
        title: 'Título de la Tarea',
        key: 'title',
        minWidth: 200, 
        sorter: (a, b) => (a.title || '').localeCompare(b.title || ''),
        render(row) {
            return h('span', { class: 'font-semibold text-gray-800' }, row.title);
        }
    },
    {
        title: 'Responsables',
        key: 'assignees',
        minWidth: 280, 
        sorter: (a, b) => {
            const nameA = a.assignees && a.assignees.length ? a.assignees[0].name : '';
            const nameB = b.assignees && b.assignees.length ? b.assignees[0].name : '';
            return nameA.localeCompare(nameB);
        },
        filterOptions: [
            { label: 'Sin Asignar', value: 'unassigned' },
            { label: 'Sin Fecha', value: 'no_date' },
            ...props.assignable_users.map(u => ({ label: u.name, value: u.id }))
        ],
        filter(value, row) {
            if (value === 'unassigned') {
                return (!row.assignees || row.assignees.length === 0) && row.status !== 'Completado' && row.status !== 'Cancelado';
            }
            if (value === 'no_date') {
                return (row.assignees && row.assignees.length > 0 && !row.start_date) && row.status !== 'Completado' && row.status !== 'Cancelado';
            }
            return row.assignees?.some(u => u.id === value);
        },
        render(row) {
            const elements = [];
            
            if (!row.assignees || row.assignees.length === 0) {
                elements.push(h(NTag, { size: 'small', type: 'warning', bordered: false, round: true, class: 'mr-1 mb-1' }, { default: () => 'Sin asignar' }));
            } else {
                const tags = row.assignees.map(u => h(NTag, { size: 'small', round: true, class: 'mr-1 mb-1 border shadow-sm max-w-full', bordered: false }, { 
                    default: () => h('div', { class: 'flex items-center gap-1 overflow-hidden' }, [
                        h(NAvatar, { size: 14, round: true, src: u.profile_photo_url || `/storage/${u.profile_photo_path}`, class: 'flex-shrink-0' }),
                        h('span', { class: 'truncate max-w-[140px]', title: u.name }, u.name)
                    ]) 
                }));
                elements.push(...tags);
            }

            if (!row.start_date && row.assignees && row.assignees.length > 0 && row.status !== 'Completado' && row.status !== 'Cancelado') {
                 elements.push(h(NTag, { 
                     size: 'small', 
                     color: { color: '#f3e8ff', textColor: '#7e22ce', borderColor: '#d8b4fe' }, 
                     round: true, 
                     class: 'mr-1 mb-1 font-bold shadow-sm' 
                 }, { default: () => 'Sin Fecha' }));
            }

            return h('div', { class: 'flex flex-wrap mt-1 w-full' }, elements);
        }
    },
    {
        title: 'Prioridad',
        key: 'priority',
        minWidth: 110, 
        sorter: (a, b) => {
            const weights = { 'Alta': 3, 'Media': 2, 'Baja': 1 };
            return (weights[a.priority] || 0) - (weights[b.priority] || 0);
        },
        filterOptions: [
            { label: 'Alta', value: 'Alta' },
            { label: 'Media', value: 'Media' },
            { label: 'Baja', value: 'Baja' }
        ],
        filter(value, row) { return row.priority === value; },
        render(row) {
            const pColors = { 'Alta': 'error', 'Media': 'warning', 'Baja': 'info' };
            return h(NTag, { size: 'small', type: pColors[row.priority] || 'default', bordered: true }, { default: () => row.priority }); 
        }
    },
    {
        title: 'Estatus',
        key: 'status',
        minWidth: 130, 
        sorter: (a, b) => (a.status || '').localeCompare(b.status || ''),
        filterOptions: [
            { label: 'Pendiente', value: 'Pendiente' },
            { label: 'En Proceso', value: 'En Proceso' },
            { label: 'Detenido', value: 'Detenido' },
            { label: 'Completado', value: 'Completado' }
        ],
        filter(value, row) { return row.status === value; },
        render(row) {
            const typeMap = { 'Pendiente': 'default', 'En Proceso': 'info', 'Completado': 'success', 'Detenido': 'error' };
            return h(NTag, { type: typeMap[row.status] || 'default', size: 'small', round: true, bordered: false, class: 'font-bold shadow-sm' }, { default: () => row.status });
        }
    },
    {
        title: 'Inicio',
        key: 'start_date',
        minWidth: 120, 
        sorter: (a, b) => new Date(a.start_date || 0).getTime() - new Date(b.start_date || 0).getTime(),
        render(row) {
            return h('span', { class: 'text-xs text-gray-600' }, row.start_date ? format(new Date(row.start_date), 'dd/MM/yy HH:mm') : '-');
        }
    },
    {
        title: 'Límite',
        key: 'due_date',
        minWidth: 120, 
        sorter: (a, b) => new Date(a.due_date || 0).getTime() - new Date(b.due_date || 0).getTime(),
        render(row) {
            return h('span', { class: 'text-xs text-gray-600' }, row.due_date ? format(new Date(row.due_date), 'dd/MM/yy HH:mm') : '-');
        }
    },
    {
        title: 'Fin Real',
        key: 'finish_date',
        minWidth: 120, 
        sorter: (a, b) => new Date(a.finish_date || 0).getTime() - new Date(b.finish_date || 0).getTime(),
        render(row) {
            return h('span', { class: 'text-xs font-bold text-emerald-600' }, row.finish_date ? format(new Date(row.finish_date), 'dd/MM/yy HH:mm') : '-');
        }
    }
]);

const rowProps = (row) => {
    return {
        style: 'cursor: pointer;',
        class: 'hover:bg-blue-50/50 transition-colors',
        onClick: () => openDetail(row)
    };
};

// ==========================================
// MÉTRICAS
// ==========================================

const metricsData = computed(() => {
    const strictPendingTasks = props.all_tasks.filter(t => t.status === 'Pendiente');
    const inProcessTasks = props.all_tasks.filter(t => t.status === 'En Proceso');
    
    const totalPending = strictPendingTasks.length;
    const totalInProcess = inProcessTasks.length;
    
    const userCounts = {};
    props.assignable_users.forEach(u => {
        userCounts[u.id] = { 
            name: u.name, 
            pendingCount: 0, 
            inProcessCount: 0, 
            totalActive: 0,
            avatar: u.profile_photo_url || `/storage/${u.profile_photo_path}` 
        };
    });
    
    let unassignedCount = 0;
    let noDateCount = 0;

    props.all_tasks.forEach(task => {
        if (task.status !== 'Completado' && task.status !== 'Cancelado') {
            if (!task.assignees || task.assignees.length === 0) {
                unassignedCount++;
            } else if (!task.start_date) {
                noDateCount++;
            }
        }

        if (task.assignees && task.assignees.length > 0) {
            task.assignees.forEach(u => {
                if (userCounts[u.id]) {
                    if (task.status === 'Pendiente') {
                        userCounts[u.id].pendingCount++;
                        userCounts[u.id].totalActive++;
                    } else if (task.status === 'En Proceso') {
                        userCounts[u.id].inProcessCount++;
                        userCounts[u.id].totalActive++;
                    }
                }
            });
        }
    });

    const sortedUsers = Object.values(userCounts).sort((a, b) => b.totalActive - a.totalActive);

    return {
        totalPending,
        totalInProcess,
        unassignedCount,
        noDateCount,
        users: sortedUsers
    };
});

</script>

<template>
    <AppLayout title="PMS - Dashboard Semanal">
        <template #header>
            <div class="flex flex-col xl:flex-row justify-between items-start xl:items-center gap-4">
                <div>
                    <h2 class="font-bold text-xl text-gray-800 leading-tight flex items-center gap-2">
                        Dashboard PMS
                    </h2>
                    <p class="text-sm text-gray-500 mt-1">Gestión de Tareas Operativas</p>
                </div>

                <div class="flex flex-wrap items-center gap-2 lg:gap-3">
                    
                    <div class="bg-white rounded-lg shadow-sm border border-gray-100 p-1">
                        <n-radio-group v-model:value="viewMode" size="small" name="view-mode">
                            <n-radio-button value="kanban" class="px-2 lg:px-4">
                                <n-icon class="mr-1"><GridOutline/></n-icon> <span class="hidden sm:inline">Kanban</span>
                            </n-radio-button>
                            <n-radio-button value="list" class="px-2 lg:px-4">
                                <n-icon class="mr-1"><ListOutline/></n-icon> <span class="hidden sm:inline">Lista</span>
                            </n-radio-button>
                        </n-radio-group>
                    </div>

                    <!-- TOGGLE BACKLOG (Sólo en Móvil y Kanban) -->
                    <n-button v-show="viewMode === 'kanban'" class="lg:hidden shadow-sm" :type="showMobileBacklog ? 'warning' : 'default'" @click="showMobileBacklog = !showMobileBacklog" size="small">
                        <template #icon><n-icon><ListOutline/></n-icon></template>
                        {{ showMobileBacklog ? 'Ocultar Backlog' : 'Ver Backlog' }}
                    </n-button>

                    <!-- FILTRO DE PRIORIDAD (Sólo en Kanban) -->
                    <div v-show="viewMode === 'kanban'" class="w-24 sm:w-32 shadow-sm">
                        <n-select 
                            v-model:value="kanbanPriorityFilter" 
                            :options="priorityFilterOptions" 
                            placeholder="Prioridad" 
                            clearable 
                            size="small" 
                        />
                    </div>

                    <!-- TOGGLE: Mostrar Tareas Completadas -->
                    <div v-show="viewMode === 'kanban'" class="flex items-center gap-1 lg:gap-2 bg-white px-2 py-1 lg:px-3 lg:py-1.5 rounded-lg shadow-sm border border-gray-100">
                        <span class="text-[10px] lg:text-[11px] font-bold text-gray-500 uppercase tracking-wide hidden sm:inline">Ver Completadas</span>
                        <span class="text-[10px] font-bold text-gray-500 uppercase tracking-wide sm:hidden">Comp.</span>
                        <n-switch v-model:value="showCompletedTasks" size="small" />
                    </div>

                    <div v-show="viewMode === 'kanban'" class="flex items-center gap-1 bg-white p-1 rounded-lg shadow-sm border border-gray-100">
                        <n-button circle quaternary @click="changeWeek(prev_week)" size="small">
                            <template #icon><n-icon><ArrowBackOutline /></n-icon></template>
                        </n-button>
                        
                        <n-date-picker 
                            v-model:value="datePickerValue" 
                            type="date" 
                            size="small"
                            format="dd MMM yyyy"
                            :clearable="false"
                            @update:value="onDatePickerChange"
                            class="w-[100px] sm:w-36 font-semibold text-xs sm:text-sm"
                        />

                        <n-button circle quaternary @click="changeWeek(next_week)" size="small">
                            <template #icon><n-icon><ArrowForwardOutline /></n-icon></template>
                        </n-button>
                    </div>

                    <n-button secondary type="info" @click="showMetricsDrawer = true" class="shadow-sm" size="small">
                        <template #icon><n-icon><StatsChartOutline /></n-icon></template>
                        <span class="hidden sm:inline">Métricas</span>
                    </n-button>

                    <n-button v-if="hasPermission('pms.create')" type="primary" @click="openCreateForm" class="shadow-sm" size="small">
                        <template #icon>
                            <n-icon><AddOutline /></n-icon>
                        </template>
                        <span class="hidden sm:inline">Nueva</span>
                    </n-button>
                </div>
            </div>
        </template>

        <div class="py-4 lg:py-6 h-[calc(100vh-140px)] lg:h-[calc(100vh-180px)] min-h-[500px] bg-gray-50/50 flex flex-col">
            <div class="w-full px-2 sm:px-4 lg:px-6 flex-1 flex flex-col min-h-0">
                
                <!-- VISTA KANBAN -->
                <div v-if="viewMode === 'kanban'" class="flex flex-col lg:flex-row gap-3 lg:gap-4 flex-1 min-h-0">
                    
                    <!-- SIDEBAR: BACKLOG -->
                    <div v-if="hasPermission('pms.view_all')" 
                         :class="showMobileBacklog ? 'flex h-[40vh] lg:h-auto' : 'hidden lg:flex'" 
                         class="w-full lg:w-72 flex-col bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden flex-shrink-0">
                        
                        <div class="p-2 lg:p-3 bg-gray-50 border-b border-gray-200 flex-shrink-0">
                            <h3 class="font-bold text-gray-600 mb-2 lg:mb-3 text-[12px] lg:text-[13px] uppercase tracking-wider flex items-center justify-between">
                                Backlog de Tareas
                                <n-tag v-if="kanbanPriorityFilter" size="small" type="info" :bordered="false" round>Filtrado</n-tag>
                            </h3>
                            <div class="flex flex-col gap-1.5 lg:gap-2">
                                <div class="flex items-center justify-between bg-orange-50 px-2 py-1.5 rounded border border-orange-100">
                                    <span class="text-[11px] lg:text-xs font-bold text-orange-800">Por Asignar</span>
                                    <n-badge :value="backlogUnassignedCount" type="warning" show-zero />
                                </div>
                                <div class="flex items-center justify-between bg-purple-50 px-2 py-1.5 rounded border border-purple-100">
                                    <span class="text-[11px] lg:text-xs font-bold text-purple-800">Sin Fecha</span>
                                    <n-badge :value="backlogNoDateCount" color="#a855f7" show-zero />
                                </div>
                            </div>
                        </div>
                        
                        <div class="flex-1 overflow-y-auto p-2 lg:p-3 bg-gray-50/30 custom-scrollbar min-h-0">
                            <draggable 
                                v-model="unassignedBacklog" 
                                group="tasks" 
                                item-key="id"
                                class="min-h-full space-y-2 lg:space-y-3"
                                ghost-class="ghost-card"
                                :disabled="!hasPermission('pms.schedule')"
                                @change="onBacklogChange" 
                            >
                                <template #item="{ element }">
                                    <TaskCard 
                                        v-show="!kanbanPriorityFilter || element.priority === kanbanPriorityFilter"
                                        :task="element" 
                                        :is-backlog="true" 
                                        @click="openDetail(element)" 
                                    />
                                </template>
                                <template #empty>
                                    <div v-if="!unassignedBacklog.length && !noDateBacklog.length" class="h-24 lg:h-32 flex flex-col items-center justify-center text-gray-400">
                                        <n-empty description="Backlog vacío" size="small" />
                                    </div>
                                </template>
                            </draggable>
                            
                            <draggable 
                                v-model="noDateBacklog" 
                                group="tasks" 
                                item-key="id"
                                class="space-y-2 lg:space-y-3 mt-2 lg:mt-3 pb-8"
                                ghost-class="ghost-card"
                                :disabled="!hasPermission('pms.schedule')"
                                @change="onBacklogChange" 
                            >
                                <template #item="{ element }">
                                    <TaskCard 
                                        v-show="!kanbanPriorityFilter || element.priority === kanbanPriorityFilter"
                                        :task="element" 
                                        :is-backlog="true" 
                                        @click="openDetail(element)" 
                                    />
                                </template>
                            </draggable>
                        </div>
                    </div>

                    <!-- MAIN KANBAN BOARD -->
                    <div class="flex-1 overflow-x-auto overflow-y-hidden bg-white rounded-2xl shadow-sm border border-gray-100 flex p-3 gap-3 snap-x snap-mandatory scroll-smooth custom-scrollbar min-h-0 h-full" style="scroll-padding: 0.75rem;">
                        <div 
                            v-for="day in days" :key="day.date" 
                            class="snap-start shrink-0 w-[85vw] sm:w-[280px] md:w-[300px] xl:w-auto xl:flex-1 flex flex-col bg-gray-50/50 rounded-xl border border-gray-100 h-full max-h-full"
                        >
                            <!-- El header del día siempre visible en el top -->
                            <div class="py-2 border-b border-gray-100 text-center flex flex-col items-center bg-white rounded-t-xl sticky top-0 z-10 flex-shrink-0">
                                <span class="text-[10px] uppercase font-bold text-gray-400 tracking-wider truncate w-full px-1">{{ day.day_name }}</span>
                                <span class="text-xl font-black text-gray-700 leading-none mt-1">{{ day.day_number }}</span>
                            </div>

                            <div class="flex-1 p-2 overflow-y-auto overflow-x-hidden custom-scrollbar min-h-0">
                                <draggable 
                                    v-model="boardColumns[day.date]" 
                                    group="tasks" 
                                    item-key="id"
                                    class="min-h-full h-full space-y-2 pb-10"
                                    ghost-class="ghost-card"
                                    :disabled="!hasPermission('pms.schedule')"
                                    @change="(evt) => onBoardChange(evt, day.date)"
                                >
                                    <template #item="{ element }">
                                        <TaskCard 
                                            v-show="(showCompletedTasks || (element.status !== 'Completado' && element.status !== 'Cancelado')) && (!kanbanPriorityFilter || element.priority === kanbanPriorityFilter)"
                                            :task="element" 
                                            @click="openDetail(element)" 
                                        />
                                    </template>
                                </draggable>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- VISTA LISTA -->
                <div v-else class="bg-white rounded-2xl shadow-sm border border-gray-100 flex flex-col flex-1 min-h-0">
                    <div class="p-3 lg:p-4 border-b border-gray-100 flex flex-col sm:flex-row justify-between items-center gap-3 bg-gray-50/50 flex-shrink-0">
                        <h3 class="font-bold text-gray-700 flex items-center gap-2 text-sm lg:text-base">
                            <n-icon><ListOutline/></n-icon> Todas las Tareas Registradas
                        </h3>
                        <n-input v-model:value="listSearch" placeholder="Buscar tarea rápida..." clearable round class="w-full sm:w-80 shadow-sm" size="small">
                            <template #prefix><n-icon><SearchOutline/></n-icon></template>
                        </n-input>
                    </div>

                    <div class="flex-1 p-2 sm:p-4 overflow-x-auto overflow-y-hidden custom-scrollbar">
                        <n-data-table
                            flex-height
                            :scroll-x="1300" 
                            :columns="listColumns"
                            :data="filteredAllTasks"
                            :pagination="paginationReactive"
                            :bordered="false"
                            size="small"
                            :row-props="rowProps"
                            class="custom-table h-full min-w-full"
                        />
                    </div>
                </div>
            </div>
        </div>

        <!-- === DRAWER DE MÉTRICAS === -->
        <n-drawer v-model:show="showMetricsDrawer" :width="400" placement="right">
            <n-drawer-content title="Métricas Operativas" closable>
                
                <!-- Resumen Global -->
                <div class="grid grid-cols-2 gap-3 mb-6">
                    <div class="bg-blue-50/50 rounded-xl p-4 border border-blue-100 text-center shadow-sm">
                        <div class="text-[10px] text-blue-600 font-bold uppercase tracking-wider mb-1">Pendientes Totales</div>
                        <div class="text-4xl font-black text-blue-700">{{ metricsData.totalPending }}</div>
                    </div>
                    <div class="bg-indigo-50/50 rounded-xl p-4 border border-indigo-100 text-center shadow-sm">
                        <div class="text-[10px] text-indigo-600 font-bold uppercase tracking-wider mb-1">En Proceso</div>
                        <div class="text-4xl font-black text-indigo-700">{{ metricsData.totalInProcess }}</div>
                    </div>
                </div>

                <div class="mb-4 flex justify-between items-center px-1">
                    <span class="text-sm font-bold text-gray-700">Carga por Usuario</span>
                    <span class="text-[10px] text-gray-400 font-mono">Tareas Asignadas</span>
                </div>

                <!-- Lista de Usuarios -->
                <n-list hoverable class="bg-white border border-gray-100 rounded-xl shadow-sm overflow-hidden mb-4">
                    <n-list-item v-for="user in metricsData.users" :key="user.name" class="px-4 py-3">
                        <div class="flex items-center gap-3 w-full">
                            <n-avatar round :src="user.avatar" size="medium" class="ring-2 ring-gray-50 flex-shrink-0" />
                            <div class="flex-1 min-w-0">
                                <div class="flex justify-between items-center">
                                    <div class="font-bold text-sm text-gray-800 truncate">{{ user.name }}</div>
                                    <div class="text-[10px] font-bold text-gray-500 bg-gray-100 px-2 py-0.5 rounded-full border border-gray-200">
                                        Total: {{ user.totalActive }}
                                    </div>
                                </div>
                                
                                <div class="mt-1.5 space-y-1">
                                    <!-- Barra Pendientes -->
                                    <div class="flex items-center gap-2">
                                        <span class="text-[9px] font-bold text-gray-400 w-14 text-right">PENDIENTES</span>
                                        <n-progress 
                                            type="line" 
                                            :percentage="metricsData.totalPending > 0 ? (user.pendingCount / metricsData.totalPending) * 100 : 0" 
                                            :show-indicator="false"
                                            :height="5"
                                            :color="user.pendingCount > 10 ? '#ef4444' : (user.pendingCount > 5 ? '#f59e0b' : '#3b82f6')"
                                            class="flex-1"
                                        />
                                        <span class="text-[10px] font-black text-gray-600 w-4">{{ user.pendingCount }}</span>
                                    </div>

                                    <!-- Barra En Proceso -->
                                    <div class="flex items-center gap-2">
                                        <span class="text-[9px] font-bold text-indigo-400 w-14 text-right">EN PROCESO</span>
                                        <n-progress 
                                            type="line" 
                                            :percentage="metricsData.totalInProcess > 0 ? (user.inProcessCount / metricsData.totalInProcess) * 100 : 0" 
                                            :show-indicator="false"
                                            :height="5"
                                            color="#6366f1"
                                            class="flex-1"
                                        />
                                        <span class="text-[10px] font-black text-indigo-600 w-4">{{ user.inProcessCount }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </n-list-item>
                </n-list>
                
                <!-- Backlog Dividido -->
                <div class="mb-2 px-1">
                    <span class="text-xs font-bold text-gray-700">Backlog de Tareas</span>
                </div>
                <n-list class="bg-white border border-gray-100 rounded-xl shadow-sm overflow-hidden">
                    <!-- Sin Asignar -->
                    <n-list-item class="px-4 py-3 bg-orange-50/30">
                        <div class="flex items-center gap-3 w-full">
                            <n-avatar round class="bg-orange-100 text-orange-600 flex-shrink-0" size="medium">?</n-avatar>
                            <div class="flex-1 min-w-0">
                                <div class="font-bold text-sm text-orange-800 truncate">Por Asignar</div>
                                <div class="text-[10px] text-orange-600/70 font-medium mt-0.5">Pendientes de un responsable</div>
                            </div>
                            <div class="flex flex-col items-end flex-shrink-0 ml-2">
                                <span class="font-black text-xl text-orange-600 leading-none">{{ metricsData.unassignedCount }}</span>
                            </div>
                        </div>
                    </n-list-item>

                    <!-- Sin Fecha -->
                    <n-list-item class="px-4 py-3 bg-purple-50/30 border-t border-purple-100/50">
                        <div class="flex items-center gap-3 w-full">
                            <n-avatar round class="bg-purple-100 text-purple-600 flex-shrink-0" size="medium">
                                <n-icon><CalendarOutline/></n-icon>
                            </n-avatar>
                            <div class="flex-1 min-w-0">
                                <div class="font-bold text-sm text-purple-800 truncate">Sin Fecha</div>
                                <div class="text-[10px] text-purple-600/70 font-medium mt-0.5">Asignadas pero no programadas</div>
                            </div>
                            <div class="flex flex-col items-end flex-shrink-0 ml-2">
                                <span class="font-black text-xl text-purple-600 leading-none">{{ metricsData.noDateCount }}</span>
                            </div>
                        </div>
                    </n-list-item>
                </n-list>
            </n-drawer-content>
        </n-drawer>

        <!-- MODAL CREAR/EDITAR TAREA -->
        <n-modal v-model:show="formModalOpen" :mask-closable="false">
            <n-card style="width: 600px" :title="taskToEdit ? 'Editar Tarea' : 'Nueva Tarea'" :bordered="false" size="huge" closable @close="formModalOpen = false">
                <TaskForm 
                    :task="taskToEdit" 
                    :assignable-users="assignable_users" 
                    :service-orders="service_orders"
                    :tickets="tickets"
                    @close="formModalOpen = false"
                    @saved="onFormSaved"
                />
            </n-card>
        </n-modal>

        <!-- MODAL DE ASIGNACIÓN AL ARRASTRAR -->
        <n-modal v-model:show="assignModalOpen" :mask-closable="false">
            <n-card style="width: 400px" title="Asignar Responsable" :bordered="false" size="huge">
                <p class="text-sm text-gray-600 mb-4">
                    Has programado la tarea <strong>"{{ taskToAssign?.title }}"</strong> para el <strong>{{ targetDateForAssign ? format(parseISO(targetDateForAssign), 'dd/MM/yyyy') : '' }}</strong>. <br>Por favor asigna al menos un responsable para continuar.
                </p>
                <n-form :model="assignForm">
                    <n-form-item label="Personal Asignado" required>
                        <n-select v-model:value="assignForm.user_ids" multiple :options="userOptions" placeholder="Selecciona..." filterable />
                    </n-form-item>
                </n-form>
                <template #footer>
                    <div class="flex justify-end gap-2">
                        <n-button @click="cancelAssignment">Cancelar</n-button>
                        <n-button type="primary" @click="submitAssignment" :disabled="assignForm.user_ids.length === 0" :loading="assignForm.processing">Asignar y Mover</n-button>
                    </div>
                </template>
            </n-card>
        </n-modal>

        <!-- DETALLE DE TAREA -->
        <TaskDetailModal 
            v-model:show="detailModalOpen" 
            :task="selectedTask"
            @edit="openEditForm"
        />

    </AppLayout>
</template>

<style scoped>
:deep(.ghost-card) {
    opacity: 0.5 !important;
    border: 2px dashed #818cf8 !important;
    background-color: #eef2ff !important;
}

.custom-scrollbar::-webkit-scrollbar {
    height: 6px;
    width: 6px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background-color: #cbd5e1; 
    border-radius: 4px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background-color: #94a3b8; 
}

/* Estilos para la tabla Naive UI */
:deep(.custom-table .n-data-table-th) {
    background-color: transparent;
    font-weight: 700;
    color: #6b7280;
    border-bottom: 1px solid #f3f4f6;
    font-size: 0.75rem;
}
:deep(.custom-table .n-data-table-td) {
    background-color: transparent;
    border-bottom: 1px solid #f9fafb;
    padding-top: 10px;
    padding-bottom: 10px;
}
</style>