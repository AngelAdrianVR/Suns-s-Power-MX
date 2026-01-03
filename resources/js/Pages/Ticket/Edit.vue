<script setup>
import { ref, watch } from 'vue';
import { useForm, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { 
    NForm, NFormItem, NInput, NSelect, NButton, NCard, NUpload, NIcon, NGrid, NGridItem, createDiscreteApi, NTag, NList, NListItem, NThing
} from 'naive-ui';
import { 
    SaveOutline, ArrowBackOutline, CloudUploadOutline, PersonOutline, AlertCircleOutline, TicketOutline, DocumentTextOutline, TrashOutline
} from '@vicons/ionicons5';

const props = defineProps({
    ticket: Object,
    clients: Array,
    serviceOrders: Array // Ahora recibimos todas las órdenes disponibles
});

const { notification, dialog } = createDiscreteApi(['notification', 'dialog']);
const formRef = ref(null);

// Mapeo de opciones
const clientOptions = props.clients.map(client => ({
    label: client.name,
    value: client.id
}));

const serviceOrderOptions = props.serviceOrders.map(order => ({
    label: order.label,
    value: order.id
}));

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

// Inicialización del formulario con datos existentes
const form = useForm({
    _method: 'PUT', // Método spoofing para Laravel update con archivos
    title: props.ticket.title,
    description: props.ticket.description,
    client_id: props.ticket.client_id,
    related_service_order_id: props.ticket.related_service_order_id,
    priority: props.ticket.priority,
    status: props.ticket.status,
    resolution_notes: props.ticket.resolution_notes || '',
    evidence: [], // Nuevos archivos
});

// Lógica de control de cliente (Bloqueo si hay orden seleccionada)
const isClientDisabled = ref(!!form.related_service_order_id);

watch(
    () => form.related_service_order_id,
    (newOrderId) => {
        if (newOrderId) {
            const selectedOrder = props.serviceOrders.find(o => o.id === newOrderId);
            if (selectedOrder) {
                form.client_id = selectedOrder.client_id;
                isClientDisabled.value = true;
                notification.info({ 
                    content: 'Cliente actualizado según la orden seleccionada',
                    duration: 2000
                });
            }
        } else {
            // Si desvincula la orden, permitimos cambiar el cliente libremente
            isClientDisabled.value = false;
        }
    }
);

const rules = {
    title: { required: true, message: 'El asunto es obligatorio', trigger: 'blur' },
    description: { required: true, message: 'La descripción es obligatoria', trigger: 'blur' },
    client_id: { required: true, type: 'number', message: 'Selecciona un cliente', trigger: ['blur', 'change'] },
    priority: { required: true, message: 'Selecciona una prioridad', trigger: ['blur', 'change'] },
    status: { required: true, message: 'Selecciona un estatus', trigger: ['blur', 'change'] }
};

// Manejo de carga de NUEVOS archivos
const handleUploadChange = (data) => {
    form.evidence = data.fileList.map(file => file.file);
};

// Función para eliminar archivo existente (Llamada al backend)
const deleteExistingFile = (fileId) => {
    dialog.warning({
        title: 'Eliminar Evidencia',
        content: '¿Estás seguro de eliminar este archivo permanentemente?',
        positiveText: 'Eliminar',
        negativeText: 'Cancelar',
        onPositiveClick: () => {
            // Usamos router.delete para llamar a la ruta genérica de media
            router.delete(route('media.delete-file', fileId), {
                preserveScroll: true,
                onSuccess: () => {
                    notification.success({ content: 'Archivo eliminado', duration: 2000 });
                    // Recargar la página para actualizar la lista de props.ticket.evidence
                    router.reload({ only: ['ticket'] });
                },
                onError: () => {
                    notification.error({ content: 'Error al eliminar archivo' });
                }
            });
        }
    });
};

const submit = () => {
    formRef.value?.validate((errors) => {
        if (!errors) {
            // Usamos post con _method: PUT para permitir envío de archivos
            form.post(route('tickets.update', props.ticket.id), {
                forceFormData: true, 
                onSuccess: () => {
                    notification.success({
                        title: 'Ticket Actualizado',
                        content: 'Los cambios se han guardado correctamente.',
                        duration: 3000
                    });
                },
                onError: () => {
                    notification.error({
                        title: 'Error',
                        content: 'Revisa los campos del formulario.',
                        duration: 4000
                    });
                }
            });
        }
    });
};
</script>

<template>
    <AppLayout title="Editar Ticket">
        <template #header>
            <div class="flex items-center gap-4">
                <Link :href="route('tickets.index')">
                    <n-button circle secondary type="default">
                        <template #icon><n-icon><ArrowBackOutline /></n-icon></template>
                    </n-button>
                </Link>
                <div class="flex flex-col">
                    <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                        Editar Ticket #{{ ticket.id }}
                    </h2>
                    <span class="text-xs text-gray-500">Gestión y seguimiento de incidencia</span>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
                
                <n-form
                    ref="formRef"
                    :model="form"
                    :rules="rules"
                    label-placement="top"
                    require-mark-placement="right-hanging"
                    size="large"
                >
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        
                        <!-- Columna Izquierda: Datos Principales -->
                        <div class="lg:col-span-2 space-y-6">
                            
                            <!-- Detalles del Problema -->
                            <n-card :bordered="false" class="shadow-sm rounded-2xl">
                                <template #header>
                                    <span class="text-gray-600 font-semibold flex items-center gap-2">
                                        <n-icon :component="TicketOutline"/> Información del Incidente
                                    </span>
                                </template>

                                <n-grid x-gap="12" :cols="1">
                                    <n-grid-item>
                                        <n-form-item label="Asunto" path="title" :feedback="form.errors.title" :validation-status="form.errors.title ? 'error' : undefined">
                                            <n-input v-model:value="form.title" />
                                        </n-form-item>
                                    </n-grid-item>
                                    
                                    <!-- Selectores Reorganizados para permitir cambio -->
                                    <n-grid-item>
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            <n-form-item label="Orden de Servicio Relacionada" path="related_service_order_id">
                                                <n-select 
                                                    v-model:value="form.related_service_order_id" 
                                                    :options="serviceOrderOptions" 
                                                    placeholder="Vincular con una instalación..."
                                                    filterable
                                                    clearable
                                                >
                                                    <template #prefix>
                                                        <n-icon :component="DocumentTextOutline" />
                                                    </template>
                                                </n-select>
                                            </n-form-item>

                                            <n-form-item label="Cliente" path="client_id">
                                                <n-select 
                                                    v-model:value="form.client_id" 
                                                    :options="clientOptions" 
                                                    filterable 
                                                    :disabled="isClientDisabled"
                                                    placeholder="Selecciona un cliente" 
                                                >
                                                    <template #prefix>
                                                        <n-icon :component="PersonOutline" />
                                                    </template>
                                                </n-select>
                                            </n-form-item>
                                        </div>
                                    </n-grid-item>

                                    <n-grid-item>
                                        <n-form-item label="Descripción Original" path="description">
                                            <n-input v-model:value="form.description" type="textarea" :autosize="{ minRows: 3, maxRows: 6 }" />
                                        </n-form-item>
                                    </n-grid-item>
                                </n-grid>
                            </n-card>

                            <!-- Sección de Resolución (Importante para editar/cerrar) -->
                            <n-card :bordered="false" class="shadow-sm rounded-2xl bg-green-50/30">
                                <template #header>
                                    <span class="text-green-700 font-semibold flex items-center gap-2">
                                        <n-icon :component="DocumentTextOutline"/> Notas de Resolución
                                    </span>
                                </template>
                                <n-form-item 
                                    label="Bitácora de solución / Comentarios Técnicos" 
                                    path="resolution_notes"
                                >
                                    <n-input 
                                        v-model:value="form.resolution_notes" 
                                        type="textarea" 
                                        placeholder="Escribe aquí los pasos realizados para solucionar el problema o notas internas..."
                                        :autosize="{ minRows: 4, maxRows: 10 }" 
                                        class="bg-white"
                                    />
                                </n-form-item>
                            </n-card>
                        </div>

                        <!-- Columna Derecha: Estado, Prioridad y Archivos -->
                        <div class="space-y-6">
                            
                            <!-- Panel de Control -->
                            <n-card :bordered="false" class="shadow-sm rounded-2xl bg-orange-50/50">
                                <template #header>
                                    <span class="text-orange-800 font-semibold flex items-center gap-2">
                                        <n-icon :component="AlertCircleOutline"/> Gestión
                                    </span>
                                </template>
                                
                                <div class="grid gap-4">
                                    <n-form-item label="Prioridad" path="priority">
                                        <n-select v-model:value="form.priority" :options="priorityOptions" />
                                    </n-form-item>

                                    <n-form-item label="Estatus Actual" path="status">
                                        <n-select v-model:value="form.status" :options="statusOptions" />
                                    </n-form-item>
                                </div>
                            </n-card>

                            <!-- Archivos Existentes -->
                            <n-card v-if="ticket.evidence && ticket.evidence.length > 0" :bordered="false" class="shadow-sm rounded-2xl">
                                <template #header>
                                    <span class="text-gray-600 font-semibold text-sm">Evidencias Adjuntas</span>
                                </template>
                                <n-list hoverable clickable>
                                    <n-list-item v-for="file in ticket.evidence" :key="file.id">
                                        <n-thing :title="file.name" content-style="margin-top: 0;">
                                            <template #description>
                                                <div class="flex gap-2 mt-1">
                                                    <a :href="file.url" target="_blank" class="text-xs text-blue-500 hover:underline">Ver/Descargar</a>
                                                </div>
                                            </template>
                                            <template #header-extra>
                                                <n-button circle size="tiny" type="error" ghost @click="deleteExistingFile(file.id)">
                                                    <template #icon><n-icon><TrashOutline/></n-icon></template>
                                                </n-button>
                                            </template>
                                        </n-thing>
                                    </n-list-item>
                                </n-list>
                            </n-card>

                            <!-- Subir Nuevos Archivos -->
                            <n-card :bordered="false" class="shadow-sm rounded-2xl">
                                <template #header>
                                    <span class="text-gray-600 font-semibold text-sm">Agregar Nueva Evidencia</span>
                                </template>
                                <n-form-item :show-label="false">
                                    <n-upload
                                        multiple
                                        directory-dnd
                                        :max="5"
                                        accept=".jpg,.jpeg,.png,.pdf,.mp4"
                                        @change="handleUploadChange"
                                        :default-upload="false" 
                                    >
                                        <n-upload-dragger>
                                            <div class="flex flex-col items-center justify-center text-gray-400 py-3">
                                                <n-icon size="30" :component="CloudUploadOutline" />
                                                <span class="text-xs mt-2">Arrastra o click para subir</span>
                                            </div>
                                        </n-upload-dragger>
                                    </n-upload>
                                </n-form-item>
                            </n-card>

                            <!-- Botones -->
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
                                    Guardar Cambios
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