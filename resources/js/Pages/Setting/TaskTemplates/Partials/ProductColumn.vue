<script setup>
import { ref } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import axios from 'axios';
import { 
    NButton, NCard, NIcon, NTag, NModal, NForm, NFormItem, NInputNumber, NSelect, createDiscreteApi, NPopconfirm, NEmpty
} from 'naive-ui';
import { 
    AddOutline, CreateOutline, TrashOutline, CubeOutline, MenuOutline
} from '@vicons/ionicons5';

const props = defineProps({
    sys: Object
});

const emit = defineEmits(['sync']);
const { notification } = createDiscreteApi(['notification']);

const showProductModal = ref(false);
const searchingProducts = ref(false);
const productSearchOptions = ref([]);
const isEditingProduct = ref(false);

const productForm = useForm({
    system_type_id: null,
    product_id: null,
    quantity: 1
});

const openAddProductModal = () => {
    if (typeof props.sys.id === 'string') {
        notification.warning({ title: 'Atención', content: 'Aún no migraste a base de datos. Completa el backend primero.', duration: 5000 });
        return;
    }
    isEditingProduct.value = false;
    productForm.reset();
    productForm.system_type_id = props.sys.id;
    productForm.quantity = 1;
    productSearchOptions.value = [];
    showProductModal.value = true;
    
    handleSearchProduct('');
};

const openEditProductModal = (prod) => {
    isEditingProduct.value = true;
    productForm.reset();
    productForm.system_type_id = props.sys.id;
    productForm.product_id = prod.id;
    productForm.quantity = Number(prod.pivot.quantity);
    
    productSearchOptions.value = [{
        label: `${prod.sku} - ${prod.name}`,
        value: prod.id
    }];

    showProductModal.value = true;
};

const handleSearchProduct = async (query = '') => {
    searchingProducts.value = true;
    try {
        const response = await axios.get('/products/search', {
            params: { query: query }
        });
        
        productSearchOptions.value = response.data.map(p => ({
            label: `${p.sku} - ${p.name}`,
            value: p.value
        }));
    } catch (error) {
        console.error("Error buscando productos:", error);
        notification.error({ 
            title: 'Error de búsqueda', 
            content: 'No se pudieron cargar los productos. Verifica que la ruta exista en web.php.', duration: 3000
        });
    } finally {
        searchingProducts.value = false;
    }
};

const handleProductSubmit = () => {
    if (isEditingProduct.value) {
        productForm.put(route('system-type-products.update', { system_type: productForm.system_type_id, product: productForm.product_id }), {
            preserveScroll: true,
            onSuccess: () => {
                showProductModal.value = false;
                notification.success({ title: 'Actualizado', content: 'Cantidad de material actualizada.', duration: 3000 });
                emit('sync');
            }
        });
    } else {
        productForm.post(route('system-type-products.store'), {
            preserveScroll: true,
            onSuccess: () => {
                showProductModal.value = false;
                notification.success({ title: 'Agregado', content: 'Producto predeterminado asignado.', duration: 3000 });
                emit('sync');
            }
        });
    }
};

const handleDeleteProduct = (productId) => {
    router.delete(route('system-type-products.destroy', { system_type: props.sys.id, product: productId }), {
        preserveScroll: true,
        onSuccess: () => { 
            notification.success({ title: 'Removido', content: 'Producto predeterminado quitado.', duration: 3000 }); 
            emit('sync');
        }
    });
};

// ================= DRAG & DROP PRODUCTOS =================
const draggedProductIndex = ref(null);
const onDragStartProduct = (index) => { draggedProductIndex.value = index; };
const onDropProduct = (dropIndex) => {
    if (draggedProductIndex.value === null || draggedProductIndex.value === dropIndex) return;
    
    let currentProducts = [...props.sys.products];
    const draggedItem = currentProducts.splice(draggedProductIndex.value, 1)[0];
    currentProducts.splice(dropIndex, 0, draggedItem);
    
    const updatedItems = currentProducts.map((item, i) => ({ id: item.id, order: i + 1 }));

    router.post(route('system-type-products.reorder', { system_type: props.sys.id }), { items: updatedItems }, {
        preserveScroll: true,
        onSuccess: () => { 
            notification.success({ title: 'Orden actualizado', content: 'Se guardó el nuevo orden del material.', duration: 3000 }); 
            emit('sync');
        }
    });

    draggedProductIndex.value = null;
};
</script>

