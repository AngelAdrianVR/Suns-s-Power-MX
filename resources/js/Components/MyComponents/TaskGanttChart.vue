<script setup>
import { computed, ref } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import { 
    NAvatar, NTooltip, NTag, NEmpty, NButton, NIcon, NModal, NCard, NForm, 
    NFormItem, NInput, NDatePicker, NSelect, createDiscreteApi, NPopselect, NPopconfirm, NThing
} from 'naive-ui';
import { 
    format, parseISO, differenceInHours, addDays, startOfDay, endOfDay 
} from 'date-fns';
import { es } from 'date-fns/locale';
import { 
    AddOutline, SaveOutline, CloseOutline, LogoWhatsapp, 
    PencilOutline, TrashOutline, ChatbubbleOutline 
} from '@vicons/ionicons5';

const props = defineProps({
    tasks: {
        type: Array,
        required: true
    },
    orderId: {
        type: Number,
        required: true
    },
    assignableUsers: {
        type: Array,
        default: () => [] 
    }
});

const { notification } = createDiscreteApi(['notification']);
const showCreateModal = ref(false);
const showDetailModal = ref(false);
const selectedTask = ref(null);

// Formulario de Tarea (Creación)
const form = useForm({
    service_order_id: props.orderId,
    title: '',
    description: '',
    due_date: null,
    priority: 'Media',
    user_ids: []
});

const priorityOptions = [
    { label: 'Baja', value: 'Baja' },
    { label: 'Media', value: 'Media' },
    { label: 'Alta', value: 'Alta' }
];

const userOptions = computed(() => {
    return props.assignableUsers.map(u => ({
        label: u.name || u.label,
        value: u.id || u.value
    }));
});

const submitTask = () => {
    form.service_order_id = props.orderId;
    form.post(route('tasks.store'), {
        onSuccess: () => {
            notification.success({ title: 'Éxito', content: 'Tarea creada.' });
            showCreateModal.value = false;
            form.reset();
        }
    });
};

const updateTaskStatus = (task, newStatus) => {
    // 1. Actualización optimista local para respuesta inmediata en UI
    task.status = newStatus;

    // 2. Petición al servidor
    router.put(route('tasks.update', task.id), { status: newStatus }, {
        preserveScroll: true,
        preserveState: true, // Mantiene el estado local para que no parpadee
        onSuccess: () => {
             notification.success({ title: 'Actualizado', content: 'Estatus guardado.' });
        },
        onError: () => {
            // Revertir si falla (opcional, requeriría guardar estado previo)
            notification.error({ title: 'Error', content: 'No se pudo actualizar el estatus.' });
        }
    });
};

const deleteTask = (taskId) => {
    router.delete(route('tasks.destroy', taskId), {
        onSuccess: () => notification.success({title: 'Tarea eliminada'}),
        preserveScroll: true
    });
};

const sendWhatsapp = (user, taskTitle) => {
    if (!user || !user.phone) {
        notification.error({ title: 'Error', content: 'El usuario no tiene teléfono registrado.' });
        return;
    }
    const phone = user.phone.replace(/\D/g, ''); 
    const text = encodeURIComponent(`Hola ${user.name}, respecto a la tarea "${taskTitle}" de la Orden #${props.orderId}...`);
    window.open(`https://wa.me/${phone}?text=${text}`, '_blank');
};

const openDetail = (task) => {
    selectedTask.value = task;
    showDetailModal.value = true;
};

// --- LÓGICA GANTT ---
const statusColors = {
    'Pendiente': 'bg-gray-300 border-gray-400 text-gray-700',
    'En Proceso': 'bg-blue-300 border-blue-500 text-blue-900',
    'Completado': 'bg-emerald-300 border-emerald-500 text-emerald-900',
    'Detenido': 'bg-red-300 border-red-500 text-red-900',
};

const timeRange = computed(() => {
    if (!props.tasks.length) return null;
    const starts = props.tasks.map(t => t.start ? parseISO(t.start) : null).filter(Boolean);
    const ends = props.tasks.map(t => t.end ? parseISO(t.end) : null).filter(Boolean);
    if (!starts.length) return null;

    let minDate = startOfDay(new Date(Math.min(...starts)));
    let maxDate = endOfDay(new Date(Math.max(...ends)));

    if (differenceInHours(maxDate, minDate) < 72) {
        maxDate = addDays(maxDate, 3);
    }
    return { minDate, maxDate, totalHours: differenceInHours(maxDate, minDate) };
});

const timelineDays = computed(() => {
    if (!timeRange.value) return [];
    const days = [];
    let current = timeRange.value.minDate;
    while (current <= timeRange.value.maxDate) {
        days.push(current);
        current = addDays(current, 1);
    }
    return days;
});

