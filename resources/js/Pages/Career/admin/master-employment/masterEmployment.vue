<!-- WEB CAREER — Master Employment (jenis ikatan kerja). Data via web route + ResponseHelper (axios). -->
<template>
    <Head><title>Master Employment - Web Career</title></Head>
    <div class="wca">
        <div class="wca-phead">
            <div>
                <h1>Master Employment</h1>
                <p>Jenis ikatan kerja (Full-time, Kontrak/PKWT, Magang, …). Dipakai sebagai pilihan <b>Employment Type</b> saat menyusun MPP &amp; lowongan. Hanya jenis <b>Aktif</b> yang muncul di pilihan baru.</p>
            </div>
            <div class="wca-phead__actions">
                <button class="wca-btn wca-btn--primary" @click="openCreate"><i class="bi bi-plus-lg"></i> Jenis Employment Baru</button>
            </div>
        </div>

        <div class="wca-note wca-note--info">
            <i class="bi bi-info-circle"></i>
            <span>Jenis yang sudah dipakai MPP <b>tidak bisa dihapus</b> — nonaktifkan saja supaya data lama tetap utuh dan jenis ini berhenti muncul sebagai pilihan baru.</span>
        </div>

        <div class="wca-stats">
            <div class="wca-stat">
                <div class="wca-stat__top"><span class="wca-stat__ico"><i class="bi bi-briefcase"></i></span></div>
                <div class="wca-stat__num">{{ list.length }}</div>
                <div class="wca-stat__label">Total Jenis</div>
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
                <input v-model="q" type="text" placeholder="Cari nama / keterangan…" />
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
                                <th>
                                    <button class="emp-sort" :class="{ on: sortBy === 'nama' }" @click="setSort('nama')">
                                        Jenis Employment <i class="bi" :class="sortIcon('nama')"></i>
                                    </button>
                                </th>
                                <th>Keterangan</th>
                                <th style="width: 130px">
                                    <button class="emp-sort" :class="{ on: sortBy === 'dipakai' }" @click="setSort('dipakai')">
                                        Dipakai <i class="bi" :class="sortIcon('dipakai')"></i>
                                    </button>
                                </th>
                                <th class="emp-hide-sm" style="width: 190px">Dibuat</th>
                                <th style="width: 150px">Status</th>
                                <th style="width: 90px"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="e in filtered" :key="e.id" :class="{ 'is-off': e.status !== 'AKTIF' }">
                                <td>
                                    <div class="emp-name">
                                        <span class="emp-ico"><i class="bi" :class="ikon(e.nama)"></i></span>
                                        <strong>{{ e.nama }}</strong>
                                    </div>
                                </td>
                                <td><span class="emp-desc">{{ e.keterangan || '—' }}</span></td>
                                <td>
                                    <span v-if="e.dipakai" class="wca-badge wca-b--indigo" :title="`Dipakai ${e.dipakai} data MPP`">{{ e.dipakai }} MPP</span>
                                    <span v-else class="emp-muted">—</span>
                                </td>
                                <td class="emp-hide-sm"><AuditStamp :at="e.createdAt" :by="e.createdBy || 'SISTEM'" /></td>
                                <td>
                                    <div class="emp-status">
                                        <el-switch :model-value="e.status === 'AKTIF'" @change="(v) => setStatus(e, v)" />
                                        <span class="emp-status__lbl" :class="e.status === 'AKTIF' ? 'is-on' : 'is-off'">{{ e.status === 'AKTIF' ? 'Aktif' : 'Nonaktif' }}</span>
                                    </div>
                                </td>
                                <td>
                                    <div class="emp-actions">
                                        <button class="wca-iconbtn" title="Ubah" @click="openEdit(e)"><i class="bi bi-pencil"></i></button>
                                        <button
                                            class="wca-iconbtn wca-iconbtn--danger"
                                            :disabled="!!e.dipakai"
                                            :title="e.dipakai ? `Tidak bisa dihapus — dipakai ${e.dipakai} data MPP` : 'Hapus'"
                                            @click="askRemove(e)"
                                        >
                                            <i class="bi" :class="e.dipakai ? 'bi-lock' : 'bi-trash'"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="!loading && !filtered.length">
                                <td colspan="6">
                                    <div class="wca-empty">
                                        <i class="bi bi-briefcase"></i>
                                        <h4>{{ list.length ? 'Tidak ada jenis employment cocok' : 'Belum ada jenis employment' }}</h4>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <AdminModal
            :busy="saving" :show="show" icon="bi-briefcase"
            :title="editingId ? 'Ubah Jenis Employment' : 'Jenis Employment Baru'"
            subtitle="Jenis ikatan kerja untuk MPP & lowongan"
            :save-label="editingId ? 'Perbarui' : 'Simpan Jenis'"
            foot-note="Nama harus unik — dipakai sebagai label pilihan di MPP."
            @close="show = false" @save="save"
        >
            <div class="wca-fsection">
                <div class="wca-fsection__label"><i class="bi bi-briefcase"></i> Data Employment</div>
                <div class="wca-form">
                    <div>
                        <label class="wca-field-lbl">Nama Employment</label>
                        <el-input v-model="form.nama" maxlength="100" show-word-limit placeholder="mis. Full-time" @keyup.enter="save" />
                    </div>
                    <div>
                        <label class="wca-field-lbl">Keterangan <span class="emp-hint">(opsional — dijelaskan singkat, tampil sebagai pendamping nama)</span></label>
                        <el-input v-model="form.keterangan" type="textarea" :rows="2" maxlength="100" show-word-limit placeholder="mis. Pekerjaan tetap, 40 jam/minggu" />
                    </div>
                </div>
            </div>
            <div v-if="editingId && editingDipakai" class="wca-note wca-note--info emp-note-modal">
                <i class="bi bi-clipboard-data"></i>
                <span>Jenis ini dipakai <b>{{ editingDipakai }} data MPP</b>. Mengubah namanya ikut mengubah label pada data tersebut.</span>
            </div>
        </AdminModal>

        <ConfirmModal
            :show="delShow" :busy="deleting" title="Hapus Jenis Employment" confirm-label="Ya, Hapus Jenis"
            note="Jenis employment akan dihapus permanen. Hanya jenis yang belum dipakai MPP yang bisa dihapus."
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

