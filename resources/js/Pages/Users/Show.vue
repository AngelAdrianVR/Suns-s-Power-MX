<script>
import AppLayout from '@/Layouts/AppLayout.vue';
import FileView from "@/Components/MyComponents/FileView.vue"; 
import { Head, Link, router } from '@inertiajs/vue3';
import { usePermissions } from '@/Composables/usePermissions'; // 1. Importar composable
import axios from 'axios'; 
import { 
    NCard, NAvatar, NTag, NDescriptions, NDescriptionsItem, NButton, NIcon, 
    NDivider, NTabs, NTabPane, NList, NListItem, NThing, NEmpty, createDiscreteApi,
    NSpin 
} from 'naive-ui';
import { 
    ArrowBackOutline, CreateOutline, MailOutline, BusinessOutline, 
    CalendarOutline, PowerOutline, CheckmarkCircleOutline, TimeOutline, AlertCircleOutline,
    CloudUploadOutline, DocumentAttachOutline, CallOutline
} from '@vicons/ionicons5';

export default {
    components: {
        AppLayout, Head, Link, NCard, NAvatar, NTag, NDescriptions, NDescriptionsItem,
        NButton, NIcon, NDivider, NTabs, NTabPane, NList, NListItem, NThing, NEmpty, NSpin, FileView,
        // Iconos
        ArrowBackOutline, CreateOutline, MailOutline, BusinessOutline, CalendarOutline,
        PowerOutline, CheckmarkCircleOutline, TimeOutline, AlertCircleOutline,
        CloudUploadOutline, DocumentAttachOutline, CallOutline
    },
    props: {
        user: {
            type: Object,
            required: true
        },
        lastTasks: {
            type: Array,
            default: () => []
        }
    },
    // 2. Usar setup como puente para Composition API
    setup() {
        const { notification, dialog } = createDiscreteApi(['notification', 'dialog']);
        const { hasPermission } = usePermissions(); // Extraer permisos
        
        return { 
            notification,
            dialog,
            hasPermission, // Exponer al template
            CheckmarkCircleOutline,
            AlertCircleOutline,
            CloudUploadOutline
        };
    },
    computed: {
        formattedDate() {
            if (!this.user.created_at) return 'N/A';
            const date = new Date(this.user.created_at);
            return date.toLocaleDateString('es-MX', { year: 'numeric', month: 'long', day: 'numeric' });
        }
    },
    methods: {
        goBack() {
            router.visit(route('users.index'));
        },
        goToEdit() {
            router.get(route('users.edit', this.user.id));
        },
        toggleStatus() {
            router.patch(route('users.toggle-status', this.user.id), {}, {
                preserveScroll: true,
                onSuccess: () => {
                    this.notification.create({
                        type: 'success',
                        title: 'Estado Actualizado',
                        content: `El estado del usuario ha sido actualizado correctamente.`,
                        duration: 3000
                    });
                }
            });
        },
        deleteFile(fileId) {
            // Eliminar visualmente (la lógica real ya la maneja FileView internamente o el padre)
            // Asumiendo que FileView emite el evento para que el padre actualice el estado local
            this.user.media = this.user.media.filter(m => m.id !== fileId);
        },
        getTaskStatusType(status) {
            switch (status) {
                case 'Completado': return 'success';
                case 'En Proceso': return 'info';
                case 'Detenido': return 'error';
                default: return 'default';
            }
        },
        formatDateShort(dateString) {
            if (!dateString) return '';
            const date = new Date(dateString);
            return date.toLocaleDateString('es-MX', { day: '2-digit', month: 'short' });
        }
    }
}
</script>

