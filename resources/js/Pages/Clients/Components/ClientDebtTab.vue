<script setup>
import { ref, computed, h } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import axios from 'axios';
import {
    NDataTable, NTag, NIcon, NButton, NModal, NCard, NTimeline,
    NTimelineItem, NSelect, NBadge, NAlert, NEmpty, NSpin,
    NPagination, createDiscreteApi, NTooltip, NDivider
} from 'naive-ui';
import {
    EyeOutline, CashOutline, AlertCircleOutline,
    CheckmarkCircleOutline, TimeOutline, CloseCircleOutline,
    CalendarOutline, PersonOutline, SearchOutline,
    DownloadOutline, CloseOutline
} from '@vicons/ionicons5';

const { notification } = createDiscreteApi(['notification']);

// --- Estado ---
const loading = ref(false);
const reportData = ref([]);
const pagination = ref({ current_page: 1, last_page: 1, total: 0 });
const fetchError = ref(null);
const currentPage = ref(1);
const monthlyProjection = ref([]);
const grandTotalProjected = ref(0);
const grandTotalReceived = ref(0);

// --- Filtros ---
const searchFilter = ref('');
const paymentMethodFilter = ref(null);
const delinquencyFilter = ref(null);

let searchTimeout = null;
const handleSearchInput = () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => fetchReport(1), 350);
};

const clearSearch = () => {
    searchFilter.value = '';
    fetchReport(1);
};

const paymentMethodOptions = [
    { label: 'Todos los planes', value: null },
    { label: 'Contado', value: 'Contado' },
    { label: '3 MSI', value: '3 MSI' },
    { label: '6 MSI', value: '6 MSI' },
    { label: '9 MSI', value: '9 MSI' },
    { label: '12 MSI', value: '12 MSI' },
    { label: 'Personalizado', value: 'Personalizado' },
];

const delinquencyOptions = [
    { label: 'Todos', value: null },
    { label: 'Extemporáneo (6-10 días)', value: 'late' },
    { label: 'Incumplido (11+ días)', value: 'defaulted' },
];

// --- Cargar datos ---
const fetchReport = async (page = 1) => {
    loading.value = true;
    fetchError.value = null;
    try {
        const response = await axios.get(route('api.clients.debt-report'), {
            params: {
                page,
                search: searchFilter.value || undefined,
                payment_method: paymentMethodFilter.value || undefined,
                delinquency: delinquencyFilter.value || undefined,
            }
        });
        reportData.value = response.data.reportData || [];
        pagination.value = response.data.pagination || { current_page: 1, last_page: 1, total: 0 };
        monthlyProjection.value = response.data.monthlyProjection || [];
        grandTotalProjected.value = response.data.grandTotalProjected || 0;
        grandTotalReceived.value = response.data.grandTotalReceived || 0;
        currentPage.value = page;
    } catch (error) {
        fetchError.value = 'No se pudo cargar el reporte de cartera.';
    } finally {
        loading.value = false;
    }
};

fetchReport();

// Refetch al cambiar filtros (vuelve a página 1)
const handleFilterChange = () => fetchReport(1);

const handlePageChange = (page) => fetchReport(page);

// --- Los filtros ahora son server-side, la data ya viene filtrada ---

// --- Modal de proyección con timeline ---
const showProjectionModal = ref(false);
const projectionClient = ref(null);
const loadingProjections = ref(false);
const projectionOrders = ref([]); // Array de órdenes con sus installments cargados

const paymentStatusStyle = (status) => {
    const map = {
        on_time: { color: '#10b981', bg: '#d1fae5', label: 'A tiempo' },
        late: { color: '#f59e0b', bg: '#fef3c7', label: 'Extemporáneo' },
        defaulted: { color: '#ef4444', bg: '#fee2e2', label: 'Incumplido' },
        pending: { color: '#6b7280', bg: '#f3f4f6', label: 'Pendiente' },
        upcoming: { color: '#3b82f6', bg: '#dbeafe', label: 'Próximo' },
        paid: { color: '#10b981', bg: '#d1fae5', label: 'Pagado' },
    };
    return map[status] || map.pending;
};

const openProjection = async (client) => {
    projectionClient.value = client;
    showProjectionModal.value = true;
    loadingProjections.value = true;
    projectionOrders.value = [];

    try {
        const orders = client.orders || [];
        const results = await Promise.allSettled(
            orders.map(order =>
                axios.get(route('api.service-orders.installments', order.id))
                    .then(res => ({ ...order, installments: res.data.installments || [], unmatched: res.data.unmatched_payments || [] }))
            )
        );
        projectionOrders.value = results
            .filter(r => r.status === 'fulfilled')
            .map(r => r.value);
    } catch (error) {
        notification.error({ title: 'Error', content: 'No se pudieron cargar las proyecciones.' });
    } finally {
        loadingProjections.value = false;
    }
};

