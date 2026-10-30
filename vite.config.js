import { fileURLToPath, URL } from 'node:url';
import fs from 'node:fs';
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        tailwindcss(),
        vue(),
        {
            name: 'copy-public-to-dist',
            closeBundle() {
                try {
                    fs.cpSync('public', 'dist', { recursive: true });

                    const manifestPath = 'public/build/manifest.json';
                    if (fs.existsSync(manifestPath)) {
                        const manifest = JSON.parse(fs.readFileSync(manifestPath, 'utf8'));
                        const cssFile = manifest['resources/css/app.css']?.file || '';
                        const jsFile = manifest['resources/js/app.js']?.file || '';

                        const htmlContent = `<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sistem Presensi Santri TPQ</title>
    <link rel="manifest" href="/manifest.webmanifest">
    <meta name="theme-color" content="#059669">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="Presensi TPQ">
    <link rel="icon" id="dynamic-favicon" href="/favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    ${cssFile ? `<link rel="stylesheet" href="/build/${cssFile}">` : ''}
    <script>
        (function() {
            var saved = localStorage.getItem('theme');
            if (saved === 'dark' || (!saved && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.classList.add('dark');
            } else {
                document.documentElement.classList.remove('dark');
            }
        })();
    </script>
</head>
<body class="bg-background text-foreground min-h-screen selection:bg-primary selection:text-primary-foreground font-sans antialiased">
    <div id="app" class="min-h-screen"></div>
    ${jsFile ? `<script type="module" src="/build/${jsFile}"></script>` : ''}
</body>
</html>`;

                        fs.writeFileSync('dist/index.html', htmlContent, 'utf8');
                    }
                } catch (e) {
                    console.error('Failed to prepare dist folder:', e);
                }
            },
        },
    ],
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
        },
    },
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});

