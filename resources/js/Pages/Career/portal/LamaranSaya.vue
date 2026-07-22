<!-- WEB CAREER — Portal Kandidat: Lamaran Saya. Data dari SESI LOGIN (DB), bukan sessionStorage.
     Kartu dibuat SAMA seperti landing: MT bergaya gelap (SS3), rekrutmen putih (SS2);
     hanya tombolnya menjadi "Lihat Lamaran Saya". -->
<template>
    <Head><title>Lamaran Saya - EVO Career</title></Head>
    <div class="wca">
        <div class="wca-phead">
            <div>
                <h1>Lamaran Saya</h1>
                <p>Pantau progres seleksimu di EVO Group. Klik lamaran untuk melihat detail & mengisi formulir tahap.</p>
            </div>
            <div class="wca-phead__actions">
                <Link href="/test/karir/landing-page" class="wca-btn wca-btn--primary"><i class="bi bi-search"></i> Cari Lowongan</Link>
            </div>
        </div>

        <div v-if="!lamaran.length" class="wca-empty">
            <i class="bi bi-file-earmark-text"></i>
            <h4>Belum ada lamaran</h4>
            <p>Mulai dengan mencari lowongan yang cocok untukmu.</p>
            <Link href="/test/karir/landing-page" class="wca-btn wca-btn--primary" style="margin-top:.8rem"><i class="bi bi-search"></i> Cari Lowongan</Link>
        </div>

        <div class="lms-grid">
            <div v-for="l in lamaran" :key="l.id" class="lms-item" :class="'lms-item--' + katKey(l.kategori)">
                <button type="button" class="lms-del" :disabled="menghapus === l.id" title="Hapus / batalkan lamaran ini" @click="minta(l)">
                    <i class="bi" :class="menghapus === l.id ? 'bi-arrow-repeat spin' : 'bi-trash'"></i>
                </button>

                <!-- ── MT: sama seperti kartu landing (SS3) ── -->
                <Link v-if="l.kategori === 'MT'" :href="`/kandidat/lamaran/${l.id}`" class="wc-mt__card lms-cardlink">
                    <div class="wc-mt__cardtop">
                        <span class="wc-mt__ribbon"><i class="bi bi-mortarboard-fill"></i> {{ k(l).batch || 'Management Trainee' }}</span>
                        <span class="wc-mt__status" :class="lmCls(l.status)">{{ lmLabel(l.status) }}</span>
                    </div>
                    <div class="wc-mt__head">
                        <h3>{{ k(l).nama || l.posisi || l.program }}</h3>
                        <p class="wc-mt__tag">{{ k(l).tagline || l.program }}</p>
                    </div>
                    <p class="wc-mt__desc">{{ k(l).ringkasan || ('Program ' + l.program + '.') }}</p>

                    <div class="wc-mt__meta">
                        <div><i class="bi bi-mortarboard"></i><span>{{ k(l).tipeKegiatan || 'Management Trainee' }}</span></div>
                        <div><i class="bi bi-geo-alt"></i><span>{{ k(l).penempatan || l.lokasi || '—' }}</span></div>
                        <div><i class="bi bi-hourglass-split"></i><span>{{ k(l).durasi || '—' }}</span></div>
                        <div><i class="bi bi-people"></i><span>{{ (k(l).kuota ?? 0) }} kursi</span></div>
                    </div>

                    <div class="wc-mt__quota">
                        <div class="wc-mt__quota-head">
                            <span>Progres tahap</span>
                            <b>Tahap {{ l.urutanTahap }} / {{ l.totalTahap }}</b>
                        </div>
                        <div class="wc-mt__bar"><span :class="{ full: l.status === 'GUGUR' }" :style="{ width: persen(l) + '%' }"></span></div>
                    </div>

                    <div v-if="l.status === 'GUGUR' && l.gugurDi" class="lms-note"><i class="bi bi-x-circle"></i> Tidak lolos di tahap: {{ l.gugurDi }}</div>

                    <div class="wc-mt__foot">
                        <span class="wc-mt__deadline"><i class="bi bi-calendar-check"></i> Dilamar {{ tglLamar(l.waktuLamar) }}</span>
                        <span class="wc-mt__cta">Lihat Lamaran Saya <i class="bi bi-arrow-right"></i></span>
                    </div>
                </Link>

                <!-- ── Rekrutmen / Internship: sama seperti kartu landing (SS2) ── -->
                <Link v-else :href="`/kandidat/lamaran/${l.id}`" class="wc-job lms-cardlink">
                    <div class="wc-job__glow"></div>
                    <div class="wc-job__top">
                        <span class="wc-badge" :class="typeClass(k(l).tipeKerja || 'Full-time')">{{ k(l).tipeKerja || 'Full-time' }}</span>
                        <span class="wca-badge" :class="lmBadge(l.status)"><i class="bi" :class="lmIkon(l.status)"></i> {{ lmLabel(l.status) }}</span>
                    </div>
                    <h3>{{ k(l).posisi || l.posisi || l.program }}</h3>
                    <p class="wc-job__co"><i class="bi bi-building"></i> {{ k(l).perusahaan || l.program }}</p>
                    <p class="wc-job__sum">{{ k(l).ringkasan || ('Lowongan ' + (l.posisi || l.program) + '.') }}</p>

                    <div class="wc-job__meta">
                        <span><i class="bi bi-geo-alt"></i> {{ k(l).lokasi || l.lokasi || '—' }} · {{ k(l).tempatKerja || 'On-site' }}</span>
                        <span><i class="bi bi-bar-chart-steps"></i> {{ k(l).level || 'Staff' }}</span>
                        <span><i class="bi bi-briefcase"></i> {{ k(l).pengalaman || '—' }}</span>
                    </div>

                    <div v-if="(k(l).skill || []).length" class="wc-tags">
                        <span v-for="s in (k(l).skill || []).slice(0, 3)" :key="s">{{ s }}</span>
                        <span v-if="(k(l).skill || []).length > 3" class="wc-tags__more">+{{ (k(l).skill || []).length - 3 }}</span>
                    </div>

                    <div class="wc-job__foot">
                        <span class="wc-job__quota"><i class="bi bi-calendar-check"></i> Dilamar {{ tglLamar(l.waktuLamar) }}</span>
                        <span class="wc-deadline"><i class="bi bi-signpost-split"></i> Tahap {{ l.urutanTahap }} / {{ l.totalTahap }}</span>
                    </div>

                    <div v-if="l.status === 'GUGUR' && l.gugurDi" class="lms-note"><i class="bi bi-x-circle"></i> Tidak lolos di tahap: {{ l.gugurDi }}</div>

                    <span class="wc-job__cta">Lihat Lamaran Saya <i class="bi bi-arrow-right"></i></span>
                </Link>
            </div>
        </div>

        <!-- Konfirmasi hapus -->
        <div v-if="target" class="lms-modal" @click.self="target = null">
            <div class="lms-modal__box">
                <div class="lms-modal__ic"><i class="bi bi-exclamation-triangle"></i></div>
                <h3>Hapus lamaran ini?</h3>
                <p>Lamaran <strong>{{ target.posisi || target.program }}</strong> ({{ target.kode }}) beserta isian formulirnya akan dihapus permanen. Tindakan ini tidak bisa dibatalkan.</p>
                <div class="lms-modal__act">
                    <button type="button" class="wca-btn wca-btn--soft" @click="target = null">Batal</button>
                    <button type="button" class="wca-btn wca-btn--danger" :disabled="menghapus" @click="hapus">
                        <i class="bi" :class="menghapus ? 'bi-arrow-repeat spin' : 'bi-trash'"></i> Ya, Hapus
                    </button>
                </div>
            </div>
        </div>

        <transition name="wca-toast"><div v-if="toast" class="wca-toast" :class="{ 'is-err': toastErr }"><i class="bi" :class="toastErr ? 'bi-exclamation-circle-fill' : 'bi-check-circle-fill'"></i> {{ toast }}</div></transition>
    </div>
