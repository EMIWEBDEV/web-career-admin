<!-- WEB CAREER — Admin: Program Kegiatan = DEFINISI program (klasifikasi+mode+alur+jadwal+posisi). Publikasi (channel/window) di menu Pembukaan Program. -->
<template>
    <Head><title>Program Kegiatan - Web Career</title></Head>
    <div class="wca">
        <div class="wca-phead">
            <div>
                <h1>Program Kegiatan</h1>
                <p>Definisi program (isi + alur + jadwal). Kapan & ke siapa dibuka (Umum/Kampus) diatur di <b>Pembukaan Program</b> — 1 program bisa dibuka ke banyak channel tanpa duplikasi.</p>
            </div>
            <div class="wca-phead__actions">
                <Link href="/karir/pembukaan" class="wca-btn wca-btn--ghost"><i class="bi bi-megaphone"></i> Pembukaan</Link>
                <button class="wca-btn wca-btn--primary" @click="openCreate"><i class="bi bi-plus-lg"></i> Buat Program</button>
            </div>
        </div>

        <!-- Tab klasifikasi -->
        <div class="wca-segt">
            <button class="wca-segt__it" :class="{ on: tab === '' }" @click="tab = ''"><i class="bi bi-grid"></i> Semua</button>
            <button class="wca-segt__it" :class="{ on: tab === 'REKRUTMEN' }" @click="tab = 'REKRUTMEN'"><i class="bi bi-briefcase"></i> Recruitment</button>
            <button class="wca-segt__it" :class="{ on: tab === 'MT' }" @click="tab = 'MT'"><i class="bi bi-mortarboard"></i> Management Trainee</button>
            <button class="wca-segt__it" :class="{ on: tab === 'INTERNSHIP' }" @click="tab = 'INTERNSHIP'"><i class="bi bi-backpack"></i> Internship</button>
        </div>

        <div class="wca-stats">
            <div class="wca-stat"><div class="wca-stat__top"><span class="wca-stat__ico"><i class="bi bi-diagram-3-fill"></i></span></div><div class="wca-stat__num">{{ filtered.length }}</div><div class="wca-stat__label">Program</div></div>
            <div class="wca-stat"><div class="wca-stat__top"><span class="wca-stat__ico" style="background:rgba(16,185,129,.12);color:#059669"><i class="bi bi-broadcast"></i></span></div><div class="wca-stat__num">{{ aktif }}</div><div class="wca-stat__label">Aktif</div></div>
            <div class="wca-stat"><div class="wca-stat__top"><span class="wca-stat__ico" style="background:rgba(139,92,246,.12);color:#7c3aed"><i class="bi bi-people"></i></span></div><div class="wca-stat__num">{{ totalPelamar }}</div><div class="wca-stat__label">Total Pelamar</div></div>
        </div>

        <div class="wca-acc">
            <div v-for="p in filtered" :key="p.id" class="wca-acc__item" :class="{ open: open === p.id }">
                <button class="wca-acc__head" @click="toggle(p.id)">
                    <span class="wca-acc__chev"><i class="bi bi-chevron-right"></i></span>
                    <span class="wca-acc__title">
                        <strong><span class="wca-dot" :style="{ background: warnaHex(p.warna) }"></span> {{ p.nama }}</strong>
                        <small>{{ p.id }} · {{ p.penyelenggara }} · Alur {{ p.alurNama }}</small>
                    </span>
                    <span class="wca-acc__tags">
                        <span class="wca-badge" :class="katBadge(p.kategori)">{{ katLabel(p.kategori) }}</span>
                        <span class="wca-badge" :class="p.mode === 'TERSTRUKTUR' ? 'wca-b--indigo' : 'wca-b--slate'">{{ modeLabel(p.mode) }}</span>
                        <span class="wca-badge" :class="progStatusBadge(p.status)"><i class="bi" :class="progStatusIkon(p.status)"></i> {{ progStatusLabel(p.status) }}</span>
                    </span>
                    <span class="wca-acc__kpi"><b>{{ totalKuota(p) }}</b><small>kuota</small></span>
                </button>

                <div class="wca-acc__body">
                    <div class="wca-acc__inner">
                        <div class="wca-dinfo wca-dinfo--4" style="margin-bottom:1rem">
                            <div><small>Mode</small><b>{{ modeLabel(p.mode) }}</b></div>
                            <div><small>Alur Seleksi</small><b>{{ p.alurNama }}</b></div>
                            <div><small>Total Pelamar</small><b>{{ p.totalPelamar }}</b></div>
                            <div><small>Penanggung Jawab</small><b>{{ p.penyelenggara }}</b></div>
                        </div>

                        <div v-if="p.kriteria && p.kriteria.length" style="margin-bottom:1rem">
                            <div class="wca-fsection__label" style="margin-bottom:.5rem"><i class="bi bi-sliders2"></i> Syarat & Kriteria (auto-gugur)</div>
                            <div class="wca-krset"><span v-for="(r, i) in p.kriteria" :key="i" class="wca-krrule wca-krrule--ro"><i class="bi bi-funnel"></i> {{ ruleText(r) }}</span></div>
                        </div>

                        <div v-if="p.mode === 'TERSTRUKTUR' && progJadwal(p)" style="margin-bottom:1rem">
                            <div class="wca-fsection__label" style="margin-bottom:.6rem"><i class="bi bi-bar-chart-steps"></i> Jadwal Kegiatan — {{ progJadwal(p).kegiatan }} ({{ progJadwal(p).agenda.length }} agenda)</div>
                            <ol class="wca-jlist">
                                <li v-for="(a, i) in progJadwal(p).agenda" :key="i">
                                    <span class="wca-jtag" :class="jenisKelas(a.jenis)"><i class="bi" :class="jenisIkon(a.jenis)"></i></span>
                                    <span class="wca-jlist__lbl">{{ a.label }}</span>
                                    <span class="wca-jlist__date"><i class="bi bi-calendar3"></i> {{ fmt(a.mulai) }} – {{ fmt(a.selesai) }}</span>
                                </li>
                            </ol>
                        </div>

                        <div v-if="p.kategori === 'MT'" style="margin-bottom:1rem">
                            <div class="wca-fsection__label" style="display:flex;align-items:center;justify-content:space-between;margin-bottom:.5rem">
                                <span><i class="bi bi-collection"></i> Batch ({{ p.batch.length }})</span>
                                <button v-if="p.status !== 'SELESAI'" class="wca-btn wca-btn--soft wca-btn--sm" @click="openBatch(p)"><i class="bi bi-plus-circle"></i> Tambah Batch</button>
                            </div>
                            <div v-if="p.batch.length" class="wca-tagset"><span v-for="b in p.batch" :key="b.id"><i class="bi bi-people"></i> {{ b.nama }} · {{ b.terisi }}/{{ b.kuota }} kursi · {{ b.pelamar }} pelamar</span></div>
                            <div v-else class="wca-hint" style="margin:0"><i class="bi bi-info-circle"></i> Belum ada batch. Contoh: <b>Batch 1 (Mei)</b>, <b>Batch 2 (Desember)</b>.</div>
                        </div>

                        <div class="wca-fsection__label" style="margin-bottom:.6rem;display:flex;align-items:center;justify-content:space-between">
                            <span><i class="bi bi-briefcase"></i> Posisi / Lowongan ({{ p.posisi.length }})</span>
                            <span class="wca-badge wca-b--indigo"><i class="bi bi-people"></i> Total kuota {{ totalKuota(p) }}</span>
                        </div>
                        <div class="wca-tablewrap">
                            <table class="wca-table">
                                <thead><tr><th>Posisi</th><th>Lokasi</th><th>Kuota</th><th>Pelamar</th><th>Status</th></tr></thead>
                                <tbody>
                                    <tr v-for="l in p.posisi" :key="l.id">
                                        <td><strong>{{ l.posisi }}</strong><br /><small style="color:var(--muted);font-weight:700">{{ l.departemen }}</small></td>
                                        <td><i class="bi bi-geo-alt" style="color:var(--indigo)"></i> {{ l.lokasi }}</td>
                                        <td>{{ l.kuota }}</td>
                                        <td><strong>{{ l.pelamar }}</strong></td>
                                        <td><span class="wca-badge" :class="statusBadge(l.status)">{{ l.status }}</span></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div v-if="p.status === 'SELESAI'" class="wca-note" style="margin:0 0 .8rem;background:#f1f5f9;border-color:#e2e8f0;color:#475569">
                            <i class="bi bi-lock-fill"></i>
                            <span><b>Program terkunci (Selesai).</b> Program, batch, posisi & pembukaan tidak bisa diubah lagi — data dibekukan untuk pelaporan.</span>
                        </div>
                        <div class="wca-acc__foot">
                            <template v-if="p.status !== 'SELESAI'">
                                <Link href="/karir/pembukaan" class="wca-btn wca-btn--soft wca-btn--sm"><i class="bi bi-megaphone"></i> Buka / Publikasikan</Link>
                                <Link href="/karir/penjadwalan" class="wca-btn wca-btn--ghost wca-btn--sm"><i class="bi bi-calendar-check"></i> Jadwalkan Tes</Link>
                                <button v-if="p.status === 'DRAFT'" class="wca-btn wca-btn--soft wca-btn--sm" @click="aktifkan(p)"><i class="bi bi-play-circle"></i> Aktifkan</button>
                                <button class="wca-btn wca-btn--ghost wca-btn--sm" @click="openEdit(p)"><i class="bi bi-pencil"></i> Ubah Program</button>
                                <button class="wca-btn wca-btn--ghost wca-btn--sm" style="margin-left:auto;color:#b91c1c" @click="askClose(p)"><i class="bi bi-lock"></i> Tutup Program</button>
                            </template>
                            <template v-else>
                                <button class="wca-btn wca-btn--ghost wca-btn--sm" @click="askReopen(p)"><i class="bi bi-unlock"></i> Buka Kembali</button>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
            <div v-if="!filtered.length" class="wca-empty"><i class="bi bi-diagram-3"></i><h4>Belum ada program pada kategori ini</h4></div>
        </div>

        <!-- Wizard Create/Edit -->
        <AdminModal :show="show" :title="editingId ? 'Ubah Program' : 'Buat Program Kegiatan'" subtitle="Definisi program: klasifikasi, mode, alur, jadwal, posisi. Publikasi diatur terpisah." icon="bi-diagram-3-fill" lg :save-label="editingId ? 'Perbarui' : 'Buat Program'" @close="show = false" @save="save">
            <!-- 1. Klasifikasi & identitas -->
            <div class="wca-fsection">
                <div class="wca-fsection__label"><i class="bi bi-tags"></i> Klasifikasi & Identitas</div>
                <div class="wca-form">
                    <div><label class="wca-field-lbl">Klasifikasi</label>
                        <el-select v-model="form.klasifikasiId" placeholder="Pilih klasifikasi" @change="onKlasifikasi">
                            <el-option v-for="k in klasifikasi" :key="k.id" :label="`${k.nama} (${k.kategori})`" :value="k.id" />
                        </el-select>
                    </div>
                    <div class="wca-frow">
                        <div><label class="wca-field-lbl">Nama Program</label><el-input v-model="form.nama" placeholder="Rekrutmen Reguler Q4 2026" /></div>
                        <div><label class="wca-field-lbl">Penyelenggara</label><el-input v-model="form.penyelenggara" placeholder="Tim Rekrutmen" /></div>
                    </div>
                </div>
            </div>

            <!-- 2. Mode & alur -->
            <div class="wca-fsection">
                <div class="wca-fsection__label"><i class="bi bi-sliders"></i> Mode & Alur Seleksi</div>
                <div class="wca-form">
                    <div class="wca-frow">
                        <div><label class="wca-field-lbl">Mode Pelaksanaan</label>
                            <el-select v-model="form.mode" placeholder="Pilih mode">
                                <el-option v-for="mo in modeOptions" :key="mo.kode" :label="mo.nama" :value="mo.kode" />
                            </el-select>
                            <small style="display:block;margin-top:.3rem;color:var(--muted);font-weight:700;font-size:.72rem"><i class="bi bi-info-circle"></i> Terisi dari preset kategori — bisa diubah bila program ini perlu berbeda.</small>
                        </div>
                        <div><label class="wca-field-lbl">Alur Seleksi</label>
                            <el-select v-model="form.alurId" placeholder="Pilih alur" @change="onAlurChangeProg">
                                <el-option v-for="a in alurByKat" :key="a.id" :label="a.nama" :value="a.id" />
                            </el-select>
                        </div>
                    </div>
                    <div v-if="alurStages(form.alurId).length" class="wca-peek">
                        <i class="bi bi-eye"></i> <b>Tahapan alur:</b>
                        <template v-for="(st, si) in alurStages(form.alurId)" :key="si"><span class="wca-peek__st">{{ st }}</span><i v-if="si < alurStages(form.alurId).length - 1" class="bi bi-chevron-right wca-peek__sep"></i></template>
                    </div>
                </div>
            </div>

            <!-- 3. Jadwal kegiatan (terstruktur) — PILIH jadwal (master), tak input ulang tanggal -->
            <div v-if="form.mode === 'TERSTRUKTUR'" class="wca-fsection">
                <div class="wca-fsection__label"><i class="bi bi-bar-chart-steps"></i> Jadwal Kegiatan (pilih dari Master Jadwal)</div>
                <div class="wca-form">
                    <div><label class="wca-field-lbl">Jadwal Kegiatan</label>
                        <el-select v-model="form.jadwalId" placeholder="Pilih jadwal (sesuai alur)" style="width:100%">
                            <el-option v-for="jd in jadwalByAlur" :key="jd.id" :label="`${jd.kegiatan} — ${jd.agenda.length} agenda`" :value="jd.id" />
                        </el-select>
                        <small v-if="!jadwalByAlur.length" style="display:block;margin-top:.3rem;color:#b45309;font-weight:700;font-size:.72rem"><i class="bi bi-exclamation-triangle"></i> Belum ada jadwal untuk alur ini — buat dulu di <b>Master Jadwal Kegiatan</b>.</small>
                    </div>
                    <div v-if="selectedJadwal" class="wca-jpreview">
                        <div class="wca-jpreview__head"><i class="bi bi-calendar-event"></i> {{ fmt(jadwalBase(selectedJadwal)) }} — {{ fmt(jadwalEnd(selectedJadwal)) }} <span class="wca-badge wca-b--slate">{{ selectedJadwal.agenda.length }} agenda</span></div>
                        <ol class="wca-jlist">
                            <li v-for="(a, i) in selectedJadwal.agenda" :key="i">
                                <span class="wca-jtag" :class="jenisKelas(a.jenis)"><i class="bi" :class="jenisIkon(a.jenis)"></i></span>
                                <span class="wca-jlist__lbl">{{ a.label }}</span>
                                <span class="wca-jlist__date"><i class="bi bi-calendar3"></i> {{ fmt(a.mulai) }} – {{ fmt(a.selesai) }}</span>
                            </li>
                        </ol>
                    </div>
                    <label class="wca-toggle-row">
                        <el-switch :model-value="true" disabled />
                        <span><b>Jadwal tampil ke pelamar</b> — <b>wajib</b> untuk mode Terstruktur (pelamar perlu tahu tanggal kohort).</span>
                    </label>
                </div>
            </div>

            <!-- 3b. Rolling — jadwal opsional (tanpa tanggal kohort) -->
            <div v-else class="wca-fsection">
                <div class="wca-fsection__label"><i class="bi bi-arrow-repeat"></i> Jadwal (Mode Rolling)</div>
                <label class="wca-toggle-row">
                    <el-switch v-model="form.tampilJadwal" />
                    <span><b>Tampilkan estimasi linimasa ke pelamar</b> <small style="color:var(--muted)">(opsional)</small> — Rolling jalan per SLA tanpa tanggal kohort.</span>
                </label>
            </div>

            <!-- 4. Posisi dari MPP -->
            <div class="wca-fsection">
                <div class="wca-fsection__label" style="display:flex;align-items:center;justify-content:space-between">
                    <span><i class="bi bi-briefcase"></i> Posisi (tarik dari MPP)</span>
                    <span class="wca-badge wca-b--indigo"><i class="bi bi-people"></i> Total kuota {{ kuotaTerpilih }}</span>
                </div>
                <label v-for="m in mpp" :key="m.ref" class="wca-mpprow" :class="{ sel: picked.includes(m.ref) }">
                    <input type="checkbox" :value="m.ref" v-model="picked" style="width:1.1rem;height:1.1rem;accent-color:var(--indigo)" />
                    <div class="wca-mpprow__main"><strong>{{ m.posisi }}</strong><small>{{ m.ref }} · {{ m.departemen }} · {{ m.lokasi }}</small></div>
                    <span class="wca-badge wca-b--slate">Kuota {{ m.kuota }}</span>
                </label>
                <div class="wca-flow__note" style="margin:.5rem 0 0"><i class="bi bi-info-circle"></i> <b>Total kuota program = jumlah kuota MPP terpilih</b> ({{ kuotaTerpilih }}) — dihitung otomatis, bukan diketik manual.</div>
            </div>

            <!-- 5. Batch (khusus MT — dibuat langsung di sini) -->
            <div v-if="isMTKat" class="wca-fsection">
                <div class="wca-fsection__label" style="justify-content:space-between;display:flex;align-items:center">
                    <span><i class="bi bi-collection"></i> Batch / Gelombang ({{ form.batch.length }})</span>
                    <button class="wca-btn wca-btn--soft wca-btn--sm" type="button" @click="addWizBatch"><i class="bi bi-plus-circle"></i> Tambah Batch</button>
                </div>
                <div v-if="!form.batch.length" class="wca-hint" style="margin:0 0 .5rem"><i class="bi bi-info-circle"></i> MT boleh berjalan beberapa gelombang dalam setahun. Contoh: <b>Batch 1 — Mei 2026</b>, <b>Batch 2 — Desember 2026</b>. Batch = <b>nama + kuota</b>; window buka/tutup diatur saat <b>Pembukaan</b>.</div>
                <div v-for="(b, i) in form.batch" :key="i" class="wca-stagecard">
                    <div class="wca-stagecard__num">{{ i + 1 }}</div>
                    <div class="wca-stagecard__body">
                        <div class="wca-frow">
                            <div><label class="wca-field-lbl">Nama Batch</label><el-input v-model="b.nama" placeholder="Batch 1 — Mei 2026" /></div>
                            <div><label class="wca-field-lbl">Kuota Kursi</label><el-input-number v-model="b.kuota" :min="1" controls-position="right" style="width:100%" /></div>
                        </div>
                    </div>
                    <div class="wca-stagecard__actions">
                        <button class="wca-iconbtn wca-iconbtn--danger" title="Hapus" @click="removeWizBatch(i)"><i class="bi bi-trash"></i></button>
                    </div>
                </div>
            </div>

            <!-- 6. Syarat & Kriteria (auto-gugur) -->
            <div class="wca-fsection">
                <div class="wca-fsection__label"><i class="bi bi-sliders2"></i> Syarat & Kriteria <span class="wca-fsection__hint">(pelamar tak memenuhi → gugur otomatis)</span></div>
                <div class="wca-form">
                    <div class="wca-krbuild">
                        <div style="flex:1;min-width:130px"><label class="wca-field-lbl">Kriteria</label>
                            <el-select v-model="ruleForm.field" placeholder="Pilih field" @change="onRuleField">
                                <el-option v-for="k in kriteriaOptions" :key="k.field" :label="k.nama" :value="k.field" />
                            </el-select>
                        </div>
                        <div style="flex:1;min-width:130px"><label class="wca-field-lbl">Operator</label>
                            <el-select v-model="ruleForm.op" placeholder="Operator" :disabled="!ruleDef">
                                <el-option v-for="op in (ruleDef?.ops ?? [])" :key="op" :label="opLabel(op)" :value="op" />
                            </el-select>
                        </div>
                        <div style="flex:1;min-width:120px"><label class="wca-field-lbl">Nilai</label>
                            <el-select v-if="ruleDef?.tipe === 'ENUM_ORD'" v-model="ruleForm.value" placeholder="Pilih">
                                <el-option v-for="o in ruleDef.opsi" :key="o" :label="o" :value="o" />
                            </el-select>
                            <el-input-number v-else-if="ruleDef?.tipe === 'NUMBER'" v-model="ruleForm.value" :min="0" controls-position="right" style="width:100%" />
                            <el-input v-else v-model="ruleForm.value" placeholder="nilai" :disabled="!ruleDef" />
                        </div>
                        <button class="wca-btn wca-btn--soft" type="button" :disabled="!ruleDef" @click="addRule"><i class="bi bi-plus-circle"></i> Tambah</button>
                    </div>
                    <div v-if="form.kriteria.length" class="wca-krset">
                        <span v-for="(r, i) in form.kriteria" :key="i" class="wca-krrule"><i class="bi bi-funnel"></i> {{ ruleText(r) }} <button type="button" @click="removeRule(i)"><i class="bi bi-x"></i></button></span>
                    </div>
                    <div v-else class="wca-hint" style="margin:0"><i class="bi bi-info-circle"></i> Belum ada syarat. Field diambil dari <b>Master Kriteria</b> (bisa ditambah di sana bila kurang).</div>
                </div>
            </div>

            <div v-if="errors.length" class="wca-note wca-note--danger" style="margin:0">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <span><b>Perbaiki dulu:</b><br /><span v-for="(e, i) in errors" :key="i">• {{ e }}<br /></span></span>
            </div>

            <template #footer>
                <span v-if="errors.length" style="margin-right:auto;font-size:.78rem;font-weight:800;color:#b45309"><i class="bi bi-shield-exclamation"></i> {{ errors.length }} masalah</span>
                <button class="wca-btn wca-btn--ghost" @click="show = false"><i class="bi bi-x-circle"></i> Batal</button>
                <button class="wca-btn wca-btn--dark" :disabled="errors.length > 0" @click="save"><i class="bi bi-check-circle-fill"></i> {{ editingId ? 'Perbarui' : 'Buat Program' }}</button>
            </template>
        </AdminModal>

        <!-- Modal tambah batch (MT) -->
        <AdminModal :show="showBatch" title="Tambah Batch" :subtitle="batchTarget?.nama" icon="bi-collection" save-label="Tambah Batch" @close="showBatch = false" @save="saveBatch">
            <div class="wca-fsection">
                <div class="wca-fsection__label"><i class="bi bi-collection"></i> Detail Batch</div>
                <div class="wca-form">
                    <div class="wca-frow">
                        <div><label class="wca-field-lbl">Nama Batch</label><el-input v-model="batchForm.nama" placeholder="Batch 1 — Mei 2026" /></div>
                        <div><label class="wca-field-lbl">Kuota Kursi</label><el-input-number v-model="batchForm.kuota" :min="1" controls-position="right" /></div>
                    </div>
                    <div class="wca-hint" style="margin:0"><i class="bi bi-info-circle"></i> Batch = <b>nama + kuota</b> saja. Window buka/tutup & channel (umum/kampus) diatur saat <b>Pembukaan Program</b>.</div>
                </div>
            </div>
        </AdminModal>

        <!-- Konfirmasi Tutup Program -->
        <AdminModal :show="showClose" title="Tutup Program?" :subtitle="closeTarget?.nama" icon="bi-lock" save-label="Ya, Tutup & Kunci" @close="showClose = false" @save="confirmClose">
            <div class="wca-note wca-note--danger" style="margin:0">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <span>Menutup program akan <b>mengunci seluruhnya</b>: program, batch, posisi, & pembukaan tidak bisa diubah lagi dan tidak tampil di landing. Data dibekukan untuk pelaporan. Lanjutkan?</span>
            </div>
        </AdminModal>

        <!-- Konfirmasi Buka Kembali -->
        <AdminModal :show="showReopen" title="Buka Kembali Program?" :subtitle="closeTarget?.nama" icon="bi-unlock" save-label="Ya, Buka Kembali" @close="showReopen = false" @save="confirmReopen">
            <div class="wca-note wca-note--info" style="margin:0">
                <i class="bi bi-info-circle"></i>
                <span>Program akan kembali <b>Berjalan</b> dan bisa diubah lagi. Gunakan hanya untuk koreksi — aksi ini tercatat.</span>
            </div>
        </AdminModal>

        <transition name="wca-toast"><div v-if="toast" class="wca-toast"><i class="bi bi-check-circle-fill"></i> {{ toast }}</div></transition>
    </div>
