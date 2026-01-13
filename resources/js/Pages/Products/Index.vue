<script setup>
import { ref, watch, h } from 'vue';
import { usePermissions } from '@/Composables/usePermissions'; // Importamos el composable
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { 
    NButton, NDataTable, NInput, NSpace, NTag, NAvatar, NCard, NIcon, NModal, NEmpty, NPagination, createDiscreteApi, NBadge
} from 'naive-ui';
import { 
    SearchOutline, AddOutline, CreateOutline, TrashOutline, CubeOutline, LocationOutline, AlertCircleOutline
} from '@vicons/ionicons5';

// Props desde el controlador
const props = defineProps({
    products: Object,
    filters: Object,
});

// Permisos
const { hasPermission } = usePermissions();

// Configuración de Notificaciones
const { notification, dialog } = createDiscreteApi(['notification', 'dialog']);

// Estado para búsqueda
const search = ref(props.filters.search || '');

// Debounce para búsqueda
let timeout = null;
watch(search, (value) => {
    clearTimeout(timeout);
    timeout = setTimeout(() => {
        router.get(route('products.index'), { search: value }, { preserveState: true, replace: true });
    }, 300);
});

// Estado para el modal de imagen
const showImageModal = ref(false);
const selectedImage = ref('');

const openImageModal = (url) => {
    if(!url) return;
    selectedImage.value = url;
    showImageModal.value = true;
};

// Formateador de moneda (MXN)
const formatCurrency = (value) => {
    return new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency: 'MXN'
    }).format(value);
};

// Confirmar eliminación
const confirmDelete = (product) => {
    dialog.warning({
        title: 'Eliminar Producto',
        content: `¿Estás seguro de que deseas eliminar "${product.name}"? Esta acción no se puede deshacer.`,
        positiveText: 'Eliminar',
        negativeText: 'Cancelar',
        onPositiveClick: () => {
            router.delete(route('products.destroy', product.id), {
                onSuccess: () => {
                    notification.success({
                        title: 'Éxito',
                        content: 'Producto eliminado correctamente',
                        duration: 3000
                    });
                }
            });
        }
    });
};

const goToEdit = (id) => {
    router.get(route('products.edit', id));
};

// --- Configuración de Columnas (Naive UI DataTable) ---
const createColumns = () => [
    {
        title: 'Imagen',
        key: 'image_url',
        width: 80,
        align: 'center',
        render(row) {
            if (row.image_url) {
                return h(NAvatar, {
                    shape: 'square',
                    size: 48, 
                    src: row.image_url,
                    class: 'cursor-pointer hover:opacity-80 transition-opacity rounded-lg shadow-sm border border-gray-100',
                    objectFit: 'cover',
                    onClick: (e) => {
                        e.stopPropagation();
                        openImageModal(row.image_url);
                    }
                });
            } else {
                return h(NAvatar, {
                    shape: 'square',
                    size: 48,
                    class: 'bg-gray-50 text-gray-300 rounded-lg border border-dashed border-gray-200'
                }, { default: () => h(NIcon, { size: 24 }, { default: () => h(CubeOutline) }) });
            }
        }
    },
    {
        title: 'Producto / SKU',
        key: 'name',
        sorter: 'default',
        render(row) {
            return h('div', { class: 'flex flex-col' }, [
                h('span', { class: 'font-bold text-gray-800 text-sm' }, row.name),
                h('span', { class: 'text-xs text-gray-400 font-mono mt-0.5' }, row.sku)
            ]);
        }
    },
    {
        title: 'Stock',
        key: 'stock',
        width: 140, // Aumenté un poco el ancho para el ícono
        render(row) {
            let type = 'success';
            let showIcon = false;

            if (row.stock <= 0) {
                type = 'error';
                showIcon = true;
            } 
            else if (row.stock <= row.min_stock) {
                type = 'warning';
                showIcon = true;
            }

            // Renderizamos un Tag que contiene opcionalmente el ícono
            return h(NTag, { type: type, size: 'small', bordered: false, round: true }, { 
                default: () => h('div', { class: 'flex items-center gap-1' }, [
                    showIcon ? h(NIcon, { component: AlertCircleOutline, class: 'text-xs' }) : null,
                    h('span', `${row.stock} uni.`)
                ])
            });
        }
    },
    {
        title: 'Ubicación',
        key: 'location',
        width: 150,
        render(row) {
            return h('div', { class: 'flex items-center gap-1 text-gray-500 text-xs' }, [
                h(NIcon, { component: LocationOutline, class: 'text-blue-400' }),
                h('span', row.location || 'N/A')
            ]);
        }
    },
    {
        title: 'Categoría',
        key: 'category',
        render(row) {
            return h(NTag, { type: 'default', size: 'small', bordered: false, round: true, class: 'bg-gray-100 text-gray-600' }, () => row.category);
        }
    },
    {
        title: 'Costo',
        key: 'purchase_price',
        render(row) {
            return h('div', { class: 'font-semibold text-emerald-600' }, formatCurrency(row.purchase_price));
        }
    },
    {
        title: 'Precio Venta',
        key: 'sale_price',
        render(row) {
            return h('div', { class: 'font-semibold text-emerald-600' }, formatCurrency(row.sale_price));
        }
    },
    {
        title: '',
        key: 'actions',
        width: 100,
        render(row) {
            const canEdit = hasPermission('products.edit');
            const canDelete = hasPermission('products.delete');

            if (!canEdit && !canDelete) return null;

            return h(NSpace, { justify: 'end' }, () => [
                canEdit ? h(
                    NButton,
                    {
                        circle: true,
                        size: 'small',
                        type: 'warning',
                        ghost: true,
                        onClick: (e) => {
                            e.stopPropagation();
                            goToEdit(row.id);
                        }
                    },
                    { icon: () => h(NIcon, null, { default: () => h(CreateOutline) }) }
                ) : null,
                canDelete ? h(
                    NButton,
                    {
                        circle: true,
                        size: 'small',
                        type: 'error',
                        ghost: true,
                        onClick: (e) => {
                            e.stopPropagation();
                            confirmDelete(row);
                        }
                    },
                    { icon: () => h(NIcon, null, { default: () => h(TrashOutline) }) }
                ) : null
            ]);
        }
    }
];

