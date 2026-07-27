<!-- WEB CAREER — Section: Tentang Perusahaan (visi/misi + brand highlight) -->
<template>
    <section id="tentang" class="wc-section wc-about">
        <div class="wc-sec-head wc-reveal">
            <span class="wc-eyebrow"><span class="wc-dot"></span> Tentang Perusahaan</span>
            <h2>Tumbuh bersama industri <span class="wc-grad">pet care Indonesia</span>.</h2>
            <p>PT Evo Manufacturing Indonesia bergerak di bidang pet food dengan komitmen menghadirkan produk berkualitas tinggi, aman, dan terjangkau.</p>
        </div>

        <div class="wc-about__grid">
            <article class="wc-about__visi wc-reveal wc-reveal--left">
                <span class="wc-about__visi-label">Visi</span>
                <p class="wc-about__visi-text">
                    Menjadi perusahaan unggul dan terpercaya di industri hewan peliharaan Indonesia.
                </p>
            </article>

            <article class="wc-about__misi wc-reveal wc-reveal--right" style="--d: 90ms">
                <div v-for="(m, i) in misi" :key="m.judul" class="wc-about__misi-item" :class="{ 'is-last': i === misi.length - 1 }">
                    <span class="wc-about__misi-no">{{ String(i + 1).padStart(2, '0') }}</span>
                    <div>
                        <strong>{{ m.judul }}</strong>
                        <p>{{ m.deskripsi }}</p>
                    </div>
                </div>
            </article>
        </div>

        <div class="wc-about__brands">
            <div
                v-for="(b, i) in brands"
                :key="b.nama"
                class="wc-about__brand wc-reveal"
                :style="{ '--d': i * 70 + 'ms' }"
            >
                <img class="wc-about__brand-logo" :src="`/brand-logos/${b.file}`" :alt="b.nama" loading="lazy" decoding="async" />
            </div>
        </div>
    </section>
</template>

<script setup>
const misi = [
    { judul: 'Produk berkualitas', deskripsi: 'Mengembangkan pet food yang adaptif, inovatif, dan bernutrisi seimbang.' },
    { judul: 'Distribusi nasional', deskripsi: 'Memperluas akses produk melalui jaringan cabang dan distribusi di Indonesia.' },
    { judul: 'Kemitraan berkelanjutan', deskripsi: 'Membangun hubungan jangka panjang dengan pelanggan dan mitra bisnis.' },
];

const brands = [
    { nama: 'Life Cat', file: 'life-cat.webp' },
    { nama: 'Ori Cat', file: 'ori-cat.webp' },
    { nama: 'Bio', file: 'bio.webp' },
    { nama: 'Life Dog', file: 'life-dog.webp' },
    { nama: 'Taro', file: 'taro.webp' },
];
</script>

<style scoped>
.wc-about__grid {
    display: grid;
    grid-template-columns: minmax(0, 0.85fr) minmax(0, 1.15fr);
    gap: 1.4rem;
    align-items: stretch;
}
.wc-about__visi {
    position: relative;
    overflow: hidden;
    padding: 2.2rem 2rem;
    border-radius: 1.5rem;
    background: linear-gradient(150deg, #6d5bd0 0%, #6366f1 100%);
    color: #fff;
    box-shadow: 0 24px 50px rgba(79, 70, 229, 0.28);
}
.wc-about__visi-label {
    display: inline-block;
    font-size: 0.72rem;
    font-weight: 800;
    letter-spacing: 0.18em;
    text-transform: uppercase;
    color: rgba(255, 255, 255, 0.8);
}
.wc-about__visi-text {
    margin: 0.9rem 0 0;
    font-size: clamp(1.35rem, 2.6vw, 1.75rem);
    font-weight: 800;
    line-height: 1.35;
    letter-spacing: -0.01em;
}
.wc-about__misi {
    padding: 0.4rem 1.8rem;
    border-radius: 1.5rem;
    background: rgba(255, 255, 255, 0.9);
    border: 1px solid rgba(226, 232, 240, 0.9);
    box-shadow: 0 12px 30px rgba(15, 23, 42, 0.05);
}
.wc-about__misi-item {
    display: flex;
    gap: 1rem;
    padding: 1.4rem 0;
    border-bottom: 1px solid rgba(226, 232, 240, 0.9);
}
.wc-about__misi-item.is-last {
    border-bottom: none;
}
.wc-about__misi-no {
    flex: none;
    font-size: 0.78rem;
    font-weight: 900;
    color: var(--indigo);
}
.wc-about__misi-item strong {
    display: block;
    margin-bottom: 0.3rem;
    font-size: 1rem;
    font-weight: 800;
    color: var(--ink);
}
.wc-about__misi-item p {
    margin: 0;
    color: var(--slate);
    font-size: 0.86rem;
    font-weight: 600;
    line-height: 1.6;
}
.wc-about__brands {
    display: grid;
    grid-template-columns: repeat(5, minmax(0, 1fr));
    gap: 1rem;
    margin-top: 1.4rem;
}
.wc-about__brand {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.6rem;
    padding: 1.5rem 1rem;
    border-radius: 1.2rem;
    background: rgba(255, 255, 255, 0.9);
    border: 1px solid rgba(226, 232, 240, 0.9);
    box-shadow: 0 12px 30px rgba(15, 23, 42, 0.05);
    text-align: center;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.wc-about__brand:hover {
    transform: translateY(-5px);
    box-shadow: 0 22px 44px rgba(79, 70, 229, 0.14);
}
.wc-about__brand-logo {
    height: 2.75rem;
    width: auto;
    max-width: 100%;
    object-fit: contain;
}
.wc-about__brand small {
    color: var(--muted);
    font-size: 0.76rem;
    font-weight: 700;
}
@media (max-width: 900px) {
    .wc-about__grid {
        grid-template-columns: 1fr;
    }
    .wc-about__brands {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
}
@media (max-width: 560px) {
    .wc-about__brands {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}
</style>
