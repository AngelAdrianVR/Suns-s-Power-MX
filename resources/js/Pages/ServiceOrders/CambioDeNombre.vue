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

// Estado local editable (todo el documento se puede modificar)
const fields = ref({ ...(props.fields || {}) });
const snapshotRef = ref(JSON.stringify(props.fields || {}));

const hasDirty = computed(() => JSON.stringify(fields.value) !== snapshotRef.value);

const updateSnapshot = () => {
    snapshotRef.value = JSON.stringify(fields.value);
};

// --- Selección de usuario (datos del solicitante) ---
const selectedUserId = ref(null);

const userOptions = computed(() =>
    (props.users || []).map((u) => ({ label: u.name, value: u.id }))
);

const userById = (id) => (props.users || []).find((u) => u.id === id);

const selectUser = (userId) => {
    selectedUserId.value = userId || null;
    if (!userId) return;

    const user = userById(userId);
    if (!user) return;

    if (user.name) fields.value.solicitante_nombre = user.name;
    if (user.ine_number) fields.value.id_numero = user.ine_number;
    if (user.rfc) {
        fields.value.rfc = user.rfc;
        fields.value.tiene_rfc = true;
    }
    if (user.phone) {
        fields.value.celular = user.phone;
        fields.value.tiene_celular = true;
    }
    if (user.email) {
        fields.value.correo = user.email;
        fields.value.tiene_correo = true;
    }
};

// --- Casillas marcables y opciones excluyentes ---
const toggleBool = (key) => {
    fields.value[key] = !fields.value[key];
};

const setOption = (key, value) => {
    fields.value[key] = value;
};

// --- Auto-ajuste de ancho de los campos editables ---
const autoResize = (el) => {
    if (!el) return;
    el.style.width = '';
    const min = parseFloat(getComputedStyle(el).minWidth) || 40;
    el.style.width = `${Math.max(el.scrollWidth + 4, min)}px`;
};

const onFieldInput = (event) => {
    if (event.target && event.target.classList.contains('fill-input')) {
        autoResize(event.target);
    }
};

onMounted(() => {
    document.querySelectorAll('.fill-input').forEach(autoResize);
});

const linkToOrder = async () => {
    if (isLinking.value) return;
    isLinking.value = true;
    try {
        // Los booleanos se envían como '1'/'0' para pasar la validación del servidor
        const payload = {};
        for (const [key, value] of Object.entries(fields.value)) {
            payload[key] = typeof value === 'boolean' ? (value ? '1' : '0') : (value ?? '');
        }

        await axios.post(route('service-orders.cambio-de-nombre.link', props.order.id), {
            fields: payload,
            user_id: selectedUserId.value,
        });
        linked.value = true;
        updateSnapshot();
        notification.success({
            title: 'Cambio de Nombre vinculado',
            content: 'El PDF se guardó como documento de la orden (pestaña Evidencias y Documentos).',
            duration: 6000
        });
    } catch (error) {
        notification.error({
            title: 'Error',
            content: 'No se pudo vincular el cambio de nombre a la orden de servicio.',
            duration: 5000
        });
    } finally {
        isLinking.value = false;
    }
};

const print = () => window.print();
</script>

