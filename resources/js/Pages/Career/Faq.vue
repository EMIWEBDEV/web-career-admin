<!-- ══════════════════════════════════════════════════════════
     WEB CAREER — FAQ Kandidat (route: /karir/faq)
     Tujuan "baca selengkapnya" dari accordion landing. Seluruh
     pertanyaan aktif dikelompokkan per kategori (Master FAQ), plus
     pencarian, deep link per pertanyaan (#slug), dan penilaian
     "membantu" yang jadi bahan HR memperbaiki jawaban.
     Data: FaqPublikController::index() → App\Support\Career\FaqPublik::semua()
     ══════════════════════════════════════════════════════════ -->
<template>
    <Head>
        <title>FAQ Kandidat - EVO Group Career</title>
        <meta
            name="description"
            content="Pertanyaan yang sering diajukan kandidat tentang pendaftaran, seleksi, program Management Trainee, dan penempatan kerja di EVO Group."
        />
    </Head>

    <CareerLayout :has-mt="hasMt" :offices="offices">
        <div class="fq">
            <button type="button" class="wc-back" @click="goToSection('faq')">
                <i class="bi bi-arrow-left"></i> Kembali ke Beranda Karir
            </button>

            <!-- ── Hero ──────────────────────────────────────────── -->
            <header class="fq-hero wc-reveal">
                <span class="wc-eyebrow"><span class="wc-dot"></span> FAQ Candidates</span>
                <h1>Pertanyaan yang sering <span class="wc-grad">diajukan.</span></h1>
                <p>
                    Semua yang perlu Kamu ketahui tentang melamar, tahapan seleksi, dan bekerja di EVO Group —
                    dikumpulkan di satu halaman.
                </p>
                <div v-if="totalFaq" class="fq-hero__stat">
                    <span><b>{{ totalFaq }}</b> pertanyaan</span>
                    <span class="fq-hero__sep"></span>
                    <span><b>{{ kategori.length }}</b> kategori</span>
                </div>
            </header>

            <!-- ── Belum ada data ────────────────────────────────── -->
            <div v-if="!totalFaq" class="fq-empty">
                <i class="bi bi-patch-question"></i>
                <strong>Daftar pertanyaan sedang disiapkan</strong>
                <span>
                    Tim rekrutmen sedang menyusun jawaban untuk pertanyaan yang paling sering diajukan. Sementara itu,
                    lihat lowongan yang tersedia.
                </span>
                <Link href="/karir/lowongan" class="fq-empty__btn">Lihat semua lowongan</Link>
            </div>

            <template v-else>
                <!-- ── Pencarian ─────────────────────────────────── -->
                <div class="fq-toolbar wc-reveal">
                    <div class="fq-search">
                        <i class="bi bi-search"></i>
                        <input
                            v-model="search"
                            type="text"
                            placeholder="Cari pertanyaan, mis. “biaya”, “lokasi”, “Form 2”…"
                            aria-label="Cari pertanyaan"
                        />
                        <button
                            v-if="search"
                            type="button"
                            class="fq-search__clear"
                            aria-label="Hapus pencarian"
                            @click="search = ''"
                        >
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                </div>

                <!-- ── Chip kategori (menggulir ke section, bukan memfilter) ── -->
                <nav v-if="!search" class="fq-chips wc-reveal" aria-label="Kategori pertanyaan">
                    <button
                        v-for="k in kategori"
                        :key="k.id"
                        type="button"
                        class="fq-chip"
                        @click="keSection(k.id)"
                    >
                        <i class="bi" :class="k.ikon"></i>
                        {{ k.nama }}
                        <span class="fq-chip__n">{{ k.jumlah }}</span>
                    </button>
                </nav>

                <p v-if="search" class="fq-result">
                    Menampilkan <b>{{ faqTersaring.length }}</b> dari {{ totalFaq }} pertanyaan untuk
                    "<b>{{ search }}</b>"
                </p>

                <!-- ── Hasil pencarian: satu daftar datar ────────── -->
                <section v-if="search" class="fq-section">
                    <div v-if="!faqTersaring.length" class="fq-nihil">
                        <i class="bi bi-search"></i>
                        <strong>Tidak ada pertanyaan yang cocok</strong>
                        <span>Coba kata kunci lain, atau hubungi tim rekrutmen lewat tombol di bawah.</span>
                    </div>
                    <FaqAccordion
                        v-else
                        :items="faqTersaring"
                        show-detail
                        :initial-open="slugAwal"
                        @open="saatDibuka"
                    >
                        <template #aksi="{ item }">
                            <FaqAksi
                                :item="item"
                                :status="statusVote[item.id]"
                                :tersalin="tersalin === item.slug"
                                @vote="kirimVote"
                                @salin="salinTautan"
                            />
                        </template>
                    </FaqAccordion>
                </section>

                <!-- ── Section per kategori ──────────────────────── -->
                <template v-else>
                    <section
                        v-for="k in kategori"
                        :id="'kategori-' + k.id"
                        :key="k.id"
                        class="fq-section wc-reveal"
                    >
                        <div class="fq-section__head">
                            <span class="fq-section__icon"><i class="bi" :class="k.ikon"></i></span>
                            <div>
                                <h2>{{ k.nama }}</h2>
                                <p v-if="k.deskripsi">{{ k.deskripsi }}</p>
                            </div>
                        </div>

                        <FaqAccordion
                            :items="perKategori(k.id)"
                            show-detail
                            :initial-open="slugAwal"
                            @open="saatDibuka"
                        >
                            <template #aksi="{ item }">
                                <FaqAksi
                                    :item="item"
                                    :status="statusVote[item.id]"
                                    :tersalin="tersalin === item.slug"
                                    @vote="kirimVote"
                                    @salin="salinTautan"
                                />
                            </template>
                        </FaqAccordion>
                    </section>
                </template>

                <!-- ── CTA penutup ───────────────────────────────── -->
                <section class="fq-cta wc-reveal">
                    <div class="fq-cta__inner">
                        <span class="fq-cta__icon"><i class="bi bi-chat-dots-fill"></i></span>
                        <h2>Masih ada pertanyaan?</h2>
                        <p>
                            Kalau jawabannya belum Kamu temukan di sini, hubungi tim rekrutmen kami. Kami akan
                            membalas pada hari kerja.
                        </p>
                        <div class="fq-cta__actions">
                            <a class="fq-cta__btn fq-cta__btn--primary" :href="'mailto:' + EMAIL_HR">
                                <i class="bi bi-envelope-fill"></i> Hubungi Tim Rekrutmen
                            </a>
                            <Link class="fq-cta__btn" href="/karir/lowongan">
                                <i class="bi bi-briefcase-fill"></i> Lihat Lowongan
                            </Link>
                        </div>
                        <small class="fq-cta__note">
                            <i class="bi bi-shield-check"></i>
                            Seluruh proses rekrutmen EVO Group <b>tidak memungut biaya apa pun</b>.
                        </small>
                    </div>
                </section>
            </template>
        </div>
    </CareerLayout>
</template>

<script setup>
import { Head, Link } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, nextTick, onMounted, onUnmounted, ref } from 'vue';
import CareerLayout from './Layouts/CareerLayout.vue';
import FaqAccordion from './components/FaqAccordion.vue';
import FaqAksi from './components/FaqAksi.vue';
import { goToSection, observeReveal, scrollToId } from './careerData';

