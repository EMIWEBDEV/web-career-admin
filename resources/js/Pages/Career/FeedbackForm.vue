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
    min-height: 85vh; padding: 0 0 80px; display: flex; flex-direction: column; align-items: center;
    background: #faf9ff;
}

/* ── Success ── */
.fb-success { text-align: center; max-width: 500px; padding-top: 80px; }
.fb-success__ring { width: 88px; height: 88px; border-radius: 50%; background: linear-gradient(135deg,#10b981,#059669); display: flex; align-items: center; justify-content: center; margin: 0 auto 24px; animation: fb-pop .5s cubic-bezier(.18,.89,.32,1.28); box-shadow: 0 12px 40px rgba(16,185,129,.3); }
.fb-success__check { color: #fff; font-size: 2.6rem; font-weight: 700; }
.fb-success__title { font-size: 2rem; font-weight: 800; color: #1e293b; margin: 0 0 10px; }
.fb-success__text { font-size: 1rem; color: #64748b; line-height: 1.65; margin: 0; max-width: 420px; margin-left: auto; margin-right: auto; }
.fb-success__redirect { font-size: .82rem; color: #94a3b8; margin-top: 18px; }
@keyframes fb-pop { 0% { transform: scale(0) rotate(-10deg); } 60% { transform: scale(1.1) rotate(2deg); } 100% { transform: scale(1) rotate(0); } }

/* ── Error Card ── */
.fb-error-card { text-align: center; max-width: 460px; margin: 80px auto; padding: 52px 36px; background: #fff; border-radius: 24px; box-shadow: 0 4px 24px rgba(0,0,0,.04), 0 1px 3px rgba(0,0,0,.03); }
.fb-error-card__icon { font-size: 3.2rem; color: #6366f1; margin-bottom: 18px; }
.fb-error-card h2 { font-size: 1.5rem; font-weight: 700; color: #1e293b; margin: 0 0 10px; }
.fb-error-card p { font-size: .92rem; color: #64748b; margin: 0 0 26px; line-height: 1.55; }
.fb-error-card__btn { display: inline-block; padding: 13px 32px; background: linear-gradient(135deg,#6366f1,#4f46e5); color: #fff; border-radius: 12px; text-decoration: none; font-weight: 700; font-size: .9rem; transition: transform .15s, box-shadow .15s; }
.fb-error-card__btn:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(99,102,241,.25); }

/* ── Hero Banner ── */
.fb-hero {
    width: 100%; text-align: center; padding: 56px 24px 48px;
    background: linear-gradient(135deg, #1e1b4b 0%, #312e81 30%, #4338ca 60%, #6366f1 100%);
    color: #fff; position: relative; overflow: hidden;
}
.fb-hero::before {
    content: ''; position: absolute; top: -50%; left: -20%;
    width: 140%; height: 200%; background: radial-gradient(ellipse at 30% 50%, rgba(129,140,248,.25), transparent 60%),
                                    radial-gradient(ellipse at 70% 30%, rgba(167,139,250,.2), transparent 50%);
    pointer-events: none;
}
.fb-hero::after {
    content: ''; position: absolute; bottom: 0; left: 0; right: 0; height: 40px;
    background: linear-gradient(180deg, transparent, #faf9ff); pointer-events: none;
}
.fb-hero__icon { font-size: 3.5rem; margin-bottom: 14px; position: relative; z-index: 1; filter: drop-shadow(0 4px 8px rgba(0,0,0,.2)); }
.fb-hero__title { font-size: 2rem; font-weight: 800; margin: 0 0 12px; letter-spacing: -.02em; position: relative; z-index: 1; }
.fb-hero__sub { font-size: .95rem; line-height: 1.7; margin: 0 auto; max-width: 520px; opacity: .9; position: relative; z-index: 1; }
.fb-hero__meta { display: flex; justify-content: center; gap: 20px; margin-top: 20px; font-size: .78rem; font-weight: 600; opacity: .8; position: relative; z-index: 1; flex-wrap: wrap; }
.fb-hero__meta span { display: inline-flex; align-items: center; gap: 6px; background: rgba(255,255,255,.12); padding: 6px 14px; border-radius: 20px; }
.fb-hero__wajib { background: rgba(245,158,11,.25) !important; color: #fbbf24; }

/* ── Questions ── */
.fb-scroll { max-width: 680px; margin: -16px auto 0; padding: 0 20px; display: flex; flex-direction: column; gap: 18px; position: relative; z-index: 2; }
.fb-q {
    background: #fff; border: 1.5px solid #e8e5f7; border-radius: 18px;
    padding: 26px 28px; position: relative; transition: all .25s;
    box-shadow: 0 2px 12px rgba(0,0,0,.03);
}
.fb-q:hover { border-color: #c4b5fd; box-shadow: 0 4px 18px rgba(99,102,241,.06); }
.fb-q:focus-within { border-color: #6366f1; box-shadow: 0 0 0 5px rgba(99,102,241,.08); }
.fb-q__head { display: flex; align-items: center; gap: 10px; margin-bottom: 16px; }
.fb-q__num {
    width: 30px; height: 30px; border-radius: 10px;
    background: linear-gradient(135deg,#6366f1,#4f46e5); color: #fff;
    font-size: .8rem; font-weight: 700; display: flex; align-items: center; justify-content: center; flex-shrink: 0;
    box-shadow: 0 2px 8px rgba(99,102,241,.3);
}
.fb-q__type-badge {
    font-size: .7rem; font-weight: 700; color: #6366f1; background: rgba(99,102,241,.07);
    padding: 4px 12px; border-radius: 20px; letter-spacing: .02em;
}
.fb-q__label { display: block; font-size: 1.05rem; font-weight: 650; color: #1e293b; margin-bottom: 16px; line-height: 1.5; }
.fb-q__empty {
    margin-top: 10px; font-size: .74rem; color: #cbd5e1; font-weight: 600;
    text-align: center; padding: 8px; border: 1.5px dashed #e2e8f0; border-radius: 10px;
    transition: all .2s;
}
.fb-q:focus-within .fb-q__empty { border-color: #a5b4fc; color: #a5b4fc; }

/* ── Progress ── */
.fb-progress-bar { height: 8px; background: #ede9fe; border-radius: 4px; overflow: hidden; margin-top: 10px; }
.fb-progress-bar__fill { height: 100%; border-radius: 4px; background: linear-gradient(90deg,#6366f1,#a78bfa); transition: width .5s cubic-bezier(.4,0,.2,1); box-shadow: 0 0 8px rgba(99,102,241,.3); }
.fb-progress-text { text-align: center; font-size: .82rem; color: #6366f1; font-weight: 700; margin-top: 8px; }

/* ── Submit ── */
.fb-submit {
    display: flex; align-items: center; justify-content: center; gap: 10px;
    width: 100%; padding: 18px; border: none; border-radius: 16px;
    font-size: 1.05rem; font-weight: 700; cursor: pointer; transition: all .35s; margin-top: 6px;
    background: #f1f5f9; color: #94a3b8;
}
.fb-submit--ready {
    background: linear-gradient(135deg,#6366f1,#4f46e5); color: #fff;
    box-shadow: 0 8px 28px rgba(99,102,241,.3);
    animation: fb-pulse 2s ease-in-out infinite;
}
.fb-submit--ready:hover { box-shadow: 0 14px 36px rgba(99,102,241,.4); transform: translateY(-2px); }
.fb-submit:disabled { cursor: not-allowed; }
@keyframes fb-pulse { 0%, 100% { box-shadow: 0 8px 28px rgba(99,102,241,.3); } 50% { box-shadow: 0 8px 40px rgba(99,102,241,.45); } }
.fb-submit__spinner { width: 20px; height: 20px; border: 2.5px solid rgba(255,255,255,.3); border-top-color: #fff; border-radius: 50%; animation: fb-spin .7s linear infinite; }
@keyframes fb-spin { to { transform: rotate(360deg); } }
.fb-error-msg { text-align: center; color: #ef4444; font-size: .84rem; margin-top: 10px; font-weight: 600; }
</style>
