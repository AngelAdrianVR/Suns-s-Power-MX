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
    order: {
        type: Object,
        required: true
    },
    // Se agregan valores por defecto para evitar errores si las listas vienen vacías
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
    }
});

const { notification } = createDiscreteApi(['notification']);
const formRef = ref(null);

// Función auxiliar para corregir el formato de fecha ISO que envía Laravel
// Convierte "2023-10-25T12:00:00.000000Z" a "2023-10-25 12:00:00"
const formatInitialDate = (dateString) => {
    if (!dateString) return null;
    if (dateString.includes('T')) {
        return dateString.replace('T', ' ').substring(0, 19);
    }
    return dateString;
};

// Formulario inicializado con los datos de la orden
const form = useForm({
    client_id: props.order.client_id,
    technician_id: props.order.technician_id,
    sales_rep_id: props.order.sales_rep_id,
    status: props.order.status,
    // CORRECCIÓN CRÍTICA: Formateamos la fecha inicial para evitar el RangeError
    start_date: formatInitialDate(props.order.start_date), 
    total_amount: Number(props.order.total_amount),
    installation_address: props.order.installation_address,
    notes: props.order.notes,
});

// Opciones de Estado
const statusOptions = [
    { label: 'Cotización', value: 'Cotización' },
    { label: 'Aceptado', value: 'Aceptado' },
    { label: 'En Proceso', value: 'En Proceso' },
    { label: 'Completado', value: 'Completado' },
    { label: 'Facturado', value: 'Facturado' },
    { label: 'Cancelado', value: 'Cancelado' }, 
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

// Transformar datos para los Selects de Naive UI con protección
const clientOptions = (props.clients || []).map(c => ({ label: c.name, value: c.id }));
const techOptions = (props.technicians || []).map(t => ({ label: t.name, value: t.id }));
const salesOptions = (props.sales_reps || []).map(s => ({ label: s.name, value: s.id }));

const submit = () => {
    formRef.value?.validate((errors) => {
        if (!errors) {
            form.put(route('service-orders.update', props.order.id), {
                onSuccess: () => {
                    notification.success({
                        title: 'Orden Actualizada',
                        content: 'Los cambios se han guardado correctamente.',
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
    <AppLayout :title="`Editar Orden #${order.id}`">
        <template #header>
            <div class="flex items-center gap-4">
                <Link :href="route('service-orders.show', order.id)">
                    <n-button circle secondary type="default">
                        <template #icon><n-icon><ArrowBackOutline /></n-icon></template>
                    </n-button>
                </Link>
                <div>
                    <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                        Editar Orden de Servicio <span class="text-indigo-600 font-mono">#{{ order.id }}</span>
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
                                        <n-form-item label="Estatus Actual" path="status">
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
                                    Guardar Cambios
                                </n-button>
                                
                                <Link :href="route('service-orders.show', order.id)" class="w-full">
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