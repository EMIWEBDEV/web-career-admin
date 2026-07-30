<!-- WEB CAREER — Master FAQ (per-modul, pola Master Kategori).
     DATA via web route + ResponseHelper (axios), bukan props Inertia.
     Dua entitas dalam satu halaman: PERTANYAAN dan KATEGORI. -->
<template>
    <Head><title>Master FAQ - Web Career</title></Head>
    <div class="wca">
        <div class="wca-phead">
            <div>
                <h1>Master FAQ</h1>
                <p>
                    Pertanyaan yang sering diajukan kandidat. Muncul di <b>accordion landing page</b> (yang ditandai)
                    dan seluruhnya di halaman publik <b>/karir/faq</b>.
                </p>
            </div>
            <div class="wca-phead__actions">
                <a class="wca-btn" href="/karir/faq" target="_blank" rel="noopener">
                    <i class="bi bi-box-arrow-up-right"></i> Lihat Halaman Publik
                </a>
                <button v-if="tab === 'faq'" class="wca-btn wca-btn--primary" @click="openCreate">
                    <i class="bi bi-plus-lg"></i> Pertanyaan Baru
                </button>
                <button v-else class="wca-btn wca-btn--primary" @click="openCreateKat">
                    <i class="bi bi-plus-lg"></i> Kategori Baru
                </button>
            </div>
        </div>

        <div class="wca-note wca-note--info">
            <i class="bi bi-info-circle"></i>
            <span>
                <b>Jawaban Ringkas</b> dipakai di accordion landing (teks biasa, singkat). <b>Jawaban Detail</b> hanya
                tampil di halaman <b>/karir/faq</b> — di sinilah penjelasan panjang, daftar langkah, dan tautan
                ditulis. Kolom <b>Landing</b> menentukan pertanyaan mana yang naik ke beranda.
            </span>
        </div>

        <div class="wca-segt">
            <button class="wca-segt__it" :class="{ on: tab === 'faq' }" @click="tab = 'faq'">
                <i class="bi bi-patch-question"></i> Pertanyaan <span class="wca-badge wca-b--slate">{{ list.length }}</span>
            </button>
            <button class="wca-segt__it" :class="{ on: tab === 'kategori' }" @click="tab = 'kategori'">
                <i class="bi bi-collection"></i> Kategori <span class="wca-badge wca-b--slate">{{ kategori.length }}</span>
            </button>
        </div>

        <!-- ════════════════ TAB: PERTANYAAN ════════════════ -->
        <template v-if="tab === 'faq'">
            <div class="wca-toolbar">
                <div class="wca-search2">
                    <i class="bi bi-search"></i><input v-model="q" type="text" placeholder="Cari pertanyaan…" />
                </div>
            </div>

            <div v-loading="loading" class="wca-card">
                <div class="wca-card__body--flush">
                    <div class="wca-tablewrap">
                        <table class="wca-table">
                            <thead>
                                <tr>
                                    <th style="width: 78px">Urutan</th>
                                    <th>Pertanyaan</th>
                                    <th>Kategori</th>
                                    <th style="width: 92px">Landing</th>
                                    <th style="width: 150px">Statistik</th>
                                    <th style="width: 80px">Status</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(f, i) in filtered" :key="f.id" :class="{ off: !f.aktif }">
                                    <td>
                                        <div class="mf-urut">
                                            <button
                                                class="wca-iconbtn"
                                                title="Naikkan"
                                                :disabled="!bisaNaik(i)"
                                                @click="pindah(i, -1)"
                                            >
                                                <i class="bi bi-chevron-up"></i>
                                            </button>
                                            <button
                                                class="wca-iconbtn"
                                                title="Turunkan"
                                                :disabled="!bisaTurun(i)"
                                                @click="pindah(i, 1)"
                                            >
                                                <i class="bi bi-chevron-down"></i>
                                            </button>
                                        </div>
                                    </td>
                                    <td>
                                        <div style="display: flex; align-items: flex-start; gap: 0.55rem">
                                            <span class="mf-ikon"><i class="bi" :class="f.ikon || 'bi-question-circle-fill'"></i></span>
                                            <div>
                                                <strong>{{ f.pertanyaan }}</strong>
                                                <span
                                                    v-if="!f.jawabanDetail"
                                                    class="wca-badge wca-b--amber"
                                                    style="margin-left: 0.3rem"
                                                    title="Halaman /karir/faq hanya menampilkan jawaban ringkas"
                                                    >Detail kosong</span
                                                ><br />
                                                <small style="color: var(--muted); font-weight: 700">{{
                                                    potong(f.jawabanRingkas)
                                                }}</small><br />
                                                <a
                                                    class="mf-slug"
                                                    :href="'/karir/faq#' + f.slug"
                                                    target="_blank"
                                                    rel="noopener"
                                                    title="Buka tautan langsung ke pertanyaan ini"
                                                >
                                                    <i class="bi bi-link-45deg"></i>#{{ f.slug }}
                                                </a>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span v-if="f.kategoriNama" class="wca-badge wca-b--indigo">{{ f.kategoriNama }}</span>
                                        <span v-else class="wca-badge wca-b--slate">Tanpa kategori</span>
                                        <div v-if="f.kategoriNonaktif" class="mf-warn">
                                            <i class="bi bi-exclamation-triangle-fill"></i> Kategori nonaktif — tidak
                                            tampil di publik
                                        </div>
                                    </td>
                                    <td>
                                        <el-switch
                                            :model-value="f.tampilLanding"
                                            @change="(v) => setLanding(f, v)"
                                        />
                                    </td>
                                    <td>
                                        <div class="mf-stat">
                                            <span title="Dilihat"><i class="bi bi-eye"></i> {{ f.dilihat }}</span>
                                            <span title="Membantu"><i class="bi bi-hand-thumbs-up"></i> {{ f.membantu }}</span>
                                            <span title="Tidak membantu"
                                                ><i class="bi bi-hand-thumbs-down"></i> {{ f.tidakMembantu }}</span
                                            >
                                        </div>
                                    </td>
                                    <td><el-switch :model-value="f.aktif" @change="(v) => setStatus(f, v)" /></td>
                                    <td>
                                        <div style="display: flex; gap: 0.5rem; align-items: center; justify-content: flex-end">
                                            <button class="wca-iconbtn" title="Ubah" @click="openEdit(f)">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <button
                                                class="wca-iconbtn"
                                                title="Selaraskan slug dengan pertanyaan (tautan lama akan mati)"
                                                @click="askSlug(f)"
                                            >
                                                <i class="bi bi-arrow-repeat"></i>
                                            </button>
                                            <button class="wca-iconbtn wca-iconbtn--danger" title="Hapus" @click="askRemove(f)">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="!loading && !filtered.length">
                                    <td colspan="7">
                                        <div class="wca-empty">
                                            <i class="bi bi-patch-question"></i>
                                            <h4>Belum ada pertanyaan</h4>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </template>

        <!-- ════════════════ TAB: KATEGORI ════════════════ -->
        <template v-else>
            <div v-loading="loadingKat" class="wca-card">
                <div class="wca-card__body--flush">
                    <div class="wca-tablewrap">
                        <table class="wca-table">
                            <thead>
                                <tr>
                                    <th style="width: 90px">Urutan</th>
                                    <th>Kategori</th>
                                    <th style="width: 110px">Pertanyaan</th>
                                    <th style="width: 80px">Status</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="k in kategori" :key="k.id" :class="{ off: !k.aktif }">
                                    <td><span class="wca-badge wca-b--slate">{{ k.urutan }}</span></td>
                                    <td>
                                        <div style="display: flex; align-items: flex-start; gap: 0.55rem">
                                            <span class="mf-ikon"><i class="bi" :class="k.ikon || 'bi-collection-fill'"></i></span>
                                            <div>
                                                <strong>{{ k.nama }}</strong>
                                                <span class="wca-badge wca-b--slate" style="margin-left: 0.3rem">{{ k.kode }}</span><br />
                                                <small style="color: var(--muted); font-weight: 700">{{
                                                    k.deskripsi || '—'
                                                }}</small>
                                            </div>
                                        </div>
                                    </td>
                                    <td><span class="wca-badge wca-b--indigo">{{ k.jumlahFaq }}</span></td>
                                    <td><el-switch :model-value="k.aktif" @change="(v) => setStatusKat(k, v)" /></td>
                                    <td>
                                        <div style="display: flex; gap: 0.5rem; align-items: center; justify-content: flex-end">
                                            <button class="wca-iconbtn" title="Ubah" @click="openEditKat(k)">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <button
                                                class="wca-iconbtn wca-iconbtn--danger"
                                                title="Hapus"
                                                @click="askRemoveKat(k)"
                                            >
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="!loadingKat && !kategori.length">
                                    <td colspan="5">
                                        <div class="wca-empty">
                                            <i class="bi bi-collection"></i>
                                            <h4>Belum ada kategori</h4>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </template>

        <!-- ════════════════ MODAL: PERTANYAAN ════════════════ -->
        <AdminModal
            xl
            :busy="saving"
            :show="show"
            :title="editingId ? 'Ubah Pertanyaan' : 'Pertanyaan Baru'"
            subtitle="Jawaban ringkas untuk landing, jawaban detail untuk halaman FAQ"
            icon="bi-patch-question"
            :save-label="editingId ? 'Perbarui' : 'Simpan Pertanyaan'"
            @close="show = false"
            @save="save"
        >
            <div class="wca-fsection">
                <div class="wca-fsection__label"><i class="bi bi-card-heading"></i> Pertanyaan</div>
                <div class="wca-form">
                    <div class="wca-frow">
                        <div>
                            <label class="wca-field-lbl">Kategori</label>
                            <el-select v-model="form.kategoriId" placeholder="Pilih kategori" clearable style="width: 100%">
                                <el-option
                                    v-for="k in kategoriAktif"
                                    :key="k.id"
                                    :label="k.nama"
                                    :value="k.id"
                                />
                            </el-select>
                        </div>
                        <div><label class="wca-field-lbl">Ikon</label><IconPicker v-model="form.ikon" /></div>
                    </div>
                    <div>
                        <label class="wca-field-lbl">Pertanyaan</label>
                        <el-input
                            v-model="form.pertanyaan"
                            maxlength="300"
                            show-word-limit
                            placeholder="Apakah Fresh Graduate bisa melamar di EVO Group?"
                        />
                    </div>
                    <div>
                        <label class="wca-field-lbl">Jawaban Ringkas (accordion landing)</label>
                        <el-input
                            v-model="form.jawabanRingkas"
                            type="textarea"
                            :rows="3"
                            maxlength="1000"
                            show-word-limit
                            placeholder="Satu-dua kalimat yang langsung menjawab."
                        />
                    </div>
                </div>
            </div>

            <div class="wca-fsection">
                <div class="wca-fsection__label"><i class="bi bi-file-richtext"></i> Jawaban Detail (halaman /karir/faq)</div>
                <div class="wca-form">
                    <EditorQuill
                        v-model="form.jawabanDetail"
                        hint="Format yang didukung: tebal, miring, garis bawah, coret, daftar, kutipan, tautan, dan judul kecil. Format lain akan dibuang otomatis saat disimpan."
                        placeholder="Penjelasan lengkap — boleh pakai daftar bernomor untuk alur, dan tautan ke halaman lain."
                    />
                </div>
            </div>

            <div class="wca-fsection">
                <div class="wca-fsection__label"><i class="bi bi-sliders"></i> Penempatan</div>
                <div class="wca-form">
                    <div class="wca-frow">
                        <div>
                            <label class="wca-field-lbl">Urutan</label>
                            <el-input v-model.number="form.urutan" type="number" :min="0" placeholder="0" />
                        </div>
                        <div>
                            <label class="wca-field-lbl">Tampilkan di landing page</label><br />
                            <el-switch v-model="form.tampilLanding" />
                        </div>
                    </div>
                    <div>
                        <label class="wca-field-lbl">Aktif (tampil di publik)</label><br />
                        <el-switch v-model="form.aktif" />
                    </div>
                </div>
            </div>
        </AdminModal>

        <!-- ════════════════ MODAL: KATEGORI ════════════════ -->
        <AdminModal
            :busy="savingKat"
            :show="showKat"
            :title="editingKatId ? 'Ubah Kategori' : 'Kategori Baru'"
            subtitle="Pengelompokan pertanyaan di halaman /karir/faq"
            icon="bi-collection"
            :save-label="editingKatId ? 'Perbarui' : 'Simpan Kategori'"
            @close="showKat = false"
            @save="saveKat"
        >
            <div class="wca-fsection">
                <div class="wca-fsection__label"><i class="bi bi-card-heading"></i> Identitas Kategori</div>
                <div class="wca-form">
                    <div class="wca-frow">
                        <div>
                            <label class="wca-field-lbl">Nama Kategori</label>
                            <el-input v-model="formKat.nama" maxlength="100" placeholder="Pendaftaran" />
                        </div>
                        <div><label class="wca-field-lbl">Ikon</label><IconPicker v-model="formKat.ikon" /></div>
                    </div>
                    <div>
                        <label class="wca-field-lbl">Deskripsi (subjudul section)</label>
                        <el-input
                            v-model="formKat.deskripsi"
                            type="textarea"
                            :rows="2"
                            maxlength="300"
                            show-word-limit
                            placeholder="Cara melamar, akun kandidat, dan kelengkapan formulir."
                        />
                    </div>
                    <div class="wca-frow">
                        <div>
                            <label class="wca-field-lbl">Urutan</label>
                            <el-input v-model.number="formKat.urutan" type="number" :min="0" placeholder="0" />
                        </div>
                        <div>
                            <label class="wca-field-lbl">Aktif</label><br />
                            <el-switch v-model="formKat.aktif" />
                        </div>
                    </div>
                </div>
            </div>
        </AdminModal>

        <!-- ════════════════ KONFIRMASI ════════════════ -->
        <ConfirmModal
            :show="delShow"
            title="Hapus Pertanyaan"
            :busy="deleting"
            confirm-label="Ya, Hapus"
            note="Pertanyaan disembunyikan dari publik; statistik dilihat & penilaian tetap tersimpan."
            @cancel="delShow = false"
            @confirm="confirmDelete"
        >
            Yakin ingin menghapus pertanyaan <strong>{{ delTarget?.pertanyaan }}</strong>?
        </ConfirmModal>

        <ConfirmModal
            :show="delKatShow"
            title="Hapus Kategori"
            :busy="deletingKat"
            confirm-label="Ya, Hapus"
            note="Kategori yang masih dipakai pertanyaan tidak bisa dihapus."
            @cancel="delKatShow = false"
            @confirm="confirmDeleteKat"
        >
            Yakin ingin menghapus kategori <strong>{{ delKatTarget?.nama }}</strong>?
        </ConfirmModal>

        <ConfirmModal
            :show="slugShow"
            title="Selaraskan Slug"
            :busy="slugBusy"
            confirm-label="Ya, Perbarui Slug"
            note="Tautan lama (#slug sebelumnya) berhenti membuka pertanyaan ini."
            @cancel="slugShow = false"
            @confirm="confirmSlug"
        >
            Slug akan dibuat ulang dari pertanyaan saat ini. Tautan
            <strong>#{{ slugTarget?.slug }}</strong> yang sudah dibagikan ke kandidat akan berhenti berfungsi.
            Lanjutkan?
        </ConfirmModal>

        <transition name="wca-toast">
            <div v-if="toast" class="wca-toast"><i class="bi bi-check-circle-fill"></i> {{ toast }}</div>
        </transition>
    </div>
