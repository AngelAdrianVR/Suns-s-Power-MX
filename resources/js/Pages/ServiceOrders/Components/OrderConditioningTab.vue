<script setup>
import { ref, computed, h } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import { usePermissions } from '@/Composables/usePermissions';
import { 
    NButton, NIcon, NTag, NCard, NSelect, NInput, NModal, NForm, NFormItem,
    NEmpty, NPopconfirm, NAvatar, NImage, createDiscreteApi, NUpload
} from 'naive-ui';
import {
    AddOutline, TrashOutline, CreateOutline, CheckmarkCircleOutline,
    CloudUploadOutline, PersonOutline, CloseOutline, ImageOutline,
    VideocamOutline, DocumentTextOutline, ConstructOutline
} from '@vicons/ionicons5';

const props = defineProps({
    order: Object,
    assignableUsers: Array,
});

const emit = defineEmits(['refresh']);

const { hasPermission } = usePermissions();
const { notification } = createDiscreteApi(['notification']);

const userOptions = computed(() => 
    props.assignableUsers?.map(u => ({ label: u.name, value: u.id })) || []
);

const categoryOptions = [
    { label: 'Instalación Eléctrica', value: 'Instalación Eléctrica' },
    { label: 'Área de Instalación', value: 'Área de Instalación' },
];

const statusOptions = [
    { label: 'Pendiente', value: 'Pendiente' },
    { label: 'En proceso', value: 'En proceso' },
    { label: 'Terminado', value: 'Terminado' },
];

// --- MODAL PARA AGREGAR TAREA ---
const showAddModal = ref(false);
const addForm = useForm({
    category: 'Instalación Eléctrica',
    task: '',
    user_id: null,
    notes: '',
});

const openAddModal = () => {
    addForm.category = 'Instalación Eléctrica';
    addForm.task = '';
    addForm.user_id = null;
    addForm.notes = '';
    showAddModal.value = true;
};

const submitAdd = () => {
    if (!addForm.task.trim()) {
        notification.warning({ title: 'Atención', content: 'Describe la tarea.', duration: 3000 });
        return;
    }
    addForm.post(route('service-orders.conditionings.store', props.order.id), {
        preserveScroll: true,
        onSuccess: () => {
            showAddModal.value = false;
            notification.success({ title: 'Agregada', content: 'Tarea de acondicionamiento creada.', duration: 3000 });
            emit('refresh');
        },
    });
};

// --- ELIMINAR TAREA ---
const deleteConditioning = (id) => {
    router.delete(route('service-orders.conditionings.destroy', id), {
        preserveScroll: true,
        onSuccess: () => {
            notification.success({ title: 'Eliminada', content: 'Tarea de acondicionamiento eliminada.', duration: 3000 });
            emit('refresh');
        },
    });
};

// --- ACTUALIZAR ESTATUS / ASIGNACIÓN ---
const updateConditioning = (cond, field, value) => {
    router.patch(route('service-orders.conditionings.update', cond.id), {
        [field]: value,
    }, {
        preserveScroll: true,
        onSuccess: () => emit('refresh'),
    });
};

// --- SUBIR EVIDENCIA ---
const handleUpload = (cond, options) => {
    const file = options.file?.file || options.file;
    if (!file) return;

    const form = useForm({ file });
    form.post(route('service-orders.conditionings.media.upload', cond.id), {
        preserveScroll: true,
        onSuccess: () => {
            notification.success({ title: 'Evidencia Subida', duration: 2000 });
            emit('refresh');
        },
        onError: (err) => {
            notification.error({ title: 'Error', content: err?.file || 'No se pudo subir.', duration: 3000 });
        },
    });
};

// --- ELIMINAR EVIDENCIA ---
const deleteMedia = (cond, mediaId) => {
    router.delete(route('service-orders.conditionings.media.delete', { conditioning: cond.id, media: mediaId }), {
        preserveScroll: true,
        onSuccess: () => {
            notification.success({ title: 'Eliminada', duration: 2000 });
            emit('refresh');
        },
    });
};

// --- UTILIDADES ---
const isImage = (mime) => mime?.startsWith('image/');
const isVideo = (mime) => mime?.startsWith('video/');

const getStatusType = (status) => {
    const map = { 'Pendiente': 'warning', 'En proceso': 'info', 'Terminado': 'success' };
    return map[status] || 'default';
};

const getCategoryType = (cat) => {
    return cat === 'Instalación Eléctrica' ? 'warning' : 'info';
};

const conditioningTasks = computed(() => props.order?.conditionings || []);
</script>

