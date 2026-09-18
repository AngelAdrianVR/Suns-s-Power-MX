<script setup>
import { ref, watch } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { usePermissions } from '@/Composables/usePermissions';
import PermissionTooltip from '@/Components/MyComponents/PermissionTooltip.vue';
import {
    NButton, NIcon, NInput, NSwitch, NPopconfirm, NEmpty, NTag, createDiscreteApi
} from 'naive-ui';
import {
    AddOutline, TrashOutline, MenuOutline, DocumentTextOutline, SparklesOutline,
    CreateOutline, CheckmarkOutline, CloseOutline
} from '@vicons/ionicons5';

const props = defineProps({
    steps: Array,
});

const { hasPermission } = usePermissions();
const { notification } = createDiscreteApi(['notification']);

// Copia local para edición inline y reordenamiento (los props se refrescan con cada back())
const localSteps = ref(props.steps ? [...props.steps] : []);

watch(() => props.steps, (value) => {
    localSteps.value = value ? [...value] : [];
});

// --- CREAR ---
const stepForm = useForm({ title: '', description: '' });

const createStep = () => {
    if (!stepForm.title.trim()) {
        notification.warning({ title: 'Falta título', content: 'Escribe el título del documento.', duration: 3000 });
        return;
    }
    stepForm.post(route('service-documentation.store'), {
        preserveScroll: true,
        onSuccess: () => {
            stepForm.reset();
            notification.success({ title: 'Creado', content: 'Documento agregado a la configuración.', duration: 3000 });
        }
    });
};

// --- EDICIÓN INLINE ---
const editingId = ref(null);
const editingTitle = ref('');
const editingDescription = ref('');

const startEdit = (step) => {
    editingId.value = step.id;
    editingTitle.value = step.title;
    editingDescription.value = step.description || '';
};

const cancelEdit = () => {
    editingId.value = null;
};

const saveEdit = (step) => {
    if (!editingTitle.value.trim()) {
        notification.warning({ title: 'Falta título', content: 'El título no puede quedar vacío.', duration: 3000 });
        return;
    }
    router.put(route('service-documentation.update', step.id), {
        title: editingTitle.value,
        description: editingDescription.value,
        is_active: step.is_active,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            editingId.value = null;
            notification.success({ title: 'Actualizado', content: 'Documento guardado.', duration: 3000 });
        }
    });
};

// --- ACTIVAR / DESACTIVAR ---
const toggleActive = (step) => {
    router.put(route('service-documentation.update', step.id), {
        title: step.title,
        description: step.description,
        is_active: !step.is_active,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            notification.success({
                title: step.is_active ? 'Desactivado' : 'Activado',
                content: step.is_active
                    ? 'El documento ya no se pedirá en el asistente.'
                    : 'El documento volverá a pedirse en el asistente.',
                duration: 3000
            });
        }
    });
};

// --- ELIMINAR ---
const deleteStep = (step) => {
    router.delete(route('service-documentation.destroy', step.id), {
        preserveScroll: true,
        onSuccess: () => {
            notification.success({ title: 'Eliminado', content: 'Documento eliminado de la configuración.', duration: 3000 });
        }
    });
};

// --- REORDENAR (drag & drop) ---
const draggedIndex = ref(null);

const onDragStart = (index) => {
    draggedIndex.value = index;
};

const onDrop = (dropIndex) => {
    if (draggedIndex.value === null || draggedIndex.value === dropIndex) {
        draggedIndex.value = null;
        return;
    }

    const items = [...localSteps.value];
    const dragged = items.splice(draggedIndex.value, 1)[0];
    items.splice(dropIndex, 0, dragged);

    const payload = items.map((item, index) => ({ id: item.id, order: index + 1 }));

    router.post(route('service-documentation.reorder'), { items: payload }, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
        onSuccess: () => {
            notification.success({ title: 'Orden guardado', content: 'Se actualizó el orden de los documentos.', duration: 3000 });
        }
    });

    draggedIndex.value = null;
};

// --- CARGAR PREDETERMINADOS ---
const loadDefaults = () => {
    router.post(route('service-documentation.defaults'), {}, {
        preserveScroll: true,
        onSuccess: (page) => {
            const flash = page.props.flash;
            if (flash?.success) {
                notification.success({ title: 'Listo', content: flash.success, duration: 4000 });
            } else if (flash?.error) {
                notification.error({ title: 'Atención', content: flash.error, duration: 4000 });
            }
        }
    });
};
</script>

