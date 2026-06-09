<script setup>
import { h } from 'vue';
import { usePermissions } from '@/Composables/usePermissions';
import { router, Link } from '@inertiajs/vue3';
import { NButton, NIcon, NTag, NDataTable, NTooltip } from 'naive-ui';
import { AddOutline, EyeOutline, AlertCircleOutline, CashOutline } from '@vicons/ionicons5';

const props = defineProps({
    client: Object
});

const { hasPermission } = usePermissions();

const formatCurrency = (amount) => {
    if (!amount && amount !== 0) return '-';
    return new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(amount);
};
const formatDate = (dateString) => {
    if (!dateString) return '-';
    return new Date(dateString).toLocaleDateString('es-MX', { year: 'numeric', month: 'short', day: 'numeric' });
};

const createServiceOrder = () => {
    router.visit(route('service-orders.create', { client_id: props.client.id }));
};

const paymentPlanLabel = (method) => {
    const map = {
        'Contado': 'Contado', '3 MSI': '3 Meses', '6 MSI': '6 Meses',
        '9 MSI': '9 Meses', '12 MSI': '12 Meses', 'Personalizado': 'Personalizado'
    };
    return map[method] || method || '-';
};

const serviceColumns = [
    { title: 'Folio', key: 'id', width: 70, render: (row) => `#${row.id}` },
    { 
        title: 'Estado', 
        key: 'status',
        width: 110,
        render(row) {
            const types = { 
                'Cotización': 'default', 'Aceptado': 'info', 'En Proceso': 'warning', 
                'Completado': 'success', 'Facturado': 'success', 'Cancelado': 'error' 
            };
            return h(NTag, { type: types[row.status] || 'default', size: 'tiny', bordered: false, round: true }, { default: () => row.status });
        }
    },
    { title: 'Inicio', key: 'start_date', width: 120, render: (row) => formatDate(row.start_date) },
    { 
        title: 'Plan de Pago', 
        key: 'payment_method', 
        width: 120,
        render(row) {
            if (!row.payment_method) return h('span', { class: 'text-xs text-gray-400' }, '-');
            return h(NTag, { type: 'info', size: 'tiny', bordered: false, round: true }, { default: () => paymentPlanLabel(row.payment_method) });
        }
    },
    { title: 'Total', key: 'total_amount', width: 110, align: 'right', render: (row) => formatCurrency(row.total_amount) },
    { 
        title: 'Pagado', 
        key: 'amount_paid', 
        width: 110, 
        align: 'right',
        render(row) {
            const paid = row.amount_paid || 0;
            return h('span', { class: paid > 0 ? 'text-green-600 font-medium text-xs' : 'text-gray-400 text-xs' }, formatCurrency(paid));
        }
    },
    { 
        title: 'Restante', 
        key: 'remaining', 
        width: 110, 
        align: 'right',
        render(row) {
            const rem = row.remaining || 0;
            if (rem <= 0 && row.total_amount > 0) {
                return h(NTag, { type: 'success', size: 'tiny', bordered: false, round: true }, { default: () => 'Liquidado' });
            }
            return h('span', { class: rem > 0 ? 'text-amber-600 font-bold text-xs' : 'text-gray-400 text-xs' }, formatCurrency(rem));
        }
    },
    { 
        title: 'Límite de Pago', 
        key: 'liquidation_deadline', 
        width: 140,
        render(row) {
            if (!row.liquidation_deadline) return h('span', { class: 'text-xs text-gray-400' }, '-');
            
            const dateStr = formatDate(row.liquidation_deadline);
            if (row.is_overdue) {
                return h('div', { class: 'flex items-center gap-1' }, [
                    h(NTooltip, null, {
                        trigger: () => h(NIcon, { component: AlertCircleOutline, class: 'text-red-500', size: 16 }),
                        default: () => `Venció el ${dateStr}`
                    }),
                    h('span', { class: 'text-xs text-red-600 font-bold' }, dateStr)
                ]);
            }
            return h('span', { class: 'text-xs text-gray-600' }, dateStr);
        }
    },
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
                <h3 class="text-base sm:text-lg font-bold text-gray-800">Órdenes de Servicio</h3>
                <p class="text-xs sm:text-sm text-gray-500">Historial de servicios y planes de pago</p>
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