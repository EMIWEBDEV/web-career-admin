<!-- WEB CAREER — Master Kampus (per-modul). DATA via web route + ResponseHelper (axios), bukan props Inertia. -->
<template>
    <Head><title>Master Kampus - Web Career</title></Head>
    <div class="wca">
        <div class="wca-phead">
            <div>
                <h1>Master Kampus</h1>
                <p>
                    Daftar universitas/mitra pendidikan. Dipakai program MT campus-hiring (dan Internship) saat sumber
                    kandidat memakai <b>Kampus</b>.
                </p>
            </div>
            <div class="wca-phead__actions">
                <button class="wca-btn wca-btn--primary" @click="openCreate">
                    <i class="bi bi-plus-lg"></i> Kampus Baru
                </button>
            </div>
        </div>

        <div class="wca-toolbar">
            <div class="wca-search2">
                <i class="bi bi-search"></i>
                <input v-model="q" type="text" placeholder="Cari nama / singkatan / kota…" />
            </div>
        </div>

        <div class="wca-card">
            <div class="wca-card__body--flush">
                <div v-loading="loading" class="wca-tablewrap">
                    <table class="wca-table">
                        <thead>
                            <tr>
                                <th>Kampus</th>
                                <th>Kota</th>
                                <th>Akreditasi</th>
                                <th>Dibuat Oleh</th>
                                <th>Status</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="k in filtered" :key="k.id">
                                <td>
                                    <strong>{{ k.nama }}</strong>
                                    <span v-if="k.singkatan" class="wca-badge wca-b--slate" style="margin-left: 0.2rem">{{
                                        k.singkatan
                                    }}</span>
                                </td>
                                <td>
                                    <template v-if="k.kota"
                                        ><i class="bi bi-geo-alt" style="color: var(--indigo)"></i> {{ k.kota }}</template
                                    >
                                    <span v-else class="kmp-muted">—</span>
                                </td>
                                <td>
                                    <span
                                        v-if="k.akreditasi"
                                        class="wca-badge"
                                        :class="k.akreditasi === 'Unggul' ? 'wca-b--green' : 'wca-b--amber'"
                                        >{{ k.akreditasi }}</span
                                    >
                                    <span v-else class="kmp-muted">—</span>
                                </td>
                                <td><AuditStamp :by="k.createdBy" :at="k.createdAt" /></td>
                                <td>
                                    <div class="kmp-status">
                                        <el-switch :model-value="k.status === 'AKTIF'" @change="(v) => setStatus(k, v)" />
                                        <span
                                            class="kmp-status__lbl"
                                            :class="k.status === 'AKTIF' ? 'is-on' : 'is-off'"
                                            >{{ k.status === 'AKTIF' ? 'Aktif' : 'Nonaktif' }}</span
                                        >
                                    </div>
                                </td>
                                <td>
                                    <div class="kmp-actions">
                                        <button class="wca-iconbtn" title="Ubah" @click="openEdit(k)">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button
                                            class="wca-iconbtn wca-iconbtn--danger"
                                            title="Hapus"
                                            @click="askRemove(k)"
                                        >
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="!loading && !filtered.length">
                                <td colspan="6">
                                    <div class="wca-empty">
                                        <i class="bi bi-mortarboard"></i>
                                        <h4>Tidak ada kampus cocok</h4>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Modal Create/Edit -->
        <AdminModal
            :show="show"
            :title="editingId ? 'Ubah Kampus' : 'Kampus Baru'"
            subtitle="Universitas / mitra pendidikan"
            icon="bi-mortarboard"
            :save-label="editingId ? 'Perbarui' : 'Simpan Kampus'"
            @close="show = false"
            @save="save"
        >
            <div class="wca-fsection">
                <div class="wca-fsection__label"><i class="bi bi-mortarboard"></i> Data Kampus</div>
                <div class="wca-form">
                    <div class="wca-frow">
                        <div>
                            <label class="wca-field-lbl">Nama Kampus</label
                            ><el-input v-model="form.nama" placeholder="Universitas Sriwijaya" />
                        </div>
                        <div>
                            <label class="wca-field-lbl">Singkatan</label
                            ><el-input v-model="form.singkatan" placeholder="UNSRI" />
                        </div>
                    </div>
                    <div class="wca-frow">
                        <div>
                            <label class="wca-field-lbl">Kota</label
                            ><el-input v-model="form.kota" placeholder="Palembang" />
                        </div>
                        <div>
                            <label class="wca-field-lbl">Akreditasi</label>
                            <RefSelect type="akreditasi" v-model="form.akreditasi" clearable placeholder="Pilih akreditasi" />
                        </div>
                    </div>
                </div>
            </div>
        </AdminModal>

        <!-- Modal konfirmasi hapus -->
        <ConfirmModal
            :show="delShow"
            title="Hapus Kampus"
            :busy="deleting"
            confirm-label="Ya, Hapus Kampus"
            note="Data kampus akan dihapus permanen dari sistem."
            @cancel="delShow = false"
            @confirm="confirmDelete"
        >
            Yakin ingin menghapus kampus <strong>{{ delTarget?.nama }}</strong
            ><span v-if="delTarget?.singkatan"> ({{ delTarget?.singkatan }})</span>?
        </ConfirmModal>

        <transition name="wca-toast"
            ><div v-if="toast" class="wca-toast"><i class="bi bi-check-circle-fill"></i> {{ toast }}</div></transition
        >
    </div>
