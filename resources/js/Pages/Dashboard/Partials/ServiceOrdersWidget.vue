<script setup>
import { NCard, NList, NListItem, NTag, NEmpty, NButton } from 'naive-ui';
import { Link } from '@inertiajs/vue3';

defineProps({
    orders: Array
});

const getStatusType = (status) => {
    switch(status) {
        case 'En Proceso': return 'info';
        case 'Aceptado': return 'success';
        case 'Cotización': return 'warning';
        default: return 'default';
    }
};
</script>

<template>
    <n-card title="Órdenes de Servicio" size="medium" class="shadow-sm rounded-2xl border-none" content-style="padding: 0;">
        <template #header-extra>
            <Link :href="route('service-orders.index')" class="text-sm text-blue-600 hover:text-blue-800 font-medium">
                Ver todas
            </Link>
        </template>
        
        <div v-if="orders.length > 0">
            <n-list hoverable clickable>
                <n-list-item v-for="order in orders" :key="order.id" @click="$inertia.visit(route('service-orders.show', order.id))">
                    <div class="flex justify-between items-center px-6 py-3">
                        <div>
                            <div class="font-medium text-gray-800">{{ order.client_name }}</div>
                            <div class="text-xs text-gray-500 mt-1">
                                Téc: {{ order.technician }} | Inicio: {{ order.start_date }}
                            </div>
                        </div>
                        <div class="flex flex-col items-end gap-1">
                            <n-tag :type="getStatusType(order.status)" size="small" round :bordered="false">
                                {{ order.status }}
                            </n-tag>
                            <span class="text-xs font-semibold text-gray-600">
                                ${{ new Intl.NumberFormat('es-MX').format(order.total_amount) }}
                            </span>
                        </div>
                    </div>
                </n-list-item>
            </n-list>
        </div>
        <div v-else class="p-8">
            <n-empty description="No hay servicios pendientes" />
        </div>
    </n-card>
</template>