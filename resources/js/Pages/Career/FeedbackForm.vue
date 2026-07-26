<template>
  <CareerLayout>
    <div class="fb-root" :class="{ 'fb-root--done': success }">
      <!-- Success -->
      <div v-if="success" class="fb-done">
        <div class="fb-done__circle">
          <svg viewBox="0 0 52 52"><path fill="none" stroke="#10b981" stroke-width="4" d="M14 27l7 7 16-16"/></svg>
        </div>
        <h1>Terima Kasih! 🎉</h1>
        <p>Feedback kamu sudah kami terima. Masukan ini sangat berarti untuk kami.</p>
        <p v-if="is_wajib" class="fb-done__note">Mengarahkan ke Portal Kandidat...</p>
      </div>

      <!-- Error -->
      <div v-else-if="error" class="fb-err">
        <div class="fb-err__icon"><i :class="errorIcon"></i></div>
        <h2>{{ stateTitle }}</h2>
        <p>{{ message }}</p>
        <a :href="error === 'already_submitted' || error === 'expired' ? '/kandidat/portal' : '/'">
          {{ error === 'already_submitted' || error === 'expired' ? 'Portal Kandidat →' : '← Beranda' }}
        </a>
      </div>

      <!-- Form -->
      <template v-else>
        <!-- Cover -->
        <section class="fb-cover">
          <div class="fb-cover__bg"></div>
          <div class="fb-cover__inner">
            <div class="fb-cover__pill" :class="{ 'fb-cover__pill--wajib': is_wajib }">
              {{ is_wajib ? 'WAJIB DIISI' : 'OPSIONAL' }}
            </div>
            <h1 class="fb-cover__title">{{ is_wajib ? 'Selamat!' : 'Hai!' }} <span class="fb-cover__wave">👋</span></h1>
            <p class="fb-cover__body">
              <template v-if="is_wajib">
                Kamu <strong>diterima</strong> di EVO Group! Sebelum lanjut, bantu kami isi feedback singkat ini ya — cuma <strong>2 menit</strong>.
              </template>
              <template v-else>
                Terima kasih sudah melamar. Bantu kami jadi lebih baik — isi feedback singkat tentang pengalamanmu.
              </template>
            </p>
            <div class="fb-cover__stats">
              <div class="fb-cover__stat"><strong>{{ pertanyaan?.length || 0 }}</strong><span>pertanyaan</span></div>
              <div class="fb-cover__stat"><strong>~2</strong><span>menit</span></div>
            </div>
            <button class="fb-cover__start" @click="started = true">
              Mulai Isi Feedback <i class="bi bi-arrow-down"></i>
            </button>
          </div>
        </section>

        <!-- Questions (show after clicking Mulai) -->
        <section v-if="started" class="fb-body">
          <div class="fb-body__inner">
            <!-- Sticky progress -->
            <div class="fb-sticky" :class="{ 'fb-sticky--done': isComplete }">
              <div class="fb-sticky__bar"><div class="fb-sticky__fill" :style="{ width: progressPercent + '%' }"></div></div>
              <div class="fb-sticky__info">
                <span>{{ answeredCount }}/{{ pertanyaan?.length }}</span>
                <span v-if="isComplete">✅ Siap kirim!</span>
                <span v-else>● Mengisi</span>
              </div>
            </div>

            <!-- SCROLL -->
            <div v-if="mode_tampilan === 'SCROLL'" class="fb-cards">
              <div v-for="(p, idx) in pertanyaan" :key="p.Id_Master_Feedback_Pertanyaan" class="fb-card" :class="{ 'fb-card--done': !!jawaban[p.Id_Master_Feedback_Pertanyaan] }">
                <div class="fb-card__top">
                  <span class="fb-card__n">{{ idx + 1 }}</span>
                  <span class="fb-card__t">{{ typeLabel(p.Tipe) }}</span>
                  <span v-if="jawaban[p.Id_Master_Feedback_Pertanyaan]" class="fb-card__ok">✓</span>
                </div>
                <p class="fb-card__q">{{ p.Label }}</p>
                <component :is="inputComponent(p.Tipe)" :pertanyaan="p" :model-value="jawaban[p.Id_Master_Feedback_Pertanyaan]" @update:model-value="v => jawaban[p.Id_Master_Feedback_Pertanyaan] = v" />
              </div>

              <button class="fb-btn" :class="{ 'fb-btn--on': isComplete }" :disabled="submitting || !isComplete" @click="submitFeedback">
                <span v-if="submitting" class="fb-btn__spin"></span>
                <template v-else>Kirim Feedback <i class="bi bi-send-fill"></i></template>
              </button>
              <p v-if="submitError" class="fb-err-msg">{{ submitError }}</p>
            </div>

            <!-- WIZARD -->
            <FeedbackFormWizard v-else :pertanyaan="pertanyaan" :is-wajib="is_wajib" @submit="handleWizardSubmit" />
          </div>
        </section>
      </template>
    </div>
  </CareerLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import axios from 'axios'
