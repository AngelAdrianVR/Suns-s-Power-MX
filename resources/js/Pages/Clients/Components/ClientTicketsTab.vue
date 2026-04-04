<script setup>
import { h } from 'vue';
import { usePermissions } from '@/Composables/usePermissions';
import { router } from '@inertiajs/vue3';
import { NButton, NIcon, NTag, NDataTable, NEmpty } from 'naive-ui';
import { AddOutline, EyeOutline, AlertCircleOutline } from '@vicons/ionicons5';

const props = defineProps({
    client: Object
});

const { hasPermission } = usePermissions();

const formatDate = (dateString) => {
    if (!dateString) return '-';
    return new Date(dateString).toLocaleDateString('es-MX', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
};

// Helpers visuales tomados de tu IndexTicket
const getPriorityColor = (priority) => {
    switch (priority) {
        case 'Urgente': return 'error';
        case 'Alta': return 'warning';
        case 'Media': return 'info';
        default: return 'default';
    }
};

const getStatusType = (status) => {
    switch (status) {
        case 'Resuelto': return 'success';
        case 'Cerrado': return 'default';
        case 'En Análisis': return 'warning';
        default: return 'info'; // Abierto
    }
};

const createTicket = () => {
    // Redirige a crear ticket preseleccionando al cliente
    router.visit(route('tickets.create', { client_id: props.client.id }));
};

const ticketColumns = [
    { title: 'Folio', key: 'id', width: 70, render: (row) => `#${row.id}` },
    { 
        title: 'Asunto', 
        key: 'title',
        render: (row) => h('span', { class: 'font-medium text-gray-800' }, row.title)
    },
    { 
        title: 'Estatus', 
        key: 'status',
        render(row) {
            return h(NTag, { type: getStatusType(row.status), size: 'small', bordered: false, round: true }, { default: () => row.status });
        }
    },
    { 
        title: 'Prioridad', 
        key: 'priority',
        render(row) {
            return h(NTag, { type: getPriorityColor(row.priority), size: 'small', bordered: true }, { 
                default: () => h('div', { class: 'flex items-center gap-1' }, [
                    row.priority === 'Urgente' ? h(NIcon, { component: AlertCircleOutline }) : null,
                    h('span', row.priority)
                ])
            });
        }
    },
    { title: 'Fecha', key: 'created_at', width: 150, render: (row) => formatDate(row.created_at) },
    {
        title: '',
        key: 'actions',
        width: 50,
        render(row) {
            return h(NButton, { 
                circle: true, size: 'tiny', secondary: true,
                onClick: () => router.visit(route('tickets.show', row.id))
            }, { icon: () => h(NIcon, null, { default: () => h(EyeOutline) }) });
        }
    }
];
</script>

<template>
    <div>
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-4 gap-3">
            <div>
                <h3 class="text-base sm:text-lg font-bold text-gray-800">Soporte</h3>
                <p class="text-xs sm:text-sm text-gray-500">Tickets e incidencias reportadas</p>
            </div>
            <n-button 
                v-if="hasPermission('tickets.create')"
                type="primary" 
                round 
                size="small" 
                class="w-full sm:w-auto" 
                @click="createTicket"
            >
                <template #icon><n-icon><AddOutline /></n-icon></template>
                Nuevo Ticket
            </n-button>
        </div>

        <div class="-mx-4 px-4 sm:mx-0 sm:px-0 overflow-x-auto">
            <div class="min-w-[600px] sm:min-w-full">
                <n-data-table
                    v-if="client.tickets && client.tickets.length > 0"
                    :columns="ticketColumns"
                    :data="client.tickets"
                    :bordered="false"
                    size="small"
                    :pagination="{ pageSize: 10 }"
                    class="mb-2"
                />
                <n-empty 
                    v-else 
                    description="El cliente no tiene tickets registrados" 
                    class="py-8"
                />
            </div>
        </div>
    </div>
</template>