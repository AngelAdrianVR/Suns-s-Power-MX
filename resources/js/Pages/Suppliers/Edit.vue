<script setup>
import { ref } from 'vue';
import { useForm, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { 
    NForm, NFormItem, NInput, NButton, NCard, NIcon, NGrid, NGridItem, createDiscreteApi 
} from 'naive-ui';
import { 
    SaveOutline, ArrowBackOutline, StorefrontOutline, PersonOutline, MailOutline, CallOutline
} from '@vicons/ionicons5';

const props = defineProps({
    supplier: {
        type: Object,
        required: true
    }
});

const { notification } = createDiscreteApi(['notification']);
const formRef = ref(null);

// Inicializamos el formulario con los datos recibidos del prop 'supplier'
const form = useForm({
    company_name: props.supplier.company_name || '',
    contact_name: props.supplier.contact_name || '',
    email: props.supplier.email || '',
    phone: props.supplier.phone || '',
});

// Mismas reglas de validación
const rules = {
    company_name: { 
        required: true, 
        message: 'El nombre de la empresa es obligatorio', 
        trigger: 'blur' 
    },
    email: { 
        type: 'email', 
        message: 'Ingresa un correo electrónico válido', 
        trigger: ['blur', 'input'] 
    }
};

const submit = () => {
    formRef.value?.validate((errors) => {
        if (!errors) {
            // Usamos PUT para actualizar y pasamos el ID del proveedor
            form.put(route('suppliers.update', props.supplier.id), {
                onSuccess: () => {
                    notification.success({
                        title: 'Proveedor Actualizado',
                        content: 'La información del proveedor ha sido guardada correctamente.',
                        duration: 3000
                    });
                },
                onError: () => {
                    notification.error({
                        title: 'Error al Actualizar',
                        content: 'Por favor revisa los campos marcados.',
                        duration: 4000
                    });
                }
            });
        } else {
            notification.warning({
                title: 'Formulario Incompleto',
                content: 'Completa los campos requeridos para continuar.',
                duration: 3000
            });
        }
    });
};
</script>

<template>
    <AppLayout title="Editar Proveedor">
        <template #header>
            <div class="flex items-center gap-4">
                <Link :href="route('suppliers.index')">
                    <n-button circle secondary type="default">
                        <template #icon><n-icon><ArrowBackOutline /></n-icon></template>
                    </n-button>
                </Link>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Editar Proveedor: {{ props.supplier.company_name }}
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
                        
                        <!-- Columna Izquierda: Identidad -->
                        <div class="md:col-span-2 space-y-6">
                            <n-card :bordered="false" class="shadow-sm rounded-2xl">
                                <template #header>
                                    <span class="text-gray-600 font-semibold flex items-center gap-2">
                                        <n-icon :component="StorefrontOutline" /> Identidad Comercial
                                    </span>
                                </template>

                                <n-grid x-gap="12" :cols="1">
                                    <n-grid-item>
                                        <n-form-item 
                                            label="Razón Social / Nombre de la Empresa" 
                                            path="company_name"
                                            :validation-status="form.errors.company_name ? 'error' : undefined"
                                            :feedback="form.errors.company_name"
                                        >
                                            <n-input 
                                                v-model:value="form.company_name" 
                                                placeholder="Ej. Solar Tech Distributions S.A. de C.V." 
                                                class="font-semibold"
                                            >
                                                <template #prefix>
                                                    <n-icon :component="StorefrontOutline" class="text-gray-400"/>
                                                </template>
                                            </n-input>
                                        </n-form-item>
                                    </n-grid-item>

                                    <n-grid-item>
                                        <n-form-item 
                                            label="Nombre del Contacto / Representante" 
                                            path="contact_name"
                                            :validation-status="form.errors.contact_name ? 'error' : undefined"
                                            :feedback="form.errors.contact_name"
                                        >
                                            <n-input 
                                                v-model:value="form.contact_name" 
                                                placeholder="Ej. Ing. Roberto Gómez" 
                                            >
                                                <template #prefix>
                                                    <n-icon :component="PersonOutline" class="text-gray-400"/>
                                                </template>
                                            </n-input>
                                        </n-form-item>
                                    </n-grid-item>
                                </n-grid>
                            </n-card>
                        </div>

                        <!-- Columna Derecha: Contacto y Acciones -->
                        <div class="space-y-6">
                            
                            <n-card :bordered="false" class="shadow-sm rounded-2xl bg-blue-50/50">
                                <template #header>
                                    <span class="text-blue-800 font-semibold flex items-center gap-2">
                                        <n-icon :component="CallOutline"/> Datos de Contacto
                                    </span>
                                </template>
                                
                                <n-grid x-gap="12" :cols="1">
                                    <n-grid-item>
                                        <n-form-item 
                                            label="Correo Electrónico" 
                                            path="email"
                                            :validation-status="form.errors.email ? 'error' : undefined"
                                            :feedback="form.errors.email"
                                        >
                                            <n-input 
                                                v-model:value="form.email" 
                                                placeholder="ventas@proveedor.com"
                                            >
                                                <template #prefix>
                                                    <n-icon :component="MailOutline" />
                                                </template>
                                            </n-input>
                                        </n-form-item>
                                    </n-grid-item>

                                    <n-grid-item>
                                        <n-form-item 
                                            label="Teléfono / WhatsApp" 
                                            path="phone"
                                            :validation-status="form.errors.phone ? 'error' : undefined"
                                            :feedback="form.errors.phone"
                                        >
                                            <n-input 
                                                v-model:value="form.phone" 
                                                placeholder="(55) 1234 5678" 
                                            >
                                                <template #prefix>
                                                    <n-icon :component="CallOutline" />
                                                </template>
                                            </n-input>
                                        </n-form-item>
                                    </n-grid-item>
                                </n-grid>
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
                                    Actualizar Proveedor
                                </n-button>
                                
                                <Link :href="route('suppliers.index')" class="w-full">
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