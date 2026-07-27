<!--
  WEB CAREER — Master Formulir (katalog). DATA dari DB via /api/v1/master-formulir.

  Halaman ini SENGAJA TIDAK punya penyusun skema. Pertanyaan formulir ditulis
  developer di berkas skema.js masing-masing formulir karena bentuk
  formulir rekrutmen tidak bisa ditebak dan menyusunnya lewat UI berisiko
  menghasilkan formulir rusak.

  Tugas admin di sini cuma dua: mendaftarkan formulir dan memilih komponen
  mana yang dipakai. Isinya bisa diperiksa lewat Pratinjau.
-->
<template>
    <Head><title>Master Formulir - Web Career</title></Head>
    <div class="wca">
        <div class="pkg-head">
            <div class="pkg-head__l">
                <div class="pkg-head__title">
                    <span class="pkg-head__ico"><i class="bi bi-input-cursor-text"></i></span>
                    <h1>Master Formulir</h1>
                </div>
                <p>
                    Daftarkan formulir dan pilih komponennya. Formulir dipilih di <b>tahap alur seleksi</b>, lalu
                    terpakai otomatis oleh program yang memakai alur itu.
                </p>
            </div>
            <button class="pkg-newbtn" @click="openCreate"><i class="bi bi-plus-lg"></i> Formulir Baru</button>
        </div>

        <div class="wca-note" style="margin-bottom: 1rem">
            <i class="bi bi-code-slash"></i>
            <span>
                Pertanyaan formulir ditulis di kode ({{ daftarKomponen.length }} komponen tersedia). Perlu formulir
                baru atau perubahan pertanyaan? Hubungi developer — bukan lewat halaman ini.
            </span>
        </div>

        <div v-loading="loading" class="pkg-list">
            <div v-for="f in list" :key="f.id" class="pkg-card" :class="{ open: open === f.id }">
                <!-- header row (pola standar "Program Kegiatan") -->
                <div class="pkg-row">
                    <button type="button" class="pkg-chev" :class="{ open: open === f.id }" title="Buka detail" @click="open = open === f.id ? null : f.id"><i class="bi bi-chevron-right"></i></button>
                    <div class="pkg-row__main">
                        <button type="button" class="pkg-row__titlebtn" @click="open = open === f.id ? null : f.id">
                            <span class="pkg-row__title">{{ f.nama }}</span>
                        </button>
                        <div class="pkg-row__meta">
                            <span class="pkg-code">{{ f.kode }}</span>
                            <template v-if="f.deskripsi"><span class="pkg-sep"></span><span class="pkg-mi">{{ f.deskripsi }}</span></template>
                        </div>
                        <div class="pkg-pills">
                            <span v-if="f.kategori" class="pkg-pill pkg-pill--violet"><i class="bi bi-tags"></i> {{ katLabel(f.kategori) }}</span>
                            <span v-if="info(f)" class="pkg-pill pkg-pill--green"><i class="bi bi-code-square"></i> {{ info(f).nama }}</span>
                            <span v-else class="pkg-pill pkg-pill--amber"><i class="bi bi-exclamation-triangle"></i> Komponen belum dipilih</span>
                            <span class="pkg-pill" :class="f.status === 'AKTIF' ? 'pkg-pill--green' : 'pkg-pill--slate'"><span class="pkg-pill__dot"></span> {{ f.status }}</span>
                        </div>
                    </div>
                    <div class="pkg-row__act" @click.stop>
                        <el-switch :model-value="f.status === 'AKTIF'" @change="(v) => setStatus(f, v)" />
                        <button class="pkg-ibtn" title="Ubah" @click="openEdit(f)"><i class="bi bi-pencil"></i></button>
                        <button class="pkg-ibtn pkg-ibtn--danger" title="Hapus" @click="askRemove(f)"><i class="bi bi-trash"></i></button>
                    </div>
                </div>

                <!-- creator strip -->
                <div class="pkg-creator">
                    <span class="pkg-creator__av" style="background:#6366f1">{{ initials(f.createdBy) }}</span>
                    <span class="pkg-creator__name">{{ f.createdBy || 'Sistem' }}</span>
                    <span class="pkg-creator__at"><i class="bi bi-clock"></i> {{ f.createdAt || '—' }}</span>
                </div>

                <!-- expanded detail -->
                <div v-if="open === f.id" class="pkg-detail">

                        <!-- Formulir tanpa komponen tidak bisa dirender kandidat,
                             jadi sengaja tidak ditawarkan di pilihan tahap alur. -->
                        <div v-if="!info(f)" class="wca-note wca-note--warn" style="margin-bottom: 0.8rem">
                            <i class="bi bi-info-circle-fill"></i>
                            <span>
                                Formulir ini <b>belum bisa dipakai</b>. Pilih komponennya lewat tombol
                                <b>Ubah</b> agar muncul di pilihan tahap Master Alur Seleksi.
                            </span>
                        </div>

                        <template v-else>
                            <div v-if="f.petunjuk" class="mfr-petunjuk">
                                <i class="bi bi-lightbulb"></i> {{ f.petunjuk }}
                            </div>

                            <div class="mfr-ring">
                                <div class="mfr-ring__main">
                                    <div class="mfr-ring__top">
                                        <span class="mfr-ring__kode">{{ f.komponen }}</span>
                                        <strong>{{ info(f).nama }}</strong>
                                        <span class="wca-badge wca-b--slate">{{ info(f).keterangan }}</span>
                                    </div>
                                    <div class="mfr-ring__meta">
                                        <span><i class="bi bi-list-ol"></i> {{ info(f).jumlahLangkah }} langkah</span>
                                        <span><i class="bi bi-ui-checks"></i> {{ info(f).jumlahField }} pertanyaan</span>
                                        <span v-if="hitungBerkas(f)"
                                            ><i class="bi bi-paperclip"></i> {{ hitungBerkas(f) }} berkas</span
                                        >
                                        <span v-if="hitungSaring(f)"
                                            ><i class="bi bi-funnel"></i> {{ hitungSaring(f) }} dapat disaring</span
                                        >
                                        <span v-if="hitungSyarat(f)"
                                            ><i class="bi bi-shuffle"></i> {{ hitungSyarat(f) }} bersyarat</span
                                        >
                                    </div>
                                </div>
                                <div class="mfr-ring__act">
                                    <button class="wca-btn wca-btn--soft wca-btn--sm" type="button" @click="openIsi(f)">
                                        <i class="bi bi-list-check"></i> Lihat Pertanyaan
                                    </button>
                                    <button
                                        class="wca-btn wca-btn--primary wca-btn--sm"
                                        type="button"
                                        @click="openPratinjau(f)"
                                    >
                                        <i class="bi bi-eyeglasses"></i> Pratinjau
                                    </button>
                                </div>
                            </div>
                        </template>
                </div>
            </div>
            <div v-if="!loading && !list.length" class="pkg-empty">
                <i class="bi bi-input-cursor-text"></i> Belum ada formulir — daftarkan formulir pertama, lalu pilih komponennya.
            </div>
        </div>

        <!-- ══════════ MODAL: identitas + pilih komponen ══════════ -->
        <AdminModal :busy="saving"
            :show="show"
            :title="editingId ? 'Ubah Formulir' : 'Daftarkan Formulir'"
            subtitle="Pertanyaannya ditulis di kode — di sini Anda memilih komponen mana yang dipakai."
            icon="bi-input-cursor-text"
            :save-label="editingId ? 'Perbarui' : 'Simpan'"
            @close="show = false"
            @save="save"
        >
            <div class="wca-fsection">
                <div class="wca-fsection__label"><i class="bi bi-tags"></i> Identitas</div>
                <div class="wca-form">
                    <div class="wca-frow">
                        <div>
                            <label class="wca-field-lbl">Nama Formulir</label>
                            <el-input v-model="form.nama" placeholder="mis. Formulir Pendaftaran MT" />
                        </div>
                        <div>
                            <label class="wca-field-lbl">Kategori</label>
                            <RefSelect type="talent" v-model="form.kategori" placeholder="Semua kategori" clearable />
                            <div class="mfr-hint">Kosongkan bila dipakai lintas kategori.</div>
                        </div>
                    </div>
                    <div>
                        <label class="wca-field-lbl">Deskripsi</label>
                        <el-input v-model="form.deskripsi" placeholder="Ringkasan singkat kegunaan formulir" />
                    </div>
                </div>
            </div>

            <div class="wca-fsection">
                <div class="wca-fsection__label"><i class="bi bi-code-square"></i> Komponen Formulir</div>
                <div class="wca-form">
                    <div>
                        <label class="wca-field-lbl">Pilih Komponen <span class="mfr-req">wajib</span></label>
                        <el-select v-model="form.komponen" style="width: 100%" placeholder="Pilih komponen">
                            <el-option v-for="k in daftarKomponen" :key="k.kode" :value="k.kode" :label="k.nama">
                                <div class="mfr-opt">
                                    <strong>{{ k.nama }}</strong>
                                    <small>{{ k.jumlahLangkah }} langkah · {{ k.jumlahField }} pertanyaan</small>
                                </div>
                            </el-option>
                        </el-select>
                    </div>

                    <div v-if="komponenTerpilih" class="mfr-pilih">
                        <i class="bi bi-info-circle-fill"></i>
                        <span>{{ komponenTerpilih.keterangan }}</span>
                    </div>

                    <div>
                        <label class="wca-field-lbl">Petunjuk untuk Admin</label>
                        <el-input
                            v-model="form.petunjuk"
                            type="textarea"
                            :rows="2"
                            placeholder="Kapan formulir ini dipakai, apa bedanya dengan formulir lain"
                        />
                        <div class="mfr-hint">Tidak dipakai sistem — murni penjelasan untuk sesama admin.</div>
                    </div>
                </div>
            </div>
        </AdminModal>

        <!-- ══════════ MODAL: daftar pertanyaan (ringkas, bisa dibaca cepat) ══════════ -->
        <AdminModal :busy="saving"
            :show="isiShow"
            :title="`Pertanyaan — ${isiFormulir?.nama || ''}`"
            :subtitle="`${isiInfo?.nama}. Ditulis di kode; ubah lewat developer.`"
            icon="bi-list-check"
            lg
            save-label="Tutup"
            @close="isiShow = false"
            @save="isiShow = false"
        >
            <div v-for="(L, iL) in isiSkema.langkah || []" :key="iL" class="mfr-lang">
                <div class="mfr-lang__head">
                    <span class="mfr-lang__no">{{ iL + 1 }}</span>
                    <strong>{{ L.judul }}</strong>
                    <small v-if="L.deskripsi">{{ L.deskripsi }}</small>
                </div>
                <div v-for="(B, iB) in L.bagian || []" :key="iB" class="mfr-bag">
                    <div class="mfr-bag__judul">
                        {{ B.judul }}
                        <span v-if="B.berulang" class="wca-badge wca-b--amber">berulang</span>
                    </div>
                    <table class="wca-table mfr-tbl">
                        <thead>
                            <tr>
                                <th style="width: 2.5rem">#</th>
                                <th>Pertanyaan</th>
                                <th style="width: 8rem">Bentuk</th>
                                <th style="width: 4.5rem">Wajib</th>
                                <th style="width: 13rem">Catatan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(F, iF) in B.field || []" :key="iF">
                                <td>{{ iF + 1 }}</td>
                                <td>
                                    <strong>{{ F.label }}</strong>
                                    <code class="mfr-key">{{ F.key }}</code>
                                </td>
                                <td>{{ tipeLabel(F.tipe) }}</td>
                                <td>
                                    <span
                                        class="wca-badge"
                                        :class="F.wajib ? 'wca-b--green' : 'wca-b--slate'"
                                        >{{ F.wajib ? 'Ya' : 'Tidak' }}</span
                                    >
                                </td>
                                <td class="mfr-catatan">
                                    <span v-if="F.tampil_jika" class="mfr-cond">
                                        <i class="bi bi-shuffle"></i>
                                        muncul jika <b>{{ F.tampil_jika.field }}</b> {{ F.tampil_jika.operator }}
                                        <b>{{ F.tampil_jika.nilai }}</b>
                                    </span>
                                    <span v-if="F.dapat_disaring" class="mfr-saring">
                                        <i class="bi bi-funnel"></i> dapat disaring
                                    </span>
                                    <span v-if="F.opsi?.length" class="mfr-opsi">{{ F.opsi.join(' / ') }}</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </AdminModal>

        <!-- ══════════ MODAL: pratinjau tampilan kandidat ══════════ -->
        <AdminModal :busy="saving"
            :show="praShow"
            :title="`Pratinjau — ${praFormulir?.nama || ''}`"
            :subtitle="`${praInfo?.nama}. Ini persis yang dilihat kandidat; isian di sini tidak disimpan.`"
            icon="bi-eyeglasses"
            lg
            save-label="Tutup"
            @close="praShow = false"
            @save="praShow = false"
        >
            <div class="mfr-pratool">
                <span class="mfr-pratool__lbl">
                    <i class="bi bi-person-badge"></i> Profil contoh (untuk field otomatis)
                </span>
                <el-input v-model="praProfil.nama" size="small" placeholder="Nama" style="max-width: 12rem" />
                <el-input v-model="praProfil.email" size="small" placeholder="Email" style="max-width: 13rem" />
                <el-input v-model="praProfil.hp" size="small" placeholder="No. WA" style="max-width: 10rem" />
                <span class="mfr-pratool__spacer"></span>
                <button class="wca-btn wca-btn--soft wca-btn--sm" type="button" @click="resetPratinjau">
                    <i class="bi bi-arrow-counterclockwise"></i> Reset Isian
                </button>
            </div>

            <div class="mfr-prabody">
                <!-- Komponen dipilih dari Komponen_Kode di DATABASE lewat registry. -->
                <component
                    :is="praKomponen"
                    v-if="praShow && praKomponen"
                    :key="praFormulir?.id + '-' + praReset"
                    v-model="praJawaban"
                    label-kirim="Kirim (pratinjau)"
                    @kirim="onPratinjauKirim"
                />
            </div>

            <details class="mfr-praJson">
                <summary>
                    <i class="bi bi-braces"></i> Lihat jawaban sebagai JSON
                    <small>(bentuk yang tersimpan di Jawaban_Json)</small>
                </summary>
                <pre>{{ JSON.stringify(praJawaban, null, 2) }}</pre>
            </details>
        </AdminModal>

        <ConfirmModal
            :show="delShow"
            title="Hapus Formulir"
            :busy="deleting"
            confirm-label="Ya, Hapus"
            note="Formulir akan dihapus permanen."
            @cancel="delShow = false"
            @confirm="confirmDelete"
        >
            Yakin ingin menghapus formulir <strong>{{ delTarget?.nama }}</strong
            >?
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
import AuditStamp from '@career/AuditStamp.vue';
import RefSelect from '@career/RefSelect.vue';
import {
    daftarFormulir,
    komponenFormulir,
    skemaFormulir,
    infoFormulir,
    semuaField,
    jawabanAwal,
} from '@career/formulir';

