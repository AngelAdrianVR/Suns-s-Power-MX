<script setup>
import { ref, computed, watch } from 'vue';
import { useForm, Link, router } from '@inertiajs/vue3';
import { usePermissions } from '@/Composables/usePermissions';
import { 
    NForm, NFormItem, NInput, NButton, NCard, NIcon, NGrid, NGridItem, 
    createDiscreteApi, NSelect, NDatePicker, NInputNumber, NSpin, NTooltip,
    NModal, NPopconfirm, NEmpty
} from 'naive-ui';
import { 
    SaveOutline, PersonOutline, ConstructOutline, 
    LocationOutline, CashOutline, DocumentTextOutline, BriefcaseOutline,
    PersonAddOutline, RefreshOutline, FlashOutline, SpeedometerOutline,
    HardwareChipOutline, AddOutline, CreateOutline, TrashOutline, 
    CheckmarkCircleOutline, CloseOutline
} from '@vicons/ionicons5';
import axios from 'axios';

const props = defineProps({
    order: {
        type: Object,
        default: null
    },
    clients: {
        type: Array,
        default: () => []
    },
    technicians: {
        type: Array,
        default: () => []
    },
    sales_reps: {
        type: Array,
        default: () => []
    },
    // NUEVO PROP
    system_types: {
        type: Array,
        default: () => []
    }
});

const { hasPermission } = usePermissions();
const { notification } = createDiscreteApi(['notification']);
const formRef = ref(null);
const loadingClientData = ref(false);
const loadingClientsList = ref(false);

const isEdit = computed(() => !!props.order);

// --- UTILIDADES ---
const formatInitialDate = (dateString) => {
    if (!dateString) return null;
    if (dateString.includes('T')) {
        return dateString.replace('T', ' ').substring(0, 19);
    }
    return dateString;
};

// --- OPCIONES PARA SELECTS ---
const statusOptions = [
    { label: 'Cotización', value: 'Cotización' },
    { label: 'Aceptado', value: 'Aceptado' },
    { label: 'En Proceso', value: 'En Proceso' },
    { label: 'Completado', value: 'Completado' },
    { label: 'Facturado', value: 'Facturado' },
    { label: 'Cancelado', value: 'Cancelado' }, 
];

const rateTypeOptions = [
    { label: '01', value: '01' }, { label: '1A', value: '1A' }, { label: '1B', value: '1B' },
    { label: '1C', value: '1C' }, { label: '1D', value: '1D' }, { label: '1F', value: '1F' },
    { label: 'DAC', value: 'DAC' }, { label: 'PDBT', value: 'PDBT' }, { label: 'GDBT', value: 'GDBT' },
    { label: 'GDMTO', value: 'GDMTO' }, { label: 'GDMTH', value: 'GDMTH' }, { label: 'N/A', value: 'N/A' },
];

const voltageOptions = [
    { label: '110V', value: '110V' },
    { label: '220V', value: '220V' },
    { label: '440V', value: '440V' }
];

const wireOptions = [
    { label: '1 Hilo', value: 1 },
    { label: '2 Hilos', value: 2 },
    { label: '3 Hilos', value: 3 }
];

const mexicoStates = [
    { label: 'Aguascalientes', value: 'Aguascalientes' }, { label: 'Baja California', value: 'Baja California' },
    { label: 'Baja California Sur', value: 'Baja California Sur' }, { label: 'Campeche', value: 'Campeche' },
    { label: 'Chiapas', value: 'Chiapas' }, { label: 'Chihuahua', value: 'Chihuahua' },
    { label: 'Ciudad de México', value: 'Ciudad de México' }, { label: 'Coahuila', value: 'Coahuila' },
    { label: 'Colima', value: 'Colima' }, { label: 'Durango', value: 'Durango' },
    { label: 'Estado de México', value: 'Estado de México' }, { label: 'Guanajuato', value: 'Guanajuato' },
    { label: 'Guerrero', value: 'Guerrero' }, { label: 'Hidalgo', value: 'Hidalgo' },
    { label: 'Jalisco', value: 'Jalisco' }, { label: 'Michoacán', value: 'Michoacán' },
    { label: 'Morelos', value: 'Morelos' }, { label: 'Nayarit', value: 'Nayarit' },
    { label: 'Nuevo León', value: 'Nuevo León' }, { label: 'Oaxaca', value: 'Oaxaca' },
    { label: 'Puebla', value: 'Puebla' }, { label: 'Querétaro', value: 'Querétaro' },
    { label: 'Quintana Roo', value: 'Quintana Roo' }, { label: 'San Luis Potosí', value: 'San Luis Potosí' },
    { label: 'Sinaloa', value: 'Sinaloa' }, { label: 'Sonora', value: 'Sonora' },
    { label: 'Tabasco', value: 'Tabasco' }, { label: 'Tamaulipas', value: 'Tamaulipas' },
    { label: 'Tlaxcala', value: 'Tlaxcala' }, { label: 'Veracruz', value: 'Veracruz' },
    { label: 'Yucatán', value: 'Yucatán' }, { label: 'Zacatecas', value: 'Zacatecas' }
];

