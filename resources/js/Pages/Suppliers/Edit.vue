<script setup>
import { ref, onMounted } from 'vue';
import { useForm, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { 
    NForm, NFormItem, NInput, NButton, NCard, NIcon, NGrid, NGridItem, 
    createDiscreteApi, NDivider, NBadge
} from 'naive-ui';
import { 
    SaveOutline, ArrowBackOutline, StorefrontOutline, PersonAddOutline, 
    GlobeOutline, TrashOutline, StarOutline, Star
} from '@vicons/ionicons5';

const props = defineProps({
    supplier: {
        type: Object,
        required: true
    }
});

const { notification } = createDiscreteApi(['notification']);
const formRef = ref(null);

// Función auxiliar para crear estructura de contacto vacío
const createEmptyContact = (isPrimary = false) => ({
    id: null, // Importante para diferenciar nuevos de existentes
    name: '',
    job_title: '',
    email: '',
    phone: '',
    notes: '',
    is_primary: isPrimary
});

// Inicialización del formulario con datos existentes
const form = useForm({
    company_name: props.supplier.company_name || '',
    website: props.supplier.website || '',
    contacts: (props.supplier.contacts && props.supplier.contacts.length > 0)
        ? props.supplier.contacts.map(c => ({
            id: c.id,
            name: c.name,
            job_title: c.job_title,
            email: c.email,
            phone: c.phone,
            notes: c.notes,
            is_primary: Boolean(c.is_primary)
        })) 
        : [createEmptyContact(true)]
});

// Reglas de validación (Similares al Create)
const rules = {
    company_name: { 
        required: true, 
        message: 'El nombre de la empresa es obligatorio', 
        trigger: 'blur' 
    }
};

// Acciones de la lista de contactos
const addContact = () => {
    form.contacts.push(createEmptyContact(false));
};

const removeContact = (index) => {
    if (form.contacts.length === 1) {
        notification.warning({ title: 'Aviso', content: 'Debe existir al menos un contacto.', duration: 2000 });
        return;
    }
    // Si tiene ID, el backend se encargará de borrarlo al no recibirlo en el array,
    // o podemos marcarlo para borrar si la lógica fuera diferente, pero aquí
    // el controlador sincroniza lo que recibe.
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
             // Validaciones manuales extra
             if (form.contacts.some(c => !c.name)) {
                notification.error({ title: 'Faltan datos', content: 'Todos los contactos deben tener un nombre.' });
                return;
            }

            form.put(route('suppliers.update', props.supplier.id), {
                onSuccess: () => {
                    notification.success({
                        title: 'Cambios Guardados',
                        content: 'La información del proveedor ha sido actualizada.',
                        duration: 3000
                    });
                },
                onError: (err) => {
                    notification.error({
                        title: 'Error al Actualizar',
                        content: 'Revisa los campos marcados en rojo.',
                        duration: 4000
                    });
                }
            });
        } else {
            notification.warning({
                title: 'Formulario Incompleto',
                content: 'Completa los campos requeridos.',
                duration: 3000
            });
        }
    });
};
</script>

