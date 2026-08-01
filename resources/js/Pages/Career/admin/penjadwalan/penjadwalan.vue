<!--
    WEB CAREER — PENJADWALAN TES.

    Tata letak mengikuti template desain `docs/refrences/Template design/
    Penjadwalan Tes.dc.html`: latar berblob, kepala halaman ber-ikon, tab
    kategori di kanan, dua panel (konfigurasi & kandidat), lalu daftar
    penjadwalan berkartu. Shell (sidebar + navbar) datang dari CareerShell,
    jadi di sini hanya isi halamannya.

    Komponen Element Plus disisakan hanya di tempat yang memang memerlukannya —
    pemilih program (butuh pencarian) dan pemilih tanggal — lalu digayakan ulang
    agar menyatu. Sisanya markup sendiri supaya bentuknya persis dan tidak
    bergantung pada gaya bawaan pustaka.
-->
<template>
    <div class="pjd">
        <span class="pjd-blob pjd-blob--a"></span>
        <span class="pjd-blob pjd-blob--b"></span>

        <div class="pjd-wrap">
            <!-- ═══ KEPALA HALAMAN ═══ -->
            <div class="pjd-head">
                <div class="pjd-head__l">
                    <div class="pjd-head__title">
                        <span class="pjd-head__ico">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" /><path d="M3 10h18M8 2v4M16 2v4" /><path d="M9 15l2 2 4-4" /></svg>
                        </span>
                        <h1>Penjadwalan Tes</h1>
                    </div>
                    <p>Susun sesi tes online untuk kandidat — pilih program, paket tes, jendela waktu, lalu kirim token akses secara otomatis.</p>
                </div>

                <!-- Tab kategori: dari Master Talent Acquisition, bukan hardcode -->
                <div class="pjd-tabs">
                    <button
                        v-for="t in opsi.talent"
                        :key="t.kode"
                        type="button"
                        class="pjd-tab"
                        :class="{ 'is-on': kategori === t.kode }"
                        @click="gantiKategori(t.kode)"
                    >
                        {{ t.nama }}
                    </button>
                </div>
            </div>

            <div class="pjd-two">
                <!-- ═══════════ KONFIGURASI TES ═══════════ -->
                <section class="pjd-panel">
                    <div class="pjd-panel__head">
                        <span class="pjd-panel__ico">
                            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 6h11M4 12h7M4 18h13" /><circle cx="18" cy="6" r="2" /><circle cx="14" cy="12" r="2" /><circle cx="20" cy="18" r="2" /></svg>
                        </span>
                        <span class="pjd-panel__ttl">Konfigurasi Tes</span>
                    </div>

                    <div class="pjd-panel__body">
                        <!-- Program -->
                        <div>
                            <label class="pjd-lbl">Program</label>
                            <el-select
                                v-model="form.programId"
                                filterable
                                placeholder="Pilih program"
                                class="pjd-select"
                                @change="onProgram"
                            >
                                <el-option v-for="p in programKategori" :key="p.id" :label="p.nama" :value="p.id">
                                    <span>{{ p.nama }}</span>
                                    <span class="pjd-opttag">{{ p.alurNama || 'alur belum diatur' }}</span>
                                </el-option>
                            </el-select>
                            <div v-if="programTerpilih && !programTerpilih.alurId" class="pjd-note pjd-note--warn">
                                <i class="bi bi-exclamation-triangle"></i>
                                Program ini belum terhubung ke alur seleksi. Atur dulu di Program Kegiatan.
                            </div>
                        </div>

                        <!-- Ujian dari ALUR PROGRAM, bukan daftar jenis tes global. Yang
                             dipilih admin = tahap yang juga dilihat kandidat di portalnya. -->
                        <div>
                            <label class="pjd-lbl">{{ tesAlur.length > 1 ? 'Ujian Mana yang Dijadwalkan' : 'Ujian yang Dijadwalkan' }}</label>

                            <!-- Satu program bisa punya beberapa ujian online di alurnya, dan
                                 masing-masing butuh paket + jendela waktunya sendiri — jadi
                                 pilihan ini tak bisa dihilangkan. Kalau hanya satu, ia dipilih
                                 otomatis dan cukup ditampilkan sebagai keterangan. -->
                            <div v-if="tesAlur.length === 1" class="pjd-note">
                                <i class="bi bi-info-circle"></i>
                                Alur program ini hanya punya satu ujian online — sudah dipilih otomatis.
                            </div>

                            <div v-loading="memuatTes" class="pjd-stages">
                                <div v-if="!memuatTes && !tesAlur.length" class="pjd-empty pjd-empty--sm">
                                    <span class="pjd-empty__ico">
                                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" /><path d="M3 10h18M8 2v4M16 2v4" /></svg>
                                    </span>
                                    <p>{{ alasanTes || 'Pilih program terlebih dahulu' }}</p>
                                </div>

                                <button
                                    v-for="t in tesAlur"
                                    :key="t.tahapUrutan + '-' + t.tesUrutan"
                                    type="button"
                                    class="pjd-stage"
                                    :class="{ 'is-on': tesTerpilihKey === t.tahapUrutan + '-' + t.tesUrutan }"
                                    @click="pilihTes(t)"
                                >
                                    <span class="pjd-stage__no">{{ t.tahapUrutan }}</span>
                                    <span class="pjd-stage__in">
                                        <span class="pjd-stage__nama">{{ t.tahapLabel }}<template v-if="t.multi"> › {{ t.tesLabel }}</template></span>
                                        <span class="pjd-stage__meta">
                                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="4" width="16" height="16" rx="2" /><path d="M9 9h6v6H9z" /></svg>
                                            {{ t.tipeNama || 'Tes Online' }}<template v-if="t.peran === 'INFORMATIF'"> · informatif</template>
                                        </span>
                                    </span>
                                    <span class="pjd-stage__badge" :class="{ 'is-wait': t.menunggu > 0 }">{{ t.menunggu }} menunggu</span>
                                </button>
                            </div>
                        </div>

                        <!-- Paket tes dari HCLearn -->
                        <div>
                            <label class="pjd-lbl">Nama Ujian / Paket Tes</label>
                            <div class="pjd-field">
                                <svg class="pjd-field__ico" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="2.2" stroke-linecap="round"><circle cx="11" cy="11" r="7" /><path d="M21 21l-4.3-4.3" /></svg>
                                <input v-model="cariPaket" type="text" class="pjd-input" placeholder="Cari paket tes…" @input="debounceCari">
                            </div>

                            <div v-loading="memuatPaket" class="pjd-pkgs">
                                <div v-if="!memuatPaket && !paket.length" class="pjd-empty pjd-empty--sm pjd-empty--span">
                                    <span class="pjd-empty__ico">
                                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="3" width="16" height="18" rx="2" /><path d="M8 8h8M8 12h8M8 16h4" /></svg>
                                    </span>
                                    <p>Paket tes tidak ditemukan</p>
                                </div>

                                <button
                                    v-for="p in paket"
                                    :key="p.Id_Master_Ujian"
                                    type="button"
                                    class="pjd-pkg"
                                    :class="{ 'is-on': form.idMasterUjian === p.Id_Master_Ujian }"
                                    @click="pilihPaket(p)"
                                >
                                    <span class="pjd-pkg__head">
                                        <span class="pjd-pkg__nama">{{ p.Nama_Ujian }}</span>
                                        <span v-if="form.idMasterUjian === p.Id_Master_Ujian" class="pjd-pkg__check">
                                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5" /></svg>
                                        </span>
                                    </span>
                                    <span class="pjd-pkg__kode">{{ p.Kode_Paket }}</span>
                                    <span class="pjd-pkg__chips">
                                        <span v-for="d in p.detail" :key="d.id_paket_detail" class="pjd-chip">{{ d.nama_indikator }}</span>
                                    </span>
                                    <span class="pjd-pkg__foot">
                                        <span><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="3" width="16" height="18" rx="2" /><path d="M8 8h8M8 12h8M8 16h4" /></svg>{{ p.Jumlah_Soal }} soal</span>
                                        <span><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 21h6V10H3zM9 21h6V3H9zM15 21h6v-7h-6z" /></svg>{{ p.Tingkatan }}</span>
                                    </span>
                                </button>
                            </div>
                        </div>

                        <!-- Jendela waktu -->
                        <div class="pjd-times">
                            <div>
                                <label class="pjd-lbl">Waktu Mulai</label>
                                <!-- default-time: memilih TANGGAL saja jangan berakhir
                                     di 00:00 — jendela ujian yang mulai & berakhir di
                                     tengah malam praktis mustahil dikerjakan. Mulai
                                     jatuh ke 08.00, berakhir ke 23.59. -->
                                <el-date-picker
                                    v-model="form.waktuMulai"
                                    type="datetime"
                                    placeholder="Tanggal &amp; jam mulai"
                                    format="DD MMM YYYY HH:mm"
                                    value-format="YYYY-MM-DD HH:mm:ss"
                                    :default-time="jamMulaiBawaan"
                                    class="pjd-date"
                                />
                            </div>
                            <div>
                                <label class="pjd-lbl">Waktu Berakhir</label>
                                <el-date-picker
                                    v-model="form.waktuAkhir"
                                    type="datetime"
                                    placeholder="Tanggal &amp; jam berakhir"
                                    format="DD MMM YYYY HH:mm"
                                    value-format="YYYY-MM-DD HH:mm:ss"
                                    :default-time="jamAkhirBawaan"
                                    class="pjd-date"
                                />
                            </div>
                        </div>

                        <div class="pjd-lock">
                            <span class="pjd-lock__ico">
                                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="11" width="14" height="10" rx="2" /><path d="M8 11V7a4 4 0 0 1 8 0v4" /></svg>
                            </span>
                            <div><b>Token + OTP unik</b> akan dikirim ke kandidat terpilih untuk mengakses tes pada jendela waktu ini.</div>
                        </div>

                        <button type="button" class="pjd-gen" :disabled="!bisaGenerate || menyimpan" @click="simpan">
                            <svg v-if="!menyimpan" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l1.9 5.1L19 10l-5.1 1.9L12 17l-1.9-5.1L5 10l5.1-1.9z" /></svg>
                            <i v-else class="bi bi-arrow-repeat pjd-spin"></i>
                            {{ labelGenerate }}
                        </button>
                    </div>
                </section>

                <!-- ═══════════ PILIH KANDIDAT ═══════════ -->
                <section class="pjd-panel">
                    <div class="pjd-panel__head">
                        <span class="pjd-panel__ico">
                            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" /><circle cx="9" cy="7" r="4" /><path d="M23 21v-2a4 4 0 0 0-3-3.87" /></svg>
                        </span>
                        <span class="pjd-panel__ttl">Pilih Kandidat</span>
                        <span class="pjd-count">{{ form.peserta.length }} / {{ kandidat.length }}</span>
                    </div>

                    <div class="pjd-panel__body pjd-panel__body--tight">
                        <div class="pjd-candbar">
                            <button type="button" class="pjd-all" @click="toggleSemua">
                                <span class="pjd-box" :class="{ 'is-on': semuaTercentang, 'is-half': sebagianTercentang }">
                                    <svg v-if="semuaTercentang" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5" /></svg>
                                    <i v-else-if="sebagianTercentang"></i>
                                </span>
                                Pilih semua
                            </button>
                            <div class="pjd-field pjd-field--grow">
                                <svg class="pjd-field__ico" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="2.2" stroke-linecap="round"><circle cx="11" cy="11" r="7" /><path d="M21 21l-4.3-4.3" /></svg>
                                <input v-model="cariKandidat" type="text" class="pjd-input" placeholder="Cari nama / posisi…" @input="debounceKandidat">
                            </div>
                        </div>

                        <!-- Kosong itu wajar; yang tidak boleh adalah kosong tanpa sebab.
                             Pesan dari server menjelaskan kenapa.
                             Saat memuat, yang berkedip HANYA daftarnya — menyelimuti
                             seluruh kartu membuat pencarian & "pilih semua" ikut
                             mati padahal keduanya tidak sedang berubah. -->
                        <div class="pjd-cands">
                            <div v-if="memuatKandidat" class="pjd-skel">
                                <span v-for="n in 4" :key="n" class="pjd-skel__row"></span>
                            </div>

                            <div v-else-if="!kandidat.length" class="pjd-empty">
                                <span class="pjd-empty__ico pjd-empty__ico--lg">
                                    <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" /><circle cx="9" cy="7" r="4" /><path d="M22 11l-3 3M19 11l3 3" /></svg>
                                </span>
                                <p>{{ alasanKandidat || 'Tidak ada kandidat' }}</p>
                            </div>

                            <button
                                v-for="k in kandidatHal"
                                v-else
                                :key="k.kode"
                                type="button"
                                class="pjd-cand"
                                :class="{ 'is-on': form.peserta.includes(k.kode) }"
                                @click="toggleKandidat(k.kode)"
                            >
                                <span class="pjd-box" :class="{ 'is-on': form.peserta.includes(k.kode) }">
                                    <svg v-if="form.peserta.includes(k.kode)" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5" /></svg>
                                </span>
                                <span class="pjd-ava" :class="{ 'is-on': form.peserta.includes(k.kode) }">{{ inisial(k.nama) }}</span>
                                <span class="pjd-cand__in">
                                    <span class="pjd-cand__nama">{{ k.nama }}</span>
                                    <span class="pjd-cand__meta">{{ k.posisi || '—' }} · <span class="pjd-mono">{{ k.kode }}</span></span>
                                    <!-- Satu tahap bisa berisi beberapa tes — sebutkan yang mana. -->
                                    <span v-if="k.tes" class="pjd-cand__flow">
                                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 3v12" /><circle cx="6" cy="18" r="3" /><circle cx="18" cy="6" r="3" /><path d="M18 9c0 6-12 3-12 9" /></svg>
                                        {{ k.tahap }} › {{ k.tes }}
                                    </span>
                                </span>
                            </button>
                        </div>

                        <!-- Paginasi kandidat — 20 per layar supaya masih terpindai
                             mata; centangan disimpan per kode, jadi pindah halaman
                             tidak menghilangkan pilihan di halaman sebelumnya. -->
                        <div v-if="kandidat.length" class="pjd-pager pjd-pager--sm">
                            <span class="pjd-pager__info">
                                {{ rentang(kandPage, kandPerPage, kandidat.length) }} dari {{ kandidat.length }} kandidat<template v-if="form.peserta.length"> · {{ form.peserta.length }} dipilih</template>
                            </span>
                            <div v-if="kandTotalPage > 1" class="pjd-pager__btns">
                                <button type="button" class="pjd-pg" title="Sebelumnya" :disabled="kandPage <= 1" @click="kandPage--">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6" /></svg>
                                </button>
                                <template v-for="(n, i) in nomorHalaman(kandPage, kandTotalPage)" :key="i">
                                    <span v-if="n === '…'" class="pjd-pg pjd-pg--gap">…</span>
                                    <button v-else type="button" class="pjd-pg" :class="{ 'is-on': n === kandPage }" @click="kandPage = n">{{ n }}</button>
                                </template>
                                <button type="button" class="pjd-pg" title="Berikutnya" :disabled="kandPage >= kandTotalPage" @click="kandPage++">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l6-6-6-6" /></svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </section>
            </div>

            <!-- ═══════════ DAFTAR PENJADWALAN ═══════════ -->
            <section class="pjd-panel">
                <div class="pjd-panel__head">
                    <span class="pjd-panel__ico">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" /><path d="M3 10h18M8 2v4M16 2v4" /></svg>
                    </span>
                    <span class="pjd-panel__ttl">Daftar Penjadwalan</span>
                    <span class="pjd-count">{{ daftarTotal }} penjadwalan</span>
                </div>

                <!-- Filter — satu program bisa punya banyak sesi, jadi mencari
                     harus lebih cepat daripada menggulir. -->
                <div class="pjd-filter">
                    <div class="pjd-field pjd-field--grow">
                        <svg class="pjd-field__ico" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="2.2" stroke-linecap="round"><circle cx="11" cy="11" r="7" /><path d="M21 21l-4.3-4.3" /></svg>
                        <input v-model="filter.q" type="text" class="pjd-input" placeholder="Cari kode, nama ujian, atau program…" @input="debounceFilter">
                    </div>
                    <el-select v-model="filter.programId" clearable filterable placeholder="Semua program" class="pjd-fsel" @change="gantiFilter">
                        <el-option v-for="p in opsi.program" :key="p.id" :label="p.nama" :value="p.id" />
                    </el-select>
                    <el-select v-model="filter.status" clearable placeholder="Semua status" class="pjd-fsel pjd-fsel--sm" @change="gantiFilter">
                        <el-option label="Diantrikan" value="DIANTRIKAN" />
                        <el-option label="Berjalan" value="BERJALAN" />
                        <el-option label="Gagal" value="GAGAL" />
                        <el-option label="Selesai" value="SELESAI" />
                    </el-select>
                    <button v-if="adaFilter" type="button" class="pjd-reset" title="Bersihkan filter" @click="resetFilter">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M18 6L6 18M6 6l12 12" /></svg>
                        Reset
                    </button>
                </div>

                <div class="pjd-panel__body pjd-panel__body--tight">
                    <!-- Rangka muat: hanya daftarnya yang berkedip, bukan seluruh
                         panel — kepala & filter tetap bisa dipakai. -->
                    <div v-if="memuat" class="pjd-skel">
                        <span v-for="n in 3" :key="n" class="pjd-skel__card"></span>
                    </div>

                    <div v-else-if="!daftar.length" class="pjd-empty">
                        <span class="pjd-empty__ico pjd-empty__ico--xl">
                            <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" /><path d="M3 10h18M8 2v4M16 2v4M9 15h6" /></svg>
                        </span>
                        <b>{{ adaFilter ? 'Tidak ada yang cocok' : 'Belum ada penjadwalan' }}</b>
                        <p>{{ adaFilter ? 'Ubah kata kunci atau bersihkan filternya.' : 'Pilih paket tes, kandidat, dan jendela waktu, lalu tekan Generate Sesi Tes.' }}</p>
                    </div>

                    <div
                        v-for="j in daftar"
                        :id="`penjadwalan-${j.id}`"
                        :key="j.id"
                        class="pjd-sched"
                        :class="{ 'is-open': terbuka === j.id, 'is-focus': String(fokusId) === String(j.id) }"
                    >
                        <!-- Kepala akordion: seluruh barisnya bisa diklik supaya
                             sasaran kliknya besar, tapi tombol aksi di kanan
                             dihentikan penyebarannya agar tak ikut membuka. -->
                        <div class="pjd-sched__head" role="button" tabindex="0" @click="toggleBaris(j)" @keydown.enter.prevent="toggleBaris(j)" @keydown.space.prevent="toggleBaris(j)">
                            <div class="pjd-sched__top">
                                <span class="pjd-caret" :class="{ 'is-open': terbuka === j.id }">
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l6-6-6-6" /></svg>
                                </span>
                                <div class="pjd-sched__id">
                                    <span class="pjd-kode">{{ j.kode }}</span>
                                    <div class="pjd-sched__nama">{{ j.nama }}</div>
                                    <div class="pjd-sched__meta">
                                        <span v-if="j.program">{{ j.program }}</span>
                                        <span v-if="j.alur">· {{ j.alur }}</span>
                                        <span>· {{ j.jumlahPeserta }} peserta</span>
                                    </div>

                                    <!-- APA yang dijadwalkan, dan OLEH SIAPA. Tanpa ini
                                         kepala kartu hanya berisi nama jadwal, dan admin
                                         harus membukanya untuk tahu paket tes mana yang
                                         dipakai serta siapa yang memasangnya. -->
                                    <div class="pjd-facts">
                                        <span v-if="j.tahapUjian" class="pjd-fact">
                                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="4" width="16" height="16" rx="2" /><path d="M9 9h6v6H9z" /></svg>
                                            <b>Aktivitas</b>{{ j.tahapUjian }}
                                        </span>
                                        <span v-if="j.paketUjian" class="pjd-fact">
                                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="3" width="16" height="18" rx="2" /><path d="M8 8h8M8 12h8M8 16h4" /></svg>
                                            <b>Paket tes</b>{{ j.paketUjian }}
                                        </span>
                                        <!-- Ini nilai BAWAAN saat Generate, bukan jadwal semua
                                             orang: tiap kandidat boleh digeser sendiri. Diberi
                                             label tegas supaya tak terbaca sebagai jadwal
                                             tunggal yang berlaku untuk seisi angkatan. -->
                                        <span v-if="fmtWaktu(j.waktuMulai)" class="pjd-fact">
                                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9" /><path d="M12 7v5l3 2" /></svg>
                                            <b>Jendela bawaan</b>{{ fmtWaktu(j.waktuMulai) }} → {{ fmtWaktu(j.waktuAkhir) || '—' }}
                                        </span>
                                        <span v-if="j.jumlahDigeser" class="pjd-fact is-warn" title="Kandidat ini punya jendela sendiri, tidak mengikuti jendela bawaan">
                                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 9v4M12 17h.01" /><path d="M10.3 3.8L1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.8a2 2 0 0 0-3.4 0z" /></svg>
                                            <b>Jadwal sendiri</b>{{ j.jumlahDigeser }} dari {{ j.jumlahPeserta }} kandidat
                                        </span>
                                    </div>

                                    <!-- Catatan sistem: alasan gagal, atau kabar bahwa
                                         tokennya masih diterbitkan di latar belakang. -->
                                    <div v-if="j.catatan" class="pjd-sched__note" :class="{ 'is-fail': j.status === 'GAGAL' }">
                                        <i class="bi" :class="j.status === 'GAGAL' ? 'bi-exclamation-triangle-fill' : 'bi-hourglass-split'"></i>
                                        {{ j.catatan }}
                                    </div>
                                </div>
                                <div class="pjd-sched__act">
                                    <span class="pjd-status" :class="statusKelas(j.status)">
                                        <i v-if="j.status === 'BERJALAN' || j.status === 'DIANTRIKAN'" class="pjd-dot"></i>{{ j.status }}
                                    </span>
                                </div>
                            </div>

                            <div class="pjd-steps">
                                <span
                                    v-for="t in j.tahap"
                                    :key="t.urutan"
                                    class="pjd-step"
                                    :class="stepKelas(t)"
                                >
                                    <span class="pjd-step__no">{{ t.urutan }}</span>{{ t.label }}
                                    <em v-if="t.status && t.status !== 'BELUM'">{{ t.status }}</em>
                                </span>
                            </div>
                        </div>

                        <!-- Isi akordion: peserta baru diambil saat dibuka. -->
                        <div v-if="terbuka === j.id" class="pjd-sched__body">
                            <div v-if="memuatPeserta && !pesertaBaris(j).length" class="pjd-skel">
                                <span v-for="n in 3" :key="n" class="pjd-skel__row"></span>
                            </div>

                            <div v-else-if="!pesertaBaris(j).length" class="pjd-empty pjd-empty--sm">
                                <span class="pjd-empty__ico">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" /><circle cx="9" cy="7" r="4" /></svg>
                                </span>
                                <p>Belum ada peserta pada penjadwalan ini.</p>
                            </div>

                            <template v-else>
                                <!-- Tabel layar lebar. Kolom Pengerjaan & Nilai sengaja
                                     tidak ada di sini: keduanya milik Worklist, dan di
                                     layar penjadwalan yang dicari admin adalah kredensial
                                     masuk ujian — token & OTP. -->
                                <div class="pjd-tbl">
                                    <div class="pjd-tbl__head">
                                        <span>KANDIDAT</span><span class="c">TOKEN</span><span class="c">OTP</span><span class="c">JENDELA UJIAN</span><span>DIJADWALKAN OLEH</span><span class="r">AKSI</span>
                                    </div>
                                    <div v-for="row in pesertaHal(j)" :key="row.id" class="pjd-tbl__row">
                                        <span class="pjd-tbl__nama">
                                            <span class="pjd-ava pjd-ava--sm is-on">{{ inisial(row.nama) }}</span>
                                            <span class="pjd-tbl__who">
                                                <b>{{ row.nama }}</b>
                                                <em>{{ row.posisi || '—' }}</em>
                                            </span>
                                        </span>
                                        <!-- KREDENSIAL DISAMARKAN.
                                             Token & OTP adalah kunci masuk ujian: siapa pun
                                             yang melihat layar — atau screenshot-nya — bisa
                                             mengerjakan tes atas nama kandidat. Jadi tertutup
                                             secara bawaan, dibuka satu per satu saat perlu,
                                             dan MENYALIN tidak menuntut membukanya dulu. -->
                                        <span class="c">
                                            <span v-if="row.shortToken" class="pjd-credwrap">
                                                <button type="button" class="pjd-cred" title="Salin token akses" @click="salin(row.shortToken, 'Token')">
                                                    <span class="pjd-mono">{{ kredTampak(row.id, 'tok') ? row.shortToken : '••••••••' }}</span>
                                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="12" height="12" rx="2" /><path d="M5 15V5a2 2 0 0 1 2-2h8" /></svg>
                                                </button>
                                                <button type="button" class="pjd-eye" :title="kredTampak(row.id, 'tok') ? 'Sembunyikan token' : 'Tampilkan token'" @click="toggleKred(row.id, 'tok')">
                                                    <svg v-if="kredTampak(row.id, 'tok')" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3l18 18" /><path d="M10.6 10.6a2 2 0 0 0 2.8 2.8" /><path d="M9.4 5.2A9.5 9.5 0 0 1 12 5c5 0 9 4.5 9 7a11 11 0 0 1-2.2 3.1M6.2 6.2A11.6 11.6 0 0 0 3 12c0 2.5 4 7 9 7a9.7 9.7 0 0 0 3.3-.6" /></svg>
                                                    <svg v-else width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z" /><circle cx="12" cy="12" r="2.6" /></svg>
                                                </button>
                                            </span>
                                            <span v-else class="pjd-tbl__sub">{{ row.pesanError ? 'gagal' : '—' }}</span>
                                        </span>
                                        <span class="c">
                                            <span v-if="row.otp" class="pjd-credwrap">
                                                <button type="button" class="pjd-cred pjd-cred--otp" title="Salin kode OTP" @click="salin(row.otp, 'OTP')">
                                                    <span class="pjd-mono">{{ kredTampak(row.id, 'otp') ? row.otp : '••••••••' }}</span>
                                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="12" height="12" rx="2" /><path d="M5 15V5a2 2 0 0 1 2-2h8" /></svg>
                                                </button>
                                                <button type="button" class="pjd-eye" :title="kredTampak(row.id, 'otp') ? 'Sembunyikan OTP' : 'Tampilkan OTP'" @click="toggleKred(row.id, 'otp')">
                                                    <svg v-if="kredTampak(row.id, 'otp')" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3l18 18" /><path d="M10.6 10.6a2 2 0 0 0 2.8 2.8" /><path d="M9.4 5.2A9.5 9.5 0 0 1 12 5c5 0 9 4.5 9 7a11 11 0 0 1-2.2 3.1M6.2 6.2A11.6 11.6 0 0 0 3 12c0 2.5 4 7 9 7a9.7 9.7 0 0 0 3.3-.6" /></svg>
                                                    <svg v-else width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z" /><circle cx="12" cy="12" r="2.6" /></svg>
                                                </button>
                                            </span>
                                            <span v-else class="pjd-tbl__sub">—</span>
                                        </span>
                                        <!-- Rentang PENUH, bukan cuma jam mulai: yang menentukan
                                             kandidat masih bisa masuk atau tidak adalah jam
                                             berakhirnya. -->
                                        <span class="c pjd-tbl__win">
                                            <span>{{ fmtWaktu(row.waktuMulai) || '—' }}</span>
                                            <span class="pjd-tbl__win2">s.d. {{ fmtWaktu(row.waktuAkhir) || '—' }}</span>
                                            <em v-if="row.jadwalSendiri" title="Jadwal khusus kandidat ini, tidak mengikuti jendela bawaan">jadwal sendiri</em>
                                        </span>

                                        <!-- Penanggung jawab BARIS INI. Satu angkatan bisa
                                             disusun banyak perekrut, jadi yang berlaku
                                             adalah pemasang jadwal orangnya — bukan siapa
                                             pun yang menekan Generate. -->
                                        <span class="pjd-by" :title="labelOleh(row)">
                                            <span class="pjd-by__ava">{{ inisial(row.ubahNama || row.olehNama) }}</span>
                                            <span class="pjd-by__in">
                                                <b>{{ row.ubahNama || row.olehNama || '—' }}</b>
                                                <i>{{ fmtWaktu(row.ubahPada || row.olehPada) || '—' }}</i>
                                                <u v-if="row.ubahNama">digeser ulang</u>
                                            </span>
                                        </span>

                                        <span class="r">
                                            <button
                                                type="button" class="pjd-ibtn" :disabled="row.terkunci || !row.dapatDiubah"
                                                :title="row.terkunci ? 'Ujian sudah dikerjakan — jadwal terkunci'
                                                    : (!row.dapatDiubah ? 'Sesi ini dibuat sebelum penautan ke HCLearn ada — buat ulang penjadwalannya agar bisa diubah' : 'Ubah jadwal kandidat ini')"
                                                @click="bukaEdit(row, j)"
                                            >
                                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9" /><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z" /></svg>
                                            </button>
                                        </span>
                                    </div>
                                </div>

                                <!-- Kartu layar sempit -->
                                <div class="pjd-rows">
                                    <div v-for="row in pesertaHal(j)" :key="row.id" class="pjd-row">
                                        <div class="pjd-row__top">
                                            <span class="pjd-ava pjd-ava--sm is-on">{{ inisial(row.nama) }}</span>
                                            <div class="pjd-row__in">
                                                <div class="pjd-row__nama">{{ row.nama }}</div>
                                                <div class="pjd-row__pos">{{ row.posisi || '—' }}</div>
                                                <div class="pjd-row__pos">{{ fmtWaktu(row.waktuMulai) || '—' }}<template v-if="row.jadwalSendiri"> · digeser</template></div>
                                                <div class="pjd-row__pos">oleh {{ row.ubahNama || row.olehNama || '—' }}</div>
                                            </div>
                                            <button
                                                type="button" class="pjd-ibtn" :disabled="row.terkunci || !row.dapatDiubah"
                                                :title="row.terkunci ? 'Ujian sudah dikerjakan — jadwal terkunci'
                                                    : (!row.dapatDiubah ? 'Sesi ini dibuat sebelum penautan ke HCLearn ada — buat ulang penjadwalannya agar bisa diubah' : 'Ubah jadwal kandidat ini')"
                                                @click="bukaEdit(row, j)"
                                            >
                                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9" /><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z" /></svg>
                                            </button>
                                        </div>
                                        <div class="pjd-row__bot">
                                            <button v-if="row.shortToken" type="button" class="pjd-cred" @click="salin(row.shortToken, 'Token')">
                                                <b>TOKEN</b><span class="pjd-mono">{{ row.shortToken }}</span>
                                            </button>
                                            <button v-if="row.otp" type="button" class="pjd-cred pjd-cred--otp" @click="salin(row.otp, 'OTP')">
                                                <b>OTP</b><span class="pjd-mono">{{ row.otp }}</span>
                                            </button>
                                            <span v-if="!row.shortToken && !row.otp" class="pjd-tbl__sub">{{ row.pesanError || 'Kredensial belum terbit' }}</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Paginasi peserta di dalam satu penjadwalan: satu
                                     angkatan bisa berisi ratusan orang. -->
                                <div v-if="pesertaTotalPage(j) > 1" class="pjd-pager pjd-pager--in">
                                    <span class="pjd-pager__info">
                                        {{ rentang(pesertaPage[j.id] || 1, pesertaPerPage, pesertaBaris(j).length) }} dari {{ pesertaBaris(j).length }} peserta
                                    </span>
                                    <div class="pjd-pager__btns">
                                        <button type="button" class="pjd-pg" title="Sebelumnya" :disabled="(pesertaPage[j.id] || 1) <= 1" @click="gantiHalPeserta(j, (pesertaPage[j.id] || 1) - 1)">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6" /></svg>
                                        </button>
                                        <template v-for="(n, i) in nomorHalaman(pesertaPage[j.id] || 1, pesertaTotalPage(j))" :key="i">
                                            <span v-if="n === '…'" class="pjd-pg pjd-pg--gap">…</span>
                                            <button v-else type="button" class="pjd-pg" :class="{ 'is-on': n === (pesertaPage[j.id] || 1) }" @click="gantiHalPeserta(j, n)">{{ n }}</button>
                                        </template>
                                        <button type="button" class="pjd-pg" title="Berikutnya" :disabled="(pesertaPage[j.id] || 1) >= pesertaTotalPage(j)" @click="gantiHalPeserta(j, (pesertaPage[j.id] || 1) + 1)">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l6-6-6-6" /></svg>
                                        </button>
                                    </div>
                                </div>

                                <div v-if="pesertaBaris(j).some((r) => r.linkUjian)" class="pjd-sched__foot">
                                    <button type="button" class="pjd-copy--txt pjd-copy" @click="salin(pesertaBaris(j).find((r) => r.linkUjian).linkUjian, 'Tautan ujian')">
                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7 0l3-3a5 5 0 0 0-7-7l-1 1" /><path d="M14 11a5 5 0 0 0-7 0l-3 3a5 5 0 0 0 7 7l1-1" /></svg>
                                        Salin tautan ujian
                                    </button>
                                </div>
                            </template>
                        </div>
                    </div>

                    <!-- Paginasi daftar penjadwalan (per program) -->
                    <div v-if="daftar.length" class="pjd-pager">
                        <span class="pjd-pager__info">
                            Menampilkan {{ rentang(daftarPage, daftarPerPage, daftarTotal) }} dari {{ daftarTotal }} penjadwalan
                        </span>
                        <div v-if="daftarTotalPage > 1" class="pjd-pager__btns">
                            <button type="button" class="pjd-pg" title="Sebelumnya" :disabled="daftarPage <= 1" @click="gantiHalaman(daftarPage - 1)">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6" /></svg>
                            </button>
                            <template v-for="(n, i) in nomorHalaman(daftarPage, daftarTotalPage)" :key="i">
                                <span v-if="n === '…'" class="pjd-pg pjd-pg--gap">…</span>
                                <button v-else type="button" class="pjd-pg" :class="{ 'is-on': n === daftarPage }" @click="gantiHalaman(n)">{{ n }}</button>
                            </template>
                            <button type="button" class="pjd-pg" title="Berikutnya" :disabled="daftarPage >= daftarTotalPage" @click="gantiHalaman(daftarPage + 1)">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l6-6-6-6" /></svg>
                            </button>
                        </div>
                    </div>
                </div>
            </section>
        </div>


        <!-- Edit jendela waktu tes -->
        <!-- Ubah jadwal SATU kandidat. Memakai AdminModal — kulit modal yang
             sama dengan seluruh halaman admin lain, supaya tidak ada modal yang
             berbeda sendiri. -->
        <AdminModal
            :show="editTampil"
            title="Ubah Jadwal Kandidat"
            :subtitle="editTarget ? editTarget.nama : ''"
            icon="bi-calendar-event"
            save-label="Simpan Jadwal"
            :busy="editSibuk"
            foot-note="Jendela ujian & token kandidat ini ikut diperbarui."
            @close="editTampil = false"
            @save="simpanEdit"
        >
            <div v-if="editTarget" class="pjd-edit">
                <div class="pjd-edit__who">
                    <span class="pjd-ava is-on">{{ inisial(editTarget.nama) }}</span>
                    <div>
                        <b>{{ editTarget.nama }}</b>
                        <span>{{ editTarget.posisi || '—' }}<template v-if="editJadwal"> · {{ editJadwal.paketUjian || editJadwal.nama }}</template></span>
                    </div>
                </div>

                <p class="pjd-edit__note">
                    <i class="bi bi-info-circle"></i>
                    Yang berubah <b>hanya jadwal kandidat ini</b> — peserta lain di penjadwalan yang sama tidak ikut bergeser. Token ujiannya di HCLearn diperbarui otomatis.
                </p>

                <label class="pjd-lbl">Waktu Mulai</label>
                <el-date-picker v-model="editMulai" type="datetime" placeholder="Tanggal &amp; jam mulai" format="DD MMM YYYY HH:mm" value-format="YYYY-MM-DD HH:mm:ss" :default-time="jamMulaiBawaan" class="pjd-date" />
                <label class="pjd-lbl pjd-lbl--gap">Waktu Berakhir</label>
                <el-date-picker v-model="editAkhir" type="datetime" placeholder="Tanggal &amp; jam berakhir" format="DD MMM YYYY HH:mm" value-format="YYYY-MM-DD HH:mm:ss" :default-time="jamAkhirBawaan" class="pjd-date" />
            </div>
        </AdminModal>

        <!-- Toast — bentuk pemberitahuan yang sama dengan halaman admin lain,
             menggantikan spanduk alert yang dulu mendorong isi halaman turun. -->
        <transition name="pjd-toast">
            <div v-if="notice" class="pjd-toast" :class="{ 'is-err': noticeType === 'error' }">
                <i class="bi" :class="noticeType === 'error' ? 'bi-exclamation-circle-fill' : 'bi-check-circle-fill'"></i>
                {{ notice }}
            </div>
        </transition>
    </div>
