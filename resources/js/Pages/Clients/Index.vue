<script setup>
import { ref, watch, h } from 'vue';
import { usePermissions } from '@/Composables/usePermissions'; 
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PaymentModal from '@/Components/MyComponents/PaymentModal.vue'; 
import { 
    NButton, NDataTable, NInput, NSpace, NTag, NAvatar, NIcon, NEmpty, NPagination, createDiscreteApi, NTooltip 
} from 'naive-ui';
import { 
    SearchOutline, AddOutline, EyeOutline, CreateOutline, TrashOutline, 
    PersonOutline, CallOutline, MailOutline, WalletOutline, CashOutline, AlertCircleOutline,
    LocationOutline // Importamos icono para dirección
} from '@vicons/ionicons5';

const props = defineProps({
    clients: Object, 
    filters: Object,
});

// Inicializar permisos
const { hasPermission } = usePermissions();
const { notification, dialog } = createDiscreteApi(['notification', 'dialog']);

// --- LÓGICA DE BÚSQUEDA ---
// Mantenemos ambos filtros en refs separados
const search = ref(props.filters.search || '');
const addressSearch = ref(props.filters.address_filter || ''); // Nuevo filtro

let searchTimeout;

// Función única para recargar la tabla
const reloadTable = () => {
    router.get(
        route('clients.index'), 
        { 
            search: search.value, 
            address_filter: addressSearch.value 
        }, 
        { preserveState: true, replace: true }
    );
};

// Observamos ambos campos
watch([search, addressSearch], () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        reloadTable();
    }, 400); // Un poco más de delay para evitar muchas peticiones si escriben en ambos
});

// --- ESTADO DEL MODAL DE PAGOS ---
const showPaymentModal = ref(false);
const selectedClientForPayment = ref(null);

const registerPayment = (client) => {
    selectedClientForPayment.value = client;
    showPaymentModal.value = true;
};

const goToEdit = (id) => router.visit(route('clients.edit', id));
const goToShow = (id) => router.visit(route('clients.show', id));

const confirmDelete = (client) => {
    dialog.warning({
        title: 'Eliminar Cliente',
        content: `¿Estás seguro de eliminar a "${client.name}"? Esta acción no se puede deshacer si tiene historial.`,
        positiveText: 'Eliminar',
        negativeText: 'Cancelar',
        onPositiveClick: () => {
            router.delete(route('clients.destroy', client.id), {
                onSuccess: () => notification.success({ title: 'Éxito', content: 'Cliente eliminado', duration: 3000 }),
                onError: () => notification.error({ title: 'Error', content: 'No se puede eliminar un cliente con ordenes de servicio ligadas.', duration: 4000 })
            });
        }
    });
};

const formatCurrency = (amount) => {
    return new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(amount);
};

