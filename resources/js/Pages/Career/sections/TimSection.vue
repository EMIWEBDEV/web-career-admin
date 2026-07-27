<!-- WEB CAREER — Section: Fungsi Perusahaan (kartu tim horizontal, snap scroll) -->
<template>
    <section id="tim" class="wc-section wc-tim">
        <div class="wc-sec-head wc-reveal">
            <span class="wc-eyebrow"><span class="wc-dot"></span> Fungsi Perusahaan</span>
            <h2>Tim yang <span class="wc-grad">menjalankan Evo.</span></h2>
            <p>Tarik atau geser kartu untuk mengenal kontribusi setiap fungsi dalam perusahaan.</p>
        </div>

        <div class="wc-tim__viewport" :class="{ 'at-start': atStart, 'at-end': atEnd }">
            <div
                ref="track"
                class="wc-tim__track"
                :class="[
                    slideDir === 1 ? 'is-slide-next' : slideDir === -1 ? 'is-slide-prev' : '',
                    { 'is-dragging': isDragging, 'is-free': snapOff },
                ]"
                @scroll.passive="onScroll"
                @pointerdown="onPointerDown"
                @dragstart.prevent
            >
                <article
                    v-for="(t, i) in tim"
                    :key="t.nama"
                    class="wc-tim__card wc-reveal"
                    :style="{ '--d': i * 60 + 'ms', '--i': i }"
                    @click="onCardClick(t)"
                >
                    <div class="wc-tim__photo" :style="{ backgroundPosition: t.pos }"></div>
                    <div class="wc-tim__scrim"></div>

                    <!-- Jumlah lowongan terbuka pada fungsi ini -->
                    <span class="wc-tim__lowongan" :class="{ 'is-empty': !t.lowongan }">
                        <i class="bi" :class="t.lowongan ? 'bi-briefcase-fill' : 'bi-briefcase'"></i>
                        {{ t.lowongan ? `${t.lowongan} lowongan` : 'Belum ada lowongan' }}
                    </span>

                    <!-- Nama fungsi: tampil saat kartu belum di-hover -->
                    <div class="wc-tim__body">
                        <h3>{{ t.nama }}</h3>
                    </div>

                    <!-- Overlay penuh: hanya deskripsi + tombol detail -->
                    <div class="wc-tim__overlay">
                        <p>{{ t.deskripsi }}</p>
                        <button class="wc-tim__btn" type="button" @click.stop="onCardClick(t)">
                            Lihat detail <i class="bi bi-arrow-right"></i>
                        </button>
                    </div>
                </article>
            </div>
        </div>

        <div class="wc-tim__nav wc-reveal">
            <button
                class="wc-tim__nav-btn"
                type="button"
                aria-label="Sebelumnya"
                :disabled="atStart"
                @click="slide(-1)"
            >
                <i class="bi bi-arrow-left"></i>
            </button>

            <div class="wc-tim__bar" role="presentation">
                <span class="wc-tim__bar-fill" :style="{ transform: `scaleX(${progress})` }"></span>
            </div>

            <button
                class="wc-tim__nav-btn"
                type="button"
                aria-label="Berikutnya"
                :disabled="atEnd"
                @click="slide(1)"
            >
                <i class="bi bi-arrow-right"></i>
            </button>
        </div>

        <div class="wc-tim__more wc-reveal">
            <Link href="/karir/tim" class="wc-tim__more-btn">
                <i class="bi bi-grid-3x3-gap-fill"></i> Lihat semua tim & lowongan
                <i class="bi bi-arrow-right"></i>
            </Link>
        </div>
    </section>
</template>

<script setup>
import { Link, router } from '@inertiajs/vue3';
import { onMounted, onUnmounted, ref } from 'vue';

