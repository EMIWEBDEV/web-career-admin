<!-- WEB CAREER — Master Workplace (tipe LOKASI kerja). Data via web route + ResponseHelper (axios).
     Bentuk halaman sengaja dibuat kembar dengan Master Experience Level: dua-duanya
     master pendamping Detail_MPP dengan skema yang sama persis, jadi admin tidak
     perlu belajar dua tata letak untuk pekerjaan yang sama. -->
<template>
    <Head><title>Master Workplace - Web Career</title></Head>
    <div class="wca">
        <div class="wca-phead">
            <div>
                <h1>Master Workplace</h1>
                <p>Tipe lokasi kerja (On-site/WFO, Hybrid, Remote/WFH, …). Dipakai sebagai pilihan <b>Workplace Type</b> saat menyusun MPP &amp; lowongan. Hanya tipe <b>Aktif</b> yang muncul di pilihan baru.</p>
            </div>
            <div class="wca-phead__actions">
                <button class="wca-btn wca-btn--primary" @click="openCreate"><i class="bi bi-plus-lg"></i> Tipe Baru</button>
            </div>
        </div>

        <div class="wca-note wca-note--info">
            <i class="bi bi-info-circle"></i>
            <span>Tipe yang sudah dipakai lowongan <b>tidak bisa dihapus</b> — nonaktifkan saja supaya data lama tetap utuh dan tipe ini berhenti muncul sebagai pilihan baru.</span>
        </div>

        <div class="wca-stats">
            <div class="wca-stat">
                <div class="wca-stat__top"><span class="wca-stat__ico"><i class="bi bi-geo-alt"></i></span></div>
                <div class="wca-stat__num">{{ list.length }}</div>
                <div class="wca-stat__label">Total Tipe</div>
            </div>
            <div class="wca-stat">
                <div class="wca-stat__top"><span class="wca-stat__ico" style="background: rgba(16, 185, 129, 0.12); color: #059669"><i class="bi bi-check-circle"></i></span></div>
                <div class="wca-stat__num">{{ jmlAktif }}</div>
                <div class="wca-stat__label">Aktif (jadi pilihan)</div>
            </div>
            <div class="wca-stat">
                <div class="wca-stat__top"><span class="wca-stat__ico" style="background: rgba(148, 163, 184, 0.16); color: #475569"><i class="bi bi-eye-slash"></i></span></div>
                <div class="wca-stat__num">{{ list.length - jmlAktif }}</div>
                <div class="wca-stat__label">Nonaktif (tersembunyi)</div>
            </div>
            <div class="wca-stat">
                <div class="wca-stat__top"><span class="wca-stat__ico" style="background: rgba(99, 102, 241, 0.12); color: #4f46e5"><i class="bi bi-clipboard-data"></i></span></div>
                <div class="wca-stat__num">{{ totalDipakai }}</div>
                <div class="wca-stat__label">Pemakaian di MPP</div>
            </div>
        </div>

        <div class="wca-toolbar">
            <div class="wca-search2">
                <i class="bi bi-search"></i>
                <input v-model="q" type="text" placeholder="Cari nama / keterangan…" aria-label="Cari tipe lokasi kerja" @keyup.esc="q = ''" />
                <!-- Tombol bersihkan: tersembunyi sampai ada isian, jadi tidak menambah beban visual toolbar. -->
                <button v-if="q" class="mw-clear" type="button" title="Bersihkan pencarian" @click="q = ''">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
            <div class="wca-segt wca-segt--sm">
                <button v-for="t in tabs" :key="t.key" class="wca-segt__it" :class="{ on: tab === t.key }" @click="tab = t.key">
                    <i class="bi" :class="t.icon"></i> {{ t.label }}
                    <span class="wca-tabn">{{ t.count }}</span>
                </button>
            </div>
        </div>

        <div class="wca-card">
            <div class="wca-card__body--flush">
                <div v-loading="loading" class="wca-tablewrap">
                    <table class="wca-table">
                        <thead>
                            <tr>
                                <th style="width: 60px">Urutan</th>
                                <th>Tipe Lokasi Kerja</th>
                                <th>Keterangan</th>
                                <th style="width: 150px">
                                    <button class="mw-sort" :class="{ on: sortBy === 'dipakai' }" @click="setSort('dipakai')">
                                        Dipakai <i class="bi" :class="sortIcon('dipakai')"></i>
                                    </button>
                                </th>
                                <th class="mw-hide-sm" style="width: 190px">Dibuat</th>
                                <th style="width: 150px">Status</th>
                                <th style="width: 90px"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(w, i) in filtered" :key="w.id" :class="{ 'is-off': w.status !== 'AKTIF' }">
                                <td><span class="wca-badge wca-b--slate">{{ sortBy === 'urutan' ? i + 1 : urutanAsli(w) }}</span></td>
                                <td>
                                    <div class="mw-name">
                                        <span class="mw-ico"><i class="bi" :class="ikon(w.nama)"></i></span>
                                        <strong>{{ w.nama }}</strong>
                                    </div>
                                </td>
                                <td><span class="mw-desc">{{ w.keterangan || '—' }}</span></td>
                                <td>
                                    <span v-if="w.dipakai" class="wca-badge wca-b--indigo" :title="`Dipakai ${w.dipakai} lowongan`">{{ w.dipakai }} lowongan</span>
                                    <span v-else class="mw-muted">—</span>
                                </td>
                                <td class="mw-hide-sm"><AuditStamp :at="w.createdAt" :by="w.createdBy || 'SISTEM'" /></td>
                                <td>
                                    <div class="mw-status">
                                        <el-switch :model-value="w.status === 'AKTIF'" @change="(v) => setStatus(w, v)" />
                                        <span class="mw-status__lbl" :class="w.status === 'AKTIF' ? 'is-on' : 'is-off'">{{ w.status === 'AKTIF' ? 'Aktif' : 'Nonaktif' }}</span>
                                    </div>
                                </td>
                                <td>
                                    <div class="mw-actions">
                                        <button class="wca-iconbtn" title="Ubah" @click="openEdit(w)"><i class="bi bi-pencil"></i></button>
                                        <button
                                            class="wca-iconbtn wca-iconbtn--danger"
                                            :disabled="!!w.dipakai"
                                            :title="w.dipakai ? `Tidak bisa dihapus — dipakai ${w.dipakai} lowongan` : 'Hapus'"
                                            @click="askRemove(w)"
                                        >
                                            <i class="bi" :class="w.dipakai ? 'bi-lock' : 'bi-trash'"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="!loading && !filtered.length">
                                <td colspan="7">
                                    <div class="wca-empty">
                                        <i class="bi" :class="adaFilter ? 'bi-funnel' : 'bi-geo-alt'"></i>
                                        <h4>{{ adaFilter ? 'Tidak ada tipe lokasi kerja cocok' : 'Belum ada tipe lokasi kerja' }}</h4>
                                        <button v-if="adaFilter" class="wca-btn wca-btn--ghost mw-empty__btn" type="button" @click="resetFilter">
                                            <i class="bi bi-arrow-counterclockwise"></i> Reset filter
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <AdminModal
            :busy="saving" :show="show" icon="bi-geo-alt"
            :title="editingId ? 'Ubah Tipe Lokasi Kerja' : 'Tipe Lokasi Kerja Baru'"
            subtitle="Tipe lokasi kerja untuk MPP & lowongan"
            :save-label="editingId ? 'Perbarui' : 'Simpan Tipe'"
            foot-note="Nama harus unik — dipakai sebagai label pilihan di MPP."
            @close="show = false" @save="save"
        >
            <div class="wca-fsection">
                <div class="wca-fsection__label"><i class="bi bi-geo-alt"></i> Data Tipe Lokasi Kerja</div>
                <div class="wca-form">
                    <div>
                        <label class="wca-field-lbl">Nama Tipe Lokasi Kerja</label>
                        <el-input v-model="form.nama" maxlength="100" show-word-limit placeholder="mis. Remote (WFH)" @keyup.enter="save" />
                    </div>
                    <div>
                        <label class="wca-field-lbl">Keterangan <span class="mw-hint">(opsional — dijelaskan singkat, tampil sebagai pendamping nama)</span></label>
                        <el-input v-model="form.keterangan" type="textarea" :rows="2" maxlength="100" show-word-limit placeholder="mis. Bekerja dari rumah / mana saja (full remote)" />
                    </div>
                </div>
            </div>
            <div v-if="editingId && editingDipakai" class="wca-note wca-note--info mw-note-modal">
                <i class="bi bi-clipboard-data"></i>
                <span>Tipe ini dipakai <b>{{ editingDipakai }} lowongan</b>. Mengubah namanya ikut mengubah label pada data tersebut.</span>
            </div>
        </AdminModal>

        <ConfirmModal
            :show="delShow" :busy="deleting" title="Hapus Tipe Lokasi Kerja" confirm-label="Ya, Hapus Tipe"
            note="Tipe lokasi kerja akan dihapus permanen. Hanya tipe yang belum dipakai lowongan yang bisa dihapus."
            @cancel="delShow = false" @confirm="confirmDelete"
        >
            Yakin ingin menghapus <strong>{{ delTarget?.nama }}</strong>?
        </ConfirmModal>

        <transition name="wca-toast"><div v-if="toast" class="wca-toast"><i class="bi bi-check-circle-fill"></i> {{ toast }}</div></transition>
    </div>
