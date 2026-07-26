<template>
  <CareerLayout>
      <div class="fb-ambient" aria-hidden="true">
        <span class="aurora aurora--a"></span>
        <span class="aurora aurora--b"></span>
        <span class="aurora aurora--c"></span>
        <div class="grid"></div>
        <span class="twinkle twinkle--1"></span>
        <span class="twinkle twinkle--2"></span>
        <span class="twinkle twinkle--3"></span>
        <span class="twinkle twinkle--4"></span>
        <span class="twinkle twinkle--5"></span>
        <span class="bokeh bokeh--a"></span>
        <span class="bokeh bokeh--b"></span>
        <i class="bi bi-star-fill glyph glyph--1"></i>
        <i class="bi bi-chat-dots-fill glyph glyph--2"></i>
        <i class="bi bi-clipboard-check-fill glyph glyph--3"></i>
        <i class="bi bi-send-fill glyph glyph--4"></i>
        <i class="bi bi-hand-thumbs-up-fill glyph glyph--5"></i>
        <i class="bi bi-lightbulb-fill glyph glyph--6"></i>
        <i class="bi bi-emoji-smile-fill glyph glyph--7"></i>
        <i class="bi bi-pencil-fill glyph glyph--8"></i>
        <div class="particles">
          <span class="pt pt--1"></span><span class="pt pt--2"></span><span class="pt pt--3"></span>
          <span class="pt pt--4"></span><span class="pt pt--5"></span><span class="pt pt--6"></span>
        </div>
      </div>

      <div class="fb-page">
        <div v-if="success || error==='already_submitted'" class="fb-glass fb-glass--center">
          <div class="fb-glass__check">✓</div>
          <h1>Feedback Terkirim!</h1>
          <p>Terima kasih — masukanmu sangat berarti untuk kami terus berkembang.</p>
          <p v-if="is_wajib" class="fb-glass__note">Mengarahkan ke Portal Kandidat dalam 3 detik...</p>
        </div>
        <div v-else-if="error" class="fb-glass fb-glass--center">
          <div class="fb-glass__icon"><i :class="errorIcon"></i></div>
          <h1>{{ stateTitle }}</h1>
          <p>{{ message }}</p>
          <a :href="error==='already_submitted'||error==='expired'?'/kandidat/portal':'/'" class="fb-glass__btn">
            {{ error==='already_submitted'||error==='expired'?'Portal Kandidat →':'← Beranda' }}
          </a>
        </div>
        <div v-else class="fb-main">
          <div class="fb-glass fb-glass--head">
            <div class="fb-head__icon">{{ is_wajib ? '🎉' : '💬' }}</div>
            <h1 class="fb-head__title">{{ is_wajib ? 'Selamat, Kamu Diterima!' : 'Bantu Kami Lebih Baik' }}</h1>
            <p class="fb-head__sub">{{ is_wajib ? 'Sebelum melanjutkan, mohon isi feedback singkat ini. Hanya 2 menit — wajib diisi untuk menyelesaikan proses lamaranmu.' : 'Terima kasih sudah melamar di EVO Group. Isi feedback singkat tentang pengalamanmu agar kami bisa terus meningkatkan proses seleksi.' }}</p>
            <div class="fb-head__info"><span><i class="bi bi-clock"></i> ~2 menit</span><span class="fb-head__dot">·</span><span><i class="bi bi-list-ol"></i> {{ pertanyaan?.length || 0 }} pertanyaan</span></div>
          </div>
          <div class="fb-progress">
            <div class="fb-progress__track"><div class="fb-progress__fill" :style="{width:progressPercent+'%'}"></div></div>
            <div class="fb-progress__badge">{{answeredCount}}<span>/{{pertanyaan?.length}}</span></div>
          </div>
          <div v-if="mode_tampilan==='SCROLL'" class="fb-cards">
            <div v-for="(p,idx) in pertanyaan" :key="p.Id_Master_Feedback_Pertanyaan" class="fb-card">
              <span class="fb-card__n">{{idx+1}}</span>
              <p class="fb-card__q">{{p.Label}}</p>
              <component :is="inputComp(p.Tipe)" :pertanyaan="p" :model-value="jawaban[p.Id_Master_Feedback_Pertanyaan]" @update:model-value="v=>jawaban[p.Id_Master_Feedback_Pertanyaan]=v"/>
            </div>
            <button class="fb-submit" :disabled="!isComplete||submitting" @click="submitFeedback">
              <template v-if="submitting"><span class="fb-submit__spin"></span>Mengirim...</template>
              <template v-else>Kirim Feedback <i class="bi bi-send-fill"></i></template>
            </button>
            <p v-if="submitError" class="fb-submit__err">{{submitError}}</p>
          </div>
          <FeedbackFormWizard v-else :pertanyaan="pertanyaan" :is-wajib="is_wajib" @submit="handleWizardSubmit"/>
        </div>
      </div>
  </CareerLayout>
