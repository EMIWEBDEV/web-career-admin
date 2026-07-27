<!-- WEB CAREER — SEMUA LOWONGAN: halaman khusus kumpulan SELURUH peluang
     (lowongan rekrutmen + program MT). Landing hanya cuplikan 6; halaman ini
     memuat semuanya dengan pencarian + filter. Kartu memakai kelas global yang
     sama dengan landing (wc-job / wc-mt__card) — klik → halaman detail yang ada. -->
<template>
    <Head><title>Semua Lowongan - EVO Career</title></Head>

    <CareerLayout :has-mt="hasMt" :offices="offices">
        <div class="sl">
            <!-- ═══ HERO ═══ -->
            <header class="sl-hero">
                <div class="sl-hero__glow sl-hero__glow--a"></div>
                <div class="sl-hero__glow sl-hero__glow--b"></div>
                <div class="sl-hero__in">
                    <span class="wc-eyebrow"><span class="wc-dot"></span> Semua Peluang Karir</span>
                    <h1>Jelajahi <span class="wc-grad">seluruh peluang</span> di EVO Group.</h1>
                    <p>{{ lowongan.length }} lowongan terbuka dan {{ programMt.length }} program Management Trainee menantimu. Temukan peran terbaikmu, lalu lamar langsung.</p>
                    <div class="sl-hero__stats">
                        <div class="sl-stat">
                            <span class="sl-stat__ico sl-stat__ico--indigo"><i class="bi bi-briefcase-fill"></i></span>
                            <span><b>{{ lowongan.length }}</b> Lowongan Terbuka</span>
                        </div>
                        <div class="sl-stat">
                            <span class="sl-stat__ico sl-stat__ico--amber"><i class="bi bi-mortarboard-fill"></i></span>
                            <span><b>{{ programMt.length }}</b> Program MT</span>
                        </div>
                        <div class="sl-stat">
                            <span class="sl-stat__ico sl-stat__ico--green"><i class="bi bi-people-fill"></i></span>
                            <span><b>{{ totalKursi }}</b> Total Kursi</span>
                        </div>
                    </div>
                </div>
            </header>

            <!-- ═══ TOOLBAR ═══ -->
            <div class="sl-toolbar">
                <div class="sl-tabs">
                    <button type="button" class="sl-tab" :class="{ 'is-on': tab === 'semua' }" @click="tab = 'semua'">Semua <span class="sl-tab__n">{{ lowongan.length + programMt.length }}</span></button>
                    <button type="button" class="sl-tab" :class="{ 'is-on': tab === 'rek' }" @click="tab = 'rek'">Lowongan <span class="sl-tab__n">{{ lowongan.length }}</span></button>
                    <button type="button" class="sl-tab" :class="{ 'is-on': tab === 'mt' }" @click="tab = 'mt'">Management Trainee <span class="sl-tab__n">{{ programMt.length }}</span></button>
                </div>
                <div class="sl-search">
                    <i class="bi bi-search"></i>
                    <input v-model="search" type="text" placeholder="Cari posisi, skill, benefit, atau program…" />
                    <button v-if="search" type="button" class="sl-search__clear" @click="search = ''"><i class="bi bi-x-lg"></i></button>
                </div>
            </div>

            <!-- ═══ LOWONGAN REKRUTMEN ═══ -->
            <section v-if="tab !== 'mt'" class="sl-sec">
                <div class="sl-sec__head">
                    <h2><i class="bi bi-briefcase"></i> Lowongan Terbuka</h2>
                    <span class="sl-sec__count">{{ filteredJobs.length }} posisi</span>
                </div>
                <div v-if="filteredJobs.length" class="rek__grid">
                    <LowonganCard v-for="job in pagedJobs" :key="job.id" :job="job" />
                </div>
                <div v-else class="sl-empty"><i class="bi bi-clipboard-x"></i> Tidak ada lowongan yang cocok dengan pencarianmu.</div>

                <!-- Paginasi loker (10 per halaman) -->
                <div v-if="jobsTotalPages > 1" class="sl-pager">
                    <button type="button" class="sl-pager__nav" :disabled="jobsPage <= 1" @click="goJobsPage(jobsPage - 1)"><i class="bi bi-chevron-left"></i></button>
                    <button v-for="p in jobsTotalPages" :key="p" type="button" class="sl-pager__num" :class="{ 'is-on': p === jobsPage }" @click="goJobsPage(p)">{{ p }}</button>
                    <button type="button" class="sl-pager__nav" :disabled="jobsPage >= jobsTotalPages" @click="goJobsPage(jobsPage + 1)"><i class="bi bi-chevron-right"></i></button>
                </div>
            </section>

            <!-- ═══ PROGRAM MT (LIGHT, 1:1 desain) ═══ -->
            <section v-if="tab !== 'rek'" id="mt" class="sl-mt">
                <div class="sl-mt__wrap">
                    <div class="sl-sec__head">
                        <h2><i class="bi bi-gem"></i> Program Management Trainee</h2>
                        <span class="sl-sec__count sl-sec__count--gold">{{ filteredMt.length }} program</span>
                    </div>
                    <div v-if="filteredMt.length" class="sl-mt__grid">
                        <Link v-for="(mt, i) in pagedMt" :key="mt.id" class="mtl__card" :class="{ 'is-full': isFull(mt) }" :style="{ '--d': i * 60 + 'ms' }" :href="mtUrl(mt.id)">
                            <div class="mtl__cardtop">
                                <span class="mtl__batch"><i class="bi bi-stars"></i> {{ mt.batch || 'Management Trainee' }}</span>
                                <span class="mtl__status" :class="statusClass(mt.status)">{{ statusLabel(mt.status) }}</span>
                            </div>
                            <h3 class="mtl__title">{{ mt.nama }}</h3>
                            <div class="mtl__tag">{{ mt.tagline || 'Program Management Trainee EVO Group.' }}</div>
                            <p class="mtl__desc">{{ mt.ringkasan }}</p>
                            <div class="mtl__meta">
                                <span><i class="bi bi-people"></i> {{ mt.tipeKegiatan }}</span>
                                <span><i class="bi bi-geo-alt"></i> {{ mt.penempatan }}</span>
                                <span><i class="bi bi-clock-history"></i> {{ mt.durasi }}</span>
                                <span><i class="bi bi-grid-1x2"></i> {{ mt.kuota }} kursi</span>
                            </div>
                            <div class="mtl__quota">
                                <div class="mtl__quota-head">
                                    <span>Kuota terisi</span>
                                    <b :class="{ full: isFull(mt) }">{{ mt.kuotaTerisi }} / {{ mt.kuota }}</b>
                                </div>
                                <div class="mtl__bar"><span :class="{ full: isFull(mt) }" :style="{ width: kuotaPct(mt) + '%' }"></span></div>
                            </div>
                            <div class="mtl__foot">
                                <span class="mtl__deadline" :class="{ soon: !isFull(mt) && daysLeft(mt.tanggalTutup) <= 7 }">
                                    <i class="bi" :class="isFull(mt) ? 'bi-lock-fill' : 'bi-calendar-event'"></i>
                                    {{ isFull(mt) ? 'Pendaftaran ditutup' : 'Ditutup ' + formatDate(mt.tanggalTutup) }}
                                </span>
                                <span class="mtl__cta">Pelajari <i class="bi bi-arrow-right"></i></span>
                            </div>
                        </Link>
                    </div>
                    <div v-else class="sl-empty"><i class="bi bi-clipboard-x"></i> Tidak ada program MT yang cocok dengan pencarianmu.</div>

                    <!-- Paginasi MT (10 per halaman) -->
                    <div v-if="mtTotalPages > 1" class="sl-pager">
                        <button type="button" class="sl-pager__nav" :disabled="mtPage <= 1" @click="goMtPage(mtPage - 1)"><i class="bi bi-chevron-left"></i></button>
                        <button v-for="p in mtTotalPages" :key="p" type="button" class="sl-pager__num" :class="{ 'is-on': p === mtPage }" @click="goMtPage(p)">{{ p }}</button>
                        <button type="button" class="sl-pager__nav" :disabled="mtPage >= mtTotalPages" @click="goMtPage(mtPage + 1)"><i class="bi bi-chevron-right"></i></button>
                    </div>
                </div>
            </section>

            <!-- ═══ CTA KEMBALI ═══ -->
            <div class="sl-back">
                <Link href="/karir/landing-page" class="sl-back__btn"><i class="bi bi-arrow-left"></i> Kembali ke Beranda Karir</Link>
            </div>
        </div>
    </CareerLayout>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import CareerLayout from './Layouts/CareerLayout.vue';
