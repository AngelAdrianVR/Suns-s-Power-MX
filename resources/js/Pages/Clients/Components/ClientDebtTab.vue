<script setup>
import { ref, computed, h } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import axios from 'axios';
import {
    NDataTable, NTag, NIcon, NButton, NModal, NCard, NTimeline,
    NTimelineItem, NSelect, NBadge, NAlert, NEmpty, NSpin,
    NPagination, createDiscreteApi, NTooltip
} from 'naive-ui';
import {
    EyeOutline, CashOutline, AlertCircleOutline,
    CheckmarkCircleOutline, TimeOutline, CloseCircleOutline,
    CalendarOutline, PersonOutline, SearchOutline,
    DownloadOutline
} from '@vicons/ionicons5';

const { notification } = createDiscreteApi(['notification']);

// --- Estado ---
const loading = ref(false);
const reportData = ref([]);
const pagination = ref({ current_page: 1, last_page: 1, total: 0 });
const fetchError = ref(null);
const currentPage = ref(1);

// --- Filtros ---
const paymentMethodFilter = ref(null);
const delinquencyFilter = ref(null); // 'late' (6-10 días) o 'defaulted' (11+ días)

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
                payment_method: paymentMethodFilter.value || undefined,
                delinquency: delinquencyFilter.value || undefined,
            }
        });
        reportData.value = response.data.reportData || [];
        pagination.value = response.data.pagination || { current_page: 1, last_page: 1, total: 0 };
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

// --- Modal de proyección ---
const showProjectionModal = ref(false);
const projectionClient = ref(null);

const openProjection = (client) => {
    projectionClient.value = client;
    showProjectionModal.value = true;
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

        <div v-else class="-mx-4 px-4 sm:mx-0 sm:px-0 overflow-x-auto">
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

        <!-- Modal de Proyección de Pagos (solo lectura) -->
        <n-modal v-model:show="showProjectionModal">
            <n-card
                style="width: 600px; max-height: 80vh;"
                :title="projectionClient?.name || 'Proyección de Pagos'"
                :bordered="false"
                size="small"
            >
                <div v-if="projectionClient" class="space-y-4">
                    <n-alert type="info" :bordered="false">
                        Saldo total: <strong>{{ formatCurrency(projectionClient.balance) }}</strong>
                        &mdash; {{ projectionClient.orders?.length || 0 }} órdenes con deuda
                    </n-alert>

                    <div v-for="order in projectionClient.orders" :key="order.id"
                         class="border rounded-lg p-3" :class="order.is_overdue ? 'border-red-200 bg-red-50/30' : 'border-gray-200'">
                        <div class="flex justify-between items-center mb-2">
                            <span class="font-bold text-sm">OS #{{ order.id }}</span>
                            <div class="flex gap-2">
                                <n-tag :type="order.status === 'Aceptado' ? 'info' : order.status === 'En Proceso' ? 'warning' : 'default'"
                                    size="tiny" round :bordered="false">{{ order.status }}</n-tag>
                                <n-tag :type="order.is_overdue ? 'error' : 'success'" size="tiny" round :bordered="false">
                                    {{ order.is_overdue ? 'Vencida' : 'Vigente' }}
                                </n-tag>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 text-xs text-gray-600 mb-3">
                            <div>Plan: <strong>{{ paymentPlanLabel(order.payment_method) }}</strong></div>
                            <div>Total: <strong>{{ formatCurrency(order.total_amount) }}</strong></div>
                            <div>Pagado: <strong class="text-emerald-600">{{ formatCurrency(order.paid) }}</strong></div>
                            <div>Restante: <strong :class="order.is_overdue ? 'text-red-600' : 'text-amber-600'">{{ formatCurrency(order.remaining) }}</strong></div>
                            <div v-if="order.liquidation_deadline" class="col-span-2">
                                Límite: <strong :class="order.is_overdue ? 'text-red-600' : ''">{{ formatDate(order.liquidation_deadline) }}</strong>
                                <span v-if="order.days_since_projected != null" class="ml-1">
                                    (<strong :class="order.days_since_projected >= 11 ? 'text-red-600' : order.days_since_projected >= 6 ? 'text-amber-600' : ''">
                                        {{ order.days_since_projected }} días
                                    </strong>)
                                </span>
                            </div>
                        </div>
                        <p class="text-xs text-gray-400 italic">
                            Para registrar un pago, ve a la pestaña <strong>Servicios</strong> en el perfil del cliente.
                        </p>
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