<template>
    <Head :title="`Cambio de Nombre · Orden #${order.id}`" />
    <div class="doc-page" @input="onFieldInput">

        <!-- HERRAMIENTAS DE SELECCIÓN (NO IMPRIMIBLES) -->
        <div class="toolbar print:hidden max-w-4xl mx-auto mb-6 bg-white border border-indigo-100 rounded-2xl p-5 shadow-sm">
            <h3 class="text-sm font-bold text-gray-800 mb-3 flex items-center gap-2">
                Datos del solicitante
            </h3>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <p class="text-xs font-bold text-indigo-700 uppercase mb-1.5">Usuario del sistema</p>
                    <n-select
                        :value="selectedUserId"
                        :options="userOptions"
                        placeholder="Elegir usuario para llenar sus datos"
                        clearable
                        filterable
                        size="small"
                        @update:value="selectUser"
                    />
                    <p class="text-[11px] text-gray-400 mt-2">
                        Al elegir un usuario se llenan nombre, INE, RFC y datos de contacto en la solicitud.
                        Todos los campos se pueden editar directamente en el documento.
                    </p>
                </div>
                <div class="border border-gray-200 rounded-xl p-3 bg-gray-50">
                    <p class="text-xs font-bold text-indigo-700 uppercase mb-1.5">Orden de servicio</p>
                    <p class="text-[12px] text-gray-600">Orden #{{ order.id }}</p>
                    <p class="text-[12px] text-gray-600">N° de servicio: {{ order.service_number || 'Sin número' }}</p>
                    <p class="text-[12px] text-gray-600">Generado: {{ generated_at }}</p>
                </div>
            </div>
        </div>

        <!-- HOJA: SOLICITUD DE CONTRATO POR CAMBIO DE TITULAR -->
        <div class="doc-sheet">

            <!-- ENCABEZADO -->
            <div class="head-row">
                <div>
                    <p>Comision Federal de Electricidad</p>
                    <p>A quien corresponda:</p>
                </div>
                <div class="text-right">
                    <p class="mb-1">Fecha:
                        <input v-model="fields.fecha" class="fill-input text-right" style="min-width: 120px;" placeholder="dd/mm/aaaa" />
                    </p>
                    <p>Asunto: Solicitud de contrato por cambio de titular</p>
                </div>
            </div>

            <!-- IDENTIFICACIÓN DEL SOLICITANTE -->
            <div class="section">
                <div class="mb-1">
                    Yo, <input v-model="fields.solicitante_nombre" class="fill-input" style="min-width: 280px;" placeholder="nombre del solicitante" />
                    en mi calidad de <input v-model="fields.solicitante_calidad" class="fill-input" style="min-width: 270px;" placeholder="calidad" />
                </div>
                <div class="mb-1">identificándome con:</div>
                <div class="mb-2 flex flex-wrap items-end gap-y-1">
                    <span class="opt" @click="setOption('id_tipo', 'ife')">(<span class="chk">{{ fields.id_tipo === 'ife' ? 'X' : '' }}</span>) IFE/INE</span>
                    <span class="opt" @click="setOption('id_tipo', 'pasaporte')">(<span class="chk">{{ fields.id_tipo === 'pasaporte' ? 'X' : '' }}</span>) Pasaporte</span>
                    <span class="opt" @click="setOption('id_tipo', 'cedula')">(<span class="chk">{{ fields.id_tipo === 'cedula' ? 'X' : '' }}</span>) Cédula Profesional</span>
                    <span class="opt" @click="setOption('id_tipo', 'otro')">(<span class="chk">{{ fields.id_tipo === 'otro' ? 'X' : '' }}</span>) Otro:
                        <input v-model="fields.id_otro" class="fill-input" style="min-width: 110px;" placeholder="documento" @click.stop />
                    </span>
                    <span class="ml-1">Número: <input v-model="fields.id_numero" class="fill-input" style="min-width: 150px;" placeholder="número" @click.stop /></span>
                </div>

                <div class="mb-1 flex flex-wrap items-end gap-y-1">
                    <span class="mr-1">y acreditando mi representación con:</span>
                    <span class="opt" @click="setOption('rep_tipo', 'na')">(<span class="chk">{{ fields.rep_tipo === 'na' ? 'X' : '' }}</span>) N/A</span>
                    <span class="opt" @click="setOption('rep_tipo', 'acta')">(<span class="chk">{{ fields.rep_tipo === 'acta' ? 'X' : '' }}</span>) Acta Constitutiva No.:
                        <input v-model="fields.rep_acta_no" class="fill-input" style="min-width: 100px;" placeholder="no." @click.stop />
                    </span>
                    <span class="opt" @click="setOption('rep_tipo', 'poder_notarial')">(<span class="chk">{{ fields.rep_tipo === 'poder_notarial' ? 'X' : '' }}</span>) Poder Notarial</span>
                </div>
                <div class="flex flex-wrap items-end gap-y-1">
                    <span>No.: <input v-model="fields.rep_no" class="fill-input" style="min-width: 100px;" placeholder="no." @click.stop /></span>
                    <span class="opt ml-2" @click="setOption('rep_tipo', 'carta_poder')">(<span class="chk">{{ fields.rep_tipo === 'carta_poder' ? 'X' : '' }}</span>) Carta Poder Simple</span>
                    <span class="opt ml-8" @click="setOption('rep_tipo', 'otro')">(<span class="chk">{{ fields.rep_tipo === 'otro' ? 'X' : '' }}</span>) Otro:
                        <input v-model="fields.rep_otro" class="fill-input" style="min-width: 170px;" placeholder="documento" @click.stop />
                    </span>
                </div>
            </div>

            <!-- SOLICITUD -->
            <div class="section text-justify">
                <div class="mb-1">
                    Solicito atentamente se sirva a efectuar los trámites administrativos necesarios para la contratación por cambio
                </div>
                <div class="mb-1">
                    de titular para el servicio de energía eléctrica con número de servicio
                    <input v-model="fields.servicio" class="fill-input" style="min-width: 240px;" placeholder="n° de servicio" />
                </div>
                <div class="mb-1">
                    actualmente a nombre de: <input v-model="fields.titular_actual" class="fill-input" style="min-width: 320px;" placeholder="titular actual" />
                    correspondiente al inmueble
                </div>
                <div class="mb-1 flex">
                    <span class="mr-1">ubicado en:</span> <input v-model="fields.domicilio" class="fill-input flex-grow" style="min-width: 400px;" placeholder="domicilio del inmueble" />,
                </div>
                <div class="mb-1 flex">
                    <span class="mr-1">con motivo de:</span> <input v-model="fields.motivo" class="fill-input flex-grow" style="min-width: 400px;" placeholder="motivo del cambio" />,
                </div>
                <div class="mb-1 flex">
                    <span class="mr-1">siendo el nombre del nuevo titular:</span> <input v-model="fields.nuevo_titular" class="fill-input flex-grow" style="min-width: 300px;" placeholder="nombre del nuevo titular" />.
                </div>
            </div>

            <!-- DOCUMENTACIÓN ADJUNTA -->
            <div class="section">
                <p class="mb-2 text-justify">
                    Acredito la propiedad y/o legítima posesión del inmueble y adjunto a la presente, copia simple de la siguiente
                    documentación, presentando original para cotejo:
                </p>
                <div class="check-list">
                    <p class="opt-line" @click="toggleBool('doc_escritura')">(<span class="chk">{{ fields.doc_escritura ? 'X' : '' }}</span>) Escritura Pública de la propiedad notariada</p>
                    <p class="opt-line" @click="toggleBool('doc_compraventa')">(<span class="chk">{{ fields.doc_compraventa ? 'X' : '' }}</span>) Contrato de compraventa notariado</p>
                    <p class="opt-line" @click="toggleBool('doc_gravamen')">(<span class="chk">{{ fields.doc_gravamen ? 'X' : '' }}</span>) Certificado de libertad de gravamen</p>
                    <p class="opt-line" @click="toggleBool('doc_predial')">(<span class="chk">{{ fields.doc_predial ? 'X' : '' }}</span>) Recibo del Predial</p>
                    <p class="opt-line" @click="toggleBool('doc_ine')">(<span class="chk">{{ fields.doc_ine ? 'X' : '' }}</span>) Credencial emitida por el INE con domicilio actual</p>
                    <p class="opt-line" @click="toggleBool('doc_arrendamiento_certificado')">(<span class="chk">{{ fields.doc_arrendamiento_certificado ? 'X' : '' }}</span>) Contrato de arrendamiento certificado</p>
                    <p class="opt-line" @click="toggleBool('doc_arrendamiento_simple')">
                        (<span class="chk">{{ fields.doc_arrendamiento_simple ? 'X' : '' }}</span>) Contrato de arrendamiento simple con firma de: A) fiador-aval o B) dos testigos. (incluyendo las identificaciones
                    </p>
                    <p class="pl-8">oficiales de los suscribientes.)</p>
                    <p class="opt-line" @click="toggleBool('doc_constancia')">(<span class="chk">{{ fields.doc_constancia ? 'X' : '' }}</span>) Carta constancia de domicilio expedida por autoridad de la localidad</p>
                </div>
            </div>

            <!-- DATOS PERSONALES -->
            <div class="section">
                <p class="mb-2">Anexo datos personales: (Marcar con una "X")</p>
                <div class="check-list">
                    <p class="opt-line" @click="toggleBool('tiene_rfc')">
                        (<span class="chk">{{ fields.tiene_rfc ? 'X' : '' }}</span>) R.F.C.:
                        <input v-model="fields.rfc" class="fill-input" style="min-width: 230px;" placeholder="RFC" @click.stop />
                    </p>
                    <p class="opt-line" @click="toggleBool('tiene_telefono')">
                        (<span class="chk">{{ fields.tiene_telefono ? 'X' : '' }}</span>) Teléfono:
                        <input v-model="fields.telefono" class="fill-input" style="min-width: 200px;" placeholder="teléfono" @click.stop />
                    </p>
                    <p class="opt-line" @click="toggleBool('tiene_celular')">
                        (<span class="chk">{{ fields.tiene_celular ? 'X' : '' }}</span>) Celular:
                        <input v-model="fields.celular" class="fill-input" style="min-width: 210px;" placeholder="celular" @click.stop />
                    </p>
                    <p class="opt-line" @click="toggleBool('tiene_correo')">
                        (<span class="chk">{{ fields.tiene_correo ? 'X' : '' }}</span>) Correo electrónico:
                        <input v-model="fields.correo" class="fill-input" style="min-width: 260px;" placeholder="correo electrónico" @click.stop />
                    </p>
                </div>
            </div>

            <!-- TIMBRADO -->
            <div class="section">
                <p>
                    Requiero timbrado de factura:
                    <span class="opt" @click="setOption('timbrado', 'Si')">(<span class="chk">{{ fields.timbrado === 'Si' ? 'X' : '' }}</span>) Si</span>
                    <span class="opt" @click="setOption('timbrado', 'No')">(<span class="chk">{{ fields.timbrado === 'No' ? 'X' : '' }}</span>) No</span>
                </p>
            </div>

            <!-- CONSTANCIA DE SITUACIÓN FISCAL -->
            <div class="section">
                <p class="mb-2">Datos de Constancia de Situación Fiscal*:</p>
                <div class="check-list">
                    <div class="flex">
                        <span class="mr-1">Nombre, Denominación o Razón Social:</span>
                        <input v-model="fields.csf_nombre" class="fill-input flex-grow" style="min-width: 260px;" placeholder="nombre o razón social" />
                    </div>
                    <div class="flex">
                        <span class="mr-1">Código Postal:</span>
                        <input v-model="fields.csf_cp" class="fill-input" style="min-width: 160px;" placeholder="C.P." />
                    </div>
                    <div class="flex">
                        <span class="mr-1">Régimen fiscal:</span>
                        <input v-model="fields.csf_regimen" class="fill-input flex-grow" style="min-width: 300px;" placeholder="régimen fiscal" />
                    </div>
                    <div class="flex">
                        <span class="mr-1">Uso de CFDI:</span>
                        <input v-model="fields.csf_uso" class="fill-input flex-grow" style="min-width: 300px;" placeholder="uso de CFDI" />
                    </div>
                    <div class="flex">
                        <span class="mr-1">Residencia Fiscal (cuando sea extranjero):</span>
                        <input v-model="fields.csf_residencia" class="fill-input flex-grow" style="min-width: 200px;" placeholder="residencia fiscal" />
                    </div>
                    <div class="flex">
                        <span class="mr-1">Número de registro de identidad fiscal (cuando sea extranjero):</span>
                        <input v-model="fields.csf_registro" class="fill-input flex-grow" style="min-width: 160px;" placeholder="registro fiscal" />
                    </div>
                </div>
            </div>

            <!-- DESPEDIDA -->
            <div class="closing text-justify">
                <p>Sin otro particular por el momento, agradezco de antemano la atención que se brinde al presente.</p>
            </div>

            <!-- FIRMA -->
            <div class="signature text-center">
                <p class="atentamente">Atentamente</p>
                <div class="inline-block">
                    <input v-model="fields.firma_nombre" class="fill-input sig-name" style="min-width: 300px;" placeholder="(Nombre opcional)" />
                    <p class="sig-label">Nombre y firma</p>
                    <p class="sig-label">Propietario, Representante Legal, Arrendatario</p>
                </div>
            </div>

            <!-- PIE -->
            <div class="foot-row">
                <p>*En caso de requerir factura timbrada.</p>
                <p>I-4001-063-R-01</p>
            </div>
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
    padding: 11mm 10mm;
    position: relative;
    color: #000;
    font-family: Arial, Helvetica, sans-serif;
    font-size: 12.5px;
    line-height: 1.4;
}

