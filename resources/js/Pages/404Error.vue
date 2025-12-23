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
    window.history.back();
};
</script>

<template>
    <!-- 
      El contenedor principal abarca toda la pantalla (h-screen). 
      El evento @mousemove activa el efecto parallax en los elementos hijos.
    -->
    <Head title="Página no encontrada" />
    <div 
        class="relative h-screen w-full flex items-center justify-center solar-grid overflow-hidden bg-slate-900 text-white font-sans" 
        @mousemove="handleMouseMove"
    >
        
        <!-- Elementos de Fondo (Cielo/Ambiente) -->
        <div class="absolute inset-0 bg-gradient-to-b from-slate-900 via-slate-800 to-slate-900 z-0"></div>
        
        <!-- Sol (Efecto Parallax Layer 1 - Se mueve opuesto al mouse) -->
        <div 
            class="absolute rounded-full bg-amber-400 w-32 h-32 blur-sm sun-glow z-10"
            :style="{ transform: `translate(${mouseX * -0.02}px, ${mouseY * -0.02}px)` }"
            style="transition: transform 0.1s ease-out; top: 15%; right: 15%;"
        ></div>

        <!-- Nube Decorativa (Efecto Parallax Layer 2 - Se mueve con el mouse) -->
        <div 
            class="absolute text-slate-700 opacity-20 z-10"
            :style="{ transform: `translate(${mouseX * 0.03}px, ${mouseY * 0.03}px)` }"
            style="transition: transform 0.1s ease-out; top: 20%; left: 10%;"
        >
            <svg width="200" height="120" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                <path d="M17.5,19c-3.037,0-5.5-2.463-5.5-5.5c0-1.405,0.531-2.684,1.405-3.664C12.833,9.263,12.215,9,11.5,9 C8.463,9,6,11.463,6,14.5S8.463,20,11.5,20h6c1.933,0,3.5-1.567,3.5-3.5S19.433,13,17.5,13V19z"/>
            </svg>
        </div>

        <!-- Contenido Central -->
        <div class="relative z-10 text-center max-w-2xl px-6">
            
            <!-- Ilustración SVG del Panel Solar Roto -->
            <div class="mb-8 relative inline-block">
                <img class="w-72" src="@/../../public/images/404.png" alt="">
                
                
                <!-- Etiqueta 404 -->
                <div class="absolute top-0 -right-8 bg-amber-500 text-slate-900 font-bold text-lg px-2 py-1 rounded shadow-lg transform rotate-12">
                    ERROR 404
                </div>
            </div>

            <!-- Títulos -->
            <h1 class="text-5xl md:text-6xl font-extrabold mb-4 text-transparent bg-clip-text bg-gradient-to-r from-blue-200 via-white to-blue-200">
                Página no encontrada
            </h1>
            
            <p class="text-slate-400 text-lg md:text-xl mb-8 leading-relaxed">
                Parece que la página que buscas no está generando energía. <br class="hidden md:inline">
                Puede que el enlace esté roto o que el recurso haya sido desinstalado.
            </p>

            <!-- Botones -->
            <div class="flex flex-col sm:flex-row gap-4 justify-center items-center">
                <button 
                    @click="goBack" 
                    class="group px-8 py-3 bg-blue-600 hover:bg-blue-500 text-white rounded-lg font-semibold transition-all duration-300 shadow-lg shadow-blue-900/50 flex items-center gap-2 transform hover:-translate-y-0.5"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 19-7-7 7-7"/><path d="M19 12H5"/></svg>
                    Volver Atrás
                </button>

                <!-- Usamos Link de Inertia para navegación SPA -->
                <Link 
                    href="/dashboard"
                    class="px-8 py-3 bg-slate-800 hover:bg-slate-700 border border-slate-700 text-slate-200 rounded-lg font-semibold transition-all duration-300 flex items-center gap-2 hover:border-blue-400/50"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="7" height="9" x="3" y="3" rx="1"/><rect width="7" height="5" x="14" y="3" rx="1"/><rect width="7" height="9" x="14" y="12" rx="1"/><rect width="7" height="5" x="3" y="16" rx="1"/></svg>
                    Ir al Dashboard
                </Link>
            </div>
            
            <!-- Footer Técnico -->
            <div class="mt-12 pt-8 border-t border-slate-800 text-xs text-slate-500 font-mono">
                System Status: <span class="text-red-400">OFFLINE</span> | Grid Connection: <span class="text-amber-500">UNSTABLE</span> | Ref: 0x404_NULL
            </div>

        </div>
    </div>
</template>

<style scoped>
/* Animación suave para el sol */
@keyframes pulse-glow {
    0%, 100% { box-shadow: 0 0 40px 10px rgba(251, 191, 36, 0.4); }
    50% { box-shadow: 0 0 60px 20px rgba(251, 191, 36, 0.6); }
}

.sun-glow {
    animation: pulse-glow 4s infinite ease-in-out;
}

/* Patrón de celdas solares para el fondo */
.solar-grid {
    background-image: 
        linear-gradient(rgba(255, 255, 255, 0.03) 1px, transparent 1px),
        linear-gradient(90deg, rgba(255, 255, 255, 0.03) 1px, transparent 1px);
    background-size: 40px 40px;
}
</style>