<!--
  Detail perjalanan penuh satu lamaran, isi PersonDrawer dari papan Spotlight.
  Isi: identitas + stepper vertikal per tahap (tanggal, keputusan, rapor
  sub-tes) + jejak keputusan + blok talent pool (masuk pool / asal tarikan).
  Judul tiap tahap dapat diklik → offcanvas detail tahap (lapis 3).
-->
<template>
    <div class="wcm-det">
        <div v-if="loading" class="wcm-det__load"><i class="bi bi-arrow-repeat wcm-spin"></i> Memuat perjalanan…</div>
        <div v-else-if="error" class="wcm-det__load">Gagal memuat detail. <button class="wca-btn wca-btn--ghost wca-btn--sm" @click="fetchDetail">Coba lagi</button></div>

        <template v-else-if="d">
            <div class="wcm-idn">
                <span class="wca-avatar">{{ initials(d.lamaran.nama) }}</span>
                <div class="wcm-idn__txt">
                    <div class="wcm-idn__nama">{{ d.lamaran.nama || '—' }}</div>
                    <div class="wcm-idn__sub">{{ d.lamaran.kode }} · {{ d.lamaran.posisi || '—' }}</div>
                    <div class="wcm-idn__sub">{{ d.lamaran.programNama || '—' }}<span v-if="d.lamaran.email"> · {{ d.lamaran.email }}</span></div>
                </div>
                <span class="wca-badge" :class="statusKelas(d.lamaran.status)">{{ statusTeks(d.lamaran.status) }}</span>
            </div>

            <div class="wcm-det__grid">
                <!-- Stepper tahap -->
                <div class="wcm-det__steps">
                    <div class="wcm-det__ttl"><i class="bi bi-signpost-split"></i> Perjalanan Tahap</div>
                    <div class="wcm-det__meta">
                        Melamar: <b>{{ formatTanggal(d.lamaran.waktuLamar) }}</b>
                        <template v-if="d.lamaran.waktuSelesai"> · Selesai: <b>{{ formatTanggal(d.lamaran.waktuSelesai) }}</b></template>
                        <template v-if="d.lamaran.mulaiDariUrutan && d.lamaran.mulaiDariUrutan > 1"> · Masuk langsung di tahap {{ d.lamaran.mulaiDariUrutan }} (tarikan talent pool)</template>
                    </div>

                    <div v-if="!d.tahap.length" class="wcm-det__load">Belum ada tahap tercatat untuk lamaran ini.</div>

                    <div v-for="t in d.tahap" :key="t.urutan" class="wcm-step" :class="['st-' + stepState(t), { 'is-klik': bisaBuka }]">
                        <div class="wcm-step__rail"><span class="wcm-step__dot"></span></div>
                        <div class="wcm-step__body">
                            <component :is="bisaBuka ? 'button' : 'div'" class="wcm-step__head"
                                :type="bisaBuka ? 'button' : null"
                                :title="bisaBuka ? 'Lihat detail tahap ini' : null"
                                @click="bisaBuka && $emit('open-stage', { urutan: t.urutan, label: t.label })">
                                <b>{{ String(t.urutan).padStart(2, '0') }} · {{ t.label }}</b>
                                <i v-if="t.provider === 'THIRD_PARTY'" class="bi bi-robot" title="Tahap otomatis pihak ke-3"></i>
                                <span class="wca-badge" :class="hasilBadge(t)">{{ hasilLabel(t) }}</span>
                                <span v-if="t.skor !== null" class="wcm-step__skor">skor {{ t.skor }}</span>
                                <i v-if="bisaBuka" class="bi bi-chevron-right wcm-step__go"></i>
                            </component>
                            <div class="wcm-step__waktu">
                                <span v-if="t.waktuMulai">mulai {{ formatTanggal(t.waktuMulai) }}</span>
                                <span v-if="t.waktuSelesai"> · selesai {{ formatTanggal(t.waktuSelesai) }}</span>
                                <span v-if="t.diputusAt"> · diputus {{ formatTanggal(t.diputusAt) }}<template v-if="t.diputusBy"> oleh {{ t.diputusBy }}</template></span>
                            </div>
                            <div v-if="t.rekomendasiAlasan || t.catatan" class="wcm-step__note">{{ t.rekomendasiAlasan || t.catatan }}</div>

                            <div v-if="tesBermakna(t.tests).length" class="wcm-tests">
                                <div v-for="(x, i) in tesBermakna(t.tests)" :key="i" class="wcm-test">
                                    <span class="wcm-test__label">{{ x.label || x.jenisTes }}<span v-if="x.peran === 'INFORMATIF'" class="wcm-test__info">informatif</span></span>
                                    <span v-if="x.nilai !== null" class="wcm-test__nilai">{{ x.nilai }}</span>
                                    <span class="wca-badge" :class="testBadge(x)">{{ labelStatusTes(x) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-if="d.lamaran.alasanGugur" class="wcm-gugur"><i class="bi bi-x-octagon"></i> {{ d.lamaran.alasanGugur }}</div>
                </div>

                <!-- Jejak + talent pool -->
                <div class="wcm-det__side">
                    <div v-if="d.asalTalentPool" class="wcm-pool wcm-pool--asal">
                        <div class="wcm-det__ttl"><i class="bi bi-box-arrow-in-right"></i> Asal Talent Pool</div>
                        <p>Ditarik dari pool <b>{{ d.asalTalentPool.programNama || '—' }}</b><template v-if="d.asalTalentPool.tahapAsal"> (tahap asal: {{ d.asalTalentPool.tahapAsal }})</template>, masuk pool {{ formatTanggal(d.asalTalentPool.tanggalMasuk) }}.</p>
                    </div>

                    <div v-if="d.talentPool" class="wcm-pool">
                        <div class="wcm-det__ttl"><i class="bi bi-droplet"></i> Talent Pool</div>
                        <p>Status <b>{{ d.talentPool.status }}</b><template v-if="d.talentPool.tahapAsal"> · dari tahap {{ d.talentPool.tahapAsal }}</template><template v-if="d.talentPool.tanggalMasuk"> · masuk {{ formatTanggal(d.talentPool.tanggalMasuk) }}</template><template v-if="d.talentPool.tanggalKedaluwarsa"> · kedaluwarsa {{ formatTanggal(d.talentPool.tanggalKedaluwarsa) }}</template>.</p>
                        <p v-if="d.talentPool.ditarikKeKode">Sudah ditarik ke lamaran <b>{{ d.talentPool.ditarikKeKode }}</b> pada {{ formatTanggal(d.talentPool.ditarikAt) }}.</p>
                        <p v-if="d.talentPool.catatan" class="wcm-pool__note">{{ d.talentPool.catatan }}</p>
                    </div>

                    <div class="wcm-det__ttl"><i class="bi bi-journal-text"></i> Jejak Keputusan</div>
                    <div v-if="!d.jejak.length" class="wcm-jejak__kosong">Belum ada keputusan tercatat.</div>
                    <div v-else class="wcm-jejak">
                        <div v-for="(j, i) in d.jejak" :key="i" class="wcm-jejak__row">
                            <span class="wca-badge" :class="verdictBadge(j.verdict)">{{ j.verdict }}</span>
                            <div class="wcm-jejak__txt">
                                <div v-if="j.ringkasan">{{ j.ringkasan }}</div>
                                <div class="wcm-jejak__meta">{{ formatTanggal(j.pada) }}<template v-if="j.oleh"> · {{ j.oleh }}</template><template v-if="j.mode"> · {{ j.mode }}</template></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </div>
</template>

<script setup>
import { ref, watch, onMounted } from 'vue'
import axios from 'axios'
import { initials } from '../careerAdmin'
import { formatTanggal, labelStatusTes, tesBermakna } from './monitoringHelpers'

const props = defineProps({
    lamaranId: { type: String, required: true },
    // Tahap dapat diklik untuk membuka offcanvas detail tahap (lapis 3).
    bisaBuka: { type: Boolean, default: false },
})
defineEmits(['open-stage'])

const CFG = { headers: { Accept: 'application/json' } }
const loading = ref(false)
const error = ref(false)
const d = ref(null)

async function fetchDetail() {
    loading.value = true
    error.value = false
    try {
        const { data } = await axios.get(`/api/v1/karir/monitoring/pelamar/${props.lamaranId}`, CFG)
        d.value = data.result || null
    } catch (e) {
        error.value = true
    } finally {
        loading.value = false
    }
}

function stepState(t) {
    if (t.hasil === 'LULUS') return 'done'
    if (t.hasil === 'GUGUR') return 'failed'
    if (t.hasil === 'TALENT_POOL') return 'talent'
    if (t.status === 'BERJALAN') return 'current'
    return 'pending'
}

function hasilLabel(t) {
    if (t.hasil === 'LULUS') return 'Lulus'
    if (t.hasil === 'GUGUR') return 'Gugur'
    if (t.hasil === 'TALENT_POOL') return 'Talent Pool'
    if (t.status === 'BERJALAN') return t.siapDiputus ? 'Siap Diputus' : 'Berjalan'
    return 'Menunggu'
}

function hasilBadge(t) {
    const s = stepState(t)
    return { done: 'wca-b--green', failed: 'wca-b--red', talent: 'wca-b--sky', current: t.siapDiputus ? 'wca-b--amber' : 'wca-b--indigo', pending: 'wca-b--slate' }[s]
}

function testBadge(x) {
    if (x.hasil === 'LULUS') return 'wca-b--green'
    if (x.hasil === 'GAGAL') return 'wca-b--red'
    if (x.status === 'SELESAI') return 'wca-b--indigo'
    if (x.status === 'TIDAK_HADIR') return 'wca-b--red'
    if (x.status === 'DIJADWALKAN') return 'wca-b--sky'
    return 'wca-b--slate'
}

function verdictBadge(v) {
    if (v === 'LULUS' || v === 'LOLOS') return 'wca-b--green'
    if (v === 'GUGUR') return 'wca-b--red'
    if (v === 'TALENT_POOL') return 'wca-b--sky'
    return 'wca-b--slate'
}

function statusKelas(s) {
    return { LULUS: 'wca-b--green', GUGUR: 'wca-b--red', TALENT_POOL: 'wca-b--sky' }[s] || 'wca-b--indigo'
}

function statusTeks(s) {
    return { LULUS: 'Diterima', GUGUR: 'Tidak Lolos', TALENT_POOL: 'Talent Pool', BERJALAN: 'Berjalan' }[s] || s
}

// Drawer dipakai ulang untuk orang lain tanpa remount → muat ulang saat id ganti.
watch(() => props.lamaranId, fetchDetail)
onMounted(fetchDetail)
defineExpose({ refresh: fetchDetail })
</script>

<style scoped>
.wcm-det__load { font-size: 0.82rem; color: #64748b; font-weight: 600; }
.wcm-det__grid { display: grid; grid-template-columns: 1fr; gap: 20px; }
.wcm-det__ttl { display: flex; align-items: center; gap: 8px; font-size: 0.76rem; font-weight: 800; color: #475569; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 10px; }
.wcm-det__meta { font-size: 0.75rem; color: #64748b; margin-bottom: 16px; font-weight: 500; }

.wcm-idn { display: flex; align-items: center; gap: 14px; padding: 14px 18px; margin-bottom: 18px; border-radius: 16px; background: linear-gradient(135deg, #eef2ff, #f8fafc); border: 1px solid rgba(99, 102, 241, 0.2); box-shadow: 0 4px 14px rgba(99, 102, 241, 0.06); }
.wcm-idn__txt { flex: 1; min-width: 0; }
.wcm-idn__nama { font-size: 1rem; font-weight: 800; color: #0f172a; letter-spacing: -0.01em; }
.wcm-idn__sub { font-size: 0.75rem; color: #64748b; margin-top: 2px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-weight: 500; }

.wcm-step { display: flex; gap: 14px; position: relative; }
.wcm-step__rail { display: flex; flex-direction: column; align-items: center; width: 18px; flex-shrink: 0; }
.wcm-step__dot { width: 12px; height: 12px; border-radius: 50%; background: #cbd5e1; margin-top: 5px; flex-shrink: 0; transition: all 0.2s ease; }
.wcm-step__rail::after { content: ''; flex: 1; width: 2px; background: #e2e8f0; margin-top: 4px; }
.wcm-step:last-of-type .wcm-step__rail::after { display: none; }
.wcm-step.st-done .wcm-step__dot { background: #6366f1; box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2); }
.wcm-step.st-current .wcm-step__dot { background: #f59e0b; box-shadow: 0 0 0 4px rgba(245, 158, 11, 0.25); animation: wcmPulse 2s infinite; }
.wcm-step.st-failed .wcm-step__dot { background: #ef4444; box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.2); }
.wcm-step.st-talent .wcm-step__dot { background: #0ea5e9; box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.2); }
@keyframes wcmPulse { 0%, 100% { transform: scale(1); } 50% { transform: scale(1.25); } }

.wcm-step__body { flex: 1; min-width: 0; padding-bottom: 18px; }
.wcm-step__head { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; font-size: 0.82rem; color: #0f172a; font-weight: 800; }
.wcm-step__head .bi-robot { color: #d97706; font-size: 0.78rem; }

button.wcm-step__head { width: 100%; text-align: left; border: 1px solid transparent; background: transparent; padding: 6px 10px; margin: -6px -10px 0; border-radius: 12px; cursor: pointer; font: inherit; transition: all 0.2s ease; }
button.wcm-step__head:hover { border-color: rgba(99, 102, 241, 0.3); background: #eef2ff; }
.wcm-step__go { margin-left: auto; color: #94a3b8; font-size: 0.75rem; transition: transform 0.2s ease; }
button.wcm-step__head:hover .wcm-step__go { color: #6366f1; transform: translateX(2px); }
.wcm-step__skor { font-size: 0.72rem; font-weight: 800; color: #4338ca; background: #eef2ff; padding: 2px 7px; border-radius: 6px; }
.wcm-step__waktu { font-size: 0.72rem; color: #64748b; margin-top: 4px; font-weight: 500; }
.wcm-step__note { margin-top: 8px; font-size: 0.75rem; color: #475569; background: #ffffff; border: 1px solid #f1f5f9; border-radius: 12px; padding: 9px 12px; font-weight: 500; }
.wcm-tests { margin-top: 10px; display: flex; flex-direction: column; gap: 6px; }
.wcm-test { display: flex; align-items: center; gap: 10px; background: #ffffff; border: 1px solid #f1f5f9; border-radius: 12px; padding: 8px 12px; font-size: 0.75rem; }
.wcm-test__label { flex: 1; font-weight: 700; color: #0f172a; }
.wcm-test__info { margin-left: 8px; font-size: 0.64rem; font-weight: 800; color: #64748b; background: #e2e8f0; padding: 2px 7px; border-radius: 99px; }
.wcm-test__nilai { font-weight: 800; color: #4338ca; font-variant-numeric: tabular-nums; }
.wcm-gugur { display: flex; gap: 10px; align-items: center; margin-top: 6px; font-size: 0.78rem; color: #be123c; background: #fff1f2; border: 1px solid #fecdd3; border-radius: 12px; padding: 10px 14px; font-weight: 600; }

.wcm-pool { margin-bottom: 18px; background: #ffffff; border: 1px solid #bae6fd; border-radius: 14px; padding: 12px 15px; }
.wcm-pool p { font-size: 0.78rem; color: #0f172a; margin: 0 0 4px; line-height: 1.55; font-weight: 600; }
.wcm-pool--asal { border-color: #7dd3fc; background: #f0f9ff; }
.wcm-pool__note { color: #64748b; font-style: italic; font-weight: 500; }
.wcm-jejak { display: flex; flex-direction: column; gap: 8px; }
.wcm-jejak__kosong { font-size: 0.78rem; color: #94a3b8; font-weight: 500; }
.wcm-jejak__row { display: flex; gap: 10px; align-items: flex-start; background: #ffffff; border: 1px solid #f1f5f9; border-radius: 12px; padding: 10px 12px; }
.wcm-jejak__txt { font-size: 0.78rem; color: #334155; min-width: 0; font-weight: 500; }
.wcm-jejak__meta { font-size: 0.70rem; color: #94a3b8; margin-top: 3px; font-weight: 500; }
.wcm-spin { animation: wcmSpin 0.9s linear infinite; display: inline-block; }
@keyframes wcmSpin { to { transform: rotate(360deg); } }
@media (max-width: 900px) { .wcm-det__grid { grid-template-columns: 1fr; } }
</style>