const getTaskStyle = (task) => {
    if (!task.start || !task.end || !timeRange.value) return {};
    const start = parseISO(task.start);
    const end = parseISO(task.end);
    const left = (differenceInHours(start, timeRange.value.minDate) / timeRange.value.totalHours) * 100;
    const width = (differenceInHours(end, start) / timeRange.value.totalHours) * 100 || 1; // min width
    return { left: `${left}%`, width: `${width}%` };
};
</script>

<template>
    <div class="w-full bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="p-4 border-b border-gray-100 flex justify-between items-center bg-gray-50/50">
            <h3 class="font-bold text-gray-700">Cronograma de Ejecución (PMS)</h3>
            <div class="flex items-center gap-4">
                <div class="hidden md:flex gap-2 text-xs">
                    <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-gray-300"></span> Pendiente</span>
                    <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-blue-300"></span> En Proceso</span>
                    <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-emerald-300"></span> Completado</span>
                </div>
                <n-button type="primary" size="small" ghost @click="showCreateModal = true">
                    <template #icon><n-icon><AddOutline /></n-icon></template>
                    Nueva Tarea
                </n-button>
            </div>
        </div>

        <div v-if="!tasks.length" class="p-8 flex flex-col items-center justify-center">
            <n-empty description="No hay tareas asignadas a esta orden aún." class="mb-4"/>
            <n-button dashed type="primary" @click="showCreateModal = true">
                Crear primera tarea
            </n-button>
        </div>

        <div v-else class="relative overflow-x-auto">
            <div class="min-w-[900px] p-4">
                
                <!-- Encabezado -->
                <div class="flex border-b border-gray-200 mb-4 pb-2">
                    <div class="w-1/3 min-w-[320px] font-semibold text-gray-500 text-xs uppercase tracking-wider pl-2">
                        Tarea / Gestión
                    </div>
                    <div class="flex-1 flex relative">
                        <div v-for="day in timelineDays" :key="day" class="flex-1 text-center text-xs text-gray-400 border-l border-gray-100 last:border-r">
                            <div class="font-bold text-gray-600">{{ format(day, 'dd') }}</div>
                            <div class="text-[10px] uppercase">{{ format(day, 'MMM', { locale: es }) }}</div>
                        </div>
                    </div>
                </div>

                <!-- Lista de Tareas -->
                <div class="space-y-6 relative">
                    <div class="absolute top-0 right-0 bottom-0 left-[33%] flex pointer-events-none opacity-30 z-0">
                         <div v-for="day in timelineDays" :key="'grid-'+day" class="flex-1 border-l border-dashed border-gray-300"></div>
                    </div>

                    <div v-for="task in tasks" :key="task.id" class="flex items-center relative z-10 group min-h-[50px]">
                        
                        <!-- Columna Izquierda: Detalles y Controles -->
                        <div class="w-1/3 min-w-[320px] pr-4 flex flex-col justify-center border-r border-gray-100 mr-2">
                            <div class="flex justify-between items-start mb-1">
                                <span class="font-bold text-gray-700 text-sm truncate cursor-pointer hover:text-indigo-600" @click="openDetail(task)">
                                    {{ task.name }}
                                </span>
                                <!-- SELECTOR DE ESTATUS -->
                                <n-popselect 
                                    :options="[
                                        {label: 'Pendiente', value: 'Pendiente'},
                                        {label: 'En Proceso', value: 'En Proceso'},
                                        {label: 'Completado', value: 'Completado'},
                                        {label: 'Detenido', value: 'Detenido'}
                                    ]"
                                    :value="task.status"
                                    @update:value="(val) => updateTaskStatus(task, val)"
                                    trigger="click"
                                >
                                    <n-tag size="tiny" :bordered="false" class="cursor-pointer font-bold" 
                                        :type="task.status === 'Completado' ? 'success' : (task.status === 'En Proceso' ? 'info' : (task.status === 'Detenido' ? 'error' : 'default'))">
                                        {{ task.status }}
                                    </n-tag>
                                </n-popselect>
                            </div>

                            <div class="flex justify-between items-center mt-1">
                                <!-- Avatares -->
                                <div class="flex -space-x-1">
                                    <n-avatar v-for="user in task.assignees" :key="user.id" round size="tiny" :src="user.avatar" class="border border-white"/>
                                </div>
                                
                                <!-- Botonera de Acciones -->
                                <div class="flex gap-1 opacity-100 md:opacity-0 group-hover:opacity-100 transition-opacity">
                                    <n-button v-if="task.assignees.length" size="tiny" circle type="success" ghost @click="sendWhatsapp(task.assignees[0], task.name)">
                                        <template #icon><n-icon><LogoWhatsapp /></n-icon></template>
                                    </n-button>
                                    <n-popconfirm @positive-click="deleteTask(task.id)">
                                        <template #trigger>
                                            <n-button size="tiny" circle type="error" quaternary>
                                                <template #icon><n-icon><TrashOutline /></n-icon></template>
                                            </n-button>
                                        </template>
                                        ¿Borrar tarea?
                                    </n-popconfirm>
                                </div>
                            </div>
                        </div>

                        <!-- Columna Derecha: Barra Gantt -->
                        <div class="flex-1 relative h-8 bg-gray-50 rounded-full overflow-hidden border border-gray-100/50" @click="openDetail(task)">
                            <n-tooltip trigger="hover" placement="top">
                                <template #trigger>
                                    <div 
                                        class="absolute top-0 bottom-0 rounded-full shadow-sm transition-all hover:brightness-95 cursor-pointer flex items-center justify-center px-2"
                                        :class="statusColors[task.status] || 'bg-gray-200'"
                                        :style="getTaskStyle(task)"
                                    >
                                        <!-- Sin porcentaje, solo barra visual -->
                                    </div>
                                </template>
                                <div class="text-xs">
                                    <div class="font-bold">{{ task.name }}</div>
                                    <div>{{ task.status }}</div>
                                    <div>{{ task.start ? format(parseISO(task.start), 'dd MMM') : '' }} - {{ task.end ? format(parseISO(task.end), 'dd MMM') : '' }}</div>
                                </div>
                            </n-tooltip>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- MODAL CREACIÓN -->
        <n-modal v-model:show="showCreateModal">
            <n-card style="width: 600px" title="Nueva Tarea" :bordered="false" size="huge" role="dialog" aria-modal="true">
                <template #header-extra><n-icon size="24" class="cursor-pointer" @click="showCreateModal=false"><CloseOutline /></n-icon></template>
                <n-form :model="form" label-placement="top">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="col-span-2"><n-form-item label="Título" path="title"><n-input v-model:value="form.title" /></n-form-item></div>
                        <n-form-item label="Prioridad"><n-select v-model:value="form.priority" :options="priorityOptions" /></n-form-item>
                        <n-form-item label="Fecha Límite"><n-date-picker v-model:formatted-value="form.due_date" type="datetime" value-format="yyyy-MM-dd HH:mm:ss" class="w-full" /></n-form-item>
                        <div class="col-span-2"><n-form-item label="Asignar a"><n-select v-model:value="form.user_ids" multiple :options="userOptions" filterable /></n-form-item></div>
                        <div class="col-span-2"><n-form-item label="Descripción"><n-input v-model:value="form.description" type="textarea" /></n-form-item></div>
                    </div>
                </n-form>
                <template #footer>
                    <div class="flex justify-end gap-3">
                        <n-button @click="showCreateModal = false">Cancelar</n-button>
                        <n-button type="primary" @click="submitTask" :loading="form.processing">Guardar</n-button>
                    </div>
                </template>
            </n-card>
        </n-modal>

        <!-- MODAL DETALLE (SOLO LECTURA + COMENTARIO) -->
        <n-modal v-model:show="showDetailModal">
            <n-card style="width: 500px" :title="selectedTask?.name || 'Detalle'" :bordered="false" role="dialog" aria-modal="true">
                <template #header-extra><n-icon size="24" class="cursor-pointer" @click="showDetailModal=false"><CloseOutline /></n-icon></template>
                
                <div class="space-y-4" v-if="selectedTask">
                    <n-tag :type="selectedTask.status === 'Completado' ? 'success' : 'default'">{{ selectedTask.status }}</n-tag>
                    
                    <div>
                        <div class="text-xs text-gray-500 font-bold uppercase">Descripción</div>
                        <p class="text-gray-700 text-sm mt-1">{{ selectedTask.description || 'Sin descripción detallada.' }}</p>
                    </div>

                    <div class="grid grid-cols-2 gap-4 bg-gray-50 p-3 rounded-lg">
                        <div>
                            <div class="text-xs text-gray-400">Inicio</div>
                            <div class="text-sm font-medium">{{ selectedTask.start ? format(parseISO(selectedTask.start), 'dd MMM HH:mm') : 'N/A' }}</div>
                        </div>
                        <div>
                            <div class="text-xs text-gray-400">Fin</div>
                            <div class="text-sm font-medium">{{ selectedTask.end ? format(parseISO(selectedTask.end), 'dd MMM HH:mm') : 'N/A' }}</div>
                        </div>
                    </div>

                    <div class="border-t pt-4 mt-4">
                         <n-button block secondary type="info">
                            <template #icon><n-icon><ChatbubbleOutline /></n-icon></template>
                            Agregar Comentario
                         </n-button>
                    </div>
                </div>
            </n-card>
        </n-modal>
    </div>
</template>