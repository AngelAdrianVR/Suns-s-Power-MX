<script>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { 
    NCard, NForm, NFormItem, NInput, NButton, NIcon, NSelect, NUpload, 
    NUploadDragger, NText, NP, NDivider, NAlert, createDiscreteApi 
} from 'naive-ui';
import { 
    ArrowBackOutline, SaveOutline, PersonOutline, MailOutline, 
    KeyOutline, BusinessOutline, CloudUploadOutline, RefreshOutline, 
    DocumentAttachOutline, CallOutline
} from '@vicons/ionicons5';

export default {
    components: {
        AppLayout,
        Head,
        Link,
        NCard,
        NForm,
        NFormItem,
        NInput,
        NButton,
        NIcon,
        NSelect,
        NUpload,
        NUploadDragger,
        NText,
        NP,
        NDivider,
        NAlert,
        ArrowBackOutline,
        CloudUploadOutline, 
        SaveOutline,
        CallOutline
    },
    // Eliminamos props de branches ya que no se usan
    props: {},
    setup() {
        const form = useForm({
            name: '',
            email: '',
            phone: '',
            password: '',
            // Eliminamos branch_id del formulario frontend
            documents: [] 
        });

        const { message } = createDiscreteApi(['message']);

        return { 
            form, 
            message,
            PersonOutline,
            MailOutline,
            KeyOutline,
            BusinessOutline,
            RefreshOutline,
            SaveOutline,
            ArrowBackOutline,
            CallOutline
        };
    },
    data() {
        return {
            rules: {
                name: { required: true, message: 'El nombre es obligatorio', trigger: ['input', 'blur'] },
                email: { required: true, message: 'El correo es obligatorio', trigger: ['input', 'blur'] },
                phone: { required: true, message: 'El teléfono es obligatorio', trigger: ['input', 'blur'] },
                password: { required: true, message: 'La contraseña es obligatoria', trigger: ['input', 'blur'] },
                // Eliminamos regla de branch_id
            }
        };
    },
    computed: {
        // Eliminamos branchOptions
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
                    this.form.post(route('users.store'), {
                        preserveScroll: true,
                        onSuccess: () => {
                            this.message.success('Usuario creado correctamente');
                        },
                        onError: () => {
                            this.message.error('Hubo un error al crear el usuario. Revisa los campos marcados en rojo.');
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
                    <template #icon>
                        <n-icon><ArrowBackOutline /></n-icon>
                    </template>
                </n-button>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Crear Nuevo Usuario
                </h2>
            </div>
        </template>

        <div class="py-8 min-h-screen">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
                
                <div class="bg-white/80 backdrop-blur-xl rounded-3xl shadow-lg border border-gray-100 overflow-hidden p-6 md:p-8">
                    
                    <div class="mb-6">
                        <h1 class="text-2xl font-bold text-gray-800">Información del Empleado</h1>
                        <p class="text-gray-500 text-sm mt-1">Completa el formulario para dar de alta un nuevo usuario en la sucursal actual.</p>
                    </div>

                    <n-divider />

                    <n-form
                        ref="formRef"
                        :model="form"
                        :rules="rules"
                        label-placement="top"
                        size="large"
                        class="mt-6"
                    >
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            
                            <!-- Nombre -->
                            <n-form-item 
                                label="Nombre Completo" 
                                path="name"
                                :validation-status="form.errors.name ? 'error' : undefined"
                                :feedback="form.errors.name"
                            >
                                <n-input 
                                    v-model:value="form.name" 
                                    placeholder="Ej. Juan Pérez"
                                    @input="form.clearErrors('name')"
                                >
                                    <template #prefix>
                                        <n-icon :component="PersonOutline" />
                                    </template>
                                </n-input>
                            </n-form-item>

                            <!-- Email -->
                            <n-form-item 
                                label="Correo Electrónico" 
                                path="email"
                                :validation-status="form.errors.email ? 'error' : undefined"
                                :feedback="form.errors.email"
                            >
                                <n-input 
                                    v-model:value="form.email" 
                                    placeholder="juan.perez@empresa.com"
                                    @input="form.clearErrors('email')"
                                >
                                    <template #prefix>
                                        <n-icon :component="MailOutline" />
                                    </template>
                                </n-input>
                            </n-form-item>

                            <!-- Teléfono -->
                            <n-form-item 
                                label="Teléfono (Con whatsapp)" 
                                path="phone"
                                :validation-status="form.errors.phone ? 'error' : undefined"
                                :feedback="form.errors.phone"
                            >
                                <n-input 
                                    v-model:value="form.phone" 
                                    placeholder="Ej. 55 1234 5678"
                                    @input="form.clearErrors('phone')"
                                >
                                    <template #prefix>
                                        <n-icon :component="CallOutline" />
                                    </template>
                                </n-input>
                            </n-form-item>

                            <!-- Contraseña -->
                            <n-form-item 
                                label="Contraseña de Acceso" 
                                path="password"
                                :validation-status="form.errors.password ? 'error' : undefined"
                                :feedback="form.errors.password"
                            >
                                <n-input
                                    v-model:value="form.password"
                                    type="text"
                                    placeholder="Contraseña segura"
                                    show-password-on="mousedown"
                                    @input="form.clearErrors('password')"
                                >
                                    <template #prefix>
                                        <n-icon :component="KeyOutline" />
                                    </template>
                                    <template #suffix>
                                        <n-button 
                                            quaternary 
                                            circle 
                                            size="small" 
                                            @click="generatePassword"
                                            title="Generar aleatoria"
                                        >
                                            <template #icon>
                                                <n-icon :component="RefreshOutline" />
                                            </template>
                                        </n-button>
                                    </template>
                                </n-input>
                            </n-form-item>
                        </div>

                        <!-- Nota sobre contraseña -->
                        <div class="mb-8 mt-2">
                            <n-alert type="info" :show-icon="true" class="rounded-xl">
                                <template #icon>
                                    <n-icon :component="KeyOutline" />
                                </template>
                                <strong>Nota de Seguridad:</strong> La contraseña generada o escrita aquí es temporal. 
                                Se recomienda que el usuario la cambie inmediatamente después de su primer inicio de sesión.
                            </n-alert>
                        </div>

                        <n-divider>Documentación Adjunta</n-divider>

                        <!-- Subida de Archivos -->
                        <n-form-item 
                            label="Archivos Personales (Acta, INE, CV, etc.)"
                            :validation-status="form.errors.documents ? 'error' : undefined"
                            :feedback="form.errors.documents"
                        >
                            <n-upload
                                multiple
                                directory-dnd
                                :default-upload="false"
                                @change="handleFileListChange"
                                class="w-full"
                            >
                                <n-upload-dragger>
                                    <div style="margin-bottom: 12px">
                                        <n-icon size="48" :depth="3">
                                            <CloudUploadOutline />
                                        </n-icon>
                                    </div>
                                    <n-text style="font-size: 16px">
                                        Haz clic o arrastra archivos aquí
                                    </n-text>
                                    <n-p depth="3" style="margin: 8px 0 0 0">
                                        Soporta carga múltiple. Puedes adjuntar PDFs, imágenes, etc.
                                    </n-p>
                                </n-upload-dragger>
                            </n-upload>
                        </n-form-item>

                        <!-- Botones de Acción -->
                        <div class="flex justify-end gap-4 mt-8">
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
                                <template #icon>
                                    <n-icon :component="SaveOutline" />
                                </template>
                                Guardar Usuario
                            </n-button>
                        </div>

                    </n-form>
                </div>
            </div>
        </div>
    </AppLayout>
</template>