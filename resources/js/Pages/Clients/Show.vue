<script setup>
import { ref, computed, onMounted } from 'vue';
import { usePermissions } from '@/Composables/usePermissions';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PaymentModal from '@/Components/MyComponents/PaymentModal.vue';

// Importación de Componentes Hijos (ajusta la ruta según lo necesites)
import ClientServicesTab from './Components/ClientServicesTab.vue';
import ClientPaymentsTab from './Components/ClientPaymentsTab.vue';
import ClientContactsTab from './Components/ClientContactsTab.vue';
import ClientDocumentsTab from './Components/ClientDocumentsTab.vue';
import ClientTicketsTab from './Components/ClientTicketsTab.vue';

import { 
    NButton, NIcon, NTabs, NTabPane, NAvatar, NBadge, NAlert, createDiscreteApi
} from 'naive-ui';
import { 
    ArrowBackOutline, PersonOutline, MailOutline, CallOutline, LocationOutline, 
    ConstructOutline, WalletOutline, PeopleOutline, DocumentTextOutline,
    CreateOutline, MapOutline, ReceiptOutline, CheckmarkCircleOutline, AlertCircleOutline,
    TicketOutline
} from '@vicons/ionicons5';

const props = defineProps({
    client: { type: Object, required: true },
    stats: { type: Object, required: true }
});

const { hasPermission } = usePermissions();
const { notification } = createDiscreteApi(['notification']);

// --- LÓGICA DE PESTAÑAS Y URL ---
const activeTab = ref('services');

onMounted(() => {
    const urlParams = new URLSearchParams(window.location.search);
    const tab = urlParams.get('tab');
    if (tab) {
        activeTab.value = tab;
    }
});

const handleTabChange = (name) => {
    activeTab.value = name;
    const url = new URL(window.location.href);
    url.searchParams.set('tab', name);
    window.history.replaceState({}, '', url);
};

// --- MODALES ---
const showPaymentModal = ref(false);

const openPaymentModal = () => {
    if (props.stats.balance <= 1) {
        notification.success({ title: 'Sin Deuda', content: 'Este cliente está al corriente.', duration: 3000 });
        return;
    }
    showPaymentModal.value = true;
};

// --- UTILIDADES ---
const formatCurrency = (amount) => new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(amount);

const primaryContact = computed(() => {
    if (!props.client.contacts || props.client.contacts.length === 0) return null;
    return props.client.contacts.find(c => c.is_primary) || props.client.contacts[0];
});

const formattedAddress = computed(() => {
    const c = props.client;
    const streetPart = c.street 
        ? ((c.road_type ? c.road_type + ' ' : '') + c.street + (c.exterior_number ? ` #${c.exterior_number}` : '')) 
        : null;

    const parts = [
        streetPart, c.interior_number ? `Int. ${c.interior_number}` : null,
        c.neighborhood ? `Col. ${c.neighborhood}` : null,
        c.zip_code ? `CP ${c.zip_code}` : null, c.municipality, c.state
    ];
    return parts.filter(Boolean).join(', ');
});

const googleMapsUrl = computed(() => {
    if (!props.client.street && !props.client.municipality) return null;
    const addressQuery = [
        props.client.street, props.client.exterior_number, props.client.neighborhood,
        props.client.municipality, props.client.state, props.client.country || 'México'
    ].filter(Boolean).join(', ');
    return `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(addressQuery)}`;
});
</script>

