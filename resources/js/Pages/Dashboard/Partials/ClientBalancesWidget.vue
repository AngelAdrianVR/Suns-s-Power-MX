<script setup>
import { ref } from 'vue';
import { NCard, NList, NListItem, NAvatar, NEmpty, NButton, NIcon, NTooltip } from 'naive-ui';
import { Link, router } from '@inertiajs/vue3';
import { CashOutline, EyeOutline } from '@vicons/ionicons5';
import PaymentModal from '@/Components/MyComponents/PaymentModal.vue'; 

defineProps({
    clients: Array
});

const formatCurrency = (value) => {
    return new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(value);
};

// Generar iniciales para el avatar
const getInitials = (name) => {
    return name
        .split(' ')
        .map(n => n[0])
        .slice(0, 2)
        .join('')
        .toUpperCase();
};

// --- LÓGICA DE PAGOS Y NAVEGACIÓN ---
const showPaymentModal = ref(false);
const selectedClientForPayment = ref(null);

const registerPayment = (client) => {
    selectedClientForPayment.value = client;
    showPaymentModal.value = true;
};

const goToShow = (id) => {
    router.visit(route('clients.show', id));
};
</script>

<template>
    <n-card title="Cuentas por Cobrar" size="medium" class="shadow-sm rounded-2xl border-none" content-style="padding: 0;">
        <template #header-extra>
            <Link :href="route('clients.index')" class="text-sm text-blue-600 hover:text-blue-800 font-medium">
                Clientes
            </Link>
        </template>

        <div v-if="clients.length > 0">
            <n-list hoverable>
                <n-list-item v-for="client in clients" :key="client.id">
                    <div class="flex items-center justify-between md:px-5 py-3">
                        <!-- Info Cliente -->
                        <div class="flex items-center gap-3">
                            <n-avatar round size="small" class="bg-indigo-100 text-indigo-600 font-bold">
                                {{ getInitials(client.name) }}
                            </n-avatar>
                            <div>
                                <div class="font-medium text-gray-800 text-sm">{{ client.name }}</div>
                                <div class="text-xs text-gray-400">{{ client.phone || 'Sin teléfono' }}</div>
                            </div>
                        </div>

                        <!-- Acciones y Saldo -->
                        <div class="flex items-center gap-4">
                            <div class="text-right">
                                <div class="text-red-500 font-bold text-sm">
                                    {{ formatCurrency(client.balance) }}
                                </div>
                                <div class="text-[10px] text-gray-400 uppercase tracking-wide">Pendiente</div>
                            </div>

                            <div class="flex gap-2">
                                <!-- Botón Abonar -->
                                <n-tooltip trigger="hover">
                                    <template #trigger>
                                        <n-button 
                                            circle 
                                            size="small" 
                                            quaternary 
                                            type="success"
                                            class="bg-emerald-50 hover:bg-emerald-100 text-emerald-600"
                                            @click.stop="registerPayment(client)"
                                        >
                                            <template #icon>
                                                <n-icon :component="CashOutline" />
                                            </template>
                                        </n-button>
                                    </template>
                                    Registrar Abono
                                </n-tooltip>

                                <!-- Botón Ver Detalles -->
                                <n-tooltip trigger="hover">
                                    <template #trigger>
                                        <n-button 
                                            circle 
                                            size="small" 
                                            quaternary 
                                            type="info"
                                            @click.stop="goToShow(client.id)"
                                        >
                                            <template #icon>
                                                <n-icon :component="EyeOutline" />
                                            </template>
                                        </n-button>
                                    </template>
                                    Ver Expediente
                                </n-tooltip>
                            </div>
                        </div>
                    </div>
                </n-list-item>
            </n-list>
        </div>
        <div v-else class="p-8">
            <n-empty description="No hay saldos pendientes" />
        </div>

        <!-- MODAL DE PAGOS INTEGRADO -->
        <PaymentModal 
            v-model:show="showPaymentModal" 
            :client="selectedClientForPayment"
            @close="selectedClientForPayment = null"
        />
    </n-card>
</template>