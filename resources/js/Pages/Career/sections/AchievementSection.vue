<!-- WEB CAREER — Section 2: Pencapaian (animated counters) -->
<template>
    <section id="achievement" class="wc-section">
        <div class="wc-sec-head wc-reveal">
            <span class="wc-eyebrow"><span class="wc-dot"></span> Pencapaian Kami</span>
            <h2>Angka yang berbicara tentang <span class="wc-grad">pertumbuhan</span>.</h2>
            <p>Dampak nyata dari kolaborasi ribuan karyawan di seluruh ekosistem EVO Group.</p>
        </div>

        <div class="wc-ach-grid">
            <article
                v-for="(a, i) in achievements"
                :key="a.label"
                ref="cards"
                class="wc-ach-card wc-reveal"
                :style="{ '--d': i * 70 + 'ms' }"
            >
                <span class="wc-ach-card__ico"><i class="bi" :class="a.icon"></i></span>
                <div class="wc-ach-card__num"><b :data-target="a.value">0</b><span>{{ a.suffix }}</span></div>
                <strong>{{ a.label }}</strong>
                <small>{{ a.desc }}</small>
            </article>
        </div>
    </section>
</template>

<script setup>
import { onMounted, onUnmounted, ref } from 'vue';

defineProps({
    achievements: { type: Array, default: () => [] },
});

const cards = ref([]);
let observer = null;

function animateCounter(el) {
    if (!el) return;
    const target = Number(el.dataset.target || 0);
    const dur = 1400;
    const start = performance.now();
    const tick = (now) => {
        const p = Math.min((now - start) / dur, 1);
        el.textContent = String(Math.round(target * (1 - Math.pow(1 - p, 4))));
        if (p < 1) requestAnimationFrame(tick);
    };
    requestAnimationFrame(tick);
}

onMounted(() => {
    observer = new IntersectionObserver(
        (entries) =>
            entries.forEach((e) => {
                if (!e.isIntersecting) return;
                animateCounter(e.target.querySelector('[data-target]'));
                observer.unobserve(e.target);
            }),
        { threshold: 0.5 },
    );
    (cards.value || []).forEach((el) => el && observer.observe(el));
});
onUnmounted(() => observer?.disconnect());
</script>