import LowonganCard from './components/LowonganCard.vue';
import { daysLeft, formatDate, isFull, kuotaPct, mtUrl, statusClass, statusLabel } from './careerData';

defineOptions({ layout: null });

const props = defineProps({
    lowongan: { type: Array, default: () => [] },
    programMt: { type: Array, default: () => [] },
    departments: { type: Array, default: () => [] },
    locations: { type: Array, default: () => [] },
    offices: { type: Array, default: () => [] },
});

const search = ref('');
// Tab awal dari query ?tab= (bukan lagi #anchor).
const initTab = (() => {
    try { const t = new URLSearchParams(window.location.search).get('tab'); return ['semua', 'rek', 'mt'].includes(t) ? t : 'semua'; } catch { return 'semua'; }
})();
const tab = ref(initTab);

// Paginasi 10 per halaman untuk daftar loker & MT.
const PER_PAGE = 10;
const jobsPage = ref(1);
const mtPage = ref(1);
watch([search, tab], () => { jobsPage.value = 1; mtPage.value = 1; });

const hasMt = computed(() => props.programMt.length > 0);
const totalKursi = computed(
    () => props.lowongan.reduce((a, j) => a + (j.kuota || 0), 0) + props.programMt.reduce((a, m) => a + (m.kuota || 0), 0),
);

