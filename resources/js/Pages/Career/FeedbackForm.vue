<template>
  <CareerLayout>
    <div class="wc-feedback-page">
      <!-- Success state -->
      <div v-if="success" class="fb-success">
        <div class="fb-success__ring">
          <div class="fb-success__check">✓</div>
        </div>
        <h1 class="fb-success__title">Feedback Terkirim!</h1>
        <p class="fb-success__text">Terima kasih atas masukanmu — sangat berarti bagi kami untuk terus meningkatkan kualitas seleksi.</p>
        <p v-if="is_wajib" class="fb-success__redirect">Mengarahkan ke Portal Kandidat dalam 3 detik...</p>
      </div>

      <!-- Error states -->
      <div v-else-if="error" class="fb-error-card">
        <div class="fb-error-card__icon">
          <i :class="errorIcon"></i>
        </div>
        <h2>{{ stateTitle }}</h2>
        <p>{{ message }}</p>
        <a :href="error === 'already_submitted' || error === 'expired' ? '/kandidat/portal' : '/'" class="fb-error-card__btn">
          {{ error === 'already_submitted' || error === 'expired' ? 'Buka Portal Kandidat →' : '← Kembali ke Beranda' }}
        </a>
      </div>

      <!-- Form -->
      <template v-else>
        <!-- Hero -->
        <div class="fb-hero">
          <div class="fb-hero__icon">
            <span v-if="is_wajib">📝</span>
            <span v-else>💬</span>
          </div>
          <h1 class="fb-hero__title">{{ is_wajib ? 'Feedback Wajib' : 'Bantu Kami Lebih Baik' }}</h1>
          <p class="fb-hero__sub">
            {{ is_wajib
                ? 'Selamat! Kamu diterima di EVO Group. Mohon luangkan 2 menit untuk mengisi feedback — wajib diisi untuk menyelesaikan proses lamaran.'
                : 'Pengalamanmu sangat berharga. Isi feedback singkat ini agar kami bisa terus meningkatkan proses seleksi.' }}
          </p>
          <div class="fb-hero__meta">
            <span><i class="bi bi-clock"></i> ~2 menit</span>
            <span><i class="bi bi-list-ol"></i> {{ pertanyaan?.length || 0 }} pertanyaan</span>
            <span v-if="is_wajib" class="fb-hero__wajib"><i class="bi bi-exclamation-triangle-fill"></i> Wajib diisi</span>
          </div>
        </div>

        <!-- SCROLL Mode -->
        <div v-if="mode_tampilan === 'SCROLL'" class="fb-scroll">
          <div
            v-for="(p, idx) in pertanyaan"
            :key="p.Id_Master_Feedback_Pertanyaan"
            class="fb-q"
          >
            <div class="fb-q__head">
              <span class="fb-q__num">{{ idx + 1 }}</span>
              <span class="fb-q__type-badge">{{ typeLabel(p.Tipe) }}</span>
            </div>
            <label class="fb-q__label">{{ p.Label }}</label>
            <component
              :is="inputComponent(p.Tipe)"
              :pertanyaan="p"
              :model-value="jawaban[p.Id_Master_Feedback_Pertanyaan]"
              @update:model-value="v => jawaban[p.Id_Master_Feedback_Pertanyaan] = v"
            />
            <div v-if="!jawaban[p.Id_Master_Feedback_Pertanyaan]" class="fb-q__empty">
              <i class="bi bi-arrow-up"></i> Pilih atau isi jawaban di atas
            </div>
          </div>

          <!-- Progress -->
          <div class="fb-progress-bar">
            <div class="fb-progress-bar__fill" :style="{ width: progressPercent + '%' }"></div>
          </div>
          <div class="fb-progress-text">{{ answeredCount }} / {{ pertanyaan?.length || 0 }} terjawab</div>

          <button class="fb-submit" :class="{ 'fb-submit--ready': isComplete }" :disabled="submitting || !isComplete" @click="submitFeedback">
            <span v-if="submitting" class="fb-submit__spinner"></span>
            <i v-else class="bi bi-send-fill"></i>
            {{ submitting ? 'Mengirim...' : 'Kirim Feedback' }}
          </button>
          <p v-if="submitError" class="fb-error-msg">{{ submitError }}</p>
        </div>

        <!-- WIZARD Mode -->
        <FeedbackFormWizard
          v-else
          :pertanyaan="pertanyaan"
          :is-wajib="is_wajib"
          @submit="handleWizardSubmit"
        />
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

