<script>
// WEB CAREER — Auth kandidat (login/register). Standalone: opt out of AppShell.
// Desain MENGIKUTI halaman /login HCIS (Auth/Login.vue) — hanya kalimat & field yang berbeda.
import axios from 'axios';
import { Head, Link, router } from '@inertiajs/vue3';
import { logout as clearSession, syncFromServer } from './careerSession';

const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

function readRedirect() {
    try { return new URLSearchParams(window.location.search).get('redirect') || '/kandidat/portal'; } catch (e) { return '/kandidat/portal'; }
}

export default {
    layout: null,
    components: { Head, Link },
    props: {
        mode: { type: String, default: 'login' },
    },
    data() {
        return {
            tab: this.mode === 'register' ? 'register' : 'login',
            redirectTarget: readRedirect(),
            showPassword: false,
            processing: false,
            errors: {},
            form: { nama: '', email: '', phone: '', password: '' },
            notice: { visible: false, type: 'info', message: '' },
            noticeTimer: null,
            redirectTimer: null,
            forgotOpen: false,
            forgotEmail: '',
            forgotErr: '',
            subsidiaries: [
                { src: '/logo/EMI.png', alt: 'PT EVO Manufacturing Indonesia' },
                { src: '/logo/ENB.png', alt: 'PT EVO Nusa Bersaudara' },
                { src: '/logo/GMN.png', alt: 'PT Graha Maju Nusantara' },
            ],
        };
    },
    computed: {
        isLogin() { return this.tab === 'login'; },
        // Navigasi antar halaman auth (bukan tab) — pertahankan ?redirect=.
        redirectQuery() {
            return this.redirectTarget && this.redirectTarget !== '/kandidat/portal'
                ? '?redirect=' + encodeURIComponent(this.redirectTarget) : '';
        },
        registerUrl() { return '/register' + this.redirectQuery; },
        loginUrl() { return '/login' + this.redirectQuery; },
        canSubmit() {
            if (this.processing) return false;
            if (!this.form.email || !this.form.password) return false;
            if (!this.isLogin && (!this.form.nama || !this.form.phone)) return false;
            return true;
        },
    },
    watch: {
        // Bila route berpindah (/login ↔ /register), sinkronkan tab dari prop mode.
        mode(m) { this.tab = m === 'register' ? 'register' : 'login'; },
    },
    mounted() {
        // Bila datang dari logout, bersihkan sesi klien (sessionStorage).
        try {
            if (new URLSearchParams(window.location.search).get('loggedout')) clearSession();
        } catch (e) { /* noop */ }
    },
    beforeUnmount() {
        clearTimeout(this.noticeTimer);
        clearTimeout(this.redirectTimer);
    },
    methods: {
        /* ── Flash / notice toast (mirip HCIS) ── */
        flashNotice(type, message) {
            if (!message) return;
            this.notice.type = type;
            this.notice.message = message;
            this.notice.visible = true;
            clearTimeout(this.noticeTimer);
            this.noticeTimer = setTimeout(() => (this.notice.visible = false), 6000);
        },
        clearErr(key) { if (this.errors[key]) delete this.errors[key]; },
        clearAll() { Object.keys(this.errors).forEach((k) => delete this.errors[k]); },
        // No. HP dipaksa format 62 (bukan 08). 0xxx → 62xxx, 8xxx → 628xxx.
        onPhoneInput() {
            let d = (this.form.phone || '').replace(/\D/g, '');
            if (d.startsWith('0')) d = '62' + d.slice(1);
            else if (d.startsWith('8')) d = '62' + d;
            this.form.phone = d.slice(0, 15);
            this.clearErr('phone');
        },
        /* ── Lupa sandi (popup email → redirect ke form ganti sandi) ── */
        openForgot() {
            this.forgotEmail = this.form.email || '';
            this.forgotErr = '';
            this.forgotOpen = true;
        },
        submitForgot() {
            if (!this.forgotEmail || !EMAIL_RE.test(this.forgotEmail)) {
                this.forgotErr = 'Email tidak valid.';
                return;
            }
            router.visit('/ganti-sandi?email=' + encodeURIComponent(this.forgotEmail));
        },
        validate() {
            this.clearAll();
            if (!this.form.email) this.errors.email = 'Email wajib diisi.';
            else if (!EMAIL_RE.test(this.form.email)) this.errors.email = 'Format email tidak valid.';
            if (!this.form.password) this.errors.password = 'Kata sandi wajib diisi.';
            else if (!this.isLogin && this.form.password.length < 6) this.errors.password = 'Minimal 6 karakter.';
            if (!this.isLogin) {
                if (!this.form.nama) this.errors.nama = 'Nama wajib diisi.';
                if (!this.form.phone) this.errors.phone = 'No. HP wajib diisi.';
                else if (!/^62\d{8,13}$/.test(this.form.phone)) this.errors.phone = 'No. HP harus format 62 (mis. 62812xxxxxxx).';
            }
            return Object.keys(this.errors).length === 0;
        },
        async submit() {
            if (!this.validate()) return;
            this.processing = true;
            try {
                const url = this.isLogin ? '/api/v1/login' : '/api/v1/register';
                const payload = this.isLogin
                    ? { email: this.form.email, password: this.form.password }
                    : { nama: this.form.nama, email: this.form.email, phone: this.form.phone, password: this.form.password };
                const res = await axios.post(url, payload, { headers: { Accept: 'application/json' } });
                // ResponseHelper membungkus data akun di key `result`.
                const user = res.data && res.data.result;
                if (user) syncFromServer(user);
                // Tujuan sesuai peran: admin/superadmin → dashboard /karir, kandidat → portal.
                // Hormati ?redirect= eksplisit (mis. balik ke formulir apply) bila bukan default.
                let dest = this.redirectTarget;
                if (dest === '/kandidat/portal' && user && ['ADMIN', 'SUPERADMIN'].includes(user.role)) {
                    dest = '/karir';
                }
                this.flashNotice('info', this.isLogin ? 'Berhasil masuk — mengalihkan…' : 'Akun berhasil dibuat — mengalihkan…');
                this.redirectTimer = setTimeout(() => router.visit(dest), 600);
            } catch (e) {
                this.processing = false;
                const r = e.response;
                if (r && r.status === 422 && r.data.errors) {
                    Object.keys(r.data.errors).forEach((k) => (this.errors[k] = Array.isArray(r.data.errors[k]) ? r.data.errors[k][0] : r.data.errors[k]));
                } else {
                    this.flashNotice('error', (r && r.data && r.data.message) || 'Terjadi kesalahan. Silakan coba lagi.');
                }
            }
        },
    },
};
</script>

