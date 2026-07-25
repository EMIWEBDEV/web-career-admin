<template>
  <div class="fb-wizard">
    <!-- Stepper -->
    <div class="fb-wizard__stepper">
      <div class="fb-wizard__steps">
        <div v-for="(_, idx) in pertanyaan" :key="idx"
             :class="['fb-wizard__step', {
               'fb-wizard__step--active': idx === currentStep,
               'fb-wizard__step--done': idx < currentStep,
               'fb-wizard__step--pending': idx > currentStep
             }]">
          <span v-if="idx < currentStep">✓</span>
          <span v-else>{{ idx + 1 }}</span>
        </div>
      </div>
      <div class="fb-wizard__progress-bar">
        <div class="fb-wizard__progress-fill" :style="{ width: ((currentStep + 1) / pertanyaan.length * 100) + '%' }"></div>
      </div>
      <div class="fb-wizard__progress-text">{{ currentStep + 1 }} / {{ pertanyaan.length }}</div>
    </div>

    <!-- Question -->
    <Transition name="fb-wizard-slide" mode="out-in">
      <div class="fb-wizard__card" :key="currentStep">
        <div class="fb-wizard__q-label">
          {{ currentPertanyaan?.Label }}
        </div>
        <component
          :is="inputComponent(currentPertanyaan?.Tipe)"
          :pertanyaan="currentPertanyaan"
          :model-value="jawaban[currentPertanyaan?.Id_Master_Feedback_Pertanyaan]"
          @update:model-value="v => jawaban[currentPertanyaan.Id_Master_Feedback_Pertanyaan] = v"
        />
      </div>
    </Transition>

    <!-- Navigation -->
    <div class="fb-wizard__nav">
      <button v-if="currentStep > 0" class="fb-wizard__btn fb-wizard__btn--prev"
              @click="currentStep--">← Sebelumnya</button>
      <div class="fb-wizard__spacer"></div>
      <button v-if="currentStep < pertanyaan.length - 1" class="fb-wizard__btn fb-wizard__btn--next"
              :disabled="!currentJawaban" @click="currentStep++">Selanjutnya →</button>
      <button v-else class="fb-wizard__btn fb-wizard__btn--submit"
              :disabled="!currentJawaban || !isComplete" @click="$emit('submit', { ...jawaban })">
        Kirim Feedback
      </button>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import RatingInput from './components/feedback/RatingInput.vue'
import NpsInput from './components/feedback/NpsInput.vue'
import LikertInput from './components/feedback/LikertInput.vue'
import TextareaInput from './components/feedback/TextareaInput.vue'
import RadioCards from './components/feedback/RadioCards.vue'
import CheckboxCards from './components/feedback/CheckboxCards.vue'
import DropdownSelect from './components/feedback/DropdownSelect.vue'

const props = defineProps({ pertanyaan: Array, isWajib: Boolean })
defineEmits(['submit'])

const currentStep = ref(0)
const jawaban = ref({})

const currentPertanyaan = computed(() => props.pertanyaan?.[currentStep.value])
const currentJawaban = computed(() => {
    const p = currentPertanyaan.value
    if (!p) return false
    const val = jawaban.value[p.Id_Master_Feedback_Pertanyaan]
    return val !== undefined && val !== null && val !== ''
})

const isComplete = computed(() => {
    return props.pertanyaan?.every(p => {
        const val = jawaban.value[p.Id_Master_Feedback_Pertanyaan]
        return val !== undefined && val !== null && val !== ''
    })
})

function inputComponent(tipe) {
    const map = { RATING: RatingInput, NPS: NpsInput, LIKERT: LikertInput, TEXTAREA: TextareaInput, RADIO: RadioCards, CHECKBOX: CheckboxCards, DROPDOWN: DropdownSelect }
    return map[tipe] || TextareaInput
}
</script>

<style scoped>
.fb-wizard { max-width: 600px; margin: 0 auto; }

.fb-wizard__stepper { text-align: center; margin-bottom: 24px; }
.fb-wizard__steps { display: flex; justify-content: center; gap: 24px; margin-bottom: 10px; }
.fb-wizard__step { width: 32px; height: 32px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 13px; font-weight: 700; background: #f1f5f9; color: #94a3b8; transition: all 0.2s; }
.fb-wizard__step--active { background: linear-gradient(135deg, #6366f1, #4f46e5); color: #fff; box-shadow: 0 4px 12px rgba(99,102,241,0.3); }
.fb-wizard__step--done { background: #10b981; color: #fff; }
.fb-wizard__progress-bar { height: 4px; background: #f1f5f9; border-radius: 2px; overflow: hidden; }
.fb-wizard__progress-fill { height: 100%; background: linear-gradient(90deg, #6366f1, #818cf8); border-radius: 2px; transition: width 0.3s; }
.fb-wizard__progress-text { margin-top: 6px; font-size: 12px; color: #94a3b8; }

.fb-wizard__card { background: rgba(255,255,255,0.9); backdrop-filter: blur(16px); border: 1px solid rgba(99,102,241,0.08); border-radius: 16px; padding: 32px 28px; min-height: 180px; }
.fb-wizard__q-label { font-size: 16px; font-weight: 600; color: #1e293b; margin-bottom: 20px; line-height: 1.5; }

.fb-wizard__nav { display: flex; align-items: center; margin-top: 20px; }
.fb-wizard__spacer { flex: 1; }
.fb-wizard__btn { padding: 12px 24px; border: none; border-radius: 10px; font-size: 14px; font-weight: 600; cursor: pointer; transition: opacity 0.15s; }
.fb-wizard__btn--prev { background: #f1f5f9; color: #475569; }
.fb-wizard__btn--next { background: linear-gradient(135deg, #6366f1, #4f46e5); color: #fff; }
.fb-wizard__btn--submit { background: linear-gradient(135deg, #10b981, #059669); color: #fff; }
.fb-wizard__btn:disabled { opacity: 0.5; cursor: not-allowed; }

.fb-wizard-slide-enter-active, .fb-wizard-slide-leave-active { transition: all 0.25s ease; }
.fb-wizard-slide-enter-from { opacity: 0; transform: translateX(30px); }
.fb-wizard-slide-leave-to { opacity: 0; transform: translateX(-30px); }
</style>
