<script setup>
import { computed, ref, onMounted } from 'vue';
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { NSelect, createDiscreteApi } from 'naive-ui';

const props = defineProps({
    order: Object,
    fields: Object,
    users: Array,
    linked: Boolean,
    generated_at: String,
});

const { notification } = createDiscreteApi(['notification']);

const isLinking = ref(false);
const linked = ref(props.linked);

const ROLES = ['apoderado', 'testigo1', 'testigo2'];
const ROLE_LABELS = { apoderado: 'Apoderado', testigo1: 'Testigo 1', testigo2: 'Testigo 2' };

// Estado local editable
const fields = ref({ ...(props.fields || {}) });
const snapshotRef = ref(JSON.stringify(props.fields || {}));

const selections = ref({ apoderado: null, testigo1: null, testigo2: null });
const ineSelections = ref({ apoderado: null, testigo1: null, testigo2: null });

const hasDirty = computed(() => JSON.stringify(fields.value) !== snapshotRef.value);

const updateSnapshot = () => {
    snapshotRef.value = JSON.stringify(fields.value);
};

const userOptions = computed(() =>
    (props.users || []).map((u) => ({ label: u.name, value: u.id }))
);

const userById = (id) => (props.users || []).find((u) => u.id === id);

const selectedUser = (role) => userById(selections.value[role]);

const selectedIneMedia = (role) => {
    const user = selectedUser(role);
    if (!user) return null;
    return user.media.find((m) => m.id === ineSelections.value[role]) || null;
};

// Al elegir una persona se llenan los datos que se puedan
const selectPerson = (role, userId) => {
    selections.value[role] = userId || null;

    if (!userId) {
        fields.value[`${role}_nombre`] = '';
        fields.value[`${role}_ine`] = '';
        ineSelections.value[role] = null;
        return;
    }

    const user = userById(userId);
    if (user) {
        fields.value[`${role}_nombre`] = user.name || '';
        fields.value[`${role}_ine`] = user.ine_number || '';
    }

    // Si tiene un solo archivo, se preselecciona como INE
    ineSelections.value[role] = user && user.media.length === 1 ? user.media[0].id : null;
};

const selectIne = (role, mediaId) => {
    ineSelections.value[role] = mediaId;
};

const inePages = computed(() =>
    ROLES.map((role) => ({
        role,
        person: fields.value[`${role}_nombre`] || 'Sin seleccionar',
        media: selectedIneMedia(role),
    })).filter((page) => page.media)
);

// --- Auto-ajuste de ancho de los campos editables ---
const autoResize = (el) => {
    if (!el) return;
    el.style.width = '';
    const min = parseFloat(getComputedStyle(el).minWidth) || 40;
    el.style.width = `${Math.max(el.scrollWidth + 4, min)}px`;
};

const onFieldInput = (event) => {
    if (event.target && event.target.classList.contains('field-input')) {
        autoResize(event.target);
    }
};

onMounted(() => {
    document.querySelectorAll('.field-input').forEach(autoResize);
});

// Convierte una imagen a JPEG (data URL) usando canvas, para que el servidor
// la pueda incrustar en el PDF sin depender de la extensión GD de PHP.
// Se limita el lado mayor a MAX_INE_EDGE px: en una hoja carta (~190 mm de
// ancho) equivale a más de 250 DPI, y evita PDFs/peticiones innecesariamente
// pesadas (en producción PHP suele tener límites de memoria y de POST).
const IMAGE_MIMES = ['image/png', 'image/jpeg', 'image/webp', 'image/gif', 'image/bmp'];
const MAX_INE_EDGE = 2000;

const mediaToJpegDataUrl = (url, mimeType) => new Promise((resolve) => {
    if (!IMAGE_MIMES.includes(mimeType)) {
        resolve(null);
        return;
    }

    const img = new Image();
    img.onload = () => {
        try {
            const naturalWidth = img.naturalWidth || 1;
            const naturalHeight = img.naturalHeight || 1;
            const factor = Math.min(1, MAX_INE_EDGE / Math.max(naturalWidth, naturalHeight));
            const width = Math.max(1, Math.round(naturalWidth * factor));
            const height = Math.max(1, Math.round(naturalHeight * factor));

            const canvas = document.createElement('canvas');
            canvas.width = width;
            canvas.height = height;
            const ctx = canvas.getContext('2d');
            ctx.fillStyle = '#ffffff';
            ctx.fillRect(0, 0, width, height);
            ctx.drawImage(img, 0, 0, width, height);
            resolve(canvas.toDataURL('image/jpeg', 0.9));
        } catch (error) {
            resolve(null);
        }
    };
    img.onerror = () => resolve(null);
    img.src = url;
});