const filteredJobs = computed(() => {
    const q = search.value.trim().toLowerCase();
    if (!q) return props.lowongan;
    return props.lowongan.filter((job) =>
        [job.posisi, job.lokasi, job.ringkasan, ...(job.skill || []), ...(job.benefit || [])].join(' ').toLowerCase().includes(q),
    );
});
const filteredMt = computed(() => {
    const q = search.value.trim().toLowerCase();
    if (!q) return props.programMt;
    return props.programMt.filter((mt) => [mt.nama, mt.tagline, mt.penempatan, mt.ringkasan].join(' ').toLowerCase().includes(q));
});

const jobsTotalPages = computed(() => Math.max(1, Math.ceil(filteredJobs.value.length / PER_PAGE)));
const mtTotalPages = computed(() => Math.max(1, Math.ceil(filteredMt.value.length / PER_PAGE)));
const pagedJobs = computed(() => filteredJobs.value.slice((jobsPage.value - 1) * PER_PAGE, jobsPage.value * PER_PAGE));
const pagedMt = computed(() => filteredMt.value.slice((mtPage.value - 1) * PER_PAGE, mtPage.value * PER_PAGE));
function goJobsPage(p) { if (p >= 1 && p <= jobsTotalPages.value) { jobsPage.value = p; scrollTop(); } }
function goMtPage(p) { if (p >= 1 && p <= mtTotalPages.value) { mtPage.value = p; scrollTop(); } }
function scrollTop() { try { window.scrollTo({ top: 240, behavior: 'smooth' }); } catch { /* noop */ } }
</script>

