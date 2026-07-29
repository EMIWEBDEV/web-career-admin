<!--
  Penyaring pelamar di dalam papan Spotlight.

  Tiga kelompok, sengaja dipisah karena dipakai pada momen yang berbeda:

   - Baris tetap   : cari nama/kode, posisi, status lamaran, kondisi proses.
                     Ini yang dipakai tiap hari, jadi selalu terlihat.
   - Panel isian   : PENDIDIKAN (jenjang, jenis institusi, kampus/sekolah,
                     jurusan, fakultas, status pendidikan, tahun lulus) dan
                     isian lain milik program itu. Disembunyikan di balik satu
                     tombol supaya baris atas tidak jadi dinding dropdown —
                     tapi apa pun yang sedang aktif tetap muncul sebagai chip,
                     jadi tidak ada penyaring yang bekerja diam-diam.

  Daftar filternya datang dari server (`filterAtribut`), diturunkan dari isian
  formulir program itu sendiri — bukan daftar yang ditulis di sini.
-->
<template>
    <div class="wcm-fp">
        <div class="wcm-fp__baris">
            <div class="wca-search2 wcm-fp__cari">
                <i class="bi bi-search"></i>
                <input :value="nilai.cari" type="text" placeholder="Cari nama atau kode lamaran…"
                    @input="ubah('cari', $event.target.value)" />
            </div>

            <span v-if="posisiOpsi.length > 1" class="wcm-fp__sel">
                <el-select :model-value="nilai.posisi" placeholder="Posisi" clearable
                    @update:model-value="ubah('posisi', $event ?? '')">
                    <el-option v-for="p in posisiOpsi" :key="p" :label="p" :value="p" />
                </el-select>
            </span>

            <button v-if="filterAtribut.length" type="button" class="wcm-fp__toggle"
                :class="{ 'is-open': panelBuka, 'is-active': jumlahAtributAktif > 0 }"
                @click="panelBuka = !panelBuka">
                <i class="bi bi-mortarboard"></i>
                Pendidikan &amp; isian
                <span v-if="jumlahAtributAktif" class="wcm-fp__lencana">{{ jumlahAtributAktif }}</span>
                <i class="bi" :class="panelBuka ? 'bi-chevron-up' : 'bi-chevron-down'"></i>
            </button>
        </div>

        <div class="wcm-fp__baris">
            <button v-for="s in STATUS" :key="s.val" type="button" class="wcm-fchip"
                :class="{ 'is-active': nilai.status === s.val }"
                @click="ubah('status', nilai.status === s.val ? '' : s.val)">{{ s.label }}</button>

            <span class="wcm-fp__pisah"></span>

            <button v-for="k in KONDISI" :key="k.val" type="button" class="wcm-fchip"
                :class="{ 'is-active': nilai.kondisi === k.val }" :title="k.ket"
                @click="ubah('kondisi', nilai.kondisi === k.val ? '' : k.val)">
                <i class="bi" :class="k.ikon"></i> {{ k.label }}
            </button>

            <span v-if="adaFilter" class="wcm-fp__hasil">
                <b>{{ jumlahTampil }}</b> dari {{ jumlahTotal }} pelamar
                <button type="button" class="wcm-fp__reset" @click="$emit('reset')">
                    <i class="bi bi-x-circle"></i> Bersihkan
                </button>
            </span>
        </div>

        <!-- Panel isian: pendidikan dulu (urutannya mengikuti alur pertanyaan),
             lalu isian lain milik program ini. -->
        <transition name="wcm-fpanel">
            <div v-if="panelBuka" class="wcm-fp__panel">
                <template v-for="g in grup" :key="g.kunci">
                    <div v-if="g.daftar.length" class="wcm-fp__grup">
                        <span class="wcm-fp__grup-lbl"><i class="bi" :class="g.ikon"></i> {{ g.judul }}</span>
                        <div class="wcm-fp__grid">
                            <label v-for="f in g.daftar" :key="f.key" class="wcm-fp__item">
                                <span class="wcm-fp__item-lbl">
                                    <i v-if="f.ikon" class="bi" :class="f.ikon"></i> {{ f.label }}
                                </span>
                                <el-select :model-value="nilai.atribut[f.key] ?? ''" placeholder="Semua" clearable
                                    :filterable="f.cari" :title="f.label"
                                    @update:model-value="ubahAtribut(f.key, $event ?? '')">
                                    <el-option v-for="o in f.opsi" :key="o" :label="String(o)" :value="String(o)" />
                                </el-select>
                                <small class="wcm-fp__item-ket">{{ f.opsi.length }} pilihan</small>
                            </label>
                        </div>
                    </div>
                </template>
            </div>
        </transition>

        <!-- Ringkasan isian yang sedang aktif. Tetap ada walau panelnya ditutup,
             supaya tidak ada penyaring tersembunyi yang membuat angka di papan
             terlihat salah tanpa sebab. -->
        <div v-if="atributAktif.length" class="wcm-fp__aktif">
            <span class="wcm-fp__aktif-lbl">Disaring:</span>
            <button v-for="a in atributAktif" :key="a.key" type="button" class="wcm-fchip is-active is-hapus"
                :title="`Hapus filter ${a.label}`" @click="ubahAtribut(a.key, '')">
                <i v-if="a.ikon" class="bi" :class="a.ikon"></i>
                <span class="wcm-fp__aktif-key">{{ a.label }}:</span> {{ a.nilai }}
                <i class="bi bi-x"></i>
            </button>
        </div>
    </div>