const API = '/api/v1/master-employment';
const CFG = { headers: { Accept: 'application/json' } };

// Ikon hanya hiasan sisi tampilan — tabelnya memang tidak punya kolom ikon.
// Dicocokkan dari nama supaya barisnya mudah dibedakan sekilas.
const IKON = [
    [/(intern|magang|apprentice)/i, 'bi-mortarboard'],
    [/(part.?time|paruh)/i, 'bi-clock-history'],
    [/(project|proyek)/i, 'bi-kanban'],
    [/(contract|kontrak|pkwt)/i, 'bi-file-earmark-text'],
    [/(freelance|lepas)/i, 'bi-person-workspace'],
    [/(outsourc|alih daya)/i, 'bi-people'],
    [/(full.?time|tetap|pkwtt)/i, 'bi-briefcase-fill'],
];

export default {
    components: { Head, AdminModal, AuditStamp, ConfirmModal },
    data() {
        return {
            list: [], loading: false, q: '', tab: 'semua',
            sortBy: 'nama', sortDir: 'asc',
            show: false, editingId: null, editingDipakai: 0, saving: false,
            form: { nama: '', keterangan: '' },
            delShow: false, delTarget: null, deleting: false,
            toast: '', tm: null,
        };
    },
    computed: {
        jmlAktif() {
            return this.list.filter((e) => e.status === 'AKTIF').length;
        },
        totalDipakai() {
            return this.list.reduce((n, e) => n + (e.dipakai || 0), 0);
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
                .filter((e) => {
                    if (this.tab === 'aktif' && e.status !== 'AKTIF') return false;
                    if (this.tab === 'nonaktif' && e.status === 'AKTIF') return false;
                    if (!s) return true;
                    return `${e.nama} ${e.keterangan || ''}`.toLowerCase().includes(s);
                })
                .slice()
                .sort((a, b) => {
                    if (this.sortBy === 'dipakai') return ((a.dipakai || 0) - (b.dipakai || 0)) * arah;
                    return String(a.nama).localeCompare(String(b.nama), 'id') * arah;
                });
        },
    },
    mounted() {
        this.load();
    },
    methods: {
        ikon(nama) {
            const cocok = IKON.find(([pola]) => pola.test(nama || ''));
            return cocok ? cocok[1] : 'bi-briefcase';
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
                this.notice('Gagal memuat data employment.');
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
        openEdit(e) {
            this.editingId = e.id;
            this.editingDipakai = e.dipakai || 0;
            this.form = { nama: e.nama, keterangan: e.keterangan || '' };
            this.show = true;
        },
        async save() {
            if (this.saving) return;
            if (!this.form.nama.trim()) return this.notice('Nama employment wajib diisi.');
            this.saving = true;
            const payload = { nama: this.form.nama.trim(), keterangan: this.form.keterangan.trim() };
            try {
                if (this.editingId) {
                    await axios.put(`${API}/${this.editingId}`, payload, CFG);
                    this.notice('Jenis employment diperbarui.');
                } else {
                    await axios.post(API, payload, CFG);
                    this.notice('Jenis employment ditambahkan.');
                }
                this.show = false;
                await this.load();
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal menyimpan jenis employment.');
            } finally {
                this.saving = false;
            }
        },
        async setStatus(e, v) {
            const prev = e.status;
            e.status = v ? 'AKTIF' : 'NONAKTIF';
            try {
                await axios.patch(`${API}/${e.id}/toggle`, { aktif: v }, CFG);
                this.notice(`"${e.nama}" ${v ? 'diaktifkan' : 'dinonaktifkan'}.`);
            } catch (err) {
                e.status = prev;
                this.notice(err.response?.data?.message || 'Gagal mengubah status.');
            }
        },
        askRemove(e) {
            if (e.dipakai) return this.notice(`"${e.nama}" dipakai ${e.dipakai} data MPP — nonaktifkan saja.`);
            this.delTarget = e;
            this.delShow = true;
        },
        async confirmDelete() {
            if (this.deleting || !this.delTarget) return;
            this.deleting = true;
            try {
                await axios.delete(`${API}/${this.delTarget.id}`, CFG);
                this.notice('Jenis employment dihapus.');
                this.delShow = false;
                this.delTarget = null;
                await this.load();
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal menghapus jenis employment.');
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
.emp-name { display: flex; align-items: center; gap: 10px; min-width: 0; }
.emp-ico {
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
.emp-name strong { font-size: 13px; color: #0f1235; }
.emp-desc { color: #64748b; font-size: 12.5px; }
.emp-muted { color: #94a3b8; }
.emp-hint { font-weight: 400; color: #94a3b8; font-size: 11px; }
.emp-note-modal { margin-top: 0.9rem; }

.emp-sort {
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
.emp-sort i { font-size: 11px; color: #94a3b8; }
.emp-sort:hover, .emp-sort.on { color: #4f46e5; }
.emp-sort.on i { color: #4f46e5; }

.emp-status { display: flex; align-items: center; gap: 10px; --el-switch-on-color: #059669; }
.emp-status__lbl { font-size: 12px; font-weight: 700; }
.emp-status__lbl.is-on { color: #059669; }
.emp-status__lbl.is-off { color: #94a3b8; }

.emp-actions { display: flex; gap: 0.35rem; justify-content: flex-end; }
.emp-actions .wca-iconbtn:disabled { opacity: 0.45; cursor: not-allowed; }

tr.is-off .emp-ico { background: rgba(148, 163, 184, 0.16); color: #64748b; }
tr.is-off .emp-name strong { color: #64748b; }

@media (max-width: 900px) {
    .emp-hide-sm { display: none; }
}
@media (max-width: 640px) {
    .emp-status__lbl { display: none; }
}
</style>
