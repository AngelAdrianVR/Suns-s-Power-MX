<script setup>
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';

const props = defineProps({
    order: Object,
    steps: Array,
    single: Boolean,
    generated_at: String,
    generated_by: String,
});

// Solo se imprimen los pasos que tienen archivos (sin hojas vacías)
const printableSteps = computed(() => (props.steps || []).filter((step) => step.attachments?.length));

const isImage = (file) => {
    if (file.mime_type) {
        // HEIC/HEIF (fotos de iPhone) no se pueden previsualizar en el navegador
        if (/heic|heif/i.test(file.mime_type)) return false;
        return file.mime_type.startsWith('image/');
    }
    return /\.(jpg|jpeg|png|gif|webp)$/i.test(file.file_name || '');
};

const print = () => window.print();
</script>

<template>
    <Head :title="`Documentación Orden #${order.id}`" />
    <div class="print-wrapper bg-gray-100 min-h-screen p-6 flex flex-col items-center gap-6">

        <!-- Cada paso con archivos en una hoja -->
        <section
            v-for="(step, index) in printableSteps"
            :key="step.id"
            class="step-page bg-white p-8 print:shadow-none print:p-0"
        >
            <h2 class="step-title">
                {{ index + 1 }}. {{ step.title }}
            </h2>

            <div v-if="step.attachments.length" class="files-grid">
                <figure
                    v-for="attachment in step.attachments"
                    :key="attachment.id"
                    class="file-card"
                >
                    <img
                        v-if="isImage(attachment)"
                        :src="attachment.url"
                        class="file-image"
                        :alt="attachment.file_name"
                    />
                    <div v-else class="file-fallback">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                        </svg>
                        <span class="file-type-label">Archivo adjunto</span>
                    </div>
                    <figcaption class="file-name">{{ attachment.file_name }}</figcaption>
                </figure>
            </div>

            <p v-else class="empty-note">Sin archivos para este documento.</p>
        </section>

        <p v-if="!printableSteps.length" class="text-sm text-gray-500 py-12">
            No hay archivos recopilados para esta orden de servicio.
        </p>

        <!-- BOTÓN FLOTANTE (NO IMPRIMIBLE) -->
        <div class="fixed bottom-8 right-8 print:hidden">
            <button
                @click="print"
                class="bg-gray-900 text-white px-5 py-3 rounded-full shadow-lg hover:bg-gray-800 transition-colors flex items-center gap-2 font-bold text-sm"
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M5 4v3H4a2 2 0 00-2 2v3a2 2 0 002 2h1v2a2 2 0 002 2h6a2 2 0 002-2v-2h1a2 2 0 002-2V9a2 2 0 00-2-2h-1V4a2 2 0 00-2-2H7a2 2 0 00-2 2zm8 0H7v3h6V4zm0 8H7v4h6v-4z" clip-rule="evenodd" />
                </svg>
                Imprimir / Guardar como PDF
            </button>
        </div>
    </div>
</template>

<style>
/* Hoja carta por paso */
.step-page {
    width: 216mm;
    min-height: 279mm;
    box-sizing: border-box;
    border: 1px solid #e5e7eb;
    page-break-after: always;
    break-after: page;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: flex-start;
    gap: 1.5rem;
}

.step-page:last-child {
    page-break-after: auto;
    break-after: auto;
}

.step-title {
    align-self: flex-start;
    font-size: 1.1rem;
    font-weight: 700;
    color: #111827;
    border-bottom: 2px solid #d1d5db;
    padding-bottom: 0.5rem;
    width: 100%;
}

.files-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 1rem;
    width: 100%;
}

.file-card {
    margin: 0;
    break-inside: avoid;
    page-break-inside: avoid;
}

.file-image {
    width: 100%;
    max-height: 160mm;
    object-fit: contain;
    background: #f9fafb;
    border: 1px solid #e5e7eb;
    border-radius: 6px;
    display: block;
    margin: 0 auto;
}

.file-fallback {
    width: 100%;
    min-height: 60mm;
    background: #f9fafb;
    border: 1px solid #e5e7eb;
    border-radius: 6px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
}

.file-type-label {
    font-size: 10px;
    font-weight: 700;
    color: #9ca3af;
    text-transform: uppercase;
}

.file-name {
    margin-top: 0.5rem;
    font-size: 11px;
    color: #374151;
    text-align: center;
    word-break: break-all;
}

.empty-note {
    font-size: 12px;
    font-style: italic;
    color: #9ca3af;
    border: 1px dashed #d1d5db;
    border-radius: 8px;
    padding: 2rem;
    width: 100%;
    text-align: center;
}

@media print {
    @page {
        size: letter;
        margin: 0;
    }

    body {
        background: white;
    }

    .print-wrapper {
        padding: 0;
        background: white;
        display: block;
    }

    .step-page {
        width: 100%;
        min-height: auto;
        border: none;
        box-shadow: none;
        padding: 18mm 16mm !important;
        margin: 0;
    }

    .print\:hidden {
        display: none !important;
    }
}
</style>
