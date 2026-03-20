<script setup>
import { h } from 'vue';
import { usePermissions } from '@/Composables/usePermissions';
import { useSecureFile } from '@/Composables/useSecureFile';
import { router, Link } from '@inertiajs/vue3';
import { NButton, NIcon, NDataTable, NTooltip, createDiscreteApi } from 'naive-ui';
import { TrashOutline, AttachOutline, CashOutline, CheckmarkCircleOutline } from '@vicons/ionicons5';

const props = defineProps({
    client: Object,
    stats: Object
});

const emit = defineEmits(['open-payment']);

const { hasPermission } = usePermissions();
const { openFileWithRetry } = useSecureFile();
const { dialog, notification } = createDiscreteApi(['dialog', 'notification']);

const formatCurrency = (amount) => new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(amount);
const formatDate = (dateString) => {
    if (!dateString) return '-';
    return new Date(dateString).toLocaleDateString('es-MX', { year: 'numeric', month: 'short', day: 'numeric' });
};

const handleDeletePayment = (payment) => {
    dialog.warning({
        title: 'Eliminar Abono',
        content: `¿Estás seguro de que deseas eliminar el abono de ${formatCurrency(payment.amount)}? Esta acción ajustará el saldo pendiente.`,
        positiveText: 'Eliminar',
        negativeText: 'Cancelar',
        onPositiveClick: () => {
            router.delete(route('payments.destroy', payment.id), {
                preserveScroll: true,
                onSuccess: () => notification.success({ title: 'Éxito', content: 'Abono eliminado correctamente.', duration: 3000 }),
                onError: () => notification.error({ title: 'Error', content: 'No se pudo eliminar el abono.', duration: 3000 })
            });
        }
    });
};

const paymentColumns = [
    { title: 'Fecha', key: 'payment_date', width: 125, render: (row) => formatDate(row.payment_date) },
    { 
        title: 'Concepto', 
        key: 'service_order_id',
        minWidth: 100,
        render(row) {
            if (row.service_order) {
                return h('div', { class: 'flex flex-col leading-tight' }, [
                    h('span', { class: 'text-[10px] text-gray-500' }, 'OS:'),
                    h(Link, { 
                        href: route('service-orders.show', row.service_order.id),
                        class: 'font-bold text-indigo-600 hover:underline cursor-pointer text-xs' 
                    }, { default: () => `#${row.service_order.id}` })
                ]);
            }
            return h('span', { class: 'text-gray-400 italic text-xs' }, 'Saldo General');
        }
    },
    { title: 'Notas', key: 'notes', width: 200, render: (row) => h('span', { class: 'text-xs' }, row.notes) },
    { title: 'Método', key: 'method', width: 120, render: (row) => h('span', { class: 'text-xs' }, row.method) },
    { 
        title: 'Monto', 
        key: 'amount', 
        align: 'right', 
        width: 150,
        render: (row) => h('span', { class: 'font-bold text-emerald-600 text-xs' }, formatCurrency(row.amount)) 
    },
    {
        title: 'Comp.',
        key: 'receipt',
        width: 100,
        align: 'center',
        render(row) {
            const url = row.receipt_url || (row.media && row.media[0]?.original_url);
            if (url) {
                return h(NTooltip, { trigger: 'hover' }, {
                    trigger: () => h(NButton, {
                        circle: true,
                        size: 'small',
                        quaternary: true,
                        type: 'info',
                        // Integración de useSecureFile
                        onClick: () => openFileWithRetry(url)
                    }, { icon: () => h(NIcon, null, { default: () => h(AttachOutline) }) }),
                    default: () => 'Ver Comprobante'
                });
            }
            return null;
        }
    },
    {
        title: '',
        key: 'actions',
        width: 60,
        align: 'center',
        render(row) {
            if (!hasPermission('collection.delete')) return null;
            return h(NTooltip, { trigger: 'hover' }, {
                trigger: () => h(NButton, {
                    circle: true, size: 'small', quaternary: true, type: 'error',
                    onClick: () => handleDeletePayment(row)
                }, { icon: () => h(NIcon, null, { default: () => h(TrashOutline) }) }),
                default: () => 'Eliminar Abono'
            });
        }
    }
];
</script>

<template>
    <div>
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-4 gap-3">
            <div>
                <h3 class="text-base sm:text-lg font-bold text-gray-800">Cobranza</h3>
                <p class="text-xs sm:text-sm text-gray-500">Abonos y pagos</p>
            </div>
            <n-button v-if="hasPermission('collection.create')" type="success" secondary round size="small" class="w-full sm:w-auto" @click="emit('open-payment')" :disabled="stats.balance <= 0">
                <template #icon><n-icon><CashOutline /></n-icon></template>
                Registrar Abono
            </n-button>
        </div>

        <div class="flex justify-between items-center mb-4 bg-gray-50 p-3 rounded-lg border border-gray-100">
            <span class="text-xs sm:text-sm text-gray-500 flex items-center gap-2">
                <n-icon class="text-emerald-500"><CheckmarkCircleOutline/></n-icon>
                Pagado:
            </span>
            <span class="font-bold text-gray-800 text-lg sm:text-xl">{{ formatCurrency(stats.total_paid) }}</span>
        </div>

        <div class="-mx-4 px-4 sm:mx-0 sm:px-0 overflow-x-auto pb-2">
            <div class="min-w-[600px] sm:min-w-full">
                <n-data-table
                    :columns="paymentColumns"
                    :data="client.payments"
                    :bordered="false"
                    size="small"
                    :pagination="{ pageSize: 10 }"
                />
            </div>
        </div>
    </div>
</template>