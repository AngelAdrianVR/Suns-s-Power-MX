<script setup>
import { ref, watch, h } from 'vue';
import { usePermissions } from '@/Composables/usePermissions'; // Importar permisos
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { 
    NButton, NDataTable, NInput, NSpace, NTag, NAvatar, NCard, NIcon, NModal, NEmpty, NPagination, createDiscreteApi 
} from 'naive-ui';
import { 
    SearchOutline, AddOutline, CreateOutline, PowerOutline, EyeOutline, TrashOutline 
} from '@vicons/ionicons5';

// Props que vienen desde el controlador
const props = defineProps({
    users: Object,
    filters: Object,
});

// Inicializar permisos
const { hasPermission } = usePermissions();

// Configuración de Notificaciones y Diálogo
const { notification, dialog } = createDiscreteApi(['notification', 'dialog']);

// Estado para búsqueda
const search = ref(props.filters.search || '');

// Debounce para búsqueda
let timeout = null;
watch(search, (value) => {
    clearTimeout(timeout);
    timeout = setTimeout(() => {
        router.get(route('users.index'), { search: value }, { preserveState: true, replace: true });
    }, 300);
});

// Estado para el modal de imagen
const showImageModal = ref(false);
const selectedImage = ref('');

const openImageModal = (url) => {
    selectedImage.value = url;
    showImageModal.value = true;
};

// Función para alternar estado (Activar/Desactivar)
const toggleStatus = (user) => {
    event.stopPropagation(); 

    router.patch(route('users.toggle-status', user.id), {}, {
        preserveScroll: true,
        onSuccess: () => {
            const statusText = !user.is_active ? 'activado' : 'desactivado'; 
            const newStatus = user.is_active ? 'Inactivo' : 'Activo';
            const type = user.is_active ? 'warning' : 'success';
            
            notification.create({
                type: type,
                title: 'Estado Actualizado',
                content: `El usuario ${user.name} ahora está ${newStatus}.`,
                duration: 3000
            });
        }
    });
};

// Función para confirmar y eliminar usuario
const confirmDelete = (user) => {
    event.stopPropagation();
    
    dialog.warning({
        title: 'Confirmar Eliminación',
        content: `¿Estás seguro de eliminar a "${user.name}"? Esta acción no se puede deshacer.`,
        positiveText: 'Sí, eliminar',
        negativeText: 'Cancelar',
        onPositiveClick: () => {
            router.delete(route('users.destroy', user.id), {
                preserveScroll: true,
                onSuccess: () => {
                    notification.success({
                        title: 'Usuario Eliminado',
                        content: 'El usuario ha sido eliminado correctamente.',
                        duration: 3000
                    });
                },
                onError: () => {
                    notification.error({
                        title: 'Error',
                        content: 'No se pudo eliminar el usuario.',
                        duration: 3000
                    });
                }
            });
        }
    });
};

// Ir al detalle del usuario
const goToShow = (userId) => {
    router.get(route('users.show', userId));
};