defineOptions({ layout: null });

const EMAIL_HR = 'recruitment@evonusabersaudara.co.id';

const props = defineProps({
    hasMt: { type: Boolean, default: false },
    offices: { type: Array, default: () => [] },
    // Master FAQ: kategori aktif yang punya isi + seluruh pertanyaan aktif.
    kategori: { type: Array, default: () => [] },
    faq: { type: Array, default: () => [] },
});

const hasMt = computed(() => props.hasMt);
const offices = computed(() => props.offices || []);
const kategori = computed(() => props.kategori || []);
const faq = computed(() => props.faq || []);
const totalFaq = computed(() => faq.value.length);

const search = ref('');

// ── Pencarian ───────────────────────────────────────────────
// Menjangkau pertanyaan, jawaban ringkas, DAN teks polos jawaban detail
// (dikirim server sebagai `cari`) — kalau hanya pertanyaan yang dicocokkan,
// kandidat yang mengetik istilah dari isi jawaban tidak menemukan apa pun.
const faqTersaring = computed(() => {
    const q = search.value.trim().toLowerCase();
    if (!q) return faq.value;

    return faq.value.filter((f) =>
        [f.pertanyaan, f.jawaban, f.cari].filter(Boolean).join(' ').toLowerCase().includes(q),
    );
});