const goToShow = (id) => router.visit(route('clients.show', id));

const downloadReport = () => router.visit(route('clients.debt-report'));

// --- Utilidades ---
const formatCurrency = (amount) =>
    new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(amount || 0);

const formatDate = (date) => {
    if (!date) return '-';
    return new Date(date + 'T12:00:00').toLocaleDateString('es-MX', {
        year: 'numeric', month: 'short', day: 'numeric'
    });
};

const formatMonthLabel = (label) => {
    return label.charAt(0).toUpperCase() + label.slice(1);
};

const paymentPlanLabel = (method) => {
    const map = {
        'Contado': 'Contado', '3 MSI': '3 Meses', '6 MSI': '6 Meses',
        '9 MSI': '9 Meses', '12 MSI': '12 Meses', 'Personalizado': 'Personalizado'
    };
    return map[method] || method || 'Sin plan';
};

const getDelinquencyColor = (days) => {
    if (days === null || days === undefined || days < 0) return 'default';
    if (days >= 11) return 'error';
    if (days >= 6) return 'warning';
    return 'success';
};

const getDelinquencyLabel = (days) => {
    if (days === null || days === undefined || days < 0) return 'Al corriente';
    if (days >= 11) return `Incumplido (${days} días)`;
    if (days >= 6) return `Extemporáneo (${days} días)`;
    return 'Al corriente';
};

// --- Columnas ---
const columns = [
    {
        title: 'Cliente',
        key: 'name',
        width: 200,
        render(row) {
            return h('div', { class: 'flex flex-col' }, [
                h('span', { class: 'font-bold text-gray-800 text-sm' }, row.name),
                row.tax_id ? h('span', { class: 'text-xs text-gray-400 font-mono' }, `RFC: ${row.tax_id}`) : null,
            ]);
        }
    },
    {
        title: 'Saldo',
        key: 'balance',
        width: 140,
        align: 'right',
        render(row) {
            return h('span', { class: 'font-bold text-red-600' }, formatCurrency(row.balance));
        }
    },
    {
        title: 'Plan',
        key: 'payment_method',
        width: 110,
        render(row) {
            const method = row.orders?.[0]?.payment_method;
            return h(NTag, { type: method ? 'info' : 'default', size: 'small', round: true, bordered: false },
                { default: () => paymentPlanLabel(method) });
        }
    },
    {
        title: 'Estado',
        key: 'delinquency',
        width: 150,
        render(row) {
            const maxDays = Math.max(...(row.orders || []).map(o => o.days_since_projected || -1));
            return h(NTag, {
                type: getDelinquencyColor(maxDays),
                size: 'small', round: true, bordered: false
            }, { default: () => getDelinquencyLabel(maxDays) });
        }
    },
    {
        title: '',
        key: 'actions',
        width: 100,
        render(row) {
            return h('div', { class: 'flex gap-1' }, [
                h(NTooltip, { trigger: 'hover' }, {
                    trigger: () => h(NButton, {
                        circle: true, size: 'small', quaternary: true, type: 'info',
                        onClick: (e) => { e.stopPropagation(); openProjection(row); }
                    }, { icon: () => h(NIcon, null, { default: () => h(CalendarOutline) }) }),
                    default: () => 'Ver proyección'
                }),
                h(NTooltip, { trigger: 'hover' }, {
                    trigger: () => h(NButton, {
                        circle: true, size: 'small', quaternary: true, type: 'primary',
                        onClick: (e) => { e.stopPropagation(); goToShow(row.id); }
                    }, { icon: () => h(NIcon, null, { default: () => h(EyeOutline) }) }),
                    default: () => 'Ver detalles'
                }),
            ]);
        }
    }
];
</script>

