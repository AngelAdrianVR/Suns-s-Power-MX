<script setup>
import { ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { NForm, NGrid, NFormItemGridItem, NInput, NSelect, NDatePicker, NSwitch, NUpload, NButton, NDynamicInput, NInputNumber } from 'naive-ui';

const props = defineProps({
    clients: Array,
    sales_reps: Array,
});

const form = useForm({
    business_name: null,
    first_name: null,
    paternal_surname: null,
    maternal_surname: null,
    scheduled_at: null,
    phone: null,
    service_number: null,
    rate_type: null,
    property_use: null,
    requires_long_ladder: false,
    property_floors: null,
    number_of_wires: null,
    Maps_link: null,
    lead_source: null,
    sales_rep_id: null,
    internal_notes: null,
    documents: [],
    system_of_interest: null,
    module_quantity: null,
    module_brand: null,
    module_capacity: null,
    budget: null,
    gross_installed_capacity: null,
    estimated_daily_generation: null,
    estimated_monthly_generation: null,
    estimated_monthly_saving: null,
    battery_quantity: null,
    battery_brand: null,
    battery_capacity: null,
    backup_devices: []
});

const rateTypeOptions = ['01', '1A', '1B', '1C', '1D', '1E', '1F', 'DAC', 'PDBT', 'GDBT', 'GDMTO', 'GDMTH', '00'].map(v => ({ label: v, value: v }));
const propertyUseOptions = ['Residencial', 'Comercial', 'Industrial'].map(v => ({ label: v, value: v }));
const systemInterestOptions = ['Interconectado', 'Autónomo', 'Back-up', 'Bombeo'].map(v => ({ label: v, value: v }));
const salesRepsOptions = props.sales_reps?.map(s => ({ label: s.name, value: s.id })) || [];

const leadSourceOptions = [
    { label: 'Facebook', value: 'Facebook' },
    { label: 'Instagram', value: 'Instagram' },
    { label: 'Google / Buscador', value: 'Google' },
    { label: 'Recomendación', value: 'Recomendación' },
    { label: 'Lona / Espectacular', value: 'Espectacular' },
    { label: 'Otro', value: 'Otro' }
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
    form.transform((data) => ({
        ...data,
        scheduled_at: data.scheduled_at ? new Date(data.scheduled_at).toLocaleString('sv-SE').replace(',', '') : null
    })).post(route('technical-visits.store'), { preserveScroll: true });
};

const onCreateDevice = () => ({ concept: '', hours: 0 });
</script>

<template>
    <n-form @submit.prevent="submit" size="large" class="bg-white p-6 rounded-lg shadow-sm">
        <n-grid :cols="24" :x-gap="24">
            <n-form-item-grid-item :span="24">
                <h3 class="text-lg font-semibold text-gray-700 border-b pb-2">Datos Generales</h3>
            </n-form-item-grid-item>

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
            <n-form-item-grid-item :span="24" label="Ubicación (Enlace de Google Maps)" :validation-status="form.errors.Maps_link ? 'error' : undefined" :feedback="form.errors.Maps_link">
                <n-input v-model:value="form.Maps_link" type="url" placeholder="https://goo.gl/maps/..." />
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
            
            <n-form-item-grid-item :span="24" label="Adjuntar Documentos" :validation-status="form.errors.documents ? 'error' : undefined" :feedback="form.errors.documents">
                <n-upload multiple @change="handleUpload">
                    <n-button>Seleccionar Archivos</n-button>
                </n-upload>
            </n-form-item-grid-item>

            <n-form-item-grid-item :span="24" class="flex justify-end mt-4">
                <n-button type="primary" attr-type="submit" :loading="form.processing">
                    Guardar Visita Técnica
                </n-button>
            </n-form-item-grid-item>
        </n-grid>
    </n-form>
</template>