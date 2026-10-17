<script setup>
import { computed, ref, onMounted } from 'vue';
import { Head } from '@inertiajs/vue3';
import * as pdfjsLib from 'pdfjs-dist';
import pdfWorkerUrl from 'pdfjs-dist/build/pdf.worker.min.mjs?url';

// El navegador prohíbe crear Workers desde un origen distinto al de la página.
// En dev la página vive en Laravel (8000) y el worker de Vite en 5173 → SecurityError.
// En dev lo servimos desde Laravel (mismo origen) vía la ruta /pdf-worker.mjs;
// en producción se usa el asset que Vite emite en public/build (mismo origen).
pdfjsLib.GlobalWorkerOptions.workerSrc = import.meta.env.DEV
    ? '/pdf-worker.mjs'
    : pdfWorkerUrl;

const props = defineProps({
    order: Object,
    steps: Array,
    single: Boolean,
    generated_at: String,
    generated_by: String,
});

// Solo se imprimen los pasos que tienen archivos (sin hojas vacías)
const printableSteps = computed(() => (props.steps || []).filter((step) => step.attachments?.length));

// Clasifica un archivo para decidir cómo se imprime
const kind = (file) => {
    const mime = (file.mime_type || '').toLowerCase();
    if (mime === 'application/pdf' || /\.pdf$/i.test(file.file_name || '')) return 'pdf';
    // HEIC/HEIF (fotos de iPhone) no se pueden previsualizar en el navegador
    if (mime.startsWith('image/') && !/heic|heif/i.test(mime)) return 'image';
    return 'other';
};

// Convierte una URL absoluta (APP_URL/storage/...) a ruta relativa del mismo origen.
// Así las imágenes y PDFs se cargan siempre desde el dominio actual (evita CORS).
const toRelative = (url) => {
    if (!url) return '';
    return url.replace(/^https?:\/\/[^/]+/i, '') || url;
};

// Estado de renderizado de cada PDF: attachmentId -> { status: 'loading'|'ready'|'error', pages: [] }
const pdfState = ref({});

// Tamaño natural de las imágenes (para decidir el acomodo en la hoja única)
const imageSizes = ref({});

const pdfAttachments = computed(() =>
    printableSteps.value
        .flatMap((step) => step.attachments)
        .filter((file) => kind(file) === 'pdf')
);

const renderPdf = async (attachment) => {
    const key = attachment.id;
    pdfState.value[key] = { status: 'loading', pages: [] };

    try {
        // pdf.js v6 solo acepta un objeto de parámetros. Pasar la URL como cadena
        // directa lanza "getDocument - expected either `data`, `range`, or `url` parameter"
        // y los PDFs (carta poder, cambio de nombre, etc.) no se previsualizan.
        const pdf = await pdfjsLib.getDocument({ url: toRelative(attachment.url) }).promise;
        const pages = [];

        for (let i = 1; i <= pdf.numPages; i++) {
            const page = await pdf.getPage(i);
            const base = page.getViewport({ scale: 1 });
            // Escala para ~1600px de ancho: nítido al imprimir, sin reventar memoria
            const scale = Math.min(3, 1600 / base.width);
            const viewport = page.getViewport({ scale });

            const canvas = document.createElement('canvas');
            canvas.width = viewport.width;
            canvas.height = viewport.height;
            const ctx = canvas.getContext('2d');
            await page.render({ canvasContext: ctx, viewport }).promise;

            pages.push({
                dataUrl: canvas.toDataURL('image/jpeg', 0.92),
                width: viewport.width,
                height: viewport.height,
            });
        }

        pdfState.value[key] = { status: 'ready', pages };
    } catch (error) {
        console.error('No se pudo renderizar el PDF', attachment.file_name, error);
        pdfState.value[key] = { status: 'error', pages: [] };
    }
};

const onImageLoad = (attachment, event) => {
    const width = event.target.naturalWidth || 0;
    const height = event.target.naturalHeight || 0;
    const current = imageSizes.value[attachment.id];

    // Sin cambio no se toca el estado: evita re-renderizar la hoja al recargar
    if (current && current.width === width && current.height === height) return;

    imageSizes.value[attachment.id] = { width, height };
};

