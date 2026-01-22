<script>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { 
    NCard, NForm, NFormItem, NInput, NButton, NIcon, NSelect, NUpload, 
    NUploadDragger, NText, NP, NDivider, NAlert, NDatePicker, NGrid, NGridItem,
    createDiscreteApi 
} from 'naive-ui';
import { 
    ArrowBackOutline, SaveOutline, PersonOutline, MailOutline, 
    KeyOutline, CloudUploadOutline, RefreshOutline, 
    CallOutline, ShieldCheckmarkOutline, HomeOutline, 
    WalletOutline, PeopleOutline, AddOutline, TrashOutline,
    CalendarOutline, LocationOutline, DocumentTextOutline
} from '@vicons/ionicons5';

export default {
    components: {
        AppLayout, Head, Link, NCard, NForm, NFormItem, NInput, NButton, 
        NIcon, NSelect, NUpload, NUploadDragger, NText, NP, NDivider, 
        NAlert, NDatePicker, NGrid, NGridItem,
        ArrowBackOutline, CloudUploadOutline, SaveOutline, CallOutline, 
        ShieldCheckmarkOutline, HomeOutline, WalletOutline, PeopleOutline,
        AddOutline, TrashOutline, CalendarOutline, LocationOutline, DocumentTextOutline, PersonOutline
    },
    props: {
        roles: {
            type: Array,
            default: () => []
        }
    },
    setup() {
        const form = useForm({
            // Generales
            first_name: '',
            paternal_surname: '',
            maternal_surname: '',
            email: '',
            phone: '',
            password: '',
            role: null,
            
            // Legales
            birth_date: null,
            curp: '',
            rfc: '',
            nss: '',

            // Domicilio
            street: '',
            exterior_number: '',
            interior_number: '',
            neighborhood: '',
            zip_code: '',
            municipality: '',
            state: '',
            address_references: '',
            cross_streets: '',

            // Bancarios
            bank_account_holder: '',
            bank_name: '',
            bank_clabe: '',
            bank_account_number: '',

            // Listas Dinámicas
            beneficiaries: [],
            contacts: [],
            documents: [] 
        });

        const { message } = createDiscreteApi(['message']);

        // Helpers para listas dinámicas
        const addBeneficiary = () => {
            form.beneficiaries.push({
                first_name: '',
                paternal_surname: '',
                maternal_surname: '',
                birth_date: null,
                relationship: ''
            });
        };

        const removeBeneficiary = (index) => {
            form.beneficiaries.splice(index, 1);
        };

        const addContact = () => {
            form.contacts.push({
                name: '',
                relationship: '',
                phone: ''
            });
        };

        const removeContact = (index) => {
            form.contacts.splice(index, 1);
        };

        return { 
            form, message,
            addBeneficiary, removeBeneficiary,
            addContact, removeContact,
            // Iconos
            PersonOutline, MailOutline, KeyOutline, RefreshOutline, SaveOutline,
            ArrowBackOutline, CallOutline, ShieldCheckmarkOutline, HomeOutline,
            WalletOutline, PeopleOutline, AddOutline, TrashOutline, CalendarOutline,
            LocationOutline, DocumentTextOutline
        };
    },
    data() {
        return {
            rules: {
                first_name: { required: true, message: 'El nombre es obligatorio', trigger: ['input', 'blur'] },
                paternal_surname: { required: true, message: 'El apellido paterno es obligatorio', trigger: ['input', 'blur'] },
                email: { required: true, message: 'El correo es obligatorio', trigger: ['input', 'blur'] },
                role: { required: true, message: 'Selecciona un rol', trigger: ['blur', 'change'] },
                password: { required: true, message: 'La contraseña es obligatoria', trigger: ['input', 'blur'] }
            }
        };
    },
    methods: {
        goBack() {
            window.history.back();
        },
        generatePassword() {
            const chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*";
            let password = "";
            for (let i = 0; i < 12; i++) {
                password += chars.charAt(Math.floor(Math.random() * chars.length));
            }
            this.form.password = password;
            this.form.clearErrors('password');
            this.message.success("Contraseña generada exitosamente");
        },
        handleFileListChange(data) {
            this.form.documents = data.fileList.map(file => file.file);
            this.form.clearErrors('documents');
        },
        submit() {
            this.$refs.formRef?.validate((errors) => {
                if (!errors) {
                    // Transformar fechas a formato compatible si es necesario, 
                    // aunque Inertia/Laravel suelen manejar timestamps bien, 
                    // a veces es mejor enviar string ISO si el backend espera date estricto.
                    this.form.post(route('users.store'), {
                        preserveScroll: true,
                        onSuccess: () => {
                            this.message.success('Usuario creado correctamente');
                        },
                        onError: () => {
                            this.message.error('Hubo un error. Revisa los campos marcados.');
                        }
                    });
                } else {
                    this.message.error('Por favor completa los campos requeridos');
                }
            });
        }
    }
}
</script>

