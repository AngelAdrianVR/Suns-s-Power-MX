<script setup>
import { ref, computed, watch } from 'vue';
import { usePermissions } from '@/Composables/usePermissions';
import { useSecureFile } from '@/Composables/useSecureFile';
import { useForm, router } from '@inertiajs/vue3';
import { 
    NButton, NIcon, NPopconfirm, NImage, NInput,
    NSpin, NEmpty, createDiscreteApi, NDivider, NTag
} from 'naive-ui';
import { 
    CloudUploadOutline, DocumentOutline, 
    CloudDownloadOutline, TrashOutline, CameraOutline, CheckmarkCircleOutline, AddOutline,
    DocumentTextOutline // <-- NUEVO ICONO
} from '@vicons/ionicons5';

const props = defineProps({
    order: Object
});

const { hasPermission } = usePermissions();
const { isOpeningFile, openFileWithRetry } = useSecureFile();
const { notification } = createDiscreteApi(['notification']);

// --- LÓGICA DE COMENTARIOS ---
const localComments = ref({});
const isSavingComment = ref(null);

// Mantiene sincronizado el estado local con la BD al cargar la página
watch(() => props.order.evidences, (newEvidences) => {
    if (newEvidences) {
        newEvidences.forEach(e => {
            localComments.value[e.id] = e.comment || '';
        });
    }
}, { immediate: true });

const saveComment = (evidenceId) => {
    isSavingComment.value = evidenceId;
    const form = useForm({
        comment: localComments.value[evidenceId] || ''
    });

    form.post(route('service-orders.evidences.upload', evidenceId), {
        preserveScroll: true,
        onSuccess: () => {
            notification.success({ title: 'Comentario Guardado', content: 'Nota de evidencia actualizada.' });
            isSavingComment.value = null;
        },
        onError: () => {
            notification.error({ title: 'Error', content: 'No se pudo guardar la nota.' });
            isSavingComment.value = null;
        }
    });
};

// --- LÓGICA PARA ORDENAR EVIDENCIAS ---
const sortedEvidences = computed(() => {
    if (!props.order.evidences) return [];
    
    // Clonamos para no mutar el prop y ordenamos
    return [...props.order.evidences].sort((a, b) => {
        const orderA = a.order !== undefined && a.order !== null ? a.order : 0;
        const orderB = b.order !== undefined && b.order !== null ? b.order : 0;
        
        if (orderA === orderB) {
            return a.id - b.id;
        }
        return orderA - orderB;
    });
});

// --- LÓGICA EVIDENCIAS ESPECÍFICAS (REQUERIDAS) ---
const triggerEvidenceFileInput = (evidenceId) => {
    document.getElementById(`file-evidence-${evidenceId}`).click();
};

const handleEvidenceFileChange = (event, evidenceId) => {
    const files = event.target.files;
    if (!files || files.length === 0) return;

    // Incluimos el comentario actual al subir fotos por si estaban escribiendo
    const form = useForm({ 
        files: Array.from(files),
        comment: localComments.value[evidenceId] || '' 
    });

    form.post(route('service-orders.evidences.upload', evidenceId), {
        preserveScroll: true,
        onSuccess: () => {
            notification.success({ title: 'Evidencia Completada', content: 'Archivo(s) adjuntado(s) correctamente.' });
            const input = document.getElementById(`file-evidence-${evidenceId}`);
            if (input) input.value = '';
        },
        onError: () => {
            notification.error({ title: 'Error', content: 'No se pudo subir la evidencia.' });
        }
    });
};

// --- LÓGICA ARCHIVOS GENERALES ADICIONALES ---
const fileInput = ref(null);

const triggerFileInput = () => {
    fileInput.value.click();
};

const handleFileChange = (event) => {
    const file = event.target.files[0];
    if (!file) return;

    const form = useForm({ file: file });

    form.post(route('service-orders.upload-media', props.order.id), {
        preserveScroll: true,
        onSuccess: () => {
            notification.success({ title: 'Archivo Extra', content: 'Evidencia extra guardada.' });
            if (fileInput.value) fileInput.value.value = '';
        },
        onError: () => notification.error({ title: 'Error', content: 'No se pudo subir.' })
    });
};

