<script setup>
import { computed, ref, h } from 'vue';
import CompleteVisitModal from '@/Components/MyComponents/CompleteVisitModal.vue';
import { usePermissions } from '@/Composables/usePermissions';
import { useSecureFile } from '@/Composables/useSecureFile';
import { Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import {
    NButton, NIcon, NTag, NCard, NGrid, NGi, NAvatar, NEmpty, NSpin,
    createDiscreteApi, NTooltip, NInput, NModal, NDatePicker, NDropdown, NImage, NSelect
} from 'naive-ui';
import {
    ArrowBackOutline, CreateOutline, PersonOutline, BusinessOutline,
    CalendarOutline, CallOutline, LocationOutline, TimeOutline,
    HardwareChipOutline, CashOutline, DocumentTextOutline,
    InformationCircleOutline, CloudDownloadOutline, AttachOutline,
    ImageOutline, DocumentTextOutline as DocIcon, OpenOutline,
    FlashOutline, BatteryFullOutline, CheckmarkCircleOutline, CloseCircleOutline,
    AlertCircleOutline, HomeOutline, MapOutline,
    CloudUploadOutline, SaveOutline, TrashOutline, EllipsisHorizontal,
    CheckmarkDoneOutline, CameraOutline
} from '@vicons/ionicons5';
import PermissionTooltip from '@/Components/MyComponents/PermissionTooltip.vue';

const props = defineProps({
    visit: Object,
    evidenceMedia: Object,
});

const { hasPermission } = usePermissions();
const { notification } = createDiscreteApi(['notification']);
const { isOpeningFile, openFileWithRetry } = useSecureFile();

// --- CHECKLIST DE EVIDENCIAS ESTÁTICO ---
const checklistItems = [
    { key: 'facade_photo', title: 'Foto de la Fachada', description: 'Fotografía clara de la fachada del inmueble.', required: true },
    { key: 'meter_photo', title: 'Foto del Medidor', description: 'Fotografía del medidor de CFE.', required: true },
    { key: 'meter_prep_photo', title: 'Foto de la Preparación del Medidor', description: 'Fotografía de la preparación/conexión del medidor.', required: true },
    { key: 'main_panel_photo', title: 'Foto del Centro de Carga Principal', description: 'Fotografía del centro de carga principal.', required: true },
    { key: 'secondary_panel_photo', title: 'Foto del Centro de Carga Secundario (Opcional)', description: 'Fotografía del centro de carga secundario (si aplica).', required: false },
];

const isImage = (file) => {
    if (file?.mime_type) return file.mime_type.startsWith('image/');
    return /\.(jpg|jpeg|png|gif|webp)$/i.test(file?.name || file?.file_name || '');
};

const getMediaForCollection = (collectionKey) => {
    return props.evidenceMedia?.[collectionKey] || [];
};

// --- NOTAS INTERNAS EDITABLES ---
const isEditingNotes = ref(false);
const editingNotes = ref(props.visit.internal_notes || '');
const isSavingNotes = ref(false);

const startEditNotes = () => {
    editingNotes.value = props.visit.internal_notes || '';
    isEditingNotes.value = true;
};

const cancelEditNotes = () => {
    isEditingNotes.value = false;
    editingNotes.value = props.visit.internal_notes || '';
};

const saveNotes = () => {
    isSavingNotes.value = true;
    router.patch(route('technical-visits.update-notes', props.visit.id), {
        internal_notes: editingNotes.value,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            isEditingNotes.value = false;
            isSavingNotes.value = false;
            notification.success({ title: 'Notas Actualizadas', content: 'Las notas internas se guardaron correctamente.', duration: 3000 });
        },
        onError: () => {
            isSavingNotes.value = false;
            notification.error({ title: 'Error', content: 'No se pudieron guardar las notas.', duration: 3000 });
        },
    });
};

// --- SISTEMA DE INTERÉS EDITABLE ---
const isEditingSystem = ref(false);
const editingSystem = ref(props.visit.system_of_interest || null);
const isSavingSystem = ref(false);
const systemTypeOptions = ['Interconectado', 'Autónomo', 'Back-up', 'Bombeo'].map(s => ({ label: s, value: s }));

const startEditSystem = () => {
    editingSystem.value = props.visit.system_of_interest || null;
    isEditingSystem.value = true;
};

const cancelEditSystem = () => {
    isEditingSystem.value = false;
    editingSystem.value = props.visit.system_of_interest || null;
};

const saveSystem = () => {
    isSavingSystem.value = true;
    router.patch(route('technical-visits.update-system-type', props.visit.id), {
        system_of_interest: editingSystem.value,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            isEditingSystem.value = false;
            isSavingSystem.value = false;
            notification.success({ title: 'Sistema Actualizado', content: 'El sistema de interés se guardó correctamente.', duration: 3000 });
        },
        onError: () => {
            isSavingSystem.value = false;
            notification.error({ title: 'Error', content: 'No se pudo guardar el sistema de interés.', duration: 3000 });
        },
    });
};

// --- VOLTAJE EDITABLE ---
const isEditingVoltage = ref(false);
const editingVoltage = ref(props.visit.voltage || '');
const isSavingVoltage = ref(false);

const startEditVoltage = () => {
    editingVoltage.value = props.visit.voltage || '';
    isEditingVoltage.value = true;
};

const cancelEditVoltage = () => {
    isEditingVoltage.value = false;
    editingVoltage.value = props.visit.voltage || '';
};

const saveVoltage = () => {
    isSavingVoltage.value = true;
    router.patch(route('technical-visits.update-voltage', props.visit.id), {
        voltage: editingVoltage.value ? parseFloat(editingVoltage.value) : null,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            isEditingVoltage.value = false;
            isSavingVoltage.value = false;
            notification.success({ title: 'Voltaje Actualizado', content: 'El voltaje se guardó correctamente.', duration: 3000 });
        },
        onError: () => {
            isSavingVoltage.value = false;
            notification.error({ title: 'Error', content: 'No se pudo guardar el voltaje.', duration: 3000 });
        },
    });
};

