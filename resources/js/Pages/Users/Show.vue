<script>
import AppLayout from '@/Layouts/AppLayout.vue';
import FileView from "@/Components/MyComponents/FileView.vue"; 
import { Head, Link, router } from '@inertiajs/vue3';
import { usePermissions } from '@/Composables/usePermissions'; 
import { 
    NCard, NAvatar, NTag, NDescriptions, NDescriptionsItem, NButton, NIcon, 
    NDivider, NTabs, NTabPane, NList, NListItem, NThing, NEmpty, NSpin, NGrid, NGridItem,
    createDiscreteApi 
} from 'naive-ui';
import { 
    ArrowBackOutline, CreateOutline, MailOutline, BusinessOutline, 
    CalendarOutline, PowerOutline, CheckmarkCircleOutline, TimeOutline, AlertCircleOutline,
    CloudUploadOutline, CallOutline, HomeOutline, WalletOutline, 
    PeopleOutline, IdCardOutline, LocationOutline, MedkitOutline, TrashOutline
} from '@vicons/ionicons5';

export default {
    components: {
        AppLayout, Head, Link, NCard, NAvatar, NTag, NDescriptions, NDescriptionsItem,
        NButton, NIcon, NDivider, NTabs, NTabPane, NList, NListItem, NThing, NEmpty, NSpin, 
        NGrid, NGridItem, FileView,
        // Iconos
        ArrowBackOutline, CreateOutline, MailOutline, BusinessOutline, CalendarOutline,
        PowerOutline, CheckmarkCircleOutline, TimeOutline, AlertCircleOutline,
        CloudUploadOutline, CallOutline, HomeOutline, WalletOutline, 
        PeopleOutline, IdCardOutline, LocationOutline, MedkitOutline, TrashOutline
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
    setup() {
        // Agregamos 'dialog' para la confirmación de eliminación
        const { notification, dialog } = createDiscreteApi(['notification', 'dialog']);
        const { hasPermission } = usePermissions(); 
        
        return { 
            notification,
            dialog,
            hasPermission, 
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
        },
        userInitials() {
            if (!this.user.first_name) return this.user.name.substring(0, 2).toUpperCase();
            return (this.user.first_name[0] + (this.user.paternal_surname[0] || '')).toUpperCase();
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
        deleteUser() {
            this.dialog.warning({
                title: 'Eliminar Usuario',
                content: `¿Estás seguro de que deseas eliminar permanentemente a ${this.user.name}? Esta acción no se puede deshacer y se perderá todo el expediente.`,
                positiveText: 'Sí, eliminar',
                negativeText: 'Cancelar',
                onPositiveClick: () => {
                    router.delete(route('users.destroy', this.user.id));
                }
            });
        },
        deleteFile(fileId) {
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
            return date.toLocaleDateString('es-MX', { day: '2-digit', month: 'short', year: 'numeric' });
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
                    Expediente Digital
                </h2>
            </div>
        </template>

        <div class="py-8 min-h-screen bg-gray-50/50">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
                
                <!-- 1. HEADER DEL PERFIL -->
                <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 md:p-8 relative overflow-hidden">
                    <!-- Fondo decorativo sutil -->
                    <div class="absolute top-0 right-0 w-64 h-64 bg-blue-50 rounded-full mix-blend-multiply filter blur-3xl opacity-30 -translate-y-1/2 translate-x-1/2"></div>
                    
                    <div class="flex flex-col md:flex-row items-center md:items-start gap-8 relative z-10">
                        <!-- Avatar -->
                        <div class="flex flex-col items-center gap-3">
                            <n-avatar 
                                round 
                                :size="120" 
                                :src="user.profile_photo_url" 
                                class="border-4 border-white shadow-lg bg-gray-200 text-4xl font-bold text-gray-400"
                            >
                                <template #default v-if="!user.profile_photo_url">
                                    {{ userInitials }}
                                </template>
                            </n-avatar>
                            <n-tag 
                                :type="user.is_active ? 'success' : 'error'" 
                                round 
                                :bordered="false"
                                size="small"
                                class="px-4"
                            >
                                <template #icon>
                                    <n-icon :component="user.is_active ? CheckmarkCircleOutline : AlertCircleOutline" />
                                </template>
                                {{ user.is_active ? 'Activo' : 'Baja' }}
                            </n-tag>
                        </div>

                        <!-- Info Principal -->
                        <div class="flex-grow w-full text-center md:text-left">
                            <div class="flex flex-col md:flex-row justify-between items-center gap-4 mb-4">
                                <div>
                                    <h1 class="text-3xl font-bold text-gray-800">{{ user.name }}</h1>
                                    <div class="flex flex-wrap justify-center md:justify-start gap-x-6 gap-y-2 mt-2 text-gray-500 text-sm">
                                        <span class="flex items-center gap-1.5">
                                            <n-icon class="text-blue-500"><BusinessOutline /></n-icon>
                                            {{ user.branch ? user.branch.name : 'Sin sucursal' }}
                                        </span>
                                        <span class="flex items-center gap-1.5">
                                            <n-icon class="text-purple-500"><IdCardOutline /></n-icon>
                                            {{ user.roles && user.roles.length ? user.roles[0].name : 'Sin Rol' }}
                                        </span>
                                        <span class="flex items-center gap-1.5">
                                            <n-icon class="text-green-500"><CalendarOutline /></n-icon>
                                            Alta: {{ formattedDate }}
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="flex gap-2">
                                    <n-button 
                                        v-if="hasPermission('users.edit')" 
                                        type="primary" 
                                        secondary 
                                        circle 
                                        @click="goToEdit"
                                        title="Editar Expediente"
                                    >
                                        <template #icon><n-icon><CreateOutline /></n-icon></template>
                                    </n-button>
                                    <n-button 
                                        v-if="hasPermission('users.toggle_status')"
                                        :type="user.is_active ? 'warning' : 'success'" 
                                        secondary 
                                        circle 
                                        @click="toggleStatus"
                                        :title="user.is_active ? 'Desactivar Usuario' : 'Activar Usuario'"
                                    >
                                        <template #icon><n-icon><PowerOutline /></n-icon></template>
                                    </n-button>
                                    
                                    <!-- Botón Eliminar Agregado -->
                                    <n-button 
                                        v-if="hasPermission('users.destroy')"
                                        type="error" 
                                        secondary 
                                        circle 
                                        @click="deleteUser"
                                        title="Eliminar Usuario Permanentemente"
                                    >
                                        <template #icon><n-icon><TrashOutline /></n-icon></template>
                                    </n-button>
                                </div>
                            </div>
                            
                            <!-- Datos de Contacto Rápido -->
                            <div class="bg-gray-50 rounded-xl p-4 flex flex-wrap justify-center md:justify-start gap-8 border border-gray-100">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-full bg-white flex items-center justify-center text-blue-500 shadow-sm">
                                        <n-icon size="20"><MailOutline /></n-icon>
                                    </div>
                                    <div class="text-left">
                                        <p class="text-xs text-gray-400 uppercase font-semibold">Correo</p>
                                        <p class="text-gray-700 font-medium text-sm">{{ user.email }}</p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-full bg-white flex items-center justify-center text-green-500 shadow-sm">
                                        <n-icon size="20"><CallOutline /></n-icon>
                                    </div>
                                    <div class="text-left">
                                        <p class="text-xs text-gray-400 uppercase font-semibold">Teléfono</p>
                                        <p class="text-gray-700 font-medium text-sm">{{ user.phone || 'No registrado' }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 2. CONTENIDO DETALLADO (TABS) -->
                <div class="bg-white rounded-3xl shadow-sm border border-gray-100 min-h-[500px]">
                    <n-tabs type="segment" size="large" animated pane-class="p-6 md:p-8">
                        
                        <!-- TAB: DATOS PERSONALES -->
                        <n-tab-pane name="profile" tab="Información General">
                            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 p-2">
                                
                                <!-- Columna Izquierda: Identidad y Domicilio -->
                                <div class="space-y-8">
                                    <section>
                                        <h3 class="text-lg font-bold text-gray-800 flex items-center gap-2 mb-4">
                                            <n-icon class="text-blue-600"><IdCardOutline /></n-icon>
                                            Datos de Identificación
                                        </h3>
                                        <n-list bordered>
                                            <n-list-item>
                                                <div class="grid grid-cols-2 gap-4">
                                                    <div>
                                                        <span class="text-xs text-gray-400 uppercase">CURP</span>
                                                        <p class="font-medium text-gray-700">{{ user.curp || 'N/A' }}</p>
                                                    </div>
                                                    <div>
                                                        <span class="text-xs text-gray-400 uppercase">RFC</span>
                                                        <p class="font-medium text-gray-700">{{ user.rfc || 'N/A' }}</p>
                                                    </div>
                                                </div>
                                            </n-list-item>
                                            <n-list-item>
                                                <div class="grid grid-cols-2 gap-4">
                                                    <div>
                                                        <span class="text-xs text-gray-400 uppercase">NSS</span>
                                                        <p class="font-medium text-gray-700">{{ user.nss || 'N/A' }}</p>
                                                    </div>
                                                    <div>
                                                        <span class="text-xs text-gray-400 uppercase">Fecha Nacimiento</span>
                                                        <p class="font-medium text-gray-700">{{ formatDateShort(user.birth_date) || 'N/A' }}</p>
                                                    </div>
                                                </div>
                                            </n-list-item>
                                        </n-list>
                                    </section>

                                    <section>
                                        <h3 class="text-lg font-bold text-gray-800 flex items-center gap-2 mb-4">
                                            <n-icon class="text-orange-600"><HomeOutline /></n-icon>
                                            Domicilio
                                        </h3>
                                        <div class="bg-gray-50 rounded-xl p-5 border border-gray-200">
                                            <p class="text-gray-800 font-medium">
                                                {{ user.street }} {{ user.exterior_number }} <span v-if="user.interior_number">Int. {{ user.interior_number }}</span>
                                            </p>
                                            <p class="text-gray-600 text-sm mt-1">
                                                Col. {{ user.neighborhood }}, CP: {{ user.zip_code }}
                                            </p>
                                            <p class="text-gray-600 text-sm">
                                                {{ user.municipality }}, {{ user.state }}
                                            </p>
                                            
                                            <n-divider v-if="user.address_references" class="my-3" />
                                            
                                            <div v-if="user.address_references">
                                                <p class="text-xs text-gray-400 uppercase mb-1">Referencias</p>
                                                <p class="text-sm text-gray-600 italic">"{{ user.address_references }}"</p>
                                            </div>
                                            <div v-if="user.cross_streets" class="mt-2">
                                                <p class="text-xs text-gray-400 uppercase mb-1">Entre Calles</p>
                                                <p class="text-sm text-gray-600">{{ user.cross_streets }}</p>
                                            </div>
                                        </div>
                                    </section>
                                </div>

                                <!-- Columna Derecha: Emergencia -->
                                <div>
                                    <section>
                                        <h3 class="text-lg font-bold text-gray-800 flex items-center gap-2 mb-4">
                                            <n-icon class="text-red-600"><MedkitOutline /></n-icon>
                                            Contactos de Emergencia
                                        </h3>
                                        
                                        <div v-if="user.contacts && user.contacts.length > 0" class="space-y-3">
                                            <div v-for="(contact, index) in user.contacts" :key="index" class="flex items-center gap-4 p-4 rounded-xl border border-gray-200 hover:bg-gray-50 transition-colors">
                                                <div class="w-10 h-10 rounded-full bg-red-50 flex items-center justify-center text-red-500 font-bold text-lg">
                                                    {{ index + 1 }}
                                                </div>
                                                <div>
                                                    <p class="font-bold text-gray-800">{{ contact.name }}</p>
                                                    <div class="flex items-center gap-3 text-sm text-gray-600">
                                                        <span class="bg-gray-100 px-2 py-0.5 rounded text-xs">{{ contact.job_title }}</span> <!-- Usamos job_title para parentesco -->
                                                        <span class="flex items-center gap-1"><n-icon><CallOutline /></n-icon> {{ contact.phone }}</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div v-else class="text-center py-8 bg-gray-50 rounded-xl border border-dashed border-gray-300">
                                            <p class="text-gray-400 text-sm">No hay contactos de emergencia registrados.</p>
                                        </div>
                                    </section>
                                </div>
                            </div>
                        </n-tab-pane>

                        <!-- TAB: FINANCIERO -->
                        <n-tab-pane name="financial" tab="Financiero y Beneficiarios">
                            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 p-2">
                                
                                <!-- Datos Bancarios -->
                                <section>
                                    <h3 class="text-lg font-bold text-gray-800 flex items-center gap-2 mb-4">
                                        <n-icon class="text-green-600"><WalletOutline /></n-icon>
                                        Datos Bancarios
                                    </h3>
                                    <div class="bg-gradient-to-br from-gray-800 to-gray-900 rounded-2xl p-6 text-white shadow-xl relative overflow-hidden">
                                        <div class="absolute top-0 right-0 w-40 h-40 bg-white opacity-5 rounded-full -translate-y-1/2 translate-x-1/2"></div>
                                        
                                        <p class="text-gray-400 text-sm uppercase tracking-widest mb-1">{{ user.bank_name || 'BANCO NO REGISTRADO' }}</p>
                                        <div class="flex items-center justify-between mt-6 mb-8">
                                            <div class="text-2xl font-mono tracking-widest">
                                                {{ user.bank_account_number ? `**** **** **** ${user.bank_account_number.slice(-4)}` : '**** **** **** ****' }}
                                            </div>
                                        </div>
                                        <div class="flex justify-between items-end">
                                            <div>
                                                <p class="text-xs text-gray-400 uppercase">Titular</p>
                                                <p class="font-medium tracking-wide">{{ user.bank_account_holder || user.name }}</p>
                                            </div>
                                            <div class="text-right">
                                                <p class="text-xs text-gray-400 uppercase">CLABE</p>
                                                <p class="font-mono text-sm">{{ user.bank_clabe || 'N/A' }}</p>
                                            </div>
                                        </div>
                                    </div>
                                </section>

                                <!-- Beneficiarios -->
                                <section>
                                    <h3 class="text-lg font-bold text-gray-800 flex items-center gap-2 mb-4">
                                        <n-icon class="text-pink-600"><PeopleOutline /></n-icon>
                                        Beneficiarios Registrados
                                    </h3>
                                    
                                    <n-list bordered class="rounded-xl overflow-hidden">
                                        <template v-if="user.beneficiaries && user.beneficiaries.length > 0">
                                            <n-list-item v-for="ben in user.beneficiaries" :key="ben.id">
                                                <div class="flex justify-between items-center">
                                                    <div>
                                                        <p class="font-bold text-gray-800">{{ ben.first_name }} {{ ben.paternal_surname }} {{ ben.maternal_surname }}</p>
                                                        <p class="text-xs text-gray-500">
                                                            Nacimiento: {{ formatDateShort(ben.birth_date) }}
                                                        </p>
                                                    </div>
                                                    <n-tag type="info" size="small" round>{{ ben.relationship }}</n-tag>
                                                </div>
                                            </n-list-item>
                                        </template>
                                        <template v-else>
                                            <div class="p-8 text-center text-gray-400">
                                                No hay beneficiarios asignados.
                                            </div>
                                        </template>
                                    </n-list>
                                </section>
                            </div>
                        </n-tab-pane>

                        <!-- TAB: TAREAS -->
                        <n-tab-pane name="tasks" tab="Tareas">
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
                                    <n-button text type="primary">Ver historial completo de tareas</n-button>
                                </div>
                            </div>
                            <div v-else class="flex flex-col items-center justify-center py-10">
                                <n-empty description="Este usuario no tiene tareas recientes." />
                            </div>
                        </n-tab-pane>

                        <!-- TAB: DOCUMENTACIÓN -->
                        <n-tab-pane name="docs" tab="Documentos">
                             <!-- Caso: Hay Documentos -->
                            <div v-if="user.media && user.media.length > 0" class="p-2">
                                <div class="flex justify-between items-center mb-6">
                                    <h3 class="text-lg font-bold text-gray-700">Archivos Adjuntos ({{ user.media.length }})</h3>
                                    <n-button v-if="hasPermission('users.edit')" @click="goToEdit" size="small" type="primary" ghost>
                                        <template #icon><n-icon><CloudUploadOutline /></n-icon></template>
                                        Subir Nuevo
                                    </n-button>
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                                    <FileView 
                                        v-for="file in user.media" 
                                        :key="file.id" 
                                        :file="file" 
                                        :deletable="hasPermission('users.edit')" 
                                        @delete-file="deleteFile($event)" 
                                    />
                                </div>
                            </div>

                            <!-- Caso: No hay Documentos -->
                            <div v-else class="flex flex-col items-center justify-center py-16 text-center bg-gray-50 rounded-2xl border border-dashed border-gray-200">
                                <div class="bg-white p-4 rounded-full mb-4 shadow-sm">
                                    <n-icon size="40" class="text-gray-300"><CloudUploadOutline /></n-icon>
                                </div>
                                <h3 class="text-lg font-medium text-gray-900">Sin Documentación</h3>
                                <p class="text-gray-500 max-w-sm mt-2 mb-6 text-sm">El expediente digital no contiene archivos adjuntos actualmente.</p>
                                
                                <n-button v-if="hasPermission('users.edit')" type="primary" @click="goToEdit">
                                    Subir Documentos
                                </n-button>
                            </div>
                        </n-tab-pane>

                    </n-tabs>
                </div>

            </div>
        </div>
    </AppLayout>
</template>