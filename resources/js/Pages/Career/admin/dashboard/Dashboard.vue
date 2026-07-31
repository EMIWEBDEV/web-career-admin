<!--
  WEB CAREER — DASHBOARD ADMIN, bertab per kategori (Rekrutmen / Magang / MT).

  Aksi DAN analitik dua-duanya seksi penuh, bukan salah satu jadi pelengkap.
  Konsekuensinya halaman ini padat isi — ditangani dua hal: QUICK-NAV LEKAT di
  bawah tab (chip yang melompat ke seksi dan menyala mengikuti posisi gulir,
  ditambah seksi yang bisa dilipat), dan KERAPATAN yang dijaga di satu tempat:
  semua bantalan/jarak chrome ditetapkan di blok <style> bawah, jadi merapatkan
  atau melonggarkan halaman ini cukup disetel di sana, bukan diburu satu per
  satu di sebelas komponen panel.

  Data TIDAK datang lewat props Inertia melainkan tiga endpoint terpisah, jadi
  KPI + antrean aksi sudah terbaca sementara analitik & seksi khas masih
  dihitung. Pindah tab hanya menembak ulang tiga endpoint itu.
-->
<template>
    <Head><title>Dashboard - Web Career</title></Head>

    <div class="wca wcd">
        <!-- ══════════════ KEPALA ══════════════ -->
        <header class="wcd-head">
            <div class="wcd-head__intro">
                <span class="wcd-head__lencana" :style="{ '--aksen': aksen }">
                    <i class="bi" :class="tabAktif ? tabAktif.ikon : 'bi-speedometer2'"></i>
                </span>
                <div>
                    <h1>{{ sapaan }}, {{ namaAdmin }}</h1>
                    <p>
                        Ringkasan rekrutmen
                        <b v-if="tabAktif">{{ tabAktif.nama }}</b>
                        — apa yang perlu ditindak hari ini, dan ke mana angkanya bergerak.
                    </p>
                </div>
            </div>

            <div class="wcd-head__alat">
                <span class="wcd-cp" :title="checkpoint ? checkpoint.waktu : ''">
                    <i class="bi" :class="sibuk ? 'bi-arrow-repeat wcd-spin' : 'bi-clock-history'"></i>
                    Data per <b>{{ checkpoint ? checkpoint.label : '—' }}</b>
                </span>
                <div class="wcd-seg" role="group" aria-label="Auto refresh">
                    <button v-for="o in [0, 60, 300]" :key="o" type="button" :class="{ 'is-on': intervalSec === o }"
                        :title="o === 0 ? 'Muat ulang otomatis mati' : `Muat ulang tiap ${o / 60} menit`"
                        @click="setInterval_(o)">{{ o === 0 ? 'Off' : (o / 60) + 'm' }}</button>
                </div>
                <button type="button" class="wca-btn wca-btn--primary wca-btn--sm" :disabled="sibuk" @click="refreshNow">
                    <i class="bi bi-arrow-clockwise"></i> Muat ulang
                </button>
            </div>
        </header>

        <!-- ══════════════ TAB KATEGORI ══════════════ -->
        <!-- Tab hanya muncul kalau memang ada pilihan. Satu tab tunggal
             dirender sebagai keterangan, bukan tab yang tak bisa diklik. -->
        <nav v-if="tabs.length > 1" class="wcd-tabs" role="tablist" aria-label="Kategori program">
            <button v-for="t in tabs" :key="t.kode" type="button" role="tab" :aria-selected="kategori === t.kode"
                class="wcd-tab" :class="{ 'is-on': kategori === t.kode }" :style="{ '--aksen': t.warna }"
                @click="pilihTab(t.kode)">
                <i class="bi" :class="t.ikon"></i>
                <span>{{ t.nama }}</span>
            </button>
        </nav>
        <p v-else-if="tabs.length === 1" class="wcd-solo">
            <i class="bi" :class="tabs[0].ikon" :style="{ color: tabs[0].warna }"></i>
            Hak akses Anda mencakup kategori <b>{{ tabs[0].nama }}</b> saja.
        </p>
        <div v-else class="wcd-card">
            <KeadaanPanel keadaan="kosong" ikon="bi-shield-lock"
                teks="Belum ada kategori yang bisa Anda lihat"
                ket="Minta admin mengisi bagian KONTEN halaman Dashboard di Manajemen Hak Akses." />
        </div>

        <template v-if="tabs.length">
            <!-- ══════════════ QUICK-NAV LEKAT ══════════════ -->
            <nav class="wcd-jump" aria-label="Lompat ke seksi">
                <a v-for="s in seksiTampil" :key="s.id" :href="`#wcd-${s.id}`"
                    :class="{ 'is-on': seksiAktif === s.id }" @click.prevent="lompat(s.id)">
                    <i class="bi" :class="s.ikon"></i><span>{{ s.label }}</span>
                    <em v-if="s.lencana">{{ s.lencana }}</em>
                </a>
            </nav>

            <!-- ══════════════ ZONA A — KPI ══════════════ -->
            <div :class="{ 'wcd-basi': zona.ringkas.segar }">
                <KeadaanPanel v-if="zona.ringkas.keadaan === 'memuat' && !zona.ringkas.data" keadaan="memuat" />
                <KeadaanPanel v-else-if="zona.ringkas.keadaan === 'galat'" keadaan="galat"
                    :ket="zona.ringkas.pesan" @ulang="muatRingkas()" />
                <KpiStrip v-else-if="zona.ringkas.data" :kpi="zona.ringkas.data.kpi"
                    :periode="zona.ringkas.data.periode" :ambang="ambang" :kategori="kategori"
                    @lompat="lompat" />
            </div>

            <!-- ══════════════ ZONA B — BUTUH AKSI ══════════════ -->
            <section :id="`wcd-aksi`" ref="refAksi" class="wcd-sec">
                <button type="button" class="wcd-sec__hd" @click="lipat('aksi')">
                    <i class="bi bi-lightning-charge-fill"></i>
                    <h2>Butuh Aksi Kamu</h2>
                    <span v-if="totalAksi" class="wcd-sec__jml">{{ totalAksi }}</span>
                    <i class="bi wcd-sec__chev" :class="tutup.has('aksi') ? 'bi-chevron-down' : 'bi-chevron-up'"></i>
                </button>
                <div v-show="!tutup.has('aksi')" class="wcd-sec__bd" :class="{ 'wcd-basi': zona.ringkas.segar }">
                    <KeadaanPanel v-if="zona.ringkas.keadaan === 'memuat' && !zona.ringkas.data" keadaan="memuat" />
                    <KeadaanPanel v-else-if="zona.ringkas.keadaan === 'galat'" keadaan="galat"
                        :ket="zona.ringkas.pesan" @ulang="muatRingkas()" />
                    <AntreanAksi v-else-if="zona.ringkas.data" :aksi="zona.ringkas.data.aksi" :ambang="ambang" />
                </div>
            </section>

            <!-- ══════════════ ZONA C — ANALITIK ══════════════ -->
            <section :id="`wcd-funnel`" ref="refFunnel" class="wcd-sec">
                <button type="button" class="wcd-sec__hd" @click="lipat('funnel')">
                    <i class="bi bi-funnel-fill"></i>
                    <h2>Funnel &amp; Konversi</h2>
                    <i class="bi wcd-sec__chev" :class="tutup.has('funnel') ? 'bi-chevron-down' : 'bi-chevron-up'"></i>
                </button>
                <div v-show="!tutup.has('funnel')" class="wcd-sec__bd" :class="{ 'wcd-basi': zona.analitik.segar }">
                    <KeadaanPanel v-if="zona.analitik.keadaan === 'memuat' && !zona.analitik.data" keadaan="memuat" />
                    <KeadaanPanel v-else-if="zona.analitik.keadaan === 'galat'" keadaan="galat"
                        :ket="zona.analitik.pesan" @ulang="muatAnalitik()" />
                    <FunnelPanel v-else-if="zona.analitik.data" :funnel="zona.analitik.data.funnel" />
                </div>
            </section>

            <section :id="`wcd-tren`" ref="refTren" class="wcd-sec">
                <button type="button" class="wcd-sec__hd" @click="lipat('tren')">
                    <i class="bi bi-graph-up"></i>
                    <h2>Tren Lamaran</h2>
                    <span class="wcd-sec__ket">{{ labelPeriode }}</span>
                    <i class="bi wcd-sec__chev" :class="tutup.has('tren') ? 'bi-chevron-down' : 'bi-chevron-up'"></i>
                </button>
                <div v-show="!tutup.has('tren')" class="wcd-sec__bd" :class="{ 'wcd-basi': zona.analitik.segar }">
                    <div class="wcd-filter">
                        <span class="wcd-filter__lbl">Periode</span>
                        <div class="wcd-seg">
                            <button v-for="p in PERIODE" :key="p.nilai" type="button"
                                :class="{ 'is-on': periode === p.nilai }" @click="setPeriode(p.nilai)">{{ p.label }}</button>
                        </div>
                        <!-- Jujur soal cakupan: periode TIDAK menyaring seluruh
                             halaman, hanya dua hal yang memang bertren. -->
                        <small>Mempengaruhi grafik ini &amp; kartu "Lamaran baru" saja.</small>
                    </div>
                    <KeadaanPanel v-if="zona.analitik.keadaan === 'memuat' && !zona.analitik.data" keadaan="memuat" />
                    <KeadaanPanel v-else-if="zona.analitik.keadaan === 'galat'" keadaan="galat"
                        :ket="zona.analitik.pesan" @ulang="muatAnalitik()" />
                    <TrenPanel v-else-if="zona.analitik.data" :tren="zona.analitik.data.tren" />
                </div>
            </section>

            <section :id="`wcd-program`" ref="refProgram" class="wcd-sec">
                <button type="button" class="wcd-sec__hd" @click="lipat('program')">
                    <i class="bi bi-heart-pulse-fill"></i>
                    <h2>Kesehatan &amp; Kuota Program</h2>
                    <i class="bi wcd-sec__chev" :class="tutup.has('program') ? 'bi-chevron-down' : 'bi-chevron-up'"></i>
                </button>
                <div v-show="!tutup.has('program')" class="wcd-sec__bd" :class="{ 'wcd-basi': zona.analitik.segar }">
                    <KeadaanPanel v-if="zona.analitik.keadaan === 'memuat' && !zona.analitik.data" keadaan="memuat" />
                    <KeadaanPanel v-else-if="zona.analitik.keadaan === 'galat'" keadaan="galat"
                        :ket="zona.analitik.pesan" @ulang="muatAnalitik()" />
                    <KesehatanPanel v-else-if="zona.analitik.data" :baris="zona.analitik.data.kesehatan" />
                </div>
            </section>

            <section :id="`wcd-agenda`" ref="refAgenda" class="wcd-sec">
                <button type="button" class="wcd-sec__hd" @click="lipat('agenda')">
                    <i class="bi bi-calendar-week-fill"></i>
                    <h2>Agenda 14 Hari</h2>
                    <span v-if="jmlAgenda" class="wcd-sec__jml">{{ jmlAgenda }}</span>
                    <i class="bi wcd-sec__chev" :class="tutup.has('agenda') ? 'bi-chevron-down' : 'bi-chevron-up'"></i>
                </button>
                <div v-show="!tutup.has('agenda')" class="wcd-sec__bd" :class="{ 'wcd-basi': zona.analitik.segar }">
                    <KeadaanPanel v-if="zona.analitik.keadaan === 'memuat' && !zona.analitik.data" keadaan="memuat" />
                    <KeadaanPanel v-else-if="zona.analitik.keadaan === 'galat'" keadaan="galat"
                        :ket="zona.analitik.pesan" @ulang="muatAnalitik()" />
                    <AgendaPanel v-else-if="zona.analitik.data" :agenda="zona.analitik.data.agenda" />
                </div>
            </section>

            <!-- ══════════════ ZONA D — SEKSI KHAS ══════════════ -->
            <section :id="`wcd-khas`" ref="refKhas" class="wcd-sec">
                <button type="button" class="wcd-sec__hd" @click="lipat('khas')">
                    <i class="bi" :class="khasIkon"></i>
                    <h2>{{ khasJudul }}</h2>
                    <i class="bi wcd-sec__chev" :class="tutup.has('khas') ? 'bi-chevron-down' : 'bi-chevron-up'"></i>
                </button>
                <div v-show="!tutup.has('khas')" class="wcd-sec__bd" :class="{ 'wcd-basi': zona.khas.segar }">
                    <KeadaanPanel v-if="zona.khas.keadaan === 'memuat' && !zona.khas.data" keadaan="memuat" />
                    <KeadaanPanel v-else-if="zona.khas.keadaan === 'galat'" keadaan="galat"
                        :ket="zona.khas.pesan" @ulang="muatKhas()" />
                    <template v-else-if="zona.khas.data">
                        <MtPanel v-if="zona.khas.data.bentuk === 'MT'" :khas="zona.khas.data.khas" />
                        <MagangPanel v-else-if="zona.khas.data.bentuk === 'MAGANG'" :khas="zona.khas.data.khas" />
                        <RekrutmenPanel v-else :khas="zona.khas.data.khas" />
                    </template>
                </div>
            </section>

            <!-- ══════════════ ZONA E — EKSTRA ══════════════ -->
            <section :id="`wcd-ekstra`" ref="refEkstra" class="wcd-sec">
                <button type="button" class="wcd-sec__hd" @click="lipat('ekstra')">
                    <i class="bi bi-grid-1x2-fill"></i>
                    <h2>Tes, Operasional, Feedback &amp; Talent Pool</h2>
                    <i class="bi wcd-sec__chev" :class="tutup.has('ekstra') ? 'bi-chevron-down' : 'bi-chevron-up'"></i>
                </button>
                <div v-show="!tutup.has('ekstra')" class="wcd-sec__bd" :class="{ 'wcd-basi': zona.khas.segar }">
                    <KeadaanPanel v-if="zona.khas.keadaan === 'memuat' && !zona.khas.data" keadaan="memuat" />
                    <KeadaanPanel v-else-if="zona.khas.keadaan === 'galat'" keadaan="galat"
                        :ket="zona.khas.pesan" @ulang="muatKhas()" />
                    <EkstraPanel v-else-if="zona.khas.data" :ekstra="zona.khas.data.ekstra" />
                </div>
            </section>
        </template>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, onUnmounted, nextTick } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { CFG } from './dashboardHelpers';
