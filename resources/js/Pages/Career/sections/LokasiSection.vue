<!-- WEB CAREER — Section 5: Lokasi Kami -->
<template>
    <section id="lokasi" class="wc-section wc-loc">
        <div class="wc-sec-head wc-reveal">
            <span class="wc-eyebrow"><span class="wc-dot"></span> Basis Operasional</span>
            <h2>Lokasi <span class="wc-grad">kami</span>.</h2>
            <p>Dua pusat operasional di Sumatera Selatan yang menjadi rumah bagi keluarga besar EVO Group.</p>
        </div>

        <div class="wc-loc__grid">
            <article
                v-for="(office, i) in offices"
                :key="office.kota"
                class="wc-loc__card wc-reveal"
                :class="{ 'is-hq': office.unggulan }"
                :style="{ '--d': i * 100 + 'ms' }"
            >
                <div class="wc-loc__map" aria-hidden="true">
                    <div class="wc-loc__pin"><i class="bi bi-geo-alt-fill"></i></div>
                </div>
                <div class="wc-loc__body">
                    <div class="wc-loc__top">
                        <h3>{{ office.kota }}</h3>
                        <span class="wc-badge" :class="office.unggulan ? 'wc-badge--star' : 'wc-badge--muted'">{{ office.tipe }}</span>
                    </div>
                    <p class="wc-loc__name">{{ office.nama }}</p>
                    <p class="wc-loc__addr"><i class="bi bi-pin-map"></i> {{ office.alamat }}</p>
                    <div class="wc-loc__tags"><span v-for="t in office.tag" :key="t">{{ t }}</span></div>
                    <div class="wc-loc__foot"><i class="bi bi-people-fill"></i> {{ office.karyawan }} karyawan</div>
                </div>
            </article>
        </div>
    </section>
</template>

<script setup>
defineProps({
    offices: { type: Array, default: () => [] },
});
</script>
