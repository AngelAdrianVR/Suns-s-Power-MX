<script setup>
import { ref, watch, h } from 'vue';
import { usePermissions } from '@/Composables/usePermissions'; // Importar permisos
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { 
    NButton, NDataTable, NInput, NSpace, NTag, NAvatar, NIcon, NEmpty, NPagination, createDiscreteApi 
} from 'naive-ui';
import { 
    SearchOutline, AddOutline, EyeOutline, CreateOutline, TrashOutline, 
    StorefrontOutline, PersonOutline, CallOutline, MailOutline, CubeOutline 
} from '@vicons/ionicons5';

const props = defineProps({
    suppliers: Object,
    filters: Object,
});

// Inicializar permisos
const { hasPermission } = usePermissions();

// Configuración de Notificaciones (Feedback visual idéntico a productos)
const { notification, dialog } = createDiscreteApi(['notification', 'dialog']);

// Lógica de búsqueda
const search = ref(props.filters.search || '');
let searchTimeout;

watch(search, (value) => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        router.get(route('suppliers.index'), { search: value }, { preserveState: true, replace: true });
    }, 300);
});

// Acciones
const goToEdit = (id) => {
    router.visit(route('suppliers.edit', id));
};

const goToShow = (id) => {
    router.visit(route('suppliers.show', id));
};

const confirmDelete = (supplier) => {
    dialog.warning({
        title: 'Eliminar Proveedor',
        content: `¿Estás seguro de eliminar a "${supplier.company_name}"? Se desvincularán sus productos.`,
        positiveText: 'Eliminar',
        negativeText: 'Cancelar',
        onPositiveClick: () => {
            router.delete(route('suppliers.destroy', supplier.id), {
                onSuccess: () => {
                    notification.success({
                        title: 'Éxito',
                        content: 'Proveedor eliminado correctamente',
                        duration: 3000
                    });
                },
                onError: () => {
                    notification.error({
                        title: 'Error',
                        content: 'No se pudo eliminar. Verifica que no tenga órdenes de compra.',
                        duration: 4000
                    });
                }
            });
        }
    });
};

// --- Configuración de Columnas (Estilo Visual Unificado) ---
const createColumns = () => [
    {
        title: '',
        key: 'avatar',
        width: 60,
        align: 'center',
        render(row) {
            return h(NAvatar, {
                size: 40,
                class: 'bg-blue-50 text-blue-500 rounded-lg border border-blue-100'
            }, { default: () => h(NIcon, { size: 20 }, { default: () => h(StorefrontOutline) }) });
        }
    },
    {
        title: 'Empresa',
        key: 'company_name',
        sorter: 'default',
        render(row) {
            return h('div', { class: 'flex flex-col' }, [
                h('span', { class: 'font-bold text-gray-800 text-sm' }, row.company_name),
                h('span', { class: 'text-xs text-gray-400 font-mono mt-0.5' }, `ID: ${row.id}`)
            ]);
        }
    },
    {
        title: 'Contacto Principal',
        key: 'contact_name',
        render(row) {
            if (!row.contact_name) return h('span', { class: 'text-gray-300 italic text-xs' }, 'No registrado');
            return h('div', { class: 'flex items-center gap-2 text-gray-600' }, [
                h(NIcon, { class: 'text-gray-400' }, { default: () => h(PersonOutline) }),
                h('span', { class: 'text-sm' }, row.contact_name)
            ]);
        }
    },
    {
        title: 'Datos de Contacto',
        key: 'contact_info',
        render(row) {
            const elements = [];
            if (row.email) {
                elements.push(h('div', { class: 'flex items-center gap-1.5 text-xs text-gray-500 mb-1' }, [
                    h(NIcon, { class: 'text-indigo-400' }, { default: () => h(MailOutline) }),
                    h('span', row.email)
                ]));
            }
            if (row.phone) {
                elements.push(h('div', { class: 'flex items-center gap-1.5 text-xs text-gray-500' }, [
                    h(NIcon, { class: 'text-green-500' }, { default: () => h(CallOutline) }),
                    h('span', row.phone)
                ]));
            }
            return h('div', {}, elements.length ? elements : h('span', { class: 'text-gray-300 text-xs' }, '-'));
        }
    },
    {
        title: 'Catálogo',
        key: 'products_count',
        render(row) {
            const hasProducts = row.products_count > 0;
            return h(NTag, { 
                type: hasProducts ? 'success' : 'default', 
                size: 'small', 
                bordered: false, 
                round: true,
                class: hasProducts ? 'bg-emerald-50 text-emerald-600' : 'bg-gray-100 text-gray-500'
            }, { 
                default: () => h('div', { class: 'flex items-center gap-1' }, [
                    h(NIcon, { component: CubeOutline }),
                    h('span', hasProducts ? `${row.products_count} Asignados` : 'Sin asignar')
                ])
            });
        }
    },
    {
        title: '',
        key: 'actions',
        width: 140,
        render(row) {
            return h(NSpace, { justify: 'end' }, () => [
                h(NButton, {
                    circle: true, size: 'small', quaternary: true, type: 'info',
                    onClick: (e) => { e.stopPropagation(); goToShow(row.id); }
                }, { icon: () => h(NIcon, null, { default: () => h(EyeOutline) }) }),

                // Botón Editar: Protegido por suppliers.edit
                hasPermission('suppliers.edit') ? h(NButton, {
                    circle: true, size: 'small', quaternary: true, type: 'warning',
                    onClick: (e) => { e.stopPropagation(); goToEdit(row.id); }
                }, { icon: () => h(NIcon, null, { default: () => h(CreateOutline) }) }) : null,

                // Botón Eliminar: Protegido por suppliers.delete
                hasPermission('suppliers.delete') ? h(NButton, {
                    circle: true, size: 'small', quaternary: true, type: 'error',
                    onClick: (e) => { e.stopPropagation(); confirmDelete(row); }
                }, { icon: () => h(NIcon, null, { default: () => h(TrashOutline) }) }) : null
            ]);
        }
    }
];

