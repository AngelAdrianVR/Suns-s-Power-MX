<script setup>
import { ref, computed, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import axios from 'axios';
import { 
    NModal, NCard, NForm, NFormItem, NSelect, NInputNumber, 
    NDatePicker, NInput, NButton, NSpin, NAlert, NDescriptions, NDescriptionsItem, NTag
} from 'naive-ui';

const props = defineProps({
    show: Boolean,
    client: Object, // Objeto cliente seleccionado
});

const emit = defineEmits(['update:show', 'close']);

const loadingOrders = ref(false);
const serviceOrders = ref([]);
const fetchError = ref(null);

// Formulario Inertia
const form = useForm({
    client_id: null,
    service_order_id: null,
    amount: 0,
    payment_date: Date.now(), // Timestamp para Naive UI DatePicker
    method: 'Transferencia',
    reference: '',
    notes: ''
});

// Opciones de método de pago
const paymentMethods = [
    { label: 'Transferencia', value: 'Transferencia' },
    { label: 'Efectivo', value: 'Efectivo' },
    { label: 'Tarjeta de Crédito/Débito', value: 'Tarjeta' },
    { label: 'Cheque', value: 'Cheque' },
    { label: 'Otro', value: 'Otro' }
];

// Opciones para el select de órdenes
const orderOptions = computed(() => {
    return serviceOrders.value.map(order => ({
        label: `${order.identifier} - Restan: $${order.pending_balance.toLocaleString('es-MX', { minimumFractionDigits: 2 })}`,
        value: order.id,
        disabled: false
    }));
});

// Obtener detalles de la orden seleccionada para mostrar info en UI
const selectedOrder = computed(() => {
    return serviceOrders.value.find(o => o.id === form.service_order_id);
});

// Cargar órdenes cuando se abre el modal y hay un cliente
watch(() => props.show, async (newValue) => {
    if (newValue && props.client) {
        form.reset();
        form.client_id = props.client.id;
        form.payment_date = Date.now();
        serviceOrders.value = [];
        loadingOrders.value = true;
        fetchError.value = null;

        try {
            // Asumiendo que definiste la ruta en web.php como: Route::get('/clients/{client}/pending-orders', ...)
            const response = await axios.get(route('api.clients.pending-orders', props.client.id));
            serviceOrders.value = response.data;
            
            // Auto-seleccionar si solo hay una orden
            if (serviceOrders.value.length === 1) {
                form.service_order_id = serviceOrders.value[0].id;
                form.amount = serviceOrders.value[0].pending_balance;
            }
        } catch (error) {
            console.error(error);
            fetchError.value = "No se pudieron cargar las órdenes de servicio.";
        } finally {
            loadingOrders.value = false;
        }
    }
});

// Auto-llenar monto restante al cambiar orden (opcional, UX helper)
watch(() => form.service_order_id, (newId) => {
    const order = serviceOrders.value.find(o => o.id === newId);
    if (order) {
        form.amount = order.pending_balance;
    }
});

const submit = () => {
    // Validar monto simple
    if (selectedOrder.value && form.amount > selectedOrder.value.pending_balance + 1) {
        alert('El monto no puede ser mayor al saldo pendiente.');
        return;
    }

    form.transform((data) => ({
        ...data,
        // Convertir timestamp de Naive UI a formato YYYY-MM-DD
        payment_date: new Date(data.payment_date).toISOString().split('T')[0]
    })).post(route('payments.store'), {
        preserveScroll: true,
        onSuccess: () => {
            closeModal();
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
            style="width: 600px; max-width: 90vw;" 
            :title="`Registrar Abono - ${client?.name || ''}`"
            :bordered="false"
            size="huge"
            role="dialog"
            aria-modal="true"
        >
            <template #header-extra>
                <NButton circle size="small" tertiary @click="closeModal">✕</NButton>
            </template>

            <div v-if="loadingOrders" class="py-8 flex justify-center">
                <NSpin size="large" description="Buscando deudas..." />
            </div>

            <div v-else-if="fetchError">
                <NAlert type="error">{{ fetchError }}</NAlert>
            </div>

            <div v-else-if="serviceOrders.length === 0" class="py-4">
                <NAlert type="success" title="¡Al corriente!">
                    Este cliente no tiene órdenes de servicio con saldo pendiente.
                </NAlert>
                <div class="mt-4 flex justify-end">
                    <NButton @click="closeModal">Cerrar</NButton>
                </div>
            </div>

            <NForm v-else ref="formRef" :model="form" @submit.prevent="submit" class="space-y-4">
                
                <!-- Selección de Orden -->
                <NFormItem label="Orden de Servicio a abonar" path="service_order_id" required>
                    <NSelect 
                        v-model:value="form.service_order_id" 
                        :options="orderOptions" 
                        placeholder="Seleccione la orden"
                        filterable
                    />
                </NFormItem>

                <!-- Info Flash de la Orden Seleccionada -->
                <div v-if="selectedOrder" class="bg-gray-50 p-3 rounded-lg border border-gray-100 mb-4">
                    <div class="flex justify-between items-center text-sm">
                        <span class="text-gray-500">Total Orden:</span>
                        <span class="font-bold text-gray-800">${{ selectedOrder.total_amount.toLocaleString('es-MX', { minimumFractionDigits: 2 }) }}</span>
                    </div>
                    <div class="flex justify-between items-center text-sm mt-1">
                        <span class="text-gray-500">Ya Pagado:</span>
                        <span class="text-emerald-600">${{ selectedOrder.paid_amount.toLocaleString('es-MX', { minimumFractionDigits: 2 }) }}</span>
                    </div>
                    <div class="flex justify-between items-center text-sm mt-1 border-t border-gray-200 pt-1">
                        <span class="text-gray-500">Saldo Pendiente:</span>
                        <span class="font-bold text-red-600 text-base">${{ selectedOrder.pending_balance.toLocaleString('es-MX', { minimumFractionDigits: 2 }) }}</span>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- Monto -->
                    <NFormItem label="Monto a Pagar" path="amount" :feedback="form.errors.amount" :validation-status="form.errors.amount ? 'error' : undefined">
                        <NInputNumber 
                            v-model:value="form.amount" 
                            :min="0.01" 
                            :max="selectedOrder?.pending_balance || 99999999"
                            :show-button="false"
                            class="w-full"
                        >
                            <template #prefix>$</template>
                        </NInputNumber>
                    </NFormItem>

                    <!-- Fecha -->
                    <NFormItem label="Fecha de Pago" path="payment_date">
                        <NDatePicker v-model:value="form.payment_date" type="date" class="w-full" />
                    </NFormItem>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- Método -->
                    <NFormItem label="Método de Pago" path="method">
                        <NSelect v-model:value="form.method" :options="paymentMethods" />
                    </NFormItem>

                    <!-- Referencia -->
                    <NFormItem label="Referencia / Folio" path="reference">
                        <NInput v-model:value="form.reference" placeholder="Ej. Transferencia #12345" />
                    </NFormItem>
                </div>

                <NFormItem label="Notas Adicionales" path="notes">
                    <NInput v-model:value="form.notes" type="textarea" placeholder="Observaciones..." />
                </NFormItem>

                <div class="flex justify-end gap-2 mt-4 pt-4 border-t border-gray-100">
                    <NButton @click="closeModal" :disabled="form.processing">Cancelar</NButton>
                    <NButton 
                        type="primary" 
                        attr-type="submit" 
                        :loading="form.processing"
                        :disabled="!form.service_order_id || form.amount <= 0"
                    >
                        Registrar Abono
                    </NButton>
                </div>
            </NForm>
        </NCard>
    </NModal>
</template>