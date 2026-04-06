<script setup>
import { ref, computed } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import { 
    NButton, NCard, NIcon, NTag, NAvatar, 
    NModal, NForm, NFormItem, NInput, NInputNumber, NSelect, createDiscreteApi, NPopconfirm, NEmpty, NSwitch
} from 'naive-ui';
import { 
    AddOutline, CreateOutline, TrashOutline, CheckmarkCircleOutline, CameraOutline, InformationCircleOutline, SyncOutline, MenuOutline
} from '@vicons/ionicons5';

const props = defineProps({
    sys: Object,
    tasks: Array,
    assignableUsers: Array,
    evidenceOptions: Array
});

const emit = defineEmits(['sync']);
const { notification } = createDiscreteApi(['notification']);

const showTaskModal = ref(false);
const isEditingTask = ref(false);

const taskForm = useForm({
    id: null,
    system_type: props.sys.name,
    title: '',
    description: '',
    priority: 'Media',
    start_days: 0,
    duration_days: 1,
    is_recurring: false,
    recurring_interval: 1,
    recurring_unit: 'months',
    recurring_count: 1,
    users: [],
    evidences: [] 
});

const priorityOptions = [
    { label: 'Baja', value: 'Baja' },
    { label: 'Media', value: 'Media' },
    { label: 'Alta', value: 'Alta' }
];

const recurringUnitOptions = [
    { label: 'Día(s)', value: 'days' },
    { label: 'Semana(s)', value: 'weeks' },
    { label: 'Mes(es)', value: 'months' },
    { label: 'Año(s)', value: 'years' }
];

const usersOptions = computed(() => props.assignableUsers.map(u => ({ label: u.name, value: u.id })));

const getRecurringText = (interval, unit) => {
    const units = {
        days: interval === 1 ? 'día' : 'días',
        weeks: interval === 1 ? 'semana' : 'semanas',
        months: interval === 1 ? 'mes' : 'meses',
        years: interval === 1 ? 'año' : 'años'
    };
    return `Se repite cada ${interval} ${units[unit]}`;
};

const getPriorityColor = (priority) => {
    const map = { 'Baja': 'success', 'Media': 'warning', 'Alta': 'error' };
    return map[priority] || 'default';
};

const openAddTaskModal = () => {
    isEditingTask.value = false;
    taskForm.reset();
    taskForm.system_type = props.sys.name;
    taskForm.start_days = 0; 
    taskForm.duration_days = 1;
    taskForm.is_recurring = false;
    taskForm.recurring_interval = 1;
    taskForm.recurring_unit = 'months';
    taskForm.recurring_count = 1;
    taskForm.evidences = [];
    showTaskModal.value = true;
};

const openEditTaskModal = (template) => {
    isEditingTask.value = true;
    taskForm.id = template.id;
    taskForm.system_type = template.system_type;
    taskForm.title = template.title;
    taskForm.description = template.description || '';
    taskForm.priority = template.priority;
    taskForm.start_days = template.start_days ?? 0;
    taskForm.duration_days = template.duration_days ?? 1;
    taskForm.is_recurring = Boolean(template.is_recurring);
    taskForm.recurring_interval = template.recurring_interval ?? 1;
    taskForm.recurring_unit = template.recurring_unit || 'months';
    taskForm.recurring_count = template.recurring_count ?? 1;
    taskForm.users = template.users?.map(u => u.id) || [];
    taskForm.evidences = template.evidence_templates?.map(e => e.id) || []; 
    showTaskModal.value = true;
};

const handleTaskSubmit = () => {
    if (isEditingTask.value) {
        taskForm.put(route('task-templates.update', taskForm.id), {
            onSuccess: () => { 
                showTaskModal.value = false; 
                notification.success({ title: 'Actualizado', content: 'Plantilla de tarea actualizada.', duration: 3000}); 
                emit('sync'); 
            }
        });
    } else {
        taskForm.post(route('task-templates.store'), {
            onSuccess: () => { 
                showTaskModal.value = false; 
                notification.success({ title: 'Creado', content: 'Plantilla de tarea guardada.', duration: 3000 }); 
                emit('sync'); 
            }
        });
    }
};