</template>

<script>
import axios from 'axios';
import { Head } from '@inertiajs/vue3';
import AdminModal from '@career/AdminModal.vue';
import ConfirmModal from '@career/ConfirmModal.vue';
import AuditStamp from '@career/AuditStamp.vue';
import RefSelect from '@career/RefSelect.vue';

const API = '/api/v1/master-kampus';
const CFG = { headers: { Accept: 'application/json' } };

export default {
    components: { Head, AdminModal, ConfirmModal, AuditStamp, RefSelect },
    data() {
        return {
            list: [],
            loading: false,
            q: '',
            show: false,
            editingId: null,
            saving: false,
            form: { nama: '', singkatan: '', kota: '', akreditasi: '' },
            delShow: false,
            delTarget: null,
            deleting: false,
            toast: '',
            tm: null,
        };
    },
    computed: {
        filtered() {
            const s = this.q.trim().toLowerCase();
            if (!s) return this.list;
            return this.list.filter((k) =>
                (`${k.nama} ${k.singkatan || ''} ${k.kota || ''}`).toLowerCase().includes(s)
            );
        },
    },
    mounted() {
        this.load();
    },
    methods: {
        async load() {
            this.loading = true;
            try {
                const res = await axios.get(API, CFG);
                this.list = res.data.result || [];
            } catch (e) {
                this.notice('Gagal memuat data kampus.');
            } finally {
                this.loading = false;
            }
        },
        openCreate() {
            this.editingId = null;
            this.form = { nama: '', singkatan: '', kota: '', akreditasi: '' };
            this.show = true;
        },
        openEdit(k) {
            this.editingId = k.id;
            this.form = {
                nama: k.nama,
                singkatan: k.singkatan || '',
                kota: k.kota || '',
                akreditasi: k.akreditasi || '',
            };
            this.show = true;
        },
        async save() {
            if (this.saving) return;
            if (!this.form.nama.trim()) return this.notice('Nama kampus wajib diisi.');
            this.saving = true;
            const f = this.form;
            const payload = { nama: f.nama, singkatan: f.singkatan, kota: f.kota, akreditasi: f.akreditasi };
            try {
                if (this.editingId) {
                    await axios.put(`${API}/${this.editingId}`, payload, CFG);
                    this.notice('Kampus diperbarui.');
                } else {
                    await axios.post(API, payload, CFG);
                    this.notice('Kampus berhasil ditambahkan.');
                }
                this.show = false;
                await this.load();
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal menyimpan kampus.');
            } finally {
                this.saving = false;
            }
        },
        async setStatus(k, v) {
            const prev = k.status;
            k.status = v ? 'AKTIF' : 'NONAKTIF';
            try {
                await axios.patch(`${API}/${k.id}/toggle`, { aktif: v }, CFG);
                this.notice(`Kampus "${k.nama}" ${v ? 'diaktifkan' : 'dinonaktifkan'}.`);
            } catch (e) {
                k.status = prev;
                this.notice(e.response?.data?.message || 'Gagal mengubah status.');
            }
        },
        askRemove(k) {
            this.delTarget = k;
            this.delShow = true;
        },
        async confirmDelete() {
            if (this.deleting || !this.delTarget) return;
            const k = this.delTarget;
            this.deleting = true;
            try {
                await axios.delete(`${API}/${k.id}`, CFG);
                this.notice('Kampus dihapus.');
                this.delShow = false;
                this.delTarget = null;
                await this.load();
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal menghapus kampus.');
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
/* Status toggle */
.kmp-status {
    display: flex;
    align-items: center;
    gap: 10px;
    --el-switch-on-color: #059669;
}
.kmp-status__lbl {
    font-size: 12px;
    font-weight: 700;
    letter-spacing: 0.01em;
}
.kmp-status__lbl.is-on {
    color: #059669;
}
.kmp-status__lbl.is-off {
    color: #94a3b8;
}
.kmp-muted {
    color: #94a3b8;
}
.kmp-actions {
    display: flex;
    gap: 0.35rem;
    justify-content: flex-end;
}

@media (max-width: 640px) {
    .kmp-status__lbl {
        display: none;
    }
}
</style>
