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
                                    <!-- Tipe aktivitas, bukan tipe tahap: satu tahap bisa
                                         berisi ujian online + tes manual + wawancara. -->
                                    <div class="plw-test__sub">{{ t.tipeNama || '—' }} · {{ t.provider === 'THIRD_PARTY' ? 'dijadwalkan di Penjadwalan' : 'dilaksanakan tim' }}</div>
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
                                    <div v-if="t.catatan" class="plw-test__cat"><i class="bi bi-chat-left-text"></i> {{ t.catatan }}</div>
                                </div>
                                <span v-if="t.nilai != null" class="plw-test__score">{{ t.nilai }}</span>
                                <span class="plw-test__pill" :class="pillTes(t)">{{ labelTes(t) }}</span>
                                <!-- Tombol aksi turun ke barisnya sendiri: drawer hanya
                                     560px dan bisa memuat tiga tombol sekaligus, sehingga
                                     memaksanya sebaris dengan nama aktivitas membuat
                                     labelnya terpotong per kata. -->
                                <div class="plw-test__aksi">
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
                                <template v-if="t.butuhKehadiran">
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
                                        <div v-for="j in f.jawaban" :key="j.key" class="plw-field">
                                            <div class="plw-field__k">{{ j.label }}</div>
                                            <!-- Isian yang ternyata berkas dibuat bisa dibuka di
                                                 tempatnya. Tanpa ini nama dokumen hanya teks mati,
                                                 dan admin tidak punya jalan melihat berkasnya. -->
                                            <button v-if="j.berkas" type="button" class="plw-field__file" @click="bukaDok(j.berkas)">
                                                <i class="bi" :class="j.berkas.isImage ? 'bi-file-earmark-image-fill' : 'bi-file-earmark-pdf-fill'"></i>
                                                <span>{{ j.nilai }}</span>
                                                <em>Lihat</em>
                                            </button>
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
                    <!-- Tahap berpenawaran yang BELUM dijawab. Menunggu itu wajar,
                         tapi menunggu tanpa batas tidak — jadi keadaannya disebut,
                         bukan dibiarkan terbaca sebagai "tidak ada apa-apa". -->
                    <div v-else-if="tahapPenawaran" class="plw-jawab is-wait">
                        <i class="bi bi-hourglass-split"></i>
                        <div style="min-width: 0">
                            <b>Kandidat belum menjawab penawaran.</b>
                            <p>Ia bisa menekan Terima / Mundur di portalnya. Bila menggantung, tim tetap bisa memutus sendiri lewat tombol di bawah.</p>
                        </div>
                    </div>

                    <!-- KEHADIRAN DULU, baru keputusan. Selama masih ada aktivitas
                         berjadwal yang kehadirannya belum ditetapkan, meloloskan
                         berarti memutuskan tanpa tahu kandidatnya datang atau tidak.
                         Alasannya ditulis di sini — tombol mati tanpa keterangan
                         membuat admin mengira layarnya rusak. -->
                    <div v-if="kehadiranKurang(detailKandidat)" class="plw-kuota is-hadir">
                        <i class="bi bi-person-lines-fill"></i>
                        <span>Tetapkan <b>Hadir</b> atau <b>Tidak Hadir</b> dulu pada aktivitas berjadwal di rapor tes di atas. Selama itu belum ditetapkan, <b>seluruh tombol keputusan</b> terkunci — kandidatnya datang atau tidak belum diketahui.</span>
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
                            :disabled="kehadiranKurang(detailKandidat)"
                            :title="kehadiranKurang(detailKandidat) ? 'Tetapkan kehadiran aktivitas berjadwal dulu' : h.deskripsi"
                            @click="askPutus(detailKandidat, h.kode)"
                        >
                            <i class="bi" :class="h.ikon"></i>
                            {{ h.labelTombol }}
                        </button>
                    </div>

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
                                :disabled="kehadiranKurang(detailKandidat)"
                                :title="h.deskripsi"
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
            :danger="putusHasil === 'GUGUR'"
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
                <div v-if="putusDef && putusDef.talentPool" class="plw-putus__row">
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

            <!-- HASIL MCU — dicatat DI SINI, di jendela keputusan.
                 Pemeriksaan kesehatan tidak punya keputusan sendiri yang terpisah
                 dari nasib tahapnya: begitu hasilnya keluar, admin meloloskan atau
                 tidak. Menaruhnya di tombol "Catat Hasil" terpisah berarti dua
                 jendela untuk satu peristiwa, dan yang kedua kerap terlewat —
                 sehingga status kesehatannya tak pernah tersimpan. -->
            <div v-if="mcuTes" class="plw-mform" :class="{ 'is-kurang': mcuKurangPutus }">
                <div class="plw-mform__head">
                    <span class="plw-mform__ico"><i class="bi bi-heart-pulse-fill"></i></span>
                    <div style="min-width: 0; flex: 1">
                        <div class="plw-mform__title">Hasil Pemeriksaan Kesehatan</div>
                        <div class="plw-mform__sub">{{ mcuTes.label }}<template v-if="mcuTes.jadwal"> · {{ jadwalRingkas(mcuTes.jadwal) }}</template></div>
                    </div>
                    <span v-if="mcuWajibSekarang" class="plw-req">wajib</span>
                    <span v-else class="plw-opt">opsional</span>
                </div>

                <div class="plw-fld">
                    <label class="plw-fld__lbl">Status Kesehatan <b v-if="mcuWajibSekarang">*</b></label>
                    <!-- Pilihan berbentuk kartu, bukan radio bawaan: statusnya
                         berwarna sesuai artinya, dan sasaran kliknya selebar baris. -->
                    <div class="plw-opts">
                        <button
                            v-for="o in MCU_STATUS" :key="o.v"
                            type="button" class="plw-opt-card" :class="[`is-${o.tone}`, { 'is-on': mcuStatus === o.v }]"
                            @click="mcuStatus = o.v"
                        >
                            <span class="plw-opt-card__dot"><i class="bi" :class="o.ikon"></i></span>
                            <span class="plw-opt-card__txt">
                                <b>{{ o.label }}</b>
                                <small>{{ o.ket }}</small>
                            </span>
                        </button>
                    </div>
                </div>

                <div class="plw-fld__row">
                    <div class="plw-fld">
                        <label class="plw-fld__lbl" for="mcu-penyedia">Penyedia (klinik / RS) <b v-if="mcuWajibSekarang">*</b></label>
                        <!-- Contoh sengaja TIDAK memakai nama rumah sakit yang nyata:
                             teks samar begitu mudah terbaca sebagai isian yang sudah
                             terisi, dan nama yang salah pada hasil kesehatan bukan
                             kekeliruan yang murah. -->
                        <input id="mcu-penyedia" v-model="mcuPenyedia" type="text" class="plw-inp" placeholder="Nama klinik / rumah sakit pelaksana" maxlength="200" />
                    </div>
                    <div class="plw-fld">
                        <label class="plw-fld__lbl" for="mcu-tanggal">Tanggal Pemeriksaan</label>
                        <input id="mcu-tanggal" v-model="mcuTanggal" type="date" class="plw-inp" />
                    </div>
                </div>

                <div class="plw-fld">
                    <label class="plw-fld__lbl" for="mcu-catatan">Catatan Medis <small>ringkas, opsional</small></label>
                    <textarea id="mcu-catatan" v-model="mcuCatatan" class="plw-inp plw-inp--ta" rows="2" maxlength="1000" placeholder="mis. Disarankan kontrol tekanan darah berkala."></textarea>
                </div>

                <p class="plw-note is-lock">
                    <i class="bi bi-shield-lock-fill"></i>
                    <span>Tulis ringkas saja. Diagnosis rinci adalah data kesehatan &mdash; simpan di berkas terlampir, bukan di catatan yang dibaca banyak orang.</span>
                </p>
                <p v-if="mcuKurangPutus" class="plw-note is-err">
                    <i class="bi bi-exclamation-circle-fill"></i>
                    <span>Status kesehatan dan penyedia wajib diisi — hasil pemeriksaan tanpa penerbitnya tidak sah.</span>
                </p>
            </div>

            <!-- Berkas pendukung — OPSIONAL. Ditaruh di sini karena inilah saat
                 admin memutuskan: memaksanya menutup modal, mengunggah, lalu
                 membuka modal lagi hanya membuat berkasnya sering tidak jadi
                 diunggah. Tanpa berkas pun keputusan tetap bisa dilanjutkan. -->
            <div class="plw-putus__unggah">
                <div class="plw-putus__unggah-head">
                    <span>
                        <i class="bi bi-paperclip"></i> Berkas Pendukung
                        <!-- "Wajib" hanya berlaku saat MELOLOSKAN. Menggugurkan atau
                             menyimpan ke Talent Pool tidak menuntut dokumen hasil,
                             jadi menandainya wajib di situ hanya menakut-nakuti. -->
                        <small v-if="berkasWajibSekarang" class="plw-req">wajib</small>
                        <small v-else>opsional</small>
                    </span>
                    <label class="plw-upbtn" :class="{ 'is-busy': berkasBusy }">
                        <i class="bi bi-upload"></i> {{ berkasBusy ? 'Mengunggah…' : 'Unggah' }}
                        <input type="file" accept=".pdf,.jpg,.jpeg" hidden :disabled="berkasBusy" @change="unggahBerkas">
                    </label>
                </div>
                <div v-if="berkasHasil.length" class="plw-putus__unggah-list">
                    <span v-for="b in berkasHasil" :key="b.id" class="plw-putus__berkas">
                        <i class="bi" :class="b.ext === 'pdf' ? 'bi-file-earmark-pdf-fill' : 'bi-file-earmark-image-fill'"></i>
                        <button type="button" @click="previewBerkas(b)">{{ b.nama }}</button>
                        <button type="button" class="plw-putus__berkas-del" title="Hapus berkas" @click="hapusBerkas(b)">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </span>
                </div>
                <p v-else class="plw-putus__unggah-kosong" :class="{ 'is-wajib': berkasWajibSekarang }">
                    <template v-if="berkasWajibSekarang">
                        <i class="bi bi-exclamation-circle-fill"></i>
                        <b>Wajib</b> diunggah sebelum meloloskan — mis. hasil MCU dari klinik. Tombol konfirmasi terkunci sampai ada berkas.
                    </template>
                    <template v-else>Belum ada berkas — keputusan tetap bisa dilanjutkan.</template>
                </p>
            </div>

            <!-- Wajib atau tidaknya alasan DARI MASTER (Butuh_Alasan): keputusan
                 yang menutup proses menuntut jejak kenapa, dan keputusan dari
                 kandidat menuntutnya juga — sebab mundurnya adalah satu-satunya
                 umpan balik kenapa penawaran kita kalah. -->
            <div class="plw-fld">
                <label class="plw-fld__lbl" for="putus-catatan">
                    {{ labelCatatanPutus }}
                    <b v-if="alasanWajib">*</b>
                    <small v-else>opsional</small>
                </label>
                <textarea
                    id="putus-catatan"
                    v-model="putusCatatan"
                    class="plw-inp plw-inp--ta"
                    :class="{ 'is-err': alasanWajib && !putusCatatan.trim() }"
                    rows="2"
                    maxlength="500"
                    :placeholder="placeholderCatatanPutus"
                ></textarea>
            </div>
            <p v-if="alasanWajib && !putusCatatan.trim()" class="plw-note is-err">
                <i class="bi bi-exclamation-circle-fill"></i> <span>{{ labelCatatanPutus }} wajib diisi.</span>
            </p>

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
                    <div class="plw-seg">
                        <button type="button" class="plw-seg__b is-net" :class="{ 'is-on': jadwalMode === 'DARING' }" @click="jadwalMode = 'DARING'">
                            <i class="bi bi-camera-video-fill"></i> Daring
                        </button>
                        <button type="button" class="plw-seg__b is-net" :class="{ 'is-on': jadwalMode === 'LURING' }" @click="jadwalMode = 'LURING'">
                            <i class="bi bi-geo-alt-fill"></i> Tatap muka
                        </button>
                    </div>
                </div>
                <p v-else class="plw-note is-lock">
                    <i class="bi bi-geo-alt-fill"></i>
                    <span>
                        {{ jadwalTarget?.tipeNama || 'Aktivitas ini' }} hanya bisa dijalankan
                        <b>tatap muka</b>, jadi metodenya tidak dapat diubah.
                    </span>
                </p>

                <div class="plw-fld__row">
                    <div class="plw-fld">
                        <label class="plw-fld__lbl">Waktu mulai <b>*</b></label>
                        <el-date-picker v-model="jadwalMulai" type="datetime" format="DD MMM YYYY HH:mm" value-format="YYYY-MM-DD HH:mm:ss" placeholder="Pilih tanggal & jam" style="width: 100%" />
                    </div>
                    <div class="plw-fld">
                        <label class="plw-fld__lbl">Waktu selesai <small>opsional</small></label>
                        <el-date-picker v-model="jadwalSelesai" type="datetime" format="DD MMM YYYY HH:mm" value-format="YYYY-MM-DD HH:mm:ss" placeholder="Perkiraan selesai" style="width: 100%" />
                    </div>
                </div>

                <div v-if="jadwalMode === 'DARING'" class="plw-fld">
                    <label class="plw-fld__lbl" for="jdw-link">Tautan pertemuan <b>*</b></label>
                    <input id="jdw-link" v-model="jadwalLink" type="url" class="plw-inp" placeholder="https://meet.google.com/..." maxlength="500" />
                </div>
                <template v-else>
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
            :confirm-label="hadirNilai === 'Y' ? 'Ya, Hadir' : 'Ya, Tidak Hadir'"
            :busy="sibuk"
            @confirm="konfirmHadir"
            @cancel="hadirShow = false"
        >
            <p class="plw-hdr__note">
                <template v-if="hadirNilai === 'Y'">
                    Setelah ditandai hadir, hasil aktivitas ini bisa dicatat berikut berkas pendukungnya,
                    dan tombol <b>Loloskan / Tidak Lolos</b> terbuka.
                </template>
                <template v-else>
                    Aktivitas ini akan ditandai <b>GAGAL</b> dan tahapnya dievaluasi ulang oleh sistem.
                    Tombol keputusan tetap terbuka setelahnya.
                </template>
            </p>
            <div class="plw-fld">
                <label class="plw-fld__lbl" for="hadir-catatan">Catatan <small>opsional</small></label>
                <textarea
                    id="hadir-catatan" v-model="hadirCatatan" class="plw-inp plw-inp--ta" rows="2" maxlength="500"
                    :placeholder="hadirNilai === 'Y'
                        ? 'Boleh dikosongkan — mis. datang terlambat 15 menit'
                        : 'Boleh dikosongkan — mis. sakit, minta jadwal ulang'"
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
            @cancel="catatShow = false"
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

            <div class="plw-fld">
                <label class="plw-fld__lbl" for="catat-catatan">Catatan Penilai <small>opsional</small></label>
                <textarea
                    id="catat-catatan" v-model="catatCatatan" class="plw-inp plw-inp--ta" rows="2" maxlength="500"
                    placeholder="mis. Komunikatif, hasil FGD baik — direkomendasikan lanjut"
                ></textarea>
            </div>
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

        <transition name="plw-toast"><div v-if="toast" class="plw-toast" :class="{ 'is-err': toastErr }"><i class="bi" :class="toastErr ? 'bi-exclamation-circle-fill' : 'bi-check-circle-fill'"></i> {{ toast }}</div></transition>
    </div>