<template>
    <AppLayout title="Documentación de servicio">
        <template #header>
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <div class="flex items-center gap-3">
                    <n-icon size="28" class="text-indigo-600"><DocumentTextOutline /></n-icon>
                    <div>
                        <h2 class="font-bold text-xl text-gray-800 leading-tight">Documentación de servicio</h2>
                        <p class="text-sm text-gray-500 mt-1">
                            Configura los documentos (pasos) que pedirá el asistente al generar el expediente de una orden.
                        </p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <PermissionTooltip permission="service_documentation.config" placement="bottom" :size="13" />
                    <n-button
                        v-if="hasPermission('service_documentation.config') && !steps.length"
                        quaternary type="primary"
                        @click="loadDefaults"
                    >
                        <template #icon><n-icon><SparklesOutline /></n-icon></template>
                        Cargar documentos predeterminados
                    </n-button>
                </div>
            </div>
        </template>

        <div class="py-8 min-h-screen">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

                <!-- Crear nuevo documento -->
                <div v-if="hasPermission('service_documentation.config')" class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
                    <h3 class="font-bold text-gray-700 mb-3 flex items-center gap-2">
                        <n-icon class="text-indigo-500"><AddOutline /></n-icon> Nuevo documento / paso
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                        <n-input v-model:value="stepForm.title" placeholder="Título (ej. INE del cliente)" maxlength="255" />
                        <n-input
                            v-model:value="stepForm.description"
                            type="textarea"
                            :autosize="{ minRows: 1, maxRows: 3 }"
                            placeholder="Descripción opcional (qué se necesita o bajo qué características)"
                            maxlength="2000"
                            class="md:col-span-2"
                        />
                    </div>
                    <div class="flex justify-end mt-3">
                        <n-button type="primary" @click="createStep" :loading="stepForm.processing">
                            <template #icon><n-icon><AddOutline /></n-icon></template>
                            Agregar documento
                        </n-button>
                    </div>
                </div>

                <!-- Lista de documentos configurados -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
                    <h3 class="font-bold text-gray-700 mb-1">Documentos del expediente</h3>
                    <p class="text-xs text-gray-400 mb-4">Arrastra para reordenar. El orden define los pasos del asistente.</p>

                    <n-empty v-if="!localSteps.length" description="Aún no hay documentos configurados.">
                        <template #extra>
                            <n-button
                                v-if="hasPermission('service_documentation.config')"
                                size="small" type="primary" secondary
                                @click="loadDefaults"
                            >
                                Cargar predeterminados
                            </n-button>
                        </template>
                    </n-empty>

                    <div v-else class="space-y-3">
                        <div
                            v-for="(step, index) in localSteps"
                            :key="step.id"
                            draggable="true"
                            class="border rounded-xl p-4 bg-gray-50/50 hover:border-indigo-200 transition-colors"
                            :class="!step.is_active ? 'opacity-60' : ''"
                            @dragstart="onDragStart(index)"
                            @dragover.prevent
                            @drop="onDrop(index)"
                        >
                            <div class="flex items-start gap-3">
                                <div class="text-gray-400 mt-1 cursor-move" title="Arrastrar para reordenar">
                                    <n-icon size="20"><MenuOutline /></n-icon>
                                </div>

                                <div class="flex-1 min-w-0">
                                    <!-- Modo vista -->
                                    <template v-if="editingId !== step.id">
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <n-tag size="small" :bordered="false" type="info">Paso {{ index + 1 }}</n-tag>
                                            <h4 class="font-bold text-gray-800">{{ step.title }}</h4>
                                            <n-tag v-if="!step.is_active" size="small" type="warning" round :bordered="false">Inactivo</n-tag>
                                        </div>
                                        <p v-if="step.description" class="text-sm text-gray-500 mt-1">{{ step.description }}</p>
                                        <p v-else class="text-sm text-gray-400 italic mt-1">Sin descripción</p>
                                    </template>

                                    <!-- Modo edición -->
                                    <template v-else>
                                        <div class="space-y-2">
                                            <n-input v-model:value="editingTitle" size="small" placeholder="Título" />
                                            <n-input
                                                v-model:value="editingDescription"
                                                type="textarea"
                                                size="small"
                                                :autosize="{ minRows: 1, maxRows: 3 }"
                                                placeholder="Descripción (opcional)"
                                            />
                                        </div>
                                    </template>
                                </div>

                                <div class="flex items-center gap-1 shrink-0">
                                    <template v-if="editingId !== step.id">
                                        <n-switch
                                            v-if="hasPermission('service_documentation.config')"
                                            :value="step.is_active"
                                            size="small"
                                            @update:value="toggleActive(step)"
                                        />
                                        <n-button
                                            v-if="hasPermission('service_documentation.config')"
                                            quaternary circle size="small" type="info"
                                            @click="startEdit(step)"
                                        >
                                            <template #icon><n-icon><CreateOutline /></n-icon></template>
                                        </n-button>
                                        <n-popconfirm
                                            v-if="hasPermission('service_documentation.config')"
                                            @positive-click="deleteStep(step)"
                                            positive-text="Sí, eliminar" negative-text="Cancelar"
                                        >
                                            <template #trigger>
                                                <n-button quaternary circle size="small" type="error">
                                                    <template #icon><n-icon><TrashOutline /></n-icon></template>
                                                </n-button>
                                            </template>
                                            ¿Eliminar "{{ step.title }}"?
                                            <br><span class="text-xs text-red-500">También se eliminarán los archivos ya recopilados de este paso.</span>
                                        </n-popconfirm>
                                    </template>
                                    <template v-else>
                                        <n-button quaternary circle size="small" type="success" @click="saveEdit(step)">
                                            <template #icon><n-icon><CheckmarkOutline /></n-icon></template>
                                        </n-button>
                                        <n-button quaternary circle size="small" @click="cancelEdit">
                                            <template #icon><n-icon><CloseOutline /></n-icon></template>
                                        </n-button>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </AppLayout>
</template>
