<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import LogoutOtherBrowserSessionsForm from '@/Pages/Profile/Partials/LogoutOtherBrowserSessionsForm.vue';
import UpdatePasswordForm from '@/Pages/Profile/Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from '@/Pages/Profile/Partials/UpdateProfileInformationForm.vue';
import { NMessageProvider } from 'naive-ui'; // Importante para las notificaciones

// Nota: No incluí DeleteUserForm ni TwoFactorAuthenticationForm en la importación para simplificar,
// ya que no proporcionaste esos archivos para editarlos y desentonarían.
// Si los necesitas, puedes descomentarlos y usarlos, aunque se verán con el estilo viejo.
// import DeleteUserForm from '@/Pages/Profile/Partials/DeleteUserForm.vue';
// import TwoFactorAuthenticationForm from '@/Pages/Profile/Partials/TwoFactorAuthenticationForm.vue';
// import SectionBorder from '@/Components/SectionBorder.vue';

defineProps({
    confirmsTwoFactorAuthentication: Boolean,
    sessions: Array,
});
</script>

<template>
    <AppLayout title="Perfil de Usuario">
        <!-- Envolvemos todo en NMessageProvider para que funcionen los useMessage() de los hijos -->
        <n-message-provider placement="top">
            <template #header>
                <h2 class="font-semibold text-2xl text-gray-800 leading-tight">
                    Mi Perfil
                </h2>
            </template>

            <div class="bg-gray-50 min-h-screen py-10">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    
                    <!-- Layout Grid para aprovechar el espacio en escritorio -->
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                        
                        <!-- Columna Izquierda -->
                        <div class="space-y-8">
                            <div v-if="$page.props.jetstream.canUpdateProfileInformation">
                                <UpdateProfileInformationForm :user="$page.props.auth.user" />
                            </div>
                        </div>

                        <!-- Columna Derecha -->
                        <div class="space-y-8">
                            <div v-if="$page.props.jetstream.canUpdatePassword">
                                <UpdatePasswordForm />
                            </div>

                            <div v-if="$page.props.jetstream.canManageTwoFactorAuthentication">
                                <!-- Placeholder o componente si se decidiera estilizar -->
                                <!-- <TwoFactorAuthenticationForm :requires-confirmation="confirmsTwoFactorAuthentication" /> -->
                            </div>

                            <LogoutOtherBrowserSessionsForm :sessions="sessions" />

                            <template v-if="$page.props.jetstream.hasAccountDeletionFeatures">
                                <!-- <DeleteUserForm /> -->
                            </template>
                        </div>

                    </div>
                </div>
            </div>
        </n-message-provider>
    </AppLayout>
</template>