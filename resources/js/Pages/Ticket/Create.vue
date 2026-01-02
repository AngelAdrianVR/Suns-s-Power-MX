<script setup>
import { ref } from 'vue';
import { useForm, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { 
    NForm, NFormItem, NInput, NSelect, NButton, NCard, NUpload, NIcon, NGrid, NGridItem, createDiscreteApi 
} from 'naive-ui';
import { 
    SaveOutline, ArrowBackOutline, CloudUploadOutline, PersonOutline, AlertCircleOutline, TicketOutline
} from '@vicons/ionicons5';

const props = defineProps({
    clients: Array
});

const { notification } = createDiscreteApi(['notification']);
const formRef = ref(null);

// Mapeo de clientes para el select
const clientOptions = props.clients.map(client => ({
    label: client.name,
    value: client.id
}));

// Opciones estáticas según la migración
const priorityOptions = [
    { label: 'Baja', value: 'Baja' },
    { label: 'Media', value: 'Media' },
    { label: 'Alta', value: 'Alta' },
    { label: 'Urgente', value: 'Urgente' }
];

const statusOptions = [
    { label: 'Abierto', value: 'Abierto' },
    { label: 'En Análisis', value: 'En Análisis' },
    { label: 'Resuelto', value: 'Resuelto' },
    { label: 'Cerrado', value: 'Cerrado' }
];

const form = useForm({
    title: '',
    description: '',
    client_id: null,
    related_service_order_id: null, // Opcional, por ahora nulo
    priority: 'Media', // Valor por defecto
    status: 'Abierto', // Valor por defecto
    evidence: [], // Array para múltiples archivos
});

const rules = {
    title: { required: true, message: 'El asunto es obligatorio', trigger: 'blur' },
    description: { required: true, message: 'La descripción del problema es obligatoria', trigger: 'blur' },
    client_id: { required: true, type: 'number', message: 'Selecciona un cliente', trigger: ['blur', 'change'] },
    priority: { required: true, message: 'Selecciona una prioridad', trigger: ['blur', 'change'] },
    status: { required: true, message: 'Selecciona un estatus inicial', trigger: ['blur', 'change'] }
};

// Manejo de archivos (Múltiples)
const handleUploadChange = (data) => {
    // Naive UI devuelve fileList. Actualizamos el form con los archivos raw.
    form.evidence = data.fileList.map(file => file.file);
};

const submit = () => {
    formRef.value?.validate((errors) => {
        if (!errors) {
            form.post(route('tickets.store'), {
                forceFormData: true, // Necesario para subir archivos
                onSuccess: () => {
                    notification.success({
                        title: 'Ticket Creado',
                        content: 'El ticket de soporte se ha registrado correctamente.',
                        duration: 3000
                    });
                },
                onError: () => {
                    notification.error({
                        title: 'Error de Validación',
                        content: 'Revisa los campos marcados en rojo.',
                        duration: 4000
                    });
                }
            });
        } else {
            notification.warning({
                title: 'Formulario Incompleto',
                content: 'Por favor completa los campos requeridos.',
                duration: 3000
            });
        }
    });
};
</script>

<template>
    <AppLayout title="Nuevo Ticket">
        <template #header>
            <div class="flex items-center gap-4">
                <Link :href="route('tickets.index')">
                    <n-button circle secondary type="default">
                        <template #icon><n-icon><ArrowBackOutline /></n-icon></template>
                    </n-button>
                </Link>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Registrar Nuevo Ticket
                </h2>
            </div>
        </template>

        <div class="py-8">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
                
                <n-form
                    ref="formRef"
                    :model="form"
                    :rules="rules"
                    label-placement="top"
                    require-mark-placement="right-hanging"
                    size="large"
                >
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        
                        <!-- Columna Izquierda: Datos del Problema -->
                        <div class="md:col-span-2 space-y-6">
                            
                            <n-card :bordered="false" class="shadow-sm rounded-2xl">
                                <template #header>
                                    <span class="text-gray-600 font-semibold flex items-center gap-2">
                                        <n-icon :component="TicketOutline"/> Detalles del Incidente
                                    </span>
                                </template>

                                <n-grid x-gap="12" :cols="1">
                                    <n-grid-item>
                                        <n-form-item 
                                            label="Asunto / Título del Problema" 
                                            path="title"
                                            :validation-status="form.errors.title ? 'error' : undefined"
                                            :feedback="form.errors.title"
                                        >
                                            <n-input v-model:value="form.title" placeholder="Ej. Falla en inversor central" />
                                        </n-form-item>
                                    </n-grid-item>

                                    <n-grid-item>
                                        <n-form-item 
                                            label="Cliente Afectado" 
                                            path="client_id"
                                            :validation-status="form.errors.client_id ? 'error' : undefined"
                                            :feedback="form.errors.client_id"
                                        >
                                            <n-select 
                                                v-model:value="form.client_id" 
                                                :options="clientOptions" 
                                                placeholder="Buscar cliente..."
                                                filterable
                                                clearable
                                            >
                                                <template #prefix>
                                                    <n-icon :component="PersonOutline" />
                                                </template>
                                            </n-select>
                                        </n-form-item>
                                    </n-grid-item>

                                    <n-grid-item>
                                        <n-form-item 
                                            label="Descripción Detallada" 
                                            path="description"
                                            :validation-status="form.errors.description ? 'error' : undefined"
                                            :feedback="form.errors.description"
                                        >
                                            <n-input 
                                                v-model:value="form.description" 
                                                type="textarea" 
                                                placeholder="Describe el problema, códigos de error visibles, circunstancias, etc."
                                                :autosize="{ minRows: 5, maxRows: 8 }" 
                                            />
                                        </n-form-item>
                                    </n-grid-item>
                                </n-grid>
                            </n-card>
                        </div>

                        <!-- Columna Derecha: Clasificación y Evidencias -->
                        <div class="space-y-6">
                            
                            <!-- Clasificación -->
                            <n-card :bordered="false" class="shadow-sm rounded-2xl bg-orange-50/50">
                                <template #header>
                                    <span class="text-orange-800 font-semibold flex items-center gap-2">
                                        <n-icon :component="AlertCircleOutline"/> Clasificación
                                    </span>
                                </template>
                                
                                <n-grid x-gap="12" :cols="1">
                                    <n-grid-item>
                                        <n-form-item 
                                            label="Prioridad" 
                                            path="priority"
                                            :validation-status="form.errors.priority ? 'error' : undefined"
                                            :feedback="form.errors.priority"
                                        >
                                            <n-select 
                                                v-model:value="form.priority" 
                                                :options="priorityOptions"
                                            />
                                        </n-form-item>
                                    </n-grid-item>

                                    <n-grid-item>
                                        <n-form-item 
                                            label="Estatus Inicial" 
                                            path="status"
                                            :validation-status="form.errors.status ? 'error' : undefined"
                                            :feedback="form.errors.status"
                                        >
                                            <n-select 
                                                v-model:value="form.status" 
                                                :options="statusOptions"
                                            />
                                        </n-form-item>
                                    </n-grid-item>
                                </n-grid>
                            </n-card>

                            <!-- Evidencias -->
                            <n-card :bordered="false" class="shadow-sm rounded-2xl">
                                <template #header>
                                    <span class="text-gray-600 font-semibold">Evidencias (Fotos/Docs)</span>
                                </template>
                                
                                <n-form-item :show-label="false">
                                    <n-upload
                                        multiple
                                        directory-dnd
                                        :max="5"
                                        accept=".jpg,.jpeg,.png,.pdf,.mp4"
                                        @change="handleUploadChange"
                                        :default-upload="false" 
                                        list-type="image"
                                    >
                                        <n-upload-dragger>
                                            <div class="flex flex-col items-center justify-center text-gray-400 py-4">
                                                <n-icon size="36" :component="CloudUploadOutline" class="mb-2" />
                                                <span class="text-sm font-semibold">Click o arrastrar archivos</span>
                                                <span class="text-xs mt-1 text-gray-300">Máx 5 archivos (Img, PDF)</span>
                                            </div>
                                        </n-upload-dragger>
                                    </n-upload>
                                </n-form-item>
                            </n-card>

                            <!-- Botones de Acción -->
                            <div class="flex flex-col gap-3">
                                <n-button 
                                    type="primary" 
                                    size="large" 
                                    block 
                                    @click="submit" 
                                    :loading="form.processing"
                                    :disabled="form.processing"
                                >
                                    <template #icon><n-icon><SaveOutline /></n-icon></template>
                                    Guardar Ticket
                                </n-button>
                                
                                <Link :href="route('tickets.index')" class="w-full">
                                    <n-button block ghost type="error">
                                        Cancelar
                                    </n-button>
                                </Link>
                            </div>
                        </div>

                    </div>
                </n-form>

            </div>
        </div>
    </AppLayout>
</template>