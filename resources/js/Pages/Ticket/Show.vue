<script>
import { defineComponent, h, ref, computed } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import AppLayout from '@/Layouts/AppLayout.vue';
import { 
    NCard, NButton, NIcon, NTag, NGrid, NGi, NDivider, NSpace, NSelect, NAvatar, NSpin,
    NTimeline, NTimelineItem, NCollapse, NCollapseItem, NModal, NForm, NFormItem, NInput, 
    createDiscreteApi, NImage, NImageGroup, NTooltip
} from 'naive-ui';
import { 
    ArrowBackOutline, TicketOutline, PersonOutline, TimeOutline, AlertCircleOutline, 
    CreateOutline, SearchOutline, ChatboxEllipsesOutline, AttachOutline, CheckmarkCircleOutline,
    CloseCircleOutline, HardwareChipOutline, DocumentTextOutline, SendOutline
} from '@vicons/ionicons5';
import { format } from 'date-fns';
import { es } from 'date-fns/locale';

export default defineComponent({
    name: 'TicketShow',
    components: {
        AppLayout, Head, Link,
        NCard, NButton, NIcon, NTag, NGrid, NGi, NDivider, NSpace, NSelect, NAvatar, NSpin,
        NTimeline, NTimelineItem, NCollapse, NCollapseItem, NModal, NForm, NFormItem, NInput,
        NImage, NImageGroup, NTooltip,
        ArrowBackOutline, TicketOutline, PersonOutline, TimeOutline, AlertCircleOutline,
        CreateOutline, SearchOutline, ChatboxEllipsesOutline, AttachOutline, CheckmarkCircleOutline,
        CloseCircleOutline, HardwareChipOutline, DocumentTextOutline, SendOutline
    },
    props: {
        ticket: {
            type: Object,
            required: true
        },
        conversation_history: {
            type: Array,
            default: () => []
        }
    },
    setup(props) {
        // Lógica de Notificaciones
        const { notification } = createDiscreteApi(['notification']);

        // --- Lógica del Modal de Respuesta Rápida ---
        const showReplyModal = ref(false);
        const replyFormRef = ref(null);
        
        const replyForm = useForm({
            message: '',
            new_status: props.ticket.status // Opción para cambiar estado al responder
        });

        const replyRules = {
            message: { required: true, message: 'El mensaje no puede estar vacío', trigger: 'blur' }
        };

        const openReplyModal = () => {
            replyForm.message = '';
            replyForm.new_status = props.ticket.status;
            showReplyModal.value = true;
        };

        const submitReply = () => {
            replyFormRef.value?.validate((errors) => {
                if (!errors) {
                    replyForm.post(route('tickets.reply', props.ticket.id), {
                        preserveScroll: true,
                        onSuccess: () => {
                            showReplyModal.value = false;
                            notification.success({
                                title: 'Respuesta Enviada',
                                content: 'El comentario ha sido agregado al historial del ticket.',
                                duration: 3000
                            });
                        },
                        onError: () => {
                            notification.error({ title: 'Error', content: 'No se pudo enviar la respuesta.' });
                        }
                    });
                }
            });
        };

        return {
            showReplyModal,
            replyForm,
            replyRules,
            replyFormRef,
            openReplyModal,
            submitReply
        };
    },
    data() {
        return {
            searchQuery: null,
            searchOptions: [],
            loadingSearch: false,
        };
    },
    computed: {
        statusConfig() {
            switch (this.ticket.status) {
                case 'Abierto': return { type: 'info', text: 'Abierto', color: 'bg-blue-100 text-blue-600', icon: AlertCircleOutline };
                case 'En Progreso': return { type: 'warning', text: 'En Progreso', color: 'bg-amber-100 text-amber-600', icon: HardwareChipOutline };
                case 'Resuelto': return { type: 'success', text: 'Resuelto', color: 'bg-green-100 text-green-600', icon: CheckmarkCircleOutline };
                case 'Cerrado': return { type: 'default', text: 'Cerrado', color: 'bg-gray-100 text-gray-600', icon: CloseCircleOutline };
                default: return { type: 'default', text: this.ticket.status, color: 'bg-gray-100 text-gray-600', icon: TicketOutline };
            }
        },
        priorityConfig() {
            switch (this.ticket.priority) {
                case 'Alta': return { type: 'error', icon: AlertCircleOutline };
                case 'Media': return { type: 'warning', icon: TimeOutline };
                case 'Baja': return { type: 'info', icon: CheckmarkCircleOutline };
                default: return { type: 'default', icon: TicketOutline };
            }
        },
        formattedDate() {
            if (!this.ticket.created_at) return 'Fecha desconocida';
            return format(new Date(this.ticket.created_at), "d 'de' MMMM, yyyy", { locale: es });
        }
    },
    methods: {
        goBack() {
            router.visit(route('tickets.index'));
        },
        goToEdit() {
            router.visit(route('tickets.edit', this.ticket.id));
        },
        async handleSearch(query) {
            if (!query) {
                this.searchOptions = [];
                return;
            }
            this.loadingSearch = true;
            try {
                // Ajusta la ruta a tu buscador de tickets real
                const response = await axios.get(route('tickets.search'), { params: { query } });
                this.searchOptions = response.data;
            } catch (error) {
                console.error("Error buscando tickets:", error);
            } finally {
                this.loadingSearch = false;
            }
        },
        handleSelectTicket(id) {
            router.visit(route('tickets.show', id));
        },
        renderTicketOption(option) {
            if (!option) return null;
            return h('div', { class: 'flex items-center gap-3 p-1' }, [
                h(NIcon, { size: 24, class: 'text-gray-400' }, { default: () => h(TicketOutline) }),
                h('div', { class: 'flex flex-col text-left' }, [
                    h('span', { class: 'font-semibold text-gray-800 text-sm leading-tight' }, option.title),
                    h('span', { class: 'text-xs text-gray-400 font-mono mt-0.5' }, `#${option.id} - ${option.status}`)
                ])
            ]);
        },
        getTimelineType(type) {
            if (type === 'system') return 'info';
            if (type === 'reply') return 'success';
            if (type === 'status_change') return 'warning';
            return 'default';
        }
    }
});
</script>

