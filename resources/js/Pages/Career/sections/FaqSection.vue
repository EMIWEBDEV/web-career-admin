<!-- WEB CAREER — Section 6: Candidate FAQ (Accordion Interaktif dengan Smooth Height Transition & 180° Icon Rotation) -->
<template>
    <section id="faq" class="wc-section wc-faq">
        <div class="wc-sec-head wc-reveal">
            <span class="wc-eyebrow"><span class="wc-dot"></span> FAQ Candidates</span>
            <h2>Pertanyaan yang sering <span class="wc-grad">diajukan.</span></h2>
            <p>Segala hal yang perlu Kamu ketahui tentang alur pendaftaran dan seleksi di EVO Group.</p>
        </div>

        <div class="wc-faq__container wc-reveal">
            <div
                v-for="(item, i) in faqs"
                :key="i"
                class="wc-faq__item"
                :class="{ 'is-open': openIndex === i }"
                @click="toggle(i)"
            >
                <div class="wc-faq__question">
                    <span class="wc-faq__icon"><i class="bi" :class="item.icon"></i></span>
                    <h3 class="wc-faq__title">{{ item.q }}</h3>
                    <span class="wc-faq__toggle" :class="{ 'is-open': openIndex === i }">
                        <i class="bi bi-chevron-down"></i>
                    </span>
                </div>

                <transition
                    name="wc-faq-anim"
                    @before-enter="beforeEnter"
                    @enter="enter"
                    @after-enter="afterEnter"
                    @before-leave="beforeLeave"
                    @leave="leave"
                >
                    <div v-show="openIndex === i" class="wc-faq__answer-wrapper">
                        <div class="wc-faq__answer">
                            <p>{{ item.a }}</p>
                        </div>
                    </div>
                </transition>
            </div>
        </div>
    </section>
</template>

<script setup>
import { ref } from 'vue';

const openIndex = ref(0);

function toggle(index) {
    openIndex.value = openIndex.value === index ? -1 : index;
}

// Javascript Transition Hooks untuk Animasi Tinggi Accordion Presisi (Exact Pixel Height)
function beforeEnter(el) {
    el.style.height = '0px';
    el.style.opacity = '0';
    el.style.overflow = 'hidden';
}

function enter(el) {
    el.style.transition = 'height 0.38s cubic-bezier(0.16, 1, 0.3, 1), opacity 0.3s ease';
    el.style.height = el.scrollHeight + 'px';
    el.style.opacity = '1';
}

function afterEnter(el) {
    el.style.height = 'auto';
    el.style.overflow = 'visible';
}

function beforeLeave(el) {
    el.style.height = el.scrollHeight + 'px';
    el.style.overflow = 'hidden';
}

function leave(el) {
    // Force reflow agar transisi dari height nyata ke 0px terpicu dengan mulus
    void el.offsetHeight;
    el.style.transition = 'height 0.35s cubic-bezier(0.16, 1, 0.3, 1), opacity 0.25s ease';
    el.style.height = '0px';
    el.style.opacity = '0';
}

const faqs = [
    {
        icon: 'bi-person-check-fill',
        q: 'Apakah Fresh Graduate bisa melamar di EVO Group?',
        a: 'Tentu saja! EVO Group membuka peluang besar bagi Fresh Graduate baik untuk posisi Rekrutmen Umum maupun Program Management Trainee (MT) yang dirancang khusus sebagai jalur percepatan karier.'
    },
    {
        icon: 'bi-file-earmark-text-fill',
        q: 'Apa perbedaan Form 1 dan Form 2 pada pendaftaran MT?',
        a: 'Form 1 diisi saat pendaftaran awal (data diri dasar + verifikasi foto wajah). Form 2 diisi di tahap seleksi berikutnya setelah Anda dinyatakan lolos ke tahap evaluasi mendalam.'
    },
    {
        icon: 'bi-geo-alt-fill',
        q: 'Di mana lokasi penempatan kerja EVO Group?',
        a: 'Penempatan disesuaikan dengan posisi yang dilamar, yaitu di Head Office (Palembang) atau Manufacturing Plant (Banyuasin, Sumatera Selatan).'
    },
    {
        icon: 'bi-clock-history',
        q: 'Berapa lama proses seleksi hingga diterima bekerja?',
        a: 'Proses seleksi rata-rata berlangsung antara 1 hingga 3 minggu tergantung pada jenjang posisi dan tahapan asesmen yang dijalani kandidat.'
    },
    {
        icon: 'bi-shield-check',
        q: 'Apakah ada pemungutan biaya dalam proses rekrutmen?',
        a: 'TIDAK ADA BIAYA APAPUN. Seluruh proses rekrutmen EVO Group bersifat gratis dan transparan. Hati-hati terhadap segala bentuk penipuan yang mengatasnamakan EVO Group.'
    }
];
</script>

