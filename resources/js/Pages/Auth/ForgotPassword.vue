<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AuthenticationCard from '@/Components/AuthenticationCard.vue';
import AuthenticationCardLogo from '@/Components/AuthenticationCardLogo.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
// Importamos componentes de UI básicos (si usas Jetstream, estos suelen estar disponibles)
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';

defineProps({
    status: String,
});

// Ahora el formulario necesita el email para saber a qué Admin de qué Branch notificar
const form = useForm({
    email: '',
});

const submit = () => {
    // Enviamos a la ruta personalizada que creamos en web.php
    form.post(route('password.request.notification'), {
        onFinish: () => form.reset('email'),
    });
};
</script>

<template>
    <Head title="Olvidaste tu contraseña" />

    <AuthenticationCard>
        <template #logo>
            <AuthenticationCardLogo />
        </template>

        <div class="mb-4 text-sm text-gray-600">
            Ingresa tu correo electrónico. Notificaremos a los administradores de tu sucursal para que te ayuden a restablecer tu contraseña.
        </div>

        <!-- Mensaje de éxito (status viene del back: ->with('status', ...)) -->
        <div v-if="status" class="mb-4 font-medium text-sm text-green-600 bg-green-50 p-3 rounded border border-green-200">
            {{ status }}
        </div>

        <form @submit.prevent="submit">
            <div>
                <InputLabel for="email" value="Correo Electrónico" />
                <TextInput
                    id="email"
                    v-model="form.email"
                    type="email"
                    class="mt-1 block w-full"
                    required
                    autofocus
                    autocomplete="username"
                    placeholder="ejemplo@sunspower.mx"
                />
                <InputError class="mt-2" :message="form.errors.email" />
            </div>

            <div class="flex items-center justify-end mt-4">
                <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                    Notificar a Supervisor
                </PrimaryButton>
            </div>
        </form>
    </AuthenticationCard>
</template>