</template>

<script>
import axios from 'axios';
import { Head, router } from '@inertiajs/vue3';
import ConfirmModal from '@career/ConfirmModal.vue';

const CFG = { headers: { Accept: 'application/json' } };

/**
 * Pilihan status MCU + nada warnanya.
 *
 * Hasil pemeriksaan kesehatan tidak cukup "lulus / gagal": keadaan tengah —
 * boleh bekerja dengan catatan — justru yang paling sering, dan itulah yang
 * menentukan penempatan saat onboarding.
 */
const MCU_STATUS = [
    { v: 'FIT', label: 'Fit', ket: 'Memenuhi syarat kesehatan', tone: 'ok', ikon: 'bi-check-lg' },
    { v: 'FIT_WITH_NOTE', label: 'Fit dengan catatan', ket: 'Boleh bekerja, ada hal yang perlu diperhatikan', tone: 'warn', ikon: 'bi-exclamation-lg' },
    { v: 'UNFIT', label: 'Unfit', ket: 'Belum memenuhi syarat kesehatan', tone: 'no', ikon: 'bi-x-lg' },
];

export default {
    // Halaman ini merender BEBERAPA simpul akar (konten + modal + lightbox yang
    // di-teleport), sehingga Vue tidak tahu ke mana harus menempelkan atribut
    // bawaan Inertia (errors, auth, flash, ...). Dimatikan supaya peringatan
    // "Extraneous non-props attributes" berhenti — atribut itu memang tidak
    // dipakai sebagai atribut HTML di sini.
    inheritAttrs: false,
    components: { Head, ConfirmModal },
    props: {
        talent: { type: Array, default: () => [] },
        programAwal: { type: Object, default: () => ({ data: [], page: 1, totalPage: 1, total: 0 }) },
        // Master hasil keputusan — sumber tombol di footer drawer.
        hasilKeputusan: { type: Array, default: () => [] },
    },
    data() {
        return {
            MCU_STATUS,
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
            lightbox: null,
            lbSrc: '',
            lbTimer: null,
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
            mcuStatus: 'FIT',
            mcuPenyedia: '',
            mcuTanggal: '',
            mcuCatatan: '',
            jadwalShow: false,
            jadwalTarget: null,
            jadwalMode: 'DARING',
            jadwalMulai: '',
            jadwalSelesai: '',
            jadwalLink: '',
            jadwalLokasi: '',
            jadwalLokasiId: null,
            daftarLokasi: [],
            lokasiLoading: false,
            jadwalCatatan: '',
            hadirShow: false,
            hadirTarget: null,
            hadirNilai: 'Y',
            hadirCatatan: '',
            catatNilai: null,
            catatCatatan: '',
            sibuk: false,
            // Id aktivitas yang sedang ditarik hasilnya dari HCLearn.
            sinkronId: null,
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
        /** Definisi hasil yang sedang dipilih — semua labelnya dari master. */
        putusDef() { return this.hasilKeputusan.find((h) => h.kode === this.putusHasil) || null; },
        putusJudul() { return this.putusDef ? `${this.putusDef.labelTombol} — ${this.putusDef.nama}` : 'Keputusan'; },
        putusLabelKonfirm() { return this.putusDef?.labelKonfirmasi || 'Ya, Lanjutkan'; },
        /** Tahap aktif kandidat membawa penawaran yang harus dijawab? */
        tahapPenawaran() {
            if (!this.detailKandidat) return false;
            const col = (this.detail.kolom || []).find((k) => k.urutan === this.detailKandidat.urutan);

            return !!(col && col.penawaran);
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

            return this.hasilKeputusan.filter(
                (h) => h.olehKandidat && (this.tahapPenawaran || h.kode !== 'DITOLAK_KANDIDAT'),
            );
        },
        /** Menggugurkan menuntut centang persetujuan dulu; yang lain langsung boleh. */
        /** DARING wajib tautan, LURING wajib lokasi; keduanya wajib waktu mulai. */
        /**
         * Aktivitas MCU pada tahap yang sedang diputus — sumber borang kesehatan
         * di modal keputusan. Satu tahap MCU hanya punya satu pemeriksaan.
         */
        mcuTes() {
            return (this.putusTarget?.tests || []).find((t) => t.isMcu) || null;
        },
        /**
         * Status & penyedia WAJIB saat tahap MCU benar-benar diputus (lolos atau
         * tidak). Hasil kesehatan tanpa penerbitnya tidak sah, dan inilah satu-
         * satunya tempat ia dicatat. Talent Pool dikecualikan: kandidatnya tidak
         * dinilai di lowongan ini, hanya disimpan untuk kesempatan lain.
         */
        mcuWajibSekarang() {
            // Keputusan yang datang dari KANDIDAT tidak menuntutnya: ia mundur
            // sebelum pemeriksaannya selesai, dan menahan pencatatan itu hanya
            // membuat lamarannya menggantung.
            return !!this.mcuTes
                && !!this.putusDef
                && !this.putusDef.olehKandidat
                && !this.putusDef.talentPool;
        },
        mcuKurangPutus() {
            return this.mcuWajibSekarang && (!this.mcuStatus || !this.mcuPenyedia.trim());
        },
        lokasiTerpilih() {
            return this.daftarLokasi.find((l) => l.id === this.jadwalLokasiId) || null;
        },
        /** DARING wajib tautan, LURING wajib lokasi TERPILIH; keduanya wajib waktu. */
        bolehSimpanJadwal() {
            if (!this.jadwalMulai) return false;

            return this.jadwalMode === 'DARING'
                ? !!this.jadwalLink.trim()
                : !!this.jadwalLokasiId;
        },
        /**
         * Berkas wajib untuk keputusan yang SEDANG dipilih — syaratnya persis
         * sama dengan yang mengunci tombol konfirmasi (uploadKurang), supaya
         * label "wajib" tidak pernah muncul di modal yang sebenarnya lolos.
         */
        berkasWajibSekarang() {
            return !!this.putusDef?.lolos
                && !!this.putusTarget
                && this.bolehUpload(this.putusTarget)
                && this.wajibUpload(this.putusTarget);
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
        bolehKonfirmPutus() {
            if (!this.putusDef) return false;

            // Hasil kesehatan wajib lengkap sebelum tahap MCU diputus.
            if (this.mcuKurangPutus) return false;

            // Tahap yang memang mewajibkan berkas tetap ditahan — aturannya milik
            // Master Tahapan, bukan preferensi modal ini.
            if (this.putusDef.lolos && this.putusTarget && this.uploadKurang(this.putusTarget)) {
                return false;
            }
            if (this.alasanWajib && !this.putusCatatan.trim()) return false;

            return !this.butuhCentang || this.putusSetuju;
        },
        /** Aktivitas yang dikerjakan tim: catatan bebas, tak ada syarat tambahan. */
        bolehSimpanCatat() { return !!this.catatTarget; },
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
        /**
         * Nada tombol keputusan — DARI FLAG master, bukan dari kodenya.
         * Hasil baru yang ditambahkan lewat master ikut dapat warna yang masuk
         * akal tanpa satu baris pun disentuh di sini.
         */
        kelasPutus(h) {
            if (h.lolos) return 'is-lolos';

            return h.talentPool && !h.kirimEmail ? 'is-talent' : 'is-gugur';
        },
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
        bolehUpload(r) {
            if (!r || !r.butuhKeputusan) return false;
            const col = (this.detail.kolom || []).find((k) => k.urutan === r.urutan);
            return !!(col && col.uploadHasil);
        },
        wajibUpload(r) {
            const col = (this.detail.kolom || []).find((k) => k.urutan === (r && r.urutan));
            return !!(col && col.wajibUpload);
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
        kehadiranKurang(r) { return (r?.tests || []).some((t) => t.butuhKehadiran); },
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
            if (t.status !== 'SELESAI') return t.status === 'DIJADWALKAN' ? 'is-sched' : 'is-wait';
            if (t.peran === 'INFORMATIF') return 'is-done';
            return t.hasil === 'LULUS' ? 'is-pass' : 'is-fail';
        },
        labelTes(t) {
            if (t.status === 'TIDAK_HADIR') return 'Tidak hadir';
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
            this.catatCatatan = '';
            this.catatShow = true;
        },
        /**
         * Isi ulang borang MCU dari data yang sudah tersimpan.
         *
         * Dipanggil tiap kali modal keputusan dibuka: koreksi keputusan tidak
         * boleh menuntut admin mengetik ulang penyedia dan tanggalnya.
         */
        muatMcu(t) {
            const m = t?.mcu || {};
            this.mcuStatus = m.status || 'FIT';
            this.mcuPenyedia = m.penyedia || '';
            // Input tanggal HTML hanya menerima YYYY-MM-DD; nilai bertimestamp
            // dari server ("2026-08-10 00:00:00") ditolak diam-diam oleh peramban.
            this.mcuTanggal = (m.tanggal || '').slice(0, 10);
            this.mcuCatatan = m.catatan || '';
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
            this.hadirShow = true;
        },
        async konfirmHadir() {
            if (this.sibuk || !this.hadirTarget) return;
            this.sibuk = true;
            try {
                const res = await axios.patch(`/api/v1/karir/lamaran/sub-tes/${this.hadirTarget.id}/kehadiran`, {
                    hadir: this.hadirNilai,
                    catatan: this.hadirCatatan || null,
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
            this.jadwalMode = t.wajibLuring ? 'LURING' : (j.mode || 'DARING');
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
                    link: this.jadwalMode === 'DARING' ? this.jadwalLink : null,
                    lokasiId: this.jadwalMode === 'LURING' ? this.jadwalLokasiId : null,
                    lokasi: this.jadwalMode === 'LURING' ? (this.jadwalLokasi || null) : null,
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
        /**
         * Tarik hasil ujian online dari HCLearn.
         *
         * Dipakai saat webhook CAT tak sampai. Yang tersimpan tetap angka resmi
         * penyedia — admin tidak pernah mengetik nilai. Aman diulang.
         */
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
            this.putusCatatan = '';
            // Borang kesehatan menumpang modal ini pada tahap MCU — isinya
            // disemai dari hasil yang mungkin sudah pernah dicatat.
            this.muatMcu((r?.tests || []).find((t) => t.isMcu));
            // Tampilkan berkas yang mungkin sudah diunggah sebelumnya.
            this.loadBerkas(r?.tahapId || null);
            this.putusSetuju = false; // selalu minta ulang, jangan warisi centang sebelumnya
            this.konfirmShow = true;
        },
        async konfirmPutus() {
            if (this.sibuk || !this.putusTarget?.tahapId) return;
            this.sibuk = true;
            try {
                const res = await axios.patch(`/api/v1/karir/lamaran/tahap/${this.putusTarget.tahapId}/putus`, {
                    hasil: this.putusHasil,
                    catatan: this.putusCatatan || null,
                    // Hasil MCU ikut pada keputusan yang sama — satu perjalanan
                    // ke server, jadi mustahil ada keputusan tanpa hasil
                    // kesehatannya (atau sebaliknya) bila salah satu gagal.
                    ...(this.mcuTes ? {
                        mcuStatus: this.mcuStatus || null,
                        mcuPenyedia: this.mcuPenyedia || null,
                        mcuTanggal: this.mcuTanggal || null,
                        mcuCatatan: this.mcuCatatan || null,
                    } : {}),
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
.plw-test__sync { flex: none; display: inline-flex; align-items: center; gap: 5px; border: 1px solid #a5b4fc; background: #fff; color: #4338ca; font-size: 11px; font-weight: 700; border-radius: 9px; padding: 5px 9px; cursor: pointer; transition: background .15s; }
.plw-test__sync:hover:not(:disabled) { background: #eef0fe; }
.plw-test__sync:disabled { opacity: .6; cursor: default; }
.plw-spin { display: inline-block; animation: plwSpin 1s linear infinite; }
@keyframes plwSpin { to { transform: rotate(360deg); } }
.plw-test__rec { flex: none; display: inline-flex; align-items: center; gap: 5px; border: 1px solid #a5b4fc; background: #eef2ff; color: #4338ca; font-size: 11px; font-weight: 700; border-radius: 9px; padding: 5px 9px; cursor: pointer; transition: background .15s; }
.plw-test__rec:hover { background: #e0e7ff; }
.plw-test__cat { margin-top: 3px; font-size: 11px; color: #64748b; display: flex; align-items: flex-start; gap: 5px; line-height: 1.45; }
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
.plw-fields { display: grid; grid-template-columns: 1fr 1fr; gap: 1px; background: #eef0f7; border: 1px solid #eef0f7; border-radius: 14px; overflow: hidden; margin-top: 8px; }
.plw-field { background: #fff; padding: 11px 14px; min-width: 0; }
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
</style>