// --- Configuración de Columnas ---
const createColumns = () => [
    {
        title: '',
        key: 'avatar',
        width: 60,
        align: 'center',
        render(row) {
            return h(NAvatar, {
                size: 40,
                class: 'bg-indigo-50 text-indigo-500 rounded-lg border border-indigo-100'
            }, { default: () => h(NIcon, { size: 20 }, { default: () => h(PersonOutline) }) });
        }
    },
    {
        title: 'Cliente / Razón Social',
        key: 'name',
        width: 250,
        render(row) {
            return h('div', { class: 'flex flex-col' }, [
                h('span', { class: 'font-bold text-gray-800 text-sm' }, row.name),
                row.tax_id 
                    ? h('span', { class: 'text-xs text-gray-400 font-mono mt-0.5' }, `RFC: ${row.tax_id}`)
                    : null
            ]);
        }
    },
    {
        title: 'Dirección',
        key: 'full_address',
        render(row) {
            // Renderizamos la dirección para ver si el filtro funcionó visualmente
            return h('div', { class: 'text-xs text-gray-500 max-w-[200px] truncate' }, [
                h(NIcon, { class: 'mr-1 relative top-0.5' }, { default: () => h(LocationOutline) }),
                row.full_address || 'Sin dirección'
            ]);
        }
    },
    {
        title: 'Contacto',
        key: 'contact_info',
        width: 200,
        render(row) {
            const elements = [];
            if (row.contact_person) {
                elements.push(h('div', { class: 'text-xs text-gray-600 font-medium mb-1' }, row.contact_person));
            }
            if (row.phone) {
                elements.push(h('div', { class: 'flex items-center gap-1.5 text-xs text-gray-500' }, [
                    h(NIcon, { class: 'text-green-500' }, { default: () => h(CallOutline) }),
                    h('span', row.phone)
                ]));
            }
            return h('div', { class: 'flex flex-col gap-0.5' }, elements.length ? elements : h('span', { class: 'text-gray-300 text-xs' }, '-'));
        }
    },
    {
        title: 'Estado',
        key: 'balance',
        render(row) {
            const hasDebt = row.has_debt;
            return h(NTag, { 
                type: hasDebt ? 'error' : 'success', 
                size: 'small', 
                bordered: false, 
                round: true,
                class: hasDebt ? 'bg-red-50 text-red-600 font-bold' : 'bg-emerald-50 text-emerald-600'
            }, { 
                default: () => h('div', { class: 'flex items-center gap-1' }, [
                    h(NIcon, { component: hasDebt ? AlertCircleOutline : WalletOutline }),
                    h('span', hasDebt ? `Debe: ${formatCurrency(row.balance)}` : 'Al corriente')
                ])
            });
        }
    },
    {
        title: '',
        key: 'actions',
        width: 190, // Ajusté un poco el ancho
        render(row) {
            return h(NSpace, { justify: 'end', align: 'center' }, () => [
                row.has_debt && hasPermission('collection.create') ? h(NTooltip, { trigger: 'hover' }, {
                    trigger: () => h(NButton, {
                        circle: true, size: 'small', quaternary: true, type: 'success',
                        class: 'bg-emerald-50 hover:bg-emerald-100 mr-2',
                        onClick: (e) => { e.stopPropagation(); registerPayment(row); }
                    }, { icon: () => h(NIcon, { component: CashOutline }) }),
                    default: () => 'Registrar Abono'
                }) : null,

                h(NButton, {
                    circle: true, size: 'small', quaternary: true, type: 'info',
                    onClick: (e) => { e.stopPropagation(); goToShow(row.id); }
                }, { icon: () => h(NIcon, null, { default: () => h(EyeOutline) }) }),

                hasPermission('clients.edit') ? h(NButton, {
                    circle: true, size: 'small', quaternary: true, type: 'warning',
                    onClick: (e) => { e.stopPropagation(); goToEdit(row.id); }
                }, { icon: () => h(NIcon, null, { default: () => h(CreateOutline) }) }) : null,

                hasPermission('clients.delete') ? h(NButton, {
                    circle: true, size: 'small', quaternary: true, type: 'error',
                    onClick: (e) => { e.stopPropagation(); confirmDelete(row); }
                }, { icon: () => h(NIcon, null, { default: () => h(TrashOutline) }) }) : null
            ]);
        }
    }
];

const columns = createColumns();

const handlePageChange = (page) => {
    // Incluimos ambos filtros al cambiar de página
    router.get(route('clients.index'), { 
        page, 
        search: search.value,
        address_filter: addressSearch.value
    }, { preserveState: true });
};

const rowProps = (row) => ({
    style: 'cursor: pointer;',
    onClick: () => goToShow(row.id)
});
</script>

