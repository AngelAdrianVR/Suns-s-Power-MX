<script setup>
import { ref, computed } from 'vue'; // Agregamos computed
import { useForm, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { 
    NForm, NFormItem, NInput, NInputNumber, NSelect, NButton, NCard, NUpload, NIcon, NGrid, NGridItem, 
    createDiscreteApi, NModal, NList, NListItem, NThing, NPopconfirm, NInputGroup 
} from 'naive-ui';
import { 
    SaveOutline, ArrowBackOutline, ImageOutline, CubeOutline, LocationOutline, AlertCircleOutline,
    AddOutline, TrashOutline, ListOutline
} from '@vicons/ionicons5';

const props = defineProps({
    categories: Array
});

const { notification } = createDiscreteApi(['notification']);
const formRef = ref(null);

// --- GESTIÓN DE CATEGORÍAS (Lógica Nueva) ---
const showCategoryModal = ref(false);
const categoryForm = useForm({
    name: ''
});

// Convertimos a computed para que se actualice automáticamente al agregar/borrar categorías
const categoryOptions = computed(() => {
    return props.categories.map(cat => ({
        label: cat.name,
        value: cat.id
    }));
});

const createCategory = () => {
    categoryForm.post(route('categories.store'), {
        preserveScroll: true,
        preserveState: true, // Mantiene los datos del formulario de producto
        onSuccess: () => {
            notification.success({ title: 'Éxito', content: 'Categoría agregada', duration: 2000 });
            categoryForm.reset();
        },
        onError: () => {
            notification.error({ title: 'Error', content: 'No se pudo crear la categoría' });
        }
    });
};

const deleteCategory = (id) => {
    router.delete(route('categories.destroy', id), {
        preserveScroll: true,
        preserveState: true, // Mantiene los datos del formulario de producto
        onSuccess: () => {
            notification.success({ title: 'Éxito', content: 'Categoría eliminada', duration: 2000 });
            // Si la categoría eliminada estaba seleccionada, limpiar el campo
            if (form.category_id === id) {
                form.category_id = null;
            }
        },
        onError: (errors) => {
            // Manejo de errores que vienen del backend (ej. integridad referencial)
            const msg = errors?.error || 'No se pudo eliminar la categoría.';
            notification.error({ title: 'Error', content: msg, duration: 4000 });
        }
    });
};
// --------------------------------------------

const form = useForm({
    name: '',
    sku: '',
    category_id: null,
    purchase_price: 0,
    sale_price: 0,
    initial_stock: 0,
    min_stock_alert: 5, 
    location: '', 
    description: '',
    image: null,
});

const rules = {
    name: { required: true, message: 'El nombre es obligatorio', trigger: 'blur' },
    sku: { required: true, message: 'El SKU es obligatorio', trigger: 'blur' },
    category_id: { required: true, type: 'number', message: 'Selecciona una categoría', trigger: ['blur', 'change'] },
    sale_price: { required: true, type: 'number', message: 'Define un precio de venta', trigger: 'blur' },
    initial_stock: { required: true, type: 'number', message: 'Ingresa el stock inicial', trigger: 'blur' }
};

const handleUploadChange = (data) => {
    if (data.fileList && data.fileList.length > 0) {
        form.image = data.fileList[0].file;
    } else {
        form.image = null;
    }
};

const submit = () => {
    formRef.value?.validate((errors) => {
        if (!errors) {
            form.post(route('products.store'), {
                forceFormData: true,
                onSuccess: () => {
                    notification.success({
                        title: 'Producto Creado',
                        content: 'El producto y su stock inicial se han registrado correctamente.',
                        duration: 3000
                    });
                },
                onError: () => {
                    notification.error({
                        title: 'Error de Validación',
                        content: 'Revisa los campos marcados en rojo.',
                        duration: 4000
                    });
                }
            });
        } else {
            notification.warning({
                title: 'Formulario Incompleto',
                content: 'Por favor completa los campos requeridos.',
                duration: 3000
            });
        }
    });
};
</script>

<template>
    <AppLayout title="Nuevo Producto">
        <template #header>
            <div class="flex items-center gap-4">
                <Link :href="route('products.index')">
                    <n-button circle secondary type="default">
                        <template #icon><n-icon><ArrowBackOutline /></n-icon></template>
                    </n-button>
                </Link>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Registrar Nuevo Producto
                </h2>
            </div>
        </template>

        <div class="py-8">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
                
                <n-form
                    ref="formRef"
                    :model="form"
                    :rules="rules"
                    label-placement="top"
                    require-mark-placement="right-hanging"
                    size="large"
                >
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        
                        <!-- Columna Izquierda: Datos Principales -->
                        <div class="md:col-span-2 space-y-6">
                            
                            <!-- Información General -->
                            <n-card :bordered="false" class="shadow-sm rounded-2xl">
                                <template #header>
                                    <span class="text-gray-600 font-semibold">Información General</span>
                                </template>

                                <n-grid x-gap="12" :cols="2">
                                    <n-grid-item span="2">
                                        <n-form-item 
                                            label="Nombre del Producto" 
                                            path="name"
                                            :validation-status="form.errors.name ? 'error' : undefined"
                                            :feedback="form.errors.name"
                                        >
                                            <n-input v-model:value="form.name" placeholder="Ej. Panel Solar 500W Monocristalino" />
                                        </n-form-item>
                                    </n-grid-item>

                                    <n-grid-item>
                                        <n-form-item 
                                            label="SKU (Código Interno)" 
                                            path="sku"
                                            :validation-status="form.errors.sku ? 'error' : undefined"
                                            :feedback="form.errors.sku"
                                        >
                                            <n-input v-model:value="form.sku" placeholder="Ej. PAN-500-MONO" />
                                        </n-form-item>
                                    </n-grid-item>

                                    <!-- SELECTOR DE CATEGORÍA CON BOTÓN DE GESTIÓN -->
                                    <n-grid-item>
                                        <n-form-item 
                                            label="Categoría" 
                                            path="category_id"
                                            :validation-status="form.errors.category_id ? 'error' : undefined"
                                            :feedback="form.errors.category_id"
                                        >
                                            <n-input-group>
                                                <n-select 
                                                    v-model:value="form.category_id" 
                                                    :options="categoryOptions" 
                                                    placeholder="Seleccionar..."
                                                    filterable
                                                />
                                                <n-button type="primary" secondary @click="showCategoryModal = true">
                                                    <template #icon><n-icon><AddOutline /></n-icon></template>
                                                </n-button>
                                            </n-input-group>
                                        </n-form-item>
                                    </n-grid-item>

                                    <n-grid-item span="2">
                                        <n-form-item 
                                            label="Descripción" 
                                            path="description"
                                            :validation-status="form.errors.description ? 'error' : undefined"
                                            :feedback="form.errors.description"
                                        >
                                            <n-input 
                                                v-model:value="form.description" 
                                                type="textarea" 
                                                placeholder="Detalles técnicos, dimensiones, garantías..."
                                                :autosize="{ minRows: 3, maxRows: 5 }" 
                                            />
                                        </n-form-item>
                                    </n-grid-item>
                                </n-grid>
                            </n-card>

                            <!-- Precios -->
                            <n-card :bordered="false" class="shadow-sm rounded-2xl">
                                <template #header>
                                    <span class="text-gray-600 font-semibold">Precios y Costos</span>
                                </template>
                                <n-grid x-gap="12" :cols="2">
                                    <n-grid-item>
                                        <n-form-item 
                                            label="Precio de Compra" 
                                            path="purchase_price"
                                            :validation-status="form.errors.purchase_price ? 'error' : undefined"
                                            :feedback="form.errors.purchase_price"
                                        >
                                            <n-input-number 
                                                v-model:value="form.purchase_price" 
                                                :show-button="false"
                                                placeholder="0.00"
                                            >
                                                <template #prefix>$</template>
                                            </n-input-number>
                                        </n-form-item>
                                    </n-grid-item>
                                    <n-grid-item>
                                        <n-form-item 
                                            label="Precio de Venta" 
                                            path="sale_price"
                                            :validation-status="form.errors.sale_price ? 'error' : undefined"
                                            :feedback="form.errors.sale_price"
                                        >
                                            <n-input-number 
                                                v-model:value="form.sale_price" 
                                                :show-button="false"
                                                placeholder="0.00"
                                                class="font-bold"
                                            >
                                                <template #prefix>$</template>
                                            </n-input-number>
                                        </n-form-item>
                                    </n-grid-item>
                                </n-grid>
                            </n-card>
                        </div>

                        <!-- Columna Derecha: Imagen y Stock -->
                        <div class="space-y-6">
                            
                            <!-- Nueva Tarjeta: Inventario -->
                            <n-card :bordered="false" class="shadow-sm rounded-2xl bg-indigo-50/50">
                                <template #header>
                                    <span class="text-indigo-800 font-semibold flex items-center gap-2">
                                        <n-icon :component="CubeOutline"/> Inventario Inicial
                                    </span>
                                </template>
                                
                                <p class="text-xs text-indigo-600 mb-2">
                                    Este stock se asignará a tu sucursal actual.
                                </p>

                                <n-grid x-gap="12" :cols="1">
                                    <n-grid-item>
                                        <n-form-item 
                                            label="Cantidad en Almacén" 
                                            path="initial_stock"
                                            :validation-status="form.errors.initial_stock ? 'error' : undefined"
                                            :feedback="form.errors.initial_stock"
                                        >
                                            <n-input-number 
                                                v-model:value="form.initial_stock" 
                                                :min="0"
                                                :precision="0"
                                                button-placement="both"
                                                class="w-full text-center"
                                            />
                                        </n-form-item>
                                    </n-grid-item>

                                    <!-- Alerta de Stock Mínimo -->
                                    <n-grid-item>
                                        <n-form-item 
                                            label="Alerta de Stock Mínimo" 
                                            path="min_stock_alert"
                                            :validation-status="form.errors.min_stock_alert ? 'error' : undefined"
                                            :feedback="form.errors.min_stock_alert"
                                        >
                                            <n-input-number 
                                                v-model:value="form.min_stock_alert" 
                                                :min="1"
                                                :precision="0"
                                                placeholder="Ej. 5"
                                            >
                                                <template #prefix>
                                                    <n-icon :component="AlertCircleOutline" />
                                                </template>
                                            </n-input-number>
                                        </n-form-item>
                                    </n-grid-item>
                                    
                                    <n-grid-item>
                                        <n-form-item 
                                            label="Ubicación Física" 
                                            path="location"
                                            :validation-status="form.errors.location ? 'error' : undefined"
                                            :feedback="form.errors.location"
                                        >
                                            <n-input 
                                                v-model:value="form.location" 
                                                placeholder="Ej. Pasillo 3, Estante B"
                                            >
                                                <template #prefix>
                                                    <n-icon :component="LocationOutline" />
                                                </template>
                                            </n-input>
                                        </n-form-item>
                                    </n-grid-item>
                                </n-grid>
                            </n-card>

                            <n-card :bordered="false" class="shadow-sm rounded-2xl">
                                <template #header>
                                    <span class="text-gray-600 font-semibold">Imagen del Producto</span>
                                </template>
                                
                                <n-form-item :show-label="false" :feedback="form.errors.image" :validation-status="form.errors.image ? 'error' : undefined">
                                    <n-upload
                                        list-type="image-card"
                                        :max="1"
                                        accept="image/png, image/jpeg, image/jpg"
                                        @change="handleUploadChange"
                                        :default-upload="false" 
                                    >
                                        <div class="flex flex-col items-center justify-center text-gray-400">
                                            <n-icon size="30" :component="ImageOutline" />
                                            <span class="text-xs mt-2">Click para subir</span>
                                        </div>
                                    </n-upload>
                                </n-form-item>
                            </n-card>

                            <div class="flex flex-col gap-3">
                                <n-button 
                                    type="primary" 
                                    size="large" 
                                    block 
                                    @click="submit" 
                                    :loading="form.processing"
                                    :disabled="form.processing"
                                >
                                    <template #icon><n-icon><SaveOutline /></n-icon></template>
                                    Guardar Producto
                                </n-button>
                                
                                <Link :href="route('products.index')" class="w-full">
                                    <n-button block ghost type="error">
                                        Cancelar
                                    </n-button>
                                </Link>
                            </div>
                        </div>

                    </div>
                </n-form>
            </div>
        </div>

        <!-- MODAL GESTIÓN DE CATEGORÍAS -->
        <n-modal v-model:show="showCategoryModal">
            <n-card
                style="width: 500px"
                title="Gestionar Categorías"
                :bordered="false"
                size="huge"
                role="dialog"
                aria-modal="true"
            >
                <template #header-extra>
                    <n-icon size="24"><ListOutline /></n-icon>
                </template>

                <div class="space-y-4">
                    <!-- Formulario de Creación -->
                    <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                        <n-input-group>
                            <n-input 
                                v-model:value="categoryForm.name" 
                                placeholder="Nueva Categoría (Ej. Inversores)"
                                @keydown.enter.prevent="createCategory"
                            />
                            <n-button type="primary" @click="createCategory" :loading="categoryForm.processing" :disabled="!categoryForm.name">
                                <template #icon><n-icon><AddOutline /></n-icon></template>
                                Agregar
                            </n-button>
                        </n-input-group>
                        <span v-if="categoryForm.errors.name" class="text-red-500 text-xs mt-1 block">
                            {{ categoryForm.errors.name }}
                        </span>
                    </div>

                    <!-- Lista de Categorías Existentes -->
                    <div class="max-h-64 overflow-y-auto pr-2">
                        <n-list hoverable clickable>
                            <n-list-item v-for="cat in props.categories" :key="cat.id">
                                <template #suffix>
                                    <n-popconfirm
                                        @positive-click="deleteCategory(cat.id)"
                                        positive-text="Sí, eliminar"
                                        negative-text="Cancelar"
                                    >
                                        <template #trigger>
                                            <n-button size="tiny" type="error" ghost circle>
                                                <template #icon><n-icon><TrashOutline /></n-icon></template>
                                            </n-button>
                                        </template>
                                        ¿Estás seguro de eliminar "{{ cat.name }}"?
                                    </n-popconfirm>
                                </template>
                                <n-thing :title="cat.name" />
                            </n-list-item>
                            
                            <div v-if="props.categories.length === 0" class="text-center text-gray-400 py-4">
                                No hay categorías registradas.
                            </div>
                        </n-list>
                    </div>
                </div>

                <template #footer>
                    <div class="flex justify-end">
                        <n-button @click="showCategoryModal = false">Cerrar</n-button>
                    </div>
                </template>
            </n-card>
        </n-modal>

    </AppLayout>
</template>