<script setup>
import { ref, computed, watch, h } from 'vue';
import { usePermissions } from '@/Composables/usePermissions';
import { useSecureFile } from '@/Composables/useSecureFile';
import { router, Link } from '@inertiajs/vue3';
import axios from 'axios';
import {
    NSelect, NTag, NIcon, NButton, NSpin, NDataTable, NTimeline,
    NTimelineItem, NDivider, NEmpty, NTooltip, NGrid, NGridItem,
    NStatistic, NCard, NAlert, NBadge, NModal, createDiscreteApi,
    NSpace, NForm, NFormItem, NInputNumber
} from 'naive-ui';
import {
    ConstructOutline, WalletOutline, AlertCircleOutline,
    CheckmarkCircleOutline, TimeOutline, CloseCircleOutline,
    MailOutline, LogoWhatsapp, CashOutline, EyeOutline,
    SendOutline, AttachOutline, CalendarOutline,
    CreateOutline, SaveOutline
} from '@vicons/ionicons5';

const props = defineProps({
    client: Object,
    stats: Object,
});

const emit = defineEmits(['open-payment', 'refresh']);

const { hasPermission } = usePermissions();
const { openFileWithRetry } = useSecureFile();
const { notification } = createDiscreteApi(['notification']);

// --- Estado ---
const selectedOrderId = ref(null);
const loadingProjection = ref(false);
const projectionData = ref(null);
const projectionError = ref(null);
const sendingReminder = ref(false);
const showReminderModal = ref(false);
const reminderTargetInstallment = ref(null);
const planExpanded = ref(false);

// --- Opciones del selector de órdenes ---
const orderOptions = computed(() => {
    if (!props.client.service_orders || props.client.service_orders.length === 0) return [];
    return props.client.service_orders.map(order => ({
        label: `OS #${order.id} - ${order.status} - ${formatCurrency(order.total_amount)}`,
        value: order.id,
    }));
});

// --- Orden seleccionada ---
const selectedOrder = computed(() => {
    if (!selectedOrderId.value || !props.client.service_orders) return null;
    return props.client.service_orders.find(o => o.id === selectedOrderId.value) || null;
});

// --- Pagos filtrados por la orden seleccionada ---
const orderPayments = computed(() => {
    if (!selectedOrderId.value || !props.client.payments) return [];
    return props.client.payments.filter(p => p.service_order_id === selectedOrderId.value);
});

// --- Cargar proyección al seleccionar orden ---
watch(selectedOrderId, async (newId) => {
    if (!newId) {
        projectionData.value = null;
        return;
    }
    loadingProjection.value = true;
    projectionError.value = null;
    projectionData.value = null;

    try {
        const response = await axios.get(route('api.service-orders.payment-projection', newId));
        projectionData.value = response.data;
    } catch (error) {
        projectionError.value = 'No se pudo cargar la proyección de pagos.';
    } finally {
        loadingProjection.value = false;
    }
});

// --- Auto-seleccionar primera orden ---
watch(() => props.client.service_orders, (orders) => {
    if (orders && orders.length > 0 && !selectedOrderId.value) {
        selectedOrderId.value = orders[0].id;
    }
}, { immediate: true });

// --- Utilidades ---
const formatCurrency = (amount) => new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(amount || 0);
const formatDate = (dateString) => {
    if (!dateString) return '-';
    // Limpiar la fecha: tomar solo la parte YYYY-MM-DD, ignorar horas
    const clean = String(dateString).trim().split(' ')[0].split('T')[0];
    const d = new Date(clean + 'T12:00:00');
    if (isNaN(d.getTime())) return String(dateString);
    return d.toLocaleDateString('es-MX', { year: 'numeric', month: 'short', day: 'numeric' });
};

const paymentPlanLabel = (method) => {
    const map = {
        'Contado': 'Contado', '3 MSI': '3 Meses', '6 MSI': '6 Meses',
        '9 MSI': '9 Meses', '12 MSI': '12 Meses', 'Personalizado': 'Personalizado'
    };
    return map[method] || method || '-';
};

const statusColorMap = {
    'Cotización': 'default', 'Aceptado': 'info', 'En Proceso': 'warning',
    'Completado': 'success', 'Facturado': 'success', 'Cancelado': 'error'
};

