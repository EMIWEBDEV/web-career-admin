<!-- ══════════════════════════════════════════════════════════
     WEB CAREER — Halaman Perkenalan Tim  (route: /karir/tim/{slug})
     Contoh isi: tim Information Technology.
     CATATAN: seluruh teks & gambar ditulis langsung di file ini
     (tanpa props/array JS) supaya mudah disesuaikan manual.
     Ganti `url('/img/IMG_5417.JPG')` pada blok .dt-shot--* di CSS
     dengan foto asli tiap momen tim.
     ══════════════════════════════════════════════════════════ -->
<template>
    <Head>
        <title>Tim Information Technology - EVO Group Career</title>
    </Head>

    <CareerLayout :has-mt="hasMt" :offices="offices">
        <div class="dt">
            <button type="button" class="wc-back" @click="goToSection('tim')">
                <i class="bi bi-arrow-left"></i> Kembali ke Fungsi Perusahaan
            </button>

            <!-- ── HERO ──────────────────────────────────────────── -->
            <header class="dt-hero wc-reveal">
                <div class="dt-hero__photo"></div>
                <div class="dt-hero__scrim"></div>
                <div class="dt-hero__body">
                    <span class="dt-hero__eyebrow"><span class="dt-dot"></span> Fungsi Perusahaan</span>
                    <h1>Information Technology</h1>
                    <p>
                        Tim yang menjaga agar seluruh sistem, data, dan alat kerja di Evo berjalan lancar —
                        dari lantai produksi sampai aplikasi yang dipakai tim lapangan setiap hari.
                    </p>
                    <div class="dt-hero__stats">
                        <div><strong>12</strong><small>Anggota tim</small></div>
                        <span class="dt-hero__sep"></span>
                        <div><strong>4</strong><small>Sub-fungsi</small></div>
                        <span class="dt-hero__sep"></span>
                        <div><strong>3</strong><small>Lowongan terbuka</small></div>
                    </div>
                </div>
            </header>

            <!-- ── TENTANG TIM ───────────────────────────────────── -->
            <section class="dt-section">
                <div class="dt-about">
                    <div class="dt-about__text wc-reveal">
                        <h2>Bukan sekadar tim pendukung, tapi rekan berpikir.</h2>
                        <p>
                            Tim IT Evo terlibat sejak awal ketika sebuah proses ingin diperbaiki. Kami duduk bersama tim
                            produksi, gudang, dan penjualan untuk memahami masalahnya dulu, baru menentukan solusi
                            teknologinya — bukan sebaliknya.
                        </p>
                        <p>
                            Sehari-hari kami mengurus sistem internal, integrasi data antar cabang, perangkat di pabrik,
                            sampai aplikasi rekrutmen yang sedang kamu buka sekarang.
                        </p>
                        <ul class="dt-points">
                            <li><i class="bi bi-check-circle-fill"></i> Membangun & merawat aplikasi internal perusahaan</li>
                            <li><i class="bi bi-check-circle-fill"></i> Menjaga infrastruktur, jaringan, dan keamanan data</li>
                            <li><i class="bi bi-check-circle-fill"></i> Mendampingi tim lain mengadopsi alat kerja baru</li>
                        </ul>
                    </div>

                    <div class="dt-about__media wc-reveal" style="--d: 90ms">
                        <div class="dt-shot dt-shot--a"></div>
                        <div class="dt-shot dt-shot--b"></div>
                        <div class="dt-shot dt-shot--c"></div>
                    </div>
                </div>
            </section>

                     <!-- ── LOWONGAN DI TIM INI ───────────────────────────── -->
            <section class="dt-section">
                <div class="dt-head dt-head--row wc-reveal">
                    <div>
                        <h2>Lowongan di tim ini</h2>
                        <p>Posisi yang sedang kami cari untuk memperkuat tim Information Technology.</p>
                    </div>
                    <span class="dt-count"><i class="bi bi-briefcase-fill"></i> {{ lowonganTim.length }} lowongan terbuka</span>
                </div>

                <div v-if="lowonganTim.length" class="rek__grid wc-reveal" style="--d: 70ms">
                    <LowonganCard v-for="job in lowonganTim" :key="job.id" :job="job" />
                </div>
                <div v-else class="dt-empty wc-reveal">
                    <i class="bi bi-clipboard-x"></i>
                    Belum ada lowongan terbuka di tim ini. Pantau terus halaman karir kami.
                </div>
            </section>


        



            <!-- ── CTA ───────────────────────────────────────────── -->
            <section class="dt-section">
                <div class="dt-cta wc-reveal">
                    <div class="dt-cta__pattern" aria-hidden="true"></div>
                    <h2>Tertarik bergabung dengan tim ini?</h2>
                    <p>Lihat posisi yang sedang kami buka, atau simpan halaman ini untuk nanti.</p>
                    <div class="dt-cta__act">
                        <Link href="/karir/lowongan" class="dt-btn dt-btn--solid">
                            <i class="bi bi-search"></i> Lihat lowongan
                        </Link>
                        <button type="button" class="dt-btn dt-btn--ghost" @click="goToSection('tim')">
                            <i class="bi bi-grid"></i> Fungsi lainnya
                        </button>
                    </div>
                </div>
            </section>
        </div>
    </CareerLayout>
