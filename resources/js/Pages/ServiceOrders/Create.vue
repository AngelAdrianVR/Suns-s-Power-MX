<script setup>
import { ref } from 'vue';
import { useForm, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { 
    NForm, NFormItem, NInput, NButton, NCard, NIcon, NGrid, NGridItem, 
    createDiscreteApi, NSelect, NDatePicker, NInputNumber 
} from 'naive-ui';
import { 
    SaveOutline, ArrowBackOutline, PersonOutline, ConstructOutline, 
    LocationOutline, CalendarOutline, CashOutline, DocumentTextOutline,
    BriefcaseOutline
} from '@vicons/ionicons5';

const props = defineProps({
    clients: Array,
    technicians: Array,
    sales_reps: Array,
});

const { notification } = createDiscreteApi(['notification']);
const formRef = ref(null);

// Formulario basado en el modelo ServiceOrder
const form = useForm({
    client_id: null,
    technician_id: null,
    sales_rep_id: null,
    status: 'Cotización', // Valor por defecto
    start_date: null,     // Timestamp para NDatePicker
    total_amount: 0,
    installation_address: '',
    notes: '',
});

// Opciones de Estado
const statusOptions = [
    { label: 'Cotización', value: 'Cotización' },
    { label: 'Aceptado', value: 'Aceptado' },
    { label: 'En Proceso', value: 'En Proceso' },
    { label: 'Completado', value: 'Completado' },
    { label: 'Facturado', value: 'Facturado' },
];

// Reglas de validación
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
    installation_address: { 
        required: true, 
        message: 'La dirección de instalación es obligatoria', 
        trigger: 'blur' 
    },
    total_amount: {
        required: true,
        type: 'number',
        min: 0,
        message: 'El monto total es requerido',
        trigger: 'blur'
    }
};

// Transformar datos para los Selects de Naive UI
const clientOptions = props.clients.map(c => ({ label: c.name, value: c.id }));
const techOptions = props.technicians.map(t => ({ label: t.name, value: t.id }));
const salesOptions = props.sales_reps.map(s => ({ label: s.name, value: s.id }));

