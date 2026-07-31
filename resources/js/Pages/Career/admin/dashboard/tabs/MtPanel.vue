<!--
  ZONA D — KHAS MT: PERSAINGAN ANTAR-POSISI.

  Inilah yang tidak terlihat di halaman mana pun sekarang. Satu program MT berisi
  banyak posisi dengan kursi masing-masing, tapi funnel program hanya
  memperlihatkan totalnya — jadi posisi yang kehabisan kandidat di tahap awal
  tenggelam di balik posisi lain yang ramai.

  MATRIKS POSISI × TAHAP menjawabnya: satu baris per posisi, satu kolom per
  tahap, isi sel = berapa orang berhenti/berada di sana. Warna sel memakai ramp
  SATU HUE (terang → gelap) karena yang dikodekan adalah magnitudo, dan angkanya
  tetap tertulis di dalam sel — jadi nilainya tidak pernah hanya bisa dibaca
  dari warna. Sel bernilai 0 memakai latar surface, BUKAN langkah teringan ramp,
  supaya "tidak ada orang di sini" tidak terbaca "ada sedikit".

  Ramp #9aa8fb→#4338ca lolos pemeriksaan ordinal (monoton, jarak ΔL ≥ 0.06,
  ujung terang 2.24:1 vs surface putih).
