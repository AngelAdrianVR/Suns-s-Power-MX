<script setup>
import { ref, computed, watch } from 'vue'; // Agregamos watch
import { useForm, Link, router } from '@inertiajs/vue3'; // Agregamos router
import AppLayout from '@/Layouts/AppLayout.vue';
import { 
    NForm, NFormItem, NInput, NButton, NCard, NIcon, NGrid, NGridItem, 
    createDiscreteApi, NSelect, NDatePicker, NInputNumber, NSpin, NTooltip
} from 'naive-ui';
import { 
    SaveOutline, ArrowBackOutline, PersonOutline, ConstructOutline, 
    LocationOutline, CashOutline, DocumentTextOutline, BriefcaseOutline,
    PersonAddOutline, RefreshOutline, FlashOutline, SpeedometerOutline,
    HardwareChipOutline // Nuevo icono para tipo de sistema
} from '@vicons/ionicons5';
import axios from 'axios';

const props = defineProps({
    clients: Array,
    technicians: Array,
    sales_reps: Array,
});

const { notification } = createDiscreteApi(['notification']);
const formRef = ref(null);
const loadingClientData = ref(false);
const loadingClientsList = ref(false); // Estado para el botón de refrescar

// Variables para manejar lógica de "Otro" tipo de sistema
const selectedSystemOption = ref(null);
const customSystemTypeText = ref('');

// Formulario
const form = useForm({
    client_id: null,
    technician_id: null,
    sales_rep_id: null,
    status: 'Cotización',
    start_date: null,
    
    // Nuevos campos
    service_number: '',
    rate_type: null,
    system_type: null, // Campo para guardar en BD
    meter_number: '',

    total_amount: 0,
    
    // Dirección de Instalación Atomizada
    installation_street: '',
    installation_exterior_number: '',
    installation_interior_number: '',
    installation_neighborhood: '',
    installation_municipality: '',
    installation_state: '',
    installation_zip_code: '',
    installation_country: 'México',

    notes: '',
});

const statusOptions = [
    { label: 'Cotización', value: 'Cotización' },
    { label: 'Aceptado', value: 'Aceptado' },
    { label: 'En Proceso', value: 'En Proceso' },
    { label: 'Completado', value: 'Completado' },
    { label: 'Facturado', value: 'Facturado' },
];

// Opciones de Tipo de Tarifa
const rateTypeOptions = [
    { label: '01', value: '01' },
    { label: '1A', value: '1A' },
    { label: '1B', value: '1B' },
    { label: '1C', value: '1C' },
    { label: '1D', value: '1D' },
    { label: '1F', value: '1F' },
    { label: 'DAC', value: 'DAC' },
    { label: 'PDBT', value: 'PDBT' },
    { label: 'GDBT', value: 'GDBT' },
    { label: 'GDMTO', value: 'GDMTO' },
    { label: 'N/A', value: 'N/A' },
];

// Opciones de Tipo de Sistema
const systemTypeOptions = [
    { label: 'Interconectado', value: 'Interconectado' },
    { label: 'Autónomo', value: 'Autónomo' },
    { label: 'Multimodo', value: 'Multimodo' },
    { label: 'Respaldo', value: 'Respaldo' },
    { label: 'Bombeo', value: 'Bombeo' },
    { label: 'Otro', value: 'Otro' },
];

