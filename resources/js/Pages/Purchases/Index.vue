<script setup>
import { ref, watch, h } from 'vue';
import { usePermissions } from '@/Composables/usePermissions'; // Importar permisos
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { 
    NButton, NDataTable, NInput, NTag, NAvatar, NIcon, NEmpty, NPagination, createDiscreteApi, NDropdown, NBadge, NTooltip 
} from 'naive-ui';
import { 
    SearchOutline, AddOutline, EyeOutline, CreateOutline, TrashOutline, 
    CartOutline, CalendarOutline, CloseCircleOutline, CheckmarkCircleOutline,
    EllipsisVertical, TimeOutline, PersonOutline
} from '@vicons/ionicons5';

const props = defineProps({
    orders: Object,
    filters: Object,
});

// Inicializar permisos
const { hasPermission } = usePermissions();

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
    let confirmContent = `¿Cambiar el estado de la orden OC-0${order.id} a "${newStatus}"?`;
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
                    notification.success({ title: 'Estado Actualizado', content: `Orden OC-0${order.id} ahora está ${newStatus}`, duration: 3000 });
                },
                onError: () => {
                    notification.error({ title: 'Error', content: 'No se pudo actualizar el estado.', duration: 3000 });
                }
            });
        }
    });
};

const confirmDelete = (order) => {
    dialog.warning({
        title: 'Eliminar Borrador',
        content: `¿Eliminar permanentemente la orden OC-0${order.id}?`,
        positiveText: 'Sí, eliminar',
        negativeText: 'Cancelar',
        onPositiveClick: () => {
            router.delete(route('purchases.destroy', order.id), {
                onSuccess: () => notification.success({ content: 'Orden eliminada', duration: 3000 })
            });
        }
    });
};

// Formato de Moneda
const formatCurrency = (amount) => {
    return new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(amount);
};

// Formato de Fecha
const formatDate = (dateString) => {
    if (!dateString) return '-';
    const date = new Date(dateString);
    if (isNaN(date.getTime())) return dateString; 
    
    return new Intl.DateTimeFormat('es-MX', { 
        day: '2-digit', 
        month: 'short', 
        year: 'numeric',
        timeZone: 'UTC'
    }).format(date);
};

// Configuración de Estilos de Estado
const getStatusStyles = (status) => {
    const styles = {
        'Borrador': { type: 'default', icon: CreateOutline, class: 'bg-gray-100 text-gray-600' },
        'Solicitada': { type: 'info', icon: CartOutline, class: 'bg-blue-100 text-blue-600' },
        'Recibida': { type: 'success', icon: CheckmarkCircleOutline, class: 'bg-green-100 text-green-600' },
        'Cancelada': { type: 'error', icon: CloseCircleOutline, class: 'bg-red-100 text-red-600' },
    };
    return styles[status] || styles['Borrador'];
};

const renderStatus = (status) => {
    const style = getStatusStyles(status);
    return h(NTag, { type: style.type, bordered: false, round: true, size: 'small' }, {
        default: () => h('div', { class: 'flex items-center gap-1' }, [
            h(NIcon, { component: style.icon }),
            h('span', status)
        ])
    });
};

// Opciones del Dropdown CON PERMISOS
const getDropdownOptions = (row) => {
    const dropdownOptions = [];
    
    if (row.status === 'Borrador') {
        // Enviar/Solicitar requiere permiso de edición
        if (hasPermission('purchases.edit')) {
            dropdownOptions.push({ label: 'Solicitar (Enviar)', key: 'Solicitada' });
        }
        // Eliminar requiere permiso de eliminación
        if (hasPermission('purchases.delete')) {
            dropdownOptions.push({ label: 'Eliminar', key: 'delete', props: { style: 'color: red' } });
        }
    }
    
    if (['Borrador', 'Solicitada'].includes(row.status)) {
        // Recibir y Cancelar requieren permiso de aprobación (autoridad)
        if (hasPermission('purchases.approve')) {
            dropdownOptions.push({ label: 'Recibir Mercancía', key: 'Recibida' });
            dropdownOptions.push({ label: 'Cancelar Orden', key: 'Cancelada', props: { style: 'color: red' } });
        }
    }
    return dropdownOptions;
};

