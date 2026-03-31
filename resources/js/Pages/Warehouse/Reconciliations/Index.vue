<script setup>
import { ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { 
    NButton, NTag, NCard, NDataTable, NIcon, NModal, NEmpty, 
    NRadioGroup, NRadioButton, NInputNumber, createDiscreteApi 
} from 'naive-ui';
import { 
    CheckmarkCircleOutline, ArrowForwardOutline, AlertCircleOutline, CheckboxOutline, OpenOutline
} from '@vicons/ionicons5';

const props = defineProps({
    orders: Object,
    filterStatus: String
});

const { notification, dialog } = createDiscreteApi(['notification', 'dialog']);

// Lógica de Tabs / Filtro
const currentFilter = ref(props.filterStatus);
const handleFilterChange = (value) => {
    router.get(route('warehouse.reconciliations.index'), { status: value }, { preserveState: true });
};

// Obtener color del status
const getStatusType = (status) => {
    const map = { 'Cotización': 'default', 'Aceptado': 'info', 'En Proceso': 'warning', 'Completado': 'success', 'Facturado': 'success', 'Cancelado': 'error' };
    return map[status] || 'default';
};

// Modal de Conciliación
const showModal = ref(false);
const selectedOrder = ref(null);
const processing = ref(false);

const openReconciliationModal = (order) => {
    // Clonamos profundamente el objeto para que si cerramos el modal sin guardar, no afecte la tabla de atrás
    selectedOrder.value = JSON.parse(JSON.stringify(order));
    showModal.value = true;
};

// Recalcular diferencia en tiempo real si el almacenista cambia el valor
const updateItemDifference = (item) => {
    if (item.used === null || item.used < 0) item.used = 0;
    
    // Recalcular diferencia
    item.difference = item.assigned - item.used;
    
    // Recalcular tipo
    if (item.difference > 0) {
        item.type = 'sobrante';
    } else if (item.difference < 0) {
        item.type = 'faltante';
    } else {
        item.type = 'exacto';
    }
};

const confirmReconciliation = () => {
    dialog.info({
        title: 'Confirmar Ajustes de Inventario',
        content: '¿Estás seguro de aprobar esta conciliación? Las diferencias de material generarán entradas o salidas automáticas en el almacén.',
        positiveText: 'Sí, Ajustar Inventario',
        negativeText: 'Cancelar',
        onPositiveClick: () => {
            processing.value = true;
            // Enviamos los items corregidos al backend
            router.post(route('warehouse.reconciliations.approve', selectedOrder.value.id), {
                items: selectedOrder.value.items_reconciliation
            }, {
                preserveScroll: true,
                onSuccess: () => {
                    notification.success({ title: 'Éxito', content: 'Inventario ajustado correctamente.', duration: 4000 });
                    showModal.value = false;
                },
                onFinish: () => processing.value = false
            });
        }
    });
};
</script>

<template>
    <AppLayout title="Almacén - Conciliaciones">
        <template #header>
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <div>
                    <h2 class="font-bold text-xl text-gray-800 leading-tight flex items-center gap-2">
                        📦 Almacén: Conciliación de Materiales
                    </h2>
                    <p class="text-sm text-gray-500 mt-1">Revisa el material reportado por los técnicos al terminar una instalación.</p>
                </div>
            </div>
        </template>

        <div class="py-8 min-h-[calc(100vh-100px)]">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
                
                <!-- Pestañas de Filtrado -->
                <div class="flex justify-center md:justify-start">
                    <n-radio-group v-model:value="currentFilter" size="large" @update:value="handleFilterChange">
                        <n-radio-button value="pending">
                            Pendientes por Conciliar
                        </n-radio-button>
                        <n-radio-button value="completed">
                            Historial (Conciliados)
                        </n-radio-button>
                    </n-radio-group>
                </div>

                <!-- Lista de Ordenes -->
                <n-card class="rounded-2xl shadow-sm border-0" size="small">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Orden</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Estatus</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Cliente</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Técnico</th>
                                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Estado Material</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Acción</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-100">
                                <tr v-for="order in orders.data" :key="order.id" class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-bold">
                                        <!-- Enlace clicable para ver la orden -->
                                        <Link :href="route('service-orders.show', order.id)" class="text-indigo-600 hover:text-indigo-800 flex items-center gap-1 hover:underline">
                                            #{{ order.id }}
                                            <n-icon size="12"><OpenOutline /></n-icon>
                                        </Link>
                                        <div class="text-xs text-gray-400 font-normal mt-1">Act.: {{ order.completion_date }}</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800">
                                        <!-- Badge de Estatus -->
                                        <n-tag :type="getStatusType(order.status)" round size="small">
                                            {{ order.status }}
                                        </n-tag>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800 font-medium">
                                        {{ order.client_name }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                        {{ order.technician_name }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-center">
                                        <n-tag v-if="order.has_discrepancies" type="warning" size="small" round>
                                            <template #icon><n-icon><AlertCircleOutline /></n-icon></template>
                                            Con Diferencias
                                        </n-tag>
                                        <n-tag v-else type="success" size="small" round>
                                            <template #icon><n-icon><CheckmarkCircleOutline /></n-icon></template>
                                            Cuadre Exacto
                                        </n-tag>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right">
                                        <!-- Botón dinámico según el estado de la conciliación -->
                                        <n-button v-if="filterStatus === 'pending'" type="primary" size="small" @click="openReconciliationModal(order)">
                                            Revisar y Conciliar
                                            <template #icon><n-icon><ArrowForwardOutline /></n-icon></template>
                                        </n-button>
                                        <n-button v-else type="success" secondary size="small" @click="openReconciliationModal(order)">
                                            <template #icon><n-icon><CheckmarkCircleOutline /></n-icon></template>
                                            Conciliado (Ver)
                                        </n-button>
                                    </td>
                                </tr>
                                <tr v-if="!orders.data.length">
                                    <td colspan="6" class="px-6 py-12">
                                        <n-empty description="No hay órdenes en esta sección." />
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </n-card>

            </div>
        </div>

        <!-- MODAL DE DETALLE DE CONCILIACIÓN -->
        <n-modal v-model:show="showModal" :mask-closable="false">
            <n-card style="width: 800px" title="Detalle de Materiales Utilizados" :bordered="false" size="huge" closable @close="showModal = false">
                
                <div v-if="selectedOrder" class="mb-4">
                    
                    <!-- Mensaje Dinámico: Pendiente vs Completado -->
                    <p v-if="filterStatus === 'pending'" class="text-sm text-gray-600 mb-4">
                        Revisa lo reportado por el técnico <strong>{{ selectedOrder.technician_name }}</strong> en la orden <strong>#{{ selectedOrder.id }}</strong>. 
                        Puedes corregir las cantidades si identificas un error en el reporte antes de aprobar.
                    </p>
                    <div v-else class="mb-4 bg-green-50 p-3 rounded-lg border border-green-200 flex items-start gap-3">
                        <n-icon size="24" class="text-green-600 mt-0.5"><CheckmarkCircleOutline/></n-icon>
                        <div>
                            <h4 class="font-bold text-green-800 text-sm">Conciliación Finalizada</h4>
                            <p class="text-xs text-green-700">El inventario ya fue ajustado basándose en las cantidades finales mostradas a continuación. Ya no es posible modificar este registro.</p>
                        </div>
                    </div>

                    <div class="overflow-x-auto border border-gray-200 rounded-lg">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">Producto</th>
                                    <th class="px-4 py-2 text-center text-xs font-medium text-gray-500">Se le Asignó</th>
                                    <!-- Cabecera dinámica de la cantidad usada -->
                                    <th class="px-4 py-2 text-center text-xs font-bold" :class="filterStatus === 'pending' ? 'text-indigo-600' : 'text-green-700'">
                                        {{ filterStatus === 'pending' ? 'Usó (Corregir si aplica)' : 'Cantidad Final Reportada' }}
                                    </th>
                                    <th class="px-4 py-2 text-center text-xs font-medium text-gray-500">Diferencia Final</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-100">
                                <tr v-for="item in selectedOrder.items_reconciliation" :key="item.id">
                                    <td class="px-4 py-2 text-sm text-gray-800">
                                        {{ item.product_name }} <br><span class="text-[10px] text-gray-400">{{ item.product_sku }}</span>
                                    </td>
                                    <td class="px-4 py-2 text-sm text-center text-gray-600 font-semibold">{{ item.assigned }}</td>
                                    <td class="px-4 py-2 text-center">
                                        <!-- Habilitamos la edición solo si está pendiente de conciliar -->
                                        <n-input-number 
                                            v-if="filterStatus === 'pending'"
                                            v-model:value="item.used" 
                                            :min="0" 
                                            :step="0.1" 
                                            :precision="2"
                                            size="small" 
                                            class="w-28 mx-auto"
                                            @update:value="updateItemDifference(item)"
                                        />
                                        <!-- Si ya se concilió, se muestra el valor final de forma destacada -->
                                        <span v-else class="font-bold text-green-800 bg-green-100 px-3 py-1 rounded-md border border-green-200 inline-block min-w-[3rem]">
                                            {{ item.used }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-2 text-center">
                                        <!-- SOBRANTE (Ingresa a Almacén) -->
                                        <n-tag v-if="item.type === 'sobrante'" type="success" size="small" :bordered="false">
                                            + {{ item.difference.toFixed(2) }} (Regresan)
                                        </n-tag>
                                        <!-- FALTANTE (Sale de Almacén) -->
                                        <n-tag v-else-if="item.type === 'faltante'" type="error" size="small" :bordered="false">
                                            {{ item.difference.toFixed(2) }} (Faltaron)
                                        </n-tag>
                                        <!-- EXACTO -->
                                        <n-tag v-else type="default" size="small" :bordered="false">
                                            Exacto
                                        </n-tag>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Notas del Técnico -->
                    <div v-if="selectedOrder.notes" class="mt-4 bg-yellow-50 p-3 rounded-lg border border-yellow-100">
                        <div class="text-xs font-bold text-yellow-800 mb-1">Notas reportadas en la Orden:</div>
                        <p class="text-sm text-yellow-700 whitespace-pre-wrap">{{ selectedOrder.notes }}</p>
                    </div>

                </div>

                <template #footer>
                    <div class="flex justify-end gap-3">
                        <n-button @click="showModal = false">Cerrar</n-button>
                        <!-- El botón de Aprobar SOLO aparece si el estado es pendiente -->
                        <n-button 
                            v-if="filterStatus === 'pending'" 
                            type="success" 
                            @click="confirmReconciliation" 
                            :loading="processing"
                        >
                            <template #icon><n-icon><CheckboxOutline /></n-icon></template>
                            Aprobar y Ajustar Inventario
                        </n-button>
                    </div>
                </template>
            </n-card>
        </n-modal>

    </AppLayout>
</template>