<template>
    <AppLayout :title="client.name">
        <div class="py-4 md:py-8 min-h-screen">
            <div class="max-w-7xl mx-auto px-3 sm:px-6 lg:px-8">
                
                <div class="mb-4 flex justify-between items-center">
                    <Link :href="route('clients.index')">
                        <n-button text class="hover:text-gray-900 text-gray-500 transition-colors" size="small">
                            <template #icon><n-icon><ArrowBackOutline /></n-icon></template>
                            <span class="hidden xs:inline">Volver</span>
                        </n-button>
                    </Link>
                    
                    <Link v-if="hasPermission('clients.edit')" :href="route('clients.edit', client.id)">
                        <n-button secondary round type="warning" size="small">
                            <template #icon><n-icon><CreateOutline /></n-icon></template> Editar
                        </n-button>
                    </Link>
                </div>

                <!-- CABECERA PRINCIPAL -->
                <div class="bg-white rounded-2xl sm:rounded-3xl p-4 sm:p-6 md:p-8 shadow-sm border border-gray-100 mb-6 relative overflow-hidden">
                    <div class="absolute top-0 right-0 w-64 h-64 bg-indigo-50 rounded-full blur-3xl -translate-y-1/2 translate-x-1/2 opacity-60 pointer-events-none"></div>

                    <div class="flex flex-col lg:flex-row justify-between gap-6 relative z-10">
                        <div class="flex items-start gap-3 sm:gap-5 max-w-4xl">
                            <n-avatar :size="60" class="bg-indigo-100 text-indigo-600 shadow-inner flex-shrink-0 sm:w-20 sm:h-20" :style="{ width: 'var(--avatar-size)', height: 'var(--avatar-size)' }">
                                <n-icon :size="30" class="sm:text-[40px]"><PersonOutline /></n-icon>
                            </n-avatar>
                            
                            <div class="flex-1 min-w-0">
                                <h1 class="text-xl sm:text-3xl font-black text-gray-800 tracking-tight leading-tight mb-2 break-words">
                                    {{ client.name }}
                                </h1>
                                
                                <div class="flex flex-col gap-2 text-xs sm:text-sm text-gray-600">
                                    <div class="flex flex-wrap gap-x-4 gap-y-1 items-center">
                                        <div v-if="client.contact_person" class="font-medium flex items-center gap-1.5">
                                            <n-icon class="text-gray-400"><PersonOutline/></n-icon> {{ client.contact_person }}
                                        </div>
                                        <div v-if="client.tax_id" class="flex items-center gap-1.5 uppercase bg-gray-100 px-2 py-0.5 rounded text-gray-500 text-[10px] font-bold tracking-wide">
                                            <n-icon><ReceiptOutline /></n-icon> {{ client.tax_id }}
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-1 mt-1" v-if="primaryContact">
                                        <div v-if="primaryContact.email" class="flex flex-col">
                                            <span class="flex items-center gap-1.5 hover:text-indigo-600 transition-colors">
                                                <n-icon class="text-indigo-400"><MailOutline /></n-icon> {{ primaryContact.email }}
                                            </span>
                                        </div>
                                        <div v-if="primaryContact.phone" class="flex flex-col">
                                            <span class="flex items-center gap-1.5 hover:text-green-600 transition-colors">
                                                <n-icon class="text-green-500"><CallOutline /></n-icon> {{ primaryContact.phone }}
                                            </span>
                                        </div>
                                    </div>
                                    <div v-else class="text-gray-400 italic mt-1">Sin medios de contacto registrados</div>

                                    <div v-if="formattedAddress" class="mt-2 flex flex-col sm:flex-row sm:items-center gap-2">
                                        <div class="flex items-start gap-1.5 text-gray-500">
                                            <n-icon class="mt-0.5 text-red-500 flex-shrink-0"><LocationOutline /></n-icon> 
                                            <span class="leading-snug">{{ formattedAddress }}</span>
                                        </div>
                                        <a v-if="googleMapsUrl" :href="googleMapsUrl" target="_blank" rel="noopener noreferrer" class="inline-block">
                                            <n-button size="tiny" secondary round type="info">
                                                <template #icon><n-icon><MapOutline/></n-icon></template> Ver en Mapa
                                            </n-button>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="flex flex-col sm:flex-row gap-3 w-full lg:w-auto">
                            <div class="bg-gray-50 rounded-xl p-4 border border-gray-100 flex flex-row sm:flex-col justify-between items-center sm:items-start w-full sm:min-w-[180px] h-auto sm:h-full">
                                <div>
                                    <div class="text-gray-500 text-[10px] font-bold uppercase tracking-wider mb-1">Saldo Pendiente</div>
                                    <div class="text-2xl font-bold" :class="stats.balance > 1 ? 'text-red-600' : 'text-emerald-600'">
                                        {{ formatCurrency(stats.balance) }}
                                    </div>
                                </div>
                                <div class="text-right sm:text-left">
                                    <div class="mt-0 sm:mt-2 text-xs text-emerald-600 flex items-center justify-end sm:justify-start gap-1 font-medium" v-if="stats.balance <= 1">
                                        <n-icon><CheckmarkCircleOutline /></n-icon> <span class="hidden xs:inline">Al corriente</span>
                                    </div>
                                    <div class="mt-0 sm:mt-2 text-xs text-red-500 flex items-center justify-end sm:justify-start gap-1 font-medium" v-else>
                                        <n-icon><AlertCircleOutline /></n-icon> <span class="hidden xs:inline">Pago req.</span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-2 sm:flex sm:flex-col gap-2 w-full sm:w-auto">
                                <div class="bg-white border border-gray-100 px-3 py-2 rounded-xl shadow-sm flex flex-col justify-center">
                                    <div class="text-[10px] text-gray-400 uppercase truncate">Facturado</div>
                                    <div class="font-semibold text-gray-700 text-sm">{{ formatCurrency(stats.total_debt) }}</div>
                                </div>
                                <div class="bg-white border border-gray-100 px-3 py-2 rounded-xl shadow-sm flex flex-col justify-center">
                                    <div class="text-[10px] text-gray-400 uppercase truncate">Pagado</div>
                                    <div class="font-semibold text-gray-700 text-sm">{{ formatCurrency(stats.total_paid) }}</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-if="client.notes" class="mt-4 sm:mt-6 pt-4 border-t border-gray-50">
                        <n-alert type="warning" :bordered="false" class="bg-amber-50 rounded-xl">
                            <template #icon><n-icon><AlertCircleOutline /></n-icon></template>
                            <span class="font-semibold text-amber-800 text-xs uppercase mr-2">Notas:</span>
                            <span class="text-gray-700 text-xs sm:text-sm">{{ client.notes }}</span>
                        </n-alert>
                    </div>
                </div>

                <!-- CONTENIDO PRINCIPAL: PESTAÑAS (Integradas con URL) -->
                <div class="bg-white rounded-2xl sm:rounded-3xl shadow-lg border border-gray-100 overflow-hidden min-h-[500px] p-2 lg:p-7">
                    <n-tabs 
                        :value="activeTab"
                        @update:value="handleTabChange"
                        type="line" 
                        animated 
                        pane-class="p-4 sm:p-6" 
                        justify-content="space-between"
                        class="custom-tabs"
                    >
                        
                        <n-tab-pane name="services" tab="Servicios">
                            <template #tab>
                                <div class="flex items-center gap-1.5">
                                    <n-icon size="18"><ConstructOutline /></n-icon> 
                                    <span class="hidden sm:inline">Órdenes</span>
                                    <span class="sm:hidden text-xs">Servicios</span>
                                    <n-badge :value="client.service_orders.length" type="info" :max="99" class="scale-75 origin-left" />
                                </div>
                            </template>
                            <ClientServicesTab :client="client" />
                        </n-tab-pane>

                        <n-tab-pane name="tickets" tab="Tickets">
                            <template #tab>
                                <div class="flex items-center gap-1.5">
                                    <n-icon size="18"><TicketOutline /></n-icon> 
                                    <span class="hidden sm:inline">Tickets</span>
                                    <span class="sm:hidden text-xs">Tickets</span>
                                    <n-badge :value="client.tickets?.length || 0" type="warning" :max="99" class="scale-75 origin-left" />
                                </div>
                            </template>
                            <ClientTicketsTab :client="client" />
                        </n-tab-pane>

                        <n-tab-pane name="payments" tab="Pagos">
                            <template #tab>
                                <div class="flex items-center gap-1.5">
                                    <n-icon size="18"><WalletOutline /></n-icon> 
                                    <span class="hidden sm:inline">Pagos</span>
                                    <span class="sm:hidden text-xs">Pagos</span>
                                </div>
                            </template>
                            <ClientPaymentsTab :client="client" :stats="stats" @open-payment="openPaymentModal" />
                        </n-tab-pane>

                        <n-tab-pane name="contacts" tab="Contactos">
                            <template #tab>
                                <div class="flex items-center gap-1.5">
                                    <n-icon size="18"><PeopleOutline /></n-icon> 
                                    <span class="hidden sm:inline">Contactos</span>
                                    <span class="sm:hidden text-xs">Contactos</span>
                                    <n-badge :value="client.contacts?.length || 0" type="success" :max="99" class="scale-75 origin-left" />
                                </div>
                            </template>
                            <ClientContactsTab :client="client" />
                        </n-tab-pane>

                        <n-tab-pane name="documents" tab="Documentos">
                            <template #tab>
                                <div class="flex items-center gap-1.5">
                                    <n-icon size="18"><DocumentTextOutline /></n-icon> 
                                    <span class="hidden sm:inline">Documentos</span>
                                    <span class="sm:hidden text-xs">Docs</span>
                                </div>
                            </template>
                            <ClientDocumentsTab :client="client" />
                        </n-tab-pane>

                    </n-tabs>
                </div>
            </div>
        </div>

        <PaymentModal 
            v-model:show="showPaymentModal" 
            :client="client"
            @close="showPaymentModal = false"
        />
    </AppLayout>