// Bloques imprimibles de un archivo: una página de PDF, la imagen o el aviso
// de que no se puede previsualizar.
const blocksForAttachment = (attachment) => {
    const fileKind = kind(attachment);

    if (fileKind === 'pdf') {
        const state = pdfState.value[attachment.id];
        if (state?.status === 'ready' && state.pages.length) {
            return state.pages.map((page, index) => ({
                key: `${attachment.id}-p${index}`,
                type: 'image',
                src: page.dataUrl,
                width: page.width,
                height: page.height,
                alt: `${attachment.file_name} — página ${index + 1}`,
                pageNumber: index + 1,
                pageCount: state.pages.length,
            }));
        }

        return [{
            key: `${attachment.id}-placeholder`,
            type: 'placeholder',
            status: state?.status ?? 'loading',
            fileKind: 'PDF',
            message: state?.status === 'error'
                ? 'No se pudo previsualizar este PDF.'
                : 'Cargando páginas del PDF…',
            attachment,
        }];
    }

    if (fileKind === 'image') {
        const size = imageSizes.value[attachment.id] || {};

        return [{
            key: `${attachment.id}-img`,
            type: 'image',
            src: toRelative(attachment.url),
            width: size.width || 1,
            height: size.height || 1,
            alt: attachment.file_name,
            attachment,
        }];
    }

    return [{
        key: `${attachment.id}-other`,
        type: 'placeholder',
        status: 'other',
        fileKind: 'Archivo adjunto',
        message: 'Este tipo de archivo no puede previsualizarse aquí.',
        attachment,
    }];
};

// ¿Los bloques son horizontales? Se usa para el acomodo automático: una imagen
// apaisada no debe ir en dos columnas porque quedaría diminuta.
const isLandscape = (block) => block.type === 'image' && block.width > block.height;

const blocksLayout = (blocks) => {
    if (blocks.length < 2) return 'single';

    return isLandscape(blocks[0]) ? 'stacked' : 'side_by_side';
};

// Hojas del expediente: por defecto una hoja por bloque; con la opción
// "Imprimir en una sola hoja" se juntan de dos en dos (frente y reverso).
const sheets = computed(() => {
    const result = [];

    printableSteps.value.forEach((step, stepIndex) => {
        const blocks = step.attachments.flatMap((attachment) => blocksForAttachment(attachment));

        if (!step.single_sheet) {
            blocks.forEach((block) => result.push({
                key: `${step.id}-${block.key}`,
                step,
                stepIndex,
                blocks: [block],
                layout: 'single',
            }));

            return;
        }

        for (let i = 0; i < blocks.length; i += 2) {
            const group = blocks.slice(i, i + 2);

            result.push({
                key: `${step.id}-${group[0].key}`,
                step,
                stepIndex,
                blocks: group,
                layout: step.sheet_layout === 'auto' ? blocksLayout(group) : step.sheet_layout,
            });
        }
    });

    return result;
});

const sheetFileNames = (sheet) => [
    ...new Set(sheet.blocks.map((block) => block.attachment?.file_name).filter(Boolean)),
];

onMounted(() => {
    pdfAttachments.value.forEach(renderPdf);
});

// Mientras haya PDFs renderizándose conviene esperar: si se imprime antes,
// el expediente saldría con hojas de "Cargando páginas del PDF…".
const isRendering = computed(() =>
    pdfAttachments.value.some((file) => (pdfState.value[file.id]?.status ?? 'loading') === 'loading')
);

const print = () => window.print();
</script>

