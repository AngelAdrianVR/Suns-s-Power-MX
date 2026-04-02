<script setup>
import { ref, watch, computed } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import draggable from 'vuedraggable';
import TaskCard from './Components/TaskCard.vue';
import TaskForm from './Components/TaskForm.vue';
import TaskDetailModal from './Components/TaskDetailModal.vue';
import { 
    NButton, NIcon, NCard, NTag, NAvatar, NBadge, 
    NModal, NForm, NFormItem, NSelect, NDatePicker, createDiscreteApi, NEmpty
} from 'naive-ui';
import { 
    ArrowBackOutline, ArrowForwardOutline, AddOutline
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
    assignable_users: Array,
    service_orders: Array,
    tickets: Array,
});

const { hasPermission } = usePermissions();
const { notification } = createDiscreteApi(['notification']);

const boardColumns = ref({});
const backlogTasks = ref([]);

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
    backlogTasks.value = [...newVal];
    if (selectedTask.value) {
        const found = newVal.find(t => t.id === selectedTask.value.id);
        if (found) selectedTask.value = found;
    }
}, { immediate: true, deep: true });

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

// --- NUEVO LÓGICA: Arrastrar tarea de vuelta a "Por Asignar" (Backlog) ---
const onBacklogChange = (evt) => {
    if (evt.added) {
        const task = evt.added.element;

        // Limpiar los usuarios, la fecha de inicio y regresar estado a Pendiente
        router.put(route('tasks.update', task.id), {
            user_ids: [],         // Envía array vacío para desasignar personas
            start_date: null,     // Quitar fecha del calendario
            status: task.status === 'Pendiente' ? undefined : 'Pendiente' // Reiniciar estado
        }, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                notification.success({ title: 'Desasignada', content: 'La tarea ha regresado a la lista por asignar.', duration: 3000 });
            },
            onError: () => {
                router.reload({ preserveScroll: true }); // Si falla la petición, revertimos la tarjeta visualmente
            }
        });
    }
};

// --- ABRIR DETALLE ---
const openDetail = (task) => {
    selectedTask.value = task;
    detailModalOpen.value = true;
};

</script>

<template>
    <AppLayout title="PMS - Dashboard Semanal">
        <template #header>
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <div>
                    <h2 class="font-bold text-xl text-gray-800 leading-tight flex items-center gap-2">
                        Dashboard PMS
                    </h2>
                    <p class="text-sm text-gray-500 mt-1">Gestión de Tareas Operativas</p>
                </div>

                <div class="md:flex items-center gap-4">
                    <!-- Controles de semana -->
                    <div class="flex items-center gap-1 bg-white p-1 rounded-lg shadow-sm border border-gray-100">
                        <n-button circle quaternary @click="changeWeek(prev_week)">
                            <template #icon><n-icon><ArrowBackOutline /></n-icon></template>
                        </n-button>
                        
                        <n-date-picker 
                            v-model:value="datePickerValue" 
                            type="date" 
                            size="small"
                            format="dd MMM yyyy"
                            :clearable="false"
                            @update:value="onDatePickerChange"
                            class="w-36 font-semibold"
                        />

                        <n-button circle quaternary @click="changeWeek(next_week)">
                            <template #icon><n-icon><ArrowForwardOutline /></n-icon></template>
                        </n-button>
                    </div>

                    <!-- Botón Nueva Tarea (Protegido por Permiso) -->
                    <n-button class="mt-3 md:mt-0" v-if="hasPermission('pms.create')" type="primary" @click="openCreateForm">
                        <template #icon>
                            <n-icon><AddOutline /></n-icon>
                        </template>
                        Nueva Tarea
                    </n-button>
                </div>
            </div>
        </template>

        <div class="py-6 min-h-[calc(100vh-100px)] bg-gray-50/50">
            <div class="w-full px-2 sm:px-4 lg:px-6">
                
                <div class="flex flex-col lg:flex-row gap-4 h-[calc(100vh-180px)] min-h-[600px]">
                    
                    <!-- SIDEBAR: BACKLOG (Protegido para quienes pueden ver todo) -->
                    <div v-if="hasPermission('pms.view_all')" class="w-full lg:w-72 flex flex-col bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden flex-shrink-0">
                        <div class="p-4 border-b border-gray-100 bg-gray-50">
                            <h3 class="font-bold text-gray-700 flex items-center justify-between">
                                Por Asignar/Sin fecha
                                <n-badge :value="backlogTasks.length" type="warning" />
                            </h3>
                            <p class="text-xs text-gray-500 mt-1">Arrastra hacia un día para asignar.</p>
                        </div>
                        
                        <div class="flex-1 overflow-y-auto p-3 bg-gray-50/30">
                            <draggable 
                                v-model="backlogTasks" 
                                group="tasks" 
                                item-key="id"
                                class="min-h-full space-y-3"
                                ghost-class="ghost-card"
                                :disabled="!hasPermission('pms.schedule')"
                                @change="onBacklogChange" 
                            >
                                <template #item="{ element }">
                                    <TaskCard :task="element" :is-backlog="true" @click="openDetail(element)" />
                                </template>
                                <template #empty>
                                    <div v-if="!backlogTasks.length" class="h-32 flex flex-col items-center justify-center text-gray-400">
                                        <n-empty description="No hay tareas pendientes" size="small" />
                                    </div>
                                </template>
                            </draggable>
                        </div>
                    </div>

                    <!-- MAIN KANBAN BOARD CON SCROLL HORIZONTAL -->
                    <div class="flex-1 overflow-x-auto overflow-y-hidden bg-white rounded-2xl shadow-sm border border-gray-100 flex p-3 gap-3 snap-x snap-mandatory scroll-smooth" style="scroll-padding: 0.75rem;">
                        <div 
                            v-for="day in days" :key="day.date" 
                            class="snap-start shrink-0 w-[260px] md:w-[300px] xl:w-auto xl:flex-1 flex flex-col bg-gray-50/50 rounded-xl border border-gray-100"
                        >
                            <div class="py-2 border-b border-gray-100 text-center flex flex-col items-center bg-white rounded-t-xl sticky top-0 z-10">
                                <span class="text-[10px] uppercase font-bold text-gray-400 tracking-wider truncate w-full px-1">{{ day.day_name }}</span>
                                <span class="text-xl font-black text-gray-700 leading-none mt-1">{{ day.day_number }}</span>
                            </div>

                            <div class="flex-1 p-2 overflow-y-auto overflow-x-hidden">
                                <draggable 
                                    v-model="boardColumns[day.date]" 
                                    group="tasks" 
                                    item-key="id"
                                    class="min-h-[150px] h-full space-y-2"
                                    ghost-class="ghost-card"
                                    :disabled="!hasPermission('pms.schedule')"
                                    @change="(evt) => onBoardChange(evt, day.date)"
                                >
                                    <template #item="{ element }">
                                        <TaskCard :task="element" @click="openDetail(element)" />
                                    </template>
                                </draggable>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

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

        <!-- === NUEVO COMPONENTE: DETALLE DE TAREA === -->
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

::-webkit-scrollbar {
    height: 6px;
    width: 6px;
}
::-webkit-scrollbar-track {
    background: #f1f5f9; 
    border-radius: 4px;
}
::-webkit-scrollbar-thumb {
    background: #cbd5e1; 
    border-radius: 4px;
}
::-webkit-scrollbar-thumb:hover {
    background: #94a3b8; 
}
</style>