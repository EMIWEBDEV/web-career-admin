<!-- WEB CAREER — Admin: Pembukaan Program (publikasi program ke landing + window).
     Tata letak, paginasi, dan gaya SENGAJA disamakan 100% dengan Program Kegiatan
     (kelas pkg-*) supaya dua halaman yang bersebelahan tidak terasa dari aplikasi
     berbeda. DATA dari DB via /api/v1/pembukaan. -->
<template>
    <Head><title>Pembukaan Program - Web Career</title></Head>
    <div class="wca">
        <div class="pkg-head">
            <div class="pkg-head__l">
                <div class="pkg-head__title">
                    <span class="pkg-head__ico"><i class="bi bi-megaphone"></i></span>
                    <h1>Pembukaan Program</h1>
                </div>
                <p>Publikasikan program ke landing publik beserta <b>window pendaftaran</b>-nya. Pembatasan kampus (bila ada) diatur lewat <b>Syarat</b> di Program Kegiatan.</p>
            </div>
            <button class="pkg-newbtn" @click="openCreate"><i class="bi bi-plus-lg"></i> Buka Program</button>
        </div>

        <!-- Stat cards -->
        <div class="pkg-stats">
            <div class="pkg-stat">
                <span class="pkg-stat__glow" style="background:radial-gradient(circle,rgba(99,102,241,.2),transparent 70%)"></span>
                <span class="pkg-stat__ico" style="background:rgba(99,102,241,.12);color:#6366f1"><i class="bi bi-megaphone-fill"></i></span>
                <div class="pkg-stat__num">{{ list.length }}</div>
                <div class="pkg-stat__label">Pembukaan</div>
            </div>
            <div class="pkg-stat">
                <span class="pkg-stat__glow" style="background:radial-gradient(circle,rgba(16,185,129,.2),transparent 70%)"></span>
                <span class="pkg-stat__ico" style="background:rgba(16,185,129,.12);color:#059669"><i class="bi bi-broadcast"></i></span>
                <div class="pkg-stat__num">{{ jmlTerbit }}</div>
                <div class="pkg-stat__label">Terbit</div>
            </div>
            <div class="pkg-stat">
                <span class="pkg-stat__glow" style="background:radial-gradient(circle,rgba(139,92,246,.2),transparent 70%)"></span>
                <span class="pkg-stat__ico" style="background:rgba(139,92,246,.12);color:#7c3aed"><i class="bi bi-infinity"></i></span>
                <div class="pkg-stat__num">{{ jmlEvergreen }}</div>
                <div class="pkg-stat__label">Selalu Terbuka</div>
            </div>
        </div>

        <!-- Toolbar: tab kategori (DARI DATABASE, disaring hak akses) -->
        <div class="pkg-toolbar">
            <div class="pkg-tabs">
                <button v-if="kategoriTab.length > 1" class="pkg-tab" :class="{ on: tab === '' }" @click="pilihTab('')">
                    <i class="bi bi-grid"></i> Semua <span class="pkg-tab__n">{{ totalSemua }}</span>
                </button>
                <button v-for="k in kategoriTab" :key="k.kode" class="pkg-tab" :class="{ on: tab === k.kode }" @click="pilihTab(k.kode)">
                    <i class="bi" :class="katIkon(k.kode)"></i> {{ k.nama }} <span class="pkg-tab__n">{{ k.jumlah }}</span>
                </button>
            </div>
        </div>

        <!-- FILTER PANEL — semua saringan dikirim ke backend. -->
        <div v-if="sheetOpen" class="pkg-sheetbg" @click="sheetOpen = false"></div>
        <div class="pkg-filter" :class="{ 'is-open': sheetOpen }">
            <div class="pkg-filter__head">
                <span class="pkg-filter__title"><i class="bi bi-funnel"></i> Filter Panel</span>
                <div class="pkg-filter__act">
                    <button v-if="adaFilter" class="pkg-filter__reset" type="button" @click="resetFilter"><i class="bi bi-arrow-counterclockwise"></i> Reset</button>
                    <button class="pkg-filter__close" type="button" aria-label="Tutup" @click="sheetOpen = false"><i class="bi bi-x-lg"></i></button>
                </div>
            </div>
            <div class="pkg-filter__grid">
                <div>
                    <label class="wca-field-lbl">Cari</label>
                    <el-input v-model="filters.q" placeholder="Kode / program / batch" clearable @input="cariDebounce">
                        <template #prefix><i class="bi bi-search"></i></template>
                    </el-input>
                </div>
                <div>
                    <label class="wca-field-lbl">Status Publikasi</label>
                    <el-select v-model="filters.status" placeholder="Semua status" clearable style="width:100%" @change="load">
                        <el-option label="Terbit" value="TERBIT" />
                        <el-option label="Draft" value="DRAFT" />
                    </el-select>
                </div>
                <div>
                    <label class="wca-field-lbl">Tanggal Dibuat</label>
                    <el-date-picker
                        v-model="filters.rentang" type="daterange" value-format="YYYY-MM-DD"
                        start-placeholder="Mulai" end-placeholder="Akhir" range-separator="—"
                        style="width:100%" @change="load"
                    />
                </div>
                <div class="pkg-filter__count"><strong>{{ list.length }}</strong> pembukaan</div>
            </div>
        </div>
        <button class="pkg-fab" type="button" aria-label="Filter" @click="sheetOpen = true">
            <i class="bi bi-funnel-fill"></i>
            <span v-if="jumlahFilter" class="pkg-fab__badge">{{ jumlahFilter }}</span>
        </button>

        <!-- Daftar pembukaan -->
        <div v-loading="loading" class="pkg-list">
            <div v-for="b in paged" :key="b.id" class="pkg-card" :class="{ open: open === b.id }">
                <div class="pkg-row">
                    <button type="button" class="pkg-chev" :class="{ open: open === b.id }" title="Buka detail" @click="open = (open === b.id ? null : b.id)"><i class="bi bi-chevron-right"></i></button>
                    <div class="pkg-row__main">
                        <button type="button" class="pkg-row__titlebtn" @click="open = (open === b.id ? null : b.id)">
                            <span class="pkg-dot" :style="{ background: b.statusPublish === 'TERBIT' ? '#10b981' : '#94a3b8' }"></span>
                            <span class="pkg-row__title">{{ b.programNama || '—' }}</span>
                        </button>
                        <div class="pkg-row__meta">
                            <span class="pkg-code">{{ b.kode }}</span>
                            <span class="pkg-sep"></span>
                            <span class="pkg-mi"><i class="bi bi-calendar-range"></i> {{ windowLabel(b) }}</span>
                            <template v-if="b.batchNama">
                                <span class="pkg-sep"></span>
                                <span class="pkg-mi"><i class="bi bi-collection"></i> {{ b.batchNama }}</span>
                            </template>
                        </div>
                        <div class="pkg-pills">
                            <span class="pkg-pill" :class="katPill(b.kategori)"><i class="bi" :class="katIkon(b.kategori)"></i> {{ katLabel(b.kategori) }}</span>
                            <span class="pkg-pill" :class="b.masaBerlaku === 'EVERGREEN' ? 'pkg-pill--green' : 'pkg-pill--struct'">
                                <i class="bi" :class="b.masaBerlaku === 'EVERGREEN' ? 'bi-infinity' : 'bi-hourglass-split'"></i>
                                {{ b.masaBerlaku === 'EVERGREEN' ? 'Selalu Terbuka' : 'Berbatas' }}
                            </span>
                            <span class="pkg-pill" :class="b.statusPublish === 'TERBIT' ? 'pkg-pill--green' : 'pkg-pill--slate'"><span class="pkg-pill__dot"></span> {{ b.statusPublish === 'TERBIT' ? 'Terbit' : 'Draft' }}</span>
                        </div>
                    </div>
                    <div class="pkg-row__act" @click.stop>
                        <el-switch :model-value="b.statusPublish === 'TERBIT'" @change="(v) => setPublish(b, v)" />
                        <button class="pkg-ibtn" title="Ubah" @click="openEdit(b)"><i class="bi bi-pencil"></i></button>
                        <button class="pkg-ibtn pkg-ibtn--danger" title="Hapus" @click="askRemove(b)"><i class="bi bi-trash"></i></button>
                    </div>
                </div>

                <!-- creator strip -->
                <div class="pkg-creator">
                    <span class="pkg-creator__av" style="background:#6366f1">{{ initials(b.createdBy) }}</span>
                    <span class="pkg-creator__name">{{ b.createdBy || 'Sistem' }}</span>
                    <span class="pkg-creator__at"><i class="bi bi-clock"></i> {{ b.createdAt || '—' }}</span>
                </div>

                <!-- expanded detail -->
                <div v-if="open === b.id" class="pkg-detail">
                    <div class="pkg-dhead"><i class="bi bi-calendar-range"></i> WINDOW PENDAFTARAN</div>
                    <div class="pkg-batchset">
                        <span class="pkg-batch"><i class="bi bi-door-open"></i> Buka · {{ fmt(b.buka) }}</span>
                        <span class="pkg-batch">
                            <i class="bi" :class="b.masaBerlaku === 'EVERGREEN' ? 'bi-infinity' : 'bi-door-closed'"></i>
                            Tutup · {{ b.masaBerlaku === 'EVERGREEN' ? 'tanpa batas' : fmt(b.tutup) }}
                        </span>
                    </div>

                    <div class="pkg-dhead pkg-dhead--indigo"><i class="bi bi-diagram-3-fill"></i> PROGRAM</div>
                    <div class="pkg-batchset">
                        <span class="pkg-batch"><i class="bi bi-hash"></i> {{ b.program || '—' }}</span>
                        <span class="pkg-batch"><i class="bi" :class="katIkon(b.kategori)"></i> {{ katLabel(b.kategori) }}</span>
                        <span v-if="b.batchNama" class="pkg-batch"><i class="bi bi-collection"></i> {{ b.batchNama }}</span>
                    </div>
                </div>
            </div>

            <div v-if="!loading && !list.length" class="pkg-empty">
                <i class="bi bi-megaphone"></i> {{ adaFilter || tab ? 'Tidak ada pembukaan yang cocok dengan filter.' : 'Belum ada pembukaan.' }}
            </div>
        </div>

        <!-- Paginasi — sama persis dengan Program Kegiatan -->
        <div v-if="totalPages > 1" class="pkg-pager">
            <span class="pkg-pager__info">Menampilkan <b>{{ pageFrom }}–{{ pageTo }}</b> dari <b>{{ list.length }}</b> pembukaan</span>
            <div class="pkg-pager__nav">
                <button type="button" class="pkg-pager__btn" :disabled="page <= 1" @click="page = Math.max(1, page - 1)"><i class="bi bi-chevron-left"></i></button>
                <button v-for="n in totalPages" :key="n" type="button" class="pkg-pager__btn" :class="{ on: n === page }" @click="page = n">{{ n }}</button>
                <button type="button" class="pkg-pager__btn" :disabled="page >= totalPages" @click="page = Math.min(totalPages, page + 1)"><i class="bi bi-chevron-right"></i></button>
            </div>
        </div>

        <!-- Modal buka/ubah -->
        <AdminModal :busy="saving" :show="show" lg :title="editingId ? 'Ubah Pembukaan' : 'Buka Program'" subtitle="Publikasikan program ke landing + window pendaftaran" icon="bi-megaphone" :save-label="editingId ? 'Perbarui' : 'Buka'" @close="show = false" @save="save">
            <div class="wca-fsection">
                <div class="wca-fsection__label"><i class="bi bi-megaphone"></i> Detail Pembukaan</div>
                <div class="wca-form">
                    <div><label class="wca-field-lbl">Program</label>
                        <div class="pbk-prog">
                            <!-- Kotak cari hanya muncul saat program banyak (>6) — kalau sedikit tak perlu. -->
                            <el-input v-if="programs.length > 6" v-model="programCari" placeholder="Cari program…" clearable size="default" style="margin-bottom:.5rem">
                                <template #prefix><i class="bi bi-search"></i></template>
                            </el-input>
                            <div class="pbk-proglist">
                                <div v-if="programsLoading" class="pbk-progload"><span class="pbk-spin"></span> Memuat program…</div>
                                <button v-for="p in programsTampil" v-else :key="p.kode" type="button" class="pbk-progcard" :class="{ 'is-on': form.program === p.kode }" @click="form.program = p.kode">
                                    <div class="pbk-progcard__head">
                                        <span class="pbk-progcard__ico" :class="katPill(p.kategori)"><i class="bi" :class="katIkon(p.kategori)"></i></span>
                                        <div class="pbk-progcard__id">
                                            <strong>{{ p.nama }}</strong>
                                            <small>{{ katLabel(p.kategori) }} · {{ p.penyelenggara }}</small>
                                        </div>
                                        <i v-if="form.program === p.kode" class="bi bi-check-circle-fill pbk-progcard__chk"></i>
                                    </div>
                                    <div class="pbk-progcard__meta">
                                        <span v-if="p.alur"><i class="bi bi-signpost-split"></i> {{ p.alur }}</span>
                                        <span v-if="p.jumlahPosisi"><i class="bi bi-briefcase"></i> {{ p.jumlahPosisi }} posisi</span>
                                        <span v-if="p.tglMulai" class="pbk-progcard__date"><i class="bi bi-calendar3"></i> {{ fmtTgl(p.tglMulai) }}<template v-if="p.tglSelesai"> – {{ fmtTgl(p.tglSelesai) }}</template></span>
                                        <span v-else-if="p.jadwal"><i class="bi bi-calendar3"></i> {{ p.jadwal }}</span>
                                    </div>
                                </button>
                                <div v-if="!programsLoading && !programsTampil.length" class="pbk-progempty"><i class="bi bi-inbox"></i> Tidak ada program berjalan yang cocok.</div>
                            </div>
                        </div>
                    </div>
                    <div><label class="wca-field-lbl">Masa Berlaku</label>
                        <el-select filterable v-model="form.masaBerlaku" placeholder="Pilih" style="width:100%" @change="onMasa">
                            <el-option label="Berbatas (ada tanggal tutup)" value="BERBATAS" />
                            <el-option label="Selalu Terbuka (tanpa tanggal tutup)" value="EVERGREEN" />
                        </el-select>
                    </div>
                    <div class="wca-frow">
                        <div><label class="wca-field-lbl">Tanggal &amp; Jam Buka</label><el-date-picker v-model="form.buka" type="datetime" value-format="YYYY-MM-DD HH:mm:ss" format="DD MMM YYYY HH:mm" placeholder="Pilih tanggal &amp; jam" style="width:100%" /></div>
                        <div><label class="wca-field-lbl">Tanggal &amp; Jam Tutup</label><el-date-picker v-model="form.tutup" type="datetime" value-format="YYYY-MM-DD HH:mm:ss" format="DD MMM YYYY HH:mm" placeholder="Pilih tanggal &amp; jam" style="width:100%" :default-time="akhirHari" :disabled="form.masaBerlaku === 'EVERGREEN'" /></div>
                    </div>
                    <p class="pbk-note"><i class="bi bi-info-circle"></i> Begitu disimpan, program <b>langsung terbit</b> ke landing. Ingin menundanya? Jadikan draft lewat tombol status di daftar.</p>
                </div>
            </div>
        </AdminModal>

        <ConfirmModal :show="delShow" title="Hapus Pembukaan" :busy="deleting" confirm-label="Ya, Hapus" note="Pembukaan program ini akan dihapus permanen." @cancel="delShow = false" @confirm="confirmDelete">
            Yakin ingin menghapus pembukaan <strong>{{ delTarget?.programNama }}</strong>?
        </ConfirmModal>

        <transition name="wca-toast"><div v-if="toast" class="wca-toast"><i class="bi bi-check-circle-fill"></i> {{ toast }}</div></transition>
    </div>
