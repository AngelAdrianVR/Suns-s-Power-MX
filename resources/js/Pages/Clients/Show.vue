<script setup>
import { ref, h } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { 
    NButton, NCard, NIcon, NTag, NTabs, NTabPane, NStatistic, NDataTable, NEmpty, 
    NSpace, NAvatar, NDivider, createDiscreteApi, NBadge, NAlert, NTooltip
} from 'naive-ui';
import { 
    ArrowBackOutline, PersonOutline, MailOutline, CallOutline, LocationOutline, 
    WalletOutline, DocumentTextOutline, ConstructOutline, CashOutline, 
    AlertCircleOutline, CheckmarkCircleOutline, ReceiptOutline, CloudDownloadOutline,
    CreateOutline, AddOutline
} from '@vicons/ionicons5';

const props = defineProps({
    client: Object,
    stats: Object, // { total_debt, total_paid, balance, services_count }
});

const { notification } = createDiscreteApi(['notification']);

// --- ESTADO Y UTILIDADES ---
const activeTab = ref('services'); // Pestaña por defecto: Servicios

const formatCurrency = (amount) => {
    return new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(amount);
};

const formatDate = (dateString) => {
    if (!dateString) return '-';
    return new Date(dateString).toLocaleDateString('es-MX', { year: 'numeric', month: 'long', day: 'numeric' });
};

// --- DEFINICIÓN DE COLUMNAS PARA TABLAS ---

// Columnas para Servicios
const serviceColumns = [
    { title: 'Folio', key: 'id', width: 80, render: (row) => `#${row.id}` },
    { 
        title: 'Estado', 
        key: 'status',
        render(row) {
            const types = { 
                'Cotización': 'default', 'Aceptado': 'info', 'En Proceso': 'warning', 
                'Completado': 'success', 'Facturado': 'success', 'Cancelado': 'error' 
            };
            return h(NTag, { type: types[row.status] || 'default', size: 'small', bordered: false, round: true }, { default: () => row.status });
        }
    },
    { title: 'Fecha Inicio', key: 'start_date', render: (row) => formatDate(row.start_date) },
    { title: 'Monto Total', key: 'total_amount', align: 'right', render: (row) => formatCurrency(row.total_amount) },
    {
        title: '',
        key: 'actions',
        width: 60,
        render(row) {
            return h(NTooltip, { trigger: 'hover' }, {
                trigger: () => h(NButton, { 
                    circle: true, size: 'tiny', secondary: true,
                    onClick: () => router.visit(route('service-orders.show', row.id))
                }, { icon: () => h(NIcon, null, { default: () => h(ArrowBackOutline, { style: 'transform: rotate(180deg)' }) }) }),
                default: () => 'Ver Detalles'
            });
        }
    }
];

// Columnas para Pagos
const paymentColumns = [
    { title: 'Fecha', key: 'payment_date', render: (row) => formatDate(row.payment_date) },
    { title: 'Método', key: 'method' },
    { title: 'Referencia', key: 'reference', render: (row) => row.reference || '-' },
    { 
        title: 'Monto', 
        key: 'amount', 
        align: 'right', 
        render: (row) => h('span', { class: 'font-bold text-emerald-600' }, formatCurrency(row.amount)) 
    }
];

// Columnas para Documentos
const docColumns = [
    { title: 'Nombre', key: 'name', render: (row) => h('div', { class: 'font-medium' }, row.name) },
    { title: 'Categoría', key: 'category', render: (row) => h(NTag, { size: 'small' }, { default: () => row.category }) },
    { title: 'Fecha', key: 'created_at', render: (row) => formatDate(row.created_at) },
    {
        title: '',
        key: 'actions',
        align: 'right',
        render(row) {
            return h(NButton, { 
                circle: true, size: 'small', quaternary: true, type: 'info',
            }, { icon: () => h(NIcon, null, { default: () => h(CloudDownloadOutline) }) });
        }
    }
];

// --- ACCIONES ---
const registerPayment = () => {
    notification.info({ title: 'Próximamente', content: 'Módulo de Pagos en desarrollo.' });
    // router.visit(route('payments.create', { client_id: props.client.id }));
};

const createServiceOrder = () => {
    router.visit(route('service-orders.create', { client_id: props.client.id }));
};

</script>

