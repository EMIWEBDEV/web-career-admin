<!--
  DAFTAR BATANG — sebaran satu dimensi (kampus, jurusan, jenjang, sumber,
  departemen). Dipakai panel Rekrutmen dan Magang; dibuat satu komponen karena
  kalau tidak, pola yang sama akan ditulis lima kali dengan lima gaya berbeda.

  SATU warna untuk semua batang, bukan gradasi "makin besar makin gelap":
  panjang batang sudah memikul magnitudo, jadi mewarnainya lagi menggandakan
  informasi yang sama sekaligus melanggar pemeriksaan kategorikal (ramp
  membentang melewati rentang lightness). Kategori di sini juga nominal —
  kampus dan jurusan tidak punya urutan alami.

  Angka pastinya SELALU tertulis di sebelah batang, jadi nilainya tidak pernah
  hanya bisa dibaca dari panjang atau warna.
-->
<template>
    <div>
        <KeadaanPanel v-if="!items.length" keadaan="kosong" rapat :ikon="ikon" :teks="teksKosong" :ket="ketKosong" />

        <ul v-else class="db-list">
            <li v-for="(it, i) in items" :key="i">
                <span class="db-nama" :title="it.nama">{{ it.nama }}</span>
                <span class="db-rel">
                    <span class="db-bar" :style="{ width: lebar(it) + '%', background: warna }"></span>
                </span>
                <span class="db-jml">{{ angka(it.jml) }}<small v-if="satuan"> {{ satuan }}</small></span>
            </li>
        </ul>

        <p v-if="terpotong" class="db-nota">Menampilkan {{ items.length }} teratas.</p>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { angka } from '../dashboardHelpers';
import KeadaanPanel from '../../monitoring/KeadaanPanel.vue';

const props = defineProps({
    items: { type: Array, default: () => [] },
    warna: { type: String, default: '#6366f1' },
    satuan: { type: String, default: '' },
    ikon: { type: String, default: 'bi-bar-chart' },
    teksKosong: { type: String, default: 'Belum ada data' },
    ketKosong: { type: String, default: '' },
    terpotong: { type: Boolean, default: false },
});

const maks = computed(() => Math.max(1, ...props.items.map((i) => i.jml || 0)));
const lebar = (it) => Math.max(it.jml > 0 ? 2 : 0, ((it.jml || 0) / maks.value) * 100);
</script>

<style scoped>
.db-list { list-style: none; margin: 0; padding: 0; display: grid; gap: 8px; }
.db-list > li { display: grid; grid-template-columns: minmax(90px, 1.1fr) minmax(60px, 2fr) auto; align-items: center; gap: 10px; }

.db-nama {
    font-size: 0.75rem;
    font-weight: 700;
    color: #334155;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
/* Batang tipis, ujung data membulat 4px, menempel ke garis dasar kiri. */
.db-rel { height: 9px; border-radius: 999px; background: #f1f5f9; overflow: hidden; }
.db-bar { display: block; height: 100%; border-radius: 0 4px 4px 0; }
.db-jml {
    font-size: 0.76rem;
    font-weight: 900;
    color: #0f172a;
    font-variant-numeric: tabular-nums;
    text-align: right;
    min-width: 34px;
}
.db-jml small { font-weight: 700; color: #94a3b8; }

.db-nota { margin: 9px 0 0; font-size: 0.7rem; color: #94a3b8; }

@media (max-width: 520px) {
    .db-list > li { grid-template-columns: 1fr auto; }
    .db-rel { grid-column: 1 / -1; order: 3; }
}
</style>