</template>

<script>
import axios from 'axios';
import { Head, Link, router } from '@inertiajs/vue3';
import { typeClass } from '../careerData';

const CFG = { headers: { Accept: 'application/json' } };
const BULAN = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

export default {
    components: { Head, Link },
    props: {
        lamaran: { type: Array, default: () => [] },
    },
    data() {
        return { target: null, menghapus: null, toast: '', toastErr: false, tm: null };
    },
    methods: {
        typeClass,
        k(l) { return l.kartu || {}; },
        katKey(kat) { return kat === 'MT' ? 'mt' : 'rek'; },
        // Status LAMARAN (bukan status program) untuk badge.
        lmLabel(s) { return { BERJALAN: 'Berjalan', LULUS: 'Diterima', GUGUR: 'Tidak lolos', MUNDUR: 'Mengundurkan diri' }[s] || s; },
        lmCls(s) { return { BERJALAN: 'wc-st--open', LULUS: 'wc-st--open', GUGUR: 'wc-st--full', MUNDUR: 'wc-st--soon' }[s] || 'wc-st--open'; },
        lmBadge(s) { return { BERJALAN: 'wca-b--indigo', LULUS: 'wca-b--green', GUGUR: 'wca-b--red', MUNDUR: 'wca-b--slate' }[s] || 'wca-b--slate'; },
        lmIkon(s) { return { BERJALAN: 'bi-hourglass-split', LULUS: 'bi-check-circle', GUGUR: 'bi-x-circle', MUNDUR: 'bi-dash-circle' }[s] || 'bi-dot'; },
        persen(l) { return l.totalTahap ? Math.max(8, Math.round((l.urutanTahap / l.totalTahap) * 100)) : 0; },
        tglLamar(iso) {
            if (!iso) return '—';
            const d = new Date(String(iso).replace(' ', 'T'));
            if (Number.isNaN(d.getTime())) return '—';
            return `${d.getDate()} ${BULAN[d.getMonth()]} ${d.getFullYear()}`;
        },
        minta(l) { this.target = l; },
        async hapus() {
            if (this.menghapus || !this.target) return;
            const l = this.target;
            this.menghapus = l.id;
            try {
                await axios.delete(`/api/v1/lamaran/${l.id}`, CFG);
                this.notice('Lamaran dihapus.');
                this.target = null;
                router.reload({ only: ['lamaran'] });
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal menghapus lamaran.', true);
            } finally {
                this.menghapus = null;
            }
        },
        notice(x, err = false) { this.toast = x; this.toastErr = err; if (this.tm) clearTimeout(this.tm); this.tm = setTimeout(() => (this.toast = ''), 3200); },
    },
};
</script>