</template>


<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';
import AdminModal from '@career/AdminModal.vue';
import { statusBadge } from './careerAdmin';

const props = defineProps({
    programs: { type: Array, default: () => [] },
    klasifikasi: { type: Array, default: () => [] },
    alur: { type: Array, default: () => [] },
    modeOptions: { type: Array, default: () => [] },
    jadwalOptions: { type: Array, default: () => [] },
    kriteriaOptions: { type: Array, default: () => [] },
    mpp: { type: Array, default: () => [] },
});
function alurStages(id) { return props.alur.find((a) => a.id === id)?.stages?.map((s) => s.label) ?? []; }

const list = reactive(props.programs.map((p) => ({ ...p, posisi: p.posisi.map((x) => ({ ...x })), batch: p.batch.map((b) => ({ ...b })) })));

// ── Jadwal (referensi ke Master Jadwal) ──
function jenisKelas(j) { return { SELEKSI: 'ag-sel', RAPAT: 'ag-rap', CAMPAIGN: 'ag-cmp', SOSIALISASI: 'ag-sos', EVALUASI: 'ag-eva', LAINNYA: 'ag-oth' }[j] || 'ag-oth'; }
function jenisIkon(j) { return { SELEKSI: 'bi-funnel', RAPAT: 'bi-people', CAMPAIGN: 'bi-megaphone', SOSIALISASI: 'bi-mortarboard', EVALUASI: 'bi-clipboard-check', LAINNYA: 'bi-dot' }[j] || 'bi-dot'; }
function jadwalById(id) { return props.jadwalOptions.find((j) => j.id === id) || null; }
function progJadwal(p) { return p.jadwalId ? jadwalById(p.jadwalId) : null; }
function jadwalBase(j) { return j.agenda.map((a) => a.mulai).filter(Boolean).sort()[0] || null; }
function jadwalEnd(j) { return j.agenda.map((a) => a.selesai).filter(Boolean).sort().slice(-1)[0] || null; }

