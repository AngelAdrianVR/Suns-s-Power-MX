<script setup>
import { ref, computed, watch } from 'vue'; // Se importó 'watch'
import { Head, Link, usePage, router } from '@inertiajs/vue3';
import { 
    NConfigProvider, 
    NMessageProvider, 
    NDialogProvider, 
    NGlobalStyle,
    NDrawer,
    NDrawerContent,
    NButton,
    NAvatar,
    NSelect,
    NIcon
} from 'naive-ui';
import { StorefrontOutline, MenuOutline } from '@vicons/ionicons5';
import Banner from '@/Components/Banner.vue';
import SideNav from '@/Components/MyComponents/SideNav.vue';
import NotificationsDropdown from '@/Components/MyComponents/NotificationsDropdown.vue';

defineProps({
    title: String,
});

const showMobileMenu = ref(false);
const page = usePage();

// --- CONTROL DEL SIDEBAR CON LOCALSTORAGE ---
// Inicializa leyendo el localStorage para ver si ya había un estado guardado. 
// Si no hay nada, por defecto será 'true' (visible).
const isSidebarVisible = ref(
    typeof window !== 'undefined' && localStorage.getItem('isSidebarVisible') !== null
        ? localStorage.getItem('isSidebarVisible') === 'true'
        : true
);

// Observa los cambios de la variable y los guarda en el localStorage
watch(isSidebarVisible, (newValue) => {
    if (typeof window !== 'undefined') {
        localStorage.setItem('isSidebarVisible', newValue);
    }
});


// --- DATA INERTIA ---
const user = computed(() => page.props.auth.user);
const notifications = computed(() => page.props.notifications || []);

// --- LOGICA SUCURSALES ---
const currentBranchId = computed(() => page.props.auth.current_branch || page.props.auth.user?.branch_id);

const branchesOptions = computed(() => {
    return (page.props.branches || []).map(branch => ({
        label: branch.name,
        value: branch.id
    }));
});

const handleBranchChange = (value) => {
    router.post(route('branch.switch'), { 
        branch_id: value 
    }, {
        preserveScroll: true,
        onSuccess: () => {
            // Recarga exitosa
        }
    });
};

// --- LOGICA ROLES ---
const userRole = computed(() => {
    const roles = page.props.auth.roles || [];
    return roles.length > 0 
        ? roles[0].charAt(0).toUpperCase() + roles[0].slice(1) 
        : 'Usuario';
});

// Validar si es Admin para mostrar el selector
const isAdmin = computed(() => {
    const roles = page.props.auth.roles || [];
    return roles.some(r => ['admin', 'super admin', 'administrador', 'gerente'].includes(r.toLowerCase()));
});

// Configuración del tema de Naive UI
const themeOverrides = {
    common: {
        primaryColor: '#2563EB',
        primaryColorHover: '#3B82F6',
        primaryColorPressed: '#1D4ED8',
        baseColor: '#FFFFFF',
        borderRadius: '8px',
    },
    Layout: {
        siderColor: 'transparent', 
        color: '#F3F4F6',
    }
};
</script>

