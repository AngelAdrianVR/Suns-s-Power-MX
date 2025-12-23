<template>
    <div class="h-full py-3 pl-3">
        <!-- Contenedor flotante tipo "iPhone" / Glassmorphism (Solo Light Mode) -->
        <div class="flex flex-col h-full bg-white/90 backdrop-blur-xl border border-white/50 shadow-[0_8px_30px_rgb(0,0,0,0.04)] rounded-3xl overflow-hidden transition-all duration-300">
            
            <!-- Logo Section -->
            <div class="flex flex-col items-center justify-center pt-5 pb-3">
                <div class="relative group cursor-pointer">
                    <div class="absolute -inset-1 bg-gradient-to-r from-blue-300 to-purple-300 rounded-full blur opacity-20 group-hover:opacity-40 transition duration-500"></div>
                    <figure class="relative w-12 h-12 bg-white rounded-2xl shadow-sm border border-gray-100 flex items-center justify-center overflow-hidden">
                        <!-- Placeholder para Logo -->
                        <img src="@/../../public/images/isologo-suns-power-mx.png" onerror="this.src='https://ui-avatars.com/api/?name=Solar+ERP&background=0D8ABC&color=fff&rounded=true&font-size=0.4'" alt="Logo" class="w-full h-full object-cover" />
                    </figure>
                </div>
                <h1 class="mt-2 font-bold text-[12px] tracking-widest uppercase text-blue-900">Sun's Power MX</h1>
            </div>

            <!-- Scrollable Menu Area -->
            <n-scrollbar class="flex-1 px-2 py-1">
                <div class="space-y-1">
                    <template v-for="(item, index) in filteredMenu" :key="index">
                        
                        <!-- Item Simple -->
                        <Link v-if="!item.children" :href="item.route ? route(item.route) : '#'" 
                            class="group flex items-center px-3 py-2.5 text-sm font-medium rounded-xl transition-all duration-200 ease-in-out relative"
                            :class="item.active 
                                ? 'bg-blue-50 text-blue-600 font-semibold shadow-sm border border-blue-100' 
                                : 'text-gray-500 hover:bg-gray-50 hover:text-gray-900'">
                            
                            <!-- Icono -->
                            <span class="mr-2.5 transition-transform duration-300 group-hover:scale-105" :class="item.active ? 'text-blue-600' : 'text-gray-400 group-hover:text-gray-600'" v-html="item.icon"></span>
                            
                            <!-- Etiqueta -->
                            <span class="truncate">{{ item.label }}</span>

                            <!-- Active Indicator (Punto) -->
                            <div v-if="item.active" class="absolute right-2 w-1.5 h-1.5 bg-blue-500 rounded-full shadow-sm"></div>
                        </Link>

                        <!-- Item con Dropdown -->
                        <div v-else class="space-y-1">
                            <button @click="toggleDropdown(item.label)" 
                                class="w-full flex items-center justify-between px-3 py-2.5 text-sm font-medium rounded-xl transition-all duration-200 text-gray-500 hover:bg-gray-50 hover:text-gray-900 group focus:outline-none"
                                :class="{'bg-gray-50 text-gray-800': item.isOpen}">
                                <div class="flex items-center truncate">
                                    <span class="mr-2.5 transition-colors" :class="item.isOpen ? 'text-gray-600' : 'text-gray-400 group-hover:text-gray-600'" v-html="item.icon"></span>
                                    <span class="truncate">{{ item.label }}</span>
                                </div>
                                <n-icon size="14" class="text-gray-400 transition-transform duration-300" :class="{'rotate-90': item.isOpen}">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M13.1717 12.0007L8.22192 7.05093L9.63614 5.63672L16.0001 12.0007L9.63614 18.3646L8.22192 16.9504L13.1717 12.0007Z"></path></svg>
                                </n-icon>
                            </button>

                            <!-- Submenú -->
                            <div v-show="item.isOpen" class="pl-3 pr-1 space-y-0.5 overflow-hidden">
                                <Link v-for="(subItem, subIndex) in item.children" :key="subIndex" 
                                    :href="route(subItem.route)"
                                    class="flex items-center px-3 py-2 text-[11px] font-medium rounded-lg transition-colors border-l-2 ml-2"
                                    :class="subItem.active 
                                        ? 'border-blue-500 bg-blue-50/50 text-blue-700' 
                                        : 'border-transparent text-gray-400 hover:text-gray-700 hover:border-gray-200'">
                                    {{ subItem.label }}
                                </Link>
                            </div>
                        </div>

                    </template>
                </div>
            </n-scrollbar>

            <!-- User Footer -->
            <div class="p-2 mt-auto">
                <div @click="$inertia.visit(route('profile.show'))" :class="{'border border-blue-700 !bg-blue-100' : route().current('profile.show')}"
                    class="bg-gray-50 rounded-2xl p-2 flex items-center justify-between border border-gray-100 cursor-pointer hover:bg-blue-100">
                    <div class="flex items-center space-x-2 overflow-hidden">
                         <n-avatar
                            round
                            :size="32"
                            :src="$page.props.auth.user.profile_photo_url"
                            :fallback-src="'https://ui-avatars.com/api/?name=' + $page.props.auth.user.name"
                        />
                        <div class="flex flex-col truncate max-w-[80px]">
                            <span class="text-[12px] font-bold text-gray-700 truncate">{{ $page.props.auth.user.name }}</span>
                            <span class="text-[10px] text-gray-400 truncate">Ver perfil</span>
                        </div>
                    </div>
                    
                    <!-- Logout Button -->
                    <n-tooltip trigger="hover" placement="right">
                        <template #trigger>
                            <button @click.stop="logout" class="text-gray-400 hover:text-red-500 transition-colors p-1.5 hover:bg-white hover:shadow-sm rounded-full">
                                <svg xmlns="http://www.w3.org/2000/svg" class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                                </svg>
                            </button>
                        </template>
                        Cerrar Sesión
                    </n-tooltip>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { Link, router } from '@inertiajs/vue3';
