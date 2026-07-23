<template>
    <Head title="Ganti Kata Sandi - EVO Group Career">
        <meta name="robots" content="noindex, nofollow" />
        <link rel="preconnect" href="https://fonts.googleapis.com" />
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
        <link
            href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Fraunces:opsz,wght@9..144,300;9..144,400;9..144,500&display=swap"
            rel="stylesheet"
        />
    </Head>

    <div class="shell">
        <div class="stage" aria-hidden="true">
            <svg class="blob-svg" viewBox="0 0 1600 900" preserveAspectRatio="xMidYMid slice">
                <defs>
                    <radialGradient id="cg1" cx="50%" cy="50%" r="50%"><stop offset="0%" stop-color="#7C3AED" stop-opacity="0.65" /><stop offset="100%" stop-color="#7C3AED" stop-opacity="0" /></radialGradient>
                    <radialGradient id="cg2" cx="50%" cy="50%" r="50%"><stop offset="0%" stop-color="#3B82F6" stop-opacity="0.55" /><stop offset="100%" stop-color="#3B82F6" stop-opacity="0" /></radialGradient>
                    <radialGradient id="cg3" cx="50%" cy="50%" r="50%"><stop offset="0%" stop-color="#C9A24A" stop-opacity="0.35" /><stop offset="100%" stop-color="#C9A24A" stop-opacity="0" /></radialGradient>
                    <filter id="cgoo"><feGaussianBlur stdDeviation="60" /></filter>
                </defs>
                <g filter="url(#cgoo)">
                    <circle cx="200" cy="200" r="280" fill="url(#cg1)"><animate attributeName="cx" values="200;350;200" dur="22s" repeatCount="indefinite" /><animate attributeName="cy" values="200;320;200" dur="18s" repeatCount="indefinite" /></circle>
                    <circle cx="1300" cy="700" r="360" fill="url(#cg2)"><animate attributeName="cx" values="1300;1150;1300" dur="24s" repeatCount="indefinite" /><animate attributeName="cy" values="700;580;700" dur="20s" repeatCount="indefinite" /></circle>
                    <circle cx="900" cy="200" r="240" fill="url(#cg3)"><animate attributeName="cx" values="900;1050;900" dur="26s" repeatCount="indefinite" /><animate attributeName="cy" values="200;350;200" dur="22s" repeatCount="indefinite" /></circle>
                </g>
            </svg>
            <div class="grid"></div>
        </div>

        <transition name="v3-toast">
            <div v-if="notice.visible" class="v3-toast" :class="`v3-toast--${notice.type}`" role="alert">
                <i :class="notice.type === 'error' ? 'bi bi-exclamation-triangle-fill' : 'bi bi-info-circle-fill'"></i>
                <span>{{ notice.message }}</span>
                <button class="v3-toast__close" type="button" aria-label="Tutup" @click="notice.visible = false"><i class="bi bi-x-lg"></i></button>
            </div>
        </transition>

        <nav class="topbar animate-fade-down">
            <Link href="/" class="mark-login">
                <img src="/logo/EVOGROUP.png" alt="EVO Group" class="brand-logo" />
                <div class="mark-login-text"><b>EVO Career</b><small>Portal Kandidat</small></div>
            </Link>
            <div class="top-right">
                <Link href="/login" class="top-link"><i class="bi bi-arrow-left"></i> Kembali ke Masuk</Link>
            </div>
        </nav>

        <main class="stage-grid">
            <section class="statement animate-fade-right">
                <span class="eyebrow"><span class="dot"></span>Keamanan Akun</span>
                <h1 class="display">Atur Ulang<br /><span>Kata Sandi.</span></h1>
                <p class="lede">Buat kata sandi baru untuk mengamankan akun &amp; melanjutkan proses lamaranmu di EVO Group.</p>
                <p class="sub-mobile">Buat kata sandi baru untuk akun EVO Career</p>
            </section>

            <aside class="stage-side animate-fade-left" style="animation-delay: 0.1s">
                <div class="float-card">
                    <div class="card-head">
                        <h3>{{ stepTitle }}</h3>
                        <span class="tag">Keamanan</span>
                    </div>

                    <!-- FASE 1: minta kode OTP (email) -->
                    <form v-if="step === 'minta'" @submit.prevent="mintaOtp" novalidate>
                        <p class="step-hint">Masukkan email akunmu. Kami akan mengirim kode OTP 6 digit untuk mengatur ulang kata sandi.</p>

                        <div class="field">
                            <label for="g-email"><span class="lbl-text"><i class="bi bi-envelope"></i> Email</span></label>
                            <div class="input-wrap" :class="{ 'is-error': errors.email }">
                                <input id="g-email" v-model="form.email" type="email" placeholder="nama@email.com" autocomplete="email" @input="clearErr('email')" />
                            </div>
                            <p v-if="errors.email" class="help">{{ errors.email }}</p>
                        </div>

                        <button type="submit" class="btn-login" :disabled="processing || !form.email">
                            <span v-if="processing" class="spinner" aria-hidden="true"></span>
                            {{ processing ? 'Mengirim…' : 'Kirim Kode OTP' }}
                            <i v-if="!processing" class="bi bi-send"></i>
                        </button>

                        <p class="switch-row">Ingat kata sandimu? <Link href="/login" class="switch-link">Masuk di sini</Link></p>
                        <div class="ver-row"><span>EVO <b>Career</b></span><span>© 2026 EVO Group</span></div>
                    </form>

                    <!-- FASE 2: masukkan 6 digit OTP -->
                    <form v-else-if="step === 'otp'" @submit.prevent="lanjutKeReset" novalidate>
                        <p class="step-hint">Kami mengirim kode OTP 6 digit ke <b>{{ form.email }}</b>. Masukkan kodenya untuk melanjutkan.</p>

                        <div class="otp-boxes" :class="{ 'is-error': errors.otp }" @paste="onPaste">
                            <input
                                v-for="(d, i) in otpDigits"
                                :key="i"
                                ref="otpInputs"
                                class="otp-box"
                                type="text"
                                inputmode="numeric"
                                maxlength="1"
                                :value="otpDigits[i]"
                                :aria-label="`Digit ${i + 1}`"
                                @input="onDigit(i, $event)"
                                @keydown="onKeydown(i, $event)"
                                @focus="$event.target.select()"
                            />
                        </div>
                        <p v-if="errors.otp" class="help help-center">{{ errors.otp }}</p>

                        <p class="otp-meta">
                            <span v-if="otpCountdown > 0"><i class="bi bi-clock"></i> Kode berlaku {{ otpCountdownText }}</span>
                            <span v-else class="otp-expired"><i class="bi bi-clock-history"></i> Kode kedaluwarsa — silakan kirim ulang</span>
                        </p>

                        <button type="submit" class="btn-login" :disabled="otpValue.length !== 6">
                            Lanjutkan <i class="bi bi-arrow-right"></i>
                        </button>

                        <p class="switch-row">
                            Tidak menerima kode?
                            <button v-if="resendCooldown <= 0" type="button" class="switch-link as-btn" :disabled="processing" @click="mintaOtp(true)">Kirim ulang</button>
                            <span v-else class="switch-muted">Kirim ulang dalam {{ resendCooldown }}s</span>
                        </p>
                        <p class="switch-row"><button type="button" class="switch-link" @click="kembaliKeMinta"><i class="bi bi-arrow-left"></i> Ganti email</button></p>
                        <div class="ver-row"><span>EVO <b>Career</b></span><span>© 2026 EVO Group</span></div>
                    </form>

                    <!-- FASE 3: kata sandi baru (OTP + password dikirim bersamaan) -->
                    <form v-else @submit.prevent="submitReset" novalidate>
                        <p class="step-hint">Kode diterima untuk <b>{{ form.email }}</b>. Sekarang buat kata sandi barumu.</p>

                        <div class="field">
                            <label for="g-pass"><span class="lbl-text"><i class="bi bi-key"></i> Kata Sandi Baru</span></label>
                            <div class="input-wrap" :class="{ 'is-error': errors.password }">
                                <input id="g-pass" v-model="form.password" :type="show ? 'text' : 'password'" placeholder="Minimal 6 karakter" autocomplete="new-password" @input="clearErr('password')" />
                                <button type="button" class="toggle-eye" :aria-label="show ? 'Sembunyikan' : 'Tampilkan'" @click="show = !show"><i :class="show ? 'bi bi-eye-slash' : 'bi bi-eye'"></i></button>
                            </div>
                            <p v-if="errors.password" class="help">{{ errors.password }}</p>
                        </div>

                        <div class="field">
                            <label for="g-conf"><span class="lbl-text"><i class="bi bi-shield-lock"></i> Konfirmasi Kata Sandi</span></label>
                            <div class="input-wrap" :class="{ 'is-error': errors.confirm }">
                                <input id="g-conf" v-model="form.confirm" :type="show ? 'text' : 'password'" placeholder="Ulangi kata sandi baru" autocomplete="new-password" @input="clearErr('confirm')" />
                            </div>
                            <p v-if="errors.confirm" class="help">{{ errors.confirm }}</p>
                        </div>

                        <button type="submit" class="btn-login" :disabled="!canSubmit">
                            <span v-if="processing" class="spinner" aria-hidden="true"></span>
                            {{ processing ? 'Menyimpan…' : 'Simpan Kata Sandi' }}
                            <i v-if="!processing" class="bi bi-check-lg"></i>
                        </button>

                        <p class="switch-row"><button type="button" class="switch-link" @click="step = 'otp'"><i class="bi bi-arrow-left"></i> Kembali ke kode OTP</button></p>
                        <div class="ver-row"><span>EVO <b>Career</b></span><span>© 2026 EVO Group</span></div>
                    </form>
                </div>
            </aside>
        </main>

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
    </div>