</template>

<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, onUnmounted } from 'vue';
import CareerLayout from './Layouts/CareerLayout.vue';
import LowonganCard from './components/LowonganCard.vue';
import { goToSection, observeReveal } from './careerData';

defineOptions({ layout: null });

// CONTOH ISI — sesuaikan/ganti dengan lowongan asli tim ini.
// Bentuk objeknya mengikuti kartu lowongan di halaman "Semua Lowongan".
const lowonganTim = [
    {
        id: 'RC-2026-011',
        posisi: 'Backend Developer',
        tipeKerja: 'Full-time',
        unggulan: true,
        benefit: ['Gaji + THR', 'BPJS', 'Laptop kerja', 'Pelatihan'],
        ringkasan: 'Membangun dan merawat layanan internal perusahaan, mulai dari sistem rekrutmen sampai integrasi data antar cabang.',
        lokasi: 'Palembang',
        tempatKerja: 'On-site (WFO)',
        pengalaman: 'Min. 1 - 2 Tahun',
        skill: ['Laravel', 'MySQL', 'REST API', 'Git'],
        kuota: 2,
        kuotaTerisi: 0,
        pelamar: 8,
    },
    {
        id: 'RC-2026-012',
        posisi: 'Frontend Developer',
        tipeKerja: 'Full-time',
        unggulan: false,
        benefit: ['Gaji + THR', 'BPJS', 'Laptop kerja'],
        ringkasan: 'Menerjemahkan kebutuhan tim lain menjadi antarmuka yang rapi, cepat, dan nyaman dipakai setiap hari.',
        lokasi: 'Palembang',
        tempatKerja: 'On-site (WFO)',
        pengalaman: 'Min. 1 Tahun',
        skill: ['Vue.js', 'Inertia', 'CSS', 'Figma'],
        kuota: 1,
        kuotaTerisi: 0,
        pelamar: 12,
    },
    {
        id: 'RC-2026-013',
        posisi: 'IT Support',
        tipeKerja: 'Contract',
        unggulan: false,
        benefit: ['Gaji + THR', 'BPJS'],
        ringkasan: 'Menangani kendala teknis harian di kantor dan pabrik, serta merawat perangkat kerja seluruh karyawan.',
        lokasi: 'Palembang',
        tempatKerja: 'On-site (WFO)',
        pengalaman: 'Fresh Graduate',
        skill: ['Troubleshooting', 'Jaringan', 'Hardware'],
        kuota: 2,
        kuotaTerisi: 2,
        pelamar: 21,
    },
];

// Hanya payload layout (navbar + footer). Konten halaman ditulis langsung di template.
const props = defineProps({
    hasMt: { type: Boolean, default: false },
    offices: { type: Array, default: () => [] },
});

const hasMt = computed(() => props.hasMt);
const offices = computed(() => props.offices || []);

let revealObs = null;
onMounted(() => nextTick(() => (revealObs = observeReveal())));
onUnmounted(() => revealObs?.disconnect());
</script>

