<script>
// Vue 3 Options API
import { Head } from '@inertiajs/vue3';

export default {
    components:{
        Head
    },
    props: {
        order: {
            type: Object,
            required: true
        }
    },
    methods: {
        formatCurrency(amount) {
            return new Intl.NumberFormat('es-MX', { 
                style: 'currency', 
                currency: this.order.currency || 'MXN' 
            }).format(amount);
        },
        print() {
            window.print();
        }
    }
}
</script>

<template>
<Head :title="'OC-' + String(order.id).padStart(4, '0')" />
    <div class="print-wrapper bg-gray-100 min-h-screen p-8 flex justify-center">
        <!-- Contenedor Hoja Carta -->
        <div class="bg-white text-gray-800 p-10 font-sans shadow-xl print:shadow-none hoja-carta relative">
            
            <!-- HEADER -->
            <header class="flex justify-between items-start border-b-2 border-gray-800 pb-6 mb-6">
                <!-- Logo y Título -->
                <div class="flex flex-col justify-between h-full">
                    <img src="/images/logo-suns-power-mx.webp" alt="Suns Power Logo" class="h-16 object-contain mb-4 w-auto self-start">
                    
                    <div>
                        <h1 class="text-2xl font-bold tracking-tight text-gray-900 uppercase">Orden de Compra</h1>
                        <p class="text-gray-500 font-mono text-sm">OC-{{ String(order.id).padStart(4, '0') }}</p>
                        <span 
                            class="inline-block mt-1 px-2 py-0.5 text-[10px] font-bold uppercase rounded border"
                            :class="{
                                'border-green-600 text-green-700': order.status === 'Recibida',
                                'border-blue-600 text-blue-700': order.status === 'Solicitada',
                                'border-gray-400 text-gray-600': order.status === 'Borrador',
                                'border-red-600 text-red-700': order.status === 'Cancelada'
                            }"
                        >
                            {{ order.status }}
                        </span>
                    </div>
                </div>

                <!-- Información del Proveedor (Ahora en el Header) -->
                <div class="text-right max-w-[250px]">
                    <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Proveedor</h3>
                    <p class="font-bold text-base text-gray-900 leading-tight">{{ order.supplier.company_name }}</p>
                    <div class="text-xs text-gray-600 mt-2 space-y-0.5">
                        <p v-if="order.supplier.contact_name"><span class="font-semibold">Contacto:</span> {{ order.supplier.contact_name }}</p>
                        <p>{{ order.supplier.email }}</p>
                        <p>{{ order.supplier.phone }}</p>
                        <p v-if="order.supplier.address">{{ order.supplier.address }}</p>
                    </div>
                    <p class="text-[10px] text-gray-400 mt-3">Fecha de Emisión: {{ order.created_at }}</p>
                </div>
            </header>

            <!-- DETALLES GENERALES (Grid Compacto) -->
            <div class="mb-6 bg-gray-50 p-3 rounded border border-gray-100 flex justify-between items-center text-sm">
                <div>
                    <span class="text-xs font-bold text-gray-400 uppercase mr-2">Fecha Esperada:</span>
                    <span class="font-medium text-gray-900">{{ order.expected_date }}</span>
                </div>
                <div>
                    <span class="text-xs font-bold text-gray-400 uppercase mr-2">Moneda:</span>
                    <span class="font-medium text-gray-900">{{ order.currency }}</span>
                </div>
            </div>

            <!-- TABLA DE PRODUCTOS -->
            <div class="mb-8">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b-2 border-gray-800">
                            <th class="py-2 px-1 text-[10px] font-bold text-gray-500 uppercase w-12 text-center">Img</th>
                            <th class="py-2 px-1 text-[10px] font-bold text-gray-500 uppercase">Descripción / SKU</th>
                            <th class="py-2 px-1 text-[10px] font-bold text-gray-500 uppercase text-right w-16">Cant.</th>
                            <th class="py-2 px-1 text-[10px] font-bold text-gray-500 uppercase text-right w-24">Precio U.</th>
                            <th class="py-2 px-1 text-[10px] font-bold text-gray-500 uppercase text-right w-24">Importe</th>
                        </tr>
                    </thead>
                    <tbody class="text-xs">
                        <tr v-for="(item, index) in order.items" :key="index" class="border-b border-gray-100">
                            <td class="py-2 px-1 text-center">
                                <img 
                                    v-if="item.product_image" 
                                    :src="item.product_image" 
                                    class="w-8 h-8 object-contain mx-auto"
                                />
                                <span v-else class="text-gray-300">-</span>
                            </td>
                            <td class="py-2 px-1">
                                <p class="font-bold text-gray-800 text-sm">{{ item.product_name }}</p>
                                <p class="text-[10px] text-gray-500">{{ item.product_sku }}</p>
                            </td>
                            <td class="py-2 px-1 text-right font-medium align-top pt-2.5">{{ item.quantity }}</td>
                            <td class="py-2 px-1 text-right text-gray-600 align-top pt-2.5">{{ formatCurrency(item.unit_cost) }}</td>
                            <td class="py-2 px-1 text-right font-bold text-gray-900 align-top pt-2.5">{{ formatCurrency(item.subtotal) }}</td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="4" class="pt-4 text-right text-xs font-bold text-gray-600 uppercase">Total</td>
                            <td class="pt-4 text-right text-lg font-black text-gray-900">{{ formatCurrency(order.total_cost) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <!-- NOTAS Y FIRMAS (Footer) -->
            <div class="mt-auto">
                <div v-if="order.notes" class="mb-8 p-3 border border-gray-200 rounded-lg bg-gray-50">
                    <p class="text-[10px] font-bold text-gray-400 uppercase mb-1">Notas:</p>
                    <p class="text-xs italic text-gray-600">{{ order.notes }}</p>
                </div>

                <div class="grid grid-cols-2 gap-16 mt-12">
                    <div class="text-center">
                        <div class="border-t border-gray-400 w-3/4 mx-auto mb-2"></div>
                        <p class="text-[10px] font-bold text-gray-400 uppercase">Firma Autorizada</p>
                        <p class="text-[10px] text-gray-400">Suns Power</p>
                    </div>
                    <div class="text-center">
                        <div class="border-t border-gray-400 w-3/4 mx-auto mb-2"></div>
                        <p class="text-[10px] font-bold text-gray-400 uppercase">Recibido Por</p>
                        <p class="text-[10px] text-gray-400">{{ order.supplier.company_name }}</p>
                    </div>
                </div>
            </div>

        </div>

        <!-- BOTÓN FLOTANTE (NO IMPRIMIBLE) -->
        <div class="fixed bottom-8 right-8 print:hidden">
            <button 
                @click="print" 
                class="bg-gray-900 text-white px-5 py-3 rounded-full shadow-lg hover:bg-gray-800 transition-colors flex items-center gap-2 font-bold text-sm"
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M5 4v3H4a2 2 0 00-2 2v3a2 2 0 002 2h1v2a2 2 0 002 2h6a2 2 0 002-2v-2h1a2 2 0 002-2V9a2 2 0 00-2-2h-1V4a2 2 0 00-2-2H7a2 2 0 00-2 2zm8 0H7v3h6V4zm0 8H7v4h6v-4z" clip-rule="evenodd" />
                </svg>
                Imprimir
            </button>
        </div>
    </div>
</template>

<style>
/* Estilos para simular hoja carta en pantalla */
.hoja-carta {
    width: 216mm;  /* Ancho carta estándar */
    min-height: 279mm; /* Alto carta estándar */
    box-sizing: border-box;
}

@media print {
    @page {
        size: letter;
        margin: 0;
    }
    body {
        background: white;
    }
    .print-wrapper {
        padding: 0;
        background: white;
        display: block;
    }
    .hoja-carta {
        width: 100%;
        min-height: auto;
        box-shadow: none;
        padding: 20mm !important; /* Margen de impresión seguro */
        margin: 0;
    }
    .print\:hidden {
        display: none !important;
    }
}
</style>