<style scoped>
.sl { min-height: 100vh; background: linear-gradient(180deg, #f3f2fd 0%, #eef1fb 46%, #eaf0fb 100%); }

/* ═══ HERO ═══ */
.sl-hero { position: relative; overflow: hidden; padding: 6.5rem 1.5rem 3rem; }
.sl-hero__glow { position: absolute; border-radius: 50%; filter: blur(10px); pointer-events: none; }
.sl-hero__glow--a { top: -140px; right: 10%; width: 420px; height: 420px; background: radial-gradient(circle at 30% 30%, rgba(139, 92, 246, 0.16), rgba(139, 92, 246, 0) 70%); }
.sl-hero__glow--b { bottom: -180px; left: 5%; width: 440px; height: 440px; background: radial-gradient(circle at 60% 40%, rgba(99, 102, 241, 0.12), rgba(99, 102, 241, 0) 70%); }
.sl-hero__in { position: relative; max-width: 1200px; margin: 0 auto; text-align: center; }
.sl-hero__in h1 { margin: 14px 0 0; font-size: clamp(1.9rem, 4vw, 2.9rem); font-weight: 800; color: #0f172a; letter-spacing: -0.03em; }
.sl-hero__in p { margin: 12px auto 0; font-size: 15px; color: #64748b; max-width: 620px; line-height: 1.65; }
.sl-hero__stats { display: flex; justify-content: center; gap: 14px; flex-wrap: wrap; margin-top: 26px; }
.sl-stat { display: inline-flex; align-items: center; gap: 10px; padding: 10px 18px; border-radius: 15px; background: rgba(255, 255, 255, 0.8); border: 1px solid rgba(226, 232, 240, 0.8); font-size: 13px; font-weight: 600; color: #64748b; box-shadow: 0 6px 18px rgba(15, 23, 42, 0.05); }
.sl-stat b { color: #0f172a; font-weight: 800; }
.sl-stat__ico { width: 34px; height: 34px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 15px; }
.sl-stat__ico--indigo { background: rgba(99, 102, 241, 0.12); color: #6366f1; }
.sl-stat__ico--amber { background: rgba(245, 158, 11, 0.14); color: #f59e0b; }
.sl-stat__ico--green { background: rgba(16, 185, 129, 0.12); color: #10b981; }

/* ═══ TOOLBAR ═══ */
.sl-toolbar { position: sticky; top: 64px; z-index: 20; max-width: 1200px; margin: 0 auto; padding: 10px 1.5rem; display: flex; align-items: center; justify-content: space-between; gap: 14px; flex-wrap: wrap; }
.sl-tabs { display: flex; gap: 8px; flex-wrap: wrap; }
.sl-tab { appearance: none; cursor: pointer; font-family: inherit; font-size: 13px; font-weight: 700; padding: 10px 16px; border-radius: 12px; border: 1px solid #e6e9f3; background: rgba(255, 255, 255, 0.9); color: #64748b; transition: all 0.16s; display: inline-flex; align-items: center; gap: 8px; }
.sl-tab.is-on { border-color: transparent; background: linear-gradient(135deg, #8b5cf6, #6366f1); color: #fff; box-shadow: 0 10px 24px rgba(99, 102, 241, 0.28); }
.sl-tab__n { font-size: 11px; font-weight: 800; padding: 2px 8px; border-radius: 8px; background: #eef0f7; color: #94a3b8; }
.sl-tab.is-on .sl-tab__n { background: rgba(255, 255, 255, 0.24); color: #fff; }
.sl-search { position: relative; display: flex; align-items: center; flex: 1; min-width: 240px; max-width: 420px; }
.sl-search .bi-search { position: absolute; left: 15px; color: #94a3b8; font-size: 14px; }
.sl-search input { width: 100%; padding: 12px 40px 12px 42px; border-radius: 14px; border: 1px solid #e6e9f3; background: rgba(255, 255, 255, 0.92); font-family: inherit; font-size: 13.5px; color: #334155; outline: none; transition: all 0.18s; box-shadow: 0 4px 14px rgba(15, 23, 42, 0.04); }
.sl-search input:focus { border-color: #a5b4fc; background: #fff; box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.12); }
.sl-search__clear { position: absolute; right: 10px; appearance: none; border: none; background: #eef0f7; width: 24px; height: 24px; border-radius: 8px; color: #64748b; cursor: pointer; font-size: 11px; display: flex; align-items: center; justify-content: center; }

/* ═══ SECTIONS ═══ */
.sl-sec { max-width: 1200px; margin: 0 auto; padding: 1.6rem 1.5rem 2.4rem; }
.sl-sec__head { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 1.4rem; }
.sl-sec__head h2 { margin: 0; font-size: 20px; font-weight: 800; color: #0f172a; letter-spacing: -0.02em; display: flex; align-items: center; gap: 10px; }
.sl-sec__head h2 .bi { color: #6366f1; }
.sl-sec__head--light h2 { color: #fff; }
.sl-sec__head--light h2 .bi { color: #fbbf24; }
.sl-sec__count { font-size: 12px; font-weight: 800; color: #6366f1; background: rgba(99, 102, 241, 0.1); border-radius: 999px; padding: 6px 14px; }
.sl-sec__count--gold { color: #fbbf24; background: rgba(251, 191, 36, 0.14); }
.sl-empty { padding: 3rem 1rem; text-align: center; color: #94a3b8; font-size: 14px; display: flex; align-items: center; justify-content: center; gap: 10px; }
.sl-empty--light { color: rgba(255, 255, 255, 0.65); }

/* ═══ GRID KARTU REKRUTMEN (kartunya di components/LowonganCard.vue) ═══ */
.rek__grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 1.05rem; }

/* ═══ SECTION MT (LIGHT, selaras section MT landing) ═══ */
.sl-mt { position: relative; overflow: hidden; padding: 2.6rem 1.5rem 3rem; background: radial-gradient(700px 340px at 84% 0%, rgba(139, 92, 246, 0.12), transparent 60%), linear-gradient(160deg, #f4f1fe 0%, #eef2ff 54%, #f6f1ff 100%); border-top: 1px solid rgba(99, 102, 241, 0.1); border-bottom: 1px solid rgba(99, 102, 241, 0.1); }
.sl-mt__wrap { position: relative; z-index: 2; max-width: 1200px; margin: 0 auto; }
.sl-mt .sl-sec__head { margin-bottom: 1.4rem; }
.sl-mt__grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(288px, 1fr)); gap: 1.05rem; }

/* Kartu MT light (identik dengan landing MtSection) */
.mtl__card { display: flex; flex-direction: column; background: rgba(255, 255, 255, 0.92); border: 1px solid rgba(226, 232, 240, 0.9); border-radius: 20px; padding: 18px; box-shadow: 0 8px 24px rgba(15, 23, 42, 0.05); text-decoration: none; transition: transform 0.18s ease, box-shadow 0.18s ease, border-color 0.18s ease; }
.mtl__card:hover { transform: translateY(-5px); border-color: rgba(139, 92, 246, 0.4); box-shadow: 0 20px 44px rgba(124, 110, 222, 0.18); }
.mtl__card.is-full { opacity: 0.9; }
.mtl__cardtop { display: flex; align-items: center; justify-content: space-between; gap: 8px; }
.mtl__batch { display: inline-flex; align-items: center; gap: 6px; font-size: 0.66rem; font-weight: 800; color: #b45309; background: rgba(245, 158, 11, 0.13); border: 1px solid rgba(245, 158, 11, 0.26); border-radius: 8px; padding: 4px 9px; }
.mtl__status { font-size: 0.63rem; font-weight: 800; border-radius: 8px; padding: 4px 9px; color: #059669; background: rgba(16, 185, 129, 0.12); }
.mtl__status.is-soon { color: #b45309; background: rgba(245, 158, 11, 0.14); }
.mtl__status.is-closed { color: #e11d48; background: rgba(225, 29, 72, 0.1); }
.mtl__title { margin: 13px 0 0; font-size: 1.03rem; font-weight: 800; color: #1e293b; letter-spacing: -0.01em; line-height: 1.25; text-wrap: pretty; }
.mtl__tag { font-size: 0.72rem; font-weight: 700; color: #8b5cf6; margin-top: 5px; }
.mtl__desc { margin: 9px 0 0; font-size: 0.78rem; line-height: 1.55; color: #64748b; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.mtl__meta { display: grid; grid-template-columns: 1fr 1fr; gap: 9px 12px; margin-top: 14px; }
.mtl__meta span { display: inline-flex; align-items: center; gap: 6px; font-size: 0.72rem; color: #64748b; }
.mtl__meta i { color: #8b5cf6; font-size: 0.82rem; }
.mtl__quota { margin-top: 14px; }
.mtl__quota-head { display: flex; align-items: center; justify-content: space-between; font-size: 0.66rem; color: #94a3b8; margin-bottom: 5px; }
.mtl__quota-head b { font-family: 'JetBrains Mono', ui-monospace, monospace; font-weight: 700; color: #6366f1; }
.mtl__quota-head b.full { color: #e11d48; }
.mtl__bar { height: 6px; border-radius: 99px; background: #eef0f7; overflow: hidden; }
.mtl__bar span { display: block; height: 100%; border-radius: 99px; background: linear-gradient(90deg, #8b5cf6, #6366f1); transition: width 0.5s ease; }
.mtl__bar span.full { background: linear-gradient(90deg, #fb7185, #e11d48); }
.mtl__foot { display: flex; align-items: center; justify-content: space-between; gap: 10px; margin-top: 15px; padding-top: 14px; border-top: 1px solid #f1f2f9; }
.mtl__deadline { display: inline-flex; align-items: center; gap: 6px; font-size: 0.68rem; color: #94a3b8; }
.mtl__deadline.soon { color: #b45309; font-weight: 700; }
.mtl__cta { display: inline-flex; align-items: center; gap: 5px; font-size: 0.75rem; font-weight: 800; color: #4f46e5; }
.mtl__cta i { transition: transform 0.18s ease; }
.mtl__card:hover .mtl__cta i { transform: translateX(4px); }
@media (max-width: 560px) { .mtl__meta { grid-template-columns: 1fr; } }

/* ═══ PAGINASI ═══ */
.sl-pager { display: flex; align-items: center; justify-content: center; gap: 7px; margin-top: 26px; flex-wrap: wrap; }
.sl-pager__num, .sl-pager__nav { appearance: none; cursor: pointer; min-width: 38px; height: 38px; padding: 0 10px; border-radius: 11px; border: 1px solid #e6e9f3; background: rgba(255, 255, 255, 0.9); color: #64748b; font-family: inherit; font-size: 13.5px; font-weight: 700; display: inline-flex; align-items: center; justify-content: center; transition: all 0.16s; }
.sl-pager__num:hover:not(.is-on), .sl-pager__nav:hover:not(:disabled) { border-color: #a5b4fc; color: #4f46e5; }
.sl-pager__num.is-on { background: linear-gradient(135deg, #8b5cf6, #6366f1); border-color: transparent; color: #fff; box-shadow: 0 10px 22px rgba(99, 102, 241, 0.3); }
.sl-pager__nav:disabled { opacity: 0.4; cursor: not-allowed; }
.sl-pager--light .sl-pager__num, .sl-pager--light .sl-pager__nav { background: rgba(255, 255, 255, 0.1); border-color: rgba(255, 255, 255, 0.2); color: rgba(255, 255, 255, 0.85); }
.sl-pager--light .sl-pager__num.is-on { background: linear-gradient(135deg, #fbbf24, #f59e0b); border-color: transparent; color: #1e1b4b; box-shadow: 0 10px 22px rgba(245, 158, 11, 0.34); }
.sl-pager--light .sl-pager__num:hover:not(.is-on), .sl-pager--light .sl-pager__nav:hover:not(:disabled) { border-color: rgba(255, 255, 255, 0.5); color: #fff; }

/* ═══ CTA KEMBALI ═══ */
.sl-back { display: flex; justify-content: center; padding: 2.6rem 1rem 3.2rem; }
.sl-back__btn { display: inline-flex; align-items: center; gap: 9px; font-size: 13.5px; font-weight: 800; color: #4f46e5; padding: 12px 24px; border-radius: 14px; text-decoration: none; background: #fff; border: 1px solid #dfe3f3; box-shadow: 0 8px 22px rgba(15, 23, 42, 0.06); transition: all 0.18s; }
.sl-back__btn:hover { transform: translateY(-2px); border-color: #a5b4fc; color: #4338ca; }

@media (max-width: 767.98px) {
    .sl-hero { padding-top: 5.4rem; }
    .sl-toolbar { position: static; }
}
</style>