import KeadaanPanel from '../monitoring/KeadaanPanel.vue';
import KpiStrip from './panels/KpiStrip.vue';
import AntreanAksi from './panels/AntreanAksi.vue';
import FunnelPanel from './panels/FunnelPanel.vue';
import TrenPanel from './panels/TrenPanel.vue';
import KesehatanPanel from './panels/KesehatanPanel.vue';
import AgendaPanel from './panels/AgendaPanel.vue';
import EkstraPanel from './panels/EkstraPanel.vue';
import RekrutmenPanel from './tabs/RekrutmenPanel.vue';
import MagangPanel from './tabs/MagangPanel.vue';
import MtPanel from './tabs/MtPanel.vue';
import { useAutoRefresh } from '../../../../composables/useAutoRefresh';

const props = defineProps({
    tabs: { type: Array, default: () => [] },
    tabAwal: { type: String, default: null },
    ambang: { type: Object, default: () => ({ macetHari: 7, sorotHari: 2 }) },
    checkpoint: { type: Object, default: null },
});

const PERIODE = [
    { nilai: '7', label: '7 hari' },
    { nilai: '30', label: '30 hari' },
    { nilai: '90', label: '90 hari' },
    { nilai: 'all', label: 'Semua' },
];

/* ─────────────────── KEADAAN TAMPILAN ───────────────────
 *
 * TIDAK ADA localStorage di halaman ini. Yang perlu bertahan lintas muat
 * ulang disimpan di URL saja (`?k=` kategori, `?p=` periode): satu sumber
 * kebenaran, ikut terbawa saat tautannya dibagikan, dan tidak meninggalkan
 * jejak di peramban bersama — dashboard ini dibuka di komputer kantor yang
 * sering dipakai lebih dari satu admin, jadi "tab terakhir" milik orang
 * sebelumnya tidak boleh bocor ke orang berikutnya.
 *
 * Lipatan seksi dan interval auto-refresh SENGAJA tidak diawetkan: keduanya
 * kembali ke bawaan setiap kali halaman dibuka.
 */
