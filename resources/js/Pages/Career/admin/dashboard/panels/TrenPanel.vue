<!--
  ZONA C — TREN LAMARAN.

  DUA seri pada SATU sumbu. Keduanya satuan "orang", jadi boleh berdampingan —
  dan justru karena itu tidak boleh dibuat dua sumbu-y: menyandingkan dua skala
  berbeda pada satu plot mengarang korelasi yang tidak ada di datanya.

  Sumbu waktu tiap seri BERBEDA MAKNANYA dan itu ditulis di legenda:
   · "Masuk"    dihitung pada tanggal melamar;
   · "Diterima" dihitung pada tanggal keputusan.
  Kalau "diterima" ikut dikelompokkan pada tanggal melamar, grafiknya berubah
  jadi kohort dan tidak lagi sebanding dengan garis di sebelahnya.

  Warna: #6366f1 / #d97706 — lolos keenam pemeriksaan validator pada surface
  putih (CVD ΔE 32.2, penglihatan normal 35.1, kontras keduanya ≥ 3:1).
-->
<template>
    <div v-if="!tren.titik.length">
        <KeadaanPanel keadaan="kosong" ikon="bi-graph-up"
            teks="Belum ada lamaran pada periode ini"
            ket="Coba perlebar periode, atau tunggu lamaran pertama masuk." />
    </div>

    <div v-else>
        <!-- Label langsung yang SELEKTIF: total per seri, bukan angka di setiap
             titik (angka di tiap titik jadi kekacauan dan tidak terbaca). -->
        <div class="tr-total">
            <span v-for="s in seri" :key="s.name" class="tr-total__i">
                <i class="tr-dot" :style="{ background: s.color }"></i>
                <b>{{ angka(s.total) }}</b>
                <span>{{ s.name }}</span>
                <small>{{ s.ket }}</small>
            </span>
            <button type="button" class="tr-alih" @click="tabel = !tabel">
                <i class="bi" :class="tabel ? 'bi-graph-up' : 'bi-table'"></i>
                {{ tabel ? 'Lihat grafik' : 'Lihat tabel' }}
            </button>
        </div>

        <!-- Tabel adalah padanan setara, bukan pelengkap: setiap nilai bisa
             dibaca tanpa hover dan tanpa membedakan warna. -->
        <div v-if="tabel" class="wcd-tw tr-tw">
            <table class="wcd-tbl">
                <thead>
                    <tr><th>Tanggal</th><th class="wcd-num">Masuk</th><th class="wcd-num">Diterima</th></tr>
                </thead>
                <tbody>
                    <tr v-for="t in titikTerpakai" :key="t.tgl">
                        <td>{{ tanggal(t.tgl) }}</td>
                        <td class="wcd-num">{{ t.masuk }}</td>
                        <td class="wcd-num">{{ t.diterima }}</td>
                    </tr>
                </tbody>
            </table>
            <p v-if="titikTerpakai.length < tren.titik.length" class="tr-nota">
                Hanya hari yang berisi ditampilkan ({{ titikTerpakai.length }} dari {{ tren.titik.length }} hari).
            </p>
        </div>

        <apexchart v-else type="area" height="290" :options="opsi" :series="seriChart" />
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

/* Tabel hanya menampilkan hari yang berisi — deretan nol sepanjang 90 baris
   tidak menambah informasi apa pun dan menenggelamkan yang berisi. Grafiknya
   tetap memakai seluruh hari supaya lubang tidak tersambung jadi garis naik. */
const titikTerpakai = computed(() => props.tren.titik.filter((t) => t.masuk || t.diterima));

const seriChart = computed(() => [
    { name: 'Masuk', data: props.tren.titik.map((t) => [new Date(t.tgl).getTime(), t.masuk]) },
    { name: 'Diterima', data: props.tren.titik.map((t) => [new Date(t.tgl).getTime(), t.diterima]) },
]);

const opsi = computed(() => ({
    chart: {
        type: 'area',
        fontFamily: 'Inter, system-ui, -apple-system, "Segoe UI", sans-serif',
        toolbar: { show: false },
        zoom: { enabled: false },
        animations: { enabled: true, speed: 320 },
    },
    colors: [SERI.masuk, SERI.diterima],
    // Garis 2px; titik disembunyikan sampai disentuh, lalu 9px dengan cincin
    // surface 2px supaya titik yang bertumpuk tetap terbaca terpisah.
    stroke: { curve: 'smooth', width: 2 },
    markers: { size: 0, strokeWidth: 2, strokeColors: '#fff', hover: { size: 5 } },
    fill: {
        type: 'gradient',
        gradient: { shadeIntensity: 0, opacityFrom: 0.22, opacityTo: 0.02, stops: [0, 100] },
    },
    dataLabels: { enabled: false },
    legend: {
        show: true,
        position: 'top',
        horizontalAlign: 'left',
        fontSize: '12px',
        fontWeight: 700,
        markers: { width: 9, height: 9, radius: 9 },
        labels: { colors: INK.kedua },
        itemMargin: { horizontal: 10 },
    },
    grid: {
        borderColor: INK.grid,
        strokeDashArray: 0,          // garis rambut SOLID — putus-putus terbaca sebagai ambang
        xaxis: { lines: { show: false } },
        padding: { left: 6, right: 12, top: 0 },
    },
    xaxis: {
        type: 'datetime',
        axisBorder: { color: INK.sumbu },
        axisTicks: { color: INK.sumbu },
        labels: {
            style: { colors: INK.redup, fontSize: '11px' },
            datetimeFormatter: { day: 'dd MMM', month: 'MMM yy' },
        },
        tooltip: { enabled: false },
    },
    yaxis: {
        min: 0,
        forceNiceScale: true,
        labels: {
            style: { colors: INK.redup, fontSize: '11px' },
            formatter: (v) => (Number.isInteger(v) ? v : ''),
        },
    },
    // Tooltip menyempurnakan, tidak menjadi satu-satunya jalan membaca nilai —
    // itulah gunanya tombol "Lihat tabel" di atas.
    tooltip: {
        shared: true,
        intersect: false,
        x: { format: 'dd MMM yyyy' },
        style: { fontSize: '12px' },
    },
}));
</script>

<style scoped>
.tr-total { display: flex; align-items: center; gap: 22px; flex-wrap: wrap; margin-bottom: 6px; }
.tr-total__i { display: inline-flex; align-items: baseline; gap: 6px; font-size: 0.75rem; color: #475569; }
.tr-total__i b { font-size: 1.15rem; font-weight: 900; color: #0f172a; }
.tr-total__i small { color: #94a3b8; font-size: 0.69rem; }
.tr-dot { width: 9px; height: 9px; border-radius: 999px; align-self: center; flex: none; }

.tr-alih {
    margin-left: auto;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    border: 1px solid #e8edf5;
    background: #fff;
    padding: 6px 12px;
    border-radius: 10px;
    font: inherit;
    font-size: 0.73rem;
    font-weight: 800;
    color: #4f46e5;
    cursor: pointer;
}
.tr-alih:hover { background: #eef2ff; }

.tr-tw { margin-top: 10px; max-height: 340px; overflow-y: auto; }
.tr-nota { margin: 8px 12px; font-size: 0.72rem; color: #94a3b8; }
</style>