</template>

<script setup>
import { computed, ref } from 'vue'

const props = defineProps({
    nilai: { type: Object, required: true },
    pelamar: { type: Array, default: () => [] },      // seluruh pelamar (belum difilter)
    filterAtribut: { type: Array, default: () => [] },
    jumlahTampil: { type: Number, default: 0 },
    jumlahTotal: { type: Number, default: 0 },
    adaFilter: { type: Boolean, default: false },
})
const emit = defineEmits(['ubah', 'reset'])

const panelBuka = ref(false)

const STATUS = [
    { val: 'BERJALAN', label: 'Berjalan' },
    { val: 'LULUS', label: 'Diterima' },
    { val: 'GUGUR', label: 'Tidak lolos' },
    { val: 'TALENT_POOL', label: 'Talent pool' },
]

const KONDISI = [
    { val: 'SIAP', label: 'Siap diputus', ikon: 'bi-hammer', ket: 'Semua hasil terkumpul, menunggu diketuk' },
    { val: 'MACET', label: 'Tertahan lama', ikon: 'bi-exclamation-octagon', ket: 'Melewati ambang lama menunggu' },
    { val: 'NUNGGU_TES', label: 'Menunggu tes', ikon: 'bi-hourglass-split', ket: 'Menunggu hasil tes pihak ke-3' },
    { val: 'TALENT', label: 'Dari talent pool', ikon: 'bi-droplet', ket: 'Lamaran hasil penarikan talent pool' },
]

const posisiOpsi = computed(() =>
    [...new Set(props.pelamar.map((p) => p.posisi).filter(Boolean))].sort((a, b) => a.localeCompare(b)),
)

const grup = computed(() => [
    {
        kunci: 'pendidikan',
        judul: 'Pendidikan',
        ikon: 'bi-mortarboard',
        daftar: props.filterAtribut.filter((f) => f.grup === 'pendidikan'),
    },
    {
        kunci: 'lain',
        judul: 'Isian lain',
        ikon: 'bi-ui-checks',
        daftar: props.filterAtribut.filter((f) => f.grup !== 'pendidikan'),
    },
])

const atributAktif = computed(() =>
    props.filterAtribut
        .filter((f) => {
            const v = props.nilai.atribut?.[f.key]
            return v !== '' && v !== null && v !== undefined
        })
        .map((f) => ({ key: f.key, label: f.label, ikon: f.ikon, nilai: props.nilai.atribut[f.key] })),
)

const jumlahAtributAktif = computed(() => atributAktif.value.length)

function ubah(key, val) {
    emit('ubah', { key, val })
}

function ubahAtribut(key, val) {
    emit('ubah', { key: 'atribut', val: { ...props.nilai.atribut, [key]: val } })
}
</script>

