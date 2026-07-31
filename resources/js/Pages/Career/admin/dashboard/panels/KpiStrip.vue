<!--
  ZONA A — DERETAN KPI.

  Server mengirim HANYA angka (satu peta datar). Label, ikon, warna, dan tujuan
  klik ditentukan di sini. Itu disengaja: dashboard lama menaruh label & ikon di
  controller, template membaca nama kunci yang berbeda, dan hasilnya kartu tampil
  kosong tanpa satu pun error. Dengan kontrak sesempit "peta angka", salah kunci
  langsung kelihatan sebagai "—", bukan diam-diam blank.

  Tiap kartu punya tujuan: melompat ke seksi yang menjelaskannya, atau membuka
  halaman tempat angka itu bisa dikerjakan. Kartu tanpa tujuan tidak dibuat
  seolah bisa diklik.
-->
<template>
    <div class="wcd-kpi" style="margin-bottom: 16px">
        <component v-for="k in kartu" :key="k.kunci"
            :is="k.tautan ? 'a' : (k.lompat ? 'button' : 'div')"
            :href="k.tautan || undefined" :type="k.lompat ? 'button' : undefined"
            class="wcd-stat" :class="{ 'is-klik': !!(k.tautan || k.lompat) }"
            :style="{ '--tone': k.warna }"
            :title="k.judul"
            @click="k.lompat ? $emit('lompat', k.lompat) : null">
            <div class="wcd-stat__top">
                <span class="wcd-stat__ic"><i class="bi" :class="k.ikon"></i></span>
                <!-- Chip perubahan hanya untuk kartu bertren DAN hanya kalau ada
                     pembanding. Periode "Semua" tidak punya periode sebelumnya,
                     jadi chip-nya tidak dipaksa muncul dengan angka 0. -->
                <span v-if="k.delta !== null && k.delta !== undefined" class="wcd-stat__delta"
                    :style="{ color: k.delta === 0 ? INK.redup : (k.delta > 0 ? STATUS.good.warna : STATUS.critical.warna) }">
                    <i class="bi" :class="k.delta === 0 ? 'bi-dash' : (k.delta > 0 ? 'bi-arrow-up-short' : 'bi-arrow-down-short')"></i>
                    {{ k.delta === 0 ? 'tetap' : angka(Math.abs(k.delta)) }}
                </span>
            </div>
            <div class="wcd-stat__num">{{ angka(k.nilai) }}</div>
            <div class="wcd-stat__lbl">{{ k.label }}</div>
            <div v-if="k.ket" class="wcd-stat__ket">{{ k.ket }}</div>
        </component>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { angka, STATUS, INK } from '../dashboardHelpers';

const props = defineProps({
    kpi: { type: Object, required: true },
    periode: { type: [Number, null], default: 30 },
    ambang: { type: Object, default: () => ({ macetHari: 7 }) },
    kategori: { type: String, default: '' },
});

defineEmits(['lompat']);

const labelPeriode = computed(() => (props.periode === null ? 'sejak awal' : `${props.periode} hari terakhir`));

const kartu = computed(() => {
    const k = props.kpi || {};
    const jenis = props.kategori ? `?jenis=${encodeURIComponent(props.kategori)}` : '';

    return [
        {
            kunci: 'aktif', nilai: k.aktif, label: 'Pelamar aktif', ket: 'sedang berproses',
            ikon: 'bi-person-walking', warna: '#6366f1', tautan: `/karir/pelamar${jenis}`,
            judul: 'Buka Worklist Pelamar',
        },
        {
            kunci: 'siap', nilai: k.siap, label: 'Siap diputus', ket: 'mesin selesai, menunggu Anda',
            ikon: 'bi-hourglass-split', warna: '#d97706', lompat: 'aksi',
            judul: 'Lihat daftar keputusan yang menggantung',
        },
        {
            kunci: 'nungguTes', nilai: k.nungguTes, label: 'Menunggu hasil tes', ket: 'di penyedia tes',
            ikon: 'bi-clipboard-check', warna: '#0284c7', lompat: 'aksi',
        },
        {
            kunci: 'macet', nilai: k.macet, label: `Macet > ${props.ambang.macetHari} hari`,
            ket: 'tahap berjalan terlalu lama', ikon: 'bi-cone-striped', warna: '#d03b3b', lompat: 'aksi',
        },
        {
            kunci: 'lulus', nilai: k.lulus, label: 'Diterima', ket: 'kursi MPP terisi',
            ikon: 'bi-patch-check-fill', warna: '#0ca30c', lompat: 'program',
        },
        {
            kunci: 'gugur', nilai: k.gugur, label: 'Tidak lolos', ket: 'selesai, tidak lanjut',
            ikon: 'bi-x-circle', warna: '#64748b',
        },
        {
            kunci: 'talent', nilai: k.talent, label: 'Talent pool', ket: 'disimpan untuk lain kali',
            ikon: 'bi-bookmark-star-fill', warna: '#7c3aed', tautan: '/karir/talent-pool',
            judul: 'Buka Talent Pool',
        },
        {
            kunci: 'baru', nilai: k.baru, label: 'Lamaran baru', ket: labelPeriode.value,
            ikon: 'bi-plus-circle-fill', warna: '#6366f1', lompat: 'tren',
            delta: k.baruSebelum === null || k.baruSebelum === undefined ? null : k.baru - k.baruSebelum,
            judul: k.baruSebelum === null || k.baruSebelum === undefined
                ? 'Lihat grafik tren'
                : `Periode sebelumnya: ${k.baruSebelum}`,
        },
    ];
});
</script>
