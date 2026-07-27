<!-- WEB CAREER — Master Akun (Users). Tabs Pengguna/Pelamar vs Admin/Superadmin. DATA dari /api/v1/master-akun. -->
<template>
    <Head><title>Master Akun - Web Career</title></Head>
    <div class="wca">
        <div class="wca-phead">
            <div>
                <h1>Master Akun</h1>
                <p>Kelola akun sistem. Tab <b>Pengguna</b> = akun pelamar/kandidat, tab <b>Admin</b> = akun pengelola (Admin & Superadmin).</p>
            </div>
            <div class="wca-phead__actions">
                <button class="wca-btn wca-btn--primary" @click="openCreate">
                    <i class="bi bi-plus-lg"></i> {{ tab === 'pengguna' ? 'Tambah Pengguna' : 'Tambah Admin' }}
                </button>
            </div>
        </div>

        <div class="wca-segtab">
            <button class="wca-segtab__btn" :class="{ on: tab === 'pengguna' }" @click="switchTab('pengguna')">
                <i class="bi bi-people"></i> Pengguna / Pelamar <span class="wca-segtab__count">{{ penggunaList.length }}</span>
            </button>
            <button class="wca-segtab__btn" :class="{ on: tab === 'admin' }" @click="switchTab('admin')">
                <i class="bi bi-shield-lock"></i> Admin &amp; Superadmin <span class="wca-segtab__count">{{ adminList.length }}</span>
            </button>
        </div>

        <div class="wca-stats">
            <div class="wca-stat"><div class="wca-stat__top"><span class="wca-stat__ico"><i class="bi bi-person-lines-fill"></i></span></div><div class="wca-stat__num">{{ activeList.length }}</div><div class="wca-stat__label">Total {{ tab === 'pengguna' ? 'Pengguna' : 'Admin' }}</div></div>
            <div class="wca-stat"><div class="wca-stat__top"><span class="wca-stat__ico" style="background:rgba(16,185,129,.12);color:#059669"><i class="bi bi-check-circle"></i></span></div><div class="wca-stat__num">{{ countAktif }}</div><div class="wca-stat__label">Aktif</div></div>
            <div class="wca-stat"><div class="wca-stat__top"><span class="wca-stat__ico" style="background:rgba(148,163,184,.16);color:#475569"><i class="bi bi-slash-circle"></i></span></div><div class="wca-stat__num">{{ activeList.length - countAktif }}</div><div class="wca-stat__label">Nonaktif</div></div>
        </div>

        <div class="wca-toolbar">
            <div class="wca-search2"><i class="bi bi-search"></i><input v-model="q" type="text" placeholder="Cari nama / email…" /></div>
        </div>

        <div class="wca-card">
            <div v-loading="loading" class="wca-card__body--flush">
                <div class="wca-tablewrap">
                    <table class="wca-table">
                        <thead>
                            <tr><th>Nama / Email</th><th>Peran</th><th>Klasifikasi</th><th>Masa Berlaku</th><th>Status</th><th>Login Terakhir</th><th></th></tr>
                        </thead>
                        <tbody>
                            <tr v-for="a in filtered" :key="a.id">
                                <td>
                                    <div class="akun-idn">
                                        <span class="akun-ava" :class="avatarClass(a.role)">{{ inisial(a.nama) }}</span>
                                        <div><strong>{{ a.nama }}</strong><small>{{ a.email }}</small></div>
                                    </div>
                                </td>
                                <td><span class="wca-badge" :class="roleBadge(a.role)">{{ roleLabel(a.role) }}</span></td>
                                <td>{{ a.klasifikasi || '—' }}</td>
                                <td>
                                    <span v-if="!a.valid_until" class="wca-badge wca-b--slate">Permanen</span>
                                    <span v-else :class="{ 'akun-exp': isExpired(a.valid_until) }">{{ fmtDate(a.valid_until) }}</span>
                                </td>
                                <td>
                                    <div class="akun-status">
                                        <el-switch :model-value="a.status === 'AKTIF'" @change="(v) => setStatus(a, v)" />
                                        <span class="akun-status__lbl" :class="a.status === 'AKTIF' ? 'is-on' : 'is-off'">{{ a.status === 'AKTIF' ? 'Aktif' : 'Nonaktif' }}</span>
                                    </div>
                                </td>
                                <td><small class="akun-muted">{{ a.last_login_at ? fmtDateTime(a.last_login_at) : 'Belum pernah' }}</small></td>
                                <td>
                                    <div style="display:flex;gap:.35rem;justify-content:flex-end">
                                        <button class="wca-iconbtn" title="Ubah" @click="openEdit(a)"><i class="bi bi-pencil"></i></button>
                                        <button class="wca-iconbtn wca-iconbtn--danger" title="Hapus" @click="askRemove(a)"><i class="bi bi-trash"></i></button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="!filtered.length"><td colspan="7"><div class="wca-empty"><i class="bi bi-person-x"></i><h4>Belum ada {{ tab === 'pengguna' ? 'pengguna' : 'admin' }}</h4></div></td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <AdminModal :busy="saving" :show="show" :title="editingId ? 'Ubah Akun' : (tab === 'pengguna' ? 'Pengguna Baru' : 'Admin Baru')" subtitle="Akun akses sistem" icon="bi-person-badge" :save-label="editingId ? 'Perbarui' : 'Simpan Akun'" @close="show = false" @save="save">
            <div class="wca-fsection">
                <div class="wca-fsection__label"><i class="bi bi-person"></i> Identitas</div>
                <div class="wca-form">
                    <div class="wca-frow">
                        <div><label class="wca-field-lbl">Nama Lengkap</label><el-input v-model="form.nama" placeholder="Nama sesuai identitas" /></div>
                        <div><label class="wca-field-lbl">No. HP</label><el-input v-model="form.phone" placeholder="62812xxxxxxx" /></div>
                    </div>
                    <div><label class="wca-field-lbl">Email</label><el-input v-model="form.email" placeholder="nama@email.com" /></div>
                    <div>
                        <label class="wca-field-lbl">Kata Sandi <span v-if="editingId" class="akun-hint">(kosongkan bila tidak diganti)</span></label>
                        <el-input v-model="form.password" type="password" show-password :placeholder="editingId ? '••••••••' : 'Minimal 6 karakter'" />
                    </div>
                </div>
            </div>
            <div class="wca-fsection">
                <div class="wca-fsection__label"><i class="bi bi-shield-check"></i> Peran &amp; Akses</div>
                <div class="wca-form">
                    <div class="wca-frow">
                        <div>
                            <label class="wca-field-lbl">Peran</label>
                            <el-select filterable v-model="form.role" placeholder="Pilih peran" style="width:100%">
                                <el-option v-for="r in roleOptions" :key="r.value" :label="r.label" :value="r.value" />
                            </el-select>
                        </div>
                        <div><label class="wca-field-lbl">Klasifikasi (masa berlaku)</label><RefSelect type="klasifikasi-akun" v-model="form.klasifikasi" placeholder="Pilih klasifikasi" /></div>
                    </div>
                    <div>
                        <label class="wca-field-lbl">Status</label>
                        <el-select filterable v-model="form.status" placeholder="Pilih status" style="width:100%">
                            <el-option label="Aktif" value="AKTIF" />
                            <el-option label="Nonaktif" value="NONAKTIF" />
                        </el-select>
                    </div>
                </div>
            </div>
        </AdminModal>

        <ConfirmModal :show="delShow" title="Hapus Akun" :busy="deleting" confirm-label="Ya, Hapus Akun" note="Data akun akan dihapus permanen." @cancel="delShow = false" @confirm="confirmDelete">
            Yakin ingin menghapus akun <strong>{{ delTarget?.nama }}</strong> <span style="color:#7c81a3">({{ delTarget?.email }})</span>?
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

