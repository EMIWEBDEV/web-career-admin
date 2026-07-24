<!-- WEB CAREER — Section 3: Lowongan Terbuka (LIGHT, 1:1 desain "EVO Career Landing") -->
<template>
    <section id="lowongan" class="rek">
        <div class="wc-sec-head wc-reveal">
            <span class="rek__eyebrow">✦ Lowongan Terbuka</span>
            <h2>Temukan peran <span class="wc-grad">terbaikmu</span>.</h2>
            <p>Cari berdasarkan posisi, skill, atau lokasi. {{ lowongan.length }} peluang menanti.</p>
        </div>

        <div v-if="tampil.length" class="rek__grid">
            <Link
                v-for="(job, i) in tampil"
                :key="job.id"
                class="rek__card wc-reveal"
                :class="{ 'is-full': isFull(job) }"
                :style="{ '--d': i * 70 + 'ms' }"
                :href="lowonganUrl(job.id)"
            >
                <div v-if="isFull(job)" class="rek__ribbon">PENUH</div>

                <div class="rek__top">
                    <span class="rek__type" :class="{ 'is-contract': job.tipeKerja === 'Contract', 'is-intern': job.tipeKerja === 'Internship' }">{{ job.tipeKerja }}</span>
                    <span v-if="job.unggulan" class="rek__star"><i class="bi bi-star-fill"></i> Unggulan</span>
                </div>

                <h3 class="rek__title">{{ job.posisi }}</h3>

                <div v-if="(job.benefit || []).length" class="rek__salary">
                    <i class="bi bi-gift"></i>
                    {{ job.benefit[0] }}<template v-if="job.benefit.length > 1"> · +{{ job.benefit.length - 1 }} benefit</template>
                </div>

                <p class="rek__desc">{{ job.ringkasan }}</p>

                <div class="rek__meta">
                    <span><i class="bi bi-geo-alt"></i> {{ job.lokasi }} · {{ job.tempatKerja }}</span>
                    <span v-if="job.pengalaman && job.pengalaman !== '—'"><i class="bi bi-briefcase"></i> {{ job.pengalaman }}</span>
                </div>

                <div class="rek__tags">
                    <span v-for="s in (job.skill || []).slice(0, 3)" :key="s">{{ s }}</span>
                    <span v-if="(job.skill || []).length > 3" class="rek__tags-more">+{{ job.skill.length - 3 }}</span>
                </div>

                <div class="rek__foot">
                    <span class="rek__people">
                        <i class="bi bi-people"></i> <b>{{ job.kuotaTerisi }}/{{ job.kuota }}</b> · {{ job.pelamar }} pelamar
                    </span>
                    <span class="rek__cta">Lihat detail <i class="bi bi-arrow-right"></i></span>
                </div>
            </Link>
        </div>

        <div v-else class="rek__empty">
            <i class="bi bi-clipboard-x"></i>
            <h4>Belum ada lowongan terbuka</h4>
            <p>Nantikan peluang karier terbaru dari EVO Group.</p>
        </div>

        <!-- Landing hanya cuplikan 6 — pencarian & filter lengkap ada di halaman "Semua Lowongan". -->
        <div v-if="lowongan.length > MAKS" class="rek__seeall">
            <Link href="/karir/lowongan" class="rek__seeall-btn">
                Lihat Semua {{ lowongan.length }} Lowongan
                <i class="bi bi-arrow-right"></i>
            </Link>
        </div>
    </section>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { isFull, lowonganUrl } from '../careerData';

const props = defineProps({
    lowongan: { type: Array, default: () => [] },
    departments: { type: Array, default: () => [] },
    locations: { type: Array, default: () => [] },
});

// Landing menampilkan MAKSIMAL 6 kartu; sisanya lewat tombol "Lihat Semua".
const MAKS = 6;
const tampil = computed(() => props.lowongan.slice(0, MAKS));
</script>

