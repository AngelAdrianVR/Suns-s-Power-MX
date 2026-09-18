<script setup>
import { ref, computed, onMounted } from 'vue';
import { usePermissions } from '@/Composables/usePermissions';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PaymentModal from '@/Components/MyComponents/PaymentModal.vue';
import axios from 'axios';

// Importación de Componentes Hijos
import ClientOrderDetail from './Components/ClientOrderDetail.vue';
import ClientContactsTab from './Components/ClientContactsTab.vue';
import ClientDocumentsTab from './Components/ClientDocumentsTab.vue';
import ClientTicketsTab from './Components/ClientTicketsTab.vue';

import { 
    NButton, NIcon, NTabs, NTabPane, NAvatar, NBadge, NAlert, NTooltip, NInputNumber, createDiscreteApi
} from 'naive-ui';
import { 
    ArrowBackOutline, PersonOutline, MailOutline, CallOutline, LocationOutline, 
    ConstructOutline, PeopleOutline, DocumentTextOutline,
    CreateOutline, MapOutline, ReceiptOutline, CheckmarkCircleOutline, AlertCircleOutline,
    TicketOutline, InformationCircleOutline, SaveOutline, CopyOutline
} from '@vicons/ionicons5';
import PermissionTooltip from '@/Components/MyComponents/PermissionTooltip.vue';

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
const preselectedOrderId = ref(null);
const preselectedAmount = ref(null);
const lockPaymentAmount = ref(false);
const paymentInstallmentNumber = ref(null);
const paymentModalTitle = ref('Registrar Abono');
const preselectedInstallmentId = ref(null);
const isLiquidating = ref(false);

const openPaymentModal = (orderId = null, amount = null, lock = false, installmentNum = null, title = 'Registrar Abono', installmentId = null, liquidate = false) => {
    preselectedOrderId.value = orderId;
    preselectedAmount.value = amount;
    lockPaymentAmount.value = lock;
    paymentInstallmentNumber.value = installmentNum;
    paymentModalTitle.value = title;
    preselectedInstallmentId.value = installmentId;
    isLiquidating.value = liquidate;
    showPaymentModal.value = true;
};

// Recarga forzando la URL con el tab actual para garantizar que se refresquen todos los datos
const handleRefresh = () => {
    const url = new URL(window.location.href);
    url.searchParams.set('tab', activeTab.value);
    router.get(url.toString(), {}, { preserveScroll: true });
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
    // Las coordenadas tienen SIEMPRE preferencia sobre la dirección.
    if (hasCoordinates.value) {
        return `https://www.google.com/maps/search/?api=1&query=${props.client.latitude},${props.client.longitude}`;
    }

    if (!props.client.street && !props.client.municipality) return null;
    const addressQuery = [
        props.client.street, props.client.exterior_number, props.client.neighborhood,
        props.client.municipality, props.client.state, props.client.country || 'México'
    ].filter(Boolean).join(', ');
    return `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(addressQuery)}`;
});

// --- COORDENADAS (LATITUD / LONGITUD) ---
const isEditingCoords = ref(false);
const isSavingCoords = ref(false);
const coordsForm = ref({ lat: null, lng: null });

const hasCoordinates = computed(() => {
    return props.client.latitude !== null && props.client.latitude !== undefined
        && props.client.longitude !== null && props.client.longitude !== undefined;
});

const coordinatesLabel = computed(() => `${props.client.latitude}, ${props.client.longitude}`);

const startEditCoords = () => {
    coordsForm.value = {
        lat: hasCoordinates.value ? Number(props.client.latitude) : null,
        lng: hasCoordinates.value ? Number(props.client.longitude) : null
    };
    isEditingCoords.value = true;
};

const cancelEditCoords = () => {
    isEditingCoords.value = false;
    coordsForm.value = { lat: null, lng: null };
};

