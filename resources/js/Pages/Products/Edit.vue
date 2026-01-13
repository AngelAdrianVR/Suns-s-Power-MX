<script setup>
import { ref, computed } from 'vue';
import { usePermissions } from '@/Composables/usePermissions';
import { useForm, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { 
    NForm, NFormItem, NInput, NInputNumber, NSelect, NButton, NCard, NUpload, NUploadDragger, NIcon, NGrid, NGridItem, 
    createDiscreteApi, NAvatar, NModal, NList, NListItem, NThing, NPopconfirm, NInputGroup, NText, NP, NEmpty
} from 'naive-ui';
import { 
    SaveOutline, ArrowBackOutline, ImageOutline, CubeOutline, CloudUploadOutline, LocationOutline, AlertCircleOutline,
    AddOutline, TrashOutline, ListOutline, AttachOutline, CloudDownloadOutline, DocumentTextOutline
} from '@vicons/ionicons5';

const props = defineProps({
    product: Object,
    categories: Array
});

const { hasPermission } = usePermissions();

const { notification } = createDiscreteApi(['notification']);
const formRef = ref(null);

// --- GESTIÓN DE CATEGORÍAS ---
const showCategoryModal = ref(false);
const categoryForm = useForm({
    name: ''
});

const categoryOptions = computed(() => {
    return props.categories.map(cat => ({
        label: cat.name,
        value: cat.id
    }));
});

const createCategory = () => {
    categoryForm.post(route('categories.store'), {
        preserveScroll: true,
        preserveState: true, 
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
        preserveState: true,
        onSuccess: () => {
            notification.success({ title: 'Éxito', content: 'Categoría eliminada', duration: 2000 });
            if (form.category_id === id) {
                form.category_id = null;
            }
        },
        onError: (errors) => {
            const msg = errors?.error || 'No se pudo eliminar la categoría.';
            notification.error({ title: 'Error', content: msg, duration: 4000 });
        }
    });
};

// --- GESTIÓN DE PRODUCTO ---

const form = useForm({
    _method: 'PUT',
    name: props.product.name,
    sku: props.product.sku,
    category_id: props.product.category_id,
    purchase_price: Number(props.product.purchase_price),
    sale_price: Number(props.product.sale_price),
    current_stock: Number(props.product.current_stock),
    min_stock_alert: Number(props.product.min_stock_alert ?? 5), 
    location: props.product.location, 
    description: props.product.description,
    image: null,
    attachments: [], // Array para nuevos archivos
});

const rules = {
    name: { required: true, message: 'El nombre es obligatorio', trigger: 'blur' },
    sku: { required: true, message: 'El SKU es obligatorio', trigger: 'blur' },
    category_id: { required: true, type: 'number', message: 'Selecciona una categoría', trigger: ['blur', 'change'] },
    sale_price: { required: true, type: 'number', message: 'Define un precio de venta', trigger: 'blur' },
    current_stock: { required: true, type: 'number', message: 'Ingresa el stock actual', trigger: 'blur' },
    min_stock_alert: { required: true, type: 'number', message: 'Define el mínimo para alertas', trigger: 'blur' }
};

// Manejo de imagen principal
const handleUploadChange = (data) => {
    if (data.fileList && data.fileList.length > 0) {
        form.image = data.fileList[0].file;
    } else {
        form.image = null;
    }
};

// Manejo de nuevos adjuntos (igual que en Create.vue)
const handleAttachmentsChange = (data) => {
    form.attachments = data.fileList.map(item => item.file).filter(f => f);
};

// Eliminar archivo existente (igual que en Show.vue)
const deleteExistingFile = (fileId) => {
    router.delete(route('media.delete-file', fileId), {
        preserveScroll: true,
        onSuccess: () => {
            notification.success({
                title: 'Archivo Eliminado',
                content: 'El archivo adjunto se ha eliminado correctamente.',
                duration: 3000
            });
        },
        onError: () => {
            notification.error({
                title: 'Error',
                content: 'No se pudo eliminar el archivo.',
                duration: 4000
            });
        }
    });
};

const getFileIcon = (fileName) => {
    const ext = fileName.split('.').pop().toLowerCase();
    if (['jpg', 'jpeg', 'png', 'gif'].includes(ext)) return ImageOutline;
    if (['pdf', 'doc', 'docx', 'txt'].includes(ext)) return DocumentTextOutline;
    return AttachOutline;
};

const submit = () => {
    formRef.value?.validate((errors) => {
        if (!errors) {
            form.post(route('products.update', props.product.id), {
                forceFormData: true,
                onSuccess: () => {
                    notification.success({
                        title: 'Producto Actualizado',
                        content: 'Los cambios se han guardado correctamente.',
                        duration: 3000
                    });
                },
                onError: () => {
                    notification.error({
                        title: 'Error al Guardar',
                        content: 'Revisa los campos marcados en rojo.',
                        duration: 4000
                    });
                }
            });
        } else {
            notification.warning({
                title: 'Formulario Incompleto',
                content: 'Por favor corrige los errores antes de continuar.',
                duration: 3000
            });
        }
    });
};
</script>

<template>
    <AppLayout :title="`Editar: ${product.name}`">
        <template #header>
            <div class="flex items-center gap-4">
                <Link :href="route('products.index')">
                    <n-button circle secondary type="default">
                        <template #icon><n-icon><ArrowBackOutline /></n-icon></template>
                    </n-button>
                </Link>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Editar Producto
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
                                            <n-input v-model:value="form.name" placeholder="Nombre del producto" />
                                        </n-form-item>
                                    </n-grid-item>

                                    <n-grid-item>
                                        <n-form-item 
                                            label="SKU" 
                                            path="sku"
                                            :validation-status="form.errors.sku ? 'error' : undefined"
                                            :feedback="form.errors.sku"
                                        >
                                            <n-input v-model:value="form.sku" placeholder="Código interno" />
                                        </n-form-item>
                                    </n-grid-item>

                                    <!-- SELECTOR DE CATEGORÍA -->
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
                                                placeholder="Descripción del producto..."
                                                :autosize="{ minRows: 3, maxRows: 5 }" 
                                            />
                                        </n-form-item>
                                    </n-grid-item>
                                </n-grid>
                            </n-card>

                            <n-card :bordered="false" class="shadow-sm rounded-2xl">
                                <template #header>
                                    <span class="text-gray-600 font-semibold">Precios y Costos</span>
                                </template>
                                <n-grid x-gap="12" :cols="2">
                                    <n-grid-item v-if="hasPermission('products.view_costs')">
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

                            <!-- NUEVA SECCIÓN: Documentos y Archivos -->
                            <n-card :bordered="false" class="shadow-sm rounded-2xl">
                                <template #header>
                                    <span class="text-gray-600 font-semibold flex items-center gap-2">
                                        <n-icon :component="AttachOutline" /> Documentos y Archivos Extra
                                    </span>
                                </template>
                                
                                <!-- 1. LISTA DE ARCHIVOS EXISTENTES -->
                                <div v-if="product.attachments && product.attachments.length > 0" class="mb-6">
                                    <p class="text-xs text-gray-500 font-bold mb-2 uppercase">Archivos Actuales</p>
                                    <div class="grid grid-cols-1 gap-3">
                                        <div v-for="file in product.attachments" :key="file.id" 
                                            class="flex items-center gap-3 p-3 rounded-lg border border-gray-200 bg-white hover:shadow-sm transition-shadow">
                                            
                                            <div class="w-8 h-8 rounded bg-gray-100 flex items-center justify-center text-gray-500">
                                                <n-icon size="18">
                                                    <component :is="getFileIcon(file.name)" />
                                                </n-icon>
                                            </div>
                                            
                                            <div class="flex-1 min-w-0">
                                                <a :href="file.url" target="_blank" class="block">
                                                    <p class="text-sm font-medium text-gray-700 truncate hover:text-blue-600 transition-colors">
                                                        {{ file.name }}
                                                    </p>
                                                    <p class="text-xs text-gray-400">{{ file.size }}</p>
                                                </a>
                                            </div>

                                            <div class="flex items-center gap-1">
                                                <a :href="file.url" target="_blank" title="Descargar">
                                                    <n-button size="tiny" circle secondary type="info">
                                                        <template #icon><n-icon><CloudDownloadOutline /></n-icon></template>
                                                    </n-button>
                                                </a>
                                                
                                                <n-popconfirm
                                                    @positive-click="deleteExistingFile(file.id)"
                                                    positive-text="Sí, eliminar"
                                                    negative-text="Cancelar"
                                                >
                                                    <template #trigger>
                                                        <n-button size="tiny" circle secondary type="error">
                                                            <template #icon><n-icon><TrashOutline /></n-icon></template>
                                                        </n-button>
                                                    </template>
                                                    ¿Eliminar este archivo permanentemente?
                                                </n-popconfirm>
                                            </div>
                                        </div>
                                    </div>
                                    <n-divider class="my-6" />
                                </div>

                                <!-- 2. SUBIDA DE NUEVOS ARCHIVOS -->
                                <p class="text-xs text-gray-500 mb-2 font-bold uppercase">Agregar Nuevos</p>
                                <n-form-item :show-label="false" :feedback="form.errors['attachments.0'] || form.errors.attachments" :validation-status="form.errors.attachments ? 'error' : undefined">
                                    <n-upload
                                        multiple
                                        directory-dnd
                                        @change="handleAttachmentsChange"
                                        :default-upload="false"
                                    >
                                        <n-upload-dragger>
                                            <div style="margin-bottom: 12px">
                                                <n-icon size="48" :depth="3">
                                                    <cloud-upload-outline />
                                                </n-icon>
                                            </div>
                                            <n-text style="font-size: 16px">
                                                Arrastra archivos nuevos aquí
                                            </n-text>
                                            <n-p depth="3" style="margin: 8px 0 0 0">
                                                Se sumarán a los existentes
                                            </n-p>
                                        </n-upload-dragger>
                                    </n-upload>
                                </n-form-item>
                            </n-card>
                        </div>

                        <!-- Columna Derecha -->
                        <div class="space-y-6">
                            
                            <!-- Gestión de Inventario -->
                            <n-card :bordered="false" class="shadow-sm rounded-2xl bg-indigo-50/50">
                                <template #header>
                                    <span class="text-indigo-800 font-semibold flex items-center gap-2">
                                        <n-icon :component="CubeOutline"/> Inventario actual
                                    </span>
                                </template>
                                
                                <p class="text-xs text-indigo-600 mb-4">
                                    Ajuste manual del stock para tu sucursal.
                                </p>

                                <n-grid x-gap="12" :cols="1">
                                    <n-grid-item>
                                        <n-form-item 
                                            label="Cantidad en Almacén" 
                                            path="current_stock"
                                            :validation-status="form.errors.current_stock ? 'error' : undefined"
                                            :feedback="form.errors.current_stock"
                                        >
                                            <n-input-number 
                                                v-model:value="form.current_stock" 
                                                :min="0"
                                                :precision="0"
                                                button-placement="both"
                                                class="w-full text-center"
                                                :disabled="!hasPermission('products.adjust_stock')"
                                            />
                                        </n-form-item>
                                    </n-grid-item>

                                    <!-- Agregado: Alerta de Stock Mínimo -->
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

                            <!-- Imagen -->
                            <n-card :bordered="false" class="shadow-sm rounded-2xl">
                                <template #header>
                                    <span class="text-gray-600 font-semibold">Imagen Principal</span>
                                </template>
                                
                                <div class="mb-4 flex justify-center">
                                    <n-avatar
                                        v-if="product.image_url && !form.image"
                                        :size="100"
                                        :src="product.image_url"
                                        class="shadow-md border border-gray-100"
                                        shape="square"
                                    />
                                    <div v-else-if="!form.image" class="text-center text-gray-400 p-4 border-2 border-dashed border-gray-200 rounded-xl w-full">
                                        <span class="text-xs">Sin imagen actual</span>
                                    </div>
                                </div>

                                <n-form-item :show-label="false" :feedback="form.errors.image" :validation-status="form.errors.image ? 'error' : undefined">
                                    <n-upload
                                        list-type="image-card"
                                        :max="1"
                                        accept="image/png, image/jpeg, image/jpg"
                                        @change="handleUploadChange"
                                        :default-upload="false" 
                                    >
                                        <div class="flex flex-col items-center justify-center text-gray-400">
                                            <n-icon size="30" :component="CloudUploadOutline" />
                                            <span class="text-xs mt-2">Cambiar Imagen</span>
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
                                    class="shadow-lg shadow-blue-500/30"
                                >
                                    <template #icon><n-icon><SaveOutline /></n-icon></template>
                                    Actualizar Producto
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