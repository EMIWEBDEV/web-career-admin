<!-- WEB CAREER ADMIN — WORKLIST PELAMAR, 1:1 desain "Worklist Pelamar" (Claude
     Design): panel program kiri (cari + chip filter + kartu ber-aksen), papan
     kanban tahap alur, drawer detail kandidat (progress alur, akordeon formulir,
     dokumen + lightbox), aksi Loloskan / Tidak Lolos. Data 100% dari API nyata. -->
<template>
    <Head><title>Worklist Pelamar — EVO Career</title></Head>

    <div class="plw">
        <!-- ═══ PANEL PROGRAM (KIRI) ═══ -->
        <aside class="plw-panel">
            <div class="plw-panel__head">
                <div class="plw-panel__toprow">
                    <div class="plw-panel__title">DAFTAR PROGRAM</div>
                    <span class="plw-panel__count">{{ total }} aktif</span>
                </div>
                <div class="plw-search">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="2.2" stroke-linecap="round"><circle cx="11" cy="11" r="7" /><path d="M21 21l-4.3-4.3" /></svg>
                    <input v-model="q" type="text" placeholder="Cari program..." @input="cariDebounce" />
                </div>
                <div class="plw-chips">
                    <button type="button" class="plw-chip" :class="{ 'is-on': jenis === '' }" @click="setJenis('')">Semua</button>
                    <button v-for="t in talent" :key="t.kode" type="button" class="plw-chip" :class="{ 'is-on': jenis === t.kode }" @click="setJenis(t.kode)">{{ t.label }}</button>
                </div>
            </div>

            <div class="plw-panel__list">
                <div v-if="loadingProg" class="plw-load"><span class="plw-spin"></span> Memuat program…</div>
                <div v-else-if="!programs.length" class="plw-empty">Tidak ada program</div>
                <button
                    v-for="p in programs"
                    :key="p.id"
                    type="button"
                    class="plw-prog"
                    :class="{ 'is-on': p.id === selectedId }"
                    :style="{ '--acc': aksen(p) }"
                    @click="pilihProgram(p)"
                >
                    <span class="plw-prog__bar"></span>
                    <span class="plw-prog__toprow">
                        <span class="plw-prog__tag" :class="p.kategori === 'MT' ? 'is-mt' : 'is-rek'">{{ katLabel(p.kategori) }}</span>
                        <span class="plw-prog__code">{{ p.kode }}</span>
                    </span>
                    <span class="plw-prog__title">{{ p.nama }}</span>
                    <span class="plw-prog__meta">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex: 0 0 auto"><rect x="3" y="4" width="18" height="16" rx="2" /><path d="M3 9h18" /></svg>
                        <span class="plw-ell">{{ p.penyelenggara }}</span>
                    </span>
                    <span class="plw-prog__meta">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex: 0 0 auto"><path d="M6 3v12" /><circle cx="6" cy="18" r="3" /><circle cx="18" cy="6" r="3" /><path d="M18 9c0 6-12 3-12 9" /></svg>
                        <span class="plw-ell">{{ p.alur || 'Belum ada alur' }}<template v-if="p.jumlahTahap"> · {{ p.jumlahTahap }} tahap</template></span>
                    </span>
                    <span class="plw-prog__stats">
                        <span class="plw-prog__stat">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#6366f1" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" /><circle cx="9" cy="7" r="4" /></svg>
                            <b style="color: #4f46e5">{{ p.aktif }}</b> aktif
                        </span>
                        <span class="plw-prog__stat">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2.4"><path d="M20 6L9 17l-5-5" /></svg>
                            <b style="color: #059669">{{ p.lolos }}</b> lolos
                        </span>
                        <span>{{ p.pelamar }} total</span>
                    </span>
                </button>

                <div v-if="totalPage > 1" class="plw-pager">
                    <button type="button" class="plw-pager__btn" :disabled="page <= 1" @click="gotoPage(page - 1)"><i class="bi bi-chevron-left"></i></button>
                    <span>{{ page }} / {{ totalPage }}</span>
                    <button type="button" class="plw-pager__btn" :disabled="page >= totalPage" @click="gotoPage(page + 1)"><i class="bi bi-chevron-right"></i></button>
                </div>
            </div>
        </aside>

        <!-- ═══ MAIN (KANAN) ═══ -->
        <main class="plw-main">
            <div class="plw-blob plw-blob--a"></div>
            <div class="plw-blob plw-blob--b"></div>

            <div class="plw-main__inner">
                <div class="plw-hgroup">
                    <h1 class="plw-h1">Worklist Pelamar</h1>
                    <p class="plw-sub">Pilih program di panel kiri, lalu pantau &amp; gerakkan kandidat pada alur seleksinya.</p>
                </div>

                <template v-if="detail.program">
                    <div class="plw-progrow">
                        <div style="min-width: 0; flex: 1">
                            <div class="plw-eyebrow">{{ katLabel(detail.program.kategori).toUpperCase() }}</div>
                            <div class="plw-progtitle">{{ detail.program.nama }}</div>
                            <div class="plw-progflow">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex: 0 0 auto"><path d="M6 3v12" /><circle cx="6" cy="18" r="3" /><circle cx="18" cy="6" r="3" /><path d="M18 9c0 6-12 3-12 9" /></svg>
                                <span class="plw-ell">{{ detail.program.alur || 'Belum ada alur' }} · {{ detail.kolom.length }} tahap</span>
                            </div>
                        </div>
                        <div class="plw-progact">
                            <button type="button" class="plw-btn-jadwal" @click="goJadwal">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" /><path d="M3 10h18M8 2v4M16 2v4" /><path d="M9 15l2 2 4-4" /></svg>
                                Jadwalkan Tes
                            </button>
                            <button type="button" class="plw-btn-reload" title="Muat ulang" @click="muatDetail(selectedId)">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-3-6.7" /><path d="M21 4v5h-5" /></svg>
                            </button>
                        </div>
                    </div>

                    <!-- BARIS PENYARING — tab keadaan + select.
                         Lowongan dulu tampil sebagai kartu besar sebaris: pada
                         program dengan 8 lowongan, kartunya sendiri memakan satu
                         layar penuh sebelum papan kanban-nya kelihatan. Sebagai
                         select ia menempati satu baris, dan pencariannya jauh
                         lebih cepat daripada memindai kartu satu per satu. -->
                    <!-- SATU BARIS: keadaan di kiri, penyaring di ujung kanan.
                         Sebelumnya penyaring bertumpuk vertikal dan mendorong
                         papan kanban turun sampai hampir keluar layar — padahal
                         papan itulah isi halamannya. -->
                    <div class="plw-filterbar">
                        <div class="plw-tabs">
                            <button type="button" class="plw-tab" :class="{ 'is-run': statusTab === 'AKTIF' }" @click="statusTab = 'AKTIF'">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M5 3h14l-6 8v7l-2 1v-8L5 3z" /></svg>
                                Berjalan <span class="plw-tab__badge">{{ jmlAktif }}</span>
                            </button>
                            <button type="button" class="plw-tab" :class="{ 'is-rej': statusTab === 'GUGUR' }" @click="statusTab = 'GUGUR'">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9" /><path d="M15 9l-6 6M9 9l6 6" /></svg>
                                Tidak Lolos <span class="plw-tab__badge">{{ jmlGugur }}</span>
                            </button>
                            <!-- DITAHAN berdiri sendiri, bukan tercampur di
                                 "Berjalan". Mereka memang masih berjalan, tapi
                                 justru itu masalahnya: tercampur di sana, orang
                                 yang sengaja disisihkan tenggelam dan tak pernah
                                 ditinjau lagi. -->
                            <button type="button" class="plw-tab" :class="{ 'is-hold': statusTab === 'HOLD' }" @click="statusTab = 'HOLD'">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="9" /><path d="M10 9v6M14 9v6" /></svg>
                                Ditahan <span class="plw-tab__badge">{{ jmlHold }}</span>
                            </button>
                        </div>

                        <div class="plw-filters">
                            <el-input v-model="cariKandidat" clearable placeholder="Cari nama / kode" class="plw-filter plw-filter--cari">
                                <template #prefix><i class="bi bi-search"></i></template>
                            </el-input>
                            <el-select v-if="(detail.posisi || []).length" v-model="posisiPilih" filterable clearable placeholder="Semua lowongan" class="plw-filter">
                                <el-option
                                    v-for="p in detail.posisi" :key="p.id" :value="p.id"
                                    :label="`${p.posisi}${p.level ? ' · ' + p.level : ''}`"
                                >
                                    <div class="plw-lopt">
                                        <b>{{ p.posisi }}</b>
                                        <small>{{ [p.departemen, p.lokasi].filter(Boolean).join(' · ') || '—' }} · {{ p.berjalan }} berjalan</small>
                                    </div>
                                </el-option>
                            </el-select>
                            <!-- KEADAAN menjawab "mana yang menunggu SAYA?" —
                                 tanpa ini admin memindai lencana kartu satu per satu. -->
                            <el-select v-model="keadaanPilih" clearable placeholder="Semua keadaan" class="plw-filter">
                                <el-option value="PERLU" label="Perlu keputusan saya" />
                                <el-option value="NUNGGU" label="Menunggu hasil tes" />
                                <el-option value="JADWAL" label="Belum dijadwalkan" />
                                <el-option value="HOLD" label="Sedang ditahan" />
                            </el-select>
                            <button v-if="adaFilter" type="button" class="plw-filter__reset" title="Bersihkan penyaring" @click="bersihkanFilter">
                                <i class="bi bi-x-lg"></i>
                            </button>
                        </div>
                    </div>

                    <div v-if="loadingDetail" class="plw-load" style="padding: 3rem 0"><span class="plw-spin"></span> Memuat papan seleksi…</div>

                    <!-- KANBAN -->
                    <div v-else class="plw-kanban" :class="{ 'is-gugur': statusTab === 'GUGUR' }">
                        <div v-for="(col, i) in kolomTampil" :key="col.kode || col.label" class="plw-col" :class="{ 'is-lawas': col.alurLain }">
                            <div class="plw-col__head">
                                <span class="plw-col__num" :class="{ 'is-hot': kartuKolom(col).length > 0 }">{{ String(i + 1).padStart(2, '0') }}</span>
                                <span class="plw-col__name">
                                    <span class="plw-ell">{{ col.label }}</span>
                                    <svg v-if="col.provider === 'THIRD_PARTY'" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="2" style="flex: 0 0 auto"><rect x="4" y="4" width="16" height="16" rx="2" /><path d="M9 9h6v6H9z" /></svg>
                                    <!-- Kolom ini bukan bagian alur program sekarang: rombongan
                                         yang masih berjalan di alur sebelumnya. Tanpa keterangan,
                                         admin melihat tahap asing di papannya dan mengira alurnya
                                         rusak — lalu ragu mengambil keputusan di sana. -->
                                    <span
                                        v-if="col.alurLain"
                                        class="plw-col__lawas"
                                        :title="col.cadangan
                                            ? 'Tahap ini sudah tidak ada di alur mana pun. Kandidatnya tetap ditampilkan agar tidak terlewat.'
                                            : 'Tahap dari alur sebelumnya. Kandidat di sini melanjutkan alur yang mereka masuki saat melamar.'"
                                    >alur lama</span>
                                </span>
                                <span class="plw-col__count">{{ kartuKolom(col).length }}</span>
                                <!-- Penyaring MILIK KOLOM INI. Satu tahap bisa
                                     berisi 200 orang sementara tahap sesudahnya
                                     berisi 3 — memaksa keduanya ikut satu
                                     penyaring global berarti ikut menyaring
                                     tahap yang tidak perlu disaring. -->
                                <button
                                    v-if="kartuKolom(col).length || kolomTersaring(col)"
                                    type="button" class="plw-col__ico"
                                    :class="{ 'is-on': filterBuka === kunciKolom(col), 'is-aktif': kolomTersaring(col) }"
                                    title="Saring & urutkan kolom ini"
                                    @click="toggleFilterKolom(col)"
                                >
                                    <i class="bi bi-funnel-fill"></i>
                                </button>
                                <!-- Pemicu pilih-banyak milik KOLOM ini. Hanya
                                     muncul di tab Berjalan dan saat kolomnya
                                     memang berisi — menawarkannya pada kolom
                                     kosong hanya ikon mati. -->
                                <button
                                    v-if="statusTab === 'AKTIF' && kartuKolom(col).length"
                                    type="button" class="plw-col__ico" :class="{ 'is-on': kolomPilih === kunciKolom(col) }"
                                    :title="kolomPilih === kunciKolom(col) ? 'Selesai memilih' : 'Pilih banyak di kolom ini (untuk jadwal massal)'"
                                    @click="togglePilihKolom(col)"
                                >
                                    <i class="bi" :class="kolomPilih === kunciKolom(col) ? 'bi-check2-square' : 'bi-ui-checks'"></i>
                                </button>
                            </div>

                            <!-- PANEL PENYARING KOLOM -->
                            <div v-if="filterBuka === kunciKolom(col)" class="plw-colf">
                                <el-input
                                    v-model="filterKol(col).q"
                                    size="small" clearable
                                    placeholder="Cari di kolom ini…"
                                >
                                    <template #prefix><i class="bi bi-search"></i></template>
                                </el-input>
                                <el-select v-model="filterKol(col).keadaan" size="small" clearable placeholder="Semua keadaan">
                                    <el-option value="PERLU" label="Perlu keputusan" />
                                    <el-option value="NUNGGU" label="Menunggu hasil tes" />
                                    <el-option value="JADWAL" label="Belum dijadwalkan" />
                                    <el-option value="TERJADWAL" label="Sudah dijadwalkan" />
                                    <el-option value="HOLD" label="Ditahan" />
                                </el-select>
                                <el-select v-model="filterKol(col).urut" size="small" placeholder="Urutkan">
                                    <el-option value="LAMA" label="Terlama menunggu" />
                                    <el-option value="BARU" label="Terbaru melamar" />
                                    <el-option value="NAMA" label="Nama A–Z" />
                                </el-select>
                                <button v-if="kolomTersaring(col)" type="button" class="plw-colf__reset" @click="bersihkanFilterKolom(col)">
                                    <i class="bi bi-arrow-counterclockwise"></i> Bersihkan kolom ini
                                </button>
                            </div>
                            <!-- PILIH BANYAK ADA DI KOLOM, bukan di atas papan.
                                 Penjadwalan massal selalu menyangkut SATU tahap —
                                 "semua yang di Wawancara HR" — jadi memilihnya
                                 dari sini menghilangkan satu langkah berpikir dan
                                 sekaligus mustahil salah kolom. -->
                            <div v-if="kolomPilih === kunciKolom(col)" class="plw-selbar">
                                <button type="button" class="plw-selbar__all" @click="pilihKolom(col)">
                                    <i class="bi" :class="kolomTerpilihPenuh(col) ? 'bi-check-square-fill' : 'bi-square'"></i>
                                    {{ kolomTerpilihPenuh(col) ? 'Batal semua' : 'Pilih semua' }}
                                </button>
                                <span class="plw-selbar__n">{{ terpilih.length }}</span>
                                <button
                                    type="button" class="plw-selbar__go" :disabled="!aktivitasTerpilih.length"
                                    :title="aktivitasTerpilih.length ? 'Jadwalkan kandidat terpilih sekaligus' : 'Tak ada aktivitas tatap muka yang bisa dijadwalkan'"
                                    @click="askJadwalMassal"
                                >
                                    <i class="bi bi-calendar-plus"></i> Jadwalkan
                                </button>
                                <button type="button" class="plw-selbar__x" title="Selesai memilih" @click="tutupPilihKolom">
                                    <i class="bi bi-x-lg"></i>
                                </button>
                            </div>

                            <div class="plw-col__cards">
                                <div
                                    v-for="r in kartuKolom(col)" :key="r.id"
                                    class="plw-card"
                                    :class="{ 'is-pilih': kolomPilih === kunciKolom(col), 'is-terpilih': terpilih.includes(r.id), 'is-hold': !!r.hold }"
                                    role="button" tabindex="0"
                                    @click="klikKartu(col, r)"
                                    @keyup.enter="klikKartu(col, r)"
                                >
                                    <div class="plw-card__toprow">
                                        <span v-if="kolomPilih === kunciKolom(col)" class="plw-card__cek">
                                            <i class="bi" :class="terpilih.includes(r.id) ? 'bi-check-square-fill' : 'bi-square'"></i>
                                        </span>
                                        <span class="plw-card__avatar" :style="{ background: avatarBg(r) }">{{ inisial(r.pelamar) }}</span>
                                        <span class="plw-card__id">
                                            <span class="plw-card__name">{{ r.pelamar }}</span>
                                            <span class="plw-card__code">{{ r.lamaranKode }}</span>
                                        </span>
                                        <!-- TAHAN / LANJUTKAN — ikon saja di pojok.
                                             Ia jalan keluar yang selalu tersedia,
                                             bukan tindakan utama; tombol berteks di
                                             baris bawah tadi berebut tempat dengan
                                             lencana keadaan dan membuat kartunya
                                             penuh sesak.

                                             TANPA SYARAT apa pun: alasan menahan
                                             justru paling sering muncul sebelum
                                             segala sesuatunya lengkap — kuota belum
                                             turun, user belum menjawab. -->
                                        <button
                                            v-if="kolomPilih !== kunciKolom(col) && r.tahapId && r.statusLamaran === 'BERJALAN'"
                                            type="button" class="plw-card__hold" :class="{ 'is-on': !!r.hold }"
                                            :title="r.hold ? 'Sedang ditahan — klik untuk melanjutkan' : 'Tahan kandidat ini (tanpa email ke kandidat)'"
                                            @click.stop="askHold(!r.hold, r)"
                                        >
                                            <i class="bi" :class="r.hold ? 'bi-play-fill' : 'bi-pause-fill'"></i>
                                        </button>
                                    </div>

                                    <div class="plw-card__pos">{{ r.posisi }}</div>
                                    <!-- Konteks lowongan: SATU baris, dipotong rapi.
                                         Sebelumnya departemen & lokasi dibiarkan
                                         mengalir dan menjebol tepi kartu. -->
                                    <div v-if="r.departemen || r.lokasi" class="plw-card__meta">
                                        <span v-if="r.departemen" :title="r.departemen"><i class="bi bi-diagram-3"></i> {{ r.departemen }}</span>
                                        <span v-if="r.lokasi" :title="r.lokasi"><i class="bi bi-geo-alt"></i> {{ r.lokasi }}</span>
                                    </div>

                                    <div class="plw-card__foot">
                                        <span class="plw-card__chip" :class="'tone-' + r.badge.tone">{{ r.badge.teks }}</span>
                                        <span v-if="r.waktuLamar" class="plw-card__umur" :title="'Melamar ' + tglId(r.waktuLamar)">
                                            <i class="bi bi-clock-history"></i> {{ umurHari(r.waktuLamar) }}
                                        </span>
                                    </div>
                                </div>
                                <div v-if="!kartuKolom(col).length" class="plw-col__empty">—</div>
                            </div>
                        </div>
                    </div>
                </template>
                <div v-else class="plw-empty" style="padding: 4rem 0">Pilih program di panel kiri untuk melihat papan seleksi.</div>
            </div>
        </main>

        <!-- ═══ DRAWER DETAIL KANDIDAT ═══ -->
        <div class="plw-overlay" :class="{ 'is-on': !!detailKandidat }" @click="tutupKandidat"></div>
        <section class="plw-drawer" :class="{ 'is-on': !!detailKandidat }" aria-label="Detail pelamar">
            <template v-if="detailKandidat">
                <div class="plw-drawer__head">
                    <div class="plw-drawer__headrow">
                        <div class="plw-drawer__avatar" :style="{ background: avatarBg(detailKandidat) }">{{ inisial(detailKandidat.pelamar) }}</div>
                        <div style="flex: 1; min-width: 0">
                            <div class="plw-drawer__name">{{ detailKandidat.pelamar }}</div>
                            <div class="plw-drawer__meta">{{ detailKandidat.posisi }} · <span class="plw-mono">{{ detailKandidat.lamaranKode }}</span></div>
                            <!-- Konteks lowongan & kontak: dulu admin harus menebak
                                 atau membuka layar lain untuk tahu ini. -->
                            <div class="plw-drawer__chips">
                                <span v-if="detailKandidat.departemen"><i class="bi bi-diagram-3"></i> {{ detailKandidat.departemen }}</span>
                                <span v-if="detailKandidat.lokasi"><i class="bi bi-geo-alt"></i> {{ detailKandidat.lokasi }}</span>
                                <span v-if="detailKandidat.level"><i class="bi bi-bar-chart-steps"></i> {{ detailKandidat.level }}</span>
                                <span v-if="detailKandidat.mppRef" class="is-mpp">{{ detailKandidat.mppRef }}</span>
                                <span v-if="detailKandidat.waktuLamar"><i class="bi bi-clock-history"></i> {{ tglId(detailKandidat.waktuLamar) }} · {{ umurHari(detailKandidat.waktuLamar) }}</span>
                                <a v-if="detailKandidat.email" :href="`mailto:${detailKandidat.email}`" class="is-link"><i class="bi bi-envelope"></i> {{ detailKandidat.email }}</a>
                                <a v-if="detailKandidat.hp" :href="`https://wa.me/${String(detailKandidat.hp).replace(/\D/g, '')}`" target="_blank" rel="noopener" class="is-link"><i class="bi bi-whatsapp"></i> {{ detailKandidat.hp }}</a>
                            </div>
                        </div>
                        <div style="display: flex; align-items: center; gap: 9px; flex: 0 0 auto">
                            <span class="plw-drawer__tag" :class="detailKandidat.kategori === 'MT' ? 'is-mt' : 'is-rek'">{{ katLabel(detailKandidat.kategori) }}</span>
                            <!-- CETAK LAPORAN — tersedia untuk SETIAP kandidat di
                                 papan, apa pun tahap & statusnya. Laporan paling
                                 sering justru diminta untuk yang sudah selesai
                                 (arsip keputusan), bukan yang sedang berjalan. -->
                            <button type="button" class="plw-drawer__cetak" title="Cetak laporan kandidat (PDF / Excel)" @click="askLaporan">
                                <i class="bi bi-printer-fill"></i> Cetak
                            </button>
                            <button type="button" class="plw-drawer__close" @click="tutupKandidat">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M18 6L6 18M6 6l12 12" /></svg>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="plw-drawer__body">
                    <!-- Info tahap -->
                    <div class="plw-infocard">
                        <div class="plw-infogrid">
                            <div>
                                <div class="plw-klabel">TAHAP</div>
                                <div class="plw-kval">{{ detailKandidat.tahap }}</div>
                            </div>
                            <div>
                                <div class="plw-klabel">POSISI TAHAP</div>
                                <div class="plw-kval">{{ detailKandidat.urutan }} / {{ detailKandidat.totalTahap }}</div>
                            </div>
                            <div>
                                <div class="plw-klabel">STATUS</div>
                                <div class="plw-status" :class="'st-' + detailKandidat.statusLamaran.toLowerCase()">
                                    <span class="plw-status__dot"></span>{{ statusLabel(detailKandidat.statusLamaran) }}
                                </div>
                            </div>
                            <div style="grid-column: 1 / -1">
                                <div class="plw-klabel">PROGRESS ALUR</div>
                                <div class="plw-segs">
                                    <div v-for="n in detailKandidat.totalTahap" :key="n" class="plw-seg" :class="segKelas(n)"></div>
                                </div>
                            </div>
                        </div>
                        <!-- Mode OTOMATIS di Master Alur: admin memang tidak berperan
                             memutus di sini, jadi alasannya dijelaskan, bukan sekadar
                             tombolnya hilang tanpa keterangan. -->
                        <div v-if="detailKandidat.otomatis" class="plw-sysnote">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#d97706" stroke-width="2.2" stroke-linecap="round" style="flex: 0 0 auto; margin-top: 1px"><path d="M5 3h14l-6 8v7l-2 1v-8L5 3z" /></svg>
                            <div>
                                <b>Tahap ini disetel OTOMATIS di Master Alur.</b>
                                Begitu seluruh aktivitas penentu selesai, sistem yang memutuskan: gagal → langsung Tidak Lolos, lulus → langsung maju ke tahap berikutnya. Admin tidak mengetuk palu di sini.
                                <template v-if="detailKandidat.aktivitasBelumTercatat">
                                    Masih menunggu {{ detailKandidat.aktivitasBelumTercatat }} hasil aktivitas.
                                </template>
                            </div>
                        </div>
                        <div v-else-if="detailKandidat.nungguSistem" class="plw-sysnote">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#d97706" stroke-width="2.2" stroke-linecap="round" style="flex: 0 0 auto; margin-top: 1px"><path d="M5 3h14l-6 8v7l-2 1v-8L5 3z" /></svg>
                            <div>
                                <b>Menunggu hasil aktivitas.</b>
                                Catat hasil {{ detailKandidat.aktivitasBelumTercatat || 'tes' }} aktivitas penentu di rapor bawah — keputusan baru bisa diambil setelah itu.
                            </div>
                        </div>
                        <!-- SIAP DIPUTUS — sebutkan apa KATA DATANYA. Admin tetap yang
                             mengetuk palu, tapi ia tak boleh disuruh menebak hasil tes
                             yang sudah dihitung sistem. -->
                        <div v-else-if="detailKandidat.siapDiputus" class="plw-sysnote" :class="detailKandidat.hasilData === 'GAGAL' ? 'is-fail' : 'is-ready'">
                            <svg v-if="detailKandidat.hasilData === 'GAGAL'" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2.2" stroke-linecap="round" style="flex: 0 0 auto; margin-top: 1px"><circle cx="12" cy="12" r="9" /><path d="M15 9l-6 6M9 9l6 6" /></svg>
                            <svg v-else width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#059669" stroke-width="2.2" stroke-linecap="round" style="flex: 0 0 auto; margin-top: 1px"><path d="M20 6L9 17l-5-5" /></svg>
                            <div>
                                <b v-if="detailKandidat.hasilData === 'LULUS'">Hasil tes: LULUS.</b>
                                <b v-else-if="detailKandidat.hasilData === 'GAGAL'">Hasil tes: TIDAK LULUS.</b>
                                <b v-else>Semua hasil sudah masuk.</b>
                                {{ detailKandidat.ringkasHasil || 'Tinjau rapor tes di bawah, lalu putuskan.' }}
                                Keputusan akhir tetap di tangan Anda — tekan Loloskan / Tidak Lolos.
                            </div>
                        </div>
                        <div v-else-if="detailKandidat.alasan" class="plw-sysnote">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#d97706" stroke-width="2.2" stroke-linecap="round" style="flex: 0 0 auto; margin-top: 1px"><circle cx="12" cy="12" r="9" /><path d="M12 8v5" /><path d="M12 16h.01" /></svg>
                            <div><b>Catatan sistem:</b> {{ detailKandidat.alasan }}</div>
                        </div>
                    </div>

                    <!-- RAPOR TES — sub-tes tahap ini (baterai multi-tes). Skor informatif
                         ikut tampil sebagai bahan pertimbangan; ia tak menentukan lulus. -->
                    <div v-if="(detailKandidat.tests || []).length">
                        <div class="plw-secrow">
                            <div class="plw-sectitle">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#6366f1" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3L22 4" /><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11" /></svg>
                                Rapor Tes Tahap Ini
                            </div>
                            <span class="plw-seccount">{{ detailKandidat.tests.length }} tes</span>
                        </div>
                        <div class="plw-tests">
                            <div v-for="t in detailKandidat.tests" :key="t.id" class="plw-test" :class="{ 'is-terkunci': t.terkunci }">
                                <span class="plw-test__ico" :class="t.provider === 'THIRD_PARTY' ? 'is-sys' : 'is-man'">
                                    <i class="bi" :class="t.provider === 'THIRD_PARTY' ? 'bi-robot' : 'bi-person-workspace'"></i>
                                </span>
                                <div class="plw-test__main">
                                    <div class="plw-test__name">
                                        {{ t.label }}
                                        <!-- Aktivitas internal: dicatat tim, tak pernah
                                             terlihat kandidat. Ditandai supaya admin tahu
                                             ia tidak sedang menunggu kandidat berbuat apa pun. -->
                                        <span v-if="t.internal" class="plw-test__tag is-internal" title="Tidak ditampilkan di portal kandidat — hanya dicatat tim">
                                            <i class="bi bi-eye-slash-fill"></i> internal
                                        </span>
                                        <span v-if="t.peran === 'INFORMATIF'" class="plw-test__tag is-info" title="Skor hanya bahan pertimbangan — tidak menentukan lulus">informatif</span>
                                        <span v-if="!t.wajib" class="plw-test__tag">opsional</span>
                                        <!-- Aktivitas inilah yang membuka pilihan jawaban
                                             kandidat begitu hasilnya dicatat. -->
                                        <span v-if="t.penawaran" class="plw-test__tag is-offer" title="Mencatat hasil aktivitas ini menandai penawaran sudah diajukan ke kandidat">penawaran</span>
                                    </div>
                                    <!-- Tipe aktivitas, bukan tipe tahap: satu tahap bisa
                                         berisi ujian online + tes manual + wawancara. -->
                                    <div class="plw-test__sub">
                                        {{ t.tipeNama || '—' }} ·
                                        <template v-if="t.infoSaja">suratnya diunggah di jendela keputusan</template>
                                        <template v-else-if="t.provider === 'THIRD_PARTY'">dijadwalkan di Penjadwalan</template>
                                        <template v-else>dilaksanakan tim</template>
                                    </div>
                                    <!-- Jadwal yang sudah ditetapkan ikut terbaca di
                                         barisnya. Tanpa ini admin harus membuka modal
                                         "Ubah Jadwal" hanya untuk mengingat kapan dan
                                         di mana kandidat diminta datang — padahal itu
                                         justru yang ia perlukan saat menandai hadir. -->
                                    <div v-if="t.jadwal" class="plw-test__jadwalinfo">
                                        <i class="bi" :class="t.jadwal.daring ? 'bi-camera-video-fill' : 'bi-geo-alt-fill'"></i>
                                        <span>
                                            {{ jadwalRingkas(t.jadwal) }}
                                            <template v-if="!t.jadwal.daring && t.jadwal.lokasi"> · {{ t.jadwal.lokasi }}</template>
                                        </span>
                                    </div>
                                    <!-- CATATAN PENILAIAN.
                                         Yang berformat dirender apa adanya lewat
                                         KontenAman (penyaring daftar-izin) —
                                         menampilkannya sebagai teks biasa akan
                                         memuntahkan tag mentah ke layar, dan
                                         gambar lembar penilaiannya hilang.
                                         Ringkasan polos tetap dipakai untuk
                                         catatan lama yang belum berformat. -->
                                    <!-- APA YANG MASIH DITUNGGU dari aktivitas ini.
                                         Alasan yang sama persis dipakai server untuk
                                         menolak keputusan — jadi yang terbaca di sini
                                         tidak akan pernah berselisih dengan yang
                                         ditegakkan di balik layar. -->
                                    <div v-if="!t.tuntas && !t.terkunci" class="plw-test__nunggu">
                                        <i class="bi bi-hourglass-split"></i> {{ t.alasanBelumTuntas }}
                                    </div>

                                    <!-- CATATAN & BERKAS DIJADIKAN SATU PANEL.
                                         Dulu ketiganya (catatan berformat, berkas
                                         kandidat, berkas tim) berdiri sendiri-sendiri
                                         dan selalu terbuka. Pada tahap berisi 4-6 tes
                                         offline, satu layar jadi dinding teks setinggi
                                         beberapa gulungan — dan tombol yang benar-benar
                                         perlu ditekan tenggelam di dalamnya.

                                         Sekarang: satu tombol dengan jumlahnya, dibuka
                                         hanya untuk aktivitas yang sedang ditinjau. -->
                                    <button
                                        v-if="jumlahDetail(t)"
                                        type="button" class="plw-test__more"
                                        :class="{ 'is-on': detailTes === t.id }"
                                        :aria-expanded="detailTes === t.id"
                                        @click.stop="toggleDetail(t)"
                                    >
                                        <i class="bi" :class="detailTes === t.id ? 'bi-chevron-up' : 'bi-chevron-down'"></i>
                                        Catatan &amp; berkas
                                        <span class="plw-test__morecount">{{ jumlahDetail(t) }}</span>
                                    </button>

                                    <!-- PERINGATAN TETAP DI LUAR PANEL.
                                         "Kandidat belum mengunggah padahal wajib" adalah hal
                                         yang harus terlihat SEKILAS, tanpa membuka apa pun.
                                         Menyembunyikannya di balik tombol membuat yang kurang
                                         hanya ketahuan oleh orang yang kebetulan mengklik. -->
                                    <div v-if="t.unggahKandidat && !(t.berkasKandidat || []).length" class="plw-test__kirimkosong" :class="{ 'is-wajib': t.unggahKandidat.wajib }">
                                        <i class="bi" :class="t.unggahKandidat.wajib ? 'bi-exclamation-triangle-fill' : 'bi-hourglass'"></i>
                                        Kandidat belum mengunggah berkas{{ t.unggahKandidat.wajib ? ' (wajib)' : '' }}.
                                    </div>

                                    <!-- PANEL DETAIL — catatan penilaian, berkas kandidat, dan
                                         berkas tim. Ketiganya hanya dirender saat dibuka: pada
                                         tahap berisi enam tes offline, merender semuanya sekaligus
                                         berarti puluhan blok teks berformat yang tak seorang pun
                                         baca serentak. -->
                                    <div v-if="detailTes === t.id" class="plw-test__detail">
                                        <div v-if="t.catatanHtml" class="plw-test__cat is-kaya">
                                            <i class="bi bi-chat-left-text"></i>
                                            <KontenAman :html="t.catatanHtml" />
                                        </div>
                                        <div v-else-if="t.catatan" class="plw-test__cat"><i class="bi bi-chat-left-text"></i> {{ t.catatan }}</div>

                                        <!-- BERKAS DARI KANDIDAT — hasil dari setelan "kandidat
                                             harus mengunggah" di Master Alur. Inilah satu-satunya
                                             layar tempat tim menilainya; berkas yang cuma bisa
                                             dibuka di portal kandidat sama saja tak pernah
                                             diserahkan. -->
                                        <div v-if="(t.berkasKandidat || []).length" class="plw-test__kirim">
                                            <div class="plw-test__kirimhead">
                                                <span class="plw-test__kirimlbl"><i class="bi bi-inbox-fill"></i> Dari kandidat</span>
                                                <!-- SUDAH DINYATAKAN LENGKAP ATAU BELUM. Berkas yang
                                                     masuk belum tentu berkas yang utuh; menilai sebelum
                                                     kandidat menyatakan selesai berarti menilai
                                                     pekerjaan setengah jadi — dan itu tak bisa ditarik.
                                                     Ditaruh di baris kepalanya sendiri supaya tidak lagi
                                                     berdesakan dengan nama-nama berkas. -->
                                                <span
                                                    v-if="t.unggahKandidat"
                                                    class="plw-test__kirimstat"
                                                    :class="t.unggahKandidat.terkirim ? 'is-ok' : 'is-nunggu'"
                                                    :title="t.unggahKandidat.terkirim
                                                        ? `Dinyatakan lengkap oleh kandidat pada ${fmtWaktu(t.unggahKandidat.terkirim)}`
                                                        : 'Kandidat belum menekan Kirim — mungkin masih ada berkas susulan.'"
                                                >
                                                    <i class="bi" :class="t.unggahKandidat.terkirim ? 'bi-patch-check-fill' : 'bi-hourglass-split'"></i>
                                                    {{ t.unggahKandidat.terkirim ? 'dinyatakan lengkap' : 'belum dikirim' }}
                                                </span>
                                            </div>
                                            <div class="plw-test__files">
                                                <button
                                                    v-for="b in t.berkasKandidat" :key="b.id"
                                                    type="button" class="plw-test__kirimfile"
                                                    :title="b.terkirim
                                                        ? `${b.nama} — diserahkan ${fmtWaktu(b.terkirim)}`
                                                        : `${b.nama} — kandidat belum menekan Kirim`"
                                                    @click.stop="bukaDok(b)"
                                                >
                                                    <i class="bi" :class="b.isImage ? 'bi-file-earmark-image-fill' : 'bi-file-earmark-pdf-fill'"></i>
                                                    <span class="plw-test__filenama">{{ b.nama }}</span>
                                                </button>
                                            </div>
                                        </div>

                                        <!-- Lampiran yang diunggah TIM. Dipisah dari yang di atas:
                                             siapa yang menyerahkan menentukan cara membacanya —
                                             bukti dari kandidat diverifikasi, berkas penilai adalah
                                             kesimpulan. -->
                                        <div v-if="(t.berkas || []).length" class="plw-test__kirim is-tim">
                                            <div class="plw-test__kirimhead">
                                                <span class="plw-test__kirimlbl"><i class="bi bi-paperclip"></i> Berkas tim</span>
                                            </div>
                                            <div class="plw-test__files">
                                                <button
                                                    v-for="b in t.berkas" :key="b.id"
                                                    type="button" class="plw-test__kirimfile" :title="b.nama"
                                                    @click.stop="bukaDok(b)"
                                                >
                                                    <i class="bi" :class="b.isImage ? 'bi-file-earmark-image-fill' : 'bi-file-earmark-pdf-fill'"></i>
                                                    <span class="plw-test__filenama">{{ b.nama }}</span>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <span v-if="t.nilai != null" class="plw-test__score">{{ t.nilai }}</span>
                                <span class="plw-test__pill" :class="pillTes(t)">{{ labelTes(t) }}</span>
                                <!-- Tombol aksi turun ke barisnya sendiri: drawer hanya
                                     560px dan bisa memuat tiga tombol sekaligus, sehingga
                                     memaksanya sebaris dengan nama aktivitas membuat
                                     labelnya terpotong per kata. -->
                                <div class="plw-test__aksi">
                                <!-- TERKUNCI URUTAN — tahapnya dikerjakan per langkah dan
                                     giliran aktivitas ini belum tiba. Tombolnya tidak
                                     ditampilkan sama sekali (server pun menolaknya), dan
                                     alasannya disebut supaya tidak terbaca sebagai layar
                                     yang rusak. -->
                                <span v-if="t.terkunci" class="plw-test__gembok">
                                    <i class="bi bi-lock-fill"></i>
                                    Menunggu <b>{{ t.menunggu }}</b> selesai
                                </span>
                                <!-- "Catat Hasil" HANYA untuk aktivitas yang dikerjakan tim
                                     (wawancara, tes offline, FGD) pada tahap multi-aktivitas.
                                     Ujian online tidak punya tombol ini: nilainya datang
                                     sendiri dari HCLearn, dan mengisinya manual justru
                                     menimpa angka resmi. Server yang menentukan lewat
                                     dapatDicatat — lihat LamaranController::rapotTes. -->
                                <!-- ATUR JADWAL — muncul untuk tipe yang menuntut waktu &
                                     tempat (wawancara, MCU, tes offline). Penandanya dari
                                     Master Tipe Tahap (Flag_Jadwal), bukan daftar kode di
                                     dalam kode program, jadi tipe baru cukup ditambahkan
                                     lewat master tanpa perlu deploy. -->
                                <!-- KEHADIRAN: gerbang sebelum hasil boleh dicatat.
                                     Tim tidak bisa melampirkan hasil MCU untuk orang
                                     yang tidak datang, jadi ini ditetapkan lebih dulu.
                                     Keduanya membuka modal konfirmasi dengan catatan
                                     OPSIONAL — lihat askHadir(). -->
                                <!-- MENAHAN AKTIVITAS BERIKUTNYA.
                                     Tombolnya ada di aktivitas yang MENAHAN,
                                     bukan di yang tertahan — di sana admin tak
                                     bisa berbuat apa-apa, dan menaruhnya di situ
                                     membuat ia mengklik hal yang salah. -->
                                <button
                                    v-if="t.perluLanjut"
                                    type="button" class="plw-test__lanjut" :disabled="lanjutId === t.id"
                                    title="Buka aktivitas berikutnya untuk kandidat ini"
                                    @click="lanjutkanAktivitas(t)"
                                >
                                    <i class="bi" :class="lanjutId === t.id ? 'bi-arrow-repeat plw-spin' : 'bi-arrow-right-circle-fill'"></i>
                                    {{ lanjutId === t.id ? 'Membuka…' : 'Lanjutkan' }}
                                </button>

                                <template v-if="t.butuhKehadiran && !t.terkunci">
                                    <button type="button" class="plw-test__hdr is-ya" title="Kandidat datang — hasil aktivitas ini lalu bisa dicatat & berkasnya diunggah" @click="askHadir(t, 'Y')">
                                        <i class="bi bi-person-check-fill"></i> Hadir
                                    </button>
                                    <button type="button" class="plw-test__hdr is-tidak" title="Kandidat tidak datang — aktivitas ditutup dan tahapnya dievaluasi ulang" @click="askHadir(t, 'T')">
                                        <i class="bi bi-person-dash-fill"></i> Tidak Hadir
                                    </button>
                                </template>
                                <!-- Kehadiran yang SUDAH ditetapkan tetap terbaca —
                                     itulah dasar tombol keputusan boleh ditekan. -->
                                <span v-else-if="t.hadir === 'Y'" class="plw-test__hdrtag">
                                    <i class="bi bi-person-check-fill"></i> Hadir
                                </span>
                                <span v-else-if="t.hadir === 'T'" class="plw-test__hdrtag is-no">
                                    <i class="bi bi-person-dash-fill"></i> Tidak hadir
                                </span>

                                <button
                                    v-if="t.butuhJadwal"
                                    type="button" class="plw-test__jdw"
                                    :class="{ 'is-set': t.jadwal }"
                                    :title="t.jadwal ? 'Ubah jadwal — undangan dikirim ulang' : 'Tetapkan waktu & tempat, kandidat diundang lewat email'"
                                    @click="askJadwal(t)"
                                >
                                    <i class="bi" :class="t.jadwal ? 'bi-calendar-check-fill' : 'bi-calendar-plus'"></i>
                                    {{ t.jadwal ? 'Ubah Jadwal' : 'Atur Jadwal' }}
                                </button>
                                <button
                                    v-if="bisaCatat(t) && bisaCatatKehadiran(t)"
                                    type="button" class="plw-test__rec"
                                    title="Rekam hasil aktivitas ini — mesin langsung mengevaluasi tahap"
                                    @click="askCatat(t)"
                                >
                                    <i class="bi bi-pencil-square"></i> Catat Hasil
                                </button>
                                <!-- SINKRON — jaring pengaman ujian online. Nilainya ditarik
                                     dari HCLearn, bukan diketik admin, jadi yang tersimpan
                                     tetap angka resmi penyedia. -->
                                <button
                                    v-if="t.dapatSinkron"
                                    type="button" class="plw-test__sync" :disabled="sinkronId === t.id"
                                    title="Tarik hasil ujian ini langsung dari HCLearn. Dipakai bila hasilnya belum masuk sendiri."
                                    @click="sinkronHasil(t)"
                                >
                                    <i class="bi" :class="sinkronId === t.id ? 'bi-arrow-repeat plw-spin' : 'bi-cloud-download'"></i>
                                    {{ sinkronId === t.id ? 'Menarik…' : 'Sinkronkan' }}
                                </button>
                                <button
                                    v-if="bisaTidakHadir(t)"
                                    type="button" class="plw-test__skip"
                                    :title="t.online
                                        ? 'Kandidat tidak mengerjakan ujian ini. Tahap berhenti menunggu hasilnya dari HCLearn.'
                                        : 'Tandai kandidat tidak hadir pada aktivitas ini (tahap tidak lagi menunggu hasilnya)'"
                                    @click="askTidakHadir(t)"
                                >
                                    <i class="bi bi-person-x"></i> Tidak hadir
                                </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Berkas & Biodata -->
                    <div>
                        <div class="plw-secrow">
                            <div class="plw-sectitle">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#6366f1" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7a2 2 0 0 1 2-2h4l2 2h6a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H4z" /></svg>
                                Berkas &amp; Biodata
                            </div>
                            <span class="plw-seccount">{{ profil.formulir.length }} formulir</span>
                        </div>

                        <div v-if="loadingProfil" class="plw-load" style="padding: 1.5rem 0"><span class="plw-spin"></span> Memuat berkas…</div>
                        <div v-else-if="!profil.formulir.length" class="plw-empty" style="padding: 1.5rem 0">Belum ada formulir terisi.</div>

                        <div v-else class="plw-forms">
                            <div v-for="(f, i) in profil.formulir" :key="f.no" class="plw-form" :class="{ 'is-open': openForm === i }">
                                <button type="button" class="plw-form__head" @click="openForm = openForm === i ? -1 : i">
                                    <span class="plw-form__step" :class="{ 'is-ok': !!f.waktuKirim }">{{ String(i + 1).padStart(2, '0') }}</span>
                                    <span style="flex: 1; min-width: 0">
                                        <span class="plw-form__title">{{ f.label }}</span>
                                        <span class="plw-form__sub">Tahap {{ f.urutan || '—' }} · {{ f.sumber === 'PENDAFTARAN' ? 'Pendaftaran' : 'Tahap seleksi' }}</span>
                                    </span>
                                    <span class="plw-form__pill" :class="f.waktuKirim ? 'is-ok' : 'is-wait'">{{ f.waktuKirim ? 'Lengkap' : 'Menunggu' }}</span>
                                    <svg class="plw-form__chev" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="2.4" stroke-linecap="round"><path d="M6 9l6 6 6-6" /></svg>
                                </button>
                                <div v-if="openForm === i" class="plw-form__body">
                                    <div v-if="f.jawaban.length" class="plw-fields">
                                        <div
                                            v-for="j in f.jawaban" :key="j.key"
                                            class="plw-field" :class="{ 'is-panjang': isianPanjang(j) }"
                                        >
                                            <div class="plw-field__k">{{ labelIsian(f, j) }}</div>
                                            <!-- ISIAN BERUPA BERKAS = LENCANA, bukan nama file
                                                 + tombol Lihat.
                                                 Nama berkasnya panjang dan tak berarti bagi
                                                 peninjau ("CONTOH_TTD_DI…"), dan tombolnya
                                                 menggandakan sesuatu yang sudah ada di
                                                 "Dokumen & Verifikasi" tepat di bawah — lengkap
                                                 dengan ukuran, status verifikasi, dan pratinjau.
                                                 Di sini yang benar-benar perlu dijawab cuma satu:
                                                 dokumennya ADA atau TIDAK. -->
                                            <div v-if="isianBerkas(j)" class="plw-field__v">
                                                <span class="plw-badge" :class="j.berkas ? 'is-ada' : 'is-kosong'">
                                                    <i class="bi" :class="j.berkas ? 'bi-check-circle-fill' : 'bi-dash-circle'"></i>
                                                    {{ j.berkas ? 'Terlampir' : 'Belum ada' }}
                                                </span>
                                            </div>
                                            <!-- ISIAN BERULANG (riwayat kerja, organisasi,
                                                 sertifikasi) — ditampilkan sebagai DAFTAR,
                                                 sama seperti di portal kandidat. Peninjau
                                                 membandingkan keduanya, jadi bentuknya tidak
                                                 boleh berbeda. -->
                                            <div v-else-if="j.baris && j.baris.length" class="plw-field__v plw-rows">
                                                <div v-for="(row, ri) in j.baris" :key="ri" class="plw-row">
                                                    <span v-if="j.baris.length > 1" class="plw-row__no">{{ ri + 1 }}</span>
                                                    <div class="plw-row__isi">
                                                        <span v-for="(p, pi) in row" :key="pi" class="plw-row__p">
                                                            <b v-if="p.label">{{ p.label }}</b>{{ p.nilai }}
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div v-else class="plw-field__v">{{ j.nilai || '—' }}</div>
                                        </div>
                                    </div>
                                    <div v-if="f.berkas.length" class="plw-docs">
                                        <div class="plw-docs__label">DOKUMEN &amp; VERIFIKASI</div>
                                        <div v-for="b in f.berkas" :key="b.field" class="plw-doc">
                                            <span class="plw-doc__ico" :class="b.isImage ? 'is-img' : 'is-pdf'">
                                                <svg v-if="b.isImage" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#6366f1" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" /><circle cx="8.5" cy="8.5" r="1.5" /><path d="M21 15l-5-5L5 21" /></svg>
                                                <svg v-else width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" /><path d="M14 2v6h6" /></svg>
                                            </span>
                                            <div style="flex: 1; min-width: 0">
                                                <div class="plw-doc__toprow">
                                                    <span class="plw-doc__name">{{ b.nama }}</span>
                                                    <span class="plw-doc__ext">{{ (b.ext || '').toUpperCase() }}</span>
                                                </div>
                                                <div class="plw-doc__desc">{{ b.field === 'foto_verifikasi' ? 'Foto verifikasi identitas kandidat.' : 'Berkas ' + b.field.replace(/[_-]/g, ' ') + ' · ' + ukuran(b.ukuran) }}</div>
                                            </div>
                                            <button type="button" class="plw-doc__eye" title="Lihat berkas" @click="bukaDok(b)">
                                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z" /><circle cx="12" cy="12" r="3" /></svg>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- FOOTER AKSI — menempel di dasar drawer, tidak ikut menggulung.
                     Keputusan adalah alasan drawer ini dibuka; kalau tombolnya ikut
                     hanyut ke bawah, admin harus menggulung dulu setiap kali. Indikator
                     kuota ikut pindah supaya alasan tombol Loloskan hilang/mati tetap
                     terbaca di sebelah tombolnya. -->
                <div v-if="detailKandidat.butuhKeputusan" class="plw-foot">
                    <!-- JAWABAN KANDIDAT atas penawaran. Ditaruh di atas tombol
                         karena inilah yang menentukan tombol mana yang benar:
                         diamnya kandidat dan persetujuannya menuntut tindakan
                         yang sama sekali berbeda, dan admin tak boleh menebaknya. -->
                    <div v-if="detailKandidat.tanggapan" class="plw-jawab" :class="detailKandidat.tanggapan.jawab === 'TERIMA' ? 'is-ya' : 'is-no'">
                        <i class="bi" :class="detailKandidat.tanggapan.jawab === 'TERIMA' ? 'bi-hand-thumbs-up-fill' : 'bi-box-arrow-left'"></i>
                        <div style="min-width: 0">
                            <b>Kandidat {{ detailKandidat.tanggapan.jawab === 'TERIMA' ? 'MENERIMA' : 'MUNDUR' }}</b>
                            <span v-if="detailKandidat.tanggapan.waktu"> · {{ tglId(detailKandidat.tanggapan.waktu) }}</span>
                            <p v-if="detailKandidat.tanggapan.catatan">“{{ detailKandidat.tanggapan.catatan }}”</p>
                        </div>
                    </div>
                    <!-- Penawarannya sendiri BELUM diajukan. Ini keadaan paling
                         awal tahap ini, dan yang menahan segalanya: kandidat tidak
                         punya apa pun untuk dijawab, jadi pilihan jawabannya pun
                         belum ditawarkan kepada siapa-siapa. -->
                    <div v-else-if="penawaranBelumDiajukan" class="plw-jawab is-wait">
                        <i class="bi bi-send-plus"></i>
                        <div style="min-width: 0">
                            <b>Penawaran belum sampai ke kandidat.</b>
                            <p>
                                <template v-if="aktivitasPenawaran && aktivitasPenawaran.butuhJadwal">
                                    Atur jadwal <b>{{ aktivitasPenawaran.label }}</b> di rapor tes di atas —
                                    undangannya langsung terkirim ke kandidat.
                                </template>
                                <template v-else>
                                    Unggah surat penawarannya lewat tombol keputusan di bawah.
                                </template>
                                Setelah itu kandidat bisa menjawab di portalnya, dan pilihan
                                “Kandidat Menolak / Mundur” terbuka di sini.
                            </p>
                        </div>
                    </div>
                    <!-- Peringatan "Kandidat belum menjawab penawaran — ia bisa
                         menekan Terima / Mundur di portalnya" DIHAPUS.
                         Tombol itu sudah dicabut dari portal; kalimatnya
                         mengarahkan tim ke sesuatu yang tidak ada. Jawabannya
                         kini dicatat tim sendiri di modal "Tandai Hadir" pada
                         aktivitas negosiasi. -->

                    <!-- DITAHAN — mendahului semua keterangan lain. Selama hold
                         berlaku, seluruh tombol keputusan terkunci: melepasnya
                         adalah satu klik, dan klik itulah yang memaksa admin
                         sadar ada alasan kenapa kandidat ini sengaja belum
                         diputus. Tanpa gerbang ini, hold cuma hiasan. -->
                    <div v-if="detailKandidat.hold" class="plw-hold">
                        <div class="plw-hold__top">
                            <span class="plw-hold__ico"><i class="bi bi-pause-circle-fill"></i></span>
                            <div style="min-width: 0; flex: 1">
                                <b>Ditahan{{ detailKandidat.hold.alasanNama ? ' — ' + detailKandidat.hold.alasanNama : '' }}</b>
                                <small>
                                    Oleh {{ detailKandidat.hold.olehSiapa || '—' }}
                                    <template v-if="detailKandidat.hold.sejak"> · sejak {{ tglId(detailKandidat.hold.sejak) }}</template>
                                    · kandidat <b>tidak</b> dikirimi pemberitahuan apa pun.
                                </small>
                            </div>
                            <button type="button" class="plw-hold__lepas" @click="askHold(false)">
                                <i class="bi bi-play-fill"></i> Lanjutkan
                            </button>
                        </div>
                        <KontenAman
                            v-if="detailKandidat.hold.catatanHtml"
                            :html="detailKandidat.hold.catatanHtml"
                            ringkas
                            class="plw-hold__cat"
                        />
                        <p v-else-if="detailKandidat.hold.catatan" class="plw-hold__cat">{{ detailKandidat.hold.catatan }}</p>
                    </div>

                    <!-- KEHADIRAN DULU, baru keputusan. Selama masih ada aktivitas
                         berjadwal yang kehadirannya belum ditetapkan, meloloskan
                         berarti memutuskan tanpa tahu kandidatnya datang atau tidak.
                         Alasannya ditulis di sini — tombol mati tanpa keterangan
                         membuat admin mengira layarnya rusak. -->
                    <!-- APA YANG MASIH KURANG — disebut satu per satu, bukan
                         "tidak bisa diloloskan" yang memindahkan tebakan ke
                         orang berikutnya. Hanya tombol yang MEMAJUKAN yang
                         terkunci; menutup lamaran tetap selalu bisa. -->
                    <div v-else-if="belumTuntas.length" class="plw-kuota is-hadir">
                        <i class="bi bi-hourglass-split"></i>
                        <div class="plw-kuota__isi">
                            <b>Tahap ini belum tuntas</b>
                            <ul class="plw-kuota__list">
                                <li v-for="(x, i) in belumTuntas" :key="i"><b>{{ x.label }}</b> — {{ x.sebab }}</li>
                            </ul>
                            <span>Selama itu, <b>Loloskan</b> dan <b>Talent Pool</b> terkunci. <b>Tidak Lolos</b>, <b>Tahan Dulu</b>, dan keputusan dari kandidat tetap bisa dipakai.</span>
                        </div>
                    </div>

                    <!-- Indikator kuota (muncul saat kandidat di tahap akhir & posisi berkuota) -->
                    <div v-else-if="detailKandidat.diTahapAkhir && detailKandidat.kuota > 0" class="plw-kuota" :class="{ 'is-penuh': detailKandidat.kuotaPenuh }">
                        <i class="bi" :class="detailKandidat.kuotaPenuh ? 'bi-lock-fill' : 'bi-people-fill'"></i>
                        <span v-if="detailKandidat.kuotaPenuh">Kuota penuh ({{ detailKandidat.terisiKuota }}/{{ detailKandidat.kuota }}) — Loloskan dinonaktifkan. Gunakan Talent Pool / Tidak Lolos.</span>
                        <span v-else>Sisa <b>{{ detailKandidat.sisaKuota }}</b> kursi dari {{ detailKandidat.kuota }} (terisi {{ detailKandidat.terisiKuota }}).</span>
                    </div>

                    <!-- KEPUTUSAN PERUSAHAAN — tombolnya DARI MASTER Hasil
                         Keputusan, bukan tiga tombol yang ditulis mati di sini.
                         Semua menunggu kehadiran ditetapkan lebih dulu: menggugurkan
                         atau menyimpan ke Talent Pool orang yang ternyata datang
                         sama kelirunya dengan meloloskan orang yang tidak datang.

                         Tombol ini HANYA MEMBUKA modal konfirmasi; syarat berkas
                         ditahan di tombol konfirmasi di dalamnya — satu-satunya
                         tempat mengunggah berkas justru ada di modal itu. -->
                    <div class="plw-actions" :class="{ 'is-three': putusanPerusahaan.length > 2 }">
                        <button
                            v-for="h in putusanPerusahaan" :key="h.kode"
                            type="button" class="plw-btn-putus" :class="kelasPutus(h)"
                            :disabled="!!terkunciPutus(h)"
                            :title="terkunciPutus(h) || h.deskripsi"
                            @click="askPutus(detailKandidat, h.kode)"
                        >
                            <i class="bi" :class="h.ikon"></i>
                            {{ labelPutus(h) }}
                        </button>
                    </div>

                    <!-- TAHAN — keadaan ketiga di samping "putuskan sekarang" dan
                         "biarkan menggantung". Yang kedua yang selama ini selalu
                         dipakai, dan akibatnya tak ada yang bisa membedakan
                         kandidat yang sedang ditunggu dari yang terlupakan. -->
                    <!-- TANPA SYARAT. Alasan menahan justru paling sering muncul
                         sebelum segala sesuatunya lengkap — kuota belum turun,
                         user department belum menjawab. Menuntut kehadiran atau
                         hasil tes lebih dulu berarti menutup jalan keluar tepat
                         saat ia paling dibutuhkan. -->
                    <button
                        v-if="!detailKandidat.hold"
                        type="button" class="plw-btn-hold"
                        title="Tunda keputusan tanpa memindahkan kandidat. Tidak ada email atau WA yang dikirim."
                        @click="askHold(true)"
                    >
                        <i class="bi bi-pause-circle"></i> Tahan Dulu (Hold)
                    </button>

                    <!-- KEPUTUSAN DARI KANDIDAT — dipisah, bukan disamakan dengan
                         penilaian tim. Kandidat yang menolak penawaran atau mundur
                         BUKAN kandidat yang gagal seleksi; mencatatnya sebagai
                         "Tidak Lolos" membuat laporan berbunyi "gagal di tahap
                         penawaran" untuk orang yang justru lolos lalu memilih pergi,
                         dan itu menuntun ke perbaikan yang salah sasaran. -->
                    <div v-if="putusanKandidat.length" class="plw-actions2">
                        <div class="plw-actions2__lbl">
                            <i class="bi bi-person-lines-fill"></i> KEPUTUSAN DARI KANDIDAT
                            <small v-if="detailKandidat.tanggapan">— sudah menjawab lewat portal</small>
                        </div>
                        <div class="plw-actions2__row">
                            <button
                                v-for="h in putusanKandidat" :key="h.kode"
                                type="button" class="plw-btn-kandidat"
                                :disabled="!!terkunciPutus(h)"
                                :title="terkunciPutus(h) || h.deskripsi"
                                @click="askPutus(detailKandidat, h.kode)"
                            >
                                <i class="bi" :class="h.ikon"></i>
                                {{ h.labelTombol }}
                            </button>
                        </div>
                    </div>
                </div>
            </template>
        </section>

        <!-- ═══ LIGHTBOX BERKAS GAMBAR ═══ -->
        <div class="plw-lb" :class="{ 'is-on': !!lightbox }" @click="lightbox = null">
            <div v-if="lightbox" :class="['plw-lb__wrap', { 'is-pdf': lightbox.pdf }]" @click.stop>
                <div class="plw-lb__bar">
                    <div class="plw-lb__id">
                        <span class="plw-lb__ico">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" /><circle cx="8.5" cy="8.5" r="1.5" /><path d="M21 15l-5-5L5 21" /></svg>
                        </span>
                        <div style="min-width: 0">
                            <div class="plw-lb__name">{{ lightbox.nama }}</div>
                            <div class="plw-lb__desc">{{ lightbox.field === 'foto_verifikasi' ? 'Foto verifikasi identitas kandidat' : 'Pratinjau berkas kandidat' }}</div>
                        </div>
                    </div>
                    <button type="button" class="plw-lb__close" @click="lightbox = null">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M18 6L6 18M6 6l12 12" /></svg>
                    </button>
                </div>
                <div class="plw-lb__card">
                    <div v-if="lbLoading" class="plw-lb__state">
                        <span class="plw-spin plw-spin--lg"></span>
                        <span>Memuat berkas…</span>
                    </div>
                    <div v-else-if="lbError" class="plw-lb__state">
                        <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="#f87171" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9" /><path d="M12 8v5" /><path d="M12 16h.01" /></svg>
                        <span>Gagal memuat berkas.</span>
                        <button type="button" class="plw-lb__retry" @click="lbCoba">Coba lagi</button>
                    </div>
                    <!-- Satu modal melayani gambar dan PDF, supaya tidak ada dua
                         gaya pratinjau untuk hal yang sama. -->
                    <iframe
                        v-if="lightbox.pdf"
                        v-show="!lbLoading && !lbError"
                        :src="lbSrc"
                        :title="lightbox.nama"
                        class="plw-lb__pdf"
                        @load="selesaiMuat()"
                    ></iframe>
                    <img v-else v-show="!lbLoading && !lbError" :src="lbSrc" :alt="lightbox.nama" @load="selesaiMuat()" @error="selesaiMuat(true)" />
                    <div class="plw-lb__foot">
                        <div style="font-size: 12px; color: #8b93a7" class="plw-ell">{{ lightbox.nama }}</div>
                        <div v-if="lightbox.status === 'TERVERIFIKASI'" class="plw-lb__ok">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M20 6L9 17l-5-5" /></svg>
                            Terverifikasi
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <ConfirmModal
            :show="konfirmShow"
            :title="putusJudul"
            :subtitle="putusTarget ? `${putusTarget.pelamar} — tahap ${putusTarget.tahap}` : ''"
            :danger="!!putusDef && !putusDef.lolos"
            :confirm-label="putusLabelKonfirm"
            :busy="sibuk"
            :confirm-disabled="!bolehKonfirmPutus"
            form-mode
            @confirm="konfirmPutus"
            @cancel="konfirmShow = false"
        >
            <!-- Ringkas apa yang akan terjadi. Keputusan ini mengirim email ke
                 kandidat dan tidak bisa ditarik kembali, jadi disebutkan di muka
                 alih-alih membiarkan admin menebak. -->
            <div class="plw-putus__ring" :class="nadaPutus">
                <div v-if="putusTarget" class="plw-putus__row">
                    <i class="bi bi-person-badge"></i>
                    <span><b>{{ putusTarget.pelamar }}</b> · {{ putusTarget.posisi || '—' }}<template v-if="putusTarget.departemen"> · {{ putusTarget.departemen }}</template></span>
                </div>
                <!-- Akibat keputusan DIBACA DARI MASTER, bukan tiga kalimat yang
                     ditulis mati. Menambah hasil baru di master berarti tombolnya
                     langsung muncul BESERTA keterangan akibatnya. -->
                <div v-if="putusDef" class="plw-putus__row">
                    <i class="bi" :class="putusDef.ikon"></i>
                    <span>{{ putusDef.deskripsi }}</span>
                </div>
                <!-- Talent Pool yang MELEKAT pada arti hasilnya (mis. tombol
                     "Talent Pool" itu sendiri) hanya diberitakan. Yang bisa
                     dipilih tampil sebagai pertanyaan tersendiri di bawah —
                     menyatakannya di sini sebagai fakta akan berbohong. -->
                <div v-if="putusDef && putusDef.talentPool && !putusDef.pilihTalentPool" class="plw-putus__row">
                    <i class="bi bi-stars"></i>
                    <span>Datanya <b>disimpan di Talent Pool</b> untuk kesempatan berikutnya.</span>
                </div>
                <div v-if="putusTarget && putusTarget.email" class="plw-putus__row is-mail">
                    <i class="bi bi-envelope"></i>
                    <span>{{ putusDef && putusDef.kirimEmail ? putusTarget.email : 'Tidak dikirimi email' }}</span>
                </div>
            </div>

            <!-- Jawaban yang SUDAH diberikan kandidat lewat portal. Admin yang
                 mencatatkan keputusan dari kandidat perlu melihatnya di sini —
                 kalau tidak, ia mencatat ulang sesuatu yang sudah tercatat. -->
            <div v-if="putusTarget && putusTarget.tanggapan" class="plw-note is-info">
                <i class="bi bi-chat-left-quote-fill"></i>
                <span>
                    Kandidat sudah menjawab lewat portal:
                    <b>{{ putusTarget.tanggapan.jawab === 'TERIMA' ? 'menerima' : 'mundur' }}</b>
                    <template v-if="putusTarget.tanggapan.catatan"> — “{{ putusTarget.tanggapan.catatan }}”</template>
                </span>
            </div>

            <!-- HASIL MCU TIDAK LAGI DI SINI.
                 Status kesehatan dicatat saat KEHADIRAN ditetapkan — satu
                 peristiwa: kandidat datang ke klinik, diperiksa, inilah
                 hasilnya. Menaruhnya di jendela ini berarti petugas yang
                 menerima hasil dari klinik tidak punya tempat mencatatnya, dan
                 orang yang menekan "Loloskan" disodori formulir medis yang
                 bukan urusannya. Server pun tak lagi menerimanya di sini.
                 Lihat modal "Tandai Hadir". -->

            <!-- KANDIDAT MUNDUR — DISIMPAN ATAU TIDAK?
                 Dua pilihan, bukan satu nasib yang dipaksakan. Sebab mundurnya
                 bermacam-macam: yang pergi karena dapat tempat lebih dekat rumah
                 memang layak disimpan, yang menghilang tanpa membalas undangan
                 tidak. Dulu keduanya pasti masuk Talent Pool, sehingga admin yang
                 tidak ingin menyimpan terpaksa mencatatnya "Tidak Lolos" —
                 memperbaiki isi Talent Pool dengan merusak arti datanya, karena
                 di laporan lalu terbaca PERUSAHAAN yang menolak orang yang
                 sebenarnya pergi sendiri.

                 Apa pun yang dipilih, HASILNYA TETAP SATU: "Mengundurkan Diri".
                 Pilihan ini tidak mengubah status kandidat, hanya menentukan
                 datanya disimpan untuk kesempatan berikutnya atau tidak. -->
            <!-- Tahap SEBELUM cut-off: tidak ada pilihan sama sekali. Kandidat
                 dicatat mundur, titik. Menawarkan Talent Pool di tahap awal —
                 sebelum ada satu pun penilaian — hanya mengisinya dengan orang
                 yang belum pernah dinilai siapa pun. -->
            <p v-if="putusDef && putusDef.pilihTalentPool && !putusTarget?.bolehTalentPool" class="plw-note is-info">
                <i class="bi bi-info-circle-fill"></i>
                <span>
                    Tahap ini <b>belum masuk cut-off Talent Pool</b> pada alurnya, jadi kandidat
                    tidak disimpan — cukup dicatat <b>{{ putusDef.nama }}</b>.
                </span>
            </p>

            <div v-if="putusDef && putusDef.pilihTalentPool && putusTarget?.bolehTalentPool" class="plw-tp">
                <div class="plw-tp__head">
                    <i class="bi bi-signpost-split-fill"></i>
                    <span>{{ putusDef.labelTalentPool || 'Simpan kandidat ini di Talent Pool' }}</span>
                </div>
                <div class="plw-tp__opts">
                    <button
                        type="button" class="plw-tp__opt is-simpan" :class="{ 'is-on': putusTalentPool }"
                        @click="putusTalentPool = true"
                    >
                        <span class="plw-tp__ico"><i class="bi bi-stars"></i></span>
                        <span class="plw-tp__txt">
                            <b>Ya, simpan di Talent Pool</b>
                            <small>Kualitasnya sudah terbukti sampai tahap ini — datanya siap ditarik untuk lowongan lain.</small>
                        </span>
                    </button>
                    <button
                        type="button" class="plw-tp__opt is-lepas" :class="{ 'is-on': !putusTalentPool }"
                        @click="putusTalentPool = false"
                    >
                        <span class="plw-tp__ico"><i class="bi bi-box-arrow-left"></i></span>
                        <span class="plw-tp__txt">
                            <b>Tidak, cukup catat pengunduran dirinya</b>
                            <small>Lamaran ditutup sebagai <b>{{ putusDef.nama }}</b> — <b>bukan</b> tidak lolos — tanpa masuk Talent Pool.</small>
                        </span>
                    </button>
                </div>
            </div>

            <!-- TANGGAL KANDIDAT MENYATAKAN MUNDUR/MENOLAK.
                 Hanya muncul untuk keputusan yang datang dari kandidat. Kabar
                 mundur biasanya lewat telepon lebih dulu dan baru dicatat
                 beberapa hari kemudian; tanpa tanggal ini kursi tampak baru
                 kosong hari pencatatan, padahal sudah kosong sejak awal. -->
            <div v-if="putusDef && putusDef.olehKandidat" class="plw-fld">
                <label class="plw-fld__lbl" for="putus-tgl">
                    Tanggal Konfirmasi Pengunduran Diri
                    <small>kapan kandidat menyatakannya</small>
                </label>
                <input
                    id="putus-tgl"
                    v-model="putusTanggal"
                    type="date"
                    class="plw-inp"
                    :max="hariIni"
                />
            </div>

            <!-- Wajib atau tidaknya alasan DARI MASTER (Butuh_Alasan): keputusan
                 yang menutup proses menuntut jejak kenapa, dan keputusan dari
                 kandidat menuntutnya juga — sebab mundurnya adalah satu-satunya
                 umpan balik kenapa penawaran kita kalah. -->
            <div class="plw-fld">
                <label class="plw-fld__lbl">
                    {{ labelCatatanPutus }}
                    <b v-if="alasanWajib">*</b>
                    <small v-else>opsional</small>
                </label>
                <!-- Editor berformat, bukan kotak sebaris. Alasan keputusan yang
                     menutup lamaran orang layak ditulis selengkap catatan
                     wawancara — dan kerap perlu memuat bukti (tangkapan layar
                     percakapan kandidat yang menyatakan mundur). -->
                <EditorQuill
                    v-model="putusCatatanHtml"
                    ringkas
                    :upload-url="URL_GAMBAR"
                    :upload-data="{ tahapId: putusTarget?.tahapId }"
                    :placeholder="placeholderCatatanPutus"
                    hint="Bisa diberi format & gambar. Tersimpan sebagai catatan keputusan tahap ini."
                />
            </div>
            <p v-if="alasanWajib && !catatanCukup" class="plw-note is-err">
                <i class="bi bi-exclamation-circle-fill"></i>
                <span>{{ labelCatatanPutus }} wajib diisi, minimal {{ MIN_ALASAN }} karakter (sekarang {{ panjangAlasanPutus }}).</span>
            </p>

            <!-- AKTIVITAS TAHAP INI BELUM SELESAI SEMUA.
                 Aktivitas yang dikerjakan tim (wawancara, FGD, DISC) tidak
                 mengunci tombol keputusan — mengunci akan membuat tahap yang
                 aktivitasnya sengaja dilewati jadi buntu. Tapi memutus tanpa
                 sadar bahwa tiga dari empat aktivitas belum dikerjakan adalah
                 kekeliruan yang tak bisa ditarik kembali.
                 Karena itu bukan dikunci, melainkan DIMINTA DIAKUI dulu. -->
            <div v-if="perluCentangAktivitas" class="plw-belum">
                <div class="plw-belum__head">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <span>
                        <b>{{ aktivitasBelumSelesai.length }} dari {{ (putusTarget?.tests || []).length }} aktivitas</b>
                        di tahap ini belum selesai
                    </span>
                </div>
                <ul class="plw-belum__list">
                    <li v-for="a in aktivitasBelumSelesai" :key="a.id">
                        {{ a.label }}
                        <em v-if="a.jadwal">sudah dijadwalkan, hasilnya belum dicatat</em>
                        <em v-else>belum dijadwalkan</em>
                    </li>
                </ul>
                <label class="plw-putus__cek is-warn">
                    <input v-model="putusSetujuAktivitas" type="checkbox" />
                    <span>
                        Saya sadar aktivitas di atas <b>belum selesai</b> dan tetap memutuskan sekarang.
                    </span>
                </label>
            </div>

            <!-- Keputusan yang menutup proses DAN mengabari kandidat tak bisa
                 dibatalkan. Centang ini memaksa jeda sadar sebelum mengirim. -->
            <label v-if="butuhCentang" class="plw-putus__cek">
                <input v-model="putusSetuju" type="checkbox" />
                <span>Saya paham keputusan ini <b>final</b> dan email pemberitahuan akan <b>langsung dikirim</b> ke kandidat.</span>
            </label>
        </ConfirmModal>

        <!-- ATUR JADWAL aktivitas tatap muka. Menyimpan jadwal SEKALIGUS mengirim
             undangan — dua hal yang selalu berpasangan, jadi tidak dipisah agar
             tidak ada jadwal yang tersimpan tanpa pernah dikabarkan. -->
        <!-- danger=false: ConfirmModal bawaannya bergaya PERINGATAN (ikon tong
             sampah, tombol merah) karena umumnya dipakai untuk tindakan merusak.
             Menjadwalkan wawancara justru sebaliknya — mengundang kandidat. -->
        <ConfirmModal
            :show="jadwalShow"
            :danger="false"
            icon="bi-calendar-event"
            title="Atur Jadwal Aktivitas"
            :subtitle="jadwalTarget ? `${jadwalTarget.label} \u2014 ${detailKandidat?.pelamar || ''}` : ''"
            confirm-label="Simpan & Undang Kandidat"
            :busy="sibuk"
            :confirm-disabled="!bolehSimpanJadwal"
            form-mode
            @confirm="konfirmJadwal"
            @cancel="jadwalShow = false"
        >
            <div class="plw-jdw">
                <!-- Pilihan metode DISEMBUNYIKAN untuk aktivitas yang mustahil
                     daring (MCU itu pemeriksaan fisik, tanda tangan kontrak butuh
                     kehadiran). Menawarkan pilihan yang tidak masuk akal hanya
                     mengundang salah pilih. -->
                <div v-if="!jadwalTarget?.wajibLuring" class="plw-fld">
                    <label class="plw-fld__lbl">Metode <b>*</b></label>
                    <!-- Tombolnya DARI MASTER Mode Jadwal. Dulu dua tombol
                         ditulis mati di sini, dan bentuk ketiga — telepon, yang
                         justru paling lazim untuk penawaran gaji — mustahil ada
                         tanpa menyunting layar. -->
                    <div class="plw-seg">
                        <button
                            v-for="m in modeJadwalDipakai" :key="m.kode"
                            type="button" class="plw-seg__b is-net"
                            :class="{ 'is-on': jadwalMode === m.kode }"
                            :title="m.deskripsi"
                            @click="jadwalMode = m.kode"
                        >
                            <i class="bi" :class="m.ikon"></i> {{ m.nama }}
                        </button>
                    </div>
                    <p v-if="modeJadwalDef?.deskripsi" class="plw-fld__hint">{{ modeJadwalDef.deskripsi }}</p>
                </div>
                <p v-else class="plw-note is-lock">
                    <i class="bi bi-geo-alt-fill"></i>
                    <span>
                        {{ jadwalTarget?.tipeNama || 'Aktivitas ini' }} hanya bisa dijalankan
                        <b>tatap muka</b>, jadi metodenya tidak dapat diubah.
                    </span>
                </p>

                <!-- SATU KOLOM PENUH, tidak lagi berdampingan.
                     Dua pemilih tanggal-dan-jam bersebelahan menyisakan lebar
                     yang tak cukup untuk formatnya sendiri: "12 Agu 2026 09:00"
                     terpotong, dan panel kalendernya melebihi kotaknya lalu
                     tertahan tepi modal. Ditumpuk, keduanya terbaca utuh — dan
                     urutannya jadi jelas: mulai dulu, baru selesai. -->
                <div class="plw-fld">
                    <label class="plw-fld__lbl">Waktu mulai <b>*</b></label>
                    <el-date-picker v-model="jadwalMulai" type="datetime" format="DD MMM YYYY HH:mm" value-format="YYYY-MM-DD HH:mm:ss" placeholder="Pilih tanggal & jam" style="width: 100%" />
                </div>
                <div class="plw-fld">
                    <label class="plw-fld__lbl">Waktu selesai <small>opsional</small></label>
                    <el-date-picker v-model="jadwalSelesai" type="datetime" format="DD MMM YYYY HH:mm" value-format="YYYY-MM-DD HH:mm:ss" placeholder="Perkiraan selesai" style="width: 100%" />
                </div>

                <div v-if="modeJadwalDef?.butuhTautan" class="plw-fld">
                    <label class="plw-fld__lbl" for="jdw-link">Tautan pertemuan <b>*</b></label>
                    <input id="jdw-link" v-model="jadwalLink" type="url" class="plw-inp" placeholder="https://meet.google.com/..." maxlength="500" />
                </div>

                <!-- TELEPON: tanpa tautan, tanpa lokasi. Yang dibutuhkan cuma
                     nomor yang akan dihubungi — dan nomornya BAGIAN DARI JANJI,
                     bukan salinan profil: kandidat yang sedang bekerja kerap
                     minta dihubungi di nomor lain, dan negosiasi gaji justru
                     percakapan yang paling tidak ingin ia terima di mejanya. -->
                <div v-else-if="modeJadwalDef?.butuhKontak" class="plw-fld">
                    <label class="plw-fld__lbl" for="jdw-kontak">{{ modeJadwalDef.labelKontak }} <b>*</b></label>
                    <input id="jdw-kontak" v-model="jadwalKontak" type="tel" class="plw-inp" placeholder="mis. 0812-3456-7890" maxlength="40" />
                    <p v-if="modeJadwalDef.petunjukKontak" class="plw-fld__hint">{{ modeJadwalDef.petunjukKontak }}</p>
                    <button
                        v-if="detailKandidat?.hp && jadwalKontak !== detailKandidat.hp"
                        type="button" class="plw-fld__isi" @click="jadwalKontak = detailKandidat.hp"
                    >
                        <i class="bi bi-arrow-counterclockwise"></i> Pakai nomor profil ({{ detailKandidat.hp }})
                    </button>
                </div>
                <template v-else-if="modeJadwalDef?.butuhLokasi">
                    <!-- Lokasi DIPILIH dari Master Lokasi, bukan diketik bebas.
                         Titik petanya ikut, sehingga kandidat menerima peta yang
                         bisa dibuka — bukan alamat yang harus disalin sendiri. -->
                    <label class="plw-fld__lbl" style="margin-top: 12px">Lokasi <b>*</b></label>
                    <el-select
                        v-model="jadwalLokasiId"
                        filterable
                        placeholder="Pilih kantor / klinik"
                        style="width: 100%; margin-top: 6px"
                        :loading="lokasiLoading"
                    >
                        <el-option
                            v-for="l in daftarLokasi"
                            :key="l.id"
                            :value="l.id"
                            :label="l.nama + (l.kota ? ' — ' + l.kota : '')"
                        >
                            <div class="plw-lok__opt">
                                <b>{{ l.nama }}</b>
                                <small>{{ [l.kategori, l.kota].filter(Boolean).join(' · ') || l.alamat }}</small>
                            </div>
                        </el-option>
                    </el-select>

                    <!-- Pratinjau peta: rekruter memastikan titiknya benar SEBELUM
                         undangan terkirim, bukan setelah kandidat tersesat. -->
                    <div v-if="lokasiTerpilih" class="plw-lok">
                        <iframe
                            v-if="lokasiTerpilih.mapsEmbed"
                            :src="lokasiTerpilih.mapsEmbed"
                            class="plw-lok__map"
                            loading="lazy"
                            referrerpolicy="no-referrer-when-downgrade"
                            :title="'Peta ' + lokasiTerpilih.nama"
                        ></iframe>
                        <div class="plw-lok__info">
                            <i class="bi bi-geo-alt-fill"></i>
                            <div style="min-width: 0">
                                <b>{{ lokasiTerpilih.nama }}</b>
                                <small v-if="lokasiTerpilih.alamat">{{ lokasiTerpilih.alamat }}</small>
                                <small v-if="lokasiTerpilih.kontakTelp">Kontak: {{ lokasiTerpilih.kontakTelp }}</small>
                            </div>
                            <a v-if="lokasiTerpilih.mapsUrl" :href="lokasiTerpilih.mapsUrl" target="_blank" rel="noopener">Buka peta</a>
                        </div>
                    </div>

                    <div class="plw-fld">
                        <label class="plw-fld__lbl" for="jdw-lokasi">Patokan / detail lokasi <small>opsional</small></label>
                        <input id="jdw-lokasi" v-model="jadwalLokasi" type="text" class="plw-inp" placeholder="mis. Gedung B lantai 3, temui resepsionis" maxlength="300" />
                    </div>
                </template>

                <div class="plw-fld">
                    <label class="plw-fld__lbl" for="jdw-catatan">Catatan untuk kandidat <small>opsional</small></label>
                    <textarea id="jdw-catatan" v-model="jadwalCatatan" class="plw-inp plw-inp--ta" rows="2" maxlength="1000" placeholder="mis. Bawa KTP asli dan portofolio cetak."></textarea>
                </div>

                <p class="plw-note is-info">
                    <i class="bi bi-envelope-fill"></i>
                    <span>
                        Undangan berisi waktu, {{ jadwalMode === 'DARING' ? 'tautan pertemuan' : 'lokasi berikut petanya' }},
                        dan catatan di atas langsung dikirim ke email kandidat.
                    </span>
                </p>
            </div>
        </ConfirmModal>

        <!-- Konfirmasi kehadiran. Catatan opsional untuk keduanya — alasan tidak
             hadir sering perlu dicatat, tapi memaksanya justru menahan tim. -->
        <ConfirmModal
            :show="hadirShow"
            :danger="hadirNilai === 'T'"
            :icon="hadirNilai === 'Y' ? 'bi-person-check-fill' : 'bi-person-dash-fill'"
            :title="hadirNilai === 'Y' ? 'Tandai Hadir' : 'Tandai Tidak Hadir'"
            :subtitle="hadirTarget ? `${hadirTarget.label} \u2014 ${detailKandidat?.pelamar || ''}` : ''"
            :confirm-label="hadirNilai === 'Y' ? 'Simpan Kehadiran & Hasil' : 'Ya, Tidak Hadir'"
            :busy="sibuk"
            :confirm-disabled="!bolehSimpanHadir"
            form-mode
            @confirm="konfirmHadir"
            @cancel="tutupModalAktivitas('hadirShow')"
        >
            <p class="plw-hdr__note">
                <template v-if="hadirNilai === 'Y'">
                    Isi hasilnya sekalian di sini — <b>satu langkah</b>, tidak ada jendela lanjutan.
                    Begitu disimpan, aktivitas ini ditutup dan tahapnya dievaluasi sistem.
                </template>
                <template v-else>
                    Aktivitas ini akan ditandai <b>GAGAL</b> dan tahapnya dievaluasi ulang oleh sistem.
                    Tombol keputusan tetap terbuka setelahnya.
                </template>
            </p>

            <!-- KANDIDAT HADIR = penilaiannya sedang berlangsung, dan inilah
                 saat catatannya ditulis — bukan nanti lewat jendela terpisah
                 yang kerap tak pernah dibuka. Keduanya OPSIONAL: menandai
                 kehadiran tidak boleh menuntut penilaian yang belum selesai.

                 Untuk TIDAK HADIR, cukup sebaris alasan. Tidak ada penilaian
                 yang bisa ditulis untuk orang yang tidak datang, dan menyodorkan
                 editor berformat di sana hanya menyiratkan sebaliknya. -->
            <template v-if="hadirNilai === 'Y'">
                <!-- Berkas kandidat ikut terbaca DI SINI, bukan hanya di balik
                     modal: penilai menulis catatannya sambil melihat lembar
                     jawaban yang diserahkan, dan menutup modal untuk membacanya
                     berarti kehilangan yang sudah diketik. -->
                <div v-if="(hadirTarget?.berkasKandidat || []).length" class="plw-kirimbox">
                    <span class="plw-kirimbox__lbl"><i class="bi bi-inbox-fill"></i> Diserahkan kandidat</span>
                    <!-- Dibuka di MODAL, bukan tab baru: tab baru melempar
                         penilai keluar dari jendela yang sedang ia isi. -->
                    <button
                        v-for="b in hadirTarget.berkasKandidat" :key="b.id"
                        type="button" class="plw-test__kirimfile" :title="b.nama" @click="bukaDok(b)"
                    >
                        <i class="bi" :class="b.isImage ? 'bi-file-earmark-image-fill' : 'bi-file-earmark-pdf-fill'"></i>
                        {{ b.nama }}
                    </button>
                </div>

                <!-- JAWABAN KANDIDAT ATAS PENAWARAN.
                     Seluruh proses berakhir pada satu pertanyaan: kandidat
                     mengambil penawaran ini atau tidak. Dicatat DI SINI karena
                     di sinilah jawabannya diterima — tim menelepon, kandidat
                     menjawab. Dulu pertanyaan itu tidak punya satu pun kolom:
                     aktivitas negosiasi berperan INFORMATIF sehingga modal ini
                     tidak menampilkan bidang hasil apa pun. -->
                <div v-if="hadirTarget?.penawaran" class="plw-mform" :class="{ 'is-kurang': jawabKurang }">
                    <div class="plw-mform__head">
                        <span class="plw-mform__ico"><i class="bi bi-envelope-paper-fill"></i></span>
                        <div style="min-width: 0; flex: 1">
                            <div class="plw-mform__title">Jawaban Kandidat atas Penawaran</div>
                            <div class="plw-mform__sub">{{ hadirTarget.label }}<template v-if="hadirTarget.jadwal"> · {{ jadwalRingkas(hadirTarget.jadwal) }}</template></div>
                        </div>
                        <span class="plw-req">wajib</span>
                    </div>

                    <div class="plw-fld">
                        <label class="plw-fld__lbl">Apa jawabannya? <b>*</b></label>
                        <div class="plw-opts">
                            <button
                                v-for="o in jawabanPenawaran" :key="o.kode"
                                type="button" class="plw-opt-card" :class="[`is-${o.nada}`, { 'is-on': jawabPenawaran === o.kode }]"
                                @click="jawabPenawaran = o.kode"
                            >
                                <span class="plw-opt-card__dot"><i class="bi" :class="o.ikon"></i></span>
                                <span class="plw-opt-card__txt">
                                    <b>{{ o.label }}</b>
                                    <small>{{ o.keterangan }}</small>
                                </span>
                            </button>
                        </div>
                        <!-- AKIBATNYA DIKATAKAN SEBELUM DITEKAN. Dua dari tiga
                             jawaban menutup lamaran seketika, dan itu tidak bisa
                             ditarik kembali — admin berhak tahu sebelum, bukan
                             sesudah. -->
                        <p v-if="jawabDef" class="plw-fld__hint" :class="{ 'is-tegas': jawabDef.menutup }">
                            <i class="bi" :class="jawabDef.menutup ? 'bi-exclamation-triangle-fill' : 'bi-info-circle-fill'"></i>
                            <template v-if="jawabDef.menutup">
                                Menyimpan akan <b>menutup lamaran ini seketika</b> dan mengirim pemberitahuan.
                                Tuliskan alasannya di catatan di bawah &mdash; minimal 10 karakter.
                            </template>
                            <template v-else>
                                Lamaran <b>tidak</b> ditutup di sini. Keputusan mengangkat kandidat tetap lewat
                                tombol tahap di worklist.
                            </template>
                        </p>
                    </div>

                    <p v-if="jawabKurang" class="plw-note is-err">
                        <i class="bi bi-exclamation-circle-fill"></i>
                        <span>{{ jawabKurang }}</span>
                    </p>
                </div>

                <!-- HASIL PEMERIKSAAN KESEHATAN — di langkah inilah tempatnya.
                     Kandidat datang ke klinik, diperiksa, dan inilah hasilnya:
                     satu peristiwa, satu jendela. Empat status baku dari master
                     (Fit / Fit with Note / Temporary Unfit / Unfit) — bukan tiga
                     yang ditulis mati di layar, yang membuat kandidat yang cuma
                     perlu diperiksa ulang dua minggu lagi terpaksa dicatat
                     "Unfit" dan gugur permanen. -->
                <div v-if="hadirTarget?.isMcu" class="plw-mform" :class="{ 'is-kurang': mcuKurangHadir }">
                    <div class="plw-mform__head">
                        <span class="plw-mform__ico"><i class="bi bi-heart-pulse-fill"></i></span>
                        <div style="min-width: 0; flex: 1">
                            <div class="plw-mform__title">Hasil Pemeriksaan Kesehatan</div>
                            <div class="plw-mform__sub">{{ hadirTarget.label }}<template v-if="hadirTarget.jadwal"> · {{ jadwalRingkas(hadirTarget.jadwal) }}</template></div>
                        </div>
                        <span class="plw-req">wajib</span>
                    </div>

                    <div class="plw-fld">
                        <label class="plw-fld__lbl">Status Kesehatan <b>*</b></label>
                        <!-- Kartu, bukan radio bawaan: statusnya berwarna sesuai
                             artinya dan sasaran kliknya selebar baris. -->
                        <div class="plw-opts">
                            <button
                                v-for="o in mcuStatusOpsi" :key="o.kode"
                                type="button" class="plw-opt-card" :class="[`is-${o.nada}`, { 'is-on': mcuStatus === o.kode }]"
                                @click="mcuStatus = o.kode"
                            >
                                <span class="plw-opt-card__dot"><i class="bi" :class="o.ikon"></i></span>
                                <span class="plw-opt-card__txt">
                                    <b>{{ o.label }}</b>
                                    <small>{{ o.keterangan }}</small>
                                </span>
                            </button>
                        </div>
                        <!-- Arti pilihan itu bagi proses DIKATAKAN, tidak dibiarkan
                             ditebak: "Temporary Unfit" dan "Unfit" sama-sama tidak
                             lolos, dan penilai berhak tahu itu SEBELUM menekan. -->
                        <p v-if="mcuDef" class="plw-fld__hint">
                            <i class="bi" :class="mcuDef.lolos ? 'bi-check-circle-fill' : 'bi-x-circle-fill'"></i>
                            Pilihan ini menutup MCU sebagai <b>{{ mcuDef.lolos ? 'LULUS' : 'TIDAK LULUS' }}</b>.
                        </p>
                    </div>

                    <div class="plw-fld">
                        <label class="plw-fld__lbl" for="mcu-penyedia">Penyedia (klinik / RS)</label>
                        <!-- Contoh sengaja TIDAK memakai nama rumah sakit nyata:
                             teks samar mudah terbaca sebagai isian yang sudah
                             terisi, dan nama yang salah pada hasil kesehatan
                             bukan kekeliruan yang murah. -->
                        <input id="mcu-penyedia" v-model="mcuPenyedia" type="text" class="plw-inp" placeholder="Nama klinik / rumah sakit pelaksana" maxlength="200" />
                    </div>
                    <div class="plw-fld">
                        <label class="plw-fld__lbl" for="mcu-tanggal">Tanggal Pemeriksaan</label>
                        <input id="mcu-tanggal" v-model="mcuTanggal" type="date" class="plw-inp" />
                    </div>

                    <!-- DUA KOTAK CATATAN, DAN ITU DISENGAJA.
                         Bukan karena "beda maksud" — karena PEMBACANYA BERBEDA.
                         Yang ini dikirim ke portal dan dibaca KANDIDAT SENDIRI
                         (portalDetail → mcu.catatan); yang berformat di bawah
                         ditahan khusus untuk tim.

                         Menggabungkannya hanya punya dua hasil, dua-duanya
                         buruk: kandidat tak pernah tahu ada pembatasan kerja
                         pada dirinya, atau temuan klinis bocor ke orang yang
                         sedang dinilai. Yang dibetulkan bukan jumlahnya,
                         melainkan labelnya — yang dulu sama-sama berbunyi
                         "catatan" tanpa menyebut siapa yang membacanya. -->
                    <div class="plw-fld">
                        <label class="plw-fld__lbl" for="mcu-catatan">
                            {{ hadirTarget?.internal ? 'Keterangan hasil' : 'Keterangan untuk kandidat' }}
                            <b v-if="mcuDef?.butuhCatatan">*</b>
                            <!-- PENANDA MENGIKUTI SETELAN AKTIVITAS INI, bukan
                                 anggapan umum. MCU bisa disetel internal di
                                 Master Alur (Tampil_Kandidat='T'), dan pada alur
                                 begitu kalimat ini TIDAK pernah sampai ke
                                 kandidat. Memasang lencana "dibaca kandidat"
                                 apa adanya berarti penilai menahan diri menulis
                                 hal yang sebenarnya aman — atau sebaliknya,
                                 menulis untuk pembaca yang tidak ada. -->
                            <span class="plw-lihat" :class="hadirTarget?.internal ? 'is-internal' : 'is-publik'">
                                <i class="bi" :class="hadirTarget?.internal ? 'bi-eye-slash-fill' : 'bi-eye-fill'"></i>
                                {{ hadirTarget?.internal ? 'HANYA TIM' : 'DIBACA KANDIDAT' }}
                            </span>
                        </label>
                        <textarea
                            id="mcu-catatan" v-model="mcuCatatan" class="plw-inp plw-inp--ta" rows="2" maxlength="1000"
                            :placeholder="mcuDef?.butuhCatatan
                                ? 'Wajib: pembatasan atau tindak lanjut yang perlu kandidat ketahui — mis. hindari kerja malam; periksa ulang 2 minggu lagi.'
                                : 'mis. Disarankan kontrol tekanan darah berkala.'"
                        ></textarea>
                        <p class="plw-note is-lock">
                            <i class="bi bi-shield-lock-fill"></i>
                            <span v-if="hadirTarget?.internal">
                                Aktivitas MCU ini disetel <b>internal</b> di Master Alur, jadi keterangan
                                ini <b>tidak</b> ditampilkan ke kandidat &mdash; ia tetap perlu diberi tahu
                                lewat jalur lain bila ada pembatasan kerja.
                            </span>
                            <span v-else>
                                Kalimat ini muncul di halaman lamaran kandidat. Tulis <b>akibatnya bagi
                                pekerjaan</b>, bukan diagnosisnya &mdash; temuan klinis rinci ditulis di
                                catatan internal di bawah atau tetap di berkas terlampir.
                            </span>
                        </p>
                    </div>
                    <p v-if="mcuKurangHadir" class="plw-note is-err">
                        <i class="bi bi-exclamation-circle-fill"></i>
                        <span>{{ mcuKurangHadir }}</span>
                    </p>
                </div>

                <!-- HASIL DI SINI JUGA — satu tindakan, bukan dua.
                     Dulu ada tombol "Catat Hasil" terpisah dengan jendelanya
                     sendiri; keduanya menjelaskan SATU peristiwa ("kandidat
                     datang, hasilnya begini"). Yang paling sering terjadi:
                     jendela kedua tidak pernah dibuka, dan hasilnya tak pernah
                     tercatat. Hanya untuk aktivitas yang hasilnya memang
                     dikerjakan tim — ujian online nilainya datang dari HCLearn. -->
                <div v-if="hadirTarget?.dinilaiTim && hadirTarget?.peran !== 'INFORMATIF' && !hadirTarget?.isMcu && !hadirTarget?.penawaran" class="plw-fld">
                    <label class="plw-fld__lbl">Hasil <b>*</b></label>
                    <div class="plw-seg">
                        <button type="button" class="plw-seg__b is-ok" :class="{ 'is-on': hadirHasil === 'LULUS' }" @click="hadirHasil = 'LULUS'">
                            <i class="bi bi-check-lg"></i> Lulus
                        </button>
                        <button type="button" class="plw-seg__b is-no" :class="{ 'is-on': hadirHasil === 'GAGAL' }" @click="hadirHasil = 'GAGAL'">
                            <i class="bi bi-x-lg"></i> Gagal
                        </button>
                    </div>
                </div>

                <!-- BIDANG NILAI MENGIKUTI MODE PENILAIAN aktivitas ini
                     (disetel di Master Alur). Tes tertulis diberi kotak angka;
                     DISC/FGD diberi daftar predikat; wawancara tidak diberi
                     bidang nilai sama sekali — memaksakan angka pada tes yang
                     hasilnya predikat hanya melahirkan angka karangan. -->
                <div v-if="hadirTarget?.penilaian?.tipe === 'ANGKA'" class="plw-fld">
                    <label class="plw-fld__lbl" for="hadir-nilai">
                        {{ hadirTarget.penilaian.nama }}
                        <small>0 – {{ hadirTarget.penilaian.maks || 1000 }}</small>
                    </label>
                    <input
                        id="hadir-nilai" v-model.number="hadirNilaiSkor" type="number"
                        min="0" :max="hadirTarget.penilaian.maks || 1000" step="0.01"
                        class="plw-inp" placeholder="mis. 85"
                    />
                </div>

                <div v-else-if="hadirTarget?.penilaian?.tipe === 'TEKS'" class="plw-fld">
                    <label class="plw-fld__lbl">{{ hadirTarget.penilaian.nama }}</label>
                    <div class="plw-opts">
                        <button
                            v-for="o in hadirTarget.penilaian.opsi" :key="o"
                            type="button" class="plw-opt-card" :class="{ 'is-on': hadirNilaiTeks === o }"
                            @click="hadirNilaiTeks = hadirNilaiTeks === o ? '' : o"
                        >
                            <span class="plw-opt-card__dot"><i class="bi bi-tag-fill"></i></span>
                            <span class="plw-opt-card__txt"><b>{{ o }}</b></span>
                        </button>
                    </div>
                </div>

                <div class="plw-fld">
                    <label class="plw-fld__lbl">
                        <!-- Dulu dirakit dari nama tipe lalu di-toLowerCase(),
                             sehingga "MCU" jadi "mcu" — nama yang sudah punya
                             bentuk bakunya sendiri jadi rusak. -->
                        {{ hadirTarget?.labelCatatan || 'Catatan hasil aktivitas' }}
                        <small>opsional</small>
                        <!-- Penanda pembaca, bukan hiasan: inilah satu-satunya
                             pembeda antara kotak ini dan "Keterangan untuk
                             kandidat" di atas. Tanpa itu keduanya terbaca
                             sebagai pengulangan, dan orang mengisi salah satu
                             secara acak. -->
                        <span class="plw-lihat is-internal">
                            <i class="bi bi-eye-slash-fill"></i> HANYA TIM
                        </span>
                    </label>
                    <EditorQuill
                        v-model="hadirCatatanHtml"
                        ringkas
                        :upload-url="URL_GAMBAR"
                        :upload-data="{ subTesId: hadirTarget?.id }"
                        placeholder="mis. Komunikasi jelas, pengalaman relevan. Tempelkan juga foto lembar penilaian bila ada."
                        hint="Bisa diberi format & gambar. Catatan ini INTERNAL — kandidat tidak melihatnya."
                    />
                </div>

                <!-- Nama lampirannya DARI TIPE AKTIVITAS, bukan satu kalimat
                     untuk semua: MCU meminta hasil dari klinik, wawancara
                     meminta lembar penilaian, tes offline meminta lembar
                     jawaban. Kalimat yang salah membuat petugas ragu apakah ia
                     sedang membuka jendela yang benar. -->
                <!-- ADA-TIDAKNYA kotak ini ditentukan TIPE AKTIVITASNYA.
                     Negosiasi gaji berlangsung lewat telepon dan tidak
                     menghasilkan dokumen; kotak kosong di sana cuma meminta
                     sesuatu yang memang tidak ada. -->
                <template v-if="hadirTarget?.berkasAktivitas !== false">
                    <BerkasAktivitas
                        :sub-tes-id="hadirTarget?.id || ''"
                        :awal="hadirTarget?.berkas || []"
                        :label="hadirTarget?.labelBerkas || 'Berkas hasil / lampiran penilaian'"
                        @berubah="tandaiBerkasBerubah"
                        @lihat="bukaDok"
                    />
                    <p v-if="hadirTarget?.petunjukBerkas" class="plw-fld__hint">
                        <i class="bi bi-info-circle"></i> {{ hadirTarget.petunjukBerkas }}
                    </p>
                </template>
            </template>

            <div v-else class="plw-fld">
                <label class="plw-fld__lbl" for="hadir-catatan">Alasan <small>opsional</small></label>
                <textarea
                    id="hadir-catatan" v-model="hadirCatatan" class="plw-inp plw-inp--ta" rows="2" maxlength="500"
                    placeholder="Boleh dikosongkan — mis. sakit, minta jadwal ulang"
                ></textarea>
            </div>
        </ConfirmModal>

        <!-- Catat hasil sub-tes MANUAL (wawancara/FGD) — masuk mesin keputusan yang sama. -->
        <ConfirmModal
            :show="catatShow"
            :danger="false"
            icon="bi-pencil-square"
            title="Catat Hasil Aktivitas"
            :subtitle="catatTarget ? `${catatTarget.label} — ${detailKandidat?.pelamar || ''}` : ''"
            confirm-label="Simpan Hasil"
            :busy="sibuk"
            :confirm-disabled="!bolehSimpanCatat"
            form-mode
            @confirm="konfirmCatat"
            @cancel="tutupModalAktivitas('catatShow')"
        >
            <!-- Hanya aktivitas yang DIKERJAKAN TIM yang sampai ke sini. Ujian
                 online tak punya tombol ini — nilainya datang dari HCLearn — dan
                 MCU juga tidak: hasil pemeriksaan kesehatan dicatat langsung di
                 jendela keputusan, bersama berkas dari kliniknya. -->
            <div class="plw-fld">
                <label class="plw-fld__lbl">Hasil <b v-if="catatTarget && catatTarget.peran !== 'INFORMATIF'">*</b></label>
                <div v-if="catatTarget && catatTarget.peran !== 'INFORMATIF'" class="plw-seg">
                    <button type="button" class="plw-seg__b is-ok" :class="{ 'is-on': catatHasil === 'LULUS' }" @click="catatHasil = 'LULUS'">
                        <i class="bi bi-check-lg"></i> Lulus
                    </button>
                    <button type="button" class="plw-seg__b is-no" :class="{ 'is-on': catatHasil === 'GAGAL' }" @click="catatHasil = 'GAGAL'">
                        <i class="bi bi-x-lg"></i> Gagal
                    </button>
                </div>
                <p v-else class="plw-note is-info">
                    <i class="bi bi-info-circle-fill"></i>
                    <span>Aktivitas informatif — cukup nilai/catatan, tidak menentukan lulus.</span>
                </p>
            </div>

            <div class="plw-fld__row">
                <div class="plw-fld">
                    <label class="plw-fld__lbl" for="catat-nilai">Nilai <small>opsional</small></label>
                    <input id="catat-nilai" v-model.number="catatNilai" type="number" min="0" max="1000" step="1" class="plw-inp" placeholder="0 – 1000" />
                </div>
                <div class="plw-fld">
                    <label class="plw-fld__lbl">Aktivitas</label>
                    <div class="plw-inp is-static">{{ catatTarget?.tipeNama || '—' }}</div>
                </div>
            </div>

            <div v-if="(catatTarget?.berkasKandidat || []).length" class="plw-kirimbox">
                <span class="plw-kirimbox__lbl"><i class="bi bi-inbox-fill"></i> Diserahkan kandidat</span>
                <a
                    v-for="b in catatTarget.berkasKandidat" :key="b.id"
                    :href="b.url" target="_blank" rel="noopener" class="plw-test__kirimfile" :title="b.nama"
                >
                    <i class="bi" :class="b.isImage ? 'bi-file-earmark-image-fill' : 'bi-file-earmark-pdf-fill'"></i>
                    {{ b.nama }}
                </a>
            </div>
            <p v-else-if="catatTarget?.unggahKandidat?.wajib" class="plw-note is-err">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <span>Kandidat <b>belum mengunggah</b> berkas yang diwajibkan untuk aktivitas ini.</span>
            </p>

            <div class="plw-fld">
                <label class="plw-fld__lbl">Catatan Penilai <small>opsional</small></label>
                <EditorQuill
                    v-model="catatCatatanHtml"
                    ringkas
                    :upload-url="URL_GAMBAR"
                    :upload-data="{ subTesId: catatTarget?.id }"
                    placeholder="mis. Komunikatif, hasil FGD baik — direkomendasikan lanjut"
                    hint="Bisa diberi format & gambar. Catatan ini INTERNAL — kandidat tidak melihatnya."
                />
            </div>

            <!-- Lembar penilaian yang dipindai berlabuh pada AKTIVITAS ini,
                 bukan pada tahapnya — di tahap campuran, berkas setingkat tahap
                 tak bisa dibedakan antara hasil DISC dan hasil wawancara. -->
            <BerkasAktivitas
                :sub-tes-id="catatTarget?.id || ''"
                :awal="catatTarget?.berkas || []"
                label="Berkas hasil aktivitas"
                @berubah="tandaiBerkasBerubah"
                @lihat="bukaDok"
            />
        </ConfirmModal>

        <!-- TIDAK HADIR — WAJIB dikonfirmasi. Sekali diklik, aktivitasnya final
             dan pada tahap ber-mode gugur-otomatis kandidat langsung dinyatakan
             tidak lolos. Tanpa konfirmasi, satu klik keliru menutup lamaran orang
             dan hanya bisa dibatalkan lewat query manual di database. -->
        <ConfirmModal
            :show="absenShow"
            title="Tandai Tidak Hadir"
            :subtitle="absenTarget ? `${absenTarget.label} — ${detailKandidat?.pelamar || ''}` : ''"
            danger
            confirm-label="Ya, Tandai Tidak Hadir"
            :busy="sibuk"
            @confirm="konfirmTidakHadir"
            @cancel="absenShow = false"
        >
            <div class="plw-putus__ring is-gugur">
                <div class="plw-putus__row">
                    <i class="bi bi-person-x"></i>
                    <span><b>{{ absenTarget?.label }}</b> ditandai <b>tidak hadir</b> dan hasilnya menjadi final — tidak bisa diulang lewat layar ini.</span>
                </div>
                <div v-if="absenTarget?.online" class="plw-putus__row">
                    <i class="bi bi-cloud-slash"></i>
                    <span>Tahap berhenti menunggu hasil dari <b>HCLearn</b>. Bila kandidat sebenarnya mengerjakan tes, pakai <b>Sinkronkan</b>, bukan ini.</span>
                </div>
                <div v-if="absenTarget?.peran !== 'INFORMATIF'" class="plw-putus__row">
                    <i class="bi bi-exclamation-octagon"></i>
                    <span>Ini aktivitas <b>penentu</b>. Pada tahap yang gugurnya otomatis, kandidat <b>langsung dinyatakan tidak lolos</b>.</span>
                </div>
            </div>
            <!-- Catatan OPSIONAL, sama seperti modal kehadiran — dua jalur yang
                 menghasilkan keadaan sama tidak boleh menuntut hal berbeda. -->
            <div class="plw-fld">
                <label class="plw-fld__lbl" for="absen-catatan">Catatan <small>opsional</small></label>
                <textarea
                    id="absen-catatan" v-model="absenCatatan" class="plw-inp plw-inp--ta" rows="2" maxlength="500"
                    placeholder="Boleh dikosongkan — mis. tidak merespons undangan sampai batas waktu"
                ></textarea>
            </div>
        </ConfirmModal>

        <!-- CETAK LAPORAN KANDIDAT.
             Pilihan formulir hanya muncul bila kandidatnya MEMANG punya lebih
             dari satu — MT mengumpulkan data dua kali (pendaftaran, lalu
             kelengkapan data diri). Untuk yang cuma satu, menanyakannya berarti
             menyuruh admin memilih dari daftar berisi satu baris. -->
        <ConfirmModal
            :show="laporanShow"
            :danger="false"
            icon="bi-printer-fill"
            title="Cetak Laporan Kandidat"
            :subtitle="detailKandidat ? `${detailKandidat.pelamar} — ${detailKandidat.lamaranKode}` : ''"
            :confirm-label="laporanSiap ? 'Tutup' : 'Buat Laporan'"
            :busy="sibuk"
            :confirm-disabled="laporanProses"
            form-mode
            @confirm="laporanSiap ? (laporanShow = false) : konfirmLaporan()"
            @cancel="laporanShow = false"
        >
            <div class="plw-fld">
                <label class="plw-fld__lbl">Format berkas <b>*</b></label>
                <div class="plw-opts">
                    <button type="button" class="plw-opt-card" :class="{ 'is-on': laporanFormat === 'PDF' }" @click="laporanFormat = 'PDF'">
                        <span class="plw-opt-card__dot" style="color:#dc2626"><i class="bi bi-file-earmark-pdf-fill"></i></span>
                        <span class="plw-opt-card__txt">
                            <b>PDF — untuk dibaca &amp; diarsip</b>
                            <small>Berkop EVO Group, lengkap dengan foto verifikasi dan perjalanan seleksi. Siap dicetak.</small>
                        </span>
                    </button>
                    <button type="button" class="plw-opt-card" :class="{ 'is-on': laporanFormat === 'XLSX' }" @click="laporanFormat = 'XLSX'">
                        <span class="plw-opt-card__dot" style="color:#15803d"><i class="bi bi-file-earmark-spreadsheet-fill"></i></span>
                        <span class="plw-opt-card__txt">
                            <b>Excel — untuk diolah</b>
                            <small>Tiga lembar: profil, perjalanan per aktivitas, dan jawaban formulir. Bisa disaring &amp; dipivot.</small>
                        </span>
                    </button>
                </div>
            </div>

            <div v-if="laporanOpsi.length > 1" class="plw-fld">
                <label class="plw-fld__lbl">Formulir yang disertakan</label>
                <label v-for="f in laporanOpsi" :key="f.id" class="plw-cek">
                    <input type="checkbox" :value="f.id" v-model="laporanFormulir" />
                    <span>
                        <b>{{ f.label }}</b>
                        <small>
                            Dikirim {{ tglId(f.waktuKirim) }}
                            <template v-if="f.utama"> · <b>terbaru</b></template>
                        </small>
                    </span>
                </label>
                <p class="plw-note is-info">
                    <i class="bi bi-info-circle-fill"></i>
                    <span>Bawaannya <b>yang terbaru</b>. Centang keduanya bila perlu membandingkan jawaban lama dengan pembaruannya.</span>
                </p>
            </div>
            <p v-else-if="laporanOpsi.length === 1" class="plw-note is-info">
                <i class="bi bi-info-circle-fill"></i>
                <span>Kandidat ini punya satu formulir (<b>{{ laporanOpsi[0].label }}</b>) — langsung disertakan.</span>
            </p>

            <div v-if="laporanProses" class="plw-note is-info">
                <i class="bi bi-arrow-repeat plw-spin"></i>
                <span>Laporan sedang dibuat di latar belakang…</span>
            </div>
            <a v-else-if="laporanSiap" :href="laporanUrl" target="_blank" rel="noopener" class="plw-unduh">
                <i class="bi bi-download"></i> Unduh laporan {{ laporanFormat === 'XLSX' ? 'Excel' : 'PDF' }}
            </a>
            <p v-else-if="laporanGagal" class="plw-note is-err">
                <i class="bi bi-exclamation-circle-fill"></i>
                <span>{{ laporanGagal }}</span>
            </p>
        </ConfirmModal>

        <!-- JADWAL MASSAL. Satu jendela untuk seratus kandidat.
             Rinciannya sengaja dipampang di muka (siapa saja, jam berapa
             masing-masing) — undangan yang sudah terkirim tak bisa ditarik. -->
        <ConfirmModal
            :show="massalShow"
            :danger="false"
            icon="bi-calendar-plus"
            title="Jadwalkan Massal"
            :subtitle="`${terpilih.length} kandidat terpilih`"
            confirm-label="Simpan & Undang Semua"
            :busy="sibuk"
            :confirm-disabled="!bolehSimpanMassal"
            form-mode
            @confirm="konfirmJadwalMassal"
            @cancel="massalShow = false"
        >
            <div class="plw-jdw">
                <div class="plw-fld">
                    <label class="plw-fld__lbl">Aktivitas yang dijadwalkan <b>*</b></label>
                    <el-select v-model="massalAktivitas" filterable placeholder="Pilih aktivitas" style="width: 100%">
                        <el-option
                            v-for="a in aktivitasTerpilih" :key="a.label"
                            :value="a.label"
                            :label="`${a.label} — ${a.jumlah} kandidat`"
                        />
                    </el-select>
                    <p class="plw-note is-info" style="margin-top: 8px">
                        <i class="bi bi-info-circle-fill"></i>
                        <span>
                            Hanya kandidat yang <b>punya aktivitas ini</b> dan belum selesai yang dijadwalkan.
                            Sisanya dilewati dan dilaporkan satu per satu — tidak ada yang gagal diam-diam.
                        </span>
                    </p>
                </div>

                <div v-if="!massalWajibLuring" class="plw-fld">
                    <label class="plw-fld__lbl">Metode <b>*</b></label>
                    <div class="plw-seg">
                        <button type="button" class="plw-seg__b is-net" :class="{ 'is-on': massalMode === 'DARING' }" @click="massalMode = 'DARING'">
                            <i class="bi bi-camera-video-fill"></i> Daring
                        </button>
                        <button type="button" class="plw-seg__b is-net" :class="{ 'is-on': massalMode === 'LURING' }" @click="massalMode = 'LURING'">
                            <i class="bi bi-geo-alt-fill"></i> Tatap muka
                        </button>
                    </div>
                </div>
                <p v-else class="plw-note is-lock">
                    <i class="bi bi-geo-alt-fill"></i>
                    <span>Aktivitas ini hanya bisa dijalankan <b>tatap muka</b>, jadi metodenya tidak dapat diubah.</span>
                </p>

                <!-- DUA POLA WAKTU — keduanya nyata di lapangan, dan memilih
                     yang salah berarti seratus orang datang berbarengan untuk
                     wawancara yang hanya bisa satu-satu. -->
                <div class="plw-fld">
                    <label class="plw-fld__lbl">Pola waktu <b>*</b></label>
                    <div class="plw-opts">
                        <button type="button" class="plw-opt-card" :class="{ 'is-on': massalPola === 'SERENTAK' }" @click="massalPola = 'SERENTAK'">
                            <span class="plw-opt-card__dot"><i class="bi bi-people-fill"></i></span>
                            <span class="plw-opt-card__txt">
                                <b>Serentak — semua di jam yang sama</b>
                                <small>Untuk FGD, tes tertulis massal, atau briefing bersama.</small>
                            </span>
                        </button>
                        <button type="button" class="plw-opt-card" :class="{ 'is-on': massalPola === 'BERGILIR' }" @click="massalPola = 'BERGILIR'">
                            <span class="plw-opt-card__dot"><i class="bi bi-hourglass-split"></i></span>
                            <span class="plw-opt-card__txt">
                                <b>Bergilir — satu per satu</b>
                                <small>Untuk wawancara panel. Tiap kandidat dapat slotnya sendiri, berurutan dari jam mulai.</small>
                            </span>
                        </button>
                    </div>
                </div>

                <div class="plw-fld__row">
                    <div class="plw-fld">
                        <label class="plw-fld__lbl">{{ massalPola === 'BERGILIR' ? 'Slot pertama mulai' : 'Waktu mulai' }} <b>*</b></label>
                        <el-date-picker v-model="massalMulai" type="datetime" format="DD MMM YYYY HH:mm" value-format="YYYY-MM-DD HH:mm:ss" placeholder="Pilih tanggal & jam" style="width: 100%" />
                    </div>
                    <div v-if="massalPola === 'BERGILIR'" class="plw-fld__row" style="gap: 8px">
                        <div class="plw-fld">
                            <label class="plw-fld__lbl">Durasi / orang</label>
                            <el-input-number v-model="massalDurasi" :min="5" :max="480" :step="5" controls-position="right" style="width: 100%" />
                        </div>
                        <div class="plw-fld">
                            <label class="plw-fld__lbl">Jeda antar sesi</label>
                            <el-input-number v-model="massalJeda" :min="0" :max="240" :step="5" controls-position="right" style="width: 100%" />
                        </div>
                    </div>
                </div>

                <div v-if="massalMode === 'DARING'" class="plw-fld">
                    <label class="plw-fld__lbl">Tautan pertemuan <b>*</b></label>
                    <input v-model="massalLink" type="url" class="plw-inp" placeholder="https://meet.google.com/..." maxlength="500" />
                </div>
                <template v-else>
                    <label class="plw-fld__lbl" style="margin-top: 12px">Lokasi <b>*</b></label>
                    <el-select v-model="massalLokasiId" filterable placeholder="Pilih kantor / klinik" style="width: 100%; margin-top: 6px" :loading="lokasiLoading">
                        <el-option v-for="l in daftarLokasi" :key="l.id" :value="l.id" :label="l.nama + (l.kota ? ' — ' + l.kota : '')" />
                    </el-select>
                    <div class="plw-fld">
                        <label class="plw-fld__lbl">Patokan / detail lokasi <small>opsional</small></label>
                        <input v-model="massalLokasi" type="text" class="plw-inp" placeholder="mis. Gedung B lantai 3, temui resepsionis" maxlength="300" />
                    </div>
                </template>

                <div class="plw-fld">
                    <label class="plw-fld__lbl">Catatan untuk kandidat <small>opsional</small></label>
                    <textarea v-model="massalCatatan" class="plw-inp plw-inp--ta" rows="2" maxlength="1000" placeholder="mis. Bawa KTP asli dan portofolio cetak."></textarea>
                </div>

                <!-- PRATINJAU JAM. Undangan yang sudah terkirim tak bisa ditarik,
                     jadi siapa-dapat-jam-berapa harus terbaca SEBELUM dikirim,
                     bukan disimpulkan dari rumus di kepala. -->
                <div v-if="pratinjauMassal.length" class="plw-prev">
                    <div class="plw-prev__head">
                        <i class="bi bi-list-ol"></i>
                        <span>Pratinjau — {{ pratinjauMassal.length }} kandidat akan diundang</span>
                    </div>
                    <div class="plw-prev__list">
                        <div v-for="(p, i) in pratinjauMassal" :key="p.id" class="plw-prev__row">
                            <span class="plw-prev__no">{{ i + 1 }}</span>
                            <span class="plw-ell">{{ p.pelamar }}</span>
                            <b>{{ p.jam }}</b>
                        </div>
                    </div>
                    <p v-if="massalDilewati.length" class="plw-note is-err" style="margin-top: 8px">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        <span><b>{{ massalDilewati.length }}</b> kandidat terpilih tidak punya aktivitas ini dan akan dilewati.</span>
                    </p>
                </div>

                <p class="plw-note is-info">
                    <i class="bi bi-envelope-fill"></i>
                    <span>Undangan dikirim ke email <b>tiap kandidat</b> begitu disimpan, berisi jam masing-masing.</span>
                </p>
            </div>
        </ConfirmModal>

        <!-- TAHAN / LEPASKAN. Alasannya dipilih dari MASTER, bukan diketik
             bebas: inilah yang dihitung saat menjelaskan kenapa satu lowongan
             lama terisi, dan "nunggu user" / "menunggu user dept" / "blm dijawab
             user" adalah tiga tulisan untuk satu sebab yang sama. -->
        <ConfirmModal
            :show="holdShow"
            :danger="false"
            :icon="holdNilai ? 'bi-pause-circle-fill' : 'bi-play-circle-fill'"
            :title="holdNilai ? 'Tahan Kandidat' : 'Lanjutkan Proses'"
            :subtitle="detailKandidat ? `${detailKandidat.pelamar} — tahap ${detailKandidat.tahap}` : ''"
            :confirm-label="holdNilai ? 'Ya, Tahan' : 'Ya, Lanjutkan'"
            :busy="sibuk"
            :confirm-disabled="!bolehSimpanHold"
            form-mode
            @confirm="konfirmHold"
            @cancel="holdShow = false"
        >
            <div class="plw-putus__ring" :class="holdNilai ? 'is-talent_pool' : 'is-lulus'">
                <div class="plw-putus__row">
                    <i class="bi" :class="holdNilai ? 'bi-pause-circle' : 'bi-play-circle'"></i>
                    <span v-if="holdNilai">
                        Kandidat <b>tetap di tahap ini</b> — tidak dipindah, tidak digugurkan.
                        Yang tertunda hanya keputusannya.
                    </span>
                    <span v-else>
                        Penahanan dilepas. Bila hasil tesnya sudah lengkap selama ditahan,
                        sistem langsung menyimpulkan tahap ini begitu Anda menekan Lanjutkan.
                    </span>
                </div>
                <div class="plw-putus__row">
                    <i class="bi bi-envelope-slash"></i>
                    <span><b>Tidak ada email atau WA</b> yang dikirim ke kandidat — ini catatan internal.</span>
                </div>
            </div>

            <template v-if="holdNilai">
                <div class="plw-fld">
                    <label class="plw-fld__lbl">Alasan menahan <b>*</b></label>
                    <!-- Daftar & aturannya SELURUHNYA dari Master Alasan Hold. -->
                    <div class="plw-opts">
                        <button
                            v-for="a in alasanHold" :key="a.value"
                            type="button" class="plw-opt-card"
                            :class="{ 'is-on': holdAlasan === a.value }"
                            :style="holdAlasan === a.value ? { borderColor: a.warna, background: a.warna + '14' } : null"
                            @click="holdAlasan = a.value"
                        >
                            <span class="plw-opt-card__dot" :style="{ color: a.warna }"><i class="bi" :class="a.ikon || 'bi-pause'"></i></span>
                            <span class="plw-opt-card__txt">
                                <b>{{ a.nama }}</b>
                                <small>{{ a.deskripsi }}</small>
                            </span>
                        </button>
                    </div>
                </div>

                <div class="plw-fld">
                    <label class="plw-fld__lbl">
                        Keterangan
                        <b v-if="holdButuhCatatan">*</b>
                        <small v-else>opsional</small>
                    </label>
                    <EditorQuill
                        v-model="holdCatatanHtml"
                        ringkas
                        :upload-url="URL_GAMBAR"
                        :upload-data="{ tahapId: detailKandidat?.tahapId }"
                        placeholder="mis. Menunggu approval MPP dari Direktur — target minggu depan."
                        hint="Bisa diberi format & gambar. Tersimpan sebagai riwayat penahanan tahap ini."
                    />
                </div>
                <p v-if="holdButuhCatatan && !holdCatatanCukup" class="plw-note is-err">
                    <i class="bi bi-exclamation-circle-fill"></i>
                    <span>Alasan ini menuntut keterangan tambahan — tanpa itu, ia tidak menjelaskan apa pun saat ditinjau kembali.</span>
                </p>
            </template>

            <div v-else class="plw-fld">
                <label class="plw-fld__lbl">Catatan pelepasan <small>opsional</small></label>
                <EditorQuill
                    v-model="holdCatatanHtml"
                    ringkas
                    :upload-url="URL_GAMBAR"
                    :upload-data="{ tahapId: detailKandidat?.tahapId }"
                    placeholder="mis. Kuota sudah turun, proses dilanjutkan."
                />
            </div>
        </ConfirmModal>

        <transition name="plw-toast"><div v-if="toast" class="plw-toast" :class="{ 'is-err': toastErr }"><i class="bi" :class="toastErr ? 'bi-exclamation-circle-fill' : 'bi-check-circle-fill'"></i> {{ toast }}</div></transition>
    </div>
</template>

<script>
import axios from 'axios';
import { Head, router } from '@inertiajs/vue3';
import BerkasAktivitas from '@career/BerkasAktivitas.vue';
import ConfirmModal from '@career/ConfirmModal.vue';
import EditorQuill from '@career/EditorQuill.vue';
import KontenAman from '@career/KontenAman.vue';
import { labelField } from '@career/formulir';

const CFG = { headers: { Accept: 'application/json' } };

/**
 * Penerima gambar yang ditanam di dalam catatan berformat.
 *
 * Bentuk URL-nya dikunci App\Support\Career\HtmlBersih — hanya tautan berpola
 * ini yang lolos penyaring saat catatannya disimpan.
 */
const URL_GAMBAR = '/api/v1/karir/lamaran/catatan/gambar';

/**
 * HTML catatan → panjang teksnya saja.
 *
 * Gerbang "alasan minimal 10 karakter" harus menghitung yang DIBACA MANUSIA.
 * Menghitung panjang HTML mentah membuat "<p>ok</p>" lolos dengan 9 karakter
 * markup — gerbangnya jadi hiasan.
 */
function teksDariHtml(html) {
    const el = document.createElement('div');
    el.innerHTML = html || '';
    // Gambar dihitung sebagai isi: catatan berupa foto lembar penilaian memang
    // tidak punya satu huruf pun, tapi jelas bukan catatan kosong.
    const punyaGambar = !!el.querySelector('img');
    const teks = (el.textContent || '').replace(/\s+/g, ' ').trim();

    return punyaGambar && teks.length < 10 ? '(catatan berupa gambar)' : teks;
}

/**
 * Pilihan status MCU + nada warnanya.
 *
 * Hasil pemeriksaan kesehatan tidak cukup "lulus / gagal": keadaan tengah —
 * boleh bekerja dengan catatan — justru yang paling sering, dan itulah yang
 * menentukan penempatan saat onboarding.
 */

export default {
    // Halaman ini merender BEBERAPA simpul akar (konten + modal + lightbox yang
    // di-teleport), sehingga Vue tidak tahu ke mana harus menempelkan atribut
    // bawaan Inertia (errors, auth, flash, ...). Dimatikan supaya peringatan
    // "Extraneous non-props attributes" berhenti — atribut itu memang tidak
    // dipakai sebagai atribut HTML di sini.
    inheritAttrs: false,
    components: { Head, BerkasAktivitas, ConfirmModal, EditorQuill, KontenAman },
    props: {
        talent: { type: Array, default: () => [] },
        programAwal: { type: Object, default: () => ({ data: [], page: 1, totalPage: 1, total: 0 }) },
        // Master hasil keputusan — sumber tombol di footer drawer.
        hasilKeputusan: { type: Array, default: () => [] },
        // Bentuk pelaksanaan jadwal (daring / tatap muka / telepon) — dari
        // master, sumber yang sama dengan hasilKeputusan.
        modeJadwal: { type: Array, default: () => [] },
        // Status hasil MCU (Fit / Fit with Note / Temporary Unfit / Unfit) —
        // dari master. Dulu tiga nilai ditulis mati di berkas ini, dan yang
        // hilang justru "belum layak sementara": kandidat yang cuma perlu
        // diperiksa ulang terpaksa dicatat Unfit, yang berarti gugur permanen.
        mcuStatusOpsi: { type: Array, default: () => [] },
        // Jawaban kandidat atas penawaran (setuju / menolak / mundur) — dari
        // master. Dicatat TIM saat menandai kehadiran negosiasi; tombolnya di
        // portal kandidat sudah dicabut.
        jawabanPenawaran: { type: Array, default: () => [] },
    },
    data() {
        return {
            // Sama dengan batas di LamaranController::putus().
            MIN_ALASAN: 10,
            URL_GAMBAR,
            programs: this.programAwal.data || [],
            page: this.programAwal.page || 1,
            totalPage: this.programAwal.totalPage || 1,
            total: this.programAwal.total || 0,
            // Disemai dari URL supaya tautan dari Dashboard ("Butuh Aksi")
            // mendarat langsung pada program yang dimaksud, bukan di daftar
            // penuh. Keduanya param yang memang sudah diterima worklistProgram.
            q: new URLSearchParams(window.location.search).get('q') || '',
            jenis: new URLSearchParams(window.location.search).get('jenis') || '',
            loadingProg: false,
            selectedId: null,
            detail: { program: null, posisi: [], kolom: [], pelamar: [] },
            loadingDetail: false,
            statusTab: 'AKTIF',
            detailKandidat: null,
            profil: { lamaran: null, formulir: [] },
            loadingProfil: false,
            berkasHasil: [],
            tahapBerkasId: null,
            berkasBusy: false,
            openForm: 0,
            // ── CETAK LAPORAN ───────────────────────────────────────────────
            laporanShow: false,
            laporanFormat: 'PDF',
            laporanOpsi: [],
            laporanFormulir: [],
            laporanProses: false,
            laporanSiap: false,
            laporanUrl: '',
            laporanGagal: '',
            laporanTimer: null,
            // Peta label per komponen formulir — dihitung sekali, dipakai
            // berkali-kali (drawer dibuka-tutup terus sepanjang hari).
            cacheLabel: {},
            // Aktivitas yang panel "Catatan & berkas"-nya sedang dibuka.
            // SATU saja: membuka semuanya sekaligus mengembalikan dinding teks
            // yang justru ingin dihindari, dan drawer-nya hanya selebar itu.
            detailTes: null,
            lightbox: null,
            lbSrc: '',
            lbTimer: null,
            lbLoading: false,
            lbError: false,
            konfirmShow: false,
            putusTarget: null,
            putusHasil: '',
            putusSetuju: false, // centang wajib sebelum menggugurkan
            // Centang "saya sadar aktivitas belum selesai" — terpisah dari yang
            // di atas karena keduanya mengakui hal yang berbeda.
            putusSetujuAktivitas: false,
            // Alasan keputusan ditulis di editor berformat. Ringkasan polosnya
            // DITURUNKAN saat dikirim, tidak disimpan sebagai state kedua —
            // dua salinan yang bisa berselisih hanya menunggu giliran basi.
            putusCatatanHtml: '',
            // Kandidat yang mundur disimpan di Talent Pool atau tidak. Hanya
            // ditanyakan untuk hasil ber-`pilihTalentPool`; nilainya disemai
            // dari bawaan masternya saat modal dibuka.
            putusTalentPool: true,
            putusTanggal: '',
            catatShow: false,
            catatTarget: null,
            catatHasil: 'LULUS',
            mcuStatus: 'FIT',
            mcuPenyedia: '',
            mcuTanggal: '',
            mcuCatatan: '',
            jawabPenawaran: '',
            jadwalShow: false,
            jadwalTarget: null,
            jadwalMode: 'DARING',
            jadwalMulai: '',
            jadwalSelesai: '',
            jadwalLink: '',
            jadwalKontak: '',
            jadwalLokasi: '',
            jadwalLokasiId: null,
            daftarLokasi: [],
            lokasiLoading: false,
            jadwalCatatan: '',
            hadirShow: false,
            hadirTarget: null,
            hadirNilai: 'Y',
            hadirCatatan: '',
            // Catatan penilaian saat kandidat HADIR — ditulis di editor
            // berformat, boleh memuat gambar lembar penilaian.
            hadirCatatanHtml: '',
            // Hasil & nilai ikut di jendela Hadir — satu tindakan, bukan dua.
            hadirHasil: 'LULUS',
            hadirNilaiSkor: null,
            hadirNilaiTeks: '',
            catatNilai: null,
            catatCatatanHtml: '',
            // Berkas aktivitas diunggah LANGSUNG saat dipilih, bukan saat modal
            // disimpan. Bila admin lalu menekan Batal, layar besar masih
            // menampilkan keadaan lama — penanda ini yang memicu muat ulang.
            berkasAktivitasBerubah: false,
            // ── TAHAN (HOLD) ────────────────────────────────────────────────
            holdShow: false,
            holdNilai: true, // true = menahan, false = melepaskan
            holdAlasan: '',
            holdCatatanHtml: '',
            // Daftar alasan dari Master Alasan Hold — dimuat sekali per sesi.
            alasanHold: [],
            // Kandidat yang sedang ditahan/dilepas dari KARTU (bukan drawer).
            holdTarget: null,
            // ── PENYARING PAPAN ─────────────────────────────────────────────
            cariKandidat: '',
            keadaanPilih: '',
            // ── PILIH BANYAK & JADWAL MASSAL ────────────────────────────────
            // Kunci kolom yang sedang dalam mode pilih — HANYA SATU kolom pada
            // satu waktu. Penjadwalan massal selalu menyangkut satu tahap, jadi
            // memilih lintas kolom hanya membuka jalan memilih orang-orang yang
            // aktivitasnya berbeda lalu bingung kenapa sebagian dilewati.
            kolomPilih: '',
            terpilih: [], // id lamaran (hashid)
            // ── PENYARING PER KOLOM ─────────────────────────────────────────
            // { [kunciKolom]: { q, keadaan, urut } } — dibuat saat kolomnya
            // pertama kali disentuh, bukan disiapkan untuk semua kolom sekaligus.
            filterKolom: {},
            filterBuka: '', // kunci kolom yang panel penyaringnya sedang terbuka
            massalShow: false,
            massalAktivitas: '',
            massalMode: 'LURING',
            massalPola: 'BERGILIR',
            massalMulai: '',
            massalDurasi: 30,
            massalJeda: 0,
            massalLink: '',
            massalLokasi: '',
            massalLokasiId: null,
            massalCatatan: '',
            sibuk: false,
            // Id aktivitas yang sedang ditarik hasilnya dari HCLearn.
            sinkronId: null,
            // Aktivitas yang sedang dibuka jalannya (tombol "Lanjutkan").
            lanjutId: null,
            // Konfirmasi "Tidak hadir" — aksi final, tak boleh sekali klik.
            absenShow: false,
            absenTarget: null,
            absenCatatan: '',
            toast: '',
            toastErr: false,
            tm: null,
            cariTm: null,
            posisiPilih: '', // '' = semua lowongan
        };
    },
    // Interval pemantau laporan hidup di luar siklus Vue — tanpa dibersihkan,
    // ia terus menembak API setelah halaman ditinggalkan.
    beforeUnmount() {
        clearInterval(this.laporanTimer);
    },
    watch: {
        // Modal ditutup = berhenti bertanya. Admin yang menutupnya sudah tidak
        // menunggu jawabannya, dan berkasnya tetap tersimpan bila memang jadi.
        laporanShow(buka) {
            if (!buka) clearInterval(this.laporanTimer);
        },
        // Pilihan DIKOSONGKAN saat papan berganti isi.
        //
        // Tanpa ini, kandidat yang terpilih lalu tersaring keluar tetap terbawa
        // diam-diam: penghitungnya bilang "12 terpilih" sementara hanya 3 yang
        // terlihat, dan penjadwalan massal mengundang sembilan orang yang tidak
        // sedang dilihat siapa pun.
        statusTab() { this.terpilih = []; },
        posisiPilih() { this.terpilih = []; },
        keadaanPilih() { this.terpilih = []; },
        // Ganti program = papan yang sama sekali lain. Penyaring kolom milik
        // program lama tidak boleh ikut, karena kunci kolomnya bisa kebetulan
        // sama (dua alur sama-sama punya tahap "Psikotes") dan kolom yang baru
        // dibuka akan langsung tersaring tanpa ada yang menyalakannya.
        selectedId() { this.tutupPilihKolom(); this.filterKolom = {}; this.filterBuka = ''; },
    },
    computed: {
        // Terminal "tidak lanjut" = GUGUR atau TALENT_POOL. Keduanya keluar dari
        // tab Berjalan dan berkumpul di tab Tidak Lolos (dengan badge berbeda).
        pelamarTampil() {
            const terminal = (r) => r.statusLamaran === 'GUGUR' || r.statusLamaran === 'TALENT_POOL';
            const q = this.cariKandidat.trim().toLowerCase();

            return (this.detail.pelamar || [])
                .filter((r) => {
                    // DITAHAN adalah tab tersendiri, bukan bagian dari
                    // "Berjalan": mereka memang masih berjalan, tapi justru itu
                    // masalahnya — tercampur di sana, orang yang sengaja
                    // disisihkan tenggelam dan tak pernah ditinjau lagi.
                    if (this.statusTab === 'HOLD') return !!r.hold;
                    if (this.statusTab === 'GUGUR') return terminal(r);

                    return !terminal(r) && !r.hold;
                })
                // Penyaring lowongan berlaku untuk SEMUA tab, supaya angka
                // "berjalan" dan "tidak lolos" satu lowongan bisa dibandingkan.
                .filter((r) => !this.posisiPilih || r.posisiId === this.posisiPilih)
                .filter((r) => !q
                    || (r.pelamar || '').toLowerCase().includes(q)
                    || (r.lamaranKode || '').toLowerCase().includes(q))
                .filter((r) => {
                    // Keadaan menjawab "mana yang menunggu SAYA?" — pertanyaan
                    // yang tanpa ini dijawab dengan memindai lencana kartu satu
                    // per satu.
                    switch (this.keadaanPilih) {
                        case 'PERLU': return !!r.butuhKeputusan && !r.hold;
                        case 'NUNGGU': return !!r.nungguSistem;
                        case 'HOLD': return !!r.hold;
                        // Punya aktivitas yang menuntut jadwal tapi belum ada
                        // jadwalnya — inilah antrean kerja penjadwalan massal.
                        case 'JADWAL': return (r.tests || []).some((t) => t.butuhJadwal && !t.jadwal);
                        default: return true;
                    }
                });
        },
        jmlAktif() { return (this.detail.pelamar || []).filter((r) => r.statusLamaran !== 'GUGUR' && r.statusLamaran !== 'TALENT_POOL' && !r.hold).length; },
        jmlGugur() { return (this.detail.pelamar || []).filter((r) => r.statusLamaran === 'GUGUR' || r.statusLamaran === 'TALENT_POOL').length; },
        jmlHold() { return (this.detail.pelamar || []).filter((r) => !!r.hold).length; },
        adaFilter() { return !!(this.posisiPilih || this.keadaanPilih || this.cariKandidat.trim()); },

        /* ── PILIH BANYAK & JADWAL MASSAL ──────────────────────────────────── */
        /** Kandidat terpilih yang masih ADA di papan (penyaring bisa berubah). */
        barisTerpilih() {
            return this.pelamarTampil.filter((r) => this.terpilih.includes(r.id));
        },
        /**
         * Aktivitas yang bisa dijadwalkan massal, dikelompokkan per NAMA.
         *
         * Dikelompokkan per nama — bukan per id — karena tiap kandidat punya
         * baris aktivitasnya sendiri; yang sama di antara mereka hanyalah
         * namanya ("Wawancara HR"). Itulah yang dipilih admin.
         */
        aktivitasTerpilih() {
            const peta = new Map();
            for (const r of this.barisTerpilih) {
                for (const t of r.tests || []) {
                    if (!t.butuhJadwal || t.selesai || t.terkunci) continue;
                    const k = t.label;
                    if (!peta.has(k)) peta.set(k, { label: k, jumlah: 0, wajibLuring: false });
                    const a = peta.get(k);
                    a.jumlah++;
                    a.wajibLuring = a.wajibLuring || !!t.wajibLuring;
                }
            }

            return [...peta.values()].sort((a, b) => b.jumlah - a.jumlah);
        },
        massalWajibLuring() {
            return !!this.aktivitasTerpilih.find((a) => a.label === this.massalAktivitas)?.wajibLuring;
        },
        /** Pasangan kandidat→aktivitas untuk nama aktivitas yang dipilih. */
        sasaranMassal() {
            return this.barisTerpilih
                .map((r) => ({
                    r,
                    t: (r.tests || []).find((x) => x.label === this.massalAktivitas && x.butuhJadwal && !x.selesai && !x.terkunci),
                }))
                .filter((x) => !!x.t);
        },
        massalDilewati() {
            return this.barisTerpilih.filter((r) => !(r.tests || []).some(
                (x) => x.label === this.massalAktivitas && x.butuhJadwal && !x.selesai && !x.terkunci,
            ));
        },
        /** Siapa dapat jam berapa — dihitung dengan rumus yang SAMA dengan server. */
        pratinjauMassal() {
            if (!this.massalMulai || !this.massalAktivitas) return [];
            const awal = new Date(String(this.massalMulai).replace(' ', 'T'));
            if (Number.isNaN(awal.getTime())) return [];
            const langkah = (Number(this.massalDurasi) || 30) + (Number(this.massalJeda) || 0);

            return this.sasaranMassal.map((x, i) => {
                const d = this.massalPola === 'BERGILIR'
                    ? new Date(awal.getTime() + i * langkah * 60000)
                    : awal;

                return {
                    id: x.r.id,
                    pelamar: x.r.pelamar,
                    jam: d.toLocaleString('id-ID', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' }),
                };
            });
        },
        bolehSimpanMassal() {
            if (!this.massalAktivitas || !this.massalMulai || !this.sasaranMassal.length) return false;

            return this.massalMode === 'DARING' ? !!this.massalLink.trim() : !!this.massalLokasiId;
        },
        /** Definisi hasil yang sedang dipilih — semua labelnya dari master. */
        putusDef() { return this.hasilKeputusan.find((h) => h.kode === this.putusHasil) || null; },
        /**
         * Tahap yang sedang diputus adalah TITIK TUNTAS?
         *
         * Di titik itu "Loloskan" bukan lagi meneruskan ke tahap berikutnya —
         * kandidat DITERIMA bekerja. Kata yang dipakai ikut berubah, karena
         * "Loloskan" pada langkah terakhir terbaca seolah masih ada lanjutannya.
         */
        putusTuntas() {
            // DARI KANDIDATNYA SENDIRI, bukan dari kolom master.
            //
            // Dulu ini mencari kolom yang NOMOR URUT-nya sama lalu membaca flag
            // di sana. Nomor urut cuma benar selama alur tak pernah berubah:
            // begitu alur disunting (baris dipakai ulang per urutan) atau
            // program diarahkan ke alur lain, "kolom ke-6" bisa tahap yang sama
            // sekali berbeda — dan tombol "Loloskan" berubah arti diam-diam
            // menjadi "Terima Kandidat", atau sebaliknya.
            //
            // `perilaku` dibekukan bersama tahapnya, jadi tetap benar apa pun
            // yang terjadi pada master sesudahnya.
            return !!this.putusTarget?.perilaku?.tuntas;
        },
        putusJudul() {
            if (!this.putusDef) return 'Keputusan';

            return this.putusDef.lolos && this.putusTuntas
                ? 'Terima Kandidat — Diterima Bekerja'
                : `${this.putusDef.labelTombol} — ${this.putusDef.nama}`;
        },
        putusLabelKonfirm() {
            if (this.putusDef?.lolos && this.putusTuntas) return 'Ya, Terima Kandidat';

            return this.putusDef?.labelKonfirmasi || 'Ya, Lanjutkan';
        },
        /** Tahap aktif kandidat membawa penawaran yang harus dijawab? */
        tahapPenawaran() {
            return !!this.detailKandidat?.perilaku?.penawaran;
        },
        /** Tahap aktif adalah titik tuntas — meloloskan di sini = diterima bekerja. */
        tahapTuntas() {
            return !!this.detailKandidat?.perilaku?.tuntas;
        },
        /**
         * Keputusan PERUSAHAAN yang boleh muncul untuk kandidat ini.
         *
         * Talent Pool hanya pada tahap yang memang di-cut-off ke sana, dan
         * Loloskan hilang saat kuota tahap akhir sudah penuh — dua aturan yang
         * sudah ada sebelumnya, kini diterapkan pada daftar dari master.
         */
        putusanPerusahaan() {
            if (!this.detailKandidat) return [];

            return this.hasilKeputusan.filter((h) => {
                if (h.olehKandidat) return false;
                if (h.lolos) return !this.kuotaBlokir(this.detailKandidat);
                if (h.talentPool && !h.kirimEmail) return this.bolehTalentPool(this.detailKandidat);

                return true;
            });
        },
        /**
         * Keputusan yang datangnya DARI KANDIDAT.
         *
         * "Menolak penawaran" hanya masuk akal bila memang ada penawaran, jadi
         * ia mengikuti penanda tahap dari Master Tipe Tahap. "Mengundurkan diri"
         * berlaku di tahap mana pun — kandidat bisa mundur kapan saja.
         */
        putusanKandidat() {
            if (!this.detailKandidat) return [];

            // SUDAH DICATAT DI MODAL "TANDAI HADIR" → tidak diulang di sini.
            //
            // Tahap yang punya aktivitas penawaran BERJADWAL (negosiasi telepon)
            // menangkap jawaban kandidat di modal kehadirannya, lengkap dengan
            // alasan dan penutupan lamarannya. Menyisakan tombol yang sama di
            // sini berarti dua jalan menuju keadaan yang sama — dan admin harus
            // menebak mana yang benar.
            //
            // Yang TIDAK berjadwal (Surat Penawaran / `infoSaja`) tidak punya
            // modal kehadiran sama sekali, jadi tombolnya tetap dibutuhkan.
            if (this.jawabanDiCatatKehadiran) return [];

            // Pada tahap berpenawaran, TIDAK ADA jawaban kandidat yang masuk akal
            // sebelum penawarannya benar-benar diajukan — belum ada yang bisa
            // ditolak. Di tahap lain, mundur tetap boleh kapan saja.
            if (this.tahapPenawaran && !this.detailKandidat.penawaranDiajukan) return [];

            return this.hasilKeputusan.filter(
                (h) => h.olehKandidat && (this.tahapPenawaran || h.kode !== 'DITOLAK_KANDIDAT'),
            );
        },
        /**
         * Jawaban kandidat ditangkap di modal kehadiran, bukan di tombol tahap?
         *
         * Benar bila tahap aktif punya aktivitas penawaran yang BERJADWAL —
         * `infoSaja` menandai penawaran berdokumen (surat penawaran) yang tidak
         * punya jendela kehadiran, jadi ia tidak dihitung.
         */
        jawabanDiCatatKehadiran() {
            return (this.detailKandidat?.tests || []).some((t) => t.penawaran && !t.infoSaja);
        },
        /** Tahap penawaran yang suratnya belum diajukan — tim masih menyiapkan. */
        penawaranBelumDiajukan() {
            return this.tahapPenawaran && !this.detailKandidat?.penawaranDiajukan;
        },
        /**
         * Aktivitas penawaran yang menahan gerbang — untuk ditunjuk namanya.
         * Yang berjadwal didahulukan: itulah langkah yang benar-benar bisa
         * dikerjakan sekarang (mengatur pertemuan), bukan suratnya.
         */
        aktivitasPenawaran() {
            const sisa = (this.detailKandidat?.tests || []).filter((t) => t.penawaran && !t.selesai);

            return sisa.find((t) => t.butuhJadwal) || sisa[0] || null;
        },
        /** Menggugurkan menuntut centang persetujuan dulu; yang lain langsung boleh. */
        /** DARING wajib tautan, LURING wajib lokasi; keduanya wajib waktu mulai. */
        lokasiTerpilih() {
            return this.daftarLokasi.find((l) => l.id === this.jadwalLokasiId) || null;
        },
        /** Bentuk yang boleh dipilih: tipe wajib-luring hanya menerima yang luring. */
        modeJadwalDipakai() {
            const semua = this.modeJadwal || [];

            return this.jadwalTarget?.wajibLuring ? semua.filter((m) => m.luring) : semua;
        },
        modeJadwalDef() { return (this.modeJadwal || []).find((m) => m.kode === this.jadwalMode) || null; },
        /** Syaratnya dibaca dari FLAG mode terpilih — sama persis dengan server. */
        bolehSimpanJadwal() {
            if (!this.jadwalMulai) return false;

            const m = this.modeJadwalDef;
            if (!m) return false;
            if (m.butuhTautan) return !!this.jadwalLink.trim();
            if (m.butuhLokasi) return !!this.jadwalLokasiId;
            if (m.butuhKontak) return !!this.jadwalKontak.trim();

            return true;
        },
        /** Alasan wajib? Dari master (Butuh_Alasan), bukan daftar kode di sini. */
        alasanWajib() { return !!this.putusDef?.butuhAlasan; },
        labelCatatanPutus() {
            if (!this.putusDef) return 'Catatan';
            if (this.putusDef.olehKandidat) return 'Alasan yang disampaikan kandidat';

            return this.putusDef.butuhAlasan ? `Alasan ${this.putusDef.nama}` : 'Catatan Keputusan';
        },
        placeholderCatatanPutus() {
            if (!this.putusDef) return '';
            if (this.putusDef.olehKandidat) return 'mis. sudah menerima tawaran di tempat lain';
            if (this.putusDef.talentPool && !this.putusDef.kirimEmail) return 'mis. kuat wawancara, cocok untuk Finance';

            return this.putusDef.butuhAlasan
                ? 'Dikirim sebagai dasar keputusan — tulis alasannya'
                : 'mis. sesuai rekomendasi sistem / alasan khusus';
        },
        /** Nada kartu ringkasan: hijau lolos, kuning talent pool, merah sisanya. */
        nadaPutus() {
            if (!this.putusDef) return '';
            if (this.putusDef.lolos) return 'is-lulus';

            return this.putusDef.talentPool && !this.putusDef.kirimEmail ? 'is-talent_pool' : 'is-gugur';
        },
        /**
         * Centang sadar hanya untuk keputusan yang MENUTUP proses DAN mengabari
         * kandidat — di situlah satu klik keliru tak bisa ditarik kembali.
         * Keputusan dari kandidat tidak diminta centang: yang dicatat adalah
         * kabar yang sudah terjadi, bukan tindakan yang baru akan dilakukan.
         */
        butuhCentang() { return !!this.putusDef && !this.putusDef.lolos && this.putusDef.kirimEmail && !this.putusDef.olehKandidat; },

        /**
         * Aktivitas tahap ini yang BELUM selesai.
         *
         * Dihitung dari rapor tahap yang sedang ditampilkan — sumber yang sama
         * dengan yang dibaca admin di layar, jadi angka di peringatan mustahil
         * berbeda dari daftar di atasnya.
         */
        /**
         * Aktivitas yang benar-benar MASIH MENUNGGU SESUATU.
         *
         * Dulu ukurannya `!t.selesai` — Flag_Selesai. Itu keliru untuk tahap
         * BERAKTIVITAS TUNGGAL, dan salahnya berbentuk jalan buntu:
         *
         *   Pada tahap satu aktivitas, "Catat Hasil" memang sengaja tidak ada —
         *   keputusan tahap itu sendiri yang mewakilinya (lihat `dicatatTim`).
         *   Akibatnya Flag_Selesai aktivitasnya TIDAK PERNAH bisa jadi 'Y'
         *   sebelum tahapnya diputus. Admin yang sudah menetapkan Hadir dan
         *   menulis catatan tetap disodori centang "saya sadar aktivitas ini
         *   belum selesai" — untuk sesuatu yang mustahil diselesaikan lebih
         *   dulu. Yang ia baca: sistem menganggapnya belum kerja, padahal
         *   sudah.
         *
         * Ukurannya sekarang `tuntas` — apa yang MASIH DITUNGGU dari aktivitas
         * itu, dihitung server dengan aturan yang sama persis dengan gerbang
         * keputusannya. Wawancara yang kehadirannya sudah ditetapkan dan tak
         * punya hasil terpisah untuk dicatat = tuntas, dan tak ada yang perlu
         * diakui.
         */
        aktivitasBelumSelesai() {
            return (this.putusTarget?.tests || []).filter((t) => !(t.tuntas ?? t.selesai));
        },
        /**
         * Perlu pengakuan sadar sebelum memutus?
         *
         * TIDAK berlaku bila seluruh aktivitas sudah selesai — di situ tak ada
         * yang perlu diakui, dan centang yang selalu muncul akan berhenti
         * dibaca lalu dicentang refleks.
         *
         * Juga tidak berlaku untuk keputusan yang datang DARI KANDIDAT: orang
         * yang mengundurkan diri tidak akan menyelesaikan sisa aktivitasnya,
         * jadi menahan admin dengan peringatan itu tak ada gunanya.
         */
        perluCentangAktivitas() {
            return !!this.putusDef
                && !this.putusDef.olehKandidat
                && this.aktivitasBelumSelesai.length > 0;
        },
        hariIni() { return new Date().toISOString().slice(0, 10); },
        /**
         * Alasan dianggap terisi bila melewati batas minimum, bukan sekadar
         * tidak kosong. "-" atau "ok" lolos uji "tidak kosong" tapi tidak
         * menjelaskan apa pun saat riwayat ini dibaca berbulan-bulan kemudian.
         */
        /**
         * Panjang alasan dihitung dari TEKSNYA, bukan dari HTML mentah.
         * "<p>ok</p>" berisi 9 karakter markup — cukup untuk lolos batas 10
         * kalau yang dihitung panjang string apa adanya, padahal isinya "ok".
         */
        panjangAlasanPutus() { return teksDariHtml(this.putusCatatanHtml).length; },
        catatanCukup() { return this.panjangAlasanPutus >= this.MIN_ALASAN; },
        bolehKonfirmPutus() {
            if (!this.putusDef) return false;

            if (this.alasanWajib && !this.catatanCukup) return false;

            // Aktivitas yang belum selesai harus diakui lebih dulu.
            if (this.perluCentangAktivitas && !this.putusSetujuAktivitas) return false;

            return !this.butuhCentang || this.putusSetuju;
        },
        /** Aktivitas yang dikerjakan tim: catatan bebas, tak ada syarat tambahan. */
        bolehSimpanCatat() { return !!this.catatTarget; },
        /**
         * Aktivitas PENENTU yang dikerjakan tim wajib membawa verdict.
         *
         * Digerbangi di layar juga, bukan hanya di server: tanpa hasil, server
         * hanya mencatat kehadiran dan aktivitasnya tetap menggantung — admin
         * mengira sudah selesai padahal tahapnya masih menunggu.
         */
        bolehSimpanHadir() {
            if (this.hadirNilai !== 'Y') return true;
            // MCU: verdict-nya DITURUNKAN dari status kesehatan, jadi yang
            // dituntut status itu — bukan pilihan Lulus/Gagal terpisah yang
            // bisa berselisih dengannya ("Unfit" tapi ditandai lulus).
            if (this.hadirTarget?.isMcu) return !this.mcuKurangHadir;
            // Penawaran: verdict-nya diturunkan dari jawaban kandidat.
            if (this.hadirTarget?.penawaran) return !this.jawabKurang;
            if (!this.hadirTarget?.dinilaiTim) return true;

            return this.hadirTarget.peran === 'INFORMATIF' || !!this.hadirHasil;
        },

        /* ── TAHAN (HOLD) ──────────────────────────────────────────────────── */
        /**
         * Seluruh tombol keputusan terkunci selama kandidat ditahan.
         *
         * Gerbangnya ada juga di server (LamaranService::ketukPalu) — ini hanya
         * supaya admin melihat sebabnya, bukan tombol yang ditekan lalu ditolak.
         */
        /** Aktivitas tahap aktif yang masih menunggu sesuatu — dari server. */
        belumTuntas() { return this.detailKandidat?.belumTuntas || []; },
        /**
         * KUNCI PER-TOMBOL, bukan satu kunci untuk semuanya.
         *
         * Dulu satu syarat mematikan SELURUH tombol keputusan. Akibatnya dua
         * arah salah sekaligus: "Tidak Lolos" dan "Mengundurkan Diri" ikut mati
         * padahal keduanya justru paling dibutuhkan saat segalanya belum
         * lengkap — sementara "Loloskan" tetap hidup untuk aktivitas yang BELUM
         * PERNAH DIJADWALKAN, karena syaratnya (kehadiran) mensyaratkan
         * jadwalnya sudah ada lebih dulu.
         *
         * Sekarang tiap hasil membawa sikapnya sendiri dari master
         * (`butuhTuntas`), dan server menolak dengan aturan yang sama persis.
         */
        terkunciPutus() {
            return (h) => {
                if (this.detailKandidat?.hold) {
                    return 'Kandidat sedang ditahan — tekan "Lanjutkan" dulu untuk melepasnya.';
                }
                if (h?.butuhTuntas && this.belumTuntas.length) {
                    const rinci = this.belumTuntas.map((x) => `${x.label} (${x.sebab})`).join(', ');

                    return `Belum bisa: ${rinci}. Selesaikan dulu di Rapor Tes di atas.`;
                }

                return '';
            };
        },
        /** Definisi alasan terpilih — sumber aturan "butuh keterangan". */
        holdAlasanDef() { return this.alasanHold.find((a) => a.value === this.holdAlasan) || null; },
        holdButuhCatatan() { return !!this.holdAlasanDef?.butuhCatatan; },
        holdCatatanCukup() { return teksDariHtml(this.holdCatatanHtml).trim() !== ''; },
        bolehSimpanHold() {
            // Melepas tahan tidak menuntut apa pun — menahan menuntut alasan,
            // dan alasan tertentu menuntut keterangannya.
            if (!this.holdNilai) return true;
            if (!this.holdAlasan) return false;

            return !this.holdButuhCatatan || this.holdCatatanCukup;
        },
        // KEDUA tab memakai kolom tahap alur yang sama. Kandidat gugur tetap
        // "diam" di tahap tempat ia gugur (backend mengirim kolomUrutan dari
        // tahap ber-Hasil GUGUR), bukan ditumpuk jadi satu kolom — supaya admin
        // langsung melihat DI TAHAP MANA kandidat paling banyak berguguran.
        kolomTampil() {
            return this.detail.kolom || [];
        },
    },
    async mounted() {
        // programAwal dari server dihitung TANPA saringan, jadi kalau URL
        // membawa q/jenis daftarnya diambil ulang dulu — kalau tidak, yang
        // terpilih otomatis adalah program pertama dari daftar penuh, bukan
        // yang ditunjuk tautan Dashboard. Menunggu (await) sebelum memilih,
        // karena muatProgram() sendiri tidak memilih apa pun.
        if (this.q || this.jenis) {
            this.page = 1;
            await this.muatProgram();
        }
        if (this.programs.length) this.pilihProgram(this.programs[0]);
    },
    methods: {
        /**
         * Label satu isian: dari SKEMA formulir bila dikenali, kalau tidak dari
         * tebakan server atas nama kuncinya.
         *
         * Yang tersimpan di database cuma kunci→jawaban; labelnya hidup di
         * skema. Tanpa pencarian ini, peninjau membaca "V Nama" dan "V Wa" —
         * nama kunci yang dirapikan seadanya, bukan pertanyaan yang benar-benar
         * dilihat kandidat saat mengisi.
         */
        labelIsian(form, isian) {
            return this.petaLabel(form.komponen)[isian.key] || isian.label;
        },
        /** Peta label per komponen, dihitung sekali lalu disimpan. */
        petaLabel(kode) {
            if (!kode) return {};
            this.cacheLabel[kode] ??= labelField(kode);

            return this.cacheLabel[kode];
        },
        /**
         * Isian ini memang berupa berkas?
         *
         * Dinilai dari SKEMA (tipe field), bukan dari ada-tidaknya berkas yang
         * terunggah — kalau dari berkasnya, isian dokumen yang KOSONG akan
         * terbaca sebagai isian teks biasa dan tampil "—". Padahal justru
         * kekosongan itulah yang perlu terlihat sebagai "Belum ada".
         */
        isianBerkas(isian) {
            return !!isian.berkas || /^(dok|file|berkas|upload)_/i.test(isian.key || '');
        },
        /**
         * Isian yang layak memakai satu baris penuh.
         *
         * Diukur dari PANJANG JAWABANNYA, bukan dari daftar kunci: alamat,
         * uraian pengalaman, dan alasan melamar sama-sama panjang tapi namanya
         * berbeda di tiap formulir. Ambangnya kasar dan memang cukup — yang
         * dihindari hanya paragraf yang terjepit di kolom setipis dua kata.
         */
        isianPanjang(isian) {
            if (this.isianBerkas(isian)) return false;
            // Isian BERULANG selalu selebar penuh — satu baris riwayat kerja
            // memuat perusahaan, jabatan, periode, dan uraian sekaligus.
            if (isian.baris && isian.baris.length) return true;

            return String(isian.nilai ?? '').length > 60;
        },
        inisial(n) { return (n || '?').split(' ').slice(0, 2).map((s) => s[0]).join('').toUpperCase(); },
        katLabel(k) { return { REKRUTMEN: 'Rekrutmen', MT: 'Management Trainee', INTERNSHIP: 'Internship / Magang' }[k] || k || '—'; },
        statusLabel(s) { return { BERJALAN: 'Berjalan', LULUS: 'Diterima', GUGUR: 'Tidak Lolos', TALENT_POOL: 'Talent Pool' }[s] || s; },
        tglId(v) {
            if (!v) return '—';
            const d = new Date(String(v).replace(' ', 'T'));
            return Number.isNaN(d.getTime()) ? '—' : d.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
        },
        /** Sudah berapa lama sejak melamar — penanda kandidat yang terlalu lama menunggu. */
        umurHari(v) {
            if (!v) return '';
            const d = new Date(String(v).replace(' ', 'T'));
            if (Number.isNaN(d.getTime())) return '';
            const hari = Math.max(0, Math.floor((Date.now() - d.getTime()) / 86400000));
            if (hari === 0) return 'hari ini';
            if (hari < 30) return `${hari} hari`;
            const bulan = Math.floor(hari / 30);
            return `${bulan} bln`;
        },
        aksen(p) { return p.warna || (p.kategori === 'MT' ? '#f59e0b' : '#6366f1'); },
        avatarBg(r) {
            if (r.statusLamaran === 'GUGUR') return 'linear-gradient(135deg,#f87171,#ef4444)';
            if (r.statusLamaran === 'TALENT_POOL') return 'linear-gradient(135deg,#fbbf24,#d97706)';
            if (r.statusLamaran === 'LULUS') return 'linear-gradient(135deg,#34d399,#10b981)';
            if (r.nungguSistem) return 'linear-gradient(135deg,#fbbf24,#f59e0b)';
            return 'linear-gradient(135deg,#8b5cf6,#6366f1)';
        },
        /**
         * Tahap aktif kandidat ini di-cut-off ke Talent Pool?
         *
         * Dibaca dari salinan tahap MILIK KANDIDAT, bukan dari kolom master.
         * Menggeser cut-off di Master Alur tidak boleh mengubah pilihan yang
         * ditawarkan untuk orang yang sudah berjalan — apalagi menawarkan
         * Talent Pool pada tahap yang saat mereka melamar belum termasuk.
         */
        bolehTalentPool(r) {
            return !!(r && (r.perilaku?.talentPool ?? r.bolehTalentPool));
        },
        /** Loloskan diblokir bila kuota penuh DAN kandidat di tahap terakhir. */
        kuotaBlokir(r) { return !!(r && r.kuotaPenuh && r.diTahapAkhir); },
        /**
         * Nada tombol keputusan — DARI FLAG master, bukan dari kodenya.
         * Hasil baru yang ditambahkan lewat master ikut dapat warna yang masuk
         * akal tanpa satu baris pun disentuh di sini.
         */
        kelasPutus(h) {
            if (h.lolos) return 'is-lolos';

            return h.talentPool && !h.kirimEmail ? 'is-talent' : 'is-gugur';
        },
        /** "Loloskan" jadi "Terima" pada tahap yang menutup proses. */
        labelPutus(h) {
            return h.lolos && this.tahapTuntas ? 'Terima' : h.labelTombol;
        },
        /**
         * Waktu terbaca manusia. Dipakai keterangan "dinyatakan lengkap pada …".
         *
         * String kosong bila tak bisa dibaca — BUKAN "Invalid Date", yang di
         * dalam tooltip terbaca sebagai kerusakan sistem padahal hanya berarti
         * kolomnya memang belum terisi.
         */
        fmtWaktu(v) {
            if (!v) return '';
            const d = new Date(String(v).replace(' ', 'T'));

            return Number.isNaN(d.getTime())
                ? ''
                : d.toLocaleString('id-ID', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
        },
        ukuran(b) {
            if (!b) return '';
            return b > 1048576 ? (b / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(b / 1024)) + ' KB';
        },
        /** Kartu pada satu kolom tahap — berlaku sama untuk tab Berjalan & Tidak Lolos. */
        /**
         * Kartu satu kolom — setelah penyaring GLOBAL, lalu penyaring KOLOM.
         *
         * Dua lapis, bukan satu. Penyaring global menjawab "papan ini sedang
         * membahas siapa"; penyaring kolom menjawab "di tahap ini, siapa yang
         * saya kerjakan sekarang". Satu tahap Psikotes bisa berisi 200 orang
         * sementara tahap sesudahnya berisi 3 — memaksa keduanya ikut satu
         * penyaring berarti menyaring tahap yang tidak perlu disaring.
         */
        kartuKolom(col) {
            const f = this.filterKolom[this.kunciKolom(col)];
            // PENEMPATAN PER IDENTITAS TAHAP, bukan per nomor urut.
            //
            // Server sudah memutuskan kolom mana milik siapa (AlurKolom::cocok)
            // dan mengirimnya sebagai `kolomKode`. Mencocokkan nomor di sini
            // akan mengulang anggapan lama — bahwa tahap ke-N kandidat sama
            // dengan kolom ke-N papan — yang runtuh begitu alur disunting atau
            // program dialihkan ke alur lain.
            //
            // Cadangan ke nomor hanya untuk muatan lama yang belum membawa
            // kolomKode (mis. tab yang sudah lama terbuka lalu difilter ulang).
            let baris = this.pelamarTampil.filter((r) => (
                r.kolomKode != null && col.kode != null
                    ? r.kolomKode === col.kode
                    : r.kolomUrutan === col.urutan
            ));

            if (f?.q) {
                const q = f.q.trim().toLowerCase();
                baris = baris.filter((r) => (r.pelamar || '').toLowerCase().includes(q)
                    || (r.lamaranKode || '').toLowerCase().includes(q)
                    || (r.posisi || '').toLowerCase().includes(q));
            }

            if (f?.keadaan) {
                baris = baris.filter((r) => {
                    switch (f.keadaan) {
                        case 'PERLU': return !!r.butuhKeputusan && !r.hold;
                        case 'NUNGGU': return !!r.nungguSistem;
                        case 'JADWAL': return (r.tests || []).some((t) => t.butuhJadwal && !t.jadwal);
                        case 'TERJADWAL': return (r.tests || []).some((t) => !!t.jadwal && !t.selesai);
                        case 'HOLD': return !!r.hold;
                        default: return true;
                    }
                });
            }

            // Bawaannya TERLAMA DULU — yang paling lama menunggu adalah yang
            // paling mendesak, dan itu yang harus terbaca lebih dulu tanpa
            // perlu menggulung sampai dasar kolom.
            const urut = f?.urut || 'LAMA';
            const waktu = (r) => new Date(String(r.waktuLamar || '').replace(' ', 'T')).getTime() || 0;

            return [...baris].sort((a, b) => {
                if (urut === 'NAMA') return (a.pelamar || '').localeCompare(b.pelamar || '');
                if (urut === 'BARU') return waktu(b) - waktu(a);

                return waktu(a) - waktu(b);
            });
        },
        /**
         * Penyaring kolom — PEMBACA MURNI, tidak pernah menulis.
         *
         * Ia dipanggil dari template (`v-model="filterKol(col).q"`), dan
         * membuat objeknya di sini berarti mengubah state reaktif DI TENGAH
         * render: Vue akan merender ulang, memanggilnya lagi, dan seterusnya.
         * Objeknya dibuat di toggleFilterKolom() — satu-satunya jalan panel ini
         * bisa terbuka, jadi ia dijamin sudah ada saat template membacanya.
         */
        filterKol(col) {
            return this.filterKolom[this.kunciKolom(col)] || { q: '', keadaan: '', urut: 'LAMA' };
        },
        toggleFilterKolom(col) {
            const k = this.kunciKolom(col);
            if (!this.filterKolom[k]) {
                this.filterKolom[k] = { q: '', keadaan: '', urut: 'LAMA' };
            }
            this.filterBuka = this.filterBuka === k ? '' : k;
        },
        /** Kolom ini sedang disaring? Dipakai menandai ikonnya. */
        kolomTersaring(col) {
            const f = this.filterKolom[this.kunciKolom(col)];

            return !!(f && (f.q || f.keadaan || (f.urut && f.urut !== 'LAMA')));
        },
        bersihkanFilterKolom(col) {
            this.filterKolom[this.kunciKolom(col)] = { q: '', keadaan: '', urut: 'LAMA' };
        },
        segKelas(n) {
            const cur = this.detailKandidat?.urutan || 1;
            if (this.detailKandidat?.statusLamaran === 'LULUS') return 'is-done';
            if (n < cur) return 'is-done';
            if (n === cur) return this.detailKandidat?.statusLamaran === 'GUGUR' ? 'is-fail' : 'is-cur';
            return '';
        },
        /* ── Panel kiri ── */
        async muatProgram() {
            this.loadingProg = true;
            try {
                const res = await axios.get('/api/v1/karir/lamaran/worklist/program', {
                    params: { page: this.page, q: this.q || undefined, jenis: this.jenis || undefined },
                    ...CFG,
                });
                const r = res.data.result;
                this.programs = r.data;
                this.page = r.page;
                this.totalPage = r.totalPage;
                this.total = r.total;
            } catch (e) {
                this.notice('Gagal memuat program.', true);
            } finally {
                this.loadingProg = false;
            }
        },
        cariDebounce() {
            clearTimeout(this.cariTm);
            this.cariTm = setTimeout(() => { this.page = 1; this.muatProgram(); }, 400);
        },
        setJenis(j) { this.jenis = j; this.page = 1; this.muatProgram(); },
        gotoPage(n) { if (n < 1 || n > this.totalPage) return; this.page = n; this.muatProgram(); },
        /* ── Panel kanan ── */
        pilihProgram(p) { this.selectedId = p.id; this.statusTab = 'AKTIF'; this.muatDetail(p.id); },
        async muatDetail(id) {
            this.loadingDetail = true;
            try {
                const res = await axios.get(`/api/v1/karir/lamaran/worklist/program/${id}`, CFG);
                this.detail = res.data.result;
                this.segarkanDrawer();
            } catch (e) {
                this.notice('Gagal memuat papan seleksi.', true);
            } finally {
                this.loadingDetail = false;
            }
        },
        goJadwal() { router.visit('/karir/penjadwalan'); },
        /* ── Drawer ── */
        async bukaKandidat(r) {
            this.detailKandidat = r;
            this.openForm = 0;
            this.profil = { lamaran: null, formulir: [] };
            this.berkasHasil = [];
            this.loadingProfil = true;
            try {
                const res = await axios.get(`/api/v1/karir/lamaran/berkas/${r.id}`, CFG);
                this.profil = res.data.result || { lamaran: null, formulir: [] };
            } catch (e) {
                this.notice('Gagal memuat berkas kandidat.', true);
            } finally {
                this.loadingProfil = false;
            }
            // Selalu dipanggil, bukan hanya saat boleh mengunggah — pemanggilan
            // inilah yang membersihkan sisa berkas tahap sebelumnya.
            this.loadBerkas(r?.tahapId || null);
        },
        /**
         * Tunjuk ulang kandidat yang sedang dibuka ke data yang BARU dimuat.
         *
         * `detailKandidat` menyimpan objek kartu hasil klik, sementara
         * muatDetail() hanya mengganti `detail`. Tanpa penunjukan ulang ini,
         * drawer tetap memegang salinan lama — hasil yang baru saja dicatat
         * tidak terlihat sampai halaman di-refresh manual, dan admin mengira
         * simpanannya gagal lalu mencatat dua kali.
         */
        segarkanDrawer() {
            if (!this.detailKandidat) return;

            const kunci = this.detailKandidat.id;
            // Dicari di daftar pelamar mentah, bukan lewat kartuKolom() yang
            // ikut tersaring tab aktif — kandidat yang baru diputus berpindah
            // tab dan akan lolos dari pencarian.
            const baru = (this.detail?.pelamar || []).find((r) => r.id === kunci);

            // Tidak ketemu = kandidat berpindah kolom/tab (mis. baru diputus).
            // Objek lama dibiarkan supaya drawer tidak tiba-tiba kosong.
            if (baru) this.detailKandidat = baru;
        },
        tutupKandidat() { this.detailKandidat = null; this.lightbox = null; this.berkasHasil = []; },
        /* ── Berkas hasil tahap (MCU/Interview) ── */
        // Keduanya dari SALINAN TAHAP milik kandidat, bukan kolom master yang
        // dicari lewat nomor urut. Ini yang paling merugikan kalau salah:
        // menyalakan "wajib unggah" di Master Alur akan mengunci kandidat yang
        // tahapnya sudah selesai dinilai — menuntut dokumen yang saat mereka
        // menjalaninya memang tidak pernah diminta.
        bolehUpload(r) {
            if (!r || !r.butuhKeputusan) return false;

            return !!r.perilaku?.uploadHasil;
        },
        wajibUpload(r) {
            return !!r?.perilaku?.wajibUpload;
        },
        /**
         * Konfirmasi "Ya, Loloskan" tertahan bila upload wajib tapi belum ada
         * berkas. Yang ditahan HANYA tombol di dalam modal — tombol Loloskan di
         * drawer tetap bisa diklik, karena unggahannya ada di modal itu sendiri.
         */
        uploadKurang(r) { return this.bolehUpload(r) && this.wajibUpload(r) && this.berkasHasil.length === 0; },
        /**
         * Ada aktivitas berjadwal yang kehadirannya belum ditetapkan.
         *
         * Inilah SATU-SATUNYA hal yang menahan tombol keputusan: selama hadir
         * atau tidak hadir sudah ditetapkan, admin boleh mengetuk palu.
         */
        /** Sudah ada isinya untuk dibuka? Menghitung ini sekali menghindari
         *  tombol "Detail" yang membuka panel kosong. */
        jumlahDetail(t) {
            return (t.catatanHtml || t.catatan ? 1 : 0)
                + (t.berkasKandidat || []).length
                + (t.berkas || []).length;
        },
        toggleDetail(t) {
            this.detailTes = this.detailTes === t.id ? null : t.id;
        },
        /** Ringkas jadwal untuk satu baris rapor: "Sen, 12 Agu 2026 · 09.00". */
        jadwalRingkas(j) {
            if (!j?.mulai) return '—';
            const d = new Date(String(j.mulai).replace(' ', 'T'));
            if (Number.isNaN(d.getTime())) return '—';
            const tgl = d.toLocaleDateString('id-ID', { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' });
            const jam = d.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }).replace(':', '.');

            return `${tgl} · ${jam} WIB`;
        },
        /**
         * Muat berkas milik SATU tahap.
         *
         * Daftarnya DIKOSONGKAN lebih dulu. Tanpa itu, berkas tahap sebelumnya
         * masih terpampang selama permintaan berjalan — dan bila tahap baru
         * ternyata tidak boleh mengunggah, daftar lama bertahan selamanya
         * sehingga berkas wawancara terlihat seolah milik tahap MCU.
         *
         * `tahapDimuat` menjaga balasan yang datang terlambat: kalau admin
         * sudah berpindah tahap, hasil permintaan lama diabaikan.
         */
        async loadBerkas(tahapId) {
            this.berkasHasil = [];
            this.tahapBerkasId = tahapId;
            if (!tahapId) return;

            try {
                const res = await axios.get(`/api/v1/karir/lamaran/tahap/${tahapId}/berkas`, CFG);
                if (this.tahapBerkasId !== tahapId) return;
                this.berkasHasil = res.data.result || [];
            } catch (e) {
                if (this.tahapBerkasId === tahapId) this.berkasHasil = [];
            }
        },
        async unggahBerkas(ev) {
            const file = ev.target.files?.[0];
            ev.target.value = '';
            if (!file || !this.detailKandidat?.tahapId) return;
            const fd = new FormData();
            fd.append('file', file);
            this.berkasBusy = true;
            try {
                await axios.post(`/api/v1/karir/lamaran/tahap/${this.detailKandidat.tahapId}/berkas`, fd, { headers: { Accept: 'application/json', 'Content-Type': 'multipart/form-data' } });
                this.notice('Berkas terunggah.');
                await this.loadBerkas(this.detailKandidat.tahapId);
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal mengunggah.', true);
            } finally {
                this.berkasBusy = false;
            }
        },
        async hapusBerkas(b) {
            try {
                await axios.delete(`/api/v1/karir/lamaran/tahap/berkas/${b.id}`, CFG);
                this.notice('Berkas dihapus.');
                await this.loadBerkas(this.detailKandidat.tahapId);
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal menghapus.', true);
            }
        },
        previewBerkas(b) {
            const gambar = ['jpg', 'jpeg', 'png', 'webp', 'gif'].includes(String(b.ext || '').toLowerCase());
            this.lightbox = { nama: b.nama, field: 'hasil', status: '', url: b.url, pdf: !gambar };
            this.lbSrc = b.url;
            this.mulaiMuat();
        },
        /**
         * Mulai memuat pratinjau, dengan BATAS WAKTU.
         *
         * URL berkas mengalihkan ke signed URL GCS. Pada PDF di dalam iframe,
         * peristiwa `load` tidak selalu terpicu dan `error` hampir tidak pernah —
         * sehingga spinner bisa berputar selamanya padahal berkasnya sudah
         * tampil. Batas 10 detik membuat keadaan menggantung itu mustahil.
         */
        mulaiMuat() {
            this.lbLoading = true;
            this.lbError = false;
            if (this.lbTimer) clearTimeout(this.lbTimer);
            this.lbTimer = setTimeout(() => { this.lbLoading = false; }, 10000);
        },
        selesaiMuat(gagal = false) {
            if (this.lbTimer) clearTimeout(this.lbTimer);
            this.lbLoading = false;
            this.lbError = gagal;
        },
        /**
         * Buka dokumen di modal — gambar MAUPUN PDF.
         *
         * PDF dulu dilempar ke tab baru, jadi admin kehilangan konteks drawer
         * yang sedang dibacanya. Modalnya sanggup menyematkan PDF lewat iframe.
         */
        bukaDok(b) {
            this.lightbox = { ...b, pdf: !b.isImage };
            this.lbSrc = b.url;
            this.mulaiMuat();
        },
        // Muat ulang gambar (signed URL bisa kedaluwarsa) — cache-buster kecil.
        lbCoba() {
            if (!this.lightbox) return;
            this.lbError = false;
            this.lbLoading = true;
            this.lbSrc = this.lightbox.url + (this.lightbox.url.includes('?') ? '&' : '?') + 'r=' + Date.now();
        },
        /* ── Rapor tes (multi-tes) ── */
        pillTes(t) {
            if (t.status === 'TIDAK_HADIR') return 'is-absent';
            // Surat penawaran tidak menunggu apa pun — dokumennya diunggah saat
            // keputusan diambil, jadi lencana "Menunggu" hanya menyesatkan.
            if (t.infoSaja && t.status !== 'SELESAI') return 'is-note';
            if (t.status !== 'SELESAI') return t.status === 'DIJADWALKAN' ? 'is-sched' : 'is-wait';
            if (t.peran === 'INFORMATIF') return 'is-done';
            return t.hasil === 'LULUS' ? 'is-pass' : 'is-fail';
        },
        labelTes(t) {
            if (t.status === 'TIDAK_HADIR') return 'Tidak hadir';
            if (t.infoSaja && t.status !== 'SELESAI') return 'Diunggah saat keputusan';
            // Ujian online: sebutkan yang sedang ditunggu. "Menunggu" saja bikin
            // admin mengira ada yang harus ia kerjakan, padahal giliran sistem.
            if (t.status === 'DIJADWALKAN') return t.online ? 'Menunggu hasil HCLearn' : 'Dijadwalkan';
            if (t.status !== 'SELESAI') return t.online ? 'Belum dijadwalkan' : 'Menunggu';
            if (t.peran === 'INFORMATIF') return 'Selesai';
            return t.hasil === 'LULUS' ? 'Lulus' : 'Gagal';
        },
        /**
         * Boleh dicatat hasilnya? Server yang memutuskan (lihat rapotTes di
         * LamaranController) — aktivitas tunggal tanpa jenis tes tidak punya
         * hasil sendiri, jadi tombolnya tidak ditampilkan.
         */
        /**
         * Hasil hanya boleh dicatat SETELAH kehadiran ditetapkan.
         *
         * Tanpa gerbang ini, tim bisa melampirkan hasil MCU untuk orang yang
         * ternyata tidak datang — dan itu baru ketahuan jauh di belakang.
         */
        bisaCatatKehadiran(t) { return !t.butuhKehadiran; },
        bisaCatat(t) {
            return this.detailKandidat?.statusLamaran === 'BERJALAN' && !!t.dapatDicatat;
        },
        /**
         * "Tidak hadir" sebagai ESCAPE HATCH — untuk aktivitas yang tidak punya
         * pasangan tombol Hadir/Tidak Hadir (mis. ujian online yang hasilnya tak
         * kunjung datang). Aktivitas berjadwal yang kehadirannya memang sedang
         * ditanyakan tidak ikut: dua tombol bertuliskan hal sama, dengan modal
         * berbeda, hanya membuat admin menebak mana yang benar.
         */
        bisaTidakHadir(t) {
            return this.detailKandidat?.statusLamaran === 'BERJALAN'
                && !!t.dapatTidakHadir
                && !t.butuhKehadiran;
        },
        askCatat(t) {
            this.catatTarget = t;
            this.catatHasil = 'LULUS';
            this.catatNilai = null;
            // Catatan yang SUDAH ada dimuat kembali — mencatat hasil kerap
            // menyusul pencatatan kehadiran yang catatannya sudah ditulis, dan
            // membiarkan editor kosong akan menghapusnya begitu disimpan.
            this.catatCatatanHtml = t.catatanHtml || '';
            this.catatShow = true;
        },

        /**
         * Berkas aktivitas berubah dari dalam modal.
         *
         * Detail kandidat TIDAK dimuat ulang di sini: memuat ulang akan
         * mengganti objek `catatTarget`/`hadirTarget` yang sedang dipegang modal
         * sehingga isinya berkedip dan editor kehilangan fokus di tengah
         * mengetik. Daftar berkasnya sudah dikelola komponennya sendiri;
         * kenyataan di layar besar menyusul saat modal ditutup.
         */
        tandaiBerkasBerubah() {
            this.berkasAktivitasBerubah = true;
        },

        /**
         * Tutup modal aktivitas tanpa menyimpan.
         *
         * Berkas yang telanjur diunggah TIDAK ikut dibatalkan — ia sudah ada di
         * server dan memang milik aktivitas itu. Yang perlu menyusul hanyalah
         * layar besar, supaya lampirannya tidak "hilang" sampai halaman dimuat
         * ulang secara manual.
         */
        tutupModalAktivitas(kunci) {
            this[kunci] = false;
            if (this.berkasAktivitasBerubah) {
                this.berkasAktivitasBerubah = false;
                this.muatDetail(this.selectedId);
            }
        },
        /** Alasan HOLD dari master — dimuat sekali per sesi. */
        async muatAlasanHold() {
            if (this.alasanHold.length) return;
            try {
                const res = await axios.get('/api/v1/karir/options/alasan-hold', CFG);
                this.alasanHold = res.data.result || [];
            } catch (e) {
                this.alasanHold = [];
            }
        },
        /**
         * @param {boolean} menahan
         * @param {object|null} baris kandidat dari KARTU; kosong = dari drawer.
         */
        askHold(menahan, baris = null) {
            this.holdNilai = menahan;
            this.holdTarget = baris || this.detailKandidat;
            this.holdAlasan = '';
            this.holdCatatanHtml = '';
            if (menahan) this.muatAlasanHold();
            this.holdShow = true;
        },
        async konfirmHold() {
            const target = this.holdTarget || this.detailKandidat;
            if (this.sibuk || !target?.tahapId) return;
            this.sibuk = true;
            try {
                const res = await axios.patch(`/api/v1/karir/lamaran/tahap/${target.tahapId}/hold`, {
                    hold: this.holdNilai,
                    alasanKode: this.holdNilai ? this.holdAlasan : null,
                    catatan: teksDariHtml(this.holdCatatanHtml) || null,
                    catatanHtml: this.holdCatatanHtml || null,
                }, CFG);
                this.notice(res.data?.message || 'Tersimpan.');
                this.holdShow = false;
                this.holdTarget = null;
                // Drawer ditutup: melepas tahan bisa membuat tahap langsung
                // menyimpulkan sendiri, sehingga isi drawer yang lama sudah
                // tidak menggambarkan keadaan mana pun.
                this.detailKandidat = null;
                await this.muatDetail(this.selectedId);
                this.muatProgram();
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal memproses penahanan.', true);
            } finally {
                this.sibuk = false;
            }
        },

        /* ── CETAK LAPORAN ─────────────────────────────────────────────────── */
        async askLaporan() {
            if (!this.detailKandidat?.id) return;
            this.laporanFormat = 'PDF';
            this.laporanSiap = false;
            this.laporanProses = false;
            this.laporanUrl = '';
            this.laporanGagal = '';
            this.laporanOpsi = [];
            this.laporanFormulir = [];
            this.laporanShow = true;

            try {
                const res = await axios.get(`/api/v1/karir/lamaran/${this.detailKandidat.id}/laporan/opsi`, CFG);
                this.laporanOpsi = res.data?.result?.formulir || [];
                // Bawaan: yang TERBARU. Itulah yang hampir selalu dimaksud
                // "cetak datanya" — jawaban lama sudah digantikan kandidat sendiri.
                this.laporanFormulir = this.laporanOpsi.filter((f) => f.utama).map((f) => f.id);
            } catch (e) {
                this.laporanGagal = e.response?.data?.message || 'Gagal memuat daftar formulir.';
            }
        },
        async konfirmLaporan() {
            if (this.laporanProses || !this.detailKandidat?.id) return;
            this.laporanProses = true;
            this.laporanGagal = '';
            try {
                const res = await axios.post(`/api/v1/karir/lamaran/${this.detailKandidat.id}/laporan`, {
                    format: this.laporanFormat,
                    formulir: this.laporanFormulir,
                }, CFG);
                this.pantauLaporan(res.data?.result?.id);
            } catch (e) {
                this.laporanProses = false;
                this.laporanGagal = e.response?.data?.message || 'Gagal meminta laporan.';
            }
        },
        /**
         * Tanya berkala sampai laporannya jadi.
         *
         * Berhenti sendiri setelah ~2 menit: antrean yang mati membuat status
         * DIPROSES bertahan selamanya, dan lingkaran berputar tanpa akhir lebih
         * membingungkan daripada pesan gagal yang jujur.
         */
        pantauLaporan(id) {
            if (!id) {
                this.laporanProses = false;
                this.laporanGagal = 'Permintaan tidak dikenali.';

                return;
            }

            let sisa = 40;
            clearInterval(this.laporanTimer);
            this.laporanTimer = setInterval(async () => {
                sisa--;
                if (sisa <= 0) {
                    clearInterval(this.laporanTimer);
                    this.laporanProses = false;
                    this.laporanGagal = 'Laporan belum selesai — pastikan worker antrean berjalan, lalu coba lagi.';

                    return;
                }
                try {
                    const { data } = await axios.get(`/api/v1/karir/lamaran/laporan/${id}`, CFG);
                    const r = data?.result || {};
                    if (r.selesai) {
                        clearInterval(this.laporanTimer);
                        this.laporanProses = false;
                        this.laporanSiap = true;
                        this.laporanUrl = r.url;
                    } else if (r.gagal) {
                        clearInterval(this.laporanTimer);
                        this.laporanProses = false;
                        this.laporanGagal = r.pesan || 'Laporan gagal dibuat.';
                    }
                } catch (e) {
                    clearInterval(this.laporanTimer);
                    this.laporanProses = false;
                    this.laporanGagal = 'Gagal memeriksa status laporan.';
                }
            }, 3000);
        },

        /* ── PENYARING & PILIH BANYAK ──────────────────────────────────────── */
        bersihkanFilter() {
            this.posisiPilih = '';
            this.keadaanPilih = '';
            this.cariKandidat = '';
        },
        /** Kunci stabil sebuah kolom — dipakai menandai kolom mana yang aktif. */
        kunciKolom(col) { return col.kode || col.label || String(col.urutan || ''); },
        togglePilihKolom(col) {
            const k = this.kunciKolom(col);
            // Berpindah kolom MENGOSONGKAN pilihan: membawa serta pilihan dari
            // kolom sebelumnya berarti menjadwalkan orang yang tidak sedang
            // dilihat, dan itu persis kesalahan yang paling mahal di sini.
            this.terpilih = [];
            this.kolomPilih = this.kolomPilih === k ? '' : k;
        },
        tutupPilihKolom() {
            this.kolomPilih = '';
            this.terpilih = [];
        },
        /** Kartu diklik: memilih bila kolomnya sedang memilih, kalau tidak membuka detail. */
        klikKartu(col, r) {
            if (this.kolomPilih === this.kunciKolom(col)) {
                const i = this.terpilih.indexOf(r.id);
                if (i >= 0) this.terpilih.splice(i, 1);
                else this.terpilih.push(r.id);

                return;
            }
            this.bukaKandidat(r);
        },
        kolomTerpilihPenuh(col) {
            const ids = this.kartuKolom(col).map((r) => r.id);

            return ids.length > 0 && ids.every((id) => this.terpilih.includes(id));
        },
        /** Pilih / batalkan seluruh kartu di satu kolom sekaligus. */
        pilihKolom(col) {
            const ids = this.kartuKolom(col).map((r) => r.id);
            if (this.kolomTerpilihPenuh(col)) {
                this.terpilih = this.terpilih.filter((id) => !ids.includes(id));

                return;
            }
            this.terpilih = [...new Set([...this.terpilih, ...ids])];
        },

        /* ── JADWAL MASSAL ─────────────────────────────────────────────────── */
        askJadwalMassal() {
            if (!this.aktivitasTerpilih.length) return;
            this.muatLokasi();
            // Aktivitas yang paling banyak dipunyai kandidat terpilih jadi
            // pilihan awal — itulah yang hampir selalu dimaksud.
            const utama = this.aktivitasTerpilih[0];
            this.massalAktivitas = utama.label;
            // Sepadan dengan penjadwalan satuan: DARING sebagai bawaan, kecuali
            // tipenya memang mustahil daring (MCU, tanda tangan kontrak).
            this.massalMode = utama.wajibLuring ? 'LURING' : 'DARING';
            this.massalPola = 'BERGILIR';
            this.massalMulai = '';
            this.massalDurasi = 30;
            this.massalJeda = 0;
            this.massalLink = '';
            this.massalLokasi = '';
            this.massalLokasiId = null;
            this.massalCatatan = '';
            this.massalShow = true;
        },
        async konfirmJadwalMassal() {
            if (this.sibuk || !this.bolehSimpanMassal) return;
            this.sibuk = true;
            try {
                const res = await axios.post('/api/v1/karir/lamaran/sub-tes/jadwal-massal', {
                    // Id AKTIVITAS, bukan id kandidat: satu kandidat bisa punya
                    // beberapa aktivitas, dan yang dijadwalkan hanya yang dipilih.
                    subTesIds: this.sasaranMassal.map((x) => x.t.id),
                    mode: this.massalMode,
                    pola: this.massalPola,
                    mulai: this.massalMulai,
                    durasiMenit: this.massalPola === 'BERGILIR' ? this.massalDurasi : null,
                    jedaMenit: this.massalPola === 'BERGILIR' ? this.massalJeda : null,
                    link: this.massalMode === 'DARING' ? this.massalLink : null,
                    lokasiId: this.massalMode === 'LURING' ? this.massalLokasiId : null,
                    lokasi: this.massalMode === 'LURING' ? (this.massalLokasi || null) : null,
                    catatan: this.massalCatatan || null,
                }, CFG);

                const gagal = res.data?.result?.gagal || [];
                this.notice(res.data?.message || 'Jadwal massal tersimpan.', gagal.length > 0);
                // Yang ditolak disebutkan satu per satu di log peramban: daftar
                // panjang di dalam toast tak terbaca, tapi menghilangkannya sama
                // saja kehilangan orang tanpa jejak.
                if (gagal.length) {
                    console.warn('[JADWAL MASSAL] dilewati:', gagal);
                }

                this.massalShow = false;
                this.terpilih = [];
                this.kolomPilih = '';
                await this.muatDetail(this.selectedId);
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal menjadwalkan massal.', true);
            } finally {
                this.sibuk = false;
            }
        },

        /** Daftar lokasi aktif dari Master Lokasi — dimuat sekali per sesi. */
        async muatLokasi() {
            if (this.daftarLokasi.length || this.lokasiLoading) return;
            this.lokasiLoading = true;
            try {
                const res = await axios.get('/api/v1/master-lokasi', { ...CFG, params: { aktif: 1 } });
                this.daftarLokasi = res.data.result || [];
            } catch (e) {
                this.notice('Daftar lokasi gagal dimuat.', true);
            } finally {
                this.lokasiLoading = false;
            }
        },
        askHadir(t, nilai) {
            this.hadirTarget = t;
            this.hadirNilai = nilai;
            this.hadirCatatan = '';
            this.hadirCatatanHtml = t.catatanHtml || '';
            // Tanpa prasetel Lulus/Gagal: verdict harus tindakan SADAR. Kalau
            // 'LULUS' sudah tercentang sejak jendela dibuka, penilai yang cuma
            // menulis catatan lalu menyimpan telah meloloskan orang tanpa
            // pernah memutuskannya.
            this.hadirHasil = t.hasil || '';
            this.hadirNilaiSkor = t.nilai ?? null;
            this.hadirNilaiTeks = t.nilaiTeks || '';
            // MCU: isi bidangnya dari yang SUDAH tercatat, dan JANGAN memprasetel
            // status apa pun. 'Fit' yang sudah tercentang sejak jendela dibuka
            // membuat penilai yang cuma menulis catatan lalu menyimpan telah
            // menyatakan orang itu sehat tanpa pernah memutuskannya.
            const m = t.mcu || {};
            this.mcuStatus = m.status || '';
            this.mcuPenyedia = m.penyedia || '';
            this.mcuTanggal = (m.tanggal || '').slice(0, 10);
            this.mcuCatatan = m.catatan || '';
            // Tanpa prasetel: menutup lamaran orang tidak boleh berjarak satu
            // klik tak sengaja dari jendela yang baru saja dibuka.
            this.jawabPenawaran = '';
            this.hadirShow = true;
        },
        async konfirmHadir() {
            if (this.sibuk || !this.hadirTarget) return;
            this.sibuk = true;
            try {
                // HADIR membawa catatan penilaian berformat; TIDAK HADIR cukup
                // sebaris alasan — tak ada penilaian untuk orang yang tak datang.
                const hadir = this.hadirNilai === 'Y';
                // Hasil ikut dikirim bila aktivitas ini memang dicatat tim —
                // server menutup aktivitasnya sekaligus, jadi tak ada jendela
                // lanjutan yang bisa terlupakan.
                const catat = hadir && this.hadirTarget.dinilaiTim;
                const res = await axios.patch(`/api/v1/karir/lamaran/sub-tes/${this.hadirTarget.id}/kehadiran`, {
                    hadir: this.hadirNilai,
                    hasil: catat && this.hadirTarget.peran !== 'INFORMATIF' ? (this.hadirHasil || null) : null,
                    nilai: catat ? (this.hadirNilaiSkor ?? null) : null,
                    nilaiTeks: catat ? (this.hadirNilaiTeks || null) : null,
                    catatan: hadir ? (teksDariHtml(this.hadirCatatanHtml) || null) : (this.hadirCatatan || null),
                    catatanHtml: hadir ? (this.hadirCatatanHtml || null) : null,
                    // Hasil pemeriksaan ikut di permintaan yang SAMA. Tidak ada
                    // jendela lanjutan yang bisa terlupakan, dan verdict-nya
                    // diturunkan server dari status ini — bukan ditanyakan dua kali.
                    ...(this.hadirTarget.isMcu && hadir ? {
                        mcuStatus: this.mcuStatus || null,
                        mcuPenyedia: this.mcuPenyedia.trim() || null,
                        mcuTanggal: this.mcuTanggal || null,
                        mcuCatatan: this.mcuCatatan.trim() || null,
                    } : {}),
                    ...(this.hadirTarget.penawaran && hadir ? { jawabanPenawaran: this.jawabPenawaran || null } : {}),
                }, CFG);
                this.notice(res.data?.message || 'Kehadiran dicatat.');
                this.hadirShow = false;
                this.hadirTarget = null;
                await this.muatDetail(this.selectedId);
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal mencatat kehadiran.', true);
            } finally {
                this.sibuk = false;
            }
        },
        askJadwal(t) {
            this.muatLokasi();
            this.jadwalTarget = t;
            // Jadwal yang sudah ada dimuat kembali supaya "Ubah Jadwal" tidak
            // memaksa mengetik ulang seluruh isinya.
            const j = t.jadwal || {};
            // Tipe tatap-muka selalu LURING, apa pun isi jadwal sebelumnya.
            // Bentuk bawaan DARI MASTER TIPE TAHAP. Negosiasi gaji membuka
            // langsung pada Telepon; tanpa ini admin harus ingat memindahkannya
            // tiap kali, dan yang lupa mengirim undangan bertautan Meet untuk
            // percakapan yang sebenarnya cuma panggilan telepon.
            const pilihan = this.jadwalTarget?.wajibLuring
                ? (this.modeJadwal || []).filter((m) => m.luring)
                : (this.modeJadwal || []);
            const sah = (k) => k && pilihan.some((m) => m.kode === k);
            this.jadwalMode = [j.mode, t.modeJadwalBawaan, pilihan[0]?.kode].find(sah) || '';
            // Nomor profil sebagai TITIK AWAL, bukan yang tersimpan diam-diam:
            // admin tetap harus melihat dan menyetujuinya sebelum menyimpan.
            this.jadwalKontak = j.kontak || this.detailKandidat?.hp || '';
            this.jadwalMulai = j.mulai || '';
            this.jadwalSelesai = j.selesai || '';
            this.jadwalLink = j.link || '';
            this.jadwalLokasi = j.lokasi || '';
            // TIDAK ADA lokasi bawaan. Sebelumnya kotak ini terisi sendiri
            // dengan lokasi bertanda UTAMA — hemat satu klik, tetapi menyimpan
            // jadwal SEKALIGUS mengirim undangannya. Rekruter yang tidak
            // menyadari isian itu mengundang kandidat ke tempat yang tidak
            // pernah ia pilih, dan surelnya sudah telanjur terkirim. Memilih
            // tempat harus tindakan sadar.
            this.jadwalLokasiId = j.lokasiId || null;
            this.jadwalCatatan = j.catatan || '';
            this.jadwalShow = true;
        },
        async konfirmJadwal() {
            if (this.sibuk || !this.jadwalTarget) return;
            this.sibuk = true;
            try {
                const res = await axios.patch(`/api/v1/karir/lamaran/sub-tes/${this.jadwalTarget.id}/jadwal`, {
                    mode: this.jadwalMode,
                    mulai: this.jadwalMulai,
                    selesai: this.jadwalSelesai || null,
                    // Yang dikirim mengikuti FLAG mode, bukan nama modenya —
                    // sama persis dengan yang ditegakkan server.
                    link: this.modeJadwalDef?.butuhTautan ? this.jadwalLink : null,
                    lokasiId: this.modeJadwalDef?.butuhLokasi ? this.jadwalLokasiId : null,
                    lokasi: this.modeJadwalDef?.butuhLokasi ? (this.jadwalLokasi || null) : null,
                    kontak: this.modeJadwalDef?.butuhKontak ? this.jadwalKontak.trim() : null,
                    catatan: this.jadwalCatatan || null,
                }, CFG);
                this.notice(res.data?.message || 'Jadwal disimpan.');
                this.jadwalShow = false;
                this.jadwalTarget = null;
                await this.muatDetail(this.selectedId);
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal menyimpan jadwal.', true);
            } finally {
                this.sibuk = false;
            }
        },
        async konfirmCatat() {
            if (this.sibuk || !this.catatTarget) return;
            this.sibuk = true;
            try {
                const res = await axios.patch(`/api/v1/karir/lamaran/sub-tes/${this.catatTarget.id}/catat-hasil`, {
                    hasil: this.catatTarget.peran === 'INFORMATIF' ? null : this.catatHasil,
                    nilai: this.catatNilai ?? null,
                    // Ringkasan polos ikut dikirim untuk daftar & ekspor; server
                    // tetap menurunkannya sendiri dari HTML, jadi keduanya
                    // mustahil berselisih.
                    catatan: teksDariHtml(this.catatCatatanHtml) || null,
                    catatanHtml: this.catatCatatanHtml || null,
                }, CFG);
                this.notice(res.data?.message || 'Hasil dicatat.');
                this.catatShow = false;
                this.catatTarget = null;
                await this.muatDetail(this.selectedId);
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal mencatat hasil.', true);
            } finally {
                this.sibuk = false;
            }
        },

        /** ESCAPE HATCH: kandidat tak hadir — tahap berhenti menunggu tes ini. */
        /**
         * Tarik hasil ujian online dari HCLearn.
         *
         * Dipakai saat webhook CAT tak sampai. Yang tersimpan tetap angka resmi
         * penyedia — admin tidak pernah mengetik nilai. Aman diulang.
         */
        /**
         * Buka aktivitas berikutnya untuk kandidat ini.
         *
         * Hanya muncul pada tahap BERURUTAN dengan mode MANUAL: tim sengaja
         * menahan agar hasil aktivitas ini dibaca dulu sebelum kandidat
         * diteruskan ke asesmen berikutnya.
         */
        async lanjutkanAktivitas(t) {
            if (this.lanjutId) return;
            this.lanjutId = t.id;
            try {
                const res = await axios.patch(`/api/v1/karir/lamaran/sub-tes/${t.id}/lanjutkan`, {}, CFG);
                this.notice(res.data?.message || 'Aktivitas berikutnya dibuka.');
                await this.muatDetail(this.selectedId);
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal membuka aktivitas berikutnya.', true);
            } finally {
                this.lanjutId = null;
            }
        },
        async sinkronHasil(t) {
            if (this.sinkronId) return;
            this.sinkronId = t.id;
            try {
                const res = await axios.post(`/api/v1/karir/lamaran/sub-tes/${t.id}/sinkron`, {}, CFG);
                this.notice(res.data?.message || 'Hasil ditarik dari HCLearn.');
                await this.muatDetail(this.selectedId);
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal menarik hasil dari HCLearn.', true);
            } finally {
                this.sinkronId = null;
            }
        },
        askTidakHadir(t) {
            this.absenTarget = t;
            this.absenCatatan = '';
            this.absenShow = true;
        },
        async konfirmTidakHadir() {
            const t = this.absenTarget;
            if (this.sibuk || !t) return;
            this.sibuk = true;
            try {
                const res = await axios.patch(`/api/v1/karir/lamaran/sub-tes/${t.id}/tidak-hadir`, {
                    catatan: this.absenCatatan || null,
                }, CFG);
                this.absenShow = false;
                this.notice(res.data?.message || 'Aktivitas ditandai tidak hadir.');
                await this.muatDetail(this.selectedId);
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal memproses.', true);
            } finally {
                this.sibuk = false;
            }
        },

        /* ── Keputusan ── */
        askPutus(r, hasil) {
            this.putusTarget = r;
            this.putusHasil = hasil;
            this.putusCatatanHtml = '';
            // Pilihan Talent Pool disemai dari BAWAAN masternya, bukan dari
            // pilihan terakhir admin. Keputusan sebelumnya menyangkut orang
            // lain; mewarisinya membuat kandidat kedua ikut nasib kandidat
            // pertama hanya karena modalnya tidak diperiksa ulang.
            // Bawaan dari master, TAPI dipaksa "tidak" bila tahapnya belum masuk
            // cut-off — supaya nilai yang terkirim selalu sama dengan yang
            // benar-benar terlihat admin.
            this.putusTalentPool = r?.bolehTalentPool
                && (this.hasilKeputusan.find((h) => h.kode === hasil)?.talentPool) !== false;
            // Prasetel hari ini: kabar mundur paling sering dicatat di hari yang sama.
            this.putusTanggal = this.hariIni;
            // Borang kesehatan TIDAK LAGI menumpang modal ini — ia pindah ke
            // langkah kehadiran, tempat hasilnya memang diterima.
            // Tampilkan berkas yang mungkin sudah diunggah sebelumnya.
            this.loadBerkas(r?.tahapId || null);
            this.putusSetuju = false; // selalu minta ulang, jangan warisi centang sebelumnya
            this.putusSetujuAktivitas = false;
            this.konfirmShow = true;
        },
        async konfirmPutus() {
            if (this.sibuk || !this.putusTarget?.tahapId) return;
            this.sibuk = true;
            try {
                const res = await axios.patch(`/api/v1/karir/lamaran/tahap/${this.putusTarget.tahapId}/putus`, {
                    hasil: this.putusHasil,
                    catatan: teksDariHtml(this.putusCatatanHtml) || null,
                    catatanHtml: this.putusCatatanHtml || null,
                    // Hanya berarti untuk hasil yang memang menyerahkan pilihan
                    // ini ke admin; server mengabaikannya pada hasil lain.
                    ...(this.putusDef?.pilihTalentPool ? { talentPool: this.putusTalentPool } : {}),
                    tanggalKonfirmasi: this.putusDef?.olehKandidat ? (this.putusTanggal || null) : null,
                    // Hasil MCU ikut pada keputusan yang sama — satu perjalanan
                    // ke server, jadi mustahil ada keputusan tanpa hasil
                    // kesehatannya (atau sebaliknya) bila salah satu gagal.
                }, CFG);
                this.notice(res.data?.message || 'Keputusan tersimpan.');
                this.konfirmShow = false;
                this.detailKandidat = null;
                this.putusTarget = null;
                this.muatDetail(this.selectedId);
                this.muatProgram();
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal memproses keputusan.', true);
            } finally {
                this.sibuk = false;
            }
        },
        notice(x, err = false) { this.toast = x; this.toastErr = err; if (this.tm) clearTimeout(this.tm); this.tm = setTimeout(() => (this.toast = ''), 3500); },
    },
};
</script>

<style scoped>
.plw-ell { min-width: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.plw-mono { font-family: 'JetBrains Mono', 'Courier New', monospace; font-weight: 700; color: #9aa3b5; }

/* ═══ SHELL HALAMAN: full-bleed di dalam shell-content ═══ */
.plw { display: flex; margin: -1rem; height: calc(100vh - 68px); min-height: 0; overflow: hidden; }

/* ═══ PANEL PROGRAM ═══ */
.plw-panel { width: 304px; flex: 0 0 304px; display: flex; flex-direction: column; min-height: 0; background: rgba(255, 255, 255, 0.66); border-right: 1px solid rgba(226, 232, 240, 0.75); }
.plw-panel__head { padding: 16px 16px 10px; flex: 0 0 auto; }
.plw-panel__toprow { display: flex; align-items: center; justify-content: space-between; gap: 8px; margin-bottom: 12px; }
.plw-panel__title { font-size: 12px; font-weight: 900; letter-spacing: 0.1em; color: #0f172a; }
.plw-panel__count { font-size: 11px; font-weight: 700; color: #8b93a7; background: #eef0f7; border-radius: 999px; padding: 3px 10px; }
.plw-search { position: relative; display: flex; align-items: center; }
.plw-search svg { position: absolute; left: 14px; }
.plw-search input { width: 100%; padding: 11px 14px 11px 40px; border-radius: 13px; border: 1px solid #e6e9f3; background: #f7f8fc; font-family: inherit; font-size: 13.5px; color: #334155; outline: none; transition: all 0.18s; }
.plw-search input:focus { border-color: #a5b4fc; background: #fff; box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.12); }
.plw-chips { display: flex; flex-wrap: wrap; gap: 7px; margin-top: 11px; }
.plw-chip { appearance: none; cursor: pointer; font-family: inherit; font-size: 11.5px; font-weight: 700; padding: 7px 13px; border-radius: 10px; transition: all 0.16s; border: 1px solid #e6e9f3; background: #fff; color: #64748b; }
.plw-chip.is-on { border-color: transparent; background: linear-gradient(135deg, #8b5cf6, #6366f1); color: #fff; }
.plw-panel__list { flex: 1; min-height: 0; overflow-y: auto; display: flex; flex-direction: column; gap: 11px; padding: 6px 14px 16px; }
.plw-empty { text-align: center; color: #94a3b8; font-size: 13px; padding: 1rem 0; }

/* ═══ INDIKATOR LOADING ═══ */
.plw-load { display: flex; align-items: center; justify-content: center; gap: 9px; color: #8b93a7; font-size: 13px; font-weight: 600; padding: 1rem 0; }
.plw-spin { width: 18px; height: 18px; border-radius: 50%; border: 2.5px solid rgba(99, 102, 241, 0.18); border-top-color: #6366f1; animation: plwSpin 0.7s linear infinite; flex: 0 0 auto; }
.plw-spin--lg { width: 30px; height: 30px; border-width: 3px; }
@keyframes plwSpin { to { transform: rotate(360deg); } }

.plw-prog { position: relative; appearance: none; cursor: pointer; text-align: left; font-family: inherit; width: 100%; padding: 14px 15px 14px 18px; border-radius: 16px; background: #fff; transition: all 0.18s; border: 1px solid #eef0f7; box-shadow: 0 2px 8px rgba(15, 23, 42, 0.03); display: flex; flex-direction: column; }
.plw-prog:hover { box-shadow: 0 8px 20px rgba(15, 23, 42, 0.07); }
.plw-prog.is-on { border-color: var(--acc); box-shadow: 0 12px 30px rgba(99, 102, 241, 0.14); }
.plw-prog__bar { position: absolute; left: 0; top: 0; bottom: 0; width: 4px; border-radius: 4px 0 0 4px; background: var(--acc); }
.plw-prog__toprow { display: flex; align-items: center; justify-content: space-between; gap: 8px; width: 100%; }
.plw-prog__tag { display: inline-block; font-size: 9.5px; font-weight: 800; letter-spacing: 0.08em; text-transform: uppercase; padding: 4px 9px; border-radius: 7px; }
.plw-prog__tag.is-mt { background: rgba(245, 158, 11, 0.14); color: #b45309; }
.plw-prog__tag.is-rek { background: rgba(99, 102, 241, 0.12); color: #4f46e5; }
.plw-prog__code { font-size: 9.5px; font-weight: 700; letter-spacing: 0.08em; color: #aab2c5; font-family: 'JetBrains Mono', monospace; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 120px; }
.plw-prog__title { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; font-size: 15px; font-weight: 800; color: #1e293b; letter-spacing: -0.01em; margin-top: 9px; line-height: 1.3; }
.plw-prog__meta { display: flex; align-items: center; gap: 6px; margin-top: 8px; font-size: 12px; color: #8792a6; min-width: 0; }
.plw-prog__meta + .plw-prog__meta { margin-top: 4px; }
.plw-prog__stats { display: flex; align-items: center; gap: 14px; margin-top: 11px; padding-top: 11px; border-top: 1px solid #eef0f7; font-size: 11.5px; font-weight: 600; color: #8792a6; }
.plw-prog__stat { display: inline-flex; align-items: center; gap: 5px; }
.plw-pager { display: flex; align-items: center; justify-content: center; gap: 10px; font-size: 12px; font-weight: 700; color: #8b93a7; padding-top: 4px; }
.plw-pager__btn { appearance: none; cursor: pointer; border: 1px solid #e6e9f3; background: #fff; width: 30px; height: 30px; border-radius: 9px; color: #64748b; }
.plw-pager__btn:disabled { opacity: 0.4; cursor: not-allowed; }

/* ═══ MAIN ═══ */
.plw-main { position: relative; flex: 1; min-width: 0; min-height: 0; overflow-y: auto; overflow-x: hidden; padding: 26px 30px 44px; }
.plw-blob { position: absolute; border-radius: 50%; filter: blur(8px); pointer-events: none; z-index: 0; }
.plw-blob--a { top: -120px; right: 14%; width: 420px; height: 420px; background: radial-gradient(circle at 30% 30%, rgba(139, 92, 246, 0.13), rgba(139, 92, 246, 0) 70%); animation: plwFloatA 16s ease-in-out infinite; }
.plw-blob--b { bottom: -160px; left: 8%; width: 460px; height: 460px; background: radial-gradient(circle at 60% 40%, rgba(99, 102, 241, 0.1), rgba(99, 102, 241, 0) 70%); animation: plwFloatB 19s ease-in-out infinite; }
@keyframes plwFloatA { 0%, 100% { transform: translate(0, 0); } 50% { transform: translate(30px, -24px); } }
@keyframes plwFloatB { 0%, 100% { transform: translate(0, 0); } 50% { transform: translate(-26px, 22px); } }
.plw-main__inner { position: relative; z-index: 2; }
.plw-hgroup { margin-bottom: 18px; }
.plw-h1 { margin: 0; font-size: 26px; font-weight: 800; color: #0f172a; letter-spacing: -0.025em; }
.plw-sub { margin: 6px 0 0; font-size: 14px; color: #64748b; }

.plw-progrow { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; flex-wrap: wrap; margin-bottom: 16px; }
.plw-eyebrow { font-size: 11px; font-weight: 800; letter-spacing: 0.16em; color: #8b5cf6; }
.plw-progtitle { font-size: 22px; font-weight: 800; color: #1e293b; letter-spacing: -0.02em; margin-top: 3px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 100%; }
.plw-progflow { display: flex; align-items: center; gap: 7px; margin-top: 5px; font-size: 13px; color: #8792a6; min-width: 0; }
.plw-progact { display: flex; align-items: center; gap: 10px; flex: 0 0 auto; }
.plw-btn-jadwal { appearance: none; cursor: pointer; font-family: inherit; font-size: 13.5px; font-weight: 800; color: #fff; padding: 11px 18px; border-radius: 13px; border: none; background: linear-gradient(135deg, #8b5cf6, #6366f1); box-shadow: 0 10px 24px rgba(99, 102, 241, 0.3); display: inline-flex; align-items: center; gap: 8px; transition: transform 0.16s; }
.plw-btn-jadwal:hover { transform: translateY(-2px); }
.plw-btn-reload { appearance: none; cursor: pointer; border: 1px solid #e6e9f3; background: #fff; width: 42px; height: 42px; border-radius: 13px; display: flex; align-items: center; justify-content: center; color: #64748b; transition: all 0.3s; }
.plw-btn-reload:hover { color: #4f46e5; transform: rotate(90deg); }

.plw-tabs { display: flex; gap: 10px; margin-bottom: 18px; flex-wrap: wrap; }
.plw-tab { appearance: none; cursor: pointer; font-family: inherit; font-size: 13.5px; font-weight: 800; display: inline-flex; align-items: center; gap: 9px; padding: 11px 18px; border-radius: 14px; transition: all 0.18s; background: #fff; color: #64748b; border: 1px solid #e6e9f3; }
.plw-tab.is-run { background: linear-gradient(135deg, #8b5cf6, #6366f1); color: #fff; border-color: transparent; box-shadow: 0 10px 24px rgba(99, 102, 241, 0.28); }
.plw-tab.is-rej { background: linear-gradient(135deg, #f87171, #ef4444); color: #fff; border-color: transparent; box-shadow: 0 10px 24px rgba(239, 68, 68, 0.26); }
.plw-tab__badge { display: inline-flex; align-items: center; justify-content: center; min-width: 22px; height: 22px; padding: 0 6px; border-radius: 8px; font-size: 12px; font-weight: 800; background: #eef0f7; color: #94a3b8; }
.plw-tab.is-run .plw-tab__badge, .plw-tab.is-rej .plw-tab__badge { background: rgba(255, 255, 255, 0.24); color: #fff; }

/* KANBAN */
.plw-kanban { display: flex; gap: 16px; align-items: flex-start; overflow-x: auto; padding-bottom: 12px; scroll-snap-type: x proximity; }
.plw-col { flex: 0 0 288px; width: 288px; scroll-snap-align: start; background: rgba(255, 255, 255, 0.62); border: 1px solid rgba(226, 232, 240, 0.8); border-radius: 20px; padding: 14px 13px; }
.plw-col__head { display: flex; align-items: center; gap: 7px; padding: 2px 4px 12px; }
.plw-col__num { width: 26px; height: 26px; border-radius: 9px; background: rgba(99, 102, 241, 0.12); color: #4f46e5; font-size: 12.5px; font-weight: 800; display: flex; align-items: center; justify-content: center; flex: 0 0 auto; }
.plw-col__num.is-hot { background: rgba(245, 158, 11, 0.16); color: #b45309; }
/* Tab "Tidak Lolos": kolom tahapnya sama, tapi diberi nada merah supaya sekali
   lihat ketahuan ini papan kegagalan — dan di tahap mana penyusutan terbesar. */
.plw-kanban.is-gugur .plw-col__num.is-hot { background: rgba(239, 68, 68, .14); color: #b91c1c; }
.plw-kanban.is-gugur .plw-col__count:not(:empty) { color: #b91c1c; background: rgba(239, 68, 68, .1); }
.plw-col__name { font-size: 13.5px; font-weight: 800; color: #334155; flex: 1; min-width: 0; display: flex; align-items: center; gap: 6px; white-space: nowrap; overflow: hidden; }
/* Kolom rombongan alur lama — dibedakan, bukan diredupkan. Orang-orang di
   dalamnya tetap harus dikerjakan; hanya asal alurnya yang berbeda. */
.plw-col.is-lawas { border-style: dashed; border-color: rgba(167, 139, 250, .55); background: rgba(250, 248, 255, 0.72); }
.plw-col__lawas { flex: 0 0 auto; padding: 1px 7px; border-radius: 999px; font-size: 9.5px; font-weight: 800; letter-spacing: .02em; text-transform: uppercase; background: #ede9fe; color: #6d28d9; cursor: help; }
.plw-col__count { font-size: 12px; font-weight: 800; color: #94a3b8; background: #eef0f7; border-radius: 8px; padding: 2px 9px; flex: 0 0 auto; }
.plw-col__cards { display: flex; flex-direction: column; gap: 11px; min-height: 60px; }
.plw-col__empty { display: flex; align-items: center; justify-content: center; height: 80px; color: #c3cad8; font-size: 22px; font-weight: 300; }

.plw-card { appearance: none; cursor: pointer; text-align: left; font-family: inherit; width: 100%; background: #fff; border: 1px solid #eef0f7; border-radius: 16px; padding: 14px; transition: all 0.18s; box-shadow: 0 2px 8px rgba(15, 23, 42, 0.04); animation: plwCardIn 0.4s cubic-bezier(0.22, 1, 0.36, 1) both; }
.plw-card:hover { border-color: #d9def0; box-shadow: 0 10px 24px rgba(15, 23, 42, 0.08); transform: translateY(-2px); }
@keyframes plwCardIn { from { opacity: 0; transform: translateY(12px); } to { opacity: 1; transform: translateY(0); } }
.plw-card__toprow { display: flex; align-items: center; gap: 10px; width: 100%; min-width: 0; }
.plw-card__avatar { width: 38px; height: 38px; border-radius: 11px; color: #fff; font-size: 13px; font-weight: 800; display: flex; align-items: center; justify-content: center; flex: 0 0 auto; }
.plw-card__id { display: flex; flex-direction: column; min-width: 0; flex: 1; }
.plw-card__name { font-size: 14px; font-weight: 800; color: #1e293b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.plw-card__code { font-size: 10.5px; font-weight: 700; letter-spacing: 0.06em; color: #aab2c5; font-family: 'JetBrains Mono', monospace; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.plw-card__pos { display: block; margin-top: 10px; font-size: 12.5px; font-weight: 600; color: #6366f1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

/* Konteks lowongan di kartu — dibuat kecil & abu supaya nama kandidat tetap
   jadi hal pertama yang terbaca. */
/* SATU BARIS, DIPOTONG RAPI.
   Sebelumnya `flex-wrap: wrap` + span tanpa batas lebar membiarkan departemen
   panjang ("PRODUCTION SUPPORT · HEALTH SAFETY…") mengalir keluar dan menjebol
   tepi kartu — teksnya terpotong oleh tepi kolom, bukan oleh ellipsis, jadi
   terbaca seperti tampilan yang rusak. */
.plw-card__meta { display: flex; flex-wrap: nowrap; gap: 4px 9px; margin-top: 5px; font-size: 10.5px; color: #94a3b8; min-width: 0; overflow: hidden; }
.plw-card__meta span { display: inline-flex; align-items: center; gap: 4px; min-width: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.plw-card__meta span > i { flex: none; }
.plw-card__foot { display: flex; align-items: center; justify-content: space-between; gap: 8px; margin-top: 10px; min-width: 0; }
.plw-card__umur { display: inline-flex; align-items: center; gap: 4px; font-size: 10.5px; font-weight: 600; color: #94a3b8; flex: none; }

/* ── Daftar lowongan sekaligus penyaring papan ─────────────────────────── */
.plw-lows { display: grid; grid-template-columns: repeat(auto-fill, minmax(230px, 1fr)); gap: 10px; margin-bottom: 18px; }
.plw-low { appearance: none; cursor: pointer; text-align: left; font-family: inherit; background: #fff; border: 1px solid #eef0f7; border-radius: 14px; padding: 11px 13px; transition: all .18s; display: flex; flex-direction: column; gap: 5px; min-width: 0; }
.plw-low:hover { border-color: #c7d2fe; box-shadow: 0 6px 16px rgba(15, 23, 42, .06); transform: translateY(-1px); }
.plw-low.is-on { border-color: #6366f1; background: linear-gradient(180deg, #f5f3ff, #fff); box-shadow: 0 6px 18px rgba(99, 102, 241, .16); }
.plw-low.is-penuh { border-left: 3px solid #10b981; }
.plw-low__nama { display: flex; align-items: center; gap: 6px; font-size: 12.5px; font-weight: 800; color: #0f172a; letter-spacing: -.01em; min-width: 0; }
.plw-low__lvl { flex: none; font-size: 9.5px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: #6366f1; background: rgba(99, 102, 241, .1); padding: 2px 6px; border-radius: 999px; }
.plw-low__meta { display: flex; flex-wrap: wrap; gap: 3px 9px; font-size: 10.5px; color: #94a3b8; min-width: 0; }
.plw-low__meta span { display: inline-flex; align-items: center; gap: 4px; min-width: 0; }
.plw-low__mpp { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 9.5px; color: #64748b; background: #f1f5f9; padding: 1px 5px; border-radius: 4px; }
.plw-low__angka { font-size: 11px; color: #64748b; }
.plw-low__angka b { color: #0f172a; }
/* Lencana keadaan: dipotong bila kepanjangan ("Tidak Lulus — Konfirmasi"),
   bukan mendorong umur-lamaran keluar kartu. Huruf besarnya dibuat lewat CSS,
   bukan lewat .toUpperCase() di template — teks yang sudah dinaikkan di JS
   tidak bisa lagi dikembalikan untuk atribut title. */
.plw-card__chip { display: inline-block; margin-top: 11px; max-width: 100%; font-size: 9.5px; font-weight: 800; letter-spacing: 0.06em; text-transform: uppercase; padding: 4px 9px; border-radius: 7px; background: #eef0f7; color: #64748b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.plw-card__chip.tone-nunggu { background: rgba(245, 158, 11, 0.14); color: #b45309; }
.plw-card__chip.tone-perlu { background: rgba(99, 102, 241, 0.12); color: #4f46e5; }
.plw-card__chip.tone-skor { background: rgba(139, 92, 246, 0.14); color: #7c3aed; }
.plw-card__chip.tone-lolos { background: rgba(16, 185, 129, 0.12); color: #059669; }
.plw-card__chip.tone-pascaPenerimaan { background: rgba(16, 185, 129, 0.16); color: #047857; }
.plw-card__chip.tone-gugur { background: rgba(239, 68, 68, 0.12); color: #dc2626; }
.plw-card__chip.tone-talent { background: rgba(234, 179, 8, 0.16); color: #a16207; }

/* ═══ BARIS PENYARING — tab di kiri, penyaring merapat ke ujung kanan ═══ */
.plw-filterbar { display: flex; align-items: center; justify-content: space-between; gap: 10px; flex-wrap: wrap; margin-bottom: 12px; }
.plw-filters { display: flex; align-items: center; gap: 7px; flex-wrap: nowrap; margin-left: auto; }
.plw-filter { width: 168px; }
.plw-filter--cari { width: 196px; }
@media (max-width: 1100px) {
    .plw-filters { flex-wrap: wrap; width: 100%; margin-left: 0; }
    .plw-filter, .plw-filter--cari { width: calc(50% - 4px); }
}
/* Ikon saja: tombol berteks "Bersihkan" berebut tempat dengan tiga penyaring
   di sebelahnya dan mendorongnya turun ke baris kedua. */
.plw-filter__reset { flex: none; display: grid; place-items: center; width: 32px; height: 32px; border: 1px solid #e2e8f0; background: #fff; color: #94a3b8; font-size: 11px; border-radius: 8px; cursor: pointer; }
.plw-filter__reset:hover { border-color: #fca5a5; color: #dc2626; background: #fef2f2; }
.plw-lopt { display: flex; flex-direction: column; line-height: 1.35; padding: 2px 0; }
.plw-lopt b { font-size: 12.5px; color: #1e293b; }
.plw-lopt small { font-size: 11px; color: #94a3b8; }
.plw-tab.is-hold { background: rgba(100, 116, 139, 0.14); color: #475569; border-color: rgba(100, 116, 139, 0.3); }

/* ═══ IKON AKSI DI KEPALA KOLOM (saring & pilih-banyak) ═══ */
.plw-col__ico { flex: none; display: grid; place-items: center; width: 24px; height: 24px; border: 1px solid #e2e8f0; background: #fff; color: #94a3b8; font-size: 11px; border-radius: 7px; cursor: pointer; transition: all .15s; }
.plw-col__ico:hover { border-color: #a5b4fc; color: #6366f1; }
.plw-col__ico.is-on { border-color: #6366f1; background: #eef2ff; color: #4338ca; }
/* Kolom yang SEDANG disaring ditandai walau panelnya tertutup — kalau tidak,
   kolom yang isinya tinggal 2 dari 40 terbaca seolah memang cuma berisi 2. */
.plw-col__ico.is-aktif { border-color: #f59e0b; background: #fffbeb; color: #b45309; }

/* ═══ PANEL PENYARING KOLOM ═══ */
.plw-colf { display: flex; flex-direction: column; gap: 6px; margin-bottom: 9px; padding: 9px; border-radius: 11px; background: #f8fafc; border: 1px solid #e6e9f0; }
.plw-colf__reset { display: inline-flex; align-items: center; justify-content: center; gap: 5px; border: 1px dashed #cbd5e1; background: #fff; color: #64748b; font-size: 10.5px; font-weight: 700; border-radius: 7px; padding: 5px 8px; cursor: pointer; }
.plw-colf__reset:hover { border-color: #94a3b8; color: #475569; }
.plw-selbar { display: flex; align-items: center; gap: 6px; margin-bottom: 9px; padding: 6px 7px; border-radius: 10px; background: #eef2ff; border: 1px solid #c7d2fe; }
.plw-selbar__all { display: inline-flex; align-items: center; gap: 4px; border: none; background: transparent; color: #4338ca; font-size: 10.5px; font-weight: 800; cursor: pointer; padding: 0; white-space: nowrap; }
.plw-selbar__n { display: grid; place-items: center; min-width: 20px; height: 19px; padding: 0 5px; border-radius: 6px; background: #4f46e5; color: #fff; font-size: 10px; font-weight: 800; }
.plw-selbar__go { margin-left: auto; display: inline-flex; align-items: center; gap: 4px; border: none; background: linear-gradient(135deg, #8b5cf6, #6366f1); color: #fff; font-size: 10.5px; font-weight: 800; border-radius: 7px; padding: 5px 9px; cursor: pointer; white-space: nowrap; }
.plw-selbar__go:disabled { opacity: .45; cursor: not-allowed; }
.plw-selbar__x { flex: none; border: none; background: transparent; color: #818cf8; font-size: 10px; cursor: pointer; padding: 2px; }
.plw-selbar__x:hover { color: #4338ca; }
.plw-card.is-pilih { cursor: pointer; }
.plw-card.is-terpilih { border-color: #6366f1; box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.18); }
.plw-card__cek { flex: none; display: grid; place-items: center; width: 18px; color: #6366f1; font-size: 14px; }
/* TAHAN — ikon di pojok kartu, TAPI berwarna sejak awal.
   Versi sebelumnya transparan/abu pucat dan baru muncul saat kartu di-hover:
   di layar terang ia praktis tak terlihat, jadi jalan keluar yang paling
   dibutuhkan justru yang paling sulit ditemukan. Kini bernada amber (=
   "tunda") yang kontras dengan kartu putih tanpa berteriak seperti tombol
   keputusan. */
.plw-card__hold { flex: none; display: grid; place-items: center; width: 28px; height: 28px; border: 1px solid #fcd34d; background: #fffbeb; color: #b45309; font-size: 13px; border-radius: 8px; cursor: pointer; transition: all .15s; }
.plw-card__hold:hover { background: #fef3c7; border-color: #f59e0b; color: #92400e; transform: scale(1.06); }
/* SEDANG DITAHAN → hijau "lanjutkan": tindakannya berlawanan, jadi warnanya
   pun harus berlawanan — bukan sekadar ikon yang berganti. */
.plw-card__hold.is-on { border-color: #86efac; background: #f0fdf4; color: #15803d; }
.plw-card__hold.is-on:hover { background: #dcfce7; border-color: #4ade80; color: #166534; }
/* Kartu yang sedang ditahan diberi garis kiri — terbaca sekilas tanpa
   menambah satu baris teks pun ke dalam kartunya. */
.plw-card.is-hold { border-left: 3px solid #94a3b8; background: #fbfcfd; }

/* Pratinjau jadwal massal — siapa dapat jam berapa, sebelum email terkirim. */
.plw-prev { margin-top: 12px; padding: 10px 12px; border-radius: 11px; background: #f8fafc; border: 1px solid #e6e9f0; }
.plw-prev__head { display: flex; align-items: center; gap: 6px; margin-bottom: 8px; font-size: 11.5px; font-weight: 800; color: #334155; }
.plw-prev__head i { color: #6366f1; }
.plw-prev__list { max-height: 190px; overflow-y: auto; display: flex; flex-direction: column; gap: 4px; }
.plw-prev__row { display: flex; align-items: center; gap: 8px; font-size: 12px; color: #475569; background: #fff; border: 1px solid #eef1f6; border-radius: 7px; padding: 5px 9px; }
.plw-prev__row b { margin-left: auto; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 11px; color: #4338ca; white-space: nowrap; }
.plw-prev__no { flex: none; display: grid; place-items: center; width: 18px; height: 18px; border-radius: 5px; background: #eef2ff; color: #4f46e5; font-size: 9.5px; font-weight: 800; }
/* DITAHAN — abu kebiruan: bukan peringatan (tak ada yang salah), bukan pula
   ajakan bertindak (justru sengaja ditunda). */
.plw-card__chip.tone-hold { background: rgba(100, 116, 139, 0.16); color: #475569; }

/* ═══ TAHAN (HOLD) di footer drawer ═══ */
.plw-hold { margin-bottom: 10px; padding: 10px 12px; border-radius: 12px; background: rgba(100, 116, 139, 0.08); border: 1px solid rgba(100, 116, 139, 0.25); }
.plw-hold__top { display: flex; align-items: flex-start; gap: 9px; }
.plw-hold__ico { flex: none; display: grid; place-items: center; width: 28px; height: 28px; border-radius: 9px; background: rgba(100, 116, 139, 0.16); color: #475569; font-size: 15px; }
.plw-hold__top b { display: block; font-size: 12.5px; font-weight: 800; color: #334155; }
.plw-hold__top small { display: block; margin-top: 2px; font-size: 11px; line-height: 1.5; color: #64748b; }
.plw-hold__lepas { flex: none; display: inline-flex; align-items: center; gap: 5px; border: 1px solid #86efac; background: #f0fdf4; color: #15803d; font-size: 11.5px; font-weight: 800; border-radius: 8px; padding: 6px 11px; cursor: pointer; }
.plw-hold__lepas:hover { background: #dcfce7; }
.plw-hold__cat { margin: 8px 0 0; padding-top: 8px; border-top: 1px dashed rgba(100, 116, 139, 0.3); font-size: 11.5px; line-height: 1.55; color: #475569; }
.plw-btn-hold { display: flex; align-items: center; justify-content: center; gap: 6px; width: 100%; margin-top: 8px; padding: 9px 12px; border-radius: 10px; border: 1px dashed #cbd5e1; background: #fff; color: #64748b; font-size: 12px; font-weight: 800; cursor: pointer; }
.plw-btn-hold:hover:not(:disabled) { border-color: #94a3b8; color: #475569; background: #f8fafc; }
.plw-btn-hold:disabled { opacity: 0.5; cursor: not-allowed; }

/* Aktivitas yang belum tiba gilirannya (tahap BERURUTAN). */
.plw-test.is-terkunci { opacity: 0.72; }
/* LANJUTKAN — ungu: bukan penilaian, melainkan membuka jalan. */
.plw-test__lanjut { display: inline-flex; align-items: center; gap: 5px; border: 1px solid #c4b5fd; background: #f5f3ff; color: #6d28d9; font-size: 11px; font-weight: 800; border-radius: 8px; padding: 5px 10px; cursor: pointer; }
.plw-test__lanjut:hover:not(:disabled) { background: #ede9fe; border-color: #a78bfa; }
.plw-test__lanjut:disabled { opacity: .6; cursor: not-allowed; }
.plw-test__gembok { display: inline-flex; align-items: center; gap: 5px; padding: 4px 9px; border-radius: 7px; font-size: 11px; font-weight: 700; color: #64748b; background: #f1f5f9; border: 1px solid #e2e8f0; }
.plw-test__gembok b { color: #334155; }
.plw-test__tag.is-internal { background: rgba(100, 116, 139, 0.14); color: #475569; }

/* ═══ DRAWER ═══ */
.plw-overlay { position: fixed; inset: 0; z-index: 1055; background: rgba(15, 23, 42, 0.42); backdrop-filter: blur(2px); transition: opacity 0.32s; opacity: 0; pointer-events: none; }
.plw-overlay.is-on { opacity: 1; pointer-events: auto; }
/* Kolom flex 3 baris: kepala & footer tetap, hanya body yang menggulung.
   Dulu SELURUH drawer yang menggulung, jadi tombol keputusan ikut hanyut. */
.plw-drawer { position: fixed; top: 0; right: 0; bottom: 0; z-index: 1056; width: 560px; max-width: 100%; display: flex; flex-direction: column; background: #f6f7fb; box-shadow: -30px 0 80px rgba(15, 23, 42, 0.2); overflow: hidden; transition: transform 0.4s cubic-bezier(0.22, 1, 0.36, 1); transform: translateX(104%); }
.plw-drawer.is-on { transform: translateX(0); }
.plw-drawer__head { position: relative; flex: 0 0 auto; z-index: 5; background: linear-gradient(180deg, #ffffff, #fbfbfe); border-bottom: 1px solid #eef0f7; padding: 18px 22px; }
.plw-drawer__headrow { display: flex; align-items: flex-start; gap: 14px; }
.plw-drawer__avatar { width: 52px; height: 52px; border-radius: 15px; color: #fff; font-size: 17px; font-weight: 800; display: flex; align-items: center; justify-content: center; flex: 0 0 auto; box-shadow: 0 10px 24px rgba(99, 102, 241, 0.28); }
.plw-drawer__name { font-size: 19px; font-weight: 800; color: #0f172a; letter-spacing: -0.015em; line-height: 1.2; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.plw-drawer__meta { font-size: 12.5px; color: #7c869a; margin-top: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.plw-drawer__chips { display: flex; flex-wrap: wrap; gap: 5px 10px; margin-top: 7px; font-size: 11px; color: #94a3b8; }
.plw-drawer__chips span, .plw-drawer__chips a { display: inline-flex; align-items: center; gap: 4px; }
.plw-drawer__chips .is-mpp { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 10px; color: #64748b; background: #f1f5f9; padding: 1px 6px; border-radius: 4px; }
.plw-drawer__chips .is-link { color: #6366f1; font-weight: 600; text-decoration: none; }
.plw-drawer__chips .is-link:hover { text-decoration: underline; }
.plw-drawer__tag { display: inline-block; padding: 6px 12px; border-radius: 999px; color: #fff; font-size: 11.5px; font-weight: 800; white-space: nowrap; }
.plw-drawer__tag.is-mt { background: linear-gradient(135deg, #fbbf24, #f59e0b); box-shadow: 0 6px 16px rgba(245, 158, 11, 0.34); }
.plw-drawer__tag.is-rek { background: linear-gradient(135deg, #8b5cf6, #6366f1); box-shadow: 0 6px 16px rgba(99, 102, 241, 0.34); }
/* CETAK — tindakan sekunder di kepala drawer: jelas terlihat, tapi tidak
   berebut perhatian dengan tombol keputusan di kaki. */
.plw-drawer__cetak { display: inline-flex; align-items: center; gap: 6px; height: 38px; padding: 0 13px; border: 1px solid #c7d2fe; background: #eef2ff; color: #4338ca; font-size: 12px; font-weight: 800; border-radius: 11px; cursor: pointer; transition: all .16s; }
.plw-drawer__cetak:hover { background: #e0e7ff; border-color: #a5b4fc; }
/* Pilihan formulir & tautan unduh di modal cetak. */
.plw-cek { display: flex; align-items: flex-start; gap: 9px; padding: 9px 11px; margin-bottom: 6px; border: 1px solid #e2e8f0; border-radius: 10px; background: #fff; cursor: pointer; }
.plw-cek:hover { border-color: #a5b4fc; }
.plw-cek input { margin-top: 2px; width: 15px; height: 15px; accent-color: #4f46e5; flex: none; cursor: pointer; }
.plw-cek b { display: block; font-size: 12.5px; font-weight: 800; color: #1e293b; }
.plw-cek small { display: block; margin-top: 1px; font-size: 11px; color: #64748b; }
.plw-unduh { display: flex; align-items: center; justify-content: center; gap: 8px; margin-top: 12px; padding: 11px; border-radius: 11px; background: linear-gradient(135deg, #059669, #10b981); color: #fff; font-size: 13px; font-weight: 800; text-decoration: none; box-shadow: 0 8px 20px rgba(16, 185, 129, .28); }
.plw-unduh:hover { filter: brightness(1.05); }

.plw-drawer__close { appearance: none; border: 1px solid #e6e9f3; background: #fff; width: 38px; height: 38px; border-radius: 11px; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.16s; color: #64748b; }
.plw-drawer__close:hover { background: #f1f5f9; color: #0f172a; }
.plw-drawer__body { flex: 1 1 auto; min-height: 0; overflow-y: auto; overscroll-behavior: contain; padding: 20px 22px 28px; display: flex; flex-direction: column; gap: 18px; }

.plw-infocard { background: #fff; border: 1px solid #eef0f7; border-radius: 18px; padding: 18px 20px; box-shadow: 0 2px 8px rgba(15, 23, 42, 0.03); }
.plw-infogrid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px 20px; }
.plw-klabel { font-size: 10.5px; font-weight: 800; letter-spacing: 0.14em; color: #a2a9ba; }
.plw-kval { font-size: 15px; font-weight: 800; color: #1e293b; margin-top: 4px; }
.plw-status { display: inline-flex; align-items: center; gap: 7px; margin-top: 6px; font-size: 14px; font-weight: 800; }
.plw-status__dot { width: 9px; height: 9px; border-radius: 50%; }
.plw-status.st-berjalan { color: #b45309; }
.plw-status.st-berjalan .plw-status__dot { background: #f59e0b; animation: plwPulse 2s infinite; }
.plw-status.st-lulus { color: #059669; }
.plw-status.st-lulus .plw-status__dot { background: #10b981; }
.plw-status.st-gugur { color: #dc2626; }
.plw-status.st-gugur .plw-status__dot { background: #ef4444; }
@keyframes plwPulse { 0%, 100% { box-shadow: 0 0 0 0 rgba(245, 158, 11, 0.5); } 70% { box-shadow: 0 0 0 7px rgba(245, 158, 11, 0); } }
.plw-segs { display: flex; gap: 5px; margin-top: 9px; }
.plw-seg { flex: 1; height: 6px; border-radius: 99px; background: #eef0f7; }
.plw-seg.is-done { background: linear-gradient(90deg, #8b5cf6, #6366f1); }
.plw-seg.is-cur { background: linear-gradient(90deg, #fbbf24, #f59e0b); }
.plw-seg.is-fail { background: linear-gradient(90deg, #f87171, #ef4444); }
.plw-sysnote { display: flex; gap: 12px; margin-top: 16px; padding: 13px 15px; border-radius: 14px; background: linear-gradient(135deg, #fffbeb, #fff8ec); border: 1px solid #f5e0a3; font-size: 12.5px; line-height: 1.6; color: #8a6d29; }
.plw-sysnote b { color: #92660a; }
.plw-sysnote.is-ready { background: linear-gradient(135deg, #ecfdf5, #f0fdf9); border-color: #a7f3d0; color: #065f46; }
.plw-sysnote.is-ready b { color: #047857; }
.plw-sysnote.is-fail { background: linear-gradient(135deg, #fef2f2, #fff5f5); border-color: #fecaca; color: #7f1d1d; }
.plw-sysnote.is-fail b { color: #b91c1c; }

/* ── Rapor tes tahap (baterai multi-tes) ── */
.plw-tests { display: flex; flex-direction: column; gap: 8px; }
.plw-test { display: flex; align-items: center; gap: 11px; padding: 11px 13px; border: 1px solid #e8eaf3; border-radius: 13px; background: #fff; }
.plw-test__ico { flex: none; width: 34px; height: 34px; border-radius: 10px; display: grid; place-items: center; font-size: 15px; }
.plw-test__ico.is-sys { background: rgba(217, 119, 6, .1); color: #b45309; }
.plw-test__ico.is-man { background: rgba(99, 102, 241, .1); color: #4f46e5; }
.plw-test__main { flex: 1; min-width: 0; }
.plw-test__name { font-size: 13px; font-weight: 700; color: #1e2447; display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
.plw-test__tag { font-size: 10px; font-weight: 700; border-radius: 999px; padding: 1px 7px; background: #f1f5f9; color: #64748b; }
.plw-test__tag.is-info { background: #eef2ff; color: #4338ca; }
.plw-test__tag.is-offer { background: rgba(124, 58, 237, .1); color: #6d28d9; }
.plw-test__sub { font-size: 11px; color: #94a3b8; margin-top: 1px; }
.plw-test__score { font-size: 15px; font-weight: 800; color: #4f46e5; font-variant-numeric: tabular-nums; }
.plw-test__pill { flex: none; font-size: 11px; font-weight: 700; border-radius: 999px; padding: 3px 10px; }
.plw-test__pill.is-pass { background: rgba(16, 185, 129, .13); color: #047857; }
.plw-test__pill.is-fail { background: rgba(239, 68, 68, .12); color: #b91c1c; }
.plw-test__pill.is-done { background: rgba(99, 102, 241, .12); color: #4338ca; }
.plw-test__pill.is-sched { background: rgba(14, 165, 233, .12); color: #0369a1; }
.plw-test__pill.is-wait { background: #f1f5f9; color: #64748b; }
/* Keterangan, bukan keadaan menunggu — nadanya sengaja paling tenang. */
.plw-test__pill.is-note { background: rgba(124, 58, 237, .09); color: #6d28d9; font-weight: 600; }
.plw-test__pill.is-absent { background: rgba(148, 163, 184, .18); color: #475569; }
.plw-test__skip { flex: none; display: inline-flex; align-items: center; gap: 5px; border: 1px solid #fca5a5; background: #fff; color: #b91c1c; font-size: 11px; font-weight: 700; border-radius: 9px; padding: 5px 9px; cursor: pointer; transition: background .15s; }
.plw-test__skip:hover { background: #fef2f2; }
.plw-test__sync { flex: none; display: inline-flex; align-items: center; gap: 5px; border: 1px solid #a5b4fc; background: #fff; color: #4338ca; font-size: 11px; font-weight: 700; border-radius: 9px; padding: 5px 9px; cursor: pointer; transition: background .15s; }
.plw-test__sync:hover:not(:disabled) { background: #eef0fe; }
.plw-test__sync:disabled { opacity: .6; cursor: default; }
.plw-spin { display: inline-block; animation: plwSpin 1s linear infinite; }
@keyframes plwSpin { to { transform: rotate(360deg); } }
.plw-test__rec { flex: none; display: inline-flex; align-items: center; gap: 5px; border: 1px solid #a5b4fc; background: #eef2ff; color: #4338ca; font-size: 11px; font-weight: 700; border-radius: 9px; padding: 5px 9px; cursor: pointer; transition: background .15s; }
.plw-test__rec:hover { background: #e0e7ff; }
.plw-test__cat { margin-top: 3px; font-size: 11px; color: #64748b; display: flex; align-items: flex-start; gap: 5px; line-height: 1.45; }
/* Catatan berformat butuh ruang sendiri: isinya bisa berparagraf & bergambar,
   jadi ikonnya diberi lebar tetap agar teksnya tidak melompat-lompat. */
.plw-test__cat.is-kaya { margin-top: 6px; padding: 7px 9px; border-radius: 9px; background: #f8fafc; border: 1px solid #eef1f6; }
.plw-test__cat.is-kaya > i { flex: none; margin-top: 2px; }
.plw-test__cat.is-kaya > div { min-width: 0; flex: 1; }
/* Berkas dari kandidat vs berkas tim — dibedakan warnanya karena cara
   membacanya berbeda: yang satu bukti yang perlu diperiksa, yang lain
   kesimpulan penilai. */
.plw-test__kirim { margin-top: 5px; display: flex; align-items: center; flex-wrap: wrap; gap: 5px; font-size: 11px; }
.plw-test__kirimlbl { display: inline-flex; align-items: center; gap: 4px; font-weight: 800; color: #0369a1; }
.plw-test__kirim.is-tim .plw-test__kirimlbl { color: #7c3aed; }
.plw-test__kirimfile { display: inline-flex; align-items: center; gap: 4px; max-width: 190px; padding: 2px 7px; border-radius: 999px; background: rgba(2, 132, 199, .08); border: 1px solid rgba(2, 132, 199, .2); color: #075985; font-weight: 700; text-decoration: none; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.plw-test__kirimfile:hover { background: rgba(2, 132, 199, .16); }
.plw-test__kirim.is-tim .plw-test__kirimfile { background: rgba(124, 58, 237, .08); border-color: rgba(124, 58, 237, .2); color: #5b21b6; }
.plw-test__kirim.is-tim .plw-test__kirimfile:hover { background: rgba(124, 58, 237, .16); }
.plw-test__kirimkosong { display: inline-flex; align-items: center; gap: 5px; color: #94a3b8; font-weight: 700; }
.plw-test__kirimkosong.is-wajib { color: #b45309; }
/* Penanda "sudah dinyatakan lengkap" — dibaca sebelum menilai. */
.plw-test__kirimstat { display: inline-flex; align-items: center; gap: 4px; padding: 2px 8px; border-radius: 999px; font-size: 10.5px; font-weight: 800; letter-spacing: .01em; cursor: help; white-space: nowrap; }
.plw-test__kirimstat.is-ok { background: rgba(16, 185, 129, .12); color: #047857; border: 1px solid rgba(16, 185, 129, .3); }
.plw-test__kirimstat.is-nunggu { background: rgba(245, 158, 11, .12); color: #b45309; border: 1px solid rgba(245, 158, 11, .32); }
/* Berkas kandidat di dalam modal penilaian — dipisahkan sebagai kotak sendiri
   supaya tidak terbaca sebagai bagian dari borang yang sedang diisi. */
.plw-kirimbox { display: flex; align-items: center; flex-wrap: wrap; gap: 6px; margin-bottom: 12px; padding: 9px 11px; border-radius: 10px; font-size: 11px; background: rgba(2, 132, 199, .05); border: 1px solid rgba(2, 132, 199, .18); }
.plw-kirimbox__lbl { display: inline-flex; align-items: center; gap: 5px; font-weight: 800; color: #075985; }
/* Jadwal yang sudah ditetapkan — dibaca sekilas saat menandai kehadiran. */
.plw-test__jadwalinfo { margin-top: 4px; display: flex; align-items: flex-start; gap: 5px; font-size: 11px; font-weight: 600; color: #4338ca; line-height: 1.45; }
.plw-test__jadwalinfo .bi { flex: none; margin-top: 1px; }

/* ═══ BORANG DI DALAM MODAL ═══
   Kontrol digambar sendiri, bukan memakai bawaan Element Plus. Modal ini
   bertetangga dengan drawer & rapor tes yang seluruhnya bergaya plw-*, dan
   komponen bawaan membawa palet, radius, serta tinggi barisnya sendiri —
   satu jendela jadi terlihat seperti dijahit dari dua aplikasi berbeda. */
.plw-fld { display: flex; flex-direction: column; gap: 6px; margin-top: 12px; text-align: left; }
.plw-fld:first-child { margin-top: 0; }
.plw-fld__lbl { display: flex; align-items: center; gap: 5px; font-size: 11.5px; font-weight: 800; color: #475569; }
.plw-fld__lbl b { color: #dc2626; }
.plw-fld__lbl small { font-weight: 700; color: #a2a9ba; }
/* Dua isian sebaris; turun sendiri di layar sempit. */
.plw-fld__row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.plw-fld__row .plw-fld { min-width: 0; }

.plw-inp {
    width: 100%; padding: 10px 12px; border: 1px solid #e3e6f0; border-radius: 10px;
    background: #fff; font: inherit; font-size: 13px; color: #1e293b;
    transition: border-color .16s, box-shadow .16s;
}
.plw-inp::placeholder { color: #a8b0c0; }
.plw-inp:focus { outline: none; border-color: #818cf8; box-shadow: 0 0 0 3px rgba(99, 102, 241, .14); }
.plw-inp.is-err { border-color: #fca5a5; background: #fffafa; }
.plw-inp.is-err:focus { border-color: #f87171; box-shadow: 0 0 0 3px rgba(239, 68, 68, .12); }
.plw-inp--ta { min-height: 68px; line-height: 1.55; resize: vertical; }
/* Keterangan yang bentuknya mengikuti isian lain supaya barisnya sejajar. */
.plw-inp.is-static { background: #f8fafc; color: #64748b; font-weight: 700; }
/* Panah bawaan input angka mengubah lebar isian di tiap peramban. */
.plw-inp[type='number'] { appearance: textfield; -moz-appearance: textfield; }
.plw-inp[type='number']::-webkit-outer-spin-button,
.plw-inp[type='number']::-webkit-inner-spin-button { appearance: none; margin: 0; }
.plw-inp[type='date'] { min-height: 41px; }
.plw-inp[type='date']::-webkit-calendar-picker-indicator { cursor: pointer; opacity: .55; }

/* Pilihan bentuk kartu — pengganti radio bawaan. */
.plw-opts { display: flex; flex-direction: column; gap: 7px; }
.plw-opt-card {
    display: flex; align-items: center; gap: 10px; width: 100%; padding: 9px 12px;
    border: 1px solid #e3e6f0; border-radius: 11px; background: #fff;
    font: inherit; text-align: left; cursor: pointer; transition: border-color .16s, background .16s, box-shadow .16s;
}
.plw-opt-card:hover { border-color: #c7cbdb; }
.plw-opt-card__dot { flex: none; width: 26px; height: 26px; border-radius: 50%; display: grid; place-items: center; font-size: 12px; color: #94a3b8; background: #f1f3f9; transition: background .16s, color .16s; }
.plw-opt-card__txt { min-width: 0; }
.plw-opt-card__txt b { display: block; font-size: 13px; font-weight: 800; color: #1e293b; }
.plw-opt-card__txt small { display: block; font-size: 11px; color: #94a3b8; margin-top: 1px; line-height: 1.4; }
.plw-opt-card.is-on.is-ok { border-color: rgba(16, 185, 129, .5); background: rgba(16, 185, 129, .07); box-shadow: 0 0 0 3px rgba(16, 185, 129, .1); }
.plw-opt-card.is-on.is-ok .plw-opt-card__dot { background: #10b981; color: #fff; }
.plw-opt-card.is-on.is-warn { border-color: rgba(245, 158, 11, .55); background: rgba(245, 158, 11, .09); box-shadow: 0 0 0 3px rgba(245, 158, 11, .1); }
.plw-opt-card.is-on.is-warn .plw-opt-card__dot { background: #f59e0b; color: #fff; }
.plw-opt-card.is-on.is-no { border-color: rgba(239, 68, 68, .45); background: rgba(239, 68, 68, .06); box-shadow: 0 0 0 3px rgba(239, 68, 68, .09); }
.plw-opt-card.is-on.is-no .plw-opt-card__dot { background: #dc2626; color: #fff; }
/* HOLD — "belum layak SEMENTARA". Sengaja BUKAN merah: ia bukan penolakan,
   melainkan penundaan yang bisa diperiksa ulang. Memberinya warna yang sama
   dengan Unfit membuat penilai yang membaca sekilas memperlakukan keduanya
   sama — dan itu persis kekeliruan yang kategori ini ada untuk mencegahnya. */
.plw-opt-card.is-on.is-hold { border-color: rgba(2, 132, 199, .5); background: rgba(2, 132, 199, .07); box-shadow: 0 0 0 3px rgba(2, 132, 199, .1); }
.plw-opt-card.is-on.is-hold .plw-opt-card__dot { background: #0284c7; color: #fff; }

/* Ikon tiap pilihan tetap berwarna meski belum terpilih: empat status MCU
   dibedakan lebih dulu oleh warnanya, baru oleh kata-katanya. */
.plw-opt-card.is-ok    .plw-opt-card__dot { color: #059669; background: rgba(16, 185, 129, .1); }
.plw-opt-card.is-warn  .plw-opt-card__dot { color: #b45309; background: rgba(245, 158, 11, .12); }
.plw-opt-card.is-hold  .plw-opt-card__dot { color: #0369a1; background: rgba(2, 132, 199, .1); }
.plw-opt-card.is-no    .plw-opt-card__dot { color: #b91c1c; background: rgba(239, 68, 68, .1); }
.plw-opt-card__dot .bi { font-size: 13px; }
/* Kartu MCU lebih tinggi dari pilihan lain — labelnya dua bahasa dan
   keterangannya kerap dua baris. */
.plw-mform .plw-opt-card { align-items: flex-start; padding: 11px 13px; }
.plw-mform .plw-opt-card .plw-opt-card__dot { margin-top: 1px; }
.plw-mform .plw-opt-card__txt small { color: #64748b; }

/* Dua pilihan berdampingan (Lulus / Gagal) — pengganti radio-button bawaan. */
.plw-seg { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
.plw-seg__b { display: inline-flex; align-items: center; justify-content: center; gap: 7px; padding: 10px; border: 1px solid #e3e6f0; border-radius: 10px; background: #fff; font: inherit; font-size: 13px; font-weight: 800; color: #64748b; cursor: pointer; transition: border-color .16s, background .16s, color .16s; }
.plw-seg__b:hover { border-color: #c7cbdb; }
.plw-seg__b.is-ok.is-on { border-color: rgba(16, 185, 129, .5); background: rgba(16, 185, 129, .1); color: #047857; }
.plw-seg__b.is-no.is-on { border-color: rgba(239, 68, 68, .45); background: rgba(239, 68, 68, .08); color: #b91c1c; }
/* Pilihan yang tidak bermuatan baik/buruk (mis. daring vs tatap muka). */
.plw-seg__b.is-net.is-on { border-color: rgba(99, 102, 241, .5); background: rgba(99, 102, 241, .09); color: #4338ca; }

/* Keterangan sebaris — nadanya dari kelas, bukan gaya sebaris di template. */
.plw-note { display: flex; align-items: flex-start; gap: 7px; margin: 8px 0 0; padding: 9px 11px; border-radius: 10px; font-size: 11.5px; line-height: 1.55; text-align: left; }
.plw-note .bi { flex: none; margin-top: 1px; }
.plw-note.is-info { color: #3730a3; background: rgba(99, 102, 241, .08); border: 1px solid rgba(99, 102, 241, .2); }
.plw-note.is-lock { color: #92400e; background: rgba(245, 158, 11, .09); border: 1px solid rgba(245, 158, 11, .25); }
.plw-note.is-err { color: #b91c1c; background: rgba(239, 68, 68, .07); border: 1px solid rgba(239, 68, 68, .22); }

/* Blok borang bertajuk (hasil MCU di modal keputusan). */
.plw-mform { margin-bottom: 12px; padding: 13px; border: 1px solid rgba(15, 23, 42, .1); border-radius: 14px; background: #f9fafc; }
.plw-mform.is-kurang { border-color: rgba(239, 68, 68, .3); background: #fffafa; }
.plw-mform__head { display: flex; align-items: center; gap: 10px; margin-bottom: 4px; }
.plw-mform__ico { flex: none; width: 34px; height: 34px; border-radius: 10px; display: grid; place-items: center; font-size: 15px; color: #fff; background: linear-gradient(140deg, #f87171, #dc2626); }
.plw-mform__title { font-size: 13.5px; font-weight: 800; color: #1e293b; }
.plw-mform__sub { font-size: 11px; color: #8b93a7; margin-top: 1px; }

@media (max-width: 520px) {
    .plw-fld__row { grid-template-columns: 1fr; }
}

/* Tombol catat untuk tes pihak ke-3 dibedakan warnanya — menandai jalur darurat. */
.plw-test__rec.is-manual { color: #b45309; border-color: rgba(234, 179, 8, .45); background: rgba(234, 179, 8, .08); }

.plw-secrow { display: flex; align-items: center; justify-content: space-between; gap: 10px; margin-bottom: 12px; }
.plw-sectitle { display: flex; align-items: center; gap: 9px; font-size: 15px; font-weight: 800; color: #1e293b; }
.plw-seccount { font-size: 11.5px; font-weight: 700; color: #8b93a7; background: #eef0f7; border-radius: 999px; padding: 4px 11px; flex: 0 0 auto; }

.plw-forms { display: flex; flex-direction: column; gap: 12px; }
.plw-form { background: #fff; border: 1px solid #eef0f7; border-radius: 18px; overflow: hidden; box-shadow: 0 2px 8px rgba(15, 23, 42, 0.03); transition: border-color 0.2s; }
.plw-form.is-open { border-color: #d9def0; }
.plw-form__head { appearance: none; border: none; background: #fff; width: 100%; display: flex; align-items: center; gap: 13px; padding: 15px 18px; cursor: pointer; text-align: left; transition: background 0.18s; font-family: inherit; }
.plw-form.is-open .plw-form__head { background: #fbfbff; }
.plw-form__step { width: 34px; height: 34px; border-radius: 10px; background: linear-gradient(135deg, #cbd2e0, #94a3b8); color: #fff; font-size: 13px; font-weight: 800; display: flex; align-items: center; justify-content: center; flex: 0 0 auto; }
.plw-form__step.is-ok { background: linear-gradient(135deg, #8b5cf6, #6366f1); }
.plw-form__title { display: block; font-size: 14.5px; font-weight: 800; color: #1e293b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.plw-form__sub { display: block; font-size: 11.5px; color: #8b93a7; margin-top: 1px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.plw-form__pill { flex: 0 0 auto; font-size: 10.5px; font-weight: 800; letter-spacing: 0.04em; padding: 4px 10px; border-radius: 999px; white-space: nowrap; }
.plw-form__pill.is-ok { background: rgba(16, 185, 129, 0.12); color: #059669; }
.plw-form__pill.is-wait { background: rgba(245, 158, 11, 0.14); color: #b45309; }
.plw-form__chev { flex: 0 0 auto; transition: transform 0.26s; }
.plw-form.is-open .plw-form__chev { transform: rotate(180deg); }
.plw-form__body { padding: 6px 18px 18px; animation: plwAccIn 0.28s cubic-bezier(0.22, 1, 0.36, 1) both; }
@keyframes plwAccIn { from { opacity: 0; transform: translateY(-6px); } to { opacity: 1; transform: translateY(0); } }
/* DUA KOLOM HANYA BILA MUAT.
   `1fr 1fr` yang dipatok memaksa dua kolom sesempit apa pun drawer-nya, dan
   isian panjang (alamat, uraian pengalaman) terjepit jadi kolom setipis dua
   kata. auto-fit + minmax menurunkannya sendiri jadi satu kolom saat sempit. */
.plw-fields { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(220px, 100%), 1fr)); gap: 1px; background: #eef0f7; border: 1px solid #eef0f7; border-radius: 14px; overflow: hidden; margin-top: 8px; }
.plw-field { background: #fff; padding: 11px 14px; min-width: 0; }
/* Isian yang isinya panjang (alamat, uraian) diberi seluruh baris, bukan
   dipaksa berbagi dengan tetangganya. */
.plw-field.is-panjang { grid-column: 1 / -1; }
/* ADA / TIDAK ADA — satu-satunya hal yang perlu dijawab di daftar isian.
   Berkasnya sendiri (nama, ukuran, status verifikasi, pratinjau) ada di
   "Dokumen & Verifikasi" tepat di bawah; mengulangnya di sini hanya membuat
   dua tempat yang harus dijaga tetap sama. */
.plw-badge { display: inline-flex; align-items: center; gap: 5px; margin-top: 3px; padding: 3px 9px; border-radius: 999px; font-size: 11.5px; font-weight: 800; }
.plw-badge.is-ada { color: #047857; background: rgba(16, 185, 129, .12); }
.plw-badge.is-kosong { color: #94a3b8; background: #f1f5f9; }
.plw-field__k { font-size: 10.5px; font-weight: 700; letter-spacing: 0.08em; color: #a2a9ba; text-transform: uppercase; }
.plw-putus__unggah { margin: 12px 0 10px; border: 1px solid #eef0f7; border-radius: 12px; padding: 10px 12px; background: #fbfbfe; }
.plw-putus__unggah-head { display: flex; align-items: center; justify-content: space-between; gap: 10px; font-size: 12px; font-weight: 800; color: #334155; }
.plw-putus__unggah-head small { font-weight: 700; font-size: 10.5px; color: #94a3b8; margin-left: 4px; }
.plw-putus__unggah-list { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 8px; }
.plw-putus__berkas { display: inline-flex; align-items: center; gap: 5px; padding: 4px 9px; border-radius: 8px; background: #fff; border: 1px solid #e6e8f2; font-size: 12px; }
.plw-putus__berkas .bi { color: #dc2626; }
.plw-putus__berkas .bi-file-earmark-image-fill { color: #6366f1; }
.plw-putus__berkas button { border: 0; background: none; padding: 0; font: inherit; font-weight: 700; color: #4f46e5; cursor: pointer; }
.plw-putus__berkas-del { border: 0; background: none; padding: 0 0 0 2px; color: #94a3b8; cursor: pointer; font-size: 10px; line-height: 1; }
.plw-putus__berkas-del:hover { color: #dc2626; }
.plw-putus__unggah-kosong { margin: 6px 0 0; font-size: 11.5px; color: #94a3b8; text-align: left; }
.plw-putus__unggah-kosong.is-wajib { display: flex; align-items: flex-start; gap: 6px; padding: 8px 10px; border-radius: 9px; line-height: 1.5; color: #b91c1c; background: rgba(239, 68, 68, .07); border: 1px solid rgba(239, 68, 68, .22); }
.plw-putus__unggah-kosong.is-wajib .bi { flex: none; margin-top: 1px; }
/* Baris aktivitas sempit (drawer 560px) dan bisa memuat 3 tombol sekaligus.
   Dulu semuanya dipaksa satu baris dengan teks keterangan, sehingga nama
   aktivitas terpotong per kata dan tombolnya saling menghimpit. Kini isi
   keterangan boleh melebar penuh dan tombol turun ke barisnya sendiri. */
.plw-test { flex-wrap: wrap; row-gap: 8px; }
.plw-test__aksi { display: flex; flex-wrap: wrap; gap: 6px; width: 100%; }
.plw-test__hdr { display: inline-flex; align-items: center; gap: 6px; padding: 5px 11px; border: 1px solid; border-radius: 9px; background: #fff; font: inherit; font-size: 12px; font-weight: 700; cursor: pointer; transition: background .16s; }
.plw-test__hdr.is-ya { color: #059669; border-color: rgba(16, 185, 129, .35); }
.plw-test__hdr.is-ya:hover { background: rgba(16, 185, 129, .08); }
.plw-test__hdr.is-tidak { color: #dc2626; border-color: rgba(220, 38, 38, .28); }
.plw-test__hdr.is-tidak:hover { background: #fef2f2; }
.plw-test__hdrtag { display: inline-flex; align-items: center; gap: 5px; padding: 4px 10px; border-radius: 999px; font-size: 11px; font-weight: 800; color: #059669; background: rgba(16, 185, 129, .12); }
.plw-test__hdrtag.is-no { color: #b91c1c; background: rgba(239, 68, 68, .1); }
.plw-hdr__note { margin: 0 0 10px; font-size: 12.5px; line-height: 1.6; color: #475569; text-align: left; }
.plw-test__jdw { display: inline-flex; align-items: center; gap: 6px; padding: 5px 11px; border: 1px solid rgba(99, 102, 241, .3); border-radius: 9px; background: #fff; font: inherit; font-size: 12px; font-weight: 700; color: #4f46e5; cursor: pointer; transition: background .16s, border-color .16s; }
.plw-test__jdw:hover { background: #f5f3ff; border-color: #a5b4fc; }
.plw-test__jdw.is-set { color: #059669; border-color: rgba(16, 185, 129, .35); }
.plw-test__jdw.is-set:hover { background: rgba(16, 185, 129, .07); }
.plw-jdw { text-align: left; }
/* DUA kendali di modal ini tetap dari Element Plus — pemilih tanggal-waktu dan
   daftar lokasi yang bisa dicari. Keduanya perkakas nyata yang tak ada
   padanannya di HTML biasa (input datetime-local tak punya kalender bahasa
   Indonesia, <select> tak bisa disaring). Yang disetel di sini bentuk luarnya
   saja, supaya sebaris dengan .plw-inp di sebelahnya — bukan kotak abu
   kebiruan bawaan yang terlihat berasal dari aplikasi lain. */
.plw-jdw :deep(.el-input__wrapper),
.plw-jdw :deep(.el-select__wrapper) {
    border-radius: 10px; padding: 3px 12px; min-height: 41px;
    box-shadow: 0 0 0 1px #e3e6f0 inset; background: #fff; transition: box-shadow .16s ease;
}
.plw-jdw :deep(.el-input__wrapper:hover),
.plw-jdw :deep(.el-select__wrapper:hover) { box-shadow: 0 0 0 1px #c7cbdb inset; }
.plw-jdw :deep(.el-input__wrapper.is-focus),
.plw-jdw :deep(.el-select__wrapper.is-focused) { box-shadow: 0 0 0 1px #818cf8 inset, 0 0 0 3px rgba(99, 102, 241, .14); }
.plw-jdw :deep(.el-input__inner),
.plw-jdw :deep(.el-select__placeholder) { font-family: inherit; font-size: 13px; color: #1e293b; }
.plw-jdw :deep(.el-input__inner::placeholder),
.plw-jdw :deep(.el-select__placeholder.is-transparent) { color: #a8b0c0; }
.plw-lok__opt { display: flex; flex-direction: column; line-height: 1.35; padding: 2px 0; }
.plw-lok__opt b { font-size: 13px; font-weight: 700; color: #1e293b; }
.plw-lok__opt small { font-size: 11px; color: #94a3b8; }
.plw-lok { margin-top: 8px; border: 1px solid #e6e8f2; border-radius: 12px; overflow: hidden; background: #fff; }
.plw-lok__map { display: block; width: 100%; height: 170px; border: 0; }
.plw-lok__info { display: flex; align-items: flex-start; gap: 9px; padding: 10px 12px; border-top: 1px solid #eef0f7; }
.plw-lok__info .bi { flex: none; color: #dc2626; margin-top: 2px; }
.plw-lok__info b { display: block; font-size: 12.5px; font-weight: 800; color: #1e293b; }
.plw-lok__info small { display: block; font-size: 11.5px; color: #64748b; margin-top: 1px; }
.plw-lok__info a { flex: none; margin-left: auto; font-size: 11.5px; font-weight: 800; color: #4f46e5; text-decoration: none; }
.plw-lb__pdf { display: block; width: 100%; height: min(74vh, 780px); border: 0; border-radius: 12px; background: #fff; }
.plw-field__file { display: inline-flex; align-items: center; gap: 7px; max-width: 100%; padding: 5px 10px; margin-top: 2px; border: 1px solid #e6e8f2; border-radius: 9px; background: #fff; font: inherit; font-size: 13px; font-weight: 700; color: #1e293b; cursor: pointer; transition: border-color .16s, background .16s; }
.plw-field__file:hover { border-color: #a5b4fc; background: #f5f3ff; }
.plw-field__file .bi { flex: none; color: #dc2626; }
.plw-field__file .bi-file-earmark-image-fill { color: #6366f1; }
.plw-field__file span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.plw-field__file em { flex: none; font-style: normal; font-size: 11.5px; font-weight: 800; color: #4f46e5; }
.plw-field__v { font-size: 13.5px; font-weight: 700; color: #1e293b; margin-top: 3px; word-break: break-word; }

/* ── ISIAN BERULANG — bentuknya SENGAJA sama dengan portal kandidat
   (.ld-rows di LamaranDetail.vue). Peninjau membuka keduanya berdampingan
   saat memverifikasi data; tata letak yang berbeda membuat mereka mengira
   isinya juga berbeda. */
.plw-rows { display: flex; flex-direction: column; gap: 6px; margin-top: 5px; }
.plw-row { display: flex; gap: 8px; align-items: flex-start; background: #f8fafc; border: 1px solid #e8eef6; border-radius: 9px; padding: 7px 9px; }
.plw-row__no { flex: none; min-width: 18px; height: 18px; display: inline-flex; align-items: center; justify-content: center; background: #e0e7ff; color: #4338ca; border-radius: 999px; font-size: 10.5px; font-weight: 800; margin-top: 1px; }
.plw-row__isi { display: flex; flex-wrap: wrap; gap: 3px 14px; min-width: 0; }
.plw-row__p { font-size: 12.5px; font-weight: 600; color: #334155; min-width: 0; word-break: break-word; }
.plw-row__p b { display: block; font-size: 10px; font-weight: 800; letter-spacing: .03em; text-transform: uppercase; color: #94a3b8; }

@media (max-width: 640px) {
    .plw-row__isi { gap: 3px 10px; }
    .plw-row__p { font-size: 12px; }
}
.plw-docs { margin-top: 14px; display: flex; flex-direction: column; gap: 9px; }
.plw-docs__label { font-size: 11px; font-weight: 800; letter-spacing: 0.12em; color: #a2a9ba; }
.plw-doc { display: flex; align-items: center; gap: 13px; padding: 12px 14px; border: 1px solid #eef0f7; border-radius: 14px; background: #fbfbfe; transition: all 0.16s; }
.plw-doc:hover { border-color: #d9def0; background: #fff; }
.plw-doc__ico { width: 40px; height: 40px; border-radius: 11px; display: flex; align-items: center; justify-content: center; flex: 0 0 auto; }
.plw-doc__ico.is-img { background: rgba(99, 102, 241, 0.12); }
.plw-doc__ico.is-pdf { background: rgba(239, 68, 68, 0.1); }
.plw-doc__toprow { display: flex; align-items: center; gap: 8px; }
.plw-doc__name { font-size: 13.5px; font-weight: 800; color: #1e293b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.plw-doc__ext { font-size: 9px; font-weight: 800; letter-spacing: 0.06em; color: #8b93a7; background: #eef0f7; border-radius: 5px; padding: 2px 6px; flex: 0 0 auto; }
.plw-doc__desc { font-size: 11.5px; color: #8b93a7; margin-top: 2px; line-height: 1.4; }
.plw-doc__eye { appearance: none; border: 1px solid #d9def0; background: #fff; width: 38px; height: 38px; border-radius: 11px; display: flex; align-items: center; justify-content: center; cursor: pointer; color: #6366f1; flex: 0 0 auto; transition: all 0.16s; }
.plw-doc__eye:hover { background: #6366f1; color: #fff; border-color: #6366f1; transform: translateY(-1px); }

/* ── Tombol keputusan ────────────────────────────────────────────────────
   Grid auto-fit, bukan flex ber-min-width tetap. Sebelumnya tiap tombol
   dipaksa minimal 130–150px; di drawer 460px (breakpoint <1180px) tiga tombol
   + gap sudah memakan 410px, sehingga label panjang pecah jadi dua baris dan
   tingginya jadi tidak sama.

   Dengan auto-fit, jumlah kolom menyesuaikan lebar yang tersedia sendiri:
   drawer lebar → 3 sebaris; menyempit → 2 + 1; sangat sempit → menumpuk.
   Tidak ada breakpoint yang perlu ditebak untuk tiap kombinasi tombol. */
.plw-foot { flex: 0 0 auto; padding: 14px 22px calc(14px + env(safe-area-inset-bottom, 0px)); background: rgba(255, 255, 255, .94); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px); border-top: 1px solid #eef0f7; box-shadow: 0 -12px 30px rgba(15, 23, 42, .07); }
.plw-actions { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 10px; }

/* SATU bentuk tombol keputusan; warnanya dipilih lewat kelas nada, karena
   daftar tombolnya kini datang dari master dan bisa bertambah. */
.plw-btn-putus {
    appearance: none; cursor: pointer; font-family: inherit; border: none;
    padding: 13px 14px; border-radius: 13px; min-height: 46px;
    font-size: 13.5px; font-weight: 800; line-height: 1.2;
    display: inline-flex; align-items: center; justify-content: center; gap: 8px;
    /* Label tidak boleh pecah di tengah frasa; kalau benar-benar sempit,
       dipotong dengan elipsis — bukan menambah tinggi tombol. */
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    min-width: 0; transition: transform .16s, background .16s;
}
.plw-btn-putus .bi { flex: none; font-size: 15px; }
.plw-btn-putus.is-lolos { background: linear-gradient(135deg, #34d399, #10b981); color: #fff; box-shadow: 0 10px 24px rgba(16, 185, 129, 0.3); }
.plw-btn-putus.is-lolos:hover { transform: translateY(-2px); }
/* Nada emas: tidak lanjut di lowongan ini, tapi datanya disimpan. */
.plw-btn-putus.is-talent { background: linear-gradient(135deg, #fbbf24, #d97706); color: #fff; box-shadow: 0 10px 24px rgba(217, 119, 6, 0.28); }
.plw-btn-putus.is-talent:hover { transform: translateY(-2px); }
.plw-btn-putus.is-gugur { background: #fff; border: 1px solid #f4c9c9; color: #dc2626; }
.plw-btn-putus.is-gugur:hover { background: #fef2f2; }
/* Tiga tombol berbagi lebar yang sama: label agak dirapatkan supaya
   "Talent Pool" tetap satu baris tanpa perlu memperkecil tombolnya. */
.plw-actions.is-three .plw-btn-putus { padding-left: 10px; padding-right: 10px; font-size: 13px; gap: 6px; }

/* KEPUTUSAN DARI KANDIDAT — baris kedua, sengaja lebih tenang. Ini bukan
   penilaian tim, jadi bobot visualnya tidak boleh menyaingi tombol di atas. */
.plw-actions2 { margin-top: 12px; padding-top: 11px; border-top: 1px dashed #e3e6f0; }
.plw-actions2__lbl { display: flex; align-items: center; gap: 6px; font-size: 9.5px; font-weight: 800; letter-spacing: .09em; color: #a2a9ba; margin-bottom: 8px; }
.plw-actions2__lbl small { letter-spacing: 0; font-weight: 700; text-transform: none; color: #7c3aed; }
.plw-actions2__row { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 8px; }
.plw-btn-kandidat {
    appearance: none; cursor: pointer; font-family: inherit;
    display: inline-flex; align-items: center; justify-content: center; gap: 7px;
    padding: 9px 12px; min-height: 38px; border-radius: 11px;
    border: 1px solid #ddd6fe; background: #faf9ff; color: #6d28d9;
    font-size: 12.5px; font-weight: 800; line-height: 1.2;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis; min-width: 0;
    transition: background .16s, border-color .16s;
}
.plw-btn-kandidat:hover:not(:disabled) { background: #f3f0ff; border-color: #c4b5fd; }
.plw-btn-kandidat:disabled { opacity: .45; cursor: not-allowed; }
.plw-btn-kandidat .bi { flex: none; }

/* Jawaban kandidat atas penawaran — kartu keadaan, bukan tombol. */
.plw-jawab { display: flex; align-items: flex-start; gap: 9px; margin-bottom: 10px; padding: 10px 12px; border-radius: 11px; font-size: 12px; line-height: 1.5; border: 1px solid; }
.plw-jawab .bi { flex: none; margin-top: 1px; font-size: 14px; }
.plw-jawab b { font-weight: 800; }
.plw-jawab p { margin: 3px 0 0; font-style: italic; opacity: .9; }
.plw-jawab.is-ya { color: #047857; background: rgba(16, 185, 129, .08); border-color: rgba(16, 185, 129, .28); }
.plw-jawab.is-no { color: #6d28d9; background: rgba(124, 58, 237, .07); border-color: rgba(124, 58, 237, .25); }
.plw-jawab.is-wait { color: #92400e; background: rgba(245, 158, 11, .09); border-color: rgba(245, 158, 11, .28); }
.plw-tp-hint { display: flex; align-items: flex-start; gap: 8px; margin: 0 0 12px; padding: 10px 12px; border-radius: 10px; font-size: 12px; line-height: 1.55; color: #92400e; background: rgba(234, 179, 8, 0.1); border: 1px solid rgba(234, 179, 8, 0.28); }
.plw-tp-hint .bi { color: #d97706; margin-top: 1px; flex: none; }

/* Ringkasan akibat keputusan — nadanya mengikuti jenis keputusan. */
.plw-putus__ring { text-align: left; margin: 0 0 12px; padding: 11px 13px; border-radius: 12px; border: 1px solid #eef0f7; background: #f8fafc; display: flex; flex-direction: column; gap: 7px; }
.plw-putus__ring.is-lulus { background: rgba(16, 185, 129, .07); border-color: rgba(16, 185, 129, .25); }
.plw-putus__ring.is-gugur { background: rgba(220, 38, 38, .06); border-color: rgba(220, 38, 38, .22); }
.plw-putus__ring.is-talent_pool { background: rgba(234, 179, 8, .08); border-color: rgba(234, 179, 8, .26); }
.plw-putus__row { display: flex; align-items: flex-start; gap: 8px; font-size: 12px; line-height: 1.55; color: #334155; }
.plw-putus__row .bi { flex: none; margin-top: 2px; color: #64748b; }
.plw-putus__row.is-mail { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 11px; color: #64748b; }

/* Centang wajib sebelum menggugurkan — sengaja mencolok, bukan sekadar teks. */
.plw-putus__cek { display: flex; align-items: flex-start; gap: 9px; margin-top: 12px; padding: 10px 12px; border-radius: 10px; cursor: pointer; font-size: 12px; line-height: 1.55; color: #7f1d1d; background: rgba(220, 38, 38, .06); border: 1px solid rgba(220, 38, 38, .22); text-align: left; }
.plw-putus__cek input { margin-top: 2px; width: 15px; height: 15px; accent-color: #dc2626; flex: none; cursor: pointer; }

/* ═══ AKTIVITAS BELUM SELESAI — pengakuan sadar sebelum memutus ═══
   Nadanya PERINGATAN, bukan galat: memutus lebih awal kadang memang benar
   (aktivitas sengaja dilewati), jadi ini menahan sebentar, bukan melarang. */
.plw-belum { margin-top: 12px; padding: 11px 12px; border-radius: 12px; background: #fffbeb; border: 1px solid #fcd34d; }
.plw-belum__head { display: flex; align-items: flex-start; gap: 8px; font-size: 12.5px; color: #92400e; line-height: 1.5; }
.plw-belum__head i { flex: none; margin-top: 1px; color: #d97706; }
.plw-belum__head b { color: #78350f; }
.plw-belum__list { margin: 8px 0 0; padding-left: 26px; font-size: 11.5px; color: #92400e; line-height: 1.65; }
.plw-belum__list li { margin-bottom: 2px; }
.plw-belum__list em { font-style: normal; color: #b45309; opacity: .8; }
.plw-belum__list em::before { content: ' — '; }
.plw-putus__cek.is-warn { margin-top: 10px; color: #78350f; background: rgba(217, 119, 6, .1); border-color: rgba(217, 119, 6, .3); }
.plw-putus__cek.is-warn input { accent-color: #d97706; }
/* ═══ PILIHAN TALENT POOL saat kandidat mundur ═══
   Dua kartu setara, bukan satu centang. Menyimpan atau tidak menyimpan
   sama-sama sah — dan centang yang sudah tercentang cenderung dilewati begitu
   saja, sehingga hampir semua yang mundur ikut tersimpan tanpa pernah benar
   benar dipertimbangkan. */
.plw-tp { margin-top: 12px; padding: 11px 12px; border-radius: 12px; background: #f8fafc; border: 1px solid #e6e9f0; }
.plw-tp__head { display: flex; align-items: center; gap: 7px; margin-bottom: 9px; font-size: 12px; font-weight: 800; color: #334155; }
.plw-tp__head i { color: #6366f1; }
.plw-tp__opts { display: flex; flex-direction: column; gap: 7px; }
.plw-tp__opt { display: flex; align-items: flex-start; gap: 9px; width: 100%; padding: 9px 11px; border-radius: 10px; border: 1.5px solid #e2e8f0; background: #fff; cursor: pointer; text-align: left; transition: border-color .15s, background .15s; }
.plw-tp__opt:hover { border-color: #cbd5e1; }
.plw-tp__ico { flex: none; display: grid; place-items: center; width: 26px; height: 26px; border-radius: 8px; background: #f1f5f9; color: #94a3b8; font-size: 13px; }
.plw-tp__txt { min-width: 0; display: flex; flex-direction: column; gap: 2px; }
.plw-tp__txt b { font-size: 12.5px; font-weight: 800; color: #1e293b; }
.plw-tp__txt small { font-size: 11.5px; line-height: 1.5; color: #64748b; }
.plw-tp__opt.is-simpan.is-on { border-color: #f59e0b; background: rgba(245, 158, 11, .07); }
.plw-tp__opt.is-simpan.is-on .plw-tp__ico { background: rgba(245, 158, 11, .16); color: #b45309; }
.plw-tp__opt.is-lepas.is-on { border-color: #64748b; background: rgba(100, 116, 139, .08); }
.plw-tp__opt.is-lepas.is-on .plw-tp__ico { background: rgba(100, 116, 139, .16); color: #475569; }

/* Indikator kuota di area keputusan */
.plw-kuota { display: flex; align-items: center; gap: 8px; margin-bottom: 10px; padding: 9px 12px; border-radius: 10px; font-size: 12px; font-weight: 700; color: #3730a3; background: rgba(99, 102, 241, 0.1); border: 1px solid rgba(99, 102, 241, 0.25); }
.plw-kuota.is-penuh { color: #b91c1c; background: rgba(239, 68, 68, 0.09); border-color: rgba(239, 68, 68, 0.28); }
/* Gerbang kehadiran — nadanya "belum saatnya", bukan "ada yang salah". */
.plw-kuota.is-hadir { align-items: flex-start; color: #92400e; background: rgba(245, 158, 11, 0.1); border-color: rgba(245, 158, 11, 0.3); line-height: 1.5; }
.plw-kuota.is-hadir .bi { margin-top: 1px; }
.plw-kuota .bi { flex: none; }
/* Upload berkas hasil (MCU/Interview) */
.plw-upload { margin-bottom: 12px; padding: 11px 13px; border-radius: 12px; border: 1px solid rgba(15, 23, 42, 0.1); background: #f8fafc; }
.plw-upload__head { display: flex; align-items: center; justify-content: space-between; gap: 8px; }
.plw-upload__head > span { font-size: 12.5px; font-weight: 800; color: #334155; display: inline-flex; align-items: center; gap: 6px; }
.plw-req { font-size: 9.5px; font-weight: 800; text-transform: uppercase; color: #b91c1c; background: rgba(239, 68, 68, 0.12); border-radius: 999px; padding: 2px 7px; }
.plw-opt { font-size: 9.5px; font-weight: 800; text-transform: uppercase; color: #64748b; background: #eef0f7; border-radius: 999px; padding: 2px 7px; }
.plw-upbtn { display: inline-flex; align-items: center; gap: 5px; font-size: 11.5px; font-weight: 700; color: #4f46e5; background: #eef2ff; border: 1px solid rgba(79, 70, 229, 0.25); border-radius: 8px; padding: 6px 11px; cursor: pointer; }
.plw-upbtn.is-busy { opacity: 0.6; pointer-events: none; }
.plw-files { display: flex; flex-direction: column; gap: 5px; margin-top: 9px; }
.plw-file { display: flex; align-items: center; gap: 8px; background: #fff; border: 1px solid rgba(15, 23, 42, 0.08); border-radius: 8px; padding: 6px 9px; }
.plw-file > .bi { color: #ef4444; font-size: 15px; flex: none; }
.plw-file__name { flex: 1; text-align: left; border: none; background: none; cursor: pointer; font-size: 12px; color: #334155; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.plw-file__name:hover { color: #4f46e5; text-decoration: underline; }
.plw-file__del { border: none; background: none; color: #94a3b8; cursor: pointer; padding: 2px; }
.plw-file__del:hover { color: #dc2626; }
.plw-files__empty { margin-top: 8px; font-size: 11.5px; color: #94a3b8; }
.plw-btn-putus:disabled { opacity: 0.45; cursor: not-allowed; transform: none; box-shadow: none; }

/* ═══ LIGHTBOX ═══ */
/* Lightbox HARUS di atas modal keputusan (.wca-modal-mask = 1200).
   Dulu 1090, sehingga pratinjau berkas yang dibuka DARI DALAM modal
   muncul di belakangnya — terlihat seperti tombolnya tidak berfungsi. */
.plw-lb { position: fixed; inset: 0; z-index: 1250; display: flex; align-items: center; justify-content: center; padding: 24px; background: rgba(10, 10, 20, 0.72); backdrop-filter: blur(6px); transition: opacity 0.28s; opacity: 0; pointer-events: none; }
.plw-lb.is-on { opacity: 1; pointer-events: auto; }
/* PDF butuh ruang baca; gambar tetap nyaman di lebar ini. */
.plw-lb__wrap.is-pdf { max-width: 900px; }
.plw-lb__wrap { max-width: 520px; width: 100%; animation: plwPop 0.3s cubic-bezier(0.34, 1.56, 0.64, 1) both; }
@keyframes plwPop { 0% { opacity: 0; transform: scale(0.4); } 60% { transform: scale(1.12); } 100% { opacity: 1; transform: scale(1); } }
.plw-lb__bar { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 14px; }
.plw-lb__id { display: flex; align-items: center; gap: 11px; color: #fff; min-width: 0; }
.plw-lb__ico { width: 40px; height: 40px; border-radius: 11px; background: rgba(255, 255, 255, 0.14); display: flex; align-items: center; justify-content: center; flex: 0 0 auto; }
.plw-lb__name { font-size: 15px; font-weight: 800; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.plw-lb__desc { font-size: 12px; color: rgba(255, 255, 255, 0.6); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.plw-lb__close { appearance: none; border: 1px solid rgba(255, 255, 255, 0.2); background: rgba(255, 255, 255, 0.08); width: 40px; height: 40px; border-radius: 12px; display: flex; align-items: center; justify-content: center; cursor: pointer; color: #fff; transition: background 0.16s; flex: 0 0 auto; }
.plw-lb__close:hover { background: rgba(255, 255, 255, 0.18); }
.plw-lb__card { background: #fff; border-radius: 18px; overflow: hidden; box-shadow: 0 30px 80px rgba(0, 0, 0, 0.5); }
.plw-lb__card img { display: block; width: 100%; max-height: 420px; object-fit: contain; background: #0f172a; }
/* Tinggi SAMA dengan viewer-nya, supaya pergantian spinner -> isi
   tidak membuat kotaknya melompat tinggi. */
.plw-lb__state { width: 100%; min-height: 340px; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 12px; background: linear-gradient(135deg, #f1f2f9, #e8eaf6); color: #64748b; font-size: 13.5px; font-weight: 700; }
.plw-lb__retry { appearance: none; cursor: pointer; font-family: inherit; font-size: 12.5px; font-weight: 800; color: #fff; border: none; padding: 9px 18px; border-radius: 11px; background: linear-gradient(135deg, #8b5cf6, #6366f1); box-shadow: 0 8px 20px rgba(99, 102, 241, 0.28); transition: transform 0.16s; }
.plw-lb__retry:hover { transform: translateY(-1px); }
.plw-lb__foot { padding: 14px 18px; display: flex; align-items: center; justify-content: space-between; gap: 10px; border-top: 1px solid #eef0f7; }
.plw-lb__ok { display: inline-flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 800; color: #059669; flex: 0 0 auto; }

/* TOAST */
/* z-index 1300 = DI ATAS modal EVO (.wca-modal-mask 1200), supaya toast tetap terbaca saat modal terbuka. */
.plw-toast { position: fixed; bottom: 24px; right: 24px; z-index: 1300; display: flex; align-items: center; gap: 9px; padding: 12px 18px; border-radius: 13px; background: #0f172a; color: #fff; font-size: 13.5px; font-weight: 700; box-shadow: 0 18px 40px rgba(0, 0, 0, 0.3); }
.plw-toast.is-err { background: #dc2626; }
.plw-toast .bi { color: #34d399; }
.plw-toast.is-err .bi { color: #fff; }
.plw-toast-enter-active, .plw-toast-leave-active { transition: opacity 0.25s, transform 0.25s; }
.plw-toast-enter-from, .plw-toast-leave-to { opacity: 0; transform: translateY(12px); }

/* ═══ RESPONSIF ═══ */
@media (max-width: 1179.98px) {
    .plw-panel { width: 264px; flex-basis: 264px; }
    .plw-drawer { width: 460px; }
}
@media (max-width: 991.98px) {
    .plw { flex-direction: column; height: auto; overflow: visible; }
    .plw-panel { width: 100%; flex: 0 0 auto; border-right: 0; border-bottom: 1px solid rgba(226, 232, 240, 0.75); }
    .plw-panel__list { flex-direction: row; overflow-x: auto; padding: 4px 16px 14px; }
    .plw-prog { flex: 0 0 250px; }
    .plw-kanban { flex-direction: column; }
    .plw-col { width: 100%; flex: 1 1 auto; }
    .plw-drawer { width: 100%; }
    .plw-main { padding: 20px 16px 44px; }
}
/* Ponsel: tombol keputusan menumpuk penuh selebar drawer. Di lebar sekecil ini
   tiga tombol sebaris membuat labelnya terpotong elipsis — lebih baik satu per
   satu, dan sekalian lebih aman disentuh. */
@media (max-width: 575.98px) {
    .plw-actions, .plw-actions.is-three { grid-template-columns: 1fr; }
    .plw-actions.is-three .plw-btn-putus { font-size: 13.5px; padding-left: 14px; padding-right: 14px; gap: 8px; }
}

/* ══════════════════════════════════════════════════════════════════════════
   RAPOR TES — kartu padat, rincian di balik satu tombol
   ══════════════════════════════════════════════════════════════════════════
   Bentuk lama menaruh SEMUANYA sekaligus di dalam kartu: nama, tag, jadwal,
   catatan penilaian berformat, berkas kandidat, berkas tim, lalu deretan
   tombol. Untuk satu wawancara itu masih terbaca. Untuk tahap berisi empat
   sampai enam tes offline — bentuk yang justru paling sering dipakai — satu
   layar berubah jadi dinding teks setinggi beberapa gulungan, dan tombol yang
   benar-benar perlu ditekan tenggelam di tengahnya.

   Sekarang kartunya menyatakan KEADAAN (nama, status, apa yang ditunggu) dan
   menawarkan TINDAKAN. Bahan bacaan — catatan & berkas — ada di balik satu
   tombol berjumlah, dibuka hanya untuk aktivitas yang sedang ditinjau.
   ═════════════════════════════════════════════════════════════════════════ */

/* Kartu jadi GRID, bukan flex sebaris.
   Flex + flex-wrap membuat pil status melompat ke baris sendiri pada lebar
   tertentu lalu kembali naik pada lebar lain — posisinya berubah-ubah dan mata
   kehilangan tempat membacanya. Grid mengunci kolomnya: ikon | isi | status. */
.plw-test {
    display: grid;
    grid-template-columns: auto minmax(0, 1fr) auto;
    align-items: start;
    gap: 6px 11px;
    padding: 12px 13px;
}
.plw-test__ico { grid-row: 1; align-self: center; }
.plw-test__main { grid-column: 2; min-width: 0; }
/* Skor & pil status berbagi kolom kanan, menumpuk ke bawah. */
.plw-test__score,
.plw-test__pill { grid-column: 3; justify-self: end; align-self: center; }
.plw-test__aksi { grid-column: 1 / -1; display: flex; flex-wrap: wrap; gap: 6px; }

/* Apa yang masih ditunggu dari aktivitas ini — kalimat yang sama dengan yang
   dipakai server saat menolak keputusan. */
.plw-test__nunggu {
    margin-top: 5px; display: inline-flex; align-items: center; gap: 5px;
    padding: 3px 9px; border-radius: 8px; font-size: 11px; font-weight: 700;
    color: #b45309; background: rgba(245, 158, 11, .1); border: 1px solid rgba(245, 158, 11, .26);
}

/* Tombol pembuka rincian. */
.plw-test__more {
    margin-top: 7px; display: inline-flex; align-items: center; gap: 6px;
    padding: 4px 10px; border: 1px solid #e2e8f0; background: #fff; border-radius: 9px;
    font: inherit; font-size: 11px; font-weight: 700; color: #475569; cursor: pointer;
    transition: background .15s, border-color .15s, color .15s;
}
.plw-test__more:hover { background: #f8fafc; border-color: #cbd5e1; color: #1e293b; }
.plw-test__more.is-on { background: #eef2ff; border-color: #c7d2fe; color: #4338ca; }
.plw-test__morecount {
    display: inline-grid; place-items: center; min-width: 17px; height: 17px; padding: 0 5px;
    border-radius: 999px; background: #eef2ff; color: #4338ca; font-size: 10px; font-weight: 800;
}
.plw-test__more.is-on .plw-test__morecount { background: #fff; }

.plw-test__detail {
    margin-top: 9px; padding: 11px 12px; border-radius: 11px;
    background: #fafbfe; border: 1px solid #eef1f8;
    display: flex; flex-direction: column; gap: 10px;
}
.plw-test__detail .plw-test__cat { margin-top: 0; }
.plw-test__detail .plw-test__cat.is-kaya { background: #fff; }

/* ── BERKAS: kepala dan daftarnya DIPISAH BARIS ────────────────────────────
   Dulu label, seluruh nama berkas, dan lencana "dinyatakan lengkap" berdesakan
   di satu baris flex-wrap. Begitu kandidat berhasil mengunggah, nama berkas
   yang panjang mendorong lencananya ke posisi yang berubah-ubah — kadang
   terjepit di antara dua berkas, kadang menggantung sendirian. Kepala terpisah
   membuat lencananya selalu di tempat yang sama, apa pun isinya. */
.plw-test__kirim { margin-top: 0; display: flex; flex-direction: column; align-items: stretch; gap: 6px; font-size: 11px; }
.plw-test__kirimhead { display: flex; align-items: center; justify-content: space-between; gap: 8px; flex-wrap: wrap; }
.plw-test__files { display: flex; flex-wrap: wrap; gap: 6px; min-width: 0; }
/* Nama panjang DIPOTONG, tidak mendorong tetangganya. Judul lengkapnya tetap
   terbaca lewat tooltip, dan isinya lewat modal. */
.plw-test__kirimfile { max-width: 100%; min-width: 0; }
.plw-test__filenama { min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.plw-test__kirimstat { flex: 0 0 auto; }
.plw-test__kirimkosong {
    margin-top: 6px; display: flex; align-items: flex-start; gap: 6px; font-size: 11px;
    line-height: 1.45; color: #94a3b8; font-weight: 700;
}
.plw-test__kirimkosong > .bi { flex: none; margin-top: 1px; }

/* Daftar "apa yang belum tuntas" di atas tombol keputusan. */
.plw-kuota__isi { min-width: 0; display: flex; flex-direction: column; gap: 4px; }
.plw-kuota__list { margin: 0; padding-left: 17px; display: flex; flex-direction: column; gap: 2px; }
.plw-kuota__list li { line-height: 1.45; }

/* ══════════════════════════════════════════════════════════════════════════
   DRAWER — satu rumus lebar untuk ponsel sampai 4K
   ══════════════════════════════════════════════════════════════════════════
   Lebar tetap 560px punya dua ujung yang sama-sama buruk: di ponsel ia
   dipangkas max-width jadi 100% (kebetulan benar), dan di layar 4K ia tetap
   560px — pita sempit di tepi kanvas raksasa, sementara isinya justru padat
   dan butuh ruang. clamp() menjawab keduanya sekaligus tanpa satu pun media
   query, dan batas atas 860px menjaga baris teks tetap terbaca: kolom yang
   terlalu lebar memaksa mata melompat balik mencari awal baris berikutnya.
   ═════════════════════════════════════════════════════════════════════════ */
.plw-drawer { width: min(100%, clamp(560px, 40vw, 860px)); }

@media (max-width: 640px) {
    /* Kepala drawer: avatar + nama + tombol tak muat sebaris di ponsel. */
    .plw-drawer__head { padding: 14px 16px; }
    .plw-drawer__headrow { flex-wrap: wrap; gap: 10px; }
    .plw-drawer__avatar { width: 44px; height: 44px; border-radius: 13px; font-size: 15px; }
    .plw-drawer__name { font-size: 17px; }
    .plw-drawer__body { padding: 16px 16px 24px; gap: 14px; }
    .plw-test { padding: 11px; gap: 5px 9px; }
    /* Pil status turun menemani isinya — di lebar ini kolom ketiga
       menyisakan terlalu sedikit ruang untuk nama aktivitas. */
    .plw-test { grid-template-columns: auto minmax(0, 1fr); }
    .plw-test__score, .plw-test__pill { grid-column: 2; justify-self: start; }
    .plw-test__aksi > * { flex: 1 1 auto; justify-content: center; }
}

/* Layar sangat lebar: kartu boleh bernapas, tapi teksnya tidak boleh melar. */
@media (min-width: 1920px) {
    .plw-drawer__body { padding: 24px 28px 34px; }
    .plw-test { padding: 14px 16px; }
    .plw-test__name { font-size: 13.5px; }
}

/* Petunjuk & pintasan di bawah bidang isian. */
.plw-fld__hint { margin: 5px 0 0; font-size: 11px; line-height: 1.5; color: #94a3b8; }
.plw-fld__isi {
    margin-top: 6px; display: inline-flex; align-items: center; gap: 5px;
    border: 1px dashed #cbd5e1; background: #fff; border-radius: 8px; padding: 4px 9px;
    font: inherit; font-size: 11px; font-weight: 700; color: #475569; cursor: pointer;
}
.plw-fld__isi:hover { background: #f8fafc; border-color: #94a3b8; color: #1e293b; }
/* Segmented mode: tiga bentuk harus muat di modal sempit tanpa terpotong. */
.plw-seg { display: flex; flex-wrap: wrap; gap: 6px; }
.plw-seg .plw-seg__b { flex: 1 1 130px; min-width: 0; justify-content: center; }

/* PENANDA PEMBACA — dipakai di mana pun ada dua kotak isian berdampingan yang
   tujuannya berbeda hanya pada SIAPA YANG MEMBACANYA. Warnanya kontras penuh
   (biru vs abu) karena inilah pembeda satu-satunya; bila ia selembut label
   biasa, matanya terlewat dan orang mengisi kotak yang salah. */
.plw-lihat {
    display: inline-flex; align-items: center; gap: 4px; margin-left: 7px;
    padding: 1px 7px; border-radius: 999px; font-size: 9.5px; font-weight: 800;
    letter-spacing: .04em; vertical-align: middle; white-space: nowrap;
}
.plw-lihat.is-publik   { background: rgba(2, 132, 199, .12); color: #0369a1; border: 1px solid rgba(2, 132, 199, .3); }
.plw-lihat.is-internal { background: rgba(100, 116, 139, .12); color: #475569; border: 1px solid rgba(100, 116, 139, .26); }
/* Peringatan privasi menempel PADA bidangnya, bukan melayang di bawah kotak
   hasil — supaya terbaca sebagai aturan untuk kotak yang sedang diisi. */
.plw-mform .plw-fld .plw-note.is-lock { margin-top: 6px; }

/* Peringatan akibat yang tidak bisa ditarik — lebih tegas dari petunjuk biasa,
   karena dua dari tiga jawaban menutup lamaran seketika. */
.plw-fld__hint.is-tegas { color: #b45309; font-weight: 700; }
.plw-fld__hint.is-tegas b { color: #92400e; }
</style>
