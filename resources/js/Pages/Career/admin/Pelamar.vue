<!-- WEB CAREER — Worklist Pelamar. Kiri: daftar program (paginasi). Kanan: kanban alur program terpilih. -->
<template>
    <Head><title>Worklist Pelamar - EVO Career</title></Head>
    <div class="wca">
        <div class="wca-phead">
            <div>
                <h1>Worklist Pelamar</h1>
                <p>Pilih program di panel kiri, lalu pantau &amp; gerakkan kandidat pada alur seleksinya.</p>
            </div>
            <div class="plm-head__act">
                <button class="wca-btn wca-btn--primary" @click="goJadwal"><i class="bi bi-calendar2-check"></i> Jadwalkan Tes</button>
            </div>
        </div>

        <div class="plm-shell">
            <!-- ===== PANEL KIRI: PROGRAM ===== -->
            <aside class="plm-left">
                <div class="plm-search">
                    <i class="bi bi-search"></i>
                    <input v-model="q" type="text" placeholder="Cari program…" @input="cariDebounce" />
                </div>
                <div class="plm-tabs">
                    <button class="plm-tab" :class="{ 'is-active': jenis === '' }" @click="setJenis('')">Semua</button>
                    <button v-for="t in talent" :key="t.kode" class="plm-tab" :class="{ 'is-active': jenis === t.kode }" @click="setJenis(t.kode)">{{ t.label }}</button>
                </div>

                <div v-loading="loadingProg" class="plm-proglist">
                    <div v-if="!programs.length" class="plm-empty-sm"><i class="bi bi-folder2-open"></i> Tidak ada program berjalan.</div>
                    <button
                        v-for="p in programs"
                        :key="p.id"
                        class="plm-progcard"
                        :class="{ 'is-active': selectedId === p.id }"
                        :style="{ '--pw': p.warna || '#4f46e5' }"
                        :title="p.nama"
                        @click="pilihProgram(p)"
                    >
                        <div class="plm-progcard__top">
                            <span class="wca-badge" :class="katBadge(p.kategori)">{{ katLabel(p.kategori) }}</span>
                            <span class="plm-progcard__kode">{{ p.kode }}</span>
                        </div>
                        <div class="plm-progcard__nama">{{ p.nama }}</div>
                        <div class="plm-progcard__peny"><i class="bi bi-building"></i> {{ p.penyelenggara }}</div>
                        <div class="plm-progcard__alur"><i class="bi bi-diagram-3"></i> {{ p.alur || 'Alur belum diatur' }} · {{ p.jumlahTahap }} tahap</div>
                        <div class="plm-progcard__stats">
                            <span class="plm-stat" title="Pelamar aktif"><i class="bi bi-people-fill"></i> {{ p.aktif }} aktif</span>
                            <span class="plm-stat plm-stat--ok" title="Sudah diterima"><i class="bi bi-check-circle-fill"></i> {{ p.lolos }} lolos</span>
                            <span class="plm-stat plm-stat--mut" title="Total pelamar">{{ p.pelamar }} total</span>
                        </div>
                    </button>
                </div>

                <!-- PAGINATION -->
                <div v-if="totalPage > 1" class="plm-pager">
                    <button class="plm-pager__btn" :disabled="page <= 1" @click="gotoPage(page - 1)"><i class="bi bi-chevron-left"></i></button>
                    <button
                        v-for="n in totalPage"
                        :key="n"
                        class="plm-pager__num"
                        :class="{ 'is-active': n === page }"
                        @click="gotoPage(n)"
                    >{{ n }}</button>
                    <button class="plm-pager__btn" :disabled="page >= totalPage" @click="gotoPage(page + 1)"><i class="bi bi-chevron-right"></i></button>
                </div>
                <div class="plm-left__foot">{{ total }} program berjalan</div>
            </aside>

            <!-- ===== PANEL KANAN: KANBAN ALUR ===== -->
            <section class="plm-right">
                <div v-if="!selectedId" class="plm-pick">
                    <i class="bi bi-arrow-left-circle"></i>
                    <h3>Pilih program</h3>
                    <p>Klik salah satu program di panel kiri untuk melihat papan seleksi kandidatnya.</p>
                </div>

                <template v-else>
                    <div class="plm-right__head">
                        <div class="plm-right__title">
                            <div class="plm-right__eyebrow">{{ katLabel(detail.program?.kategori) }}</div>
                            <h2>{{ detail.program?.nama }}</h2>
                            <p><i class="bi bi-diagram-3"></i> {{ detail.program?.alur }}</p>
                        </div>
                        <button class="wca-btn wca-btn--ghost plm-refresh" :disabled="loadingDetail" @click="muatDetail(selectedId)"><i class="bi bi-arrow-clockwise"></i></button>
                    </div>

                    <div class="plm-statustabs">
                        <button class="plm-stab" :class="{ 'is-active': statusTab === 'AKTIF' }" @click="statusTab = 'AKTIF'">
                            <i class="bi bi-hourglass-split"></i> Berjalan <span class="plm-stab__n">{{ jmlAktif }}</span>
                        </button>
                        <button class="plm-stab" :class="{ 'is-active': statusTab === 'GUGUR' }" @click="statusTab = 'GUGUR'">
                            <i class="bi bi-x-circle"></i> Tidak Lolos <span class="plm-stab__n">{{ jmlGugur }}</span>
                        </button>
                    </div>

                    <div v-loading="loadingDetail" class="plm-board">
                        <div v-for="k in detail.kolom" :key="k.urutan" class="plm-col">
                            <div class="plm-col__head">
                                <span class="plm-col__title">
                                    <span class="plm-col__num">{{ k.urutan }}</span>{{ k.label }}
                                    <i v-if="k.provider === 'THIRD_PARTY'" class="bi bi-mortarboard-fill plm-col__hcl" title="Tes pihak ke-3"></i>
                                </span>
                                <span class="plm-col__n">{{ kartuKolom(k.urutan).length }}</span>
                            </div>
                            <div class="plm-col__body">
                                <div
                                    v-for="r in kartuKolom(k.urutan)"
                                    :key="r.id"
                                    class="plm-card"
                                    :class="cardKelas(r)"
                                    @click="bukaKandidat(r)"
                                >
                                    <div class="plm-card__top">
                                        <span class="plm-avatar" :style="{ background: warna(r.pelamar) }">{{ inisial(r.pelamar) }}</span>
                                        <div class="plm-card__id">
                                            <div class="plm-card__nama">{{ r.pelamar || '—' }}</div>
                                            <div class="plm-card__kode">{{ r.lamaranKode }}</div>
                                        </div>
                                    </div>
                                    <div class="plm-card__pos">{{ r.posisi }}</div>
                                    <div class="plm-card__foot">
                                        <span class="plm-badge" :class="'plm-badge--' + r.badge.tone">
                                            <i v-if="r.badge.tone === 'skor'" class="bi bi-star-fill"></i>{{ r.badge.teks }}
                                        </span>
                                    </div>
                                </div>
                                <div v-if="!kartuKolom(k.urutan).length" class="plm-col__empty">—</div>
                            </div>
                        </div>
                    </div>
                </template>
            </section>
        </div>

        <!-- Offcanvas PROFIL kandidat (panel besar) -->
        <transition name="plm-drawer">
            <div v-if="detailKandidat" class="plm-overlay" @click.self="tutupKandidat">
                <aside class="plm-panel">
                    <!-- Header sticky -->
                    <header class="plm-panel__head">
                        <span class="plm-avatar plm-avatar--lg" :style="{ background: warna(detailKandidat.pelamar) }">{{ inisial(detailKandidat.pelamar) }}</span>
                        <div class="plm-panel__idn">
                            <h3>{{ detailKandidat.pelamar }}</h3>
                            <p>{{ detailKandidat.posisi }} · {{ detailKandidat.lamaranKode }}</p>
                        </div>
                        <span class="wca-badge" :class="katBadge(detailKandidat.kategori)">{{ katLabel(detailKandidat.kategori) }}</span>
                        <button class="plm-panel__x" @click="tutupKandidat"><i class="bi bi-x-lg"></i></button>
                    </header>

                    <div class="plm-panel__body">
                        <!-- Ringkasan + keputusan -->
                        <section class="plm-block">
                            <dl class="plm-drawer__grid">
                                <div><dt>Tahap</dt><dd>{{ detailKandidat.tahap }}</dd></div>
                                <div><dt>Posisi Tahap</dt><dd>{{ detailKandidat.urutan }} / {{ detailKandidat.totalTahap }}</dd></div>
                                <div><dt>Status</dt><dd>{{ statusLabel(detailKandidat.statusLamaran) }}</dd></div>
                                <div v-if="detailKandidat.skor !== null && detailKandidat.skor !== undefined"><dt>Nilai Tes</dt><dd>{{ detailKandidat.skor }}</dd></div>
                            </dl>

                            <div v-if="detailKandidat.rekomendasi" class="plm-reko" :class="detailKandidat.rekomendasi === 'GUGUR' ? 'is-gugur' : 'is-lolos'">
                                <i class="bi" :class="detailKandidat.rekomendasi === 'GUGUR' ? 'bi-hand-thumbs-down-fill' : 'bi-hand-thumbs-up-fill'"></i>
                                Rekomendasi sistem: {{ detailKandidat.rekomendasi === 'GUGUR' ? 'Gugur' : 'Lolos' }}
                            </div>
                            <p v-if="detailKandidat.alasan" class="plm-drawer__alasan">{{ detailKandidat.alasan }}</p>

                            <div v-if="detailKandidat.butuhKeputusan" class="plm-drawer__act">
                                <button class="plm-btn plm-btn--gugur" :disabled="sibuk" @click="askPutus(detailKandidat, 'GUGUR')"><i class="bi bi-x-lg"></i> Gugurkan</button>
                                <button class="plm-btn plm-btn--lolos" :disabled="sibuk" @click="askPutus(detailKandidat, 'LULUS')"><i class="bi bi-check-lg"></i> Loloskan</button>
                            </div>
                            <div v-else-if="detailKandidat.nungguSistem" class="plm-note plm-note--wait">
                                <i class="bi bi-hourglass-split"></i> Tahap ini <b>digerakkan sistem</b> (tes pihak ke-3). Tidak bisa diputus manual — kandidat maju otomatis setelah nilai masuk.
                            </div>
                            <div v-else-if="detailKandidat.statusLamaran === 'GUGUR'" class="plm-note plm-note--gugur">
                                <i class="bi bi-x-circle-fill"></i> Tidak lolos di tahap <b>{{ detailKandidat.tahap }}</b>.
                            </div>
                            <div v-else-if="detailKandidat.statusLamaran === 'LULUS'" class="plm-note plm-note--ok">
                                <i class="bi bi-check-circle-fill"></i> Diterima — seluruh tahap selesai.
                            </div>
                        </section>

                        <!-- Formulir & berkas (di-loop) -->
                        <section v-loading="loadingProfil" class="plm-block">
                            <div class="plm-block__title"><i class="bi bi-folder2-open"></i> Berkas &amp; Biodata <span class="plm-block__n">{{ profil.formulir.length }} formulir</span></div>

                            <div v-if="!loadingProfil && !profil.formulir.length" class="plm-note"><i class="bi bi-inbox"></i> Kandidat belum mengisi formulir apa pun.</div>

                            <div v-for="f in profil.formulir" :key="f.no" class="plm-form">
                                <div class="plm-form__head">
                                    <span class="plm-form__no">{{ f.no }}</span>
                                    <div>
                                        <div class="plm-form__lbl">{{ f.label }}</div>
                                        <div class="plm-form__meta">Tahap {{ f.urutan }} · {{ f.sumber === 'PENDAFTARAN' ? 'Pendaftaran' : 'Tahap seleksi' }}<template v-if="f.waktuKirim"> · {{ f.waktuKirim.slice(0,16) }}</template></div>
                                    </div>
                                </div>

                                <div class="plm-fgrid">
                                    <div v-for="j in f.jawaban" :key="j.key" class="plm-fitem">
                                        <span class="plm-fitem__k">{{ j.label }}</span>
                                        <span class="plm-fitem__v">{{ j.nilai || '—' }}</span>
                                    </div>
                                </div>

                                <div v-if="f.berkas.length" class="plm-files">
                                    <a v-for="b in f.berkas" :key="b.field" :href="b.url" target="_blank" rel="noopener" class="plm-file" :class="{ 'is-pdf': b.isPdf }">
                                        <i class="bi" :class="b.isPdf ? 'bi-file-earmark-pdf' : b.isImage ? 'bi-file-earmark-image' : 'bi-file-earmark-text'"></i>
                                        <span class="plm-file__nm">{{ b.nama }}</span>
                                        <span class="plm-file__ext">{{ (b.ext || '').toUpperCase() }}</span>
                                    </a>
                                </div>
                            </div>
                        </section>
                    </div>
                </aside>
            </div>
        </transition>

        <ConfirmModal
            :show="konfirmShow"
            :title="putusHasil === 'LULUS' ? 'Loloskan Kandidat' : 'Gugurkan Kandidat'"
            :subtitle="putusTarget ? `${putusTarget.pelamar} — tahap ${putusTarget.tahap}` : ''"
            :danger="putusHasil === 'GUGUR'"
            :confirm-label="putusHasil === 'LULUS' ? 'Ya, Loloskan' : 'Ya, Gugurkan'"
            :busy="sibuk"
            @confirm="konfirmPutus"
            @cancel="konfirmShow = false"
        >
            <el-input v-model="putusCatatan" type="textarea" :rows="2" placeholder="Catatan (mis. sesuai rekomendasi sistem / alasan khusus)" />
        </ConfirmModal>

        <transition name="wca-toast"><div v-if="toast" class="wca-toast" :class="{ 'is-err': toastErr }"><i class="bi" :class="toastErr ? 'bi-exclamation-circle-fill' : 'bi-check-circle-fill'"></i> {{ toast }}</div></transition>
    </div>