<template>
    <Head :title="isLogin ? 'Masuk - EVO Group Career' : 'Daftar - EVO Group Career'">
        <meta name="robots" content="noindex, nofollow" />
        <link rel="preconnect" href="https://fonts.googleapis.com" />
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
        <link
            href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Fraunces:opsz,wght@9..144,300;9..144,400;9..144,500&display=swap"
            rel="stylesheet"
        />
    </Head>

    <div class="shell">
        <!-- BACKDROP — flowing morph blobs -->
        <div class="stage" aria-hidden="true">
            <svg class="blob-svg" viewBox="0 0 1600 900" preserveAspectRatio="xMidYMid slice">
                <defs>
                    <radialGradient id="cg1" cx="50%" cy="50%" r="50%">
                        <stop offset="0%" stop-color="#7C3AED" stop-opacity="0.65" />
                        <stop offset="100%" stop-color="#7C3AED" stop-opacity="0" />
                    </radialGradient>
                    <radialGradient id="cg2" cx="50%" cy="50%" r="50%">
                        <stop offset="0%" stop-color="#3B82F6" stop-opacity="0.55" />
                        <stop offset="100%" stop-color="#3B82F6" stop-opacity="0" />
                    </radialGradient>
                    <radialGradient id="cg3" cx="50%" cy="50%" r="50%">
                        <stop offset="0%" stop-color="#C9A24A" stop-opacity="0.35" />
                        <stop offset="100%" stop-color="#C9A24A" stop-opacity="0" />
                    </radialGradient>
                    <filter id="cgoo"><feGaussianBlur stdDeviation="60" /></filter>
                </defs>
                <g filter="url(#cgoo)">
                    <circle cx="200" cy="200" r="280" fill="url(#cg1)">
                        <animate attributeName="cx" values="200;350;200" dur="22s" repeatCount="indefinite" />
                        <animate attributeName="cy" values="200;320;200" dur="18s" repeatCount="indefinite" />
                    </circle>
                    <circle cx="1300" cy="700" r="360" fill="url(#cg2)">
                        <animate attributeName="cx" values="1300;1150;1300" dur="24s" repeatCount="indefinite" />
                        <animate attributeName="cy" values="700;580;700" dur="20s" repeatCount="indefinite" />
                    </circle>
                    <circle cx="900" cy="200" r="240" fill="url(#cg3)">
                        <animate attributeName="cx" values="900;1050;900" dur="26s" repeatCount="indefinite" />
                        <animate attributeName="cy" values="200;350;200" dur="22s" repeatCount="indefinite" />
                    </circle>
                </g>
            </svg>
            <div class="grid"></div>
        </div>

        <!-- Floating flash notice -->
        <transition name="v3-toast">
            <div v-if="notice.visible" class="v3-toast" :class="`v3-toast--${notice.type}`" role="alert">
                <i :class="notice.type === 'error' ? 'bi bi-exclamation-triangle-fill' : 'bi bi-info-circle-fill'"></i>
                <span>{{ notice.message }}</span>
                <button class="v3-toast__close" type="button" aria-label="Tutup" @click="notice.visible = false">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
        </transition>

        <!-- TOP -->
        <nav class="topbar animate-fade-down">
            <Link href="/" class="mark-login">
                <img src="/logo/EVOGROUP.png" alt="EVO Group" class="brand-logo" />
                <div class="mark-login-text">
                    <b>EVO Career</b>
                    <small>Portal Kandidat</small>
                </div>
            </Link>
            <div class="top-right">
                <Link href="/" class="top-link"><i class="bi bi-arrow-left"></i> Kembali ke Karir</Link>
                <span class="v-pill">
                    <span class="desktop-only">
                        <i class="bi bi-shield-fill-check" style="color: var(--indigo); font-size: 11px; margin-right: 5px; vertical-align: middle"></i>
                        Portal Karir Kandidat <span style="color: rgba(11, 16, 51, 0.15)">|</span>
                        <b style="color: var(--indigo); font-weight: 700">EVO Group</b>
                    </span>
                    <span class="mobile-only"><span class="status-dot"></span> Portal Karir</span>
                </span>
            </div>
        </nav>

        <!-- MAIN -->
        <main class="stage-grid">
            <section class="statement animate-fade-right">
                <span class="eyebrow"><span class="dot"></span>Portal Karir Kandidat</span>
                <h1 class="display">
                    Karier Impian<br />
                    <span>Dimulai di Sini.</span>
                </h1>
                <p class="lede">
                    Satu akun untuk melamar lowongan, mengikuti program Management Trainee, dan memantau progres
                    seleksimu. Silakan {{ isLogin ? 'masuk' : 'daftar' }} untuk melanjutkan.
                </p>
                <p class="sub-mobile">Satu akun untuk lamaran &amp; Management Trainee EVO Group</p>
            </section>

            <aside class="stage-side animate-fade-left" style="animation-delay: 0.1s">
                <div class="float-card">
                    <div class="card-head">
                        <h3>{{ isLogin ? 'Selamat Datang' : 'Buat Akun' }}</h3>
                        <span class="tag">{{ isLogin ? 'Autentikasi' : 'Registrasi' }}</span>
                    </div>

                    <form @submit.prevent="submit" novalidate>
                        <!-- Nama (register) -->
                        <div v-if="!isLogin" class="field">
                            <label for="c-nama"><span class="lbl-text"><i class="bi bi-person"></i> Nama Lengkap</span></label>
                            <div class="input-wrap" :class="{ 'is-error': errors.nama }">
                                <input id="c-nama" v-model="form.nama" type="text" placeholder="Nama sesuai KTP" autocomplete="name" @input="clearErr('nama')" />
                            </div>
                            <p v-if="errors.nama" class="help">{{ errors.nama }}</p>
                        </div>

                        <!-- Email -->
                        <div class="field">
                            <label for="c-email"><span class="lbl-text"><i class="bi bi-envelope"></i> Email</span></label>
                            <div class="input-wrap" :class="{ 'is-error': errors.email }">
                                <input id="c-email" v-model="form.email" type="email" placeholder="nama@email.com" autocomplete="email" autofocus @input="clearErr('email')" />
                            </div>
                            <p v-if="errors.email" class="help">{{ errors.email }}</p>
                        </div>

                        <!-- No. HP (register) -->
                        <div v-if="!isLogin" class="field">
                            <label for="c-phone"><span class="lbl-text"><i class="bi bi-telephone"></i> No. HP</span></label>
                            <div class="input-wrap" :class="{ 'is-error': errors.phone }">
                                <input id="c-phone" v-model="form.phone" type="tel" inputmode="numeric" placeholder="62812xxxxxxx" autocomplete="tel" @input="onPhoneInput" />
                            </div>
                            <p v-if="errors.phone" class="help">{{ errors.phone }}</p>
                        </div>

                        <!-- Password -->
                        <div class="field">
                            <label for="c-pass">
                                <span class="lbl-text"><i class="bi bi-key"></i> {{ isLogin ? 'Password' : 'Buat Password' }}</span>
                                <button v-if="isLogin" type="button" class="forgot-link" @click="openForgot">Lupa sandi?</button>
                            </label>
                            <div class="input-wrap" :class="{ 'is-error': errors.password }">
                                <input
                                    id="c-pass"
                                    v-model="form.password"
                                    :type="showPassword ? 'text' : 'password'"
                                    :placeholder="isLogin ? 'Masukan Password' : 'Minimal 6 karakter'"
                                    :autocomplete="isLogin ? 'current-password' : 'new-password'"
                                    @input="clearErr('password')"
                                />
                                <button type="button" class="toggle-eye" :aria-label="showPassword ? 'Sembunyikan' : 'Tampilkan'" @click="showPassword = !showPassword">
                                    <i :class="showPassword ? 'bi bi-eye-slash' : 'bi bi-eye'"></i>
                                </button>
                            </div>
                            <p v-if="errors.password" class="help">{{ errors.password }}</p>
                        </div>

                        <button type="submit" class="btn-login" :disabled="!canSubmit">
                            <span v-if="processing" class="spinner" aria-hidden="true"></span>
                            {{ processing ? 'Memproses…' : isLogin ? 'Login Akses' : 'Daftar Sekarang' }}
                            <i v-if="!processing" class="bi bi-arrow-right"></i>
                        </button>

                        <p class="switch-row">
                            <template v-if="isLogin">
                                Belum punya akun?
                                <Link :href="registerUrl" class="switch-link">Daftar sekarang</Link>
                            </template>
                            <template v-else>
                                Sudah punya akun?
                                <Link :href="loginUrl" class="switch-link">Masuk di sini</Link>
                            </template>
                        </p>

                        <div class="ver-row">
                            <span>EVO <b>Career</b></span>
                            <span>© 2026 EVO Group</span>
                        </div>
                    </form>
                </div>
            </aside>
        </main>

        <!-- SUBS -->
        <div class="subs animate-fade-up">
            <span class="lbl">Supported By</span>
            <div class="logos">
                <template v-for="(co, i) in subsidiaries" :key="co.alt">
                    <img :src="co.src" :alt="co.alt" loading="lazy" decoding="async" />
                    <span v-if="i < subsidiaries.length - 1" class="dv"></span>
                </template>
            </div>
            <span class="lbl">Group of Companies</span>
        </div>

        <!-- Popup lupa sandi -->
        <transition name="v3-toast">
            <div v-if="forgotOpen" class="forgot-mask" @click.self="forgotOpen = false">
                <div class="forgot-card">
                    <div class="forgot-head">
                        <h4><i class="bi bi-key"></i> Lupa Kata Sandi</h4>
                        <button type="button" class="forgot-x" @click="forgotOpen = false"><i class="bi bi-x-lg"></i></button>
                    </div>
                    <p class="forgot-desc">Masukkan email akunmu. Kamu akan diarahkan untuk membuat kata sandi baru.</p>
                    <div class="forgot-field" :class="{ err: forgotErr }">
                        <i class="bi bi-envelope"></i>
                        <input v-model="forgotEmail" type="email" placeholder="nama@email.com" @input="forgotErr = ''" @keyup.enter="submitForgot" />
                    </div>
                    <p v-if="forgotErr" class="forgot-help">{{ forgotErr }}</p>
                    <button type="button" class="forgot-btn" @click="submitForgot"><i class="bi bi-arrow-right-circle"></i> Lanjutkan</button>
                </div>
            </div>
        </transition>
    </div>
