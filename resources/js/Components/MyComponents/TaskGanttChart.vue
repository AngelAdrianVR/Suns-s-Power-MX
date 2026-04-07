<script setup>
import { computed, ref, watch } from 'vue';
import { useForm, router, usePage } from '@inertiajs/vue3';
import { usePermissions } from '@/Composables/usePermissions';
import { 
    NAvatar, NTooltip, NTag, NEmpty, NButton, NIcon, NModal, NCard, NForm, 
    NFormItem, NInput, NDatePicker, NSelect, createDiscreteApi, NPopselect, NPopconfirm, NBadge,
    NDropdown, NDivider
} from 'naive-ui';
import { 
    format, parseISO, differenceInHours, addDays, startOfDay, endOfDay 
} from 'date-fns';
import { es } from 'date-fns/locale';
import { 
    AddOutline, SaveOutline, CloseOutline, LogoWhatsapp, 
    PencilOutline, TrashOutline, ChatbubbleOutline, FlagOutline, SendOutline,
    CalendarOutline, TimeOutline, PersonOutline,
    ChevronDownOutline, ConstructOutline, LocationOutline, MapOutline,
    HardwareChipOutline, SpeedometerOutline, CameraOutline, CheckmarkCircleOutline, SyncOutline
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
    secureUrl: { 
        type: String,
        default: ''
    }
});

const { notification, dialog } = createDiscreteApi(['notification', 'dialog']);
const { hasPermission } = usePermissions();

// Computeds para separar y ordenar las tareas basadas en el backend
const sortedNormalTasks = computed(() => {
    if (!props.tasks) return [];
    return props.tasks
        .filter(t => !t.is_recurring)
        .sort((a, b) => (a.order || 0) - (b.order || 0));
});

const sortedRecurringTasks = computed(() => {
    if (!props.tasks) return [];
    return props.tasks
        .filter(t => t.is_recurring)
        .sort((a, b) => (a.order || 0) - (b.order || 0));
});

const page = usePage();
const currentUserId = computed(() => page.props.auth?.user?.id);

const showCreateModal = ref(false);
const showDetailModal = ref(false);
const selectedTask = ref(null);
const isEditing = ref(false); 

const showMobileComments = ref(false);

watch(showDetailModal, (val) => {
    if (val) showMobileComments.value = false;
});

const canEditTaskStatus = (task) => {
    if (hasPermission('pms.schedule')) return true;
    if (!currentUserId.value || !task.assignees) return false;
    return task.assignees.some(u => u.id === currentUserId.value);
};

watch(() => props.tasks, (newTasks) => {
    if (selectedTask.value) {
        const updatedTask = newTasks.find(t => t.id === selectedTask.value.id);
        if (updatedTask) {
            selectedTask.value = updatedTask;
        }
    }
}, { deep: true });

const form = useForm({
    id: null, 
    taskable_id: props.orderId,
    taskable_type: 'App\\Models\\ServiceOrder',
    title: '',
    description: '',
    start_date: null, 
    due_date: null, 
    finish_date: null, 
    priority: 'Media',
    user_ids: []
});

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
    return props.assignableUsers?.map(u => ({
        label: u.name || u.label,
        value: u.id || u.value
    })) || [];
});

const openCreate = () => {
    isEditing.value = false;
    form.reset();
    form.taskable_id = props.orderId;
    form.taskable_type = 'App\\Models\\ServiceOrder';
    form.user_ids = [];
    form.start_date = null; 
    form.due_date = null;
    form.finish_date = null;
    showCreateModal.value = true;
};

const openEdit = (task) => {
    showDetailModal.value = false; 
    isEditing.value = true;
    form.id = task.id;
    form.title = task.title || task.name; 
    form.description = task.description;
    form.priority = task.priority;
    
    const startDateRaw = task.start_date || task.start;
    const dueDateRaw = task.due_date || task.end;
    
    form.start_date = startDateRaw ? parseISO(startDateRaw).getTime() : null;
    form.due_date = dueDateRaw ? parseISO(dueDateRaw).getTime() : null; 
    form.finish_date = task.finish_date ? parseISO(task.finish_date).getTime() : null;
    
    form.user_ids = task.assignees ? task.assignees.map(u => u.id) : [];
    form.taskable_id = props.orderId;
    form.taskable_type = 'App\\Models\\ServiceOrder';
    
    showCreateModal.value = true;
};

const submitTask = () => {
    const transformedForm = form.transform((data) => ({
        ...data,
        taskable_id: Number(props.orderId),
        taskable_type: 'App\\Models\\ServiceOrder',
        start_date: data.start_date ? format(new Date(data.start_date), 'yyyy-MM-dd HH:mm:ss') : null,
        due_date: data.due_date ? format(new Date(data.due_date), 'yyyy-MM-dd HH:mm:ss') : null,
        finish_date: data.finish_date ? format(new Date(data.finish_date), 'yyyy-MM-dd HH:mm:ss') : null,
    }));

    if (isEditing.value) {
        transformedForm.put(route('tasks.update', form.id), {
            onSuccess: () => {
                notification.success({ title: 'Actualizado', content: 'Tarea modificada.', duration: 3000 });
                showCreateModal.value = false;
            }
        });
    } else {
        transformedForm.post(route('tasks.store'), {
            onSuccess: () => {
                notification.success({ title: 'Éxito', content: 'Tarea creada para esta Orden.', duration: 3000 });
                showCreateModal.value = false;
                form.reset();
            }
        });
    }
};