<template>
    <AppLayout :title="`Usuario: ${user.name}`">
        <template #header>
            <div class="flex items-center gap-4">
                <n-button circle secondary @click="goBack">
                    <template #icon>
                        <n-icon><ArrowBackOutline /></n-icon>
                    </template>
                </n-button>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Perfil de Usuario
                </h2>
            </div>
        </template>

        <div class="py-8 min-h-screen">
            <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
                
                <!-- Tarjeta Principal de Información -->
                <div class="bg-white/80 backdrop-blur-xl rounded-3xl shadow-lg border border-gray-100 overflow-hidden p-6 md:p-8">
                    
                    <div class="flex flex-col md:flex-row items-center md:items-start gap-8">
                        
                        <!-- Columna Izquierda: Avatar y Estado -->
                        <div class="flex flex-col items-center space-y-4 min-w-[120px]">
                            <n-avatar 
                                round 
                                :size="120" 
                                :src="user.profile_photo_url" 
                                class="border-4 border-white shadow-md"
                            />
                            <div class="flex flex-col items-center gap-2">
                                <n-tag 
                                    :type="user.is_active ? 'success' : 'error'" 
                                    round 
                                    size="medium"
                                    :bordered="false"
                                >
                                    <template #icon>
                                        <n-icon :component="user.is_active ? CheckmarkCircleOutline : AlertCircleOutline" />
                                    </template>
                                    {{ user.is_active ? 'Activo' : 'Inactivo' }}
                                </n-tag>
                            </div>
                        </div>

                        <!-- Columna Derecha: Detalles y Acciones -->
                        <div class="flex-grow w-full">
                            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-3 mb-5">
                                <div>
                                    <h1 class="text-3xl font-bold text-gray-800">{{ user.name }}</h1>
                                    <p class="text-gray-500 flex items-center gap-2 mt-1">
                                        <n-icon><MailOutline /></n-icon>
                                        {{ user.email }}
                                    </p>
                                    <p class="text-gray-500 flex items-center gap-2">
                                        <n-icon><CallOutline /></n-icon>
                                        {{ user.phone || 'No proporcionado' }}
                                    </p>
                                </div>
                                <div class="flex flex-col md:flex-row gap-2 w-full md:w-auto">
                                    
                                    <!-- Botón Editar: Protegido por users.edit -->
                                    <Link v-if="hasPermission('users.edit')" :href="route('users.edit', user.id)" class="flex-1 md:flex-none">
                                        <n-button type="warning" ghost round block class="md:w-auto">
                                            <template #icon>
                                                <n-icon><CreateOutline /></n-icon>
                                            </template>
                                            Editar/Reestablecer Contraseña
                                        </n-button>
                                    </Link>
                                    
                                    <!-- Botón Estado: Protegido por users.toggle_status -->
                                    <n-button 
                                        v-if="hasPermission('users.toggle_status')"
                                        :type="user.is_active ? 'error' : 'success'" 
                                        secondary 
                                        round 
                                        class="flex-1 md:flex-none md:w-auto"
                                        @click="toggleStatus"
                                    >
                                        <template #icon>
                                            <n-icon><PowerOutline /></n-icon>
                                        </template>
                                        {{ user.is_active ? 'Desactivar' : 'Activar' }}
                                    </n-button>
                                </div>
                            </div>

                            <n-divider class="my-4" />

                            <n-descriptions label-placement="top" :column="2" class="mt-4">
                                <n-descriptions-item>
                                    <template #label>
                                        <div class="flex items-center gap-1 font-semibold text-gray-600 text-xs uppercase tracking-wider">
                                            <n-icon><BusinessOutline /></n-icon>
                                            Sucursal
                                        </div>
                                    </template>
                                    <span v-if="user.branch" class="text-base font-medium text-gray-700">
                                        {{ user.branch.name }}
                                    </span>
                                    <span class="text-gray-400 italic" v-else>Sin asignar</span>
                                </n-descriptions-item>

                                <n-descriptions-item>
                                    <template #label>
                                        <div class="flex items-center gap-1 font-semibold text-gray-600 text-xs uppercase tracking-wider">
                                            <n-icon><CalendarOutline /></n-icon>
                                            Fecha de Registro
                                        </div>
                                    </template>
                                    <span class="text-base font-medium text-gray-700">
                                        {{ formattedDate }}
                                    </span>
                                </n-descriptions-item>
                            </n-descriptions>
                        </div>
                    </div>
                </div>

                <!-- Sección de Pestañas (Tabs) -->
                <div class="bg-white/80 backdrop-blur-xl rounded-3xl shadow-lg border border-gray-100 overflow-hidden min-h-[400px]">
                    <n-tabs type="line" size="large" animated pane-class="p-6">
                        
                        <!-- Tab 1: Últimas Tareas -->
                        <n-tab-pane name="tasks" tab="Tareas Recientes">
                            <div v-if="lastTasks.length > 0">
                                <n-list hoverable clickable>
                                    <n-list-item v-for="task in lastTasks" :key="task.id">
                                        <template #prefix>
                                            <div class="h-10 w-10 rounded-full bg-indigo-50 flex items-center justify-center text-indigo-600">
                                                <n-icon size="20"><TimeOutline /></n-icon>
                                            </div>
                                        </template>
                                        <n-thing :title="task.title" content-style="margin-top: 4px;">
                                            <template #description>
                                                <span class="text-xs text-gray-400">
                                                    Asignada el {{ formatDateShort(task.created_at) }}
                                                </span>
                                            </template>
                                            <div class="text-sm text-gray-600 line-clamp-1">
                                                {{ task.description || 'Sin descripción' }}
                                            </div>
                                        </n-thing>
                                        <template #suffix>
                                            <n-tag :type="getTaskStatusType(task.status)" size="small" round :bordered="false">
                                                {{ task.status }}
                                            </n-tag>
                                        </template>
                                    </n-list-item>
                                </n-list>
                                <div class="mt-4 text-center">
                                    <span class="text-xs text-gray-400">Mostrando las últimas {{ lastTasks.length }} tareas</span>
                                </div>
                            </div>
                            <div v-else class="flex flex-col items-center justify-center py-10">
                                <n-empty description="Este usuario no tiene tareas asignadas recientemente.">
                                    <!-- Solo mostrar botón de asignar si tiene permisos para gestionar tareas (opcional, o users.edit) -->
                                    <template #extra>
                                        <n-button v-if="hasPermission('users.assign_tasks')" size="small" dashed>
                                            Asignar Tarea
                                        </n-button>
                                        <n-button v-else size="small" dashed disabled>
                                            Sin actividad reciente
                                        </n-button>
                                    </template>
                                </n-empty>
                            </div>
                        </n-tab-pane>

                        <!-- Tab 2: Documentación -->
                        <n-tab-pane name="docs" tab="Documentación">
                            <!-- Caso: Hay Documentos -->
                            <div v-if="user.media && user.media.length > 0" class="p-5">
                                <div class="flex justify-between items-center mb-4">
                                    <h3 class="text-lg font-bold text-gray-700">Archivos ({{ user.media.length }})</h3>
                                    <!-- Botón Subir: Protegido por users.edit -->
                                    <n-button v-if="hasPermission('users.edit')" @click="goToEdit" type="primary" ghost>Subir Documento</n-button>
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                                    <!-- Renderizamos el componente FileView. Deletable protegido por users.edit -->
                                    <FileView 
                                        v-for="file in user.media" 
                                        :key="file.id" 
                                        :file="file" 
                                        :deletable="hasPermission('users.edit')" 
                                        @delete-file="deleteFile($event)" 
                                    />
                                </div>
                            </div>

                            <!-- Caso: No hay Documentos (Empty State) -->
                            <div v-else class="flex flex-col items-center justify-center py-16 text-center">
                                <div class="bg-gray-50 p-6 rounded-full mb-4">
                                    <svg class="w-12 h-12 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                    </svg>
                                </div>
                                <h3 class="text-lg font-medium text-gray-900">Documentación del Empleado</h3>
                                <p class="text-gray-500 max-w-sm mt-2">Aquí podrás gestionar contratos, identificaciones y otros documentos legales.</p>
                                
                                <!-- Botón Funcional: Protegido por users.edit -->
                                <n-button 
                                    v-if="hasPermission('users.edit')"
                                    class="mt-6" 
                                    type="primary" 
                                    ghost 
                                    @click="goToEdit"
                                >
                                    <template #icon>
                                        <n-icon :component="CloudUploadOutline" />
                                    </template>
                                    Subir Documento
                                </n-button>
                            </div>
                        </n-tab-pane>

                        <!-- Tab 3: Nóminas -->
                        <n-tab-pane name="payroll" tab="Información de Nóminas">
                            <div class="flex flex-col items-center justify-center py-16 text-center">
                                <div class="bg-green-50 p-6 rounded-full mb-4">
                                    <svg class="w-12 h-12 text-green-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                </div>
                                <h3 class="text-lg font-medium text-gray-900">Historial de Pagos</h3>
                                <p class="text-gray-500 max-w-sm mt-2">Visualiza el historial de pagos, bonos y deducciones del usuario.</p>
                                <n-button class="mt-6" type="primary" ghost>Ver Detalles de Nómina</n-button>
                            </div>
                        </n-tab-pane>

                    </n-tabs>
                </div>

            </div>
        </div>
    </AppLayout>
</template>