// ── Status daur hidup + kuota ──
function progStatusLabel(s) { return { DRAFT: 'Draft', BERJALAN: 'Berjalan', SELESAI: 'Selesai' }[s] || s; }
function progStatusBadge(s) { return { DRAFT: 'wca-b--amber', BERJALAN: 'wca-b--green', SELESAI: 'wca-b--slate' }[s] || 'wca-b--slate'; }
function progStatusIkon(s) { return { DRAFT: 'bi-pencil-square', BERJALAN: 'bi-play-circle', SELESAI: 'bi-lock-fill' }[s] || 'bi-dot'; }
function totalKuota(p) { return p.posisi.reduce((n, x) => n + (x.kuota || 0), 0); }

const tab = ref('');
const open = ref(list[0]?.id ?? null);
function toggle(id) {
    open.value = open.value === id ? null : id;
}

const filtered = computed(() => (tab.value ? list.filter((p) => p.kategori === tab.value) : list));
const aktif = computed(() => filtered.value.filter((p) => p.status === 'BERJALAN').length);
const totalPelamar = computed(() => filtered.value.reduce((n, p) => n + (p.totalPelamar || 0), 0));

const warnaMap = { gold: '#f59e0b', sky: '#0ea5e9', indigo: '#6366f1', green: '#10b981', slate: '#64748b', red: '#ef4444' };
function warnaHex(w) { return warnaMap[w] || '#64748b'; }
function katLabel(k) { return { MT: 'Management Trainee', INTERNSHIP: 'Internship', REKRUTMEN: 'Recruitment' }[k] || 'Recruitment'; }
function katBadge(k) { return { MT: 'wca-b--gold', INTERNSHIP: 'wca-b--green', REKRUTMEN: 'wca-b--sky' }[k] || 'wca-b--sky'; }
function modeLabel(m) { return { TERSTRUKTUR: 'Terstruktur', ROLLING: 'Rolling' }[m] || m; }

