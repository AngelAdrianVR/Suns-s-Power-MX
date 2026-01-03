<script>
import { Link, router } from '@inertiajs/vue3';
import { usePermissions } from '@/Composables/usePermissions'; // 1. Importar composable
import AppLayout from '@/Layouts/AppLayout.vue';
import { 
    NCard, NIcon, NSteps, NStep, NTag, NButton, NDivider, NDataTable, 
    NResult, createDiscreteApi, NAlert, NDescriptions, NDescriptionsItem, NAvatar,
    NModal, NImage
} from 'naive-ui';
import { 
    ArrowBackOutline, PrintOutline, CubeOutline, CheckmarkCircleOutline, 
    CloseCircleOutline, DocumentTextOutline, TimeOutline, AirplaneOutline,
    StorefrontOutline, ImageOutline
} from '@vicons/ionicons5';
import { h } from 'vue';

export default {
    components: {
        AppLayout, Link, NCard, NIcon, NSteps, NStep, NTag, NButton, 
        NDivider, NDataTable, NResult, NAlert, NDescriptions, NDescriptionsItem, NAvatar,
        NModal, NImage,
        // Iconos
        ArrowBackOutline, PrintOutline, CubeOutline, CheckmarkCircleOutline,
        CloseCircleOutline, DocumentTextOutline, TimeOutline, AirplaneOutline, StorefrontOutline, ImageOutline
    },
    props: {
        order: {
            type: Object,
            required: true
        }
    },
    // 2. Usar setup para exponer hasPermission al template
    setup() {
        const { hasPermission } = usePermissions();
        return { hasPermission };
    },
    data() {
        return {
            loadingAction: false,
            showImageModal: false,
            previewImageUrl: null,
            columns: [
                {
                    title: 'Img',
                    key: 'image',
                    width: 60,
                    render: (row) => {
                        if (row.product.image_url) {
                            return h(NAvatar, {
                                size: 'small',
                                src: row.product.image_url,
                                style: 'cursor: pointer',
                                onClick: () => this.openImage(row.product.image_url)
                            });
                        }
                        return h(NIcon, { size: 20, component: CubeOutline, color: '#ccc' });
                    }
                },
                {
                    title: 'Producto',
                    key: 'product_name',
                    render: (row) => {
                        return h('div', { class: 'flex flex-col' }, [
                            h('span', { class: 'font-medium' }, row.product.name),
                            h('span', { class: 'text-xs text-gray-400' }, row.product.sku)
                        ]);
                    }
                },
                {
                    title: 'Cant.',
                    key: 'quantity',
                    width: 80
                },
                {
                    title: 'Costo',
                    key: 'unit_cost',
                    render: (row) => this.formatCurrency(row.unit_cost),
                    align: 'right'
                },
                {
                    title: 'Total',
                    key: 'subtotal',
                    render: (row) => this.formatCurrency(row.subtotal), // Usamos el subtotal calculado en backend
                    align: 'right',
                    className: 'font-bold'
                }
            ]
        }
    },
    computed: {
        currentStep() {
            switch (this.order.status) {
                case 'Borrador': return 1;
                case 'Solicitada': return 2;
                case 'Recibida': return 3;
                case 'Cancelada': return 0; // Estado especial
                default: return 1;
            }
        },
        stepStatus() {
            if (this.order.status === 'Cancelada') return 'error';
            if (this.order.status === 'Recibida') return 'finish';
            return 'process';
        },
        isCancelled() {
            return this.order.status === 'Cancelada';
        },
        itemsWithKey() {
            // Naive UI requiere keys unicas
            return this.order.items.map(item => ({...item, key: item.id}));
        }
    },
    methods: {
        formatCurrency(amount) {
            return new Intl.NumberFormat('es-MX', { 
                style: 'currency', 
                currency: this.order.currency || 'MXN' 
            }).format(amount);
        },
        formatDate(dateStr) {
            if (!dateStr) return 'N/A';
            return new Date(dateStr).toLocaleDateString('es-MX', {
                year: 'numeric', month: 'long', day: 'numeric'
            });
        },
        openImage(url) {
            this.previewImageUrl = url;
            this.showImageModal = true;
        },
        changeStatus(newStatus) {
            const messages = {
                'Solicitada': '¿Confirmar que la orden ha sido enviada al proveedor?',
                'Recibida': '¿Confirmar recepción? Esto aumentará el stock de los productos.',
                'Cancelada': '¿Seguro que deseas cancelar esta orden?',
                'Borrador': '¿Regresar a borrador para editar?'
            };

            const { dialog } = createDiscreteApi(['dialog']);

            dialog.warning({
                title: `Marcar como ${newStatus}`,
                content: messages[newStatus] || '¿Cambiar estado?',
                positiveText: 'Sí, cambiar',
                negativeText: 'Cancelar',
                onPositiveClick: () => {
                    this.loadingAction = true;
                    router.patch(route('purchases.status', this.order.id), {
                        status: newStatus
                    }, {
                        onFinish: () => this.loadingAction = false,
                        preserveScroll: true
                    });
                }
            });
        },
        printOrder() {
            // Abre la nueva ruta de impresión en una pestaña nueva
            const url = route('purchases.print', this.order.id);
            window.open(url, '_blank');
        }
    }
}
</script>