<style scoped>
.dt {
    max-width: 1140px;
    margin: 0 auto;
    padding: 1.5rem 1.25rem 4rem;
}
.dt-section {
    margin-top: clamp(3rem, 7vw, 4.75rem);
}

/* ── Judul kecil & heading ───────────────────────────────── */
.dt-label {
    display: inline-block;
    margin-bottom: 0.6rem;
    font-size: 0.72rem;
    font-weight: 800;
    letter-spacing: 0.18em;
    text-transform: uppercase;
    color: var(--indigo);
}
.dt-head {
    max-width: 44rem;
    margin-bottom: 1.9rem;
}
.dt h2 {
    margin: 0 0 0.6rem;
    font-size: clamp(1.5rem, 3vw, 2.05rem);
    font-weight: 900;
    letter-spacing: -0.025em;
    line-height: 1.25;
    color: var(--ink);
}
.dt-head p {
    margin: 0;
    color: var(--slate);
    font-size: 0.94rem;
    font-weight: 600;
    line-height: 1.65;
}

/* ── Hero ────────────────────────────────────────────────── */
.dt-hero {
    position: relative;
    display: flex;
    align-items: flex-end;
    min-height: clamp(22rem, 42vw, 28rem);
    margin-top: 1rem;
    overflow: hidden;
    border-radius: 1.75rem;
    box-shadow: 0 26px 58px rgba(79, 70, 229, 0.22);
}
.dt-hero__photo {
    position: absolute;
    inset: 0;
    /* PLACEHOLDER — ganti dengan foto tim IT */
    background: url('/img/IMG_5417.JPG') center/cover;
    transform: scale(1.02);
}
.dt-hero__scrim {
    position: absolute;
    inset: 0;
    background: linear-gradient(180deg, rgba(24, 22, 58, 0.15) 0%, rgba(24, 22, 58, 0.62) 55%, rgba(24, 22, 58, 0.9) 100%);
}
.dt-hero__body {
    position: relative;
    z-index: 1;
    padding: clamp(1.6rem, 4vw, 2.6rem);
    color: #f1f5f9;
}
.dt-hero__eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.4rem 0.9rem;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.16);
    border: 1px solid rgba(255, 255, 255, 0.24);
    -webkit-backdrop-filter: blur(6px);
    backdrop-filter: blur(6px);
    font-size: 0.7rem;
    font-weight: 800;
    letter-spacing: 0.14em;
    text-transform: uppercase;
}
.dt-dot {
    width: 0.42rem;
    height: 0.42rem;
    border-radius: 50%;
    background: #a78bfa;
}
.dt-hero h1 {
    margin: 1rem 0 0.7rem;
    font-size: clamp(1.9rem, 5vw, 3rem);
    font-weight: 900;
    letter-spacing: -0.03em;
    line-height: 1.1;
    color: #fff;
    text-shadow: 0 2px 16px rgba(15, 23, 42, 0.4);
}
.dt-hero__body > p {
    max-width: 40rem;
    margin: 0;
    color: rgba(238, 242, 248, 0.92);
    font-size: 0.95rem;
    font-weight: 600;
    line-height: 1.7;
    text-shadow: 0 1px 10px rgba(15, 23, 42, 0.45);
}
.dt-hero__stats {
    display: flex;
    align-items: center;
    gap: 1.3rem;
    margin-top: 1.6rem;
    padding-top: 1.2rem;
    border-top: 1px solid rgba(255, 255, 255, 0.22);
}
.dt-hero__stats strong {
    display: block;
    font-size: 1.5rem;
    font-weight: 900;
    line-height: 1.1;
    color: #fff;
}
.dt-hero__stats small {
    font-size: 0.74rem;
    font-weight: 700;
    color: rgba(255, 255, 255, 0.78);
}
.dt-hero__sep {
    width: 1px;
    height: 2.1rem;
    background: rgba(255, 255, 255, 0.24);
}

