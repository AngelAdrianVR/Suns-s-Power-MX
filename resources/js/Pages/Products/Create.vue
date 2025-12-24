<script setup>
import { ref } from 'vue';
import { useForm, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { 
    NForm, NFormItem, NInput, NInputNumber, NSelect, NButton, NCard, NUpload, NIcon, NGrid, NGridItem, createDiscreteApi 
} from 'naive-ui';
import { 
    SaveOutline, ArrowBackOutline, ImageOutline, CubeOutline, LocationOutline
} from '@vicons/ionicons5';

const props = defineProps({
    categories: Array
});

const { notification } = createDiscreteApi(['notification']);
const formRef = ref(null);

const categoryOptions = props.categories.map(cat => ({
    label: cat.name,
    value: cat.id
}));

const form = useForm({
    name: '',
    sku: '',
    category_id: null,
    purchase_price: 0,
    sale_price: 0,
    initial_stock: 0,
    location: '', // Valor por defecto
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

                                    <n-grid-item>
                                        <n-form-item 
                                            label="Categoría" 
                                            path="category_id"
                                            :validation-status="form.errors.category_id ? 'error' : undefined"
                                            :feedback="form.errors.category_id"
                                        >
                                            <n-select 
                                                v-model:value="form.category_id" 
                                                :options="categoryOptions" 
                                                placeholder="Seleccionar..."
                                                filterable
                                            />
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
                                
                                <p class="text-xs text-indigo-600 mb-4">
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
    </AppLayout>
</template>