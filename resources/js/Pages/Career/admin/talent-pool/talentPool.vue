<!-- WEB CAREER — Talent Pool. Kandidat "bagus tapi belum terpakai" dari Worklist.
     DATA dari DB via /api/v1/karir/talent-pool. -->
<template>
    <Head><title>Talent Pool - Web Career</title></Head>
    <div class="wca">
        <div class="pkg-head">
            <div class="pkg-head__l">
                <div class="pkg-head__title">
                    <span class="pkg-head__ico tp-ico"><i class="bi bi-stars"></i></span>
                    <h1>Talent Pool</h1>
                </div>
                <p>Kandidat bagus yang belum lolos di lowongannya, disimpan untuk kesempatan berikutnya. Kuota tidak terpotong oleh kartu di sini.</p>
            </div>
        </div>

        <!-- Ringkasan angka -->
        <div class="tp-stats">
            <div class="tp-stat tp-stat--total"><span class="tp-stat__n">{{ ringkas.total }}</span><span class="tp-stat__l">Total</span></div>
            <div class="tp-stat tp-stat--aktif"><span class="tp-stat__n">{{ ringkas.aktif }}</span><span class="tp-stat__l">Aktif</span></div>
            <div class="tp-stat tp-stat--ditarik"><span class="tp-stat__n">{{ ringkas.ditarik }}</span><span class="tp-stat__l">Ditarik</span></div>
            <div class="tp-stat tp-stat--kedaluwarsa"><span class="tp-stat__n">{{ ringkas.kedaluwarsa }}</span><span class="tp-stat__l">Kadaluarsa</span></div>
            <div class="tp-stat tp-stat--arsip"><span class="tp-stat__n">{{ ringkas.arsip }}</span><span class="tp-stat__l">Arsip</span></div>
        </div>

        <!-- Filter -->
        <div class="tp-filter">
            <div class="tp-filter__search">
                <el-input v-model="filters.q" placeholder="Cari kandidat / posisi / program / tag" clearable @input="cariDebounce">
                    <template #prefix><i class="bi bi-search"></i></template>
                </el-input>
            </div>
            <div class="tp-filter__seg">
                <button v-for="s in statusOpsi" :key="s.value" type="button" class="tp-seg" :class="{ 'is-on': filters.status === s.value }" @click="setStatus(s.value)">{{ s.label }}</button>
            </div>
        </div>

        <!-- Toolbar: pilih-semua, urutan, ekspor -->
        <div class="tp-toolbar">
            <label class="tp-checkall" v-if="list.length">
                <el-checkbox :model-value="selected.length === list.length && list.length > 0" :indeterminate="selected.length > 0 && selected.length < list.length" @change="toggleAll" />
                <span>{{ selected.length ? `${selected.length} dipilih` : 'Pilih semua' }}</span>
            </label>
            <div class="tp-toolbar__r">
                <el-select :model-value="sort" size="small" style="width: 175px" @change="setSort">
                    <template #prefix><i class="bi bi-sort-down"></i></template>
                    <el-option v-for="s in sortOpsi" :key="s.value" :label="s.label" :value="s.value" />
                </el-select>
                <button type="button" class="tp-tbtn" @click="exportCsv"><i class="bi bi-download"></i> Ekspor CSV</button>
            </div>
        </div>

        <!-- Bar aksi massal (muncul saat ada yang dipilih) -->
        <transition name="wca-toast">
            <div v-if="selected.length" class="tp-bulk">
                <span><i class="bi bi-check2-square"></i> {{ selected.length }} kartu dipilih</span>
                <div class="tp-bulk__act">
                    <button type="button" @click="bulkAksi('PERPANJANG')"><i class="bi bi-arrow-clockwise"></i> Perpanjang</button>
                    <button type="button" @click="bulkAksi('ARSIP')"><i class="bi bi-archive"></i> Arsipkan</button>
                    <button type="button" @click="bulkAksi('AKTIF')"><i class="bi bi-stars"></i> Aktifkan</button>
                    <button type="button" class="is-danger" @click="bulkAksi('HAPUS')"><i class="bi bi-trash"></i> Hapus</button>
                    <button type="button" class="is-clear" @click="selected = []"><i class="bi bi-x-lg"></i></button>
                </div>
            </div>
        </transition>

        <div v-loading="loading" class="tp-grid">
            <div v-for="k in list" :key="k.id" class="tp-card" :class="['is-' + k.status.toLowerCase(), { 'is-sel': isSel(k.id) }]">
                <div class="tp-card__top">
                    <el-checkbox class="tp-card__chk" :model-value="isSel(k.id)" @change="toggleSel(k.id)" @click.stop />
                    <span class="tp-card__av">{{ initials(k.kandidat) }}</span>
                    <div class="tp-card__id">
                        <strong class="tp-ell">{{ k.kandidat }}</strong>
                        <small v-if="k.email" class="tp-ell">{{ k.email }}</small>
                    </div>
                    <span class="tp-badge" :class="'tp-badge--' + k.status.toLowerCase()">{{ statusLabel(k.status) }}</span>
                </div>

                <div class="tp-card__body">
                    <div class="tp-line"><i class="bi bi-briefcase"></i> <span class="tp-ell">{{ k.posisi }}</span></div>
                    <div class="tp-line"><i class="bi bi-diagram-3"></i> <span class="tp-ell">{{ k.program }}</span></div>
                    <div class="tp-line tp-line--sub">
                        <span v-if="k.tahapAsal"><i class="bi bi-signpost"></i> {{ k.tahapAsal }}</span>
                        <span v-if="k.skor !== null" class="tp-skor"><i class="bi bi-graph-up"></i> {{ k.skor }}</span>
                    </div>
                    <div v-if="k.tanggalKedaluwarsa" class="tp-exp" :class="expClass(k)">
                        <i class="bi bi-hourglass-split"></i>
                        <span v-if="k.status === 'KEDALUWARSA'">Kadaluarsa {{ k.tanggalKedaluwarsa }}</span>
                        <span v-else>Berlaku s/d {{ k.tanggalKedaluwarsa }}<template v-if="k.sisaHari !== null"> · {{ k.sisaHari }} hari lagi</template></span>
                    </div>
                    <div v-if="k.tag" class="tp-tag"><i class="bi bi-tag-fill"></i> {{ k.tag }}</div>
                    <p v-if="k.catatan" class="tp-note">{{ k.catatan }}</p>
                </div>

                <div class="tp-card__foot">
                    <span class="tp-by"><i class="bi bi-clock"></i> {{ k.createdAt || '—' }}</span>
                    <div class="tp-act">
                        <button v-if="k.status === 'KEDALUWARSA' || (k.sisaHari !== null && k.sisaHari <= 14)" class="tp-ibtn tp-ibtn--gold" title="Perpanjang masa berlaku" @click="perpanjang(k)"><i class="bi bi-arrow-clockwise"></i></button>
                        <button class="tp-ibtn" title="Kelola" @click="openEdit(k)"><i class="bi bi-sliders"></i></button>
                        <button class="tp-ibtn tp-ibtn--danger" title="Hapus" @click="askRemove(k)"><i class="bi bi-trash"></i></button>
                    </div>
                </div>
            </div>
            <div v-if="!loading && !list.length" class="tp-empty"><i class="bi bi-stars"></i> {{ adaFilter ? 'Tidak ada kandidat yang cocok.' : 'Talent Pool masih kosong. Kandidat akan muncul di sini saat admin menekan “Masuk Talent Pool” di Worklist.' }}</div>
        </div>

        <!-- Paginasi -->
        <div v-if="totalPage > 1" class="tp-pager">
            <button type="button" class="tp-pager__btn" :disabled="page <= 1" @click="gotoPage(page - 1)"><i class="bi bi-chevron-left"></i></button>
            <span class="tp-pager__info">Halaman {{ page }} / {{ totalPage }} · {{ total }} kartu</span>
            <button type="button" class="tp-pager__btn" :disabled="page >= totalPage" @click="gotoPage(page + 1)"><i class="bi bi-chevron-right"></i></button>
        </div>

        <!-- Kelola kartu -->
        <AdminModal :busy="saving" :show="show" title="Kelola Kartu Talent Pool" :subtitle="edit ? edit.kandidat : ''" icon="bi-stars" save-label="Simpan" @close="show = false" @save="save">
            <div class="wca-fsection">
                <div class="wca-form">
                    <div>
                        <label class="wca-field-lbl">Status</label>
                        <el-select v-model="form.status" style="width:100%">
                            <el-option label="Aktif — siap dipertimbangkan lagi" value="AKTIF" />
                            <el-option label="Ditarik — sedang diproses untuk lowongan lain" value="DITARIK" />
                            <el-option label="Arsip — disimpan, tidak aktif" value="ARSIP" />
                        </el-select>
                    </div>
                    <div><label class="wca-field-lbl">Tag</label><el-input v-model="form.tag" placeholder="mis. Kuat wawancara, cocok Finance" /></div>
                    <div><label class="wca-field-lbl">Catatan</label><el-input v-model="form.catatan" type="textarea" :rows="3" placeholder="Catatan rekruter" /></div>
                </div>
            </div>
        </AdminModal>

        <ConfirmModal :show="delShow" title="Hapus dari Talent Pool" :busy="deleting" confirm-label="Ya, Hapus" danger note="Kartu akan dihapus permanen dari kolam." @cancel="delShow = false" @confirm="confirmDelete">
            Hapus <strong>{{ delTarget?.kandidat }}</strong> dari Talent Pool?
        </ConfirmModal>

        <transition name="wca-toast"><div v-if="toast" class="wca-toast"><i class="bi bi-check-circle-fill"></i> {{ toast }}</div></transition>
    </div>
