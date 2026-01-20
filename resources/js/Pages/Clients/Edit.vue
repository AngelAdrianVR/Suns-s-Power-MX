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

// Definición de Props explícita para evitar errores de compilación
const props = defineProps({
    client: {
        type: Object,
        required: true
    }
});

const { notification } = createDiscreteApi(['notification']);
const formRef = ref(null);

// Función auxiliar para crear contactos vacíos
const createEmptyContact = (isPrimary = false) => ({
    id: null, // Importante para diferenciar nuevos de existentes
    name: '',
    job_title: '',
    email: '',
    phone: '',
    notes: '',
    is_primary: isPrimary
});

// Inicializar formulario transformando los contactos existentes
const form = useForm({
    name: props.client.name,
    contact_person: props.client.contact_person,
    tax_id: props.client.tax_id,
    
    // Dirección Atomizada
    road_type: props.client.road_type || 'Calle', // Valor por defecto si viene nulo
    street: props.client.street,
    exterior_number: props.client.exterior_number,
    interior_number: props.client.interior_number,
    neighborhood: props.client.neighborhood,
    municipality: props.client.municipality,
    state: props.client.state,
    zip_code: props.client.zip_code,
    country: props.client.country || 'México',
    
    notes: props.client.notes,

    // Inicializar contactos: Si hay, mapearlos; si no, crear uno vacío
    contacts: (props.client.contacts && props.client.contacts.length > 0)
        ? props.client.contacts.map(c => ({
            id: c.id,
            name: c.name,
            job_title: c.job_title,
            email: c.email,
            phone: c.phone,
            notes: c.notes,
            is_primary: !!c.is_primary // Asegurar booleano
        }))
        : [ createEmptyContact(true) ]
});

// Opciones de vialidad
const roadTypeOptions = [
    { label: 'Calle', value: 'Calle' },
    { label: 'Avenida', value: 'Avenida' },
    { label: 'Boulevard', value: 'Boulevard' },
    { label: 'Calzada', value: 'Calzada' },
    { label: 'Privada', value: 'Privada' },
    { label: 'Carretera', value: 'Carretera' },
    { label: 'Camino', value: 'Camino' },
    { label: 'Andador', value: 'Andador' },
    { label: 'Circuito', value: 'Circuito' },
];

const rules = {
    name: { 
        required: true, 
        message: 'El nombre o razón social es obligatorio', 
        trigger: 'blur' 
    },
};

// --- LÓGICA DE CONTACTOS ---

const addContact = () => {
    form.contacts.push(createEmptyContact(false));
};

const removeContact = (index) => {
    if (form.contacts.length === 1) {
        notification.warning({ title: 'Atención', content: 'Debe conservar al menos un contacto.', duration: 2000 });
        return;
    }
    form.contacts.splice(index, 1);
};

const setPrimary = (index) => {
    form.contacts.forEach((c, i) => {
        c.is_primary = (i === index);
    });
};

// --- SUBMIT ---

const submit = () => {
    formRef.value?.validate((errors) => {
        if (!errors) {
            // Validación manual de contactos
            if (form.contacts.some(c => !c.name)) {
                notification.error({ 
                    title: 'Faltan datos', 
                    content: 'Todos los contactos deben tener un nombre.' 
                });
                return;
            }

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
                content: 'Por favor completa la información obligatoria.',
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
                <!-- Botón regresar al SHOW del cliente -->
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
                        
                        <!-- Columna Izquierda: Datos Principales -->
                        <div class="lg:col-span-2 space-y-6">
                            
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
                                                class="font-semibold text-lg"
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
                                                label="Alias / Atención A" 
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

                            <!-- Tarjeta 2: Ubicación Atomizada -->
                            <n-card :bordered="false" class="shadow-sm rounded-2xl">
                                <template #header>
                                    <span class="text-gray-600 font-semibold flex items-center gap-2">
                                        <n-icon :component="LocationOutline" /> Dirección Fiscal / Instalación
                                    </span>
                                </template>
                                
                                <n-grid x-gap="12" y-gap="4" cols="1 s:2 m:4" responsive="screen">
                                    
                                    <!-- NUEVO: Tipo de Vialidad -->
                                    <n-grid-item span="1 m:1">
                                        <n-form-item label="Tipo Vialidad" path="road_type">
                                            <n-select 
                                                v-model:value="form.road_type" 
                                                filterable 
                                                tag 
                                                :options="roadTypeOptions" 
                                                placeholder="Calle" 
                                            />
                                        </n-form-item>
                                    </n-grid-item>

                                    <!-- Calle -->
                                    <n-grid-item span="1 m:3">
                                        <n-form-item label="Nombre de Vialidad (Calle)" path="street">
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
                        </div>

                        <!-- Columna Derecha: Lista Dinámica de Contactos -->
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
                                                <n-form-item label="Nombre Completo" :path="`contacts[${index}].name`" :show-label="true">
                                                    <n-input v-model:value="contact.name" placeholder="Ej. Roberto Gómez" size="small">
                                                        <template #prefix><n-icon :component="PersonOutline"/></template>
                                                    </n-input>
                                                </n-form-item>
                                            </n-grid-item>
                                            
                                            <!-- Puesto / Parentesco -->
                                            <n-grid-item>
                                                <n-form-item label="Puesto / Parentesco" :path="`contacts[${index}].job_title`" :show-label="true">
                                                    <n-input v-model:value="contact.job_title" placeholder="Ej. Gerente / Esposo" size="small" />
                                                </n-form-item>
                                            </n-grid-item>

                                            <n-grid-item>
                                                <n-form-item label="Email" :path="`contacts[${index}].email`" :show-label="true">
                                                    <n-input v-model:value="contact.email" placeholder="mail@ejemplo.com" size="small">
                                                        <template #prefix><n-icon :component="MailOutline"/></template>
                                                    </n-input>
                                                </n-form-item>
                                            </n-grid-item>

                                            <n-grid-item>
                                                <n-form-item label="Teléfono" :path="`contacts[${index}].phone`" :show-label="true">
                                                    <n-input v-model:value="contact.phone" placeholder="(55) 1234 5678" size="small">
                                                        <template #prefix><n-icon :component="CallOutline"/></template>
                                                    </n-input>
                                                </n-form-item>
                                            </n-grid-item>
                                        </n-grid>

                                        <!-- Errores específicos del contacto -->
                                        <div v-if="form.errors[`contacts.${index}.name`]" class="text-red-500 text-xs mt-1">
                                            {{ form.errors[`contacts.${index}.name`] }}
                                        </div>

                                    </n-card>
                                </div>
                            </transition-group>

                            <!-- Acciones -->
                            <div class="flex flex-col gap-3 sticky top-6 pt-4">
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