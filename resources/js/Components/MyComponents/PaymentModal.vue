<script setup>
import { ref, computed, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import axios from 'axios';
import { 
    NModal, NCard, NForm, NFormItem, NSelect, NInputNumber, 
    NDatePicker, NInput, NButton, NSpin, NAlert, NGrid, NGridItem,
    NUpload, NUploadDragger, NIcon, createDiscreteApi
} from 'naive-ui';
import { 
    CloudUploadOutline, CashOutline, ReceiptOutline, 
    AlertCircleOutline, WalletOutline, CloseOutline 
} from '@vicons/ionicons5';

const props = defineProps({
    show: {
        type: Boolean,
        default: false
    },
    client: {
        type: Object,
        default: null
    },
});

const emit = defineEmits(['update:show', 'close']);
const { notification } = createDiscreteApi(['notification']);

const loadingOrders = ref(false);
const serviceOrders = ref([]);
const fetchError = ref(null);

// Formulario Inertia con el nuevo campo para el comprobante
const form = useForm({
    client_id: null,
    service_order_id: null,
    amount: 0,
    payment_date: Date.now(), 
    method: 'Transferencia',
    reference: '',
    notes: '',
    proof: null // Archivo obligatorio
});

// Opciones de método de pago
const paymentMethods = [
    { label: 'Transferencia', value: 'Transferencia' },
    { label: 'Efectivo', value: 'Efectivo' },
    { label: 'Tarjeta de Crédito/Débito', value: 'Tarjeta' },
    { label: 'Cheque', value: 'Cheque' },
    { label: 'Otro', value: 'Otro' }
];

const orderOptions = computed(() => {
    return serviceOrders.value.map(order => ({
        label: `${order.identifier} - Restan: $${order.pending_balance.toLocaleString('es-MX', { minimumFractionDigits: 2 })}`,
        value: order.id
    }));
});

const selectedOrder = computed(() => {
    return serviceOrders.value.find(o => o.id === form.service_order_id);
});

// Cargar órdenes cuando se abre el modal
watch(() => props.show, async (newValue) => {
    if (newValue && props.client) {
        form.reset();
        form.client_id = props.client.id;
        form.payment_date = Date.now();
        serviceOrders.value = [];
        loadingOrders.value = true;
        fetchError.value = null;

        try {
            const response = await axios.get(route('api.clients.pending-orders', props.client.id));
            serviceOrders.value = response.data;
            
            if (serviceOrders.value.length === 1) {
                form.service_order_id = serviceOrders.value[0].id;
                form.amount = serviceOrders.value[0].pending_balance;
            }
        } catch (error) {
            fetchError.value = "No se pudieron cargar las órdenes de servicio con deuda.";
        } finally {
            loadingOrders.value = false;
        }
    }
});

// Auto-llenar monto al cambiar de orden
watch(() => form.service_order_id, (newId) => {
    const order = serviceOrders.value.find(o => o.id === newId);
    if (order) {
        form.amount = order.pending_balance;
    }
});

// Manejo del archivo
const handleFileChange = (options) => {
    if (options.fileList.length > 0) {
        form.proof = options.fileList[0].file;
    } else {
        form.proof = null;
    }
};

const submit = () => {
    // Validaciones manuales antes del envío
    if (!form.service_order_id) {
        notification.warning({ title: 'Atención', content: 'Debes seleccionar una orden de servicio.' });
        return;
    }
    if (form.amount <= 0) {
        notification.warning({ title: 'Atención', content: 'El monto debe ser mayor a cero.' });
        return;
    }
    if (!form.proof) {
        notification.error({ title: 'Comprobante Requerido', content: 'Debes subir una imagen o PDF del comprobante para registrar el abono.' });
        return;
    }
    if (selectedOrder.value && form.amount > selectedOrder.value.pending_balance + 1) {
        notification.error({ title: 'Error de Monto', content: 'El monto no puede ser mayor al saldo pendiente.' });
        return;
    }

    form.transform((data) => ({
        ...data,
        payment_date: new Date(data.payment_date).toISOString().split('T')[0]
    })).post(route('payments.store'), {
        preserveScroll: true,
        onSuccess: () => {
            notification.success({ title: 'Éxito', content: 'Abono y comprobante registrados correctamente.' });
            closeModal();
        },
        onError: () => {
            notification.error({ title: 'Error', content: 'Hubo un problema al procesar el pago. Verifica los campos.' });
        }
    });
};

const closeModal = () => {
    emit('update:show', false);
    emit('close');
};
</script>

<template>
    <NModal :show="show" @update:show="(val) => emit('update:show', val)">
        <NCard 
            style="width: 650px; border-radius: 1rem;" 
            :title="`Registrar Abono`"
            :bordered="false"
            size="huge"
            role="dialog"
            aria-modal="true"
        >
            <template #header-extra>
                <NButton circle size="small" quaternary @click="closeModal">
                    <template #icon><NIcon><CloseOutline /></NIcon></template>
                </NButton>
            </template>

            <!-- Encabezado con información del cliente -->
            <div v-if="client" class="mb-6 p-4 bg-indigo-50 rounded-2xl flex items-center gap-4 border border-indigo-100">
                <div class="bg-white p-3 rounded-xl shadow-sm">
                    <NIcon size="28" class="text-indigo-500"><WalletOutline /></NIcon>
                </div>
                <div>
                    <p class="text-[10px] text-indigo-400 font-bold uppercase tracking-widest">Cliente</p>
                    <p class="text-xl font-bold text-indigo-900 leading-none">{{ client.name }}</p>
                </div>
            </div>

            <div v-if="loadingOrders" class="py-12 flex justify-center">
                <NSpin size="large" description="Cargando estados de cuenta..." />
            </div>

            <div v-else-if="fetchError" class="py-4">
                <NAlert type="error" :title="fetchError" />
            </div>

            <div v-else-if="serviceOrders.length === 0" class="py-6">
                <NAlert type="success" title="¡Sin deudas pendientes!">
                    Este cliente no tiene órdenes de servicio pendientes de pago.
                </NAlert>
                <div class="mt-6 flex justify-end">
                    <NButton @click="closeModal" round>Cerrar Ventana</NButton>
                </div>
            </div>

            <NForm v-else :model="form" @submit.prevent="submit">
                <NGrid :cols="2" x-gap="12">
                    <NGridItem :span="2">
                        <NFormItem label="Seleccionar Orden de Servicio" required>
                            <NSelect 
                                v-model:value="form.service_order_id" 
                                :options="orderOptions" 
                                placeholder="Elija la orden a abonar"
                                filterable
                            />
                        </NFormItem>
                    </NGridItem>

                    <!-- Resumen visual de la deuda -->
                    <NGridItem :span="2" v-if="selectedOrder">
                        <div class="bg-slate-50 p-4 rounded-xl border border-slate-100 mb-4 grid grid-cols-3 gap-4">
                            <div class="text-center border-r border-slate-200">
                                <p class="text-[10px] text-slate-400 uppercase font-bold">Total</p>
                                <p class="font-bold text-slate-700">${{ selectedOrder.total_amount.toLocaleString('es-MX') }}</p>
                            </div>
                            <div class="text-center border-r border-slate-200">
                                <p class="text-[10px] text-emerald-500 uppercase font-bold">Abonado</p>
                                <p class="font-bold text-emerald-600">${{ selectedOrder.paid_amount.toLocaleString('es-MX') }}</p>
                            </div>
                            <div class="text-center">
                                <p class="text-[10px] text-red-400 uppercase font-bold">Pendiente</p>
                                <p class="font-black text-red-600">${{ selectedOrder.pending_balance.toLocaleString('es-MX') }}</p>
                            </div>
                        </div>
                    </NGridItem>

                    <NGridItem>
                        <NFormItem label="Monto a Abonar" required>
                            <NInputNumber 
                                v-model:value="form.amount" 
                                :min="0.01" 
                                :precision="2"
                                class="w-full"
                                size="large"
                                placeholder="0.00"
                            >
                                <template #prefix>$</template>
                            </NInputNumber>
                        </NFormItem>
                    </NGridItem>

                    <NGridItem>
                        <NFormItem label="Fecha de Pago" required>
                            <NDatePicker v-model:value="form.payment_date" type="date" class="w-full" size="large" />
                        </NFormItem>
                    </NGridItem>

                    <NGridItem>
                        <NFormItem label="Método de Pago">
                            <NSelect v-model:value="form.method" :options="paymentMethods" size="large" />
                        </NFormItem>
                    </NGridItem>

                    <NGridItem>
                        <NFormItem label="Referencia / Folio">
                            <NInput v-model:value="form.reference" placeholder="Ej. Folio 5521" size="large" />
                        </NFormItem>
                    </NGridItem>

                    <!-- Zona de Carga de Comprobante Obligatoria -->
                    <NGridItem :span="2">
                        <NFormItem label="Comprobante de Pago (Obligatorio)" required>
                            <NUpload
                                :max="1"
                                @change="handleFileChange"
                                :default-upload="false"
                                accept=".pdf,.jpg,.jpeg,.png"
                            >
                                <NUploadDragger class="border-2 border-dashed hover:border-indigo-400 transition-colors bg-gray-50/50">
                                    <div class="mb-3">
                                        <NIcon size="40" :depth="3" class="text-indigo-400">
                                            <CloudUploadOutline />
                                        </NIcon>
                                    </div>
                                    <p class="text-sm font-bold text-gray-700">Arrastre aquí el comprobante</p>
                                    <p class="text-[10px] text-gray-400 mt-1 uppercase">PDF, JPG o PNG (Máx. 10MB)</p>
                                </NUploadDragger>
                            </NUpload>
                        </NFormItem>
                    </NGridItem>

                    <NGridItem :span="2">
                        <NFormItem label="Notas Adicionales">
                            <NInput 
                                v-model:value="form.notes" 
                                type="textarea" 
                                placeholder="Notas u observaciones..." 
                                :autosize="{ minRows: 2 }"
                            />
                        </NFormItem>
                    </NGridItem>
                </NGrid>

                <div class="flex justify-end gap-3 mt-6 pt-6 border-t border-gray-100">
                    <NButton @click="closeModal" :disabled="form.processing" round>Cancelar</NButton>
                    <NButton 
                        type="primary" 
                        @click="submit" 
                        :loading="form.processing"
                        :disabled="!form.service_order_id || form.amount <= 0"
                        size="large"
                        round
                        class="px-8 shadow-lg shadow-indigo-100"
                    >
                        <template #icon><NIcon><ReceiptOutline /></NIcon></template>
                        Registrar Abono
                    </NButton>
                </div>
            </NForm>
        </NCard>
    </NModal>
</template>

<style scoped>
:deep(.n-card-header__main) {
    font-weight: 800;
    color: #1f2937;
}
</style>