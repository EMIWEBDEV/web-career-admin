<!-- ══════════════════════════════════════════════════════════
     WEB CAREER — LANDING PAGE
     Menyusun CareerLayout (navbar + footer) + section terpisah.
     Semua data dummy dari CareerLandingController (tanpa DB).
     ══════════════════════════════════════════════════════════ -->
<template>
    <Head>
        <title>Karir - EVO Group | Naik Level Bersama Kami</title>
    </Head>

    <CareerLayout :has-mt="hasMt" :offices="offices">
        <HeroSection :benefits="benefits" :has-mt="hasMt" :hero-slides="heroSlides" />
        <TentangSection />
        <AchievementSection :achievements="achievements" />
        <TimSection :tim="tim" />
        <MtSection :program-mt="programMt" />
        <LokasiSection :offices="offices" />
        <FaqSection />
        <CtaSection />
        <StickyApplyBar :has-mt="hasMt" />
    </CareerLayout>
</template>

<script setup>
import { Head } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, onUnmounted } from 'vue';
import CareerLayout from './Layouts/CareerLayout.vue';
import HeroSection from './sections/HeroSection.vue';
import TentangSection from './sections/TentangSection.vue';
import AchievementSection from './sections/AchievementSection.vue';
import TimSection from './sections/TimSection.vue';
import MtSection from './sections/MtSection.vue';
import LokasiSection from './sections/LokasiSection.vue';
import FaqSection from './sections/FaqSection.vue';
import CtaSection from './sections/CtaSection.vue';
import StickyApplyBar from './components/StickyApplyBar.vue';
import { scrollToId } from './careerData';

defineOptions({ layout: null });

const props = defineProps({
    meta: { type: Object, default: () => ({}) },
    programMt: { type: Array, default: () => [] },
    achievements: { type: Array, default: () => [] },
    offices: { type: Array, default: () => [] },
    benefits: { type: Array, default: () => [] },
    tim: { type: Array, default: () => [] },
    heroSlides: { type: Array, default: () => [] },
});

const tim = computed(() => props.tim || []);
const programMt = computed(() => props.programMt || []);
const achievements = computed(() => props.achievements || []);
const offices = computed(() => props.offices || []);
const benefits = computed(() => props.benefits || []);
const heroSlides = computed(() => props.heroSlides || []);
const hasMt = computed(() => programMt.value.length > 0);

// Reveal-on-scroll untuk seluruh elemen .wc-reveal di semua section
let revealObserver = null;
function initReveal() {
    revealObserver = new IntersectionObserver(
        (entries) =>
            entries.forEach((e) => {
                if (e.isIntersecting) {
                    e.target.classList.add('is-in');
                    revealObserver.unobserve(e.target);
                }
            }),
        { threshold: 0.15 },
    );
    document.querySelectorAll('.wc-reveal').forEach((el) => revealObserver.observe(el));
}

onMounted(() => {
    nextTick(() => {
        initReveal();
        // Jika datang dari halaman lain (mis. detail) yang minta scroll ke section
        try {
            const target = sessionStorage.getItem('wcScrollTarget');
            if (target) {
                sessionStorage.removeItem('wcScrollTarget');
                setTimeout(() => scrollToId(target), 120);
            }
        } catch (e) {
            /* ignore */
        }
    });
});
onUnmounted(() => revealObserver?.disconnect());
</script>