// --- COLUMNAS TABLA ---
const createColumns = () => [
    {
        title: 'Folio',
        key: 'id',
        width: 80,
        render: (row) => h('span', { class: 'font-mono text-gray-500 font-bold' }, `OC-${String(row.id).padStart(4, '0')}`)
    },
    {
        title: 'Proveedor',
        key: 'supplier',
        render(row) {
            // CORRECCIÓN: Usar los campos del contacto que mandamos desde el controlador
            return h('div', { class: 'flex flex-col' }, [
                h('span', { class: 'font-bold text-gray-800 text-sm' }, row.supplier?.company_name || 'Desconocido'),
                h('div', { class: 'flex items-center gap-1 text-xs text-gray-400' }, [
                    h(NIcon, { component: PersonOutline, class: 'text-gray-400' }),
                    h('span', row.supplier?.contact_name || 'Sin contacto')
                ])
            ]);
        }
    },
    {
        title: 'Estado',
        key: 'status',
        width: 110,
        render: (row) => renderStatus(row.status)
    },
    {
        title: 'Creada',
        key: 'created_at',
        width: 110,
        render(row) {
            return h('div', { class: 'flex items-center gap-1 text-xs text-gray-500' }, [
                h('span', formatDate(row.created_at))
            ]);
        }
    },
    {
        title: 'Recepción', 
        key: 'received_date',
        width: 110,
        render(row) {
            if (!row.received_date) return h('span', { class: 'text-gray-300 text-xs' }, '-');
            return h('div', { class: 'flex items-center gap-1 text-xs text-green-600 font-medium' }, [
                h(NIcon, { component: CheckmarkCircleOutline }),
                h('span', formatDate(row.received_date))
            ]);
        }
    },
    {
        title: 'Total',
        key: 'total_cost',
        align: 'right',
        render: (row) => h('span', { class: 'font-bold text-gray-800' }, formatCurrency(row.total_cost) + ' ' + row.currency)
    },
    {
        title: '',
        key: 'actions',
        width: 140,
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

            // 2. Editar (Requiere permiso y que no esté finalizada)
            if (!['Recibida', 'Cancelada'].includes(row.status) && hasPermission('purchases.edit')) {
                buttons.push(
                    h(NButton, {
                        circle: true, size: 'small', quaternary: true, type: 'warning',
                        title: 'Editar Orden',
                        onClick: (e) => { e.stopPropagation(); goToEdit(row.id); }
                    }, { icon: () => h(NIcon, { component: CreateOutline }) })
                );
            }

            // 3. Dropdown (Opciones dinámicas según permisos)
            const dropdownOptions = getDropdownOptions(row);

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
                <!-- Botón Crear: Protegido por purchases.create -->
                <Link v-if="hasPermission('purchases.create')" :href="route('purchases.create')" class="w-full md:w-auto">
                    <n-button type="primary" round size="large" class="shadow-md w-full md:w-auto">
                        <template #icon><n-icon><AddOutline /></n-icon></template>
                        Nueva Orden
                    </n-button>
                </Link>
            </div>
        </template>

        <div class="py-8 min-h-screen">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 px-4">
                
                <!-- Filtros -->
                <div class="mb-6 flex justify-between items-center">
                    <n-input 
                        v-model:value="search" 
                        type="text" 
                        placeholder="Buscar por folio o proveedor..." 
                        class="w-full md:max-w-md shadow-sm rounded-full"
                        clearable round size="large"
                    >
                        <template #prefix><n-icon :component="SearchOutline" class="text-gray-400" /></template>
                    </n-input>
                </div>

                <!-- CONTENEDOR PRINCIPAL -->
                <div class="backdrop-blur-xl rounded-3xl overflow-hidden">
                    
                    <!-- VISTA ESCRITORIO (TABLA) - Oculta en pantallas pequeñas -->
                    <div class="hidden md:block">
                        <n-data-table
                            :columns="columns"
                            :data="orders.data"
                            :pagination="false"
                            :bordered="false"
                            single-column
                            :row-props="rowProps"
                        />
                    </div>

                    <!-- VISTA MÓVIL (TARJETAS) - Visible solo en pantallas pequeñas -->
                    <div class="md:hidden p-4 space-y-4 bg-gray-50/50">
                        <div v-if="orders.data.length === 0" class="flex flex-col items-center justify-center p-8 text-gray-400">
                            <n-icon size="48" :component="CartOutline" />
                            <span class="mt-2 text-sm">No hay órdenes encontradas</span>
                        </div>

                        <div 
                            v-for="order in orders.data" 
                            :key="order.id"
                            class="bg-white rounded-2xl p-4 shadow-sm border border-gray-100 active:scale-[0.99] transition-transform duration-200"
                            @click="goToShow(order.id)"
                        >
                            <!-- Encabezado Tarjeta -->
                            <div class="flex justify-between items-start mb-3">
                                <div class="flex flex-col">
                                    <span class="font-mono text-gray-400 text-xs font-bold tracking-wider">OC-{{ String(order.id).padStart(4, '0') }}</span>
                                    <span class="font-bold text-gray-800 text-lg leading-tight">{{ order.supplier?.company_name || 'Sin Proveedor' }}</span>
                                    <!-- Contacto móvil -->
                                    <span class="text-xs text-gray-400 flex items-center gap-1 mt-1">
                                        <n-icon :component="PersonOutline" /> {{ order.supplier?.contact_name || 'Sin contacto' }}
                                    </span>
                                </div>
                                <n-tag :type="getStatusStyles(order.status).type" round size="small" :bordered="false">
                                    <template #icon><n-icon :component="getStatusStyles(order.status).icon" /></template>
                                    {{ order.status }}
                                </n-tag>
                            </div>

                            <!-- Información Detalles -->
                            <div class="grid grid-cols-2 gap-y-3 gap-x-4 text-sm mb-4">
                                <!-- Creada -->
                                <div class="flex flex-col">
                                    <span class="text-xs text-gray-400 mb-0.5">Creada</span>
                                    <div class="flex items-center gap-1 text-gray-600">
                                        <n-icon :component="TimeOutline" class="text-gray-400"/>
                                        <span>{{ formatDate(order.created_at) }}</span>
                                    </div>
                                </div>
                                <!-- Total -->
                                <div class="flex flex-col text-right">
                                    <span class="text-xs text-gray-400 mb-0.5">Total</span>
                                    <span class="font-bold text-gray-800 text-base">{{ formatCurrency(order.total_cost) }}</span>
                                </div>
                                <!-- Entrega Estimada -->
                                <div class="flex flex-col">
                                    <span class="text-xs text-gray-400 mb-0.5">Entrega Est.</span>
                                    <div class="flex items-center gap-1 text-gray-600">
                                        <n-icon :component="CalendarOutline" class="text-gray-400"/>
                                        <span>{{ order.expected_date ? formatDate(order.expected_date) : 'Pendiente' }}</span>
                                    </div>
                                </div>
                                <!-- Recepción -->
                                <div class="flex flex-col text-right">
                                    <span class="text-xs text-gray-400 mb-0.5">Recepción</span>
                                    <div class="flex items-center justify-end gap-1" :class="order.received_date ? 'text-green-600 font-medium' : 'text-gray-300'">
                                        <n-icon v-if="order.received_date" :component="CheckmarkCircleOutline"/>
                                        <span>{{ order.received_date ? formatDate(order.received_date) : '-' }}</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Acciones (Footer de Tarjeta) -->
                            <div class="flex items-center justify-between pt-3 border-t border-gray-100 mt-2">
                                <div class="flex gap-2">
                                    <n-button circle size="small" quaternary type="info" @click.stop="goToShow(order.id)">
                                        <template #icon><n-icon :component="EyeOutline" /></template>
                                    </n-button>
                                    
                                    <!-- Editar: Protegido por purchases.edit -->
                                    <n-button 
                                        v-if="!['Recibida', 'Cancelada'].includes(order.status) && hasPermission('purchases.edit')"
                                        circle size="small" quaternary type="warning" @click.stop="goToEdit(order.id)"
                                    >
                                        <template #icon><n-icon :component="CreateOutline" /></template>
                                    </n-button>
                                </div>

                                <!-- Dropdown de acciones extra (Protegido internamente) -->
                                <n-dropdown 
                                    v-if="getDropdownOptions(order).length > 0"
                                    trigger="click" 
                                    :options="getDropdownOptions(order)"
                                    @select="(key) => {
                                        if (key === 'delete') confirmDelete(order);
                                        else updateStatus(order, key);
                                    }"
                                >
                                    <n-button size="small" secondary round @click.stop>
                                        Acciones
                                        <template #icon><n-icon :component="EllipsisVertical" /></template>
                                    </n-button>
                                </n-dropdown>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Paginación (Compartida) -->
                    <div class="p-4 flex justify-center md:justify-end border-t border-gray-100 bg-white" v-if="orders.total > 0">
                        <n-pagination
                            :page="orders.current_page"
                            :page-count="orders.last_page"
                            :on-update:page="handlePageChange"
                            size="medium"
                        />
                    </div>
                </div>
                
            </div>
        </div>
    </AppLayout>
</template>