<script setup>
import { ref, watch, h } from 'vue';
import { usePermissions } from '@/Composables/usePermissions';
import { Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { 
    NButton, NDataTable, NInput, NSpace, NTag, NAvatar, NIcon, NEmpty, NPagination, 
    createDiscreteApi, NTooltip, NSelect, NDatePicker, NModal, NCard, NForm, NFormItem 
} from 'naive-ui';
import { 
    SearchOutline, AddOutline, EyeOutline, CreateOutline, TrashOutline, 
    CalendarOutline, LocationOutline, PersonOutline, BusinessOutline,
    ChevronDownOutline, HardwareChipOutline, TimeOutline
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

let searchTimeout;

const applyFilters = () => {
    router.get(route('technical-visits.index'), { 
        search: search.value,
        status: statusFilter.value,
        municipality: municipalityFilter.value,
        state: stateFilter.value,
        system_type: systemTypeFilter.value,
        date_range: dateRangeFilter.value 
    }, { preserveState: true, replace: true });
};

watch(search, (value) => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(applyFilters, 300);
});

watch([statusFilter, municipalityFilter, stateFilter, systemTypeFilter, dateRangeFilter], applyFilters);

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
        notification.warning({ title: 'Atención', content: 'Debes seleccionar una nueva fecha y hora.' });
        return;
    }
    
    // Convertimos el timestamp de NaiveUI a formato compatible con Laravel (Y-m-d H:i:s)
    const formattedDate = new Date(rescheduleForm.scheduled_at).toISOString().slice(0, 19).replace('T', ' ');

    // Como tu update() requiere todos los campos, la forma más limpia aquí es 
    // enviar a la vista de edición o implementar un método rápido.
    // Aquí hacemos un PUT, pero asumiendo que tu controlador puede requerir los demás campos,
    // puedes usar una ruta customizada si lo deseas en el futuro.
    rescheduleForm.transform((data) => ({
        ...data,
        scheduled_at: formattedDate,
        status: 'Reprogramada'
    })).put(route('technical-visits.update', currentVisitId.value), {
        preserveScroll: true,
        onSuccess: () => {
            showRescheduleModal.value = false;
            notification.success({ title: 'Visita Reprogramada', content: 'La fecha se ha actualizado correctamente.' });
        },
        onError: () => {
            // Fallback: Si falla la validación por campos faltantes en update, redirigimos a Edit.
            notification.error({ title: 'Error de validación', content: 'Por favor actualiza desde el formulario completo.', duration: 4000 });
            goToEdit(currentVisitId.value);
        }
    });
};

const confirmDelete = (visit) => {
    dialog.warning({
        title: 'Eliminar Visita Técnica',
        content: `¿Estás seguro de eliminar la visita #${visit.id} de ${visit.prospect_name}? Esta acción no se puede deshacer.`,
        positiveText: 'Sí, Eliminar',
        negativeText: 'Cancelar',
        onPositiveClick: () => {
            router.delete(route('technical-visits.destroy', visit.id), {
                onSuccess: () => notification.success({ title: 'Éxito', content: 'Visita técnica eliminada.' }),
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
                return h('div', { class: 'flex flex-col gap-1' }, [
                    h('div', { class: 'flex items-start gap-1 text-sm text-gray-600' }, [
                        h(NIcon, { component: LocationOutline, class: 'mt-0.5 text-gray-400' }),
                        h('span', { class: 'line-clamp-2' }, `${row.municipality || 'N/A'}, ${row.state || 'N/A'}`)
                    ])
                ]);
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
                return h(NTag, { 
                    type: getStatusColor(row.status), 
                    size: 'small', 
                    bordered: false, 
                    round: true
                }, { default: () => row.status });
            }
        },
        {
            title: '',
            key: 'actions',
            width: 180,
            render(row) {
                const canEdit = hasPermission('technical_visits.edit');
                const canDelete = hasPermission('technical_visits.delete');

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
                            circle: true, size: 'small', quaternary: true, type: 'primary',
                            onClick: (e) => { e.stopPropagation(); openRescheduleModal(row); }
                        }, { icon: () => h(NIcon, null, { default: () => h(TimeOutline) }) }),
                        default: () => 'Reprogramar'
                    }) : null,
                    canEdit ? h(NTooltip, { trigger: 'hover' }, {
                        trigger: () => h(NButton, {
                            circle: true, size: 'small', quaternary: true, type: 'warning',
                            onClick: (e) => { e.stopPropagation(); goToEdit(row.id); }
                        }, { icon: () => h(NIcon, null, { default: () => h(CreateOutline) }) }),
                        default: () => 'Editar'
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
        date_range: dateRangeFilter.value
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
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                
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
                            <n-tag :type="getStatusColor(visit.status)" size="small" round :bordered="false">
                                {{ visit.status }}
                            </n-tag>
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
                                
                                <n-button v-if="hasPermission('technical_visits.edit')" circle size="small" quaternary type="primary" @click.stop="openRescheduleModal(visit)">
                                    <template #icon><n-icon :component="TimeOutline" /></template>
                                </n-button>
                                
                                <n-button v-if="hasPermission('technical_visits.edit')" circle size="small" quaternary type="warning" @click.stop="goToEdit(visit.id)">
                                    <template #icon><n-icon :component="CreateOutline" /></template>
                                </n-button>

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
                        <n-button type="primary" @click="submitReschedule" :loading="rescheduleForm.processing">Guardar Cambios</n-button>
                    </div>
                </template>
            </n-card>
        </n-modal>
        
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