const URL_TAB = 'k';
const URL_PERIODE = 'p';

const kueri = new URLSearchParams(window.location.search);

/** Tulis satu parameter ke bilah alamat tanpa memuat ulang halaman. */
function setKueri(kunci, nilai) {
    const u = new URL(window.location.href);
    u.searchParams.set(kunci, nilai);
    // replaceState, bukan navigasi Inertia: ini keadaan tampilan, bukan
    // halaman baru — memuat ulang seluruh shell untuk itu cuma pemborosan.
    window.history.replaceState({}, '', u);
}

const sahKode = (k) => props.tabs.some((t) => t.kode === k);
const dariUrl = kueri.get(URL_TAB);
const kategori = ref((sahKode(dariUrl) && dariUrl) || props.tabAwal);

const periodeUrl = kueri.get(URL_PERIODE);
const periode = ref(PERIODE.some((p) => p.nilai === periodeUrl) ? periodeUrl : '30');

const checkpoint = ref(props.checkpoint);

const tutup = ref(new Set());
const seksiAktif = ref('aksi');

const zona = reactive({
    ringkas: { keadaan: 'memuat', data: null, pesan: '', segar: false },
    analitik: { keadaan: 'memuat', data: null, pesan: '', segar: false },
    khas: { keadaan: 'memuat', data: null, pesan: '', segar: false },
});

