<script setup>
import { ref, watch, h } from 'vue';
import { usePermissions } from '@/Composables/usePermissions';
import { Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import CompleteVisitModal from '@/Components/MyComponents/CompleteVisitModal.vue';
import { 
    NButton, NDataTable, NInput, NSpace, NTag, NAvatar, NIcon, NEmpty, NPagination, 
    createDiscreteApi, NTooltip, NSelect, NDatePicker, NModal, NCard, NForm, NFormItem, NDropdown, NSwitch
} from 'naive-ui';
import { 
    SearchOutline, AddOutline, EyeOutline, CreateOutline, TrashOutline, 
    CalendarOutline, LocationOutline, PersonOutline, BusinessOutline,
    ChevronDownOutline, HardwareChipOutline, TimeOutline, CloseCircleOutline,
    OpenOutline, InformationCircleOutline, CheckmarkCircleOutline, CheckmarkDoneOutline,
    EllipsisHorizontal
} from '@vicons/ionicons5';

const props = defineProps({
    visits: Object, // Paginado
    filters: Object,
    statuses: Array,
    system_types: Array,
    municipalities: Array,
    states: Array,
});

const { hasPermission } = usePermissions();
const { notification, dialog } = createDiscreteApi(['notification', 'dialog']);

// Transformar array de fechas de la URL (Strings) a Numbers (Timestamps para Naive UI)
const parseDateRange = (range) => {
    if (!range || !Array.isArray(range)) return null;
    return [Number(range[0]), Number(range[1])];
};

// Lógica de búsqueda y filtrado
const search = ref(props.filters.search || '');
const statusFilter = ref(props.filters.status || null);
const municipalityFilter = ref(props.filters.municipality || null);
const stateFilter = ref(props.filters.state || null);
const systemTypeFilter = ref(props.filters.system_type || null);
const dateRangeFilter = ref(parseDateRange(props.filters.date_range));
const showCompletedFilter = ref(props.filters.show_completed === '1' || props.filters.show_completed === true); 

let searchTimeout;

const applyFilters = () => {
    router.get(route('technical-visits.index'), { 
        search: search.value,
        status: statusFilter.value,
        municipality: municipalityFilter.value,
        state: stateFilter.value,
        system_type: systemTypeFilter.value,
        date_range: dateRangeFilter.value,
        show_completed: showCompletedFilter.value ? '1' : '0',
    }, { preserveState: true, replace: true });
};

watch(search, (value) => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(applyFilters, 300);
});

watch([statusFilter, municipalityFilter, stateFilter, systemTypeFilter, dateRangeFilter, showCompletedFilter], applyFilters);

// Acciones de Navegación
const goToEdit = (id) => router.visit(route('technical-visits.edit', id));
const goToShow = (id) => router.visit(route('technical-visits.show', id));

// Modal para Reprogramar
const showRescheduleModal = ref(false);
const rescheduleForm = useForm({
    scheduled_at: null,
    reschedule_reason: '',
});
const currentVisitId = ref(null);

const openRescheduleModal = (visit) => {
    currentVisitId.value = visit.id;
    rescheduleForm.scheduled_at = null;
    rescheduleForm.reschedule_reason = '';
    showRescheduleModal.value = true;
};

const submitReschedule = () => {
    if (!rescheduleForm.scheduled_at) {
        notification.warning({ title: 'Atención', content: 'Debes seleccionar una nueva fecha y hora.', duration: 3000 });
        return;
    }
    
    const formattedDate = new Date(rescheduleForm.scheduled_at).toISOString().slice(0, 19).replace('T', ' ');

    router.patch(route('technical-visits.quick-update', currentVisitId.value), {
        action: 'reschedule',
        scheduled_at: formattedDate,
        reschedule_reason: rescheduleForm.reschedule_reason || '',
    }, {
        preserveScroll: true,
        onSuccess: () => {
            showRescheduleModal.value = false;
            notification.success({ title: 'Visita Reprogramada', content: 'La fecha se ha actualizado correctamente.', duration: 3000 });
        },
    });
};