const API = '/api/v1/master-formulir';
const CFG = { headers: { Accept: 'application/json' } };

const TIPE_LABEL = {
    text: 'Teks singkat',
    textarea: 'Paragraf',
    number: 'Angka',
    date: 'Tanggal',
    select: 'Dropdown',
    radio: 'Pilihan satu',
    checkbox: 'Pilihan banyak',
    file: 'Unggah berkas',
    consent: 'Persetujuan',
    prefill: 'Terisi otomatis',
};

export default {
    components: { Head, AdminModal, ConfirmModal, AuditStamp, RefSelect },
    data() {
        return {
            list: [],
            loading: false,
            open: null,

            show: false,
            editingId: null,
            form: { nama: '', kategori: '', deskripsi: '', komponen: '', petunjuk: '' },

            isiShow: false,
            isiFormulir: null,

            praShow: false,
            praFormulir: null,
            praJawaban: {},
            praProfil: { nama: 'Budi Santoso', email: 'budi.santoso@gmail.com', hp: '0812-3456-7890' },
            praReset: 0,

            delShow: false,
            delTarget: null,
            deleting: false,

            saving: false,
            toast: '',
            tm: null,
        };
    },
    computed: {
        daftarKomponen() {
            return daftarFormulir();
        },
        komponenTerpilih() {
            return this.daftarKomponen.find((k) => k.kode === this.form.komponen) || null;
        },
        isiInfo() {
            return this.isiFormulir ? infoFormulir(this.isiFormulir.komponen) : null;
        },
        isiSkema() {
            return this.isiFormulir ? skemaFormulir(this.isiFormulir.komponen) : { langkah: [] };
        },
        praInfo() {
            return this.praFormulir ? infoFormulir(this.praFormulir.komponen) : null;
        },
        praKomponen() {
            return this.praFormulir ? komponenFormulir(this.praFormulir.komponen) : null;
        },
    },
    mounted() {
        this.load();
    },
    methods: {
        initials(name) {
            if (!name) return 'SY';
            const p = String(name).trim().split(/\s+/);
            return ((p[0]?.[0] || '') + (p[1]?.[0] || p[0]?.[1] || '')).toUpperCase() || 'SY';
        },
        katLabel(k) {
            return { REKRUTMEN: 'Rekrutmen', MT: 'Management Trainee', INTERNSHIP: 'Internship' }[k] || k;
        },
        tipeLabel(t) {
            return TIPE_LABEL[t] || t;
        },

        /** Info komponen; null bila kodenya kosong atau tidak dikenal registry. */
        info(f) {
            const i = infoFormulir(f.komponen);
            if (!i) return null;
            return {
                nama: i.nama,
                keterangan: i.keterangan,
                jumlahLangkah: (i.skema.langkah || []).length,
                jumlahField: semuaField(i.skema).length,
            };
        },
        fieldnya(f) {
            return semuaField(skemaFormulir(f.komponen));
        },
        hitungBerkas(f) {
            return this.fieldnya(f).filter((x) => x.tipe === 'file').length;
        },
        hitungSaring(f) {
            return this.fieldnya(f).filter((x) => x.dapat_disaring).length;
        },
        hitungSyarat(f) {
            return this.fieldnya(f).filter((x) => x.tampil_jika).length;
        },

        async load() {
            this.loading = true;
            try {
                const res = await axios.get(API, CFG);
                this.list = res.data.result || [];
            } catch (e) {
                this.notice('Gagal memuat data formulir.');
            } finally {
                this.loading = false;
            }
        },

        openCreate() {
            this.editingId = null;
            this.form = { nama: '', kategori: '', deskripsi: '', komponen: '', petunjuk: '' };
            this.show = true;
        },
        openEdit(f) {
            this.editingId = f.id;
            this.form = {
                nama: f.nama,
                kategori: f.kategori || '',
                deskripsi: f.deskripsi || '',
                komponen: f.komponen || '',
                petunjuk: f.petunjuk || '',
            };
            this.show = true;
        },
        async save() {
            if (this.saving) return;
            if (!this.form.nama.trim()) return this.notice('Nama formulir wajib diisi.');
            if (!this.form.komponen) return this.notice('Komponen formulir wajib dipilih.');
            this.saving = true;
            try {
                if (this.editingId) {
                    await axios.put(`${API}/${this.editingId}`, this.form, CFG);
                    this.notice('Formulir diperbarui.');
                } else {
                    await axios.post(API, this.form, CFG);
                    this.notice('Formulir didaftarkan.');
                }
                this.show = false;
                await this.load();
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal menyimpan.');
            } finally {
                this.saving = false;
            }
        },
        async setStatus(f, v) {
            const prev = f.status;
            f.status = v ? 'AKTIF' : 'NONAKTIF';
            try {
                await axios.patch(`${API}/${f.id}/toggle`, { aktif: v }, CFG);
            } catch (e) {
                f.status = prev;
                this.notice('Gagal mengubah status.');
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
                this.notice('Formulir dihapus.');
                this.delShow = false;
                this.delTarget = null;
                await this.load();
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal menghapus.');
            } finally {
                this.deleting = false;
            }
        },

        openIsi(f) {
            this.isiFormulir = f;
            this.isiShow = true;
        },

        openPratinjau(f) {
            this.praFormulir = f;
            this.praJawaban = jawabanAwal(skemaFormulir(f.komponen), this.praProfil);
            this.praShow = true;
        },
        resetPratinjau() {
            this.praJawaban = jawabanAwal(skemaFormulir(this.praFormulir.komponen), this.praProfil);
            // Ganti key komponen -> stepper Formulir 2 kembali ke langkah 1.
            this.praReset++;
        },
        onPratinjauKirim() {
            this.notice('Pratinjau: formulir lolos validasi. (Tidak ada data yang disimpan.)');
        },

        notice(x) {
            this.toast = x;
            if (this.tm) clearTimeout(this.tm);
            this.tm = setTimeout(() => (this.toast = ''), 3500);
        },
    },
};
</script>

