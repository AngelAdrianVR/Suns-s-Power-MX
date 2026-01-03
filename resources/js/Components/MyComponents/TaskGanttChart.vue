<script setup>
import { computed, ref, watch } from 'vue';
import { useForm, router, usePage } from '@inertiajs/vue3';
import { 
    NAvatar, NTooltip, NTag, NEmpty, NButton, NIcon, NModal, NCard, NForm, 
    NFormItem, NInput, NDatePicker, NSelect, createDiscreteApi, NPopselect, NPopconfirm, NThing, NBadge,
    NScrollbar, NDropdown 
} from 'naive-ui';
import { 
    format, parseISO, differenceInHours, addDays, startOfDay, endOfDay 
} from 'date-fns';
import { es } from 'date-fns/locale';
import { 
    AddOutline, SaveOutline, CloseOutline, LogoWhatsapp, 
    PencilOutline, TrashOutline, ChatbubbleOutline, FlagOutline, SendOutline
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
    },
    secureUrl: { // NUEVA PROP: URL FIRMADA
        type: String,
        default: ''
    }
});

const { notification } = createDiscreteApi(['notification']);
const showCreateModal = ref(false);
const showDetailModal = ref(false);
const selectedTask = ref(null);
const isEditing = ref(false); 

// --- REACTIVIDAD DE COMENTARIOS ---
watch(() => props.tasks, (newTasks) => {
    if (selectedTask.value) {
        const updatedTask = newTasks.find(t => t.id === selectedTask.value.id);
        if (updatedTask) {
            selectedTask.value = updatedTask;
        }
    }
}, { deep: true });

// Formulario de Tarea
const form = useForm({
    id: null, 
    service_order_id: props.orderId,
    title: '',
    description: '',
    start_date: null, 
    due_date: null, 
    finish_date: null, 
    priority: 'Media',
    user_ids: []
});

// Formulario de Comentario
const commentForm = useForm({
    body: '',
    commentable_type: 'task',
    commentable_id: null
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

// --- Lógica CRUD Tareas ---

const openCreate = () => {
    isEditing.value = false;
    form.reset();
    form.service_order_id = props.orderId;
    form.user_ids = [];
    form.start_date = null; 
    showCreateModal.value = true;
};

const openEdit = (task) => {
    isEditing.value = true;
    form.id = task.id;
    form.title = task.name;
    form.description = task.description;
    form.priority = task.priority;
    form.start_date = task.start;
    form.due_date = task.end; 
    form.finish_date = task.finish_date;
    form.user_ids = task.assignees.map(u => u.id);
    showCreateModal.value = true;
};

const submitTask = () => {
    if (isEditing.value) {
        form.put(route('tasks.update', form.id), {
            onSuccess: () => {
                notification.success({ title: 'Actualizado', content: 'Tarea modificada.', duration: 3000 });
                showCreateModal.value = false;
            }
        });
    } else {
        form.post(route('tasks.store'), {
            onSuccess: () => {
                notification.success({ title: 'Éxito', content: 'Tarea creada.', duration: 3000 });
                showCreateModal.value = false;
                form.reset();
            }
        });
    }
};

const updateTaskStatus = (task, newStatus) => {
    task.status = newStatus;
    router.put(route('tasks.update', task.id), { status: newStatus }, {
        preserveScroll: true,
        preserveState: true, 
        onSuccess: () => notification.success({ title: 'Actualizado', content: 'Estatus guardado.', duration: 3000 })
    });
};

const deleteTask = (taskId) => {
    router.delete(route('tasks.destroy', taskId), {
        onSuccess: () => notification.success({title: 'Tarea eliminada', duration: 3000}),
        preserveScroll: true
    });
};

// --- Comentarios ---
const openDetail = (task) => {
    selectedTask.value = task;
    commentForm.commentable_id = task.id;
    showDetailModal.value = true;
};

const submitComment = () => {
    if(!commentForm.body.trim()) return;
    commentForm.post(route('comments.store'), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            commentForm.reset('body');
            notification.success({ title: 'Comentario agregado', duration: 3000 });
        }
    });
};

// --- WHATSAPP LOGIC ---
const whatsappOptions = [
    { label: '🔔 Nueva Tarea + Link', key: 'new_task' },
    { label: '⏰ Recordatorio', key: 'reminder' },
    { label: '💬 Aviso Comentario', key: 'unread' }
];

