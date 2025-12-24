<script>
import { defineComponent, h } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import axios from 'axios';
import AppLayout from '@/Layouts/AppLayout.vue';
import { 
    NCard, NButton, NIcon, NTag, NNumberAnimation, NStatistic, NGrid, NGi, NDivider, NSpace, NSelect, NAvatar, NSpin
} from 'naive-ui';
import { 
    ArrowBackOutline, CubeOutline, PricetagOutline, LocationOutline, AlertCircleOutline, CreateOutline, SearchOutline
} from '@vicons/ionicons5';

export default defineComponent({
    name: 'ProductShow',
    components: {
        AppLayout, Head, Link,
        NCard, NButton, NIcon, NTag, NNumberAnimation, NStatistic, NGrid, NGi, NDivider, NSpace, NSelect, NAvatar, NSpin,
        ArrowBackOutline, CubeOutline, PricetagOutline, LocationOutline, AlertCircleOutline, CreateOutline, SearchOutline
    },
    props: {
        product: {
            type: Object,
            required: true
        }
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
            // Navegar hacia atrás conservando estado si es posible
            // if (window.history.length > 1) {
            //     window.history.back();
            // } else {
                router.visit(route('products.index'));
            // }
        },
        goToEdit() {
            router.visit(route('products.edit', this.product.id));
        },
        // --- Lógica del Buscador ---
        async handleSearch(query) {
            if (!query) {
                this.searchOptions = [];
                return;
            }
            this.loadingSearch = true;
            try {
                // Asegúrate de tener la ruta 'products.search' definida en web.php
                const response = await axios.get(route('products.search'), { params: { query } });
                this.searchOptions = response.data;
            } catch (error) {
                console.error("Error buscando productos:", error);
            } finally {
                this.loadingSearch = false;
            }
        },
        handleSelectProduct(id) {
            // Navegar al nuevo producto
            router.visit(route('products.show', id));
        },
        // Renderizado personalizado para las opciones del Select (Imagen + Texto)
        renderProductOption(option) {
            // Protección contra renders vacíos
            if (!option) return null;
            
            // Aseguramos que la URL sea válida o undefined para activar el fallback correctamente
            const imageUrl = option.image_url && option.image_url.length > 0 ? option.image_url : undefined;

            return h('div', { class: 'flex items-center gap-3 p-1' }, [
                // Imagen
                h(NAvatar, {
                    src: imageUrl,
                    shape: 'square',
                    size: 40,
                    // Agregamos block y dimensiones fijas por si acaso
                    class: 'flex-shrink-0 bg-gray-100 rounded-lg border border-gray-100 block',
                    style: { width: '40px', height: '40px' }, 
                    objectFit: 'cover',
                    fallbackSrc: '' // Truco para forzar el slot default si falla
                }, { 
                    default: () => h(NIcon, { class: 'text-gray-300' }, { default: () => h(CubeOutline) }) 
                }),
                // Textos
                h('div', { class: 'flex flex-col text-left' }, [
                    h('span', { class: 'font-semibold text-gray-800 text-sm leading-tight' }, option.name),
                    h('span', { class: 'text-xs text-gray-400 font-mono mt-0.5' }, option.sku)
                ])
            ]);
        }
    }
});
</script>

<template>
    <AppLayout :title="product.name">
        <div class="py-8 min-h-screen bg-gray-50/50">
            <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
                
                <!-- Header de Navegación y Búsqueda -->
                <div class="mb-6 flex flex-col md:flex-row items-center justify-between gap-4">
                    <n-button text @click="goBack" class="hover:text-gray-900 text-gray-500 transition-colors self-start md:self-auto">
                        <template #icon>
                            <n-icon size="20"><ArrowBackOutline /></n-icon>
                        </template>
                        Volver
                    </n-button>
                    
                    <!-- Buscador Rápido -->
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
                    
                    <!-- Columna Izquierda: Imagen -->
                    <div class="md:col-span-1">
                        <div class="sticky top-24">
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
                                    
                                    <!-- Badge de Stock en Imagen -->
                                    <div class="absolute top-3 right-3 px-3 py-1 rounded-full text-xs font-bold shadow-sm backdrop-blur-md"
                                         :class="stockStatus.color">
                                        {{ stockStatus.text }}
                                    </div>
                                </div>
                            </div>
                            <!-- Botón editar móvil -->
                            <n-button type="warning" block secondary class="mt-4" @click="goToEdit">
                                Editar Producto
                            </n-button>
                        </div>
                    </div>

                    <!-- Columna Derecha: Información -->
                    <div class="md:col-span-2 space-y-6">
                        
                        <!-- Tarjeta Principal -->
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

                        <!-- Tarjeta de Inventario (Sucursal) -->
                        <div class="bg-white rounded-3xl p-6 shadow-sm border border-gray-100 relative overflow-hidden">
                            <div class="absolute top-0 right-0 p-4 opacity-5">
                                <n-icon size="120"><CubeOutline /></n-icon>
                            </div>
                            
                            <h3 class="font-bold text-lg text-gray-800 mb-4 flex items-center gap-2">
                                <n-icon class="text-indigo-500"><LocationOutline /></n-icon>
                                Disponibilidad en Sucursal
                            </h3>

                            <n-grid x-gap="12" :cols="2">
                                <n-gi>
                                    <div class="bg-gray-50 rounded-2xl p-4 border border-gray-100">
                                        <span class="text-xs text-gray-400 font-semibold uppercase tracking-wider">Stock Actual</span>
                                        <div class="text-2xl font-bold text-gray-800 mt-1">
                                            {{ product.stock }} <span class="text-sm font-normal text-gray-400">unidades</span>
                                        </div>
                                    </div>
                                </n-gi>
                                <n-gi>
                                    <div class="bg-gray-50 rounded-2xl p-4 border border-gray-100">
                                        <span class="text-xs text-gray-400 font-semibold uppercase tracking-wider">Ubicación</span>
                                        <div class="text-lg font-bold text-gray-800 mt-1 truncate" :title="product.location">
                                            {{ product.location }}
                                        </div>
                                    </div>
                                </n-gi>
                            </n-grid>

                            <div v-if="product.stock <= product.min_stock" class="mt-4 flex items-start gap-3 bg-amber-50 p-3 rounded-xl text-amber-700 text-sm">
                                <n-icon class="mt-0.5"><AlertCircleOutline /></n-icon>
                                <span>
                                    <strong>Stock Bajo:</strong> Quedan pocas unidades en esta sucursal. Considera realizar un resurtido pronto.
                                </span>
                            </div>
                        </div>

                    </div>
                </div>

            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
/* Transiciones suaves similares a iOS */
.n-card, .bg-white {
    transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
}
:deep(.n-base-selection) {
    border-radius: 12px; /* Redondear select */
}
</style>