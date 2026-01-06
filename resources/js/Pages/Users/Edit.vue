<script>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { 
    NCard, NForm, NFormItem, NInput, NButton, NIcon, NSelect, NUpload, 
    NUploadDragger, NText, NP, NDivider, NAlert, createDiscreteApi 
} from 'naive-ui';
import { 
    ArrowBackOutline, SaveOutline, PersonOutline, MailOutline, 
    KeyOutline, CloudUploadOutline, RefreshOutline, 
    CallOutline, ShieldCheckmarkOutline
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
        CallOutline,
        ShieldCheckmarkOutline
    },
    props: {
        user: {
            type: Object,
            required: true
        },
        roles: {
            type: Array,
            default: () => []
        }
    },
    setup(props) {
        // Obtenemos el rol actual del usuario (si tiene alguno asignado)
        const currentRole = props.user.roles && props.user.roles.length > 0 
            ? props.user.roles[0].name 
            : null;

        const form = useForm({
            _method: 'PUT', // Truco para permitir envío de archivos en actualización
            name: props.user.name,
            email: props.user.email,
            phone: props.user.phone,
            role: currentRole,
            password: '', // Vacío por defecto, solo se actualiza si el usuario escribe algo
            documents: [] 
        });

        const { message } = createDiscreteApi(['message']);

        return { 
            form, 
            message,
            PersonOutline,
            MailOutline,
            KeyOutline,
            RefreshOutline,
            SaveOutline,
            ArrowBackOutline,
            CallOutline,
            ShieldCheckmarkOutline
        };
    },
    data() {
        return {
            rules: {
                name: { required: true, message: 'El nombre es obligatorio', trigger: ['input', 'blur'] },
                email: { required: true, message: 'El correo es obligatorio', trigger: ['input', 'blur'] },
                phone: { required: true, message: 'El teléfono es obligatorio', trigger: ['input', 'blur'] },
                role: { required: true, message: 'Selecciona un rol', trigger: ['blur', 'change'] }
                // Password es opcional en edición, no agregamos regla required
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
            // No limpiamos error porque password no es obligatorio aquí, pero por si acaso
            if(this.form.errors.password) this.form.clearErrors('password');
            this.message.success("Nueva contraseña generada (debes guardar para aplicar)");
        },
        handleFileListChange(data) {
            this.form.documents = data.fileList.map(file => file.file);
            this.form.clearErrors('documents');
        },
        submit() {
            this.$refs.formRef?.validate((errors) => {
                if (!errors) {
                    // Usamos post hacia la ruta update debido al _method: 'PUT'
                    // Esto es necesario si permites subir archivos al editar
                    this.form.post(route('users.update', this.user.id), {
                        preserveScroll: true,
                        onSuccess: () => {
                            this.message.success('Usuario actualizado correctamente');
                        },
                        onError: () => {
                            this.message.error('Hubo un error al actualizar. Revisa los campos.');
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
    <AppLayout title="Editar Usuario">
        <template #header>
            <div class="flex items-center gap-4">
                <n-button circle secondary @click="goBack">
                    <template #icon>
                        <n-icon><ArrowBackOutline /></n-icon>
                    </template>
                </n-button>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Editar Usuario: {{ user.name }}
                </h2>
            </div>
        </template>

        <div class="py-8 min-h-screen">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
                
                <div class="bg-white/80 backdrop-blur-xl rounded-3xl shadow-lg border border-gray-100 overflow-hidden p-6 md:p-8">
                    
                    <div class="mb-6">
                        <h1 class="text-2xl font-bold text-gray-800">Actualizar Información</h1>
                        <p class="text-gray-500 text-sm mt-1">Modifica los datos del empleado o asigna un nuevo rol.</p>
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

                            <!-- Selector de Rol -->
                            <n-form-item 
                                label="Rol de Usuario" 
                                path="role"
                                :validation-status="form.errors.role ? 'error' : undefined"
                                :feedback="form.errors.role"
                            >
                                <n-select 
                                    v-model:value="form.role" 
                                    :options="roles"
                                    placeholder="Selecciona un rol"
                                    clearable
                                    @update:value="form.clearErrors('role')"
                                />
                            </n-form-item>

                            <!-- Contraseña -->
                            <n-form-item 
                                label="Nueva Contraseña (Opcional)" 
                                path="password"
                                :validation-status="form.errors.password ? 'error' : undefined"
                                :feedback="form.errors.password"
                            >
                                <n-input
                                    v-model:value="form.password"
                                    type="text"
                                    placeholder="Dejar en blanco para mantener la actual"
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
                                            title="Generar nueva aleatoria"
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
                                <strong>Nota:</strong> Si no deseas cambiar la contraseña del usuario, deja el campo en blanco.
                            </n-alert>
                        </div>

                        <n-divider>Agregar Documentación</n-divider>

                        <!-- Subida de Archivos -->
                        <n-form-item 
                            label="Subir nuevos archivos"
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
                                        Haz clic o arrastra archivos adicionales aquí
                                    </n-text>
                                    <n-p depth="3" style="margin: 8px 0 0 0">
                                        Los archivos que subas se agregarán a los existentes.
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
                                Actualizar Usuario
                            </n-button>
                        </div>

                    </n-form>
                </div>
            </div>
        </div>
    </AppLayout>
</template>