// Modal para Rechazar
const showRejectModal = ref(false);
const rejectForm = ref({ rejection_reason: '' });
const currentRejectVisitId = ref(null);

const openRejectModal = (visit) => {
    currentRejectVisitId.value = visit.id;
    rejectForm.value.rejection_reason = '';
    showRejectModal.value = true;
};

const submitReject = () => {
    if (!rejectForm.value.rejection_reason.trim()) {
        notification.warning({ title: 'Atención', content: 'Debes escribir el motivo del rechazo.', duration: 3000 });
        return;
    }

    router.patch(route('technical-visits.quick-update', currentRejectVisitId.value), {
        action: 'reject',
        rejection_reason: rejectForm.value.rejection_reason,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            showRejectModal.value = false;
            notification.success({ title: 'Visita Rechazada', content: 'La visita ha sido marcada como rechazada.', duration: 3000 });
        },
    });
};

// Acciones rápidas: Aceptar y Terminar
const quickAction = (visitId, action) => {
    if (action === 'accept') {
        router.patch(route('technical-visits.quick-update', visitId), { action: 'accept' }, {
            preserveScroll: true,
            onSuccess: () => notification.success({ title: 'Visita Aceptada', content: 'La visita ha sido marcada como aceptada.', duration: 3000 }),
        });
    } else if (action === 'complete') {
        // Ahora abre el modal de completado en lugar de terminar directo
        openCompleteModal(visitId);
    }
};

// --- MODAL DE COMPLETADO (Convertir a Cliente + Orden de Servicio) ---
const showCompleteModal = ref(false);
const completingVisitId = ref(null);
const completingSystemType = ref(null);

const openCompleteModal = (visitId) => {
    completingVisitId.value = visitId;
    const visitData = props.visits.data.find(v => v.id === visitId);
    completingSystemType.value = visitData?.system_of_interest || null;
    showCompleteModal.value = true;
};

// --- EDICIÓN INLINE DE SISTEMA DE INTERÉS ---

const updateSystemType = (visitId, newValue) => {
    router.patch(route('technical-visits.update-system-type', visitId), {
        system_of_interest: newValue,
    }, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => notification.success({ title: 'Actualizado', content: 'Sistema de interés actualizado.', duration: 2000 }),
    });
};

// Opciones para el select inline de sistema (columna de la tabla)
const inlineSystemOptions = ['Interconectado', 'Autónomo', 'Back-up', 'Bombeo'].map(s => ({ label: s, value: s }));

const confirmDelete = (visit) => {
    dialog.warning({
        title: 'Eliminar Visita Técnica',
        content: `¿Estás seguro de eliminar la visita #${visit.id} de ${visit.prospect_name}? Esta acción no se puede deshacer.`,
        positiveText: 'Sí, Eliminar',
        negativeText: 'Cancelar',
        onPositiveClick: () => {
            router.delete(route('technical-visits.destroy', visit.id), {
                onSuccess: () => notification.success({ title: 'Éxito', content: 'Visita técnica eliminada.', duration: 3000 }),
            });
        }
    });
};

// Utilidades
const getStatusColor = (status) => {
    const map = {
        'Pendiente': 'warning',
        'Reprogramada': 'info',
        'Aceptada': 'success',
        'Terminada': 'default',
        'Rechazada': 'error'
    };
    return map[status] || 'default';
};

// Opciones para Selects
const statusOptions = props.statuses.map(s => ({ label: s, value: s }));
const municipalityOptions = props.municipalities.map(m => ({ label: m, value: m }));
const stateOptions = props.states.map(s => ({ label: s, value: s }));
const systemTypeOptions = props.system_types.map(s => ({ label: s, value: s }));

