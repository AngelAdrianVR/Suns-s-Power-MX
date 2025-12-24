<script setup>
import { ref, watch, h } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { 
    NButton, NDataTable, NInput, NTag, NAvatar, NIcon, NEmpty, NPagination, createDiscreteApi, NDropdown, NBadge 
} from 'naive-ui';
import { 
    SearchOutline, AddOutline, EyeOutline, CreateOutline, TrashOutline, 
    CartOutline, CalendarOutline, CloseCircleOutline, CheckmarkCircleOutline,
    EllipsisVertical
} from '@vicons/ionicons5';

const props = defineProps({
    orders: Object,
    filters: Object,
});

const { notification, dialog } = createDiscreteApi(['notification', 'dialog']);

// Lógica de búsqueda
const search = ref(props.filters.search || '');
let searchTimeout;

watch(search, (value) => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        router.get(route('purchases.index'), { search: value }, { preserveState: true, replace: true });
    }, 300);
});

// --- ACCIONES ---

const goToEdit = (id) => {
    router.visit(route('purchases.edit', id));
};

const goToShow = (id) => {
    router.visit(route('purchases.show', id));
};

// Cambiar Estado (Recibir, Cancelar, etc)
const updateStatus = (order, newStatus) => {
    let confirmTitle = 'Actualizar Estado';
    let confirmContent = `¿Cambiar el estado de la orden #${order.id} a "${newStatus}"?`;
    let confirmType = 'info';

    if (newStatus === 'Recibida') {
        confirmTitle = 'Recepcionar Mercancía';
        confirmContent = `¡Atención! Esto ingresará ${order.items_count} productos al inventario de la sucursal. ¿Confirmar recepción?`;
        confirmType = 'success';
    } else if (newStatus === 'Cancelada') {
        confirmTitle = 'Cancelar Orden';
        confirmContent = 'La orden quedará invalidada. ¿Estás seguro?';
        confirmType = 'error';
    }

    dialog.create({
        title: confirmTitle,
        content: confirmContent,
        type: confirmType,
        positiveText: 'Confirmar',
        negativeText: 'Volver',
        onPositiveClick: () => {
            router.patch(route('purchases.status', order.id), { status: newStatus }, {
                preserveScroll: true,
                onSuccess: () => {
                    notification.success({ title: 'Estado Actualizado', content: `Orden #${order.id} ahora está ${newStatus}` });
                },
                onError: () => {
                    notification.error({ title: 'Error', content: 'No se pudo actualizar el estado.' });
                }
            });
        }
    });
};

const confirmDelete = (order) => {
    dialog.warning({
        title: 'Eliminar Borrador',
        content: `¿Eliminar permanentemente la orden #${order.id}?`,
        positiveText: 'Sí, eliminar',
        negativeText: 'Cancelar',
        onPositiveClick: () => {
            router.delete(route('purchases.destroy', order.id), {
                onSuccess: () => notification.success({ content: 'Orden eliminada' })
            });
        }
    });
};

// Formato de Moneda
const formatCurrency = (amount) => {
    return new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(amount);
};

// Renderizado de Estado (Tags con color)
const renderStatus = (status) => {
    const styles = {
        'Borrador': { type: 'default', icon: CreateOutline },
        'Solicitada': { type: 'info', icon: CartOutline },
        'Recibida': { type: 'success', icon: CheckmarkCircleOutline },
        'Cancelada': { type: 'error', icon: CloseCircleOutline },
    };
    const style = styles[status] || styles['Borrador'];
    
    return h(NTag, { type: style.type, bordered: false, round: true, size: 'small' }, {
        default: () => h('div', { class: 'flex items-center gap-1' }, [
            h(NIcon, { component: style.icon }),
            h('span', status)
        ])
    });
};

