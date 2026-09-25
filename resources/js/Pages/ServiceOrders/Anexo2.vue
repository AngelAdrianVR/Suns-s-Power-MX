<script setup>
import { computed, ref } from 'vue';
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

// Convierte cualquier valor de texto a mayúsculas (todo el documento va así)
const upper = (value) => (value ? String(value).toUpperCase() : '');

// --- Selección de usuario (llenan los datos de contacto) ---
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

    if (user.name) fields.value.con_nombre = upper(user.name);
    if (user.role) fields.value.con_puesto = upper(user.role);
    if (user.phone) fields.value.con_telefono = upper(user.phone);
    if (user.email) fields.value.con_correo = upper(user.email);
};

// --- Domicilio del contacto (oficinas Veracruz / CDMX) ---
const CONTACT_ADDRESSES = {
    veracruz: {
        calle: 'Av Benito Juárez',
        num_ext: '105',
        num_int: '',
        cp: '93330',
        colonia: 'Tajin',
        municipio: 'Poza Rica de Hidalgo',
        estado: 'Veracruz',
    },
    cdmx: {
        calle: 'Lago Chiem',
        num_ext: '45',
        num_int: '',
        cp: '11440',
        colonia: 'San Juanico',
        municipio: 'Miguel Hidalgo',
        estado: 'Ciudad de México',
    },
};

const contactLocation = ref(null);

const locationOptions = [
    { label: 'Veracruz', value: 'veracruz' },
    { label: 'Ciudad de México (CDMX)', value: 'cdmx' },
];

const selectContactLocation = (value) => {
    contactLocation.value = value || null;
    if (!value) return;

    const address = CONTACT_ADDRESSES[value];
    if (!address) return;

    fields.value.con_calle = upper(address.calle);
    fields.value.con_num_ext = upper(address.num_ext);
    fields.value.con_num_int = upper(address.num_int);
    fields.value.con_cp = upper(address.cp);
    fields.value.con_colonia = upper(address.colonia);
    fields.value.con_municipio = upper(address.municipio);
    fields.value.con_estado = upper(address.estado);
};

// --- Casillas y opciones excluyentes ---
const setOption = (key, value) => {
    fields.value[key] = value;
};

const toggleManifiesto = () => {
    fields.value.manifiesto = fields.value.manifiesto === 'Si' ? 'No' : 'Si';
};

const linkToOrder = async () => {
    if (isLinking.value) return;
    isLinking.value = true;
    try {
        await axios.post(route('service-orders.anexo2.link', props.order.id), {
            fields: fields.value,
            user_id: selectedUserId.value,
        });
        linked.value = true;
        updateSnapshot();
        notification.success({
            title: 'Anexo 2 vinculado',
            content: 'El PDF se guardó como documento de la orden (pestaña Evidencias y Documentos).',
            duration: 6000
        });
    } catch (error) {
        notification.error({
            title: 'Error',
            content: 'No se pudo vincular el Anexo 2 a la orden de servicio.',
            duration: 5000
        });
    } finally {
        isLinking.value = false;
    }
};

const print = () => window.print();
</script>

