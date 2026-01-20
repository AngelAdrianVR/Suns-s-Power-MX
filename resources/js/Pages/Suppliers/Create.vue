<script setup>
import { ref } from 'vue';
import { useForm, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { 
    NForm, NFormItem, NInput, NButton, NCard, NIcon, NGrid, NGridItem, 
    createDiscreteApi, NUpload, NUploadDragger, NText, NP
} from 'naive-ui';
import { 
    SaveOutline, ArrowBackOutline, StorefrontOutline, PersonAddOutline, 
    GlobeOutline, TrashOutline, StarOutline, Star, CardOutline, DocumentAttachOutline,
    ArchiveOutline
} from '@vicons/ionicons5';

const { notification } = createDiscreteApi(['notification']);
const formRef = ref(null);

const createEmptyContact = (isPrimary = false) => ({
    name: '',
    job_title: '',
    email: '',
    phone: '',
    notes: '',
    is_primary: isPrimary
});

const form = useForm({
    company_name: '',
    website: '',
    
    // Campos Fiscales y Bancarios
    rfc: '',
    address: '',
    bank_account_holder: '',
    bank_name: '',
    clabe: '',
    account_number: '',
    
    // Archivos (Array de objetos File)
    documents: [],

    contacts: [ createEmptyContact(true) ]
});

const rules = {
    company_name: { 
        required: true, 
        message: 'El nombre de la empresa es obligatorio', 
        trigger: 'blur' 
    }
};

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

const handleUploadChange = (data) => {
    // data.fileList contiene la lista actualizada.
    // Mapeamos para enviar los objetos File nativos al backend.
    form.documents = data.fileList.map(f => f.file);
};

const submit = () => {
    formRef.value?.validate((errors) => {
        if (!errors) {
            const invalidContact = form.contacts.find(c => !c.name);
            if (invalidContact) {
                notification.error({ title: 'Error', content: 'Todos los contactos deben tener un nombre.' });
                return;
            }

            form.post(route('suppliers.store'), {
                onSuccess: () => {
                    notification.success({
                        title: 'Proveedor Registrado',
                        content: 'El proveedor, datos bancarios y documentos han sido guardados.',
                        duration: 3000
                    });
                },
                onError: (err) => {
                    console.error(err);
                    notification.error({
                        title: 'Error de Validación',
                        content: 'Revisa los campos marcados en rojo.',
                        duration: 4000
                    });
                }
            });
        }
    });
};
</script>

<template>
    <AppLayout title="Nuevo Proveedor">
        <template #header>
            <div class="flex items-center gap-4">
                <Link :href="route('suppliers.index')">
                    <n-button circle secondary type="default">
                        <template #icon><n-icon><ArrowBackOutline /></n-icon></template>
                    </n-button>
                </Link>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Registrar Nuevo Proveedor
                </h2>
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
                    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
                        
                        <!-- COLUMNA IZQUIERDA (Datos Principales y Fiscales) -->
                        <div class="lg:col-span-4 space-y-6">
                            
                            <!-- Datos Generales -->
                            <n-card :bordered="false" class="shadow-sm rounded-2xl">
                                <template #header>
                                    <span class="text-gray-700 font-bold flex items-center gap-2">
                                        <n-icon :component="StorefrontOutline" /> Empresa
                                    </span>
                                </template>

                                <n-form-item label="Razón Social / Nombre" path="company_name">
                                    <n-input v-model:value="form.company_name" placeholder="Solar Tech S.A. de C.V." />
                                </n-form-item>

                                <n-form-item label="RFC" path="rfc">
                                    <n-input v-model:value="form.rfc" placeholder="XAXX010101000" />
                                </n-form-item>

                                <n-form-item label="Sitio Web" path="website">
                                    <n-input v-model:value="form.website" placeholder="https://..." >
                                        <template #prefix><n-icon :component="GlobeOutline" /></template>
                                    </n-input>
                                </n-form-item>

                                <n-form-item label="Dirección Fiscal / Física" path="address">
                                    <n-input 
                                        v-model:value="form.address" 
                                        type="textarea" 
                                        placeholder="Calle, Número, Colonia, CP, Ciudad..."
                                        :autosize="{ minRows: 2, maxRows: 4 }"
                                    />
                                </n-form-item>
                            </n-card>

                            <!-- Documentos -->
                            <n-card :bordered="false" class="shadow-sm rounded-2xl">
                                <template #header>
                                    <span class="text-gray-700 font-bold flex items-center gap-2">
                                        <n-icon :component="DocumentAttachOutline" /> Documentación
                                    </span>
                                </template>
                                <p class="text-xs text-gray-500 mb-2">Constancia fiscal, carátula bancaria, contratos, etc.</p>
                                
                                <n-upload
                                    multiple
                                    directory-dnd
                                    @change="handleUploadChange"
                                    :default-upload="false"
                                >
                                    <n-upload-dragger>
                                        <div style="margin-bottom: 12px">
                                            <n-icon size="48" :depth="3">
                                                <archive-outline />
                                            </n-icon>
                                        </div>
                                        <n-text style="font-size: 16px">
                                            Haz clic o arrastra archivos aquí
                                        </n-text>
                                        <n-p depth="3" style="margin: 8px 0 0 0">
                                            Formatos permitidos: PDF, JPG, PNG
                                        </n-p>
                                    </n-upload-dragger>
                                </n-upload>
                            </n-card>

                            <div class="sticky top-8 pt-4">
                                <n-button 
                                    type="primary" 
                                    size="large" 
                                    block 
                                    @click="submit" 
                                    :loading="form.processing"
                                    class="mb-3"
                                >
                                    <template #icon><n-icon><SaveOutline /></n-icon></template>
                                    Guardar Proveedor
                                </n-button>
                                
                                <Link :href="route('suppliers.index')" class="w-full">
                                    <n-button block ghost>Cancelar</n-button>
                                </Link>
                            </div>
                        </div>

                        <!-- COLUMNA DERECHA (Bancos y Contactos) -->
                        <div class="lg:col-span-8 space-y-6">
                            
                            <!-- Datos Bancarios -->
                            <n-card :bordered="false" class="shadow-sm rounded-2xl">
                                <template #header>
                                    <span class="text-gray-700 font-bold flex items-center gap-2">
                                        <n-icon :component="CardOutline" /> Datos Bancarios
                                    </span>
                                </template>
                                
                                <n-grid :x-gap="12" :y-gap="8" cols="1 s:2">
                                    <n-grid-item span="2">
                                        <n-form-item label="Titular de la Cuenta" path="bank_account_holder">
                                            <n-input v-model:value="form.bank_account_holder" placeholder="Nombre completo del titular" />
                                        </n-form-item>
                                    </n-grid-item>
                                    
                                    <n-grid-item>
                                        <n-form-item label="Institución Bancaria" path="bank_name">
                                            <n-input v-model:value="form.bank_name" placeholder="Ej. BBVA, Santander" />
                                        </n-form-item>
                                    </n-grid-item>

                                    <n-grid-item>
                                        <n-form-item label="Número de Cuenta" path="account_number">
                                            <n-input v-model:value="form.account_number" placeholder="Ej. 1234567890" />
                                        </n-form-item>
                                    </n-grid-item>

                                    <n-grid-item span="2">
                                        <n-form-item label="CLABE Interbancaria" path="clabe">
                                            <n-input v-model:value="form.clabe" placeholder="18 dígitos" />
                                        </n-form-item>
                                    </n-grid-item>
                                </n-grid>
                            </n-card>

                            <!-- Agenda de Contactos -->
                            <div class="flex justify-between items-center mb-2 mt-8">
                                <h3 class="text-lg font-bold text-gray-700 flex items-center gap-2">
                                    <n-icon :component="PersonAddOutline" /> Contactos
                                </h3>
                                <n-button size="small" dashed type="primary" @click="addContact">
                                    Agregar Otro
                                </n-button>
                            </div>

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
                                        <div class="flex justify-between items-start mb-4">
                                            <div class="flex items-center gap-2">
                                                <div 
                                                    class="size-8 flex items-center justify-center rounded-full"
                                                    :class="contact.is_primary ? 'bg-blue-100 text-blue-600' : 'bg-gray-100 text-gray-400'"
                                                >
                                                    <n-icon size="18" :component="contact.is_primary ? Star : StarOutline" />
                                                </div>
                                                <span class="font-medium text-gray-600">Contacto {{ index + 1 }}</span>
                                                <span v-if="contact.is_primary" class="text-xs bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full font-bold">Principal</span>
                                            </div>
                                            
                                            <div class="flex items-center gap-2">
                                                <n-button v-if="!contact.is_primary" size="tiny" quaternary type="info" @click="setPrimary(index)">
                                                    Hacer Principal
                                                </n-button>
                                                <n-button v-if="form.contacts.length > 1" size="small" quaternary type="error" @click="removeContact(index)">
                                                    <template #icon><n-icon><TrashOutline /></n-icon></template>
                                                </n-button>
                                            </div>
                                        </div>

                                        <n-grid :x-gap="12" :y-gap="8" cols="1 s:2">
                                            <n-grid-item>
                                                <n-form-item label="Nombre Completo" :path="`contacts[${index}].name`">
                                                    <n-input v-model:value="contact.name" placeholder="Ej. Roberto Gómez" />
                                                </n-form-item>
                                            </n-grid-item>
                                            <n-grid-item>
                                                <n-form-item label="Puesto / Cargo" :path="`contacts[${index}].job_title`">
                                                    <n-input v-model:value="contact.job_title" placeholder="Ej. Ventas" />
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
                                                <n-form-item label="Notas">
                                                    <n-input 
                                                        v-model:value="contact.notes" 
                                                        type="textarea" 
                                                        :autosize="{ minRows: 1, maxRows: 2 }" 
                                                        placeholder="Detalles adicionales..."
                                                    />
                                                </n-form-item>
                                            </n-grid-item>
                                        </n-grid>
                                        
                                        <div v-if="form.errors[`contacts.${index}.name`]" class="text-red-500 text-xs mt-1">
                                            {{ form.errors[`contacts.${index}.name`] }}
                                        </div>
                                    </n-card>
                                </div>
                            </transition-group>

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