.head-row {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 0.75rem;
}

.section {
    margin-bottom: 0.55rem;
}

.closing {
    margin-bottom: 1.4rem;
}

/* Campos editables: línea inferior tipo formulario */
.fill-input {
    border: none;
    outline: none;
    background: transparent;
    font-family: inherit;
    font-size: inherit;
    color: #000;
    padding: 0 2px;
    margin: 0;
    min-width: 40px;
    border-bottom: 1px solid #000;
    border-radius: 0;
    box-shadow: none;
}

.fill-input:hover,
.fill-input:focus {
    background: #fefce8;
    box-shadow: 0 0 0 1px #f59e0b;
    border-radius: 2px;
}

.fill-input::placeholder {
    color: #9ca3af;
}

/* Casillas marcables */
.chk {
    display: inline-block;
    width: 14px;
    text-align: center;
    font-weight: 700;
    user-select: none;
}

.opt,
.opt-line {
    cursor: pointer;
}

.opt:hover .chk,
.opt-line:hover .chk {
    background: #fef3c7;
    border-radius: 2px;
}

.check-list p,
.check-list div {
    margin-bottom: 0.15rem;
}

/* Firma */
.signature {
    margin-bottom: 0.9rem;
}

.atentamente {
    margin-bottom: 1.2rem;
}