<template>
    <Head :title="`Anexo 2 · Orden #${order.id}`" />
    <div class="doc-page">

        <!-- HERRAMIENTAS DE SELECCIÓN (NO IMPRIMIBLES) -->
        <div class="toolbar print:hidden max-w-4xl mx-auto mb-6 bg-white border border-indigo-100 rounded-2xl p-5 shadow-sm">
            <h3 class="text-sm font-bold text-gray-800 mb-3">
                Datos de contacto
            </h3>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <p class="text-xs font-bold text-indigo-700 uppercase mb-1.5">Usuario del sistema</p>
                    <n-select
                        :value="selectedUserId"
                        :options="userOptions"
                        placeholder="Elegir usuario"
                        clearable
                        filterable
                        size="small"
                        @update:value="selectUser"
                    />
                    <p class="text-[11px] text-gray-400 mt-2">
                        Llena el nombre, el puesto (rol) y los datos de contacto del usuario elegido.
                    </p>
                </div>
                <div>
                    <p class="text-xs font-bold text-indigo-700 uppercase mb-1.5">Domicilio del contacto</p>
                    <n-select
                        :value="contactLocation"
                        :options="locationOptions"
                        placeholder="Elegir Veracruz o CDMX"
                        clearable
                        size="small"
                        @update:value="selectContactLocation"
                    />
                    <p class="text-[11px] text-gray-400 mt-2">
                        Los datos del solicitante se toman del cliente de la orden. Todo se puede editar en el documento.
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

        <!-- HOJA: ANEXO 2 -->
        <div class="doc-sheet">

            <!-- ENCABEZADO -->
            <div class="head-row !flex !justify-end">
                <div class="head-item">
                    <span class="head-label">Fecha</span>
                    <input v-model="fields.fecha" class="line-input" style="width: 121px;" />
                </div>
                <div class="head-item">
                    <span class="head-label">Número de Solicitud</span>
                    <input v-model="fields.num_solicitud" class="line-input" style="width: 121px;" />
                </div>
            </div>

            <!-- I. DATOS DEL SOLICITANTE (CLIENTE) -->
            <div class="section-header">I. &nbsp;&nbsp;&nbsp; Datos del Solicitante</div>

            <div class="row">
                <div class="field w100">
                    <div class="field-label">Nombre, Denominación o Razón Social</div>
                    <input v-model="fields.sol_nombre" class="bracket-input" />
                </div>
            </div>

            <div class="sub-label">Domicilio</div>
            <div class="row">
                <div class="field w40">
                    <div class="field-label">Calle</div>
                    <input v-model="fields.sol_calle" class="bracket-input" />
                </div>
                <div class="field w20">
                    <div class="field-label">Número exterior</div>
                    <input v-model="fields.sol_num_ext" class="bracket-input" />
                </div>
                <div class="field w20">
                    <div class="field-label">Número Interior</div>
                    <input v-model="fields.sol_num_int" class="bracket-input" />
                </div>
                <div class="field w20">
                    <div class="field-label">Código Postal</div>
                    <input v-model="fields.sol_cp" class="bracket-input" />
                </div>
            </div>

            <div class="row">
                <div class="field w35">
                    <div class="field-label">Colonia/Población</div>
                    <input v-model="fields.sol_colonia" class="bracket-input" />
                </div>
                <div class="field w35">
                    <div class="field-label">Delegación/Municipio</div>
                    <input v-model="fields.sol_municipio" class="bracket-input" />
                </div>
                <div class="field w30">
                    <div class="field-label">Estado</div>
                    <input v-model="fields.sol_estado" class="bracket-input" />
                </div>
            </div>

            <div class="row">
                <div class="field w33">
                    <div class="field-label">Teléfono</div>
                    <input v-model="fields.sol_telefono" class="bracket-input" />
                </div>
                <div class="field w33">
                    <div class="field-label">Correo Electrónico</div>
                    <input v-model="fields.sol_correo" class="bracket-input" />
                </div>
                <div class="field w33">
                    <div class="field-label">Fax</div>
                    <input v-model="fields.sol_fax" class="bracket-input" />
                </div>
            </div>

            <!-- II. DATOS DE CONTACTO (USUARIO) -->
            <div class="section-header">II. &nbsp;&nbsp; Datos de Contacto</div>

            <div class="row">
                <div class="field w50">
                    <div class="field-label">Nombre</div>
                    <input v-model="fields.con_nombre" class="bracket-input" />
                </div>
                <div class="field w50">
                    <div class="field-label">Puesto</div>
                    <input v-model="fields.con_puesto" class="bracket-input" />
                </div>
            </div>

            <div class="sub-label">Domicilio</div>
            <div class="row">
                <div class="field w40">
                    <div class="field-label">Calle</div>
                    <input v-model="fields.con_calle" class="bracket-input" />
                </div>
                <div class="field w20">
                    <div class="field-label">Número exterior</div>
                    <input v-model="fields.con_num_ext" class="bracket-input" />
                </div>
                <div class="field w20">
                    <div class="field-label">Número Interior</div>
                    <input v-model="fields.con_num_int" class="bracket-input" />
                </div>
                <div class="field w20">
                    <div class="field-label">Código Postal</div>
                    <input v-model="fields.con_cp" class="bracket-input" />
                </div>
            </div>

            <div class="row">
                <div class="field w35">
                    <div class="field-label">Colonia/Población</div>
                    <input v-model="fields.con_colonia" class="bracket-input" />
                </div>
                <div class="field w35">
                    <div class="field-label">Delegación/Municipio</div>
                    <input v-model="fields.con_municipio" class="bracket-input" />
                </div>
                <div class="field w30">
                    <div class="field-label">Estado</div>
                    <input v-model="fields.con_estado" class="bracket-input" />
                </div>
            </div>

            <div class="row">
                <div class="field w33">
                    <div class="field-label">Teléfono</div>
                    <input v-model="fields.con_telefono" class="bracket-input" />
                </div>
                <div class="field w33">
                    <div class="field-label">Correo Electrónico</div>
                    <input v-model="fields.con_correo" class="bracket-input" />
                </div>
                <div class="field w33">
                    <div class="field-label">Fax</div>
                    <input v-model="fields.con_fax" class="bracket-input" />
                </div>
            </div>

            <!-- III. DATOS DE LA SOLICITUD -->
            <div class="section-header">III. &nbsp; Datos de la Solicitud</div>

            <div class="check-row section-body">
                <div class="check-label" style="width: 32%;">Modalidad de la Solicitud</div>
                <div class="check-opts" style="width: 68%;">
                    <span class="opt" @click="setOption('modalidad', 'baja')">Baja Tensión
                        <span class="check-box">{{ fields.modalidad === 'baja' ? 'X' : '' }}</span>
                    </span>
                    <span class="opt" @click="setOption('modalidad', 'media')">Media Tensión
                        <span class="check-box">{{ fields.modalidad === 'media' ? 'X' : '' }}</span>
                    </span>
                </div>
            </div>

            <!-- IV. UTILIZACIÓN DE LA ENERGÍA -->
            <div class="section-header">IV. &nbsp; Utilización de la Energía Eléctrica Producida</div>

            <div class="check-row spread section-body">
                <span class="opt">Consumo de Centros de Carga
                    <span class="check-box">{{ fields.utilizacion === 'centros' ? 'X' : '' }}</span>
                </span>
                <span class="opt">Consumo de Centros de Carga y Venta de Excedentes
                    <span class="check-box">{{ fields.utilizacion === 'excedentes' ? 'X' : '' }}</span>
                </span>
                <span class="opt">Venta Total
                    <span class="check-box">{{ fields.utilizacion === 'venta' ? 'X' : '' }}</span>
                </span>
            </div>

            <!-- V. DATOS DEL SERVICIO -->
            <div class="section-header">V. &nbsp;&nbsp; Datos del Servicio Suministro Actual</div>

            <div class="row section-body">
                <div class="field w50">
                    <div class="field-label">Registro Público de Usuario (RPU)</div>
                    <input v-model="fields.rpu" class="bracket-input" />
                </div>
                <div class="field w50">
                    <div class="field-label">Nivel de Tensión de Suministro</div>
                    <input v-model="fields.nivel_tension" class="bracket-input" />
                </div>
            </div>

            <!-- VI. CENTRAL ELÉCTRICA -->
            <div class="section-header">VI. &nbsp; Central Eléctrica</div>

            <div class="row section-body">
                <div class="field w25">
                    <div class="field-label center">Fecha estimada de Operación Normal (DD/MM/AAAA)</div>
                    <input v-model="fields.fecha_operacion" class="bracket-input" />
                </div>
                <div class="field w25">
                    <div class="field-label center">Capacidad Bruta Instalada (Kw)</div>
                    <input v-model="fields.capacidad_bruta" class="bracket-input" />
                </div>
                <div class="field w25">
                    <div class="field-label center">Capacidad a Incrementar (kw) (Opcional)</div>
                    <input v-model="fields.capacidad_incrementar" class="bracket-input" />
                </div>
                <div class="field w25">
                    <div class="field-label center">Generación Promedio Mensual Estimada (kwh/Mes)</div>
                    <input v-model="fields.generacion_promedio" class="bracket-input" />
                </div>
            </div>

            <!-- VII. MANIFESTACIÓN -->
            <div class="section-header">VII. Manifestación de Cumplimiento de las Especificaciones Técnicas Generales</div>

            <div class="manifest-row">
                <p class="manifest-text">
                    Manifiesto bajo protesta de decir la verdad que la Central Eléctrica cumple con las
                    Especificaciones técnicas requeridas de acuerdo las disposiciones aplicables.
                </p>
                <span class="si-box" @click="toggleManifiesto">{{ fields.manifiesto }}</span>
            </div>

            <div class="tech-label">Tecnología para generación de energía eléctrica</div>

            <div class="tech-grid">
                <div class="tech-col">
                    <div class="tech-item" @click="setOption('tecnologia', 'solar')">Solar
                        <span class="check-box w40">{{ fields.tecnologia === 'solar' ? 'X' : '' }}</span>
                    </div>
                    <div class="tech-item" @click="setOption('tecnologia', 'eolico')">Eólico
                        <span class="check-box w40">{{ fields.tecnologia === 'eolico' ? 'X' : '' }}</span>
                    </div>
                </div>
                <div class="tech-col">
                    <div class="tech-item" @click="setOption('tecnologia', 'biomasa')">Biomasa
                        <span class="check-box w40">{{ fields.tecnologia === 'biomasa' ? 'X' : '' }}</span>
                    </div>
                    <div class="tech-item" @click="setOption('tecnologia', 'cogeneracion')">Cogeneración
                        <span class="check-box w40">{{ fields.tecnologia === 'cogeneracion' ? 'X' : '' }}</span>
                    </div>
                </div>
                <div class="tech-col tech-col-wide">
                    <div class="tech-item" @click="setOption('tecnologia', 'otro')">Otro
                        <span class="check-box w60">{{ fields.tecnologia === 'otro' ? 'X' : '' }}</span>
                    </div>
                    <div class="tech-item">Especificar
                        <input v-model="fields.tecnologia_otro" class="line-input flex-grow" style="min-width: 120px;" />
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="field w33">
                    <div class="field-label">No de unidades de generación</div>
                    <input v-model="fields.num_unidades" class="bracket-input" />
                </div>
                <div class="field w33">
                    <div class="field-label">Combustible principal</div>
                    <input v-model="fields.combustible_principal" class="bracket-input" />
                </div>
                <div class="field w33">
                    <div class="field-label">Combustible secundario</div>
                    <input v-model="fields.combustible_secundario" class="bracket-input" />
                </div>
            </div>

            <!-- TABLA COORDENADAS UTM -->
            <table class="utm-table">
                <tr>
                    <!-- <td class="utm-title" rowspan="7">Coordenadas UTM</td> -->
                    <td class="utm-head border !border-b-transparent text-left">Coordenadas UTM</td>
                    <td class="utm-head border !border-b-transparent text-left">X</td>
                    <td class="utm-head border !border-b-transparent text-left">Y</td>
                </tr>
                <tr v-for="i in 6" :key="i">
                    <td class="utm-num">{{ i }}</td>
                    <td><input v-model="fields['utm_x' + i]" class="cell-input" /></td>
                    <td><input v-model="fields['utm_y' + i]" class="cell-input" /></td>
                </tr>
            </table>

            <!-- TEXTOS LEGALES -->
            <div class="legal">
                <div class="legal-right">
                    <input v-model="fields.titular_nombre" class="line-input legal-name" />
                    <span class="legal-text">(Representante Legal o El Solicitante) / (El Solicitante) certifica que la</span>
                </div>
                <p>
                    información proporcionada en la presente solicitud es apropiada, precisa y verídica. El solicitante
                    acepta que los datos proporcionados sean utilizados para llevar a cabo los Estudios de Interconexión
                    para garantizar la confiabilidad del Sistema Eléctrico Nacional con la Interconexion de la Central
                    Eléctrica del solicitante al amparo de la <em>Ley de la Industria Eléctrica y su Reglamento</em>,
                    en caso de ser requeridos. El solicitante entiende que los datos proporcionados, se añadirán a las
                    bases de datos del suministrador cuando se firme un contrato de interconexión respectivo.
                </p>
                <p>
                    El solicitante deberá anexa a la presente solicitud, la información técnica requerida en el
                    documento "Informacion Técnica Requerida para Centrales Eléctricas"
                </p>
            </div>

            <!-- FIRMAS -->
            <div class="sign-grid">
                <div class="sign-left">
                    <div class="firma-box">
                        <div class="firma-title">Firma de Conformidad</div>
                        <!-- <div class="firma-line"></div> -->
                        <div class="firma-foot">Solicitante</div>
                    </div>

                    <div class="sign-line">
                        <span class="sign-label">Nombre</span>
                        <input v-model="fields.firma_nombre" class="line-input flex-grow" style="min-width: 150px;" />
                    </div>
                    <div class="sign-line">
                        <span class="sign-label">Cargo</span>
                        <input v-model="fields.firma_cargo" class="line-input flex-grow" style="min-width: 150px;" />
                    </div>
                    <div class="sign-line">
                        <span class="sign-label">Fecha</span>
                        <input v-model="fields.firma_fecha" class="line-input flex-grow" style="min-width: 150px;" />
                    </div>
                </div>

                <div class="sign-right">
                    <div class="cfe-box">
                        <span>sello y firma<br>Centro de Atención</span>
                    </div>
                </div>
            </div>

            <!-- LÍNEA DE CIERRE -->
            <div class="sheet-end-line"></div>
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
    padding: 10mm 10mm;
    position: relative;
    color: #000;
    font-family: Arial, Helvetica, sans-serif;
    font-size: 11.5px;
    line-height: 1.25;
}

