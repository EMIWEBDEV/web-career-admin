<script>
// WEB CAREER — halaman hasil verifikasi email (magic link). Standalone.
// Status dari server: sukses | sudah | kadaluarsa | invalid.
// - sukses/sudah  : countdown berjalan lalu auto-redirect ke /login.
// - kadaluarsa    : tombol kirim ulang (email sudah diketahui server).
// - invalid       : kandidat bisa MENEMPEL tautan/token dari email secara
//                   manual (jaring pengaman bila tombol/link email rusak).
import axios from 'axios';
import { Head, Link, router } from '@inertiajs/vue3';

export default {
    layout: null,
    components: { Head, Link },
    props: {
        status: { type: String, default: 'invalid' },
        email: { type: String, default: null },
    },
    data() {
        return {
            detik: 10,
            countdownTimer: null,
            resending: false,
            resendMsg: '',
            resendErr: '',
            pasteVal: '',
            pasteErr: '',
        };
    },
    computed: {
        isSukses() { return this.status === 'sukses' || this.status === 'sudah'; },
    },
    mounted() {
        if (this.isSukses) {
            this.countdownTimer = setInterval(() => {
                this.detik -= 1;
                if (this.detik <= 0) {
                    clearInterval(this.countdownTimer);
                    router.visit('/login');
                }
            }, 1000);
        }
    },
    beforeUnmount() {
        clearInterval(this.countdownTimer);
    },
    methods: {
        keLogin() {
            clearInterval(this.countdownTimer);
            router.visit('/login');
        },
        async kirimUlang() {
            if (this.resending || !this.email) return;
            this.resending = true;
            this.resendMsg = '';
            this.resendErr = '';
            try {
                const res = await axios.post('/api/v1/kirim-verifikasi', { email: this.email }, { headers: { Accept: 'application/json' } });
                this.resendMsg = (res.data && res.data.message) || 'Email verifikasi telah dikirim ulang.';
            } catch (e) {
                const r = e.response;
                this.resendErr = (r && r.data && r.data.message) || 'Gagal mengirim ulang. Silakan coba lagi.';
            } finally {
                this.resending = false;
            }
        },
        // Terima tempelan berupa: URL lengkap dari email, query string, atau
        // token mentah — ekstrak token (dan email bila ada) lalu verifikasi.
        prosesTempel() {
            this.pasteErr = '';
            const raw = (this.pasteVal || '').trim();
            if (!raw) { this.pasteErr = 'Tempel dulu tautan atau token dari email kamu.'; return; }

            let token = '';
            let email = '';
            if (raw.includes('token=')) {
                try {
                    const qs = raw.includes('?') ? raw.split('?')[1] : raw;
                    const p = new URLSearchParams(qs);
                    token = (p.get('token') || '').trim();
                    email = (p.get('email') || '').trim();
                } catch (e) { /* jatuh ke validasi bawah */ }
            } else if (/^[A-Za-z0-9]{40,}$/.test(raw)) {
                token = raw; // token mentah 64 karakter
            }

            if (!token) {
                this.pasteErr = 'Format tidak dikenali. Salin utuh tautan dari email (mengandung "token=").';
                return;
            }
            const q = new URLSearchParams({ token });
            if (email) q.set('email', email);
            router.visit('/verifikasi-email?' + q.toString());
        },
    },
};
</script>

