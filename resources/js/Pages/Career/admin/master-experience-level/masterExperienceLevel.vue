<!-- WEB CAREER — Master Experience Level (tingkat pengalaman). Data via web route + ResponseHelper (axios). -->
<template>
    <Head><title>Master Experience Level - Web Career</title></Head>
    <div class="wca">
        <div class="wca-phead">
            <div>
                <h1>Master Experience Level</h1>
                <p>Tingkat pengalaman kerja (Fresh Graduate, Min. 1-2 Tahun, Min. 3-5 Tahun, …). Dipakai sebagai pilihan <b>Experience Level</b> saat menyusun MPP &amp; lowongan. Hanya level <b>Aktif</b> yang muncul di pilihan baru.</p>
            </div>
            <div class="wca-phead__actions">
                <button class="wca-btn wca-btn--primary" @click="openCreate"><i class="bi bi-plus-lg"></i> Level Baru</button>
            </div>
        </div>

        <div class="wca-note wca-note--info">
            <i class="bi bi-info-circle"></i>
            <span>Level yang sudah dipakai MPP <b>tidak bisa dihapus</b> — nonaktifkan saja supaya data lama tetap utuh dan level ini berhenti muncul sebagai pilihan baru.</span>
        </div>

        <div class="wca-stats">
            <div class="wca-stat">
                <div class="wca-stat__top"><span class="wca-stat__ico"><i class="bi bi-bar-chart-steps"></i></span></div>
                <div class="wca-stat__num">{{ list.length }}</div>
                <div class="wca-stat__label">Total Level</div>
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
                                <th style="width: 60px">Jenjang</th>
                                <th>Experience Level</th>
                                <th>Keterangan</th>
                                <th style="width: 130px">
                                    <button class="xpl-sort" :class="{ on: sortBy === 'dipakai' }" @click="setSort('dipakai')">
                                        Dipakai <i class="bi" :class="sortIcon('dipakai')"></i>
                                    </button>
                                </th>
                                <th class="xpl-hide-sm" style="width: 190px">Dibuat</th>
                                <th style="width: 150px">Status</th>
                                <th style="width: 90px"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(x, i) in filtered" :key="x.id" :class="{ 'is-off': x.status !== 'AKTIF' }">
                                <td><span class="wca-badge wca-b--slate">{{ sortBy === 'urutan' ? i + 1 : jenjang(x) }}</span></td>
                                <td>
                                    <div class="xpl-name">
                                        <span class="xpl-ico"><i class="bi" :class="ikon(x.nama)"></i></span>
                                        <strong>{{ x.nama }}</strong>
                                    </div>
                                </td>
                                <td><span class="xpl-desc">{{ x.keterangan || '—' }}</span></td>
                                <td>
                                    <span v-if="x.dipakai" class="wca-badge wca-b--indigo" :title="`Dipakai ${x.dipakai} data MPP`">{{ x.dipakai }} MPP</span>
                                    <span v-else class="xpl-muted">—</span>
                                </td>
                                <td class="xpl-hide-sm"><AuditStamp :at="x.createdAt" :by="x.createdBy || 'SISTEM'" /></td>
                                <td>
                                    <div class="xpl-status">
                                        <el-switch :model-value="x.status === 'AKTIF'" @change="(v) => setStatus(x, v)" />
                                        <span class="xpl-status__lbl" :class="x.status === 'AKTIF' ? 'is-on' : 'is-off'">{{ x.status === 'AKTIF' ? 'Aktif' : 'Nonaktif' }}</span>
                                    </div>
                                </td>
                                <td>
                                    <div class="xpl-actions">
                                        <button class="wca-iconbtn" title="Ubah" @click="openEdit(x)"><i class="bi bi-pencil"></i></button>
                                        <button
                                            class="wca-iconbtn wca-iconbtn--danger"
                                            :disabled="!!x.dipakai"
                                            :title="x.dipakai ? `Tidak bisa dihapus — dipakai ${x.dipakai} data MPP` : 'Hapus'"
                                            @click="askRemove(x)"
                                        >
                                            <i class="bi" :class="x.dipakai ? 'bi-lock' : 'bi-trash'"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="!loading && !filtered.length">
                                <td colspan="7">
                                    <div class="wca-empty">
                                        <i class="bi bi-bar-chart-steps"></i>
                                        <h4>{{ list.length ? 'Tidak ada experience level cocok' : 'Belum ada experience level' }}</h4>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <AdminModal
            :busy="saving" :show="show" icon="bi-bar-chart-steps"
            :title="editingId ? 'Ubah Experience Level' : 'Experience Level Baru'"
            subtitle="Tingkat pengalaman untuk MPP & lowongan"
            :save-label="editingId ? 'Perbarui' : 'Simpan Level'"
            foot-note="Nama harus unik — dipakai sebagai label pilihan di MPP."
            @close="show = false" @save="save"
        >
            <div class="wca-fsection">
                <div class="wca-fsection__label"><i class="bi bi-bar-chart-steps"></i> Data Experience Level</div>
                <div class="wca-form">
                    <div>
                        <label class="wca-field-lbl">Nama Experience Level</label>
                        <el-input v-model="form.nama" maxlength="100" show-word-limit placeholder="mis. Min. 1 - 2 Tahun" @keyup.enter="save" />
                    </div>
                    <div>
                        <label class="wca-field-lbl">Keterangan <span class="xpl-hint">(opsional — dijelaskan singkat, tampil sebagai pendamping nama)</span></label>
                        <el-input v-model="form.keterangan" type="textarea" :rows="2" maxlength="100" show-word-limit placeholder="mis. Pengalaman 1-3 tahun di bidang terkait" />
                    </div>
                </div>
            </div>
            <div v-if="editingId && editingDipakai" class="wca-note wca-note--info xpl-note-modal">
                <i class="bi bi-clipboard-data"></i>
                <span>Level ini dipakai <b>{{ editingDipakai }} data MPP</b>. Mengubah namanya ikut mengubah label pada data tersebut.</span>
            </div>
        </AdminModal>

        <ConfirmModal
            :show="delShow" :busy="deleting" title="Hapus Experience Level" confirm-label="Ya, Hapus Level"
            note="Experience level akan dihapus permanen. Hanya level yang belum dipakai MPP yang bisa dihapus."
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