// Configuración de Columnas Desktop
const createColumns = () => {
    return [
        {
            title: 'Folio',
            key: 'id',
            width: 80,
            render(row) {
                return h('span', { class: 'font-mono text-gray-500 font-bold' }, `#${row.id}`);
            }
        },
        {
            title: 'Prospecto / Cliente',
            key: 'prospect_name',
            width: 250,
            render(row) {
                return h('div', { class: 'flex flex-col' }, [
                    h('span', { class: 'font-bold text-gray-800 text-sm flex items-center gap-1' }, [
                        h(NIcon, { component: row.client ? BusinessOutline : PersonOutline, class: row.client ? 'text-blue-500' : 'text-gray-400' }),
                        row.prospect_name
                    ]),
                    row.client ? h('span', { class: 'text-xs text-blue-500 mt-1 font-medium' }, 'Cliente Existente') : h('span', { class: 'text-xs text-orange-500 mt-1 font-medium' }, 'Nuevo Prospecto')
                ]);
            }
        },
        {
            title: 'Programación',
            key: 'scheduled_at',
            width: 180,
            render(row) {
                return h('div', { class: 'flex items-center gap-2' }, [
                    h(NIcon, { component: CalendarOutline, class: 'text-indigo-500' }),
                    h('div', { class: 'flex flex-col text-sm font-medium text-gray-700' }, [
                        h('span', row.scheduled_at?.split(' ')[0]),
                        h('span', { class: 'text-xs text-gray-400' }, row.scheduled_at?.split(' ')[1] || 'Sin hora')
                    ])
                ]);
            }
        },
        {
            title: 'Ubicación',
            key: 'location',
            width: 200,
            render(row) {
                const elements = [
                    h('div', { class: 'flex items-start gap-1 text-sm text-gray-600' }, [
                        h(NIcon, { component: LocationOutline, class: 'mt-0.5 text-gray-400' }),
                        h('span', { class: 'line-clamp-2' }, `${row.municipality || 'N/A'}, ${row.state || 'N/A'}`)
                    ])
                ];
                if (row.google_maps_link) {
                    elements.push(
                        h('a', { 
                            href: row.google_maps_link, 
                            target: '_blank', 
                            class: 'text-xs text-blue-500 hover:text-blue-700 flex items-center gap-1 mt-1',
                            onClick: (e) => e.stopPropagation()
                        }, [
                            h(NIcon, { component: OpenOutline, size: 12 }),
                            'Ver en Google Maps'
                        ])
                    );
                }
                return h('div', { class: 'flex flex-col gap-1' }, elements);
            }
        },
        {
            title: 'Escalera Larga',
            key: 'requires_long_ladder',
            width: 120,
            align: 'center',
            render(row) {
                const require = row.requires_long_ladder;
                return h(NTag, { 
                    type: require ? 'warning' : 'default', 
                    size: 'small', 
                    round: true,
                    bordered: false 
                }, { default: () => require ? 'Sí' : 'No' });
            }
        },
        {
            title: 'Estatus',
            key: 'status',
            width: 130, 
            render(row) {
                const elements = [
                    h(NTag, { 
                        type: getStatusColor(row.status), 
                        size: 'small', 
                        bordered: false, 
                        round: true
                    }, { default: () => row.status })
                ];
                
                if (row.status === 'Reprogramada' && row.reschedule_reason) {
                    elements.push(
                        h('div', { class: 'text-xs text-gray-500 mt-1 flex items-start gap-1' }, [
                            h(NIcon, { component: InformationCircleOutline, size: 12, class: 'mt-0.5 text-blue-400' }),
                            h('span', { class: 'line-clamp-2' }, row.reschedule_reason)
                        ])
                    );
                }
                if (row.status === 'Rechazada' && row.rejection_reason) {
                    elements.push(
                        h('div', { class: 'text-xs text-gray-500 mt-1 flex items-start gap-1' }, [
                            h(NIcon, { component: InformationCircleOutline, size: 12, class: 'mt-0.5 text-red-400' }),
                            h('span', { class: 'line-clamp-2' }, row.rejection_reason)
                        ])
                    );
                }
                
                return h('div', { class: 'flex flex-col' }, elements);
            }
        },
        {
            title: 'Sistema',
            key: 'system_of_interest',
            width: 160,
            render(row) {
                // Visitas terminadas: solo mostrar tag, sin edición
                if (row.status === 'Terminada') {
                    if (!row.system_of_interest) {
                        return h('span', { class: 'text-xs text-gray-400' }, '—');
                    }
                    return h(NTag, {
                        type: 'info',
                        size: 'small',
                        round: true,
                        bordered: false,
                    }, { default: () => row.system_of_interest });
                }

                const current = row.system_of_interest || 'Sin definir';
                return h(NSelect, {
                    value: current,
                    options: inlineSystemOptions,
                    size: 'tiny',
                    placeholder: 'Seleccionar',
                    clearable: true,
                    consistentMenuWidth: false,
                    onClick: (e) => e.stopPropagation(),
                    onUpdateValue: (value) => {
                        updateSystemType(row.id, value);
                    },
                });
            }
        },
        {
            title: '',
            key: 'actions',
            width: 200,
            render(row) {
                const canEdit = hasPermission('technical_visits.edit');
                const canDelete = hasPermission('technical_visits.delete');

                // Opciones del dropdown de cambio de estatus
                const statusOptions = [
                    { label: 'Reprogramar', key: 'reschedule', icon: () => h(NIcon, null, { default: () => h(TimeOutline) }) },
                    { label: 'Aceptar', key: 'accept', icon: () => h(NIcon, null, { default: () => h(CheckmarkCircleOutline) }) },
                    { label: 'Terminar', key: 'complete', icon: () => h(NIcon, null, { default: () => h(CheckmarkDoneOutline) }) },
                    { label: 'Rechazar', key: 'reject', icon: () => h(NIcon, null, { default: () => h(CloseCircleOutline) }) },
                ];

                const isTerminated = row.status === 'Terminada';

                const handleStatusSelect = (key, visitRow) => {
                    if (key === 'reschedule') { openRescheduleModal(visitRow); }
                    else if (key === 'reject') { openRejectModal(visitRow); }
                    else if (key === 'accept' || key === 'complete') { quickAction(visitRow.id, key); }
                };

                return h(NSpace, { justify: 'end', wrap: false }, () => [
                    h(NTooltip, { trigger: 'hover' }, {
                        trigger: () => h(NButton, {
                            circle: true, size: 'small', quaternary: true, type: 'info',
                            onClick: (e) => { e.stopPropagation(); goToShow(row.id); }
                        }, { icon: () => h(NIcon, null, { default: () => h(EyeOutline) }) }),
                        default: () => 'Ver Detalles'
                    }),
                    canEdit ? h(NTooltip, { trigger: 'hover' }, {
                        trigger: () => h(NButton, {
                            circle: true, size: 'small', quaternary: true, type: 'warning',
                            onClick: (e) => { e.stopPropagation(); goToEdit(row.id); }
                        }, { icon: () => h(NIcon, null, { default: () => h(CreateOutline) }) }),
                        default: () => 'Editar'
                    }) : null,
                    // Ocultar dropdown de estatus para visitas terminadas
                    canEdit && !isTerminated ? h(NDropdown, {
                        trigger: 'click',
                        options: statusOptions,
                        onSelect: (key) => { handleStatusSelect(key, row); },
                        onClick: (e) => e.stopPropagation(),
                    }, {
                        default: () => h(NButton, {
                            circle: true, size: 'small', quaternary: true, type: 'default',
                            onClick: (e) => e.stopPropagation(),
                        }, { icon: () => h(NIcon, null, { default: () => h(EllipsisHorizontal) }) }),
                    }) : null,
                    canDelete ? h(NTooltip, { trigger: 'hover' }, {
                        trigger: () => h(NButton, {
                            circle: true, size: 'small', quaternary: true, type: 'error',
                            onClick: (e) => { e.stopPropagation(); confirmDelete(row); }
                        }, { icon: () => h(NIcon, null, { default: () => h(TrashOutline) }) }),
                        default: () => 'Eliminar'
                    }) : null
                ]);
            }
        }
    ];
};

