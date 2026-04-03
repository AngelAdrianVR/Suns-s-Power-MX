<script setup>
import { ref, computed } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { usePermissions } from '@/Composables/usePermissions';
import { 
    NButton, NCard, NTabs, NTabPane, NIcon, NTag, NAvatar, 
    NModal, NForm, NFormItem, NInput, NInputNumber, NSelect, createDiscreteApi, NPopconfirm, NEmpty, NSwitch
} from 'naive-ui';
import { 
    AddOutline, CreateOutline, TrashOutline, SettingsOutline, CheckmarkCircleOutline, CameraOutline, MenuOutline, CloseOutline, ListOutline, InformationCircleOutline
} from '@vicons/ionicons5';

const props = defineProps({
    task_templates: Array,
    evidence_templates: Array,
    system_types: Array,
    assignable_users: Array
});

const { hasPermission } = usePermissions();
const { notification } = createDiscreteApi(['notification']);

// ================= ESTADO TIPOS DE SISTEMA (NUEVO GESTOR) =================
const normalizedSystemTypes = computed(() => {
    // Normaliza para soportar tanto strings (viejo) como objetos (nuevo BD)
    return props.system_types.map(s => typeof s === 'string' ? { id: s, name: s } : s);
});

// TABS
const activeSystemType = ref(normalizedSystemTypes.value[0]?.name || 'Interconectado');

// Lógica del modal de tipos de sistema
const showSystemModal = ref(false);
const editingSystemId = ref(null);
const editSystemName = ref('');

const systemForm = useForm({ name: '' });

const handleAddSystem = () => {
    if(!systemForm.name) return;
    systemForm.post(route('system-types.store'), {
        preserveScroll: true,
        onSuccess: () => {
            activeSystemType.value = systemForm.name; // Mover a la nueva pestaña
            systemForm.reset();
            notification.success({ title: 'Creado', content: 'Tipo de sistema agregado correctamente.' });
        }
    });
};

const startEditSystem = (sys) => {
    if (typeof sys.id === 'string') {
        notification.warning({ title: 'Atención', content: 'Aún no migraste a base de datos. Completa el backend primero.' });
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
            notification.success({ title: 'Actualizado', content: 'Nombre actualizado.' });
        }
    });
};

const handleDeleteSystem = (id) => {
    if (typeof id === 'string') return;
    router.delete(route('system-types.destroy', id), {
        preserveScroll: true,
        onSuccess: () => {
            notification.success({ title: 'Eliminado', content: 'Tipo de sistema eliminado.' });
            if (normalizedSystemTypes.value.length) {
                activeSystemType.value = normalizedSystemTypes.value[0].name;
            }
        }
    });
};


// ================= ESTADO MODAL TAREAS =================
const showTaskModal = ref(false);
const isEditingTask = ref(false);
const taskForm = useForm({
    id: null,
    system_type: activeSystemType.value,
    title: '',
    description: '',
    priority: 'Media',
    start_days: 0,      // <-- NUEVO
    duration_days: 1,   // <-- NUEVO
    users: []
});

const priorityOptions = [
    { label: 'Baja', value: 'Baja' },
    { label: 'Media', value: 'Media' },
    { label: 'Alta', value: 'Alta' }
];

const usersOptions = computed(() => {
    return props.assignable_users.map(u => ({ label: u.name, value: u.id }));
});

const tasksBySystem = computed(() => {
    return props.task_templates.filter(t => t.system_type === activeSystemType.value);
});

const openAddTaskModal = (sysType) => {
    isEditingTask.value = false;
    taskForm.reset();
    taskForm.system_type = sysType.name || sysType;
    taskForm.start_days = 0; // Default por si reset() no lo cubre
    taskForm.duration_days = 1;
    showTaskModal.value = true;
};

