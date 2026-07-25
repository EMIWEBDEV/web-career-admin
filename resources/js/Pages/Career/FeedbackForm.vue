<template>
  <CareerLayout>
    <div class="wc-feedback-page">
      <div class="wc-feedback-container">
        <!-- Error States -->
        <div v-if="error" class="wc-feedback-state">
          <div class="wc-feedback-state__icon">
            <span v-if="error === 'invalid'">🔗</span>
            <span v-else-if="error === 'expired'">⏰</span>
            <span v-else-if="error === 'already_submitted'">✅</span>
          </div>
          <h2 class="wc-feedback-state__title">{{ stateTitle }}</h2>
          <p class="wc-feedback-state__text">{{ message }}</p>
          <a
            :href="error === 'already_submitted' || error === 'expired' ? '/kandidat/portal' : '/'"
            class="wc-feedback-state__btn"
          >
            {{ error === 'already_submitted' || error === 'expired' ? 'Buka Portal Kandidat' : 'Kembali ke Beranda' }}
          </a>
        </div>

        <!-- Form -->
        <template v-else>
          <!-- Header -->
          <div class="wc-feedback__header">
            <h1 class="wc-feedback__title">{{ is_wajib ? 'Feedback (Wajib)' : 'Feedback' }}</h1>
            <p class="wc-feedback__subtitle">{{ is_wajib ? 'Mohon luangkan waktu sebentar untuk mengisi feedback — wajib diisi untuk menyelesaikan proses.' : 'Bantu kami menjadi lebih baik dengan mengisi feedback singkat.' }}</p>
            <span v-if="is_wajib" class="wc-feedback__badge">WAJIB</span>
          </div>

          <!-- SCROLL Mode -->
          <div v-if="mode_tampilan === 'SCROLL'" class="wc-feedback-scroll">
            <div
              v-for="(p, idx) in pertanyaan"
              :key="p.Id_Master_Feedback_Pertanyaan"
              class="wc-feedback__card"
            >
              <div class="wc-feedback__q-num">{{ idx + 1 }}</div>
              <label class="wc-feedback__q-label">{{ p.Label }}</label>
              <component
                :is="inputComponent(p.Tipe)"
                :pertanyaan="p"
                :model-value="jawaban[p.Id_Master_Feedback_Pertanyaan]"
                @update:model-value="v => jawaban[p.Id_Master_Feedback_Pertanyaan] = v"
              />
            </div>

            <div class="wc-feedback__actions">
              <button
                class="wc-feedback__submit"
                :disabled="submitting || !isComplete"
                @click="submitFeedback"
              >
                {{ submitting ? 'Mengirim...' : 'Kirim Feedback' }}
              </button>
            </div>
            <p v-if="submitError" class="wc-feedback__error">{{ submitError }}</p>
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
    </div>
  </CareerLayout>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { usePage } from '@inertiajs/vue3'
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
    feedback: Object,
    pertanyaan: Array,
    is_wajib: Boolean,
    mode_tampilan: String,
    error: { type: String, default: null },
    message: { type: String, default: null },
})

const jawaban = ref({})
const submitting = ref(false)
const submitError = ref(null)
const success = ref(false)

const stateTitle = computed(() => {
    switch (props.error) {
        case 'invalid': return 'Link Tidak Valid'
        case 'expired': return 'Link Kadaluarsa'
        case 'already_submitted': return 'Feedback Terkirim'
        default: return ''
    }
})

const isComplete = computed(() => {
    if (!props.pertanyaan) return false
    return props.pertanyaan.every(p => jawaban.value[p.Id_Master_Feedback_Pertanyaan] !== undefined
        && jawaban.value[p.Id_Master_Feedback_Pertanyaan] !== ''
        && jawaban.value[p.Id_Master_Feedback_Pertanyaan] !== null)
})

function inputComponent(tipe) {
    const map = {
        RATING: RatingInput,
        NPS: NpsInput,
        LIKERT: LikertInput,
        TEXTAREA: TextareaInput,
        RADIO: RadioCards,
        CHECKBOX: CheckboxCards,
        DROPDOWN: DropdownSelect,
    }
    return map[tipe] || TextareaInput
}

