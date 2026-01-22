<script setup>
import { ref } from 'vue';
import { useForm, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { 
    NForm, NFormItem, NInput, NButton, NCard, NIcon, NGrid, NGridItem, 
    createDiscreteApi, NDivider, NSelect
} from 'naive-ui';
import { 
    SaveOutline, ArrowBackOutline, PersonOutline, MailOutline, CallOutline, 
    BusinessOutline, LocationOutline, DocumentTextOutline, ReceiptOutline, 
    PersonAddOutline, TrashOutline, Star, StarOutline
} from '@vicons/ionicons5';

const { notification } = createDiscreteApi(['notification']);
const formRef = ref(null);

// Estructura inicial de un contacto (Igual que en Proveedores)
const createEmptyContact = (isPrimary = false) => ({
    name: '',
    job_title: '', // Nuevo campo: Puesto / Parentesco
    email: '',
    phone: '',
    notes: '',
    is_primary: isPrimary
});

// Formulario actualizado con la nueva estructura
const form = useForm({
    name: '',            
    contact_person: '',  
    tax_id: '',          
    
    // Dirección Atomizada
    road_type: '', // NUEVO: Tipo de vialidad
    street: '',
    exterior_number: '',
    interior_number: '',
    neighborhood: '',
    municipality: '',
    state: '',
    zip_code: '',
    country: 'México',
    
    notes: '',

    // Lista de Contactos (Reemplaza a los campos planos)
    contacts: [ createEmptyContact(true) ]
});

// Opciones para tipo de vialidad (Opcional, puede ser un simple input)
const roadTypeOptions = [
    { label: 'Calle', value: 'Calle' },
    { label: 'Avenida', value: 'Avenida' },
    { label: 'Boulevard', value: 'Boulevard' },
    { label: 'Calzada', value: 'Calzada' },
    { label: 'Circuito', value: 'Circuito' },
    { label: 'Privada', value: 'Privada' },
    { label: 'Carretera', value: 'Carretera' },
    { label: 'Camino', value: 'Camino' },
];

const rules = {
    name: { 
        required: true, 
        message: 'El nombre o razón social es obligatorio', 
        trigger: 'blur' 
    },
};

// Funciones para manejo de contactos (Idénticas a Suppliers)
const addContact = () => {
    form.contacts.push(createEmptyContact(false));
};

const removeContact = (index) => {
    if (form.contacts.length === 1) {
        notification.warning({ title: 'Atención', content: 'Debe haber al menos un contacto.', duration: 2000 });
        return;
    }
    form.contacts.splice(index, 1);
};

const setPrimary = (index) => {
    form.contacts.forEach((c, i) => {
        c.is_primary = (i === index);
    });
};

const submit = () => {
    formRef.value?.validate((errors) => {
        if (!errors) {
            // Validaciones extra para contactos
            const invalidContact = form.contacts.find(c => !c.name);
            if (invalidContact) {
                notification.error({ title: 'Error', content: 'Todos los contactos deben tener un nombre.' });
                return;
            }

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

                                            <n-form-item label="Persona de Atención (Alias)" path="contact_person">
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
                                    
                                    <!-- NUEVO: Tipo de Vialidad -->
                                    <n-grid-item span="1 m:1">
                                        <n-form-item label="Tipo Vialidad" path="road_type">
                                            <!-- Usamos NSelect con tags para permitir entrada libre o selección -->
                                            <n-select 
                                                v-model:value="form.road_type" 
                                                filterable 
                                                tag 
                                                :options="roadTypeOptions" 
                                                placeholder="Calle" 
                                            />
                                        </n-form-item>
                                    </n-grid-item>

                                    <!-- Calle ahora ocupa el resto -->
                                    <n-grid-item span="1 m:3">
                                        <n-form-item label="Nombre de la Vialidad (Calle)" path="street">
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

                            <!-- 3. Notas -->
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
                        </div>

                        <!-- Columna Derecha: Lista de Contactos Dinámica -->
                        <div class="space-y-6">
                            
                            <div class="flex justify-between items-center mb-2">
                                <h3 class="text-lg font-bold text-gray-700 flex items-center gap-2">
                                    <n-icon :component="CallOutline" class="text-blue-600"/> Contactos
                                </h3>
                                <n-button size="small" dashed type="primary" @click="addContact">
                                    <template #icon><n-icon><PersonAddOutline /></n-icon></template>
                                    Agregar
                                </n-button>
                            </div>

                            <!-- Iteración de Contactos -->
                            <transition-group name="list" tag="div" class="space-y-4">
                                <div 
                                    v-for="(contact, index) in form.contacts" 
                                    :key="index"
                                    class="relative group"
                                >
                                    <n-card 
                                        :bordered="false" 
                                        class="shadow-sm rounded-xl border border-gray-100 transition hover:shadow-md"
                                        :class="{'ring-2 ring-blue-100': contact.is_primary}"
                                    >
                                        <!-- Header del Card de Contacto -->
                                        <div class="flex justify-between items-start mb-4">
                                            <div class="flex items-center gap-2">
                                                <div 
                                                    class="size-8 flex items-center justify-center rounded-full"
                                                    :class="contact.is_primary ? 'bg-blue-100 text-blue-600' : 'bg-gray-100 text-gray-400'"
                                                >
                                                    <n-icon size="18" :component="contact.is_primary ? Star : StarOutline" />
                                                </div>
                                                <span class="font-medium text-gray-600">Contacto #{{ index + 1 }}</span>
                                            </div>
                                            
                                            <div class="flex items-center gap-2">
                                                <n-button 
                                                    v-if="!contact.is_primary" 
                                                    size="tiny" 
                                                    quaternary 
                                                    type="info"
                                                    @click="setPrimary(index)"
                                                >
                                                    Principal
                                                </n-button>
                                                <n-button 
                                                    v-if="form.contacts.length > 1"
                                                    size="small" 
                                                    quaternary 
                                                    type="error" 
                                                    @click="removeContact(index)"
                                                >
                                                    <template #icon><n-icon><TrashOutline /></n-icon></template>
                                                </n-button>
                                            </div>
                                        </div>

                                        <!-- Campos del Contacto -->
                                        <n-grid :x-gap="12" :y-gap="8" cols="1">
                                            <n-grid-item>
                                                <n-form-item label="Nombre Completo" :path="`contacts[${index}].name`">
                                                    <n-input v-model:value="contact.name" placeholder="Ej. Roberto Gómez">
                                                        <template #prefix><n-icon :component="PersonOutline"/></template>
                                                    </n-input>
                                                </n-form-item>
                                            </n-grid-item>
                                            
                                            <!-- NUEVO: Puesto / Parentesco -->
                                            <n-grid-item>
                                                <n-form-item label="Puesto / Parentesco" :path="`contacts[${index}].job_title`">
                                                    <n-input v-model:value="contact.job_title" placeholder="Ej. Gerente / Esposo" />
                                                </n-form-item>
                                            </n-grid-item>

                                            <n-grid-item>
                                                <n-form-item label="Email" :path="`contacts[${index}].email`">
                                                    <n-input v-model:value="contact.email" placeholder="mail@ejemplo.com">
                                                        <template #prefix><n-icon :component="MailOutline"/></template>
                                                    </n-input>
                                                </n-form-item>
                                            </n-grid-item>

                                            <n-grid-item>
                                                <n-form-item label="Teléfono / Celular" :path="`contacts[${index}].phone`">
                                                    <n-input v-model:value="contact.phone" placeholder="(55) 1234 5678">
                                                        <template #prefix><n-icon :component="CallOutline"/></template>
                                                    </n-input>
                                                </n-form-item>
                                            </n-grid-item>
                                        </n-grid>

                                        <!-- Validaciones individuales -->
                                        <div v-if="form.errors[`contacts.${index}.name`]" class="text-red-500 text-xs mt-1">
                                            {{ form.errors[`contacts.${index}.name`] }}
                                        </div>
                                        <div v-if="form.errors[`contacts.${index}.email`]" class="text-red-500 text-xs mt-1">
                                            {{ form.errors[`contacts.${index}.email`] }}
                                        </div>

                                    </n-card>
                                </div>
                            </transition-group>

                            <div class="flex flex-col gap-3 sticky top-6 pt-4">
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

<style scoped>
.list-enter-active,
.list-leave-active {
  transition: all 0.3s ease;
}
.list-enter-from,
.list-leave-to {
  opacity: 0;
  transform: translateX(30px);
}
</style>