</template>

<style scoped>
* {
    box-sizing: border-box;
}
.bi {
    display: contents;
}
.sub-mobile {
    display: none;
}
.desktop-only {
    display: inline-flex;
    align-items: center;
    gap: 3px;
}
.mobile-only {
    display: none;
}

.shell {
    --bg-0: #eef0ff;
    --ink: #0b1033;
    --indigo: #4f46e5;
    --indigo-2: #6366f1;
    --violet: #7c3aed;
    --gold: #c9a24a;
    --text: #0f1235;
    --text-soft: #5b5f86;
    --line: rgba(11, 16, 51, 0.1);
    position: fixed;
    inset: 0;
    font-family: 'Plus Jakarta Sans', system-ui, sans-serif;
    color: var(--text);
    background: var(--bg-0);
    overflow-y: auto;
    overflow-x: hidden;
    display: flex;
    flex-direction: column;
    -webkit-font-smoothing: antialiased;
}

/* BACKDROP */
.stage {
    position: fixed;
    inset: 0;
    overflow: hidden;
    z-index: 0;
}
.blob-svg {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    animation: ambientFade 2s ease-out forwards;
    opacity: 0;
    will-change: transform, opacity;
}
.grid {
    position: absolute;
    inset: 0;
    background-image:
        linear-gradient(rgba(79, 70, 229, 0.05) 1px, transparent 1px),
        linear-gradient(90deg, rgba(79, 70, 229, 0.05) 1px, transparent 1px);
    background-size: 56px 56px;
    -webkit-mask-image: radial-gradient(ellipse at 70% 50%, #000 30%, transparent 75%);
    mask-image: radial-gradient(ellipse at 70% 50%, #000 30%, transparent 75%);
}

/* TOP NAV */
.topbar {
    position: relative;
    z-index: 3;
    padding: 24px 56px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-shrink: 0;
}
.mark-login {
    display: flex;
    align-items: center;
    gap: 14px;
    text-decoration: none;
    color: inherit;
}
.brand-logo {
    height: 48px;
    width: auto;
    object-fit: contain;
    filter: drop-shadow(0 4px 12px rgba(11, 16, 51, 0.15));
    transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
}
.brand-logo:hover {
    transform: scale(1.08) rotate(-2deg);
}
.mark-login-text {
    display: flex;
    flex-direction: column;
}
.mark-login b {
    font-weight: 700;
    font-size: 16px;
    letter-spacing: -0.01em;
}
.mark-login small {
    display: block;
    font-size: 11px;
    color: var(--text-soft);
    letter-spacing: 0.18em;
    text-transform: uppercase;
    margin-top: 2px;
}
.top-right {
    display: flex;
    align-items: center;
    gap: 24px;
}
.top-link {
    font-size: 13px;
    color: var(--text-soft);
    text-decoration: none;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: color 0.3s ease, transform 0.3s ease;
}
.top-link:hover {
    color: var(--ink);
    transform: translateY(-1px);
}
.v-pill {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    padding: 5px 14px;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.45);
    border: 1px solid rgba(255, 255, 255, 0.65);
    font-size: 10px;
    color: #475569;
    font-weight: 500;
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    box-shadow: 0 4px 12px rgba(11, 16, 51, 0.03);
    letter-spacing: 0.02em;
}

/* MAIN STAGE */
.stage-grid {
    position: relative;
    z-index: 2;
    flex: 1 0 auto;
    padding: 0 56px 24px;
    display: grid;
    grid-template-columns: 1fr 460px;
    align-items: center;
    gap: 40px;
    max-width: 1360px;
    margin: 0 auto;
    width: 100%;
}
.statement {
    position: relative;
}
.eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    padding: 8px 14px;
    border-radius: 999px;
    background: rgba(79, 70, 229, 0.1);
    color: var(--indigo);
    font-size: 12px;
    font-weight: 600;
    letter-spacing: 0.06em;
    margin-bottom: 24px;
}
.eyebrow .dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: var(--indigo);
    box-shadow: 0 0 12px var(--indigo);
}
.display {
    margin: 0;
    font-family: 'Plus Jakarta Sans', sans-serif;
    font-size: clamp(48px, 5.6vw, 84px);
    line-height: 1.02;
    font-weight: 700;
    letter-spacing: -0.035em;
    color: var(--ink);
}
.display span {
    display: inline-block;
    font-family: 'Fraunces', serif;
    font-style: italic;
    font-weight: 400;
    background: linear-gradient(120deg, var(--violet) 0%, var(--indigo) 45%, #2a6fdb 75%, var(--gold) 110%);
    -webkit-background-clip: text;
    background-clip: text;
    color: transparent;
    padding-right: 0.04em;
}
.lede {
    margin-top: 24px;
    max-width: 460px;
    color: var(--text-soft);
    font-size: 15px;
    line-height: 1.55;
}

/* FLOATING GLASS CARD */
.float-card {
    position: relative;
    background: linear-gradient(145deg, rgba(255, 255, 255, 0.85) 0%, rgba(255, 255, 255, 0.45) 100%);
    backdrop-filter: blur(24px) saturate(200%);
    -webkit-backdrop-filter: blur(24px) saturate(200%);
    border: 1px solid rgba(255, 255, 255, 0.9);
    border-radius: 36px;
    padding: 38px 44px 32px;
    box-shadow:
        inset 0 2px 4px rgba(255, 255, 255, 0.8),
        inset 0 -2px 10px rgba(255, 255, 255, 0.3),
        0 40px 80px -20px rgba(11, 16, 51, 0.25),
        0 16px 40px -10px rgba(79, 70, 229, 0.15);
    transform: translateY(-12px);
    will-change: transform;
    transition: transform 0.4s ease, box-shadow 0.4s ease;
}
.float-card:hover {
    transform: translateY(-18px);
    box-shadow:
        inset 0 2px 4px rgba(255, 255, 255, 0.8),
        inset 0 -2px 10px rgba(255, 255, 255, 0.3),
        0 50px 100px -20px rgba(11, 16, 51, 0.3),
        0 20px 50px -10px rgba(79, 70, 229, 0.2);
}
.card-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 30px;
}
.card-head h3 {
    font-size: 24px;
    font-weight: 800;
    margin: 0;
    letter-spacing: -0.02em;
    background: linear-gradient(135deg, var(--ink) 20%, var(--indigo) 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    color: transparent;
}
.card-head .tag {
    font-size: 10.5px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    color: var(--indigo);
    background: rgba(79, 70, 229, 0.1);
    padding: 6px 12px;
    border-radius: 8px;
}
.field {
    margin-bottom: 14px;
}
.field label {
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-size: 12px;
    font-weight: 600;
    color: var(--ink);
    margin-bottom: 8px;
}
.field label .lbl-text {
    display: flex;
    align-items: center;
    gap: 6px;
}
.field label .lbl-text i {
    font-size: 13px;
    color: var(--indigo);
}
.input-wrap {
    position: relative;
    display: flex;
    align-items: center;
    background: #ffffff;
    border: 1.5px solid rgba(11, 16, 51, 0.15);
    border-radius: 16px;
    box-shadow: 0 2px 10px rgba(11, 16, 51, 0.03);
    transition: border-color 0.3s ease, background-color 0.3s ease, box-shadow 0.3s ease;
    transform: translateZ(0);
    overflow: hidden;
}
.input-wrap:focus-within {
    border-color: var(--indigo);
    box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.12), 0 4px 12px rgba(11, 16, 51, 0.05);
}
.input-wrap.is-error {
    border-color: #ef4444;
    box-shadow: 0 0 0 4px rgba(239, 68, 68, 0.12);
}
.input-wrap input {
    flex: 1;
    border: 0;
    background: transparent;
    padding: 16px 20px;
    font: 500 15px 'Plus Jakarta Sans';
    color: var(--ink);
    outline: none;
    border-radius: inherit;
}
.input-wrap input::placeholder {
    color: #9ca0c6;
    font-weight: 400;
}
.input-wrap input:-webkit-autofill,
.input-wrap input:-webkit-autofill:hover,
.input-wrap input:-webkit-autofill:focus {
    -webkit-box-shadow: 0 0 0 1000px #ffffff inset !important;
    -webkit-text-fill-color: var(--ink) !important;
    caret-color: var(--ink);
    transition: background-color 50000s ease-in-out 0s;
}
.toggle-eye {
    background: none;
    border: 0;
    padding: 0 14px;
    color: #8a8fb8;
    cursor: pointer;
    font-size: 15px;
    transition: color 0.3s ease, transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
}
.toggle-eye:hover {
    color: var(--indigo);
    transform: scale(1.15);
}
.help {
    margin: 6px 2px 0;
    font-size: 12px;
    color: #dc2626;
}
.forgot-link {
    border: none;
    background: none;
    padding: 0;
    color: var(--indigo);
    font: 700 11.5px 'Plus Jakarta Sans';
    cursor: pointer;
}
.forgot-link:hover {
    color: var(--violet);
    text-decoration: underline;
}
/* Popup lupa sandi */
.forgot-mask {
    position: fixed;
    inset: 0;
    z-index: 60;
    display: grid;
    place-items: center;
    padding: 1.5rem;
    background: rgba(11, 16, 51, 0.35);
    backdrop-filter: blur(4px);
}
.forgot-card {
    width: 100%;
    max-width: 24rem;
    background: #fff;
    border-radius: 20px;
    padding: 1.5rem 1.6rem 1.6rem;
    box-shadow: 0 40px 80px -20px rgba(11, 16, 51, 0.4);
}
.forgot-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 0.5rem;
}
.forgot-head h4 {
    margin: 0;
    font-size: 1.05rem;
    font-weight: 900;
    color: var(--ink);
    display: flex;
    align-items: center;
    gap: 0.45rem;
}
.forgot-head h4 i {
    color: var(--indigo);
}
.forgot-x {
    border: none;
    background: #f1f5f9;
    width: 1.9rem;
    height: 1.9rem;
    border-radius: 0.5rem;
    color: var(--text-soft);
    cursor: pointer;
}
.forgot-desc {
    margin: 0 0 1rem;
    font-size: 0.85rem;
    color: var(--text-soft);
    line-height: 1.5;
}
.forgot-field {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.8rem 1rem;
    border: 1.5px solid rgba(11, 16, 51, 0.15);
    border-radius: 14px;
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
}
.forgot-field:focus-within {
    border-color: var(--indigo);
    box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.12);
}
.forgot-field.err {
    border-color: #ef4444;
    box-shadow: 0 0 0 4px rgba(239, 68, 68, 0.12);
}
.forgot-field i {
    color: #8a8fb8;
}
.forgot-field input {
    flex: 1;
    border: none;
    outline: none;
    background: none;
    font: 500 14.5px 'Plus Jakarta Sans';
    color: var(--ink);
}
.forgot-help {
    margin: 6px 2px 0;
    font-size: 12px;
    color: #dc2626;
}
.forgot-btn {
    width: 100%;
    margin-top: 1rem;
    padding: 0.85rem;
    border: none;
    border-radius: 14px;
    background: linear-gradient(135deg, var(--indigo) 0%, var(--violet) 100%);
    color: #fff;
    font: 700 14px 'Plus Jakarta Sans';
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    box-shadow: 0 14px 30px -10px rgba(124, 58, 237, 0.5);
}
.forgot-btn:hover {
    transform: translateY(-2px);
}
.btn-login {
    width: 100%;
    margin-top: 4px;
    padding: 16px 20px;
    border: 0;
    cursor: pointer;
    border-radius: 16px;
    background: linear-gradient(135deg, var(--indigo) 0%, var(--violet) 100%); /* selaras CTA admin: indigo → violet */
    color: #fff;
    font: 700 15px 'Plus Jakarta Sans';
    letter-spacing: 0.03em;
    box-shadow: 0 16px 36px -10px rgba(124, 58, 237, 0.5), inset 0 1px 0 rgba(255, 255, 255, 0.25);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
    transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1), box-shadow 0.3s ease, opacity 0.6s ease;
    position: relative;
    overflow: hidden;
}
.btn-login:hover:not(:disabled) {
    transform: translateY(-3px) scale(1.015);
    box-shadow: 0 24px 48px -10px rgba(79, 70, 229, 0.6);
}
.btn-login:active:not(:disabled) {
    transform: translateY(1px) scale(0.98);
}
.btn-login:disabled {
    opacity: 0.55;
    cursor: not-allowed;
    box-shadow: none;
}
.spinner {
    width: 18px;
    height: 18px;
    border: 2.5px solid rgba(255, 255, 255, 0.45);
    border-top-color: #fff;
    border-radius: 50%;
    animation: spin 0.7s linear infinite;
}
@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}
.switch-row {
    margin: 16px 0 0;
    text-align: center;
    font-size: 13px;
    font-weight: 500;
    color: var(--text-soft);
}
.switch-link {
    border: none;
    background: transparent;
    padding: 0;
    color: var(--indigo);
    font: 700 13px 'Plus Jakarta Sans';
    cursor: pointer;
}
.switch-link:hover {
    color: var(--violet);
    text-decoration: underline;
}
.ver-row {
    margin-top: 14px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 11.5px;
    color: var(--text-soft);
}
.ver-row b {
    color: var(--ink);
    font-weight: 700;
    letter-spacing: 0.05em;
}