import { NScrollbar, NAvatar, NIcon, NTooltip } from 'naive-ui';

export default {
    name: 'SideNav',
    components: {
        Link,
        NScrollbar,
        NAvatar,
        NIcon,
        NTooltip
    },
    data() {
        return {
            openMenus: {}, 
        }
    },
    computed: {
        menuItems() {
            const current = (routePattern) => route().current(routePattern);
            const user = this.$page.props.auth.user;
            const hasPermission = (permission) => user.permissions?.includes(permission);

            const icons = {
                dashboard: '<svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" /></svg>',
                products: '<svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" /></svg>',
                orders: '<svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" /></svg>',
                purchases: '<svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" /></svg>',
                clients: '<svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" /></svg>',
                users: '<svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" /></svg>'
            };

            return [
                {
                    label: 'Dashboard',
                    icon: icons.dashboard,
                    route: 'dashboard',
                    active: current('dashboard'),
                    show: true
                },
                {
                    label: 'Productos',
                    icon: icons.products,
                    route: 'products.index',
                    active: current('products.*'),
                    show: hasPermission('Ver productos') || true
                },
                {
                    label: 'Órdenes Servicio', // Texto más corto para sidebar delgado
                    icon: icons.orders,
                    route: 'service-orders.index',
                    active: current('service-orders.*'),
                    show: hasPermission('Ver ordenes de servicio') || true
                },
                {
                    label: 'Compras',
                    icon: icons.purchases,
                    route: 'purchases.index',
                    active: current('purchases.*') || current('suppliers.*'),
                    show: hasPermission('Ver compras') || hasPermission('Ver proveedores') || true,
                    children: [
                        {
                            label: 'Órdenes',
                            route: 'purchases.index',
                            active: current('purchases.*'),
                            show: hasPermission('Ver compras') || true
                        },
                        {
                            label: 'Proveedores',
                            route: 'suppliers.index',
                            active: current('suppliers.*'),
                            show: hasPermission('Ver proveedores') || true
                        }
                    ]
                },
                {
                    label: 'Clientes',
                    icon: icons.clients,
                    route: 'clients.index',
                    active: current('clients.*'),
                    show: hasPermission('Ver clientes') || true
                },
                {
                    label: 'Usuarios',
                    icon: icons.users,
                    route: 'users.index',
                    active: current('users.*'),
                    show: hasPermission('Ver usuarios') || hasPermission('Ver personal') || true
                },
            ];
        },
        filteredMenu() {
            return this.menuItems
                .filter(item => item.show)
                .map(item => {
                    if (item.children) {
                        const visibleChildren = item.children.filter(child => child.show !== false);
                        const isActiveGroup = visibleChildren.some(child => child.active) || item.active;
                        
                        return {
                            ...item,
                            children: visibleChildren,
                            isOpen: this.openMenus[item.label] !== undefined ? this.openMenus[item.label] : isActiveGroup
                        };
                    }
                    return item;
                });
        }
    },
    methods: {
        toggleDropdown(label) {
            if (this.openMenus[label] === undefined) {
                const item = this.filteredMenu.find(i => i.label === label);
                this.openMenus[label] = !item.isOpen;
            } else {
                this.openMenus[label] = !this.openMenus[label];
            }
        },
        logout() {
            router.post(route('logout'));
        }
    }
}
</script>