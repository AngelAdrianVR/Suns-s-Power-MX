<script setup>
import { ref, computed } from 'vue';
import { usePermissions } from '@/Composables/usePermissions';
import { NCard, NList, NListItem, NAvatar, NEmpty, NButton, NIcon, NTooltip, NTag, NModal, NCard as NCardModal, NSpace, NAlert, createDiscreteApi } from 'naive-ui';
import { Link, router } from '@inertiajs/vue3';
import { 
    CashOutline, EyeOutline, SendOutline, CalendarOutline,
    AlertCircleOutline, TimeOutline, CheckmarkCircleOutline,
    MailOutline, LogoWhatsapp 
} from '@vicons/ionicons5';
import axios from 'axios';

const props = defineProps({
    payments: Array,
});

const { hasPermission } = usePermissions();
const { notification } = createDiscreteApi(['notification']);

const formatCurrency = (value) => {
    return new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(value || 0);
};

const formatDate = (dateStr) => {
    if (!dateStr) return '-';
    const d = new Date(dateStr + 'T12:00:00');
    return d.toLocaleDateString('es-MX', { day: 'numeric', month: 'short' });
};

const getInitials = (name) => {
    return name?.split(' ').map(n => n[0]).slice(0, 2).join('').toUpperCase() || '?';
};

// --- SEMÁFORO ---
const trafficLight = (payment) => {
    if (payment.is_overdue) {
        if (payment.days_abs <= 5) return { color: '#f59e0b', bg: '#fef3c7', label: `${payment.days_abs} día(s) de retraso`, dot: 'warning' };
        if (payment.days_abs <= 10) return { color: '#ef4444', bg: '#fee2e2', label: `${payment.days_abs} día(s) de retraso`, dot: 'error' };
        return { color: '#dc2626', bg: '#fef2f2', label: `${payment.days_abs} día(s) vencido`, dot: 'error' };
    }
    // Futuro
    if (payment.days_abs <= 2) return { color: '#f59e0b', bg: '#fef3c7', label: `Vence en ${payment.days_abs} día(s)`, dot: 'warning' };
    if (payment.days_abs <= 5) return { color: '#eab308', bg: '#fefce8', label: `Vence en ${payment.days_abs} día(s)`, dot: 'warning' };
    return { color: '#10b981', bg: '#d1fae5', label: `Vence en ${payment.days_abs} día(s)`, dot: 'success' };
};

const goToClient = (clientId) => {
    router.visit(route('clients.show', clientId));
};

// --- RECORDATORIO ---
const sendingReminder = ref(false);
const showReminderModal = ref(false);
const reminderPayment = ref(null);

const openReminderModal = (payment) => {
    reminderPayment.value = payment;
    showReminderModal.value = true;
};