</template>

<style scoped>
.n-avatar { --avatar-size: 60px; }
@media (min-width: 640px) { .n-avatar { --avatar-size: 80px; } }

:deep(.n-tabs .n-tabs-tab) {
    padding-top: 12px;
    padding-bottom: 12px;
    font-weight: 600;
    font-size: 0.85rem;
    transition: color 0.3s;
}
@media (min-width: 640px) {
    :deep(.n-tabs .n-tabs-tab) {
        padding-top: 16px;
        padding-bottom: 16px;
        font-size: 0.95rem;
    }
}
:deep(.n-tabs .n-tabs-tab--active) { color: #4f46e5 !important; }
:deep(.n-tabs .n-tabs-bar) { height: 3px; background-color: #4f46e5; border-radius: 3px; }
:deep(.n-data-table .n-data-table-th) {
    background-color: #f9fafb;
    font-size: 10px;
    text-transform: uppercase;
    font-weight: 700;
    color: #9ca3af;
    letter-spacing: 0.05em;
    border-bottom: 1px solid #f3f4f6;
}
@media (min-width: 640px) { :deep(.n-data-table .n-data-table-th) { font-size: 11px; } }
:deep(.n-data-table .n-data-table-td) { padding: 8px 10px; }
@media (min-width: 640px) { :deep(.n-data-table .n-data-table-td) { padding: 12px 16px; } }
:deep(.n-data-table-tr:hover) { background-color: #f3f4f6 !important; }
</style>