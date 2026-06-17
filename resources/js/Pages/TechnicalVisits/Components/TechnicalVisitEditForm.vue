<script setup>
import { ref, watch } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import { NForm, NGrid, NFormItemGridItem, NInput, NSelect, NDatePicker, NSwitch, NUpload, NButton, NDynamicInput, NInputNumber, NIcon, NPopconfirm, NDivider, NText, NP } from 'naive-ui';
import { CloudUploadOutline, TrashOutline, AttachOutline, ImageOutline, DocumentTextOutline, CloudDownloadOutline } from '@vicons/ionicons5';

const props = defineProps({
    clients: Array,
    sales_reps: Array,
    visit: Object,
});

const form = useForm({
    business_name: props.visit?.business_name || null,
    first_name: props.visit?.first_name || null,
    paternal_surname: props.visit?.paternal_surname || null,
    maternal_surname: props.visit?.maternal_surname || null,
    scheduled_at: props.visit?.scheduled_at ? new Date(props.visit.scheduled_at).getTime() : null,
    phone: props.visit?.phone || null,
    service_number: props.visit?.service_number || null,
    rate_type: props.visit?.rate_type || null,
    property_use: props.visit?.property_use || null,
    requires_long_ladder: props.visit?.requires_long_ladder || false,
    property_floors: props.visit?.property_floors || null,
    number_of_wires: props.visit?.number_of_wires || null,
    google_maps_link: props.visit?.google_maps_link || null,
    lead_source: props.visit?.lead_source || null,
    sales_rep_id: props.visit?.sales_rep_id || null,
    internal_notes: props.visit?.internal_notes || null,
    documents: [],
    // Dirección
    road_type: props.visit?.road_type || null,
    street: props.visit?.street || null,
    exterior_number: props.visit?.exterior_number || null,
    interior_number: props.visit?.interior_number || null,
    neighborhood: props.visit?.neighborhood || null,
    municipality: props.visit?.municipality || null,
    state: props.visit?.state || null,
    zip_code: props.visit?.zip_code || null,
    country: props.visit?.country || 'México',
    // Sistema
    system_of_interest: props.visit?.system_of_interest || null,
    module_quantity: props.visit?.module_quantity || null,
    module_brand: props.visit?.module_brand || null,
    module_capacity: props.visit?.module_capacity ? Number(props.visit.module_capacity) : null,
    budget: props.visit?.budget ? Number(props.visit.budget) : null,
    gross_installed_capacity: props.visit?.gross_installed_capacity ? Number(props.visit.gross_installed_capacity) : null,
    estimated_daily_generation: props.visit?.estimated_daily_generation ? Number(props.visit.estimated_daily_generation) : null,
    estimated_monthly_generation: props.visit?.estimated_monthly_generation ? Number(props.visit.estimated_monthly_generation) : null,
    estimated_monthly_saving: props.visit?.estimated_monthly_saving ? Number(props.visit.estimated_monthly_saving) : null,
    battery_quantity: props.visit?.battery_quantity || null,
    battery_brand: props.visit?.battery_brand || null,
    battery_capacity: props.visit?.battery_capacity ? Number(props.visit.battery_capacity) : null,
    backup_devices: props.visit?.backup_devices || [],
    status: props.visit?.status || 'Pendiente',
    reschedule_reason: props.visit?.reschedule_reason || null,
    rejection_reason: props.visit?.rejection_reason || null,
});

const rateTypeOptions = ['01', '1A', '1B', '1C', '1D', '1E', '1F', 'DAC', 'PDBT', 'GDBT', 'GDMTO', 'GDMTH', '00'].map(v => ({ label: v, value: v }));
const propertyUseOptions = ['Residencial', 'Comercial', 'Industrial'].map(v => ({ label: v, value: v }));
const systemInterestOptions = ['Interconectado', 'Autónomo', 'Back-up', 'Bombeo'].map(v => ({ label: v, value: v }));
const statusOptions = ['Pendiente', 'Reprogramada', 'Aceptada', 'Terminada', 'Rechazada'].map(v => ({ label: v, value: v }));
const salesRepsOptions = props.sales_reps?.map(s => ({ label: s.name, value: s.id })) || [];

