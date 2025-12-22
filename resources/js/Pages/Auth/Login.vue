<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthenticationCard from '@/Components/AuthenticationCard.vue';
import AuthenticationCardLogo from '@/Components/AuthenticationCardLogo.vue';
import { NForm, NFormItem, NInput, NCheckbox, NButton, NAlert, NDivider } from 'naive-ui';

defineProps({
    canResetPassword: Boolean,
    status: String,
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.transform(data => ({
        ...data,
        remember: form.remember ? 'on' : '',
    })).post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <Head title="Iniciar Sesión" />

    <AuthenticationCard>
        <template #logo>
            <AuthenticationCardLogo class="w-20 h-20" />
        </template>

        <!-- Cabecera de la tarjeta con estilo -->
        <div class="text-center mb-8">
            <h2 class="text-2xl font-bold text-gray-800">
                Bienvenido
            </h2>
            <p class="text-gray-500 text-sm mt-1">Ingresa tus credenciales para acceder al ERP</p>
        </div>

        <n-alert v-if="status" type="success" class="mb-6" :show-icon="true" closable>
            {{ status }}
        </n-alert>

        <n-form @submit.prevent="submit" size="large">
            
            <!-- CORRECCIÓN DEL WARNING: Usamos :input-props (con dos puntos) para pasar el objeto -->
            <n-form-item label="Correo Electrónico" path="email" :validation-status="form.errors.email ? 'error' : undefined" :feedback="form.errors.email">
                <n-input
                    v-model:value="form.email"
                    placeholder="usuario@susns.com"
                    :input-props="{ type: 'email', required: true, autofocus: true, autocomplete: 'username' }"
                >
                    <template #prefix>
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400" viewBox="0 0 20 20" fill="currentColor">
                            <path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z" />
                            <path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z" />
                        </svg>
                    </template>
                </n-input>
            </n-form-item>

            <n-form-item label="Contraseña" path="password" class="mt-2" :validation-status="form.errors.password ? 'error' : undefined" :feedback="form.errors.password">
                <n-input
                    v-model:value="form.password"
                    type="password"
                    show-password-on="click"
                    placeholder="••••••••"
                    :input-props="{ required: true, autocomplete: 'current-password' }"
                >
                    <template #prefix>
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" />
                        </svg>
                    </template>
                </n-input>
            </n-form-item>

            <div class="flex items-center justify-between mt-4">
                <n-checkbox v-model:checked="form.remember">
                    <span class="text-sm text-gray-600">Recuérdame</span>
                </n-checkbox>

                <Link v-if="canResetPassword" :href="route('password.request')" class="text-xs font-semibold text-blue-900 hover:text-yellow-600 transition-colors">
                    ¿Olvidaste tu contraseña?
                </Link>
            </div>

            <div class="mt-8">
                <n-button
                    attr-type="submit"
                    type="primary"
                    block
                    size="large"
                    :loading="form.processing"
                    :disabled="form.processing"
                    class="font-bold shadow-lg shadow-blue-900/20"
                >
                    INGRESAR AL SISTEMA
                </n-button>
            </div>
        </n-form>
        
        <div class="mt-6 text-center">
            <n-divider dashed class="text-gray-400 text-xs">Sistema ERP Susn's Power MX</n-divider>
        </div>
    </AuthenticationCard>
</template>