<script setup>
import { computed, ref, watch } from 'vue';
import { useForm, router, usePage } from '@inertiajs/vue3';
import { usePermissions } from '@/Composables/usePermissions'; // Importación de permisos
import { 
    NAvatar, NTooltip, NTag, NEmpty, NButton, NIcon, NModal, NCard, NForm, 
    NFormItem, NInput, NDatePicker, NSelect, createDiscreteApi, NPopselect, NPopconfirm, NThing, NBadge,
    NScrollbar, NDropdown, NDescriptions, NDescriptionsItem
} from 'naive-ui';
import { 
    format, parseISO, differenceInHours, addDays, startOfDay, endOfDay 
} from 'date-fns';
import { es } from 'date-fns/locale';
import { 
    AddOutline, SaveOutline, CloseOutline, LogoWhatsapp, 
    PencilOutline, TrashOutline, ChatbubbleOutline, FlagOutline, SendOutline,
    CalendarOutline, TimeOutline, PersonOutline
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

const { notification } = createDiscreteApi(['notification']);
const { hasPermission } = usePermissions(); // Extrayendo permisos
const page = usePage();
const currentUserId = computed(() => page.props.auth?.user?.id); // ID del usuario logueado

const showCreateModal = ref(false);
const showDetailModal = ref(false);
const selectedTask = ref(null);
const isEditing = ref(false); 

// --- REGLA DE NEGOCIO PARA CAMBIO DE STATUS ---
const canEditTaskStatus = (task) => {
    // Si es admin o planeador y tiene el permiso maestro
    if (hasPermission('pms.schedule')) return true;
    
    // Si no, verificamos que el usuario logueado esté dentro del array de asignados de ESTA tarea
    if (!currentUserId.value || !task.assignees) return false;
    return task.assignees.some(u => u.id === currentUserId.value);
};
// ----------------------------------------------

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
    form.taskable_id = props.orderId;
    form.taskable_type = 'App\\Models\\ServiceOrder';
    form.user_ids = [];
    form.start_date = null; 
    form.due_date = null;
    form.finish_date = null;
    showCreateModal.value = true;
};

