<script setup>
import { computed, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import { 
    NModal, NCard, NTag, NAvatar, NBadge, NPopselect, 
    NIcon, NInput, NButton, createDiscreteApi 
} from 'naive-ui';
import { 
    CreateOutline, ChatbubbleOutline, SendOutline, 
    TrashOutline, ChevronDownOutline 
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

// Sincronizar el ID de la tarea cuando se abre
watch(() => props.task, (newTask) => {
    if (newTask) {
        commentForm.commentable_id = newTask.id;
    }
}, { immediate: true });

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
    
    router.put(route('tasks.update', props.task.id), { status: newStatus }, {
        preserveScroll: true,
        preserveState: true, // Inertia actualizará los props automáticamente
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
</script>

<template>
    <n-modal v-model:show="isOpen">
        <n-card style="width: 800px; max-width: 95vw;" :title="task?.title || 'Detalle de la Tarea'" :bordered="false" size="huge" closable @close="isOpen = false" content-style="padding: 0;">
            
            <div class="flex flex-col md:flex-row h-[550px] max-h-[75vh]" v-if="task">
                <!-- Columna Izquierda: Información -->
                <div class="w-full md:w-1/2 p-5 space-y-5 border-r border-gray-100 overflow-y-auto bg-white">
                    
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
                <div class="w-full md:w-1/2 flex flex-col bg-gray-50/50">
                    <div class="p-4 border-b border-gray-100 bg-white text-xs font-bold text-gray-500 flex justify-between items-center shadow-[0_2px_10px_-3px_rgba(0,0,0,0.05)] z-10">
                        <span class="flex items-center gap-1.5"><n-icon size="16"><ChatbubbleOutline/></n-icon> COMENTARIOS Y NOTAS</span>
                        <n-badge :value="task.comments?.length || 0" type="info" />
                    </div>
                    
                    <div class="flex-1 p-5 overflow-y-auto space-y-5">
                         <div v-if="!task.comments?.length" class="h-full flex flex-col items-center justify-center text-gray-400 py-8 opacity-70">
                            <n-icon size="48" class="mb-3 text-gray-300"><ChatbubbleOutline /></n-icon>
                            <span class="text-sm font-medium">No hay comentarios aún</span>
                            <span class="text-xs mt-1">Sé el primero en agregar una nota a la tarea.</span>
                         </div>
                         
                         <div v-for="comment in task.comments" :key="comment.id" class="flex gap-3 text-xs group">
                            <n-avatar :src="getAvatarSrc(comment.user)" round size="medium" class="mt-1 shadow-sm ring-2 ring-white" />
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

                    <div class="p-4 bg-white border-t border-gray-100 shadow-[0_-4px_15px_-3px_rgba(0,0,0,0.03)] z-10">
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