</template>

<script>
import axios from 'axios';
import { Head } from '@inertiajs/vue3';
import AdminModal from '@career/AdminModal.vue';
import AuditStamp from '@career/AuditStamp.vue';
import ConfirmModal from '@career/ConfirmModal.vue';

const API = '/api/v1/master-workplace';
const CFG = { headers: { Accept: 'application/json' } };

// Ikon hanya hiasan sisi tampilan — tabelnya memang tidak punya kolom ikon.
// Dicocokkan dari nama supaya barisnya mudah dibedakan sekilas.
const IKON = [
    [/(on[\s-]?site|wfo|kantor)/i, 'bi-building'],
    [/(hybrid|campur)/i, 'bi-shuffle'],
    [/(remote|wfh|rumah)/i, 'bi-house-door'],
    [/(lapangan|field|proyek|project)/i, 'bi-cone-striped'],
    [/./, 'bi-geo-alt'],
];

export default {
    components: { Head, AdminModal, AuditStamp, ConfirmModal },
    data() {
        return {
            list: [], loading: false, q: '', tab: 'semua',
            sortBy: 'urutan', sortDir: 'asc', // 'urutan' = urutan asli (id) — bukan abjad.
            show: false, editingId: null, editingDipakai: 0, saving: false,
            form: { nama: '', keterangan: '' },
            delShow: false, delTarget: null, deleting: false,
            toast: '', tm: null,
        };
    },
    computed: {
        jmlAktif() {
            return this.list.filter((w) => w.status === 'AKTIF').length;
        },
        totalDipakai() {
            return this.list.reduce((n, w) => n + (w.dipakai || 0), 0);
        },
        adaFilter() {
            return this.q.trim() !== '' || this.tab !== 'semua';
        },
        tabs() {
            return [
                { key: 'semua', label: 'Semua', icon: 'bi-collection', count: this.list.length },
                { key: 'aktif', label: 'Aktif', icon: 'bi-check-circle', count: this.jmlAktif },
                { key: 'nonaktif', label: 'Nonaktif', icon: 'bi-eye-slash', count: this.list.length - this.jmlAktif },
            ];
        },
        filtered() {
            const s = this.q.trim().toLowerCase();
            const arah = this.sortDir === 'asc' ? 1 : -1;

            return this.list
                .filter((w) => {
                    if (this.tab === 'aktif' && w.status !== 'AKTIF') return false;
                    if (this.tab === 'nonaktif' && w.status === 'AKTIF') return false;
                    if (!s) return true;
                    return `${w.nama} ${w.keterangan || ''}`.toLowerCase().includes(s);
                })
                .slice()
                .sort((a, b) => {
                    if (this.sortBy === 'dipakai') return ((a.dipakai || 0) - (b.dipakai || 0)) * arah;
                    if (this.sortBy === 'nama') return String(a.nama).localeCompare(String(b.nama), 'id') * arah;
                    return 0; // 'urutan' — list sudah datang terurut dari server (ORDER BY Id).
                });
        },
    },
    mounted() {
        this.load();
    },
    methods: {
        ikon(nama) {
            const cocok = IKON.find(([pola]) => pola.test(nama || ''));
            return cocok ? cocok[1] : 'bi-geo-alt';
        },
        urutanAsli(w) {
            return this.list.findIndex((e) => e.id === w.id) + 1;
        },
        resetFilter() {
            this.q = '';
            this.tab = 'semua';
        },
        setSort(kolom) {
            if (this.sortBy === kolom) {
                this.sortDir = this.sortDir === 'asc' ? 'desc' : 'asc';
                return;
            }
            this.sortBy = kolom;
            this.sortDir = kolom === 'dipakai' ? 'desc' : 'asc';
        },
        sortIcon(kolom) {
            if (this.sortBy !== kolom) return 'bi-arrow-down-up';
            return this.sortDir === 'asc' ? 'bi-sort-down-alt' : 'bi-sort-down';
        },
        async load() {
            this.loading = true;
            try {
                const res = await axios.get(API, CFG);
                this.list = res.data.result || [];
            } catch (e) {
                this.notice('Gagal memuat data tipe lokasi kerja.');
            } finally {
                this.loading = false;
            }
        },
        openCreate() {
            this.editingId = null;
            this.editingDipakai = 0;
            this.form = { nama: '', keterangan: '' };
            this.show = true;
        },
        openEdit(w) {
            this.editingId = w.id;
            this.editingDipakai = w.dipakai || 0;
            this.form = { nama: w.nama, keterangan: w.keterangan || '' };
            this.show = true;
        },
        async save() {
            if (this.saving) return;
            if (!this.form.nama.trim()) return this.notice('Nama tipe lokasi kerja wajib diisi.');
            this.saving = true;
            const payload = { nama: this.form.nama.trim(), keterangan: this.form.keterangan.trim() };
            try {
                if (this.editingId) {
                    await axios.put(`${API}/${this.editingId}`, payload, CFG);
                    this.notice('Tipe lokasi kerja diperbarui.');
                } else {
                    await axios.post(API, payload, CFG);
                    this.notice('Tipe lokasi kerja ditambahkan.');
                }
                this.show = false;
                await this.load();
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal menyimpan tipe lokasi kerja.');
            } finally {
                this.saving = false;
            }
        },
        async setStatus(w, v) {
            const prev = w.status;
            w.status = v ? 'AKTIF' : 'NONAKTIF';
            try {
                await axios.patch(`${API}/${w.id}/toggle`, { aktif: v }, CFG);
                this.notice(`"${w.nama}" ${v ? 'diaktifkan' : 'dinonaktifkan'}.`);
            } catch (err) {
                w.status = prev;
                this.notice(err.response?.data?.message || 'Gagal mengubah status.');
            }
        },
        askRemove(w) {
            if (w.dipakai) return this.notice(`"${w.nama}" dipakai ${w.dipakai} lowongan — nonaktifkan saja.`);
            this.delTarget = w;
            this.delShow = true;
        },
        async confirmDelete() {
            if (this.deleting || !this.delTarget) return;
            this.deleting = true;
            try {
                await axios.delete(`${API}/${this.delTarget.id}`, CFG);
                this.notice('Tipe lokasi kerja dihapus.');
                this.delShow = false;
                this.delTarget = null;
                await this.load();
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal menghapus tipe lokasi kerja.');
            } finally {
                this.deleting = false;
            }
        },
        notice(m) {
            this.toast = m;
            if (this.tm) clearTimeout(this.tm);
            this.tm = setTimeout(() => (this.toast = ''), 3000);
        },
    },
};
</script>