/* Encabezado */
.head-row {
    display: flex;
    justify-content: flex-start;
    gap: 10%;
    margin-bottom: 8px;
    padding: 0 4%;
}

.head-item {
    display: flex;
    align-items: flex-end;
    gap: 6px;
}

.head-label {
    font-weight: bold;
    font-size: 12px;
    white-space: nowrap;
}

/* Encabezados de sección */
.section-header {
    background-color: #d9d9d9;
    font-weight: bold;
    font-size: 11px;
    padding: 2px 8px;
    margin-top: 8px;
    margin-bottom: 4px;
}

/* Campos: fila con línea inferior corrida y divisiones verticales por campo */
.row {
    display: flex;
    border-bottom: 2.5px solid #000;
    margin-bottom: 5px;
}

.row .field {
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    min-height: 20px;
    padding: 0;
    min-width: 0;
}

/* Etiquetas de campo (van arriba, dentro del recuadro) */
.field-label {
    font-size: 8.5px;
    line-height: 1;
    margin: 0 0 1px;
    padding: 0 4px;
}

.field-label.center {
    text-align: center;
}

.sub-label {
    font-size: 10.5px;
    font-weight: 600;
    margin: 2px 0 1px;
}

.bracket-input {
    border: none;
    outline: none;
    background: transparent;
    font-family: inherit;
    font-size: inherit;
    color: #000;
    width: 100%;
    padding: 0;
    margin: 0;
    text-transform: uppercase;
}

