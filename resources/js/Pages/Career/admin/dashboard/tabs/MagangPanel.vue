<!--
  ZONA D — KHAS MAGANG: KAMPUS, KEMITRAAN & BATCH.

  Kemitraan/MoU SENGAJA tidak disaring oleh ada-tidaknya program magang berjalan.
  Justru saat belum ada program, pertanyaannya paling penting: MoU mana yang
  kuotanya belum dipakai dan sebentar lagi kedaluwarsa? Menyaringnya ikut program
  akan menyembunyikan tepat informasi itu.

  Sebaran pendidikan dibaca dari Formulir_Jawaban_Index, yang isinya HANYA field
  yang dipakai aturan syarat + field turunan. Jadi kampus/jurusan bisa saja belum
  terindeks pada program yang tidak memakainya sebagai syarat — itu keadaan data,
  bukan kegagalan baca, dan panelnya cukup menampilkan yang ada.
-->
<template>
    <div>
        <!-- ══════ KEMITRAAN / MoU ══════ -->
        <div class="wcd-card__hd"><i class="bi bi-file-earmark-text-fill"></i> Kemitraan &amp; MoU Kampus</div>

        <KeadaanPanel v-if="!khas.kemitraan.length" keadaan="kosong" rapat ikon="bi-file-earmark-x"
            teks="Belum ada kemitraan terdaftar"
            ket="Kemitraan dikelola di menu Master Kemitraan / MoU." />

        <div v-else class="wcd-grid wcd-grid--2">
            <div v-for="(m, i) in kemitraanUrut" :key="i" class="mg-mou" :class="{ 'is-habis': habis(m), 'is-segera': segera(m) }">
                <div class="mg-mou__hd">
                    <strong>{{ m.mitra }}</strong>
                    <span v-if="m.jenis" class="wcd-lb">{{ m.jenis }}</span>
                </div>
                <p class="mg-mou__kampus">
                    <i class="bi bi-building"></i>
                    {{ m.kampus || 'Kampus belum ditautkan' }}<template v-if="m.kota"> · {{ m.kota }}</template>
                </p>

                <div class="mg-mou__kuota">
                    <div class="wcd-meter">
                        <span :style="{ width: pct(m) + '%', background: m.terisi >= m.kuota && m.kuota ? STATUS.good.warna : '#059669' }"></span>
                    </div>
                    <span><b>{{ angka(m.terisi) }}</b> / {{ angka(m.kuota) }} kuota</span>
                </div>

                <div class="mg-mou__ft">
                    <span class="wcd-lb" :style="nadaMasa(m)">
                        <i class="bi" :class="ikonMasa(m)"></i>
                        <template v-if="m.sisaHari === null">Tanpa tanggal akhir</template>
                        <template v-else-if="m.sisaHari < 0">Berakhir {{ Math.abs(m.sisaHari) }} hari lalu</template>
                        <template v-else-if="m.sisaHari === 0">Berakhir hari ini</template>
                        <template v-else>{{ m.sisaHari }} hari lagi</template>
                    </span>
                    <span class="mg-mou__tgl">{{ tanggal(m.mulai) }} – {{ m.selesai ? tanggal(m.selesai) : '∞' }}</span>
                    <span v-if="m.status" class="wcd-lb">{{ m.status }}</span>
                </div>

                <!-- Kombinasi paling perlu ditindak: masih ada kuota, tapi masa
                     berlakunya hampir habis. -->
                <p v-if="segera(m) && m.terisi < m.kuota" class="mg-mou__ingat">
                    <i class="bi" :class="STATUS.serious.ikon"></i>
                    Masih {{ angka(m.kuota - m.terisi) }} kuota belum dipakai dan masa berlakunya hampir habis.
                </p>
            </div>
        </div>

        <!-- ══════ BATCH ══════ -->
        <div class="wcd-card__hd mg-jarak"><i class="bi bi-layers-fill"></i> Progres Batch</div>

        <KeadaanPanel v-if="!khas.batch.length" keadaan="kosong" rapat ikon="bi-layers"
            teks="Belum ada batch magang"
            ket="Batch dibuat di dalam program di menu Program Kegiatan." />

        <div v-else class="wcd-tw">
            <table class="wcd-tbl">
                <thead>
                    <tr><th>Batch</th><th>Program</th><th class="wcd-num">Terisi</th><th>Progres</th><th>Status</th></tr>
                </thead>
                <tbody>
                    <tr v-for="(b, i) in khas.batch" :key="i">
                        <td class="wcd-tbl__utama">{{ b.nama }}</td>
                        <td>{{ b.program }}</td>
                        <td class="wcd-num">{{ angka(b.terisi) }} <small>/ {{ angka(b.kuota) }}</small></td>
                        <td>
                            <div class="mg-prog">
                                <div class="wcd-meter">
                                    <span :style="{ width: pct(b) + '%', background: '#059669' }"></span>
                                </div>
                                <span>{{ b.kuota ? pct(b) + '%' : '—' }}</span>
                            </div>
                        </td>
                        <td><span class="wcd-lb">{{ b.status || '—' }}</span></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- ══════ SEBARAN PENDIDIKAN ══════ -->
        <div class="wcd-card__hd mg-jarak"><i class="bi bi-mortarboard-fill"></i> Profil Pendidikan Pelamar</div>

        <div class="wcd-grid wcd-grid--3">
            <div class="wcd-card wcd-card--datar">
                <h4 class="mg-sub">Jenjang</h4>
                <DaftarBatang :items="khas.pendidikan.jenjang" warna="#059669" satuan="org"
                    ikon="bi-mortarboard" teks-kosong="Jenjang belum terindeks" />
            </div>
            <div class="wcd-card wcd-card--datar">
                <h4 class="mg-sub">Kampus teratas</h4>
                <DaftarBatang :items="khas.pendidikan.kampus" warna="#059669" satuan="org"
                    ikon="bi-building" teks-kosong="Kampus belum terindeks"
                    ket-kosong="Field kampus hanya terindeks bila dipakai sebagai syarat program." />
            </div>
            <div class="wcd-card wcd-card--datar">
                <h4 class="mg-sub">Jurusan teratas</h4>
                <DaftarBatang :items="khas.pendidikan.jurusan" warna="#059669" satuan="org"
                    ikon="bi-book" teks-kosong="Jurusan belum terindeks" />
            </div>
        </div>

        <!-- IPK: sebaran tiga rentang + ringkasan. Bukan grafik — tiga angka
             memang lebih cepat dibaca sebagai angka. -->
        <div v-if="khas.pendidikan.ipk" class="wcd-card wcd-card--datar mg-ipk">
            <h4 class="mg-sub">Sebaran IPK <small>{{ angka(khas.pendidikan.ipk.jml) }} pelamar terdata</small></h4>
            <div class="wcd-kpi wcd-kpi--rapat">
                <div class="wcd-stat" :style="{ '--tone': '#059669' }">
                    <div class="wcd-stat__num">{{ desimal(khas.pendidikan.ipk.rata, 2) }}</div>
                    <div class="wcd-stat__lbl">IPK rata-rata</div>
                    <div class="wcd-stat__ket">
                        {{ desimal(khas.pendidikan.ipk.terendah, 2) }} – {{ desimal(khas.pendidikan.ipk.tertinggi, 2) }}
                    </div>
                </div>
                <div class="wcd-stat" :style="{ '--tone': '#059669' }">
                    <div class="wcd-stat__num">{{ angka(khas.pendidikan.ipk.atas35) }}</div>
                    <div class="wcd-stat__lbl">IPK ≥ 3,50</div>
                </div>
                <div class="wcd-stat" :style="{ '--tone': '#94a3b8' }">
                    <div class="wcd-stat__num">{{ angka(khas.pendidikan.ipk.tiga) }}</div>
                    <div class="wcd-stat__lbl">3,00 – 3,49</div>
                </div>
                <div class="wcd-stat" :style="{ '--tone': '#94a3b8' }">
                    <div class="wcd-stat__num">{{ angka(khas.pendidikan.ipk.bawah3) }}</div>
                    <div class="wcd-stat__lbl">Di bawah 3,00</div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { angka, desimal, tanggal, persen, STATUS } from '../dashboardHelpers';
