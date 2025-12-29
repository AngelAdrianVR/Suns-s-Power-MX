<script setup>
import { ref } from 'vue';
import { useForm, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { 
    NForm, NFormItem, NInput, NButton, NCard, NIcon, NGrid, NGridItem, createDiscreteApi 
} from 'naive-ui';
import { 
    SaveOutline, ArrowBackOutline, PersonOutline, MailOutline, CallOutline, 
    BusinessOutline, LocationOutline, DocumentTextOutline, ReceiptOutline
} from '@vicons/ionicons5';

const props = defineProps({
    client: Object
});

const { notification } = createDiscreteApi(['notification']);
const formRef = ref(null);

// Inicializar formulario con datos existentes
const form = useForm({
    name: props.client.name,
    contact_person: props.client.contact_person,
    tax_id: props.client.tax_id,
    email: props.client.email,
    phone: props.client.phone,
    address: props.client.address,
    notes: props.client.notes,
});

// Reglas de validación (Mismas que en Create)
const rules = {
    name: { 
        required: true, 
        message: 'El nombre o razón social es obligatorio', 
        trigger: 'blur' 
    },
    email: { 
        type: 'email', 
        message: 'Ingresa un correo válido', 
        trigger: ['blur', 'input'] 
    }
};

const submit = () => {
    formRef.value?.validate((errors) => {
        if (!errors) {
            form.put(route('clients.update', props.client.id), {
                onSuccess: () => {
                    notification.success({
                        title: 'Cliente Actualizado',
                        content: 'La información del expediente se ha guardado correctamente.',
                        duration: 3000
                    });
                },
                onError: () => {
                    notification.error({
                        title: 'Error de Validación',
                        content: 'No se pudieron guardar los cambios. Revisa los campos marcados.',
                        duration: 4000
                    });
                }
            });
        } else {
            notification.warning({
                title: 'Campos Requeridos',
                content: 'Por favor corrige los errores antes de guardar.',
                duration: 3000
            });
        }
    });
};
</script>

<template>
    <AppLayout :title="`Editar: ${client.name}`">
        <template #header>
            <div class="flex items-center gap-4">
                <!-- Botón regresar al SHOW del cliente, no al index -->
                <Link :href="route('clients.show', client.id)">
                    <n-button circle secondary type="default">
                        <template #icon><n-icon><ArrowBackOutline /></n-icon></template>
                    </n-button>
                </Link>
                <div>
                    <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                        Editar Cliente
                    </h2>
                    <p class="text-xs text-gray-500 mt-0.5">Modificando expediente: {{ client.name }}</p>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
                
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
                            
                            <!-- Tarjeta 1: Identidad -->
                            <n-card :bordered="false" class="shadow-sm rounded-2xl">
                                <template #header>
                                    <span class="text-gray-600 font-semibold flex items-center gap-2">
                                        <n-icon :component="PersonOutline" /> Identidad del Cliente
                                    </span>
                                </template>

                                <n-grid x-gap="12" :cols="1">
                                    <n-grid-item>
                                        <n-form-item 
                                            label="Nombre Completo / Razón Social" 
                                            path="name"
                                            :validation-status="form.errors.name ? 'error' : undefined"
                                            :feedback="form.errors.name"
                                        >
                                            <n-input 
                                                v-model:value="form.name" 
                                                placeholder="Ej. Juan Pérez o Energías Renovables S.A." 
                                                class="font-semibold"
                                            >
                                                <template #prefix>
                                                    <n-icon :component="BusinessOutline" class="text-gray-400"/>
                                                </template>
                                            </n-input>
                                        </n-form-item>
                                    </n-grid-item>

                                    <n-grid-item>
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            <n-form-item 
                                                label="RFC (Tax ID)" 
                                                path="tax_id"
                                                :validation-status="form.errors.tax_id ? 'error' : undefined"
                                                :feedback="form.errors.tax_id"
                                            >
                                                <n-input 
                                                    v-model:value="form.tax_id" 
                                                    placeholder="XAXX010101000" 
                                                    class="uppercase"
                                                >
                                                    <template #prefix>
                                                        <n-icon :component="ReceiptOutline" class="text-gray-400"/>
                                                    </template>
                                                </n-input>
                                            </n-form-item>

                                            <n-form-item 
                                                label="Persona de Contacto (Opcional)" 
                                                path="contact_person"
                                            >
                                                <n-input 
                                                    v-model:value="form.contact_person" 
                                                    placeholder="Si es empresa, ¿quién atiende?" 
                                                >
                                                    <template #prefix>
                                                        <n-icon :component="PersonOutline" class="text-gray-400"/>
                                                    </template>
                                                </n-input>
                                            </n-form-item>
                                        </div>
                                    </n-grid-item>
                                </n-grid>
                            </n-card>

                            <!-- Tarjeta 2: Ubicación -->
                            <n-card :bordered="false" class="shadow-sm rounded-2xl">
                                <template #header>
                                    <span class="text-gray-600 font-semibold flex items-center gap-2">
                                        <n-icon :component="LocationOutline" /> Ubicación Principal
                                    </span>
                                </template>
                                <n-form-item 
                                    label="Dirección (Calle, Número, Colonia, CP)" 
                                    path="address"
                                >
                                    <n-input 
                                        v-model:value="form.address" 
                                        type="textarea"
                                        placeholder="Dirección fiscal o del sitio principal de instalación"
                                        :autosize="{ minRows: 2, maxRows: 4 }"
                                    />
                                </n-form-item>
                            </n-card>
                        </div>

                        <!-- Columna Derecha: Contacto y Acciones -->
                        <div class="space-y-6">
                            
                            <!-- Tarjeta Contacto -->
                            <n-card :bordered="false" class="shadow-sm rounded-2xl bg-blue-50/50">
                                <template #header>
                                    <span class="text-blue-800 font-semibold flex items-center gap-2">
                                        <n-icon :component="CallOutline"/> Medios de Contacto
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
                                                placeholder="cliente@email.com"
                                            >
                                                <template #prefix>
                                                    <n-icon :component="MailOutline" />
                                                </template>
                                            </n-input>
                                        </n-form-item>
                                    </n-grid-item>

                                    <n-grid-item>
                                        <n-form-item 
                                            label="Teléfono / Celular" 
                                            path="phone"
                                        >
                                            <n-input 
                                                v-model:value="form.phone" 
                                                placeholder="(55) 0000 0000" 
                                            >
                                                <template #prefix>
                                                    <n-icon :component="CallOutline" />
                                                </template>
                                            </n-input>
                                        </n-form-item>
                                    </n-grid-item>
                                </n-grid>
                            </n-card>

                            <!-- Tarjeta Notas -->
                            <n-card :bordered="false" class="shadow-sm rounded-2xl">
                                <template #header>
                                    <span class="text-gray-600 font-semibold flex items-center gap-2 text-sm">
                                        <n-icon :component="DocumentTextOutline"/> Notas Internas
                                    </span>
                                </template>
                                <n-input 
                                    v-model:value="form.notes" 
                                    type="textarea" 
                                    placeholder="Referencias, condiciones especiales, etc."
                                    :autosize="{ minRows: 2 }"
                                />
                            </n-card>

                            <!-- Acciones -->
                            <div class="flex flex-col gap-3 sticky top-6">
                                <n-button 
                                    type="primary" 
                                    size="large" 
                                    block 
                                    @click="submit" 
                                    :loading="form.processing"
                                    :disabled="form.processing"
                                    class="shadow-md hover:shadow-lg transition-shadow"
                                >
                                    <template #icon><n-icon><SaveOutline /></n-icon></template>
                                    Actualizar Cliente
                                </n-button>
                                
                                <Link :href="route('clients.show', client.id)" class="w-full">
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