const handleWhatsappSelect = (key, task) => {
    const user = task.assignees[0]; 
    if (!user || !user.phone) {
        notification.error({ title: 'Error', content: 'El usuario no tiene teléfono registrado.' });
        return;
    }

    const phone = user.phone.replace(/\D/g, ''); 
    // Usamos la secureUrl si existe, si no, el route normal por seguridad (aunque ya no debería pasar)
    const orderUrl = props.secureUrl || route('service-orders.show', props.orderId); 
    let message = '';

    switch (key) {
        case 'new_task':
            message = `Hola ${user.name}, se te ha asignado una *Nueva Tarea*: "${task.name}".\n\n📌 Orden #${props.orderId}\n🔗 Ver detalles (Enlace Seguro): ${orderUrl}`;
            break;
        case 'reminder':
            message = `Hola ${user.name}, paso a recordarte sobre la tarea pendiente: "${task.name}" de la Orden #${props.orderId}.\n\nPor favor actualiza el estatus cuando puedas.\n${orderUrl}`;
            break;
        case 'unread':
            message = `Hola ${user.name}, tienes *comentarios no leídos* en la tarea "${task.name}" (Orden #${props.orderId}).\n\n🔗 Entra a revisarlos: ${orderUrl}`;
            break;
    }

    window.open(`https://wa.me/${phone}?text=${encodeURIComponent(message)}`, '_blank');
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
    
    if (!starts.length) {
        const now = new Date();
        return { minDate: startOfDay(now), maxDate: endOfDay(addDays(now, 3)), totalHours: 72 };
    }

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
    const endDateStr = task.finish_date || task.end; 
    
    if (!task.start || !endDateStr || !timeRange.value) {
        return { display: 'none' };
    }
    const start = parseISO(task.start);
    const end = parseISO(endDateStr);
    const left = (differenceInHours(start, timeRange.value.minDate) / timeRange.value.totalHours) * 100;
    const width = (differenceInHours(end, start) / timeRange.value.totalHours) * 100 || 1; 
    return { left: `${left}%`, width: `${width}%` };
};