.bracket-input:hover,
.bracket-input:focus {
    background: #fefce8;
}

/* Las líneas verticales separadoras sólo recorren la zona de captura
   (quedan por debajo de los títulos de cada campo). */
.row .field .bracket-input {
    border-right: 2.5px solid #000;
    padding: 0 4px;
    height: 12px;
    font-size: 11px;
    line-height: 1;
}

.row .field:first-child .bracket-input {
    border-left: 2.5px solid #000;
}

.w20 { width: 20%; }
.w25 { width: 25%; }
.w30 { width: 30%; }
.w33 { width: 33.33%; }
.w35 { width: 35%; }
.w40 { width: 40%; }
.w50 { width: 50%; }
.w100 { width: 100%; }

/* Filas de casillas */
.check-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 4px;
    margin-bottom: 4px;
}

.check-row.spread {
    gap: 8px;
}

/* Secciones III a VI: un poco más de aire arriba y abajo */
.section-body {
    margin-top: 7px;
    margin-bottom: 7px;
}

.check-label {
    font-size: 10.5px;
}

.check-opts {
    display: flex;
    justify-content: space-around;
    align-items: center;
}

.opt {
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 10.5px;
    white-space: nowrap;
}

.check-box {
    border: 1.5px solid #000;
    width: 44px;
    height: 16px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 11px;
    flex-shrink: 0;
}

