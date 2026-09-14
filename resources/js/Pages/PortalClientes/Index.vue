<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { NBadge, NIcon } from 'naive-ui';
import { CashOutline, CheckmarkCircleOutline, ChevronForwardOutline } from '@vicons/ionicons5';
import AppLayout from '@/Layouts/AppLayout.vue';
import { usePermissions } from '@/Composables/usePermissions';

const props = defineProps({
    pagos: Object,
});

const { hasPermission } = usePermissions();

const formatCurrency = (amount) =>
    new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(amount || 0);

// Tarjetas de módulos del portal. Por ahora solo "Pagos";
// las siguientes (tickets, servicios, etc.) se agregan aquí.
const cards = computed(() => {
    const items = [];

    if (hasPermission('validar_abonos')) {
        items.push({
            key: 'pagos',
            title: 'Pagos',
            description: 'Abonos y comprobantes enviados por los clientes desde el portal.',
            route: 'portal-abonos.index',
            icon: CashOutline,
            pendingCount: props.pagos?.pending_count ?? 0,
            pendingAmount: props.pagos?.pending_amount ?? 0,
        });
    }

    return items;
});
</script>

<template>
    <AppLayout title="Portal de Clientes">
        <template #header>
            <div>
                <h2 class="font-semibold text-2xl text-gray-800 leading-tight">Portal de Clientes</h2>
                <p class="text-sm text-gray-500 mt-1">
                    Información que tus clientes envían desde el portal.
                </p>
            </div>
        </template>

        <div class="py-8">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div v-if="cards.length" class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-5">
                    <Link
                        v-for="card in cards"
                        :key="card.key"
                        :href="route(card.route)"
                        class="group bg-white rounded-2xl border border-gray-100 shadow-sm hover:shadow-lg hover:border-blue-200 transition-all duration-300 p-5 flex flex-col gap-4"
                    >
                        <div class="flex items-start justify-between">
                            <div class="flex items-center gap-3">
                                <div class="w-11 h-11 rounded-2xl bg-amber-50 text-amber-600 flex items-center justify-center">
                                    <n-badge
                                        :value="card.pendingCount"
                                        :show="card.pendingCount > 0"
                                        :max="99"
                                        type="warning"
                                        :offset="[-2, 2]"
                                    >
                                        <n-icon :component="card.icon" size="22" />
                                    </n-badge>
                                </div>
                                <div>
                                    <h3 class="font-semibold text-gray-800">{{ card.title }}</h3>
                                    <p class="text-xs text-gray-400">Portal de clientes</p>
                                </div>
                            </div>

                            <n-icon
                                :component="ChevronForwardOutline"
                                size="18"
                                class="text-gray-300 group-hover:text-blue-500 transition-colors"
                            />
                        </div>

                        <p class="text-sm text-gray-500 leading-relaxed">
                            {{ card.description }}
                        </p>

                        <!-- Notificación de pendientes -->
                        <div class="mt-auto">
                            <div
                                v-if="card.pendingCount > 0"
                                class="flex flex-wrap items-center gap-x-2 gap-y-1 rounded-xl bg-amber-50 border border-amber-100 px-3 py-2"
                            >
                                <span class="text-xs font-semibold text-amber-700">
                                    {{ card.pendingCount }}
                                    {{ card.pendingCount === 1 ? 'abono pendiente' : 'abonos pendientes' }}
                                    de validar
                                </span>
                                <span class="text-xs text-amber-600">
                                    {{ formatCurrency(card.pendingAmount) }} por aprobar
                                </span>
                            </div>
                            <div
                                v-else
                                class="flex items-center gap-1.5 rounded-xl bg-emerald-50 border border-emerald-100 px-3 py-2"
                            >
                                <n-icon :component="CheckmarkCircleOutline" size="14" class="text-emerald-500" />
                                <span class="text-xs text-emerald-600">Sin pendientes por validar</span>
                            </div>
                        </div>
                    </Link>
                </div>

                <p v-else class="text-sm text-gray-400">
                    No tienes módulos disponibles por el momento.
                </p>
            </div>
        </div>
    </AppLayout>
</template>
