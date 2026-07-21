<!-- WEB CAREER — Halaman Detail Lowongan (route: /test/karir/landing-page/lowongan/{id}) -->
<template>
    <Head>
        <title>{{ job.posisi }} - EVO Group Career</title>
    </Head>

    <CareerLayout :has-mt="hasMt" :offices="offices">
        <section class="wc-detail">
            <button type="button" class="wc-back" @click="goToSection('lowongan')"><i class="bi bi-arrow-left"></i> Kembali ke Lowongan</button>

            <div class="wc-detail__wrap">
                <div class="wc-detail__main">
                    <header class="wc-dhead wc-reveal">
                        <div class="wc-dhead__pattern" aria-hidden="true"></div>
                        <div class="wc-dhead__badges">
                            <span class="wc-badge" :class="typeClass(job.tipeKerja)">{{ job.tipeKerja }}</span>
                            <span v-if="job.unggulan" class="wc-badge wc-badge--star"><i class="bi bi-star-fill"></i> Unggulan</span>
                            <span class="wc-badge wc-badge--muted"><i class="bi bi-diagram-3"></i> {{ job.departemen }}</span>
                        </div>
                        <h1>{{ job.posisi }}</h1>
                        <p class="wc-dhead__co"><i class="bi bi-building"></i> {{ job.perusahaan }}</p>
                        <div class="wc-dhead__meta">
                            <span><i class="bi bi-geo-alt"></i> {{ job.lokasi }} · {{ job.tempatKerja }}</span>
                            <span><i class="bi bi-bar-chart-steps"></i> {{ job.level }}</span>
                            <span><i class="bi bi-briefcase"></i> {{ job.pengalaman }}</span>
                        </div>
                    </header>

                    <section class="wc-quota-banner wc-reveal" :class="{ 'is-full': isFull(job) }" style="--d: 60ms">
                        <div class="wc-quota-banner__info">
                            <div class="wc-quota-banner__head">
                                <strong><i class="bi" :class="isFull(job) ? 'bi-lock-fill' : 'bi-people-fill'"></i> Kuota Posisi</strong>
                                <span>{{ job.kuotaTerisi }} dari {{ job.kuota }} posisi terisi</span>
                            </div>
                            <div class="wc-quota-banner__bar"><span :class="{ full: isFull(job) }" :style="{ width: kuotaPct(job) + '%' }"></span></div>
                        </div>
                        <div class="wc-quota-banner__badge" :class="isFull(job) ? 'wc-st--full' : 'wc-st--open'">
                            <i class="bi" :class="isFull(job) ? 'bi-x-octagon-fill' : 'bi-check-circle-fill'"></i>
                            {{ isFull(job) ? 'Kuota Penuh' : 'Masih Menerima' }}
                        </div>
                    </section>

                    <section class="wc-block wc-reveal"><h2><i class="bi bi-file-text"></i> Deskripsi Pekerjaan</h2><p>{{ job.deskripsi }}</p></section>
                    <section class="wc-block wc-reveal">
                        <h2><i class="bi bi-list-check"></i> Tanggung Jawab</h2>
                        <ul class="wc-list"><li v-for="(t, i) in job.tanggungJawab" :key="i"><i class="bi bi-check-circle-fill"></i><span>{{ t }}</span></li></ul>
                    </section>
                    <section class="wc-block wc-reveal">
                        <h2><i class="bi bi-clipboard-check"></i> Persyaratan</h2>
                        <ul class="wc-list"><li v-for="(t, i) in job.persyaratan" :key="i"><i class="bi bi-dot"></i><span>{{ t }}</span></li></ul>
                    </section>
                    <section class="wc-block wc-reveal">
                        <h2><i class="bi bi-tags"></i> Skill yang Dibutuhkan</h2>
                        <div class="wc-tags wc-tags--lg"><span v-for="s in job.skill" :key="s">{{ s }}</span></div>
                    </section>
                    <section class="wc-block wc-reveal">
                        <h2><i class="bi bi-gift"></i> Benefit</h2>
                        <div class="wc-benefit-grid"><div v-for="(b, i) in job.benefit" :key="i" class="wc-benefit"><i class="bi bi-patch-check-fill"></i><span>{{ b }}</span></div></div>
                    </section>
                    <section class="wc-block wc-reveal">
                        <h2><i class="bi bi-signpost-split"></i> Tahapan Seleksi</h2>
                        <ol class="wc-pipeline"><li v-for="(p, i) in job.pipeline" :key="i"><span class="wc-pipeline__num">{{ i + 1 }}</span><div><strong>{{ p.label }}</strong><small>{{ stageTypeLabel(p.tipe) }}</small></div></li></ol>
                    </section>
                </div>

                <aside class="wc-side wc-reveal" style="--d: 120ms">
                    <div class="wc-apply">
                        <div class="wc-apply__title"><i class="bi bi-briefcase-fill"></i> Info Lamaran</div>
                        <ul class="wc-apply__facts">
                            <li><span><i class="bi bi-people"></i> Kuota</span><b>{{ job.kuotaTerisi }}/{{ job.kuota }} posisi</b></li>
                            <li><span><i class="bi bi-person-lines-fill"></i> Pelamar</span><b>{{ job.pelamar }} orang</b></li>
                            <li><span><i class="bi bi-geo-alt"></i> Lokasi</span><b>{{ job.lokasi }}</b></li>
                            <li><span><i class="bi bi-calendar-event"></i> Ditutup</span><b>{{ formatDate(job.tanggalTutup) }}</b></li>
                            <li><span><i class="bi bi-clock"></i> Sisa waktu</span><b :class="{ 'wc-danger': isFull(job) || daysLeft(job.tanggalTutup) <= 7 }">{{ isFull(job) ? 'Ditutup' : deadlineLabel(job.tanggalTutup) }}</b></li>
                        </ul>
                        <button v-if="!isFull(job)" type="button" class="wc-btn wc-btn--primary wc-btn--full" @click="goApply(job.id)"><i class="bi bi-send-fill"></i> Lamar Sekarang</button>
                        <button v-else type="button" class="wc-btn wc-btn--disabled wc-btn--full" disabled><i class="bi bi-lock-fill"></i> Kuota Telah Penuh</button>
                        <p class="wc-apply__note"><i class="bi bi-info-circle"></i> Demo dummy — lamaran belum tersambung ke sistem.</p>
                    </div>
                </aside>
            </div>
        </section>
    </CareerLayout>
</template>

<script setup>
import { Head } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, onUnmounted } from 'vue';
import CareerLayout from './Layouts/CareerLayout.vue';
import { daysLeft, deadlineLabel, formatDate, goApply, goToSection, isFull, kuotaPct, observeReveal, stageTypeLabel, typeClass } from './careerData';

defineOptions({ layout: null });

const props = defineProps({
    lowongan: { type: Object, default: () => ({}) },
    hasMt: { type: Boolean, default: false },
    offices: { type: Array, default: () => [] },
});

const job = computed(() => props.lowongan || {});
const hasMt = computed(() => props.hasMt);
const offices = computed(() => props.offices || []);

let revealObs = null;
onMounted(() => nextTick(() => (revealObs = observeReveal())));
onUnmounted(() => revealObs?.disconnect());
</script>
