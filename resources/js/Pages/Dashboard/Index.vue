<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { usePermissions } from '@/Composables/usePermissions';
import { Head, router } from '@inertiajs/vue3';
import { ref, watch, computed, reactive } from 'vue';
import { NGrid, NGridItem, NStatistic, NIcon, NButton } from 'naive-ui';
import { ArrowForwardOutline, RefreshOutline } from '@vicons/ionicons5';

// Importar Componentes Modulares
import ServiceOrdersWidget from './Partials/ServiceOrdersWidget.vue';
import LowStockWidget from './Partials/LowStockWidget.vue';
import PurchaseOrdersWidget from './Partials/PurchaseOrdersWidget.vue';
import ClientBalancesWidget from './Partials/ClientBalancesWidget.vue';

// Importar componentes del PMS (Asegúrate de que estas rutas existan)
import TaskCard from '@/Pages/PMS/Components/TaskCard.vue';
import TaskDetailModal from '@/Pages/PMS/Components/TaskDetailModal.vue';

const props = defineProps({
    pendingServiceOrders: Array,
    lowStockProducts: Array,
    pendingPurchaseOrders: Array,
    clientsWithBalance: Array,
    kpis: Object,
    weeklyTasks: Object,
    weekDays: Array,
});

const { hasPermission } = usePermissions();

// Estado para el Modal del Dashboard
const detailModalOpen = ref(false);
const selectedTask = ref(null);

const openTaskDetail = (task) => {
    selectedTask.value = task;
    detailModalOpen.value = true;
};

// ==========================================
// DISTRIBUCIÓN DE TAREAS EN MÚLTIPLES DÍAS
// ==========================================
const processedWeeklyTasks = computed(() => {
    const spanTasks = {};
    
    // Inicializar los arreglos para cada día de la semana mostrada
    props.weekDays.forEach(day => {
        spanTasks[day.date] = [];
    });

    // 1. Extraer todas las tareas únicas enviadas por el backend
    const allTasks = [];
    const taskIds = new Set();
    
    Object.values(props.weeklyTasks || {}).forEach(dayTasks => {
        dayTasks.forEach(task => {
            if (!taskIds.has(task.id)) {
                taskIds.add(task.id);
                allTasks.push(task);
            }
        });
    });

    // 2. Distribuir las tareas en los días correspondientes según su duración
    allTasks.forEach(task => {
        if (!task.start_date) return;

        // Extraer solo la parte "YYYY-MM-DD" de las fechas para evitar problemas de zona horaria
        const taskStartStr = task.start_date.substring(0, 10);
        const taskEndStr = task.due_date ? task.due_date.substring(0, 10) : taskStartStr;

        props.weekDays.forEach(day => {
            // Comparación de strings 'YYYY-MM-DD' (Funciona perfectamente para rangos de fechas)
            if (day.date >= taskStartStr && day.date <= taskEndStr) {
                spanTasks[day.date].push(task);
            }
        });
    });

    return spanTasks;
});

// ==========================================
// SCROLL INFINITO (CARGA PEREZOSA)
// ==========================================
const ITEMS_PER_PAGE = 10;
const displayLimits = reactive({});

// Inicializar y resetear límites al cambiar la semana o montar
watch(() => props.weekDays, (newDays) => {
    newDays.forEach(day => {
        if (!displayLimits[day.date]) {
            displayLimits[day.date] = ITEMS_PER_PAGE;
        }
    });
}, { immediate: true });

// Controlador de scroll para cada columna
const handleColumnScroll = (e, date) => {
    const { scrollTop, clientHeight, scrollHeight } = e.target;
    // Si se llega al final del contenedor (margen de 20px)
    if (scrollTop + clientHeight >= scrollHeight - 20) {
        if (processedWeeklyTasks.value[date] && displayLimits[date] < processedWeeklyTasks.value[date].length) {
            displayLimits[date] += ITEMS_PER_PAGE;
        }
    }
};

// Mantener la tarea del modal actualizada observando nuestra variable procesada
watch(() => processedWeeklyTasks.value, (newTasks) => {
    if (selectedTask.value && newTasks) {
        let updatedTask = null;
        for (const date in newTasks) {
            updatedTask = newTasks[date].find(t => t.id === selectedTask.value.id);
            if (updatedTask) break;
        }
        if (updatedTask) {
            selectedTask.value = updatedTask;
        }
    }
}, { deep: true });