const openEditTaskModal = (template) => {
    isEditingTask.value = true;
    taskForm.id = template.id;
    taskForm.system_type = template.system_type;
    taskForm.title = template.title;
    taskForm.description = template.description || '';
    taskForm.priority = template.priority;
    taskForm.start_days = template.start_days ?? 0;        // <-- NUEVO
    taskForm.duration_days = template.duration_days ?? 1;  // <-- NUEVO
    taskForm.users = template.users.map(u => u.id); 
    showTaskModal.value = true;
};

const handleTaskSubmit = () => {
    if (isEditingTask.value) {
        taskForm.put(route('task-templates.update', taskForm.id), {
            onSuccess: () => { showTaskModal.value = false; notification.success({ title: 'Actualizado', content: 'Plantilla de tarea actualizada.' }); }
        });
    } else {
        taskForm.post(route('task-templates.store'), {
            onSuccess: () => { showTaskModal.value = false; notification.success({ title: 'Creado', content: 'Plantilla de tarea guardada.' }); }
        });
    }
};

const handleDeleteTask = (id) => {
    router.delete(route('task-templates.destroy', id));
};

// ================= ESTADO MODAL EVIDENCIAS =================
const showEvidenceModal = ref(false);
const isEditingEvidence = ref(false);
const evidenceForm = useForm({
    id: null,
    system_type: activeSystemType.value,
    title: '',
    description: '',
    allows_multiple: false
});

const evidencesBySystem = computed(() => {
    return props.evidence_templates
        .filter(e => e.system_type === activeSystemType.value)
        .sort((a, b) => (a.order || 0) - (b.order || 0));
});

const openAddEvidenceModal = (sysType) => {
    isEditingEvidence.value = false;
    evidenceForm.reset();
    evidenceForm.system_type = sysType.name || sysType;
    evidenceForm.allows_multiple = false;
    showEvidenceModal.value = true;
};

const openEditEvidenceModal = (evidence) => {
    isEditingEvidence.value = true;
    evidenceForm.id = evidence.id;
    evidenceForm.system_type = evidence.system_type;
    evidenceForm.title = evidence.title;
    evidenceForm.description = evidence.description || '';
    evidenceForm.allows_multiple = Boolean(evidence.allows_multiple);
    showEvidenceModal.value = true;
};

const handleEvidenceSubmit = () => {
    if (isEditingEvidence.value) {
        evidenceForm.put(route('evidence-templates.update', evidenceForm.id), {
            onSuccess: () => { showEvidenceModal.value = false; notification.success({ title: 'Actualizado', content: 'Evidencia actualizada.' }); }
        });
    } else {
        evidenceForm.post(route('evidence-templates.store'), {
            onSuccess: () => { showEvidenceModal.value = false; notification.success({ title: 'Creado', content: 'Evidencia guardada.' }); }
        });
    }
};

const handleDeleteEvidence = (id) => {
    router.delete(route('evidence-templates.destroy', id));
};

// ================= DRAG AND DROP (REORDENAMIENTO) =================
const draggedEvidenceIndex = ref(null);

const onDragStartEvidence = (index) => {
    draggedEvidenceIndex.value = index;
};

const onDropEvidence = (dropIndex) => {
    if (draggedEvidenceIndex.value === null || draggedEvidenceIndex.value === dropIndex) return;
    
    let currentEvidences = [...evidencesBySystem.value];
    const draggedItem = currentEvidences.splice(draggedEvidenceIndex.value, 1)[0];
    currentEvidences.splice(dropIndex, 0, draggedItem);
    
    const updatedItems = currentEvidences.map((item, i) => ({
        id: item.id,
        order: i + 1
    }));

    router.post(route('evidence-templates.reorder'), { items: updatedItems }, {
        preserveScroll: true,
        onSuccess: () => {
            notification.success({ title: 'Orden actualizado', content: 'Se guardó el nuevo orden de las evidencias.' });
        }
    });

    draggedEvidenceIndex.value = null;
};