<template>
    <AppLayout :title="`Orden OC-0${order.id}`">
        <template #header>
            <div class="flex items-center gap-4">
                <Link :href="route('purchases.index')">
                    <n-button circle secondary>
                        <template #icon><n-icon><ArrowBackOutline /></n-icon></template>
                    </n-button>
                </Link>
                <div class="flex flex-col">
                    <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                        Orden de Compra #{{ order.id }}
                    </h2>
                    <span class="text-xs text-gray-500">{{ formatDate(order.created_at) }}</span>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                
                <!-- ALERTA DE CANCELACIÓN -->
                <div v-if="isCancelled" class="mb-6">
                    <n-alert title="Orden Cancelada" type="error" closable>
                        Esta orden ha sido cancelada y no afecta el inventario.
                    </n-alert>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    
                    <!-- COLUMNA IZQUIERDA: ESTADO Y DETALLES -->
                    <div class="lg:col-span-2 space-y-6">
                        
                        <!-- STEPPER DE PROGRESO -->
                        <n-card :bordered="false" class="shadow-sm rounded-2xl">
                            <n-steps :current="currentStep" :status="stepStatus" size="small">
                                <n-step title="Borrador" description="Creación" />
                                <n-step title="Solicitada" description="Proveedor" />
                                <n-step title="Recibida" description="Stock OK" />
                            </n-steps>

                            <n-divider />

                            <!-- BOTONES DE ACCIÓN (Lógica de transición) -->
                            <div class="flex flex-wrap gap-3 justify-end items-center">
                                
                                <!-- Si está en Borrador -->
                                <template v-if="order.status === 'Borrador'">
                                    <!-- Editar: Requiere permiso purchases.edit -->
                                    <Link v-if="hasPermission('purchases.edit')" :href="route('purchases.edit', order.id)">
                                        <n-button secondary>Editar</n-button>
                                    </Link>
                                    
                                    <!-- Solicitar: Requiere permiso purchases.edit -->
                                    <n-button v-if="hasPermission('purchases.edit')" type="info" @click="changeStatus('Solicitada')" :loading="loadingAction">
                                        <template #icon><n-icon><AirplaneOutline /></n-icon></template>
                                        Solicitar
                                    </n-button>
                                </template>

                                <!-- Si está Solicitada -->
                                <template v-if="order.status === 'Solicitada'">
                                    <!-- Cancelar: Requiere permiso purchases.approve -->
                                    <n-button v-if="hasPermission('purchases.approve')" type="error" ghost @click="changeStatus('Cancelada')" :loading="loadingAction">
                                        <template #icon><n-icon><CloseCircleOutline /></n-icon></template>
                                        Cancelar
                                    </n-button>
                                    
                                    <!-- Recibir: Requiere permiso purchases.approve -->
                                    <n-button v-if="hasPermission('purchases.approve')" type="primary" @click="changeStatus('Recibida')" :loading="loadingAction">
                                        <template #icon><n-icon><CheckmarkCircleOutline /></n-icon></template>
                                        Recibir
                                    </n-button>
                                </template>

                                <!-- Si está Cancelada -->
                                <template v-if="order.status === 'Cancelada'">
                                    <!-- Reactivar: Requiere permiso purchases.edit -->
                                    <n-button v-if="hasPermission('purchases.edit')" secondary type="warning" @click="changeStatus('Borrador')" :loading="loadingAction">
                                        Reactivar
                                    </n-button>
                                </template>

                                <p v-if="order.received_date" class="text-green-800 bg-green-100 rounded-md px-2 text-xs py-1">Recibida: {{ formatDate(order.received_date) }}</p>

                                <!-- Común: Imprimir (Disponible para todos los que puedan ver) -->
                                <n-button secondary circle @click="printOrder">
                                    <template #icon><n-icon><PrintOutline /></n-icon></template>
                                </n-button>
                            </div>
                        </n-card>

                        <!-- LISTA DE PRODUCTOS -->
                        <n-card title="Detalle de Productos" :bordered="false" class="shadow-sm rounded-2xl">
                            <template #header-extra>
                                <n-tag type="default" size="small">{{ order.items.length }} Items</n-tag>
                            </template>
                            
                            <!-- TABLA PARA ESCRITORIO (md en adelante) -->
                            <div class="hidden md:block">
                                <n-data-table
                                    :columns="columns"
                                    :data="itemsWithKey"
                                    :bordered="false"
                                    size="small"
                                />
                            </div>

                            <!-- VISTA MOVIL (Tarjetas) (visible solo en sm e inferior) -->
                            <div class="block md:hidden space-y-4">
                                <div v-for="item in itemsWithKey" :key="item.id" class="bg-white border border-gray-100 rounded-lg p-3 shadow-sm flex gap-3">
                                    <!-- Imagen -->
                                    <div class="flex-shrink-0" @click="item.product.image_url && openImage(item.product.image_url)">
                                        <img 
                                            v-if="item.product.image_url" 
                                            :src="item.product.image_url" 
                                            class="w-16 h-16 object-cover rounded-md border border-gray-200"
                                        />
                                        <div v-else class="w-16 h-16 bg-gray-100 rounded-md flex items-center justify-center text-gray-400">
                                            <n-icon size="24"><CubeOutline /></n-icon>
                                        </div>
                                    </div>
                                    <!-- Info -->
                                    <div class="flex-grow">
                                        <p class="font-bold text-gray-800 text-sm leading-tight">{{ item.product.name }}</p>
                                        <p class="text-xs text-gray-500 mb-2">SKU: {{ item.product.sku }}</p>
                                        
                                        <div class="flex justify-between items-end border-t border-dashed border-gray-100 pt-2">
                                            <div class="text-xs text-gray-600">
                                                <span class="block">{{ item.quantity }} x {{ formatCurrency(item.unit_cost) }}</span>
                                            </div>
                                            <div class="font-bold text-indigo-600 text-sm">
                                                {{ formatCurrency(item.subtotal) }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Totales -->
                            <div class="mt-6 flex flex-col items-end border-t border-gray-100 pt-4">
                                <div class="w-full sm:w-1/2 lg:w-1/3 space-y-2">
                                    <div class="flex justify-between text-gray-500">
                                        <span>Moneda:</span>
                                        <span class="font-medium">{{ order.currency }}</span>
                                    </div>
                                    <div class="flex justify-between text-lg font-black text-gray-800">
                                        <span>Total:</span>
                                        <span>{{ formatCurrency(order.total_cost) }}</span>
                                    </div>
                                </div>
                            </div>
                        </n-card>
                    </div>

                    <!-- COLUMNA DERECHA: INFO PROVEEDOR Y NOTAS -->
                    <div class="lg:col-span-1 space-y-6">
                        
                        <!-- TARJETA PROVEEDOR -->
                        <n-card :bordered="false" class="shadow-sm rounded-2xl bg-blue-50/50">
                            <div class="flex flex-col items-center text-center mb-4">
                                <n-icon size="48" class="text-blue-200 mb-2"><StorefrontOutline /></n-icon>
                                <h3 class="font-bold text-lg text-gray-800">{{ order.supplier.company_name }}</h3>
                                <p class="text-sm text-gray-500">{{ order.supplier.email }}</p>
                            </div>
                            <n-descriptions column="1" label-placement="left" size="small">
                                <n-descriptions-item label="Contacto">
                                    {{ order.supplier.contact_name || 'N/A' }}
                                </n-descriptions-item>
                                <n-descriptions-item label="Teléfono">
                                    {{ order.supplier.phone || 'N/A' }}
                                </n-descriptions-item>
                            </n-descriptions>
                        </n-card>

                        <!-- TARJETA DATOS ADICIONALES -->
                        <n-card title="Información Adicional" :bordered="false" class="shadow-sm rounded-2xl">
                            <div class="space-y-4">
                                <div>
                                    <label class="text-xs font-bold text-gray-400 uppercase">Solicitado Por</label>
                                    <div class="flex items-center gap-2 mt-1">
                                        <n-avatar
                                            round
                                            :size="32"
                                            :src="order.requestor.profile_photo_url"
                                            :fallback-src="'https://ui-avatars.com/api/?name=' + order.requestor.name"
                                        />
                                        <span class="text-sm font-medium">{{ order.requestor?.name || 'Sistema' }}</span>
                                    </div>
                                </div>

                                <div>
                                    <label class="text-xs font-bold text-gray-400 uppercase">Fecha Esperada</label>
                                    <p class="text-sm flex items-center gap-2 mt-1">
                                        <n-icon class="text-orange-400"><TimeOutline /></n-icon>
                                        {{ formatDate(order.expected_date) }}
                                    </p>
                                </div>

                                <div v-if="order.notes">
                                    <label class="text-xs font-bold text-gray-400 uppercase">Notas</label>
                                    <div class="bg-yellow-50 p-3 rounded-lg mt-1 text-sm text-yellow-800 border border-yellow-100">
                                        {{ order.notes }}
                                    </div>
                                </div>
                            </div>
                        </n-card>
                    </div>

                </div>
            </div>
        </div>

        <!-- MODAL PARA VER IMAGEN -->
        <n-modal v-model:show="showImageModal" preset="card" style="width: 600px; max-width: 90%" title="Vista Previa del Producto">
            <div class="flex justify-center bg-gray-50 p-4 rounded-lg">
                <img :src="previewImageUrl" class="max-h-[70vh] object-contain rounded shadow-sm" />
            </div>
        </n-modal>

    </AppLayout>
</template>

<style scoped>
/* Ajustes para móviles si es necesario */
</style>