/* BOTTOM SUBSIDIARIES */
.subs {
    position: relative;
    z-index: 3;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 16px 28px;
    margin: 0 56px 24px;
    background: rgba(255, 255, 255, 0.65);
    backdrop-filter: blur(25px);
    border: 1px solid rgba(255, 255, 255, 0.8);
    border-radius: 24px;
    box-shadow: 0 12px 36px -12px rgba(11, 16, 51, 0.1);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}
.subs:hover {
    transform: translateY(-4px);
    box-shadow: 0 16px 42px -12px rgba(11, 16, 51, 0.15);
}
.subs .lbl {
    font-size: 10.5px;
    color: var(--text-soft);
    letter-spacing: 0.22em;
    text-transform: uppercase;
    font-weight: 600;
}
.subs .logos {
    display: flex;
    align-items: center;
    gap: 32px;
}
.subs .logos img {
    height: 30px;
    opacity: 0.88;
    transition: opacity 0.3s ease, transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
}
.subs .logos img:hover {
    opacity: 1;
    transform: scale(1.1) translateY(-2px);
}
.subs .logos .dv {
    width: 1px;
    height: 22px;
    background: var(--line);
}

/* Toast */
.v3-toast {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 50;
    display: flex;
    align-items: center;
    gap: 12px;
    max-width: min(92vw, 380px);
    padding: 14px 16px;
    border-radius: 14px;
    font-size: 0.9rem;
    font-weight: 500;
    color: #fff;
    box-shadow: 0 18px 40px -12px rgba(0, 0, 0, 0.35);
    backdrop-filter: blur(10px);
}
.v3-toast--error {
    background: linear-gradient(120deg, #ef4444, #dc2626);
}
.v3-toast--info {
    background: linear-gradient(120deg, var(--indigo), var(--violet));
}
.v3-toast span {
    flex: 1;
}
.v3-toast__close {
    border: none;
    background: rgba(255, 255, 255, 0.18);
    color: #fff;
    width: 26px;
    height: 26px;
    border-radius: 8px;
    cursor: pointer;
    display: grid;
    place-items: center;
}
.v3-toast-enter-active,
.v3-toast-leave-active {
    transition: opacity 0.3s ease, transform 0.3s ease;
}
.v3-toast-enter-from,
.v3-toast-leave-to {
    opacity: 0;
    transform: translateX(40px);
}

/* Entrance animations */
@keyframes ambientFade {
    0% {
        opacity: 0;
        transform: scale(0.95) translateY(10px);
    }
    100% {
        opacity: 1;
        transform: scale(1) translateY(0);
    }
}
@keyframes revealDown {
    0% {
        opacity: 0;
        transform: translateY(-20px);
    }
    100% {
        opacity: 1;
        transform: translateY(0);
    }
}
@keyframes revealUp {
    0% {
        opacity: 0;
        transform: translateY(20px) scale(0.98);
    }
    100% {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}
@keyframes revealRight {
    0% {
        opacity: 0;
        transform: translateX(-50px);
    }
    100% {
        opacity: 1;
        transform: translateX(0);
    }
}
@keyframes revealPop {
    0% {
        opacity: 0;
        transform: translateX(60px) scale(0.95);
    }
    100% {
        opacity: 1;
        transform: translateX(0) scale(1);
    }
}
.animate-fade-down {
    animation: revealDown 1s cubic-bezier(0.22, 1, 0.36, 1) forwards;
    opacity: 0;
    will-change: transform, opacity;
}
.animate-fade-up {
    animation: revealUp 1s cubic-bezier(0.22, 1, 0.36, 1) forwards;
    animation-delay: 0.5s;
    opacity: 0;
    will-change: transform, opacity;
}
.animate-fade-right {
    animation: revealRight 1s cubic-bezier(0.22, 1, 0.36, 1) forwards;
    animation-delay: 0.15s;
    opacity: 0;
    will-change: transform, opacity;
}
.animate-fade-left {
    animation: revealPop 1.2s cubic-bezier(0.22, 1, 0.36, 1) forwards;
    animation-delay: 0.25s;
    opacity: 0;
    will-change: transform, opacity;
}

/* TABLET */
@media (max-width: 1099px) {
    .topbar {
        padding: 22px 36px;
    }
    .stage-grid {
        grid-template-columns: 1fr;
        gap: 32px;
        padding: 20px 36px 24px;
        align-items: center;
    }
    .display {
        font-size: clamp(48px, 6.5vw, 68px);
        line-height: 1.05;
    }
    .lede {
        margin-top: 18px;
        font-size: 14.5px;
        max-width: 100%;
    }
    .float-card {
        transform: none;
        max-width: 440px;
        margin: 0 auto;
        padding: 32px 30px 24px;
    }
    .subs {
        margin: 0 36px 24px;
        padding: 14px 22px;
    }
    .subs .logos {
        gap: 22px;
    }
    .subs .logos img {
        height: 24px;
    }
}

/* MOBILE */
@media (max-width: 767px) {
    .topbar {
        padding: 12px 18px;
        flex-wrap: wrap;
        gap: 8px;
    }
    .mark-login .brand-logo {
        height: 28px;
    }
    .mark-login b {
        font-size: 13px;
    }
    .mark-login small {
        font-size: 8.5px;
        letter-spacing: 0.1em;
    }
    .top-right {
        gap: 8px;
    }
    .top-link {
        display: none;
    }
    .v-pill {
        padding: 3px 8px;
        font-size: 8px;
        gap: 4px;
    }
    .statement {
        text-align: center;
    }
    .eyebrow {
        display: none !important;
    }
    .display {
        font-size: clamp(26px, 7.5vw, 32px);
        line-height: 1.1;
    }
    .lede {
        display: none !important;
    }
    .stage-grid {
        display: flex;
        flex-direction: column;
        justify-content: center;
        flex: 1 1 auto;
        padding: 8px 18px 12px;
        gap: 16px;
    }
    .stage-side {
        width: 100%;
    }
    .float-card {
        transform: none;
        max-width: 100%;
        padding: 22px 20px 18px;
        border-radius: 22px;
    }
    .card-head {
        margin-bottom: 16px;
    }
    .card-head h3 {
        font-size: 18px;
    }
    .field {
        margin-bottom: 10px;
    }
    .input-wrap input {
        font-size: 14px;
        padding: 13px 16px;
    }
    .subs {
        margin: 0 18px 12px;
        padding: 8px 14px;
        flex-direction: column;
        gap: 4px;
        text-align: center;
        border-radius: 14px;
    }
    .subs .lbl {
        font-size: 8px;
    }
    .subs .logos {
        gap: 12px;
        flex-wrap: wrap;
        justify-content: center;
    }
    .subs .logos img {
        height: 18px;
    }
    .subs .logos .dv {
        display: none;
    }
    .sub-mobile {
        display: block;
        font-size: 12.5px;
        color: var(--text-soft);
        font-weight: 500;
        margin-top: 8px;
    }
    .desktop-only {
        display: none !important;
    }
    .mobile-only {
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .status-dot {
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background: #10b981;
        box-shadow: 0 0 8px #10b981;
    }
}
</style>

<style>
html,
body,
#app {
    background: #eef0ff !important;
}
</style>
