<!-- WEB CAREER — CTA penutup landing (enterprise, animated aurora + radar + partikel) -->
<template>
    <section class="wc-cta wc-reveal">
        <div class="wc-cta__inner">
            <!-- Lapisan latar beranimasi -->
            <div class="wc-cta__aurora" aria-hidden="true"></div>
            <div class="wc-cta__grid" aria-hidden="true"></div>

            <!-- Cincin radar (SVG) -->
            <svg class="wc-cta__radar" viewBox="0 0 400 400" aria-hidden="true" preserveAspectRatio="xMidYMid slice">
                <defs>
                    <radialGradient id="ctaRingFade" cx="50%" cy="50%" r="50%">
                        <stop offset="55%" stop-color="rgba(148,163,255,0)" />
                        <stop offset="100%" stop-color="rgba(148,163,255,.35)" />
                    </radialGradient>
                </defs>
                <g fill="none" stroke="url(#ctaRingFade)" stroke-width="1">
                    <circle class="wc-cta__ring" cx="200" cy="200" r="60" />
                    <circle class="wc-cta__ring" cx="200" cy="200" r="120" />
                    <circle class="wc-cta__ring" cx="200" cy="200" r="180" />
                </g>
            </svg>

            <!-- Partikel mengambang -->
            <div class="wc-cta__orbs" aria-hidden="true">
                <span v-for="n in 6" :key="n" :style="orbStyle(n)"></span>
            </div>

            <div class="wc-cta__content">
                <span class="wc-cta__eyebrow"><i class="bi bi-stars"></i> Mulai Perjalananmu</span>
                <h2>
                    Siap naik level bersama
                    <span class="wc-cta__grad">EVO Group</span>?
                </h2>
                <p>Ambil langkah pertama menuju karier impianmu hari ini — proses transparan, tim yang suportif, dan ruang untuk bertumbuh.</p>

                <div class="wc-cta__actions">
                    <button class="wc-cta__btn wc-cta__btn--primary" type="button" @click="goToSection('lowongan')">
                        <i class="bi bi-briefcase-fill"></i> Lihat Semua Lowongan
                    </button>
                    <Link class="wc-cta__btn wc-cta__btn--ghost" href="/register">
                        <i class="bi bi-person-plus"></i> Buat Akun
                    </Link>
                </div>

                <ul class="wc-cta__trust">
                    <li><i class="bi bi-shield-check"></i> Proses seleksi transparan</li>
                    <li><i class="bi bi-lightning-charge-fill"></i> Respons cepat</li>
                    <li><i class="bi bi-people-fill"></i> Lingkungan suportif</li>
                </ul>
            </div>
        </div>
    </section>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import { goToSection } from '../careerData';

// Posisi partikel deterministik (tanpa random) agar konsisten
const ORB = [
    { l: 12, t: 26, s: 7, d: 0 },
    { l: 82, t: 18, s: 5, d: 1.4 },
    { l: 68, t: 74, s: 9, d: 0.7 },
    { l: 24, t: 78, s: 5, d: 2.1 },
    { l: 91, t: 58, s: 6, d: 1.1 },
    { l: 45, t: 12, s: 4, d: 2.7 },
];
function orbStyle(n) {
    const o = ORB[(n - 1) % ORB.length];
    return {
        left: o.l + '%',
        top: o.t + '%',
        width: o.s + 'px',
        height: o.s + 'px',
        animationDelay: o.d + 's',
    };
}
</script>

