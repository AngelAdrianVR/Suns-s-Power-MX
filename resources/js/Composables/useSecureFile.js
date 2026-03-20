import { ref } from 'vue';
import { createDiscreteApi } from 'naive-ui';

export function useSecureFile() {
    const { notification } = createDiscreteApi(['notification']);
    const isOpeningFile = ref(false);

    const openFileWithRetry = async (url) => {
        if (!url || isOpeningFile.value) return;
        isOpeningFile.value = true;

        // 1. Abrimos una nueva pestaña en blanco inmediatamente
        const newWindow = window.open('', '_blank');
        if (newWindow) {
            newWindow.document.write(`
                <html>
                <head>
                    <title>Comprobando seguridad...</title>
                    <meta charset="utf-8">
                    <style>
                        body { display:flex; flex-direction:column; justify-content:center; align-items:center; height:100vh; font-family:sans-serif; background:#f9fafb; color:#4b5563; margin:0; text-align:center; padding: 20px;}
                        .loader { border: 4px solid #f3f3f3; border-top: 4px solid #4f46e5; border-radius: 50%; width: 40px; height: 40px; animation: spin 1s linear infinite; margin-bottom: 20px; }
                        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
                    </style>
                </head>
                <body>
                    <div class="loader"></div>
                    <h2>Preparando documento</h2>
                    <p>El servidor está completando el escaneo de seguridad del archivo. Abriendo en breve...</p>
                </body>
                </html>
            `);
        } else {
            notification.info({ title: 'Procesando', content: 'Preparando archivo seguro...' });
        }

        let attempts = 0;
        const maxAttempts = 10;
        let isReady = false;
        let finalUrl = '';
        let fetchUrl = '';

        while (attempts < maxAttempts && !isReady) {
            try {
                const timestamp = new Date().getTime();
                const separator = url.includes('?') ? '&' : '?';
                finalUrl = `${url}${separator}t=${timestamp}`;

                // --- FIX DE CORS PARA DESARROLLO LOCAL ---
                fetchUrl = finalUrl;
                try {
                    const parsedUrl = new URL(finalUrl, window.location.origin);
                    const currentWindowHost = window.location.hostname;
                    if (['localhost', '127.0.0.1'].includes(parsedUrl.hostname) && ['localhost', '127.0.0.1'].includes(currentWindowHost)) {
                        parsedUrl.hostname = currentWindowHost;
                        parsedUrl.port = window.location.port;
                        fetchUrl = parsedUrl.toString();
                    }
                } catch(e) {}
                // -----------------------------------------

                const response = await fetch(fetchUrl, { 
                    method: 'HEAD',
                    cache: 'no-store'
                });
                
                if (response.ok) {
                    isReady = true; 
                } else {
                    throw new Error('El archivo aún no está listo o fue bloqueado');
                }
            } catch (error) {
                attempts++;
                await new Promise(resolve => setTimeout(resolve, 2000));
            }
        }

        if (isReady) {
            if (newWindow) {
                newWindow.location.replace(finalUrl);
            } else {
                window.open(finalUrl, '_blank');
            }
        } else {
            if (newWindow) newWindow.close();
            notification.error({
                title: 'No se pudo abrir',
                content: 'El archivo sigue bloqueado por el servidor o fue eliminado. Inténtalo más tarde.',
                duration: 6000
            });
        }

        isOpeningFile.value = false;
    };

    return {
        isOpeningFile,
        openFileWithRetry
    };
}