import DaftarBatang from '../panels/DaftarBatang.vue';
import KeadaanPanel from '../../monitoring/KeadaanPanel.vue';

const props = defineProps({ khas: { type: Object, required: true } });

const pct = (x) => (x.kuota ? Math.min(100, persen(x.terisi, x.kuota)) : 0);
const habis = (m) => m.sisaHari !== null && m.sisaHari < 0;
const segera = (m) => m.sisaHari !== null && m.sisaHari >= 0 && m.sisaHari <= 60;

/* Yang hampir/sudah berakhir naik ke atas — itu yang masih bisa (atau sudah
   tidak bisa) ditindak. */
const kemitraanUrut = computed(() => [...props.khas.kemitraan].sort((a, b) => {
    const sa = a.sisaHari === null ? 99999 : a.sisaHari;
    const sb = b.sisaHari === null ? 99999 : b.sisaHari;
    return sa - sb;
}));

function nadaMasa(m) {
    if (habis(m)) return { background: '#f1f5f9', color: '#64748b' };
    if (m.sisaHari !== null && m.sisaHari <= 30) return { background: `color-mix(in srgb, ${STATUS.critical.warna} 12%, #fff)`, color: STATUS.critical.warna };
    if (segera(m)) return { background: `color-mix(in srgb, ${STATUS.serious.warna} 14%, #fff)`, color: '#9a3412' };
    return { background: '#f0fdf4', color: '#15803d' };
}

