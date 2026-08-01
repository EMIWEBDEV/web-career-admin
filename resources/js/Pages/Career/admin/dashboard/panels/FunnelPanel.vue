<!--
  ZONA C — FUNNEL & KONVERSI (Redesigned)
-->
<template>
    <div v-if="!funnel.length">
        <KeadaanPanel keadaan="kosong" ikon="bi-funnel"
            teks="Belum ada funnel untuk kategori ini"
            ket="Funnel terbentuk dari tahap alur seleksi program. Pastikan programnya sudah punya alur." />
    </div>

    <div v-else class="fn">
        <!-- Kepala: pemilih alur, cakupan, dan penyempitan terbesar -->
        <div class="fn-atas">
            <div v-if="funnel.length > 1" class="wcd-seg fn-alur">
                <button v-for="(f, i) in funnel" :key="f.alurId" type="button"
                    :class="{ 'is-on': idx === i }" :title="f.programNama.join(', ')"
                    @click="idx = i">
                    <span>{{ f.alur }}</span> <em>{{ angka(f.pelamar) }}</em>
                </button>
            </div>

            <span class="fn-cakup" :title="aktif.programNama.join(', ')">
                <i class="bi bi-diagram-2-fill"></i>
                {{ aktif.program }} program · <b>{{ angka(aktif.pelamar) }}</b> pelamar
            </span>

            <span v-if="aktif.penyempitan" class="fn-sempit"
                :title="`${angka(aktif.penyempitan.hilang)} kandidat berhenti di titik ini`">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <span>Penyempitan terbesar: <b>{{ aktif.penyempitan.dari }} → {{ aktif.penyempitan.ke }}</b></span>
                <em>{{ aktif.penyempitan.persen }}% lanjut (−{{ angka(aktif.penyempitan.hilang) }})</em>
            </span>

            <span class="fn-legenda">
                <i class="fn-swatch is-lanjut"></i> Lanjut
                <i class="fn-swatch is-henti"></i> Berhenti di tahap
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

                <!-- Rincian baris (dilipat) -->
                <div v-show="buka.has(t.urutan)" class="fn-rinci">
                    <span><i class="bi bi-person-check-fill"></i> Lanjut: <b>{{ angka(t.lanjut) }}</b></span>
                    <span><i class="bi bi-person-pause-fill"></i> Di tahap ini: <b :class="{ 'is-nol': !t.diSini }">{{ angka(t.diSini) }}</b></span>
                    <span><i class="bi bi-patch-check-fill"></i> Diterima: <b :class="{ 'is-nol': !t.diterima }">{{ angka(t.diterima) }}</b></span>
                    <span><i class="bi bi-person-x-fill"></i> Tidak lolos: <b :class="{ 'is-nol': !t.gugur }">{{ angka(t.gugur) }}</b></span>
                    <span><i class="bi bi-bookmark-star-fill"></i> Talent pool: <b :class="{ 'is-nol': !t.talent }">{{ angka(t.talent) }}</b></span>
                </div>
            </li>
        </ul>

        <!-- Padanan tabel lengkap (dilipat) -->
        <details class="fn-tabel">
            <summary><i class="bi bi-table"></i> Lihat matriks lengkap per tahap</summary>
            <div class="wcd-tw">
                <table class="wcd-tbl">
                    <thead>
                        <tr>
                            <th>#</th><th>Tahap</th><th class="wcd-num">Mencapai</th>
                            <th class="wcd-num">Lanjut</th><th class="wcd-num">Di sini</th>
                            <th class="wcd-num">Diterima</th><th class="wcd-num">Gugur</th>
                            <th class="wcd-num">Talent</th><th class="wcd-num">Konversi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="t in aktif.tahap" :key="t.urutan" :class="{ 'is-sempit': sorot(t) }">
                            <td>{{ t.urutan }}</td>
                            <td class="wcd-tbl__utama">{{ t.label }}</td>
                            <td class="wcd-num">{{ angka(t.capai) }}</td>
                            <td class="wcd-num">{{ angka(t.lanjut) }}</td>
                            <td class="wcd-num">{{ angka(t.diSini) }}</td>
                            <td class="wcd-num">{{ angka(t.diterima) }}</td>
                            <td class="wcd-num">{{ angka(t.gugur) }}</td>
                            <td class="wcd-num">{{ angka(t.talent) }}</td>
                            <td class="wcd-num">
                                <span v-if="t.konversi !== null" class="wcd-lb" :style="nadaKonversi(t)">
                                    {{ t.konversi }}%
                                </span>
                                <span v-else class="fn-konv__nol">—</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </details>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { angka, STATUS, INK } from '../dashboardHelpers';
import KeadaanPanel from '../../monitoring/KeadaanPanel.vue';

const props = defineProps({ funnel: { type: Array, default: () => [] } });

const idx = ref(0);
const buka = ref(new Set());

const aktif = computed(() => props.funnel[idx.value] || props.funnel[0] || { tahap: [], programNama: [] });

function lebar(t) {
    const pert = aktif.value.tahap[0]?.capai || 1;
    return Math.max(2, Math.round((t.capai / pert) * 100));
}

function persenLanjut(t) {
    if (!t.capai) return 0;
    return Math.round((t.lanjut / t.capai) * 100);
}