</template>

<script>
import axios from 'axios';
import AdminModal from '@career/AdminModal.vue';

export default {
    name: 'Penjadwalan',
    components: { AdminModal },
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
            // Halaman daftar kandidat (dipotong di klien; servernya sudah
            // membatasi 500 baris, dan 20 per layar cukup untuk dipindai mata).
            kandPage: 1,
            kandPerPage: 20,

            // ── Daftar penjadwalan: disaring & dipaginasi di server ──
            daftar: [],
            daftarTotal: 0,
            daftarPage: 1,
            daftarPerPage: 10,
            daftarTotalPage: 1,
            filter: { q: '', programId: null, status: '' },
            timerFilter: null,
            // Pewaktu penutupan otomatis tiap kredensial yang dibuka.
            timerKred: {},
            timerToast: null,
            // Baris akordion yang terbuka + cache pesertanya (diambil saat dibuka).
            terbuka: null,
            pesertaCache: {},
            // Kredensial yang sedang dibuka: kunci `{idPeserta}-tok|otp`.
            kredBuka: {},
            // Halaman peserta per baris akordion: { idPenjadwalan: halaman }.
            pesertaPage: {},
            pesertaPerPage: 20,
            memuatPeserta: false,

            cariPaket: '',
            cariKandidat: '',
            timerPaket: null,
            timerKandidat: null,
            editTampil: false,
            editTarget: null,
            // Penjadwalan induk kandidat yang sedang diubah — untuk keterangan.
            editJadwal: null,
            editMulai: '',
            editAkhir: '',
            editSibuk: false,
            notice: '',
            noticeType: 'success',
            fokusId: new URLSearchParams(window.location.search).get('fokus'),
            // Hanya bagian JAM yang dipakai Element Plus; tanggalnya diabaikan.
            jamMulaiBawaan: new Date(2000, 0, 1, 8, 0, 0),
            jamAkhirBawaan: new Date(2000, 0, 1, 23, 59, 0),
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
        labelGenerate() {
            return this.menyimpan ? 'Membuat sesi…' : `Generate ${this.form.peserta.length} Sesi Tes`;
        },
        /** Potongan kandidat untuk halaman yang sedang dilihat. */
        kandidatHal() {
            const a = (this.kandPage - 1) * this.kandPerPage;

            return this.kandidat.slice(a, a + this.kandPerPage);
        },
        kandTotalPage() {
            return Math.max(1, Math.ceil(this.kandidat.length / this.kandPerPage));
        },
        adaFilter() {
            const f = this.filter;

            return !!(f.q || f.programId || f.status);
        },
    },
    watch: {
        // Jumlah kandidat berubah (ganti tahap / cari) → jangan tertinggal di
        // halaman yang sudah tidak ada isinya.
        'kandidat.length'() { this.kandPage = 1; },
    },
    beforeUnmount() {
        // Pewaktu penutupan kredensial jangan menyala setelah halaman ditinggal.
        Object.values(this.timerKred).forEach((t) => clearTimeout(t));
        clearTimeout(this.timerToast);
        clearTimeout(this.timerFilter);
        clearTimeout(this.timerPaket);
        clearTimeout(this.timerKandidat);
    },
    mounted() {
        this.muatOpsi();
        this.muatPaket();
        this.muat();
    },
    methods: {
        /** Toast: muncul lalu hilang sendiri. Pesan baru menyela yang lama. */
        beritahu(pesan, tipe = 'success') {
            this.notice = pesan;
            this.noticeType = tipe;
            clearTimeout(this.timerToast);
            this.timerToast = setTimeout(() => { this.notice = ''; }, tipe === 'error' ? 6000 : 4000);
        },
        inisial(nama) {
            return (nama || '?').split(' ').slice(0, 2).map((n) => n[0]).join('').toUpperCase();
        },
        /**
         * Rupa chip tahap pada kartu penjadwalan:
         *   is-active — tahap yang ujiannya dijadwalkan di sini (punya Nama_Ujian);
         *   is-hcl    — tahap ujian online lain di alur yang sama;
         *   is-todo   — tahap yang ditangani tim.
         */
        stepKelas(t) {
            if (t.namaUjian) return 'is-active';

            return t.kirimHclearn ? 'is-hcl' : 'is-todo';
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
                const params = { page: this.daftarPage, perPage: this.daftarPerPage };
                if (this.filter.q) params.q = this.filter.q;
                if (this.filter.programId) params.programId = this.filter.programId;
                if (this.filter.status) params.status = this.filter.status;
                const res = await axios.get('/api/v1/penjadwalan', { params, headers: { Accept: 'application/json' } });
                const r = res.data.result || {};
                this.daftar = r.data || [];
                this.daftarTotal = r.total || 0;
                this.daftarTotalPage = r.totalPage || 1;
                // Halaman bisa jadi kosong setelah menyaring atau menghapus.
                if (this.daftarPage > this.daftarTotalPage) { this.daftarPage = this.daftarTotalPage; return this.muat(); }
                if (this.fokusId && this.daftar.some((j) => String(j.id) === String(this.fokusId))) {
                    this.$nextTick(() => document.getElementById(`penjadwalan-${this.fokusId}`)?.scrollIntoView({ behavior: 'smooth', block: 'center' }));
                }
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
        /* ── Daftar penjadwalan: filter, halaman, akordion ── */
        debounceFilter() {
            clearTimeout(this.timerFilter);
            this.timerFilter = setTimeout(() => { this.daftarPage = 1; this.muat(); }, 400);
        },
        gantiFilter() {
            this.daftarPage = 1;
            this.muat();
        },
        resetFilter() {
            this.filter = { q: '', programId: null, status: '' };
            this.daftarPage = 1;
            this.muat();
        },
        gantiHalaman(n) {
            if (n < 1 || n > this.daftarTotalPage || n === this.daftarPage) return;
            this.daftarPage = n;
            this.terbuka = null;
            this.muat();
        },
        /**
         * Buka/tutup satu baris. Pesertanya baru diambil saat pertama dibuka —
         * daftar bisa panjang, dan yang tak dibuka tak perlu dikueri sama sekali.
         */
        async toggleBaris(j) {
            // Pindah/tutup baris → semua kredensial yang telanjur dibuka ditutup.
            this.kredBuka = {};
            if (this.terbuka === j.id) { this.terbuka = null; return; }
            this.terbuka = j.id;
            if (this.pesertaCache[j.id]) return;
            this.memuatPeserta = true;
            try {
                const res = await axios.get(`/api/v1/penjadwalan/${j.id}/peserta`, { headers: { Accept: 'application/json' } });
                this.pesertaCache = { ...this.pesertaCache, [j.id]: res.data.result || [] };
            } catch (e) {
                this.beritahu('Gagal memuat peserta penjadwalan', 'error');
                this.terbuka = null;
            } finally {
                this.memuatPeserta = false;
            }
        },
        pesertaBaris(j) {
            return this.pesertaCache[j.id] || [];
        },
        pesertaHal(j) {
            const a = ((this.pesertaPage[j.id] || 1) - 1) * this.pesertaPerPage;

            return this.pesertaBaris(j).slice(a, a + this.pesertaPerPage);
        },
        pesertaTotalPage(j) {
            return Math.max(1, Math.ceil(this.pesertaBaris(j).length / this.pesertaPerPage));
        },
        gantiHalPeserta(j, n) {
            if (n < 1 || n > this.pesertaTotalPage(j)) return;
            this.pesertaPage = { ...this.pesertaPage, [j.id]: n };
            // Kredensial yang telanjur dibuka ditutup saat pindah halaman.
            this.kredBuka = {};
        },
        /** Rentang baris yang sedang tampil, mis. "21–40". */
        rentang(halaman, perHalaman, total) {
            if (! total) return '0';
            const awal = (halaman - 1) * perHalaman + 1;

            return `${awal}–${Math.min(halaman * perHalaman, total)}`;
        },
        /**
         * Nomor halaman yang ditampilkan: selalu halaman pertama & terakhir,
         * plus tetangga halaman aktif. Sisanya diringkas '…' supaya deretannya
         * tidak melebar tak terkendali saat halamannya puluhan.
         */
        nomorHalaman(aktif, total) {
            if (total <= 7) return Array.from({ length: total }, (_, i) => i + 1);
            const n = new Set([1, total, aktif, aktif - 1, aktif + 1]);
            const urut = [...n].filter((x) => x >= 1 && x <= total).sort((a, b) => a - b);

            return urut.reduce((acc, x, i) => {
                if (i && x - urut[i - 1] > 1) acc.push('…');
                acc.push(x);

                return acc;
            }, []);
        },
        /** Warna pil status penjadwalan. DIANTRIKAN = token belum terbit. */
        statusKelas(status) {
            if (status === 'BERJALAN') return 'is-run';
            if (status === 'DIANTRIKAN') return 'is-queue';
            if (status === 'GAGAL') return 'is-fail';

            return 'is-idle';
        },
        kredTampak(idPeserta, jenis) {
            return !!this.kredBuka[`${idPeserta}-${jenis}`];
        },
        /**
         * Buka/tutup satu kredensial.
         *
         * Dibuka per nilai, bukan per baris — membuka token tidak ikut membuka
         * OTP-nya. Tertutup lagi otomatis setelah 30 detik supaya layar yang
         * ditinggal pergi tidak memamerkan kunci ujian.
         */
        toggleKred(idPeserta, jenis) {
            const kunci = `${idPeserta}-${jenis}`;
            const buka = !this.kredBuka[kunci];
            this.kredBuka = { ...this.kredBuka, [kunci]: buka };
            clearTimeout(this.timerKred[kunci]);
            if (buka) {
                this.timerKred[kunci] = setTimeout(() => {
                    this.kredBuka = { ...this.kredBuka, [kunci]: false };
                }, 30000);
            }
        },
        /**
         * Keterangan lengkap penanggung jawab jadwal satu kandidat.
         *
         * Bila jadwalnya pernah digeser, KEDUANYA disebut — pemasang awal dan
         * penggeser terakhir. Dengan sepuluh perekrut di satu angkatan, "siapa
         * yang terakhir menyentuh ini" adalah pertanyaan pertama saat ada
         * jadwal yang keliru.
         */
        labelOleh(row) {
            const awal = `Dijadwalkan oleh ${row.olehNama || '—'}${this.fmtWaktu(row.olehPada) ? ` · ${this.fmtWaktu(row.olehPada)}` : ''}`;
            if (!row.ubahNama) return awal;

            return `${awal}\nDigeser oleh ${row.ubahNama}${this.fmtWaktu(row.ubahPada) ? ` · ${this.fmtWaktu(row.ubahPada)}` : ''}`;
        },
        /** Tanggal ringkas untuk kepala akordion. */
        fmtWaktu(v) {
            if (!v) return null;
            const d = new Date(String(v).replace(' ', 'T'));
            if (Number.isNaN(d.getTime())) return null;

            return d.toLocaleString('id-ID', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
        },
        /**
         * Buka pengubahan jadwal SATU kandidat.
         *
         * Sasarannya baris peserta, bukan penjadwalannya: yang perlu digeser
         * hampir selalu satu orang, dan menggeser di level jadwal berarti
         * memindahkan seisi angkatan demi satu orang.
         */
        bukaEdit(row, j) {
            if (row.terkunci || ! row.dapatDiubah) return;
            this.editTarget = row;
            this.editJadwal = j || null;
            this.editMulai = this.normalWaktu(row.waktuMulai);
            this.editAkhir = this.normalWaktu(row.waktuAkhir);
            this.editTampil = true;
        },
        // Samakan format waktu dari server (ISO / 'YYYY-MM-DD HH:mm:ss') ke value-format picker.
        normalWaktu(v) {
            if (!v) return '';
            return String(v).replace('T', ' ').slice(0, 19);
        },
        async simpanEdit() {
            if (this.editSibuk || !this.editTarget) return;
            if (!this.editMulai || !this.editAkhir) {
                this.beritahu('Isi waktu mulai dan waktu berakhir dulu.', 'error');

                return;
            }
            this.editSibuk = true;
            try {
                const res = await axios.put(`/api/v1/penjadwalan/peserta/${this.editTarget.id}`, {
                    waktuMulai: this.editMulai,
                    waktuAkhir: this.editAkhir,
                }, { headers: { Accept: 'application/json' } });
                this.beritahu(res.data.message || 'Jadwal kandidat diperbarui');
                this.editTampil = false;
                // Muat ulang peserta baris itu supaya jendela barunya langsung terlihat.
                const jid = this.editJadwal?.id;
                if (jid) {
                    const sisa = { ...this.pesertaCache };
                    delete sisa[jid];
                    this.pesertaCache = sisa;
                    const segar = await axios.get(`/api/v1/penjadwalan/${jid}/peserta`, { headers: { Accept: 'application/json' } });
                    this.pesertaCache = { ...this.pesertaCache, [jid]: segar.data.result || [] };
                }
            } catch (e) {
                this.beritahu(e.response?.data?.message || 'Gagal memperbarui jadwal', 'error');
            } finally {
                this.editSibuk = false;
            }
        },
        /**
         * Salin ke papan klip. Labelnya disebut supaya admin yakin YANG MANA
         * yang tersalin — token, OTP, dan tautan mudah tertukar saat mendikte
         * ulang ke kandidat lewat telepon.
         */
        async salin(teks, label = 'Teks') {
            if (!teks) return;
            try {
                await navigator.clipboard.writeText(teks);
                this.beritahu(`${label} disalin: ${teks}`);
            } catch (e) {
                this.beritahu('Peramban menolak menyalin — salin manual dari layar.', 'error');
            }
        },
    },
};
</script>

<style scoped>
/* Nilai warna, radius, dan bayangan mengikuti template desain apa adanya —
   jangan "dirapikan" tanpa mengubah templatenya juga, karena halaman lain
   yang memakai template sekeluarga akan ikut tidak sinkron. */

.pjd { position: relative; margin: -1rem; padding: 26px 30px 44px; min-height: calc(100vh - 68px); overflow-x: clip; }
.pjd-wrap { position: relative; z-index: 2; width: 100%; }

/* Blob latar — diklip oleh overflow-x: clip di atas. */
.pjd-blob { position: absolute; border-radius: 50%; filter: blur(8px); pointer-events: none; z-index: 0; }
.pjd-blob--a { top: -120px; right: 12%; width: 440px; height: 440px; background: radial-gradient(circle at 30% 30%, rgba(139, 92, 246, .12), rgba(139, 92, 246, 0) 70%); animation: pjdFloatA 16s ease-in-out infinite; }
.pjd-blob--b { bottom: -160px; left: 6%; width: 460px; height: 460px; background: radial-gradient(circle at 60% 40%, rgba(99, 102, 241, .1), rgba(99, 102, 241, 0) 70%); animation: pjdFloatB 19s ease-in-out infinite; }
@keyframes pjdFloatA { 0%, 100% { transform: translate(0, 0); } 50% { transform: translate(28px, -22px); } }
@keyframes pjdFloatB { 0%, 100% { transform: translate(0, 0); } 50% { transform: translate(-24px, 20px); } }
@keyframes pjdRise { from { opacity: 0; transform: translateY(14px); } to { opacity: 1; transform: translateY(0); } }
@keyframes pjdPulse { 0%, 100% { box-shadow: 0 0 0 0 rgba(16, 185, 129, .5); } 70% { box-shadow: 0 0 0 7px rgba(16, 185, 129, 0); } }
@keyframes pjdSpin { to { transform: rotate(360deg); } }

/* ── KEPALA HALAMAN ── */
.pjd-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 18px; flex-wrap: wrap; margin-bottom: 18px; }
.pjd-head__l { min-width: 0; }
.pjd-head__title { display: flex; align-items: center; gap: 10px; }
.pjd-head__ico { width: 38px; height: 38px; border-radius: 12px; background: linear-gradient(135deg, #8b5cf6, #6366f1); color: #fff; display: flex; align-items: center; justify-content: center; flex: 0 0 auto; box-shadow: 0 10px 24px rgba(99, 102, 241, .3); }
.pjd-head h1 { margin: 0; font-size: 26px; font-weight: 800; color: #0f172a; letter-spacing: -.025em; }
.pjd-head p { margin: 9px 0 0; font-size: 14px; color: #64748b; line-height: 1.6; max-width: 640px; }

.pjd-tabs { display: inline-flex; flex-wrap: wrap; gap: 4px; padding: 5px; border-radius: 15px; background: rgba(255, 255, 255, .8); border: 1px solid rgba(226, 232, 240, .9); box-shadow: 0 6px 18px rgba(15, 23, 42, .05); flex: 0 0 auto; }
.pjd-tab { appearance: none; cursor: pointer; font-family: inherit; font-size: 13px; font-weight: 800; padding: 9px 15px; border-radius: 11px; border: none; background: transparent; color: #64748b; transition: all .16s; }
.pjd-tab:hover { color: #4f46e5; }
.pjd-tab.is-on { color: #fff; background: linear-gradient(135deg, #8b5cf6, #6366f1); }

/* ── TOAST ── seragam dengan Worklist Pelamar & portal kandidat. */
/* z-index 1300 = DI ATAS modal EVO (.wca-modal-mask 1200), supaya toast tetap terbaca saat modal terbuka. */
.pjd-toast { position: fixed; bottom: 24px; right: 24px; z-index: 1300; display: flex; align-items: center; gap: 9px; max-width: min(520px, calc(100vw - 48px)); padding: 12px 18px; border-radius: 13px; background: #0f172a; color: #fff; font-size: 13.5px; font-weight: 700; line-height: 1.5; box-shadow: 0 18px 40px rgba(0, 0, 0, .3); }
.pjd-toast.is-err { background: #dc2626; }
.pjd-toast .bi { flex: 0 0 auto; color: #34d399; }
.pjd-toast.is-err .bi { color: #fff; }
.pjd-toast-enter-active, .pjd-toast-leave-active { transition: opacity .25s, transform .25s; }
.pjd-toast-enter-from, .pjd-toast-leave-to { opacity: 0; transform: translateY(12px); }

/* ── PANEL ──
   align-items: stretch (bawaan grid) supaya kedua kartu di baris atas SAMA
   TINGGI. Panel dibuat kolom flex, lalu daftar kandidat yang memanjang mengisi
   sisa ruangnya — tanpa ini kartu kanan mengambang lebih pendek dan barisnya
   terlihat timpang. */
.pjd-two { display: grid; grid-template-columns: 1fr; gap: 18px; margin-bottom: 18px; }
.pjd-panel { display: flex; flex-direction: column; background: rgba(255, 255, 255, .92); border: 1px solid rgba(226, 232, 240, .9); border-radius: 22px; overflow: hidden; box-shadow: 0 10px 30px rgba(15, 23, 42, .05); }
.pjd-panel__head { display: flex; align-items: center; gap: 11px; padding: 16px 22px; background: linear-gradient(180deg, #fbfbfe, #f8f9fc); border-bottom: 1px solid #eef0f7; }
.pjd-panel__ico { width: 32px; height: 32px; border-radius: 10px; flex: 0 0 auto; display: flex; align-items: center; justify-content: center; background: rgba(99, 102, 241, .12); color: #6366f1; }
.pjd-panel__ttl { font-size: 14.5px; font-weight: 800; color: #0f172a; letter-spacing: -.01em; flex: 1; }
.pjd-count { font-size: 11px; font-weight: 800; color: #8b93a7; background: #eef0f7; border-radius: 999px; padding: 4px 11px; flex: 0 0 auto; }
.pjd-panel__body { padding: 20px 22px 22px; display: flex; flex-direction: column; gap: 20px; flex: 1; min-height: 0; }
.pjd-panel__body--tight { padding: 18px 22px 22px; gap: 14px; }

.pjd-lbl { display: block; font-size: 12px; font-weight: 800; letter-spacing: .04em; color: #475569; margin-bottom: 8px; }
.pjd-lbl--gap { margin-top: 14px; }
.pjd-mono { font-family: 'JetBrains Mono', ui-monospace, monospace; }

/* Kolom isian + ikon kiri */
.pjd-field { position: relative; }
.pjd-field--grow { flex: 1; min-width: 170px; }
.pjd-field__ico { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); pointer-events: none; }
.pjd-input { width: 100%; padding: 12px 14px 12px 40px; border-radius: 13px; border: 1px solid #e6e9f3; background: #f7f8fc; font-family: inherit; font-size: 13.5px; color: #334155; outline: none; transition: all .18s; }
.pjd-input::placeholder { color: #94a3b8; }
.pjd-input:focus { border-color: #a5b4fc; background: #fff; box-shadow: 0 0 0 3px rgba(99, 102, 241, .1); }

.pjd-note { display: flex; gap: 7px; align-items: flex-start; font-size: 12px; line-height: 1.55; color: #8792a6; margin: 8px 0 10px; }
.pjd-note--warn { color: #b45309; }
.pjd-opttag { float: right; color: #a2a9ba; font-size: 11.5px; margin-left: 1rem; }

/* ── DAFTAR TAHAP/UJIAN ── */
.pjd-stages { display: flex; flex-direction: column; gap: 10px; }
.pjd-stage { appearance: none; cursor: pointer; font-family: inherit; width: 100%; display: flex; align-items: center; gap: 12px; padding: 13px 15px; border-radius: 15px; border: 1.5px solid #eef0f7; background: #fff; transition: all .18s; }
.pjd-stage:hover { border-color: #c7cdf0; }
.pjd-stage.is-on { border-color: #8b5cf6; background: linear-gradient(135deg, rgba(139, 92, 246, .06), rgba(99, 102, 241, .06)); box-shadow: 0 8px 22px rgba(99, 102, 241, .12); }
.pjd-stage__no { width: 28px; height: 28px; border-radius: 9px; flex: 0 0 auto; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 800; color: #8792a6; background: #f1f2f9; }
.pjd-stage.is-on .pjd-stage__no { color: #fff; background: linear-gradient(135deg, #8b5cf6, #6366f1); }
.pjd-stage__in { flex: 1; min-width: 0; text-align: left; }
.pjd-stage__nama { display: block; font-size: 13.5px; font-weight: 800; color: #1e293b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.pjd-stage__meta { display: flex; align-items: center; gap: 5px; font-size: 11.5px; color: #8792a6; margin-top: 3px; }
.pjd-stage__badge { flex: 0 0 auto; font-size: 10.5px; font-weight: 800; padding: 4px 10px; border-radius: 8px; white-space: nowrap; background: #eef0f7; color: #94a3b8; }
.pjd-stage__badge.is-wait { background: rgba(245, 158, 11, .14); color: #b45309; }

/* ── KARTU PAKET TES ── */
.pjd-pkgs { display: grid; grid-template-columns: 1fr; gap: 12px; margin-top: 12px; max-height: 340px; overflow-y: auto; padding: 2px; }
.pjd-pkg { appearance: none; cursor: pointer; font-family: inherit; text-align: left; padding: 14px 15px; border-radius: 16px; border: 1.5px solid #eef0f7; background: #fff; box-shadow: 0 2px 8px rgba(15, 23, 42, .03); transition: all .18s; }
.pjd-pkg:hover { border-color: #c7cdf0; }
.pjd-pkg.is-on { border-color: #8b5cf6; background: linear-gradient(135deg, rgba(139, 92, 246, .05), rgba(99, 102, 241, .05)); box-shadow: 0 10px 26px rgba(99, 102, 241, .14); }
.pjd-pkg__head { display: flex; align-items: flex-start; justify-content: space-between; gap: 8px; width: 100%; }
.pjd-pkg__nama { font-size: 13.5px; font-weight: 800; color: #1e293b; text-align: left; line-height: 1.3; }
.pjd-pkg__check { width: 22px; height: 22px; border-radius: 7px; flex: 0 0 auto; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, #8b5cf6, #6366f1); }
.pjd-pkg__kode { display: block; font-family: 'JetBrains Mono', ui-monospace, monospace; font-size: 10.5px; font-weight: 600; color: #a2a9ba; margin-top: 6px; }
.pjd-pkg__chips { display: flex; flex-wrap: wrap; gap: 5px; margin-top: 10px; }
.pjd-chip { font-size: 10px; font-weight: 700; color: #4f46e5; background: rgba(99, 102, 241, .1); border-radius: 6px; padding: 3px 8px; }
.pjd-pkg__foot { display: flex; align-items: center; gap: 12px; margin-top: 11px; padding-top: 10px; border-top: 1px solid #f1f2f9; font-size: 11px; color: #8792a6; }
.pjd-pkg__foot > span { display: inline-flex; align-items: center; gap: 5px; }

.pjd-times { display: grid; grid-template-columns: 1fr; gap: 14px; }

.pjd-lock { display: flex; gap: 12px; padding: 13px 15px; border-radius: 15px; background: linear-gradient(135deg, #fffdf7, #fff8ec); border: 1px solid #f2e4c4; font-size: 12.5px; line-height: 1.55; color: #8a6d29; }
.pjd-lock b { color: #92660a; }
.pjd-lock__ico { width: 32px; height: 32px; border-radius: 10px; flex: 0 0 auto; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, #fbbf24, #f59e0b); color: #fff; }

.pjd-gen { appearance: none; font-family: inherit; width: 100%; font-size: 14px; font-weight: 800; padding: 14px; border-radius: 14px; border: none; display: inline-flex; align-items: center; justify-content: center; gap: 9px; cursor: pointer; color: #fff; background: linear-gradient(135deg, #8b5cf6, #6366f1); box-shadow: 0 14px 32px rgba(99, 102, 241, .34); transition: all .18s; }
.pjd-gen:hover:not(:disabled) { filter: brightness(1.05); }
.pjd-gen:disabled { cursor: not-allowed; color: #a5abc9; background: #eef0f7; box-shadow: none; }
.pjd-spin { display: inline-block; animation: pjdSpin 1s linear infinite; }

/* ── KANDIDAT ── */
.pjd-candbar { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
.pjd-all { display: inline-flex; align-items: center; gap: 9px; appearance: none; border: none; background: transparent; cursor: pointer; font-family: inherit; padding: 0; font-size: 13px; font-weight: 700; color: #475569; }
.pjd-box { width: 20px; height: 20px; border-radius: 6px; flex: 0 0 auto; display: flex; align-items: center; justify-content: center; border: 1.5px solid #cbd2e0; background: #fff; transition: all .16s; }
.pjd-box.is-on { border-color: transparent; background: linear-gradient(135deg, #8b5cf6, #6366f1); }
.pjd-box.is-half i { width: 9px; height: 2.5px; border-radius: 2px; background: #8b5cf6; display: block; }

/* flex:1 → daftar kandidat memakan sisa tinggi kartu, sehingga kartu ini
   berujung sama tinggi dengan Konfigurasi Tes di sebelahnya. */
.pjd-cands { display: flex; flex-direction: column; gap: 10px; flex: 1; min-height: 220px; max-height: 620px; overflow-y: auto; padding: 2px; }
.pjd-cand { appearance: none; cursor: pointer; font-family: inherit; width: 100%; display: flex; align-items: flex-start; gap: 12px; padding: 14px 15px; border-radius: 16px; border: 1.5px solid #eef0f7; background: #fff; box-shadow: 0 2px 8px rgba(15, 23, 42, .03); transition: all .18s; }
.pjd-cand:hover { border-color: #c7cdf0; }
.pjd-cand.is-on { border-color: #8b5cf6; background: linear-gradient(135deg, rgba(139, 92, 246, .05), rgba(99, 102, 241, .05)); box-shadow: 0 8px 22px rgba(99, 102, 241, .12); }
.pjd-ava { width: 38px; height: 38px; border-radius: 12px; background: linear-gradient(135deg, #a5b4fc, #818cf8); color: #fff; font-size: 14px; font-weight: 800; display: flex; align-items: center; justify-content: center; flex: 0 0 auto; }
.pjd-ava.is-on { background: linear-gradient(135deg, #8b5cf6, #6366f1); }
.pjd-ava--sm { width: 30px; height: 30px; border-radius: 10px; font-size: 11.5px; }
.pjd-cand__in { flex: 1; min-width: 0; text-align: left; }
.pjd-cand__nama { display: block; font-size: 14px; font-weight: 800; color: #1e293b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.pjd-cand__meta { display: block; font-size: 11.5px; color: #8792a6; margin-top: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.pjd-cand__meta .pjd-mono { color: #a2a9ba; }
.pjd-cand__flow { display: inline-flex; align-items: center; gap: 5px; font-size: 10.5px; font-weight: 700; color: #7c74b0; background: rgba(139, 92, 246, .1); border-radius: 6px; padding: 3px 8px; margin-top: 7px; }

/* ── KEADAAN KOSONG ── */
.pjd-empty { display: flex; flex-direction: column; align-items: center; gap: 10px; padding: 36px 18px; text-align: center; }
.pjd-empty--sm { padding: 26px 18px; }
.pjd-empty--span { grid-column: 1 / -1; }
.pjd-empty__ico { width: 56px; height: 56px; border-radius: 18px; background: linear-gradient(135deg, #f4f2ff, #eef2ff); border: 1px solid #e7e3fb; display: flex; align-items: center; justify-content: center; color: #a5b4fc; flex: 0 0 auto; }
.pjd-empty__ico--lg { width: 56px; height: 56px; }
.pjd-empty__ico--xl { width: 64px; height: 64px; border-radius: 20px; }
.pjd-empty b { font-size: 14px; font-weight: 800; color: #475569; }
.pjd-empty p { margin: 0; font-size: 12.5px; line-height: 1.55; color: #8b93a7; max-width: 320px; }

/* ── FILTER DAFTAR ── */
.pjd-filter { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; padding: 14px 22px; border-bottom: 1px solid #eef0f7; background: #fdfdff; }
.pjd-fsel { width: 210px; }
.pjd-fsel--sm { width: 160px; }
.pjd-reset { appearance: none; display: inline-flex; align-items: center; gap: 6px; border: 1px solid #e6e9f3; background: #fff; padding: 10px 13px; border-radius: 12px; cursor: pointer; font-family: inherit; font-size: 12.5px; font-weight: 800; color: #64748b; transition: all .16s; }
.pjd-reset:hover { color: #dc2626; border-color: #f4d0d0; background: #fef2f2; }

/* ── RANGKA MUAT ── */
.pjd-skel { display: flex; flex-direction: column; gap: 10px; padding: 2px; }
.pjd-skel__row, .pjd-skel__card { display: block; border-radius: 16px; background: linear-gradient(90deg, #f1f2f9 25%, #f8f9fc 37%, #f1f2f9 63%); background-size: 400% 100%; animation: pjdShimmer 1.3s ease-in-out infinite; }
.pjd-skel__row { height: 74px; }
.pjd-skel__card { height: 112px; border-radius: 18px; }
@keyframes pjdShimmer { from { background-position: 100% 50%; } to { background-position: 0 50%; } }

/* ── KARTU PENJADWALAN (AKORDION) ── */
.pjd-sched { border: 1px solid #eef0f7; border-radius: 18px; background: #fff; overflow: hidden; box-shadow: 0 4px 16px rgba(15, 23, 42, .04); animation: pjdRise .4s cubic-bezier(.22, 1, .36, 1) both; transition: border-color .18s, box-shadow .18s; }
.pjd-sched + .pjd-sched { margin-top: 14px; }
.pjd-sched:hover { border-color: #dfe3f3; }
.pjd-sched.is-open { border-color: #c7cdf0; box-shadow: 0 10px 28px rgba(99, 102, 241, .12); }
.pjd-sched.is-focus { border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99, 102, 241, .14), 0 12px 28px rgba(15, 23, 42, .08); }
.pjd-sched__head { padding: 16px 18px; background: linear-gradient(180deg, #fbfbfe, #f8f9fc); border-bottom: 1px solid #eef0f7; cursor: pointer; outline: none; }
.pjd-sched__head:focus-visible { box-shadow: inset 0 0 0 2px #a5b4fc; }
.pjd-sched__top { display: flex; align-items: flex-start; gap: 12px; flex-wrap: wrap; }
.pjd-caret { width: 26px; height: 26px; border-radius: 8px; flex: 0 0 auto; display: flex; align-items: center; justify-content: center; background: #f1f2f9; color: #8792a6; margin-top: 2px; transition: transform .22s cubic-bezier(.22, 1, .36, 1), background .18s, color .18s; }
.pjd-caret.is-open { transform: rotate(90deg); background: linear-gradient(135deg, #8b5cf6, #6366f1); color: #fff; }
/* Fakta jadwal — apa yang dijadwalkan & oleh siapa, terbaca tanpa membuka. */
.pjd-facts { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 10px; }
.pjd-fact { display: inline-flex; align-items: center; gap: 6px; font-size: 11.5px; color: #475569; background: #fff; border: 1px solid #eef0f7; border-radius: 9px; padding: 5px 10px; }
.pjd-fact > svg { color: #a5b4fc; flex: 0 0 auto; }
.pjd-fact b { font-size: 9.5px; font-weight: 800; letter-spacing: .07em; color: #a2a9ba; text-transform: uppercase; }
.pjd-fact.is-warn { background: #fffdf7; border-color: #f2e4c4; color: #8a6d29; }
.pjd-fact.is-warn > svg { color: #f59e0b; }
.pjd-fact.is-warn b { color: #b45309; }
.pjd-sched__body { animation: pjdRise .28s cubic-bezier(.22, 1, .36, 1) both; }
.pjd-sched__foot { display: flex; justify-content: flex-end; padding: 12px 18px 14px; border-top: 1px solid #f4f5fb; }
.pjd-sched__id { min-width: 0; flex: 1; }
.pjd-kode { display: inline-block; font-family: 'JetBrains Mono', ui-monospace, monospace; font-size: 10px; font-weight: 700; color: #7c74b0; background: rgba(139, 92, 246, .1); border-radius: 6px; padding: 3px 8px; }
.pjd-sched__nama { font-size: 15.5px; font-weight: 800; color: #0f172a; letter-spacing: -.01em; margin-top: 8px; }
.pjd-sched__meta { display: flex; flex-wrap: wrap; gap: 4px; font-size: 12px; color: #8792a6; margin-top: 4px; }
.pjd-sched__act { display: flex; align-items: center; gap: 10px; flex: 0 0 auto; flex-wrap: wrap; justify-content: flex-end; }

/* Penjadwal — kartu orang, bukan chip teks. Melekat pada baris kandidat. */
.pjd-by { display: inline-flex; align-items: center; gap: 8px; min-width: 0; }
.pjd-by__ava { width: 28px; height: 28px; border-radius: 9px; flex: 0 0 auto; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, #a5b4fc, #818cf8); color: #fff; font-size: 10.5px; font-weight: 800; letter-spacing: .02em; }
.pjd-by__in { display: flex; flex-direction: column; min-width: 0; line-height: 1.3; }
.pjd-by__in b { font-size: 12px; font-weight: 800; color: #475569; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.pjd-by__in i { font-style: normal; font-size: 10.5px; color: #8792a6; }
.pjd-by__in u { text-decoration: none; font-size: 9px; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; color: #b45309; margin-top: 2px; }
.pjd-status { display: inline-flex; align-items: center; gap: 6px; font-size: 10.5px; font-weight: 800; border-radius: 8px; padding: 5px 11px; }
.pjd-status.is-run { color: #059669; background: rgba(16, 185, 129, .12); }
.pjd-status.is-idle { color: #8b93a7; background: #eef0f7; }
.pjd-status.is-queue { color: #b45309; background: rgba(245, 158, 11, .14); }
.pjd-status.is-queue .pjd-dot { background: #f59e0b; }
.pjd-status.is-fail { color: #b91c1c; background: rgba(239, 68, 68, .1); }
.pjd-sched__note { display: flex; align-items: flex-start; gap: 7px; margin-top: 8px; font-size: 11.5px; line-height: 1.5; color: #8a6d29; }
.pjd-sched__note.is-fail { color: #b91c1c; }
.pjd-sched__note .bi { flex: 0 0 auto; margin-top: 1px; }
.pjd-dot { width: 7px; height: 7px; border-radius: 50%; background: #10b981; animation: pjdPulse 2s infinite; }
.pjd-ibtn { appearance: none; border: 1px solid #e6e9f3; background: #fff; width: 34px; height: 34px; border-radius: 10px; display: flex; align-items: center; justify-content: center; cursor: pointer; color: #64748b; transition: all .16s; }
.pjd-ibtn:hover { color: #4f46e5; border-color: #c7cdf0; }
.pjd-ibtn--del { border-color: #f4d0d0; color: #dc2626; }
.pjd-ibtn--del:hover { background: #fef2f2; color: #b91c1c; border-color: #f4d0d0; }

.pjd-steps { display: flex; flex-wrap: wrap; gap: 7px; margin-top: 14px; }
.pjd-step { display: inline-flex; align-items: center; gap: 6px; font-size: 11px; font-weight: 700; padding: 5px 10px; border-radius: 9px; white-space: nowrap; background: #f7f8fc; color: #8792a6; border: 1px solid #eef0f7; }
.pjd-step.is-active { background: rgba(99, 102, 241, .1); color: #4338ca; border-color: rgba(99, 102, 241, .24); }
.pjd-step.is-hcl { background: rgba(16, 185, 129, .1); color: #059669; border-color: rgba(16, 185, 129, .2); }
.pjd-step__no { width: 17px; height: 17px; border-radius: 5px; display: flex; align-items: center; justify-content: center; font-size: 9.5px; font-weight: 800; color: #fff; background: #cbd2e0; }
.pjd-step.is-active .pjd-step__no { background: linear-gradient(135deg, #8b5cf6, #6366f1); }
.pjd-step.is-hcl .pjd-step__no { background: linear-gradient(135deg, #34d399, #10b981); }
.pjd-step em { font-style: normal; font-size: 9px; font-weight: 800; letter-spacing: .06em; color: #059669; background: rgba(16, 185, 129, .14); border-radius: 5px; padding: 2px 6px; }

/* Tabel peserta — grid, bukan <table>, supaya kolomnya persis desain. */
.pjd-tbl { display: none; overflow-x: auto; }
.pjd-tbl__head, .pjd-tbl__row { display: grid; grid-template-columns: 1.8fr 1fr .9fr 1.1fr 1.5fr .45fr; gap: 12px; align-items: center; }
.pjd-tbl__head { padding: 11px 18px; background: #f7f8fc; border-bottom: 1px solid #eef0f7; font-size: 10px; font-weight: 800; letter-spacing: .08em; color: #94a3b8; }
.pjd-tbl__row { padding: 14px 18px; border-bottom: 1px solid #f4f5fb; transition: background .14s; }
.pjd-tbl__row:hover { background: #fbfbfe; }
.pjd-tbl .c { text-align: center; }
.pjd-tbl .r { text-align: right; }
.pjd-tbl__nama { display: flex; align-items: center; gap: 10px; min-width: 0; }
.pjd-tbl__who { display: flex; flex-direction: column; min-width: 0; line-height: 1.3; }
.pjd-tbl__who b { font-size: 13.5px; font-weight: 800; color: #1e293b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.pjd-tbl__who em { font-style: normal; font-size: 11.5px; color: #8792a6; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.pjd-tbl__pos { font-size: 12.5px; color: #475569; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.pjd-tbl__sub { font-size: 12.5px; color: #94a3b8; }
.pjd-tbl__win { display: flex; flex-direction: column; font-size: 11.5px; color: #475569; line-height: 1.4; }
.pjd-tbl__win2 { color: #8792a6; }
.pjd-tbl__win em { font-style: normal; font-size: 9.5px; font-weight: 800; letter-spacing: .06em; color: #b45309; text-transform: uppercase; margin-top: 3px; }
.pjd-tbl__tok { display: flex; align-items: center; justify-content: flex-end; gap: 8px; min-width: 0; }
.pjd-tok { font-size: 12px; font-weight: 700; color: #4f46e5; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.pjd-pill { display: inline-block; font-size: 9.5px; font-weight: 800; letter-spacing: .06em; border-radius: 7px; padding: 4px 9px; }
.pjd-pill.is-ok { color: #059669; background: rgba(16, 185, 129, .12); }
.pjd-pill.is-bad { color: #b91c1c; background: rgba(239, 68, 68, .1); }
.pjd-pill.is-wait { color: #8b93a7; background: #eef0f7; }
.pjd-copy { appearance: none; border: 1px solid #d9def0; background: #fff; width: 28px; height: 28px; border-radius: 8px; display: flex; align-items: center; justify-content: center; cursor: pointer; color: #6366f1; flex: 0 0 auto; transition: all .16s; }
.pjd-copy:hover { background: #6366f1; color: #fff; border-color: #6366f1; }
.pjd-copy--txt { width: auto; height: auto; gap: 7px; padding: 8px 14px; border-radius: 10px; font-family: inherit; font-size: 12px; font-weight: 800; }

/* Kredensial — seluruh pilnya jadi tombol salin: sasaran kliknya lebar, dan
   nilainya tetap terbaca untuk didikte lewat telepon. */
.pjd-credwrap { display: inline-flex; align-items: center; gap: 4px; }
.pjd-eye { appearance: none; border: 1px solid #e6e9f3; background: #fff; width: 26px; height: 26px; border-radius: 8px; display: flex; align-items: center; justify-content: center; cursor: pointer; color: #94a3b8; flex: 0 0 auto; transition: all .16s; }
.pjd-eye:hover { color: #4f46e5; border-color: #c7cdf0; }
.pjd-cred { appearance: none; display: inline-flex; align-items: center; gap: 7px; border: 1px solid #d9def0; background: #f7f8fc; padding: 5px 10px; border-radius: 9px; cursor: pointer; font-family: inherit; font-size: 12px; font-weight: 700; color: #4f46e5; transition: all .16s; letter-spacing: .04em; }
.pjd-cred:hover { background: #eef0fe; border-color: #a5b4fc; }
.pjd-cred:active { transform: scale(.97); }
.pjd-cred b { font-size: 9px; font-weight: 800; letter-spacing: .08em; color: #a2a9ba; }
.pjd-cred--otp { color: #b45309; border-color: #f2e4c4; background: #fffdf7; }
.pjd-cred--otp:hover { background: #fff8ec; border-color: #f59e0b; }

/* ── PAGINASI ── */
.pjd-pager { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; padding-top: 14px; border-top: 1px solid #f1f2f9; }
.pjd-pager--sm { padding-top: 12px; }
.pjd-pager__info { font-size: 12px; font-weight: 700; color: #8792a6; }
.pjd-pager__btns { display: flex; gap: 6px; }
.pjd-pg { appearance: none; border: 1px solid #e6e9f3; background: #fff; width: 32px; height: 32px; border-radius: 10px; display: flex; align-items: center; justify-content: center; cursor: pointer; color: #64748b; transition: all .16s; }
.pjd-pg:hover:not(:disabled) { color: #4f46e5; border-color: #c7cdf0; }
.pjd-pg:disabled { opacity: .4; cursor: not-allowed; }
.pjd-pg.is-on { color: #fff; background: linear-gradient(135deg, #8b5cf6, #6366f1); border-color: transparent; box-shadow: 0 6px 16px rgba(99, 102, 241, .28); }
.pjd-pg--gap { border: none; background: transparent; color: #a2a9ba; cursor: default; width: 20px; }
.pjd-pager--in { padding: 12px 18px 14px; border-top: 1px solid #f4f5fb; }

/* Kartu peserta — dipakai di layar sempit. */
.pjd-rows { display: flex; flex-direction: column; gap: 10px; padding: 14px 15px; }
.pjd-row { border: 1px solid #eef0f7; border-radius: 14px; background: #fbfbfe; padding: 14px 15px; }
.pjd-row__top { display: flex; align-items: center; gap: 11px; }
.pjd-row__in { min-width: 0; flex: 1; }
.pjd-row__nama { font-size: 13.5px; font-weight: 800; color: #1e293b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.pjd-row__pos { font-size: 11.5px; color: #8792a6; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.pjd-row__bot { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-top: 12px; padding-top: 11px; border-top: 1px solid #eef0f7; }

/* ── MODAL UBAH JADWAL KANDIDAT ── */
.pjd-edit { display: flex; flex-direction: column; }
.pjd-edit__who { display: flex; align-items: center; gap: 12px; padding: 12px 14px; border-radius: 14px; background: #f7f8fc; border: 1px solid #eef0f7; margin-bottom: 14px; }
.pjd-edit__who b { display: block; font-size: 14px; font-weight: 800; color: #1e293b; }
.pjd-edit__who span:not(.pjd-ava) { display: block; font-size: 11.5px; color: #8792a6; margin-top: 2px; }
.pjd-edit__note { display: flex; gap: .5rem; margin: 0 0 1rem; padding: .6rem .75rem; border-radius: 10px; background: rgba(245, 158, 11, .1); border: 1px solid rgba(245, 158, 11, .24); font-size: .78rem; line-height: 1.5; color: #92660a; }
.pjd-edit__note .bi { flex: 0 0 auto; margin-top: 1px; color: #d97706; }
.pjd-ibtn:disabled { opacity: .35; cursor: not-allowed; }
.pjd-ibtn:disabled:hover { color: #64748b; border-color: #e6e9f3; }

/* ── ELEMENT PLUS: disamakan dengan kolom isian di atas ── */
.pjd-select, .pjd-date { width: 100%; }
.pjd :deep(.el-select__wrapper),
.pjd :deep(.el-input__wrapper) { border-radius: 13px; background: #fff; box-shadow: 0 0 0 1px #e6e9f3 inset; padding: 6px 15px; min-height: 46px; transition: all .18s; }
.pjd :deep(.el-select__wrapper.is-focused),
.pjd :deep(.el-input__wrapper.is-focus) { box-shadow: 0 0 0 1px #a5b4fc inset, 0 0 0 3px rgba(99, 102, 241, .1); }
.pjd :deep(.el-select__placeholder),
.pjd :deep(.el-input__inner) { font-size: 13.5px; font-weight: 700; color: #1e293b; }
.pjd :deep(.el-select__placeholder.is-transparent),
.pjd :deep(.el-input__inner::placeholder) { font-weight: 500; color: #94a3b8; }
.pjd :deep(.el-input__prefix) { color: #8b5cf6; }
.pjd :deep(.el-loading-mask) { background: rgba(255, 255, 255, .7); border-radius: 14px; }

@media (min-width: 760px) {
    .pjd-pkgs { grid-template-columns: repeat(2, 1fr); }
    .pjd-times { grid-template-columns: repeat(2, 1fr); }
}
@media (min-width: 1180px) {
    .pjd-two { grid-template-columns: 1.25fr 1fr; }
    .pjd-tbl { display: block; }
    .pjd-rows { display: none; }
}
@media (max-width: 760px) {
    .pjd { padding: 20px 16px 44px; }
    .pjd-head h1 { font-size: 22px; }
    .pjd-filter { padding: 12px 16px; }
    .pjd-fsel, .pjd-fsel--sm { width: 100%; }
}
</style>