const halaman = usePage();
/* auth.user memakai kunci `name` (IdentitasShell::pengguna), sedangkan
   careerAuth adalah isi sesi mentah yang memakai `nama`. Keduanya dicoba —
   bukan karena ragu, tapi karena sapaan tidak boleh jadi "Admin" generik hanya
   gara-gara satu dari dua sumber itu kosong. */
const namaAdmin = computed(
    () => halaman.props?.auth?.user?.name || halaman.props?.careerAuth?.nama || 'Admin',
);
const tabAktif = computed(() => props.tabs.find((t) => t.kode === kategori.value) || props.tabs[0] || null);
const aksen = computed(() => tabAktif.value?.warna || '#6366f1');

const sapaan = computed(() => {
    const j = new Date().getHours();
    if (j < 11) return 'Selamat pagi';
    if (j < 15) return 'Selamat siang';
    if (j < 19) return 'Selamat sore';
    return 'Selamat malam';
});

const labelPeriode = computed(() => PERIODE.find((p) => p.nilai === periode.value)?.label || '30 hari');

const totalAksi = computed(() => {
    const a = zona.ringkas.data?.aksi;
    if (!a) return 0;
    return Object.values(a).reduce((n, b) => n + (b.total || 0), 0);
});
const jmlAgenda = computed(() => zona.analitik.data?.agenda?.length || 0);