const openEdit = (task) => {
    isEditing.value = true;
    form.id = task.id;
    // Soporte hacia atrás por si viene como name o title
    form.title = task.title || task.name; 
    form.description = task.description;
    form.priority = task.priority;
    
    // Parseo seguro de fechas a timestamp para los DatePickers
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
    // Transformamos la información inyectando de manera estricta el módulo y el ID
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

// --- LÓGICA GANTT ---
const statusColors = {
    'Pendiente': 'bg-gray-200 border-gray-300 text-gray-700',
    'En Proceso': 'bg-blue-200 border-blue-400 text-blue-900',
    'Completado': 'bg-emerald-200 border-emerald-400 text-black',
    'Detenido': 'bg-red-200 border-red-400 text-red-900',
};

const timeRange = computed(() => {
    if (!props.tasks.length) return null;
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
        <div v-if="!tasks.length" class="p-12 flex flex-col items-center justify-center bg-gray-50/50">
            <n-empty description="No hay tareas asignadas a esta orden aún." class="mb-5"/>
            <n-button dashed type="primary" size="large" @click="openCreate">
                Crear primera tarea
            </n-button>
        </div>

        <!-- Gantt Chart Wrapper -->
        <div v-else class="relative overflow-x-auto flex-1 bg-white">
            <div class="min-w-[1050px]">
                
                <!-- Encabezado de la Tabla (Pegajoso Superior) -->
                <div class="flex sticky top-0 z-20 bg-gray-50/95 backdrop-blur shadow-sm border-b border-gray-200">
                    <!-- Cabecera de Info Izquierda -->
                    <div class="w-[480px] flex-shrink-0 flex items-center text-xs font-bold text-gray-500 uppercase tracking-wider">
                        <div class="flex-1 px-4 py-3">Tarea / Detalles</div>
                        <div class="w-20 px-2 py-3 text-center border-l border-gray-200">Inicio</div>
                        <div class="w-20 px-2 py-3 text-center border-l border-gray-200">Fin</div>
                    </div>
                    <!-- Cabecera de Días del Gantt -->
                    <div class="flex-1 flex">
                        <div v-for="day in timelineDays" :key="day" class="flex-1 px-1 py-2 text-center border-l border-gray-200 last:border-r">
                            <div class="text-sm font-bold text-gray-700">{{ format(day, 'dd') }}</div>
                            <div class="text-[10px] text-gray-400 uppercase">{{ format(day, 'MMM', { locale: es }) }}</div>
                        </div>
                    </div>
                </div>

                <!-- Contenedor de Filas -->
                <div class="relative">
                    <!-- Cuadrícula de Fondo (Días) -->
                    <div class="absolute top-0 bottom-0 left-[480px] right-0 flex pointer-events-none z-0">
                         <div v-for="day in timelineDays" :key="'grid-'+day" class="flex-1 border-l border-dashed border-gray-200/70"></div>
                    </div>

                    <!-- Iteración de Tareas -->
                    <div v-for="task in tasks" :key="task.id" class="flex relative z-10 group border-b border-gray-100 min-h-[72px] hover:bg-slate-50 transition-colors">
                        
                        <!-- Columna Izquierda Fija: Detalles y Fechas -->
                        <div class="w-[480px] flex-shrink-0 flex items-stretch border-r border-gray-200 bg-white group-hover:bg-slate-50 transition-colors">
                            
                            <!-- Indicador de Prioridad -->
                            <div class="w-1.5 flex-shrink-0" 
                                :class="{
                                    'bg-red-500': task.priority === 'Alta',
                                    'bg-amber-400': task.priority === 'Media',
                                    'bg-blue-400': task.priority === 'Baja'
                                }">
                            </div>

                            <!-- Información de la Tarea -->
                            <div class="flex-1 p-3 flex flex-col justify-center" @click="openDetail(task)">
                                <div class="flex justify-between items-start gap-3">
                                    <span class="font-bold text-gray-800 text-sm line-clamp-2 cursor-pointer hover:text-indigo-600 leading-tight" @click="openDetail(task)">
                                        {{ task.title || task.name }}
                                    </span>
                                    
                                    <!-- Selector Activo de Estatus (SÓLO PARA AUTORIZADOS) -->
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

                                    <!-- Etiqueta Visual (CUANDO NO TIENEN PERMISO) -->
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

                                <div class="flex items-center justify-between mt-2 h-7">
                                    <!-- Avatares -->
                                    <div class="flex -space-x-1.5">
                                        <n-tooltip v-for="user in task.assignees" :key="user.id" placement="bottom">
                                            <template #trigger>
                                                <n-avatar round size="small" :src="user.avatar" class="border-2 border-white shadow-sm hover:z-10 relative"/>
                                            </template>
                                            {{ user.name }}
                                        </n-tooltip>
                                        <div v-if="!task.assignees?.length" class="text-xs text-gray-400 italic">Sin asignar</div>
                                    </div>
                                    
                                    <!-- Botones de Acción (Visibles en Hover) -->
                                    <div class="flex gap-1.5 opacity-0 group-hover:opacity-100 transition-opacity" @click.stop>
                                        <n-button size="tiny" circle secondary type="default" @click="openDetail(task)">
                                            <template #icon>
                                                <n-badge :value="task.comments?.length || 0" :max="99" :show-zero="false">
                                                    <n-icon><ChatbubbleOutline /></n-icon>
                                                </n-badge>
                                            </template>
                                        </n-button>
                                        
                                        <!-- Menú WhatsApp -->
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
                            
                            <!-- Columna Fecha Inicio -->
                            <div class="w-20 flex flex-col items-center justify-center text-xs text-gray-500 border-l border-gray-100 bg-gray-50/30">
                                <span class="font-medium text-gray-700">{{ (task.start_date || task.start) ? format(parseISO(task.start_date || task.start), 'dd MMM') : '-' }}</span>
                                <span class="text-[10px]">{{ (task.start_date || task.start) ? format(parseISO(task.start_date || task.start), 'HH:mm') : '' }}</span>
                            </div>

                            <!-- Columna Fecha Fin -->
                            <div class="w-20 flex flex-col items-center justify-center text-xs text-gray-500 border-l border-gray-100 bg-gray-50/30" :class="task.finish_date ? 'bg-green-50/50' : ''">
                                <span class="font-bold" :class="task.finish_date ? 'text-green-600' : 'text-gray-700'">{{ getDisplayEndDate(task) ? format(parseISO(getDisplayEndDate(task)), 'dd MMM') : '-' }}</span>
                                <span class="text-[10px]" :class="task.finish_date ? 'text-green-500' : ''">{{ getDisplayEndDate(task) ? format(parseISO(getDisplayEndDate(task)), 'HH:mm') : '' }}</span>
                            </div>

                        </div>

                        <!-- Columna Derecha: Renderizado del Gantt -->
                        <div class="flex-1 relative pr-4" @click="openDetail(task)">
                            <n-tooltip trigger="hover" placement="top" :style="{ maxWidth: '250px' }">
                                <template #trigger>
                                    <!-- Píldora de la tarea -->
                                    <div 
                                        class="absolute top-1/2 -translate-y-1/2 h-8 rounded-md shadow-sm border transition-all hover:brightness-95 hover:shadow-md cursor-pointer flex items-center px-2 overflow-hidden"
                                        :class="statusColors[task.status] || 'bg-gray-200 border-gray-300'"
                                        :style="getTaskStyle(task)"
                                    >
                                        <!-- Texto opcional dentro de la barra si hay espacio -->
                                        <span class="truncate text-[10px] font-bold opacity-80 mix-blend-color-burn pointer-events-none">
                                            {{ task.title || task.name }}
                                        </span>
                                    </div>
                                </template>
                                <!-- Contenido del Tooltip -->
                                <div class="text-xs space-y-1">
                                    <div class="font-bold text-sm mb-1">{{ task.title || task.name }}</div>
                                    <div class="flex items-center gap-1"><n-icon><FlagOutline/></n-icon> Estatus: <strong>{{ task.status }}</strong></div>
                                    <div class="flex items-center gap-1"><n-icon><TimeOutline/></n-icon> Inicio: {{ (task.start_date || task.start) ? format(parseISO(task.start_date || task.start), 'dd MMM, HH:mm') : 'N/A' }}</div>
                                    <div class="flex items-center gap-1"><n-icon><TimeOutline/></n-icon> Fin: {{ getDisplayEndDate(task) ? format(parseISO(getDisplayEndDate(task)), 'dd MMM, HH:mm') : 'N/A' }}</div>
                                </div>
                            </n-tooltip>
                        </div>
                    </div>
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
        <!-- MODAL DETALLE Y CHAT                           -->
        <!-- ============================================== -->
        <n-modal v-model:show="showDetailModal">
            <n-card style="width: 850px; max-width: 95vw;" :bordered="false" role="dialog" aria-modal="true" content-style="padding: 0;" class="rounded-2xl shadow-2xl overflow-hidden">
                
                <!-- Header del Modal -->
                <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-start bg-gray-50/80">
                    <div class="pr-8">
                        <div class="flex items-center gap-3 mb-1">
                            <n-tag :type="selectedTask?.status === 'Completado' ? 'success' : 'default'" size="small" round font-bold>{{ selectedTask?.status }}</n-tag>
                            <span class="text-xs font-bold px-2 py-0.5 rounded-md" :class="{
                                'bg-red-100 text-red-700': selectedTask?.priority === 'Alta',
                                'bg-amber-100 text-amber-700': selectedTask?.priority === 'Media',
                                'bg-blue-100 text-blue-700': selectedTask?.priority === 'Baja'
                            }">Prioridad {{ selectedTask?.priority }}</span>
                        </div>
                        <h2 class="text-xl font-bold text-gray-800 leading-tight mt-2">{{ selectedTask?.title || selectedTask?.name }}</h2>
                    </div>
                    <n-button circle quaternary @click="showDetailModal=false">
                        <template #icon><n-icon size="24"><CloseOutline /></n-icon></template>
                    </n-button>
                </div>

                <div class="flex flex-col md:flex-row h-[550px] bg-white" v-if="selectedTask">
                    
                    <!-- COLUMNA IZQUIERDA: Info Detallada -->
                    <div class="w-full md:w-[45%] p-6 space-y-6 border-r border-gray-100 overflow-y-auto">
                        
                        <!-- Descripción -->
                        <div>
                            <h4 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Descripción</h4>
                            <div class="text-sm text-gray-700 bg-gray-50 p-4 rounded-xl leading-relaxed whitespace-pre-wrap">
                                {{ selectedTask.description || 'No se agregaron instrucciones adicionales para esta tarea.' }}
                            </div>
                        </div>

                        <!-- Fechas -->
                        <div>
                            <h4 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Tiempos</h4>
                            <n-descriptions label-placement="left" :column="1" size="small" bordered class="bg-white rounded-xl overflow-hidden">
                                <n-descriptions-item label="Inicio">
                                    <span class="font-medium text-gray-800">{{ (selectedTask.start_date || selectedTask.start) ? format(parseISO(selectedTask.start_date || selectedTask.start), 'dd MMM yyyy, HH:mm') : 'Sin definir' }}</span>
                                </n-descriptions-item>
                                <n-descriptions-item label="Vencimiento">
                                    <span class="font-medium text-gray-800">{{ (selectedTask.due_date || selectedTask.end) ? format(parseISO(selectedTask.due_date || selectedTask.end), 'dd MMM yyyy, HH:mm') : 'Sin definir' }}</span>
                                </n-descriptions-item>
                                <n-descriptions-item label="Fin Real">
                                    <span class="font-bold" :class="selectedTask.finish_date ? 'text-green-600' : 'text-gray-400'">
                                        {{ selectedTask.finish_date ? format(parseISO(selectedTask.finish_date), 'dd MMM yyyy, HH:mm') : 'Pendiente' }}
                                    </span>
                                </n-descriptions-item>
                            </n-descriptions>
                        </div>

                         <!-- Responsables -->
                         <div>
                            <h4 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-3">Responsables Asignados</h4>
                            <div class="flex flex-col gap-2">
                                <div v-for="u in selectedTask.assignees" :key="u.id" class="flex items-center gap-3 bg-white border border-gray-100 p-2 rounded-lg shadow-sm">
                                    <n-avatar :src="u.avatar" round size="medium" />
                                    <div>
                                        <div class="text-sm font-bold text-gray-800">{{ u.name }}</div>
                                        <div class="text-xs text-gray-500 flex items-center gap-1"><n-icon><LogoWhatsapp/></n-icon> {{ u.phone || 'Sin número' }}</div>
                                    </div>
                                </div>
                                <div v-if="!selectedTask.assignees?.length" class="text-sm text-gray-400 italic py-2">Ningún responsable asignado.</div>
                            </div>
                        </div>
                    </div>

                    <!-- COLUMNA DERECHA: Chat / Comentarios -->
                    <div class="w-full md:w-[55%] flex flex-col bg-slate-50/50">
                        <div class="p-4 border-b border-gray-100 bg-white flex justify-between items-center shadow-sm z-10">
                            <span class="font-bold text-gray-700 flex items-center gap-2">
                                <n-icon class="text-indigo-500"><ChatbubbleOutline /></n-icon>
                                Actividad y Comentarios
                            </span>
                            <n-badge :value="selectedTask.comments?.length || 0" type="info" show-zero />
                        </div>
                        
                        <!-- Lista de Mensajes -->
                        <div class="flex-1 p-5 overflow-y-auto space-y-5">
                             <div v-if="!selectedTask.comments?.length" class="h-full flex flex-col items-center justify-center text-gray-400">
                                <n-icon size="48" class="mb-3 opacity-20"><ChatbubbleOutline/></n-icon>
                                <p class="text-sm">No hay comentarios aún.</p>
                                <p class="text-xs mt-1">Sé el primero en escribir una nota.</p>
                             </div>
                             
                             <div v-for="comment in selectedTask.comments" :key="comment.id" class="flex gap-3 group">
                                <n-avatar :src="comment.user_avatar" round size="medium" class="flex-shrink-0 mt-1 shadow-sm" />
                                <div class="bg-white p-3.5 rounded-2xl rounded-tl-sm shadow-sm border border-gray-100 flex-1 relative transition-shadow hover:shadow-md">
                                    <div class="flex justify-between items-baseline mb-1.5">
                                        <span class="font-bold text-gray-800 text-sm">{{ comment.user }}</span>
                                        <span class="text-[10px] text-gray-400 font-medium">{{ comment.created_at }}</span>
                                    </div>
                                    <p class="text-sm text-gray-600 leading-relaxed whitespace-pre-wrap">{{ comment.body }}</p>
                                </div>
                             </div>
                        </div>

                        <!-- Input de Mensaje Fijo Abajo -->
                        <div class="p-4 bg-white border-t border-gray-200">
                             <n-input 
                                v-model:value="commentForm.body" 
                                type="textarea" 
                                placeholder="Escribe un mensaje o nota..." 
                                :autosize="{ minRows: 2, maxRows: 4 }"
                                class="bg-gray-50"
                                @keyup.ctrl.enter="submitComment"
                             />
                             <div class="flex justify-between items-center mt-3">
                                 <span class="text-[10px] text-gray-400 ml-1">Ctrl + Enter para enviar</span>
                                 <n-button type="primary" :disabled="!commentForm.body.trim()" :loading="commentForm.processing" @click="submitComment">
                                    <template #icon><n-icon><SendOutline /></n-icon></template>
                                    Enviar Mensaje
                                 </n-button>
                             </div>
                        </div>
                    </div>
                </div>
            </n-card>
        </n-modal>
    </div>
</template>