// --- SUBIR EVIDENCIA DEL CHECKLIST ---
const triggerEvidenceInput = (collectionKey) => {
    document.getElementById(`file-${collectionKey}`).click();
};

const handleEvidenceUpload = (event, collectionKey) => {
    const file = event.target.files[0];
    if (!file) return;

    const form = useForm({ file, collection: collectionKey });

    form.post(route('technical-visits.upload-evidence', props.visit.id), {
        preserveScroll: true,
        onSuccess: () => {
            notification.success({ title: 'Evidencia Subida', content: 'El archivo se ha subido correctamente.', duration: 3000 });
            const input = document.getElementById(`file-${collectionKey}`);
            if (input) input.value = '';
        },
        onError: () => notification.error({ title: 'Error', content: 'No se pudo subir el archivo.', duration: 3000 }),
    });
};

// --- SUBIR ARCHIVO ADICIONAL ---
const triggerAdditionalInput = () => {
    document.getElementById('file-additional').click();
};

const handleAdditionalUpload = (event) => {
    const file = event.target.files[0];
    if (!file) return;

    const form = useForm({ file });

    form.post(route('technical-visits.upload-additional', props.visit.id), {
        preserveScroll: true,
        onSuccess: () => {
            notification.success({ title: 'Archivo Subido', content: 'Evidencia adicional guardada.', duration: 3000 });
            const input = document.getElementById('file-additional');
            if (input) input.value = '';
        },
        onError: () => notification.error({ title: 'Error', content: 'No se pudo subir el archivo.', duration: 3000 }),
    });
};

// --- ACCIONES RÁPIDAS DE ESTATUS ---
const quickAction = (action) => {
    if (action === 'accept') {
        router.patch(route('technical-visits.quick-update', props.visit.id), { action: 'accept' }, {
            preserveScroll: true,
            onSuccess: () => notification.success({ title: 'Visita Aceptada', content: 'La visita ha sido marcada como aceptada.', duration: 3000 }),
        });
    } else if (action === 'complete') {
        showCompleteModal.value = true;
    }
};

// --- MODAL DE COMPLETADO (Convertir a Cliente + Orden de Servicio) ---
const showCompleteModal = ref(false);

// Modales de Reprogramar y Rechazar
const showRescheduleModal = ref(false);
const rescheduleForm = useForm({
    scheduled_at: null,
    reschedule_reason: '',
});

const openRescheduleModal = () => {
    rescheduleForm.scheduled_at = null;
    rescheduleForm.reschedule_reason = '';
    showRescheduleModal.value = true;
};

const submitReschedule = () => {
    if (!rescheduleForm.scheduled_at) {
        notification.warning({ title: 'Atención', content: 'Debes seleccionar una nueva fecha y hora.', duration: 3000 });
        return;
    }
    const formattedDate = new Date(rescheduleForm.scheduled_at).toISOString().slice(0, 19).replace('T', ' ');
    router.patch(route('technical-visits.quick-update', props.visit.id), {
        action: 'reschedule',
        scheduled_at: formattedDate,
        reschedule_reason: rescheduleForm.reschedule_reason || '',
    }, {
        preserveScroll: true,
        onSuccess: () => {
            showRescheduleModal.value = false;
            notification.success({ title: 'Visita Reprogramada', content: 'La fecha se ha actualizado correctamente.', duration: 3000 });
        },
    });
};

const showRejectModal = ref(false);
const rejectReason = ref('');

const openRejectModal = () => {
    rejectReason.value = '';
    showRejectModal.value = true;
};

const submitReject = () => {
    if (!rejectReason.value.trim()) {
        notification.warning({ title: 'Atención', content: 'Debes escribir el motivo del rechazo.', duration: 3000 });
        return;
    }
    router.patch(route('technical-visits.quick-update', props.visit.id), {
        action: 'reject',
        rejection_reason: rejectReason.value,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            showRejectModal.value = false;
            notification.success({ title: 'Visita Rechazada', content: 'La visita ha sido marcada como rechazada.', duration: 3000 });
        },
    });
};

// Opciones del dropdown de estatus (dinámicas según estado)
const statusDropdownOptions = computed(() => {
    const s = props.visit?.status;
    const items = [];
    const canConvert = props.visit?.service_order_id === null;

    const can = (perm) => hasPermission(perm);

    if (s === 'Terminada') {
        if (!props.visit?.client_id && can('technical_visits.edit')) {
            items.push({ label: 'Convertir a Cliente', key: 'convert-client', icon: () => h(NIcon, null, { default: () => h(BusinessOutline) }) });
        }
        if (props.visit?.client_id && canConvert && can('technical_visits.edit')) {
            items.push({ label: 'Crear Orden de Servicio', key: 'create-order', icon: () => h(NIcon, null, { default: () => h(HardwareChipOutline) }) });
        }
        return items;
    }

    // Pendiente / Reprogramada: sin Terminar
    if (s === 'Pendiente' || s === 'Reprogramada') {
        if (can('technical_visits.accept')) items.push({ label: 'Aceptar', key: 'accept', icon: () => h(NIcon, null, { default: () => h(CheckmarkCircleOutline) }) });
        if (can('technical_visits.reschedule')) items.push({ label: 'Reprogramar', key: 'reschedule', icon: () => h(NIcon, null, { default: () => h(TimeOutline) }) });
        if (can('technical_visits.reject')) items.push({ label: 'Rechazar', key: 'reject', icon: () => h(NIcon, null, { default: () => h(CloseCircleOutline) }) });
    }
    // Aceptada: con Terminar
    else if (s === 'Aceptada') {
        if (can('technical_visits.complete')) items.push({ label: 'Terminar', key: 'complete', icon: () => h(NIcon, null, { default: () => h(CheckmarkDoneOutline) }) });
        if (can('technical_visits.reschedule')) items.push({ label: 'Reprogramar', key: 'reschedule', icon: () => h(NIcon, null, { default: () => h(TimeOutline) }) });
        if (can('technical_visits.reject')) items.push({ label: 'Rechazar', key: 'reject', icon: () => h(NIcon, null, { default: () => h(CloseCircleOutline) }) });
    }
    // Rechazada: solo aceptar/reprogramar para revertir
    else if (s === 'Rechazada') {
        if (can('technical_visits.accept')) items.push({ label: 'Aceptar', key: 'accept', icon: () => h(NIcon, null, { default: () => h(CheckmarkCircleOutline) }) });
        if (can('technical_visits.reschedule')) items.push({ label: 'Reprogramar', key: 'reschedule', icon: () => h(NIcon, null, { default: () => h(TimeOutline) }) });
    }

    return items;
});