const getPriorityColor = (priority) => {
    const map = { 'Baja': 'success', 'Media': 'warning', 'Alta': 'error' };
    return map[priority] || 'default';
};
</script>

<template>
    <AppLayout title="Gestor de Automatización">
        <template #header>
            <div class="flex justify-between items-center gap-4">
                <div class="flex items-center gap-3">
                    <n-icon size="28" class="text-indigo-600"><SettingsOutline /></n-icon>
                    <div>
                        <h2 class="font-bold text-xl text-gray-800 leading-tight">
                            Gestor de Automatización
                        </h2>
                        <p class="text-sm text-gray-500 mt-1">Configura las tareas y evidencias fotográficas automáticas por tipo de sistema.</p>
                    </div>
                </div>
                <n-button 
                    v-if="hasPermission('system_type.create')" 
                    type="primary" 
                    ghost 
                    round
                    @click="showSystemModal = true"
                >
                    <template #icon><n-icon><ListOutline /></n-icon></template>
                    Gestionar Tipos de Sistema
                </n-button>
            </div>
        </template>

        <div class="py-8 min-h-screen">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                
                <div class="bg-white rounded-3xl shadow-lg border border-gray-100 overflow-hidden">
                    <n-tabs type="line" size="large" animated class="px-6 pt-4" v-model:value="activeSystemType">
                        <n-tab-pane v-for="sys in normalizedSystemTypes" :key="sys.name" :name="sys.name" :tab="sys.name">
                            
                            <div class="py-4 grid grid-cols-1 lg:grid-cols-2 gap-8">
                                
                                <!-- COLUMNA 1: TAREAS PROGRAMADAS -->
                                <div>
                                    <div class="flex justify-between items-center mb-4">
                                        <h3 class="text-lg font-bold text-gray-700 flex items-center gap-2">
                                            <n-icon class="text-indigo-500"><CheckmarkCircleOutline/></n-icon> Tareas del Sistema
                                        </h3>
                                        <n-button type="primary" size="small" class="bg-indigo-600" @click="openAddTaskModal(sys)">
                                            <template #icon><n-icon><AddOutline /></n-icon></template> Agregar
                                        </n-button>
                                    </div>

                                    <div v-if="tasksBySystem.length > 0" class="space-y-3">
                                        <n-card v-for="item in tasksBySystem" :key="item.id" size="small" class="rounded-xl shadow-sm border border-gray-100 hover:shadow-md transition-shadow">
                                            <div class="flex justify-between items-start gap-3">
                                                <div class="flex-1">
                                                    <div class="flex items-center gap-2 mb-1">
                                                        <h4 class="font-semibold text-gray-800 text-sm">{{ item.title }}</h4>
                                                        <n-tag :type="getPriorityColor(item.priority)" size="tiny" round>{{ item.priority }}</n-tag>
                                                    </div>
                                                    <p class="text-xs text-gray-600" v-if="item.description">{{ item.description }}</p>
                                                    
                                                    <!-- NUEVO INFO DE TIEMPOS -->
                                                    <div class="mt-2 text-[11px] text-indigo-500 font-medium">
                                                        ⏱️ Inicia en {{ item.start_days }} días - Dura {{ item.duration_days }} días
                                                    </div>

                                                    <div class="mt-2 flex flex-wrap gap-1">
                                                        <template v-if="item.users?.length">
                                                            <n-avatar v-for="u in item.users" :key="u.id" round size="small" :src="u.profile_photo_url" :fallback-src="'https://ui-avatars.com/api/?name='+u.name"/>
                                                        </template>
                                                        <span v-else class="text-[10px] italic text-gray-400">Sin asignar</span>
                                                    </div>
                                                </div>

                                                <div class="flex gap-1">
                                                    <n-button circle quaternary size="small" type="info" @click="openEditTaskModal(item)">
                                                        <template #icon><n-icon><CreateOutline /></n-icon></template>
                                                    </n-button>
                                                    <n-popconfirm @positive-click="handleDeleteTask(item.id)" positive-text="Sí" negative-text="No">
                                                        <template #trigger>
                                                            <n-button circle quaternary size="small" type="error">
                                                                <template #icon><n-icon><TrashOutline /></n-icon></template>
                                                            </n-button>
                                                        </template>
                                                        ¿Eliminar?
                                                    </n-popconfirm>
                                                </div>
                                            </div>
                                        </n-card>
                                    </div>
                                    <n-empty v-else description="Sin tareas automáticas." class="py-8" />
                                </div>

                                <!-- COLUMNA 2: EVIDENCIAS REQUERIDAS (DRAG AND DROP) -->
                                <div>
                                    <div class="flex justify-between items-center mb-4">
                                        <h3 class="text-lg font-bold text-gray-700 flex items-center gap-2">
                                            <n-icon class="text-emerald-500"><CameraOutline/></n-icon> Evidencias Requeridas
                                        </h3>
                                        <n-button type="primary" size="small" class="bg-emerald-600" @click="openAddEvidenceModal(sys)">
                                            <template #icon><n-icon><AddOutline /></n-icon></template> Agregar
                                        </n-button>
                                    </div>

                                    <div v-if="evidencesBySystem.length > 0" class="space-y-3">
                                        <!-- Wrapper drag and drop -->
                                        <div 
                                            v-for="(ev, index) in evidencesBySystem" 
                                            :key="ev.id"
                                            draggable="true"
                                            @dragstart="onDragStartEvidence(index)"
                                            @dragover.prevent
                                            @drop="onDropEvidence(index)"
                                        >
                                            <n-card size="small" class="rounded-xl shadow-sm border border-emerald-100 bg-emerald-50/20 hover:shadow-md transition-shadow cursor-move">
                                                <div class="flex justify-between items-center gap-3">
                                                    <!-- Icono de agarre (drag handle) -->
                                                    <div class="text-gray-400 flex items-center">
                                                        <n-icon size="20"><MenuOutline /></n-icon>
                                                    </div>

                                                    <div class="flex-1">
                                                        <div class="flex items-center gap-2 mb-1">
                                                            <h4 class="font-bold text-emerald-800 text-sm">{{ ev.title }}</h4>
                                                            <n-tag v-if="ev.allows_multiple" type="info" size="tiny" round>Múltiples</n-tag>
                                                        </div>
                                                        <p class="text-xs text-gray-600">{{ ev.description }}</p>
                                                    </div>

                                                    <div class="flex gap-1">
                                                        <n-button circle quaternary size="small" type="info" @click="openEditEvidenceModal(ev)">
                                                            <template #icon><n-icon><CreateOutline /></n-icon></template>
                                                        </n-button>
                                                        <n-popconfirm @positive-click="handleDeleteEvidence(ev.id)" positive-text="Sí" negative-text="No">
                                                            <template #trigger>
                                                                <n-button circle quaternary size="small" type="error">
                                                                    <template #icon><n-icon><TrashOutline /></n-icon></template>
                                                                </n-button>
                                                            </template>
                                                            ¿Eliminar?
                                                        </n-popconfirm>
                                                    </div>
                                                </div>
                                            </n-card>
                                        </div>
                                    </div>
                                    <n-empty v-else description="Sin evidencias fotográficas configuradas." class="py-8" />
                                </div>

                            </div>
                        </n-tab-pane>
                    </n-tabs>
                </div>

            </div>
        </div>

        <!-- MODAL TAREAS -->
        <n-modal v-model:show="showTaskModal" preset="card" class="max-w-lg" :title="isEditingTask ? 'Editar Tarea' : 'Nueva Tarea'">
            <n-form :model="taskForm" @submit.prevent="handleTaskSubmit">
                <n-form-item label="Título de la Tarea" path="title"><n-input v-model:value="taskForm.title" /></n-form-item>
                <n-form-item label="Descripción" path="description"><n-input type="textarea" v-model:value="taskForm.description" /></n-form-item>
                <div class="grid grid-cols-2 gap-4">
                    <n-form-item label="Prioridad" path="priority"><n-select v-model:value="taskForm.priority" :options="priorityOptions" /></n-form-item>
                    <n-form-item label="Tipo de Sistema"><n-input :value="taskForm.system_type" disabled /></n-form-item>
                </div>

                <!-- NUEVOS CAMPOS: TIEMPOS -->
                <div class="grid grid-cols-2 gap-4">
                    <n-form-item label="Días para iniciar" path="start_days">
                        <n-input-number v-model:value="taskForm.start_days" :min="0" class="w-full" placeholder="0 para hoy" />
                    </n-form-item>
                    <n-form-item label="Duración (Días)" path="duration_days">
                        <n-input-number v-model:value="taskForm.duration_days" :min="1" class="w-full" placeholder="Ej. 1" />
                    </n-form-item>
                </div>

                <!-- SELECT USUARIOS CON MENSAJE -->
                <n-form-item path="users">
                    <template #label>
                        Asignar Usuarios Automáticamente
                    </template>
                    <n-select v-model:value="taskForm.users" multiple :options="usersOptions" clearable />
                    <template #feedback>
                        <span class="text-amber-600 text-[11px] flex items-center gap-1 mt-1">
                            <n-icon size="14"><InformationCircleOutline /></n-icon> 
                            Si no asignas a una persona, la tarea quedará en la sección "Sin asignar" en el PMS.
                        </span>
                    </template>
                </n-form-item>
                
                <div class="flex justify-end gap-3 mt-4">
                    <n-button @click="showTaskModal = false">Cancelar</n-button>
                    <n-button type="primary" attr-type="submit" :loading="taskForm.processing" class="bg-indigo-600">Guardar</n-button>
                </div>
            </n-form>
        </n-modal>

        <!-- MODAL EVIDENCIAS -->
        <n-modal v-model:show="showEvidenceModal" preset="card" class="max-w-lg" :title="isEditingEvidence ? 'Editar Evidencia Requerida' : 'Nueva Evidencia Requerida'">
            <n-form :model="evidenceForm" @submit.prevent="handleEvidenceSubmit">
                <n-form-item label="Título del Requisito (Ej. Foto Inversor)" path="title"><n-input v-model:value="evidenceForm.title" /></n-form-item>
                <n-form-item label="Instrucciones para la foto/documento" path="description">
                    <n-input type="textarea" v-model:value="evidenceForm.description" placeholder="Asegúrate que se vean los cables..." />
                </n-form-item>

                <n-form-item label="Tipo de Sistema"><n-input :value="evidenceForm.system_type" disabled /></n-form-item>
                <div class="flex justify-end gap-3 mt-4">
                    <n-button @click="showEvidenceModal = false">Cancelar</n-button>
                    <n-button type="primary" attr-type="submit" :loading="evidenceForm.processing" class="bg-emerald-600">Guardar Evidencia</n-button>
                </div>
            </n-form>
        </n-modal>

        <!-- NUEVO MODAL: GESTIÓN DE TIPOS DE SISTEMA -->
        <n-modal v-model:show="showSystemModal" preset="card" class="max-w-md" title="Gestionar Tipos de Sistema">
            <!-- Formulario agregar -->
            <div v-if="hasPermission('system_type.create')" class="flex gap-2 mb-6">
                <n-input v-model:value="systemForm.name" placeholder="Nuevo tipo (ej. Interconectado)" @keyup.enter="handleAddSystem" />
                <n-button type="primary" class="bg-indigo-600" @click="handleAddSystem" :loading="systemForm.processing" :disabled="!systemForm.name">
                    Agregar
                </n-button>
            </div>
            
            <!-- Lista de tipos actuales -->
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