import CareerLayout from './Layouts/CareerLayout.vue'
import FeedbackFormWizard from './FeedbackFormWizard.vue'
import RatingInput from './components/feedback/RatingInput.vue'
import NpsInput from './components/feedback/NpsInput.vue'
import LikertInput from './components/feedback/LikertInput.vue'
import TextareaInput from './components/feedback/TextareaInput.vue'
import RadioCards from './components/feedback/RadioCards.vue'
import CheckboxCards from './components/feedback/CheckboxCards.vue'
import DropdownSelect from './components/feedback/DropdownSelect.vue'

const props = defineProps({ feedback: Object, pertanyaan: Array, is_wajib: Boolean, mode_tampilan: String, error: { type: String, default: null }, message: { type: String, default: null } })
const jawaban = ref({}); const submitting = ref(false); const submitError = ref(null); const success = ref(false); const started = ref(false)

const stateTitle = computed(() => ({ invalid: 'Link Tidak Valid', expired: 'Link Kadaluarsa', already_submitted: 'Feedback Sudah Terkirim' }[props.error] || ''))
const errorIcon = computed(() => ({ invalid: 'bi bi-link-45deg', expired: 'bi bi-hourglass-split', already_submitted: 'bi bi-check-circle-fill' }[props.error] || 'bi bi-exclamation-circle'))
const answeredCount = computed(() => (props.pertanyaan||[]).filter(p => jawaban.value[p.Id_Master_Feedback_Pertanyaan] != null && jawaban.value[p.Id_Master_Feedback_Pertanyaan] !== '').length)
const isComplete = computed(() => props.pertanyaan?.every(p => jawaban.value[p.Id_Master_Feedback_Pertanyaan] != null && jawaban.value[p.Id_Master_Feedback_Pertanyaan] !== ''))
const progressPercent = computed(() => props.pertanyaan?.length ? Math.round(answeredCount.value / props.pertanyaan.length * 100) : 0)

function inputComponent(t) { const m = { RATING: RatingInput, NPS: NpsInput, LIKERT: LikertInput, TEXTAREA: TextareaInput, RADIO: RadioCards, CHECKBOX: CheckboxCards, DROPDOWN: DropdownSelect }; return m[t] || TextareaInput }
function typeLabel(t) { const m = { RATING: '⭐ Rating', NPS: '📊 NPS', LIKERT: '📐 Likert', TEXTAREA: '📝 Teks', RADIO: '🔘 Pilih Satu', CHECKBOX: '☑️ Multi-Pilih', DROPDOWN: '🔽 Dropdown' }; return m[t] || t }

