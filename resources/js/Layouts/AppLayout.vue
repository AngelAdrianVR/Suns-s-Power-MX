<script setup>
import { ref, computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { 
    NConfigProvider, 
    NMessageProvider, 
    NDialogProvider, 
    NGlobalStyle,
    NDrawer,
    NDrawerContent,
    NButton,
    NAvatar
} from 'naive-ui';
import Banner from '@/Components/Banner.vue';
import SideNav from '@/Components/MyComponents/SideNav.vue';
import NotificationsDropdown from '@/Components/MyComponents/NotificationsDropdown.vue'; // Asegúrate que la ruta sea correcta

defineProps({
    title: String,
});

const showMobileMenu = ref(false);
const page = usePage();

// Obtener notificaciones desde las props compartidas de Inertia
// Ajusta 'notifications' si tu backend las envía con otro nombre (ej. $page.props.auth.notifications)
const notifications = computed(() => page.props.notifications || []);
const user = computed(() => page.props.auth.user);

// Configuración del tema de Naive UI (Solo Light Mode)
const themeOverrides = {
    common: {
        primaryColor: '#2563EB', // blue-600
        primaryColorHover: '#3B82F6',
        primaryColorPressed: '#1D4ED8',
        baseColor: '#FFFFFF',
        borderRadius: '8px', // Bordes más modernos por defecto
    },
    Layout: {
        siderColor: 'transparent', 
        color: '#F3F4F6',
    }
};
</script>

<template>
    <n-config-provider :theme-overrides="themeOverrides">
        <!-- Estilos globales y proveedores de Naive UI -->
        <n-global-style />
        <n-message-provider>
            <n-dialog-provider>
                
                <!-- Contenedor principal -->
                <div class="h-screen w-full bg-gray-100 flex overflow-hidden text-gray-800 font-sans">
                    <Head :title="title" />
                    <Banner />

                    <!-- Desktop Sidebar -->
                    <div class="hidden md:block w-60 flex-shrink-0 h-full">
                        <SideNav />
                    </div>

                    <!-- Area de Contenido Principal -->
                    <div class="flex-1 flex flex-col h-full overflow-hidden relative">
                        
                        <!-- Header Desktop: Barra superior para notificaciones y perfil -->
                        <header class="hidden md:flex items-center justify-end px-8 py-3 bg-transparent z-10">
                            <div class="flex items-center gap-4">
                                <!-- Componente de Notificaciones -->
                                <NotificationsDropdown :notifications="notifications" />

                                <!-- Perfil de Usuario (Placeholder opcional o enlace) -->
                                <div class="flex items-center gap-3 pl-4 border-l border-gray-300 ml-2">
                                    <div class="text-right hidden lg:block">
                                        <p class="text-sm font-bold text-gray-700 leading-none">{{ user?.name }}</p>
                                        <p class="text-xs text-gray-500 mt-1">{{'Admin'}}</p>
                                    </div>
                                    <n-avatar 
                                        round 
                                        size="medium" 
                                        :src="user?.profile_photo_url" 
                                        class="shadow-sm border border-white cursor-default"
                                        :fallback-src="'https://ui-avatars.com/api/?name=' + (user?.name || 'User')"
                                    />
                                </div>
                            </div>
                        </header>

                        <!-- Mobile Header -->
                        <header class="md:hidden bg-white/80 backdrop-blur-md border-b border-gray-200 h-16 flex items-center justify-between px-4 sticky top-0 z-20">
                            <div class="flex items-center">
                                <Link :href="route('dashboard')" class="block w-10 h-10 rounded mr-3 shadow-sm">
                                    <img src="@/../../public/images/isologo-suns-power-mx.png" onerror="this.src='https://ui-avatars.com/api/?name=Solar+ERP&background=0D8ABC&color=fff&rounded=true&font-size=0.4'" alt="Logo" class="w-full h-full object-cover" />
                                </Link>
                            </div>
                            
                            <div class="flex items-center gap-3">
                                <!-- Notificaciones en Mobile -->
                                <NotificationsDropdown :notifications="notifications" />

                                <button @click="showMobileMenu = true" class="p-2 rounded-lg text-gray-500 hover:bg-gray-100 transition-colors">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                                    </svg>
                                </button>
                            </div>
                        </header>

                        <!-- Page Header (Title & Actions) -->
                        <div v-if="$slots.header" class="px-6 py-2 pb-6 flex items-center justify-between">
                            <slot name="header" />
                        </div>

                        <!-- Main Content Area -->
                        <main class="flex-1 overflow-y-auto px-4 md:px-6 pb-6 scroll-smooth">
                             <slot />
                        </main>
                    </div>

                    <!-- Mobile Drawer (Sidebar) -->
                    <n-drawer v-model:show="showMobileMenu" placement="left" :width="280">
                        <n-drawer-content body-content-style="padding: 0;">
                            <SideNav />
                        </n-drawer-content>
                    </n-drawer>

                </div>

            </n-dialog-provider>
        </n-message-provider>
    </n-config-provider>
</template>

<style>
/* Personalización fina del scrollbar */
::-webkit-scrollbar {
    width: 6px;
    height: 6px;
}
::-webkit-scrollbar-track {
    background: transparent;
}
::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 3px;
}
::-webkit-scrollbar-thumb:hover {
    background: #94a3b8;
}
</style>