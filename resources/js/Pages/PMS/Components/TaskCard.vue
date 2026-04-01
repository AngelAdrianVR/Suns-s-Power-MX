<script setup>
import { computed } from 'vue';
import { NTag, NAvatar, NTooltip, NIcon } from 'naive-ui';
import { 
    ChatbubbleOutline, 
    MoveOutline, 
    CheckmarkCircle, 
    ArrowUp, 
    ArrowForward, 
    ArrowDown,
    TimeOutline,
    PlayCircleOutline,
    PauseCircleOutline
} from '@vicons/ionicons5';
import { format, parseISO } from 'date-fns';
import { es } from 'date-fns/locale';

const props = defineProps({
    task: {
        type: Object,
        required: true
    },
    isBacklog: {
        type: Boolean,
        default: false
    }
});

defineEmits(['click']);

// Configuración visual de Prioridades con íconos
const priorityInfo = computed(() => {
    const map = { 
        'Alta': { color: 'text-red-600', bg: 'bg-red-50', border: 'border-red-200', icon: ArrowUp }, 
        'Media': { color: 'text-amber-600', bg: 'bg-amber-50', border: 'border-amber-200', icon: ArrowForward }, 
        'Baja': { color: 'text-blue-600', bg: 'bg-blue-50', border: 'border-blue-200', icon: ArrowDown } 
    };
    return map[props.task.priority] || { color: 'text-gray-500', bg: 'bg-gray-50', border: 'border-gray-200', icon: ArrowForward };
});

// Configuración visual del Estatus (Ícono y Color)
const statusIconInfo = computed(() => {
    const map = {
        'Pendiente': { icon: TimeOutline, color: 'text-gray-400' },
        'En Proceso': { icon: PlayCircleOutline, color: 'text-blue-500' },
        'Completado': { icon: CheckmarkCircle, color: 'text-green-500' },
        'Detenido': { icon: PauseCircleOutline, color: 'text-red-500' }
    };
    return map[props.task.status] || { icon: TimeOutline, color: 'text-gray-400' };
});

const isCompleted = computed(() => props.task.status === 'Completado');

// Formatear la fecha de finalización si existe
const formattedFinishDate = computed(() => {
    if (!props.task.finish_date) return null;
    try {
        return format(parseISO(props.task.finish_date), "d MMM, HH:mm", { locale: es });
    } catch (error) {
        return null; // Fallback por si la fecha viene mal formada
    }
});

// Función segura para obtener la imagen del usuario
const getAvatarSrc = (user) => {
    if (user.profile_photo_url) return user.profile_photo_url;
    if (user.profile_photo_path) return '/storage/' + user.profile_photo_path;
    return null;
};
</script>

<template>
    <div 
        @click="$emit('click', task)"
        class="bg-white p-3 sm:p-4 rounded-xl shadow-sm border transition-all relative group"
        :class="[
            isBacklog ? 'cursor-grab border-gray-200' : 'cursor-pointer hover:border-indigo-300 hover:shadow-md',
            isCompleted ? 'border-green-100 bg-green-50/10' : 'border-gray-200'
        ]"
    >
        <!-- Encabezado de la Tarjeta (Módulo y Estatus) -->
        <div class="flex justify-between items-start mb-2 gap-2">
            <span class="text-[10px] sm:text-xs font-bold uppercase tracking-wide truncate flex-1"
                  :class="isCompleted ? 'text-green-600' : 'text-gray-400'">
                {{ task.taskable_type?.includes('ServiceOrder') ? 'OS #' + task.taskable_id : 
                   task.taskable_type?.includes('Ticket') ? 'TICKET #' + task.taskable_id : 'General' }}
            </span>
            
            <n-icon v-if="isBacklog" class="text-gray-300 group-hover:text-gray-500 transition-colors flex-shrink-0">
                <MoveOutline/>
            </n-icon>
            
            <!-- Tooltip para el Estatus (Solo Ícono) -->
            <n-tooltip v-else trigger="hover">
                <template #trigger>
                    <div class="flex items-center flex-shrink-0 mt-0.5 transition-transform hover:scale-110 cursor-help">
                        <n-icon size="18" :class="statusIconInfo.color">
                            <component :is="statusIconInfo.icon" />
                        </n-icon>
                    </div>
                </template>
                Estatus: {{ task.status }}
            </n-tooltip>
        </div>
        
        <!-- Título de la Tarea -->
        <h4 class="text-sm sm:text-base font-semibold leading-tight mb-3 line-clamp-2 transition-colors break-words" 
            :class="isCompleted ? 'text-gray-500 line-through decoration-gray-300' : 'text-gray-800'"
            :title="task.title">
            {{ task.title }}
        </h4>
        
        <!-- Detalles extra si está completada -->
        <div v-if="isCompleted && formattedFinishDate" class="flex items-center gap-1 text-[10px] text-green-600 font-medium mb-3 bg-green-50 w-fit px-2 py-1 rounded-md border border-green-100">
            <n-icon><TimeOutline /></n-icon>
            Completada el {{ formattedFinishDate }}
        </div>
        
        <!-- Footer de la Tarjeta (Prioridad, Avatares, Comentarios) -->
        <div class="flex justify-between items-center mt-auto flex-wrap gap-2 pt-1">
            
            <!-- Badge de Prioridad -->
            <div class="flex items-center gap-1 px-2 py-0.5 rounded-md border text-[10px] font-bold"
                 :class="[priorityInfo.bg, priorityInfo.color, priorityInfo.border]">
                <n-icon :component="priorityInfo.icon" />
                {{ task.priority }}
            </div>
            
            <!-- Responsables y Comentarios -->
            <div class="flex items-center gap-3 ml-auto">
                <template v-if="isBacklog">
                    <n-tag size="tiny" :bordered="false" type="warning" style="font-size: 9px;">Sin asignar</n-tag>
                </template>
                <template v-else>
                    <!-- Avatares -->
                    <div class="flex -space-x-2">
                        <n-tooltip v-for="user in task.assignees" :key="user.id" trigger="hover">
                            <template #trigger>
                                <n-avatar 
                                    round 
                                    :size="24" 
                                    :src="getAvatarSrc(user)" 
                                    :fallback-src="`https://ui-avatars.com/api/?name=${encodeURIComponent(user.name)}&background=random`" 
                                    class="border-2 border-white shadow-sm ring-1 ring-black/5"
                                />
                            </template>
                            {{ user.name }}
                        </n-tooltip>
                        
                        <span v-if="!task.assignees?.length" class="text-[9px] sm:text-[10px] text-amber-700 font-bold bg-amber-100 px-2 py-0.5 rounded-full border border-amber-200">
                            Sin asignar
                        </span>
                    </div>
                </template>

                <!-- Comentarios -->
                <div class="flex items-center text-gray-400 text-xs gap-1 font-medium" v-if="task.comments?.length">
                    <n-icon><ChatbubbleOutline/></n-icon> {{ task.comments.length }}
                </div>
            </div>

        </div>
    </div>
</template>