const perKategori = (id) => faq.value.filter((f) => f.kategoriId === id);

function keSection(id) {
    scrollToId('kategori-' + id);
}

// ── Deep link ───────────────────────────────────────────────
// /karir/faq#<slug> harus langsung membuka pertanyaan itu — tautan seperti ini
// yang dibagikan HR ke kandidat lewat WA/email.
const slugAwal = ref('');

function bacaHash() {
    const hash = decodeURIComponent((window.location.hash || '').replace(/^#/, ''));
    if (!hash) return;

    const target = faq.value.find((f) => f.slug === hash);
    if (!target) return;

    slugAwal.value = hash;
    // Tunggu accordion selesai membuka supaya posisi gulirnya tepat.
    nextTick(() => setTimeout(() => scrollToId('faq-' + hash), 220));
}

// ── Penghitung dilihat ──────────────────────────────────────
const sudahDicatat = new Set();

function saatDibuka(slug) {
    const item = faq.value.find((f) => f.slug === slug);
    if (!item || sudahDicatat.has(slug)) return;
    sudahDicatat.add(slug);

    // Alamat ikut diperbarui supaya pembaca bisa langsung menyalin dari address bar.
    try {
        window.history.replaceState(null, '', '#' + slug);
    } catch (e) {
        /* history dibatasi (mis. iframe) — tidak penting */
    }

    // Statistik tidak boleh mengganggu pembaca: kegagalan diabaikan diam-diam.
    axios.post(`/api/v1/karir/faq/${item.id}/dilihat`).catch(() => {});
}

// ── Penilaian "membantu" ────────────────────────────────────
// statusVote[id] = 'mengirim' | 'selesai' | 'gagal'
const statusVote = ref({});

// Slug yang tautannya baru saja disalin (label tombol berubah sebentar).
const tersalin = ref('');

async function kirimVote({ id, membantu }) {
    if (statusVote.value[id]) return;
    statusVote.value = { ...statusVote.value, [id]: 'mengirim' };

    try {
        await axios.post(`/api/v1/karir/faq/${id}/membantu`, { membantu });
        statusVote.value = { ...statusVote.value, [id]: 'selesai' };
    } catch (e) {
        // 409 = sudah menilai di sesi ini; bagi pembaca hasilnya sama saja.
        statusVote.value = { ...statusVote.value, [id]: e?.response?.status === 409 ? 'selesai' : 'gagal' };
    }
}

async function salinTautan(slug) {
    const url = `${window.location.origin}/karir/faq#${slug}`;
    try {
        await navigator.clipboard.writeText(url);
        tersalin.value = slug;
        setTimeout(() => (tersalin.value = ''), 1800);
    } catch (e) {
        // Clipboard diblokir (http / izin ditolak) — biarkan alamat di address bar
        // yang jadi jalan salin manual.
        window.history.replaceState(null, '', '#' + slug);
    }
}

let revealObserver = null;
onMounted(() => {
    nextTick(() => {
        revealObserver = observeReveal();
        bacaHash();
    });
});
onUnmounted(() => revealObserver?.disconnect());
</script>

<style scoped>
.fq {
    width: min(920px, calc(100vw - 2rem));
    margin: 0 auto;
    padding: clamp(1.5rem, 4vw, 2.5rem) 0 clamp(3rem, 6vw, 4.5rem);
}

/* ── Hero ── */
.fq-hero {
    text-align: center;
    margin: clamp(1rem, 3vw, 2rem) auto clamp(1.75rem, 4vw, 2.5rem);
}
.fq-hero h1 {
    margin: 0.85rem 0 0.75rem;
    font-size: clamp(1.75rem, 4.5vw, 2.6rem);
    font-weight: 900;
    letter-spacing: -0.02em;
    color: #1e1b4b;
}
.fq-hero p {
    max-width: 620px;
    margin: 0 auto;
    color: #475569;
    font-size: 1rem;
    line-height: 1.7;
}
.fq-hero__stat {
    display: inline-flex;
    align-items: center;
    gap: 0.9rem;
    margin-top: 1.4rem;
    padding: 0.6rem 1.25rem;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.85);
    border: 1px solid rgba(226, 232, 240, 0.9);
    color: #475569;
    font-size: 0.88rem;
    font-weight: 600;
}
.fq-hero__stat b {
    color: #4f46e5;
}
.fq-hero__sep {
    width: 1px;
    height: 0.9rem;
    background: rgba(148, 163, 184, 0.5);
}

/* ── Toolbar & pencarian ── */
.fq-toolbar {
    margin-bottom: 1.1rem;
}
.fq-search {
    position: relative;
    display: flex;
    align-items: center;
    gap: 0.7rem;
    padding: 0.85rem 1.15rem;
    border-radius: 999px;
    background: #ffffff;
    border: 1px solid rgba(226, 232, 240, 0.95);
    box-shadow: 0 10px 28px rgba(15, 23, 42, 0.06);
    transition: border-color 0.25s ease, box-shadow 0.25s ease;
}
.fq-search:focus-within {
    border-color: rgba(139, 92, 246, 0.6);
    box-shadow: 0 14px 34px rgba(99, 102, 241, 0.16);
}
.fq-search i {
    color: #6366f1;
    font-size: 1rem;
}
.fq-search input {
    flex: 1;
    border: 0;
    outline: 0;
    background: none;
    font-size: 0.96rem;
    color: #1e1b4b;
}
.fq-search__clear {
    display: grid;
    place-items: center;
    width: 1.7rem;
    height: 1.7rem;
    border: 0;
    border-radius: 50%;
    background: #f1f5f9;
    color: #64748b;
    cursor: pointer;
}
.fq-search__clear:hover {
    background: #e2e8f0;
}

/* ── Chip kategori ── */
.fq-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 0.6rem;
    margin-bottom: 1.9rem;
}
.fq-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.55rem 1rem;
    border-radius: 999px;
    border: 1px solid rgba(226, 232, 240, 0.95);
    background: rgba(255, 255, 255, 0.85);
    color: #334155;
    font-size: 0.86rem;
    font-weight: 700;
    cursor: pointer;
    transition: transform 0.2s ease, border-color 0.2s ease, color 0.2s ease, box-shadow 0.2s ease;
}
.fq-chip:hover {
    border-color: rgba(139, 92, 246, 0.55);
    color: #4f46e5;
    transform: translateY(-2px);
    box-shadow: 0 10px 22px rgba(99, 102, 241, 0.12);
}
.fq-chip i {
    color: #6366f1;
}
.fq-chip__n {
    padding: 0.05rem 0.45rem;
    border-radius: 999px;
    background: rgba(99, 102, 241, 0.12);
    color: #4f46e5;
    font-size: 0.75rem;
    font-weight: 800;
}

