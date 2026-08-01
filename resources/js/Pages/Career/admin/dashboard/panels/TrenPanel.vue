<!--
  ZONA C — TREN LAMARAN (Redesigned)
-->
<template>
    <div v-if="!tren.titik.length">
        <KeadaanPanel keadaan="kosong" ikon="bi-graph-up"
            teks="Belum ada lamaran pada periode ini"
            ket="Coba perlebar periode, atau tunggu lamaran pertama masuk." />
    </div>

    <div v-else class="tr-container">
        <!-- Label langsung & Rincian Total -->
        <div class="tr-total">
            <div v-for="s in seri" :key="s.name" class="tr-stat-pill" :style="{ '--seri-color': s.color }">
                <span class="tr-stat-pill__dot"></span>
                <div class="tr-stat-pill__info">
                    <span class="tr-stat-pill__val">{{ angka(s.total) }}</span>
                    <span class="tr-stat-pill__lbl">{{ s.name }}</span>
                </div>
                <small class="tr-stat-pill__ket">{{ s.ket }}</small>
            </div>

            <button type="button" class="tr-alih" @click="tabel = !tabel">
                <i class="bi" :class="tabel ? 'bi-graph-up-arrow' : 'bi-table'"></i>
                <span>{{ tabel ? 'Tampilkan grafik' : 'Tampilkan tabel' }}</span>
            </button>
        </div>

        <!-- Tabel padanan setara -->
        <div v-if="tabel" class="wcd-tw tr-tw">
            <table class="wcd-tbl">
                <thead>
                    <tr><th>Tanggal</th><th class="wcd-num">Lamaran Masuk</th><th class="wcd-num">Diterima</th></tr>
                </thead>
                <tbody>
                    <tr v-for="t in titikTerpakai" :key="t.tgl">
                        <td class="wcd-tbl__utama">{{ tanggal(t.tgl) }}</td>
                        <td class="wcd-num"><b>{{ t.masuk }}</b></td>
                        <td class="wcd-num"><span class="wcd-lb" style="background:#f0fdf4; color:#059669;">{{ t.diterima }}</span></td>
                    </tr>
                </tbody>
            </table>
            <p v-if="titikTerpakai.length < tren.titik.length" class="tr-nota">
                Hanya hari yang memiliki aktivitas ditampilkan ({{ titikTerpakai.length }} dari {{ tren.titik.length }} hari).
            </p>
        </div>

        <div v-else class="tr-chart-wrapper">
            <apexchart type="area" height="300" :options="opsi" :series="seriChart" />
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import apexchart from 'vue3-apexcharts';
import { angka, tanggal, SERI, INK } from '../dashboardHelpers';
import KeadaanPanel from '../../monitoring/KeadaanPanel.vue';

const props = defineProps({ tren: { type: Object, default: () => ({ titik: [], totalMasuk: 0, totalDiterima: 0 }) } });

const tabel = ref(false);

const seri = computed(() => [
    { name: 'Masuk', color: SERI.masuk, total: props.tren.totalMasuk, ket: 'per tanggal melamar' },
    { name: 'Diterima', color: SERI.diterima, total: props.tren.totalDiterima, ket: 'per tanggal keputusan' },
]);

const titikTerpakai = computed(() => props.tren.titik.filter((t) => t.masuk || t.diterima));

const seriChart = computed(() => [
    { name: 'Masuk', data: props.tren.titik.map((t) => [new Date(t.tgl).getTime(), t.masuk]) },
    { name: 'Diterima', data: props.tren.titik.map((t) => [new Date(t.tgl).getTime(), t.diterima]) },
]);

const opsi = computed(() => ({
    chart: {
        type: 'area',
        fontFamily: 'Inter, system-ui, -apple-system, sans-serif',
        toolbar: { show: false },
        zoom: { enabled: false },
        animations: { enabled: true, speed: 350 },
    },
    colors: [SERI.masuk, SERI.diterima],
    stroke: { curve: 'smooth', width: 3.5 },
    markers: { size: 0, strokeWidth: 2, strokeColors: '#fff', hover: { size: 6 } },
    fill: {
        type: 'gradient',
        gradient: { shadeIntensity: 0, opacityFrom: 0.28, opacityTo: 0.03, stops: [0, 100] },
    },
    dataLabels: { enabled: false },
    legend: {
        show: true,
        position: 'top',
        horizontalAlign: 'left',
        fontSize: '13px',
        fontWeight: 700,
        markers: { width: 10, height: 10, radius: 10 },
        labels: { colors: INK.kedua },
        itemMargin: { horizontal: 12 },
    },
    grid: {
        borderColor: INK.grid,
        strokeDashArray: 0,
        xaxis: { lines: { show: false } },
        padding: { left: 8, right: 14, top: 0 },
    },
    xaxis: {
        type: 'datetime',
        axisBorder: { color: INK.sumbu },
        axisTicks: { color: INK.sumbu },
        labels: {
            style: { colors: INK.redup, fontSize: '11px', fontWeight: 600 },
            datetimeFormatter: { day: 'dd MMM', month: 'MMM yy' },
        },
        tooltip: { enabled: false },
    },
    yaxis: {
        min: 0,
        forceNiceScale: true,
        labels: {
            style: { colors: INK.redup, fontSize: '11px', fontWeight: 600 },
            formatter: (v) => (Number.isInteger(v) ? v : ''),
        },
    },
    tooltip: {
        shared: true,
        intersect: false,
        x: { format: 'dd MMM yyyy' },
        style: { fontSize: '12px' },
    },
}));
</script>

<style scoped>
.tr-container {
    padding-top: 4px;
}

.tr-total {
    display: flex;
    align-items: center;
    gap: 16px;
    flex-wrap: wrap;
    margin-bottom: 14px;
}

.tr-stat-pill {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    padding: 10px 16px;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    box-shadow: 0 2px 6px rgba(15, 23, 42, 0.04);
}

.tr-stat-pill__dot {
    width: 10px;
    height: 10px;
    border-radius: 999px;
    background: var(--seri-color, #6366f1);
    flex: none;
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--seri-color, #6366f1) 25%, transparent);
}

.tr-stat-pill__info {
    display: flex;
    align-items: baseline;
    gap: 6px;
}

.tr-stat-pill__val {
    font-size: 1.2rem;
    font-weight: 900;
    color: #0f172a;
    line-height: 1;
}

.tr-stat-pill__lbl {
    font-size: 0.8rem;
    font-weight: 800;
    color: #334155;
}

.tr-stat-pill__ket {
    font-size: 0.72rem;
    color: #94a3b8;
    font-weight: 500;
}

.tr-alih {
    margin-left: auto;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    border: 1px solid #c7d2fe;
    background: #eef2ff;
    padding: 8px 16px;
    border-radius: 12px;
    font: inherit;
    font-size: 0.78rem;
    font-weight: 800;
    color: #4f46e5;
    cursor: pointer;
    transition: all 0.2s ease;
}
.tr-alih:hover { background: #4f46e5; color: #ffffff; border-color: #4f46e5; transform: translateY(-1px); }

.tr-tw { margin-top: 10px; max-height: 340px; overflow-y: auto; }
.tr-nota { margin: 10px 12px; font-size: 0.75rem; color: #94a3b8; }
.tr-chart-wrapper { padding: 8px 0; }
</style>
