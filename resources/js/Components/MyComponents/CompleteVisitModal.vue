<script setup>
import { ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import {
    NButton, NIcon, NModal, NCard, NForm, NFormItem, NInput, NSelect,
    NSwitch, NDivider, NTag
} from 'naive-ui';
import {
    CheckmarkDoneOutline, BusinessOutline, HardwareChipOutline,
    CashOutline, HomeOutline, CheckmarkCircleOutline, CreateOutline
} from '@vicons/ionicons5';

const props = defineProps({
    show: { type: Boolean, default: false },
    visitId: { type: [Number, String], required: true },
    systemType: { type: String, default: null },
});

const emit = defineEmits(['update:show', 'completed']);

// --- Estado interno ---
const completeStep = ref(1);
const isCompleting = ref(false);
const taxId = ref('');
const newServiceOrderId = ref(null);

// Propuesta comercial
const paymentMethod = ref(null);
const downPayment = ref(null);
const requiresPreInstallation = ref(false);
const preInstallationAssignedTo = ref(null);
const preInstallationDetails = ref('');

const paymentMethodOptions = [
    { label: 'Contado', value: 'Contado' },
    { label: '3 MSI', value: '3 MSI' },
    { label: '6 MSI', value: '6 MSI' },
    { label: '9 MSI', value: '9 MSI' },
    { label: '12 MSI', value: '12 MSI' },
    { label: 'Personalizado', value: 'Personalizado' },
];

const preInstallationOptions = [
    { label: "Sun's power mx", value: "Sun's power mx" },
    { label: 'Cliente', value: 'Cliente' },
    { label: 'Otro', value: 'Otro' },
];

// Resetear estado al abrir el modal
watch(() => props.show, (val) => {
    if (val) {
        completeStep.value = 1;
        taxId.value = '';
        newServiceOrderId.value = null;
        paymentMethod.value = null;
        downPayment.value = null;
        requiresPreInstallation.value = false;
        preInstallationAssignedTo.value = null;
        preInstallationDetails.value = '';
    }
});

const handleClose = () => {
    emit('update:show', false);
};

// --- Paso 1: Convertir a Cliente ---
const handleCompleteFlow = (createClient) => {
    isCompleting.value = true;
    router.post(route('technical-visits.convert-to-client', props.visitId), {
        create_client: createClient,
        tax_id: taxId.value || null,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            isCompleting.value = false;
            if (createClient) {
                completeStep.value = 2;
            } else {
                handleClose();
                emit('completed');
            }
        },
        onError: () => {
            isCompleting.value = false;
        },
    });
};

// --- Paso 2: Crear Orden de Servicio ---
const handleCreateServiceOrder = () => {
    isCompleting.value = true;
    router.post(route('technical-visits.create-service-order', props.visitId), {
        payment_method: paymentMethod.value,
        down_payment: downPayment.value ? parseFloat(downPayment.value) : null,
        requires_pre_installation: requiresPreInstallation.value,
        pre_installation_assigned_to: preInstallationAssignedTo.value,
        pre_installation_details: preInstallationDetails.value || null,
    }, {
        preserveScroll: true,
        onSuccess: (page) => {
            isCompleting.value = false;
            newServiceOrderId.value = page.props.flash?.new_service_order_id || null;
            completeStep.value = 3;
        },
        onError: () => {
            isCompleting.value = false;
        },
    });
};

// --- Paso 3: Ir a editar orden ---
const goToServiceOrderEdit = (id) => {
    handleClose();
    emit('completed');
    router.visit(route('service-orders.edit', id || newServiceOrderId.value));
};
</script>

