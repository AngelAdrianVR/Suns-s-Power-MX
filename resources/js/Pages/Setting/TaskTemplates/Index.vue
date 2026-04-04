<script setup>
import { ref, computed } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { usePermissions } from '@/Composables/usePermissions';
import { 
    NButton, NTabs, NTabPane, NIcon, NModal, NInput, createDiscreteApi, NPopconfirm, NEmpty
} from 'naive-ui';
import { 
    SettingsOutline, ListOutline, CreateOutline, TrashOutline, CheckmarkCircleOutline, CloseOutline
} from '@vicons/ionicons5';

// Importar los Parciales
import TaskColumn from './Partials/TaskColumn.vue';
import EvidenceColumn from './Partials/EvidenceColumn.vue';
import ProductColumn from './Partials/ProductColumn.vue';

const props = defineProps({
    task_templates: Array,
    evidence_templates: Array,
    system_types: Array,
    assignable_users: Array
});

const { hasPermission } = usePermissions();
const { notification } = createDiscreteApi(['notification']);

// ================= SINCRONIZACIÓN AUTOMÁTICA =================
const triggerSync = () => {
    router.post(route('service-orders.sync-evidences'), {}, {
        preserveScroll: true,
        preserveState: true
    });
};

// ================= ESTADO TIPOS DE SISTEMA =================
const normalizedSystemTypes = computed(() => {
    return props.system_types.map(s => typeof s === 'string' ? { id: s, name: s } : s);
});

const activeSystemType = ref(normalizedSystemTypes.value[0]?.name || 'Interconectado');

const showSystemModal = ref(false);
const editingSystemId = ref(null);
const editSystemName = ref('');

const systemForm = useForm({ name: '' });

const handleAddSystem = () => {
    if(!systemForm.name) return;
    systemForm.post(route('system-types.store'), {
        preserveScroll: true,
        onSuccess: () => {
            activeSystemType.value = systemForm.name;
            systemForm.reset();
            notification.success({ title: 'Creado', content: 'Tipo de sistema agregado correctamente.', duration: 3000 });
        }
    });
};

const startEditSystem = (sys) => {
    if (typeof sys.id === 'string') {
        notification.warning({ title: 'Atención', content: 'Aún no migraste a base de datos. Completa el backend primero.', duration: 5000 });
        return;
    }
    editingSystemId.value = sys.id;
    editSystemName.value = sys.name;
};

const saveEditSystem = (sys) => {
    router.put(route('system-types.update', sys.id), { name: editSystemName.value }, {
        preserveScroll: true,
        onSuccess: () => {
            if (activeSystemType.value === sys.name) activeSystemType.value = editSystemName.value;
            editingSystemId.value = null;
            notification.success({ title: 'Actualizado', content: 'Nombre actualizado.', duration: 3000 });
        }
    });
};

const handleDeleteSystem = (id) => {
    if (typeof id === 'string') return;
    router.delete(route('system-types.destroy', id), {
        preserveScroll: true,
        onSuccess: () => {
            notification.success({ title: 'Eliminado', content: 'Tipo de sistema eliminado.', duration: 3000 });
            if (normalizedSystemTypes.value.length) {
                activeSystemType.value = normalizedSystemTypes.value[0].name;
            }
        }
    });
};

// ================= HELPERS PARA FILTRAR DATOS A LOS PARCIALES =================
const getTasksForSystem = (systemName) => {
    return props.task_templates.filter(t => t.system_type === systemName);
};

const getEvidencesForSystem = (systemName) => {
    return props.evidence_templates
        .filter(e => e.system_type === systemName)
        .sort((a, b) => (a.order || 0) - (b.order || 0));
};

const getEvidenceOptions = (systemName) => {
    return getEvidencesForSystem(systemName).map(e => ({ label: e.title, value: e.id }));
};

const getTaskOptions = (systemName) => {
    return getTasksForSystem(systemName).map(t => ({ label: t.title, value: t.id }));
};
</script>