const getDisplayEndDate = (task) => {
    return task.finish_date; 
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
                    <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-red-300"></span> Detenido</span>
                </div>
                <n-button type="primary" size="small" ghost @click="openCreate">
                    <template #icon><n-icon><AddOutline /></n-icon></template>
                    Nueva Tarea
                </n-button>
            </div>
        </div>

        <div v-if="!tasks.length" class="p-8 flex flex-col items-center justify-center">
            <n-empty description="No hay tareas asignadas a esta orden aún." class="mb-4"/>
            <n-button dashed type="primary" @click="openCreate">
                Crear primera tarea
            </n-button>
        </div>

        <div v-else class="relative overflow-x-auto">
            <div class="min-w-[900px] p-4">
                
                <!-- Encabezado -->
                <div class="flex border-b border-gray-200 mb-4 pb-2">
                    <div class="w-5/12 min-w-[380px] font-semibold text-gray-500 text-xs uppercase tracking-wider pl-2 flex">
                        <span class="flex-1">Tarea / Gestión</span>
                        <span class="w-24 text-center">Inicio</span>
                        <span class="w-24 text-center">Fin</span>
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
                    <div class="absolute top-0 right-0 bottom-0 left-[41.6%] flex pointer-events-none opacity-30 z-0">
                         <div v-for="day in timelineDays" :key="'grid-'+day" class="flex-1 border-l border-dashed border-gray-300"></div>
                    </div>

                    <div v-for="task in tasks" :key="task.id" class="flex items-center relative z-10 group min-h-[50px]">
                        
                        <!-- Columna Izquierda: Detalles y Controles -->
                        <div class="w-5/12 min-w-[380px] pr-4 flex items-center border-r border-gray-100 mr-2 bg-white z-10 rounded-l-sm transition-all hover:bg-gray-50">
                            
                            <div class="h-10 w-1 rounded-full mr-2" 
                                :class="{
                                    'bg-red-500': task.priority === 'Alta',
                                    'bg-amber-500': task.priority === 'Media',
                                    'bg-blue-400': task.priority === 'Baja'
                                }">
                            </div>

                            <div class="flex-1 flex flex-col justify-center pr-2">
                                <div class="flex justify-between items-start mb-1">
                                    <span class="font-bold text-gray-700 text-sm truncate cursor-pointer hover:text-indigo-600 flex items-center gap-2" @click="openDetail(task)">
                                        {{ task.name }}
                                    </span>
                                    
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
                                        <n-tag size="tiny" :bordered="false" class="cursor-pointer font-bold hover:opacity-80" 
                                            :type="task.status === 'Completado' ? 'success' : (task.status === 'En Proceso' ? 'info' : (task.status === 'Detenido' ? 'error' : 'default'))">
                                            {{ task.status }}
                                        </n-tag>
                                    </n-popselect>
                                </div>
                                <div class="flex justify-between items-center mt-1">
                                    <div class="flex -space-x-1">
                                        <n-avatar v-for="user in task.assignees" :key="user.id" round size="tiny" :src="user.avatar" class="border border-white"/>
                                    </div>
                                    <div class="flex gap-1 opacity-100 md:opacity-0 group-hover:opacity-100 transition-opacity">
                                        <n-button size="tiny" circle tertiary @click="openDetail(task)">
                                            <template #icon>
                                                <n-badge :value="task.comments?.length || 0" :max="99" :show-zero="false">
                                                    <n-icon><ChatbubbleOutline /></n-icon>
                                                </n-badge>
                                            </template>
                                        </n-button>
                                        <!-- BOTÓN WHATSAPP DESPLEGABLE -->
                                        <n-dropdown 
                                            v-if="task.assignees.length" 
                                            trigger="click" 
                                            :options="whatsappOptions" 
                                            @select="(key) => handleWhatsappSelect(key, task)"
                                        >
                                            <n-button size="tiny" circle type="success" ghost>
                                                <template #icon><n-icon><LogoWhatsapp /></n-icon></template>
                                            </n-button>
                                        </n-dropdown>
                                        
                                        <n-button size="tiny" circle secondary type="warning" @click="openEdit(task)">
                                            <template #icon><n-icon><PencilOutline /></n-icon></template>
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
                            
                            <!-- Fechas Texto -->
                            <div class="w-24 text-center text-[10px] text-gray-500 leading-tight">
                                {{ task.start ? format(parseISO(task.start), 'dd MMM') : '-' }}
                            </div>
                            <div class="w-24 text-center text-[10px] text-gray-500 leading-tight font-medium" :class="task.finish_date ? 'text-green-600' : ''">
                                {{ getDisplayEndDate(task) ? format(parseISO(getDisplayEndDate(task)), 'dd MMM') : '-' }}
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
                                    </div>
                                </template>
                                <div class="text-xs">
                                    <div class="font-bold">{{ task.name }}</div>
                                    <div>{{ task.status }}</div>
                                    <div>Prioridad: {{ task.priority }}</div>
                                </div>
                            </n-tooltip>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- MODAL CREACIÓN / EDICIÓN -->
        <n-modal v-model:show="showCreateModal">
            <n-card style="width: 600px" :title="isEditing ? 'Editar Tarea' : 'Nueva Tarea'" :bordered="false" size="huge" role="dialog" aria-modal="true">
                <template #header-extra><n-icon size="24" class="cursor-pointer" @click="showCreateModal=false"><CloseOutline /></n-icon></template>
                <n-form :model="form" label-placement="top">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="col-span-2"><n-form-item label="Título" path="title"><n-input v-model:value="form.title" /></n-form-item></div>
                        <n-form-item label="Prioridad"><n-select v-model:value="form.priority" :options="priorityOptions" /></n-form-item>
                        <div class="col-span-2"><n-form-item label="Asignar a"><n-select v-model:value="form.user_ids" multiple :options="userOptions" filterable /></n-form-item></div>
                        
                        <n-form-item label="Fecha Inicio" v-if="isEditing">
                            <n-date-picker v-model:formatted-value="form.start_date" type="datetime" value-format="yyyy-MM-dd HH:mm:ss" class="w-full" />
                        </n-form-item>
                        
                        <n-form-item label="Fecha Estimada Fin">
                            <n-date-picker v-model:formatted-value="form.due_date" type="datetime" value-format="yyyy-MM-dd HH:mm:ss" class="w-full" />
                        </n-form-item>

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

        <!-- MODAL DETALLE -->
        <n-modal v-model:show="showDetailModal">
            <n-card style="width: 600px" :title="selectedTask?.name || 'Detalle'" :bordered="false" role="dialog" aria-modal="true" content-style="padding: 0;">
                <template #header-extra><n-icon size="24" class="cursor-pointer" @click="showDetailModal=false"><CloseOutline /></n-icon></template>
                
                <div class="flex h-[500px]" v-if="selectedTask">
                    <!-- Columna Info -->
                    <div class="w-1/2 p-6 space-y-4 border-r overflow-y-auto">
                        <div class="flex justify-between items-center">
                             <n-tag :type="selectedTask.status === 'Completado' ? 'success' : 'default'">{{ selectedTask.status }}</n-tag>
                             <div class="flex items-center gap-1 text-xs text-gray-500">
                                <div class="w-2 h-2 rounded-full" :class="{
                                    'bg-red-500': selectedTask.priority === 'Alta',
                                    'bg-amber-500': selectedTask.priority === 'Media',
                                    'bg-blue-400': selectedTask.priority === 'Baja'
                                }"></div>
                                {{ selectedTask.priority }}
                             </div>
                        </div>
                       
                        <div>
                            <div class="text-xs text-gray-500 font-bold uppercase">Descripción</div>
                            <p class="text-gray-700 text-sm mt-1 bg-gray-50 p-2 rounded-md">{{ selectedTask.description || 'Sin descripción detallada.' }}</p>
                        </div>

                        <div class="grid grid-cols-1 gap-2 bg-gray-50 p-3 rounded-lg text-xs">
                            <div class="flex justify-between">
                                <span class="text-gray-400">Inicio:</span>
                                <span class="font-medium">{{ selectedTask.start ? format(parseISO(selectedTask.start), 'dd MMM HH:mm') : 'N/A' }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-400">Fin Real:</span>
                                <span class="font-medium" :class="selectedTask.finish_date ? 'text-green-700' : ''">
                                    {{ selectedTask.finish_date ? format(parseISO(selectedTask.finish_date), 'dd MMM HH:mm') : '-' }}
                                </span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-400">Vencimiento:</span>
                                <span class="font-medium">{{ selectedTask.end ? format(parseISO(selectedTask.end), 'dd MMM HH:mm') : '-' }}</span>
                            </div>
                        </div>

                         <div>
                            <div class="text-xs text-gray-500 font-bold uppercase mb-1">Responsables</div>
                            <div class="flex flex-wrap gap-1">
                                <n-tag size="small" v-for="u in selectedTask.assignees" :key="u.id">{{ u.name }}</n-tag>
                            </div>
                        </div>
                    </div>

                    <!-- Columna Chat -->
                    <div class="w-1/2 flex flex-col bg-gray-50/50">
                        <div class="p-3 border-b bg-white text-xs font-bold text-gray-500 flex justify-between items-center">
                            <span>COMENTARIOS</span>
                            <n-badge :value="selectedTask.comments?.length || 0" type="info" />
                        </div>
                        
                        <div class="flex-1 p-3 overflow-y-auto space-y-3">
                             <div v-if="!selectedTask.comments?.length" class="text-center text-gray-400 text-xs py-8">
                                No hay comentarios aún.
                             </div>
                             <div v-for="comment in selectedTask.comments" :key="comment.id" class="flex gap-2 text-xs">
                                <n-avatar :src="comment.user_avatar" round size="small" />
                                <div class="bg-white p-2 rounded-lg shadow-sm border border-gray-100 flex-1">
                                    <div class="flex justify-between items-center mb-1">
                                        <span class="font-bold text-gray-700">{{ comment.user }}</span>
                                        <span class="text-[10px] text-gray-400">{{ comment.created_at }}</span>
                                    </div>
                                    <p class="text-gray-600">{{ comment.body }}</p>
                                </div>
                             </div>
                        </div>

                        <div class="p-3 bg-white border-t">
                             <n-input 
                                v-model:value="commentForm.body" 
                                type="textarea" 
                                placeholder="Escribe un comentario..." 
                                :autosize="{ minRows: 2, maxRows: 4 }"
                                class="text-xs"
                             />
                             <div class="flex justify-end mt-2">
                                 <n-button size="small" type="primary" :disabled="!commentForm.body" :loading="commentForm.processing" @click="submitComment">
                                    <template #icon><n-icon><SendOutline /></n-icon></template>
                                    Enviar
                                 </n-button>
                             </div>
                        </div>
                    </div>
                </div>
            </n-card>
        </n-modal>
    </div>
</template>