<template>
    <AppLayout title="Clientes">
        <template #header>
            <div class="flex flex-col md:flex-row justify-between items-center gap-4">
                <div>
                    <h2 class="font-semibold text-2xl text-gray-800 leading-tight">
                        Cartera de Clientes
                    </h2>
                    <p class="text-sm text-gray-500 mt-1">Gestión de expedientes y estado de cuenta por sucursal</p>
                </div>
                <Link v-if="hasPermission('clients.create')" :href="route('clients.create')">
                    <n-button type="primary" round size="large" class="shadow-md hover:shadow-lg transition-shadow duration-300">
                        <template #icon><n-icon><AddOutline /></n-icon></template>
                        Nuevo Cliente
                    </n-button>
                </Link>
            </div>
        </template>

        <div class="py-8 min-h-screen">
            <div class="max-w-[90rem] mx-auto sm:px-6 lg:px-8">
                
                <!-- Barra de Filtros -->
                <div class="mb-6 px-4 sm:px-0 flex flex-col md:flex-row gap-4 justify-between items-start md:items-center">
                    
                    <div class="flex flex-col md:flex-row gap-3 w-full md:w-auto flex-grow max-w-4xl">
                        <!-- Filtro 1: Buscador General -->
                        <n-input 
                            v-model:value="search" 
                            type="text" 
                            placeholder="Buscar: Nombre, RFC, Email..." 
                            class="w-full md:w-64 shadow-sm"
                            clearable
                            round
                            size="large"
                        >
                            <template #prefix>
                                <n-icon :component="SearchOutline" class="text-gray-400" />
                            </template>
                        </n-input>

                        <!-- Filtro 2: Buscador Dirección (NUEVO) -->
                        <n-input 
                            v-model:value="addressSearch" 
                            type="text" 
                            placeholder="Filtrar por Colonia, Estado, Municipio..." 
                            class="w-full md:w-80 shadow-sm"
                            clearable
                            round
                            size="large"
                        >
                            <template #prefix>
                                <n-icon :component="LocationOutline" class="text-indigo-400" />
                            </template>
                        </n-input>
                    </div>

                </div>

                <!-- TABLA (Escritorio) -->
                <div class="hidden md:block bg-white/80 backdrop-blur-xl rounded-3xl shadow-lg border border-gray-100 overflow-hidden">
                    <n-data-table
                        :columns="columns"
                        :data="clients.data"
                        :pagination="false"
                        :bordered="false"
                        single-column
                        :row-props="rowProps"
                        class="custom-table"
                    />
                    <div class="p-4 flex justify-end border-t border-gray-100" v-if="clients.total > 0">
                        <n-pagination
                            :page="clients.current_page"
                            :page-count="clients.last_page"
                            :on-update:page="handlePageChange"
                        />
                    </div>
                </div>

                <!-- CARDS (Móvil) -->
                <div class="md:hidden space-y-4 px-4 sm:px-0">
                    <div v-if="clients.data.length === 0" class="flex justify-center mt-10">
                        <n-empty description="No se encontraron clientes" />
                    </div>

                    <div 
                        v-for="client in clients.data" 
                        :key="client.id" 
                        class="bg-white rounded-2xl p-4 shadow-sm border border-gray-100 relative overflow-hidden active:bg-gray-50 transition-colors"
                        @click="goToShow(client.id)"
                    >
                        <div class="flex items-start gap-4">
                            <div class="flex-shrink-0">
                                <div class="w-12 h-12 rounded-xl bg-indigo-50 text-indigo-500 flex items-center justify-center border border-indigo-100">
                                    <n-icon size="24"><PersonOutline /></n-icon>
                                </div>
                            </div>
                            <div class="flex-grow min-w-0 pr-8">
                                <h3 class="text-lg font-bold text-gray-800 leading-tight truncate">
                                    {{ client.name }}
                                </h3>
                                <div v-if="client.contact_person" class="text-sm text-gray-500 mt-0.5">
                                    {{ client.contact_person }}
                                </div>
                                <!-- Mostrar dirección en móvil también -->
                                <div class="mt-2 text-xs text-gray-400 flex items-start gap-1">
                                    <n-icon class="mt-0.5"><LocationOutline/></n-icon>
                                    <span class="line-clamp-2">{{ client.full_address || 'Sin dirección' }}</span>
                                </div>
                            </div>
                            
                            <!-- Botones acción móvil -->
                            <div class="absolute top-4 right-4 flex flex-col gap-2">
                                <button v-if="hasPermission('clients.edit')" @click.stop="goToEdit(client.id)" class="text-amber-500 hover:bg-amber-50 p-2 rounded-full">
                                    <n-icon size="20"><CreateOutline /></n-icon>
                                </button>
                                <button v-if="hasPermission('clients.delete')" @click.stop="confirmDelete(client)" class="text-red-500 hover:bg-red-50 p-2 rounded-full">
                                    <n-icon size="20"><TrashOutline /></n-icon>
                                </button>
                            </div>
                        </div>

                        <div class="mt-4 pt-3 border-t border-gray-50 flex justify-between items-center">
                            <span class="text-xs text-gray-400 font-mono">ID: {{ client.id }}</span>
                            <div 
                                class="flex items-center gap-1 px-3 py-1 rounded-full text-xs font-bold"
                                :class="client.has_debt ? 'bg-red-50 text-red-600' : 'bg-emerald-50 text-emerald-600'"
                            >
                                <n-icon :component="client.has_debt ? AlertCircleOutline : WalletOutline"/>
                                <span>{{ client.has_debt ? `Debe: ${formatCurrency(client.balance)}` : 'Al corriente' }}</span>
                            </div>
                        </div>
                        
                        <div v-if="client.has_debt && hasPermission('collection.create')" class="mt-2">
                             <n-button block type="success" ghost size="small" @click.stop="registerPayment(client)">
                                <template #icon><n-icon :component="CashOutline" /></template>
                                Registrar Abono
                             </n-button>
                        </div>
                    </div>
                     <div class="flex justify-center mt-6" v-if="clients.total > 0">
                        <n-pagination simple :page="clients.current_page" :page-count="clients.last_page" :on-update:page="handlePageChange" />
                    </div>
                </div>

            </div>
        </div>

        <PaymentModal 
            v-model:show="showPaymentModal" 
            :client="selectedClientForPayment"
            @close="selectedClientForPayment = null"
        />

    </AppLayout>
</template>

<style scoped>
:deep(.n-data-table .n-data-table-th) {
    background-color: transparent;
    font-weight: 600;
    color: #6b7280;
    border-bottom: 1px solid #f3f4f6;
    text-transform: uppercase;
    font-size: 0.75rem;
    letter-spacing: 0.05em;
}
:deep(.n-data-table .n-data-table-td) {
    background-color: transparent;
    border-bottom: 1px solid #f9fafb;
    padding-top: 16px;
    padding-bottom: 16px;
}
:deep(.n-data-table:hover .n-data-table-td) {
    background-color: rgba(249, 250, 251, 0.5);
}
</style>