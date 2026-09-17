<script setup>
import { ref, computed, watch, reactive } from 'vue';
import axios from 'axios';
import {
    NModal, NSteps, NStep, NButton, NIcon, NSpin, NEmpty, NTag, NPopconfirm,
    NRadioGroup, NRadioButton, NCheckbox, createDiscreteApi
} from 'naive-ui';
import {
    CloudUploadOutline, LinkOutline, FolderOpenOutline, TrashOutline,
    DocumentTextOutline, ArrowBackOutline, ArrowForwardOutline, PrintOutline,
    ChevronDownOutline, CubeOutline
} from '@vicons/ionicons5';

const props = defineProps({
    show: Boolean,
    order: Object,
    steps: Array,
});

const emit = defineEmits(['update:show']);

const { notification } = createDiscreteApi(['notification']);

const loading = ref(false);
const currentStep = ref(0);
const clientDocuments = ref([]);
const orderMedia = ref([]);
const products = ref([]);
const attachmentsByStep = ref({});
const fileInput = ref(null);

// Lista de pasos autoritativa: se refresca con la del servidor al abrir el asistente
// (los props son solo el valor inicial para renderizar rápido).
const steps = ref(props.steps || []);

// Estado local por paso (fuente elegida y selección pendiente)
const stepState = reactive({});

const ensureStepState = (stepId) => {
    if (!stepState[stepId]) {
        stepState[stepId] = {
            source: 'upload', // upload | client | order | product
            selectedClient: [],
            selectedOrder: [],
            selectedProduct: [],
            expandedProduct: null,
            uploading: false,
            linking: false,
        };
    }
    return stepState[stepId];
};

const current = computed(() => steps.value[currentStep.value] || null);
const state = computed(() => (current.value ? ensureStepState(current.value.id) : {}));
const isLastStep = computed(() => currentStep.value >= (steps.value.length || 0) - 1);

const currentAttachments = computed(() => attachmentsByStep.value[current.value?.id] || []);

watch(() => props.show, async (visible) => {
    if (visible) {
        currentStep.value = 0;
        await loadData();
    }
});

const loadData = async () => {
    loading.value = true;
    try {
        const { data } = await axios.get(route('service-orders.documentation.wizard', props.order.id));
        steps.value = data.steps || [];
        clientDocuments.value = data.client_documents || [];
        orderMedia.value = data.order_media || [];
        products.value = data.products || [];
        attachmentsByStep.value = data.attachments || {};
    } catch (error) {
        notification.error({
            title: 'Error',
            content: `No se pudo cargar la información del asistente. ${extractErrorMessage(error)}`,
            duration: 6000
        });
    } finally {
        loading.value = false;
    }
};

const isImage = (file) => {
    if (file.mime_type) {
        // HEIC/HEIF (fotos de iPhone) no se pueden previsualizar en el navegador
        if (/heic|heif/i.test(file.mime_type)) return false;
        return file.mime_type.startsWith('image/');
    }
    return /\.(jpg|jpeg|png|gif|webp)$/i.test(file.file_name || '');
};

const sourceLabel = (source) => {
    return { upload: 'Subido', client: 'Del cliente', order: 'De la orden', product: 'De producto' }[source] || source;
};

// Extrae el mensaje real del servidor (validación, 419, 500, etc.)
const extractErrorMessage = (error) => {
    const data = error?.response?.data;
    if (data?.errors) {
        const first = Object.values(data.errors)[0];
        return Array.isArray(first) ? first[0] : String(first);
    }
    if (data?.message) return data.message;
    if (error?.message && !error.message.includes('Network')) return error.message;
    return 'Revisa tu conexión e inténtalo de nuevo.';
};

const MAX_FILE_SIZE_MB = 20;

