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

// Datos de contacto fijos de la empresa (sección II): siempre son estos valores
const CONTACT_EMAIL = 'sunspower.mx@gmail.com';
const CONTACT_PHONE = '55 2867 8538';

// Estado local editable (todo el documento se puede modificar)
const initialFields = props.fields || {};
const fields = ref({
    ...initialFields,
    con_correo: initialFields.con_correo || CONTACT_EMAIL,
    con_telefono: initialFields.con_telefono || CONTACT_PHONE,
});
const snapshotRef = ref(JSON.stringify(fields.value));

const hasDirty = computed(() => JSON.stringify(fields.value) !== snapshotRef.value);

const updateSnapshot = () => {
    snapshotRef.value = JSON.stringify(fields.value);
};

// Convierte cualquier valor de texto a mayúsculas (todo el documento va así)
const upper = (value) => (value ? String(value).toUpperCase() : '');

// --- Selección de usuario (llena nombre y puesto del contacto) ---
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
                        Llena el nombre y el puesto (rol) del usuario elegido. El correo y el teléfono son los datos de contacto de la empresa.
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

        <!-- HOJA: ANEXO 2 (réplica exacta del machote A4) -->
        <div class="doc-sheet">
            <div class="paper">

                <!-- ENCABEZADO -->
                <span class="hd-label" style="left: 108.2pt; top: 62.3pt; font-size: 6.5pt;">Fecha</span>
                <span class="hd-line" style="left: 125.28pt; top: 70.56pt; width: 115.56pt;"></span>
                <input v-model="fields.fecha" class="hd-input" style="left: 125.28pt; top: 62pt; width: 115.56pt;" />
                <span class="hd-label" style="left: 346.1pt; top: 62.2pt; font-size: 6pt;">Número de Solicitud</span>
                <span class="hd-line" style="left: 410.16pt; top: 70.56pt; width: 77.28pt;"></span>
                <input v-model="fields.num_solicitud" class="hd-input" style="left: 410.16pt; top: 62pt; width: 77.28pt;" />

                <!-- I. DATOS DEL SOLICITANTE -->
                <div class="band" style="top: 78.84pt;"><span class="band-num">I.</span><span class="band-title">Datos del Solicitante</span></div>

                <div class="fr" style="top: 89.2pt; height: 16.4pt;">
                    <span class="lbl" style="left: 1.8pt;">Nombre, Denominación o Razon Social</span>
                    <input v-model="fields.sol_nombre" class="fi" style="left: 3.6pt; top: 7.52pt; width: 480.72pt; height: 8.4pt;" />
                    <span class="ln" style="left: 1.08pt; top: 15.92pt; width: 485.76pt; height: 0.48pt;"></span>
                    <span class="tk" style="left: 0; top: 6.8pt; height: 9.6pt;"></span>
                    <span class="tk" style="left: 486.84pt; top: 6.8pt; height: 9.6pt;"></span>
                </div>

                <span class="txt" style="left: 1.8pt; top: 105.7pt;">Domicilio</span>

                <div class="fr" style="top: 113.7pt; height: 17.46pt;">
                    <span class="lbl" style="left: 1.8pt;">Calle</span>
                    <span class="lbl" style="left: 129.2pt;">Número exterior</span>
                    <span class="lbl" style="left: 244.7pt;">Número Interior</span>
                    <span class="lbl" style="left: 360.4pt;">Código Postal</span>
                    <input v-model="fields.sol_calle" class="fi" style="left: 3.6pt; top: 8.0pt; width: 117.6pt; height: 8.4pt;" />
                    <input v-model="fields.sol_num_ext" class="fi" style="left: 128.4pt; top: 8.0pt; width: 108pt; height: 8.4pt;" />
                    <input v-model="fields.sol_num_int" class="fi" style="left: 243.84pt; top: 8.0pt; width: 108.24pt; height: 8.4pt;" />
                    <input v-model="fields.sol_cp" class="fi" style="left: 359.52pt; top: 8.0pt; width: 123.6pt; height: 8.4pt;" />
                    <span class="ln" style="left: 1.08pt; top: 16.38pt; width: 486.36pt; height: 1.08pt;"></span>
                    <span class="tk" style="left: 0; top: 7.5pt; height: 9.96pt;"></span>
                    <span class="tk" style="left: 124.8pt; top: 7.5pt; height: 9.96pt;"></span>
                    <span class="tk" style="left: 240.24pt; top: 7.5pt; height: 9.96pt;"></span>
                    <span class="tk" style="left: 355.92pt; top: 7.5pt; height: 9.96pt;"></span>
                    <span class="tk" style="left: 486.84pt; top: 7.5pt; height: 9.96pt;"></span>
                </div>

                <div class="fr" style="top: 130.8pt; height: 17.52pt;">
                    <span class="lbl" style="left: 1.8pt;">Colonia/Poblacion</span>
                    <span class="lbl" style="left: 126.6pt;">Delegación/Municipio</span>
                    <span class="lbl" style="left: 323.9pt;">Estado</span>
                    <input v-model="fields.sol_colonia" class="fi" style="left: 3.6pt; top: 8.04pt; width: 150.68pt; height: 8.4pt;" />
                    <input v-model="fields.sol_municipio" class="fi" style="left: 161.88pt; top: 8.04pt; width: 166.08pt; height: 8.4pt;" />
                    <input v-model="fields.sol_estado" class="fi" style="left: 335.16pt; top: 8.04pt; width: 149.16pt; height: 8.4pt;" />
                    <span class="ln" style="left: 0.24pt; top: 16.44pt; width: 487.68pt; height: 1.08pt;"></span>
                    <span class="tk" style="left: 0; top: 7.56pt; height: 9.96pt;"></span>
                    <span class="tk" style="left: 158.28pt; top: 7.56pt; height: 9.96pt;"></span>
                    <span class="tk" style="left: 331.56pt; top: 7.56pt; height: 9.96pt;"></span>
                    <span class="tk" style="left: 486.84pt; top: 7.56pt; height: 9.96pt;"></span>
                </div>

                <div class="fr" style="top: 148pt; height: 17.48pt;">
                    <span class="lbl" style="left: 1.8pt;">Teléfono</span>
                    <span class="lbl" style="left: 162.2pt;">Correo Electrónico</span>
                    <span class="lbl" style="left: 337.3pt;">Fax</span>
                    <input v-model="fields.sol_telefono" class="fi" style="left: 3.6pt; top: 8.0pt; width: 150.68pt; height: 8.4pt;" />
                    <input v-model="fields.sol_correo" class="fi lowercase-input" style="left: 161.88pt; top: 8.0pt; width: 166.08pt; height: 8.4pt;" />
                    <input v-model="fields.sol_fax" class="fi" style="left: 335.16pt; top: 8.0pt; width: 149.16pt; height: 8.4pt;" />
                    <span class="ln" style="left: 1.08pt; top: 16.4pt; width: 486.84pt; height: 1.08pt;"></span>
                    <span class="tk" style="left: 0; top: 7.52pt; height: 9.96pt;"></span>
                    <span class="tk" style="left: 158.28pt; top: 7.52pt; height: 9.96pt;"></span>
                    <span class="tk" style="left: 331.56pt; top: 7.52pt; height: 9.96pt;"></span>
                    <span class="tk" style="left: 486.84pt; top: 7.52pt; height: 9.96pt;"></span>
                </div>

                <!-- II. DATOS DE CONTACTO -->
                <div class="band" style="top: 172.68pt;"><span class="band-num">II.</span><span class="band-title">Datos de Contacto</span></div>

                <div class="fr" style="top: 183pt; height: 18pt;">
                    <span class="lbl" style="left: 1.8pt;">Nombre</span>
                    <span class="lbl" style="left: 242pt;">Puesto</span>
                    <input v-model="fields.con_nombre" class="fi" style="left: 3.6pt; top: 8.28pt; width: 232.44pt; height: 8.4pt;" />
                    <input v-model="fields.con_puesto" class="fi" style="left: 243.84pt; top: 8.28pt; width: 240.48pt; height: 8.4pt;" />
                    <span class="ln" style="left: 1.08pt; top: 16.92pt; width: 486.84pt; height: 1.08pt;"></span>
                    <span class="tk" style="left: 0; top: 7.32pt; height: 10.68pt;"></span>
                    <span class="tk" style="left: 240.24pt; top: 7.32pt; height: 10.68pt;"></span>
                    <span class="tk" style="left: 486.84pt; top: 7.32pt; height: 10.68pt;"></span>
                </div>

                <span class="txt" style="left: 1.8pt; top: 200pt;">Domicilio</span>

                <div class="fr" style="top: 208.5pt; height: 18.18pt;">
                    <span class="lbl" style="left: 1.8pt;">Calle</span>
                    <span class="lbl" style="left: 129.2pt;">Número exterior</span>
                    <span class="lbl" style="left: 244.7pt;">Número Interior</span>
                    <span class="lbl" style="left: 367.7pt;">Código Postal</span>
                    <input v-model="fields.con_calle" class="fi" style="left: 3.6pt; top: 8.1pt; width: 117.6pt; height: 8.4pt;" />
                    <input v-model="fields.con_num_ext" class="fi" style="left: 128.4pt; top: 8.1pt; width: 108pt; height: 8.4pt;" />
                    <input v-model="fields.con_num_int" class="fi" style="left: 243.84pt; top: 8.1pt; width: 108.24pt; height: 8.4pt;" />
                    <input v-model="fields.con_cp" class="fi" style="left: 359.52pt; top: 8.1pt; width: 123.6pt; height: 8.4pt;" />
                    <span class="ln" style="left: 1.08pt; top: 17.1pt; width: 486.84pt; height: 1.08pt;"></span>
                    <span class="tk" style="left: 0; top: 7.5pt; height: 10.68pt;"></span>
                    <span class="tk" style="left: 124.8pt; top: 7.5pt; height: 10.68pt;"></span>
                    <span class="tk" style="left: 240.24pt; top: 7.5pt; height: 10.68pt;"></span>
                    <span class="tk" style="left: 355.92pt; top: 7.5pt; height: 10.68pt;"></span>
                    <span class="tk" style="left: 486.84pt; top: 7.5pt; height: 10.68pt;"></span>
                </div>

                <div class="fr" style="top: 226.4pt; height: 18.88pt;">
                    <span class="lbl" style="left: 1.8pt;">Colonia/Poblacion</span>
                    <span class="lbl" style="left: 163.6pt;">Delegación/Municipio</span>
                    <span class="lbl" style="left: 323.9pt;">Estado</span>
                    <input v-model="fields.con_colonia" class="fi" style="left: 3.6pt; top: 8.0pt; width: 150.68pt; height: 8.4pt;" />
                    <input v-model="fields.con_municipio" class="fi" style="left: 161.88pt; top: 8.0pt; width: 166.08pt; height: 8.4pt;" />
                    <input v-model="fields.con_estado" class="fi" style="left: 335.16pt; top: 8.0pt; width: 149.16pt; height: 8.4pt;" />
                    <span class="ln" style="left: 1.08pt; top: 17.8pt; width: 486.84pt; height: 1.08pt;"></span>
                    <span class="tk" style="left: 0; top: 7.48pt; height: 11.4pt;"></span>
                    <span class="tk" style="left: 158.28pt; top: 7.48pt; height: 11.4pt;"></span>
                    <span class="tk" style="left: 331.56pt; top: 7.48pt; height: 11.4pt;"></span>
                    <span class="tk" style="left: 486.84pt; top: 7.48pt; height: 11.4pt;"></span>
                </div>

                <div class="fr" style="top: 245pt; height: 18.16pt;">
                    <span class="lbl" style="left: 1.8pt;">Teléfono</span>
                    <span class="lbl" style="left: 163.5pt;">Correo Electrónico</span>
                    <span class="lbl" style="left: 337.3pt;">Fax</span>
                    <input v-model="fields.con_telefono" class="fi" style="left: 3.6pt; top: 8.0pt; width: 150.68pt; height: 8.4pt;" />
                    <input v-model="fields.con_correo" class="fi lowercase-input" style="left: 161.88pt; top: 8.0pt; width: 166.08pt; height: 8.4pt;" />
                    <input v-model="fields.con_fax" class="fi" style="left: 335.16pt; top: 8.0pt; width: 149.16pt; height: 8.4pt;" />
                    <span class="ln" style="left: 1.08pt; top: 17.08pt; width: 486.84pt; height: 1.08pt;"></span>
                    <span class="tk" style="left: 0; top: 7.48pt; height: 10.68pt;"></span>
                    <span class="tk" style="left: 158.28pt; top: 7.48pt; height: 10.68pt;"></span>
                    <span class="tk" style="left: 331.56pt; top: 7.48pt; height: 10.68pt;"></span>
                    <span class="tk" style="left: 486.84pt; top: 7.48pt; height: 10.68pt;"></span>
                </div>

                <!-- III. DATOS DE LA SOLICITUD -->
                <div class="band" style="top: 270.36pt;"><span class="band-num">III.</span><span class="band-title">Datos de la Solicitud</span></div>

                <span class="txt clickable" style="left: 1.8pt; top: 283pt;" @click="setOption('modalidad', 'baja')">Modalidad de la Solicitud</span>
                <span class="txt clickable" style="left: 166.7pt; top: 283pt;" @click="setOption('modalidad', 'baja')">Baja Tensión</span>
                <span class="cb" style="left: 251.28pt; top: 279.72pt; width: 25.44pt; height: 11.52pt; font-size: 10.8pt;" @click="setOption('modalidad', 'baja')">{{ fields.modalidad === 'baja' ? 'X' : '' }}</span>
                <span class="txt clickable" style="left: 292.2pt; top: 283pt;" @click="setOption('modalidad', 'media')">Media Tensión</span>
                <span class="cb" style="left: 354.84pt; top: 280.2pt; width: 54.84pt; height: 10.2pt; font-size: 10.8pt;" @click="setOption('modalidad', 'media')">{{ fields.modalidad === 'media' ? 'X' : '' }}</span>

                <!-- IV. UTILIZACIÓN DE LA ENERGÍA -->
                <div class="band" style="top: 299.4pt;"><span class="band-num">IV.</span><span class="band-title">Utilización de la Energia Eléctrica Producida</span></div>

                <span class="txt clickable" style="left: 1.8pt; top: 309.2pt;" @click="setOption('utilizacion', 'centros')">Consumo de Centros de</span>
                <span class="txt clickable" style="left: 1.8pt; top: 317.3pt;" @click="setOption('utilizacion', 'centros')">Carga</span>
                <span class="cb" style="left: 74.28pt; top: 312.48pt; width: 26.4pt; height: 12.6pt; font-size: 10.8pt;" @click="setOption('utilizacion', 'centros')">{{ fields.utilizacion === 'centros' ? 'X' : '' }}</span>
                <span class="txt clickable" style="left: 126.6pt; top: 309.2pt;" @click="setOption('utilizacion', 'excedentes')">Consumo de Centros de Carga y</span>
                <span class="txt clickable" style="left: 126.6pt; top: 317.3pt;" @click="setOption('utilizacion', 'excedentes')">Venta de Excedentes</span>
                <span class="cb" style="left: 251.76pt; top: 311.64pt; width: 25.32pt; height: 13.08pt; font-size: 10.8pt;" @click="setOption('utilizacion', 'excedentes')">{{ fields.utilizacion === 'excedentes' ? 'X' : '' }}</span>
                <span class="txt clickable" style="left: 298.2pt; top: 312.5pt;" @click="setOption('utilizacion', 'venta')">Venta Total</span>
                <span class="cb" style="left: 355.68pt; top: 311.64pt; width: 54.96pt; height: 12.72pt; font-size: 10.8pt;" @click="setOption('utilizacion', 'venta')">{{ fields.utilizacion === 'venta' ? 'X' : '' }}</span>

                <!-- V. DATOS DEL SERVICIO -->
                <div class="band" style="top: 333.72pt;"><span class="band-num">V.</span><span class="band-title">Datos del Servicio Suministro Actual</span></div>

                <div class="fr" style="top: 344.2pt; height: 18.2pt;">
                    <span class="lbl" style="left: 1.8pt;">Registro Público de Usuario (RPU)</span>
                    <span class="lbl" style="left: 248.7pt;">Nivel de Tensión de Suministro</span>
                    <input v-model="fields.rpu" class="fi" style="left: 3.6pt; top: 8.28pt; width: 232.44pt; height: 8.4pt;" />
                    <input v-model="fields.nivel_tension" class="fi" style="left: 243.84pt; top: 8.28pt; width: 240.48pt; height: 8.4pt;" />
                    <span class="ln" style="left: 1.08pt; top: 17.12pt; width: 486.36pt; height: 1.08pt;"></span>
                    <span class="tk" style="left: 0; top: 7.52pt; height: 10.68pt;"></span>
                    <span class="tk" style="left: 240.24pt; top: 7.52pt; height: 10.68pt;"></span>
                    <span class="tk" style="left: 486.84pt; top: 7.52pt; height: 10.68pt;"></span>
                </div>

                <!-- VI. CENTRAL ELÉCTRICA -->
                <div class="band" style="top: 369.96pt;"><span class="band-num">VI.</span><span class="band-title">Central Eléctrica</span></div>

                <div class="fr" style="top: 379.1pt; height: 24.46pt;">
                    <span class="lbl" style="left: 1.8pt;">Fecha estimada de Operación Normal</span>
                    <span class="lbl" style="left: 1.8pt; top: 5.9pt;">(DD/MM/AAAA)</span>
                    <span class="lbl" style="left: 178.8pt; top: 0.1pt;">Capacidad Bruta</span>
                    <span class="lbl" style="left: 164.9pt; top: 5.9pt;">Instalada (Kw)</span>
                    <span class="lbl" style="left: 243.4pt; top: 0.1pt;">Capacidad a Incrementar (kw)</span>
                    <span class="lbl" style="left: 274.3pt; top: 5.6pt;">(Opcional)</span>
                    <span class="lbl" style="left: 360.7pt; top: 0.1pt;">Generación Promedio Mensual Estimada</span>
                    <span class="lbl" style="left: 396.8pt; top: 5.6pt;">(kwh/Mes)</span>
                    <input v-model="fields.fecha_operacion" class="fi" style="left: 3.6pt; top: 14.86pt; width: 150.68pt; height: 8.4pt;" />
                    <input v-model="fields.capacidad_bruta" class="fi" style="left: 161.88pt; top: 14.86pt; width: 74.76pt; height: 8.4pt;" />
                    <input v-model="fields.capacidad_incrementar" class="fi" style="left: 243.84pt; top: 14.86pt; width: 84.12pt; height: 8.4pt;" />
                    <input v-model="fields.generacion_promedio" class="fi" style="left: 335.16pt; top: 14.86pt; width: 149.16pt; height: 8.4pt;" />
                    <span class="ln" style="left: 1.08pt; top: 23.38pt; width: 486.84pt; height: 1.08pt;"></span>
                    <span class="tk" style="left: 0; top: 13.78pt; height: 10.68pt;"></span>
                    <span class="tk" style="left: 158.28pt; top: 13.78pt; height: 10.68pt;"></span>
                    <span class="tk" style="left: 240.24pt; top: 13.78pt; height: 10.68pt;"></span>
                    <span class="tk" style="left: 331.56pt; top: 13.78pt; height: 10.68pt;"></span>
                    <span class="tk" style="left: 486.84pt; top: 13.78pt; height: 10.68pt;"></span>
                </div>

                <!-- VII. MANIFESTACIÓN -->
                <div class="band" style="top: 411.12pt;"><span class="band-num">VII.</span><span class="band-title">Manifestación de Cumplimiento de las Especificicaciones Técnicas Generales</span></div>

                <span class="mtext1" style="left: 1.7pt; top: 421.5pt;">Manifiesto bajo protesta de decir la verdad que la Central Eléctrica cumple con las Especificaciones técnicas requeridas de acuerdo las disposiciones</span>
                <span class="mtext2" style="left: 1.8pt; top: 429.1pt;">aplicables.</span>
                <span class="cb" style="left: 462.12pt; top: 420.48pt; width: 24.84pt; height: 9.84pt; font-size: 6pt;" @click="toggleManifiesto">{{ fields.manifiesto === 'Si' ? 'SI' : '' }}</span>

                <span class="txt" style="left: 1.8pt; top: 444.7pt;">Tecnología para generación de energía eléctrica</span>

                <span class="txt clickable" style="left: 10.6pt; top: 455.1pt;" @click="setOption('tecnologia', 'solar')">Solar</span>
                <span class="stack" style="left: 31.68pt; top: 452.64pt; width: 23.76pt; height: 26.64pt; border-width: 0.48pt;">
                    <span class="sc top" @click="setOption('tecnologia', 'solar')"><span class="xmark" style="font-size: 8.6pt;">{{ fields.tecnologia === 'solar' ? 'X' : '' }}</span></span>
                    <span class="sc bot" style="border-top-width: 0.48pt;" @click="setOption('tecnologia', 'eolico')"></span>
                </span>
                <span class="txt clickable" style="left: 9.5pt; top: 468.2pt;" @click="setOption('tecnologia', 'eolico')">Eólico</span>

                <span class="txt clickable" style="left: 143.8pt; top: 454.6pt;" @click="setOption('tecnologia', 'biomasa')">Biomasa</span>
                <span class="stack" style="left: 193.08pt; top: 452.4pt; width: 48.12pt; height: 26.16pt; border-width: 0.84pt;">
                    <span class="sc top" @click="setOption('tecnologia', 'biomasa')"></span>
                    <span class="sc bot" style="border-top-width: 0.84pt;" @click="setOption('tecnologia', 'cogeneracion')"></span>
                </span>
                <span class="txt clickable" style="left: 137.2pt; top: 468.2pt;" @click="setOption('tecnologia', 'cogeneracion')">Cogeneración</span>

                <span class="txt clickable" style="left: 308pt; top: 457.7pt;" @click="setOption('tecnologia', 'otro')">Otro</span>
                <span class="cb" style="left: 354.84pt; top: 452.4pt; width: 54.84pt; height: 13.08pt; font-size: 8.6pt;" @click="setOption('tecnologia', 'otro')">{{ fields.tecnologia === 'otro' ? 'X' : '' }}</span>
                <span class="txt" style="left: 302.8pt; top: 470.8pt;">Especificar</span>
                <input v-model="fields.tecnologia_otro" class="fi" style="left: 334.04pt; top: 471.74pt; width: 151.6pt; height: 8.4pt;" />
                <span class="hln" style="left: 332.04pt; top: 480.24pt; width: 155.4pt; height: 1.08pt;"></span>

                <div class="fr" style="top: 485pt; height: 18.16pt;">
                    <span class="lbl" style="left: 1.8pt;">Cantidad de paneles y capacidad de ellos</span>
                    <span class="lbl" style="left: 130.6pt;">Combustible príncipal</span>
                    <span class="lbl" style="left: 337.3pt;">Combustible secundarío</span>
                    <input v-model="fields.num_unidades" class="fi" style="left: 3.6pt; top: 8.0pt; width: 117.6pt; height: 8.4pt;" />
                    <input v-model="fields.combustible_principal" class="fi" style="left: 128.4pt; top: 8.0pt; width: 199.56pt; height: 8.4pt;" />
                    <input v-model="fields.combustible_secundario" class="fi" style="left: 335.16pt; top: 8.0pt; width: 149.16pt; height: 8.4pt;" />
                    <span class="ln" style="left: 1.08pt; top: 17.08pt; width: 486.84pt; height: 1.08pt;"></span>
                    <span class="tk" style="left: 0; top: 7.48pt; height: 10.68pt;"></span>
                    <span class="tk" style="left: 124.8pt; top: 7.48pt; height: 10.68pt;"></span>
                    <span class="tk" style="left: 331.56pt; top: 7.48pt; height: 10.68pt;"></span>
                    <span class="tk" style="left: 486.84pt; top: 7.48pt; height: 10.68pt;"></span>
                </div>

                <!-- TABLA COORDENADAS UTM -->
                <span class="txt" style="left: 1.8pt; top: 502pt;">Coordenadas UTM</span>
                <span class="txt" style="left: 126.6pt; top: 502.4pt;">X</span>
                <span class="txt" style="left: 333.4pt; top: 502.4pt;">Y</span>

                <span class="ln" style="left: 1.08pt; top: 516.24pt; width: 486.84pt; height: 1.08pt;"></span>
                <span class="ln" style="left: 1.08pt; top: 524.4pt; width: 486.84pt; height: 1.08pt;"></span>
                <span class="ln" style="left: 1.08pt; top: 532.56pt; width: 486.84pt; height: 1.08pt;"></span>
                <span class="ln" style="left: 1.08pt; top: 540.72pt; width: 486.84pt; height: 1.08pt;"></span>
                <span class="ln" style="left: 1.08pt; top: 548.88pt; width: 486.84pt; height: 1.08pt;"></span>
                <span class="ln" style="left: 1.08pt; top: 557.04pt; width: 486.84pt; height: 1.08pt;"></span>

                <span class="tk" style="left: 0; top: 510.24pt; height: 47.88pt;"></span>
                <span class="tk" style="left: 124.8pt; top: 492.48pt; height: 65.64pt;"></span>
                <span class="tk" style="left: 331.56pt; top: 492.48pt; height: 65.64pt;"></span>
                <span class="tk" style="left: 486.84pt; top: 492.48pt; height: 65.64pt;"></span>

                <template v-for="i in 6" :key="i">
                    <span class="txt utm-num" :style="{ top: (i === 1 ? 507.9 : 516.1 + (i - 2) * 8.16) + 'pt' }">{{ i }}</span>
                    <input v-model="fields['utm_x' + i]" class="fi" :style="{ left: '128.4pt', top: (i === 1 ? 503.7 : 517.7 + (i - 2) * 8.16) + 'pt', width: '200.64pt', height: (i === 1 ? 11.5 : 7.1) + 'pt' }" />
                    <input v-model="fields['utm_y' + i]" class="fi" :style="{ left: '335.16pt', top: (i === 1 ? 503.7 : 517.7 + (i - 2) * 8.16) + 'pt', width: '149.16pt', height: (i === 1 ? 11.5 : 7.1) + 'pt' }" />
                </template>

                <!-- TEXTOS LEGALES -->
                <span class="hln" style="left: 2.8pt; top: 578.8pt; width: 292.5pt; height: 0.84pt;"></span>
                <input v-model="fields.titular_nombre" class="fi" style="left: 4.6pt; top: 570.8pt; width: 288pt; height: 8pt;" />
                <span class="legal-top" style="left: 285.6pt; top: 572.1pt;">(Representante Legal o El Solicitante) ( El Solicitante) certifica que la</span>

                <span class="legal-line" style="left: 1.8pt; top: 579.9pt;">Información proporcionada en la presente solicitud es apropiada,&nbsp; precisa y verídica.&nbsp; El solicitante acepta que los datos proporcionados sean utilizados para llevr a cabo los Estudios de Interconexión</span>
                <span class="legal-line" style="left: 1.8pt; top: 587.7pt;">para garantizar la confiabilidad del Sistema Eléctrico Nacional con la Interconexion de la Central Eléctrica del solicitante al amparo de la <i>Ley de la Industria Eléctrica y su Reglamento</i>,&nbsp; en caso de ser</span>
                <span class="legal-line" style="left: 1.8pt; top: 595.5pt;">requeridos.</span>
                <span class="legal-line" style="left: 412.4pt; top: 595.5pt;">El solicitante entiende que los</span>
                <span class="legal-line" style="left: 1.8pt; top: 603.3pt;">datos proporcionados, se añadiran a las bases de datos del suministrador cuando se firme un contrato de interconexión respectivo.</span>
                <span class="legal-line" style="left: 1.8pt; top: 611.1pt;">El solicitante deberá anexa a la presente solicitud, la información técnica requerida en el documento “Informacion Técnica Requerida para Centrales Eléctricas”</span>

                <!-- FIRMAS -->
                <span class="sign-box" style="left: 0; top: 634.56pt; width: 241.32pt; height: 40.8pt;"></span>
                <span class="sign-title" style="left: 0; top: 634.5pt; width: 241.32pt;">Firma de Conformidad</span>
                <span class="sign-foot" style="left: 0; top: 666.5pt; width: 241.32pt;">Solicitante</span>

                <span class="sign-box" style="left: 331.56pt; top: 642.72pt; width: 156.36pt; height: 68.28pt;"></span>
                <span class="cfe-text" style="left: 331.56pt; top: 692.7pt; width: 156.36pt;">sello y firma</span>
                <span class="cfe-text" style="left: 331.56pt; top: 701.6pt; width: 156.36pt;">Centro de Atención</span>

                <span class="txt" style="left: 33.2pt; top: 683.8pt;">Nombre</span>
                <span class="txt" style="left: 33.2pt; top: 693.7pt;">Cargo</span>
                <span class="txt" style="left: 33.2pt; top: 701.6pt;">Fecha</span>

                <span class="hln" style="left: 68.3pt; top: 691.9pt; width: 172.4pt; height: 0.84pt;"></span>
                <span class="hln" style="left: 68.3pt; top: 702.2pt; width: 172.4pt; height: 0.84pt;"></span>
                <span class="hln" style="left: 68.3pt; top: 710.8pt; width: 172.4pt; height: 0.84pt;"></span>

                <input v-model="fields.firma_nombre" class="fi" style="left: 70.1pt; top: 683.4pt; width: 168.8pt; height: 8.4pt;" />
                <input v-model="fields.firma_cargo" class="fi" style="left: 70.1pt; top: 693.7pt; width: 168.8pt; height: 8.4pt;" />
                <input v-model="fields.firma_fecha" class="fi" style="left: 70.1pt; top: 702.3pt; width: 168.8pt; height: 8.4pt;" />

                <span class="txt" style="left: 411.5pt; top: 710.9pt;">-</span>
                <span class="hln" style="left: 0.48pt; top: 719.28pt; width: 486.96pt; height: 1.08pt;"></span>
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
* {
    box-sizing: border-box;
}

