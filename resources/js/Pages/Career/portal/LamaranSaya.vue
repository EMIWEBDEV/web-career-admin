<!-- WEB CAREER — Portal Kandidat: Lamaran Saya. Data dari server (bukan sessionStorage). -->
<template>
    <Head><title>Lamaran Saya - EVO Career</title></Head>
    <div class="wca">
        <div class="wca-phead">
            <div>
                <h1>Lamaran Saya</h1>
                <p>Pantau progres seleksimu di EVO Group. Klik lamaran untuk melihat detail & mengisi formulir tahap.</p>
            </div>
            <div class="wca-phead__actions">
                <Link href="/kandidat/loker" class="wca-btn wca-btn--primary"><i class="bi bi-search"></i> Cari Lowongan</Link>
            </div>
        </div>

        <div v-if="!lamaran.length" class="wca-empty">
            <i class="bi bi-file-earmark-text"></i>
            <h4>Belum ada lamaran</h4>
            <p>Mulai dengan mencari lowongan yang cocok untukmu.</p>
            <Link href="/kandidat/loker" class="wca-btn wca-btn--primary" style="margin-top:.8rem"><i class="bi bi-search"></i> Cari Lowongan</Link>
        </div>

        <div class="lms-list">
            <Link v-for="l in lamaran" :key="l.id" :href="`/kandidat/lamaran/${l.id}`" class="lms-card">
                <span class="lms-card__bar" :style="{ background: l.warna || '#4f46e5' }"></span>
                <div class="lms-card__main">
                    <div class="lms-card__top">
                        <strong>{{ l.posisi || l.program }}</strong>
                        <span class="wca-badge" :class="statusBadge(l.status)"><i class="bi" :class="statusIkon(l.status)"></i> {{ statusLabel(l) }}</span>
                    </div>
                    <div class="lms-card__sub">{{ l.program }}<template v-if="l.lokasi"> · {{ l.lokasi }}</template> · {{ l.kode }}</div>

                    <!-- Bar progres tahap -->
                    <div v-if="l.totalTahap" class="lms-prog">
                        <div class="lms-prog__track"><div class="lms-prog__fill" :style="{ width: persen(l) + '%', background: l.warna || '#4f46e5' }"></div></div>
                        <span class="lms-prog__txt">Tahap {{ l.urutanTahap }} dari {{ l.totalTahap }}</span>
                    </div>
                    <div v-if="l.status === 'GUGUR' && l.gugurDi" class="lms-gugur"><i class="bi bi-x-circle"></i> Gugur di tahap: {{ l.gugurDi }}</div>
                </div>
                <i class="bi bi-chevron-right lms-card__chev"></i>
            </Link>
        </div>
    </div>
</template>

<script>
import { Head, Link } from '@inertiajs/vue3';

export default {
    components: { Head, Link },
    props: {
        lamaran: { type: Array, default: () => [] },
    },
    methods: {
        statusLabel(l) {
            return { BERJALAN: 'Berjalan', LULUS: 'Diterima', GUGUR: 'Tidak lolos', MUNDUR: 'Mengundurkan diri' }[l.status] || l.status;
        },
        statusBadge(s) { return { BERJALAN: 'wca-b--indigo', LULUS: 'wca-b--green', GUGUR: 'wca-b--red', MUNDUR: 'wca-b--slate' }[s] || 'wca-b--slate'; },
        statusIkon(s) { return { BERJALAN: 'bi-hourglass-split', LULUS: 'bi-check-circle', GUGUR: 'bi-x-circle', MUNDUR: 'bi-dash-circle' }[s] || 'bi-dot'; },
        persen(l) { return l.totalTahap ? Math.round((l.urutanTahap / l.totalTahap) * 100) : 0; },
    },
};
</script>

<style scoped>
.lms-list { display: flex; flex-direction: column; gap: .7rem; }
.lms-card { display: flex; align-items: stretch; gap: .8rem; border: 1px solid rgba(11, 16, 51, .09); border-radius: 14px; background: #fff; text-decoration: none; color: inherit; overflow: hidden; transition: box-shadow 160ms ease; }
.lms-card:hover { box-shadow: 0 12px 26px -18px rgba(30, 27, 75, .4); }
.lms-card__bar { flex: 0 0 5px; }
.lms-card__main { flex: 1; min-width: 0; padding: .9rem .3rem .9rem 0; }
.lms-card__top { display: flex; align-items: center; gap: .5rem; flex-wrap: wrap; }
.lms-card__top strong { font-size: 1rem; color: #0f172a; }
.lms-card__sub { font-size: 12px; color: #64748b; margin-top: .15rem; }
.lms-card__chev { align-self: center; padding-right: 1rem; color: #cbd5e1; }
.lms-prog { display: flex; align-items: center; gap: .6rem; margin-top: .6rem; }
.lms-prog__track { flex: 1; height: 6px; border-radius: 999px; background: #eef2f7; overflow: hidden; }
.lms-prog__fill { height: 100%; border-radius: 999px; transition: width 400ms ease; }
.lms-prog__txt { font-size: 11px; font-weight: 600; color: #64748b; white-space: nowrap; }
.lms-gugur { display: inline-flex; align-items: center; gap: .35rem; margin-top: .5rem; font-size: 12px; color: #b91c1c; }
</style>