const clientOptions = computed(() => props.clients.map(c => ({ label: c.name, value: c.id })));
const techOptions = computed(() => props.technicians.map(t => ({ label: t.name, value: t.id })));
const salesOptions = computed(() => props.sales_reps.map(s => ({ label: s.name, value: s.id })));

// --- LÓGICA DINÁMICA DE TIPO DE SISTEMA ---
const systemTypeOptions = computed(() => {
    const types = props.system_types.map(sys => ({ label: sys.name, value: sys.name }));
    types.push({ label: 'Otro', value: 'Otro' }); // Mantenemos "Otro" por retrocompatibilidad
    return types;
});

let initialSelectedSystemOption = null;
let initialCustomSystemText = '';

if (props.order?.system_type) {
    const exists = props.system_types.some(s => s.name === props.order.system_type);
    if (exists) {
        initialSelectedSystemOption = props.order.system_type;
    } else {
        initialSelectedSystemOption = 'Otro';
        initialCustomSystemText = props.order.system_type;
    }
}

const selectedSystemOption = ref(initialSelectedSystemOption);
const customSystemTypeText = ref(initialCustomSystemText);


// ================= ESTADO MODAL TIPOS DE SISTEMA =================
const showSystemModal = ref(false);
const editingSystemId = ref(null);
const editSystemName = ref('');

const systemForm = useForm({ name: '' });

const handleAddSystem = () => {
    if(!systemForm.name) return;
    systemForm.post(route('system-types.store'), {
        preserveScroll: true,
        onSuccess: () => {
            selectedSystemOption.value = systemForm.name; // Seleccionar automáticamente el recién creado
            systemForm.reset();
            notification.success({ title: 'Creado', content: 'Tipo de sistema agregado correctamente.' });
        }
    });
};

const startEditSystem = (sys) => {
    editingSystemId.value = sys.id;
    editSystemName.value = sys.name;
};

const saveEditSystem = (sys) => {
    router.put(route('system-types.update', sys.id), { name: editSystemName.value }, {
        preserveScroll: true,
        onSuccess: () => {
            if (selectedSystemOption.value === sys.name) {
                selectedSystemOption.value = editSystemName.value; // Actualiza el select si estaba seleccionado
            }
            editingSystemId.value = null;
            notification.success({ title: 'Actualizado', content: 'Nombre actualizado.' });
        }
    });
};

const handleDeleteSystem = (id) => {
    router.delete(route('system-types.destroy', id), {
        preserveScroll: true,
        onSuccess: () => {
            notification.success({ title: 'Eliminado', content: 'Tipo de sistema eliminado.' });
            // Si el que se borró era el que estaba seleccionado y no es "Otro", lo limpiamos
            if (selectedSystemOption.value !== 'Otro' && !props.system_types.some(s => s.name === selectedSystemOption.value && s.id !== id)) {
                selectedSystemOption.value = null;
            }
        }
    });
};