const handleStatusSelect = (key) => {
    if (key === 'reschedule') openRescheduleModal();
    else if (key === 'reject') openRejectModal();
    else if (key === 'accept') quickAction('accept');
    else if (key === 'complete') quickAction('complete');
    else if (key === 'convert-client') showCompleteModal.value = true;
    else if (key === 'create-order') showCompleteModal.value = true;
};

// --- UTILIDADES ---
const formatCurrency = (amount) => {
    if (!amount) return 'N/A';
    return new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(amount);
};

const formatDate = (date) => {
    if (!date) return 'N/A';
    return new Date(date).toLocaleDateString('es-MX', {
        year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit'
    });
};

const getStatusColor = (status) => {
    const map = {
        'Pendiente': 'warning',
        'Reprogramada': 'info',
        'Aceptada': 'success',
        'Terminada': 'default',
        'Rechazada': 'error'
    };
    return map[status] || 'default';
};

const getFileIcon = (fileName) => {
    const ext = fileName?.split('.').pop()?.toLowerCase();
    if (['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(ext)) return ImageOutline;
    if (['pdf', 'doc', 'docx', 'txt'].includes(ext)) return DocIcon;
    return AttachOutline;
};

const fullAddress = computed(() => {
    const v = props.visit;
    const parts = [
        v.road_type, v.street,
        v.exterior_number ? `#${v.exterior_number}` : null,
        v.interior_number ? `Int. ${v.interior_number}` : null,
        v.neighborhood ? `Col. ${v.neighborhood}` : null,
        v.zip_code ? `CP ${v.zip_code}` : null,
        v.municipality, v.state, v.country
    ];
    return parts.filter(Boolean).join(', ') || 'No especificada';
});

const googleMapsUrl = computed(() => {
    if (!props.visit.google_maps_link) return null;
    return props.visit.google_maps_link;
});

const prospectName = computed(() => {
    const v = props.visit;
    if (v.client) return v.client.name;
    if (v.business_name) return v.business_name;
    return [v.first_name, v.paternal_surname, v.maternal_surname].filter(Boolean).join(' ') || 'Sin nombre';
});

const rateTypeLabel = computed(() => {
    const map = {
        '01': '01 - Doméstica', '1A': '1A', '1B': '1B', '1C': '1C',
        '1D': '1D', '1E': '1E', '1F': '1F',
        'DAC': 'DAC - Alto Consumo', 'PDBT': 'PDBT - Pequeña Demanda',
        'GDBT': 'GDBT - Gran Demanda BT', 'GDMTO': 'GDMTO - Gran Demanda MT',
        'GDMTH': 'GDMTH - Gran Demanda MT Horaria', '00': '00'
    };
    return map[props.visit.rate_type] || props.visit.rate_type;
});

const salesRepInitials = computed(() => {
    const name = props.visit?.sales_rep?.name;
    if (!name) return '?';
    return name.split(' ').map(w => w[0]).slice(0, 2).join('').toUpperCase();
});
</script>

<template>
    <AppLayout :title="`Visita #${visit.id}`">
        <div class="py-4 md:py-8 min-h-screen">
            <div class="max-w-5xl mx-auto px-3 sm:px-6 lg:px-8">

                <!-- Barra superior -->
                <div class="mb-4 flex justify-between items-center">
                    <Link :href="route('technical-visits.index')">
                        <n-button text class="hover:text-gray-900 text-gray-500 transition-colors" size="small">
                            <template #icon><n-icon><ArrowBackOutline /></n-icon></template>
                            <span class="hidden xs:inline">Volver</span>
                        </n-button>
                    </Link>
                    <div class="flex items-center gap-2">
                        <PermissionTooltip permission="technical_visits.edit" placement="bottom" :size="13" />
                        <Link v-if="hasPermission('technical_visits.edit')" :href="route('technical-visits.edit', visit.id)">
                            <n-button secondary round type="warning" size="small">
                                <template #icon><n-icon><CreateOutline /></n-icon></template> Editar
                            </n-button>
                        </Link>
                        <PermissionTooltip permission="technical_visits.edit" placement="bottom" :size="13" />
                        <n-dropdown v-if="hasPermission('technical_visits.edit') && statusDropdownOptions.length > 0" trigger="click" :options="statusDropdownOptions" :on-select="handleStatusSelect">
                            <n-button secondary round type="primary" size="small">
                                <template #icon><n-icon><EllipsisHorizontal /></n-icon></template>
                                {{ props.visit.status === 'Terminada' ? 'Conversión' : 'Acciones' }}
                            </n-button>
                        </n-dropdown>
                    </div>
                </div>

                <!-- CABECERA PRINCIPAL -->
                <div class="bg-white rounded-2xl sm:rounded-3xl p-4 sm:p-6 md:p-8 shadow-sm border border-gray-100 mb-6 relative overflow-hidden">
                    <div class="absolute top-0 right-0 w-64 h-64 bg-indigo-50 rounded-full blur-3xl -translate-y-1/2 translate-x-1/2 opacity-60 pointer-events-none"></div>

                    <div class="relative z-10 flex flex-col lg:flex-row justify-between gap-6">
                        <div class="flex items-start gap-4 sm:gap-5 max-w-2xl">
                            <n-avatar :size="56" class="bg-indigo-100 text-indigo-600 shadow-inner flex-shrink-0" :style="{ width: '56px', height: '56px' }">
                                <n-icon size="28"><component :is="visit.client ? BusinessOutline : PersonOutline" /></n-icon>
                            </n-avatar>
                            <div>
                                <div class="flex flex-wrap items-center gap-3 mb-1">
                                    <h1 class="text-xl sm:text-2xl font-bold text-gray-800">{{ prospectName }}</h1>
                                    <n-tag :type="getStatusColor(visit.status)" round :bordered="false" size="small">
                                        {{ visit.status }}
                                    </n-tag>
                                </div>
                                <div class="flex flex-wrap items-center gap-2 text-sm text-gray-500">
                                    <span class="font-mono text-indigo-500">#{{ visit.id }}</span>
                                    <span>•</span>
                                    <span class="flex items-center gap-1">
                                        <n-icon size="14"><CalendarOutline /></n-icon>
                                        {{ formatDate(visit.scheduled_at) }}
                                    </span>
                                    <span v-if="visit.client" class="text-blue-500 font-medium">• Cliente Existente</span>
                                    <span v-else class="text-orange-500 font-medium">• Prospecto Nuevo</span>
                                </div>

                                <!-- Razón de estatus -->
                                <div v-if="visit.status === 'Reprogramada' && visit.reschedule_reason" class="mt-3 bg-blue-50 border border-blue-200 rounded-xl p-3 flex items-start gap-2">
                                    <n-icon size="18" class="text-blue-500 mt-0.5"><InformationCircleOutline /></n-icon>
                                    <div>
                                        <p class="text-xs font-bold text-blue-700 uppercase">Motivo de Reprogramación</p>
                                        <p class="text-sm text-blue-600">{{ visit.reschedule_reason }}</p>
                                    </div>
                                </div>
                                <div v-if="visit.status === 'Rechazada' && visit.rejection_reason" class="mt-3 bg-red-50 border border-red-200 rounded-xl p-3 flex items-start gap-2">
                                    <n-icon size="18" class="text-red-500 mt-0.5"><CloseCircleOutline /></n-icon>
                                    <div>
                                        <p class="text-xs font-bold text-red-700 uppercase">Motivo del Rechazo</p>
                                        <p class="text-sm text-red-600">{{ visit.rejection_reason }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- CUERPO: GRID DE 2 COLUMNAS -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

                    <!-- Columna Izquierda (2/3) -->
                    <div class="lg:col-span-2 space-y-6">

                        <!-- Datos de Contacto -->
                        <n-card :bordered="false" class="shadow-sm rounded-2xl">
                            <template #header>
                                <span class="font-semibold text-gray-700 flex items-center gap-2">
                                    <n-icon :component="PersonOutline" /> Datos del Prospecto
                                </span>
                            </template>
                            <n-grid :cols="2" :x-gap="16" :y-gap="12" responsive="screen">
                                <n-gi span="2 m:1">
                                    <p class="text-xs text-gray-400 uppercase font-bold">Nombre / Razón Social</p>
                                    <p class="text-sm font-medium text-gray-800">{{ visit.business_name || prospectName }}</p>
                                </n-gi>
                                <n-gi span="2 m:1" v-if="visit.client">
                                    <p class="text-xs text-gray-400 uppercase font-bold">Cliente Relacionado</p>
                                    <Link :href="route('clients.show', visit.client.id)" class="text-sm font-medium text-blue-600 hover:underline">
                                        {{ visit.client.name }}
                                    </Link>
                                </n-gi>
                                <n-gi span="2 m:1" v-if="visit.phone">
                                    <p class="text-xs text-gray-400 uppercase font-bold flex items-center gap-1">
                                        <n-icon size="14"><CallOutline /></n-icon> Teléfono
                                    </p>
                                    <p class="text-sm font-medium text-gray-800">{{ visit.phone }}</p>
                                </n-gi>
                                <n-gi span="2 m:1" v-if="visit.lead_source">
                                    <p class="text-xs text-gray-400 uppercase font-bold">¿Cómo se enteró de nosotros?</p>
                                    <p class="text-sm font-medium text-gray-800">{{ visit.lead_source }}</p>
                                </n-gi>
                                <n-gi span="2 m:1" v-if="visit.sales_rep">
                                    <p class="text-xs text-gray-400 uppercase font-bold">Vendedor Asignado</p>
                                    <p class="text-sm font-medium text-gray-800">{{ visit.sales_rep.name }}</p>
                                </n-gi>
                            </n-grid>
                        </n-card>

                        <!-- Dirección -->
                        <n-card :bordered="false" class="shadow-sm rounded-2xl">
                            <template #header>
                                <span class="font-semibold text-gray-700 flex items-center gap-2">
                                    <n-icon :component="LocationOutline" /> Dirección
                                </span>
                            </template>
                            <p class="text-sm text-gray-700 mb-3">{{ fullAddress }}</p>
                            <div class="flex flex-wrap gap-2">
                                <a v-if="googleMapsUrl" :href="googleMapsUrl" target="_blank"
                                    class="inline-flex items-center gap-1 text-xs font-medium text-blue-600 bg-blue-50 hover:bg-blue-100 px-3 py-1.5 rounded-full transition-colors">
                                    <n-icon size="14"><OpenOutline /></n-icon> Ver en Google Maps
                                </a>
                                <a v-if="visit.street || visit.municipality" 
                                    :href="`https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(fullAddress)}`" 
                                    target="_blank"
                                    class="inline-flex items-center gap-1 text-xs font-medium text-gray-500 bg-gray-50 hover:bg-gray-100 px-3 py-1.5 rounded-full transition-colors">
                                    <n-icon size="14"><MapOutline /></n-icon> Buscar dirección
                                </a>
                            </div>
                        </n-card>

                        <!-- Datos del Inmueble y Servicio -->
                        <n-card :bordered="false" class="shadow-sm rounded-2xl">
                            <template #header>
                                <span class="font-semibold text-gray-700 flex items-center gap-2">
                                    <n-icon :component="HomeOutline" /> Datos del Inmueble y Servicio
                                </span>
                            </template>
                            <n-grid :cols="3" :x-gap="16" :y-gap="12" responsive="screen">
                                <n-gi span="3 m:1" v-if="visit.service_number">
                                    <p class="text-xs text-gray-400 uppercase font-bold">N° de Servicio</p>
                                    <p class="text-sm font-mono font-medium text-gray-800">{{ visit.service_number }}</p>
                                </n-gi>
                                <n-gi span="3 m:1" v-if="visit.rate_type">
                                    <p class="text-xs text-gray-400 uppercase font-bold">Tarifa</p>
                                    <p class="text-sm font-medium text-gray-800">{{ rateTypeLabel }}</p>
                                </n-gi>
                                <n-gi span="3 m:1" v-if="visit.property_use">
                                    <p class="text-xs text-gray-400 uppercase font-bold">Uso del Inmueble</p>
                                    <p class="text-sm font-medium text-gray-800">{{ visit.property_use }}</p>
                                </n-gi>
                                <n-gi span="3 m:1" v-if="visit.property_floors">
                                    <p class="text-xs text-gray-400 uppercase font-bold">N° de Pisos</p>
                                    <p class="text-sm font-medium text-gray-800">{{ visit.property_floors }}</p>
                                </n-gi>
                                <n-gi span="3 m:1" v-if="visit.number_of_wires">
                                    <p class="text-xs text-gray-400 uppercase font-bold">N° de Hilos</p>
                                    <p class="text-sm font-medium text-gray-800">{{ visit.number_of_wires }}</p>
                                </n-gi>
                                <n-gi span="3 m:1">
                                    <p class="text-xs text-gray-400 uppercase font-bold">¿Necesita escalera Larga?</p>
                                    <n-tag :type="visit.requires_long_ladder ? 'warning' : 'default'" size="small" round :bordered="false">
                                        {{ visit.requires_long_ladder ? 'Sí' : 'No' }}
                                    </n-tag>
                                </n-gi>
                            </n-grid>
                        </n-card>

                        <!-- Sistema y Cotización -->
                        <n-card :bordered="false" class="shadow-sm rounded-2xl">
                            <template #header>
                                <span class="font-semibold text-gray-700 flex items-center gap-2">
                                    <n-icon :component="HardwareChipOutline" /> Sistema y Cotización
                                </span>
                            </template>

                            <div v-if="!visit.system_of_interest" class="text-center py-4 text-gray-400 text-sm">
                                No se ha definido un sistema de interés.
                            </div>

                            <template v-else>
                                <n-grid :cols="2" :x-gap="16" :y-gap="12" responsive="screen">
                                    <n-gi span="2">
                                        <p class="text-xs text-gray-400 uppercase font-bold">Sistema de Interés</p>
                                        <div v-if="!isEditingSystem" class="flex items-center gap-2">
                                            <n-tag v-if="visit.system_of_interest" type="info" size="small" round :bordered="false">{{ visit.system_of_interest }}</n-tag>
                                            <span v-else class="text-sm text-gray-400">Sin definir</span>
                                            <n-button size="tiny" text type="primary" @click="startEditSystem" v-if="hasPermission('technical_visits.edit')">
                                                <template #icon><n-icon><CreateOutline /></n-icon></template>
                                            </n-button>
                                        </div>
                                        <div v-else class="flex items-center gap-2">
                                            <n-select 
                                                v-model:value="editingSystem" 
                                                :options="systemTypeOptions" 
                                                placeholder="Seleccionar sistema" 
                                                size="small" 
                                                class="w-48"
                                                clearable
                                            />
                                            <n-button size="tiny" type="primary" @click="saveSystem" :loading="isSavingSystem">
                                                <template #icon><n-icon><SaveOutline /></n-icon></template>
                                            </n-button>
                                            <n-button size="tiny" @click="cancelEditSystem">Cancelar</n-button>
                                        </div>
                                    </n-gi>

                                    <template v-if="visit.system_of_interest !== 'Back-up'">
                                        <n-gi span="2 m:1" v-if="visit.module_quantity">
                                            <p class="text-xs text-gray-400 uppercase font-bold">Módulos</p>
                                            <p class="text-sm font-medium text-gray-800">{{ visit.module_quantity }} <span class="text-gray-400">unidades</span></p>
                                        </n-gi>
                                        <n-gi span="2 m:1" v-if="visit.module_brand">
                                            <p class="text-xs text-gray-400 uppercase font-bold">Marca de Módulos</p>
                                            <p class="text-sm font-medium text-gray-800">{{ visit.module_brand }}</p>
                                        </n-gi>
                                        <n-gi span="2 m:1" v-if="visit.module_capacity">
                                            <p class="text-xs text-gray-400 uppercase font-bold">Capacidad por Módulo</p>
                                            <p class="text-sm font-medium text-gray-800">{{ visit.module_capacity }} Wp</p>
                                        </n-gi>
                                        <n-gi span="2 m:1" v-if="visit.gross_installed_capacity">
                                            <p class="text-xs text-gray-400 uppercase font-bold">Cap. Bruta Instalada</p>
                                            <p class="text-sm font-bold text-indigo-600">{{ visit.gross_installed_capacity }} kWp</p>
                                        </n-gi>
                                    </template>

                                    <template v-if="visit.system_of_interest === 'Interconectado'">
                                        <n-gi span="2 m:1" v-if="visit.estimated_daily_generation">
                                            <p class="text-xs text-gray-400 uppercase font-bold flex items-center gap-1"><n-icon size="14"><FlashOutline /></n-icon> Gen. Diaria Est.</p>
                                            <p class="text-sm font-medium text-gray-800">{{ visit.estimated_daily_generation }} kWh</p>
                                        </n-gi>
                                        <n-gi span="2 m:1" v-if="visit.estimated_monthly_generation">
                                            <p class="text-xs text-gray-400 uppercase font-bold">Gen. Mensual Est.</p>
                                            <p class="text-sm font-medium text-gray-800">{{ visit.estimated_monthly_generation }} kWh</p>
                                        </n-gi>
                                        <n-gi span="2 m:1" v-if="visit.estimated_monthly_saving">
                                            <p class="text-xs text-gray-400 uppercase font-bold">Ahorro Mensual Est.</p>
                                            <p class="text-sm font-bold text-green-600">{{ formatCurrency(visit.estimated_monthly_saving) }}</p>
                                        </n-gi>
                                    </template>

                                    <template v-if="['Autónomo', 'Back-up'].includes(visit.system_of_interest)">
                                        <n-gi span="2 m:1" v-if="visit.battery_quantity">
                                            <p class="text-xs text-gray-400 uppercase font-bold flex items-center gap-1"><n-icon size="14"><BatteryFullOutline /></n-icon> Baterías</p>
                                            <p class="text-sm font-medium text-gray-800">{{ visit.battery_quantity }} <span class="text-gray-400">unidades</span></p>
                                        </n-gi>
                                        <n-gi span="2 m:1" v-if="visit.battery_brand">
                                            <p class="text-xs text-gray-400 uppercase font-bold">Marca de Baterías</p>
                                            <p class="text-sm font-medium text-gray-800">{{ visit.battery_brand }}</p>
                                        </n-gi>
                                        <n-gi span="2 m:1" v-if="visit.battery_capacity">
                                            <p class="text-xs text-gray-400 uppercase font-bold">Capacidad Batería</p>
                                            <p class="text-sm font-medium text-gray-800">{{ visit.battery_capacity }} kWh</p>
                                        </n-gi>
                                    </template>

                                    <n-gi span="2" v-if="visit.backup_devices && visit.backup_devices.length > 0">
                                        <p class="text-xs text-gray-400 uppercase font-bold mb-2">Equipos a Respaldar</p>
                                        <div class="space-y-1">
                                            <div v-for="(device, i) in visit.backup_devices" :key="i" 
                                                class="flex justify-between text-sm bg-gray-50 rounded-lg px-3 py-1.5">
                                                <span class="text-gray-700">{{ device.concept || 'Sin descripción' }}</span>
                                                <span class="text-gray-400">{{ device.hours }} h</span>
                                            </div>
                                        </div>
                                    </n-gi>

                                    <n-gi span="2" v-if="visit.budget">
                                        <p class="text-xs text-gray-400 uppercase font-bold flex items-center gap-1"><n-icon size="14"><CashOutline /></n-icon> Presupuesto</p>
                                        <p class="text-lg font-bold text-indigo-600">{{ formatCurrency(visit.budget) }}</p>
                                    </n-gi>
                                </n-grid>
                            </template>
                        </n-card>

                        <!-- Evidencias del Levantamiento (Checklist Estático) -->
                        <n-card :bordered="false" class="shadow-sm rounded-2xl">
                            <template #header>
                                <span class="font-semibold text-gray-700 flex items-center gap-2">
                                    <n-icon :component="CameraOutline" /> Evidencias del Levantamiento
                                </span>
                            </template>

                            <div class="space-y-6">
                                <!-- Checklist items -->
                                <div v-for="item in checklistItems" :key="item.key" 
                                     class="border rounded-xl p-4"
                                     :class="getMediaForCollection(item.key).length ? 'bg-emerald-50/30 border-emerald-200' : 'bg-gray-50/50 border-gray-200'">
                                    <div class="flex justify-between items-start mb-3">
                                        <div>
                                            <h4 class="font-bold text-gray-800 text-sm">
                                                {{ item.title }}
                                                <span v-if="!item.required" class="text-xs font-normal text-gray-400">(Opcional)</span>
                                            </h4>
                                            <p class="text-xs text-gray-500 mt-0.5">{{ item.description }}</p>
                                        </div>
                                        <n-tag v-if="getMediaForCollection(item.key).length" type="success" size="tiny" round :bordered="false">Completado</n-tag>
                                        <n-tag v-else type="warning" size="tiny" round :bordered="false">Pendiente</n-tag>
                                    </div>

                                    <!-- Preview de archivo subido -->
                                    <div v-if="getMediaForCollection(item.key).length" class="space-y-2">
                                        <div v-for="media in getMediaForCollection(item.key)" :key="media.id" class="relative group">
                                            <n-image v-if="isImage(media)" :src="media.original_url" class="rounded-lg border border-gray-200 w-full h-40 object-cover" object-fit="cover" />
                                            <div v-else class="w-full h-20 rounded-lg border border-gray-200 bg-white flex items-center justify-center cursor-pointer hover:bg-gray-50" @click="openFileWithRetry(media.original_url)">
                                                <div class="flex flex-col items-center gap-1">
                                                    <n-icon size="22" class="text-gray-400"><DocIcon /></n-icon>
                                                    <span class="text-xs text-gray-500">Ver Documento</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Botón para subir (requiere technical_visits.upload_evidence) -->
                                    <div v-if="hasPermission('technical_visits.upload_evidence')" class="mt-3">
                                        <div class="flex items-center gap-2 mb-1">
                                            <PermissionTooltip permission="technical_visits.upload_evidence" placement="top" :size="12" />
                                        </div>
                                        <input type="file" :id="'file-'+item.key" class="hidden" @change="e => handleEvidenceUpload(e, item.key)" accept="image/*,application/pdf" />
                                        <n-button dashed size="small" type="primary" class="w-full" @click="triggerEvidenceInput(item.key)">
                                            <template #icon><n-icon><CloudUploadOutline /></n-icon></template>
                                            {{ getMediaForCollection(item.key).length ? 'Cambiar Archivo' : 'Subir ' + (isImage(getMediaForCollection(item.key)[0]) ? 'Fotografía' : 'Archivo') }}
                                        </n-button>
                                    </div>
                                </div>

                                <!-- Voltaje -->
                                <div class="border rounded-xl p-4 bg-gray-50/50 border-gray-200">
                                    <div class="flex justify-between items-start mb-3">
                                        <div>
                                            <h4 class="font-bold text-gray-800 text-sm">Voltaje (V)</h4>
                                            <p class="text-xs text-gray-500 mt-0.5">Registro del voltaje medido en la visita.</p>
                                        </div>
                                    </div>
                                    
                                    <div v-if="!isEditingVoltage" class="flex items-center gap-2">
                                        <p class="text-lg font-mono font-bold text-gray-700">{{ visit.voltage ? visit.voltage + ' V' : '—' }}</p>
                                        <n-button size="tiny" text type="primary" @click="startEditVoltage" v-if="hasPermission('technical_visits.edit')">
                                            <template #icon><n-icon><CreateOutline /></n-icon></template>
                                        </n-button>
                                    </div>
                                    <div v-else class="flex items-center gap-2">
                                        <n-input v-model:value="editingVoltage" type="number" placeholder="Ej: 127" size="small" class="w-32">
                                            <template #suffix>V</template>
                                        </n-input>
                                        <n-button size="tiny" type="primary" @click="saveVoltage" :loading="isSavingVoltage">
                                            <template #icon><n-icon><SaveOutline /></n-icon></template>
                                        </n-button>
                                        <n-button size="tiny" @click="cancelEditVoltage">Cancelar</n-button>
                                    </div>
                                </div>

                                <!-- Evidencias Adicionales -->
                                <div class="border rounded-xl p-4 bg-gray-50/50 border-gray-200">
                                    <div class="flex justify-between items-start mb-3">
                                        <div>
                                            <h4 class="font-bold text-gray-800 text-sm">Evidencias Adicionales</h4>
                                            <p class="text-xs text-gray-500 mt-0.5">Fotografías o documentos extra del levantamiento.</p>
                                        </div>
                                    </div>

                                    <div v-if="getMediaForCollection('additional_evidences').length" class="grid grid-cols-2 md:grid-cols-3 gap-3 mb-3">
                                        <div v-for="media in getMediaForCollection('additional_evidences')" :key="media.id" class="relative group">
                                            <n-image v-if="isImage(media)" :src="media.original_url" class="rounded-lg border border-gray-200 w-full h-24 object-cover" object-fit="cover" />
                                            <div v-else class="w-full h-24 rounded-lg border border-gray-200 bg-white flex flex-col items-center justify-center cursor-pointer hover:bg-gray-50" @click="openFileWithRetry(media.original_url)">
                                                <n-icon size="22" class="text-gray-400"><DocIcon /></n-icon>
                                                <span class="text-xs text-gray-500">Ver</span>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-2 mb-2">
                                        <PermissionTooltip permission="technical_visits.upload_evidence" placement="top" :size="12" />
                                    </div>
                                    <input type="file" id="file-additional" class="hidden" @change="handleAdditionalUpload" accept="image/*,application/pdf" />
                                    <n-button dashed size="small" type="primary" class="w-full" @click="triggerAdditionalInput" v-if="hasPermission('technical_visits.upload_evidence')">
                                        <template #icon><n-icon><CloudUploadOutline /></n-icon></template>
                                        Subir Archivo Adicional
                                    </n-button>
                                </div>
                            </div>
                        </n-card>

                        <!-- Notas Internas -->
                        <n-card :bordered="false" class="shadow-sm rounded-2xl">
                            <template #header>
                                <span class="font-semibold text-gray-700 flex items-center gap-2">
                                    <n-icon :component="DocumentTextOutline" /> Notas Internas
                                </span>
                            </template>
                            
                            <div v-if="!isEditingNotes">
                                <p v-if="visit.internal_notes" class="text-sm text-gray-600 whitespace-pre-wrap">{{ visit.internal_notes }}</p>
                                <p v-else class="text-sm text-gray-400 italic">Sin notas internas.</p>
                                <div class="mt-3" v-if="hasPermission('technical_visits.edit')">
                                    <n-button size="tiny" text type="primary" @click="startEditNotes">
                                        <template #icon><n-icon><CreateOutline /></n-icon></template>
                                        {{ visit.internal_notes ? 'Editar Notas' : 'Agregar Notas' }}
                                    </n-button>
                                </div>
                            </div>
                            <div v-else>
                                <n-input
                                    v-model:value="editingNotes"
                                    type="textarea"
                                    placeholder="Escribe las notas internas de la visita..."
                                    :autosize="{ minRows: 3, maxRows: 8 }"
                                />
                                <div class="flex justify-end gap-2 mt-3">
                                    <n-button size="small" @click="cancelEditNotes">Cancelar</n-button>
                                    <n-button size="small" type="primary" @click="saveNotes" :loading="isSavingNotes">
                                        <template #icon><n-icon><SaveOutline /></n-icon></template>
                                        Guardar Notas
                                    </n-button>
                                </div>
                            </div>
                        </n-card>
                    </div>

                    <!-- Columna Derecha (1/3) -->
                    <div class="space-y-6">

                        <!-- Info del Vendedor -->
                        <n-card :bordered="false" class="shadow-sm rounded-2xl bg-indigo-50/50" v-if="visit.sales_rep">
                            <template #header>
                                <span class="font-semibold text-indigo-800 flex items-center gap-2">
                                    <n-icon :component="PersonOutline" /> Vendedor
                                </span>
                            </template>
                            <div class="flex items-center gap-3">
                                <n-avatar :size="40" :src="visit.sales_rep.photo" class="bg-indigo-200 text-indigo-600 font-bold">
                                    {{ salesRepInitials }}
                                </n-avatar>
                                <div>
                                    <p class="text-sm font-bold text-gray-800">{{ visit.sales_rep.name }}</p>
                                    <p class="text-xs text-indigo-500">Asignado</p>
                                </div>
                            </div>
                        </n-card>

                        <!-- Documentos -->
                        <n-card :bordered="false" class="shadow-sm rounded-2xl">
                            <template #header>
                                <span class="font-semibold text-gray-700 flex items-center gap-2">
                                    <n-icon :component="AttachOutline" /> Documentos
                                </span>
                            </template>

                            <div v-if="!visit.media || visit.media.length === 0" class="text-center py-4">
                                <n-empty description="Sin documentos" size="small" />
                            </div>

                            <div v-else class="space-y-2">
                                <div v-for="file in visit.media" :key="file.id"
                                    class="flex items-center gap-2 p-2 rounded-lg hover:bg-gray-50 transition-colors cursor-pointer"
                                    :class="{'opacity-70 pointer-events-none': isOpeningFile}"
                                    @click="openFileWithRetry(file.original_url)">
                                    <div class="w-8 h-8 rounded bg-gray-100 flex items-center justify-center text-gray-500 flex-shrink-0">
                                        <n-icon v-if="!isOpeningFile" size="16"><component :is="getFileIcon(file.name)" /></n-icon>
                                        <n-spin v-else size="14" />
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <span class="text-xs font-medium text-gray-700 truncate block hover:text-blue-600">
                                            {{ file.name }}
                                        </span>
                                    </div>
                                    <n-button size="tiny" circle secondary type="info" @click.stop="openFileWithRetry(file.url)" :disabled="isOpeningFile">
                                        <template #icon><n-icon size="14"><CloudDownloadOutline /></n-icon></template>
                                    </n-button>
                                </div>
                            </div>
                        </n-card>

                        <!-- Propuesta Comercial (si existe) -->
                        <n-card v-if="visit.payment_method || visit.requires_pre_installation" :bordered="false" class="shadow-sm rounded-2xl">
                            <template #header>
                                <span class="font-semibold text-gray-700 flex items-center gap-2">
                                    <n-icon :component="CashOutline" /> Propuesta Comercial
                                </span>
                            </template>
                            <n-grid :cols="1" :y-gap="8">
                                <n-gi v-if="visit.payment_method">
                                    <p class="text-xs text-gray-400 uppercase font-bold">Método de Pago</p>
                                    <p class="text-sm font-medium text-gray-800">{{ visit.payment_method }}</p>
                                </n-gi>
                                <n-gi v-if="visit.requires_pre_installation">
                                    <p class="text-xs text-gray-400 uppercase font-bold">Pre-Instalación</p>
                                    <n-tag type="warning" size="small" round :bordered="false">Requerida</n-tag>
                                    <p v-if="visit.pre_installation_details" class="text-xs text-gray-500 mt-1">{{ visit.pre_installation_details }}</p>
                                    <p v-if="visit.pre_installation_assigned_to" class="text-xs text-gray-500 mt-1">
                                        Responsable: <span class="font-medium">{{ visit.pre_installation_assigned_to }}</span>
                                    </p>
                                </n-gi>
                            </n-grid>
                        </n-card>

                        <!-- Orden de Servicio relacionada -->
                        <n-card v-if="visit.service_order" :bordered="false" class="shadow-sm rounded-2xl bg-green-50/50">
                            <template #header>
                                <span class="font-semibold text-green-800 flex items-center gap-2">
                                    <n-icon :component="CheckmarkCircleOutline" /> Orden de Servicio
                                </span>
                            </template>
                            <p class="text-xs text-green-600 mb-2">Esta visita ya fue convertida a orden de servicio.</p>
                            <Link :href="route('service-orders.show', visit.service_order.id)">
                                <n-button size="small" type="success" secondary block>
                                    Ver Orden #{{ visit.service_order.id }}
                                </n-button>
                            </Link>
                        </n-card>

                        <!-- Metadatos -->
                        <n-card :bordered="false" class="shadow-sm rounded-2xl">
                            <template #header>
                                <span class="font-semibold text-gray-700 flex items-center gap-2">
                                    <n-icon :component="TimeOutline" /> Información del Registro
                                </span>
                            </template>
                            <div class="space-y-3 text-xs">
                                <div>
                                    <p class="text-gray-400 uppercase font-bold">Creado</p>
                                    <p class="text-gray-600">{{ formatDate(visit.created_at) }}</p>
                                </div>
                                <div>
                                    <p class="text-gray-400 uppercase font-bold">Última Actualización</p>
                                    <p class="text-gray-600">{{ formatDate(visit.updated_at) }}</p>
                                </div>
                            </div>
                        </n-card>
                    </div>

                </div>
            </div>
        </div>

        <!-- Modal Reprogramar -->
        <n-modal v-model:show="showRescheduleModal">
            <n-card
                style="width: 450px"
                title="Reprogramar Visita"
                :bordered="false"
                size="huge"
                role="dialog"
                aria-modal="true"
            >
                <template #header-extra>
                    <n-icon size="24" :component="TimeOutline" class="text-indigo-500" />
                </template>
                
                <n-form :model="rescheduleForm">
                    <n-form-item label="Nueva Fecha y Hora" path="scheduled_at">
                        <n-date-picker 
                            v-model:value="rescheduleForm.scheduled_at" 
                            type="datetime" 
                            clearable 
                            class="w-full"
                        />
                    </n-form-item>
                    <n-form-item label="Motivo de Reprogramación (Opcional)" path="reschedule_reason">
                        <n-input 
                            v-model:value="rescheduleForm.reschedule_reason" 
                            type="textarea" 
                            placeholder="Ej: El cliente tuvo una emergencia..."
                            :autosize="{ minRows: 3 }"
                        />
                    </n-form-item>
                </n-form>
                
                <template #footer>
                    <div class="flex justify-end gap-3">
                        <n-button @click="showRescheduleModal = false">Cancelar</n-button>
                        <n-button type="primary" @click="submitReschedule">Guardar Cambios</n-button>
                    </div>
                </template>
            </n-card>
        </n-modal>

        <!-- Modal Rechazar -->
        <n-modal v-model:show="showRejectModal">
            <n-card
                style="width: 450px"
                title="Rechazar Visita"
                :bordered="false"
                size="huge"
                role="dialog"
                aria-modal="true"
            >
                <template #header-extra>
                    <n-icon size="24" :component="CloseCircleOutline" class="text-red-500" />
                </template>
                
                <n-form>
                    <n-form-item label="Motivo del Rechazo" required>
                        <n-input 
                            v-model:value="rejectReason" 
                            type="textarea" 
                            placeholder="Ej: El prospecto no está interesado, datos de contacto incorrectos..."
                            :autosize="{ minRows: 4 }"
                        />
                    </n-form-item>
                </n-form>
                
                <template #footer>
                    <div class="flex justify-end gap-3">
                        <n-button @click="showRejectModal = false">Cancelar</n-button>
                        <n-button type="error" @click="submitReject">Confirmar Rechazo</n-button>
                    </div>
                </template>
            </n-card>
        </n-modal>

        <CompleteVisitModal
            v-model:show="showCompleteModal"
            :visit-id="visit.id"
            :system-type="visit.system_of_interest"
            @completed="console.log('Visita completada')"
        />

    </AppLayout>
</template>
