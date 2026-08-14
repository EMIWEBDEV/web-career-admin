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
                <!-- CHIP KATEGORI — isinya mengikuti hak akses, bukan seluruh master.
                     "Semua" hanya berarti bila memang ADA yang bisa dipilih: pada
                     admin yang dijatah satu kategori, "Semua" dan chip kategorinya
                     menyaring himpunan yang sama persis, jadi dua tombol untuk satu
                     hasil — dan yang menekannya mengira ada isi lain yang belum
                     terlihat. Satu kategori: seluruh baris chip disembunyikan. -->
                <div v-if="talent.length > 1" class="plw-chips">
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

                    <!-- ═══ TAB KEADAAN ═══
                         Baris sendiri, di atas penyaring. Ia menjawab pertanyaan
                         yang berbeda: tab memilih POPULASI (siapa yang sedang
                         dilihat), penyaring di bawahnya mempersempit populasi itu.
                         Mencampur keduanya dalam satu baris — bentuk lamanya —
                         membuat orang mengira "Tidak Lolos" adalah salah satu
                         nilai penyaring, lalu mencarinya di dalam dropdown. -->
                    <div class="plw-tabs">
                            <!-- SEMUA lebih dulu, dan inilah bawaannya.
                                 Tab-tab sesudahnya menyempitkan, bukan
                                 mengungkap: dengan "Berjalan" sebagai bawaan,
                                 kandidat yang sudah ditutup — gugur, mundur,
                                 talent pool — tidak terlihat sama sekali sampai
                                 seseorang tahu harus menekan tab mana, dan
                                 papan berbunyi seolah mereka lenyap. -->
                            <button type="button" class="plw-tab" :class="{ 'is-all': statusTab === 'SEMUA' }" @click="statusTab = 'SEMUA'">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="3" y="3" width="7" height="7" rx="1.5" /><rect x="14" y="3" width="7" height="7" rx="1.5" /><rect x="3" y="14" width="7" height="7" rx="1.5" /><rect x="14" y="14" width="7" height="7" rx="1.5" /></svg>
                                Semua <span class="plw-tab__badge">{{ jmlSemua }}</span>
                            </button>
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
                            <!-- MENGUNDURKAN DIRI berdiri sendiri — bukan "Tidak
                                 Lolos", apalagi "Berjalan".

                                 Kandidat yang menolak penawaran atau mengundurkan
                                 diri tidak gagal seleksi: perusahaan tidak
                                 menolaknya, ia yang pergi. Menaruhnya di "Tidak
                                 Lolos" membuat laporan berbunyi seolah kita yang
                                 menggugurkan; membiarkannya di "Berjalan" — yang
                                 selama ini terjadi, karena penyaringnya hanya
                                 mengenal GUGUR & TALENT_POOL — jauh lebih buruk:
                                 orang yang sudah pergi tetap menempati kolom
                                 papan dan terus tampak menunggu diproses.

                                 Disebut lengkap, bukan "Mundur": satu kata itu
                                 sama-sama dipakai untuk memundurkan JADWAL, dan
                                 tab yang bisa dibaca dua arti bukan penyaring. -->
                            <button type="button" class="plw-tab" :class="{ 'is-out': statusTab === 'MUNDUR' }" @click="statusTab = 'MUNDUR'">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" /><path d="M16 17l5-5-5-5M21 12H9" /></svg>
                                Mengundurkan Diri <span class="plw-tab__badge">{{ jmlMundur }}</span>
                            </button>
                    </div>

                    <!-- ═══ PENYARING — SATU KARTU, SELURUHNYA ELEMENT PLUS ═══
                         Semua pemilih bisa DICARI SAMBIL MENGETIK (`filterable`).
                         Bukan hiasan: satu program bisa menampung ratusan kampus
                         dan puluhan lowongan, dan dropdown sepanjang itu tanpa
                         kotak cari memaksa admin menggulung mencari satu baris.

                         Isi tiap pilihan datang dari data yang BENAR-BENAR ada di
                         papan — kampus dari jawaban formulir kandidat program ini,
                         tahap dari alur yang mereka jalani. Tidak ada pilihan yang
                         menghasilkan nol baris, dan tidak ada kandidat yang
                         kampusnya tak bisa dipilih. -->
                    <div class="plw-toolbar" :class="{ 'is-buka': panelBuka }">
                        <!-- KEPALA PANEL. Dulu panel ini langsung mulai dengan
                             enam kotak isian tanpa satu kata pun yang menyebut
                             ia apa — dan papan yang tiba-tiba berisi tiga orang
                             terbaca sebagai data yang hilang, bukan sebagai
                             saringan yang sedang bekerja. Kepala inilah yang
                             menyebutkannya, sekaligus jadi pegangan menutup
                             panelnya di layar sempit. -->
                        <div class="plw-fhead">
                            <span class="plw-fhead__ico"><i class="bi bi-funnel-fill"></i></span>
                            <div class="plw-fhead__ttl">
                                <b>Panel Penyaring</b>
                                <small>{{ chipFilter.length ? `${chipFilter.length} penyaring aktif` : 'Semua kandidat ditampilkan' }}</small>
                            </div>
                            <span v-if="chipFilter.length" class="plw-fhead__n">{{ chipFilter.length }}</span>
                            <button
                                v-if="chipFilter.length" type="button" class="plw-fhead__x"
                                title="Bersihkan seluruh penyaring" @click="bersihkanFilter"
                            >
                                <i class="bi bi-x-circle"></i><span>Bersihkan</span>
                            </button>
                            <button
                                type="button" class="plw-fhead__tgl"
                                :title="panelBuka ? 'Sembunyikan penyaring' : 'Tampilkan penyaring'"
                                :aria-expanded="panelBuka"
                                @click="panelBuka = !panelBuka"
                            >
                                <i class="bi" :class="panelBuka ? 'bi-chevron-up' : 'bi-chevron-down'"></i>
                            </button>
                        </div>

                        <div v-show="panelBuka" class="plw-fbody">
                        <div class="plw-fgrid">
                            <el-input v-model="cariKandidat" clearable class="plw-f plw-f--cari" placeholder="Cari nama, kode, posisi, atau kampus">
                                <template #prefix><i class="bi bi-search"></i></template>
                            </el-input>
                            <!-- MULTI-PILIH. Pertanyaan yang benar-benar diajukan
                                 jarang menyangkut satu nilai: "psikotes DAN
                                 wawancara", "Unsri DAN Unila". Dengan satu nilai
                                 saja, membandingkan dua tahap berarti menyaring
                                 dua kali dan mengingat sendiri angka yang
                                 pertama. Tag yang menumpuk diringkas setelah dua
                                 — kotak penyaring yang tumbuh mengikuti jumlah
                                 pilihan akan mendorong seluruh papan turun. -->
                            <el-select
                                v-model="tahapPilih" multiple filterable clearable
                                collapse-tags collapse-tags-tooltip :max-collapse-tags="2"
                                class="plw-f" placeholder="Tahap — semua"
                            >
                                <el-option v-for="(c, i) in kolomTampil" :key="kunciKolom(c)" :value="kunciKolom(c)" :label="c.label">
                                    <div class="plw-lopt">
                                        <b>{{ String(i + 1).padStart(2, '0') }} · {{ c.label }}</b>
                                        <small>{{ jumlahTahap(c) }} kandidat</small>
                                    </div>
                                </el-option>
                            </el-select>
                            <!-- KAMPUS — daftarnya MENGIKUTI yang terdaftar, bukan
                                 Master Kampus. Master itu berisi 328 ribu baris
                                 hasil impor Dapodik/PDDIKTI; menawarkannya utuh di
                                 sini berarti 99,9% pilihannya menghasilkan papan
                                 kosong, dan yang mencari kampus kandidatnya
                                 sendiri justru tenggelam di antara ratusan ribu
                                 nama yang tak seorang pun melamar dari sana. -->
                            <el-select
                                v-model="kampusPilih" multiple filterable clearable
                                collapse-tags collapse-tags-tooltip :max-collapse-tags="2"
                                class="plw-f" placeholder="Kampus — semua"
                            >
                                <el-option v-for="k in kampusOpsi" :key="k.nama" :value="k.nama" :label="k.nama">
                                    <div class="plw-lopt plw-lopt--kampus">
                                        <!-- Bendera negara — sama seperti pemilih
                                             kampus di formulir pendaftaran. Kode
                                             negaranya datang bersama daftar
                                             pelamar (lihat detailProgram), jadi
                                             tidak ada permintaan tambahan. -->
                                        <img
                                            v-if="k.bendera" class="plw-lopt__flag"
                                            :src="`https://flagcdn.com/20x15/${k.bendera}.png`"
                                            alt="" width="20" height="15" loading="lazy"
                                        />
                                        <i v-else class="bi bi-globe2 plw-lopt__flag plw-lopt__flag--kosong" title="Negara tidak tercatat di data institusi"></i>
                                        <b>{{ k.nama }}</b>
                                        <small>{{ k.jumlah }} kandidat</small>
                                    </div>
                                </el-option>
                            </el-select>
                            <el-select
                                v-if="(detail.posisi || []).length"
                                v-model="posisiPilih" multiple filterable clearable
                                collapse-tags collapse-tags-tooltip :max-collapse-tags="2"
                                class="plw-f" placeholder="Lowongan — semua"
                            >
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
                            <el-select
                                v-model="keadaanPilih" multiple filterable clearable
                                collapse-tags collapse-tags-tooltip :max-collapse-tags="2"
                                class="plw-f" placeholder="Keadaan — semua"
                            >
                                <el-option value="PERLU" label="Perlu keputusan saya" />
                                <el-option value="NUNGGU" label="Menunggu hasil tes" />
                                <el-option value="JADWAL" label="Belum dijadwalkan" />
                                <el-option value="TERJADWAL" label="Sudah dijadwalkan" />
                                <el-option value="HOLD" label="Sedang ditahan" />
                            </el-select>
                        </div>

                        <!-- RENTANG TANGGAL MELAMAR — BARIS PENUH SENDIRI.
                             Satu kontrol, bukan dua kotak terpisah: "dari" yang
                             lebih baru daripada "sampai" adalah rentang kosong,
                             dan pemilih rentang menutup kemungkinan itu sejak
                             awal. Dulu ia berdesakan sebagai kolom keenam
                             selebar dropdown, memuat DUA tanggal + pemisah di
                             ruang yang cuma cukup untuk satu — placeholdernya
                             terpotong jadi "Melamar dar… s/d" dan tak seorang
                             pun tahu itu penyaring apa. -->
                        <div class="plw-frow">
                            <label class="plw-frow__lbl">
                                <i class="bi bi-calendar-range"></i>
                                Rentang tanggal melamar
                            </label>
                            <div class="plw-frow__isi">
                                <el-date-picker
                                    v-model="rangeTanggal"
                                    type="daterange" unlink-panels
                                    class="plw-f plw-f--tgl"
                                    start-placeholder="Tanggal awal" end-placeholder="Tanggal akhir"
                                    range-separator="→"
                                    value-format="YYYY-MM-DD" format="DD MMM YYYY"
                                />
                                <!-- Pintasan yang benar-benar ditanyakan orang.
                                     Tanpa ini, "yang masuk minggu ini" menuntut
                                     dua kali membuka kalender dan menghitung
                                     mundur tanggalnya sendiri. -->
                                <div class="plw-fquick">
                                    <button
                                        v-for="p in pintasTanggal" :key="p.hari"
                                        type="button" class="plw-fq" :class="{ 'is-on': pintasAktif === p.hari }"
                                        @click="pakaiPintasTanggal(p.hari)"
                                    >
                                        {{ p.label }}
                                    </button>
                                    <button v-if="rangeTanggal" type="button" class="plw-fq is-off" @click="rangeTanggal = null">
                                        <i class="bi bi-x-lg"></i> Semua
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- CHIP PENYARING AKTIF. Penyaring yang menyala di dalam
                             dropdown tidak terbaca sekilas — dan papan yang tiba-tiba
                             berisi tiga orang lalu terbaca sebagai data yang hilang,
                             bukan sebagai saringan yang sedang bekerja. -->
                        <div v-if="chipFilter.length" class="plw-chiprow">
                            <span v-for="ch in chipFilter" :key="ch.key" class="plw-fchip">
                                <span class="plw-fchip__k">{{ ch.jenis }}</span>
                                <span class="plw-ell">{{ ch.label }}</span>
                                <button type="button" title="Copot penyaring ini" @click="copotChip(ch.key)"><i class="bi bi-x-lg"></i></button>
                            </span>
                            <button type="button" class="plw-fclear" @click="bersihkanFilter">Bersihkan semua</button>
                        </div>
                        </div>
                    </div>

                    <!-- Latar gelap khusus ponsel: panel di sana melayang di atas
                         papan, dan tanpa latar ini papan di belakangnya masih
                         tampak bisa disentuh. -->
                    <div v-if="panelBuka" class="plw-ftirai" @click="panelBuka = false"></div>

                    <!-- FAB — pintu masuk penyaring di ponsel. Panelnya sendiri
                         disembunyikan di sana: enam isian bertumpuk memakan satu
                         layar penuh sebelum satu kandidat pun terlihat, padahal
                         yang dibuka orang di ponsel hampir selalu papannya. -->
                    <button
                        type="button" class="plw-fab" :class="{ 'is-aktif': chipFilter.length > 0 }"
                        :aria-label="panelBuka ? 'Tutup penyaring' : 'Buka penyaring'"
                        @click="panelBuka = !panelBuka"
                    >
                        <i class="bi" :class="panelBuka ? 'bi-x-lg' : 'bi-funnel-fill'"></i>
                        <span v-if="chipFilter.length && !panelBuka" class="plw-fab__n">{{ chipFilter.length }}</span>
                    </button>

                    <!-- ═══ MODE TAMPILAN — KANBAN / LIST ═══
                         Dua cara membaca populasi yang sama. Kanban menjawab "di
                         mana orang menumpuk"; list menjawab "siapa saja, dan apa
                         isinya" — termasuk kampus & tanggal melamar yang tak muat
                         di kartu kanban. -->
                    <div class="plw-moderow">
                        <div class="plw-modes">
                            <button
                                type="button" class="plw-mode" :class="{ 'is-on': mode === 'kanban' }"
                                title="Tampilan papan kanban" @click="mode = 'kanban'"
                            >
                                <i class="bi bi-kanban-fill"></i><span>Kanban</span>
                            </button>
                            <button
                                type="button" class="plw-mode" :class="{ 'is-on': mode === 'list' }"
                                title="Tampilan daftar" @click="mode = 'list'"
                            >
                                <i class="bi bi-list-ul"></i><span>List</span>
                            </button>
                        </div>
                        <div class="plw-hasil">{{ teksHasil }}</div>
                    </div>

                    <div v-if="loadingDetail" class="plw-load" style="padding: 3rem 0"><span class="plw-spin"></span> Memuat papan seleksi…</div>

                    <!-- KANBAN -->
                    <div v-else-if="mode === 'kanban'" class="plw-kanban" :class="{ 'is-gugur': statusTab === 'GUGUR', 'is-mundur': statusTab === 'MUNDUR' }">
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
                            <!-- Dua baris, bukan satu. Kolom papan cuma ±280px;
                                 memaksa "Pilih semua" + 3 tombol aksi berjejer
                                 membuat labelnya terpotong justru saat jumlah
                                 tombolnya paling banyak. Baris atas untuk
                                 memilih, baris bawah untuk bertindak. -->
                            <div v-if="kolomPilih === kunciKolom(col)" class="plw-selbar">
                                <div class="plw-selbar__head">
                                    <button type="button" class="plw-selbar__all" @click="pilihKolom(col)">
                                        <i class="bi" :class="kolomTerpilihPenuh(col) ? 'bi-check-square-fill' : 'bi-square'"></i>
                                        {{ kolomTerpilihPenuh(col) ? 'Batal semua' : 'Pilih semua' }}
                                    </button>
                                    <span class="plw-selbar__n">{{ terpilih.length }}</span>
                                    <button type="button" class="plw-selbar__x" title="Selesai memilih" @click="tutupPilihKolom">
                                        <i class="bi bi-x-lg"></i>
                                    </button>
                                </div>
                                <div class="plw-selbar__aksi">
                                    <button
                                        type="button" class="plw-selbar__go" :disabled="!aktivitasTerpilih.length"
                                        :title="aktivitasTerpilih.length ? 'Jadwalkan kandidat terpilih sekaligus' : 'Tak ada aktivitas tatap muka yang bisa dijadwalkan'"
                                        @click="askJadwalMassal"
                                    >
                                        <i class="bi bi-calendar-plus"></i> Jadwalkan
                                    </button>
                                    <!-- TAHAN MASSAL. Penahanan hampir selalu lahir
                                         dari satu peristiwa yang mengenai banyak
                                         orang sekaligus ("MPP belum turun"), jadi
                                         menahannya satu per satu lewat drawer
                                         berarti mengulang pekerjaan yang sama
                                         belasan kali. -->
                                    <!-- KEPUTUSAN MASSAL. Satu angkatan diputus dalam
                                         satu peristiwa — sesudah rapat panel, sesudah
                                         hasil tes turun. Membuka drawer satu per satu
                                         untuk itu berarti mengulang pekerjaan yang sama
                                         puluhan kali, dan yang terlewat di tengah daftar
                                         tidak meninggalkan jejak apa pun. -->
                                    <button
                                        v-if="bolehPutus"
                                        type="button" class="plw-selbar__putus" :disabled="!bisaPutusMassal.length"
                                        :title="bisaPutusMassal.length
                                            ? `Ambil keputusan untuk ${bisaPutusMassal.length} kandidat terpilih`
                                            : 'Yang terpilih sedang ditahan atau tahapnya sudah diputus'"
                                        @click="askPutusMassal"
                                    >
                                        <i class="bi bi-hammer"></i> Keputusan
                                    </button>
                                    <button
                                        type="button" class="plw-selbar__hold" :disabled="!bisaTahanMassal.length"
                                        :title="bisaTahanMassal.length
                                            ? `Tahan ${bisaTahanMassal.length} kandidat terpilih`
                                            : 'Semua yang terpilih sudah ditahan atau tahapnya sudah diputus'"
                                        @click="askHoldMassal(true)"
                                    >
                                        <i class="bi bi-pause-circle"></i> Tahan
                                    </button>
                                    <button
                                        v-if="bisaLepasMassal.length"
                                        type="button" class="plw-selbar__lepas"
                                        :title="`Lanjutkan ${bisaLepasMassal.length} kandidat yang sedang ditahan`"
                                        @click="askHoldMassal(false)"
                                    >
                                        <i class="bi bi-play-circle"></i> Lanjutkan
                                    </button>
                                </div>
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

                    <!-- ═══ MODE LIST ═══
                         Satu baris per kandidat, berkolom tetap. Yang tak muat di
                         kartu kanban justru muncul di sini — kampus dan tanggal
                         melamar — sebab keduanya baru berguna ketika kandidat
                         dibandingkan berjajar, bukan saat dilihat satu per satu.

                         Di layar sempit tiap baris melipat jadi kartu (lihat CSS):
                         tabel tujuh kolom pada 390px hanya menghasilkan tulisan
                         setinggi satu huruf, dan menggeser mendatar untuk membaca
                         nama orang bukan cara siapa pun bekerja. -->
                    <div v-else-if="barisList.length" class="plw-list">
                        <!-- ═══ BILAH PILIH-BANYAK MODE LIST ═══
                             Di kanban, memilih banyak terkurung di SATU kolom —
                             dan itu benar di sana: kolomnya sendiri yang berarti
                             "orang-orang di tahap ini". Mode list tidak punya
                             kolom; yang ada penyaring. Jadi di sini pilihannya
                             bebas melintasi tahap, dan yang menjaga kebenaran
                             tetap sama: tiap tombol massal menyaring sendiri
                             siapa yang layak, lalu menyebut yang dilewati
                             sebelum apa pun disimpan. -->
                        <transition name="plw-sel">
                            <div v-if="terpilih.length" class="plw-lsel">
                                <span class="plw-lsel__n">{{ terpilih.length }}</span>
                                <span class="plw-lsel__txt">kandidat terpilih</span>
                                <!-- Jalan kedua ke "pilih semua". Di layar sempit
                                     kepala tabel disembunyikan seluruhnya (barisnya
                                     melipat jadi kartu), jadi kotak centang di sana
                                     tidak pernah terjangkau — dan tanpa tombol ini
                                     dua puluh kandidat harus dicentang satu-satu
                                     dengan jempol. -->
                                <button type="button" class="plw-lsel__all" @click="toggleHalaman">
                                    <i class="bi" :class="halamanTercentangPenuh ? 'bi-x-square' : 'bi-check2-square'"></i>
                                    {{ halamanTercentangPenuh ? 'Batal sehalaman' : 'Semua di halaman' }}
                                </button>
                                <div class="plw-lsel__aksi">
                                    <button
                                        type="button" class="plw-selbar__go" :disabled="!aktivitasTerpilih.length"
                                        :title="aktivitasTerpilih.length ? 'Jadwalkan kandidat terpilih sekaligus' : 'Tak ada aktivitas tatap muka yang bisa dijadwalkan'"
                                        @click="askJadwalMassal"
                                    >
                                        <i class="bi bi-calendar-plus"></i> Jadwalkan
                                    </button>
                                    <button
                                        v-if="bolehPutus"
                                        type="button" class="plw-selbar__putus" :disabled="!bisaPutusMassal.length"
                                        :title="bisaPutusMassal.length
                                            ? `Ambil keputusan untuk ${bisaPutusMassal.length} kandidat terpilih`
                                            : 'Yang terpilih sedang ditahan atau tahapnya sudah diputus'"
                                        @click="askPutusMassal"
                                    >
                                        <i class="bi bi-hammer"></i> Keputusan
                                    </button>
                                    <button
                                        type="button" class="plw-selbar__hold" :disabled="!bisaTahanMassal.length"
                                        :title="bisaTahanMassal.length
                                            ? `Tahan ${bisaTahanMassal.length} kandidat terpilih`
                                            : 'Semua yang terpilih sudah ditahan atau tahapnya sudah diputus'"
                                        @click="askHoldMassal(true)"
                                    >
                                        <i class="bi bi-pause-circle"></i> Tahan
                                    </button>
                                    <button
                                        v-if="bisaLepasMassal.length"
                                        type="button" class="plw-selbar__lepas"
                                        :title="`Lanjutkan ${bisaLepasMassal.length} kandidat yang sedang ditahan`"
                                        @click="askHoldMassal(false)"
                                    >
                                        <i class="bi bi-play-circle"></i> Lanjutkan
                                    </button>
                                </div>
                                <button type="button" class="plw-lsel__x" title="Batalkan pilihan" @click="terpilih = []">
                                    <i class="bi bi-x-lg"></i>
                                </button>
                            </div>
                        </transition>

                        <div class="plw-list__head">
                            <!-- Centang kepala hanya menyentuh HALAMAN INI. Menyapu
                                 seluruh hasil saringan dari satu centang terlalu
                                 mudah dilakukan tanpa sengaja, dan yang tersapu
                                 tidak terlihat di layar untuk diperiksa. -->
                            <span class="plw-lcek plw-lcek--head">
                                <button
                                    type="button" class="plw-tick"
                                    :class="{ 'is-on': halamanTercentangPenuh, 'is-half': halamanTercentangSebagian }"
                                    :title="halamanTercentangPenuh ? 'Batalkan pilihan di halaman ini' : 'Pilih semua di halaman ini'"
                                    :aria-pressed="halamanTercentangPenuh"
                                    @click="toggleHalaman"
                                >
                                    <i v-if="halamanTercentangPenuh" class="bi bi-check-lg"></i>
                                    <i v-else-if="halamanTercentangSebagian" class="bi bi-dash-lg"></i>
                                </button>
                            </span>
                            <span>KANDIDAT</span>
                            <span>POSISI</span>
                            <span>TAHAP</span>
                            <span>KAMPUS</span>
                            <span>BERKAS</span>
                            <span>KEADAAN</span>
                            <span>MELAMAR</span>
                            <span style="text-align: right">AKSI</span>
                        </div>
                        <div
                            v-for="r in barisList" :key="r.id"
                            class="plw-lrow" :class="{ 'is-hold': !!r.hold, 'is-terpilih': terpilih.includes(r.id) }"
                            role="button" tabindex="0"
                            @click="bukaKandidat(r)"
                            @keyup.enter="bukaKandidat(r)"
                        >
                            <!-- .stop: barisnya sendiri membuka kandidat. Tanpa ini
                                 mencentang orang justru membuka profilnya, dan
                                 memilih dua puluh orang berarti menutup dua puluh
                                 modal. -->
                            <span class="plw-lcek" @click.stop>
                                <button
                                    type="button" class="plw-tick" :class="{ 'is-on': terpilih.includes(r.id) }"
                                    :title="terpilih.includes(r.id) ? `Batal memilih ${r.pelamar}` : `Pilih ${r.pelamar}`"
                                    :aria-pressed="terpilih.includes(r.id)"
                                    @click="toggleBarisList(r)"
                                >
                                    <i v-if="terpilih.includes(r.id)" class="bi bi-check-lg"></i>
                                </button>
                            </span>
                            <span class="plw-lcell plw-lcell--who">
                                <span class="plw-card__avatar" :style="{ background: avatarBg(r) }">{{ inisial(r.pelamar) }}</span>
                                <span style="min-width: 0">
                                    <span class="plw-lname">{{ r.pelamar }}</span>
                                    <span class="plw-lcode">{{ r.lamaranKode }}</span>
                                </span>
                            </span>
                            <span class="plw-lcell" data-k="Posisi">
                                <span class="plw-ell" :title="r.posisi">{{ r.posisi || '—' }}</span>
                            </span>
                            <span class="plw-lcell" data-k="Tahap">
                                <span class="plw-lpill" :title="r.tahap">{{ r.tahap || '—' }}</span>
                            </span>
                            <span class="plw-lcell" data-k="Kampus">
                                <span class="plw-ell" :title="r.kampus || 'Kampus belum terisi di formulir'">{{ r.kampus || '—' }}</span>
                            </span>
                            <!-- BERKAS — jumlah lampiran formulir kandidat.
                                 "Belum ada" ditandai berbeda, bukan sekadar angka
                                 nol: itu satu-satunya keadaan yang menuntut
                                 tindakan, dan nol di tengah deretan angka lain
                                 terlalu mudah terlewat. -->
                            <span class="plw-lcell" data-k="Berkas">
                                <span
                                    class="plw-ldok" :class="{ 'is-kosong': !r.jmlBerkasForm }"
                                    :title="r.jmlBerkasForm ? `${r.jmlBerkasForm} berkas terlampir di formulir` : 'Kandidat belum melampirkan berkas apa pun'"
                                >
                                    <i class="bi" :class="r.jmlBerkasForm ? 'bi-paperclip' : 'bi-dash-circle'"></i>
                                    {{ r.jmlBerkasForm ? `${r.jmlBerkasForm} berkas` : 'Belum ada' }}
                                </span>
                            </span>
                            <span class="plw-lcell" data-k="Keadaan">
                                <span class="plw-card__chip" :class="'tone-' + r.badge.tone" style="margin-top: 0">{{ r.badge.teks }}</span>
                            </span>
                            <span class="plw-lcell" data-k="Melamar">
                                <span v-if="r.waktuLamar" class="plw-ltgl" :title="'Melamar ' + tglId(r.waktuLamar)">
                                    {{ tglId(r.waktuLamar) }}<small>{{ umurHari(r.waktuLamar) }}</small>
                                </span>
                                <span v-else>—</span>
                            </span>
                            <span class="plw-lcell plw-lcell--act">
                                <button
                                    v-if="r.tahapId && r.statusLamaran === 'BERJALAN'"
                                    type="button" class="plw-card__hold" :class="{ 'is-on': !!r.hold }"
                                    :title="r.hold ? 'Sedang ditahan — klik untuk melanjutkan' : 'Tahan kandidat ini (tanpa email ke kandidat)'"
                                    @click.stop="askHold(!r.hold, r)"
                                >
                                    <i class="bi" :class="r.hold ? 'bi-play-fill' : 'bi-pause-fill'"></i>
                                </button>
                                <button type="button" class="plw-ldetail" @click.stop="bukaKandidat(r)">Detail</button>
                            </span>
                        </div>
                    </div>

                    <!-- TIDAK ADA YANG COCOK. Dibedakan dari "program ini memang
                         belum punya pelamar": yang pertama diperbaiki dengan
                         mencopot saringan, yang kedua tidak bisa diperbaiki
                         siapa pun — dan menyamakan keduanya membuat admin
                         mencari-cari tombol yang tak akan menolongnya. -->
                    <div v-else class="plw-kosong">
                        <span class="plw-kosong__ico"><i class="bi bi-search"></i></span>
                        <div class="plw-kosong__judul">{{ chipFilter.length ? 'Tidak ada kandidat yang cocok' : 'Belum ada kandidat' }}</div>
                        <div class="plw-kosong__sub">
                            {{ chipFilter.length
                                ? 'Tidak ada yang memenuhi saringan ini. Copot salah satunya untuk melebarkan hasil.'
                                : 'Tab ini masih kosong untuk program tersebut.' }}
                        </div>
                        <button v-if="chipFilter.length" type="button" class="plw-kosong__btn" @click="bersihkanFilter">Bersihkan saringan</button>
                    </div>

                    <!-- PAGINASI — hanya mode list. Kanban tidak dipaginasi:
                         kolomnya sudah membagi populasi, dan memotongnya lagi per
                         halaman berarti tumpukan yang terbaca di layar bukan
                         tumpukan yang sebenarnya. -->
                    <div v-if="mode === 'list' && totalList > 0" class="plw-pagerbar">
                        <div class="plw-pagerbar__info">Halaman {{ halamanList }} dari {{ totalHalamanList }} · {{ totalList }} kandidat</div>
                        <div class="plw-pagerbar__nav">
                            <button type="button" :disabled="halamanList <= 1" title="Sebelumnya" @click="listPage = halamanList - 1">
                                <i class="bi bi-chevron-left"></i>
                            </button>
                            <button
                                v-for="(n, i) in nomorHalaman" :key="i"
                                type="button" class="plw-pnum"
                                :class="{ 'is-on': n === halamanList, 'is-gap': n === '…' }"
                                :disabled="n === '…'"
                                @click="n !== '…' && (listPage = n)"
                            >{{ n }}</button>
                            <button type="button" :disabled="halamanList >= totalHalamanList" title="Berikutnya" @click="listPage = halamanList + 1">
                                <i class="bi bi-chevron-right"></i>
                            </button>
                        </div>
                        <div class="plw-pagerbar__per">
                            Per halaman
                            <el-select v-model="listPer" class="plw-perpage">
                                <el-option v-for="n in [10, 20, 50, 100]" :key="n" :value="n" :label="String(n)" />
                            </el-select>
                        </div>
                    </div>
                </template>
                <div v-else class="plw-empty" style="padding: 4rem 0">Pilih program di panel kiri untuk melihat papan seleksi.</div>
            </div>
        </main>

        <!-- ═══ MODAL DETAIL KANDIDAT ═══
             Dulu sebuah drawer selebar 560px yang merayap dari kanan. Bentuk itu
             benar untuk "mengintip sambil papan tetap terlihat" — tapi bukan
             itu yang terjadi di sini: begitu drawer terbuka, seluruh pekerjaan
             pindah ke dalamnya (rapor tes, berkas, keputusan), dan 560px terlalu
             sempit untuk semuanya. Kisi biodata jadi satu kolom, tombol
             keputusan terpotong per kata, dan riwayat kerja digulung berkali-kali.

             Sekarang: modal EVO ber-ukuran XL, isinya dibagi TIGA TAB. Tab
             memisahkan tiga pekerjaan yang memang tidak dilakukan bersamaan —
             menilai hasil tes, memeriksa berkas, dan melihat posisi kandidat di
             alur — sehingga masing-masing dapat lebar penuh. -->
        <AdminModal
            :show="!!detailKandidat"
            size="xl"
            icon="bi-person-vcard-fill"
            :title="detailKandidat ? detailKandidat.pelamar : ''"
            :subtitle="detailKandidat ? `${detailKandidat.posisi} · ${detailKandidat.lamaranKode}` : ''"
            foot-note=""
            foot-blok
            @close="tutupKandidat"
        >
            <!-- HERO — identitas, konteks lowongan, kontak, dan tab.
                 Ditaruh di slot `sticky` AdminModal, BUKAN di isi biasa: slot itu
                 duduk tepat di puncak area gulir tanpa padding yang perlu
                 dinetralkan margin negatif. Bentuk lamanya menetralkan padding
                 dengan angka yang ditulis ulang di sini, dan selisih beberapa
                 piksel di antara keduanya menjadi celah — jalur tempat baris di
                 bawahnya terlihat menyembul di atas hero saat isi digulir. -->
            <template v-if="detailKandidat" #sticky>
                <div class="plw-hero">
                    <div class="plw-hero__row">
                        <div class="plw-hero__avatar" :style="{ background: avatarBg(detailKandidat) }">{{ inisial(detailKandidat.pelamar) }}</div>
                        <div style="flex: 1; min-width: 0">
                            <div class="plw-hero__tags">
                                <span class="plw-drawer__tag" :class="detailKandidat.kategori === 'MT' ? 'is-mt' : 'is-rek'">{{ katLabel(detailKandidat.kategori) }}</span>
                                <span class="plw-card__chip" :class="'tone-' + detailKandidat.badge.tone" style="margin-top: 0">{{ detailKandidat.badge.teks }}</span>
                            </div>
                            <!-- Nama RESMI dari formulir. Nama akun disebut di
                                 bawahnya HANYA bila berbeda: rekruter perlu tahu
                                 akun mana yang akan menerima surelnya, dan
                                 menyembunyikannya membuat "SUPRIADI MAMI PERI"
                                 di layar ini tak bisa dicocokkan dengan
                                 "SUPRIADI" di kotak masuk. -->
                            <div class="plw-hero__name">{{ detailKandidat.pelamar }}</div>
                            <div v-if="namaAkunBeda(detailKandidat)" class="plw-drawer__akun">
                                <i class="bi bi-person-badge"></i> Nama akun: {{ detailKandidat.pelamarAkun }}
                            </div>
                            <div class="plw-drawer__meta">{{ detailKandidat.posisi }} · <span class="plw-mono">{{ detailKandidat.lamaranKode }}</span></div>
                            <!-- Konteks lowongan & kontak: dulu admin harus menebak
                                 atau membuka layar lain untuk tahu ini. -->
                            <div class="plw-drawer__chips">
                                <span v-if="detailKandidat.departemen"><i class="bi bi-diagram-3"></i> {{ detailKandidat.departemen }}</span>
                                <span v-if="detailKandidat.lokasi"><i class="bi bi-geo-alt"></i> {{ detailKandidat.lokasi }}</span>
                                <span v-if="detailKandidat.level"><i class="bi bi-bar-chart-steps"></i> {{ detailKandidat.level }}</span>
                                <span v-if="detailKandidat.kampus"><i class="bi bi-mortarboard-fill"></i> {{ detailKandidat.kampus }}</span>
                                <span v-if="detailKandidat.mppRef" class="is-mpp">{{ detailKandidat.mppRef }}</span>
                                <span v-if="detailKandidat.waktuLamar"><i class="bi bi-clock-history"></i> {{ tglId(detailKandidat.waktuLamar) }} · {{ umurHari(detailKandidat.waktuLamar) }}</span>
                                <a v-if="detailKandidat.email" :href="`mailto:${detailKandidat.email}`" class="is-link"><i class="bi bi-envelope"></i> {{ detailKandidat.email }}</a>
                                <a v-if="detailKandidat.hp" :href="`https://wa.me/${String(detailKandidat.hp).replace(/\D/g, '')}`" target="_blank" rel="noopener" class="is-link"><i class="bi bi-whatsapp"></i> {{ detailKandidat.hp }}</a>
                            </div>
                        </div>
                        <!-- CETAK LAPORAN — tersedia untuk SETIAP kandidat di
                             papan, apa pun tahap & statusnya. Laporan paling
                             sering justru diminta untuk yang sudah selesai
                             (arsip keputusan), bukan yang sedang berjalan. -->
                        <button type="button" class="plw-drawer__cetak" title="Cetak laporan kandidat (PDF)" @click="askLaporan">
                            <i class="bi bi-printer-fill"></i> Cetak
                        </button>
                    </div>

                    <div class="plw-mtabs" role="tablist">
                        <button
                            v-for="t in tabDetail" :key="t.key"
                            type="button" class="plw-mtab" :class="{ 'is-on': tabAktif === t.key }"
                            role="tab" :aria-selected="tabAktif === t.key"
                            @click="tabAktif = t.key"
                        >
                            <i class="bi" :class="t.ikon"></i><span>{{ t.label }}</span>
                            <span v-if="t.jumlah" class="plw-mtab__n">{{ t.jumlah }}</span>
                        </button>
                    </div>
                </div>
            </template>

            <template v-if="detailKandidat">
                <div v-show="tabAktif === 'rapor'" class="plw-tabpane">
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
                                    <div v-for="n in detailKandidat.totalTahap" :key="n" class="plw-segbar" :class="segKelas(n)"></div>
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
                                <!-- ANGKANYA DISEMBUNYIKAN pada alat tes yang keputusannya milik
                                     penilai (PAPI Kostick, DISC, Kraeplin) — sebelum maupun
                                     sesudah diputuskan. Keluarannya profil, bukan nilai
                                     kelulusan; yang tersisa cukup lencana statusnya.
                                     Server yang menentukan lewat `skorBermakna`. -->
                                <span v-if="t.nilai != null && t.skorBermakna" class="plw-test__score">{{ t.nilai }}</span>
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
                                    <i class="bi" :class="lanjutId === t.id ? 'bi-arrow-repeat plw-putar' : 'bi-arrow-right-circle-fill'"></i>
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
                                <!-- KEPUTUSAN LANGSUNG — ujian online ber-peran INFORMATIF.
                                     Kandidat sudah mengerjakan, nilainya sudah masuk dari
                                     HCLearn; yang tersisa satu hal saja: layak atau tidak.
                                     Karena itu dua tombol, bukan jendela berisi bidang nilai
                                     dan catatan yang tak satu pun perlu diisi. Sekali klik
                                     tanpa konfirmasi — ini bukan aksi final yang menutup
                                     lamaran, dan tahapnya masih bisa dinilai ulang lewat
                                     keputusan tahap. -->
                                <template v-if="bisaPutusTes(t)">
                                    <button
                                        type="button" class="plw-test__ver is-lulus" :disabled="keputusanId === t.id"
                                        title="Kandidat dinyatakan LULUS pada aktivitas ini"
                                        @click="putusTes(t, 'LULUS')"
                                    >
                                        <i class="bi" :class="keputusanId === t.id && keputusanHasil === 'LULUS' ? 'bi-arrow-repeat plw-putar' : 'bi-check-circle-fill'"></i>
                                        Lulus
                                    </button>
                                    <button
                                        type="button" class="plw-test__ver is-gagal" :disabled="keputusanId === t.id"
                                        title="Kandidat dinyatakan TIDAK LULUS pada aktivitas ini"
                                        @click="putusTes(t, 'GAGAL')"
                                    >
                                        <i class="bi" :class="keputusanId === t.id && keputusanHasil === 'GAGAL' ? 'bi-arrow-repeat plw-putar' : 'bi-x-circle-fill'"></i>
                                        Tidak Lulus
                                    </button>
                                </template>
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
                                    <i class="bi" :class="sinkronId === t.id ? 'bi-arrow-repeat plw-putar' : 'bi-cloud-download'"></i>
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

                    <!-- ═══ PROGRES SELEKSI ═══
                         Seluruh tahap program dalam satu linimasa, dengan posisi
                         kandidat ini di dalamnya.

                         Ada DI KAKI RAPOR, bukan sebagai tab tersendiri. Pertanyaan
                         yang dijawabnya — "tes ini tahap keberapa, sesudah ini apa
                         lagi?" — muncul justru sambil membaca rapor, dan tab
                         terpisah membuat jawabannya berada satu klik lebih jauh
                         dari pertanyaannya. -->
                    <div class="plw-alur">
                        <div class="plw-alur__head">
                            <span class="plw-alur__lbl">PROGRES SELEKSI</span>
                            <span class="plw-alur__pos">Tahap {{ detailKandidat.urutan }} dari {{ detailKandidat.totalTahap }}</span>
                        </div>
                        <div class="plw-alur__bar"><div :style="{ width: persenAlur + '%' }"></div></div>

                        <ol class="plw-alur__line">
                            <li v-for="s in alurKandidat" :key="s.kunci" class="plw-alur__item" :class="'is-' + s.keadaan">
                                <span class="plw-alur__node"><span></span></span>
                                <div class="plw-alur__isi">
                                    <div style="min-width: 0">
                                        <div class="plw-alur__nama">{{ s.nomor }}. {{ s.label }}</div>
                                        <div class="plw-alur__ket">{{ s.catatan }}</div>
                                    </div>
                                    <span class="plw-alur__tag">{{ s.tag }}</span>
                                </div>
                            </li>
                        </ol>
                    </div>
                </div>

                <!-- TAB: BERKAS & BIODATA -->
                <div v-show="tabAktif === 'berkas'" class="plw-tabpane">
                    <div>
                        <div class="plw-secrow">
                            <div class="plw-sectitle">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#6366f1" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7a2 2 0 0 1 2-2h4l2 2h6a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H4z" /></svg>
                                Berkas &amp; Biodata
                            </div>
                            <!-- Hitungan menyebut DUA hal yang berbeda: berapa
                                 formulir, dan berapa dokumen dari yang diminta.
                                 "7/7" itulah yang dicari verifikator lebih dulu —
                                 jumlah formulir saja tidak pernah menjawabnya. -->
                            <span class="plw-seccount">{{ profil.formulir.length }} formulir · {{ dokLengkap }}/{{ dokTotal }} dokumen</span>
                            <!-- DUA CARA MEMBACA ISI YANG SAMA.
                                 Daftar = jawaban beserta pertanyaannya (bawaan);
                                 Berkas = lembarannya saja, berfolder. Keduanya
                                 memakai data yang sama persis — yang berganti
                                 hanya sudut pandangnya. -->
                            <div class="plw-fmview" role="group" aria-label="Cara menampilkan berkas">
                                <button
                                    type="button" class="plw-fmview__b" :class="{ 'is-on': berkasMode === 'berkas' }"
                                    title="Tampilan berkas — folder per formulir, petak lembaran"
                                    :aria-pressed="berkasMode === 'berkas'"
                                    @click="setBerkasMode('berkas')"
                                >
                                    <i class="bi bi-grid-1x2-fill"></i>
                                </button>
                                <button
                                    type="button" class="plw-fmview__b" :class="{ 'is-on': berkasMode === 'daftar' }"
                                    title="Tampilan daftar — jawaban beserta pertanyaannya"
                                    :aria-pressed="berkasMode === 'daftar'"
                                    @click="setBerkasMode('daftar')"
                                >
                                    <i class="bi bi-list-ul"></i>
                                </button>
                            </div>
                        </div>

                        <div v-if="loadingProfil" class="plw-load" style="padding: 1.5rem 0"><span class="plw-spin"></span> Memuat berkas…</div>
                        <div v-else-if="!profil.formulir.length" class="plw-empty" style="padding: 1.5rem 0">Belum ada formulir terisi.</div>

                        <!-- ═══ FILE MANAGER ═══
                             Folder kiri (satu per formulir) + petak berkas kanan.
                             Menjawab pertanyaan yang di mode daftar menuntut
                             membuka tiap akordion satu per satu: "mana ijazahnya?"
                             Sekali klik menyorot, bilah pratinjau di kaki panel
                             yang membukanya — supaya klik meleset di petak rapat
                             tidak langsung melempar tab baru. -->
                        <div v-else-if="berkasMode === 'berkas'" class="plw-fm">
                            <aside class="plw-fm__side">
                                <div class="plw-fm__sidehead">
                                    <span class="plw-fm__sideico"><i class="bi bi-folder2-open"></i></span>
                                    <span class="plw-fm__sidetxt">
                                        <b>Berkas Kandidat</b>
                                        <em>{{ fmSemua.length }} lembar · {{ profil.formulir.length }} formulir</em>
                                    </span>
                                </div>
                                <div class="plw-fm__folders">
                                    <button
                                        v-for="fd in fmFolderOpsi" :key="fd.key || 'all'"
                                        type="button" class="plw-fm__folder" :class="{ 'is-on': fmFolder === fd.key }"
                                        :title="fd.label"
                                        @click="fmFolder = fd.key; fmSorot = ''"
                                    >
                                        <span class="plw-fm__fico"><i class="bi" :class="fd.ikon"></i></span>
                                        <span class="plw-fm__flabel">{{ fd.label }}</span>
                                        <span class="plw-fm__fn">{{ fd.jumlah }}</span>
                                    </button>
                                </div>
                                <div class="plw-fm__meter">
                                    <div class="plw-fm__meterhead">
                                        <span>KELENGKAPAN</span>
                                        <span>{{ fmLengkap }} / {{ profil.formulir.length }}</span>
                                    </div>
                                    <div class="plw-fm__bar"><div :style="{ width: fmPersen + '%' }"></div></div>
                                </div>
                            </aside>

                            <section class="plw-fm__main">
                                <div class="plw-fm__toolbar">
                                    <div class="plw-fm__crumb">
                                        <span>Berkas</span>
                                        <i class="bi bi-chevron-right"></i>
                                        <b :title="fmJudul">{{ fmJudul }}</b>
                                    </div>
                                    <div class="plw-fm__cari">
                                        <i class="bi bi-search"></i>
                                        <input v-model="fmCari" type="text" placeholder="Cari berkas atau pertanyaannya…">
                                        <button v-if="fmCari" type="button" title="Bersihkan" @click="fmCari = ''"><i class="bi bi-x-lg"></i></button>
                                    </div>
                                </div>

                                <div v-if="fmBerkas.length" class="plw-fm__grid">
                                    <button
                                        v-for="b in fmBerkas" :key="b.id"
                                        type="button" class="plw-fm__file" :class="{ 'is-on': fmSorot === b.id }"
                                        :title="b.file"
                                        @click="fmSorot = fmSorot === b.id ? '' : b.id"
                                    >
                                        <span class="plw-fm__fileico" :class="{ 'is-img': b.isImage }">
                                            <i class="bi" :class="ikonBerkas(b)"></i>
                                        </span>
                                        <span class="plw-fm__filenama">{{ b.nama }}</span>
                                        <span class="plw-fm__filefile">{{ b.file }}</span>
                                        <span class="plw-fm__filefoot">
                                            <span class="plw-fm__fileext">{{ b.ext || 'FILE' }}</span>
                                            <span class="plw-fm__filedari">{{ b.konteks }}</span>
                                        </span>
                                    </button>
                                </div>
                                <div v-else class="plw-fm__kosong">
                                    <span class="plw-fm__kosongico"><i class="bi bi-folder-x"></i></span>
                                    <b>{{ fmCari ? 'Berkas tidak ditemukan' : 'Folder ini tidak berisi berkas' }}</b>
                                    <span>{{ fmCari ? 'Coba kata kunci lain atau pilih folder lain.' : 'Formulir ini terisi, tapi tidak ada pertanyaan berkasnya.' }}</span>
                                </div>

                                <!-- PRATINJAU — menempel di kaki panel, bukan melayang.
                                     Menyebut ASAL berkasnya sebelum dibuka: satu kandidat
                                     bisa mengunggah tiga "SCAN.pdf" dari tiga formulir
                                     berbeda, dan nama filenya tidak membedakan apa pun. -->
                                <div v-if="fmTerpilih" class="plw-fm__pratinjau">
                                    <span class="plw-fm__pico"><i class="bi" :class="ikonBerkas(fmTerpilih)"></i></span>
                                    <span class="plw-fm__pin">
                                        <b>{{ fmTerpilih.nama }}</b>
                                        <em>{{ fmTerpilih.konteks }} · {{ fmTerpilih.file }}</em>
                                    </span>
                                    <button type="button" class="plw-fm__pbtn" @click="bukaDok(fmTerpilih.berkas)">
                                        <i class="bi bi-box-arrow-up-right"></i> Buka Berkas
                                    </button>
                                </div>
                            </section>
                        </div>

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
                                    <!-- Formulir yang isiannya kosong tapi berkasnya ada
                                         (mis. hanya foto verifikasi) tetap menggambar kisi
                                         ini — kalau syaratnya cuma jawaban, berkasnya ikut
                                         hilang bersama kisinya. -->
                                    <template v-if="f.jawaban.length || berkasLepas(f).length">
                                    <!-- Pil ringkas: menjawab "formulir ini sebenarnya
                                         berisi apa" sebelum satu barisnya dibaca. -->
                                    <div class="plw-fsum">
                                        <i class="bi bi-check2-square"></i>
                                        <span>{{ ringkasFormulir(f) }}</span>
                                    </div>

                                    <!-- ═══ BIODATA, DIKELOMPOKKAN SEPERTI SAAT DIISI ═══
                                         Judul & ikon kelompok datang dari skema formulir
                                         (langkah), bukan dari nama kunci. Lihat grupIsian(). -->
                                    <template v-for="g in grupIsian(f)" :key="g.kunci">
                                    <div class="plw-ghead">
                                        <span class="plw-ghead__ico"><i class="bi" :class="g.ikon"></i></span>
                                        <span class="plw-ghead__lbl">{{ g.judul }}</span>
                                        <span class="plw-ghead__garis"></span>
                                    </div>
                                    <div class="plw-fields">
                                        <div
                                            v-for="j in g.items" :key="j.key"
                                            class="plw-field" :class="{ 'is-panjang': isianPanjang(j) || (j.baris && j.baris.length) }"
                                        >
                                            <div v-if="!(j.baris && j.baris.length)" class="plw-field__k">{{ labelIsian(f, j) }}</div>
                                            <!-- ISIAN BERULANG (riwayat kerja, organisasi,
                                                 sertifikasi) — LINIMASA, bukan deretan
                                                 pasangan label-nilai yang mengalir bebas.

                                                 Bentuk lamanya memakai flex-wrap: tiap
                                                 pasangan selebar isinya sendiri, jadi
                                                 "Perusahaan" di baris 1 dan "Perusahaan" di
                                                 baris 2 berhenti di tempat yang berbeda.
                                                 Mata tidak punya kolom untuk diikuti, dan
                                                 uraian panjang menyeret sisa pasangan ke
                                                 posisi acak — inilah yang terbaca sebagai
                                                 tumpang tindih.

                                                 Sekarang: satu kartu per baris di satu garis
                                                 waktu, isinya grid berkolom tetap. Yang
                                                 panjang (uraian) mengambil baris sendiri,
                                                 jadi ia tidak lagi mendorong tetangganya.

                                                 Akordion + gulung dalam: riwayat kerja bisa
                                                 belasan baris, dan tanpa batas tinggi satu
                                                 field mendorong seluruh isi formulir jauh ke
                                                 bawah. -->
                                            <div v-if="j.baris && j.baris.length" class="plw-field__v plw-tl">
                                                <button
                                                    type="button" class="plw-tl__head"
                                                    :aria-expanded="!riwayatTutup(f.no, j.key)"
                                                    @click="toggleRiwayat(f.no, j.key)"
                                                >
                                                    <span class="plw-tl__ico"><i class="bi" :class="ikonRiwayat(j)"></i></span>
                                                    <span class="plw-tl__cap">{{ labelIsian(f, j) }}</span>
                                                    <span class="plw-tl__n">{{ j.baris.length }}</span>
                                                    <span class="plw-tl__garis"></span>
                                                    <svg
                                                        class="plw-tl__chev" :class="{ 'is-up': !riwayatTutup(f.no, j.key) }"
                                                        width="15" height="15" viewBox="0 0 24 24" fill="none"
                                                        stroke="currentColor" stroke-width="2.6" stroke-linecap="round"
                                                    ><path d="M6 9l6 6 6-6" /></svg>
                                                </button>

                                                <div v-show="!riwayatTutup(f.no, j.key)" class="plw-tl__body">
                                                    <ol class="plw-tl__line">
                                                        <li v-for="(row, ri) in j.baris" :key="ri" class="plw-tl__item">
                                                            <span class="plw-tl__dot">{{ ri + 1 }}</span>
                                                            <div class="plw-tl__card">
                                                                <!-- KEPALA KARTU — judul + pil periode di kanan.
                                                                     Dulu keduanya sekadar dua sel di kisi yang
                                                                     sama: nama tempat kerja dan rentang tahunnya
                                                                     tampil sebesar dan sepucat kolom lain, jadi
                                                                     satu daftar berisi lima riwayat tidak punya
                                                                     satu pun titik pandang. -->
                                                                <div v-if="selBarisLead(j.baris, row)" class="plw-tl__top">
                                                                    <span class="plw-tl__judul">{{ nilaiTampil(selBarisLead(j.baris, row)) }}</span>
                                                                    <span v-if="selBarisPeriode(j.baris, row)" class="plw-tl__periode">{{ selBarisPeriode(j.baris, row) }}</span>
                                                                </div>
                                                                <!-- ISIAN RINGKAS — kisi berkolom tetap.
                                                                     Yang pertama dinaikkan jadi judul kartu:
                                                                     pada riwayat kerja/organisasi kolom
                                                                     pertama selalu "nama tempatnya", dan
                                                                     itulah yang dicari mata lebih dulu.

                                                                     Sub-isian BERKAS tidak ikut kisi ini:
                                                                     ia turun ke jalur lampiran di dasar
                                                                     kartu (lihat di bawah), sebab satu baris
                                                                     bisa membawa beberapa lembar sekaligus
                                                                     dan sel kisi tak muat memuat daftar. -->
                                                                <div v-if="adaRingkas(j.baris, row)" class="plw-tl__grid">
                                                                    <template v-for="(p, pi) in row" :key="'r' + pi">
                                                                        <div
                                                                            v-if="!selUraian(j.baris, p, pi) && !berkasSel(p).length && !selSudahDipakai(j.baris, row, p, pi)"
                                                                            class="plw-tl__cell"
                                                                        >
                                                                            <span v-if="p.label" class="plw-tl__k">{{ p.label }}</span>
                                                                            <span class="plw-tl__v" :class="{ 'is-rp': selRupiah(p) }">{{ nilaiTampil(p) }}</span>
                                                                        </div>
                                                                    </template>
                                                                </div>

                                                                <!-- URAIAN — satu blok penuh, DILIPAT.
                                                                     Panjangnya tak terbatas dan tak seragam antar
                                                                     kandidat, jadi yang menentukan perlu tidaknya
                                                                     tombol adalah hasil UKUR di layar, bukan jumlah
                                                                     hurufnya. Lihat UraianLipat.vue. -->
                                                                <template v-for="(p, pi) in row" :key="'u' + pi">
                                                                    <div v-if="selUraian(j.baris, p, pi)" class="plw-tl__note">
                                                                        <span v-if="p.label" class="plw-tl__k">{{ p.label }}</span>
                                                                        <UraianLipat :teks="String(p.nilai ?? '')" :baris="4" />
                                                                    </div>
                                                                </template>

                                                                <!-- LAMPIRAN BARIS INI — BISA LEBIH DARI SATU.
                                                                     Satu sertifikat kerap datang berlembar:
                                                                     piagamnya, transkrip nilainya, surat
                                                                     keterangannya. Bentuk lama hanya sanggup
                                                                     menggambar SATU tombol per sub-isian,
                                                                     jadi lembar kedua dan seterusnya masuk ke
                                                                     basis data lalu tak pernah muncul di layar
                                                                     mana pun — tanpa galat, tanpa jejak.
                                                                     Sekarang seluruhnya berjajar di jalurnya
                                                                     sendiri, bernomor, di dasar kartu. -->
                                                                <template v-for="(p, pi) in row" :key="'b' + pi">
                                                                    <div v-if="berkasSel(p).length" class="plw-tl__berkas">
                                                                        <div class="plw-tl__berkashead">
                                                                            <i class="bi bi-paperclip"></i>
                                                                            <span>{{ p.label || 'Lampiran' }}</span>
                                                                            <span class="plw-tl__berkasn">{{ berkasSel(p).length }}</span>
                                                                        </div>
                                                                        <button
                                                                            v-for="(b, bi) in berkasSel(p)" :key="bi"
                                                                            type="button" class="plw-tl__file"
                                                                            :title="b.nama" @click="bukaDok(b)"
                                                                        >
                                                                            <span class="plw-tl__fileico">
                                                                                <i class="bi" :class="b.isImage ? 'bi-file-earmark-image-fill' : 'bi-file-earmark-pdf-fill'"></i>
                                                                            </span>
                                                                            <span class="plw-tl__filenama">{{ b.nama }}</span>
                                                                            <span class="plw-tl__fileext">{{ (b.ext || '').toUpperCase() }}</span>
                                                                            <span class="plw-tl__filego">Lihat</span>
                                                                        </button>
                                                                    </div>
                                                                </template>
                                                            </div>
                                                        </li>
                                                    </ol>
                                                </div>
                                            </div>
                                            <!-- Nominal rupiah tampil sebagai UANG, bukan deret
                                                 angka. "9500000" memaksa peninjau menghitung
                                                 digitnya sendiri untuk tahu ini sembilan juta
                                                 atau sembilan puluh juta — dan itu keliru
                                                 justru saat menawar gaji. -->
                                            <div v-else class="plw-field__v" :class="{ 'is-rp': isianRupiah(f, j), 'is-mono': selMono(f, j) }">{{ nilaiIsian(f, j) }}</div>
                                        </div>
                                    </div>
                                    </template>

                                    <!-- ═══ DOKUMEN TERLAMPIR ═══
                                         Seksi sendiri, bukan baris di dalam kisi isian.
                                         Termasuk berkas tanpa pertanyaan (foto verifikasi,
                                         yang diambil sistem saat melamar) dan pertanyaan
                                         dokumen yang masih KOSONG — kartu redup yang tak
                                         bisa ditekan. Daftar yang hanya memuat berkas yang
                                         ada tak pernah bisa menjawab apa yang belum masuk. -->
                                    <template v-if="dokFormulir(f).length">
                                    <div class="plw-ghead">
                                        <span class="plw-ghead__ico"><i class="bi bi-folder2-open"></i></span>
                                        <span class="plw-ghead__lbl">Dokumen Terlampir</span>
                                        <span class="plw-ghead__n">{{ dokFormulir(f).filter((d) => d.berkas).length }} / {{ dokFormulir(f).length }}</span>
                                        <span class="plw-ghead__garis"></span>
                                    </div>
                                    <div class="plw-docgrid">
                                        <component
                                            :is="d.berkas ? 'button' : 'div'"
                                            v-for="d in dokFormulir(f)" :key="d.id"
                                            :type="d.berkas ? 'button' : null"
                                            class="plw-doc" :class="{ 'is-kosong': !d.berkas }"
                                            :title="d.berkas ? d.berkas.nama : 'Belum diunggah kandidat'"
                                            @click="d.berkas && bukaDok(d.berkas)"
                                        >
                                            <span class="plw-doc__ico">
                                                <i v-if="d.berkas" class="bi" :class="d.berkas.isImage ? 'bi-file-earmark-image-fill' : 'bi-file-earmark-pdf-fill'"></i>
                                                <i v-else class="bi bi-file-earmark-x"></i>
                                            </span>
                                            <span class="plw-doc__in">
                                                <span class="plw-doc__nama">{{ d.nama }}</span>
                                                <span class="plw-doc__file">{{ d.berkas ? d.berkas.nama : 'Belum diunggah' }}</span>
                                            </span>
                                            <span v-if="d.berkas" class="plw-doc__ext">{{ (d.berkas.ext || '').toUpperCase() }}</span>
                                        </component>
                                    </div>
                                    </template>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </template>

            <!-- FOOTER AKSI — menempel di kaki modal, tidak ikut menggulung.
                 Keputusan adalah alasan jendela ini dibuka; kalau tombolnya ikut
                 hanyut ke bawah, admin harus menggulung dulu setiap kali. Indikator
                 kuota ikut pindah supaya alasan tombol Loloskan hilang/mati tetap
                 terbaca di sebelah tombolnya. -->
            <template #footer>
                <div v-if="detailKandidat && detailKandidat.butuhKeputusan" class="plw-foot">
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
                    <!-- Daftarnya MENDATAR, bukan butir bertumpuk. Tiga aktivitas
                         yang belum tuntas dulu berarti tiga baris penuh di atas
                         tombol keputusan — kaki modal setinggi separuh layar, dan
                         tombol yang justru jadi alasan jendela ini dibuka terdorong
                         keluar pandangan. Sebagai chip, tiga aktivitas muat dalam
                         satu baris; nama dan sebabnya tetap disebut lengkap. -->
                    <!-- BISA DITUTUP. Isinya penjelasan, bukan peringatan yang
                         menuntut tindakan — dan sesudah dibaca sekali ia cuma
                         memakan sepertiga kaki modal. Yang ditutup TIDAK hilang
                         artinya: tombol yang terkunci tetap membawa alasannya di
                         tooltip, dan lencana kecil di baris tombol mengembalikan
                         keterangan ini kapan pun. -->
                    <div v-else-if="belumTuntas.length && !notaTutup" class="plw-kuota is-hadir">
                        <i class="bi bi-hourglass-split"></i>
                        <div class="plw-kuota__isi">
                            <b>Tahap ini belum tuntas</b>
                            <div class="plw-kuota__chips">
                                <span v-for="(x, i) in belumTuntas" :key="i" class="plw-kuota__chip" :title="`${x.label} — ${x.sebab}`">
                                    <b>{{ x.label }}</b><em>{{ x.sebab }}</em>
                                </span>
                            </div>
                            <span>Selama itu, <b>Loloskan</b> dan <b>Talent Pool</b> terkunci. <b>Tidak Lolos</b>, <b>Tahan Dulu</b>, dan keputusan dari kandidat tetap bisa dipakai.</span>
                        </div>
                        <button type="button" class="plw-kuota__x" title="Tutup keterangan" @click="tutupNota(true)">
                            <i class="bi bi-x-lg"></i>
                        </button>
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
                    <!-- TOMBOL MATI HARUS MENJELASKAN DIRINYA.
                         Deretan tombol kelabu tanpa keterangan terbaca sebagai
                         aplikasi rusak, bukan sebagai batas wewenang — dan
                         tooltip saja tidak terbaca di layar sentuh. -->
                    <div v-if="!bolehPutus" class="plw-nogate">
                        <i class="bi bi-shield-lock-fill"></i>
                        <div>
                            <b>Anda tidak berwenang mengetuk keputusan.</b>
                            Semua keputusan — Loloskan, Tidak Lolos, Talent Pool, sampai Mengundurkan Diri —
                            menuntut izin <b>APPROVE</b> pada menu <b>Worklist Pelamar</b>.
                            Menandai kehadiran, mencatat hasil, menahan, dan menjadwalkan tetap bisa Anda lakukan.
                            <span>Minta penambahan izinnya di <b>Manajemen Hak Akses</b>.</span>
                        </div>
                    </div>

                    <!-- MASTER KOSONG BUKAN "TIDAK BERWENANG", DAN BUKAN PULA DIAM.
                         Seluruh tombol di bawah lahir dari Master Hasil Keputusan.
                         Ketika masternya belum terisi di sebuah lingkungan, deretan
                         tombolnya cuma lenyap tanpa sepatah kata — yang tertinggal
                         hanya "Tahan Dulu", dan admin membaca itu sebagai aplikasi
                         rusak, bukan sebagai data yang belum disiapkan. Satu kalimat
                         di sini memangkas penelusuran dari berjam-jam jadi sedetik. -->
                    <div v-else-if="!hasilKeputusan.length" class="plw-nomaster">
                        <i class="bi bi-database-exclamation"></i>
                        <div>
                            <b>Master Hasil Keputusan masih kosong di lingkungan ini.</b>
                            Tombol <b>Loloskan</b>, <b>Tidak Lolos</b>, dan <b>Talent Pool</b> seluruhnya dibangun
                            dari master tersebut — selama tabelnya belum terisi, tidak ada satu pun keputusan
                            yang bisa diketuk. Menahan dan menjadwalkan tetap berjalan.
                            <span>Jalankan seed <b>N_WEB_CAREERS_Master_Hasil_Keputusan</b> pada basis data lingkungan ini.</span>
                        </div>
                    </div>

                    <!-- SATU BARIS untuk seluruh keputusan tim, TAHAN DULU ikut di
                         dalamnya — seperti di desain. Bentuk lamanya menaruh Tahan
                         Dulu di barisnya sendiri selebar penuh: satu tombol netral
                         memakan tinggi yang sama dengan tiga tombol keputusan, dan
                         kaki modal tumbuh sampai sepertiga layar. Menahan memang
                         keadaan ketiga di samping "putuskan" dan "biarkan
                         menggantung", tapi ia sejajar dengan keputusan lain —
                         bukan lebih besar dari semuanya. -->
                    <div class="plw-actions" :class="{ 'is-padat': putusanPerusahaan.length + (detailKandidat.hold ? 0 : 1) > 3 }">
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
                        <!-- TANPA SYARAT. Alasan menahan justru paling sering muncul
                             sebelum segala sesuatunya lengkap — kuota belum turun,
                             user department belum menjawab. Menuntut kehadiran atau
                             hasil tes lebih dulu berarti menutup jalan keluar tepat
                             saat ia paling dibutuhkan. -->
                        <button
                            v-if="!detailKandidat.hold"
                            type="button" class="plw-btn-putus is-hold"
                            title="Tunda keputusan tanpa memindahkan kandidat. Tidak ada email atau WA yang dikirim."
                            @click="askHold(true)"
                        >
                            <i class="bi bi-pause-circle"></i> Tahan Dulu
                        </button>
                    </div>

                    <!-- KEPUTUSAN DARI KANDIDAT — dipisah, bukan disamakan dengan
                         penilaian tim. Kandidat yang menolak penawaran atau mundur
                         BUKAN kandidat yang gagal seleksi; mencatatnya sebagai
                         "Tidak Lolos" membuat laporan berbunyi "gagal di tahap
                         penawaran" untuk orang yang justru lolos lalu memilih pergi,
                         dan itu menuntun ke perbaikan yang salah sasaran.

                         Labelnya SEBARIS dengan tombolnya, bukan judul di atasnya:
                         satu baris keterangan setinggi 18px yang cuma menamai satu
                         tombol di bawahnya adalah tinggi yang dibayar tanpa imbalan. -->
                    <div v-if="putusanKandidat.length" class="plw-actions2">
                        <span class="plw-actions2__lbl" :title="detailKandidat.tanggapan ? 'Kandidat sudah menjawab lewat portal' : ''">
                            <i class="bi bi-person-lines-fill"></i>
                            Dari kandidat
                            <em v-if="detailKandidat.tanggapan">· sudah menjawab</em>
                        </span>
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
                        <!-- Mengembalikan keterangan yang tadi ditutup. Tidak pernah
                             ada jalan buntu: yang disembunyikan tetap bisa dipanggil
                             dari tempat keputusannya diambil. -->
                        <button
                            v-if="belumTuntas.length && notaTutup"
                            type="button" class="plw-actions2__info"
                            title="Tampilkan lagi keterangan kenapa sebagian tombol terkunci"
                            @click="tutupNota(false)"
                        >
                            <i class="bi bi-info-circle"></i>
                        </button>
                    </div>
                    <!-- Tanpa baris keputusan kandidat, lencana pemanggil keterangan
                         tetap perlu tempat. -->
                    <button
                        v-else-if="belumTuntas.length && notaTutup"
                        type="button" class="plw-actions2__info is-solo"
                        title="Tampilkan lagi keterangan kenapa sebagian tombol terkunci"
                        @click="tutupNota(false)"
                    >
                        <i class="bi bi-info-circle"></i> Kenapa sebagian tombol terkunci?
                    </button>
                </div>
                <!-- Kandidat yang tahapnya sudah diputus (lulus, gugur, mundur)
                     tidak punya satu pun tombol keputusan — jendelanya murni
                     arsip. Tanpa tombol tutup di kaki, satu-satunya jalan keluar
                     adalah tombol X di pojok, dan itu jauh dari tempat mata
                     berhenti membaca. -->
                <button v-else type="button" class="wca-btn wca-btn--ghost" @click="tutupKandidat">
                    <i class="bi bi-x-lg"></i> Tutup
                </button>
            </template>
        </AdminModal>

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
                    <!-- DAFTARNYA DISARING MENURUT PERUNTUKAN aktivitas ini: MCU
                         hanya menampilkan rumah sakit, wawancara hanya kantor.
                         Labelnya pun dari master — jendela ini tidak tahu bahwa
                         "MEDIS" berarti rumah sakit, ia cuma membaca teksnya. -->
                    <label class="plw-fld__lbl" style="margin-top: 12px">
                        {{ peruntukanJadwal?.nama || 'Lokasi' }} <b>*</b>
                    </label>
                    <el-select
                        v-model="jadwalLokasiId"
                        filterable
                        :placeholder="peruntukanJadwal?.labelPilih || 'Pilih kantor / klinik'"
                        style="width: 100%; margin-top: 6px"
                        :loading="lokasiLoading"
                    >
                        <el-option
                            v-for="l in lokasiUntukJadwal"
                            :key="l.id"
                            :value="l.id"
                            :label="l.nama + (l.kota ? ' — ' + l.kota : '')"
                        >
                            <div class="plw-lok__opt">
                                <b>{{ l.nama }}</b>
                                <small>{{ [l.kategori, l.kota].filter(Boolean).join(' · ') || l.alamat }}</small>
                            </div>
                        </el-option>
                        <!-- TEMPAT DADAKAN — RS yang belum terdaftar. Tanpa ini,
                             nama RS terpaksa dititipkan di kotak patokan, dan di
                             sana ia bukan alamat, bukan peta, dan tak terhitung. -->
                        <el-option v-if="bolehLokasiLain" :value="LOKASI_LAIN" :label="peruntukanJadwal?.labelLainnya || 'Lainnya'">
                            <div class="plw-lok__opt">
                                <b>{{ peruntukanJadwal?.labelLainnya || 'Lainnya' }}</b>
                                <small>Isi sendiri nama &amp; alamatnya</small>
                            </div>
                        </el-option>
                    </el-select>

                    <!-- Daftar kosong disebutkan APA ADANYA. Dropdown kosong tanpa
                         keterangan terbaca sebagai layar yang rusak, dan rekruter
                         menutupnya alih-alih memakai "Lainnya". -->
                    <p v-if="!lokasiLoading && !lokasiUntukJadwal.length" class="plw-note" style="margin-top: 8px">
                        <i class="bi bi-info-circle"></i>
                        <span>{{ peruntukanJadwal?.labelKosong || 'Belum ada lokasi terdaftar untuk aktivitas ini.' }}</span>
                    </p>

                    <!-- Isian tempat di luar daftar. Alamatnya WAJIB (dari master):
                         undangan tanpa alamat membuat kandidat tidak tahu harus
                         datang ke mana, dan itu baru ketahuan di hari-H. -->
                    <template v-if="jadwalLokasiId === LOKASI_LAIN">
                        <div class="plw-fld">
                            <label class="plw-fld__lbl" for="jdw-lok-nama">
                                {{ peruntukanJadwal?.labelNamaLainnya || 'Nama tempat' }} <b>*</b>
                            </label>
                            <input id="jdw-lok-nama" v-model="jadwalLokasiNama" type="text" class="plw-inp" placeholder="mis. RS Siti Khadijah" maxlength="200" />
                        </div>
                        <div class="plw-fld">
                            <label class="plw-fld__lbl" for="jdw-lok-alamat">
                                {{ peruntukanJadwal?.labelAlamatLainnya || 'Alamat' }}
                                <b v-if="peruntukanJadwal?.wajibAlamatLainnya">*</b>
                                <small v-else>opsional</small>
                            </label>
                            <input id="jdw-lok-alamat" v-model="jadwalLokasiAlamat" type="text" class="plw-inp" placeholder="mis. Jl. Demang Lebar Daun No. 7, Palembang" maxlength="500" />
                        </div>
                    </template>

                    <!-- Pratinjau peta: rekruter memastikan titiknya benar SEBELUM
                         undangan terkirim, bukan setelah kandidat tersesat.
                         BERLAKU JUGA untuk tempat yang diketik sendiri — petanya
                         disusun dari nama + alamatnya, jadi tidak ada undangan
                         berperingkat dua hanya karena tempatnya belum terdaftar. -->
                    <div v-if="lokasiTerpilih" class="plw-lok" :class="{ 'is-teks': lokasiTerpilih.lepas }">
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
                                <b>
                                    {{ lokasiTerpilih.nama }}
                                    <!-- Disebut apa adanya: tempat ini tidak ada di master,
                                         jadi petanya hasil pencarian nama + alamat — bukan
                                         titik yang pernah diverifikasi seseorang. -->
                                    <span v-if="lokasiTerpilih.lepas" class="plw-lok__tag">tanpa peta</span>
                                </b>
                                <small v-if="lokasiTerpilih.alamat">{{ lokasiTerpilih.alamat }}</small>
                                <small v-if="lokasiTerpilih.lepas">Alamat ini dikirim apa adanya ke kandidat. Daftarkan di Master Lokasi bila tempatnya sering dipakai — di sana petanya ikut.</small>
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

            <!-- Penyunting yang sama dengan cabang "hadir" di modal ini.
                 Dulu satu cabang memakai penyunting dan cabang sebelahnya
                 textarea polos — dua catatan yang berakhir di kolom yang sama,
                 lahir dengan dua wajah berbeda tergantung tombol mana yang
                 ditekan sepuluh detik sebelumnya. -->
            <div v-else class="plw-fld">
                <label class="plw-fld__lbl">Alasan <small>opsional</small></label>
                <EditorQuill
                    v-model="hadirCatatanHtml"
                    ringkas
                    placeholder="Boleh dikosongkan — mis. sakit, minta jadwal ulang"
                />
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
            confirm-label="Buat & Unduh"
            :busy="sibuk || laporanMuat"
            form-mode
            @confirm="konfirmLaporan"
            @cancel="laporanShow = false"
        >
            <div class="plw-fld">
                <label class="plw-fld__lbl">Format berkas <b>*</b></label>
                <div class="plw-opts">
                    <!-- SATU FORMAT, DAN KARTUNYA TETAP DITAMPILKAN.
                         Bukan lagi pilihan, melainkan pemberitahuan: admin tahu
                         persis apa yang akan keluar sebelum menekan "Buat".
                         Menghilangkannya sama sekali membuat modal ini langsung
                         membuka daftar formulir tanpa pernah menyebut berkas apa
                         yang sedang dibuatnya. -->
                    <button type="button" class="plw-opt-card is-fmt" :class="{ 'is-on': laporanFormat === 'PDF' }" @click="laporanFormat = 'PDF'">
                        <span class="plw-opt-card__dot" style="color:#dc2626"><i class="bi bi-file-earmark-pdf-fill"></i></span>
                        <span class="plw-opt-card__txt">
                            <b>PDF — untuk dibaca &amp; diarsip</b>
                            <small>Profil kandidat berkop EVO Group: identitas, perjalanan seleksi, dan jawaban formulir. Siap dicetak.</small>
                        </span>
                        <span class="plw-opt-card__cek"><i class="bi bi-check-lg"></i></span>
                    </button>
                    <!-- EXCEL DINONAKTIFKAN DARI PILIHAN — BUKAN DIHAPUS.
                         Job-nya (WcLaporanKandidatJob format 'XLSX' → buatExcel)
                         masih utuh dan masih dipanggil untuk berkas lama di panel
                         unduhan, jadi yang ditutup hanya pintunya. Kembalikan
                         dengan membuka komentar ini; tidak ada yang lain yang
                         perlu disentuh.
                    <button type="button" class="plw-opt-card is-fmt" :class="{ 'is-on': laporanFormat === 'XLSX' }" @click="laporanFormat = 'XLSX'">
                        <span class="plw-opt-card__dot" style="color:#15803d"><i class="bi bi-file-earmark-spreadsheet-fill"></i></span>
                        <span class="plw-opt-card__txt">
                            <b>Excel — untuk diolah</b>
                            <small>Tiga lembar bersaringan: profil, perjalanan per aktivitas, dan jawaban formulir. Siap dipivot.</small>
                        </span>
                        <span class="plw-opt-card__cek"><i class="bi bi-check-lg"></i></span>
                    </button>
                    -->
                </div>
            </div>

            <!-- DAFTAR FORMULIR MASIH DIAMBIL.
                 Rangkanya ditampilkan, bukan ruang kosong: yang membuka modal
                 ini harus tahu bahwa masih ADA yang akan muncul di bawah pilihan
                 format — kalau tidak, ia menekan "Buat & Unduh" mengira sudah
                 tidak ada yang perlu dipilih. -->
            <div v-if="laporanMuat" class="plw-fld">
                <label class="plw-fld__lbl">Formulir yang disertakan</label>
                <div class="plw-load" style="justify-content: flex-start; padding: 0.35rem 0 0.6rem">
                    <span class="plw-spin"></span> Memuat daftar formulir…
                </div>
                <div class="plw-rangka"></div>
                <div class="plw-rangka plw-rangka--pendek"></div>
            </div>

            <div v-else-if="laporanOpsi.length > 1" class="plw-fld">
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
            <p v-else-if="!laporanMuat && laporanOpsi.length === 1" class="plw-note is-info">
                <i class="bi bi-info-circle-fill"></i>
                <span>Kandidat ini punya satu formulir (<b>{{ laporanOpsi[0].label }}</b>) — langsung disertakan.</span>
            </p>

            <!-- TIDAK ADA LAGI TOMBOL "UNDUH" DI SINI.
                 Dulu: tekan "Buat Laporan" → tunggu di dalam modal → muncul
                 tautan → tekan lagi. Dua kali menekan untuk satu maksud, dan
                 modal yang harus dijaga tetap terbuka selama menunggu. Sekarang
                 modalnya menutup begitu permintaan terkirim, kemajuannya pindah
                 ke panel unduhan di pojok, dan berkasnya tersimpan sendiri
                 begitu siap. -->
            <p v-if="laporanGagal" class="plw-note is-err">
                <i class="bi bi-exclamation-circle-fill"></i>
                <span>{{ laporanGagal }}</span>
            </p>
        </ConfirmModal>

        <!-- ══ PANEL UNDUHAN — pojok kanan bawah ══════════════════════════
             Membuat laporan berjalan di antrean dan bisa memakan puluhan
             detik. Menahan admin di dalam modal selama itu berarti ia tidak
             bisa mengerjakan apa pun; menutup modal tanpa jejak berarti ia
             tidak tahu permintaannya masih hidup. Panel ini menjawab keduanya:
             pekerjaannya terlihat, halamannya tetap bisa dipakai. -->
        <transition name="plw-unduhan">
            <div v-if="unduhan.length" class="plw-unduhan">
                <div class="plw-unduhan__head">
                    <span>
                        <i class="bi bi-cloud-arrow-down-fill"></i>
                        {{ judulUnduhan }}
                    </span>
                    <button type="button" class="plw-unduhan__x" title="Tutup" @click="bersihkanUnduhan">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
                <div class="plw-unduhan__list">
                    <div v-for="u in unduhan" :key="u.id" class="plw-unduhan__row">
                        <!-- Cincin kemajuan: satu lingkaran mengatakan "berapa
                             lagi" tanpa perlu dibaca. Saat selesai ia berganti
                             ceklis, bukan hilang — hilang membuat orang ragu
                             berkasnya benar-benar turun. -->
                        <span class="plw-ring" :class="'is-' + u.keadaan">
                            <svg viewBox="0 0 36 36" width="34" height="34">
                                <circle class="plw-ring__bg" cx="18" cy="18" r="15.5" />
                                <circle
                                    class="plw-ring__val" cx="18" cy="18" r="15.5"
                                    :stroke-dasharray="busur(u.persen)"
                                />
                            </svg>
                            <i v-if="u.keadaan === 'selesai'" class="bi bi-check-lg"></i>
                            <i v-else-if="u.keadaan === 'gagal'" class="bi bi-exclamation-lg"></i>
                            <b v-else>{{ bulat(u.persen) }}</b>
                        </span>
                        <span class="plw-unduhan__txt">
                            <b :title="u.nama">{{ u.nama }}</b>
                            <small :class="{ 'is-err': u.keadaan === 'gagal' }">{{ u.pesan }}</small>
                        </span>
                        <i class="bi plw-unduhan__ikon" :class="u.format === 'XLSX' ? 'bi-file-earmark-spreadsheet-fill is-xls' : 'bi-file-earmark-pdf-fill is-pdf'"></i>
                    </div>
                </div>
            </div>
        </transition>

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
                                <small>Untuk FGD, tes tertulis massal, atau briefing bersama. Semua {{ sasaranMassal.length || 'kandidat' }} dapat jam yang sama persis.</small>
                            </span>
                            <span class="plw-opt-card__cek"><i class="bi bi-check-lg"></i></span>
                        </button>
                        <button type="button" class="plw-opt-card" :class="{ 'is-on': massalPola === 'BERGILIR' }" @click="massalPola = 'BERGILIR'">
                            <span class="plw-opt-card__dot"><i class="bi bi-hourglass-split"></i></span>
                            <span class="plw-opt-card__txt">
                                <b>Bergilir — satu per satu</b>
                                <small>Untuk wawancara panel. Tiap kandidat dapat slotnya sendiri, berurutan dari jam mulai.</small>
                            </span>
                            <span class="plw-opt-card__cek"><i class="bi bi-check-lg"></i></span>
                        </button>
                    </div>
                </div>

                <!-- WAKTU MULAI MELEBAR PENUH, DUA ANGKA TURUN KE BAWAHNYA.
                     Sebelumnya ketiganya dipaksa satu baris: tanggal separuh,
                     durasi dan jeda seperempat masing-masing. Di lebar
                     seperempat, "Durasi per orang" membungkus dua baris dan
                     kotak angkanya menyisakan ruang untuk dua digit — padahal
                     nilainya boleh sampai 480. -->
                <div class="plw-fld">
                    <label class="plw-fld__lbl">{{ massalPola === 'BERGILIR' ? 'Slot pertama mulai' : 'Waktu mulai' }} <b>*</b></label>
                    <el-date-picker v-model="massalMulai" type="datetime" format="DD MMM YYYY HH:mm" value-format="YYYY-MM-DD HH:mm:ss" placeholder="Pilih tanggal & jam" style="width: 100%" />
                </div>
                <div v-if="massalPola === 'BERGILIR'" class="plw-fld__row">
                    <div class="plw-fld">
                        <!-- SATUANNYA DISEBUT DI LABEL. Kotak angka tanpa satuan
                             membuat "30" terbaca sebagai apa saja — menit, orang,
                             atau nomor urut. -->
                        <label class="plw-fld__lbl">Durasi per orang <small>menit</small></label>
                        <el-input-number v-model="massalDurasi" :min="5" :max="480" :step="5" controls-position="right" style="width: 100%" />
                    </div>
                    <div class="plw-fld">
                        <!-- step 1, bukan 5: jeda yang wajar 0–10 menit, dan panah
                             yang melompat lima-lima memaksa admin mengetik manual
                             untuk angka yang paling sering dipakai. -->
                        <label class="plw-fld__lbl">Jeda antar sesi <small>menit</small></label>
                        <el-input-number v-model="massalJeda" :min="0" :max="240" :step="1" controls-position="right" style="width: 100%" />
                    </div>
                </div>

                <!-- ARITMATIKANYA DITULISKAN, BUKAN DISIMPULKAN SENDIRI.
                     Dua kotak angka bersebelahan tidak memberi tahu bahwa slot
                     bergeser sebesar JUMLAH keduanya. Yang membacanya mengira
                     jeda 2 menit berarti orang kedua masuk dua menit kemudian,
                     lalu mengirim undangan dengan jam yang tidak ia maksud —
                     dan undangan yang sudah terkirim tak bisa ditarik. -->
                <p v-if="massalPola === 'BERGILIR'" class="plw-note is-info">
                    <i class="bi bi-calculator-fill"></i>
                    <span>
                        Tiap orang <b>{{ massalDurasi }} menit</b><template v-if="massalJeda > 0">, lalu jeda <b>{{ massalJeda }} menit</b></template> —
                        jadi slotnya bergeser <b>tiap {{ Number(massalDurasi || 0) + Number(massalJeda || 0) }} menit</b>.
                        <template v-if="ritmeMassal.length">
                            Dari jam mulai: <b>{{ ritmeMassal.join(' → ') }}</b><template v-if="sasaranMassal.length > ritmeMassal.length"> → dan seterusnya</template>.
                        </template>
                        <template v-else>Pilih tanggal &amp; jam untuk melihat urutannya.</template>
                    </span>
                </p>

                <div v-if="massalMode === 'DARING'" class="plw-fld">
                    <label class="plw-fld__lbl">Tautan pertemuan <b>*</b></label>
                    <input v-model="massalLink" type="url" class="plw-inp" placeholder="https://meet.google.com/..." maxlength="500" />
                </div>
                <template v-else>
                    <!-- Disaring menurut peruntukan aktivitas yang dipilih, sama
                         seperti jendela satuan. Tanpa ini, menjadwalkan MCU
                         massal bisa mengirim seratus orang ke kantor sekaligus. -->
                    <label class="plw-fld__lbl" style="margin-top: 12px">
                        {{ peruntukanMassal?.nama || 'Lokasi' }} <b>*</b>
                    </label>
                    <el-select v-model="massalLokasiId" filterable :placeholder="peruntukanMassal?.labelPilih || 'Pilih kantor / klinik'" style="width: 100%; margin-top: 6px" :loading="lokasiLoading">
                        <el-option v-for="l in lokasiUntukMassal" :key="l.id" :value="l.id" :label="l.nama + (l.kota ? ' — ' + l.kota : '')" />
                        <el-option v-if="peruntukanMassal?.izinkanLainnya" :value="LOKASI_LAIN" :label="peruntukanMassal?.labelLainnya || 'Lainnya'" />
                    </el-select>
                    <p v-if="!lokasiLoading && !lokasiUntukMassal.length" class="plw-note" style="margin-top: 8px">
                        <i class="bi bi-info-circle"></i>
                        <span>{{ peruntukanMassal?.labelKosong || 'Belum ada lokasi terdaftar untuk aktivitas ini.' }}</span>
                    </p>
                    <template v-if="massalLokasiId === LOKASI_LAIN">
                        <div class="plw-fld">
                            <label class="plw-fld__lbl">{{ peruntukanMassal?.labelNamaLainnya || 'Nama tempat' }} <b>*</b></label>
                            <input v-model="massalLokasiNama" type="text" class="plw-inp" placeholder="mis. RS Siti Khadijah" maxlength="200" />
                        </div>
                        <div class="plw-fld">
                            <label class="plw-fld__lbl">
                                {{ peruntukanMassal?.labelAlamatLainnya || 'Alamat' }}
                                <b v-if="peruntukanMassal?.wajibAlamatLainnya">*</b>
                                <small v-else>opsional</small>
                            </label>
                            <input v-model="massalLokasiAlamat" type="text" class="plw-inp" placeholder="mis. Jl. Demang Lebar Daun No. 7, Palembang" maxlength="500" />
                        </div>
                    </template>
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

        <!-- ═══ TAHAN / LANJUTKAN BANYAK SEKALIGUS ═══
             Dua mode, dan keduanya benar-benar dibutuhkan:

             SERAGAM  — satu peristiwa mengenai semua ("MPP belum turun").
                        Mengetik alasan yang sama dua belas kali bukan
                        ketelitian, cuma pekerjaan yang terbuang.
             SENDIRI  — satu angkatan bisa tertahan karena sebab berbeda-beda:
                        tiga menunggu kuota, dua menunggu user department, satu
                        menunggu kandidatnya menjawab. Memaksakan satu alasan
                        membuat lima dari enam catatan itu SALAH, dan justru
                        laporan "kenapa lowongan ini lama terisi" yang rusak. -->
        <ConfirmModal
            :show="hmShow"
            :danger="false"
            :icon="hmNilai ? 'bi-pause-circle-fill' : 'bi-play-circle-fill'"
            :title="hmNilai ? 'Tahan Beberapa Kandidat' : 'Lanjutkan Beberapa Kandidat'"
            :subtitle="`${hmTarget.length} kandidat terpilih`"
            :confirm-label="hmNilai ? `Ya, Tahan ${hmTarget.length} Kandidat` : `Ya, Lanjutkan ${hmTarget.length} Kandidat`"
            :busy="sibuk"
            :confirm-disabled="!bolehSimpanHoldMassal"
            :confirm-icon="hmNilai ? 'bi-pause-circle' : 'bi-play-circle'"
            form-mode
            size="xl"
            @confirm="konfirmHoldMassal"
            @cancel="hmShow = false"
        >
            <div class="plw-putus__ring" :class="hmNilai ? 'is-talent_pool' : 'is-lulus'">
                <div class="plw-putus__row">
                    <i class="bi" :class="hmNilai ? 'bi-pause-circle' : 'bi-play-circle'"></i>
                    <span v-if="hmNilai">
                        Semua kandidat ini <b>tetap di tahapnya</b> — tidak dipindah, tidak digugurkan.
                        Yang tertunda hanya keputusannya.
                    </span>
                    <span v-else>
                        Penahanan dilepas. Yang hasil tesnya sudah lengkap selama ditahan akan
                        langsung disimpulkan sistem begitu Anda menekan Lanjutkan.
                    </span>
                </div>
                <div class="plw-putus__row">
                    <i class="bi bi-envelope-slash"></i>
                    <span><b>Tidak ada email atau WA</b> yang dikirim — ini catatan internal.</span>
                </div>
            </div>

            <!-- Yang TIDAK bisa diproses disebutkan sebelum disimpan, bukan
                 dilaporkan sesudahnya. Admin berhak tahu bahwa dari 12 yang ia
                 centang, hanya 9 yang akan benar-benar tersentuh. -->
            <p v-if="hmDilewati.length" class="plw-note is-err" style="margin-bottom: 10px">
                <i class="bi bi-exclamation-circle-fill"></i>
                <span>
                    <b>{{ hmDilewati.length }}</b> kandidat terpilih dilewati —
                    {{ hmNilai ? 'sudah ditahan atau tahapnya sudah diputus' : 'memang tidak sedang ditahan' }}:
                    {{ hmDilewati.map((x) => x.pelamar).join(', ') }}.
                </span>
            </p>

            <template v-if="hmNilai">
                <div class="plw-fld">
                    <label class="plw-fld__lbl">Cara mengisi alasan</label>
                    <div class="plw-opts plw-opts--row">
                        <button
                            type="button" class="plw-opt-card" :class="{ 'is-on': hmPola === 'SERAGAM' }"
                            @click="hmPola = 'SERAGAM'"
                        >
                            <span class="plw-opt-card__dot"><i class="bi bi-collection-fill"></i></span>
                            <span class="plw-opt-card__txt">
                                <b>Sama untuk semua</b>
                                <small>Satu alasan &amp; satu keterangan dipakai {{ hmTarget.length }} kandidat.</small>
                            </span>
                            <span class="plw-opt-card__cek"><i class="bi bi-check-lg"></i></span>
                        </button>
                        <button
                            type="button" class="plw-opt-card" :class="{ 'is-on': hmPola === 'SENDIRI' }"
                            @click="hmPola = 'SENDIRI'"
                        >
                            <span class="plw-opt-card__dot"><i class="bi bi-person-lines-fill"></i></span>
                            <span class="plw-opt-card__txt">
                                <b>Beda per kandidat</b>
                                <small>Tiap orang punya alasan &amp; keterangannya sendiri.</small>
                            </span>
                            <span class="plw-opt-card__cek"><i class="bi bi-check-lg"></i></span>
                        </button>
                    </div>
                </div>

                <!-- ── SERAGAM ── -->
                <template v-if="hmPola === 'SERAGAM'">
                    <div class="plw-fld">
                        <label class="plw-fld__lbl">Alasan menahan <b>*</b></label>
                        <div class="plw-opts">
                            <button
                                v-for="a in alasanHold" :key="a.value"
                                type="button" class="plw-opt-card"
                                :class="{ 'is-on': hmAlasan === a.value }"
                                :style="hmAlasan === a.value ? { borderColor: a.warna, background: a.warna + '14' } : null"
                                @click="hmAlasan = a.value"
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
                            <b v-if="hmButuhCatatan">*</b>
                            <small v-else>opsional</small>
                        </label>
                        <EditorQuill
                            v-model="hmCatatanHtml"
                            ringkas
                            placeholder="mis. Menunggu approval MPP dari Direktur — target minggu depan."
                            hint="Tersimpan sebagai riwayat penahanan pada tahap SETIAP kandidat terpilih."
                        />
                    </div>
                    <p v-if="hmButuhCatatan && !hmCatatanCukup" class="plw-note is-err">
                        <i class="bi bi-exclamation-circle-fill"></i>
                        <span>Alasan ini menuntut keterangan tambahan — tanpa itu, ia tidak menjelaskan apa pun saat ditinjau kembali.</span>
                    </p>
                </template>

                <!-- ── SENDIRI-SENDIRI ── -->
                <template v-else>
                    <!-- Jalan pintas: isi baris pertama, lalu tebarkan. Mode ini
                         paling sering dipakai untuk "sebagian besar sama, dua
                         orang berbeda" — memaksa mengetik ulang semuanya
                         membuat orang kembali memilih mode seragam yang keliru. -->
                    <div class="plw-hmbar">
                        <i class="bi bi-magic"></i>
                        <span>Isi baris pertama, lalu salin ke sisanya — yang perlu beda tinggal disunting.</span>
                        <button type="button" :disabled="!hmBaris[0]?.alasan" @click="sebarBarisPertama">
                            <i class="bi bi-arrow-down-up"></i> Salin ke semua
                        </button>
                    </div>

                    <div class="plw-hmlist">
                        <div v-for="(b, i) in hmBaris" :key="b.id" class="plw-hmrow" :class="{ 'is-kurang': barisHmKurang(b) }">
                            <div class="plw-hmrow__head">
                                <span class="plw-hmrow__n">{{ i + 1 }}</span>
                                <div style="flex: 1; min-width: 0">
                                    <b>{{ b.pelamar }}</b>
                                    <small>{{ b.posisi || '—' }} · tahap {{ b.tahap }}</small>
                                </div>
                                <span v-if="barisHmKurang(b)" class="plw-hmrow__warn">
                                    <i class="bi bi-exclamation-circle-fill"></i> {{ barisHmKurang(b) }}
                                </span>
                            </div>
                            <div class="plw-hmrow__isi">
                                <el-select
                                    v-model="b.alasan" filterable placeholder="Pilih alasan menahan"
                                    class="plw-hmrow__sel"
                                >
                                    <el-option
                                        v-for="a in alasanHold" :key="a.value"
                                        :value="a.value" :label="a.nama"
                                    />
                                </el-select>
                                <EditorQuill
                                    v-model="b.catatanHtml"
                                    ringkas mungil
                                    :placeholder="alasanButuhCatatan(b.alasan) ? 'Keterangan WAJIB untuk alasan ini…' : 'Keterangan (opsional)…'"
                                />
                            </div>
                        </div>
                    </div>
                </template>
            </template>

            <div v-else class="plw-fld">
                <label class="plw-fld__lbl">Catatan pelepasan <small>opsional, dipakai untuk semua</small></label>
                <EditorQuill
                    v-model="hmCatatanHtml"
                    ringkas
                    placeholder="mis. Kuota sudah turun, proses dilanjutkan."
                />
            </div>
        </ConfirmModal>

        <!-- ══ KEPUTUSAN MASSAL (LOLOS / TIDAK LOLOS) ══
             form-mode + danger=false + size: ketiganya wajib di sini.

             Tanpa `form-mode`, ConfirmModal memakai bentuk KALIMAT konfirmasi:
             isinya dibungkus <p> dan dirata-tengahkan. Borang ini berisi <div>,
             dan <div> di dalam <p> ditutup paksa oleh peramban — tata letaknya
             lepas dari kendali CSS, persis seperti yang terjadi pada mode
             "Beda per kandidat". Rata tengahnya juga menghapus garis baca
             borang: label, isian, dan bantuan tak lagi berbaris di kiri.

             Tanpa `danger=false`, kepala modal menampilkan tong sampah merah
             dan tombolnya pun bertong sampah — untuk tindakan yang sama sekali
             tidak menghapus apa pun. Ikonnya jadi janji yang keliru.

             Tanpa `size`, borang selebar konfirmasi memaksa dua kartu pilihan
             menumpuk dan tiap baris kandidat memanjang ke bawah. -->
        <ConfirmModal
            :show="pmShow"
            :busy="sibuk"
            :danger="false"
            size="xl"
            form-mode
            icon="bi-hammer"
            confirm-icon="bi-hammer"
            title="Keputusan untuk Beberapa Kandidat"
            :subtitle="`${pmTarget.length} kandidat terpilih`"
            :confirm-label="`Ya, Putuskan ${pmTarget.length} Kandidat`"
            :confirm-disabled="!bolehSimpanPutusMassal"
            @confirm="konfirmPutusMassal"
            @cancel="pmShow = false"
        >
            <div class="plw-putus__ring is-lulus">
                <div class="plw-putus__row">
                    <i class="bi bi-hammer"></i>
                    <span>Keputusan ini <b>menutup tahap</b> bagi setiap kandidat terpilih dan memindahkannya sesuai hasilnya.</span>
                </div>
                <div class="plw-putus__row">
                    <i class="bi bi-envelope-fill"></i>
                    <span>Email hasil dikirim <b>sesuai setelan masternya</b> — sama seperti keputusan satuan.</span>
                </div>
            </div>

            <!-- Yang TIDAK bisa diproses disebut SEBELUM disimpan. Admin berhak
                 tahu bahwa dari 12 yang ia centang, hanya 9 yang tersentuh. -->
            <p v-if="pmDilewati.length" class="plw-note is-err" style="margin-bottom: 10px">
                <i class="bi bi-exclamation-circle-fill"></i>
                <span>
                    <b>{{ pmDilewati.length }}</b> kandidat terpilih dilewati — sedang ditahan
                    atau tahapnya sudah diputus:
                    {{ pmDilewati.map((x) => x.pelamar).join(', ') }}.
                </span>
            </p>

            <div class="plw-fld">
                <label class="plw-fld__lbl">Cara mengambil keputusan</label>
                <div class="plw-opts plw-opts--row">
                    <button
                        type="button" class="plw-opt-card" :class="{ 'is-on': pmPola === 'SERAGAM' }"
                        @click="pmPola = 'SERAGAM'"
                    >
                        <span class="plw-opt-card__dot"><i class="bi bi-collection-fill"></i></span>
                        <span class="plw-opt-card__txt">
                            <b>Sama untuk semua</b>
                            <small>Satu keputusan &amp; satu alasan dipakai {{ pmTarget.length }} kandidat.</small>
                        </span>
                        <span class="plw-opt-card__cek"><i class="bi bi-check-lg"></i></span>
                    </button>
                    <button
                        type="button" class="plw-opt-card" :class="{ 'is-on': pmPola === 'SENDIRI' }"
                        @click="pmPola = 'SENDIRI'"
                    >
                        <span class="plw-opt-card__dot"><i class="bi bi-person-lines-fill"></i></span>
                        <span class="plw-opt-card__txt">
                            <b>Beda per kandidat</b>
                            <small>Sebagian lolos, sebagian tidak — dalam satu kali simpan.</small>
                        </span>
                        <span class="plw-opt-card__cek"><i class="bi bi-check-lg"></i></span>
                    </button>
                </div>
            </div>

            <template v-if="pmPola === 'SERAGAM'">
                <div class="plw-fld">
                    <label class="plw-fld__lbl">Keputusan <b>*</b></label>
                    <div class="plw-opts">
                        <button
                            v-for="h in pmHasilOpsi" :key="h.kode"
                            type="button" class="plw-opt-card"
                            :class="{ 'is-on': pmHasil === h.kode }"
                            :style="pmHasil === h.kode ? { borderColor: h.warna, background: h.warna + '14' } : null"
                            @click="pmHasil = h.kode"
                        >
                            <span class="plw-opt-card__dot" :style="{ color: h.warna }"><i class="bi" :class="h.ikon || 'bi-dot'"></i></span>
                            <span class="plw-opt-card__txt">
                                <b>{{ h.labelTombol || h.nama }}</b>
                                <small>{{ h.deskripsi }}</small>
                            </span>
                        </button>
                    </div>
                </div>

                <div class="plw-fld">
                    <label class="plw-fld__lbl">
                        Alasan / catatan
                        <b v-if="pmButuhCatatan">*</b>
                        <small v-else>opsional, dipakai untuk semua</small>
                    </label>
                    <EditorQuill
                        v-model="pmCatatanHtml"
                        ringkas
                        placeholder="mis. Hasil psikotes di bawah ambang batas yang ditetapkan panel."
                        hint="Tersimpan sebagai catatan keputusan pada tahap SETIAP kandidat terpilih."
                    />
                    <p v-if="pmButuhCatatan && !pmCatatanCukup" class="plw-note is-err">
                        <i class="bi bi-exclamation-circle-fill"></i>
                        <span>Keputusan ini menuntut alasan yang benar-benar ditulis.</span>
                    </p>
                </div>
            </template>

            <template v-else>
                <!-- `plw-hmbar`, bukan `plw-hmhint`: kelas kedua itu tidak pernah
                     punya satu baris CSS pun, jadi bilahnya tampil sebagai teks
                     telanjang dengan tombol menggantung di tengah. -->
                <div class="plw-hmbar">
                    <i class="bi bi-magic"></i>
                    <span>Isi baris pertama, lalu salin ke sisanya — yang perlu beda tinggal disunting.</span>
                    <button type="button" :disabled="!pmBaris[0]?.hasil" @click="sebarPutusBarisPertama">
                        <i class="bi bi-arrow-down-up"></i> Salin ke semua
                    </button>
                </div>

                <div class="plw-hmlist">
                    <div v-for="(b, i) in pmBaris" :key="b.id" class="plw-hmrow" :class="{ 'is-kurang': barisPmKurang(b) }">
                        <div class="plw-hmrow__head">
                            <span class="plw-hmrow__n">{{ i + 1 }}</span>
                            <div style="flex: 1; min-width: 0">
                                <b>{{ b.pelamar }}</b>
                                <small>{{ b.posisi || '—' }} · tahap {{ b.tahap }}</small>
                            </div>
                            <span v-if="barisPmKurang(b)" class="plw-hmrow__warn">
                                <i class="bi bi-exclamation-circle-fill"></i> {{ barisPmKurang(b) }}
                            </span>
                        </div>
                        <!-- Keputusan & alasannya berdampingan, tidak bertumpuk:
                             yang dibaca ulang berbulan-bulan kemudian adalah
                             pasangan keduanya, dan di lebar xl keduanya muat. -->
                        <div class="plw-hmrow__isi">
                            <el-select v-model="b.hasil" placeholder="Pilih keputusan" class="plw-hmrow__sel">
                                <el-option
                                    v-for="h in pmHasilOpsi" :key="h.kode"
                                    :value="h.kode" :label="h.labelTombol || h.nama"
                                />
                            </el-select>
                            <!-- Penyunting, bukan textarea. Catatan keputusan
                                 dibaca kembali di rapor tahap bersama catatan
                                 satuan yang memang ber-HTML; kalau yang massal
                                 lahir sebagai teks polos, dua catatan untuk hal
                                 yang sama tampil dengan dua wajah berbeda. -->
                            <EditorQuill
                                v-model="b.catatanHtml"
                                ringkas mungil
                                :placeholder="hasilKeputusan.find((h) => h.kode === b.hasil)?.butuhAlasan
                                    ? 'Alasan WAJIB untuk keputusan ini…'
                                    : 'Catatan (opsional)…'"
                            />
                        </div>
                    </div>
                </div>
            </template>
        </ConfirmModal>

        <transition name="plw-toast"><div v-if="toast" class="plw-toast" :class="{ 'is-err': toastErr, 'is-atas-unduhan': unduhan.length }"><i class="bi" :class="toastErr ? 'bi-exclamation-circle-fill' : 'bi-check-circle-fill'"></i> {{ toast }}</div></transition>
    </div>
