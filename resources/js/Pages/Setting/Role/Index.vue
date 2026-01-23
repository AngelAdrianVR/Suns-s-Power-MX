<script setup>
import { ref, computed } from 'vue';
import { usePermissions } from '@/Composables/usePermissions';
import { Head, useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { 
    NCard, NButton, NIcon, NTag, NDataTable, NModal, NForm, NFormItem, NInput, NGrid, NGi, 
    NTabs, NTabPane, NSwitch, NSpace, NDivider, NCollapse, NCollapseItem, createDiscreteApi, NPopconfirm, NSelect
} from 'naive-ui';
import { 
    ShieldCheckmarkOutline, AddOutline, CreateOutline, TrashOutline, 
    SettingsOutline, KeyOutline, SaveOutline, CloseOutline
} from '@vicons/ionicons5';

const props = defineProps({
    roles: Array,
    groupedPermissions: Object, // { 'Modulo': [ {id, name, description, module}... ] }
    isDeveloper: Boolean
});

const { hasPermission } = usePermissions();

const { notification } = createDiscreteApi(['notification']);

// --- ESTADO Y COLUMNAS TABLA ROLES ---
const showRoleModal = ref(false);
const editingRole = ref(null);

const roleColumns = [
    { title: 'ID', key: 'id', width: 60, align: 'center' },
    { title: 'Nombre del Rol', key: 'name', sorter: 'default' },
    { 
        title: 'Permisos Asignados', 
        key: 'permissions_count',
        render(row) {
            return h(NTag, { type: row.permissions_count > 0 ? 'success' : 'warning', size: 'small', bordered: false }, 
                { default: () => `${row.permissions_count} permisos` }
            );
        }
    },
    { title: 'Fecha Creación', key: 'created_at' },
    {
        title: 'Acciones',
        key: 'actions',
        render(row) {
            // Verificar permiso para gestionar roles
            if (!hasPermission('roles.manage')) {
                return h('span', { class: 'text-gray-400 text-xs italic' }, 'Solo lectura');
            }

            return h(NSpace, {}, {
                default: () => [
                    // Botón Editar
                    h(NButton, { 
                        size: 'small', quaternary: true, circle: true, type: 'warning',
                        onClick: () => openEditRole(row) 
                    }, { icon: () => h(NIcon, null, { default: () => h(CreateOutline) }) }),
                    
                    // Botón Eliminar (Protegido para ID 1)
                    row.id !== 1 ? h(NPopconfirm, {
                        onPositiveClick: () => deleteRole(row)
                    }, {
                        trigger: () => h(NButton, { size: 'small', quaternary: true, circle: true, type: 'error' }, { icon: () => h(NIcon, null, { default: () => h(TrashOutline) }) }),
                        default: () => '¿Eliminar este rol? Los usuarios perderán estos accesos.'
                    }) : null
                ]
            });
        }
    }
];

// --- FORMULARIO ROL ---
const formRole = useForm({
    name: '',
    permissions: [] // Array de IDs
});

const openCreateRole = () => {
    editingRole.value = null;
    formRole.reset();
    formRole.permissions = [];
    showRoleModal.value = true;
};

const openEditRole = (role) => {
    editingRole.value = role;
    formRole.name = role.name;
    formRole.permissions = [...role.permissions]; // Copia de los IDs
    showRoleModal.value = true;
};

const submitRole = () => {
    if (editingRole.value) {
        formRole.put(route('roles.update', editingRole.value.id), {
            onSuccess: () => {
                showRoleModal.value = false;
                notification.success({ content: 'Rol actualizado correctamente', duration: 3000 });
            }
        });
    } else {
        formRole.post(route('roles.store'), {
            onSuccess: () => {
                showRoleModal.value = false;
                notification.success({ content: 'Rol creado correctamente', duration: 3000 });
            }
        });
    }
};

const deleteRole = (role) => {
    router.delete(route('roles.destroy', role.id), {
        onSuccess: () => notification.success({ content: 'Rol eliminado', duration: 3000 }),
        onError: () => notification.error({ content: 'No se pudo eliminar el rol', duration: 3000 })
    });
};

// --- GESTIÓN DE PERMISOS (SOLO DEV) ---
const showPermissionModal = ref(false);
const editingPermission = ref(null);

const formPermission = useForm({
    name: '',       // Clave interna (ej: users.create)
    description: '', // Texto legible
    module: ''      // Categoría
});

// Opciones de módulos para el select (extraídas de los permisos existentes o hardcodeadas)
const moduleOptions = computed(() => {
    const modules = Object.keys(props.groupedPermissions || {});
    // Agregamos opciones por defecto si está vacío al inicio
    const defaults = ['Productos', 'Ordenes de servicio', 'Compras', 'Clientes', 'Tickets', 'Usuarios', 'Configuraciones'];
    const combined = [...new Set([...modules, ...defaults])];
    return combined.map(m => ({ label: m, value: m }));
});

const openCreatePermission = () => {
    editingPermission.value = null;
    formPermission.reset();
    // No cerramos el modal principal, trabajamos en el mismo modal de gestión
};

const editPermission = (perm) => {
    editingPermission.value = perm;
    formPermission.name = perm.name;
    formPermission.description = perm.description;
    // Buscamos el módulo al que pertenece iterando groupedPermissions
    let foundModule = '';
    for (const [modName, perms] of Object.entries(props.groupedPermissions)) {
        if (perms.some(p => p.id === perm.id)) {
            foundModule = modName;
            break;
        }
    }
    formPermission.module = foundModule;
};

const submitPermission = () => {
    if (editingPermission.value) {
        formPermission.put(route('permissions.update', editingPermission.value.id), {
            preserveScroll: true,
            onSuccess: () => {
                notification.success({ content: 'Permiso actualizado' });
                editingPermission.value = null;
                formPermission.reset();
            }
        });
    } else {
        formPermission.post(route('permissions.store'), {
            preserveScroll: true,
            onSuccess: () => {
                notification.success({ content: 'Permiso creado', duration: 3000 });
                formPermission.reset();
            }
        });
    }
};

const deletePermission = (permId) => {
    router.delete(route('permissions.destroy', permId), {
        preserveScroll: true,
        onSuccess: () => notification.success({ content: 'Permiso eliminado' })
    });
};

// Helper para togglear permisos en rol
const togglePermission = (permId) => {
    const index = formRole.permissions.indexOf(permId);
    if (index === -1) {
        formRole.permissions.push(permId);
    } else {
        formRole.permissions.splice(index, 1);
    }
};

import { h } from 'vue'; 

</script>

<template>
    <AppLayout title="Roles y Permisos">
        <template #header>
            <div class="lg:flex items-center justify-between w-11/12 mx-auto">
                <div>
                    <h2 class="font-semibold text-xl text-gray-800 leading-tight flex items-center gap-2">
                        <n-icon class="text-indigo-600"><ShieldCheckmarkOutline /></n-icon>
                        Roles y Permisos
                    </h2>
                    <p class="text-sm text-gray-500">Administra los niveles de acceso del sistema</p>
                </div>
                <div class="mt-2 lg:mt-0 flex gap-2">
                    <!-- Botón restringido solo para desarrolladores (crear nuevos permisos) -->
                    <n-button v-if="isDeveloper" secondary type="info" @click="showPermissionModal = true">
                        <template #icon><n-icon><KeyOutline /></n-icon></template>
                        Gestionar Permisos (Dev)
                    </n-button>
                    <!-- Botón para usuarios con permisos de gestión de roles -->
                    <n-button v-if="hasPermission('roles.manage')" type="primary" @click="openCreateRole">
                        <template #icon><n-icon><AddOutline /></n-icon></template>
                        Nuevo Rol
                    </n-button>
                </div>
            </div>
        </template>
        <div class="py-8">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                
                <n-card :bordered="false" class="shadow-sm rounded-2xl">
                    <n-data-table
                        :columns="roleColumns"
                        :data="roles"
                        :pagination="{ pageSize: 10 }"
                        :bordered="false"
                    />
                </n-card>

                <!-- MODAL DE CREACIÓN / EDICIÓN DE ROL -->
                <n-modal v-model:show="showRoleModal" class="custom-modal" preset="card" :style="{ width: '800px' }">
                    <template #header>
                        <div class="flex items-center gap-2">
                            <n-icon size="24" class="text-indigo-600"><ShieldCheckmarkOutline /></n-icon>
                            <span>{{ editingRole ? 'Editar Rol' : 'Crear Nuevo Rol' }}</span>
                        </div>
                    </template>

                    <n-form size="large" class="mt-4">
                        <n-form-item label="Nombre del Rol (Ej. Vendedor, Gerente)">
                            <n-input v-model:value="formRole.name" placeholder="Nombre descriptivo" />
                        </n-form-item>

                        <!-- SECCIÓN DE PERMISOS -->
                        <!-- CORRECCIÓN: Eliminado v-if="isDeveloper". Ahora es visible si existen permisos cargados -->
                        <div v-if="groupedPermissions">
                            <n-divider title-placement="left" class="text-gray-500 text-xs uppercase font-bold tracking-wider">
                                Asignación de Permisos
                            </n-divider>
                            
                            <div class="h-96 overflow-y-auto pr-2 bg-gray-50 p-4 rounded-xl border border-gray-100">
                                <n-collapse :default-expanded-names="Object.keys(groupedPermissions)">
                                    <n-collapse-item 
                                        v-for="(perms, moduleName) in groupedPermissions" 
                                        :key="moduleName" 
                                        :title="moduleName" 
                                        :name="moduleName"
                                    >
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                            <div 
                                                v-for="perm in perms" 
                                                :key="perm.id"
                                                class="flex items-start gap-3 p-3 rounded-lg border transition-colors cursor-pointer hover:bg-white"
                                                :class="formRole.permissions.includes(perm.id) ? 'bg-indigo-50 border-indigo-200' : 'bg-white border-gray-100'"
                                                @click="togglePermission(perm.id)"
                                            >
                                                <n-switch 
                                                    :value="formRole.permissions.includes(perm.id)" 
                                                    size="small"
                                                    @update:value="togglePermission(perm.id)"
                                                    class="mt-1"
                                                />
                                                <div class="flex flex-col">
                                                    <span class="text-sm font-semibold text-gray-700">{{ perm.description }}</span>
                                                    <span class="text-xs text-gray-400 font-mono">{{ perm.name }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </n-collapse-item>
                                </n-collapse>
                            </div>
                        </div>
                        <div v-else class="p-4 bg-gray-50 text-gray-500 rounded-lg text-sm flex items-center gap-2">
                            <n-icon><SettingsOutline /></n-icon>
                            No hay permisos disponibles para asignar.
                        </div>
                    </n-form>

                    <template #footer>
                        <div class="flex justify-end gap-3">
                            <n-button @click="showRoleModal = false">Cancelar</n-button>
                            <n-button type="primary" @click="submitRole" :loading="formRole.processing">
                                <template #icon><n-icon><SaveOutline /></n-icon></template>
                                Guardar Rol
                            </n-button>
                        </div>
                    </template>
                </n-modal>

                <!-- MODAL GESTIÓN DE PERMISOS (SOLO DEV) -->
                <!-- Este modal sigue existiendo pero solo se abre desde el botón restringido en el header -->
                <n-modal v-model:show="showPermissionModal" preset="card" :style="{ width: '900px' }" title="Gestión Avanzada de Permisos">
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        
                        <!-- Columna Izquierda: Formulario -->
                        <div class="lg:col-span-1 border-r border-gray-100 pr-6">
                            <h3 class="font-bold text-gray-700 mb-4 flex items-center gap-2">
                                <n-icon :component="editingPermission ? SettingsOutline : AddOutline" />
                                {{ editingPermission ? 'Editar Permiso' : 'Nuevo Permiso' }}
                            </h3>
                            
                            <n-form size="medium">
                                <n-form-item label="Módulo" path="module">
                                    <n-select 
                                        v-model:value="formPermission.module" 
                                        :options="moduleOptions" 
                                        filterable 
                                        tag 
                                        placeholder="Selecciona o escribe..." 
                                    />
                                </n-form-item>
                                <n-form-item label="Clave Interna (ej: users.create)" path="name">
                                    <n-input v-model:value="formPermission.name" placeholder="modulo.accion" />
                                </n-form-item>
                                <n-form-item label="Descripción Legible" path="description">
                                    <n-input 
                                        v-model:value="formPermission.description" 
                                        type="textarea" 
                                        placeholder="Lo que verá el usuario..." 
                                        :rows="3"
                                    />
                                </n-form-item>

                                <div class="flex flex-col gap-2 mt-2">
                                    <n-button type="primary" block @click="submitPermission" :loading="formPermission.processing">
                                        {{ editingPermission ? 'Actualizar' : 'Crear Permiso' }}
                                    </n-button>
                                    <n-button v-if="editingPermission" block ghost @click="openCreatePermission">
                                        Cancelar Edición
                                    </n-button>
                                </div>
                            </n-form>
                        </div>

                        <!-- Columna Derecha: Lista de Permisos Existentes -->
                        <div class="lg:col-span-2 h-[500px] overflow-y-auto pr-2">
                            <n-collapse :default-expanded-names="Object.keys(groupedPermissions)">
                                <n-collapse-item 
                                    v-for="(perms, moduleName) in groupedPermissions" 
                                    :key="moduleName" 
                                    :title="moduleName + ' (' + perms.length + ')'" 
                                    :name="moduleName"
                                >
                                    <div class="space-y-2">
                                        <div 
                                            v-for="perm in perms" 
                                            :key="perm.id"
                                            class="flex items-center justify-between p-3 bg-gray-50 rounded-lg border border-gray-100 hover:border-blue-200 transition-colors group"
                                        >
                                            <div class="flex flex-col">
                                                <span class="font-semibold text-sm text-gray-700">{{ perm.description }}</span>
                                                <span class="text-xs text-gray-400 font-mono">{{ perm.name }}</span>
                                            </div>
                                            <div class="flex gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                                <n-button circle size="tiny" quaternary type="info" @click="editPermission(perm)">
                                                    <template #icon><n-icon><CreateOutline /></n-icon></template>
                                                </n-button>
                                                <n-popconfirm @positive-click="deletePermission(perm.id)">
                                                    <template #trigger>
                                                        <n-button circle size="tiny" quaternary type="error">
                                                            <template #icon><n-icon><TrashOutline /></n-icon></template>
                                                        </n-button>
                                                    </template>
                                                    ¿Eliminar este permiso permanentemente?
                                                </n-popconfirm>
                                            </div>
                                        </div>
                                    </div>
                                </n-collapse-item>
                            </n-collapse>
                        </div>
                    </div>
                </n-modal>

            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
/* Estilos para scrollbars finos si es necesario */
</style>