// --- SUBIR ARCHIVOS ---
const onFilesSelected = async (event) => {
    const files = Array.from(event.target.files || []);
    if (!files.length || !current.value) return;

    const oversized = files.find((file) => file.size > MAX_FILE_SIZE_MB * 1024 * 1024);
    if (oversized) {
        notification.error({
            title: 'Archivo demasiado grande',
            content: `"${oversized.name}" supera ${MAX_FILE_SIZE_MB} MB. Comprímelo o elige otro.`,
            duration: 5000
        });
        if (event.target) event.target.value = '';
        return;
    }

    const st = state.value;
    const stepId = current.value.id;
    const stepTitle = current.value.title;
    st.uploading = true;

    try {
        const formData = new FormData();
        files.forEach((file) => formData.append('files[]', file));
        formData.append('step_id', stepId);

        const { data } = await axios.post(
            route('service-orders.documentation.upload', props.order.id),
            formData
        );

        const added = data.attachments || [];
        attachmentsByStep.value[stepId] = [
            ...(attachmentsByStep.value[stepId] || []),
            ...added,
        ];

        notification.success({
            title: 'Archivos subidos',
            content: `${added.length} archivo(s) agregados al paso "${stepTitle}".`,
            duration: 3500
        });
    } catch (error) {
        notification.error({
            title: 'Error al subir',
            content: extractErrorMessage(error),
            duration: 6000
        });
    } finally {
        st.uploading = false;
        if (event.target) event.target.value = '';
    }
};

// --- VINCULAR ARCHIVOS EXISTENTES ---
const toggleMedia = (source, mediaId) => {
    const st = state.value;
    const key = source === 'client' ? 'selectedClient' : source === 'product' ? 'selectedProduct' : 'selectedOrder';
    const index = st[key].indexOf(mediaId);
    if (index >= 0) {
        st[key].splice(index, 1);
    } else {
        st[key].push(mediaId);
    }
};

// Expande/colapsa un producto para ver sus archivos
const toggleProduct = (productId) => {
    const st = state.value;
    st.expandedProduct = st.expandedProduct === productId ? null : productId;
};

const selectedCount = computed(() => {
    const st = state.value;
    if (st.source === 'client') return st.selectedClient.length;
    if (st.source === 'product') return st.selectedProduct.length;
    return st.selectedOrder.length;
});

const linkSelection = async () => {
    const st = state.value;
    const ids = st.source === 'client'
        ? st.selectedClient
        : (st.source === 'product' ? st.selectedProduct : st.selectedOrder);

    if (!ids.length) {
        notification.warning({ title: 'Selecciona archivos', content: 'Elige al menos un archivo para vincular.', duration: 3000 });
        return;
    }

    st.linking = true;
    try {
        const { data } = await axios.post(route('service-orders.documentation.link', props.order.id), {
            step_id: current.value.id,
            source: st.source,
            media_ids: ids,
        });

        const existing = attachmentsByStep.value[current.value.id] || [];
        const merged = [...existing];
        (data.attachments || []).forEach((attachment) => {
            if (!merged.some((item) => item.id === attachment.id)) merged.push(attachment);
        });
        attachmentsByStep.value[current.value.id] = merged;

        if (st.source === 'client') st.selectedClient = [];
        else if (st.source === 'product') st.selectedProduct = [];
        else st.selectedOrder = [];

        notification.success({ title: 'Vinculado', content: 'Archivo(s) vinculados a este paso.', duration: 3000 });
    } catch (error) {
        notification.error({ title: 'Error al vincular', content: extractErrorMessage(error), duration: 6000 });
    } finally {
        st.linking = false;
    }
};

// --- QUITAR ARCHIVO DEL PASO ---
const removeAttachment = async (attachment) => {
    try {
        await axios.delete(route('service-orders.documentation.attachment.destroy', {
            serviceOrder: props.order.id,
            attachment: attachment.id,
        }));

        attachmentsByStep.value[attachment.step_id] = (attachmentsByStep.value[attachment.step_id] || [])
            .filter((item) => item.id !== attachment.id);

        notification.success({ title: 'Eliminado', content: 'Archivo removido de este paso.', duration: 2500 });
    } catch (error) {
        notification.error({ title: 'Error al eliminar', content: extractErrorMessage(error), duration: 6000 });
    }
};

// --- NAVEGACIÓN ---
const close = () => emit('update:show', false);

const goNext = () => {
    if (!isLastStep.value) currentStep.value++;
};

const goPrev = () => {
    if (currentStep.value > 0) currentStep.value--;
};

const jumpTo = (index) => {
    if (index >= 0 && index < (steps.value.length || 0)) {
        currentStep.value = index;
    }
};

const generateExpediente = () => {
    window.open(route('service-orders.documentation.print', props.order.id), '_blank');
    close();
};
</script>