.doc-page {
    background: #e5e7eb;
    min-height: 100vh;
    padding: 1rem 0;
    overflow-x: auto;
}

/* Hoja A4 idéntica al machote (210 × 297 mm) */
.doc-sheet {
    width: 210mm;
    height: 297mm;
    position: relative;
    margin: 0 auto;
    background: #fff;
    box-shadow: 0 0 0 1px #d1d5db, 0 12px 30px rgba(0, 0, 0, 0.12);
    color: #000;
    font-family: Calibri, Carlito, 'Segoe UI', Arial, sans-serif;
    font-size: 6pt;
    line-height: 1;
}

.paper {
    position: absolute;
    left: 50.4pt;
    top: 0;
    width: 487.92pt;
    height: 720.36pt;
}

/* Encabezado */
.hd-label,
.hd-line,
.hd-input {
    position: absolute;
}

.hd-label {
    font-weight: bold;
    line-height: 1;
    white-space: nowrap;
}

.hd-line {
    height: 1.08pt;
    background: #000;
}

.hd-input {
    height: 8.5pt;
    border: 0;
    outline: 0;
    background: transparent;
    color: #000;
    font-family: inherit;
    font-size: 6.5pt;
    padding: 0 1pt;
    text-transform: uppercase;
}

/* Bandas grises de sección */
.band {
    position: absolute;
    left: 0.96pt;
    width: 486.48pt;
    height: 9.96pt;
    background: #d9d9d9;
    color: #000;
    font-size: 7.6pt;
    font-weight: bold;
    line-height: 9.96pt;
    white-space: nowrap;
    print-color-adjust: exact;
    -webkit-print-color-adjust: exact;
}