.sig-name {
    text-align: center;
}

.sig-label {
    font-size: 12px;
    line-height: 1.3;
}

/* Pie */
.foot-row {
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    font-size: 12px;
    color: #000;
}

@media print {
    @page {
        size: letter;
        margin: 8mm 9mm;
    }

    html,
    body { background: #fff !important; margin: 0 !important; padding: 0 !important; }

    .doc-page { padding: 0; background: #fff; }

    .doc-sheet {
        width: auto !important;
        min-height: auto;
        border: none;
        margin: 0;
        padding: 25px;
        font-size: 11px;
        line-height: 1.28;
    }

    /* Compactar espacios para que quepa en una sola hoja */
    .head-row { margin-bottom: 6px !important; }
    .section { margin-bottom: 5px !important; }
    .closing { margin-bottom: 10px !important; }
    .signature { margin-bottom: 6px !important; }
    .atentamente { margin-bottom: 8px !important; }
    .check-list p,
    .check-list div { margin-bottom: 1px !important; }
    .doc-sheet .mb-1 { margin-bottom: 2px !important; }
    .doc-sheet .mb-2 { margin-bottom: 3px !important; }
    .foot-row { font-size: 10.5px; }

    .fill-input {
        border: none !important;
        border-bottom: 1px solid #000 !important;
        background: transparent !important;
        box-shadow: none !important;
    }

    .chk {
        width: 14px;
    }

    .opt,
    .opt-line {
        cursor: default;
    }

    .print\:hidden { display: none !important; }
}
</style>
