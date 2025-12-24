<script>
import { defineComponent, h, ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import AppLayout from '@/Layouts/AppLayout.vue';
import { 
    NCard, NButton, NIcon, NTag, NNumberAnimation, NStatistic, NGrid, NGi, NDivider, NSpace, NSelect, NAvatar, NSpin,
    NTimeline, NTimelineItem, NCollapse, NCollapseItem, NModal, NForm, NFormItem, NInput, NInputNumber, createDiscreteApi
} from 'naive-ui';
import { 
    ArrowBackOutline, CubeOutline, PricetagOutline, LocationOutline, AlertCircleOutline, CreateOutline, SearchOutline,
    TimeOutline, SwapHorizontalOutline, BuildOutline, ClipboardOutline
} from '@vicons/ionicons5';

export default defineComponent({
    name: 'ProductShow',
    components: {
        AppLayout, Head, Link,
        NCard, NButton, NIcon, NTag, NNumberAnimation, NStatistic, NGrid, NGi, NDivider, NSpace, NSelect, NAvatar, NSpin,
        NTimeline, NTimelineItem, NCollapse, NCollapseItem, NModal, NForm, NFormItem, NInput, NInputNumber,
        ArrowBackOutline, CubeOutline, PricetagOutline, LocationOutline, AlertCircleOutline, CreateOutline, SearchOutline,
        TimeOutline, SwapHorizontalOutline, BuildOutline, ClipboardOutline
    },
    props: {
        product: {
            type: Object,
            required: true
        },
        stock_history: {
            type: Array,
            default: () => []
        }
    },
    setup(props) {
        // Lógica de Notificaciones de Naive UI
        const { notification } = createDiscreteApi(['notification']);

        // --- Lógica del Modal de Ajuste ---
        const showAdjustmentModal = ref(false);
        const adjustmentFormRef = ref(null);
        
        // Formulario solo para el ajuste
        const adjustmentForm = useForm({
            current_stock: props.product.stock, 
            adjustment_note: '', 
        });

        const adjustmentRules = {
            current_stock: { required: true, type: 'number', message: 'Ingresa la cantidad real', trigger: 'blur' },
            adjustment_note: { required: true, message: 'La nota es obligatoria para auditoría', trigger: 'blur' }
        };

        const openAdjustmentModal = () => {
            adjustmentForm.current_stock = props.product.stock;
            adjustmentForm.adjustment_note = '';
            showAdjustmentModal.value = true;
        };

        const submitAdjustment = () => {
            adjustmentFormRef.value?.validate((errors) => {
                if (!errors) {
                    // CAMBIO: Ahora apuntamos a la ruta específica de ajuste
                    adjustmentForm.post(route('products.adjust_stock', props.product.id), {
                        preserveScroll: true,
                        onSuccess: () => {
                            showAdjustmentModal.value = false;
                            notification.success({
                                title: 'Inventario Ajustado',
                                content: 'El stock se ha actualizado y registrado en el historial.',
                                duration: 3000
                            });
                        },
                        onError: () => {
                            notification.error({ title: 'Error', content: 'No se pudo procesar el ajuste.' });
                        }
                    });
                }
            });
        };

        return {
            showAdjustmentModal,
            adjustmentForm,
            adjustmentRules,
            adjustmentFormRef,
            openAdjustmentModal,
            submitAdjustment
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
        formattedPrice() {
            return new Intl.NumberFormat('es-MX', {
                style: 'currency',
                currency: 'MXN'
            }).format(this.product.sale_price);
        },
        stockStatus() {
            if (this.product.stock <= 0) return { type: 'error', text: 'Agotado', color: 'bg-red-100 text-red-600' };
            if (this.product.stock <= this.product.min_stock) return { type: 'warning', text: 'Stock Bajo', color: 'bg-amber-100 text-amber-600' };
            return { type: 'success', text: 'Disponible', color: 'bg-green-100 text-green-600' };
        }
    },
    methods: {
        goBack() {
            router.visit(route('products.index'));
        },
        goToEdit() {
            router.visit(route('products.edit', this.product.id));
        },
        async handleSearch(query) {
            if (!query) {
                this.searchOptions = [];
                return;
            }
            this.loadingSearch = true;
            try {
                const response = await axios.get(route('products.search'), { params: { query } });
                this.searchOptions = response.data;
            } catch (error) {
                console.error("Error buscando productos:", error);
            } finally {
                this.loadingSearch = false;
            }
        },
        handleSelectProduct(id) {
            router.visit(route('products.show', id));
        },
        renderProductOption(option) {
            if (!option) return null;
            const imageUrl = option.image_url && option.image_url.length > 0 ? option.image_url : undefined;

            return h('div', { class: 'flex items-center gap-3 p-1' }, [
                h(NAvatar, {
                    src: imageUrl,
                    shape: 'square',
                    size: 40,
                    class: 'flex-shrink-0 bg-gray-100 rounded-lg border border-gray-100 block',
                    style: { width: '40px', height: '40px' }, 
                    objectFit: 'cover',
                    fallbackSrc: ''
                }, { 
                    default: () => h(NIcon, { class: 'text-gray-300' }, { default: () => h(CubeOutline) }) 
                }),
                h('div', { class: 'flex flex-col text-left' }, [
                    h('span', { class: 'font-semibold text-gray-800 text-sm leading-tight' }, option.name),
                    h('span', { class: 'text-xs text-gray-400 font-mono mt-0.5' }, option.sku)
                ])
            ]);
        },
        getTimelineType(type) {
            if (type === 'Entrada') return 'success';
            if (type === 'Salida') return 'error';
            return 'info';
        }
    }
});
</script>

<template>
    <AppLayout :title="product.name">
        <div class="py-8 min-h-screen">
            <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
                
                <!-- Header de Navegación -->
                <div class="mb-6 flex flex-col md:flex-row items-center justify-between gap-4">
                    <n-button text @click="goBack" class="hover:text-gray-900 text-gray-500 transition-colors self-start md:self-auto">
                        <template #icon>
                            <n-icon size="20"><ArrowBackOutline /></n-icon>
                        </template>
                        Volver
                    </n-button>
                    
                    <div class="w-full md:w-96">
                        <n-select
                            v-model:value="searchQuery"
                            filterable
                            placeholder="Buscar otro producto (Nombre o SKU)..."
                            :options="searchOptions"
                            :loading="loadingSearch"
                            clearable
                            remote
                            size="large"
                            class="shadow-sm rounded-xl"
                            @search="handleSearch"
                            @update:value="handleSelectProduct"
                            :render-label="renderProductOption"
                        >
                            <template #arrow>
                                <n-icon><SearchOutline /></n-icon>
                            </template>
                        </n-select>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                    
                    <!-- Columna Izquierda: Imagen y Stock -->
                    <div class="md:col-span-1 space-y-6">
                        <!-- Imagen -->
                        <div class="bg-white rounded-3xl p-2 shadow-lg border border-gray-100 overflow-hidden relative group">
                            <div class="aspect-square rounded-2xl overflow-hidden bg-gray-50 relative">
                                <img 
                                    v-if="product.image_url" 
                                    :src="product.image_url" 
                                    class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105"
                                    alt="Producto"
                                />
                                <div v-else class="w-full h-full flex flex-col items-center justify-center text-gray-300">
                                    <n-icon size="64"><CubeOutline /></n-icon>
                                    <span class="text-sm mt-2 font-medium">Sin imagen</span>
                                </div>
                                <div class="absolute top-3 right-3 px-3 py-1 rounded-full text-xs font-bold shadow-sm backdrop-blur-md"
                                     :class="stockStatus.color">
                                    {{ stockStatus.text }}
                                </div>
                            </div>
                        </div>

                        <!-- Tarjeta de Inventario (Sucursal) -->
                        <div class="bg-white rounded-3xl p-6 shadow-sm border border-gray-100 relative overflow-hidden">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="font-bold text-lg text-gray-800 flex items-center gap-2">
                                    <n-icon class="text-indigo-500"><LocationOutline /></n-icon>
                                    Inventario
                                </h3>
                                <!-- Botón Ajuste Rápido -->
                                <n-button size="small" secondary circle type="info" @click="openAdjustmentModal">
                                    <template #icon><n-icon><ClipboardOutline /></n-icon></template>
                                </n-button>
                            </div>

                            <div class="space-y-4">
                                <div class="bg-gray-50 rounded-2xl p-4 border border-gray-100 flex justify-between items-center">
                                    <span class="text-xs text-gray-400 font-semibold uppercase">Actual</span>
                                    <div class="text-2xl font-bold text-gray-800">{{ product.stock }}</div>
                                </div>
                                <div class="bg-gray-50 rounded-2xl p-4 border border-gray-100 flex justify-between items-center">
                                    <span class="text-xs text-gray-400 font-semibold uppercase">Mínimo permitido</span>
                                    <div class="text-lg font-bold text-gray-600">{{ product.min_stock }}</div>
                                </div>
                                <div class="bg-gray-50 rounded-2xl p-4 border border-gray-100">
                                    <span class="text-xs text-gray-400 font-semibold uppercase block mb-1">Ubicación</span>
                                    <div class="text-sm font-medium text-gray-800 truncate" :title="product.location">
                                        {{ product.location }}
                                    </div>
                                </div>
                            </div>
                            <n-button type="warning" block secondary class="mt-4" @click="goToEdit">
                                Editar Producto
                            </n-button>
                        </div>
                    </div>

                    <!-- Columna Derecha: Información e Historial -->
                    <div class="md:col-span-2 space-y-6">
                        
                        <!-- Tarjeta Info Principal -->
                        <div class="bg-white rounded-3xl p-6 md:p-8 shadow-sm border border-gray-100">
                            <div class="flex flex-col gap-1 mb-4">
                                <div class="flex items-center gap-2">
                                    <n-tag type="default" size="small" round :bordered="false" class="bg-gray-100 text-gray-500 font-mono">
                                        {{ product.sku }}
                                    </n-tag>
                                    <n-tag type="primary" size="small" round :bordered="false" class="bg-blue-50 text-blue-600">
                                        {{ product.category }}
                                    </n-tag>
                                </div>
                                <h1 class="text-3xl md:text-4xl font-black text-gray-800 tracking-tight">{{ product.name }}</h1>
                            </div>

                            <n-statistic label="Precio de Venta" class="mb-6">
                                <template #prefix>$</template>
                                <span class="text-4xl font-bold text-emerald-600">
                                    <n-number-animation 
                                        ref="numberAnimationInstRef"
                                        :from="0" 
                                        :to="product.sale_price" 
                                        :active="true" 
                                        :precision="2" 
                                        show-separator
                                    />
                                </span>
                                <template #suffix>MXN</template>
                            </n-statistic>

                            <n-divider />

                            <div class="prose prose-sm text-gray-500 max-w-none">
                                <h4 class="text-gray-800 font-semibold mb-2">Descripción</h4>
                                <p>{{ product.description || 'No hay descripción disponible para este producto.' }}</p>
                            </div>
                        </div>

                        <!-- Sección de Historial de Movimientos -->
                        <div class="bg-white rounded-3xl p-6 shadow-sm border border-gray-100">
                            <h3 class="font-bold text-xl text-gray-800 mb-6 flex items-center gap-2">
                                <n-icon class="text-orange-500"><SwapHorizontalOutline /></n-icon>
                                Historial de Movimientos
                            </h3>

                            <div v-if="stock_history.length === 0" class="text-center py-8 text-gray-400">
                                <n-icon size="40" class="mb-2 opacity-50"><TimeOutline /></n-icon>
                                <p>No hay movimientos registrados en esta sucursal.</p>
                            </div>

                            <!-- Historial Agrupado -->
                            <n-collapse v-else display-directive="show" class="max-h-96 overflow-auto" :default-expanded-names="[stock_history[0].group_label]">
                                <n-collapse-item 
                                    v-for="(group, index) in stock_history" 
                                    :key="index" 
                                    :title="group.group_label" 
                                    :name="group.group_label"
                                >
                                    <n-timeline>
                                        <n-timeline-item
                                            v-for="movement in group.movements"
                                            :key="movement.id"
                                            :type="getTimelineType(movement.type)"
                                            :title="movement.reference_text"
                                            :time="movement.date"
                                        >
                                            <div class="flex flex-col gap-1 text-sm">
                                                <div class="flex items-center gap-2 font-medium">
                                                    <span :class="{
                                                        'text-green-600': movement.type === 'Entrada',
                                                        'text-red-600': movement.type === 'Salida',
                                                        'text-blue-600': movement.type === 'Ajuste'
                                                    }">
                                                        {{ movement.type === 'Entrada' ? '+' : (movement.type === 'Salida' ? '-' : '') }}{{ movement.quantity }} uni.
                                                    </span>
                                                    <span class="text-gray-400 font-normal">&rarr; Stock final: {{ movement.stock_after }} uni.</span>
                                                </div>
                                                <div class="text-gray-500 text-xs">
                                                    Por: {{ movement.user_name }}
                                                </div>
                                                <div v-if="movement.notes" class="text-gray-400 text-xs italic bg-gray-50 p-1 rounded mt-1">
                                                    "{{ movement.notes }}"
                                                </div>
                                            </div>
                                        </n-timeline-item>
                                    </n-timeline>
                                </n-collapse-item>
                            </n-collapse>
                        </div>

                    </div>
                </div>

                <!-- Modal de Ajuste de Inventario -->
                <n-modal v-model:show="showAdjustmentModal">
                    <n-card
                        style="width: 500px"
                        title="Ajuste Rápido de Inventario"
                        :bordered="false"
                        size="huge"
                        role="dialog"
                        aria-modal="true"
                    >
                        <template #header-extra>
                            <n-icon size="24" class="text-indigo-500"><ClipboardOutline /></n-icon>
                        </template>
                        
                        <p class="text-gray-500 text-sm mb-6">
                            Estás por realizar un ajuste manual al inventario físico de esta sucursal. 
                            Esta acción quedará registrada en el historial.
                        </p>

                        <n-form
                            ref="adjustmentFormRef"
                            :model="adjustmentForm"
                            :rules="adjustmentRules"
                        >
                            <n-form-item label="Nuevo Stock Real (Físico)" path="current_stock">
                                <n-input-number 
                                    v-model:value="adjustmentForm.current_stock" 
                                    :min="0"
                                    class="w-full text-center font-bold"
                                    size="large"
                                />
                            </n-form-item>

                            <n-form-item label="Motivo del Ajuste (Nota)" path="adjustment_note">
                                <n-input 
                                    v-model:value="adjustmentForm.adjustment_note" 
                                    type="textarea" 
                                    placeholder="Ej. Conteo cíclico, merma por daño, regalo a cliente..."
                                    :rows="3"
                                />
                            </n-form-item>
                        </n-form>

                        <template #footer>
                            <div class="flex justify-end gap-3">
                                <n-button @click="showAdjustmentModal = false" :disabled="adjustmentForm.processing">
                                    Cancelar
                                </n-button>
                                <n-button 
                                    type="primary" 
                                    @click="submitAdjustment" 
                                    :loading="adjustmentForm.processing"
                                >
                                    Guardar Ajuste
                                </n-button>
                            </div>
                        </template>
                    </n-card>
                </n-modal>

            </div>
        </div>
    </AppLayout>
</template>