<template>
    <AppLayout :title="client.name">
        <div class="py-8 min-h-screen">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                
                <!-- Navegación y Edición -->
                <div class="mb-6 flex justify-between items-center">
                    <Link :href="route('clients.index')">
                        <n-button text class="hover:text-gray-900 text-gray-500 transition-colors">
                            <template #icon><n-icon><ArrowBackOutline /></n-icon></template>
                            Volver al directorio
                        </n-button>
                    </Link>
                    <Link :href="route('clients.edit', client.id)">
                        <n-button secondary round type="warning" size="small">
                            <template #icon><n-icon><CreateOutline /></n-icon></template>
                            Editar Datos
                        </n-button>
                    </Link>
                </div>

                <!-- CABECERA PRINCIPAL: Identidad y Finanzas -->
                <div class="bg-white rounded-3xl p-6 md:p-8 shadow-sm border border-gray-100 mb-6 relative overflow-hidden">
                    <!-- Fondo decorativo -->
                    <div class="absolute top-0 right-0 w-64 h-64 bg-indigo-50 rounded-full blur-3xl -translate-y-1/2 translate-x-1/2 opacity-60 pointer-events-none"></div>

                    <div class="flex flex-col lg:flex-row justify-between gap-8 relative z-10">
                        
                        <!-- Columna Identidad -->
                        <div class="flex items-start gap-5">
                            <n-avatar :size="80" class="bg-indigo-100 text-indigo-600 shadow-inner flex-shrink-0">
                                <n-icon size="40"><PersonOutline /></n-icon>
                            </n-avatar>
                            <div>
                                <h1 class="text-3xl font-black text-gray-800 tracking-tight leading-none mb-2">
                                    {{ client.name }}
                                </h1>
                                <div class="flex flex-col gap-1.5 text-sm text-gray-500">
                                    <div v-if="client.contact_person" class="font-medium text-gray-600 flex items-center gap-1">
                                        <n-icon><PersonOutline/></n-icon> {{ client.contact_person }}
                                    </div>
                                    <div class="flex flex-wrap gap-4 mt-1">
                                        <span v-if="client.email" class="flex items-center gap-1.5 hover:text-indigo-600 transition-colors">
                                            <n-icon class="text-indigo-400"><MailOutline /></n-icon> {{ client.email }}
                                        </span>
                                        <span v-if="client.phone" class="flex items-center gap-1.5 hover:text-green-600 transition-colors">
                                            <n-icon class="text-green-500"><CallOutline /></n-icon> {{ client.phone }}
                                        </span>
                                        <span v-if="client.tax_id" class="flex items-center gap-1.5">
                                            <n-icon class="text-gray-400"><ReceiptOutline /></n-icon> RFC: {{ client.tax_id }}
                                        </span>
                                    </div>
                                    <div v-if="client.address" class="flex items-start gap-1.5 mt-1 max-w-xl text-gray-400">
                                        <n-icon class="mt-0.5"><LocationOutline /></n-icon> 
                                        <span>{{ client.address }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Columna Estado Financiero -->
                        <div class="flex gap-4 items-center">
                            <!-- Card Saldo Pendiente -->
                            <div class="bg-gray-50 rounded-2xl p-4 border border-gray-100 min-w-[180px] flex flex-col justify-between h-full">
                                <div>
                                    <div class="text-gray-500 text-[10px] font-bold uppercase tracking-wider mb-1">Saldo Pendiente</div>
                                    <div class="text-2xl font-bold" :class="stats.balance > 1 ? 'text-red-600' : 'text-emerald-600'">
                                        {{ formatCurrency(stats.balance) }}
                                    </div>
                                </div>
                                <div class="mt-2 text-xs text-emerald-600 flex items-center gap-1 font-medium" v-if="stats.balance <= 1">
                                    <n-icon><CheckmarkCircleOutline /></n-icon> Al corriente
                                </div>
                                <div class="mt-2 text-xs text-red-500 flex items-center gap-1 font-medium" v-else>
                                    <n-icon><AlertCircleOutline /></n-icon> Pago requerido
                                </div>
                            </div>

                            <!-- Estadísticas Rápidas -->
                            <div class="hidden sm:flex flex-col gap-2">
                                <div class="bg-white border border-gray-100 px-4 py-2 rounded-xl shadow-sm">
                                    <div class="text-[10px] text-gray-400 uppercase">Total Facturado</div>
                                    <div class="font-semibold text-gray-700">{{ formatCurrency(stats.total_debt) }}</div>
                                </div>
                                <div class="bg-white border border-gray-100 px-4 py-2 rounded-xl shadow-sm">
                                    <div class="text-[10px] text-gray-400 uppercase">Total Pagado</div>
                                    <div class="font-semibold text-gray-700">{{ formatCurrency(stats.total_paid) }}</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Notas Importantes (Siempre Visibles) -->
                    <div v-if="client.notes" class="mt-6 pt-4 border-t border-gray-50">
                        <n-alert type="warning" :bordered="false" class="bg-amber-50 rounded-xl">
                            <template #icon><n-icon><AlertCircleOutline /></n-icon></template>
                            <span class="font-semibold text-amber-800 text-xs uppercase mr-2">Notas Internas:</span>
                            <span class="text-gray-700 text-sm">{{ client.notes }}</span>
                        </n-alert>
                    </div>
                </div>

                <!-- CONTENIDO PRINCIPAL: PESTAÑAS -->
                <div class="bg-white rounded-3xl shadow-lg border border-gray-100 overflow-hidden min-h-[500px] p-4">
                    <n-tabs 
                        v-model:value="activeTab" 
                        type="line" 
                        animated 
                        pane-class="p-6"
                        justify-content="start"
                        class="custom-tabs"
                    >
                        
                        <!-- PESTAÑA 1: SERVICIOS (Principal) -->
                        <n-tab-pane name="services" tab="Servicios">
                            <template #tab>
                                <div class="flex items-center gap-2">
                                    <n-icon><ConstructOutline /></n-icon> Órdenes de Servicio
                                    <n-badge :value="client.service_orders.length" type="info" :max="99" class="scale-75" />
                                </div>
                            </template>

                            <!-- Header de Tab -->
                            <div class="flex flex-col sm:flex-row justify-between items-center mb-6 gap-4">
                                <div>
                                    <h3 class="text-lg font-bold text-gray-800">Historial de Instalaciones y Servicios</h3>
                                    <p class="text-sm text-gray-500">Gestión operativa del cliente</p>
                                </div>
                                <n-button type="primary" round size="medium" @click="createServiceOrder">
                                    <template #icon><n-icon><AddOutline /></n-icon></template>
                                    Nueva Orden
                                </n-button>
                            </div>

                            <n-data-table
                                :columns="serviceColumns"
                                :data="client.service_orders"
                                :bordered="false"
                                size="small"
                                :pagination="{ pageSize: 10 }"
                                class="mb-4"
                            />
                        </n-tab-pane>

                        <!-- PESTAÑA 2: PAGOS Y COBRANZA -->
                        <n-tab-pane name="payments" tab="Pagos">
                            <template #tab>
                                <div class="flex items-center gap-2">
                                    <n-icon><WalletOutline /></n-icon> Historial de Pagos
                                </div>
                            </template>

                            <!-- Header de Tab -->
                            <div class="flex flex-col sm:flex-row justify-between items-center mb-6 gap-4">
                                <div>
                                    <h3 class="text-lg font-bold text-gray-800">Control de Cobranza</h3>
                                    <p class="text-sm text-gray-500">Registro de abonos y liquidaciones</p>
                                </div>
                                <!-- Acción Contextual de Pagos -->
                                <n-button type="success" secondary round size="medium" @click="registerPayment" :disabled="stats.balance <= 0">
                                    <template #icon><n-icon><CashOutline /></n-icon></template>
                                    Registrar Abono
                                </n-button>
                            </div>

                            <div class="flex justify-between items-center mb-4 bg-gray-50 p-4 rounded-xl border border-gray-100">
                                <span class="text-sm text-gray-500 flex items-center gap-2">
                                    <n-icon class="text-emerald-500"><CheckmarkCircleOutline/></n-icon>
                                    Total acumulado pagado:
                                </span>
                                <span class="font-bold text-gray-800 text-xl">{{ formatCurrency(stats.total_paid) }}</span>
                            </div>

                            <n-data-table
                                :columns="paymentColumns"
                                :data="client.payments"
                                :bordered="false"
                                size="small"
                                :pagination="{ pageSize: 10 }"
                            />
                        </n-tab-pane>

                        <!-- PESTAÑA 3: DOCUMENTOS -->
                        <n-tab-pane name="documents" tab="Documentos">
                            <template #tab>
                                <div class="flex items-center gap-2">
                                    <n-icon><DocumentTextOutline /></n-icon> Documentos
                                </div>
                            </template>

                            <div class="flex flex-col sm:flex-row justify-between items-center mb-6 gap-4">
                                <div>
                                    <h3 class="text-lg font-bold text-gray-800">Expediente Digital</h3>
                                    <p class="text-sm text-gray-500">Contratos, identificaciones y evidencias</p>
                                </div>
                            </div>

                            <!-- Área de carga (Placeholder visual) -->
                            <div class="border-2 border-dashed border-gray-200 rounded-2xl p-8 text-center mb-6 hover:bg-gray-50 transition-colors cursor-pointer group">
                                <div class="bg-blue-50 w-12 h-12 rounded-full flex items-center justify-center mx-auto mb-3 group-hover:scale-110 transition-transform">
                                    <n-icon size="24" class="text-blue-500"><CloudDownloadOutline /></n-icon>
                                </div>
                                <h4 class="font-bold text-gray-700">Subir nuevo documento</h4>
                                <p class="text-gray-400 text-xs mt-1">Arrastra archivos aquí o haz clic para explorar</p>
                            </div>

                            <n-data-table
                                :columns="docColumns"
                                :data="client.documents"
                                :bordered="false"
                                size="small"
                            />
                        </n-tab-pane>

                    </n-tabs>
                </div>

            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
/* Estilos personalizados para las pestañas para darles un toque moderno */
:deep(.n-tabs .n-tabs-tab) {
    padding-top: 20px;
    padding-bottom: 20px;
    font-weight: 600;
    font-size: 0.95rem;
    transition: color 0.3s;
}
:deep(.n-tabs .n-tabs-tab--active) {
    color: #4f46e5 !important; /* Indigo 600 */
}
:deep(.n-tabs .n-tabs-bar) {
    height: 3px;
    background-color: #4f46e5;
    border-radius: 3px;
}
:deep(.n-data-table .n-data-table-th) {
    background-color: #f9fafb;
    font-size: 11px;
    text-transform: uppercase;
    font-weight: 700;
    color: #9ca3af;
    letter-spacing: 0.05em;
    border-bottom: 1px solid #f3f4f6;
}
:deep(.n-data-table .n-data-table-td) {
    padding: 12px 16px;
}
</style>