const leadSourceOptions = [
    { label: 'Facebook', value: 'Facebook' },
    { label: 'Instagram', value: 'Instagram' },
    { label: 'Google / Buscador', value: 'Google' },
    { label: 'Recomendación', value: 'Recomendación' },
    { label: 'Lona / Espectacular', value: 'Espectacular' },
    { label: 'Otro', value: 'Otro' }
];

const roadTypeOptions = [
    { label: 'Calle', value: 'Calle' },
    { label: 'Avenida', value: 'Avenida' },
    { label: 'Boulevard', value: 'Boulevard' },
    { label: 'Circuito', value: 'Circuito' },
    { label: 'Cerrada', value: 'Cerrada' },
    { label: 'Privada', value: 'Privada' },
    { label: 'Prolongación', value: 'Prolongación' },
    { label: 'Carretera', value: 'Carretera' },
    { label: 'Camino', value: 'Camino' },
    { label: 'Pasaje', value: 'Pasaje' },
    { label: 'Andador', value: 'Andador' },
];

watch([() => form.module_quantity, () => form.module_capacity], ([qty, cap]) => {
    if (qty && cap) form.gross_installed_capacity = Number(((qty * cap) / 1000).toFixed(2));
    else form.gross_installed_capacity = null;
});

watch(() => form.gross_installed_capacity, (val) => {
    if (val && form.system_of_interest === 'Interconectado') {
        form.estimated_daily_generation = Number((val * 3.76).toFixed(2));
    } else {
        form.estimated_daily_generation = null;
    }
});

watch(() => form.estimated_daily_generation, (val) => {
    if (val && form.system_of_interest === 'Interconectado') {
        form.estimated_monthly_generation = Number((val * 30).toFixed(2));
    } else {
        form.estimated_monthly_generation = null;
    }
});

watch([() => form.estimated_monthly_generation, () => form.rate_type, () => form.system_of_interest], ([gen, rate, system]) => {
    if (gen && rate && system === 'Interconectado') {
        let multiplier = 0;
        if (['01', '1A', '1B', '1C', '1D', '1E', '1F'].includes(rate)) multiplier = 4;
        else if (rate === 'DAC') multiplier = 7;
        else if (['PDBT', 'GDBT'].includes(rate)) multiplier = 4.4;
        else if (['GDMTO', 'GDMTH'].includes(rate)) multiplier = 3.3;
        
        form.estimated_monthly_saving = multiplier > 0 ? Number((gen * multiplier).toFixed(2)) : null;
    } else {
        form.estimated_monthly_saving = null;
    }
});

const handleUpload = ({ fileList }) => { form.documents = fileList.map(f => f.file); };

const submit = () => {
    const payload = form.transform((data) => ({
        ...data,
        scheduled_at: data.scheduled_at ? new Date(data.scheduled_at).toLocaleString('sv-SE').replace(',', '') : null
    }));
    
    if (props.visit?.id) {
        payload.put(route('technical-visits.update', props.visit.id), { preserveScroll: true });
    } else {
        payload.post(route('technical-visits.store'), { preserveScroll: true });
    }
};

const onCreateDevice = () => ({ concept: '', hours: 0 });