const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
function fmt(iso) {
    if (!iso) return '—';
    const d = new Date(iso);
    if (Number.isNaN(d.getTime())) return iso;
    return `${d.getUTCDate()} ${MONTHS[d.getUTCMonth()]} ${d.getUTCFullYear()}`;
}

// ── Wizard ──
const show = ref(false);
const editingId = ref(null);
const picked = ref([]);
const form = reactive({ klasifikasiId: '', nama: '', penyelenggara: '', mode: 'ROLLING', alurId: '', jadwalId: '', tampilJadwal: false, batch: [], kriteria: [] });

// ── Syarat & Kriteria (auto-gugur) — rule builder dinamis dari Master Kriteria ──
function opLabel(op) { return { '<=': '≤ (maksimal)', '>=': '≥ (minimal)', '==': '= (sama dengan)', BETWEEN: 'antara', IN: 'termasuk daftar', NOT_IN: 'kecuali daftar' }[op] || op; }
function kritDef(field) { return props.kriteriaOptions.find((k) => k.field === field) || null; }
const ruleForm = reactive({ field: '', op: '', value: '' });
const ruleDef = computed(() => kritDef(ruleForm.field));
function onRuleField() { ruleForm.op = ruleDef.value?.ops?.[0] ?? ''; ruleForm.value = ''; }
function addRule() {
    const d = ruleDef.value;
    if (!d || !ruleForm.op || ruleForm.value === '' || ruleForm.value == null) { notice('Lengkapi field, operator, & nilai.'); return; }
    const val = d.tipe === 'NUMBER' ? Number(ruleForm.value) : ruleForm.value;
    form.kriteria.push({ field: d.field, op: ruleForm.op, value: val, label: d.nama, satuan: d.satuan });
    Object.assign(ruleForm, { field: '', op: '', value: '' });
}
function removeRule(i) { form.kriteria.splice(i, 1); }
function ruleText(r) {
    const sat = r.satuan ? ' ' + r.satuan : '';
    const o = { '<=': `≤ ${r.value}${sat}`, '>=': `≥ ${r.value}${sat}`, '==': `= ${r.value}`, IN: `∈ ${r.value}`, NOT_IN: `∉ ${r.value}` }[r.op] || `${r.op} ${r.value}`;
    return `${r.label} ${o}`;
}

