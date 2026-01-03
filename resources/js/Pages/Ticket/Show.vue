<script>
import { defineComponent, h, ref, computed } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import AppLayout from '@/Layouts/AppLayout.vue';
import { 
    NCard, NButton, NIcon, NTag, NGrid, NGi, NDivider, NSpace, NSelect, NAvatar, NSpin,
    NTimeline, NTimelineItem, NCollapse, NCollapseItem, NModal, NForm, NFormItem, NInput, 
    createDiscreteApi, NImage, NImageGroup, NTooltip, NUpload
} from 'naive-ui';
import { 
    ArrowBackOutline, TicketOutline, PersonOutline, TimeOutline, AlertCircleOutline, 
    CreateOutline, SearchOutline, ChatboxEllipsesOutline, AttachOutline, CheckmarkCircleOutline,
    CloseCircleOutline, HardwareChipOutline, DocumentTextOutline, SendOutline
} from '@vicons/ionicons5';
import { format, parse } from 'date-fns'; // Importamos 'parse' para corregir la lectura de fechas
import { es } from 'date-fns/locale';

export default defineComponent({
    name: 'TicketShow',
    components: {
        AppLayout, Head, Link,
        NCard, NButton, NIcon, NTag, NGrid, NGi, NDivider, NSpace, NSelect, NAvatar, NSpin,
        NTimeline, NTimelineItem, NCollapse, NCollapseItem, NModal, NForm, NFormItem, NInput,
        NImage, NImageGroup, NTooltip, NUpload,
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
            body: '', 
            new_status: props.ticket.status, 
            attachments: [] 
        });

        const replyRules = {
            body: { required: true, message: 'La respuesta no puede estar vacía', trigger: 'blur' }
        };

        const openReplyModal = () => {
            replyForm.body = '';
            replyForm.new_status = props.ticket.status;
            replyForm.attachments = [];
            showReplyModal.value = true;
        };

        const submitReply = () => {
            replyFormRef.value?.validate((errors) => {
                if (!errors) {
                    replyForm.post(route('tickets.reply', props.ticket.id), {
                        preserveScroll: true,
                        forceFormData: true, 
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
                case 'En Análisis': 
                case 'En Progreso': return { type: 'warning', text: 'En Análisis', color: 'bg-amber-100 text-amber-600', icon: HardwareChipOutline };
                case 'Resuelto': return { type: 'success', text: 'Resuelto', color: 'bg-green-100 text-green-600', icon: CheckmarkCircleOutline };
                case 'Cerrado': return { type: 'default', text: 'Cerrado', color: 'bg-gray-100 text-gray-600', icon: CloseCircleOutline };
                default: return { type: 'default', text: this.ticket.status, color: 'bg-gray-100 text-gray-600', icon: TicketOutline };
            }
        },
        priorityConfig() {
            switch (this.ticket.priority) {
                case 'Alta': 
                case 'Urgente': return { type: 'error', icon: AlertCircleOutline };
                case 'Media': return { type: 'warning', icon: TimeOutline };
                case 'Baja': return { type: 'info', icon: CheckmarkCircleOutline };
                default: return { type: 'default', icon: TicketOutline };
            }
        },
        formattedDate() {
            return this.formatDateLong(this.ticket.created_at);
        }
    },
    methods: {
        goBack() {
            router.visit(route('tickets.index'));
        },
        goToEdit() {
            router.visit(route('tickets.edit', this.ticket.id));
        },
        // FUNCIÓN MEJORADA: Parsea correctamente las fechas ambiguas
        formatDateLong(dateStr) {
            if (!dateStr) return 'Fecha desconocida';
            
            let dateObj = new Date(dateStr);

            // CORRECCIÓN: Si es string con barras "02/01/2026", forzamos lectura como Día/Mes/Año
            if (typeof dateStr === 'string' && dateStr.includes('/')) {
                // Intentamos parsear con formato d/M/yyyy HH:mm (formato común de Laravel)
                const parsed = parse(dateStr, 'd/M/yyyy HH:mm', new Date());
                
                // Si el parseo es válido, usamos esa fecha corregida
                if (!isNaN(parsed.getTime())) {
                    dateObj = parsed;
                }
            }
            
            // Si después de todo la fecha no es válida, devolvemos el string original
            if (isNaN(dateObj.getTime())) return dateStr; 

            return format(dateObj, "d 'de' MMMM yyyy, h:mm a", { locale: es });
        },
        async handleSearch(query) {
            if (!query) {
                this.searchOptions = [];
                return;
            }
            this.loadingSearch = true;
            try {
                const response = await axios.get(route('tickets.index'), { 
                    params: { search: query },
                    headers: { 'X-Inertia': 'true', 'X-Inertia-Partial-Data': 'tickets' } 
                });
                this.searchOptions = []; 
            } catch (error) {
                console.error("Error buscando tickets:", error);
            } finally {
                this.loadingSearch = false;
            }
        },
        handleSelectTicket(id) {
            // router.visit(route('tickets.show', id));
        },
        renderTicketOption(option) {
            return null; 
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
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    
                    <!-- Columna Izquierda: Estado y Meta-datos -->
                    <div class="lg:col-span-1 space-y-6">
                        
                        <!-- Tarjeta Principal de Estado -->
                        <div class="bg-white rounded-3xl p-6 shadow-sm border border-gray-100 relative overflow-hidden group">
                            <div class="absolute top-0 right-0 p-4 opacity-10 group-hover:opacity-20 transition-opacity">
                                <n-icon size="90" :class="statusConfig.color.split(' ')[1]">
                                    <component :is="statusConfig.icon" />
                                </n-icon>
                            </div>

                            <div class="relative z-10">
                                <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Estado actual</h3>
                                <div class="flex items-center gap-3 mb-6">
                                    <div :class="`size-8 rounded-full flex items-center justify-center ${statusConfig.color}`">
                                        <n-icon size="20"><component :is="statusConfig.icon" /></n-icon>
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
                                        <span class="text-sm font-medium text-gray-800 capitalize">{{ formattedDate }}</span>
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

                        <!-- Tarjeta de Cliente -->
                        <div v-if="ticket.client" class="bg-white rounded-3xl p-6 shadow-sm border border-gray-100">
                            <h3 class="font-bold text-lg text-gray-800 mb-4 flex items-center gap-2">
                                <n-icon class="text-indigo-500"><PersonOutline /></n-icon>
                                Cliente
                            </h3>

                            <div class="flex items-center gap-3">
                                <n-avatar round size="medium" class="bg-indigo-50 text-indigo-500 border border-indigo-100">
                                    <n-icon><PersonOutline /></n-icon>
                                </n-avatar>
                                <div class="flex flex-col">
                                    <span class="font-medium text-gray-800">{{ ticket.client.name }}</span>
                                    <span class="text-xs text-gray-500">{{ ticket.client.phone || 'Sin teléfono' }}</span>
                                </div>
                            </div>
                            
                            <div v-if="ticket.service_order" class="mt-4 pt-4 border-t border-gray-100">
                                <span class="text-xs text-gray-400 font-semibold uppercase block mb-1">Orden Relacionada</span>
                                <Link :href="route('service-orders.show', ticket.service_order.id)" class="text-sm text-indigo-600 hover:underline">
                                    Orden #{{ ticket.service_order.id }}
                                </Link>
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
                            <div v-if="ticket.media && ticket.media.length > 0" class="mt-8">
                                <h4 class="text-gray-800 font-semibold mb-4 flex items-center gap-2">
                                    <n-icon class="text-gray-400"><AttachOutline /></n-icon> 
                                    Evidencias Adjuntas
                                </h4>
                                <n-image-group>
                                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                                        <div 
                                            v-for="(ev, index) in ticket.media" 
                                            :key="index"
                                            class="relative group aspect-square bg-gray-50 rounded-xl overflow-hidden border border-gray-100"
                                        >
                                            <!-- Si es imagen -->
                                            <n-image
                                                v-if="ev.mime_type.startsWith('image/')"
                                                width="100%"
                                                height="100%"
                                                class="w-full h-full object-cover"
                                                :src="ev.url" 
                                                object-fit="cover"
                                                :alt="ev.name"
                                            />
                                            <!-- Si es otro archivo -->
                                            <div v-else class="w-full h-full flex flex-col items-center justify-center text-gray-400 p-2">
                                                <n-icon size="30"><DocumentTextOutline /></n-icon>
                                                <span class="text-xs truncate w-full text-center mt-1">{{ ev.name }}</span>
                                                <a :href="ev.url" target="_blank" class="absolute inset-0 z-10"></a>
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
                                Historial de conversación
                            </h3>

                            <div v-if="conversation_history.length === 0" class="text-center py-8 text-gray-400">
                                <n-icon size="40" class="mb-2 opacity-50"><ChatboxEllipsesOutline /></n-icon>
                                <p>No hay respuestas registradas aún.</p>
                            </div>

                            <div v-else class="px-2">
                                <n-timeline icon-size="30">
                                    <n-timeline-item
                                        v-for="item in conversation_history"
                                        :key="item.id"
                                        type="info"
                                        :title="item.user?.name || 'Sistema'"
                                        :time="formatDateLong(item.created_at)"
                                    >
                                        <template #icon v-if="item.user?.profile_photo_url">
                                            <!-- CAMBIO: Avatar a 48px para que se vea más grande -->
                                            <n-avatar round :size="28" :src="item.user.profile_photo_url" />
                                        </template>
                                        
                                        <div class="bg-gray-50 rounded-xl p-3 mt-2 border border-gray-100 text-sm text-gray-700">
                                            <div class="whitespace-pre-line">{{ item.body }}</div>
                                            
                                            <!-- Adjuntos en comentarios -->
                                            <div v-if="item.attachments && item.attachments.length > 0" class="mt-2 flex flex-wrap gap-2">
                                                 <a v-for="att in item.attachments" :key="att.id" :href="att.url" target="_blank" class="text-xs text-blue-500 underline flex items-center gap-1">
                                                    <n-icon><AttachOutline /></n-icon> {{ att.name }}
                                                 </a>
                                            </div>
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
                            <n-grid cols="1" y-gap="12">
                                <n-gi>
                                    <n-form-item label="Mensaje / Respuesta" path="body">
                                        <n-input 
                                            v-model:value="replyForm.body" 
                                            type="textarea" 
                                            placeholder="Escribe tu respuesta..."
                                            :rows="4"
                                            class="text-base"
                                        />
                                    </n-form-item>
                                </n-gi>
                                <n-gi>
                                    <n-form-item label="Cambiar Estado (Opcional)" path="new_status">
                                        <n-select 
                                            v-model:value="replyForm.new_status"
                                            :options="[
                                                { label: 'Mantener actual', value: ticket.status },
                                                { label: 'Abierto', value: 'Abierto' },
                                                { label: 'En Análisis', value: 'En Análisis' },
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