.fq-result {
    margin: 0 0 1.2rem;
    color: #475569;
    font-size: 0.9rem;
}

/* ── Section ── */
.fq-section {
    margin-bottom: clamp(2rem, 5vw, 2.75rem);
    scroll-margin-top: 96px;
}
.fq-section__head {
    display: flex;
    align-items: flex-start;
    gap: 0.9rem;
    margin-bottom: 1.1rem;
}
.fq-section__icon {
    display: grid;
    place-items: center;
    width: 2.75rem;
    height: 2.75rem;
    flex: none;
    border-radius: 0.9rem;
    background: linear-gradient(135deg, #8b5cf6, #6366f1);
    color: #ffffff;
    font-size: 1.15rem;
    box-shadow: 0 10px 22px rgba(99, 102, 241, 0.28);
}
.fq-section__head h2 {
    margin: 0.15rem 0 0.2rem;
    font-size: 1.2rem;
    font-weight: 900;
    letter-spacing: -0.01em;
    color: #1e1b4b;
}
.fq-section__head p {
    margin: 0;
    color: #64748b;
    font-size: 0.9rem;
    line-height: 1.6;
}

/* ── Kosong / nihil ── */
.fq-empty,
.fq-nihil {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.6rem;
    padding: clamp(2rem, 6vw, 3.25rem) 1.5rem;
    text-align: center;
    border-radius: 1.5rem;
    background: rgba(255, 255, 255, 0.85);
    border: 1px dashed rgba(148, 163, 184, 0.5);
}
.fq-empty i,
.fq-nihil i {
    font-size: 2.2rem;
    color: #a5b4fc;
}
.fq-empty strong,
.fq-nihil strong {
    font-size: 1.05rem;
    color: #1e1b4b;
}
.fq-empty span,
.fq-nihil span {
    max-width: 460px;
    color: #64748b;
    font-size: 0.92rem;
    line-height: 1.65;
}
.fq-empty__btn {
    margin-top: 0.6rem;
    padding: 0.7rem 1.4rem;
    border-radius: 999px;
    background: linear-gradient(135deg, #8b5cf6, #6366f1);
    color: #ffffff;
    font-size: 0.9rem;
    font-weight: 800;
    text-decoration: none;
}

/* ── CTA ── */
.fq-cta {
    margin-top: clamp(2rem, 5vw, 3rem);
}
.fq-cta__inner {
    padding: clamp(1.75rem, 5vw, 2.75rem);
    border-radius: 1.75rem;
    text-align: center;
    background: linear-gradient(140deg, rgba(99, 102, 241, 0.1), rgba(139, 92, 246, 0.06));
    border: 1px solid rgba(139, 92, 246, 0.28);
}
.fq-cta__icon {
    display: grid;
    place-items: center;
    width: 3.1rem;
    height: 3.1rem;
    margin: 0 auto 0.9rem;
    border-radius: 1rem;
    background: linear-gradient(135deg, #8b5cf6, #6366f1);
    color: #ffffff;
    font-size: 1.3rem;
    box-shadow: 0 14px 30px rgba(99, 102, 241, 0.3);
}
.fq-cta__inner h2 {
    margin: 0 0 0.5rem;
    font-size: clamp(1.25rem, 3.5vw, 1.6rem);
    font-weight: 900;
    letter-spacing: -0.015em;
    color: #1e1b4b;
}
.fq-cta__inner p {
    max-width: 520px;
    margin: 0 auto 1.4rem;
    color: #475569;
    font-size: 0.95rem;
    line-height: 1.7;
}
.fq-cta__actions {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 0.7rem;
}
.fq-cta__btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.8rem 1.5rem;
    border-radius: 999px;
    border: 1px solid rgba(139, 92, 246, 0.35);
    background: rgba(255, 255, 255, 0.92);
    color: #4f46e5;
    font-size: 0.92rem;
    font-weight: 800;
    text-decoration: none;
    transition: transform 0.22s ease, box-shadow 0.22s ease;
}
.fq-cta__btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 14px 28px rgba(99, 102, 241, 0.18);
}
.fq-cta__btn--primary {
    background: linear-gradient(135deg, #8b5cf6, #6366f1);
    border-color: transparent;
    color: #ffffff;
}
.fq-cta__note {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    margin-top: 1.2rem;
    color: #475569;
    font-size: 0.82rem;
}
.fq-cta__note i {
    color: #16a34a;
}

@media (max-width: 640px) {
    .fq-cta__actions {
        flex-direction: column;
    }
    .fq-cta__btn {
        justify-content: center;
    }
}
</style>
