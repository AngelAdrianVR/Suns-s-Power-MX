<script setup>
import { ref, computed } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { 
    NButton, NCard, NTabs, NTabPane, NIcon, NTag, NAvatar, 
    NModal, NForm, NFormItem, NInput, NSelect, createDiscreteApi, NPopconfirm, NEmpty, NDivider
} from 'naive-ui';
import { 
    AddOutline, CreateOutline, TrashOutline, SettingsOutline, CheckmarkCircleOutline, CameraOutline
} from '@vicons/ionicons5';

const props = defineProps({
    task_templates: Array,
    evidence_templates: Array,
    system_types: Array,
    assignable_users: Array
});

const { notification } = createDiscreteApi(['notification']);

// TABS
const activeSystemType = ref(props.system_types[0] || 'Interconectado');

// ================= ESTADO MODAL TAREAS =================
const showTaskModal = ref(false);
const isEditingTask = ref(false);
const taskForm = useForm({
    id: null,
    system_type: activeSystemType.value,
    title: '',
    description: '',
    priority: 'Media',
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
    taskForm.system_type = sysType;
    showTaskModal.value = true;
};

const openEditTaskModal = (template) => {
    isEditingTask.value = true;
    taskForm.id = template.id;
    taskForm.system_type = template.system_type;
    taskForm.title = template.title;
    taskForm.description = template.description || '';
    taskForm.priority = template.priority;
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
    description: ''
});

const evidencesBySystem = computed(() => {
    return props.evidence_templates.filter(e => e.system_type === activeSystemType.value);
});

const openAddEvidenceModal = (sysType) => {
    isEditingEvidence.value = false;
    evidenceForm.reset();
    evidenceForm.system_type = sysType;
    showEvidenceModal.value = true;
};

const openEditEvidenceModal = (evidence) => {
    isEditingEvidence.value = true;
    evidenceForm.id = evidence.id;
    evidenceForm.system_type = evidence.system_type;
    evidenceForm.title = evidence.title;
    evidenceForm.description = evidence.description || '';
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

const getPriorityColor = (priority) => {
    const map = { 'Baja': 'success', 'Media': 'warning', 'Alta': 'error' };
    return map[priority] || 'default';
};
</script>

<template>
    <AppLayout title="Gestor de Automatización">
        <template #header>
            <div class="flex items-center gap-3">
                <n-icon size="28" class="text-indigo-600"><SettingsOutline /></n-icon>
                <div>
                    <h2 class="font-bold text-xl text-gray-800 leading-tight">
                        Gestor de Automatización
                    </h2>
                    <p class="text-sm text-gray-500 mt-1">Configura las tareas y evidencias fotográficas automáticas por tipo de sistema.</p>
                </div>
            </div>
        </template>

        <div class="py-8 min-h-screen">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                
                <div class="bg-white rounded-3xl shadow-lg border border-gray-100 overflow-hidden">
                    <n-tabs type="line" size="large" animated class="px-6 pt-4" v-model:value="activeSystemType">
                        <n-tab-pane v-for="sysType in system_types" :key="sysType" :name="sysType" :tab="sysType">
                            
                            <div class="py-4 grid grid-cols-1 lg:grid-cols-2 gap-8">
                                
                                <!-- COLUMNA 1: TAREAS PROGRAMADAS -->
                                <div>
                                    <div class="flex justify-between items-center mb-4">
                                        <h3 class="text-lg font-bold text-gray-700 flex items-center gap-2">
                                            <n-icon class="text-indigo-500"><CheckmarkCircleOutline/></n-icon> Tareas del Sistema
                                        </h3>
                                        <n-button type="primary" size="small" class="bg-indigo-600" @click="openAddTaskModal(sysType)">
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
                                                    
                                                    <div class="mt-2 flex flex-wrap gap-1">
                                                        <template v-if="item.users?.length">
                                                            <!-- AQUÍ ESTÁ LA CORRECCIÓN: usamos profile_photo_url -->
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

                                <!-- COLUMNA 2: EVIDENCIAS REQUERIDAS -->
                                <div>
                                    <div class="flex justify-between items-center mb-4">
                                        <h3 class="text-lg font-bold text-gray-700 flex items-center gap-2">
                                            <n-icon class="text-emerald-500"><CameraOutline/></n-icon> Evidencias Requeridas
                                        </h3>
                                        <n-button type="primary" size="small" class="bg-emerald-600" @click="openAddEvidenceModal(sysType)">
                                            <template #icon><n-icon><AddOutline /></n-icon></template> Agregar
                                        </n-button>
                                    </div>

                                    <div v-if="evidencesBySystem.length > 0" class="space-y-3">
                                        <n-card v-for="ev in evidencesBySystem" :key="ev.id" size="small" class="rounded-xl shadow-sm border border-emerald-100 bg-emerald-50/20 hover:shadow-md transition-shadow">
                                            <div class="flex justify-between items-start gap-3">
                                                <div class="flex-1">
                                                    <h4 class="font-bold text-emerald-800 text-sm mb-1">{{ ev.title }}</h4>
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
                <n-form-item label="Asignar Usuarios Automáticamente" path="users">
                    <n-select v-model:value="taskForm.users" multiple :options="usersOptions" clearable />
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

    </AppLayout>
</template>