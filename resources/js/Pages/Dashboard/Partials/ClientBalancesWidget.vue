<script setup>
import { NCard, NList, NListItem, NAvatar, NEmpty } from 'naive-ui';
import { Link } from '@inertiajs/vue3';

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
                    <div class="flex items-center justify-between px-6 py-3">
                        <div class="flex items-center gap-3">
                            <n-avatar round size="small" class="bg-indigo-100 text-indigo-600 font-bold">
                                {{ getInitials(client.name) }}
                            </n-avatar>
                            <div>
                                <div class="font-medium text-gray-800 text-sm">{{ client.name }}</div>
                                <div class="text-xs text-gray-400">{{ client.phone || 'Sin teléfono' }}</div>
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="text-red-500 font-bold text-sm">
                                {{ formatCurrency(client.balance) }}
                            </div>
                            <div class="text-[10px] text-gray-400 uppercase tracking-wide">Pendiente</div>
                        </div>
                    </div>
                </n-list-item>
            </n-list>
        </div>
        <div v-else class="p-8">
            <n-empty description="No hay saldos pendientes" />
        </div>
    </n-card>
</template>