<template>
    <Head title="Verifikasi Email - EVO Group Career">
        <meta name="robots" content="noindex, nofollow" />
        <link rel="preconnect" href="https://fonts.googleapis.com" />
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
        <link
            href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
            rel="stylesheet"
        />
    </Head>

    <div class="shell">
        <div class="stage" aria-hidden="true"><div class="grid"></div></div>

        <nav class="topbar">
            <Link href="/" class="mark-login">
                <img src="/logo/EVOGROUP.png" alt="EVO Group" class="brand-logo" />
                <div class="mark-login-text">
                    <b>EVO Career</b>
                    <small>Portal Kandidat</small>
                </div>
            </Link>
        </nav>

        <main class="center-wrap">
            <div class="float-card">
                <!-- SUKSES / SUDAH -->
                <template v-if="isSukses">
                    <div class="icon-badge icon-badge--ok">
                        <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5" /></svg>
                    </div>
                    <p class="eyebrow-txt">VERIFIKASI AKUN</p>
                    <h1>{{ status === 'sudah' ? 'Email Sudah Terverifikasi' : 'Email Berhasil Diverifikasi!' }}</h1>
                    <p class="desc">
                        <template v-if="status === 'sudah'">
                            Alamat email <b>{{ email }}</b> memang sudah terverifikasi sebelumnya — kamu tidak perlu melakukan apa pun lagi.
                        </template>
                        <template v-else>
                            Terima kasih! Alamat email <b>{{ email }}</b> sudah terkonfirmasi dan akunmu kini aktif sepenuhnya. Silakan masuk untuk mulai melamar.
                        </template>
                    </p>
                    <div class="countdown">
                        <span class="countdown-num">{{ detik }}</span>
                        <span>Kamu akan diarahkan ke halaman masuk dalam <b>{{ detik }} detik</b>…</span>
                    </div>
                    <button type="button" class="btn-main" @click="keLogin">Masuk Sekarang <i class="bi bi-arrow-right"></i></button>
                </template>

                <!-- KADALUARSA -->
                <template v-else-if="status === 'kadaluarsa'">
                    <div class="icon-badge icon-badge--warn">
                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9" /><path d="M12 7v5l3 3" /></svg>
                    </div>
                    <p class="eyebrow-txt">TAUTAN KEDALUWARSA</p>
                    <h1>Waktunya Sudah Habis</h1>
                    <p class="desc">
                        Demi keamanan, tautan verifikasi hanya berlaku <b>30 menit</b> dan sekali pakai.
                        Tenang — cukup satu klik untuk mendapatkan tautan baru ke <b>{{ email }}</b>.
                    </p>
                    <button type="button" class="btn-main" :disabled="resending" @click="kirimUlang">
                        <span v-if="resending" class="spinner" aria-hidden="true"></span>
                        {{ resending ? 'Mengirim…' : 'Kirim Ulang Email Verifikasi' }}
                    </button>
                    <p v-if="resendMsg" class="feedback feedback--ok"><i class="bi bi-check-circle-fill"></i> {{ resendMsg }}</p>
                    <p v-if="resendErr" class="feedback feedback--err"><i class="bi bi-exclamation-triangle-fill"></i> {{ resendErr }}</p>
                </template>

                <!-- INVALID -->
                <template v-else>
                    <div class="icon-badge icon-badge--err">
                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18" /><path d="M6 6l12 12" /></svg>
                    </div>
                    <p class="eyebrow-txt">TAUTAN TIDAK VALID</p>
                    <h1>Hmm, Tautannya Tidak Dikenali</h1>
                    <p class="desc">
                        Tautan mungkin terpotong saat disalin, sudah terpakai, atau sudah diganti tautan yang lebih baru.
                        Coba <b>tempel utuh</b> tautan dari email terbaru kamu di bawah ini:
                    </p>
                    <div class="paste-field" :class="{ err: pasteErr }">
                        <i class="bi bi-link-45deg"></i>
                        <input v-model="pasteVal" type="text" placeholder="Tempel tautan / token dari email di sini" @input="pasteErr = ''" @keyup.enter="prosesTempel" />
                    </div>
                    <p v-if="pasteErr" class="feedback feedback--err">{{ pasteErr }}</p>
                    <button type="button" class="btn-main" @click="prosesTempel">Verifikasi Sekarang</button>
                    <p class="alt-row">Sudah terverifikasi sebelumnya? <Link href="/login" class="alt-link">Masuk di sini</Link></p>
                </template>

                <div class="ver-row">
                    <span>EVO <b>Career</b></span>
                    <span>© 2026 EVO Group</span>
                </div>
            </div>
        </main>
    </div>
</template>

<style scoped>
* { box-sizing: border-box; }
.bi { display: contents; }

