<script setup>
import { ref } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import { 
    NButton, NCard, NIcon, NTag, NModal, NForm, NFormItem, NInput, NSelect, createDiscreteApi, NPopconfirm, NEmpty
} from 'naive-ui';
import { 
    AddOutline, CreateOutline, TrashOutline, CheckmarkCircleOutline, CameraOutline, MenuOutline
} from '@vicons/ionicons5';

const props = defineProps({
    sys: Object,
    evidences: Array,
    taskOptions: Array
});

const emit = defineEmits(['sync']);
const { notification } = createDiscreteApi(['notification']);

const showEvidenceModal = ref(false);
const isEditingEvidence = ref(false);
const evidenceForm = useForm({
    id: null,
    system_type: props.sys.name,
    title: '',
    description: '',
    allows_multiple: false,
    tasks: [] 
});

const openAddEvidenceModal = () => {
    isEditingEvidence.value = false;
    evidenceForm.reset();
    evidenceForm.system_type = props.sys.name;
    evidenceForm.allows_multiple = false;
    evidenceForm.tasks = [];
    showEvidenceModal.value = true;
};

const openEditEvidenceModal = (evidence) => {
    isEditingEvidence.value = true;
    evidenceForm.id = evidence.id;
    evidenceForm.system_type = evidence.system_type;
    evidenceForm.title = evidence.title;
    evidenceForm.description = evidence.description || '';
    evidenceForm.allows_multiple = Boolean(evidence.allows_multiple);
    evidenceForm.tasks = evidence.task_templates?.map(t => t.id) || []; 
    showEvidenceModal.value = true;
};

const handleEvidenceSubmit = () => {
    if (isEditingEvidence.value) {
        evidenceForm.put(route('evidence-templates.update', evidenceForm.id), {
            onSuccess: () => { 
                showEvidenceModal.value = false; 
                notification.success({ title: 'Actualizado', content: 'Evidencia actualizada.', duration: 3000 }); 
                emit('sync');
            }
        });
    } else {
        evidenceForm.post(route('evidence-templates.store'), {
            onSuccess: () => { 
                showEvidenceModal.value = false; 
                notification.success({ title: 'Creado', content: 'Evidencia guardada.', duration: 3000 }); 
                emit('sync');
            }
        });
    }
};

const handleDeleteEvidence = (id) => {
    router.delete(route('evidence-templates.destroy', id), {
        preserveScroll: true,
        onSuccess: () => {
            notification.success({ title: 'Eliminado', content: 'Evidencia eliminada.', duration: 3000 });
            emit('sync');
        }
    });
};

const draggedEvidenceIndex = ref(null);
const onDragStartEvidence = (index) => { draggedEvidenceIndex.value = index; };
const onDropEvidence = (dropIndex) => {
    if (draggedEvidenceIndex.value === null || draggedEvidenceIndex.value === dropIndex) return;
    
    let currentEvidences = [...props.evidences];
    const draggedItem = currentEvidences.splice(draggedEvidenceIndex.value, 1)[0];
    currentEvidences.splice(dropIndex, 0, draggedItem);
    
    const updatedItems = currentEvidences.map((item, i) => ({ id: item.id, order: i + 1 }));

    router.post(route('evidence-templates.reorder'), { items: updatedItems }, {
        preserveScroll: true,
        onSuccess: () => { 
            notification.success({ title: 'Orden actualizado', content: 'Se guardó el nuevo orden de las evidencias.', duration: 3000 }); 
            emit('sync');
        }
    });

    draggedEvidenceIndex.value = null;
};
</script>

<template>
    <div>
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-bold text-gray-700 flex items-center gap-2">
                <n-icon class="text-emerald-500"><CameraOutline/></n-icon> Evidencias
            </h3>
            <n-button type="primary" size="small" class="bg-emerald-600" @click="openAddEvidenceModal">
                <template #icon><n-icon><AddOutline /></n-icon></template> Agregar
            </n-button>
        </div>

        <div v-if="evidences.length > 0" class="space-y-3">
            <div v-for="(ev, index) in evidences" :key="ev.id" draggable="true" @dragstart="onDragStartEvidence(index)" @dragover.prevent @drop="onDropEvidence(index)">
                <n-card size="small" class="rounded-xl shadow-sm border border-emerald-100 bg-emerald-50/20 hover:shadow-md transition-shadow cursor-move">
                    <div class="flex justify-between items-center gap-3">
                        <div class="text-gray-400 flex items-center">
                            <n-icon size="20"><MenuOutline /></n-icon>
                        </div>

                        <div class="flex-1">
                            <div class="flex items-center gap-2 mb-1">
                                <h4 class="font-bold text-emerald-800 text-sm">{{ ev.title }}</h4>
                                <n-tag v-if="ev.allows_multiple" type="info" size="tiny" round>Múltiples</n-tag>
                            </div>
                            <p class="text-xs text-gray-600">{{ ev.description }}</p>

                            <div v-if="ev.task_templates?.length > 0" class="mt-2 text-[11px] text-gray-500 flex flex-wrap gap-1 items-center">
                                <n-icon class="text-indigo-500"><CheckmarkCircleOutline/></n-icon> Para:
                                <n-tag v-for="t in ev.task_templates" :key="t.id" size="tiny" type="warning" round>{{ t.title }}</n-tag>
                            </div>
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
        <n-empty v-else description="Sin evidencias fotográficas." class="py-8" />

        <!-- MODAL EVIDENCIAS -->
        <n-modal v-model:show="showEvidenceModal" preset="card" class="max-w-lg" :title="isEditingEvidence ? 'Editar Evidencia Requerida' : 'Nueva Evidencia Requerida'">
            <n-form :model="evidenceForm" @submit.prevent="handleEvidenceSubmit">
                <n-form-item label="Título del Requisito (Ej. Foto Inversor)" path="title"><n-input v-model:value="evidenceForm.title" /></n-form-item>
                <n-form-item label="Instrucciones para la foto/documento" path="description">
                    <n-input type="textarea" v-model:value="evidenceForm.description" placeholder="Asegúrate que se vean los cables..." />
                </n-form-item>

                <n-form-item label="Tipo de Sistema"><n-input :value="evidenceForm.system_type" disabled /></n-form-item>
                
                <n-form-item path="tasks">
                    <template #label>Requerida obligatoriamente en las tareas <span class="text-xs text-gray-400 ml-1">(Opcional)</span></template>
                    <n-select v-model:value="evidenceForm.tasks" multiple :options="taskOptions" clearable placeholder="Selecciona tareas" />
                </n-form-item>

                <div class="flex justify-end gap-3 mt-4">
                    <n-button @click="showEvidenceModal = false">Cancelar</n-button>
                    <n-button type="primary" attr-type="submit" :loading="evidenceForm.processing" class="bg-emerald-600">Guardar Evidencia</n-button>
                </div>
            </n-form>
        </n-modal>
    </div>
</template>