const isImage = (file) => {
    if (file.mime_type) return file.mime_type.startsWith('image/');
    return /\.(jpg|jpeg|png|gif|webp)$/i.test(file.file_name);
};
</script>

<template>
    <div class="p-4 space-y-8">
        
        <!-- SECCIÓN 1: EVIDENCIAS REQUERIDAS (GENERADAS POR EL SISTEMA) -->
        <div v-if="order.evidences?.length">
            <h3 class="text-lg font-bold text-gray-800 flex items-center gap-2 mb-4">
                <n-icon class="text-emerald-500"><CheckmarkCircleOutline/></n-icon> Evidencias Requeridas (Checklist)
            </h3>
            
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
                <div v-for="evidence in sortedEvidences" :key="evidence.id" 
                     class="border rounded-2xl overflow-hidden shadow-sm hover:shadow transition-shadow flex flex-col justify-between"
                     :class="evidence.media?.length ? 'bg-emerald-50/30 border-emerald-100' : 'bg-white border-gray-200'">
                    
                    <!-- Header Evidencia -->
                    <div class="p-4 border-b border-gray-100">
                        <div class="flex justify-between items-start mb-2">
                            <h4 class="font-bold text-gray-800">{{ evidence.title }}</h4>
                            <n-tag v-if="evidence.media?.length" type="success" size="small" round>Completada</n-tag>
                            <n-tag v-else type="warning" size="small" round>Pendiente</n-tag>
                        </div>
                        <p class="text-xs text-gray-500">{{ evidence.description }}</p>
                    </div>

                    <!-- Visualizador o Botón de Carga -->
                    <div class="p-4 bg-gray-50/50 flex-1 flex flex-col justify-between">
                        <div>
                            <!-- Si ya se subieron archivos -->
                            <div v-if="evidence.media?.length" class="space-y-3">
                                <div class="grid grid-cols-2 gap-2">
                                    <div v-for="media in evidence.media" :key="media.id" class="relative group">
                                        <n-image
                                            v-if="isImage(media)"
                                            :src="media.original_url"
                                            class="rounded-lg shadow-sm border border-gray-200 w-full h-24 object-cover"
                                            object-fit="cover"
                                        />
                                        <div v-else 
                                             class="w-full h-24 rounded-lg shadow-sm border border-gray-200 bg-white flex flex-col items-center justify-center cursor-pointer hover:bg-gray-50"
                                             @click="openFileWithRetry(media.original_url)"
                                        >
                                            <n-icon size="24" class="text-emerald-400 mb-1"><DocumentOutline /></n-icon>
                                            <span class="text-[10px] text-emerald-600 font-bold">Ver Documento</span>
                                        </div>
                                        
                                        <n-popconfirm v-if="hasPermission('service_orders.edit')" @positive-click="router.delete(route('media.delete-file', media.id), { preserveScroll: true })">
                                            <template #trigger>
                                                <button class="absolute top-1 right-1 bg-white p-1 rounded-full shadow opacity-0 group-hover:opacity-100 transition-opacity text-red-500 hover:text-red-700 z-10">
                                                    <n-icon size="14"><TrashOutline /></n-icon>
                                                </button>
                                            </template>
                                            ¿Eliminar este archivo?
                                        </n-popconfirm>
                                    </div>
                                </div>
                                
                                <div class="mt-2">
                                    <input type="file" :id="'file-evidence-'+evidence.id" class="hidden" multiple @change="e => handleEvidenceFileChange(e, evidence.id)" accept="image/*,application/pdf" />
                                    <n-button dashed size="small" type="primary" class="w-full bg-white text-emerald-600 hover:text-emerald-700" @click="triggerEvidenceFileInput(evidence.id)">
                                        <template #icon><n-icon><AddOutline /></n-icon></template>
                                        Agregar Más Archivos
                                    </n-button>
                                </div>
                            </div>

                            <!-- Si no se ha subido NADA, mostrar input principal -->
                            <div v-else>
                                <input type="file" :id="'file-evidence-'+evidence.id" class="hidden" multiple @change="e => handleEvidenceFileChange(e, evidence.id)" accept="image/*,application/pdf" />
                                <n-button dashed type="primary" class="w-full h-16 bg-white" @click="triggerEvidenceFileInput(evidence.id)">
                                    <div class="flex flex-col items-center gap-1 text-emerald-600">
                                        <n-icon size="20"><CameraOutline /></n-icon>
                                        <span class="text-xs font-semibold">Subir Fotografía(s)</span>
                                    </div>
                                </n-button>
                            </div>
                        </div>

                        <!-- SECCIÓN NUEVA: COMENTARIOS INDIVIDUALES -->
                        <div class="mt-4 border-t border-gray-200 pt-3">
                            <span class="text-xs font-semibold text-gray-500 mb-2 flex items-center gap-1">
                                <n-icon><DocumentTextOutline /></n-icon> Notas / Comentarios
                            </span>
                            <div class="flex flex-col gap-2">
                                <n-input
                                    v-model:value="localComments[evidence.id]"
                                    type="textarea"
                                    placeholder="Observaciones de esta evidencia..."
                                    size="small"
                                    :autosize="{ minRows: 1, maxRows: 3 }"
                                />
                                <!-- Botón que solo aparece si el comentario cambió y no se ha guardado -->
                                <div class="flex justify-end min-h-[24px]">
                                    <n-button
                                        v-if="localComments[evidence.id] !== (evidence.comment || '')"
                                        size="tiny"
                                        type="primary"
                                        @click="saveComment(evidence.id)"
                                        :loading="isSavingComment === evidence.id"
                                    >
                                        Guardar Nota
                                    </n-button>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
            <n-divider />
        </div>

        <!-- SECCIÓN 2: ARCHIVOS Y FOTOS EXTRAS (GENERALES) -->
        <div>
            <h3 class="text-lg font-bold text-gray-800 flex items-center gap-2 mb-4">
                <n-icon class="text-indigo-500"><CloudUploadOutline/></n-icon> Archivos y Fotos Adicionales
            </h3>

            <div v-if="hasPermission('service_orders.edit')" class="bg-gray-50 border border-dashed border-gray-300 rounded-lg p-6 mb-6 flex flex-col items-center justify-center">
                <input type="file" ref="fileInput" class="hidden" @change="handleFileChange" accept="image/*,application/pdf,.doc,.docx,.xls,.xlsx"/>
                <n-button dashed type="primary" size="large" @click="triggerFileInput" class="h-16 w-full md:w-1/3">
                    Subir Archivo Extra
                </n-button>
            </div>

            <div v-if="order.media?.length">
                <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-5 gap-4">
                    <div v-for="file in order.media" :key="file.id" class="relative group">
                        <n-image v-if="isImage(file)" :src="file.original_url" class="rounded-lg shadow-sm border border-gray-200 overflow-hidden w-full h-32 object-cover" object-fit="cover"/>
                        <div v-else class="w-full h-32 rounded-lg shadow-sm border border-gray-200 bg-gray-100 flex flex-col items-center justify-center cursor-pointer hover:bg-gray-200 transition-colors" @click="openFileWithRetry(file.original_url)" :class="{'opacity-50 pointer-events-none': isOpeningFile}">
                            <n-icon v-if="!isOpeningFile" size="30" class="text-gray-400 mb-2"><DocumentOutline /></n-icon>
                            <n-spin v-else size="small" />
                            <span class="text-xs text-indigo-600 font-bold">Ver Documento</span>
                        </div>
                        <n-popconfirm v-if="hasPermission('service_orders.edit')" @positive-click="router.delete(route('media.delete-file', file.id), { preserveScroll: true })">
                            <template #trigger>
                                <button class="absolute top-2 right-2 bg-white/90 p-1 rounded-full shadow-sm opacity-0 group-hover:opacity-100 transition-opacity text-red-500 hover:text-red-700 z-10">
                                    <n-icon><TrashOutline /></n-icon>
                                </button>
                            </template>
                            ¿Eliminar evidencia extra?
                        </n-popconfirm>
                    </div>
                </div>
            </div>
            <div v-else class="p-4 text-center">
                <n-empty description="Sin archivos adicionales." />
            </div>
        </div>

    </div>
</template>