const columns = createColumns();

const handlePageChange = (page) => {
    router.get(route('suppliers.index'), { page, search: search.value }, { preserveState: true });
};

// Propiedades de la fila para hacer click completo
const rowProps = (row) => {
  return {
    style: 'cursor: pointer;',
    onClick: () => goToShow(row.id)
  }
}

</script>

<template>
    <AppLayout title="Proveedores">
        <template #header>
            <div class="flex flex-col md:flex-row justify-between items-center gap-4">
                <div>
                    <h2 class="font-semibold text-2xl text-gray-800 leading-tight">
                        Directorio de Proveedores
                    </h2>
                    <p class="text-sm text-gray-500 mt-1">Gestiona tus aliados comerciales por sucursal</p>
                </div>
                <!-- Botón Crear: Protegido por suppliers.create -->
                <Link v-if="hasPermission('suppliers.create')" :href="route('suppliers.create')">
                    <n-button type="primary" round size="large" class="shadow-md hover:shadow-lg transition-shadow duration-300">
                        <template #icon>
                            <n-icon><AddOutline /></n-icon>
                        </template>
                        Nuevo Proveedor
                    </n-button>
                </Link>
            </div>
        </template>

        <div class="py-8 min-h-screen">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                
                <!-- Barra de búsqueda Estilizada -->
                <div class="mb-6 px-4 sm:px-0 flex justify-between items-center">
                    <n-input 
                        v-model:value="search" 
                        type="text" 
                        placeholder="Buscar empresa, contacto o email..." 
                        class="max-w-md shadow-sm rounded-full"
                        clearable
                        round
                        size="large"
                    >
                        <template #prefix>
                            <n-icon :component="SearchOutline" class="text-gray-400" />
                        </template>
                    </n-input>
                </div>

                <!-- TABLA (Escritorio) -->
                <div class="hidden md:block bg-white/80 backdrop-blur-xl rounded-3xl shadow-lg border border-gray-100 overflow-hidden">
                    <n-data-table
                        :columns="columns"
                        :data="suppliers.data"
                        :pagination="false"
                        :bordered="false"
                        single-column
                        :row-props="rowProps"
                        class="custom-table"
                    />
                    <!-- Paginación -->
                    <div class="p-4 flex justify-end border-t border-gray-100" v-if="suppliers.total > 0">
                        <n-pagination
                            :page="suppliers.current_page"
                            :page-count="suppliers.last_page"
                            :on-update:page="handlePageChange"
                        />
                    </div>
                    <!-- <div v-else class="p-10 flex justify-center">
                        <n-empty description="No se encontraron proveedores" />
                    </div> -->
                </div>

                <!-- CARDS (Móvil) -->
                <div class="md:hidden space-y-4 px-4 sm:px-0">
                    <div v-if="suppliers.data.length === 0" class="flex justify-center mt-10">
                        <n-empty description="No se encontraron proveedores" />
                    </div>

                    <div 
                        v-for="supplier in suppliers.data" 
                        :key="supplier.id" 
                        class="bg-white rounded-2xl p-4 shadow-sm border border-gray-100 relative overflow-hidden active:bg-gray-50 transition-colors"
                        @click="goToShow(supplier.id)"
                    >
                        <div class="flex items-start gap-4">
                            <!-- Icono / Avatar -->
                            <div class="flex-shrink-0">
                                <div class="w-12 h-12 rounded-xl bg-blue-50 text-blue-500 flex items-center justify-center border border-blue-100">
                                    <n-icon size="24"><StorefrontOutline /></n-icon>
                                </div>
                            </div>

                            <!-- Info Principal -->
                            <div class="flex-grow min-w-0 pr-8">
                                <h3 class="text-lg font-bold text-gray-800 leading-tight truncate">
                                    {{ supplier.company_name }}
                                </h3>
                                
                                <div v-if="supplier.contact_name" class="flex items-center gap-1 mt-1 text-gray-500 text-sm">
                                    <n-icon size="14"><PersonOutline /></n-icon>
                                    <span class="truncate">{{ supplier.contact_name }}</span>
                                </div>

                                <!-- Datos de contacto rápidos -->
                                <div class="mt-3 space-y-1">
                                    <div v-if="supplier.phone" class="flex items-center gap-2 text-xs text-gray-600 bg-gray-50 p-1.5 rounded-lg w-fit">
                                        <n-icon class="text-green-500"><CallOutline /></n-icon>
                                        {{ supplier.phone }}
                                    </div>
                                    <div v-if="supplier.email" class="flex items-center gap-2 text-xs text-gray-600 bg-gray-50 p-1.5 rounded-lg w-fit">
                                        <n-icon class="text-indigo-400"><MailOutline /></n-icon>
                                        <span class="truncate max-w-[150px]">{{ supplier.email }}</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Menú de Acciones (Botones flotantes) -->
                            <div class="absolute top-4 right-4 flex flex-col gap-2">
                                <button 
                                    v-if="hasPermission('suppliers.edit')"
                                    @click.stop="goToEdit(supplier.id)"
                                    class="text-amber-500 hover:bg-amber-50 p-2 rounded-full transition"
                                >
                                    <n-icon size="20"><CreateOutline /></n-icon>
                                </button>
                                <button 
                                    v-if="hasPermission('suppliers.delete')"
                                    @click.stop="confirmDelete(supplier)"
                                    class="text-red-500 hover:bg-red-50 p-2 rounded-full transition"
                                >
                                    <n-icon size="20"><TrashOutline /></n-icon>
                                </button>
                            </div>
                        </div>

                        <!-- Footer de la Card: Contador de productos -->
                        <div class="mt-4 pt-3 border-t border-gray-50 flex justify-between items-center">
                            <span class="text-xs text-gray-400">ID: {{ supplier.id }}</span>
                            <div 
                                class="flex items-center gap-1 px-2 py-1 rounded-full text-xs font-bold"
                                :class="supplier.products_count > 0 ? 'bg-emerald-50 text-emerald-600' : 'bg-gray-100 text-gray-400'"
                            >
                                <n-icon :component="CubeOutline"/>
                                <span>{{ supplier.products_count }} Productos</span>
                            </div>
                        </div>
                    </div>

                    <!-- Paginación Móvil -->
                    <div class="flex justify-center mt-6" v-if="suppliers.total > 0">
                        <n-pagination
                            simple
                            :page="suppliers.current_page"
                            :page-count="suppliers.last_page"
                            :on-update:page="handlePageChange"
                        />
                    </div>
                </div>

            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
/* Reutilizando los mismos estilos de tabla de Products/Index.vue */
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
    padding-top: 16px; /* Un poco más de aire para proveedores */
    padding-bottom: 16px;
}
:deep(.n-data-table:hover .n-data-table-td) {
    background-color: rgba(249, 250, 251, 0.5);
}
</style>