const columns = createColumns();

const handlePageChange = (page) => {
    router.get(props.products.path + '?page=' + page, { search: search.value }, { preserveState: true });
};

// Propiedades de la fila
const rowProps = (row) => {
  return {
    style: 'cursor: pointer;',
    onClick: () => router.get(route('products.show', row.id))
  }
}

</script>

<template>
    <AppLayout title="Inventario">
        <template #header>
            <div class="flex flex-col md:flex-row justify-between items-center gap-4">
                <div>
                    <h2 class="font-semibold text-2xl text-gray-800 leading-tight">
                        Catálogo de Productos
                    </h2>
                    <p class="text-sm text-gray-500 mt-1">Gestiona el inventario por sucursal</p>
                </div>
                <!-- Botón Crear -->
                <Link v-if="hasPermission('products.create')" :href="route('products.create')">
                    <n-button type="primary" round size="large" class="shadow-md hover:shadow-lg transition-shadow duration-300">
                        <template #icon>
                            <n-icon><AddOutline /></n-icon>
                        </template>
                        Nuevo Producto
                    </n-button>
                </Link>
            </div>
        </template>

        <div class="py-8 min-h-screen">
            <div class="max-w-[90rem] mx-auto sm:px-6 lg:px-8">
                
                <!-- Barra de búsqueda -->
                <div class="mb-6 px-4 sm:px-0 flex justify-between items-center">
                    <n-input 
                        v-model:value="search" 
                        type="text" 
                        placeholder="Buscar por nombre, SKU o descripción..." 
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
                        :data="products.data"
                        :pagination="false"
                        :bordered="false"
                        single-column
                        :row-props="rowProps"
                        class="custom-table"
                    />
                    <!-- Paginación -->
                    <div class="p-4 flex justify-end border-t border-gray-100" v-if="products.total > 0">
                        <n-pagination
                            :page="products.current_page"
                            :page-count="products.last_page"
                            :on-update:page="handlePageChange"
                        />
                    </div>
                </div>

                <!-- CARDS (Móvil) -->
                <div class="md:hidden space-y-4 px-4 sm:px-0">
                    <div v-if="products.data.length === 0" class="flex justify-center mt-10">
                        <n-empty description="No se encontraron productos" />
                    </div>

                    <div 
                        v-for="product in products.data" 
                        :key="product.id" 
                        class="bg-white rounded-2xl p-4 shadow-sm border border-gray-100 flex items-start gap-4 relative overflow-hidden"
                        @click="router.get(route('products.show', product.id))"
                    >
                        <!-- Imagen -->
                        <div class="flex-shrink-0" @click.stop>
                            <n-avatar 
                                shape="square"
                                :size="72" 
                                :src="product.image_url" 
                                class="cursor-pointer border border-gray-100 shadow-sm rounded-xl bg-gray-50 object-cover"
                                @click="openImageModal(product.image_url)"
                            >
                                <template #fallback>
                                    <n-icon :size="32" class="text-gray-300"><CubeOutline/></n-icon>
                                </template>
                            </n-avatar>
                        </div>

                        <!-- Info -->
                        <div class="flex-grow min-w-0">
                            <div class="flex justify-between items-start">
                                <!-- Nombre del producto: eliminada clase 'truncate', agregada 'break-words' y pr-20 para espacio de botones -->
                                <div class="pr-20"> 
                                    <h3 class="text-lg font-bold text-gray-800 leading-tight break-words">{{ product.name }}</h3>
                                    <p class="text-xs text-gray-400 font-mono mt-0.5">{{ product.sku }}</p>
                                </div>
                                
                                <!-- Botones de Acción (Móvil): Editar y Eliminar -->
                                <div class="flex gap-1 absolute top-4 right-4 md:static">
                                    <button 
                                        v-if="hasPermission('products.edit')"
                                        @click.stop="goToEdit(product.id)"
                                        class="text-amber-500 hover:bg-amber-50 p-2 rounded-full transition"
                                    >
                                        <n-icon size="20"><CreateOutline /></n-icon>
                                    </button>
                                    <button 
                                        v-if="hasPermission('products.delete')"
                                        @click.stop="confirmDelete(product)"
                                        class="text-red-500 hover:bg-red-50 p-2 rounded-full transition"
                                    >
                                        <n-icon size="20"><TrashOutline /></n-icon>
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Datos de Stock y Ubicación (Mobile) -->
                            <div class="mt-3 flex flex-wrap items-center gap-2">
                                <div class="flex items-center gap-1 bg-gray-50 px-2 py-1 rounded text-xs text-gray-600">
                                    <n-icon :component="LocationOutline" class="text-blue-500"/>
                                    <span class="truncate max-w-[80px]">{{ product.location || 'N/A' }}</span>
                                </div>
                                
                                <div 
                                    class="flex items-center gap-1 px-2 py-1 rounded text-xs font-bold"
                                    :class="{
                                        'bg-red-50 text-red-600': product.stock <= 0,
                                        'bg-amber-50 text-amber-600': product.stock > 0 && product.stock <= product.min_stock,
                                        'bg-green-50 text-green-600': product.stock > product.min_stock
                                    }"
                                >
                                    <n-icon v-if="product.stock <= product.min_stock" :component="AlertCircleOutline"/>
                                    <n-icon v-else :component="CubeOutline"/>
                                    <span>{{ product.stock }} uni.</span>
                                </div>
                            </div>

                            <div class="mt-2 flex items-center justify-between">
                                <span class="text-xs text-gray-400">{{ product.category }}</span>
                                <span class="text-lg font-bold text-emerald-600">
                                    {{ formatCurrency(product.sale_price) }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Paginación Móvil -->
                    <div class="flex justify-center mt-6" v-if="products.total > 0">
                        <n-pagination
                            simple
                            :page="products.current_page"
                            :page-count="products.last_page"
                            :on-update:page="handlePageChange"
                        />
                    </div>
                </div>

            </div>
        </div>

        <!-- Modal Imagen -->
        <n-modal v-model:show="showImageModal">
            <n-card
                style="width: 90%; max-width: 500px; padding: 0; overflow: hidden; border-radius: 16px;"
                :bordered="false"
                role="dialog"
                aria-modal="true"
                class="bg-transparent shadow-none"
            >
                <img :src="selectedImage" class="w-full h-auto object-contain block rounded-lg" />
            </n-card>
        </n-modal>
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
    padding-top: 12px;
    padding-bottom: 12px;
}
:deep(.n-data-table:hover .n-data-table-td) {
    background-color: rgba(249, 250, 251, 0.5);
}
</style>