// Jadwal yang cocok untuk alur program (sinkron: jadwal mereferensikan alur yang sama)
const jadwalByAlur = computed(() => props.jadwalOptions.filter((j) => j.alurId === form.alurId));
const selectedJadwal = computed(() => jadwalById(form.jadwalId));
const isMTKat = computed(() => props.klasifikasi.find((k) => k.id === form.klasifikasiId)?.kategori === 'MT');
// Kuota total program = jumlah kuota MPP terpilih (otomatis, bukan input manual).
const kuotaTerpilih = computed(() => props.mpp.filter((m) => picked.value.includes(m.ref)).reduce((n, m) => n + (m.kuota || 0), 0));

// Batch di wizard (MT)
function addWizBatch() { form.batch.push({ nama: `Batch ${form.batch.length + 1}`, kuota: 10 }); }
function removeWizBatch(i) { form.batch.splice(i, 1); }

const alurByKat = computed(() => {
    const kat = props.klasifikasi.find((k) => k.id === form.klasifikasiId)?.kategori;
    return props.alur.filter((a) => !kat || a.kategori === kat);
});
function onKlasifikasi() {
    const k = props.klasifikasi.find((x) => x.id === form.klasifikasiId);
    if (!k) return;
    form.mode = k.modeDefault; // preset default kategori — bisa diubah manual
    form.alurId = k.alurDefault;
    form.jadwalId = ''; // reset pilihan jadwal (alur berubah)
    if (k.kategori !== 'MT') form.batch = []; // batch hanya untuk MT
}
function onAlurChangeProg() { form.jadwalId = ''; }

