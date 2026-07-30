<!--
  ZONA D — KHAS REKRUTMEN: PEMENUHAN MPP.

  Pertanyaan pertama atasan soal rekrutmen selalu sama: "posisi mana yang belum
  terisi, dan sudah berapa lama dibuka?" Dua kolom itulah inti tabel di bawah —
  UMUR BUKA disandingkan dengan SISA KURSI, karena posisi yang sudah 90 hari
  terbuka dan masih kosong adalah masalah yang berbeda jenis dari posisi yang
  baru dibuka minggu ini.

  TIME-TO-FILL dihitung dari Waktu_Lamar → Waktu_Selesai kandidat yang diterima.
  Belum ada halaman lain yang menghitungnya. Kalau belum pernah ada kursi terisi,
  angkanya null — BUKAN 0 hari, yang akan terbaca "instan".
-->
<template>
    <div>
        <!-- ── Time-to-fill & ringkasan ── -->
        <div class="wcd-grid wcd-grid--3" style="margin-bottom: 16px">
            <div class="wcd-stat" :style="{ '--tone': '#6366f1' }">
                <div class="wcd-stat__top"><span class="wcd-stat__ic"><i class="bi bi-briefcase-fill"></i></span></div>
                <div class="wcd-stat__num">{{ angka(khas.posisi.length) }}</div>
                <div class="wcd-stat__lbl">Posisi terdaftar</div>
                <div class="wcd-stat__ket">{{ angka(totalKuota) }} kursi, {{ angka(totalTerisi) }} terisi</div>
            </div>

            <div class="wcd-stat" :style="{ '--tone': khas.timeToFill ? '#0ca30c' : '#94a3b8' }">
                <div class="wcd-stat__top"><span class="wcd-stat__ic"><i class="bi bi-stopwatch-fill"></i></span></div>
                <div class="wcd-stat__num">
                    <template v-if="khas.timeToFill">{{ desimal(khas.timeToFill.rata, 1) }} <small>hari</small></template>
                    <template v-else>—</template>
                </div>
                <div class="wcd-stat__lbl">Rata-rata waktu mengisi kursi</div>
                <div class="wcd-stat__ket">
                    <template v-if="khas.timeToFill">
                        dari {{ khas.timeToFill.jml }} kursi terisi · tercepat {{ khas.timeToFill.tercepat }},
                        terlama {{ khas.timeToFill.terlama }} hari
                    </template>
                    <template v-else>belum ada kursi yang pernah terisi</template>
                </div>
            </div>

            <div class="wcd-stat" :style="{ '--tone': posisiKritis.length ? STATUS.critical.warna : '#0ca30c' }">
                <div class="wcd-stat__top"><span class="wcd-stat__ic"><i class="bi bi-hourglass-bottom"></i></span></div>
                <div class="wcd-stat__num">{{ angka(posisiKritis.length) }}</div>
                <div class="wcd-stat__lbl">Posisi lama &amp; masih kosong</div>
                <div class="wcd-stat__ket">terbuka &gt; 30 hari, belum satu pun terisi</div>
            </div>
        </div>

        <!-- ── Tabel posisi ── -->
        <div class="wcd-tw">
            <table class="wcd-tbl">
                <thead>
                    <tr>
                        <th>Posisi</th><th>Departemen</th><th>Lokasi</th>
                        <th class="wcd-num">Kursi</th><th>Pemenuhan</th>
                        <th class="wcd-num">Pelamar</th><th class="wcd-num">Umur buka</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(p, i) in posisiUrut" :key="i">
                        <td class="wcd-tbl__utama">
                            {{ p.posisi }}
                            <span class="wcd-tbl__sub">
                                {{ p.program }}<template v-if="p.mppRef"> · {{ p.mppRef }}</template>
                            </span>
                        </td>
                        <td>{{ p.departemen || '—' }}</td>
                        <td>{{ p.lokasi || '—' }}<span v-if="p.level" class="wcd-tbl__sub">{{ p.level }}</span></td>
                        <td class="wcd-num">{{ angka(p.terisi) }} <small>/ {{ angka(p.kuota) }}</small></td>
                        <td>
                            <div class="rk-fill">
                                <div class="wcd-meter">
                                    <span :style="{ width: pct(p) + '%', background: p.sisaKursi === 0 && p.kuota ? STATUS.good.warna : '#6366f1' }"></span>
                                </div>
                                <span>{{ p.kuota ? pct(p) + '%' : '—' }}</span>
                            </div>
                        </td>
                        <td class="wcd-num">
                            {{ angka(p.pelamar) }}
                            <small v-if="p.berjalan" :title="`${p.berjalan} masih berproses`">({{ p.berjalan }} aktif)</small>
                        </td>
                        <td class="wcd-num">
                            <span v-if="p.umurBukaHari === null">—</span>
                            <span v-else class="wcd-lb" :style="nadaUmurBuka(p)">
                                <i class="bi" :class="kritis(p) ? STATUS.critical.ikon : 'bi-calendar3'"></i>
                                {{ p.umurBukaHari }} hari
                            </span>
                        </td>
                    </tr>
                    <tr v-if="!khas.posisi.length">
                        <td colspan="7">
                            <KeadaanPanel keadaan="kosong" rapat ikon="bi-briefcase"
                                teks="Belum ada posisi terdaftar"
                                ket="Posisi ditambahkan lewat MPP di menu Program Kegiatan." />
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- ── Sebaran ── -->
        <div class="wcd-grid wcd-grid--2" style="margin-top: 16px">
            <div class="wcd-card wcd-card--datar">
                <h3 class="wcd-card__hd"><i class="bi bi-diagram-3"></i> Kebutuhan per Departemen</h3>
                <DaftarBatang :items="departemen" satuan="kursi" ikon="bi-diagram-3"
                    teks-kosong="Belum ada kuota per departemen" />
                <p v-if="departemen.length" class="rk-nota">
                    Angka = kursi (kuota). Pelamar aktif per departemen ada di tooltip tiap baris tabel di atas.
                </p>
            </div>

            <div class="wcd-card wcd-card--datar">
                <h3 class="wcd-card__hd"><i class="bi bi-signpost-split-fill"></i> Sumber Kandidat</h3>
                <DaftarBatang :items="sumber" satuan="pelamar" ikon="bi-signpost-split"
                    teks-kosong="Sumber kandidat belum tercatat"
                    ket-kosong="Kolom Sumber_Kandidat_Kode pada lamaran masih kosong." />
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { angka, desimal, persen, STATUS } from '../dashboardHelpers';
import DaftarBatang from '../panels/DaftarBatang.vue';
import KeadaanPanel from '../../monitoring/KeadaanPanel.vue';

