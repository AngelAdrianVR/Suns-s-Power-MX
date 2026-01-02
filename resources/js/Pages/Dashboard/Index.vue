<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import { NGrid, NGridItem, NStatistic, NIcon } from 'naive-ui';

// Importar Componentes Modulares
import ServiceOrdersWidget from './Partials/ServiceOrdersWidget.vue';
import LowStockWidget from './Partials/LowStockWidget.vue';
import PurchaseOrdersWidget from './Partials/PurchaseOrdersWidget.vue';
import ClientBalancesWidget from './Partials/ClientBalancesWidget.vue';

const props = defineProps({
    pendingServiceOrders: Array,
    lowStockProducts: Array,
    pendingPurchaseOrders: Array,
    clientsWithBalance: Array,
    kpis: Object,
});

// Formateador de moneda
const formatCurrency = (value) => {
    return new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(value);
};
</script>

<template>
    <AppLayout title="Dashboard">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Resumen Operativo
            </h2>
        </template>

        <div class="py-12 min-h-screen">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                
                <!-- Sección de KPIs Superiores -->
                <div class="mb-8 grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-2xl p-6 transition-transform hover:scale-[1.01]">
                        <n-statistic label="Servicios en Proceso" :value="kpis.total_pending_services">
                            <template #suffix>
                                <span class="text-xs text-gray-400">órdenes activas</span>
                            </template>
                        </n-statistic>
                    </div>
                    
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-2xl p-6 transition-transform hover:scale-[1.01]">
                        <n-statistic label="Alertas de Inventario" :value="kpis.total_low_stock">
                            <template #prefix>
                                <span class="text-orange-500 text-2xl mr-2">⚠</span>
                            </template>
                        </n-statistic>
                    </div>

                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-2xl p-6 transition-transform hover:scale-[1.01]">
                        <n-statistic label="Ventas del Mes" :value="formatCurrency(kpis.monthly_sales)">
                        </n-statistic>
                    </div>
                </div>

                <!-- Grid Principal de Widgets -->
                <n-grid x-gap="24" y-gap="24" cols="1 1000:2" responsive="screen">
                    
                    <!-- Columna Izquierda -->
                    <n-grid-item>
                        <div class="flex flex-col gap-6">
                            <!-- Widget de Servicios -->
                            <ServiceOrdersWidget :orders="pendingServiceOrders" />
                            
                            <!-- Widget de Clientes con Deuda -->
                            <ClientBalancesWidget :clients="clientsWithBalance" />
                        </div>
                    </n-grid-item>

                    <!-- Columna Derecha -->
                    <n-grid-item>
                        <div class="flex flex-col gap-6">
                            <!-- Widget de Stock Bajo -->
                            <LowStockWidget :products="lowStockProducts" />
                            
                            <!-- Widget de Compras Pendientes -->
                            <PurchaseOrdersWidget :orders="pendingPurchaseOrders" />
                        </div>
                    </n-grid-item>

                </n-grid>
            </div>
        </div>
    </AppLayout>
</template>