<template>
    <n-modal
        :show="show"
        @update:show="(value) => emit('update:show', value)"
        :mask-closable="false"
        preset="card"
        title="Expediente de Documentación de Servicio"
        style="width: 900px; max-width: 95vw;"
        :bordered="false"
        size="huge"
    >
        <template #header-extra>
            <n-tag size="small" :bordered="false" type="info">Orden #{{ order.id }}</n-tag>
        </template>

        <n-spin :show="loading">
            <div v-if="steps.length" class="min-h-[420px]">
                <!-- Indicador de pasos (naive-ui n-steps es 1-based, por eso currentStep + 1) -->
                <n-steps :current="currentStep + 1" size="small" class="mb-6 overflow-x-auto" @update:current="(index) => jumpTo(index - 1)">
                    <n-step
                        v-for="(step, index) in steps"
                        :key="step.id"
                        :title="`${index + 1}. ${step.title}`"
                        :description="(attachmentsByStep[step.id]?.length ? `${attachmentsByStep[step.id].length} archivo(s)` : 'Sin archivos')"
                    />
                </n-steps>

                <div v-if="current">
                    <!-- Título y descripción del paso -->
                    <div class="mb-5">
                        <h4 class="text-lg font-bold text-gray-800">
                            Paso {{ currentStep + 1 }} de {{ steps.length }}: {{ current.title }}
                        </h4>
                        <p v-if="current.description" class="text-sm text-gray-500 mt-1">
                            {{ current.description }}
                        </p>
                    </div>

                    <!-- Selección de fuente de archivos -->
                    <n-radio-group v-model:value="state.source" class="mb-4">
                        <n-radio-button value="upload">
                            <span class="flex items-center gap-1">
                                <n-icon size="14"><CloudUploadOutline /></n-icon> Adjuntar archivos
                            </span>
                        </n-radio-button>
                        <n-radio-button value="client">
                            <span class="flex items-center gap-1">
                                <n-icon size="14"><LinkOutline /></n-icon> Seleccionar del cliente
                            </span>
                        </n-radio-button>
                        <n-radio-button value="order">
                            <span class="flex items-center gap-1">
                                <n-icon size="14"><FolderOpenOutline /></n-icon> Seleccionar de la orden
                            </span>
                        </n-radio-button>
                        <n-radio-button value="product">
                            <span class="flex items-center gap-1">
                                <n-icon size="14"><CubeOutline /></n-icon> Seleccionar de productos
                            </span>
                        </n-radio-button>
                    </n-radio-group>

                    <!-- Adjuntar archivos nuevos -->
                    <div v-if="state.source === 'upload'">
                        <input
                            type="file"
                            ref="fileInput"
                            class="hidden"
                            multiple
                            @change="onFilesSelected"
                        />
                        <div
                            class="border-2 border-dashed border-indigo-200 rounded-xl p-8 text-center cursor-pointer hover:bg-indigo-50/50 transition-colors"
                            @click="fileInput && fileInput.click()"
                        >
                            <n-spin :show="state.uploading">
                                <n-icon size="30" class="text-indigo-400 mb-2"><CloudUploadOutline /></n-icon>
                                <p class="text-sm font-semibold text-indigo-600">
                                    {{ state.uploading ? 'Subiendo archivos...' : 'Haz clic para seleccionar archivos' }}
                                </p>
                                <p class="text-xs text-gray-400 mt-1">Puedes elegir varios archivos a la vez (imágenes, PDF, Word, Excel...)</p>
                            </n-spin>
                        </div>
                    </div>

                    <!-- Vincular del cliente -->
                    <div v-else-if="state.source === 'client'">
                        <p class="text-xs text-gray-400 mb-3">
                            Selecciona documentos del expediente de <strong>{{ order.client?.name || 'el cliente' }}</strong>.
                        </p>
                        <div v-if="clientDocuments.length" class="grid grid-cols-2 md:grid-cols-3 gap-3 max-h-60 overflow-y-auto pr-1">
                            <label
                                v-for="doc in clientDocuments"
                                :key="doc.id"
                                class="border rounded-xl p-3 flex items-start gap-2 cursor-pointer hover:border-indigo-300 transition-colors"
                                :class="state.selectedClient.includes(doc.id) ? 'border-indigo-400 bg-indigo-50/50' : 'border-gray-200 bg-white'"
                            >
                                <n-checkbox
                                    :checked="state.selectedClient.includes(doc.id)"
                                    @update:checked="toggleMedia('client', doc.id)"
                                />
                                <div class="min-w-0">
                                    <img
                                        v-if="isImage(doc)"
                                        :src="doc.url"
                                        class="w-full h-16 object-cover rounded-md mb-1"
                                        alt=""
                                    />
                                    <p class="text-xs font-semibold text-gray-700 truncate" :title="doc.file_name">{{ doc.file_name }}</p>
                                    <p class="text-[10px] text-gray-400">{{ doc.category || 'General' }}</p>
                                </div>
                            </label>
                        </div>
                        <n-empty v-else description="El cliente no tiene documentos adjuntos en su expediente." class="my-6" />
                        <div v-if="clientDocuments.length" class="flex justify-end mt-4">
                            <n-button
                                type="primary"
                                secondary
                                :loading="state.linking"
                                :disabled="!state.selectedClient.length"
                                @click="linkSelection"
                            >
                                <template #icon><n-icon><LinkOutline /></n-icon></template>
                                Vincular seleccionados ({{ state.selectedClient.length }})
                            </n-button>
                        </div>
                    </div>

                    <!-- Vincular de la orden -->
                    <div v-else-if="state.source === 'order'">
                        <p class="text-xs text-gray-400 mb-3">
                            Selecciona archivos adjuntos a la orden de servicio (evidencias y archivos adicionales).
                        </p>
                        <div v-if="orderMedia.length" class="grid grid-cols-2 md:grid-cols-3 gap-3 max-h-60 overflow-y-auto pr-1">
                            <label
                                v-for="doc in orderMedia"
                                :key="doc.id"
                                class="border rounded-xl p-3 flex items-start gap-2 cursor-pointer hover:border-indigo-300 transition-colors"
                                :class="state.selectedOrder.includes(doc.id) ? 'border-indigo-400 bg-indigo-50/50' : 'border-gray-200 bg-white'"
                            >
                                <n-checkbox
                                    :checked="state.selectedOrder.includes(doc.id)"
                                    @update:checked="toggleMedia('order', doc.id)"
                                />
                                <div class="min-w-0">
                                    <img
                                        v-if="isImage(doc)"
                                        :src="doc.url"
                                        class="w-full h-16 object-cover rounded-md mb-1"
                                        alt=""
                                    />
                                    <p class="text-xs font-semibold text-gray-700 truncate" :title="doc.file_name">{{ doc.file_name }}</p>
                                    <p class="text-[10px] text-gray-400">
                                        {{ doc.collection === 'specific_evidences' ? 'Evidencia del checklist' : 'Archivo de la orden' }}
                                    </p>
                                </div>
                            </label>
                        </div>
                        <n-empty v-else description="La orden no tiene archivos adjuntos." class="my-6" />
                        <div v-if="orderMedia.length" class="flex justify-end mt-4">
                            <n-button
                                type="primary"
                                secondary
                                :loading="state.linking"
                                :disabled="!state.selectedOrder.length"
                                @click="linkSelection"
                            >
                                <template #icon><n-icon><FolderOpenOutline /></n-icon></template>
                                Vincular seleccionados ({{ state.selectedOrder.length }})
                            </n-button>
                        </div>
                    </div>

                    <!-- Vincular de productos de la orden -->
                    <div v-else-if="state.source === 'product'">
                        <p class="text-xs text-gray-400 mb-3">
                            Elige un producto de la orden para ver sus archivos vinculados (manuales, fichas técnicas, etc.).
                        </p>
                        <div v-if="products.length" class="border border-gray-200 rounded-xl divide-y divide-gray-100 max-h-72 overflow-y-auto">
                            <div v-for="product in products" :key="product.id" class="bg-white">
                                <button
                                    type="button"
                                    class="w-full flex items-center justify-between gap-2 p-3 text-left hover:bg-gray-50 transition-colors"
                                    @click="toggleProduct(product.id)"
                                >
                                    <div class="min-w-0">
                                        <p class="text-sm font-semibold text-gray-800 truncate">{{ product.name }}</p>
                                        <p class="text-[11px] text-gray-400">
                                            {{ product.sku || 'Sin SKU' }} · {{ product.quantity }} en la orden · {{ product.media.length }} archivo(s)
                                        </p>
                                    </div>
                                    <n-icon size="16" class="text-gray-400 shrink-0 transition-transform" :class="{ 'rotate-180': state.expandedProduct === product.id }">
                                        <ChevronDownOutline />
                                    </n-icon>
                                </button>

                                <div v-if="state.expandedProduct === product.id" class="px-3 pb-3">
                                    <div class="grid grid-cols-2 gap-2">
                                        <label
                                            v-for="doc in product.media"
                                            :key="doc.id"
                                            class="border rounded-lg p-2 flex items-start gap-2 cursor-pointer hover:border-indigo-300 transition-colors"
                                            :class="state.selectedProduct.includes(doc.id) ? 'border-indigo-400 bg-indigo-50/50' : 'border-gray-200 bg-white'"
                                        >
                                            <n-checkbox
                                                :checked="state.selectedProduct.includes(doc.id)"
                                                @update:checked="toggleMedia('product', doc.id)"
                                            />
                                            <div class="min-w-0">
                                                <img
                                                    v-if="isImage(doc)"
                                                    :src="doc.url"
                                                    class="w-full h-14 object-cover rounded-md mb-1"
                                                    alt=""
                                                />
                                                <p class="text-xs font-semibold text-gray-700 truncate" :title="doc.file_name">{{ doc.file_name }}</p>
                                                <p class="text-[10px] text-gray-400">{{ doc.category || 'Archivo del producto' }}</p>
                                            </div>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <n-empty v-else description="Ningún producto de la orden tiene archivos vinculados." class="my-6" />
                        <div v-if="products.length" class="flex justify-end mt-4">
                            <n-button
                                type="primary"
                                secondary
                                :loading="state.linking"
                                :disabled="!selectedCount"
                                @click="linkSelection"
                            >
                                <template #icon><n-icon><CubeOutline /></n-icon></template>
                                Vincular seleccionados ({{ selectedCount }})
                            </n-button>
                        </div>
                    </div>

                    <!-- Archivos ya recopilados en este paso -->
                    <div class="mt-6">
                        <h5 class="text-xs font-bold text-gray-400 uppercase mb-2">
                            Archivos en este documento ({{ currentAttachments.length }})
                        </h5>
                        <div v-if="currentAttachments.length" class="space-y-2">
                            <div
                                v-for="attachment in currentAttachments"
                                :key="attachment.id"
                                class="flex items-center justify-between bg-gray-50 border border-gray-100 rounded-lg px-3 py-2"
                            >
                                <div class="flex items-center gap-2 min-w-0">
                                    <img
                                        v-if="isImage(attachment)"
                                        :src="attachment.url"
                                        class="w-8 h-8 object-cover rounded-md shrink-0"
                                        alt=""
                                    />
                                    <n-icon v-else size="18" class="text-gray-400 shrink-0"><DocumentTextOutline /></n-icon>
                                    <span class="text-sm text-gray-700 truncate" :title="attachment.file_name">{{ attachment.file_name }}</span>
                                    <n-tag size="tiny" :bordered="false" type="info">{{ sourceLabel(attachment.source) }}</n-tag>
                                </div>
                                <n-popconfirm @positive-click="removeAttachment(attachment)" positive-text="Quitar" negative-text="Cancelar">
                                    <template #trigger>
                                        <n-button quaternary circle size="small" type="error">
                                            <template #icon><n-icon><TrashOutline /></n-icon></template>
                                        </n-button>
                                    </template>
                                    ¿Quitar este archivo del paso?
                                </n-popconfirm>
                            </div>
                        </div>
                        <p v-else class="text-xs text-gray-400 italic">
                            Aún no hay archivos en este paso. Puedes dejarlo vacío si no aplica (ej. manuales ya adjuntos en productos).
                        </p>
                    </div>
                </div>
            </div>

            <n-empty v-else description="No hay documentos configurados. Ve a Configuraciones > Documentación de servicio." />
        </n-spin>

        <template #footer>
            <div class="flex justify-between items-center w-full">
                <div class="flex items-center gap-2">
                    <n-button quaternary @click="close">Cancelar</n-button>
                    <n-button :disabled="currentStep === 0" @click="goPrev">
                        <template #icon><n-icon><ArrowBackOutline /></n-icon></template>
                        Anterior
                    </n-button>
                </div>
                <div>
                    <n-button v-if="!isLastStep && steps.length" type="primary" @click="goNext">
                        Siguiente
                        <template #icon><n-icon><ArrowForwardOutline /></n-icon></template>
                    </n-button>
                    <n-button v-else-if="steps.length" type="success" @click="generateExpediente">
                        <template #icon><n-icon><PrintOutline /></n-icon></template>
                        Generar Expediente Completo
                    </n-button>
                </div>
            </div>
        </template>
    </n-modal>
</template>
