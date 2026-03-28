<script setup>
import { ref, computed } from 'vue';
import { usePermissions } from '@/Composables/usePermissions';
import { useForm, router } from '@inertiajs/vue3';
import { 
    NButton, NIcon, NPopconfirm, NModal, NForm, 
    NFormItem, NSelect, NInputNumber, createDiscreteApi 
} from 'naive-ui';
import { AddOutline, RemoveCircleOutline } from '@vicons/ionicons5';

const props = defineProps({
    order: Object,
    available_products: Array,
    can_view_financials: Boolean
});

const { hasPermission } = usePermissions();
const { notification } = createDiscreteApi(['notification']);

const showProductModal = ref(false);

const productForm = useForm({
    product_id: null,
    quantity: 1
});

const formatCurrency = (amount) => new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(amount);

const productOptions = computed(() => {
    return props.available_products.map(p => ({
        label: props.can_view_financials 
            ? `${p.name} (${p.sku}) - ${formatCurrency(p.sale_price)}` 
            : `${p.name} (${p.sku})`,
        value: p.id
    }));
});

const addProduct = () => {
    productForm.post(route('service-orders.add-items', props.order.id), {
        onSuccess: () => { 
            showProductModal.value = false; 
            productForm.reset();
            notification.success({ title: 'Producto Agregado', duration: 3000 }); 
        },
        preserveScroll: true
    });
};

const removeProduct = (itemId) => {
    router.delete(route('service-orders.remove-item', itemId), {
        preserveScroll: true,
        onSuccess: () => notification.success({title: 'Producto removido', duration: 3000})
    });
};
</script>

<template>
    <div class="p-4">
        <div class="flex justify-between items-center mb-4">
            <h3 class="font-bold text-gray-700">Productos Asignados</h3>
            <n-button 
                v-if="hasPermission('service_orders.edit')"
                type="primary" 
                size="small" 
                @click="showProductModal = true"
            >
                <template #icon><n-icon><AddOutline /></n-icon></template>
                Agregar Producto
            </n-button>
        </div>

        <div class="border rounded-lg overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Producto</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Cant.</th>
                        <th v-if="can_view_financials" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">P. Unit (Ref)</th>
                        <th v-if="can_view_financials" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Costo Total</th>
                        <th class="px-6 py-3"></th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <tr v-for="item in order.items" :key="item.id">
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            {{ item.product.name }} <span class="text-gray-400 text-xs">({{ item.product.sku }})</span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">{{ item.quantity }}</td>
                        <td v-if="can_view_financials" class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-right">{{ formatCurrency(item.product.purchase_price) }}</td>
                        <td v-if="can_view_financials" class="px-6 py-4 whitespace-nowrap text-sm font-bold text-gray-800 text-right">{{ formatCurrency(item.product.purchase_price * item.quantity) }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-right">
                            <n-popconfirm v-if="hasPermission('service_orders.edit')" @positive-click="removeProduct(item.id)">
                                <template #trigger>
                                    <n-button circle size="tiny" type="error" tertiary>
                                        <template #icon><n-icon><RemoveCircleOutline /></n-icon></template>
                                    </n-button>
                                </template>
                                ¿Quitar producto y devolver stock?
                            </n-popconfirm>
                        </td>
                    </tr>
                    <tr v-if="!order.items?.length">
                        <td colspan="5" class="px-6 py-8 text-center text-gray-400 text-sm">No hay materiales asignados.</td>
                    </tr>
                </tbody>
                <tfoot v-if="can_view_financials" class="bg-gray-50 font-bold">
                    <tr>
                        <td colspan="3" class="px-6 py-3 text-right">Total Materiales (Costo Interno):</td>
                        <td class="px-6 py-3 text-right text-indigo-600">
                            {{ formatCurrency(order.items?.reduce((sum, i) => sum + (i.product.purchase_price * i.quantity), 0) || 0) }}
                        </td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <!-- MODAL: Agregar Producto -->
        <n-modal v-model:show="showProductModal" preset="card" title="Asignar Material/Producto" style="width: 500px;">
            <n-form>
                <n-form-item label="Producto">
                    <n-select 
                        v-model:value="productForm.product_id" 
                        :options="productOptions" 
                        filterable 
                        placeholder="Buscar producto..."
                    />
                </n-form-item>
                <n-form-item label="Cantidad">
                    <n-input-number v-model:value="productForm.quantity" :min="1" />
                </n-form-item>
                <div class="flex justify-end mt-4">
                    <n-button type="primary" @click="addProduct" :loading="productForm.processing" :disabled="!productForm.product_id">
                        Asignar y Descontar
                    </n-button>
                </div>
            </n-form>
        </n-modal>
    </div>
</template>