</template>

<script>
import axios from 'axios';
import { Head } from '@inertiajs/vue3';
import AdminModal from '@career/AdminModal.vue';
import ConfirmModal from '@career/ConfirmModal.vue';
import IconPicker from '@career/IconPicker.vue';
import EditorQuill from '@career/EditorQuill.vue';

const API = '/api/v1/master-faq';
const API_KAT = '/api/v1/master-faq-kategori';
const CFG = { headers: { Accept: 'application/json' } };

const formKosong = () => ({
    kategoriId: '',
    ikon: '',
    pertanyaan: '',
    jawabanRingkas: '',
    jawabanDetail: '',
    urutan: 0,
    aktif: true,
    tampilLanding: false,
});

const formKatKosong = () => ({ nama: '', ikon: '', deskripsi: '', urutan: 0, aktif: true });

export default {
    components: { Head, AdminModal, ConfirmModal, IconPicker, EditorQuill },
    data() {
        return {
            tab: 'faq',
            list: [],
            kategori: [],
            loading: false,
            loadingKat: false,
            q: '',

            show: false,
            editingId: null,
            form: formKosong(),
            saving: false,

            showKat: false,
            editingKatId: null,
            formKat: formKatKosong(),
            savingKat: false,

            delShow: false,
            delTarget: null,
            deleting: false,

            delKatShow: false,
            delKatTarget: null,
            deletingKat: false,

            slugShow: false,
            slugTarget: null,
            slugBusy: false,

            toast: '',
            tm: null,
        };
    },
    computed: {
        filtered() {
            const s = this.q.trim().toLowerCase();
            if (!s) return this.list;

            return this.list.filter((f) =>
                [f.pertanyaan, f.jawabanRingkas, f.slug, f.kategoriNama]
                    .filter(Boolean)
                    .join(' ')
                    .toLowerCase()
                    .includes(s),
            );
        },
        kategoriAktif() {
            return this.kategori.filter((k) => k.aktif);
        },
    },
    mounted() {
        this.load();
        this.loadKategori();
    },
    methods: {
        // ── Muat data ──
        async load() {
            this.loading = true;
            try {
                const res = await axios.get(API, CFG);
                this.list = res.data.result || [];
            } catch (e) {
                this.notice('Gagal memuat data FAQ.');
            } finally {
                this.loading = false;
            }
        },
        async loadKategori() {
            this.loadingKat = true;
            try {
                const res = await axios.get(API_KAT, CFG);
                this.kategori = res.data.result || [];
            } catch (e) {
                this.notice('Gagal memuat kategori FAQ.');
            } finally {
                this.loadingKat = false;
            }
        },

        // ── Pertanyaan: modal ──
        openCreate() {
            this.editingId = null;
            this.form = formKosong();
            this.show = true;
        },
        openEdit(f) {
            this.editingId = f.id;
            this.form = {
                kategoriId: f.kategoriId || '',
                ikon: f.ikon || '',
                pertanyaan: f.pertanyaan,
                jawabanRingkas: f.jawabanRingkas || '',
                jawabanDetail: f.jawabanDetail || '',
                urutan: f.urutan || 0,
                aktif: f.aktif,
                tampilLanding: f.tampilLanding,
            };
            this.show = true;
        },
        async save() {
            if (this.saving) return;
            if (!this.form.pertanyaan.trim()) return this.notice('Pertanyaan wajib diisi.');
            if (!this.form.jawabanRingkas.trim()) return this.notice('Jawaban ringkas wajib diisi.');

            this.saving = true;
            const payload = {
                kategoriId: this.form.kategoriId || null,
                ikon: this.form.ikon || null,
                pertanyaan: this.form.pertanyaan,
                jawabanRingkas: this.form.jawabanRingkas,
                jawabanDetail: this.form.jawabanDetail || null,
                urutan: Number(this.form.urutan) || 0,
                aktif: this.form.aktif,
                tampilLanding: this.form.tampilLanding,
            };

            try {
                if (this.editingId) {
                    await axios.put(`${API}/${this.editingId}`, payload, CFG);
                    this.notice('Pertanyaan diperbarui.');
                } else {
                    await axios.post(API, payload, CFG);
                    this.notice('Pertanyaan ditambahkan.');
                }
                this.show = false;
                await Promise.all([this.load(), this.loadKategori()]);
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal menyimpan.');
            } finally {
                this.saving = false;
            }
        },

        // ── Pertanyaan: switch ──
        async setStatus(f, v) {
            const prev = f.aktif;
            f.aktif = v;
            try {
                await axios.patch(`${API}/${f.id}/toggle`, { aktif: v }, CFG);
                this.notice(`Pertanyaan ${v ? 'diaktifkan' : 'dinonaktifkan'}.`);
            } catch (e) {
                f.aktif = prev;
                this.notice('Gagal mengubah status.');
            }
        },
        async setLanding(f, v) {
            const prev = f.tampilLanding;
            f.tampilLanding = v;
            try {
                await axios.patch(`${API}/${f.id}/landing`, { tampil: v }, CFG);
                this.notice(v ? 'Ditampilkan di landing page.' : 'Disembunyikan dari landing page.');
            } catch (e) {
                f.tampilLanding = prev;
                this.notice('Gagal mengubah tampilan landing.');
            }
        },

        // ── Pertanyaan: urutan ──
        // Perpindahan dibatasi DI DALAM kategori yang sama: memindahkan lintas
        // kategori tidak punya arti — urutan hanya menentukan posisi di section
        // kategorinya sendiri.
        tetanggaSekategori(i, arah) {
            const kini = this.filtered[i];
            const calon = this.filtered[i + arah];
            if (!kini || !calon) return null;

            return (kini.kategoriId || null) === (calon.kategoriId || null) ? calon : null;
        },
        bisaNaik(i) {
            return !this.q && !!this.tetanggaSekategori(i, -1);
        },
        bisaTurun(i) {
            return !this.q && !!this.tetanggaSekategori(i, 1);
        },
        async pindah(i, arah) {
            if (!this.tetanggaSekategori(i, arah)) return;

            const urut = [...this.list];
            const iAsli = urut.findIndex((x) => x.id === this.filtered[i].id);
            const jAsli = urut.findIndex((x) => x.id === this.filtered[i + arah].id);
            if (iAsli < 0 || jAsli < 0) return;

            [urut[iAsli], urut[jAsli]] = [urut[jAsli], urut[iAsli]];
            this.list = urut;

            try {
                await axios.post(`${API}/reorder`, { ids: urut.map((x) => x.id) }, CFG);
                this.notice('Urutan disimpan.');
                await this.load();
            } catch (e) {
                this.notice('Gagal menyimpan urutan.');
                await this.load();
            }
        },

        // ── Pertanyaan: slug & hapus ──
        askSlug(f) {
            this.slugTarget = f;
            this.slugShow = true;
        },
        async confirmSlug() {
            if (this.slugBusy || !this.slugTarget) return;
            this.slugBusy = true;
            try {
                await axios.patch(`${API}/${this.slugTarget.id}/slug`, {}, CFG);
                this.notice('Slug diperbarui.');
                this.slugShow = false;
                this.slugTarget = null;
                await this.load();
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal memperbarui slug.');
            } finally {
                this.slugBusy = false;
            }
        },
        askRemove(f) {
            this.delTarget = f;
            this.delShow = true;
        },
        async confirmDelete() {
            if (this.deleting || !this.delTarget) return;
            this.deleting = true;
            try {
                await axios.delete(`${API}/${this.delTarget.id}`, CFG);
                this.notice('Pertanyaan dihapus.');
                this.delShow = false;
                this.delTarget = null;
                await Promise.all([this.load(), this.loadKategori()]);
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal menghapus.');
            } finally {
                this.deleting = false;
            }
        },

        // ── Kategori ──
        openCreateKat() {
            this.editingKatId = null;
            this.formKat = formKatKosong();
            this.showKat = true;
        },
        openEditKat(k) {
            this.editingKatId = k.id;
            this.formKat = {
                nama: k.nama,
                ikon: k.ikon || '',
                deskripsi: k.deskripsi || '',
                urutan: k.urutan || 0,
                aktif: k.aktif,
            };
            this.showKat = true;
        },
        async saveKat() {
            if (this.savingKat) return;
            if (!this.formKat.nama.trim()) return this.notice('Nama kategori wajib diisi.');

            this.savingKat = true;
            const payload = {
                nama: this.formKat.nama,
                ikon: this.formKat.ikon || null,
                deskripsi: this.formKat.deskripsi || null,
                urutan: Number(this.formKat.urutan) || 0,
                aktif: this.formKat.aktif,
            };

            try {
                if (this.editingKatId) {
                    await axios.put(`${API_KAT}/${this.editingKatId}`, payload, CFG);
                    this.notice('Kategori diperbarui.');
                } else {
                    await axios.post(API_KAT, payload, CFG);
                    this.notice('Kategori ditambahkan.');
                }
                this.showKat = false;
                await Promise.all([this.loadKategori(), this.load()]);
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal menyimpan.');
            } finally {
                this.savingKat = false;
            }
        },
        async setStatusKat(k, v) {
            const prev = k.aktif;
            k.aktif = v;
            try {
                await axios.patch(`${API_KAT}/${k.id}/toggle`, { aktif: v }, CFG);
                this.notice(`Kategori "${k.nama}" ${v ? 'diaktifkan' : 'dinonaktifkan'}.`);
                await this.load();
            } catch (e) {
                k.aktif = prev;
                this.notice('Gagal mengubah status.');
            }
        },
        askRemoveKat(k) {
            this.delKatTarget = k;
            this.delKatShow = true;
        },
        async confirmDeleteKat() {
            if (this.deletingKat || !this.delKatTarget) return;
            this.deletingKat = true;
            try {
                await axios.delete(`${API_KAT}/${this.delKatTarget.id}`, CFG);
                this.notice('Kategori dihapus.');
                this.delKatShow = false;
                this.delKatTarget = null;
                await this.loadKategori();
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal menghapus.');
            } finally {
                this.deletingKat = false;
            }
        },

        // ── Pembantu ──
        potong(t, n = 110) {
            const s = (t || '').trim();
            return s.length > n ? s.slice(0, n) + '…' : s;
        },
        notice(x) {
            this.toast = x;
            if (this.tm) clearTimeout(this.tm);
            this.tm = setTimeout(() => (this.toast = ''), 3000);
        },
    },
};
</script>

<style scoped>
.mf-urut {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
}
.mf-ikon {
    display: grid;
    place-items: center;
    width: 2rem;
    height: 2rem;
    flex: none;
    border-radius: 0.6rem;
    background: rgba(99, 102, 241, 0.1);
    color: #6366f1;
    font-size: 0.95rem;
}
.mf-slug {
    display: inline-flex;
    align-items: center;
    gap: 0.15rem;
    margin-top: 0.2rem;
    color: var(--muted, #64748b);
    font-size: 0.72rem;
    font-weight: 700;
    text-decoration: none;
}
.mf-slug:hover {
    color: #4f46e5;
    text-decoration: underline;
}
.mf-warn {
    margin-top: 0.3rem;
    color: #b45309;
    font-size: 0.72rem;
    font-weight: 700;
}
.mf-stat {
    display: flex;
    flex-wrap: wrap;
    gap: 0.55rem;
    color: var(--muted, #64748b);
    font-size: 0.78rem;
    font-weight: 700;
}
@media (max-width: 720px) {
    .wca-frow {
        grid-template-columns: 1fr;
    }
    .wca-tablewrap {
        overflow-x: auto;
    }
}
</style>
