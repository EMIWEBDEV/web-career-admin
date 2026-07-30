<!--
  ZONA C — KESEHATAN & KUOTA PROGRAM.

  Diurut TERBURUK DULU. Program tanpa proses berjalan (skor null) ditaruh paling
  bawah karena bukan masalah — dan dibedakan dua label yang sering tertukar:
  KOSONG (belum ada pelamar sama sekali) vs SELESAI (pernah ada, semuanya sudah
  tuntas). Menyamakan keduanya membuat program yang sukses terlihat mati.

  Skor hanya dihukum oleh dua hal yang memang tanggung jawab admin: tahap macet
  dan keputusan yang didiamkan. Menunggu penyedia tes SENGAJA tidak menurunkan
  skor — sebabnya di luar kendali.

  "Kandidat per kursi" adalah angka yang paling jarang dilihat tapi paling
  menentukan: di bawah 1 berarti kursi itu tidak mungkin penuh dari kandidat
  yang ada sekarang, sekalipun semuanya lolos.
-->
<template>
    <div v-if="!baris.length">
        <KeadaanPanel keadaan="kosong" ikon="bi-collection"
            teks="Belum ada program pada kategori ini"
            ket="Program dibuat di menu Program Kegiatan." />
    </div>

    <ul v-else class="ks-list">
        <li v-for="p in baris" :key="p.id">
            <!-- Cincin skor: 0-100, satu hue status + angka di tengah. Angkanya
                 ada di dalam cincin supaya nilainya tidak hanya dipikul warna. -->
            <div class="ks-ring" :style="{ '--tone': nada(p).warna }">
                <svg viewBox="0 0 44 44" aria-hidden="true">
                    <circle class="ks-ring__bg" cx="22" cy="22" r="19" />
                    <circle v-if="p.sehat.skor !== null" class="ks-ring__fg" cx="22" cy="22" r="19"
                        :stroke-dasharray="keliling" :stroke-dashoffset="offset(p.sehat.skor)" />
                </svg>
                <b>{{ p.sehat.skor === null ? '–' : p.sehat.skor }}</b>
            </div>

            <div class="ks-isi">
                <div class="ks-judul">
                    <strong>{{ p.nama }}</strong>
                    <span class="wcd-lb" :style="{ background: `color-mix(in srgb, ${nada(p).warna} 12%, #fff)`, color: nada(p).warna }">
                        <i class="bi" :class="nada(p).ikon"></i> {{ nada(p).teks }}
                    </span>
                    <span v-if="p.status !== 'BERJALAN'" class="wcd-lb">{{ p.status }}</span>
                </div>

                <div class="ks-meta">
                    <span><i class="bi bi-diagram-2"></i> {{ p.alur || 'Tanpa alur' }}</span>
                    <span><i class="bi bi-people-fill"></i> {{ angka(p.pelamar) }} pelamar</span>
                    <span v-if="p.sehat.aktif"><i class="bi bi-person-walking"></i> {{ angka(p.sehat.aktif) }} aktif</span>
                    <span v-if="p.sehat.macet" class="is-buruk"><i class="bi bi-cone-striped"></i> {{ angka(p.sehat.macet) }} macet</span>
                    <span v-if="p.sehat.siapMenggantung" class="is-buruk"><i class="bi bi-hourglass-split"></i> {{ angka(p.sehat.siapMenggantung) }} keputusan menggantung</span>
                    <span v-if="p.sehat.maxAging !== null"><i class="bi bi-clock-history"></i> terlama {{ p.sehat.maxAging }} hari</span>
                </div>

                <!-- Kuota: meter + angka. Meter saja tidak cukup — angka pastinya
                     tetap ditulis di sebelahnya. -->
                <div class="ks-kuota">
                    <div class="wcd-meter" :title="`${p.terisi} dari ${p.kuota} kursi terisi`">
                        <span :style="{ width: persenKursi(p) + '%', background: p.sisaKursi === 0 ? STATUS.good.warna : '#6366f1' }"></span>
                    </div>
                    <span class="ks-kuota__txt">
                        <b>{{ angka(p.terisi) }}</b> / {{ angka(p.kuota) }} kursi
                        <template v-if="p.kuota">({{ persenKursi(p) }}%)</template>
                    </span>
                    <span v-if="p.kandidatPerKursi !== null" class="wcd-lb" :style="nadaRasio(p)">
                        <i class="bi" :class="p.kandidatPerKursi < 1 ? STATUS.critical.ikon : 'bi-check2'"></i>
                        {{ desimal(p.kandidatPerKursi, 1) }} kandidat / kursi sisa
                    </span>
                    <span v-else-if="p.kuota" class="wcd-lb" :style="{ background: '#f0fdf4', color: STATUS.good.warna }">
                        <i class="bi" :class="STATUS.good.ikon"></i> Kursi penuh
                    </span>
                    <span v-else class="wcd-lb">Kuota belum diisi</span>
                </div>
            </div>
        </li>
    </ul>