// Si dan click en "Editar" desde el Dashboard, los mandamos al módulo PMS
const redirectToPms = () => {
    router.get(route('tasks.index'));
};

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

                    <div v-if="hasPermission('sales.view_sales_amount')" class="bg-white overflow-hidden shadow-sm sm:rounded-2xl p-6 transition-transform hover:scale-[1.01]">
                        <n-statistic label="Ventas del Mes" :value="formatCurrency(kpis.monthly_sales)">
                        </n-statistic>
                    </div>
                </div>

                <!-- NUEVA SECCIÓN: PLAN DE TAREAS SEMANAL (Adaptado a móviles) -->
                <div class="mb-10 flex flex-col">
                    <div class="lg:flex justify-between items-center mb-4 px-2">
                        <h3 class="text-lg font-bold text-gray-800 mb-2 lg:mb-0">Mi Plan de Tareas Semanal</h3>
                        <n-button v-if="hasPermission('pms.index')" type="primary" secondary size="small" @click="redirectToPms">
                            Gestionar plan de tareas
                            <template #icon><n-icon><ArrowForwardOutline/></n-icon></template>
                        </n-button>
                    </div>
                    
                    <!-- Contenedor con Scroll Horizontal nativo para UX Móvil (Snap) -->
                    <div class="flex overflow-x-auto pb-4 snap-x snap-mandatory gap-4 w-full scroll-smooth" style="scroll-padding: 0.5rem;">
                        
                        <!-- Columnas de días ajustadas -->
                        <div v-for="day in weekDays" :key="day.date" class="snap-start shrink-0 w-[220px] sm:w-[260px] flex flex-col bg-white/60 rounded-2xl border border-gray-100 shadow-sm ml-2 first:ml-0">
                            <!-- Cabecera del día -->
                             <div class="py-2.5 border-b border-gray-100 text-center flex flex-col items-center bg-white rounded-t-2xl sticky top-0 z-10">
                                <span class="text-[10px] uppercase font-bold text-gray-400 tracking-wider w-full px-1">{{ day.day_name }}</span>
                                <span class="text-xl font-black text-gray-700 leading-none mt-1">{{ day.day_number }}</span>
                            </div>
                            <!-- Lista de tareas del día con controlador de scroll -->
                            <div class="flex-1 p-2.5 overflow-y-auto space-y-2.5 max-h-[400px] custom-scrollbar" @scroll="(e) => handleColumnScroll(e, day.date)">
                                 <template v-if="processedWeeklyTasks[day.date]?.length">
                                     <!-- Renderizado condicional basado en el límite -->
                                     <template v-for="(task, index) in processedWeeklyTasks[day.date]" :key="task.id">
                                         <TaskCard 
                                            v-if="index < displayLimits[day.date]"
                                            :task="task" 
                                            @click="openTaskDetail(task)" 
                                         />
                                     </template>

                                     <!-- Indicador de carga inferior -->
                                     <div v-if="processedWeeklyTasks[day.date]?.length > displayLimits[day.date]" class="py-3 text-center text-xs font-medium text-gray-400 flex items-center justify-center bg-white/50 rounded-lg border border-dashed border-gray-200 mt-1">
                                         <n-icon class="animate-spin mr-1.5" size="14"><RefreshOutline/></n-icon>
                                         Cargando más...
                                     </div>
                                 </template>
                                 <div v-else class="h-24 flex items-center justify-center text-gray-400 text-xs font-medium bg-gray-50/50 rounded-xl border border-dashed border-gray-200">
                                     Sin tareas
                                 </div>
                            </div>
                        </div>

                    </div>
                </div>
                <!-- FIN SECCIÓN PLAN DE TAREAS -->

                <!-- Grid Principal de Widgets -->
                <n-grid x-gap="24" y-gap="24" cols="1 1000:2" responsive="screen">
                    
                    <!-- Columna Izquierda -->
                    <n-grid-item>
                        <div class="flex flex-col gap-6">
                            <!-- Widget de Servicios -->
                            <!-- <ServiceOrdersWidget v-if="hasPermission('service_orders.index')" :orders="pendingServiceOrders" /> -->
                            
                             <!-- Widget de Clientes con Deuda (Protegido por el permiso 'clients.view_balance') -->
                            <ClientBalancesWidget v-if="hasPermission('clients.view_balance')" :clients="clientsWithBalance" />
                        </div>
                    </n-grid-item>

                    <!-- Columna Derecha -->
                    <n-grid-item>
                        <div class="flex flex-col gap-6">
                            <!-- Widget de Stock Bajo -->
                            <LowStockWidget v-if="hasPermission('warehouse.alarms_stock')" :products="lowStockProducts"  />
                            
                            <!-- Widget de Compras Pendientes -->
                            <PurchaseOrdersWidget v-if="hasPermission('purchases.index')" :orders="pendingPurchaseOrders" />
                        </div>
                    </n-grid-item>

                </n-grid>
            </div>
        </div>

        <!-- Modal de Detalle de Tarea -->
        <TaskDetailModal 
            v-model:show="detailModalOpen" 
            :task="selectedTask"
            @edit="redirectToPms" 
        />

    </AppLayout>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
    height: 6px;
    width: 6px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background-color: #cbd5e1; 
    border-radius: 4px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background-color: #94a3b8; 
}
</style>