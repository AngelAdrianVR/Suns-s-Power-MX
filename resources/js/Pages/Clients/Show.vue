<script setup>
import { ref, h, computed } from 'vue';
import { usePermissions } from '@/Composables/usePermissions';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PaymentModal from '@/Components/MyComponents/PaymentModal.vue';
import { 
    NButton, NCard, NIcon, NTag, NTabs, NTabPane, NStatistic, NDataTable, NEmpty, 
    NSpace, NAvatar, NDivider, createDiscreteApi, NBadge, NAlert, NTooltip
} from 'naive-ui';
import { 
    ArrowBackOutline, PersonOutline, MailOutline, CallOutline, LocationOutline, 
    WalletOutline, DocumentTextOutline, ConstructOutline, CashOutline, 
    AlertCircleOutline, CheckmarkCircleOutline, ReceiptOutline, CloudDownloadOutline,
    CreateOutline, AddOutline, EyeOutline, MapOutline, PhonePortraitOutline,
    PeopleOutline, Star, StarOutline, OpenOutline, TrashOutline
} from '@vicons/ionicons5';

// Definición de Props explícita
const props = defineProps({
    client: {
        type: Object,
        required: true
    },
    stats: {
        type: Object,
        required: true
    }
});

// Inicializar permisos
const { hasPermission } = usePermissions();

// Configuración Global de Notificaciones y Diálogos
const { notification, dialog } = createDiscreteApi(['notification', 'dialog'], {
    notificationProviderProps: {
        duration: 4000,
        keepAliveOnHover: true
    }
});

// --- ESTADO Y UTILIDADES ---
const activeTab = ref('services');
const showPaymentModal = ref(false);
const fileInput = ref(null);

const formatCurrency = (amount) => {
    return new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(amount);
};

const formatDate = (dateString) => {
    if (!dateString) return '-';
    return new Date(dateString).toLocaleDateString('es-MX', { year: 'numeric', month: 'short', day: 'numeric' });
};

// --- COMPUTED: CONTACTO PRINCIPAL ---
const primaryContact = computed(() => {
    if (!props.client.contacts || props.client.contacts.length === 0) return null;
    return props.client.contacts.find(c => c.is_primary) || props.client.contacts[0];
});

// --- COMPUTED: DIRECCIÓN Y MAPAS ---
const formattedAddress = computed(() => {
    const c = props.client;
    // Construir la calle con su tipo de vialidad
    const streetPart = c.street 
        ? ((c.road_type ? c.road_type + ' ' : '') + c.street + (c.exterior_number ? ` #${c.exterior_number}` : '')) 
        : null;

    const parts = [
        streetPart,
        c.interior_number ? `Int. ${c.interior_number}` : null,
        c.neighborhood ? `Col. ${c.neighborhood}` : null,
        c.zip_code ? `CP ${c.zip_code}` : null,
        c.municipality,
        c.state
    ];
    return parts.filter(Boolean).join(', ');
});

const googleMapsUrl = computed(() => {
    if (!props.client.street && !props.client.municipality) return null;
    
    const addressQuery = [
        props.client.street,
        props.client.exterior_number,
        props.client.neighborhood,
        props.client.municipality,
        props.client.state,
        props.client.country || 'México'
    ].filter(Boolean).join(', ');
    
    return `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(addressQuery)}`;
});

