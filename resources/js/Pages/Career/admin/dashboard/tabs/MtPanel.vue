<!--
  ZONA D — KHAS MT: PERSAINGAN ANTAR-POSISI (Redesigned & Tidied Table UI/UX)
-->
<template>
    <div v-if="!khas.matriks.length">
        <KeadaanPanel keadaan="kosong" ikon="bi-diagram-3"
            teks="Belum ada posisi pada program MT"
            ket="Posisi MT ditambahkan lewat MPP di menu Program Kegiatan." />
    </div>

    <div v-else class="mt-container">
        <!-- Ringkasan persaingan -->
        <div class="wcd-grid wcd-grid--3" style="margin-bottom: 18px">
            <div class="wcd-stat" :style="{ '--tone': '#d97706' }">
                <div class="wcd-stat__top">
                    <span class="wcd-stat__ic"><i class="bi bi-diagram-3-fill"></i></span>
                </div>
                <div class="wcd-stat__num">{{ angka(khas.matriks.length) }}</div>
                <div class="wcd-stat__lbl">Posisi Diperebutkan</div>
                <div class="wcd-stat__ket">{{ angka(totalKuota) }} kursi · {{ angka(totalPelamar) }} pelamar</div>
            </div>
            <div class="wcd-stat" :style="{ '--tone': terlaris ? '#10b981' : '#94a3b8' }">
                <div class="wcd-stat__top">
                    <span class="wcd-stat__ic"><i class="bi bi-fire"></i></span>
                </div>
                <div class="wcd-stat__num">{{ terlaris ? desimal(terlaris.rasio, 1) : '—' }}<small v-if="terlaris">×</small></div>
                <div class="wcd-stat__lbl">Paling Diminati</div>
                <div class="wcd-stat__ket">{{ terlaris ? terlaris.posisi : 'Rasio belum bisa dihitung' }}</div>
            </div>
            <div class="wcd-stat" :style="{ '--tone': sepi.length ? STATUS.critical.warna : '#10b981' }">
                <div class="wcd-stat__top">
                    <span class="wcd-stat__ic"><i class="bi bi-person-dash-fill"></i></span>
                </div>
                <div class="wcd-stat__num">{{ angka(sepi.length) }}</div>
                <div class="wcd-stat__lbl">Posisi Sepi Pelamar</div>
                <div class="wcd-stat__ket">Pelamar &lt; jumlah kursi yang tersedia</div>
            </div>
        </div>

        <p v-if="sepi.length" class="mt-ingat">
            <i class="bi" :class="STATUS.critical.ikon"></i>
            <span>
                <b>{{ sepi.map((s) => s.posisi).slice(0, 3).join(', ') }}<template v-if="sepi.length > 3"> +{{ sepi.length - 3 }} lain</template></b>
                memiliki pelamar lebih sedikit daripada kuota kursinya.
            </span>
        </p>

        <!-- Legenda skala warna sel -->
        <div class="mt-legend">
            <span class="mt-legend__lbl"><i class="bi bi-palette-fill"></i> Intensitas Kandidat per Sel:</span>
            <span class="mt-legend__sel is-nol">0</span>
            <span v-for="(w, i) in RAMP" :key="w" class="mt-legend__sel"
                :style="{ background: w, color: selTinta(w) }">{{ rentang(i) }}</span>
        </div>

        <!-- Matriks. Kolom pertama LEKAT saat digulir mendatar -->
        <div class="wcd-tw mt-tw">
            <table class="wcd-tbl mt-tbl">
                <thead>
                    <tr>
                        <th class="mt-lekat">Posisi MT</th>
                        <th class="wcd-num">Kursi</th>
                        <th class="wcd-num">Pelamar</th>
                        <th class="wcd-num">Rasio</th>
                        <th v-for="t in khas.tahap" :key="t.urutan" class="mt-th-tahap" :title="t.label">
                            <div class="mt-th-badge">
                                <span class="mt-th-num">{{ t.urutan }}</span>
                                <span class="mt-th-txt">{{ t.label }}</span>
                            </div>
                        </th>
                        <th class="wcd-num">Skor Rata</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(m, i) in matriksUrut" :key="i">
                        <td class="mt-lekat wcd-tbl__utama">
                            <strong class="mt-posisi-title">{{ m.posisi }}</strong>
                            <span class="wcd-tbl__sub">
                                {{ m.program }}<template v-if="m.departemen"> · {{ m.departemen }}</template>
                            </span>
                        </td>
                        <td class="wcd-num"><b class="mt-val-bold">{{ angka(m.kuota) }}</b></td>
                        <td class="wcd-num"><b class="mt-val-bold">{{ angka(m.pelamar) }}</b></td>
                        <td class="wcd-num">
                            <span v-if="m.rasio === null" class="mt-null-val">—</span>
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
                        <td class="wcd-num"><b class="mt-val-bold">{{ m.skor ? desimal(m.skor.rata, 2) : '—' }}</b></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <p class="mt-nota">
            <i class="bi bi-info-circle-fill"></i> Sel dihitung sesuai posisi aktif/terakhir pelamar di alur seleksi MT.
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

const maksSel = computed(() => Math.max(1, ...props.khas.matriks.flatMap((m) => m.sel.map((s) => s.total || 0))));

const berRasio = computed(() => props.khas.matriks.filter((m) => m.rasio !== null && m.pelamar > 0));
const terlaris = computed(() => [...berRasio.value].sort((a, b) => b.rasio - a.rasio)[0] || null);
const sepi = computed(() => props.khas.matriks.filter((m) => m.rasio !== null && m.rasio < 1));

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