const submit = () => {
    formRef.value?.validate((errors) => {
        if (!errors) {
            // Conversión de fecha si es necesario antes de enviar (aunque Inertia maneja timestamps bien usualmente)
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
            <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
                
                <n-form
                    ref="formRef"
                    :model="form"
                    :rules="rules"
                    label-placement="top"
                    require-mark-placement="right-hanging"
                    size="large"
                >
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        
                        <!-- Columna Izquierda: Datos Operativos (2/3 del ancho) -->
                        <div class="md:col-span-2 space-y-6">
                            
                            <!-- Tarjeta 1: Asignación Principal -->
                            <n-card :bordered="false" class="shadow-sm rounded-2xl">
                                <template #header>
                                    <span class="text-gray-600 font-semibold flex items-center gap-2">
                                        <n-icon :component="BriefcaseOutline" /> Detalles Generales
                                    </span>
                                </template>

                                <n-grid x-gap="12" :cols="2">
                                    <!-- Cliente -->
                                    <n-grid-item span="2">
                                        <n-form-item label="Cliente" path="client_id">
                                            <n-select 
                                                v-model:value="form.client_id" 
                                                :options="clientOptions" 
                                                filterable 
                                                placeholder="Buscar cliente..."
                                                clearable
                                            />
                                        </n-form-item>
                                    </n-grid-item>

                                    <!-- Vendedor -->
                                    <n-grid-item>
                                        <n-form-item label="Representante de Ventas" path="sales_rep_id">
                                            <n-select 
                                                v-model:value="form.sales_rep_id" 
                                                :options="salesOptions" 
                                                placeholder="Selecciona vendedor"
                                                filterable
                                            />
                                        </n-form-item>
                                    </n-grid-item>

                                    <!-- Estado Inicial -->
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

                            <!-- Tarjeta 2: Detalles Técnicos -->
                            <n-card :bordered="false" class="shadow-sm rounded-2xl">
                                <template #header>
                                    <span class="text-gray-600 font-semibold flex items-center gap-2">
                                        <n-icon :component="ConstructOutline" /> Logística e Instalación
                                    </span>
                                </template>

                                <n-grid x-gap="12" :cols="2">
                                    <!-- Técnico -->
                                    <n-grid-item>
                                        <n-form-item label="Técnico Responsable (Opcional)" path="technician_id">
                                            <n-select 
                                                v-model:value="form.technician_id" 
                                                :options="techOptions" 
                                                placeholder="Asignar técnico..."
                                                filterable
                                                clearable
                                            >
                                                <template #prefix>
                                                    <n-icon :component="PersonOutline" class="text-gray-400"/>
                                                </template>
                                            </n-select>
                                        </n-form-item>
                                    </n-grid-item>

                                    <!-- Fecha Inicio -->
                                    <n-grid-item>
                                        <n-form-item label="Fecha Programada de Inicio" path="start_date">
                                            <!-- CORRECCIÓN: Se agrega value-format -->
                                            <n-date-picker 
                                                v-model:formatted-value="form.start_date" 
                                                type="datetime" 
                                                value-format="yyyy-MM-dd HH:mm:ss"
                                                class="w-full"
                                                placeholder="Seleccionar fecha y hora"
                                                clearable
                                            />
                                        </n-form-item>
                                    </n-grid-item>

                                    <!-- Dirección -->
                                    <n-grid-item span="2">
                                        <n-form-item label="Dirección de Instalación" path="installation_address">
                                            <n-input 
                                                v-model:value="form.installation_address" 
                                                type="textarea"
                                                placeholder="Calle, Número, Colonia, Referencias..."
                                                :autosize="{ minRows: 2, maxRows: 4 }"
                                            >
                                                <template #prefix>
                                                    <n-icon :component="LocationOutline" class="text-gray-400"/>
                                                </template>
                                            </n-input>
                                        </n-form-item>
                                    </n-grid-item>
                                </n-grid>
                            </n-card>
                        </div>

                        <!-- Columna Derecha: Financiero y Acciones (1/3 del ancho) -->
                        <div class="space-y-6">
                            
                            <!-- Tarjeta Financiera -->
                            <n-card :bordered="false" class="shadow-sm rounded-2xl bg-emerald-50/50">
                                <template #header>
                                    <span class="text-emerald-800 font-semibold flex items-center gap-2">
                                        <n-icon :component="CashOutline"/> Presupuesto
                                    </span>
                                </template>
                                
                                <n-form-item 
                                    label="Monto Total del Servicio" 
                                    path="total_amount"
                                >
                                    <n-input-number 
                                        v-model:value="form.total_amount" 
                                        :show-button="false"
                                        placeholder="0.00"
                                        class="w-full text-right font-mono"
                                        :min="0"
                                        :precision="2"
                                    >
                                        <template #prefix>
                                            <span class="text-gray-500 mr-2">$</span>
                                        </template>
                                        <template #suffix>MXN</template>
                                    </n-input-number>
                                </n-form-item>
                            </n-card>

                            <!-- Tarjeta Notas -->
                            <n-card :bordered="false" class="shadow-sm rounded-2xl">
                                <template #header>
                                    <span class="text-gray-600 font-semibold flex items-center gap-2 text-sm">
                                        <n-icon :component="DocumentTextOutline"/> Notas Adicionales
                                    </span>
                                </template>
                                <n-input 
                                    v-model:value="form.notes" 
                                    type="textarea" 
                                    placeholder="Instrucciones especiales para el equipo..."
                                    :autosize="{ minRows: 3 }"
                                />
                            </n-card>

                            <!-- Acciones Sticky -->
                            <div class="flex flex-col gap-3 sticky top-6">
                                <n-button 
                                    type="primary" 
                                    size="large" 
                                    block 
                                    @click="submit" 
                                    :loading="form.processing"
                                    :disabled="form.processing"
                                    class="shadow-md hover:shadow-lg transition-shadow"
                                >
                                    <template #icon><n-icon><SaveOutline /></n-icon></template>
                                    Crear Orden
                                </n-button>
                                
                                <Link :href="route('service-orders.index')" class="w-full">
                                    <n-button block ghost type="error">
                                        Cancelar
                                    </n-button>
                                </Link>
                            </div>
                        </div>

                    </div>
                </n-form>

            </div>
        </div>
    </AppLayout>
</template>