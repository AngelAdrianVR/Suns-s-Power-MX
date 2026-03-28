<script setup>
import { ref } from 'vue';
import { usePermissions } from '@/Composables/usePermissions';
import { useSecureFile } from '@/Composables/useSecureFile';
import { useForm, router } from '@inertiajs/vue3';
import { 
    NButton, NIcon, NPopconfirm, NImage, 
    NSpin, NEmpty, createDiscreteApi 
} from 'naive-ui';
import { 
    CloudUploadOutline, DocumentOutline, 
    CloudDownloadOutline, TrashOutline 
} from '@vicons/ionicons5';

const props = defineProps({
    order: Object
});

const { hasPermission } = usePermissions();
const { isOpeningFile, openFileWithRetry } = useSecureFile();
const { notification } = createDiscreteApi(['notification']);

const fileInput = ref(null);

const triggerFileInput = () => {
    fileInput.value.click();
};

const handleFileChange = (event) => {
    const file = event.target.files[0];
    if (!file) return;

    const form = useForm({ file: file });

    form.post(route('service-orders.upload-media', props.order.id), {
        onSuccess: () => {
            notification.success({ title: 'Archivo subido', content: 'Evidencia guardada.', duration: 3000 });
            // Recargar página completamente para garantizar visualización de nuevas imágenes
            window.location.reload();
        },
        onError: () => {
            notification.error({ title: 'Error', content: 'No se pudo subir el archivo.' });
        }
    });
};

const isImage = (file) => {
    if (file.mime_type) {
        return file.mime_type.startsWith('image/');
    }
    return /\.(jpg|jpeg|png|gif|webp)$/i.test(file.file_name);
};
</script>

<template>
    <div class="p-4">
        <!-- Subida de archivos -->
        <div v-if="hasPermission('service_orders.edit')" class="bg-gray-50 border border-dashed border-gray-300 rounded-lg p-6 mb-6 flex flex-col items-center justify-center">
            <input 
                type="file" 
                ref="fileInput" 
                class="hidden" 
                @change="handleFileChange" 
                accept="image/*,application/pdf,.doc,.docx,.xls,.xlsx"
            />
            <n-button dashed type="primary" size="large" @click="triggerFileInput" class="h-20 w-full md:w-1/2">
                <div class="flex flex-col items-center gap-2">
                    <n-icon size="24"><CloudUploadOutline /></n-icon>
                    <span>Seleccionar archivo para subir</span>
                </div>
            </n-button>
            <p class="text-xs text-gray-400 mt-2">Formatos aceptados: Imágenes, PDF y Documentos (Max 10MB)</p>
        </div>

        <div v-if="order.media?.length">
            <h4 class="font-bold text-gray-700 mb-3">Archivos Adjuntos</h4>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div v-for="file in order.media" :key="file.id" class="relative group">
                    
                    <!-- Caso 1: Imagen -->
                    <n-image
                        v-if="isImage(file)"
                        :src="file.original_url"
                        class="rounded-lg shadow-sm border border-gray-200 overflow-hidden w-full h-40 object-cover"
                        object-fit="cover"
                    />

                    <!-- Caso 2: Documento (PDF, etc) -->
                    <div v-else 
                         class="w-full h-40 rounded-lg shadow-sm border border-gray-200 bg-gray-100 flex flex-col items-center justify-center gap-2 p-2 cursor-pointer hover:bg-gray-200 transition-colors"
                         @click="openFileWithRetry(file.original_url)"
                         :class="{'opacity-50 pointer-events-none': isOpeningFile}"
                    >
                        <n-icon v-if="!isOpeningFile" size="40" class="text-gray-400"><DocumentOutline /></n-icon>
                        <n-spin v-else size="medium" />
                        
                        <span class="text-xs text-indigo-600 flex items-center gap-1 font-bold text-center break-all">
                            Abrir Archivo <n-icon v-if="!isOpeningFile"><CloudDownloadOutline/></n-icon>
                        </span>
                    </div>

                    <div class="mt-1 text-xs text-gray-500 truncate">{{ file.file_name }}</div>
                    
                    <!-- Eliminar archivo -->
                    <n-popconfirm 
                        v-if="hasPermission('service_orders.edit')"
                        @positive-click="router.delete(route('media.delete-file', file.id), { preserveScroll: true, onSuccess: () => router.reload({only: ['order']}) })"
                    >
                        <template #trigger>
                            <button class="absolute top-2 right-2 bg-white/90 p-1 rounded-full shadow-sm opacity-0 group-hover:opacity-100 transition-opacity text-red-500 hover:text-red-700 z-10">
                                <n-icon><TrashOutline /></n-icon>
                            </button>
                        </template>
                        ¿Eliminar evidencia?
                    </n-popconfirm>
                </div>
            </div>
        </div>

        <div class="p-8 text-center" v-else>
            <n-empty description="No se han cargado evidencias fotográficas o documentos aún." />
        </div>
    </div>
</template>