/* ── Tentang tim ─────────────────────────────────────────── */
.dt-about {
    display: grid;
    grid-template-columns: minmax(0, 1.05fr) minmax(0, 0.95fr);
    gap: clamp(1.5rem, 4vw, 2.75rem);
    align-items: center;
}
.dt-about__text p {
    margin: 0 0 0.9rem;
    color: var(--slate);
    font-size: 0.92rem;
    font-weight: 600;
    line-height: 1.75;
}
.dt-points {
    margin: 1.3rem 0 0;
    padding: 0;
    list-style: none;
}
.dt-points li {
    display: flex;
    align-items: flex-start;
    gap: 0.65rem;
    padding: 0.5rem 0;
    color: var(--ink);
    font-size: 0.89rem;
    font-weight: 700;
}
.dt-points i {
    margin-top: 0.15rem;
    color: var(--indigo);
}

/* Kolase 3 foto */
.dt-about__media {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    grid-template-rows: repeat(2, 9.5rem);
    gap: 0.9rem;
}
.dt-about__media .dt-shot:first-child {
    grid-row: span 2;
}

/* ── Kotak foto placeholder ──────────────────────────────── */
.dt-shot {
    position: relative;
    height: 100%;
    min-height: 8rem;
    overflow: hidden;
    border-radius: 1.1rem;
    /* PLACEHOLDER — ganti tiap varian dengan foto momen yang sesuai */
    background-image: url('/img/IMG_5417.JPG');
    background-size: cover;
    box-shadow: 0 14px 32px rgba(15, 23, 42, 0.12);
    transition: transform 0.4s cubic-bezier(0.22, 1, 0.36, 1);
}
.dt-shot:hover {
    transform: translateY(-4px);
}
.dt-shot--a { background-position: 40% center; }
.dt-shot--b { background-position: 65% 30%; }
.dt-shot--c { background-position: 25% 70%; }
.dt-shot--d { background-position: 50% 40%; }
.dt-shot--e { background-position: 20% center; }
.dt-shot--f { background-position: 80% center; }
.dt-shot--g { background-position: 55% 65%; }
.dt-shot--h { background-position: 35% 45%; }

