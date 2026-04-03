<script setup>
import { NDescriptions, NDescriptionsItem, NIcon, NAvatar } from 'naive-ui';
import { 
    CalendarOutline, CheckmarkCircleOutline, FlashOutline, 
    PricetagOutline, HardwareChipOutline 
} from '@vicons/ionicons5';

const props = defineProps({
    order: Object
});

const formatDate = (dateString) => {
    if(!dateString) return 'Sin definir';
    const date = new Date(dateString);
    return date.toLocaleDateString('es-MX', { year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' });
};
</script>

<template>
    <div class="p-4">
        <n-descriptions label-placement="top" bordered column="3">
            <n-descriptions-item label="Fecha de Inicio Real">
                <div class="flex items-center gap-2">
                    <n-icon class="text-gray-400"><CalendarOutline /></n-icon>
                    {{ formatDate(order.start_date) }}
                </div>
            </n-descriptions-item>
            <n-descriptions-item label="Fecha de Finalización">
                <div class="flex items-center gap-2" :class="order.completion_date ? 'text-green-600 font-medium' : 'text-gray-400'">
                    <n-icon><CheckmarkCircleOutline /></n-icon>
                    {{ order.completion_date ? formatDate(order.completion_date) : 'En progreso' }}
                </div>
            </n-descriptions-item>
            <n-descriptions-item label="Representante de Ventas">
                <div v-if="order.sales_rep" class="flex items-center gap-3">
                    <n-avatar size="small" round :src="order.sales_rep.profile_photo_url" :fallback-src="'https://ui-avatars.com/api/?name='+order.sales_rep.name" />
                    <span>{{ order.sales_rep.name }}</span>
                </div>
                <span v-else class="text-gray-400 italic">No asignado</span>
            </n-descriptions-item>
            
            <n-descriptions-item label="Número de Servicio / N° Medidor">
                <div v-if="order.service_number" class="flex items-center gap-2 font-mono text-indigo-700 bg-indigo-50 px-2 py-1 rounded w-fit">
                    <n-icon><FlashOutline /></n-icon> {{ order.service_number }} / {{ order.meter_number }}
                </div>
                <span v-else class="text-gray-400 italic">No especificado</span>
            </n-descriptions-item>

            <n-descriptions-item label="Tipo de Tarifa">
                 <div v-if="order.rate_type" class="flex items-center gap-2">
                    <n-icon class="text-gray-500"><PricetagOutline /></n-icon> {{ order.rate_type }}
                </div>
                <span v-else class="text-gray-400 italic">N/A</span>
            </n-descriptions-item>
            
            <n-descriptions-item label="Tipo de Sistema">
                 <div v-if="order.system_type" class="flex items-center gap-2">
                    <n-icon class="text-gray-500"><HardwareChipOutline /></n-icon> {{ order.system_type }}
                </div>
                <span v-else class="text-gray-400 italic">N/A</span>
            </n-descriptions-item>

            <n-descriptions-item label="Notas">
                {{ order.notes || 'Sin notas registradas.' }}
            </n-descriptions-item>
        </n-descriptions>
    </div>
</template>