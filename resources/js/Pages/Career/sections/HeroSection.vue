<!-- WEB CAREER — Section 1: Hero (foto full-bleed + scrim brand + glass, cinematic) -->
<template>
    <section id="hero" class="wc-hero2">
        <!-- Media latar -->
        <div class="wc-hero2__media" aria-hidden="true">
            <img class="wc-hero2__img" src="/img/IMG_5417.JPG" alt="" decoding="async" fetchpriority="high" />
            <div class="wc-hero2__scrim"></div>
            <div class="wc-hero2__glow"></div>
            <div class="wc-hero2__vignette"></div>
            <div class="wc-hero2__fade"></div>
        </div>

        <div class="wc-hero2__inner">
            <div class="wc-hero2__content wc-reveal" style="--d: 0ms">
                <span class="wc-hero2__eyebrow"><span class="wc-hero2__dot"></span> EVO Group Career</span>
                <h1 class="wc-hero2__title">
                    Naik Level Hidupmu Bersama
                    <span class="wc-hero2__grad">EVO Group</span>
                </h1>
                <p class="wc-hero2__sub">
                    Bergabunglah dengan ekosistem <b>people</b>, <b>pet</b>, &amp; <b>manufacturing</b> terkemuka di
                    Sumatera Selatan. Temukan peran, bertumbuh, dan wujudkan versi terbaik dirimu.
                </p>

                <div class="wc-hero2__actions">
                    <button class="wc-hero2__btn wc-hero2__btn--primary" type="button" @click="goToSection('lowongan')">
                        <i class="bi bi-briefcase-fill"></i> Jelajahi Lowongan
                    </button>
                    <button v-if="hasMt" class="wc-hero2__btn wc-hero2__btn--glass" type="button" @click="goToSection('mt')">
                        <i class="bi bi-mortarboard-fill"></i> Management Trainee
                    </button>
                </div>

                <ul class="wc-hero2__chips">
                    <li v-for="b in benefits" :key="b.title"><i class="bi" :class="b.icon"></i> {{ b.title }}</li>
                </ul>
            </div>

            <!-- Kartu glass mengambang -->
            <aside class="wc-hero2__card wc-reveal" style="--d: 180ms">
                <div class="wc-hero2__card-shine" aria-hidden="true"></div>
                <div class="wc-hero2__stats">
                    <div class="wc-hero2__stat">
                        <strong>1.200<span>+</span></strong>
                        <small>Karyawan Aktif</small>
                    </div>
                    <span class="wc-hero2__vr"></span>
                    <div class="wc-hero2__stat">
                        <strong>15<span>+</span></strong>
                        <small>Tahun Berkarya</small>
                    </div>
                    <span class="wc-hero2__vr"></span>
                    <div class="wc-hero2__stat">
                        <strong>2</strong>
                        <small>Lokasi Operasional</small>
                    </div>
                </div>
                <div class="wc-hero2__ribbon">
                    <i class="bi bi-patch-check-fill"></i>
                    <span>Ekosistem people, pet &amp; manufacturing yang terus bertumbuh.</span>
                </div>
            </aside>
        </div>

        <button class="wc-hero2__cue" type="button" aria-label="Gulir ke bawah" @click="goToSection('achievement')">
            <i class="bi bi-chevron-double-down"></i>
        </button>
    </section>
</template>

<script setup>
import { goToSection } from '../careerData';

defineProps({
    benefits: { type: Array, default: () => [] },
    hasMt: { type: Boolean, default: false },
});
</script>