/* ── Kartu ruang lingkup ─────────────────────────────────── */
.dt-cards {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 1rem;
}
.dt-card {
    position: relative;
    overflow: hidden;
    padding: 1.5rem 1.35rem;
    border-radius: 1.25rem;
    background: rgba(255, 255, 255, 0.9);
    border: 1px solid rgba(226, 232, 240, 0.9);
    box-shadow: 0 12px 30px rgba(15, 23, 42, 0.05);
    transition: transform 0.25s ease, box-shadow 0.25s ease, border-color 0.25s ease;
}
.dt-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 3px;
    background: linear-gradient(90deg, #8b5cf6, #6366f1);
    transform: scaleX(0);
    transform-origin: left;
    transition: transform 0.3s ease;
}
.dt-card:hover {
    transform: translateY(-5px);
    border-color: rgba(139, 92, 246, 0.35);
    box-shadow: 0 22px 44px rgba(79, 70, 229, 0.14);
}
.dt-card:hover::before {
    transform: scaleX(1);
}
.dt-card__ic {
    display: grid;
    place-items: center;
    width: 2.7rem;
    height: 2.7rem;
    margin-bottom: 0.95rem;
    border-radius: 0.85rem;
    background: linear-gradient(135deg, rgba(139, 92, 246, 0.14), rgba(99, 102, 241, 0.14));
    color: var(--indigo);
    font-size: 1.2rem;
    transition: background 0.25s ease, color 0.25s ease;
}
.dt-card:hover .dt-card__ic {
    background: linear-gradient(135deg, #8b5cf6, #6366f1);
    color: #fff;
}
.dt-card h3 {
    margin: 0 0 0.35rem;
    font-size: 0.98rem;
    font-weight: 800;
    letter-spacing: -0.01em;
    color: var(--ink);
}
.dt-card p {
    margin: 0;
    color: var(--slate);
    font-size: 0.84rem;
    font-weight: 600;
    line-height: 1.6;
}

/* ── Galeri keseruan ─────────────────────────────────────── */
.dt-gallery {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 1rem;
}
.dt-gal {
    position: relative;
    margin: 0;
    overflow: hidden;
    border-radius: 1.25rem;
    height: 15rem;
}
.dt-gal--wide {
    grid-column: span 2;
}
.dt-gal .dt-shot {
    border-radius: 1.25rem;
    box-shadow: none;
}
.dt-gal:hover .dt-shot {
    transform: scale(1.06);
}
.dt-gal figcaption {
    position: absolute;
    right: 0;
    bottom: 0;
    left: 0;
    padding: 1.5rem 1.2rem 1.1rem;
    background: linear-gradient(180deg, rgba(24, 22, 58, 0) 0%, rgba(24, 22, 58, 0.78) 100%);
    color: #f1f5f9;
}
.dt-gal figcaption strong {
    display: block;
    font-size: 0.92rem;
    font-weight: 800;
    text-shadow: 0 1px 8px rgba(15, 23, 42, 0.5);
}
.dt-gal figcaption span {
    font-size: 0.78rem;
    font-weight: 600;
    color: rgba(226, 232, 240, 0.88);
    text-shadow: 0 1px 8px rgba(15, 23, 42, 0.5);
}

/* ── Cara kami bekerja ───────────────────────────────────── */
.dt-work {
    display: grid;
    grid-template-columns: minmax(0, 0.9fr) minmax(0, 1.1fr);
    gap: clamp(1.5rem, 4vw, 2.75rem);
    align-items: center;
}
.dt-work__media .dt-shot {
    min-height: 24rem;
    border-radius: 1.5rem;
}
.dt-steps {
    margin-top: 1.4rem;
}
.dt-step {
    display: flex;
    gap: 1rem;
    padding: 1.15rem 0;
    border-bottom: 1px solid rgba(226, 232, 240, 0.9);
}
.dt-step:last-child {
    border-bottom: none;
}
.dt-step__no {
    flex: none;
    font-size: 0.78rem;
    font-weight: 900;
    color: var(--indigo);
}
.dt-step strong {
    display: block;
    margin-bottom: 0.25rem;
    font-size: 0.98rem;
    font-weight: 800;
    color: var(--ink);
}
.dt-step p {
    margin: 0;
    color: var(--slate);
    font-size: 0.86rem;
    font-weight: 600;
    line-height: 1.65;
}

/* ── Kutipan ─────────────────────────────────────────────── */
.dt-quote {
    position: relative;
    margin: 0;
    padding: clamp(1.8rem, 4vw, 2.6rem);
    border-radius: 1.5rem;
    background: linear-gradient(150deg, #6d5bd0 0%, #6366f1 100%);
    color: #fff;
    box-shadow: 0 24px 50px rgba(79, 70, 229, 0.28);
}
.dt-quote > i {
    font-size: 2.6rem;
    line-height: 1;
    color: rgba(255, 255, 255, 0.45);
}
.dt-quote p {
    max-width: 46rem;
    margin: 0.4rem 0 1.5rem;
    font-size: clamp(1.05rem, 2.2vw, 1.3rem);
    font-weight: 700;
    line-height: 1.6;
    letter-spacing: -0.01em;
}
.dt-quote footer {
    display: flex;
    align-items: center;
    gap: 0.85rem;
}
.dt-quote__ava {
    width: 2.9rem;
    height: 2.9rem;
    flex: none;
    border-radius: 50%;
    /* PLACEHOLDER — ganti dengan foto anggota tim */
    background: url('/img/IMG_5417.JPG') 45% 25% / cover;
    border: 2px solid rgba(255, 255, 255, 0.6);
}
.dt-quote footer strong {
    display: block;
    font-size: 0.92rem;
    font-weight: 800;
}
.dt-quote footer small {
    font-size: 0.78rem;
    font-weight: 600;
    color: rgba(255, 255, 255, 0.78);
}

/* ── Daftar lowongan tim ─────────────────────────────────── */
.dt-head--row {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 1rem;
    max-width: none;
}
.dt-head--row p {
    margin: 0;
}
.dt-count {
    flex: none;
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.5rem 1rem;
    border-radius: 999px;
    background: rgba(99, 102, 241, 0.1);
    color: var(--indigo);
    font-size: 0.78rem;
    font-weight: 800;
}
.rek__grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 1.05rem;
}
.dt-empty {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.6rem;
    padding: 2.75rem 1rem;
    border-radius: 1.25rem;
    background: rgba(255, 255, 255, 0.85);
    border: 1px dashed rgba(203, 213, 225, 0.9);
    color: var(--muted);
    font-size: 0.88rem;
    font-weight: 600;
}

/* ── Tag alat ────────────────────────────────────────────── */
.dt-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 0.6rem;
}
.dt-tags span {
    padding: 0.55rem 1.05rem;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.9);
    border: 1px solid rgba(226, 232, 240, 0.9);
    color: var(--ink);
    font-size: 0.82rem;
    font-weight: 700;
    transition: transform 0.2s ease, border-color 0.2s ease, color 0.2s ease;
}
.dt-tags span:hover {
    transform: translateY(-2px);
    border-color: rgba(139, 92, 246, 0.4);
    color: var(--indigo);
}

