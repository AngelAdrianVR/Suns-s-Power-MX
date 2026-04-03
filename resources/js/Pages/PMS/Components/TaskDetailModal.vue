<script setup>
import { computed, watch, ref } from 'vue';
import { router, useForm, Link } from '@inertiajs/vue3';
import { 
    NModal, NCard, NTag, NAvatar, NBadge, NPopselect, 
    NIcon, NInput, NButton, createDiscreteApi, NList, NListItem, NThing
} from 'naive-ui';
import { 
    CreateOutline, ChatbubbleOutline, SendOutline, 
    TrashOutline, ChevronDownOutline, ConstructOutline,
    LocationOutline, MapOutline, PersonOutline, CashOutline,
    FlashOutline, HardwareChipOutline, SpeedometerOutline,
    TicketOutline, WarningOutline, LinkOutline
} from '@vicons/ionicons5';
import { format, parseISO } from 'date-fns';
import { es } from 'date-fns/locale';
import { usePermissions } from '@/Composables/usePermissions';

const props = defineProps({
    show: Boolean,
    task: Object
});

const emit = defineEmits(['update:show', 'edit']);

const { hasPermission } = usePermissions();
const { notification, dialog } = createDiscreteApi(['notification', 'dialog']);

// Estado para controlar la vista de comentarios en Móvil
const showMobileComments = ref(false);

// Computed para el v-model del modal
const isOpen = computed({
    get: () => props.show,
    set: (value) => emit('update:show', value)
});

// Formulario para comentarios
const commentForm = useForm({
    body: '',
    commentable_type: 'task', 
    commentable_id: null
});

// Sincronizar el ID de la tarea y reiniciar estado móvil cuando se abre
watch(() => props.task, (newTask) => {
    if (newTask) {
        commentForm.commentable_id = newTask.id;
    }
}, { immediate: true });

watch(isOpen, (val) => {
    if (val) showMobileComments.value = false;
});

// --- MÉTODOS DE LA TAREA ---

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

const statusOptions = [
    { label: 'Pendiente', value: 'Pendiente' },
    { label: 'En Proceso', value: 'En Proceso' },
    { label: 'Detenido', value: 'Detenido' },
    { label: 'Completado', value: 'Completado' }
];

const handleStatusChange = (newStatus) => {
    if (!props.task) return;
    
    // Validación estricta de responsables antes de permitir el cambio de estatus
    if (!props.task.assignees || props.task.assignees.length === 0) {
        notification.warning({ 
            title: 'Responsable Requerido', 
            content: 'No puedes cambiar el estatus de una tarea que no tiene personal asignado. Por favor, edita la tarea y asigna a un responsable primero.', 
            duration: 6000 
        });
        return; 
    }
    
    router.put(route('tasks.update', props.task.id), { status: newStatus }, {
        preserveScroll: true,
        preserveState: true, 
        onSuccess: () => {
            notification.success({ title: 'Estatus Actualizado', content: `La tarea ahora está: ${newStatus}`, duration: 3000 });
        }
    });
};

const confirmDelete = () => {
    dialog.warning({
        title: 'Eliminar Tarea',
        content: '¿Estás seguro de que deseas eliminar esta tarea? Esta acción no se puede deshacer.',
        positiveText: 'Sí, Eliminar',
        negativeText: 'Cancelar',
        onPositiveClick: () => {
            router.delete(route('tasks.destroy', props.task.id), {
                preserveScroll: true,
                onSuccess: () => {
                    isOpen.value = false;
                    notification.success({ title: 'Eliminada', content: 'Tarea eliminada exitosamente.', duration: 3000 });
                }
            });
        }
    });
};

// --- UTILIDADES UI ---

const getStatusTagType = (status) => {
    const map = { 'Pendiente': 'default', 'En Proceso': 'info', 'Completado': 'success', 'Detenido': 'error' };
    return map[status] || 'default';
};

const getAvatarSrc = (user) => {
    if (user?.profile_photo_url) return user.profile_photo_url;
    if (user?.profile_photo_path) return '/storage/' + user.profile_photo_path;
    return `https://ui-avatars.com/api/?name=${encodeURIComponent(user?.name || 'User')}&background=random`;
};

