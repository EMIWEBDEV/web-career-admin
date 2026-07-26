<template>
  <CareerLayout>
    <div class="fb-page">
      <!-- Ambient background -->
      <div class="fb-ambient" aria-hidden="true">
        <span class="fb-aurora fb-aurora--a"></span>
        <span class="fb-aurora fb-aurora--b"></span>
        <span class="fb-twinkle fb-twinkle--1"></span>
        <span class="fb-twinkle fb-twinkle--2"></span>
        <span class="fb-twinkle fb-twinkle--3"></span>
        <i class="bi bi-chat-dots-fill fb-glyph fb-glyph--1"></i>
        <i class="bi bi-star-fill fb-glyph fb-glyph--2"></i>
        <i class="bi bi-send-fill fb-glyph fb-glyph--3"></i>
      </div>

      <!-- Success -->
      <div v-if="success" class="fb-glass fb-glass--center">
        <div class="fb-glass__check">✓</div>
        <h1>Feedback Terkirim!</h1>
        <p>Terima kasih — masukanmu sangat berarti untuk kami terus berkembang.</p>
        <p v-if="is_wajib" class="fb-glass__note">Mengarahkan ke Portal Kandidat dalam 3 detik...</p>
      </div>

      <!-- Error -->
      <div v-else-if="error" class="fb-glass fb-glass--center">
        <div class="fb-glass__icon"><i :class="errorIcon"></i></div>
        <h1>{{ stateTitle }}</h1>
        <p>{{ message }}</p>
        <a :href="error==='already_submitted'||error==='expired'?'/kandidat/portal':'/'" class="fb-glass__btn">
          {{ error==='already_submitted'||error==='expired'?'Portal Kandidat →':'← Beranda' }}
        </a>
      </div>

      <!-- Form -->
      <div v-else class="fb-main">
        <div class="fb-glass fb-glass--head">
          <h1 class="fb-head__title">
            {{ is_wajib ? 'Selamat! Kamu Diterima 🎉' : 'Bantu Kami Lebih Baik' }}
          </h1>
          <p class="fb-head__sub">
            {{ is_wajib
                ? 'Sebelum melanjutkan, mohon luangkan 2 menit untuk mengisi feedback — ini wajib diisi untuk menyelesaikan proses lamaranmu.'
                : 'Terima kasih sudah melamar di EVO Group. Isi feedback singkat tentang pengalamanmu agar kami bisa terus meningkatkan proses seleksi.' }}
          </p>
          <div class="fb-head__info">
            <span><i class="bi bi-clock"></i> ~2 menit</span>
            <span class="fb-head__dot">·</span>
            <span><i class="bi bi-list-ol"></i> {{ pertanyaan?.length || 0 }} pertanyaan</span>
            <span class="fb-head__dot">·</span>
            <span><i class="bi bi-send"></i> Rahasia &amp; anonim</span>
          </div>
        </div>

        <!-- Progress -->
        <div class="fb-progress">
          <div class="fb-progress__track"><div class="fb-progress__fill" :style="{ width: progressPercent + '%' }"></div></div>
          <span>{{ answeredCount }}/{{ pertanyaan?.length }}</span>
        </div>

        <!-- Cards -->
        <div v-if="mode_tampilan === 'SCROLL'" class="fb-cards">
          <div v-for="(p, idx) in pertanyaan" :key="p.Id_Master_Feedback_Pertanyaan" class="fb-card">
            <span class="fb-card__n">{{ idx + 1 }}</span>
            <p class="fb-card__q">{{ p.Label }}</p>
            <component :is="inputComp(p.Tipe)" :pertanyaan="p" :model-value="jawaban[p.Id_Master_Feedback_Pertanyaan]" @update:model-value="v => jawaban[p.Id_Master_Feedback_Pertanyaan] = v" />
          </div>
          <button class="fb-submit" :disabled="!isComplete || submitting" @click="submitFeedback">
            <template v-if="submitting"><span class="fb-submit__spin"></span> Mengirim...</template>
            <template v-else>Kirim Feedback <i class="bi bi-send-fill"></i></template>
          </button>
          <p v-if="submitError" class="fb-submit__err">{{ submitError }}</p>
        </div>

        <FeedbackFormWizard v-else :pertanyaan="pertanyaan" :is-wajib="is_wajib" @submit="handleWizardSubmit" />
      </div>
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
const stateTitle = computed(()=>({invalid:'Link Tidak Valid',expired:'Link Kadaluarsa',already_submitted:'Feedback Sudah Terkirim'}[props.error]||''))
const errorIcon = computed(()=>({invalid:'bi-link-45deg',expired:'bi-hourglass-split',already_submitted:'bi-check-circle-fill'}[props.error]||'bi-exclamation-circle'))
const answeredCount = computed(()=>(props.pertanyaan||[]).filter(p=>jawaban.value[p.Id_Master_Feedback_Pertanyaan]!=null&&jawaban.value[p.Id_Master_Feedback_Pertanyaan]!=='').length)
const isComplete = computed(()=>props.pertanyaan?.every(p=>jawaban.value[p.Id_Master_Feedback_Pertanyaan]!=null&&jawaban.value[p.Id_Master_Feedback_Pertanyaan]!==''))
const progressPercent = computed(()=>props.pertanyaan?.length?Math.round(answeredCount.value/props.pertanyaan.length*100):0)
function inputComp(t){const m={RATING:RatingInput,NPS:NpsInput,LIKERT:LikertInput,TEXTAREA:TextareaInput,RADIO:RadioCards,CHECKBOX:CheckboxCards,DROPDOWN:DropdownSelect};return m[t]||TextareaInput}
async function submitFeedback(){if(!isComplete.value)return;submitting.value=true;submitError.value=null
  try{const arr=Object.entries(jawaban.value).map(([id,val])=>({id_pertanyaan:parseInt(id),jawaban:Array.isArray(val)?val:String(val)}))
    const{data}=await axios.post(window.location.pathname,{jawaban:arr})
    if(data.success){success.value=true;if(data.result?.redirect_to)setTimeout(()=>window.location.href=data.result.redirect_to,3000)}
  }catch(e){submitError.value=e.response?.data?.message||'Gagal mengirim. Coba lagi.'}finally{submitting.value=false}
}
function handleWizardSubmit(w){jawaban.value=w;submitFeedback()}
</script>