<template>
    <n-modal v-model:show="props.show" :mask-closable="false" @update:show="val => !val && handleClose()">
        <n-card
            style="width: 480px"
            :title="completeStep === 1 ? 'Completar Visita' : 'Crear Orden de Servicio'"
            :bordered="false"
            size="huge"
            role="dialog"
            aria-modal="true"
        >
            <template #header-extra>
                <n-icon size="24" :component="CheckmarkDoneOutline" class="text-green-500" />
            </template>

            <!-- Paso 1: ¿Convertir a Cliente? -->
            <div v-if="completeStep === 1">
                <div class="text-center mb-6">
                    <n-icon size="48" :component="BusinessOutline" class="text-indigo-400 mb-3" />
                    <h3 class="text-lg font-semibold text-gray-800">¿Convertir prospecto en cliente?</h3>
                    <p class="text-sm text-gray-500 mt-2">
                        Se creará un nuevo registro de cliente con los datos de esta visita
                        (nombre, dirección y contacto).
                    </p>
                </div>

                <div class="mb-6 px-4">
                    <n-input
                        v-model:value="taxId"
                        placeholder="RFC del cliente (opcional)"
                        maxlength="13"
                        class="w-full"
                    >
                        <template #prefix>
                            <span class="text-xs text-gray-400 font-mono">RFC</span>
                        </template>
                    </n-input>
                </div>

                <div class="flex justify-center gap-4">
                    <n-button size="large" @click="handleClose" :disabled="isCompleting">
                        Cancelar
                    </n-button>
                    <n-button size="large" @click="handleCompleteFlow(false)" :loading="isCompleting">
                        No, solo terminar
                    </n-button>
                    <n-button size="large" type="primary" @click="handleCompleteFlow(true)" :loading="isCompleting">
                        <template #icon><n-icon><BusinessOutline /></n-icon></template>
                        Sí, crear cliente
                    </n-button>
                </div>
            </div>

            <!-- Paso 2: ¿Crear Orden de Servicio? -->
            <div v-if="completeStep === 2">
                <div class="text-center mb-6">
                    <n-icon size="48" :component="HardwareChipOutline" class="text-green-400 mb-3" />
                    <h3 class="text-lg font-semibold text-gray-800">¿Crear orden de servicio?</h3>
                    <p class="text-sm text-gray-500 mt-2">
                        Se generará una orden de servicio tipo
                        <strong>{{ props.systemType || 'sin definir' }}</strong>
                        con tareas, evidencias y productos configurados automáticamente.
                    </p>
                </div>

                <div class="bg-gray-50 rounded-xl p-4 mb-6 space-y-4">
                    <h4 class="text-sm font-bold text-gray-600 flex items-center gap-2">
                        <n-icon :component="CashOutline" class="text-indigo-500" />
                        Propuesta Comercial
                    </h4>

                    <n-form-item label="Método de Pago" label-placement="top" size="small">
                        <n-select
                            v-model:value="paymentMethod"
                            :options="paymentMethodOptions"
                            placeholder="Seleccionar plan de pago"
                            clearable
                        />
                    </n-form-item>

                    <n-form-item label="Anticipo (MXN)" label-placement="top" size="small">
                        <n-input
                            v-model:value="downPayment"
                            type="number"
                            placeholder="0.00"
                            :min="0"
                            :step="0.01"
                        >
                            <template #prefix>$</template>
                        </n-input>
                    </n-form-item>

                    <n-divider class="!my-3" />

                    <h4 class="text-sm font-bold text-gray-600 flex items-center gap-2">
                        <n-icon :component="HomeOutline" class="text-orange-500" />
                        Acondicionamiento Previo
                    </h4>

                    <n-form-item label="¿Requiere acondicionamiento previo a la instalación?" label-placement="top" size="small">
                        <n-switch v-model:value="requiresPreInstallation">
                            <template #checked>Sí</template>
                            <template #unchecked>No</template>
                        </n-switch>
                    </n-form-item>

                    <template v-if="requiresPreInstallation">
                        <n-form-item label="¿Quién lo realizará?" label-placement="top" size="small">
                            <n-select
                                v-model:value="preInstallationAssignedTo"
                                :options="preInstallationOptions"
                                placeholder="Seleccionar responsable"
                                clearable
                            />
                        </n-form-item>

                        <n-form-item label="Detalles del acondicionamiento" label-placement="top" size="small">
                            <n-input
                                v-model:value="preInstallationDetails"
                                type="textarea"
                                placeholder="Describir los trabajos de acondicionamiento necesarios..."
                                :autosize="{ minRows: 2, maxRows: 4 }"
                            />
                        </n-form-item>
                    </template>
                </div>

                <div class="flex justify-center gap-4">
                    <n-button size="large" @click="handleClose" :disabled="isCompleting">
                        No, finalizar
                    </n-button>
                    <n-button size="large" type="success" @click="handleCreateServiceOrder()" :loading="isCompleting">
                        <template #icon><n-icon><CheckmarkCircleOutline /></n-icon></template>
                        Sí, crear orden
                    </n-button>
                </div>
            </div>

            <!-- Paso 3: Orden Creada Exitosa -->
            <div v-if="completeStep === 3">
                <div class="text-center mb-6">
                    <n-icon size="48" :component="CheckmarkDoneOutline" class="text-green-500 mb-3" />
                    <h3 class="text-lg font-semibold text-gray-800">¡Orden de servicio creada exitosamente!</h3>
                    <p class="text-sm text-gray-500 mt-2">
                        Algunos campos no pudieron llenarse automáticamente desde la visita técnica.
                        ¿Quieres completarlos ahora?
                    </p>
                </div>
                <div class="flex justify-center gap-4">
                    <n-button size="large" @click="handleClose">
                        Cerrar
                    </n-button>
                    <n-button size="large" type="primary" @click="goToServiceOrderEdit(newServiceOrderId)">
                        <template #icon><n-icon><CreateOutline /></n-icon></template>
                        Completar orden
                    </n-button>
                </div>
            </div>
        </n-card>
    </n-modal>
</template>
