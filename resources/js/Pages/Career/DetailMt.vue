<!-- WEB CAREER — Halaman Detail Management Trainee (route: /karir/landing-page/mt/{id}) -->
<template>
    <Head>
        <title>{{ mt.nama }} - EVO Group Career</title>
    </Head>

    <CareerLayout :has-mt="hasMt" :offices="offices">
        <section class="wc-detail">
            <button type="button" class="wc-back" @click="goToSection('mt')"><i class="bi bi-arrow-left"></i> Kembali ke Program</button>

            <div class="wc-detail__wrap">
                <div class="wc-detail__main">
                    <header class="wc-dhead wc-dhead--mt wc-reveal">
                        <div class="wc-dhead__pattern" aria-hidden="true"></div>
                        <div class="wc-dhead__badges">
                            <span class="wc-badge wc-badge--violet"><i class="bi bi-mortarboard-fill"></i> Management Trainee</span>
                            <span class="wc-badge wc-badge--muted">{{ mt.batch }}</span>
                            <span class="wc-badge" :class="statusClass(mt.status)">{{ statusLabel(mt.status) }}</span>
                        </div>
                        <h1>{{ mt.nama }}</h1>
                        <p class="wc-dhead__tag">{{ mt.tagline }}</p>
                        <div class="wc-dhead__meta">
                            <span><i class="bi bi-geo-alt"></i> {{ mt.penempatan }}</span>
                            <span><i class="bi bi-mortarboard"></i> {{ mt.tipeKegiatan }}</span>
                            <span><i class="bi bi-hourglass-split"></i> {{ mt.durasi }}</span>
                        </div>
                    </header>

                    <section class="wc-quota-banner wc-reveal" :class="{ 'is-full': isFull(mt) }" style="--d: 60ms">
                        <div class="wc-quota-banner__info">
                            <div class="wc-quota-banner__head">
                                <strong><i class="bi" :class="isFull(mt) ? 'bi-lock-fill' : 'bi-people-fill'"></i> Kuota Peserta</strong>
                                <span>{{ mt.kuotaTerisi }} dari {{ mt.kuota }} kursi terisi</span>
                            </div>
                            <div class="wc-quota-banner__bar"><span :class="{ full: isFull(mt) }" :style="{ width: kuotaPct(mt) + '%' }"></span></div>
                        </div>
                        <div class="wc-quota-banner__badge" :class="statusClass(mt.status)">
                            <i class="bi" :class="isFull(mt) ? 'bi-x-octagon-fill' : 'bi-check-circle-fill'"></i>
                            {{ isFull(mt) ? 'Kuota Penuh' : 'Masih Tersedia' }}
                        </div>
                    </section>

                    <section class="wc-block wc-reveal">
                        <h2><i class="bi bi-file-text"></i> Tentang Kegiatan</h2>
                        <p>{{ mt.deskripsi }}</p>
                        <div v-if="mt.catatanKegiatan" class="wc-callout">
                            <i class="bi bi-megaphone-fill"></i><span>{{ mt.catatanKegiatan }}</span>
                        </div>
                    </section>

                    <section class="wc-block wc-reveal">
                        <h2><i class="bi bi-info-circle"></i> Informasi Kegiatan</h2>
                        <div class="wc-info-grid">
                            <div><small>Jenis Kegiatan</small><b>{{ mt.tipeKegiatan }}</b></div>
                            <div><small>Penempatan</small><b>{{ mt.penempatan }}</b></div>
                            <div><small>Durasi Program</small><b>{{ mt.durasi }}</b></div>
                            <div><small>Ikatan Dinas</small><b>{{ mt.ikatan }}</b></div>
                            <div><small>Total Kuota</small><b>{{ mt.kuota }} kursi</b></div>
                            <div><small>Total Pelamar</small><b>{{ mt.pelamar }} orang</b></div>
                        </div>
                    </section>

                    <section class="wc-block wc-reveal">
                        <h2><i class="bi bi-gift"></i> Apa yang Kamu Dapatkan</h2>
                        <div class="wc-benefit-grid"><div v-for="(b, i) in mt.benefit" :key="i" class="wc-benefit"><i class="bi bi-patch-check-fill"></i><span>{{ b }}</span></div></div>
                    </section>

                    <section v-if="mt.fasilitas && mt.fasilitas.length" class="wc-block wc-reveal">
                        <h2><i class="bi bi-box2-heart"></i> Fasilitas</h2>
                        <div class="wc-tags wc-tags--lg"><span v-for="f in mt.fasilitas" :key="f">{{ f }}</span></div>
                    </section>

                    <section v-if="mt.kriteria && mt.kriteria.length" class="wc-block wc-reveal">
                        <h2><i class="bi bi-clipboard-check"></i> Kriteria Peserta</h2>
                        <ul class="wc-list"><li v-for="(t, i) in mt.kriteria" :key="i"><i class="bi bi-check-circle-fill"></i><span>{{ t }}</span></li></ul>
                    </section>

                    <!-- Kampus Sasaran hanya untuk channel KAMPUS (ada whitelist). UMUM -> kosong -> disembunyikan. -->
                    <section v-if="mt.targetKampus && mt.targetKampus.length" class="wc-block wc-reveal">
                        <h2><i class="bi bi-mortarboard"></i> Kampus Sasaran</h2>
                        <div class="wc-tags wc-tags--lg"><span v-for="c in mt.targetKampus" :key="c">{{ c }}</span></div>
                    </section>

                    <!-- Jadwal Kegiatan WAJIB dari DB. Tidak ada agenda -> kartu hilang. -->
                    <section v-if="mt.jadwal && mt.jadwal.length" class="wc-block wc-reveal">
                        <h2><i class="bi bi-calendar-range"></i> Jadwal Kegiatan</h2>
                        <ol class="wc-timeline">
                            <li v-for="(j, i) in mt.jadwal" :key="i">
                                <span class="wc-timeline__dot"></span>
                                <div class="wc-timeline__body"><strong>{{ j.label }}</strong><span class="wc-timeline__date"><i class="bi bi-calendar3"></i> {{ j.tanggal }}</span></div>
                            </li>
                        </ol>
                    </section>

                    <!-- Tahapan Seleksi WAJIB dari DB (alur). Tanpa tahap -> kartu hilang. -->
                    <section v-if="mt.pipeline && mt.pipeline.length" class="wc-block wc-reveal">
                        <h2><i class="bi bi-signpost-split"></i> Tahapan Seleksi</h2>
                        <ol class="wc-pipeline"><li v-for="(p, i) in mt.pipeline" :key="i"><span class="wc-pipeline__num">{{ i + 1 }}</span><div><strong>{{ p.label }}</strong><small>{{ stageTypeLabel(p.tipe) }}</small></div></li></ol>
                    </section>
                </div>

                <aside class="wc-side wc-reveal" style="--d: 120ms">
                    <div class="wc-apply">
                        <div class="wc-apply__title"><i class="bi bi-mortarboard-fill"></i> Info Pendaftaran</div>
                        <ul class="wc-apply__facts">
                            <li><span><i class="bi bi-people"></i> Kuota</span><b>{{ mt.kuotaTerisi }} / {{ mt.kuota }} kursi</b></li>
                            <li><span><i class="bi bi-person-lines-fill"></i> Pelamar</span><b>{{ mt.pelamar }} orang</b></li>
                            <li><span><i class="bi bi-calendar-check"></i> Dibuka</span><b>{{ formatDateTime(mt.tanggalBuka) }}</b></li>
                            <li><span><i class="bi bi-calendar-x"></i> Ditutup</span><b>{{ formatDateTime(mt.tanggalTutup) }}</b></li>
                            <li><span><i class="bi bi-megaphone"></i> Pengumuman</span><b>{{ formatDate(mt.tanggalPengumuman) }}</b></li>
                            <li><span><i class="bi bi-clock"></i> Sisa waktu</span><b :class="{ 'wc-danger': isFull(mt) || daysLeft(mt.tanggalTutup) <= 7 }">{{ isFull(mt) ? 'Ditutup' : deadlineLabel(mt.tanggalTutup) }}</b></li>
                        </ul>
                        <button v-if="!isFull(mt)" type="button" class="wc-btn wc-btn--primary wc-btn--full" @click="goApply(mt)"><i class="bi bi-send-fill"></i> Daftar Program</button>
                        <button v-else type="button" class="wc-btn wc-btn--disabled wc-btn--full" disabled><i class="bi bi-lock-fill"></i> Kuota Telah Penuh</button>
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
import { daysLeft, deadlineLabel, formatDate, formatDateTime, goApply, goToSection, isFull, kuotaPct, observeReveal, stageTypeLabel, statusClass, statusLabel } from './careerData';

defineOptions({ layout: null });

const props = defineProps({
    programMt: { type: Object, default: () => ({}) },
    hasMt: { type: Boolean, default: false },
    offices: { type: Array, default: () => [] },
});

const mt = computed(() => props.programMt || {});
const hasMt = computed(() => props.hasMt);
const offices = computed(() => props.offices || []);

let revealObs = null;
onMounted(() => nextTick(() => (revealObs = observeReveal())));
onUnmounted(() => revealObs?.disconnect());
</script>