const updateTaskStatus = (task, newStatus) => {
    if (!task.assignees || task.assignees.length === 0) {
        notification.warning({ 
            title: 'Responsable Requerido', 
            content: 'No puedes cambiar el estatus de una tarea que no tiene personal asignado. Por favor, edita la tarea y asigna a un responsable primero.', 
            duration: 6000 
        });
        return; 
    }
    
    if (newStatus === 'Completado') {
        const pendingEvidences = task.required_evidences?.filter(e => !e.media?.length).length || 0;
        if (pendingEvidences > 0) {
            notification.error({
                title: 'Evidencias Pendientes',
                content: `No puedes completar la tarea. Faltan ${pendingEvidences} evidencias por subir en la Orden de Servicio vinculada.`,
                duration: 6000
            });
            return;
        }
    }

    task.status = newStatus;
    router.put(route('tasks.update', task.id), { status: newStatus }, {
        preserveScroll: true,
        preserveState: true, 
        onSuccess: () => notification.success({ title: 'Actualizado', content: 'Estatus guardado.', duration: 3000 }),
        onError: (errors) => {
            if (errors.status) {
                notification.error({ title: 'Error', content: errors.status, duration: 5000 });
            }
        }
    });
};

const deleteTask = (taskId) => {
    router.delete(route('tasks.destroy', taskId), {
        onSuccess: () => notification.success({title: 'Tarea eliminada', duration: 3000}),
        preserveScroll: true
    });
};

const confirmDeleteFromModal = () => {
    dialog.warning({
        title: 'Eliminar Tarea',
        content: '¿Estás seguro de que deseas eliminar esta tarea? Esta acción no se puede deshacer.',
        positiveText: 'Sí, Eliminar',
        negativeText: 'Cancelar',
        onPositiveClick: () => {
            deleteTask(selectedTask.value.id);
            showDetailModal.value = false;
        }
    });
};

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

const getStatusTagType = (status) => {
    const map = { 'Pendiente': 'default', 'En Proceso': 'info', 'Completado': 'success', 'Detenido': 'error' };
    return map[status] || 'default';
};

const getAvatarSrc = (user) => {
    if (user?.avatar) return user.avatar;
    if (user?.profile_photo_url) return user.profile_photo_url;
    if (user?.profile_photo_path) return '/storage/' + user.profile_photo_path;
    return `https://ui-avatars.com/api/?name=${encodeURIComponent(user?.name || user || 'User')}&background=random`;
};

const getGoogleMapsUrl = (lat, lng, fullAddress) => {
    if (lat && lng) {
        return `https://www.google.com/maps/search/?api=1&query=${lat},${lng}`;
    } else if (fullAddress) {
        return `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(fullAddress)}`;
    }
    return '#';
}

const whatsappOptions = [
    { label: '🔔 Nueva Tarea + Link', key: 'new_task' },
    { label: '⏰ Recordatorio', key: 'reminder' },
    { label: '💬 Aviso Comentario', key: 'unread' }
];

const handleWhatsappSelect = (key, task) => {
    const user = task.assignees && task.assignees[0]; 
    if (!user || !user.phone) {
        notification.error({ title: 'Error', content: 'El usuario no tiene teléfono registrado.' });
        return;
    }

    const phone = user.phone.replace(/\D/g, ''); 
    const orderUrl = props.secureUrl || route('service-orders.show', props.orderId); 
    const taskName = task.title || task.name;
    let message = '';

    switch (key) {
        case 'new_task':
            message = `Hola ${user.name}, se te ha asignado una *Nueva Tarea*: "${taskName}".\n\n📌 Orden #${props.orderId}\n🔗 Ver detalles: ${orderUrl}`;
            break;
        case 'reminder':
            message = `Hola ${user.name}, paso a recordarte sobre la tarea pendiente: "${taskName}" de la Orden #${props.orderId}.\n\nPor favor actualiza el estatus cuando puedas.\n${orderUrl}`;
            break;
        case 'unread':
            message = `Hola ${user.name}, tienes *comentarios no leídos* en la tarea "${taskName}" (Orden #${props.orderId}).\n\n🔗 Entra a revisarlos: ${orderUrl}`;
            break;
    }

    window.open(`https://wa.me/${phone}?text=${encodeURIComponent(message)}`, '_blank');
};

const statusColors = {
    'Pendiente': 'bg-gray-200 border-gray-300 text-gray-700',
    'En Proceso': 'bg-blue-200 border-blue-400 text-blue-900',
    'Completado': 'bg-emerald-200 border-emerald-400 text-black',
    'Detenido': 'bg-red-200 border-red-400 text-red-900',
};