<style scoped>
.mfr-head-act {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    margin-left: auto;
}
.mfr-meta {
    margin-bottom: 0.8rem;
}
.mfr-hint {
    font-size: 11.5px;
    line-height: 1.55;
    color: #64748b;
    margin-top: 0.3rem;
}
.mfr-req {
    font-weight: 600;
    font-size: 11px;
    color: #b45309;
    background: #fef3c7;
    border-radius: 999px;
    padding: 0.1rem 0.45rem;
    margin-left: 0.35rem;
}
.mfr-petunjuk {
    display: flex;
    gap: 0.45rem;
    font-size: 12px;
    color: #64748b;
    background: rgba(245, 158, 11, 0.08);
    border-radius: 10px;
    padding: 0.5rem 0.65rem;
    margin-bottom: 0.7rem;
}

/* ── Kartu komponen terpasang ─────────────────────────────── */
.mfr-ring {
    display: flex;
    align-items: flex-start;
    gap: 0.8rem;
    padding: 0.8rem;
    border: 1px solid rgba(11, 16, 51, 0.08);
    border-left: 3px solid #10b981;
    border-radius: 12px;
    background: linear-gradient(90deg, rgba(16, 185, 129, 0.05), transparent 45%);
}
.mfr-ring__main {
    flex: 1;
    min-width: 0;
}
.mfr-ring__top {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.4rem;
}
.mfr-ring__kode {
    font-weight: 800;
    font-size: 11px;
    color: #4338ca;
    background: rgba(79, 70, 229, 0.1);
    border-radius: 6px;
    padding: 0.1rem 0.4rem;
}
.mfr-ring__meta {
    display: flex;
    flex-wrap: wrap;
    gap: 0.3rem 0.9rem;
    margin-top: 0.35rem;
    font-size: 11.5px;
    color: #64748b;
}
.mfr-ring__act {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    flex-shrink: 0;
    flex-wrap: wrap;
    justify-content: flex-end;
}