const formatCommentDate = (dateStr) => {
    if (!dateStr) return '';
    try {
        return format(new Date(dateStr), 'dd MMM, HH:mm', { locale: es });
    } catch {
        return dateStr;
    }
};

const getGoogleMapsUrl = (lat, lng, fullAddress) => {
    if (lat && lng) {
        return `https://www.google.com/maps/search/?api=1&query=${lat},${lng}`;
    } else if (fullAddress) {
        return `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(fullAddress)}`;
    }
    return '#';
}

const formatCurrency = (value) => {
    if (value === null || value === undefined) return '';
    return new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(value);
};

</script>

<template>
    <n-modal v-model:show="isOpen">
        <!-- Tarjeta con un header personalizado para reducir el espacio y adaptarlo -->
        <n-card style="width: 900px; max-width: 95vw;" :bordered="false" size="small" closable @close="isOpen = false" content-style="padding: 0;">
            <template #header>
                <div class="text-sm md:text-base font-bold text-gray-800 leading-tight pr-6 break-words whitespace-normal">
                    {{ task?.title || 'Detalle de la Tarea' }}
                </div>
            </template>
            
            <div class="flex flex-col md:flex-row h-[80vh] md:h-[600px] max-h-[85vh]" v-if="task">
                
                <!-- Toggle Button (Exclusivo para móviles) -->
                <div class="md:hidden p-3 bg-gray-50 border-b border-gray-100 flex-shrink-0 sticky top-0 z-20">
                    <n-button block type="primary" secondary @click="showMobileComments = !showMobileComments" class="font-bold">
                        <template #icon>
                            <n-icon><ChatbubbleOutline v-if="!showMobileComments"/><CreateOutline v-else/></n-icon>
                        </template>
                        {{ showMobileComments ? 'Volver a Detalles de Tarea' : `Ver Comentarios y Notas (${task.comments?.length || 0})` }}
                    </n-button>
                </div>

                <!-- Columna Izquierda: Información -->
                <div class="w-full md:w-1/2 p-4 md:p-5 space-y-4 md:space-y-5 border-r border-gray-100 overflow-y-auto bg-white custom-scrollbar flex-1"
                     :class="showMobileComments ? 'hidden md:block' : 'block'">
                    
                    <div class="flex justify-between items-center bg-gray-50/80 p-3 rounded-xl border border-gray-100">
                         <div class="flex flex-col">
                             <span class="text-[10px] text-gray-400 font-bold uppercase tracking-wider mb-1">Estatus:</span>
                             <n-popselect
                                 :value="task.status"
                                 :options="statusOptions"
                                 trigger="click"
                                 @update:value="handleStatusChange"
                             >
                                 <n-tag :type="getStatusTagType(task.status)" class="font-bold shadow-sm cursor-pointer hover:opacity-80 transition-all flex items-center gap-1.5" :bordered="false">
                                     {{ task.status }}
                                     <n-icon size="14"><ChevronDownOutline/></n-icon>
                                 </n-tag>
                             </n-popselect>
                         </div>
                         <div class="flex flex-col items-end">
                             <span class="text-[10px] text-gray-400 font-bold uppercase tracking-wider mb-1">Prioridad:</span>
                             <div class="flex items-center gap-1.5 text-xs text-gray-700 font-bold bg-white px-2 py-1.5 rounded-md shadow-sm border border-gray-100">
                                <div class="w-2.5 h-2.5 rounded-full" :class="{
                                    'bg-red-500': task.priority === 'Alta',
                                    'bg-amber-500': task.priority === 'Media',
                                    'bg-blue-400': task.priority === 'Baja'
                                }"></div>
                                {{ task.priority }}
                             </div>
                         </div>
                    </div>
                   
                    <div>
                        <div class="text-[10px] text-gray-400 font-bold uppercase tracking-wider mb-1.5 flex items-center">
                            <n-icon class="mr-1 text-sm"><CreateOutline/></n-icon>Descripción
                        </div>
                        <p class="text-gray-700 text-sm mt-1 bg-gray-50 p-4 rounded-xl border border-gray-100 whitespace-pre-wrap leading-relaxed">{{ task.description || 'Sin descripción detallada.' }}</p>
                    </div>

                    <!-- SECCIÓN DINÁMICA: DEPENDIENDO DEL TASKABLE -->
                    <div v-if="task.taskable_type === 'App\\Models\\ServiceOrder' && task.taskable" class="mt-4 border-t border-gray-100 pt-4">
                        <div class="flex justify-between items-center mb-3">
                            <div class="text-[10px] text-blue-500 font-bold uppercase tracking-wider flex items-center">
                                <n-icon class="mr-1 text-sm"><ConstructOutline/></n-icon>Orden de Servicio #{{ task.taskable.id }}
                            </div>
                            <!-- Link a la Orden -->
                            <Link :href="route('service-orders.show', task.taskable.id)">
                                <n-button size="tiny" secondary type="info">
                                    <template #icon><n-icon><LinkOutline/></n-icon></template>
                                    Ver Orden
                                </n-button>
                            </Link>
                        </div>
                        
                        <div class="bg-blue-50/30 rounded-xl p-4 border border-blue-100/50 space-y-3">
                            
                            <!-- Cliente -->
                            <div class="flex items-start gap-2" v-if="task.taskable.client">
                                <n-icon class="mt-0.5 text-gray-400"><PersonOutline/></n-icon>
                                <div>
                                    <div class="text-xs text-gray-500 font-medium">Cliente</div>
                                    <div class="text-sm font-semibold text-gray-800">{{ task.taskable.client.name }}</div>
                                </div>
                            </div>

                            <!-- Sistema & Medidor -->
                            <div class="grid grid-cols-2 gap-2 bg-white p-2 rounded-lg border border-gray-100 shadow-sm" v-if="task.taskable.system_type || task.taskable.meter_number">
                                <div v-if="task.taskable.system_type">
                                    <div class="text-[10px] text-gray-400 font-medium flex items-center gap-1"><n-icon><HardwareChipOutline/></n-icon> Sistema</div>
                                    <div class="text-xs font-semibold text-gray-700">{{ task.taskable.system_type }}</div>
                                </div>
                                <div v-if="task.taskable.meter_number">
                                    <div class="text-[10px] text-gray-400 font-medium flex items-center gap-1"><n-icon><SpeedometerOutline/></n-icon> Medidor</div>
                                    <div class="text-xs font-semibold text-gray-700">{{ task.taskable.meter_number }}</div>
                                </div>
                            </div>

                            <!-- Ubicación y Coordenadas -->
                            <div class="flex items-start gap-2 pt-2 border-t border-gray-200/50" v-if="task.taskable.installation_street">
                                <n-icon class="mt-0.5 text-gray-400"><LocationOutline/></n-icon>
                                <div class="flex-1">
                                    <div class="text-xs text-gray-500 font-medium">Ubicación de Instalación</div>
                                    <div class="text-sm text-gray-700 leading-snug">
                                        {{ task.taskable.installation_street }} {{ task.taskable.installation_exterior_number }} {{ task.taskable.installation_interior_number ? 'Int. ' + task.taskable.installation_interior_number : '' }}, 
                                        {{ task.taskable.installation_neighborhood }}, {{ task.taskable.installation_municipality }}, {{ task.taskable.installation_state }}
                                    </div>
                                    <div class="text-[11px] text-gray-500 font-mono mt-1" v-if="task.taskable.installation_lat && task.taskable.installation_lng">
                                        📍 {{ task.taskable.installation_lat }}, {{ task.taskable.installation_lng }}
                                    </div>
                                    <div class="mt-2">
                                        <a :href="getGoogleMapsUrl(task.taskable.installation_lat, task.taskable.installation_lng, `${task.taskable.installation_street} ${task.taskable.installation_exterior_number}, ${task.taskable.installation_municipality}, ${task.taskable.installation_state}`)" target="_blank" class="inline-flex items-center gap-1 text-xs text-blue-600 hover:text-blue-800 font-medium bg-blue-50 px-2 py-1 rounded">
                                            <n-icon><MapOutline/></n-icon> Abrir en Google Maps
                                        </a>
                                    </div>
                                </div>
                            </div>

                            <!-- Materiales Asignados (Si hay) -->
                            <div v-if="task.taskable.items && task.taskable.items.length > 0" class="pt-2 border-t border-gray-200/50">
                                <div class="text-[10px] text-gray-500 font-bold uppercase mb-2">Materiales Asignados a la Orden</div>
                                <ul class="space-y-1">
                                    <li v-for="item in task.taskable.items" :key="item.id" class="text-xs flex justify-between items-center bg-white px-2 py-1 rounded shadow-sm border border-gray-50">
                                        <span class="text-gray-700 truncate mr-2">{{ item.product?.name || 'Producto Desconocido' }}</span>
                                        <n-tag size="small" :bordered="false" type="info" class="flex-shrink-0">x{{ item.quantity }}</n-tag>
                                    </li>
                                </ul>
                            </div>

                        </div>
                    </div>

                    <div v-else-if="task.taskable_type === 'App\\Models\\Ticket' && task.taskable" class="mt-4 border-t border-gray-100 pt-4">
                        <div class="flex justify-between items-center mb-3">
                            <div class="text-[10px] text-orange-500 font-bold uppercase tracking-wider flex items-center">
                                <n-icon class="mr-1 text-sm"><TicketOutline/></n-icon>Ticket de Soporte #{{ task.taskable.id }}
                            </div>
                            <!-- Link al Ticket -->
                            <Link :href="route('tickets.show', task.taskable.id)">
                                <n-button size="tiny" secondary type="warning">
                                    <template #icon><n-icon><LinkOutline/></n-icon></template>
                                    Ver Ticket
                                </n-button>
                            </Link>
                        </div>

                        <div class="bg-orange-50/30 rounded-xl p-4 border border-orange-100/50 space-y-3">
                            
                            <!-- Estatus del Ticket -->
                            <div class="flex justify-between items-center bg-white p-2 rounded-lg shadow-sm border border-gray-100">
                                <span class="text-xs text-gray-500 font-medium">Estatus del Ticket:</span>
                                <n-tag size="small" round :type="task.taskable.status === 'Resuelto' ? 'success' : (task.taskable.status === 'Abierto' ? 'error' : 'warning')">{{ task.taskable.status }}</n-tag>
                            </div>

                            <!-- Cliente -->
                            <div class="flex items-start gap-2" v-if="task.taskable.client">
                                <n-icon class="mt-0.5 text-gray-400"><PersonOutline/></n-icon>
                                <div>
                                    <div class="text-xs text-gray-500 font-medium">Cliente Afectado</div>
                                    <div class="text-sm font-semibold text-gray-800">{{ task.taskable.client.name }}</div>
                                </div>
                            </div>

                            <!-- Orden Relacionada -->
                            <div v-if="task.taskable.serviceOrder" class="pt-2 border-t border-gray-200/50 flex justify-between items-center">
                                <div>
                                    <div class="text-xs text-gray-500 font-medium flex items-center gap-1"><n-icon><ConstructOutline/></n-icon> Orden Vinculada</div>
                                    <div class="text-sm font-semibold text-gray-700">OS #{{ task.taskable.serviceOrder.id }}</div>
                                </div>
                                <Link :href="route('service-orders.show', task.taskable.serviceOrder.id)">
                                    <n-button size="tiny" quaternary type="info">Ir a Orden</n-button>
                                </Link>
                            </div>

                        </div>
                    </div>
                    <!-- ========================================== -->

                    <div class="grid grid-cols-1 gap-3 bg-gray-50 p-4 rounded-xl text-xs border border-gray-100 shadow-sm">
                        <div class="flex justify-between border-b border-gray-200 pb-2">
                            <span class="text-gray-500 font-medium">Fecha Inicio:</span>
                            <span class="font-bold text-gray-700">{{ task.start_date ? format(parseISO(task.start_date), 'dd MMM yyyy, HH:mm') : 'No definida' }}</span>
                        </div>
                        <div class="flex justify-between border-b border-gray-200 pb-2">
                            <span class="text-gray-500 font-medium">Límite Estimado:</span>
                            <span class="font-bold text-gray-700">{{ task.due_date ? format(parseISO(task.due_date), 'dd MMM yyyy, HH:mm') : 'No definida' }}</span>
                        </div>
                        <div class="flex justify-between pt-1">
                            <span class="text-gray-500 font-medium">Fin Real:</span>
                            <span class="font-bold" :class="task.finish_date ? 'text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded-md border border-emerald-100' : 'text-gray-500'">
                                {{ task.finish_date ? format(parseISO(task.finish_date), 'dd MMM yyyy, HH:mm') : 'Pendiente' }}
                            </span>
                        </div>
                    </div>

                     <div>
                        <div class="text-[10px] text-gray-400 font-bold uppercase tracking-wider mb-2">Personal Asignado</div>
                        <div class="flex flex-wrap gap-2">
                            <n-tag size="medium" round v-for="u in task.assignees" :key="u.id" class="px-1 pr-3 bg-white border border-gray-200 shadow-sm" :bordered="false">
                                <template #avatar>
                                    <n-avatar :src="getAvatarSrc(u)" />
                                </template>
                                <span class="font-medium text-gray-700">{{ u.name }}</span>
                            </n-tag>
                            <span v-if="!task.assignees?.length" class="text-xs text-amber-600 bg-amber-50 px-3 py-1.5 rounded-full border border-amber-100 font-medium shadow-sm">Sin personal asignado</span>
                        </div>
                    </div>
                </div>

                <!-- Columna Derecha: Chat y Notas -->
                <div class="w-full md:w-1/2 flex-col bg-gray-50/50 flex-1 md:h-auto h-full"
                     :class="showMobileComments ? 'flex' : 'hidden md:flex'">
                    <div class="p-4 border-b border-gray-100 bg-white text-xs font-bold text-gray-500 flex justify-between items-center shadow-[0_2px_10px_-3px_rgba(0,0,0,0.05)] z-10 flex-shrink-0">
                        <span class="flex items-center gap-1.5"><n-icon size="16"><ChatbubbleOutline/></n-icon> COMENTARIOS Y NOTAS</span>
                        <n-badge :value="task.comments?.length || 0" type="info" />
                    </div>
                    
                    <div class="flex-1 p-5 overflow-y-auto space-y-5 custom-scrollbar min-h-0">
                         <div v-if="!task.comments?.length" class="h-full flex flex-col items-center justify-center text-gray-400 py-8 opacity-70">
                            <n-icon size="48" class="mb-3 text-gray-300"><ChatbubbleOutline /></n-icon>
                            <span class="text-sm font-medium">No hay comentarios aún</span>
                            <span class="text-xs mt-1">Sé el primero en agregar una nota a la tarea.</span>
                         </div>
                         
                         <div v-for="comment in task.comments" :key="comment.id" class="flex gap-3 text-xs group">
                            <n-avatar :src="getAvatarSrc(comment.user)" round size="medium" class="mt-1 shadow-sm ring-2 ring-white flex-shrink-0" />
                            <div class="bg-white p-3.5 rounded-2xl rounded-tl-sm shadow-sm border border-gray-100 flex-1 relative hover:shadow-md transition-shadow">
                                <div class="absolute -left-1.5 top-3 w-3 h-3 bg-white border-l border-b border-gray-100 rotate-45"></div>
                                <div class="flex justify-between items-center mb-1.5 relative z-10">
                                    <span class="font-bold text-gray-800 text-sm">{{ comment.user?.name || comment.user }}</span>
                                    <span class="text-[10px] text-gray-400 font-medium bg-gray-50 px-1.5 py-0.5 rounded">{{ formatCommentDate(comment.created_at) }}</span>
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
            
            <template #footer v-if="task">
                <div class="flex justify-end items-center px-4 py-3 bg-gray-50/50 border-t border-gray-100 gap-3">
                    <n-button v-if="hasPermission('pms.delete')" @click="confirmDelete" type="error" secondary>
                        <template #icon><n-icon><TrashOutline/></n-icon></template>
                        Eliminar
                    </n-button>
                    <!-- Emitimos 'edit' al padre para que maneje la apertura del form -->
                    <n-button v-if="hasPermission('pms.edit')" @click="$emit('edit', task)" type="primary" secondary>
                        <template #icon><n-icon><CreateOutline/></n-icon></template>
                        Editar Tarea Completa
                    </n-button>
                </div>
            </template>
        </n-card>
    </n-modal>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
    width: 6px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background-color: #e5e7eb;
    border-radius: 20px;
}
.custom-scrollbar:hover::-webkit-scrollbar-thumb {
    background-color: #d1d5db;
}
</style>