<script setup>
import { ref, computed } from 'vue';
import { usePage, Link, router } from '@inertiajs/vue3';
import {
    NCard, NList, NListItem, NAvatar, NEmpty, NButton, NIcon,
    NTooltip, NTag, NModal, NInput, NPopconfirm,
} from 'naive-ui';
import {
    CashOutline, DocumentTextOutline, CheckmarkCircleOutline, CloseCircleOutline,
} from '@vicons/ionicons5';
import PermissionTooltip from '@/Components/MyComponents/PermissionTooltip.vue';

const props = defineProps({
    payments: Array,
});

const page = usePage();

const approvingId = ref(null);
const rejectTarget = ref(null);
const rejectReason = ref('');
const rejectSending = ref(false);

const pendingCount = computed(() => (props.payments || []).length);

const formatCurrency = (value) => {
    return new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(value || 0);
};

const getInitials = (name) => {
    return name?.split(' ').map(n => n[0]).slice(0, 2).join('').toUpperCase() || '?';
};

function approve(abono) {
    approvingId.value = abono.id;
    router.post(
        route('portal-abonos.approve', abono.id),
        {},
        {
            preserveScroll: true,
            onFinish: () => (approvingId.value = null),
        }
    );
}

function openReject(abono) {
    rejectTarget.value = abono;
    rejectReason.value = '';
}

function confirmReject() {
    if (!rejectTarget.value) return;

    rejectSending.value = true;

    router.post(
        route('portal-abonos.reject', rejectTarget.value.id),
        { rejection_reason: rejectReason.value },
        {
            preserveScroll: true,
            onSuccess: () => {
                rejectTarget.value = null;
                rejectReason.value = '';
            },
            onFinish: () => (rejectSending.value = false),
        }
    );
}
</script>

<template>
    <n-card title="Abonos del Portal por Validar" size="medium" class="shadow-sm rounded-2xl border-none" content-style="padding: 0;">
        <template #header-extra>
            <div class="flex items-center gap-2">
                <PermissionTooltip permission="validar_abonos" placement="bottom" :size="13" />
                <Link :href="route('portal-abonos.index')" class="text-sm text-blue-600 hover:text-blue-800 font-medium">
                    Ver todos
                </Link>
            </div>
        </template>

        <div v-if="pendingCount > 0" class="max-h-[450px] overflow-auto">
            <n-list hoverable>
                <n-list-item v-for="p in payments" :key="p.id">
                    <div class="flex items-center justify-between md:px-5 py-3 gap-3">
                        <!-- Cliente + Info -->
                        <div class="flex items-center gap-3 min-w-0 flex-1">
                            <n-avatar round size="small" class="font-bold bg-amber-100 text-amber-600">
                                {{ getInitials(p.client_name) }}
                            </n-avatar>
                            <div class="min-w-0">
                                <div class="font-medium text-gray-800 text-sm truncate">{{ p.client_name || '—' }}</div>
                                <div class="flex items-center gap-1.5 mt-0.5 flex-wrap">
                                    <n-tag type="warning" size="tiny" round :bordered="false">
                                        En revisión
                                    </n-tag>
                                    <span class="text-xs text-gray-500">
                                        {{ p.service_number ? 'OS ' + p.service_number : 'Sin servicio' }} · {{ p.method }}
                                    </span>
                                </div>
                                <div class="text-[11px] text-gray-400 mt-0.5">
                                    Registrado: {{ p.created_at }}
                                    <span v-if="p.reference"> · Ref: {{ p.reference }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Monto + Acciones -->
                        <div class="flex items-center gap-3 flex-shrink-0">
                            <div class="text-right">
                                <div class="font-bold text-sm text-gray-800">{{ formatCurrency(p.amount) }}</div>
                                <div class="text-[10px] text-gray-400">Pago: {{ p.payment_date }}</div>
                            </div>

                            <div class="flex gap-1.5">
                                <!-- Ver comprobante -->
                                <n-tooltip v-if="p.receipt" trigger="hover">
                                    <template #trigger>
                                        <a
                                            :href="p.receipt.url"
                                            target="_blank"
                                            rel="noopener"
                                            class="inline-flex"
                                        >
                                            <n-button circle size="small" quaternary type="info">
                                                <template #icon><n-icon :component="DocumentTextOutline" /></template>
                                            </n-button>
                                        </a>
                                    </template>
                                    Ver comprobante
                                </n-tooltip>

                                <!-- Aprobar -->
                                <n-popconfirm @positive-click="approve(p)">
                                    <template #trigger>
                                        <n-button
                                            circle size="small" type="success"
                                            :loading="approvingId === p.id"
                                        >
                                            <template #icon><n-icon :component="CheckmarkCircleOutline" /></template>
                                        </n-button>
                                    </template>
                                    ¿Aprobar {{ formatCurrency(p.amount) }} y restarlo del saldo?
                                </n-popconfirm>

                                <!-- Rechazar -->
                                <n-tooltip trigger="hover">
                                    <template #trigger>
                                        <n-button circle size="small" type="error" quaternary @click="openReject(p)">
                                            <template #icon><n-icon :component="CloseCircleOutline" /></template>
                                        </n-button>
                                    </template>
                                    Rechazar
                                </n-tooltip>
                            </div>
                        </div>
                    </div>
                </n-list-item>
            </n-list>
        </div>
        <div v-else class="p-8">
            <n-empty description="No hay abonos pendientes de validar">
                <template #extra>
                    <n-icon size="40" class="text-emerald-300"><CashOutline /></n-icon>
                </template>
            </n-empty>
        </div>

        <!-- MODAL RECHAZO -->
        <n-modal
            :show="rejectTarget !== null"
            @update:show="(v) => { if (!v) rejectTarget = null; }"
            preset="card"
            title="Rechazar abono del portal"
            style="width: min(480px, 92vw)"
            :mask-closable="false"
        >
            <p class="text-sm text-gray-600 mb-3">
                Indica el motivo por el que se rechaza el abono de
                <strong>{{ rejectTarget?.client_name }}</strong> por {{ formatCurrency(rejectTarget?.amount) }}.
                El cliente podrá verlo en el portal.
            </p>
            <n-input
                v-model:value="rejectReason"
                type="textarea"
                :rows="3"
                placeholder="Ej. El comprobante no coincide con el monto registrado."
                :status="page.props.errors?.rejection_reason ? 'error' : undefined"
            />
            <p v-if="page.props.errors?.rejection_reason" class="text-xs text-red-500 mt-1">
                {{ page.props.errors.rejection_reason }}
            </p>

            <template #footer>
                <div class="flex justify-end gap-2">
                    <n-button size="small" @click="rejectTarget = null">Cancelar</n-button>
                    <n-button
                        size="small"
                        type="error"
                        :loading="rejectSending"
                        :disabled="!rejectReason.trim()"
                        @click="confirmReject"
                    >
                        <template #icon><n-icon :component="CloseCircleOutline" /></template>
                        Confirmar rechazo
                    </n-button>
                </div>
            </template>
        </n-modal>
    </n-card>
</template>