.mfr-opt {
    display: flex;
    flex-direction: column;
    line-height: 1.35;
}
.mfr-opt small {
    color: #94a3b8;
    font-size: 11px;
}
.mfr-pilih {
    display: flex;
    gap: 0.45rem;
    font-size: 12px;
    color: #4338ca;
    background: rgba(79, 70, 229, 0.07);
    border-radius: 10px;
    padding: 0.55rem 0.7rem;
}

/* ── Daftar pertanyaan ────────────────────────────────────── */
.mfr-lang {
    margin-bottom: 1.1rem;
}
.mfr-lang__head {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 0.55rem;
}
.mfr-lang__no {
    width: 1.6rem;
    height: 1.6rem;
    display: grid;
    place-items: center;
    border-radius: 50%;
    background: linear-gradient(140deg, #4f46e5, #7c3aed);
    color: #fff;
    font-size: 11px;
    font-weight: 700;
}
.mfr-lang__head small {
    color: #94a3b8;
    font-size: 11.5px;
}
.mfr-bag {
    margin-bottom: 0.7rem;
}
.mfr-bag__judul {
    font-size: 12px;
    font-weight: 700;
    color: #475569;
    margin-bottom: 0.35rem;
}
.mfr-tbl {
    font-size: 12px;
}
.mfr-key {
    display: block;
    font-size: 10.5px;
    color: #4338ca;
    background: rgba(79, 70, 229, 0.07);
    border-radius: 5px;
    padding: 0 0.3rem;
    width: fit-content;
    margin-top: 0.15rem;
}
.mfr-catatan {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
    font-size: 11px;
}
.mfr-cond {
    color: #b45309;
}
.mfr-saring {
    color: #4338ca;
}
.mfr-opsi {
    color: #94a3b8;
}

/* ── Pratinjau ────────────────────────────────────────────── */
.mfr-pratool {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.4rem;
    padding: 0.6rem 0.75rem;
    margin-bottom: 0.9rem;
    border: 1px dashed rgba(11, 16, 51, 0.16);
    border-radius: 12px;
    background: rgba(248, 250, 252, 0.7);
}
.mfr-pratool__lbl {
    font-size: 11.5px;
    font-weight: 700;
    color: #475569;
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    margin-right: 0.3rem;
}
.mfr-pratool__spacer {
    flex: 1;
}
/* Latar bertitik supaya jelas ini "layar kandidat", bukan panel admin. */
.mfr-prabody {
    border: 1px solid rgba(11, 16, 51, 0.09);
    border-radius: 14px;
    padding: 1.2rem;
    background: #fff;
    background-image: radial-gradient(circle at 1px 1px, rgba(11, 16, 51, 0.05) 1px, transparent 0);
    background-size: 22px 22px;
}
.mfr-praJson {
    margin-top: 0.8rem;
    font-size: 11.5px;
}
.mfr-praJson summary {
    cursor: pointer;
    color: #4338ca;
    font-weight: 600;
    user-select: none;
}
.mfr-praJson summary small {
    color: #94a3b8;
    font-weight: 500;
}
.mfr-praJson pre {
    margin: 0.5rem 0 0;
    padding: 0.7rem;
    border-radius: 10px;
    background: #0f172a;
    color: #cbd5e1;
    font-size: 11px;
    line-height: 1.6;
    max-height: 16rem;
    overflow: auto;
}

@media (max-width: 900px) {
    .mfr-ring {
        flex-direction: column;
    }
    .mfr-ring__act {
        justify-content: flex-start;
    }
}
</style>