// --- COLUMNAS CONTACTOS ---
const contactColumns = [
    {
        title: '',
        key: 'is_primary',
        width: 40,
        render(row) {
            return row.is_primary 
                ? h(NIcon, { color: '#f59e0b', size: 18 }, { default: () => h(Star) }) 
                : null;
        }
    },
    { 
        title: 'Nombre', 
        key: 'name', 
        render(row) {
            return h('div', { class: 'font-medium text-gray-800' }, row.name);
        }
    },
    { 
        title: 'Puesto / Parentesco', 
        key: 'job_title',
        render(row) {
             return row.job_title 
                ? h(NTag, { size: 'small', bordered: false, type: 'default' }, { default: () => row.job_title }) 
                : '-';
        }
    },
    { 
        title: 'Email', 
        key: 'email',
        render(row) {
            if (!row.email) return '-';
            return h('a', { 
                href: `mailto:${row.email}`, 
                class: 'text-indigo-600 hover:underline flex items-center gap-1' 
            }, [
                h(NIcon, null, { default: () => h(MailOutline) }),
                row.email
            ]);
        }
    },
    { 
        title: 'Teléfono', 
        key: 'phone',
        render(row) {
            if (!row.phone) return '-';
             return h('a', { 
                href: `tel:${row.phone}`, 
                class: 'text-green-600 hover:underline flex items-center gap-1' 
            }, [
                h(NIcon, null, { default: () => h(CallOutline) }),
                row.phone
            ]);
        }
    },
    { title: 'Notas', key: 'notes', ellipsis: { tooltip: true } }
];

// --- COLUMNAS SERVICIOS ---
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
    { title: 'Inicio', key: 'start_date', width: 90, render: (row) => formatDate(row.start_date) },
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