<template>
    <Head :title="`Documentación Orden #${order.id}`" />
    <div class="print-wrapper bg-gray-100 min-h-screen p-6 flex flex-col items-center gap-6 print:p-0 print:block">

        <template v-for="sheet in sheets" :key="sheet.key">
            <div class="sheet-page">
                <!-- ENCABEZADO DE HOJA (paso + archivo): solo visible en pantalla; al imprimir se oculta -->
                <div class="sheet-caption">
                    <span class="step-label">{{ sheet.stepIndex + 1 }}. {{ sheet.step.title }}</span>
                    <span v-for="name in sheetFileNames(sheet)" :key="name" class="file-label">{{ name }}</span>
                    <span v-if="sheet.blocks.length === 1 && sheet.blocks[0].pageCount" class="page-label">
                        Página {{ sheet.blocks[0].pageNumber }} de {{ sheet.blocks[0].pageCount }}
                    </span>
                </div>

                <div class="sheet-blocks" :class="`layout-${sheet.layout}`">
                    <div v-for="block in sheet.blocks" :key="block.key" class="sheet-image-wrap">
                        <!-- PÁGINA DE PDF O IMAGEN -->
                        <img
                            v-if="block.type === 'image'"
                            class="sheet-image"
                            :src="block.src"
                            :alt="block.alt"
                            @load="block.attachment && onImageLoad(block.attachment, $event)"
                        />

                        <!-- ARCHIVO QUE NO SE PUEDE PREVISUALIZAR -->
                        <div v-else class="sheet-placeholder">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                            </svg>
                            <span class="file-type-label">{{ block.fileKind }}</span>
                            <span v-if="block.status === 'error'" class="placeholder-note">
                                {{ block.message }}
                                <a :href="toRelative(block.attachment.url)" target="_blank" class="placeholder-link">Ábrelo en una pestaña nueva</a>
                            </span>
                            <span v-else class="placeholder-note">{{ block.message }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </template>

        <p v-if="!printableSteps.length" class="empty-note">
            No hay archivos recopilados para esta orden de servicio.
        </p>

        <!-- BOTÓN FLOTANTE (NO IMPRIMIBLE) -->
        <div class="fixed bottom-8 right-8 print:hidden">
            <button
                @click="print"
                :disabled="isRendering"
                class="bg-gray-900 text-white px-5 py-3 rounded-full shadow-lg hover:bg-gray-800 transition-colors flex items-center gap-2 font-bold text-sm disabled:opacity-60 disabled:cursor-not-allowed"
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M5 4v3H4a2 2 0 00-2 2v3a2 2 0 002 2h1v2a2 2 0 002 2h6a2 2 0 002-2v-2h1a2 2 0 002-2V9a2 2 0 00-2-2h-1V4a2 2 0 00-2-2H7a2 2 0 00-2 2zm8 0H7v3h6V4zm0 8H7v4h6v-4z" clip-rule="evenodd" />
                </svg>
                {{ isRendering ? 'Preparando documentos…' : 'Imprimir / Guardar como PDF' }}
            </button>
        </div>
    </div>
</template>

<style>
/* Hoja carta: una hoja completa por imagen o por página de PDF */
.sheet-page {
    width: 216mm;
    min-height: 279mm;
    box-sizing: border-box;
    background: #fff;
    border: 1px solid #e5e7eb;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
    page-break-after: always;
    break-after: page;
    display: flex;
    flex-direction: column;
    padding: 10mm;
}

.sheet-page:last-child {
    page-break-after: auto;
    break-after: auto;
}

/* ENCABEZADO DE HOJA (título del paso + archivo): guía visual en pantalla.
   Al imprimir se oculta desde @media print para que la hoja salga limpia. */
.sheet-caption {
    flex: 0 0 auto;
    display: flex;
    align-items: baseline;
    gap: 0.75rem;
    flex-wrap: wrap;
    padding-bottom: 3mm;
    margin-bottom: 4mm;
    border-bottom: 1px solid #d1d5db;
}

.step-label {
    font-size: 12px;
    font-weight: 700;
    color: #111827;
}

.file-label {
    font-size: 10px;
    color: #6b7280;
    word-break: break-all;
}

.page-label {
    font-size: 10px;
    color: #6b7280;
    margin-left: auto;
}

/* BLOQUES DE LA HOJA: 1 por hoja (comportamiento normal) o 2 cuando el
   documento tiene activado "Imprimir en una sola hoja" (frente y reverso). */
.sheet-blocks {
    flex: 1 1 auto;
    min-height: 0;
    display: grid;
    gap: 5mm;
}

.layout-single {
    grid-template: 1fr / 1fr;
}

.layout-stacked {
    grid-template: 1fr 1fr / 1fr;
}

.layout-side_by_side {
    grid-template: 1fr / 1fr 1fr;
}

.sheet-image-wrap {
    min-height: 0;
    position: relative;
}

/* Al juntar dos bloques conviene ver el límite de cada archivo en pantalla */
.layout-stacked > .sheet-image-wrap + .sheet-image-wrap,
.layout-side_by_side > .sheet-image-wrap + .sheet-image-wrap {
    border-top: 1px dashed #d1d5db;
    padding-top: 5mm;
}

.layout-side_by_side > .sheet-image-wrap + .sheet-image-wrap {
    border-top: none;
    border-left: 1px dashed #d1d5db;
    padding-top: 0;
    padding-left: 5mm;
}

.sheet-image {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    object-fit: contain;
    background: #f9fafb;
    display: block;
}

.sheet-placeholder {
    position: absolute;
    inset: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    border: 1px dashed #d1d5db;
    border-radius: 8px;
    color: #9ca3af;
}

.file-type-label {
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.placeholder-note {
    font-size: 11px;
    color: #9ca3af;
    text-align: center;
    padding: 0 1rem;
}

.placeholder-link {
    color: #4f46e5;
    font-weight: 600;
    text-decoration: underline;
}

.empty-note {
    font-size: 12px;
    font-style: italic;
    color: #9ca3af;
    border: 1px dashed #d1d5db;
    border-radius: 8px;
    padding: 2rem;
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

    .sheet-page {
        width: 100%;
        min-height: 279mm;
        border: none;
        box-shadow: none;
        padding: 10mm 12mm !important;
        margin: 0;
    }

    /* El encabezado de cada hoja (número de paso, título y archivo) es solo una
       guía en pantalla: al imprimir se oculta para que la hoja salga limpia. */
    .sheet-caption {
        display: none !important;
    }

    /* La línea divisoria entre los dos archivos de una hoja también es solo
       una guía en pantalla. */
    .layout-stacked > .sheet-image-wrap + .sheet-image-wrap,
    .layout-side_by_side > .sheet-image-wrap + .sheet-image-wrap {
        border: none !important;
    }

    .sheet-image {
        background: #fff;
    }

    .print\:hidden {
        display: none !important;
    }
}
</style>
