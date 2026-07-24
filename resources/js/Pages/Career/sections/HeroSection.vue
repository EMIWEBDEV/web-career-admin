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
                    Bergabunglah dengan ekosistem people, pet &amp; manufacturing terkemuka di
                    Sumatera Selatan. Temukan peran, tumbuh, dan wujudkan versi terbaik dirimu.
                </p>

                <div class="wc-hero2__actions">
                    <button class="wc-hero2__btn wc-hero2__btn--primary" type="button" @click="goToSection('lowongan')">
                        <i class="bi bi-search"></i> Jelajahi Lowongan
                    </button>
                    <button v-if="hasMt" class="wc-hero2__btn wc-hero2__btn--glass" type="button" @click="goToSection('mt')">
                        <i class="bi bi-stars"></i> Management Trainee
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
                    <span class="wc-hero2__ribbon-dot" aria-hidden="true"></span>
                    <span>Ekosistem people, pet &amp; manufacturing yang terus bertumbuh</span>
                </div>
            </aside>
        </div>

        <button class="wc-hero2__cue" type="button" aria-label="Gulir ke bawah" @click="goToSection('achievement')">
            <i class="bi bi-arrow-down"></i>
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
    background: #6660b0; /* base violet lebih lembut — palet tidak terlalu pekat */
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
    object-position: center 32%;
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
/* Overlay VIOLET lembut — palet dilunakkan dengan wash putih, foto tim tetap terlihat. */
.wc-hero2__scrim {
    position: absolute;
    inset: 0;
    background:
        /* lapisan keterbacaan kiri — violet lebih terang & transparan */
        linear-gradient(102deg, rgba(74, 66, 150, 0.68) 0%, rgba(103, 94, 190, 0.48) 44%, rgba(148, 136, 220, 0.28) 74%, rgba(196, 181, 253, 0.14) 100%),
        /* wash putih halus dari kanan-atas agar tidak terlalu kontras */
        radial-gradient(120% 90% at 88% 8%, rgba(248, 247, 253, 0.3) 0%, rgba(248, 247, 253, 0) 46%),
        /* fade vertikal: sedikit gelap di atas, memutih halus ke bawah */
        linear-gradient(180deg, rgba(48, 42, 104, 0.28) 0%, rgba(48, 42, 104, 0) 24%, rgba(248, 247, 253, 0) 56%, rgba(248, 247, 253, 0.8) 100%);
}
.wc-hero2__glow {
    display: none;
}
.wc-hero2__vignette {
    display: none;
}
.wc-hero2__fade {
    position: absolute;
    left: 0;
    right: 0;
    bottom: -1px;
    height: 4rem;
    background: linear-gradient(180deg, transparent 0%, rgba(245, 244, 253, 0.4) 55%, #f8fafc 100%);
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
    gap: 8px;
    padding: 7px 14px;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.15);
    border: 1px solid rgba(255, 255, 255, 0.26);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    color: #efeafe;
    font-size: 11.5px;
    font-weight: 800;
    letter-spacing: 0.14em;
    text-transform: uppercase;
}
.wc-hero2__dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: #c4b5fd;
    box-shadow: 0 0 0 4px rgba(196, 181, 253, 0.34);
}
.wc-hero2__title {
    margin: 20px 0 0;
    font-size: clamp(2rem, 4.6vw, 3.5rem);
    font-weight: 800;
    line-height: 1.08;
    letter-spacing: -0.03em;
    color: #ffffff;
    text-wrap: pretty;
}
/* Desain: span "EVO Group" SOLID #c4b5fd (bukan gradien). */
.wc-hero2__grad {
    color: #c4b5fd;
}
.wc-hero2__sub {
    max-width: 520px;
    margin: 18px 0 0;
    color: rgba(255, 255, 255, 0.86);
    font-size: 16px;
    font-weight: 400;
    line-height: 1.65;
    text-wrap: pretty;
}
.wc-hero2__actions {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    margin: 28px 0 0;
}
.wc-hero2__btn {
    display: inline-flex;
    align-items: center;
    gap: 9px;
    border: none;
    padding: 15px 26px;
    border-radius: 14px;
    font: inherit;
    font-size: 14.5px;
    font-weight: 800;
    cursor: pointer;
    transition: transform 0.16s ease, box-shadow 0.16s ease, background 0.16s ease;
}
.wc-hero2__btn--primary {
    background: linear-gradient(135deg, #8b5cf6, #6366f1);
    color: #fff;
    box-shadow: 0 16px 40px rgba(99, 102, 241, 0.5);
}
.wc-hero2__btn--primary:hover {
    transform: translateY(-2px);
}
.wc-hero2__btn--glass {
    padding: 15px 24px;
    font-weight: 700;
    background: rgba(255, 255, 255, 0.14);
    border: 1px solid rgba(255, 255, 255, 0.3);
    color: #fff;
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
}
.wc-hero2__btn--glass:hover {
    background: rgba(255, 255, 255, 0.24);
}
.wc-hero2__btn--glass i {
    color: #c4b5fd;
}
.wc-hero2__chips {
    list-style: none;
    margin: 26px 0 0;
    padding: 0;
    display: flex;
    flex-wrap: wrap;
    gap: 9px;
}
.wc-hero2__chips li {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 8px 13px;
    border-radius: 11px;
    background: rgba(255, 255, 255, 0.11);
    border: 1px solid rgba(255, 255, 255, 0.2);
    color: rgba(255, 255, 255, 0.94);
    font-size: 12.5px;
    font-weight: 600;
}
.wc-hero2__chips i {
    color: rgba(255, 255, 255, 0.94);
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
    gap: 22px;
}
.wc-hero2__stat {
    display: flex;
    flex-direction: column;
    gap: 2px;
    text-align: left;
}
.wc-hero2__stat strong {
    font-size: 30px;
    font-weight: 800;
    letter-spacing: -0.02em;
    line-height: 1.1;
    color: #fff;
}
.wc-hero2__stat strong span {
    color: #c4b5fd;
}
.wc-hero2__stat small {
    color: rgba(255, 255, 255, 0.74);
    font-size: 11px;
    font-weight: 400;
    line-height: 1.3;
}
.wc-hero2__vr {
    width: 1px;
    background: rgba(255, 255, 255, 0.2);
    flex: none;
}
.wc-hero2__ribbon {
    position: relative;
    display: flex;
    align-items: center;
    gap: 8px;
    margin-top: 14px;
    padding-top: 14px;
    border-top: 1px solid rgba(255, 255, 255, 0.18);
    color: rgba(255, 255, 255, 0.82);
    font-size: 11.5px;
    font-weight: 400;
    line-height: 1.45;
}
.wc-hero2__ribbon-dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: #34d399;
    flex: none;
}
/* Cue: panah polos ala desain (tanpa lingkaran kaca). */
.wc-hero2__cue {
    position: absolute;
    z-index: 2;
    left: 50%;
    bottom: 18px;
    transform: translateX(-50%);
    border: none;
    background: transparent;
    color: rgba(255, 255, 255, 0.64);
    font-size: 1.6rem;
    cursor: pointer;
    animation: wcCueBounce 2.4s ease-in-out infinite;
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
        background: linear-gradient(180deg, rgba(58, 50, 118, 0.7) 0%, rgba(88, 80, 168, 0.5) 45%, rgba(248, 247, 253, 0.16) 100%);
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