<style scoped>
.wc-faq {
    width: min(1000px, calc(100vw - 2rem));
    margin: clamp(2.5rem, 5vw, 3.5rem) auto;
}
.wc-faq__container {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    margin-top: 2rem;
}
.wc-faq__item {
    background: rgba(255, 255, 255, 0.88);
    border: 1px solid rgba(226, 232, 240, 0.9);
    border-radius: 1.25rem;
    padding: 1.25rem 1.5rem;
    cursor: pointer;
    box-shadow: 0 8px 24px rgba(15, 23, 42, 0.04);
    transition: transform 0.25s cubic-bezier(0.16, 1, 0.3, 1), box-shadow 0.25s ease, border-color 0.25s ease, background 0.25s ease;
}
.wc-faq__item:hover {
    border-color: rgba(139, 92, 246, 0.4);
    transform: translateY(-2px);
    box-shadow: 0 14px 30px rgba(99, 102, 241, 0.12);
}
.wc-faq__item.is-open {
    background: #ffffff;
    border-color: rgba(139, 92, 246, 0.6);
    box-shadow: 0 18px 40px rgba(99, 102, 241, 0.16), 0 0 0 1px rgba(139, 92, 246, 0.3);
}
.wc-faq__question {
    display: flex;
    align-items: center;
    gap: 1rem;
}
.wc-faq__icon {
    display: grid;
    place-items: center;
    width: 2.5rem;
    height: 2.5rem;
    border-radius: 0.85rem;
    background: rgba(99, 102, 241, 0.1);
    color: #6366f1;
    font-size: 1.1rem;
    flex: none;
    transition: background 0.25s ease, color 0.25s ease, transform 0.25s ease;
}
.wc-faq__item.is-open .wc-faq__icon {
    background: linear-gradient(135deg, #8b5cf6, #6366f1);
    color: #ffffff;
    transform: scale(1.05);
}
.wc-faq__title {
    flex: 1;
    margin: 0;
    font-size: 1.05rem;
    font-weight: 800;
    color: #1e1b4b;
    letter-spacing: -0.01em;
}
.wc-faq__toggle {
    display: grid;
    place-items: center;
    width: 2.2rem;
    height: 2.2rem;
    border-radius: 50%;
    background: #f1f5f9;
    color: #6366f1;
    font-size: 1.1rem;
    flex: none;
    transition: background 0.3s ease, color 0.3s ease, transform 0.35s cubic-bezier(0.16, 1, 0.3, 1);
}
.wc-faq__toggle i {
    transition: transform 0.35s cubic-bezier(0.16, 1, 0.3, 1);
}
.wc-faq__toggle.is-open {
    background: #6366f1;
    color: #ffffff;
}
.wc-faq__toggle.is-open i {
    transform: rotate(180deg);
}

/* Wrapper & Content */
.wc-faq__answer-wrapper {
    will-change: height, opacity;
}
.wc-faq__answer {
    margin-top: 1rem;
    padding-top: 1rem;
    padding-left: 3.5rem;
    border-top: 1px dashed rgba(226, 232, 240, 0.9);
}
.wc-faq__answer p {
    margin: 0;
    color: #475569;
    font-size: 0.94rem;
    line-height: 1.7;
    font-weight: 500;
}
@media (max-width: 640px) {
    .wc-faq__answer {
        padding-left: 0;
    }
}
</style>