const API = '/api/v1/master-experience-level';
const CFG = { headers: { Accept: 'application/json' } };

// Ikon hanya hiasan sisi tampilan — tabelnya memang tidak punya kolom ikon.
// Dicocokkan dari nama supaya barisnya mudah dibedakan sekilas.
const IKON = [
    [/(fresh|lulusan baru|entry)/i, 'bi-mortarboard'],
    [/(trainee|management trainee|mt)/i, 'bi-person-workspace'],
    [/(senior|manajer|manager|kepala|head)/i, 'bi-person-badge'],
    [/(direktur|director|eksekutif|executive)/i, 'bi-award'],
    [/./, 'bi-bar-chart-steps'],
];

export default {
    components: { Head, AdminModal, AuditStamp, ConfirmModal },
    data() {
        return {
            list: [], loading: false, q: '', tab: 'semua',
            sortBy: 'urutan', sortDir: 'asc', // 'urutan' = jenjang asli (id) — data ini berjenjang, bukan abjad.
            show: false, editingId: null, editingDipakai: 0, saving: false,
            form: { nama: '', keterangan: '' },
            delShow: false, delTarget: null, deleting: false,
            toast: '', tm: null,
        };
    },
    computed: {
        jmlAktif() {
            return this.list.filter((x) => x.status === 'AKTIF').length;
        },
        totalDipakai() {
            return this.list.reduce((n, x) => n + (x.dipakai || 0), 0);
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
                .filter((x) => {
                    if (this.tab === 'aktif' && x.status !== 'AKTIF') return false;
                    if (this.tab === 'nonaktif' && x.status === 'AKTIF') return false;
                    if (!s) return true;
                    return `${x.nama} ${x.keterangan || ''}`.toLowerCase().includes(s);
                })
                .slice()
                .sort((a, b) => {
                    if (this.sortBy === 'dipakai') return ((a.dipakai || 0) - (b.dipakai || 0)) * arah;
                    if (this.sortBy === 'nama') return String(a.nama).localeCompare(String(b.nama), 'id') * arah;
                    return 0; // 'urutan' — list sudah datang terurut jenjang dari server (ORDER BY Id).
                });
        },
    },
    mounted() {
        this.load();
    },
    methods: {
        ikon(nama) {
            const cocok = IKON.find(([pola]) => pola.test(nama || ''));
            return cocok ? cocok[1] : 'bi-bar-chart-steps';
        },
        jenjang(x) {
            return this.list.findIndex((e) => e.id === x.id) + 1;
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
                this.notice('Gagal memuat data experience level.');
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
        openEdit(x) {
            this.editingId = x.id;
            this.editingDipakai = x.dipakai || 0;
            this.form = { nama: x.nama, keterangan: x.keterangan || '' };
            this.show = true;
        },
        async save() {
            if (this.saving) return;
            if (!this.form.nama.trim()) return this.notice('Nama experience level wajib diisi.');
            this.saving = true;
            const payload = { nama: this.form.nama.trim(), keterangan: this.form.keterangan.trim() };
            try {
                if (this.editingId) {
                    await axios.put(`${API}/${this.editingId}`, payload, CFG);
                    this.notice('Experience level diperbarui.');
                } else {
                    await axios.post(API, payload, CFG);
                    this.notice('Experience level ditambahkan.');
                }
                this.show = false;
                await this.load();
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal menyimpan experience level.');
            } finally {
                this.saving = false;
            }
        },
        async setStatus(x, v) {
            const prev = x.status;
            x.status = v ? 'AKTIF' : 'NONAKTIF';
            try {
                await axios.patch(`${API}/${x.id}/toggle`, { aktif: v }, CFG);
                this.notice(`"${x.nama}" ${v ? 'diaktifkan' : 'dinonaktifkan'}.`);
            } catch (err) {
                x.status = prev;
                this.notice(err.response?.data?.message || 'Gagal mengubah status.');
            }
        },
        askRemove(x) {
            if (x.dipakai) return this.notice(`"${x.nama}" dipakai ${x.dipakai} data MPP — nonaktifkan saja.`);
            this.delTarget = x;
            this.delShow = true;
        },
        async confirmDelete() {
            if (this.deleting || !this.delTarget) return;
            this.deleting = true;
            try {
                await axios.delete(`${API}/${this.delTarget.id}`, CFG);
                this.notice('Experience level dihapus.');
                this.delShow = false;
                this.delTarget = null;
                await this.load();
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal menghapus experience level.');
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
.xpl-name { display: flex; align-items: center; gap: 10px; min-width: 0; }
.xpl-ico {
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
.xpl-name strong { font-size: 13px; color: #0f1235; }
.xpl-desc { color: #64748b; font-size: 12.5px; }
.xpl-muted { color: #94a3b8; }
.xpl-hint { font-weight: 400; color: #94a3b8; font-size: 11px; }
.xpl-note-modal { margin-top: 0.9rem; }

.xpl-sort {
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
.xpl-sort i { font-size: 11px; color: #94a3b8; }
.xpl-sort:hover, .xpl-sort.on { color: #4f46e5; }
.xpl-sort.on i { color: #4f46e5; }

.xpl-status { display: flex; align-items: center; gap: 10px; --el-switch-on-color: #059669; }
.xpl-status__lbl { font-size: 12px; font-weight: 700; }
.xpl-status__lbl.is-on { color: #059669; }
.xpl-status__lbl.is-off { color: #94a3b8; }

.xpl-actions { display: flex; gap: 0.35rem; justify-content: flex-end; }
.xpl-actions .wca-iconbtn:disabled { opacity: 0.45; cursor: not-allowed; }

tr.is-off .xpl-ico { background: rgba(148, 163, 184, 0.16); color: #64748b; }
tr.is-off .xpl-name strong { color: #64748b; }

@media (max-width: 900px) {
    .xpl-hide-sm { display: none; }
}
@media (max-width: 640px) {
    .xpl-status__lbl { display: none; }
}
</style>