.shell {
    --bg-0: #eef0ff;
    --ink: #0b1033;
    --indigo: #4f46e5;
    --violet: #7c3aed;
    --text-soft: #5b5f86;
    position: fixed;
    inset: 0;
    font-family: 'Plus Jakarta Sans', system-ui, sans-serif;
    color: var(--ink);
    background: var(--bg-0);
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    -webkit-font-smoothing: antialiased;
}
.stage { position: fixed; inset: 0; z-index: 0; overflow: hidden; }
.grid {
    position: absolute;
    inset: 0;
    background-image:
        linear-gradient(rgba(79, 70, 229, 0.05) 1px, transparent 1px),
        linear-gradient(90deg, rgba(79, 70, 229, 0.05) 1px, transparent 1px);
    background-size: 56px 56px;
    -webkit-mask-image: radial-gradient(ellipse at 50% 40%, #000 30%, transparent 75%);
    mask-image: radial-gradient(ellipse at 50% 40%, #000 30%, transparent 75%);
}
.topbar { position: relative; z-index: 2; padding: 24px 56px; display: flex; align-items: center; }
.mark-login { display: flex; align-items: center; gap: 14px; text-decoration: none; color: inherit; }
.brand-logo { height: 44px; width: auto; object-fit: contain; filter: drop-shadow(0 4px 12px rgba(11, 16, 51, 0.15)); }
.mark-login-text { display: flex; flex-direction: column; }
.mark-login b { font-weight: 700; font-size: 16px; }
.mark-login small { font-size: 11px; color: var(--text-soft); letter-spacing: 0.18em; text-transform: uppercase; margin-top: 2px; }

.center-wrap { position: relative; z-index: 1; flex: 1; display: grid; place-items: center; padding: 12px 18px 40px; }
.float-card {
    width: 100%;
    max-width: 480px;
    text-align: center;
    background: linear-gradient(145deg, rgba(255, 255, 255, 0.88) 0%, rgba(255, 255, 255, 0.55) 100%);
    backdrop-filter: blur(24px) saturate(200%);
    -webkit-backdrop-filter: blur(24px) saturate(200%);
    border: 1px solid rgba(255, 255, 255, 0.9);
    border-radius: 32px;
    padding: 40px 42px 28px;
    box-shadow:
        inset 0 2px 4px rgba(255, 255, 255, 0.8),
        0 40px 80px -20px rgba(11, 16, 51, 0.25),
        0 16px 40px -10px rgba(79, 70, 229, 0.15);
}
.icon-badge {
    width: 72px; height: 72px; border-radius: 22px;
    display: grid; place-items: center; margin: 0 auto 18px;
}
.icon-badge--ok { background: linear-gradient(135deg, #10b981, #059669); box-shadow: 0 16px 36px -8px rgba(16, 185, 129, 0.55); }
.icon-badge--warn { background: linear-gradient(135deg, #f59e0b, #d97706); box-shadow: 0 16px 36px -8px rgba(245, 158, 11, 0.55); }
.icon-badge--err { background: linear-gradient(135deg, #ef4444, #dc2626); box-shadow: 0 16px 36px -8px rgba(239, 68, 68, 0.5); }
.eyebrow-txt { margin: 0; font-size: 11px; font-weight: 800; letter-spacing: 0.16em; color: var(--violet); }
h1 { margin: 8px 0 0; font-size: 25px; font-weight: 800; letter-spacing: -0.02em; color: var(--ink); }
.desc { margin: 12px 0 0; font-size: 14px; line-height: 1.7; color: var(--text-soft); }
.desc b { color: var(--ink); }

.countdown {
    margin: 20px 0 0;
    display: flex; align-items: center; gap: 12px; text-align: left;
    background: rgba(79, 70, 229, 0.08);
    border: 1px solid rgba(79, 70, 229, 0.18);
    border-radius: 14px; padding: 12px 16px;
    font-size: 13px; color: var(--text-soft);
}
.countdown b { color: var(--indigo); }
.countdown-num {
    flex-shrink: 0;
    width: 40px; height: 40px; border-radius: 12px;
    display: grid; place-items: center;
    background: linear-gradient(135deg, var(--indigo), var(--violet));
    color: #fff; font-size: 17px; font-weight: 800;
}

.btn-main {
    width: 100%; margin-top: 18px; padding: 15px 20px;
    border: 0; cursor: pointer; border-radius: 16px;
    background: linear-gradient(135deg, var(--indigo) 0%, var(--violet) 100%);
    color: #fff; font: 700 14.5px 'Plus Jakarta Sans';
    display: flex; align-items: center; justify-content: center; gap: 10px;
    box-shadow: 0 16px 36px -10px rgba(124, 58, 237, 0.5);
    transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1), box-shadow 0.3s ease;
}
.btn-main:hover:not(:disabled) { transform: translateY(-2px); box-shadow: 0 24px 48px -10px rgba(79, 70, 229, 0.6); }
.btn-main:disabled { opacity: 0.6; cursor: not-allowed; }
.spinner {
    width: 17px; height: 17px;
    border: 2.5px solid rgba(255, 255, 255, 0.45); border-top-color: #fff;
    border-radius: 50%; animation: spin 0.7s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

.paste-field {
    margin-top: 18px;
    display: flex; align-items: center; gap: 8px;
    padding: 13px 15px; background: #fff;
    border: 1.5px solid rgba(11, 16, 51, 0.15); border-radius: 14px;
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
}
.paste-field:focus-within { border-color: var(--indigo); box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.12); }
.paste-field.err { border-color: #ef4444; box-shadow: 0 0 0 4px rgba(239, 68, 68, 0.12); }
.paste-field i { color: #8a8fb8; font-size: 16px; }
.paste-field input { flex: 1; border: 0; outline: none; background: none; font: 500 13.5px 'Plus Jakarta Sans'; color: var(--ink); }
.paste-field input::placeholder { color: #9ca0c6; font-weight: 400; }

.feedback { margin: 12px 0 0; font-size: 12.5px; display: flex; align-items: center; justify-content: center; gap: 6px; }
.feedback--ok { color: #059669; }
.feedback--err { color: #dc2626; }

.alt-row { margin: 16px 0 0; font-size: 13px; color: var(--text-soft); }
.alt-link { color: var(--indigo); font-weight: 700; text-decoration: none; }
.alt-link:hover { color: var(--violet); text-decoration: underline; }

.ver-row {
    margin-top: 22px; padding-top: 14px;
    border-top: 1px solid rgba(11, 16, 51, 0.08);
    display: flex; justify-content: space-between;
    font-size: 11.5px; color: var(--text-soft);
}
.ver-row b { color: var(--ink); letter-spacing: 0.05em; }

@media (max-width: 560px) {
    .topbar { padding: 16px 20px; }
    .brand-logo { height: 32px; }
    .float-card { padding: 30px 24px 22px; border-radius: 24px; }
    h1 { font-size: 21px; }
}
</style>

<style>
html, body, #app { background: #eef0ff !important; }
</style>