</template>

<script>
import axios from 'axios';
import { Head } from '@inertiajs/vue3';
import AdminModal from '@career/AdminModal.vue';
import ConfirmModal from '@career/ConfirmModal.vue';
import RefSelect from '@career/RefSelect.vue';

const API = '/api/v1/pembukaan';
const CFG = { headers: { Accept: 'application/json' } };
const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

export default {
    components: { Head, AdminModal, ConfirmModal, RefSelect },
    data() {
        return {
            list: [],
            loading: false,
            open: null,
            // Tab kategori dari DB (disaring hak akses) + Filter Panel + paginasi —
            // menyalin pola Program Kegiatan.
            tab: '',
            kategoriTab: [],
            totalSemua: 0,
            filters: { q: '', status: null, rentang: null },
            sheetOpen: false,
            cariTimer: null,
            page: 1,
            perPage: 6,
            show: false,
            editingId: null,
            saving: false,
            // Picker program (kartu detail) — dimuat dari endpoint programs.
            programs: [],
            programsLoading: false,
            programCari: '',
            // Default jam saat memilih Tanggal Tutup = 23:59:59 (akhir hari), bukan 00:00.
            akhirHari: new Date(2000, 0, 1, 23, 59, 59),
            form: { program: '', masaBerlaku: 'BERBATAS', buka: null, tutup: null, statusPublish: 'TERBIT' },
            delShow: false,
            delTarget: null,
            deleting: false,
            toast: '',
            tm: null,
        };
    },
    computed: {
        jmlTerbit() { return this.list.filter((b) => b.statusPublish === 'TERBIT').length; },
        jmlEvergreen() { return this.list.filter((b) => b.masaBerlaku === 'EVERGREEN').length; },
        programsTampil() {
            const s = this.programCari.trim().toLowerCase();
            if (!s) return this.programs;
            return this.programs.filter((p) => `${p.nama} ${p.kategori} ${p.alur || ''} ${p.penyelenggara || ''}`.toLowerCase().includes(s));
        },
        adaFilter() {
            return !!(this.filters.q || this.filters.status || (this.filters.rentang && this.filters.rentang.length));
        },
        jumlahFilter() {
            return [this.filters.q, this.filters.status, this.filters.rentang?.length ? 1 : null].filter(Boolean).length;
        },
        totalPages() { return Math.max(1, Math.ceil(this.list.length / this.perPage)); },
        paged() { const s = (this.page - 1) * this.perPage; return this.list.slice(s, s + this.perPage); },
        pageFrom() { return this.list.length ? (this.page - 1) * this.perPage + 1 : 0; },
        pageTo() { return Math.min(this.page * this.perPage, this.list.length); },
    },
    mounted() {
        this.load();
    },
    methods: {
        katLabel(k) { return { REKRUTMEN: 'Rekrutmen', MT: 'Management Trainee', INTERNSHIP: 'Internship' }[k] || k || '—'; },
        katIkon(k) { return { REKRUTMEN: 'bi-briefcase', MT: 'bi-mortarboard', INTERNSHIP: 'bi-backpack' }[k] || 'bi-diagram-3'; },
        katPill(k) { return { MT: 'pkg-pill--gold', INTERNSHIP: 'pkg-pill--green', REKRUTMEN: 'pkg-pill--sky' }[k] || 'pkg-pill--slate'; },
        initials(name) {
            if (!name) return 'SY';
            const p = String(name).trim().split(/\s+/);
            return ((p[0]?.[0] || '') + (p[1]?.[0] || p[0]?.[1] || '')).toUpperCase() || 'SY';
        },
        fmt(iso) {
            if (!iso) return '—';
            const d = new Date(String(iso).replace(' ', 'T'));
            if (Number.isNaN(d.getTime())) return iso;
            const jam = String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0');
            return `${d.getDate()} ${MONTHS[d.getMonth()]} ${d.getFullYear()} ${jam}`;
        },
        windowLabel(b) { return b.masaBerlaku === 'EVERGREEN' ? this.fmt(b.buka) + ' — tanpa batas' : `${this.fmt(b.buka)} – ${this.fmt(b.tutup)}`; },

        async load() {
            this.loading = true;
            try {
                const params = {
                    q: this.filters.q || undefined,
                    kategori: this.tab || undefined,
                    status: this.filters.status || undefined,
                    dari: this.filters.rentang?.[0] || undefined,
                    sampai: this.filters.rentang?.[1] || undefined,
                };
                const r = (await axios.get(API, { ...CFG, params })).data.result || {};
                this.list = r.data || [];
                this.kategoriTab = r.kategori || [];
                this.totalSemua = r.total || 0;
                if (this.page > this.totalPages) this.page = 1;
            } catch (e) {
                this.notice('Gagal memuat data pembukaan.');
            } finally {
                this.loading = false;
            }
        },
        pilihTab(kode) { this.tab = kode; this.page = 1; this.load(); },
        cariDebounce() { if (this.cariTimer) clearTimeout(this.cariTimer); this.cariTimer = setTimeout(() => { this.page = 1; this.load(); }, 400); },
        resetFilter() { this.filters = { q: '', status: null, rentang: null }; this.page = 1; this.load(); },

        blankForm() { return { program: '', masaBerlaku: 'BERBATAS', buka: null, tutup: null, statusPublish: 'TERBIT' }; },
        onMasa() { if (this.form.masaBerlaku === 'EVERGREEN') this.form.tutup = null; },
        /** Format tanggal (tanpa jam) untuk kartu program. */
        fmtTgl(iso) {
            if (!iso) return '';
            const d = new Date(String(iso).replace(' ', 'T'));
            if (Number.isNaN(d.getTime())) return iso;
            return `${d.getDate()} ${MONTHS[d.getMonth()]} ${d.getFullYear()}`;
        },
        async loadPrograms() {
            // Selalu ambil fresh saat modal dibuka → loader tampil + urutan terbaru ikut program baru.
            this.programsLoading = true;
            this.programs = [];
            try {
                this.programs = (await axios.get(`${API}/programs`, CFG)).data.result || [];
            } catch (e) {
                this.programs = [];
            } finally {
                this.programsLoading = false;
            }
        },
        openCreate() {
            this.editingId = null;
            this.form = this.blankForm();
            this.programCari = '';
            this.loadPrograms();
            this.show = true;
        },
        openEdit(b) {
            this.editingId = b.id;
            this.form = {
                program: b.program || '',
                masaBerlaku: b.masaBerlaku || 'BERBATAS',
                buka: b.buka || null,
                tutup: b.tutup || null,
                // Field disembunyikan; nilai lama dipertahankan (dikirim saat update).
                statusPublish: b.statusPublish || 'TERBIT',
            };
            this.programCari = '';
            this.loadPrograms();
            this.show = true;
        },
        async save() {
            if (this.saving) return;
            if (!this.form.program) return this.notice('Program wajib dipilih.');
            if (!this.form.masaBerlaku) return this.notice('Masa berlaku wajib dipilih.');
            this.saving = true;
            const payload = {
                program: this.form.program,
                masaBerlaku: this.form.masaBerlaku,
                buka: this.form.buka,
                tutup: this.form.masaBerlaku === 'EVERGREEN' ? null : this.form.tutup,
                statusPublish: this.form.statusPublish,
            };
            try {
                if (this.editingId) {
                    await axios.put(`${API}/${this.editingId}`, payload, CFG);
                    this.notice('Pembukaan diperbarui.');
                } else {
                    await axios.post(API, payload, CFG);
                    this.notice('Program dibuka.');
                }
                this.show = false;
                await this.load();
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal menyimpan.');
            } finally {
                this.saving = false;
            }
        },
        async setPublish(b, v) {
            const prev = b.statusPublish;
            b.statusPublish = v ? 'TERBIT' : 'DRAFT';
            try {
                await axios.patch(`${API}/${b.id}/toggle`, { aktif: v }, CFG);
                this.notice(`Pembukaan "${b.programNama}" ${v ? 'diterbitkan' : 'dijadikan draft'}.`);
            } catch (e) {
                b.statusPublish = prev;
                this.notice('Gagal mengubah status.');
            }
        },
        askRemove(b) { this.delTarget = b; this.delShow = true; },
        async confirmDelete() {
            if (this.deleting || !this.delTarget) return;
            const b = this.delTarget;
            this.deleting = true;
            try {
                await axios.delete(`${API}/${b.id}`, CFG);
                this.notice('Pembukaan dihapus.');
                this.delShow = false;
                this.delTarget = null;
                await this.load();
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal menghapus.');
            } finally {
                this.deleting = false;
            }
        },
        notice(x) { this.toast = x; if (this.tm) clearTimeout(this.tm); this.tm = setTimeout(() => (this.toast = ''), 3000); },
    },
};
</script>
<style scoped>
/* ═══════════ DESAIN "PROGRAM KEGIATAN" (1:1) ═══════════ */
/* Page header */
.pkg-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 18px; flex-wrap: wrap; margin-bottom: 22px; }
.pkg-head__l { min-width: 0; }
.pkg-head__title { display: flex; align-items: center; gap: 10px; }
.pkg-head__ico { width: 38px; height: 38px; border-radius: 12px; background: linear-gradient(135deg, #8b5cf6, #6366f1); color: #fff; display: flex; align-items: center; justify-content: center; flex: 0 0 auto; font-size: 1.05rem; box-shadow: 0 10px 24px rgba(99, 102, 241, .3); }
.pkg-head h1 { margin: 0; font-size: 27px; font-weight: 800; color: #0f172a; letter-spacing: -.025em; }
.pkg-head__l p { margin: 9px 0 0; font-size: 14px; color: #64748b; line-height: 1.6; max-width: 620px; text-wrap: pretty; }
.pkg-head__l p b { color: #475569; }
.pkg-newbtn { appearance: none; cursor: pointer; font-family: inherit; font-size: 14px; font-weight: 800; color: #fff; padding: 13px 22px; border-radius: 14px; border: none; background: linear-gradient(135deg, #8b5cf6, #6366f1); box-shadow: 0 14px 30px rgba(99, 102, 241, .34); display: inline-flex; align-items: center; gap: 9px; flex: 0 0 auto; transition: transform .16s; }
.pkg-newbtn:hover { transform: translateY(-2px); }

/* Stat cards */
.pkg-stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px; }
.pkg-stat { position: relative; overflow: hidden; background: rgba(255, 255, 255, .9); border: 1px solid rgba(226, 232, 240, .9); border-radius: 20px; padding: 20px 22px; box-shadow: 0 10px 30px rgba(15, 23, 42, .05); }
.pkg-stat__glow { position: absolute; right: -24px; top: -24px; width: 96px; height: 96px; border-radius: 50%; pointer-events: none; }
.pkg-stat__ico { position: relative; width: 42px; height: 42px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.15rem; }
.pkg-stat__num { position: relative; font-size: 34px; font-weight: 800; color: #0f172a; letter-spacing: -.03em; margin-top: 14px; line-height: 1; }
.pkg-stat__label { position: relative; font-size: 13px; font-weight: 700; color: #64748b; margin-top: 5px; }

/* Toolbar */
.pkg-toolbar { display: flex; align-items: center; justify-content: space-between; gap: 14px; flex-wrap: wrap; margin: 26px 0 16px; }

/* ── FILTER PANEL — card di desktop, bottom-sheet lewat FAB di mobile ── */
.pkg-filter { background: #fff; border: 1px solid rgba(15,23,42,.08); border-radius: 16px; padding: .9rem 1rem 1rem; margin-bottom: 1rem; box-shadow: 0 8px 24px rgba(15,23,42,.04); }
.pkg-filter__head { display: flex; align-items: center; justify-content: space-between; margin-bottom: .65rem; }
.pkg-filter__title { display: inline-flex; align-items: center; gap: .45rem; font-size: 12px; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; color: #4338ca; }
.pkg-filter__act { display: inline-flex; gap: .4rem; }
.pkg-filter__reset { display: inline-flex; align-items: center; gap: .3rem; border: 1px solid rgba(79,70,229,.25); background: #eef2ff; color: #4338ca; font-size: 11.5px; font-weight: 700; border-radius: 9px; padding: 4px 10px; cursor: pointer; }
.pkg-filter__reset:hover { background: #e0e7ff; }
.pkg-filter__close { display: none; border: none; background: transparent; color: #64748b; font-size: 15px; cursor: pointer; }
.pkg-filter__grid { display: grid; grid-template-columns: minmax(220px,1.6fr) 1fr 1.4fr auto; gap: .7rem; align-items: end; }
.pkg-filter__count { font-size: 12px; color: #64748b; padding-bottom: .5rem; white-space: nowrap; }
.pkg-sheetbg { display: none; }
.pkg-fab { display: none; position: fixed; right: 18px; bottom: 20px; z-index: 70; width: 52px; height: 52px; border-radius: 50%; border: none; background: linear-gradient(135deg,#8b5cf6,#6366f1); color: #fff; font-size: 19px; cursor: pointer; box-shadow: 0 12px 28px rgba(99,102,241,.45); }
.pkg-fab__badge { position: absolute; top: -4px; right: -4px; min-width: 19px; height: 19px; border-radius: 999px; background: #ef4444; color: #fff; font-size: 10.5px; font-weight: 800; display: grid; place-items: center; padding: 0 5px; border: 2px solid #fff; }

@media (max-width: 960px) { .pkg-filter__grid { grid-template-columns: 1fr 1fr; } }
@media (max-width: 640px) {
    .pkg-filter { display: none; }
    .pkg-filter.is-open { display: block; position: fixed; left: 0; right: 0; bottom: 0; z-index: 80; margin: 0; border-radius: 18px 18px 0 0; max-height: 78vh; overflow-y: auto; box-shadow: 0 -18px 40px rgba(15,23,42,.25); }
    .pkg-filter__grid { grid-template-columns: 1fr; }
    .pkg-filter__close { display: inline-flex; }
    .pkg-fab { display: grid; place-items: center; }
    .pkg-sheetbg { display: block; position: fixed; inset: 0; z-index: 75; background: rgba(15,23,42,.45); }
}
.pkg-tabs { display: flex; gap: 8px; flex-wrap: wrap; }
.pkg-tab { appearance: none; cursor: pointer; font-family: inherit; font-size: 13px; font-weight: 700; padding: 10px 15px; border-radius: 12px; border: 1px solid #e6e9f3; background: rgba(255, 255, 255, .9); color: #64748b; transition: all .16s; display: inline-flex; align-items: center; gap: 7px; }
.pkg-tab:hover { border-color: #c7cdf0; color: #4f46e5; }
.pkg-tab.on { border-color: transparent; background: linear-gradient(135deg, #8b5cf6, #6366f1); color: #fff; box-shadow: 0 10px 24px rgba(99, 102, 241, .28); }
.pkg-tab__n { font-size: 11px; font-weight: 800; padding: 1px 7px; border-radius: 7px; background: #eef0f7; color: #94a3b8; }
.pkg-tab.on .pkg-tab__n { background: rgba(255, 255, 255, .24); color: #fff; }
.pkg-search { position: relative; flex: 1; min-width: 200px; max-width: 320px; display: flex; align-items: center; }
.pkg-search .bi-search { position: absolute; left: 14px; color: #94a3b8; font-size: 14px; }
.pkg-search input { width: 100%; padding: 11px 34px 11px 40px; border-radius: 13px; border: 1px solid #e6e9f3; background: rgba(255, 255, 255, .9); font-family: inherit; font-size: 13.5px; color: #334155; outline: none; transition: all .18s; }
.pkg-search input:focus { border-color: #a5b4fc; background: #fff; box-shadow: 0 0 0 4px rgba(99, 102, 241, .12); }
.pkg-search__x { position: absolute; right: 9px; appearance: none; border: none; background: #eef0f7; width: 22px; height: 22px; border-radius: 7px; color: #64748b; cursor: pointer; font-size: 10px; display: flex; align-items: center; justify-content: center; }

/* Program list */
.pkg-list { display: flex; flex-direction: column; gap: 14px; min-height: 60px; }
.pkg-card { background: rgba(255, 255, 255, .92); border: 1px solid rgba(226, 232, 240, .9); border-radius: 18px; box-shadow: 0 10px 30px rgba(15, 23, 42, .05); transition: border-color .16s, box-shadow .16s; }
.pkg-card.open { border-color: rgba(99, 102, 241, .3); box-shadow: 0 18px 44px rgba(79, 70, 229, .12); }
.pkg-row { display: flex; align-items: flex-start; gap: 14px; padding: 18px 20px; }
.pkg-chev { appearance: none; cursor: pointer; flex: 0 0 auto; width: 34px; height: 34px; border-radius: 10px; border: 1px solid #e6e9f3; background: #fff; color: #94a3b8; display: flex; align-items: center; justify-content: center; transition: all .18s; }
.pkg-chev:hover { color: #4f46e5; border-color: #c7cdf0; }
.pkg-chev i { transition: transform .2s; }
.pkg-chev.open { color: #4f46e5; border-color: #c7cdf0; background: rgba(99, 102, 241, .08); }
.pkg-chev.open i { transform: rotate(90deg); }
.pkg-row__main { flex: 1; min-width: 0; }
.pkg-row__titlebtn { appearance: none; border: none; background: transparent; cursor: pointer; padding: 0; text-align: left; display: flex; align-items: center; gap: 9px; width: 100%; }
.pkg-dot { width: 9px; height: 9px; border-radius: 50%; flex: 0 0 auto; }
.pkg-row__title { font-size: 16.5px; font-weight: 800; color: #0f172a; letter-spacing: -.01em; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.pkg-row__meta { display: flex; align-items: center; gap: 8px; margin-top: 6px; flex-wrap: wrap; font-size: 12px; color: #8792a6; }
.pkg-code { font-family: 'JetBrains Mono', ui-monospace, monospace; font-weight: 700; color: #94a3b8; }
.pkg-sep { width: 3px; height: 3px; border-radius: 50%; background: #cbd2e0; }
.pkg-mi { display: inline-flex; align-items: center; gap: 5px; }
.pkg-mi i { color: #a2a9ba; }
.pkg-flow { min-width: 0; }
.pkg-pills { display: flex; align-items: center; gap: 8px; margin-top: 11px; flex-wrap: wrap; }
.pkg-pill { display: inline-flex; align-items: center; gap: 5px; font-size: 11px; font-weight: 700; border-radius: 8px; padding: 4px 10px; }
.pkg-pill--struct { color: #4f46e5; background: rgba(99, 102, 241, .1); }
.pkg-pill--gold { color: #b45309; background: rgba(245, 158, 11, .14); }
.pkg-pill--sky { color: #0369a1; background: rgba(14, 165, 233, .12); }
.pkg-pill--green { color: #059669; background: rgba(16, 185, 129, .12); }
.pkg-pill--amber { color: #b45309; background: rgba(245, 158, 11, .14); }
.pkg-pill--slate { color: #64748b; background: #eef0f7; }
.pkg-pill__dot { width: 6px; height: 6px; border-radius: 50%; background: currentColor; }
.pkg-pill--sm { font-size: 10.5px; padding: 3px 9px; }
.pkg-row__act { display: inline-flex; align-items: center; gap: 8px; flex: 0 0 auto; }
.pkg-ibtn { appearance: none; border: 1px solid #e6e9f3; background: #fff; width: 38px; height: 38px; border-radius: 11px; display: flex; align-items: center; justify-content: center; cursor: pointer; color: #64748b; transition: all .16s; flex: 0 0 auto; }
.pkg-ibtn:hover { color: #4f46e5; border-color: #c7cdf0; }
.pkg-ibtn--danger { border-color: #f4d0d0; color: #dc2626; }
.pkg-ibtn--danger:hover { background: #fef2f2; border-color: #f4d0d0; color: #dc2626; }

/* creator strip */
.pkg-creator { display: flex; align-items: center; gap: 9px; padding: 0 20px 16px 54px; }
.pkg-creator__av { width: 26px; height: 26px; border-radius: 8px; color: #fff; font-size: 10px; font-weight: 800; display: flex; align-items: center; justify-content: center; flex: 0 0 auto; }
.pkg-creator__name { font-size: 12px; font-weight: 700; color: #64748b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.pkg-creator__at { display: inline-flex; align-items: center; gap: 5px; font-size: 11.5px; color: #a2a9ba; flex: 0 0 auto; }

/* expanded detail */
.pkg-detail { border-top: 1px solid #eef0f7; padding: 20px; background: linear-gradient(180deg, #fbfbfe, #fff); animation: pkgIn .3s cubic-bezier(.22, 1, .36, 1) both; }
@keyframes pkgIn { from { opacity: 0; transform: translateY(-6px); } to { opacity: 1; transform: none; } }
.pkg-dhead { display: inline-flex; align-items: center; gap: 8px; font-size: 12px; font-weight: 800; letter-spacing: .1em; color: #64748b; margin-bottom: 12px; }
.pkg-dhead--amber { color: #b45309; }
.pkg-dhead--amber i { color: #d97706; }
.pkg-dhead--indigo { color: #4338ca; }
.pkg-dhead--indigo i { color: #6366f1; }
.pkg-rules { display: flex; flex-direction: column; gap: 10px; }
.pkg-rule { position: relative; border: 1px solid #f0e6d0; border-radius: 14px; background: linear-gradient(135deg, #fffdf7, #fff8ec); padding: 14px 16px 14px 18px; overflow: hidden; }
.pkg-rule__bar { position: absolute; left: 0; top: 0; bottom: 0; width: 4px; background: linear-gradient(180deg, #fbbf24, #f59e0b); }
.pkg-rule__top { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.pkg-rule__name { font-size: 13.5px; font-weight: 800; color: #1e293b; }
.pkg-rule__act { display: inline-flex; align-items: center; gap: 5px; font-size: 10.5px; font-weight: 800; border-radius: 7px; padding: 3px 9px; }
.pkg-rule__act.is-gugur { color: #dc2626; background: rgba(239, 68, 68, .1); }
.pkg-rule__act.is-mark { color: #4f46e5; background: rgba(99, 102, 241, .1); }
.pkg-rule__match { font-size: 10px; font-weight: 800; letter-spacing: .06em; color: #7c74b0; background: rgba(139, 92, 246, .12); border-radius: 7px; padding: 3px 9px; }
.pkg-rule__match--amber { color: #b45309; background: rgba(245, 158, 11, .14); }
.pkg-rule__match--off { color: #64748b; background: #eef0f7; }
.pkg-conds { display: flex; flex-wrap: wrap; gap: 7px; margin-top: 11px; }
.pkg-cond { display: inline-flex; align-items: center; gap: 6px; font-size: 11.5px; font-weight: 700; color: #475569; background: #fff; border: 1px solid #eae1cb; border-radius: 9px; padding: 5px 11px; }
.pkg-cond i { color: #8b5cf6; }
.pkg-mono { font-family: 'JetBrains Mono', ui-monospace, monospace; }
.pkg-batchset { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 4px; }
.pkg-batch { display: inline-flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 700; color: #475569; background: #f1f5f9; border-radius: 9px; padding: 6px 11px; }
.pkg-batch i { color: #8b5cf6; }
.pkg-poshead { display: flex; align-items: center; justify-content: space-between; gap: 10px; flex-wrap: wrap; margin: 22px 0 12px; }
.pkg-totalq { display: inline-flex; align-items: center; gap: 7px; font-size: 12px; font-weight: 800; color: #4f46e5; background: rgba(99, 102, 241, .1); border: 1px solid rgba(99, 102, 241, .2); border-radius: 999px; padding: 6px 13px; }

/* posisi table (grid, flat) */
.pkg-tbl { width: 100%; overflow-x: auto; }
.pkg-tbl__head, .pkg-tbl__row { display: grid; grid-template-columns: 2.4fr 1.3fr 1.6fr 1.2fr .7fr .9fr; gap: 12px; align-items: center; min-width: 640px; }
.pkg-tbl__head { padding: 0 2px 12px; font-size: 10.5px; font-weight: 800; letter-spacing: .08em; color: #a2a9ba; border-bottom: 1px solid #eef0f7; }
.pkg-tbl__row { padding: 15px 2px; border-bottom: 1px solid #f4f5fb; transition: background .14s; }
.pkg-tbl__row:hover { background: #f7f8fc; }
.pkg-c { text-align: center; justify-self: center; }
.pkg-pos { display: flex; align-items: center; gap: 11px; min-width: 0; }
.pkg-pos__dot { width: 8px; height: 8px; border-radius: 50%; flex: 0 0 auto; background: linear-gradient(135deg, #8b5cf6, #6366f1); }
.pkg-pos__txt { min-width: 0; }
.pkg-pos__title { display: block; font-size: 13.5px; font-weight: 800; color: #1e293b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.pkg-pos__lvl { display: block; font-size: 11px; color: #94a3b8; margin-top: 1px; }
.pkg-mpp { font-family: 'JetBrains Mono', ui-monospace, monospace; font-size: 11px; font-weight: 700; color: #7c74b0; background: rgba(139, 92, 246, .1); border-radius: 6px; padding: 3px 8px; }
.pkg-manual { color: #b45309; font-size: 12px; }
.pkg-td { font-size: 12.5px; color: #475569; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.pkg-td--loc { display: inline-flex; align-items: center; gap: 5px; }
.pkg-td--loc i { color: #8b5cf6; flex: 0 0 auto; }
.pkg-q { font-size: 15px; font-weight: 800; color: #0f172a; }
.pkg-tbl__empty { padding: 15px 2px; color: #94a3b8; font-size: 13px; }
.pkg-empty { padding: 40px; text-align: center; color: #a2a9ba; font-size: 13.5px; background: rgba(255, 255, 255, .7); border: 1px dashed #d9def0; border-radius: 18px; }
.pkg-empty i { font-size: 1.6rem; display: block; margin-bottom: .4rem; color: #c4b5fd; }

/* pagination */
.pkg-pager { display: flex; align-items: center; justify-content: space-between; gap: 14px; flex-wrap: wrap; margin-top: 20px; }
.pkg-pager__info { font-size: 12.5px; color: #8792a6; }
.pkg-pager__info b { color: #475569; }
.pkg-pager__nav { display: flex; align-items: center; gap: 6px; }
.pkg-pager__btn { appearance: none; cursor: pointer; min-width: 38px; height: 38px; padding: 0 10px; border-radius: 11px; border: 1px solid #e6e9f3; background: #fff; color: #64748b; font-family: inherit; font-size: 13.5px; font-weight: 700; display: inline-flex; align-items: center; justify-content: center; transition: all .16s; }
.pkg-pager__btn:hover:not(:disabled):not(.on) { border-color: #a5b4fc; color: #4f46e5; }
.pkg-pager__btn.on { background: linear-gradient(135deg, #8b5cf6, #6366f1); border-color: transparent; color: #fff; box-shadow: 0 10px 22px rgba(99, 102, 241, .3); }
.pkg-pager__btn:disabled { opacity: .4; cursor: not-allowed; }

@media (max-width: 767.98px) {
    .pkg-stats { grid-template-columns: 1fr; }
    .pkg-row { flex-wrap: wrap; }
    .pkg-row__act { width: 100%; justify-content: flex-end; }
}

.pgk-head-act { display: inline-flex; align-items: center; gap: .4rem; margin-left: auto; }
/* Langkah 1: kategori dulu — baris tunggal + ajakan sebelum kategori dipilih */
.wca-frow--single { grid-template-columns: 1fr !important; }
.pgk-waitkat { display: flex; align-items: center; gap: 10px; margin-top: .9rem; padding: 14px 16px; border: 1px dashed #d9def0; border-radius: 14px; background: #fbfbfe; color: #64748b; font-size: 13px; }
.pgk-waitkat i { font-size: 1.1rem; color: #8b5cf6; flex: 0 0 auto; }
.pgk-waitkat strong { color: #4f46e5; }
/* Warna label: baris swatch klik-langsung (mengisi ruang, bukan kotak mungil) */
.pgk-swatches { display: flex; align-items: center; flex-wrap: wrap; gap: 9px; min-height: 40px; }
.pgk-swatch { appearance: none; border: none; cursor: pointer; width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #fff; font-size: .8rem; box-shadow: inset 0 0 0 1px rgba(15, 23, 42, .08); transition: transform .14s, box-shadow .14s; }
.pgk-swatch:hover { transform: scale(1.12); }
.pgk-swatch.on { box-shadow: 0 0 0 2px #fff, 0 0 0 4px #6366f1, 0 6px 14px rgba(99, 102, 241, .3); transform: scale(1.08); }
/* ── Pemilih MPP model KARTU (ala Monitoring MPP) ── */
.pgk-mpppick { margin-bottom: .9rem; }
.pgk-mpppick__head { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; margin-bottom: .6rem; }
.pgk-mppsearch { position: relative; display: flex; align-items: center; flex: 1; min-width: 220px; max-width: 340px; }
.pgk-mppsearch .bi-search { position: absolute; left: 12px; color: #94a3b8; font-size: .8rem; }
.pgk-mppsearch input { width: 100%; padding: 9px 32px 9px 34px; border-radius: 11px; border: 1px solid #e6e9f3; background: #fff; font: inherit; font-size: .8rem; color: #334155; outline: none; transition: all .18s; }
.pgk-mppsearch input:focus { border-color: #a5b4fc; box-shadow: 0 0 0 4px rgba(99, 102, 241, .12); }
.pgk-mppsearch button { position: absolute; right: 8px; appearance: none; border: none; background: #eef0f7; width: 20px; height: 20px; border-radius: 6px; color: #64748b; cursor: pointer; font-size: .6rem; display: flex; align-items: center; justify-content: center; }
/* Grid 3 kolom + scrollbar sendiri; kartu meniru persis MppCard Monitoring MPP. */
.pgk-mppgrid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; max-height: 350px; overflow-y: auto; padding: 3px 6px 6px 3px; }
@media (max-width: 900px) { .pgk-mppgrid { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 620px) { .pgk-mppgrid { grid-template-columns: 1fr; } }
.pgk-mppcard { position: relative; overflow: hidden; display: flex; flex-direction: column; gap: .55rem; cursor: pointer; background: #fff; border: 1px solid #e6e9f3; border-radius: 1.15rem; padding: 1rem 1.05rem; box-shadow: 0 6px 20px rgba(15, 23, 42, .04); transition: transform .2s cubic-bezier(.22, 1, .36, 1), box-shadow .2s ease, border-color .2s ease; }
.pgk-mppcard::after { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px; background: linear-gradient(90deg, #6366f1, #8b5cf6, #6366f1); opacity: 0; transition: opacity .25s ease; }
.pgk-mppcard:hover::after, .pgk-mppcard.on::after { opacity: 1; }
.pgk-mppcard:hover { transform: translateY(-4px); border-color: #c7d2fe; box-shadow: 0 18px 36px rgba(79, 70, 229, .14), 0 6px 12px rgba(15, 23, 42, .05); }
.pgk-mppcard.on { border-color: #6366f1; background: linear-gradient(135deg, #fbfaff, #f5f4ff); box-shadow: 0 12px 28px rgba(99, 102, 241, .18); }
.pgk-mppcard__head { display: flex; align-items: flex-start; gap: 8px; }
.pgk-mppcard__check { margin-top: 2px; width: 20px; height: 20px; border-radius: 7px; border: 1.5px solid #d9def0; background: #fff; color: transparent; font-size: .7rem; display: flex; align-items: center; justify-content: center; flex: 0 0 auto; transition: all .16s; }
.pgk-mppcard.on .pgk-mppcard__check { border-color: transparent; background: linear-gradient(135deg, #8b5cf6, #6366f1); color: #fff; }
.pgk-mppcard__title { flex: 1; min-width: 0; margin: 0; font-size: .95rem; font-weight: 900; line-height: 1.25; color: #0f172a; }
.pgk-mppcard__info { appearance: none; border: none; background: transparent; cursor: pointer; color: #94a3b8; font-size: .85rem; padding: 2px; flex: 0 0 auto; transition: color .16s; }
.pgk-mppcard__info:hover { color: #4f46e5; }
.pgk-mppcard__badges { display: flex; flex-wrap: wrap; gap: .35rem; }
.pgk-bdg { display: inline-flex; align-items: center; gap: .35rem; font-size: .68rem; font-weight: 800; border-radius: .55rem; padding: .24rem .55rem; }
.pgk-bdg--indigo { color: #4338ca; background: rgba(99, 102, 241, .12); }
.pgk-bdg--sky { color: #0369a1; background: rgba(14, 165, 233, .12); }
.pgk-mppcard__no { display: inline-flex; align-items: center; gap: .15rem; font-size: .74rem; font-weight: 800; color: #94a3b8; font-family: 'JetBrains Mono', monospace; }
.pgk-mppcard__tags { display: flex; flex-wrap: wrap; gap: .4rem; }
.mppt { display: inline-flex; align-items: center; gap: .35rem; font-size: .68rem; font-weight: 800; white-space: nowrap; padding: .26rem .55rem; border-radius: .6rem; }
.mppt--emp { background: linear-gradient(135deg, rgba(99, 102, 241, .12), rgba(139, 92, 246, .08)); color: #4338ca; border: 1px solid rgba(99, 102, 241, .18); }
.mppt--wp { background: linear-gradient(135deg, rgba(16, 185, 129, .1), rgba(6, 182, 212, .06)); color: #0f766e; border: 1px solid rgba(16, 185, 129, .18); }
.mppt--exp { background: linear-gradient(135deg, rgba(245, 158, 11, .1), rgba(251, 191, 36, .06)); color: #b45309; border: 1px solid rgba(245, 158, 11, .2); }
.mppt__dot { width: .42rem; height: .42rem; border-radius: 50%; flex: none; }
.mppt__dot--emp { background: #6366f1; box-shadow: 0 0 0 3px rgba(99, 102, 241, .2); }
.mppt__dot--wp { background: #10b981; box-shadow: 0 0 0 3px rgba(16, 185, 129, .2); }
.mppt__dot--exp { background: #f59e0b; box-shadow: 0 0 0 3px rgba(245, 158, 11, .2); }
.pgk-mppcard__meta { display: flex; flex-wrap: wrap; gap: .35rem .9rem; padding-top: .45rem; border-top: 1px dashed #e6e9f3; margin-top: auto; }
.pgk-mppcard__meta span { display: inline-flex; align-items: center; gap: .35rem; font-size: .72rem; font-weight: 700; color: #475569; }
.pgk-mppcard__meta i { color: #6366f1; }
/* Panel "Sudah Dipilih" — scrollbar sendiri */
.pgk-selpanel { margin-top: 1rem; border: 1px solid #e6e9f3; border-radius: 14px; background: #fbfbfe; overflow: hidden; }
.pgk-selpanel__hd { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; padding: 10px 14px; border-bottom: 1px solid #eef0f7; background: #fff; font-size: .8rem; font-weight: 800; color: #1e293b; }
.pgk-selpanel__hd > i { color: #059669; }
.pgk-selpanel__tot { display: inline-flex; align-items: center; gap: 6px; font-size: .68rem; font-weight: 800; color: #4f46e5; background: rgba(99, 102, 241, .1); border-radius: 999px; padding: 4px 11px; }
.pgk-selpanel__hint { margin-left: auto; font-size: .68rem; font-weight: 600; color: #94a3b8; }
.pgk-selpanel__body { max-height: 280px; overflow-y: auto; padding: 12px; }
/* ── Panel checklist Nilai (operator daftar) ── */
.pgk-multichk { border: 1px solid #e6e9f3; border-radius: 12px; background: #fff; overflow: hidden; }
.pgk-multichk__search { position: relative; display: flex; align-items: center; border-bottom: 1px solid #eef0f7; }
.pgk-multichk__search .bi-search { position: absolute; left: 11px; color: #94a3b8; font-size: .72rem; }
.pgk-multichk__search input { width: 100%; border: none; outline: none; padding: 8px 10px 8px 30px; font: inherit; font-size: .76rem; color: #334155; background: transparent; }
.pgk-multichk__list { max-height: 170px; overflow-y: auto; padding: 4px; }
.pgk-multichk__item { display: flex; align-items: center; gap: 8px; padding: 6px 9px; border-radius: 8px; cursor: pointer; font-size: .78rem; color: #475569; transition: background .12s; }
.pgk-multichk__item:hover { background: #f7f8fc; }
.pgk-multichk__item.on { background: rgba(99, 102, 241, .07); color: #4338ca; font-weight: 700; }
.pgk-multichk__item input { accent-color: #6366f1; }
.pgk-multichk__item span { flex: 1; min-width: 0; }
.pgk-multichk__item .bi-check-lg { color: #6366f1; font-size: .72rem; }
.pgk-multichk__empty { padding: 12px; text-align: center; color: #94a3b8; font-size: .74rem; }
.pgk-multichk__foot { display: flex; align-items: center; justify-content: space-between; gap: 8px; padding: 7px 11px; border-top: 1px solid #eef0f7; background: #fbfbfe; font-size: .72rem; color: #64748b; }
.pgk-multichk__foot b { color: #4f46e5; }
.pgk-multichk__foot button { appearance: none; border: none; background: transparent; cursor: pointer; color: #dc2626; font: inherit; font-size: .72rem; font-weight: 700; display: inline-flex; align-items: center; gap: 4px; }
.pgk-lblinfo { color: #a5b4fc; font-size: .74rem; cursor: help; margin-left: 3px; }
/* ── Baris posisi terpilih (elegan: chip info, bukan input mati) ── */
.pgk-posrow { position: relative; display: flex; align-items: center; gap: 16px; background: #fff; border: 1px solid #e6e9f3; border-radius: 14px; padding: 13px 14px 13px 20px; overflow: hidden; transition: box-shadow .16s, border-color .16s; }
.pgk-posrow:hover { border-color: #c7cdf0; box-shadow: 0 8px 20px rgba(15, 23, 42, .06); }
.pgk-posrow__accent { position: absolute; left: 0; top: 0; bottom: 0; width: 4px; background: linear-gradient(180deg, #8b5cf6, #6366f1); }
.pgk-posrow__main { flex: 1; min-width: 0; }
.pgk-posrow__title { display: flex; align-items: center; gap: 9px; flex-wrap: wrap; }
.pgk-posrow__title strong { font-size: .9rem; font-weight: 800; color: #1e293b; }
.pgk-posrow__meta { display: flex; flex-wrap: wrap; gap: 5px 14px; margin-top: 6px; }
.pgk-posrow__meta span { display: inline-flex; align-items: center; gap: 5px; font-size: .72rem; color: #64748b; }
.pgk-posrow__meta i { color: #8b5cf6; }
.pgk-posrow__kuota, .pgk-posrow__status { display: flex; flex-direction: column; gap: 4px; flex: 0 0 auto; }
.pgk-posrow__kuota label, .pgk-posrow__status label { font-size: .66rem; font-weight: 800; letter-spacing: .04em; text-transform: uppercase; color: #94a3b8; }
.pgk-posrow__kuota label small { font-weight: 600; text-transform: none; letter-spacing: 0; color: #b9c0d4; }
.pgk-posrow__del { appearance: none; border: 1px solid #f4d0d0; background: #fff; width: 36px; height: 36px; border-radius: 10px; display: flex; align-items: center; justify-content: center; cursor: pointer; color: #dc2626; flex: 0 0 auto; transition: background .16s; }
.pgk-posrow__del:hover { background: #fef2f2; }
@media (max-width: 640px) { .pgk-posrow { flex-wrap: wrap; } }
/* ── Pratinjau (langkah 4) ── */
.pgk-prev { display: flex; flex-direction: column; gap: 12px; }
/* Hero ringkasan */
.pgk-prev__hero { position: relative; overflow: hidden; display: flex; background: linear-gradient(135deg, #fbfaff, #f3f2ff 60%, #eef2ff); border: 1px solid rgba(99, 102, 241, .16); border-radius: 16px; }
.pgk-prev__heroBar { width: 6px; flex: 0 0 auto; }
.pgk-prev__heroMain { flex: 1; min-width: 0; padding: 16px 18px; }
.pgk-prev__heroTop { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.pgk-prev__heroTop h4 { margin: 0; font-size: 1.05rem; font-weight: 900; color: #1e1b4b; letter-spacing: -.01em; min-width: 0; overflow-wrap: anywhere; }
.pgk-prev__heroChips { display: flex; flex-wrap: wrap; gap: 7px 16px; margin-top: 10px; }
.pgk-prev__heroChips span { display: inline-flex; align-items: center; gap: 6px; font-size: .74rem; font-weight: 600; color: #64748b; min-width: 0; }
.pgk-prev__heroChips i { color: #8b5cf6; }
.pgk-prev__heroChips code { font-family: 'JetBrains Mono', monospace; font-size: .68rem; font-weight: 700; color: #4338ca; background: rgba(99, 102, 241, .09); border-radius: 6px; padding: 2px 7px; overflow-wrap: anywhere; }
/* Stat mini */
.pgk-prev__stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; }
@media (max-width: 640px) { .pgk-prev__stats { grid-template-columns: repeat(2, 1fr); } }
.pgk-prev__stats > div { display: flex; align-items: center; gap: 10px; background: #fff; border: 1px solid #e6e9f3; border-radius: 13px; padding: 11px 13px; }
.pgk-prev__sico { width: 34px; height: 34px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: .9rem; flex: 0 0 auto; }
.pgk-prev__stats b { font-size: 1.15rem; font-weight: 900; color: #0f172a; }
.pgk-prev__stats > div > span:last-child { font-size: .7rem; font-weight: 700; color: #94a3b8; }
.pgk-prev__card { background: #fff; border: 1px solid #e6e9f3; border-radius: 14px; padding: 15px 17px; }
.pgk-prev__hd { display: flex; align-items: center; gap: 8px; font-size: .82rem; font-weight: 800; color: #1e293b; margin-bottom: 11px; flex-wrap: wrap; }
.pgk-prev__hd i { color: #6366f1; }
.pgk-prev__list { display: flex; flex-direction: column; }
.pgk-prev__item { display: flex; align-items: center; gap: 9px; padding: 8px 2px; border-bottom: 1px solid #f4f5fb; }
.pgk-prev__item:last-child { border-bottom: none; }
.pgk-prev__dot { width: 7px; height: 7px; border-radius: 50%; background: linear-gradient(135deg, #8b5cf6, #6366f1); flex: 0 0 auto; }
.pgk-prev__nm { font-size: .82rem; font-weight: 700; color: #334155; min-width: 0; }
.pgk-prev__kt { margin-left: auto; font-size: .72rem; font-weight: 800; color: #4f46e5; background: rgba(99, 102, 241, .08); border-radius: 8px; padding: 3px 10px; flex: 0 0 auto; }
.pgk-prev__tag { display: inline-flex; align-items: center; gap: 5px; font-size: .64rem; font-weight: 800; border-radius: 7px; padding: 3px 9px; }
.pgk-prev__tag--ind { color: #4f46e5; background: rgba(99, 102, 241, .1); }
.pgk-prev__tag--vio { color: #7c3aed; background: rgba(139, 92, 246, .12); }
.pgk-prev__tag--red { color: #dc2626; background: rgba(239, 68, 68, .1); }
.pgk-prev__syarat { border-top: 1px solid #f4f5fb; padding-top: 10px; margin-top: 10px; }
.pgk-prev__syarat:first-of-type { border-top: none; padding-top: 0; margin-top: 0; }
.pgk-prev__srow { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.pgk-prev__srow > b { font-size: .82rem; color: #1e293b; }
.pgk-prev__conds { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 8px; }
.pgk-prev__cond { display: inline-flex; align-items: center; gap: 6px; font-size: .72rem; color: #475569; background: #f7f8fc; border: 1px solid #eef0f7; border-radius: 8px; padding: 5px 10px; }
.pgk-prev__cond i { color: #8b5cf6; }
.pgk-prev__cond b { color: #4338ca; }
.pgk-prev__none { font-size: .76rem; color: #94a3b8; }
.pgk-mppline { display: flex; align-items: center; gap: 9px; flex-wrap: wrap; }
.pgk-mppline__dot { width: 9px; height: 9px; border-radius: 50%; background: linear-gradient(135deg, #8b5cf6, #6366f1); flex: 0 0 auto; }
.pgk-mppline strong { font-size: .9rem; color: #1e293b; font-weight: 800; }
/* Nilai rentang (ANTARA) */
.pgk-antara { display: flex; align-items: center; gap: 7px; }
.pgk-antara .el-input-number { flex: 1; width: auto; }
.pgk-antara__sep { color: #94a3b8; font-weight: 700; }
.pgk-meta { margin-bottom: .8rem; }
.pgk-empty { color: #94a3b8; font-size: 13px; padding: .8rem; }
.pgk-rows { display: flex; flex-direction: column; gap: .6rem; }
.pgk-row { display: flex; align-items: flex-start; gap: .6rem; background: #f8fafc; border: 1px solid rgba(11, 16, 51, .07); border-radius: 12px; padding: .7rem; }
.pgk-row__grid { flex: 1; display: grid; gap: .5rem; min-width: 0; }
/* Kolom menyesuaikan mode: saat MEMBUAT, field yang jawabannya sudah pasti
   (terisi/status) tidak dirender sama sekali, jadi gridnya ikut menyempit. */
.pgk-row__grid--batch { grid-template-columns: 2fr 1fr; }
.pgk-row__grid--batch.is-edit { grid-template-columns: 1.6fr 1fr 1fr 1fr; }
.pgk-row__grid--posisi { grid-template-columns: 1.3fr 1.2fr 1fr 1fr; }
.pgk-row__grid--posisi.is-edit { grid-template-columns: 1.3fr 1.2fr 1fr 1fr 1fr; }

/* Baris posisi disusun bertingkat: picker MPP di atas (lebar penuh, karena
   labelnya panjang), field turunan yang terkunci di bawahnya. */
.pgk-row--stack { flex-wrap: wrap; position: relative; padding-right: 3rem; }
.pgk-row__pick { flex: 1 1 100%; min-width: 0; }
.pgk-row--stack .pgk-row__grid { flex: 1 1 100%; }
.pgk-row__del { position: absolute; top: .7rem; right: .7rem; margin-top: 0 !important; }

/* ── Stepper wizard ─────────────────────────────────────────── */
/* Stepper: dot di atas, label di bawah — garis penghubung lewat PUSAT antar-dot. */
.pgk-steps { display: flex; margin-bottom: 1.15rem; padding-bottom: .9rem; border-bottom: 1px solid rgba(11, 16, 51, .09); }
.pgk-step { position: relative; flex: 1; display: flex; flex-direction: column; align-items: center; gap: .45rem; border: 0; background: transparent; cursor: pointer; padding: .2rem .4rem; font: inherit; text-align: center; }
.pgk-step:not(:first-child)::before { content: ''; position: absolute; top: calc(.2rem + .95rem - 1px); left: calc(-50% + 1.35rem); right: calc(50% + 1.35rem); height: 2px; border-radius: 99px; background: #e6e9f3; }
.pgk-step.done::before, .pgk-step.cur::before { background: linear-gradient(90deg, #a5b4fc, #8b5cf6); }
.pgk-step:disabled { cursor: not-allowed; opacity: .55; }
.pgk-step__dot { position: relative; z-index: 1; flex: none; width: 1.9rem; height: 1.9rem; display: grid; place-items: center; border-radius: 50%; background: #eef2f7; color: #94a3b8; font-size: .8rem; transition: all 200ms ease; box-shadow: 0 0 0 4px #fff; }
.pgk-step.done .pgk-step__dot { background: rgba(16, 185, 129, .15); color: #059669; }
.pgk-step.cur .pgk-step__dot { background: linear-gradient(140deg, #4f46e5, #7c3aed); color: #fff; box-shadow: 0 6px 14px -6px rgba(79, 70, 229, .9); }
.pgk-step__lbl { font-size: 12.5px; font-weight: 600; color: #94a3b8; line-height: 1.25; }
.pgk-step__lbl small { font-weight: 500; color: #cbd5e1; }
.pgk-step.cur .pgk-step__lbl { color: #4338ca; }
.pgk-step.done .pgk-step__lbl { color: #059669; }

/* ── Panel per langkah ──────────────────────────────────────── */
.pgk-panel__bar { display: flex; align-items: flex-start; justify-content: space-between; gap: .6rem; margin-bottom: .8rem; }
.pgk-panel__title { font-size: 13px; font-weight: 700; color: #334155; display: flex; align-items: center; gap: .4rem; }
.pgk-panel__note { font-size: 11.5px; color: #94a3b8; margin-top: .15rem; }
.pgk-panel__intro { display: flex; align-items: center; gap: .5rem; font-size: 12.5px; color: #475569; background: rgba(79, 70, 229, .06); border-radius: 10px; padding: .6rem .8rem; margin-bottom: 1rem; }

/* ── Seksi opsional dengan toggle ───────────────────────────── */
.pgk-opsi { border: 1px solid rgba(11, 16, 51, .1); border-radius: 14px; margin-bottom: .9rem; overflow: hidden; transition: border-color 180ms ease; }
.pgk-opsi.is-on { border-color: rgba(79, 70, 229, .3); }
.pgk-opsi__hd { display: flex; align-items: center; flex-wrap: wrap; gap: .3rem .7rem; padding: .8rem 1rem; cursor: pointer; margin: 0; }
.pgk-opsi.is-on .pgk-opsi__hd { background: rgba(79, 70, 229, .04); border-bottom: 1px solid rgba(79, 70, 229, .12); }
.pgk-opsi__t { font-size: 13.5px; font-weight: 700; color: #0f172a; display: inline-flex; align-items: center; gap: .4rem; }
.pgk-opsi__hd small { flex: 1 1 100%; font-size: 11.5px; color: #94a3b8; padding-left: 3rem; }
.pgk-opsi__body { padding: .9rem 1rem 1rem; }
.pgk-opsi__toolbar { display: flex; align-items: center; gap: .5rem; margin-bottom: .6rem; }
.pgk-req { font-weight: 600; font-size: 11px; color: #b45309; background: #fef3c7; border-radius: 999px; padding: .1rem .45rem; margin-left: .35rem; }
.pgk-hint { font-size: 11.5px; color: #64748b; margin-top: .3rem; }
.pgk-hint--warn { color: #b45309; }
.pgk-syarat { border: 1px solid rgba(79,70,229,.18); border-radius: 14px; padding: .9rem; margin-bottom: .7rem; background: #fff; }
/* Identitas syarat: nama + tahap berlabel, tombol hapus di kanan. */
.pgk-syarat__id { display: grid; grid-template-columns: 1fr 1fr auto; gap: .6rem; align-items: end; margin-bottom: .7rem; }
.pgk-syarat__del { margin-bottom: .1rem; }
.pgk-pesan { margin-top: .7rem; }
.pgk-pesan .wca-field-lbl { display: flex; align-items: center; gap: .3rem; color: #4338ca; }
.pgk-isipesan { margin-top: .35rem; border: 0; background: transparent; color: #7c3aed; font: inherit; font-size: 11.5px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: .3rem; padding: 0; }
.pgk-isipesan:hover { text-decoration: underline; }

.pgk-aturan { border: 1px solid rgba(79,70,229,.14); border-radius: 12px; padding: .7rem; background: rgba(248,250,252,.7); }
.pgk-aturan__bar { display: flex; align-items: center; flex-wrap: wrap; gap: .4rem; font-size: 12.5px; color: #475569; margin-bottom: .6rem; }

/* Kondisi: grid berlabel yang lega — field cukup lebar sehingga tidak
   terpotong jadi "U". Turun 1 kolom di layar sempit. */
.pgk-kondisi { display: grid; grid-template-columns: minmax(0, 1.7fr) minmax(0, 1.2fr) minmax(0, 1fr) auto; gap: .5rem; align-items: end; margin-bottom: .5rem; }
.pgk-kondisi__f, .pgk-kondisi__o, .pgk-kondisi__v { min-width: 0; }
.pgk-kondisi__del { margin-bottom: .1rem; }
.pgk-kondisi-kosong { font-size: 11.5px; color: #94a3b8; padding: .3rem 0; }
.pgk-addkondisi { margin-top: .2rem; width: 100%; border: 1px dashed rgba(79,70,229,.3); border-radius: 9px; background: transparent; color: #4338ca; font: inherit; font-size: 12px; font-weight: 600; padding: .45rem; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; gap: .35rem; }
.pgk-addkondisi:hover { background: rgba(79,70,229,.06); }

/* Aksi + mode uji, berdampingan rapi. */
.pgk-syarat__opt { display: grid; grid-template-columns: 1fr 1fr; gap: .7rem; align-items: center; margin-top: .7rem; }
.pgk-syarat__uji { display: flex; flex-direction: column; gap: .1rem; }
.pgk-syarat__uji small { font-size: 11px; color: #94a3b8; padding-left: 1.6rem; }
.pgk-syarat-ro { border-left: 3px solid #4f46e5; padding: .5rem .7rem; margin-bottom: .5rem; background: rgba(79,70,229,.04); border-radius: 0 10px 10px 0; }
.pgk-syarat-ro__top { display: flex; align-items: center; flex-wrap: wrap; gap: .4rem; margin-bottom: .35rem; font-size: 13px; }
.pgk-gab { font-size: 10.5px; font-weight: 700; letter-spacing: .06em; color: #4338ca; background: rgba(79,70,229,.12); border-radius: 999px; padding: .1rem .45rem; }
.pgk-mpp { font-size: 11.5px; color: #4338ca; background: rgba(79, 70, 229, .08); border-radius: 6px; padding: .1rem .35rem; }

/* Meter kursi: hijau selama masih di dalam pagu MPP, merah begitu terlampaui. */
.pgk-meter { display: inline-flex; align-items: center; gap: .3rem; font-size: 11.5px; font-weight: 600; letter-spacing: 0; text-transform: none; color: #047857; background: rgba(16, 185, 129, .1); border: 1px solid rgba(16, 185, 129, .25); border-radius: 999px; padding: .2rem .55rem; }
.pgk-meter.is-over { color: #b91c1c; background: rgba(239, 68, 68, .1); border-color: rgba(239, 68, 68, .3); }
.pgk-alert { display: flex; align-items: center; gap: .5rem; font-size: 12.5px; color: #b91c1c; background: rgba(239, 68, 68, .08); border: 1px solid rgba(239, 68, 68, .25); border-radius: 10px; padding: .6rem .75rem; }
.pgk-statusnote { display: flex; align-items: center; gap: .45rem; align-self: end; font-size: 12.5px; color: #047857; background: rgba(16, 185, 129, .1); border: 1px solid rgba(16, 185, 129, .25); border-radius: 10px; padding: .55rem .7rem; }
.pgk-row__grid--kriteria { grid-template-columns: 1.2fr 1fr 1.2fr; }
.pgk-row .wca-iconbtn { margin-top: 1.6rem; }
@media (max-width: 900px) {
    .pgk-row__grid--batch,
    .pgk-row__grid--posisi,
    .pgk-row__grid--kriteria { grid-template-columns: 1fr 1fr; }
    .pgk-kondisi { grid-template-columns: 1fr 1fr; }
    .pgk-kondisi__f { grid-column: 1 / -1; }
    .pgk-kondisi__del { grid-column: 2; justify-self: end; margin-bottom: 0; }
}
@media (max-width: 560px) {
    .pgk-row__grid--batch,
    .pgk-row__grid--posisi,
    .pgk-row__grid--kriteria { grid-template-columns: 1fr; }
    .pgk-row .wca-iconbtn { margin-top: 0; }
    .pgk-row__del { margin-top: 0 !important; }
    .pgk-steps { gap: .2rem; }
    .pgk-step__lbl { display: none; }
    .pgk-syarat__id { grid-template-columns: 1fr auto; }
    .pgk-syarat__opt { grid-template-columns: 1fr; }
}

/* ── Picker Program (kartu detail, gaya MPP) ── */
.pbk-proglist { max-height: 340px; overflow-y: auto; display: grid; grid-template-columns: repeat(2, 1fr); gap: .55rem; padding-right: 2px; }
@media (max-width: 640px) { .pbk-proglist { grid-template-columns: 1fr; } }
.pbk-progcard { text-align: left; border: 1px solid rgba(15, 23, 42, .1); background: #fff; border-radius: 13px; padding: .7rem .85rem; cursor: pointer; transition: all .15s; display: flex; flex-direction: column; gap: .5rem; }
.pbk-progcard:hover { border-color: rgba(99, 102, 241, .4); background: #f8fafc; transform: translateY(-1px); }
.pbk-progcard.is-on { border-color: #6366f1; background: #eef2ff; box-shadow: 0 4px 14px rgba(79, 70, 229, .12); }
.pbk-progcard__head { display: flex; align-items: center; gap: .6rem; }
.pbk-progcard__ico { flex: none; width: 2.2rem; height: 2.2rem; border-radius: 10px; display: grid; place-items: center; font-size: 1rem; }
.pbk-progcard__id { min-width: 0; flex: 1; display: flex; flex-direction: column; }
.pbk-progcard__id strong { font-size: 13.5px; color: #1e293b; line-height: 1.25; }
.pbk-progcard__id small { font-size: 11px; color: #94a3b8; }
.pbk-progcard__chk { color: #4f46e5; font-size: 17px; flex: none; }
.pbk-progcard__meta { display: flex; flex-wrap: wrap; gap: .3rem .9rem; font-size: 11.5px; color: #64748b; }
.pbk-progcard__meta span { display: inline-flex; align-items: center; gap: .3rem; }
.pbk-progcard__meta > span > i { color: #94a3b8; }
.pbk-progcard__date { color: #4338ca; font-weight: 700; }
.pbk-progcard__date > i { color: #6366f1 !important; }
.pbk-progload { grid-column: 1 / -1; display: flex; align-items: center; justify-content: center; gap: .5rem; color: #64748b; font-size: 12.5px; font-weight: 600; padding: 2rem 0; }
.pbk-spin { width: 16px; height: 16px; border: 2px solid rgba(99, 102, 241, .25); border-top-color: #6366f1; border-radius: 50%; animation: pbkspin .7s linear infinite; }
@keyframes pbkspin { to { transform: rotate(360deg); } }
.pbk-progempty { grid-column: 1 / -1; text-align: center; color: #94a3b8; font-size: 12.5px; padding: 1.6rem 0; }
.pbk-progempty > i { display: block; font-size: 1.5rem; margin-bottom: .3rem; opacity: .6; }
/* Reuse pil kategori sebagai warna ikon kartu */
.pbk-progcard__ico.pkg-pill--gold { background: rgba(234, 179, 8, .16); color: #a16207; }
.pbk-progcard__ico.pkg-pill--green { background: rgba(16, 185, 129, .14); color: #059669; }
.pbk-progcard__ico.pkg-pill--sky { background: rgba(14, 165, 233, .14); color: #0369a1; }
.pbk-progcard__ico.pkg-pill--slate { background: #eef0f7; color: #64748b; }
.pbk-note { display: flex; align-items: flex-start; gap: .4rem; margin: .2rem 0 0; font-size: 11.5px; line-height: 1.55; color: #64748b; }
.pbk-note > i { color: #6366f1; margin-top: 1px; }
</style>