</template>

<script setup>
import { ref, computed } from 'vue'; import axios from 'axios'
import CareerLayout from './Layouts/CareerLayout.vue'; import FeedbackFormWizard from './FeedbackFormWizard.vue'
import './components/AuthShell.vue'; // load global .authx CSS
defineOptions({ layout: null })
import RatingInput from './components/feedback/RatingInput.vue'; import NpsInput from './components/feedback/NpsInput.vue'
import LikertInput from './components/feedback/LikertInput.vue'; import TextareaInput from './components/feedback/TextareaInput.vue'
import RadioCards from './components/feedback/RadioCards.vue'; import CheckboxCards from './components/feedback/CheckboxCards.vue'
import DropdownSelect from './components/feedback/DropdownSelect.vue'

const props = defineProps({ feedback: Object, pertanyaan: Array, is_wajib: Boolean, mode_tampilan: String, error: String, message: String, is_authenticated: Boolean })
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
/* Ambient */
.fb-ambient { position: fixed; inset: 0; z-index: 0; pointer-events: none; overflow: hidden; }
.fb-ambient .grid { position: absolute; inset: -20%; background-image: linear-gradient(rgba(99,102,241,.045) 1px,transparent 1px),linear-gradient(90deg,rgba(99,102,241,.045) 1px,transparent 1px); background-size: 54px 54px; -webkit-mask:radial-gradient(ellipse at 42% 42%,#000 34%,transparent 74%); mask:radial-gradient(ellipse at 42% 42%,#000 34%,transparent 74%); }
.fb-ambient .aurora { position: absolute; border-radius: 50%; }
.fb-ambient .aurora--a { top: -14%; left: -8%; width: 560px; height: 560px; filter: blur(90px); opacity: .6; background: radial-gradient(circle,rgba(139,92,246,.55),transparent 68%); animation: fb-fl-a 20s ease-in-out infinite; }
.fb-ambient .aurora--b { top: 8%; right: -6%; width: 440px; height: 440px; filter: blur(85px); opacity: .55; background: radial-gradient(circle,rgba(99,102,241,.5),transparent 66%); animation: fb-fl-b 24s ease-in-out 3s infinite reverse; }
.fb-ambient .aurora--c { bottom: -12%; left: 30%; width: 380px; height: 380px; filter: blur(75px); opacity: .45; background: radial-gradient(circle,rgba(245,158,11,.4),transparent 70%); animation: fb-fl-c 26s ease-in-out 5s infinite; }
@keyframes fb-fl-a { 0%,100%{transform:translate(0,0) scale(1)} 33%{transform:translate(50px,-40px) scale(1.1)} 66%{transform:translate(-30px,25px) scale(.9)} }
@keyframes fb-fl-b { 0%,100%{transform:translate(0,0)} 50%{transform:translate(-40px,-25px)} }
@keyframes fb-fl-c { 0%,100%{transform:translate(0,0) scale(1)} 50%{transform:translate(30px,-20px) scale(1.15)} }
.fb-ambient .bokeh { position: absolute; border-radius: 50%; filter: blur(12px); }
.fb-ambient .bokeh--a { top: 18%; left: 55%; width: 160px; height: 160px; background: radial-gradient(circle,rgba(139,92,246,.12),transparent 70%); animation: fb-bk 20s ease-in-out infinite; }
.fb-ambient .bokeh--b { top: 60%; left: 20%; width: 110px; height: 110px; background: radial-gradient(circle,rgba(99,102,241,.1),transparent 70%); animation: fb-bk 25s ease-in-out -6s infinite reverse; }
@keyframes fb-bk { 0%,100%{transform:translate(0,0)} 50%{transform:translate(25px,-15px)} }
.fb-ambient .twinkle { position: absolute; border-radius: 50%; animation: fb-tw 3.8s ease-in-out infinite; }
.fb-ambient .twinkle--1 { top: 22%; left: 46%; width: 5px; height: 5px; background: #c4b5fd; box-shadow:0 0 10px #a78bfa; animation-delay:-.2s; }
.fb-ambient .twinkle--2 { top: 34%; left: 52%; width: 4px; height: 4px; background: #fcd34d; box-shadow:0 0 10px #f59e0b; animation-delay:-1.1s; }
.fb-ambient .twinkle--3 { top: 14%; left: 38%; width: 6px; height: 6px; background: #a5b4fc; box-shadow:0 0 12px #6366f1; animation-delay:-2s; }
.fb-ambient .twinkle--4 { top: 48%; left: 64%; width: 4px; height: 4px; background: #c4b5fd; box-shadow:0 0 8px #8b5cf6; animation-delay:-3.2s; }
.fb-ambient .twinkle--5 { top: 65%; left: 28%; width: 5px; height: 5px; background: #fcd34d; box-shadow:0 0 10px #f59e0b; animation-delay:-.5s; }
@keyframes fb-tw { 0%,100%{opacity:.25;transform:scale(1)} 50%{opacity:1;transform:scale(2.2)} }
.fb-ambient .glyph { position: absolute; display: block; line-height: 1; animation: fb-gly 9s ease-in-out infinite; filter: drop-shadow(0 0 10px currentColor); }
.fb-ambient .glyph--1 { top: 18%; left: 8%; color: rgba(245,158,11,.5); font-size: 36px; animation-delay: 0s; }
.fb-ambient .glyph--2 { top: 38%; right: 6%; color: rgba(99,102,241,.5); font-size: 30px; animation-delay: -2s; }
.fb-ambient .glyph--3 { top: 55%; left: 5%; color: rgba(16,185,129,.45); font-size: 28px; animation-delay: -4s; }
.fb-ambient .glyph--4 { top: 22%; right: 10%; color: rgba(139,92,246,.45); font-size: 32px; animation-delay: -6s; }
.fb-ambient .glyph--5 { top: 62%; right: 8%; color: rgba(99,102,241,.5); font-size: 26px; animation-delay: -1s; }
.fb-ambient .glyph--6 { top: 70%; left: 12%; color: rgba(245,158,11,.45); font-size: 28px; animation-delay: -3s; }
.fb-ambient .glyph--7 { top: 10%; left: 50%; color: rgba(245,158,11,.4); font-size: 24px; animation-delay: -5s; }
.fb-ambient .glyph--8 { top: 75%; right: 15%; color: rgba(99,102,241,.45); font-size: 22px; animation-delay: -7s; }
@keyframes fb-gly { 0%,100%{transform:translateY(0) rotate(0);opacity:.5} 50%{transform:translateY(-25px) rotate(12deg);opacity:1} }
.fb-ambient .particles { position: absolute; inset: 0; }
.fb-ambient .pt { position: absolute; width: 4px; height: 4px; border-radius: 50%; background: #c4b5fd; box-shadow: 0 0 6px #a78bfa; animation: fb-pt 14s linear infinite; }
.fb-ambient .pt--1 { left: 15%; animation-delay: 0s; }
.fb-ambient .pt--2 { left: 35%; animation-delay: -3s; }
.fb-ambient .pt--3 { left: 55%; animation-delay: -6s; }
.fb-ambient .pt--4 { left: 75%; animation-delay: -9s; }
.fb-ambient .pt--5 { left: 25%; animation-delay: -1.5s; }
.fb-ambient .pt--6 { left: 65%; animation-delay: -7.5s; }
@keyframes fb-pt { 0%{transform:translateY(105vh) scale(0);opacity:0} 10%{opacity:.6} 90%{opacity:.6} 100%{transform:translateY(-10vh) scale(1.5);opacity:0} }
/* Page */
.fb-page { position: relative; min-height: 60vh; padding: 7rem 20px 80px; display: flex; flex-direction: column; align-items: center; }
.fb-glass { position: relative; background: rgba(255,255,255,.85); border: 1px solid rgba(0,0,0,.06); border-radius: 28px; box-shadow: 0 4px 24px rgba(0,0,0,.03), 0 1px 4px rgba(0,0,0,.02); }
.fb-glass--center { text-align: center; max-width: 480px; width: 100%; padding: 56px 36px; margin-top: 60px; }
.fb-glass--head { width: 100%; padding: 36px 34px; }
.fb-glass__check { width: 80px; height: 80px; border-radius: 50%; background: linear-gradient(135deg,#10b981,#059669); color: #fff; font-size: 2.2rem; font-weight: 700; display: flex; align-items: center; justify-content: center; margin: 0 auto 24px; box-shadow: 0 12px 32px rgba(16,185,129,.3); animation: fb-pop .5s cubic-bezier(.18,.89,.32,1.28) both; }
@keyframes fb-pop { 0%{transform:scale(0) rotate(-8deg)} 60%{transform:scale(1.15) rotate(3deg)} 100%{transform:scale(1) rotate(0)} }
.fb-glass__icon { font-size: 2.8rem; margin-bottom: 18px; }
.fb-glass h1 { font-size: 1.7rem; font-weight: 800; color: #1e293b; margin: 0 0 12px; letter-spacing: -.01em; }
.fb-glass p { font-size: .92rem; color: #64748b; line-height: 1.65; margin: 0; }
.fb-glass__note { font-size: .8rem!important; color: #94a3b8!important; margin-top: 16px!important; }
.fb-glass__btn { display: inline-block; margin-top: 24px; padding: 13px 30px; background: linear-gradient(135deg,#6366f1,#4f46e5); color: #fff; border-radius: 12px; text-decoration: none; font-weight: 700; font-size: .88rem; transition: transform .15s,box-shadow .15s; }
.fb-glass__btn:hover { transform: translateY(-1px); box-shadow: 0 8px 24px rgba(99,102,241,.3); }
.fb-main { position: relative; z-index: 1; max-width: 680px; width: 100%; display: flex; flex-direction: column; align-items: center; gap: 20px; }
.fb-head__icon { font-size: 2.5rem; margin-bottom: 10px; line-height: 1; }
.fb-head__title { font-size: 1.7rem; font-weight: 800; color: #1e293b; margin: 0 0 10px; letter-spacing: -.02em; }
.fb-head__sub { font-size: .9rem; color: #64748b; line-height: 1.65; margin: 0; }
.fb-head__info { display: flex; align-items: center; gap: 12px; margin-top: 14px; font-size: .78rem; color: #94a3b8; font-weight: 600; }
.fb-head__dot { color: #cbd5e1; }
.fb-progress { display: flex; align-items: center; gap: 12px; width: 100%; padding: 10px 16px; background: #fff; border: 1px solid #e8e5f7; border-radius: 14px; position: sticky; top: 80px; z-index: 5; }
.fb-progress__track { flex: 1; height: 6px; background: #ede9fe; border-radius: 4px; overflow: hidden; box-shadow: inset 0 1px 2px rgba(0,0,0,.04); }
.fb-progress__fill { height: 100%; border-radius: 4px; background: linear-gradient(90deg,#6366f1,#a78bfa); transition: width .5s cubic-bezier(.4,0,.2,1); box-shadow: 0 0 8px rgba(99,102,241,.25); }
.fb-progress__badge { background: linear-gradient(135deg,#6366f1,#4f46e5); color: #fff; padding: 4px 12px; border-radius: 20px; font-size: .75rem; font-weight: 700; white-space: nowrap; }
.fb-progress__badge span { font-weight: 500; opacity: .75; }
.fb-cards { width: 100%; display: flex; flex-direction: column; gap: 16px; }
.fb-card { background: #fff; border: 1.5px solid #e8e5f7; border-radius: 20px; padding: 26px 28px; transition: all .25s cubic-bezier(.4,0,.2,1); position: relative; }
.fb-card:hover { border-color: #c4b5fd; }
.fb-card:focus-within { border-color: #6366f1; box-shadow: 0 0 0 5px rgba(99,102,241,.06); }
.fb-card__n { display: inline-flex; align-items: center; justify-content: center; width: 30px; height: 30px; border-radius: 10px; background: linear-gradient(135deg,#6366f1,#4f46e5); color: #fff; font-size: .78rem; font-weight: 700; margin-bottom: 14px; box-shadow: 0 3px 10px rgba(99,102,241,.3); }
.fb-card__q { font-size: 1rem; font-weight: 650; color: #1e293b; margin: 0 0 16px; line-height: 1.5; }
.fb-submit { position: relative; overflow: hidden; display: flex; align-items: center; justify-content: center; gap: 10px; width: 100%; padding: 16px; border: 0; cursor: pointer; border-radius: 16px; font-size: .94rem; font-weight: 700; transition: all .3s cubic-bezier(.22,1,.36,1); letter-spacing: .01em; background: #f1f5f9; color: #94a3b8; }
.fb-submit:not(:disabled) { background: linear-gradient(135deg,#8b5cf6 0%,#6366f1 55%,#4f46e5 100%); color: #fff; box-shadow: 0 16px 32px rgba(99,102,241,.36); }
.fb-submit:not(:disabled)::after { content:''; position:absolute; top:0; left:0; height:100%; width:55%; background:linear-gradient(90deg,transparent,rgba(255,255,255,.4),transparent); transform:translateX(-170%) skewX(-18deg); animation: fb-sheen 5s ease-in-out 1.5s infinite; pointer-events:none; }
@keyframes fb-sheen { 0%,100%{transform:translateX(-170%) skewX(-18deg)} 50%{transform:translateX(220%) skewX(-18deg)} }
.fb-submit:not(:disabled):hover { transform: translateY(-2px); box-shadow: 0 22px 44px rgba(99,102,241,.44); filter: brightness(1.05); }
.fb-submit:not(:disabled):active { transform: translateY(0); }
.fb-submit:disabled { opacity: .55; cursor: not-allowed; box-shadow: none; }
.fb-submit__spin { display: inline-block; width: 20px; height: 20px; border: 2.5px solid rgba(255,255,255,.25); border-top-color: #fff; border-radius: 50%; animation: fb-spin .6s linear infinite; }
@keyframes fb-spin { to{transform:rotate(360deg)} }
.fb-submit__err { text-align: center; color: #ef4444; font-size: .84rem; font-weight: 600; margin-top: 6px; }
</style>