// Lista de Estados de México
const mexicoStates = [
    { label: 'Aguascalientes', value: 'Aguascalientes' },
    { label: 'Baja California', value: 'Baja California' },
    { label: 'Baja California Sur', value: 'Baja California Sur' },
    { label: 'Campeche', value: 'Campeche' },
    { label: 'Chiapas', value: 'Chiapas' },
    { label: 'Chihuahua', value: 'Chihuahua' },
    { label: 'Ciudad de México', value: 'Ciudad de México' },
    { label: 'Coahuila', value: 'Coahuila' },
    { label: 'Colima', value: 'Colima' },
    { label: 'Durango', value: 'Durango' },
    { label: 'Estado de México', value: 'Estado de México' },
    { label: 'Guanajuato', value: 'Guanajuato' },
    { label: 'Guerrero', value: 'Guerrero' },
    { label: 'Hidalgo', value: 'Hidalgo' },
    { label: 'Jalisco', value: 'Jalisco' },
    { label: 'Michoacán', value: 'Michoacán' },
    { label: 'Morelos', value: 'Morelos' },
    { label: 'Nayarit', value: 'Nayarit' },
    { label: 'Nuevo León', value: 'Nuevo León' },
    { label: 'Oaxaca', value: 'Oaxaca' },
    { label: 'Puebla', value: 'Puebla' },
    { label: 'Querétaro', value: 'Querétaro' },
    { label: 'Quintana Roo', value: 'Quintana Roo' },
    { label: 'San Luis Potosí', value: 'San Luis Potosí' },
    { label: 'Sinaloa', value: 'Sinaloa' },
    { label: 'Sonora', value: 'Sonora' },
    { label: 'Tabasco', value: 'Tabasco' },
    { label: 'Tamaulipas', value: 'Tamaulipas' },
    { label: 'Tlaxcala', value: 'Tlaxcala' },
    { label: 'Veracruz', value: 'Veracruz' },
    { label: 'Yucatán', value: 'Yucatán' },
    { label: 'Zacatecas', value: 'Zacatecas' }
];

const rules = {
    client_id: { 
        required: true, 
        type: 'number',
        message: 'Selecciona un cliente', 
        trigger: ['blur', 'change'] 
    },
    sales_rep_id: { 
        required: true, 
        type: 'number',
        message: 'Selecciona un vendedor', 
        trigger: ['blur', 'change'] 
    },
    total_amount: {
        required: true,
        type: 'number',
        min: 0,
        message: 'El monto total es requerido',
        trigger: 'blur'
    },
    installation_street: { required: true, message: 'La calle es obligatoria', trigger: 'blur' },
    installation_neighborhood: { required: true, message: 'La colonia es obligatoria', trigger: 'blur' }
};

// --- CAMBIO IMPORTANTE: Options ahora son Computed ---
const clientOptions = computed(() => props.clients.map(c => ({ label: c.name, value: c.id })));
const techOptions = computed(() => props.technicians.map(t => ({ label: t.name, value: t.id })));
const salesOptions = computed(() => props.sales_reps.map(s => ({ label: s.name, value: s.id })));

// --- Watchers para lógica de "Otro" ---
watch(selectedSystemOption, (newVal) => {
    if (newVal !== 'Otro') {
        form.system_type = newVal;
    } else {
        // Si selecciona otro, seteamos el valor actual del input de texto
        form.system_type = customSystemTypeText.value;
    }
});

watch(customSystemTypeText, (newVal) => {
    if (selectedSystemOption.value === 'Otro') {
        form.system_type = newVal;
    }
});

// --- FUNCIONES ---

