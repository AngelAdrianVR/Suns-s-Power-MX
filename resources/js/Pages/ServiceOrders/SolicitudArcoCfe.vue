<script setup>
import { computed, ref, onMounted } from 'vue';
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { createDiscreteApi } from 'naive-ui';

const props = defineProps({
    order: Object,
    fields: Object,
    linked: Boolean,
    generated_at: String,
});

const { notification } = createDiscreteApi(['notification']);

const isLinking = ref(false);
const linked = ref(props.linked);

// Estado local editable (todo lo que va entre corchetes en la carta)
const fields = ref({ ...(props.fields || {}) });
const snapshotRef = ref(JSON.stringify(props.fields || {}));

const hasDirty = computed(() => JSON.stringify(fields.value) !== snapshotRef.value);

const updateSnapshot = () => {
    snapshotRef.value = JSON.stringify(fields.value);
};

// --- Auto-ajuste de ancho de los campos editables ---
// Cada input crece con su contenido (ancho mínimo por campo) para no recortar texto.
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

const linkToOrder = async () => {
    if (isLinking.value) return;
    isLinking.value = true;
    try {
        await axios.post(route('service-orders.solicitud-arco-cfe.link', props.order.id), {
            fields: fields.value,
        });
        linked.value = true;
        updateSnapshot();
        notification.success({
            title: 'Solicitud vinculada',
            content: 'El PDF se guardó como documento de la orden (pestaña Evidencias y Documentos).',
            duration: 6000
        });
    } catch (error) {
        notification.error({
            title: 'Error',
            content: 'No se pudo vincular la solicitud a la orden de servicio.',
            duration: 5000
        });
    } finally {
        isLinking.value = false;
    }
};

const print = () => window.print();
</script>

<template>
    <Head :title="`Solicitud Arco CFE · Orden #${order.id}`" />
    <div class="doc-page">
        <div class="doc-sheet" @input="onFieldInput">

            <!-- FECHA (editable) -->
            <div class="letter-head">
                <input
                    v-model="fields.fecha"
                    class="field-input text-right"
                    style="min-width: 120px;"
                    placeholder="dd/mm/aaaa"
                />
            </div>

            <div class="addressee">Comisión Federal de Electricidad</div>

            <!-- PÁRRAFO INICIAL -->
            <p class="paragraph">
                Por medio del presente solicito que el servicio no.
                <input v-model="fields.servicio" class="field-input" style="min-width: 150px;" placeholder="n° de servicio" />
                a nombre de
                <input v-model="fields.cliente" class="field-input" style="min-width: 220px;" placeholder="nombre del cliente" />
                ubicado en
                <input v-model="fields.domicilio" class="field-input" style="min-width: 450px;" placeholder="domicilio" />
                ,
                <a v-if="fields.maps_url" :href="fields.maps_url" target="_blank" class="maps-link">VER</a>
                <span v-else class="font-bold">VER</span>
                <!-- <input
                    v-model="fields.maps_url"
                    class="field-input maps-input"
                    style="width: 230px;"
                    placeholder="enlace de Google Maps"
                    title="Enlace del mapa (VER)"
                /> -->
                sea rectificado y/o actualizado de la siguiente forma:
            </p>

            <ul class="letter-list">
                <li>
                    • Actualizados los datos fiscales con timbrado, RFC:
                    <input v-model="fields.rfc" class="field-input" style="min-width: 150px;" placeholder="RFC" />.
                    Régimen fiscal:
                    <input v-model="fields.regimen" class="field-input" style="min-width: 430px;" placeholder="régimen fiscal" />,
                    Uso de CFDI:
                    <input v-model="fields.uso_cfdi" class="field-input" style="min-width: 190px;" placeholder="uso de CFDI" />.
                </li>
                <li>
                    • Actualizado los medios de contacto: número de teléfono:
                    <input v-model="fields.telefono" class="field-input" style="min-width: 140px;" placeholder="teléfono" />
                    y correo electrónico:
                    <input v-model="fields.correo" class="field-input" style="min-width: 280px;" placeholder="correo electrónico" />.
                </li>
                <li>
                    • Actualizado
                    <input v-model="fields.calle" class="field-input" style="min-width: 200px;" placeholder="calle" />
                    a entre calles
                    <input v-model="fields.entre_calles" class="field-input" style="min-width: 240px;" placeholder="entre calles" />.
                </li>
                <li>
                    • Rectificado nombre del titular
                    <input v-model="fields.titular" class="field-input" style="min-width: 270px;" placeholder="nombre del titular" />.
                </li>
            </ul>

            <!-- <p>Atentamente</p> -->

            <!-- LÍNEA DE FIRMA -->
            <div class="signature">
                <div class="signature-line"></div>
                <!-- <div class="signature-label">Firma</div> -->
                <input
                    v-model="fields.firma_nombre"
                    class="field-input signer-name-input"
                    style="min-width: 300px;"
                    placeholder="Nombre de quien firma"
                />
            </div>

            <!-- Pie discreto -->
            <!-- <div class="doc-footer">
                ORDEN #{{ order.id }} · GENERADO {{ generated_at }}
            </div> -->
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
    margin: 0 auto;
    padding: 22mm 20mm;
    position: relative;
    color: #111827;
    font-size: 17px;
    line-height: 1.7;
}

.letter-head {
    text-align: right;
    margin-bottom: 10px;
    color: #374151;
}

.addressee {
    font-size: 17px;
    font-weight: bold;
    color: #111827;
    margin-bottom: 16px;
}

.paragraph {
    margin-bottom: 14px;
    text-align: justify;
}

.letter-list {
    margin: 0 0 14px;
    padding-left: 0;
    list-style: none;
}

.letter-list li {
    margin-bottom: 10px;
    text-align: justify;
}

/* Campos editables: en negritas, integrados con el texto */
.field-input {
    border: none;
    outline: none;
    background: transparent;
    font-family: inherit;
    font-size: inherit;
    color: #000;
    font-weight: 700;
    padding: 0 2px;
    margin: 0;
    min-width: 40px;
    border-radius: 0;
    box-shadow: none;
    border-bottom: 1px dashed transparent;
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

.maps-link {
    font-weight: 700;
    color: #1e3a8a;
    text-decoration: underline;
}

.maps-input {
    margin-left: 4px;
}

.signature {
    margin-top: 70px;
}

.signature-line {
    width: 65%;
    border-top: 1px solid #000;
    margin: 0 auto;
}

.signature-label {
    margin-top: 5px;
    text-align: center;
    font-size: 10px;
    color: #374151;
}

.signer-name-input {
    display: block;
    margin: 14px auto 0;
    text-align: center;
}

.doc-footer {
    position: absolute;
    bottom: 14mm;
    left: 20mm;
    right: 20mm;
    font-size: 9px;
    color: #9ca3af;
    font-family: ui-monospace, monospace;
    text-align: center;
}

@media print {
    @page {
        size: letter;
        margin: 22mm 20mm;
    }

    body { background: #fff !important; }

    .doc-page { padding: 0; background: #fff; }

    .doc-sheet {
        width: auto !important;
        min-height: auto;
        border: none;
        margin: 30px;
        padding: 0;
    }

    .doc-footer { display: none; }

    .field-input {
        border: none !important;
        background: transparent !important;
        box-shadow: none !important;
    }

    .print\:hidden { display: none !important; }
}
</style>
