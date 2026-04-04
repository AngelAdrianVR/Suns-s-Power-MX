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
    PauseCircleOutline,
    CameraOutline // <-- NUEVO ÍCONO
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

const statusColor = computed(() => {
    const map = {
        'Pendiente': 'bg-gray-200 text-gray-600 border-gray-200',
        'En Proceso': 'bg-blue-200 text-blue-700 border-blue-200',
        'Completado': 'bg-emerald-300 text-emerald-700 border-emerald-200',
        'Detenido': 'bg-red-100 text-red-700 border-red-200'
    };
    return map[props.task.status] || 'bg-gray-100 text-gray-600';
});

const getAvatarSrc = (user) => {
    if (user.profile_photo_url) return user.profile_photo_url;
    if (user.profile_photo_path) return '/storage/' + user.profile_photo_path;
    return `https://ui-avatars.com/api/?name=${encodeURIComponent(user.name)}&background=random`;
};
</script>

<template>
    <div 
        class="bg-white rounded-xl shadow-sm hover:shadow-md transition-all border border-gray-100 group cursor-pointer relative overflow-hidden"
        @click="$emit('click')"
    >
        <!-- Borde izquierdo de color para estatus -->
        <div class="absolute left-0 top-0 bottom-0 w-1.5" :class="statusColor.split(' ')[0]"></div>

        <div class="p-3 pl-4">
            <!-- Header: Tipo y Prioridad -->
            <div class="flex justify-between items-start mb-2 gap-2">
                
                <div class="flex flex-col min-w-0">
                    <span class="text-[9px] font-bold text-gray-400 uppercase tracking-wider truncate">
                        <template v-if="task.taskable_type === 'App\\Models\\ServiceOrder'">
                            <span class="text-blue-500">OS #{{ task.taskable_id }}</span>
                        </template>
                        <template v-else-if="task.taskable_type === 'App\\Models\\Ticket'">
                            <span class="text-orange-500">Ticket #{{ task.taskable_id }}</span>
                        </template>
                        <template v-else>
                            General
                        </template>
                    </span>
                    <h4 class="text-xs sm:text-sm font-semibold text-gray-800 leading-tight mt-0.5 break-words line-clamp-2" :title="task.title">
                        {{ task.title }}
                    </h4>
                </div>

                <!-- Prioridad Badge -->
                <div class="flex-shrink-0 flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold border" :class="[priorityInfo.color, priorityInfo.bg, priorityInfo.border]">
                    <n-icon size="12" class="mr-0.5"><component :is="priorityInfo.icon" /></n-icon>
                    {{ task.priority }}
                </div>
            </div>

            <!-- Información adicional sutil -->
            <div class="text-[10px] text-gray-500 mt-2 flex flex-wrap items-center gap-2 mb-3">
                <span class="flex items-center" v-if="task.due_date">
                    <n-icon class="mr-1"><TimeOutline/></n-icon>
                    Límite: {{ format(parseISO(task.due_date), 'dd MMM') }}
                </span>
                
                <!-- NUEVO: INDICADOR DE EVIDENCIAS REQUERIDAS -->
                <span v-if="task.required_evidences?.length" class="flex items-center font-semibold" :class="task.required_evidences.every(e => e.media?.length > 0) ? 'text-emerald-600' : 'text-amber-600'">
                    <n-icon class="mr-1"><CameraOutline/></n-icon>
                    {{ task.required_evidences.filter(e => e.media?.length > 0).length }}/{{ task.required_evidences.length }}
                </span>

                <span v-if="task.status !== 'Pendiente'" class="flex items-center font-semibold" :class="statusColor.split(' ')[1]">
                    <n-icon class="mr-1" v-if="task.status === 'En Proceso'"><PlayCircleOutline/></n-icon>
                    <n-icon class="mr-1" v-if="task.status === 'Detenido'"><PauseCircleOutline/></n-icon>
                    <n-icon class="mr-1" v-if="task.status === 'Completado'"><CheckmarkCircle/></n-icon>
                    {{ task.status }}
                </span>
            </div>

            <!-- Footer: Asignados y Comentarios -->
            <div class="flex items-center justify-between border-t border-gray-50 pt-3">
                
                <template v-if="isBacklog">
                    <div class="flex items-center gap-2">
                        <!-- Identificador visual especial si es backlog -->
                        <span v-if="!task.assignees?.length" class="text-[10px] text-amber-600 font-bold bg-amber-50 px-2 py-0.5 rounded border border-amber-100 flex items-center gap-1">
                            Por asignar
                        </span>

                        <div v-if="task.assignees?.length > 0" class="flex -space-x-2">
                            <n-tooltip v-for="user in task.assignees" :key="user.id" trigger="hover">
                                <template #trigger>
                                    <n-avatar round :size="24" :src="getAvatarSrc(user)" :fallback-src="`https://ui-avatars.com/api/?name=${encodeURIComponent(user.name)}&background=random`" class="border-2 border-white shadow-sm ring-1 ring-black/5" />
                                </template>
                                {{ user.name }}
                            </n-tooltip>
                        </div>

                        <!-- ETIQUETA NUEVA MORADA: Si tiene asignado pero no tiene fecha -->
                        <span v-if="task.assignees?.length > 0 && !task.start_date" class="text-[9px] text-purple-700 font-bold bg-purple-100 px-2 py-0.5 rounded border border-purple-200">
                            Sin fecha
                        </span>
                    </div>
                </template>
                <template v-else>
                    <!-- Avatares -->
                    <div class="flex items-center gap-2">
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