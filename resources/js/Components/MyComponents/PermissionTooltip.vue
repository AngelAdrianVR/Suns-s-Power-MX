<script setup>
import { computed } from 'vue';
import { NTooltip, NIcon } from 'naive-ui';
import { LockClosedOutline } from '@vicons/ionicons5';
import { usePermissions } from '@/Composables/usePermissions';

const props = defineProps({
    /**
     * Permiso requerido para la acción/componente (ej. 'warehouse.alarms_stock')
     */
    permission: {
        type: String,
        required: true,
    },
    /**
     * Mensaje personalizado. Por defecto: "Requiere permiso: {permission}"
     */
    message: {
        type: String,
        default: null,
    },
    /**
     * Posición del tooltip: 'top', 'bottom', 'left', 'right'
     */
    placement: {
        type: String,
        default: 'left',
    },
    /**
     * Tipo de display: 'icon' = solo ícono, 'badge' = badge pequeño con texto
     */
    variant: {
        type: String,
        default: 'icon',
        validator: (value) => ['icon', 'badge'].includes(value),
    },
    /**
     * Tamaño del icono en px
     */
    size: {
        type: Number,
        default: 16,
    },
});

const { hasPermission } = usePermissions();

const displayMessage = computed(() => {
    return props.message || `Requiere permiso: ${props.permission}`;
});

const shouldRender = computed(() => {
    return hasPermission('permissions.show_permission');
});
</script>

<template>
    <NTooltip
        v-if="shouldRender"
        :placement="placement"
        trigger="hover"
        :show-arrow="true"
        :delay="300"
    >
        <template #trigger>
            <span
                class="inline-flex items-center cursor-help permission-tooltip-trigger select-none"
                :class="variant === 'badge' ? 'bg-amber-50 border border-amber-200 rounded-full px-2 py-0.5 gap-1' : ''"
            >
                <NIcon :size="size" class="text-amber-500 hover:text-amber-600 transition-colors">
                    <LockClosedOutline />
                </NIcon>
                <span v-if="variant === 'badge'" class="text-[10px] font-medium text-amber-700 leading-none">
                    permiso
                </span>
            </span>
        </template>
        <div class="flex items-center gap-1.5 text-xs">
            <span>🔒</span>
            <span>{{ displayMessage }}</span>
        </div>
    </NTooltip>
</template>

<style scoped>
.permission-tooltip-trigger {
    transition: opacity 0.15s ease;
}
.permission-tooltip-trigger:hover {
    opacity: 0.9;
}
</style>
