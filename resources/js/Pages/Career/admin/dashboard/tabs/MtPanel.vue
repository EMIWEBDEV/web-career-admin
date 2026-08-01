<!--
  ZONA D — KHAS MT: PERSAINGAN ANTAR-POSISI (Redesigned)
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
            <span class="mt-legend__lbl"><i class="bi bi-palette"></i> Intensitas Kandidat per Sel:</span>
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
                            <span>{{ t.urutan }}. {{ t.label }}</span>
                        </th>
                        <th class="wcd-num">Skor Rata</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(m, i) in matriksUrut" :key="i">
                        <td class="mt-lekat wcd-tbl__utama">
                            <strong>{{ m.posisi }}</strong>
                            <span class="wcd-tbl__sub">
                                {{ m.program }}<template v-if="m.departemen"> · {{ m.departemen }}</template>
                            </span>
                        </td>
                        <td class="wcd-num"><b>{{ angka(m.kuota) }}</b></td>
                        <td class="wcd-num"><b>{{ angka(m.pelamar) }}</b></td>
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
                        <td class="wcd-num"><b>{{ m.skor ? desimal(m.skor.rata, 2) : '—' }}</b></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <p class="mt-nota">
            Sel dihitung sesuai posisi aktif/terakhir pelamar di alur seleksi MT.
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
    if (m.rasio >= 5) return { background: '#f0fdf4', color: '#059669' };
    return { background: '#f1f5f9', color: '#475569' };
}
</script>

<style scoped>
.mt-container { padding-top: 4px; }
.mt-ingat {
    display: flex;
    gap: 12px;
    align-items: center;
    margin: 0 0 18px;
    padding: 14px 18px;
    border-radius: 14px;
    background: #fef2f2;
    border: 1px solid #fecaca;
    font-size: 0.8rem;
    line-height: 1.5;
    color: #991b1b;
}
.mt-ingat > .bi { font-size: 18px; color: #dc2626; flex: none; }
.mt-ingat b { color: #7f1d1d; }

.mt-legend { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; margin-bottom: 14px; background: #f8fafc; padding: 8px 14px; border-radius: 12px; border: 1px solid #f1f5f9; }
.mt-legend__lbl { font-size: 0.76rem; font-weight: 800; color: #475569; margin-right: 6px; display: inline-flex; align-items: center; gap: 4px; }
.mt-legend__lbl .bi { color: #6366f1; }
.mt-legend__sel {
    display: inline-grid;
    place-items: center;
    min-width: 38px;
    height: 22px;
    padding: 0 8px;
    border-radius: 6px;
    font-size: 0.72rem;
    font-weight: 800;
    font-variant-numeric: tabular-nums;
}
.mt-legend__sel.is-nol { background: #ffffff; color: #94a3b8; border: 1px solid #e2e8f0; }

.mt-tw { max-height: 480px; overflow: auto; border-radius: 16px; border: 1px solid #e2e8f0; }
.mt-tbl { font-size: 0.78rem; }

.mt-lekat {
    position: sticky;
    left: 0;
    z-index: 2;
    background: #ffffff;
    box-shadow: 2px 0 6px rgba(15, 23, 42, 0.05);
    min-width: 200px;
}
thead .mt-lekat { z-index: 3; background: #f8fafc; }
tbody tr:hover .mt-lekat { background: #f8fafc; }

.mt-th-tahap { max-width: 100px; white-space: normal; }
.mt-th-tahap span { display: block; font-size: 0.7rem; line-height: 1.35; }

.mt-sel { padding: 4px; text-align: center; }
.mt-sel span {
    display: grid;
    place-items: center;
    min-width: 36px;
    height: 30px;
    border-radius: 8px;
    font-size: 0.78rem;
    font-weight: 800;
    font-variant-numeric: tabular-nums;
    transition: transform 0.15s ease;
}
.mt-sel span:hover { transform: scale(1.1); z-index: 1; }

.mt-nota { margin: 12px 0 0; font-size: 0.74rem; line-height: 1.5; color: #94a3b8; }
</style>
