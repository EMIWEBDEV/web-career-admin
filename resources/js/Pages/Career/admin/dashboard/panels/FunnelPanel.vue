<!--
  ZONA C — FUNNEL & KONVERSI.

  Dikelompokkan PER ALUR, bukan per kategori. Program dalam satu kategori boleh
  memakai alur berbeda, jadi menjumlahkan "tahap 3" dari dua alur menghasilkan
  kolom bernama satu hal yang isinya campuran dua hal. Pemilih alur di atas
  memilih salah satu; bawaannya alur dengan pelamar terbanyak.

  ── SATU TAHAP = SATU BARIS ──────────────────────────────────────────────
  Alur seleksi bisa berisi sepuluh tahap lebih. Versi sebelumnya memberi tiap
  tahap tiga baris (kepala, batang, rincian) ≈ 90px, jadi sepuluh tahap sudah
  menghabiskan satu layar penuh sendirian. Sekarang tiap tahap tepat satu baris
  ±30px: nomor, label, batang, capaian, konversi — semuanya sejajar dalam satu
  kisi, jadi kolomnya bisa dibaca menurun tanpa mata melompat.

  Rinciannya TIDAK dibuang, hanya dilipat: baris bisa diklik untuk membuka
  pecahan di sini/diterima/gugur/talent, angkanya juga ada di tooltip batang
  dan di padanan tabel. Yang hilang cuma ruang kosongnya.

  ── DUA NADA DALAM SATU BATANG ───────────────────────────────────────────
  Panjang batang = capai/capai-tahap-pertama (tahap pertama selalu penuh).
  Batang itu dibelah tepat di titik yang benar secara aritmetika:

      capai(i) = lanjut(i) + berhenti(i),  dan  lanjut(i) = capai(i+1)

  Bagian pekat = yang lanjut, bagian pudar = yang berhenti di tahap ini. Karena
  keduanya satu hue, batang tetap membaca sebagai SATU besaran yang terbelah —
  bukan dua seri yang bersaing. Ini memindahkan isi baris "rincian" ke dalam
  batang tanpa menambah tinggi sama sekali.

  Satu-satunya kanal warna yang berbeda dipakai untuk hal yang layak: tahap
  dengan PENYEMPITAN TERBESAR, yang juga dinamai dengan teks dan ikon di atas —
  tidak pernah hanya lewat warna.
