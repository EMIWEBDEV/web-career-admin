<!-- WEB CAREER — Feedback Form: halaman isi feedback kandidat -->
<template>
  <CareerLayout>
    <div class="wc-fb">
      <!-- Success -->
      <div v-if="success" class="wc-fb-msg wc-fb-msg--ok">
        <div class="wc-fb-msg__icon">✅</div>
        <h1 class="wc-fb-msg__title">Feedback Terkirim!</h1>
        <p class="wc-fb-msg__text">Terima kasih! Masukanmu sangat berarti bagi kami.</p>
        <p v-if="is_wajib" class="wc-fb-msg__sub">Mengarahkan ke Portal Kandidat dalam 3 detik...</p>
      </div>

      <!-- Error -->
      <div v-else-if="error" class="wc-fb-msg wc-fb-msg--err">
        <div class="wc-fb-msg__icon"><i :class="errorIcon"></i></div>
        <h1 class="wc-fb-msg__title">{{ stateTitle }}</h1>
        <p class="wc-fb-msg__text">{{ message }}</p>
        <a :href="error === 'already_submitted' || error === 'expired' ? '/kandidat/portal' : '/'"
           class="wc-fb-msg__btn">
          {{ error === 'already_submitted' || error === 'expired' ? 'Portal Kandidat →' : '← Beranda' }}
        </a>
      </div>

      <!-- Form -->
      <template v-else>
        <div class="wc-fb__head">
          <span class="wc-fb__pill" :class="{ 'wc-fb__pill--wajib': is_wajib }">
            {{ is_wajib ? 'WAJIB DIISI' : 'OPSIONAL' }}
          </span>
          <h1 class="wc-fb__title">{{ is_wajib ? 'Feedback Lamaran' : 'Bantu Kami Lebih Baik' }}</h1>
          <p class="wc-fb__desc">
            {{ is_wajib
                ? 'Selamat! Kamu diterima di EVO Group. Mohon luangkan waktu sebentar untuk mengisi feedback sebelum melanjutkan.'
                : 'Terima kasih sudah melamar. Isi feedback singkat ini agar kami bisa terus meningkatkan proses seleksi.' }}
          </p>
          <div class="wc-fb__meta">
            <i class="bi bi-clock"></i> ~2 menit &nbsp;·&nbsp;
            <i class="bi bi-list-ol"></i> {{ pertanyaan?.length || 0 }} pertanyaan
          </div>
        </div>

        <!-- Progress -->
        <div class="wc-fb__progress">
          <div class="wc-fb__progress-bar">
            <div class="wc-fb__progress-fill" :style="{ width: progressPercent + '%' }"></div>
          </div>
          <span>{{ answeredCount }}/{{ pertanyaan?.length }}</span>
        </div>

        <!-- Questions -->
        <div v-if="mode_tampilan === 'SCROLL'" class="wc-fb__list">
          <div v-for="(p, idx) in pertanyaan" :key="p.Id_Master_Feedback_Pertanyaan" class="wc-fb__card">
            <div class="wc-fb__card-num">{{ idx + 1 }}</div>
            <label class="wc-fb__card-label">{{ p.Label }}</label>
            <component :is="inputComp(p.Tipe)" :pertanyaan="p" :model-value="jawaban[p.Id_Master_Feedback_Pertanyaan]" @update:model-value="v => jawaban[p.Id_Master_Feedback_Pertanyaan] = v" />
          </div>

          <button class="wc-fb__submit" :disabled="!isComplete || submitting" @click="submitFeedback">
            <span v-if="submitting" class="wc-fb__submit-spin"></span>
            <span v-else>Kirim Feedback <i class="bi bi-send-fill"></i></span>
          </button>
          <p v-if="submitError" class="wc-fb__err">{{ submitError }}</p>
        </div>

        <FeedbackFormWizard v-else :pertanyaan="pertanyaan" :is-wajib="is_wajib" @submit="handleWizardSubmit" />
      </template>
    </div>
  </CareerLayout>
</template>

<script setup>
import { ref, computed } from 'vue'; import axios from 'axios'
import CareerLayout from './Layouts/CareerLayout.vue'; import FeedbackFormWizard from './FeedbackFormWizard.vue'
import RatingInput from './components/feedback/RatingInput.vue'; import NpsInput from './components/feedback/NpsInput.vue'
import LikertInput from './components/feedback/LikertInput.vue'; import TextareaInput from './components/feedback/TextareaInput.vue'
import RadioCards from './components/feedback/RadioCards.vue'; import CheckboxCards from './components/feedback/CheckboxCards.vue'
import DropdownSelect from './components/feedback/DropdownSelect.vue'