.check-box.w40 { width: 40px; }
.check-box.w60 { width: 60px; }

/* Manifestación */
.manifest-row {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 12px;
    margin-bottom: 6px;
}

.manifest-text {
    font-size: 10px;
    text-align: justify;
    flex-grow: 1;
}

.si-box {
    border: 1.5px solid #000;
    padding: 1px 8px;
    font-size: 10.5px;
    font-weight: bold;
    text-align: center;
    min-width: 32px;
    cursor: pointer;
    flex-shrink: 0;
}

/* Tecnología */
.tech-label {
    font-size: 10.5px;
    margin-bottom: 3px;
}

.tech-grid {
    display: flex;
    gap: 16px;
    margin-bottom: 6px;
}

.tech-col {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 3px;
}

.tech-col-wide {
    flex: 1.4;
}

.tech-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    font-size: 10.5px;
    cursor: pointer;
    padding-right: 12px;
}

.tech-item input {
    cursor: text;
}

/* Tabla UTM */
.utm-table {
    width: 100%;
    border-collapse: collapse;
    border: 2.5px solid #000;
    text-align: center;
    font-size: 9.5px;
    margin: 8px 0 6px;
    table-layout: fixed;
}

.utm-table td {
    border: 2.5px solid #000;
    padding: 1px 3px;
    height: 9px;
    line-height: 1;
}