<template>
    <AppLayout title="Crear Usuario">
        <template #header>
            <div class="flex items-center gap-4">
                <n-button circle secondary @click="goBack">
                    <template #icon><n-icon><ArrowBackOutline /></n-icon></template>
                </n-button>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Nuevo Expediente de Usuario
                </h2>
            </div>
        </template>

        <div class="py-8 min-h-screen bg-gray-50/50">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                
                <n-form
                    ref="formRef"
                    :model="form"
                    :rules="rules"
                    label-placement="top"
                    size="large"
                >
                    <!-- SECCIÓN 1: DATOS PERSONALES -->
                    <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 md:p-8 mb-6">
                        <div class="flex items-center gap-3 mb-6">
                            <div class="p-2 bg-blue-50 rounded-lg text-blue-600">
                                <n-icon size="24"><PersonOutline /></n-icon>
                            </div>
                            <div>
                                <h3 class="text-lg font-bold text-gray-800">Información Personal</h3>
                                <p class="text-xs text-gray-400">Datos generales e identificación del colaborador.</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <n-form-item label="Nombre(s)" path="first_name">
                                <n-input v-model:value="form.first_name" placeholder="Ej. Juan" />
                            </n-form-item>
                            <n-form-item label="Apellido Paterno" path="paternal_surname">
                                <n-input v-model:value="form.paternal_surname" placeholder="Ej. Pérez" />
                            </n-form-item>
                            <n-form-item label="Apellido Materno" path="maternal_surname">
                                <n-input v-model:value="form.maternal_surname" placeholder="Ej. López" />
                            </n-form-item>

                            <!-- CORRECCIÓN AQUÍ: Se usa v-model:formatted-value y value-format -->
                            <n-form-item label="Fecha de Nacimiento" path="birth_date">
                                <n-date-picker 
                                    v-model:formatted-value="form.birth_date" 
                                    value-format="yyyy-MM-dd"
                                    type="date" 
                                    class="w-full" 
                                    clearable 
                                    placeholder="Seleccionar fecha"
                                />
                            </n-form-item>
                            
                            <n-form-item label="CURP" path="curp">
                                <n-input v-model:value="form.curp" placeholder="18 caracteres" maxlength="18" uppercase />
                            </n-form-item>
                            
                            <n-form-item label="RFC" path="rfc">
                                <n-input v-model:value="form.rfc" placeholder="13 caracteres" maxlength="13" uppercase />
                            </n-form-item>

                            <n-form-item label="NSS (Seguro Social)" path="nss">
                                <n-input v-model:value="form.nss" placeholder="11 dígitos" maxlength="11" />
                            </n-form-item>

                            <n-form-item label="Correo Electrónico" path="email">
                                <n-input v-model:value="form.email" placeholder="correo@empresa.com">
                                    <template #prefix><n-icon :component="MailOutline" /></template>
                                </n-input>
                            </n-form-item>

                            <n-form-item label="Teléfono / Celular" path="phone">
                                <n-input v-model:value="form.phone" placeholder="10 dígitos">
                                    <template #prefix><n-icon :component="CallOutline" /></template>
                                </n-input>
                            </n-form-item>
                        </div>
                    </div>

                    <!-- SECCIÓN 2: ACCESO Y ROL -->
                    <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 md:p-8 mb-6">
                        <div class="flex items-center gap-3 mb-6">
                            <div class="p-2 bg-purple-50 rounded-lg text-purple-600">
                                <n-icon size="24"><ShieldCheckmarkOutline /></n-icon>
                            </div>
                            <div>
                                <h3 class="text-lg font-bold text-gray-800">Acceso al Sistema</h3>
                                <p class="text-xs text-gray-400">Credenciales y permisos.</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <n-form-item label="Rol de Usuario" path="role">
                                <n-select v-model:value="form.role" :options="roles" placeholder="Selecciona un rol" />
                            </n-form-item>

                            <n-form-item label="Contraseña" path="password">
                                <n-input 
                                    v-model:value="form.password" 
                                    type="text" 
                                    placeholder="Contraseña segura"
                                    show-password-on="mousedown"
                                >
                                    <template #prefix><n-icon :component="KeyOutline" /></template>
                                    <template #suffix>
                                        <n-button quaternary circle size="small" @click="generatePassword" title="Generar">
                                            <template #icon><n-icon :component="RefreshOutline" /></template>
                                        </n-button>
                                    </template>
                                </n-input>
                            </n-form-item>
                        </div>
                    </div>

                    <!-- SECCIÓN 3: DOMICILIO -->
                    <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 md:p-8 mb-6">
                        <div class="flex items-center gap-3 mb-6">
                            <div class="p-2 bg-orange-50 rounded-lg text-orange-600">
                                <n-icon size="24"><HomeOutline /></n-icon>
                            </div>
                            <div>
                                <h3 class="text-lg font-bold text-gray-800">Domicilio</h3>
                                <p class="text-xs text-gray-400">Dirección actual del empleado.</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <n-form-item label="Calle" path="street" class="md:col-span-2">
                                <n-input v-model:value="form.street" placeholder="Nombre de la calle" />
                            </n-form-item>
                            
                            <div class="grid grid-cols-2 gap-4">
                                <n-form-item label="No. Exterior (el de la casa)" path="exterior_number">
                                    <n-input v-model:value="form.exterior_number" />
                                </n-form-item>
                                <n-form-item label="No. Interior (edificio, caseta, etc.)" path="interior_number">
                                    <n-input v-model:value="form.interior_number" placeholder="Opcional" />
                                </n-form-item>
                            </div>

                            <n-form-item label="Colonia" path="neighborhood">
                                <n-input v-model:value="form.neighborhood" />
                            </n-form-item>

                            <n-form-item label="Código Postal" path="zip_code">
                                <n-input v-model:value="form.zip_code" />
                            </n-form-item>

                            <n-form-item label="Municipio / Alcaldía" path="municipality">
                                <n-input v-model:value="form.municipality" />
                            </n-form-item>

                            <n-form-item label="Estado" path="state">
                                <n-input v-model:value="form.state" />
                            </n-form-item>

                            <n-form-item label="Referencias" path="address_references" class="md:col-span-2">
                                <n-input v-model:value="form.address_references" type="textarea" :rows="2" placeholder="Fachada color, cerca de..." />
                            </n-form-item>

                             <n-form-item label="Entre Calles" path="cross_streets">
                                <n-input v-model:value="form.cross_streets" placeholder="Entre calle A y calle B" />
                            </n-form-item>
                        </div>
                    </div>

                    <!-- SECCIÓN 4: DATOS BANCARIOS -->
                    <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 md:p-8 mb-6">
                        <div class="flex items-center gap-3 mb-6">
                            <div class="p-2 bg-green-50 rounded-lg text-green-600">
                                <n-icon size="24"><WalletOutline /></n-icon>
                            </div>
                            <div>
                                <h3 class="text-lg font-bold text-gray-800">Datos Bancarios</h3>
                                <p class="text-xs text-gray-400">Información para depósitos de nómina.</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <n-form-item label="Titular de la Cuenta" path="bank_account_holder">
                                <n-input v-model:value="form.bank_account_holder" placeholder="Tal como aparece en el estado de cuenta" />
                            </n-form-item>

                            <n-form-item label="Institución Bancaria" path="bank_name">
                                <n-input v-model:value="form.bank_name" placeholder="Ej. BBVA, Santander" />
                            </n-form-item>

                            <n-form-item label="CLABE Interbancaria" path="bank_clabe">
                                <n-input v-model:value="form.bank_clabe" placeholder="18 dígitos" maxlength="18" />
                            </n-form-item>

                            <n-form-item label="Número de Cuenta" path="bank_account_number">
                                <n-input v-model:value="form.bank_account_number" placeholder="Número de tarjeta o cuenta" />
                            </n-form-item>
                        </div>
                    </div>

                    <!-- SECCIÓN 5: BENEFICIARIOS -->
                    <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 md:p-8 mb-6">
                        <div class="flex items-center justify-between mb-6">
                            <div class="flex items-center gap-3">
                                <div class="p-2 bg-pink-50 rounded-lg text-pink-600">
                                    <n-icon size="24"><PeopleOutline /></n-icon>
                                </div>
                                <div>
                                    <h3 class="text-lg font-bold text-gray-800">Beneficiarios</h3>
                                    <p class="text-xs text-gray-400">En caso de fallecimiento o prestaciones.</p>
                                </div>
                            </div>
                            <n-button size="small" secondary type="primary" @click="addBeneficiary">
                                <template #icon><n-icon><AddOutline /></n-icon></template>
                                Agregar Beneficiario
                            </n-button>
                        </div>

                        <div v-if="form.beneficiaries.length === 0" class="text-center py-8 bg-gray-50 rounded-xl border border-dashed border-gray-300">
                            <n-text depth="3">No se han agregado beneficiarios</n-text>
                        </div>

                        <div v-for="(beneficiary, index) in form.beneficiaries" :key="index" class="bg-gray-50 rounded-xl p-4 mb-4 relative border border-gray-200">
                             <div class="absolute top-2 right-2">
                                <n-button circle size="small" type="error" ghost @click="removeBeneficiary(index)">
                                    <template #icon><n-icon><TrashOutline /></n-icon></template>
                                </n-button>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <n-form-item label="Nombre(s)" :show-label="index === 0">
                                    <n-input v-model:value="beneficiary.first_name" placeholder="Nombre" />
                                </n-form-item>
                                <n-form-item label="Apellido Paterno" :show-label="index === 0">
                                    <n-input v-model:value="beneficiary.paternal_surname" placeholder="Ap. Paterno" />
                                </n-form-item>
                                <n-form-item label="Apellido Materno" :show-label="index === 0">
                                    <n-input v-model:value="beneficiary.maternal_surname" placeholder="Ap. Materno" />
                                </n-form-item>
                                <n-form-item label="Fecha Nacimiento" :show-label="index === 0">
                                    <n-date-picker v-model:value="beneficiary.birth_date" type="date" class="w-full" />
                                </n-form-item>
                                <n-form-item label="Parentesco" :show-label="index === 0">
                                    <n-input v-model:value="beneficiary.relationship" placeholder="Ej. Esposa, Hijo" />
                                </n-form-item>
                            </div>
                        </div>
                    </div>

                    <!-- SECCIÓN 6: CONTACTOS DE EMERGENCIA -->
                    <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 md:p-8 mb-6">
                        <div class="flex items-center justify-between mb-6">
                            <div class="flex items-center gap-3">
                                <div class="p-2 bg-red-50 rounded-lg text-red-600">
                                    <n-icon size="24"><CallOutline /></n-icon>
                                </div>
                                <div>
                                    <h3 class="text-lg font-bold text-gray-800">Contactos de Emergencia</h3>
                                    <p class="text-xs text-gray-400">¿A quién llamamos en caso de accidente?</p>
                                </div>
                            </div>
                            <n-button size="small" secondary type="error" @click="addContact">
                                <template #icon><n-icon><AddOutline /></n-icon></template>
                                Agregar Contacto
                            </n-button>
                        </div>

                        <div v-if="form.contacts.length === 0" class="text-center py-8 bg-gray-50 rounded-xl border border-dashed border-gray-300">
                            <n-text depth="3">No hay contactos de emergencia</n-text>
                        </div>

                        <div v-for="(contact, index) in form.contacts" :key="index" class="bg-gray-50 rounded-xl p-4 mb-4 relative border border-gray-200">
                            <div class="absolute top-2 right-2">
                                <n-button circle size="small" type="error" ghost @click="removeContact(index)">
                                    <template #icon><n-icon><TrashOutline /></n-icon></template>
                                </n-button>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <n-form-item label="Nombre Completo" :show-label="index === 0">
                                    <n-input v-model:value="contact.name" placeholder="Nombre del contacto" />
                                </n-form-item>
                                <n-form-item label="Parentesco" :show-label="index === 0">
                                    <n-input v-model:value="contact.relationship" placeholder="Ej. Hermano" />
                                </n-form-item>
                                <n-form-item label="Teléfono" :show-label="index === 0">
                                    <n-input v-model:value="contact.phone" placeholder="Número de contacto" />
                                </n-form-item>
                            </div>
                        </div>
                    </div>

                    <!-- SECCIÓN 7: DOCUMENTACIÓN -->
                    <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 md:p-8 mb-8">
                        <div class="flex items-center gap-3 mb-6">
                            <div class="p-2 bg-gray-100 rounded-lg text-gray-600">
                                <n-icon size="24"><DocumentTextOutline /></n-icon>
                            </div>
                            <div>
                                <h3 class="text-lg font-bold text-gray-800">Documentación</h3>
                                <p class="text-xs text-gray-400">Sube los archivos digitales del expediente.</p>
                            </div>
                        </div>

                        <n-form-item>
                            <n-upload
                                multiple
                                directory-dnd
                                :default-upload="false"
                                @change="handleFileListChange"
                                class="w-full"
                            >
                                <n-upload-dragger>
                                    <div style="margin-bottom: 12px">
                                        <n-icon size="48" :depth="3"><CloudUploadOutline /></n-icon>
                                    </div>
                                    <n-text style="font-size: 16px">Haz clic o arrastra archivos aquí</n-text>
                                    <n-p depth="3" style="margin: 8px 0 0 0">
                                        Acta de nacimiento, INE, Comprobante de Domicilio, CV, etc.
                                    </n-p>
                                </n-upload-dragger>
                            </n-upload>
                        </n-form-item>
                    </div>

                    <!-- BOTONES ACCIÓN -->
                    <div class="flex justify-end gap-4 pb-12">
                        <n-button @click="goBack" size="large" round>
                            Cancelar
                        </n-button>
                        <n-button 
                            type="primary" 
                            size="large" 
                            round 
                            @click="submit" 
                            :loading="form.processing"
                            :disabled="form.processing"
                        >
                            <template #icon><n-icon :component="SaveOutline" /></template>
                            Guardar Expediente
                        </n-button>
                    </div>

                </n-form>
            </div>
        </div>
    </AppLayout>
</template>