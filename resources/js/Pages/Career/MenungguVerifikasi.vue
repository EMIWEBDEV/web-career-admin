<script>
// WEB CAREER — halaman "Menunggu Verifikasi Email". Tujuan redirect setelah
// register berhasil. Standalone (tanpa AppShell). Semua aksi terjadi di TAB INI:
//  - Polling status verifikasi tiap 4 detik → begitu terverifikasi (klik magic
//    link di tab/perangkat mana pun), halaman ini otomatis lanjut ke /login.
//  - Countdown masa berlaku tautan (30 menit).
//  - Kirim ulang email (server throttle 2 menit; tombol ikut cooldown).
//  - "Tautan bermasalah?" → tempel magic link / token → verifikasi inline
//    (tanpa pindah tab / buka tab baru).
import axios from 'axios';
import { Head, Link, router } from '@inertiajs/vue3';

const EXPIRY_DETIK = 30 * 60; // selaras VERIF_BERLAKU_MENIT di server
const RESEND_COOLDOWN = 120; // selaras VERIF_THROTTLE_MENIT (2 menit)
const POLL_MS = 4000;

export default {
    layout: null,
    components: { Head, Link },
    props: {
        email: { type: String, default: '' },
    },
    data() {
        return {
            state: 'menunggu', // menunggu | sukses
            sisaExpiry: EXPIRY_DETIK,
            cooldown: RESEND_COOLDOWN, // email baru saja terkirim saat register
            resending: false,
            noticeMsg: '',
            noticeType: 'info', // info | error
            pasteOpen: false,
            pasteVal: '',
            pasteErr: '',
            pasteBusy: false,
            pollTimer: null,
            tickTimer: null,
        };
    },
    computed: {
        expiryLabel() {
            const s = Math.max(0, this.sisaExpiry);
            const m = Math.floor(s / 60);
            const d = s % 60;
            return m + ':' + String(d).padStart(2, '0');
        },
        expiryHabis() { return this.sisaExpiry <= 0; },
        emailValid() { return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(this.email || ''); },
    },
    mounted() {
        if (!this.emailValid) return; // tampilkan state "email hilang"
        this.startTick();
        this.startPolling();
    },
    beforeUnmount() {
        clearInterval(this.pollTimer);
        clearInterval(this.tickTimer);
    },
    methods: {
        startTick() {
            this.tickTimer = setInterval(() => {
                if (this.sisaExpiry > 0) this.sisaExpiry -= 1;
                if (this.cooldown > 0) this.cooldown -= 1;
            }, 1000);
        },
        startPolling() {
            this.pollTimer = setInterval(this.cekStatus, POLL_MS);
        },
        async cekStatus() {
            try {
                const res = await axios.get('/api/v1/status-verifikasi', {
                    params: { email: this.email },
                    headers: { Accept: 'application/json' },
                });
                if (res.data && res.data.result && res.data.result.verified) {
                    this.onVerified();
                }
            } catch (e) { /* diamkan; polling lanjut */ }
        },
        onVerified() {
            if (this.state === 'sukses') return;
            this.state = 'sukses';
            clearInterval(this.pollTimer);
            clearInterval(this.tickTimer);
            setTimeout(() => router.visit('/login'), 2200);
        },
        showNotice(type, msg) {
            this.noticeType = type;
            this.noticeMsg = msg;
        },
        async kirimUlang() {
            if (this.resending || this.cooldown > 0 || !this.emailValid) return;
            this.resending = true;
            this.noticeMsg = '';
            try {
                const res = await axios.post('/api/v1/kirim-verifikasi', { email: this.email }, { headers: { Accept: 'application/json' } });
                this.showNotice('info', (res.data && res.data.message) || 'Email verifikasi telah dikirim ulang.');
                this.sisaExpiry = EXPIRY_DETIK; // tautan baru → reset masa berlaku
                this.cooldown = RESEND_COOLDOWN;
            } catch (e) {
                const r = e.response;
                this.showNotice('error', (r && r.data && r.data.message) || 'Gagal mengirim ulang. Silakan coba lagi.');
                // 429 (throttle) → set cooldown supaya tombol nonaktif dulu.
                if (r && r.status === 429) this.cooldown = RESEND_COOLDOWN;
            } finally {
                this.resending = false;
            }
        },
        // Ekstrak token (+email) dari tempelan: URL lengkap, query string, atau token mentah.
        parseTempel(raw) {
            const s = (raw || '').trim();
            if (!s) return null;
            let token = '';
            let email = '';
            if (s.includes('token=')) {
                try {
                    const qs = s.includes('?') ? s.split('?').slice(1).join('?') : s;
                    const p = new URLSearchParams(qs);
                    token = (p.get('token') || '').trim();
                    email = (p.get('email') || '').trim();
                } catch (e) { /* noop */ }
            } else if (/^[A-Za-z0-9]{40,}$/.test(s)) {
                token = s;
            }
            return token ? { token, email } : null;
        },
        async prosesTempel() {
            if (this.pasteBusy) return;
            this.pasteErr = '';
            const parsed = this.parseTempel(this.pasteVal);
            if (!parsed) {
                this.pasteErr = 'Format tidak dikenali. Salin utuh tautan dari email (mengandung "token=").';
                return;
            }
            this.pasteBusy = true;
            try {
                const body = { token: parsed.token };
                if (parsed.email || this.email) body.email = parsed.email || this.email;
                const res = await axios.post('/api/v1/verifikasi-token', body, { headers: { Accept: 'application/json' } });
                const st = res.data && res.data.result && res.data.result.status;
                if (st === 'sukses' || st === 'sudah') this.onVerified();
            } catch (e) {
                const r = e.response;
                if (r && r.status === 410) {
                    this.pasteErr = 'Tautan sudah kedaluwarsa. Silakan tekan "Kirim Ulang" untuk tautan baru.';
                } else {
                    this.pasteErr = (r && r.data && r.data.message) || 'Verifikasi gagal. Periksa kembali tautan yang kamu tempel.';
                }
            } finally {
                this.pasteBusy = false;
            }
        },
    },
};
</script>