.utm-title {
    width: 25%;
    font-weight: bold;
    text-align: left;
    padding-left: 4px !important;
}

.utm-head {
    font-weight: bold;
}

.utm-num {
    font-weight: bold;
}

.cell-input {
    border: none;
    outline: none;
    background: transparent;
    font-family: inherit;
    font-size: inherit;
    color: #000;
    width: 100%;
    text-align: center;
    padding: 0;
    height: 9px;
    line-height: 1;
    text-transform: uppercase;
}

.cell-input:hover,
.cell-input:focus {
    background: #fefce8;
}

/* Textos legales */
.legal {
    font-size: 8px;
    text-align: justify;
    line-height: 1.2;
    margin-bottom: 8px;
}

.legal p {
    margin-bottom: 2px;
}

.legal-right {
    display: flex;
    align-items: flex-end;
    gap: 4px;
    margin-bottom: 2px;
}

.legal-text {
    flex: 0 1 auto;
    text-align: right;
    line-height: 1.15;
}

/* Nombre del titular del servicio (renglón legal): la línea cubre el espacio libre a su izquierda */
.legal-name {
    flex: 1 1 auto;
    min-width: 180px;
    font-size: 8px;
    font-weight: bold;
}

/* Campos de línea simple */
.line-input {
    border: none;
    border-bottom: 2.5px solid #000;
    outline: none;
    background: transparent;
    font-family: inherit;
    font-size: inherit;
    color: #000;
    padding: 0 3px;
    margin: 0;
    text-transform: uppercase;
}