const linkToOrder = async () => {
    if (isLinking.value) return;
    isLinking.value = true;
    try {
        // Convierte las INE seleccionadas a JPEG en el navegador
        const ineImages = {};
        const notConverted = [];
        for (const role of ROLES) {
            const media = selectedIneMedia(role);
            if (!media) continue;

            const dataUrl = await mediaToJpegDataUrl(media.url, media.mime_type);
            if (dataUrl) {
                ineImages[role] = dataUrl;
            } else {
                notConverted.push(ROLE_LABELS[role]);
            }
        }

        await axios.post(route('service-orders.carta-poder.link', props.order.id), {
            fields: fields.value,
            apoderado_id: selections.value.apoderado,
            testigo1_id: selections.value.testigo1,
            testigo2_id: selections.value.testigo2,
            ine_media: {
                apoderado: ineSelections.value.apoderado,
                testigo1: ineSelections.value.testigo1,
                testigo2: ineSelections.value.testigo2,
            },
            ine_images: ineImages,
        });
        linked.value = true;
        updateSnapshot();

        if (notConverted.length) {
            notification.warning({
                title: 'Algunas INE no se pudieron convertir',
                content: `No se pudo leer ${notConverted.join(', ')} en este navegador (los formatos HEIC de iPhone no son compatibles). Se intentó usar el archivo original; si el PDF no muestra la imagen, vuelve a subir la INE en formato JPG.`,
                duration: 8000
            });
        }

        notification.success({
            title: 'Carta Poder vinculada',
            content: 'El PDF se guardó como documento de la orden (pestaña Evidencias y Documentos).',
            duration: 6000
        });
    } catch (error) {
        notification.error({
            title: 'Error',
            content: 'No se pudo vincular la carta poder a la orden de servicio.',
            duration: 5000
        });
    } finally {
        isLinking.value = false;
    }
};

const print = () => window.print();
</script>