// --- COLUMNAS ---
const createColumns = () => [
    {
        title: 'Folio',
        key: 'id',
        width: 80,
        render: (row) => h('span', { class: 'font-mono text-gray-500 font-bold' }, `#${row.id}`)
    },
    {
        title: 'Proveedor',
        key: 'supplier',
        render(row) {
            return h('div', { class: 'flex flex-col' }, [
                h('span', { class: 'font-bold text-gray-800 text-sm' }, row.supplier?.company_name || 'Desconocido'),
                h('span', { class: 'text-xs text-gray-400' }, row.supplier?.contact_name)
            ]);
        }
    },
    {
        title: 'Estado',
        key: 'status',
        width: 120,
        render: (row) => renderStatus(row.status)
    },
    {
        title: 'Fecha Estimada',
        key: 'expected_date',
        render(row) {
            if (!row.expected_date) return h('span', { class: 'text-gray-300' }, '-');
            return h('div', { class: 'flex items-center gap-1 text-xs text-gray-600' }, [
                h(NIcon, { component: CalendarOutline }),
                h('span', row.expected_date)
            ]);
        }
    },
    {
        title: 'Total',
        key: 'total_cost',
        align: 'right',
        render: (row) => h('span', { class: 'font-bold text-gray-800' }, formatCurrency(row.total_cost))
    },
    {
        title: '',
        key: 'actions',
        width: 160, // Más espacio para botones
        render(row) {
            const buttons = [];

            // 1. Ver Detalle (Siempre visible)
            buttons.push(
                h(NButton, {
                    circle: true, size: 'small', quaternary: true, type: 'info',
                    title: 'Ver Detalles',
                    onClick: (e) => { e.stopPropagation(); goToShow(row.id); }
                }, { icon: () => h(NIcon, { component: EyeOutline }) })
            );

            // 2. Editar (Solo si no está recibida/cancelada)
            if (!['Recibida', 'Cancelada'].includes(row.status)) {
                buttons.push(
                    h(NButton, {
                        circle: true, size: 'small', quaternary: true, type: 'warning',
                        title: 'Editar Orden',
                        onClick: (e) => { e.stopPropagation(); goToEdit(row.id); }
                    }, { icon: () => h(NIcon, { component: CreateOutline }) })
                );
            }

            // 3. Dropdown de Acciones Rápidas (Cambio de Estado)
            const dropdownOptions = [];
            
            if (row.status === 'Borrador') {
                dropdownOptions.push({ label: 'Solicitar (Enviar)', key: 'Solicitada' });
                dropdownOptions.push({ label: 'Eliminar', key: 'delete', props: { style: 'color: red' } });
            }
            if (['Borrador', 'Solicitada'].includes(row.status)) {
                dropdownOptions.push({ label: 'Recibir Mercancía', key: 'Recibida' });
                dropdownOptions.push({ label: 'Cancelar Orden', key: 'Cancelada', props: { style: 'color: red' } });
            }

            if (dropdownOptions.length > 0) {
                buttons.push(
                    h(NDropdown, {
                        trigger: 'click',
                        options: dropdownOptions,
                        onSelect: (key) => {
                            if (key === 'delete') confirmDelete(row);
                            else updateStatus(row, key);
                        }
                    }, {
                        default: () => h(NButton, {
                            circle: true, size: 'small', quaternary: true,
                            onClick: (e) => e.stopPropagation()
                        }, { icon: () => h(NIcon, { component: EllipsisVertical }) })
                    })
                );
            }

            return h('div', { class: 'flex justify-end gap-1' }, buttons);
        }
    }
];

const columns = createColumns();

const handlePageChange = (page) => {
    router.get(route('purchases.index'), { page, search: search.value }, { preserveState: true });
};

const rowProps = (row) => ({
    style: 'cursor: pointer;',
    onClick: () => goToShow(row.id)
});

</script>

<template>
    <AppLayout title="Órdenes de Compra">
        <template #header>
            <div class="flex flex-col md:flex-row justify-between items-center gap-4">
                <div>
                    <h2 class="font-semibold text-2xl text-gray-800 leading-tight">
                        Órdenes de Compra
                    </h2>
                    <p class="text-sm text-gray-500 mt-1">Administra tus adquisiciones y recepciones de stock</p>
                </div>
                <Link :href="route('purchases.create')">
                    <n-button type="primary" round size="large" class="shadow-md">
                        <template #icon><n-icon><AddOutline /></n-icon></template>
                        Nueva Orden
                    </n-button>
                </Link>
            </div>
        </template>

        <div class="py-8 min-h-screen">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                
                <!-- Filtros -->
                <div class="mb-6 px-4 sm:px-0 flex justify-between items-center">
                    <n-input 
                        v-model:value="search" 
                        type="text" 
                        placeholder="Buscar por folio o proveedor..." 
                        class="max-w-md shadow-sm rounded-full"
                        clearable round size="large"
                    >
                        <template #prefix><n-icon :component="SearchOutline" class="text-gray-400" /></template>
                    </n-input>
                </div>

                <!-- Tabla -->
                <div class="bg-white/80 backdrop-blur-xl rounded-3xl shadow-lg border border-gray-100 overflow-hidden">
                    <n-data-table
                        :columns="columns"
                        :data="orders.data"
                        :pagination="false"
                        :bordered="false"
                        single-column
                        :row-props="rowProps"
                    />
                    
                    <!-- Paginación -->
                    <div class="p-4 flex justify-end border-t border-gray-100" v-if="orders.total > 0">
                        <n-pagination
                            :page="orders.current_page"
                            :page-count="orders.last_page"
                            :on-update:page="handlePageChange"
                        />
                    </div>
                    <!-- <div v-else class="p-10 flex justify-center flex-col items-center text-gray-400 gap-2">
                        <n-icon size="48"><CartOutline /></n-icon>
                        <span>No hay órdenes de compra registradas</span>
                    </div> -->
                </div>
                
            </div>
        </div>
    </AppLayout>
</template>