<template>
    <n-config-provider :theme-overrides="themeOverrides">
        <n-global-style />
        <n-message-provider>
            <n-dialog-provider>
                
                <div class="h-screen w-full bg-gray-100 flex overflow-hidden text-gray-800 font-sans">
                    <Head :title="title" />
                    <Banner />

                    <!-- Desktop Sidebar (Con animación de ocultamiento) -->
                    <div class="hidden lg:block flex-shrink-0 h-full transition-all duration-300 ease-in-out overflow-hidden"
                         :class="isSidebarVisible ? 'w-56' : 'w-0'">
                        <!-- Contenedor interno con ancho fijo para evitar que el contenido colapse feo -->
                        <div class="w-56 h-full">
                            <SideNav />
                        </div>
                    </div>

                    <!-- Area de Contenido Principal -->
                    <div class="flex-1 flex flex-col h-full overflow-hidden relative">
                        
                        <!-- Header Desktop -->
                        <header class="hidden lg:flex items-center justify-between px-8 py-3 bg-transparent z-10 gap-4">
                            
                            <!-- Botón para colapsar/mostrar SideNav -->
                            <n-button quaternary circle @click="isSidebarVisible = !isSidebarVisible" class="text-gray-500 hover:bg-white hover:shadow-sm transition-all">
                                <n-icon size="24" :component="MenuOutline" />
                            </n-button>

                            <div class="flex items-center gap-4">
                                <!-- Notificaciones -->
                                <NotificationsDropdown :notifications="notifications" />

                                <!-- Perfil de Usuario Integrado -->
                                <div class="flex items-center gap-3 pl-4 border-l border-gray-300 ml-2">
                                    <div class="text-right hidden lg:block">
                                        <p class="text-sm  text-gray-700 leading-none mb-1">{{ user?.name }}</p>
                                        
                                        <!-- OPCIÓN A: Selector de Sucursal (Solo Admins) -->
                                        <div v-if="isAdmin" class="w-40 scale-95 origin-right">
                                            <n-select
                                                :value="currentBranchId"
                                                :options="branchesOptions"
                                                @update:value="handleBranchChange"
                                                placeholder="Sucursal"
                                                size="tiny"
                                                class="font-medium"
                                                :consistent-menu-width="false"
                                            />
                                        </div>

                                        <!-- OPCIÓN B: Texto Rol (Usuarios normales) -->
                                        <p v-else class="text-xs text-blue-600 font-semibold">{{ userRole }}</p>
                                    </div>

                                    <n-avatar 
                                        round 
                                        size="medium" 
                                        :src="user?.profile_photo_url" 
                                        class="shadow-sm border border-white cursor-default flex-shrink-0"
                                        :fallback-src="'https://ui-avatars.com/api/?name=' + (user?.name || 'User')"
                                    />
                                </div>
                            </div>
                        </header>

                        <!-- Mobile Header -->
                        <header class="lg:hidden bg-white/80 backdrop-blur-md border-b border-gray-200 h-16 flex items-center justify-between px-4 sticky top-0 z-20">
                            <div class="flex items-center">
                                <Link :href="route('dashboard')" class="block w-10 h-10 rounded mr-3 shadow-sm">
                                    <img src="@/../../public/images/isologo-suns-power-mx.png" onerror="this.src='https://ui-avatars.com/api/?name=SP&background=0D8ABC&color=fff'" alt="Logo" class="w-full h-full object-cover" />
                                </Link>
                            </div>
                            
                            <div class="flex items-center gap-3">
                                <NotificationsDropdown :notifications="notifications" />

                                <button @click="showMobileMenu = true" class="p-2 rounded-lg text-gray-500 hover:bg-gray-100 transition-colors">
                                    <div class="relative">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                                        </svg>
                                    </div>
                                </button>
                            </div>
                        </header>

                        <!-- Page Header -->
                        <div v-if="$slots.header" class="px-6 py-2 pb-6 flex items-center justify-between">
                            <slot name="header" />
                        </div>

                        <!-- Main Content -->
                        <main class="flex-1 overflow-y-auto px-4 lg:px-6 pb-6 scroll-smooth">
                             <slot />
                        </main>
                    </div>

                    <!-- Mobile Drawer -->
                    <n-drawer v-model:show="showMobileMenu" placement="left" :width="280">
                        <n-drawer-content body-content-style="padding: 0;">
                            
                            <!-- Mobile User Profile & Branch Selector -->
                            <div class="p-5 bg-gradient-to-br from-gray-50 to-white border-b border-gray-200">
                                <div class="flex items-center gap-3 mb-4">
                                    <n-avatar 
                                        round 
                                        size="large" 
                                        :src="user?.profile_photo_url" 
                                        :fallback-src="'https://ui-avatars.com/api/?name=' + (user?.name || 'User')"
                                        class="border-2 border-white shadow-sm"
                                    />
                                    <div>
                                        <p class="font-bold text-gray-800 text-sm">{{ user?.name }}</p>
                                        <p class="text-xs text-blue-600 font-medium">{{ userRole }}</p>
                                    </div>
                                </div>

                                <!-- Selector Móvil (Solo Admin) -->
                                <div v-if="isAdmin">
                                    <p class="text-[10px] uppercase text-gray-400 font-bold mb-2 tracking-wider flex items-center gap-1">
                                        <n-icon :component="StorefrontOutline" /> Sucursal Activa
                                    </p>
                                    <n-select
                                        :value="currentBranchId"
                                        :options="branchesOptions"
                                        @update:value="handleBranchChange"
                                        size="small"
                                        class="shadow-sm"
                                    />
                                </div>
                            </div>

                            <!-- Navegación -->
                            <div class="pt-2">
                                <SideNav />
                            </div>
                            
                        </n-drawer-content>
                    </n-drawer>

                </div>

            </n-dialog-provider>
        </n-message-provider>
    </n-config-provider>
</template>

<style>
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