// --- Color de estatus de pago ---
const paymentStatusStyle = (status) => {
    const map = {
        on_time: { color: '#10b981', bg: '#d1fae5', label: 'A tiempo' },
        late: { color: '#f59e0b', bg: '#fef3c7', label: 'Extemporáneo' },
        defaulted: { color: '#ef4444', bg: '#fee2e2', label: 'Incumplido' },
        pending: { color: '#6b7280', bg: '#f3f4f6', label: 'Pendiente' },
        upcoming: { color: '#3b82f6', bg: '#dbeafe', label: 'Próximo' },
    };
    return map[status] || map.pending;
};

const statusIcon = (status) => {
    const map = {
        on_time: CheckmarkCircleOutline,
        late: TimeOutline,
        defaulted: CloseCircleOutline,
        pending: TimeOutline,
        upcoming: CalendarOutline,
    };
    return map[status] || TimeOutline;
};

// --- Recordatorio ---
const canSendReminder = computed(() => {
    if (!projectionData.value?.reminder_info) return { email: false, whatsapp: false };
    const info = projectionData.value.reminder_info;
    return {
        email: info.has_email,
        whatsapp: info.has_phone,
    };
});

// Solo la PRÓXIMA cuota pendiente/atrasada (la primera que no esté on_time)
const nextPendingInstallment = computed(() => {
    if (!projectionData.value?.projection?.installments) return null;
    return projectionData.value.projection.installments.find(
        inst => inst.status !== 'on_time'
    ) || null;
});

const hasLateInstallments = computed(() => {
    if (!projectionData.value?.projection?.installments) return false;
    return projectionData.value.projection.installments.some(
        inst => inst.status === 'late' || inst.status === 'defaulted'
    );
});

// --- SOLO PERMITIR MODIFICAR PLAN SI NO HAY PAGOS REGISTRADOS ---
const canModifyPaymentPlan = computed(() => {
    if (!selectedOrder.value) return false;
    return !selectedOrder.value.total_paid || selectedOrder.value.total_paid <= 0;
});

// Solo se permite recordatorio para la primera cuota pendiente/atrasada
const canRemindNext = computed(() => {
    if (!nextPendingInstallment.value) return false;
    return ['pending', 'late', 'defaulted'].includes(nextPendingInstallment.value.status);
});

const openReminderModal = (installment = null) => {
    // Solo permitir recordatorio para la primera cuota pendiente
    const target = installment || nextPendingInstallment.value;
    if (!target) return;
    reminderTargetInstallment.value = target;
    showReminderModal.value = true;
};

// Abrir modal de pago con datos preseleccionados
const openPaymentForInstallment = (inst) => {
    // Pago de mensualidad específica: monto bloqueado, con número de cuota
    emit('open-payment', selectedOrderId.value, inst.amount, true, inst.installment, `Pagar ${inst.label}`);
};

const openGeneralPayment = () => {
    const isPersonalizado = selectedOrder.value?.payment_method === 'Personalizado';
    const title = isPersonalizado ? 'Registrar Abono' : 'Liquidar Servicio';
    // Personalizado: monto desbloqueado para abonos flexibles
    emit('open-payment', selectedOrderId.value, orderRemaining.value, !isPersonalizado, null, title);
};

// --- ACTUALIZAR PLAN DE PAGO ---
const showPaymentMethodModal = ref(false);
const paymentMethodForm = ref({
    payment_method: null,
    down_payment: 0,
});
const savingPaymentMethod = ref(false);
const paymentMethodOptions = [
    { label: 'Contado', value: 'Contado' },
    { label: '3 MSI', value: '3 MSI' },
    { label: '6 MSI', value: '6 MSI' },
    { label: '9 MSI', value: '9 MSI' },
    { label: '12 MSI', value: '12 MSI' },
    { label: 'Personalizado', value: 'Personalizado' },
];

const openPaymentMethodModal = () => {
    paymentMethodForm.value = {
        payment_method: selectedOrder.value?.payment_method || null,
        down_payment: 0,
    };
    showPaymentMethodModal.value = true;
};