const API = '/api/v1/master-akun';
const CFG = { headers: { Accept: 'application/json' } };

export default {
    components: { Head, AdminModal, ConfirmModal, RefSelect },
    data() {
        return {
            all: [],
            loading: false,
            tab: 'pengguna',
            q: '',
            show: false,
            editingId: null,
            saving: false,
            form: { nama: '', email: '', phone: '', password: '', role: 'KANDIDAT', klasifikasi: '', status: 'AKTIF' },
            delShow: false,
            delTarget: null,
            deleting: false,
            toast: '',
            tm: null,
        };
    },
    computed: {
        penggunaList() { return this.all.filter((a) => a.role === 'KANDIDAT'); },
        adminList() { return this.all.filter((a) => a.role === 'ADMIN' || a.role === 'SUPERADMIN'); },
        activeList() { return this.tab === 'pengguna' ? this.penggunaList : this.adminList; },
        filtered() {
            const s = this.q.trim().toLowerCase();
            if (!s) return this.activeList;
            return this.activeList.filter((a) => (a.nama + ' ' + a.email).toLowerCase().includes(s));
        },
        countAktif() { return this.activeList.filter((a) => a.status === 'AKTIF').length; },
        roleOptions() {
            return this.tab === 'pengguna'
                ? [{ value: 'KANDIDAT', label: 'Pengguna (Pelamar)' }]
                : [{ value: 'ADMIN', label: 'Admin' }, { value: 'SUPERADMIN', label: 'Superadmin' }];
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
                this.all = res.data.result || [];
            } catch (e) {
                this.notice('Gagal memuat data akun.');
            } finally {
                this.loading = false;
            }
        },
        switchTab(t) { this.tab = t; this.q = ''; },
        inisial(nama) { return (nama || '?').trim().split(/\s+/).slice(0, 2).map((w) => w[0]).join('').toUpperCase(); },
        avatarClass(role) { return { KANDIDAT: 'ava-sky', ADMIN: 'ava-indigo', SUPERADMIN: 'ava-amber' }[role] || 'ava-slate'; },
        roleBadge(role) { return { KANDIDAT: 'wca-b--sky', ADMIN: 'wca-b--indigo', SUPERADMIN: 'wca-b--amber' }[role] || 'wca-b--slate'; },
        roleLabel(role) { return { KANDIDAT: 'Pengguna', ADMIN: 'Admin', SUPERADMIN: 'Superadmin' }[role] || role; },
        fmtDate(d) {
            if (!d) return '—';
            const dt = new Date(String(d).replace(' ', 'T'));
            return isNaN(dt.getTime()) ? d : dt.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
        },
        fmtDateTime(d) {
            if (!d) return '—';
            const dt = new Date(String(d).replace(' ', 'T'));
            return isNaN(dt.getTime()) ? d : dt.toLocaleString('id-ID', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
        },
        isExpired(d) {
            const dt = new Date(String(d).replace(' ', 'T'));
            return !isNaN(dt.getTime()) && dt < new Date(new Date().toDateString());
        },
        openCreate() {
            this.editingId = null;
            this.form = { nama: '', email: '', phone: '', password: '', role: this.tab === 'pengguna' ? 'KANDIDAT' : 'ADMIN', klasifikasi: '', status: 'AKTIF' };
            this.show = true;
        },
        openEdit(a) {
            this.editingId = a.id;
            this.form = { nama: a.nama, email: a.email, phone: a.phone || '', password: '', role: a.role, klasifikasi: a.klasifikasi || '', status: a.status };
            this.show = true;
        },
        async save() {
            if (this.saving) return;
            const f = this.form;
            if (!f.nama.trim() || !f.email.trim()) return this.notice('Nama & email wajib diisi.');
            if (!this.editingId && (!f.password || f.password.length < 6)) return this.notice('Kata sandi minimal 6 karakter.');
            if (f.password && f.password.length < 6) return this.notice('Kata sandi minimal 6 karakter.');
            this.saving = true;
            const payload = { nama: f.nama, email: f.email, phone: f.phone, password: f.password || '', role: f.role, klasifikasi: f.klasifikasi, status: f.status };
            try {
                if (this.editingId) {
                    await axios.put(`${API}/${this.editingId}`, payload, CFG);
                    this.notice('Akun diperbarui.');
                } else {
                    await axios.post(API, payload, CFG);
                    this.notice('Akun berhasil dibuat.');
                }
                this.show = false;
                await this.load();
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal menyimpan akun.');
            } finally {
                this.saving = false;
            }
        },
        async setStatus(a, v) {
            const prev = a.status;
            a.status = v ? 'AKTIF' : 'NONAKTIF';
            try {
                await axios.patch(`${API}/${a.id}/toggle`, { aktif: v }, CFG);
                this.notice(`Akun ${v ? 'diaktifkan' : 'dinonaktifkan'}.`);
            } catch (e) {
                a.status = prev;
                this.notice(e.response?.data?.message || 'Gagal mengubah status.');
            }
        },
        askRemove(a) { this.delTarget = a; this.delShow = true; },
        async confirmDelete() {
            if (this.deleting || !this.delTarget) return;
            const a = this.delTarget;
            this.deleting = true;
            try {
                await axios.delete(`${API}/${a.id}`, CFG);
                this.notice('Akun dihapus.');
                this.delShow = false;
                this.delTarget = null;
                await this.load();
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal menghapus akun.');
            } finally {
                this.deleting = false;
            }
        },
        notice(m) { this.toast = m; if (this.tm) clearTimeout(this.tm); this.tm = setTimeout(() => (this.toast = ''), 3000); },
    },
};
</script>