const errors = computed(() => {
    const e = [];
    if (!form.klasifikasiId) e.push('Klasifikasi wajib dipilih.');
    if (!form.nama.trim()) e.push('Nama program wajib diisi.');
    if (!form.alurId) e.push('Alur seleksi wajib dipilih.');
    if (form.mode === 'TERSTRUKTUR' && !form.jadwalId) e.push('Mode terstruktur — pilih Jadwal Kegiatan (buat dulu di Master Jadwal bila belum ada).');
    return e;
});

function resetForm() {
    Object.assign(form, { klasifikasiId: '', nama: '', penyelenggara: '', mode: 'ROLLING', alurId: '', jadwalId: '', tampilJadwal: false, batch: [], kriteria: [] });
    Object.assign(ruleForm, { field: '', op: '', value: '' });
    picked.value = [];
}
function openCreate() {
    editingId.value = null;
    resetForm();
    show.value = true;
}
function openEdit(p) {
    if (p.status === 'SELESAI') { notice('Program sudah Selesai & terkunci — buka kembali dulu untuk mengubah.'); return; }
    editingId.value = p.id;
    Object.assign(form, {
        klasifikasiId: p.klasifikasiId, nama: p.nama, penyelenggara: p.penyelenggara, mode: p.mode, alurId: p.alurId,
        jadwalId: p.jadwalId ?? '', tampilJadwal: p.tampilJadwal ?? (p.mode === 'TERSTRUKTUR'),
        batch: (p.batch ?? []).map((b) => ({ nama: b.nama, kuota: b.kuota })),
        kriteria: (p.kriteria ?? []).map((r) => ({ ...r })),
    });
    picked.value = [];
    show.value = true;
}
function save() {
    if (errors.value.length) return;
    const k = props.klasifikasi.find((x) => x.id === form.klasifikasiId);
    const alurNama = props.alur.find((a) => a.id === form.alurId)?.nama ?? '—';
    const chosen = props.mpp.filter((m) => picked.value.includes(m.ref));
    const newPosisi = chosen.map((m) => ({ id: m.ref, posisi: m.posisi, departemen: m.departemen, lokasi: m.lokasi, kuota: m.kuota, pelamar: 0, status: 'BUKA' }));

    const tampilJadwal = form.mode === 'TERSTRUKTUR' ? true : form.tampilJadwal;
    const jadwalId = form.mode === 'TERSTRUKTUR' ? form.jadwalId : null;
    const isMT = k?.kategori === 'MT';
    // Batch (MT) dari wizard → format program; pertahankan terisi/pelamar lama (edit) via nama.
    const buildBatch = (existing = []) => (isMT ? form.batch : []).map((b, i) => {
        const old = existing.find((x) => x.nama === b.nama);
        return { id: old?.id ?? 'BAT-' + (i + 1), nama: b.nama || `Batch ${i + 1}`, kuota: b.kuota || 1, terisi: old?.terisi ?? 0, pelamar: old?.pelamar ?? 0, status: 'AKTIF' };
    });
    if (editingId.value) {
        const row = list.find((x) => x.id === editingId.value);
        if (row) Object.assign(row, {
            nama: form.nama, klasifikasiId: form.klasifikasiId, kategori: k?.kategori, warna: k?.warna, mode: form.mode,
            alurId: form.alurId, alurNama, penyelenggara: form.penyelenggara, jadwalId, tampilJadwal,
            batch: buildBatch(row.batch), kriteria: form.kriteria.map((r) => ({ ...r })),
            posisi: newPosisi.length ? newPosisi : row.posisi,
        });
        notice('Program diperbarui (demo dummy).');
    } else {
        list.unshift({
            id: 'PRG-' + (list.length + 1), nama: form.nama, klasifikasiId: form.klasifikasiId, kategori: k?.kategori ?? 'REKRUTMEN', warna: k?.warna ?? 'sky',
            mode: form.mode, alurId: form.alurId, alurNama, penyelenggara: form.penyelenggara || '—', status: 'DRAFT',
            jadwalId, tampilJadwal, batch: buildBatch(), kriteria: form.kriteria.map((r) => ({ ...r })), posisi: newPosisi, totalPelamar: 0,
        });
        open.value = list[0].id;
        notice('Program dibuat sebagai Draft — Aktifkan lalu publikasikan di Pembukaan (demo dummy).');
    }
    show.value = false;
}

