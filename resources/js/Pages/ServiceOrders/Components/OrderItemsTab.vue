<script setup>
import { ref, computed } from 'vue';
import { usePermissions } from '@/Composables/usePermissions';
import { useForm, router } from '@inertiajs/vue3';
import { 
    NButton, NIcon, NPopconfirm, NModal, NForm, 
    NFormItem, NSelect, NInputNumber, createDiscreteApi, NTag
} from 'naive-ui';
import { AddOutline, RemoveCircleOutline, AlertCircleOutline, ChevronUpOutline, ChevronDownOutline, SwapVerticalOutline } from '@vicons/ionicons5';

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

// ================= CÁLCULO SEGURO =================
// Se agregó 'props.order?.items' para evitar errores si la página carga antes de tener los datos listos.
const sortedItems = computed(() => {
    if (!props.order || !props.order.items) return [];
    return [...props.order.items].sort((a, b) => (a.order || 0) - (b.order || 0));
});

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
            notification.success({ title: 'Éxito', content: 'Material asignado a la orden', duration: 3000 });
        }
    });
};

const removeProduct = (itemId) => {
    router.delete(route('service-orders.remove-item', itemId), {
        preserveScroll: true,
        onSuccess: () => notification.success({ title: 'Removido', content: 'Material removido', duration: 3000 })
    });
};
</script>

<template>
    <div class="bg-white rounded-2xl border border-gray-100 p-6 shadow-sm min-h-[400px]">
        <div class="flex justify-between items-center mb-6">
            <div>
                <h3 class="text-lg font-bold text-gray-800">Material y Equipos Asignados</h3>
                <p class="text-sm text-gray-500">Lista de productos que se descontarán del inventario para esta instalación.</p>
            </div>
            
            <n-button 
                v-if="hasPermission('service_orders.edit') && !['Completado', 'Facturado'].includes(order.status)" 
                type="primary" class="bg-indigo-600" @click="showProductModal = true">
                <template #icon><n-icon><AddOutline /></n-icon></template>
                Asignar Material Extra
            </n-button>
        </div>

        <div class="overflow-x-auto rounded-xl border border-gray-200">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider rounded-tl-xl">Producto / SKU</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider w-32">Cant. Asignada</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider w-32">Cant. Usada Real</th>
                        <th v-if="can_view_financials" class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider w-32">Precio Ref.</th>
                        <th v-if="can_view_financials" class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider w-32">Subtotal</th>
                        <th v-if="!['Completado', 'Facturado'].includes(order.status)" class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider rounded-tr-xl w-24">Acción</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-100">
                    <!-- Se agregó el operador '?' (item.product?.name) para prevenir crasheos si un producto fue borrado de la BD -->
                    <tr v-for="item in sortedItems" :key="item.id" class="hover:bg-gray-50 transition-colors">
                        <td class="px-4 py-3 text-sm text-gray-800 font-medium">
                            <div class="flex items-center gap-2">
                                <div class="w-8 h-8 rounded bg-gray-100 flex items-center justify-center text-gray-400">
                                    <n-icon size="16"><SwapVerticalOutline/></n-icon>
                                </div>
                                <div>
                                    <p class="font-bold text-gray-800">{{ item.product?.name || 'Producto Eliminado' }}</p>
                                    <p class="text-xs text-gray-500">{{ item.product?.sku || 'N/A' }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <n-tag type="info" size="small" font-bold>{{ item.quantity }}</n-tag>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <n-tag v-if="item.used_quantity !== null" :type="item.used_quantity === item.quantity ? 'success' : 'warning'" size="small" font-bold>
                                {{ item.used_quantity }}
                            </n-tag>
                            <span v-else class="text-xs text-gray-400 italic">Pendiente</span>
                        </td>
                        <td v-if="can_view_financials" class="px-4 py-3 text-sm text-gray-600 text-right">
                            {{ formatCurrency(item.product?.purchase_price || 0) }}
                        </td>
                        <td v-if="can_view_financials" class="px-4 py-3 text-sm font-bold text-gray-800 text-right">
                            {{ formatCurrency((item.product?.purchase_price || 0) * (item.used_quantity !== null ? item.used_quantity : item.quantity)) }}
                        </td>
                        <td v-if="!['Completado', 'Facturado'].includes(order.status)" class="px-4 py-3 text-right">
                            <n-popconfirm @positive-click="removeProduct(item.id)" positive-text="Sí, quitar" negative-text="Cancelar">
                                <template #trigger>
                                    <n-button circle quaternary type="error" size="small">
                                        <template #icon><n-icon><TrashOutline /></n-icon></template>
                                    </n-button>
                                </template>
                                ¿Quitar este material de la orden?
                            </n-popconfirm>
                        </td>
                    </tr>
                    <tr v-if="sortedItems.length === 0">
                        <td :colspan="can_view_financials ? 6 : 4" class="px-4 py-8 text-center text-gray-500">
                            No hay productos asignados a esta orden.
                        </td>
                    </tr>
                </tbody>
                <tfoot v-if="sortedItems.length > 0 && can_view_financials" class="bg-gray-50 font-bold border-t border-gray-200">
                    <tr>
                        <td colspan="4" class="px-4 py-3 text-right text-gray-700">Costo Total Materiales (Ref.):</td>
                        <td class="px-4 py-3 text-right text-indigo-700">
                            <!-- Se blindó el cálculo total con ?. para no crashear -->
                            {{ formatCurrency(order.items?.reduce((sum, i) => sum + ((i.product?.purchase_price || 0) * (i.used_quantity !== null ? i.used_quantity : i.quantity)), 0) || 0) }}
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
                    <n-button type="primary" @click="addProduct" :loading="productForm.processing" :disabled="!productForm.product_id" class="bg-indigo-600">
                        Asignar y Descontar
                    </n-button>
                </div>
            </n-form>
        </n-modal>
    </div>
</template>