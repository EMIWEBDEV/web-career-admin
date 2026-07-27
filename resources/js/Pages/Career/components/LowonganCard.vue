<!-- WEB CAREER — Kartu lowongan (dipakai di SemuaLowongan & halaman perkenalan tim) -->
<template>
    <Link class="rek__card" :class="{ 'is-full': isFull(job) }" :href="lowonganUrl(job.id)">
        <div v-if="isFull(job)" class="rek__ribbon">PENUH</div>
        <div class="rek__top">
            <span class="rek__type" :class="{ 'is-contract': job.tipeKerja === 'Contract', 'is-intern': job.tipeKerja === 'Internship' }">{{ job.tipeKerja }}</span>
            <span v-if="job.unggulan" class="rek__star"><i class="bi bi-star-fill"></i> Unggulan</span>
        </div>
        <h3 class="rek__title">{{ job.posisi }}</h3>
        <!-- TANPA info perusahaan/divisi — baris ini jadi sorotan BENEFIT (dari MPP). -->
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
            <span class="rek__people"><i class="bi bi-people"></i> <b>{{ job.kuotaTerisi }}/{{ job.kuota }}</b> · {{ job.pelamar }} pelamar</span>
            <span class="rek__cta">Lihat detail <i class="bi bi-arrow-right"></i></span>
        </div>
    </Link>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import { isFull, lowonganUrl } from '../careerData';

defineProps({
    job: { type: Object, required: true },
});
</script>

<style scoped>
.rek__card { position: relative; overflow: hidden; display: flex; flex-direction: column; background: rgba(255, 255, 255, 0.9); border: 1px solid rgba(226, 232, 240, 0.9); border-radius: 20px; padding: 20px; box-shadow: 0 10px 30px rgba(15, 23, 42, 0.05); text-decoration: none; transition: transform 0.18s ease, box-shadow 0.18s ease, border-color 0.18s ease; }
.rek__card:hover { transform: translateY(-5px); border-color: rgba(99, 102, 241, 0.38); box-shadow: 0 22px 46px rgba(79, 70, 229, 0.16); }
.rek__card.is-full { opacity: 0.92; }
.rek__ribbon { position: absolute; top: 15px; right: -36px; transform: rotate(45deg); background: linear-gradient(135deg, #fb7185, #e11d48); color: #fff; font-size: 0.62rem; font-weight: 800; letter-spacing: 0.14em; padding: 5px 42px; box-shadow: 0 8px 18px rgba(225, 29, 72, 0.34); z-index: 3; }
.rek__top { display: flex; align-items: center; gap: 10px; }
.rek__type { font-size: 0.66rem; font-weight: 800; letter-spacing: 0.03em; padding: 5px 11px; border-radius: 8px; color: #4f46e5; background: rgba(99, 102, 241, 0.1); }
.rek__type.is-contract { color: #b45309; background: rgba(245, 158, 11, 0.13); }
.rek__type.is-intern { color: #059669; background: rgba(16, 185, 129, 0.12); }
.rek__star { display: inline-flex; align-items: center; gap: 5px; font-size: 0.64rem; font-weight: 800; color: #b45309; background: rgba(245, 158, 11, 0.13); border-radius: 8px; padding: 5px 9px; }
.rek__title { margin: 13px 0 0; font-size: 1.06rem; font-weight: 800; color: #1e293b; letter-spacing: -0.01em; line-height: 1.25; text-wrap: pretty; }
.rek__salary { display: inline-flex; align-items: center; gap: 6px; font-size: 0.78rem; font-weight: 700; color: #b45309; margin-top: 7px; }
.rek__desc { margin: 10px 0 0; font-size: 0.8rem; line-height: 1.55; color: #64748b; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.rek__meta { display: flex; flex-direction: column; gap: 6px; margin-top: 13px; }
.rek__meta span { display: inline-flex; align-items: center; gap: 7px; font-size: 0.76rem; color: #64748b; }
.rek__meta i { color: #8b5cf6; font-size: 0.84rem; }
.rek__tags { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 13px; }
.rek__tags span { font-size: 0.68rem; font-weight: 700; color: #4f46e5; background: rgba(99, 102, 241, 0.08); border-radius: 7px; padding: 4px 9px; }
.rek__tags-more { color: #94a3b8 !important; background: #f1f5f9 !important; }
.rek__foot { display: flex; align-items: center; justify-content: space-between; gap: 10px; margin-top: auto; padding-top: 14px; border-top: 1px solid #f1f2f9; }
.rek__people { display: inline-flex; align-items: center; gap: 6px; font-size: 0.68rem; color: #94a3b8; }
.rek__people b { color: #6366f1; }
.rek__cta { display: inline-flex; align-items: center; gap: 5px; font-size: 0.76rem; font-weight: 800; color: #4f46e5; white-space: nowrap; }
.rek__cta i { transition: transform 0.18s ease; }
.rek__card:hover .rek__cta i { transform: translateX(4px); }
</style>
