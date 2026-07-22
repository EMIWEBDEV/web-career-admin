<!-- WEB CAREER — Portal Kandidat: Detail Lamaran + timeline waterfall + akses tes pihak ke-3. -->
<template>
    <Head><title>Detail Lamaran - EVO Career</title></Head>
    <div class="wca">
        <div class="wca-phead">
            <div>
                <Link href="/kandidat/portal" class="lde-back"><i class="bi bi-arrow-left"></i> Lamaran Saya</Link>
                <h1>{{ lamaran.posisi || lamaran.program }}</h1>
                <p>{{ lamaran.program }}<template v-if="lamaran.lokasi"> · {{ lamaran.lokasi }}</template> · {{ lamaran.kode }}</p>
            </div>
            <span class="wca-badge lde-status" :class="statusBadge(lamaran.status)"><i class="bi" :class="statusIkon(lamaran.status)"></i> {{ statusLabel(lamaran.status) }}</span>
        </div>

        <!-- Progres posisi pelamar -->
        <div class="lde-progress">
            <div class="lde-progress__top">
                <span><i class="bi bi-geo-alt-fill"></i> Posisi Anda: <b>Tahap {{ posisiSekarang }} dari {{ totalTahap }}</b></span>
                <span class="lde-progress__pct">{{ persen }}%</span>
            </div>
            <div class="lde-progress__bar"><div class="lde-progress__fill" :style="{ width: persen + '%' }"></div></div>
            <div v-if="tahapLulusTerbaru" class="lde-progress__ok">
                <i class="bi bi-check-circle-fill"></i> Anda lolos <b>{{ tahapLulusTerbaru.label }}</b>, lanjut ke tahap berikutnya.
            </div>
        </div>

        <div class="lde-grid">
            <!-- ===== Waterfall timeline ===== -->
            <div class="lde-timeline">
                <div class="wca-fsection__label lde-tl-title"><i class="bi bi-signpost-split"></i> Tahapan Seleksi</div>
                <ol class="lde-steps">
                    <li v-for="t in tahap" :key="t.id" class="lde-step" :class="stepKelas(t)">
                        <span class="lde-step__dot"><i class="bi" :class="stepIkon(t)"></i></span>
                        <div class="lde-step__body">
                            <div class="lde-step__lbl">{{ t.urutan }}. {{ t.label }}</div>
                            <div class="lde-step__meta">
                                <span v-if="t.provider === 'THIRD_PARTY'" class="lde-tag lde-tag--hcl"><i class="bi bi-mortarboard"></i> Tes HCLearn</span>
                                <span v-else-if="t.formulir" class="lde-tag"><i class="bi bi-input-cursor-text"></i> Formulir</span>
                                <span class="lde-step__st" :class="stepKelas(t)">{{ stepStatus(t) }}</span>
                            </div>
                        </div>
                    </li>
                </ol>
            </div>

            <!-- ===== Panel utama ===== -->
            <div class="lde-main">
                <!-- Detail program yang dilamar -->
                <div class="lde-info">
                    <div class="lde-info__head"><i class="bi bi-briefcase-fill"></i> Detail Lamaran</div>
                    <dl class="lde-info__grid">
                        <div><dt>Program</dt><dd>{{ lamaran.program || '—' }}</dd></div>
                        <div v-if="lamaran.kategori"><dt>Kategori</dt><dd>{{ lamaran.kategori }}</dd></div>
                        <div v-if="lamaran.posisi"><dt>Posisi</dt><dd>{{ lamaran.posisi }}</dd></div>
                        <div v-if="lamaran.departemen"><dt>Departemen</dt><dd>{{ lamaran.departemen }}</dd></div>
                        <div v-if="lamaran.lokasi"><dt>Lokasi</dt><dd>{{ lamaran.lokasi }}</dd></div>
                        <div v-if="lamaran.batch"><dt>Batch</dt><dd>{{ lamaran.batch }}</dd></div>
                        <div v-if="lamaran.penyelenggara"><dt>Penyelenggara</dt><dd>{{ lamaran.penyelenggara }}</dd></div>
                        <div v-if="lamaran.waktuLamar"><dt>Tanggal Melamar</dt><dd>{{ fmtTanggal(lamaran.waktuLamar) }}</dd></div>
                    </dl>
                </div>

                <!-- Gugur -->
                <div v-if="lamaran.status === 'GUGUR'" class="lde-final lde-final--gugur">
                    <i class="bi bi-x-circle-fill"></i>
                    <div>
                        <h3>Belum berhasil kali ini</h3>
                        <p v-if="lamaran.gugurDi">Proses berhenti di tahap <b>{{ lamaran.gugurDi }}</b>.</p>
                        <p v-if="lamaran.alasanGugur" class="lde-final__note">{{ lamaran.alasanGugur }}</p>
                        <p>Jangan menyerah — masih banyak kesempatan lain di EVO Group.</p>
                    </div>
                </div>

                <!-- Diterima -->
                <div v-else-if="lamaran.status === 'LULUS'" class="lde-final lde-final--lulus">
                    <i class="bi bi-check-circle-fill"></i>
                    <div>
                        <h3>Selamat! 🎉</h3>
                        <p>Anda menyelesaikan seluruh tahap seleksi. Tim rekrutmen akan menghubungi Anda untuk langkah berikutnya.</p>
                    </div>
                </div>

                <template v-else>
                    <!-- Tahap tes pihak ke-3 (HCLearn) -->
                    <div v-if="tahapTes" class="lde-tes">
                        <div class="lde-tes__head">
                            <span class="lde-tes__ic"><i class="bi bi-mortarboard-fill"></i></span>
                            <div>
                                <div class="lde-form__eyebrow">Tahap Aktif · Tes Online</div>
                                <h3>{{ tahapTes.ujian?.namaUjian || tahapTes.label }}</h3>
                                <p>Tes ini diselenggarakan melalui platform HCLearn.</p>
                            </div>
                        </div>

                        <!-- Belum dijadwalkan admin -->
                        <div v-if="tahapTes.butuhJadwal || !tahapTes.ujian?.terjadwal" class="lde-notice lde-notice--wait">
                            <i class="bi bi-hourglass-split"></i>
                            <div>
                                <b>Menunggu penjadwalan</b>
                                <p>Tim rekrutmen belum menetapkan jadwal tes Anda. Anda akan menerima token dan waktu pengerjaan di halaman ini setelah dijadwalkan.</p>
                            </div>
                        </div>

                        <!-- Sudah dijadwalkan -->
                        <template v-else>
                            <div class="lde-cred">
                                <div class="lde-cred__item">
                                    <span class="lde-cred__lbl">Token Akses</span>
                                    <span class="lde-cred__val lde-mono">{{ tahapTes.ujian.token || '—' }}</span>
                                </div>
                                <div class="lde-cred__item">
                                    <span class="lde-cred__lbl">Kode OTP</span>
                                    <span class="lde-cred__val lde-mono">{{ tahapTes.ujian.otp || '—' }}</span>
                                </div>
                                <div class="lde-cred__item">
                                    <span class="lde-cred__lbl">Waktu Mulai</span>
                                    <span class="lde-cred__val">{{ fmtWaktu(tahapTes.ujian.waktuMulai) }}</span>
                                </div>
                                <div class="lde-cred__item">
                                    <span class="lde-cred__lbl">Waktu Berakhir</span>
                                    <span class="lde-cred__val">{{ fmtWaktu(tahapTes.ujian.waktuSelesai) }}</span>
                                </div>
                            </div>

                            <!-- Status jendela -->
                            <div v-if="sudahSelesai(tahapTes)" class="lde-notice lde-notice--done">
                                <i class="bi bi-check-circle-fill"></i>
                                <div><b>Tes selesai dikerjakan</b><p v-if="tahapTes.ujian.nilai !== null && tahapTes.ujian.nilai !== undefined">Nilai Anda: <b>{{ tahapTes.ujian.nilai }}</b> — {{ tahapTes.ujian.kelulusan || 'menunggu keputusan' }}.</p></div>
                            </div>
                            <div v-else-if="belumMulai(tahapTes)" class="lde-notice lde-notice--wait">
                                <i class="bi bi-clock-history"></i>
                                <div><b>Tes belum dibuka</b><p>Tombol akan aktif otomatis saat waktu mulai tiba{{ hitungMundur(tahapTes) ? ' — ' + hitungMundur(tahapTes) : '' }}.</p></div>
                            </div>
                            <div v-else-if="sudahLewat(tahapTes)" class="lde-notice lde-notice--err">
                                <i class="bi bi-exclamation-triangle-fill"></i>
                                <div><b>Waktu tes berakhir</b><p>Jendela pengerjaan sudah lewat. Hubungi tim rekrutmen bila ada kendala.</p></div>
                            </div>

                            <button
                                class="lde-btn-tes"
                                :disabled="!bisaAkses(tahapTes)"
                                @click="bukaTes(tahapTes)"
                            >
                                <i class="bi" :class="bisaAkses(tahapTes) ? 'bi-box-arrow-up-right' : 'bi-lock-fill'"></i>
                                {{ bisaAkses(tahapTes) ? 'Mulai Tes Sekarang' : 'Tes Terkunci' }}
                            </button>
                        </template>
                    </div>

                    <!-- Formulir yang harus diisi -->
                    <div v-else-if="tugas && komponen" class="lde-form">
                        <div class="lde-form__head">
                            <span class="lde-form__ic"><i class="bi bi-pencil-square"></i></span>
                            <div>
                                <div class="lde-form__eyebrow">Tahap Aktif</div>
                                <h3>{{ tugas.label }}</h3>
                                <p>Lengkapi formulir berikut untuk melanjutkan proses.</p>
                            </div>
                        </div>
                        <div class="lde-form__body">
                            <component :is="komponen" v-model="jawaban" :konteks="konteks" label-kirim="Kirim & Lanjutkan" @kirim="kirim" @berkas="onBerkas" />
                        </div>
                    </div>

                    <!-- Menunggu proses admin -->
                    <div v-else class="lde-wait">
                        <i class="bi bi-hourglass-split"></i>
                        <h3>Sedang dalam proses</h3>
                        <p>Tahap Anda saat ini sedang ditinjau tim rekrutmen. Kami akan mengabari perkembangannya.</p>
                    </div>
                </template>
            </div>
        </div>

        <transition name="wca-toast"><div v-if="toast" class="wca-toast" :class="{ 'is-err': toastErr }"><i class="bi" :class="toastErr ? 'bi-exclamation-circle-fill' : 'bi-check-circle-fill'"></i> {{ toast }}</div></transition>
    </div>
