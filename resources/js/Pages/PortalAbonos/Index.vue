<script setup>
import { computed, ref } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { NButton, NInput, NModal, NPopconfirm, NTag } from 'naive-ui';
import { CheckmarkCircleOutline, CloseCircleOutline, DocumentTextOutline } from '@vicons/ionicons5';

const props = defineProps({
    abonos: Array,
});

const page = usePage();

const rejectTarget = ref(null);
const rejectReason = ref('');
const rejectSending = ref(false);
const approvingId = ref(null);

const pendingCount = computed(() => props.abonos.filter((a) => a.status === 'En revisión').length);

const formatCurrency = (amount) =>
    new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(amount || 0);

function statusTag(status) {
    if (status === 'Completado') return { type: 'success', label: 'Completado' };
    if (status === 'Rechazado') return { type: 'error', label: 'Rechazado' };

    return { type: 'warning', label: 'En revisión' };
}

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
    <div class="py-8 min-h-screen bg-gray-50">
        <Head title="Validación de abonos del portal" />

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
                <div>
                    <h2 class="font-bold text-2xl text-gray-800">Abonos del Portal de Clientes</h2>
                    <p class="text-sm text-gray-500 mt-1">
                        {{ abonos.length }} abonos registrados · {{ pendingCount }} pendientes de validación
                    </p>
                </div>
            </div>

            <div class="bg-white rounded-3xl shadow-lg border border-gray-100 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Cliente</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Servicio</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Monto</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Fecha pago</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Método</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Estatus</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Comprobante</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="abono in abonos" :key="abono.id" class="hover:bg-gray-50">
                                <td class="px-4 py-3 text-sm text-gray-800 font-medium">{{ abono.client_name || '—' }}</td>
                                <td class="px-4 py-3 text-sm text-gray-600">{{ abono.service_number || '—' }}</td>
                                <td class="px-4 py-3 text-sm font-semibold text-gray-800">{{ formatCurrency(abono.amount) }}</td>
                                <td class="px-4 py-3 text-sm text-gray-600">
                                    {{ abono.payment_date }}
                                    <span class="block text-xs text-gray-400">Registrado: {{ abono.created_at }}</span>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600">
                                    {{ abono.method }}
                                    <span v-if="abono.reference" class="block text-xs text-gray-400">Ref: {{ abono.reference }}</span>
                                </td>
                                <td class="px-4 py-3">
                                    <n-tag :type="statusTag(abono.status).type" size="small" round>
                                        {{ statusTag(abono.status).label }}
                                    </n-tag>
                                    <p v-if="abono.rejection_reason" class="text-xs text-red-500 mt-1 max-w-[180px]">
                                        {{ abono.rejection_reason }}
                                    </p>
                                </td>
                                <td class="px-4 py-3">
                                    <a
                                        v-if="abono.receipt"
                                        :href="abono.receipt.url"
                                        target="_blank"
                                        class="inline-flex items-center gap-1 text-blue-600 hover:text-blue-800 text-sm"
                                    >
                                        <n-icon :component="DocumentTextOutline" size="16" />
                                        Ver
                                    </a>
                                    <span v-else class="text-gray-400 text-sm">—</span>
                                </td>
                                <td class="px-4 py-3">
                                    <div v-if="abono.status === 'En revisión'" class="flex items-center gap-2">
                                        <n-popconfirm @positive-click="approve(abono)">
                                            <template #trigger>
                                                <n-button
                                                    size="small"
                                                    type="success"
                                                    :loading="approvingId === abono.id"
                                                >
                                                    Aprobar
                                                </n-button>
                                            </template>
                                            ¿Aprobar y registrar como pago?
                                        </n-popconfirm>

                                        <n-button size="small" type="error" secondary @click="openReject(abono)">
                                            Rechazar
                                        </n-button>
                                    </div>
                                    <span v-else class="text-xs text-gray-400">Procesado</span>
                                </td>
                            </tr>
                            <tr v-if="!abonos.length">
                                <td colspan="8" class="px-4 py-10 text-center text-gray-400">
                                    Aún no hay abonos registrados desde el portal.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <n-modal
            :show="rejectTarget !== null"
            @update:show="(v) => { if (!v) rejectTarget = null; }"
            preset="card"
            title="Rechazar abono"
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
    </div>
</template>