<style scoped>
.wcm-fp { display: flex; flex-direction: column; gap: 8px; padding: 11px 20px; background: #fff; border-bottom: 1px solid #eef0f7; }
.wcm-fp__baris { display: flex; align-items: center; gap: 7px; flex-wrap: wrap; }
.wcm-fp__cari { flex: 1 1 230px; min-width: 190px; max-width: 340px; }
.wcm-fp__sel { min-width: 150px; }
.wcm-fp__pisah { width: 1px; height: 18px; background: #e4e7f0; margin: 0 3px; }

.wcm-fchip { display: inline-flex; align-items: center; gap: 5px; border: 1px solid #e4e7f0; background: #fff; padding: 5px 11px; border-radius: 99px; font-size: 11.5px; font-weight: 700; color: #64748b; cursor: pointer; }
.wcm-fchip:hover { border-color: #c7d2fe; }
.wcm-fchip.is-active { border-color: #c7d2fe; background: #eef2ff; color: #4338ca; }

.wcm-fp__hasil { margin-left: auto; display: inline-flex; align-items: center; gap: 9px; font-size: 11.5px; color: #6b7280; }
.wcm-fp__hasil b { color: #4f46e5; font-size: 12.5px; }
.wcm-fp__reset { display: inline-flex; align-items: center; gap: 5px; border: 0; background: transparent; color: #4f46e5; font-size: 11.5px; font-weight: 700; cursor: pointer; padding: 2px 5px; border-radius: 7px; }
.wcm-fp__reset:hover { background: #eef2ff; }

/* ── Tombol pembuka panel ── */
.wcm-fp__toggle { display: inline-flex; align-items: center; gap: 6px; border: 1px solid #e4e7f0; background: #fff; padding: 7px 12px; border-radius: 10px; font-size: 11.5px; font-weight: 700; color: #475569; cursor: pointer; transition: border-color 140ms ease, background 140ms ease; }
.wcm-fp__toggle:hover { border-color: #c7d2fe; }
.wcm-fp__toggle.is-open { background: #f8faff; border-color: #c7d2fe; color: #4338ca; }
.wcm-fp__toggle.is-active { border-color: #a5b4fc; color: #4338ca; }
.wcm-fp__lencana { display: inline-grid; place-items: center; min-width: 17px; height: 17px; padding: 0 5px; border-radius: 99px; background: #4f46e5; color: #fff; font-size: 10.5px; font-weight: 800; }

/* ── Panel isian ── */
.wcm-fp__panel { display: flex; flex-direction: column; gap: 12px; padding: 12px 14px; border: 1px solid #eef0f7; border-radius: 12px; background: linear-gradient(180deg, #fbfcff, #fff); }
.wcm-fp__grup { display: flex; flex-direction: column; gap: 8px; }
.wcm-fp__grup-lbl { display: inline-flex; align-items: center; gap: 6px; font-size: 10.5px; font-weight: 800; letter-spacing: 0.06em; text-transform: uppercase; color: #94a3b8; }
.wcm-fp__grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(190px, 1fr)); gap: 9px; }
.wcm-fp__item { display: flex; flex-direction: column; gap: 3px; min-width: 0; }
.wcm-fp__item-lbl { display: flex; align-items: center; gap: 5px; font-size: 11px; font-weight: 700; color: #475569; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.wcm-fp__item-lbl .bi { color: #a5b4fc; }
.wcm-fp__item-ket { font-size: 10px; color: #b6bfcd; }

.wcm-fpanel-enter-active, .wcm-fpanel-leave-active { transition: opacity 160ms ease, transform 160ms ease; }
.wcm-fpanel-enter-from, .wcm-fpanel-leave-to { opacity: 0; transform: translateY(-4px); }

/* ── Ringkasan filter aktif ── */
.wcm-fp__aktif { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
.wcm-fp__aktif-lbl { font-size: 10.5px; font-weight: 800; letter-spacing: 0.05em; text-transform: uppercase; color: #94a3b8; }
.wcm-fp__aktif-key { color: #6366f1; font-weight: 600; }
.wcm-fchip.is-hapus { max-width: 320px; }
.wcm-fchip.is-hapus > span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.wcm-fchip.is-hapus .bi-x { color: #818cf8; }
.wcm-fchip.is-hapus:hover { background: #e0e7ff; }

@media (max-width: 860px) {
    .wcm-fp { padding: 10px 14px; }
    .wcm-fp__cari, .wcm-fp__sel { min-width: 0; flex: 1 1 130px; max-width: none; }
    .wcm-fp__hasil { margin-left: 0; }
    .wcm-fp__grid { grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); }
}
</style>
