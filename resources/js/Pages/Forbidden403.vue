<script setup>
import { ref } from 'vue';
import { Link, Head } from '@inertiajs/vue3';

const mouseX = ref(0);
const mouseY = ref(0);

// Función para el efecto parallax
const handleMouseMove = (event) => {
    // Calculamos la posición relativa al centro de la pantalla
    mouseX.value = event.clientX - window.innerWidth / 2;
    mouseY.value = event.clientY - window.innerHeight / 2;
};

const goBack = () => {
    // Intentar usar la navegación de Inertia si es posible, o fallback al historial del navegador
    if (window.history.length > 1) {
        window.history.back();
    } else {
        // Fallback si no hay historial previo
        import('@inertiajs/vue3').then(({ router }) => {
            router.visit('/dashboard');
        });
    }
};
</script>

<template>
    <Head title="Acceso Restringido" />
    <div 
        class="relative h-screen w-full flex items-center justify-center solar-grid overflow-hidden bg-slate-900 text-white font-sans" 
        @mousemove="handleMouseMove"
    >
        
        <!-- Elementos de Fondo (Ambiente de Seguridad) -->
        <!-- Degradado ligeramente más rojizo/oscuro para indicar error de permisos -->
        <div class="absolute inset-0 bg-gradient-to-b from-slate-900 via-slate-900 to-red-900/20 z-0"></div>
        
        <!-- Luz de Alerta (Efecto Parallax Layer 1) -->
        <div 
            class="absolute rounded-full bg-red-600 w-32 h-32 blur-[60px] opacity-40 z-10"
            :style="{ transform: `translate(${mouseX * -0.02}px, ${mouseY * -0.02}px)` }"
            style="transition: transform 0.1s ease-out; top: 10%; right: 20%;"
        ></div>

        <!-- Candado Flotante Decorativo (Efecto Parallax Layer 2) -->
        <div 
            class="absolute text-slate-700 opacity-20 z-10"
            :style="{ transform: `translate(${mouseX * 0.03}px, ${mouseY * 0.03}px) rotate(-15deg)` }"
            style="transition: transform 0.1s ease-out; bottom: 20%; left: 10%;"
        >
            <svg width="180" height="180" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                <path d="M12 2C9.243 2 7 4.243 7 7V10H6C4.895 10 4 10.895 4 12V20C4 21.105 4.895 22 6 22H18C19.105 22 20 21.105 20 20V12C20 10.895 19.105 10 18 10H17V7C17 4.243 14.757 2 12 2ZM9 7C9 5.346 10.346 4 12 4C13.654 4 15 5.346 15 7V10H9V7Z"/>
            </svg>
        </div>

        <!-- Contenido Central -->
        <div class="relative z-10 text-center max-w-3xl px-6">
            
            <!-- Icono Principal de Seguridad (SVG Inline para no depender de imagen externa) -->
            <div class="mb-6 relative inline-block group">
                <!-- Círculo de fondo con pulso rojo -->
                <div class="absolute inset-0 bg-red-500 rounded-full blur-xl opacity-20 group-hover:opacity-40 transition-opacity duration-500 animate-pulse"></div>
                
                <svg class="w-48 h-48 mx-auto text-red-500 drop-shadow-2xl relative z-10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
                    <path d="M9 12l2 2 4-4" stroke-opacity="0" class="hidden-check" /> <!-- Oculto para usar la X de error -->
                    <path d="M15 9l-6 6" />
                    <path d="M9 9l6 6" />
                </svg>

                <!-- Etiqueta 403 -->
                <div class="absolute top-10 -right-4 bg-red-600 text-white font-bold text-lg px-3 py-1 rounded border border-red-400 shadow-[0_0_15px_rgba(220,38,38,0.6)] transform rotate-12">
                    ERROR 403
                </div>
            </div>

            <!-- Títulos -->
            <h1 class="text-5xl md:text-6xl font-extrabold mb-4 text-transparent bg-clip-text bg-gradient-to-r from-red-200 via-white to-red-200">
                Acceso Restringido
            </h1>
            
            <h2 class="text-xl md:text-2xl font-semibold text-red-400 mb-6">
                Zona de otra sucursal
            </h2>

            <p class="text-slate-400 text-lg md:text-xl mb-10 leading-relaxed max-w-2xl mx-auto">
                No tienes permisos para visualizar esta orden de servicio. <br class="hidden md:inline">
                Esta información pertenece a una <strong>sucursal diferente</strong> Regresa y cambia de sucursal antes.
            </p>

            <!-- Botones -->
            <div class="flex flex-col sm:flex-row gap-4 justify-center items-center">
                <button 
                    @click="goBack" 
                    class="group px-8 py-3 bg-red-600 hover:bg-red-500 text-white rounded-lg font-semibold transition-all duration-300 shadow-lg shadow-red-900/40 flex items-center gap-2 transform hover:-translate-y-0.5"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 19-7-7 7-7"/><path d="M19 12H5"/></svg>
                    Volver Atrás
                </button>

                <Link 
                    href="/dashboard"
                    class="px-8 py-3 bg-slate-800 hover:bg-slate-700 border border-slate-700 text-slate-200 rounded-lg font-semibold transition-all duration-300 flex items-center gap-2 hover:border-red-400/50"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="7" height="9" x="3" y="3" rx="1"/><rect width="7" height="5" x="14" y="3" rx="1"/><rect width="7" height="9" x="14" y="12" rx="1"/><rect width="7" height="5" x="3" y="16" rx="1"/></svg>
                    Ir al Dashboard
                </Link>
            </div>
            
            <!-- Footer Técnico -->
            <div class="mt-12 pt-8 border-t border-slate-800 text-xs text-slate-500 font-mono">
                Security Protocol: <span class="text-red-500 font-bold">BLOCKING</span> | Cross-Branch Access: <span class="text-amber-500">DENIED</span> | Ref: 0x403_FORBIDDEN
            </div>

        </div>
    </div>
</template>

<style scoped>
/* Reutilizamos el grid solar pero funciona bien con el ambiente oscuro */
.solar-grid {
    background-image: 
        linear-gradient(rgba(255, 255, 255, 0.03) 1px, transparent 1px),
        linear-gradient(90deg, rgba(255, 255, 255, 0.03) 1px, transparent 1px);
    background-size: 40px 40px;
}
</style>