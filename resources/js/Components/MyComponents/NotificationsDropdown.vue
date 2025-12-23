<script setup>
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { formatDistanceToNow } from 'date-fns';
import { es } from 'date-fns/locale';
import { 
    NPopover, 
    NBadge, 
    NButton, 
    NCheckbox,
    NCheckboxGroup, // Importación agregada
    NIcon,
    NScrollbar,
    NTooltip
} from 'naive-ui';
import { TrashOutline, CheckmarkDoneOutline, NotificationsOutline } from '@vicons/ionicons5';

const props = defineProps({
    notifications: {
        type: Array,
        default: () => []
    }
});

const showPopover = ref(false);
const selectedNotifications = ref([]);

// Computados
const unreadNotificationsCount = computed(() => {
    return props.notifications.filter(n => !n.read_at).length;
});

// Métodos
const handleNotificationClick = (notification) => {
    const url = notification.data.url || '#';
    
    const visit = () => {
        if (url !== '#') router.visit(url);
        showPopover.value = false;
    };

    if (notification.read_at) {
        visit();
        return;
    }

    router.patch(route('notifications.read', notification.id), {}, {
        preserveScroll: true,
        onSuccess: visit
    });
};

const markAllAsRead = () => {
    router.post(route('notifications.read-all'), {}, {
        preserveScroll: true,
    });
};

const deleteNotification = (notificationId) => {
    router.delete(route('notifications.destroy', notificationId), {
        preserveScroll: true,
    });
};

const deleteSelected = () => {
    if (selectedNotifications.value.length === 0) return;

    router.post(route('notifications.destroy-selected'), {
        ids: selectedNotifications.value
    }, {
        preserveScroll: true,
        onSuccess: () => {
            selectedNotifications.value = [];
        }
    });
};

const formatTimeAgo = (dateString) => {
    return formatDistanceToNow(new Date(dateString), { addSuffix: true, locale: es });
};
</script>

