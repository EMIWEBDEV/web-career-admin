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
                <div class="wc-about__visi-shine" aria-hidden="true"></div>
                <i class="bi bi-compass-fill wc-about__visi-bg-icon" aria-hidden="true"></i>
                <span class="wc-about__visi-label"><i class="bi bi-compass"></i> Visi Kami</span>
                <p class="wc-about__visi-text">
                    Menjadi perusahaan unggul dan terpercaya di industri hewan peliharaan Indonesia.
                </p>
            </article>

            <article class="wc-about__misi wc-reveal wc-reveal--right" style="--d: 90ms">
                <div v-for="(m, i) in misi" :key="m.judul" class="wc-about__misi-item" :class="{ 'is-last': i === misi.length - 1 }">
                    <div class="wc-about__misi-no-badge">
                        <span>{{ String(i + 1).padStart(2, '0') }}</span>
                    </div>
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
                <small class="wc-about__brand-name">{{ b.nama }}</small>
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
    padding: clamp(2rem, 4vw, 2.5rem) clamp(1.8rem, 4vw, 2.2rem);
    border-radius: 1.75rem;
    background: linear-gradient(135deg, #6366f1 0%, #4f46e5 50%, #4338ca 100%);
    color: #fff;
    box-shadow: 0 20px 48px rgba(79, 70, 229, 0.32);
    display: flex;
    flex-direction: column;
    justify-content: center;
}
.wc-about__visi-shine {
    position: absolute;
    top: -50%;
    right: -40%;
    width: 250px;
    height: 250px;
    background: radial-gradient(circle, rgba(255, 255, 255, 0.28), transparent 70%);
    pointer-events: none;
}
.wc-about__visi-bg-icon {
    position: absolute;
    right: -1rem;
    bottom: -1.5rem;
    font-size: 11rem;
    color: #ffffff;
    opacity: 0.1;
    pointer-events: none;
    line-height: 1;
}
.wc-about__visi-label {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 6px 14px;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.18);
    border: 1px solid rgba(255, 255, 255, 0.3);
    -webkit-backdrop-filter: blur(8px);
    backdrop-filter: blur(8px);
    font-size: 0.7rem;
    font-weight: 800;
    letter-spacing: 0.16em;
    text-transform: uppercase;
    color: #ffffff;
    width: fit-content;
}
.wc-about__visi-text {
    margin: 1.2rem 0 0;
    font-size: clamp(1.35rem, 2.5vw, 1.75rem);
    font-weight: 800;
    line-height: 1.35;
    letter-spacing: -0.015em;
    text-shadow: 0 2px 10px rgba(15, 23, 42, 0.2);
    position: relative;
    z-index: 1;
}
.wc-about__misi {
    padding: 1rem 1.6rem;
    border-radius: 1.75rem;
    background: rgba(255, 255, 255, 0.95);
    border: 1px solid rgba(99, 102, 241, 0.16);
    box-shadow: 0 16px 40px rgba(99, 102, 241, 0.07);
    display: flex;
    flex-direction: column;
    justify-content: center;
}
.wc-about__misi-item {
    display: flex;
    align-items: flex-start;
    gap: 1.1rem;
    padding: 1.1rem 0.8rem;
    border-bottom: 1px solid rgba(226, 232, 240, 0.8);
    border-radius: 1rem;
    transition: transform 0.22s ease, background 0.22s ease;
}
.wc-about__misi-item:hover {
    background: rgba(99, 102, 241, 0.04);
    transform: translateX(5px);
}
.wc-about__misi-item.is-last {
    border-bottom: none;
}
.wc-about__misi-no-badge {
    flex: none;
    width: 2.35rem;
    height: 2.35rem;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.12), rgba(139, 92, 246, 0.18));
    border: 1px solid rgba(99, 102, 241, 0.25);
    color: #4f46e5;
    font-weight: 900;
    font-size: 0.82rem;
    transition: background 0.25s ease, color 0.25s ease, transform 0.25s ease, box-shadow 0.25s ease;
}
.wc-about__misi-item:hover .wc-about__misi-no-badge {
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: #ffffff;
    transform: scale(1.08);
    box-shadow: 0 6px 16px rgba(99, 102, 241, 0.32);
}
.wc-about__misi-item strong {
    display: block;
    margin-bottom: 0.25rem;
    font-size: 1rem;
    font-weight: 800;
    color: #1e1b4b;
}
.wc-about__misi-item p {
    margin: 0;
    color: #64748b;
    font-size: 0.86rem;
    font-weight: 600;
    line-height: 1.6;
}
.wc-about__brands {
    display: grid;
    grid-template-columns: repeat(5, minmax(0, 1fr));
    gap: 1.1rem;
    margin-top: 1.5rem;
}
.wc-about__brand {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.65rem;
    padding: 1.5rem 1rem;
    border-radius: 1.25rem;
    background: #ffffff;
    border: 1px solid rgba(226, 232, 240, 0.95);
    box-shadow: 0 10px 24px rgba(15, 23, 42, 0.04);
    text-align: center;
    transition: transform 0.25s cubic-bezier(0.16, 1, 0.3, 1), box-shadow 0.25s ease, border-color 0.25s ease;
}
.wc-about__brand:hover {
    transform: translateY(-6px);
    border-color: rgba(99, 102, 241, 0.35);
    box-shadow: 0 20px 40px rgba(99, 102, 241, 0.15);
}
.wc-about__brand-logo {
    height: 2.85rem;
    width: auto;
    max-width: 100%;
    object-fit: contain;
    filter: drop-shadow(0 2px 6px rgba(0,0,0,0.06));
    transition: transform 0.25s ease;
}
.wc-about__brand:hover .wc-about__brand-logo {
    transform: scale(1.08);
}
.wc-about__brand-name {
    color: #64748b;
    font-size: 0.78rem;
    font-weight: 700;
    margin-top: 2px;
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