const refreshClientsList = () => {
    loadingClientsList.value = true;
    // Inertia reload permite recargar solo ciertos props sin refrescar toda la página
    router.reload({
        only: ['clients'],
        onFinish: () => {
            loadingClientsList.value = false;
            notification.success({
                title: 'Lista Actualizada',
                content: 'Se ha actualizado la lista de clientes.',
                duration: 2000
            });
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

        notification.success({
            title: 'Datos Cargados',
            content: 'Dirección de instalación importada del cliente.',
            duration: 2000
        });

    } catch (error) {
        console.error(error);
        notification.warning({
            title: 'Sin Dirección',
            content: 'No se pudo recuperar la dirección del cliente.',
            duration: 3000
        });
    } finally {
        loadingClientData.value = false;
    }
};

const submit = () => {
    formRef.value?.validate((errors) => {
        if (!errors) {
            form.post(route('service-orders.store'), {
                onSuccess: () => {
                    notification.success({
                        title: 'Orden Creada',
                        content: 'La orden de servicio se ha generado correctamente.',
                        duration: 3000
                    });
                },
                onError: () => {
                    notification.error({
                        title: 'Error de Validación',
                        content: 'Revisa los campos obligatorios.',
                        duration: 4000
                    });
                }
            });
        } else {
            notification.warning({
                title: 'Campos Incompletos',
                content: 'Por favor completa la información requerida.',
                duration: 3000
            });
        }
    });
};
</script>

<template>
    <AppLayout title="Nueva Orden de Servicio">
        <template #header>
            <div class="flex items-center gap-4">
                <Link :href="route('service-orders.index')">
                    <n-button circle secondary type="default">
                        <template #icon><n-icon><ArrowBackOutline /></n-icon></template>
                    </n-button>
                </Link>
                <div>
                    <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                        Generar Nueva Orden
                    </h2>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                
                <n-form
                    ref="formRef"
                    :model="form"
                    :rules="rules"
                    label-placement="top"
                    require-mark-placement="right-hanging"
                    size="medium"
                >
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        
                        <!-- Columna Izquierda: Datos Operativos (2/3) -->
                        <div class="lg:col-span-2 space-y-6">
                            
                            <!-- Tarjeta 1: Asignación -->
                            <n-card :bordered="false" class="shadow-sm rounded-2xl">
                                <template #header>
                                    <span class="text-gray-600 font-semibold flex items-center gap-2">
                                        <n-icon :component="BriefcaseOutline" /> Detalles Generales
                                    </span>
                                </template>

                                <n-grid x-gap="12" :cols="2">
                                    <n-grid-item span="2">
                                        <n-form-item label="Cliente" path="client_id">
                                            <!-- Grupo de Input con Botones -->
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
                                                
                                                <!-- Botón Nuevo Cliente -->
                                                <n-tooltip trigger="hover">
                                                    <template #trigger>
                                                        <!-- Usamos <a> normal para abrir en nueva pestaña -->
                                                        <a :href="route('clients.create')" target="_blank">
                                                            <n-button secondary type="primary">
                                                                <template #icon><n-icon><PersonAddOutline /></n-icon></template>
                                                            </n-button>
                                                        </a>
                                                    </template>
                                                    Nuevo Cliente (Nueva Pestaña)
                                                </n-tooltip>

                                                <!-- Botón Refrescar Lista -->
                                                <n-tooltip trigger="hover">
                                                    <template #trigger>
                                                        <n-button 
                                                            secondary 
                                                            @click="refreshClientsList" 
                                                            :loading="loadingClientsList"
                                                        >
                                                            <template #icon><n-icon><RefreshOutline /></n-icon></template>
                                                        </n-button>
                                                    </template>
                                                    Recargar Lista
                                                </n-tooltip>
                                            </div>
                                        </n-form-item>
                                    </n-grid-item>

                                    <n-grid-item>
                                        <n-form-item label="Vendedor" path="sales_rep_id">
                                            <n-select 
                                                v-model:value="form.sales_rep_id" 
                                                :options="salesOptions" 
                                                filterable
                                            />
                                        </n-form-item>
                                    </n-grid-item>

                                    <n-grid-item>
                                        <n-form-item label="Estado Inicial" path="status">
                                            <n-select 
                                                v-model:value="form.status" 
                                                :options="statusOptions" 
                                            />
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

                                <!-- Loader superpuesto mientras carga dirección -->
                                <div v-if="loadingClientData" class="absolute inset-0 bg-white/60 z-10 flex items-center justify-center rounded-2xl">
                                    <n-spin size="medium" description="Obteniendo dirección..." />
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

                                    <!-- NUEVOS CAMPOS AGREGADOS -->
                                    <n-grid-item>
                                        <n-form-item label="Número de Servicio" path="service_number">
                                            <n-input 
                                                v-model:value="form.service_number" 
                                                placeholder="Ej. 123456789"
                                            >
                                                <template #prefix><n-icon :component="FlashOutline"/></template>
                                            </n-input>
                                        </n-form-item>
                                    </n-grid-item>

                                    <n-grid-item>
                                        <n-form-item label="Tipo de Tarifa" path="rate_type">
                                            <n-select 
                                                v-model:value="form.rate_type" 
                                                :options="rateTypeOptions" 
                                                placeholder="Selecciona tarifa"
                                                filterable
                                            />
                                        </n-form-item>
                                    </n-grid-item>
                                    
                                    <!-- INICIO MODIFICACIÓN: TIPO DE SISTEMA -->
                                    <n-grid-item span="2">
                                        <n-form-item label="Tipo de Sistema" path="system_type">
                                            <div class="flex gap-2 w-full">
                                                <!-- Select Principal -->
                                                <n-select 
                                                    v-model:value="selectedSystemOption" 
                                                    :options="systemTypeOptions" 
                                                    placeholder="Selecciona tipo de sistema"
                                                    class="w-full"
                                                >
                                                    <template #prefix><n-icon :component="HardwareChipOutline"/></template>
                                                </n-select>
                                                
                                                <!-- Input condicional para 'Otro' -->
                                                <n-input 
                                                    v-if="selectedSystemOption === 'Otro'"
                                                    v-model:value="customSystemTypeText" 
                                                    placeholder="Especifique tipo..."
                                                    class="w-full"
                                                />
                                            </div>
                                        </n-form-item>
                                    </n-grid-item>
                                    <!-- FIN MODIFICACIÓN -->

                                    <n-grid-item span="2">
                                        <n-form-item label="Número de Medidor" path="meter_number">
                                            <n-input v-model:value="form.meter_number" placeholder="Ingrese el número de serie del medidor">
                                                <template #prefix><n-icon :component="SpeedometerOutline"/></template>
                                            </n-input>
                                        </n-form-item>
                                    </n-grid-item>
                                    <!-- FIN NUEVOS CAMPOS -->

                                </n-grid>
                                
                                <div class="mt-4 border-t pt-4">
                                    <label class=" text-gray-500 font-medium mb-3 text-sm flex items-center gap-1">
                                        <n-icon :component="LocationOutline"/> Dirección del Sitio
                                    </label>
                                    
                                    <n-grid x-gap="12" y-gap="2" cols="1 s:2 m:4" responsive="screen">
                                        <!-- Calle -->
                                        <n-grid-item span="1 m:2">
                                            <n-form-item label="Calle" path="installation_street">
                                                <n-input 
                                                    v-model:value="form.installation_street" 
                                                    placeholder="Av. Principal" 
                                                    :input-props="{ autocomplete: 'new-password' }"
                                                />
                                            </n-form-item>
                                        </n-grid-item>

                                        <n-grid-item>
                                            <n-form-item label="No. Exterior" path="installation_exterior_number">
                                                <n-input 
                                                    v-model:value="form.installation_exterior_number" 
                                                    placeholder="123" 
                                                    :input-props="{ autocomplete: 'off' }"
                                                />
                                            </n-form-item>
                                        </n-grid-item>

                                        <n-grid-item>
                                            <n-form-item label="No. Interior" path="installation_interior_number">
                                                <n-input 
                                                    v-model:value="form.installation_interior_number" 
                                                    placeholder="4B" 
                                                    :input-props="{ autocomplete: 'off' }"
                                                />
                                            </n-form-item>
                                        </n-grid-item>

                                        <!-- Fila 2 -->
                                        <n-grid-item span="1 m:2">
                                            <n-form-item label="Colonia" path="installation_neighborhood">
                                                <n-input 
                                                    v-model:value="form.installation_neighborhood" 
                                                    placeholder="Centro" 
                                                    :input-props="{ autocomplete: 'off' }"
                                                />
                                            </n-form-item>
                                        </n-grid-item>

                                        <n-grid-item>
                                            <n-form-item label="C.P." path="installation_zip_code">
                                                <n-input 
                                                    v-model:value="form.installation_zip_code" 
                                                    placeholder="00000" 
                                                    :input-props="{ autocomplete: 'off' }"
                                                />
                                            </n-form-item>
                                        </n-grid-item>

                                        <n-grid-item>
                                            <n-form-item label="Estado" path="installation_state">
                                                <n-select 
                                                    v-model:value="form.installation_state" 
                                                    filterable 
                                                    placeholder="Selecciona un estado" 
                                                    :options="mexicoStates"
                                                />
                                            </n-form-item>
                                        </n-grid-item>

                                        <n-grid-item span="1 m:2">
                                            <n-form-item label="Municipio" path="installation_municipality">
                                                <n-input 
                                                    v-model:value="form.installation_municipality" 
                                                    placeholder="Municipio" 
                                                    :input-props="{ autocomplete: 'off' }"
                                                />
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
                                        <n-icon :component="DocumentTextOutline"/> Notas
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
                                    Crear Orden
                                </n-button>
                                
                                <Link :href="route('service-orders.index')" class="w-full">
                                    <n-button block ghost>Cancelar</n-button>
                                </Link>
                            </div>
                        </div>

                    </div>
                </n-form>
            </div>
        </div>
    </AppLayout>
</template>