const props = defineProps({ feedback: Object, pertanyaan: Array, is_wajib: Boolean, mode_tampilan: String, error: String, message: String })
const jawaban = ref({}); const submitting = ref(false); const submitError = ref(null); const success = ref(false)
const stateTitle = computed(() => ({ invalid:'Link Tidak Valid', expired:'Link Kadaluarsa', already_submitted:'Feedback Sudah Terkirim' }[props.error]||''))
const errorIcon = computed(() => ({ invalid:'bi bi-link-45deg', expired:'bi bi-hourglass-split', already_submitted:'bi bi-check-circle-fill' }[props.error]||'bi bi-exclamation-circle'))
const answeredCount = computed(() => (props.pertanyaan||[]).filter(p => jawaban.value[p.Id_Master_Feedback_Pertanyaan]!=null&&jawaban.value[p.Id_Master_Feedback_Pertanyaan]!=='').length)
const isComplete = computed(() => props.pertanyaan?.every(p => jawaban.value[p.Id_Master_Feedback_Pertanyaan]!=null&&jawaban.value[p.Id_Master_Feedback_Pertanyaan]!==''))
const progressPercent = computed(() => props.pertanyaan?.length?Math.round(answeredCount.value/props.pertanyaan.length*100):0)
function inputComp(t){ const m={RATING:RatingInput,NPS:NpsInput,LIKERT:LikertInput,TEXTAREA:TextareaInput,RADIO:RadioCards,CHECKBOX:CheckboxCards,DROPDOWN:DropdownSelect}; return m[t]||TextareaInput }
async function submitFeedback(){ if(!isComplete.value)return; submitting.value=true; submitError.value=null
  try{ const arr=Object.entries(jawaban.value).map(([id,val])=>({id_pertanyaan:parseInt(id),jawaban:Array.isArray(val)?val:String(val)}))
    const{data}=await axios.post(window.location.pathname,{jawaban:arr})
    if(data.success){ success.value=true; if(data.result?.redirect_to) setTimeout(()=>window.location.href=data.result.redirect_to,3000) }
  }catch(e){ submitError.value=e.response?.data?.message||'Gagal mengirim. Coba lagi.' } finally{ submitting.value=false }
}
function handleWizardSubmit(w){ jawaban.value=w; submitFeedback() }
</script>

<style scoped>
/* ── Container ── */
.wc-fb { max-width: 640px; margin: 0 auto; padding: 48px 20px 80px; }

/* ── Message states ── */
.wc-fb-msg { text-align: center; padding-top: 60px; }
.wc-fb-msg__icon { font-size: 3.5rem; margin-bottom: 16px; }
.wc-fb-msg__title { font-size: 1.8rem; font-weight: 800; color: #1e293b; margin: 0 0 8px; }
.wc-fb-msg__text { font-size: .95rem; color: #64748b; line-height: 1.6; margin: 0; }
.wc-fb-msg__sub { font-size: .8rem; color: #94a3b8; margin-top: 16px; }
.wc-fb-msg__btn { display: inline-block; margin-top: 24px; padding: 12px 28px; background: linear-gradient(135deg,#6366f1,#4f46e5); color:#fff; border-radius: 12px; text-decoration: none; font-weight: 700; font-size: .9rem; }

/* ── Head ── */
.wc-fb__head { text-align: center; margin-bottom: 28px; }
.wc-fb__pill { display: inline-block; padding: 4px 14px; border-radius: 20px; font-size: .65rem; font-weight: 800; letter-spacing: .1em; background: #e2e8f0; color: #64748b; margin-bottom: 12px; }
.wc-fb__pill--wajib { background: #fef3c7; color: #b45309; }
.wc-fb__title { font-size: 1.8rem; font-weight: 800; color: #1e293b; margin: 0 0 10px; }
.wc-fb__desc { font-size: .9rem; color: #64748b; line-height: 1.6; max-width: 480px; margin: 0 auto; }
.wc-fb__meta { margin-top: 12px; font-size: .78rem; color: #94a3b8; font-weight: 600; }

/* ── Progress ── */
.wc-fb__progress { display: flex; align-items: center; gap: 12px; margin-bottom: 24px; padding: 12px 16px; background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; position: sticky; top: 80px; z-index: 5; }
.wc-fb__progress-bar { flex: 1; height: 6px; background: #f1f5f9; border-radius: 3px; overflow: hidden; }
.wc-fb__progress-fill { height: 100%; border-radius: 3px; background: linear-gradient(90deg,#6366f1,#818cf8); transition: width .4s ease; }
.wc-fb__progress span { font-size: .78rem; font-weight: 700; color: #6366f1; white-space: nowrap; }

/* ── Cards ── */
.wc-fb__list { display: flex; flex-direction: column; gap: 16px; }
.wc-fb__card { background: #fff; border: 1.5px solid #e8e5f7; border-radius: 16px; padding: 22px 24px; transition: border-color .2s, box-shadow .2s; }
.wc-fb__card:focus-within { border-color: #6366f1; box-shadow: 0 0 0 4px rgba(99,102,241,.06); }
.wc-fb__card-num { width: 28px; height: 28px; border-radius: 8px; background: linear-gradient(135deg,#6366f1,#4f46e5); color: #fff; font-size: .75rem; font-weight: 700; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 14px; }
.wc-fb__card-label { display: block; font-size: .98rem; font-weight: 650; color: #1e293b; margin-bottom: 14px; line-height: 1.45; }

/* ── Submit ── */
.wc-fb__submit { display: block; width: 100%; padding: 16px; border: none; border-radius: 14px; font-size: 1rem; font-weight: 700; cursor: pointer; background: #f1f5f9; color: #94a3b8; transition: all .3s; }
.wc-fb__submit:not(:disabled) { background: linear-gradient(135deg,#6366f1,#4f46e5); color: #fff; box-shadow: 0 8px 24px rgba(99,102,241,.2); animation: fb-glow 2s infinite; }
@keyframes fb-glow { 0%,100%{box-shadow:0 8px 24px rgba(99,102,241,.2)} 50%{box-shadow:0 8px 34px rgba(99,102,241,.35)} }
.wc-fb__submit:not(:disabled):hover { transform: translateY(-1px); }
.wc-fb__submit:disabled { cursor: not-allowed; }
.wc-fb__submit-spin { display: inline-block; width: 20px; height: 20px; border: 2.5px solid rgba(255,255,255,.3); border-top-color: #fff; border-radius: 50%; animation: fb-spin .7s linear infinite; }
@keyframes fb-spin { to { transform: rotate(360deg); } }
.wc-fb__err { text-align: center; color: #ef4444; font-size: .82rem; margin-top: 8px; }
</style>