.line-input:hover,
.line-input:focus {
    background: #fefce8;
}

/* Firmas */
.sign-grid {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 20px;
}

.sign-left {
    width: 58%;
}

.sign-right {
    width: 38%;
    display: flex;
    justify-content: flex-end;
}

.firma-box {
    border: 2.5px solid #000;
    width: 80%;
    height: 76px;
    padding: 3px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 6px;
}

.firma-title {
    font-size: 9px;
}

.firma-line {
    width: 78%;
    border-bottom: 2.5px solid #000;
}

.firma-foot {
    font-size: 9px;
}

.sign-line {
    display: flex;
    align-items: flex-end;
    gap: 8px;
    margin-bottom: 4px;
    padding-left: 24px;
}

.sign-label {
    font-size: 9.5px;
    width: 45px;
    white-space: nowrap;
}

.sign-line .line-input {
    font-size: 10px;
}

.cfe-box {
    border: 2.5px solid #000;
    width: 80%;
    height: 115px;
    display: flex;
    align-items: flex-end;
    justify-content: center;
    padding-bottom: 8px;
}

.cfe-box span {
    font-size: 9px;
    text-align: center;
    line-height: 1.2;
}

/* Línea horizontal de cierre, al final de la hoja */
.sheet-end-line {
    margin-top: 10px;
    border-bottom: 2.5px solid #000;
}

/* Impresión: compactar para una sola hoja */
@media print {
    @page {
        size: letter;
        margin: 15mm 18mm;
    }

    html,
    body { background: #fff !important; margin: 0 !important; padding: 0 !important; }

    .doc-page { padding: 0; background: #fff; min-height: auto !important; }

    .doc-sheet {
        width: auto !important;
        min-height: auto;
        border: none;
        margin: 0;
        padding: 0;
        font-size: 10px;
        line-height: 1.15;
    }

    .head-row { margin-bottom: 5px; }
    .section-header { font-size: 9.5px; padding: 1px 5px; margin-top: 5px; margin-bottom: 2px; }
    .row { margin-bottom: 3px; }
    .row .field { min-height: 15px; }
    .field-label { font-size: 7px; margin: 0 0 1px; padding: 0 3px; }
    .row .field .bracket-input { padding: 0 3px; height: 10px; font-size: 9.5px; }
    .sub-label { font-size: 9px; margin: 1px 0; }
    .check-row { margin-bottom: 2px; }
    .opt { font-size: 9px; gap: 5px; }
    .check-box { height: 13px; width: 40px; font-size: 9.5px; }
    .manifest-text { font-size: 8.5px; }
    .tech-label { font-size: 9px; }
    .tech-item { font-size: 9px; }
    .utm-table { margin: 5px 0 3px; font-size: 9px; }
    .utm-table td { height: 7px; padding: 1px 2px; }
    .cell-input { height: 7px; }
    .legal { font-size: 7px; margin-bottom: 5px; }
    .legal-name { min-width: 150px; font-size: 7px; }
    .firma-box { height: 56px; }
    .cfe-box { height: 86px; }
    .sign-line { margin-bottom: 3px; padding-left: 20px; }
    .sign-label { font-size: 8.5px; }
    .sign-line .line-input { font-size: 9px; }

    .bracket-input,
    .cell-input,
    .line-input {
        background: transparent !important;
        box-shadow: none !important;
    }

    .opt,
    .tech-item,
    .si-box { cursor: default; }

    .print\:hidden { display: none !important; }
}
</style>
