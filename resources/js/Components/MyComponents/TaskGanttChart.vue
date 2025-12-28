<script setup>
import { computed, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { 
    NAvatar, NTooltip, NTag, NEmpty, NButton, NIcon, NModal, NCard, NForm, 
    NFormItem, NInput, NDatePicker, NSelect, createDiscreteApi 
} from 'naive-ui';
import { 
    format, parseISO, differenceInHours, addDays, startOfDay, endOfDay 
} from 'date-fns';
import { es } from 'date-fns/locale';
import { AddOutline, SaveOutline } from '@vicons/ionicons5';

const props = defineProps({
    tasks: {
        type: Array,
        required: true
    },
    orderId: {
        type: Number,
        required: true
    },
    assignableUsers: {
        type: Array,
        default: () => [] // Array de { label: 'Nombre', value: id }
    }
});

const { notification } = createDiscreteApi(['notification']);
const showCreateModal = ref(false);

// Formulario de Tarea
const form = useForm({
    service_order_id: props.orderId,
    title: '',
    description: '',
    due_date: null,
    priority: 'Media',
    user_ids: []
});

const priorityOptions = [
    { label: 'Baja', value: 'Baja' },
    { label: 'Media', value: 'Media' },
    { label: 'Alta', value: 'Alta' }
];

// Mapeo de usuarios para el select (si no vienen ya formateados)
const userOptions = computed(() => {
    return props.assignableUsers.map(u => ({
        label: u.name || u.label,
        value: u.id || u.value
    }));
});

const submitTask = () => {
    // Asegurar ID de orden
    form.service_order_id = props.orderId;
    
    // Formatear fecha para enviar timestamp o string (dependiendo de config global, aquí mandamos el valor del picker directo)
    // Inertia/Laravel maneja timestamps de JS, pero a veces es mejor formatear.
    
    form.post(route('tasks.store'), {
        onSuccess: () => {
            notification.success({ title: 'Éxito', content: 'Tarea creada y asignada.' });
            showCreateModal.value = false;
            form.reset();
        },
        onError: () => {
            notification.error({ title: 'Error', content: 'Revisa los campos del formulario.' });
        }
    });
};

// Configuración de colores por estado
const statusColors = {
    'Pendiente': 'bg-gray-300 border-gray-400 text-gray-700',
    'En Proceso': 'bg-blue-200 border-blue-400 text-blue-800',
    'Completado': 'bg-emerald-200 border-emerald-400 text-emerald-800',
    'Detenido': 'bg-red-200 border-red-400 text-red-800',
};

// 1. Calcular Rango Global de Fechas
const timeRange = computed(() => {
    if (!props.tasks.length) return null;

    // Obtener fechas válidas
    const starts = props.tasks.map(t => t.start ? parseISO(t.start) : null).filter(Boolean);
    const ends = props.tasks.map(t => t.end ? parseISO(t.end) : null).filter(Boolean);

    if (!starts.length) return null;

    let minDate = startOfDay(new Date(Math.min(...starts)));
    let maxDate = endOfDay(new Date(Math.max(...ends)));

    // Si la diferencia es muy corta (menos de 3 días), agregamos buffer visual
    if (differenceInHours(maxDate, minDate) < 72) {
        maxDate = addDays(maxDate, 3);
    }

    const totalHours = differenceInHours(maxDate, minDate);

    return { minDate, maxDate, totalHours };
});

// 2. Generar columnas de días para el encabezado
const timelineDays = computed(() => {
    if (!timeRange.value) return [];
    
    const days = [];
    let current = timeRange.value.minDate;
    
    while (current <= timeRange.value.maxDate) {
        days.push(current);
        current = addDays(current, 1);
    }
    return days;
});

// 3. Calcular posición y ancho de cada barra
const getTaskStyle = (task) => {
    if (!task.start || !task.end || !timeRange.value) return {};

    const start = parseISO(task.start);
    const end = parseISO(task.end);
    const globalStart = timeRange.value.minDate;
    const totalDuration = timeRange.value.totalHours;

    const offsetHours = differenceInHours(start, globalStart);
    const durationHours = differenceInHours(end, start) || 1; // Mínimo 1 hora visual

    const left = (offsetHours / totalDuration) * 100;
    const width = (durationHours / totalDuration) * 100;

    return {
        left: `${left}%`,
        width: `${width}%`
    };
};
</script>

<template>
    <div class="w-full bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="p-4 border-b border-gray-100 flex justify-between items-center bg-gray-50/50">
            <h3 class="font-bold text-gray-700">Cronograma de Ejecución (PMS)</h3>
            <div class="flex items-center gap-4">
                <div class="hidden md:flex gap-2 text-xs">
                    <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-gray-300"></span> Pendiente</span>
                    <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-blue-300"></span> En Proceso</span>
                    <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-emerald-300"></span> Completado</span>
                </div>
                <!-- Botón Agregar Tarea -->
                <n-button type="primary" size="small" ghost @click="showCreateModal = true">
                    <template #icon><n-icon><AddOutline /></n-icon></template>
                    Nueva Tarea
                </n-button>
            </div>
        </div>

        <div v-if="!tasks.length" class="p-8 flex flex-col items-center justify-center">
            <n-empty description="No hay tareas asignadas a esta orden aún." class="mb-4"/>
            <n-button dashed type="primary" @click="showCreateModal = true">
                Crear primera tarea
            </n-button>
        </div>

        <div v-else class="relative overflow-x-auto">
            <div class="min-w-[800px] p-4">
                
                <!-- Encabezado de Fechas (Eje X) -->
                <div class="flex border-b border-gray-200 mb-4 pb-2">
                    <div class="w-1/4 min-w-[200px] font-semibold text-gray-500 text-xs uppercase tracking-wider pl-2">
                        Tarea / Actividad
                    </div>
                    <div class="flex-1 flex relative">
                        <div 
                            v-for="day in timelineDays" 
                            :key="day" 
                            class="flex-1 text-center text-xs text-gray-400 border-l border-gray-100 last:border-r"
                        >
                            <div class="font-bold text-gray-600">{{ format(day, 'dd') }}</div>
                            <div class="text-[10px] uppercase">{{ format(day, 'MMM', { locale: es }) }}</div>
                        </div>
                    </div>
                </div>

                <!-- Lista de Tareas (Eje Y) y Barras -->
                <div class="space-y-4 relative">
                    <!-- Grid de fondo (Líneas verticales) -->
                    <div class="absolute top-0 right-0 bottom-0 left-[25%] flex pointer-events-none opacity-30 z-0">
                         <div v-for="day in timelineDays" :key="'grid-'+day" class="flex-1 border-l border-dashed border-gray-300"></div>
                    </div>

                    <div v-for="task in tasks" :key="task.id" class="flex items-center relative z-10 group">
                        
                        <!-- Nombre de la Tarea -->
                        <div class="w-1/4 min-w-[200px] pr-4">
                            <div class="font-medium text-sm text-gray-800 truncate" :title="task.name">
                                {{ task.name }}
                            </div>
                            <div class="flex -space-x-2 mt-1">
                                <n-avatar 
                                    v-for="user in task.assignees" 
                                    :key="user.id" 
                                    round 
                                    size="small" 
                                    :src="user.avatar"
                                    class="border-2 border-white w-6 h-6"
                                    :fallback-src="'https://ui-avatars.com/api/?name='+user.name"
                                />
                            </div>
                        </div>

                        <!-- Barra de Tiempo -->
                        <div class="flex-1 relative h-10 bg-gray-50 rounded-lg overflow-hidden border border-gray-100/50">
                            
                            <n-tooltip trigger="hover" placement="top">
                                <template #trigger>
                                    <div 
                                        class="absolute top-2 bottom-2 rounded-md shadow-sm transition-all hover:brightness-95 cursor-pointer flex items-center px-2"
                                        :class="statusColors[task.status] || 'bg-gray-200'"
                                        :style="getTaskStyle(task)"
                                    >
                                        <div class="text-[10px] font-bold truncate opacity-90 w-full">
                                            {{ task.progress }}%
                                        </div>
                                    </div>
                                </template>
                                
                                <!-- Tooltip Content -->
                                <div class="text-xs">
                                    <div class="font-bold">{{ task.name }}</div>
                                    <div>Inicio: {{ task.start ? format(parseISO(task.start), 'dd MMM HH:mm') : 'S/D' }}</div>
                                    <div>Fin: {{ task.end ? format(parseISO(task.end), 'dd MMM HH:mm') : 'S/D' }}</div>
                                    <div class="mt-1 font-mono">Estado: {{ task.status }}</div>
                                </div>
                            </n-tooltip>

                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- MODAL DE CREACIÓN DE TAREA -->
        <n-modal v-model:show="showCreateModal">
            <n-card 
                style="width: 600px" 
                title="Nueva Tarea / Actividad" 
                :bordered="false" 
                size="huge" 
                role="dialog" 
                aria-modal="true"
            >
                <template #header-extra>
                    <n-icon size="24" class="text-gray-400 cursor-pointer" @click="showCreateModal=false"><close-outline /></n-icon>
                </template>

                <n-form :model="form" label-placement="top" class="mt-2">
                    <div class="grid grid-cols-2 gap-4">
                        <!-- Título -->
                        <div class="col-span-2">
                            <n-form-item label="Título de la Tarea" path="title">
                                <n-input v-model:value="form.title" placeholder="Ej. Instalación de soportes" />
                            </n-form-item>
                        </div>

                        <!-- Prioridad -->
                        <n-form-item label="Prioridad" path="priority">
                            <n-select v-model:value="form.priority" :options="priorityOptions" />
                        </n-form-item>

                        <!-- Fecha Vencimiento -->
                        <n-form-item label="Fecha Límite / Vencimiento" path="due_date">
                            <n-date-picker 
                                v-model:formatted-value="form.due_date"
                                type="datetime"
                                value-format="yyyy-MM-dd HH:mm:ss"
                                class="w-full" 
                                clearable
                            />
                        </n-form-item>

                        <!-- Asignación (Múltiple) -->
                        <div class="col-span-2">
                            <n-form-item label="Asignar Responsables (Notificación Automática)" path="user_ids">
                                <n-select 
                                    v-model:value="form.user_ids" 
                                    multiple 
                                    :options="userOptions" 
                                    placeholder="Selecciona técnicos..."
                                    filterable
                                />
                            </n-form-item>
                        </div>

                        <!-- Descripción -->
                        <div class="col-span-2">
                            <n-form-item label="Descripción Detallada" path="description">
                                <n-input 
                                    v-model:value="form.description" 
                                    type="textarea" 
                                    placeholder="Instrucciones específicas..." 
                                />
                            </n-form-item>
                        </div>
                    </div>
                </n-form>

                <template #footer>
                    <div class="flex justify-end gap-3">
                        <n-button @click="showCreateModal = false">Cancelar</n-button>
                        <n-button type="primary" @click="submitTask" :loading="form.processing">
                            <template #icon><n-icon><SaveOutline /></n-icon></template>
                            Guardar y Asignar
                        </n-button>
                    </div>
                </template>
            </n-card>
        </n-modal>
    </div>
</template>