<template>
    <Head title="Menunggu Verifikasi - EVO Career">
        <meta name="robots" content="noindex, nofollow" />
        <link rel="preconnect" href="https://fonts.googleapis.com" />
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
        <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
    </Head>

    <div class="shell">
        <div class="stage" aria-hidden="true">
            <span class="blob blob-a"></span>
            <span class="blob blob-b"></span>
            <div class="grid"></div>
        </div>

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
                <!-- SUKSES -->
                <template v-if="state === 'sukses'">
                    <div class="icon-badge icon-badge--ok">
                        <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5" /></svg>
                    </div>
                    <p class="eyebrow-txt">TERVERIFIKASI</p>
                    <h1>Email Berhasil Diverifikasi! 🎉</h1>
                    <p class="desc">Akunmu kini aktif sepenuhnya. Sebentar, kami arahkan kamu ke halaman masuk…</p>
                    <div class="redir-row"><span class="spinner spinner--indigo"></span> Mengalihkan ke halaman masuk…</div>
                </template>

                <!-- EMAIL TIDAK DIKETAHUI (akses langsung) -->
                <template v-else-if="!emailValid">
                    <div class="icon-badge icon-badge--warn">
                        <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9v4" /><path d="M12 17h.01" /><path d="M10.3 3.9L2 18a2 2 0 0 0 1.7 3h16.6a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z" /></svg>
                    </div>
                    <h1>Halaman Verifikasi</h1>
                    <p class="desc">Halaman ini muncul setelah kamu mendaftar. Silakan mulai dari pendaftaran atau masuk bila sudah punya akun.</p>
                    <Link href="/register" class="btn-main">Daftar Sekarang</Link>
                    <p class="alt-row">Sudah punya akun? <Link href="/login" class="alt-link">Masuk di sini</Link></p>
                </template>

                <!-- MENUNGGU -->
                <template v-else>
                    <div class="icon-badge icon-badge--wait">
                        <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 6h16a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1z" /><path d="M3.5 7.2l8.5 6 8.5-6" /></svg>
                        <span class="ping"></span>
                    </div>
                    <p class="eyebrow-txt">SATU LANGKAH LAGI</p>
                    <h1>Cek Email Kamu</h1>
                    <p class="desc">
                        Kami telah mengirim tautan verifikasi ke<br />
                        <b class="email-chip">{{ email }}</b>
                    </p>
                    <p class="desc-sub">
                        Buka email itu lalu tekan <b>“Verifikasi Email Saya”</b>. Halaman ini akan otomatis lanjut begitu emailmu terverifikasi — tidak perlu menyegarkan (refresh) apa pun.
                    </p>

                    <div class="live-row">
                        <span class="dot-live"></span>
                        Menunggu verifikasi…
                        <span class="live-sep">•</span>
                        Tautan berlaku <b :class="{ danger: expiryHabis }">{{ expiryHabis ? 'habis' : expiryLabel }}</b>
                    </div>

                    <div v-if="noticeMsg" class="notice" :class="noticeType === 'error' ? 'notice--err' : 'notice--ok'">
                        <i :class="noticeType === 'error' ? 'bi bi-exclamation-triangle-fill' : 'bi bi-check-circle-fill'"></i>
                        <span>{{ noticeMsg }}</span>
                    </div>

                    <button type="button" class="btn-main" :disabled="resending || cooldown > 0" @click="kirimUlang">
                        <span v-if="resending" class="spinner" aria-hidden="true"></span>
                        <template v-if="cooldown > 0">Kirim ulang dalam {{ cooldown }}s</template>
                        <template v-else>{{ resending ? 'Mengirim…' : 'Kirim Ulang Email Verifikasi' }}</template>
                    </button>

                    <!-- Tautan bermasalah → tempel magic link -->
                    <button type="button" class="paste-toggle" @click="pasteOpen = !pasteOpen">
                        <i class="bi" :class="pasteOpen ? 'bi-chevron-up' : 'bi-chevron-down'"></i>
                        Tautan di email tidak bisa diklik? Tempel di sini
                    </button>
                    <transition name="collapse">
                        <div v-if="pasteOpen" class="paste-box">
                            <p class="paste-hint">Salin seluruh tautan dari email (yang mengandung <code>token=</code>), lalu tempel di bawah — verifikasi tetap di halaman ini.</p>
                            <div class="paste-field" :class="{ err: pasteErr }">
                                <i class="bi bi-link-45deg"></i>
                                <input v-model="pasteVal" type="text" placeholder="Tempel tautan / token dari email" @input="pasteErr = ''" @keyup.enter="prosesTempel" />
                            </div>
                            <p v-if="pasteErr" class="paste-err">{{ pasteErr }}</p>
                            <button type="button" class="btn-ghost" :disabled="pasteBusy" @click="prosesTempel">
                                <span v-if="pasteBusy" class="spinner spinner--indigo" aria-hidden="true"></span>
                                {{ pasteBusy ? 'Memverifikasi…' : 'Verifikasi Sekarang' }}
                            </button>
                        </div>
                    </transition>

                    <p class="alt-row">Salah alamat email? <Link href="/register" class="alt-link">Daftar ulang</Link></p>
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
.blob { position: absolute; border-radius: 50%; filter: blur(8px); pointer-events: none; }
.blob-a { top: -130px; left: -80px; width: 420px; height: 420px; background: radial-gradient(circle at 30% 30%, rgba(139, 92, 246, 0.28), rgba(139, 92, 246, 0) 70%); animation: floatA 16s ease-in-out infinite; }
.blob-b { bottom: -150px; right: -70px; width: 460px; height: 460px; background: radial-gradient(circle at 60% 40%, rgba(99, 102, 241, 0.24), rgba(99, 102, 241, 0) 70%); animation: floatB 20s ease-in-out infinite; }
@keyframes floatA { 0%, 100% { transform: translate(0, 0); } 50% { transform: translate(28px, -24px); } }
@keyframes floatB { 0%, 100% { transform: translate(0, 0); } 50% { transform: translate(-24px, 22px); } }
.grid {
    position: absolute; inset: 0;
    background-image:
        linear-gradient(rgba(79, 70, 229, 0.05) 1px, transparent 1px),
        linear-gradient(90deg, rgba(79, 70, 229, 0.05) 1px, transparent 1px);
    background-size: 56px 56px;
    -webkit-mask-image: radial-gradient(ellipse at 50% 35%, #000 30%, transparent 75%);
    mask-image: radial-gradient(ellipse at 50% 35%, #000 30%, transparent 75%);
}
.topbar { position: relative; z-index: 2; padding: 24px 56px; display: flex; align-items: center; }
.mark-login { display: flex; align-items: center; gap: 14px; text-decoration: none; color: inherit; }
.brand-logo { height: 44px; width: auto; object-fit: contain; filter: drop-shadow(0 4px 12px rgba(11, 16, 51, 0.15)); }
.mark-login-text { display: flex; flex-direction: column; }
.mark-login b { font-weight: 700; font-size: 16px; }
.mark-login small { font-size: 11px; color: var(--text-soft); letter-spacing: 0.18em; text-transform: uppercase; margin-top: 2px; }

.center-wrap { position: relative; z-index: 1; flex: 1; display: grid; place-items: center; padding: 12px 18px 44px; }
.float-card {
    width: 100%; max-width: 500px; text-align: center;
    background: linear-gradient(145deg, rgba(255, 255, 255, 0.9) 0%, rgba(255, 255, 255, 0.58) 100%);
    backdrop-filter: blur(24px) saturate(200%);
    -webkit-backdrop-filter: blur(24px) saturate(200%);
    border: 1px solid rgba(255, 255, 255, 0.9);
    border-radius: 32px;
    padding: 40px 42px 28px;
    box-shadow: inset 0 2px 4px rgba(255, 255, 255, 0.8), 0 40px 80px -20px rgba(11, 16, 51, 0.25), 0 16px 40px -10px rgba(79, 70, 229, 0.15);
    animation: cardIn 0.6s cubic-bezier(0.22, 1, 0.36, 1) both;
}
@keyframes cardIn { from { opacity: 0; transform: translateY(18px); } to { opacity: 1; transform: translateY(0); } }

.icon-badge { position: relative; width: 74px; height: 74px; border-radius: 22px; display: grid; place-items: center; margin: 0 auto 18px; }
.icon-badge--wait { background: linear-gradient(135deg, #8b5cf6, #6366f1); box-shadow: 0 16px 36px -8px rgba(99, 102, 241, 0.5); }
.icon-badge--ok { background: linear-gradient(135deg, #10b981, #059669); box-shadow: 0 16px 36px -8px rgba(16, 185, 129, 0.55); animation: pop 0.5s cubic-bezier(0.34, 1.56, 0.64, 1) both; }
.icon-badge--warn { background: linear-gradient(135deg, #f59e0b, #d97706); box-shadow: 0 16px 36px -8px rgba(245, 158, 11, 0.5); }
@keyframes pop { from { transform: scale(0.6); opacity: 0; } to { transform: scale(1); opacity: 1; } }
.ping { position: absolute; inset: -6px; border-radius: 26px; border: 2px solid rgba(139, 92, 246, 0.5); animation: ping 1.8s cubic-bezier(0, 0, 0.2, 1) infinite; }
@keyframes ping { 0% { transform: scale(0.9); opacity: 0.8; } 100% { transform: scale(1.25); opacity: 0; } }

.eyebrow-txt { margin: 0; font-size: 11px; font-weight: 800; letter-spacing: 0.16em; color: var(--violet); }
h1 { margin: 8px 0 0; font-size: 25px; font-weight: 800; letter-spacing: -0.02em; color: var(--ink); }
.desc { margin: 12px 0 0; font-size: 14.5px; line-height: 1.7; color: var(--text-soft); }
.desc b { color: var(--ink); }
.email-chip { display: inline-block; margin-top: 6px; padding: 5px 14px; background: rgba(79, 70, 229, 0.1); border: 1px solid rgba(79, 70, 229, 0.2); border-radius: 999px; color: var(--indigo) !important; font-weight: 700; font-size: 13.5px; }
.desc-sub { margin: 14px 0 0; font-size: 13px; line-height: 1.65; color: var(--text-soft); }
.desc-sub b { color: var(--ink); }

.live-row {
    margin: 20px 0 0; display: inline-flex; align-items: center; gap: 8px; flex-wrap: wrap; justify-content: center;
    padding: 10px 16px; background: rgba(16, 185, 129, 0.08); border: 1px solid rgba(16, 185, 129, 0.2);
    border-radius: 999px; font-size: 12.5px; color: #0f766e; font-weight: 600;
}
.live-row b { color: #0f766e; }
.live-row b.danger { color: #dc2626; }
.live-sep { color: rgba(15, 118, 110, 0.4); }
.dot-live { width: 8px; height: 8px; border-radius: 50%; background: #10b981; box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.6); animation: pulse 1.5s infinite; }
@keyframes pulse { 0% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.5); } 70% { box-shadow: 0 0 0 7px rgba(16, 185, 129, 0); } 100% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); } }

.notice { margin: 16px 0 0; display: flex; align-items: center; gap: 8px; padding: 11px 14px; border-radius: 12px; font-size: 12.5px; text-align: left; }
.notice--ok { background: rgba(16, 185, 129, 0.1); color: #059669; }
.notice--err { background: rgba(239, 68, 68, 0.1); color: #dc2626; }
.notice span { flex: 1; }

.btn-main {
    width: 100%; margin-top: 18px; padding: 15px 20px; border: 0; cursor: pointer; border-radius: 16px;
    background: linear-gradient(135deg, var(--indigo) 0%, var(--violet) 100%); color: #fff;
    font: 700 14.5px 'Plus Jakarta Sans'; text-decoration: none;
    display: flex; align-items: center; justify-content: center; gap: 10px;
    box-shadow: 0 16px 36px -10px rgba(124, 58, 237, 0.5);
    transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1), box-shadow 0.3s ease, opacity 0.3s ease;
}
.btn-main:hover:not(:disabled) { transform: translateY(-2px); box-shadow: 0 24px 48px -10px rgba(79, 70, 229, 0.6); }
.btn-main:disabled { opacity: 0.55; cursor: not-allowed; box-shadow: none; }

.paste-toggle {
    margin: 14px 0 0; width: 100%; background: none; border: 0; cursor: pointer;
    font: 600 12.5px 'Plus Jakarta Sans'; color: var(--indigo);
    display: flex; align-items: center; justify-content: center; gap: 6px;
}
.paste-toggle:hover { color: var(--violet); }
.paste-box { margin-top: 14px; padding: 16px; background: rgba(79, 70, 229, 0.05); border: 1px solid rgba(79, 70, 229, 0.14); border-radius: 16px; text-align: left; }
.paste-hint { margin: 0 0 10px; font-size: 12px; line-height: 1.55; color: var(--text-soft); }
.paste-hint code { background: rgba(79, 70, 229, 0.12); padding: 1px 5px; border-radius: 5px; font-size: 11px; color: var(--indigo); }
.paste-field { display: flex; align-items: center; gap: 8px; padding: 12px 14px; background: #fff; border: 1.5px solid rgba(11, 16, 51, 0.15); border-radius: 13px; transition: border-color 0.2s ease, box-shadow 0.2s ease; }
.paste-field:focus-within { border-color: var(--indigo); box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.12); }
.paste-field.err { border-color: #ef4444; box-shadow: 0 0 0 4px rgba(239, 68, 68, 0.12); }
.paste-field i { color: #8a8fb8; font-size: 16px; }
.paste-field input { flex: 1; border: 0; outline: none; background: none; font: 500 13px 'Plus Jakarta Sans'; color: var(--ink); }
.paste-field input::placeholder { color: #9ca0c6; font-weight: 400; }
.paste-err { margin: 8px 0 0; font-size: 12px; color: #dc2626; }
.btn-ghost {
    width: 100%; margin-top: 12px; padding: 12px; cursor: pointer; border-radius: 13px;
    border: 1.5px solid var(--indigo); background: #fff; color: var(--indigo);
    font: 700 13px 'Plus Jakarta Sans';
    display: flex; align-items: center; justify-content: center; gap: 8px;
    transition: background 0.2s ease, color 0.2s ease;
}
.btn-ghost:hover:not(:disabled) { background: var(--indigo); color: #fff; }
.btn-ghost:disabled { opacity: 0.6; cursor: not-allowed; }

.redir-row { margin: 20px 0 0; display: flex; align-items: center; justify-content: center; gap: 10px; font-size: 13px; color: var(--text-soft); font-weight: 600; }
.spinner { width: 17px; height: 17px; border: 2.5px solid rgba(255, 255, 255, 0.45); border-top-color: #fff; border-radius: 50%; animation: spin 0.7s linear infinite; }
.spinner--indigo { border-color: rgba(79, 70, 229, 0.25); border-top-color: var(--indigo); }
@keyframes spin { to { transform: rotate(360deg); } }

.alt-row { margin: 16px 0 0; font-size: 13px; color: var(--text-soft); }
.alt-link { color: var(--indigo); font-weight: 700; text-decoration: none; }
.alt-link:hover { color: var(--violet); text-decoration: underline; }

.ver-row { margin-top: 22px; padding-top: 14px; border-top: 1px solid rgba(11, 16, 51, 0.08); display: flex; justify-content: space-between; font-size: 11.5px; color: var(--text-soft); }
.ver-row b { color: var(--ink); letter-spacing: 0.05em; }

.collapse-enter-active, .collapse-leave-active { transition: opacity 0.25s ease, transform 0.25s ease; }
.collapse-enter-from, .collapse-leave-to { opacity: 0; transform: translateY(-6px); }

@media (max-width: 560px) {
    .topbar { padding: 16px 20px; }
    .brand-logo { height: 32px; }
    .float-card { padding: 30px 22px 22px; border-radius: 24px; }
    h1 { font-size: 21px; }
}
</style>

<style>
html, body, #app { background: #eef0ff !important; }
</style>
