<!-- WEB CAREER — Program Kegiatan (induk-detail: Program + Batch + Posisi + Kriteria). DATA dari DB via /api/v1/program-kegiatan. -->
<template>
    <Head><title>Program Kegiatan - Web Career</title></Head>
    <div class="wca">
        <div class="wca-phead">
            <div>
                <h1>Program Kegiatan</h1>
                <p>Definisi program (isi + alur + jadwal + posisi + kriteria). Satu program menampung <b>batch</b>, <b>posisi/lowongan</b>, dan <b>syarat auto-gugur</b>.</p>
            </div>
            <div class="wca-phead__actions">
                <button class="wca-btn wca-btn--primary" @click="openCreate"><i class="bi bi-plus-lg"></i> Buat Program</button>
            </div>
        </div>

        <!-- Tab kategori -->
        <div class="wca-segt">
            <button class="wca-segt__it" :class="{ on: tab === '' }" @click="tab = ''"><i class="bi bi-grid"></i> Semua</button>
            <button class="wca-segt__it" :class="{ on: tab === 'REKRUTMEN' }" @click="tab = 'REKRUTMEN'"><i class="bi bi-briefcase"></i> Rekrutmen</button>
            <button class="wca-segt__it" :class="{ on: tab === 'MT' }" @click="tab = 'MT'"><i class="bi bi-mortarboard"></i> Management Trainee</button>
            <button class="wca-segt__it" :class="{ on: tab === 'INTERNSHIP' }" @click="tab = 'INTERNSHIP'"><i class="bi bi-backpack"></i> Internship</button>
        </div>

        <!-- Stats -->
        <div class="wca-stats">
            <div class="wca-stat"><div class="wca-stat__top"><span class="wca-stat__ico"><i class="bi bi-diagram-3-fill"></i></span></div><div class="wca-stat__num">{{ filtered.length }}</div><div class="wca-stat__label">Program</div></div>
            <div class="wca-stat"><div class="wca-stat__top"><span class="wca-stat__ico" style="background:rgba(16,185,129,.12);color:#059669"><i class="bi bi-broadcast"></i></span></div><div class="wca-stat__num">{{ aktif }}</div><div class="wca-stat__label">Berjalan</div></div>
            <div class="wca-stat"><div class="wca-stat__top"><span class="wca-stat__ico" style="background:rgba(139,92,246,.12);color:#7c3aed"><i class="bi bi-people"></i></span></div><div class="wca-stat__num">{{ totalKuotaPosisi }}</div><div class="wca-stat__label">Total Kuota Posisi</div></div>
        </div>

        <!-- Accordion -->
        <div v-loading="loading" class="wca-acc">
            <div v-for="p in filtered" :key="p.id" class="wca-acc__item" :class="{ open: open === p.id }">
                <button class="wca-acc__head" @click="open = (open === p.id ? null : p.id)">
                    <span class="wca-acc__chev"><i class="bi bi-chevron-right"></i></span>
                    <span class="wca-acc__title">
                        <strong><span class="wca-dot" :style="{ background: p.warna || '#4f46e5' }"></span> {{ p.nama }}</strong>
                        <small>{{ p.kode }} · {{ p.penyelenggara || '—' }} · Alur {{ p.alurNama || p.alur || '—' }}</small>
                    </span>
                    <span class="wca-acc__tags">
                        <span class="wca-badge" :class="katBadge(p.kategori)">{{ katLabel(p.kategori) }}</span>
                        <span class="wca-badge" :class="p.mode === 'TERSTRUKTUR' ? 'wca-b--indigo' : 'wca-b--slate'">{{ modeLabel(p.mode) }}</span>
                        <span class="wca-badge" :class="statusBadge(p.status)"><i class="bi" :class="statusIkon(p.status)"></i> {{ statusLabel(p.status) }}</span>
                    </span>
                    <span class="pgk-head-act" @click.stop>
                        <el-switch :model-value="p.status === 'BERJALAN'" @change="(v) => setStatus(p, v)" />
                        <button class="wca-iconbtn" title="Ubah" @click="openEdit(p)"><i class="bi bi-pencil"></i></button>
                        <button class="wca-iconbtn wca-iconbtn--danger" title="Hapus" @click="askRemove(p)"><i class="bi bi-trash"></i></button>
                    </span>
                </button>

                <div class="wca-acc__body">
                    <div class="wca-acc__inner">
                        <div class="pgk-meta"><AuditStamp :by="p.createdBy" :at="p.createdAt" /></div>

                        <!-- Syarat auto-gugur -->
                        <div v-if="p.syarat && p.syarat.length" style="margin-bottom:1rem">
                            <div class="wca-fsection__label" style="margin-bottom:.5rem"><i class="bi bi-sliders2"></i> Syarat Auto-Gugur ({{ p.syarat.length }})</div>
                            <div v-for="(s, i) in p.syarat" :key="i" class="pgk-syarat-ro">
                                <div class="pgk-syarat-ro__top">
                                    <strong>{{ s.nama }}</strong>
                                    <span class="wca-badge" :class="s.aksi === 'GUGUR' ? 'wca-b--red' : 'wca-b--indigo'">
                                        <i class="bi" :class="s.aksi === 'GUGUR' ? 'bi-x-octagon' : 'bi-hand-index-thumb'"></i>
                                        {{ s.aksi === 'GUGUR' ? 'Gugur langsung' : 'Tandai — admin ketuk palu' }}
                                    </span>
                                    <span v-if="s.uji" class="wca-badge wca-b--amber"><i class="bi bi-flask"></i> Mode uji</span>
                                    <span v-if="!s.aktif" class="wca-badge wca-b--slate">Nonaktif</span>
                                </div>
                                <div class="wca-krset">
                                    <span class="pgk-gab">{{ s.aturan?.penghubung === 'ATAU' ? 'SALAH SATU' : 'SEMUA' }}</span>
                                    <span v-for="(r, j) in s.aturan?.aturan || []" :key="j" class="wca-krrule wca-krrule--ro">
                                        <i class="bi bi-funnel"></i> {{ r.field }} {{ r.operator }} {{ r.nilai }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Batch -->
                        <div v-if="p.batch && p.batch.length" style="margin-bottom:1rem">
                            <div class="wca-fsection__label" style="margin-bottom:.5rem"><i class="bi bi-collection"></i> Batch ({{ p.batch.length }})</div>
                            <div class="wca-tagset">
                                <span v-for="(b, i) in p.batch" :key="i"><i class="bi bi-people"></i> {{ b.nama }} · {{ b.terisi }}/{{ b.kuota }} kursi<template v-if="b.status"> · {{ b.status }}</template></span>
                            </div>
                        </div>

                        <!-- Posisi -->
                        <div class="wca-fsection__label" style="margin-bottom:.6rem;display:flex;align-items:center;justify-content:space-between">
                            <span><i class="bi bi-briefcase"></i> Posisi / Lowongan ({{ (p.posisi || []).length }})</span>
                            <span class="wca-badge wca-b--indigo"><i class="bi bi-people"></i> Total kuota {{ totalKuota(p) }}</span>
                        </div>
                        <div class="wca-tablewrap">
                            <table class="wca-table">
                                <thead><tr><th>Posisi</th><th>No. MPP</th><th>Departemen</th><th>Lokasi</th><th>Kuota</th><th>Status</th></tr></thead>
                                <tbody>
                                    <tr v-for="(l, i) in p.posisi" :key="i">
                                        <td><strong>{{ l.posisi }}</strong><small v-if="l.level" style="display:block;color:#94a3b8">{{ l.level }}</small></td>
                                        <td><code v-if="l.mppRef" class="pgk-mpp">{{ l.mppRef }}</code><span v-else style="color:#b45309" title="Diinput manual sebelum aturan wajib-MPP">manual</span></td>
                                        <td>{{ l.departemen || '—' }}</td>
                                        <td><i class="bi bi-geo-alt" style="color:var(--indigo)"></i> {{ l.lokasi || '—' }}</td>
                                        <td>{{ l.kuota }}</td>
                                        <td><span class="wca-badge wca-b--slate">{{ l.status || '—' }}</span></td>
                                    </tr>
                                    <tr v-if="!(p.posisi || []).length"><td colspan="6" style="color:#94a3b8">Belum ada posisi.</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div v-if="!loading && !filtered.length" class="wca-empty"><i class="bi bi-diagram-3"></i><h4>Belum ada program pada kategori ini</h4></div>
        </div>

        <!-- Modal buat/ubah program -->
        <AdminModal :show="show" :title="editingId ? 'Ubah Program' : 'Buat Program Kegiatan'" subtitle="Definisi program: identitas, mode, alur, jadwal, batch, posisi & kriteria." icon="bi-diagram-3-fill" lg :save-label="editingId ? 'Perbarui' : 'Buat Program'" @close="show = false" @save="save">
            <!-- Identitas -->
            <div class="wca-fsection">
                <div class="wca-fsection__label"><i class="bi bi-tags"></i> Identitas & Klasifikasi</div>
                <div class="wca-form">
                    <!-- Kategori DULU: mode, warna, alur & jadwal semuanya menurun dari sini. -->
                    <div class="wca-frow">
                        <div>
                            <label class="wca-field-lbl">Kategori <span class="pgk-req">wajib</span></label>
                            <RefSelect type="talent" v-model="form.kategori" placeholder="Pilih kategori dulu" @picked="onKategori" />
                            <div class="pgk-hint">Menentukan mode, alur & jadwal yang tersedia di bawah.</div>
                        </div>
                        <div>
                            <label class="wca-field-lbl">Nama Program</label>
                            <el-input v-model="form.nama" :disabled="terkunci" :placeholder="terkunci ? 'Pilih kategori dulu' : 'Rekrutmen Reguler Q4 2026'" />
                        </div>
                    </div>
                    <div class="wca-frow">
                        <div><label class="wca-field-lbl">Mode</label><RefSelect type="mode" v-model="form.mode" :disabled="terkunci" :placeholder="terkunci ? 'Pilih kategori dulu' : 'Pilih mode'" /></div>
                        <div><label class="wca-field-lbl">Warna</label>
                            <el-color-picker v-model="form.warna" :predefine="palette" :disabled="terkunci" />
                        </div>
                    </div>
                    <div class="wca-frow">
                        <div>
                            <label class="wca-field-lbl">Alur Seleksi</label>
                            <RefSelect
                                type="alur"
                                v-model="form.alur"
                                :params="{ kategori: form.kategori }"
                                :disabled="terkunci"
                                :placeholder="terkunci ? 'Pilih kategori dulu' : 'Pilih alur'"
                                no-data-text="Belum ada alur untuk kategori ini"
                                clearable
                                @picked="onAlurGanti"
                            />
                        </div>
                        <div>
                            <label class="wca-field-lbl">Jadwal</label>
                            <RefSelect
                                type="jadwal"
                                v-model="form.jadwal"
                                :params="{ kategori: form.kategori, alur: form.alur }"
                                :disabled="terkunci"
                                :placeholder="terkunci ? 'Pilih kategori dulu' : 'Pilih jadwal'"
                                no-data-text="Belum ada jadwal untuk alur ini"
                                clearable
                            />
                        </div>
                    </div>
                    <div class="wca-frow">
                        <div>
                            <label class="wca-field-lbl">Penyelenggara</label>
                            <el-input v-model="form.penyelenggara" :disabled="terkunci" :placeholder="terkunci ? 'Pilih kategori dulu' : 'Tim Rekrutmen'" />
                        </div>
                        <!-- Status hanya muncul saat MENGUBAH. Program baru selalu lahir
                             BERJALAN, jadi tak perlu ditanyakan saat dibuat. -->
                        <div v-if="editingId"><label class="wca-field-lbl">Status</label>
                            <el-select filterable v-model="form.status" placeholder="Status" style="width:100%">
                                <el-option label="Draft" value="DRAFT" />
                                <el-option label="Berjalan" value="BERJALAN" />
                                <el-option label="Selesai" value="SELESAI" />
                            </el-select>
                        </div>
                        <div v-else class="pgk-statusnote">
                            <i class="bi bi-play-circle-fill"></i>
                            <span>Program langsung <strong>Berjalan</strong> setelah dibuat.</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Posisi -->
            <div class="wca-fsection">
                <div class="wca-fsection__label" style="display:flex;align-items:center;justify-content:space-between">
                    <span><i class="bi bi-briefcase"></i> Posisi / Lowongan ({{ form.posisi.length }})</span>
                    <button class="wca-btn wca-btn--soft wca-btn--sm" type="button" :disabled="terkunci" @click="addPosisi"><i class="bi bi-plus-circle"></i> Ambil dari MPP</button>
                </div>
                <div class="pgk-rows">
                    <!-- Posisi TIDAK boleh diketik manual: hanya boleh dipilih dari MPP
                         yang sudah disetujui. Departemen, lokasi & level ikut terisi
                         otomatis dan dikunci agar tidak menyimpang dari MPP. -->
                    <div v-for="(l, i) in form.posisi" :key="i" class="pgk-row pgk-row--stack">
                        <div class="pgk-row__pick">
                            <label class="wca-field-lbl">Posisi (dari MPP)</label>
                            <RefSelect
                                type="mpp"
                                v-model="l.mppRef"
                                :params="{ kategori: form.kategori }"
                                :placeholder="form.kategori ? 'Cari nomor / nama posisi MPP' : 'Pilih kategori dulu'"
                                no-data-text="Tidak ada MPP untuk kategori ini"
                                @picked="(o) => onMpp(l, o)"
                            />
                            <div v-if="!l.mppRef" class="pgk-hint pgk-hint--warn"><i class="bi bi-exclamation-triangle"></i> Baris ini belum tertaut MPP — pilih dulu sebelum menyimpan.</div>
                        </div>

                        <div class="pgk-row__grid pgk-row__grid--posisi" :class="{ 'is-edit': mengubah }">
                            <div><label class="wca-field-lbl">Departemen</label><el-input :model-value="l.departemen" disabled placeholder="—" /></div>
                            <div><label class="wca-field-lbl">Lokasi</label><el-input :model-value="l.lokasi" disabled placeholder="—" /></div>
                            <div><label class="wca-field-lbl">Level</label><el-input :model-value="l.level" disabled placeholder="—" /></div>
                            <div>
                                <label class="wca-field-lbl">Kuota <small v-if="l.kuotaMpp">/ MPP {{ l.kuotaMpp }}</small></label>
                                <el-input-number v-model="l.kuota" :min="0" :max="l.kuotaMpp || undefined" controls-position="right" style="width:100%" />
                            </div>
                            <!-- Lowongan yang baru dibuat pasti BUKA — tidak ditanyakan.
                                 Baru relevan saat program diubah (mis. ditutup). -->
                            <div v-if="mengubah"><label class="wca-field-lbl">Status</label>
                                <el-select v-model="l.status" style="width:100%">
                                    <el-option label="Buka" value="BUKA" />
                                    <el-option label="Penuh" value="PENUH" />
                                    <el-option label="Tutup" value="TUTUP" />
                                </el-select>
                            </div>
                        </div>
                        <button class="wca-iconbtn wca-iconbtn--danger pgk-row__del" type="button" title="Hapus posisi" @click="form.posisi.splice(i, 1)"><i class="bi bi-trash"></i></button>
                    </div>
                    <div v-if="!form.posisi.length" class="pgk-empty">
                        <template v-if="form.kategori">Tanpa posisi — program tetap bisa dibuat. Klik "Ambil dari MPP" untuk menautkan lowongan.</template>
                        <template v-else>Pilih kategori dulu, baru daftar MPP bisa ditampilkan.</template>
                    </div>
                </div>
            </div>

            <!-- PENGATURAN TAMBAHAN — semuanya opsional dan bergantung pada Posisi
                 di atas (kuota batch dibatasi pagu MPP), jadi sengaja diletakkan
                 paling bawah agar tidak dikira langkah wajib. -->
            <div class="pgk-extra">
                <div class="pgk-extra__head">
                    <span class="pgk-extra__title"><i class="bi bi-sliders"></i> Pengaturan Tambahan</span>
                    <span class="pgk-extra__note">Boleh dilewati — program tetap bisa dibuat tanpa ini.</span>
                </div>

                <!-- Batch -->
                <div class="wca-fsection">
                    <div class="wca-fsection__label" style="display:flex;align-items:center;justify-content:space-between">
                        <span><i class="bi bi-collection"></i> Batch ({{ form.batch.length }})</span>
                        <span class="pgk-headright">
                            <!-- Batas kuota berasal dari MPP: total kursi batch tidak boleh
                                 melebihi total kuota posisi yang ditarik dari MPP. -->
                            <span v-if="kuotaMpp" class="pgk-meter" :class="{ 'is-over': kuotaBatchLebih }">
                                <i class="bi" :class="kuotaBatchLebih ? 'bi-exclamation-octagon-fill' : 'bi-people-fill'"></i>
                                {{ kuotaBatch }} / {{ kuotaMpp }} kursi MPP
                            </span>
                            <button class="wca-btn wca-btn--soft wca-btn--sm" type="button" :disabled="terkunci" @click="addBatch"><i class="bi bi-plus-circle"></i> Tambah Batch</button>
                        </span>
                    </div>
                    <div class="pgk-rows">
                        <div v-if="kuotaBatchLebih" class="pgk-alert">
                            <i class="bi bi-exclamation-triangle-fill"></i>
                            Total kursi batch <strong>{{ kuotaBatch }}</strong> melebihi kuota MPP <strong>{{ kuotaMpp }}</strong>. Kurangi {{ kuotaBatch - kuotaMpp }} kursi sebelum menyimpan.
                        </div>
                        <div v-for="(b, i) in form.batch" :key="i" class="pgk-row">
                            <div class="pgk-row__grid pgk-row__grid--batch" :class="{ 'is-edit': mengubah }">
                                <div><label class="wca-field-lbl">Nama</label><el-input v-model="b.nama" placeholder="Batch 1 — Mei 2026" /></div>
                                <div>
                                    <label class="wca-field-lbl">Kuota <small v-if="kuotaMpp">maks {{ batasBatch(i) }}</small></label>
                                    <el-input-number v-model="b.kuota" :min="0" :max="kuotaMpp ? batasBatch(i) : undefined" controls-position="right" style="width:100%" />
                                </div>
                                <!-- Batch baru pasti masih kosong (Terisi 0) dan AKTIF.
                                     Keduanya hasil pengisian peserta, bukan input saat
                                     mendefinisikan program — jadi hanya muncul saat diubah. -->
                                <template v-if="mengubah">
                                    <div><label class="wca-field-lbl">Terisi</label><el-input-number v-model="b.terisi" :min="0" :max="b.kuota || undefined" controls-position="right" style="width:100%" /></div>
                                    <div><label class="wca-field-lbl">Status</label>
                                        <el-select v-model="b.status" style="width:100%">
                                            <el-option label="Aktif" value="AKTIF" />
                                            <el-option label="Selesai" value="SELESAI" />
                                            <el-option label="Tutup" value="TUTUP" />
                                        </el-select>
                                    </div>
                                </template>
                            </div>
                            <button class="wca-iconbtn wca-iconbtn--danger" type="button" title="Hapus batch" @click="form.batch.splice(i, 1)"><i class="bi bi-trash"></i></button>
                        </div>
                        <div v-if="!form.batch.length" class="pgk-empty">
                            <template v-if="terkunci">Pilih kategori dulu.</template>
                            <template v-else>Tanpa batch — program berjalan langsung, tanpa angkatan. Klik "Tambah Batch" bila perlu dibagi per angkatan.</template>
                        </div>
                    </div>
                </div>

                <!-- Syarat auto-gugur -->
                <div class="wca-fsection">
                    <div class="wca-fsection__label" style="display:flex;align-items:center;justify-content:space-between">
                        <span><i class="bi bi-sliders2"></i> Syarat Auto-Gugur ({{ form.syarat.length }})</span>
                        <button class="wca-btn wca-btn--soft wca-btn--sm" type="button" :disabled="terkunci || !tahapFormulir.length" @click="addSyarat">
                            <i class="bi bi-plus-circle"></i> Tambah Syarat
                        </button>
                    </div>

                    <!-- Syarat hanya bisa memeriksa field yang memang DITANYAKAN
                         formulir. Tanpa tahap berformulir, tidak ada yang bisa dinilai. -->
                    <div v-if="!terkunci && !tahapFormulir.length" class="pgk-alert" style="margin-bottom:.6rem">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        Alur <b>{{ form.alur || '—' }}</b> belum punya tahap berformulir. Pasang formulir di Master Tahapan Seleksi dulu — tanpa formulir, tidak ada data yang bisa disaring.
                    </div>

                    <div class="pgk-rows">
                        <div v-for="(S, i) in form.syarat" :key="i" class="pgk-syarat">
                            <div class="pgk-syarat__head">
                                <el-input v-model="S.nama" placeholder="Nama syarat — mis. Kelayakan Akademik" style="flex:1.4" />
                                <el-select v-model="S.tahapId" placeholder="Diperiksa di tahap" style="flex:1.6" @change="(v) => onTahapSyarat(S, v)">
                                    <el-option v-for="t in tahapFormulir" :key="t.tahapId" :value="t.tahapId" :label="`${t.urutan}. ${t.label} — ${t.formulirNama || t.formulir}`" />
                                </el-select>
                                <button class="wca-iconbtn wca-iconbtn--danger" type="button" title="Hapus syarat" @click="form.syarat.splice(i, 1)"><i class="bi bi-trash"></i></button>
                            </div>

                            <!-- Pohon aturan: grup luar DAN/ATAU berisi kondisi. -->
                            <div class="pgk-aturan">
                                <div class="pgk-aturan__bar">
                                    <span>Pelamar lolos bila</span>
                                    <el-select v-model="S.aturan.penghubung" size="small" style="width:6.5rem">
                                        <el-option value="DAN" label="SEMUA" />
                                        <el-option value="ATAU" label="SALAH SATU" />
                                    </el-select>
                                    <span>kondisi berikut terpenuhi:</span>
                                    <span style="flex:1"></span>
                                    <button class="wca-btn wca-btn--soft wca-btn--sm" type="button" @click="addKondisi(S)"><i class="bi bi-plus-lg"></i> Kondisi</button>
                                </div>

                                <div v-for="(K, j) in S.aturan.aturan" :key="j" class="pgk-kondisi">
                                    <el-select v-model="K.field" filterable placeholder="Pilih field" style="flex:1.5" :no-data-text="S.tahapId ? 'Formulir tahap ini tidak punya field' : 'Pilih tahap dulu'">
                                        <el-option-group label="Dari formulir">
                                            <el-option v-for="f in fieldTahap(S.tahapId)" :key="f.key" :value="f.key" :label="`${f.label} (${f.key})`" />
                                        </el-option-group>
                                        <el-option-group label="Dihitung otomatis">
                                            <el-option v-for="f in fieldTurunan" :key="f.key" :value="f.key" :label="f.label" />
                                        </el-option-group>
                                    </el-select>
                                    <el-select v-model="K.operator" style="width:8.5rem">
                                        <el-option v-for="o in operatorOptions" :key="o.value" :value="o.value" :label="o.label" />
                                    </el-select>
                                    <el-input v-model="K.nilai" :placeholder="phNilai(K.operator)" style="flex:1" />
                                    <button class="wca-iconbtn wca-iconbtn--danger" type="button" title="Hapus kondisi" @click="S.aturan.aturan.splice(j, 1)"><i class="bi bi-x-lg"></i></button>
                                </div>

                                <div v-if="!S.aturan.aturan.length" class="pgk-empty" style="padding:.4rem">
                                    Belum ada kondisi — syarat ini akan diabaikan mesin.
                                </div>
                            </div>

                            <div class="pgk-syarat__foot">
                                <el-select v-model="S.aksi" size="small" style="width:16rem">
                                    <el-option value="TANDAI" label="Tandai — admin yang ketuk palu" />
                                    <el-option value="GUGUR" label="Gugurkan langsung tanpa admin" />
                                </el-select>
                                <el-checkbox v-model="S.uji">Mode uji (hitung saja, jangan pengaruhi pelamar)</el-checkbox>
                            </div>

                            <!-- Pesan yang DILIHAT KANDIDAT bila tidak lolos. Tulis
                                 dengan nada manis — ini kesan terakhir mereka. -->
                            <div class="pgk-pesan">
                                <label class="wca-field-lbl">
                                    <i class="bi bi-chat-heart"></i> Pesan penolakan untuk kandidat
                                    <span class="pgk-opt">opsional</span>
                                </label>
                                <el-input
                                    v-model="S.pesanGugur"
                                    type="textarea"
                                    :rows="2"
                                    :placeholder="contohPesan"
                                    maxlength="500"
                                    show-word-limit
                                />
                                <button class="pgk-isipesan" type="button" @click="S.pesanGugur = contohPesan">
                                    <i class="bi bi-magic"></i> Pakai contoh
                                </button>
                            </div>
                        </div>

                        <div v-if="!form.syarat.length" class="pgk-empty">
                            <template v-if="terkunci">Pilih kategori dulu.</template>
                            <template v-else>Tanpa syarat — semua pelamar lolos penyaringan otomatis dan diperiksa admin satu per satu.</template>
                        </div>
                    </div>
                </div>
            </div>
        </AdminModal>

        <ConfirmModal :show="delShow" title="Hapus Program" :busy="deleting" confirm-label="Ya, Hapus" note="Program beserta batch, posisi & kriteria-nya akan dihapus permanen." @cancel="delShow = false" @confirm="confirmDelete">
            Yakin ingin menghapus program <strong>{{ delTarget?.nama }}</strong>?
        </ConfirmModal>

        <transition name="wca-toast"><div v-if="toast" class="wca-toast"><i class="bi bi-check-circle-fill"></i> {{ toast }}</div></transition>
    </div>
</template>

<script>
import axios from 'axios';
import { Head } from '@inertiajs/vue3';
import AdminModal from '@career/AdminModal.vue';
import ConfirmModal from '@career/ConfirmModal.vue';
import AuditStamp from '@career/AuditStamp.vue';
import RefSelect from '@career/RefSelect.vue';
import { skemaFormulir, semuaField } from '@career/formulir';

const API = '/api/v1/program-kegiatan';
const CFG = { headers: { Accept: 'application/json' } };

export default {
    components: { Head, AdminModal, ConfirmModal, AuditStamp, RefSelect },
    data() {
        return {
            list: [],
            loading: false,
            open: null,
            tab: '',
            show: false,
            editingId: null,
            form: { nama: '', kategori: '', warna: '#4f46e5', mode: 'ROLLING', alur: '', jadwal: '', penyelenggara: '', status: 'DRAFT', batch: [], posisi: [], syarat: [] },
            tahapFormulir: [],
            fieldTurunan: [],
            palette: ['#4f46e5', '#7c3aed', '#0ea5e9', '#10b981', '#f59e0b', '#ef4444', '#64748b'],
            operatorOptions: [
                { value: '>=', label: 'minimal (>=)' },
                { value: '<=', label: 'maksimal (<=)' },
                { value: '=', label: 'sama dengan' },
                { value: '!=', label: 'tidak sama dengan' },
                { value: '>', label: 'lebih dari' },
                { value: '<', label: 'kurang dari' },
                { value: 'ANTARA', label: 'antara' },
                { value: 'ADA_DI', label: 'salah satu dari' },
                { value: 'TIDAK_ADA_DI', label: 'bukan salah satu dari' },
            ],
            presets: [],
            // Contoh pesan penolakan bernada hangat — tombol "Pakai contoh" mengisi ini.
            contohPesan: 'Terima kasih sudah mendaftar di EVO Group. Setelah kami tinjau, untuk kesempatan kali ini Anda belum memenuhi kualifikasi yang dibutuhkan. Kami sangat menghargai minat & waktu Anda, dan berharap dapat bertemu kembali di kesempatan berikutnya. Sukses selalu! 🙏',
            delShow: false,
            delTarget: null,
            deleting: false,
            saving: false,
            toast: '',
            tm: null,
        };
    },
    computed: {
        filtered() { return this.tab ? this.list.filter((p) => p.kategori === this.tab) : this.list; },
        aktif() { return this.filtered.filter((p) => p.status === 'BERJALAN').length; },
        totalKuotaPosisi() { return this.filtered.reduce((n, p) => n + this.totalKuota(p), 0); },

        /** Semua field selain Kategori terkunci sampai kategori dipilih. */
        terkunci() { return !this.form.kategori; },

        /**
         * Mode ubah. Dipakai untuk menyembunyikan field yang jawabannya sudah pasti
         * pada program baru: status lowongan (BUKA), status batch (AKTIF), dan
         * jumlah terisi (0). Menanyakannya saat membuat cuma bikin ragu.
         */
        mengubah() { return Boolean(this.editingId); },

        /** Pagu kursi = total kuota posisi yang ditarik dari MPP. */
        kuotaMpp() { return this.form.posisi.reduce((n, l) => n + (Number(l.kuota) || 0), 0); },
        kuotaBatch() { return this.form.batch.reduce((n, b) => n + (Number(b.kuota) || 0), 0); },
        kuotaBatchLebih() { return this.kuotaMpp > 0 && this.kuotaBatch > this.kuotaMpp; },
    },
    mounted() {
        this.load();
        this.loadPresets();
        this.loadFieldTurunan();
    },
    methods: {
        katLabel(k) { return { REKRUTMEN: 'Rekrutmen', MT: 'Management Trainee', INTERNSHIP: 'Internship' }[k] || k || '—'; },
        katBadge(k) { return { MT: 'wca-b--gold', INTERNSHIP: 'wca-b--green', REKRUTMEN: 'wca-b--sky' }[k] || 'wca-b--slate'; },
        modeLabel(m) { return { TERSTRUKTUR: 'Terstruktur', ROLLING: 'Rolling' }[m] || m || '—'; },
        statusLabel(s) { return { DRAFT: 'Draft', BERJALAN: 'Berjalan', SELESAI: 'Selesai' }[s] || s; },
        statusBadge(s) { return { DRAFT: 'wca-b--amber', BERJALAN: 'wca-b--green', SELESAI: 'wca-b--slate' }[s] || 'wca-b--slate'; },
        statusIkon(s) { return { DRAFT: 'bi-pencil-square', BERJALAN: 'bi-play-circle', SELESAI: 'bi-lock-fill' }[s] || 'bi-dot'; },
        totalKuota(p) { return (p.posisi || []).reduce((n, x) => n + (Number(x.kuota) || 0), 0); },

        // ── Penyusun syarat ────────────────────────────────────────────
        /**
         * Tahap berformulir milik alur terpilih. Syarat hanya bisa memeriksa
         * field yang memang DITANYAKAN formulir, jadi daftarnya harus mengikuti
         * alur — bukan diketik bebas.
         */
        async loadTahapFormulir() {
            if (!this.form.alur) { this.tahapFormulir = []; return; }
            try {
                const res = await axios.get('/api/v1/karir/options/tahap-formulir', { ...CFG, params: { alur: this.form.alur } });
                this.tahapFormulir = res.data.result || [];
            } catch (e) {
                this.tahapFormulir = [];
            }
        },
        async loadFieldTurunan() {
            try {
                const res = await axios.get('/api/v1/karir/options/field-turunan', CFG);
                this.fieldTurunan = res.data.result || [];
            } catch (e) {
                this.fieldTurunan = [];
            }
        },
        /** Field milik formulir yang dipasang di tahap tsb — dibaca dari skema di kode. */
        fieldTahap(tahapId) {
            const t = this.tahapFormulir.find((x) => x.tahapId === tahapId);
            if (!t || !t.komponen) return [];
            return semuaField(skemaFormulir(t.komponen))
                .filter((f) => f.tipe !== 'file' && f.tipe !== 'consent')
                .map((f) => ({ key: f.key, label: f.label }));
        },
        /** Alur berganti -> tahap berformulir berubah, syarat lama tidak berlaku lagi. */
        onAlurGanti() {
            this.form.jadwal = '';
            if (this.form.syarat.length) {
                this.form.syarat = [];
                this.notice('Alur diganti — syarat dikosongkan karena tahapnya berubah.');
            }
            this.loadTahapFormulir();
        },
        onTahapSyarat(S, tahapId) {
            const t = this.tahapFormulir.find((x) => x.tahapId === tahapId);
            S.formulir = t ? t.formulir : '';
            // Field milik formulir lama tidak berlaku di formulir baru.
            S.aturan.aturan = [];
        },
        phNilai(op) {
            if (op === 'ANTARA') return 'mis. 20,25';
            if (op === 'ADA_DI' || op === 'TIDAK_ADA_DI') return 'mis. S1,D4';
            return 'nilai';
        },
        async load() {
            this.loading = true;
            try {
                const res = await axios.get(API, CFG);
                this.list = res.data.result || [];
            } catch (e) {
                this.notice('Gagal memuat data program.');
            } finally {
                this.loading = false;
            }
        },
        openCreate() {
            this.editingId = null;
            // Semua field sengaja KOSONG — mode & warna baru terisi dari preset
            // Master Kategori begitu kategori dipilih. status juga kosong: backend
            // memaksa BERJALAN untuk program baru.
            this.form = { nama: '', kategori: '', warna: '', mode: '', alur: '', jadwal: '', penyelenggara: '', status: '', batch: [], posisi: [], syarat: [] };
            this.show = true;
            this.loadTahapFormulir();
        },

        /**
         * Kategori berubah -> terapkan preset Master Kategori (mode, alur, warna) dan
         * buang pilihan turunan yang jadi tidak berlaku. Alur & jadwal milik kategori
         * lain tidak boleh menempel di program ini.
         */
        onKategori(opt) {
            this.form.alur = '';
            this.form.jadwal = '';
            // Posisi ikut dibuang: MPP milik kategori lama tidak berlaku di kategori baru.
            if (this.form.posisi.length) {
                this.notice('Kategori diganti — daftar posisi dikosongkan, pilih ulang dari MPP.');
            }
            this.form.posisi = [];

            const preset = this.presets.find((p) => p.value === (opt?.value ?? this.form.kategori));
            if (!preset) return;
            if (preset.mode) this.form.mode = preset.mode;
            if (preset.warna) this.form.warna = preset.warna;
            if (preset.alur) this.form.alur = preset.alur;
            this.loadTahapFormulir();
        },

        /** Satu MPP dipilih -> salin field turunannya. Nilai-nilai ini dikunci di UI. */
        onMpp(baris, opt) {
            if (!opt) {
                Object.assign(baris, { posisi: '', departemen: '', lokasi: '', level: '', kuota: 0, kuotaMpp: 0 });
                return;
            }
            const kembar = this.form.posisi.filter((x) => x.mppRef === opt.value).length > 1;
            if (kembar) {
                baris.mppRef = '';
                return this.notice(`"${opt.posisi}" sudah ada di daftar posisi.`);
            }
            Object.assign(baris, {
                posisi: opt.posisi,
                departemen: opt.departemen,
                lokasi: opt.lokasi,
                level: opt.level,
                kuotaMpp: opt.kuota,
                kuota: opt.kuota,
            });
        },

        async loadPresets() {
            try {
                const res = await axios.get('/api/v1/karir/options/kategori-preset', CFG);
                this.presets = res.data.result || [];
            } catch (e) {
                this.presets = [];
            }
        },
        openEdit(p) {
            this.editingId = p.id;
            this.form = {
                nama: p.nama || '',
                kategori: p.kategori || '',
                warna: p.warna || '#4f46e5',
                mode: p.mode || 'ROLLING',
                alur: p.alur || '',
                jadwal: p.jadwal || '',
                penyelenggara: p.penyelenggara || '',
                status: p.status || 'DRAFT',
                batch: (p.batch || []).map((b) => ({ nama: b.nama, kuota: b.kuota, terisi: b.terisi, status: b.status })),
                // kuotaMpp diisi dari kuota tersimpan supaya batas atas input tetap
                // masuk akal sebelum admin memilih ulang MPP-nya.
                posisi: (p.posisi || []).map((l) => ({ mppRef: l.mppRef || '', posisi: l.posisi, departemen: l.departemen, lokasi: l.lokasi, level: l.level || '', kuota: l.kuota, kuotaMpp: l.kuota, status: l.status || 'BUKA' })),
                syarat: (p.syarat || []).map((s) => ({ nama: s.nama, tahapId: s.tahapId, formulir: s.formulir, aturan: s.aturan && s.aturan.aturan ? s.aturan : { penghubung: 'DAN', aturan: [] }, aksi: s.aksi || 'TANDAI', pesanGugur: s.pesanGugur || '', uji: !!s.uji, aktif: s.aktif !== false })),
            };
            this.show = true;
        },
        addBatch() { this.form.batch.push({ nama: '', kuota: 0, terisi: 0, status: 'AKTIF' }); },

        /**
         * Batas kuota satu baris batch = pagu MPP dikurangi kursi batch LAIN.
         * Dihitung per baris supaya admin tahu sisa yang masih boleh dipakai,
         * bukan sekadar ditolak saat menyimpan.
         */
        batasBatch(i) {
            const lain = this.form.batch.reduce((n, b, j) => (j === i ? n : n + (Number(b.kuota) || 0)), 0);
            return Math.max(0, this.kuotaMpp - lain);
        },
        addPosisi() { this.form.posisi.push({ mppRef: '', posisi: '', departemen: '', lokasi: '', level: '', kuota: 0, kuotaMpp: 0, status: 'BUKA' }); },
        addSyarat() { this.form.syarat.push({ nama: '', tahapId: null, formulir: '', aturan: { penghubung: 'DAN', aturan: [] }, aksi: 'TANDAI', pesanGugur: '', uji: false, aktif: true }); },
        addKondisi(S) { S.aturan.aturan.push({ field: '', operator: '>=', nilai: '' }); },
        async save() {
            if (this.saving) return;
            if (!this.form.nama.trim()) return this.notice('Nama program wajib diisi.');
            if (!this.form.kategori) return this.notice('Kategori wajib dipilih.');
            // Batch, posisi & kriteria boleh KOSONG. Yang divalidasi hanya baris yang
            // benar-benar ditambahkan admin.
            if (this.form.batch.some((b) => !b.nama || !String(b.nama).trim())) return this.notice('Setiap batch wajib punya nama.');
            if (this.form.posisi.some((l) => !l.mppRef)) return this.notice('Setiap posisi wajib dipilih dari daftar MPP.');
            if (this.kuotaBatchLebih) return this.notice(`Total kursi batch (${this.kuotaBatch}) melebihi kuota MPP (${this.kuotaMpp}).`);
            if (this.form.syarat.some((s) => !s.nama || !String(s.nama).trim())) return this.notice('Setiap syarat wajib punya nama.');
            if (this.form.syarat.some((s) => !s.tahapId)) return this.notice('Setiap syarat wajib menyebut diperiksa di tahap mana.');
            if (this.form.syarat.some((s) => s.aturan.aturan.some((k) => !k.field || !k.nilai))) return this.notice('Setiap kondisi wajib punya field & nilai.');
            this.saving = true;
            const payload = { ...this.form };
            // kuotaMpp hanya batas atas untuk input, bukan data program.
            payload.posisi = payload.posisi.map(({ kuotaMpp, ...sisa }) => sisa);
            // Saat membuat, status ditentukan backend (selalu BERJALAN).
            if (!this.editingId) delete payload.status;
            try {
                if (this.editingId) {
                    await axios.put(`${API}/${this.editingId}`, payload, CFG);
                    this.notice('Program diperbarui.');
                } else {
                    await axios.post(API, payload, CFG);
                    this.notice('Program ditambahkan.');
                }
                this.show = false;
                await this.load();
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal menyimpan.');
            } finally {
                this.saving = false;
            }
        },
        async setStatus(p, v) {
            const prev = p.status;
            p.status = v ? 'BERJALAN' : 'DRAFT';
            try {
                await axios.patch(`${API}/${p.id}/toggle`, { aktif: v }, CFG);
                this.notice(`Program "${p.nama}" ${v ? 'dijalankan' : 'dijadikan draft'}.`);
            } catch (e) {
                p.status = prev;
                this.notice('Gagal mengubah status.');
            }
        },
        askRemove(p) { this.delTarget = p; this.delShow = true; },
        async confirmDelete() {
            if (this.deleting || !this.delTarget) return;
            const p = this.delTarget;
            this.deleting = true;
            try {
                await axios.delete(`${API}/${p.id}`, CFG);
                this.notice('Program dihapus.');
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
.pgk-head-act { display: inline-flex; align-items: center; gap: .4rem; margin-left: auto; }
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

/* Pembungkus seksi opsional. Sengaja dibedakan dari .wca-fsection biasa supaya
   terbaca sebagai "lanjutan", bukan langkah wajib berikutnya. */
.pgk-extra { border: 1px dashed rgba(11, 16, 51, .16); border-radius: 14px; padding: .9rem; background: rgba(248, 250, 252, .6); }
.pgk-extra__head { display: flex; align-items: baseline; flex-wrap: wrap; gap: .25rem .6rem; margin-bottom: .75rem; padding-left: .15rem; }
.pgk-extra__title { font-size: 12px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: #475569; }
.pgk-extra__note { font-size: 11.5px; color: #94a3b8; }
.pgk-extra .wca-fsection { background: #fff; }
.pgk-extra .wca-fsection + .wca-fsection { margin-top: .75rem; }
.pgk-req { font-weight: 600; font-size: 11px; color: #b45309; background: #fef3c7; border-radius: 999px; padding: .1rem .45rem; margin-left: .35rem; }
.pgk-hint { font-size: 11.5px; color: #64748b; margin-top: .3rem; }
.pgk-hint--warn { color: #b45309; }
.pgk-syarat { border: 1px solid rgba(79,70,229,.18); border-radius: 12px; padding: .7rem; margin-bottom: .6rem; background: rgba(248,250,252,.6); }
.pgk-syarat__head { display: flex; align-items: center; gap: .45rem; margin-bottom: .55rem; }
.pgk-syarat__foot { display: flex; align-items: center; flex-wrap: wrap; gap: .6rem; margin-top: .55rem; padding-top: .5rem; border-top: 1px dashed rgba(11,16,51,.1); font-size: 12px; }
.pgk-pesan { margin-top: .6rem; }
.pgk-pesan .wca-field-lbl { display: flex; align-items: center; gap: .3rem; color: #4338ca; }
.pgk-isipesan { margin-top: .35rem; border: 0; background: transparent; color: #7c3aed; font: inherit; font-size: 11.5px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: .3rem; padding: 0; }
.pgk-isipesan:hover { text-decoration: underline; }
.pgk-aturan { border: 1px dashed rgba(11,16,51,.16); border-radius: 10px; padding: .55rem; background: #fff; }
.pgk-aturan__bar { display: flex; align-items: center; flex-wrap: wrap; gap: .4rem; font-size: 12px; font-weight: 600; color: #475569; margin-bottom: .5rem; }
.pgk-kondisi { display: flex; align-items: center; gap: .4rem; margin-bottom: .4rem; }
.pgk-syarat-ro { border-left: 3px solid #4f46e5; padding: .5rem .7rem; margin-bottom: .5rem; background: rgba(79,70,229,.04); border-radius: 0 10px 10px 0; }
.pgk-syarat-ro__top { display: flex; align-items: center; flex-wrap: wrap; gap: .4rem; margin-bottom: .35rem; font-size: 13px; }
.pgk-gab { font-size: 10.5px; font-weight: 700; letter-spacing: .06em; color: #4338ca; background: rgba(79,70,229,.12); border-radius: 999px; padding: .1rem .45rem; }
.pgk-mpp { font-size: 11.5px; color: #4338ca; background: rgba(79, 70, 229, .08); border-radius: 6px; padding: .1rem .35rem; }

.pgk-headright { display: inline-flex; align-items: center; gap: .5rem; }
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
}
@media (max-width: 560px) {
    .pgk-row__grid--batch,
    .pgk-row__grid--posisi,
    .pgk-row__grid--kriteria { grid-template-columns: 1fr; }
    .pgk-row .wca-iconbtn { margin-top: 0; }
    .pgk-row__del { margin-top: 0 !important; }
}
</style>