</template>

<script>
import axios from 'axios';
import { Head, router } from '@inertiajs/vue3';
import ConfirmModal from '@career/ConfirmModal.vue';

const CFG = { headers: { Accept: 'application/json' } };

export default {
    components: { Head, ConfirmModal },
    props: {
        talent: { type: Array, default: () => [] },
        programAwal: { type: Object, default: () => ({ data: [], page: 1, totalPage: 1, total: 0 }) },
    },
    data() {
        return {
            programs: this.programAwal.data || [],
            page: this.programAwal.page || 1,
            totalPage: this.programAwal.totalPage || 1,
            total: this.programAwal.total || 0,
            q: '',
            jenis: '',
            loadingProg: false,
            selectedId: null,
            detail: { program: null, kolom: [], pelamar: [] },
            loadingDetail: false,
            statusTab: 'AKTIF',
            detailKandidat: null,
            profil: { lamaran: null, formulir: [] },
            loadingProfil: false,
            konfirmShow: false,
            putusTarget: null,
            putusHasil: '',
            putusCatatan: '',
            sibuk: false,
            toast: '',
            toastErr: false,
            tm: null,
            cariTm: null,
        };
    },
    computed: {
        pelamarTampil() {
            const aktif = this.statusTab !== 'GUGUR';
            return (this.detail.pelamar || []).filter((r) => (r.statusLamaran === 'GUGUR') !== aktif);
        },
        jmlAktif() { return (this.detail.pelamar || []).filter((r) => r.statusLamaran !== 'GUGUR').length; },
        jmlGugur() { return (this.detail.pelamar || []).filter((r) => r.statusLamaran === 'GUGUR').length; },
    },
    mounted() {
        // Pilih program pertama otomatis supaya panel kanan tidak kosong.
        if (this.programs.length) this.pilihProgram(this.programs[0]);
    },
    methods: {
        inisial(n) { return (n || '?').split(' ').slice(0, 2).map((s) => s[0]).join('').toUpperCase(); },
        warna(n) {
            const p = ['#6366f1', '#0ea5e9', '#10b981', '#f59e0b', '#ec4899', '#8b5cf6', '#14b8a6'];
            let h = 0; for (const c of (n || 'x')) h = (h + c.charCodeAt(0)) % p.length;
            return p[h];
        },
        katLabel(k) { return { REKRUTMEN: 'Rekrutmen', MT: 'Management Trainee', INTERNSHIP: 'Internship' }[k] || k || '—'; },
        katBadge(k) { return { MT: 'wca-b--gold', INTERNSHIP: 'wca-b--green', REKRUTMEN: 'wca-b--sky' }[k] || 'wca-b--slate'; },
        statusLabel(s) { return { BERJALAN: 'Berjalan', LULUS: 'Diterima', GUGUR: 'Tidak Lolos' }[s] || s; },
        cardKelas(r) {
            if (r.statusLamaran === 'GUGUR') return 'is-gugur';
            if (r.statusLamaran === 'LULUS') return 'is-lolos';
            if (r.butuhKeputusan) return 'is-perlu';
            return '';
        },
        kartuKolom(urutan) { return this.pelamarTampil.filter((r) => r.kolomUrutan === urutan); },
        // ── Panel kiri ──
        async muatProgram() {
            this.loadingProg = true;
            try {
                const res = await axios.get('/api/v1/karir/lamaran/worklist/program', {
                    params: { page: this.page, q: this.q || undefined, jenis: this.jenis || undefined },
                    ...CFG,
                });
                const r = res.data.result;
                this.programs = r.data;
                this.page = r.page;
                this.totalPage = r.totalPage;
                this.total = r.total;
            } catch (e) {
                this.notice('Gagal memuat program.', true);
            } finally {
                this.loadingProg = false;
            }
        },
        cariDebounce() {
            clearTimeout(this.cariTm);
            this.cariTm = setTimeout(() => { this.page = 1; this.muatProgram(); }, 400);
        },
        setJenis(j) { this.jenis = j; this.page = 1; this.muatProgram(); },
        gotoPage(n) { if (n < 1 || n > this.totalPage) return; this.page = n; this.muatProgram(); },
        // ── Panel kanan ──
        pilihProgram(p) { this.selectedId = p.id; this.statusTab = 'AKTIF'; this.muatDetail(p.id); },
        async muatDetail(id) {
            this.loadingDetail = true;
            try {
                const res = await axios.get(`/api/v1/karir/lamaran/worklist/program/${id}`, CFG);
                this.detail = res.data.result;
            } catch (e) {
                this.notice('Gagal memuat papan seleksi.', true);
            } finally {
                this.loadingDetail = false;
            }
        },
        goJadwal() { router.visit('/karir/penjadwalan'); },
        async bukaKandidat(r) {
            this.detailKandidat = r;
            this.profil = { lamaran: null, formulir: [] };
            this.loadingProfil = true;
            try {
                const res = await axios.get(`/api/v1/karir/lamaran/berkas/${r.id}`, CFG);
                this.profil = res.data.result || { lamaran: null, formulir: [] };
            } catch (e) {
                this.notice('Gagal memuat berkas kandidat.', true);
            } finally {
                this.loadingProfil = false;
            }
        },
        tutupKandidat() { this.detailKandidat = null; },
        askPutus(r, hasil) { this.putusTarget = r; this.putusHasil = hasil; this.putusCatatan = ''; this.konfirmShow = true; },
        async konfirmPutus() {
            if (this.sibuk || !this.putusTarget?.tahapId) return;
            this.sibuk = true;
            try {
                const res = await axios.patch(`/api/v1/karir/lamaran/tahap/${this.putusTarget.tahapId}/putus`, {
                    hasil: this.putusHasil, catatan: this.putusCatatan || null,
                }, CFG);
                this.notice(res.data?.message || 'Keputusan tersimpan.');
                this.konfirmShow = false;
                this.detailKandidat = null;
                this.putusTarget = null;
                this.muatDetail(this.selectedId);
                this.muatProgram();
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal memproses keputusan.', true);
            } finally {
                this.sibuk = false;
            }
        },
        notice(x, err = false) { this.toast = x; this.toastErr = err; if (this.tm) clearTimeout(this.tm); this.tm = setTimeout(() => (this.toast = ''), 3500); },
    },
};
</script>