// --- MANEJO DE ARCHIVOS EXISTENTES ---
const getFileIcon = (fileName) => {
    const ext = fileName?.split('.').pop()?.toLowerCase();
    if (['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(ext)) return ImageOutline;
    if (['pdf', 'doc', 'docx', 'txt'].includes(ext)) return DocumentTextOutline;
    return AttachOutline;
};

const deleteExistingFile = (fileId) => {
    router.delete(route('media.delete-file', fileId), {
        preserveScroll: true,
        onSuccess: () => {
            // No necesitamos notificación; el componente se recargará solo
        },
    });
};
</script>

<template>
    <n-form @submit.prevent="submit" size="large" class="bg-white p-6 rounded-lg shadow-sm">
        <n-grid :cols="24" :x-gap="24">
            <n-form-item-grid-item :span="24">
                <h3 class="text-lg font-semibold text-gray-700 border-b pb-2">Datos Generales</h3>
            </n-form-item-grid-item>

            <template v-if="visit">
                <n-form-item-grid-item :span="12" label="Estatus" :validation-status="form.errors.status ? 'error' : undefined" :feedback="form.errors.status">
                    <n-select v-model:value="form.status" :options="statusOptions" filterable placeholder="Selecciona el estatus" />
                </n-form-item-grid-item>
                <n-form-item-grid-item v-if="form.status === 'Reprogramada'" :span="12" label="Motivo de Reprogramación" :validation-status="form.errors.reschedule_reason ? 'error' : undefined" :feedback="form.errors.reschedule_reason">
                    <n-input v-model:value="form.reschedule_reason" type="textarea" :rows="1" placeholder="Explica la razón..." />
                </n-form-item-grid-item>
                <n-form-item-grid-item v-if="form.status === 'Rechazada'" :span="12" label="Motivo del Rechazo" :validation-status="form.errors.rejection_reason ? 'error' : undefined" :feedback="form.errors.rejection_reason">
                    <n-input v-model:value="form.rejection_reason" type="textarea" :rows="1" placeholder="Explica la razón del rechazo..." />
                </n-form-item-grid-item>
            </template>

            <n-form-item-grid-item :span="12" label="Razón Social" :validation-status="form.errors.business_name ? 'error' : undefined" :feedback="form.errors.business_name">
                <n-input v-model:value="form.business_name" placeholder="Ej. Empresa SA de CV (Opcional)" />
            </n-form-item-grid-item>
            <n-form-item-grid-item :span="12" label="Nombre(s)" :validation-status="form.errors.first_name ? 'error' : undefined" :feedback="form.errors.first_name">
                <n-input v-model:value="form.first_name" placeholder="Ej. Juan" />
            </n-form-item-grid-item>
            <n-form-item-grid-item :span="12" label="Apellido Paterno" :validation-status="form.errors.paternal_surname ? 'error' : undefined" :feedback="form.errors.paternal_surname">
                <n-input v-model:value="form.paternal_surname" placeholder="Ej. Pérez" />
            </n-form-item-grid-item>
            <n-form-item-grid-item :span="12" label="Apellido Materno" :validation-status="form.errors.maternal_surname ? 'error' : undefined" :feedback="form.errors.maternal_surname">
                <n-input v-model:value="form.maternal_surname" placeholder="Ej. Gómez" />
            </n-form-item-grid-item>
            <n-form-item-grid-item :span="12" label="Fecha y horario programado" :validation-status="form.errors.scheduled_at ? 'error' : undefined" :feedback="form.errors.scheduled_at">
                <n-date-picker v-model:value="form.scheduled_at" type="datetime" clearable class="w-full" placeholder="Selecciona fecha y hora" />
            </n-form-item-grid-item>
            <n-form-item-grid-item :span="12" label="Número de Teléfono" :validation-status="form.errors.phone ? 'error' : undefined" :feedback="form.errors.phone">
                <n-input v-model:value="form.phone" placeholder="Ej. 3312345678" />
            </n-form-item-grid-item>
            <n-form-item-grid-item :span="12" label="Vendedor" :validation-status="form.errors.sales_rep_id ? 'error' : undefined" :feedback="form.errors.sales_rep_id">
                <n-select v-model:value="form.sales_rep_id" :options="salesRepsOptions" filterable placeholder="Selecciona el vendedor" />
            </n-form-item-grid-item>
            <n-form-item-grid-item :span="12" label="¿De dónde se enteró de nosotros?" :validation-status="form.errors.lead_source ? 'error' : undefined" :feedback="form.errors.lead_source">
                <n-select v-model:value="form.lead_source" :options="leadSourceOptions" placeholder="Selecciona una opción" filterable />
            </n-form-item-grid-item>

            <n-form-item-grid-item :span="24">
                <h3 class="text-lg font-semibold text-gray-700 border-b pb-2 mt-4">Datos del Inmueble y Servicio</h3>
            </n-form-item-grid-item>

            <n-form-item-grid-item :span="8" label="Número de servicio" :validation-status="form.errors.service_number ? 'error' : undefined" :feedback="form.errors.service_number">
                <n-input v-model:value="form.service_number" placeholder="Ej. 123456789012" />
            </n-form-item-grid-item>
            <n-form-item-grid-item :span="8" label="Tipo de tarifa" :validation-status="form.errors.rate_type ? 'error' : undefined" :feedback="form.errors.rate_type">
                <n-select v-model:value="form.rate_type" :options="rateTypeOptions" filterable placeholder="Selecciona tarifa" />
            </n-form-item-grid-item>
            <n-form-item-grid-item :span="8" label="Uso del Inmueble" :validation-status="form.errors.property_use ? 'error' : undefined" :feedback="form.errors.property_use">
                <n-select v-model:value="form.property_use" :options="propertyUseOptions" placeholder="Selecciona uso" />
            </n-form-item-grid-item>
            <n-form-item-grid-item :span="8" label="Número de pisos" :validation-status="form.errors.property_floors ? 'error' : undefined" :feedback="form.errors.property_floors">
                <n-input-number v-model:value="form.property_floors" :min="1" class="w-full" placeholder="Ej. 2" />
            </n-form-item-grid-item>
            <n-form-item-grid-item :span="8" label="Número de hilos" :validation-status="form.errors.number_of_wires ? 'error' : undefined" :feedback="form.errors.number_of_wires">
                <n-input-number v-model:value="form.number_of_wires" :min="1" class="w-full" placeholder="Ej. 2 o 3" />
            </n-form-item-grid-item>
            <n-form-item-grid-item :span="8" label="Requiere escalera larga" :validation-status="form.errors.requires_long_ladder ? 'error' : undefined" :feedback="form.errors.requires_long_ladder">
                <n-switch v-model:value="form.requires_long_ladder" />
            </n-form-item-grid-item>
            <n-form-item-grid-item :span="24" label="Ubicación (Enlace de Google Maps)" :validation-status="form.errors.google_maps_link ? 'error' : undefined" :feedback="form.errors.google_maps_link">
                <n-input v-model:value="form.google_maps_link" type="url" placeholder="https://goo.gl/maps/..." />
            </n-form-item-grid-item>

            <n-form-item-grid-item :span="24">
                <h3 class="text-lg font-semibold text-gray-700 border-b pb-2 mt-4">Dirección</h3>
            </n-form-item-grid-item>

            <n-form-item-grid-item :span="6" label="Tipo de Vialidad" :validation-status="form.errors.road_type ? 'error' : undefined" :feedback="form.errors.road_type">
                <n-select v-model:value="form.road_type" :options="roadTypeOptions" placeholder="Calle, Av..." clearable />
            </n-form-item-grid-item>
            <n-form-item-grid-item :span="12" label="Calle" :validation-status="form.errors.street ? 'error' : undefined" :feedback="form.errors.street">
                <n-input v-model:value="form.street" placeholder="Ej. Av. Principal" />
            </n-form-item-grid-item>
            <n-form-item-grid-item :span="3" label="No. Ext" :validation-status="form.errors.exterior_number ? 'error' : undefined" :feedback="form.errors.exterior_number">
                <n-input v-model:value="form.exterior_number" placeholder="123" />
            </n-form-item-grid-item>
            <n-form-item-grid-item :span="3" label="No. Int" :validation-status="form.errors.interior_number ? 'error' : undefined" :feedback="form.errors.interior_number">
                <n-input v-model:value="form.interior_number" placeholder="4B" />
            </n-form-item-grid-item>
            <n-form-item-grid-item :span="12" label="Colonia" :validation-status="form.errors.neighborhood ? 'error' : undefined" :feedback="form.errors.neighborhood">
                <n-input v-model:value="form.neighborhood" placeholder="Ej. Centro" />
            </n-form-item-grid-item>
            <n-form-item-grid-item :span="6" label="C.P." :validation-status="form.errors.zip_code ? 'error' : undefined" :feedback="form.errors.zip_code">
                <n-input v-model:value="form.zip_code" placeholder="00000" />
            </n-form-item-grid-item>
            <n-form-item-grid-item :span="6" label="País" :validation-status="form.errors.country ? 'error' : undefined" :feedback="form.errors.country">
                <n-input v-model:value="form.country" placeholder="México" />
            </n-form-item-grid-item>
            <n-form-item-grid-item :span="12" label="Estado" :validation-status="form.errors.state ? 'error' : undefined" :feedback="form.errors.state">
                <n-input v-model:value="form.state" placeholder="Ej. Jalisco" />
            </n-form-item-grid-item>
            <n-form-item-grid-item :span="12" label="Municipio" :validation-status="form.errors.municipality ? 'error' : undefined" :feedback="form.errors.municipality">
                <n-input v-model:value="form.municipality" placeholder="Ej. Guadalajara" />
            </n-form-item-grid-item>

            <n-form-item-grid-item :span="24">
                <h3 class="text-lg font-semibold text-gray-700 border-b pb-2 mt-4">Sistema y Cotización</h3>
            </n-form-item-grid-item>

            <n-form-item-grid-item :span="24" label="Añadir sistema de interés" :validation-status="form.errors.system_of_interest ? 'error' : undefined" :feedback="form.errors.system_of_interest">
                <n-select v-model:value="form.system_of_interest" :options="systemInterestOptions" placeholder="Selecciona tipo de sistema" />
            </n-form-item-grid-item>

            <template v-if="form.system_of_interest && form.system_of_interest !== 'Back-up'">
                <n-form-item-grid-item :span="8" label="Número de Módulos" :validation-status="form.errors.module_quantity ? 'error' : undefined" :feedback="form.errors.module_quantity">
                    <n-input-number v-model:value="form.module_quantity" class="w-full" placeholder="Ej. 10" />
                </n-form-item-grid-item>
                <n-form-item-grid-item :span="8" label="Marca de Módulos" :validation-status="form.errors.module_brand ? 'error' : undefined" :feedback="form.errors.module_brand">
                    <n-input v-model:value="form.module_brand" placeholder="Ej. Jinko Solar" />
                </n-form-item-grid-item>
                <n-form-item-grid-item :span="8" label="Capacidad de cada módulo (Wp)" :validation-status="form.errors.module_capacity ? 'error' : undefined" :feedback="form.errors.module_capacity">
                    <n-input-number v-model:value="form.module_capacity" :step="0.01" class="w-full" placeholder="Ej. 550" />
                </n-form-item-grid-item>
                
                <n-form-item-grid-item :span="8" label="Cap. Bruta Instalada (kWp)" :validation-status="form.errors.gross_installed_capacity ? 'error' : undefined" :feedback="form.errors.gross_installed_capacity">
                    <n-input-number v-model:value="form.gross_installed_capacity" disabled class="w-full" placeholder="Calculado automáticamente" />
                </n-form-item-grid-item>
            </template>

            <template v-if="form.system_of_interest === 'Interconectado'">
                <n-form-item-grid-item :span="8" label="Generación diaria est. (kWh)" :validation-status="form.errors.estimated_daily_generation ? 'error' : undefined" :feedback="form.errors.estimated_daily_generation">
                    <n-input-number v-model:value="form.estimated_daily_generation" disabled class="w-full" placeholder="Calculado automáticamente" />
                </n-form-item-grid-item>
                <n-form-item-grid-item :span="8" label="Generación mensual est. (kWh)" :validation-status="form.errors.estimated_monthly_generation ? 'error' : undefined" :feedback="form.errors.estimated_monthly_generation">
                    <n-input-number v-model:value="form.estimated_monthly_generation" disabled class="w-full" placeholder="Calculado automáticamente" />
                </n-form-item-grid-item>
                <n-form-item-grid-item :span="8" label="Ahorro mensual est. (MXN)" :validation-status="form.errors.estimated_monthly_saving ? 'error' : undefined" :feedback="form.errors.estimated_monthly_saving">
                    <n-input-number v-model:value="form.estimated_monthly_saving" disabled class="w-full" placeholder="Calculado automáticamente" />
                </n-form-item-grid-item>
            </template>

            <template v-if="['Autónomo', 'Back-up'].includes(form.system_of_interest)">
                <n-form-item-grid-item :span="8" label="Número de baterías" :validation-status="form.errors.battery_quantity ? 'error' : undefined" :feedback="form.errors.battery_quantity">
                    <n-input-number v-model:value="form.battery_quantity" class="w-full" placeholder="Ej. 2" />
                </n-form-item-grid-item>
                <n-form-item-grid-item :span="8" label="Marca de baterías" :validation-status="form.errors.battery_brand ? 'error' : undefined" :feedback="form.errors.battery_brand">
                    <n-input v-model:value="form.battery_brand" placeholder="Ej. Pylontech" />
                </n-form-item-grid-item>
                <n-form-item-grid-item :span="8" label="Capacidad Batería (kWh)" :validation-status="form.errors.battery_capacity ? 'error' : undefined" :feedback="form.errors.battery_capacity">
                    <n-input-number v-model:value="form.battery_capacity" :step="0.01" class="w-full" placeholder="Ej. 4.8" />
                </n-form-item-grid-item>

                <n-form-item-grid-item :span="24" label="Equipos electrónicos a respaldar" :validation-status="form.errors.backup_devices ? 'error' : undefined" :feedback="form.errors.backup_devices">
                    <n-dynamic-input v-model:value="form.backup_devices" :on-create="onCreateDevice">
                        <template #create-button-default>Añadir equipo</template>
                        <template #default="{ value }">
                            <div class="flex gap-4 w-full">
                                <n-input v-model:value="value.concept" placeholder="Concepto (Ej. Refrigerador o Iluminación)" class="flex-1" />
                                <n-input-number v-model:value="value.hours" placeholder="Uso (Horas)" :min="0" class="w-32" />
                            </div>
                        </template>
                    </n-dynamic-input>
                </n-form-item-grid-item>
            </template>

            <n-form-item-grid-item :span="8" label="Presupuesto (MXN)" :validation-status="form.errors.budget ? 'error' : undefined" :feedback="form.errors.budget">
                <n-input-number v-model:value="form.budget" :step="0.01" class="w-full" placeholder="Ej. 150000" />
            </n-form-item-grid-item>

            <n-form-item-grid-item :span="24">
                <h3 class="text-lg font-semibold text-gray-700 border-b pb-2 mt-4">Información Adicional</h3>
            </n-form-item-grid-item>

            <n-form-item-grid-item :span="24" label="Notas Internas" :validation-status="form.errors.internal_notes ? 'error' : undefined" :feedback="form.errors.internal_notes">
                <n-input v-model:value="form.internal_notes" type="textarea" :rows="3" placeholder="Detalles u observaciones de la visita..." />
            </n-form-item-grid-item>
            
            <n-form-item-grid-item :span="24">
                <h3 class="text-lg font-semibold text-gray-700 border-b pb-2 mt-4">Documentos y Archivos</h3>
            </n-form-item-grid-item>

            <!-- Archivos existentes -->
            <n-form-item-grid-item v-if="visit?.media && visit.media.length > 0" :span="24" label="Archivos Actuales">
                <div class="grid grid-cols-1 gap-3 w-full">
                    <div v-for="file in visit.media" :key="file.id" 
                        class="flex items-center gap-3 p-3 rounded-lg border border-gray-200 bg-white hover:shadow-sm transition-shadow">
                        
                        <div class="w-8 h-8 rounded bg-gray-100 flex items-center justify-center text-gray-500">
                            <n-icon size="18">
                                <component :is="getFileIcon(file.name)" />
                            </n-icon>
                        </div>
                        
                        <div class="flex-1 min-w-0">
                            <a :href="file.url" target="_blank" class="block">
                                <p class="text-sm font-medium text-gray-700 truncate hover:text-blue-600 transition-colors">
                                    {{ file.name }}
                                </p>
                                <p class="text-xs text-gray-400">{{ file.size }}</p>
                            </a>
                        </div>

                        <div class="flex items-center gap-1">
                            <a :href="file.url" target="_blank" title="Descargar">
                                <n-button size="tiny" circle secondary type="info">
                                    <template #icon><n-icon><CloudDownloadOutline /></n-icon></template>
                                </n-button>
                            </a>
                            
                            <n-popconfirm
                                @positive-click="deleteExistingFile(file.id)"
                                positive-text="Sí, eliminar"
                                negative-text="Cancelar"
                            >
                                <template #trigger>
                                    <n-button size="tiny" circle secondary type="error">
                                        <template #icon><n-icon><TrashOutline /></n-icon></template>
                                    </n-button>
                                </template>
                                ¿Eliminar este archivo permanentemente?
                            </n-popconfirm>
                        </div>
                    </div>
                </div>
            </n-form-item-grid-item>

            <!-- Subir nuevos archivos -->
            <n-form-item-grid-item :span="24" label="Agregar Nuevos Archivos" :validation-status="form.errors.documents ? 'error' : undefined" :feedback="form.errors.documents">
                <n-upload multiple @change="handleUpload">
                    <n-button>
                        <template #icon><n-icon><CloudUploadOutline /></n-icon></template>
                        Seleccionar Archivos
                    </n-button>
                </n-upload>
            </n-form-item-grid-item>

            <n-form-item-grid-item :span="24" class="flex justify-end mt-4">
                <n-button type="primary" attr-type="submit" :loading="form.processing">
                    {{ visit ? 'Actualizar Visita Técnica' : 'Guardar Visita Técnica' }}
                </n-button>
            </n-form-item-grid-item>
        </n-grid>
    </n-form>
</template>