</template>

<script>
import axios from 'axios';
import { Head, Link, router } from '@inertiajs/vue3';
import { komponenFormulir, skemaFormulir, jawabanAwal } from '@career/formulir';

const CFG = { headers: { Accept: 'application/json' } };

export default {
    components: { Head, Link },
    props: {
        lamaran: { type: Object, default: () => ({}) },
        tahap: { type: Array, default: () => [] },
        tugas: { type: Object, default: null },
        profil: { type: Object, default: () => ({}) },
        konteks: { type: Object, default: () => ({}) },
    },
    data() {
        return {
            jawaban: {},
            berkas: {},
            mengirim: false,
            toast: '',
            toastErr: false,
            tm: null,
            now: Date.now(),
            jam: null,
        };
    },
    computed: {
        komponen() { return this.tugas ? komponenFormulir(this.tugas.komponen) : null; },
        totalTahap() { return this.lamaran.totalTahap || this.tahap.length || 0; },
        posisiSekarang() {
            const aktif = this.tahap.find((t) => t.status === 'BERJALAN');
            if (aktif) return aktif.urutan;
            const selesai = this.tahap.filter((t) => t.status === 'SELESAI').length;
            return Math.min(selesai + 1, this.totalTahap) || this.lamaran.urutanTahap || 1;
        },
        persen() {
            if (!this.totalTahap) return 0;
            const selesai = this.tahap.filter((t) => t.status === 'SELESAI' && t.hasil !== 'GUGUR').length;
            return Math.round((selesai / this.totalTahap) * 100);
        },
        tahapLulusTerbaru() {
            const lulus = this.tahap.filter((t) => t.status === 'SELESAI' && t.hasil !== 'GUGUR');
            return lulus.length ? lulus[lulus.length - 1] : null;
        },
        // Tahap tes pihak ke-3 yang sedang aktif.
        tahapTes() {
            return this.tahap.find((t) => t.status === 'BERJALAN' && t.provider === 'THIRD_PARTY') || null;
        },
    },
    mounted() {
        if (this.tugas) this.jawaban = jawabanAwal(skemaFormulir(this.tugas.komponen), this.profil);
        // Detak untuk membuka/menutup tombol tes tepat waktu.
        this.jam = setInterval(() => { this.now = Date.now(); }, 15000);
    },
    beforeUnmount() {
        if (this.jam) clearInterval(this.jam);
        if (this.tm) clearTimeout(this.tm);
    },
    methods: {
        statusLabel(s) { return { BERJALAN: 'Berjalan', LULUS: 'Diterima', GUGUR: 'Tidak lolos' }[s] || s; },
        statusBadge(s) { return { BERJALAN: 'wca-b--indigo', LULUS: 'wca-b--green', GUGUR: 'wca-b--red' }[s] || 'wca-b--slate'; },
        statusIkon(s) { return { BERJALAN: 'bi-hourglass-split', LULUS: 'bi-check-circle', GUGUR: 'bi-x-circle' }[s] || 'bi-dot'; },
        stepKelas(t) {
            if (t.status === 'SELESAI') return t.hasil === 'GUGUR' ? 'is-gugur' : 'is-lulus';
            if (t.status === 'BERJALAN') return 'is-aktif';
            return 'is-nunggu';
        },
        stepIkon(t) {
            if (t.status === 'SELESAI') return t.hasil === 'GUGUR' ? 'bi-x' : 'bi-check-lg';
            if (t.status === 'BERJALAN') return t.provider === 'THIRD_PARTY' ? 'bi-mortarboard' : 'bi-dot';
            return 'bi-lock';
        },
        stepStatus(t) {
            if (t.status === 'SELESAI') return t.hasil === 'GUGUR' ? 'Gugur' : 'Lulus';
            if (t.status === 'BERJALAN') return 'Berlangsung';
            return 'Menunggu';
        },
        // ── Gerbang waktu tes ──
        belumMulai(t) { return t.ujian?.waktuMulai ? this.now < new Date(t.ujian.waktuMulai).getTime() : false; },
        sudahLewat(t) { return t.ujian?.waktuSelesai ? this.now > new Date(t.ujian.waktuSelesai).getTime() : false; },
        sudahSelesai(t) { return t.ujian?.statusPengerjaan === 'selesai'; },
        bisaAkses(t) {
            const u = t.ujian;
            if (!u || !u.link || this.sudahSelesai(t)) return false;
            return !this.belumMulai(t) && !this.sudahLewat(t);
        },
        hitungMundur(t) {
            if (!t.ujian?.waktuMulai) return '';
            const selisih = new Date(t.ujian.waktuMulai).getTime() - this.now;
            if (selisih <= 0) return '';
            const menit = Math.floor(selisih / 60000);
            if (menit < 60) return `${menit} menit lagi`;
            const jam = Math.floor(menit / 60);
            if (jam < 24) return `${jam} jam lagi`;
            return `${Math.floor(jam / 24)} hari lagi`;
        },
        bukaTes(t) {
            if (!this.bisaAkses(t)) return;
            window.open(t.ujian.link, '_blank', 'noopener');
        },
        fmtWaktu(iso) {
            if (!iso) return '—';
            return new Date(iso).toLocaleString('id-ID', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
        },
        fmtTanggal(v) {
            if (!v) return '—';
            return new Date(v.replace(' ', 'T')).toLocaleDateString('id-ID', { day: '2-digit', month: 'long', year: 'numeric' });
        },
        onBerkas(e) {
            if (e.galat) { this.notice(e.galat, true); return; }
            if (e.file) this.berkas[e.field.key] = e.file;
        },
        async kirim(nilai) {
            if (this.mengirim || !this.tugas) return;
            this.mengirim = true;
            try {
                const res = await axios.post(`/api/v1/lamaran/tahap/${this.tugas.tahapId}/kirim`, { jawaban: nilai }, CFG);
                this.notice(res.data?.message || 'Formulir terkirim.');
                setTimeout(() => router.reload(), 800);
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal mengirim formulir.', true);
            } finally {
                this.mengirim = false;
            }
        },
        notice(x, err = false) { this.toast = x; this.toastErr = err; if (this.tm) clearTimeout(this.tm); this.tm = setTimeout(() => (this.toast = ''), 4000); },
    },
};
</script>

<style scoped>
.lde-back { display: inline-flex; align-items: center; gap: .35rem; font-size: 12.5px; color: #64748b; text-decoration: none; margin-bottom: .3rem; }
.lde-back:hover { color: #4338ca; }
.lde-status { align-self: flex-start; }

/* Progres posisi */
.lde-progress { border: 1px solid rgba(11,16,51,.09); border-radius: 14px; background: #fff; padding: .9rem 1.1rem; margin-bottom: 1.1rem; }
.lde-progress__top { display: flex; justify-content: space-between; align-items: center; font-size: 13px; color: #475569; }
.lde-progress__top .bi { color: #4f46e5; }
.lde-progress__pct { font-weight: 800; color: #4f46e5; }
.lde-progress__bar { height: 8px; border-radius: 999px; background: #eef2f7; margin-top: .5rem; overflow: hidden; }
.lde-progress__fill { height: 100%; border-radius: 999px; background: linear-gradient(90deg,#4f46e5,#7c3aed); transition: width .4s ease; }
.lde-progress__ok { margin-top: .6rem; font-size: 12.5px; color: #059669; display: flex; gap: .4rem; align-items: center; }

.lde-grid { display: grid; grid-template-columns: 17rem 1fr; gap: 1.2rem; align-items: start; }

/* Waterfall timeline */
.lde-timeline { border: 1px solid rgba(11, 16, 51, .09); border-radius: 14px; padding: 1rem; background: #fff; position: sticky; top: 1rem; }
.lde-tl-title { margin-bottom: .7rem; }
.lde-steps { list-style: none; margin: 0; padding: 0; }
.lde-step { display: flex; gap: .6rem; padding-bottom: .9rem; position: relative; }
.lde-step:not(:last-child)::before { content: ''; position: absolute; left: 12px; top: 26px; bottom: 0; width: 2px; background: #eef2f7; }
.lde-step.is-lulus:not(:last-child)::before { background: #6ee7b7; }
.lde-step__dot { flex: none; width: 26px; height: 26px; border-radius: 50%; display: grid; place-items: center; font-size: .75rem; background: #eef2f7; color: #94a3b8; z-index: 1; }
.lde-step.is-lulus .lde-step__dot { background: rgba(16,185,129,.15); color: #059669; }
.lde-step.is-gugur .lde-step__dot { background: rgba(239,68,68,.15); color: #dc2626; }
.lde-step.is-aktif .lde-step__dot { background: linear-gradient(140deg,#4f46e5,#7c3aed); color: #fff; animation: ldepulse 1.6s ease-in-out infinite; }
.lde-step__lbl { font-size: 13px; font-weight: 600; color: #334155; }
.lde-step__meta { display: flex; align-items: center; gap: .5rem; margin-top: .2rem; font-size: 11px; color: #94a3b8; flex-wrap: wrap; }
.lde-tag { display: inline-flex; align-items: center; gap: .25rem; padding: .1rem .4rem; border-radius: 6px; background: #f1f5f9; color: #64748b; font-weight: 600; }
.lde-tag--hcl { background: #eef0fe; color: #4f46e5; }
.lde-step__st { font-weight: 700; }
.lde-step.is-aktif .lde-step__st { color: #4338ca; }
.lde-step.is-lulus .lde-step__st { color: #059669; }
.lde-step.is-gugur .lde-step__st { color: #dc2626; }
@keyframes ldepulse { 0%,100% { box-shadow: 0 0 0 0 rgba(124,58,237,.4); } 50% { box-shadow: 0 0 0 5px rgba(124,58,237,0); } }

/* Panel */
.lde-main { min-width: 0; display: flex; flex-direction: column; gap: 1rem; }

/* Detail lamaran */
.lde-info { border: 1px solid rgba(11,16,51,.09); border-radius: 16px; background: #fff; padding: 1.1rem 1.2rem; }
.lde-info__head { font-size: 12px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: #4f46e5; display: flex; gap: .45rem; align-items: center; margin-bottom: .8rem; }
.lde-info__grid { display: grid; grid-template-columns: 1fr 1fr; gap: .75rem 1.2rem; margin: 0; }
.lde-info__grid dt { font-size: 11.5px; color: #94a3b8; margin-bottom: .1rem; }
.lde-info__grid dd { margin: 0; font-size: 13.5px; font-weight: 600; color: #1e293b; }

/* Tes pihak ke-3 */
.lde-tes { border: 1px solid rgba(79,70,229,.2); border-radius: 16px; background: #fff; overflow: hidden; }
.lde-tes__head { display: flex; gap: .8rem; padding: 1.1rem 1.2rem; border-bottom: 1px solid rgba(11,16,51,.07); background: linear-gradient(180deg, rgba(79,70,229,.06), transparent); }
.lde-tes__ic { flex: none; width: 2.4rem; height: 2.4rem; display: grid; place-items: center; border-radius: .8rem; background: linear-gradient(140deg,#4f46e5,#7c3aed); color: #fff; font-size: 1.15rem; }
.lde-tes__head h3 { margin: .1rem 0 0; font-size: 1.05rem; font-weight: 800; color: #0f172a; }
.lde-tes__head p { margin: .1rem 0 0; font-size: 12.5px; color: #64748b; }

.lde-cred { display: grid; grid-template-columns: 1fr 1fr; gap: .1rem; padding: 1rem 1.2rem 0; }
.lde-cred__item { padding: .7rem .2rem; }
.lde-cred__lbl { display: block; font-size: 11px; letter-spacing: .04em; text-transform: uppercase; color: #94a3b8; margin-bottom: .2rem; }
.lde-cred__val { font-size: 15px; font-weight: 700; color: #1e293b; }
.lde-mono { font-family: ui-monospace, 'SF Mono', Menlo, monospace; letter-spacing: .04em; color: #4f46e5; }

.lde-notice { display: flex; gap: .7rem; align-items: flex-start; margin: 1rem 1.2rem 0; padding: .85rem 1rem; border-radius: 12px; font-size: 13px; }
.lde-notice .bi { font-size: 1.15rem; margin-top: .05rem; }
.lde-notice b { display: block; margin-bottom: .1rem; }
.lde-notice p { margin: 0; color: #64748b; }
.lde-notice--wait { background: rgba(245,158,11,.08); border: 1px solid rgba(245,158,11,.25); }
.lde-notice--wait .bi { color: #d97706; }
.lde-notice--done { background: rgba(16,185,129,.08); border: 1px solid rgba(16,185,129,.25); }
.lde-notice--done .bi { color: #059669; }
.lde-notice--err { background: rgba(239,68,68,.07); border: 1px solid rgba(239,68,68,.22); }
.lde-notice--err .bi { color: #dc2626; }

.lde-btn-tes { margin: 1.1rem 1.2rem 1.3rem; width: calc(100% - 2.4rem); display: inline-flex; align-items: center; justify-content: center; gap: .5rem; padding: .8rem 1rem; border: 0; border-radius: 12px; font-size: 14px; font-weight: 700; cursor: pointer; color: #fff; background: linear-gradient(135deg,#4f46e5,#7c3aed); transition: transform .12s, box-shadow .12s; }
.lde-btn-tes:hover:not(:disabled) { transform: translateY(-1px); box-shadow: 0 8px 20px -8px rgba(79,70,229,.6); }
.lde-btn-tes:disabled { background: #e2e8f0; color: #94a3b8; cursor: not-allowed; }

.lde-form { border: 1px solid rgba(11, 16, 51, .09); border-radius: 16px; background: #fff; overflow: hidden; }
.lde-form__head { display: flex; gap: .8rem; padding: 1.1rem 1.2rem; border-bottom: 1px solid rgba(11,16,51,.07); background: linear-gradient(180deg, rgba(79,70,229,.05), transparent); }
.lde-form__ic { flex: none; width: 2.4rem; height: 2.4rem; display: grid; place-items: center; border-radius: .8rem; background: linear-gradient(140deg,#4f46e5,#7c3aed); color: #fff; font-size: 1.05rem; }
.lde-form__eyebrow { font-size: 10.5px; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; color: #7c3aed; }
.lde-form__head h3 { margin: .1rem 0 0; font-size: 1.05rem; font-weight: 800; color: #0f172a; }
.lde-form__head p { margin: .1rem 0 0; font-size: 12.5px; color: #64748b; }
.lde-form__body { padding: 1.3rem 1.2rem; }

.lde-wait, .lde-final { display: flex; gap: 1rem; align-items: flex-start; border-radius: 16px; padding: 1.4rem; }
.lde-wait { border: 1px dashed rgba(11,16,51,.16); background: rgba(248,250,252,.7); flex-direction: column; align-items: center; text-align: center; color: #64748b; }
.lde-wait .bi { font-size: 1.8rem; color: #7c3aed; }
.lde-wait h3 { margin: .3rem 0 .1rem; color: #0f172a; }
.lde-final .bi { font-size: 1.8rem; }
.lde-final h3 { margin: 0 0 .2rem; }
.lde-final p { margin: .15rem 0; font-size: 13px; }
.lde-final--gugur { border: 1px solid rgba(239,68,68,.25); background: rgba(239,68,68,.06); }
.lde-final--gugur .bi { color: #dc2626; }
.lde-final--lulus { border: 1px solid rgba(16,185,129,.3); background: rgba(16,185,129,.07); }
.lde-final--lulus .bi { color: #059669; }
.lde-final__note { font-style: italic; color: #64748b; }

.wca-toast.is-err { background: #b91c1c; }

@media (max-width: 820px) {
    .lde-grid { grid-template-columns: 1fr; }
    .lde-timeline { position: static; }
    .lde-info__grid { grid-template-columns: 1fr; }
    .lde-cred { grid-template-columns: 1fr; }
}
</style>