</template>

<script>
import axios from 'axios';
import { Head, Link, router } from '@inertiajs/vue3';

const RESEND_COOLDOWN = 120; // detik — selaras RESET_OTP_THROTTLE_MENIT (2 menit) di backend
const OTP_BERLAKU = 10 * 60; // detik — selaras RESET_OTP_BERLAKU_MENIT (10 menit)

export default {
    layout: null,
    components: { Head, Link },
    props: {
        email: { type: String, default: '' },
    },
    data() {
        return {
            step: 'minta', // 'minta' → 'otp' → 'reset'
            form: { email: this.email || '', password: '', confirm: '' },
            otpDigits: ['', '', '', '', '', ''],
            show: false,
            processing: false,
            errors: {},
            notice: { visible: false, type: 'info', message: '' },
            noticeTimer: null,
            resendCooldown: 0,
            otpCountdown: 0,
            tickTimer: null,
            subsidiaries: [
                { src: '/logo/EMI.png', alt: 'PT EVO Manufacturing Indonesia' },
                { src: '/logo/ENB.png', alt: 'PT EVO Nusa Bersaudara' },
                { src: '/logo/GMN.png', alt: 'PT Graha Maju Nusantara' },
            ],
        };
    },
    computed: {
        stepTitle() {
            return this.step === 'minta' ? 'Lupa Kata Sandi' : this.step === 'otp' ? 'Verifikasi OTP' : 'Reset Kata Sandi';
        },
        otpValue() {
            return this.otpDigits.join('');
        },
        canSubmit() {
            return !this.processing && this.otpValue.length === 6 && !!this.form.password && !!this.form.confirm;
        },
        otpCountdownText() {
            const m = Math.floor(this.otpCountdown / 60);
            const s = this.otpCountdown % 60;
            return `${m}:${String(s).padStart(2, '0')}`;
        },
    },
    beforeUnmount() {
        clearTimeout(this.noticeTimer);
        clearInterval(this.tickTimer);
    },
    methods: {
        flashNotice(type, message) {
            if (!message) return;
            this.notice.type = type;
            this.notice.message = message;
            this.notice.visible = true;
            clearTimeout(this.noticeTimer);
            this.noticeTimer = setTimeout(() => (this.notice.visible = false), 6000);
        },
        clearErr(key) {
            if (this.errors[key]) delete this.errors[key];
        },
        /* ── 6-kotak OTP: fokus, ketik, hapus, tempel ── */
        focusOtp(i) {
            this.$nextTick(() => {
                const els = this.$refs.otpInputs;
                if (els && els[i]) els[i].focus();
            });
        },
        resetOtpBoxes() {
            this.otpDigits = ['', '', '', '', '', ''];
        },
        onDigit(i, e) {
            const v = (e.target.value || '').replace(/\D/g, '');
            const digit = v ? v[v.length - 1] : '';
            this.otpDigits.splice(i, 1, digit);
            e.target.value = digit; // pastikan karakter non-digit tidak tersisa
            this.clearErr('otp');
            if (digit && i < 5) this.focusOtp(i + 1);
        },
        onKeydown(i, e) {
            if (e.key === 'Backspace' && !this.otpDigits[i] && i > 0) {
                this.otpDigits.splice(i - 1, 1, '');
                this.focusOtp(i - 1);
            } else if (e.key === 'ArrowLeft' && i > 0) {
                this.focusOtp(i - 1);
            } else if (e.key === 'ArrowRight' && i < 5) {
                this.focusOtp(i + 1);
            }
        },
        onPaste(e) {
            e.preventDefault();
            const txt = (e.clipboardData ? e.clipboardData.getData('text') : '') || '';
            const digits = txt.replace(/\D/g, '').slice(0, 6).split('');
            if (!digits.length) return;
            const next = ['', '', '', '', '', ''];
            digits.forEach((d, idx) => (next[idx] = d));
            this.otpDigits = next;
            this.clearErr('otp');
            this.focusOtp(Math.min(digits.length, 6) - 1);
        },
        startTick() {
            clearInterval(this.tickTimer);
            this.tickTimer = setInterval(() => {
                if (this.resendCooldown > 0) this.resendCooldown -= 1;
                if (this.otpCountdown > 0) this.otpCountdown -= 1;
                if (this.resendCooldown <= 0 && this.otpCountdown <= 0) clearInterval(this.tickTimer);
            }, 1000);
        },
        kembaliKeMinta() {
            this.step = 'minta';
            this.resetOtpBoxes();
            this.errors = {};
            clearInterval(this.tickTimer);
        },
        // Fase 1 / kirim ulang: minta kode OTP. Respons SELALU generik (anti-enumerasi).
        async mintaOtp(isResend = false) {
            this.errors = {};
            if (!this.form.email) {
                this.errors.email = 'Email wajib diisi.';
                return;
            }
            if (isResend && this.resendCooldown > 0) return;

            this.processing = true;
            try {
                const res = await axios.post('/api/v1/lupa-sandi', { email: this.form.email }, { headers: { Accept: 'application/json' } });
                this.step = 'otp';
                this.resetOtpBoxes();
                this.resendCooldown = RESEND_COOLDOWN;
                this.otpCountdown = OTP_BERLAKU;
                this.startTick();
                this.focusOtp(0);
                this.flashNotice('info', (res.data && res.data.message) || 'Jika email terdaftar, kode OTP telah dikirim.');
            } catch (e) {
                const r = e.response;
                if (r && r.status === 422 && r.data.errors) {
                    Object.keys(r.data.errors).forEach((k) => (this.errors[k] = Array.isArray(r.data.errors[k]) ? r.data.errors[k][0] : r.data.errors[k]));
                } else {
                    this.flashNotice('error', (r && r.data && r.data.message) || 'Tidak dapat mengirim kode OTP. Coba lagi.');
                }
            } finally {
                this.processing = false;
            }
        },
        // Fase 2 → 3: setelah 6 digit terisi, tampilkan input kata sandi.
        lanjutKeReset() {
            if (this.otpValue.length !== 6) {
                this.errors = { otp: 'Masukkan 6 digit kode OTP.' };
                return;
            }
            this.errors = {};
            this.step = 'reset';
        },
        // Fase 3: kirim OTP + kata sandi baru BERSAMAAN (satu request ke backend).
        async submitReset() {
            this.errors = {};
            if (this.otpValue.length !== 6) this.errors.otp = 'Kode OTP harus 6 digit.';
            if (!this.form.password) this.errors.password = 'Kata sandi wajib diisi.';
            else if (this.form.password.length < 6) this.errors.password = 'Minimal 6 karakter.';
            if (this.form.password && this.form.password !== this.form.confirm) this.errors.confirm = 'Konfirmasi tidak cocok.';
            if (Object.keys(this.errors).length) {
                if (this.errors.otp) this.step = 'otp';
                return;
            }

            this.processing = true;
            try {
                await axios.post(
                    '/api/v1/ganti-sandi',
                    { email: this.form.email, otp: this.otpValue, password: this.form.password },
                    { headers: { Accept: 'application/json' } },
                );
                clearInterval(this.tickTimer);
                this.flashNotice('info', 'Kata sandi diperbarui — mengalihkan ke halaman masuk…');
                setTimeout(() => router.visit('/login'), 900);
            } catch (e) {
                this.processing = false;
                const r = e.response;
                const code = r && r.data && r.data.code;
                // Masalah pada OTP → kembali ke langkah kode, kosongkan kotak.
                if (code === 'OTP_INVALID' || code === 'OTP_EXPIRED' || code === 'OTP_LOCKED') {
                    this.step = 'otp';
                    this.resetOtpBoxes();
                    this.focusOtp(0);
                    if (code === 'OTP_EXPIRED') this.otpCountdown = 0;
                    if (code === 'OTP_LOCKED') this.otpCountdown = 0;
                    this.errors = { otp: (r.data && r.data.message) || 'Kode OTP tidak valid. Silakan coba lagi.' };
                } else if (r && r.status === 422 && r.data.errors) {
                    Object.keys(r.data.errors).forEach((k) => (this.errors[k] = Array.isArray(r.data.errors[k]) ? r.data.errors[k][0] : r.data.errors[k]));
                    if (this.errors.otp) this.step = 'otp';
                } else {
                    this.flashNotice('error', (r && r.data && r.data.message) || 'Tidak dapat memperbarui kata sandi.');
                }
            }
        },
    },
};
</script>

