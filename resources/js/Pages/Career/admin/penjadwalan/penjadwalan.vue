<template>
    <div class="wca-page">
        <!-- Tab kategori: dari Master Talent Acquisition, bukan hardcode -->
        <div class="wca-segtab">
            <button
                v-for="t in opsi.talent"
                :key="t.kode"
                type="button"
                class="wca-segtab__btn"
                :class="{ 'is-active': kategori === t.kode }"
                @click="gantiKategori(t.kode)"
            >
                {{ t.nama }}
            </button>
        </div>

        <el-alert v-if="notice" :title="notice" :type="noticeType" show-icon closable class="wca-alert" @close="notice = ''" />

        <div class="wca-split">
            <!-- ============ KONFIGURASI TES ============ -->
            <el-card shadow="never" class="wca-card">
                <template #header>
                    <div class="wca-cardtitle"><i class="bi bi-sliders"></i> Konfigurasi Tes</div>
                </template>

                <el-form label-position="top">
                    <el-form-item label="Program">
                        <el-select v-model="form.programId" filterable placeholder="Pilih program" class="wca-w" @change="onProgram">
                            <el-option v-for="p in programKategori" :key="p.id" :label="p.nama" :value="p.id">
                                <span>{{ p.nama }}</span>
                                <span class="wca-opttag">{{ p.alurNama || 'alur belum diatur' }}</span>
                            </el-option>
                        </el-select>
                        <div v-if="programTerpilih && !programTerpilih.alurId" class="wca-hint wca-hint--warn">
                            Program ini belum terhubung ke alur seleksi. Atur di Program Kegiatan.
                        </div>
                    </el-form-item>

                    <!-- Tes DIAMBIL DARI ALUR PROGRAM, bukan daftar jenis tes global.
                         Yang dipilih admin = tahap yang juga dilihat kandidat di
                         portalnya, jadi tidak mungkin memilih tes yang tak ada di
                         alur program ini. -->
                    <el-form-item :label="tesAlur.length > 1 ? 'Ujian Mana yang Dijadwalkan' : 'Ujian yang Dijadwalkan'">
                        <!-- Satu program bisa punya BEBERAPA ujian online di alurnya
                             (mis. Psikotes di tahap 2 dan Psikotes 2 di tahap 4), dan
                             masing-masing butuh paket + jendela waktunya sendiri. Jadi
                             pilihan ini tak bisa dihilangkan — tapi kalau ujiannya cuma
                             satu, ia dipilih otomatis dan tampil sebagai keterangan. -->
                        <div v-if="tesAlur.length === 1" class="wca-hint" style="margin-top:-.2rem">
                            <i class="bi bi-info-circle"></i>
                            Alur program ini hanya punya satu ujian online — sudah dipilih otomatis.
                        </div>

                        <div v-loading="memuatTes" class="wca-teslist">
                            <el-empty
                                v-if="!memuatTes && !tesAlur.length"
                                :image-size="52"
                                :description="alasanTes || 'Pilih program terlebih dahulu'"
                            />

                            <button
                                v-for="t in tesAlur"
                                :key="t.tahapUrutan + '-' + t.tesUrutan"
                                type="button"
                                class="wca-tes"
                                :class="{ 'is-active': tesTerpilihKey === t.tahapUrutan + '-' + t.tesUrutan }"
                                @click="pilihTes(t)"
                            >
                                <span class="wca-tes__no">{{ t.tahapUrutan }}</span>
                                <span class="wca-tes__info">
                                    <span class="wca-tes__nama">{{ t.tahapLabel }}<template v-if="t.multi"> › {{ t.tesLabel }}</template></span>
                                    <span class="wca-tes__meta">
                                        <span v-if="t.tipeNama"><i class="bi bi-pc-display"></i> {{ t.tipeNama }}</span>
                                        <span v-if="t.peran === 'INFORMATIF'"><i class="bi bi-info-circle"></i> informatif</span>
                                    </span>
                                </span>
                                <el-tag size="small" :type="t.menunggu ? 'warning' : 'info'" effect="light">
                                    {{ t.menunggu }} menunggu
                                </el-tag>
                            </button>
                        </div>
                    </el-form-item>

                    <el-form-item label="Nama Ujian / Paket Tes">
                        <el-input v-model="cariPaket" placeholder="Cari paket tes…" clearable size="small" class="wca-search" @input="debounceCari">
                            <template #prefix><i class="bi bi-search"></i></template>
                        </el-input>

                        <div v-loading="memuatPaket" class="wca-paketgrid">
                            <el-empty v-if="!memuatPaket && !paket.length" :image-size="60" description="Paket tes tidak ditemukan" />

                            <button
                                v-for="p in paket"
                                :key="p.Id_Master_Ujian"
                                type="button"
                                class="wca-paketcard"
                                :class="{ 'is-active': form.idMasterUjian === p.Id_Master_Ujian }"
                                @click="pilihPaket(p)"
                            >
                                <div class="wca-paketcard__head">
                                    <span class="wca-paketcard__nama">{{ p.Nama_Ujian }}</span>
                                    <i v-if="form.idMasterUjian === p.Id_Master_Ujian" class="bi bi-check-circle-fill"></i>
                                </div>
                                <div class="wca-paketcard__kode">{{ p.Kode_Paket }}</div>
                                <div class="wca-paketcard__alat">
                                    <span v-for="d in p.detail" :key="d.id_paket_detail" class="wca-chip">{{ d.nama_indikator }}</span>
                                </div>
                                <div class="wca-paketcard__meta">
                                    <span><i class="bi bi-grid-3x3-gap"></i> {{ p.Jumlah_Soal }} soal</span>
                                    <span><i class="bi bi-diagram-3"></i> {{ p.Tingkatan }}</span>
                                </div>
                            </button>
                        </div>
                    </el-form-item>

                    <div class="wca-grid2">
                        <el-form-item label="Waktu Mulai">
                            <el-date-picker
                                v-model="form.waktuMulai"
                                type="datetime"
                                placeholder="Tanggal & jam mulai"
                                format="DD MMM YYYY HH:mm"
                                value-format="YYYY-MM-DD HH:mm:ss"
                                class="wca-w"
                            />
                        </el-form-item>
                        <el-form-item label="Waktu Berakhir">
                            <el-date-picker
                                v-model="form.waktuAkhir"
                                type="datetime"
                                placeholder="Tanggal & jam berakhir"
                                format="DD MMM YYYY HH:mm"
                                value-format="YYYY-MM-DD HH:mm:ss"
                                class="wca-w"
                            />
                        </el-form-item>
                    </div>

                    <div class="wca-hint"><i class="bi bi-shield-lock"></i> Kandidat terpilih menerima token + OTP unik untuk mengakses tes pada jendela waktu ini.</div>

                    <el-button type="primary" class="wca-w" :disabled="!bisaGenerate" :loading="menyimpan" @click="simpan">
                        <i class="bi bi-stars"></i>&nbsp; Generate {{ form.peserta.length }} Sesi Tes
                    </el-button>
                </el-form>
            </el-card>

            <!-- ============ PILIH KANDIDAT ============ -->
            <el-card shadow="never" class="wca-card">
                <template #header>
                    <div class="wca-cardhead">
                        <div class="wca-cardtitle"><i class="bi bi-people"></i> Pilih Kandidat</div>
                        <el-tag type="primary" effect="light">{{ form.peserta.length }} / {{ kandidat.length }}</el-tag>
                    </div>
                </template>

                <div class="wca-kandhead">
                    <el-checkbox :model-value="semuaTercentang" :indeterminate="sebagianTercentang" @change="toggleSemua">Pilih semua</el-checkbox>
                    <el-input v-model="cariKandidat" placeholder="Cari nama / posisi…" clearable size="small" class="wca-search" @input="debounceKandidat">
                        <template #prefix><i class="bi bi-search"></i></template>
                    </el-input>
                </div>

                <div v-loading="memuatKandidat" class="wca-kandlist">
                    <!-- Kosong itu wajar; yang tidak boleh adalah kosong tanpa sebab.
                         Pesan dari server menjelaskan kenapa (tahap belum sampai,
                         jenis tes lain, atau semua sudah dijadwalkan). -->
                    <el-empty v-if="!memuatKandidat && !kandidat.length" :image-size="60" :description="alasanKandidat || 'Tidak ada kandidat'" />

                    <label v-for="k in kandidat" :key="k.kode" class="wca-kand" :class="{ 'is-active': form.peserta.includes(k.kode) }">
                        <el-checkbox :model-value="form.peserta.includes(k.kode)" @change="toggleKandidat(k.kode)" />
                        <span class="wca-avatar">{{ inisial(k.nama) }}</span>
                        <span class="wca-kandinfo">
                            <span class="wca-kandnama">{{ k.nama }}</span>
                            <span class="wca-kandmeta">{{ k.posisi || '—' }} · {{ k.kode }}</span>
                            <!-- Satu tahap bisa berisi beberapa tes — sebutkan yang mana. -->
                            <span v-if="k.tes" class="wca-kandtes">
                                <i class="bi bi-diagram-3"></i> {{ k.tahap }} › {{ k.tes }}
                            </span>
                        </span>
                    </label>
                </div>
            </el-card>
        </div>

        <!-- ============ DAFTAR PENJADWALAN ============ -->
        <el-card shadow="never" class="wca-card wca-card--full">
            <template #header>
                <div class="wca-cardhead">
                    <div class="wca-cardtitle"><i class="bi bi-calendar-check"></i> Daftar Penjadwalan</div>
                    <el-tag effect="light">{{ daftar.length }} penjadwalan</el-tag>
                </div>
            </template>

            <div v-loading="memuat">
                <el-empty v-if="!memuat && !daftar.length" :image-size="70" description="Belum ada penjadwalan" />

                <div v-for="j in daftar" :key="j.id" class="wca-jadwal">
                    <div class="wca-jadwalhead">
                        <div>
                            <span class="wca-kode">{{ j.kode }}</span>
                            <div class="wca-nama">{{ j.nama }}</div>
                            <div class="wca-meta">
                                <span v-if="j.program">{{ j.program }}</span>
                                <span v-if="j.alur">{{ j.alur }}</span>
                                <span>{{ j.jumlahPeserta }} peserta</span>
                            </div>
                        </div>
                        <div class="wca-jadwalact">
                            <el-tag :type="j.status === 'BERJALAN' ? 'success' : 'info'" effect="light">{{ j.status }}</el-tag>
                            <el-button v-if="tahapTerjadwal(j)" text type="primary" title="Ubah jadwal tes" @click="bukaEdit(j)"><i class="bi bi-pencil-square"></i></el-button>
                            <el-button text type="danger" title="Hapus penjadwalan" @click="konfirmHapus(j)"><i class="bi bi-trash"></i></el-button>
                        </div>
                    </div>

                    <div class="wca-tahap">
                        <span v-for="t in j.tahap" :key="t.urutan" class="wca-step" :class="{ 'wca-step--hcl': t.kirimHclearn }">
                            <b>{{ t.urutan }}</b> {{ t.label }}
                            <em v-if="t.status && t.status !== 'BELUM'">· {{ t.status }}</em>
                        </span>
                    </div>

                    <el-table v-if="j.peserta.length" :data="j.peserta" size="small">
                        <el-table-column prop="nama" label="Kandidat" min-width="150" />
                        <el-table-column prop="posisi" label="Posisi" min-width="140" />
                        <el-table-column label="Kirim" width="110">
                            <template #default="{ row }">
                                <el-tag size="small" :type="row.statusKirim === 'TERKIRIM' ? 'success' : row.statusKirim === 'GAGAL' ? 'danger' : 'info'">
                                    {{ row.statusKirim }}
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column prop="statusPengerjaan" label="Pengerjaan" width="130" />
                        <el-table-column prop="nilai" label="Nilai" width="80" />
                        <el-table-column label="Token / Tautan" min-width="180">
                            <template #default="{ row }">
                                <el-button v-if="row.linkUjian" text type="primary" size="small" @click="salin(row.linkUjian)">
                                    {{ row.shortToken }} · salin
                                </el-button>
                                <span v-else class="wca-muted">{{ row.pesanError || '—' }}</span>
                            </template>
                        </el-table-column>
                    </el-table>
                </div>
            </div>
        </el-card>

        <ConfirmModal
            :show="hapusTampil"
            title="Hapus Penjadwalan"
            :subtitle="`Hapus ${target?.kode || ''}? Token yang sudah terbit di HCLearn tidak ikut terhapus.`"
            danger
            confirm-label="Ya, Hapus"
            @confirm="hapus"
            @cancel="hapusTampil = false"
        />

        <!-- Edit jendela waktu tes -->
        <el-dialog v-model="editTampil" title="Ubah Jadwal Tes" width="440px" align-center>
            <div v-if="editTarget" class="wca-editbox">
                <p class="wca-editnama"><b>{{ editTarget.kode }}</b> — {{ editTarget.namaUjian || editTarget.nama }}</p>
                <p class="wca-editnote"><i class="bi bi-info-circle"></i> Jendela waktu kandidat & token ujian di HCLearn ikut diperbarui. Tidak bisa diubah bila peserta sudah mengerjakan.</p>
                <label class="wca-editlbl">Waktu Mulai</label>
                <el-date-picker v-model="editMulai" type="datetime" placeholder="Tanggal & jam mulai" format="DD MMM YYYY HH:mm" value-format="YYYY-MM-DD HH:mm:ss" style="width: 100%" />
                <label class="wca-editlbl">Waktu Berakhir</label>
                <el-date-picker v-model="editAkhir" type="datetime" placeholder="Tanggal & jam berakhir" format="DD MMM YYYY HH:mm" value-format="YYYY-MM-DD HH:mm:ss" style="width: 100%" />
            </div>
            <template #footer>
                <el-button @click="editTampil = false">Batal</el-button>
                <el-button type="primary" :loading="editSibuk" :disabled="!editMulai || !editAkhir" @click="simpanEdit">Simpan Perubahan</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script>