-->
<template>
    <div v-if="!khas.matriks.length">
        <KeadaanPanel keadaan="kosong" ikon="bi-diagram-3"
            teks="Belum ada posisi pada program MT"
            ket="Posisi MT ditambahkan lewat MPP di menu Program Kegiatan." />
    </div>

    <div v-else>
        <!-- Ringkasan persaingan -->
        <div class="wcd-grid wcd-grid--3" style="margin-bottom: 16px">
            <div class="wcd-stat" :style="{ '--tone': '#d97706' }">
                <div class="wcd-stat__top"><span class="wcd-stat__ic"><i class="bi bi-diagram-3-fill"></i></span></div>
                <div class="wcd-stat__num">{{ angka(khas.matriks.length) }}</div>
                <div class="wcd-stat__lbl">Posisi diperebutkan</div>
                <div class="wcd-stat__ket">{{ angka(totalKuota) }} kursi · {{ angka(totalPelamar) }} pelamar</div>
            </div>
            <div class="wcd-stat" :style="{ '--tone': terlaris ? '#0ca30c' : '#94a3b8' }">
                <div class="wcd-stat__top"><span class="wcd-stat__ic"><i class="bi bi-fire"></i></span></div>
                <div class="wcd-stat__num">{{ terlaris ? desimal(terlaris.rasio, 1) : '—' }}<small v-if="terlaris">×</small></div>
                <div class="wcd-stat__lbl">Paling diminati</div>
                <div class="wcd-stat__ket">{{ terlaris ? terlaris.posisi : 'rasio belum bisa dihitung' }}</div>
            </div>
            <div class="wcd-stat" :style="{ '--tone': sepi.length ? STATUS.critical.warna : '#0ca30c' }">
                <div class="wcd-stat__top"><span class="wcd-stat__ic"><i class="bi bi-person-dash"></i></span></div>
                <div class="wcd-stat__num">{{ angka(sepi.length) }}</div>
                <div class="wcd-stat__lbl">Posisi sepi</div>
                <div class="wcd-stat__ket">pelamar lebih sedikit dari kursinya</div>
            </div>
        </div>

        <p v-if="sepi.length" class="mt-ingat">
            <i class="bi" :class="STATUS.critical.ikon"></i>
            <span>
                <b>{{ sepi.map((s) => s.posisi).slice(0, 3).join(', ') }}<template v-if="sepi.length > 3"> +{{ sepi.length - 3 }} lain</template></b>
                punya pelamar lebih sedikit daripada kursinya — kursi itu tidak akan penuh
                sekalipun semua pelamarnya lolos.
            </span>
        </p>

        <!-- Legenda skala warna sel (wajib untuk skala kontinu). -->
        <div class="mt-legend">
            <span class="mt-legend__lbl">Jumlah kandidat per sel</span>
            <span class="mt-legend__sel is-nol">0</span>
            <span v-for="(w, i) in RAMP" :key="w" class="mt-legend__sel"
                :style="{ background: w, color: selTinta(w) }">{{ rentang(i) }}</span>
        </div>

        <!-- Matriks. Kolom pertama LEKAT saat digulir mendatar, supaya nama
             posisi tidak hilang di alur bertahap tujuh kolom. -->
        <div class="wcd-tw mt-tw">
            <table class="wcd-tbl mt-tbl">
                <thead>
                    <tr>
                        <th class="mt-lekat">Posisi</th>
                        <th class="wcd-num">Kursi</th>
                        <th class="wcd-num">Pelamar</th>
                        <th class="wcd-num">Rasio</th>
                        <th v-for="t in khas.tahap" :key="t.urutan" class="mt-th-tahap" :title="t.label">
                            <span>{{ t.urutan }}. {{ t.label }}</span>
                        </th>
                        <th class="wcd-num">Skor rata</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(m, i) in matriksUrut" :key="i">
                        <td class="mt-lekat wcd-tbl__utama">
                            {{ m.posisi }}
                            <span class="wcd-tbl__sub">
                                {{ m.program }}<template v-if="m.departemen"> · {{ m.departemen }}</template>
                            </span>
                        </td>
                        <td class="wcd-num">{{ angka(m.kuota) }}</td>
                        <td class="wcd-num">{{ angka(m.pelamar) }}</td>
                        <td class="wcd-num">
                            <span v-if="m.rasio === null">—</span>
                            <span v-else class="wcd-lb" :style="nadaRasio(m)">
                                <i class="bi" :class="m.rasio < 1 ? STATUS.critical.ikon : 'bi-people-fill'"></i>
                                {{ desimal(m.rasio, 1) }}×
                            </span>
                        </td>
                        <td v-for="(s, j) in m.sel" :key="j" class="mt-sel">
                            <span :style="gayaSel(s.total)" :title="tooltipSel(khas.tahap[j], s)">
                                {{ s.total || '' }}
                            </span>
                        </td>
                        <td class="wcd-num">{{ m.skor ? desimal(m.skor.rata, 2) : '—' }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <p class="mt-nota">
            Sel dihitung dengan aturan penempatan yang sama seperti funnel &amp; papan Monitoring:
            kandidat yang gugur berada di kolom tempat dia gugur, yang masih berjalan di kolom
            tahap aktifnya. Angka pada satu baris karena itu berjumlah sama dengan total pelamarnya.
        </p>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { angka, desimal, RAMP, selRamp, selTinta, STATUS } from '../dashboardHelpers';
import KeadaanPanel from '../../monitoring/KeadaanPanel.vue';

const props = defineProps({ khas: { type: Object, required: true } });

const totalKuota = computed(() => props.khas.matriks.reduce((n, m) => n + (m.kuota || 0), 0));
const totalPelamar = computed(() => props.khas.matriks.reduce((n, m) => n + (m.pelamar || 0), 0));

/* Skala warna dihitung dari sel TERBESAR di seluruh matriks, bukan per baris —
   kalau per baris, posisi dengan 2 pelamar akan tampak sama "panas" dengan
   posisi yang punya 200. */
const maksSel = computed(() => Math.max(1, ...props.khas.matriks.flatMap((m) => m.sel.map((s) => s.total || 0))));

const berRasio = computed(() => props.khas.matriks.filter((m) => m.rasio !== null && m.pelamar > 0));
const terlaris = computed(() => [...berRasio.value].sort((a, b) => b.rasio - a.rasio)[0] || null);
const sepi = computed(() => props.khas.matriks.filter((m) => m.rasio !== null && m.rasio < 1));

/* Posisi sepi di atas — itu yang perlu diputuskan (perpanjang, gabung, atau
   turunkan target). Sesudahnya pelamar terbanyak. */
const matriksUrut = computed(() => [...props.khas.matriks].sort((a, b) => {
    const sa = a.rasio !== null && a.rasio < 1 ? 1 : 0;
    const sb = b.rasio !== null && b.rasio < 1 ? 1 : 0;
    if (sa !== sb) return sb - sa;
    return (b.pelamar || 0) - (a.pelamar || 0);
}));

function gayaSel(nilai) {
    const w = selRamp(nilai, maksSel.value);
    if (!w) return { background: '#f8fafc', color: '#cbd5e1' };
    return { background: w, color: selTinta(w) };
}

function rentang(i) {
    const lebar = maksSel.value / RAMP.length;
    const a = Math.floor(i * lebar) + 1;
    const b = Math.ceil((i + 1) * lebar);
    return a >= b ? String(b) : `${a}–${b}`;
}

function tooltipSel(tahap, s) {
    if (!tahap) return '';
    const bagian = [];
    if (s.aktif) bagian.push(`${s.aktif} masih di sini`);
    if (s.lulus) bagian.push(`${s.lulus} diterima`);
    if (s.gugur) bagian.push(`${s.gugur} gugur`);
    if (s.talent) bagian.push(`${s.talent} talent pool`);
    return `${tahap.urutan}. ${tahap.label} — ${bagian.length ? bagian.join(', ') : 'belum ada kandidat'}`;
}
</script>

<style scoped>
.mt-ingat {
    display: flex;
    gap: 10px;
    align-items: flex-start;
    margin: 0 0 16px;
    padding: 11px 13px;
    border-radius: 12px;
    background: #fef2f2;
    border: 1px solid #fecaca;
    font-size: 0.76rem;
    line-height: 1.6;
    color: #7f1d1d;
}
.mt-ingat > .bi { font-size: 16px; color: #d03b3b; flex: none; margin-top: 1px; }
.mt-ingat b { color: #991b1b; }

.mt-legend { display: flex; align-items: center; gap: 5px; flex-wrap: wrap; margin-bottom: 10px; }
.mt-legend__lbl { font-size: 0.71rem; font-weight: 800; color: #475569; margin-right: 4px; }
.mt-legend__sel {
    display: inline-grid;
    place-items: center;
    min-width: 34px;
    height: 20px;
    padding: 0 6px;
    border-radius: 5px;
    font-size: 0.67rem;
    font-weight: 800;
    font-variant-numeric: tabular-nums;
}
.mt-legend__sel.is-nol { background: #f8fafc; color: #cbd5e1; border: 1px solid #eef2f7; }

.mt-tw { max-height: 460px; overflow: auto; }
.mt-tbl { font-size: 0.76rem; }

/* Kolom nama posisi lekat: tanpa ini nama posisi hilang begitu digulir ke
   tahap 5-7 dan sel-selnya jadi tak bermakna. */
.mt-lekat {
    position: sticky;
    left: 0;
    z-index: 2;
    background: #fff;
    box-shadow: 1px 0 0 #eef2f7;
    min-width: 190px;
}
thead .mt-lekat { z-index: 3; background: #f8fafc; }
tbody tr:hover .mt-lekat { background: #fcfdff; }

.mt-th-tahap { max-width: 92px; white-space: normal; }
.mt-th-tahap span { display: block; font-size: 0.66rem; line-height: 1.35; }

.mt-sel { padding: 4px; text-align: center; }
/* Jarak 2px antar sel dibuat oleh padding td, bukan garis tepi pada selnya —
   garis di sekeliling mark menambah bobot visual tanpa menambah makna. */
.mt-sel span {
    display: grid;
    place-items: center;
    min-width: 34px;
    height: 28px;
    border-radius: 7px;
    font-size: 0.74rem;
    font-weight: 800;
    font-variant-numeric: tabular-nums;
}

.mt-nota { margin: 12px 0 0; font-size: 0.71rem; line-height: 1.6; color: #94a3b8; }
.wcd-tbl td small { color: #94a3b8; font-weight: 700; }
</style>
