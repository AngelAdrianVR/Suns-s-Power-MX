<script>
// Vue 3 Options API
export default {
    props: {
        order: {
            type: Object,
            required: true
        }
    },
    mounted() {
        // Opcional: imprimir automáticamente al cargar
        // window.print();
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
    <div class="min-h-screen bg-white text-gray-800 p-8 font-sans print-container">
        
        <!-- HEADER -->
        <header class="flex justify-between items-start border-b-2 border-gray-800 pb-6 mb-8">
            <div>
                <h1 class="text-3xl font-bold tracking-tight text-gray-900 uppercase">Orden de Compra</h1>
                <p class="text-gray-500 mt-1">#OC-{{ String(order.id).padStart(6, '0') }}</p>
                <span 
                    class="inline-block mt-2 px-3 py-1 text-xs font-bold uppercase rounded-full border"
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
            <div class="text-right">
                <div class="text-xl font-bold">{{ order.company_info.name }}</div>
                <div class="text-sm text-gray-600 space-y-1 mt-1">
                    <p>{{ order.company_info.address }}</p>
                    <p>{{ order.company_info.phone }}</p>
                    <p class="text-xs text-gray-400 mt-2">Fecha de Emisión: {{ order.created_at }}</p>
                </div>
            </div>
        </header>

        <!-- INFO GRID -->
        <div class="grid grid-cols-2 gap-8 mb-8">
            <!-- PROVEEDOR -->
            <div class="bg-gray-50 p-4 rounded-lg border border-gray-100">
                <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-3">Proveedor</h3>
                <p class="font-bold text-lg text-gray-900">{{ order.supplier.company_name }}</p>
                <div class="text-sm text-gray-600 mt-2 space-y-1">
                    <p><span class="font-medium">Contacto:</span> {{ order.supplier.contact_name }}</p>
                    <p><span class="font-medium">Email:</span> {{ order.supplier.email }}</p>
                    <p><span class="font-medium">Teléfono:</span> {{ order.supplier.phone }}</p>
                </div>
            </div>

            <!-- DETALLES DE ENVÍO -->
            <div class="bg-gray-50 p-4 rounded-lg border border-gray-100">
                <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-3">Detalles de Entrega</h3>
                <div class="text-sm text-gray-600 space-y-2">
                    <div class="flex justify-between border-b border-gray-200 pb-1">
                        <span>Fecha Esperada:</span>
                        <span class="font-medium text-gray-900">{{ order.expected_date }}</span>
                    </div>
                    <div class="flex justify-between border-b border-gray-200 pb-1">
                        <span>Moneda:</span>
                        <span class="font-medium text-gray-900">{{ order.currency }}</span>
                    </div>
                </div>
                <div v-if="order.notes" class="mt-4 pt-2 border-t border-gray-200">
                    <p class="text-xs font-bold text-gray-400 uppercase">Notas:</p>
                    <p class="text-sm italic text-gray-600 mt-1">{{ order.notes }}</p>
                </div>
            </div>
        </div>

        <!-- TABLA -->
        <div class="mb-8">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b-2 border-gray-800">
                        <th class="py-3 px-2 text-xs font-bold text-gray-500 uppercase w-16">Imagen</th>
                        <th class="py-3 px-2 text-xs font-bold text-gray-500 uppercase">Descripción</th>
                        <th class="py-3 px-2 text-xs font-bold text-gray-500 uppercase text-right">Cant.</th>
                        <th class="py-3 px-2 text-xs font-bold text-gray-500 uppercase text-right">Precio Unit.</th>
                        <th class="py-3 px-2 text-xs font-bold text-gray-500 uppercase text-right">Importe</th>
                    </tr>
                </thead>
                <tbody class="text-sm">
                    <tr v-for="(item, index) in order.items" :key="index" class="border-b border-gray-100 hover:bg-gray-50">
                        <td class="py-3 px-2">
                            <img 
                                v-if="item.product_image" 
                                :src="item.product_image" 
                                class="w-10 h-10 object-contain rounded border border-gray-200 bg-white"
                            />
                            <div v-else class="w-10 h-10 bg-gray-100 rounded flex items-center justify-center text-xs text-gray-400">
                                N/A
                            </div>
                        </td>
                        <td class="py-3 px-2">
                            <p class="font-bold text-gray-800">{{ item.product_name }}</p>
                            <p class="text-xs text-gray-500">SKU: {{ item.product_sku }}</p>
                        </td>
                        <td class="py-3 px-2 text-right font-medium">{{ item.quantity }}</td>
                        <td class="py-3 px-2 text-right text-gray-600">{{ formatCurrency(item.unit_cost) }}</td>
                        <td class="py-3 px-2 text-right font-bold text-gray-900">{{ formatCurrency(item.subtotal) }}</td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="4" class="pt-6 text-right text-sm font-bold text-gray-600 uppercase">Total Estimado</td>
                        <td class="pt-6 text-right text-2xl font-black text-gray-900">{{ formatCurrency(order.total_cost) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <!-- FIRMAS -->
        <div class="mt-16 grid grid-cols-2 gap-12 pt-8">
            <div class="text-center">
                <div class="border-t border-gray-300 w-3/4 mx-auto pt-2"></div>
                <p class="text-xs font-bold text-gray-400 uppercase">Firma Autorizada</p>
            </div>
            <div class="text-center">
                <div class="border-t border-gray-300 w-3/4 mx-auto pt-2"></div>
                <p class="text-xs font-bold text-gray-400 uppercase">Recibido Por</p>
            </div>
        </div>

        <!-- BOTÓN FLOTANTE (NO IMPRIMIBLE) -->
        <div class="fixed bottom-8 right-8 print:hidden">
            <button 
                @click="print" 
                class="bg-gray-900 text-white px-6 py-3 rounded-full shadow-lg hover:bg-gray-800 transition-colors flex items-center gap-2 font-bold"
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M5 4v3H4a2 2 0 00-2 2v3a2 2 0 002 2h1v2a2 2 0 002 2h6a2 2 0 002-2v-2h1a2 2 0 002-2V9a2 2 0 00-2-2h-1V4a2 2 0 00-2-2H7a2 2 0 00-2 2zm8 0H7v3h6V4zm0 8H7v4h6v-4z" clip-rule="evenodd" />
                </svg>
                Imprimir Documento
            </button>
        </div>
    </div>
</template>

<style>
@media print {
    body {
        background: white;
    }
    .print\:hidden {
        display: none !important;
    }
    .print-container {
        padding: 0;
    }
}
</style>