<script setup>
import { NCard, NTable, NTag, NEmpty, NProgress } from 'naive-ui';
import { Link } from '@inertiajs/vue3';

defineProps({
    products: Array
});
</script>

<template>
    <n-card title="Alertas de Inventario" size="medium" class="shadow-sm rounded-2xl border-none">
        <template #header-extra>
            <!-- CORRECCIÓN: La ruta correcta según tu web.php es 'products.index' -->
             <Link :href="route('products.index')" class="text-sm text-blue-600 hover:text-blue-800 font-medium">
                Gestión
            </Link>
        </template>

        <n-table :single-line="false" size="small" :bordered="false" v-if="products.length > 0">
            <thead>
                <tr>
                    <th class="text-gray-500 font-normal">Producto</th>
                    <th class="text-center text-gray-500 font-normal">Stock</th>
                    <th class="text-right text-gray-500 font-normal">Estado</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="product in products" :key="product.id">
                    <td>
                        <div class="font-medium text-gray-800 text-sm">{{ product.name }}</div>
                        <div class="text-xs text-gray-400">{{ product.sku }}</div>
                    </td>
                    <td class="text-center">
                        <span :class="{'text-red-600 font-bold': product.current_stock == 0, 'text-orange-600': product.current_stock > 0}">
                            {{ product.current_stock }}
                        </span>
                        <span class="text-xs text-gray-400"> / {{ product.min_stock_alert }}</span>
                    </td>
                    <td class="text-right">
                        <n-tag :type="product.current_stock == 0 ? 'error' : 'warning'" size="small" round :bordered="false">
                            {{ product.status }}
                        </n-tag>
                    </td>
                </tr>
            </tbody>
        </n-table>
        <div v-else class="p-4">
             <n-empty description="Inventario saludable" />
        </div>
    </n-card>
</template>