// ── Status daur hidup: Aktifkan / Tutup / Buka Kembali ──
const showClose = ref(false);
const showReopen = ref(false);
let closeTarget = ref(null);
function aktifkan(p) { p.status = 'BERJALAN'; notice(`Program "${p.nama}" diaktifkan (demo dummy).`); }
function askClose(p) { closeTarget.value = p; showClose.value = true; }
function confirmClose() {
    if (closeTarget.value) {
        closeTarget.value.status = 'SELESAI';
        (closeTarget.value.posisi || []).forEach((x) => (x.status = 'DITUTUP'));
        notice(`Program "${closeTarget.value.nama}" ditutup & dikunci (demo dummy).`);
    }
    showClose.value = false;
}
function askReopen(p) { closeTarget.value = p; showReopen.value = true; }
function confirmReopen() {
    if (closeTarget.value) {
        closeTarget.value.status = 'BERJALAN';
        (closeTarget.value.posisi || []).forEach((x) => (x.status = 'BUKA'));
        notice(`Program "${closeTarget.value.nama}" dibuka kembali (demo dummy).`);
    }
    showReopen.value = false;
}

// ── Batch (MT) ──
const showBatch = ref(false);
let batchTarget = null;
const batchForm = reactive({ nama: '', kuota: 10 });
function openBatch(p) {
    if (p.status === 'SELESAI') { notice('Program terkunci — tak bisa tambah batch.'); return; }
    batchTarget = p;
    Object.assign(batchForm, { nama: `Batch ${p.batch.length + 1}`, kuota: 10 });
    showBatch.value = true;
}
function saveBatch() {
    if (batchTarget) {
        batchTarget.batch.push({ id: 'B-' + (batchTarget.batch.length + 1) + '-' + batchTarget.id, nama: batchForm.nama || 'Batch Baru', kuota: batchForm.kuota, terisi: 0, pelamar: 0, status: 'AKTIF' });
    }
    notice('Batch ditambahkan (demo dummy).');
    showBatch.value = false;
}

const toast = ref('');
let t = null;
function notice(m) {
    toast.value = m;
    if (t) clearTimeout(t);
    t = setTimeout(() => (toast.value = ''), 3000);
}
</script>