<template>
    <div class="p-2 md:p-4 space-y-4">
        <div class="flex justify-between items-center">
            <h4 class="text-sm font-bold text-gray-600 flex items-center gap-2">
                <n-icon :component="ConstructOutline" class="text-orange-500" />
                Tareas de Acondicionamiento Previo
            </h4>
            <n-button size="small" type="primary" secondary @click="openAddModal" v-if="hasPermission('service_orders.edit')">
                <template #icon><n-icon><AddOutline /></n-icon></template>
                Agregar Tarea
            </n-button>
        </div>

        <n-empty v-if="!conditioningTasks.length" description="Sin tareas de acondicionamiento registradas." />

        <div v-for="cond in conditioningTasks" :key="cond.id" 
             class="border rounded-xl p-4 bg-gray-50/50 space-y-4">
            
            <!-- Header de la tarea -->
            <div class="flex flex-col sm:flex-row justify-between gap-2">
                <div class="flex-1 space-y-1">
                    <div class="flex items-center gap-2 flex-wrap">
                        <n-tag :type="getCategoryType(cond.category)" size="small" round :bordered="false">
                            {{ cond.category }}
                        </n-tag>
                        <n-select
                            v-if="hasPermission('service_orders.edit')"
                            :value="cond.status"
                            :options="statusOptions"
                            size="tiny"
                            class="w-28"
                            @update:value="(v) => updateConditioning(cond, 'status', v)"
                        />
                        <n-tag v-else :type="getStatusType(cond.status)" size="small" round :bordered="false">
                            {{ cond.status }}
                        </n-tag>
                    </div>
                    <p class="text-sm font-semibold text-gray-800">{{ cond.task }}</p>
                    <p v-if="cond.notes" class="text-xs text-gray-500">{{ cond.notes }}</p>
                </div>
                <div class="flex items-center gap-2 flex-shrink-0 relative">
                    <!-- Asignar usuario -->
                    <n-select
                        v-if="hasPermission('service_orders.edit')"
                        :value="cond.user_id"
                        :options="userOptions"
                        size="tiny"
                        placeholder="Asignar"
                        clearable
                        filterable
                        class="w-36"
                        @update:value="(v) => updateConditioning(cond, 'user_id', v)"
                    >
                        <template #prefix><n-icon :component="PersonOutline" size="14" /></template>
                    </n-select>
                    <div v-else-if="cond.user" class="flex items-center gap-1 text-xs text-gray-500">
                        <n-avatar size="20" round :src="cond.user.profile_photo_url" />
                        {{ cond.user.name }}
                    </div>
                    <small class="absolute -top-1 left-0 text-xs text-gray-500 italic">
                        No asignar si el cliente lo hace
                    </small>

                    <n-popconfirm @positive-click="deleteConditioning(cond.id)" v-if="hasPermission('service_orders.edit')">
                        <template #trigger>
                            <n-button size="tiny" quaternary type="error">
                                <template #icon><n-icon><TrashOutline /></n-icon></template>
                            </n-button>
                        </template>
                        ¿Eliminar esta tarea?
                    </n-popconfirm>
                </div>
            </div>

            <!-- Evidencias -->
            <div class="border-t pt-3">
                <div class="flex justify-between items-center mb-2">
                    <span class="text-xs font-bold text-gray-500 flex items-center gap-1">
                        <n-icon :component="ImageOutline" size="14" />
                        Evidencias. Max 3. ({{ cond.media?.length || 0 }}/3)
                    </span>
                </div>

                <!-- Grid de evidencias existentes -->
                <div v-if="cond.media?.length" class="grid grid-cols-3 gap-2 mb-3">
                    <div v-for="media in cond.media" :key="media.id" class="relative group">
                        <n-image v-if="isImage(media.mime_type)" :src="media.original_url" class="rounded-lg border w-full h-24 object-cover" object-fit="cover" />
                        <div v-else-if="isVideo(media.mime_type)" class="w-full h-24 rounded-lg border bg-gray-900 flex items-center justify-center">
                            <n-icon size="28" class="text-white"><VideocamOutline /></n-icon>
                        </div>
                        <div v-else class="w-full h-24 rounded-lg border bg-white flex flex-col items-center justify-center">
                            <n-icon size="22" class="text-gray-400"><DocumentTextOutline /></n-icon>
                            <span class="text-[10px] text-gray-400 truncate max-w-full px-1">{{ media.file_name }}</span>
                        </div>
                        <n-button 
                            size="tiny" circle type="error" 
                            class="absolute -top-1 -right-1 opacity-0 group-hover:opacity-100 transition-opacity"
                            @click="deleteMedia(cond, media.id)"
                        >
                            <template #icon><n-icon size="12"><CloseOutline /></n-icon></template>
                        </n-button>
                    </div>
                </div>

                <!-- Upload de evidencia -->
                <div v-if="(cond.media?.length || 0) < 3 && hasPermission('service_orders.edit')">
                    <n-upload
                        :show-file-list="false"
                        accept="image/*,video/*"
                        :max="1"
                        @change="(opts) => handleUpload(cond, opts)"
                    >
                        <n-button dashed size="tiny" type="primary" class="w-full">
                            <template #icon><n-icon><CloudUploadOutline /></n-icon></template>
                            Subir Foto/Video (máx 80 MB)
                        </n-button>
                    </n-upload>
                </div>
            </div>
        </div>

        <!-- Modal Agregar Tarea -->
        <n-modal v-model:show="showAddModal">
            <n-card style="width: 450px" title="Nueva Tarea de Acondicionamiento" :bordered="false" size="huge" role="dialog">
                <n-form :model="addForm">
                    <n-form-item label="Categoría" required>
                        <n-select v-model:value="addForm.category" :options="categoryOptions" />
                    </n-form-item>
                    <n-form-item label="Tarea" required>
                        <n-input v-model:value="addForm.task" placeholder="Ej. Mover tinaco, cambiar a 220V..." />
                    </n-form-item>
                    <n-form-item label="Asignar a (opcional)">
                        <n-select v-model:value="addForm.user_id" :options="userOptions" filterable clearable placeholder="Seleccionar usuario" />
                    </n-form-item>
                    <n-form-item label="Notas">
                        <n-input v-model:value="addForm.notes" type="textarea" placeholder="Detalles adicionales..." :autosize="{ minRows: 2 }" />
                    </n-form-item>
                </n-form>
                <template #footer>
                    <div class="flex justify-end gap-3">
                        <n-button @click="showAddModal = false">Cancelar</n-button>
                        <n-button type="primary" @click="submitAdd" :loading="addForm.processing">
                            Agregar Tarea
                        </n-button>
                    </div>
                </template>
            </n-card>
        </n-modal>
    </div>
</template>