const props = defineProps({
    feedback: Object, pertanyaan: Array, is_wajib: Boolean, mode_tampilan: String,
    error: { type: String, default: null }, message: { type: String, default: null },
})

const jawaban = ref({})
const submitting = ref(false)
const submitError = ref(null)
const success = ref(false)

const stateTitle = computed(() => ({
    invalid: 'Link Tidak Valid', expired: 'Link Kadaluarsa', already_submitted: 'Feedback Sudah Terkirim',
}[props.error] || ''))

const errorIcon = computed(() => ({
    invalid: 'bi bi-link-45deg', expired: 'bi bi-hourglass-split', already_submitted: 'bi bi-check-circle-fill',
}[props.error] || 'bi bi-exclamation-circle'))

const answeredCount = computed(() =>
    (props.pertanyaan || []).filter(p => jawaban.value[p.Id_Master_Feedback_Pertanyaan] != null && jawaban.value[p.Id_Master_Feedback_Pertanyaan] !== '').length
)

const isComplete = computed(() => props.pertanyaan?.every(p =>
    jawaban.value[p.Id_Master_Feedback_Pertanyaan] != null && jawaban.value[p.Id_Master_Feedback_Pertanyaan] !== ''
))

const progressPercent = computed(() =>
    props.pertanyaan?.length ? Math.round(answeredCount.value / props.pertanyaan.length * 100) : 0
)

function inputComponent(t) {
    return { RATING: RatingInput, NPS: NpsInput, LIKERT: LikertInput, TEXTAREA: TextareaInput, RADIO: RadioCards, CHECKBOX: CheckboxCards, DROPDOWN: DropdownSelect }[t] || TextareaInput
}
function typeLabel(t) {
    return { RATING: '⭐ Rating', NPS: '📊 NPS', LIKERT: '📐 Likert', TEXTAREA: '📝 Teks', RADIO: '🔘 Pilih Satu', CHECKBOX: '☑️ Multi-Pilih', DROPDOWN: '🔽 Dropdown' }[t] || t
}

async function submitFeedback() {
    if (!isComplete.value) return
    submitting.value = true; submitError.value = null
    const arr = Object.entries(jawaban.value).map(([id, val]) => ({ id_pertanyaan: parseInt(id), jawaban: Array.isArray(val) ? val : String(val) }))
    try {
        const { data } = await axios.post(window.location.pathname, { jawaban: arr })
        if (data.success) {
            success.value = true
            if (data.result?.redirect_to) setTimeout(() => window.location.href = data.result.redirect_to, 3000)
        }
    } catch (e) { submitError.value = e.response?.data?.message || 'Gagal mengirim. Coba lagi.' }
    finally { submitting.value = false }
}
function handleWizardSubmit(w) { jawaban.value = w; submitFeedback() }
</script>

