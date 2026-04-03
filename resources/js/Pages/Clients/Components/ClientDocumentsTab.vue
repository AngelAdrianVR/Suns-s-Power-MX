<script setup>
import { ref, h } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import { usePermissions } from '@/Composables/usePermissions';
import { useSecureFile } from '@/Composables/useSecureFile';
import { NButton, NIcon, NTag, NDataTable, NTooltip, createDiscreteApi } from 'naive-ui';
import { CloudDownloadOutline, TrashOutline, CloudUploadOutline } from '@vicons/ionicons5';

const props = defineProps({
    client: Object
});

const { hasPermission } = usePermissions();
const { openFileWithRetry } = useSecureFile();
const { dialog, notification } = createDiscreteApi(['dialog', 'notification']);
const fileInput = ref(null);

const formatDate = (dateString) => {
    if (!dateString) return '-';
    return new Date(dateString).toLocaleDateString('es-MX', { year: 'numeric', month: 'short', day: 'numeric' });
};

const docColumns = [
    { 
        title: 'Nombre', 
        key: 'name', 
        render: (row) => h('a', { 
            class: 'font-medium text-indigo-600 hover:underline text-xs sm:text-sm cursor-pointer',
            // Integración de useSecureFile en el nombre clickeable
            onClick: (e) => {
                e.preventDefault();
                openFileWithRetry(row.url);
            }
        }, row.name) 
    },
    { title: 'Categoría', key: 'category', render: (row) => h(NTag, { size: 'tiny' }, { default: () => row.category }) },
    { title: 'Fecha', key: 'created_at', render: (row) => formatDate(row.created_at) },
    {
        title: '',
        key: 'actions',
        align: 'right',
        render(row) {
            const buttons = [];
            buttons.push(h(NTooltip, { trigger: 'hover' }, {
                trigger: () => h(NButton, { 
                    circle: true, size: 'small', quaternary: true, type: 'info', tag: 'a',
                    href: row.url, target: '_blank', download: row.name, class: 'mr-1',
                    onClick: (e) => e.stopPropagation()
                }, { icon: () => h(NIcon, null, { default: () => h(CloudDownloadOutline) }) }),
                default: () => 'Descargar Directamente'
            }));

            if (hasPermission('clients.edit')) {
                buttons.push(h(NTooltip, { trigger: 'hover' }, {
                    trigger: () => h(NButton, { 
                        circle: true, size: 'small', quaternary: true, type: 'error',
                        onClick: (e) => {
                            e.stopPropagation();
                            dialog.warning({
                                title: 'Eliminar archivo',
                                content: '¿Estás seguro de que deseas eliminar este archivo? Esta acción no se puede deshacer.',
                                positiveText: 'Eliminar',
                                negativeText: 'Cancelar',
                                onPositiveClick: () => {
                                    router.delete(route('media.delete-file', row.id), {
                                        preserveScroll: true,
                                        onSuccess: () => notification.success({ title: 'Éxito', content: 'Archivo eliminado correctamente.', duration: 3000 }),
                                    });
                                }
                            });
                        }
                    }, { icon: () => h(NIcon, null, { default: () => h(TrashOutline) }) }),
                    default: () => 'Eliminar'
                }));
            }
            return h('div', { class: 'flex items-center justify-end' }, buttons);
        }
    }
];

const docRowProps = (row) => {
    return {
        style: 'cursor: pointer;',
        // Integración de useSecureFile en la fila entera
        onClick: (e) => {
            if (e.target.tagName === 'A' || e.target.closest('button') || e.target.closest('a')) return;
            openFileWithRetry(row.url);
        }
    }
}

const uploadForm = useForm({
    files: [],
    category: 'General'
});

const triggerFileInput = () => {
    fileInput.value.click();
};

const handleFileChange = (event) => {
    const files = Array.from(event.target.files);
    if (files.length === 0) return;

    uploadForm.files = files;
    uploadForm.post(route('clients.documents.store', props.client.id), {
        preserveScroll: true,
        onSuccess: () => {
            notification.success({ title: 'Éxito', content: 'Documentos subidos correctamente.', duration: 3000 });
            // Recargar página completamente tras subida exitosa
            window.location.reload();
        },
        onError: () => notification.error({ title: 'Error', content: 'Hubo un problema al subir los documentos.', duration: 3000 })
    });
};
</script>

<template>
    <div>
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-4 gap-3">
            <div>
                <h3 class="text-base sm:text-lg font-bold text-gray-800">Expediente</h3>
            </div>
        </div>

        <input type="file" ref="fileInput" class="hidden" multiple @change="handleFileChange">

        <div 
            v-if="hasPermission('clients.edit')" 
            @click="triggerFileInput"
            class="border-2 border-dashed border-gray-200 rounded-xl p-6 text-center mb-6 hover:bg-indigo-50 hover:border-indigo-300 transition-all cursor-pointer group relative"
        >
            <div v-if="uploadForm.processing" class="absolute inset-0 bg-white/80 flex items-center justify-center z-10 rounded-xl">
                <span class="text-indigo-600 font-bold animate-pulse">Subiendo...</span>
            </div>
            <div class="bg-blue-50 w-10 h-10 rounded-full flex items-center justify-center mx-auto mb-2 group-hover:scale-110 transition-transform">
                <n-icon size="20" class="text-blue-500"><CloudUploadOutline /></n-icon>
            </div>
            <h4 class="font-bold text-gray-700 text-sm">Subir documentos</h4>
            <p class="text-gray-400 text-[10px] mt-1">Clic para explorar (soporta múltiples archivos)</p>
        </div>

        <div class="-mx-4 px-4 sm:mx-0 sm:px-0 overflow-x-auto">
            <div class="min-w-[450px] sm:min-w-full">
                <n-data-table
                    :columns="docColumns"
                    :data="client.documents"
                    :bordered="false"
                    size="small"
                    :row-props="docRowProps"
                    class="cursor-pointer"
                />
            </div>
        </div>
    </div>
</template>