<template>
    <Head :title="`Carta Poder · Orden #${order.id}`" />
    <div class="doc-page" @input="onFieldInput">

        <!-- HERRAMIENTAS DE SELECCIÓN (NO IMPRIMIBLES) -->
        <div class="toolbar print:hidden max-w-4xl mx-auto mb-6 bg-white border border-indigo-100 rounded-2xl p-5 shadow-sm">
            <h3 class="text-sm font-bold text-gray-800 mb-3 flex items-center gap-2">
                Personas que firman la carta
            </h3>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div v-for="role in ROLES" :key="role" class="border border-gray-200 rounded-xl p-3">
                    <p class="text-xs font-bold text-indigo-700 uppercase mb-1.5">{{ ROLE_LABELS[role] }}</p>
                    <n-select
                        :value="selections[role]"
                        :options="userOptions"
                        placeholder="Elegir usuario del sistema"
                        clearable
                        filterable
                        size="small"
                        @update:value="(value) => selectPerson(role, value)"
                    />

                    <div v-if="selectedUser(role)" class="mt-2">
                        <p class="text-[11px] text-gray-500 mb-1">Selecciona la INE (archivo vinculado al usuario):</p>
                        <div v-if="selectedUser(role).media.length" class="space-y-1 max-h-32 overflow-y-auto pr-1">
                            <button
                                v-for="media in selectedUser(role).media"
                                :key="media.id"
                                type="button"
                                class="w-full text-left border rounded-lg px-2 py-1.5 text-[11px] transition-colors"
                                :class="ineSelections[role] === media.id
                                    ? 'border-indigo-400 bg-indigo-50 text-indigo-800 font-semibold'
                                    : 'border-gray-200 bg-white text-gray-600 hover:border-indigo-200'"
                                @click="selectIne(role, media.id)"
                            >
                                {{ media.file_name }}
                            </button>
                        </div>
                        <p v-else class="text-[11px] text-amber-600 mt-1">
                            Este usuario no tiene archivos vinculados. Sube su INE en su expediente.
                        </p>
                    </div>
                </div>
            </div>

            <p class="text-[11px] text-gray-400 mt-3">
                El otorgante es el cliente de la orden. Los nombres y números de INE se pueden editar directamente en la carta.
            </p>
        </div>

        <!-- HOJA 1: CARTA PODER -->
        <div class="doc-sheet legal-font">
            <h1 class="doc-title">CARTA PODER</h1>

            <p class="paragraph">
                Yo <input v-model="fields.otorgante_nombre" class="field-input" style="min-width: 220px;" placeholder="nombre del otorgante" />
                con domicilio en
                <input v-model="fields.otorgante_domicilio" class="field-input" style="min-width: 420px;" placeholder="domicilio" />
                y con identificación oficial
                <input v-model="fields.otorgante_ine" class="field-input" style="min-width: 180px;" placeholder="INE No." />,
                por medio de la presente otorgo poder amplio, cumplido y bastante a favor de
                <input v-model="fields.apoderado_nombre" class="field-input" style="min-width: 240px;" placeholder="nombre del apoderado" />
                quien se identifica con
                <input v-model="fields.apoderado_ine" class="field-input" style="min-width: 180px;" placeholder="INE No." />,
                para que en mi nombre y representación realice las gestiones necesarias, firme y suscriba los contratos
                de interconexión y contraprestación ante la Comisión Federal de Electricidad (CFE), así como cualquier
                documento relacionado con dichos contratos, dando por válidas y legales todas y cada una de las
                gestiones que realice.
            </p>

            <p class="paragraph">
                Manifiesto que la persona antes mencionada ha sido contratada únicamente para realizar los procesos como
                gestor de contratos de interconexión y contraprestación, no excediendo sus honorarios lo establecido en
                el artículo 2556 del CÓDIGO CIVIL FEDERAL.
            </p>

            <p class="paragraph">
                El presente poder se otorga con carácter de especial, limitándose exclusivamente a la facultad descrita
                en el párrafo anterior, el cual tendrá una
                <input v-model="fields.vigencia" class="field-input" style="min-width: 90px;" placeholder="vigencia" />
                contados a partir de la firma del presente.
            </p>

            <p class="fecha-paragraph">
                En la ciudad de
                <input v-model="fields.ciudad" class="field-input" style="min-width: 170px;" placeholder="ciudad" />,
                a los
                <input v-model="fields.dia" class="field-input" style="min-width: 40px;" placeholder="día" />
                días del mes de
                <input v-model="fields.mes" class="field-input" style="min-width: 90px;" placeholder="mes" />
                de
                <input v-model="fields.anio" class="field-input" style="min-width: 70px;" placeholder="año" />.
            </p>

            <!-- FIRMAS OTORGANTE / APODERADO -->
            <div class="sig-grid">
                <div class="sig-cell">
                    <div class="sig-header">OTORGANTE</div>
                    <div class="sig-body">
                        <div class="sig-line"></div>
                        <input v-model="fields.otorgante_nombre" class="field-input sig-name" style="min-width: 180px;" placeholder="nombre" />
                    </div>
                </div>
                <div class="sig-cell">
                    <div class="sig-header">APODERADO</div>
                    <div class="sig-body">
                        <div class="sig-line"></div>
                        <input v-model="fields.apoderado_nombre" class="field-input sig-name" style="min-width: 180px;" placeholder="nombre" />
                    </div>
                </div>
            </div>

            <h2 class="testigos-title">TESTIGOS</h2>
            <hr class="testigos-line">

            <div class="sig-grid">
                <div class="sig-cell">
                    <div class="sig-header">Testigo 1</div>
                    <div class="sig-body">
                        <div class="sig-line"></div>
                        <input v-model="fields.testigo1_nombre" class="field-input sig-name" style="min-width: 180px;" placeholder="nombre" />
                    </div>
                </div>
                <div class="sig-cell">
                    <div class="sig-header">Testigo 2</div>
                    <div class="sig-body">
                        <div class="sig-line"></div>
                        <input v-model="fields.testigo2_nombre" class="field-input sig-name" style="min-width: 180px;" placeholder="nombre" />
                    </div>
                </div>
            </div>
        </div>

        <!-- HOJAS 2-4: INES (UNA HOJA POR PERSONA) -->
        <div v-for="page in inePages" :key="page.role" class="doc-sheet ine-page">
            <div class="ine-caption">
                INE — {{ ROLE_LABELS[page.role] }}: {{ page.person }}
            </div>
            <img :src="page.media.url" class="ine-image" alt="INE" />
        </div>

        <!-- BOTONES FLOTANTES (NO IMPRIMIBLES) -->
        <div class="fixed bottom-8 right-8 print:hidden flex flex-col gap-3 items-end z-50">
            <button
                v-if="!linked"
                @click="linkToOrder"
                :disabled="isLinking"
                class="bg-indigo-600 text-white px-5 py-3 rounded-full shadow-lg hover:bg-indigo-500 transition-colors flex items-center gap-2 font-bold text-sm disabled:opacity-50"
            >
                <span v-if="!isLinking">Vincular a la Orden de Servicio</span>
                <span v-else>Generando PDF...</span>
            </button>
            <button
                v-else-if="hasDirty"
                @click="linkToOrder"
                :disabled="isLinking"
                class="bg-indigo-600 text-white px-5 py-3 rounded-full shadow-lg hover:bg-indigo-500 transition-colors flex items-center gap-2 font-bold text-sm disabled:opacity-50"
            >
                <span v-if="!isLinking">Actualizar PDF vinculado</span>
                <span v-else>Generando PDF...</span>
            </button>
            <div v-else class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-2 rounded-full text-xs font-bold shadow-sm flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                Vinculado a la orden
            </div>
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
.legal-font {
    font-family: 'Times New Roman', Times, serif;
}

