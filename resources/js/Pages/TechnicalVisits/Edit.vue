<script setup>
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import TechnicalVisitEditForm from './Components/TechnicalVisitEditForm.vue';
import { NButton, NIcon } from 'naive-ui';
import { ArrowBackOutline } from '@vicons/ionicons5';

defineProps({
    visit: Object,
    clients: Array,
    sales_reps: Array,
});
</script>

<template>
    <AppLayout title="Editar Visita Técnica">
        <template #header>
            <div class="flex items-center gap-4">
                <Link :href="route('technical-visits.index')">
                    <n-button circle secondary type="default">
                        <template #icon><n-icon><ArrowBackOutline /></n-icon></template>
                    </n-button>
                </Link>
                <div>
                    <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                        Editar Visita Técnica
                    </h2>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <TechnicalVisitEditForm 
                    :visit="visit"
                    :clients="clients" 
                    :sales_reps="sales_reps" 
                />
            </div>
        </div>
    </AppLayout>
</template>