// `lowongan` = jumlah lowongan terbuka pada fungsi tersebut (sementara statis).
const tim = [
    { nama: 'Manufacturing', slug: 'manufacturing', pos: '50% center', lowongan: 3, deskripsi: 'Menjalankan proses produksi pet food secara konsisten dengan perhatian pada mutu dan efisiensi.' },
    { nama: 'Quality & Research', slug: 'quality-research', pos: '30% center', lowongan: 2, deskripsi: 'Menjaga kualitas serta mendukung riset formula bernutrisi seimbang bagi hewan peliharaan.' },
    { nama: 'Sales & Distribution', slug: 'sales-distribution', pos: '70% center', lowongan: 5, deskripsi: 'Mengembangkan pasar dan memastikan produk dapat dijangkau melalui jaringan distribusi nasional.' },
    { nama: 'Brand & Marketing', slug: 'brand-marketing', pos: '40% center', lowongan: 1, deskripsi: 'Membangun merek dan menyampaikan manfaat produk secara relevan kepada konsumen.' },
    { nama: 'Supply Chain', slug: 'supply-chain', pos: '60% center', lowongan: 0, deskripsi: 'Mengelola ketersediaan bahan dan alur pasok untuk mendukung kegiatan operasional perusahaan.' },
    { nama: 'Corporate Support', slug: 'corporate-support', pos: '35% center', lowongan: 2, deskripsi: 'Memperkuat organisasi melalui fungsi keuangan, sumber daya manusia, dan administrasi perusahaan.' },
];

// Halaman perkenalan tim: /karir/tim/{slug}
function bukaDetail(t) {
    router.visit(`/karir/tim/${t.slug}`);
}

const track = ref(null);
const atStart = ref(true);
const atEnd = ref(false);
const progress = ref(0);
const slideDir = ref(0);
const isDragging = ref(false);
// Snap dimatikan sementara saat geser manual / animasi JS agar tidak saling rebut
const snapOff = ref(false);

let slideTimeout = null;
let rafId = null;
let drag = null;
let suppressClick = false;
let clickGuard = null;

function reduceMotion() {
    return typeof window !== 'undefined' && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}

/** Lebar satu langkah geser = lebar kartu + jarak antar kartu. */
function stepWidth() {
    const el = track.value;
    if (!el) return 300;
    const card = el.querySelector('.wc-tim__card');
    const gap = parseFloat(getComputedStyle(el).columnGap || '16') || 16;
    return card ? card.offsetWidth + gap : 300;
}

function onScroll() {
    const el = track.value;
    if (!el) return;
    const max = el.scrollWidth - el.clientWidth;
    atStart.value = el.scrollLeft <= 4;
    atEnd.value = el.scrollLeft >= max - 4;
    progress.value = max > 0 ? Math.min(1, Math.max(0.08, el.scrollLeft / max)) : 1;
}

/** Geser halus ala carousel (easing sendiri, tidak memakai smooth bawaan browser). */
function animateTo(target, duration = 560) {
    const el = track.value;
    if (!el) return;
    if (rafId) cancelAnimationFrame(rafId);

    const max = el.scrollWidth - el.clientWidth;
    const to = Math.max(0, Math.min(max, target));

    if (reduceMotion()) {
        el.scrollLeft = to;
        onScroll();
        return;
    }

    const from = el.scrollLeft;
    const dist = to - from;
    if (Math.abs(dist) < 1) return;

    snapOff.value = true;
    let startTime = null;
    const tick = (now) => {
        if (startTime === null) startTime = now;
        const p = Math.min(1, (now - startTime) / duration);
        const eased = 1 - Math.pow(1 - p, 3); // easeOutCubic
        el.scrollLeft = from + dist * eased;
        if (p < 1) {
            rafId = requestAnimationFrame(tick);
        } else {
            rafId = null;
            snapOff.value = false;
        }
    };
    rafId = requestAnimationFrame(tick);
}

/** Berhenti pas di batas kartu terdekat dari posisi tertentu. */
function snapTo(position, duration) {
    const step = stepWidth();
    animateTo(Math.round(position / step) * step, duration);
}

function nudge(dir) {
    if (reduceMotion()) return;
    slideDir.value = 0;
    if (slideTimeout) clearTimeout(slideTimeout);
    requestAnimationFrame(() => (slideDir.value = dir));
    slideTimeout = setTimeout(() => (slideDir.value = 0), 620);
}