import axios from 'axios';
import ConfirmModal from '@career/ConfirmModal.vue';

export default {
    name: 'Penjadwalan',
    components: { ConfirmModal },
    data() {
        return {
            memuat: false,
            memuatPaket: false,
            memuatKandidat: false,
            memuatTes: false,
            menyimpan: false,
            kategori: '',
            opsi: { talent: [], program: [] },
            paket: [],
            // Tes/tahap yang bisa dijadwalkan pada alur program terpilih.
            tesAlur: [],
            alasanTes: '',
            kandidat: [],
            // Penjelasan dari server saat daftar kandidat kosong.
            alasanKandidat: '',
            daftar: [],
            cariPaket: '',
            cariKandidat: '',
            timerPaket: null,
            timerKandidat: null,
            hapusTampil: false,
            target: null,
            editTampil: false,
            editTarget: null,
            editMulai: '',
            editAkhir: '',
            editSibuk: false,
            notice: '',
            noticeType: 'success',
            form: { programId: null, tahapUrutan: null, tesUrutan: null, idMasterUjian: null, namaUjian: '', waktuMulai: '', waktuAkhir: '', peserta: [] },
        };
    },
    computed: {
        programKategori() {
            return this.opsi.program.filter((p) => !this.kategori || p.kategori === this.kategori);
        },
        programTerpilih() {
            return this.opsi.program.find((p) => p.id === this.form.programId) || null;
        },
        tesTerpilihKey() {
            return this.form.tahapUrutan ? `${this.form.tahapUrutan}-${this.form.tesUrutan}` : '';
        },
        semuaTercentang() {
            return this.kandidat.length > 0 && this.kandidat.every((k) => this.form.peserta.includes(k.kode));
        },
        sebagianTercentang() {
            return this.form.peserta.length > 0 && !this.semuaTercentang;
        },
        bisaGenerate() {
            const f = this.form;
            return !!(f.programId && f.tahapUrutan && f.idMasterUjian && f.waktuMulai && f.waktuAkhir && f.peserta.length);
        },
    },
    mounted() {
        this.muatOpsi();
        this.muatPaket();
        this.muat();
    },
    methods: {
        beritahu(pesan, tipe = 'success') {
            this.notice = pesan;
            this.noticeType = tipe;
        },
        inisial(nama) {
            return (nama || '?').split(' ').slice(0, 2).map((n) => n[0]).join('').toUpperCase();
        },
        async muatOpsi() {
            try {
                const res = await axios.get('/api/v1/penjadwalan/opsi', { headers: { Accept: 'application/json' } });
                this.opsi = res.data.result || { talent: [], program: [] };
                if (!this.kategori && this.opsi.talent.length) this.kategori = this.opsi.talent[0].kode;
            } catch (e) {
                this.beritahu('Gagal memuat opsi program', 'error');
            }
        },
        gantiKategori(kode) {
            this.kategori = kode;
            this.form.programId = null;
            this.lupakanTes();
            this.muatTesAlur(); // tanpa program → daftar tes ikut dikosongkan
        },
        onProgram() {
            // Ganti program = ganti alur, jadi pilihan tes lama tidak berlaku lagi.
            this.lupakanTes();
            this.muatTesAlur();
        },
        // Kosongkan pilihan tes + turunannya (kandidat ikut tahap yang dipilih).
        lupakanTes() {
            this.form.tahapUrutan = null;
            this.form.tesUrutan = null;
            this.form.peserta = [];
            this.kandidat = [];
            this.alasanKandidat = '';
        },
        async muatTesAlur() {
            if (!this.form.programId) { this.tesAlur = []; this.alasanTes = ''; return; }
            this.memuatTes = true;
            try {
                const res = await axios.get('/api/v1/penjadwalan/tes', {
                    params: { programId: this.form.programId },
                    headers: { Accept: 'application/json' },
                });
                this.tesAlur = res.data.result || [];
                this.alasanTes = this.tesAlur.length ? '' : (res.data.message || '');
                // Belum memilih apa pun → pilihkan. Satu ujian saja: langsung itu.
                // Beberapa: arahkan ke yang benar-benar ada kandidat menunggu —
                // itu yang dicari admin saat membuka halaman ini. Kalau admin
                // sudah memilih sendiri, jangan digeser.
                if (!this.form.tahapUrutan) {
                    const perlu = this.tesAlur.length === 1
                        ? this.tesAlur[0]
                        : this.tesAlur.find((t) => t.menunggu > 0);
                    if (perlu) this.pilihTes(perlu);
                }
            } catch (e) {
                this.beritahu('Gagal memuat tes pada alur program', 'error');
            } finally {
                this.memuatTes = false;
            }
        },
        pilihTes(t) {
            this.form.tahapUrutan = t.tahapUrutan;
            this.form.tesUrutan = t.tesUrutan;
            this.form.peserta = [];
            this.muatKandidat();
        },
        async muatPaket() {
            this.memuatPaket = true;
            this.paket = [];
            this.form.idMasterUjian = null;
            this.form.namaUjian = '';
            try {
                const res = await axios.get('/api/v1/penjadwalan/paket-ujian', {
                    params: this.cariPaket ? { q: this.cariPaket } : {},
                    headers: { Accept: 'application/json' },
                });
                this.paket = res.data.result || [];
            } catch (e) {
                this.beritahu(e.response?.data?.message || 'Gagal memuat paket tes dari HCLearn', 'error');
            } finally {
                this.memuatPaket = false;
            }
        },
        debounceCari() {
            clearTimeout(this.timerPaket);
            this.timerPaket = setTimeout(() => this.muatPaket(), 400);
        },
        pilihPaket(p) {
            this.form.idMasterUjian = p.Id_Master_Ujian;
            this.form.namaUjian = p.Nama_Ujian;
        },
        async muatKandidat() {
            // Kandidat = pelamar NYATA program terpilih yang punya SUB-TES pihak
            // ke-3 menunggu jadwal. Satu tahap bisa berisi beberapa tes, jadi
            // orang yang sama bisa muncul lagi untuk tes berikutnya di tahap itu.
            if (!this.form.programId || !this.form.tahapUrutan) { this.kandidat = []; this.form.peserta = []; this.alasanKandidat = ''; return; }
            this.memuatKandidat = true;
            try {
                const params = { programId: this.form.programId, tahapUrutan: this.form.tahapUrutan };
                if (this.form.tesUrutan) params.tesUrutan = this.form.tesUrutan;
                if (this.cariKandidat) params.q = this.cariKandidat;
                const res = await axios.get('/api/v1/penjadwalan/kandidat', { params, headers: { Accept: 'application/json' } });
                this.kandidat = res.data.result || [];
                this.alasanKandidat = this.kandidat.length ? '' : (res.data.message || '');
                // Buang peserta terpilih yang tak lagi ada di daftar terbaru.
                this.form.peserta = this.form.peserta.filter((k) => this.kandidat.some((c) => c.kode === k));
            } catch (e) {
                this.beritahu('Gagal memuat kandidat', 'error');
            } finally {
                this.memuatKandidat = false;
            }
        },
        debounceKandidat() {
            clearTimeout(this.timerKandidat);
            this.timerKandidat = setTimeout(() => this.muatKandidat(), 400);
        },
        toggleKandidat(kode) {
            const i = this.form.peserta.indexOf(kode);
            if (i >= 0) this.form.peserta.splice(i, 1);
            else this.form.peserta.push(kode);
        },
        toggleSemua() {
            this.form.peserta = this.semuaTercentang ? [] : this.kandidat.map((k) => k.kode);
        },
        async muat() {
            this.memuat = true;
            try {
                const res = await axios.get('/api/v1/penjadwalan', { headers: { Accept: 'application/json' } });
                this.daftar = res.data.result || [];
            } catch (e) {
                this.beritahu('Gagal memuat daftar penjadwalan', 'error');
            } finally {
                this.memuat = false;
            }
        },
        async simpan() {
            this.menyimpan = true;
            try {
                const res = await axios.post('/api/v1/penjadwalan', this.form, { headers: { Accept: 'application/json' } });
                this.beritahu(res.data.message || 'Penjadwalan dibuat');
                this.form.peserta = [];
                this.muat();
                // Yang barusan dijadwalkan hilang dari antrean — segarkan hitungannya.
                this.muatTesAlur();
                this.muatKandidat();
            } catch (e) {
                this.beritahu(e.response?.data?.message || 'Gagal membuat penjadwalan', 'error');
            } finally {
                this.menyimpan = false;
            }
        },
        // Tahap tes yang dijadwalkan (ber-namaUjian) — target edit jendela waktu.
        tahapTerjadwal(j) {
            return (j.tahap || []).find((t) => t.namaUjian) || null;
        },
        bukaEdit(j) {
            const t = this.tahapTerjadwal(j);
            if (!t) return;
            this.editTarget = { ...j, namaUjian: t.namaUjian };
            this.editMulai = this.normalWaktu(t.waktuMulai);
            this.editAkhir = this.normalWaktu(t.waktuAkhir);
            this.editTampil = true;
        },
        // Samakan format waktu dari server (ISO / 'YYYY-MM-DD HH:mm:ss') ke value-format picker.
        normalWaktu(v) {
            if (!v) return '';
            return String(v).replace('T', ' ').slice(0, 19);
        },
        async simpanEdit() {
            if (this.editSibuk || !this.editTarget) return;
            this.editSibuk = true;
            try {
                const res = await axios.put(`/api/v1/penjadwalan/${this.editTarget.id}`, {
                    waktuMulai: this.editMulai,
                    waktuAkhir: this.editAkhir,
                }, { headers: { Accept: 'application/json' } });
                this.beritahu(res.data.message || 'Jadwal diperbarui');
                this.editTampil = false;
                this.muat();
            } catch (e) {
                this.beritahu(e.response?.data?.message || 'Gagal memperbarui jadwal', 'error');
            } finally {
                this.editSibuk = false;
            }
        },
        konfirmHapus(j) {
            this.target = j;
            this.hapusTampil = true;
        },
        async hapus() {
            try {
                await axios.delete(`/api/v1/penjadwalan/${this.target.id}`, { headers: { Accept: 'application/json' } });
                this.hapusTampil = false;
                this.beritahu('Penjadwalan dihapus');
                this.muat();
                // Pesertanya kembali ke antrean menunggu jadwal.
                this.muatTesAlur();
                this.muatKandidat();
            } catch (e) {
                this.beritahu('Gagal menghapus penjadwalan', 'error');
            }
        },
        salin(teks) {
            navigator.clipboard?.writeText(teks);
            this.beritahu('Tautan ujian disalin');
        },
    },
};
</script>