<style scoped>
* { box-sizing: border-box; }
.bi { display: contents; }
.sub-mobile { display: none; }
.desktop-only { display: inline-flex; align-items: center; gap: 3px; }
.mobile-only { display: none; }
.shell {
    --bg-0: #eef0ff; --ink: #0b1033; --indigo: #4f46e5; --indigo-2: #6366f1; --violet: #7c3aed; --gold: #c9a24a;
    --text: #0f1235; --text-soft: #5b5f86; --line: rgba(11, 16, 51, 0.1);
    position: fixed; inset: 0; font-family: 'Plus Jakarta Sans', system-ui, sans-serif; color: var(--text);
    background: var(--bg-0); overflow-y: auto; overflow-x: hidden; display: flex; flex-direction: column; -webkit-font-smoothing: antialiased;
}
.stage { position: fixed; inset: 0; overflow: hidden; z-index: 0; }
.blob-svg { position: absolute; inset: 0; width: 100%; height: 100%; animation: ambientFade 2s ease-out forwards; opacity: 0; will-change: transform, opacity; }
.grid {
    position: absolute; inset: 0;
    background-image: linear-gradient(rgba(79, 70, 229, 0.05) 1px, transparent 1px), linear-gradient(90deg, rgba(79, 70, 229, 0.05) 1px, transparent 1px);
    background-size: 56px 56px;
    -webkit-mask-image: radial-gradient(ellipse at 70% 50%, #000 30%, transparent 75%);
    mask-image: radial-gradient(ellipse at 70% 50%, #000 30%, transparent 75%);
}
.topbar { position: relative; z-index: 3; padding: 24px 56px; display: flex; align-items: center; justify-content: space-between; flex-shrink: 0; }
.mark-login { display: flex; align-items: center; gap: 14px; text-decoration: none; color: inherit; }
.brand-logo { height: 48px; width: auto; object-fit: contain; filter: drop-shadow(0 4px 12px rgba(11, 16, 51, 0.15)); transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1); }
.brand-logo:hover { transform: scale(1.08) rotate(-2deg); }
.mark-login-text { display: flex; flex-direction: column; }
.mark-login b { font-weight: 700; font-size: 16px; letter-spacing: -0.01em; }
.mark-login small { display: block; font-size: 11px; color: var(--text-soft); letter-spacing: 0.18em; text-transform: uppercase; margin-top: 2px; }
.top-right { display: flex; align-items: center; gap: 24px; }
.top-link { font-size: 13px; color: var(--text-soft); text-decoration: none; font-weight: 600; display: inline-flex; align-items: center; gap: 6px; transition: color 0.3s ease, transform 0.3s ease; }
.top-link:hover { color: var(--ink); transform: translateY(-1px); }
.stage-grid { position: relative; z-index: 2; flex: 1 0 auto; padding: 0 56px 24px; display: grid; grid-template-columns: 1fr 460px; align-items: center; gap: 40px; max-width: 1360px; margin: 0 auto; width: 100%; }
.statement { position: relative; }
.eyebrow { display: inline-flex; align-items: center; gap: 10px; padding: 8px 14px; border-radius: 999px; background: rgba(79, 70, 229, 0.1); color: var(--indigo); font-size: 12px; font-weight: 600; letter-spacing: 0.06em; margin-bottom: 24px; }
.eyebrow .dot { width: 6px; height: 6px; border-radius: 50%; background: var(--indigo); box-shadow: 0 0 12px var(--indigo); }
.display { margin: 0; font-family: 'Plus Jakarta Sans', sans-serif; font-size: clamp(48px, 5.6vw, 84px); line-height: 1.02; font-weight: 700; letter-spacing: -0.035em; color: var(--ink); }
.display span { display: inline-block; font-family: 'Fraunces', serif; font-style: italic; font-weight: 400; background: linear-gradient(120deg, var(--violet) 0%, var(--indigo) 45%, #2a6fdb 75%, var(--gold) 110%); -webkit-background-clip: text; background-clip: text; color: transparent; padding-right: 0.04em; }
.lede { margin-top: 24px; max-width: 460px; color: var(--text-soft); font-size: 15px; line-height: 1.55; }
.float-card { position: relative; background: linear-gradient(145deg, rgba(255, 255, 255, 0.85) 0%, rgba(255, 255, 255, 0.45) 100%); backdrop-filter: blur(24px) saturate(200%); -webkit-backdrop-filter: blur(24px) saturate(200%); border: 1px solid rgba(255, 255, 255, 0.9); border-radius: 36px; padding: 38px 44px 32px; box-shadow: inset 0 2px 4px rgba(255, 255, 255, 0.8), inset 0 -2px 10px rgba(255, 255, 255, 0.3), 0 40px 80px -20px rgba(11, 16, 51, 0.25), 0 16px 40px -10px rgba(79, 70, 229, 0.15); transform: translateY(-12px); will-change: transform; transition: transform 0.4s ease, box-shadow 0.4s ease; }
.float-card:hover { transform: translateY(-18px); }
.card-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px; }
.card-head h3 { font-size: 24px; font-weight: 800; margin: 0; letter-spacing: -0.02em; background: linear-gradient(135deg, var(--ink) 20%, var(--indigo) 100%); -webkit-background-clip: text; background-clip: text; -webkit-text-fill-color: transparent; color: transparent; }
.card-head .tag { font-size: 10.5px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.1em; color: var(--indigo); background: rgba(79, 70, 229, 0.1); padding: 6px 12px; border-radius: 8px; }
.field { margin-bottom: 14px; }
.field label { display: flex; align-items: center; justify-content: space-between; font-size: 12px; font-weight: 600; color: var(--ink); margin-bottom: 8px; }
.field label .lbl-text { display: flex; align-items: center; gap: 6px; }
.field label .lbl-text i { font-size: 13px; color: var(--indigo); }
.input-wrap { position: relative; display: flex; align-items: center; background: #ffffff; border: 1.5px solid rgba(11, 16, 51, 0.15); border-radius: 16px; box-shadow: 0 2px 10px rgba(11, 16, 51, 0.03); transition: border-color 0.3s ease, background-color 0.3s ease, box-shadow 0.3s ease; transform: translateZ(0); overflow: hidden; }
.input-wrap:focus-within { border-color: var(--indigo); box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.12), 0 4px 12px rgba(11, 16, 51, 0.05); }
.input-wrap.is-error { border-color: #ef4444; box-shadow: 0 0 0 4px rgba(239, 68, 68, 0.12); }
.input-wrap input { flex: 1; border: 0; background: transparent; padding: 16px 20px; font: 500 15px 'Plus Jakarta Sans'; color: var(--ink); outline: none; border-radius: inherit; }
.input-wrap input::placeholder { color: #9ca0c6; font-weight: 400; }
.toggle-eye { background: none; border: 0; padding: 0 14px; color: #8a8fb8; cursor: pointer; font-size: 15px; transition: color 0.3s ease, transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1); }
.toggle-eye:hover { color: var(--indigo); transform: scale(1.15); }
.help { margin: 6px 2px 0; font-size: 12px; color: #dc2626; }
.btn-login { width: 100%; margin-top: 4px; padding: 16px 20px; border: 0; cursor: pointer; border-radius: 16px; background: linear-gradient(135deg, var(--indigo) 0%, var(--violet) 100%); color: #fff; font: 700 15px 'Plus Jakarta Sans'; letter-spacing: 0.03em; box-shadow: 0 16px 36px -10px rgba(124, 58, 237, 0.5), inset 0 1px 0 rgba(255, 255, 255, 0.25); display: flex; align-items: center; justify-content: center; gap: 12px; transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1), box-shadow 0.3s ease, opacity 0.6s ease; position: relative; overflow: hidden; }
.btn-login:hover:not(:disabled) { transform: translateY(-3px) scale(1.015); box-shadow: 0 24px 48px -10px rgba(79, 70, 229, 0.6); }
.btn-login:active:not(:disabled) { transform: translateY(1px) scale(0.98); }
.btn-login:disabled { opacity: 0.55; cursor: not-allowed; box-shadow: none; }
.spinner { width: 18px; height: 18px; border: 2.5px solid rgba(255, 255, 255, 0.45); border-top-color: #fff; border-radius: 50%; animation: spin 0.7s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
.switch-row { margin: 16px 0 0; text-align: center; font-size: 13px; font-weight: 500; color: var(--text-soft); }
.switch-link { border: none; background: transparent; padding: 0; color: var(--indigo); font: 700 13px 'Plus Jakarta Sans'; cursor: pointer; text-decoration: none; }
.switch-link:hover { color: var(--violet); text-decoration: underline; }
.switch-link.as-btn:disabled { opacity: 0.5; cursor: not-allowed; }
.switch-muted { color: #9ca0c6; font-weight: 600; }
.step-hint { margin: 0 0 16px; font-size: 13px; line-height: 1.55; color: var(--text-soft); }
.step-hint b { color: var(--ink); font-weight: 700; }
.lbl-timer { font-size: 11.5px; font-weight: 600; color: var(--indigo); background: rgba(79, 70, 229, 0.1); padding: 2px 8px; border-radius: 6px; }
.help-center { text-align: center; }
/* 6-kotak OTP */
.otp-boxes { display: flex; gap: 10px; justify-content: center; margin: 8px 0 4px; }
.otp-box { width: 48px; height: 56px; text-align: center; font: 800 24px 'Plus Jakarta Sans'; color: var(--ink); background: #fff; border: 1.5px solid rgba(11, 16, 51, 0.15); border-radius: 14px; box-shadow: 0 2px 10px rgba(11, 16, 51, 0.03); outline: none; transition: border-color 0.2s ease, box-shadow 0.2s ease, transform 0.15s ease; }
.otp-box:focus { border-color: var(--indigo); box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.14); transform: translateY(-1px); }
.otp-boxes.is-error .otp-box { border-color: #ef4444; box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1); }
.otp-meta { margin: 14px 0 4px; text-align: center; font-size: 12.5px; font-weight: 500; color: var(--text-soft); }
.otp-meta i { color: var(--indigo); margin-right: 4px; }
.otp-expired { color: #dc2626; }
.otp-expired i { color: #dc2626; }
@media (max-width: 767px) {
    .otp-boxes { gap: 7px; }
    .otp-box { width: 42px; height: 50px; font-size: 20px; }
}
.ver-row { margin-top: 14px; display: flex; justify-content: space-between; align-items: center; font-size: 11.5px; color: var(--text-soft); }
.ver-row b { color: var(--ink); font-weight: 700; letter-spacing: 0.05em; }
.subs { position: relative; z-index: 3; display: flex; align-items: center; justify-content: space-between; padding: 16px 28px; margin: 0 56px 24px; background: rgba(255, 255, 255, 0.65); backdrop-filter: blur(25px); border: 1px solid rgba(255, 255, 255, 0.8); border-radius: 24px; box-shadow: 0 12px 36px -12px rgba(11, 16, 51, 0.1); }
.subs .lbl { font-size: 10.5px; color: var(--text-soft); letter-spacing: 0.22em; text-transform: uppercase; font-weight: 600; }
.subs .logos { display: flex; align-items: center; gap: 32px; }
.subs .logos img { height: 30px; opacity: 0.88; transition: opacity 0.3s ease, transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1); }
.subs .logos img:hover { opacity: 1; transform: scale(1.1) translateY(-2px); }
.subs .logos .dv { width: 1px; height: 22px; background: var(--line); }
.v3-toast { position: fixed; top: 20px; right: 20px; z-index: 50; display: flex; align-items: center; gap: 12px; max-width: min(92vw, 380px); padding: 14px 16px; border-radius: 14px; font-size: 0.9rem; font-weight: 500; color: #fff; box-shadow: 0 18px 40px -12px rgba(0, 0, 0, 0.35); backdrop-filter: blur(10px); }
.v3-toast--error { background: linear-gradient(120deg, #ef4444, #dc2626); }
.v3-toast--info { background: linear-gradient(120deg, var(--indigo), var(--violet)); }
.v3-toast span { flex: 1; }
.v3-toast__close { border: none; background: rgba(255, 255, 255, 0.18); color: #fff; width: 26px; height: 26px; border-radius: 8px; cursor: pointer; display: grid; place-items: center; }
.v3-toast-enter-active, .v3-toast-leave-active { transition: opacity 0.3s ease, transform 0.3s ease; }
.v3-toast-enter-from, .v3-toast-leave-to { opacity: 0; transform: translateX(40px); }
@keyframes ambientFade { 0% { opacity: 0; transform: scale(0.95) translateY(10px); } 100% { opacity: 1; transform: scale(1) translateY(0); } }
@keyframes revealDown { 0% { opacity: 0; transform: translateY(-20px); } 100% { opacity: 1; transform: translateY(0); } }
@keyframes revealUp { 0% { opacity: 0; transform: translateY(20px) scale(0.98); } 100% { opacity: 1; transform: translateY(0) scale(1); } }
@keyframes revealRight { 0% { opacity: 0; transform: translateX(-50px); } 100% { opacity: 1; transform: translateX(0); } }
@keyframes revealPop { 0% { opacity: 0; transform: translateX(60px) scale(0.95); } 100% { opacity: 1; transform: translateX(0) scale(1); } }
.animate-fade-down { animation: revealDown 1s cubic-bezier(0.22, 1, 0.36, 1) forwards; opacity: 0; }
.animate-fade-up { animation: revealUp 1s cubic-bezier(0.22, 1, 0.36, 1) forwards; animation-delay: 0.5s; opacity: 0; }
.animate-fade-right { animation: revealRight 1s cubic-bezier(0.22, 1, 0.36, 1) forwards; animation-delay: 0.15s; opacity: 0; }
.animate-fade-left { animation: revealPop 1.2s cubic-bezier(0.22, 1, 0.36, 1) forwards; animation-delay: 0.25s; opacity: 0; }
@media (max-width: 1099px) {
    .topbar { padding: 22px 36px; }
    .stage-grid { grid-template-columns: 1fr; gap: 32px; padding: 20px 36px 24px; }
    .display { font-size: clamp(48px, 6.5vw, 68px); line-height: 1.05; }
    .lede { margin-top: 18px; font-size: 14.5px; max-width: 100%; }
    .float-card { transform: none; max-width: 440px; margin: 0 auto; padding: 32px 30px 24px; }
    .subs { margin: 0 36px 24px; padding: 14px 22px; }
    .subs .logos { gap: 22px; }
    .subs .logos img { height: 24px; }
}
@media (max-width: 767px) {
    .topbar { padding: 12px 18px; flex-wrap: wrap; gap: 8px; }
    .mark-login .brand-logo { height: 28px; }
    .mark-login b { font-size: 13px; }
    .top-link { display: none; }
    .statement { text-align: center; }
    .eyebrow { display: none !important; }
    .display { font-size: clamp(26px, 7.5vw, 32px); line-height: 1.1; }
    .lede { display: none !important; }
    .stage-grid { display: flex; flex-direction: column; justify-content: center; flex: 1 1 auto; padding: 8px 18px 12px; gap: 16px; }
    .stage-side { width: 100%; }
    .float-card { transform: none; max-width: 100%; padding: 22px 20px 18px; border-radius: 22px; }
    .card-head { margin-bottom: 16px; }
    .card-head h3 { font-size: 18px; }
    .field { margin-bottom: 10px; }
    .input-wrap input { font-size: 14px; padding: 13px 16px; }
    .subs { margin: 0 18px 12px; padding: 8px 14px; flex-direction: column; gap: 4px; text-align: center; border-radius: 14px; }
    .subs .logos { gap: 12px; flex-wrap: wrap; justify-content: center; }
    .subs .logos img { height: 18px; }
    .subs .logos .dv { display: none; }
    .sub-mobile { display: block; font-size: 12.5px; color: var(--text-soft); font-weight: 500; margin-top: 8px; }
}
</style>

<style>
html, body, #app { background: #eef0ff !important; }
</style>
