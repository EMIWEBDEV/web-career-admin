<!-- WEB CAREER — Program Kegiatan (induk-detail: Program + Batch + Posisi + Kriteria). DATA dari DB via /api/v1/program-kegiatan. -->
<template>
    <Head><title>Program Kegiatan - Web Career</title></Head>
    <div class="wca">
        <div class="pkg-head">
            <div class="pkg-head__l">
                <div class="pkg-head__title">
                    <span class="pkg-head__ico"><i class="bi bi-calendar2-week"></i></span>
                    <h1>Program Kegiatan</h1>
                </div>
                <p>Definisikan program secara menyeluruh — isi, alur seleksi, jadwal, posisi, dan kriteria. Satu program menampung <b>batch</b>, <b>posisi/lowongan</b>, dan <b>syarat auto-gugur</b>.</p>
            </div>
            <button class="pkg-newbtn" @click="openCreate"><i class="bi bi-plus-lg"></i> Buat Program</button>
        </div>

        <!-- Stat cards -->
        <div class="pkg-stats">
            <div class="pkg-stat">
                <span class="pkg-stat__glow" style="background:radial-gradient(circle,rgba(99,102,241,.2),transparent 70%)"></span>
                <span class="pkg-stat__ico" style="background:rgba(99,102,241,.12);color:#6366f1"><i class="bi bi-diagram-3-fill"></i></span>
                <div class="pkg-stat__num">{{ filtered.length }}</div>
                <div class="pkg-stat__label">Program</div>
            </div>
            <div class="pkg-stat">
                <span class="pkg-stat__glow" style="background:radial-gradient(circle,rgba(16,185,129,.2),transparent 70%)"></span>
                <span class="pkg-stat__ico" style="background:rgba(16,185,129,.12);color:#059669"><i class="bi bi-broadcast"></i></span>
                <div class="pkg-stat__num">{{ aktif }}</div>
                <div class="pkg-stat__label">Berjalan</div>
            </div>
            <div class="pkg-stat">
                <span class="pkg-stat__glow" style="background:radial-gradient(circle,rgba(139,92,246,.2),transparent 70%)"></span>
                <span class="pkg-stat__ico" style="background:rgba(139,92,246,.12);color:#7c3aed"><i class="bi bi-people"></i></span>
                <div class="pkg-stat__num">{{ totalKuotaPosisi }}</div>
                <div class="pkg-stat__label">Total Kuota Posisi</div>
            </div>
        </div>

        <!-- Toolbar: tab kategori (DARI DATABASE, disaring hak akses) -->
        <div class="pkg-toolbar">
            <div class="pkg-tabs">
                <!-- "Semua" hanya berguna bila memang ada lebih dari satu kategori. -->
                <button v-if="kategoriTab.length > 1" class="pkg-tab" :class="{ on: tab === '' }" @click="pilihTab('')">
                    <i class="bi bi-grid"></i> Semua <span class="pkg-tab__n">{{ totalSemua }}</span>
                </button>
                <button v-for="k in kategoriTab" :key="k.kode" class="pkg-tab" :class="{ on: tab === k.kode }" @click="pilihTab(k.kode)">
                    <i class="bi" :class="katIkon(k.kode)"></i> {{ k.nama }} <span class="pkg-tab__n">{{ k.jumlah }}</span>
                </button>
            </div>
        </div>

        <!-- FILTER PANEL — semua saringan dikirim ke backend. -->
        <div v-if="sheetOpen" class="pkg-sheetbg" @click="sheetOpen = false"></div>
        <div class="pkg-filter" :class="{ 'is-open': sheetOpen }">
            <div class="pkg-filter__head">
                <span class="pkg-filter__title"><i class="bi bi-funnel"></i> Filter Panel</span>
                <div class="pkg-filter__act">
                    <button v-if="adaFilter" class="pkg-filter__reset" type="button" @click="resetFilter"><i class="bi bi-arrow-counterclockwise"></i> Reset</button>
                    <button class="pkg-filter__close" type="button" aria-label="Tutup" @click="sheetOpen = false"><i class="bi bi-x-lg"></i></button>
                </div>
            </div>
            <div class="pkg-filter__grid">
                <div>
                    <label class="wca-field-lbl">Cari</label>
                    <el-input v-model="filters.q" placeholder="Nama / kode / penyelenggara / alur" clearable @input="cariDebounce">
                        <template #prefix><i class="bi bi-search"></i></template>
                    </el-input>
                </div>
                <div>
                    <label class="wca-field-lbl">Status</label>
                    <el-select v-model="filters.status" placeholder="Semua status" clearable style="width:100%" @change="load">
                        <el-option label="Berjalan" value="BERJALAN" />
                        <el-option label="Draft" value="DRAFT" />
                        <el-option label="Selesai" value="SELESAI" />
                    </el-select>
                </div>
                <div>
                    <label class="wca-field-lbl">Tanggal Dibuat</label>
                    <el-date-picker
                        v-model="filters.rentang" type="daterange" value-format="YYYY-MM-DD"
                        start-placeholder="Mulai" end-placeholder="Akhir" range-separator="—"
                        style="width:100%" @change="load"
                    />
                </div>
                <div class="pkg-filter__count"><strong>{{ searched.length }}</strong> program</div>
            </div>
        </div>
        <button class="pkg-fab" type="button" aria-label="Filter" @click="sheetOpen = true">
            <i class="bi bi-funnel-fill"></i>
            <span v-if="jumlahFilter" class="pkg-fab__badge">{{ jumlahFilter }}</span>
        </button>

        <!-- Program list -->
        <div v-loading="loading" class="pkg-list">
            <div v-for="p in paged" :key="p.id" class="pkg-card" :class="{ open: open === p.id }">
                <!-- header row -->
                <div class="pkg-row">
                    <button type="button" class="pkg-chev" :class="{ open: open === p.id }" title="Buka detail" @click="open = (open === p.id ? null : p.id)"><i class="bi bi-chevron-right"></i></button>
                    <div class="pkg-row__main">
                        <button type="button" class="pkg-row__titlebtn" @click="open = (open === p.id ? null : p.id)">
                            <span class="pkg-dot" :style="{ background: p.warna || '#4f46e5' }"></span>
                            <span class="pkg-row__title">{{ p.nama }}</span>
                        </button>
                        <div class="pkg-row__meta">
                            <span class="pkg-code">{{ p.kode }}</span>
                            <span class="pkg-sep"></span>
                            <span class="pkg-mi"><i class="bi bi-person"></i> {{ p.penyelenggara || '—' }}</span>
                            <span class="pkg-sep"></span>
                            <span class="pkg-mi pkg-flow"><i class="bi bi-diagram-2"></i> {{ p.alurNama || p.alur || '—' }}</span>
                        </div>
                        <div class="pkg-pills">
                            <span class="pkg-pill" :class="katPill(p.kategori)"><i class="bi" :class="katIkon(p.kategori)"></i> {{ katLabel(p.kategori) }}</span>
                            <span class="pkg-pill pkg-pill--struct"><i class="bi bi-list"></i> {{ modeLabel(p.mode) }}</span>
                            <span class="pkg-pill" :class="statusPill(p.status)"><span class="pkg-pill__dot"></span> {{ statusLabel(p.status) }}</span>
                        </div>
                    </div>
                    <div class="pkg-row__act" @click.stop>
                        <el-switch :model-value="p.status === 'BERJALAN'" @change="(v) => setStatus(p, v)" />
                        <button class="pkg-ibtn" title="Ubah" @click="openEdit(p)"><i class="bi bi-pencil"></i></button>
                        <button class="pkg-ibtn pkg-ibtn--danger" title="Hapus" @click="askRemove(p)"><i class="bi bi-trash"></i></button>
                    </div>
                </div>

                <!-- creator strip -->
                <div class="pkg-creator">
                    <span class="pkg-creator__av" :style="{ background: p.warna || '#6366f1' }">{{ initials(p.createdBy) }}</span>
                    <span class="pkg-creator__name">{{ p.createdBy || 'Sistem' }}</span>
                    <span class="pkg-creator__at"><i class="bi bi-clock"></i> {{ p.createdAt || '—' }}</span>
                </div>

                <!-- expanded detail -->
                <div v-if="open === p.id" class="pkg-detail">
                    <!-- syarat auto-gugur -->
                    <template v-if="p.syarat && p.syarat.length">
                        <div class="pkg-dhead pkg-dhead--amber"><i class="bi bi-sliders2"></i> SYARAT AUTO-GUGUR ({{ p.syarat.length }})</div>
                        <div class="pkg-rules">
                            <div v-for="(s, i) in p.syarat" :key="i" class="pkg-rule">
                                <span class="pkg-rule__bar"></span>
                                <div class="pkg-rule__top">
                                    <span class="pkg-rule__name">{{ s.nama }}</span>
                                    <span class="pkg-rule__act" :class="s.aksi === 'GUGUR' ? 'is-gugur' : 'is-mark'">
                                        <i class="bi" :class="s.aksi === 'GUGUR' ? 'bi-x-octagon' : 'bi-hand-index-thumb'"></i>
                                        {{ s.aksi === 'GUGUR' ? 'Gugur langsung' : 'Tandai — ketuk palu' }}
                                    </span>
                                    <span class="pkg-rule__match">{{ s.aturan?.penghubung === 'ATAU' ? 'SALAH SATU' : 'SEMUA' }}</span>
                                    <span v-if="!s.aktif" class="pkg-rule__match pkg-rule__match--off">Nonaktif</span>
                                </div>
                                <div v-if="(s.aturan?.aturan || []).length" class="pkg-conds">
                                    <span v-for="(r, j) in s.aturan.aturan" :key="j" class="pkg-cond"><i class="bi bi-funnel"></i> <span class="pkg-mono">{{ r.field }} {{ r.operator }} {{ r.nilai }}</span></span>
                                </div>
                            </div>
                        </div>
                    </template>

                    <!-- batch (opsional) -->
                    <template v-if="p.batch && p.batch.length">
                        <div class="pkg-dhead"><i class="bi bi-collection"></i> BATCH ({{ p.batch.length }})</div>
                        <div class="pkg-batchset">
                            <span v-for="(b, i) in p.batch" :key="i" class="pkg-batch"><i class="bi bi-people"></i> {{ b.nama }} · {{ b.terisi }}/{{ b.kuota }} kursi<template v-if="b.status"> · {{ b.status }}</template></span>
                        </div>
                    </template>

                    <!-- posisi / lowongan -->
                    <div class="pkg-poshead">
                        <span class="pkg-dhead pkg-dhead--indigo"><i class="bi bi-briefcase"></i> POSISI / LOWONGAN ({{ (p.posisi || []).length }})</span>
                        <span class="pkg-totalq"><i class="bi bi-people"></i> Total Kuota {{ totalKuota(p) }}</span>
                    </div>
                    <div class="pkg-tbl">
                        <div class="pkg-tbl__head">
                            <span>POSISI</span><span>NO. MPP</span><span>DEPARTEMEN</span><span>LOKASI</span><span class="pkg-c">KUOTA</span><span class="pkg-c">STATUS</span>
                        </div>
                        <div v-for="(l, i) in p.posisi" :key="i" class="pkg-tbl__row">
                            <span class="pkg-pos"><span class="pkg-pos__dot"></span><span class="pkg-pos__txt"><span class="pkg-pos__title">{{ l.posisi }}</span><span v-if="l.level" class="pkg-pos__lvl">{{ l.level }}</span></span></span>
                            <span><code v-if="l.mppRef" class="pkg-mpp">{{ l.mppRef }}</code><span v-else class="pkg-manual" title="Diinput manual sebelum aturan wajib-MPP">manual</span></span>
                            <span class="pkg-td">{{ l.departemen || '—' }}</span>
                            <span class="pkg-td pkg-td--loc"><i class="bi bi-geo-alt"></i> {{ l.lokasi || '—' }}</span>
                            <span class="pkg-c pkg-q">{{ l.kuota }}</span>
                            <span class="pkg-c"><span class="pkg-pill pkg-pill--slate pkg-pill--sm">{{ l.status || '—' }}</span></span>
                        </div>
                        <div v-if="!(p.posisi || []).length" class="pkg-tbl__empty">Belum ada posisi.</div>
                    </div>
                </div>
            </div>

            <div v-if="!loading && !searched.length" class="pkg-empty"><i class="bi bi-diagram-3"></i> Tidak ada program pada filter atau pencarian ini.</div>
        </div>

        <!-- Pagination -->
        <div v-if="totalPages > 1" class="pkg-pager">
            <span class="pkg-pager__info">Menampilkan <b>{{ pageFrom }}–{{ pageTo }}</b> dari <b>{{ searched.length }}</b> program</span>
            <div class="pkg-pager__nav">
                <button type="button" class="pkg-pager__btn" :disabled="page <= 1" @click="page = Math.max(1, page - 1)"><i class="bi bi-chevron-left"></i></button>
                <button v-for="n in totalPages" :key="n" type="button" class="pkg-pager__btn" :class="{ on: n === page }" @click="page = n">{{ n }}</button>
                <button type="button" class="pkg-pager__btn" :disabled="page >= totalPages" @click="page = Math.min(totalPages, page + 1)"><i class="bi bi-chevron-right"></i></button>
            </div>
        </div>

        <!-- Modal buat/ubah program -->
        <AdminModal :busy="saving" :show="show" :title="editingId ? 'Ubah Program' : 'Buat Program Kegiatan'" :subtitle="langkahMeta[langkah].sub" icon="bi-diagram-3-fill" xl @close="tutupModal">
            <!-- ── Stepper ── -->
            <nav class="pgk-steps">
                <button
                    v-for="(s, i) in langkahMeta"
                    :key="i"
                    class="pgk-step"
                    :class="{ done: i < langkah, cur: i === langkah }"
                    type="button"
                    :disabled="i > langkah"
                    @click="i <= langkah && (langkah = i)"
                >
                    <span class="pgk-step__dot"><i v-if="i < langkah" class="bi bi-check-lg"></i><i v-else class="bi" :class="s.ikon"></i></span>
                    <span class="pgk-step__lbl">{{ s.judul }}<small v-if="s.opsional"> (opsional)</small></span>
                </button>
            </nav>

            <!-- ══════════ LANGKAH 1 — IDENTITAS ══════════
                 Progressive disclosure: HANYA Kategori yang tampil dulu.
                 Field lain baru muncul setelah kategori dipilih (bukan disabled). -->
            <div v-show="langkah === 0" class="pgk-panel">
                <div class="wca-form">
                    <div class="wca-frow wca-frow--single">
                        <div>
                            <label class="wca-field-lbl">Kategori <span class="pgk-req">wajib</span></label>
                            <RefSelect type="talent" v-model="form.kategori" placeholder="Pilih kategori dulu" @picked="onKategori" />
                            <div class="pgk-hint">Menentukan alur & jadwal yang tersedia di bawah.</div>
                        </div>
                    </div>

                    <!-- Muncul setelah kategori dipilih -->
                    <template v-if="form.kategori">
                        <div class="wca-frow">
                            <div>
                                <label class="wca-field-lbl">Nama Program <span class="pgk-req">wajib</span></label>
                                <el-input v-model="form.nama" placeholder="mis. Rekrutmen Reguler Q4 2026" />
                            </div>
                            <div>
                                <label class="wca-field-lbl">Warna Label</label>
                                <!-- Swatch langsung klik — bukan color-picker mungil dengan ruang kosong. -->
                                <div class="pgk-swatches">
                                    <button
                                        v-for="c in palette"
                                        :key="c"
                                        type="button"
                                        class="pgk-swatch"
                                        :class="{ on: form.warna === c }"
                                        :style="{ background: c }"
                                        :title="c"
                                        @click="form.warna = c"
                                    ><i v-if="form.warna === c" class="bi bi-check-lg"></i></button>
                                    <el-color-picker v-model="form.warna" size="default" title="Warna kustom" />
                                </div>
                            </div>
                        </div>
                        <div class="wca-frow">
                            <div>
                                <label class="wca-field-lbl">Alur Seleksi</label>
                                <RefSelect type="alur" v-model="form.alur" :params="{ kategori: form.kategori }" placeholder="Pilih alur" no-data-text="Belum ada alur untuk kategori ini" clearable @picked="onAlurGanti" />
                            </div>
                            <div>
                                <label class="wca-field-lbl">Jadwal</label>
                                <RefSelect type="jadwal" v-model="form.jadwal" :params="{ kategori: form.kategori, alur: form.alur }" placeholder="Pilih jadwal" no-data-text="Belum ada jadwal untuk alur ini" clearable />
                            </div>
                        </div>
                        <div class="wca-frow">
                            <div>
                                <label class="wca-field-lbl">Penyelenggara</label>
                                <el-input v-model="form.penyelenggara" placeholder="mis. Tim Rekrutmen" />
                            </div>
                            <div v-if="editingId">
                                <label class="wca-field-lbl">Status</label>
                                <el-select filterable v-model="form.status" placeholder="Status" style="width:100%">
                                    <el-option label="Draft" value="DRAFT" />
                                    <el-option label="Berjalan" value="BERJALAN" />
                                    <el-option label="Selesai" value="SELESAI" />
                                </el-select>
                            </div>
                        </div>
                    </template>

                    <!-- Sebelum kategori dipilih: ajakan, bukan form kosong terkunci -->
                    <div v-else class="pgk-waitkat">
                        <i class="bi bi-arrow-up-circle"></i>
                        <span>Pilih <strong>kategori</strong> dulu — form identitas, alur & jadwal akan muncul setelahnya.</span>
                    </div>
                </div>
            </div>

            <!-- ══════════ LANGKAH 2 — POSISI ══════════ -->
            <div v-show="langkah === 1" class="pgk-panel">
                <div class="pgk-panel__bar">
                    <div>
                        <div class="pgk-panel__title"><i class="bi bi-briefcase"></i> Posisi / Lowongan</div>
                        <div class="pgk-panel__note">Diambil dari MPP yang sudah disetujui — tidak diketik manual.</div>
                    </div>
                </div>

                <!-- Pilih MPP lewat KARTU (ala Monitoring MPP) — klik kartu = pilih, klik lagi = buang.
                     Ikon info membuka popover detail TANPA menutup modal. -->
                <div class="pgk-mpppick">
                    <div class="pgk-mpppick__head">
                        <label class="wca-field-lbl">Pilih Posisi dari MPP <span class="pgk-req">klik kartu — bisa banyak</span></label>
                        <div class="pgk-mppsearch">
                            <i class="bi bi-search"></i>
                            <input v-model="mppCari" type="text" placeholder="Cari posisi / departemen / nomor MPP…" />
                            <button v-if="mppCari" type="button" @click="mppCari = ''"><i class="bi bi-x-lg"></i></button>
                        </div>
                    </div>
                    <div v-if="mppOptions.length" class="pgk-mppgrid">
                        <div
                            v-for="o in mppTersaring"
                            :key="o.value"
                            class="pgk-mppcard"
                            :class="{ on: mppTerpilih.includes(o.value) }"
                            role="button"
                            @click="toggleMpp(o)"
                        >
                            <div class="pgk-mppcard__head">
                                <span class="pgk-mppcard__check"><i class="bi bi-check-lg"></i></span>
                                <h5 class="pgk-mppcard__title">{{ tc(o.posisi) }}</h5>
                                <el-popover placement="top" :width="280" trigger="click">
                                    <template #reference>
                                        <button type="button" class="pgk-mppcard__info" title="Lihat detail MPP" @click.stop><i class="bi bi-info-circle"></i></button>
                                    </template>
                                    <div class="pgk-mppdetail">
                                        <strong>{{ tc(o.posisi) }}</strong>
                                        <div><i class="bi bi-upc-scan"></i> {{ o.value }}</div>
                                        <div><i class="bi bi-diagram-3"></i> {{ o.departemen || '—' }}</div>
                                        <div><i class="bi bi-bar-chart-steps"></i> Level {{ o.level || '—' }}</div>
                                        <div><i class="bi bi-briefcase"></i> {{ o.employment || '—' }}</div>
                                        <div><i class="bi bi-geo-alt"></i> {{ o.workplace || '—' }}</div>
                                        <div><i class="bi bi-stars"></i> {{ o.experience || '—' }}</div>
                                        <div><i class="bi bi-people"></i> Kuota {{ o.kuota }} orang</div>
                                    </div>
                                </el-popover>
                            </div>
                            <div class="pgk-mppcard__badges">
                                <span class="pgk-bdg pgk-bdg--indigo"><i class="bi bi-diagram-3"></i> {{ tc(o.divisi) || '—' }}</span>
                                <span v-if="o.sub" class="pgk-bdg pgk-bdg--sky">{{ tc(o.sub) }}</span>
                            </div>
                            <div class="pgk-mppcard__no"><i class="bi bi-hash"></i>{{ o.value }}</div>
                            <div v-if="o.employment || o.workplace || o.experience" class="pgk-mppcard__tags">
                                <span v-if="o.employment" class="mppt mppt--emp"><span class="mppt__dot mppt__dot--emp"></span><i class="bi bi-briefcase-fill"></i> {{ o.employment }}</span>
                                <span v-if="o.workplace" class="mppt mppt--wp"><span class="mppt__dot mppt__dot--wp"></span><i class="bi bi-geo-alt-fill"></i> {{ o.workplace }}</span>
                                <span v-if="o.experience" class="mppt mppt--exp"><span class="mppt__dot mppt__dot--exp"></span><i class="bi bi-stars"></i> {{ o.experience }}</span>
                            </div>
                            <div class="pgk-mppcard__meta">
                                <span><i class="bi bi-people-fill"></i> {{ o.kuota }} orang</span>
                                <span v-if="o.level"><i class="bi bi-bar-chart-steps"></i> {{ tc(o.level) }}</span>
                            </div>
                        </div>
                        <div v-if="!mppTersaring.length" class="pgk-empty" style="grid-column:1/-1">Tidak ada MPP yang cocok dengan pencarian.</div>
                    </div>
                    <div v-else class="pgk-empty">Tidak ada MPP untuk kategori ini.</div>
                </div>

                <!-- Panel SUDAH DIPILIH — area sendiri dengan scrollbar sendiri. -->
                <div class="pgk-selpanel">
                    <div class="pgk-selpanel__hd">
                        <i class="bi bi-check2-circle"></i> Sudah Dipilih ({{ form.posisi.length }})
                        <span v-if="form.posisi.length" class="pgk-selpanel__tot"><i class="bi bi-people"></i> Total kuota {{ kuotaMpp }}</span>
                        <span class="pgk-selpanel__hint">Ikon <i class="bi bi-info-circle"></i> pada kartu menampilkan detail tanpa menutup modal</span>
                    </div>
                    <div class="pgk-selpanel__body">
                <div class="pgk-rows">
                    <!-- Baris posisi terpilih: info sebagai chip elegan (bukan input mati), kuota bisa disetel. -->
                    <div v-for="(l, i) in form.posisi" :key="l.mppRef || i" class="pgk-posrow">
                        <span class="pgk-posrow__accent"></span>
                        <div class="pgk-posrow__main">
                            <div class="pgk-posrow__title">
                                <strong>{{ l.posisi || 'Posisi manual (lama)' }}</strong>
                                <code v-if="l.mppRef" class="pgk-mpp">{{ l.mppRef }}</code>
                            </div>
                            <div class="pgk-posrow__meta">
                                <span v-if="l.departemen"><i class="bi bi-diagram-3"></i> {{ l.departemen }}</span>
                                <span v-if="l.level"><i class="bi bi-person-badge"></i> {{ l.level }}</span>
                                <span v-if="l.lokasi"><i class="bi bi-building"></i> {{ l.lokasi }}</span>
                            </div>
                        </div>
                        <div class="pgk-posrow__kuota">
                            <label>Kuota <small v-if="l.kuotaMpp">dari MPP {{ l.kuotaMpp }}</small></label>
                            <el-input-number v-model="l.kuota" :min="0" :max="l.kuotaMpp || undefined" controls-position="right" style="width:130px" />
                        </div>
                        <div v-if="mengubah" class="pgk-posrow__status">
                            <label>Status</label>
                            <el-select v-model="l.status" style="width:110px">
                                <el-option label="Buka" value="BUKA" />
                                <el-option label="Penuh" value="PENUH" />
                                <el-option label="Tutup" value="TUTUP" />
                            </el-select>
                        </div>
                        <button class="pgk-posrow__del" type="button" title="Hapus posisi" @click="form.posisi.splice(i, 1)"><i class="bi bi-trash"></i></button>
                    </div>
                    <div v-if="!form.posisi.length" class="pgk-empty">
                        Tanpa posisi — program tetap bisa dibuat. Klik kartu MPP di atas, barisnya terbuat otomatis.
                    </div>
                </div>
                    </div>
                </div>
            </div>

            <!-- ══════════ LANGKAH 3 — PENGATURAN TAMBAHAN (opsional) ══════════ -->
            <div v-show="langkah === 2" class="pgk-panel">
                <div class="pgk-panel__intro">
                    <i class="bi bi-info-circle"></i>
                    Semua di langkah ini <b>boleh dilewati</b>. Aktifkan hanya yang Anda perlukan.
                </div>

                <!-- Batch -->
                <div class="pgk-opsi" :class="{ 'is-on': pakaiBatch }">
                    <label class="pgk-opsi__hd">
                        <el-switch v-model="pakaiBatch" @change="togglePakaiBatch" />
                        <span class="pgk-opsi__t"><i class="bi bi-collection"></i> Bagi per angkatan (Batch)</span>
                        <small>Kalau seleksi dijalankan bergelombang. Kalau tidak, biarkan mati.</small>
                    </label>

                    <div v-if="pakaiBatch" class="pgk-opsi__body">
                        <div class="pgk-opsi__toolbar">
                            <span v-if="kuotaMpp" class="pgk-meter" :class="{ 'is-over': kuotaBatchLebih }">
                                <i class="bi" :class="kuotaBatchLebih ? 'bi-exclamation-octagon-fill' : 'bi-people-fill'"></i>
                                {{ kuotaBatch }} / {{ kuotaMpp }} kursi MPP
                            </span>
                            <span style="flex:1"></span>
                            <button class="wca-btn wca-btn--soft wca-btn--sm" type="button" @click="addBatch"><i class="bi bi-plus-circle"></i> Tambah Batch</button>
                        </div>
                        <div v-if="kuotaBatchLebih" class="pgk-alert">
                            <i class="bi bi-exclamation-triangle-fill"></i>
                            Total kursi batch <strong>{{ kuotaBatch }}</strong> melebihi kuota MPP <strong>{{ kuotaMpp }}</strong>. Kurangi {{ kuotaBatch - kuotaMpp }} kursi.
                        </div>
                        <div class="pgk-rows">
                            <div v-for="(b, i) in form.batch" :key="i" class="pgk-row">
                                <div class="pgk-row__grid pgk-row__grid--batch" :class="{ 'is-edit': mengubah }">
                                    <div><label class="wca-field-lbl">Nama</label><el-input v-model="b.nama" placeholder="Batch 1 — Mei 2026" /></div>
                                    <div>
                                        <label class="wca-field-lbl">Kuota <small v-if="kuotaMpp">maks {{ batasBatch(i) }}</small></label>
                                        <el-input-number v-model="b.kuota" :min="0" :max="kuotaMpp ? batasBatch(i) : undefined" controls-position="right" style="width:100%" />
                                    </div>
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
                            <div v-if="!form.batch.length" class="pgk-empty" style="padding:.6rem">Klik "Tambah Batch" untuk menambah angkatan.</div>
                        </div>
                    </div>
                </div>

                <!-- Syarat auto-gugur -->
                <div class="pgk-opsi" :class="{ 'is-on': pakaiSyarat }">
                    <label class="pgk-opsi__hd">
                        <el-switch v-model="pakaiSyarat" :disabled="!tahapFormulir.length" @change="togglePakaiSyarat" />
                        <span class="pgk-opsi__t"><i class="bi bi-sliders2"></i> Syarat Auto-Gugur</span>
                        <small>Saring pelamar otomatis dari jawaban formulir (mis. IPK minimal, usia maksimal).</small>
                    </label>

                    <div v-if="!tahapFormulir.length" class="pgk-alert" style="margin:.5rem 0 0">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        Alur <b>{{ form.alur || '—' }}</b> belum punya tahap berformulir. Pasang formulir di Master Tahapan Seleksi dulu — tanpa formulir tidak ada data yang bisa disaring.
                    </div>

                    <div v-if="pakaiSyarat" class="pgk-opsi__body">
                        <div class="pgk-opsi__toolbar">
                            <span style="flex:1"></span>
                            <button class="wca-btn wca-btn--soft wca-btn--sm" type="button" @click="addSyarat"><i class="bi bi-plus-circle"></i> Tambah Syarat</button>
                        </div>

                        <div v-for="(S, i) in form.syarat" :key="i" class="pgk-syarat">
                            <!-- Baris identitas syarat: nama + tahap, masing-masing berlabel -->
                            <div class="pgk-syarat__id">
                                <div class="pgk-syarat__idf">
                                    <label class="wca-field-lbl">Nama Syarat</label>
                                    <el-input v-model="S.nama" placeholder="mis. Kelayakan Akademik" />
                                </div>
                                <div class="pgk-syarat__idf">
                                    <label class="wca-field-lbl">Diperiksa di Tahap</label>
                                    <el-select v-model="S.tahapId" placeholder="Pilih tahap berformulir" style="width:100%" @change="(v) => onTahapSyarat(S, v)">
                                        <el-option v-for="t in tahapFormulir" :key="t.tahapId" :value="t.tahapId" :label="`${t.urutan}. ${t.label} — ${t.formulirNama || t.formulir}`" />
                                    </el-select>
                                </div>
                                <button class="wca-iconbtn wca-iconbtn--danger pgk-syarat__del" type="button" title="Hapus syarat" @click="form.syarat.splice(i, 1)"><i class="bi bi-trash"></i></button>
                            </div>

                            <!-- Pohon aturan -->
                            <div class="pgk-aturan">
                                <div class="pgk-aturan__bar">
                                    <span>Pelamar <b>lolos</b> bila</span>
                                    <el-select v-model="S.aturan.penghubung" size="small" style="width:8.5rem">
                                        <el-option value="DAN" label="SEMUA" />
                                        <el-option value="ATAU" label="SALAH SATU" />
                                    </el-select>
                                    <span>kondisi terpenuhi</span>
                                </div>

                                <!-- BINDING TIPE FIELD: operator & input Nilai mengikuti tipe field dari skema
                                     formulir (angka -> input angka, pilihan -> dropdown dari sumber yang sama
                                     dengan formulirnya, teks -> bebas). Seperti formula: tipe menentukan bentuk. -->
                                <div v-for="(K, j) in S.aturan.aturan" :key="j" class="pgk-kondisi">
                                    <div class="pgk-kondisi__f">
                                        <label class="wca-field-lbl">Field</label>
                                        <el-select v-model="K.field" filterable placeholder="Pilih field" style="width:100%" :no-data-text="S.tahapId ? 'Formulir tahap ini tidak punya field' : 'Pilih tahap dulu'" @change="onFieldGanti(S, K)">
                                            <el-option-group label="Dari formulir">
                                                <el-option v-for="f in fieldTahap(S.tahapId)" :key="f.key" :value="f.key" :label="f.label" />
                                            </el-option-group>
                                            <el-option-group label="Dihitung otomatis (mis. usia dari tanggal lahir)">
                                                <el-option v-for="f in fieldTurunan" :key="f.key" :value="f.key" :label="f.label" />
                                            </el-option-group>
                                        </el-select>
                                    </div>
                                    <div class="pgk-kondisi__o">
                                        <label class="wca-field-lbl">
                                            Syarat
                                            <el-tooltip content="Daftar operator otomatis menyesuaikan tipe field yang dipilih." placement="top">
                                                <i class="bi bi-info-circle pgk-lblinfo"></i>
                                            </el-tooltip>
                                        </label>
                                        <el-select v-model="K.operator" style="width:100%" @change="K.nilai = ''">
                                            <el-option v-for="o in operatorUntuk(S, K)" :key="o.value" :value="o.value" :label="o.label">
                                                <div class="pgk-opdesc"><span>{{ o.label }}</span><small>{{ o.desc }}</small></div>
                                            </el-option>
                                        </el-select>
                                    </div>
                                    <div class="pgk-kondisi__v">
                                        <label class="wca-field-lbl">Nilai</label>
                                        <!-- angka + ANTARA: dua kotak rentang -->
                                        <div v-if="tipeField(S, K.field) === 'number' && K.operator === 'ANTARA'" class="pgk-antara">
                                            <el-input-number :model-value="antaraVal(K, 0)" :controls="false" placeholder="dari" @update:model-value="(v) => setAntara(K, 0, v)" />
                                            <span class="pgk-antara__sep">—</span>
                                            <el-input-number :model-value="antaraVal(K, 1)" :controls="false" placeholder="sampai" @update:model-value="(v) => setAntara(K, 1, v)" />
                                        </div>
                                        <!-- angka biasa -->
                                        <el-input-number
                                            v-else-if="tipeField(S, K.field) === 'number'"
                                            :model-value="angkaVal(K)"
                                            controls-position="right"
                                            style="width:100%"
                                            :placeholder="phNilai(K.operator)"
                                            @update:model-value="(v) => (K.nilai = v === null || v === undefined ? '' : String(v))"
                                        />
                                        <!-- pilihan, operator daftar: PANEL CHECKLIST (bukan select sempit) —
                                             muat banyak nilai (mis. puluhan kampus) tetap jelas & bisa dicari -->
                                        <div v-else-if="adaOpsi(S, K.field) && (K.operator === 'ADA_DI' || K.operator === 'TIDAK_ADA_DI')" class="pgk-multichk">
                                            <div class="pgk-multichk__search">
                                                <i class="bi bi-search"></i>
                                                <input :value="K._cari || ''" type="text" placeholder="Cari nilai…" @input="(e) => (K._cari = e.target.value)" />
                                            </div>
                                            <div class="pgk-multichk__list">
                                                <label v-for="o in opsiTersaring(S, K)" :key="o" class="pgk-multichk__item" :class="{ on: listVal(K).includes(o) }">
                                                    <input type="checkbox" :checked="listVal(K).includes(o)" @change="toggleNilai(K, o)" />
                                                    <span>{{ o }}</span>
                                                    <i v-if="listVal(K).includes(o)" class="bi bi-check-lg"></i>
                                                </label>
                                                <div v-if="!opsiTersaring(S, K).length" class="pgk-multichk__empty">Tidak ada nilai yang cocok.</div>
                                            </div>
                                            <div class="pgk-multichk__foot">
                                                <span><b>{{ listVal(K).length }}</b> nilai dipilih</span>
                                                <button v-if="listVal(K).length" type="button" @click="K.nilai = ''"><i class="bi bi-x-circle"></i> Bersihkan</button>
                                            </div>
                                        </div>
                                        <!-- pilihan tunggal -->
                                        <el-select v-else-if="adaOpsi(S, K.field)" v-model="K.nilai" filterable style="width:100%" placeholder="Pilih nilai">
                                            <el-option v-for="o in opsiField(S, K.field)" :key="o" :value="o" :label="o" />
                                        </el-select>
                                        <!-- teks bebas (fallback) -->
                                        <el-input v-else v-model="K.nilai" :placeholder="phNilai(K.operator)" />
                                    </div>
                                    <button class="wca-iconbtn wca-iconbtn--danger pgk-kondisi__del" type="button" title="Hapus kondisi" @click="S.aturan.aturan.splice(j, 1)"><i class="bi bi-x-lg"></i></button>
                                </div>

                                <button class="pgk-addkondisi" type="button" @click="addKondisi(S)"><i class="bi bi-plus-lg"></i> Tambah Kondisi</button>
                                <div v-if="!S.aturan.aturan.length" class="pgk-kondisi-kosong">Belum ada kondisi — syarat ini akan diabaikan mesin.</div>
                            </div>

                            <!-- Aksi bila tidak lolos (Mode Uji dihapus — mulai dari TANDAI dulu bila ragu) -->
                            <div class="pgk-syarat__opt">
                                <div>
                                    <label class="wca-field-lbl">Bila tidak lolos</label>
                                    <el-select v-model="S.aksi" style="width:100%">
                                        <el-option value="TANDAI" label="Tandai — admin yang ketuk palu" />
                                        <el-option value="GUGUR" label="Gugurkan langsung tanpa admin" />
                                    </el-select>
                                </div>
                            </div>

                            <div class="pgk-pesan">
                                <label class="wca-field-lbl">
                                    <i class="bi bi-chat-heart"></i> Pesan penolakan untuk kandidat
                                    <span class="pgk-opt">opsional</span>
                                </label>
                                <el-input v-model="S.pesanGugur" type="textarea" :rows="3" :placeholder="contohPesan" maxlength="500" show-word-limit resize="none" />
                                <button class="pgk-isipesan" type="button" @click="S.pesanGugur = contohPesan"><i class="bi bi-magic"></i> Pakai contoh</button>
                            </div>
                        </div>

                        <div v-if="!form.syarat.length" class="pgk-empty" style="padding:.6rem">Klik "Tambah Syarat" untuk mulai menyaring pelamar.</div>
                    </div>
                </div>
            </div>

            <!-- ══════════ LANGKAH 4 — PRATINJAU ══════════ -->
            <div v-show="langkah === 3" class="pgk-panel">
                <div class="pgk-prev">
                    <!-- HERO ringkasan program -->
                    <div class="pgk-prev__hero">
                        <span class="pgk-prev__heroBar" :style="{ background: form.warna || '#4f46e5' }"></span>
                        <div class="pgk-prev__heroMain">
                            <div class="pgk-prev__heroTop">
                                <h4>{{ form.nama || '—' }}</h4>
                                <span class="pgk-prev__tag pgk-prev__tag--vio">{{ katLabel(form.kategori) }}</span>
                                <span v-if="editingId" class="pgk-prev__tag pgk-prev__tag--ind">{{ statusLabel(form.status) }}</span>
                            </div>
                            <div class="pgk-prev__heroChips">
                                <span><i class="bi bi-signpost-split"></i> Alur <code>{{ form.alur || '—' }}</code></span>
                                <span><i class="bi bi-calendar3-range"></i> Jadwal <code>{{ form.jadwal || '—' }}</code></span>
                                <span><i class="bi bi-person"></i> {{ form.penyelenggara || '—' }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Stat mini -->
                    <div class="pgk-prev__stats">
                        <div><span class="pgk-prev__sico" style="background:rgba(99,102,241,.12);color:#6366f1"><i class="bi bi-briefcase-fill"></i></span><b>{{ form.posisi.length }}</b><span>Posisi</span></div>
                        <div><span class="pgk-prev__sico" style="background:rgba(16,185,129,.12);color:#059669"><i class="bi bi-people-fill"></i></span><b>{{ kuotaMpp }}</b><span>Total Kuota</span></div>
                        <div><span class="pgk-prev__sico" style="background:rgba(139,92,246,.12);color:#7c3aed"><i class="bi bi-collection-fill"></i></span><b>{{ form.batch.length }}</b><span>Batch</span></div>
                        <div><span class="pgk-prev__sico" style="background:rgba(245,158,11,.14);color:#b45309"><i class="bi bi-sliders2"></i></span><b>{{ form.syarat.length }}</b><span>Syarat</span></div>
                    </div>

                    <!-- Posisi -->
                    <div class="pgk-prev__card">
                        <div class="pgk-prev__hd">
                            <i class="bi bi-briefcase"></i> Posisi / Lowongan ({{ form.posisi.length }})
                            <span v-if="form.posisi.length" class="pgk-prev__tag pgk-prev__tag--ind"><i class="bi bi-people"></i> Total kuota {{ kuotaMpp }}</span>
                        </div>
                        <div v-if="form.posisi.length" class="pgk-prev__list">
                            <div v-for="(l, i) in form.posisi" :key="i" class="pgk-prev__item">
                                <span class="pgk-prev__dot"></span>
                                <span class="pgk-prev__nm">{{ l.posisi }}</span>
                                <code v-if="l.mppRef" class="pgk-mpp">{{ l.mppRef }}</code>
                                <span class="pgk-prev__kt">{{ l.kuota }} kuota</span>
                            </div>
                        </div>
                        <div v-else class="pgk-prev__none">Tanpa posisi — program dibuat tanpa lowongan tertaut.</div>
                    </div>

                    <!-- Batch -->
                    <div v-if="form.batch.length" class="pgk-prev__card">
                        <div class="pgk-prev__hd"><i class="bi bi-collection"></i> Batch ({{ form.batch.length }})</div>
                        <div class="pgk-prev__list">
                            <div v-for="(b, i) in form.batch" :key="i" class="pgk-prev__item">
                                <span class="pgk-prev__dot"></span>
                                <span class="pgk-prev__nm">{{ b.nama || '(tanpa nama)' }}</span>
                                <span class="pgk-prev__kt">{{ b.kuota }} kursi</span>
                            </div>
                        </div>
                    </div>

                    <!-- Syarat -->
                    <div v-if="form.syarat.length" class="pgk-prev__card">
                        <div class="pgk-prev__hd"><i class="bi bi-sliders2"></i> Syarat Auto-Gugur ({{ form.syarat.length }})</div>
                        <div v-for="(S, i) in form.syarat" :key="i" class="pgk-prev__syarat">
                            <div class="pgk-prev__srow">
                                <b>{{ S.nama || 'Syarat ' + (i + 1) }}</b>
                                <span class="pgk-prev__tag pgk-prev__tag--vio">{{ S.aturan.penghubung === 'ATAU' ? 'SALAH SATU' : 'SEMUA' }}</span>
                                <span class="pgk-prev__tag" :class="S.aksi === 'GUGUR' ? 'pgk-prev__tag--red' : 'pgk-prev__tag--ind'">
                                    <i class="bi" :class="S.aksi === 'GUGUR' ? 'bi-x-octagon' : 'bi-hand-index-thumb'"></i>
                                    {{ S.aksi === 'GUGUR' ? 'Gugur langsung' : 'Tandai — ketuk palu' }}
                                </span>
                            </div>
                            <div class="pgk-prev__conds">
                                <span v-for="(K, j) in S.aturan.aturan" :key="j" class="pgk-prev__cond">
                                    <i class="bi bi-funnel"></i> {{ labelField(S, K.field) }} <b>{{ opLabel(K.operator) }}</b> {{ K.nilai || '—' }}
                                </span>
                                <span v-if="!S.aturan.aturan.length" class="pgk-prev__none">Belum ada kondisi — syarat diabaikan mesin.</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── Footer wizard ── -->
            <template #footer>
                <button class="wca-btn wca-btn--ghost" type="button" @click="tutupModal"><i class="bi bi-x-circle"></i> Batal</button>
                <span style="flex:1"></span>
                <button v-if="langkah > 0" class="wca-btn wca-btn--ghost" type="button" @click="langkah--"><i class="bi bi-arrow-left"></i> Kembali</button>
                <button v-if="langkah < langkahMeta.length - 1" class="wca-btn wca-btn--primary" type="button" @click="maju">Lanjut <i class="bi bi-arrow-right"></i></button>
                <button v-else class="wca-btn wca-btn--primary" type="button" @click="save"><i class="bi bi-check-circle-fill"></i> {{ editingId ? 'Perbarui' : 'Buat Program' }}</button>
            </template>
        </AdminModal>

        <ConfirmModal :show="delShow" title="Hapus Program" :busy="deleting" confirm-label="Ya, Hapus" note="Program beserta batch, posisi & kriteria-nya akan dihapus permanen." @cancel="delShow = false" @confirm="confirmDelete">
            Yakin ingin menghapus program <strong>{{ delTarget?.nama }}</strong>?
        </ConfirmModal>

        <!-- GUARD INFO DIVISI — daftar divisi yang belum terisi + tombol isi langsung -->
        <div v-if="guardDivisiShow" class="gid-overlay" @click.self="guardDivisiShow = false">
            <div class="gid-modal">
                <div class="gid-modal__head">
                    <span class="gid-modal__ico"><i class="bi bi-exclamation-triangle-fill"></i></span>
                    <div>
                        <h3>Info Divisi Belum Lengkap</h3>
                        <p>Lengkapi informasi divisi berikut dulu sebelum lanjut. Program yang sedang dibuat <b>tetap tersimpan</b> saat Anda mengisinya.</p>
                    </div>
                    <button type="button" class="gid-modal__x" @click="guardDivisiShow = false"><i class="bi bi-x-lg"></i></button>
                </div>
                <div class="gid-list">
                    <div v-for="d in divisiBelum" :key="d.id" class="gid-item">
                        <span class="gid-item__l"><i class="bi bi-diagram-3"></i> <b>{{ d.nama }}</b> <span class="gid-item__badge">0/0 belum diisi</span></span>
                        <button type="button" class="gid-item__btn" @click="gotoInfoDivisi(d.id)"><i class="bi bi-pencil-square"></i> Isi Sekarang</button>
                    </div>
                </div>
                <div class="gid-modal__foot">
                    <button type="button" class="wca-btn wca-btn--ghost" @click="guardDivisiShow = false"><i class="bi bi-arrow-left"></i> Nanti Dulu</button>
                    <button type="button" class="wca-btn wca-btn--primary" @click="gotoInfoDivisi(divisiBelum[0] && divisiBelum[0].id)"><i class="bi bi-box-arrow-up-right"></i> Buka Master Info Divisi</button>
                </div>
            </div>
        </div>

        <transition name="wca-toast"><div v-if="toast" class="wca-toast"><i class="bi bi-check-circle-fill"></i> {{ toast }}</div></transition>
    </div>
</template>

<script>
import axios from 'axios';
import { Head, router } from '@inertiajs/vue3';
import AdminModal from '@career/AdminModal.vue';
import ConfirmModal from '@career/ConfirmModal.vue';
import AuditStamp from '@career/AuditStamp.vue';
import RefSelect from '@career/RefSelect.vue';
import { skemaFormulir, semuaField } from '@career/formulir';
import { titleCase } from '../monitoring-mpp/mppHelpers';

const API = '/api/v1/program-kegiatan';
const CFG = { headers: { Accept: 'application/json' } };
// Draft "Buat Program" disimpan di sessionStorage — tahan refresh/pindah halaman,
// hanya dibuang saat Batal/tutup modal atau setelah simpan sukses (BUKAN saat edit).
const DRAFT_KEY = 'evo_pgk_draft_v1';

export default {
    components: { Head, AdminModal, ConfirmModal, AuditStamp, RefSelect },
    data() {
        return {
            list: [],
            loading: false,
            open: null,
            tab: '',
            query: '',
            page: 1,
            perPage: 6,
            // Tab kategori dari DB (sudah disaring hak akses) + Filter Panel.
            kategoriTab: [],
            totalSemua: 0,
            filters: { q: '', status: null, rentang: null },
            sheetOpen: false,
            cariTimer: null,
            show: false,
            editingId: null,
            // Wizard: langkah aktif + apakah seksi opsional diaktifkan.
            langkah: 0,
            langkahMeta: [
                { judul: 'Identitas', ikon: 'bi-tags', sub: 'Kategori dulu — alur & jadwal mengikutinya.' },
                { judul: 'Posisi', ikon: 'bi-briefcase', sub: 'Tautkan lowongan dari MPP yang sudah disetujui.' },
                { judul: 'Tambahan', ikon: 'bi-sliders', opsional: true, sub: 'Batch & syarat auto-gugur — boleh dilewati.' },
                { judul: 'Pratinjau', ikon: 'bi-eye', sub: 'Periksa ringkasan akhir sebelum disimpan.' },
            ],
            pakaiBatch: false,
            pakaiSyarat: false,
            form: { nama: '', kategori: '', warna: '#4f46e5', mode: 'ROLLING', alur: '', jadwal: '', penyelenggara: '', status: 'DRAFT', batch: [], posisi: [], syarat: [] },
            tahapFormulir: [],
            fieldTurunan: [],
            // Kartu MPP (langkah Posisi) + cache opsi dinamis field pilihan (mis. kampus).
            mppOptions: [],
            mppCari: '',
            opsiSumber: {},
            palette: ['#4f46e5', '#7c3aed', '#0ea5e9', '#10b981', '#f59e0b', '#ef4444', '#64748b'],
            operatorOptions: [
                { value: '>=', label: 'minimal (>=)', desc: 'Jawaban harus ≥ angka ini' },
                { value: '<=', label: 'maksimal (<=)', desc: 'Jawaban harus ≤ angka ini' },
                { value: '=', label: 'sama dengan', desc: 'Jawaban persis sama dengan nilai' },
                { value: '!=', label: 'tidak sama dengan', desc: 'Jawaban apa pun selain nilai ini' },
                { value: '>', label: 'lebih dari', desc: 'Lebih besar, tanpa termasuk nilainya' },
                { value: '<', label: 'kurang dari', desc: 'Lebih kecil, tanpa termasuk nilainya' },
                { value: 'ANTARA', label: 'antara', desc: 'Berada dalam rentang dari–sampai' },
                { value: 'ADA_DI', label: 'salah satu dari', desc: 'Jawaban termasuk daftar yang dicentang' },
                { value: 'TIDAK_ADA_DI', label: 'bukan salah satu dari', desc: 'Jawaban di luar daftar yang dicentang' },
            ],
            presets: [],
            // Contoh pesan penolakan bernada hangat — tombol "Pakai contoh" mengisi ini.
            contohPesan: 'Terima kasih sudah mendaftar di EVO Group. Setelah kami tinjau, untuk kesempatan kali ini Anda belum memenuhi kualifikasi yang dibutuhkan. Kami sangat menghargai minat & waktu Anda, dan berharap dapat bertemu kembali di kesempatan berikutnya. Sukses selalu! 🙏',
            delShow: false,
            delTarget: null,
            deleting: false,
            // Guard Info Divisi: daftar divisi yang belum punya info + modal-nya.
            divisiBelum: [],
            guardDivisiShow: false,
            saving: false,
            toast: '',
            tm: null,
        };
    },
    computed: {
        // Penyaringan (kategori, pencarian, status, tanggal) SUDAH dikerjakan
        // backend — `list` di sini sudah bersih. Sisanya hanya pemenggalan halaman.
        filtered() { return this.list; },
        aktif() { return this.filtered.filter((p) => p.status === 'BERJALAN').length; },
        totalKuotaPosisi() { return this.filtered.reduce((n, p) => n + this.totalKuota(p), 0); },
        searched() { return this.list; },

        adaFilter() {
            return !!(this.filters.q || this.filters.status || (this.filters.rentang && this.filters.rentang.length));
        },
        jumlahFilter() {
            return [this.filters.q, this.filters.status, this.filters.rentang?.length ? 1 : null].filter(Boolean).length;
        },
        totalPages() { return Math.max(1, Math.ceil(this.searched.length / this.perPage)); },
        paged() { const s = (this.page - 1) * this.perPage; return this.searched.slice(s, s + this.perPage); },
        pageFrom() { return this.searched.length ? (this.page - 1) * this.perPage + 1 : 0; },
        pageTo() { return Math.min(this.page * this.perPage, this.searched.length); },

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

        /** MPP yang sedang dipakai baris posisi — sumber kebenaran tetap form.posisi. */
        mppTerpilih() { return this.form.posisi.filter((l) => l.mppRef).map((l) => l.mppRef); },

        /** Kartu MPP tersaring kotak cari (posisi/departemen/lokasi/nomor). */
        mppTersaring() {
            const q = this.mppCari.trim().toLowerCase();
            if (!q) return this.mppOptions;
            return this.mppOptions.filter((o) =>
                [o.value, o.posisi, o.departemen, o.lokasi, o.level].join(' ').toLowerCase().includes(q));
        },
        kuotaBatch() { return this.form.batch.reduce((n, b) => n + (Number(b.kuota) || 0), 0); },
        kuotaBatchLebih() { return this.kuotaMpp > 0 && this.kuotaBatch > this.kuotaMpp; },
    },
    watch: {
        tab() { this.page = 1; },
        query() { this.page = 1; },
        // Persist draft BUAT (bukan edit) tiap ada perubahan — debounce ringan.
        form: { deep: true, handler() { this.simpanDraftDebounce(); } },
        langkah() { this.simpanDraftDebounce(); },
        pakaiBatch() { this.simpanDraftDebounce(); },
        pakaiSyarat() { this.simpanDraftDebounce(); },
    },
    mounted() {
        this.load();
        this.loadPresets();
        this.loadFieldTurunan();
        // Pulihkan draft "Buat Program" bila ada (refresh / pindah halaman lalu kembali).
        this.restoreDraft();
    },
    methods: {
        katLabel(k) { return { REKRUTMEN: 'Rekrutmen', MT: 'Management Trainee', INTERNSHIP: 'Internship' }[k] || k || '—'; },
        katBadge(k) { return { MT: 'wca-b--gold', INTERNSHIP: 'wca-b--green', REKRUTMEN: 'wca-b--sky' }[k] || 'wca-b--slate'; },
        modeLabel(m) { return { TERSTRUKTUR: 'Terstruktur', ROLLING: 'Rolling' }[m] || m || '—'; },
        statusLabel(s) { return { DRAFT: 'Draft', BERJALAN: 'Berjalan', SELESAI: 'Selesai' }[s] || s; },
        statusBadge(s) { return { DRAFT: 'wca-b--amber', BERJALAN: 'wca-b--green', SELESAI: 'wca-b--slate' }[s] || 'wca-b--slate'; },
        statusIkon(s) { return { DRAFT: 'bi-pencil-square', BERJALAN: 'bi-play-circle', SELESAI: 'bi-lock-fill' }[s] || 'bi-dot'; },
        totalKuota(p) { return (p.posisi || []).reduce((n, x) => n + (Number(x.kuota) || 0), 0); },

        // ── Desain "Program Kegiatan": hitung per kategori, pil warna, inisial pembuat ──
        countKat(k) { return this.list.filter((p) => p.kategori === k).length; },
        katIkon(k) { return { REKRUTMEN: 'bi-briefcase', MT: 'bi-mortarboard', INTERNSHIP: 'bi-backpack' }[k] || 'bi-diagram-3'; },
        katPill(k) { return { MT: 'pkg-pill--gold', INTERNSHIP: 'pkg-pill--green', REKRUTMEN: 'pkg-pill--sky' }[k] || 'pkg-pill--slate'; },
        statusPill(s) { return { DRAFT: 'pkg-pill--amber', BERJALAN: 'pkg-pill--green', SELESAI: 'pkg-pill--slate' }[s] || 'pkg-pill--slate'; },
        initials(name) {
            if (!name) return 'SY';
            const parts = String(name).trim().split(/\s+/);
            return ((parts[0]?.[0] || '') + (parts[1]?.[0] || parts[0]?.[1] || '')).toUpperCase() || 'SY';
        },

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
        /** Field milik formulir yang dipasang di tahap tsb — dibaca dari skema di kode,
         *  LENGKAP dengan tipe & sumber opsi. Skema formulir = satu-satunya "master syarat":
         *  dari sinilah binding operator + bentuk input Nilai diturunkan otomatis. */
        fieldTahap(tahapId) {
            const t = this.tahapFormulir.find((x) => x.tahapId === tahapId);
            if (!t || !t.komponen) return [];
            return semuaField(skemaFormulir(t.komponen))
                .filter((f) => f.tipe !== 'file' && f.tipe !== 'consent')
                .map((f) => ({ key: f.key, label: f.label, tipe: f.tipe || 'text', opsi: f.opsi || [], sumber_opsi: f.sumber_opsi || null }));
        },

        // ── Binding tipe field → operator + bentuk input Nilai ─────────────
        metaField(S, key) {
            if (!key) return null;
            return this.fieldTahap(S.tahapId).find((f) => f.key === key)
                || this.fieldTurunan.find((f) => f.key === key)
                || null;
        },
        tipeField(S, key) {
            const m = this.metaField(S, key);
            if (!m) return 'text';
            // Field turunan (usia dst) bertipe dari definisinya; date dibanding sbg angka tidak — biarkan number saja.
            return m.tipe === 'number' ? 'number' : (m.tipe || 'text');
        },
        adaOpsi(S, key) {
            const m = this.metaField(S, key);
            return !!(m && (((m.opsi || []).length) || m.sumber_opsi));
        },
        opsiField(S, key) {
            const m = this.metaField(S, key);
            if (!m) return [];
            if (m.sumber_opsi) return this.opsiSumber[m.sumber_opsi] || [];
            return m.opsi || [];
        },
        /** Operator yang masuk akal per tipe — aturan tetap, bukan master DB. */
        operatorUntuk(S, K) {
            if (!K.field) return this.operatorOptions;
            const t = this.tipeField(S, K.field);
            const izin = t === 'number'
                ? ['>=', '<=', '=', '!=', '>', '<', 'ANTARA']
                : ['=', '!=', 'ADA_DI', 'TIDAK_ADA_DI'];
            return this.operatorOptions.filter((o) => izin.includes(o.value));
        },
        /** Field berganti → operator disesuaikan, nilai dikosongkan, opsi sumber dimuat. */
        onFieldGanti(S, K) {
            const boleh = this.operatorUntuk(S, K).map((o) => o.value);
            if (!boleh.includes(K.operator)) K.operator = boleh[0] || '=';
            K.nilai = '';
            const m = this.metaField(S, K.field);
            if (m && m.sumber_opsi) this.loadOpsiSumber(m.sumber_opsi);
        },
        /** Opsi dinamis (mis. kampus dari Master Kampus) — pakai LABEL karena jawaban formulir menyimpan nama. */
        async loadOpsiSumber(sumber) {
            if (this.opsiSumber[sumber]) return;
            try {
                const res = await axios.get(`/api/v1/karir/options/${sumber}`, CFG);
                this.opsiSumber[sumber] = (res.data.result || []).map((o) => o.label ?? o.value);
            } catch (e) {
                this.opsiSumber[sumber] = [];
            }
        },
        /** Title Case ala Monitoring MPP (jabatan/divisi tersimpan UPPERCASE). */
        tc(s) { return titleCase(s || ''); },
        /** Label ramah untuk pratinjau. */
        labelField(S, key) { const m = this.metaField(S, key); return (m && m.label) || key || '—'; },
        opLabel(op) { const o = this.operatorOptions.find((x) => x.value === op); return o ? o.label : op; },

        // Konversi nilai (disimpan sebagai string, ANTARA/daftar dipisah koma).
        angkaVal(K) { const n = parseFloat(K.nilai); return Number.isFinite(n) ? n : undefined; },
        listVal(K) { return String(K.nilai || '').split(',').map((s) => s.trim()).filter(Boolean); },
        antaraVal(K, idx) { const p = String(K.nilai || '').split(','); const n = parseFloat(p[idx]); return Number.isFinite(n) ? n : undefined; },
        setAntara(K, idx, v) {
            const p = String(K.nilai || '').split(',');
            p[idx] = v === null || v === undefined ? '' : String(v);
            K.nilai = [p[0] ?? '', p[1] ?? ''].join(',');
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
                const params = {
                    q: this.filters.q || undefined,
                    kategori: this.tab || undefined,
                    status: this.filters.status || undefined,
                    dari: this.filters.rentang?.[0] || undefined,
                    sampai: this.filters.rentang?.[1] || undefined,
                };
                const r = (await axios.get(API, { ...CFG, params })).data.result || {};
                this.list = r.data || [];
                // Tab kategori datang dari master + hak akses pengguna.
                this.kategoriTab = r.kategori || [];
                this.totalSemua = r.total || 0;
                // Jangan tertinggal di halaman yang sudah tidak ada isinya.
                if (this.page > this.totalPages) this.page = 1;
            } catch (e) {
                this.notice('Gagal memuat data program.');
            } finally {
                this.loading = false;
            }
        },
        pilihTab(kode) { this.tab = kode; this.page = 1; this.load(); },
        cariDebounce() { if (this.cariTimer) clearTimeout(this.cariTimer); this.cariTimer = setTimeout(() => { this.page = 1; this.load(); }, 400); },
        resetFilter() { this.filters = { q: '', status: null, rentang: null }; this.page = 1; this.load(); },
        /* ── Draft "Buat Program" (sessionStorage) ────────────────────────
         * Aturan CLEAR (penting, cegah bug data hilang):
         *   • DIBUANG saat: Batal/tutup modal (tutupModal) & simpan sukses (save).
         *   • DIPERTAHANKAN saat: refresh, pindah halaman, unmount — draft utuh.
         *   • TIDAK aktif untuk mode EDIT (editingId terisi).
         */
        simpanDraftDebounce() {
            if (this.editingId || !this.show) return; // hanya sesi BUAT yang terbuka
            if (this._draftTm) clearTimeout(this._draftTm);
            this._draftTm = setTimeout(() => this.saveDraft(), 300);
        },
        saveDraft() {
            if (this.editingId || !this.show) return;
            try {
                sessionStorage.setItem(DRAFT_KEY, JSON.stringify({
                    form: this.form, langkah: this.langkah,
                    pakaiBatch: this.pakaiBatch, pakaiSyarat: this.pakaiSyarat,
                }));
            } catch (e) { /* storage penuh / privat — abaikan, tak boleh mengganggu UI */ }
        },
        clearDraft() {
            if (this._draftTm) clearTimeout(this._draftTm);
            try { sessionStorage.removeItem(DRAFT_KEY); } catch (e) { /* abaikan */ }
        },
        restoreDraft() {
            let raw = null;
            try { raw = sessionStorage.getItem(DRAFT_KEY); } catch (e) { return; }
            if (!raw) return;
            let d;
            try { d = JSON.parse(raw); } catch (e) { this.clearDraft(); return; }
            if (!d || !d.form || typeof d.form !== 'object') { this.clearDraft(); return; }
            this.editingId = null;
            this.form = d.form;
            this.langkah = Number(d.langkah) || 0;
            this.pakaiBatch = !!d.pakaiBatch;
            this.pakaiSyarat = !!d.pakaiSyarat;
            this.show = true;
            this.loadTahapFormulir();
            if (this.form.kategori) this.loadMppOptions();
            this.notice('Draft program dipulihkan. Lanjutkan, atau Batal untuk membuangnya.');
        },
        /** Tutup/Batal modal = buang draft (sesuai aturan: batal → dibuang). */
        tutupModal() {
            this.show = false;
            this.clearDraft();
        },
        /**
         * Redirect ke Master Info Divisi untuk mengisi info divisi yang kurang.
         * Draft "Buat Program" tetap di sessionStorage → begitu admin kembali ke
         * halaman ini, modal terbuka lagi dengan isian utuh (lihat restoreDraft).
         */
        gotoInfoDivisi(id) {
            this.saveDraft(); // pastikan tersimpan sebelum meninggalkan halaman
            router.visit(id ? `/master-info-divisi?buka=${id}` : '/master-info-divisi');
        },
        openCreate() {
            this.editingId = null;
            // Semua field sengaja KOSONG — mode & warna baru terisi dari preset
            // Master Kategori begitu kategori dipilih. status juga kosong: backend
            // memaksa BERJALAN untuk program baru.
            this.form = { nama: '', kategori: '', warna: '', mode: '', alur: '', jadwal: '', penyelenggara: '', status: '', batch: [], posisi: [], syarat: [] };
            this.langkah = 0;
            this.pakaiBatch = false;
            this.pakaiSyarat = false;
            this.show = true;
            this.loadTahapFormulir();
        },

        /** Lanjut ke langkah berikutnya, validasi minimum langkah aktif dulu. */
        async maju() {
            if (this.langkah === 0) {
                if (!this.form.kategori) return this.notice('Kategori wajib dipilih.');
                if (!this.form.nama.trim()) return this.notice('Nama program wajib diisi.');
            }
            if (this.langkah === 1) {
                if (this.form.posisi.some((l) => !l.mppRef)) return this.notice('Ada posisi yang belum dipilih dari MPP.');
                // WAJIB: Info Divisi tiap posisi harus sudah terisi (dipakai landing page).
                // Draft tersimpan di sessionStorage — admin bisa buka Master Info Divisi,
                // mengisinya, lalu kembali tanpa kehilangan program yang sedang dibuat.
                const refs = this.form.posisi.map((l) => l.mppRef).filter(Boolean);
                if (refs.length) {
                    try {
                        const res = await axios.post(`${API}/cek-info-divisi`, { mppRefs: refs }, CFG);
                        const belum = res.data?.result?.belumLengkap || [];
                        if (belum.length) {
                            // Tampilkan daftar divisi bermasalah + tombol redirect ke pengisian.
                            this.divisiBelum = belum;
                            this.guardDivisiShow = true;
                            return;
                        }
                    } catch (e) { /* fail-open: jika cek gagal, jangan blokir admin */ }
                }
            }
            this.langkah = Math.min(this.langkah + 1, this.langkahMeta.length - 1);
        },

        /** Matikan batch -> buang datanya; nyalakan saat kosong -> beri satu baris. */
        togglePakaiBatch(v) {
            if (v && !this.form.batch.length) this.addBatch();
            if (!v) this.form.batch = [];
        },
        togglePakaiSyarat(v) {
            if (v && !this.form.syarat.length) this.addSyarat();
            if (!v) this.form.syarat = [];
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
            this.loadMppOptions();
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
                syarat: (p.syarat || []).map((s) => ({ nama: s.nama, tahapId: s.tahapId, formulir: s.formulir, aturan: s.aturan && s.aturan.aturan ? s.aturan : { penghubung: 'DAN', aturan: [] }, aksi: s.aksi || 'TANDAI', pesanGugur: s.pesanGugur || '', uji: false, aktif: s.aktif !== false })),
            };
            this.langkah = 0;
            // Seksi opsional otomatis menyala kalau datanya sudah ada.
            this.pakaiBatch = this.form.batch.length > 0;
            this.pakaiSyarat = this.form.syarat.length > 0;
            this.show = true;
            this.loadTahapFormulir();
            this.loadMppOptions();
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
        /** Muat opsi MPP untuk kategori aktif — dipakai multi-pilih langkah Posisi. */
        async loadMppOptions() {
            if (!this.form.kategori) { this.mppOptions = []; return; }
            try {
                const res = await axios.get('/api/v1/karir/options/mpp', { ...CFG, params: { kategori: this.form.kategori } });
                this.mppOptions = res.data.result || [];
            } catch (e) {
                this.mppOptions = [];
            }
        },
        /** Klik kartu MPP → baris posisi dibuat; klik lagi → dibuang. */
        toggleMpp(o) {
            const i = this.form.posisi.findIndex((l) => l.mppRef === o.value);
            if (i >= 0) { this.form.posisi.splice(i, 1); return; }
            this.form.posisi.push({
                mppRef: o.value,
                posisi: o.posisi || '',
                departemen: o.departemen || '',
                lokasi: o.lokasi || '',
                level: o.level || '',
                kuotaMpp: o.kuota || 0,
                kuota: o.kuota || 0,
                status: 'BUKA',
            });
        },

        /** Opsi checklist Nilai tersaring kotak carinya sendiri (K._cari). */
        opsiTersaring(S, K) {
            const semua = this.opsiField(S, K.field);
            const q = String(K._cari || '').trim().toLowerCase();
            if (!q) return semua;
            return semua.filter((o) => String(o).toLowerCase().includes(q));
        },
        /** Centang/lepas satu nilai pada operator daftar (disimpan koma-terpisah). */
        toggleNilai(K, o) {
            const list = this.listVal(K);
            const i = list.indexOf(o);
            if (i >= 0) list.splice(i, 1); else list.push(o);
            K.nilai = list.join(',');
        },
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
                this.clearDraft(); // sukses simpan → draft tak perlu lagi
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
/* ═══════════ DESAIN "PROGRAM KEGIATAN" (1:1) ═══════════ */
/* Page header */
.pkg-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 18px; flex-wrap: wrap; margin-bottom: 22px; }
.pkg-head__l { min-width: 0; }
.pkg-head__title { display: flex; align-items: center; gap: 10px; }
.pkg-head__ico { width: 38px; height: 38px; border-radius: 12px; background: linear-gradient(135deg, #8b5cf6, #6366f1); color: #fff; display: flex; align-items: center; justify-content: center; flex: 0 0 auto; font-size: 1.05rem; box-shadow: 0 10px 24px rgba(99, 102, 241, .3); }
.pkg-head h1 { margin: 0; font-size: 27px; font-weight: 800; color: #0f172a; letter-spacing: -.025em; }
.pkg-head__l p { margin: 9px 0 0; font-size: 14px; color: #64748b; line-height: 1.6; max-width: 620px; text-wrap: pretty; }
.pkg-head__l p b { color: #475569; }
.pkg-newbtn { appearance: none; cursor: pointer; font-family: inherit; font-size: 14px; font-weight: 800; color: #fff; padding: 13px 22px; border-radius: 14px; border: none; background: linear-gradient(135deg, #8b5cf6, #6366f1); box-shadow: 0 14px 30px rgba(99, 102, 241, .34); display: inline-flex; align-items: center; gap: 9px; flex: 0 0 auto; transition: transform .16s; }
.pkg-newbtn:hover { transform: translateY(-2px); }

/* Stat cards */
.pkg-stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px; }
.pkg-stat { position: relative; overflow: hidden; background: rgba(255, 255, 255, .9); border: 1px solid rgba(226, 232, 240, .9); border-radius: 20px; padding: 20px 22px; box-shadow: 0 10px 30px rgba(15, 23, 42, .05); }
.pkg-stat__glow { position: absolute; right: -24px; top: -24px; width: 96px; height: 96px; border-radius: 50%; pointer-events: none; }
.pkg-stat__ico { position: relative; width: 42px; height: 42px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.15rem; }
.pkg-stat__num { position: relative; font-size: 34px; font-weight: 800; color: #0f172a; letter-spacing: -.03em; margin-top: 14px; line-height: 1; }
.pkg-stat__label { position: relative; font-size: 13px; font-weight: 700; color: #64748b; margin-top: 5px; }

/* Toolbar */
.pkg-toolbar { display: flex; align-items: center; justify-content: space-between; gap: 14px; flex-wrap: wrap; margin: 26px 0 16px; }

/* ── FILTER PANEL — card di desktop, bottom-sheet lewat FAB di mobile ── */
.pkg-filter { background: #fff; border: 1px solid rgba(15,23,42,.08); border-radius: 16px; padding: .9rem 1rem 1rem; margin-bottom: 1rem; box-shadow: 0 8px 24px rgba(15,23,42,.04); }
.pkg-filter__head { display: flex; align-items: center; justify-content: space-between; margin-bottom: .65rem; }
.pkg-filter__title { display: inline-flex; align-items: center; gap: .45rem; font-size: 12px; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; color: #4338ca; }
.pkg-filter__act { display: inline-flex; gap: .4rem; }
.pkg-filter__reset { display: inline-flex; align-items: center; gap: .3rem; border: 1px solid rgba(79,70,229,.25); background: #eef2ff; color: #4338ca; font-size: 11.5px; font-weight: 700; border-radius: 9px; padding: 4px 10px; cursor: pointer; }
.pkg-filter__reset:hover { background: #e0e7ff; }
.pkg-filter__close { display: none; border: none; background: transparent; color: #64748b; font-size: 15px; cursor: pointer; }
.pkg-filter__grid { display: grid; grid-template-columns: minmax(220px,1.6fr) 1fr 1.4fr auto; gap: .7rem; align-items: end; }
.pkg-filter__count { font-size: 12px; color: #64748b; padding-bottom: .5rem; white-space: nowrap; }
.pkg-sheetbg { display: none; }
.pkg-fab { display: none; position: fixed; right: 18px; bottom: 20px; z-index: 70; width: 52px; height: 52px; border-radius: 50%; border: none; background: linear-gradient(135deg,#8b5cf6,#6366f1); color: #fff; font-size: 19px; cursor: pointer; box-shadow: 0 12px 28px rgba(99,102,241,.45); }
.pkg-fab__badge { position: absolute; top: -4px; right: -4px; min-width: 19px; height: 19px; border-radius: 999px; background: #ef4444; color: #fff; font-size: 10.5px; font-weight: 800; display: grid; place-items: center; padding: 0 5px; border: 2px solid #fff; }

@media (max-width: 960px) { .pkg-filter__grid { grid-template-columns: 1fr 1fr; } }
@media (max-width: 640px) {
    .pkg-filter { display: none; }
    .pkg-filter.is-open { display: block; position: fixed; left: 0; right: 0; bottom: 0; z-index: 80; margin: 0; border-radius: 18px 18px 0 0; max-height: 78vh; overflow-y: auto; box-shadow: 0 -18px 40px rgba(15,23,42,.25); }
    .pkg-filter__grid { grid-template-columns: 1fr; }
    .pkg-filter__close { display: inline-flex; }
    .pkg-fab { display: grid; place-items: center; }
    .pkg-sheetbg { display: block; position: fixed; inset: 0; z-index: 75; background: rgba(15,23,42,.45); }
}
.pkg-tabs { display: flex; gap: 8px; flex-wrap: wrap; }
.pkg-tab { appearance: none; cursor: pointer; font-family: inherit; font-size: 13px; font-weight: 700; padding: 10px 15px; border-radius: 12px; border: 1px solid #e6e9f3; background: rgba(255, 255, 255, .9); color: #64748b; transition: all .16s; display: inline-flex; align-items: center; gap: 7px; }
.pkg-tab:hover { border-color: #c7cdf0; color: #4f46e5; }
.pkg-tab.on { border-color: transparent; background: linear-gradient(135deg, #8b5cf6, #6366f1); color: #fff; box-shadow: 0 10px 24px rgba(99, 102, 241, .28); }
.pkg-tab__n { font-size: 11px; font-weight: 800; padding: 1px 7px; border-radius: 7px; background: #eef0f7; color: #94a3b8; }
.pkg-tab.on .pkg-tab__n { background: rgba(255, 255, 255, .24); color: #fff; }
.pkg-search { position: relative; flex: 1; min-width: 200px; max-width: 320px; display: flex; align-items: center; }
.pkg-search .bi-search { position: absolute; left: 14px; color: #94a3b8; font-size: 14px; }
.pkg-search input { width: 100%; padding: 11px 34px 11px 40px; border-radius: 13px; border: 1px solid #e6e9f3; background: rgba(255, 255, 255, .9); font-family: inherit; font-size: 13.5px; color: #334155; outline: none; transition: all .18s; }
.pkg-search input:focus { border-color: #a5b4fc; background: #fff; box-shadow: 0 0 0 4px rgba(99, 102, 241, .12); }
.pkg-search__x { position: absolute; right: 9px; appearance: none; border: none; background: #eef0f7; width: 22px; height: 22px; border-radius: 7px; color: #64748b; cursor: pointer; font-size: 10px; display: flex; align-items: center; justify-content: center; }

/* Program list */
.pkg-list { display: flex; flex-direction: column; gap: 14px; min-height: 60px; }
.pkg-card { background: rgba(255, 255, 255, .92); border: 1px solid rgba(226, 232, 240, .9); border-radius: 18px; box-shadow: 0 10px 30px rgba(15, 23, 42, .05); transition: border-color .16s, box-shadow .16s; }
.pkg-card.open { border-color: rgba(99, 102, 241, .3); box-shadow: 0 18px 44px rgba(79, 70, 229, .12); }
.pkg-row { display: flex; align-items: flex-start; gap: 14px; padding: 18px 20px; }
.pkg-chev { appearance: none; cursor: pointer; flex: 0 0 auto; width: 34px; height: 34px; border-radius: 10px; border: 1px solid #e6e9f3; background: #fff; color: #94a3b8; display: flex; align-items: center; justify-content: center; transition: all .18s; }
.pkg-chev:hover { color: #4f46e5; border-color: #c7cdf0; }
.pkg-chev i { transition: transform .2s; }
.pkg-chev.open { color: #4f46e5; border-color: #c7cdf0; background: rgba(99, 102, 241, .08); }
.pkg-chev.open i { transform: rotate(90deg); }
.pkg-row__main { flex: 1; min-width: 0; }
.pkg-row__titlebtn { appearance: none; border: none; background: transparent; cursor: pointer; padding: 0; text-align: left; display: flex; align-items: center; gap: 9px; width: 100%; }
.pkg-dot { width: 9px; height: 9px; border-radius: 50%; flex: 0 0 auto; }
.pkg-row__title { font-size: 16.5px; font-weight: 800; color: #0f172a; letter-spacing: -.01em; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.pkg-row__meta { display: flex; align-items: center; gap: 8px; margin-top: 6px; flex-wrap: wrap; font-size: 12px; color: #8792a6; }
.pkg-code { font-family: 'JetBrains Mono', ui-monospace, monospace; font-weight: 700; color: #94a3b8; }
.pkg-sep { width: 3px; height: 3px; border-radius: 50%; background: #cbd2e0; }
.pkg-mi { display: inline-flex; align-items: center; gap: 5px; }
.pkg-mi i { color: #a2a9ba; }
.pkg-flow { min-width: 0; }
.pkg-pills { display: flex; align-items: center; gap: 8px; margin-top: 11px; flex-wrap: wrap; }
.pkg-pill { display: inline-flex; align-items: center; gap: 5px; font-size: 11px; font-weight: 700; border-radius: 8px; padding: 4px 10px; }
.pkg-pill--struct { color: #4f46e5; background: rgba(99, 102, 241, .1); }
.pkg-pill--gold { color: #b45309; background: rgba(245, 158, 11, .14); }
.pkg-pill--sky { color: #0369a1; background: rgba(14, 165, 233, .12); }
.pkg-pill--green { color: #059669; background: rgba(16, 185, 129, .12); }
.pkg-pill--amber { color: #b45309; background: rgba(245, 158, 11, .14); }
.pkg-pill--slate { color: #64748b; background: #eef0f7; }
.pkg-pill__dot { width: 6px; height: 6px; border-radius: 50%; background: currentColor; }
.pkg-pill--sm { font-size: 10.5px; padding: 3px 9px; }
.pkg-row__act { display: inline-flex; align-items: center; gap: 8px; flex: 0 0 auto; }
.pkg-ibtn { appearance: none; border: 1px solid #e6e9f3; background: #fff; width: 38px; height: 38px; border-radius: 11px; display: flex; align-items: center; justify-content: center; cursor: pointer; color: #64748b; transition: all .16s; flex: 0 0 auto; }
.pkg-ibtn:hover { color: #4f46e5; border-color: #c7cdf0; }
.pkg-ibtn--danger { border-color: #f4d0d0; color: #dc2626; }
.pkg-ibtn--danger:hover { background: #fef2f2; border-color: #f4d0d0; color: #dc2626; }

/* creator strip */
.pkg-creator { display: flex; align-items: center; gap: 9px; padding: 0 20px 16px 54px; }
.pkg-creator__av { width: 26px; height: 26px; border-radius: 8px; color: #fff; font-size: 10px; font-weight: 800; display: flex; align-items: center; justify-content: center; flex: 0 0 auto; }
.pkg-creator__name { font-size: 12px; font-weight: 700; color: #64748b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.pkg-creator__at { display: inline-flex; align-items: center; gap: 5px; font-size: 11.5px; color: #a2a9ba; flex: 0 0 auto; }

/* expanded detail */
.pkg-detail { border-top: 1px solid #eef0f7; padding: 20px; background: linear-gradient(180deg, #fbfbfe, #fff); animation: pkgIn .3s cubic-bezier(.22, 1, .36, 1) both; }
@keyframes pkgIn { from { opacity: 0; transform: translateY(-6px); } to { opacity: 1; transform: none; } }
.pkg-dhead { display: inline-flex; align-items: center; gap: 8px; font-size: 12px; font-weight: 800; letter-spacing: .1em; color: #64748b; margin-bottom: 12px; }
.pkg-dhead--amber { color: #b45309; }
.pkg-dhead--amber i { color: #d97706; }
.pkg-dhead--indigo { color: #4338ca; }
.pkg-dhead--indigo i { color: #6366f1; }
.pkg-rules { display: flex; flex-direction: column; gap: 10px; }
.pkg-rule { position: relative; border: 1px solid #f0e6d0; border-radius: 14px; background: linear-gradient(135deg, #fffdf7, #fff8ec); padding: 14px 16px 14px 18px; overflow: hidden; }
.pkg-rule__bar { position: absolute; left: 0; top: 0; bottom: 0; width: 4px; background: linear-gradient(180deg, #fbbf24, #f59e0b); }
.pkg-rule__top { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.pkg-rule__name { font-size: 13.5px; font-weight: 800; color: #1e293b; }
.pkg-rule__act { display: inline-flex; align-items: center; gap: 5px; font-size: 10.5px; font-weight: 800; border-radius: 7px; padding: 3px 9px; }
.pkg-rule__act.is-gugur { color: #dc2626; background: rgba(239, 68, 68, .1); }
.pkg-rule__act.is-mark { color: #4f46e5; background: rgba(99, 102, 241, .1); }
.pkg-rule__match { font-size: 10px; font-weight: 800; letter-spacing: .06em; color: #7c74b0; background: rgba(139, 92, 246, .12); border-radius: 7px; padding: 3px 9px; }
.pkg-rule__match--amber { color: #b45309; background: rgba(245, 158, 11, .14); }
.pkg-rule__match--off { color: #64748b; background: #eef0f7; }
.pkg-conds { display: flex; flex-wrap: wrap; gap: 7px; margin-top: 11px; }
.pkg-cond { display: inline-flex; align-items: center; gap: 6px; font-size: 11.5px; font-weight: 700; color: #475569; background: #fff; border: 1px solid #eae1cb; border-radius: 9px; padding: 5px 11px; }
.pkg-cond i { color: #8b5cf6; }
.pkg-mono { font-family: 'JetBrains Mono', ui-monospace, monospace; }
.pkg-batchset { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 4px; }
.pkg-batch { display: inline-flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 700; color: #475569; background: #f1f5f9; border-radius: 9px; padding: 6px 11px; }
.pkg-batch i { color: #8b5cf6; }
.pkg-poshead { display: flex; align-items: center; justify-content: space-between; gap: 10px; flex-wrap: wrap; margin: 22px 0 12px; }
.pkg-totalq { display: inline-flex; align-items: center; gap: 7px; font-size: 12px; font-weight: 800; color: #4f46e5; background: rgba(99, 102, 241, .1); border: 1px solid rgba(99, 102, 241, .2); border-radius: 999px; padding: 6px 13px; }

/* posisi table (grid, flat) */
.pkg-tbl { width: 100%; overflow-x: auto; }
.pkg-tbl__head, .pkg-tbl__row { display: grid; grid-template-columns: 2.4fr 1.3fr 1.6fr 1.2fr .7fr .9fr; gap: 12px; align-items: center; min-width: 640px; }
.pkg-tbl__head { padding: 0 2px 12px; font-size: 10.5px; font-weight: 800; letter-spacing: .08em; color: #a2a9ba; border-bottom: 1px solid #eef0f7; }
.pkg-tbl__row { padding: 15px 2px; border-bottom: 1px solid #f4f5fb; transition: background .14s; }
.pkg-tbl__row:hover { background: #f7f8fc; }
.pkg-c { text-align: center; justify-self: center; }
.pkg-pos { display: flex; align-items: center; gap: 11px; min-width: 0; }
.pkg-pos__dot { width: 8px; height: 8px; border-radius: 50%; flex: 0 0 auto; background: linear-gradient(135deg, #8b5cf6, #6366f1); }
.pkg-pos__txt { min-width: 0; }
.pkg-pos__title { display: block; font-size: 13.5px; font-weight: 800; color: #1e293b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.pkg-pos__lvl { display: block; font-size: 11px; color: #94a3b8; margin-top: 1px; }
.pkg-mpp { font-family: 'JetBrains Mono', ui-monospace, monospace; font-size: 11px; font-weight: 700; color: #7c74b0; background: rgba(139, 92, 246, .1); border-radius: 6px; padding: 3px 8px; }
.pkg-manual { color: #b45309; font-size: 12px; }
.pkg-td { font-size: 12.5px; color: #475569; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.pkg-td--loc { display: inline-flex; align-items: center; gap: 5px; }
.pkg-td--loc i { color: #8b5cf6; flex: 0 0 auto; }
.pkg-q { font-size: 15px; font-weight: 800; color: #0f172a; }
.pkg-tbl__empty { padding: 15px 2px; color: #94a3b8; font-size: 13px; }
.pkg-empty { padding: 40px; text-align: center; color: #a2a9ba; font-size: 13.5px; background: rgba(255, 255, 255, .7); border: 1px dashed #d9def0; border-radius: 18px; }
.pkg-empty i { font-size: 1.6rem; display: block; margin-bottom: .4rem; color: #c4b5fd; }

/* pagination */
.pkg-pager { display: flex; align-items: center; justify-content: space-between; gap: 14px; flex-wrap: wrap; margin-top: 20px; }
.pkg-pager__info { font-size: 12.5px; color: #8792a6; }
.pkg-pager__info b { color: #475569; }
.pkg-pager__nav { display: flex; align-items: center; gap: 6px; }
.pkg-pager__btn { appearance: none; cursor: pointer; min-width: 38px; height: 38px; padding: 0 10px; border-radius: 11px; border: 1px solid #e6e9f3; background: #fff; color: #64748b; font-family: inherit; font-size: 13.5px; font-weight: 700; display: inline-flex; align-items: center; justify-content: center; transition: all .16s; }
.pkg-pager__btn:hover:not(:disabled):not(.on) { border-color: #a5b4fc; color: #4f46e5; }
.pkg-pager__btn.on { background: linear-gradient(135deg, #8b5cf6, #6366f1); border-color: transparent; color: #fff; box-shadow: 0 10px 22px rgba(99, 102, 241, .3); }
.pkg-pager__btn:disabled { opacity: .4; cursor: not-allowed; }

@media (max-width: 767.98px) {
    .pkg-stats { grid-template-columns: 1fr; }
    .pkg-row { flex-wrap: wrap; }
    .pkg-row__act { width: 100%; justify-content: flex-end; }
}

.pgk-head-act { display: inline-flex; align-items: center; gap: .4rem; margin-left: auto; }
/* Langkah 1: kategori dulu — baris tunggal + ajakan sebelum kategori dipilih */
.wca-frow--single { grid-template-columns: 1fr !important; }
.pgk-waitkat { display: flex; align-items: center; gap: 10px; margin-top: .9rem; padding: 14px 16px; border: 1px dashed #d9def0; border-radius: 14px; background: #fbfbfe; color: #64748b; font-size: 13px; }
.pgk-waitkat i { font-size: 1.1rem; color: #8b5cf6; flex: 0 0 auto; }
.pgk-waitkat strong { color: #4f46e5; }
/* Warna label: baris swatch klik-langsung (mengisi ruang, bukan kotak mungil) */
.pgk-swatches { display: flex; align-items: center; flex-wrap: wrap; gap: 9px; min-height: 40px; }
.pgk-swatch { appearance: none; border: none; cursor: pointer; width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #fff; font-size: .8rem; box-shadow: inset 0 0 0 1px rgba(15, 23, 42, .08); transition: transform .14s, box-shadow .14s; }
.pgk-swatch:hover { transform: scale(1.12); }
.pgk-swatch.on { box-shadow: 0 0 0 2px #fff, 0 0 0 4px #6366f1, 0 6px 14px rgba(99, 102, 241, .3); transform: scale(1.08); }
/* ── Pemilih MPP model KARTU (ala Monitoring MPP) ── */
.pgk-mpppick { margin-bottom: .9rem; }
.pgk-mpppick__head { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; margin-bottom: .6rem; }
.pgk-mppsearch { position: relative; display: flex; align-items: center; flex: 1; min-width: 220px; max-width: 340px; }
.pgk-mppsearch .bi-search { position: absolute; left: 12px; color: #94a3b8; font-size: .8rem; }
.pgk-mppsearch input { width: 100%; padding: 9px 32px 9px 34px; border-radius: 11px; border: 1px solid #e6e9f3; background: #fff; font: inherit; font-size: .8rem; color: #334155; outline: none; transition: all .18s; }
.pgk-mppsearch input:focus { border-color: #a5b4fc; box-shadow: 0 0 0 4px rgba(99, 102, 241, .12); }
.pgk-mppsearch button { position: absolute; right: 8px; appearance: none; border: none; background: #eef0f7; width: 20px; height: 20px; border-radius: 6px; color: #64748b; cursor: pointer; font-size: .6rem; display: flex; align-items: center; justify-content: center; }
/* Grid 3 kolom + scrollbar sendiri; kartu meniru persis MppCard Monitoring MPP. */
.pgk-mppgrid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; max-height: 350px; overflow-y: auto; padding: 3px 6px 6px 3px; }
@media (max-width: 900px) { .pgk-mppgrid { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 620px) { .pgk-mppgrid { grid-template-columns: 1fr; } }
.pgk-mppcard { position: relative; overflow: hidden; display: flex; flex-direction: column; gap: .55rem; cursor: pointer; background: #fff; border: 1px solid #e6e9f3; border-radius: 1.15rem; padding: 1rem 1.05rem; box-shadow: 0 6px 20px rgba(15, 23, 42, .04); transition: transform .2s cubic-bezier(.22, 1, .36, 1), box-shadow .2s ease, border-color .2s ease; }
.pgk-mppcard::after { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px; background: linear-gradient(90deg, #6366f1, #8b5cf6, #6366f1); opacity: 0; transition: opacity .25s ease; }
.pgk-mppcard:hover::after, .pgk-mppcard.on::after { opacity: 1; }
.pgk-mppcard:hover { transform: translateY(-4px); border-color: #c7d2fe; box-shadow: 0 18px 36px rgba(79, 70, 229, .14), 0 6px 12px rgba(15, 23, 42, .05); }
.pgk-mppcard.on { border-color: #6366f1; background: linear-gradient(135deg, #fbfaff, #f5f4ff); box-shadow: 0 12px 28px rgba(99, 102, 241, .18); }
.pgk-mppcard__head { display: flex; align-items: flex-start; gap: 8px; }
.pgk-mppcard__check { margin-top: 2px; width: 20px; height: 20px; border-radius: 7px; border: 1.5px solid #d9def0; background: #fff; color: transparent; font-size: .7rem; display: flex; align-items: center; justify-content: center; flex: 0 0 auto; transition: all .16s; }
.pgk-mppcard.on .pgk-mppcard__check { border-color: transparent; background: linear-gradient(135deg, #8b5cf6, #6366f1); color: #fff; }
.pgk-mppcard__title { flex: 1; min-width: 0; margin: 0; font-size: .95rem; font-weight: 900; line-height: 1.25; color: #0f172a; }
.pgk-mppcard__info { appearance: none; border: none; background: transparent; cursor: pointer; color: #94a3b8; font-size: .85rem; padding: 2px; flex: 0 0 auto; transition: color .16s; }
.pgk-mppcard__info:hover { color: #4f46e5; }
.pgk-mppcard__badges { display: flex; flex-wrap: wrap; gap: .35rem; }
.pgk-bdg { display: inline-flex; align-items: center; gap: .35rem; font-size: .68rem; font-weight: 800; border-radius: .55rem; padding: .24rem .55rem; }
.pgk-bdg--indigo { color: #4338ca; background: rgba(99, 102, 241, .12); }
.pgk-bdg--sky { color: #0369a1; background: rgba(14, 165, 233, .12); }
.pgk-mppcard__no { display: inline-flex; align-items: center; gap: .15rem; font-size: .74rem; font-weight: 800; color: #94a3b8; font-family: 'JetBrains Mono', monospace; }
.pgk-mppcard__tags { display: flex; flex-wrap: wrap; gap: .4rem; }
.mppt { display: inline-flex; align-items: center; gap: .35rem; font-size: .68rem; font-weight: 800; white-space: nowrap; padding: .26rem .55rem; border-radius: .6rem; }
.mppt--emp { background: linear-gradient(135deg, rgba(99, 102, 241, .12), rgba(139, 92, 246, .08)); color: #4338ca; border: 1px solid rgba(99, 102, 241, .18); }
.mppt--wp { background: linear-gradient(135deg, rgba(16, 185, 129, .1), rgba(6, 182, 212, .06)); color: #0f766e; border: 1px solid rgba(16, 185, 129, .18); }
.mppt--exp { background: linear-gradient(135deg, rgba(245, 158, 11, .1), rgba(251, 191, 36, .06)); color: #b45309; border: 1px solid rgba(245, 158, 11, .2); }
.mppt__dot { width: .42rem; height: .42rem; border-radius: 50%; flex: none; }
.mppt__dot--emp { background: #6366f1; box-shadow: 0 0 0 3px rgba(99, 102, 241, .2); }
.mppt__dot--wp { background: #10b981; box-shadow: 0 0 0 3px rgba(16, 185, 129, .2); }
.mppt__dot--exp { background: #f59e0b; box-shadow: 0 0 0 3px rgba(245, 158, 11, .2); }
.pgk-mppcard__meta { display: flex; flex-wrap: wrap; gap: .35rem .9rem; padding-top: .45rem; border-top: 1px dashed #e6e9f3; margin-top: auto; }
.pgk-mppcard__meta span { display: inline-flex; align-items: center; gap: .35rem; font-size: .72rem; font-weight: 700; color: #475569; }
.pgk-mppcard__meta i { color: #6366f1; }
/* Panel "Sudah Dipilih" — scrollbar sendiri */
.pgk-selpanel { margin-top: 1rem; border: 1px solid #e6e9f3; border-radius: 14px; background: #fbfbfe; overflow: hidden; }
.pgk-selpanel__hd { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; padding: 10px 14px; border-bottom: 1px solid #eef0f7; background: #fff; font-size: .8rem; font-weight: 800; color: #1e293b; }
.pgk-selpanel__hd > i { color: #059669; }
.pgk-selpanel__tot { display: inline-flex; align-items: center; gap: 6px; font-size: .68rem; font-weight: 800; color: #4f46e5; background: rgba(99, 102, 241, .1); border-radius: 999px; padding: 4px 11px; }
.pgk-selpanel__hint { margin-left: auto; font-size: .68rem; font-weight: 600; color: #94a3b8; }
.pgk-selpanel__body { max-height: 280px; overflow-y: auto; padding: 12px; }
/* ── Panel checklist Nilai (operator daftar) ── */
.pgk-multichk { border: 1px solid #e6e9f3; border-radius: 12px; background: #fff; overflow: hidden; }
.pgk-multichk__search { position: relative; display: flex; align-items: center; border-bottom: 1px solid #eef0f7; }
.pgk-multichk__search .bi-search { position: absolute; left: 11px; color: #94a3b8; font-size: .72rem; }
.pgk-multichk__search input { width: 100%; border: none; outline: none; padding: 8px 10px 8px 30px; font: inherit; font-size: .76rem; color: #334155; background: transparent; }
.pgk-multichk__list { max-height: 170px; overflow-y: auto; padding: 4px; }
.pgk-multichk__item { display: flex; align-items: center; gap: 8px; padding: 6px 9px; border-radius: 8px; cursor: pointer; font-size: .78rem; color: #475569; transition: background .12s; }
.pgk-multichk__item:hover { background: #f7f8fc; }
.pgk-multichk__item.on { background: rgba(99, 102, 241, .07); color: #4338ca; font-weight: 700; }
.pgk-multichk__item input { accent-color: #6366f1; }
.pgk-multichk__item span { flex: 1; min-width: 0; }
.pgk-multichk__item .bi-check-lg { color: #6366f1; font-size: .72rem; }
.pgk-multichk__empty { padding: 12px; text-align: center; color: #94a3b8; font-size: .74rem; }
.pgk-multichk__foot { display: flex; align-items: center; justify-content: space-between; gap: 8px; padding: 7px 11px; border-top: 1px solid #eef0f7; background: #fbfbfe; font-size: .72rem; color: #64748b; }
.pgk-multichk__foot b { color: #4f46e5; }
.pgk-multichk__foot button { appearance: none; border: none; background: transparent; cursor: pointer; color: #dc2626; font: inherit; font-size: .72rem; font-weight: 700; display: inline-flex; align-items: center; gap: 4px; }
.pgk-lblinfo { color: #a5b4fc; font-size: .74rem; cursor: help; margin-left: 3px; }
/* ── Baris posisi terpilih (elegan: chip info, bukan input mati) ── */
.pgk-posrow { position: relative; display: flex; align-items: center; gap: 16px; background: #fff; border: 1px solid #e6e9f3; border-radius: 14px; padding: 13px 14px 13px 20px; overflow: hidden; transition: box-shadow .16s, border-color .16s; }
.pgk-posrow:hover { border-color: #c7cdf0; box-shadow: 0 8px 20px rgba(15, 23, 42, .06); }
.pgk-posrow__accent { position: absolute; left: 0; top: 0; bottom: 0; width: 4px; background: linear-gradient(180deg, #8b5cf6, #6366f1); }
.pgk-posrow__main { flex: 1; min-width: 0; }
.pgk-posrow__title { display: flex; align-items: center; gap: 9px; flex-wrap: wrap; }
.pgk-posrow__title strong { font-size: .9rem; font-weight: 800; color: #1e293b; }
.pgk-posrow__meta { display: flex; flex-wrap: wrap; gap: 5px 14px; margin-top: 6px; }
.pgk-posrow__meta span { display: inline-flex; align-items: center; gap: 5px; font-size: .72rem; color: #64748b; }
.pgk-posrow__meta i { color: #8b5cf6; }
.pgk-posrow__kuota, .pgk-posrow__status { display: flex; flex-direction: column; gap: 4px; flex: 0 0 auto; }
.pgk-posrow__kuota label, .pgk-posrow__status label { font-size: .66rem; font-weight: 800; letter-spacing: .04em; text-transform: uppercase; color: #94a3b8; }
.pgk-posrow__kuota label small { font-weight: 600; text-transform: none; letter-spacing: 0; color: #b9c0d4; }
.pgk-posrow__del { appearance: none; border: 1px solid #f4d0d0; background: #fff; width: 36px; height: 36px; border-radius: 10px; display: flex; align-items: center; justify-content: center; cursor: pointer; color: #dc2626; flex: 0 0 auto; transition: background .16s; }
.pgk-posrow__del:hover { background: #fef2f2; }
@media (max-width: 640px) { .pgk-posrow { flex-wrap: wrap; } }
/* ── Pratinjau (langkah 4) ── */
.pgk-prev { display: flex; flex-direction: column; gap: 12px; }
/* Hero ringkasan */
.pgk-prev__hero { position: relative; overflow: hidden; display: flex; background: linear-gradient(135deg, #fbfaff, #f3f2ff 60%, #eef2ff); border: 1px solid rgba(99, 102, 241, .16); border-radius: 16px; }
.pgk-prev__heroBar { width: 6px; flex: 0 0 auto; }
.pgk-prev__heroMain { flex: 1; min-width: 0; padding: 16px 18px; }
.pgk-prev__heroTop { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.pgk-prev__heroTop h4 { margin: 0; font-size: 1.05rem; font-weight: 900; color: #1e1b4b; letter-spacing: -.01em; min-width: 0; overflow-wrap: anywhere; }
.pgk-prev__heroChips { display: flex; flex-wrap: wrap; gap: 7px 16px; margin-top: 10px; }
.pgk-prev__heroChips span { display: inline-flex; align-items: center; gap: 6px; font-size: .74rem; font-weight: 600; color: #64748b; min-width: 0; }
.pgk-prev__heroChips i { color: #8b5cf6; }
.pgk-prev__heroChips code { font-family: 'JetBrains Mono', monospace; font-size: .68rem; font-weight: 700; color: #4338ca; background: rgba(99, 102, 241, .09); border-radius: 6px; padding: 2px 7px; overflow-wrap: anywhere; }
/* Stat mini */
.pgk-prev__stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; }
@media (max-width: 640px) { .pgk-prev__stats { grid-template-columns: repeat(2, 1fr); } }
.pgk-prev__stats > div { display: flex; align-items: center; gap: 10px; background: #fff; border: 1px solid #e6e9f3; border-radius: 13px; padding: 11px 13px; }
.pgk-prev__sico { width: 34px; height: 34px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: .9rem; flex: 0 0 auto; }
.pgk-prev__stats b { font-size: 1.15rem; font-weight: 900; color: #0f172a; }
.pgk-prev__stats > div > span:last-child { font-size: .7rem; font-weight: 700; color: #94a3b8; }
.pgk-prev__card { background: #fff; border: 1px solid #e6e9f3; border-radius: 14px; padding: 15px 17px; }
.pgk-prev__hd { display: flex; align-items: center; gap: 8px; font-size: .82rem; font-weight: 800; color: #1e293b; margin-bottom: 11px; flex-wrap: wrap; }
.pgk-prev__hd i { color: #6366f1; }
.pgk-prev__list { display: flex; flex-direction: column; }
.pgk-prev__item { display: flex; align-items: center; gap: 9px; padding: 8px 2px; border-bottom: 1px solid #f4f5fb; }
.pgk-prev__item:last-child { border-bottom: none; }
.pgk-prev__dot { width: 7px; height: 7px; border-radius: 50%; background: linear-gradient(135deg, #8b5cf6, #6366f1); flex: 0 0 auto; }
.pgk-prev__nm { font-size: .82rem; font-weight: 700; color: #334155; min-width: 0; }
.pgk-prev__kt { margin-left: auto; font-size: .72rem; font-weight: 800; color: #4f46e5; background: rgba(99, 102, 241, .08); border-radius: 8px; padding: 3px 10px; flex: 0 0 auto; }
.pgk-prev__tag { display: inline-flex; align-items: center; gap: 5px; font-size: .64rem; font-weight: 800; border-radius: 7px; padding: 3px 9px; }
.pgk-prev__tag--ind { color: #4f46e5; background: rgba(99, 102, 241, .1); }
.pgk-prev__tag--vio { color: #7c3aed; background: rgba(139, 92, 246, .12); }
.pgk-prev__tag--red { color: #dc2626; background: rgba(239, 68, 68, .1); }
.pgk-prev__syarat { border-top: 1px solid #f4f5fb; padding-top: 10px; margin-top: 10px; }
.pgk-prev__syarat:first-of-type { border-top: none; padding-top: 0; margin-top: 0; }
.pgk-prev__srow { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.pgk-prev__srow > b { font-size: .82rem; color: #1e293b; }
.pgk-prev__conds { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 8px; }
.pgk-prev__cond { display: inline-flex; align-items: center; gap: 6px; font-size: .72rem; color: #475569; background: #f7f8fc; border: 1px solid #eef0f7; border-radius: 8px; padding: 5px 10px; }
.pgk-prev__cond i { color: #8b5cf6; }
.pgk-prev__cond b { color: #4338ca; }
.pgk-prev__none { font-size: .76rem; color: #94a3b8; }
.pgk-mppline { display: flex; align-items: center; gap: 9px; flex-wrap: wrap; }
.pgk-mppline__dot { width: 9px; height: 9px; border-radius: 50%; background: linear-gradient(135deg, #8b5cf6, #6366f1); flex: 0 0 auto; }
.pgk-mppline strong { font-size: .9rem; color: #1e293b; font-weight: 800; }
/* Nilai rentang (ANTARA) */
.pgk-antara { display: flex; align-items: center; gap: 7px; }
.pgk-antara .el-input-number { flex: 1; width: auto; }
.pgk-antara__sep { color: #94a3b8; font-weight: 700; }
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

/* ── Stepper wizard ─────────────────────────────────────────── */
/* Stepper: dot di atas, label di bawah — garis penghubung lewat PUSAT antar-dot. */
.pgk-steps { display: flex; margin-bottom: 1.15rem; padding-bottom: .9rem; border-bottom: 1px solid rgba(11, 16, 51, .09); }
.pgk-step { position: relative; flex: 1; display: flex; flex-direction: column; align-items: center; gap: .45rem; border: 0; background: transparent; cursor: pointer; padding: .2rem .4rem; font: inherit; text-align: center; }
.pgk-step:not(:first-child)::before { content: ''; position: absolute; top: calc(.2rem + .95rem - 1px); left: calc(-50% + 1.35rem); right: calc(50% + 1.35rem); height: 2px; border-radius: 99px; background: #e6e9f3; }
.pgk-step.done::before, .pgk-step.cur::before { background: linear-gradient(90deg, #a5b4fc, #8b5cf6); }
.pgk-step:disabled { cursor: not-allowed; opacity: .55; }
.pgk-step__dot { position: relative; z-index: 1; flex: none; width: 1.9rem; height: 1.9rem; display: grid; place-items: center; border-radius: 50%; background: #eef2f7; color: #94a3b8; font-size: .8rem; transition: all 200ms ease; box-shadow: 0 0 0 4px #fff; }
.pgk-step.done .pgk-step__dot { background: rgba(16, 185, 129, .15); color: #059669; }
.pgk-step.cur .pgk-step__dot { background: linear-gradient(140deg, #4f46e5, #7c3aed); color: #fff; box-shadow: 0 6px 14px -6px rgba(79, 70, 229, .9); }
.pgk-step__lbl { font-size: 12.5px; font-weight: 600; color: #94a3b8; line-height: 1.25; }
.pgk-step__lbl small { font-weight: 500; color: #cbd5e1; }
.pgk-step.cur .pgk-step__lbl { color: #4338ca; }
.pgk-step.done .pgk-step__lbl { color: #059669; }

/* ── Panel per langkah ──────────────────────────────────────── */
.pgk-panel__bar { display: flex; align-items: flex-start; justify-content: space-between; gap: .6rem; margin-bottom: .8rem; }
.pgk-panel__title { font-size: 13px; font-weight: 700; color: #334155; display: flex; align-items: center; gap: .4rem; }
.pgk-panel__note { font-size: 11.5px; color: #94a3b8; margin-top: .15rem; }
.pgk-panel__intro { display: flex; align-items: center; gap: .5rem; font-size: 12.5px; color: #475569; background: rgba(79, 70, 229, .06); border-radius: 10px; padding: .6rem .8rem; margin-bottom: 1rem; }

/* ── Seksi opsional dengan toggle ───────────────────────────── */
.pgk-opsi { border: 1px solid rgba(11, 16, 51, .1); border-radius: 14px; margin-bottom: .9rem; overflow: hidden; transition: border-color 180ms ease; }
.pgk-opsi.is-on { border-color: rgba(79, 70, 229, .3); }
.pgk-opsi__hd { display: flex; align-items: center; flex-wrap: wrap; gap: .3rem .7rem; padding: .8rem 1rem; cursor: pointer; margin: 0; }
.pgk-opsi.is-on .pgk-opsi__hd { background: rgba(79, 70, 229, .04); border-bottom: 1px solid rgba(79, 70, 229, .12); }
.pgk-opsi__t { font-size: 13.5px; font-weight: 700; color: #0f172a; display: inline-flex; align-items: center; gap: .4rem; }
.pgk-opsi__hd small { flex: 1 1 100%; font-size: 11.5px; color: #94a3b8; padding-left: 3rem; }
.pgk-opsi__body { padding: .9rem 1rem 1rem; }
.pgk-opsi__toolbar { display: flex; align-items: center; gap: .5rem; margin-bottom: .6rem; }
.pgk-req { font-weight: 600; font-size: 11px; color: #b45309; background: #fef3c7; border-radius: 999px; padding: .1rem .45rem; margin-left: .35rem; }
.pgk-hint { font-size: 11.5px; color: #64748b; margin-top: .3rem; }
.pgk-hint--warn { color: #b45309; }
.pgk-syarat { border: 1px solid rgba(79,70,229,.18); border-radius: 14px; padding: .9rem; margin-bottom: .7rem; background: #fff; }
/* Identitas syarat: nama + tahap berlabel, tombol hapus di kanan. */
.pgk-syarat__id { display: grid; grid-template-columns: 1fr 1fr auto; gap: .6rem; align-items: end; margin-bottom: .7rem; }
.pgk-syarat__del { margin-bottom: .1rem; }
.pgk-pesan { margin-top: .7rem; }
.pgk-pesan .wca-field-lbl { display: flex; align-items: center; gap: .3rem; color: #4338ca; }
.pgk-isipesan { margin-top: .35rem; border: 0; background: transparent; color: #7c3aed; font: inherit; font-size: 11.5px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: .3rem; padding: 0; }
.pgk-isipesan:hover { text-decoration: underline; }

.pgk-aturan { border: 1px solid rgba(79,70,229,.14); border-radius: 12px; padding: .7rem; background: rgba(248,250,252,.7); }
.pgk-aturan__bar { display: flex; align-items: center; flex-wrap: wrap; gap: .4rem; font-size: 12.5px; color: #475569; margin-bottom: .6rem; }

/* Kondisi: grid berlabel yang lega — field cukup lebar sehingga tidak
   terpotong jadi "U". Turun 1 kolom di layar sempit. */
.pgk-kondisi { display: grid; grid-template-columns: minmax(0, 1.7fr) minmax(0, 1.2fr) minmax(0, 1fr) auto; gap: .5rem; align-items: end; margin-bottom: .5rem; }
.pgk-kondisi__f, .pgk-kondisi__o, .pgk-kondisi__v { min-width: 0; }
.pgk-kondisi__del { margin-bottom: .1rem; }
.pgk-kondisi-kosong { font-size: 11.5px; color: #94a3b8; padding: .3rem 0; }
.pgk-addkondisi { margin-top: .2rem; width: 100%; border: 1px dashed rgba(79,70,229,.3); border-radius: 9px; background: transparent; color: #4338ca; font: inherit; font-size: 12px; font-weight: 600; padding: .45rem; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; gap: .35rem; }
.pgk-addkondisi:hover { background: rgba(79,70,229,.06); }

/* Aksi + mode uji, berdampingan rapi. */
.pgk-syarat__opt { display: grid; grid-template-columns: 1fr 1fr; gap: .7rem; align-items: center; margin-top: .7rem; }
.pgk-syarat__uji { display: flex; flex-direction: column; gap: .1rem; }
.pgk-syarat__uji small { font-size: 11px; color: #94a3b8; padding-left: 1.6rem; }
.pgk-syarat-ro { border-left: 3px solid #4f46e5; padding: .5rem .7rem; margin-bottom: .5rem; background: rgba(79,70,229,.04); border-radius: 0 10px 10px 0; }
.pgk-syarat-ro__top { display: flex; align-items: center; flex-wrap: wrap; gap: .4rem; margin-bottom: .35rem; font-size: 13px; }
.pgk-gab { font-size: 10.5px; font-weight: 700; letter-spacing: .06em; color: #4338ca; background: rgba(79,70,229,.12); border-radius: 999px; padding: .1rem .45rem; }
.pgk-mpp { font-size: 11.5px; color: #4338ca; background: rgba(79, 70, 229, .08); border-radius: 6px; padding: .1rem .35rem; }

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
    .pgk-kondisi { grid-template-columns: 1fr 1fr; }
    .pgk-kondisi__f { grid-column: 1 / -1; }
    .pgk-kondisi__del { grid-column: 2; justify-self: end; margin-bottom: 0; }
}
@media (max-width: 560px) {
    .pgk-row__grid--batch,
    .pgk-row__grid--posisi,
    .pgk-row__grid--kriteria { grid-template-columns: 1fr; }
    .pgk-row .wca-iconbtn { margin-top: 0; }
    .pgk-row__del { margin-top: 0 !important; }
    .pgk-steps { gap: .2rem; }
    .pgk-step__lbl { display: none; }
    .pgk-syarat__id { grid-template-columns: 1fr auto; }
    .pgk-syarat__opt { grid-template-columns: 1fr; }
}

/* ── Guard Info Divisi ── */
.gid-overlay { position: fixed; inset: 0; z-index: 3000; background: rgba(15, 23, 42, .5); display: grid; place-items: center; padding: 1rem; }
.gid-modal { width: 100%; max-width: 520px; background: #fff; border-radius: 16px; overflow: hidden; box-shadow: 0 24px 60px rgba(15, 23, 42, .3); }
.gid-modal__head { display: flex; gap: .8rem; align-items: flex-start; padding: 1.1rem 1.2rem .9rem; border-bottom: 1px solid rgba(15, 23, 42, .08); }
.gid-modal__ico { flex: none; width: 2.4rem; height: 2.4rem; border-radius: 11px; display: grid; place-items: center; background: rgba(245, 158, 11, .14); color: #d97706; font-size: 18px; }
.gid-modal__head h3 { margin: 0; font-size: 15.5px; font-weight: 800; color: #1e293b; }
.gid-modal__head p { margin: .2rem 0 0; font-size: 12px; line-height: 1.55; color: #64748b; }
.gid-modal__x { flex: none; border: none; background: transparent; color: #94a3b8; font-size: 15px; cursor: pointer; padding: 2px; }
.gid-modal__x:hover { color: #64748b; }
.gid-list { padding: .8rem 1.2rem; display: flex; flex-direction: column; gap: .5rem; max-height: 44vh; overflow-y: auto; }
.gid-item { display: flex; align-items: center; justify-content: space-between; gap: .7rem; padding: .6rem .8rem; border: 1px solid rgba(15, 23, 42, .1); border-radius: 11px; background: #f8fafc; }
.gid-item__l { display: inline-flex; align-items: center; gap: .45rem; font-size: 13px; color: #334155; min-width: 0; }
.gid-item__l > i { color: #94a3b8; flex: none; }
.gid-item__badge { font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: .03em; color: #b91c1c; background: rgba(239, 68, 68, .12); border-radius: 999px; padding: 2px 8px; white-space: nowrap; }
.gid-item__btn { flex: none; display: inline-flex; align-items: center; gap: .35rem; border: none; background: linear-gradient(135deg, #6366f1, #4f46e5); color: #fff; font-size: 11.5px; font-weight: 700; border-radius: 9px; padding: 7px 12px; cursor: pointer; box-shadow: 0 6px 16px rgba(79, 70, 229, .25); }
.gid-item__btn:hover { transform: translateY(-1px); }
.gid-modal__foot { display: flex; align-items: center; justify-content: space-between; gap: .6rem; padding: .9rem 1.2rem; border-top: 1px solid rgba(15, 23, 42, .08); background: #fbfcfe; }
</style>

<!-- Konten el-popover & el-option di-teleport ke <body>, jadi butuh style TIDAK ber-scope. -->
<style>
.pgk-mppdetail { display: flex; flex-direction: column; gap: 6px; font-size: 12.5px; color: #475569; }
.pgk-mppdetail strong { font-size: 13.5px; color: #1e293b; margin-bottom: 2px; }
.pgk-mppdetail div { display: flex; align-items: center; gap: 8px; }
.pgk-mppdetail i { color: #8b5cf6; width: 14px; text-align: center; }
.pgk-opdesc { display: flex; flex-direction: column; line-height: 1.3; padding: 3px 0; }
.pgk-opdesc span { font-size: 13px; font-weight: 600; }
.pgk-opdesc small { font-size: 11px; color: #94a3b8; }
</style>
