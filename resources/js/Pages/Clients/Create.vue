<script setup>
import { ref } from 'vue';
import { useForm, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { 
    NForm, NFormItem, NInput, NButton, NCard, NIcon, NGrid, NGridItem, createDiscreteApi, NDivider
} from 'naive-ui';
import { 
    SaveOutline, ArrowBackOutline, PersonOutline, MailOutline, CallOutline, 
    BusinessOutline, LocationOutline, DocumentTextOutline, ReceiptOutline, MapOutline
} from '@vicons/ionicons5';

const { notification } = createDiscreteApi(['notification']);
const formRef = ref(null);

// Formulario actualizado con la nueva estructura de BD
const form = useForm({
    name: '',            
    contact_person: '',  
    tax_id: '',          
    
    // Contacto Principal y Secundario
    email: '',
    email_secondary: '',
    phone: '',
    phone_secondary: '',
    
    // Dirección Atomizada
    street: '',
    exterior_number: '',
    interior_number: '',
    neighborhood: '',
    municipality: '',
    state: '',
    zip_code: '',
    country: 'México', // Valor por defecto
    
    notes: '',
});

const rules = {
    name: { 
        required: true, 
        message: 'El nombre o razón social es obligatorio', 
        trigger: 'blur' 
    },
    email: { 
        type: 'email', 
        message: 'Formato de correo inválido', 
        trigger: ['blur', 'input'] 
    },
    email_secondary: { 
        type: 'email', 
        message: 'Formato de correo inválido', 
        trigger: ['blur', 'input'] 
    },
};

const submit = () => {
    formRef.value?.validate((errors) => {
        if (!errors) {
            form.post(route('clients.store'), {
                onSuccess: () => {
                    notification.success({
                        title: 'Cliente Registrado',
                        content: 'El expediente del cliente se ha creado correctamente.',
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
                title: 'Campos Requeridos',
                content: 'Completa la información obligatoria.',
                duration: 3000
            });
        }
    });
};
</script>

<template>
    <AppLayout title="Nuevo Cliente">
        <template #header>
            <div class="flex items-center gap-4">
                <Link :href="route('clients.index')">
                    <n-button circle secondary type="default">
                        <template #icon><n-icon><ArrowBackOutline /></n-icon></template>
                    </n-button>
                </Link>
                <div>
                    <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                        Registrar Nuevo Cliente
                    </h2>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
                
                <n-form
                    ref="formRef"
                    :model="form"
                    :rules="rules"
                    label-placement="top"
                    require-mark-placement="right-hanging"
                    size="medium"
                >
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        
                        <!-- Columna Izquierda: Datos e Identificación -->
                        <div class="lg:col-span-2 space-y-6">
                            
                            <!-- 1. Identidad -->
                            <n-card :bordered="false" class="shadow-sm rounded-2xl">
                                <template #header>
                                    <span class="text-gray-700 font-bold flex items-center gap-2">
                                        <n-icon :component="PersonOutline" class="text-blue-600"/> Identidad del Cliente
                                    </span>
                                </template>

                                <n-grid x-gap="12" :cols="1">
                                    <n-grid-item>
                                        <n-form-item label="Nombre Completo / Razón Social" path="name">
                                            <n-input v-model:value="form.name" placeholder="Ej. Energías Renovables S.A." class="font-semibold text-lg">
                                                <template #prefix><n-icon :component="BusinessOutline"/></template>
                                            </n-input>
                                        </n-form-item>
                                    </n-grid-item>

                                    <n-grid-item>
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            <n-form-item label="RFC (Tax ID)" path="tax_id">
                                                <n-input v-model:value="form.tax_id" placeholder="XAXX010101000" class="uppercase">
                                                    <template #prefix><n-icon :component="ReceiptOutline"/></template>
                                                </n-input>
                                            </n-form-item>

                                            <n-form-item label="Persona de Contacto" path="contact_person">
                                                <n-input v-model:value="form.contact_person" placeholder="Atención a...">
                                                    <template #prefix><n-icon :component="PersonOutline"/></template>
                                                </n-input>
                                            </n-form-item>
                                        </div>
                                    </n-grid-item>
                                </n-grid>
                            </n-card>

                            <!-- 2. Dirección Detallada -->
                            <n-card :bordered="false" class="shadow-sm rounded-2xl">
                                <template #header>
                                    <span class="text-gray-700 font-bold flex items-center gap-2">
                                        <n-icon :component="LocationOutline" class="text-orange-600"/> Dirección Fiscal / Instalación
                                    </span>
                                </template>

                                <n-grid x-gap="12" y-gap="4" cols="1 s:2 m:4" responsive="screen">
                                    
                                    <!-- Calle ocupa 2 columnas en pantallas medianas -->
                                    <n-grid-item span="1 m:2">
                                        <n-form-item label="Calle" path="street">
                                            <n-input v-model:value="form.street" placeholder="Av. Principal" />
                                        </n-form-item>
                                    </n-grid-item>

                                    <n-grid-item>
                                        <n-form-item label="No. Exterior" path="exterior_number">
                                            <n-input v-model:value="form.exterior_number" placeholder="123" />
                                        </n-form-item>
                                    </n-grid-item>

                                    <n-grid-item>
                                        <n-form-item label="No. Interior" path="interior_number">
                                            <n-input v-model:value="form.interior_number" placeholder="Apt 4B" />
                                        </n-form-item>
                                    </n-grid-item>

                                    <!-- Fila 2 -->
                                    <n-grid-item span="1 m:2">
                                        <n-form-item label="Colonia / Barrio" path="neighborhood">
                                            <n-input v-model:value="form.neighborhood" placeholder="Centro" />
                                        </n-form-item>
                                    </n-grid-item>

                                    <n-grid-item>
                                        <n-form-item label="Código Postal" path="zip_code">
                                            <n-input v-model:value="form.zip_code" placeholder="00000" />
                                        </n-form-item>
                                    </n-grid-item>

                                    <n-grid-item>
                                        <n-form-item label="País" path="country">
                                            <n-input v-model:value="form.country" placeholder="México" />
                                        </n-form-item>
                                    </n-grid-item>

                                    <!-- Fila 3 -->
                                    <n-grid-item span="1 m:2">
                                        <n-form-item label="Municipio / Alcaldía" path="municipality">
                                            <n-input v-model:value="form.municipality" placeholder="Delegación..." />
                                        </n-form-item>
                                    </n-grid-item>

                                    <n-grid-item span="1 m:2">
                                        <n-form-item label="Estado / Provincia" path="state">
                                            <n-input v-model:value="form.state" placeholder="Estado..." />
                                        </n-form-item>
                                    </n-grid-item>

                                </n-grid>
                            </n-card>
                        </div>

                        <!-- Columna Derecha: Contacto y Extras -->
                        <div class="space-y-6">
                            
                            <!-- 3. Medios de Contacto -->
                            <n-card :bordered="false" class="shadow-sm rounded-2xl bg-blue-50/60">
                                <template #header>
                                    <span class="text-blue-800 font-bold flex items-center gap-2">
                                        <n-icon :component="CallOutline"/> Medios de Contacto
                                    </span>
                                </template>
                                
                                <div class="space-y-4">
                                    <!-- Contacto 1 -->
                                    <div>
                                        <p class="text-xs font-bold text-gray-500 uppercase mb-2">Principal</p>
                                        <n-grid x-gap="8" :cols="1">
                                            <n-grid-item>
                                                <n-form-item label="Correo Electrónico" path="email">
                                                    <n-input v-model:value="form.email" placeholder="mail@ejemplo.com">
                                                        <template #prefix><n-icon :component="MailOutline" /></template>
                                                    </n-input>
                                                </n-form-item>
                                            </n-grid-item>
                                            <n-grid-item>
                                                <n-form-item label="Teléfono / Móvil" path="phone">
                                                    <n-input v-model:value="form.phone" placeholder="(00) 0000 0000">
                                                        <template #prefix><n-icon :component="CallOutline" /></template>
                                                    </n-input>
                                                </n-form-item>
                                            </n-grid-item>
                                        </n-grid>
                                    </div>

                                    <n-divider style="margin: 0" />

                                    <!-- Contacto 2 -->
                                    <div>
                                        <p class="text-xs font-bold text-gray-500 uppercase mb-2">Secundario (Opcional)</p>
                                        <n-grid x-gap="8" :cols="1">
                                            <n-grid-item>
                                                <n-form-item label="Correo Alternativo" path="email_secondary">
                                                    <n-input v-model:value="form.email_secondary" placeholder="mail2@ejemplo.com">
                                                        <template #prefix><n-icon :component="MailOutline" /></template>
                                                    </n-input>
                                                </n-form-item>
                                            </n-grid-item>
                                            <n-grid-item>
                                                <n-form-item label="Teléfono Alternativo" path="phone_secondary">
                                                    <n-input v-model:value="form.phone_secondary" placeholder="Otro número">
                                                        <template #prefix><n-icon :component="CallOutline" /></template>
                                                    </n-input>
                                                </n-form-item>
                                            </n-grid-item>
                                        </n-grid>
                                    </div>
                                </div>
                            </n-card>

                            <!-- 4. Notas -->
                            <n-card :bordered="false" class="shadow-sm rounded-2xl">
                                <template #header>
                                    <span class="text-gray-700 font-bold flex items-center gap-2 text-sm">
                                        <n-icon :component="DocumentTextOutline"/> Notas Internas
                                    </span>
                                </template>
                                <n-input 
                                    v-model:value="form.notes" 
                                    type="textarea" 
                                    placeholder="Referencias de ubicación, horarios, etc."
                                    :autosize="{ minRows: 3 }"
                                />
                            </n-card>

                            <!-- Botones -->
                            <div class="flex flex-col gap-3 sticky top-6">
                                <n-button 
                                    type="primary" 
                                    size="large" 
                                    block 
                                    @click="submit" 
                                    :loading="form.processing"
                                    :disabled="form.processing"
                                    class="shadow-md"
                                >
                                    <template #icon><n-icon><SaveOutline /></n-icon></template>
                                    Guardar Cliente
                                </n-button>
                                
                                <Link :href="route('clients.index')" class="w-full">
                                    <n-button block ghost>Cancelar</n-button>
                                </Link>
                            </div>
                        </div>

                    </div>
                </n-form>
            </div>
        </div>
    </AppLayout>
</template>