.band-num,
.band-title {
    position: absolute;
    top: -1.9pt;
    line-height: 9.96pt;
    white-space: nowrap;
}

.band-num {
    left: 11.04pt;
}

.band-title {
    left: 28.5pt;
}

/* Filas de campos con línea inferior y divisiones verticales */
.fr {
    position: absolute;
    left: 0;
    width: 487.92pt;
}

.lbl {
    position: absolute;
    top: -0.7pt;
    font-size: 6pt;
    line-height: 1;
    white-space: nowrap;
}

.txt {
    position: absolute;
    font-size: 6pt;
    line-height: 1;
    white-space: nowrap;
}

.clickable {
    cursor: pointer;
}

.ln,
.hln {
    position: absolute;
    background: #000;
}

.tk {
    position: absolute;
    width: 1.08pt;
    background: #000;
}

.fi {
    position: absolute;
    border: 0;
    outline: 0;
    margin: 0;
    padding: 0;
    background: transparent;
    color: #000;
    font-family: inherit;
    font-size: 6.5pt;
    text-transform: uppercase;
}

.fi.lowercase-input {
    text-transform: none;
}

.fi:hover,
.fi:focus,
.hd-input:hover,
.hd-input:focus {
    background: #fefce8;
}

/* Casillas */
.cb {
    position: absolute;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #fff;
    border: 0.84pt solid #000;
    color: #000;
    font-weight: normal;
    line-height: 1;
    cursor: pointer;
    white-space: nowrap;
}