<style scoped>
.wca-page { padding: 1.25rem; }
.wca-alert { margin-bottom: 1rem; }
.wca-editbox { display: flex; flex-direction: column; }
.wca-editnama { margin: 0 0 .5rem; font-size: .95rem; color: #1e293b; }
.wca-editnote { display: flex; gap: .5rem; margin: 0 0 1rem; padding: .6rem .75rem; border-radius: 10px; background: rgba(245, 158, 11, .1); border: 1px solid rgba(245, 158, 11, .24); font-size: .78rem; line-height: 1.5; color: #92660a; }
.wca-editnote .bi { flex: 0 0 auto; margin-top: 1px; color: #d97706; }
.wca-editlbl { font-size: .82rem; font-weight: 700; color: #334155; margin: .75rem 0 .35rem; }
.wca-editlbl:first-of-type { margin-top: 0; }
.wca-segtab { display: inline-flex; gap: .25rem; padding: .25rem; background: var(--el-fill-color-light); border-radius: 12px; margin-bottom: 1rem; }
.wca-segtab__btn { border: 0; background: transparent; padding: .5rem 1rem; border-radius: 9px; font-weight: 600; font-size: .9rem; color: var(--el-text-color-secondary); cursor: pointer; }
.wca-segtab__btn.is-active { background: #fff; color: #4f46e5; box-shadow: 0 1px 3px rgba(0,0,0,.08); }
.wca-split { display: grid; gap: 1rem; grid-template-columns: 1fr; margin-bottom: 1rem; }
.wca-card { border-radius: 14px; }
.wca-card--full { margin-top: 0; }
.wca-cardhead { display: flex; align-items: center; justify-content: space-between; gap: .75rem; }
.wca-cardtitle { font-weight: 700; font-size: 1rem; color: #4f46e5; display: flex; align-items: center; gap: .5rem; }
.wca-w { width: 100%; }
.wca-search { margin-bottom: .6rem; }
.wca-grid2 { display: grid; gap: 0 1rem; grid-template-columns: 1fr; }
.wca-hint { font-size: .78rem; color: var(--el-text-color-secondary); margin: .3rem 0 .9rem; display: flex; gap: .4rem; align-items: flex-start; }
.wca-hint--warn { color: var(--el-color-warning); }
.wca-opttag { float: right; color: var(--el-text-color-secondary); font-size: .78rem; margin-left: 1rem; }
.wca-muted { color: var(--el-text-color-secondary); }

/* Tes/tahap dari alur program — pengganti dropdown jenis tes */
.wca-teslist { display: flex; flex-direction: column; gap: .45rem; max-height: 250px; overflow-y: auto; padding: .15rem; }
.wca-tes { display: flex; align-items: center; gap: .65rem; width: 100%; text-align: left; padding: .55rem .7rem; border: 1.5px solid var(--el-border-color); border-radius: 11px; background: #fff; cursor: pointer; transition: border-color .15s, box-shadow .15s; }
.wca-tes:hover { border-color: #a5a6f6; }
.wca-tes.is-active { border-color: #4f46e5; box-shadow: 0 0 0 3px #eef0fe; }
.wca-tes__no { width: 26px; height: 26px; flex: 0 0 26px; border-radius: 8px; display: grid; place-items: center; background: #eef0fe; color: #4f46e5; font-size: .78rem; font-weight: 700; }
.wca-tes.is-active .wca-tes__no { background: linear-gradient(135deg, #6366f1, #8b5cf6); color: #fff; }
.wca-tes__info { display: flex; flex-direction: column; min-width: 0; flex: 1; }
.wca-tes__nama { font-weight: 600; font-size: .88rem; line-height: 1.35; }
.wca-tes__meta { display: flex; flex-wrap: wrap; gap: .15rem .7rem; font-size: .72rem; color: var(--el-text-color-secondary); }

/* Kartu paket tes — dua kolom (col-6) */
.wca-paketgrid { display: grid; gap: .7rem; grid-template-columns: 1fr; max-height: 340px; overflow-y: auto; padding: .15rem; }
.wca-paketcard { text-align: left; border: 1.5px solid var(--el-border-color); background: #fff; border-radius: 12px; padding: .75rem .85rem; cursor: pointer; transition: border-color .15s, box-shadow .15s; }
.wca-paketcard:hover { border-color: #a5a6f6; }
.wca-paketcard.is-active { border-color: #4f46e5; box-shadow: 0 0 0 3px #eef0fe; }
.wca-paketcard__head { display: flex; align-items: flex-start; justify-content: space-between; gap: .5rem; color: #4f46e5; }
.wca-paketcard__nama { font-weight: 650; font-size: .9rem; line-height: 1.3; color: var(--el-text-color-primary); }
.wca-paketcard.is-active .wca-paketcard__nama { color: #4f46e5; }
.wca-paketcard__kode { font-family: ui-monospace, monospace; font-size: .72rem; color: var(--el-text-color-secondary); margin-top: .15rem; }
.wca-paketcard__alat { display: flex; flex-wrap: wrap; gap: .3rem; margin: .5rem 0 .4rem; }
.wca-chip { font-size: .7rem; padding: .12rem .45rem; border-radius: 999px; background: #eef0fe; color: #4f46e5; font-weight: 600; }
.wca-paketcard__meta { display: flex; gap: .9rem; font-size: .74rem; color: var(--el-text-color-secondary); }

/* Kandidat */
.wca-kandhead { display: flex; align-items: center; gap: .75rem; flex-wrap: wrap; margin-bottom: .6rem; }
.wca-kandhead .wca-search { flex: 1; min-width: 180px; margin-bottom: 0; }
.wca-kandlist { display: flex; flex-direction: column; gap: .5rem; max-height: 480px; overflow-y: auto; padding: .15rem; }
.wca-kand { display: flex; align-items: center; gap: .7rem; padding: .6rem .75rem; border: 1.5px solid var(--el-border-color); border-radius: 12px; cursor: pointer; }
.wca-kand.is-active { border-color: #4f46e5; background: #f7f8ff; }
.wca-avatar { width: 34px; height: 34px; flex: 0 0 34px; border-radius: 50%; display: grid; place-items: center; background: linear-gradient(135deg,#6366f1,#8b5cf6); color: #fff; font-size: .78rem; font-weight: 700; }
.wca-kandinfo { display: flex; flex-direction: column; min-width: 0; }
.wca-kandnama { font-weight: 600; font-size: .9rem; }
.wca-kandmeta { font-size: .76rem; color: var(--el-text-color-secondary); }
.wca-kandtes { display: inline-flex; align-items: center; gap: .3rem; margin-top: .15rem; font-size: .72rem; font-weight: 600; color: #4338ca; }

/* Daftar penjadwalan */
.wca-jadwal { border: 1px solid var(--el-border-color); border-radius: 12px; padding: .85rem; margin-bottom: .85rem; }
.wca-jadwalhead { display: flex; justify-content: space-between; gap: .75rem; flex-wrap: wrap; }
.wca-jadwalact { display: flex; align-items: center; gap: .35rem; }
.wca-kode { font-family: ui-monospace, monospace; font-size: .74rem; color: #4f46e5; font-weight: 700; }
.wca-nama { font-weight: 650; font-size: .96rem; }
.wca-meta { display: flex; flex-wrap: wrap; gap: .2rem .9rem; font-size: .8rem; color: var(--el-text-color-secondary); margin-top: .15rem; }
.wca-tahap { display: flex; flex-wrap: wrap; gap: .4rem; margin: .7rem 0; }
.wca-step { font-size: .76rem; padding: .22rem .55rem; border: 1px solid var(--el-border-color); border-radius: 999px; }
.wca-step--hcl { border-color: #4f46e5; background: #eef0fe; color: #4f46e5; }
.wca-step em { font-style: normal; opacity: .75; }

@media (min-width: 900px) {
    .wca-split { grid-template-columns: 1fr 1fr; }
    .wca-paketgrid { grid-template-columns: 1fr 1fr; }
    .wca-grid2 { grid-template-columns: 1fr 1fr; }
}
</style>