const savePaymentMethod = async () => {
    if (!paymentMethodForm.value.payment_method) {
        notification.warning({ title: 'Atención', content: 'Selecciona un plan de pago.', duration: 3000 });
        return;
    }
    savingPaymentMethod.value = true;
    try {
        await axios.patch(route('api.service-orders.update-payment-method', selectedOrderId.value), paymentMethodForm.value);
        showPaymentMethodModal.value = false;
        notification.success({ title: 'Actualizado', content: 'Plan de pago guardado correctamente.', duration: 3000 });
        // Notificar al padre para refrescar datos de la orden (payment_method, etc.)
        emit('refresh');
        // Refrescar proyección
        const response = await axios.get(route('api.service-orders.payment-projection', selectedOrderId.value));
        projectionData.value = response.data;
    } catch (error) {
        const msg = error.response?.data?.error || 'No se pudo actualizar el plan de pago.';
        notification.error({ title: 'Error', content: msg, duration: 4000 });
    } finally {
        savingPaymentMethod.value = false;
    }
};

const sendReminder = async (channel) => {
    sendingReminder.value = true;
    try {
        const payload = {
            channel,
            installment: reminderTargetInstallment.value?.installment ?? null,
        };
        const response = await axios.post(
            route('api.service-orders.send-reminder', selectedOrderId.value),
            payload
        );

        // Si es WhatsApp, abrir la URL
        if (channel === 'whatsapp' && response.data.results?.whatsapp?.url) {
            window.open(response.data.results.whatsapp.url, '_blank');
        }

        if (response.data.success) {
            notification.success({ title: 'Enviado', content: response.data.message, duration: 3000 });
        } else {
            notification.warning({ title: 'Atención', content: response.data.message, duration: 4000 });
        }
        showReminderModal.value = false;
    } catch (error) {
        notification.error({ title: 'Error', content: 'No se pudo enviar el recordatorio.', duration: 3000 });
    } finally {
        sendingReminder.value = false;
    }
};

// --- Columnas de pagos realizados ---
const paymentColumns = [
    { title: 'Fecha', key: 'payment_date', width: 125, render: (row) => formatDate(row.payment_date) },
    {
        title: 'Método', key: 'method', width: 120,
        render: (row) => h('span', { class: 'text-xs' }, row.method || '-')
    },
    {
        title: 'Notas', key: 'notes', width: 120,
        render: (row) => h('span', { class: 'text-xs' }, row.notes || '-')
    },
    {
        title: 'Monto', key: 'amount', align: 'right', width: 130,
        render: (row) => h('span', { class: 'font-bold text-emerald-600 text-xs' }, formatCurrency(row.amount))
    },
    {
        title: 'Comp.',
        key: 'receipt',
        width: 80,
        align: 'center',
        render(row) {
            const url = row.receipt_url || (row.media && row.media[0]?.original_url);
            if (url) {
                return h(NTooltip, null, {
                    trigger: () => h(NButton, {
                        circle: true, size: 'small', quaternary: true, type: 'info',
                        onClick: () => openFileWithRetry(url)
                    }, { icon: () => h(NIcon, null, { default: () => h(AttachOutline) }) }),
                    default: () => 'Ver Comprobante'
                });
            }
            return null;
        }
    },
];

// --- Calcular totales ---
const orderTotalPaid = computed(() => {
    return orderPayments.value.reduce((sum, p) => sum + parseFloat(p.amount || 0), 0);
});

const orderRemaining = computed(() => {
    if (!selectedOrder.value) return 0;
    return Math.max(0, parseFloat(selectedOrder.value.total_amount || 0) - orderTotalPaid.value);
});

// --- INLINE EDIT: PRECIO DE MANTENIMIENTO POR MÓDULO ---
const isEditingPrice = ref(false);
const editingPrice = ref(null);
const isSavingPrice = ref(false);

const startEditPrice = () => {
    editingPrice.value = selectedOrder.value?.price_per_module || null;
    isEditingPrice.value = true;
};

const cancelEditPrice = () => {
    isEditingPrice.value = false;
    editingPrice.value = selectedOrder.value?.price_per_module || null;
};

const savePrice = async () => {
    isSavingPrice.value = true;
    try {
        await axios.patch(
            route('api.service-orders.update-maintenance-price', selectedOrderId.value),
            { price_per_module: editingPrice.value }
        );
        isEditingPrice.value = false;
        notification.success({ title: 'Actualizado', content: 'Precio de mantenimiento guardado.', duration: 3000 });
        emit('refresh');
    } catch (error) {
        notification.error({ title: 'Error', content: 'No se pudo guardar el precio.', duration: 3000 });
    } finally {
        isSavingPrice.value = false;
    }
};
</script>