</template>

<script>
import axios from 'axios';
import { Head, router } from '@inertiajs/vue3';
import AdminModal from '@career/AdminModal.vue';
import BerkasAktivitas from '@career/BerkasAktivitas.vue';
import ConfirmModal from '@career/ConfirmModal.vue';
import EditorQuill from '@career/EditorQuill.vue';
import KontenAman from '@career/KontenAman.vue';
import UraianLipat from '@career/UraianLipat.vue';
import { grupBagian, grupField, labelField, tipeField } from '@career/formulir';

/**
 * Peta label/tipe/grup per FORMULIR, bukan per kode komponen.
 *
 * Sumbernya kini skema yang dibekukan di tiap pengisian (lihat
 * formulirTerkirim()), jadi dua kandidat bisa memakai versi formulir yang
 * berbeda. WeakMap dikunci ke objek formulirnya: begitu profil kandidat lain
 * dimuat, entri lamanya ikut hilang sendiri tanpa perlu dibersihkan.
 */
const PETA_SKEMA = new WeakMap();

/** Cache kolom-uraian per array baris riwayat. Lihat kolomUraian(). */
const KOLOM_URAIAN = new WeakMap();
/** Cache kolom-periode per array baris riwayat. Lihat kolomPeriode(). */
const KOLOM_PERIODE = new WeakMap();
const EMPTY_SET = new Set();

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
    components: { Head, AdminModal, BerkasAktivitas, ConfirmModal, EditorQuill, KontenAman, UraianLipat },
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
        // HAK AKSES pengguna ini, dikirim shell untuk SEMUA halaman admin
        // (PropsShell: "dipakai Vue menyembunyikan tombol yang tidak
        // diizinkan"). Halaman ini tidak pernah membacanya — akibatnya tombol
        // keputusan tetap digambar untuk admin yang tidak berhak, dan
        // penolakannya baru datang setelah alasan diketik & modal dikirim.
        akses: { type: Object, default: () => ({ permissions: {}, konten: {} }) },
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
            // Bawaan SEMUA, bukan AKTIF. Papan yang menyembunyikan kandidat
            // yang sudah ditutup membuat orang mencarinya di tempat yang salah —
            // dan yang mundur di tahap akhir tampak seolah hilang begitu saja.
            statusTab: 'SEMUA',
            detailKandidat: null,
            // Tab modal detail: 'rapor' | 'berkas'. Selalu kembali ke
            // 'rapor' setiap kandidat dibuka — lihat bukaKandidat().
            tabAktif: 'rapor',
            profil: { lamaran: null, formulir: [] },
            loadingProfil: false,
            berkasHasil: [],
            tahapBerkasId: null,
            berkasBusy: false,
            openForm: 0,
            // Akordion riwayat yang SEDANG DITUTUP. Yang disimpan kondisi
            // tutupnya, bukan bukanya — dengan begitu riwayat yang baru muncul
            // (kandidat lain, formulir lain) terbuka sendiri tanpa perlu
            // didaftarkan lebih dulu.
            riwayatDitutup: {},
            // ── BERKAS: DUA CARA MEMBACA SATU ISI ───────────────────────────
            //
            // 'daftar' — akordion per formulir. BAWAAN, dan memang harus:
            //   yang paling sering ditanyakan peninjau bukan "berkas apa saja
            //   yang ada" melainkan "apa jawaban pertanyaan ini" — dan jawaban
            //   itu hanya punya arti bersama pertanyaannya.
            // 'berkas' — file manager: folder per formulir di kiri, petak berkas
            //   di kanan. Dipakai saat yang dicari memang lembarannya sendiri
            //   ("mana ijazahnya?"), yang di mode daftar menuntut membuka tiap
            //   akordion satu per satu.
            //
            // Pilihannya diingat per peramban: seorang verifikator berkas
            // memakai mode yang sama sepanjang hari.
            berkasMode: localStorage.getItem('plw.berkasMode') === 'berkas' ? 'berkas' : 'daftar',
            // Folder yang sedang dibuka di file manager: '' = semua, selain itu
            // nomor formulirnya.
            fmFolder: '',
            fmCari: '',
            // Berkas yang sedang disorot — memunculkan bilah pratinjau di kaki.
            fmSorot: '',
            // Keterangan "tahap ini belum tuntas" sedang ditutup?
            //
            // Disimpan PER KANDIDAT (kunci id lamaran), bukan sekali untuk
            // seluruh sesi: alasan terkuncinya berbeda-beda tiap orang, dan
            // menutupnya sekali lalu tak pernah melihatnya lagi berarti kandidat
            // berikutnya diputus tanpa tahu apa yang belum selesai.
            notaDitutup: {},
            // ── CETAK LAPORAN ───────────────────────────────────────────────
            laporanShow: false,
            laporanFormat: 'PDF',
            laporanOpsi: [],
            laporanFormulir: [],
            laporanGagal: '',
            // Daftar formulir diambil SESUDAH modal terbuka. Tanpa penanda ini
            // modalnya terbuka dengan ruang kosong di bawah pilihan format,
            // lalu daftar centangnya muncul tiba-tiba — dan yang sempat menekan
            // "Buat & Unduh" lebih dulu mencetak tanpa pilihan yang ia kira ada.
            laporanMuat: false,
            // Antrean unduhan yang sedang berjalan — ditampilkan di pojok kanan
            // bawah. Array, bukan satu objek: admin kerap mencetak beberapa
            // kandidat berturut-turut tanpa menunggu yang sebelumnya selesai.
            unduhan: [],
            // Pegangan gelung animasi progres — satu untuk seluruh baris.
            unduhanRaf: null,
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
            // Tempat di luar master (RS dadakan) — dua isian wajibnya.
            jadwalLokasiNama: '',
            jadwalLokasiAlamat: '',
            // Penanda pilihan "Lainnya". Nilainya SAMA PERSIS dengan konstanta
            // MasterLokasiController::LAINNYA di server; garis bawah membuatnya
            // mustahil bertabrakan dengan hashid mana pun.
            LOKASI_LAIN: '__LAINNYA__',
            daftarLokasi: [],
            // Master peruntukan (KANTOR / MEDIS / …) berikut seluruh labelnya.
            daftarPeruntukan: [],
            lokasiLoading: false,
            jadwalCatatan: '',
            hadirShow: false,
            hadirTarget: null,
            hadirNilai: 'Y',
            // Catatan jendela kehadiran — SATU untuk kedua cabang (hadir maupun
            // tidak), ditulis di editor berformat, boleh memuat gambar lembar
            // penilaian. Dulu cabang "tidak hadir" punya model teks polosnya
            // sendiri, dan alasan ketidakhadiran jadi satu-satunya catatan di
            // riwayat yang tampil tanpa format.
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

            /* ── TAHAN / LANJUTKAN MASSAL ── */
            hmShow: false,
            hmNilai: true,          // true = menahan, false = melepaskan
            hmPola: 'SERAGAM',      // SERAGAM = satu alasan untuk semua, SENDIRI = per kandidat
            hmTarget: [],           // baris kandidat yang BENAR-BENAR akan diproses
            hmDilewati: [],         // terpilih tapi tidak memenuhi syarat
            hmAlasan: '',
            hmCatatanHtml: '',
            hmBaris: [],            // [{ id, tahapId, pelamar, posisi, tahap, alasan, catatan }]
            // Daftar alasan dari Master Alasan Hold — dimuat sekali per sesi.
            alasanHold: [],

            /* ── KEPUTUSAN MASSAL (LOLOS / TIDAK LOLOS) ── */
            pmShow: false,
            pmPola: 'SERAGAM',      // SERAGAM = satu keputusan untuk semua, SENDIRI = per kandidat
            pmHasil: '',            // kode hasil pada mode SERAGAM
            pmCatatanHtml: '',      // catatan bersama pada mode SERAGAM
            pmTarget: [],           // baris yang BENAR-BENAR akan diputus
            pmDilewati: [],         // tercentang tapi tidak memenuhi syarat
            pmBaris: [],            // [{ id, tahapId, pelamar, posisi, tahap, hasil, catatan }]
            // Kandidat yang sedang ditahan/dilepas dari KARTU (bukan drawer).
            holdTarget: null,
            // ── MODE TAMPILAN ───────────────────────────────────────────────
            // Disimpan di perangkat, bukan di server: pilihan ini soal kebiasaan
            // membaca, dan admin yang bekerja dari daftar tidak ingin kembali ke
            // papan setiap kali halaman dimuat ulang.
            mode: localStorage.getItem('plw.mode') === 'list' ? 'list' : 'kanban',
            // ── PENYARING PAPAN ─────────────────────────────────────────────
            cariKandidat: '',
            // LARIK, bukan satu nilai: keempatnya multi-pilih. Larik kosong =
            // tidak menyaring apa pun, sama seperti '' dulu.
            keadaanPilih: [],
            tahapPilih: [],
            kampusPilih: [],
            // [mulai, selesai] dalam 'YYYY-MM-DD', atau null saat kosong —
            // bentuk yang dipakai el-date-picker bertipe daterange.
            rangeTanggal: null,
            /**
             * Panel penyaring sedang terbuka?
             *
             * Terbuka di layar lebar — di sana ia memang menempati ruang yang
             * tak dipakai apa pun, dan menyembunyikannya cuma menambah satu
             * klik ke pekerjaan yang paling sering dilakukan.
             *
             * Tertutup di ponsel: enam isian bertumpuk setinggi satu layar
             * penuh berarti papan kandidat — yang justru dibuka orang — baru
             * muncul setelah digulir melewati seluruh penyaring. Di sana
             * pintunya FAB (lihat .plw-fab).
             */
            panelBuka: typeof window !== 'undefined' ? window.innerWidth >= 768 : true,
            // Pintasan rentang: sekian hari terakhir sampai hari ini.
            pintasTanggal: [
                { hari: 7, label: '7 hari' },
                { hari: 30, label: '30 hari' },
                { hari: 90, label: '90 hari' },
            ],
            // ── PAGINASI MODE LIST ──────────────────────────────────────────
            listPage: 1,
            listPer: 20,
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
            massalLokasiNama: '',
            massalLokasiAlamat: '',
            massalCatatan: '',
            sibuk: false,
            // Id aktivitas yang sedang ditarik hasilnya dari HCLearn.
            sinkronId: null,
            // Aktivitas yang sedang diputus lulus/tidak (ujian online informatif).
            // Hasilnya ikut disimpan supaya spinner muncul di tombol YANG DITEKAN,
            // bukan di keduanya.
            keputusanId: null,
            keputusanHasil: null,
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
            posisiPilih: [], // larik kosong = semua lowongan
        };
    },
    // Pemantau unduhan hidup di luar siklus Vue — tanpa dibersihkan, ia terus
    // menembak API setelah halaman ditinggalkan.
    beforeUnmount() {
        this.unduhan.forEach((u) => clearTimeout(u.timer));
        if (this.unduhanRaf) cancelAnimationFrame(this.unduhanRaf);
    },
    watch: {
        // Pilihan DIKOSONGKAN saat papan berganti isi.
        //
        // Tanpa ini, kandidat yang terpilih lalu tersaring keluar tetap terbawa
        // diam-diam: penghitungnya bilang "12 terpilih" sementara hanya 3 yang
        // terlihat, dan penjadwalan massal mengundang sembilan orang yang tidak
        // sedang dilihat siapa pun.
        statusTab() { this.terpilih = []; this.listPage = 1; },
        posisiPilih() { this.terpilih = []; this.listPage = 1; },
        /**
         * Ganti aktivitas → lokasi yang sudah dipilih DIBATALKAN.
         *
         * Peruntukannya bisa ikut berganti (wawancara → MCU), dan pilihan lama
         * lalu tak lagi ada di dropdown — tetapi nilainya tetap tersimpan di
         * v-model. Layar menampilkan kotak yang seolah kosong sementara yang
         * terkirim adalah kantor, untuk seratus orang sekaligus.
         */
        massalAktivitas() {
            this.massalLokasiId = null;
            this.massalLokasiNama = '';
            this.massalLokasiAlamat = '';
        },
        keadaanPilih() { this.terpilih = []; this.listPage = 1; },
        // Ganti program = papan yang sama sekali lain. Penyaring kolom milik
        // program lama tidak boleh ikut, karena kunci kolomnya bisa kebetulan
        // sama (dua alur sama-sama punya tahap "Psikotes") dan kolom yang baru
        // dibuka akan langsung tersaring tanpa ada yang menyalakannya.
        //
        // Penyaring TAHAP & KAMPUS ikut dikosongkan: keduanya menyebut nilai
        // milik program lama, dan yang tersisa di kotaknya akan menyaring papan
        // baru sampai kosong tanpa satu pun petunjuk kenapa.
        selectedId() {
            this.tutupPilihKolom();
            this.filterKolom = {};
            this.filterBuka = '';
            this.tahapPilih = [];
            this.kampusPilih = [];
            this.listPage = 1;
        },
        /* Pindah cara membaca papan MENGOSONGKAN pilihan. Dua mode memilih dengan
           cara berbeda — kanban terkurung satu kolom, list bebas melintasi tahap
           — dan pilihan yang terbawa diam-diam jadi tak terlihat di mode
           sebelahnya: kanban hanya menggambar centang pada kolom yang sedang
           dalam mode pilih. Dua puluh orang yang tak tampak di layar tetap ikut
           tersapu tombol massal berikutnya. */
        mode(v) {
            localStorage.setItem('plw.mode', v);
            this.terpilih = [];
            this.tutupPilihKolom();
        },
        /* Saringan berubah → kembali ke halaman satu. Tanpa ini, menyaring dari
           halaman 7 mendarat di halaman yang sudah tidak ada isinya, dan daftar
           terbaca kosong padahal hasilnya ada. */
        cariKandidat() { this.listPage = 1; },
        tahapPilih() { this.listPage = 1; this.terpilih = []; },
        kampusPilih() { this.listPage = 1; this.terpilih = []; },
        rangeTanggal() { this.listPage = 1; this.terpilih = []; },
        listPer() { this.listPage = 1; },
    },
    computed: {
        /**
         * Status lamaran yang berarti KANDIDAT SENDIRI yang mengakhiri.
         *
         * Dibaca dari MASTER (`Flag_Oleh_Kandidat = 'Y'`), bukan didaftar di
         * sini. Master itulah yang menentukan pilihan tombolnya; kalau daftar
         * ini ditulis ulang secara literal, menambah satu sebab mundur di
         * master akan memunculkan tombolnya tapi TIDAK memindahkan kandidatnya
         * ke tab yang benar — dan ia diam-diam kembali menempati papan.
         */
        statusMundur() {
            return new Set(this.hasilKeputusan.filter((h) => h.olehKandidat).map((h) => h.kode));
        },
        // Terminal "tidak lanjut" = GUGUR atau TALENT_POOL. Keduanya keluar dari
        // tab Berjalan dan berkumpul di tab Tidak Lolos (dengan badge berbeda).
        pelamarTampil() {
            const terminal = (r) => r.statusLamaran === 'GUGUR' || r.statusLamaran === 'TALENT_POOL';
            const mundur = (r) => this.statusMundur.has(r.statusLamaran);
            const q = this.cariKandidat.trim().toLowerCase();

            return (this.detail.pelamar || [])
                .filter((r) => {
                    // SEMUA tidak menyaring apa pun — seluruh kandidat program
                    // ini, di kolom tahapnya masing-masing.
                    if (this.statusTab === 'SEMUA') return true;
                    // DITAHAN adalah tab tersendiri, bukan bagian dari
                    // "Berjalan": mereka memang masih berjalan, tapi justru itu
                    // masalahnya — tercampur di sana, orang yang sengaja
                    // disisihkan tenggelam dan tak pernah ditinjau lagi.
                    if (this.statusTab === 'HOLD') return !!r.hold;
                    if (this.statusTab === 'MUNDUR') return mundur(r);
                    if (this.statusTab === 'GUGUR') return terminal(r);

                    return !terminal(r) && !mundur(r) && !r.hold;
                })
                // Penyaring lowongan berlaku untuk SEMUA tab, supaya angka
                // "berjalan" dan "tidak lolos" satu lowongan bisa dibandingkan.
                // Larik kosong = tidak menyaring. Beberapa nilai = gabungan
                // (ATAU) di dalam satu penyaring, dan irisan (DAN) antar
                // penyaring — bentuk yang sama dengan yang orang harapkan dari
                // saringan bertumpuk mana pun.
                .filter((r) => !this.posisiPilih.length || this.posisiPilih.includes(r.posisiId))
                // TAHAP — dicocokkan lewat KUNCI KOLOM, bukan nomor urut. Alasan
                // yang sama dengan penempatan kartu di kartuKolom(): nomor urut
                // berhenti benar begitu alur disunting atau program dialihkan.
                .filter((r) => !this.tahapPilih.length || this.tahapPilih.includes(this.kunciBaris(r)))
                .filter((r) => !this.kampusPilih.length || this.kampusPilih.includes(r.kampus || ''))
                // RENTANG TANGGAL MELAMAR. Batas akhirnya mencakup SELURUH hari
                // yang dipilih: memakai tengah malam membuat orang yang melamar
                // pukul 09.00 pada tanggal akhir jatuh di luar rentangnya sendiri.
                .filter((r) => this.dalamRentang(r.waktuLamar))
                // Cari menjangkau posisi & kampus juga — keduanya yang paling
                // sering diketik orang saat mencari "anak Unsri di QC".
                .filter((r) => !q
                    || (r.pelamar || '').toLowerCase().includes(q)
                    || (r.lamaranKode || '').toLowerCase().includes(q)
                    || (r.posisi || '').toLowerCase().includes(q)
                    || (r.kampus || '').toLowerCase().includes(q))
                // Keadaan menjawab "mana yang menunggu SAYA?" — pertanyaan yang
                // tanpa ini dijawab dengan memindai lencana kartu satu per satu.
                // Beberapa keadaan sekaligus digabung dengan ATAU: "perlu
                // keputusan ATAU sedang ditahan" adalah satu antrean kerja yang
                // memang dibaca bersamaan.
                .filter((r) => !this.keadaanPilih.length
                    || this.keadaanPilih.some((k) => this.cocokKeadaan(r, k)));
        },
        jmlSemua() { return (this.detail.pelamar || []).length; },
        jmlAktif() {
            return (this.detail.pelamar || []).filter((r) => r.statusLamaran !== 'GUGUR'
                && r.statusLamaran !== 'TALENT_POOL'
                && !this.statusMundur.has(r.statusLamaran)
                && !r.hold).length;
        },
        jmlGugur() { return (this.detail.pelamar || []).filter((r) => r.statusLamaran === 'GUGUR' || r.statusLamaran === 'TALENT_POOL').length; },
        jmlHold() { return (this.detail.pelamar || []).filter((r) => !!r.hold).length; },
        jmlMundur() { return (this.detail.pelamar || []).filter((r) => this.statusMundur.has(r.statusLamaran)).length; },
        /* ── PENYARING: PILIHAN & RINGKASANNYA ─────────────────────────────── */
        /**
         * Peta cepat "identitas tahap → kunci kolom".
         *
         * kunciBaris() dipanggil untuk SETIAP kandidat pada setiap render, dan
         * pada program berisi ratusan pelamar pencarian linier ke daftar kolom
         * dikerjakan ribuan kali per render. Petanya dihitung sekali per
         * perubahan alur.
         */
        petaKolom() {
            const kode = new Map();
            const urutan = new Map();
            for (const c of this.kolomTampil) {
                const k = this.kunciKolom(c);
                if (c.kode != null) kode.set(c.kode, k);
                if (c.urutan != null && !urutan.has(c.urutan)) urutan.set(c.urutan, k);
            }

            return { kode, urutan };
        },
        /**
         * Kampus yang BENAR-BENAR ada di program ini, berikut jumlah pelamarnya.
         *
         * Dihitung dari seluruh pelamar program — bukan dari `pelamarTampil` —
         * supaya daftarnya tidak menyusut mengikuti saringan yang sedang
         * berjalan. Dropdown yang isinya ikut menciut membuat pilihan yang baru
         * saja terlihat lenyap begitu satu penyaring lain dinyalakan, dan tak
         * ada cara menebak ke mana perginya.
         */
        kampusOpsi() {
            const peta = new Map();
            for (const r of this.detail.pelamar || []) {
                const nama = (r.kampus || '').trim();
                if (!nama) continue;
                peta.set(nama, (peta.get(nama) || 0) + 1);
            }

            const bendera = this.detail.kampusBendera || {};

            return [...peta.entries()]
                // `bendera` boleh kosong: nama yang diketik sendiri kandidat
                // memang tidak ada di Master Kampus, dan menebak negaranya dari
                // ejaan lebih menyesatkan daripada menggambar bola dunia.
                .map(([nama, jumlah]) => ({ nama, jumlah, bendera: bendera[nama] || null }))
                .sort((a, b) => b.jumlah - a.jumlah || a.nama.localeCompare(b.nama));
        },
        /**
         * Penyaring yang sedang menyala — sumber chip DAN tombol bersihkan.
         *
         * SATU CHIP PER NILAI, bukan satu chip per penyaring. Sejak keempatnya
         * multi-pilih, "Tahap: 3 dipilih" menyembunyikan justru yang perlu
         * dilihat, dan melepas satu di antaranya menuntut membuka dropdown-nya
         * kembali. Kuncinya ikut membawa nilainya supaya copotChip() tahu yang
         * mana yang dicabut.
         */
        chipFilter() {
            const out = [];
            const cari = this.cariKandidat.trim();
            if (cari) out.push({ key: 'cari', jenis: 'Cari', label: cari });
            this.tahapPilih.forEach((v) => {
                const c = this.kolomTampil.find((x) => this.kunciKolom(x) === v);
                out.push({ key: `tahap:${v}`, jenis: 'Tahap', label: c?.label || v });
            });
            this.kampusPilih.forEach((v) => out.push({ key: `kampus:${v}`, jenis: 'Kampus', label: v }));
            this.posisiPilih.forEach((v) => {
                const p = (this.detail.posisi || []).find((x) => x.id === v);
                out.push({ key: `posisi:${v}`, jenis: 'Lowongan', label: p?.posisi || 'Terpilih' });
            });
            this.keadaanPilih.forEach((v) => {
                const teks = {
                    PERLU: 'Perlu keputusan saya',
                    NUNGGU: 'Menunggu hasil tes',
                    JADWAL: 'Belum dijadwalkan',
                    TERJADWAL: 'Sudah dijadwalkan',
                    HOLD: 'Sedang ditahan',
                };
                out.push({ key: `keadaan:${v}`, jenis: 'Keadaan', label: teks[v] || v });
            });
            if (this.rentangAktif) {
                const [a, b] = this.rangeTanggal;
                out.push({ key: 'tanggal', jenis: 'Melamar', label: `${this.tglSingkat(a)} → ${this.tglSingkat(b)}` });
            }

            return out;
        },
        /** Rentang tanggal benar-benar terisi (el-date-picker mengosongkan jadi null). */
        rentangAktif() {
            return Array.isArray(this.rangeTanggal) && !!this.rangeTanggal[0] && !!this.rangeTanggal[1];
        },
        /**
         * Pintasan mana yang sedang menyala.
         *
         * Dicocokkan dari NILAI rentangnya, bukan dari tombol mana yang
         * terakhir ditekan: rentang yang sama boleh saja dipilih lewat
         * kalender, dan menyalakan tombol berdasarkan riwayat klik akan
         * membuatnya padam untuk rentang yang isinya persis sama.
         */
        pintasAktif() {
            if (!this.rentangAktif) return null;
            const cocok = this.pintasTanggal.find((p) => {
                const [a, b] = this.rentangHari(p.hari);

                return a === this.rangeTanggal[0] && b === this.rangeTanggal[1];
            });

            return cocok ? cocok.hari : null;
        },

        /* ── MODE LIST: PAGINASI ───────────────────────────────────────────── */
        /**
         * Urutan daftar: TERLAMA MENUNGGU DULU — sama dengan bawaan kolom kanban.
         * Dua tampilan atas populasi yang sama tidak boleh mengurutkan berbeda;
         * kalau berbeda, "yang paling atas" berarti dua hal tergantung tombol
         * mana yang terakhir ditekan.
         */
        barisTerurut() {
            const waktu = (r) => new Date(String(r.waktuLamar || '').replace(' ', 'T')).getTime() || 0;

            return [...this.pelamarTampil].sort((a, b) => waktu(a) - waktu(b));
        },
        totalList() { return this.barisTerurut.length; },
        totalHalamanList() { return Math.max(1, Math.ceil(this.totalList / this.listPer)); },
        /** Halaman yang benar-benar dipakai — dijepit agar tak melewati batas. */
        halamanList() { return Math.min(Math.max(1, this.listPage), this.totalHalamanList); },
        barisList() {
            const awal = (this.halamanList - 1) * this.listPer;

            return this.barisTerurut.slice(awal, awal + this.listPer);
        },
        /** Nomor halaman dengan elipsis — 1 … 4 5 6 … 12. */
        nomorHalaman() {
            const total = this.totalHalamanList;
            const kini = this.halamanList;
            const out = [];
            for (let n = 1; n <= total; n++) {
                if (n === 1 || n === total || Math.abs(n - kini) <= 1) {
                    out.push(n);
                } else if (out[out.length - 1] !== '…') {
                    out.push('…');
                }
            }

            return out;
        },
        teksHasil() {
            if (!this.totalList) return 'Tidak ada kandidat';
            if (this.mode === 'kanban') return `${this.totalList} kandidat di papan`;
            const awal = (this.halamanList - 1) * this.listPer;

            return `Menampilkan ${awal + 1}–${Math.min(awal + this.listPer, this.totalList)} dari ${this.totalList}`;
        },

        /* ── MODAL DETAIL: BERKAS SEBAGAI FILE MANAGER ─────────────────────── */
        /**
         * SELURUH lembar berkas kandidat, diratakan jadi satu daftar.
         *
         * Dikumpulkan dari TIGA tempat, karena di basis data memang berkas
         * kandidat tersebar di tiga bentuk yang berbeda:
         *   1. jawaban tingkat atas yang bertipe dokumen (dok_ktp, dok_cv, …);
         *   2. sub-isian di dalam baris berulang — sertifikat pada riwayat
         *      sertifikasi, yang satu barisnya bisa membawa beberapa lembar;
         *   3. berkas tanpa pertanyaan — foto verifikasi yang diambil sistem.
         *
         * Yang jadi NAMA di layar adalah pertanyaannya, bukan nama file. Nama
         * file bawaan unggahan ("SUJATMIKO_-_PAPI_KOSTICK_-_20260805.pdf") tidak
         * memberi tahu siapa pun ini menjawab apa; ia turun jadi baris kedua.
         */
        fmSemua() {
            const keluar = [];
            const tambah = (f, nama, konteks, b, i) => {
                if (!b) return;
                keluar.push({
                    id: `${f.no}::${b.field || nama}::${i}`,
                    nama,
                    konteks,
                    file: b.nama || '—',
                    ext: String(b.ext || '').toUpperCase(),
                    isImage: !!b.isImage,
                    folder: String(f.no),
                    folderLabel: f.label,
                    berkas: b,
                });
            };

            (this.profil.formulir || []).forEach((f) => {
                (f.jawaban || []).forEach((j) => {
                    const label = this.labelIsian(f, j);
                    this.berkasSel(j).forEach((b, i) => tambah(f, label, f.label, b, i));

                    (j.baris || []).forEach((row, ri) => (row || []).forEach((p) => {
                        this.berkasSel(p).forEach((b, i) => tambah(
                            f,
                            p.label || label,
                            `${label} · baris ${ri + 1}`,
                            b,
                            `${ri}-${i}`,
                        ));
                    }));
                });

                this.berkasLepas(f).forEach((b, i) => tambah(f, this.labelBerkas(b), f.label, b, `x${i}`));
            });

            return keluar;
        },
        /** Folder kiri: "Semua" lebih dulu, lalu satu per formulir. */
        fmFolderOpsi() {
            const per = new Map();
            this.fmSemua.forEach((b) => per.set(b.folder, (per.get(b.folder) || 0) + 1));

            return [
                { key: '', label: 'Semua Berkas', ikon: 'bi-collection-fill', jumlah: this.fmSemua.length },
                ...(this.profil.formulir || []).map((f) => ({
                    key: String(f.no),
                    label: f.label,
                    ikon: f.waktuKirim ? 'bi-folder-fill' : 'bi-folder',
                    jumlah: per.get(String(f.no)) || 0,
                })),
            ];
        },
        /** Isi petak kanan: folder terpilih, disaring kata kunci. */
        fmBerkas() {
            const q = this.fmCari.trim().toLowerCase();

            return this.fmSemua.filter((b) => {
                if (this.fmFolder && b.folder !== this.fmFolder) return false;
                if (!q) return true;

                return `${b.nama} ${b.file} ${b.konteks} ${b.ext}`.toLowerCase().includes(q);
            });
        },
        /** Judul remah-roti kanan. */
        fmJudul() {
            return (this.fmFolderOpsi.find((f) => f.key === this.fmFolder) || {}).label || 'Semua Berkas';
        },
        /** Berkas yang sedang disorot — jadi isi bilah pratinjau di kaki panel. */
        fmTerpilih() {
            return this.fmBerkas.find((b) => b.id === this.fmSorot) || null;
        },
        /**
         * Kelengkapan: formulir yang SUDAH DIKIRIM, bukan yang punya berkas.
         * Formulir tanpa satu pun pertanyaan dokumen tetap sah lengkap.
         */
        fmLengkap() {
            return (this.profil.formulir || []).filter((f) => !!f.waktuKirim).length;
        },
        fmPersen() {
            const n = (this.profil.formulir || []).length;

            return n ? Math.round((this.fmLengkap / n) * 100) : 0;
        },
        /** Total pertanyaan dokumen di seluruh formulir — penyebut "7/7 dokumen". */
        dokTotal() {
            return (this.profil.formulir || []).reduce((n, f) => n + this.dokFormulir(f).length, 0);
        },
        /** Dokumen yang benar-benar sudah terlampir — pembilangnya. */
        dokLengkap() {
            return (this.profil.formulir || []).reduce((n, f) => n + this.dokFormulir(f).filter((d) => d.berkas).length, 0);
        },

        /* ── MODAL DETAIL: TAB & ALUR ──────────────────────────────────────── */
        /** Definisi tab modal detail berikut penghitungnya. */
        tabDetail() {
            // DUA tab, bukan tiga. "Alur Seleksi" dulu berdiri sendiri padahal
            // isinya satu blok progres yang tidak pernah dibaca terpisah — ia
            // justru dicari sambil menilai rapor ("tes ini tahap keberapa, masih
            // sisa berapa lagi?"). Sekarang ia duduk di kaki tab Rapor Tes, tempat
            // pertanyaan itu muncul, dan satu klik tab hilang dari alur kerja.
            return [
                { key: 'rapor', label: 'Rapor Tes', ikon: 'bi-clipboard2-check-fill', jumlah: (this.detailKandidat?.tests || []).length },
                { key: 'berkas', label: 'Berkas & Biodata', ikon: 'bi-folder2-open', jumlah: (this.profil.formulir || []).length },
            ];
        },
        /**
         * Linimasa tahap program dengan posisi kandidat ini.
         *
         * Kolomnya dari papan (`kolomTampil`) — alur yang BENAR-BENAR dipakai
         * kandidat, bukan alur yang sekarang menempel di program. Perbandingan
         * memakai nomor urut karena itulah yang dibawa kandidat pada `urutan`;
         * kolom cadangan "alur lama" tidak punya urutan dan jatuh ke akhir.
         */
        alurKandidat() {
            const d = this.detailKandidat;
            if (!d) return [];
            const kini = Number(d.urutan) || 0;
            const tutup = d.statusLamaran !== 'BERJALAN';
            const lulusPenuh = d.statusLamaran === 'LULUS';

            return this.kolomTampil.map((c, i) => {
                const no = Number(c.urutan) || i + 1;
                let keadaan = 'nanti';
                if (lulusPenuh || no < kini) keadaan = 'lewat';
                else if (no === kini) keadaan = tutup ? 'tutup' : 'kini';

                const teks = {
                    lewat: { tag: 'Selesai', catatan: 'Sudah dilewati' },
                    kini: { tag: 'Berlangsung', catatan: d.hold ? 'Sedang ditahan' : 'Sedang berjalan' },
                    tutup: { tag: this.statusLabel(d.statusLamaran), catatan: 'Perjalanan berakhir di tahap ini' },
                    nanti: { tag: 'Menunggu', catatan: 'Belum dimulai' },
                }[keadaan];

                return {
                    kunci: this.kunciKolom(c),
                    nomor: String(no).padStart(2, '0'),
                    label: c.label,
                    keadaan,
                    tag: teks.tag,
                    catatan: teks.catatan,
                };
            });
        },
        persenAlur() {
            const d = this.detailKandidat;
            if (!d?.totalTahap) return 0;
            if (d.statusLamaran === 'LULUS') return 100;

            return Math.min(100, Math.round(((Number(d.urutan) || 1) - 0.5) / d.totalTahap * 100));
        },

        /* ── PILIH BANYAK & JADWAL MASSAL ──────────────────────────────────── */
        /** Kandidat terpilih yang masih ADA di papan (penyaring bisa berubah). */
        barisTerpilih() {
            return this.pelamarTampil.filter((r) => this.terpilih.includes(r.id));
        },
        /** Seluruh baris HALAMAN INI sudah tercentang? — keadaan centang kepala. */
        halamanTercentangPenuh() {
            return this.barisList.length > 0 && this.barisList.every((r) => this.terpilih.includes(r.id));
        },
        /**
         * Sebagian saja — kotak kepala digambar setengah (garis, bukan centang).
         *
         * Perlu dibedakan dari "kosong": tanpa keadaan tengah ini, memilih tiga
         * dari dua puluh membuat kotak kepala tampak persis seperti belum ada
         * yang dipilih, dan menekannya akan terbaca sebagai "pilih semua"
         * padahal ia justru menghapus tiga pilihan tadi.
         */
        halamanTercentangSebagian() {
            return !this.halamanTercentangPenuh && this.barisList.some((r) => this.terpilih.includes(r.id));
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
                    if (!peta.has(k)) peta.set(k, { label: k, jumlah: 0, wajibLuring: false, lokasiPeruntukan: null });
                    const a = peta.get(k);
                    a.jumlah++;
                    a.wajibLuring = a.wajibLuring || !!t.wajibLuring;
                    // Peruntukan lokasinya ikut dibawa — satu nama aktivitas
                    // selalu satu tipe tahap, jadi nilainya sama untuk seluruh
                    // kandidat yang terkumpul di baris ini.
                    a.lokasiPeruntukan = a.lokasiPeruntukan || t.lokasiPeruntukan || null;
                }
            }

            return [...peta.values()].sort((a, b) => b.jumlah - a.jumlah);
        },
        aktivitasMassalDef() {
            return this.aktivitasTerpilih.find((a) => a.label === this.massalAktivitas) || null;
        },
        massalWajibLuring() { return !!this.aktivitasMassalDef?.wajibLuring; },
        peruntukanMassal() {
            const kode = this.aktivitasMassalDef?.lokasiPeruntukan;

            return kode ? this.daftarPeruntukan.find((p) => p.kode === kode) || null : null;
        },
        lokasiUntukMassal() {
            const kode = this.aktivitasMassalDef?.lokasiPeruntukan;
            if (!kode) return this.daftarLokasi;

            return this.daftarLokasi.filter((l) => (l.peruntukan || []).includes(kode));
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
        /**
         * Tiga jam pertama saja — contoh irama, bukan daftar lengkap.
         *
         * Daftar utuhnya ada di pratinjau bawah, tapi itu jauh di bawah kolom
         * catatan: yang sedang mengetik durasi & jeda tidak melihatnya, dan
         * justru di detik itulah ia perlu tahu apa arti angkanya. Diambil dari
         * pratinjau yang sama supaya keduanya mustahil berselisih.
         */
        ritmeMassal() {
            if (!this.massalMulai) return [];
            const awal = new Date(String(this.massalMulai).replace(' ', 'T'));
            if (Number.isNaN(awal.getTime())) return [];

            // Rumus yang SAMA dengan pratinjauMassal dan dengan server:
            // mulai = awal + urutan × (durasi + jeda). Jam saja, tanpa tanggal —
            // ini contoh irama, dan tanggalnya sudah terbaca di kolom di atas.
            const langkah = (Number(this.massalDurasi) || 0) + (Number(this.massalJeda) || 0);
            const jumlah = Math.min(3, Math.max(2, this.sasaranMassal.length));

            return Array.from({ length: jumlah }, (_, i) => new Date(awal.getTime() + i * langkah * 60000)
                .toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }));
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
            if (this.massalMode === 'DARING') return !!this.massalLink.trim();
            if (!this.massalLokasiId) return false;
            // Tempat luar-master: syaratnya sama persis dengan jendela satuan
            // dan dengan server.
            if (this.massalLokasiId === this.LOKASI_LAIN) {
                if (!this.massalLokasiNama.trim()) return false;
                if (this.peruntukanMassal?.wajibAlamatLainnya && !this.massalLokasiAlamat.trim()) return false;
            }

            return true;
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

            // Pada tahap berpenawaran, TIDAK ADA jawaban kandidat yang masuk akal
            // sebelum penawarannya benar-benar diajukan — belum ada yang bisa
            // ditolak. Di tahap lain, mundur tetap boleh kapan saja.
            if (this.tahapPenawaran && !this.detailKandidat.penawaranDiajukan) return [];

            return this.hasilKeputusan.filter((h) => {
                if (!h.olehKandidat) return false;
                // "Menolak penawaran" hanya masuk akal bila ada penawarannya.
                if (h.kode === 'DITOLAK_KANDIDAT') {
                    // Dan bila negosiasinya BERJADWAL, penolakan itu dicatat di
                    // modal kehadirannya — di sanalah peristiwanya terjadi,
                    // lengkap dengan alasan dan penutupan lamarannya. Dua pintu
                    // menuju keadaan yang sama memaksa admin menebak mana yang
                    // benar; yang tak berjadwal (Surat Penawaran / `infoSaja`)
                    // tidak punya modal itu, jadi tombolnya tetap dibutuhkan.
                    return this.tahapPenawaran && !this.jawabanDiCatatKehadiran;
                }

                // MENGUNDURKAN DIRI TETAP ADA DI TAHAP NEGOSIASI.
                //
                // Dulu seluruh tombol ini padam begitu tahapnya punya negosiasi
                // berjadwal — dan mundur pun ikut hilang bersamanya. Padahal
                // keduanya bukan peristiwa yang sama: menolak penawaran adalah
                // jawaban ATAS ANGKA yang kita ajukan, mundur adalah kandidat
                // meninggalkan proses (dapat tempat lain, alasan pribadi) dan
                // itu bisa terjadi kapan saja — termasuk sebelum negosiasinya
                // sempat berlangsung. Tanpa tombol ini, satu-satunya cara
                // mencatatnya adalah memilih "menolak penawaran" yang tidak
                // pernah ada, atau "Tidak Lolos" yang menyalahkan orang yang
                // pergi baik-baik.
                return true;
            });
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
        /**
         * Peruntukan yang berlaku untuk aktivitas yang sedang dijadwalkan.
         *
         * null = tipe ini tidak dibatasi (phone screen, negosiasi) — seluruh
         * lokasi boleh, persis seperti sebelum pembagian ini ada.
         */
        peruntukanJadwal() {
            const kode = this.jadwalTarget?.lokasiPeruntukan;

            return kode ? this.daftarPeruntukan.find((p) => p.kode === kode) || null : null;
        },
        /** Lokasi yang boleh dipilih untuk aktivitas ini. */
        lokasiUntukJadwal() {
            const kode = this.jadwalTarget?.lokasiPeruntukan;
            if (!kode) return this.daftarLokasi;

            return this.daftarLokasi.filter((l) => (l.peruntukan || []).includes(kode));
        },
        bolehLokasiLain() { return !!this.peruntukanJadwal?.izinkanLainnya; },
        /** Judul panel unduhan — menyebut jumlahnya, bukan sekadar "Unduhan". */
        judulUnduhan() {
            const jalan = this.unduhan.filter((u) => u.keadaan === 'siap' || u.keadaan === 'unduh').length;

            return jalan
                ? `Menyiapkan ${jalan} berkas…`
                : `${this.unduhan.length} berkas selesai`;
        },
        /**
         * Tempat yang sedang dipilih — dari master ATAU yang diketik sendiri.
         *
         * Yang diketik dibentuk MENYERUPAI baris master (termasuk peta dari nama
         * + alamatnya), sehingga kartu pratinjau di bawahnya tidak perlu tahu
         * bedanya dan rekruter tetap bisa memastikan titiknya sebelum undangan
         * terkirim.
         */
        lokasiTerpilih() {
            if (this.jadwalLokasiId === this.LOKASI_LAIN) {
                const nama = (this.jadwalLokasiNama || '').trim();
                if (!nama) return null;

                // TANPA PETA. Titiknya belum pernah diverifikasi siapa pun, dan
                // peta hasil tebakan tampil sama persis seperti peta yang sudah
                // dipastikan — rekruter lalu mengira ia sudah memeriksa
                // tempatnya padahal belum. Yang dijanjikan ke kandidat cukup
                // alamat yang ia ketik sendiri.
                return {
                    nama,
                    alamat: (this.jadwalLokasiAlamat || '').trim() || null,
                    kontakTelp: null,
                    lepas: true,
                    mapsEmbed: null,
                    mapsUrl: null,
                };
            }

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
            if (m.butuhLokasi) {
                if (!this.jadwalLokasiId) return false;
                // Tempat di luar master: nama wajib, alamat wajib bila masternya
                // bilang begitu. Dicermin dari `periksaPeruntukanLokasi` di
                // server — tombol yang menyala lalu ditolak 422 membuat rekruter
                // mengira sistemnya rusak.
                if (this.jadwalLokasiId === this.LOKASI_LAIN) {
                    if (!(this.jadwalLokasiNama || '').trim()) return false;
                    if (this.peruntukanJadwal?.wajibAlamatLainnya && !(this.jadwalLokasiAlamat || '').trim()) return false;
                }

                return true;
            }
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
        /** Keterangan "belum tuntas" kandidat INI sedang ditutup? */
        notaTutup() { return !!this.notaDitutup[this.detailKandidat?.id]; },
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
        /**
         * Aksi yang dimiliki pengguna ini pada halaman worklist.
         *
         * Dikirim shell ke SEMUA halaman admin lewat props `akses`. Halaman ini
         * dulu tidak pernah membacanya.
         */
        aksiSaya() {
            return this.akses?.permissions?.pelamarPage || [];
        },
        /**
         * Berhak MEMUTUS tahap?
         *
         * Seluruh keputusan — Loloskan, Tidak Lolos, Talent Pool, Mengundurkan
         * Diri, Menolak Penawaran — memakai satu endpoint yang sama
         * (PATCH .../putus) dengan satu izin yang sama: APPROVE. Tidak ada
         * keputusan yang lebih ringan dari yang lain di mata server.
         */
        bolehPutus() {
            return this.aksiSaya.includes('APPROVE');
        },
        terkunciPutus() {
            return (h) => {
                // HAK AKSES DIPERIKSA PALING DULU.
                //
                // Tanpa ini tombol tetap digambar, admin mengetik alasan,
                // memilih tanggal, mencentang persetujuan, menekan simpan —
                // dan BARU di situ server menjawab 403. Seluruh isian hilang,
                // dan pesannya ("tidak memiliki hak akses") muncul di ujung
                // jalan yang seharusnya tidak pernah bisa dimasuki.
                if (! this.bolehPutus) {
                    return 'Akun Anda tidak punya izin APPROVE di Worklist Pelamar, jadi tidak bisa mengetuk keputusan. Minta admin menambahkannya di Manajemen Hak Akses.';
                }
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
        /* ── TAHAN / LANJUTKAN MASSAL ──────────────────────────────────────── */
        /**
         * Terpilih yang MEMANG bisa ditahan.
         *
         * Tahap yang sudah diputus tidak bisa ditahan lagi (server menolaknya),
         * dan yang sudah ditahan tidak perlu ditahan dua kali. Disaring di sini
         * supaya tombolnya mati saat tak ada satu pun yang bisa diproses —
         * bukan membuka modal yang seluruh isinya akan gagal.
         */
        bisaTahanMassal() {
            return this.barisTerpilih.filter((r) => r.tahapId && !r.hold && r.statusLamaran === 'BERJALAN');
        },
        /** Terpilih yang sedang DITAHAN — sasaran tombol "Lanjutkan". */
        bisaLepasMassal() {
            return this.barisTerpilih.filter((r) => r.tahapId && r.hold);
        },
        /**
         * Terpilih yang BISA diputus.
         *
         * Yang sedang ditahan sengaja dikeluarkan: menahan berarti "keputusannya
         * ditunda", dan mengetuk palu massal ke atasnya membatalkan penundaan itu
         * diam-diam — justru pada kandidat yang paling butuh ditimbang sendiri.
         * Lepaskan tahanannya dulu bila memang sudah siap diputus.
         */
        bisaPutusMassal() {
            return this.barisTerpilih.filter((r) => r.tahapId && !r.hold && r.statusLamaran === 'BERJALAN');
        },
        /**
         * Pilihan keputusan untuk massal: HANYA keputusan perusahaan.
         *
         * "Mengundurkan diri" dan "menolak tawaran" datang DARI KANDIDAT — satu
         * per satu, dengan alasan masing-masing, kerap disertai tanggal ia
         * mengabari. Menerapkannya ke dua puluh orang sekaligus berarti mengaku
         * dua puluh orang menyatakan hal yang sama pada saat yang sama, dan itu
         * tidak pernah benar. Keputusan itu tetap lewat drawer per kandidat.
         */
        pmHasilOpsi() { return this.hasilKeputusan.filter((h) => !h.olehKandidat); },
        pmHasilDef() { return this.hasilKeputusan.find((h) => h.kode === this.pmHasil) || null; },
        pmButuhCatatan() { return !!this.pmHasilDef?.butuhAlasan; },
        pmCatatanCukup() { return teksDariHtml(this.pmCatatanHtml).trim() !== ''; },
        /**
         * Boleh disimpan? Pada mode SENDIRI syaratnya berlaku untuk SETIAP baris —
         * satu baris kosong di tengah daftar akan ditolak server dan menyisakan
         * keputusan setengah jadi yang harus dicari sendiri oleh admin.
         */
        bolehSimpanPutusMassal() {
            if (!this.pmTarget.length) return false;
            if (this.pmPola === 'SERAGAM') {
                if (!this.pmHasil) return false;

                return !this.pmButuhCatatan || this.pmCatatanCukup;
            }

            return this.pmBaris.every((b) => !this.barisPmKurang(b));
        },
        hmAlasanDef() { return this.alasanHold.find((a) => a.value === this.hmAlasan) || null; },
        hmButuhCatatan() { return !!this.hmAlasanDef?.butuhCatatan; },
        hmCatatanCukup() { return teksDariHtml(this.hmCatatanHtml).trim() !== ''; },
        /**
         * Boleh disimpan?
         *
         * Melepas tidak menuntut apa pun. Menahan menuntut alasan — dan pada
         * mode SENDIRI, menuntutnya untuk SETIAP baris: satu baris kosong di
         * tengah daftar akan ditolak server dan menyisakan penahanan setengah
         * jadi yang harus dicari sendiri oleh admin.
         */
        bolehSimpanHoldMassal() {
            if (!this.hmTarget.length) return false;
            if (!this.hmNilai) return true;
            if (this.hmPola === 'SERAGAM') {
                if (!this.hmAlasan) return false;

                return !this.hmButuhCatatan || this.hmCatatanCukup;
            }

            return this.hmBaris.every((b) => !this.barisHmKurang(b));
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
            return this.petaSkema(form).label[isian.key]
                || this.petaSkema(form).bagian[isian.key]?.label
                || this.labelTebakan(isian.key)
                || isian.label;
        },
        /**
         * Peta label + tipe + kelompok satu formulir, dihitung sekali.
         *
         * Sumbernya SKEMA BEKU milik pengisian itu; kode komponen hanya
         * cadangan untuk formulir bawaan lama yang memang tak punya snapshot.
         */
        petaSkema(form) {
            if (!form) return { label: {}, tipe: {}, grup: {}, bagian: {} };

            let peta = PETA_SKEMA.get(form);
            if (peta) return peta;

            const sumber = form.skema || form.komponen || null;
            peta = {
                label: labelField(sumber),
                tipe: tipeField(sumber),
                grup: grupField(sumber),
                bagian: grupBagian(sumber),
            };
            PETA_SKEMA.set(form, peta);

            return peta;
        },
        /**
         * Label cadangan dari nama kunci — dipakai HANYA bila skemanya tak
         * memuat pertanyaan itu (formulir versi lama, field yang sudah dihapus).
         *
         * Awalan sependek satu-dua huruf DIBUANG. `v_nama` berarti "nama pada
         * langkah validasi" bagi yang menulis skemanya; bagi yang membacanya di
         * layar, "V Nama" cuma huruf nyasar di depan kata — dan "V Email" lebih
         * buruk lagi karena terbaca seperti nama sistem.
         *
         * Singkatan yang memang huruf kapital (NIK, KTP, CV, IPK) dikembalikan
         * apa adanya, bukan jadi "Nik" dan "Ktp".
         */
        labelTebakan(key) {
            const potong = String(key || '').split(/[_-]+/).filter(Boolean);
            if (!potong.length) return '';
            if (potong.length > 1 && potong[0].length <= 2) potong.shift();

            const AKRONIM = new Set(['nik', 'ktp', 'kk', 'npwp', 'cv', 'wa', 'hp', 'ipk', 'sim', 'no', 'sk', 'pt', 'bpjs', 'nisn', 'npsn']);

            return potong
                .map((w) => (AKRONIM.has(w.toLowerCase()) ? w.toUpperCase() : w.charAt(0).toUpperCase() + w.slice(1)))
                .join(' ');
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
         * Berkas yang TIDAK punya pertanyaan pasangannya.
         *
         * Foto verifikasi diambil sistem saat kandidat melamar — ia tidak
         * pernah menjadi jawaban dari pertanyaan mana pun, jadi tak punya baris
         * di daftar isian. Kalau berkas semacam ini tidak ikut ditampilkan, ia
         * hilang sama sekali dari pandangan peninjau.
         *
         * Yang dihitung "terpakai" mencakup DUA tingkat:
         *   1. berkas jawaban tingkat atas — dok_ktp, dok_cv, …
         *   2. berkas SUB-ISIAN di dalam baris berulang — sert_file pada
         *      riwayat sertifikasi.
         *
         * Tingkat kedua sempat terlewat, dan akibatnya sertifikat muncul dua
         * kali: sekali di dalam baris riwayatnya, sekali lagi di daftar ini.
         */
        berkasLepas(f) {
            const dipakai = new Set();
            (f.jawaban || []).forEach((j) => {
                this.berkasSel(j).forEach((b) => dipakai.add(b.field));
                (j.baris || []).forEach((row) => row.forEach((p) => {
                    this.berkasSel(p).forEach((b) => dipakai.add(b.field));
                }));
            });

            return (f.berkas || []).filter((b) => !dipakai.has(b.field));
        },
        /**
         * Nama baris untuk berkas tanpa pertanyaan. `foto_verifikasi` disebut
         * apa adanya karena ia punya arti tersendiri — bukti kandidat yang
         * mengisi memang orangnya; sisanya dirapikan dari nama kuncinya.
         */
        labelBerkas(b) {
            if (b.field === 'foto_verifikasi') return 'Foto Verifikasi';

            const inti = String(b.field || '').replace(/^(dok|file|berkas|upload)[_-]/i, '');

            return this.labelTebakan(inti) || 'Berkas';
        },
        /** Kunci akordion satu riwayat: nomor formulir + kunci isiannya. */
        kunciRiwayat(no, key) { return `${no}::${key}`; },
        /**
         * Riwayat TERBUKA secara bawaan — peninjau membuka formulir justru untuk
         * membacanya, dan memaksa satu klik tambahan per riwayat memperlambat
         * pekerjaan yang paling sering dilakukan. Akordionnya untuk MENUTUP
         * yang sudah selesai dibaca, bukan untuk membuka yang belum.
         */
        riwayatTutup(no, key) {
            return !!this.riwayatDitutup[this.kunciRiwayat(no, key)];
        },
        toggleRiwayat(no, key) {
            const k = this.kunciRiwayat(no, key);
            this.riwayatDitutup = { ...this.riwayatDitutup, [k]: !this.riwayatDitutup[k] };
        },
        /**
         * Kolom mana pada satu riwayat yang jadi blok URAIAN (keluar dari kisi).
         *
         * Diputuskan SEKALI UNTUK SELURUH RIWAYAT, bukan per sel. Kalau diputus
         * per sel, kolom "Uraian" pindah tempat mengikuti panjang jawaban tiap
         * baris: baris 1 yang uraiannya sekalimat tetap duduk di kisi kanan,
         * baris 2 yang uraiannya separagraf melompat jadi blok penuh di bawah.
         * Label yang sama muncul di dua tempat berbeda dalam satu daftar, dan
         * mata kehilangan kolom untuk diikuti — persis penyakit yang dulu
         * membuat bagian ini dibongkar jadi linimasa.
         *
         * Ambangnya PANJANG JAWABAN TERPANJANG di kolom itu, bukan daftar nama
         * kolom: tiap formulir menamai uraiannya sendiri-sendiri ("Uraian",
         * "Deskripsi Tugas", "Rincian Kegiatan"), dan daftar nama pasti
         * tertinggal begitu ada formulir baru.
         *
         * Dikunci per array `baris` lewat WeakMap: drawer ini dirender ulang
         * tiap kali ada centang berubah, dan menghitung ulang tiap render
         * membuang kerja yang jawabannya selalu sama.
         */
        kolomUraian(baris) {
            if (!Array.isArray(baris) || !baris.length) return EMPTY_SET;

            let peta = KOLOM_URAIAN.get(baris);
            if (peta) return peta;

            const maks = {};
            baris.forEach((row) => (row || []).forEach((p, pi) => {
                const k = p.label || `#${pi}`;
                const n = p.berkas ? 0 : String(p.nilai ?? '').length;
                maks[k] = Math.max(maks[k] ?? 0, n);
            }));

            peta = new Set(Object.keys(maks).filter((k) => maks[k] > 45));
            KOLOM_URAIAN.set(baris, peta);

            return peta;
        },
        /** Sel ini termasuk kolom uraian? */
        selUraian(baris, p, pi) {
            return this.kolomUraian(baris).has(p.label || `#${pi}`);
        },
        /**
         * Kolom mana yang jadi JUDUL kartu riwayat: sel pertama yang bukan
         * uraian dan bukan berkas. Pada riwayat kerja/organisasi/sertifikasi
         * kolom itu selalu "nama tempatnya" — yang dicari mata lebih dulu.
         */
        selBarisLead(baris, row) {
            return (row || []).find((p, pi) => !this.selUraian(baris, p, pi) && !this.berkasSel(p).length) || null;
        },
        /**
         * Kolom PERIODE satu riwayat — naik jadi pil di pojok kanan kartu.
         *
         * Diputus SEKALI untuk seluruh riwayat, disiplin yang sama dengan
         * kolomUraian(): kalau diputus per baris, pil periode muncul di baris
         * ini lalu hilang di baris berikutnya hanya karena isinya kebetulan
         * kosong — dan mata kehilangan tempat tetap untuk mencarinya.
         *
         * Kolom judul sengaja dilewati: pada riwayat pendidikan kolom pertama
         * kerap "Tahun Lulus", dan menaikkannya jadi pil menyisakan kartu tanpa
         * judul sama sekali.
         */
        kolomPeriode(baris) {
            if (!Array.isArray(baris) || !baris.length) return null;

            let kunci = KOLOM_PERIODE.get(baris);
            if (kunci !== undefined) return kunci;

            kunci = null;
            const contoh = baris.find((r) => Array.isArray(r) && r.length) || [];
            let lewatiJudul = true;
            for (let i = 0; i < contoh.length; i++) {
                const p = contoh[i];
                if (this.selUraian(baris, p, i) || this.berkasSel(p).length) continue;
                if (lewatiJudul) { lewatiJudul = false; continue; }
                if (/(periode|tahun|masa|tanggal|durasi|tgl)/i.test(p.label || '')) { kunci = p.label || `#${i}`; break; }
            }
            KOLOM_PERIODE.set(baris, kunci);

            return kunci;
        },
        /** Nilai pil periode baris ini — kosong bila kolomnya tak ada/tak terisi. */
        selBarisPeriode(baris, row) {
            const kol = this.kolomPeriode(baris);
            if (!kol) return '';
            const lead = this.selBarisLead(baris, row);
            const p = (row || []).find((x, xi) => (x.label || `#${xi}`) === kol && x !== lead);

            return p && String(p.nilai ?? '').trim() ? this.nilaiTampil(p) : '';
        },
        /** Sel ini sudah dipakai judul atau pil periode? Kalau ya, jangan diulang di kisi. */
        selSudahDipakai(baris, row, p, pi) {
            if (p === this.selBarisLead(baris, row)) return true;
            const kol = this.kolomPeriode(baris);

            return !!kol && (p.label || `#${pi}`) === kol && !!this.selBarisPeriode(baris, row);
        },
        /** Baris ini punya isian ringkas? Bila tidak, kisinya tidak dirender —
         *  kisi kosong menyisakan garis pemisah yang menggantung tanpa isi.
         *  Sub-isian berkas TIDAK dihitung: ia punya jalurnya sendiri di dasar
         *  kartu, jadi baris yang isinya cuma sertifikat + uraian akan menggambar
         *  kisi kosong kalau berkasnya ikut dihitung di sini. */
        adaRingkas(baris, row) {
            return (row || []).some((p, pi) => !this.selUraian(baris, p, pi)
                && !this.berkasSel(p).length
                && !this.selSudahDipakai(baris, row, p, pi));
        },
        /**
         * SELURUH berkas satu sub-isian.
         *
         * `berkasList` bentuk baru (bisa banyak lembar); `berkas` bentuk lama
         * yang tetap dikirim server. Keduanya dibaca supaya muatan yang sempat
         * ter-cache sebelum pembaruan server tidak kehilangan lampirannya.
         */
        berkasSel(p) {
            if (p?.berkasList?.length) return p.berkasList;

            return p?.berkas ? [p.berkas] : [];
        },
        /**
         * Isian formulir yang BUKAN dokumen — bagian biodata di akordion.
         *
         * Dokumen dipisah ke seksinya sendiri di bawah karena keduanya dibaca
         * dengan cara berbeda: isian dipindai berjajar (label kiri, nilai kanan),
         * dokumen ditekan satu per satu. Mencampurnya membuat satu kisi yang
         * separuh selnya berisi teks dan separuh lagi berisi tombol.
         */
        isianData(f) {
            return (f.jawaban || []).filter((j) => !this.isianBerkas(j));
        },
        /**
         * Isian biodata DIKELOMPOKKAN seperti saat kandidat mengisinya.
         *
         * Tiga puluh kotak label-nilai berderet tanpa jeda bukan cuma tidak
         * rapi — hubungan antar jawaban ikut hilang. "Kesesuaian Data: Sesuai"
         * hanya punya arti di sebelah nama, email, dan WA yang divalidasinya.
         * Kelompoknya datang dari skema formulir (langkah + ikonnya), jadi
         * peninjau membaca susunan yang sama dengan yang diisi kandidat.
         *
         * URUTANNYA JUGA DARI SKEMA — kelompok menurut nomor langkah, isi tiap
         * kelompok menurut nomor field. Bentuk sebelumnya mengikuti urutan kunci
         * di `Jawaban_Json`, yaitu urutan PENYIMPANAN: tidak pernah dijanjikan
         * sama dengan urutan pertanyaan, dan pada praktiknya memang berbeda —
         * jawaban langkah 3 bisa muncul di atas jawaban langkah 1. Sekarang
         * halaman ini terbaca lurus dari atas ke bawah seperti formulirnya.
         *
         * Riwayat berulang duduk di posisi aslinya juga (lewat grupBagian),
         * bukan diseret ke akhir: ia memang mengambil lebar penuh, tapi kisi
         * membiarkannya mengambil barisnya sendiri tanpa memutus urutan baca.
         */
        grupIsian(f) {
            const peta = this.petaSkema(f);
            const grup = new Map();
            const ambil = (kunci, judul, ikon, urutan) => {
                if (!grup.has(kunci)) grup.set(kunci, { kunci, judul, ikon, urutan, items: [] });

                return grup.get(kunci);
            };
            // Yang tak dikenal skema dijaga urutan aslinya, di belakang yang
            // dikenal — bukan diselipkan di tengah dengan posisi tebakan.
            let sisa = 0;

            this.isianData(f).forEach((j) => {
                const g = peta.grup[j.key] || peta.bagian[j.key];
                if (g && g.judul) {
                    ambil(g.judul, g.judul, g.ikon, g.urutan).items.push({ ...j, _pos: g.posisi });
                } else {
                    // Formulir versi lama / field yang sudah dihapus dari skema.
                    // Tetap ditampilkan — menyembunyikannya berarti jawaban yang
                    // pernah diberikan kandidat lenyap tanpa ada yang tahu.
                    ambil('__lain', 'Data Lainnya', 'bi-person-lines-fill', 9999)
                        .items.push({ ...j, _pos: 100000 + sisa++ });
                }
            });

            const hasil = [...grup.values()].sort((a, b) => a.urutan - b.urutan);
            hasil.forEach((g) => g.items.sort((a, b) => a._pos - b._pos));

            // Formulir yang skemanya tak dikenal sama sekali (versi lama, formulir
            // dinamis tanpa snapshot) menghasilkan SATU kelompok penampung.
            // Menyebutnya "Data Lainnya" saat tak ada kelompok lain terbaca
            // seperti ada bagian utama yang hilang — padahal inilah bagian
            // utamanya.
            if (hasil.length === 1 && hasil[0].kunci === '__lain') hasil[0].judul = 'Data Isian';

            return hasil;
        },
        /**
         * Dokumen satu formulir — seksi "Dokumen Terlampir".
         *
         * Pertanyaan dokumen yang KOSONG tetap ikut, sebagai kartu redup. Justru
         * kekosongan itu yang perlu terlihat: daftar yang hanya memuat berkas
         * yang ada tidak pernah bisa menjawab "apa yang belum diunggah?".
         *
         * Lampiran di dalam baris berulang (sertifikat pada riwayat) TIDAK ikut —
         * ia sudah punya tempatnya di kartu riwayatnya, dan mencabutnya ke sini
         * memisahkan sertifikat dari nama pelatihannya.
         *
         * Urutannya juga mengikuti formulir, dengan alasan yang sama seperti
         * grupIsian(): verifikator mencocokkan daftar ini dengan syarat yang
         * dibacanya di pengumuman, dan syarat itu ditulis berurutan.
         */
        dokFormulir(f) {
            const peta = this.petaSkema(f);
            const keluar = [];
            (f.jawaban || []).forEach((j) => {
                if (!this.isianBerkas(j)) return;
                const pos = peta.grup[j.key]?.posisi ?? 100000;
                const label = this.labelIsian(f, j);
                const berkas = this.berkasSel(j);
                if (!berkas.length) { keluar.push({ id: `${f.no}-${j.key}`, nama: label, berkas: null, pos }); return; }
                berkas.forEach((b, i) => keluar.push({
                    id: `${f.no}-${j.key}-${i}`,
                    nama: berkas.length > 1 ? `${label} (${i + 1})` : label,
                    berkas: b,
                    pos: pos + i / 100,
                }));
            });
            // Berkas tanpa pertanyaan (foto verifikasi) tak punya tempat di
            // skema — ia memang bukan jawaban isian mana pun. Ditaruh di akhir.
            this.berkasLepas(f).forEach((b, i) => keluar.push({
                id: `${f.no}-x-${b.field}`,
                nama: this.labelBerkas(b),
                berkas: b,
                pos: 200000 + i,
            }));

            return keluar.sort((a, b) => a.pos - b.pos);
        },
        /** Pil ringkasan di puncak akordion: berapa data, berapa dokumen lengkap. */
        ringkasFormulir(f) {
            const isi = this.isianData(f).filter((j) => String(j.nilai ?? '').trim() !== '' || (j.baris || []).length).length;
            const dok = this.dokFormulir(f);
            const ada = dok.filter((d) => d.berkas).length;
            const bagian = [`${isi} data`];
            // "6/6", bukan "6": yang perlu terbaca sekilas bukan berapa yang
            // masuk melainkan apakah semuanya sudah masuk.
            if (dok.length) bagian.push(`${ada}/${dok.length} dokumen`);
            if (f.waktuKirim) bagian.push(`dikirim ${this.tglId(f.waktuKirim)}`);

            return bagian.join(' · ');
        },
        /**
         * Nilai ini dibaca DIGIT PER DIGIT, bukan sebagai kata?
         *
         * NIK, nomor telepon, dan email dicocokkan orang karakter demi karakter
         * saat memverifikasi berkas. Huruf proporsional membuat "1607 1111 2222
         * 0003" dan "1607 1111 2222 0008" nyaris kembar; huruf lebar-tetap
         * membuat selisihnya jatuh di kolom yang sama dan langsung terlihat.
         */
        selMono(form, isian) {
            const tipe = this.petaSkema(form).tipe[isian.key] || '';
            if (['email', 'telepon', 'phone', 'nik', 'nomor', 'number'].includes(tipe)) return true;

            const teks = String(isian.nilai ?? '');
            if (!teks) return false;

            // Cadangan untuk formulir yang tipenya tak tercatat di skema:
            // alamat surel, atau deret yang isinya didominasi angka & pemisah.
            return /@/.test(teks) || (/^[\d\s+().-]{8,}$/.test(teks));
        },
        /** Tutup / tampilkan lagi keterangan "tahap ini belum tuntas". */
        tutupNota(tutup) {
            const id = this.detailKandidat?.id;
            if (!id) return;
            this.notaDitutup = { ...this.notaDitutup, [id]: tutup };
        },
        /** Ganti cara membaca berkas ('daftar' | 'berkas') dan ingat pilihannya. */
        setBerkasMode(m) {
            this.berkasMode = m;
            try { localStorage.setItem('plw.berkasMode', m); } catch (e) { /* mode privat */ }
        },
        /**
         * Ikon petak berkas — dari jenis berkasnya, bukan dari namanya.
         * Gambar, PDF, lembar kerja, dan dokumen kata punya cara dibuka yang
         * berbeda, dan itulah yang perlu terbaca sebelum diklik.
         */
        ikonBerkas(b) {
            if (b.isImage) return 'bi-file-earmark-image-fill';
            const e = String(b.ext || '').toLowerCase();
            if (e === 'pdf') return 'bi-file-earmark-pdf-fill';
            if (['xls', 'xlsx', 'csv'].includes(e)) return 'bi-file-earmark-spreadsheet-fill';
            if (['doc', 'docx'].includes(e)) return 'bi-file-earmark-word-fill';

            return 'bi-file-earmark-fill';
        },
        /**
         * Ikon kepala riwayat — ditebak dari nama isiannya.
         *
         * Sekadar tebakan, dan memang cukup: yang salah tebak jatuh ke ikon
         * daftar netral, bukan ke ikon yang keliru artinya. Mengikatnya ke
         * master akan menuntut satu kolom baru hanya demi hiasan.
         */
        ikonRiwayat(isian) {
            const k = `${isian.key || ''} ${isian.label || ''}`.toLowerCase();
            if (/sertifik|pelatihan|training|lisensi|piagam/.test(k)) return 'bi-patch-check-fill';
            if (/organisasi|kepanitiaan|komunitas/.test(k)) return 'bi-people-fill';
            if (/kerja|pengalaman|magang|intern|karier|karir/.test(k)) return 'bi-briefcase-fill';
            if (/pendidikan|sekolah|kuliah|kampus|studi/.test(k)) return 'bi-mortarboard-fill';
            if (/prestasi|penghargaan|award/.test(k)) return 'bi-trophy-fill';
            if (/keluarga|kerabat|darurat/.test(k)) return 'bi-house-heart-fill';

            return 'bi-list-stars';
        },

        /* ── Nominal rupiah ────────────────────────────────────────────── */

        /** "9500000" → "Rp 9.500.000". Yang bukan angka dikembalikan apa adanya. */
        rupiah(v) {
            const angka = String(v ?? '').replace(/\D/g, '');
            if (!angka) return String(v ?? '');

            return 'Rp ' + angka.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        },
        /**
         * Isian ini nominal RUPIAH?
         *
         * DUA sumber, dan keduanya memang dibutuhkan:
         *   1. tipe `currency` di skema — sumber yang benar; dan
         *   2. tebakan dari nama isiannya, karena skema yang sudah dipakai
         *      menulis ekspektasi gaji sebagai `number` biasa. Menunggu seluruh
         *      skema lama diperbaiki dulu berarti nominal gaji tetap tampil
         *      telanjang di layar sampai entah kapan.
         *
         * Tebakan hanya berlaku bila jawabannya memang angka bulat, jadi isian
         * bernama "gaji" yang jawabannya kalimat tidak ikut tersulap.
         */
        isianRupiah(form, isian) {
            if (this.petaSkema(form).tipe[isian.key] === 'currency') return true;

            return this.namaUang(isian.key, this.labelIsian(form, isian)) && this.angkaBulat(isian.nilai);
        },
        /** Sel linimasa hanya punya label — tipenya tidak ikut sampai ke sini. */
        selRupiah(p) {
            return !p.berkas && this.namaUang(p.label) && this.angkaBulat(p.nilai);
        },
        namaUang(...teks) {
            return /(gaji|upah|salary|penghasilan|tunjangan|nominal|biaya|honor|rp\b|rupiah)/i.test(teks.filter(Boolean).join(' '));
        },
        angkaBulat(v) {
            return /^\s*\d{4,}\s*$/.test(String(v ?? ''));
        },
        /** Nilai satu isian siap tampil (rupiah bila memang nominal). */
        nilaiIsian(form, isian) {
            if (!isian.nilai && isian.nilai !== 0) return '—';

            return this.isianRupiah(form, isian) ? this.rupiah(isian.nilai) : isian.nilai;
        },
        /** Nilai satu sel linimasa siap tampil. */
        nilaiTampil(p) {
            if (!p.nilai && p.nilai !== 0) return '—';

            return this.selRupiah(p) ? this.rupiah(p.nilai) : p.nilai;
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
            // Selalu kembali ke Rapor Tes. Tab terakhir yang dibuka milik
            // KANDIDAT SEBELUMNYA; membawanya ke kandidat berikutnya berarti
            // jendela terbuka di "Berkas & Biodata" yang masih memuat, dan
            // alasan orang ini dibuka — hasil tesnya — justru tak terlihat.
            this.tabAktif = 'rapor';
            this.openForm = 0;
            // Folder, pencarian, dan sorotan file manager milik kandidat tadi.
            // Yang tersisa akan menyaring berkas orang lain memakai kata kunci
            // yang tak pernah diketikkan untuknya — dan tampak seperti berkasnya
            // hilang. Modenya TIDAK direset: itu preferensi peninjau.
            this.fmFolder = '';
            this.fmCari = '';
            this.fmSorot = '';
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
            // Nilainya sudah masuk; yang ditunggu ORANG DI KANTOR INI, bukan HCLearn.
            if (t.butuhKeputusan) return 'Sudah dites — tunggu keputusan';
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
        /** Nama akun berbeda dari nama formulir? Perbandingan tak peka huruf besar. */
        namaAkunBeda(r) {
            const a = (r?.pelamarAkun || '').trim().toLowerCase();
            const f = (r?.pelamar || '').trim().toLowerCase();

            return !!a && !!f && a !== f;
        },
        bisaCatatKehadiran(t) { return !t.butuhKehadiran; },
        bisaCatat(t) {
            return this.detailKandidat?.statusLamaran === 'BERJALAN' && !!t.dapatDicatat;
        },
        /**
         * Sepasang tombol Lulus / Tidak Lulus pada ujian online INFORMATIF.
         *
         * Server yang menentukan (`dapatPutusTes` di rapotTes): ujian sudah
         * dikerjakan, nilainya sudah masuk, dan perannya memang menyerahkan
         * verdict ke penilai.
         */
        bisaPutusTes(t) {
            return this.detailKandidat?.statusLamaran === 'BERJALAN' && !!t.dapatPutusTes;
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

        /* ── TAHAN / LANJUTKAN MASSAL ──────────────────────────────────────── */
        /** Alasan ini menuntut keterangan? Dibaca dari master, bukan didaftar. */
        alasanButuhCatatan(kode) {
            return !!this.alasanHold.find((a) => a.value === kode)?.butuhCatatan;
        },
        /** Apa yang kurang dari satu baris — dipakai penanda merah & tombol simpan. */
        /** Kekurangan satu baris keputusan massal — dipakai menandai barisnya sendiri. */
        barisPmKurang(b) {
            if (!b?.hasil) return 'Keputusan belum dipilih';
            const def = this.hasilKeputusan.find((h) => h.kode === b.hasil);
            // Isinya kini HTML. `<p><br></p>` — yang ditinggalkan Quill pada
            // editor kosong — panjangnya 11 huruf dan akan lolos dari uji
            // "sudah diisi" kalau dinilai apa adanya.
            if (def?.butuhAlasan && !teksDariHtml(b.catatanHtml).trim()) return 'Alasan wajib';

            return '';
        },
        async askPutusMassal() {
            if (!this.bolehPutus) {
                return this.notice('Anda tidak punya hak akses untuk mengambil keputusan.', true);
            }
            const bisa = this.bisaPutusMassal;
            if (!bisa.length) return;

            this.pmTarget = bisa;
            // Yang tercentang tapi tak bisa diproses — disebut di modal supaya
            // admin tahu SEBELUM menyimpan, bukan sesudah.
            this.pmDilewati = this.barisTerpilih.filter((r) => !bisa.includes(r));
            this.pmPola = 'SERAGAM';
            this.pmHasil = '';
            this.pmCatatanHtml = '';
            this.pmBaris = bisa.map((r) => ({
                id: r.id,
                tahapId: r.tahapId,
                pelamar: r.pelamar,
                posisi: r.posisi,
                tahap: r.tahap,
                hasil: '',
                catatanHtml: '',
            }));
            this.pmShow = true;
        },
        /** Salin keputusan & alasan baris pertama ke seluruh baris. */
        sebarPutusBarisPertama() {
            const a = this.pmBaris[0];
            if (!a?.hasil) return;
            this.pmBaris = this.pmBaris.map((b, i) => (i === 0 ? b : { ...b, hasil: a.hasil, catatanHtml: a.catatanHtml }));
            const nama = this.hasilKeputusan.find((x) => x.kode === a.hasil)?.nama || a.hasil;
            this.notice(`Keputusan "${nama}" disalin ke ${this.pmBaris.length - 1} baris lain.`);
        },
        async konfirmPutusMassal() {
            if (this.sibuk || !this.pmTarget.length) return;
            this.sibuk = true;
            try {
                // SATU BENTUK KIRIMAN untuk kedua mode — mode seragam hanya
                // mengisi keputusan yang sama ke tiap item di sini. Server tidak
                // perlu tahu mode mana yang dipakai, jadi tidak ada aturan kedua
                // yang harus dijaga tetap sama dengan yang pertama.
                const item = this.pmTarget.map((r) => {
                    if (this.pmPola === 'SERAGAM') {
                        return {
                            tahapId: r.tahapId,
                            hasil: this.pmHasil,
                            catatan: teksDariHtml(this.pmCatatanHtml) || null,
                            catatanHtml: this.pmCatatanHtml || null,
                        };
                    }
                    const b = this.pmBaris.find((x) => x.tahapId === r.tahapId);

                    // Bentuk kirimannya SAMA dengan mode seragam: teks polos
                    // untuk yang membaca ringkas, HTML untuk yang menampilkan
                    // utuh. Kalau mode ini hanya mengirim teks polos, catatan
                    // yang lahir dari dua mode berbeda akan tampil berbeda di
                    // rapor tahap yang sama.
                    return {
                        tahapId: r.tahapId,
                        hasil: b?.hasil,
                        catatan: teksDariHtml(b?.catatanHtml) || null,
                        catatanHtml: b?.catatanHtml || null,
                    };
                });

                const res = await axios.patch('/api/v1/karir/lamaran/tahap/putus-massal', { item }, CFG);

                const gagal = res.data?.result?.gagal || [];
                this.notice(res.data?.message || 'Selesai.', gagal.length > 0);
                // Yang gagal disebut NAMANYA. Pada daftar dua puluh orang,
                // "3 dilewati" tanpa nama memaksa admin mencocokkan sendiri.
                if (gagal.length) {
                    gagal.slice(0, 5).forEach((g) => this.notice(`✗ ${g.nama || g.tahapId} — ${g.pesan}`, true));
                }
                this.pmShow = false;
                this.terpilih = [];
                this.tutupPilihKolom();
                this.muatDetail(this.selectedId);
                this.muatProgram();
            } catch (e) {
                const gagal = e.response?.data?.result?.gagal || [];
                const rinci = gagal.length
                    ? ` (${gagal.slice(0, 3).map((g) => g.nama || '?').join(', ')}${gagal.length > 3 ? ', …' : ''})`
                    : '';
                this.notice((e.response?.data?.message || 'Gagal memproses keputusan.') + rinci, true);
            } finally {
                this.sibuk = false;
            }
        },
        barisHmKurang(b) {
            if (!b?.alasan) return 'Alasan belum dipilih';
            // Lihat barisPmKurang(): editor kosong tetap menyisakan markup.
            if (this.alasanButuhCatatan(b.alasan) && !teksDariHtml(b.catatanHtml).trim()) {
                return 'Keterangan wajib';
            }

            return '';
        },
        async askHoldMassal(menahan) {
            const bisa = menahan ? this.bisaTahanMassal : this.bisaLepasMassal;
            if (!bisa.length) return;

            this.hmNilai = menahan;
            this.hmTarget = bisa;
            // Yang tercentang tapi tak bisa diproses — disebut di modal supaya
            // admin tahu sebelum menyimpan, bukan sesudah.
            this.hmDilewati = this.barisTerpilih.filter((r) => !bisa.includes(r));
            this.hmPola = 'SERAGAM';
            this.hmAlasan = '';
            this.hmCatatanHtml = '';
            this.hmBaris = bisa.map((r) => ({
                id: r.id,
                tahapId: r.tahapId,
                pelamar: r.pelamar,
                posisi: r.posisi,
                tahap: r.tahap,
                alasan: '',
                catatanHtml: '',
            }));
            if (menahan) await this.muatAlasanHold();
            this.hmShow = true;
        },
        /** Salin alasan & keterangan baris pertama ke seluruh baris. */
        sebarBarisPertama() {
            const a = this.hmBaris[0];
            if (!a?.alasan) return;
            this.hmBaris = this.hmBaris.map((b, i) => (i === 0 ? b : { ...b, alasan: a.alasan, catatanHtml: a.catatanHtml }));
            this.notice(`Alasan "${this.alasanHold.find((x) => x.value === a.alasan)?.nama || a.alasan}" disalin ke ${this.hmBaris.length - 1} baris lain.`);
        },
        async konfirmHoldMassal() {
            if (this.sibuk || !this.hmTarget.length) return;
            this.sibuk = true;
            try {
                // SATU BENTUK KIRIMAN untuk kedua mode. Mode seragam hanya
                // mengisi alasan yang sama ke tiap item di sini — server tidak
                // perlu tahu mode mana yang dipakai, jadi tidak ada aturan
                // kedua yang harus dijaga tetap sama dengan yang pertama.
                const item = this.hmTarget.map((r) => {
                    const b = this.hmBaris.find((x) => x.tahapId === r.tahapId);
                    if (!this.hmNilai) {
                        return { tahapId: r.tahapId, catatanHtml: this.hmCatatanHtml || null };
                    }
                    if (this.hmPola === 'SERAGAM') {
                        return {
                            tahapId: r.tahapId,
                            alasanKode: this.hmAlasan,
                            catatan: teksDariHtml(this.hmCatatanHtml) || null,
                            catatanHtml: this.hmCatatanHtml || null,
                        };
                    }

                    return {
                        tahapId: r.tahapId,
                        alasanKode: b?.alasan || null,
                        catatan: teksDariHtml(b?.catatanHtml) || null,
                        catatanHtml: b?.catatanHtml || null,
                    };
                });

                const res = await axios.patch('/api/v1/karir/lamaran/tahap/hold-massal', {
                    hold: this.hmNilai,
                    item,
                }, CFG);

                const gagal = res.data?.result?.gagal || [];
                this.notice(res.data?.message || 'Selesai.', gagal.length > 0);
                this.hmShow = false;
                this.terpilih = [];
                this.tutupPilihKolom();
                this.muatDetail(this.selectedId);
                this.muatProgram();
            } catch (e) {
                // Kegagalan menyeluruh membawa daftar siapa & kenapa — jauh
                // lebih berguna daripada satu kalimat untuk dua puluh orang.
                const gagal = e.response?.data?.result?.gagal || [];
                const rinci = gagal.length
                    ? ` (${gagal.slice(0, 3).map((g) => g.nama || '?').join(', ')}${gagal.length > 3 ? ', …' : ''})`
                    : '';
                this.notice((e.response?.data?.message || 'Gagal memproses penahanan.') + rinci, true);
            } finally {
                this.sibuk = false;
            }
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
            this.laporanGagal = '';
            this.laporanOpsi = [];
            this.laporanFormulir = [];
            this.laporanMuat = true;
            this.laporanShow = true;

            try {
                const res = await axios.get(`/api/v1/karir/lamaran/${this.detailKandidat.id}/laporan/opsi`, CFG);
                this.laporanOpsi = res.data?.result?.formulir || [];
                // Bawaan: yang TERBARU. Itulah yang hampir selalu dimaksud
                // "cetak datanya" — jawaban lama sudah digantikan kandidat sendiri.
                this.laporanFormulir = this.laporanOpsi.filter((f) => f.utama).map((f) => f.id);
            } catch (e) {
                this.laporanGagal = e.response?.data?.message || 'Gagal memuat daftar formulir.';
            } finally {
                // `finally`, bukan di ujung `try`: bila permintaannya gagal,
                // penanda yang tak pernah dimatikan membuat tombolnya terkunci
                // selamanya dan modalnya hanya bisa ditutup.
                this.laporanMuat = false;
            }
        },
        /**
         * Minta laporan → modal MENUTUP → kemajuannya pindah ke pojok.
         *
         * Modal tidak lagi menunggui pekerjaannya. Membuat laporan berjalan di
         * antrean dan bisa memakan puluhan detik; menahan admin di dalam jendela
         * selama itu membuat seluruh halaman tak bisa dipakai untuk hal lain.
         */
        async konfirmLaporan() {
            if (!this.detailKandidat?.id) return;
            this.laporanGagal = '';

            const format = this.laporanFormat;
            const nama = `${this.detailKandidat.pelamar || 'Kandidat'} — ${this.detailKandidat.lamaranKode || ''}`.trim();
            const unduh = this.mulaiUnduhan(nama, format);

            this.laporanShow = false;

            try {
                const res = await axios.post(`/api/v1/karir/lamaran/${this.detailKandidat.id}/laporan`, {
                    format,
                    formulir: this.laporanFormulir,
                }, CFG);
                this.pantauLaporan(unduh, res.data?.result?.id);
            } catch (e) {
                this.gagalkanUnduhan(unduh, e.response?.data?.message || 'Gagal meminta laporan.');
            }
        },

        /** Baris baru di panel unduhan. Mengembalikan objeknya, bukan indeksnya:
         *  indeks bergeser begitu ada baris lain yang ditutup. */
        mulaiUnduhan(nama, format) {
            const u = {
                id: `u${Date.now()}${this.unduhan.length}`,
                nama,
                format,
                // DUA ANGKA, BUKAN SATU.
                //
                // `target` adalah kebenaran yang datang dari server; `persen`
                // adalah yang dilihat mata dan selalu MENGEJAR target, tidak
                // pernah melompat ke sana. Bilah yang melompat 0 → 100 saat
                // jawaban tiba terbaca seperti kerusakan, dan bilah yang diam
                // lama lalu melompat justru membuat orang menekan tombolnya
                // lagi. Yang meyakinkan adalah gerak yang tak pernah berhenti.
                persen: 0,
                target: 0,
                keadaan: 'siap',       // siap → unduh → selesai | gagal
                pesan: 'Menyiapkan berkas…',
                // Berkas sudah benar-benar tersimpan; tinggal menunggu cincinnya
                // sampai di 100 supaya perpindahan ke "Selesai" tidak mendahului
                // animasinya.
                tuntas: false,
                timer: null,
            };
            this.unduhan.push(u);
            this.jalankanAnimasi();

            return u;
        },

        gagalkanUnduhan(u, pesan) {
            clearTimeout(u.timer);
            u.keadaan = 'gagal';
            u.pesan = pesan;
            u.persen = 0;
            u.target = 0;
        },

        /**
         * Satu gelung animasi untuk SELURUH baris — bukan satu per baris.
         *
         * Tiap bingkai, angka yang tampil mendekat ke targetnya sebesar
         * sebagian dari selisihnya: cepat saat jauh, melambat saat mendekat.
         * Itu sebabnya lompatan 90 → 100 terbaca sebagai "mengejar", bukan
         * sebagai kedipan.
         *
         * Gelungnya berhenti sendiri begitu tak ada lagi yang perlu digerakkan
         * — rAF yang berjalan selamanya membuat tab ini terus membangunkan CPU
         * meski tak ada unduhan sama sekali.
         */
        jalankanAnimasi() {
            if (this.unduhanRaf) return;

            const langkah = () => {
                let hidup = false;

                for (const u of this.unduhan) {
                    const selisih = u.target - u.persen;
                    if (selisih > 0.05) {
                        // Minimal 0,25 supaya sisa terakhir tidak merayap
                        // selamanya karena selisihnya mengecil terus.
                        u.persen = Math.min(u.target, u.persen + Math.max(0.25, selisih * 0.11));
                        hidup = true;
                    } else if (selisih > 0) {
                        u.persen = u.target;
                    }

                    // Label berganti SETELAH cincinnya penuh — bukan sebelum.
                    if (u.tuntas && u.keadaan !== 'selesai' && u.persen >= 99.5) {
                        u.keadaan = 'selesai';
                        u.pesan = u.pesanSelesai || 'Tersimpan';
                    }
                    if (u.tuntas && u.keadaan !== 'selesai') hidup = true;
                }

                this.unduhanRaf = hidup ? requestAnimationFrame(langkah) : null;
            };

            this.unduhanRaf = requestAnimationFrame(langkah);
        },

        /** Angka bulat untuk ditampilkan — nilainya sendiri disimpan pecahan. */
        bulat(n) { return Math.min(100, Math.round(n || 0)); },

        /**
         * Panjang busur cincin. Memakai nilai PECAHAN, bukan yang sudah
         * dibulatkan: pembulatan membuat lingkarannya bergerak melangkah satu
         * persen sekali — dan gerak melangkah persis yang ingin dihindari.
         */
        busur(n) {
            const KELILING = 97.4;   // 2πr, r = 15.5

            return `${(Math.min(100, Math.max(0, n || 0)) / 100) * KELILING} ${KELILING}`;
        },

        /** Tutup panel — hanya membuang tampilannya, berkas yang sudah turun tetap ada. */
        bersihkanUnduhan() {
            this.unduhan.forEach((u) => clearTimeout(u.timer));
            this.unduhan = [];
            if (this.unduhanRaf) {
                cancelAnimationFrame(this.unduhanRaf);
                this.unduhanRaf = null;
            }
        },

        /**
         * Tanya berkala sampai laporannya jadi, lalu unduh sendiri.
         *
         * Berhenti setelah ~2 menit: antrean yang mati membuat status DIPROSES
         * bertahan selamanya, dan lingkaran berputar tanpa akhir lebih
         * membingungkan daripada pesan gagal yang jujur.
         *
         * KEMAJUAN TAHAP PENYIAPAN DISIMULASIKAN sampai 90%, dan itu disengaja:
         * server tidak tahu berapa persen sebuah PDF "sudah jadi", dan mengarang
         * angka yang melompat ke 100 lalu diam justru lebih menyesatkan daripada
         * bilah yang merambat pelan. Sisa 10% diisi unduhan yang persentasenya
         * BENAR — dihitung dari byte yang sudah turun.
         */
        pantauLaporan(u, id) {
            if (!id) {
                this.gagalkanUnduhan(u, 'Permintaan tidak dikenali.');

                return;
            }

            u.keadaan = 'siap';
            u.pesan = 'Menyiapkan berkas…';

            // ── ANGGARAN WAKTU: 4 MENIT ────────────────────────────────────
            //
            // Diukur dari riwayat nyata di N_WEB_CAREERS_Export_Log: laporan
            // memakan 17–97 detik, dan yang terlama terjadi saat worker baru
            // bangun. Batas sebelumnya 80 detik — lebih pendek daripada
            // pekerjaan yang memang normal, sehingga panelnya menyerah pada
            // berkas yang sebentar lagi jadi dan admin mengulang permintaan
            // yang sebenarnya masih hidup.
            //
            // Jarak tanyanya melebar seiring waktu: cepat di awal supaya yang
            // ringan terasa seketika, melambat kemudian supaya menunggu tiga
            // menit tidak berarti 150 permintaan.
            const MULAI = Date.now();
            const BATAS = 4 * 60 * 1000;
            const jeda = (lewat) => (lewat < 15000 ? 1200 : lewat < 45000 ? 2500 : 5000);

            const tanya = async () => {
                const lewat = Date.now() - MULAI;

                // Yang dinaikkan TARGET-nya; yang tampil mengejarnya sendiri.
                // Merambat melambat mendekati 90 — memberi kesan bergerak tanpa
                // pernah berjanji hampir selesai. Angka 90 disengaja: sisanya
                // milik unduhan yang persentasenya benar-benar terukur, jadi
                // bilah ini tak pernah sampai penuh atas dasar tebakan.
                u.target = Math.min(90, u.target + Math.max(1.2, (90 - u.target) / 9));
                this.jalankanAnimasi();

                if (lewat > BATAS) {
                    this.gagalkanUnduhan(u, 'Belum selesai setelah 4 menit — periksa worker antrean, lalu coba lagi.');

                    return;
                }

                try {
                    const { data } = await axios.get(`/api/v1/karir/lamaran/laporan/${id}`, CFG);
                    const r = data?.result || {};

                    if (r.selesai) {
                        await this.tarikBerkas(u, id);

                        return;
                    }
                    if (r.gagal) {
                        this.gagalkanUnduhan(u, r.pesan || 'Laporan gagal dibuat.');

                        return;
                    }
                    // Menunggu lama bukan kerusakan — tapi diam tanpa kabar
                    // membuatnya terasa begitu. Kalimatnya berubah supaya
                    // terlihat masih hidup.
                    if (lewat > 30000) {
                        u.pesan = `Masih diproses… ${Math.round(lewat / 1000)} detik`;
                    }
                } catch (e) {
                    this.gagalkanUnduhan(u, 'Gagal memeriksa status laporan.');

                    return;
                }

                u.timer = setTimeout(tanya, jeda(lewat));
            };

            clearTimeout(u.timer);
            u.timer = setTimeout(tanya, 900);
        },

        /**
         * Tarik berkasnya sebagai blob lalu SIMPAN SENDIRI.
         *
         * Bukan membuka tautan di tab baru: tab yang terbuka lalu menutup
         * sendiri terlihat seperti kedipan tak jelas, dan pemblokir pop-up
         * kerap menahannya tanpa memberi tahu siapa pun. Dengan blob,
         * persentasenya nyata dan berkasnya benar-benar tersimpan.
         */
        async tarikBerkas(u, id) {
            u.keadaan = 'unduh';
            u.target = Math.max(u.target, 90);
            u.pesan = 'Mengunduh…';
            this.jalankanAnimasi();

            try {
                const res = await axios.get(`/api/v1/karir/lamaran/laporan/${id}/unduh`, {
                    ...CFG,
                    responseType: 'blob',
                    onDownloadProgress: (e) => {
                        if (!e.total) return;
                        // 90–100%: penyiapan sudah memakai 0–90.
                        u.target = 90 + (e.loaded / e.total) * 10;
                        this.jalankanAnimasi();
                    },
                });

                const nama = this.namaBerkas(res, u);
                const url = URL.createObjectURL(res.data);
                const a = document.createElement('a');
                a.href = url;
                a.download = nama;
                document.body.appendChild(a);
                a.click();
                a.remove();
                // Dilepas setelah peramban sempat memulai unduhan; mencabutnya
                // seketika membatalkan berkas yang baru saja diklik.
                setTimeout(() => URL.revokeObjectURL(url), 60000);

                // Berkasnya SUDAH tersimpan. Yang ditunda hanya labelnya —
                // sampai cincinnya benar-benar penuh, supaya "Selesai" tidak
                // muncul di atas lingkaran yang masih separuh.
                u.target = 100;
                u.tuntas = true;
                u.pesanSelesai = `Tersimpan · ${nama}`;
                this.jalankanAnimasi();
            } catch (e) {
                this.gagalkanUnduhan(u, 'Berkas gagal diunduh.');
            }
        },

        /** Nama berkas dari header server; kalau tak ada, disusun sendiri. */
        namaBerkas(res, u) {
            const cd = res.headers?.['content-disposition'] || '';
            const m = /filename\*?=(?:UTF-8'')?"?([^";]+)"?/i.exec(cd);
            if (m) return decodeURIComponent(m[1]);

            const aman = (u.nama || 'laporan').replace(/[^\w\s.-]+/g, '').trim().replace(/\s+/g, '-');

            return `${aman}.${u.format === 'XLSX' ? 'xlsx' : 'pdf'}`;
        },

        /* ── PENYARING & PILIH BANYAK ──────────────────────────────────────── */
        bersihkanFilter() {
            this.posisiPilih = [];
            this.keadaanPilih = [];
            this.cariKandidat = '';
            this.tahapPilih = [];
            this.kampusPilih = [];
            this.rangeTanggal = null;
            this.listPage = 1;
        },
        /**
         * Copot SATU penyaring dari chip-nya. Kuncinya sama dengan chipFilter.
         *
         * Penyaring multi-pilih berkunci `jenis:nilai` — yang dicabut satu
         * nilai, bukan seluruh penyaringnya. Menyaring "Unsri, Unila, UI" lalu
         * ingin melepas Unila saja tidak boleh berarti kehilangan dua lainnya.
         */
        copotChip(key) {
            const [jenis, ...sisa] = String(key).split(':');
            const nilai = sisa.join(':');
            const larik = {
                tahap: 'tahapPilih', kampus: 'kampusPilih',
                posisi: 'posisiPilih', keadaan: 'keadaanPilih',
            }[jenis];

            if (larik) {
                // Lowongan bernilai angka; sisanya teks. Dicocokkan longgar
                // karena kuncinya selalu lahir sebagai teks dari template.
                this[larik] = this[larik].filter((v) => String(v) !== nilai);
            } else if (jenis === 'cari') {
                this.cariKandidat = '';
            } else if (jenis === 'tanggal') {
                this.rangeTanggal = null;
            }
            this.listPage = 1;
        },
        /**
         * Kunci kolom tempat SATU kandidat berdiri.
         *
         * Cerminan aturan penempatan di kartuKolom(): kode tahap lebih dulu,
         * nomor urut hanya sebagai cadangan untuk muatan lama. Ditulis sekali di
         * sini supaya penyaring "Tahap" dan papan kanban mustahil berselisih
         * soal kandidat ini ada di kolom mana.
         */
        kunciBaris(r) {
            if (r.kolomKode != null) {
                const k = this.petaKolom.kode.get(r.kolomKode);
                if (k) return k;
            }

            return this.petaKolom.urutan.get(r.kolomUrutan) || '';
        },
        /** Jumlah kandidat pada satu kolom — dipakai label pilihan "Tahap". */
        jumlahTahap(col) {
            const k = this.kunciKolom(col);

            return (this.detail.pelamar || []).filter((r) => this.kunciBaris(r) === k).length;
        },
        /**
         * Waktu melamar berada di dalam rentang yang dipilih?
         *
         * Batas akhirnya mencakup seluruh hari terakhir. Membandingkan langsung
         * dengan tengah malam membuat orang yang melamar pukul 09.00 pada tanggal
         * penutup jatuh DI LUAR rentang yang justru dipilih untuk memuatnya —
         * kesalahan yang tak terlihat sampai seseorang menghitung ulang manual.
         */
        dalamRentang(waktu) {
            if (!this.rentangAktif) return true;
            const t = new Date(String(waktu || '').replace(' ', 'T')).getTime();
            if (!t) return false;
            const [a, b] = this.rangeTanggal;

            return t >= new Date(`${a}T00:00:00`).getTime() && t <= new Date(`${b}T23:59:59.999`).getTime();
        },
        /** '2026-08-11' → '11 Agu 2026'. Dipakai label chip rentang. */
        tglSingkat(iso) {
            const d = new Date(`${iso}T00:00:00`);

            return Number.isNaN(d.getTime())
                ? iso
                : d.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
        },
        /**
         * Rentang "sekian hari terakhir sampai hari ini", sebagai ['YYYY-MM-DD', …].
         *
         * Disusun dari komponen tanggal SETEMPAT, bukan lewat toISOString():
         * yang terakhir mengubah ke UTC lebih dulu, dan di WIB tanggal sebelum
         * pukul 07.00 akan mundur satu hari — pintasan "7 hari" jadi menyaring
         * jendela yang meleset sehari dari yang tertulis di tombolnya.
         */
        rentangHari(hari) {
            const pad = (n) => String(n).padStart(2, '0');
            const iso = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
            const akhir = new Date();
            const awal = new Date();
            // hari - 1: "7 hari" berarti hari ini plus enam hari ke belakang,
            // bukan delapan tanggal.
            awal.setDate(awal.getDate() - (hari - 1));

            return [iso(awal), iso(akhir)];
        },
        /** Tekan pintasan yang sedang menyala = melepasnya, bukan memasangnya lagi. */
        pakaiPintasTanggal(hari) {
            this.rangeTanggal = this.pintasAktif === hari ? null : this.rentangHari(hari);
        },
        /** Centang / batal satu baris di mode list. */
        toggleBarisList(r) {
            const i = this.terpilih.indexOf(r.id);
            if (i >= 0) this.terpilih.splice(i, 1);
            else this.terpilih.push(r.id);
        },
        /**
         * Centang kepala: sapu HALAMAN INI saja.
         *
         * Pilihan di halaman lain sengaja tidak disentuh — memilih dua puluh
         * orang di halaman 1 lalu pindah ke halaman 2 untuk menambah lima lagi
         * adalah cara orang benar-benar bekerja, dan mengosongkannya diam-diam
         * berarti dua puluh keputusan hilang tanpa satu pun tanda.
         */
        toggleHalaman() {
            const idHal = this.barisList.map((r) => r.id);
            if (this.halamanTercentangPenuh) {
                this.terpilih = this.terpilih.filter((id) => !idHal.includes(id));

                return;
            }
            const set = new Set(this.terpilih);
            idHal.forEach((id) => set.add(id));
            this.terpilih = [...set];
        },
        /** Satu baris cocok dengan SATU kode keadaan. Lihat penyaring keadaan. */
        cocokKeadaan(r, kode) {
            switch (kode) {
                case 'PERLU': return !!r.butuhKeputusan && !r.hold;
                case 'NUNGGU': return !!r.nungguSistem;
                case 'HOLD': return !!r.hold;
                // Punya aktivitas yang menuntut jadwal tapi belum ada jadwalnya
                // — inilah antrean kerja penjadwalan massal.
                case 'JADWAL': return (r.tests || []).some((t) => t.butuhJadwal && !t.jadwal);
                // Sudah punya jadwal yang belum lewat — antrean "siapa yang
                // harus ditandai hadir hari ini".
                case 'TERJADWAL': return (r.tests || []).some((t) => !!t.jadwal && !t.selesai);
                default: return true;
            }
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
            this.massalLokasiNama = '';
            this.massalLokasiAlamat = '';
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
                    lokasiNama: this.massalLokasiId === this.LOKASI_LAIN ? (this.massalLokasiNama.trim() || null) : null,
                    lokasiAlamat: this.massalLokasiId === this.LOKASI_LAIN ? (this.massalLokasiAlamat.trim() || null) : null,
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

        /**
         * Master Lokasi + master peruntukannya — dimuat sekali per sesi.
         *
         * Keduanya sekaligus: daftar lokasi tanpa peruntukan tidak bisa disaring,
         * dan menyaring dengan daftar yang belum sampai berarti dropdown MCU
         * tampak kosong selama sekejap — cukup lama untuk membuat rekruter
         * mengira memang tak ada rumah sakit terdaftar.
         */
        async muatLokasi() {
            if (this.daftarLokasi.length || this.lokasiLoading) return;
            this.lokasiLoading = true;
            try {
                const [lok, per] = await Promise.all([
                    axios.get('/api/v1/master-lokasi', { ...CFG, params: { aktif: 1 } }),
                    axios.get('/api/v1/master-lokasi/peruntukan', CFG),
                ]);
                this.daftarLokasi = lok.data.result || [];
                this.daftarPeruntukan = per.data.result || [];
            } catch (e) {
                this.notice('Daftar lokasi gagal dimuat.', true);
            } finally {
                this.lokasiLoading = false;
            }
        },
        askHadir(t, nilai) {
            this.hadirTarget = t;
            this.hadirNilai = nilai;
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
                    // Satu sumber untuk kedua cabang. Sebelumnya cabang "tidak
                    // hadir" mengirim teks polos dan mengosongkan catatanHtml,
                    // sehingga alasan ketidakhadiran tampil beda sendiri di
                    // riwayat yang sama.
                    catatan: teksDariHtml(this.hadirCatatanHtml) || null,
                    catatanHtml: this.hadirCatatanHtml || null,
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
            this.jadwalLokasiNama = j.lokasiNama || '';
            this.jadwalLokasiAlamat = j.lokasiAlamat || '';
            this.jadwalCatatan = j.catatan || '';
            this.jadwalShow = true;
        },
        /** Nama & alamat tempat luar-master — hanya saat "Lainnya" dipilih. */
        isianLokasiLain() {
            if (!this.modeJadwalDef?.butuhLokasi || this.jadwalLokasiId !== this.LOKASI_LAIN) {
                return { lokasiNama: null, lokasiAlamat: null };
            }

            return {
                lokasiNama: (this.jadwalLokasiNama || '').trim() || null,
                lokasiAlamat: (this.jadwalLokasiAlamat || '').trim() || null,
            };
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
                    // Hanya ikut saat "Lainnya" — kalau selalu dikirim, sisa
                    // ketikan dari percobaan sebelumnya tersimpan sebagai tempat
                    // kedua pada jadwal yang lokasinya sudah dipilih dari master.
                    ...this.isianLokasiLain(),
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
        /**
         * Nyatakan lulus / tidak lulus pada ujian online ber-peran INFORMATIF.
         *
         * LANGSUNG, tanpa jendela konfirmasi. Alat tes seperti PAPI Kostick dan
         * DISC tidak berbunyi lulus/gagal sendiri, jadi keputusannya memang milik
         * penilai — dan ia sudah membaca hasilnya sebelum menekan. Menyisipkan
         * dialog "yakin?" di sini hanya menambah satu klik pada pekerjaan yang
         * dilakukan berpuluh kali sehari, sementara yang dipertaruhkan bukan
         * aksi final: lamaran baru berhenti pada keputusan TAHAP, yang tetap
         * punya konfirmasinya sendiri.
         *
         * Nilai dan catatan TIDAK dikirim. Angkanya milik HCLearn dan server
         * mempertahankan catatan lama bila field-nya absen — mengirim keduanya
         * kosong berarti menghapus apa yang sudah ditulis.
         */
        async putusTes(t, hasil) {
            if (this.keputusanId) return;
            this.keputusanId = t.id;
            this.keputusanHasil = hasil;
            try {
                const res = await axios.patch(`/api/v1/karir/lamaran/sub-tes/${t.id}/catat-hasil`, { hasil }, CFG);
                this.notice(res.data?.message || (hasil === 'LULUS' ? 'Dinyatakan lulus.' : 'Dinyatakan tidak lulus.'));
                await this.muatDetail(this.selectedId);
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal menyimpan keputusan.', true);
            } finally {
                this.keputusanId = null;
                this.keputusanHasil = null;
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
            // Pagar kedua. Tombolnya memang sudah mati, tapi modal ini juga
            // terpanggil dari jalur lain — dan membuka borang yang pasti
            // ditolak server adalah cara terburuk menyampaikan "tidak boleh".
            if (! this.bolehPutus) {
                this.notice('Akun Anda tidak punya izin APPROVE di Worklist Pelamar. Minta ditambahkan di Manajemen Hak Akses.', true);

                return;
            }
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

/* Rangka baris yang sedang dimuat. Tingginya SAMA dengan baris centang
   formulir yang akan menggantikannya, supaya modalnya tidak melonjak saat
   datanya tiba. */
.plw-rangka {
    height: 46px;
    border-radius: 10px;
    margin-bottom: 8px;
    background: linear-gradient(90deg, #eef1f6 25%, #f6f8fb 50%, #eef1f6 75%);
    background-size: 240% 100%;
    animation: plwRangka 1.3s ease-in-out infinite;
}
.plw-rangka--pendek { width: 72%; }
@keyframes plwRangka {
    0% { background-position: 130% 0; }
    100% { background-position: -30% 0; }
}
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

.plw-tabs { display: flex; gap: 9px; margin-bottom: 13px; flex-wrap: wrap; }
.plw-tab { appearance: none; cursor: pointer; font-family: inherit; font-size: 13.5px; font-weight: 800; display: inline-flex; align-items: center; gap: 9px; padding: 11px 18px; border-radius: 14px; transition: all 0.18s; background: #fff; color: #64748b; border: 1px solid #e6e9f3; }
.plw-tab.is-run { background: linear-gradient(135deg, #8b5cf6, #6366f1); color: #fff; border-color: transparent; box-shadow: 0 10px 24px rgba(99, 102, 241, 0.28); }
.plw-tab.is-rej { background: linear-gradient(135deg, #f87171, #ef4444); color: #fff; border-color: transparent; box-shadow: 0 10px 24px rgba(239, 68, 68, 0.26); }
.plw-tab__badge { display: inline-flex; align-items: center; justify-content: center; min-width: 22px; height: 22px; padding: 0 6px; border-radius: 8px; font-size: 12px; font-weight: 800; background: #eef0f7; color: #94a3b8; }
/* MUNDUR: ungu-abu, sengaja BUKAN merah. Merah dipakai "Tidak Lolos" dan
   berarti perusahaan yang menolak; kandidat yang pergi sendiri bukan kegagalan
   seleksi, dan mewarnainya sama membuat dua peristiwa berbeda terbaca sama. */
.plw-tab.is-out { background: linear-gradient(135deg, #a78bfa, #7c3aed); color: #fff; border-color: transparent; box-shadow: 0 10px 24px rgba(124, 58, 237, 0.26); }
/* SEMUA: gelap netral — ia bukan salah satu keadaan, melainkan ketiadaan
   penyaring, jadi ia tidak boleh meminjam warna keadaan mana pun. */
.plw-tab.is-all { background: linear-gradient(135deg, #475569, #1e293b); color: #fff; border-color: transparent; box-shadow: 0 10px 24px rgba(30, 41, 59, 0.22); }
.plw-tab.is-run .plw-tab__badge, .plw-tab.is-rej .plw-tab__badge, .plw-tab.is-out .plw-tab__badge, .plw-tab.is-all .plw-tab__badge { background: rgba(255, 255, 255, 0.24); color: #fff; }

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
/* Tab "Mundur": nada ungu — di tahap mana kandidat paling sering pergi sendiri. */
.plw-kanban.is-mundur .plw-col__num.is-hot { background: rgba(124, 58, 237, .14); color: #6d28d9; }
.plw-kanban.is-mundur .plw-col__count:not(:empty) { color: #6d28d9; background: rgba(124, 58, 237, .1); }
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
/* Kandidat yang pergi sendiri — ungu, sewarna tab "Mengundurkan Diri" dan
   sengaja bukan merah "Tidak Lolos". */
.plw-card__chip.tone-mundur { background: rgba(124, 58, 237, 0.13); color: #6d28d9; }

/* ═══ KARTU PENYARING ═══
   Kisi, bukan baris mengalir. Penyaring yang lebarnya mengikuti isi membuat
   posisinya berpindah-pindah antar program (nama lowongan panjang menggeser
   sisanya), dan otot ingatan "kampus ada di kotak ketiga" tidak pernah
   terbentuk. Kisi menjaga tiap penyaring di tempat yang sama. */
.plw-toolbar { background: #fff; border: 1px solid #e7e3fb; border-radius: 16px; box-shadow: 0 6px 18px rgba(99, 102, 241, 0.06); margin-bottom: 14px; }

/* ── Kepala panel penyaring ────────────────────────────────────────────── */
.plw-fhead { display: flex; align-items: center; gap: 10px; padding: 11px 14px; }
.plw-fhead__ico { flex: none; width: 30px; height: 30px; border-radius: 9px; display: grid; place-items: center; font-size: 13px; color: #fff; background: linear-gradient(135deg, #8b5cf6, #6366f1); box-shadow: 0 5px 12px rgba(99, 102, 241, .28); }
.plw-fhead__ttl { flex: 1; min-width: 0; }
.plw-fhead__ttl b { display: block; font-size: 13px; font-weight: 800; color: #1e293b; letter-spacing: -.01em; }
.plw-fhead__ttl small { display: block; font-size: 11px; color: #94a3b8; margin-top: 1px; }
.plw-fhead__n { flex: none; min-width: 20px; height: 20px; padding: 0 6px; border-radius: 999px; display: grid; place-items: center; font-size: 10.5px; font-weight: 800; color: #4338ca; background: #e0e7ff; }
.plw-fhead__x { appearance: none; flex: none; display: inline-flex; align-items: center; gap: 5px; border: 1px solid #e6e9f3; background: #fff; padding: 6px 10px; border-radius: 9px; cursor: pointer; font-family: inherit; font-size: 11.5px; font-weight: 700; color: #64748b; transition: all .16s; }
.plw-fhead__x:hover { color: #dc2626; border-color: #f4d0d0; background: #fef2f2; }
.plw-fhead__tgl { appearance: none; flex: none; width: 30px; height: 30px; border-radius: 9px; border: 1px solid #e6e9f3; background: #fff; cursor: pointer; color: #64748b; display: grid; place-items: center; font-size: 12px; transition: all .16s; }
.plw-fhead__tgl:hover { color: #4f46e5; border-color: #c7cdf0; }
.plw-fbody { border-top: 1px solid #f1f2f9; }

.plw-fgrid { display: grid; grid-template-columns: 1.6fr 1fr 1.2fr 1.2fr 1fr; gap: 9px; padding: 13px 14px 10px; align-items: center; }
.plw-f { width: 100%; min-width: 0; }
.plw-f--tgl { width: 100% !important; }

/* ── Rentang tanggal: baris penuh, berlabel, berpintasan ───────────────── */
.plw-frow { padding: 0 14px 13px; }
.plw-frow__lbl { display: flex; align-items: center; gap: 6px; margin-bottom: 6px; font-size: 11.5px; font-weight: 800; color: #475569; }
.plw-frow__lbl > .bi { color: #8b5cf6; font-size: 12px; }
.plw-frow__isi { display: flex; align-items: center; gap: 9px; flex-wrap: wrap; }
/* Pemilih rentang mengambil sisa baris; pintasannya tetap seukuran isinya. */
.plw-frow__isi > .plw-f--tgl { flex: 1 1 300px; min-width: 0; }
.plw-fquick { display: flex; align-items: center; gap: 6px; flex: 0 0 auto; flex-wrap: wrap; }
.plw-fq { appearance: none; border: 1px solid #e6e9f3; background: #fff; padding: 8px 12px; border-radius: 9px; cursor: pointer; font-family: inherit; font-size: 11.5px; font-weight: 700; color: #64748b; transition: all .16s; white-space: nowrap; }
.plw-fq:hover { border-color: #c7d2fe; color: #4f46e5; }
.plw-fq.is-on { border-color: transparent; color: #fff; background: linear-gradient(135deg, #8b5cf6, #6366f1); box-shadow: 0 6px 14px rgba(99, 102, 241, .24); }
.plw-fq.is-off { color: #94a3b8; }
.plw-fq.is-off:hover { color: #dc2626; border-color: #f4d0d0; background: #fef2f2; }

/* ── FAB penyaring — hanya ponsel (lihat media query di bawah) ─────────── */
.plw-fab { display: none; }
.plw-ftirai { display: none; }
.plw-lopt { display: flex; flex-direction: column; line-height: 1.35; padding: 2px 0; }
.plw-lopt b { font-size: 12.5px; color: #1e293b; }
.plw-lopt small { font-size: 11px; color: #94a3b8; }
/* Opsi kampus: bendera di kiri, nama & jumlah menumpuk di kanannya — bentuk
   yang sama dengan pemilih kampus di formulir pendaftaran. */
.plw-lopt--kampus { display: grid; grid-template-columns: 20px minmax(0, 1fr); grid-template-rows: auto auto; column-gap: 9px; align-items: center; }
.plw-lopt--kampus > .plw-lopt__flag { grid-row: 1 / span 2; }
.plw-lopt--kampus > b, .plw-lopt--kampus > small { min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.plw-lopt__flag { width: 20px; height: 15px; border-radius: 2px; box-shadow: 0 0 0 1px rgba(15, 23, 42, .1); object-fit: cover; }
/* Negara tak tercatat di data impor: bola dunia netral, bukan bendera tebakan.
   Bendera yang salah lebih menyesatkan daripada tidak ada bendera. */
.plw-lopt__flag--kosong { display: grid; place-items: center; height: auto; box-shadow: none; color: #cbd5e1; font-size: 13px; }

/* Chip penyaring aktif */
.plw-chiprow { display: flex; align-items: center; gap: 7px; flex-wrap: wrap; padding: 0 14px 13px; }
.plw-fchip { display: inline-flex; align-items: center; gap: 7px; max-width: 100%; padding: 5px 6px 5px 11px; border-radius: 9px; background: #eef2ff; border: 1px solid #c7d2fe; font-size: 11.5px; font-weight: 700; color: #3730a3; }
.plw-fchip__k { opacity: 0.65; flex: 0 0 auto; }
.plw-fchip button { appearance: none; border: none; background: transparent; cursor: pointer; color: #4f46e5; display: flex; padding: 1px; font-size: 10px; flex: 0 0 auto; }
.plw-fchip button:hover { color: #dc2626; }
.plw-fclear { appearance: none; border: none; background: transparent; cursor: pointer; font-family: inherit; font-size: 11.5px; font-weight: 700; color: #64748b; padding: 5px 6px; text-decoration: underline; text-underline-offset: 2px; }
.plw-fclear:hover { color: #dc2626; }

/* ═══ PEMILIH MODE TAMPILAN ═══ */
.plw-moderow { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; margin: 0 2px 13px; }
.plw-modes { display: inline-flex; align-items: center; gap: 3px; padding: 4px; border-radius: 13px; background: #fff; border: 1px solid #e7e3fb; box-shadow: 0 6px 18px rgba(99, 102, 241, 0.06); }
.plw-mode { appearance: none; border: none; cursor: pointer; font-family: inherit; font-size: 12.5px; font-weight: 800; display: inline-flex; align-items: center; gap: 7px; padding: 8px 14px; border-radius: 10px; background: transparent; color: #94a3b8; transition: all 0.16s; }
.plw-mode:hover { color: #4f46e5; }
.plw-mode.is-on { background: linear-gradient(135deg, #8b5cf6, #6366f1); color: #fff; box-shadow: 0 6px 16px rgba(99, 102, 241, 0.28); }
.plw-hasil { font-size: 12.5px; color: #64748b; }

/* ═══ MODE LIST ═══ */
.plw-list { background: #fff; border: 1px solid #e7e3fb; border-radius: 16px; box-shadow: 0 6px 18px rgba(99, 102, 241, 0.06); overflow: hidden; }
/* Kolom pertama = kotak centang pilih-banyak. Lebarnya tetap 26px: ia bukan
   data, cuma pegangan — kolom yang ikut melar akan menggeser seluruh tabel
   demi ruang yang tak dipakai apa pun. */
.plw-list__head,
.plw-lrow { display: grid; grid-template-columns: 26px 1.8fr 1.3fr 1.2fr 1.4fr 0.95fr 1fr 0.95fr 92px; gap: 12px; align-items: center; }
.plw-lcek { display: flex; align-items: center; justify-content: center; min-width: 0; }
/* NAMANYA `plw-tick`, BUKAN `plw-cek`. Kelas `plw-cek` sudah dipakai kartu
   pilihan formulir di modal cetak laporan — berkas yang sama, komponen yang
   lain. Definisi yang belakangan menang, jadi kotak centang ini memungut
   `padding: 9px 11px` + `border-radius: 10px` + `align-items: flex-start`
   miliknya: 19px berubah jadi gumpalan 37px yang membulat, dan centangnya
   terlempar ke pojok kiri-atas sampai tak terlihat. */
.plw-tick {
    appearance: none; cursor: pointer; flex: none; padding: 0;
    /* 18px = kelipatan genap, jadi tepinya jatuh tepat di batas piksel pada
       layar 1x. 19px membuat garis 1,6px-nya digambar setengah piksel dan
       terbaca kabur/cembung. */
    width: 18px; height: 18px;
    border-radius: 5px; border: 1.6px solid #cbd5e1; background: #fff;
    display: grid; place-items: center; color: #fff;
    transition: background .15s, border-color .15s, box-shadow .15s;
}
/* Ikon dipaksa jadi kotak setinggi glifnya sendiri. Bootstrap Icons menurunkan
   glif 0,125em lewat vertical-align agar sejajar teks di kalimat — di dalam
   kotak yang isinya HANYA ikon, geseran itu justru menjatuhkannya sekitar
   1,5px di bawah titik tengah. Terlihat jelas pada kotak 18px. */
.plw-tick > .bi { display: block; font-size: 11px; line-height: 1; }
.plw-tick > .bi::before { display: block; vertical-align: 0; line-height: 1; }
.plw-tick:hover { border-color: #a5b4fc; }
.plw-tick.is-on { border-color: transparent; background: linear-gradient(135deg, #8b5cf6, #6366f1); box-shadow: 0 2px 6px rgba(99, 102, 241, .35); }
.plw-tick.is-half { border-color: #a5b4fc; background: #eef2ff; color: #4f46e5; }
/* Garis "sebagian" digambar sendiri, bukan lewat glif bi-dash-lg: dash di font
   ini lebarnya mengikuti metrik huruf dan terbaca sebagai tanda minus yang
   melayang, bukan sebagai keadaan tengah sebuah kotak centang. */
.plw-tick.is-half > .bi { width: 9px; height: 2px; border-radius: 1px; background: #4f46e5; }
.plw-tick.is-half > .bi::before { content: none; }
.plw-lrow.is-terpilih { background: #f5f3ff; }
.plw-lrow.is-terpilih:hover { background: #ede9fe; }

/* Bilah aksi massal mode list — menempel di puncak tabel, muncul hanya saat
   ada yang tercentang. Sengaja BUKAN melayang di kaki layar: yang ditindak
   ada di tabel tepat di bawahnya, dan jaraknya ke tombol harus sependek
   mungkin agar keduanya terbaca sebagai satu hal. */
.plw-lsel {
    display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
    padding: 10px 16px; background: linear-gradient(180deg, #eef2ff, #f8f9ff);
    border-bottom: 1px solid #dfe3f7;
}
.plw-lsel__n { flex: none; display: grid; place-items: center; min-width: 26px; height: 24px; padding: 0 8px; border-radius: 8px; background: linear-gradient(135deg, #8b5cf6, #6366f1); color: #fff; font-size: 12px; font-weight: 800; box-shadow: 0 4px 10px rgba(99, 102, 241, .3); }
.plw-lsel__txt { font-size: 12.5px; font-weight: 700; color: #4338ca; }
.plw-lsel__all { appearance: none; display: inline-flex; align-items: center; gap: 6px; border: 1px solid #c7d2fe; background: #fff; padding: 7px 11px; border-radius: 9px; cursor: pointer; font-family: inherit; font-size: 11.5px; font-weight: 800; color: #4338ca; transition: all .16s; }
.plw-lsel__all:hover { background: #eef2ff; border-color: #a5b4fc; }
.plw-lsel__aksi { display: flex; align-items: center; gap: 7px; flex-wrap: wrap; margin-left: auto; }
.plw-lsel__aksi > button { font-size: 11.5px; padding: 8px 12px; border-radius: 9px; }
.plw-lsel__x { appearance: none; flex: none; border: 1px solid #d5dbf5; background: #fff; width: 28px; height: 28px; border-radius: 8px; cursor: pointer; color: #6366f1; display: grid; place-items: center; font-size: 11px; transition: all .16s; }
.plw-lsel__x:hover { color: #dc2626; border-color: #f4d0d0; background: #fef2f2; }
.plw-sel-enter-active, .plw-sel-leave-active { transition: opacity .18s, transform .18s; }
.plw-sel-enter-from, .plw-sel-leave-to { opacity: 0; transform: translateY(-6px); }
.plw-list__head { padding: 12px 16px; background: #f8fafc; border-bottom: 1px solid #eef0f7; font-size: 9.5px; font-weight: 800; letter-spacing: 0.12em; color: #94a3b8; }
.plw-lrow { padding: 12px 16px; border-bottom: 1px solid #f4f5fb; cursor: pointer; transition: background 0.14s; animation: plwCardIn 0.3s ease both; }
.plw-lrow:last-child { border-bottom: none; }
.plw-lrow:hover { background: #fbfbfe; }
.plw-lrow.is-hold { background: #fffdf6; }
.plw-lrow.is-hold:hover { background: #fffaeb; }
.plw-lcell { min-width: 0; font-size: 12.5px; color: #475569; display: flex; align-items: center; gap: 8px; }
.plw-lcell--who { gap: 10px; }
.plw-lcell--act { justify-content: flex-end; gap: 6px; }
.plw-lname { display: block; font-size: 13px; font-weight: 800; color: #0f172a; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.plw-lcode { display: block; font-size: 10.5px; font-family: 'JetBrains Mono', monospace; color: #94a3b8; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.plw-lpill { display: inline-block; max-width: 100%; font-size: 10.5px; font-weight: 700; padding: 4px 10px; border-radius: 8px; background: #f4f2ff; border: 1px solid #e7e3fb; color: #6d28d9; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.plw-ltgl { display: flex; flex-direction: column; line-height: 1.3; min-width: 0; }
.plw-ltgl small { font-size: 10.5px; color: #94a3b8; }
.plw-ldok { display: inline-flex; align-items: center; gap: 6px; max-width: 100%; font-size: 11px; font-weight: 700; padding: 4px 10px; border-radius: 8px; background: #eef2ff; border: 1px solid #dbe2fe; color: #4338ca; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.plw-ldok i { font-size: 11px; flex: 0 0 auto; }
.plw-ldok.is-kosong { background: #f8fafc; border-color: #eef0f7; color: #a2a9ba; }
.plw-ldetail { appearance: none; cursor: pointer; font-family: inherit; font-size: 11.5px; font-weight: 800; color: #4f46e5; background: #fff; border: 1px solid #d9def0; padding: 6px 12px; border-radius: 9px; flex: 0 0 auto; transition: all 0.16s; }
.plw-ldetail:hover { border-color: #a5b4fc; background: #eef2ff; }

/* ═══ KEADAAN KOSONG ═══ */
.plw-kosong { padding: 46px 24px; text-align: center; background: #fff; border: 1px dashed #d9def0; border-radius: 16px; }
.plw-kosong__ico { width: 54px; height: 54px; border-radius: 15px; margin: 0 auto; display: flex; align-items: center; justify-content: center; background: #f1f5f9; color: #cbd5e1; font-size: 22px; }
.plw-kosong__judul { font-size: 15px; font-weight: 800; color: #1e293b; margin-top: 14px; }
.plw-kosong__sub { font-size: 12.5px; color: #64748b; margin-top: 6px; }
.plw-kosong__btn { appearance: none; border: none; cursor: pointer; font-family: inherit; margin-top: 16px; font-size: 13px; font-weight: 800; color: #fff; padding: 11px 20px; border-radius: 12px; background: linear-gradient(135deg, #8b5cf6, #6366f1); box-shadow: 0 10px 22px rgba(99, 102, 241, 0.28); }

/* ═══ PAGINASI MODE LIST ═══ */
.plw-pagerbar { display: flex; align-items: center; justify-content: space-between; gap: 13px; flex-wrap: wrap; margin-top: 14px; padding: 11px 16px; background: #fff; border: 1px solid #e7e3fb; border-radius: 16px; box-shadow: 0 6px 18px rgba(99, 102, 241, 0.06); }
.plw-pagerbar__info { font-size: 12px; color: #64748b; min-width: 0; }
.plw-pagerbar__nav { display: flex; align-items: center; gap: 5px; flex-wrap: wrap; justify-content: center; }
.plw-pagerbar__nav > button { appearance: none; cursor: pointer; min-width: 32px; height: 32px; padding: 0 9px; border-radius: 9px; border: 1px solid #e2e8f0; background: #fff; color: #475569; font-family: inherit; font-size: 12.5px; font-weight: 800; display: inline-flex; align-items: center; justify-content: center; transition: all 0.16s; }
.plw-pagerbar__nav > button:hover:not(:disabled) { border-color: #a5b4fc; color: #4f46e5; }
.plw-pagerbar__nav > button:disabled { color: #cbd5e1; cursor: not-allowed; }
.plw-pnum.is-on { border-color: transparent; background: linear-gradient(135deg, #8b5cf6, #6366f1); color: #fff; }
.plw-pnum.is-gap { border-color: transparent; background: transparent; color: #cbd5e1; cursor: default; }
.plw-pagerbar__per { display: flex; align-items: center; gap: 8px; font-size: 12px; color: #64748b; }
.plw-perpage { width: 84px; }
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
.plw-selbar { display: flex; flex-direction: column; gap: 7px; margin-bottom: 9px; padding: 7px; border-radius: 10px; background: #eef2ff; border: 1px solid #c7d2fe; }
.plw-selbar__head { display: flex; align-items: center; gap: 6px; }
.plw-selbar__all { display: inline-flex; align-items: center; gap: 4px; border: none; background: transparent; color: #4338ca; font-size: 10.5px; font-weight: 800; cursor: pointer; padding: 0; white-space: nowrap; }
.plw-selbar__n { display: grid; place-items: center; min-width: 20px; height: 19px; padding: 0 5px; border-radius: 6px; background: #4f46e5; color: #fff; font-size: 10px; font-weight: 800; }
/* Tombol aksi selebar panel dan berbagi rata. Jumlahnya berubah-ubah
   (Lanjutkan hanya muncul bila ada yang tertahan), jadi lebarnya ikut
   membagi diri sendiri alih-alih dipatok per tombol. */
.plw-selbar__aksi { display: flex; flex-wrap: wrap; gap: 6px; }
.plw-selbar__aksi > button { flex: 1 1 88px; min-width: 0; justify-content: center; }
.plw-selbar__go { display: inline-flex; align-items: center; gap: 4px; border: none; background: linear-gradient(135deg, #8b5cf6, #6366f1); color: #fff; font-size: 10.5px; font-weight: 800; border-radius: 7px; padding: 6px 9px; cursor: pointer; white-space: nowrap; }
.plw-selbar__go:disabled { opacity: .45; cursor: not-allowed; }
.plw-selbar__x { flex: none; margin-left: auto; border: none; background: transparent; color: #818cf8; font-size: 10px; cursor: pointer; padding: 2px; }
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

/* ── TAHAN MASSAL ────────────────────────────────────────────────────────── */
/* Tombol di bilah pilih-banyak. Warnanya BUKAN ungu seperti "Jadwalkan":
   menjadwalkan mendorong proses maju, menahan justru menghentikannya — dua
   arah berlawanan yang tidak boleh terbaca sama saat dipindai cepat. */
.plw-selbar__hold,
.plw-selbar__lepas {
    appearance: none; cursor: pointer; font: inherit; font-size: 10.5px; font-weight: 800;
    display: inline-flex; align-items: center; gap: 5px; padding: 6px 9px;
    white-space: nowrap; border-radius: 7px; border: 1px solid transparent; transition: all .16s;
}
/* Keputusan massal — indigo, sekeluarga dengan tombol keputusan di drawer.
   Sengaja BUKAN hijau/merah: satu tombol ini membuka pilihan Lolos MAUPUN
   Tidak Lolos, jadi mewarnainya seperti salah satunya menyesatkan sebelum
   admin sempat memilih. */
.plw-selbar__putus {
    appearance: none; cursor: pointer; font: inherit; font-size: 10.5px; font-weight: 800;
    display: inline-flex; align-items: center; gap: 5px; padding: 6px 9px;
    white-space: nowrap; border-radius: 7px; transition: all .16s;
    background: #eef2ff; border: 1px solid #c7d2fe; color: #4338ca;
}
.plw-selbar__putus:hover:not(:disabled) { background: #e0e7ff; border-color: #a5b4fc; }
.plw-selbar__putus:disabled { opacity: .45; cursor: not-allowed; }

.plw-selbar__hold { background: #fff7ed; border-color: #fed7aa; color: #b45309; }
.plw-selbar__hold:hover:not(:disabled) { background: #ffedd5; border-color: #fdba74; }
.plw-selbar__lepas { background: #ecfdf5; border-color: #a7f3d0; color: #047857; }
.plw-selbar__lepas:hover { background: #d1fae5; border-color: #6ee7b7; }
.plw-selbar__hold:disabled { opacity: .45; cursor: not-allowed; }

/* Jalan pintas "salin ke semua". */
.plw-hmbar {
    display: flex; align-items: center; gap: 9px; flex-wrap: wrap;
    margin: 4px 0 10px; padding: 9px 12px; border-radius: 11px;
    background: #f5f3ff; border: 1px solid #ddd6fe;
    font-size: 11.5px; line-height: 1.5; color: #5b21b6;
}
.plw-hmbar > .bi { flex: none; font-size: 14px; color: #7c3aed; }
.plw-hmbar span { flex: 1; min-width: 140px; }
.plw-hmbar button {
    appearance: none; cursor: pointer; font: inherit; font-size: 11px; font-weight: 800;
    display: inline-flex; align-items: center; gap: 5px; padding: 5px 11px;
    border-radius: 999px; border: 1px solid #c4b5fd; background: #fff; color: #6d28d9;
    transition: all .16s; flex: none;
}
.plw-hmbar button:hover:not(:disabled) { background: #ede9fe; border-color: #a78bfa; }
.plw-hmbar button:disabled { opacity: .45; cursor: not-allowed; }

/* Daftar per kandidat — DIGULUNG DI DALAM, bukan memanjangkan modal.
   Dua puluh kandidat tanpa batas tinggi membuat tombol Simpan terdorong jauh
   di luar layar, dan admin harus menggulung modal setiap kali memeriksanya. */
/* Tinggi diukur dari layar, bukan dari angka tetap: tiap baris kini membawa
   penyunting sendiri, dan 320px yang dulu memuat empat baris textarea hanya
   memuat satu setengah baris sekarang. */
.plw-hmlist { max-height: 44vh; overflow-y: auto; overscroll-behavior: contain; padding-right: 4px; display: flex; flex-direction: column; gap: 8px; }
.plw-hmlist::-webkit-scrollbar { width: 6px; }
.plw-hmlist::-webkit-scrollbar-thumb { background: #d9def0; border-radius: 999px; }
.plw-hmrow { padding: 10px 12px; border: 1px solid #e6e9f3; border-radius: 12px; background: #fbfbfe; }
/* Baris yang belum lengkap ditandai merah DI TEMPATNYA — pada daftar dua
   puluh baris, pesan galat tunggal di bawah tidak memberi tahu yang mana. */
.plw-hmrow.is-kurang { border-color: #fecaca; background: #fef2f2; }
.plw-hmrow__head { display: flex; align-items: center; gap: 9px; margin-bottom: 8px; }
.plw-hmrow__n { flex: none; width: 20px; height: 20px; display: grid; place-items: center; border-radius: 999px; background: #e0e7ff; color: #4338ca; font-size: 10.5px; font-weight: 800; }
.plw-hmrow__head b { display: block; font-size: 13px; font-weight: 800; color: #1e293b; }
.plw-hmrow__head small { display: block; font-size: 10.5px; color: #94a3b8; margin-top: 1px; }
.plw-hmrow__warn { flex: none; display: inline-flex; align-items: center; gap: 4px; font-size: 10.5px; font-weight: 800; color: #dc2626; }
.plw-hmrow__sel { width: 100%; }
/* Keputusan di kiri, alasannya di kanan — sepasang, karena begitulah ia
   dibaca kembali nanti. Bertumpuk, satu baris kandidat jadi setinggi tiga
   baris dan dua puluh kandidat menuntut penggulungan yang tak ada ujungnya. */
.plw-hmrow__isi { display: grid; grid-template-columns: minmax(0, 208px) minmax(0, 1fr); gap: 10px; align-items: start; }

@media (max-width: 860px) {
    .plw-hmrow__isi { grid-template-columns: minmax(0, 1fr); }
}
@media (max-width: 640px) {
    /* `.plw-opts--row { flex-direction: column }` yang dulu di sini DIHAPUS.
       Media query tidak menambah kekhususan, dan aturan `--row` yang sebenarnya
       berada jauh di bawah berkas ini — jadi ia tetap menang dan baris ini tak
       pernah berlaku. Penumpukan di layar sempit kini datang dari
       `min-width: 190px` + `flex-wrap`, yang bekerja tanpa bergantung urutan. */
    .plw-hmlist { max-height: 52vh; }
}

/* Keterangan "tidak berwenang" — biru keterangan, BUKAN merah galat. Ini
   bukan kesalahan yang dibuat admin, melainkan batas wewenang akunnya; warna
   merah membuatnya terbaca seolah ada yang rusak. */
.plw-nogate {
    display: flex; gap: 10px; align-items: flex-start; margin-bottom: 10px;
    padding: 11px 13px; border-radius: 12px;
    background: #eff6ff; border: 1px solid #bfdbfe;
    font-size: 12px; line-height: 1.55; color: #1e40af;
}
.plw-nogate .bi { flex: none; margin-top: 1px; font-size: 14px; color: #2563eb; }
.plw-nogate b { color: #1e3a8a; }
.plw-nogate span { display: block; margin-top: 3px; color: #3b82f6; }

/* Keterangan "master kosong" — kuning peringatan, bukan biru keterangan:
   berbeda dari batas wewenang, keadaan ini MEMANG perlu dibereskan, dan
   yang membereskannya bukan admin yang sedang menatap layar ini. */
.plw-nomaster {
    display: flex; gap: 10px; align-items: flex-start; margin-bottom: 10px;
    padding: 11px 13px; border-radius: 12px;
    background: #fffbeb; border: 1px solid #fde68a;
    font-size: 12px; line-height: 1.55; color: #92400e;
}
.plw-nomaster .bi { flex: none; margin-top: 1px; font-size: 14px; color: #d97706; }
.plw-nomaster b { color: #78350f; }
.plw-nomaster span { display: block; margin-top: 3px; color: #b45309; }

/* Aktivitas yang belum tiba gilirannya (tahap BERURUTAN). */
.plw-test.is-terkunci { opacity: 0.72; }
/* LANJUTKAN — ungu: bukan penilaian, melainkan membuka jalan. */
.plw-test__lanjut { display: inline-flex; align-items: center; gap: 5px; border: 1px solid #c4b5fd; background: #f5f3ff; color: #6d28d9; font-size: 11px; font-weight: 800; border-radius: 8px; padding: 5px 10px; cursor: pointer; }
.plw-test__lanjut:hover:not(:disabled) { background: #ede9fe; border-color: #a78bfa; }
.plw-test__lanjut:disabled { opacity: .6; cursor: not-allowed; }
.plw-test__gembok { display: inline-flex; align-items: center; gap: 5px; padding: 4px 9px; border-radius: 7px; font-size: 11px; font-weight: 700; color: #64748b; background: #f1f5f9; border: 1px solid #e2e8f0; }
.plw-test__gembok b { color: #334155; }
.plw-test__tag.is-internal { background: rgba(100, 116, 139, 0.14); color: #475569; }

/* ═══ MODAL DETAIL — HERO & TAB ═══
   Hero menempel di puncak isi yang menggulung. Nama orang yang sedang
   diputuskan tidak boleh hilang dari layar: rapor tes bisa sepanjang enam
   aktivitas, dan siapa pun yang menggulung sampai dasar lalu menekan
   "Tidak Lolos" berhak tahu ia sedang menggugurkan siapa. */
/* Penempelannya diurus `.wca-modal__stickybar` (slot `sticky` AdminModal); di
   sini hanya rupa & padding dalamnya. Padding mendatar mengikuti variabel milik
   isi modal supaya hero tetap sejajar dengan isi di bawahnya di tiap lebar
   layar — bukan angka terpisah yang harus diingat ikut diubah. */
.plw-hero { padding: 18px var(--wca-modal-pad, 1.35rem) 0; background: linear-gradient(135deg, #f4f2ff 0%, #eef2ff 52%, #eaf1ff 100%); border-bottom: 1px solid #e4e7f5; }
.plw-hero__row { display: flex; align-items: flex-start; gap: 14px; flex-wrap: wrap; }
.plw-hero__avatar { width: 54px; height: 54px; border-radius: 16px; color: #fff; font-size: 17px; font-weight: 800; display: flex; align-items: center; justify-content: center; flex: 0 0 auto; box-shadow: 0 10px 24px rgba(99, 102, 241, 0.28); }
.plw-hero__tags { display: flex; align-items: center; gap: 7px; flex-wrap: wrap; margin-bottom: 7px; }
.plw-hero__name { font-size: 20px; font-weight: 800; color: #1e1b4b; letter-spacing: -0.02em; line-height: 1.2; text-wrap: pretty; }

/* Tab modal — nada sama dengan pemilih mode di papan, jadi keduanya terbaca
   sebagai "pemilih tampilan", bukan sebagai tombol tindakan. */
.plw-mtabs { display: flex; gap: 6px; margin-top: 14px; overflow-x: auto; padding-bottom: 12px; }
.plw-mtab { appearance: none; border: 1px solid rgba(255, 255, 255, 0.85); cursor: pointer; font-family: inherit; display: inline-flex; align-items: center; gap: 7px; padding: 9px 14px; border-radius: 11px; font-size: 12.5px; font-weight: 800; white-space: nowrap; background: rgba(255, 255, 255, 0.62); color: #5b5486; transition: all 0.16s; }
.plw-mtab:hover { background: #fff; }
.plw-mtab.is-on { border-color: transparent; background: linear-gradient(135deg, #8b5cf6, #6366f1); color: #fff; box-shadow: 0 8px 20px rgba(99, 102, 241, 0.28); }
.plw-mtab__n { display: inline-grid; place-items: center; min-width: 18px; height: 18px; padding: 0 5px; border-radius: 999px; font-size: 10px; background: rgba(99, 102, 241, 0.14); color: #4338ca; }
.plw-mtab.is-on .plw-mtab__n { background: rgba(255, 255, 255, 0.26); color: #fff; }
.plw-tabpane { display: flex; flex-direction: column; gap: 18px; }

/* ═══ TAB ALUR SELEKSI ═══ */
.plw-alur { background: #fff; border: 1px solid #eef0f7; border-radius: 18px; padding: 18px 20px; }
.plw-alur__head { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; margin-bottom: 12px; }
.plw-alur__lbl { font-size: 11px; font-weight: 800; letter-spacing: 0.12em; color: #8b93a7; }
.plw-alur__pos { font-size: 13px; font-weight: 800; color: #4f46e5; }
.plw-alur__bar { height: 8px; border-radius: 99px; background: #eef0f7; overflow: hidden; margin-bottom: 20px; }
.plw-alur__bar > div { height: 100%; border-radius: 99px; background: linear-gradient(90deg, #8b5cf6, #6366f1); transition: width 0.5s; }
.plw-alur__line { list-style: none; margin: 0; padding: 0 0 0 30px; position: relative; display: flex; flex-direction: column; gap: 14px; }
.plw-alur__line::before { content: ''; position: absolute; left: 13px; top: 8px; bottom: 8px; width: 2px; background: #eef0f7; }
.plw-alur__item { position: relative; }
.plw-alur__node { position: absolute; left: -30px; top: 0; width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; background: #fff; border: 3px solid #cbd2e0; }
.plw-alur__node > span { width: 9px; height: 9px; border-radius: 50%; background: #cbd2e0; display: block; }
.plw-alur__item.is-lewat .plw-alur__node { border-color: #10b981; }
.plw-alur__item.is-lewat .plw-alur__node > span { background: #10b981; }
.plw-alur__item.is-kini .plw-alur__node { border-color: #f59e0b; animation: plwPulse 2.2s infinite; }
.plw-alur__item.is-kini .plw-alur__node > span { background: #f59e0b; }
.plw-alur__item.is-tutup .plw-alur__node { border-color: #ef4444; }
.plw-alur__item.is-tutup .plw-alur__node > span { background: #ef4444; }
@keyframes plwPulse {
    0%, 100% { box-shadow: 0 0 0 0 rgba(245, 158, 11, 0.5); }
    70% { box-shadow: 0 0 0 7px rgba(245, 158, 11, 0); }
}
.plw-alur__isi { display: flex; align-items: flex-start; justify-content: space-between; gap: 10px; flex-wrap: wrap; }
.plw-alur__nama { font-size: 13.5px; font-weight: 800; color: #94a3b8; }
.plw-alur__item.is-lewat .plw-alur__nama, .plw-alur__item.is-kini .plw-alur__nama, .plw-alur__item.is-tutup .plw-alur__nama { color: #1e293b; }
.plw-alur__ket { font-size: 11.5px; color: #8792a6; margin-top: 2px; }
.plw-alur__tag { flex: 0 0 auto; font-size: 10.5px; font-weight: 800; padding: 4px 9px; border-radius: 999px; white-space: nowrap; background: #eef0f7; color: #94a3b8; }
.plw-alur__item.is-lewat .plw-alur__tag { background: rgba(16, 185, 129, 0.12); color: #059669; }
.plw-alur__item.is-kini .plw-alur__tag { background: rgba(245, 158, 11, 0.14); color: #b45309; }
.plw-alur__item.is-tutup .plw-alur__tag { background: rgba(239, 68, 68, 0.12); color: #dc2626; }

.plw-drawer__meta { font-size: 12.5px; color: #6b6597; margin-top: 3px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
/* Dulu ditulis dua kali berturut-turut, beda hanya pada `background` — yang
   pertama tidak pernah terpakai sedetik pun. */
.plw-drawer__akun { display: inline-flex; align-items: center; gap: 5px; margin-top: 3px; font-size: 11px; font-weight: 600; color: #64748b; background: #f1f5f9; border-radius: 7px; padding: 2px 8px; }
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
/* ══ PANEL UNDUHAN (pojok kanan bawah) ══════════════════════════════════
   Melayang di atas halaman, bukan di dalam modal: pekerjaannya berjalan di
   antrean dan admin harus tetap bisa memakai papan sementara menunggu. */
.plw-unduhan {
    position: fixed; right: 20px; bottom: 20px; z-index: 3000;
    width: 340px; max-width: calc(100vw - 40px);
    background: #fff; border: 1px solid #e6e9f2; border-radius: 14px;
    box-shadow: 0 18px 44px rgba(15, 23, 42, .18); overflow: hidden;
}
/* Kepala panel MENGIKUTI EVO THEME, bukan navy pekat. Panelnya melayang di
   atas papan yang seluruhnya terang; kepala gelap membuatnya terbaca seperti
   jendela milik aplikasi lain yang kebetulan menumpang di pojok. */
.plw-unduhan__head {
    display: flex; align-items: center; justify-content: space-between; gap: 8px;
    padding: 11px 13px; background: var(--evo-panel, #fff); color: var(--evo-ink, #0f172a);
    border-bottom: 1px solid var(--evo-line, #e2e8f0);
    font-size: 12.5px; font-weight: 700;
}
.plw-unduhan__head i { margin-right: 6px; color: var(--evo-indigo, #6366f1); }
.plw-unduhan__x {
    border: 0; background: transparent; color: var(--evo-muted, #64748b); cursor: pointer;
    font-size: 12px; padding: 2px 4px; border-radius: 6px;
}
.plw-unduhan__x:hover { color: var(--evo-ink, #0f172a); background: var(--evo-bg, #f8fafc); }
.plw-unduhan__list { max-height: 260px; overflow-y: auto; }
.plw-unduhan__row { display: flex; align-items: center; gap: 11px; padding: 11px 13px; border-bottom: 1px solid #f1f4f9; }
.plw-unduhan__row:last-child { border-bottom: 0; }
.plw-unduhan__txt { flex: 1; min-width: 0; }
.plw-unduhan__txt b { display: block; font-size: 12.5px; font-weight: 700; color: #1e293b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.plw-unduhan__txt small { display: block; font-size: 11px; color: #94a3b8; margin-top: 1px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.plw-unduhan__txt small.is-err { color: #dc2626; }
.plw-unduhan__ikon { flex: none; font-size: 17px; }
.plw-unduhan__ikon.is-pdf { color: #dc2626; }
.plw-unduhan__ikon.is-xls { color: #15803d; }

/* Cincin kemajuan — satu lingkaran menjawab "berapa lagi" tanpa perlu dibaca. */
.plw-ring { position: relative; flex: none; width: 34px; height: 34px; display: grid; place-items: center; }
.plw-ring svg { position: absolute; inset: 0; transform: rotate(-90deg); }
.plw-ring__bg { fill: none; stroke: #eef1f7; stroke-width: 3; }
.plw-ring__val { fill: none; stroke: #6366f1; stroke-width: 3; stroke-linecap: round; transition: stroke-dasharray .3s ease; }
.plw-ring b { position: relative; font-size: 10px; font-weight: 800; color: #475569; }
.plw-ring i { position: relative; font-size: 15px; }
.plw-ring.is-selesai .plw-ring__val { stroke: #10b981; }
.plw-ring.is-selesai i { color: #10b981; }
.plw-ring.is-gagal .plw-ring__val { stroke: #ef4444; }
.plw-ring.is-gagal i { color: #ef4444; }

.plw-unduhan-enter-active, .plw-unduhan-leave-active { transition: transform .22s ease, opacity .22s ease; }
.plw-unduhan-enter-from, .plw-unduhan-leave-to { transform: translateY(16px); opacity: 0; }

@media (max-width: 520px) {
    .plw-unduhan { right: 12px; left: 12px; bottom: 12px; width: auto; }
}


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
/* `plw-segbar`, bukan `plw-seg`: nama kedua itu dipakai KELOMPOK TOMBOL
   Lulus/Gagal di modal penilaian — komponen lain, berkas yang sama. Selama
   keduanya bernama sama, wadah tombol itu ikut memungut `height: 6px` milik
   bilah kemajuan ini, dan tombol-tombolnya meluber keluar wadah setinggi enam
   piksel. */
.plw-segs { display: flex; gap: 5px; margin-top: 9px; }
.plw-segbar { flex: 1; height: 6px; border-radius: 99px; background: #eef0f7; }
.plw-segbar.is-done { background: linear-gradient(90deg, #8b5cf6, #6366f1); }
.plw-segbar.is-cur { background: linear-gradient(90deg, #fbbf24, #f59e0b); }
.plw-segbar.is-fail { background: linear-gradient(90deg, #f87171, #ef4444); }
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
/* Keputusan lulus/tidak lulus — dua tombol yang saling berlawanan, jadi
   keduanya BERISI warna (bukan garis tepi seperti tombol lain di baris ini):
   pada aktivitas yang menunggu keputusan, inilah satu-satunya yang harus
   ditekan, dan sepasang tombol pucat di antara tombol pucat lain membuatnya
   luput terbaca. */
.plw-test__ver { flex: none; display: inline-flex; align-items: center; gap: 5px; border: 1px solid transparent; font-size: 11px; font-weight: 700; border-radius: 9px; padding: 5px 10px; cursor: pointer; transition: filter .15s; }
.plw-test__ver:hover:not(:disabled) { filter: brightness(.94); }
.plw-test__ver:disabled { opacity: .6; cursor: default; }
.plw-test__ver.is-lulus { background: #059669; color: #fff; }
.plw-test__ver.is-gagal { background: #fff; border-color: #fca5a5; color: #b91c1c; }
.plw-test__ver.is-gagal:hover:not(:disabled) { background: #fef2f2; filter: none; }
.plw-test__sync { flex: none; display: inline-flex; align-items: center; gap: 5px; border: 1px solid #a5b4fc; background: #fff; color: #4338ca; font-size: 11px; font-weight: 700; border-radius: 9px; padding: 5px 9px; cursor: pointer; transition: background .15s; }
.plw-test__sync:hover:not(:disabled) { background: #eef0fe; }
.plw-test__sync:disabled { opacity: .6; cursor: default; }
/* Ikon yang BERPUTAR di tempat — bukan cincin pemuat.
   Dulu keduanya sama-sama bernama .plw-spin di berkas yang sama, dan definisi
   yang belakangan menang: setiap <i class="bi-arrow-repeat plw-spin"> ikut
   memungut lebar 18px, tinggi 18px, dan border 2.5px milik cincin — panah
   berputarnya digambar terkurung di dalam lingkaran bergaris. */
.plw-putar { display: inline-block; animation: plwSpin 1s linear infinite; }
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
/* Dua kolom sama lebar. Jaraknya diambil alih BARISNYA, dan margin anak-anaknya
   dinolkan — `.plw-fld:first-child` hanya menolkan kolom PERTAMA, sehingga
   kolom kedua turun 12px sendirian dan kedua labelnya tidak pernah sejajar. */
.plw-fld__row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-top: 12px; }
.plw-fld__row:first-child { margin-top: 0; }
.plw-fld__row > .plw-fld { min-width: 0; margin-top: 0; }

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

/* DUA PILIHAN BERDAMPINGAN — setengah lebar masing-masing. Keduanya setara,
   dan perbandingannya harus terbaca sekali lihat.

   Aturan ini dulu ditulis JAUH DI ATAS `.plw-opts`, padahal kekhususannya
   sama persis (satu kelas). Yang belakangan menang, jadi `flex-direction: row`
   selalu dikalahkan `column` milik aturan dasar — modifier `--row` tidak
   pernah bekerja sehari pun. Akibatnya bukan cuma bertumpuk: pada wadah
   ber-arah kolom, `flex-basis` mengukur TINGGI, sehingga tiap kartu berdiri
   setinggi 200px lebih dengan isi setinggi dua baris di tengah kekosongan.

   Sekarang ia duduk tepat setelah aturan dasarnya — urutan berkas yang
   menentukan, jadi jaraknya harus dekat supaya tetap begitu. */
.plw-opts--row { flex-direction: row; flex-wrap: wrap; align-items: stretch; }
/* calc(50% - 4px): setengah lebar dikurangi separuh gap 7px. Dua kartu pas
   sebaris, dan `min-width` memaksanya turun jadi satu kolom di modal sempit
   alih-alih memeras keduanya sampai judulnya terpotong. */
.plw-opts--row > .plw-opt-card { flex: 1 1 calc(50% - 4px); min-width: 190px; }

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
/* ── KARTU TERPILIH ─────────────────────────────────────────────────────
   Aturan ini DULU TIDAK ADA. Yang punya gaya terpilih hanya varian `is-fmt`,
   `is-ok`, dan `is-warn` — sementara kartu polos ber-`is-on` (Pola waktu,
   predikat hasil) berubah NOL PIKSEL saat ditekan. Admin memilih "Bergilir",
   layarnya diam saja, dan ia menekan lagi mengira klik pertamanya tidak masuk.

   Dinaikkan ke kartu dasar, bukan disalin ke tiap varian: varian berikutnya
   akan lupa membawanya, dan bug yang sama lahir lagi. Varian is-ok/is-warn
   tetap menang karena selektornya lebih spesifik. */
.plw-opt-card.is-on {
    border-color: #6366f1; background: rgba(99, 102, 241, .06);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, .12);
}
.plw-opt-card.is-on .plw-opt-card__dot { background: #6366f1; color: #fff; }
.plw-opt-card.is-on .plw-opt-card__txt b { color: #3730a3; }

/* Ceklis di kanan — muncul HANYA pada yang terpilih. Kartu yang tidak
   menyediakan span-nya tidak terpengaruh. */
.plw-opt-card__cek {
    flex: none; margin-left: auto; width: 21px; height: 21px; border-radius: 50%;
    display: grid; place-items: center; font-size: 11px;
    border: 1.5px solid #d8dcea; color: transparent; background: #fff;
    transition: background .16s, border-color .16s, color .16s;
}
.plw-opt-card.is-on .plw-opt-card__cek { background: #6366f1; border-color: #6366f1; color: #fff; }
.plw-opt-card.is-fmt { padding: 11px 13px; }
.plw-opt-card.is-fmt.is-on .plw-opt-card__dot { background: #fff; color: inherit; box-shadow: 0 1px 3px rgba(30, 41, 59, .12); }

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

.plw-secrow { display: flex; align-items: center; justify-content: space-between; gap: 10px; margin-bottom: 12px; flex-wrap: wrap; }
.plw-sectitle { display: flex; align-items: center; gap: 9px; font-size: 15px; font-weight: 800; color: #1e293b; }
.plw-seccount { font-size: 11.5px; font-weight: 700; color: #8b93a7; background: #eef0f7; border-radius: 999px; padding: 4px 11px; flex: 0 0 auto; }

/* ── PEMILIH CARA MEMBACA BERKAS (daftar / file manager) ─────────────────── */
/* Saklar tampilan — IKON SAJA, seperti desain. Dua label teks di sini berebut
   baris dengan judul seksi dan hitungannya, lalu ketiganya melipat di layar
   sedang. Artinya tetap terbaca lewat title/aria-pressed. */
.plw-fmview { display: inline-flex; align-items: center; gap: 3px; padding: 4px; border-radius: 13px; background: #fff; border: 1px solid #e7e3fb; flex: 0 0 auto; margin-left: auto; }
.plw-fmview__b { appearance: none; border: none; background: transparent; cursor: pointer; font-family: inherit; width: 34px; height: 34px; display: inline-flex; align-items: center; justify-content: center; border-radius: 10px; font-size: 15px; color: #94a3b8; transition: all 0.16s; }
.plw-fmview__b:hover { color: #4f46e5; background: #f4f2ff; }
.plw-fmview__b.is-on { background: linear-gradient(135deg, #8b5cf6, #6366f1); color: #fff; box-shadow: 0 8px 18px rgba(99, 102, 241, 0.26); }

/* ── FILE MANAGER ───────────────────────────────────────────────────────────
   Dua panel: folder (kiri, tetap) + petak berkas (kanan, menggulir sendiri).
   Tingginya dipatok supaya panel kanan yang menggulir, bukan seluruh modal —
   kalau modal yang menggulir, daftar folder ikut hanyut dan pindah folder
   menuntut menggulir balik ke atas dulu. */
.plw-fm { display: grid; grid-template-columns: 248px minmax(0, 1fr); background: #fff; border: 1px solid #e7e3fb; border-radius: 18px; overflow: hidden; box-shadow: 0 6px 20px rgba(99, 102, 241, 0.07); min-height: 420px; }
.plw-fm__side { display: flex; flex-direction: column; min-width: 0; background: #fbfbfe; border-right: 1px solid #eef0f7; }
.plw-fm__sidehead { display: flex; align-items: center; gap: 10px; padding: 13px 14px 12px; border-bottom: 1px solid #eef0f7; }
.plw-fm__sideico { width: 34px; height: 34px; border-radius: 11px; flex: 0 0 auto; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, #8b5cf6, #6366f1); color: #fff; font-size: 16px; }
.plw-fm__sidetxt { min-width: 0; display: block; }
.plw-fm__sidetxt b { display: block; font-size: 12.5px; font-weight: 800; color: #1e293b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.plw-fm__sidetxt em { display: block; font-size: 10.5px; font-style: normal; color: #94a3b8; margin-top: 1px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.plw-fm__folders { flex: 1; min-height: 0; overflow-y: auto; padding: 8px; display: flex; flex-direction: column; gap: 3px; }
.plw-fm__folder { appearance: none; border: 1px solid transparent; background: transparent; cursor: pointer; font-family: inherit; display: flex; align-items: center; gap: 9px; width: 100%; padding: 9px 10px; border-radius: 11px; text-align: left; transition: all 0.15s; }
.plw-fm__folder:hover { background: #f4f2ff; }
.plw-fm__folder.is-on { background: #fff; border-color: #c7d2fe; box-shadow: 0 6px 16px rgba(99, 102, 241, 0.12); }
.plw-fm__fico { width: 26px; height: 26px; border-radius: 8px; flex: 0 0 auto; display: flex; align-items: center; justify-content: center; background: #eef0f7; color: #8b93a7; font-size: 13px; transition: all 0.15s; }
.plw-fm__folder.is-on .plw-fm__fico { background: #e0e7ff; color: #4f46e5; }
.plw-fm__flabel { flex: 1; min-width: 0; font-size: 12.5px; font-weight: 700; color: #64748b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.plw-fm__folder.is-on .plw-fm__flabel { color: #1e293b; font-weight: 800; }
.plw-fm__fn { flex: 0 0 auto; font-size: 10.5px; font-weight: 800; padding: 2px 7px; border-radius: 6px; background: #eef0f7; color: #94a3b8; }
.plw-fm__folder.is-on .plw-fm__fn { background: #ede9fe; color: #6d28d9; }
.plw-fm__meter { padding: 12px 14px; border-top: 1px solid #eef0f7; }
.plw-fm__meterhead { display: flex; align-items: center; justify-content: space-between; gap: 8px; font-size: 10px; font-weight: 800; letter-spacing: 0.1em; color: #94a3b8; }
.plw-fm__meterhead span:last-child { color: #4f46e5; letter-spacing: 0; }
.plw-fm__bar { height: 6px; border-radius: 99px; background: #eef0f7; overflow: hidden; margin-top: 8px; }
.plw-fm__bar > div { height: 100%; border-radius: 99px; background: linear-gradient(90deg, #8b5cf6, #6366f1); transition: width 0.3s; }

.plw-fm__main { display: flex; flex-direction: column; min-width: 0; }
.plw-fm__toolbar { display: flex; align-items: center; gap: 10px; padding: 11px 14px; border-bottom: 1px solid #eef0f7; flex-wrap: wrap; }
.plw-fm__crumb { display: flex; align-items: center; gap: 6px; min-width: 0; flex: 0 1 auto; font-size: 12px; color: #94a3b8; }
.plw-fm__crumb i { font-size: 10px; color: #cbd5e1; flex: 0 0 auto; }
.plw-fm__crumb b { font-size: 12.5px; font-weight: 800; color: #1e293b; min-width: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.plw-fm__cari { position: relative; flex: 1 1 180px; min-width: 0; display: flex; align-items: center; }
.plw-fm__cari > i { position: absolute; left: 11px; font-size: 12px; color: #94a3b8; pointer-events: none; }
.plw-fm__cari input { width: 100%; height: 36px; padding: 0 32px 0 31px; border-radius: 10px; border: 1px solid #e2e8f0; background: #f8fafc; font-size: 12.5px; color: #334155; outline: none; font-family: inherit; transition: all 0.16s; }
.plw-fm__cari input:focus { background: #fff; border-color: #a5b4fc; box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.12); }
.plw-fm__cari > button { position: absolute; right: 8px; appearance: none; border: none; background: transparent; cursor: pointer; color: #94a3b8; font-size: 11px; padding: 4px; display: flex; }
.plw-fm__cari > button:hover { color: #4f46e5; }

.plw-fm__grid { flex: 1; min-height: 0; overflow-y: auto; display: grid; grid-template-columns: repeat(auto-fill, minmax(182px, 1fr)); gap: 11px; padding: 14px; align-content: start; }
.plw-fm__file { appearance: none; font-family: inherit; cursor: pointer; display: flex; flex-direction: column; align-items: flex-start; text-align: left; padding: 13px; border-radius: 14px; border: 1px solid #e7e3fb; background: #fff; box-shadow: 0 2px 10px rgba(15, 23, 42, 0.04); transition: transform 0.18s cubic-bezier(0.22, 1, 0.36, 1), box-shadow 0.18s, border-color 0.18s; min-width: 0; }
.plw-fm__file:hover { transform: translateY(-3px); border-color: #a5b4fc; box-shadow: 0 14px 30px rgba(99, 102, 241, 0.15); }
.plw-fm__file.is-on { border-color: #6366f1; background: #f6f5ff; box-shadow: 0 12px 28px rgba(99, 102, 241, 0.18); }
.plw-fm__fileico { width: 42px; height: 42px; border-radius: 12px; display: flex; align-items: center; justify-content: center; background: #eef2ff; border: 1px solid #dbe2fe; color: #4f46e5; font-size: 19px; flex: 0 0 auto; }
.plw-fm__fileico.is-img { background: #ecfdf5; border-color: #a7f3d0; color: #059669; }
.plw-fm__filenama { display: block; width: 100%; font-size: 12.5px; font-weight: 800; color: #1e293b; margin-top: 11px; line-height: 1.35; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.plw-fm__filefile { display: block; width: 100%; font-size: 10.5px; color: #94a3b8; margin-top: 4px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.plw-fm__filefoot { display: flex; align-items: center; gap: 7px; width: 100%; margin-top: 10px; padding-top: 9px; border-top: 1px solid #f1f5f9; min-width: 0; }
.plw-fm__fileext { flex: 0 0 auto; font-size: 9.5px; font-weight: 800; letter-spacing: 0.05em; padding: 3px 7px; border-radius: 6px; background: #eef0f7; color: #64748b; }
.plw-fm__filedari { flex: 1; min-width: 0; font-size: 10px; color: #aab2c5; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

.plw-fm__kosong { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 7px; padding: 44px 22px; text-align: center; }
.plw-fm__kosongico { width: 52px; height: 52px; border-radius: 15px; display: flex; align-items: center; justify-content: center; background: #f1f5f9; color: #cbd5e1; font-size: 23px; }
.plw-fm__kosong b { font-size: 13.5px; font-weight: 800; color: #334155; margin-top: 6px; }
.plw-fm__kosong > span:last-child { font-size: 12px; color: #94a3b8; max-width: 280px; line-height: 1.55; }

.plw-fm__pratinjau { display: flex; align-items: center; gap: 12px; padding: 11px 14px; border-top: 1px solid #eef0f7; background: linear-gradient(135deg, #f7f5ff, #f2f5ff); flex-wrap: wrap; }
.plw-fm__pico { width: 38px; height: 38px; border-radius: 11px; flex: 0 0 auto; display: flex; align-items: center; justify-content: center; background: #fff; border: 1px solid #dbe2fe; color: #4f46e5; font-size: 17px; }
.plw-fm__pin { flex: 1 1 160px; min-width: 0; display: block; }
.plw-fm__pin b { display: block; font-size: 12.5px; font-weight: 800; color: #1e1b4b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.plw-fm__pin em { display: block; font-size: 11px; font-style: normal; color: #6b6597; margin-top: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.plw-fm__pbtn { appearance: none; border: none; cursor: pointer; font-family: inherit; flex: 0 0 auto; display: inline-flex; align-items: center; gap: 7px; font-size: 12px; font-weight: 800; color: #fff; padding: 9px 15px; border-radius: 11px; background: linear-gradient(135deg, #8b5cf6, #6366f1); box-shadow: 0 10px 22px rgba(99, 102, 241, 0.3); transition: transform 0.16s, box-shadow 0.16s; }
.plw-fm__pbtn:hover { transform: translateY(-1px); box-shadow: 0 14px 28px rgba(99, 102, 241, 0.4); }

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
/* Pil ringkas di puncak isi akordion. */
.plw-fsum { display: inline-flex; align-items: center; gap: 8px; max-width: 100%; margin: 6px 0 4px; padding: 6px 13px; border-radius: 999px; background: #f6f5ff; border: 1px solid #e7e3fb; font-size: 11.5px; font-weight: 700; color: #4f46e5; }
.plw-fsum i { flex: 0 0 auto; font-size: 12px; }
.plw-fsum span { min-width: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

/* KEPALA KELOMPOK BIODATA — tile ikon, judul, garis memudar.
   Judulnya datang dari skema formulir apa adanya ("Validasi Data", "Identitas
   Resmi"), jadi TIDAK dibesarkan jadi HURUF KAPITAL: itu nama kelompok yang
   dibaca kandidat saat mengisi, bukan label sistem. Kapital penuh pada frasa
   sepanjang itu juga lebih lambat dibaca. */
.plw-ghead { display: flex; align-items: center; gap: 9px; margin: 18px 0 10px; }
.plw-ghead__ico { flex: 0 0 auto; width: 28px; height: 28px; border-radius: 9px; display: flex; align-items: center; justify-content: center; background: #eef2ff; border: 1px solid #dbe2fe; color: #4f46e5; font-size: 13px; }
.plw-ghead__lbl { flex: 0 0 auto; min-width: 0; font-size: 13px; font-weight: 800; letter-spacing: 0.01em; color: #4338ca; }
.plw-ghead__n { flex: 0 0 auto; font-size: 10.5px; font-weight: 800; padding: 3px 9px; border-radius: 7px; background: #f4f2ff; color: #6d28d9; }
.plw-ghead__garis { flex: 1; min-width: 12px; height: 1px; background: linear-gradient(90deg, #e7e3fb, transparent); }

/* DUA KOLOM TETAP — seperti desain. Kelompoknya sudah memotong daftar jadi
   petak-petak pendek, jadi kolom yang ikut berubah jumlahnya per kelompok
   justru membuat mata kehilangan garis bacanya. Di bawah 720px turun jadi satu
   kolom (lihat media query) karena dua kolom di lebar itu menyisakan ruang
   nilai selebar dua kata.

   Sel adalah KARTU terpisah, bukan petak dalam kisi bergaris rambut. Bentuk
   lama menyatukan semuanya jadi satu blok bergaris 1px: rapi saat isinya
   pendek, tapi begitu satu sel memuat linimasa riwayat, garis-garis itu
   memotongnya di tempat yang tak masuk akal. */
.plw-fields { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; }
.plw-field { background: #fbfbfe; border: 1px solid #eef0f7; border-radius: 11px; padding: 10px 12px; min-width: 0; }
/* Baris riwayat membawa kepala seksinya sendiri; kotak di dalam kotak di sini
   hanya menambah satu bingkai yang tak menerangkan apa pun. */
.plw-field:has(> .plw-tl) { background: transparent; border-color: transparent; padding: 0; }
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
/* Blok "tombol buka berkas" yang dulu di sini SUDAH DIHAPUS.
   Templatenya tidak lagi memakai `.plw-lihat` sebagai tombol maupun
   `.plw-lihat__ext` — yang tersisa cuma lencana penanda pembaca di bawah
   (HANYA TIM / DIBACA KANDIDAT), dan aturan mati ini membocorkan
   `cursor: pointer`, efek hover, serta `.plw-lihat .bi { font-size: 12.5px }`
   ke sana: lencana setinggi 9,5px dengan ikon 12,5px yang mengundang diklik
   padahal tidak melakukan apa pun. */
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
/* Tempat yang diketik sendiri: petanya hasil pencarian nama + alamat, bukan
   titik yang pernah diverifikasi. Disebut supaya tidak terbaca setara dengan
   lokasi master yang koordinatnya sudah dipastikan. */
.plw-lok__tag { display: inline-block; margin-left: 6px; font-size: 10px; font-weight: 700; border-radius: 999px; padding: 1px 7px; background: #fef3c7; color: #92400e; vertical-align: middle; }
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
/* NIK, telepon, email — dibaca digit per digit saat memverifikasi berkas.
   Huruf lebar-tetap membuat selisih satu angka jatuh di kolom yang sama dan
   langsung terlihat. Lihat selMono(). */
.plw-field__v.is-mono { font-family: 'JetBrains Mono', ui-monospace, 'SFMono-Regular', Menlo, monospace; font-size: 12.5px; letter-spacing: -0.01em; }

/* ── ISIAN BERULANG — LINIMASA ────────────────────────────────────────────
   Bentuk lamanya (.plw-rows) memakai flex-wrap: tiap pasangan label-nilai
   selebar isinya sendiri, sehingga kolom yang sama berhenti di tempat berbeda
   antar baris dan uraian panjang menyeret sisanya. Yang terbaca bukan tabel,
   melainkan tumpukan teks.

   Sekarang: satu garis waktu vertikal, satu kartu per baris, isi kartu di
   GRID berkolom tetap. Kolomnya sejajar antar baris karena lebarnya tidak
   lagi ditentukan isi masing-masing. */
.plw-tl { margin-top: 6px; }

/* KEPALA SEKSI RIWAYAT — bukan tombol berbingkai penuh seperti dulu.
   Bentuknya mengikuti kepala seksi di desain: tile ikon, judul ber-tracking,
   pil jumlah, lalu garis memudar yang menutup sisa baris. Yang menandai ia
   bisa ditekan tinggal chevron di ujung — dan itu memang cukup, karena
   seluruh barisnya tetap sasaran klik. */
.plw-tl__head {
    display: flex; align-items: center; gap: 10px; width: 100%; padding: 4px 0 10px;
    appearance: none; border: 0; background: transparent;
    font: inherit; cursor: pointer; color: #4338ca; text-align: left;
}
.plw-tl__ico {
    flex: none; width: 32px; height: 32px; border-radius: 10px; display: inline-flex; align-items: center; justify-content: center;
    background: #eef2ff; border: 1px solid #c7d2fe; color: #4f46e5; font-size: 15px;
}
.plw-tl__cap { flex: 0 0 auto; max-width: 60%; font-size: 11px; font-weight: 800; letter-spacing: .12em; text-transform: uppercase; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.plw-tl__n { flex: none; font-size: 11px; font-weight: 800; padding: 3px 10px; border-radius: 8px; background: #f4f2ff; color: #6d28d9; }
/* Garis memudar — pengganti bingkai. Ia yang mengikat kepala seksi ke isinya
   tanpa menambah satu kotak lagi di dalam kotak formulir. */
.plw-tl__garis { flex: 1; min-width: 12px; height: 1px; background: linear-gradient(90deg, #e7e3fb, transparent); }
.plw-tl__chev { flex: none; color: #94a3b8; transition: transform .2s ease; }
.plw-tl__chev.is-up { transform: rotate(180deg); }
.plw-tl__head:hover .plw-tl__chev { color: #4f46e5; }

/* Tidak ada jendela gulir kedua di sini. Isi modal sendiri yang menggulir;
   dua bilah gulir bersarang membuat roda mouse menggerakkan yang salah, dan
   riwayat yang panjang justru paling sering dibaca berurutan dari atas. */
.plw-tl__body { padding: 2px 0; }

/* GARIS WAKTU.
   Rel digambar sebagai ::before milik <ol>, BUKAN border-left, supaya letak
   titiknya bisa dihitung dari satu angka (--rel) alih-alih ditebak. Bentuk
   lamanya memakai border-left + dot ber-left tetap: pusat titik meleset
   setengah lebarnya dari garis, dan itulah "bulatnya kurang pas" yang
   terlihat. Sekarang titik selalu duduk tepat di atas rel berapa pun
   ukurannya, karena posisinya diturunkan dari --rel dan --titik. */
.plw-tl__line {
    --rel: 13px;        /* jarak sumbu rel dari tepi kiri daftar */
    --titik: 26px;      /* garis tengah bulatan */
    list-style: none; margin: 0; padding: 2px 0 2px calc(var(--rel) + 19px);
    display: flex; flex-direction: column; gap: 11px; position: relative;
}
.plw-tl__line::before {
    content: ''; position: absolute; left: calc(var(--rel) - 1px); top: 10px; bottom: 10px; width: 2px;
    border-radius: 999px;
    background: linear-gradient(180deg, #c7d2fe, #eef0f7);
}
.plw-tl__item { position: relative; }

/* Titik bernomor — SATU warna, ungu merek. Bentuk lamanya menggilir enam warna
   pelangi per baris; itu memang menandai barisnya, tapi warna di antarmuka ini
   sudah punya arti lain (hijau lulus, kuning menunggu, merah gagal), dan
   riwayat kerja yang berwarna-warni terbaca seolah tiap barisnya berstatus
   berbeda. Nomornya sendiri sudah cukup membedakan. */
.plw-tl__dot {
    position: absolute; top: 13px;
    /* Item mulai di (--rel + 19px); mundur sejauh itu lalu setengah bulatan
       lagi, maka pusat titik jatuh persis di sumbu rel. */
    left: calc(-19px - var(--titik) / 2);
    width: var(--titik); height: var(--titik);
    display: inline-flex; align-items: center; justify-content: center;
    color: #fff; border: 3px solid #fff; border-radius: 999px;
    font-size: 11px; font-weight: 800; font-variant-numeric: tabular-nums;
    background: linear-gradient(135deg, #8b5cf6, #6366f1);
    box-shadow: 0 0 0 1px #e7e3fb;
    transition: transform .18s ease;
}
.plw-tl__item:hover .plw-tl__dot { transform: scale(1.08); }

/* Kartu satu baris riwayat — datar dan tenang (#fbfbfe), bukan kartu putih
   berpita warna di dalam kartu putih formulir. Kartu di dalam kartu dengan
   ketinggian yang sama membuat batas keduanya hilang. */
.plw-tl__card {
    position: relative;
    background: #fbfbfe; border: 1px solid #eef0f7; border-radius: 14px; padding: 13px 15px;
    transition: border-color .18s ease, box-shadow .18s ease;
    animation: plwCardIn .32s ease both;
}
.plw-tl__card:hover { border-color: #c7d2fe; box-shadow: 0 8px 22px rgba(99, 102, 241, .1); }

/* KEPALA KARTU — judul kiri, pil periode kanan. */
.plw-tl__top { display: flex; align-items: flex-start; justify-content: space-between; gap: 10px; flex-wrap: wrap; margin-bottom: 10px; }
.plw-tl__judul { min-width: 0; font-size: 14px; font-weight: 800; color: #1e293b; letter-spacing: -.01em; line-height: 1.35; text-wrap: pretty; }
.plw-tl__periode { flex: 0 0 auto; font-size: 10.5px; font-weight: 800; padding: 4px 10px; border-radius: 8px; white-space: nowrap; background: #f4f2ff; border: 1px solid #e7e3fb; color: #6d28d9; }

/* Kisi isian ringkas — kartu kecil berlatar putih di atas kartu #fbfbfe,
   mengikuti kisi biodata di desain. Sel polos tanpa latar membuat label dan
   nilai dari kolom bersebelahan terbaca menyambung jadi satu kalimat. */
.plw-tl__grid {
    display: grid; grid-template-columns: repeat(auto-fit, minmax(min(158px, 100%), 1fr));
    gap: 8px;
}
.plw-tl__cell { min-width: 0; background: #fff; border: 1px solid #eef0f7; border-radius: 11px; padding: 9px 11px; }
.plw-tl__k { display: block; font-size: 9.5px; font-weight: 800; letter-spacing: .07em; text-transform: uppercase; color: #a2a9ba; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.plw-tl__v { display: block; margin-top: 3px; font-size: 12.5px; font-weight: 700; color: #334155; line-height: 1.45; word-break: break-word; }
/* Nominal rupiah: angka rata, jadi ribuan sejajar antar baris. */
.plw-tl__v.is-rp, .plw-field__v.is-rp { font-variant-numeric: tabular-nums; color: #047857; }

/* URAIAN — blok sendiri di bawah kisi, dipisah garis tipis. */
.plw-tl__note { margin-top: 10px; padding: 10px 12px; border-radius: 11px; background: #fff; border: 1px solid #eef0f7; }
/* Baris yang isinya uraian saja: tak ada kisi di atasnya. */
.plw-tl__note:first-child { margin-top: 0; }

/* ── LAMPIRAN SATU BARIS RIWAYAT (bisa lebih dari satu lembar) ────────────
   Jalur sendiri di dasar kartu, bukan sel di dalam kisi: jumlahnya tak
   terbatas, dan satu sel selebar 150px tidak bisa memuat daftar berkas tanpa
   memotong namanya jadi tiga huruf. */
.plw-tl__berkas { margin-top: 11px; display: flex; flex-direction: column; gap: 6px; }
.plw-tl__berkas:first-child { margin-top: 0; }
.plw-tl__berkashead { display: flex; align-items: center; gap: 6px; font-size: 9.5px; font-weight: 800; letter-spacing: .07em; text-transform: uppercase; color: #a2a9ba; }
.plw-tl__berkashead .bi { font-size: 11px; }
.plw-tl__berkasn { display: inline-grid; place-items: center; min-width: 16px; height: 16px; padding: 0 5px; border-radius: 999px; background: #eef2ff; color: #4338ca; font-size: 9.5px; letter-spacing: 0; }
.plw-tl__file {
    display: flex; align-items: center; gap: 9px; width: 100%; padding: 8px 10px;
    appearance: none; border: 1px solid #e7e3fb; border-radius: 11px; background: #fff;
    font: inherit; cursor: pointer; text-align: left; transition: all .16s;
}
.plw-tl__file:hover { border-color: #a5b4fc; background: #f8f7ff; box-shadow: 0 4px 12px rgba(99, 102, 241, .1); }
.plw-tl__fileico { flex: none; width: 28px; height: 28px; border-radius: 9px; display: inline-flex; align-items: center; justify-content: center; background: #eef2ff; border: 1px solid #c7d2fe; color: #dc2626; font-size: 13px; }
.plw-tl__fileico .bi-file-earmark-image-fill { color: #4f46e5; }
.plw-tl__filenama { flex: 1; min-width: 0; font-size: 12px; font-weight: 700; color: #334155; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.plw-tl__fileext { flex: none; font-size: 9px; font-weight: 800; color: #8b93a7; background: #eef0f7; border-radius: 5px; padding: 2px 6px; }
.plw-tl__filego { flex: none; font-size: 11px; font-weight: 800; color: #4f46e5; background: #eef2ff; border: 1px solid #c7d2fe; border-radius: 8px; padding: 3px 10px; }

/* Satu pertanyaan berkas dengan beberapa lembar: tombolnya berjajar & melipat. */
.plw-berkasv { display: flex; flex-wrap: wrap; gap: 6px; }

/* ── SEKSI DOKUMEN TERLAMPIR (mode Daftar) ─────────────────────────────────
   Kartu berjajar, satu per lembar. Yang belum diunggah tetap tampil sebagai
   kartu redup dan tidak bisa ditekan — kekosongan itulah yang paling perlu
   terbaca saat memverifikasi berkas. */
.plw-docgrid { display: grid; grid-template-columns: repeat(auto-fill, minmax(min(228px, 100%), 1fr)); gap: 9px; }
.plw-doc { appearance: none; font-family: inherit; text-align: left; display: flex; align-items: center; gap: 11px; min-width: 0; padding: 11px 13px; border-radius: 13px; background: #fff; border: 1px solid #e7e3fb; transition: border-color 0.16s, box-shadow 0.16s, transform 0.16s; }
button.plw-doc { cursor: pointer; }
button.plw-doc:hover { border-color: #a5b4fc; box-shadow: 0 8px 22px rgba(99, 102, 241, 0.12); transform: translateY(-1px); }
.plw-doc.is-kosong { background: #fbfbfe; border-color: #eef0f7; opacity: 0.68; }
.plw-doc__ico { flex: 0 0 auto; width: 40px; height: 40px; border-radius: 12px; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, #f4f2ff, #eef2ff); border: 1px solid #dbe2fe; color: #dc2626; font-size: 17px; }
.plw-doc__ico .bi-file-earmark-image-fill { color: #4f46e5; }
.plw-doc.is-kosong .plw-doc__ico { background: #f4f6fb; border-color: #eef0f7; color: #cbd5e1; }
.plw-doc__in { flex: 1; min-width: 0; display: block; }
.plw-doc__nama { display: block; font-size: 12.5px; font-weight: 800; color: #1e293b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.plw-doc.is-kosong .plw-doc__nama { color: #64748b; }
.plw-doc__file { display: block; font-size: 10.5px; color: #94a3b8; margin-top: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.plw-doc__ext { flex: 0 0 auto; font-size: 9.5px; font-weight: 800; letter-spacing: 0.05em; padding: 3px 8px; border-radius: 7px; background: #eef2ff; color: #4338ca; }

@media (max-width: 640px) {
    .plw-tl__line { --rel: 11px; --titik: 23px; padding-left: calc(var(--rel) + 16px); }
    .plw-tl__dot { left: calc(var(--titik) / -2 - 16px); }
    .plw-tl__card { padding: 11px 12px; }
    .plw-tl__grid { gap: 7px; }
    .plw-tl__v { font-size: 12px; }
    .plw-tl__judul { font-size: 13px; }
    .plw-tl__body { max-height: 300px; }
}

/* ── Tombol keputusan ────────────────────────────────────────────────────
   Grid auto-fit, bukan flex ber-min-width tetap. Sebelumnya tiap tombol
   dipaksa minimal 130–150px; di drawer 460px (breakpoint <1180px) tiga tombol
   + gap sudah memakan 410px, sehingga label panjang pecah jadi dua baris dan
   tingginya jadi tidak sama.

   Dengan auto-fit, jumlah kolom menyesuaikan lebar yang tersedia sendiri:
   drawer lebar → 3 sebaris; menyempit → 2 + 1; sangat sempit → menumpuk.
   Tidak ada breakpoint yang perlu ditebak untuk tiap kombinasi tombol. */
/* Kaki modal: keterangan + deretan keputusan. Batas tingginya sendiri —
   pada tahap yang menahan banyak syarat, panel keterangannya bisa lebih
   tinggi daripada isi modalnya, dan tombol keputusan justru terdorong keluar
   layar oleh penjelasan tentang tombol itu sendiri. */
/* Kaki modal dibatasi SEPEREMPAT layar, bukan separuh.
   Yang dibaca orang ada di badan modal; kaki hanya tempat mengetuk palu.
   Ketika kakinya boleh setinggi 46vh, tiga baris keterangan + dua baris tombol
   cukup untuk menyisakan ruang baca setinggi empat baris teks — dan isi yang
   jadi dasar keputusan itu justru terdorong keluar pandangan. */
.plw-foot { max-height: 26vh; overflow-y: auto; overscroll-behavior: contain; }
.plw-actions { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 9px; }

/* SATU bentuk tombol keputusan; warnanya dipilih lewat kelas nada, karena
   daftar tombolnya kini datang dari master dan bisa bertambah. */
.plw-btn-putus {
    appearance: none; cursor: pointer; font-family: inherit; border: none;
    padding: 11px 14px; border-radius: 13px; min-height: 42px;
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
/* TAHAN DULU sebaris dengan keputusan lain, tapi tenang: ini penundaan, bukan
   palu. Warnanya netral supaya tidak berebut perhatian dengan tiga tombol yang
   benar-benar memindahkan kandidat. */
.plw-btn-putus.is-hold { background: #fff; border: 1px dashed #d9def0; color: #64748b; }
.plw-btn-putus.is-hold:hover { background: #f8fafc; border-color: #c7d2fe; color: #4f46e5; }
/* Empat tombol berbagi satu baris: label dirapatkan supaya "Talent Pool" tetap
   utuh sebaris tanpa perlu memperkecil tombolnya. */
.plw-actions.is-padat .plw-btn-putus { padding-left: 9px; padding-right: 9px; font-size: 12.5px; gap: 6px; }

/* KEPUTUSAN DARI KANDIDAT — SEBARIS dengan labelnya, sengaja lebih tenang.
   Ini bukan penilaian tim, jadi bobot visualnya tidak boleh menyaingi tombol di
   atas — dan judul setinggi satu baris penuh yang cuma menamai satu tombol
   adalah tinggi yang dibayar tanpa imbalan. */
.plw-actions2 { display: flex; align-items: center; flex-wrap: wrap; gap: 8px; margin-top: 10px; padding-top: 10px; border-top: 1px dashed #e3e6f0; }
.plw-actions2__lbl { display: inline-flex; align-items: center; gap: 6px; flex: 0 0 auto; font-size: 10px; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; color: #a2a9ba; }
.plw-actions2__lbl em { font-style: normal; letter-spacing: 0; text-transform: none; color: #7c3aed; }
.plw-btn-kandidat {
    appearance: none; cursor: pointer; font-family: inherit; flex: 0 1 auto;
    display: inline-flex; align-items: center; justify-content: center; gap: 7px;
    padding: 8px 13px; min-height: 34px; border-radius: 10px;
    border: 1px solid #ddd6fe; background: #faf9ff; color: #6d28d9;
    font-size: 12.5px; font-weight: 800; line-height: 1.2;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis; min-width: 0;
    transition: background .16s, border-color .16s;
}
.plw-btn-kandidat:hover:not(:disabled) { background: #f3f0ff; border-color: #c4b5fd; }
.plw-btn-kandidat:disabled { opacity: .45; cursor: not-allowed; }
.plw-btn-kandidat .bi { flex: none; }
/* Pemanggil keterangan yang tadi ditutup — kecil, di ujung baris. */
.plw-actions2__info {
    appearance: none; cursor: pointer; font-family: inherit; margin-left: auto; flex: 0 0 auto;
    display: inline-flex; align-items: center; gap: 7px;
    width: 32px; height: 32px; justify-content: center; padding: 0;
    border-radius: 9px; border: 1px solid #f2e4c4; background: #fffdf7; color: #b45309;
    font-size: 13px; transition: all .16s;
}
.plw-actions2__info:hover { background: #fff8ec; border-color: #e8cf9a; }
.plw-actions2__info.is-solo { width: auto; height: auto; margin: 10px 0 0; padding: 8px 13px; font-size: 12px; font-weight: 800; }

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
/* Lapis toast bersama (--wca-z-toast, evo-theme.css). 1300 dulu cukup untuk
   modal EVO (1200), tapi TIDAK untuk lightbox berkas (.plw-lb 1250 & 1500),
   panel unduhan (3000), maupun drawer — padahal justru dari sanalah aksi
   simpan/unduh dijalankan. Satu angka bersama menutup seluruh selisih itu. */
.plw-toast {
    position: fixed; bottom: 24px; right: 24px; z-index: var(--wca-z-toast, 100000);
    display: flex; align-items: center; gap: 9px; padding: 12px 18px;
    max-width: min(520px, calc(100vw - 48px));
    border-radius: 13px; background: #0f172a; color: #fff;
    font-size: 13.5px; font-weight: 700; line-height: 1.5;
    box-shadow: 0 18px 40px rgba(0, 0, 0, 0.3);
}
/* PANEL UNDUHAN MENEMPATI SUDUT YANG SAMA (.plw-unduhan, kanan-bawah).
   Saat ia terbuka, toast digeser ke ATAS panel alih-alih menimpanya — dua-duanya
   jawaban atas tindakan admin, dan yang satu tidak boleh menghapus yang lain
   dari pandangan. Kelasnya dipasang halaman saat daftar unduhan tidak kosong. */
.plw-toast.is-atas-unduhan { bottom: 96px; }

@media (max-width: 560px) {
    /* Melebar penuh: pada 360px, toast sudut menyisakan ruang teks selebar
       dua kata dan pesan sepanjang "Waktu berakhir harus setelah waktu mulai."
       terpotong jadi lima baris sempit. */
    .plw-toast {
        left: 12px; right: 12px; bottom: 16px;
        max-width: none; align-items: flex-start;
    }
    .plw-toast.is-atas-unduhan { bottom: 92px; }
    .plw-toast .bi { flex: none; margin-top: 1px; }
}
.plw-toast.is-err { background: #dc2626; }
.plw-toast .bi { color: #34d399; }
.plw-toast.is-err .bi { color: #fff; }
.plw-toast-enter-active, .plw-toast-leave-active { transition: opacity 0.25s, transform 0.25s; }
.plw-toast-enter-from, .plw-toast-leave-to { opacity: 0; transform: translateY(12px); }

/* ═══ RESPONSIF ═══
   Tiga titik henti, masing-masing menjawab satu hal yang benar-benar patah:

     1440px — kisi penyaring enam kolom mulai menyempitkan tiap kotak sampai
              teks pilihannya terpotong; dipecah jadi tiga kolom.
     1180px — panel program menyempit; tabel list kehilangan kolom yang tak
              esensial (kampus & tanggal tetap terbaca di kartu detail).
      992px — papan pindah ke bawah panel; kanban menumpuk vertikal; tiap
              baris list MELIPAT jadi kartu berlabel.

   Tabel yang digulung mendatar sengaja tidak dipakai di ponsel: membaca nama
   orang dengan menggeser layar ke kanan bukan cara siapa pun bekerja. */
@media (max-width: 1440px) {
    .plw-fgrid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
    .plw-f--cari { grid-column: span 2; }
}
@media (max-width: 1179.98px) {
    .plw-panel { width: 264px; flex-basis: 264px; }
    /* Kampus & tanggal keluar dari baris: keduanya masih bisa dicari lewat
       penyaring, sementara nama-posisi-tahap-keadaan adalah tulang punggung
       baris yang tak boleh menyusut.
       Nomor anaknya bergeser satu sejak kolom centang jadi yang pertama:
       Kampus kini anak ke-5, Melamar ke-8. */
    .plw-list__head > :nth-child(5),
    .plw-list__head > :nth-child(8),
    .plw-lcell[data-k='Kampus'],
    .plw-lcell[data-k='Melamar'] { display: none; }
    .plw-list__head,
    .plw-lrow { grid-template-columns: 26px 1.9fr 1.4fr 1.3fr 1fr 1.1fr 92px; }
}
@media (max-width: 991.98px) {
    .plw { flex-direction: column; height: auto; overflow: visible; }
    .plw-panel { width: 100%; flex: 0 0 auto; border-right: 0; border-bottom: 1px solid rgba(226, 232, 240, 0.75); }
    .plw-panel__list { flex-direction: row; overflow-x: auto; padding: 4px 16px 14px; }
    .plw-prog { flex: 0 0 250px; }
    .plw-kanban { flex-direction: column; }
    .plw-col { width: 100%; flex: 1 1 auto; }
    .plw-main { padding: 20px 16px 44px; }

    .plw-fgrid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .plw-f--cari { grid-column: span 2; }

    /* BARIS LIST → KARTU. Kolomnya dilipat jadi tumpukan, dan tiap nilai
       mendapat label dari `data-k` — tanpa itu "Universitas Sriwijaya" dan
       "PRODUCTION SUPERVISOR" berdiri berdampingan tanpa keterangan apa pun. */
    .plw-list__head { display: none; }
    .plw-lrow { position: relative; display: flex; flex-direction: column; align-items: stretch; gap: 8px; padding: 14px 15px; }
    /* Kotak centang naik ke sudut kartu. Sebagai anak tumpukan biasa ia jadi
       satu baris kosong berisi satu kotak — dan kepala tabel yang biasanya
       memuatnya memang disembunyikan di lebar ini. */
    .plw-lcek { position: absolute; top: 12px; right: 13px; }
    .plw-lcek--head { display: none; }
    .plw-lcell--who { padding-right: 30px; }
    .plw-lsel__aksi { margin-left: 0; width: 100%; }
    .plw-lsel__aksi > button { flex: 1 1 108px; justify-content: center; }
    .plw-lcell { flex-wrap: wrap; }
    .plw-lcell[data-k]::before {
        content: attr(data-k);
        flex: 0 0 84px;
        font-size: 9.5px; font-weight: 800; letter-spacing: 0.1em; text-transform: uppercase; color: #a2a9ba;
    }
    .plw-lcell--act { justify-content: flex-start; padding-top: 4px; border-top: 1px solid #f1f5f9; }
    .plw-ltgl { flex-direction: row; align-items: baseline; gap: 6px; }
}
/* FILE MANAGER di layar sempit: folder jadi baris chip mendatar di atas petak.
   Panel kiri selebar 248px pada tablet menyisakan ruang berkas yang lebih
   sempit dari satu kartu — folder yang tak bisa ditinggalkan justru memakan
   tempat isi yang dicari. */
@media (max-width: 900px) {
    .plw-fm { grid-template-columns: minmax(0, 1fr); min-height: 0; }
    .plw-fm__side { border-right: 0; border-bottom: 1px solid #eef0f7; }
    .plw-fm__sidehead { display: none; }
    .plw-fm__folders { flex-direction: row; overflow-x: auto; overflow-y: hidden; padding: 10px 12px; gap: 7px; }
    .plw-fm__folder { width: auto; flex: 0 0 auto; border-color: #e7e3fb; background: #fff; }
    .plw-fm__flabel { max-width: 150px; }
    .plw-fm__meter { display: none; }
    .plw-fm__grid { grid-template-columns: repeat(auto-fill, minmax(158px, 1fr)); max-height: none; }
}
/* Dua kolom biodata butuh ruang nilai yang layak; di bawah 720px keduanya
   menyisakan lebar selebar dua kata dan setiap alamat terpotong tiga baris. */
@media (max-width: 720px) {
    .plw-fields { grid-template-columns: minmax(0, 1fr); }
}
@media (max-width: 520px) {
    .plw-fm__grid { grid-template-columns: minmax(0, 1fr); }
    .plw-fm__pbtn { width: 100%; justify-content: center; }
}
@media (max-width: 640px) {
    .plw-fgrid { grid-template-columns: minmax(0, 1fr); }
    .plw-f--cari { grid-column: span 1; }
    .plw-frow__isi > .plw-f--tgl { flex-basis: 100%; }
    .plw-fquick { width: 100%; }
    .plw-fq { flex: 1; text-align: center; }
    .plw-moderow { align-items: flex-start; }
    .plw-modes { width: 100%; }
    .plw-mode { flex: 1; justify-content: center; }
    .plw-pagerbar { justify-content: center; }
    .plw-pagerbar__info, .plw-pagerbar__per { width: 100%; justify-content: center; text-align: center; }
}
/* ── PONSEL: PANEL PENYARING JADI LEMBAR TARIK, DIPANGGIL FAB ──────────────
   Enam isian bertumpuk memakan satu layar penuh sebelum satu kandidat pun
   terlihat. Yang dibuka orang di ponsel hampir selalu papannya — penyaring
   dipakai sekali lalu tidak disentuh lagi sepanjang sesi. Jadi ia dipindahkan
   ke lembar yang muncul saat dipanggil, dan yang tinggal di layar cuma satu
   tombol bulat yang ikut membawa hitungan penyaring aktifnya.

   Lapisannya sengaja DI BAWAH 1200 (topeng modal) supaya modal peninjauan
   tetap menutupi keduanya; di atas 1030 (sidebar) supaya lembarnya tidak
   tertimbun navigasi. */
@media (max-width: 767.98px) {
    .plw-toolbar {
        position: fixed; left: 0; right: 0; bottom: 0; z-index: 1095;
        margin: 0; max-height: 84vh;
        display: flex; flex-direction: column;
        border-radius: 18px 18px 0 0; border-bottom: 0;
        box-shadow: 0 -14px 40px rgba(15, 23, 42, .2);
        transform: translateY(101%);
        transition: transform .26s cubic-bezier(.22, 1, .36, 1);
    }
    .plw-toolbar.is-buka { transform: translateY(0); }
    /* Pegangan lembar — penanda bahwa yang muncul ini bisa ditutup. */
    .plw-fhead { position: relative; flex: none; padding-top: 15px; }
    .plw-fhead::before {
        content: ''; position: absolute; top: 7px; left: 50%; transform: translateX(-50%);
        width: 38px; height: 4px; border-radius: 999px; background: #e2e5f0;
    }
    .plw-fbody { flex: 1 1 auto; min-height: 0; overflow-y: auto; overscroll-behavior: contain; }
    /* Ditutup lewat FAB atau tirai; tombol lipat jadi pilihan ketiga yang
       menyelesaikan hal yang sama. */
    .plw-fhead__tgl { display: none; }

    .plw-ftirai {
        display: block; position: fixed; inset: 0; z-index: 1090;
        background: rgba(15, 23, 42, .45); backdrop-filter: blur(2px);
    }

    .plw-fab {
        display: inline-grid; place-items: center; position: fixed;
        right: 16px; bottom: calc(16px + env(safe-area-inset-bottom, 0px)); z-index: 1096;
        width: 54px; height: 54px; border-radius: 50%; border: none; cursor: pointer;
        color: #fff; font-size: 19px;
        background: linear-gradient(135deg, #8b5cf6, #6366f1);
        box-shadow: 0 12px 28px rgba(99, 102, 241, .42);
        transition: transform .16s, box-shadow .16s;
    }
    .plw-fab:active { transform: scale(.94); }
    /* Ada penyaring yang menyala: warnanya berubah, karena papan yang
       tersaring tanpa penanda apa pun terbaca sebagai data yang hilang. */
    .plw-fab.is-aktif { background: linear-gradient(135deg, #f59e0b, #ea580c); box-shadow: 0 12px 28px rgba(234, 88, 12, .42); }
    .plw-fab__n {
        position: absolute; top: -3px; right: -3px; min-width: 21px; height: 21px; padding: 0 5px;
        border-radius: 999px; background: #fff; color: #b45309; border: 2px solid #ea580c;
        font-size: 10.5px; font-weight: 800; display: grid; place-items: center;
    }
}

/* Ponsel: tombol keputusan menumpuk penuh selebar drawer. Di lebar sekecil ini
   tiga tombol sebaris membuat labelnya terpotong elipsis — lebih baik satu per
   satu, dan sekalian lebih aman disentuh. */
@media (max-width: 575.98px) {
    .plw-actions, .plw-actions.is-padat { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .plw-actions.is-padat .plw-btn-putus { font-size: 12.5px; padding-left: 10px; padding-right: 10px; gap: 6px; }
    /* Kaki boleh sedikit lebih tinggi di ponsel: tombolnya menumpuk dua kolom,
       dan badan modal di layar setinggi itu tetap menggulir dengan nyaman. */
    .plw-foot { max-height: 38vh; }
    .plw-actions2 { gap: 7px; }
    .plw-actions2__info { margin-left: 0; }
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

/* "Apa yang belum tuntas" di atas tombol keputusan — chip mendatar, bukan
   butir bertumpuk (lihat catatan di template). */
.plw-kuota__isi { min-width: 0; flex: 1; display: flex; flex-direction: column; gap: 5px; }
/* Tombol tutup keterangan. Kecil dan tak berlatar: ia jalan keluar, bukan
   tindakan — dan tidak boleh terbaca sebagai "batalkan" pada bilah berwarna. */
.plw-kuota__x {
    appearance: none; cursor: pointer; flex: 0 0 auto; align-self: flex-start;
    width: 24px; height: 24px; display: flex; align-items: center; justify-content: center;
    border: none; background: transparent; border-radius: 7px;
    color: #b45309; font-size: 11px; opacity: .65; transition: all .16s;
}
.plw-kuota__x:hover { opacity: 1; background: rgba(255, 255, 255, 0.7); }
.plw-kuota__chips { display: flex; flex-wrap: wrap; gap: 6px; }
.plw-kuota__chip { display: inline-flex; align-items: baseline; gap: 6px; max-width: 100%; padding: 4px 10px; border-radius: 8px; background: rgba(255, 255, 255, 0.72); border: 1px solid #f2e4c4; line-height: 1.35; min-width: 0; }
.plw-kuota__chip b { flex: 0 0 auto; font-size: 11.5px; font-weight: 800; color: #92660a; }
.plw-kuota__chip em { min-width: 0; font-style: normal; font-size: 11px; color: #a08040; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

@media (max-width: 640px) {
    /* Kepala modal: avatar + nama + tombol cetak tak muat sebaris di ponsel. */
    .plw-hero { padding-top: 15px; }
    .plw-hero__row { gap: 10px; }
    .plw-hero__avatar { width: 44px; height: 44px; border-radius: 13px; font-size: 15px; }
    .plw-hero__name { font-size: 17px; }
    .plw-drawer__cetak { order: 3; }
    .plw-tabpane { gap: 14px; }
    .plw-alur { padding: 15px 16px; }
    .plw-test { padding: 11px; gap: 5px 9px; }
    /* Pil status turun menemani isinya — di lebar ini kolom ketiga
       menyisakan terlalu sedikit ruang untuk nama aktivitas. */
    .plw-test { grid-template-columns: auto minmax(0, 1fr); }
    .plw-test__score, .plw-test__pill { grid-column: 2; justify-self: start; }
    .plw-test__aksi > * { flex: 1 1 auto; justify-content: center; }
}

/* Layar sangat lebar: kartu boleh bernapas, tapi teksnya tidak boleh melar. */
@media (min-width: 1920px) {
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