const sendReminder = async (channel) => {
    if (!reminderPayment.value) return;
    sendingReminder.value = true;
    try {
        const payload = { channel, installment: reminderPayment.value.installment };
        const response = await axios.post(
            route('api.service-orders.send-reminder', reminderPayment.value.service_order_id),
            payload
        );
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
</script>

<template>
    <n-card title="Pagos Próximos / Vencidos" size="medium" class="shadow-sm rounded-2xl border-none" content-style="padding: 0;">
        <template #header-extra>
            <Link v-if="hasPermission('clients.index')" :href="route('clients.index')" class="text-sm text-blue-600 hover:text-blue-800 font-medium">
                Ver clientes
            </Link>
        </template>

        <div v-if="payments.length > 0" class="max-h-[450px] overflow-auto">
            <n-list hoverable>
                <n-list-item v-for="p in payments" :key="p.id">
                    <div class="flex items-center justify-between md:px-5 py-3">
                        <!-- Cliente + Info -->
                        <div class="flex items-center gap-3 min-w-0 flex-1">
                            <n-avatar round size="small" class="font-bold"
                                :class="p.is_overdue ? 'bg-red-100 text-red-600' : 'bg-emerald-100 text-emerald-600'"
                            >
                                {{ getInitials(p.client?.name) }}
                            </n-avatar>
                            <div class="min-w-0">
                                <div class="font-medium text-gray-800 text-sm truncate">{{ p.client?.name }}</div>
                                <div class="flex items-center gap-1.5 mt-0.5">
                                    <n-tag
                                        :type="trafficLight(p).dot"
                                        size="tiny"
                                        round
                                        :bordered="false"
                                    >
                                        {{ trafficLight(p).label }}
                                    </n-tag>
                                </div>
                            </div>
                        </div>

                        <!-- Monto + Acciones -->
                        <div class="flex items-center gap-3 flex-shrink-0">
                            <div class="text-right">
                                <div class="font-bold text-sm" :class="p.is_overdue ? 'text-red-600' : 'text-gray-800'">
                                    {{ formatCurrency(p.amount) }}
                                </div>
                                <div class="text-[10px] text-gray-400">
                                    {{ p.label }} — {{ formatDate(p.projected_date) }}
                                </div>
                            </div>

                            <div class="flex gap-1.5">
                                <!-- Ver detalles -->
                                <n-tooltip trigger="hover">
                                    <template #trigger>
                                        <n-button
                                            circle size="small" quaternary type="info"
                                            @click.stop="goToClient(p.client.id)"
                                        >
                                            <template #icon><n-icon :component="EyeOutline" /></template>
                                        </n-button>
                                    </template>
                                    Ver expediente
                                </n-tooltip>

                                <!-- Recordatorio -->
                                <n-tooltip v-if="hasPermission('collection.create') && (p.has_email || p.has_phone)" trigger="hover">
                                    <template #trigger>
                                        <n-button
                                            circle size="small" quaternary type="warning"
                                            @click.stop="openReminderModal(p)"
                                        >
                                            <template #icon><n-icon :component="SendOutline" /></template>
                                        </n-button>
                                    </template>
                                    Recordar pago
                                </n-tooltip>
                            </div>
                        </div>
                    </div>
                </n-list-item>
            </n-list>
        </div>
        <div v-else class="p-8">
            <n-empty description="No hay pagos próximos o vencidos">
                <template #extra>
                    <n-icon size="40" class="text-emerald-300"><CheckmarkCircleOutline /></n-icon>
                </template>
            </n-empty>
        </div>

        <!-- MODAL RECORDATORIO -->
        <n-modal v-model:show="showReminderModal" :mask-closable="true">
            <n-card-modal
                style="width: 450px; border-radius: 0.75rem;"
                title="Enviar Recordatorio de Pago"
                :bordered="false"
                size="small"
            >
                <n-alert v-if="reminderPayment" type="warning" :bordered="false" class="mb-4">
                    Cliente: <strong>{{ reminderPayment.client?.name }}</strong><br/>
                    Concepto: {{ reminderPayment.label }}<br/>
                    Monto: {{ formatCurrency(reminderPayment.amount) }}<br/>
                    Fecha esperada: {{ formatDate(reminderPayment.projected_date) }}
                    <span v-if="reminderPayment.is_overdue" class="text-red-600 font-bold">
                        — {{ reminderPayment.days_abs }} día(s) vencido
                    </span>
                </n-alert>

                <p class="text-sm text-gray-600 mb-4">Selecciona el canal de comunicación:</p>

                <n-space vertical :size="12">
                    <n-button
                        block :type="reminderPayment?.has_email ? 'info' : 'default'"
                        :disabled="!reminderPayment?.has_email || sendingReminder"
                        :loading="sendingReminder"
                        @click="sendReminder('email')"
                    >
                        <template #icon><n-icon><MailOutline /></n-icon></template>
                        Enviar por Email
                        <span v-if="!reminderPayment?.has_email" class="text-xs ml-2">(sin email)</span>
                    </n-button>
                    <n-button
                        block :type="reminderPayment?.has_phone ? 'success' : 'default'"
                        :disabled="!reminderPayment?.has_phone || sendingReminder"
                        :loading="sendingReminder"
                        @click="sendReminder('whatsapp')"
                    >
                        <template #icon><n-icon><LogoWhatsapp /></n-icon></template>
                        Enviar por WhatsApp
                        <span v-if="!reminderPayment?.has_phone" class="text-xs ml-2">(sin teléfono)</span>
                    </n-button>
                    <n-button
                        v-if="reminderPayment?.has_email && reminderPayment?.has_phone"
                        block type="primary"
                        :disabled="sendingReminder"
                        :loading="sendingReminder"
                        @click="sendReminder('both')"
                    >
                        <template #icon><n-icon><SendOutline /></n-icon></template>
                        Enviar por Ambos
                    </n-button>
                </n-space>
            </n-card-modal>
        </n-modal>
    </n-card>
</template>