function ikonMasa(m) {
    if (habis(m)) return 'bi-slash-circle';
    if (segera(m)) return STATUS.serious.ikon;
    return 'bi-calendar-check';
}
</script>

<style scoped>
.wcd-card__hd { font-size: 0.82rem; }
.mg-jarak { margin-top: 22px; }
.mg-sub {
    margin: 0 0 11px;
    font-size: 0.76rem;
    font-weight: 900;
    color: #0f172a;
    display: flex;
    justify-content: space-between;
    gap: 8px;
}
.mg-sub small { font-weight: 700; color: #94a3b8; font-size: 0.69rem; }

.mg-mou {
    padding: 13px 14px;
    border: 1px solid #eef2f7;
    border-left: 3px solid #059669;
    border-radius: 13px;
    background: #fff;
}
.mg-mou.is-segera { border-left-color: #ec835a; }
.mg-mou.is-habis { border-left-color: #cbd5e1; opacity: 0.72; }

.mg-mou__hd { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.mg-mou__hd strong { font-size: 0.82rem; font-weight: 900; color: #0f172a; }
.mg-mou__kampus { margin: 5px 0 10px; font-size: 0.73rem; color: #64748b; }
.mg-mou__kampus .bi { color: #94a3b8; }

.mg-mou__kuota { display: flex; align-items: center; gap: 10px; }
.mg-mou__kuota .wcd-meter { flex: 1; }
.mg-mou__kuota span { font-size: 0.73rem; color: #475569; font-variant-numeric: tabular-nums; }
.mg-mou__kuota b { font-weight: 900; color: #0f172a; }

.mg-mou__ft { display: flex; align-items: center; gap: 9px; flex-wrap: wrap; margin-top: 10px; }
.mg-mou__tgl { font-size: 0.7rem; color: #94a3b8; }

.mg-mou__ingat {
    margin: 10px 0 0;
    font-size: 0.72rem;
    line-height: 1.55;
    color: #9a3412;
}
.mg-mou__ingat .bi { color: #ec835a; }

.mg-prog { display: flex; align-items: center; gap: 8px; min-width: 120px; }
.mg-prog span { font-size: 0.72rem; font-weight: 800; color: #475569; }
.wcd-tbl td small { color: #94a3b8; font-weight: 700; }

.mg-ipk { margin-top: 14px; }
</style>