// Geser tepat satu kartu supaya selalu berhenti rapi
function slide(dir) {
    const el = track.value;
    if (!el) return;
    const step = stepWidth();
    animateTo((Math.round(el.scrollLeft / step) + dir) * step);
    nudge(dir);
}

/* ── Geser dengan tarikan mouse. Layar sentuh memakai scroll bawaan
      (sudah mulus + punya momentum sendiri), jadi tidak diambil alih. ── */
function onPointerDown(e) {
    if (e.pointerType !== 'mouse' || e.button !== 0) return;
    const el = track.value;
    if (!el) return;

    if (rafId) cancelAnimationFrame(rafId);
    rafId = null;

    drag = { x: e.clientX, scroll: el.scrollLeft, lastX: e.clientX, lastT: e.timeStamp, v: 0, moved: 0 };
    isDragging.value = true;
    snapOff.value = true;

    // Catatan penting: JANGAN pakai setPointerCapture ataupun preventDefault di
    // sini. Keduanya membuat event `click` tidak sampai ke kartu (capture
    // mengalihkan target klik ke track, preventDefault membatalkan compat event),
    // sehingga kartu tidak bisa dibuka. Gerakan cukup dipantau lewat window.
    window.addEventListener('pointermove', onPointerMove);
    window.addEventListener('pointerup', onPointerUp);
    window.addEventListener('pointercancel', onPointerUp);
}

function onPointerMove(e) {
    if (!drag) return;
    const el = track.value;
    if (!el) return;
    const dx = e.clientX - drag.x;
    el.scrollLeft = drag.scroll - dx;

    const dt = e.timeStamp - drag.lastT;
    if (dt > 0) drag.v = (e.clientX - drag.lastX) / dt; // px per ms
    drag.lastX = e.clientX;
    drag.lastT = e.timeStamp;
    drag.moved = Math.max(drag.moved, Math.abs(dx));
}

function onPointerUp() {
    window.removeEventListener('pointermove', onPointerMove);
    window.removeEventListener('pointerup', onPointerUp);
    window.removeEventListener('pointercancel', onPointerUp);

    if (!drag) return;
    const el = track.value;
    const { v, moved } = drag;
    drag = null;
    isDragging.value = false;

    // Tarikan dianggap geser, bukan klik kartu. Flag dibersihkan pada klik
    // pertama sesudahnya (plus batas waktu, kalau tarikan berakhir di luar kartu).
    if (moved > 8) {
        suppressClick = true;
        if (clickGuard) clearTimeout(clickGuard);
        clickGuard = setTimeout(() => (suppressClick = false), 300);
        if (Math.abs(v) > 0.35) nudge(v < 0 ? 1 : -1);
    }

    if (!el) return;
    // Lemparan (momentum) diperhitungkan sebelum menempel ke kartu terdekat
    snapTo(el.scrollLeft - v * 180, 520);
}

function onCardClick(t) {
    if (suppressClick) {
        suppressClick = false;
        return;
    }
    bukaDetail(t);
}

onMounted(() => {
    onScroll();
    window.addEventListener('resize', onScroll);
});
onUnmounted(() => {
    window.removeEventListener('resize', onScroll);
    window.removeEventListener('pointermove', onPointerMove);
    window.removeEventListener('pointerup', onPointerUp);
    window.removeEventListener('pointercancel', onPointerUp);
    if (slideTimeout) clearTimeout(slideTimeout);
    if (clickGuard) clearTimeout(clickGuard);
    if (rafId) cancelAnimationFrame(rafId);
});
</script>