<template>
    <div>
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-bold text-gray-700 flex items-center gap-2">
                <n-icon class="text-blue-500"><CubeOutline/></n-icon> Material Requerido
            </h3>
            <n-button type="primary" size="small" class="bg-blue-600" @click="openAddProductModal">
                <template #icon><n-icon><AddOutline /></n-icon></template> Agregar
            </n-button>
        </div>

        <div v-if="sys.products?.length > 0" class="space-y-3">
            <div v-for="(prod, index) in sys.products" :key="prod.id" draggable="true" @dragstart="onDragStartProduct(index)" @dragover.prevent @drop="onDropProduct(index)">
                <n-card size="small" class="rounded-xl shadow-sm border border-blue-100 bg-blue-50/20 hover:shadow-md transition-shadow cursor-move">
                    <div class="flex justify-between items-center gap-3">
                        <div class="text-gray-400 flex items-center">
                            <n-icon size="20"><MenuOutline /></n-icon>
                        </div>
                        
                        <div class="flex-1">
                            <div class="flex items-center gap-2 mb-1">
                                <h4 class="font-bold text-blue-800 text-sm">{{ prod.name }}</h4>
                                <n-tag type="info" size="tiny" round>Cant: {{ prod.pivot.quantity }}</n-tag>
                            </div>
                            <p class="text-xs text-gray-600">SKU: {{ prod.sku }}</p>
                        </div>

                        <div class="flex gap-1">
                            <n-button circle quaternary size="small" type="warning" @click="openEditProductModal(prod)">
                                <template #icon><n-icon><CreateOutline /></n-icon></template>
                            </n-button>
                            <n-popconfirm @positive-click="handleDeleteProduct(prod.id)" positive-text="Sí, quitar" negative-text="No">
                                <template #trigger>
                                    <n-button circle quaternary size="small" type="error">
                                        <template #icon><n-icon><TrashOutline /></n-icon></template>
                                    </n-button>
                                </template>
                                ¿Quitar producto de este tipo de sistema?
                            </n-popconfirm>
                        </div>
                    </div>
                </n-card>
            </div>
        </div>
        <n-empty v-else description="Sin material predeterminado." class="py-8" />

        <!-- MODAL PRODUCTOS PREDETERMINADOS -->
        <n-modal v-model:show="showProductModal" preset="card" class="max-w-md" :title="isEditingProduct ? 'Editar Cantidad de Material' : 'Agregar Material Predeterminado'">
            <n-form :model="productForm" @submit.prevent="handleProductSubmit">
                <n-form-item label="Producto / Material" path="product_id">
                    <n-select
                        v-model:value="productForm.product_id"
                        filterable
                        remote
                        :options="productSearchOptions"
                        :loading="searchingProducts"
                        @search="handleSearchProduct"
                        placeholder="Escribe el nombre o SKU..."
                        clearable
                        :disabled="isEditingProduct"
                    />
                </n-form-item>
                
                <n-form-item label="Cantidad predeterminada" path="quantity">
                    <n-input-number v-model:value="productForm.quantity" :min="0.01" :step="1" class="w-full" placeholder="Ej. 5" />
                </n-form-item>
                
                <div class="flex justify-end gap-3 mt-4">
                    <n-button @click="showProductModal = false">Cancelar</n-button>
                    <n-button type="primary" attr-type="submit" :loading="productForm.processing" :disabled="!productForm.product_id" class="bg-blue-600">
                        {{ isEditingProduct ? 'Guardar Cambios' : 'Guardar Material' }}
                    </n-button>
                </div>
            </n-form>
        </n-modal>
    </div>
</template>