const handleDeleteTask = (id) => {
    router.delete(route('task-templates.destroy', id), {
        preserveScroll: true,
        onSuccess: () => {
            notification.success({ title: 'Eliminado', content: 'Plantilla de tarea eliminada.', duration: 3000 });
            emit('sync');
        }
    });
};

// ================= DRAG & DROP TAREAS =================
const draggedTaskIndex = ref(null);
const onDragStartTask = (index) => { draggedTaskIndex.value = index; };
const onDropTask = (dropIndex) => {
    if (draggedTaskIndex.value === null || draggedTaskIndex.value === dropIndex) return;
    
    let currentTasks = [...props.tasks];
    const draggedItem = currentTasks.splice(draggedTaskIndex.value, 1)[0];
    currentTasks.splice(dropIndex, 0, draggedItem);
    
    const updatedItems = currentTasks.map((item, i) => ({ id: item.id, order: i + 1 }));

    router.post(route('task-templates.reorder'), { items: updatedItems }, {
        preserveScroll: true,
        onSuccess: () => { 
            notification.success({ title: 'Orden actualizado', content: 'Se guardó el nuevo orden de las tareas.', duration: 3000 }); 
            emit('sync');
        }
    });

    draggedTaskIndex.value = null;
};
</script>

<template>
    <div>
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-bold text-gray-700 flex items-center gap-2">
                <n-icon class="text-indigo-500"><CheckmarkCircleOutline/></n-icon> Tareas del Sistema
            </h3>
            <n-button type="primary" size="small" class="bg-indigo-600" @click="openAddTaskModal">
                <template #icon><n-icon><AddOutline /></n-icon></template> Agregar
            </n-button>
        </div>

        <div v-if="tasks.length > 0" class="space-y-3">
            <div v-for="(item, index) in tasks" :key="item.id" draggable="true" @dragstart="onDragStartTask(index)" @dragover.prevent @drop="onDropTask(index)">
                <n-card size="small" class="rounded-xl shadow-sm border border-gray-100 hover:shadow-md transition-shadow cursor-move bg-indigo-50/10">
                    <div class="flex justify-between items-start gap-3">
                        <div class="text-gray-400 flex items-center mt-1">
                            <n-icon size="20"><MenuOutline /></n-icon>
                        </div>
                        
                        <div class="flex-1">
                            <div class="flex items-center gap-2 mb-1">
                                <h4 class="font-semibold text-gray-800 text-sm">{{ item.title }}</h4>
                                <n-tag :type="getPriorityColor(item.priority)" size="tiny" round>{{ item.priority }}</n-tag>
                            </div>
                            <p class="text-xs text-gray-600" v-if="item.description">{{ item.description }}</p>
                            
                            <div class="mt-2 text-[11px] text-indigo-500 font-medium">
                                ⏱️ Inicia en {{ item.start_days }} días - Dura {{ item.duration_days }} días
                            </div>

                            <div v-if="item.is_recurring" class="mt-1 text-[11px] text-blue-500 font-medium flex items-center gap-1">
                                <n-icon><SyncOutline/></n-icon> 
                                {{ getRecurringText(item.recurring_interval, item.recurring_unit) }}
                                <span class="ml-1 text-purple-500 font-bold">({{ item.recurring_count || 1 }} veces)</span>
                            </div>

                            <div v-if="item.evidence_templates?.length > 0" class="mt-2 text-[11px] text-gray-500 flex flex-wrap gap-1 items-center">
                                <n-icon class="text-emerald-500"><CameraOutline/></n-icon> Evidencias requeridas:
                                <n-tag v-for="ev in item.evidence_templates" :key="ev.id" size="tiny" type="info" round>{{ ev.title }}</n-tag>
                            </div>

                            <div class="mt-2 flex flex-wrap gap-1">
                                <template v-if="item.users?.length">
                                    <n-avatar v-for="u in item.users" :key="u.id" round size="small" :src="u.profile_photo_url" :fallback-src="'https://ui-avatars.com/api/?name='+u.name"/>
                                </template>
                                <span v-else class="text-[10px] italic text-gray-400">Sin asignar</span>
                            </div>
                        </div>

                        <div class="flex gap-1">
                            <n-button circle quaternary size="small" type="info" @click="openEditTaskModal(item)">
                                <template #icon><n-icon><CreateOutline /></n-icon></template>
                            </n-button>
                            <n-popconfirm @positive-click="handleDeleteTask(item.id)" positive-text="Sí" negative-text="No">
                                <template #trigger>
                                    <n-button circle quaternary size="small" type="error">
                                        <template #icon><n-icon><TrashOutline /></n-icon></template>
                                    </n-button>
                                </template>
                                ¿Eliminar?
                            </n-popconfirm>
                        </div>
                    </div>
                </n-card>
            </div>
        </div>
        <n-empty v-else description="Sin tareas automáticas." class="py-8" />

        <!-- MODAL TAREAS -->
        <n-modal v-model:show="showTaskModal" preset="card" class="max-w-lg" :title="isEditingTask ? 'Editar Tarea' : 'Nueva Tarea'">
            <n-form :model="taskForm" @submit.prevent="handleTaskSubmit">
                <n-form-item label="Título de la Tarea" path="title"><n-input v-model:value="taskForm.title" /></n-form-item>
                <n-form-item label="Descripción" path="description"><n-input type="textarea" v-model:value="taskForm.description" /></n-form-item>
                
                <div class="grid grid-cols-2 gap-4">
                    <n-form-item label="Prioridad" path="priority"><n-select v-model:value="taskForm.priority" :options="priorityOptions" /></n-form-item>
                    <n-form-item label="Tipo de Sistema"><n-input :value="taskForm.system_type" disabled /></n-form-item>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <n-form-item label="Días para iniciar" path="start_days">
                        <n-input-number v-model:value="taskForm.start_days" :min="0" class="w-full" placeholder="0 para hoy" />
                    </n-form-item>
                    <n-form-item label="Duración (Días)" path="duration_days">
                        <n-input-number v-model:value="taskForm.duration_days" :min="1" class="w-full" placeholder="Ej. 1" />
                    </n-form-item>
                </div>

                <div class="mb-4">
                    <div class="flex items-center gap-2 mb-2">
                        <n-switch v-model:value="taskForm.is_recurring" />
                        <span class="font-medium text-gray-700">Tarea Cíclica / Recurrente</span>
                    </div>
                    
                    <div v-if="taskForm.is_recurring" class="flex flex-wrap items-center gap-3 p-4 bg-gray-50 rounded-xl border border-gray-100 transition-all">
                        <span class="text-sm text-gray-600 font-medium">Repetir cada:</span>
                        <n-input-number v-model:value="taskForm.recurring_interval" :min="1" class="w-20" />
                        <n-select v-model:value="taskForm.recurring_unit" :options="recurringUnitOptions" class="w-32" />
                        
                        <span class="text-sm text-gray-600 font-medium ml-2 border-l border-gray-300 pl-4 w-full sm:w-auto mt-2 sm:mt-0">¿Cuántas veces?:</span>
                        <n-input-number v-model:value="taskForm.recurring_count" :min="1" class="w-24 mt-2 sm:mt-0" />
                    </div>
                </div>

                <n-form-item path="evidences">
                    <template #label>Evidencias Obligatorias para Cerrar Tarea <span class="text-xs text-gray-400 ml-1">(Opcional)</span></template>
                    <n-select v-model:value="taskForm.evidences" multiple :options="evidenceOptions" clearable placeholder="Selecciona evidencias" />
                </n-form-item>

                <n-form-item path="users">
                    <template #label>Asignar Usuarios Automáticamente</template>
                    <n-select v-model:value="taskForm.users" multiple :options="usersOptions" clearable />
                    <template #feedback>
                        <span class="text-amber-600 text-[11px] flex items-center gap-1 mt-1">
                            <n-icon size="14"><InformationCircleOutline /></n-icon> Si no asignas a una persona, la tarea quedará en "Sin asignar".
                        </span>
                    </template>
                </n-form-item>
                
                <div class="flex justify-end gap-3 mt-4">
                    <n-button @click="showTaskModal = false">Cancelar</n-button>
                    <n-button type="primary" attr-type="submit" :loading="taskForm.processing" class="bg-indigo-600">Guardar</n-button>
                </div>
            </n-form>
        </n-modal>
    </div>
</template>