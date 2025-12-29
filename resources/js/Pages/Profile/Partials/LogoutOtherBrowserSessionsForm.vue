<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { NCard, NButton, NIcon, NList, NListItem, NThing, NTag, NModal, NInput, useMessage } from 'naive-ui';
import { LaptopOutline, PhonePortraitOutline, LogOutOutline, GlobeOutline } from '@vicons/ionicons5';

defineProps({
    sessions: Array,
});

const message = useMessage();
const confirmingLogout = ref(false);
const passwordInput = ref(null);

const form = useForm({
    password: '',
});

const confirmLogout = () => {
    confirmingLogout.value = true;
    // setTimeout(() => passwordInput.value?.focus(), 250); // Naive focus handling is slightly different
};

const logoutOtherBrowserSessions = () => {
    form.delete(route('other-browser-sessions.destroy'), {
        preserveScroll: true,
        onSuccess: () => {
            closeModal();
            message.success('Se han cerrado las otras sesiones correctamente.');
        },
        onError: () => {
            // passwordInput.value?.focus();
            message.error('Contraseña incorrecta.');
        },
        onFinish: () => form.reset(),
    });
};

const closeModal = () => {
    confirmingLogout.value = false;
    form.reset();
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
                    <div class="p-2 bg-orange-50 rounded-xl text-orange-500">
                        <n-icon size="24"><GlobeOutline /></n-icon>
                    </div>
                    <div>
                        <h3 class="text-xl font-bold text-gray-800">Sesiones de Navegador</h3>
                        <p class="text-sm text-gray-400 font-medium">Administra y cierra tus sesiones activas en otros dispositivos.</p>
                    </div>
                </div>
            </template>

            <div class="max-w-xl text-sm text-gray-500 mb-6 leading-relaxed">
                Si es necesario, puedes cerrar todas las demás sesiones de navegador en todos tus dispositivos. A continuación se muestran algunas de tus sesiones recientes; sin embargo, esta lista puede no ser exhaustiva. Si crees que tu cuenta ha sido comprometida, también deberías actualizar tu contraseña.
            </div>

            <!-- Lista de Sesiones -->
            <div v-if="sessions.length > 0" class="mb-8">
                <n-list hoverable class="bg-transparent">
                    <n-list-item v-for="(session, i) in sessions" :key="i" class="rounded-xl hover:bg-gray-50 transition-colors">
                        <template #prefix>
                            <div class="p-3 bg-gray-100 rounded-full text-gray-500">
                                <n-icon v-if="session.agent.is_desktop" size="24"><LaptopOutline /></n-icon>
                                <n-icon v-else size="24"><PhonePortraitOutline /></n-icon>
                            </div>
                        </template>
                        
                        <n-thing>
                            <template #header>
                                <span class="text-gray-800 font-semibold">
                                    {{ session.agent.platform ? session.agent.platform : 'Desconocido' }} 
                                    - 
                                    {{ session.agent.browser ? session.agent.browser : 'Desconocido' }}
                                </span>
                            </template>
                            <template #description>
                                <div class="flex items-center gap-2 text-xs text-gray-400 mt-1">
                                    <span>{{ session.ip_address }}</span>
                                    <span class="mx-1">•</span>
                                    <span v-if="session.is_current_device" class="text-green-500 font-bold">Este dispositivo</span>
                                    <span v-else>Última actividad {{ session.last_active }}</span>
                                </div>
                            </template>
                        </n-thing>
                    </n-list-item>
                </n-list>
            </div>

            <div class="flex items-center mt-5">
                <n-button type="warning" secondary round @click="confirmLogout">
                    <template #icon><n-icon><LogOutOutline /></n-icon></template>
                    Cerrar Otras Sesiones
                </n-button>
            </div>

            <!-- Modal de Confirmación -->
            <n-modal
                v-model:show="confirmingLogout"
                preset="card"
                style="width: 90%; max-width: 500px; border-radius: 16px;"
                title="Cerrar Otras Sesiones"
                :bordered="false"
                size="huge"
                aria-modal="true"
            >
                <div class="py-2">
                    <p class="text-gray-600 mb-4">
                        Por favor ingresa tu contraseña para confirmar que deseas cerrar las sesiones de navegador en todos tus dispositivos.
                    </p>

                    <n-input
                        ref="passwordInput"
                        v-model:value="form.password"
                        type="password"
                        placeholder="Contraseña"
                        show-password-on="click"
                        round
                        size="large"
                        @keyup.enter="logoutOtherBrowserSessions"
                        :status="form.errors.password ? 'error' : undefined"
                    />
                    <span v-if="form.errors.password" class="text-red-500 text-xs mt-1 block">{{ form.errors.password }}</span>
                </div>

                <template #footer>
                    <div class="flex justify-end gap-3">
                        <n-button @click="closeModal" round>
                            Cancelar
                        </n-button>
                        <n-button 
                            type="primary" 
                            round 
                            :loading="form.processing"
                            @click="logoutOtherBrowserSessions"
                        >
                            Cerrar Sesiones
                        </n-button>
                    </div>
                </template>
            </n-modal>
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