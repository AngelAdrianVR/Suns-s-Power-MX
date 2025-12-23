<script>
import AppLayout from '@/Layouts/AppLayout.vue';
import FileView from "@/Components/MyComponents/FileView.vue";
import { Head, Link, useForm } from '@inertiajs/vue3';
import { 
    NCard, NForm, NFormItem, NInput, NButton, NIcon, NSelect, NUpload, 
    NUploadDragger, NText, NP, NDivider, NAlert, createDiscreteApi 
} from 'naive-ui';
import { 
    ArrowBackOutline, SaveOutline, PersonOutline, MailOutline, 
    KeyOutline, BusinessOutline, CloudUploadOutline, RefreshOutline, 
    DocumentAttachOutline 
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
        // Iconos
        ArrowBackOutline,
        CloudUploadOutline,
        SaveOutline,
        FileView,
    },
    props: {
        user: {
            type: Object,
            required: true
        },
        branches: {
            type: Array,
            default: () => []
        }
    },
    setup(props) {
        // Inicializamos el formulario con los datos del usuario.
        // TRUCO IMPORTANTE: Para subir archivos en una actualización (PUT/PATCH),
        // Laravel/Inertia recomiendan usar POST simulando el método PUT con _method.
        const form = useForm({
            _method: 'PUT',
            name: props.user.name,
            email: props.user.email,
            password: '', // Vacío por defecto para no cambiarla
            branch_id: props.user.branch_id,
            documents: [] 
        });

        const { message } = createDiscreteApi(['message']);

        // Retornamos iconos para uso en :component
        return { 
            form, 
            message,
            PersonOutline,
            MailOutline,
            KeyOutline,
            BusinessOutline,
            RefreshOutline,
            SaveOutline,
            ArrowBackOutline
        };
    },
    data() {
        return {
            rules: {
                name: { required: true, message: 'El nombre es obligatorio', trigger: ['input', 'blur'] },
                email: { required: true, message: 'El correo es obligatorio', trigger: ['input', 'blur'] },
                // La contraseña NO es obligatoria en edición
                branch_id: { required: true, type: 'number', message: 'Selecciona una sucursal', trigger: ['blur', 'change'] }
            }
        };
    },
    computed: {
        branchOptions() {
            return this.branches.map(branch => ({
                label: branch.name,
                value: branch.id
            }));
        }
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
            // Limpiamos error de contraseña si existía alguno previo
            this.form.clearErrors('password');
            this.message.success("Nueva contraseña generada");
        },
        handleFileListChange(data) {
            this.form.documents = data.fileList.map(file => file.file);
            // Limpiamos el error de documentos si el usuario agrega nuevos archivos
            this.form.clearErrors('documents');
        },
        deleteFile(fileId) {
            this.user.media = this.user.media.filter(m => m.id !== fileId);
        },
        submit() {
            this.$refs.formRef?.validate((errors) => {
                if (!errors) {
                    // Usamos post hacia la ruta update, gracias al _method: 'PUT'
                    this.form.post(route('users.update', this.user.id), {
                        preserveScroll: true,
                        onSuccess: () => {
                            this.message.success('Usuario actualizado correctamente');
                        },
                        onError: () => {
                            this.message.error('Error al actualizar. Revisa los campos marcados en rojo.');
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
    <AppLayout :title="`Editar: ${user.name}`">
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
                        <p class="text-gray-500 text-sm mt-1">Modifica los datos del empleado o agrega nueva documentación.</p>
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
                            <!-- Agregamos validation-status y feedback para errores de servidor -->
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

                            <!-- Sucursal -->
                            <n-form-item 
                                label="Sucursal Asignada" 
                                path="branch_id"
                                :validation-status="form.errors.branch_id ? 'error' : undefined"
                                :feedback="form.errors.branch_id"
                            >
                                <n-select
                                    v-model:value="form.branch_id"
                                    :options="branchOptions"
                                    placeholder="Selecciona una sucursal"
                                    filterable
                                    @update:value="form.clearErrors('branch_id')"
                                >
                                    <template #arrow>
                                        <n-icon :component="BusinessOutline" />
                                    </template>
                                </n-select>
                            </n-form-item>

                            <!-- Contraseña (Opcional) -->
                            <n-form-item 
                                label="Actualizar Contraseña (Opcional)" 
                                path="password"
                                :validation-status="form.errors.password ? 'error' : undefined"
                                :feedback="form.errors.password"
                            >
                                <n-input
                                    v-model:value="form.password"
                                    type="text"
                                    placeholder="Dejar vacío para mantener la actual"
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
                                            title="Generar nueva contraseña aleatoria"
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
                        <div class="mb-8 mt-2" v-if="form.password">
                            <n-alert type="warning" :show-icon="true" class="rounded-xl">
                                <template #icon>
                                    <n-icon :component="KeyOutline" />
                                </template>
                                <strong>Cambio de Contraseña:</strong> Estás a punto de cambiar la contraseña de este usuario. 
                                Asegúrate de comunicarle la nueva clave temporal.
                            </n-alert>
                        </div>

                        <div v-if="user.media?.length" class="grid grid-cols-2 lg:grid-cols-3 gap-3 col-span-full mb-3">
                            <label class="col-span-full text-gray-700 dark:text-white text-sm">Archivos adjuntos</label>
                            <FileView v-for="file in user.media" :key="file" :file="file" :deletable="true"
                                @delete-file="deleteFile($event)" />
                        </div>

                        <n-divider>Agregar Documentación</n-divider>

                        <!-- Subida de Archivos -->
                        <n-form-item 
                            label="Adjuntar Archivos Nuevos"
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
                                        Haz clic o arrastra archivos aquí para agregar
                                    </n-text>
                                    <n-p depth="3" style="margin: 8px 0 0 0">
                                        Los archivos subidos se añadirán a los existentes.
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