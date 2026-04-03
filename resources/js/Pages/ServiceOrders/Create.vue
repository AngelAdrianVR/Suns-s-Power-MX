<script setup>
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import ServiceOrderForm from './Components/ServiceOrderForm.vue';
import { NButton, NIcon } from 'naive-ui';
import { ArrowBackOutline } from '@vicons/ionicons5';

defineProps({
    clients: Array,
    technicians: Array,
    sales_reps: Array,
    system_types: Array,
});
</script>

<template>
    <AppLayout title="Nueva Orden de Servicio">
        <template #header>
            <div class="flex items-center gap-4">
                <Link :href="route('service-orders.index')">
                    <n-button circle secondary type="default">
                        <template #icon><n-icon><ArrowBackOutline /></n-icon></template>
                    </n-button>
                </Link>
                <div>
                    <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                        Generar Nueva Orden
                    </h2>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Usamos el componente centralizado -->
                <ServiceOrderForm 
                    :clients="clients" 
                    :technicians="technicians" 
                    :sales_reps="sales_reps" 
                    :system_types="system_types"
                />
            </div>
        </div>
    </AppLayout>
</template>