const timeRange = computed(() => {
    if (!props.tasks?.length) return null;
    const starts = props.tasks.map(t => (t.start_date || t.start) ? parseISO(t.start_date || t.start) : null).filter(Boolean);
    const ends = props.tasks.map(t => (t.due_date || t.end) ? parseISO(t.due_date || t.end) : null).filter(Boolean);
    
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
    const endDateStr = task.finish_date || task.due_date || task.end; 
    const startDateStr = task.start_date || task.start;
    
    if (!startDateStr || !endDateStr || !timeRange.value) {
        return { display: 'none' };
    }
    const start = parseISO(startDateStr);
    const end = parseISO(endDateStr);
    const left = (differenceInHours(start, timeRange.value.minDate) / timeRange.value.totalHours) * 100;
    const width = (differenceInHours(end, start) / timeRange.value.totalHours) * 100 || 1; 
    return { left: `${left}%`, width: `${width}%` };
};

const getDisplayEndDate = (task) => {
    return task.finish_date || task.due_date || task.end; 
};

const translateUnit = (unit) => {
    const units = { days: 'día(s)', weeks: 'semana(s)', months: 'mes(es)', years: 'año(s)' };
    return units[unit] || unit;
};

</script>

<template>
    <div class="w-full bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden flex flex-col">
        
        <!-- Toolbar Header -->
        <div class="px-6 py-4 border-b border-gray-100 flex flex-col sm:flex-row justify-between items-start sm:items-center bg-white gap-4">
            <div>
                <h3 class="font-bold text-gray-800 text-lg flex items-center gap-2">
                    <n-icon class="text-indigo-600"><CalendarOutline /></n-icon>
                    Cronograma de Ejecución
                </h3>
                <p class="text-xs text-gray-500 mt-0.5">Gestión y avance de tareas del proyecto.</p>
            </div>
            
            <div class="flex items-center gap-6">
                <!-- Legend -->
                <div class="hidden md:flex gap-3 text-xs text-gray-600 font-medium">
                    <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-gray-300 border border-gray-400"></span> Pendiente</span>
                    <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-blue-300 border border-blue-400"></span> En Proceso</span>
                    <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-emerald-300 border border-emerald-400"></span> Completado</span>
                    <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-red-300 border border-red-400"></span> Detenido</span>
                </div>
                
                <n-button type="primary" @click="openCreate">
                    <template #icon><n-icon><AddOutline /></n-icon></template>
                    Nueva Tarea
                </n-button>
            </div>
        </div>

        <!-- Empty State -->
        <div v-if="!tasks?.length" class="p-12 flex flex-col items-center justify-center bg-gray-50/50">
            <n-empty description="No hay tareas asignadas a esta orden aún." class="mb-5"/>
            <n-button dashed type="primary" size="large" @click="openCreate">
                Crear primera tarea
            </n-button>
        </div>

        <!-- Gantt Chart Wrapper -->
        <div v-else class="relative overflow-x-auto flex-1 bg-white">
            <div class="min-w-[1050px]">
                
                <!-- Encabezado de la Tabla -->
                <div class="flex sticky top-0 z-20 bg-gray-50/95 backdrop-blur shadow-sm border-b border-gray-200">
                    <div class="w-[480px] flex-shrink-0 flex items-center text-xs font-bold text-gray-500 uppercase tracking-wider">
                        <div class="flex-1 px-4 py-3">Tarea / Detalles</div>
                        <div class="w-20 px-2 py-3 text-center border-l border-gray-200">Inicio</div>
                        <div class="w-20 px-2 py-3 text-center border-l border-gray-200">Fin</div>
                    </div>
                    <div class="flex-1 flex">
                        <div v-for="day in timelineDays" :key="day" class="flex-1 px-1 py-2 text-center border-l border-gray-200 last:border-r">
                            <div class="text-sm font-bold text-gray-700">{{ format(day, 'dd') }}</div>
                            <div class="text-[10px] text-gray-400 uppercase">{{ format(day, 'MMM', { locale: es }) }}</div>
                        </div>
                    </div>
                </div>

                <!-- Contenedor de Filas -->
                <div class="relative">
                    <div class="absolute top-0 bottom-0 left-[480px] right-0 flex pointer-events-none z-0">
                         <div v-for="day in timelineDays" :key="'grid-'+day" class="flex-1 border-l border-dashed border-gray-200/70"></div>
                    </div>

                    <template v-for="(taskList, listIndex) in [
                        { tasks: sortedNormalTasks, isRecurring: false }, 
                        { tasks: sortedRecurringTasks, isRecurring: true }
                    ]" :key="listIndex">

                        <div v-if="taskList.isRecurring && taskList.tasks.length > 0" class="mt-8 mb-4 px-4 relative z-10">
                            <n-divider dashed>
                                <div class="text-purple-600 font-bold flex items-center gap-2">
                                    <n-icon size="18"><SyncOutline /></n-icon>
                                    Mantenimientos y Tareas Cíclicas
                                </div>
                            </n-divider>
                        </div>

                        <div v-for="task in taskList.tasks" :key="(taskList.isRecurring ? 'rec-' : 'norm-') + task.id" class="flex items-stretch border-b border-gray-100 group relative hover:bg-slate-50 transition-colors h-[72px]">
                            
                            <!-- Columna Izquierda Fija: Detalles y Fechas -->
                            <div class="w-[480px] flex-shrink-0 flex items-stretch border-r border-gray-200 bg-white group-hover:bg-slate-50 transition-colors">
                                
                                <div class="w-1.5 flex-shrink-0" 
                                    :class="{
                                        'bg-red-500': task.priority === 'Alta',
                                        'bg-amber-400': task.priority === 'Media',
                                        'bg-blue-400': task.priority === 'Baja'
                                    }">
                                </div>

                                <div class="flex-1 p-3 flex flex-col justify-center" @click="openDetail(task)">
                                    <div class="flex justify-between items-start gap-3">
                                        <span class="font-bold text-gray-800 text-sm line-clamp-2 cursor-pointer hover:text-indigo-600 leading-tight">
                                            {{ task.title || task.name }}
                                        </span>
                                        
                                        <div @click.stop>
                                            <n-popselect 
                                                v-if="canEditTaskStatus(task)"
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
                                                <n-tag size="small" :bordered="false" class="cursor-pointer font-semibold whitespace-nowrap hover:opacity-80" 
                                                    :type="task.status === 'Completado' ? 'success' : (task.status === 'En Proceso' ? 'info' : (task.status === 'Detenido' ? 'error' : 'default'))">
                                                    {{ task.status }}
                                                </n-tag>
                                            </n-popselect>

                                            <n-tag 
                                                v-else 
                                                size="small" 
                                                :bordered="false" 
                                                class="font-semibold whitespace-nowrap cursor-not-allowed opacity-90" 
                                                title="No tienes permiso para modificar el estatus de esta tarea"
                                                :type="task.status === 'Completado' ? 'success' : (task.status === 'En Proceso' ? 'info' : (task.status === 'Detenido' ? 'error' : 'default'))">
                                                {{ task.status }}
                                            </n-tag>
                                        </div>
                                    </div>

                                    <div class="flex items-center justify-between mt-2 h-7">
                                        <div class="flex -space-x-1.5">
                                            <n-tooltip v-for="user in task.assignees" :key="user.id" placement="bottom">
                                                <template #trigger>
                                                    <n-avatar round size="small" :src="getAvatarSrc(user)" class="border-2 border-white shadow-sm hover:z-10 relative"/>
                                                </template>
                                                {{ user.name }}
                                            </n-tooltip>
                                            <div v-if="!task.assignees?.length" class="text-xs text-gray-400 italic">Sin asignar</div>
                                        </div>
                                        
                                        <div class="flex gap-1.5 opacity-0 group-hover:opacity-100 transition-opacity" @click.stop>
                                            <n-button size="tiny" circle secondary type="default" @click="openDetail(task)">
                                                <template #icon>
                                                    <n-badge :value="task.comments?.length || 0" :max="99" :show-zero="false">
                                                        <n-icon><ChatbubbleOutline /></n-icon>
                                                    </n-badge>
                                                </template>
                                            </n-button>
                                            
                                            <n-dropdown 
                                                v-if="task.assignees?.length" 
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
                                
                                <div class="w-20 flex flex-col items-center justify-center text-xs text-gray-500 border-l border-gray-100 bg-gray-50/30">
                                    <span class="font-medium text-gray-700">{{ (task.start_date || task.start) ? format(parseISO(task.start_date || task.start), 'dd MMM') : '-' }}</span>
                                    <span class="text-[10px]">{{ (task.start_date || task.start) ? format(parseISO(task.start_date || task.start), 'HH:mm') : '' }}</span>
                                </div>

                                <div class="w-20 flex flex-col items-center justify-center text-xs text-gray-500 border-l border-gray-100 bg-gray-50/30" :class="task.finish_date ? 'bg-green-50/50' : ''">
                                    <span class="font-bold" :class="task.finish_date ? 'text-green-600' : 'text-gray-700'">{{ getDisplayEndDate(task) ? format(parseISO(getDisplayEndDate(task)), 'dd MMM') : '-' }}</span>
                                    <span class="text-[10px]" :class="task.finish_date ? 'text-green-500' : ''">{{ getDisplayEndDate(task) ? format(parseISO(getDisplayEndDate(task)), 'HH:mm') : '' }}</span>
                                </div>

                            </div>

                            <!-- Columna Derecha: Renderizado del Gantt -->
                            <div class="flex-1 relative pr-4" @click="openDetail(task)">
                                <n-tooltip trigger="hover" placement="top" :style="{ maxWidth: '250px' }">
                                    <template #trigger>
                                        <div 
                                            class="absolute top-1/2 -translate-y-1/2 h-8 rounded-md shadow-sm border transition-all hover:brightness-95 hover:shadow-md cursor-pointer flex items-center px-2 overflow-hidden"
                                            :class="statusColors[task.status] || 'bg-gray-200 border-gray-300'"
                                            :style="getTaskStyle(task)"
                                        >
                                            <span class="truncate text-[10px] font-bold opacity-80 mix-blend-color-burn pointer-events-none">
                                                {{ task.title || task.name }}
                                            </span>
                                        </div>
                                    </template>
                                    <div class="text-xs space-y-1">
                                        <div class="font-bold text-sm mb-1">{{ task.title || task.name }}</div>
                                        <div class="flex items-center gap-1"><n-icon><FlagOutline/></n-icon> Estatus: <strong>{{ task.status }}</strong></div>
                                        <div class="flex items-center gap-1"><n-icon><TimeOutline/></n-icon> Inicio: {{ (task.start_date || task.start) ? format(parseISO(task.start_date || task.start), 'dd MMM, HH:mm') : 'N/A' }}</div>
                                        <div class="flex items-center gap-1"><n-icon><TimeOutline/></n-icon> Fin: {{ getDisplayEndDate(task) ? format(parseISO(getDisplayEndDate(task)), 'dd MMM, HH:mm') : 'N/A' }}</div>
                                        <div v-if="task.is_recurring" class="flex items-center gap-1 text-purple-500 font-semibold border-t border-gray-200/30 pt-1 mt-1">
                                            <n-icon><SyncOutline/></n-icon> Tarea Cíclica (Cada {{ task.recurring_interval }} {{ translateUnit(task.recurring_unit) }})
                                        </div>
                                        <div v-if="task.required_evidences?.length" class="flex items-center gap-1 text-blue-500 font-semibold border-t border-gray-200/30 pt-1 mt-1">
                                            <n-icon><CameraOutline/></n-icon> Requiere {{ task.required_evidences.length }} evidencia(s)
                                        </div>
                                    </div>
                                </n-tooltip>
                            </div>
                        </div>
                    </template>
                </div>

            </div>
        </div>

        <!-- ============================================== -->
        <!-- MODAL CREACIÓN / EDICIÓN                       -->
        <!-- ============================================== -->
        <n-modal v-model:show="showCreateModal">
            <n-card style="width: 700px; max-width: 95vw;" :title="isEditing ? '✏️ Editar Tarea' : '✨ Nueva Tarea'" :bordered="false" size="huge" role="dialog" aria-modal="true" class="rounded-2xl shadow-xl">
                <template #header-extra><n-icon size="24" class="cursor-pointer text-gray-400 hover:text-gray-700 transition-colors" @click="showCreateModal=false"><CloseOutline /></n-icon></template>
                
                <n-form :model="form" label-placement="top" class="grid grid-cols-1 md:grid-cols-2 gap-x-6 mt-2">
                    <n-form-item label="Título de la Tarea" path="title" class="md:col-span-2">
                        <n-input v-model:value="form.title" placeholder="Ej. Instalación de cableado..." size="large"/>
                    </n-form-item>
                    
                    <n-form-item label="Prioridad">
                        <n-select v-model:value="form.priority" :options="priorityOptions" size="large" />
                    </n-form-item>
                    
                    <n-form-item label="Responsables">
                        <n-select v-model:value="form.user_ids" multiple :options="userOptions" filterable placeholder="Seleccionar técnicos..." size="large" />
                    </n-form-item>
                    
                    <n-form-item label="Fecha y Hora de Inicio">
                        <n-date-picker v-model:value="form.start_date" type="datetime" clearable class="w-full" size="large" />
                    </n-form-item>
                    
                    <n-form-item label="Fecha y Hora Estimada Fin">
                        <n-date-picker v-model:value="form.due_date" type="datetime" clearable class="w-full" size="large" />
                    </n-form-item>

                    <n-form-item label="Descripción / Instrucciones" class="md:col-span-2">
                        <n-input v-model:value="form.description" type="textarea" :autosize="{ minRows: 3, maxRows: 6 }" placeholder="Detalla las instrucciones para esta tarea..." />
                    </n-form-item>
                </n-form>

                <template #footer>
                    <div class="flex justify-end gap-3 pt-4">
                        <n-button size="large" @click="showCreateModal = false">Cancelar</n-button>
                        <n-button size="large" type="primary" @click="submitTask" :loading="form.processing">
                            <template #icon><n-icon><SaveOutline /></n-icon></template>
                            Guardar Tarea
                        </n-button>
                    </div>
                </template>
            </n-card>
        </n-modal>

        <!-- ============================================== -->
        <!-- MODAL DETALLE Y CHAT COMPLETO                  -->
        <!-- ============================================== -->
        <n-modal v-model:show="showDetailModal">
            <n-card style="width: 900px; max-width: 95vw;" :bordered="false" size="small" closable @close="showDetailModal = false" content-style="padding: 0;">
                <template #header>
                    <div class="text-sm md:text-base font-bold text-gray-800 leading-tight pr-6 break-words whitespace-normal">
                        {{ selectedTask?.title || selectedTask?.name || 'Detalle de la Tarea' }}
                    </div>
                </template>
                
                <div class="flex flex-col md:flex-row h-[80vh] md:h-[600px] max-h-[85vh]" v-if="selectedTask">
                    
                    <!-- Toggle Button (Exclusivo para móviles) -->
                    <div class="md:hidden p-3 bg-gray-50 border-b border-gray-100 flex-shrink-0 sticky top-0 z-20">
                        <n-button block type="primary" secondary @click="showMobileComments = !showMobileComments" class="font-bold">
                            <template #icon>
                                <n-icon><ChatbubbleOutline v-if="!showMobileComments"/><CreateOutline v-else/></n-icon>
                            </template>
                            {{ showMobileComments ? 'Volver a Detalles de Tarea' : `Ver Comentarios y Notas (${selectedTask.comments?.length || 0})` }}
                        </n-button>
                    </div>

                    <!-- Columna Izquierda: Información -->
                    <div class="w-full md:w-1/2 p-4 md:p-5 space-y-4 md:space-y-5 border-r border-gray-100 overflow-y-auto bg-white custom-scrollbar flex-1"
                         :class="showMobileComments ? 'hidden md:block' : 'block'">
                        
                        <!-- Control de Estatus y Prioridad -->
                        <div class="flex justify-between items-center bg-gray-50/80 p-3 rounded-xl border border-gray-100">
                             <div class="flex flex-col">
                                 <span class="text-[10px] text-gray-400 font-bold uppercase tracking-wider mb-1">Estatus:</span>
                                 <n-popselect
                                     v-if="canEditTaskStatus(selectedTask)"
                                     :value="selectedTask.status"
                                     :options="[
                                         {label: 'Pendiente', value: 'Pendiente'},
                                         {label: 'En Proceso', value: 'En Proceso'},
                                         {label: 'Detenido', value: 'Detenido'},
                                         {label: 'Completado', value: 'Completado'}
                                     ]"
                                     trigger="click"
                                     @update:value="(val) => updateTaskStatus(selectedTask, val)"
                                 >
                                     <n-tag :type="getStatusTagType(selectedTask.status)" class="font-bold shadow-sm cursor-pointer hover:opacity-80 transition-all flex items-center gap-1.5" :bordered="false">
                                         {{ selectedTask.status }}
                                         <n-icon size="14"><ChevronDownOutline/></n-icon>
                                     </n-tag>
                                 </n-popselect>
                                 <n-tag v-else :type="getStatusTagType(selectedTask.status)" class="font-bold shadow-sm flex items-center gap-1.5" :bordered="false" title="Sin permisos para editar estatus">
                                     {{ selectedTask.status }}
                                 </n-tag>
                             </div>
                             <div class="flex flex-col items-end">
                                 <span class="text-[10px] text-gray-400 font-bold uppercase tracking-wider mb-1">Prioridad:</span>
                                 <div class="flex items-center gap-1.5 text-xs text-gray-700 font-bold bg-white px-2 py-1.5 rounded-md shadow-sm border border-gray-100">
                                    <div class="w-2.5 h-2.5 rounded-full" :class="{
                                        'bg-red-500': selectedTask.priority === 'Alta',
                                        'bg-amber-500': selectedTask.priority === 'Media',
                                        'bg-blue-400': selectedTask.priority === 'Baja'
                                    }"></div>
                                    {{ selectedTask.priority }}
                                 </div>
                             </div>
                        </div>
                       
                        <!-- Descripción -->
                        <div>
                            <div class="text-[10px] text-gray-400 font-bold uppercase tracking-wider mb-1.5 flex items-center">
                                <n-icon class="mr-1 text-sm"><CreateOutline/></n-icon>Descripción
                            </div>
                            <p class="text-gray-700 text-sm mt-1 bg-gray-50 p-4 rounded-xl border border-gray-100 whitespace-pre-wrap leading-relaxed">{{ selectedTask.description || 'Sin descripción detallada.' }}</p>
                        </div>

                        <!-- Evidencias Requeridas -->
                        <div class="mt-4 border-t border-gray-100 pt-4">
                            <div class="text-[10px] text-gray-400 font-bold uppercase tracking-wider mb-2 flex items-center">
                                <n-icon class="mr-1 text-sm"><CameraOutline/></n-icon>Evidencias Requeridas
                            </div>
                            
                            <div v-if="selectedTask.required_evidences?.length" class="space-y-2">
                                <div v-for="ev in selectedTask.required_evidences" :key="ev.id" class="flex items-center justify-between bg-blue-50/50 p-2.5 rounded-lg border border-blue-100/50">
                                    <div class="flex flex-col flex-1 min-w-0 pr-3">
                                        <span class="text-xs font-bold text-blue-900 truncate" :title="ev.title">{{ ev.title }}</span>
                                    </div>
                                    <n-tag :type="ev.media?.length ? 'success' : 'warning'" size="small" round :bordered="false" class="flex-shrink-0 font-bold shadow-sm">
                                        {{ ev.media?.length ? 'Completada' : 'Pendiente' }}
                                    </n-tag>
                                </div>
                                <div class="mt-2 text-[10px] text-gray-500 italic">
                                    * Las evidencias se suben directamente desde el perfil de la Orden de Servicio vinculada.
                                </div>
                            </div>
                            
                            <div v-else class="text-xs text-gray-500 bg-gray-50 p-3 rounded-lg border border-gray-100 flex items-center gap-2">
                                <n-icon size="16" class="text-gray-400"><CheckmarkCircleOutline/></n-icon>
                                <span>Esta tarea <strong>no requiere</strong> adjuntar evidencias fotográficas.</span>
                            </div>
                        </div>

                        <!-- Datos de la Tarea/Orden Relacionada -->
                        <div v-if="selectedTask.taskable_type === 'App\\Models\\ServiceOrder' && selectedTask.taskable" class="mt-4 border-t border-gray-100 pt-4">
                            <div class="flex justify-between items-center mb-3">
                                <div class="text-[10px] text-blue-500 font-bold uppercase tracking-wider flex items-center">
                                    <n-icon class="mr-1 text-sm"><ConstructOutline/></n-icon>Detalles de Operación
                                </div>
                            </div>
                            
                            <div class="bg-blue-50/30 rounded-xl p-4 border border-blue-100/50 space-y-3">
                                <div class="flex items-start gap-2" v-if="selectedTask.taskable.client">
                                    <n-icon class="mt-0.5 text-gray-400"><PersonOutline/></n-icon>
                                    <div>
                                        <div class="text-xs text-gray-500 font-medium">Cliente</div>
                                        <div class="text-sm font-semibold text-gray-800">{{ selectedTask.taskable.client.name }}</div>
                                    </div>
                                </div>

                                <div class="grid grid-cols-2 gap-2 bg-white p-2 rounded-lg border border-gray-100 shadow-sm" v-if="selectedTask.taskable.system_type || selectedTask.taskable.meter_number">
                                    <div v-if="selectedTask.taskable.system_type">
                                        <div class="text-[10px] text-gray-400 font-medium flex items-center gap-1"><n-icon><HardwareChipOutline/></n-icon> Sistema</div>
                                        <div class="text-xs font-semibold text-gray-700">{{ selectedTask.taskable.system_type }}</div>
                                    </div>
                                    <div v-if="selectedTask.taskable.meter_number">
                                        <div class="text-[10px] text-gray-400 font-medium flex items-center gap-1"><n-icon><SpeedometerOutline/></n-icon> Medidor</div>
                                        <div class="text-xs font-semibold text-gray-700">{{ selectedTask.taskable.meter_number }}</div>
                                    </div>
                                </div>

                                <div class="flex items-start gap-2 pt-2 border-t border-gray-200/50" v-if="selectedTask.taskable.installation_street">
                                    <n-icon class="mt-0.5 text-gray-400"><LocationOutline/></n-icon>
                                    <div class="flex-1">
                                        <div class="text-xs text-gray-500 font-medium">Ubicación de Instalación</div>
                                        <div class="text-sm text-gray-700 leading-snug">
                                            {{ selectedTask.taskable.installation_street }} {{ selectedTask.taskable.installation_exterior_number }} {{ selectedTask.taskable.installation_interior_number ? 'Int. ' + selectedTask.taskable.installation_interior_number : '' }}, 
                                            {{ selectedTask.taskable.installation_neighborhood }}, {{ selectedTask.taskable.installation_municipality }}, {{ selectedTask.taskable.installation_state }}
                                        </div>
                                        <div class="mt-2">
                                            <a :href="getGoogleMapsUrl(selectedTask.taskable.installation_lat, selectedTask.taskable.installation_lng, `${selectedTask.taskable.installation_street} ${selectedTask.taskable.installation_exterior_number}, ${selectedTask.taskable.installation_municipality}, ${selectedTask.taskable.installation_state}`)" target="_blank" class="inline-flex items-center gap-1 text-xs text-blue-600 hover:text-blue-800 font-medium bg-blue-50 px-2 py-1 rounded">
                                                <n-icon><MapOutline/></n-icon> Abrir en Google Maps
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Resumen de Tiempos -->
                        <div class="grid grid-cols-1 gap-3 bg-gray-50 p-4 rounded-xl text-xs border border-gray-100 shadow-sm">
                            <div class="flex justify-between border-b border-gray-200 pb-2">
                                <span class="text-gray-500 font-medium">Fecha Inicio:</span>
                                <span class="font-bold text-gray-700">{{ (selectedTask.start_date || selectedTask.start) ? format(parseISO(selectedTask.start_date || selectedTask.start), 'dd MMM yyyy, HH:mm') : 'No definida' }}</span>
                            </div>
                            <div class="flex justify-between border-b border-gray-200 pb-2">
                                <span class="text-gray-500 font-medium">Límite Estimado:</span>
                                <span class="font-bold text-gray-700">{{ (selectedTask.due_date || selectedTask.end) ? format(parseISO(selectedTask.due_date || selectedTask.end), 'dd MMM yyyy, HH:mm') : 'No definida' }}</span>
                            </div>
                            <div class="flex justify-between pt-1">
                                <span class="text-gray-500 font-medium">Fin Real:</span>
                                <span class="font-bold" :class="selectedTask.finish_date ? 'text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded-md border border-emerald-100' : 'text-gray-500'">
                                    {{ selectedTask.finish_date ? format(parseISO(selectedTask.finish_date), 'dd MMM yyyy, HH:mm') : 'Pendiente' }}
                                </span>
                            </div>
                        </div>

                        <!-- Responsables -->
                        <div>
                            <div class="text-[10px] text-gray-400 font-bold uppercase tracking-wider mb-2">Personal Asignado</div>
                            <div class="flex flex-wrap gap-2">
                                <n-tag size="medium" round v-for="u in selectedTask.assignees" :key="u.id" class="px-1 pr-3 bg-white border border-gray-200 shadow-sm" :bordered="false">
                                    <template #avatar>
                                        <n-avatar :src="getAvatarSrc(u)" />
                                    </template>
                                    <span class="font-medium text-gray-700">{{ u.name }}</span>
                                </n-tag>
                                <span v-if="!selectedTask.assignees?.length" class="text-xs text-amber-600 bg-amber-50 px-3 py-1.5 rounded-full border border-amber-100 font-medium shadow-sm">Sin personal asignado</span>
                            </div>
                        </div>
                    </div>

                    <!-- Columna Derecha: Chat y Notas -->
                    <div class="w-full md:w-1/2 flex-col bg-gray-50/50 flex-1 md:h-auto h-full"
                         :class="showMobileComments ? 'flex' : 'hidden md:flex'">
                        <div class="p-4 border-b border-gray-100 bg-white text-xs font-bold text-gray-500 flex justify-between items-center shadow-[0_2px_10px_-3px_rgba(0,0,0,0.05)] z-10 flex-shrink-0">
                            <span class="flex items-center gap-1.5"><n-icon size="16"><ChatbubbleOutline/></n-icon> COMENTARIOS Y NOTAS</span>
                            <n-badge :value="selectedTask.comments?.length || 0" type="info" />
                        </div>
                        
                        <div class="flex-1 p-5 overflow-y-auto space-y-5 custom-scrollbar min-h-0">
                             <div v-if="!selectedTask.comments?.length" class="h-full flex flex-col items-center justify-center text-gray-400 py-8 opacity-70">
                                <n-icon size="48" class="mb-3 text-gray-300"><ChatbubbleOutline /></n-icon>
                                <span class="text-sm font-medium">No hay comentarios aún</span>
                                <span class="text-xs mt-1">Sé el primero en agregar una nota a la tarea.</span>
                             </div>
                             
                             <div v-for="comment in selectedTask.comments" :key="comment.id" class="flex gap-3 text-xs group">
                                <n-avatar :src="comment.user_avatar || getAvatarSrc(comment.user)" round size="medium" class="mt-1 shadow-sm ring-2 ring-white flex-shrink-0" />
                                <div class="bg-white p-3.5 rounded-2xl rounded-tl-sm shadow-sm border border-gray-100 flex-1 relative hover:shadow-md transition-shadow">
                                    <div class="absolute -left-1.5 top-3 w-3 h-3 bg-white border-l border-b border-gray-100 rotate-45"></div>
                                    <div class="flex justify-between items-center mb-1.5 relative z-10">
                                        <span class="font-bold text-gray-800 text-sm">{{ comment.user?.name || comment.user }}</span>
                                        <span class="text-[10px] text-gray-400 font-medium bg-gray-50 px-1.5 py-0.5 rounded">{{ comment.created_at }}</span>
                                    </div>
                                    <p class="text-gray-600 relative z-10 text-[13px] leading-relaxed whitespace-pre-wrap">{{ comment.body }}</p>
                                </div>
                             </div>
                        </div>

                        <div class="p-4 bg-white border-t border-gray-100 shadow-[0_-4px_15px_-3px_rgba(0,0,0,0.03)] z-10 flex-shrink-0">
                             <n-input 
                                v-model:value="commentForm.body" 
                                type="textarea" 
                                placeholder="Escribe una actualización o nota..." 
                                :autosize="{ minRows: 2, maxRows: 5 }"
                                class="text-sm !bg-gray-50/50 hover:!bg-white focus:!bg-white transition-colors"
                                @keyup.enter.ctrl="submitComment"
                             />
                             <div class="flex justify-between items-center mt-3">
                                 <span class="text-[10px] text-gray-400 flex items-center gap-1 font-medium hidden sm:flex"><n-icon><SendOutline/></n-icon> Ctrl + Enter para enviar</span>
                                 <n-button size="small" type="primary" :disabled="!commentForm.body.trim()" :loading="commentForm.processing" @click="submitComment" class="px-5 font-bold shadow-sm ml-auto">
                                    Enviar Nota
                                 </n-button>
                             </div>
                        </div>
                    </div>
                </div>
                
                <template #footer v-if="selectedTask">
                    <div class="flex justify-end items-center px-4 py-3 bg-gray-50/50 border-t border-gray-100 gap-3">
                        <n-button v-if="hasPermission('tasks.delete')" @click="confirmDeleteFromModal" type="error" secondary>
                            <template #icon><n-icon><TrashOutline/></n-icon></template>
                            Eliminar
                        </n-button>
                        <n-button @click="openEdit(selectedTask)" type="primary" secondary>
                            <template #icon><n-icon><CreateOutline/></n-icon></template>
                            Editar Tarea Completa
                        </n-button>
                    </div>
                </template>
            </n-card>
        </n-modal>
    </div>
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
</style>