/* ── CTA ─────────────────────────────────────────────────── */
.dt-cta {
    position: relative;
    overflow: hidden;
    padding: clamp(2rem, 5vw, 3rem);
    border-radius: 1.5rem;
    background: linear-gradient(140deg, #1e1b4b 0%, #4338ca 100%);
    color: #fff;
    text-align: center;
    box-shadow: 0 24px 50px rgba(30, 27, 75, 0.3);
}
.dt-cta__pattern {
    position: absolute;
    inset: 0;
    background: radial-gradient(circle at 15% 20%, rgba(167, 139, 250, 0.35), transparent 45%),
        radial-gradient(circle at 85% 80%, rgba(99, 102, 241, 0.4), transparent 45%);
}
.dt-cta h2,
.dt-cta p,
.dt-cta__act {
    position: relative;
    z-index: 1;
}
.dt-cta h2 {
    color: #fff;
}
.dt-cta p {
    max-width: 34rem;
    margin: 0 auto;
    color: rgba(226, 232, 240, 0.88);
    font-size: 0.92rem;
    font-weight: 600;
    line-height: 1.7;
}
.dt-cta__act {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 0.75rem;
    margin-top: 1.6rem;
}
.dt-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1.5rem;
    border: 0;
    border-radius: 999px;
    font-size: 0.86rem;
    font-weight: 800;
    text-decoration: none;
    cursor: pointer;
    transition: transform 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
}
.dt-btn--solid {
    background: #fff;
    color: #312e81;
}
.dt-btn--solid:hover {
    transform: translateY(-2px);
    box-shadow: 0 14px 30px rgba(15, 23, 42, 0.28);
}
.dt-btn--ghost {
    background: rgba(255, 255, 255, 0.14);
    border: 1px solid rgba(255, 255, 255, 0.32);
    color: #fff;
}
.dt-btn--ghost:hover {
    background: rgba(255, 255, 255, 0.24);
    transform: translateY(-2px);
}

/* ── Responsif ───────────────────────────────────────────── */
@media (max-width: 980px) {
    .dt-about,
    .dt-work {
        grid-template-columns: 1fr;
    }
    .dt-work__media {
        order: -1;
    }
    .dt-work__media .dt-shot {
        min-height: 16rem;
    }
    .dt-cards {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
    .dt-gallery {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
    .dt-gal--wide {
        grid-column: span 2;
    }
}
@media (max-width: 700px) {
    .dt-head--row {
        flex-direction: column;
        align-items: flex-start;
    }
}
@media (max-width: 600px) {
    .dt-cards,
    .dt-gallery {
        grid-template-columns: 1fr;
    }
    .dt-gal--wide {
        grid-column: span 1;
    }
    .dt-about__media {
        grid-template-rows: repeat(2, 8rem);
    }
    .dt-hero__stats {
        gap: 0.9rem;
    }
}
@media (prefers-reduced-motion: reduce) {
    .dt-shot,
    .dt-card,
    .dt-btn,
    .dt-tags span {
        transition: none;
    }
}
</style>