<style scoped>
.wca-segtab { display: inline-flex; gap: 4px; padding: 4px; margin-bottom: 1rem; background: #eef1f8; border: 1px solid rgba(11, 16, 51, .08); border-radius: 14px; }
.wca-segtab__btn { display: inline-flex; align-items: center; gap: 8px; padding: 9px 16px; border: 0; background: transparent; color: #5b5f86; font: 600 13.5px 'Plus Jakarta Sans', system-ui, sans-serif; border-radius: 10px; cursor: pointer; }
.wca-segtab__btn.on { background: #fff; color: #4f46e5; box-shadow: 0 2px 8px rgba(11, 16, 51, .08); }
.wca-segtab__count { min-width: 20px; padding: 1px 7px; border-radius: 999px; background: rgba(79, 70, 229, .12); color: #4338ca; font-size: 11px; font-weight: 700; }
.wca-segtab__btn:not(.on) .wca-segtab__count { background: rgba(11, 16, 51, .07); color: #64748b; }
.akun-idn { display: flex; align-items: center; gap: 10px; }
.akun-idn small { display: block; color: #7c81a3; font-size: 12px; }
.akun-ava { flex: 0 0 auto; width: 38px; height: 38px; display: grid; place-items: center; border-radius: 11px; color: #fff; font: 700 13px 'Plus Jakarta Sans', system-ui, sans-serif; }
.ava-sky { background: linear-gradient(135deg, #0ea5e9, #0284c7); }
.ava-indigo { background: linear-gradient(135deg, #6366f1, #4f46e5); }
.ava-amber { background: linear-gradient(135deg, #f59e0b, #d97706); }
.ava-slate { background: linear-gradient(135deg, #94a3b8, #64748b); }
.akun-muted { color: #7c81a3; font-size: 12px; }
.akun-exp { color: #dc2626; font-weight: 600; }
.akun-hint { color: #9096b8; font-weight: 500; font-size: 11.5px; }
.akun-status { display: flex; align-items: center; gap: 10px; --el-switch-on-color: #059669; }
.akun-status__lbl { font-size: 12px; font-weight: 700; }
.akun-status__lbl.is-on { color: #059669; }
.akun-status__lbl.is-off { color: #94a3b8; }
@media (max-width: 640px) {
    .wca-segtab { width: 100%; }
    .wca-segtab__btn { flex: 1; justify-content: center; padding: 9px 10px; font-size: 12.5px; }
    .akun-status__lbl { display: none; }
}
</style>