<style scoped>
/* ── Page ── */
.fb-page { position: relative; min-height: 80vh; padding: 40px 20px 80px; display: flex; flex-direction: column; align-items: center; overflow: hidden; }

/* ── Ambient (mirip AuthShell tapi simpler) ── */
.fb-ambient { position: fixed; inset: 0; pointer-events: none; z-index: 0; }
.fb-aurora { position: absolute; border-radius: 50%; filter: blur(80px); opacity: .35; }
.fb-aurora--a { width: 500px; height: 500px; background: #c4b5fd; top: -10%; left: -15%; animation: fb-float-a 12s ease-in-out infinite; }
.fb-aurora--b { width: 400px; height: 400px; background: #a5b4fc; bottom: -10%; right: -10%; animation: fb-float-b 15s ease-in-out infinite; }
@keyframes fb-float-a { 0%,100%{transform:translate(0,0)} 33%{transform:translate(40px,-30px)} 66%{transform:translate(-20px,20px)} }
@keyframes fb-float-b { 0%,100%{transform:translate(0,0)} 50%{transform:translate(-30px,-20px)} }
.fb-twinkle { position: absolute; width: 4px; height: 4px; border-radius: 50%; background: #6366f1; animation: fb-tw 3s ease-in-out infinite; }
.fb-twinkle--1 { top: 15%; left: 20%; animation-delay: 0s; }
.fb-twinkle--2 { top: 70%; right: 15%; animation-delay: 1s; }
.fb-twinkle--3 { bottom: 20%; left: 60%; animation-delay: 2s; }
@keyframes fb-tw { 0%,100%{opacity:.3;transform:scale(1)} 50%{opacity:1;transform:scale(1.8)} }
.fb-glyph { position: absolute; font-size: 2rem; color: #c4b5fd; opacity: .2; animation: fb-gly 8s ease-in-out infinite; }
.fb-glyph--1 { top: 10%; right: 10%; animation-delay: 0s; }
.fb-glyph--2 { bottom: 25%; left: 8%; animation-delay: 3s; font-size: 1.5rem; }
.fb-glyph--3 { top: 50%; right: 5%; animation-delay: 5s; font-size: 1.2rem; }
@keyframes fb-gly { 0%,100%{transform:translateY(0) rotate(0);opacity:.15} 50%{transform:translateY(-20px) rotate(10deg);opacity:.3} }

/* ── Glass card ── */
.fb-glass { position: relative; z-index: 1; background: rgba(255,255,255,.75); backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px); border: 1px solid rgba(255,255,255,.6); border-radius: 24px; box-shadow: 0 8px 32px rgba(0,0,0,.04), 0 2px 8px rgba(0,0,0,.02); }
.fb-glass--center { text-align: center; max-width: 480px; width: 100%; padding: 48px 32px; margin-top: 60px; }
.fb-glass--head { max-width: 640px; width: 100%; padding: 32px 30px; }
.fb-glass__check { width: 72px; height: 72px; border-radius: 50%; background: linear-gradient(135deg,#10b981,#059669); color: #fff; font-size: 2rem; font-weight: 700; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; box-shadow: 0 8px 24px rgba(16,185,129,.25); }
.fb-glass__icon { font-size: 2.5rem; color: #6366f1; margin-bottom: 16px; }
.fb-glass h1 { font-size: 1.6rem; font-weight: 800; color: #1e293b; margin: 0 0 10px; }
.fb-glass p { font-size: .9rem; color: #64748b; line-height: 1.6; margin: 0; }
.fb-glass__note { font-size: .78rem !important; color: #94a3b8 !important; margin-top: 14px !important; }
.fb-glass__btn { display: inline-block; margin-top: 20px; padding: 11px 26px; background: linear-gradient(135deg,#6366f1,#4f46e5); color: #fff; border-radius: 10px; text-decoration: none; font-weight: 700; font-size: .85rem; }

/* ── Main ── */
.fb-main { position: relative; z-index: 1; max-width: 680px; width: 100%; display: flex; flex-direction: column; align-items: center; gap: 16px; }

/* ── Head ── */
.fb-head__title { font-size: 1.75rem; font-weight: 800; color: #1e293b; margin: 0 0 10px; }
.fb-head__sub { font-size: .9rem; color: #64748b; line-height: 1.65; margin: 0; }
.fb-head__info { display: flex; align-items: center; gap: 10px; margin-top: 14px; font-size: .78rem; color: #94a3b8; font-weight: 600; }
.fb-head__dot { color: #cbd5e1; }

/* ── Progress ── */
.fb-progress { display: flex; align-items: center; gap: 12px; width: 100%; padding: 10px 16px; background: rgba(255,255,255,.6); backdrop-filter: blur(12px); border: 1px solid rgba(255,255,255,.5); border-radius: 12px; position: sticky; top: 80px; z-index: 5; }
.fb-progress__track { flex: 1; height: 5px; background: #f1f5f9; border-radius: 3px; overflow: hidden; }
.fb-progress__fill { height: 100%; border-radius: 3px; background: linear-gradient(90deg,#6366f1,#818cf8); transition: width .4s ease; }
.fb-progress span { font-size: .78rem; font-weight: 700; color: #6366f1; }

/* ── Cards ── */
.fb-cards { width: 100%; display: flex; flex-direction: column; gap: 14px; }
.fb-card { background: rgba(255,255,255,.7); backdrop-filter: blur(12px); border: 1px solid rgba(255,255,255,.5); border-radius: 18px; padding: 22px 24px; transition: all .2s; }
.fb-card:focus-within { background: rgba(255,255,255,.9); border-color: #6366f1; box-shadow: 0 0 0 4px rgba(99,102,241,.06); }
.fb-card__n { display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; border-radius: 8px; background: linear-gradient(135deg,#6366f1,#4f46e5); color: #fff; font-size: .75rem; font-weight: 700; margin-bottom: 12px; }
.fb-card__q { font-size: .95rem; font-weight: 650; color: #1e293b; margin: 0 0 14px; line-height: 1.45; }

/* ── Submit ── */
.fb-submit { display: block; width: 100%; padding: 16px; border: none; border-radius: 16px; font-size: 1rem; font-weight: 700; cursor: pointer; transition: all .3s; background: rgba(255,255,255,.5); color: #94a3b8; display: flex; align-items: center; justify-content: center; gap: 8px; }
.fb-submit:not(:disabled) { background: linear-gradient(135deg,#6366f1,#4f46e5); color: #fff; box-shadow: 0 8px 24px rgba(99,102,241,.2); animation: fb-glow 2s infinite; }
@keyframes fb-glow { 0%,100%{box-shadow:0 8px 24px rgba(99,102,241,.2)} 50%{box-shadow:0 8px 36px rgba(99,102,241,.35)} }
.fb-submit:not(:disabled):hover { transform: translateY(-1px); }
.fb-submit:disabled { cursor: not-allowed; }
.fb-submit__spin { display: inline-block; width: 18px; height: 18px; border: 2.5px solid rgba(255,255,255,.3); border-top-color: #fff; border-radius: 50%; animation: fb-spin .7s linear infinite; }
@keyframes fb-spin { to{transform:rotate(360deg)} }
.fb-submit__err { text-align: center; color: #ef4444; font-size: .82rem; margin-top: 4px; }
</style>