// --- COLUMNAS PAGOS ---
const paymentColumns = [
    { title: 'Fecha', key: 'payment_date', width: 90, render: (row) => formatDate(row.payment_date) },
    { 
        title: 'Concepto', 
        key: 'service_order_id',
        minWidth: 120,
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
    { title: 'Método', key: 'method', width: 80, render: (row) => h('span', { class: 'text-xs' }, row.method) },
    { 
        title: 'Monto', 
        key: 'amount', 
        align: 'right', 
        width: 100,
        render: (row) => h('span', { class: 'font-bold text-emerald-600 text-xs' }, formatCurrency(row.amount)) 
    }
];

// --- COLUMNAS DOCUMENTOS ---
const docColumns = [
    { 
        title: 'Nombre', 
        key: 'name', 
        // Hacemos que el nombre sea un link explícito
        render: (row) => h('a', { 
            class: 'font-medium text-indigo-600 hover:underline text-xs sm:text-sm cursor-pointer',
            href: row.url,
            target: '_blank'
        }, row.name) 
    },
    { title: 'Categoría', key: 'category', render: (row) => h(NTag, { size: 'tiny' }, { default: () => row.category }) },
    { title: 'Fecha', key: 'created_at', render: (row) => formatDate(row.created_at) },
    {
        title: '',
        key: 'actions',
        align: 'right',
        render(row) {
            const buttons = [];

            // Botón de Descarga
            buttons.push(h(NTooltip, { trigger: 'hover' }, {
                trigger: () => h(NButton, { 
                    circle: true, 
                    size: 'small', 
                    quaternary: true, 
                    type: 'info',
                    tag: 'a',
                    href: row.url,
                    target: '_blank',
                    download: row.name,
                    class: 'mr-1',
                    onClick: (e) => e.stopPropagation()
                }, { icon: () => h(NIcon, null, { default: () => h(CloudDownloadOutline) }) }),
                default: () => 'Descargar'
            }));

            // Botón de Eliminar (Solo si tiene permisos)
            if (hasPermission('clients.edit')) {
                buttons.push(h(NTooltip, { trigger: 'hover' }, {
                    trigger: () => h(NButton, { 
                        circle: true, 
                        size: 'small', 
                        quaternary: true, 
                        type: 'error',
                        onClick: (e) => {
                            e.stopPropagation();
                            dialog.warning({
                                title: 'Eliminar archivo',
                                content: '¿Estás seguro de que deseas eliminar este archivo? Esta acción no se puede deshacer.',
                                positiveText: 'Eliminar',
                                negativeText: 'Cancelar',
                                onPositiveClick: () => {
                                    router.delete(route('media.delete-file', row.id), {
                                        preserveScroll: true,
                                        onSuccess: () => {
                                            notification.success({ title: 'Éxito', content: 'Archivo eliminado correctamente.' });
                                        },
                                        onError: () => {
                                            notification.error({ title: 'Error', content: 'Error al eliminar el archivo.' });
                                        }
                                    });
                                }
                            });
                        }
                    }, { icon: () => h(NIcon, null, { default: () => h(TrashOutline) }) }),
                    default: () => 'Eliminar'
                }));
            }

            return h('div', { class: 'flex items-center justify-end' }, buttons);
        }
    }
];

// Propiedades de fila para hacerlas clickeables y abrir en nueva pestaña
const docRowProps = (row) => {
    return {
        style: 'cursor: pointer;',
        onClick: (e) => {
            // Evitar conflicto si se clickea en un botón o link interno explícito
            if (e.target.tagName === 'A' || e.target.closest('button') || e.target.closest('a')) return;
            window.open(row.url, '_blank');
        }
    }
}

// --- ACCIONES ---
const openPaymentModal = () => {
    if (props.stats.balance <= 1) {
        notification.success({ 
            title: 'Sin Deuda', 
            content: 'Este cliente está al corriente.',
            duration: 3000
        });
        return;
    }
    showPaymentModal.value = true;
};

const createServiceOrder = () => {
    router.visit(route('service-orders.create', { client_id: props.client.id }));
};

// --- LÓGICA DE SUBIDA DE ARCHIVOS (MÚLTIPLE) ---
const uploadForm = useForm({
    files: [], // Cambiado de 'file' a 'files' array
    category: 'General'
});

const triggerFileInput = () => {
    fileInput.value.click();
};

const handleFileChange = (event) => {
    // Convertir FileList a Array
    const files = Array.from(event.target.files);
    if (files.length === 0) return;

    uploadForm.files = files;

    // Inertia maneja arrays de archivos automáticamente
    uploadForm.post(route('clients.documents.store', props.client.id), {
        preserveScroll: true,
        onSuccess: () => {
            notification.success({ 
                title: 'Éxito', 
                content: `${files.length > 1 ? 'Documentos subidos' : 'Documento subido'} correctamente.` 
            });
            uploadForm.reset();
            if (fileInput.value) fileInput.value.value = '';
        },
        onError: () => {
            notification.error({ 
                title: 'Error', 
                content: 'Hubo un problema al subir los documentos.' 
            });
        }
    });
};
</script>

<template>
    <AppLayout :title="client.name">
        <div class="py-4 md:py-8 min-h-screen">
            <div class="max-w-7xl mx-auto px-3 sm:px-6 lg:px-8">
                
                <!-- Navegación y Edición -->
                <div class="mb-4 flex justify-between items-center">
                    <Link :href="route('clients.index')">
                        <n-button text class="hover:text-gray-900 text-gray-500 transition-colors" size="small">
                            <template #icon><n-icon><ArrowBackOutline /></n-icon></template>
                            <span class="hidden xs:inline">Volver</span>
                        </n-button>
                    </Link>
                    
                    <Link v-if="hasPermission('clients.edit')" :href="route('clients.edit', client.id)">
                        <n-button secondary round type="warning" size="small">
                            <template #icon><n-icon><CreateOutline /></n-icon></template>
                            Editar
                        </n-button>
                    </Link>
                </div>

                <!-- CABECERA PRINCIPAL -->
                <div class="bg-white rounded-2xl sm:rounded-3xl p-4 sm:p-6 md:p-8 shadow-sm border border-gray-100 mb-6 relative overflow-hidden">
                    <div class="absolute top-0 right-0 w-64 h-64 bg-indigo-50 rounded-full blur-3xl -translate-y-1/2 translate-x-1/2 opacity-60 pointer-events-none"></div>

                    <div class="flex flex-col lg:flex-row justify-between gap-6 relative z-10">
                        
                        <!-- Columna Identidad -->
                        <div class="flex items-start gap-3 sm:gap-5 max-w-4xl">
                            <n-avatar 
                                :size="60" 
                                class="bg-indigo-100 text-indigo-600 shadow-inner flex-shrink-0 sm:w-20 sm:h-20"
                                :style="{ width: 'var(--avatar-size)', height: 'var(--avatar-size)' }"
                            >
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

                                    <!-- Datos de Contacto (DINÁMICO DESDE CONTACTOS) -->
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
                                    <div v-else class="text-gray-400 italic mt-1">
                                        Sin medios de contacto registrados
                                    </div>

                                    <!-- Dirección Atomizada -->
                                    <div v-if="formattedAddress" class="mt-2 flex flex-col sm:flex-row sm:items-center gap-2">
                                        <div class="flex items-start gap-1.5 text-gray-500">
                                            <n-icon class="mt-0.5 text-red-500 flex-shrink-0"><LocationOutline /></n-icon> 
                                            <span class="leading-snug">{{ formattedAddress }}</span>
                                        </div>
                                        
                                        <a v-if="googleMapsUrl" :href="googleMapsUrl" target="_blank" rel="noopener noreferrer" class="inline-block">
                                            <n-button size="tiny" secondary round type="info">
                                                <template #icon><n-icon><MapOutline/></n-icon></template>
                                                Ver en Mapa
                                            </n-button>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Columna Estado Financiero -->
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

                <!-- CONTENIDO PRINCIPAL: PESTAÑAS -->
                <div class="bg-white rounded-2xl sm:rounded-3xl shadow-lg border border-gray-100 overflow-hidden min-h-[500px] p-2 lg:p-7">
                    <n-tabs 
                        v-model:value="activeTab" 
                        type="line" 
                        animated 
                        pane-class="p-4 sm:p-6" 
                        justify-content="space-between"
                        class="custom-tabs"
                    >
                        
                        <!-- PESTAÑA 1: SERVICIOS -->
                        <n-tab-pane name="services" tab="Servicios">
                            <template #tab>
                                <div class="flex items-center gap-1.5">
                                    <n-icon size="18"><ConstructOutline /></n-icon> 
                                    <span class="hidden sm:inline">Órdenes</span>
                                    <span class="sm:hidden text-xs">Servicios</span>
                                    <n-badge :value="client.service_orders.length" type="info" :max="99" class="scale-75 origin-left" />
                                </div>
                            </template>

                            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-4 gap-3">
                                <div>
                                    <h3 class="text-base sm:text-lg font-bold text-gray-800">Historial</h3>
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
                        </n-tab-pane>

                        <!-- PESTAÑA 2: PAGOS -->
                        <n-tab-pane name="payments" tab="Pagos">
                            <template #tab>
                                <div class="flex items-center gap-1.5">
                                    <n-icon size="18"><WalletOutline /></n-icon> 
                                    <span class="hidden sm:inline">Historial</span>
                                    <span class="sm:hidden text-xs">Pagos</span>
                                </div>
                            </template>

                            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-4 gap-3">
                                <div>
                                    <h3 class="text-base sm:text-lg font-bold text-gray-800">Cobranza</h3>
                                    <p class="text-xs sm:text-sm text-gray-500">Abonos y pagos</p>
                                </div>
                                <n-button v-if="hasPermission('collection.create')" type="success" secondary round size="small" class="w-full sm:w-auto" @click="openPaymentModal" :disabled="stats.balance <= 0">
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
                        </n-tab-pane>

                        <!-- PESTAÑA 3: CONTACTOS -->
                        <n-tab-pane name="contacts" tab="Contactos">
                            <template #tab>
                                <div class="flex items-center gap-1.5">
                                    <n-icon size="18"><PeopleOutline /></n-icon> 
                                    <span class="hidden sm:inline">Contactos</span>
                                    <span class="sm:hidden text-xs">Contactos</span>
                                    <n-badge :value="client.contacts?.length || 0" type="success" :max="99" class="scale-75 origin-left" />
                                </div>
                            </template>

                            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-4 gap-3">
                                <div>
                                    <h3 class="text-base sm:text-lg font-bold text-gray-800">Directorio</h3>
                                    <p class="text-xs sm:text-sm text-gray-500">Personas de contacto registradas</p>
                                </div>
                            </div>

                            <div class="-mx-4 px-4 sm:mx-0 sm:px-0 overflow-x-auto">
                                <div class="min-w-[600px] sm:min-w-full">
                                    <n-data-table
                                        :columns="contactColumns"
                                        :data="client.contacts"
                                        :bordered="false"
                                        size="medium"
                                        class="mb-2"
                                    />
                                    <n-empty v-if="!client.contacts || client.contacts.length === 0" description="No hay contactos registrados" class="py-8"/>
                                </div>
                            </div>
                        </n-tab-pane>

                        <!-- PESTAÑA 4: DOCUMENTOS -->
                        <n-tab-pane name="documents" tab="Documentos">
                            <template #tab>
                                <div class="flex items-center gap-1.5">
                                    <n-icon size="18"><DocumentTextOutline /></n-icon> 
                                    <span class="hidden sm:inline">Documentos</span>
                                    <span class="sm:hidden text-xs">Docs</span>
                                </div>
                            </template>

                            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-4 gap-3">
                                <div>
                                    <h3 class="text-base sm:text-lg font-bold text-gray-800">Expediente</h3>
                                </div>
                            </div>

                            <input 
                                type="file" 
                                ref="fileInput" 
                                class="hidden" 
                                multiple
                                @change="handleFileChange"
                            >

                            <div 
                                v-if="hasPermission('clients.edit')" 
                                @click="triggerFileInput"
                                class="border-2 border-dashed border-gray-200 rounded-xl p-6 text-center mb-6 hover:bg-indigo-50 hover:border-indigo-300 transition-all cursor-pointer group relative"
                            >
                                <div v-if="uploadForm.processing" class="absolute inset-0 bg-white/80 flex items-center justify-center z-10 rounded-xl">
                                    <span class="text-indigo-600 font-bold animate-pulse">Subiendo...</span>
                                </div>

                                <div class="bg-blue-50 w-10 h-10 rounded-full flex items-center justify-center mx-auto mb-2 group-hover:scale-110 transition-transform">
                                    <n-icon size="20" class="text-blue-500"><CloudDownloadOutline /></n-icon>
                                </div>
                                <h4 class="font-bold text-gray-700 text-sm">Subir documentos</h4>
                                <p class="text-gray-400 text-[10px] mt-1">Clic para explorar (soporta múltiples archivos)</p>
                            </div>

                            <div class="-mx-4 px-4 sm:mx-0 sm:px-0 overflow-x-auto">
                                <div class="min-w-[450px] sm:min-w-full">
                                    <n-data-table
                                        :columns="docColumns"
                                        :data="client.documents"
                                        :bordered="false"
                                        size="small"
                                        :row-props="docRowProps"
                                        class="cursor-pointer"
                                    />
                                </div>
                            </div>
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
/* Estilos responsivos para avatar */
.n-avatar {
    --avatar-size: 60px;
}
@media (min-width: 640px) {
    .n-avatar {
        --avatar-size: 80px;
    }
}

/* Estilos personalizados para las pestañas */
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
:deep(.n-tabs .n-tabs-tab--active) {
    color: #4f46e5 !important;
}
:deep(.n-tabs .n-tabs-bar) {
    height: 3px;
    background-color: #4f46e5;
    border-radius: 3px;
}
:deep(.n-data-table .n-data-table-th) {
    background-color: #f9fafb;
    font-size: 10px;
    text-transform: uppercase;
    font-weight: 700;
    color: #9ca3af;
    letter-spacing: 0.05em;
    border-bottom: 1px solid #f3f4f6;
}
@media (min-width: 640px) {
    :deep(.n-data-table .n-data-table-th) {
        font-size: 11px;
    }
}
:deep(.n-data-table .n-data-table-td) {
    padding: 8px 10px;
}
@media (min-width: 640px) {
    :deep(.n-data-table .n-data-table-td) {
        padding: 12px 16px;
    }
}
/* Efecto hover en filas de documentos */
:deep(.n-data-table-tr:hover) {
    background-color: #f3f4f6 !important;
}
</style>