.stack {
    position: absolute;
    background: #fff;
    border-style: solid;
    border-color: #000;
}

.sc {
    position: absolute;
    left: 0;
    width: 100%;
    height: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
}

.sc.top {
    top: 0;
}

.sc.bot {
    bottom: 0;
    border-top-style: solid;
    border-top-color: #000;
}

/* Manifestación */
.mtext1,
.mtext2 {
    position: absolute;
    white-space: nowrap;
    line-height: 1;
}

.mtext1 {
    font-size: 5.4pt;
}

.mtext2 {
    font-size: 6pt;
}

/* Tabla UTM */
.utm-num {
    left: 0.9pt;
    width: 124.8pt;
    text-align: center;
}

/* Textos legales */
.legal-top,
.legal-line {
    position: absolute;
    font-size: 6pt;
    line-height: 1;
    white-space: nowrap;
}

.legal-line i {
    font-style: italic;
}

/* Firmas */
.sign-box {
    position: absolute;
    border: 1.08pt solid #000;
    background: transparent;
}

.sign-title,
.sign-foot,
.cfe-text {
    position: absolute;
    text-align: center;
    font-size: 6pt;
    line-height: 1;
    white-space: nowrap;
}

.cfe-text {
    line-height: 7.9pt;
}

/* Impresión: una sola hoja A4, sin márgenes adicionales */
@media print {
    @page {
        size: A4;
        margin: 0;
    }

    html,
    body {
        background: #fff !important;
        margin: 0 !important;
        padding: 0 !important;
    }

    .doc-page {
        padding: 0;
        background: #fff;
        min-height: auto !important;
        overflow: visible;
    }

    .doc-sheet {
        width: 210mm;
        height: 296.8mm;
        margin: 0;
        border: 0;
        box-shadow: none;
    }

    .print\:hidden {
        display: none !important;
    }

    .fi:hover,
    .fi:focus,
    .hd-input:hover,
    .hd-input:focus {
        background: transparent !important;
    }
}
</style>