<template>
    <div class="relative">
        <n-popover 
            trigger="click" 
            placement="bottom-end" 
            v-model:show="showPopover"
            :show-arrow="false"
            raw 
            class="iphone-popover-content"
            style="margin-top: 12px;"
        >
            <template #trigger>
                <div class="cursor-pointer group mt-2">
                    <!-- Badge indicador rojo -->
                    <n-badge :value="unreadNotificationsCount" :max="99" :show="unreadNotificationsCount > 0" processing>
                        <div class="relative flex items-center justify-center w-10 h-10 rounded-full bg-white/50 hover:bg-white transition-all duration-300 shadow-sm hover:shadow-md border border-gray-100 group-active:scale-95">
                            <!-- Icono -->
                            <img 
                                src="/images/notification3d.png" 
                                alt="Notificaciones" 
                                class="w-6 h-6 object-contain"
                                onerror="this.style.display='none'; this.nextElementSibling.style.display='block';"
                            >
                            <!-- Fallback icon si la imagen falla -->
                            <n-icon size="22" class="text-blue-600 hidden">
                                <NotificationsOutline />
                            </n-icon>
                        </div>
                    </n-badge>
                </div>
            </template>

            <!-- Contenido del Dropdown (Estilo iPhone) -->
            <div class="w-[360px] bg-white/90 backdrop-blur-xl rounded-2xl shadow-2xl border border-white/40 overflow-hidden flex flex-col max-h-[85vh]">
                
                <!-- Cabecera -->
                <div class="px-5 py-4 flex items-center justify-between border-b border-gray-100/50 bg-white/50">
                    <div class="flex items-center gap-2">
                        <h3 class="font-bold text-lg text-gray-800 tracking-tight">Notificaciones</h3>
                        <!-- Indicador numérico en texto -->
                        <span v-if="unreadNotificationsCount > 0" class="px-2 py-0.5 rounded-full bg-red-100 text-red-600 text-xs font-bold border border-red-200">
                            {{ unreadNotificationsCount }} nuevas
                        </span>
                    </div>
                    
                    <div class="flex gap-2">
                        <n-tooltip trigger="hover" v-if="unreadNotificationsCount > 0">
                            <template #trigger>
                                <button 
                                    @click="markAllAsRead"
                                    class="p-1.5 rounded-full hover:bg-blue-50 text-blue-600 transition-colors"
                                >
                                    <n-icon size="18"><CheckmarkDoneOutline /></n-icon>
                                </button>
                            </template>
                            Marcar todo leído
                        </n-tooltip>
                        
                        <n-tooltip trigger="hover" v-if="selectedNotifications.length > 0">
                            <template #trigger>
                                <button 
                                    @click="deleteSelected"
                                    class="p-1.5 rounded-full hover:bg-red-50 text-red-500 transition-colors"
                                >
                                    <n-icon size="18"><TrashOutline /></n-icon>
                                </button>
                            </template>
                            Eliminar seleccionados
                        </n-tooltip>
                    </div>
                </div>

                <!-- Lista de notificaciones -->
                <div class="flex-1 overflow-hidden relative">
                    <n-scrollbar style="max-height: 400px;">
                        <div v-if="notifications.length > 0">
                            <!-- Envolvemos la lista en un grupo para manejar el array de selecciones -->
                            <n-checkbox-group v-model:value="selectedNotifications">
                                <div 
                                    v-for="notification in notifications" 
                                    :key="notification.id"
                                    class="group relative flex items-start p-4 transition-colors hover:bg-blue-50/30 border-b border-gray-50 last:border-0"
                                    :class="{ 'bg-blue-50/60': !notification.read_at }"
                                >
                                    <!-- Checkbox personalizado -->
                                    <div class="pt-1 pr-3" @click.stop>
                                        <!-- Usamos :value en lugar de v-model:checked -->
                                        <n-checkbox :value="notification.id" size="small" />
                                    </div>

                                    <!-- Contenido -->
                                    <div class="flex-1 cursor-pointer" @click="handleNotificationClick(notification)">
                                        <div class="flex items-start gap-3">
                                            <!-- Avatar / Icono de la notificación -->
                                            <div 
                                                class="flex-shrink-0 w-9 h-9 rounded-xl flex items-center justify-center text-white shadow-sm"
                                                :class="notification.read_at ? 'bg-gray-400' : 'bg-gradient-to-br from-blue-500 to-indigo-600'"
                                            >
                                                <i :class="notification.data.icon || 'fa-solid fa-bell'" class="text-sm"></i>
                                            </div>
                                            
                                            <div class="flex-1 min-w-0">
                                                <p class="text-sm text-gray-800 leading-snug" :class="{'font-semibold': !notification.read_at}" v-html="notification.data.message"></p>
                                                <p class="text-xs text-gray-500 mt-1 font-medium">{{ formatTimeAgo(notification.created_at) }}</p>
                                            </div>

                                            <!-- Indicador de no leído (punto azul brillante) -->
                                            <div v-if="!notification.read_at" class="w-2.5 h-2.5 rounded-full bg-blue-500 shadow-lg shadow-blue-500/50 mt-1.5 flex-shrink-0 animate-pulse"></div>
                                        </div>
                                    </div>

                                    <!-- Botón eliminar hover -->
                                    <button 
                                        @click.stop="deleteNotification(notification.id)"
                                        class="absolute right-2 top-1/2 -translate-y-1/2 p-2 rounded-full text-gray-400 hover:text-red-500 hover:bg-red-50 opacity-0 group-hover:opacity-100 transition-all duration-200"
                                    >
                                        <n-icon size="16"><TrashOutline /></n-icon>
                                    </button>
                                </div>
                            </n-checkbox-group>
                        </div>

                        <!-- Empty State -->
                        <div v-else class="py-12 px-6 flex flex-col items-center justify-center text-center">
                            <div class="w-16 h-16 bg-gray-50 rounded-full flex items-center justify-center mb-3">
                                <n-icon size="30" class="text-gray-300"><NotificationsOutline /></n-icon>
                            </div>
                            <h4 class="text-gray-900 font-semibold">Sin notificaciones</h4>
                            <p class="text-gray-500 text-sm mt-1">Estás al día con todo.</p>
                        </div>
                    </n-scrollbar>
                </div>

                <!-- Footer (Solo si hay seleccionados para acción rápida) -->
                <div v-if="selectedNotifications.length > 0" class="px-4 py-3 bg-gray-50/80 backdrop-blur-sm border-t border-gray-100 flex justify-center">
                    <n-button 
                        type="error" 
                        size="small" 
                        secondary 
                        class="w-full rounded-lg"
                        @click="deleteSelected"
                    >
                        Eliminar seleccionados ({{ selectedNotifications.length }})
                    </n-button>
                </div>
            </div>
        </n-popover>
    </div>
</template>

<style scoped>
/* Estilos adicionales para asegurar el look "Apple" */
:deep(.iphone-popover-content) {
    background: transparent !important;
    box-shadow: none !important;
    border: none !important;
    padding: 0 !important;
}

/* Personalización del scrollbar dentro del dropdown */
:deep(.n-scrollbar-rail) {
    width: 4px;
}
:deep(.n-scrollbar-rail__scrollbar) {
    background-color: rgba(0,0,0,0.1);
}
</style>