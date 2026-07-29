<!--
  SPOTLIGHT — papan proses satu program, layar penuh (read-only).
  Kolom = tahap alur, isi kolom = kartu orang yang sedang berada di tahap itu.
  Klik kartu orang → drawer perjalanan; klik header kolom → drawer detail tahap.
  Satu fetch /papan memuat seluruh papan; filter per kolom di client.
-->
<template>
    <teleport to="body">
        <div class="wcm-sl" @click.self="tutup">
            <section class="wcm-sl__panel" role="dialog" aria-label="Papan proses program">
                <!-- HEADER -->
                <header class="wcm-sl__head">
                    <div class="wcm-sl__id">
                        <span class="wcm-sl__dot" :style="{ background: program.warna || '#6366f1' }"></span>
                        <div>
                            <div class="wcm-sl__nama">{{ program.nama || 'Memuat…' }}
                                <span v-if="program.status" class="wca-badge" :class="statusBadge(program.status)">{{ program.status }}</span>
                            </div>
                            <div class="wcm-sl__sub">
                                <span v-if="program.kode">{{ program.kode }}</span>
                                <span v-if="program.kategori"> · {{ program.kategori }}</span>
                                <span v-if="program.alur"> · Alur {{ program.alur }}</span>
                                <span v-if="program.penyelenggara"> · {{ program.penyelenggara }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="wcm-sl__meta">
                        <div class="wcm-sl__ring">
                            <span class="wcm-sl__ringnum">{{ pelamarTerfilter.length }}</span>
                            <span class="wcm-sl__ringlbl">pelamar</span>
                        </div>
                        <div v-if="program.kuota > 0" class="wcm-sl__kuota">
                            <span>Kuota terisi <b>{{ program.terisi }}/{{ program.kuota }}</b></span>
                            <div class="wcm-sl__kbar"><div :class="{ 'is-full': program.terisi >= program.kuota }" :style="{ width: kuotaPct + '%' }"></div></div>
                        </div>
                        <button class="wca-iconbtn" title="Tutup (Esc)" @click="tutup"><i class="bi bi-x-lg"></i></button>
                    </div>
                </header>

                <!-- RINGKASAN CEPAT -->
                <div v-if="!loading && !error" class="wcm-sl__chips">
                    <span class="wcm-ch"><i class="bi bi-people"></i> {{ ringkas.berjalan }} berproses</span>
                    <span v-if="ringkas.siap" class="wcm-ch is-warn"><i class="bi bi-hammer"></i> {{ ringkas.siap }} siap diputus</span>
                    <span v-if="ringkas.nunggu" class="wcm-ch"><i class="bi bi-hourglass-split"></i> {{ ringkas.nunggu }} menunggu tes</span>
                    <span v-if="ringkas.lulus" class="wcm-ch is-green"><i class="bi bi-check-circle"></i> {{ ringkas.lulus }} diterima</span>
                    <span v-if="ringkas.gugur" class="wcm-ch is-red"><i class="bi bi-x-circle"></i> {{ ringkas.gugur }} tidak lolos</span>
                    <span v-if="ringkas.talent" class="wcm-ch is-sky"><i class="bi bi-droplet"></i> {{ ringkas.talent }} talent pool</span>
                    <span class="wcm-sl__hint"><i class="bi bi-hand-index"></i> {{ petunjuk }}</span>

                    <!-- Pengalih mode. Penyaring TIDAK direset saat berpindah:
                         atasan sering membandingkan dua sudut pandang atas
                         kelompok orang yang sama. -->
                    <div class="wcm-mode" role="tablist" aria-label="Mode tampilan papan">
                        <button v-for="m in MODE" :key="m.val" type="button" role="tab"
                            class="wcm-mode__b" :class="{ 'is-active': mode === m.val }"
                            :aria-selected="mode === m.val" :title="m.ket" @click="mode = m.val">
                            <i class="bi" :class="m.ikon"></i> {{ m.label }}
                        </button>
                    </div>
                </div>

                <FilterPapan v-if="!loading && !error" :nilai="filter" :pelamar="pelamar"
                    :filter-atribut="filterAtribut" :jumlah-tampil="pelamarTerfilter.length"
                    :jumlah-total="pelamar.length" :ada-filter="adaFilter"
                    @ubah="filter[$event.key] = $event.val" @reset="resetFilter" />

                <!-- PAPAN -->
                <div class="wcm-sl__body">
                    <div v-if="loading" class="wcm-sl__load"><i class="bi bi-arrow-repeat wcm-spin"></i> Memuat papan proses…</div>
                    <div v-else-if="error" class="wcm-sl__load">Gagal memuat papan.
                        <button class="wca-btn wca-btn--primary wca-btn--sm" @click="fetchPapan"><i class="bi bi-arrow-clockwise"></i> Coba lagi</button>
                    </div>
                    <div v-else-if="!kolom.length" class="wcm-sl__load">Alur belum dikonfigurasi untuk program ini.</div>

                    <FullProcessGrid v-else-if="mode === 'FULL'" :kolom="kolom" :pelamar="pelamarTerfilter"
                        :orang-terbuka="orangTerbuka"
                        @open-person="bukaOrang" @open-stage="bukaTahap" @open-cell="bukaSel" />

                    <div v-else class="wcm-board">
                        <div v-for="k in kolom" :key="k.urutan" class="wcm-col">
                            <button type="button" class="wcm-col__head"
                                :class="{ 'is-open': tahapTerbuka && tahapTerbuka.urutan === k.urutan }"
                                @click="bukaTahap(k)">
                                <span class="wcm-col__no">{{ String(k.urutan).padStart(2, '0') }}</span>
                                <span class="wcm-col__label">{{ k.label }}</span>
                                <i v-if="k.provider === 'THIRD_PARTY'" class="bi bi-robot" title="Tahap otomatis pihak ke-3"></i>
                                <span class="wcm-col__n">{{ perKolom(k.urutan).length }}</span>
                                <i class="bi bi-chevron-right wcm-col__go"></i>
                            </button>

                            <div class="wcm-col__body">
                                <div v-if="!perKolom(k.urutan).length" class="wcm-col__kosong">—</div>
                                <button v-for="o in perKolom(k.urutan)" :key="o.id" type="button" class="wcm-kartu"
                                    :class="[{ 'is-alert': o.siapDiputus, 'is-open': orangTerbuka === o.id }, 'st-' + o.status.toLowerCase()]"
                                    @click="bukaOrang(o.id)">
                                    <span class="wca-avatar wca-avatar--sm">{{ initials(o.nama) }}</span>
                                    <span class="wcm-kartu__txt">
                                        <span class="wcm-kartu__nama">{{ o.nama || '—' }}
                                            <i v-if="o.dariTalentPool" class="bi bi-droplet-fill wcm-kartu__tp" title="Dari Talent Pool"></i>
                                        </span>
                                        <span class="wcm-kartu__pos">{{ o.posisi || '—' }}</span>
                                        <span class="wcm-kartu__bar">
                                            <span class="wca-badge" :class="toneClass(o.badge.tone)">{{ o.badge.teks }}</span>
                                            <span v-if="o.agingHari !== null" class="wcm-kartu__age" :class="{ 'is-tua': o.agingHari > 7 }">{{ formatAging(o.agingHari) }}</span>
                                        </span>
                                    </span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

        </div>
    </teleport>

    <!-- Drawer DILUAR teleport di atas: masing-masing sudah meng-teleport diri
         ke <body>. Menyarangkan teleport ke target yang sama membuat Vue gagal
         mem-patch DOM (error "__vnode of null") saat isinya berubah.
         Satu drawer aktif pada satu waktu supaya tidak saling menimpa. -->
    <PersonDrawer v-if="orangTerbuka" :lamaran-id="orangTerbuka"
        @close="tutupOrang" @open-stage="tahapOrang = $event" />
    <StageDetailPanel v-else-if="tahapTerbuka" :program-id="programId" :kolom="tahapTerbuka"
        :orang="perKolom(tahapTerbuka.urutan)"
        @close="tahapTerbuka = null" @open-person="bukaOrang" />

    <!-- Lapis 3: detail satu tahap milik orang yang sedang dibuka. Label ikut
         dikirim supaya judul benar sejak detik pertama, tanpa menunggu fetch. -->
    <StagePersonDrawer v-if="orangTerbuka && tahapOrang" :lamaran-id="orangTerbuka"
        :urutan="tahapOrang.urutan" :label-awal="tahapOrang.label"
        :nama-pelamar="namaOrangTerbuka"
        @close="tahapOrang = null" />
</template>

<script setup>
import { ref, computed, nextTick, onMounted, onUnmounted } from 'vue'
import axios from 'axios'
import PersonDrawer from './PersonDrawer.vue'
import StageDetailPanel from './StageDetailPanel.vue'
import StagePersonDrawer from './StagePersonDrawer.vue'
import FilterPapan from './FilterPapan.vue'
import FullProcessGrid from './FullProcessGrid.vue'
import { initials, statusBadge } from '../careerAdmin'
import { formatAging, toneClass } from './monitoringHelpers'
import { useLapisEsc } from '../../../../composables/useLapisEsc'

const props = defineProps({
    programId: { type: String, required: true },
    // Buka langsung ke orang tertentu (dari klik item "Perlu Perhatian").
    fokusLamaranId: { type: String, default: null },
})
const emit = defineEmits(['close'])

const CFG = { headers: { Accept: 'application/json' } }
const loading = ref(false)
const error = ref(false)
const program = ref({})
const kolom = ref([])
const pelamar = ref([])
const filterAtribut = ref([])
const orangTerbuka = ref(props.fokusLamaranId)
const tahapTerbuka = ref(null)
const tahapOrang = ref(null) // urutan tahap yang dibuka DI DALAM detail orang

/**
 * DUA MODE, satu papan.
 *   LIVE — kolom berisi kartu orang yang SEKARANG ada di tahap itu.
 *          Menjawab "siapa di mana", dipakai untuk mengejar yang tertahan.
 *   FULL — satu baris penuh per orang, satu sel per tahap.
 *          Menjawab "apa yang sudah dilalui dan berapa lama".
 * Keduanya berbagi data, penyaring, dan drawer yang sama — berpindah mode
 * tidak menghapus konteks yang sudah disusun atasan.
 */
const MODE = [
    { val: 'LIVE', label: 'Live', ikon: 'bi-kanban', ket: 'Siapa berada di tahap mana saat ini' },
    { val: 'FULL', label: 'Full Process', ikon: 'bi-grid-3x3', ket: 'Perjalanan penuh tiap pelamar di semua tahap' },
]
const mode = ref('LIVE')

const petunjuk = computed(() =>
    mode.value === 'FULL'
        ? 'klik nama untuk perjalanannya · klik sel untuk detail tahap orang itu · klik judul kolom untuk detail tahap'
        : 'klik kartu orang untuk perjalanannya · klik judul kolom untuk detail tahap',
)

const FILTER_KOSONG = { cari: '', status: '', posisi: '', kondisi: '', atribut: {} }
const filter = ref({ ...FILTER_KOSONG, atribut: {} })

const adaFilter = computed(() => {
    const f = filter.value

    return !!(f.cari || f.status || f.posisi || f.kondisi
        || Object.values(f.atribut).some((v) => v !== '' && v !== null && v !== undefined))
})

function cocokKondisi(o, kondisi) {
    return {
        SIAP: o.siapDiputus,
        MACET: o.agingHari !== null && o.agingHari > 7,
        NUNGGU_TES: o.nungguSistem,
        TALENT: o.dariTalentPool,
    }[kondisi] ?? true
}

const pelamarTerfilter = computed(() => {
    const f = filter.value
    const cari = f.cari.trim().toLowerCase()
    const atr = Object.entries(f.atribut).filter(([, v]) => v !== '' && v !== null && v !== undefined)

    return pelamar.value.filter((o) => {
        if (cari && ![o.nama, o.kode].some((v) => (v || '').toLowerCase().includes(cari))) return false
        if (f.status && o.status !== f.status) return false
        if (f.posisi && o.posisi !== f.posisi) return false
        if (f.kondisi && !cocokKondisi(o, f.kondisi)) return false
        // Nilai isian dibandingkan sebagai teks — angka dari JSON bisa datang
        // sebagai number sedangkan opsi dropdown berupa string.
        for (const [k, v] of atr) {
            if (String(o.atribut?.[k] ?? '').trim() !== String(v)) return false
        }

        return true
    })
})

function resetFilter() {
    filter.value = { ...FILTER_KOSONG, atribut: {} }
}

const kuotaPct = computed(() =>
    program.value.kuota > 0 ? Math.min(100, Math.round((program.value.terisi / program.value.kuota) * 100)) : 0,
)

// Nama untuk breadcrumb lapis 3 — diambil dari payload papan (tanpa fetch lagi).
const namaOrangTerbuka = computed(
    () => pelamar.value.find((o) => o.id === orangTerbuka.value)?.nama ?? '',
)

const ringkas = computed(() => {
    const r = { berjalan: 0, lulus: 0, gugur: 0, talent: 0, siap: 0, nunggu: 0 }
    pelamarTerfilter.value.forEach((o) => {
        if (o.status === 'BERJALAN') r.berjalan++
        else if (o.status === 'LULUS') r.lulus++
        else if (o.status === 'GUGUR') r.gugur++
        else if (o.status === 'TALENT_POOL') r.talent++
        if (o.siapDiputus) r.siap++
        if (o.nungguSistem) r.nunggu++
    })
    return r
})

/** Orang di satu kolom — yang masih berproses & paling lama menunggu di atas. */
function perKolom(urutan) {
    return pelamarTerfilter.value
        .filter((o) => o.kolomUrutan === urutan)
        .sort((a, b) => {
            const aktifA = a.status === 'BERJALAN' ? 0 : 1
            const aktifB = b.status === 'BERJALAN' ? 0 : 1
            if (aktifA !== aktifB) return aktifA - aktifB
            if (a.siapDiputus !== b.siapDiputus) return a.siapDiputus ? -1 : 1
            return (b.agingHari ?? -1) - (a.agingHari ?? -1)
        })
}

async function fetchPapan() {
    loading.value = !pelamar.value.length // refresh diam-diam saat auto-refresh
    error.value = false
    try {
        const { data } = await axios.get(`/api/v1/karir/monitoring/program/${props.programId}/papan`, CFG)
        const r = data.result || {}
        program.value = r.program || {}
        kolom.value = r.kolom || []
        pelamar.value = r.pelamar || []
        filterAtribut.value = r.filterAtribut || []
    } catch (e) {
        if (!pelamar.value.length) error.value = true
    } finally {
        loading.value = false
    }
}

/** Buka detail orang; panel tahap ditutup agar tidak bertumpuk. */
function bukaOrang(id) {
    tahapTerbuka.value = null
    tahapOrang.value = null // pindah orang → tutup lapis 3 milik orang sebelumnya
    orangTerbuka.value = id
}

function tutupOrang() {
    tahapOrang.value = null
    orangTerbuka.value = null
}

/**
 * Klik SATU SEL di matriks Full Process = "tahap ini, orang ini". Membuka dua
 * lapis sekaligus (perjalanan orang + detail tahapnya) — hasilnya sama persis
 * dengan menempuhnya lewat klik bertahap, jadi tidak ada jalur baru yang harus
 * dipelajari, hanya jalan pintas.
 */
async function bukaSel({ id, urutan, label }) {
    tahapTerbuka.value = null
    orangTerbuka.value = id

    // Lapis 2 dan lapis 3 TIDAK boleh menyatu dalam satu siklus patch.
    // Keduanya meng-teleport diri ke <body>; memasangnya berbarengan membuat
    // Vue kehilangan acuan node dan melempar "__vnode of null" — persis
    // kegagalan yang dulu muncul saat teleport disarangkan. Menunggu satu
    // tick membuat drawer orang terpasang lebih dulu, baru drawer tahapnya.
    await nextTick()
    tahapOrang.value = { urutan, label }
}

/** Buka detail tahap (kolom papan); drawer orang ditutup agar tidak bertumpuk. */
function bukaTahap(k) {
    orangTerbuka.value = null
    tahapOrang.value = null
    tahapTerbuka.value = k
}

function tutup() {
    if (orangTerbuka.value || tahapTerbuka.value) return // lapisan atas yang tutup duluan
    emit('close')
}

// Lapis paling bawah — hanya menanggapi Esc bila tidak ada drawer di atasnya
// (antrean lapisan diurus useLapisEsc).
useLapisEsc(() => emit('close'))

onMounted(() => {
    fetchPapan()
    document.body.style.overflow = 'hidden'
})
onUnmounted(() => {
    document.body.style.overflow = ''
})
defineExpose({ refresh: fetchPapan })
</script>

<style scoped>
.wcm-sl {
    position: fixed;
    inset: 0;
    z-index: 1100;
    background: rgba(15, 23, 42, 0.55);
    backdrop-filter: blur(8px);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
    animation: wcmFade 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}
@keyframes wcmFade { from { opacity: 0; } to { opacity: 1; } }

.wcm-sl__panel {
    width: min(1520px, 100%);
    height: 100%;
    background: #f8fafc;
    border-radius: 24px;
    border: 1px solid rgba(255, 255, 255, 0.8);
    box-shadow: 0 25px 60px -15px rgba(15, 23, 42, 0.3);
    display: flex;
    flex-direction: column;
    overflow: hidden;
    animation: wcmPop 0.25s cubic-bezier(0.16, 1, 0.3, 1);
}
@keyframes wcmPop { from { transform: scale(0.98) translateY(12px); opacity: 0.7; } to { transform: none; opacity: 1; } }

.wcm-sl__head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
    padding: 18px 24px;
    background: #ffffff;
    border-bottom: 1px solid #f1f5f9;
    flex-wrap: wrap;
}
.wcm-sl__id { display: flex; gap: 14px; align-items: center; min-width: 0; }
.wcm-sl__dot { flex: none; width: 14px; height: 14px; border-radius: 50%; box-shadow: 0 0 10px rgba(99, 102, 241, 0.4); }
.wcm-sl__nama { display: flex; align-items: center; gap: 10px; font-size: 1.15rem; font-weight: 800; color: #0f172a; flex-wrap: wrap; letter-spacing: -0.01em; }
.wcm-sl__sub { font-size: 0.76rem; color: #64748b; margin-top: 2px; font-weight: 500; }
.wcm-sl__meta { display: flex; align-items: center; gap: 18px; }
.wcm-sl__ring { text-align: right; }
.wcm-sl__ringnum { display: block; font-size: 1.5rem; font-weight: 900; color: #4338ca; line-height: 1; font-variant-numeric: tabular-nums; }
.wcm-sl__ringlbl { font-size: 0.68rem; font-weight: 700; text-transform: uppercase; color: #64748b; letter-spacing: 0.04em; }
.wcm-sl__kuota { font-size: 0.73rem; color: #475569; min-width: 150px; font-weight: 600; }
.wcm-sl__kbar { margin-top: 5px; height: 6px; border-radius: 99px; background: #e2e8f0; overflow: hidden; }
.wcm-sl__kbar div { height: 100%; border-radius: 99px; background: linear-gradient(90deg, #6366f1, #10b981); transition: width 0.4s ease; }
.wcm-sl__kbar div.is-full { background: #ef4444; }

.wcm-sl__chips { display: flex; align-items: center; gap: 8px; padding: 12px 24px; background: #ffffff; border-bottom: 1px solid #f1f5f9; flex-wrap: wrap; }
.wcm-ch { display: inline-flex; align-items: center; gap: 6px; font-size: 0.72rem; font-weight: 700; color: #475569; background: #f1f5f9; border-radius: 99px; padding: 4px 12px; font-variant-numeric: tabular-nums; }
.wcm-ch.is-warn { color: #d97706; background: rgba(245, 158, 11, 0.14); }
.wcm-ch.is-green { color: #047857; background: rgba(16, 185, 129, 0.14); }
.wcm-ch.is-red { color: #be123c; background: rgba(244, 63, 94, 0.12); }
.wcm-ch.is-sky { color: #0369a1; background: rgba(14, 165, 233, 0.13); }
.wcm-sl__hint { margin-left: auto; display: inline-flex; align-items: center; gap: 6px; font-size: 0.70rem; color: #94a3b8; font-weight: 500; }

/* Pengalih mode Live / Full Process — segmented control. */
.wcm-mode { display: inline-flex; gap: 2px; padding: 2px; border: 1px solid #e4e7f0; border-radius: 10px; background: #f6f7fb; }
.wcm-mode__b { display: inline-flex; align-items: center; gap: 5px; border: 0; border-radius: 8px; background: transparent; padding: 5px 11px; font: inherit; font-size: 11.5px; font-weight: 700; color: #64748b; cursor: pointer; transition: background 140ms ease, color 140ms ease, box-shadow 140ms ease; }
.wcm-mode__b:hover { color: #4338ca; }
.wcm-mode__b.is-active { background: #fff; color: #4338ca; box-shadow: 0 1px 3px rgba(15, 23, 42, 0.12); }

.wcm-sl__body { flex: 1; overflow: hidden; padding: 16px 24px 20px; }
.wcm-sl__load { font-size: 0.85rem; color: #64748b; display: flex; align-items: center; gap: 10px; padding: 30px 2px; font-weight: 600; }

.wcm-board { display: flex; gap: 14px; height: 100%; overflow-x: auto; padding-bottom: 8px; }
.wcm-col { flex: 0 0 270px; display: flex; flex-direction: column; background: #ffffff; border: 1px solid rgba(226, 232, 240, 0.95); border-radius: 18px; overflow: hidden; box-shadow: 0 2px 10px rgba(15, 23, 42, 0.02); }
.wcm-col__head { display: flex; align-items: center; gap: 8px; width: 100%; text-align: left; border: 0; border-bottom: 1px solid #f1f5f9; background: #fafafa; padding: 12px 14px; cursor: pointer; transition: all 0.2s ease; }
.wcm-col__head:hover { background: #eef2ff; }
.wcm-col__head.is-open { background: #eef2ff; box-shadow: inset 3px 0 0 #6366f1; }
.wcm-col__no { font-size: 0.65rem; font-weight: 800; color: #94a3b8; letter-spacing: 0.06em; }
.wcm-col__label { flex: 1; font-size: 0.80rem; font-weight: 800; color: #0f172a; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.wcm-col__head .bi-robot { color: #d97706; font-size: 0.75rem; }
.wcm-col__n { font-size: 0.68rem; font-weight: 800; color: #4338ca; background: #eef2ff; border-radius: 99px; padding: 2px 8px; font-variant-numeric: tabular-nums; }
.wcm-col__go { color: #94a3b8; font-size: 0.72rem; transition: transform 0.2s ease; }
.wcm-col__head:hover .wcm-col__go { transform: translateX(2px); color: #6366f1; }
.wcm-col__body { flex: 1; overflow-y: auto; padding: 10px; display: flex; flex-direction: column; gap: 8px; }
.wcm-col__kosong { text-align: center; color: #cbd5e1; font-size: 1.1rem; padding: 20px 0; font-weight: 600; }

.wcm-kartu { display: flex; gap: 10px; align-items: flex-start; width: 100%; text-align: left; border: 1px solid #f1f5f9; border-radius: 14px; background: #ffffff; padding: 10px 12px; cursor: pointer; transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1); box-shadow: 0 1px 4px rgba(15, 23, 42, 0.02); }
.wcm-kartu:hover { transform: translateY(-2px); border-color: rgba(99, 102, 241, 0.4); box-shadow: 0 8px 18px rgba(99, 102, 241, 0.1); }
.wcm-kartu.is-alert { border-color: rgba(245, 158, 11, 0.5); background: linear-gradient(135deg, #ffffec, #ffffff); }
.wcm-kartu.is-open { border-color: #6366f1; box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.25); opacity: 1; }
.wcm-kartu.st-gugur { opacity: 0.65; }
.wcm-kartu.st-lulus { border-color: #a7f3d0; }
.wcm-kartu.st-talent_pool { border-color: #bae6fd; }
.wcm-kartu__txt { flex: 1; min-width: 0; }
.wcm-kartu__nama { display: block; font-size: 0.78rem; font-weight: 800; color: #0f172a; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.wcm-kartu__tp { color: #0284c7; font-size: 0.68rem; margin-left: 2px; }
.wcm-kartu__pos { display: block; font-size: 0.70rem; color: #64748b; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; margin-bottom: 6px; font-weight: 500; }
.wcm-kartu__bar { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
.wcm-kartu__age { font-size: 0.65rem; color: #94a3b8; font-weight: 700; font-variant-numeric: tabular-nums; }
.wcm-kartu__age.is-tua { color: #e11d48; }
.wcm-spin { animation: wcmSpin 0.9s linear infinite; display: inline-block; }
@keyframes wcmSpin { to { transform: rotate(360deg); } }

@media (max-width: 860px) {
    .wcm-sl { padding: 0; }
    .wcm-sl__panel { border-radius: 0; }
    .wcm-col { flex-basis: 230px; }
    .wcm-sl__hint { display: none; }
}
</style>