// --- Configuración de Columnas para Escritorio (Naive UI DataTable) ---
const createColumns = () => [
    {
        title: 'Avatar',
        key: 'profile_photo_url',
        width: 80,
        render(row) {
            return h(NAvatar, {
                round: true,
                size: 'medium',
                src: row.profile_photo_url,
                class: 'cursor-pointer hover:opacity-80 transition-opacity',
                onClick: (e) => {
                    e.stopPropagation();
                    openImageModal(row.profile_photo_url);
                }
            });
        }
    },
    {
        title: 'Nombre',
        key: 'name',
        sorter: 'default',
        render(row) {
            return h('div', { class: 'font-medium text-gray-800' }, row.name);
        }
    },
    {
        title: 'Correo',
        key: 'email',
        render(row) {
            return h('span', { class: 'text-gray-500' }, row.email);
        }
    },
    {
        title: 'Rol',
        key: 'role',
        render(row) {
            // Asumimos que viene 'roles' con Spatie y tomamos el primero
            if (row.roles && row.roles.length > 0) {
                return h(NTag, { type: 'default', size: 'small', bordered: false, round: true }, () => row.roles[0].name);
            }
            return h('span', { class: 'text-gray-400 italic text-xs' }, 'Sin rol');
        }
    },
    {
        title: 'Sucursal',
        key: 'branch',
        render(row) {
            return row.branch 
                ? h(NTag, { type: 'info', size: 'small', bordered: false, round: true }, () => row.branch.name) 
                : h('span', { class: 'text-gray-400 italic' }, 'Sin asignar');
        }
    },
    {
        title: 'Estado',
        key: 'is_active',
        width: 100,
        render(row) {
            return h(
                NTag,
                {
                    type: row.is_active ? 'success' : 'error',
                    bordered: false,
                    round: true,
                    size: 'small'
                },
                () => row.is_active ? 'Activo' : 'Inactivo'
            );
        }
    },
    {
        title: 'Acciones',
        key: 'actions',
        width: 180, // Aumentamos un poco el ancho para que quepan los 4 botones
        render(row) {
            const buttons = [];

            // Botón Ver Detalle
            buttons.push(h(
                NButton,
                {
                    circle: true,
                    size: 'small',
                    type: 'info',
                    ghost: true,
                    onClick: (e) => {
                        e.stopPropagation();
                        goToShow(row.id);
                    }
                },
                { icon: () => h(NIcon, null, { default: () => h(EyeOutline) }) }
            ));

            // Botón Editar
            if (hasPermission('users.edit')) {
                buttons.push(h(
                    NButton,
                    {
                        circle: true,
                        size: 'small',
                        type: 'warning',
                        ghost: true,
                        onClick: (e) => {
                            e.stopPropagation();
                            router.get(route('users.edit', row.id));
                        }
                    },
                    { icon: () => h(NIcon, null, { default: () => h(CreateOutline) }) }
                ));
            }

            // Botón Toggle Status
            if (hasPermission('users.toggle_status')) {
                buttons.push(h(
                    NButton,
                    {
                        circle: true,
                        size: 'small',
                        type: row.is_active ? 'error' : 'success',
                        secondary: true,
                        onClick: (e) => toggleStatus(row)
                    },
                    { icon: () => h(NIcon, null, { default: () => h(PowerOutline) }) }
                ));
            }

            // Botón Eliminar
            if (hasPermission('users.destroy')) {
                buttons.push(h(
                    NButton,
                    {
                        circle: true,
                        size: 'small',
                        type: 'error',
                        ghost: true,
                        onClick: (e) => confirmDelete(row)
                    },
                    { icon: () => h(NIcon, null, { default: () => h(TrashOutline) }) }
                ));
            }

            return h(NSpace, {}, () => buttons);
        }
    }
];

const columns = createColumns();

// Paginación manual para Naive UI
const handlePageChange = (page) => {
    router.get(props.users.path + '?page=' + page, { search: search.value }, { preserveState: true });
};

// Propiedades de la fila
const rowProps = (row) => {
  return {
    style: 'cursor: pointer;',
    onClick: () => goToShow(row.id)
  }
}
</script>