</template>

<script>
import axios from 'axios';
import { Head } from '@inertiajs/vue3';
import AdminModal from '@career/AdminModal.vue';
import ConfirmModal from '@career/ConfirmModal.vue';

const API = '/api/v1/karir/talent-pool';
const CFG = { headers: { Accept: 'application/json' } };

export default {
    components: { Head, AdminModal, ConfirmModal },
    data() {
        return {
            list: [],
            ringkas: { total: 0, aktif: 0, ditarik: 0, arsip: 0, kedaluwarsa: 0 },
            loading: false,
            filters: { q: '', status: '' },
            sort: 'terbaru',
            sortOpsi: [
                { value: 'terbaru', label: 'Terbaru' },
                { value: 'lama', label: 'Terlama' },
                { value: 'skor', label: 'Skor tertinggi' },
                { value: 'kedaluwarsa', label: 'Segera kadaluarsa' },
                { value: 'nama', label: 'Nama A–Z' },
            ],
            page: 1,
            perPage: 12,
            total: 0,
            totalPage: 1,
            selected: [],
            cariTimer: null,
            statusOpsi: [
                { value: '', label: 'Semua' },
                { value: 'AKTIF', label: 'Aktif' },
                { value: 'DITARIK', label: 'Ditarik' },
                { value: 'KEDALUWARSA', label: 'Kadaluarsa' },
                { value: 'ARSIP', label: 'Arsip' },
            ],
            show: false,
            edit: null,
            form: { status: 'AKTIF', tag: '', catatan: '' },
            saving: false,
            delShow: false,
            delTarget: null,
            deleting: false,
            toast: '',
            tm: null,
        };
    },
    computed: {
        adaFilter() { return !!(this.filters.q || this.filters.status); },
    },
    mounted() { this.load(); },
    methods: {
        initials(name) {
            if (!name) return '—';
            const p = String(name).trim().split(/\s+/);
            return ((p[0]?.[0] || '') + (p[1]?.[0] || '')).toUpperCase() || '—';
        },
        statusLabel(s) { return { AKTIF: 'Aktif', DITARIK: 'Ditarik', ARSIP: 'Arsip', KEDALUWARSA: 'Kadaluarsa' }[s] || s; },
        /** Warna baris masa berlaku: merah bila lewat/≤7 hari, kuning ≤14, netral. */
        expClass(k) {
            if (k.status === 'KEDALUWARSA' || (k.sisaHari !== null && k.sisaHari < 0)) return 'is-habis';
            if (k.sisaHari !== null && k.sisaHari <= 7) return 'is-kritis';
            if (k.sisaHari !== null && k.sisaHari <= 14) return 'is-segera';
            return '';
        },
        async perpanjang(k) {
            try {
                await axios.patch(`${API}/${k.id}/perpanjang`, {}, CFG);
                this.notice('Masa berlaku diperpanjang.');
                await this.load();
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal memperpanjang.');
            }
        },
        async load() {
            this.loading = true;
            try {
                const params = {
                    q: this.filters.q || undefined,
                    status: this.filters.status || undefined,
                    sort: this.sort,
                    page: this.page,
                    perPage: this.perPage,
                };
                const res = await axios.get(API, { ...CFG, params });
                const r = res.data.result || {};
                this.list = r.data || [];
                this.ringkas = r.ringkas || { total: 0, aktif: 0, ditarik: 0, arsip: 0, kedaluwarsa: 0 };
                this.total = r.total || 0;
                this.totalPage = r.totalPage || 1;
                this.selected = [];
            } catch (e) {
                this.notice('Gagal memuat talent pool.');
            } finally {
                this.loading = false;
            }
        },
        cariDebounce() {
            if (this.cariTimer) clearTimeout(this.cariTimer);
            this.cariTimer = setTimeout(() => { this.page = 1; this.load(); }, 400);
        },
        setStatus(v) { this.filters.status = v; this.page = 1; this.load(); },
        setSort(v) { this.sort = v; this.page = 1; this.load(); },
        gotoPage(n) { if (n < 1 || n > this.totalPage) return; this.page = n; this.load(); },
        /* ── Seleksi & aksi massal ── */
        isSel(id) { return this.selected.includes(id); },
        toggleSel(id) {
            const i = this.selected.indexOf(id);
            if (i >= 0) this.selected.splice(i, 1); else this.selected.push(id);
        },
        toggleAll() {
            if (this.selected.length === this.list.length) this.selected = [];
            else this.selected = this.list.map((k) => k.id);
        },
        async bulkAksi(aksi) {
            if (!this.selected.length) return;
            try {
                const res = await axios.post(`${API}/bulk`, { ids: this.selected, aksi }, CFG);
                this.notice(res.data?.message || 'Aksi massal selesai.');
                await this.load();
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal aksi massal.');
            }
        },
        exportCsv() {
            const p = new URLSearchParams();
            if (this.filters.q) p.set('q', this.filters.q);
            if (this.filters.status) p.set('status', this.filters.status);
            window.open(`${API}/export?${p.toString()}`, '_blank');
        },
        openEdit(k) {
            this.edit = k;
            this.form = { status: k.status, tag: k.tag || '', catatan: k.catatan || '' };
            this.show = true;
        },
        async save() {
            if (this.saving || !this.edit) return;
            this.saving = true;
            try {
                await axios.patch(`${API}/${this.edit.id}`, this.form, CFG);
                this.notice('Kartu diperbarui.');
                this.show = false;
                await this.load();
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal menyimpan.');
            } finally {
                this.saving = false;
            }
        },
        askRemove(k) { this.delTarget = k; this.delShow = true; },
        async confirmDelete() {
            if (this.deleting || !this.delTarget) return;
            this.deleting = true;
            try {
                await axios.delete(`${API}/${this.delTarget.id}`, CFG);
                this.notice('Kartu dihapus.');
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
.tp-ico { background: linear-gradient(135deg, #fbbf24, #d97706) !important; }

/* Stat tiles */
.tp-stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: .8rem; margin-bottom: 1rem; }
.tp-stat { border-radius: 14px; padding: .85rem 1rem; border: 1px solid rgba(15, 23, 42, .08); background: #fff; display: flex; flex-direction: column; gap: .1rem; box-shadow: 0 6px 18px rgba(15, 23, 42, .04); }
.tp-stat__n { font-size: 1.5rem; font-weight: 800; color: #1e293b; line-height: 1; }
.tp-stat__l { font-size: 11.5px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: #94a3b8; }
.tp-stat--total { border-top: 3px solid #6366f1; }
.tp-stat--aktif { border-top: 3px solid #d97706; }
.tp-stat--ditarik { border-top: 3px solid #0ea5e9; }
.tp-stat--kedaluwarsa { border-top: 3px solid #ef4444; }
.tp-stat--arsip { border-top: 3px solid #94a3b8; }
.tp-stats { grid-template-columns: repeat(5, 1fr); }
@media (max-width: 640px) { .tp-stats { grid-template-columns: repeat(2, 1fr); } }

/* Baris masa berlaku pada kartu */
.tp-exp { display: inline-flex; align-items: center; gap: .35rem; align-self: flex-start; font-size: 11px; font-weight: 700; color: #64748b; background: #f1f5f9; border-radius: 999px; padding: 2px 9px; }
.tp-exp.is-segera { color: #b45309; background: rgba(234, 179, 8, .14); }
.tp-exp.is-kritis { color: #c2410c; background: rgba(249, 115, 22, .16); }
.tp-exp.is-habis { color: #dc2626; background: rgba(239, 68, 68, .14); }
.tp-badge--kedaluwarsa { background: rgba(239, 68, 68, .14); color: #dc2626; }
.tp-card.is-kedaluwarsa { border-top-color: #ef4444; opacity: .96; }
.tp-card.is-sel { outline: 2px solid #d97706; outline-offset: 1px; }
.tp-card__chk { flex: none; }

/* Toolbar & bulk */
.tp-toolbar { display: flex; align-items: center; justify-content: space-between; gap: .8rem; margin-bottom: .8rem; flex-wrap: wrap; }
.tp-checkall { display: inline-flex; align-items: center; gap: .5rem; font-size: 12.5px; font-weight: 700; color: #475569; }
.tp-toolbar__r { display: inline-flex; align-items: center; gap: .5rem; }
.tp-tbtn { display: inline-flex; align-items: center; gap: .35rem; border: 1px solid rgba(15, 23, 42, .12); background: #fff; color: #475569; font-size: 12px; font-weight: 700; border-radius: 9px; padding: 6px 12px; cursor: pointer; }
.tp-tbtn:hover { background: #f8fafc; color: #d97706; border-color: rgba(217, 119, 6, .4); }
.tp-bulk { display: flex; align-items: center; justify-content: space-between; gap: .8rem; background: linear-gradient(135deg, #fffbeb, #fff7ed); border: 1px solid rgba(217, 119, 6, .3); border-radius: 12px; padding: .6rem .9rem; margin-bottom: .8rem; flex-wrap: wrap; }
.tp-bulk > span { font-size: 12.5px; font-weight: 800; color: #92400e; display: inline-flex; align-items: center; gap: .4rem; }
.tp-bulk__act { display: inline-flex; align-items: center; gap: .4rem; flex-wrap: wrap; }
.tp-bulk__act button { display: inline-flex; align-items: center; gap: .3rem; border: 1px solid rgba(15, 23, 42, .12); background: #fff; color: #475569; font-size: 11.5px; font-weight: 700; border-radius: 8px; padding: 5px 10px; cursor: pointer; }
.tp-bulk__act button:hover { background: #f8fafc; }
.tp-bulk__act .is-danger { color: #dc2626; border-color: #fca5a5; }
.tp-bulk__act .is-danger:hover { background: #fef2f2; }
.tp-bulk__act .is-clear { color: #94a3b8; padding: 5px 8px; }

/* Paginasi */
.tp-pager { display: flex; align-items: center; justify-content: center; gap: .8rem; margin-top: 1.1rem; }
.tp-pager__btn { width: 34px; height: 34px; border-radius: 9px; border: 1px solid rgba(15, 23, 42, .12); background: #fff; color: #475569; cursor: pointer; display: grid; place-items: center; }
.tp-pager__btn:disabled { opacity: .4; cursor: not-allowed; }
.tp-pager__btn:not(:disabled):hover { background: #f8fafc; color: #d97706; }
.tp-pager__info { font-size: 12.5px; font-weight: 700; color: #64748b; }
.tp-ibtn--gold { color: #b45309; border-color: rgba(217, 119, 6, .4); }
.tp-ibtn--gold:hover { background: #fff7ed; color: #92400e; border-color: #d97706; }

/* Filter */
.tp-filter { display: flex; gap: .7rem; align-items: center; flex-wrap: wrap; background: #fff; border: 1px solid rgba(15, 23, 42, .08); border-radius: 14px; padding: .7rem .8rem; margin-bottom: 1rem; box-shadow: 0 6px 18px rgba(15, 23, 42, .04); }
.tp-filter__search { flex: 1; min-width: 220px; }
.tp-filter__seg { display: inline-flex; background: #f1f5f9; border-radius: 10px; padding: 3px; gap: 2px; }
.tp-seg { border: none; background: transparent; font-size: 12px; font-weight: 700; color: #64748b; padding: 5px 12px; border-radius: 8px; cursor: pointer; transition: all .15s; }
.tp-seg.is-on { background: #fff; color: #d97706; box-shadow: 0 2px 6px rgba(15, 23, 42, .1); }

/* Card grid */
.tp-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(290px, 1fr)); gap: .9rem; }
.tp-card { border: 1px solid rgba(15, 23, 42, .09); border-radius: 16px; background: #fff; padding: .9rem; display: flex; flex-direction: column; gap: .6rem; box-shadow: 0 8px 22px rgba(15, 23, 42, .05); transition: transform .16s, box-shadow .16s; border-top: 3px solid #e2e8f0; }
.tp-card:hover { transform: translateY(-2px); box-shadow: 0 14px 30px rgba(15, 23, 42, .09); }
.tp-card.is-aktif { border-top-color: #f59e0b; }
.tp-card.is-ditarik { border-top-color: #0ea5e9; }
.tp-card.is-arsip { border-top-color: #cbd5e1; opacity: .92; }
.tp-card__top { display: flex; align-items: center; gap: .6rem; }
.tp-card__av { flex: none; width: 2.3rem; height: 2.3rem; border-radius: 50%; display: grid; place-items: center; font-size: 12.5px; font-weight: 800; color: #fff; background: linear-gradient(135deg, #fbbf24, #d97706); }
.tp-card__id { min-width: 0; flex: 1; display: flex; flex-direction: column; }
.tp-card__id strong { font-size: 13.5px; color: #1e293b; }
.tp-card__id small { font-size: 11px; color: #94a3b8; }
.tp-ell { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.tp-badge { flex: none; font-size: 10px; font-weight: 800; letter-spacing: .05em; text-transform: uppercase; padding: 3px 9px; border-radius: 999px; }
.tp-badge--aktif { background: rgba(234, 179, 8, .16); color: #a16207; }
.tp-badge--ditarik { background: rgba(14, 165, 233, .14); color: #0369a1; }
.tp-badge--arsip { background: #f1f5f9; color: #64748b; }
.tp-card__body { display: flex; flex-direction: column; gap: .35rem; }
.tp-line { display: flex; align-items: center; gap: .45rem; font-size: 12.5px; color: #475569; }
.tp-line > i { color: #94a3b8; flex: none; }
.tp-line--sub { gap: .9rem; font-size: 11.5px; color: #64748b; }
.tp-skor { color: #7c3aed; font-weight: 700; }
.tp-tag { display: inline-flex; align-items: center; gap: .3rem; align-self: flex-start; font-size: 11px; font-weight: 700; color: #b45309; background: rgba(234, 179, 8, .12); border-radius: 999px; padding: 2px 9px; }
.tp-note { margin: 0; font-size: 12px; line-height: 1.5; color: #64748b; background: #f8fafc; border-radius: 9px; padding: .5rem .6rem; }
.tp-card__foot { display: flex; align-items: center; justify-content: space-between; margin-top: auto; padding-top: .5rem; border-top: 1px dashed rgba(15, 23, 42, .1); }
.tp-by { font-size: 11px; color: #94a3b8; display: inline-flex; align-items: center; gap: .3rem; }
.tp-act { display: inline-flex; gap: .3rem; }
.tp-ibtn { border: 1px solid rgba(15, 23, 42, .1); background: #fff; color: #475569; width: 1.9rem; height: 1.9rem; border-radius: 8px; cursor: pointer; display: grid; place-items: center; transition: all .15s; }
.tp-ibtn:hover { background: #f8fafc; color: #d97706; border-color: rgba(217, 119, 6, .4); }
.tp-ibtn--danger:hover { color: #dc2626; border-color: #fca5a5; background: #fef2f2; }
.tp-empty { grid-column: 1 / -1; text-align: center; color: #94a3b8; font-size: 13px; padding: 3rem 1rem; line-height: 1.6; }
.tp-empty > i { display: block; font-size: 2rem; color: #d97706; margin-bottom: .5rem; opacity: .6; }
</style>