<template>
    <div>
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-4 gap-3">
            <div>
                <h3 class="text-base sm:text-lg font-bold text-gray-800">Cartera de Deuda</h3>
                <p class="text-xs sm:text-sm text-gray-500">Clientes con saldos pendientes y estado de pagos</p>
            </div>
            <div class="flex gap-2 flex-wrap items-center">
                <div class="relative">
                    <input
                        v-model="searchFilter"
                        type="text"
                        placeholder="Buscar cliente..."
                        class="w-44 pl-7 pr-7 py-1.5 text-xs border border-gray-200 rounded-lg focus:border-amber-400 focus:ring-1 focus:ring-amber-400 outline-none transition-all"
                        @input="handleSearchInput"
                    />
                    <n-icon size="14" class="absolute left-2 top-1/2 -translate-y-1/2 text-gray-400"><SearchOutline /></n-icon>
                    <n-icon
                        v-if="searchFilter"
                        size="14"
                        class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 cursor-pointer hover:text-gray-600"
                        @click="clearSearch"
                    ><CloseOutline /></n-icon>
                </div>
                <n-select
                    v-model:value="paymentMethodFilter"
                    :options="paymentMethodOptions"
                    placeholder="Plan de pago"
                    size="small"
                    clearable
                    class="w-40"
                    @update:value="handleFilterChange"
                />
                <n-select
                    v-model:value="delinquencyFilter"
                    :options="delinquencyOptions"
                    placeholder="Mora"
                    size="small"
                    clearable
                    class="w-48"
                    @update:value="handleFilterChange"
                />
                <n-button size="small" secondary round type="warning" @click="downloadReport">
                    <template #icon><n-icon><DownloadOutline /></n-icon></template>
                    Descargar reporte
                </n-button>
            </div>
        </div>

        <div v-if="loading" class="py-12 flex justify-center">
            <n-spin size="medium" />
        </div>

        <n-alert v-else-if="fetchError" type="error" :title="fetchError" class="mb-4" />

        <!-- Proyección Mensual -->
        <div v-if="!loading && !fetchError && monthlyProjection.length" class="mb-4 bg-white rounded-xl border border-emerald-100 p-4">
            <div class="flex items-center justify-between mb-3">
                <div class="flex items-center gap-2">
                    <div class="w-2 h-2 rounded-full bg-emerald-500"></div>
                    <h4 class="text-xs font-bold text-gray-700 uppercase tracking-wider">Proyección Mensual</h4>
                    <span class="text-[10px] text-gray-400 ml-1">— Próximos 12 meses</span>
                </div>
                <!-- <div class="flex items-center gap-3 text-[10px]">
                    <span class="text-gray-700 font-bold">A recibir: <span class="text-gray-900 text-xs">{{ formatCurrency(grandTotalProjected) }}</span></span>
                    <span class="text-gray-300">|</span>
                    <span class="text-green-700 font-bold">Recibido: <span class="text-green-600 text-xs">{{ formatCurrency(grandTotalReceived) }}</span></span>
                </div> -->
            </div>
            <div class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-6 gap-1.5">
                <div
                    v-for="m in monthlyProjection"
                    :key="`${m.month}-${m.year}`"
                    class="bg-gray-50 rounded-lg p-2 border border-gray-100"
                >
                    <div class="text-[9px] font-bold text-gray-400 uppercase tracking-wider mb-0.5">
                        {{ formatMonthLabel(m.label) }}
                    </div>
                    <div class="text-xs font-black" :class="m.total > 0 ? 'text-gray-800' : 'text-gray-300'">
                        {{ formatCurrency(m.total) }}
                    </div>
                </div>
            </div>
        </div>

        <div v-if="!loading && !fetchError" class="-mx-4 px-4 sm:mx-0 sm:px-0 overflow-x-auto">
            <div class="min-w-[700px] sm:min-w-full">
                <n-data-table
                    :columns="columns"
                    :data="reportData"
                    :bordered="false"
                    size="small"
                    :row-props="(row) => ({ style: 'cursor: pointer;', onClick: () => goToShow(row.id) })"
                />
                <n-empty v-if="reportData.length === 0 && !loading" description="Sin resultados con los filtros actuales" class="py-8" />

                <div class="flex justify-center mt-4" v-if="pagination.total > 30 && reportData.length > 0">
                    <n-pagination
                        :page="currentPage"
                        :page-count="pagination.last_page"
                        :on-update:page="handlePageChange"
                    />
                </div>
            </div>
        </div>

        <!-- Modal de Proyección de Pagos con Timeline -->
        <n-modal v-model:show="showProjectionModal">
            <n-card
                style="width: 700px; max-height: 85vh; overflow-y: auto;"
                :title="projectionClient?.name || 'Proyección de Pagos'"
                :bordered="false"
                size="small"
                closable
                @close="showProjectionModal = false"
            >
                <div v-if="loadingProjections" class="py-12 flex justify-center">
                    <n-spin size="medium" />
                </div>

                <div v-else-if="projectionOrders.length === 0" class="py-8">
                    <n-empty description="No se pudieron cargar las proyecciones." />
                </div>

                <div v-else class="space-y-6">
                    <n-alert type="info" :bordered="false">
                        <template #header>
                            Saldo total: <strong>{{ formatCurrency(projectionClient?.balance) }}</strong>
                            &mdash; {{ projectionOrders.length }} orden(es) con deuda
                        </template>
                    </n-alert>

                    <div v-for="order in projectionOrders" :key="order.id"
                         class="border rounded-xl p-4"
                         :class="order.is_overdue ? 'border-red-200 bg-red-50/20' : 'border-gray-200'">

                        <div class="flex justify-between items-center mb-3">
                            <div>
                                <span class="font-bold text-gray-800">OS #{{ order.id }}</span>
                                <div class="flex items-center gap-2 mt-1">
                                    <n-tag :type="order.status === 'Aceptado' ? 'info' : order.status === 'En Proceso' ? 'warning' : 'default'"
                                        size="tiny" round :bordered="false">{{ order.status }}</n-tag>
                                    <n-tag :type="order.is_overdue ? 'error' : 'success'" size="tiny" round :bordered="false">
                                        {{ order.is_overdue ? 'Vencida' : 'Vigente' }}
                                    </n-tag>
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="text-xs text-gray-400">Plan: <strong>{{ paymentPlanLabel(order.payment_method) }}</strong></div>
                                <div class="text-xs text-gray-400">Restante: <strong class="text-amber-600">{{ formatCurrency(order.remaining) }}</strong></div>
                            </div>
                        </div>

                        <!-- Timeline de cuotas (solo lectura) -->
                        <div v-if="order.installments && order.installments.length > 0" class="bg-white rounded-lg border border-gray-100 p-3">
                            <n-timeline>
                                <n-timeline-item
                                    v-for="inst in order.installments"
                                    :key="inst.id || inst.installment"
                                    :type="inst.status === 'defaulted' ? 'error' : inst.status === 'late' ? 'warning' : inst.status === 'on_time' || inst.status === 'paid' ? 'success' : 'info'"
                                >
                                    <template #header>
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <span class="font-semibold text-gray-800 text-sm">{{ inst.label }}</span>
                                            <n-tag
                                                :type="inst.status === 'defaulted' ? 'error' : inst.status === 'late' ? 'warning' : inst.status === 'on_time' || inst.status === 'paid' ? 'success' : 'info'"
                                                size="tiny" round :bordered="false"
                                            >
                                                {{ inst.status === 'paid' || inst.status === 'on_time' ? 'Pagado' : inst.status_label || inst.status }}
                                            </n-tag>
                                        </div>
                                    </template>
                                    <div class="space-y-1 text-sm">
                                        <div class="flex justify-between">
                                            <span class="text-gray-500">Fecha:</span>
                                            <span class="font-medium">{{ formatDate(inst.projected_date) }}</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-gray-500">Monto:</span>
                                            <span class="font-bold">{{ formatCurrency(inst.amount) }}</span>
                                        </div>
                                        <div v-if="inst.payment" class="flex justify-between text-emerald-600">
                                            <span>Pagado:</span>
                                            <span>{{ formatCurrency(inst.payment.amount) }} — {{ formatDate(inst.payment.date) }}</span>
                                        </div>
                                        <div v-else-if="inst.days_since_projected > 0" class="text-xs" :class="inst.status === 'defaulted' ? 'text-red-500 font-bold' : 'text-amber-500'">
                                            {{ inst.days_since_projected }} día(s) de retraso
                                        </div>
                                    </div>
                                </n-timeline-item>
                            </n-timeline>
                        </div>
                        <div v-else class="text-center py-4 text-gray-400 text-xs">
                            Sin cuotas proyectadas para esta orden.
                        </div>

                        <!-- Pagos no emparejados -->
                        <div v-if="order.unmatched && order.unmatched.length > 0" class="mt-2">
                            <n-alert type="warning" :bordered="false" class="text-xs">
                                <template #header>Pagos adicionales no programados ({{ order.unmatched.length }})</template>
                                <div v-for="up in order.unmatched" :key="up.id" class="text-xs mt-1">
                                    {{ formatCurrency(up.amount) }} — {{ formatDate(up.date) }} ({{ up.method }})
                                </div>
                            </n-alert>
                        </div>
                    </div>
                </div>

                <template #footer>
                    <div class="flex justify-end gap-3">
                        <n-button @click="showProjectionModal = false">Cerrar</n-button>
                        <n-button type="primary" @click="goToShow(projectionClient?.id); showProjectionModal = false;">
                            <template #icon><n-icon><EyeOutline /></n-icon></template>
                            Ver más detalles
                        </n-button>
                    </div>
                </template>
            </n-card>
        </n-modal>
    </div>
</template>
