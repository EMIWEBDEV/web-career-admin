<!-- WEB CAREER — Portal Kandidat: Cari Lowongan. Posisi BUKA dari program BERJALAN. -->
<template>
    <Head><title>Cari Lowongan - EVO Career</title></Head>
    <div class="wca">
        <div class="wca-phead">
            <div>
                <h1>Cari Lowongan</h1>
                <p>Posisi yang sedang dibuka di EVO Group. Klik <b>Lamar</b> untuk mulai proses seleksi.</p>
            </div>
        </div>

        <!-- Filter kategori -->
        <div class="wca-segt">
            <button class="wca-segt__it" :class="{ on: tab === '' }" @click="tab = ''"><i class="bi bi-grid"></i> Semua</button>
            <button class="wca-segt__it" :class="{ on: tab === 'REKRUTMEN' }" @click="tab = 'REKRUTMEN'"><i class="bi bi-briefcase"></i> Rekrutmen</button>
            <button class="wca-segt__it" :class="{ on: tab === 'MT' }" @click="tab = 'MT'"><i class="bi bi-mortarboard"></i> Management Trainee</button>
            <button class="wca-segt__it" :class="{ on: tab === 'INTERNSHIP' }" @click="tab = 'INTERNSHIP'"><i class="bi bi-backpack"></i> Internship</button>
        </div>

        <div v-if="!filtered.length" class="wca-empty">
            <i class="bi bi-search"></i>
            <h4>Belum ada lowongan pada kategori ini</h4>
            <p>Coba kategori lain, atau cek lagi nanti.</p>
        </div>

        <div class="lok-grid">
            <div v-for="l in filtered" :key="l.id" class="lok-card">
                <div class="lok-card__top">
                    <span class="lok-card__dot" :style="{ background: l.warna || '#4f46e5' }"></span>
                    <span class="wca-badge" :class="katBadge(l.kategori)">{{ katLabel(l.kategori) }}</span>
                </div>
                <h3 class="lok-card__title">{{ l.posisi }}</h3>
                <div class="lok-card__prog">{{ l.program }}</div>
                <div class="lok-card__meta">
                    <span><i class="bi bi-building"></i> {{ l.departemen || '—' }}</span>
                    <span><i class="bi bi-geo-alt"></i> {{ l.lokasi || '—' }}</span>
                    <span v-if="l.level"><i class="bi bi-bar-chart-steps"></i> {{ l.level }}</span>
                    <span><i class="bi bi-people"></i> {{ l.kuota }} kuota</span>
                </div>
                <button class="wca-btn wca-btn--primary lok-card__btn" :disabled="melamar === l.id" @click="lamar(l)">
                    <i class="bi" :class="melamar === l.id ? 'bi-arrow-repeat spin' : 'bi-send'"></i>
                    {{ melamar === l.id ? 'Memproses…' : 'Lamar Posisi Ini' }}
                </button>
            </div>
        </div>

        <transition name="wca-toast"><div v-if="toast" class="wca-toast" :class="{ 'is-err': toastErr }"><i class="bi" :class="toastErr ? 'bi-exclamation-circle-fill' : 'bi-check-circle-fill'"></i> {{ toast }}</div></transition>
    </div>
</template>

<script>
import axios from 'axios';
import { Head, router } from '@inertiajs/vue3';

const CFG = { headers: { Accept: 'application/json' } };

export default {
    components: { Head },
    props: {
        loker: { type: Array, default: () => [] },
    },
    data() {
        return { tab: '', melamar: null, toast: '', toastErr: false, tm: null };
    },
    computed: {
        filtered() {
            return this.tab ? this.loker.filter((l) => l.kategori === this.tab) : this.loker;
        },
    },
    methods: {
        katLabel(k) { return { REKRUTMEN: 'Rekrutmen', MT: 'Management Trainee', INTERNSHIP: 'Internship' }[k] || k; },
        katBadge(k) { return { MT: 'wca-b--gold', INTERNSHIP: 'wca-b--green', REKRUTMEN: 'wca-b--sky' }[k] || 'wca-b--slate'; },
        async lamar(l) {
            if (this.melamar) return;
            this.melamar = l.id;
            try {
                const res = await axios.post('/api/v1/lamaran', { programId: l.programId, posisiId: l.id }, CFG);
                const id = res.data?.result?.lamaran;
                this.notice(res.data?.message || 'Lamaran dibuat.');
                // Langsung ke detail lamaran supaya kandidat bisa mengisi formulir tahap pertama.
                if (id) setTimeout(() => router.visit(`/kandidat/lamaran/${id}`), 500);
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal melamar.', true);
            } finally {
                this.melamar = null;
            }
        },
        notice(x, err = false) { this.toast = x; this.toastErr = err; if (this.tm) clearTimeout(this.tm); this.tm = setTimeout(() => (this.toast = ''), 3500); },
    },
};
</script>

<style scoped>
.lok-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(17rem, 1fr)); gap: 1rem; }
.lok-card { display: flex; flex-direction: column; gap: .5rem; border: 1px solid rgba(11, 16, 51, .09); border-radius: 16px; padding: 1.1rem; background: #fff; transition: box-shadow 180ms ease, transform 180ms ease; }
.lok-card:hover { box-shadow: 0 14px 30px -18px rgba(30, 27, 75, .4); transform: translateY(-2px); }
.lok-card__top { display: flex; align-items: center; gap: .5rem; }
.lok-card__dot { width: .7rem; height: .7rem; border-radius: 50%; }
.lok-card__title { margin: .2rem 0 0; font-size: 1.05rem; font-weight: 800; color: #0f172a; letter-spacing: -.015em; }
.lok-card__prog { font-size: 12px; color: #64748b; }
.lok-card__meta { display: flex; flex-direction: column; gap: .3rem; margin: .4rem 0; font-size: 12.5px; color: #475569; }
.lok-card__meta .bi { color: #7c3aed; margin-right: .25rem; }
.lok-card__btn { margin-top: auto; width: 100%; justify-content: center; }
.spin { animation: lokspin 1s linear infinite; }
@keyframes lokspin { to { transform: rotate(360deg); } }
.wca-toast.is-err { background: #b91c1c; }
</style>