const columns = createColumns();

const handlePageChange = (page) => {
    router.get(route('technical-visits.index'), { 
        page, 
        search: search.value, 
        status: statusFilter.value,
        municipality: municipalityFilter.value,
        state: stateFilter.value,
        system_type: systemTypeFilter.value,
        date_range: dateRangeFilter.value,
        show_completed: showCompletedFilter.value ? '1' : '0',
    }, { preserveState: true });
};

const rowProps = (row) => ({
    style: 'cursor: pointer;',
    onClick: () => goToShow(row.id)
});

</script>

<template>
    <AppLayout title="Visitas Técnicas">
        <template #header>
            <div class="flex flex-col md:flex-row justify-between items-center gap-4">
                <div>
                    <h2 class="font-semibold text-2xl text-gray-800 leading-tight">
                        Visitas Técnicas
                    </h2>
                    <p class="text-sm text-gray-500 mt-1">Gestión de levantamientos y prospectos</p>
                </div>
                <Link v-if="hasPermission('technical_visits.create')" :href="route('technical-visits.create')">
                    <n-button type="primary" round size="large" class="shadow-md hover:shadow-lg transition-shadow duration-300">
                        <template #icon>
                            <n-icon><AddOutline /></n-icon>
                        </template>
                        Agendar Visita
                    </n-button>
                </Link>
            </div>
        </template>

        <div class="py-8 min-h-screen">
            <div class="max-w-[90rem] mx-auto sm:px-6 lg:px-8">
                
                <!-- Filtros -->
                <div class="mb-6 px-4 sm:px-0 flex flex-col xl:flex-row justify-between items-start xl:items-center gap-4">
                    <n-input 
                        v-model:value="search" 
                        type="text" 
                        placeholder="Buscar prospecto o ubicación..." 
                        class="w-full xl:max-w-xs shadow-sm rounded-full"
                        clearable round
                    >
                        <template #prefix><n-icon :component="SearchOutline" class="text-gray-400" /></template>
                    </n-input>

                    <div class="flex flex-col md:flex-row gap-3 w-full xl:w-auto flex-wrap justify-end">
                        <n-date-picker 
                            v-model:value="dateRangeFilter" 
                            type="daterange" 
                            clearable 
                            placeholder="Fechas de Visita"
                            class="w-full md:w-60 shadow-sm"
                        />
                        <n-select 
                            v-model:value="municipalityFilter"
                            :options="municipalityOptions"
                            placeholder="Municipio"
                            filterable clearable
                            class="w-full md:w-40 shadow-sm"
                        />
                        <n-select 
                            v-model:value="stateFilter"
                            :options="stateOptions"
                            placeholder="Estado"
                            filterable clearable
                            class="w-full md:w-40 shadow-sm"
                        />
                        <n-select 
                            v-model:value="systemTypeFilter"
                            :options="systemTypeOptions"
                            placeholder="Sistema"
                            filterable clearable
                            class="w-full md:w-40 shadow-sm"
                        />
                        <n-select 
                            v-model:value="statusFilter"
                            :options="statusOptions"
                            placeholder="Estatus"
                            clearable
                            class="w-full md:w-40 shadow-sm"
                        />
                        <n-switch 
                            v-model:value="showCompletedFilter"
                            size="small"
                        >
                            <template #checked>Mostrando terminadas</template>
                            <template #unchecked>Terminadas ocultas</template>
                        </n-switch>
                    </div>
                </div>

                <!-- TABLA (Escritorio) -->
                <div class="hidden md:block bg-white/80 backdrop-blur-xl rounded-3xl shadow-lg border border-gray-100 overflow-x-auto">
                    <n-data-table
                        :columns="columns"
                        :data="visits.data"
                        :pagination="false"
                        :bordered="false"
                        single-column
                        :row-props="rowProps"
                        class="custom-table"
                    />
                    <div class="p-4 flex justify-end border-t border-gray-100" v-if="visits.total > 0">
                        <n-pagination
                            :page="visits.current_page"
                            :page-count="visits.last_page"
                            :on-update:page="handlePageChange"
                        />
                    </div>
                </div>

                <!-- CARDS (Móvil) -->
                <div class="md:hidden space-y-4 px-4 sm:px-0">
                    <div v-if="visits.data.length === 0" class="flex justify-center mt-10">
                        <n-empty description="No se encontraron visitas técnicas" />
                    </div>

                    <div 
                        v-for="visit in visits.data" 
                        :key="visit.id" 
                        class="bg-white rounded-2xl p-4 shadow-sm border border-gray-100 relative overflow-hidden active:bg-gray-50 transition-colors"
                        @click="goToShow(visit.id)"
                    >
                        <!-- Header Card -->
                        <div class="flex justify-between items-start mb-3">
                            <div class="flex items-center gap-2">
                                <div class="bg-indigo-50 text-indigo-600 px-2 py-1 rounded-md font-mono font-bold text-xs">
                                    #{{ visit.id }}
                                </div>
                                <span class="text-xs text-gray-400 font-medium">Creada {{ visit.created_at_human }}</span>
                            </div>
                            <div class="flex flex-col items-end gap-1">
                                <n-tag :type="getStatusColor(visit.status)" size="small" round :bordered="false">
                                    {{ visit.status }}
                                </n-tag>
                                <span v-if="visit.status === 'Reprogramada' && visit.reschedule_reason" class="text-[10px] text-blue-500 max-w-[150px] text-right leading-tight">
                                    {{ visit.reschedule_reason }}
                                </span>
                                <span v-if="visit.status === 'Rechazada' && visit.rejection_reason" class="text-[10px] text-red-500 max-w-[150px] text-right leading-tight">
                                    {{ visit.rejection_reason }}
                                </span>
                            </div>
                        </div>

                        <!-- Info Principal -->
                        <div class="mb-3">
                            <h3 class="font-bold text-gray-800 text-base flex items-center gap-2">
                                <n-icon :component="visit.client ? BusinessOutline : PersonOutline" :class="visit.client ? 'text-blue-500' : 'text-gray-400'"/>
                                {{ visit.prospect_name }}
                            </h3>
                            <div class="flex flex-col gap-2 mt-2">
                                <div class="flex items-center gap-2 text-sm text-gray-600 bg-gray-50 p-2 rounded-lg">
                                    <n-icon :component="CalendarOutline" class="text-indigo-500" />
                                    <span class="font-medium">{{ visit.scheduled_at || 'Sin fecha' }}</span>
                                </div>
                                <div class="flex items-start gap-1 text-xs text-gray-500">
                                    <n-icon :component="LocationOutline" class="mt-0.5 text-gray-400"/>
                                    <span>{{ visit.municipality || 'N/A' }}, {{ visit.state || 'N/A' }}</span>
                                </div>
                                <a v-if="visit.google_maps_link" :href="visit.google_maps_link" target="_blank" class="text-xs text-blue-500 hover:text-blue-700 flex items-center gap-1" @click.stop>
                                    <n-icon :component="OpenOutline" size="12" />
                                    Ver en Google Maps
                                </a>
                            </div>
                            
                            <div class="flex flex-wrap gap-2 mt-3">
                                <n-tag v-if="visit.system_of_interest" size="small" :bordered="false" type="info">
                                    <template #icon><n-icon :component="HardwareChipOutline" /></template>
                                    {{ visit.system_of_interest }}
                                </n-tag>
                                <n-tag v-if="visit.requires_long_ladder" size="small" :bordered="false" type="warning">
                                    Escalera Larga
                                </n-tag>
                            </div>
                        </div>

                        <!-- Footer Actions -->
                        <div class="flex justify-end items-center border-t border-gray-100 pt-3">
                            <div class="flex gap-2">
                                <n-button circle size="small" quaternary type="info" @click.stop="goToShow(visit.id)">
                                    <template #icon><n-icon :component="EyeOutline" /></template>
                                </n-button>
                                
                                <n-button v-if="hasPermission('technical_visits.edit')" circle size="small" quaternary type="warning" @click.stop="goToEdit(visit.id)">
                                    <template #icon><n-icon :component="CreateOutline" /></template>
                                </n-button>

                                <n-dropdown v-if="hasPermission('technical_visits.edit') && visit.status !== 'Terminada'" trigger="click" :options="[
                                    { label: 'Reprogramar', key: 'reschedule', icon: () => h(NIcon, null, { default: () => h(TimeOutline) }) },
                                    { label: 'Aceptar', key: 'accept', icon: () => h(NIcon, null, { default: () => h(CheckmarkCircleOutline) }) },
                                    { label: 'Terminar', key: 'complete', icon: () => h(NIcon, null, { default: () => h(CheckmarkDoneOutline) }) },
                                    { label: 'Rechazar', key: 'reject', icon: () => h(NIcon, null, { default: () => h(CloseCircleOutline) }) },
                                ]" :on-select="(key) => { if (key === 'reschedule') openRescheduleModal(visit); else if (key === 'reject') openRejectModal(visit); else quickAction(visit.id, key); }">
                                    <n-button circle size="small" quaternary type="default" @click.stop>
                                        <template #icon><n-icon :component="EllipsisHorizontal" /></template>
                                    </n-button>
                                </n-dropdown>

                                <n-button v-if="hasPermission('technical_visits.delete')" circle size="small" quaternary type="error" @click.stop="confirmDelete(visit)">
                                    <template #icon><n-icon :component="TrashOutline" /></template>
                                </n-button>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-center mt-6" v-if="visits.total > 0">
                        <n-pagination simple :page="visits.current_page" :page-count="visits.last_page" :on-update:page="handlePageChange" />
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal Reprogramar -->
        <n-modal v-model:show="showRescheduleModal">
            <n-card
                style="width: 450px"
                title="Reprogramar Visita"
                :bordered="false"
                size="huge"
                role="dialog"
                aria-modal="true"
            >
                <template #header-extra>
                    <n-icon size="24" :component="TimeOutline" class="text-indigo-500" />
                </template>
                
                <n-form :model="rescheduleForm">
                    <n-form-item label="Nueva Fecha y Hora" path="scheduled_at">
                        <n-date-picker 
                            v-model:value="rescheduleForm.scheduled_at" 
                            type="datetime" 
                            clearable 
                            class="w-full"
                        />
                    </n-form-item>
                    <n-form-item label="Motivo de Reprogramación (Opcional)" path="reschedule_reason">
                        <n-input 
                            v-model:value="rescheduleForm.reschedule_reason" 
                            type="textarea" 
                            placeholder="Ej: El cliente tuvo una emergencia..."
                            :autosize="{ minRows: 3 }"
                        />
                    </n-form-item>
                </n-form>
                
                <template #footer>
                    <div class="flex justify-end gap-3">
                        <n-button @click="showRescheduleModal = false">Cancelar</n-button>
                        <n-button type="primary" @click="submitReschedule">Guardar Cambios</n-button>
                    </div>
                </template>
            </n-card>
        </n-modal>

        <!-- Modal Rechazar -->
        <n-modal v-model:show="showRejectModal">
            <n-card
                style="width: 450px"
                title="Rechazar Visita"
                :bordered="false"
                size="huge"
                role="dialog"
                aria-modal="true"
            >
                <template #header-extra>
                    <n-icon size="24" :component="CloseCircleOutline" class="text-red-500" />
                </template>
                
                <n-form :model="rejectForm">
                    <n-form-item label="Motivo del Rechazo" path="rejection_reason" required>
                        <n-input 
                            v-model:value="rejectForm.rejection_reason" 
                            type="textarea" 
                            placeholder="Ej: El prospecto no está interesado, datos de contacto incorrectos..."
                            :autosize="{ minRows: 4 }"
                        />
                    </n-form-item>
                </n-form>
                
                <template #footer>
                    <div class="flex justify-end gap-3">
                        <n-button @click="showRejectModal = false">Cancelar</n-button>
                        <n-button type="error" @click="submitReject">Confirmar Rechazo</n-button>
                    </div>
                </template>
            </n-card>
        </n-modal>

        <CompleteVisitModal
            v-model:show="showCompleteModal"
            :visit-id="completingVisitId"
            :system-type="completingSystemType"
            @completed="console.log('Visita completada')"
        />
        
    </AppLayout>
</template>

<style scoped>
.custom-table {
    --n-th-font-weight: 600;
    --n-td-padding: 16px;
}
:deep(.n-data-table .n-data-table-th) {
    background-color: transparent;
    color: #6b7280;
    border-bottom: 1px solid #f3f4f6;
    text-transform: uppercase;
    font-size: 0.75rem;
}
</style>