// --- ESTADO DEL FORMULARIO ---
const form = useForm({
    client_id: props.order?.client_id || null,
    technician_id: props.order?.technician_id || null,
    sales_rep_id: props.order?.sales_rep_id || null,
    status: props.order?.status || 'Cotización',
    start_date: formatInitialDate(props.order?.start_date) || null,
    
    service_number: props.order?.service_number || '',
    rate_type: props.order?.rate_type || null,
    system_type: props.order?.system_type || null, 
    meter_number: props.order?.meter_number || '',
    
    voltage: props.order?.voltage || null,
    number_of_wires: props.order?.number_of_wires || null,
    number_of_units: props.order?.number_of_units || null,
    unit_capacity: props.order?.unit_capacity ? Number(props.order.unit_capacity) : null,
    total_capacity: props.order?.total_capacity ? Number(props.order.total_capacity) : 0,

    total_amount: props.order ? Number(props.order.total_amount) : 0,
    
    installation_street: props.order?.installation_street || '',
    installation_exterior_number: props.order?.installation_exterior_number || '',
    installation_interior_number: props.order?.installation_interior_number || '',
    installation_neighborhood: props.order?.installation_neighborhood || '',
    installation_municipality: props.order?.installation_municipality || '',
    installation_state: props.order?.installation_state || '',
    installation_zip_code: props.order?.installation_zip_code || '',
    installation_country: props.order?.installation_country || 'México',
    installation_lat: props.order?.installation_lat ? parseFloat(props.order.installation_lat) : null,
    installation_lng: props.order?.installation_lng ? parseFloat(props.order.installation_lng) : null,

    notes: props.order?.notes || '',
});

const rules = {
    client_id: { required: true, type: 'number', message: 'Selecciona un cliente', trigger: ['blur', 'change'] },
    sales_rep_id: { required: true, type: 'number', message: 'Selecciona un vendedor', trigger: ['blur', 'change'] },
    total_amount: { required: true, type: 'number', min: 0, message: 'Requerido', trigger: 'blur' },
    installation_street: { required: true, message: 'La calle es obligatoria', trigger: 'blur' },
    installation_neighborhood: { required: true, message: 'La colonia es obligatoria', trigger: 'blur' }
};

// --- WATCHERS ---
watch(selectedSystemOption, (newVal) => {
    if (newVal !== 'Otro') {
        form.system_type = newVal;
    } else {
        form.system_type = customSystemTypeText.value;
    }
});

watch(customSystemTypeText, (newVal) => {
    if (selectedSystemOption.value === 'Otro') {
        form.system_type = newVal;
    }
});

watch([() => form.number_of_units, () => form.unit_capacity], ([units, capacity]) => {
    const u = Number(units) || 0;
    const c = Number(capacity) || 0;
    
    if (u > 0 && c > 0) {
        form.total_capacity = Number(((u * c) / 1000).toFixed(2));
    } else {
        form.total_capacity = 0;
    }
});

// --- FUNCIONES PRINCIPALES ---
const refreshClientsList = () => {
    loadingClientsList.value = true;
    router.reload({
        only: ['clients'],
        onFinish: () => {
            loadingClientsList.value = false;
            notification.success({ title: 'Lista Actualizada', content: 'Se ha actualizado la lista de clientes.', duration: 2000 });
        }
    });
};

const handleClientChange = async (clientId) => {
    if (!clientId) return;
    
    loadingClientData.value = true;
    try {
        const response = await axios.get(route('api.clients.details', clientId));
        const data = response.data;

        form.installation_street = data.street || '';
        form.installation_exterior_number = data.exterior_number || '';
        form.installation_interior_number = data.interior_number || '';
        form.installation_neighborhood = data.neighborhood || '';
        form.installation_municipality = data.municipality || '';
        form.installation_state = data.state || '';
        form.installation_zip_code = data.zip_code || '';
        form.installation_country = data.country || 'México';
        
        form.installation_lat = data.lat ? parseFloat(data.lat) : null;
        form.installation_lng = data.lng ? parseFloat(data.lng) : null;

        notification.success({
            title: isEdit.value ? 'Dirección Actualizada' : 'Datos Cargados',
            content: 'Se han cargado los datos de dirección desde la ficha del cliente.',
            duration: 2000
        });

    } catch (error) {
        notification.warning({ title: 'Aviso', content: 'No se pudo obtener la dirección del cliente.' });
    } finally {
        loadingClientData.value = false;
    }
};