const saveCoords = async () => {
    const hasLat = coordsForm.value.lat !== null && coordsForm.value.lat !== '';
    const hasLng = coordsForm.value.lng !== null && coordsForm.value.lng !== '';

    if (hasLat !== hasLng) {
        notification.warning({
            title: 'Datos incompletos',
            content: 'Ingresa latitud y longitud, o deja ambos campos vacíos para quitar las coordenadas.',
            duration: 5000
        });
        return;
    }

    isSavingCoords.value = true;
    try {
        await axios.patch(
            route('api.clients.update-coordinates', props.client.id),
            { latitude: hasLat ? coordsForm.value.lat : null, longitude: hasLng ? coordsForm.value.lng : null }
        );
        isEditingCoords.value = false;
        notification.success({ title: 'Ubicación actualizada', content: 'Las coordenadas se guardaron correctamente.', duration: 3000 });
        router.reload({ only: ['client'] });
    } catch (error) {
        notification.error({ title: 'Error', content: 'No se pudieron guardar las coordenadas.', duration: 4000 });
    } finally {
        isSavingCoords.value = false;
    }
};

const copyCoordinates = async () => {
    try {
        await navigator.clipboard.writeText(coordinatesLabel.value);
        notification.success({ title: 'Copiado', content: 'Coordenadas copiadas al portapapeles.', duration: 2500 });
    } catch (error) {
        notification.error({ title: 'Error', content: 'No se pudieron copiar las coordenadas.', duration: 3000 });
    }
};
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
                    
                    <div class="flex items-center gap-2">
                        <PermissionTooltip permission="clients.index" placement="bottom" :size="13" />
                        <a v-if="hasPermission('clients.index')" :href="route('clients.statement.view', client.id)" target="_blank" rel="noopener">
                            <n-button secondary round type="info" size="small">
                                <template #icon><n-icon><DocumentTextOutline /></n-icon></template> Estado de cuenta
                            </n-button>
                        </a>

                        <PermissionTooltip permission="clients.edit" placement="bottom" :size="13" />
                        <Link v-if="hasPermission('clients.edit')" :href="route('clients.edit', client.id)">
                            <n-button secondary round type="warning" size="small">
                                <template #icon><n-icon><CreateOutline /></n-icon></template> Editar
                            </n-button>
                        </Link>
                    </div>
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
                                    </div>

                                    <!-- Coordenadas (latitud / longitud) -->
                                    <div class="mt-2">
                                        <!-- Modo vista -->
                                        <div v-if="!isEditingCoords" class="flex flex-wrap items-center gap-2">
                                            <span class="text-[10px] text-gray-400 uppercase font-bold">Coordenadas</span>
                                            <span v-if="hasCoordinates" class="font-mono text-xs text-gray-500 truncate max-w-[220px]">
                                                📍 {{ coordinatesLabel }}
                                            </span>
                                            <span v-else class="text-xs text-gray-400 italic">Sin coordenadas registradas</span>

                                            <n-button v-if="hasCoordinates" size="tiny" text @click="copyCoordinates">
                                                <template #icon><n-icon><CopyOutline /></n-icon></template>
                                            </n-button>

                                            <n-button
                                                v-if="hasPermission('clients.edit')"
                                                size="tiny" text type="primary"
                                                @click="startEditCoords"
                                            >
                                                <template #icon><n-icon><CreateOutline /></n-icon></template>
                                                {{ hasCoordinates ? 'Editar' : 'Agregar' }}
                                            </n-button>
                                            <PermissionTooltip permission="clients.edit" placement="top" :size="12" />

                                            <a v-if="googleMapsUrl" :href="googleMapsUrl" target="_blank" rel="noopener noreferrer" class="inline-block">
                                                <n-button size="tiny" secondary round type="info">
                                                    <template #icon><n-icon><MapOutline/></n-icon></template> Ver en Mapa
                                                </n-button>
                                            </a>
                                        </div>

                                        <!-- Modo edición -->
                                        <div v-else class="rounded-lg border border-indigo-200 bg-indigo-50/40 p-2 max-w-md">
                                            <div class="text-[10px] text-gray-400 uppercase font-bold mb-1">Coordenadas (Google Maps)</div>
                                            <div class="flex items-center gap-2">
                                                <n-input-number
                                                    v-model:value="coordsForm.lat"
                                                    :min="-90" :max="90" :precision="6" :show-button="false"
                                                    size="small" placeholder="Latitud" class="w-full"
                                                />
                                                <n-input-number
                                                    v-model:value="coordsForm.lng"
                                                    :min="-180" :max="180" :precision="6" :show-button="false"
                                                    size="small" placeholder="Longitud" class="w-full"
                                                />
                                            </div>
                                            <div class="flex justify-end gap-2 mt-2">
                                                <n-button size="tiny" @click="cancelEditCoords">Cancelar</n-button>
                                                <n-button size="tiny" type="primary" :loading="isSavingCoords" @click="saveCoords">
                                                    <template #icon><n-icon><SaveOutline /></n-icon></template>
                                                    Guardar
                                                </n-button>
                                            </div>
                                            <p class="text-[10px] text-gray-400 leading-tight mt-2">
                                                Copia cada valor desde Google Maps (clic derecho sobre el punto → coordenadas).
                                                Deja ambos campos vacíos para quitarlas.
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div v-if="hasPermission('clients.view_balance')" class="flex flex-col sm:flex-row gap-3 w-full lg:w-auto relative">
                            <div class="absolute -top-2 -right-2 z-10">
                                <PermissionTooltip permission="clients.view_balance" placement="left" :size="12" />
                            </div>
                            <div class="bg-gray-50 rounded-xl p-4 border border-gray-100 flex flex-row sm:flex-col justify-between items-center sm:items-start w-full sm:min-w-[180px] h-auto sm:h-full relative">
                                <div>
                                    <div class="text-gray-500 text-[10px] font-bold uppercase tracking-wider mb-1">Saldo Pendiente</div>
                                    <div class="flex items-center gap-1.5">
                                        <div class="text-2xl font-bold" :class="stats.balance > 1 ? 'text-red-600' : 'text-emerald-600'">
                                            {{ formatCurrency(stats.balance) }}
                                        </div>
                                        <n-tooltip trigger="hover" placement="top">
                                            <template #trigger>
                                                <n-icon size="14" class="text-gray-400 cursor-pointer hover:text-gray-600"><InformationCircleOutline /></n-icon>
                                            </template>
                                            Las órdenes de servicio con estatus "Cotización" no se toman en cuenta para el balance actual del cliente
                                        </n-tooltip>
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
                            
                            <div class="flex flex-col gap-2 w-full sm:w-auto">
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

                                <!-- Desglose de intereses cobrados -->
                                <div v-if="stats.total_interest_paid > 0" class="bg-amber-50 border border-amber-200 rounded-xl px-3 py-1.5 flex items-center justify-between gap-2">
                                    <div class="text-[10px] text-amber-700 font-medium">
                                        <span class="font-bold">Intereses cobrados</span>
                                    </div>
                                    <div class="text-xs font-black text-amber-800">
                                        {{ formatCurrency(stats.total_interest_paid) }}
                                    </div>
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
                                    <span class="hidden sm:inline">Órdenes y Pagos</span>
                                    <span class="sm:hidden text-xs">Servicios</span>
                                    <n-badge :value="client.service_orders.length" type="info" :max="99" class="scale-75 origin-left" />
                                </div>
                            </template>
                            <ClientOrderDetail :client="client" :stats="stats" @open-payment="openPaymentModal" @refresh="handleRefresh" />
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
            :preselected-order-id="preselectedOrderId"
            :preselected-amount="preselectedAmount"
            :lock-amount="lockPaymentAmount"
            :installment-number="paymentInstallmentNumber"
            :installment-id="preselectedInstallmentId"
            :is-liquidating="isLiquidating"
            :modal-title="paymentModalTitle"
            @paid="handleRefresh"
            @close="showPaymentModal = false; preselectedOrderId = null; preselectedAmount = null; lockPaymentAmount = false; paymentInstallmentNumber = null; preselectedInstallmentId = null; isLiquidating = false;"
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