async function submitFeedback() {
  if (!isComplete.value) return; submitting.value = true; submitError.value = null
  try {
    const arr = Object.entries(jawaban.value).map(([id,val]) => ({ id_pertanyaan: parseInt(id), jawaban: Array.isArray(val) ? val : String(val) }))
    const { data } = await axios.post(window.location.pathname, { jawaban: arr })
    if (data.success) { success.value = true; if (data.result?.redirect_to) setTimeout(() => window.location.href = data.result.redirect_to, 3000) }
  } catch (e) { submitError.value = e.response?.data?.message || 'Gagal. Coba lagi.' } finally { submitting.value = false }
}
function handleWizardSubmit(w) { jawaban.value = w; submitFeedback() }
</script>

<style scoped>
/* ── Root ── */
.fb-root { max-width: 720px; margin: 0 auto; padding: 0 20px 60px; }
.fb-root--done { padding-top: 80px; }

/* ── Success ── */
.fb-done { text-align: center; }
.fb-done__circle { width: 80px; height: 80px; margin: 0 auto 24px; }
.fb-done__circle svg { width: 100%; height: 100%; }
.fb-done__circle path { stroke-dasharray: 50; stroke-dashoffset: 50; animation: fb-draw .6s .2s ease forwards; }
@keyframes fb-draw { to { stroke-dashoffset: 0; } }
.fb-done h1 { font-size: 1.8rem; font-weight: 800; color: #1e293b; margin: 0 0 10px; }
.fb-done p { font-size: .95rem; color: #64748b; line-height: 1.6; margin: 0; }
.fb-done__note { font-size: .8rem !important; color: #94a3b8 !important; margin-top: 16px !important; }

/* ── Error ── */
.fb-err { text-align: center; padding-top: 80px; }
.fb-err__icon { font-size: 2.5rem; color: #6366f1; margin-bottom: 16px; }
.fb-err h2 { font-size: 1.3rem; font-weight: 700; color: #1e293b; margin: 0 0 8px; }
.fb-err p { font-size: .88rem; color: #64748b; margin: 0 0 20px; }
.fb-err a { padding: 10px 24px; background: linear-gradient(135deg,#6366f1,#4f46e5); color: #fff; border-radius: 10px; text-decoration: none; font-weight: 700; font-size: .85rem; }

/* ── Cover ── */
.fb-cover { position: relative; text-align: center; padding: 60px 24px 48px; border-radius: 24px; overflow: hidden; margin-top: 32px; }
.fb-cover__bg { position: absolute; inset: 0; background: linear-gradient(160deg, #f5f3ff 0%, #ede9fe 30%, #faf9ff 60%, #fff 100%); border: 1.5px solid #e8e5f7; border-radius: 24px; }
.fb-cover__inner { position: relative; z-index: 1; }
.fb-cover__pill { display: inline-block; padding: 5px 16px; border-radius: 20px; font-size: .68rem; font-weight: 800; letter-spacing: .1em; background: #e2e8f0; color: #64748b; margin-bottom: 16px; }
.fb-cover__pill--wajib { background: #fef3c7; color: #b45309; }
.fb-cover__title { font-size: 2.2rem; font-weight: 800; color: #1e293b; margin: 0 0 14px; }
.fb-cover__wave { display: inline-block; animation: fb-wave 1.5s ease-in-out infinite; }
@keyframes fb-wave { 0%,100%{transform:rotate(0)} 25%{transform:rotate(15deg)} 75%{transform:rotate(-10deg)} }
.fb-cover__body { font-size: .95rem; color: #475569; line-height: 1.7; max-width: 480px; margin: 0 auto; }
.fb-cover__stats { display: flex; justify-content: center; gap: 24px; margin: 24px 0; }
.fb-cover__stat { text-align: center; }
.fb-cover__stat strong { display: block; font-size: 1.6rem; font-weight: 800; color: #6366f1; }
.fb-cover__stat span { font-size: .72rem; color: #94a3b8; font-weight: 600; }
.fb-cover__start { display: inline-flex; align-items: center; gap: 8px; padding: 14px 36px; border: none; border-radius: 14px; background: linear-gradient(135deg,#6366f1,#4f46e5); color: #fff; font-size: .95rem; font-weight: 700; cursor: pointer; box-shadow: 0 6px 20px rgba(99,102,241,.25); transition: all .2s; }
.fb-cover__start:hover { box-shadow: 0 10px 28px rgba(99,102,241,.35); transform: translateY(-1px); }

/* ── Body ── */
.fb-body { margin-top: 32px; }
.fb-body__inner { display: flex; flex-direction: column; gap: 20px; }

/* ── Sticky Progress ── */
.fb-sticky { position: sticky; top: 80px; z-index: 10; background: rgba(255,255,255,.85); backdrop-filter: blur(12px); border: 1px solid #e2e8f0; border-radius: 14px; padding: 12px 16px; margin-bottom: 8px; }
.fb-sticky--done { border-color: #a7f3d0; background: rgba(240,253,244,.9); }
.fb-sticky__bar { height: 6px; background: #f1f5f9; border-radius: 3px; overflow: hidden; margin-bottom: 6px; }
.fb-sticky__fill { height: 100%; border-radius: 3px; background: linear-gradient(90deg,#6366f1,#818cf8); transition: width .5s ease; }
.fb-sticky__info { display: flex; justify-content: space-between; font-size: .74rem; font-weight: 700; color: #6366f1; }

/* ── Cards ── */
.fb-cards { display: flex; flex-direction: column; gap: 16px; }
.fb-card { background: #fff; border: 1.5px solid #eeeaf8; border-radius: 18px; padding: 22px 24px; transition: all .25s; }
.fb-card:hover { border-color: #c4b5fd; }
.fb-card:focus-within { border-color: #6366f1; box-shadow: 0 0 0 4px rgba(99,102,241,.06); }
.fb-card--done { border-color: #a7f3d0; }
.fb-card__top { display: flex; align-items: center; gap: 8px; margin-bottom: 12px; }
.fb-card__n { width: 26px; height: 26px; border-radius: 8px; background: linear-gradient(135deg,#6366f1,#4f46e5); color: #fff; font-size: .72rem; font-weight: 700; display: flex; align-items: center; justify-content: center; }
.fb-card__t { font-size: .7rem; font-weight: 700; color: #6366f1; background: rgba(99,102,241,.06); padding: 3px 10px; border-radius: 20px; }
.fb-card__ok { margin-left: auto; color: #10b981; font-weight: 700; font-size: .85rem; }
.fb-card__q { font-size: .98rem; font-weight: 650; color: #1e293b; margin: 0 0 14px; line-height: 1.5; }

/* ── Button ── */
.fb-btn { display: flex; align-items: center; justify-content: center; gap: 10px; width: 100%; padding: 16px; border: none; border-radius: 14px; font-size: 1rem; font-weight: 700; cursor: pointer; background: #f1f5f9; color: #94a3b8; transition: all .3s; }
.fb-btn--on { background: linear-gradient(135deg,#6366f1,#4f46e5); color: #fff; box-shadow: 0 8px 24px rgba(99,102,241,.25); animation: fb-glow 2s ease-in-out infinite; }
@keyframes fb-glow { 0%,100%{box-shadow:0 8px 24px rgba(99,102,241,.25)} 50%{box-shadow:0 8px 36px rgba(99,102,241,.4)} }
.fb-btn--on:hover { transform: translateY(-1px); }
.fb-btn:disabled { cursor: not-allowed; }
.fb-btn__spin { width: 20px; height: 20px; border: 2.5px solid rgba(255,255,255,.3); border-top-color: #fff; border-radius: 50%; animation: fb-spin .7s linear infinite; }
@keyframes fb-spin { to { transform: rotate(360deg); } }
.fb-err-msg { text-align: center; color: #ef4444; font-size: .82rem; margin-top: 4px; }
</style>