function tooltip(t) {
    const pert = aktif.value.tahap[0]?.capai || 1;
    const pDarisemua = Math.round((t.capai / pert) * 100);
    const r = [`${t.urutan}. ${t.label}`, `${angka(t.capai)} kandidat (${pDarisemua}% dari total melamar)`];
    if (t.konversi !== null) r.push(`Konversi dari tahap sebelumnya: ${t.konversi}%`);
    r.push(`• ${angka(t.lanjut)} lanjut ke tahap berikutnya`);
    if (t.diSini) r.push(`• ${angka(t.diSini)} sedang berproses di sini`);
    if (t.diterima) r.push(`• ${angka(t.diterima)} diterima di sini`);
    if (t.gugur) r.push(`• ${angka(t.gugur)} tidak lolos`);
    if (t.talent) r.push(`• ${angka(t.talent)} ke talent pool`);
    return r.join('\n');
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
    if (sorot(t)) return { background: `color-mix(in srgb, ${STATUS.critical.warna} 14%, #fff)`, color: STATUS.critical.warna };
    if (t.konversi >= 80) return { background: `color-mix(in srgb, ${STATUS.good.warna} 14%, #fff)`, color: STATUS.good.warna };
    return { background: '#f1f5f9', color: INK.kedua };
}
</script>

<style scoped>
.fn-atas {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
    margin-bottom: 14px;
}
.fn-alur { flex-wrap: wrap; }
.fn-alur em { font-style: normal; opacity: 0.7; }

.fn-cakup { font-size: 0.78rem; color: #64748b; white-space: nowrap; font-weight: 600; }
.fn-cakup .bi { color: #6366f1; margin-right: 4px; }
.fn-cakup b { color: #0f172a; font-weight: 800; }

.fn-sempit {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 6px 14px;
    border-radius: 999px;
    background: #fff7ed;
    border: 1px solid #ffedd5;
    font-size: 0.76rem;
    color: #9a3412;
    box-shadow: 0 2px 6px rgba(234, 88, 12, 0.06);
}
.fn-sempit > .bi { font-size: 14px; color: #ea580c; flex: none; }
.fn-sempit b { font-weight: 900; }
.fn-sempit em { font-style: normal; font-weight: 800; opacity: 0.85; }

.fn-legenda {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    margin-left: auto;
    font-size: 0.74rem;
    font-weight: 600;
    color: #64748b;
    white-space: nowrap;
}
.fn-swatch { display: inline-block; width: 12px; height: 10px; border-radius: 4px; }
.fn-swatch.is-lanjut { background: #6366f1; }
.fn-swatch.is-henti { background: #c7d2fe; margin-left: 8px; }

/* ══════════ BARIS TAHAP ══════════ */
.fn-list { list-style: none; margin: 0; padding: 0; border: 1px solid #e2e8f0; border-radius: 16px; overflow: hidden; background: #fff; }
.fn-list > li + li { border-top: 1px solid #f1f5f9; }

.fn-row {
    display: grid;
    grid-template-columns: 24px minmax(110px, 1.2fr) minmax(100px, 2fr) 52px 68px 16px;
    align-items: center;
    gap: 12px;
    width: 100%;
    padding: 10px 16px;
    border: 0;
    border-left: 4px solid transparent;
    background: transparent;
    font: inherit;
    text-align: left;
    cursor: pointer;
    transition: background 0.16s ease;
}
.fn-row:hover { background: #f8fafc; }
.fn-row.is-buka { background: #f8fafc; }
.fn-row.is-sempit { border-left-color: #ea580c; background: #fffdfb; }

.fn-ur {
    display: grid;
    place-items: center;
    width: 22px;
    height: 22px;
    border-radius: 7px;
    background: #eef2ff;
    color: #4f46e5;
    font-size: 0.72rem;
    font-weight: 900;
}
.fn-lbl {
    font-size: 0.8rem;
    font-weight: 800;
    color: #0f172a;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.fn-lbl .bi { font-size: 11px; color: #94a3b8; margin-left: 4px; }

.fn-rel { display: block; height: 10px; border-radius: 999px; background: #f1f5f9; overflow: hidden; }
.fn-bar {
    display: block;
    height: 100%;
    border-radius: 0 6px 6px 0;
    background: #c7d2fe;
    transition: width 0.4s cubic-bezier(0.16, 1, 0.3, 1);
}
.fn-bar__lanjut {
    display: block;
    height: 100%;
    border-radius: 0 6px 6px 0;
    background: linear-gradient(90deg, #6366f1, #4f46e5);
    transition: width 0.4s cubic-bezier(0.16, 1, 0.3, 1);
}
.is-sempit .fn-bar { background: #fed7aa; }

.fn-capai { font-size: 0.82rem; font-weight: 900; color: #0f172a; text-align: right; }
.fn-konv { display: flex; justify-content: flex-end; }
.fn-konv__nol { font-size: 0.72rem; color: #cbd5e1; font-weight: 700; }
.fn-chev { font-size: 12px; color: #94a3b8; }

.fn-rinci {
    display: flex;
    flex-wrap: wrap;
    gap: 8px 18px;
    padding: 8px 16px 14px 52px;
    background: #f8fafc;
    border-top: 1px dashed #e2e8f0;
    font-size: 0.76rem;
    color: #475569;
}
.fn-rinci .bi { font-size: 12px; color: #6366f1; margin-right: 4px; }
.fn-rinci b { color: #0f172a; font-weight: 800; }
.fn-rinci .is-nol { color: #cbd5e1; font-weight: 500; }

.fn-tabel { margin-top: 16px; }
.fn-tabel summary {
    cursor: pointer;
    font-size: 0.78rem;
    font-weight: 800;
    color: #4f46e5;
    padding: 6px 0;
}

@media (max-width: 640px) {
    .fn-row {
        grid-template-columns: 24px 1fr 48px 60px 14px;
        row-gap: 6px;
        padding: 10px 12px;
    }
    .fn-lbl { grid-column: 2 / -1; }
    .fn-rel { grid-column: 2; }
    .fn-rinci { padding-left: 16px; }
}
</style>
