<script setup>
import { NCard, NTimeline, NTimelineItem, NEmpty, NTime } from 'naive-ui';
import { Link } from '@inertiajs/vue3';

defineProps({
    orders: Array
});
</script>

<template>
    <n-card title="Compras por Recibir" size="medium" class="shadow-sm rounded-2xl border-none">
        <template #header-extra>
            <Link :href="route('purchases.index')" class="text-sm text-blue-600 hover:text-blue-800 font-medium">
                Compras
            </Link>
        </template>

        <div v-if="orders.length > 0" class="px-2">
            <n-timeline>
                <n-timeline-item
                    v-for="order in orders"
                    :key="order.id"
                    type="info"
                    :title="order.supplier"
                    :time="order.expected_date"
                    @click="$inertia.visit(route('purchases.show', order.id))"
                    class="hover:bg-gray-50 cursor-pointer"
                >
                    <div class="text-xs text-gray-500 mt-1">
                        Orden #{{ order.id }} - Total: ${{ new Intl.NumberFormat('es-MX').format(order.total_cost) }} {{ order.currency }}
                    </div>
                </n-timeline-item>
            </n-timeline>
        </div>
        <div v-else class="p-4">
             <n-empty description="Sin entregas pendientes" />
        </div>
    </n-card>
</template>