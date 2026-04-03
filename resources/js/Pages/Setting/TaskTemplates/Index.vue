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
    AddOutline, CreateOutline, TrashOutline, SettingsOutline, CheckmarkCircleOutline, CameraOutline, MenuOutline, CloseOutline, ListOutline, InformationCircleOutline, SyncOutline, CubeOutline
} from '@vicons/ionicons5';

const props = defineProps({
    task_templates: Array,
    evidence_templates: Array,
    system_types: Array,
    assignable_users: Array
});

const { hasPermission } = usePermissions();
const { notification } = createDiscreteApi(['notification']);

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

// ================= OPCIONES GLOBALES POR SISTEMA =================
const tasksBySystem = computed(() => {
    return props.task_templates.filter(t => t.system_type === activeSystemType.value);
});

const evidencesBySystem = computed(() => {
    return props.evidence_templates
        .filter(e => e.system_type === activeSystemType.value)
        .sort((a, b) => (a.order || 0) - (b.order || 0));
});

const evidenceOptions = computed(() => evidencesBySystem.value.map(e => ({ label: e.title, value: e.id })));
const taskOptions = computed(() => tasksBySystem.value.map(t => ({ label: t.title, value: t.id })));


// ================= ESTADO MODAL TAREAS =================
const showTaskModal = ref(false);
const isEditingTask = ref(false);

const taskForm = useForm({
    id: null,
    system_type: activeSystemType.value,
    title: '',
    description: '',
    priority: 'Media',
    start_days: 0,
    duration_days: 1,
    is_recurring: false,
    recurring_interval: 1,
    recurring_unit: 'months',
    users: [],
    evidences: [] 
});

const priorityOptions = [
    { label: 'Baja', value: 'Baja' },
    { label: 'Media', value: 'Media' },
    { label: 'Alta', value: 'Alta' }
];

const recurringUnitOptions = [
    { label: 'Día(s)', value: 'days' },
    { label: 'Semana(s)', value: 'weeks' },
    { label: 'Mes(es)', value: 'months' },
    { label: 'Año(s)', value: 'years' }
];

const usersOptions = computed(() => props.assignable_users.map(u => ({ label: u.name, value: u.id })));

const getRecurringText = (interval, unit) => {
    const units = {
        days: interval === 1 ? 'día' : 'días',
        weeks: interval === 1 ? 'semana' : 'semanas',
        months: interval === 1 ? 'mes' : 'meses',
        years: interval === 1 ? 'año' : 'años'
    };
    return `Se repite cada ${interval} ${units[unit]}`;
};

const openAddTaskModal = (sysType) => {
    isEditingTask.value = false;
    taskForm.reset();
    taskForm.system_type = sysType.name || sysType;
    taskForm.start_days = 0; 
    taskForm.duration_days = 1;
    taskForm.is_recurring = false;
    taskForm.recurring_interval = 1;
    taskForm.recurring_unit = 'months';
    taskForm.evidences = [];
    showTaskModal.value = true;
};

const openEditTaskModal = (template) => {
    isEditingTask.value = true;
    taskForm.id = template.id;
    taskForm.system_type = template.system_type;
    taskForm.title = template.title;
    taskForm.description = template.description || '';
    taskForm.priority = template.priority;
    taskForm.start_days = template.start_days ?? 0;
    taskForm.duration_days = template.duration_days ?? 1;
    taskForm.is_recurring = Boolean(template.is_recurring);
    taskForm.recurring_interval = template.recurring_interval ?? 1;
    taskForm.recurring_unit = template.recurring_unit || 'months';
    taskForm.users = template.users?.map(u => u.id) || [];
    taskForm.evidences = template.evidence_templates?.map(e => e.id) || []; 
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
    allows_multiple: false,
    tasks: [] 
});

