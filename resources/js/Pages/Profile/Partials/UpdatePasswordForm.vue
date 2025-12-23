<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { NCard, NFormItem, NInput, NButton, NIcon, useMessage } from 'naive-ui';
import { LockClosedOutline, KeyOutline, CheckmarkCircleOutline } from '@vicons/ionicons5';

const message = useMessage();
const passwordInput = ref(null);
const currentPasswordInput = ref(null);

const form = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const updatePassword = () => {
    form.put(route('user-password.update'), {
        errorBag: 'updatePassword',
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            message.success('Contraseña actualizada con éxito');
        },
        onError: () => {
            if (form.errors.password) {
                form.reset('password', 'password_confirmation');
                // passwordInput.value?.focus(); // Naive UI inputs have different ref handling, simple reset is fine visually
            }

            if (form.errors.current_password) {
                form.reset('current_password');
                // currentPasswordInput.value?.focus();
            }
            message.error('Hubo un error al actualizar la contraseña');
        },
    });
};
</script>

<template>
    <div class="relative">
        <n-card 
            class="iphone-card" 
            :bordered="false"
            content-style="padding: 2rem;"
        >
            <template #header>
                <div class="flex items-center gap-3 mb-2">
                    <div class="p-2 bg-purple-50 rounded-xl text-purple-500">
                        <n-icon size="24"><LockClosedOutline /></n-icon>
                    </div>
                    <div>
                        <h3 class="text-xl font-bold text-gray-800">Actualizar Contraseña</h3>
                        <p class="text-sm text-gray-400 font-medium">Asegúrate de usar una contraseña larga y segura.</p>
                    </div>
                </div>
            </template>

            <form @submit.prevent="updatePassword">
                <div class="grid grid-cols-1 gap-6 max-w-xl">
                    
                    <!-- Contraseña Actual -->
                    <n-form-item label="Contraseña Actual" :feedback="form.errors.current_password" :validation-status="form.errors.current_password ? 'error' : undefined">
                        <n-input
                            ref="currentPasswordInput"
                            v-model:value="form.current_password"
                            type="password"
                            show-password-on="click"
                            placeholder="Ingresa tu contraseña actual"
                            size="large"
                            round
                        >
                            <template #prefix>
                                <n-icon :component="KeyOutline" />
                            </template>
                        </n-input>
                    </n-form-item>

                    <!-- Nueva Contraseña -->
                    <n-form-item label="Nueva Contraseña" :feedback="form.errors.password" :validation-status="form.errors.password ? 'error' : undefined">
                        <n-input
                            ref="passwordInput"
                            v-model:value="form.password"
                            type="password"
                            show-password-on="click"
                            placeholder="Ingresa la nueva contraseña"
                            size="large"
                            round
                        >
                             <template #prefix>
                                <n-icon :component="LockClosedOutline" />
                            </template>
                        </n-input>
                    </n-form-item>

                    <!-- Confirmar Contraseña -->
                    <n-form-item label="Confirmar Contraseña" :feedback="form.errors.password_confirmation" :validation-status="form.errors.password_confirmation ? 'error' : undefined">
                        <n-input
                            v-model:value="form.password_confirmation"
                            type="password"
                            show-password-on="click"
                            placeholder="Confirma la nueva contraseña"
                            size="large"
                            round
                        >
                             <template #prefix>
                                <n-icon :component="CheckmarkCircleOutline" />
                            </template>
                        </n-input>
                    </n-form-item>
                </div>

                <!-- Botón Guardar -->
                <div class="mt-8 flex justify-end">
                    <n-button 
                        type="primary" 
                        size="large" 
                        round 
                        color="#8a2be2" 
                        :loading="form.processing" 
                        :disabled="form.processing"
                        attr-type="submit"
                        class="shadow-md hover:shadow-lg transition-shadow"
                    >
                        Guardar Contraseña
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