<style scoped>
.lms-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(21rem, 1fr)); gap: 1.4rem; align-items: start; }
.lms-item { position: relative; }
.lms-cardlink { display: block; text-decoration: none; height: 100%; }

/* MT dipakai di luar section gelap → beri latar gelap sendiri agar seperti SS3. */
.lms-item--mt :deep(.wc-mt__card) {
    background:
        radial-gradient(circle at 12% 18%, rgba(99, 102, 241, .35), transparent 46%),
        radial-gradient(circle at 88% 84%, rgba(124, 58, 237, .32), transparent 46%),
        linear-gradient(155deg, #0b1120 0%, #1e1b4b 52%, #0f172a 100%);
    border-color: rgba(255, 255, 255, .14);
}

/* Tombol hapus melayang di pojok kartu. */
.lms-del { position: absolute; top: .7rem; right: .7rem; z-index: 3; width: 30px; height: 30px; border: 1px solid rgba(185, 28, 28, .3); border-radius: 9px; background: rgba(255, 255, 255, .92); color: #b91c1c; cursor: pointer; font-size: .82rem; display: flex; align-items: center; justify-content: center; transition: background 140ms ease, color 140ms ease; }
.lms-del:hover:not(:disabled) { background: #b91c1c; color: #fff; }
.lms-del:disabled { opacity: .55; cursor: default; }

.lms-note { display: flex; align-items: center; gap: .35rem; margin-top: .6rem; font-size: 12px; font-weight: 700; color: #fca5a5; }
.lms-item--rek .lms-note { color: #b91c1c; }

.spin { animation: lmsspin 1s linear infinite; display: inline-block; }
@keyframes lmsspin { to { transform: rotate(360deg); } }

.lms-modal { position: fixed; inset: 0; z-index: 60; background: rgba(15, 23, 42, .45); display: flex; align-items: center; justify-content: center; padding: 1rem; }
.lms-modal__box { width: 100%; max-width: 26rem; background: #fff; border-radius: 18px; padding: 1.5rem; text-align: center; box-shadow: 0 24px 60px -20px rgba(15, 23, 42, .5); }
.lms-modal__ic { width: 3rem; height: 3rem; margin: 0 auto .8rem; border-radius: 50%; display: flex; align-items: center; justify-content: center; background: rgba(185, 28, 28, .1); color: #b91c1c; font-size: 1.4rem; }
.lms-modal__box h3 { margin: 0 0 .4rem; font-size: 1.05rem; color: #0f172a; }
.lms-modal__box p { font-size: 13px; color: #64748b; line-height: 1.55; margin: 0 0 1.2rem; }
.lms-modal__act { display: flex; gap: .6rem; justify-content: center; }
.wca-toast.is-err { background: #b91c1c; }
</style>
