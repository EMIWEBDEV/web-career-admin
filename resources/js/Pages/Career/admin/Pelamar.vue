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

                    <div class="plw-tabs">
                        <button type="button" class="plw-tab" :class="{ 'is-run': statusTab === 'AKTIF' }" @click="statusTab = 'AKTIF'">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M5 3h14l-6 8v7l-2 1v-8L5 3z" /></svg>
                            Berjalan <span class="plw-tab__badge">{{ jmlAktif }}</span>
                        </button>
                        <button type="button" class="plw-tab" :class="{ 'is-rej': statusTab === 'GUGUR' }" @click="statusTab = 'GUGUR'">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9" /><path d="M15 9l-6 6M9 9l6 6" /></svg>
                            Tidak Lolos <span class="plw-tab__badge">{{ jmlGugur }}</span>
                        </button>
                    </div>

                    <!-- LOWONGAN PROGRAM INI — sekaligus penyaring papan.
                         Satu program MT menaungi beberapa posisi dengan kuota
                         sendiri-sendiri; tanpa ini admin tidak bisa menjawab
                         "posisi mana yang pelamarnya kurang / kuotanya penuh". -->
                    <div v-if="(detail.posisi || []).length" class="plw-lows">
                        <button type="button" class="plw-low" :class="{ 'is-on': posisiPilih === '' }" @click="posisiPilih = ''">
                            <span class="plw-low__nama">Semua Lowongan</span>
                            <span class="plw-low__meta">{{ (detail.pelamar || []).length }} pelamar</span>
                        </button>
                        <button
                            v-for="p in detail.posisi" :key="p.id" type="button"
                            class="plw-low" :class="{ 'is-on': posisiPilih === p.id, 'is-penuh': p.kuota > 0 && p.lolos >= p.kuota }"
                            @click="posisiPilih = posisiPilih === p.id ? '' : p.id"
                        >
                            <span class="plw-low__nama">
                                {{ p.posisi }}
                                <span v-if="p.level" class="plw-low__lvl">{{ p.level }}</span>
                            </span>
                            <span class="plw-low__meta">
                                <span v-if="p.departemen" class="plw-ell"><i class="bi bi-diagram-3"></i> {{ p.departemen }}</span>
                                <span v-if="p.lokasi"><i class="bi bi-geo-alt"></i> {{ p.lokasi }}</span>
                                <span v-if="p.mppRef" class="plw-low__mpp">{{ p.mppRef }}</span>
                            </span>
                            <span class="plw-low__angka">
                                <b>{{ p.berjalan }}</b> berjalan ·
                                <b>{{ p.lolos }}</b>/{{ p.kuota || '—' }} kursi
                                <template v-if="p.gugur"> · {{ p.gugur }} gugur</template>
                            </span>
                        </button>
                    </div>

                    <div v-if="loadingDetail" class="plw-load" style="padding: 3rem 0"><span class="plw-spin"></span> Memuat papan seleksi…</div>

                    <!-- KANBAN -->
                    <div v-else class="plw-kanban" :class="{ 'is-gugur': statusTab === 'GUGUR' }">
                        <div v-for="(col, i) in kolomTampil" :key="col.kode || col.label" class="plw-col">
                            <div class="plw-col__head">
                                <span class="plw-col__num" :class="{ 'is-hot': kartuKolom(col).length > 0 }">{{ String(i + 1).padStart(2, '0') }}</span>
                                <span class="plw-col__name">
                                    <span class="plw-ell">{{ col.label }}</span>
                                    <svg v-if="col.provider === 'THIRD_PARTY'" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="2" style="flex: 0 0 auto"><rect x="4" y="4" width="16" height="16" rx="2" /><path d="M9 9h6v6H9z" /></svg>
                                </span>
                                <span class="plw-col__count">{{ kartuKolom(col).length }}</span>
                            </div>
                            <div class="plw-col__cards">
                                <button v-for="r in kartuKolom(col)" :key="r.id" type="button" class="plw-card" @click="bukaKandidat(r)">
                                    <span class="plw-card__toprow">
                                        <span class="plw-card__avatar" :style="{ background: avatarBg(r) }">{{ inisial(r.pelamar) }}</span>
                                        <span class="plw-card__id">
                                            <span class="plw-card__name">{{ r.pelamar }}</span>
                                            <span class="plw-card__code">{{ r.lamaranKode }}</span>
                                        </span>
                                    </span>
                                    <span class="plw-card__pos">{{ r.posisi }}</span>
                                    <!-- Konteks lowongan langsung di kartu: admin tidak
                                         perlu membuka satu per satu untuk tahu departemen,
                                         lokasi, dan sudah berapa lama menunggu. -->
                                    <span v-if="r.departemen || r.lokasi" class="plw-card__meta">
                                        <span v-if="r.departemen" class="plw-ell"><i class="bi bi-diagram-3"></i> {{ r.departemen }}</span>
                                        <span v-if="r.lokasi"><i class="bi bi-geo-alt"></i> {{ r.lokasi }}</span>
                                    </span>
                                    <span class="plw-card__foot">
                                        <span class="plw-card__chip" :class="'tone-' + r.badge.tone">{{ r.badge.teks.toUpperCase() }}</span>
                                        <span v-if="r.waktuLamar" class="plw-card__umur" :title="'Melamar ' + tglId(r.waktuLamar)">
                                            <i class="bi bi-clock-history"></i> {{ umurHari(r.waktuLamar) }}
                                        </span>
                                    </span>
                                </button>
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
                                Sistem yang memutuskan begitu seluruh aktivitas penentu selesai — admin tidak mengetuk palu di sini.
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
                        <div v-else-if="detailKandidat.siapDiputus" class="plw-sysnote is-ready">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#059669" stroke-width="2.2" stroke-linecap="round" style="flex: 0 0 auto; margin-top: 1px"><path d="M20 6L9 17l-5-5" /></svg>
                            <div><b>Semua hasil sudah masuk.</b> Tinjau rapor tes di bawah, lalu putuskan Loloskan / Tidak Lolos.</div>
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
                            <div v-for="t in detailKandidat.tests" :key="t.id" class="plw-test">
                                <span class="plw-test__ico" :class="t.provider === 'THIRD_PARTY' ? 'is-sys' : 'is-man'">
                                    <i class="bi" :class="t.provider === 'THIRD_PARTY' ? 'bi-robot' : 'bi-person-workspace'"></i>
                                </span>
                                <div class="plw-test__main">
                                    <div class="plw-test__name">
                                        {{ t.label }}
                                        <span v-if="t.peran === 'INFORMATIF'" class="plw-test__tag is-info" title="Skor hanya bahan pertimbangan — tidak menentukan lulus">informatif</span>
                                        <span v-if="!t.wajib" class="plw-test__tag">opsional</span>
                                    </div>
                                    <div class="plw-test__sub">{{ t.jenisTes || 'Aktivitas internal' }}</div>
                                    <div v-if="t.catatan" class="plw-test__cat"><i class="bi bi-chat-left-text"></i> {{ t.catatan }}</div>
                                </div>
                                <span v-if="t.nilai != null" class="plw-test__score">{{ t.nilai }}</span>
                                <span class="plw-test__pill" :class="pillTes(t)">{{ labelTes(t) }}</span>
                                <!-- Tombol ini HANYA untuk aktivitas yang punya hasil
                                     sendiri (tes ber-jenis, atau tahap berisi beberapa
                                     aktivitas). Untuk tahap satu-aktivitas seperti
                                     Seleksi Administrasi / formulir, hasilnya ADALAH
                                     keputusan Loloskan / Tidak Lolos di bawah — server
                                     yang menentukan lewat dapatDicatat. -->
                                <button
                                    v-if="bisaCatat(t)"
                                    type="button" class="plw-test__rec" title="Rekam hasil aktivitas ini — mesin langsung mengevaluasi tahap"
                                    @click="askCatat(t)"
                                >
                                    <i class="bi bi-pencil-square"></i> Catat Hasil
                                </button>
                                <button
                                    v-if="bisaTidakHadir(t)"
                                    type="button" class="plw-test__skip" title="Tandai kandidat tidak hadir pada tes ini (tahap tidak lagi menunggu hasilnya)"
                                    @click="tandaiTidakHadir(t)"
                                >
                                    <i class="bi bi-person-x"></i> Tidak hadir
                                </button>
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
                                        <div v-for="j in f.jawaban" :key="j.key" class="plw-field">
                                            <div class="plw-field__k">{{ j.label }}</div>
                                            <div class="plw-field__v">{{ j.nilai || '—' }}</div>
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

                    <!-- Upload berkas hasil (MCU/Interview) — PDF/JPG oleh admin/requester -->
                    <div v-if="detailKandidat.butuhKeputusan && bolehUpload(detailKandidat)" class="plw-upload">
                        <div class="plw-upload__head">
                            <span><i class="bi bi-paperclip"></i> Berkas Hasil
                                <small v-if="wajibUpload(detailKandidat)" class="plw-req">wajib</small>
                                <small v-else class="plw-opt">opsional</small>
                            </span>
                            <label class="plw-upbtn" :class="{ 'is-busy': berkasBusy }">
                                <i class="bi bi-upload"></i> {{ berkasBusy ? 'Mengunggah…' : 'Unggah PDF/JPG' }}
                                <input type="file" accept=".pdf,.jpg,.jpeg" hidden :disabled="berkasBusy" @change="unggahBerkas">
                            </label>
                        </div>
                        <div v-if="berkasHasil.length" class="plw-files">
                            <div v-for="b in berkasHasil" :key="b.id" class="plw-file">
                                <i class="bi" :class="b.ext === 'pdf' ? 'bi-file-earmark-pdf' : 'bi-file-earmark-image'"></i>
                                <button type="button" class="plw-file__name" @click="previewBerkas(b)">{{ b.nama }}</button>
                                <button type="button" class="plw-file__del" title="Hapus" @click="hapusBerkas(b)"><i class="bi bi-x-lg"></i></button>
                            </div>
                        </div>
                        <div v-else class="plw-files__empty">
                            Belum ada berkas. <template v-if="wajibUpload(detailKandidat)"><b>Wajib</b> diunggah sebelum Loloskan.</template>
                        </div>
                    </div>

                    <!-- Indikator kuota (muncul saat kandidat di tahap akhir & posisi berkuota) -->
                    <div v-if="detailKandidat.butuhKeputusan && detailKandidat.diTahapAkhir && detailKandidat.kuota > 0" class="plw-kuota" :class="{ 'is-penuh': detailKandidat.kuotaPenuh }">
                        <i class="bi" :class="detailKandidat.kuotaPenuh ? 'bi-lock-fill' : 'bi-people-fill'"></i>
                        <span v-if="detailKandidat.kuotaPenuh">Kuota penuh ({{ detailKandidat.terisiKuota }}/{{ detailKandidat.kuota }}) — Loloskan dinonaktifkan. Gunakan Talent Pool / Tidak Lolos.</span>
                        <span v-else>Sisa <b>{{ detailKandidat.sisaKuota }}</b> kursi dari {{ detailKandidat.kuota }} (terisi {{ detailKandidat.terisiKuota }}).</span>
                    </div>

                    <!-- Aksi keputusan — 2 atau 3 tombol tergantung tahap. Tombol
                         "Masuk Talent Pool" hanya muncul bila tahap ini di-cut-off
                         ke Talent Pool (diatur di Master Tahapan Seleksi). Loloskan
                         disembunyikan bila kuota penuh di tahap terakhir. -->
                    <div v-if="detailKandidat.butuhKeputusan" class="plw-actions" :class="{ 'is-three': bolehTalentPool(detailKandidat) && !kuotaBlokir(detailKandidat) }">
                        <button v-if="!kuotaBlokir(detailKandidat)" type="button" class="plw-btn-lolos" :disabled="uploadKurang(detailKandidat)" :title="uploadKurang(detailKandidat) ? 'Unggah berkas hasil dulu' : ''" @click="askPutus(detailKandidat, 'LULUS')">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round"><path d="M20 6L9 17l-5-5" /></svg>
                            Loloskan
                        </button>
                        <button v-if="bolehTalentPool(detailKandidat)" type="button" class="plw-btn-talent" @click="askPutus(detailKandidat, 'TALENT_POOL')">
                            <i class="bi bi-stars"></i>
                            Masuk Talent Pool
                        </button>
                        <button type="button" class="plw-btn-gugur" @click="askPutus(detailKandidat, 'GUGUR')">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M18 6L6 18M6 6l12 12" /></svg>
                            Tidak Lolos
                        </button>
                    </div>
                </div>
            </template>
        </section>

        <!-- ═══ LIGHTBOX BERKAS GAMBAR ═══ -->
        <div class="plw-lb" :class="{ 'is-on': !!lightbox }" @click="lightbox = null">
            <div v-if="lightbox" class="plw-lb__wrap" @click.stop>
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
                    <img v-show="!lbLoading && !lbError" :src="lbSrc" :alt="lightbox.nama" @load="lbLoading = false" @error="lbLoading = false; lbError = true" />
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
            :danger="putusHasil === 'GUGUR'"
            :confirm-label="putusLabelKonfirm"
            :busy="sibuk"
            :confirm-disabled="!bolehKonfirmPutus"
            @confirm="konfirmPutus"
            @cancel="konfirmShow = false"
        >
            <!-- Ringkas apa yang akan terjadi. Keputusan ini mengirim email ke
                 kandidat dan tidak bisa ditarik kembali, jadi disebutkan di muka
                 alih-alih membiarkan admin menebak. -->
            <div class="plw-putus__ring" :class="`is-${(putusHasil || '').toLowerCase()}`">
                <div v-if="putusTarget" class="plw-putus__row">
                    <i class="bi bi-person-badge"></i>
                    <span><b>{{ putusTarget.pelamar }}</b> · {{ putusTarget.posisi || '—' }}<template v-if="putusTarget.departemen"> · {{ putusTarget.departemen }}</template></span>
                </div>
                <div v-if="putusHasil === 'LULUS'" class="plw-putus__row">
                    <i class="bi bi-arrow-right-circle"></i>
                    <span>Maju ke tahap berikutnya dan <b>email pemberitahuan dikirim</b> ke kandidat.</span>
                </div>
                <div v-else-if="putusHasil === 'GUGUR'" class="plw-putus__row">
                    <i class="bi bi-x-octagon"></i>
                    <span>Proses seleksi <b>dihentikan</b> dan <b>email pemberitahuan dikirim</b> ke kandidat.</span>
                </div>
                <div v-else class="plw-putus__row">
                    <i class="bi bi-stars"></i>
                    <span>Tidak melanjutkan di lowongan ini, tetapi disimpan di <b>Talent Pool</b>. Kuota tidak terpotong, dan <b>tidak ada email</b> yang dikirim.</span>
                </div>
                <div v-if="putusTarget && putusTarget.email" class="plw-putus__row is-mail">
                    <i class="bi bi-envelope"></i>
                    <span>{{ putusHasil === 'TALENT_POOL' ? 'Tidak dikirimi email' : putusTarget.email }}</span>
                </div>
            </div>

            <el-input v-model="putusCatatan" type="textarea" :rows="2" :placeholder="putusHasil === 'TALENT_POOL' ? 'Catatan / tag (mis. kuat wawancara, cocok Finance)' : 'Catatan (mis. sesuai rekomendasi sistem / alasan khusus)'" />

            <!-- Menggugurkan itu tak bisa dibatalkan dan kandidat langsung
                 dikabari. Centang ini memaksa jeda sadar sebelum mengirim. -->
            <label v-if="putusHasil === 'GUGUR'" class="plw-putus__cek">
                <input v-model="putusSetuju" type="checkbox" />
                <span>Saya paham keputusan ini <b>final</b> dan email pemberitahuan akan <b>langsung dikirim</b> ke kandidat.</span>
            </label>
        </ConfirmModal>

        <!-- Catat hasil sub-tes MANUAL (wawancara/FGD) — masuk mesin keputusan yang sama. -->
        <ConfirmModal
            :show="catatShow"
            title="Catat Hasil Aktivitas"
            :subtitle="catatTarget ? `${catatTarget.label} — ${detailKandidat?.pelamar || ''}` : ''"
            confirm-label="Simpan Hasil"
            :busy="sibuk"
            @confirm="konfirmCatat"
            @cancel="catatShow = false"
        >
            <div class="plw-catat">
                <template v-if="catatTarget && catatTarget.peran !== 'INFORMATIF'">
                    <div class="plw-catat__lbl">Hasil</div>
                    <el-radio-group v-model="catatHasil">
                        <el-radio-button label="LULUS">Lulus</el-radio-button>
                        <el-radio-button label="GAGAL">Gagal</el-radio-button>
                    </el-radio-group>
                </template>
                <div v-else class="plw-catat__info"><i class="bi bi-info-circle"></i> Aktivitas informatif — cukup nilai/catatan, tidak menentukan lulus.</div>
                <div class="plw-catat__lbl">Nilai (opsional)</div>
                <el-input-number v-model="catatNilai" :min="0" :max="1000" controls-position="right" style="width:100%" />
                <div class="plw-catat__lbl">Catatan penilai</div>
                <el-input v-model="catatCatatan" type="textarea" :rows="2" placeholder="mis. Komunikatif, hasil FGD baik — direkomendasikan lanjut" />
            </div>
        </ConfirmModal>

        <transition name="plw-toast"><div v-if="toast" class="plw-toast" :class="{ 'is-err': toastErr }"><i class="bi" :class="toastErr ? 'bi-exclamation-circle-fill' : 'bi-check-circle-fill'"></i> {{ toast }}</div></transition>
    </div>
