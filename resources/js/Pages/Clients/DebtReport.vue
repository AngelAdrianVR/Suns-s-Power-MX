<script setup>
import { ref, computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { NButton, NIcon, NTag, NDivider } from 'naive-ui';
import { PrintOutline, DownloadOutline, AlertCircleOutline } from '@vicons/ionicons5';

const props = defineProps({
    reportData: Array,
    generatedAt: String,
});

const formatCurrency = (amount) => {
    if (!amount && amount !== 0) return '$0.00';
    return new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(amount);
};

const formatDate = (dateString) => {
    if (!dateString) return '-';
    return new Date(dateString + 'T00:00:00').toLocaleDateString('es-MX', { year: 'numeric', month: 'short', day: 'numeric' });
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
    <!-- <AppLayout title="Reporte de Cartera de Deuda"> -->
        <div class="py-8 min-h-screen bg-gray-50 print:bg-white">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

                <!-- Barra de acciones (oculta en impresión) -->
                <div class="flex justify-between items-center mb-6 print:hidden">
                    <div>
                        <h2 class="font-bold text-2xl text-gray-800">Reporte de Cartera de Deuda</h2>
                        <p class="text-sm text-gray-500 mt-1">Generado el {{ generatedAt }}</p>
                    </div>
                    <n-button type="primary" size="large" @click="printReport" class="shadow-md">
                        <template #icon><n-icon><PrintOutline /></n-icon></template>
                        Imprimir Reporte
                    </n-button>
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

                    <!-- Tabla del Reporte -->
                    <div class="p-4 sm:p-6 overflow-x-auto">
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
                                            <div>
                                                <p class="font-bold text-gray-800">{{ client.name }}</p>
                                                <p class="text-xs text-gray-400" v-if="client.phone">{{ client.phone }}</p>
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
    .n-button, .n-layout-sider, header, nav { display: none !important; }
    body { background: white !important; }
    table { font-size: 10px; }
}
</style>
