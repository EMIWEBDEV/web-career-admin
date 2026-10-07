import { defineConfig } from "vite";
import laravel from "laravel-vite-plugin";
import path from "path";
import vue from "@vitejs/plugin-vue";
export default defineConfig({
    plugins: [
        laravel({
            input: [
                // Satu-satunya CSS aplikasi. Dipakai layout Blade lewat
                // @vite(['resources/css/evo-theme.css']). Halaman Vue memuatnya
                // via import di resources/js/app.js.
                "resources/css/evo-theme.css",
                "resources/js/app.js",
            ],
            // Simpan perubahan PHP apa pun -> browser langsung reload.
            // Default plugin hanya memantau routes/ + resources/views/, jadi edit
            // Controller/Model/config tidak pernah kelihatan tanpa restart. Daftar
            // di bawah menutup celah itu.
            refresh: [
                "app/**",
                "bootstrap/**",
                "config/**",
                "routes/**",
                "resources/views/**",
                "lang/**",
            ],
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
    ],
    server: {
        host: "localhost",

        // Port khusus project ini. 5173 sengaja dihindari karena itu default yang
        // diperebutkan semua project Vite lain yang jalan barengan.
        //
        // strictPort DIBIARKAN false: kalau 5180 kebetulan terpakai, Vite naik ke
        // 5181, 5182, dst. dan laravel-vite-plugin menulis port yang BENAR-BENAR
        // dipakai ke public/hot. Laravel membaca file itu, jadi beberapa project
        // bisa jalan bersamaan tanpa saling menimpa dan tanpa perlu diatur manual.
        //
        // hmr sengaja tidak di-set: default-nya mengikuti host+port server di atas,
        // jadi websocket-nya ikut pindah sendiri saat port bergeser. Menghardcode
        // hmr.port justru bikin hot-reload mati diam-diam ketika port bergeser.
        port: 5180,
        watch: {
            // Folder yang isinya berubah terus tapi tidak pernah jadi sumber modul.
            // Membiarkannya dipantau bikin watcher Windows kebanjiran event dan
            // HMR jadi telat/berhenti.
            ignored: [
                "**/vendor/**",
                "**/storage/**",
                "**/public/build/**",
                "**/bootstrap/cache/**",
                "**/.git/**",
            ],
        },
    },
    resolve: {
        alias: {
            // Versi Vue ber-compiler (template string dirakit saat runtime).
            vue: "vue/dist/vue.esm-bundler.js",

            // Komponen reusable Web Career (modal, dsb) — dipakai lintas halaman master.
            "@career": path.resolve(__dirname, "./resources/js/components/career"),
            // Utilitas non-komponen (JS murni). Dipakai lintas halaman yang
            // kedalaman foldernya berbeda-beda, jadi jalur relatif
            // ("../../../utils/…") cuma jadi sumber salah ketik.
            "@utils": path.resolve(__dirname, "./resources/js/utils"),
        },
    },
    build: {
        rollupOptions: {
            output: {
                manualChunks(id) {
                    if (!id.includes("node_modules")) {
                        return;
                    }

                    if (id.includes("@fullcalendar")) return "vendor-calendar";
                    if (id.includes("apexcharts") || id.includes("highcharts")) return "vendor-charts";
                    if (id.includes("element-plus")) return "vendor-ui";
                    if (id.includes("@formkit")) return "vendor-forms";
                    if (id.includes("quill")) return "vendor-editor";
                    if (id.includes("vue") || id.includes("@inertiajs")) return "vendor-vue";

                    return "vendor";
                },
            },
        },
    },
});