<style scoped>
.plm-head__act { display: flex; gap: .6rem; }

.plm-shell { display: grid; grid-template-columns: 320px 1fr; gap: 1rem; align-items: start; }

/* ===== PANEL KIRI ===== */
.plm-left { background: #fff; border: 1px solid rgba(11,16,51,.08); border-radius: 16px; padding: .9rem; position: sticky; top: 1rem; display: flex; flex-direction: column; gap: .7rem; }
.plm-search { display: flex; align-items: center; gap: .5rem; background: #f4f6fb; border: 1px solid rgba(11,16,51,.08); border-radius: 10px; padding: .55rem .75rem; }
.plm-search .bi { color: #94a3b8; }
.plm-search input { border: 0; outline: 0; flex: 1; font-size: 13.5px; background: transparent; color: #1e293b; }
.plm-tabs { display: flex; flex-wrap: wrap; gap: .3rem; }
.plm-tab { border: 0; background: #eef1f8; padding: .35rem .7rem; border-radius: 8px; font-size: 12px; font-weight: 600; color: #64748b; cursor: pointer; }
.plm-tab.is-active { background: #4f46e5; color: #fff; }

.plm-proglist { display: flex; flex-direction: column; gap: .55rem; max-height: 60vh; overflow-y: auto; }
.plm-empty-sm { text-align: center; color: #94a3b8; padding: 1.5rem 0; font-size: 13px; }
.plm-progcard { text-align: left; border: 1px solid rgba(11,16,51,.1); border-left: 4px solid var(--pw); background: #fff; border-radius: 12px; padding: .7rem .8rem; cursor: pointer; transition: box-shadow .15s, border-color .15s; }
.plm-progcard:hover { box-shadow: 0 6px 16px -10px rgba(20,24,45,.3); }
.plm-progcard.is-active { border-color: var(--pw); box-shadow: 0 0 0 2px color-mix(in srgb, var(--pw) 22%, transparent); background: color-mix(in srgb, var(--pw) 5%, #fff); }
.plm-progcard__top { display: flex; align-items: center; justify-content: space-between; gap: .4rem; }
.plm-progcard__kode { font-family: ui-monospace, monospace; font-size: 10px; font-weight: 700; color: #94a3b8; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 45%; }
.plm-progcard__nama { font-weight: 800; font-size: 13.5px; color: #16223b; margin: .45rem 0 .3rem; line-height: 1.25; display: -webkit-box; -webkit-line-clamp: 2; line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.plm-progcard__peny, .plm-progcard__alur { font-size: 11px; color: #94a3b8; display: flex; align-items: center; gap: .3rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.plm-progcard__peny { margin-bottom: .15rem; }
.plm-progcard__alur { margin-top: .1rem; }
.plm-progcard__stats { display: flex; flex-wrap: wrap; gap: .3rem .6rem; margin-top: .55rem; padding-top: .5rem; border-top: 1px dashed rgba(11,16,51,.08); }
.plm-stat { font-size: 10.5px; font-weight: 700; color: #4338ca; display: inline-flex; align-items: center; gap: .25rem; }
.plm-stat--ok { color: #059669; }
.plm-stat--mut { color: #94a3b8; }

.plm-pager { display: flex; align-items: center; justify-content: center; gap: .25rem; flex-wrap: wrap; }
.plm-pager__btn, .plm-pager__num { border: 1px solid rgba(11,16,51,.1); background: #fff; min-width: 30px; height: 30px; border-radius: 8px; font-size: 12.5px; font-weight: 700; color: #475569; cursor: pointer; }
.plm-pager__num.is-active { background: #4f46e5; color: #fff; border-color: #4f46e5; }
.plm-pager__btn:disabled { opacity: .4; cursor: not-allowed; }
.plm-left__foot { text-align: center; font-size: 11.5px; color: #94a3b8; }

/* ===== PANEL KANAN ===== */
.plm-right { min-width: 0; }
.plm-pick { background: #fff; border: 1px dashed rgba(11,16,51,.16); border-radius: 16px; padding: 3rem 1rem; text-align: center; color: #64748b; }
.plm-pick .bi { font-size: 2.2rem; color: #a5b4fc; }
.plm-pick h3 { margin: .5rem 0 .2rem; color: #0f172a; }
.plm-right__head { display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; margin-bottom: 1rem; }
.plm-right__eyebrow { font-size: 11px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: #7c3aed; }
.plm-right__head h2 { margin: .15rem 0 .1rem; font-size: 1.2rem; color: #0f172a; }
.plm-right__head p { margin: 0; font-size: 12.5px; color: #64748b; display: flex; align-items: center; gap: .35rem; }
.plm-right__title { min-width: 0; flex: 1; }
.plm-right__title h2 { display: -webkit-box; -webkit-line-clamp: 2; line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.plm-right__title p { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.plm-right__title p i { flex: none; }

/* Tab Berjalan / Tidak Lolos */
.plm-statustabs { display: flex; gap: .5rem; margin-bottom: 1rem; }
.plm-stab { display: inline-flex; align-items: center; gap: .4rem; border: 1px solid rgba(11,16,51,.1); background: #fff; padding: .5rem .9rem; border-radius: 10px; font-size: 13px; font-weight: 700; color: #64748b; cursor: pointer; }
.plm-stab.is-active { background: #4f46e5; color: #fff; border-color: #4f46e5; }
.plm-stab__n { font-size: 11px; font-weight: 800; background: rgba(0,0,0,.08); border-radius: 999px; padding: .05rem .45rem; }
.plm-stab.is-active .plm-stab__n { background: rgba(255,255,255,.25); }

.plm-board { display: flex; gap: .9rem; overflow-x: auto; padding-bottom: 1rem; align-items: flex-start; }
.plm-col { flex: 0 0 260px; background: #f4f6fb; border: 1px solid rgba(11,16,51,.06); border-radius: 14px; padding: .7rem; }
.plm-col__head { display: flex; align-items: center; justify-content: space-between; gap: .5rem; padding: .1rem .3rem .65rem; }
.plm-col__title { display: flex; align-items: center; gap: .4rem; font-weight: 700; font-size: 12.5px; color: #334155; }
.plm-col__num { display: inline-grid; place-items: center; width: 18px; height: 18px; border-radius: 6px; background: #4f46e5; color: #fff; font-size: 10.5px; }
.plm-col__hcl { color: #4f46e5; }
.plm-col__n { font-size: 11.5px; font-weight: 800; color: #64748b; background: #fff; border-radius: 999px; padding: .05rem .5rem; }
.plm-col__body { display: flex; flex-direction: column; gap: .6rem; min-height: 50px; max-height: 62vh; overflow-y: auto; }
.plm-col__empty { text-align: center; color: #cbd5e1; padding: .6rem 0; }

.plm-card { background: #fff; border: 1px solid rgba(11,16,51,.08); border-radius: 12px; padding: .75rem; cursor: pointer; transition: box-shadow .15s, transform .1s; }
.plm-card:hover { box-shadow: 0 8px 20px -12px rgba(20,24,45,.28); transform: translateY(-1px); }
.plm-card.is-perlu { border-color: rgba(79,70,229,.35); }
.plm-card.is-gugur { border-color: rgba(239,68,68,.28); }
.plm-card.is-lolos { border-color: rgba(16,185,129,.32); }
.plm-card__top { display: flex; align-items: center; gap: .5rem; }
.plm-avatar { flex: none; width: 32px; height: 32px; border-radius: 9px; display: grid; place-items: center; color: #fff; font-size: 11.5px; font-weight: 800; }
.plm-avatar--lg { width: 52px; height: 52px; border-radius: 14px; font-size: 17px; }
.plm-card__id { min-width: 0; }
.plm-card__nama { font-weight: 800; font-size: 13px; color: #16223b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.plm-card__kode { font-family: ui-monospace, monospace; font-size: 10.5px; color: #94a3b8; }
.plm-card__pos { margin: .5rem 0 .5rem; font-size: 12px; font-weight: 700; color: #4f46e5; }
.plm-card__foot { display: flex; align-items: center; justify-content: flex-end; }
.plm-badge { display: inline-flex; align-items: center; gap: .25rem; font-size: 10px; font-weight: 800; letter-spacing: .03em; text-transform: uppercase; padding: .2rem .5rem; border-radius: 7px; }
.plm-badge--berjalan { background: #e0e7ff; color: #4338ca; }
.plm-badge--perlu { background: #fef3c7; color: #b45309; }
.plm-badge--nunggu { background: #f1f5f9; color: #64748b; }
.plm-badge--skor { background: #fff7ed; color: #c2410c; }
.plm-badge--skor .bi { color: #f59e0b; }
.plm-badge--gugur { background: #fee2e2; color: #b91c1c; }
.plm-badge--lolos { background: #d1fae5; color: #047857; }

/* Offcanvas PROFIL — panel besar (80% lebar), z-index di atas topbar(1020)+sidebar(1030) */
.plm-overlay { position: fixed; inset: 0; background: rgba(15,23,42,.5); display: flex; justify-content: flex-end; z-index: 2050; }
.plm-panel { width: min(820px, 80vw); height: 100%; background: #f7f8fc; box-shadow: -14px 0 44px -20px rgba(0,0,0,.45); display: flex; flex-direction: column; }
.plm-panel__head { display: flex; align-items: center; gap: .9rem; padding: 1.1rem 1.3rem; background: #fff; border-bottom: 1px solid rgba(11,16,51,.08); position: sticky; top: 0; z-index: 2; }
.plm-panel__idn { min-width: 0; flex: 1; }
.plm-panel__idn h3 { margin: 0; font-size: 1.15rem; color: #0f172a; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.plm-panel__idn p { margin: .1rem 0 0; font-size: 12.5px; color: #64748b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.plm-panel__x { flex: none; border: 0; background: #f1f5f9; width: 34px; height: 34px; border-radius: 9px; cursor: pointer; color: #475569; }
.plm-panel__body { flex: 1; overflow-y: auto; padding: 1.2rem 1.3rem; display: flex; flex-direction: column; gap: 1rem; }
.plm-block { background: #fff; border: 1px solid rgba(11,16,51,.07); border-radius: 14px; padding: 1.1rem 1.2rem; }
.plm-block__title { display: flex; align-items: center; gap: .5rem; font-weight: 800; font-size: .98rem; color: #0f172a; margin-bottom: .9rem; }
.plm-block__title .bi { color: #4f46e5; }
.plm-block__n { margin-left: auto; font-size: 11px; font-weight: 700; color: #64748b; background: #f1f5f9; border-radius: 999px; padding: .1rem .55rem; }
.plm-drawer__grid { display: grid; grid-template-columns: 1fr 1fr; gap: .8rem 1rem; margin: 0 0 1rem; }

/* Formulir (di-loop) */
.plm-form { border: 1px solid rgba(11,16,51,.09); border-radius: 12px; padding: .9rem 1rem; margin-top: .8rem; }
.plm-form:first-of-type { margin-top: 0; }
.plm-form__head { display: flex; gap: .6rem; align-items: center; margin-bottom: .8rem; }
.plm-form__no { flex: none; width: 26px; height: 26px; border-radius: 8px; display: grid; place-items: center; background: linear-gradient(135deg,#4f46e5,#7c3aed); color: #fff; font-size: 12px; font-weight: 800; }
.plm-form__lbl { font-weight: 800; font-size: 13.5px; color: #16223b; }
.plm-form__meta { font-size: 11px; color: #94a3b8; }
.plm-fgrid { display: grid; grid-template-columns: 1fr 1fr; gap: .55rem 1.2rem; }
.plm-fitem { display: flex; flex-direction: column; min-width: 0; }
.plm-fitem__k { font-size: 11px; color: #94a3b8; }
.plm-fitem__v { font-size: 13px; font-weight: 600; color: #1e293b; word-break: break-word; }
.plm-files { display: flex; flex-wrap: wrap; gap: .5rem; margin-top: .9rem; padding-top: .8rem; border-top: 1px dashed rgba(11,16,51,.1); }
.plm-file { display: inline-flex; align-items: center; gap: .45rem; max-width: 220px; padding: .5rem .7rem; border: 1px solid rgba(11,16,51,.12); border-radius: 10px; text-decoration: none; color: #334155; font-size: 12.5px; font-weight: 600; background: #f8fafc; }
.plm-file:hover { border-color: #4f46e5; }
.plm-file .bi { font-size: 1.05rem; color: #64748b; }
.plm-file.is-pdf .bi { color: #dc2626; }
.plm-file__nm { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.plm-file__ext { flex: none; font-size: 9.5px; font-weight: 800; color: #94a3b8; }
.plm-drawer__grid dt { font-size: 11px; color: #94a3b8; }
.plm-drawer__grid dd { margin: .05rem 0 0; font-size: 13.5px; font-weight: 700; color: #1e293b; }
.plm-drawer__alasan { font-size: 12.5px; color: #64748b; font-style: italic; margin: .5rem 0; }
.plm-w { width: 100%; margin: .6rem 0; justify-content: center; }
.plm-drawer__act { display: flex; gap: .6rem; margin-top: 1rem; }
.plm-drawer__act .plm-btn { flex: 1; justify-content: center; }
.plm-reko { display: inline-flex; align-items: center; gap: .4rem; font-size: 12.5px; font-weight: 700; padding: .35rem .7rem; border-radius: 8px; }
.plm-reko.is-lolos { color: #059669; background: rgba(16,185,129,.1); }
.plm-reko.is-gugur { color: #dc2626; background: rgba(239,68,68,.09); }
.plm-note { display: flex; gap: .5rem; align-items: flex-start; margin-top: 1rem; padding: .8rem 1rem; border-radius: 12px; font-size: 12.5px; background: #f1f5f9; color: #475569; }
.plm-note--wait { background: rgba(245,158,11,.08); color: #92400e; }
.plm-note--gugur { background: rgba(239,68,68,.07); color: #b91c1c; }
.plm-note--ok { background: rgba(16,185,129,.08); color: #047857; }
.plm-btn { display: inline-flex; align-items: center; gap: .35rem; border: 0; border-radius: 10px; padding: .6rem .9rem; font-size: 13.5px; font-weight: 800; cursor: pointer; }
.plm-btn--lolos { background: #059669; color: #fff; }
.plm-btn--gugur { background: #fee2e2; color: #b91c1c; }
.plm-btn:disabled { opacity: .5; cursor: not-allowed; }
.plm-jwb__row { display: flex; justify-content: space-between; gap: 1rem; padding: .5rem 0; border-bottom: 1px dashed rgba(11,16,51,.08); font-size: 13px; }
.plm-jwb__k { color: #64748b; }
.plm-jwb__v { font-weight: 600; color: #1e293b; text-align: right; }
.plm-muted { color: #94a3b8; text-align: center; }
.plm-drawer-enter-active, .plm-drawer-leave-active { transition: opacity .2s; }
.plm-drawer-enter-from, .plm-drawer-leave-to { opacity: 0; }
.wca-toast.is-err { background: #b91c1c; }

@media (max-width: 900px) {
    .plm-shell { grid-template-columns: 1fr; }
    .plm-left { position: static; }
    .plm-proglist { max-height: 320px; }
    .plm-col__body { max-height: none; }
}

@media (max-width: 640px) {
    .wca-phead { flex-direction: column; align-items: stretch; gap: .7rem; }
    .plm-head__act { width: 100%; }
    .plm-head__act .wca-btn { flex: 1; justify-content: center; }
    .plm-tabs { overflow-x: auto; flex-wrap: nowrap; padding-bottom: .2rem; }
    .plm-tab { white-space: nowrap; }
    .plm-right__head { flex-direction: row; }
    .plm-refresh { flex: none; }
    .plm-statustabs .plm-stab { flex: 1; justify-content: center; }
    /* Satu kolom kanban tampil hampir penuh, geser horizontal antar tahap */
    .plm-col { flex-basis: 82vw; }
    .plm-panel { width: 100vw; }
    .plm-panel__body { padding: 1rem; }
    .plm-drawer__grid { grid-template-columns: 1fr 1fr; }
    .plm-fgrid { grid-template-columns: 1fr; }
    .plm-file { max-width: 100%; flex: 1 1 100%; }
}
</style>
