<script setup>
import { ref, computed } from 'vue';
import { usePermissions } from '@/Composables/usePermissions';
import { useForm, router } from '@inertiajs/vue3';
import { 
    NButton, NIcon, NPopconfirm, NModal, NForm, 
    NFormItem, NSelect, NInputNumber, createDiscreteApi, NTag
} from 'naive-ui';
import { AddOutline, RemoveCircleOutline, AlertCircleOutline } from '@vicons/ionicons5';

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

// Determina si debemos mostrar las cantidades reportadas (Ya sea que se reportó o la orden ya está finalizada)
const showReportedQuantities = computed(() => {
    return ['Completado', 'Facturado'].includes(props.order.status) || props.order.items?.some(i => i.used_quantity !== null);
});
</script>

<template>
    <div class="p-4">
        <div class="flex justify-between items-center mb-4">
            <h3 class="font-bold text-gray-700">Productos Asignados</h3>
            <n-button 
                v-if="hasPermission('service_orders.edit') && !['Completado', 'Facturado'].includes(order.status)"
                type="primary" 
                size="small" 
                @click="showProductModal = true"
            >
                <template #icon><n-icon><AddOutline /></n-icon></template>
                Agregar Producto
            </n-button>
        </div>

        <div v-if="order.status === 'Completado' && !order.inventory_reconciled" class="mb-4 bg-amber-50 border border-amber-200 p-3 rounded-lg flex items-start gap-3">
            <n-icon size="20" class="text-amber-500 mt-0.5"><AlertCircleOutline/></n-icon>
            <div>
                <h4 class="font-bold text-amber-800 text-sm">Pendiente de Conciliación de Almacén</h4>
                <p class="text-xs text-amber-700">El técnico reportó el material utilizado. Almacén debe revisar las diferencias y ajustar el inventario.</p>
            </div>
        </div>

        <div class="border rounded-lg overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Producto</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Cant. Asignada</th>
                        <th v-if="showReportedQuantities" class="px-6 py-3 text-center text-xs font-bold text-indigo-600 uppercase bg-indigo-50/30">Cant. Utilizada</th>
                        <th v-if="can_view_financials" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">P. Unit (Ref)</th>
                        <th v-if="can_view_financials" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Costo Total Real</th>
                        <th v-if="!['Completado', 'Facturado'].includes(order.status)" class="px-6 py-3"></th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <tr v-for="item in order.items" :key="item.id" :class="{'bg-red-50/20': item.used_quantity !== null && item.used_quantity !== item.quantity}">
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            {{ item.product.name }} <span class="text-gray-400 text-xs">({{ item.product.sku }})</span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-center font-medium">{{ item.quantity }}</td>
                        
                        <td v-if="showReportedQuantities" class="px-6 py-4 whitespace-nowrap text-sm text-center bg-indigo-50/10">
                            <span v-if="item.used_quantity !== null" class="font-bold text-indigo-700 flex items-center justify-center gap-2">
                                {{ item.used_quantity }}
                                <n-tag v-if="item.used_quantity < item.quantity" type="warning" size="tiny" :bordered="false">Sobraron {{ (item.quantity - item.used_quantity).toFixed(2) }}</n-tag>
                                <n-tag v-if="item.used_quantity > item.quantity" type="error" size="tiny" :bordered="false">Faltaron {{ (item.used_quantity - item.quantity).toFixed(2) }}</n-tag>
                            </span>
                            <span v-else class="text-gray-400 italic text-xs">No reportado</span>
                        </td>

                        <td v-if="can_view_financials" class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-right">
                            {{ formatCurrency(item.product.purchase_price) }}
                        </td>
                        
                        <td v-if="can_view_financials" class="px-6 py-4 whitespace-nowrap text-sm font-bold text-gray-800 text-right">
                            <!-- El costo total se basa en lo USADO si ya se reportó, si no, en lo asignado -->
                            {{ formatCurrency(item.product.purchase_price * (item.used_quantity !== null ? item.used_quantity : item.quantity)) }}
                        </td>

                        <td v-if="!['Completado', 'Facturado'].includes(order.status)" class="px-6 py-4 whitespace-nowrap text-right">
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
                        <td colspan="6" class="px-6 py-8 text-center text-gray-400 text-sm">No hay materiales asignados.</td>
                    </tr>
                </tbody>
                <tfoot v-if="can_view_financials" class="bg-gray-50 font-bold border-t-2 border-gray-200">
                    <tr>
                        <td :colspan="showReportedQuantities ? 4 : 3" class="px-6 py-3 text-right">Costo Interno Instalación:</td>
                        <td class="px-6 py-3 text-right text-indigo-700 text-base">
                            <!-- Calcula el total sumando (precio * cantidad_usada o cantidad_asignada) -->
                            {{ formatCurrency(order.items?.reduce((sum, i) => sum + (i.product.purchase_price * (i.used_quantity !== null ? i.used_quantity : i.quantity)), 0) || 0) }}
                        </td>
                        <td v-if="!['Completado', 'Facturado'].includes(order.status)"></td>
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