async function submitFeedback() {
    if (!isComplete.value) return
    submitting.value = true
    submitError.value = null

    const jawabanArr = Object.entries(jawaban.value).map(([id, val]) => ({
        id_pertanyaan: parseInt(id),
        jawaban: Array.isArray(val) ? val : String(val),
    }))

    try {
        const path = window.location.pathname
        const { data } = await axios.post(path, { jawaban: jawabanArr })
        if (data.success) {
            success.value = true
            if (data.result?.redirect_to) {
                setTimeout(() => { window.location.href = data.result.redirect_to }, 3000)
            }
        }
    } catch (e) {
        submitError.value = e.response?.data?.message || 'Gagal mengirim. Coba lagi.'
    } finally {
        submitting.value = false
    }
}

function handleWizardSubmit(wizardJawaban) {
    jawaban.value = wizardJawaban
    submitFeedback()
}
</script>

<style scoped>
.wc-feedback-page {
    min-height: 80vh;
    padding: 40px 20px 60px;
    display: flex;
    justify-content: center;
    background: linear-gradient(180deg, #f8f7ff 0%, #f0f0ff 30%, #fff 100%);
}

.wc-feedback-container {
    width: 100%;
    max-width: 640px;
}

.wc-feedback-state {
    text-align: center;
    padding: 60px 20px;
    background: rgba(255,255,255,0.9);
    backdrop-filter: blur(16px);
    border-radius: 20px;
    border: 1px solid rgba(99,102,241,0.1);
    box-shadow: 0 8px 40px rgba(99,102,241,0.08);
}

.wc-feedback-state__icon {
    font-size: 56px;
    margin-bottom: 16px;
}

.wc-feedback-state__title {
    font-size: 22px;
    font-weight: 700;
    color: #1e293b;
    margin: 0 0 8px;
}

.wc-feedback-state__text {
    font-size: 14px;
    color: #64748b;
    margin: 0 0 24px;
    line-height: 1.6;
}

.wc-feedback-state__btn {
    display: inline-block;
    padding: 12px 28px;
    background: linear-gradient(135deg, #6366f1, #4f46e5);
    color: #fff;
    border-radius: 10px;
    text-decoration: none;
    font-weight: 600;
    font-size: 14px;
}

.wc-feedback__header {
    text-align: center;
    margin-bottom: 24px;
}

.wc-feedback__title { font-size: 24px; font-weight: 700; color: #1e293b; margin: 0; }
.wc-feedback__subtitle { font-size: 14px; color: #64748b; margin: 8px 0 0; line-height: 1.5; }
.wc-feedback__badge {
    display: inline-block; margin-top: 10px; padding: 4px 14px;
    background: linear-gradient(135deg, #6366f1, #4f46e5); color: #fff;
    font-size: 11px; font-weight: 700; letter-spacing: .08em; border-radius: 20px;
}

.wc-feedback-scroll { display: flex; flex-direction: column; gap: 16px; }

.wc-feedback__card {
    background: rgba(255,255,255,0.9); backdrop-filter: blur(16px);
    border: 1px solid rgba(99,102,241,0.08); border-radius: 14px;
    padding: 20px 24px; position: relative;
}

.wc-feedback__q-num {
    position: absolute; top: 18px; left: 16px;
    width: 26px; height: 26px; border-radius: 8px;
    background: linear-gradient(135deg, #6366f1, #4f46e5); color: #fff;
    font-size: 12px; font-weight: 700; display: flex; align-items: center; justify-content: center;
}

.wc-feedback__q-label {
    display: block; margin-left: 32px; margin-bottom: 12px;
    font-size: 15px; font-weight: 600; color: #334155; line-height: 1.4;
}

.wc-feedback__actions { display: flex; justify-content: center; margin-top: 8px; }

.wc-feedback__submit {
    padding: 14px 40px; border: none; border-radius: 12px;
    background: linear-gradient(135deg, #6366f1, #4f46e5); color: #fff;
    font-size: 15px; font-weight: 700; cursor: pointer; transition: opacity 0.2s;
}

.wc-feedback__submit:disabled { opacity: 0.5; cursor: not-allowed; }
.wc-feedback__error { text-align: center; color: #ef4444; font-size: 13px; margin-top: 8px; }
</style>
