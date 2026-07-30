<!-- ══════════════════════════════════════════════════════════
     WEB CAREER — Accordion FAQ (dipakai bersama)
     Satu komponen untuk DUA tempat:
       - section FAQ di landing page  → mode ringkas, buka satu
       - halaman /karir/faq           → jawaban detail + boleh multi-buka
     Transisi tinggi memakai hook JS (bukan max-height) supaya tingginya
     presisi mengikuti isi, berapa pun panjang jawabannya.
     ══════════════════════════════════════════════════════════ -->
<template>
    <div class="wc-faq__container">
        <div
            v-for="item in items"
            :id="item.slug ? 'faq-' + item.slug : null"
            :key="item.slug || item.id"
            class="wc-faq__item"
            :class="{ 'is-open': terbuka(item) }"
        >
            <!-- Tombol, bukan div: pertanyaan harus bisa dicapai keyboard & pembaca layar. -->
            <button
                type="button"
                class="wc-faq__question"
                :aria-expanded="terbuka(item) ? 'true' : 'false'"
                @click="toggle(item)"
            >
                <span class="wc-faq__icon"><i class="bi" :class="item.ikon || 'bi-question-circle-fill'"></i></span>
                <h3 class="wc-faq__title">{{ item.pertanyaan }}</h3>
                <span class="wc-faq__toggle" :class="{ 'is-open': terbuka(item) }">
                    <i class="bi bi-chevron-down"></i>
                </span>
            </button>

            <transition
                name="wc-faq-anim"
                @before-enter="beforeEnter"
                @enter="enter"
                @after-enter="afterEnter"
                @before-leave="beforeLeave"
                @leave="leave"
            >
                <div v-show="terbuka(item)" class="wc-faq__answer-wrapper">
                    <div class="wc-faq__answer">
                        <p class="wc-faq__ringkas">{{ item.jawaban }}</p>

                        <!-- Jawaban panjang berformat. Sudah disaring HtmlBersih di
                             server; DOMPurify di sini adalah lapisan kedua supaya
                             data lama / dari jalur lain tetap tidak bisa menyuntik. -->
                        <div
                            v-if="showDetail && item.jawabanDetail"
                            class="wc-faq__detail"
                            v-html="bersih(item.jawabanDetail)"
                        ></div>

                        <slot name="aksi" :item="item"></slot>
                    </div>
                </div>
            </transition>
        </div>
    </div>
</template>

<script setup>
import DOMPurify from 'dompurify';
import { ref, watch } from 'vue';

const props = defineProps({
    items: { type: Array, default: () => [] },
    // Landing: satu terbuka sekaligus. Halaman FAQ: bebas beberapa sekaligus,
    // supaya pembaca bisa membandingkan dua jawaban tanpa yang satu menutup.
    single: { type: Boolean, default: false },
    showDetail: { type: Boolean, default: false },
    // Slug yang harus langsung terbuka saat pertama dirender (deep link / landing).
    initialOpen: { type: String, default: '' },
});

const emit = defineEmits(['open']);

const kunci = (item) => item.slug || item.id;
const dibuka = ref(new Set(props.initialOpen ? [props.initialOpen] : []));

watch(
    () => props.initialOpen,
    (slug) => {
        if (!slug) return;
        if (props.single) dibuka.value = new Set([slug]);
        else dibuka.value = new Set([...dibuka.value, slug]);
        emit('open', slug);
    },
);

function terbuka(item) {
    return dibuka.value.has(kunci(item));
}

function toggle(item) {
    const k = kunci(item);
    const sudah = dibuka.value.has(k);

    if (props.single) {
        dibuka.value = sudah ? new Set() : new Set([k]);
    } else {
        const next = new Set(dibuka.value);
        sudah ? next.delete(k) : next.add(k);
        dibuka.value = next;
    }

    // Hanya saat MEMBUKA — menutup bukan tanda ketertarikan.
    if (!sudah) emit('open', k);
}

function bersih(html) {
    return DOMPurify.sanitize(html || '', {
        ALLOWED_TAGS: ['p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'ul', 'ol', 'li', 'h3', 'h4', 'blockquote', 'a'],
        ALLOWED_ATTR: ['href', 'target', 'rel'],
    });
}

// ── Hook transisi tinggi (pixel presisi, bukan max-height tebakan) ──
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
    // Force reflow agar transisi dari height nyata ke 0px terpicu dengan mulus.
    void el.offsetHeight;
    el.style.transition = 'height 0.35s cubic-bezier(0.16, 1, 0.3, 1), opacity 0.25s ease';
    el.style.height = '0px';
    el.style.opacity = '0';
}
</script>

<style scoped>
.wc-faq__container {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}
.wc-faq__item {
    /* Deep link menggulir ke elemen ini — sisakan ruang untuk navbar sticky. */
    scroll-margin-top: 96px;
    background: rgba(255, 255, 255, 0.88);
    border: 1px solid rgba(226, 232, 240, 0.9);
    border-radius: 1.25rem;
    padding: 1.25rem 1.5rem;
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
    width: 100%;
    padding: 0;
    background: none;
    border: 0;
    text-align: left;
    cursor: pointer;
    font: inherit;
    color: inherit;
}
.wc-faq__question:focus-visible {
    outline: 2px solid #6366f1;
    outline-offset: 4px;
    border-radius: 0.75rem;
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
.wc-faq__ringkas {
    margin: 0;
    color: #475569;
    font-size: 0.94rem;
    line-height: 1.7;
    font-weight: 500;
}
.wc-faq__detail {
    margin-top: 0.9rem;
    color: #475569;
    font-size: 0.94rem;
    line-height: 1.75;
}
/* :deep — isi dari v-html tidak terjangkau scoped style biasa. */
.wc-faq__detail :deep(p) {
    margin: 0 0 0.75rem;
}
.wc-faq__detail :deep(p:last-child) {
    margin-bottom: 0;
}
.wc-faq__detail :deep(h3),
.wc-faq__detail :deep(h4) {
    margin: 1.1rem 0 0.5rem;
    font-size: 1rem;
    font-weight: 800;
    color: #1e1b4b;
}
.wc-faq__detail :deep(ul),
.wc-faq__detail :deep(ol) {
    margin: 0 0 0.75rem;
    padding-left: 1.35rem;
}
.wc-faq__detail :deep(li) {
    margin-bottom: 0.35rem;
}
.wc-faq__detail :deep(a) {
    color: #6366f1;
    font-weight: 700;
    text-decoration: underline;
    text-underline-offset: 2px;
}
.wc-faq__detail :deep(blockquote) {
    margin: 0 0 0.75rem;
    padding: 0.65rem 1rem;
    border-left: 3px solid rgba(139, 92, 246, 0.5);
    background: rgba(99, 102, 241, 0.06);
    border-radius: 0 0.6rem 0.6rem 0;
}
.wc-faq__detail :deep(strong) {
    color: #1e1b4b;
}
@media (max-width: 640px) {
    .wc-faq__answer {
        padding-left: 0;
    }
}
</style>