<template>
    <AppLayout :title="`Ticket #${ticket.id}`">
        <div class="py-8 min-h-screen">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
                
                <!-- Header de Navegación -->
                <div class="mb-6 flex flex-col md:flex-row items-center justify-between gap-4">
                    <n-button text @click="goBack" class="hover:text-gray-900 text-gray-500 transition-colors self-start md:self-auto">
                        <template #icon>
                            <n-icon size="20"><ArrowBackOutline /></n-icon>
                        </template>
                        Volver a Tickets
                    </n-button>
                    
                    <div class="w-full md:w-96">
                        <n-select
                            v-model:value="searchQuery"
                            filterable
                            placeholder="Buscar ticket (ID, Asunto)..."
                            :options="searchOptions"
                            :loading="loadingSearch"
                            clearable
                            remote
                            size="large"
                            class="shadow-sm rounded-xl"
                            @search="handleSearch"
                            @update:value="handleSelectTicket"
                            :render-label="renderTicketOption"
                        >
                            <template #arrow>
                                <n-icon><SearchOutline /></n-icon>
                            </template>
                        </n-select>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    
                    <!-- Columna Izquierda: Estado y Meta-datos -->
                    <div class="lg:col-span-1 space-y-6">
                        
                        <!-- Tarjeta Principal de Estado -->
                        <div class="bg-white rounded-3xl p-6 shadow-sm border border-gray-100 relative overflow-hidden group">
                            <div class="absolute top-0 right-0 p-4 opacity-10 group-hover:opacity-20 transition-opacity">
                                <n-icon size="120" :class="statusConfig.color.split(' ')[1]">
                                    <component :is="statusConfig.icon" />
                                </n-icon>
                            </div>

                            <div class="relative z-10">
                                <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Estado Actual</h3>
                                <div class="flex items-center gap-3 mb-6">
                                    <div :class="`p-2 rounded-full ${statusConfig.color}`">
                                        <n-icon size="24"><component :is="statusConfig.icon" /></n-icon>
                                    </div>
                                    <span class="text-2xl font-black text-gray-800">{{ statusConfig.text }}</span>
                                </div>

                                <n-divider class="my-4" />

                                <div class="space-y-4">
                                    <div class="flex items-center justify-between">
                                        <span class="text-sm text-gray-500 flex items-center gap-2">
                                            <n-icon><AlertCircleOutline /></n-icon> Prioridad
                                        </span>
                                        <n-tag :type="priorityConfig.type" size="small" round strong>
                                            {{ ticket.priority }}
                                        </n-tag>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <span class="text-sm text-gray-500 flex items-center gap-2">
                                            <n-icon><TimeOutline /></n-icon> Creado
                                        </span>
                                        <span class="text-sm font-medium text-gray-800">{{ formattedDate }}</span>
                                    </div>
                                </div>

                                <div class="mt-6 grid grid-cols-2 gap-3">
                                    <n-button type="warning" secondary block @click="goToEdit">
                                        <template #icon><n-icon><CreateOutline /></n-icon></template>
                                        Editar
                                    </n-button>
                                    <n-button type="primary" block @click="openReplyModal">
                                        <template #icon><n-icon><ChatboxEllipsesOutline /></n-icon></template>
                                        Responder
                                    </n-button>
                                </div>
                            </div>
                        </div>

                        <!-- Tarjeta de Personas (Solicitante / Asignado) -->
                        <div class="bg-white rounded-3xl p-6 shadow-sm border border-gray-100">
                            <h3 class="font-bold text-lg text-gray-800 mb-4 flex items-center gap-2">
                                <n-icon class="text-indigo-500"><PersonOutline /></n-icon>
                                Involucrados
                            </h3>

                            <div class="space-y-6">
                                <!-- Solicitante -->
                                <div class="flex items-center gap-3">
                                    <n-avatar round size="medium" :src="ticket.user?.profile_photo_url" class="bg-indigo-50 text-indigo-500 border border-indigo-100">
                                        <n-icon><PersonOutline /></n-icon>
                                    </n-avatar>
                                    <div class="flex flex-col">
                                        <span class="text-xs text-gray-400 font-semibold uppercase">Solicitante</span>
                                        <span class="font-medium text-gray-800">{{ ticket.user?.name || 'Usuario Desconocido' }}</span>
                                        <span class="text-xs text-gray-500">{{ ticket.user?.email }}</span>
                                    </div>
                                </div>

                                <!-- Agente Asignado -->
                                <div class="bg-gray-50 rounded-2xl p-4 border border-gray-100 flex items-center gap-3">
                                    <n-avatar round size="small" :src="ticket.assigned_to?.profile_photo_url" class="bg-white text-gray-400 shadow-sm">
                                        <n-icon><HardwareChipOutline /></n-icon>
                                    </n-avatar>
                                    <div class="flex flex-col">
                                        <span class="text-xs text-gray-400 font-semibold uppercase">Agente Asignado</span>
                                        <span v-if="ticket.assigned_to" class="font-medium text-gray-800">{{ ticket.assigned_to.name }}</span>
                                        <span v-else class="text-sm text-gray-400 italic">Sin asignar</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>

                    <!-- Columna Derecha: Contenido Principal -->
                    <div class="lg:col-span-2 space-y-6">
                        
                        <!-- Información del Ticket -->
                        <div class="bg-white rounded-3xl p-6 md:p-8 shadow-sm border border-gray-100">
                            <div class="flex flex-col gap-2 mb-4">
                                <div class="flex items-center gap-2">
                                    <n-tag type="default" size="small" round :bordered="false" class="bg-gray-100 text-gray-500 font-mono">
                                        ID: #{{ ticket.id }}
                                    </n-tag>
                                    <n-tag type="info" size="small" round :bordered="false" class="bg-blue-50 text-blue-600">
                                        {{ ticket.category || 'General' }}
                                    </n-tag>
                                </div>
                                <h1 class="text-2xl md:text-3xl font-black text-gray-800 tracking-tight leading-tight">
                                    {{ ticket.title }}
                                </h1>
                            </div>

                            <n-divider />

                            <div class="prose prose-sm prose-indigo text-gray-600 max-w-none">
                                <h4 class="text-gray-800 font-semibold mb-2 flex items-center gap-2">
                                    <n-icon><DocumentTextOutline /></n-icon> Descripción
                                </h4>
                                <p class="whitespace-pre-line">{{ ticket.description || 'Sin descripción detallada.' }}</p>
                            </div>

                            <!-- Sección de Evidencias / Archivos -->
                            <div v-if="ticket.evidences && ticket.evidences.length > 0" class="mt-8">
                                <h4 class="text-gray-800 font-semibold mb-4 flex items-center gap-2">
                                    <n-icon class="text-gray-400"><AttachOutline /></n-icon> 
                                    Evidencias Adjuntas
                                </h4>
                                <n-image-group>
                                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                                        <div 
                                            v-for="(ev, index) in ticket.evidences" 
                                            :key="index"
                                            class="relative group aspect-square bg-gray-50 rounded-xl overflow-hidden border border-gray-100"
                                        >
                                            <n-image
                                                width="100%"
                                                height="100%"
                                                class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-110"
                                                :src="ev.url" 
                                                object-fit="cover"
                                                :alt="ev.name"
                                            />
                                            <div class="absolute bottom-0 left-0 right-0 bg-black/50 backdrop-blur-sm p-1 text-center opacity-0 group-hover:opacity-100 transition-opacity">
                                                <span class="text-xs text-white truncate block">{{ ev.name }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </n-image-group>
                            </div>
                        </div>

                        <!-- Historial / Timeline -->
                        <div class="bg-white rounded-3xl p-6 shadow-sm border border-gray-100">
                            <h3 class="font-bold text-xl text-gray-800 mb-6 flex items-center gap-2">
                                <n-icon class="text-orange-500"><TimeOutline /></n-icon>
                                Actividad del Ticket
                            </h3>

                            <div v-if="conversation_history.length === 0" class="text-center py-8 text-gray-400">
                                <n-icon size="40" class="mb-2 opacity-50"><ChatboxEllipsesOutline /></n-icon>
                                <p>No hay actividad registrada aún.</p>
                            </div>

                            <div v-else class="px-2">
                                <n-timeline>
                                    <n-timeline-item
                                        v-for="item in conversation_history"
                                        :key="item.id"
                                        :type="getTimelineType(item.type)"
                                        :title="item.user_name"
                                        :time="item.created_at_human"
                                    >
                                        <template #icon v-if="item.user_avatar">
                                            <n-avatar round size="small" :src="item.user_avatar" />
                                        </template>
                                        
                                        <div class="bg-gray-50 rounded-xl p-3 mt-2 border border-gray-100 text-sm text-gray-700">
                                            <div v-if="item.type === 'status_change'" class="font-medium text-amber-600 mb-1">
                                                Cambió el estado a: {{ item.new_value }}
                                            </div>
                                            <div class="whitespace-pre-line">{{ item.message }}</div>
                                        </div>
                                    </n-timeline-item>
                                </n-timeline>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- Modal de Respuesta Rápida -->
                <n-modal v-model:show="showReplyModal">
                    <n-card
                        style="width: 600px"
                        title="Responder al Ticket"
                        :bordered="false"
                        size="huge"
                        role="dialog"
                        aria-modal="true"
                    >
                        <template #header-extra>
                            <n-icon size="24" class="text-indigo-500"><SendOutline /></n-icon>
                        </template>
                        
                        <n-form
                            ref="replyFormRef"
                            :model="replyForm"
                            :rules="replyRules"
                        >
                            <n-grid cols="2" x-gap="12">
                                <n-gi span="2">
                                    <n-form-item label="Mensaje / Respuesta" path="message">
                                        <n-input 
                                            v-model:value="replyForm.message" 
                                            type="textarea" 
                                            placeholder="Escribe tu respuesta para el cliente o nota interna..."
                                            :rows="4"
                                            class="text-base"
                                        />
                                    </n-form-item>
                                </n-gi>
                                <n-gi>
                                    <n-form-item label="Actualizar Estado (Opcional)" path="new_status">
                                        <n-select 
                                            v-model:value="replyForm.new_status"
                                            :options="[
                                                { label: 'Mantener actual', value: ticket.status },
                                                { label: 'En Progreso', value: 'En Progreso' },
                                                { label: 'Resuelto', value: 'Resuelto' },
                                                { label: 'Cerrado', value: 'Cerrado' }
                                            ]"
                                        />
                                    </n-form-item>
                                </n-gi>
                            </n-grid>
                        </n-form>

                        <template #footer>
                            <div class="flex justify-end gap-3">
                                <n-button @click="showReplyModal = false" :disabled="replyForm.processing">
                                    Cancelar
                                </n-button>
                                <n-button 
                                    type="primary" 
                                    @click="submitReply" 
                                    :loading="replyForm.processing"
                                >
                                    Enviar Respuesta
                                </n-button>
                            </div>
                        </template>
                    </n-card>
                </n-modal>

            </div>
        </div>
    </AppLayout>
</template>