-->
<template>
    <div v-if="!funnel.length">
        <KeadaanPanel keadaan="kosong" ikon="bi-funnel"
            teks="Belum ada funnel untuk kategori ini"
            ket="Funnel terbentuk dari tahap alur seleksi program. Pastikan programnya sudah punya alur." />
    </div>

    <div v-else class="fn">
        <!-- Kepala: pemilih alur, cakupan, dan penyempitan terbesar dalam SATU
             baris yang membungkus — dulu tiga blok bertumpuk ±110px. -->
        <div class="fn-atas">
            <div v-if="funnel.length > 1" class="wcd-seg fn-alur">
                <button v-for="(f, i) in funnel" :key="f.alurId" type="button"
                    :class="{ 'is-on': idx === i }" :title="f.programNama.join(', ')"
                    @click="idx = i">
                    {{ f.alur }} <em>{{ angka(f.pelamar) }}</em>
                </button>
            </div>

            <span class="fn-cakup" :title="aktif.programNama.join(', ')">
                <i class="bi bi-diagram-2"></i>
                {{ aktif.program }} program · <b>{{ angka(aktif.pelamar) }}</b> pelamar
            </span>

            <!-- Status → ikon + teks, bukan warna sendirian. -->
            <span v-if="aktif.penyempitan" class="fn-sempit"
                :title="`${angka(aktif.penyempitan.hilang)} kandidat berhenti di titik ini`">
                <i class="bi bi-arrow-down-right-circle-fill"></i>
                Penyempitan terbesar <b>{{ aktif.penyempitan.dari }} → {{ aktif.penyempitan.ke }}</b>
                <em>{{ aktif.penyempitan.persen }}% lanjut · −{{ angka(aktif.penyempitan.hilang) }}</em>
            </span>

            <span class="fn-legenda">
                <i class="fn-swatch is-lanjut"></i> lanjut
                <i class="fn-swatch is-henti"></i> berhenti di tahap
            </span>
        </div>

        <!-- Daftar tahap: satu baris per tahap. -->
        <ul class="fn-list">
            <li v-for="t in aktif.tahap" :key="t.urutan">
                <button type="button" class="fn-row" :class="{ 'is-sempit': sorot(t), 'is-buka': buka.has(t.urutan) }"
                    :aria-expanded="buka.has(t.urutan)" @click="alih(t.urutan)">
                    <span class="fn-ur">{{ t.urutan }}</span>

                    <span class="fn-lbl" :title="t.label">
                        {{ t.label }}
                        <i v-if="t.provider === 'THIRD_PARTY'" class="bi bi-box-arrow-up-right"
                            title="Tahap oleh penyedia tes pihak ketiga"></i>
                    </span>

                    <span class="fn-rel" :title="tooltip(t)">
                        <span class="fn-bar" :style="{ width: lebar(t) + '%' }">
                            <span class="fn-bar__lanjut" :style="{ width: persenLanjut(t) + '%' }"></span>
                        </span>
                    </span>

                    <span class="fn-capai">{{ angka(t.capai) }}</span>

                    <span class="fn-konv">
                        <span v-if="t.konversi !== null" class="wcd-lb" :style="nadaKonversi(t)">
                            <i class="bi" :class="sorot(t) ? 'bi-arrow-down-right' : 'bi-arrow-right-short'"></i>
                            {{ t.konversi }}%
                        </span>
                        <span v-else class="fn-konv__nol">awal</span>
                    </span>

                    <i class="bi fn-chev" :class="buka.has(t.urutan) ? 'bi-chevron-up' : 'bi-chevron-down'"></i>
                </button>

                <!-- Rincian: dilipat, tidak dibuang. -->
                <div v-if="buka.has(t.urutan)" class="fn-rinci">
                    <span><i class="bi bi-arrow-right-circle"></i> {{ angka(lanjut(t)) }} lanjut ke tahap berikutnya</span>
                    <span v-if="t.aktif"><i class="bi bi-person-walking"></i> {{ angka(t.aktif) }} masih di sini</span>
                    <span v-if="t.lulus"><i class="bi bi-patch-check"></i> {{ angka(t.lulus) }} diterima</span>
                    <span v-if="t.gugur"><i class="bi bi-x-circle"></i> {{ angka(t.gugur) }} gugur</span>
                    <span v-if="t.talent"><i class="bi bi-bookmark-star"></i> {{ angka(t.talent) }} talent pool</span>
                    <span v-if="!berhenti(t)" class="is-nol">belum ada yang berhenti di tahap ini</span>
                </div>
            </li>
        </ul>

        <!-- Padanan tabel: tiap angka tetap bisa dibaca tanpa mengandalkan
             panjang batang atau warna. -->
        <details class="fn-tabel">
            <summary>Lihat sebagai tabel</summary>
            <div class="wcd-tw">
                <table class="wcd-tbl">
                    <thead>
                        <tr>
                            <th>#</th><th>Tahap</th><th class="wcd-num">Capai</th><th class="wcd-num">Konversi</th>
                            <th class="wcd-num">Lanjut</th><th class="wcd-num">Di sini</th>
                            <th class="wcd-num">Diterima</th><th class="wcd-num">Gugur</th><th class="wcd-num">Talent</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="t in aktif.tahap" :key="t.urutan">
                            <td>{{ t.urutan }}</td>
                            <td class="wcd-tbl__utama">{{ t.label }}</td>
                            <td class="wcd-num">{{ angka(t.capai) }}</td>
                            <td class="wcd-num">{{ t.konversi === null ? '—' : t.konversi + '%' }}</td>
                            <td class="wcd-num">{{ angka(lanjut(t)) }}</td>
                            <td class="wcd-num">{{ angka(t.aktif) }}</td>
                            <td class="wcd-num">{{ angka(t.lulus) }}</td>
                            <td class="wcd-num">{{ angka(t.gugur) }}</td>
                            <td class="wcd-num">{{ angka(t.talent) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </details>
    </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import { angka, STATUS, INK } from '../dashboardHelpers';
import KeadaanPanel from '../../monitoring/KeadaanPanel.vue';

const props = defineProps({ funnel: { type: Array, default: () => [] } });

const idx = ref(0);
const buka = ref(new Set());

const aktif = computed(() => props.funnel[idx.value] || props.funnel[0] || { tahap: [], programNama: [] });
const dasar = computed(() => aktif.value.tahap?.[0]?.capai || 0);

// Ganti alur = ganti daftar tahap; baris terbuka milik alur lama tidak ada
// artinya di alur baru.
watch(idx, () => { buka.value = new Set(); });

/** Yang berhenti di tahap ini (apa pun sebabnya). */
function berhenti(t) {
    return (t.aktif || 0) + (t.lulus || 0) + (t.gugur || 0) + (t.talent || 0);
}

/** Yang meneruskan ke tahap berikutnya = capai(i) − berhenti(i) = capai(i+1). */
function lanjut(t) {
    return Math.max(0, (t.capai || 0) - berhenti(t));
}

function lebar(t) {
    if (!dasar.value) return 0;
    // Minimum 1.5% supaya tahap bernilai kecil masih punya bentuk yang terlihat,
    // tapi tidak dibulatkan ke atas sampai membohongi perbandingannya.
    return Math.max(t.capai > 0 ? 1.5 : 0, (t.capai / dasar.value) * 100);
}

/** Porsi pekat DI DALAM batang — relatif terhadap batang itu, bukan ke rel. */
function persenLanjut(t) {
    if (!t.capai) return 0;
    return (lanjut(t) / t.capai) * 100;
}

function tooltip(t) {
    const b = [`${angka(t.capai)} mencapai tahap ini`];
    if (lanjut(t)) b.push(`${angka(lanjut(t))} lanjut`);
    if (t.aktif) b.push(`${angka(t.aktif)} masih di sini`);
    if (t.lulus) b.push(`${angka(t.lulus)} diterima`);
    if (t.gugur) b.push(`${angka(t.gugur)} gugur`);
    if (t.talent) b.push(`${angka(t.talent)} talent pool`);
    return `${t.urutan}. ${t.label} — ${b.join(' · ')}`;
}

function alih(u) {
    const s = new Set(buka.value);
    s.has(u) ? s.delete(u) : s.add(u);
    buka.value = s;
}

function sorot(t) {
    const p = aktif.value.penyempitan;
    return !!p && t.label === p.ke && t.konversi === p.persen;
}

function nadaKonversi(t) {
    if (sorot(t)) return { background: `color-mix(in srgb, ${STATUS.critical.warna} 12%, #fff)`, color: STATUS.critical.warna };
    if (t.konversi >= 80) return { background: `color-mix(in srgb, ${STATUS.good.warna} 12%, #fff)`, color: STATUS.good.warna };
    return { background: '#eef2f7', color: INK.kedua };
}
</script>

<style scoped>
/* ══════════ KEPALA ══════════ */
.fn-atas {
    display: flex;
    align-items: center;
    gap: 8px 14px;
    flex-wrap: wrap;
    margin-bottom: 10px;
}
.fn-alur { flex-wrap: wrap; }
.fn-alur em { font-style: normal; opacity: 0.65; }

.fn-cakup { font-size: 0.73rem; color: #64748b; white-space: nowrap; }
.fn-cakup .bi { color: #94a3b8; margin-right: 3px; }
.fn-cakup b { color: #334155; font-weight: 800; }

.fn-sempit {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 10px;
    border-radius: 999px;
    background: #fff7ed;
    border: 1px solid #fed7aa;
    font-size: 0.72rem;
    color: #9a3412;
}
.fn-sempit > .bi { font-size: 13px; color: #ec835a; flex: none; }
.fn-sempit b { font-weight: 900; }
.fn-sempit em { font-style: normal; font-weight: 800; opacity: 0.8; }

.fn-legenda {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    margin-left: auto;
    font-size: 0.7rem;
    color: #94a3b8;
    white-space: nowrap;
}
.fn-swatch { display: inline-block; width: 11px; height: 8px; border-radius: 3px; }
.fn-swatch.is-lanjut { background: #6366f1; }
.fn-swatch.is-henti { background: #c7d2fe; margin-left: 6px; }

/* ══════════ BARIS TAHAP ══════════ */
/* Satu kisi dipakai semua baris → kolom lurus menurun, mata tidak melompat.
   Batang mengambil sisa ruang, kolom angka lebarnya tetap supaya tidak
   bergoyang saat angkanya berubah panjang. */
.fn-list { list-style: none; margin: 0; padding: 0; border: 1px solid #eef2f7; border-radius: 13px; overflow: hidden; }
.fn-list > li + li { border-top: 1px solid #f1f5f9; }

.fn-row {
    display: grid;
    grid-template-columns: 20px minmax(96px, 1.15fr) minmax(80px, 2fr) 46px 62px 14px;
    align-items: center;
    gap: 9px;
    width: 100%;
    padding: 5px 11px;
    border: 0;
    border-left: 3px solid transparent;
    background: transparent;
    font: inherit;
    text-align: left;
    cursor: pointer;
    transition: background 0.14s;
}
.fn-row:hover { background: #fbfcff; }
.fn-row.is-buka { background: #f8fafc; }
/* Tahap penyempitan ditandai garis tepi, bukan latar penuh — penanda yang
   cukup kuat untuk ditemukan sekali lihat tanpa memberati seluruh baris. */
.fn-row.is-sempit { border-left-color: #ec835a; }

.fn-ur {
    display: grid;
    place-items: center;
    width: 20px;
    height: 20px;
    border-radius: 6px;
    background: #eef2ff;
    color: #4338ca;
    font-size: 0.66rem;
    font-weight: 900;
}
.fn-lbl {
    font-size: 0.76rem;
    font-weight: 800;
    color: #0f172a;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.fn-lbl .bi { font-size: 10px; color: #94a3b8; margin-left: 3px; }

/* Batang tipis, ujung membulat 4px, menempel ke garis dasar kiri. */
.fn-rel { display: block; height: 8px; border-radius: 999px; background: #f1f5f9; overflow: hidden; }
.fn-bar {
    display: block;
    height: 100%;
    border-radius: 0 4px 4px 0;
    background: #c7d2fe;                 /* porsi yang berhenti di tahap ini */
    transition: width 0.35s cubic-bezier(0.16, 1, 0.3, 1);
}
.fn-bar__lanjut {
    display: block;
    height: 100%;
    border-radius: 0 4px 4px 0;
    background: #6366f1;                 /* porsi yang lanjut */
    transition: width 0.35s cubic-bezier(0.16, 1, 0.3, 1);
}
.is-sempit .fn-bar { background: #fbd4c2; }

.fn-capai { font-size: 0.78rem; font-weight: 900; color: #0f172a; font-variant-numeric: tabular-nums; text-align: right; }
.fn-konv { display: flex; justify-content: flex-end; }
.fn-konv__nol { font-size: 0.68rem; color: #cbd5e1; font-weight: 700; }
.fn-chev { font-size: 10px; color: #cbd5e1; }

/* ══════════ RINCIAN (dilipat) ══════════ */
.fn-rinci {
    display: flex;
    flex-wrap: wrap;
    gap: 6px 14px;
    padding: 2px 14px 9px 46px;
    background: #f8fafc;
    font-size: 0.71rem;
    color: #64748b;
}
.fn-rinci .bi { font-size: 11px; color: #94a3b8; margin-right: 2px; }
.fn-rinci .is-nol { color: #cbd5e1; font-style: italic; }

.fn-tabel { margin-top: 12px; }
.fn-tabel summary {
    cursor: pointer;
    font-size: 0.73rem;
    font-weight: 800;
    color: #4f46e5;
    padding: 4px 0;
}
.fn-tabel .wcd-tw { margin-top: 8px; }

/* Di layar sempit label dan batang tidak muat berdampingan: label naik ke
   baris sendiri, batang + angka tetap satu baris di bawahnya. Tetap jauh
   lebih rapat daripada tiga baris per tahap. */
@media (max-width: 640px) {
    .fn-row {
        grid-template-columns: 20px 1fr 44px 58px 14px;
        row-gap: 5px;
        padding: 7px 10px;
    }
    .fn-lbl { grid-column: 2 / -1; }
    .fn-rel { grid-column: 2; }
    .fn-legenda { margin-left: 0; }
    .fn-rinci { padding-left: 14px; }
}

@media (prefers-reduced-motion: reduce) {
    .fn-bar,
    .fn-bar__lanjut { transition: none; }
}
</style>