<template>
    <AppLayout :title="`Editar ${props.supplier.company_name}`">
        <template #header>
            <div class="flex items-center gap-4">
                <Link :href="route('suppliers.index')">
                    <n-button circle secondary type="default">
                        <template #icon><n-icon><ArrowBackOutline /></n-icon></template>
                    </n-button>
                </Link>
                <div>
                    <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                        Editar Proveedor
                    </h2>
                    <p class="text-xs text-gray-500">{{ props.supplier.company_name }}</p>
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
                    size="medium"
                >
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        
                        <!-- Columna Izquierda: Datos Empresa -->
                        <div class="lg:col-span-1 space-y-6">
                            <n-card :bordered="false" class="shadow-sm rounded-2xl sticky top-8">
                                <template #header>
                                    <span class="text-gray-700 font-bold flex items-center gap-2">
                                        <n-icon :component="StorefrontOutline" /> Empresa
                                    </span>
                                </template>

                                <n-form-item 
                                    label="Razón Social / Nombre" 
                                    path="company_name"
                                    :validation-status="form.errors.company_name ? 'error' : undefined"
                                    :feedback="form.errors.company_name"
                                >
                                    <n-input 
                                        v-model:value="form.company_name" 
                                        placeholder="Solar Tech S.A. de C.V." 
                                        class="font-semibold"
                                    />
                                </n-form-item>

                                <n-form-item 
                                    label="Sitio Web" 
                                    path="website"
                                    :validation-status="form.errors.website ? 'error' : undefined"
                                    :feedback="form.errors.website"
                                >
                                    <n-input 
                                        v-model:value="form.website" 
                                        placeholder="https://..." 
                                    >
                                        <template #prefix><n-icon :component="GlobeOutline" /></template>
                                    </n-input>
                                </n-form-item>

                                <n-divider />

                                <div class="flex flex-col gap-3 mt-4">
                                    <n-button 
                                        type="primary" 
                                        size="large" 
                                        block 
                                        @click="submit" 
                                        :loading="form.processing"
                                    >
                                        <template #icon><n-icon><SaveOutline /></n-icon></template>
                                        Guardar Cambios
                                    </n-button>
                                    
                                    <Link :href="route('suppliers.show', props.supplier.id)" class="w-full">
                                        <n-button block ghost>Cancelar</n-button>
                                    </Link>
                                </div>
                            </n-card>
                        </div>

                        <!-- Columna Derecha: Lista de Contactos -->
                        <div class="lg:col-span-2 space-y-4">
                            <div class="flex justify-between items-center mb-2">
                                <h3 class="text-lg font-bold text-gray-700">Agenda de Contactos</h3>
                                <n-button size="small" dashed type="primary" @click="addContact">
                                    <template #icon><n-icon><PersonAddOutline /></n-icon></template>
                                    Agregar Otro Contacto
                                </n-button>
                            </div>

                            <!-- Iteración de Contactos -->
                            <transition-group name="list" tag="div" class="space-y-4">
                                <div 
                                    v-for="(contact, index) in form.contacts" 
                                    :key="index"
                                    class="relative"
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
                                                    class="p-2 rounded-full transition-colors"
                                                    :class="contact.is_primary ? 'bg-blue-100 text-blue-600' : 'bg-gray-100 text-gray-400'"
                                                >
                                                    <n-icon size="20" :component="contact.is_primary ? Star : StarOutline" />
                                                </div>
                                                <span class="font-medium text-gray-600">
                                                    {{ contact.name || `Nuevo Contacto ${index + 1}` }}
                                                </span>
                                                <span v-if="contact.is_primary" class="text-xs bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full font-bold">Principal</span>
                                            </div>
                                            
                                            <div class="flex items-center gap-2">
                                                <n-button 
                                                    v-if="!contact.is_primary" 
                                                    size="tiny" 
                                                    quaternary 
                                                    type="info"
                                                    @click="setPrimary(index)"
                                                >
                                                    Hacer Principal
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
                                        <n-grid :x-gap="12" :y-gap="8" cols="1 s:2">
                                            <n-grid-item>
                                                <n-form-item label="Nombre Completo" :path="`contacts[${index}].name`">
                                                    <n-input v-model:value="contact.name" placeholder="Ej. Roberto Gómez" />
                                                </n-form-item>
                                            </n-grid-item>
                                            <n-grid-item>
                                                <n-form-item label="Puesto / Cargo" :path="`contacts[${index}].job_title`">
                                                    <n-input v-model:value="contact.job_title" placeholder="Ej. Gerente de Ventas" />
                                                </n-form-item>
                                            </n-grid-item>
                                            <n-grid-item>
                                                <n-form-item label="Email" :path="`contacts[${index}].email`">
                                                    <n-input v-model:value="contact.email" placeholder="correo@ejemplo.com" />
                                                </n-form-item>
                                            </n-grid-item>
                                            <n-grid-item>
                                                <n-form-item label="Teléfono / WhatsApp" :path="`contacts[${index}].phone`">
                                                    <n-input v-model:value="contact.phone" placeholder="(55) 1234 5678" />
                                                </n-form-item>
                                            </n-grid-item>
                                            <n-grid-item span="2">
                                                <n-form-item label="Notas Adicionales">
                                                    <n-input 
                                                        v-model:value="contact.notes" 
                                                        type="textarea" 
                                                        :autosize="{ minRows: 1, maxRows: 2 }" 
                                                        placeholder="Horarios, extensiones, etc."
                                                    />
                                                </n-form-item>
                                            </n-grid-item>
                                        </n-grid>

                                        <!-- Mensajes de error específicos -->
                                        <div v-if="form.errors[`contacts.${index}.name`]" class="text-red-500 text-xs mt-1 px-1">
                                            {{ form.errors[`contacts.${index}.name`] }}
                                        </div>
                                    </n-card>
                                </div>
                            </transition-group>
                            
                            <!-- Botón inferior para agregar -->
                            <div 
                                class="text-center py-4 border-2 border-dashed border-gray-200 rounded-xl hover:bg-gray-50 cursor-pointer transition flex flex-col items-center justify-center text-gray-400 hover:text-blue-500 hover:border-blue-200" 
                                @click="addContact"
                            >
                                <n-icon size="24" class="mb-1" :component="PersonAddOutline" />
                                <span class="text-sm font-medium">Agregar nuevo contacto</span>
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