<template>
    <div>
        <!-- ENCABEZADO: SELECTOR DE ORDEN + BOTÓN NUEVA ORDEN -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-5 gap-3">
            <div class="flex-1 max-w-lg w-full">
                <h3 class="text-base sm:text-lg font-bold text-gray-800 mb-2">Detalle de Servicio</h3>
                <n-select
                    v-model:value="selectedOrderId"
                    :options="orderOptions"
                    placeholder="Selecciona una orden de servicio..."
                    filterable
                    :loading="loadingProjection"
                    :disabled="!orderOptions.length"
                    class="w-full"
                />
            </div>
            <n-button
                v-if="hasPermission('service_orders.create')"
                type="primary" round size="small"
                @click="router.visit(route('service-orders.create', { client_id: client.id }))"
                class="w-full sm:w-auto mt-2 sm:mt-0"
            >
                <template #icon><n-icon><ConstructOutline /></n-icon></template>
                Nueva Orden
            </n-button>
        </div>

        <!-- SIN ÓRDENES -->
        <n-empty v-if="!orderOptions.length" description="Este cliente aún no tiene órdenes de servicio." class="py-8" />

        <template v-else>
            <!-- CARGA -->
            <div v-if="loadingProjection" class="py-12 flex justify-center">
                <n-spin size="medium" />
            </div>

            <template v-else-if="selectedOrder">
                <!-- TARJETA RESUMEN DE LA ORDEN -->
                <n-card size="small" :bordered="false" class="mb-4 bg-gray-50/50 rounded-xl">
                    <n-grid :cols="4" x-gap="12" y-gap="8" responsive="screen" item-responsive>
                        <n-grid-item span="4 m:2 l:1">
                            <n-statistic label="Estado" tabular-nums>
                                <n-tag :type="statusColorMap[selectedOrder.status] || 'default'" size="small" round :bordered="false">
                                    {{ selectedOrder.status }}
                                </n-tag>
                            </n-statistic>
                        </n-grid-item>
                        <n-grid-item span="4 m:2 l:1">
                            <n-statistic label="Plan de Pago" tabular-nums>
                                <template v-if="selectedOrder.payment_method">
                                    <div class="flex items-center gap-1 flex-wrap">
                                        <n-tag type="info" size="small" round :bordered="false">
                                            {{ paymentPlanLabel(selectedOrder.payment_method) }}
                                        </n-tag>
                                        <n-button
                                            v-if="canModifyPaymentPlan"
                                            size="tiny" text type="warning"
                                            @click="openPaymentMethodModal"
                                        >
                                            Editar
                                        </n-button>
                                    </div>
                                </template>
                                <n-button
                                    v-else-if="canModifyPaymentPlan"
                                    size="tiny" text type="warning"
                                    @click="openPaymentMethodModal"
                                >
                                    Agregar plan de pago
                                </n-button>
                                <span v-else class="text-xs text-gray-400 italic">Sin plan</span>
                            </n-statistic>
                        </n-grid-item>
                        <n-grid-item span="4 m:2 l:1">
                            <n-statistic label="Total" :value="formatCurrency(selectedOrder.total_amount)" />
                        </n-grid-item>
                        <n-grid-item span="4 m:2 l:1">
                            <n-statistic label="Saldo Pendiente" :value="formatCurrency(orderRemaining)"
                                :style="orderRemaining > 0 ? 'color: #ef4444' : 'color: #10b981'" />
                        </n-grid-item>
                    </n-grid>

                    <!-- Datos adicionales -->
                    <n-grid v-if="selectedOrder.price_per_module || selectedOrder.system_type || selectedOrder.service_number" :cols="3" x-gap="12" class="mt-3 pt-3 border-t border-gray-200">
                        <n-grid-item v-if="selectedOrder.service_number">
                            <div class="text-[10px] text-gray-400 uppercase">Nº Servicio</div>
                            <div class="text-sm font-medium">{{ selectedOrder.service_number }}</div>
                        </n-grid-item>
                        <n-grid-item v-if="selectedOrder.system_type">
                            <div class="text-[10px] text-gray-400 uppercase">Tipo de Sistema</div>
                            <div class="text-sm font-medium">{{ selectedOrder.system_type }}</div>
                        </n-grid-item>
                        <n-grid-item>
                            <div class="text-[10px] text-gray-400 uppercase">Precio Mantenimiento / Módulo</div>
                            <div v-if="!isEditingPrice" class="flex items-center gap-1">
                                <span class="text-sm font-medium">{{ selectedOrder.price_per_module ? formatCurrency(selectedOrder.price_per_module) : '—' }}</span>
                                <n-button size="tiny" text type="primary" @click="startEditPrice">
                                    <template #icon><n-icon><CreateOutline /></n-icon></template>
                                </n-button>
                            </div>
                            <div v-else class="flex items-center gap-1">
                                <n-input-number
                                    v-model:value="editingPrice"
                                    :min="0"
                                    :precision="2"
                                    size="tiny"
                                    placeholder="0.00"
                                    class="w-24"
                                >
                                    <template #prefix>$</template>
                                </n-input-number>
                                <n-button size="tiny" type="primary" @click="savePrice" :loading="isSavingPrice">
                                    <template #icon><n-icon><SaveOutline /></n-icon></template>
                                </n-button>
                                <n-button size="tiny" @click="cancelEditPrice">X</n-button>
                            </div>
                        </n-grid-item>
                    </n-grid>

                    <div class="mt-3 flex gap-2 flex-wrap">
                        <n-button size="tiny" secondary round @click="router.visit(route('service-orders.show', selectedOrder.id))">
                            <template #icon><n-icon><EyeOutline /></n-icon></template>
                            Ver Orden Completa
                        </n-button>
                        <n-button v-if="hasPermission('collection.create') && orderRemaining > 1"
                            size="tiny" type="success" secondary round
                            @click="openGeneralPayment()">
                            <template #icon><n-icon><CashOutline /></n-icon></template>
                            {{ selectedOrder.payment_method === 'Personalizado' ? 'Registrar Abono' : 'Liquidar Servicio' }}
                        </n-button>
                    </div>
                </n-card>

                <!-- PLAN DE PAGOS (PROYECCIÓN) - COLAPSABLE -->
                <div class="mb-4">
                    <div class="flex items-center justify-between mb-3">
                        <h4 class="text-sm font-bold text-gray-700 flex items-center gap-2 cursor-pointer select-none"
                            @click="planExpanded = !planExpanded">
                            <n-icon><CalendarOutline /></n-icon>
                            Plan de Pagos
                            <n-badge v-if="hasLateInstallments" dot type="error" />
                            <n-icon size="16" class="text-gray-400 transition-transform" :class="planExpanded ? 'rotate-180' : ''">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
                            </n-icon>
                        </h4>
                        <n-button
                            v-if="canRemindNext && (canSendReminder.email || canSendReminder.whatsapp)"
                            size="tiny" type="warning" secondary round
                            @click="openReminderModal()"
                        >
                            <template #icon><n-icon><SendOutline /></n-icon></template>
                            Recordar pago
                        </n-button>
                    </div>

                    <!-- Proyección Personalizado: mostrar solo pagos reales -->
                    <n-alert v-if="selectedOrder.payment_method === 'Personalizado'" type="info" :bordered="false" class="mb-3">
                        Plan de pago <strong>personalizado</strong>. No hay fechas fijas proyectadas. Los pagos se registran según lo acordado.
                    </n-alert>

                    <!-- Timeline de cuotas para MSI / Contado -->
                    <div v-if="projectionData?.projection?.installments?.length && planExpanded" class="bg-white rounded-xl border border-gray-100 p-4">
                        <n-timeline>
                            <n-timeline-item
                                v-for="inst in projectionData.projection.installments"
                                :key="inst.installment"
                                :color="inst === nextPendingInstallment ? paymentStatusStyle(inst.status).color : '#d1d5db'"
                                :type="inst === nextPendingInstallment ? (inst.status === 'defaulted' ? 'error' : inst.status === 'late' ? 'warning' : inst.status === 'on_time' ? 'success' : 'info') : 'default'"
                            >
                                <template #header>
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <span class="font-bold text-gray-800">{{ inst.label }}</span>
                                        <!-- Solo mostrar estatus en la PRÓXIMA cuota pendiente -->
                                        <n-tag
                                            v-if="inst === nextPendingInstallment"
                                            :type="inst.status === 'defaulted' ? 'error' : inst.status === 'late' ? 'warning' : inst.status === 'on_time' ? 'success' : 'info'"
                                            size="tiny" round :bordered="false"
                                        >
                                            {{ inst.status_label }}
                                        </n-tag>
                                        <n-tag v-else-if="inst.status === 'on_time'" type="success" size="tiny" round :bordered="false">
                                            Pagado
                                        </n-tag>
                                    </div>
                                </template>
                                <div class="space-y-1 text-sm">
                                    <div class="flex justify-between">
                                        <span class="text-gray-500">Fecha proyectada:</span>
                                        <span class="font-medium">{{ formatDate(inst.projected_date) }}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-500">Monto:</span>
                                        <span class="font-bold">{{ formatCurrency(inst.amount) }}</span>
                                    </div>

                                    <!-- Pago real -->
                                    <template v-if="inst.payment">
                                        <div class="flex justify-between text-emerald-600">
                                            <span>Pagado:</span>
                                            <span>{{ formatCurrency(inst.payment.amount) }} — {{ formatDate(inst.payment.date) }}</span>
                                        </div>
                                        <div class="text-xs" :class="inst.status === 'on_time' ? 'text-emerald-500' : inst.status === 'late' ? 'text-amber-500' : 'text-red-500'"
                                            v-if="inst === nextPendingInstallment || inst.status === 'on_time'">
                                            {{ inst.payment.days_diff }} día(s) {{ inst.payment.days_diff >= 0 ? 'después' : 'antes' }} de la fecha proyectada
                                        </div>
                                    </template>
                                    <template v-else>
                                        <!-- Solo mostrar detalles de atraso en la próxima cuota -->
                                        <div v-if="inst === nextPendingInstallment" class="text-xs" :class="inst.status === 'defaulted' ? 'text-red-500 font-bold' : inst.status === 'late' ? 'text-amber-500' : 'text-gray-400'">
                                            Sin pago registrado
                                            <span v-if="inst.days_since_projected > 0">— {{ inst.days_since_projected }} día(s) de retraso</span>
                                        </div>
                                        <div v-else class="text-xs text-gray-400">
                                            Pendiente
                                        </div>
                                        <!-- Botón para pagar esta mensualidad (solo la próxima) -->
                                        <n-button
                                            v-if="inst === nextPendingInstallment && inst.status !== 'on_time' && hasPermission('collection.create')"
                                            size="tiny" quaternary type="success"
                                            @click="openPaymentForInstallment(inst)"
                                            class="mt-1"
                                        >
                                            <template #icon><n-icon><CashOutline /></n-icon></template>
                                            Pagar esta mensualidad
                                        </n-button>
                                        <!-- Botón de recordatorio (solo la próxima si está atrasada) -->
                                        <n-button
                                            v-if="inst === nextPendingInstallment && (inst.status === 'late' || inst.status === 'defaulted')"
                                            size="tiny" quaternary type="warning"
                                            @click="openReminderModal(inst)"
                                            class="mt-1"
                                        >
                                            <template #icon><n-icon><SendOutline /></n-icon></template>
                                            Recordar este pago
                                        </n-button>
                                    </template>
                                </div>
                            </n-timeline-item>
                        </n-timeline>
                    </div>

                    <!-- Resumen colapsado -->
                    <div v-else-if="projectionData?.projection?.installments?.length && !planExpanded" class="bg-white rounded-xl border-2 border-green-300 p-3 text-xs text-gray-500 cursor-pointer" @click="planExpanded = true">
                        <span class="font-medium text-gray-700">{{ projectionData.projection.installments.length }} cuota(s)</span> —
                        Clic para desplegar el plan completo
                    </div>

                    <!-- Pagos no emparejados -->
                    <n-alert v-if="projectionData?.projection?.unmatched_payments?.length" type="warning" :bordered="false" class="mt-3">
                        <template #header>Pagos adicionales no programados</template>
                        <div v-for="up in projectionData.projection.unmatched_payments" :key="up.id" class="text-xs mt-1">
                            {{ formatCurrency(up.amount) }} — {{ formatDate(up.date) }} ({{ up.method }})
                        </div>
                    </n-alert>
                </div>

                <n-divider />

                <!-- PAGOS REALIZADOS (TABLA) -->
                <div>
                    <h4 class="text-sm font-bold text-gray-700 flex items-center gap-2 mb-3">
                        <n-icon><WalletOutline /></n-icon>
                        Pagos Realizados
                    </h4>

                    <div class="flex justify-between items-center mb-2 bg-gray-50 p-2 rounded-lg border border-gray-100">
                        <span class="text-xs text-gray-500">Total pagado:</span>
                        <span class="font-bold text-emerald-600">{{ formatCurrency(orderTotalPaid) }}</span>
                    </div>

                    <div class="-mx-2 sm:mx-0 overflow-x-auto">
                        <div class="min-w-[400px] sm:min-w-full">
                            <n-data-table
                                v-if="orderPayments.length"
                                :columns="paymentColumns"
                                :data="orderPayments"
                                :bordered="false"
                                size="small"
                                :pagination="{ pageSize: 10 }"
                            />
                            <n-empty v-else description="Sin pagos registrados para esta orden" class="py-4" />
                        </div>
                    </div>
                </div>
            </template>
        </template>

        <!-- MODAL DE RECORDATORIO (solo próxima cuota pendiente) -->
        <n-modal v-model:show="showReminderModal" :mask-closable="true">
            <n-card
                style="width: 450px; border-radius: 0.75rem;"
                title="Enviar Recordatorio de Pago"
                :bordered="false"
                size="small"
            >
                <n-alert type="warning" :bordered="false" class="mb-4">
                    <template v-if="reminderTargetInstallment">
                        Recordatorio para: <strong>{{ reminderTargetInstallment.label }}</strong><br/>
                        Fecha esperada: {{ formatDate(reminderTargetInstallment.projected_date) }}<br/>
                        Monto: {{ formatCurrency(reminderTargetInstallment.amount) }}
                    </template>
                </n-alert>

                <p class="text-sm text-gray-600 mb-4">
                    Selecciona el canal de comunicación:
                </p>

                <n-space vertical :size="12">
                    <n-button
                        block
                        :type="canSendReminder.email ? 'info' : 'default'"
                        :disabled="!canSendReminder.email || sendingReminder"
                        :loading="sendingReminder"
                        @click="sendReminder('email')"
                    >
                        <template #icon><n-icon><MailOutline /></n-icon></template>
                        Enviar por Email
                        <span v-if="!canSendReminder.email" class="text-xs ml-2">(sin email registrado)</span>
                    </n-button>

                    <n-button
                        block
                        :type="canSendReminder.whatsapp ? 'success' : 'default'"
                        :disabled="!canSendReminder.whatsapp || sendingReminder"
                        :loading="sendingReminder"
                        @click="sendReminder('whatsapp')"
                    >
                        <template #icon><n-icon><LogoWhatsapp /></n-icon></template>
                        Enviar por WhatsApp
                        <span v-if="!canSendReminder.whatsapp" class="text-xs ml-2">(sin teléfono registrado)</span>
                    </n-button>

                    <n-button
                        block
                        type="primary"
                        :disabled="sendingReminder || (!canSendReminder.email && !canSendReminder.whatsapp)"
                        :loading="sendingReminder"
                        @click="sendReminder('both')"
                        v-if="canSendReminder.email && canSendReminder.whatsapp"
                    >
                        <template #icon><n-icon><SendOutline /></n-icon></template>
                        Enviar por Ambos Canales
                    </n-button>
                </n-space>
            </n-card>
        </n-modal>

        <!-- MODAL AGREGAR/EDITAR PLAN DE PAGO -->
        <n-modal v-model:show="showPaymentMethodModal" :mask-closable="false">
            <n-card
                style="width: 420px"
                title="Plan de Pago"
                :bordered="false"
                size="small"
            >
                <n-form :model="paymentMethodForm">
                    <n-form-item label="Método de pago" required>
                        <n-select
                            v-model:value="paymentMethodForm.payment_method"
                            :options="paymentMethodOptions"
                            placeholder="Seleccionar..."
                        />
                    </n-form-item>
                    <n-form-item label="Anticipo (opcional)">
                        <n-input-number v-model:value="paymentMethodForm.down_payment" :min="0" :precision="2">
                            <template #prefix>$</template>
                        </n-input-number>
                    </n-form-item>
                </n-form>
                <template #footer>
                    <div class="flex justify-end gap-3">
                        <n-button @click="showPaymentMethodModal = false">Cancelar</n-button>
                        <n-button type="primary" @click="savePaymentMethod" :loading="savingPaymentMethod">Guardar</n-button>
                    </div>
                </template>
            </n-card>
        </n-modal>
    </div>
</template>

<style scoped>
:deep(.n-timeline-item-content) {
    padding-bottom: 16px;
}
</style>