<style scoped>
/* ── Page ── */
.wc-feedback-page {
    min-height: 85vh; padding: 48px 20px 80px; display: flex; justify-content: center;
    background: radial-gradient(ellipse 80% 50% at 50% -20%, rgba(99,102,241,.08), transparent),
                linear-gradient(180deg, #faf9ff 0%, #f4f2ff 40%, #fff 100%);
}

/* ── Success ── */
.fb-success { text-align: center; max-width: 500px; padding-top: 60px; }
.fb-success__ring { width: 80px; height: 80px; border-radius: 50%; background: linear-gradient(135deg,#10b981,#059669); display: flex; align-items: center; justify-content: center; margin: 0 auto 24px; animation: fb-pop .4s cubic-bezier(.18,.89,.32,1.28); }
.fb-success__check { color: #fff; font-size: 2.4rem; font-weight: 700; }
.fb-success__title { font-size: 1.8rem; font-weight: 800; color: #1e293b; margin: 0 0 8px; }
.fb-success__text { font-size: .95rem; color: #64748b; line-height: 1.6; margin: 0; }
.fb-success__redirect { font-size: .8rem; color: #94a3b8; margin-top: 16px; }
@keyframes fb-pop { 0% { transform: scale(0); } 100% { transform: scale(1); } }

/* ── Error Card ── */
.fb-error-card { text-align: center; max-width: 460px; margin: 60px auto; padding: 48px 32px; background: #fff; border-radius: 20px; box-shadow: 0 8px 40px rgba(0,0,0,.06); }
.fb-error-card__icon { font-size: 3rem; color: #6366f1; margin-bottom: 16px; }
.fb-error-card h2 { font-size: 1.4rem; font-weight: 700; color: #1e293b; margin: 0 0 8px; }
.fb-error-card p { font-size: .9rem; color: #64748b; margin: 0 0 24px; line-height: 1.5; }
.fb-error-card__btn { display: inline-block; padding: 12px 28px; background: linear-gradient(135deg,#6366f1,#4f46e5); color: #fff; border-radius: 10px; text-decoration: none; font-weight: 700; font-size: .9rem; }

/* ── Hero ── */
.fb-hero { text-align: center; max-width: 600px; margin: 0 auto 32px; }
.fb-hero__icon { font-size: 3rem; margin-bottom: 12px; }
.fb-hero__title { font-size: 2rem; font-weight: 800; color: #1e293b; margin: 0 0 10px; letter-spacing: -.02em; }
.fb-hero__sub { font-size: .95rem; color: #64748b; line-height: 1.65; margin: 0 auto; max-width: 520px; }
.fb-hero__meta { display: flex; justify-content: center; gap: 18px; margin-top: 16px; font-size: .78rem; color: #94a3b8; font-weight: 600; }
.fb-hero__meta span { display: inline-flex; align-items: center; gap: 5px; }
.fb-hero__wajib { color: #f59e0b; }

/* ── Questions ── */
.fb-scroll { max-width: 640px; margin: 0 auto; display: flex; flex-direction: column; gap: 20px; }
.fb-q { background: #fff; border: 1.5px solid #e2e8f0; border-radius: 16px; padding: 24px 26px; position: relative; transition: border-color .2s, box-shadow .2s; }
.fb-q:focus-within { border-color: #6366f1; box-shadow: 0 0 0 4px rgba(99,102,241,.08); }
.fb-q__head { display: flex; align-items: center; gap: 10px; margin-bottom: 14px; }
.fb-q__num { width: 28px; height: 28px; border-radius: 8px; background: linear-gradient(135deg,#6366f1,#4f46e5); color: #fff; font-size: .78rem; font-weight: 700; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.fb-q__type-badge { font-size: .68rem; font-weight: 700; color: #6366f1; background: rgba(99,102,241,.08); padding: 3px 10px; border-radius: 20px; }
.fb-q__label { display: block; font-size: 1rem; font-weight: 600; color: #1e293b; margin-bottom: 14px; line-height: 1.45; }
.fb-q__empty { margin-top: 8px; font-size: .72rem; color: #cbd5e1; font-weight: 600; text-align: center; padding: 6px; border: 1.5px dashed #e2e8f0; border-radius: 8px; }

/* ── Progress ── */
.fb-progress-bar { height: 6px; background: #f1f5f9; border-radius: 3px; overflow: hidden; margin-top: 8px; }
.fb-progress-bar__fill { height: 100%; border-radius: 3px; background: linear-gradient(90deg,#6366f1,#818cf8); transition: width .4s ease; }
.fb-progress-text { text-align: center; font-size: .78rem; color: #94a3b8; font-weight: 600; margin-top: 6px; }

/* ── Submit ── */
.fb-submit { display: flex; align-items: center; justify-content: center; gap: 10px; width: 100%; padding: 16px; border: none; border-radius: 14px; background: #e2e8f0; color: #94a3b8; font-size: 1rem; font-weight: 700; cursor: pointer; transition: all .3s; margin-top: 4px; }
.fb-submit--ready { background: linear-gradient(135deg,#6366f1,#4f46e5); color: #fff; box-shadow: 0 8px 24px rgba(99,102,241,.25); }
.fb-submit--ready:hover { box-shadow: 0 12px 32px rgba(99,102,241,.35); transform: translateY(-1px); }
.fb-submit:disabled { cursor: not-allowed; }
.fb-submit__spinner { width: 18px; height: 18px; border: 2.5px solid rgba(255,255,255,.3); border-top-color: #fff; border-radius: 50%; animation: fb-spin .7s linear infinite; }
@keyframes fb-spin { to { transform: rotate(360deg); } }
.fb-error-msg { text-align: center; color: #ef4444; font-size: .82rem; margin-top: 8px; }
</style>
