<script setup>
import { computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { 
    NForm, NFormItem, NInput, NSelect, NButton, NDatePicker 
} from 'naive-ui';
import { format, parseISO } from 'date-fns';

const props = defineProps({
    task: {
        type: Object,
        default: null
    },
    assignableUsers: {
        type: Array,
        required: true
    },
    serviceOrders: {
        type: Array,
        default: () => []
    },
    tickets: {
        type: Array,
        default: () => []
    }
});

const emit = defineEmits(['close', 'saved']);

// Iniciar formulario de Inertia
const form = useForm({
    title: props.task?.title || '',
    description: props.task?.description || '',
    priority: props.task?.priority || 'Media',
    status: props.task?.status || 'Pendiente',
    start_date: props.task?.start_date ? parseISO(props.task.start_date).getTime() : null,
    due_date: props.task?.due_date ? parseISO(props.task.due_date).getTime() : null,
    user_ids: props.task?.assignees?.map(u => u.id) || [],
    taskable_type: props.task?.taskable_type || 'General', // <-- Cambio clave: Usar texto por defecto
    taskable_id: props.task?.taskable_id || null
});

const typeOptions = [
    { label: 'General (Sin asignar a módulo)', value: 'General' }, // <-- Uso de cadena no nula
    { label: 'Orden de Servicio', value: 'App\\Models\\ServiceOrder' },
    { label: 'Ticket de Soporte', value: 'App\\Models\\Ticket' }
];

const getTaskableOptions = computed(() => {
    if (form.taskable_type === 'App\\Models\\ServiceOrder') {
        return props.serviceOrders.map(o => {
            // Obtenemos el nombre del cliente o un valor por defecto si no tiene
            const clientName = o.client?.name ? o.client.name : 'Sin Cliente';
            return { 
                label: `OS#${o.id} - ${clientName}`, 
                value: o.id 
            };
        });
    }
    if (form.taskable_type === 'App\\Models\\Ticket') {
        return props.tickets.map(t => {
            // Aplicamos la misma lógica para los tickets por consistencia
            const clientName = t.client?.name ? t.client.name : 'Sin Cliente';
            return { 
                label: `Ticket #${t.id} - ${clientName}`, 
                value: t.id 
            };
        });
    }
    return [];
});

const onTypeChange = () => {
    form.taskable_id = null;
};

const priorityOptions = [
    { label: 'Alta', value: 'Alta' },
    { label: 'Media', value: 'Media' },
    { label: 'Baja', value: 'Baja' }
];

const statusOptions = [
    { label: 'Pendiente', value: 'Pendiente' },
    { label: 'En Proceso', value: 'En Proceso' },
    { label: 'Completado', value: 'Completado' },
    { label: 'Detenido', value: 'Detenido' }
];

const userOptions = computed(() => 
    props.assignableUsers.map(u => ({ label: u.name, value: u.id }))
);

const submit = () => {
    // Formatear fechas antes de enviar
    const payload = { ...form.data() };
    if (payload.start_date) payload.start_date = format(new Date(payload.start_date), 'yyyy-MM-dd HH:mm:ss');
    if (payload.due_date) payload.due_date = format(new Date(payload.due_date), 'yyyy-MM-dd HH:mm:ss');

    // <-- Transformar el 'General' a nulo para el Backend
    if (payload.taskable_type === 'General') {
        payload.taskable_type = null;
    }

    if (props.task) {
        // Modo Edición
        form.transform(() => payload).put(route('tasks.update', props.task.id), {
            onSuccess: () => emit('saved'),
            preserveScroll: true
        });
    } else {
        // Modo Creación
        form.transform(() => payload).post(route('tasks.store'), {
            onSuccess: () => emit('saved'),
            preserveScroll: true
        });
    }
};
</script>

<template>
    <n-form :model="form" @submit.prevent="submit" class="space-y-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <n-form-item label="Tipo de Tarea" required>
                <n-select v-model:value="form.taskable_type" :options="typeOptions" @update:value="onTypeChange" />
            </n-form-item>
            
            <!-- El label se mantiene siempre, y el required es dinámico -->
            <n-form-item label="Referencia (Módulo)" :required="form.taskable_type !== 'General'">
                
                <!-- Si se seleccionó un módulo (Orden o Ticket), mostramos el selector normal -->
                <n-select 
                    v-if="form.taskable_type !== 'General'" 
                    v-model:value="form.taskable_id" 
                    :options="getTaskableOptions" 
                    placeholder="Seleccione..." 
                    filterable 
                />
                
                <!-- Si es nulo (General), mostramos un texto claro deshabilitado -->
                <n-input 
                    v-else 
                    value="General" 
                    disabled 
                />
                
            </n-form-item>
        </div>

        <n-form-item label="Título de la Tarea" required>
            <n-input v-model:value="form.title" placeholder="Ej. Revisión de paneles" />
        </n-form-item>

        <n-form-item label="Descripción">
            <n-input v-model:value="form.description" type="textarea" placeholder="Detalles de la tarea..." :rows="3" />
        </n-form-item>

        <div class="grid grid-cols-2 gap-4">
            <n-form-item label="Prioridad" required>
                <n-select v-model:value="form.priority" :options="priorityOptions" />
            </n-form-item>
            
            <n-form-item label="Estatus" v-if="task" required>
                <n-select v-model:value="form.status" :options="statusOptions" />
            </n-form-item>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <n-form-item label="Fecha y Hora de Inicio">
                <!-- <-- Cambio: type="datetime" para poder escoger la hora -->
                <n-date-picker v-model:value="form.start_date" type="datetime" clearable class="w-full" />
            </n-form-item>
            
            <n-form-item label="Fecha y Hora Límite">
                <!-- <-- Cambio: type="datetime" para poder escoger la hora -->
                <n-date-picker v-model:value="form.due_date" type="datetime" clearable class="w-full" />
            </n-form-item>
        </div>

        <n-form-item label="Responsables">
            <n-select v-model:value="form.user_ids" multiple :options="userOptions" placeholder="Asignar a..." filterable />
        </n-form-item>

        <div class="flex justify-end gap-2 mt-6">
            <n-button @click="$emit('close')">Cancelar</n-button>
            <n-button type="primary" attr-type="submit" :loading="form.processing">
                {{ task ? 'Guardar Cambios' : 'Crear Tarea' }}
            </n-button>
        </div>
    </n-form>
</template>