<style scoped>
/* ── Viewport: fade di tepi supaya kartu terpotong terlihat disengaja ── */
.wc-tim__viewport {
    position: relative;
    margin-top: clamp(1.75rem, 4vw, 2.5rem);
    --fade: 3.5rem;
    -webkit-mask-image: linear-gradient(90deg, transparent 0, #000 var(--fade), #000 calc(100% - var(--fade)), transparent 100%);
    mask-image: linear-gradient(90deg, transparent 0, #000 var(--fade), #000 calc(100% - var(--fade)), transparent 100%);
    transition: -webkit-mask-image 0.25s ease, mask-image 0.25s ease;
}
.wc-tim__viewport.at-start {
    --fade: 0rem;
    -webkit-mask-image: linear-gradient(90deg, #000 calc(100% - 3.5rem), transparent 100%);
    mask-image: linear-gradient(90deg, #000 calc(100% - 3.5rem), transparent 100%);
}
.wc-tim__viewport.at-end {
    -webkit-mask-image: linear-gradient(90deg, transparent 0, #000 3.5rem);
    mask-image: linear-gradient(90deg, transparent 0, #000 3.5rem);
}
.wc-tim__viewport.at-start.at-end {
    -webkit-mask-image: none;
    mask-image: none;
}

/* ── Track ───────────────────────────────────────────────── */
.wc-tim__track {
    display: flex;
    gap: 1.1rem;
    padding: 0.6rem 0.25rem 1.4rem;
    overflow-x: auto;
    /* Snap diurus JS (animateTo/snapTo) supaya animasi geser tidak direbut browser.
       Di perangkat sentuh scroll-nya native, jadi snap bawaan dipakai kembali. */
    scroll-snap-type: none;
    scroll-padding-left: 0.25rem;
    scrollbar-width: none;
    overscroll-behavior-x: contain;
    cursor: grab;
}
.wc-tim__track::-webkit-scrollbar {
    display: none;
}
@media (hover: none) and (pointer: coarse) {
    .wc-tim__track {
        scroll-snap-type: x mandatory;
    }
    /* Tetap dimatikan sementara saat animasi JS berjalan (mis. tombol panah) */
    .wc-tim__track.is-free {
        scroll-snap-type: none;
    }
}
.wc-tim__track.is-dragging {
    cursor: grabbing;
    user-select: none;
}
/* Saat ditarik, kartu tidak ikut mengangkat & overlay tidak muncul */
.wc-tim__track.is-dragging .wc-tim__card {
    transform: none;
    cursor: grabbing;
}
.wc-tim__track.is-dragging .wc-tim__overlay {
    opacity: 0;
}
.wc-tim__track.is-dragging .wc-tim__body {
    opacity: 1;
    transform: none;
}

/* ── Kartu ───────────────────────────────────────────────── */
.wc-tim__card {
    position: relative;
    display: flex;
    flex: 0 0 clamp(17rem, 30vw, 22rem);
    align-items: flex-end;
    height: 24rem;
    overflow: hidden;
    border-radius: 1.35rem;
    background: #eef2ff;
    scroll-snap-align: start;
    cursor: pointer;
    user-select: none;
    -webkit-user-select: none;
    box-shadow: 0 14px 34px rgba(15, 23, 42, 0.09);
    transition: transform 0.35s cubic-bezier(0.22, 1, 0.36, 1), box-shadow 0.35s ease;
}
.wc-tim__card:hover {
    transform: translateY(-6px);
    box-shadow: 0 26px 52px rgba(79, 70, 229, 0.22);
}

.wc-tim__photo {
    position: absolute;
    inset: 0;
    background-image: url('/img/IMG_5417.JPG');
    background-size: cover;
    transition: transform 0.6s cubic-bezier(0.22, 1, 0.36, 1);
}
.wc-tim__card:hover .wc-tim__photo {
    transform: scale(1.07);
}
.wc-tim__scrim {
    position: absolute;
    inset: 0;
    background: linear-gradient(180deg, rgba(30, 27, 75, 0) 45%, rgba(30, 27, 75, 0.16) 75%, rgba(30, 27, 75, 0.34) 100%);
    transition: background 0.4s ease;
}

/* ── Badge jumlah lowongan ───────────────────────────────── */
.wc-tim__lowongan {
    position: absolute;
    top: 1rem;
    left: 1rem;
    z-index: 4;
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.35rem 0.75rem;
    border-radius: 999px;
    background: rgba(99, 102, 241, 0.92);
    border: 1px solid rgba(255, 255, 255, 0.28);
    -webkit-backdrop-filter: blur(6px);
    backdrop-filter: blur(6px);
    color: #fff;
    font-size: 0.7rem;
    font-weight: 800;
    letter-spacing: 0.01em;
    box-shadow: 0 8px 20px rgba(30, 27, 75, 0.28);
}
.wc-tim__lowongan.is-empty {
    background: rgba(30, 27, 75, 0.55);
    color: rgba(241, 245, 249, 0.88);
}
.wc-tim__lowongan i {
    font-size: 0.72rem;
}

/* ── Nama fungsi (state normal): panel sangat tipis agar foto tetap terlihat ── */
.wc-tim__body {
    position: relative;
    z-index: 2;
    width: 100%;
    padding: 1.15rem 1.35rem 1.3rem;
    background: linear-gradient(180deg, rgba(24, 22, 58, 0.06) 0%, rgba(24, 22, 58, 0.24) 100%);
    -webkit-backdrop-filter: blur(5px);
    backdrop-filter: blur(5px);
    border-top: 1px solid rgba(255, 255, 255, 0.12);
    transition: opacity 0.3s ease, transform 0.35s ease;
}
.wc-tim__body h3 {
    margin: 0;
    font-size: 1.05rem;
    font-weight: 800;
    letter-spacing: -0.01em;
    line-height: 1.3;
    color: #f1f5f9;
    text-shadow: 0 1px 3px rgba(15, 23, 42, 0.7), 0 2px 14px rgba(15, 23, 42, 0.55);
}
.wc-tim__card:hover .wc-tim__body,
.wc-tim__card:focus-within .wc-tim__body {
    opacity: 0;
    transform: translateY(12px);
}

/* ── Overlay saat hover: setinggi kartu, hanya deskripsi + tombol ── */
.wc-tim__overlay {
    position: absolute;
    inset: 0;
    z-index: 3;
    display: flex;
    flex-direction: column;
    justify-content: flex-end;
    padding: 1.6rem 1.45rem 1.6rem;
    background: linear-gradient(180deg, rgba(24, 22, 58, 0.62) 0%, rgba(30, 27, 75, 0.86) 100%);
    -webkit-backdrop-filter: blur(3px);
    backdrop-filter: blur(3px);
    opacity: 0;
    transition: opacity 0.35s ease;
}
.wc-tim__card:hover .wc-tim__overlay,
.wc-tim__card:focus-within .wc-tim__overlay {
    opacity: 1;
}
.wc-tim__overlay p {
    margin: 0;
    color: rgba(238, 242, 248, 0.95);
    font-size: 0.88rem;
    font-weight: 600;
    line-height: 1.65;
    transform: translateY(10px);
    transition: transform 0.4s cubic-bezier(0.22, 1, 0.36, 1) 0.04s;
}
.wc-tim__card:hover .wc-tim__overlay p,
.wc-tim__card:focus-within .wc-tim__overlay p {
    transform: translateY(0);
}
.wc-tim__btn {
    display: inline-flex;
    align-items: center;
    align-self: flex-start;
    gap: 0.45rem;
    margin-top: 1.1rem;
    transform: translateY(12px);
    padding: 0.5rem 1rem;
    border: 0;
    border-radius: 999px;
    background: rgba(241, 245, 249, 0.96);
    color: #312e81;
    font-size: 0.78rem;
    font-weight: 800;
    cursor: pointer;
    transition: background 0.22s ease, color 0.22s ease, box-shadow 0.22s ease,
        transform 0.4s cubic-bezier(0.22, 1, 0.36, 1) 0.08s;
}
.wc-tim__card:hover .wc-tim__btn,
.wc-tim__card:focus-within .wc-tim__btn {
    transform: translateY(0);
}
.wc-tim__btn i {
    transition: transform 0.22s ease;
}
.wc-tim__btn:hover {
    background: linear-gradient(135deg, #8b5cf6, #6366f1);
    color: #fff;
    box-shadow: 0 10px 24px rgba(99, 102, 241, 0.38);
}
.wc-tim__btn:hover i {
    transform: translateX(3px);
}

/* ── Animasi saat track digeser kanan / kiri ─────────────── */
.wc-tim__track.is-slide-next .wc-tim__card {
    animation: wcSlideNext 0.55s cubic-bezier(0.22, 1, 0.36, 1) both;
    animation-delay: calc(var(--i, 0) * 35ms);
}
.wc-tim__track.is-slide-prev .wc-tim__card {
    animation: wcSlidePrev 0.55s cubic-bezier(0.22, 1, 0.36, 1) both;
    animation-delay: calc(var(--i, 0) * 35ms);
}
@keyframes wcSlideNext {
    0% {
        transform: translateX(0) scale(1);
    }
    38% {
        transform: translateX(-14px) scale(0.965);
    }
    100% {
        transform: translateX(0) scale(1);
    }
}
@keyframes wcSlidePrev {
    0% {
        transform: translateX(0) scale(1);
    }
    38% {
        transform: translateX(14px) scale(0.965);
    }
    100% {
        transform: translateX(0) scale(1);
    }
}

/* ── Nav + progress ──────────────────────────────────────── */
.wc-tim__nav {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 1rem;
    margin-top: 0.4rem;
}
.wc-tim__bar {
    position: relative;
    width: min(16rem, 40vw);
    height: 3px;
    border-radius: 999px;
    background: rgba(203, 213, 225, 0.8);
    overflow: hidden;
}
.wc-tim__bar-fill {
    position: absolute;
    inset: 0;
    border-radius: inherit;
    background: linear-gradient(90deg, #8b5cf6, #6366f1);
    transform-origin: left;
    transition: transform 0.25s ease;
}
.wc-tim__nav-btn {
    display: grid;
    place-items: center;
    flex: none;
    width: 2.6rem;
    height: 2.6rem;
    border-radius: 50%;
    border: 1px solid rgba(203, 213, 225, 0.9);
    background: rgba(255, 255, 255, 0.9);
    color: var(--indigo);
    font-size: 1rem;
    cursor: pointer;
    transition: transform 0.18s ease, background 0.18s ease, color 0.18s ease, box-shadow 0.18s ease, opacity 0.18s ease;
}
.wc-tim__nav-btn:hover:not(:disabled) {
    background: linear-gradient(135deg, #8b5cf6, #6366f1);
    border-color: transparent;
    color: #fff;
    box-shadow: 0 12px 26px rgba(99, 102, 241, 0.32);
    transform: translateY(-2px);
}
.wc-tim__nav-btn:active:not(:disabled) {
    transform: scale(0.93);
}
.wc-tim__nav-btn:disabled {
    opacity: 0.35;
    cursor: default;
}

/* ── Tombol ke halaman semua tim ─────────────────────────── */
.wc-tim__more {
    display: flex;
    justify-content: center;
    margin-top: 1.6rem;
}
.wc-tim__more-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.55rem;
    padding: 0.8rem 1.6rem;
    border-radius: 999px;
    background: linear-gradient(135deg, #8b5cf6, #6366f1);
    color: #fff;
    font-size: 0.85rem;
    font-weight: 800;
    text-decoration: none;
    box-shadow: 0 14px 30px rgba(99, 102, 241, 0.3);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.wc-tim__more-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 20px 40px rgba(99, 102, 241, 0.4);
}
.wc-tim__more-btn i:last-child {
    transition: transform 0.2s ease;
}
.wc-tim__more-btn:hover i:last-child {
    transform: translateX(4px);
}

@media (max-width: 640px) {
    .wc-tim__card {
        flex-basis: 16.5rem;
        height: 22rem;
    }
    .wc-tim__viewport {
        --fade: 1.5rem;
    }
}
@media (prefers-reduced-motion: reduce) {
    .wc-tim__card,
    .wc-tim__photo,
    .wc-tim__body,
    .wc-tim__overlay,
    .wc-tim__overlay p,
    .wc-tim__btn,
    .wc-tim__bar-fill {
        transition: none;
    }
    .wc-tim__track.is-slide-next .wc-tim__card,
    .wc-tim__track.is-slide-prev .wc-tim__card {
        animation: none;
    }
}
</style>