<template>
    <AppLayout title="Gestor de Automatización">
        <template #header>
            <div class="flex justify-between items-center gap-4">
                <div class="flex items-center gap-3">
                    <n-icon size="28" class="text-indigo-600"><SettingsOutline /></n-icon>
                    <div>
                        <h2 class="font-bold text-xl text-gray-800 leading-tight">Gestor de Automatización</h2>
                        <p class="text-sm text-gray-500 mt-1">Configura tareas, evidencias y productos predeterminados por tipo de sistema.</p>
                    </div>
                </div>
                <n-button v-if="hasPermission('system_type.create')" type="primary" ghost round @click="showSystemModal = true">
                    <template #icon><n-icon><ListOutline /></n-icon></template> Gestionar Tipos de Sistema
                </n-button>
            </div>
        </template>

        <div class="py-8 min-h-screen">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                
                <div class="bg-white rounded-3xl shadow-lg border border-gray-100 overflow-hidden">
                    <n-tabs type="line" size="large" animated class="px-6 pt-4" v-model:value="activeSystemType">
                        <n-tab-pane v-for="sys in normalizedSystemTypes" :key="sys.name" :name="sys.name" :tab="sys.name">
                            
                            <div class="py-4 grid grid-cols-1 lg:grid-cols-3 gap-6">
                                <!-- PARCIAL 1: Tareas -->
                                <TaskColumn 
                                    :sys="sys" 
                                    :tasks="getTasksForSystem(sys.name)"
                                    :assignable-users="assignable_users"
                                    :evidence-options="getEvidenceOptions(sys.name)"
                                    @sync="triggerSync"
                                />

                                <!-- PARCIAL 2: Evidencias -->
                                <EvidenceColumn 
                                    :sys="sys"
                                    :evidences="getEvidencesForSystem(sys.name)"
                                    :task-options="getTaskOptions(sys.name)"
                                    @sync="triggerSync"
                                />

                                <!-- PARCIAL 3: Productos / Material -->
                                <ProductColumn 
                                    :sys="sys"
                                    @sync="triggerSync"
                                />
                            </div>
                            
                        </n-tab-pane>
                    </n-tabs>
                </div>

            </div>
        </div>

        <!-- MODAL TIPO DE SISTEMAS -->
        <n-modal v-model:show="showSystemModal" preset="card" class="max-w-md" title="Gestionar Tipos de Sistema">
            <div v-if="hasPermission('system_type.create')" class="flex gap-2 mb-6">
                <n-input v-model:value="systemForm.name" placeholder="Nuevo tipo (ej. Interconectado)" @keyup.enter="handleAddSystem" />
                <n-button type="primary" class="bg-indigo-600" @click="handleAddSystem" :loading="systemForm.processing" :disabled="!systemForm.name">
                    Agregar
                </n-button>
            </div>
            
            <div class="space-y-2 max-h-96 overflow-y-auto pr-1">
                <div v-for="sys in normalizedSystemTypes" :key="sys.id" class="flex justify-between items-center bg-gray-50 p-2 rounded-xl border border-gray-100 hover:border-gray-300 transition-colors">
                    
                    <span v-if="editingSystemId !== sys.id" class="font-medium text-gray-700 ml-2">{{ sys.name }}</span>
                    <n-input v-else v-model:value="editSystemName" size="small" class="w-full mr-2 ml-1" @keyup.enter="saveEditSystem(sys)" />

                    <div class="flex gap-1">
                        <template v-if="editingSystemId !== sys.id">
                            <n-button v-if="hasPermission('system_type.edit')" circle quaternary size="small" type="info" @click="startEditSystem(sys)">
                                <template #icon><n-icon><CreateOutline/></n-icon></template>
                            </n-button>
                            <n-popconfirm v-if="hasPermission('system_type.delete')" @positive-click="handleDeleteSystem(sys.id)" positive-text="Sí, eliminar" negative-text="Cancelar">
                                <template #trigger>
                                    <n-button circle quaternary size="small" type="error">
                                        <template #icon><n-icon><TrashOutline/></n-icon></template>
                                    </n-button>
                                </template>
                                ¿Eliminar este tipo de sistema? 
                                <br><span class="text-xs text-red-500">Se ocultarán temporalmente las tareas/evidencias asociadas si no actualizas su nombre.</span>
                            </n-popconfirm>
                        </template>
                        <template v-else>
                            <n-button circle quaternary size="small" type="success" @click="saveEditSystem(sys)">
                                <template #icon><n-icon><CheckmarkCircleOutline/></n-icon></template>
                            </n-button>
                            <n-button circle quaternary size="small" @click="editingSystemId = null">
                                <template #icon><n-icon><CloseOutline/></n-icon></template>
                            </n-button>
                        </template>
                    </div>
                </div>
                <n-empty v-if="normalizedSystemTypes.length === 0" description="No hay tipos registrados" />
            </div>
        </n-modal>

    </AppLayout>
</template>