<template>
    <AppLayout title="Usuarios">
        <template #header>
            <div class="flex flex-col md:flex-row justify-between items-center gap-4">
                <h2 class="font-semibold text-2xl text-gray-800 leading-tight">
                    Usuarios
                </h2>
                <!-- Botón Registrar -->
                <Link v-if="hasPermission('users.create')" :href="route('users.create')">
                    <n-button type="primary" round class="shadow-md hover:shadow-lg transition-shadow duration-300">
                        <template #icon>
                            <n-icon><AddOutline /></n-icon>
                        </template>
                        Nuevo Usuario
                    </n-button>
                </Link>
            </div>
        </template>

        <div class="py-8 min-h-screen">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                
                <!-- Barra de búsqueda -->
                <div class="mb-6 px-4 sm:px-0">
                    <n-input 
                        v-model:value="search" 
                        type="text" 
                        placeholder="Buscar por nombre o correo..." 
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

                <!-- VISTA DE ESCRITORIO (Tabla) -->
                <div class="hidden md:block bg-white/80 backdrop-blur-xl rounded-3xl shadow-lg border border-gray-100 overflow-hidden">
                    <n-data-table
                        :columns="columns"
                        :data="users.data"
                        :pagination="false"
                        :bordered="false"
                        single-column
                        :row-props="rowProps"
                        class="iphone-table"
                    />
                    <!-- Paginación Personalizada -->
                    <div class="p-4 flex justify-end border-t border-gray-100" v-if="users.total > 0">
                        <n-pagination
                            :page="users.current_page"
                            :page-count="users.last_page"
                            :on-update:page="handlePageChange"
                        />
                    </div>
                </div>

                <!-- VISTA MÓVIL (Tarjetas) -->
                <div class="md:hidden space-y-4 px-4 sm:px-0">
                    <div v-if="users.data.length === 0" class="flex justify-center mt-10">
                        <n-empty description="No se encontraron usuarios" />
                    </div>

                    <div 
                        v-for="user in users.data" 
                        :key="user.id" 
                        class="bg-white rounded-2xl p-4 shadow-sm border border-gray-100 flex items-center gap-4 relative overflow-hidden cursor-pointer active:scale-[0.98] transition-transform duration-200"
                        @click="goToShow(user.id)"
                    >
                        <!-- Indicador visual -->
                        <div 
                            class="absolute left-0 top-0 bottom-0 w-1.5"
                            :class="user.is_active ? 'bg-green-400' : 'bg-red-400'"
                        ></div>

                        <!-- Avatar -->
                        <div class="flex-shrink-0 ml-2" @click.stop>
                            <n-avatar 
                                round 
                                :size="54" 
                                :src="user.profile_photo_url" 
                                class="cursor-pointer border-2 border-white shadow-sm"
                                @click="openImageModal(user.profile_photo_url)"
                            />
                        </div>

                        <!-- Info -->
                        <div class="flex-grow min-w-0">
                            <div class="flex justify-between items-start">
                                <h3 class="text-lg font-bold text-gray-800 truncate">{{ user.name }}</h3>
                                <!-- Botones móvil -->
                                <div class="flex gap-2">
                                     <button 
                                        @click.stop="goToShow(user.id)"
                                        class="text-blue-500 hover:bg-blue-50 p-1 rounded-full transition"
                                    >
                                        <n-icon size="20"><EyeOutline /></n-icon>
                                    </button>
                                     <button 
                                        v-if="hasPermission('users.edit')"
                                        @click.stop="router.get(route('users.edit', user.id))"
                                        class="text-amber-500 hover:bg-amber-50 p-1 rounded-full transition"
                                    >
                                        <n-icon size="20"><CreateOutline /></n-icon>
                                    </button>
                                    <button 
                                        v-if="hasPermission('users.toggle_status')"
                                        @click.stop="toggleStatus(user)"
                                        :class="user.is_active ? 'text-red-500 hover:bg-red-50' : 'text-green-500 hover:bg-green-50'"
                                        class="p-1 rounded-full transition"
                                    >
                                        <n-icon size="20"><PowerOutline /></n-icon>
                                    </button>
                                    <button 
                                        v-if="hasPermission('users.destroy')"
                                        @click.stop="confirmDelete(user)"
                                        class="text-red-600 hover:bg-red-50 p-1 rounded-full transition"
                                    >
                                        <n-icon size="20"><TrashOutline /></n-icon>
                                    </button>
                                </div>
                            </div>
                            <p class="text-sm text-gray-500 truncate">{{ user.email }}</p>
                            
                            <!-- Rol y Sucursal en móvil -->
                            <div class="mt-2 flex items-center gap-2 flex-wrap">
                                <span 
                                    class="text-xs px-2 py-0.5 rounded-full bg-blue-100 text-blue-600 font-medium"
                                    v-if="user.roles && user.roles.length"
                                >
                                    {{ user.roles[0].name }}
                                </span>
                                <span 
                                    class="text-xs px-2 py-0.5 rounded-full bg-gray-100 text-gray-600 font-medium"
                                    v-if="user.branch"
                                >
                                    {{ user.branch.name }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Paginación Móvil -->
                    <div class="flex justify-center mt-6" v-if="users.total > 0">
                        <n-pagination
                            simple
                            :page="users.current_page"
                            :page-count="users.last_page"
                            :on-update:page="handlePageChange"
                        />
                    </div>
                </div>

            </div>
        </div>

        <!-- Modal para ver foto -->
        <n-modal v-model:show="showImageModal">
            <n-card
                style="width: 90%; max-width: 500px; padding: 0; overflow: hidden; border-radius: 16px;"
                :bordered="false"
                role="dialog"
                aria-modal="true"
            >
                <img :src="selectedImage" class="w-full h-auto object-cover block" />
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