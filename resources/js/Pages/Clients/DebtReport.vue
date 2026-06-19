<script setup>
import { ref, computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { NButton, NIcon, NTag } from 'naive-ui';
import { PrintOutline, DownloadOutline, AlertCircleOutline, ArrowBackOutline, ChevronDownOutline, ChevronForwardOutline, CheckmarkCircleOutline } from '@vicons/ionicons5';

const props = defineProps({
    reportData: Array,
    monthlyProjection: Array,
    grandTotalProjected: Number,
    grandTotalReceived: Number,
    generatedAt: String,
});

const expandedClients = ref(new Set());
const allExpanded = ref(false);

const toggleClientInstallments = (clientId) => {
    const s = new Set(expandedClients.value);
    if (s.has(clientId)) {
        s.delete(clientId);
    } else {
        s.add(clientId);
    }
    expandedClients.value = s;
    allExpanded.value = false;
};

const toggleAllInstallments = () => {
    allExpanded.value = !allExpanded.value;
    if (allExpanded.value) {
        const allIds = props.reportData.map(c => c.id);
        expandedClients.value = new Set(allIds);
    } else {
        expandedClients.value = new Set();
    }
};

const formatCurrency = (amount) => {
    if (!amount && amount !== 0) return '$0.00';
    return new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(amount);
};

const monthNames = [
    'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
    'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'
];

const formatDate = (dateString) => {
    if (!dateString) return '-';
    const d = new Date(dateString + 'T12:00:00');
    const day = d.getDate();
    const month = monthNames[d.getMonth()];
    const year = d.getFullYear();
    return `${day} ${month} ${year}`;
};

const formatMonthLabel = (label) => {
    // Capitalize first letter of Spanish month
    return label.charAt(0).toUpperCase() + label.slice(1);
};

const paymentPlanLabel = (method) => {
    const map = {
        'Contado': 'Contado', '3 MSI': '3 Meses', '6 MSI': '6 Meses',
        '9 MSI': '9 Meses', '12 MSI': '12 Meses', 'Personalizado': 'Personalizado'
    };
    return map[method] || method || '-';
};

const grandTotal = computed(() => {
    return props.reportData.reduce((sum, c) => sum + (c.balance || 0), 0);
});

const totalClients = computed(() => props.reportData.length);

const totalOrders = computed(() => {
    return props.reportData.reduce((sum, c) => sum + (c.orders?.length || 0), 0);
});

const printReport = () => {
    window.print();
};
</script>

<template>
    <div class="py-8 min-h-screen bg-gray-50 print:bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

            <!-- Barra superior -->
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6 print:hidden">
                <div>
                    <h2 class="font-bold text-2xl text-gray-800">Reporte de Cartera de Deuda</h2>
                    <p class="text-sm text-gray-500 mt-1">Generado el {{ generatedAt }} &mdash; {{ totalClients }} clientes, {{ totalOrders }} órdenes</p>
                </div>
                <div class="flex gap-3">
                    <Link :href="route('clients.index')">
                        <n-button size="large" round>
                            <template #icon><n-icon><ArrowBackOutline /></n-icon></template>
                            Volver
                        </n-button>
                    </Link>
                    <n-button type="primary" size="large" @click="printReport" round class="shadow-md">
                        <template #icon><n-icon><PrintOutline /></n-icon></template>
                        Imprimir
                    </n-button>
                </div>
            </div>

                <!-- CONTENIDO IMPRIMIBLE -->
                <div class="bg-white rounded-3xl shadow-lg border border-gray-100 overflow-hidden print:shadow-none print:border-none print:rounded-none">
                    
                    <!-- Encabezado del Reporte -->
                    <div class="p-6 sm:p-10 border-b border-gray-100">
                        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                            <div class="flex items-center gap-4">
                                <!-- Logo placeholder -->
                                <div class="relative group cursor-pointer">
                                    <div class="absolute -inset-1 bg-gradient-to-r from-blue-300 to-purple-300 rounded-full blur opacity-20 group-hover:opacity-40 transition duration-500"></div>
                                    <figure class="relative size-14 bg-white rounded-2xl shadow-sm border border-gray-100 flex items-center justify-center overflow-hidden">
                                        <img src="@/../../public/images/isologo-suns-power-mx.png" onerror="this.src='https://ui-avatars.com/api/?name=Solar+ERP&background=0D8ABC&color=fff&rounded=true&font-size=0.4'" alt="Logo" class="w-full h-full object-cover" />
                                    </figure>
                                </div>
                                <h1 class="mt-2 font-bold text-[15px] tracking-widest uppercase text-blue-900">Sun's Power MX</h1>
                            </div>
                            <div class="text-right">
                                <p class="text-xs text-gray-400">Generado el</p>
                                <p class="text-sm font-bold text-gray-700">{{ generatedAt }}</p>
                            </div>
                        </div>

                        <!-- Resumen -->
                        <div class="grid grid-cols-3 gap-4 mt-6">
                            <div class="bg-indigo-50 rounded-xl p-4 text-center">
                                <p class="text-2xl font-black text-indigo-600">{{ totalClients }}</p>
                                <p class="text-xs text-indigo-400 font-bold uppercase">Clientes con Deuda</p>
                            </div>
                            <div class="bg-amber-50 rounded-xl p-4 text-center">
                                <p class="text-2xl font-black text-amber-600">{{ totalOrders }}</p>
                                <p class="text-xs text-amber-400 font-bold uppercase">Órdenes Pendientes</p>
                            </div>
                            <div class="bg-red-50 rounded-xl p-4 text-center">
                                <p class="text-2xl font-black text-red-600">{{ formatCurrency(grandTotal) }}</p>
                                <p class="text-xs text-red-400 font-bold uppercase">Saldo Total Vencido</p>
                            </div>
                        </div>
                    </div>

                    <!-- Proyección Mensual -->
                    <div v-if="monthlyProjection?.length" class="px-4 sm:px-10 py-6 border-b border-gray-100">
                        <div class="flex items-center justify-between mb-4">
                            <div class="flex items-center gap-2">
                                <div class="w-2 h-2 rounded-full bg-emerald-500"></div>
                                <h3 class="text-sm font-bold text-gray-700 uppercase tracking-wider">Proyección Mensual</h3>
                                <span class="text-xs text-gray-400 ml-1">— Ingreso estimado próximos 12 meses</span>
                            </div>
                            <!-- <div class="flex items-center gap-4 text-xs">
                                <span class="text-gray-800 font-bold">Total a recibir: <span class="text-gray-900 text-sm">{{ formatCurrency(grandTotalProjected) }}</span></span>
                                <span class="text-gray-300">|</span>
                                <span class="text-green-700 font-bold">Total recibido: <span class="text-green-600 text-sm">{{ formatCurrency(grandTotalReceived) }}</span></span>
                            </div> -->
                        </div>
                        <div class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-6 lg:grid-cols-6 gap-2">
                            <div
                                v-for="m in monthlyProjection"
                                :key="`${m.month}-${m.year}`"
                                class="relative bg-gray-50 rounded-xl p-3 border border-gray-100 hover:border-emerald-200 hover:shadow-sm transition-all"
                            >
                                <div class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">
                                    {{ formatMonthLabel(m.label) }}
                                </div>
                                <div class="text-sm font-black" :class="m.total > 0 ? 'text-gray-800' : 'text-gray-300'">
                                    {{ formatCurrency(m.total) }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tabla del Reporte -->
                    <div class="p-4 sm:p-6 overflow-x-auto">
                        <div class="flex items-center justify-between mb-3">
                            <span class="text-xs text-gray-400 font-semibold uppercase tracking-wider">Detalle de órdenes</span>
                            <n-button
                                v-if="reportData.some(c => c.orders?.some(o => o.installments?.length))"
                                text
                                size="tiny"
                                @click="toggleAllInstallments"
                                class="text-gray-400 hover:text-gray-600"
                            >
                                <n-icon :component="allExpanded ? ChevronDownOutline : ChevronForwardOutline" size="14" class="mr-1" />
                                <span class="text-[11px]">{{ allExpanded ? 'Ocultar cuotas' : 'Ver cuotas de todos' }}</span>
                            </n-button>
                        </div>
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b-2 border-gray-200 text-left">
                                    <th class="py-3 px-3 text-xs font-bold text-gray-400 uppercase tracking-wider">Cliente</th>
                                    <th class="py-3 px-3 text-xs font-bold text-gray-400 uppercase tracking-wider">RFC</th>
                                    <th class="py-3 px-3 text-xs font-bold text-gray-400 uppercase tracking-wider">Orden</th>
                                    <th class="py-3 px-3 text-xs font-bold text-gray-400 uppercase tracking-wider">Estado</th>
                                    <th class="py-3 px-3 text-xs font-bold text-gray-400 uppercase tracking-wider">Plan de Pago</th>
                                    <th class="py-3 px-3 text-xs font-bold text-gray-400 uppercase tracking-wider text-right">Total</th>
                                    <th class="py-3 px-3 text-xs font-bold text-gray-400 uppercase tracking-wider text-right">Pagado</th>
                                    <th class="py-3 px-3 text-xs font-bold text-gray-400 uppercase tracking-wider text-right">Saldo</th>
                                    <th class="py-3 px-3 text-xs font-bold text-gray-400 uppercase tracking-wider">Límite</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template v-for="client in reportData" :key="client.id">
                                    <tr 
                                        v-for="(order, oIdx) in client.orders" 
                                        :key="order.id"
                                        class="border-b border-gray-50 hover:bg-gray-50/50 transition-colors"
                                        :class="{ 'bg-red-50/30': order.is_overdue }"
                                    >
                                        <td class="py-3 px-3" v-if="oIdx === 0" :rowspan="client.orders.length">
                                            <div class="flex items-center gap-1.5">
                                                <n-button
                                                    v-if="client.orders?.some(o => o.installments?.length)"
                                                    text
                                                    size="tiny"
                                                    class="!p-0"
                                                    @click.stop="toggleClientInstallments(client.id)"
                                                >
                                                    <n-icon :component="expandedClients.has(client.id) ? ChevronDownOutline : ChevronForwardOutline" size="14" class="text-gray-400" />
                                                </n-button>
                                                <div>
                                                    <p class="font-bold text-gray-800 text-sm">{{ client.name }}</p>
                                                    <p class="text-xs text-gray-400" v-if="client.phone">{{ client.phone }}</p>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="py-3 px-3 text-xs text-gray-500 font-mono" v-if="oIdx === 0" :rowspan="client.orders.length">
                                            {{ client.tax_id || '-' }}
                                        </td>
                                        <td class="py-3 px-3">
                                            <span class="font-mono text-xs text-indigo-600 font-bold">#{{ order.id }}</span>
                                        </td>
                                        <td class="py-3 px-3">
                                            <n-tag :type="order.status === 'Aceptado' ? 'info' : order.status === 'En Proceso' ? 'warning' : 'default'" size="tiny" round :bordered="false">
                                                {{ order.status }}
                                            </n-tag>
                                        </td>
                                        <td class="py-3 px-3">
                                            <span class="text-xs">{{ paymentPlanLabel(order.payment_method) }}</span>
                                        </td>
                                        <td class="py-3 px-3 text-right text-xs font-mono">{{ formatCurrency(order.total_amount) }}</td>
                                        <td class="py-3 px-3 text-right text-xs font-mono text-green-600">{{ formatCurrency(order.paid) }}</td>
                                        <td class="py-3 px-3 text-right">
                                            <span class="text-xs font-bold" :class="order.is_overdue ? 'text-red-600' : 'text-amber-600'">
                                                {{ formatCurrency(order.remaining) }}
                                            </span>
                                        </td>
                                        <td class="py-3 px-3">
                                            <div v-if="order.liquidation_deadline" class="flex items-center gap-1">
                                                <n-icon v-if="order.is_overdue" :component="AlertCircleOutline" class="text-red-500" size="14" />
                                                <span class="text-xs" :class="order.is_overdue ? 'text-red-600 font-bold' : 'text-gray-500'">
                                                    {{ formatDate(order.liquidation_deadline) }}
                                                </span>
                                            </div>
                                            <span v-else class="text-xs text-gray-400">-</span>
                                        </td>
                                    </tr>
                                    <!-- Subtotal por cliente -->
                                    <tr class="bg-gray-50/80 border-b border-gray-200">
                                        <td colspan="5" class="py-2 px-3 text-right text-xs font-bold text-gray-500 uppercase">
                                            Subtotal {{ client.name }}
                                        </td>
                                        <td class="py-2 px-3 text-right text-xs font-bold text-gray-700">{{ formatCurrency(client.total_debt) }}</td>
                                        <td class="py-2 px-3 text-right text-xs font-bold text-green-600">{{ formatCurrency(client.total_paid) }}</td>
                                        <td class="py-2 px-3 text-right text-xs font-bold text-red-600">{{ formatCurrency(client.balance) }}</td>
                                        <td></td>
                                    </tr>
                                    <!-- Cuotas proyectadas del cliente (expandible) -->
                                    <tr v-if="expandedClients.has(client.id) && client.orders?.some(o => o.installments?.length)">
                                        <td colspan="9" class="p-0">
                                            <div class="bg-emerald-50/40 border-b border-emerald-100">
                                                <div class="py-3 px-6">
                                                    <div class="flex items-center gap-2 mb-2">
                                                        <div class="w-1.5 h-1.5 rounded-full bg-emerald-500"></div>
                                                        <span class="text-[11px] font-bold text-gray-500 uppercase tracking-wider">Cuotas Proyectadas</span>
                                                    </div>
                                                    <div class="grid gap-2">
                                                        <template v-for="order in client.orders" :key="'inst-' + order.id">
                                                        <div v-if="order.installments?.length">
                                                            <div class="text-xs text-gray-400 font-semibold mb-1.5 ml-1">
                                                                OS #{{ order.id }} · {{ paymentPlanLabel(order.payment_method) }}
                                                            </div>
                                                            <div class="flex flex-wrap gap-1.5">
                                                                <div
                                                                    v-for="inst in order.installments"
                                                                    :key="inst.installment"
                                                                    class="inline-flex items-center gap-1.5 bg-white rounded-lg border px-2.5 py-1.5 text-xs"
                                                                    :class="inst.is_paid
                                                                        ? 'border-emerald-300 bg-emerald-50'
                                                                        : inst.is_past
                                                                            ? 'border-gray-200 opacity-60'
                                                                            : 'border-emerald-200 bg-emerald-50/60'"
                                                                >
                                                                    <span v-if="inst.is_paid" class="text-emerald-600">
                                                                        <n-icon :component="CheckmarkCircleOutline" size="14" />
                                                                    </span>
                                                                    <span class="text-gray-400 font-medium">#{{ inst.installment }}</span>
                                                                    <span class="text-gray-700">{{ formatDate(inst.projected_date) }}</span>
                                                                    <span class="font-bold" :class="inst.is_paid ? 'text-emerald-700' : 'text-gray-800'">{{ formatCurrency(inst.amount) }}</span>
                                                                    <span
                                                                        v-if="inst.is_paid"
                                                                        class="text-[10px] text-emerald-600 font-semibold"
                                                                    >Pagado</span>
                                                                    <span
                                                                        v-else-if="inst.is_past"
                                                                        class="text-[10px] text-gray-400 italic"
                                                                    >Pendiente</span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        </template>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                </template>

                                <!-- Gran Total -->
                                <tr class="bg-indigo-50 border-t-2 border-indigo-200">
                                    <td colspan="5" class="py-3 px-3 text-right text-sm font-black text-indigo-800 uppercase">
                                        Total General
                                    </td>
                                    <td colspan="3" class="py-3 px-3 text-right">
                                        <span class="text-lg font-black text-indigo-600">{{ formatCurrency(grandTotal) }}</span>
                                    </td>
                                    <td></td>
                                </tr>
                            </tbody>
                        </table>

                        <n-empty v-if="!reportData.length" description="No hay clientes con saldo pendiente." class="py-16" />
                    </div>

                    <!-- Pie del reporte -->
                    <div class="p-6 border-t border-gray-100 text-center text-xs text-gray-400">
                        Sun's Power MX — Reporte generado automáticamente el {{ generatedAt }}
                    </div>
                </div>
            </div>
        </div>
    <!-- </AppLayout> -->
</template>

<style scoped>
@media print {
    @page {
        margin: 8mm;
    }
    .n-button, .n-layout-sider, header, nav { display: none !important; }
    body { background: white !important; padding: 0 !important; }
    .min-h-screen { min-height: auto !important; padding-top: 0 !important; }
    .max-w-7xl { max-width: 100% !important; padding: 0 !important; }
    table { font-size: 9px; }
    .px-4\ sm\:px-10 { padding-left: 0.5rem !important; padding-right: 0.5rem !important; }
    .p-4\ sm\:p-6 { padding: 0.5rem !important; }
    .p-6 { padding: 0.75rem !important; }
    .py-3 { padding-top: 0.3rem !important; padding-bottom: 0.3rem !important; }
    .px-3 { padding-left: 0.4rem !important; padding-right: 0.4rem !important; }
}
</style>