const props = defineProps({ khas: { type: Object, required: true } });

const totalKuota = computed(() => props.khas.posisi.reduce((n, p) => n + (p.kuota || 0), 0));
const totalTerisi = computed(() => props.khas.posisi.reduce((n, p) => n + (p.terisi || 0), 0));

const pct = (p) => (p.kuota ? persen(p.terisi, p.kuota) : 0);
const kritis = (p) => (p.umurBukaHari || 0) > 30 && p.terisi === 0 && p.kuota > 0;

const posisiKritis = computed(() => props.khas.posisi.filter(kritis));

/* Yang paling perlu ditengok di atas: lama terbuka & masih kosong dulu, lalu
   sisa kursi terbanyak. Urutan alfabetis tidak menjawab pertanyaan apa pun. */
const posisiUrut = computed(() => [...props.khas.posisi].sort((a, b) => {
    const ka = kritis(a) ? 1 : 0;
    const kb = kritis(b) ? 1 : 0;
    if (ka !== kb) return kb - ka;
    if (b.sisaKursi !== a.sisaKursi) return b.sisaKursi - a.sisaKursi;
    return String(a.posisi).localeCompare(String(b.posisi));
}));

const departemen = computed(() => props.khas.departemen.map((d) => ({ nama: d.departemen, jml: d.kuota })));
const sumber = computed(() => props.khas.sumber.map((s) => ({ nama: s.nama, jml: s.jml })));

function nadaUmurBuka(p) {
    if (kritis(p)) return { background: `color-mix(in srgb, ${STATUS.critical.warna} 12%, #fff)`, color: STATUS.critical.warna };
    if ((p.umurBukaHari || 0) > 30) return { background: `color-mix(in srgb, ${STATUS.warning.warna} 14%, #fff)`, color: '#92400e' };
    return {};
}
</script>

<style scoped>
.rk-fill { display: flex; align-items: center; gap: 8px; min-width: 120px; }
.rk-fill span { font-size: 0.72rem; font-weight: 800; color: #475569; font-variant-numeric: tabular-nums; }
.rk-nota { margin: 10px 0 0; font-size: 0.7rem; color: #94a3b8; line-height: 1.55; }
.wcd-tbl td small { color: #94a3b8; font-weight: 700; }
</style>