const submit = () => {
    formRef.value?.validate((errors) => {
        if (!errors) {
            if (isEdit.value) {
                form.put(route('service-orders.update', props.order.id), {
                    onSuccess: () => notification.success({ title: 'Orden Actualizada', content: 'Los cambios se han guardado correctamente.', duration: 3000 }),
                    onError: () => notification.error({ title: 'Error de Validación', content: 'Revisa los campos obligatorios.', duration: 4000 })
                });
            } else {
                form.post(route('service-orders.store'), {
                    onSuccess: () => notification.success({ title: 'Orden Creada', content: 'La orden de servicio se ha generado correctamente.', duration: 3000 }),
                    onError: () => notification.error({ title: 'Error de Validación', content: 'Revisa los campos obligatorios.', duration: 4000 })
                });
            }
        } else {
            notification.warning({ title: 'Campos Incompletos', content: 'Por favor completa la información requerida.', duration: 3000 });
        }
    });
};
</script>

<template>
    <n-form
        ref="formRef"
        :model="form"
        :rules="rules"
        label-placement="top"
        require-mark-placement="right-hanging"
        size="medium"
    >
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            
            <div class="lg:col-span-2 space-y-6">
                
                <!-- Tarjeta 1: Asignación -->
                <n-card :bordered="false" class="shadow-sm rounded-2xl">
                    <template #header>
                        <span class="text-gray-600 font-semibold flex items-center gap-2">
                            <n-icon :component="BriefcaseOutline" /> Detalles Generales
                        </span>
                    </template>

                    <n-grid x-gap="12" :cols="2">
                        <!-- Cliente con botón de recarga -->
                        <n-grid-item span="2">
                            <n-form-item label="Cliente" path="client_id">
                                <div class="flex gap-2 w-full">
                                    <n-select 
                                        v-model:value="form.client_id" 
                                        :options="clientOptions" 
                                        filterable 
                                        placeholder="Buscar cliente..."
                                        clearable
                                        @update:value="handleClientChange"
                                        class="flex-grow"
                                    />
                                    
                                    <n-tooltip trigger="hover">
                                        <template #trigger>
                                            <n-button tag="a" :href="route('clients.create')" target="_blank" secondary type="primary">
                                                <template #icon><n-icon><PersonAddOutline /></n-icon></template>
                                            </n-button>
                                        </template>
                                        Nuevo Cliente (Nueva Pestaña)
                                    </n-tooltip>

                                    <n-tooltip trigger="hover">
                                        <template #trigger>
                                            <n-button secondary @click="isEdit ? handleClientChange(form.client_id) : refreshClientsList()" :loading="isEdit ? loadingClientData : loadingClientsList">
                                                <template #icon><n-icon><RefreshOutline /></n-icon></template>
                                            </n-button>
                                        </template>
                                        {{ isEdit ? 'Resetear dirección a la del cliente' : 'Recargar Lista' }}
                                    </n-tooltip>
                                </div>
                            </n-form-item>
                        </n-grid-item>

                        <n-grid-item>
                            <n-form-item label="Vendedor" path="sales_rep_id">
                                <n-select v-model:value="form.sales_rep_id" :options="salesOptions" filterable />
                            </n-form-item>
                        </n-grid-item>

                        <n-grid-item>
                            <n-form-item label="Estatus" path="status">
                                <n-select v-model:value="form.status" :options="statusOptions" />
                            </n-form-item>
                        </n-grid-item>
                    </n-grid>
                </n-card>

                <!-- Tarjeta 2: Detalles Técnicos y Dirección -->
                <n-card :bordered="false" class="shadow-sm rounded-2xl relative">
                    <template #header>
                        <span class="text-gray-600 font-semibold flex items-center gap-2">
                            <n-icon :component="ConstructOutline" /> Logística e Instalación
                        </span>
                    </template>

                    <div v-if="loadingClientData" class="absolute inset-0 bg-white/60 z-10 flex items-center justify-center rounded-2xl">
                        <n-spin size="medium" description="Actualizando dirección..." />
                    </div>

                    <n-grid x-gap="12" :cols="2">
                        <n-grid-item>
                            <n-form-item label="Técnico (Opcional)" path="technician_id">
                                <n-select 
                                    v-model:value="form.technician_id" 
                                    :options="techOptions" 
                                    filterable
                                    clearable
                                >
                                    <template #prefix><n-icon :component="PersonOutline"/></template>
                                </n-select>
                            </n-form-item>
                        </n-grid-item>

                        <n-grid-item>
                            <n-form-item label="Fecha Programada" path="start_date">
                                <n-date-picker 
                                    v-model:formatted-value="form.start_date" 
                                    type="datetime" 
                                    value-format="yyyy-MM-dd HH:mm:ss"
                                    class="w-full"
                                    clearable
                                />
                            </n-form-item>
                        </n-grid-item>
                        
                        <n-grid-item>
                            <n-form-item label="Número de Servicio" path="service_number">
                                <n-input v-model:value="form.service_number" placeholder="Ej. 123456789">
                                    <template #prefix><n-icon :component="FlashOutline"/></template>
                                </n-input>
                            </n-form-item>
                        </n-grid-item>

                        <n-grid-item>
                            <n-form-item label="Tipo de Tarifa" path="rate_type">
                                <n-select v-model:value="form.rate_type" :options="rateTypeOptions" placeholder="Selecciona tarifa" filterable />
                            </n-form-item>
                        </n-grid-item>

                        <!-- AQUI ESTÁ EL NUEVO SELECTOR Y GESTOR DINÁMICO -->
                        <n-grid-item span="2">
                            <n-form-item label="Tipo de Sistema" path="system_type">
                                <div class="flex gap-2 w-full items-start">
                                    <div class="flex-grow flex gap-2">
                                        <n-select 
                                            v-model:value="selectedSystemOption" 
                                            :options="systemTypeOptions" 
                                            placeholder="Selecciona tipo de sistema"
                                            class="w-full"
                                            filterable
                                        >
                                            <template #prefix><n-icon :component="HardwareChipOutline"/></template>
                                        </n-select>
                                        
                                        <n-input 
                                            v-if="selectedSystemOption === 'Otro'"
                                            v-model:value="customSystemTypeText" 
                                            placeholder="Especifique tipo..."
                                            class="w-full"
                                        />
                                    </div>

                                    <n-tooltip trigger="hover">
                                        <template #trigger>
                                            <n-button 
                                                v-if="hasPermission('system_type.create')"
                                                secondary 
                                                type="primary" 
                                                @click="showSystemModal = true"
                                            >
                                                <template #icon><n-icon><AddOutline /></n-icon></template>
                                            </n-button>
                                        </template>
                                        Gestionar Tipos de Sistema
                                    </n-tooltip>
                                </div>
                            </n-form-item>
                        </n-grid-item>

                        <n-grid-item span="2">
                            <n-form-item label="Número de Medidor" path="meter_number">
                                <n-input v-model:value="form.meter_number" placeholder="Ingrese el número de serie del medidor">
                                    <template #prefix><n-icon :component="SpeedometerOutline"/></template>
                                </n-input>
                            </n-form-item>
                        </n-grid-item>

                    </n-grid>

                    <!-- TARJETA: ESPECIFICACIONES ELÉCTRICAS -->
                    <div class="mt-6 border-t pt-4">
                        <label class="text-gray-500 font-medium mb-3 text-sm flex items-center gap-1">
                            <n-icon :component="FlashOutline"/> Especificaciones Eléctricas
                        </label>

                        <n-grid x-gap="12" y-gap="2" cols="1 s:2 m:3" responsive="screen">
                            <n-grid-item>
                                <n-form-item label="Voltaje" path="voltage">
                                    <n-select v-model:value="form.voltage" :options="voltageOptions" placeholder="Selecciona" clearable />
                                </n-form-item>
                            </n-grid-item>

                            <n-grid-item>
                                <n-form-item label="Número de Hilos" path="number_of_wires">
                                    <n-select v-model:value="form.number_of_wires" :options="wireOptions" placeholder="Selecciona" clearable />
                                </n-form-item>
                            </n-grid-item>

                            <n-grid-item class="hidden m:block"></n-grid-item>

                            <n-grid-item>
                                <n-form-item label="Cantidad de Unidades" path="number_of_units">
                                    <n-input-number v-model:value="form.number_of_units" :min="0" placeholder="Ej. 10" class="w-full" clearable />
                                </n-form-item>
                            </n-grid-item>

                            <n-grid-item>
                                <n-form-item label="Capacidad Unitaria (Watts)" path="unit_capacity">
                                    <n-input-number v-model:value="form.unit_capacity" :min="0" placeholder="Ej. 550" class="w-full" clearable>
                                        <template #suffix>W</template>
                                    </n-input-number>
                                </n-form-item>
                            </n-grid-item>

                            <n-grid-item>
                                <n-form-item label="Capacidad Total (Calculada)" path="total_capacity">
                                    <n-input-number 
                                        v-model:value="form.total_capacity" 
                                        readonly 
                                        class="w-full bg-gray-50 font-bold" 
                                        :show-button="false"
                                    >
                                        <template #suffix><span class="text-indigo-600">KW</span></template>
                                    </n-input-number>
                                </n-form-item>
                            </n-grid-item>
                        </n-grid>
                    </div>

                    <div class="mt-4 border-t pt-4">
                        <label class="text-gray-500 font-medium mb-3 text-sm flex items-center gap-1">
                            <n-icon :component="LocationOutline"/> Dirección del Sitio
                        </label>
                        
                        <n-grid x-gap="12" y-gap="2" cols="1 s:2 m:4" responsive="screen">
                            <n-grid-item span="1 m:2">
                                <n-form-item label="Calle" path="installation_street">
                                    <n-input v-model:value="form.installation_street" placeholder="Av. Principal" :input-props="{ autocomplete: 'new-password' }" />
                                </n-form-item>
                            </n-grid-item>

                            <n-grid-item>
                                <n-form-item label="No. Exterior" path="installation_exterior_number">
                                    <n-input v-model:value="form.installation_exterior_number" placeholder="123" :input-props="{ autocomplete: 'off' }" />
                                </n-form-item>
                            </n-grid-item>

                            <n-grid-item>
                                <n-form-item label="No. Interior" path="installation_interior_number">
                                    <n-input v-model:value="form.installation_interior_number" placeholder="4B" :input-props="{ autocomplete: 'off' }" />
                                </n-form-item>
                            </n-grid-item>

                            <n-grid-item span="1 m:2">
                                <n-form-item label="Colonia" path="installation_neighborhood">
                                    <n-input v-model:value="form.installation_neighborhood" placeholder="Centro" :input-props="{ autocomplete: 'off' }" />
                                </n-form-item>
                            </n-grid-item>

                            <n-grid-item>
                                <n-form-item label="C.P." path="installation_zip_code">
                                    <n-input v-model:value="form.installation_zip_code" placeholder="00000" :input-props="{ autocomplete: 'off' }" />
                                </n-form-item>
                            </n-grid-item>

                            <n-grid-item>
                                <n-form-item label="Estado" path="installation_state">
                                    <n-select v-model:value="form.installation_state" filterable placeholder="Selecciona un estado" :options="mexicoStates" />
                                </n-form-item>
                            </n-grid-item>

                            <n-grid-item span="1 m:2">
                                <n-form-item label="Municipio" path="installation_municipality">
                                    <n-input v-model:value="form.installation_municipality" placeholder="Municipio" :input-props="{ autocomplete: 'off' }" />
                                </n-form-item>
                            </n-grid-item>

                            <n-grid-item span="1 m:2">
                                <n-form-item label="Latitud (Opcional)" path="installation_lat">
                                    <n-input-number v-model:value="form.installation_lat" placeholder="Ej. 20.659698" :show-button="false" class="w-full" />
                                </n-form-item>
                            </n-grid-item>

                            <n-grid-item span="1 m:2">
                                <n-form-item label="Longitud (Opcional)" path="installation_lng">
                                    <n-input-number v-model:value="form.installation_lng" placeholder="Ej. -103.349609" :show-button="false" class="w-full" />
                                </n-form-item>
                            </n-grid-item>
                        </n-grid>
                    </div>
                </n-card>
            </div>

            <!-- Columna Derecha: Financiero (1/3) -->
            <div class="space-y-6">
                <n-card :bordered="false" class="shadow-sm rounded-2xl bg-emerald-50/50">
                    <template #header>
                        <span class="text-emerald-800 font-semibold flex items-center gap-2">
                            <n-icon :component="CashOutline"/> Presupuesto
                        </span>
                    </template>
                    <n-form-item label="Monto Total" path="total_amount">
                        <n-input-number 
                            v-model:value="form.total_amount" 
                            :show-button="false"
                            class="w-full text-right font-mono"
                            :min="0"
                            :precision="2"
                        >
                            <template #prefix><span class="text-gray-500 mr-2">$</span></template>
                            <template #suffix>MXN</template>
                        </n-input-number>
                    </n-form-item>
                </n-card>

                <n-card :bordered="false" class="shadow-sm rounded-2xl">
                    <template #header>
                        <span class="text-gray-600 font-semibold flex items-center gap-2 text-sm">
                            <n-icon :component="DocumentTextOutline"/> Notas Adicionales
                        </span>
                    </template>
                    <n-input 
                        v-model:value="form.notes" 
                        type="textarea" 
                        placeholder="Instrucciones especiales..."
                        :autosize="{ minRows: 3 }"
                    />
                </n-card>

                <div class="flex flex-col gap-3 sticky top-6">
                    <n-button 
                        type="primary" 
                        size="large" 
                        block 
                        @click="submit" 
                        :loading="form.processing"
                        :disabled="form.processing || loadingClientData"
                        class="shadow-md"
                    >
                        <template #icon><n-icon><SaveOutline /></n-icon></template>
                        {{ isEdit ? 'Guardar Cambios' : 'Crear Orden' }}
                    </n-button>
                    
                    <Link :href="isEdit ? route('service-orders.show', order.id) : route('service-orders.index')" class="w-full">
                        <n-button block ghost :type="isEdit ? 'error' : 'default'">
                            Cancelar
                        </n-button>
                    </Link>
                </div>
            </div>

        </div>
    </n-form>

    <!-- MODAL: GESTIÓN DE TIPOS DE SISTEMA -->
    <n-modal v-model:show="showSystemModal" preset="card" class="max-w-md" title="Gestionar Tipos de Sistema">
        <div v-if="hasPermission('system_type.create')" class="flex gap-2 mb-6">
            <n-input v-model:value="systemForm.name" placeholder="Nuevo tipo (ej. Interconectado)" @keyup.enter="handleAddSystem" />
            <n-button type="primary" class="bg-indigo-600" @click="handleAddSystem" :loading="systemForm.processing" :disabled="!systemForm.name">
                Agregar
            </n-button>
        </div>
        
        <div class="space-y-2 max-h-96 overflow-y-auto pr-1">
            <div v-for="sys in system_types" :key="sys.id" class="flex justify-between items-center bg-gray-50 p-2 rounded-xl border border-gray-100 hover:border-gray-300 transition-colors">
                
                <span v-if="editingSystemId !== sys.id" class="font-medium text-gray-700 ml-2">{{ sys.name }}</span>
                <n-input v-else v-model:value="editSystemName" size="small" class="w-full mr-2 ml-1" @keyup.enter="saveEditSystem(sys)" />

                <div class="flex gap-1">
                    <template v-if="editingSystemId !== sys.id">
                        <n-button v-if="hasPermission('system_type.edit')" circle quaternary size="small" type="info" @click="startEditSystem(sys)">
                            <template #icon><n-icon><CreateOutline/></n-icon></template>
                        </n-button>
                        <n-popconfirm v-if="hasPermission('system_type.delete')" @positive-click="handleDeleteSystem(sys.id)" positive-text="Sí, eliminar" negative-text="Cancelar">
                            <template #trigger>
                                <n-button circle quaternary size="small" type="error">
                                    <template #icon><n-icon><TrashOutline/></n-icon></template>
                                </n-button>
                            </template>
                            ¿Eliminar este tipo de sistema? 
                        </n-popconfirm>
                    </template>
                    <template v-else>
                        <n-button circle quaternary size="small" type="success" @click="saveEditSystem(sys)">
                            <template #icon><n-icon><CheckmarkCircleOutline/></n-icon></template>
                        </n-button>
                        <n-button circle quaternary size="small" @click="editingSystemId = null">
                            <template #icon><n-icon><CloseOutline/></n-icon></template>
                        </n-button>
                    </template>
                </div>
            </div>
            <n-empty v-if="!system_types || system_types.length === 0" description="No hay tipos registrados" />
        </div>
    </n-modal>
</template>