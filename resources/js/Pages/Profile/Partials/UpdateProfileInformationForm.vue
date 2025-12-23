<script setup>
import { ref } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { NCard, NForm, NFormItem, NInput, NButton, NAvatar, NUpload, NIcon, useMessage } from 'naive-ui';
import { PersonOutline, MailOutline, CloudUploadOutline, TrashOutline, SaveOutline } from '@vicons/ionicons5';

const props = defineProps({
    user: Object,
});

// Usamos el hook de mensajes de Naive UI para feedback elegante
const message = useMessage();

const form = useForm({
    _method: 'PUT',
    name: props.user.name,
    email: props.user.email,
    photo: null,
});

const verificationLinkSent = ref(null);
const photoPreview = ref(null);
const photoInput = ref(null);

const updateProfileInformation = () => {
    if (photoInput.value) {
        form.photo = photoInput.value.files[0];
    }

    form.post(route('user-profile-information.update'), {
        errorBag: 'updateProfileInformation',
        preserveScroll: true,
        onSuccess: () => {
            clearPhotoFileInput();
            message.success('Perfil actualizado correctamente');
        },
        onError: () => {
            message.error('Por favor revisa los errores en el formulario');
        }
    });
};

const sendEmailVerification = () => {
    verificationLinkSent.value = true;
};

const selectNewPhoto = () => {
    photoInput.value.click();
};

const updatePhotoPreview = () => {
    const photo = photoInput.value.files[0];

    if (! photo) return;

    const reader = new FileReader();

    reader.onload = (e) => {
        photoPreview.value = e.target.result;
    };

    reader.readAsDataURL(photo);
};

const deletePhoto = () => {
    router.delete(route('current-user-photo.destroy'), {
        preserveScroll: true,
        onSuccess: () => {
            photoPreview.value = null;
            clearPhotoFileInput();
            message.info('Foto de perfil eliminada');
        },
    });
};

const clearPhotoFileInput = () => {
    if (photoInput.value?.value) {
        photoInput.value.value = null;
    }
};
</script>

<template>
    <div class="relative">
        <!-- Tarjeta estilo iPhone -->
        <n-card 
            class="iphone-card" 
            :bordered="false"
            content-style="padding: 2rem;"
        >
            <template #header>
                <div class="flex items-center gap-3 mb-2">
                    <div class="p-2 bg-blue-50 rounded-xl text-blue-500">
                        <n-icon size="24"><PersonOutline /></n-icon>
                    </div>
                    <div>
                        <h3 class="text-xl font-bold text-gray-800">Información del Perfil</h3>
                        <p class="text-sm text-gray-400 font-medium">Actualiza tu información personal y correo electrónico.</p>
                    </div>
                </div>
            </template>

            <form @submit.prevent="updateProfileInformation">
                
                <!-- Sección de Foto -->
                <div v-if="$page.props.jetstream.managesProfilePhotos" class="mb-8 flex flex-col items-center sm:items-start sm:flex-row gap-6">
                    <input
                        id="photo"
                        ref="photoInput"
                        type="file"
                        class="hidden"
                        @change="updatePhotoPreview"
                    >

                    <!-- Previsualización de Foto -->
                    <div class="relative group">
                        <div v-show="!photoPreview" class="relative">
                            <n-avatar 
                                round 
                                :size="96" 
                                :src="user.profile_photo_url" 
                                class="shadow-lg border-4 border-white"
                                object-fit="cover"
                            />
                        </div>
                        <div v-show="photoPreview" class="relative">
                            <span
                                class="block rounded-full w-24 h-24 bg-cover bg-no-repeat bg-center shadow-lg border-4 border-white"
                                :style="'background-image: url(\'' + photoPreview + '\');'"
                            />
                        </div>
                    </div>

                    <div class="flex flex-col gap-3 justify-center">
                        <n-button secondary type="primary" round @click.prevent="selectNewPhoto">
                            <template #icon><n-icon><CloudUploadOutline /></n-icon></template>
                            Seleccionar Nueva Foto
                        </n-button>

                        <n-button
                            v-if="user.profile_photo_path"
                            secondary
                            type="error"
                            round
                            size="small"
                            @click.prevent="deletePhoto"
                        >
                            <template #icon><n-icon><TrashOutline /></n-icon></template>
                            Eliminar Foto
                        </n-button>
                        <span v-if="form.errors.photo" class="text-red-500 text-xs">{{ form.errors.photo }}</span>
                    </div>
                </div>

                <!-- Campos de Texto -->
                <div class="grid grid-cols-1 gap-6 max-w-xl">
                    <!-- Nombre -->
                    <n-form-item label="Nombre" :feedback="form.errors.name" :validation-status="form.errors.name ? 'error' : undefined">
                        <n-input 
                            v-model:value="form.name" 
                            placeholder="Tu nombre completo" 
                            size="large" 
                            round
                        >
                            <template #prefix>
                                <n-icon :component="PersonOutline" />
                            </template>
                        </n-input>
                    </n-form-item>

                    <!-- Email -->
                    <n-form-item label="Correo Electrónico" :feedback="form.errors.email" :validation-status="form.errors.email ? 'error' : undefined">
                        <n-input 
                            v-model:value="form.email" 
                            placeholder="tucorreo@ejemplo.com" 
                            size="large" 
                            round
                        >
                            <template #prefix>
                                <n-icon :component="MailOutline" />
                            </template>
                        </n-input>
                    </n-form-item>

                    <!-- Verificación de Email -->
                    <div v-if="$page.props.jetstream.hasEmailVerification && user.email_verified_at === null" class="bg-amber-50 p-4 rounded-xl border border-amber-100">
                        <p class="text-sm text-amber-800">
                            Tu dirección de correo no está verificada.
                        </p>
                        <button
                            class="mt-2 text-sm font-semibold text-amber-600 hover:text-amber-800 underline transition"
                            @click.prevent="sendEmailVerification"
                        >
                            Haz clic aquí para reenviar el correo de verificación.
                        </button>

                        <div v-show="verificationLinkSent" class="mt-2 font-medium text-sm text-green-600">
                            Se ha enviado un nuevo enlace de verificación a tu correo.
                        </div>
                    </div>
                </div>

                <!-- Botón Guardar -->
                <div class="mt-8 flex justify-end">
                    <n-button 
                        type="primary" 
                        size="large" 
                        round 
                        :loading="form.processing" 
                        :disabled="form.processing"
                        attr-type="submit"
                        class="shadow-md hover:shadow-lg transition-shadow"
                    >
                        <template #icon>
                            <n-icon><SaveOutline /></n-icon>
                        </template>
                        Guardar Cambios
                    </n-button>
                </div>
            </form>
        </n-card>
    </div>
</template>

<style scoped>
.iphone-card {
    background-color: rgba(255, 255, 255, 0.8);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border-radius: 24px;
    box-shadow: 0 10px 40px -10px rgba(0,0,0,0.05);
    border: 1px solid rgba(255, 255, 255, 0.5);
}
</style>