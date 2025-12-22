<script setup>
import { NConfigProvider, NGlobalStyle, NLayout, NLayoutContent, NCard } from 'naive-ui';

// Definimos el tema personalizado "Susn's Style"
// Azul Profundo para seriedad y confianza.
// Amarillo Dorado para energía y focos de atención (efectos de luz).
const themeOverrides = {
    common: {
        primaryColor: '#0F3460', // Azul corporativo profundo
        primaryColorHover: '#16213E', // Azul más oscuro al pasar el mouse
        primaryColorPressed: '#0F3460', 
        primaryColorSuppl: '#1A1A2E', // Color suplementario oscuro
        borderRadius: '6px', // Bordes sutilmente redondeados, más profesional
    },
    Card: {
        borderColor: 'rgba(255, 193, 7, 0.3)', // Un borde muy sutil amarillento
        borderRadius: '12px',
        boxShadow: '0 8px 16px -4px rgba(15, 52, 96, 0.15)', // Sombra con tinte azulado
    },
    Input: {
        // Aquí logramos el "efecto de luz" amarillo
        borderHover: '1px solid #FFD700', // Borde dorado al pasar el mouse
        borderFocus: '1px solid #FFD700', // Borde dorado al escribir
        boxShadowFocus: '0 0 0 2px rgba(255, 215, 0, 0.2)', // Halo de luz dorada
        caretColor: '#FFD700', // El cursor también dorado
    },
    Button: {
        // Botón primario azul, pero con detalles
        fontWeight: '600',
    },
    Checkbox: {
        colorChecked: '#0F3460', // Check azul
        borderChecked: '1px solid #0F3460',
        checkMarkColor: '#FFD700', // La palomita dorada
    }
};
</script>

<template>
    <!-- Aplicamos el proveedor de configuración con el tema personalizado -->
    <n-config-provider :theme-overrides="themeOverrides">
        <n-global-style />
        
        <!-- Fondo con un gradiente sutil corporativo -->
        <n-layout class="min-h-screen login-background">
            <n-layout-content content-style="background: transparent; padding: 24px; display: flex; justify-content: center; align-items: center; min-height: 100vh;">
                <div class="w-full max-w-md animate-fade-in">
                    
                    <div class="flex justify-center mb-8">
                        <slot name="logo" />
                    </div>

                    <!-- NCard con una barra superior decorativa amarilla (el color secundario) -->
                    <div class="relative">
                        <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-blue-900 via-yellow-400 to-blue-900 rounded-t-lg z-10"></div>
                        <n-card class="shadow-2xl relative overflow-hidden" size="large" :bordered="false">
                            <!-- Decoración de fondo sutil -->
                            <div class="absolute top-0 right-0 -mt-4 -mr-4 w-24 h-24 bg-yellow-400 opacity-5 rounded-full blur-xl pointer-events-none"></div>
                            
                            <slot />
                        </n-card>
                    </div>

                </div>
            </n-layout-content>
        </n-layout>
    </n-config-provider>
</template>

<style scoped>
.login-background {
    /* Fondo azul muy claro/grisáceo para que contraste con la tarjeta */
    background: linear-gradient(135deg, #f3f4f6 0%, #e5e7eb 100%);
}
.animate-fade-in {
    animation: fadeIn 0.5s ease-out;
}
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>