const khasJudul = computed(() => ({
    MT: 'Persaingan Antar-Posisi (MT)',
    MAGANG: 'Kampus, Kemitraan & Batch',
}[zona.khas.data?.bentuk] || 'Pemenuhan MPP & Posisi'));
const khasIkon = computed(() => ({
    MT: 'bi-diagram-3-fill',
    MAGANG: 'bi-building-fill',
}[zona.khas.data?.bentuk] || 'bi-clipboard-data-fill'));

const seksiTampil = computed(() => [
    { id: 'aksi', label: 'Butuh Aksi', ikon: 'bi-lightning-charge-fill', lencana: totalAksi.value || null },
    { id: 'funnel', label: 'Funnel', ikon: 'bi-funnel-fill' },
    { id: 'tren', label: 'Tren', ikon: 'bi-graph-up' },
    { id: 'program', label: 'Program', ikon: 'bi-heart-pulse-fill' },
    { id: 'agenda', label: 'Agenda', ikon: 'bi-calendar-week-fill', lencana: jmlAgenda.value || null },
    { id: 'khas', label: khasJudul.value.split(' ')[0], ikon: khasIkon.value },
    { id: 'ekstra', label: 'Ekstra', ikon: 'bi-grid-1x2-fill' },
]);

/* ─────────────────── PEMUATAN ─────────────────── */

/**
 * Satu jalur muat untuk ketiga zona.
 *
 * `ulang = true` (auto-refresh / tombol) TIDAK mengosongkan data lama: render
 * sebelumnya ditahan dengan opasitas turun (.wcd-basi) supaya tidak ada
 * kedipan skeleton dan tata letak tidak melompat. Kalau permintaan ulangnya
 * gagal, data lama TETAP ditampilkan — mengganti tampilan yang masih benar
 * dengan layar galat justru membuang informasi yang sudah ada di layar.
 */
async function muat(nama, jalur, ulang = false) {
    const z = zona[nama];
    if (!ulang || !z.data) z.keadaan = 'memuat';
    z.segar = true;
    try {
        const { data } = await axios.get(jalur, {
            params: { kategori: kategori.value, periode: periode.value },
            ...CFG,
        });
        z.data = data.result || null;
        z.keadaan = 'siap';
        z.pesan = '';
        if (z.data?.checkpoint) checkpoint.value = z.data.checkpoint;
    } catch (e) {
        z.pesan = e?.response?.data?.message || 'Sambungan ke server gagal.';
        if (!z.data) z.keadaan = 'galat';
    } finally {
        z.segar = false;
    }
}

const muatRingkas = (u = false) => muat('ringkas', '/api/v1/karir/dashboard/ringkas', u);
const muatAnalitik = (u = false) => muat('analitik', '/api/v1/karir/dashboard/analitik', u);
const muatKhas = (u = false) => muat('khas', '/api/v1/karir/dashboard/khas', u);

/** Ringkas didahulukan; dua sisanya menyusul bersamaan. */
async function muatSemua(ulang = false) {
    if (!kategori.value) return;
    await muatRingkas(ulang);
    await Promise.all([muatAnalitik(ulang), muatKhas(ulang)]);
}

/* Auto-refresh selalu mulai dari Mati. Menghidupkannya diam-diam karena
   kunjungan sebelumnya berarti halaman menembak server sendiri tanpa ada yang
   memintanya di sesi ini. */
const { intervalSec, busy: sibuk, refreshNow } = useAutoRefresh(() => muatSemua(true), { initial: 0 });

function setInterval_(n) {
    intervalSec.value = n;
}

function setPeriode(p) {
    if (periode.value === p) return;
    periode.value = p;
    setKueri(URL_PERIODE, p);
    // Periode hanya menyentuh KPI "baru" + tren → cukup dua endpoint itu.
    muatRingkas(true);
    muatAnalitik(true);
}

function pilihTab(kode) {
    if (kategori.value === kode) return;
    kategori.value = kode;
    setKueri(URL_TAB, kode);

    zona.ringkas.data = null;
    zona.analitik.data = null;
    zona.khas.data = null;
    muatSemua();
}

function lipat(id) {
    // Set baru (bukan mutasi) supaya v-show ikut ter-render ulang.
    const s = new Set(tutup.value);
    s.has(id) ? s.delete(id) : s.add(id);
    tutup.value = s;
}

