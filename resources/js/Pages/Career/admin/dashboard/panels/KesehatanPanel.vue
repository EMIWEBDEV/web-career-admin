<!--
  ZONA C — KESEHATAN & KUOTA PROGRAM (Redesigned)
-->
<template>
    <div v-if="!baris.length">
        <KeadaanPanel keadaan="kosong" ikon="bi-collection"
            teks="Belum ada program pada kategori ini"
            ket="Program dibuat di menu Program Kegiatan." />
    </div>

    <ul v-else class="ks-list">
        <li v-for="p in baris" :key="p.id" class="ks-card">
            <!-- Cincin skor: 0-100 -->
            <div class="ks-ring" :style="{ '--tone': nada(p).warna }">
                <svg viewBox="0 0 44 44" aria-hidden="true">
                    <circle class="ks-ring__bg" cx="22" cy="22" r="19" />
                    <circle v-if="p.sehat.skor !== null" class="ks-ring__fg" cx="22" cy="22" r="19"
                        :stroke-dasharray="keliling" :stroke-dashoffset="offset(p.sehat.skor)" />
                </svg>
                <div class="ks-ring__inner">
                    <b>{{ p.sehat.skor === null ? '–' : p.sehat.skor }}</b>
                    <small>Skor</small>
                </div>
            </div>

            <div class="ks-isi">
                <div class="ks-judul">
                    <strong>{{ p.nama }}</strong>
                    <span class="wcd-lb" :style="{ background: `color-mix(in srgb, ${nada(p).warna} 12%, #fff)`, color: nada(p).warna }">
                        <i class="bi" :class="nada(p).ikon"></i> {{ nada(p).teks }}
                    </span>
                    <span v-if="p.status !== 'BERJALAN'" class="wcd-lb ks-status-tag">{{ p.status }}</span>
                </div>

                <div class="ks-meta">
                    <span><i class="bi bi-diagram-2"></i> Alur: <b>{{ p.alur || 'Tanpa alur' }}</b></span>
                    <span><i class="bi bi-people-fill"></i> <b>{{ angka(p.pelamar) }}</b> pelamar</span>
                    <span v-if="p.sehat.aktif"><i class="bi bi-person-walking"></i> <b>{{ angka(p.sehat.aktif) }}</b> aktif</span>
                    <span v-if="p.sehat.macet" class="is-buruk"><i class="bi bi-cone-striped"></i> <b>{{ angka(p.sehat.macet) }}</b> macet</span>
                    <span v-if="p.sehat.siapMenggantung" class="is-buruk"><i class="bi bi-hourglass-split"></i> <b>{{ angka(p.sehat.siapMenggantung) }}</b> menggantung</span>
                    <span v-if="p.sehat.maxAging !== null"><i class="bi bi-clock-history"></i> Terlama: {{ p.sehat.maxAging }} hari</span>
                </div>

                <!-- Kuota: meter + angka -->
                <div class="ks-kuota">
                    <div class="wcd-meter" :title="`${p.terisi} dari ${p.kuota} kursi terisi`">
                        <span :style="{ width: persenKursi(p) + '%', background: p.sisaKursi === 0 ? STATUS.good.warna : 'linear-gradient(90deg, #6366f1, #4f46e5)' }"></span>
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
.ks-list { list-style: none; margin: 0; padding: 0; display: grid; gap: 12px; }
.ks-card {
    display: flex;
    gap: 18px;
    align-items: flex-start;
    padding: 18px;
    border: 1px solid rgba(226, 232, 240, 0.9);
    border-radius: 16px;
    background: #ffffff;
    transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
    box-shadow: 0 2px 8px -2px rgba(15, 23, 42, 0.04);
}
.ks-card:hover { border-color: #c7d2fe; transform: translateY(-2px); box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.08); }

.ks-ring { position: relative; width: 54px; height: 54px; flex: none; }
.ks-ring svg { width: 100%; height: 100%; transform: rotate(-90deg); }
.ks-ring__bg { fill: none; stroke: #f1f5f9; stroke-width: 4; }
.ks-ring__fg {
    fill: none;
    stroke: var(--tone);
    stroke-width: 4;
    stroke-linecap: round;
    transition: stroke-dashoffset 0.6s cubic-bezier(0.16, 1, 0.3, 1);
}

.ks-ring__inner {
    position: absolute;
    inset: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
}

.ks-ring__inner b {
    font-size: 0.9rem;
    font-weight: 900;
    color: #0f172a;
    line-height: 1;
}

.ks-ring__inner small {
    font-size: 0.58rem;
    color: #94a3b8;
    text-transform: uppercase;
    font-weight: 800;
    margin-top: 1px;
}

.ks-isi { flex: 1; min-width: 0; }
.ks-judul { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.ks-judul strong { font-size: 0.92rem; font-weight: 900; color: #0f172a; }
.ks-status-tag { background: #f1f5f9; color: #475569; }

.ks-meta { display: flex; flex-wrap: wrap; gap: 14px; margin-top: 8px; font-size: 0.76rem; color: #64748b; }
.ks-meta .bi { font-size: 13px; color: #6366f1; }
.ks-meta b { color: #0f172a; font-weight: 800; }
.ks-meta .is-buruk { color: #dc2626; font-weight: 700; }
.ks-meta .is-buruk .bi { color: #ef4444; }

.ks-kuota { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; margin-top: 12px; }
.wcd-meter { height: 8px; border-radius: 999px; background: #f1f5f9; overflow: hidden; min-width: 100px; max-width: 220px; flex: 1; }
.wcd-meter span { display: block; height: 100%; border-radius: 999px; transition: width 0.4s ease; }
.ks-kuota__txt { font-size: 0.78rem; color: #475569; font-variant-numeric: tabular-nums; }
.ks-kuota__txt b { color: #0f172a; font-weight: 900; }

@media (max-width: 640px) {
    .ks-card { flex-direction: column; }
}
</style>
