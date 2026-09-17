<script setup>
import { computed, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { createDiscreteApi } from 'naive-ui';

const props = defineProps({
    order: Object,
    panels: Array,
    microinverters: Array,
    linked: Boolean,
    generated_at: String,
});

const DEFAULT_MI_MODEL = 'MICROINVERSOR SOLAX POWER X1-MICRO 2500 G2';

const { notification } = createDiscreteApi(['notification']);

const isLinking = ref(false);
const linked = ref(props.linked);

// Cada microinversor conecta 4 paneles → una rama por cada grupo de 4
const groups = computed(() => {
    const result = [];
    const panels = props.panels || [];
    for (let i = 0; i < panels.length; i += 4) {
        result.push(panels.slice(i, i + 4));
    }
    return result;
});

// Si hay muchas ramas, se compacta la línea unifilar para que no se desborde
const busZoom = computed(() => {
    const width = groups.value.length * 132 + 460;
    return Math.min(1, 880 / width);
});

// --- ESTADO LOCAL EDITABLE (series de paneles + textos de microinversores) ---
const localSerials = ref([]);
const localMicros = ref([]);
const snapshotSerials = ref([]);
const snapshotMicros = ref([]);
const isSavingData = ref(false);

const syncLocals = () => {
    localSerials.value = (props.panels || []).map((p) => p.serial || '');
    localMicros.value = Array.from({ length: groups.value.length }, (_, i) => ({
        model: props.microinverters?.[i]?.model || DEFAULT_MI_MODEL,
        serial: props.microinverters?.[i]?.serial || `MI-${i + 1}`,
    }));
    snapshotSerials.value = [...localSerials.value];
    snapshotMicros.value = localMicros.value.map((m) => ({ ...m }));
};

syncLocals();

const hasDirty = computed(() => {
    const serialsDirty = localSerials.value.some(
        (s, i) => (s || '').trim() !== (snapshotSerials.value[i] || '').trim()
    );
    const microsDirty = localMicros.value.some((m, i) => {
        const snap = snapshotMicros.value[i] || {};
        return (
            (m.model || '').trim() !== (snap.model || '').trim() ||
            (m.serial || '').trim() !== (snap.serial || '').trim()
        );
    });
    return serialsDirty || microsDirty;
});

const saveDiagramData = async (silent = false) => {
    if (isSavingData.value || !hasDirty.value) return true;

    isSavingData.value = true;
    try {
        const { data } = await axios.patch(route('api.service-orders.update-diagram', props.order.id), {
            panel_serials: localSerials.value.map((s) => (s || '').trim()),
            microinverters: localMicros.value.map((m) => ({
                model: (m.model || '').trim(),
                serial: (m.serial || '').trim(),
            })),
        });

        snapshotSerials.value = Array.isArray(data.panel_serials) ? [...data.panel_serials] : [...localSerials.value];
        snapshotMicros.value = Array.isArray(data.microinverters)
            ? data.microinverters.map((m) => ({ ...m }))
            : localMicros.value.map((m) => ({ ...m }));

        if (!silent) {
            notification.success({
                title: 'Diagrama actualizado',
                content: 'Series y textos guardados correctamente.',
                duration: 2500
            });
        }
        return true;
    } catch (error) {
        if (!silent) {
            notification.error({
                title: 'Error',
                content: 'No se pudieron guardar los cambios del diagrama.',
                duration: 4000
            });
        }
        return false;
    } finally {
        isSavingData.value = false;
    }
};

const sheetRef = ref(null);

const print = () => {
    const sheet = sheetRef.value;

    // Ajusta la escala para que todo quepa en una sola hoja carta horizontal
    if (sheet && 'zoom' in sheet.style) {
        const printableHeight = 740; // alto imprimible aprox. (carta horizontal con margen de 10mm)
        const scale = Math.min(1, printableHeight / Math.max(1, sheet.scrollHeight));
        sheet.style.zoom = scale;

        const reset = () => {
            sheet.style.zoom = '';
            window.removeEventListener('afterprint', reset);
        };
        window.addEventListener('afterprint', reset);
        window.print();
        setTimeout(reset, 600);
        return;
    }

    window.print();
};

/**
 * Vincula el diagrama a la orden: guarda los cambios pendientes y pide al
 * servidor que genere el PDF (DomPDF) y lo adjunte a la orden.
 */
const linkToOrder = async () => {
    if (isLinking.value) return;
    isLinking.value = true;
    try {
        // Guarda primero cualquier cambio pendiente para que el PDF quede actualizado
        const saved = await saveDiagramData(true);
        if (!saved) {
            notification.error({
                title: 'Error',
                content: 'Guarda primero los cambios para poder vincular el diagrama.',
                duration: 4000
            });
            return;
        }

        await axios.post(route('service-orders.diagram-unifilar.link', props.order.id));
        linked.value = true;
        notification.success({
            title: 'Diagrama vinculado',
            content: 'El PDF se guardó como documento de la orden (pestaña Evidencias y Documentos).',
            duration: 6000
        });
    } catch (error) {
        notification.error({
            title: 'Error',
            content: 'No se pudo vincular el diagrama a la orden de servicio.',
            duration: 5000
        });
    } finally {
        isLinking.value = false;
    }
};
</script>

<template>
    <Head :title="`Diagrama Unifilar · Orden #${order.id}`" />
    <div class="diagram-page">
        <div ref="sheetRef" class="diagram-sheet">
            <!-- ENCABEZADO TÉCNICO (SIMBOLOGÍA) -->
        <div class="tech-header border-b border-gray-400 pb-3 mb-4">
            <div class="grid-legend">
                <div class="legend-item">
                    <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
                        <path d="M12 4v10M6 14h12M8 17h8M10 20h4" />
                    </svg>
                    <span>DE PUESTA A TIERRA</span>
                </div>
                
                <div class="legend-item">
                    <div class="circle-symbol">
                        <span>G</span>
                        <span class="sub">kVA</span>
                    </div>
                    <span>GENERADOR</span>
                </div>

                <div class="legend-item">
                    <span class="symbol-text text-xl">~</span>
                    <span>SIMBOLO CORRIENTE ALTERNA (C.A.)</span>
                </div>

                <div class="legend-item">
                    <span class="symbol-text text-xl font-bold">=</span>
                    <span>SIMBOLO CORRIENTE CONTINUA (C.C.)</span>
                </div>
            </div>

            <div class="grid-legend mt-3">
                <div class="legend-item">
                    <div class="micro-symbol-box border border-black p-1 text-[8px] text-center w-12 flex flex-col justify-between h-12 relative">
                        <span class="absolute top-1 left-1">~</span>
                        <div class="w-full h-px bg-black my-1 diagonal-line"></div>
                        <span class="absolute bottom-1 right-1">=</span>
                    </div>
                    <span class="ml-2 w-32">MICRO INVERSOR DE<br>SOLAX POWER X1 - MICRO</span>
                </div>

                <div class="legend-item justify-start pl-8">
                    <div class="w-6 h-6 bg-green-500"></div>
                    <span class="ml-2">CAJA DE CONEXION</span>
                </div>
            </div>
        </div>

        <!-- TÍTULO PRINCIPAL -->
        <h2 class="tracking-[0.3em] font-bold text-gray-800 text-base mb-4">GENERADOR FOTOVOLTAICO</h2>

        <div class="flex gap-6 mb-8 items-start">

            <!-- CAJA DE PANELES (MÓDULOS CONECTADOS POR GRUPOS DE 4 A CADA MI) -->
            <div class="border border-black p-2 flex-1 min-w-[300px]">
                <div class="text-[10px] uppercase mb-2">{{ props.panels?.length || 0 }} MÓDULOS SOLARES · {{ groups.length }} RAMA(S) DE 4</div>
                <div v-if="panels.length" class="strings">
                    <div v-for="(group, idx) in groups" :key="idx" class="string">
                        <div class="string-panels">
                            <div v-for="panel in group" :key="panel.number" class="panel-drawing" :title="'PV-' + panel.number">
                                <svg viewBox="0 0 100 100" class="panel-svg">
                                    <rect x="2" y="2" width="96" height="96" />
                                    <polyline points="0,0 50,50 100,0" />
                                    <polyline points="0,100 50,50 100,100" />
                                </svg>
                                <span class="panel-num">{{ panel.number }}</span>
                            </div>
                        </div>
                        <!-- Conexión del grupo de módulos a su microinversor -->
                        <div class="string-drop"></div>
                        <div class="string-label">MI-{{ idx + 1 }}</div>
                    </div>
                </div>
                <div v-if="!panels.length" class="text-[10px] text-gray-500 italic py-2 leading-snug">
                    Registra la cantidad de unidades en "Detalles Operativos" para capturar las series.
                </div>
                <div class="text-[10px] mt-2">Modelo: {{ order.system_type || 'ESTÁNDAR' }}</div>
            </div>

            <!-- CAJA DE NÚMEROS DE SERIE (EDITABLE, AGRUPADA POR MI) -->
            <div class="border border-black p-2 w-[340px] shrink-0">
                <div class="text-[10px] tracking-[0.2em] mb-2 text-center">S N P A N E L E S &nbsp; S O L A R E S</div>
                <div v-if="panels.length" class="text-[10px]">
                    <div v-for="(group, idx) in groups" :key="idx" class="mb-2.5">
                        <div class="text-[9px] font-bold text-gray-600 border-b border-gray-300 mb-1 pb-0.5">
                            MI-{{ idx + 1 }} · PV {{ group[0].number }} – {{ group[group.length - 1].number }}
                        </div>
                        <div class="grid grid-cols-2 gap-x-4 gap-y-1">
                            <div v-for="panel in group" :key="panel.number" class="flex items-center">
                                <span class="w-5 shrink-0">{{ panel.number }}.</span>
                                <input
                                    class="diagram-input serial-input font-mono"
                                    v-model="localSerials[panel.number - 1]"
                                    placeholder="—"
                                    @blur="saveDiagramData()"
                                    @keyup.enter="saveDiagramData()"
                                />
                            </div>
                        </div>
                    </div>
                </div>
                <div v-else class="text-[10px] text-gray-500 italic py-2 text-center">
                    Sin paneles registrados.
                </div>
                <div class="text-[9px] text-gray-400 mt-2 border-t border-gray-200 pt-1.5 print:hidden">
                    Escribe el número de serie de cada panel; se guarda automáticamente.
                </div>
            </div>
        </div>

        <!-- LÍNEA UNIFILAR (INVERSORES Y PROTECCIONES) -->
        <div class="unifilar-bus relative pt-8" :style="{ zoom: busZoom }">
            <!-- Línea de bus horizontal principal -->
            <div class="absolute top-[76px] left-[40px] right-0 h-px bg-black z-0"></div>

            <div class="flex items-start flex-nowrap relative z-10 w-full">

                <!-- MICROINVERSORES ITERADOS (TEXTOS EDITABLES) -->
                <div v-for="(group, idx) in groups" :key="idx" class="flex flex-col items-center mr-6">
                    <span class="text-xl mb-1 bg-white px-1">=</span>
                    <div class="border border-black p-1 text-center bg-white w-28 relative">
                        <textarea
                            class="diagram-input mi-model-input"
                            rows="3"
                            v-model="localMicros[idx].model"
                            @blur="saveDiagramData()"
                            spellcheck="false"
                        ></textarea>
                        <!-- Pines de conexión del MI -->
                        <div class="flex justify-center gap-1 mt-1 mb-0.5">
                            <div class="w-2 h-4 border border-black"></div>
                            <div class="w-2 h-4 border border-black"></div>
                            <div class="w-2 h-4 border border-black"></div>
                        </div>
                    </div>
                    <div class="text-[8px] mt-1 flex items-center gap-1">
                        <span class="shrink-0">SN:</span>
                        <input
                            class="diagram-input mi-serial-input"
                            v-model="localMicros[idx].serial"
                            @blur="saveDiagramData()"
                            @keyup.enter="saveDiagramData()"
                        />
                    </div>
                    <div class="text-[7px] text-center w-24">UNIDAD DE CONVERTIDOR DE ENERGIA</div>

                    <!-- Conexión a la línea principal -->
                    <div class="w-[1px] h-6 bg-black relative">
                        <span class="absolute top-1/2 left-1 bg-white text-xs px-0.5">~</span>
                    </div>
                </div>

                <!-- CAJA DE CONEXIÓN Y PUESTA A TIERRA -->
                <div class="flex flex-col items-center mr-4 mt-[35px]">
                    <div class="w-[1px] h-[45px] bg-black"></div>
                    <div class="w-6 h-6 bg-green-500 mb-1 z-10"></div>
                    <svg class="h-6 w-6 mt-6 stroke-black fill-none stroke-[1px]" viewBox="0 0 24 24">
                        <path d="M12 0v10M6 10h12M8 13h8M10 16h4" />
                    </svg>
                </div>

                <!-- UNIDAD DE CONVERTIDOR (G) -->
                <div class="flex flex-col items-center mr-6 mt-[65px] bg-white px-2 relative top-[-12px]">
                    <span class="text-xs absolute -left-3 top-2">~</span>
                    <div class="w-10 h-10 rounded-full border border-black flex flex-col items-center justify-center bg-white z-10">
                        <span class="text-[10px] font-bold">G</span>
                        <span class="text-[7px]">27</span>
                    </div>
                    <span class="text-[8px] mt-1">kVA</span>
                </div>

                <!-- INTERRUPTOR TERMOMAGNÉTICO -->
                <div class="flex flex-col items-center mr-6 mt-[50px] bg-white relative top-[-10px] z-10">
                    <span class="text-xs absolute -left-4 top-4">~</span>
                    <div class="border border-black p-1 text-[7px] text-center w-20 h-14 flex flex-col justify-between">
                        <span>INTERRUPTOR<br>TERMOMAGNETICO</span>
                        <div class="w-4 h-4 border border-black mx-auto relative">
                            <div class="absolute w-[1px] h-3 bg-black transform rotate-45 top-0 left-1"></div>
                        </div>
                        <span>32A</span>
                    </div>
                </div>

                <!-- SUPRESOR DE PICOS -->
                <div class="flex flex-col items-center mr-6 mt-[50px] bg-white relative top-[-10px] z-10">
                    <span class="text-xs absolute -left-4 top-4">~</span>
                    <div class="border border-black p-1 text-[7px] text-center w-20 h-14 flex flex-col justify-between">
                        <span>SUPRESOR DE<br>PICOS</span>
                        <div class="w-4 h-4 border border-black mx-auto">
                            <div class="w-full h-[1px] bg-black mt-1"></div>
                        </div>
                        <span>275V</span>
                    </div>
                    <!-- Puesta a tierra del supresor -->
                    <div class="w-[1px] h-6 bg-black"></div>
                    <svg class="h-5 w-5 stroke-black fill-none stroke-[1px]" viewBox="0 0 24 24">
                        <path d="M12 0v10M6 10h12M8 13h8M10 16h4" />
                    </svg>
                </div>

                <!-- CENTRO DE CARGA -->
                <div class="flex flex-col items-center mr-6 mt-[50px] bg-white relative top-[-10px] z-10">
                    <span class="text-xs absolute -left-4 top-4">~</span>
                    <div class="border border-black p-1 text-[7px] text-center w-16 h-14 flex flex-col items-center">
                        <span class="mb-1">CENTRO DE<br>CARGA</span>
                        <div class="w-6 h-6 border border-black grid grid-cols-2">
                            <div class="border-r border-black"></div>
                            <div></div>
                        </div>
                    </div>
                </div>

                <!-- CFE (MEDIDOR) -->
                <div class="flex flex-col items-center mt-[50px] bg-white relative top-[-10px] z-10">
                    <span class="text-xs absolute -left-4 top-4">~</span>
                    <div class="border border-black p-1 text-[7px] text-center w-16 h-14 flex flex-col justify-center items-center">
                        <div class="w-8 h-8 border border-black rounded-full flex justify-center items-center mb-1">
                            <div class="w-4 h-2 border border-black"></div>
                        </div>
                        <span>CFE</span>
                    </div>
                    <!-- Puesta a tierra de CFE -->
                    <div class="w-[1px] h-6 bg-black"></div>
                    <svg class="h-5 w-5 stroke-black fill-none stroke-[1px]" viewBox="0 0 24 24">
                        <path d="M12 0v10M6 10h12M8 13h8M10 16h4" />
                    </svg>
                </div>

            </div>
        </div>

        <!-- Datos de la orden (Pie de página discreto) -->
        <!-- <div class="mt-10 border-t border-gray-300 pt-3 flex justify-between text-[9px] text-gray-500 font-mono">
            <div>CLIENTE: {{ order.client || 'N/A' }} | DIRECCIÓN: {{ order.installation_address || 'N/A' }}</div>
            <div>ORDEN #{{ order.id }} | FECHA: {{ generated_at }}</div>
        </div> -->
        </div>
    </div>

    <!-- BOTONES FLOTANTES (NO IMPRIMIBLES) -->
    <div class="fixed bottom-8 right-8 print:hidden flex flex-col gap-3 items-end z-50">
        <button
            v-if="hasDirty"
            @click="saveDiagramData()"
            :disabled="isSavingData"
            class="bg-amber-500 text-white px-5 py-3 rounded-full shadow-lg hover:bg-amber-400 transition-colors flex items-center gap-2 font-bold text-sm disabled:opacity-50"
        >
            <span v-if="!isSavingData">Guardar cambios</span>
            <span v-else>Guardando...</span>
        </button>
        <button
            v-if="!linked"
            @click="linkToOrder"
            :disabled="isLinking"
            class="bg-indigo-600 text-white px-5 py-3 rounded-full shadow-lg hover:bg-indigo-500 transition-colors flex items-center gap-2 font-bold text-sm disabled:opacity-50"
        >
            <svg v-if="!isLinking" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                <path d="M11 3a1 1 0 10-2 0v5H4a1 1 0 100 2h5v5a1 1 0 102 0v-5h5a1 1 0 100-2h-5V3z" />
            </svg>
            <span v-if="!isLinking">Vincular a la Orden de Servicio</span>
            <span v-else>Generando PDF...</span>
        </button>
        <button
            v-else-if="hasDirty"
            @click="linkToOrder"
            :disabled="isLinking"
            class="bg-indigo-600 text-white px-5 py-3 rounded-full shadow-lg hover:bg-indigo-500 transition-colors flex items-center gap-2 font-bold text-sm disabled:opacity-50"
        >
            <svg v-if="!isLinking" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M4 2a1 1 0 011 1v2.101a7.002 7.002 0 0111.601 2.566 1 1 0 11-1.885.666A5.002 5.002 0 005.999 7H9a1 1 0 010 2H4a1 1 0 01-1-1V3a1 1 0 011-1zm.008 9.057a1 1 0 011.276.61A5.002 5.002 0 0014.001 13H11a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0v-2.101a7.002 7.002 0 01-11.601-2.566 1 1 0 01.61-1.276z" clip-rule="evenodd" />
            </svg>
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
</template>

<style>
/* Fondo de pantalla y hoja carta */
.diagram-page {
    background: #f3f4f6;
    min-height: 100vh;
    padding: 1rem 0;
}

.diagram-sheet {
    width: 279mm;
    min-height: 216mm;
    box-sizing: border-box;
    background: #fff;
    border: 1px solid #e5e7eb;
    margin: 0 auto;
    padding: 9mm 11mm;
    position: relative;
}

/* --- ENCABEZADO TÉCNICO (SIMBOLOGÍA) --- */
.grid-legend {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 6px;
}

.legend-item {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 9px;
    font-weight: 600;
    color: #111827;
    line-height: 1.25;
}

.legend-item svg {
    color: #000;
    flex-shrink: 0;
}

.circle-symbol {
    position: relative;
    width: 30px;
    height: 30px;
    border: 1px solid #000;
    border-radius: 50%;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    font-size: 10px;
    font-weight: bold;
    line-height: 1;
    flex-shrink: 0;
}

.circle-symbol .sub {
    font-size: 7px;
    font-weight: normal;
}

.symbol-text {
    font-size: 20px;
    font-weight: bold;
    line-height: 1;
    flex-shrink: 0;
}

.diagonal-line {
    transform: rotate(45deg);
}

/* --- PANELES SOLARES AGRUPADOS POR MICROINVERSOR --- */
.strings {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.string {
    display: flex;
    flex-direction: column;
    align-items: center;
}

.string-panels {
    display: grid;
    grid-template-columns: repeat(2, 34px);
    grid-template-rows: repeat(2, 34px);
    gap: 2px;
}

.string-drop {
    width: 1px;
    height: 10px;
    background: #000;
    margin-top: 2px;
}

.string-label {
    font-size: 9px;
    font-weight: bold;
    border: 1px solid #000;
    background: #eef2ff;
    padding: 0 6px;
    margin-top: 1px;
}

.panel-drawing {
    position: relative;
    width: 34px;
    height: 34px;
}

.panel-drawing .panel-svg {
    width: 100%;
    height: 100%;
    display: block;
    stroke: #000;
    fill: none;
    stroke-width: 2px;
}

.panel-num {
    position: absolute;
    right: 1px;
    bottom: 1px;
    font-size: 7px;
    line-height: 1;
    background: #fff;
    padding: 0 1px;
    color: #111827;
}

/* --- INPUTS EDITABLES DENTRO DEL DIAGRAMA --- */
.diagram-input {
    border: none;
    outline: none;
    background: transparent;
    font: inherit;
    color: inherit;
    text-align: inherit;
    padding: 0;
    margin: 0;
    width: 100%;
    min-width: 0;
    border-radius: 0;
    box-shadow: none;
}

.diagram-input:hover,
.diagram-input:focus {
    background: #fefce8;
    box-shadow: 0 0 0 1px #f59e0b;
    border-radius: 2px;
}

.serial-input {
    font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
    font-size: 10px;
    padding: 1px 2px;
}

.mi-model-input {
    font-size: 8px;
    line-height: 1.25;
    text-align: center;
    resize: none;
    overflow: hidden;
}

.mi-serial-input {
    font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
    font-size: 8px;
    text-align: center;
    width: 100px;
}

/* --- IMPRESIÓN: UNA SOLA HOJA CARTA HORIZONTAL CON MARGEN --- */
@media print {
    @page {
        size: letter landscape;
        margin: 10mm;
    }

    body {
        background: #fff !important;
    }

    .diagram-page {
        padding: 0;
        background: #fff;
    }

    .diagram-sheet {
        width: auto !important;
        min-height: auto;
        border: none;
        margin: 0px;
        padding: 0;
    }

    .diagram-input {
        border: none !important;
        background: transparent !important;
        box-shadow: none !important;
        resize: none;
        overflow: visible !important;
    }

    .print\:hidden {
        display: none !important;
    }
}
</style>