<style scoped>
.wc-cta {
    width: min(1180px, calc(100vw - 2rem));
    margin: 0 auto clamp(3rem, 6vw, 5rem);
}
.wc-cta__inner {
    position: relative;
    isolation: isolate;
    overflow: hidden;
    padding: clamp(2.75rem, 6vw, 4.75rem) clamp(1.5rem, 5vw, 4rem);
    border-radius: 2rem;
    text-align: center;
    color: #f8fafc;
    background:
        radial-gradient(120% 140% at 12% 8%, rgba(99, 102, 241, 0.32), transparent 46%),
        radial-gradient(120% 130% at 92% 96%, rgba(139, 92, 246, 0.26), transparent 50%),
        linear-gradient(150deg, #0b1120 0%, #141a3d 46%, #0b1224 100%);
    border: 1px solid rgba(148, 163, 255, 0.16);
    box-shadow: 0 34px 80px rgba(8, 11, 30, 0.5), inset 0 1px 0 rgba(255, 255, 255, 0.06);
}

/* Aurora drift lembut */
.wc-cta__aurora {
    position: absolute;
    inset: -40%;
    z-index: 0;
    pointer-events: none;
    background:
        radial-gradient(38% 44% at 30% 40%, rgba(129, 140, 248, 0.4), transparent 60%),
        radial-gradient(34% 40% at 74% 62%, rgba(167, 139, 250, 0.32), transparent 62%),
        radial-gradient(30% 36% at 58% 22%, rgba(251, 191, 36, 0.16), transparent 64%);
    filter: blur(6px);
    animation: ctaAurora 18s ease-in-out infinite alternate;
}
@keyframes ctaAurora {
    0% { transform: translate3d(-3%, -2%, 0) rotate(0deg) scale(1.02); }
    100% { transform: translate3d(4%, 3%, 0) rotate(8deg) scale(1.12); }
}

/* Dot-grid halus */
.wc-cta__grid {
    position: absolute;
    inset: 0;
    z-index: 0;
    pointer-events: none;
    background-image: radial-gradient(rgba(255, 255, 255, 0.16) 1px, transparent 1.4px);
    background-size: 26px 26px;
    -webkit-mask-image: radial-gradient(80% 80% at 50% 45%, #000 30%, transparent 78%);
    mask-image: radial-gradient(80% 80% at 50% 45%, #000 30%, transparent 78%);
    opacity: 0.5;
    animation: ctaGridPan 26s linear infinite;
}
@keyframes ctaGridPan {
    to { background-position: 26px 26px; }
}

/* Cincin radar */
.wc-cta__radar {
    position: absolute;
    z-index: 0;
    top: 50%;
    left: 50%;
    width: min(560px, 92%);
    height: min(560px, 92%);
    transform: translate(-50%, -50%);
    pointer-events: none;
    opacity: 0.7;
}
.wc-cta__ring {
    transform-box: fill-box;
    transform-origin: center;
    animation: ctaPulse 4.5s ease-out infinite;
}
.wc-cta__ring:nth-child(2) { animation-delay: 1.5s; }
.wc-cta__ring:nth-child(3) { animation-delay: 3s; }
@keyframes ctaPulse {
    0% { transform: scale(0.6); opacity: 0; }
    35% { opacity: 0.9; }
    100% { transform: scale(1.25); opacity: 0; }
}

/* Partikel */
.wc-cta__orbs {
    position: absolute;
    inset: 0;
    z-index: 0;
    pointer-events: none;
}
.wc-cta__orbs span {
    position: absolute;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(255, 255, 255, 0.95), rgba(129, 140, 248, 0.35) 60%, transparent 72%);
    box-shadow: 0 0 12px rgba(148, 163, 255, 0.6);
    animation: ctaFloat 7s ease-in-out infinite;
}
@keyframes ctaFloat {
    0%, 100% { transform: translateY(0); opacity: 0.4; }
    50% { transform: translateY(-16px); opacity: 1; }
}

/* Konten */
.wc-cta__content {
    position: relative;
    z-index: 1;
}
.wc-cta__eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.45rem 1rem;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.08);
    border: 1px solid rgba(255, 255, 255, 0.18);
    color: #e0e7ff;
    font-size: 0.72rem;
    font-weight: 900;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    margin-bottom: 1.1rem;
}
.wc-cta__eyebrow i {
    color: #fcd34d;
}
.wc-cta h2 {
    margin: 0 0 0.7rem;
    font-size: clamp(1.55rem, 3.6vw, 2.55rem);
    font-weight: 900;
    letter-spacing: -0.025em;
    line-height: 1.12;
    color: #fff;
}
.wc-cta__grad {
    background: linear-gradient(100deg, #a5b4fc 0%, #c4b5fd 55%, #fcd34d 120%);
    -webkit-background-clip: text;
    background-clip: text;
    -webkit-text-fill-color: transparent;
}
.wc-cta p {
    margin: 0 auto 1.9rem;
    max-width: 38rem;
    color: rgba(226, 232, 240, 0.86);
    font-size: clamp(0.9rem, 2vw, 1.04rem);
    font-weight: 600;
    line-height: 1.65;
}
.wc-cta__actions {
    display: flex;
    flex-wrap: wrap;
    gap: 0.8rem;
    justify-content: center;
    margin-bottom: 2rem;
}
.wc-cta__btn {
    display: inline-flex;
    align-items: center;
    gap: 0.55rem;
    padding: 0.95rem 1.75rem;
    border-radius: 0.95rem;
    border: none;
    font: inherit;
    font-size: 0.92rem;
    font-weight: 900;
    text-decoration: none;
    cursor: pointer;
    transition: transform 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
}
.wc-cta__btn--primary {
    color: #422006;
    background: linear-gradient(135deg, #fde68a, #fbbf24 55%, #f59e0b);
    box-shadow: 0 16px 38px rgba(245, 158, 11, 0.4);
}
.wc-cta__btn--primary:hover {
    transform: translateY(-3px);
    box-shadow: 0 22px 48px rgba(245, 158, 11, 0.52);
}
.wc-cta__btn--ghost {
    color: #fff;
    background: rgba(255, 255, 255, 0.08);
    border: 1px solid rgba(255, 255, 255, 0.28);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
}
.wc-cta__btn--ghost:hover {
    background: rgba(255, 255, 255, 0.16);
    transform: translateY(-3px);
}
.wc-cta__trust {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 0.65rem 1.6rem;
}
.wc-cta__trust li {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    color: rgba(226, 232, 240, 0.78);
    font-size: 0.8rem;
    font-weight: 800;
}
.wc-cta__trust i {
    color: #c4b5fd;
}
@media (max-width: 640px) {
    .wc-cta__actions .wc-cta__btn {
        flex: 1;
        justify-content: center;
    }
}
</style>
