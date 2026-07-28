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
                    <div class="wc-loc__foot">
                        <span><i class="bi bi-people-fill"></i> {{ office.karyawan }} karyawan</span>
                        <a
                            :href="`https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(office.nama + ' ' + office.alamat)}`"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="wc-loc__map-link"
                        >
                            Peta <i class="bi bi-box-arrow-up-right"></i>
                        </a>
                    </div>
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

<style scoped>
.wc-loc__foot {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    margin-top: 1rem;
    padding-top: 0.85rem;
    border-top: 1px solid rgba(226, 232, 240, 0.8);
    font-size: 0.78rem;
    color: #64748b;
}
.wc-loc__map-link {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.72rem;
    font-weight: 800;
    color: #6366f1;
    text-decoration: none;
    padding: 0.25rem 0.65rem;
    border-radius: 999px;
    background: rgba(99, 102, 241, 0.08);
    transition: background 0.2s ease, color 0.2s ease;
}
.wc-loc__map-link:hover {
    background: #6366f1;
    color: #ffffff;
}
</style>