const openAddEvidenceModal = (sysType) => {
    isEditingEvidence.value = false;
    evidenceForm.reset();
    evidenceForm.system_type = sysType.name || sysType;
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

const draggedEvidenceIndex = ref(null);
const onDragStartEvidence = (index) => { draggedEvidenceIndex.value = index; };
const onDropEvidence = (dropIndex) => {
    if (draggedEvidenceIndex.value === null || draggedEvidenceIndex.value === dropIndex) return;
    
    let currentEvidences = [...evidencesBySystem.value];
    const draggedItem = currentEvidences.splice(draggedEvidenceIndex.value, 1)[0];
    currentEvidences.splice(dropIndex, 0, draggedItem);
    
    const updatedItems = currentEvidences.map((item, i) => ({ id: item.id, order: i + 1 }));

    router.post(route('evidence-templates.reorder'), { items: updatedItems }, {
        preserveScroll: true,
        onSuccess: () => { notification.success({ title: 'Orden actualizado', content: 'Se guardó el nuevo orden de las evidencias.' }); }
    });

    draggedEvidenceIndex.value = null;
};

const getPriorityColor = (priority) => {
    const map = { 'Baja': 'success', 'Media': 'warning', 'Alta': 'error' };
    return map[priority] || 'default';
};

// ================= ESTADO MODAL PRODUCTOS PREDETERMINADOS (NUEVO) =================
const showProductModal = ref(false);
const searchingProducts = ref(false);
const productSearchOptions = ref([]);

const productForm = useForm({
    system_type_id: null,
    product_id: null,
    quantity: 1
});

const openAddProductModal = (sys) => {
    if (typeof sys.id === 'string') {
        notification.warning({ title: 'Atención', content: 'Aún no migraste a base de datos. Completa el backend primero.' });
        return;
    }
    productForm.reset();
    productForm.system_type_id = sys.id;
    productForm.quantity = 1;
    productSearchOptions.value = [];
    showProductModal.value = true;
};

const handleSearchProduct = async (query) => {
    if (!query) return;
    searchingProducts.value = true;
    try {
        // Usa el endpoint que ya tienes en ProductController@search
        const response = await fetch(`/products/search?query=${encodeURIComponent(query)}`);
        const data = await response.json();
        // formatea si es necesario para naive-ui
        productSearchOptions.value = data.map(p => ({
            label: `${p.sku} - ${p.name}`,
            value: p.value // El controller actual devuelve 'value' como ID
        }));
    } catch (error) {
        console.error("Error buscando productos:", error);
    } finally {
        searchingProducts.value = false;
    }
};

const handleProductSubmit = () => {
    productForm.post(route('system-type-products.store'), {
        preserveScroll: true,
        onSuccess: () => {
            showProductModal.value = false;
            notification.success({ title: 'Agregado', content: 'Producto predeterminado asignado.' });
        }
    });
};

const handleDeleteProduct = (sysId, productId) => {
    router.delete(route('system-type-products.destroy', { system_type: sysId, product: productId }), {
        preserveScroll: true,
        onSuccess: () => { notification.success({ title: 'Removido', content: 'Producto predeterminado quitado.' }); }
    });
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
                            
                            <!-- AHORA USAMOS 3 COLUMNAS (lg:grid-cols-3) -->
                            <div class="py-4 grid grid-cols-1 lg:grid-cols-3 gap-6">
                                
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
                                                    
                                                    <div class="mt-2 text-[11px] text-indigo-500 font-medium">
                                                        ⏱️ Inicia en {{ item.start_days }} días - Dura {{ item.duration_days }} días
                                                    </div>

                                                    <div v-if="item.is_recurring" class="mt-1 text-[11px] text-blue-500 font-medium flex items-center gap-1">
                                                        <n-icon><SyncOutline/></n-icon> 
                                                        {{ getRecurringText(item.recurring_interval, item.recurring_unit) }}
                                                    </div>

                                                    <div v-if="item.evidence_templates?.length > 0" class="mt-2 text-[11px] text-gray-500 flex flex-wrap gap-1 items-center">
                                                        <n-icon class="text-emerald-500"><CameraOutline/></n-icon> Evidencias requeridas:
                                                        <n-tag v-for="ev in item.evidence_templates" :key="ev.id" size="tiny" type="info" round>{{ ev.title }}</n-tag>
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

                                <!-- COLUMNA 2: EVIDENCIAS REQUERIDAS -->
                                <div>
                                    <div class="flex justify-between items-center mb-4">
                                        <h3 class="text-lg font-bold text-gray-700 flex items-center gap-2">
                                            <n-icon class="text-emerald-500"><CameraOutline/></n-icon> Evidencias
                                        </h3>
                                        <n-button type="primary" size="small" class="bg-emerald-600" @click="openAddEvidenceModal(sys)">
                                            <template #icon><n-icon><AddOutline /></n-icon></template> Agregar
                                        </n-button>
                                    </div>

                                    <div v-if="evidencesBySystem.length > 0" class="space-y-3">
                                        <div v-for="(ev, index) in evidencesBySystem" :key="ev.id" draggable="true" @dragstart="onDragStartEvidence(index)" @dragover.prevent @drop="onDropEvidence(index)">
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
                                </div>

                                <!-- COLUMNA 3: PRODUCTOS PREDETERMINADOS (NUEVO) -->
                                <div>
                                    <div class="flex justify-between items-center mb-4">
                                        <h3 class="text-lg font-bold text-gray-700 flex items-center gap-2">
                                            <n-icon class="text-blue-500"><CubeOutline/></n-icon> Material Requerido
                                        </h3>
                                        <n-button type="primary" size="small" class="bg-blue-600" @click="openAddProductModal(sys)">
                                            <template #icon><n-icon><AddOutline /></n-icon></template> Agregar
                                        </n-button>
                                    </div>

                                    <div v-if="sys.products?.length > 0" class="space-y-3">
                                        <n-card v-for="prod in sys.products" :key="prod.id" size="small" class="rounded-xl shadow-sm border border-blue-100 bg-blue-50/20 hover:shadow-md transition-shadow">
                                            <div class="flex justify-between items-start gap-3">
                                                <div class="flex-1">
                                                    <div class="flex items-center gap-2 mb-1">
                                                        <h4 class="font-bold text-blue-800 text-sm">{{ prod.name }}</h4>
                                                        <n-tag type="info" size="tiny" round>Cant: {{ prod.pivot.quantity }}</n-tag>
                                                    </div>
                                                    <p class="text-xs text-gray-600">SKU: {{ prod.sku }}</p>
                                                </div>

                                                <div class="flex gap-1">
                                                    <n-popconfirm @positive-click="handleDeleteProduct(sys.id, prod.id)" positive-text="Sí, quitar" negative-text="No">
                                                        <template #trigger>
                                                            <n-button circle quaternary size="small" type="error">
                                                                <template #icon><n-icon><TrashOutline /></n-icon></template>
                                                            </n-button>
                                                        </template>
                                                        ¿Quitar producto de este tipo de sistema?
                                                    </n-popconfirm>
                                                </div>
                                            </div>
                                        </n-card>
                                    </div>
                                    <n-empty v-else description="Sin material predeterminado." class="py-8" />
                                </div>

                            </div>
                        </n-tab-pane>
                    </n-tabs>
                </div>

            </div>
        </div>

        <!-- MODAL PRODUCTOS PREDETERMINADOS -->
        <n-modal v-model:show="showProductModal" preset="card" class="max-w-md" title="Agregar Material Predeterminado">
            <n-form :model="productForm" @submit.prevent="handleProductSubmit">
                <n-form-item label="Producto / Material" path="product_id">
                    <n-select
                        v-model:value="productForm.product_id"
                        filterable
                        remote
                        :options="productSearchOptions"
                        :loading="searchingProducts"
                        @search="handleSearchProduct"
                        placeholder="Escribe el nombre o SKU..."
                        clearable
                    />
                </n-form-item>
                
                <n-form-item label="Cantidad predeterminada" path="quantity">
                    <n-input-number v-model:value="productForm.quantity" :min="0.01" :step="1" class="w-full" placeholder="Ej. 5" />
                </n-form-item>
                
                <div class="flex justify-end gap-3 mt-4">
                    <n-button @click="showProductModal = false">Cancelar</n-button>
                    <n-button type="primary" attr-type="submit" :loading="productForm.processing" :disabled="!productForm.product_id" class="bg-blue-600">Guardar Material</n-button>
                </div>
            </n-form>
        </n-modal>

        <!-- MODAL TAREAS -->
        <n-modal v-model:show="showTaskModal" preset="card" class="max-w-lg" :title="isEditingTask ? 'Editar Tarea' : 'Nueva Tarea'">
            <n-form :model="taskForm" @submit.prevent="handleTaskSubmit">
                <n-form-item label="Título de la Tarea" path="title"><n-input v-model:value="taskForm.title" /></n-form-item>
                <n-form-item label="Descripción" path="description"><n-input type="textarea" v-model:value="taskForm.description" /></n-form-item>
                
                <div class="grid grid-cols-2 gap-4">
                    <n-form-item label="Prioridad" path="priority"><n-select v-model:value="taskForm.priority" :options="priorityOptions" /></n-form-item>
                    <n-form-item label="Tipo de Sistema"><n-input :value="taskForm.system_type" disabled /></n-form-item>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <n-form-item label="Días para iniciar" path="start_days">
                        <n-input-number v-model:value="taskForm.start_days" :min="0" class="w-full" placeholder="0 para hoy" />
                    </n-form-item>
                    <n-form-item label="Duración (Días)" path="duration_days">
                        <n-input-number v-model:value="taskForm.duration_days" :min="1" class="w-full" placeholder="Ej. 1" />
                    </n-form-item>
                </div>

                <div class="mb-4">
                    <div class="flex items-center gap-2 mb-2">
                        <n-switch v-model:value="taskForm.is_recurring" />
                        <span class="font-medium text-gray-700">Tarea Cíclica / Recurrente</span>
                    </div>
                    
                    <div v-if="taskForm.is_recurring" class="flex items-center gap-3 p-4 bg-gray-50 rounded-xl border border-gray-100 transition-all">
                        <span class="text-sm text-gray-600 font-medium">Repetir cada:</span>
                        <n-input-number v-model:value="taskForm.recurring_interval" :min="1" class="w-24" />
                        <n-select v-model:value="taskForm.recurring_unit" :options="recurringUnitOptions" class="w-36" />
                    </div>
                </div>

                <n-form-item path="evidences">
                    <template #label>Evidencias Obligatorias para Cerrar Tarea <span class="text-xs text-gray-400 ml-1">(Opcional)</span></template>
                    <n-select v-model:value="taskForm.evidences" multiple :options="evidenceOptions" clearable placeholder="Selecciona evidencias" />
                </n-form-item>

                <n-form-item path="users">
                    <template #label>Asignar Usuarios Automáticamente</template>
                    <n-select v-model:value="taskForm.users" multiple :options="usersOptions" clearable />
                    <template #feedback>
                        <span class="text-amber-600 text-[11px] flex items-center gap-1 mt-1">
                            <n-icon size="14"><InformationCircleOutline /></n-icon> Si no asignas a una persona, la tarea quedará en "Sin asignar".
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