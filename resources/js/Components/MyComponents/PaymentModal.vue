<script setup>
import { ref, computed, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import axios from 'axios';
import { 
    NModal, NCard, NForm, NFormItem, NSelect, NInputNumber, 
    NDatePicker, NInput, NButton, NSpin, NAlert, NGrid, NGridItem,
    NUpload, NUploadDragger, NIcon, createDiscreteApi, NText
} from 'naive-ui';
import { 
    CloudUploadOutline, ReceiptOutline, WalletOutline, CloseOutline 
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

// Formulario Inertia
const form = useForm({
    client_id: null,
    service_order_id: null,
    amount: 0,
    payment_date: Date.now(), 
    method: 'Transferencia',
    reference: '',
    notes: '',
    proof: null
});

// Opciones de método de pago
const paymentMethods = [
    { label: 'Transferencia', value: 'Transferencia' },
    { label: 'Efectivo', value: 'Efectivo' },
    { label: 'Tarjeta de Crédito/Débito', value: 'Tarjeta' },
    { label: 'Cheque', value: 'Cheque' },
    { label: 'Depósito', value: 'Depósito' },
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
    if (!form.service_order_id) {
        notification.warning({ title: 'Atención', content: 'Debes seleccionar una orden de servicio.', duration: 3000 });
        return;
    }
    if (form.amount <= 0) {
        notification.warning({ title: 'Atención', content: 'El monto debe ser mayor a cero.', duration: 3000 });
        return;
    }
    if (!form.proof) {
        notification.error({ title: 'Comprobante Requerido', content: 'Debes subir una imagen o PDF del comprobante.', duration: 3000 });
        return;
    }
    if (selectedOrder.value && form.amount > selectedOrder.value.pending_balance + 1) {
        notification.error({ title: 'Error de Monto', content: 'El monto no puede ser mayor al saldo pendiente.', duration: 3000 });
        return;
    }

    form.transform((data) => ({
        ...data,
        payment_date: new Date(data.payment_date).toISOString().split('T')[0]
    })).post(route('payments.store'), {
        preserveScroll: true,
        onSuccess: () => {
            notification.success({ title: 'Éxito', content: 'Abono registrado correctamente.', duration: 3000 });
            closeModal();
        },
        onError: () => {
            notification.error({ title: 'Error', content: 'Hubo un problema al procesar el pago.', duration: 3000 });
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
            style="width: 650px; border-radius: 0.75rem;" 
            :title="`Registrar Abono`"
            :bordered="false"
            size="small"
            role="dialog"
            aria-modal="true"
        >
            <template #header-extra>
                <NButton circle size="small" quaternary @click="closeModal">
                    <template #icon><NIcon><CloseOutline /></NIcon></template>
                </NButton>
            </template>

            <!-- Encabezado Compacto -->
            <div v-if="client" class="mb-3 p-2 bg-indigo-50 rounded-lg flex items-center gap-3 border border-indigo-100">
                <div class="bg-white p-1.5 rounded-md shadow-sm text-indigo-500 flex items-center justify-center">
                    <NIcon size="18"><WalletOutline /></NIcon>
                </div>
                <div>
                    <p class="text-[10px] text-indigo-400 font-bold uppercase tracking-widest leading-none">Cliente</p>
                    <p class="text-base font-bold text-indigo-900 leading-tight">{{ client.name }}</p>
                </div>
            </div>

            <div v-if="loadingOrders" class="py-8 flex justify-center">
                <NSpin size="medium" />
            </div>

            <div v-else-if="fetchError" class="py-2">
                <NAlert type="error" :title="fetchError" />
            </div>

            <div v-else-if="serviceOrders.length === 0" class="py-4">
                <NAlert type="success" title="¡Sin deudas pendientes!">
                    Este cliente no tiene órdenes de servicio pendientes de pago.
                </NAlert>
                <div class="mt-4 flex justify-end">
                    <NButton @click="closeModal" size="medium">Cerrar Ventana</NButton>
                </div>
            </div>

            <!-- Formulario con espaciado vertical reducido (y-gap="10") -->
            <NForm v-else :model="form" @submit.prevent="submit">
                <NGrid :cols="2" x-gap="12" y-gap="8">
                    <NGridItem :span="2">
                        <NFormItem label="Seleccionar Orden de Servicio" path="service_order_id" required>
                            <NSelect 
                                v-model:value="form.service_order_id" 
                                :options="orderOptions" 
                                placeholder="Elija la orden a abonar"
                                filterable
                                size="medium"
                            />
                        </NFormItem>
                    </NGridItem>

                    <!-- Resumen visual compacto -->
                    <NGridItem :span="2" v-if="selectedOrder">
                        <div class="bg-slate-50 px-3 py-2 rounded-lg border border-slate-100 mb-1 grid grid-cols-3 gap-2">
                            <div class="text-center border-r border-slate-200">
                                <p class="text-[9px] text-slate-400 uppercase font-bold">Total</p>
                                <p class="font-bold text-slate-700 text-sm">${{ selectedOrder.total_amount.toLocaleString('es-MX') }}</p>
                            </div>
                            <div class="text-center border-r border-slate-200">
                                <p class="text-[9px] text-emerald-500 uppercase font-bold">Abonado</p>
                                <p class="font-bold text-emerald-600 text-sm">${{ selectedOrder.paid_amount.toLocaleString('es-MX') }}</p>
                            </div>
                            <div class="text-center">
                                <p class="text-[9px] text-red-400 uppercase font-bold">Pendiente</p>
                                <p class="font-black text-red-600 text-sm">${{ selectedOrder.pending_balance.toLocaleString('es-MX') }}</p>
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
                                size="medium"
                                placeholder="0.00"
                            >
                                <template #prefix>$</template>
                            </NInputNumber>
                        </NFormItem>
                    </NGridItem>

                    <NGridItem>
                        <NFormItem label="Fecha de Pago" required>
                            <NDatePicker v-model:value="form.payment_date" type="date" class="w-full" size="medium" />
                        </NFormItem>
                    </NGridItem>

                    <NGridItem>
                        <NFormItem label="Método de Pago">
                            <NSelect v-model:value="form.method" :options="paymentMethods" size="medium" />
                        </NFormItem>
                    </NGridItem>

                    <NGridItem>
                        <NFormItem label="Referencia / Folio">
                            <NInput v-model:value="form.reference" placeholder="Ej. Folio 5521" size="medium" />
                        </NFormItem>
                    </NGridItem>

                    <!-- Zona de Carga Compacta -->
                    <NGridItem :span="2">
                        <NFormItem label="Comprobante (Obligatorio)" required>
                            <NUpload
                                :max="1"
                                @change="handleFileChange"
                                :default-upload="false"
                                accept=".pdf,.jpg,.jpeg,.png"
                                class="w-full"
                            >
                                <NUploadDragger class="compact-dragger bg-gray-50/50 hover:bg-indigo-50/30 transition-colors border border-dashed border-gray-300">
                                    <div class="flex items-center justify-center gap-3 py-1">
                                        <NIcon size="24" :depth="3" class="text-indigo-400">
                                            <CloudUploadOutline />
                                        </NIcon>
                                        <div class="text-left">
                                            <p class="text-xs font-bold text-gray-700">Clic o arrastra archivo aquí</p>
                                            <p class="text-[9px] text-gray-400 uppercase">PDF, JPG o PNG</p>
                                        </div>
                                    </div>
                                </NUploadDragger>
                            </NUpload>
                        </NFormItem>
                    </NGridItem>

                    <NGridItem :span="2">
                        <NFormItem label="Notas" :show-label="false">
                            <NInput 
                                v-model:value="form.notes" 
                                type="textarea" 
                                placeholder="Notas adicionales (opcional)..." 
                                :autosize="{ minRows: 1, maxRows: 3 }"
                                size="medium"
                            />
                        </NFormItem>
                    </NGridItem>
                </NGrid>

                <div class="flex justify-end gap-3 mt-4 pt-3 border-t border-gray-100">
                    <NButton @click="closeModal" :disabled="form.processing" size="medium" round>Cancelar</NButton>
                    <NButton 
                        type="primary" 
                        @click="submit" 
                        :loading="form.processing"
                        :disabled="!form.service_order_id || form.amount <= 0"
                        size="medium"
                        round
                        class="px-6 shadow-md shadow-indigo-100"
                    >
                        <template #icon><NIcon><ReceiptOutline /></NIcon></template>
                        Registrar
                    </NButton>
                </div>
            </NForm>
        </NCard>
    </NModal>
</template>

<style scoped>
:deep(.n-card-header) {
    padding-bottom: 10px;
}
:deep(.n-card__content) {
    padding-top: 0;
}
:deep(.n-form-item) {
    margin-bottom: 0; /* Controlamos el espacio con NGrid y-gap */
}
:deep(.n-form-item-label) {
    font-size: 12px; /* Etiquetas más pequeñas */
    padding-bottom: 2px !important;
}
.compact-dragger {
    padding: 8px !important; /* Forzar padding pequeño en el dragger */
}
</style>