function nadaRasio(m) {
    if (m.rasio < 1) return { background: '#fef2f2', color: '#dc2626' };
    if (m.rasio >= 5) return { background: '#ecfdf5', color: '#047857' };
    return { background: '#f1f5f9', color: '#334155' };
}
</script>

<style scoped>
.mt-container { padding-top: 4px; }

.mt-ingat {
    display: flex;
    gap: 12px;
    align-items: center;
    margin: 0 0 16px;
    padding: 14px 18px;
    border-radius: 14px;
    background: #fef2f2;
    border: 1px solid #fecaca;
    font-size: 0.82rem;
    line-height: 1.5;
    color: #991b1b;
}
.mt-ingat > .bi { font-size: 18px; color: #dc2626; flex: none; }
.mt-ingat b { color: #7f1d1d; }

.mt-legend {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
    margin-bottom: 16px;
    background: #f8fafc;
    padding: 10px 16px;
    border-radius: 14px;
    border: 1px solid #e2e8f0;
}
.mt-legend__lbl {
    font-size: 0.78rem;
    font-weight: 800;
    color: #334155;
    margin-right: 6px;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.mt-legend__lbl .bi { color: #6366f1; }
.mt-legend__sel {
    display: inline-grid;
    place-items: center;
    min-width: 40px;
    height: 24px;
    padding: 0 8px;
    border-radius: 8px;
    font-size: 0.74rem;
    font-weight: 800;
    font-variant-numeric: tabular-nums;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.06);
}
.mt-legend__sel.is-nol { background: #ffffff; color: #94a3b8; border: 1px solid #cbd5e1; }

/* ══════════ METRICS TABLE CONTAINER (wcd-tw mt-tw) ══════════ */
.wcd-tw.mt-tw {
    position: relative;
    max-height: 500px;
    overflow: auto;
    border-radius: 16px;
    border: 1px solid #e2e8f0;
    background: #ffffff;
    box-shadow: 0 4px 16px -4px rgba(15, 23, 42, 0.06);
}

/* Custom Scrollbar for mt-tw */
.wcd-tw.mt-tw::-webkit-scrollbar {
    width: 8px;
    height: 8px;
}
.wcd-tw.mt-tw::-webkit-scrollbar-track {
    background: #f1f5f9;
    border-radius: 999px;
}
.wcd-tw.mt-tw::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 999px;
}
.wcd-tw.mt-tw::-webkit-scrollbar-thumb:hover {
    background: #94a3b8;
}

.mt-tbl {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    font-size: 0.8rem;
}

.mt-tbl th {
    padding: 12px 14px;
    background: #f8fafc;
    color: #475569;
    font-size: 0.74rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    border-bottom: 2px solid #e2e8f0;
    position: sticky;
    top: 0;
    z-index: 10;
}

.mt-tbl td {
    padding: 10px 14px;
    border-bottom: 1px solid #f1f5f9;
    vertical-align: middle;
    white-space: nowrap;
}

.mt-tbl tbody tr:hover td {
    background: rgba(248, 250, 252, 0.8);
}

.mt-tbl tbody tr:last-child td {
    border-bottom: 0;
}

/* Kolom sticky Posisi MT */
.mt-lekat {
    position: sticky;
    left: 0;
    z-index: 15;
    background: #ffffff;
    border-right: 2px solid #e2e8f0;
    min-width: 220px;
    box-shadow: 4px 0 12px -2px rgba(15, 23, 42, 0.05);
}

thead .mt-lekat {
    z-index: 20;
    background: #f8fafc;
}

tbody tr:hover .mt-lekat {
    background: #ffffff;
}

.mt-posisi-title {
    display: block;
    font-size: 0.84rem;
    font-weight: 800;
    color: #0f172a;
}

.mt-val-bold {
    font-weight: 800;
    color: #0f172a;
}

.mt-null-val {
    color: #cbd5e1;
}

/* Header Tahap Badge */
.mt-th-tahap {
    min-width: 100px;
    max-width: 130px;
    text-align: center;
}

.mt-th-badge {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 2px;
}

.mt-th-num {
    display: inline-grid;
    place-items: center;
    width: 20px;
    height: 20px;
    border-radius: 6px;
    background: #e0e7ff;
    color: #4338ca;
    font-size: 0.68rem;
    font-weight: 900;
}

.mt-th-txt {
    font-size: 0.72rem;
    font-weight: 800;
    color: #334155;
    text-transform: none;
    letter-spacing: normal;
    white-space: normal;
    text-align: center;
    line-height: 1.25;
}

/* Metric Cell Styling */
.mt-sel {
    padding: 6px 8px !important;
    text-align: center;
}

.mt-sel span {
    display: grid;
    place-items: center;
    min-width: 38px;
    height: 32px;
    margin: 0 auto;
    border-radius: 8px;
    font-size: 0.8rem;
    font-weight: 900;
    font-variant-numeric: tabular-nums;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
    transition: transform 0.18s cubic-bezier(0.16, 1, 0.3, 1), box-shadow 0.18s cubic-bezier(0.16, 1, 0.3, 1);
}

.mt-sel span:hover {
    transform: scale(1.14);
    z-index: 5;
    box-shadow: 0 4px 12px rgba(15, 23, 42, 0.15);
}

.mt-nota {
    margin: 14px 0 0;
    font-size: 0.76rem;
    line-height: 1.5;
    color: #64748b;
    display: flex;
    align-items: center;
    gap: 6px;
}
.mt-nota .bi { color: #6366f1; }
</style>
