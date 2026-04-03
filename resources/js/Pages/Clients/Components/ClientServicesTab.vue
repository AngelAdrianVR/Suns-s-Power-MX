<script setup>
import { h } from 'vue';
import { usePermissions } from '@/Composables/usePermissions';
import { router, Link } from '@inertiajs/vue3';
import { NButton, NIcon, NTag, NDataTable } from 'naive-ui';
import { AddOutline, EyeOutline } from '@vicons/ionicons5';

const props = defineProps({
    client: Object
});

const { hasPermission } = usePermissions();

const formatCurrency = (amount) => new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(amount);
const formatDate = (dateString) => {
    if (!dateString) return '-';
    return new Date(dateString).toLocaleDateString('es-MX', { year: 'numeric', month: 'short', day: 'numeric' });
};

const createServiceOrder = () => {
    router.visit(route('service-orders.create', { client_id: props.client.id }));
};

const serviceColumns = [
    { title: 'Folio', key: 'id', width: 70, render: (row) => `#${row.id}` },
    { 
        title: 'Estado', 
        key: 'status',
        render(row) {
            const types = { 
                'Cotización': 'default', 'Aceptado': 'info', 'En Proceso': 'warning', 
                'Completado': 'success', 'Facturado': 'success', 'Cancelado': 'error' 
            };
            return h(NTag, { type: types[row.status] || 'default', size: 'tiny', bordered: false, round: true }, { default: () => row.status });
        }
    },
    { title: 'Inicio', key: 'start_date', width: 130, render: (row) => formatDate(row.start_date) },
    { title: 'Total', key: 'total_amount', align: 'right', render: (row) => formatCurrency(row.total_amount) },
    {
        title: '',
        key: 'actions',
        width: 50,
        render(row) {
            return h(NButton, { 
                circle: true, size: 'tiny', secondary: true,
                onClick: () => router.visit(route('service-orders.show', row.id))
            }, { icon: () => h(NIcon, null, { default: () => h(EyeOutline) }) });
        }
    }
];
</script>

<template>
    <div>
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-4 gap-3">
            <div>
                <h3 class="text-base sm:text-lg font-bold text-gray-800">Pago</h3>
                <p class="text-xs sm:text-sm text-gray-500">Gestión operativa</p>
            </div>
            <n-button 
                v-if="hasPermission('service_orders.create')"
                type="primary" 
                round 
                size="small" 
                class="w-full sm:w-auto" 
                @click="createServiceOrder"
            >
                <template #icon><n-icon><AddOutline /></n-icon></template>
                Nueva Orden
            </n-button>
        </div>

        <div class="-mx-4 px-4 sm:mx-0 sm:px-0 overflow-x-auto">
            <div class="min-w-[500px] sm:min-w-full">
                <n-data-table
                    :columns="serviceColumns"
                    :data="client.service_orders"
                    :bordered="false"
                    size="small"
                    :pagination="{ pageSize: 10 }"
                    class="mb-2"
                />
            </div>
        </div>
    </div>
</template>