</template>

<script>
import axios from 'axios';
import { Head, router } from '@inertiajs/vue3';
import ConfirmModal from '@career/ConfirmModal.vue';

const CFG = { headers: { Accept: 'application/json' } };

export default {
    components: { Head, ConfirmModal },
    props: {
        talent: { type: Array, default: () => [] },
        programAwal: { type: Object, default: () => ({ data: [], page: 1, totalPage: 1, total: 0 }) },
    },
    data() {
        return {
            programs: this.programAwal.data || [],
            page: this.programAwal.page || 1,
            totalPage: this.programAwal.totalPage || 1,
            total: this.programAwal.total || 0,
            q: '',
            jenis: '',
            loadingProg: false,
            selectedId: null,
            detail: { program: null, posisi: [], kolom: [], pelamar: [] },
            loadingDetail: false,
            statusTab: 'AKTIF',
            detailKandidat: null,
            profil: { lamaran: null, formulir: [] },
            loadingProfil: false,
            berkasHasil: [],
            berkasBusy: false,
            openForm: 0,
            lightbox: null,
            lbSrc: '',
            lbLoading: false,
            lbError: false,
            konfirmShow: false,
            putusTarget: null,
            putusHasil: '',
            putusSetuju: false, // centang wajib sebelum menggugurkan
            putusCatatan: '',
            catatShow: false,
            catatTarget: null,
            catatHasil: 'LULUS',
            catatNilai: null,
            catatCatatan: '',
            sibuk: false,
            toast: '',
            toastErr: false,
            tm: null,
            cariTm: null,
            posisiPilih: '', // '' = semua lowongan
        };
    },
    computed: {
        // Terminal "tidak lanjut" = GUGUR atau TALENT_POOL. Keduanya keluar dari
        // tab Berjalan dan berkumpul di tab Tidak Lolos (dengan badge berbeda).
        pelamarTampil() {
            const terminal = (r) => r.statusLamaran === 'GUGUR' || r.statusLamaran === 'TALENT_POOL';
            const aktif = this.statusTab !== 'GUGUR';
            return (this.detail.pelamar || [])
                .filter((r) => terminal(r) !== aktif)
                // Penyaring lowongan berlaku untuk KEDUA tab, supaya angka
                // "berjalan" dan "tidak lolos" satu lowongan bisa dibandingkan.
                .filter((r) => !this.posisiPilih || r.posisiId === this.posisiPilih);
        },
        jmlAktif() { return (this.detail.pelamar || []).filter((r) => r.statusLamaran !== 'GUGUR' && r.statusLamaran !== 'TALENT_POOL').length; },
        jmlGugur() { return (this.detail.pelamar || []).filter((r) => r.statusLamaran === 'GUGUR' || r.statusLamaran === 'TALENT_POOL').length; },
        // Judul & label tombol konfirmasi untuk 3 keputusan.
        putusJudul() { return { LULUS: 'Loloskan Kandidat', GUGUR: 'Gugurkan Kandidat', TALENT_POOL: 'Simpan ke Talent Pool' }[this.putusHasil] || 'Keputusan'; },
        putusLabelKonfirm() { return { LULUS: 'Ya, Loloskan', GUGUR: 'Ya, Gugurkan & Kirim Email', TALENT_POOL: 'Ya, Simpan' }[this.putusHasil] || 'Ya'; },
        /** Menggugurkan menuntut centang persetujuan dulu; yang lain langsung boleh. */
        bolehKonfirmPutus() { return this.putusHasil !== 'GUGUR' || this.putusSetuju; },
        // KEDUA tab memakai kolom tahap alur yang sama. Kandidat gugur tetap
        // "diam" di tahap tempat ia gugur (backend mengirim kolomUrutan dari
        // tahap ber-Hasil GUGUR), bukan ditumpuk jadi satu kolom — supaya admin
        // langsung melihat DI TAHAP MANA kandidat paling banyak berguguran.
        kolomTampil() {
            return this.detail.kolom || [];
        },
    },
    mounted() {
        if (this.programs.length) this.pilihProgram(this.programs[0]);
    },
    methods: {
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
        /** Tahap aktif kandidat ini di-cut-off ke Talent Pool? (dari kolom alur) */
        bolehTalentPool(r) {
            if (!r) return false;
            const col = (this.detail.kolom || []).find((k) => k.urutan === r.urutan);
            return !!(col && col.talentPool);
        },
        /** Loloskan diblokir bila kuota penuh DAN kandidat di tahap terakhir. */
        kuotaBlokir(r) { return !!(r && r.kuotaPenuh && r.diTahapAkhir); },
        ukuran(b) {
            if (!b) return '';
            return b > 1048576 ? (b / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(b / 1024)) + ' KB';
        },
        /** Kartu pada satu kolom tahap — berlaku sama untuk tab Berjalan & Tidak Lolos. */
        kartuKolom(col) {
            return this.pelamarTampil.filter((r) => r.kolomUrutan === col.urutan);
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
            if (this.bolehUpload(r) && r.tahapId) this.loadBerkas(r.tahapId);
        },
        tutupKandidat() { this.detailKandidat = null; this.lightbox = null; this.berkasHasil = []; },
        /* ── Berkas hasil tahap (MCU/Interview) ── */
        bolehUpload(r) {
            if (!r || !r.butuhKeputusan) return false;
            const col = (this.detail.kolom || []).find((k) => k.urutan === r.urutan);
            return !!(col && col.uploadHasil);
        },
        wajibUpload(r) {
            const col = (this.detail.kolom || []).find((k) => k.urutan === (r && r.urutan));
            return !!(col && col.wajibUpload);
        },
        /** Loloskan tertahan bila upload wajib tapi belum ada berkas. */
        uploadKurang(r) { return this.bolehUpload(r) && this.wajibUpload(r) && this.berkasHasil.length === 0; },
        async loadBerkas(tahapId) {
            try {
                this.berkasHasil = (await axios.get(`/api/v1/karir/lamaran/tahap/${tahapId}/berkas`, CFG)).data.result || [];
            } catch (e) { this.berkasHasil = []; }
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
            if (b.ext === 'jpg' || b.ext === 'jpeg') {
                this.lightbox = { nama: b.nama, field: 'hasil', status: '' };
                this.lbSrc = b.url; this.lbLoading = true; this.lbError = false;
            } else {
                window.open(b.url, '_blank');
            }
        },
        bukaDok(b) {
            if (b.isImage) {
                this.lightbox = b;
                this.lbSrc = b.url;
                this.lbLoading = true;
                this.lbError = false;
            } else {
                window.open(b.url, '_blank', 'noopener');
            }
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
            if (t.status !== 'SELESAI') return t.status === 'DIJADWALKAN' ? 'is-sched' : 'is-wait';
            if (t.peran === 'INFORMATIF') return 'is-done';
            return t.hasil === 'LULUS' ? 'is-pass' : 'is-fail';
        },
        labelTes(t) {
            if (t.status === 'TIDAK_HADIR') return 'Tidak hadir';
            if (t.status === 'DIJADWALKAN') return 'Dijadwalkan';
            if (t.status !== 'SELESAI') return 'Menunggu';
            if (t.peran === 'INFORMATIF') return 'Selesai';
            return t.hasil === 'LULUS' ? 'Lulus' : 'Gagal';
        },
        /**
         * Boleh dicatat hasilnya? Server yang memutuskan (lihat rapotTes di
         * LamaranController) — aktivitas tunggal tanpa jenis tes tidak punya
         * hasil sendiri, jadi tombolnya tidak ditampilkan.
         */
        bisaCatat(t) {
            return this.detailKandidat?.statusLamaran === 'BERJALAN' && !!t.dapatDicatat;
        },
        /** Kehadiran hanya relevan untuk aktivitas yang benar-benar sesi/tes. */
        bisaTidakHadir(t) {
            return this.detailKandidat?.statusLamaran === 'BERJALAN' && !!t.dapatTidakHadir;
        },
        askCatat(t) {
            this.catatTarget = t;
            this.catatHasil = 'LULUS';
            this.catatNilai = null;
            this.catatCatatan = '';
            this.catatShow = true;
        },
        async konfirmCatat() {
            if (this.sibuk || !this.catatTarget) return;
            this.sibuk = true;
            try {
                const res = await axios.patch(`/api/v1/karir/lamaran/sub-tes/${this.catatTarget.id}/catat-hasil`, {
                    hasil: this.catatTarget.peran === 'INFORMATIF' ? null : this.catatHasil,
                    nilai: this.catatNilai,
                    catatan: this.catatCatatan || null,
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
        async tandaiTidakHadir(t) {
            if (this.sibuk) return;
            this.sibuk = true;
            try {
                const res = await axios.patch(`/api/v1/karir/lamaran/sub-tes/${t.id}/tidak-hadir`, {}, CFG);
                this.notice(res.data?.message || 'Sub-tes ditandai tidak hadir.');
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
            this.putusCatatan = '';
            this.putusSetuju = false; // selalu minta ulang, jangan warisi centang sebelumnya
            this.konfirmShow = true;
        },
        async konfirmPutus() {
            if (this.sibuk || !this.putusTarget?.tahapId) return;
            this.sibuk = true;
            try {
                const res = await axios.patch(`/api/v1/karir/lamaran/tahap/${this.putusTarget.tahapId}/putus`, {
                    hasil: this.putusHasil, catatan: this.putusCatatan || null,
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
.plw-col__head { display: flex; align-items: center; gap: 9px; padding: 2px 4px 12px; }
.plw-col__num { width: 26px; height: 26px; border-radius: 9px; background: rgba(99, 102, 241, 0.12); color: #4f46e5; font-size: 12.5px; font-weight: 800; display: flex; align-items: center; justify-content: center; flex: 0 0 auto; }
.plw-col__num.is-hot { background: rgba(245, 158, 11, 0.16); color: #b45309; }
/* Tab "Tidak Lolos": kolom tahapnya sama, tapi diberi nada merah supaya sekali
   lihat ketahuan ini papan kegagalan — dan di tahap mana penyusutan terbesar. */
.plw-kanban.is-gugur .plw-col__num.is-hot { background: rgba(239, 68, 68, .14); color: #b91c1c; }
.plw-kanban.is-gugur .plw-col__count:not(:empty) { color: #b91c1c; background: rgba(239, 68, 68, .1); }
.plw-col__name { font-size: 13.5px; font-weight: 800; color: #334155; flex: 1; min-width: 0; display: flex; align-items: center; gap: 6px; white-space: nowrap; overflow: hidden; }
.plw-col__count { font-size: 12px; font-weight: 800; color: #94a3b8; background: #eef0f7; border-radius: 8px; padding: 2px 9px; flex: 0 0 auto; }
.plw-col__cards { display: flex; flex-direction: column; gap: 11px; min-height: 60px; }
.plw-col__empty { display: flex; align-items: center; justify-content: center; height: 80px; color: #c3cad8; font-size: 22px; font-weight: 300; }

.plw-card { appearance: none; cursor: pointer; text-align: left; font-family: inherit; width: 100%; background: #fff; border: 1px solid #eef0f7; border-radius: 16px; padding: 14px; transition: all 0.18s; box-shadow: 0 2px 8px rgba(15, 23, 42, 0.04); animation: plwCardIn 0.4s cubic-bezier(0.22, 1, 0.36, 1) both; }
.plw-card:hover { border-color: #d9def0; box-shadow: 0 10px 24px rgba(15, 23, 42, 0.08); transform: translateY(-2px); }
@keyframes plwCardIn { from { opacity: 0; transform: translateY(12px); } to { opacity: 1; transform: translateY(0); } }
.plw-card__toprow { display: flex; align-items: center; gap: 11px; width: 100%; }
.plw-card__avatar { width: 38px; height: 38px; border-radius: 11px; color: #fff; font-size: 13px; font-weight: 800; display: flex; align-items: center; justify-content: center; flex: 0 0 auto; }
.plw-card__id { display: flex; flex-direction: column; min-width: 0; flex: 1; }
.plw-card__name { font-size: 14px; font-weight: 800; color: #1e293b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.plw-card__code { font-size: 10.5px; font-weight: 700; letter-spacing: 0.06em; color: #aab2c5; font-family: 'JetBrains Mono', monospace; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.plw-card__pos { display: block; margin-top: 10px; font-size: 12.5px; font-weight: 600; color: #6366f1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

/* Konteks lowongan di kartu — dibuat kecil & abu supaya nama kandidat tetap
   jadi hal pertama yang terbaca. */
.plw-card__meta { display: flex; flex-wrap: wrap; gap: 4px 10px; margin-top: 5px; font-size: 11px; color: #94a3b8; min-width: 0; }
.plw-card__meta span { display: inline-flex; align-items: center; gap: 4px; min-width: 0; }
.plw-card__foot { display: flex; align-items: center; justify-content: space-between; gap: 8px; margin-top: 10px; }
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
.plw-card__chip { display: inline-block; margin-top: 11px; font-size: 9.5px; font-weight: 800; letter-spacing: 0.08em; padding: 4px 9px; border-radius: 7px; background: #eef0f7; color: #64748b; }
.plw-card__chip.tone-nunggu { background: rgba(245, 158, 11, 0.14); color: #b45309; }
.plw-card__chip.tone-perlu { background: rgba(99, 102, 241, 0.12); color: #4f46e5; }
.plw-card__chip.tone-skor { background: rgba(139, 92, 246, 0.14); color: #7c3aed; }
.plw-card__chip.tone-lolos { background: rgba(16, 185, 129, 0.12); color: #059669; }
.plw-card__chip.tone-gugur { background: rgba(239, 68, 68, 0.12); color: #dc2626; }
.plw-card__chip.tone-talent { background: rgba(234, 179, 8, 0.16); color: #a16207; }

/* ═══ DRAWER ═══ */
.plw-overlay { position: fixed; inset: 0; z-index: 1055; background: rgba(15, 23, 42, 0.42); backdrop-filter: blur(2px); transition: opacity 0.32s; opacity: 0; pointer-events: none; }
.plw-overlay.is-on { opacity: 1; pointer-events: auto; }
.plw-drawer { position: fixed; top: 0; right: 0; bottom: 0; z-index: 1056; width: 560px; max-width: 100%; background: #f6f7fb; box-shadow: -30px 0 80px rgba(15, 23, 42, 0.2); overflow-y: auto; transition: transform 0.4s cubic-bezier(0.22, 1, 0.36, 1); transform: translateX(104%); }
.plw-drawer.is-on { transform: translateX(0); }
.plw-drawer__head { position: sticky; top: 0; z-index: 5; background: linear-gradient(180deg, #ffffff, #fbfbfe); border-bottom: 1px solid #eef0f7; padding: 18px 22px; }
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
.plw-drawer__close { appearance: none; border: 1px solid #e6e9f3; background: #fff; width: 38px; height: 38px; border-radius: 11px; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.16s; color: #64748b; }
.plw-drawer__close:hover { background: #f1f5f9; color: #0f172a; }
.plw-drawer__body { padding: 20px 22px 40px; display: flex; flex-direction: column; gap: 18px; }

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
.plw-test__sub { font-size: 11px; color: #94a3b8; margin-top: 1px; }
.plw-test__score { font-size: 15px; font-weight: 800; color: #4f46e5; font-variant-numeric: tabular-nums; }
.plw-test__pill { flex: none; font-size: 11px; font-weight: 700; border-radius: 999px; padding: 3px 10px; }
.plw-test__pill.is-pass { background: rgba(16, 185, 129, .13); color: #047857; }
.plw-test__pill.is-fail { background: rgba(239, 68, 68, .12); color: #b91c1c; }
.plw-test__pill.is-done { background: rgba(99, 102, 241, .12); color: #4338ca; }
.plw-test__pill.is-sched { background: rgba(14, 165, 233, .12); color: #0369a1; }
.plw-test__pill.is-wait { background: #f1f5f9; color: #64748b; }
.plw-test__pill.is-absent { background: rgba(148, 163, 184, .18); color: #475569; }
.plw-test__skip { flex: none; display: inline-flex; align-items: center; gap: 5px; border: 1px solid #fca5a5; background: #fff; color: #b91c1c; font-size: 11px; font-weight: 700; border-radius: 9px; padding: 5px 9px; cursor: pointer; transition: background .15s; }
.plw-test__skip:hover { background: #fef2f2; }
.plw-test__rec { flex: none; display: inline-flex; align-items: center; gap: 5px; border: 1px solid #a5b4fc; background: #eef2ff; color: #4338ca; font-size: 11px; font-weight: 700; border-radius: 9px; padding: 5px 9px; cursor: pointer; transition: background .15s; }
.plw-test__rec:hover { background: #e0e7ff; }
.plw-test__cat { margin-top: 3px; font-size: 11px; color: #64748b; display: flex; align-items: flex-start; gap: 5px; line-height: 1.45; }

/* Modal catat hasil */
.plw-catat { display: flex; flex-direction: column; gap: 6px; }
.plw-catat__lbl { font-size: 11.5px; font-weight: 700; color: #475569; margin-top: 6px; }
.plw-catat__info { display: flex; align-items: center; gap: 6px; font-size: 12px; color: #4338ca; background: #eef2ff; border-radius: 9px; padding: 8px 10px; }

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
.plw-fields { display: grid; grid-template-columns: 1fr 1fr; gap: 1px; background: #eef0f7; border: 1px solid #eef0f7; border-radius: 14px; overflow: hidden; margin-top: 8px; }
.plw-field { background: #fff; padding: 11px 14px; min-width: 0; }
.plw-field__k { font-size: 10.5px; font-weight: 700; letter-spacing: 0.08em; color: #a2a9ba; text-transform: uppercase; }
.plw-field__v { font-size: 13.5px; font-weight: 700; color: #1e293b; margin-top: 3px; word-break: break-word; }
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

.plw-actions { display: flex; gap: 10px; flex-wrap: wrap; padding-top: 2px; }
.plw-btn-lolos { flex: 1; min-width: 150px; appearance: none; border: none; cursor: pointer; padding: 13px 18px; border-radius: 13px; background: linear-gradient(135deg, #34d399, #10b981); color: #fff; font-family: inherit; font-size: 13.5px; font-weight: 800; display: inline-flex; align-items: center; justify-content: center; gap: 8px; box-shadow: 0 10px 24px rgba(16, 185, 129, 0.3); transition: transform 0.16s; }
.plw-btn-lolos:hover { transform: translateY(-2px); }
.plw-btn-gugur { flex: 1; min-width: 150px; appearance: none; cursor: pointer; padding: 13px 18px; border-radius: 13px; background: #fff; border: 1px solid #f4c9c9; color: #dc2626; font-family: inherit; font-size: 13.5px; font-weight: 800; display: inline-flex; align-items: center; justify-content: center; gap: 8px; transition: all 0.16s; }
.plw-btn-gugur:hover { background: #fef2f2; }
/* Tombol ke-3: Masuk Talent Pool — nada emas, di antara Loloskan & Tidak Lolos. */
.plw-btn-talent { flex: 1; min-width: 150px; appearance: none; cursor: pointer; padding: 13px 18px; border-radius: 13px; background: linear-gradient(135deg, #fbbf24, #d97706); border: none; color: #fff; font-family: inherit; font-size: 13.5px; font-weight: 800; display: inline-flex; align-items: center; justify-content: center; gap: 8px; box-shadow: 0 10px 24px rgba(217, 119, 6, 0.28); transition: transform 0.16s; }
.plw-btn-talent:hover { transform: translateY(-2px); }
.plw-btn-talent .bi { font-size: 15px; }
/* Saat 3 tombol, izinkan membungkus rapi di layar sempit. */
.plw-actions.is-three .plw-btn-lolos, .plw-actions.is-three .plw-btn-talent, .plw-actions.is-three .plw-btn-gugur { min-width: 130px; }
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
/* Indikator kuota di area keputusan */
.plw-kuota { display: flex; align-items: center; gap: 8px; margin-bottom: 12px; padding: 9px 12px; border-radius: 10px; font-size: 12px; font-weight: 700; color: #3730a3; background: rgba(99, 102, 241, 0.1); border: 1px solid rgba(99, 102, 241, 0.25); }
.plw-kuota.is-penuh { color: #b91c1c; background: rgba(239, 68, 68, 0.09); border-color: rgba(239, 68, 68, 0.28); }
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
.plw-btn-lolos:disabled { opacity: 0.45; cursor: not-allowed; transform: none; }

/* ═══ LIGHTBOX ═══ */
.plw-lb { position: fixed; inset: 0; z-index: 1090; display: flex; align-items: center; justify-content: center; padding: 24px; background: rgba(10, 10, 20, 0.72); backdrop-filter: blur(6px); transition: opacity 0.28s; opacity: 0; pointer-events: none; }
.plw-lb.is-on { opacity: 1; pointer-events: auto; }
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
.plw-lb__state { width: 100%; height: 300px; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 12px; background: linear-gradient(135deg, #f1f2f9, #e8eaf6); color: #64748b; font-size: 13.5px; font-weight: 700; }
.plw-lb__retry { appearance: none; cursor: pointer; font-family: inherit; font-size: 12.5px; font-weight: 800; color: #fff; border: none; padding: 9px 18px; border-radius: 11px; background: linear-gradient(135deg, #8b5cf6, #6366f1); box-shadow: 0 8px 20px rgba(99, 102, 241, 0.28); transition: transform 0.16s; }
.plw-lb__retry:hover { transform: translateY(-1px); }
.plw-lb__foot { padding: 14px 18px; display: flex; align-items: center; justify-content: space-between; gap: 10px; border-top: 1px solid #eef0f7; }
.plw-lb__ok { display: inline-flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 800; color: #059669; flex: 0 0 auto; }

/* TOAST */
.plw-toast { position: fixed; bottom: 24px; right: 24px; z-index: 1100; display: flex; align-items: center; gap: 9px; padding: 12px 18px; border-radius: 13px; background: #0f172a; color: #fff; font-size: 13.5px; font-weight: 700; box-shadow: 0 18px 40px rgba(0, 0, 0, 0.3); }
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
</style>