<style scoped>
.mw-name { display: flex; align-items: center; gap: 10px; min-width: 0; }
.mw-ico {
    flex: 0 0 auto;
    width: 32px;
    height: 32px;
    border-radius: 10px;
    display: grid;
    place-items: center;
    background: rgba(99, 102, 241, 0.1);
    color: #4f46e5;
    font-size: 14px;
}
.mw-name strong { font-size: 13px; color: #0f1235; }
.mw-desc { color: #64748b; font-size: 12.5px; }
.mw-muted { color: #94a3b8; }
.mw-hint { font-weight: 400; color: #94a3b8; font-size: 11px; }
.mw-note-modal { margin-top: 0.9rem; }

.mw-sort {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    border: none;
    background: none;
    padding: 0;
    font: inherit;
    color: inherit;
    cursor: pointer;
}
.mw-sort i { font-size: 11px; color: #94a3b8; }
.mw-sort:hover, .mw-sort.on { color: #4f46e5; }
.mw-sort.on i { color: #4f46e5; }

.mw-status { display: flex; align-items: center; gap: 10px; --el-switch-on-color: #059669; }
.mw-status__lbl { font-size: 12px; font-weight: 700; }
.mw-status__lbl.is-on { color: #059669; }
.mw-status__lbl.is-off { color: #94a3b8; }

.mw-actions { display: flex; gap: 0.35rem; justify-content: flex-end; }
.mw-actions .wca-iconbtn:disabled { opacity: 0.45; cursor: not-allowed; }

.mw-clear {
    flex: none;
    display: grid;
    place-items: center;
    width: 20px;
    height: 20px;
    padding: 0;
    border: none;
    border-radius: 999px;
    background: rgba(148, 163, 184, 0.22);
    color: #475569;
    font-size: 9px;
    cursor: pointer;
}
.mw-clear:hover { background: rgba(239, 68, 68, 0.15); color: #dc2626; }
.mw-empty__btn { margin-top: 0.9rem; }

tr.is-off .mw-ico { background: rgba(148, 163, 184, 0.16); color: #64748b; }
tr.is-off .mw-name strong { color: #64748b; }

@media (max-width: 900px) {
    .mw-hide-sm { display: none; }
}
@media (max-width: 640px) {
    .mw-status__lbl { display: none; }
}
</style>