async function lompat(id) {
    if (tutup.value.has(id)) lipat(id);
    await nextTick();
    document.getElementById(`wcd-${id}`)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

/* Quick-nav menyala mengikuti seksi yang sedang terlihat. */
let pengamat = null;
onMounted(() => {
    muatSemua();

    pengamat = new IntersectionObserver((entri) => {
        const terlihat = entri.filter((e) => e.isIntersecting)
            .sort((a, b) => a.boundingClientRect.top - b.boundingClientRect.top)[0];
        if (terlihat) seksiAktif.value = terlihat.target.id.replace('wcd-', '');
    }, { rootMargin: '-96px 0px -60% 0px' });

    nextTick(() => {
        ['aksi', 'funnel', 'tren', 'program', 'agenda', 'khas', 'ekstra'].forEach((id) => {
            const el = document.getElementById(`wcd-${id}`);
            if (el) pengamat.observe(el);
        });
    });
});
onUnmounted(() => pengamat?.disconnect());
</script>

<!--
  CHROME BERSAMA, SENGAJA TIDAK SCOPED.

  Kartu, kartu KPI, kepala seksi, tabel, dan lencana dipakai oleh SEBELAS
  komponen panel di bawah folder ini. Kalau tiap panel mendefinisikan sendiri
  gaya kartunya (yang sudah terjadi di feedback-dashboard DAN monitoring —
  `wca-kpi-card` kini ada dua salinan scoped yang tak bisa dipakai lintas
  halaman), salinan ketiga & keempat cuma soal waktu. Jadi chrome-nya
  didefinisikan SEKALI di sini dengan awalan `wcd-`, dan panel hanya memakai
  kelasnya. Gaya yang benar-benar khas satu panel tetap scoped di panel itu.
-->
<style>
.wcd {
    --wcd-line: #e8edf5;
    --wcd-ink: #0f172a;
    --wcd-ink2: #475569;
    --wcd-redup: #94a3b8;
    --wcd-bg: #f8fafc;
}

/* ══════════ KERAPATAN ══════════
   Halaman ini memuat tujuh seksi. Setiap 4px bantalan yang dipakai bersama
   kepala, tab, quick-nav, dan tujuh kepala seksi berlipat jadi puluhan piksel
   gulir yang tidak membawa informasi apa pun. Angka di bawah sudah dirapatkan
   satu tingkat dari bawaan yang longgar — cukup untuk memuat lebih banyak
   dalam satu layar, masih cukup lapang untuk sasaran sentuh 32px+. */

/* ══════════ KEPALA ══════════ */
.wcd-head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 14px;
    flex-wrap: wrap;
    padding: 13px 18px;
    margin-bottom: 11px;
    background: #fff;
    border: 1px solid var(--wcd-line);
    border-radius: 20px;
    box-shadow: 0 4px 20px rgba(15, 23, 42, 0.04);
}
.wcd-head__intro { display: flex; align-items: center; gap: 12px; min-width: 0; }
.wcd-head__lencana {
    display: grid;
    place-items: center;
    width: 40px;
    height: 40px;
    flex: none;
    border-radius: 13px;
    font-size: 18px;
    color: #fff;
    background: linear-gradient(135deg, var(--aksen), color-mix(in srgb, var(--aksen) 62%, #0f172a));
    box-shadow: 0 8px 20px color-mix(in srgb, var(--aksen) 32%, transparent);
}
.wcd-head__intro h1 { margin: 0; font-size: 1.05rem; font-weight: 900; color: var(--wcd-ink); letter-spacing: -0.01em; }
.wcd-head__intro p { margin: 1px 0 0; font-size: 0.77rem; color: var(--wcd-ink2); line-height: 1.45; }
.wcd-head__alat { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }

.wcd-cp {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 0.75rem;
    color: var(--wcd-ink2);
    white-space: nowrap;
}
.wcd-cp b { color: var(--wcd-ink); font-weight: 800; }
.wcd-spin { display: inline-block; animation: wcdSpin 900ms linear infinite; }
@keyframes wcdSpin { to { transform: rotate(360deg); } }
@media (prefers-reduced-motion: reduce) { .wcd-spin { animation: none; } }

/* Kendali tersegmen — dipakai auto-refresh & pemilih periode. */
.wcd-seg {
    display: inline-flex;
    padding: 3px;
    gap: 2px;
    background: var(--wcd-bg);
    border: 1px solid var(--wcd-line);
    border-radius: 11px;
}
.wcd-seg button {
    border: 0;
    background: transparent;
    padding: 5px 11px;
    border-radius: 8px;
    font: inherit;
    font-size: 0.74rem;
    font-weight: 800;
    color: var(--wcd-ink2);
    cursor: pointer;
    transition: background 0.15s, color 0.15s;
}
.wcd-seg button:hover { background: #eef2f7; }
.wcd-seg button.is-on { background: #fff; color: #4f46e5; box-shadow: 0 1px 4px rgba(15, 23, 42, 0.08); }

/* ══════════ TAB KATEGORI ══════════ */
.wcd-tabs {
    display: flex;
    gap: 8px;
    margin-bottom: 10px;
    overflow-x: auto;
    padding-bottom: 2px;
    scrollbar-width: thin;
}
.wcd-tab {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    flex: none;
    padding: 9px 15px;
    border: 1px solid var(--wcd-line);
    border-radius: 13px;
    background: #fff;
    font: inherit;
    font-size: 0.81rem;
    font-weight: 800;
    color: var(--wcd-ink2);
    cursor: pointer;
    transition: transform 0.15s, box-shadow 0.15s, border-color 0.15s, color 0.15s;
}
.wcd-tab .bi { font-size: 15px; color: var(--aksen); }
.wcd-tab:hover { transform: translateY(-1px); box-shadow: 0 6px 16px rgba(15, 23, 42, 0.06); }
.wcd-tab.is-on {
    color: #fff;
    border-color: transparent;
    background: linear-gradient(135deg, var(--aksen), color-mix(in srgb, var(--aksen) 68%, #0f172a));
    box-shadow: 0 10px 22px color-mix(in srgb, var(--aksen) 30%, transparent);
}
.wcd-tab.is-on .bi { color: #fff; }

.wcd-solo {
    margin: 0 0 14px;
    padding: 10px 14px;
    background: #fff;
    border: 1px solid var(--wcd-line);
    border-radius: 12px;
    font-size: 0.78rem;
    color: var(--wcd-ink2);
}
.wcd-solo b { color: var(--wcd-ink); }

/* ══════════ QUICK-NAV LEKAT ══════════ */
.wcd-jump {
    position: sticky;
    top: 0;
    z-index: 20;
    display: flex;
    gap: 6px;
    padding: 7px 0;
    margin-bottom: 10px;
    overflow-x: auto;
    background: linear-gradient(#f6f7fb 72%, rgba(246, 247, 251, 0));
    backdrop-filter: blur(6px);
    scrollbar-width: none;
}
.wcd-jump::-webkit-scrollbar { display: none; }
.wcd-jump a {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    flex: none;
    padding: 6px 12px;
    border-radius: 999px;
    border: 1px solid var(--wcd-line);
    background: #fff;
    font-size: 0.73rem;
    font-weight: 800;
    color: var(--wcd-ink2);
    text-decoration: none;
    transition: color 0.15s, border-color 0.15s, background 0.15s;
}
.wcd-jump a:hover { color: #4f46e5; border-color: #c7d2fe; }
.wcd-jump a.is-on { color: #fff; background: #4f46e5; border-color: #4f46e5; }
.wcd-jump a .bi { font-size: 13px; }
.wcd-jump a em {
    font-style: normal;
    min-width: 18px;
    padding: 0 5px;
    border-radius: 999px;
    background: #eef2f7;
    color: #4f46e5;
    font-size: 0.68rem;
    text-align: center;
}
.wcd-jump a.is-on em { background: rgba(255, 255, 255, 0.24); color: #fff; }

/* ══════════ SEKSI ══════════ */
.wcd-sec {
    margin-bottom: 11px;
    background: #fff;
    border: 1px solid var(--wcd-line);
    border-radius: 18px;
    box-shadow: 0 2px 12px rgba(15, 23, 42, 0.03);
    /* Sepadan dengan tinggi quick-nav lekat, supaya judul seksi tidak
       tersembunyi di baliknya setelah "lompat ke seksi". */
    scroll-margin-top: 56px;
    overflow: hidden;
}
.wcd-sec__hd {
    display: flex;
    align-items: center;
    gap: 10px;
    width: 100%;
    padding: 11px 17px;
    border: 0;
    background: transparent;
    font: inherit;
    text-align: left;
    cursor: pointer;
}
.wcd-sec__hd:hover { background: #fcfdff; }
.wcd-sec__hd > .bi:first-child { font-size: 15px; color: #6366f1; }
.wcd-sec__hd h2 { margin: 0; flex: 1; font-size: 0.92rem; font-weight: 900; color: var(--wcd-ink); }
.wcd-sec__jml {
    min-width: 22px;
    padding: 2px 8px;
    border-radius: 999px;
    background: #eef2ff;
    color: #4338ca;
    font-size: 0.72rem;
    font-weight: 900;
    text-align: center;
}
.wcd-sec__ket { font-size: 0.73rem; color: var(--wcd-redup); font-weight: 700; }
.wcd-sec__chev { font-size: 13px; color: var(--wcd-redup); }
.wcd-sec__bd { padding: 0 17px 15px; }

/* Muat ulang: render lama ditahan, tidak dikedipkan jadi skeleton. */
.wcd-basi { opacity: 0.55; transition: opacity 0.2s; pointer-events: none; }

/* ══════════ KARTU & KISI ══════════ */
.wcd-card {
    background: #fff;
    border: 1px solid var(--wcd-line);
    border-radius: 15px;
    padding: 13px;
}
.wcd-card--datar { background: var(--wcd-bg); border-color: transparent; }
.wcd-card__hd {
    display: flex;
    align-items: center;
    gap: 8px;
    margin: 0 0 10px;
    font-size: 0.8rem;
    font-weight: 900;
    color: var(--wcd-ink);
}
.wcd-card__hd .bi { color: #6366f1; }
.wcd-card__hd small { margin-left: auto; font-weight: 700; color: var(--wcd-redup); font-size: 0.72rem; }

.wcd-grid { display: grid; gap: 11px; }
.wcd-grid--2 { grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); }
.wcd-grid--3 { grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); }
.wcd-grid--4 { grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); }

/* ══════════ KARTU ANGKA (KPI & stat tile) ══════════ */
/* Dipakai KpiStrip, EkstraPanel, dan ketiga panel khas — karena itu di sini,
   bukan discoped di salah satunya. Warna nada masuk lewat --tone, jadi tidak
   ada deretan kelas .tone-xxx yang harus ditambah tiap ada nada baru. */
.wcd-kpi { display: grid; grid-template-columns: repeat(auto-fit, minmax(168px, 1fr)); gap: 10px; }
.wcd-kpi--rapat { grid-template-columns: repeat(auto-fit, minmax(146px, 1fr)); }

.wcd-stat {
    display: block;
    width: 100%;
    padding: 11px 13px;
    background: #fff;
    border: 1px solid var(--wcd-line);
    border-radius: 15px;
    text-align: left;
    text-decoration: none;
    font: inherit;
    transition: transform 0.15s, box-shadow 0.15s, border-color 0.15s;
}
.wcd-stat.is-klik { cursor: pointer; }
.wcd-stat.is-klik:hover {
    transform: translateY(-2px);
    border-color: color-mix(in srgb, var(--tone, #6366f1) 40%, var(--wcd-line));
    box-shadow: 0 8px 20px rgba(15, 23, 42, 0.07);
}
.wcd-stat__top { display: flex; align-items: center; gap: 8px; margin-bottom: 7px; }
.wcd-stat__ic {
    display: grid;
    place-items: center;
    width: 27px;
    height: 27px;
    flex: none;
    border-radius: 9px;
    font-size: 13px;
    color: var(--tone, #6366f1);
    background: color-mix(in srgb, var(--tone, #6366f1) 13%, #fff);
}
/* Angka besar berdiri sendiri → figur proporsional, BUKAN tabular-nums
   (angka selebar-sama membuat "121" terlihat renggang di ukuran display). */
.wcd-stat__num { font-size: 1.42rem; font-weight: 900; line-height: 1; color: var(--wcd-ink); }
.wcd-stat__num small { font-size: 0.78rem; font-weight: 800; color: var(--wcd-redup); }
.wcd-stat__lbl { margin-top: 4px; font-size: 0.73rem; font-weight: 700; color: var(--wcd-ink2); }
.wcd-stat__ket { margin-top: 2px; font-size: 0.7rem; color: var(--wcd-redup); }
.wcd-stat__delta { margin-left: auto; display: inline-flex; align-items: center; gap: 3px; font-size: 0.7rem; font-weight: 800; }

/* ══════════ TABEL ══════════ */
/* Tiap tabel menggulir di wadahnya sendiri — badan halaman tidak pernah
   menggulir mendatar, seberapa lebar pun matriksnya. */
.wcd-tw { overflow-x: auto; border: 1px solid var(--wcd-line); border-radius: 14px; }
.wcd-tbl { width: 100%; border-collapse: collapse; font-size: 0.78rem; }
.wcd-tbl th,
.wcd-tbl td { padding: 9px 12px; text-align: left; border-bottom: 1px solid var(--wcd-line); white-space: nowrap; }
.wcd-tbl th {
    background: var(--wcd-bg);
    font-size: 0.7rem;
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--wcd-ink2);
    position: sticky;
    top: 0;
}
.wcd-tbl tbody tr:last-child td { border-bottom: 0; }
.wcd-tbl tbody tr:hover td { background: #fcfdff; }
/* Angka yang harus lurus vertikal saja yang pakai tabular-nums — bukan angka
   besar berdiri sendiri di kartu KPI (di sana justru terlihat renggang). */
.wcd-num { text-align: right; font-variant-numeric: tabular-nums; }
.wcd-tbl__utama { font-weight: 800; color: var(--wcd-ink); white-space: normal; min-width: 180px; }
.wcd-tbl__sub { display: block; font-weight: 600; color: var(--wcd-redup); font-size: 0.72rem; }

/* ══════════ LENCANA & METER ══════════ */
.wcd-lb {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 2px 9px;
    border-radius: 999px;
    font-size: 0.7rem;
    font-weight: 800;
    background: #eef2f7;
    color: var(--wcd-ink2);
    white-space: nowrap;
}
.wcd-lb .bi { font-size: 11px; }

.wcd-meter { height: 6px; border-radius: 999px; background: #eef2f7; overflow: hidden; min-width: 70px; }
.wcd-meter span { display: block; height: 100%; border-radius: 999px; background: #6366f1; }

@media (max-width: 700px) {
    .wcd-head { padding: 14px 16px; }
    .wcd-head__alat { width: 100%; justify-content: space-between; }
    .wcd-sec__bd { padding: 0 14px 16px; }
    .wcd-sec__hd { padding: 13px 14px; }
    .wcd-sec__hd h2 { font-size: 0.86rem; }
}
</style>