.doc-page {
    background: #f3f4f6;
    min-height: 100vh;
    padding: 1rem 0;
}

.doc-sheet {
    width: 216mm;
    min-height: 279mm;
    box-sizing: border-box;
    background: #fff;
    border: 1px solid #e5e7eb;
    margin: 0 auto 1rem;
    padding: 16mm 14mm;
    position: relative;
    color: #000;
    font-size: 14px;
    line-height: 1.6;
    text-align: justify;
    page-break-after: always;
}

.doc-sheet:last-of-type {
    page-break-after: auto;
}

.doc-title {
    text-align: center;
    font-weight: bold;
    font-size: 18px;
    letter-spacing: 4px;
    margin: 0 0 18px;
    text-transform: uppercase;
}

.paragraph {
    margin-bottom: 12px;
}

.fecha-paragraph {
    margin-bottom: 20px;
}

/* Campos editables: negritas subrayadas, se adaptan al contenido */
.field-input {
    border: none;
    outline: none;
    background: transparent;
    font-family: inherit;
    font-size: inherit;
    color: #000;
    font-weight: 700;
    text-decoration: underline;
    padding: 0 2px;
    margin: 0;
    min-width: 40px;
    border-radius: 0;
    box-shadow: none;
}

.field-input:hover,
.field-input:focus {
    background: #fefce8;
    box-shadow: 0 0 0 1px #f59e0b;
    border-radius: 2px;
}

.field-input::placeholder {
    font-weight: 400;
    color: #9ca3af;
}

/* Tablas de firmas */
.sig-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    border: 1px solid #000;
    margin-bottom: 28px;
}

.sig-cell {
    display: flex;
    flex-direction: column;
    border-right: 1px solid #000;
}

.sig-cell:last-child {
    border-right: none;
}

.sig-header {
    text-align: center;
    border-bottom: 1px solid #000;
    padding: 4px;
    font-weight: 600;
    font-size: 13px;
}

.sig-body {
    height: 110px;
    display: flex;
    flex-direction: column;
    justify-content: flex-end;
    align-items: center;
    padding-bottom: 8px;
}

.sig-line {
    width: 80%;
    border-bottom: 1px solid #000;
    margin-bottom: 4px;
}

.sig-name {
    text-align: center;
    font-size: 15px;
}

.testigos-title {
    text-align: center;
    font-size: 24px;
    font-weight: bold;
    color: #3B5A9A;
    letter-spacing: 4px;
    margin: 0 0 4px;
}

.testigos-line {
    border: none;
    border-top: 2px solid #82A2D4;
    margin: 0 0 10px;
}

/* Hojas de INE */
.ine-page {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
}

.ine-caption {
    font-size: 13px;
    color: #374151;
    margin-bottom: 10px;
    font-family: ui-sans-serif, system-ui, sans-serif;
}

.ine-image {
    max-width: 176mm;
    max-height: 238mm;
    object-fit: contain;
    display: block;
    margin: 0 auto;
}

@media print {
    @page {
        size: letter;
        margin: 14mm 12mm;
    }

    body { background: #fff !important; }

    .doc-page { padding: 0; background: #fff; }

    .doc-sheet {
        width: auto !important;
        min-height: auto;
        border: none;
        margin: 20px;
        padding: 0;
    }

    .ine-page { min-height: auto; }

    .field-input {
        border: none !important;
        background: transparent !important;
        box-shadow: none !important;
    }

    .print\:hidden { display: none !important; }
}
</style>