<style scoped>
.rek {
    width: min(1180px, calc(100vw - 2rem));
    margin: 0 auto;
    padding: clamp(2.75rem, 6vw, 4rem) 0 0;
}
.rek__eyebrow {
    display: inline-block;
    font-size: 0.72rem;
    font-weight: 800;
    letter-spacing: 0.18em;
    text-transform: uppercase;
    color: #8b5cf6;
}
.rek__grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 1.05rem;
}
.rek__card {
    position: relative;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    background: rgba(255, 255, 255, 0.9);
    border: 1px solid rgba(226, 232, 240, 0.9);
    border-radius: 20px;
    padding: 20px;
    box-shadow: 0 10px 30px rgba(15, 23, 42, 0.05);
    text-decoration: none;
    transition: transform 0.18s ease, box-shadow 0.18s ease, border-color 0.18s ease;
}
.rek__card:hover {
    transform: translateY(-5px);
    border-color: rgba(99, 102, 241, 0.38);
    box-shadow: 0 22px 46px rgba(79, 70, 229, 0.16);
}
.rek__card.is-full {
    opacity: 0.92;
}
.rek__ribbon {
    position: absolute;
    top: 15px;
    right: -36px;
    transform: rotate(45deg);
    background: linear-gradient(135deg, #fb7185, #e11d48);
    color: #fff;
    font-size: 0.62rem;
    font-weight: 800;
    letter-spacing: 0.14em;
    padding: 5px 42px;
    box-shadow: 0 8px 18px rgba(225, 29, 72, 0.34);
    z-index: 3;
}
.rek__top {
    display: flex;
    align-items: center;
    gap: 10px;
}
.rek__type {
    font-size: 0.66rem;
    font-weight: 800;
    letter-spacing: 0.03em;
    padding: 5px 11px;
    border-radius: 8px;
    color: #4f46e5;
    background: rgba(99, 102, 241, 0.1);
}
.rek__type.is-contract {
    color: #b45309;
    background: rgba(245, 158, 11, 0.13);
}
.rek__type.is-intern {
    color: #059669;
    background: rgba(16, 185, 129, 0.12);
}
.rek__star {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-size: 0.64rem;
    font-weight: 800;
    color: #b45309;
    background: rgba(245, 158, 11, 0.13);
    border-radius: 8px;
    padding: 5px 9px;
}
.rek__title {
    margin: 13px 0 0;
    font-size: 1.06rem;
    font-weight: 800;
    color: #1e293b;
    letter-spacing: -0.01em;
    line-height: 1.25;
    text-wrap: pretty;
}
.rek__salary {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 0.78rem;
    font-weight: 700;
    color: #b45309;
    margin-top: 7px;
}
.rek__desc {
    margin: 10px 0 0;
    font-size: 0.8rem;
    line-height: 1.55;
    color: #64748b;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.rek__meta {
    display: flex;
    flex-direction: column;
    gap: 6px;
    margin-top: 13px;
}
.rek__meta span {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    font-size: 0.76rem;
    color: #64748b;
}
.rek__meta i {
    color: #8b5cf6;
    font-size: 0.84rem;
}
.rek__tags {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-top: 13px;
}
.rek__tags span {
    font-size: 0.68rem;
    font-weight: 700;
    color: #4f46e5;
    background: rgba(99, 102, 241, 0.08);
    border-radius: 7px;
    padding: 4px 9px;
}
.rek__tags-more {
    color: #94a3b8 !important;
    background: #f1f5f9 !important;
}
.rek__foot {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    margin-top: 15px;
    padding-top: 14px;
    border-top: 1px solid #f1f2f9;
}
.rek__people {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 0.68rem;
    color: #94a3b8;
}
.rek__people b {
    color: #6366f1;
}
.rek__cta {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-size: 0.76rem;
    font-weight: 800;
    color: #4f46e5;
    white-space: nowrap;
}
.rek__cta i {
    transition: transform 0.18s ease;
}
.rek__card:hover .rek__cta i {
    transform: translateX(4px);
}
.rek__empty {
    text-align: center;
    padding: 3rem 1rem;
    color: #94a3b8;
}
.rek__empty i {
    font-size: 2.4rem;
    color: #c4b5fd;
}
.rek__empty h4 {
    margin: 0.8rem 0 0.3rem;
    color: #475569;
    font-weight: 800;
}
.rek__empty p {
    margin: 0;
    font-size: 0.85rem;
}
.rek__seeall {
    display: flex;
    justify-content: center;
    margin-top: 1.9rem;
}
.rek__seeall-btn {
    display: inline-flex;
    align-items: center;
    gap: 9px;
    font-size: 0.875rem;
    font-weight: 800;
    color: #fff;
    padding: 14px 26px;
    border-radius: 14px;
    text-decoration: none;
    background: linear-gradient(135deg, #8b5cf6, #6366f1);
    box-shadow: 0 14px 32px rgba(99, 102, 241, 0.32);
    transition: transform 0.16s ease, box-shadow 0.16s ease;
}
.rek__seeall-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 20px 42px rgba(99, 102, 241, 0.42);
    color: #fff;
}
.rek__seeall-btn i {
    transition: transform 0.18s ease;
}
.rek__seeall-btn:hover i {
    transform: translateX(4px);
}
</style>