</template>

<script setup>
import { angka, desimal, nadaSehat, persen, STATUS } from '../dashboardHelpers';
import KeadaanPanel from '../../monitoring/KeadaanPanel.vue';

defineProps({ baris: { type: Array, default: () => [] } });

const keliling = 2 * Math.PI * 19;

const nada = (p) => nadaSehat(p.sehat.label);
const offset = (skor) => keliling - (keliling * Math.max(0, Math.min(100, skor))) / 100;
const persenKursi = (p) => (p.kuota ? persen(p.terisi, p.kuota) : 0);

function nadaRasio(p) {
    const s = p.kandidatPerKursi < 1 ? STATUS.critical : (p.kandidatPerKursi < 2 ? STATUS.warning : STATUS.good);
    return { background: `color-mix(in srgb, ${s.warna} 12%, #fff)`, color: s.warna };
}
</script>

<style scoped>
.ks-list { list-style: none; margin: 0; padding: 0; display: grid; gap: 10px; }
.ks-list > li {
    display: flex;
    gap: 14px;
    align-items: flex-start;
    padding: 13px 14px;
    border: 1px solid #eef2f7;
    border-radius: 14px;
    background: #fff;
}
.ks-list > li:hover { border-color: #ddd6fe; }

.ks-ring { position: relative; width: 46px; height: 46px; flex: none; }
.ks-ring svg { width: 100%; height: 100%; transform: rotate(-90deg); }
.ks-ring__bg { fill: none; stroke: #eef2f7; stroke-width: 4; }
.ks-ring__fg {
    fill: none;
    stroke: var(--tone);
    stroke-width: 4;
    stroke-linecap: round;
    transition: stroke-dashoffset 0.5s cubic-bezier(0.16, 1, 0.3, 1);
}
.ks-ring b {
    position: absolute;
    inset: 0;
    display: grid;
    place-items: center;
    font-size: 0.8rem;
    font-weight: 900;
    color: #0f172a;
}

.ks-isi { flex: 1; min-width: 0; }
.ks-judul { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.ks-judul strong { font-size: 0.83rem; font-weight: 900; color: #0f172a; }

.ks-meta { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 6px; font-size: 0.72rem; color: #64748b; }
.ks-meta .bi { font-size: 11px; color: #94a3b8; }
.ks-meta .is-buruk { color: #b91c1c; font-weight: 700; }
.ks-meta .is-buruk .bi { color: #d03b3b; }

.ks-kuota { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; margin-top: 10px; }
.ks-kuota .wcd-meter { flex: 1; min-width: 90px; max-width: 200px; }
.ks-kuota__txt { font-size: 0.73rem; color: #475569; font-variant-numeric: tabular-nums; }
.ks-kuota__txt b { color: #0f172a; font-weight: 900; }

@media (prefers-reduced-motion: reduce) { .ks-ring__fg { transition: none; } }
@media (max-width: 640px) {
    .ks-list > li { flex-direction: column; }
}
</style>
