<!-- WEB CAREER — Section 4: Management Trainee (dinamis; sembunyi bila kosong) -->
<template>
    <section v-if="programMt.length" id="mt" class="wc-mt">
        <div class="wc-mt__bg" aria-hidden="true">
            <span v-for="n in 18" :key="n" class="wc-mt__star" :style="starStyle(n)"></span>
        </div>
        <div class="wc-sec-head wc-sec-head--light wc-reveal">
            <span class="wc-eyebrow wc-eyebrow--gold"><i class="bi bi-gem"></i> Program Unggulan</span>
            <h2>Management Trainee — jalur cepat calon <span class="wc-grad-gold">pemimpin</span>.</h2>
            <p>Kegiatan kaderisasi eksklusif untuk lulusan terbaik yang siap tumbuh menjadi future leader.</p>
        </div>

        <div class="wc-mt__grid">
            <Link v-for="(mt, i) in programMt" :key="mt.id" class="wc-mt__card wc-reveal" :class="{ 'is-full': isFull(mt) }" :style="{ '--d': i * 100 + 'ms' }" :href="mtUrl(mt.id)">
                <div class="wc-mt__cardtop">
                    <span class="wc-mt__ribbon"><i class="bi bi-mortarboard-fill"></i> {{ mt.batch }}</span>
                    <span class="wc-mt__status" :class="statusClass(mt.status)">{{ statusLabel(mt.status) }}</span>
                </div>
                <div class="wc-mt__head">
                    <h3>{{ mt.nama }}</h3>
                    <p class="wc-mt__tag">{{ mt.tagline }}</p>
                </div>
                <p class="wc-mt__desc">{{ mt.ringkasan }}</p>

                <div class="wc-mt__meta">
                    <div><i class="bi bi-mortarboard"></i><span>{{ mt.tipeKegiatan }}</span></div>
                    <div><i class="bi bi-geo-alt"></i><span>{{ mt.penempatan }}</span></div>
                    <div><i class="bi bi-hourglass-split"></i><span>{{ mt.durasi }}</span></div>
                    <div><i class="bi bi-people"></i><span>{{ mt.kuota }} kursi</span></div>
                </div>

                <div class="wc-mt__quota">
                    <div class="wc-mt__quota-head">
                        <span>Kuota terisi</span>
                        <b>{{ mt.kuotaTerisi }} / {{ mt.kuota }}</b>
                    </div>
                    <div class="wc-mt__bar"><span :class="{ full: isFull(mt) }" :style="{ width: kuotaPct(mt) + '%' }"></span></div>
                </div>

                <div class="wc-mt__foot">
                    <span class="wc-mt__deadline" :class="{ soon: !isFull(mt) && daysLeft(mt.tanggalTutup) <= 7 }">
                        <i class="bi" :class="isFull(mt) ? 'bi-lock-fill' : 'bi-calendar-event'"></i>
                        {{ isFull(mt) ? 'Pendaftaran ditutup' : 'Ditutup ' + formatDate(mt.tanggalTutup) }}
                    </span>
                    <span class="wc-mt__cta">Pelajari <i class="bi bi-arrow-right"></i></span>
                </div>
            </Link>
        </div>
    </section>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import { daysLeft, formatDate, isFull, kuotaPct, mtUrl, statusClass, statusLabel } from '../careerData';

defineProps({
    programMt: { type: Array, default: () => [] },
});

const STAR_POS = Array.from({ length: 18 }, (_, i) => ({
    top: (i * 53) % 100,
    left: (i * 71) % 100,
    delay: (i % 6) * 0.5,
    size: 1 + (i % 3),
}));
function starStyle(n) {
    const s = STAR_POS[(n - 1) % STAR_POS.length];
    return { top: s.top + '%', left: s.left + '%', width: s.size + 'px', height: s.size + 'px', animationDelay: s.delay + 's' };
}
</script>