<style scoped>
/* HERO v2 — palet: navy scrim | aksen indigo→cyan | kaca putih transparan */
.wc-hero2 {
    position: relative;
    min-height: 100vh;
    min-height: 100svh;
    display: flex;
    align-items: center;
    overflow: hidden;
    isolation: isolate;
}
.wc-hero2__media {
    position: absolute;
    inset: 0;
    z-index: -1;
    overflow: hidden;
}
.wc-hero2__img {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
    object-position: center 42%;
    image-orientation: from-image;
    transform: scale(1.06);
    animation: wcHeroZoom 22s ease-in-out infinite alternate;
    will-change: transform;
}
@keyframes wcHeroZoom {
    to {
        transform: scale(1.16);
    }
}
.wc-hero2__scrim {
    position: absolute;
    inset: 0;
    background:
        linear-gradient(102deg, rgba(6, 9, 26, 0.94) 0%, rgba(9, 13, 38, 0.78) 32%, rgba(9, 13, 38, 0.34) 60%, rgba(9, 13, 38, 0.06) 100%),
        linear-gradient(180deg, rgba(6, 9, 26, 0.5) 0%, transparent 26%, rgba(6, 9, 26, 0.28) 72%, rgba(6, 9, 26, 0.72) 100%);
}
.wc-hero2__glow {
    position: absolute;
    inset: 0;
    background:
        radial-gradient(52% 60% at 14% 46%, rgba(99, 102, 241, 0.34), transparent 68%),
        radial-gradient(46% 52% at 88% 82%, rgba(139, 92, 246, 0.24), transparent 70%);
    mix-blend-mode: screen;
    pointer-events: none;
}
.wc-hero2__vignette {
    position: absolute;
    inset: 0;
    pointer-events: none;
    background: radial-gradient(125% 105% at 50% 38%, transparent 54%, rgba(6, 9, 26, 0.5) 100%);
}
.wc-hero2__fade {
    position: absolute;
    left: 0;
    right: 0;
    bottom: -1px;
    height: 4.5rem;
    background: linear-gradient(180deg, transparent 0%, rgba(248, 250, 252, 0.18) 48%, rgba(248, 250, 252, 0.8) 84%, #f8fafc 100%);
    pointer-events: none;
}
.wc-hero2__inner {
    position: relative;
    z-index: 1;
    width: min(1180px, calc(100vw - 2rem));
    margin: 0 auto;
    padding: clamp(7rem, 15vh, 10.5rem) 0 clamp(4.5rem, 12vh, 7.5rem);
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(280px, 330px);
    align-items: center;
    gap: 2.5rem;
    color: #f8fafc;
}
.wc-hero2__content {
    max-width: 640px;
}
.wc-hero2__eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.45rem 0.95rem;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.2);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    color: #e0e7ff;
    font-size: 0.72rem;
    font-weight: 900;
    letter-spacing: 0.1em;
    text-transform: uppercase;
}
.wc-hero2__dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: #a78bfa;
    box-shadow: 0 0 0 4px rgba(167, 139, 250, 0.25), 0 0 12px #a78bfa;
}
.wc-hero2__title {
    margin: 1.2rem 0 1.1rem;
    font-size: clamp(2.2rem, 5.4vw, 4.1rem);
    font-weight: 900;
    line-height: 1.05;
    letter-spacing: -0.03em;
    color: #ffffff;
    text-shadow: 0 2px 40px rgba(0, 0, 0, 0.4);
}
.wc-hero2__grad {
    background: linear-gradient(100deg, #a5b4fc 0%, #818cf8 40%, #c4b5fd 100%);
    -webkit-background-clip: text;
    background-clip: text;
    -webkit-text-fill-color: transparent;
}
.wc-hero2__sub {
    max-width: 40rem;
    margin: 0;
    color: rgba(226, 232, 240, 0.92);
    font-size: clamp(1rem, 2vw, 1.15rem);
    font-weight: 600;
    line-height: 1.72;
    text-shadow: 0 1px 18px rgba(0, 0, 0, 0.4);
}
.wc-hero2__sub b {
    color: #fff;
    font-weight: 900;
}
.wc-hero2__actions {
    display: flex;
    flex-wrap: wrap;
    gap: 0.8rem;
    margin: 2rem 0 1.9rem;
}
.wc-hero2__btn {
    display: inline-flex;
    align-items: center;
    gap: 0.55rem;
    border: none;
    padding: 0.95rem 1.6rem;
    border-radius: 0.9rem;
    font: inherit;
    font-size: 0.92rem;
    font-weight: 900;
    cursor: pointer;
    transition: transform 0.18s ease, box-shadow 0.18s ease, background 0.18s ease;
}
.wc-hero2__btn--primary {
    background: linear-gradient(135deg, #6366f1, #2563eb);
    color: #fff;
    box-shadow: 0 18px 40px rgba(37, 99, 235, 0.45);
}
.wc-hero2__btn--primary:hover {
    transform: translateY(-3px);
    box-shadow: 0 24px 50px rgba(37, 99, 235, 0.55);
}
.wc-hero2__btn--glass {
    background: rgba(255, 255, 255, 0.12);
    border: 1px solid rgba(255, 255, 255, 0.32);
    color: #fff;
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
}
.wc-hero2__btn--glass:hover {
    background: rgba(255, 255, 255, 0.22);
    transform: translateY(-3px);
}
.wc-hero2__chips {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-wrap: wrap;
    gap: 0.55rem;
}
.wc-hero2__chips li {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.45rem 0.85rem;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.09);
    border: 1px solid rgba(255, 255, 255, 0.16);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    color: #e2e8f0;
    font-size: 0.78rem;
    font-weight: 800;
}
.wc-hero2__chips i {
    color: #c4b5fd;
}
.wc-hero2__card {
    position: relative;
    overflow: hidden;
    padding: 1.6rem 1.7rem;
    border-radius: 1.5rem;
    background: rgba(255, 255, 255, 0.09);
    border: 1px solid rgba(255, 255, 255, 0.2);
    backdrop-filter: blur(20px) saturate(1.3);
    -webkit-backdrop-filter: blur(20px) saturate(1.3);
    box-shadow: 0 30px 70px rgba(0, 0, 0, 0.4), inset 0 1px 0 rgba(255, 255, 255, 0.28);
    justify-self: end;
    width: 100%;
    max-width: 330px;
}
.wc-hero2__card-shine {
    position: absolute;
    top: -60%;
    right: -30%;
    width: 18rem;
    height: 18rem;
    background: radial-gradient(circle, rgba(255, 255, 255, 0.22), transparent 62%);
    pointer-events: none;
}
.wc-hero2__stats {
    position: relative;
    display: flex;
    align-items: stretch;
    justify-content: space-between;
    gap: 0.5rem;
}
.wc-hero2__stat {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
    text-align: center;
    flex: 1;
}
.wc-hero2__stat strong {
    font-size: 1.75rem;
    font-weight: 900;
    letter-spacing: -0.02em;
    line-height: 1;
    color: #fff;
}
.wc-hero2__stat strong span {
    color: #c4b5fd;
}
.wc-hero2__stat small {
    color: rgba(226, 232, 240, 0.82);
    font-size: 0.68rem;
    font-weight: 800;
    line-height: 1.25;
}
.wc-hero2__vr {
    width: 1px;
    background: rgba(255, 255, 255, 0.18);
    flex: none;
}
.wc-hero2__ribbon {
    position: relative;
    display: flex;
    align-items: flex-start;
    gap: 0.55rem;
    margin-top: 1.25rem;
    padding-top: 1.1rem;
    border-top: 1px solid rgba(255, 255, 255, 0.16);
    color: rgba(226, 232, 240, 0.9);
    font-size: 0.8rem;
    font-weight: 700;
    line-height: 1.45;
}
.wc-hero2__ribbon i {
    color: #34d399;
    margin-top: 0.1rem;
    font-size: 0.95rem;
    flex: none;
}
.wc-hero2__cue {
    position: absolute;
    z-index: 2;
    left: 50%;
    bottom: 1.5rem;
    transform: translateX(-50%);
    width: 2.75rem;
    height: 2.75rem;
    border-radius: 50%;
    border: 1px solid rgba(255, 255, 255, 0.3);
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    color: #fff;
    font-size: 1rem;
    cursor: pointer;
    animation: wcCueBounce 2s ease-in-out infinite;
}
@keyframes wcCueBounce {
    0%, 100% {
        transform: translateX(-50%) translateY(0);
    }
    50% {
        transform: translateX(-50%) translateY(6px);
    }
}
@media (max-width: 1024px) {
    .wc-hero2__inner {
        grid-template-columns: 1fr;
        gap: 2rem;
    }
    .wc-hero2__card {
        justify-self: start;
        max-width: 520px;
    }
}
@media (max-width: 640px) {
    .wc-hero2__scrim {
        background: linear-gradient(180deg, rgba(6, 9, 26, 0.72) 0%, rgba(9, 13, 38, 0.55) 45%, rgba(6, 9, 26, 0.82) 100%);
    }
    .wc-hero2__inner {
        padding-top: 6.5rem;
    }
    .wc-hero2__actions .wc-hero2__btn {
        flex: 1;
        justify-content: center;
    }
    .wc-hero2__stat strong {
        font-size: 1.4rem;
    }
    .wc-hero2__cue {
        display: none;
    }
}
</style>
