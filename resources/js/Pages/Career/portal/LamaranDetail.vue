<!-- WEB CAREER — Portal Kandidat: DETAIL LAMARAN (POV peserta), 1:1 desain
     "Lamaran Saya" (Claude Design). Layout fluid + blob, kartu hero, banner
     status, progres seleksi, kartu Tahap Aktif (tes HCLearn token/OTP/jendela +
     Mulai Tes, ATAU formulir tahap), timeline tahap, akordeon Formulir & Berkas
     Saya + lightbox. Data 100% nyata; fungsi akses tes & kirim formulir utuh. -->
<template>
    <Head><title>Detail Lamaran - EVO Career</title></Head>

    <div class="ld">
        <div class="ld-blob ld-blob--a"></div>
        <div class="ld-blob ld-blob--b"></div>

        <div class="ld-wrap">
            <!-- [feat/feedback] Banner feedback -->
            <!-- ═══ FEEDBACK CTA BANNER (Eye-Catching & Ultra-Premium) ═══ -->
            <div
                v-if="showFeedbackBanner && feedbackPending"
                :class="[
                    'ld-feedback-banner',
                    feedbackPending.Hasil_Akhir === 'DITERIMA'
                        ? 'ld-feedback-banner--wajib'
                        : 'ld-feedback-banner--optional',
                ]"
            >
                <div class="ld-feedback-banner__glow"></div>
                <div class="ld-feedback-banner__content">
                    <div class="ld-feedback-banner__left">
                        <div class="ld-feedback-banner__icon">
                            <i class="bi bi-stars"></i>
                        </div>
                        <div class="ld-feedback-banner__text">
                            <h4 class="ld-feedback-banner__title">
                                <template v-if="feedbackPending.Hasil_Akhir === 'DITERIMA'"
                                    >Selamat! Mohon isi feedback untuk menyelesaikan proses 🎉</template
                                >
                                <template v-else>Bantu Kami Berbenah &amp; Tingkatkan Layanan ✨</template>
                            </h4>
                            <p class="ld-feedback-banner__sub">
                                {{
                                    feedbackPending.Hasil_Akhir === 'DITERIMA'
                                        ? 'Masukan Anda wajib diisi untuk kelengkapan administrasi.'
                                        : 'Ulasan dan saran Anda sangat berharga bagi evaluasi rekrutmen kami (hanya 1-2 menit).'
                                }}
                            </p>
                        </div>
                    </div>
                    <div class="ld-feedback-banner__actions">
                        <a
                            :href="feedbackPending.feedback_url"
                            class="ld-feedback-banner__btn ld-feedback-banner__btn--fill"
                            title="Bantu kami evaluasi proses rekrutmen ini, masukan Anda sangat berharga!"
                        >
                            <svg
                                width="18"
                                height="18"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2.2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            >
                                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" />
                                <path d="M12 8v4M12 16h.01" />
                            </svg>
                            <span>Bantu Kami Berbenah ✨</span>
                        </a>
                    </div>
                </div>
            </div>

            <Link href="/kandidat/portal" class="ld-back">
                <svg
                    width="16"
                    height="16"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2.4"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                >
                    <path d="M19 12H5M11 18l-6-6 6-6" />
                </svg>
                Lamaran Saya
            </Link>

            <!-- ═══ HERO HEADER ═══ -->
            <div class="ld-hero">
                <span class="ld-hero__bar"></span>
                <div class="ld-hero__glow"></div>
                <div class="ld-hero__in">
                    <div class="ld-hero__avatar">
                        <svg
                            v-if="lamaran.kategori === 'MT'"
                            width="26"
                            height="26"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        >
                            <path d="M22 10L12 5 2 10l10 5 10-5z" />
                            <path d="M6 12v5c3 3 9 3 12 0v-5" />
                        </svg>
                        <svg
                            v-else
                            width="26"
                            height="26"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        >
                            <rect x="3" y="7" width="18" height="13" rx="2" />
                            <path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" />
                        </svg>
                    </div>
                    <div style="flex: 1; min-width: 0">
                        <div class="ld-hero__badges">
                            <span class="ld-chip" :style="katChipStyle">{{ katLabel(lamaran.kategori) }}</span>
                            <span class="ld-chip" :style="stChipStyle">
                                <span v-if="stKey === 'berjalan'" class="ld-pulse"></span>
                                <svg
                                    v-else-if="stKey === 'lolos'"
                                    width="13"
                                    height="13"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2.6"
                                    stroke-linecap="round"
                                >
                                    <path d="M20 6L9 17l-5-5" />
                                </svg>
                                <svg
                                    v-else
                                    width="13"
                                    height="13"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2.2"
                                    stroke-linecap="round"
                                >
                                    <circle cx="12" cy="12" r="9" />
                                    <path d="M15 9l-6 6M9 9l6 6" />
                                </svg>
                                {{ stLabel(lamaran.status) }}
                            </span>
                        </div>
                        <h1 class="ld-hero__title">{{ lamaran.posisi || lamaran.program }}</h1>
                        <div class="ld-hero__meta">
                            {{ lamaran.program }}<template v-if="lamaran.lokasi"> · {{ lamaran.lokasi }}</template> ·
                            Dilamar {{ fmtTanggal(lamaran.waktuLamar) }} ·
                            <span class="ld-mono">{{ lamaran.kode }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ═══ BANNER STATUS ═══ -->
            <div class="ld-banner" :style="{ background: ST[stKey].bg, borderColor: ST[stKey].bg }">
                <span class="ld-banner__ico" :style="{ background: ST[stKey].dot }">
                    <svg
                        v-if="stKey === 'lolos'"
                        width="20"
                        height="20"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2.2"
                        stroke-linecap="round"
                    >
                        <path d="M20 6L9 17l-5-5" />
                    </svg>
                    <svg
                        v-else-if="stKey === 'gugur'"
                        width="20"
                        height="20"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2.2"
                        stroke-linecap="round"
                    >
                        <circle cx="12" cy="12" r="9" />
                        <path d="M15 9l-6 6M9 9l6 6" />
                    </svg>
                    <svg
                        v-else
                        width="20"
                        height="20"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2.2"
                        stroke-linecap="round"
                    >
                        <circle cx="12" cy="12" r="9" />
                        <path d="M12 7v5l3 2" />
                    </svg>
                </span>
                <div style="min-width: 0">
                    <div class="ld-banner__title" :style="{ color: ST[stKey].c }">{{ banner.title }}</div>
                    <div class="ld-banner__text" :style="{ color: ST[stKey].c }">{{ banner.text }}</div>
                </div>
            </div>

            <!-- ═══ KEPUTUSAN TAHAP ═══
                 Muncul begitu admin mengetuk palu DAN hasilnya memang sudah boleh
                 diumumkan (Mode Pengumuman). Server yang menahan: kalau belum
                 boleh, `hasil` tidak dikirim sama sekali ke sini. -->
            <transition name="ld-verdict">
                <div v-if="putusanTahap" class="ld-verdict" :class="putusanTahap.lolos ? 'is-lolos' : 'is-gugur'">
                    <span class="ld-nico" aria-hidden="true">
                        <svg viewBox="0 0 44 44" width="40" height="40">
                            <circle class="ld-nico__halo" cx="22" cy="22" r="17" />
                            <circle class="ld-nico__ring" cx="22" cy="22" r="13.5" />
                            <path v-if="putusanTahap.lolos" class="ld-nico__check" d="M15.8 22.3l4.3 4.3 8.1-8.9" />
                            <g v-else class="ld-nico__cross">
                                <line class="ld-nico__bang" x1="17" y1="17" x2="27" y2="27" />
                                <line class="ld-nico__bang" x1="27" y1="17" x2="17" y2="27" />
                            </g>
                        </svg>
                    </span>
                    <div style="min-width: 0; flex: 1">
                        <div class="ld-verdict__eyebrow">
                            TAHAP {{ putusanTahap.urutan }} DARI {{ totalTahap }} · KEPUTUSAN
                        </div>
                        <div class="ld-verdict__title">
                            {{ putusanTahap.label }} — {{ putusanTahap.lolos ? 'Lolos' : 'Tidak Lolos' }}
                        </div>
                        <p class="ld-verdict__text">{{ putusanTahap.teks }}</p>
                    </div>
                    <span class="ld-verdict__badge">{{ putusanTahap.lolos ? 'LOLOS' : 'TIDAK LOLOS' }}</span>
                </div>
            </transition>

            <!-- Keputusan sudah ada tapi belum waktunya diumumkan. Kandidat tetap
                 diberi tahu bahwa tahapnya SUDAH diputus, tanpa isinya. -->
            <div v-if="!putusanTahap && tahapTertunda" class="ld-verdict is-tunda">
                <span class="ld-nico" aria-hidden="true">
                    <svg viewBox="0 0 44 44" width="40" height="40">
                        <circle class="ld-nico__halo" cx="22" cy="22" r="17" />
                        <circle class="ld-nico__ring" cx="22" cy="22" r="13.5" />
                        <line class="ld-nico__hand ld-nico__hand--h" x1="22" y1="22" x2="22" y2="16" />
                        <line class="ld-nico__hand ld-nico__hand--m" x1="22" y1="22" x2="26.5" y2="22" />
                        <circle class="ld-nico__pin" cx="22" cy="22" r="1.6" />
                    </svg>
                </span>
                <div style="min-width: 0; flex: 1">
                    <div class="ld-verdict__eyebrow">
                        TAHAP {{ tahapTertunda.urutan }} DARI {{ totalTahap }} · MENUNGGU PENGUMUMAN
                    </div>
                    <div class="ld-verdict__title">{{ tahapTertunda.label }} sudah diputuskan</div>
                    <p class="ld-verdict__text">
                        {{ tahapTertunda.pengumuman?.label || 'Hasilnya diumumkan menyusul.'
                        }}<template v-if="tahapTertunda.pengumuman?.tanggal">
                            Dijadwalkan {{ fmtWaktu(tahapTertunda.pengumuman.tanggal) }}.</template
                        >
                    </p>
                </div>
            </div>

            <!-- ═══ PROGRES SELEKSI ═══ -->
            <div class="ld-prog">
                <div class="ld-prog__head">
                    <span class="ld-seclabel">PROGRES SELEKSI</span>
                    <span style="font-size: 13px; font-weight: 800; color: #4f46e5"
                        >Tahap {{ posisiSekarang }} dari {{ totalTahap }} · {{ persen }}%</span
                    >
                </div>
                <div class="ld-prog__bar"><div :style="{ width: persen + '%' }"></div></div>
                <div class="ld-steps">
                    <div v-for="(s, i) in heroSteps" :key="i" class="ld-step">
                        <div v-if="i > 0" class="ld-step__line" :style="{ background: s.line }"></div>
                        <div class="ld-step__node" :class="'is-' + s.st">
                            <svg
                                v-if="s.st === 'done'"
                                width="15"
                                height="15"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="3"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            >
                                <path d="M20 6L9 17l-5-5" />
                            </svg>
                            <svg
                                v-else-if="s.st === 'fail'"
                                width="14"
                                height="14"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2.6"
                                stroke-linecap="round"
                            >
                                <path d="M18 6L6 18M6 6l12 12" />
                            </svg>
                            <template v-else>{{ i + 1 }}</template>
                        </div>
                        <div class="ld-step__lbl" :style="{ color: s.lbl }">{{ s.name }}</div>
                    </div>
                </div>
            </div>

            <div class="ld-mainc">
                <!-- ── Tahap aktif: tes online (HCLearn) ── -->
                <div v-if="tahapTes" class="ld-card ld-act">
                    <div class="ld-act__head">
                        <span class="ld-act__ico">
                            <svg
                                width="24"
                                height="24"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            >
                                <path d="M22 10L12 5 2 10l10 5 10-5z" />
                                <path d="M6 12v5c3 3 9 3 12 0v-5" />
                            </svg>
                        </span>
                        <div style="min-width: 0">
                            <div class="ld-eyebrow">
                                TAHAP {{ tahapTes.urutan }} DARI {{ totalTahap }} ·
                                {{ (tahapTes.tipeNama || 'Tes Online').toUpperCase() }}
                            </div>
                            <!-- Judul memakai NAMA TAHAP, bukan nama alat tesnya.
                                     Nama instrumen ("General Reasoning Test") dan
                                     platform penyelenggara adalah informasi internal
                                     rekrutmen — membocorkannya memudahkan kandidat
                                     mencari bocoran soal, dan tidak ada gunanya
                                     bagi dia. -->
                            <div class="ld-act__title">{{ tahapTes.label }}</div>
                            <div class="ld-act__sub">
                                {{
                                    aktivitas.length > 1
                                        ? 'Tahap ini terdiri dari beberapa aktivitas.'
                                        : 'Ikuti aktivitas di bawah sesuai jadwalnya.'
                                }}
                            </div>
                        </div>
                    </div>

                    <div v-if="!sesi.ujian?.terjadwal" class="ld-notice ld-notice--wait">
                        <span class="ld-nico ld-nico--wait" aria-hidden="true">
                            <svg viewBox="0 0 44 44" width="34" height="34">
                                <circle class="ld-nico__halo" cx="22" cy="22" r="17" />
                                <g class="ld-nico__glass">
                                    <path class="ld-nico__stroke" d="M14.5 12.5h15l-7.5 9.5zM14.5 31.5h15l-7.5-9.5z" />
                                    <line class="ld-nico__stroke" x1="13.5" y1="12.5" x2="30.5" y2="12.5" />
                                    <line class="ld-nico__stroke" x1="13.5" y1="31.5" x2="30.5" y2="31.5" />
                                </g>
                                <circle class="ld-nico__sand" cx="22" cy="22" r="1.4" />
                            </svg>
                        </span>
                        <div>
                            <b>Menunggu dijadwalkan</b>
                            <p>
                                Kamu sudah masuk tahap ini. Tim rekrutmen sedang menyiapkan jadwalnya — token, kode OTP,
                                dan waktu pengerjaan akan muncul di sini begitu terbit, dan kamu diberi tahu lewat
                                email.
                            </p>
                        </div>
                    </div>

                    <template v-else>
                        <!-- Token & OTP hilang begitu tesnya selesai dikerjakan:
                                 tidak bisa dipakai lagi, dan menyisakannya membuat
                                 kandidat mengira masih ada yang harus dibuka. -->
                        <div v-if="!sudahSelesai(sesi)" class="ld-cred">
                            <div class="ld-cred__item">
                                <span class="ld-cred__lbl">TOKEN AKSES</span>
                                <span class="ld-cred__val ld-mono">{{ sesi.ujian.token || '—' }}</span>
                            </div>
                            <div class="ld-cred__item">
                                <span class="ld-cred__lbl">KODE OTP</span>
                                <span class="ld-cred__val ld-mono">{{ sesi.ujian.otp || '—' }}</span>
                            </div>
                            <div class="ld-cred__item">
                                <span class="ld-cred__lbl">WAKTU MULAI</span>
                                <span class="ld-cred__val">{{ fmtWaktu(sesi.ujian.waktuMulai) }}</span>
                            </div>
                            <div class="ld-cred__item">
                                <span class="ld-cred__lbl">WAKTU BERAKHIR</span>
                                <span class="ld-cred__val">{{ fmtWaktu(sesi.ujian.waktuSelesai) }}</span>
                            </div>
                        </div>

                        <!-- JADWAL TATAP MUKA (wawancara / tes offline / MCU).
                                 Kartunya satu komponen — lihat JadwalKartu.vue. -->
                        <div v-for="j in jadwalAktivitas" :key="j.key" class="ld-jdwwrap">
                            <JadwalKartu :jadwal="j" />
                        </div>

                        <!-- HASIL MCU. Menyangkut kesehatan kandidat sendiri,
                                 jadi ia berhak tahu — tapi hanya kesimpulannya,
                                 bukan rincian medis. -->
                        <div v-for="m in hasilMcu" :key="m.key" class="ld-mcu" :class="'is-' + m.status.toLowerCase()">
                            <span class="ld-mcu__ico"><i class="bi bi-heart-pulse-fill"></i></span>
                            <div style="min-width: 0; flex: 1">
                                <div class="ld-eyebrow">HASIL PEMERIKSAAN KESEHATAN</div>
                                <div class="ld-mcu__judul">{{ m.label }}</div>
                                <div class="ld-mcu__meta">
                                    <span v-if="m.penyedia">{{ m.penyedia }}</span>
                                    <span v-if="m.tanggal">{{ fmtWaktu(m.tanggal) }}</span>
                                </div>
                                <p v-if="m.catatan" class="ld-mcu__cat">{{ m.catatan }}</p>
                            </div>
                        </div>

                        <!-- BERKAS YANG HARUS KANDIDAT UNGGAH — lihat
                             components/career/UnggahAktivitas.vue. -->
                        <UnggahAktivitas
                            :daftar="unggahAktivitas" :berkas="berkasTes"
                            :unggah-di="unggahDi" :kirim-di="kirimDi"
                            @pilih="unggahBerkasTes" @hapus="hapusBerkasTes"
                            @buka="bukaDok" @kirim="kirimBerkasTes"
                        />

                        <!-- KEADAAN TAHAP — SATU KALIMAT, BUKAN DAFTAR.
                             Dulu di sini berdiri daftar seluruh aktivitas
                             berikut statusnya ("DISC — Menunggu", "FGD —
                             Menunggu"). Itu membuka susunan asesmen
                             perusahaan kepada orang yang sedang dinilai: ia
                             tahu persis alat ukur apa yang dipakai dan yang
                             mana belum dilalui, dan itu bisa dipersiapkan.
                             Untuk perusahaan tertutup, bagian itu memang
                             bukan miliknya.

                             Yang tersisa hanya yang benar-benar berguna
                             baginya: sedang menunggu apa, dan apakah ada
                             yang harus ia kerjakan. Rincian jadwal & tombol
                             tesnya tetap tampil di kartunya masing-masing
                             di bawah — itu memang undangan untuk dia. -->
                        <div v-if="aktivitas.length > 1" class="ld-keadaan" :class="`is-${keadaanTahap.nada}`">
                            <!-- IKON SVG BERANIMASI, bukan glyph font.
                                 Tiap keadaan punya gerakannya sendiri dan
                                 gerakan itulah yang menyampaikan artinya
                                 sebelum kalimatnya dibaca: jam berdetak =
                                 menunggu, tanda centang tergambar = tuntas,
                                 denyut = ada yang menunggu dikerjakan.
                                 Seluruhnya dimatikan otomatis bagi pengguna
                                 yang meminta gerak dikurangi. -->
                            <span class="ld-keadaan__ico" aria-hidden="true">
                                <svg viewBox="0 0 48 48" width="30" height="30" fill="none">
                                    <circle class="ld-kico__halo" cx="24" cy="24" r="21" />

                                    <!-- MENUNGGU JADWAL — jarum jam berputar pelan. -->
                                    <template v-if="keadaanTahap.nada === 'tunggu'">
                                        <circle class="ld-kico__ring" cx="24" cy="24" r="14" />
                                        <line class="ld-kico__jarum ld-kico__jarum--h" x1="24" y1="24" x2="24" y2="16.5" />
                                        <line class="ld-kico__jarum ld-kico__jarum--m" x1="24" y1="24" x2="29.5" y2="24" />
                                        <circle class="ld-kico__pin" cx="24" cy="24" r="1.7" />
                                    </template>

                                    <!-- DITAHAN TIM — gembok dengan gagang yang berdenyut. -->
                                    <template v-else-if="keadaanTahap.nada === 'tinjau'">
                                        <path class="ld-kico__gagang" d="M18.5 22v-3.5a5.5 5.5 0 0 1 11 0V22" />
                                        <rect class="ld-kico__body" x="15.5" y="22" width="17" height="12.5" rx="3" />
                                        <circle class="ld-kico__pin" cx="24" cy="28.2" r="1.8" />
                                    </template>

                                    <!-- SUDAH DIJADWALKAN — kalender dengan centang tergambar. -->
                                    <template v-else-if="keadaanTahap.nada === 'jadwal'">
                                        <rect class="ld-kico__ring" x="14" y="15.5" width="20" height="18" rx="3" />
                                        <line class="ld-kico__jarum" x1="14" y1="21" x2="34" y2="21" />
                                        <line class="ld-kico__jarum" x1="19.5" y1="13" x2="19.5" y2="17" />
                                        <line class="ld-kico__jarum" x1="28.5" y1="13" x2="28.5" y2="17" />
                                        <path class="ld-kico__check" d="M19.5 27.5l3.4 3.4 6-6.4" />
                                    </template>

                                    <!-- BISA DIKERJAKAN — tombol putar yang berdenyut. -->
                                    <template v-else-if="keadaanTahap.nada === 'aksi'">
                                        <circle class="ld-kico__ring ld-kico__ring--denyut" cx="24" cy="24" r="14" />
                                        <path class="ld-kico__play" d="M21 18.8l9 5.2-9 5.2z" />
                                    </template>

                                    <!-- BARU TERKIRIM — pesawat kertas melaju,
                                         centang menyusul saat ia mendarat. -->
                                    <template v-else-if="keadaanTahap.nada === 'kirim'">
                                        <path class="ld-kico__pesawat" d="M33.5 15.5L14 23.2l7.6 2.6 2.6 7.6z" />
                                        <path class="ld-kico__check" d="M21.6 25.8l11.9-10.3" />
                                    </template>

                                    <!-- SEDANG DITINJAU — pasir jam mengalir. -->
                                    <template v-else>
                                        <path class="ld-kico__ring" d="M17 14h14M17 34h14M18.5 14c0 6 5.5 7.4 5.5 10s-5.5 4-5.5 10M29.5 14c0 6-5.5 7.4-5.5 10s5.5 4 5.5 10" />
                                        <circle class="ld-kico__pasir" cx="24" cy="24" r="1.5" />
                                    </template>
                                </svg>
                            </span>
                            <div class="ld-keadaan__teks">
                                <b>{{ keadaanTahap.judul }}</b>
                                <p>{{ keadaanTahap.pesan }}</p>
                            </div>
                        </div>

                        <!-- SUDAH DIKERJAKAN — tes tidak bisa diulang. Yang ditunggu
                                 berikutnya berbeda tergantung cara tahap menyimpulkan. -->
                        <div v-if="sudahSelesai(sesi)" class="ld-notice ld-notice--done">
                            <span class="ld-nico ld-nico--done" aria-hidden="true">
                                <svg viewBox="0 0 44 44" width="34" height="34">
                                    <circle class="ld-nico__halo" cx="22" cy="22" r="17" />
                                    <circle class="ld-nico__ring" cx="22" cy="22" r="13.5" />
                                    <path class="ld-nico__check" d="M15.8 22.3l4.3 4.3 8.1-8.9" />
                                </svg>
                            </span>
                            <div>
                                <b>Tes sudah kamu kerjakan — tidak dapat diulang</b>
                                <p>{{ pesanSetelahTes }}</p>
                            </div>
                        </div>
                        <div v-else-if="belumMulai(sesi)" class="ld-notice ld-notice--wait">
                            <span class="ld-nico ld-nico--wait" aria-hidden="true">
                                <svg viewBox="0 0 44 44" width="34" height="34">
                                    <circle class="ld-nico__halo" cx="22" cy="22" r="17" />
                                    <circle class="ld-nico__ring" cx="22" cy="22" r="13.5" />
                                    <line class="ld-nico__hand ld-nico__hand--h" x1="22" y1="22" x2="22" y2="16" />
                                    <line class="ld-nico__hand ld-nico__hand--m" x1="22" y1="22" x2="26.5" y2="22" />
                                    <circle class="ld-nico__pin" cx="22" cy="22" r="1.6" />
                                </svg>
                            </span>
                            <div>
                                <b>Tes belum dibuka</b>
                                <p>
                                    Tombol akan aktif otomatis saat waktu mulai tiba{{
                                        hitungMundur(sesi) ? ' — ' + hitungMundur(sesi) : ''
                                    }}.
                                </p>
                            </div>
                        </div>
                        <div v-else-if="sudahLewat(sesi)" class="ld-notice ld-notice--err">
                            <span class="ld-nico ld-nico--err" aria-hidden="true">
                                <svg viewBox="0 0 44 44" width="34" height="34">
                                    <circle class="ld-nico__halo" cx="22" cy="22" r="17" />
                                    <path class="ld-nico__tri" d="M22 12.2 33.2 31.2H10.8z" />
                                    <line class="ld-nico__bang" x1="22" y1="19.6" x2="22" y2="24.4" />
                                    <circle class="ld-nico__dot" cx="22" cy="27.6" r="1.4" />
                                </svg>
                            </span>
                            <div>
                                <b>Waktu tes berakhir</b>
                                <p>Jendela pengerjaan sudah lewat. Hubungi tim rekrutmen bila ada kendala.</p>
                            </div>
                        </div>

                        <!-- Tombol DISEMBUNYIKAN setelah tes dikerjakan. Menampilkannya
                                 dalam keadaan mati tetap mengesankan tes bisa diulang. -->
                        <!-- Aktivitas yang belum tiba gilirannya diberi
                             keterangan, bukan sekadar tombol mati: "Tes
                             Terkunci" tanpa sebab terbaca sebagai kesalahan
                             sistem, dan kandidat menghubungi tim untuk
                             sesuatu yang memang sudah semestinya. -->
                        <div v-if="sesi.terkunci" class="ld-antre">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><rect x="4" y="11" width="16" height="10" rx="2" /><path d="M8 11V7a4 4 0 0 1 8 0v4" /></svg>
                            <span>
                                <b>Belum gilirannya</b>
                                <template v-if="sesi.menunggu"> — menunggu <b>{{ sesi.menunggu }}</b> selesai lebih dulu.</template>
                                <template v-else> — tahap ini dikerjakan berurutan.</template>
                            </span>
                        </div>
                        <!-- LAYAR ANTARA — kandidat tahu ia sedang dipindahkan,
                             bukan halaman yang tiba-tiba hilang. Masih bisa
                             dibatalkan selama hitungan berjalan. -->
                        <div v-else-if="menujuUjian" class="ld-pindah">
                            <span class="ld-pindah__ring" aria-hidden="true">
                                <svg viewBox="0 0 48 48" width="34" height="34" fill="none">
                                    <circle class="ld-pindah__jalur" cx="24" cy="24" r="19" />
                                    <circle class="ld-pindah__isi" cx="24" cy="24" r="19" />
                                </svg>
                                <b>{{ hitungPindah }}</b>
                            </span>
                            <div class="ld-pindah__teks">
                                <b>Menyiapkan ruang ujian…</b>
                                <p>Kamu akan dipindahkan ke halaman ujian dalam {{ hitungPindah }} detik. Jangan tutup halaman ini.</p>
                            </div>
                            <button type="button" class="ld-pindah__batal" @click="batalKeUjian">Batal</button>
                        </div>
                        <!-- BARU PULANG DARI RUANG UJIAN, hasilnya belum sampai.
                             Menggantikan tombol, tidak sekadar mematikannya:
                             tombol mati pun masih terbaca sebagai "tesnya
                             masih menungguku", padahal ia sudah menekan kirim
                             semenit yang lalu. Hilang begitu hasilnya masuk. -->
                        <div v-else-if="tesDitunggu && tesDitunggu.id === sesi.id" class="ld-terkirim">
                            <span class="ld-terkirim__ring" aria-hidden="true">
                                <svg viewBox="0 0 44 44" width="32" height="32" fill="none">
                                    <circle class="ld-terkirim__halo" cx="22" cy="22" r="17" />
                                    <path class="ld-terkirim__pesawat" d="M31 13.5L12.5 21l7.2 2.5 2.5 7.2z" />
                                </svg>
                            </span>
                            <div class="ld-terkirim__teks">
                                <b>Jawabanmu sudah terkirim</b>
                                <p>Hasilnya sedang diterima sistem. Halaman ini memperbarui dirinya sendiri — tidak perlu kamu muat ulang.</p>
                            </div>
                        </div>
                        <!-- SUDAH DIKERJAKAN — tidak ada tombol apa pun, dan tidak
                             ada panel tambahan: keterangannya sudah disampaikan
                             sekali di atas ("Tes sudah kamu kerjakan"). Panel
                             kedua di sini pernah ada, dan hasilnya kandidat
                             membaca kalimat yang sama dua kali berturut-turut
                             lalu mengira keduanya menunjuk tes yang berbeda. -->
                        <button
                            v-else-if="!sudahSelesai(sesi)"
                            class="ld-btn-tes"
                            :disabled="!bisaAkses(sesi)"
                            @click="bukaTes(sesi)"
                        >
                            <svg
                                v-if="bisaAkses(sesi)"
                                width="17"
                                height="17"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2.4"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            >
                                <path
                                    d="M15 3h6v6M10 14L21 3M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"
                                />
                            </svg>
                            <svg
                                v-else
                                width="17"
                                height="17"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2.2"
                                stroke-linecap="round"
                            >
                                <rect x="4" y="11" width="16" height="10" rx="2" />
                                <path d="M8 11V7a4 4 0 0 1 8 0v4" />
                            </svg>
                            {{ bisaAkses(sesi) ? 'Mulai Tes Sekarang' : 'Tes Terkunci' }}
                        </button>
                    </template>
                </div>

                <!-- ── Tahap aktif: formulir yang harus diisi ── -->
                <div v-else-if="tugas && (komponen || tugas.schema)" class="ld-card ld-act">
                    <div class="ld-act__head">
                        <span class="ld-act__ico">
                            <svg
                                width="22"
                                height="22"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            >
                                <path d="M12 20h9" />
                                <path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z" />
                            </svg>
                        </span>
                        <div style="min-width: 0">
                            <div class="ld-eyebrow">TAHAP AKTIF · FORMULIR</div>
                            <div class="ld-act__title">{{ tugas.label }}</div>
                            <div class="ld-act__sub">Lengkapi formulir berikut untuk melanjutkan proses.</div>
                        </div>
                    </div>
                    <div class="ld-act__form">
                        <DynamicForm
                            v-if="tugas.schema"
                            v-model="jawaban"
                            :skema="tugas.schema"
                            :judul="tugas.formulirNama || tugas.label"
                            :konteks="konteksForm"
                            :langkah-awal="langkahAwal"
                            :disabled="mengirim"
                            label-kirim="Kirim &amp; Lanjutkan"
                            @kirim="kirim"
                            @berkas="onBerkas"
                            @pindah-langkah="simpanDraf"
                        />
                        <component
                            v-else
                            :is="komponen"
                            v-model="jawaban"
                            :konteks="konteksForm"
                            :langkah-awal="langkahAwal"
                            :disabled="mengirim"
                            label-kirim="Kirim &amp; Lanjutkan"
                            @kirim="kirim"
                            @berkas="onBerkas"
                            @pindah-langkah="simpanDraf"
                        />
                    </div>

                    <!-- BERKAS YANG HARUS KANDIDAT UNGGAH.
                         SATU TAHAP BISA MENUNTUT KEDUANYA: mengisi formulir
                         DAN menyerahkan dokumen (ijazah terpindai, portofolio).
                         Dulu kartu ini hanya memuat formulirnya, sehingga
                         setelan "kandidat harus mengunggah" di Master Alur
                         diam-diam tidak berlaku pada tahap berformulir — dan
                         tidak ada satu pun layar yang mengabarkannya. -->
                    <UnggahAktivitas
                        :daftar="unggahAktivitas" :berkas="berkasTes"
                        :unggah-di="unggahDi" :kirim-di="kirimDi"
                        @pilih="unggahBerkasTes" @hapus="hapusBerkasTes"
                        @buka="bukaDok" @kirim="kirimBerkasTes"
                    />
                </div>

                <!-- ── Tahap aktif: ditangani tim rekrutmen (wawancara, MCU,
                         screening, penawaran). Tanpa kartu ini halaman terlihat
                         kosong dan kandidat tidak tahu sedang menunggu apa. ── -->
                <div v-else-if="tahapAktif" class="ld-card ld-act">
                    <div class="ld-act__head">
                        <span class="ld-act__ico">
                            <svg
                                width="22"
                                height="22"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            >
                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                                <circle cx="9" cy="7" r="4" />
                                <path d="M23 21v-2a4 4 0 0 0-3-3.87" />
                            </svg>
                        </span>
                        <div style="min-width: 0">
                            <div class="ld-eyebrow">
                                TAHAP {{ tahapAktif.urutan }} DARI {{ totalTahap }} ·
                                {{ (tahapAktif.tipeNama || 'Proses Seleksi').toUpperCase() }}
                            </div>
                            <div class="ld-act__title">{{ tahapAktif.label }}</div>
                            <div class="ld-act__sub">{{ pesanTahap.sub }}</div>
                        </div>
                    </div>
                    <!-- JADWAL & HASIL MCU juga muncul di sini. Sebelumnya
                             keduanya hanya dirender di kartu tes online, sehingga
                             tahap yang MURNI tatap muka — Wawancara Manajemen,
                             MCU — tidak pernah menampilkannya meski undangannya
                             sudah terkirim. -->
                    <div v-for="j in jadwalAktivitas" :key="'j' + j.key" class="ld-jdwwrap">
                        <JadwalKartu :jadwal="j" />
                    </div>

                    <!-- BERKAS YANG HARUS KANDIDAT UNGGAH — satu komponen
                         yang sama dengan kartu tahap lainnya, supaya tombol
                         kirimnya tidak lagi bisa hilang di salah satunya. -->
                    <UnggahAktivitas
                        :daftar="unggahAktivitas" :berkas="berkasTes"
                        :unggah-di="unggahDi" :kirim-di="kirimDi"
                        @pilih="unggahBerkasTes" @hapus="hapusBerkasTes"
                        @buka="bukaDok" @kirim="kirimBerkasTes"
                    />

                    <div
                        v-for="m in hasilMcu"
                        :key="'m' + m.key"
                        class="ld-mcu"
                        :class="'is-' + m.status.toLowerCase()"
                    >
                        <span class="ld-mcu__ico"><i class="bi bi-heart-pulse-fill"></i></span>
                        <div style="min-width: 0; flex: 1">
                            <div class="ld-eyebrow">HASIL PEMERIKSAAN KESEHATAN</div>
                            <div class="ld-mcu__judul">{{ m.label }}</div>
                            <div class="ld-mcu__meta">
                                <span v-if="m.penyedia">{{ m.penyedia }}</span>
                                <span v-if="m.tanggal">{{ fmtWaktu(m.tanggal) }}</span>
                            </div>
                            <p v-if="m.catatan" class="ld-mcu__cat">{{ m.catatan }}</p>
                        </div>
                    </div>

                    <!-- ═══ PENAWARAN — KETERANGAN, BUKAN TINDAKAN ═══
                         Tombol "Terima Penawaran" dan "Mengundurkan Diri"
                         DICABUT beserta endpoint-nya. Keputusan itu kini hanya
                         dicatat tim di worklist, lewat "Keputusan dari
                         Kandidat" — satu pintu, dengan jejak siapa mencatat dan
                         kapan.

                         Kartunya sendiri TETAP ADA. Menghapusnya sekalian
                         membuat kandidat di tahap penawaran melihat halaman
                         yang kosong: pemberitahuan "menunggu dihubungi" di
                         bawah sengaja tidak berlaku untuk tahap penawaran,
                         jadi tak ada satu pun kalimat yang menjelaskan apa yang
                         sedang terjadi padanya. -->
                    <div v-if="tahapAktif.penawaran" class="ld-offer">
                        <div class="ld-offer__head">
                            <span class="ld-offer__ico"><i class="bi bi-envelope-paper-fill"></i></span>
                            <div style="min-width: 0; flex: 1">
                                <div class="ld-eyebrow">PENAWARAN UNTUKMU</div>
                                <div class="ld-offer__judul">{{ tahapAktif.label }}</div>
                            </div>
                        </div>

                        <div class="ld-offer__wait">
                            <i class="bi bi-hourglass-split"></i>
                            <div style="min-width: 0">
                                <b>Tim rekrutmen akan menghubungimu.</b>
                                <p>
                                    Jadwal pertemuan dan surat penawarannya dikirim lewat email dan muncul di
                                    halaman ini. Sampaikan keputusanmu langsung kepada tim rekrutmen yang
                                    menghubungimu — merekalah yang mencatatnya.
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Kalimat "menunggu dihubungi" hanya benar SELAMA belum
                             ada jadwal. Setelah dijadwalkan ia justru menyesatkan.
                             Pada tahap penawaran, kartu di atas sudah menjelaskan
                             apa yang ditunggu — jadi tidak diulang. -->
                    <div v-if="!jadwalAktivitas.length && !tahapAktif.penawaran" class="ld-notice ld-notice--wait">
                        <span class="ld-nico ld-nico--wait" aria-hidden="true">
                            <svg viewBox="0 0 44 44" width="34" height="34">
                                <circle class="ld-nico__halo" cx="22" cy="22" r="17" />
                                <g class="ld-nico__glass">
                                    <path class="ld-nico__stroke" d="M14.5 12.5h15l-7.5 9.5zM14.5 31.5h15l-7.5-9.5z" />
                                    <line class="ld-nico__stroke" x1="13.5" y1="12.5" x2="30.5" y2="12.5" />
                                    <line class="ld-nico__stroke" x1="13.5" y1="31.5" x2="30.5" y2="31.5" />
                                </g>
                                <circle class="ld-nico__sand" cx="22" cy="22" r="1.4" />
                            </svg>
                        </span>
                        <div>
                            <b>{{ pesanTahap.judul }}</b>
                            <p>{{ pesanTahap.teks }}</p>
                        </div>
                    </div>
                </div>

                <!-- ═══ TAB ISI BAWAH ═══
                         Alur, detail lowongan, formulir, dan catatan hasil dulu
                         ditumpuk vertikal sehingga halaman ini sangat panjang dan
                         kandidat harus menggulung jauh untuk menemukan berkasnya.
                         Dipisah jadi tab: yang dicari langsung terjangkau. -->
                <div class="ld-tabs" role="tablist">
                    <button
                        v-for="t in tabs"
                        :key="t.k"
                        type="button"
                        role="tab"
                        class="ld-tabs__b"
                        :class="{ 'is-on': tab === t.k }"
                        :aria-selected="tab === t.k"
                        @click="tab = t.k"
                    >
                        <i class="bi" :class="t.ikon"></i>
                        {{ t.label }}
                        <span v-if="t.jml" class="ld-tabs__n">{{ t.jml }}</span>
                    </button>
                </div>

                <!-- ── ALUR SELEKSI · WATERFALL (gantt bertingkat) ── -->
                <div v-if="isMtCategory" v-show="tab === 'alur'" class="ld-card ld-wf">
                    <div class="ld-wf__head">
                        <div class="ld-sectitle">
                            <svg
                                width="18"
                                height="18"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="#6366f1"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            >
                                <path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01" />
                            </svg>
                            Alur Seleksi <span style="color: #8b5cf6; font-weight: 700">· Waterfall</span>
                        </div>
                        <div class="ld-wf__legend">
                            <span><i style="background: #8b5cf6"></i> Selesai</span>
                            <span><i style="background: #f59e0b"></i> Berlangsung</span>
                            <span><i style="background: #cbd5e1"></i> Menunggu</span>
                        </div>
                    </div>
                    <div class="ld-wf__body">
                        <!-- Sumbu tanggal (ruler) -->
                        <div class="ld-wf__axis">
                            <div class="ld-wf__axisside">Tahap</div>
                            <div class="ld-wf__axistrack">
                                <span
                                    v-for="(tk, i) in gantt.ticks"
                                    :key="i"
                                    class="ld-wf__tick"
                                    :class="{ 'is-last': i === gantt.ticks.length - 1 }"
                                    :style="{ left: tk.left + '%' }"
                                    >{{ tk.label }}</span
                                >
                            </div>
                        </div>

                        <div v-for="(w, i) in gantt.rows" :key="i" class="ld-wf__row">
                            <div class="ld-wf__side">
                                <span class="ld-wf__node" :class="'is-' + w.state">
                                    <svg
                                        v-if="w.state === 'done'"
                                        width="14"
                                        height="14"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="3"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                    >
                                        <path d="M20 6L9 17l-5-5" />
                                    </svg>
                                    <svg
                                        v-else-if="w.state === 'fail'"
                                        width="13"
                                        height="13"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="2.6"
                                        stroke-linecap="round"
                                    >
                                        <path d="M18 6L6 18M6 6l12 12" />
                                    </svg>
                                    <template v-else>{{ w.urutan }}</template>
                                </span>
                                <div style="min-width: 0">
                                    <div
                                        class="ld-wf__name"
                                        :style="{ color: w.state === 'todo' ? '#94a3b8' : '#1e293b' }"
                                    >
                                        {{ w.name }}
                                    </div>
                                    <div class="ld-wf__st" :style="{ color: w.stColor }">{{ w.statusLabel }}</div>
                                    <div class="ld-wf__date" :class="{ 'is-est': w.est }">
                                        <svg
                                            width="12"
                                            height="12"
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            stroke-width="2"
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                        >
                                            <rect x="3" y="4" width="18" height="18" rx="2" />
                                            <path d="M3 10h18M8 2v4M16 2v4" />
                                        </svg>
                                        {{ w.dateText }}
                                    </div>
                                </div>
                            </div>
                            <div class="ld-wf__track">
                                <span
                                    v-for="(tk, gi) in gantt.ticks"
                                    :key="gi"
                                    class="ld-wf__grid"
                                    :style="{ left: tk.left + '%' }"
                                ></span>
                                <span
                                    class="ld-wf__bar"
                                    :class="{ 'is-glow': w.glow, 'is-empty': w.state === 'todo' }"
                                    :style="{ marginLeft: w.left + '%', width: w.width + '%', background: w.bg }"
                                >
                                    <span class="ld-wf__barlabel">{{ w.barLabel }}</span>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ── Data kandidat (dari formulir pendaftaran) ──
                         Diambil dari jawaban yang SUDAH kandidat kirim, bukan
                         disalin ulang: foto, kampus, tanggal lahir, dan sisanya
                         mengikuti urutan pertanyaan di skema formulirnya. -->
                <div v-if="profilKandidat" v-show="tab === 'berkas'" class="ld-card ld-profil">
                    <div class="ld-profil__head">
                        <div class="ld-profil__foto">
                            <img
                                v-if="profilKandidat.foto"
                                :src="profilKandidat.foto.url"
                                :alt="profilKandidat.nama"
                                @click="previewBerkas(profilKandidat.foto)"
                            />
                            <span v-else>{{ inisialNama }}</span>
                        </div>
                        <div style="min-width: 0">
                            <div class="ld-eyebrow">DATA KANDIDAT</div>
                            <div class="ld-profil__nama">{{ profilKandidat.nama || '—' }}</div>
                            <div class="ld-profil__sub">Dikirim {{ fmtWaktu(profilKandidat.waktuKirim) }}</div>
                        </div>
                    </div>
                    <div class="ld-profil__grid">
                        <div v-for="d in profilKandidat.data" :key="d.key" class="ld-profil__item">
                            <span class="ld-profil__lbl">{{ d.label }}</span>
                            <span class="ld-profil__val">{{ d.nilai }}</span>
                        </div>
                    </div>
                </div>

                <!-- ── Formulir & Berkas Saya ── -->
                <div v-if="formulir.length" v-show="tab === 'berkas'">
                    <div class="ld-secrow">
                        <div class="ld-sectitle">
                            <svg
                                width="18"
                                height="18"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="#6366f1"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            >
                                <path d="M4 7a2 2 0 0 1 2-2h4l2 2h6a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H4z" />
                            </svg>
                            Formulir &amp; Berkas Saya
                        </div>
                        <span class="ld-seccount">{{ formulir.length }} formulir</span>
                    </div>
                    <div class="ld-forms">
                        <div
                            v-for="(f, fi) in formulir"
                            :key="f.no"
                            class="ld-form"
                            :class="{ 'is-open': openForm === fi }"
                        >
                            <button type="button" class="ld-form__head" @click="openForm = openForm === fi ? -1 : fi">
                                <span class="ld-form__step" :class="{ 'is-ok': !!f.waktuKirim }">{{
                                    String(fi + 1).padStart(2, '0')
                                }}</span>
                                <span style="flex: 1; min-width: 0">
                                    <span class="ld-form__title">{{ f.label }}</span>
                                    <span class="ld-form__sub"
                                        >{{
                                            f.sumber === 'PENDAFTARAN' ? 'Pendaftaran' : 'Tahap ' + (f.urutan || '—')
                                        }}
                                        · {{ f.jawaban.length }} isian<template v-if="f.berkas.length">
                                            · {{ f.berkas.length }} berkas</template
                                        ></span
                                    >
                                </span>
                                <span class="ld-form__pill" :class="statusFormulir(f).kelas">{{
                                    statusFormulir(f).teks
                                }}</span>
                                <svg
                                    class="ld-form__chev"
                                    width="18"
                                    height="18"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="#94a3b8"
                                    stroke-width="2.4"
                                    stroke-linecap="round"
                                >
                                    <path d="M6 9l6 6 6-6" />
                                </svg>
                            </button>
                            <div v-if="openForm === fi" class="ld-form__body">
                                <div v-if="f.jawaban.length" class="ld-fields">
                                    <div
                                        v-for="j in f.jawaban"
                                        :key="j.key"
                                        class="ld-field"
                                        :class="{ 'is-panjang': isianPanjang(j) }"
                                    >
                                        <div class="ld-field__k">{{ labelIsian(j) }}</div>
                                        <!-- ISIAN BERUPA BERKAS = LENCANA, bukan nama file +
                                                 tombol Lihat. Berkasnya sendiri — berikut ukuran,
                                                 status verifikasi, dan pratinjau — sudah ada di
                                                 "Dokumen & Verifikasi" tepat di bawah. Di daftar
                                                 isian yang perlu dijawab cuma satu: ADA atau TIDAK. -->
                                        <div v-if="isianBerkas(j)" class="ld-field__v">
                                            <span class="ld-badge" :class="j.berkas ? 'is-ada' : 'is-kosong'">
                                                <i
                                                    class="bi"
                                                    :class="j.berkas ? 'bi-check-circle-fill' : 'bi-dash-circle'"
                                                ></i>
                                                {{ j.berkas ? 'Terlampir' : 'Belum ada' }}
                                            </span>
                                        </div>
                                        <!-- ISIAN BERULANG (riwayat kerja, organisasi,
                                             sertifikasi) — daftar, bukan satu paragraf.
                                             Digabung jadi satu baris teks, isinya jadi
                                             dinding kata yang tak bisa dibaca siapa pun. -->
                                        <div v-else-if="j.baris && j.baris.length" class="ld-field__v ld-rows">
                                            <div v-for="(row, ri) in j.baris" :key="ri" class="ld-row">
                                                <span v-if="j.baris.length > 1" class="ld-row__no">{{ ri + 1 }}</span>
                                                <div class="ld-row__isi">
                                                    <span v-for="(p, pi) in row" :key="pi" class="ld-row__p">
                                                        <b v-if="p.label">{{ p.label }}</b>{{ p.nilai }}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                        <div v-else class="ld-field__v">{{ j.nilai || '—' }}</div>
                                    </div>
                                </div>
                                <div v-if="f.berkas.length" class="ld-docs">
                                    <div class="ld-docs__label">DOKUMEN &amp; VERIFIKASI</div>
                                    <div v-for="b in f.berkas" :key="b.field" class="ld-doc">
                                        <span class="ld-doc__ico" :class="b.isImage ? 'is-img' : 'is-pdf'">
                                            <svg
                                                v-if="b.isImage"
                                                width="20"
                                                height="20"
                                                viewBox="0 0 24 24"
                                                fill="none"
                                                stroke="#6366f1"
                                                stroke-width="2"
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                            >
                                                <rect x="3" y="3" width="18" height="18" rx="2" />
                                                <circle cx="8.5" cy="8.5" r="1.5" />
                                                <path d="M21 15l-5-5L5 21" />
                                            </svg>
                                            <svg
                                                v-else
                                                width="20"
                                                height="20"
                                                viewBox="0 0 24 24"
                                                fill="none"
                                                stroke="#ef4444"
                                                stroke-width="2"
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                            >
                                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                                                <path d="M14 2v6h6" />
                                            </svg>
                                        </span>
                                        <div style="flex: 1; min-width: 0">
                                            <div class="ld-doc__toprow">
                                                <span class="ld-doc__name">{{ b.nama }}</span>
                                                <span class="ld-doc__ext">{{ (b.ext || '').toUpperCase() }}</span>
                                            </div>
                                            <div class="ld-doc__desc">
                                                {{
                                                    b.field === 'foto_verifikasi'
                                                        ? 'Foto verifikasi identitas yang kamu unggah.'
                                                        : 'Berkas ' +
                                                          b.field.replace(/[_-]/g, ' ') +
                                                          ' · ' +
                                                          ukuran(b.ukuran)
                                                }}
                                            </div>
                                        </div>
                                        <button
                                            type="button"
                                            class="ld-doc__eye"
                                            title="Lihat berkas"
                                            @click="bukaDok(b)"
                                        >
                                            <svg
                                                width="18"
                                                height="18"
                                                viewBox="0 0 24 24"
                                                fill="none"
                                                stroke="currentColor"
                                                stroke-width="2"
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                            >
                                                <path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z" />
                                                <circle cx="12" cy="12" r="3" />
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div style="height: 8px"></div>
        </div>

        <!-- LIGHTBOX -->
        <div class="ld-lb" :class="{ 'is-on': !!lightbox }" @click="lightbox = null">
            <div v-if="lightbox" :class="['ld-lb__wrap', { 'is-pdf': lightbox.pdf }]" @click.stop>
                <div class="ld-lb__bar">
                    <div style="display: flex; align-items: center; gap: 11px; color: #fff; min-width: 0">
                        <span class="ld-lb__ico"
                            ><svg
                                width="20"
                                height="20"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="#fff"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            >
                                <rect x="3" y="3" width="18" height="18" rx="2" />
                                <circle cx="8.5" cy="8.5" r="1.5" />
                                <path d="M21 15l-5-5L5 21" /></svg
                        ></span>
                        <div style="min-width: 0">
                            <div class="ld-lb__name">{{ lightbox.nama }}</div>
                            <div class="ld-lb__desc">
                                {{
                                    lightbox.field === 'foto_verifikasi'
                                        ? 'Foto verifikasi identitas'
                                        : 'Pratinjau berkas'
                                }}
                            </div>
                        </div>
                    </div>
                    <button type="button" class="ld-lb__close" @click="lightbox = null">
                        <svg
                            width="20"
                            height="20"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2.4"
                            stroke-linecap="round"
                        >
                            <path d="M18 6L6 18M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <div class="ld-lb__card">
                    <div v-if="lbLoading" class="ld-lb__state"><span class="ld-spin"></span> Memuat berkas…</div>
                    <div v-else-if="lbError" class="ld-lb__state">
                        <svg
                            width="28"
                            height="28"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="#f87171"
                            stroke-width="2"
                            stroke-linecap="round"
                        >
                            <circle cx="12" cy="12" r="9" />
                            <path d="M12 8v5M12 16h.01" />
                        </svg>
                        Gagal memuat berkas.
                    </div>
                    <!-- PDF disematkan lewat iframe; gambar tetap pakai <img>.
                         Satu modal melayani keduanya supaya tidak ada dua gaya. -->
                    <iframe
                        v-if="lightbox.pdf"
                        v-show="!lbLoading && !lbError"
                        :src="lightbox.url"
                        :title="lightbox.nama"
                        class="ld-lb__pdf"
                        @load="selesaiMuat()"
                        @error="selesaiMuat(true)"
                    ></iframe>
                    <img
                        v-else
                        v-show="!lbLoading && !lbError"
                        :src="lightbox.url"
                        :alt="lightbox.nama"
                        @load="selesaiMuat()"
                        @error="selesaiMuat(true)"
                    />
                    <div class="ld-lb__foot">
                        <span style="font-size: 12px; color: #8b93a7">Berkas milik akunmu · pratinjau aman</span>
                        <span v-if="lightbox.status === 'TERVERIFIKASI'" class="ld-lb__ok"
                            ><svg
                                width="14"
                                height="14"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2.4"
                            >
                                <path d="M20 6L9 17l-5-5" />
                            </svg>
                            Terverifikasi</span
                        >
                    </div>
                </div>
            </div>
        </div>

        <!-- Dialog "Terima / Mundur" DICABUT bersama tombolnya. -->


        <transition name="ld-toast"
            ><div v-if="toast" class="ld-toast" :class="{ 'is-err': toastErr }">
                <i class="bi" :class="toastErr ? 'bi-exclamation-circle-fill' : 'bi-check-circle-fill'"></i> {{ toast }}
            </div></transition
        >
    </div>
</template>

<script>
import axios from 'axios';
import { Head, Link, router } from '@inertiajs/vue3';
import { FORMULIR, komponenFormulir, skemaFormulir, jawabanAwal } from '@career/formulir';
import DynamicForm from '@career/formulir/DynamicForm.vue';
import JadwalKartu from '@career/JadwalKartu.vue';
import UnggahAktivitas from '@career/UnggahAktivitas.vue';

/**
 * Peta key -> label pertanyaan, dikumpulkan dari SKEMA seluruh formulir.
 *
 * Label diambil dari sumber aslinya (skema formulir), bukan ditulis ulang di
 * sini — jadi mengubah pertanyaan cukup di satu tempat. Key yang tidak
 * ditemukan (mis. jawaban dari versi formulir lama) memakai label turunan
 * dari server sebagai cadangan.
 */
const LABEL_FIELD = (() => {
    const peta = {};
    Object.values(FORMULIR).forEach((f) =>
        (f.skema?.langkah || []).forEach((L) =>
            (L.bagian || []).forEach((B) =>
                (B.field || []).forEach((x) => {
                    if (x.key && x.label) peta[x.key] = x.label;
                }),
            ),
        ),
    );
    return peta;
})();

// Sudah tampil di kepala kartu profil — tidak perlu diulang di daftar.
const PROFIL_SEMBUNYI = ['nama', 'email'];

const CFG = { headers: { Accept: 'application/json' } };
const ST = {
    berjalan: { c: '#b45309', bg: 'rgba(245,158,11,.14)', dot: '#f59e0b' },
    lolos: { c: '#059669', bg: 'rgba(16,185,129,.12)', dot: '#10b981' },
    gugur: { c: '#dc2626', bg: 'rgba(239,68,68,.1)', dot: '#ef4444' },
    // Disimpan untuk kesempatan berikutnya — belum berakhir, jadi bukan merah.
    menunggu: { c: '#4f46e5', bg: 'rgba(99,102,241,.12)', dot: '#6366f1' },
    // KEPUTUSAN DARI KANDIDAT (mengundurkan diri, menolak penawaran).
    // Sengaja abu-abu: prosesnya memang berhenti, tetapi bukan karena ia
    // ditolak. Mewarnainya merah seperti "Tidak Lolos" mengatakan hal yang
    // salah kepada orang yang justru memilih pergi sendiri.
    netral: { c: '#475569', bg: 'rgba(100,116,139,.12)', dot: '#64748b' },
};
// Ikon fakta cepat (stroke) — dipakai kartu Detail Lowongan.
const SVG = (path) =>
    `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#8b5cf6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">${path}</svg>`;
const P = {
    koper: '<rect x="3" y="7" width="18" height="13" rx="2"/><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>',
    build: '<path d="M3 21h18M6 21V7l6-4 6 4v14"/><path d="M9 9h.01M15 9h.01M9 13h.01M15 13h.01"/>',
    pin: '<path d="M12 21s-7-6-7-11a7 7 0 0 1 14 0c0 5-7 11-7 11z"/><circle cx="12" cy="10" r="2.5"/>',
    jam: '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
    level: '<path d="M4 20h4V10H4zM10 20h4V4h-4zM16 20h4v-8h-4z"/>',
    topi: '<path d="M22 10L12 5 2 10l10 5 10-5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/>',
    doc: '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/>',
};

// Kalimat "sedang menunggu apa" TIDAK ditulis di sini. Setiap tipe aktivitas
// membawa Pesan_Kandidat-nya sendiri dari Master Tipe Tahap, jadi menambah tipe
// baru (mis. "Tes Praktik Lapangan") cukup lewat master tanpa menyentuh file
// ini. Satu kalimat cadangan di bawah hanya dipakai bila master belum diisi.
const PESAN_UMUM =
    'Tidak ada yang perlu kamu kerjakan sekarang — tim rekrutmen akan mengabarimu lewat email dan halaman ini.';

export default {
    components: { Head, Link, JadwalKartu, DynamicForm, UnggahAktivitas },
    props: {
        lamaran: { type: Object, default: () => ({}) },
        tahap: { type: Array, default: () => [] },
        tugas: { type: Object, default: null },
        formulir: { type: Array, default: () => [] },
        lowonganUrl: { type: String, default: null },
        kartu: { type: Object, default: null },
        profil: { type: Object, default: () => ({}) },
        konteks: { type: Object, default: () => ({}) },
    },
    data() {
        return {
            ST,
            jawaban: {},
            berkas: {},
            // Langkah wizard yang dipulihkan dari draf server. Dipakai sebagai
            // nilai AWAL komponen formulir, bukan diikat dua arah — mengikatnya
            // membuat wizard melompat setiap draf tersimpan.
            langkahAwal: 0,
            drafSiap: false,
            berkasDraf: {},
            mengirim: false,
            openForm: 0,
            lightbox: null,
            lbTimer: null,
            berkasTes: {},
            unggahDi: null,
            // Aktivitas yang tombol kirimnya sedang diproses.
            kirimDi: null,
            tab: 'alur',
            // Layar antara menuju ruang ujian (tab yang sama, jeda 3 detik).
            menujuUjian: false,
            hitungPindah: 3,
            timerPindah: null,
            // ── PULANG DARI RUANG UJIAN ──
            // { id, at } aktivitas yang barusan dikerjakan, dibaca kembali dari
            // sessionStorage saat kandidat diantar pulang oleh CAT.
            pulangTes: null,
            pulangCek: 0,
            pulangTimer: null,
            lbLoading: false,
            lbError: false,
            toast: '',
            toastErr: false,
            tm: null,
            now: Date.now(),
            jam: null,
            // [feat/feedback]
            showFeedbackBanner: true,
        };
    },
    computed: {
        isMtCategory() {
            const cat = (this.lamaran?.kategori || this.lamaran?.Kategori || this.lamaran?.kategori_program || '')
                .toString()
                .toUpperCase();
            return cat === 'MT';
        },
        // [feat/feedback]
        feedbackPending() {
            return this.$page.props.feedbackPending;
        },
        komponen() {
            return this.tugas ? komponenFormulir(this.tugas.komponen) : null;
        },
        skemaTugas() {
            return this.tugas?.schema || skemaFormulir(this.tugas?.komponen);
        },
        totalTahap() {
            return this.lamaran.totalTahap || this.tahap.length || 0;
        },
        /**
         * Nada status DARI SERVER (turunan Master Hasil Keputusan).
         *
         * Peta literal sebelumnya hanya mengenal tiga status, sehingga lamaran
         * ber-status MENGUNDURKAN_DIRI jatuh ke cadangan 'berjalan' — halaman
         * menampilkan proses yang masih berlangsung untuk lamaran yang sudah
         * ditutup kandidatnya sendiri.
         */
        stKey() {
            return ST[this.lamaran.statusNada] ? this.lamaran.statusNada : 'berjalan';
        },
        /** Status yang SUDAH final — dipakai menghentikan animasi "sedang jalan". */
        lamaranBerhenti() {
            return !!this.lamaran.status && this.lamaran.status !== 'BERJALAN';
        },
        katChipStyle() {
            return this.lamaran.kategori === 'MT'
                ? { background: 'rgba(245,158,11,.14)', color: '#b45309' }
                : this.lamaran.kategori === 'INTERNSHIP'
                  ? { background: 'rgba(16,185,129,.12)', color: '#059669' }
                  : { background: 'rgba(99,102,241,.12)', color: '#4f46e5' };
        },
        stChipStyle() {
            const t = ST[this.stKey];
            return { background: t.bg, color: t.c };
        },
        posisiSekarang() {
            const aktif = this.tahap.find((t) => t.status === 'BERJALAN');
            if (aktif) return aktif.urutan;
            const selesai = this.tahap.filter((t) => t.status === 'SELESAI').length;
            return Math.min(selesai + 1, this.totalTahap) || this.lamaran.urutanTahap || 1;
        },
        persen() {
            if (!this.totalTahap) return 0;
            const selesai = this.tahap.filter((t) => t.status === 'SELESAI' && t.hasil !== 'GUGUR').length;
            return Math.round((selesai / this.totalTahap) * 100);
        },
        tahapAktif() {
            return this.tahap.find((t) => t.status === 'BERJALAN') || null;
        },
        // Seluruh aktivitas tahap aktif, apa pun tipenya.
        aktivitas() {
            return this.tahapAktif?.tes || [];
        },
        // Aktivitas ujian online saja — hanya inilah yang punya token & jendela
        // waktu. Wawancara/tes manual di tahap yang sama TIDAK ikut ke sini.
        tesList() {
            return this.aktivitas.filter((x) => x.eksternal);
        },
        /** Ujian online yang masih menuntut sesuatu dari kandidat. */
        tesBelumTuntas() {
            return this.tesList.filter((x) => !x.selesai && !this.sudahSelesai(x));
        },
        // Tahap ini menampilkan kartu ujian online bila ada aktivitas online di
        // dalamnya — bukan karena tipe tahapnya kebetulan "Tes Online".
        //
        // KARTUNYA MENGHILANG setelah seluruh ujiannya dikerjakan DAN masih ada
        // aktivitas lain yang menunggu kandidat. Tanpa itu, kartu "Tes sudah
        // kamu kerjakan" tetap terpampang di bawah kartu jadwal FGD — dua
        // aktivitas berbeda berdampingan di satu layar, dan kalimat tentang tes
        // yang sudah lewat terbaca seolah menerangkan FGD yang justru sedang
        // ditunggu. Kandidat lalu ragu apakah FGD-nya masih perlu dihadiri.
        //
        // Bila TIDAK ada aktivitas lain yang menunggu, kartunya dipertahankan:
        // di situ "sudah dikerjakan, hasilnya sedang ditinjau" memang satu-
        // satunya kabar yang ia punya.
        tahapTes() {
            if (! this.tesList.length) return null;
            if (! this.tesBelumTuntas.length && this.aktivitasManual.length) return null;

            return this.tahapAktif;
        },
        // Aktivitas non-online yang masih ditunggu (wawancara, tes manual, MCU).
        aktivitasManual() {
            return this.aktivitas.filter((x) => !x.eksternal && !x.selesai);
        },
        // Sesi ujian yang sedang relevan: yang bisa dikerjakan → yang sudah
        // terjadwal → yang pertama menunggu jadwal.
        sesi() {
            const t = this.tahapTes;
            if (!t) return { label: '', ujian: null };
            // Yang SUDAH dikerjakan tidak boleh merebut kartu ini. Ujian
            // ber-peran informatif menunggu keputusan penilai — jadi belum
            // `selesai`, padahal kandidat tak punya urusan lagi dengannya.
            // Dulu ia tetap terpilih dan menutupi tes berikutnya yang justru
            // menunggu dikerjakan.
            const belum = this.tesBelumTuntas.length
                ? this.tesBelumTuntas
                : this.tesList.filter((x) => !x.selesai);
            const pilih =
                belum.find((x) => x.ujian?.bisaAkses) || belum.find((x) => x.ujian) || belum[0] || this.tesList[0];
            if (pilih) return { label: pilih.label || t.label, ujian: pilih.ujian || t.ujian };
            return { label: t.label, ujian: t.ujian };
        },
        /**
         * KEADAAN TAHAP dalam satu kalimat — pengganti daftar aktivitas.
         *
         * Yang dijawab hanya dua hal yang memang milik kandidat: apakah ada
         * yang harus ia kerjakan sekarang, dan kalau tidak, ia sedang menunggu
         * apa. Nama-nama asesmen yang belum dilalui SENGAJA tidak disebut —
         * membeberkannya kepada orang yang sedang dinilai memberinya waktu
         * mempersiapkan alat ukurnya, dan itu merusak nilai ukurnya sendiri.
         *
         * Urutannya dari yang paling menuntut tindakan ke yang paling pasif;
         * yang pertama cocok yang dipakai, supaya kandidat tidak pernah membaca
         * "sedang diproses" padahal ada tes yang menunggu dikerjakannya.
         */
        /**
         * Aktivitas yang jawabannya SUDAH dikirim tapi hasilnya belum sampai.
         *
         * Null berarti tidak ada yang ditunggu — entah karena kandidat memang
         * tidak baru pulang dari ujian, atau karena hasilnya sudah masuk.
         */
        tesDitunggu() {
            if (!this.pulangTes) return null;

            const t = (this.aktivitas || []).find((x) => x.id === this.pulangTes.id);

            // Aktivitasnya tak lagi ada di tahap aktif = tahapnya sudah bergerak.
            // Itu kabar yang lebih baru daripada jejak kita, jadi jejaknya kalah.
            if (!t) return null;

            return this.sudahSelesai(t) || t.selesai ? null : t;
        },
        keadaanTahap() {
            const akt = this.aktivitas || [];
            const belum = akt.filter((x) => !x.selesai);

            // BARU PULANG DARI RUANG UJIAN — didahulukan dari keadaan mana pun.
            // Selama hasilnya belum sampai, keadaan lain masih membaca tes itu
            // sebagai "bisa dikerjakan sekarang", dan kandidat yang baru saja
            // menekan kirim akan disambut ajakan mengerjakannya lagi.
            if (this.tesDitunggu) {
                return {
                    nada: 'kirim',
                    ikon: 'bi-send-check-fill',
                    judul: 'Jawaban tesmu sudah terkirim',
                    pesan: 'Terima kasih sudah menyelesaikan tes. Hasilnya sedang diterima sistem — halaman ini memperbarui dirinya sendiri, jadi tidak perlu kamu muat ulang.',
                };
            }

            if (!belum.length) {
                return {
                    nada: 'proses',
                    ikon: 'bi-hourglass-split',
                    judul: 'Seluruh rangkaian tahap ini sudah kamu selesaikan',
                    pesan: 'Hasilnya sedang ditinjau tim rekrutmen. Keputusannya muncul di halaman ini begitu terbit, dan kamu juga diberi tahu lewat email.',
                };
            }

            // Ada yang bisa dikerjakan SEKARANG (tes online terbuka).
            if (belum.some((x) => x.ujian?.bisaAkses && !x.terkunci)) {
                return {
                    nada: 'aksi',
                    ikon: 'bi-play-circle-fill',
                    judul: 'Ada aktivitas yang bisa kamu kerjakan sekarang',
                    pesan: 'Ikuti petunjuk pada kartu di bawah. Pastikan koneksi stabil sebelum memulai.',
                };
            }

            // DITAHAN TIM. Rangkaian berikutnya sengaja belum dibuka karena
            // hasil sebelumnya sedang ditinjau. Ini keadaan yang paling mudah
            // disalahpahami kandidat sebagai "sistemnya macet", jadi ia
            // didahulukan dan disebut apa adanya.
            if (belum.every((x) => x.terkunci) && belum.length) {
                return {
                    nada: 'tinjau',
                    ikon: 'bi-shield-lock-fill',
                    judul: 'Hasil kamu sedang ditinjau',
                    pesan: 'Rangkaian berikutnya dibuka setelah tim rekrutmen selesai meninjau bagian yang sudah kamu jalani. Tidak ada yang perlu kamu lakukan sekarang.',
                };
            }

            // Sudah ada undangan bertanggal — itu yang paling perlu ia tahu.
            const terjadwal = belum.find((x) => x.jadwal);
            if (terjadwal) {
                return {
                    nada: 'jadwal',
                    ikon: 'bi-calendar-check-fill',
                    judul: 'Kamu sudah dijadwalkan',
                    pesan: 'Rincian waktu dan tempatnya ada di bawah. Undangan yang sama juga dikirim ke emailmu — mohon hadir tepat waktu.',
                };
            }

            return {
                nada: 'tunggu',
                ikon: 'bi-clock-history',
                judul: 'Menunggu jadwal dari tim rekrutmen',
                pesan: 'Tahap ini masih berjalan dan belum ada yang perlu kamu kerjakan. Begitu jadwalnya ditetapkan, rinciannya muncul di halaman ini dan dikirim ke emailmu.',
            };
        },

        // Apa yang terjadi SETELAH tes dikerjakan. Sengaja TIDAK menyebut lulus
        // atau tidak: kapan hasil boleh dilihat kandidat diatur Mode Pengumuman
        // tahap, jadi membocorkannya di sini akan mendahului aturan itu.
        pesanSetelahTes() {
            const t = this.tahapTes;
            if (t?.otomatis) {
                return 'Hasilnya diproses otomatis oleh sistem. Begitu keputusan tahap ini terbit, status di halaman ini langsung berubah — kamu juga diberi tahu lewat email.';
            }
            // Diperiksa dari SELURUH aktivitas tahap, bukan hanya `tesList` yang
            // berisi ujian online saja. Satu tahap bisa memuat psikotes online +
            // tes manual + wawancara; kalau hanya yang online dihitung, kandidat
            // yang baru selesai ujian diberi tahu "hasil sedang ditinjau" padahal
            // dua aktivitas lain belum dijalankan sama sekali.
            const sisa = this.aktivitas.filter((x) => !x.selesai);
            if (sisa.length) {
                // NAMA AKTIVITAS YANG TERSISA TIDAK DISEBUT.
                //
                // Sebelumnya kalimat ini berbunyi "masih menunggu: DISC, FGD,
                // Wawancara" — membeberkan alat ukur yang belum dilalui kepada
                // orang yang sedang diukur. Yang berguna baginya bukan nama
                // asesmennya, melainkan bahwa tahapnya belum selesai dan tak ada
                // yang perlu ia kerjakan sekarang.
                return (
                    'Tahap ini masih berjalan. Tim rekrutmen akan menghubungi kamu bila ada yang perlu kamu ikuti berikutnya — ' +
                    'keputusan tahap baru diambil setelah seluruh rangkaiannya rampung.'
                );
            }

            return 'Hasilnya sudah masuk dan sedang ditinjau tim rekrutmen. Keputusan tahap ini akan muncul di halaman ini begitu terbit.';
        },
        // Kalimat untuk tahap yang ditangani tim — diambil dari aktivitas yang
        // sedang ditunggu, teksnya dari Master Tipe Tahap (bukan dari kode ini).
        pesanTahap() {
            const t = this.tahapAktif;
            if (!t) return { judul: '', sub: '', teks: '' };

            // Formulir tahap ini SUDAH dikirim -> berhenti memintanya lagi.
            // Kalimat "lengkapi formulir di bawah" pada kandidat yang baru saja
            // mengirim membuatnya mengira kiriman tadi tidak terbaca.
            if (t.sudahIsi) {
                return {
                    judul: 'Terima kasih — data kamu sudah lengkap',
                    sub: t.label,
                    teks: 'Formulir dan berkasmu sudah kami terima dan sedang diperiksa tim rekrutmen. Hasil tahap ini akan muncul di halaman ini begitu terbit.',
                };
            }
            const a = this.aktivitasManual[0] || null;
            const nama = a?.tipeNama || t.tipeNama;
            return {
                judul: nama ? `Menunggu ${nama.toLowerCase()}` : 'Sedang ditangani tim rekrutmen',
                sub: a && this.aktivitas.length > 1 ? a.label : nama || 'Proses seleksi',
                teks: a?.pesan || t.pesan || PESAN_UMUM,
            };
        },
        banner() {
            if (this.stKey === 'lolos')
                return {
                    title: 'Selamat, kamu diterima! 🎉',
                    text: 'Seluruh tahap seleksi telah kamu selesaikan. Tim rekrutmen akan menghubungimu.',
                };
            if (this.stKey === 'gugur')
                return {
                    title: 'Belum lolos pada tahap ini',
                    text:
                        this.lamaran.alasanGugur ||
                        'Terima kasih atas partisipasimu. Jangan menyerah — banyak peluang lain menantimu.',
                };

            const cur = this.tahapAktif;
            if (!cur)
                return { title: 'Lamaranmu sedang diproses', text: 'Pantau halaman ini untuk perkembangan seleksimu.' };

            const posisi = `Tahap ${cur.urutan} dari ${this.totalTahap}`;

            // Ada yang harus DIKERJAKAN kandidat → itu yang disebut lebih dulu.
            if (this.tugas && (this.komponen || this.tugas.schema)) {
                return {
                    title: `${posisi} · ${cur.label}`,
                    text: `Lengkapi ${this.tugas.formulirNama || 'formulir'} di bawah untuk melanjutkan ke tahap berikutnya.`,
                };
            }

            if (this.tahapTes) {
                const u = this.sesi.ujian;
                const nama = this.sesi.label || cur.label;
                if (!u || !u.terjadwal) {
                    const akt = this.tesList.find((x) => !x.selesai);
                    return {
                        title: `${posisi} · ${nama} — menunggu jadwal`,
                        text: akt?.pesan || cur.pesan || PESAN_UMUM,
                    };
                }
                if (u.statusPengerjaan === 'selesai') {
                    return { title: `${nama} sudah kamu kerjakan`, text: this.pesanSetelahTes };
                }
                if (u.belumMulai) {
                    const mundur = this.hitungMundur(this.sesi);
                    return {
                        title: `${nama} dijadwalkan ${this.fmtWaktu(u.waktuMulai)}`,
                        text: `Tesnya belum dibuka${mundur ? ` — ${mundur}` : ''}. Siapkan koneksi internet, kamera, dan ruangan yang tenang.`,
                    };
                }
                if (u.sudahLewat) {
                    return {
                        title: `Jendela ${nama} sudah lewat`,
                        text: 'Waktu pengerjaan berakhir. Hubungi tim rekrutmen bila kamu terkendala saat tes.',
                    };
                }
                return {
                    title: `${nama} bisa dikerjakan sekarang`,
                    text: `Tesnya terbuka sampai ${this.fmtWaktu(u.waktuSelesai)}. Tekan "Mulai Tes Sekarang" di bawah.`,
                };
            }

            return { title: `${posisi} · ${cur.label}`, text: this.pesanTahap.teks };
        },
        /** Tahap terakhir yang keputusannya SUDAH boleh dilihat kandidat. */
        putusanTahap() {
            const sudah = this.tahap.filter((t) => t.hasilTampil && t.hasil);
            const t = sudah.length ? sudah[sudah.length - 1] : null;
            if (!t) return null;

            // Sudah mengerjakan tahap SESUDAHNYA -> keputusan lama tidak relevan
            // lagi dan hanya menutupi keadaan terkini. Kandidat tetap bisa
            // melihatnya di timeline Alur Seleksi.
            const berikut = this.tahap.find((x) => x.urutan === t.urutan + 1);
            if (berikut && (berikut.sudahIsi || berikut.status === 'SELESAI')) return null;

            const lolos = t.hasil === 'LULUS';
            const lanjut = this.tahap.find((x) => x.urutan === t.urutan + 1) || null;

            let teks;
            if (!lolos) {
                teks =
                    this.lamaran.alasanGugur ||
                    'Terima kasih sudah mengikuti tahap ini. Kamu tetap bisa melamar lowongan lain di EVO Group.';
            } else if (lanjut) {
                teks = `Selamat, kamu lanjut ke tahap ${lanjut.urutan} — ${lanjut.label}.`;
            } else {
                teks = 'Selamat, kamu menyelesaikan seluruh tahap seleksi.';
            }
            if (t.diputusAt) teks += ` Diputuskan ${this.fmtWaktu(t.diputusAt)}.`;

            return { urutan: t.urutan, label: t.label, lolos, teks };
        },
        /** Sudah diputus tapi hasilnya belum boleh diumumkan. */
        tahapTertunda() {
            const tunda = this.tahap.filter((t) => t.menungguPengumuman);
            return tunda.length ? tunda[tunda.length - 1] : null;
        },
        inisialNama() {
            const n = (this.profilKandidat?.nama || '').trim();
            return n ? n.charAt(0).toUpperCase() : '?';
        },
        /** Ringkasan data kandidat dari formulir pendaftaran yang sudah dikirim. */
        profilKandidat() {
            const f = this.formulir.find((x) => x.sumber === 'PENDAFTARAN') || this.formulir[0];
            if (!f) return null;

            const gambar = (f.berkas || []).filter((b) => b.isImage);
            const foto = gambar.find((b) => /foto/i.test(b.field || '')) || gambar[0] || null;

            const data = (f.jawaban || [])
                .filter((j) => !PROFIL_SEMBUNYI.includes(j.key) && j.nilai !== null && j.nilai !== '')
                .map((j) => ({ ...j, label: LABEL_FIELD[j.key] || j.label }));

            const nama = (f.jawaban || []).find((j) => j.key === 'nama')?.nilai || '';

            return { foto, nama, data, waktuKirim: f.waktuKirim };
        },
        /** Konteks formulir + daftar berkas draf yang sudah tersimpan di server. */
        konteksForm() {
            return { ...(this.konteks || {}), berkasDraf: this.berkasDraf };
        },
        /** Tab isi bawah — hanya yang memang ada isinya yang ditampilkan. */
        tabs() {
            const out = [];
            if (this.isMtCategory) out.push({ k: 'alur', label: 'Alur Seleksi', ikon: 'bi-list-task' });
            // TAB "DETAIL LOWONGAN" DIHAPUS.
            //
            // Halaman ini menjawab "bagaimana lamaranku berjalan". Rincian
            // lowongannya — deskripsi, kualifikasi, benefit — sudah dibaca
            // kandidat SEBELUM ia melamar, dan masih terbuka di halaman
            // lowongannya sendiri. Menyalinnya ke sini membuat halaman proses
            // dibuka untuk membaca ulang iklan, sementara yang benar-benar
            // ditunggu (jadwal, tes, keputusan) terdorong ke bawah.
            if (this.formulir.length) {
                out.push({
                    k: 'berkas',
                    label: 'Formulir & Berkas',
                    ikon: 'bi-folder-fill',
                    jml: this.formulir.length,
                });
            }
            // TAB "HASIL TAHAPAN" DIHAPUS.
            //
            // Isinya menggandakan apa yang sudah terbaca di halaman ini: hasil
            // tiap tahap tampil di kartu keputusan paling atas dan di timeline
            // progres, lengkap dengan status dan tanggalnya. Tab tersendiri
            // membuat kandidat mengira ada keterangan LAIN yang belum ia baca,
            // lalu membukanya dan menemukan hal yang sama — hanya dengan tata
            // letak berbeda.
            //
            // `catatanTahap` sengaja DIPERTAHANKAN: dipakai bagian lain dan
            // menghapusnya hanya menambah perubahan tanpa manfaat.

            return out;
        },
        /**
         * Catatan keputusan per tahap yang boleh dibaca kandidat.
         *
         * NILAI tidak ikut — kandidat cukup tahu keterangannya, bukan angkanya.
         * Hanya tahap yang hasilnya sudah boleh diumumkan yang masuk, supaya
         * aturan Mode Pengumuman tetap dihormati di sini.
         *
         * CATATAN PER AKTIVITAS DIHAPUS DARI SINI. Yang ditulis tim di sana
         * adalah penilaian internal ("gugup, tidak direkomendasikan"), dan sejak
         * catatannya bisa memuat lembar penilaian terpindai, menampilkannya
         * berarti menyerahkan rapor internal ke kandidat. Server pun tidak lagi
         * mengirimkannya — lihat LamaranController::portalDetail().
         */
        catatanTahap() {
            return this.tahap
                .filter((t) => t.hasilTampil && (t.catatan || (t.berkas || []).length))
                .map((t) => ({
                    urutan: t.urutan,
                    label: t.label,
                    hasil: t.hasil,
                    catatan: t.catatan,
                    at: t.diputusAt,
                    berkas: t.berkas || [],
                }));
        },
        /** Aktivitas tahap aktif yang SUDAH punya jadwal tatap muka. */
        jadwalAktivitas() {
            return this.aktivitas
                .filter((x) => x.jadwal && !x.selesai)
                .map((x) => ({ key: x.urutan, label: x.label, ...x.jadwal }));
        },
        /** Hasil MCU aktivitas tahap aktif yang sudah terbit. */
        hasilMcu() {
            return this.aktivitas.filter((x) => x.mcu).map((x) => ({ key: x.urutan, ...x.mcu }));
        },
        /** Aktivitas tahap aktif yang meminta kandidat mengunggah berkas. */
        unggahAktivitas() {
            return this.aktivitas
                .filter((x) => x.unggah && !x.selesai)
                .map((x) => ({ key: x.urutan, id: x.id, label: x.label, ...x.unggah }));
        },
        k() {
            return this.kartu || {};
        },
        ringkasan() {
            return this.k.ringkasan || this.k.deskripsi || '';
        },
        tanggungJawab() {
            return this.k.tanggungJawab || [];
        },
        persyaratan() {
            return this.k.persyaratan || [];
        },
        skill() {
            return this.k.skill || [];
        },
        benefit() {
            return [...(this.k.benefit || []), ...(this.k.fasilitas || [])];
        },
        // Fakta cepat — adaptif MT vs rekrutmen.
        facts() {
            const k = this.k;
            const out = [];
            const push = (t, v, icon) => {
                if (v) out.push({ t, v, icon: SVG(icon) });
            };
            if (this.lamaran.kategori === 'MT') {
                push('Jenis', k.tipeKegiatan || 'Management Trainee', P.topi);
                push('Penempatan', k.penempatan || this.lamaran.lokasi, P.pin);
                push('Durasi', k.durasi, P.jam);
                push('Ikatan', k.ikatan, P.doc);
            } else {
                push('Status Kerja', k.tipeKerja || 'Full-time', P.koper);
                push('Tempat Kerja', k.tempatKerja || 'On-site', P.build);
                push('Lokasi', k.lokasi || this.lamaran.lokasi, P.pin);
                push('Level', k.level, P.level);
                push('Pengalaman', k.pengalaman, P.jam);
            }
            return out;
        },
        heroSteps() {
            const src = this.tahap.length
                ? this.tahap
                : Array.from({ length: this.totalTahap }, (_, i) => ({
                      urutan: i + 1,
                      label: `Tahap ${i + 1}`,
                      status: 'MENUNGGU',
                  }));
            return src.map((t) => {
                // Tahap yang masih BERJALAN pada lamaran yang sudah BERHENTI
                // bukan lagi tahap "sedang berlangsung". Dulu hanya status
                // GUGUR yang dihitung, sehingga kandidat yang mengundurkan diri
                // tetap melihat tahapnya berkedip seolah prosesnya jalan terus.
                const st =
                    t.status === 'SELESAI'
                        ? t.hasil === 'GUGUR'
                            ? 'fail'
                            : 'done'
                        : t.status === 'BERJALAN'
                          ? this.lamaranBerhenti
                              ? this.stKey === 'gugur'
                                  ? 'fail'
                                  : 'todo'
                              : 'current'
                          : 'todo';
                return {
                    name: t.label,
                    st,
                    line: st === 'done' || st === 'current' ? '#a5b4fc' : st === 'fail' ? '#fca5a5' : '#e2e8f0',
                    lbl: st === 'todo' ? '#94a3b8' : st === 'fail' ? '#dc2626' : '#334155',
                };
            });
        },
        // GANTT alur seleksi berbasis TANGGAL NYATA + estimasi cascade untuk tahap
        // yang belum terjadwal. Menghasilkan baris (batang proporsional ke tanggal)
        // + ticks (sumbu tanggal). Anchor: tanggal melamar.
        gantt() {
            const DAY = 86400000,
                DEF = 3,
                GAP = 1;
            const parse = (v) => {
                if (!v) return null;
                const d = new Date(String(v).replace(' ', 'T'));
                return Number.isNaN(d.getTime()) ? null : d.getTime();
            };
            const applyMs = parse(this.lamaran.waktuLamar) || Date.now();
            const bgOf = (s) =>
                s === 'done'
                    ? 'linear-gradient(90deg,#8b5cf6,#6366f1)'
                    : s === 'current'
                      ? 'linear-gradient(90deg,#fbbf24,#f59e0b)'
                      : s === 'fail'
                        ? 'linear-gradient(90deg,#f87171,#ef4444)'
                        : '#dfe4ee';
            const stColorOf = (s) =>
                s === 'done' ? '#6366f1' : s === 'current' ? '#b45309' : s === 'fail' ? '#dc2626' : '#94a3b8';

            let cursor = applyMs;
            const rows = this.tahap.map((t) => {
                const state = this.tlState(t);
                const rg = this.tglTahap(t);
                let start = parse(rg.start),
                    end = parse(rg.end),
                    est = false;
                if (start === null && end === null) {
                    est = true;
                    start = cursor;
                    end = start + DEF * DAY;
                } else if (start === null) {
                    start = end - DEF * DAY;
                } else if (end === null) {
                    end = start + DEF * DAY;
                }
                if (est && start < cursor) {
                    start = cursor;
                    end = start + DEF * DAY;
                }
                cursor = Math.max(cursor, end) + GAP * DAY;
                return { urutan: t.urutan, name: t.label, statusLabel: this.stepStatus(t), state, start, end, est };
            });
            if (!rows.length) return { rows: [], ticks: [] };

            const min = Math.min(...rows.map((r) => r.start));
            const max = Math.max(...rows.map((r) => r.end));
            const span = Math.max(DAY, max - min);
            rows.forEach((r) => {
                r.left = +(((r.start - min) / span) * 100).toFixed(2);
                r.width = Math.max(6, +(((r.end - r.start) / span) * 100).toFixed(2));
                r.bg = bgOf(r.state);
                r.stColor = stColorOf(r.state);
                r.glow = r.state === 'current';
                r.dateText = (r.est ? '± ' : '') + this.fmtRangeMs(r.start, r.end);
                r.barLabel = this.fmtMs(r.end, true);
            });

            const N = 4;
            const ticks = [];
            for (let i = 0; i <= N; i++)
                ticks.push({ left: (i / N) * 100, label: this.fmtMs(min + (span * i) / N, true) });
            return { rows, ticks };
        },
    },
    watch: {
        // Tab bawaan 'alur' hanya ada untuk kategori MT. Untuk kategori lain
        // daftar tabnya berbeda, dan tanpa penjagaan ini kandidat membuka
        // halaman ke panel yang tidak pernah dirender — terlihat kosong melompong.
        tabs: {
            immediate: true,
            handler(v) {
                if (v.length && !v.some((t) => t.k === this.tab)) this.tab = v[0].k;
            },
        },
    },
    mounted() {
        this.muatBerkasTes();
        if (this.tugas) {
            this.jawaban = jawabanAwal(this.skemaTugas, this.profil);
            this.muatDraf();
        }
        this.jam = setInterval(() => {
            this.now = Date.now();
        }, 15000);
        this.sambutPulangTes();
    },
    beforeUnmount() {
        if (this.jam) clearInterval(this.jam);
        if (this.tm) clearTimeout(this.tm);
        if (this.timerPindah) clearInterval(this.timerPindah);
        this.hentikanPantauHasil();
    },
    methods: {
        katLabel(k) {
            return { REKRUTMEN: 'Rekrutmen', MT: 'Management Trainee', INTERNSHIP: 'Internship' }[k] || k || '—';
        },
        /**
         * Status satu formulir = KEPUTUSAN tahapnya, bukan sekadar "sudah dikirim".
         *
         * Dulu labelnya hanya melihat waktuKirim, sehingga formulir tahap yang
         * SUDAH diloloskan tetap tertulis "Menunggu" berdampingan dengan formulir
         * yang memang belum diproses — kandidat tidak bisa membedakan keduanya.
         *
         * Hasil hanya dipakai bila memang sudah boleh diumumkan (hasilTampil),
         * jadi aturan Mode Pengumuman tetap dihormati di sini.
         */
        /**
         * Label isian dari SKEMA formulir, bukan turunan nama key.
         *
         * Server hanya bisa menebak dari key-nya, sehingga keluar "V Nama",
         * "Dok Cv", "Setuju Data Benar". Pertanyaan aslinya ada di skema —
         * satu-satunya sumber yang benar — dan key yang tidak ditemukan di sana
         * tetap memakai tebakan server sebagai cadangan.
         */
        labelIsian(j) {
            return LABEL_FIELD[j.key] || j.label;
        },
        /**
         * Isian ini memang berupa berkas?
         *
         * Dinilai dari POLA KUNCI juga, bukan cuma dari ada-tidaknya berkas
         * terunggah — kalau hanya dari berkasnya, dokumen yang BELUM diunggah
         * terbaca sebagai isian teks biasa lalu tampil "—". Padahal justru
         * kekosongan itu yang perlu terbaca sebagai "Belum ada".
         */
        isianBerkas(j) {
            return !!j.berkas || /^(dok|file|berkas|upload)_/i.test(j.key || '');
        },
        /** Jawaban panjang (alamat, uraian) memakai satu baris penuh. */
        isianPanjang(j) {
            if (this.isianBerkas(j)) return false;
            // Isian BERULANG selalu memakai lebar penuh: satu baris riwayat
            // kerja saja sudah memuat perusahaan, jabatan, periode, dan uraian
            // — dijejalkan ke setengah kolom, semuanya terpotong.
            if (j.baris && j.baris.length) return true;

            return String(j.nilai ?? '').length > 60;
        },
        statusFormulir(f) {
            const t = this.tahap.find((x) => x.urutan === f.urutan);

            if (t && t.hasilTampil && t.hasil) {
                return t.hasil === 'LULUS'
                    ? { teks: 'Diterima', kelas: 'is-ok' }
                    : { teks: 'Tidak Lolos', kelas: 'is-err' };
            }
            if (t && t.menungguPengumuman) {
                return { teks: 'Menunggu Pengumuman', kelas: 'is-wait' };
            }
            if (!f.waktuKirim) {
                return { teks: 'Belum Dikirim', kelas: 'is-wait' };
            }

            return { teks: 'Menunggu Diproses', kelas: 'is-wait' };
        },
        /**
         * Kata status — dari server (Master Hasil Keputusan), bukan peta di sini.
         *
         * Peta literal sebelumnya berhenti di tiga status, sehingga kandidat
         * yang mengundurkan diri membaca `MENGUNDURKAN_DIRI` apa adanya di
         * layarnya sendiri.
         */
        stLabel(s) {
            if (s === this.lamaran.status && this.lamaran.statusLabel) {
                return this.lamaran.statusLabel;
            }

            return { BERJALAN: 'Berjalan', LULUS: 'Diterima', GUGUR: 'Tidak Lolos' }[s] || s;
        },
        stepStatus(t) {
            if (t.status === 'SELESAI') return t.hasil === 'GUGUR' ? 'Gugur' : 'Lulus';
            if (t.status === 'BERJALAN') return 'Berlangsung';
            return 'Menunggu';
        },
        // ── Aktivitas dalam satu tahap (mis. Psikotes 2 · DISC · Wawancara) ──
        // Aktivitas ONLINE punya token & jendela waktu; aktivitas manual tidak —
        // yang ditunggu di sana adalah kabar dari tim, bukan tombol mulai tes.
        subState(x) {
            if (x.selesai) return x.hasil === 'GAGAL' ? 'fail' : 'done';
            // TAHAP BERURUTAN — giliran aktivitas ini belum tiba. Didahulukan
            // di atas segalanya: tanpa ini, tes online yang terkunci tetap
            // terbaca "bisa dikerjakan" padahal tombolnya tidak akan bekerja.
            if (x.terkunci) return 'antre';
            if (!x.eksternal) return 'tim';
            if (x.ujian?.bisaAkses) return 'open';
            if (x.ujian?.terjadwal) return 'sched';
            return 'wait';
        },
        subLabel(x) {
            // Nama aktivitas yang ditunggu disebut bila ada — tapi hanya bila
            // server memang mengirimkannya. Aktivitas internal sengaja tidak
            // punya nama di sini, dan menyebutnya akan membocorkan langkah yang
            // justru disembunyikan.
            if (this.subState(x) === 'antre') {
                return x.menunggu ? `menunggu ${x.menunggu} selesai` : 'menunggu tahap sebelumnya';
            }

            return {
                done: 'selesai',
                fail: 'tidak lolos',
                open: 'bisa dikerjakan',
                sched: 'terjadwal',
                wait: 'menunggu jadwal',
                tim: 'diatur tim rekrutmen',
            }[this.subState(x)];
        },
        // Timeline node styling (design).
        // Lamaran yang sudah BERHENTI — apa pun sebabnya — tidak lagi punya
        // tahap "sedang berlangsung". Pemeriksaan lama hanya mengenal GUGUR,
        // sehingga pengunduran diri menyisakan titik berkedip di timeline.
        tlState(t) {
            if (t.status === 'SELESAI') return t.hasil === 'GUGUR' ? 'fail' : 'done';
            if (t.status === 'BERJALAN') {
                if (!this.lamaranBerhenti) return 'current';

                return this.stKey === 'gugur' ? 'fail' : 'todo';
            }

            return 'todo';
        },
        tlTodo(t) {
            return t.status !== 'SELESAI' && t.status !== 'BERJALAN';
        },
        tlCol(t) {
            if (t.status === 'SELESAI') return t.hasil === 'GUGUR' ? '#ef4444' : '#10b981';
            if (t.status === 'BERJALAN') {
                if (!this.lamaranBerhenti) return '#f59e0b';

                return this.stKey === 'gugur' ? '#ef4444' : '#cbd2e0';
            }

            return '#cbd2e0';
        },
        tlNode(t) {
            return {
                borderColor: this.tlCol(t),
                animation: t.status === 'BERJALAN' && !this.lamaranBerhenti ? 'ldPulse 2s infinite' : 'none',
            };
        },
        tlTagStyle(t) {
            if (t.status === 'SELESAI')
                return t.hasil === 'GUGUR'
                    ? { background: 'rgba(239,68,68,.1)', color: '#dc2626' }
                    : { background: 'rgba(16,185,129,.12)', color: '#059669' };
            if (t.status === 'BERJALAN') return { background: 'rgba(245,158,11,.14)', color: '#b45309' };
            return { background: '#eef0f7', color: '#94a3b8' };
        },
        // ── Rentang tanggal tahap (jendela ujian bila tes, else waktu tahap) ──
        tglTahap(t) {
            const u = t.ujian || {};
            return { start: u.waktuMulai || t.waktuMulai || null, end: u.waktuSelesai || t.waktuSelesai || null };
        },
        fmtD(iso, short) {
            if (!iso) return '';
            const d = new Date(String(iso).replace(' ', 'T'));
            if (Number.isNaN(d.getTime())) return '';
            return d.toLocaleDateString(
                'id-ID',
                short ? { day: '2-digit', month: 'short' } : { day: '2-digit', month: 'short', year: 'numeric' },
            );
        },
        fmtMs(ms, short) {
            const d = new Date(ms);
            if (Number.isNaN(d.getTime())) return '';
            return d.toLocaleDateString(
                'id-ID',
                short ? { day: '2-digit', month: 'short' } : { day: '2-digit', month: 'short', year: 'numeric' },
            );
        },
        fmtRangeMs(a, b) {
            const da = this.fmtMs(a),
                db = this.fmtMs(b);
            return da === db ? da : `${this.fmtMs(a, true)} – ${db}`;
        },
        ukuran(b) {
            if (!b) return '';
            return b > 1048576 ? (b / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(b / 1024)) + ' KB';
        },
        /**
         * Buka berkas di modal halaman — gambar maupun PDF.
         *
         * Dulu PDF dilempar ke tab baru, jadi ada dua gaya pratinjau untuk hal
         * yang sama. Modalnya sudah sanggup menyematkan PDF lewat iframe, jadi
         * tidak ada lagi alasan memisahkannya.
         */
        /**
         * Klik foto verifikasi di kartu data kandidat.
         *
         * Template memanggil ini sejak lama, tapi metodenya TIDAK PERNAH ADA —
         * setiap klik hanya melempar "previewBerkas is not a function" di
         * console dan tidak terjadi apa-apa. Diarahkan ke bukaDok() supaya
         * fotonya memakai modal yang sama dengan berkas lain.
         */
        previewBerkas(b) {
            if (!b?.url) return;
            this.bukaDok({ ...b, isImage: b.isImage ?? true });
        },
        /** Muat berkas yang sudah diunggah untuk tiap aktivitas yang memintanya. */
        async muatBerkasTes() {
            for (const u of this.unggahAktivitas) {
                try {
                    const res = await axios.get(`/kandidat/lamaran/tes/${u.id}/berkas`);
                    this.berkasTes = { ...this.berkasTes, [u.key]: res.data.result || [] };
                } catch (e) {
                    // Diam: daftar kosong lebih baik daripada halaman gagal muat.
                }
            }
        },
        /**
         * Kirim berkas ke server.
         *
         * MENGUNGGAH ≠ MENGIRIM. Dua nama yang berbeda, karena keduanya memang
         * dua perbuatan berbeda: yang satu menaruh satu berkas, yang satu
         * menyatakan seluruhnya sudah lengkap. Sempat sama-sama bernama
         * `kirimBerkasTes`, dan karena kunci objek yang kembar diam-diam saling
         * menimpa di JavaScript, yang menang justru pernyataan lengkapnya —
         * sehingga memilih berkas TIDAK MELAKUKAN APA PUN, tanpa satu pesan
         * galat pun. Nama yang berbeda membuat kekeliruan itu mustahil terulang.
         *
         * Format & ukuran diperiksa DI SINI juga supaya kandidat dapat kabar
         * seketika — tapi server tetap memeriksanya sendiri, karena pemeriksaan
         * di layar bisa dilewati.
         */
        async unggahBerkasTes(file, u) {
            const ext = (file.name.split('.').pop() || '').toLowerCase();
            if (!u.format.includes(ext)) {
                this.notice(`"${file.name}" ditolak — hanya menerima ${u.format.join(', ').toUpperCase()}.`, true);

                return;
            }
            if (file.size > u.maksMb * 1024 * 1024) {
                this.notice(`"${file.name}" melebihi ${u.maksMb} MB.`, true);

                return;
            }

            this.unggahDi = u.key;
            const fd = new FormData();
            fd.append('berkas', file);
            try {
                const res = await axios.post(`/kandidat/lamaran/tes/${u.id}/berkas`, fd);
                const baru = res.data.result;
                this.berkasTes = { ...this.berkasTes, [u.key]: [...(this.berkasTes[u.key] || []), baru] };
                this.notice('Berkas terunggah.');
            } catch (e) {
                const err = e.response?.data?.errors;
                this.notice(err ? Object.values(err).flat()[0] : (e.response?.data?.message || 'Berkas gagal diunggah.'), true);
            } finally {
                this.unggahDi = null;
            }
        },
        async hapusBerkasTes(b, u) {
            try {
                await axios.delete(`/kandidat/lamaran/tes/berkas/${b.id}`);
                this.berkasTes = { ...this.berkasTes, [u.key]: (this.berkasTes[u.key] || []).filter((x) => x.id !== b.id) };
                this.notice('Berkas dihapus.');
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal menghapus berkas.', true);
            }
        },

        /**
         * Nyatakan berkas untuk satu aktivitas SUDAH LENGKAP.
         *
         * Halaman disegarkan sesudahnya, bukan sekadar menandai di layar: yang
         * berubah bukan cuma tombol ini — keadaan tahap di atas ikut berpindah
         * dari "ada yang perlu kamu kerjakan" jadi "menunggu penilaian". Kalau
         * hanya tombolnya yang berubah, dua bagian layar yang sama-sama terlihat
         * akan saling bertentangan.
         */
        async kirimBerkasTes(u) {
            if (this.kirimDi || !(this.berkasTes[u.key] || []).length) {
                return;
            }

            this.kirimDi = u.key;
            try {
                const { data } = await axios.patch(`/kandidat/lamaran/tes/${u.id}/berkas/kirim`);
                this.notice(data?.message || 'Berkas terkirim.');
                router.reload({ preserveScroll: true });
            } catch (e) {
                this.notice(e.response?.data?.message || 'Berkas gagal dikirim.', true);
            } finally {
                this.kirimDi = null;
            }
        },
        bukaDok(b) {
            this.lightbox = { ...b, pdf: !b.isImage };
            this.mulaiMuat();
        },
        /**
         * Mulai memuat pratinjau, dengan BATAS WAKTU.
         *
         * URL berkas mengalihkan ke signed URL GCS. Pada PDF di dalam iframe,
         * peristiwa `load` tidak selalu terpicu dan `error` hampir tidak pernah,
         * sehingga spinner bisa berputar selamanya padahal berkasnya sudah
         * tampil. Batas 10 detik membuat keadaan menggantung itu mustahil.
         */
        mulaiMuat() {
            this.lbLoading = true;
            this.lbError = false;
            if (this.lbTimer) clearTimeout(this.lbTimer);
            this.lbTimer = setTimeout(() => {
                this.lbLoading = false;
            }, 10000);
        },
        selesaiMuat(gagal = false) {
            if (this.lbTimer) clearTimeout(this.lbTimer);
            this.lbLoading = false;
            this.lbError = gagal;
        },
        // ── Gerbang waktu tes (fungsi asli, dipertahankan) ──
        belumMulai(t) {
            return t.ujian?.waktuMulai ? this.now < new Date(t.ujian.waktuMulai).getTime() : false;
        },
        sudahLewat(t) {
            return t.ujian?.waktuSelesai ? this.now > new Date(t.ujian.waktuSelesai).getTime() : false;
        },
        sudahSelesai(t) {
            return t.ujian?.statusPengerjaan === 'selesai';
        },
        bisaAkses(t) {
            const u = t.ujian;
            if (!u || !u.link || this.sudahSelesai(t)) return false;
            // Tahap BERURUTAN: tesnya boleh saja sudah punya token dan jendela
            // waktunya terbuka, tapi gilirannya belum tiba. Membiarkan tombolnya
            // hidup berarti kandidat mengerjakan tes di luar urutan yang sudah
            // disusun — dan urutan itu tidak bisa dikembalikan setelahnya.
            if (t.terkunci) return false;

            return !this.belumMulai(t) && !this.sudahLewat(t);
        },
        hitungMundur(t) {
            if (!t.ujian?.waktuMulai) return '';
            const selisih = new Date(t.ujian.waktuMulai).getTime() - this.now;
            if (selisih <= 0) return '';
            const menit = Math.floor(selisih / 60000);
            if (menit < 60) return `${menit} menit lagi`;
            const jam = Math.floor(menit / 60);
            if (jam < 24) return `${jam} jam lagi`;
            return `${Math.floor(jam / 24)} hari lagi`;
        },
        /**
         * Masuk ruang ujian DI TAB YANG SAMA, setelah jeda singkat.
         *
         * Tab baru bermasalah pada dua hal sekaligus: pemblokir popup kerap
         * menahannya tanpa kabar (kandidat mengira tombolnya rusak), dan begitu
         * ujian selesai, tab CAT tidak punya jalan pulang — kandidat menutupnya
         * lalu menatap halaman lamaran yang isinya belum berubah.
         *
         * Jeda 3 detik bukan hiasan: berpindah domain tanpa peringatan terbaca
         * seperti halaman yang tiba-tiba hilang, dan di layar inilah kandidat
         * sempat membaca bahwa ia akan dipindahkan.
         */
        bukaTes(t) {
            if (!this.bisaAkses(t) || this.menujuUjian) {
                return;
            }

            // TITIPKAN JEJAK sebelum pergi. Saat kandidat diantar pulang oleh
            // CAT, hasil tesnya belum tentu sudah sampai ke sini — callback-nya
            // antrean, bukan seketika. Tanpa jejak ini halaman menyambutnya
            // dengan tombol "Mulai Tes Sekarang" untuk tes yang baru saja ia
            // kerjakan: pesan paling menakutkan yang bisa dibaca seseorang yang
            // baru selesai ujian.
            //
            // sessionStorage, bukan localStorage: jejaknya milik tab ini saja
            // dan ikut hilang saat tabnya ditutup — tidak menempel di peramban
            // bersama orang lain yang memakai komputer yang sama.
            this.tandaiPergiTes(t);

            this.menujuUjian = true;
            this.hitungPindah = 3;

            this.timerPindah = setInterval(() => {
                this.hitungPindah--;
                if (this.hitungPindah > 0) {
                    return;
                }

                clearInterval(this.timerPindah);
                // `replace`, bukan `href`: menekan Kembali dari ruang ujian tak
                // boleh melempar kandidat ke halaman perantara ini lalu
                // memindahkannya lagi secara otomatis.
                window.location.replace(t.ujian.link);
            }, 1000);
        },
        batalKeUjian() {
            clearInterval(this.timerPindah);
            this.menujuUjian = false;
            // Batal berarti tidak jadi pergi — jejaknya harus ikut hilang,
            // kalau tidak halaman ini menyambut kepulangan yang tak pernah ada.
            this.lupakanPergiTes();
        },

        // ── Jejak "sedang di ruang ujian" ────────────────────────────────────
        kunciPergiTes() {
            return 'wc_tes_pergi_' + (this.lamaran?.id || '');
        },
        tandaiPergiTes(t) {
            try {
                sessionStorage.setItem(this.kunciPergiTes(), JSON.stringify({ id: t.id, at: Date.now() }));
            } catch (e) {
                // Mode privat / storage penuh — fitur sambutan hilang, tapi
                // membuka tesnya TIDAK BOLEH ikut gagal karenanya.
            }
        },
        bacaPergiTes() {
            try {
                const isi = JSON.parse(sessionStorage.getItem(this.kunciPergiTes()) || 'null');
                if (!isi || !isi.id) return null;

                // Jejak basi dibuang. Batasnya longgar (12 jam) karena satu sesi
                // tes bisa berjam-jam, tapi tetap ada supaya tab yang dibiarkan
                // menganggur berhari-hari tidak menyambut kandidat dengan kabar
                // tes yang sudah lama selesai.
                if (Date.now() - (isi.at || 0) > 12 * 60 * 60 * 1000) {
                    this.lupakanPergiTes();
                    return null;
                }

                return isi;
            } catch (e) {
                return null;
            }
        },
        lupakanPergiTes() {
            this.pulangTes = null;
            this.hentikanPantauHasil();
            try {
                sessionStorage.removeItem(this.kunciPergiTes());
            } catch (e) {
                /* diabaikan — lihat tandaiPergiTes() */
            }
        },

        /**
         * Cadangan bila jejak sessionStorage tidak ada: penanda `?dari=tes`
         * yang dipasang di alamat pulang (lihat WcPenjadwalanJob).
         *
         * Jejak bisa hilang secara wajar — kandidat menutup tabnya di tengah
         * ujian lalu membuka tautan pulang dari email, peramban ponsel yang
         * membersihkan penyimpanan sesi saat berpindah domain, atau mode privat.
         *
         * Penanda alamat tidak menyebut aktivitas MANA, jadi ia hanya dipakai
         * bila jawabannya cuma satu: tepat satu tes daring yang belum selesai.
         * Bila ada dua, menebak berarti bisa menyembunyikan tes yang justru
         * masih harus dikerjakan — itu lebih buruk daripada tidak menyambut.
         */
        tebakPulangDariAlamat() {
            try {
                if (new URLSearchParams(window.location.search).get('dari') !== 'tes') {
                    return null;
                }
            } catch (e) {
                return null;
            }

            const daring = (this.aktivitas || []).filter((x) => x.ujian && !x.selesai && !this.sudahSelesai(x));

            return daring.length === 1 ? { id: daring[0].id, at: Date.now() } : null;
        },

        /**
         * Sambut kepulangan dari ruang ujian, lalu tunggu hasilnya masuk.
         *
         * Hasil tes datang lewat callback CAT yang diproses antrean, jadi ada
         * jeda antara "kandidat sampai di halaman ini" dan "tahapnya berubah
         * jadi selesai". Halaman menyegarkan dirinya sendiri selama jeda itu,
         * supaya posisinya berubah di depan mata kandidat — bukan menuntutnya
         * menekan muat-ulang untuk sesuatu yang memang sedang berjalan.
         */
        sambutPulangTes() {
            const jejak = this.bacaPergiTes() || this.tebakPulangDariAlamat();
            if (!jejak) {
                return;
            }

            this.pulangTes = jejak;

            // Sudah tercatat selesai sebelum ia sampai — tidak ada yang perlu
            // ditunggu, dan sambutannya tidak perlu ditampilkan sama sekali.
            if (!this.tesDitunggu) {
                this.lupakanPergiTes();

                return;
            }

            this.pulangCek = 0;
            this.pulangTimer = setInterval(() => {
                if (!this.tesDitunggu) {
                    this.lupakanPergiTes();

                    return;
                }

                // Berhenti setelah ~3 menit. Callback yang belum juga sampai
                // sesudah itu bukan lagi soal jeda antrean, dan menyegarkan
                // halaman tanpa akhir hanya membebani server tanpa mengubah
                // apa pun. Keterangannya tetap tampil — yang berhenti hanya
                // pemeriksaannya.
                if (++this.pulangCek > 12) {
                    this.hentikanPantauHasil();

                    return;
                }

                router.reload({ preserveScroll: true });
            }, 15000);
        },
        hentikanPantauHasil() {
            if (this.pulangTimer) {
                clearInterval(this.pulangTimer);
                this.pulangTimer = null;
            }
        },
        fmtWaktu(iso) {
            if (!iso) return '—';
            return new Date(iso).toLocaleString('id-ID', {
                day: '2-digit',
                month: 'short',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
            });
        },
        fmtTanggal(v) {
            if (!v) return '—';
            const d = new Date(String(v).replace(' ', 'T'));
            return Number.isNaN(d.getTime())
                ? '—'
                : d.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
        },
        onBerkas(e) {
            if (e.galat) {
                this.notice(e.galat, true);
                return;
            }
            // Pakai lightbox yang SAMA dengan pratinjau berkas lain di halaman
            // ini, supaya kandidat tidak menemui dua gaya pratinjau berbeda.
            if (e.lihat) {
                this.lbLoading = true;
                this.lbError = false;
                this.lightbox = { ...e.lihat, field: e.field.key, pdf: !e.lihat.gambar };
                return;
            }
            // Dihapus di kartu pratinjau -> berkasnya harus ikut dibuang dari
            // kumpulan yang akan dikirim, bukan hanya hilang dari layar.
            if (e.hapus) {
                delete this.berkas[e.field.key];
                delete this.berkasDraf[e.field.key];
                this.notice('Berkas dihapus.');
                return;
            }
            if (e.file) {
                this.berkas[e.field.key] = e.file;
                this.unggahDraf(e.field.key, e.file);
            }
        },
        async kirim(nilai) {
            if (this.mengirim || !this.tugas) return;
            this.mengirim = true;
            try {
                const res = await axios.post(
                    `/api/v1/lamaran/tahap/${this.tugas.tahapId}/kirim`,
                    { jawaban: nilai },
                    CFG,
                );
                this.notice(res.data?.message || 'Formulir terkirim.');
                setTimeout(() => router.reload(), 800);
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal mengirim formulir.', true);
            } finally {
                this.mengirim = false;
            }
        },
        /**
         * Muat simpanan sementara dari server lalu lanjutkan dari sana.
         *
         * Gagal memuat TIDAK memblokir pengisian: kandidat tetap bisa mulai dari
         * isian kosong. Draf adalah kenyamanan, bukan syarat.
         */
        async muatDraf() {
            if (!this.tugas) return;
            try {
                const { data } = await axios.get(`/kandidat/lamaran/tahap/${this.tugas.tahapId}/draf`);
                const d = data?.result?.draf;
                if (d) {
                    this.jawaban = { ...this.jawaban, ...(d.jawaban || {}) };
                    this.langkahAwal = d.langkah || 0;
                    this.berkasDraf = Object.fromEntries((d.berkas || []).map((b) => [b.field, b]));
                    this.buangBerkasBasi();
                    if (d.disimpanAt) this.notice('Melanjutkan isian yang tersimpan sebelumnya.');
                }
            } catch (e) {
                // Diam saja — lihat alasan di atas.
            } finally {
                this.drafSiap = true;
            }
        },
        /**
         * Naikkan berkas ke simpanan sementara di server.
         *
         * Dilakukan begitu berkas dipilih, bukan ditunda sampai Kirim: tanpa ini
         * berkas hanya hidup di memori tab, sehingga refresh membuat kandidat
         * harus memilih ulang semuanya — dan pratinjaunya tidak bisa dibuka
         * karena tidak ada apa pun di server untuk ditandatangani URL-nya.
         */
        async unggahDraf(key, file) {
            if (!this.tugas) return;
            const fd = new FormData();
            fd.append('field', key);
            fd.append('berkas', file);
            try {
                const { data } = await axios.post(`/kandidat/lamaran/tahap/${this.tugas.tahapId}/draf/berkas`, fd);
                const r = data?.result;
                if (r?.url) {
                    this.berkasDraf = { ...this.berkasDraf, [key]: r };
                } else {
                    // Server menerima berkasnya tapi tidak mengembalikan URL.
                    // Tarik ulang daftarnya daripada membiarkan kartu tanpa tautan.
                    await this.muatBerkasDraf();
                }
            } catch (err) {
                this.notice(err.response?.data?.message || 'Berkas gagal disimpan sementara. Coba unggah ulang.', true);
            }
        },
        /**
         * Bersihkan nama berkas di draf yang TIDAK punya salinan di server.
         *
         * Bisa terjadi pada draf yang dibuat sebelum unggahan draf ada, atau bila
         * unggahannya gagal setelah jawabannya telanjur tersimpan. Kalau
         * dibiarkan, kandidat melihat kotak hijau "berkas siap dikirim" untuk
         * berkas yang isinya tidak ada di mana pun — terlihat beres, padahal
         * yang terkirim nanti kosong. Lebih baik dikembalikan ke keadaan kosong
         * dan diminta unggah ulang.
         */
        buangBerkasBasi() {
            if (!this.tugas) return;
            const skema = this.skemaTugas;
            const fieldBerkas = (skema?.langkah || [])
                .flatMap((L) => L.bagian || [])
                .flatMap((B) => B.field || [])
                .filter((x) => x.tipe === 'file');

            const basi = fieldBerkas.filter(
                (x) => this.jawaban[x.key] && !this.berkasDraf[x.key] && !this.berkas[x.key],
            );
            if (!basi.length) return;

            basi.forEach((x) => {
                this.jawaban[x.key] = '';
            });
            this.notice(`${basi.length} berkas perlu diunggah ulang — salinannya tidak ditemukan di server.`, true);
        },
        /** Tarik ulang daftar berkas draf dari server. */
        async muatBerkasDraf() {
            if (!this.tugas) return;
            try {
                const { data } = await axios.get(`/kandidat/lamaran/tahap/${this.tugas.tahapId}/draf`);
                const b = data?.result?.draf?.berkas || [];
                this.berkasDraf = Object.fromEntries(b.map((x) => [x.field, x]));
            } catch (e) {
                // Tidak fatal: kartu berkas cukup kehilangan tautannya.
            }
        },
        /** Simpan tiap kali kandidat berpindah langkah ("Lanjut"/"Kembali"). */
        async simpanDraf(langkah) {
            if (!this.tugas || !this.drafSiap) return;
            try {
                await axios.post(`/kandidat/lamaran/tahap/${this.tugas.tahapId}/draf`, {
                    jawaban: this.jawaban,
                    langkah,
                    komponen: this.tugas.komponen,
                    schema: this.tugas.schema || null,
                });
            } catch (e) {
                // Pesan dari server ditampilkan apa adanya bila ada — galat
                // validasi yang disembunyikan di balik kalimat umum membuat
                // sebabnya mustahil ditebak dari layar.
                this.notice(
                    err.response?.data?.message || 'Isian belum tersimpan ke server. Periksa koneksi Anda.',
                    true,
                );
            }
        },
        notice(x, err = false) {
            this.toast = x;
            this.toastErr = err;
            if (this.tm) clearTimeout(this.tm);
            this.tm = setTimeout(() => (this.toast = ''), 4000);
        },
    },
};
</script>

<style scoped>
.ld-mono {
    font-family: 'JetBrains Mono', 'Courier New', monospace;
    font-weight: 700;
    color: #8b93a7;
}
.ld-seclabel {
    font-size: 12px;
    font-weight: 800;
    letter-spacing: 0.14em;
    color: #8b93a7;
}
.ld-eyebrow {
    font-size: 11px;
    font-weight: 800;
    letter-spacing: 0.14em;
    color: #8b5cf6;
}
.ld-sectitle {
    display: flex;
    align-items: center;
    gap: 9px;
    font-size: 15px;
    font-weight: 800;
    color: #1e293b;
}

/* overflow-x: clip mengeklip blob horizontal TANPA menjadikan .ld scroll
   container (hidden akan memaksa overflow-y:auto → scrollbar ganda). */
.ld {
    position: relative;
    margin: -1rem;
    padding: 26px 30px 44px;
    min-height: calc(100vh - 68px);
    overflow-x: clip;
}
.ld-blob {
    position: absolute;
    border-radius: 50%;
    filter: blur(8px);
    pointer-events: none;
    z-index: 0;
}
.ld-blob--a {
    top: -120px;
    right: 10%;
    width: 420px;
    height: 420px;
    background: radial-gradient(circle at 30% 30%, rgba(139, 92, 246, 0.13), rgba(139, 92, 246, 0) 70%);
    animation: ldFloatA 16s ease-in-out infinite;
}
.ld-blob--b {
    bottom: -160px;
    left: 6%;
    width: 440px;
    height: 440px;
    background: radial-gradient(circle at 60% 40%, rgba(99, 102, 241, 0.1), rgba(99, 102, 241, 0) 70%);
    animation: ldFloatB 19s ease-in-out infinite;
}
@keyframes ldFloatA {
    0%,
    100% {
        transform: translate(0, 0);
    }
    50% {
        transform: translate(28px, -22px);
    }
}
@keyframes ldFloatB {
    0%,
    100% {
        transform: translate(0, 0);
    }
    50% {
        transform: translate(-24px, 20px);
    }
}
/* Fluid: mengisi penuh lebar shell-content (bukan container terpusat). */
.ld-wrap {
    position: relative;
    z-index: 2;
    width: 100%;
}

.ld-back {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    font-size: 13px;
    font-weight: 700;
    color: #64748b;
    text-decoration: none;
    margin-bottom: 14px;
    transition: color 0.16s;
}
.ld-back:hover {
    color: #4f46e5;
}

/* HERO */
.ld-hero {
    position: relative;
    border-radius: 24px;
    overflow: hidden;
    background: #fff;
    border: 1px solid #eef0f7;
    box-shadow: 0 12px 34px rgba(15, 23, 42, 0.07);
    animation: ldRise 0.5s cubic-bezier(0.22, 1, 0.36, 1) both;
}
@keyframes ldRise {
    from {
        opacity: 0;
        transform: translateY(14px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
.ld-hero__bar {
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 5px;
    background: linear-gradient(180deg, #8b5cf6, #6366f1);
}
.ld-hero__glow {
    position: absolute;
    top: -70px;
    right: -40px;
    width: 240px;
    height: 240px;
    border-radius: 50%;
    background: radial-gradient(circle at 50% 50%, rgba(99, 102, 241, 0.08), rgba(99, 102, 241, 0) 70%);
    pointer-events: none;
}
.ld-hero__in {
    position: relative;
    display: flex;
    align-items: flex-start;
    gap: 16px;
    padding: 22px 26px 22px 28px;
}
.ld-hero__avatar {
    width: 52px;
    height: 52px;
    border-radius: 15px;
    background: linear-gradient(135deg, #8b5cf6, #6366f1);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    flex: 0 0 auto;
    box-shadow: 0 10px 24px rgba(99, 102, 241, 0.28);
}
.ld-hero__badges {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}
.ld-chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 5px 11px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 800;
}
.ld-pulse {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #f59e0b;
    animation: ldPulse 2s infinite;
}
@keyframes ldPulse {
    0%,
    100% {
        box-shadow: 0 0 0 0 rgba(245, 158, 11, 0.5);
    }
    70% {
        box-shadow: 0 0 0 8px rgba(245, 158, 11, 0);
    }
}
.ld-hero__title {
    margin: 11px 0 0;
    font-size: 23px;
    font-weight: 800;
    color: #0f172a;
    letter-spacing: -0.02em;
}
.ld-hero__meta {
    font-size: 12.5px;
    color: #8792a6;
    margin-top: 5px;
}

/* BANNER */
/* Jarak DUA ARAH. Banner, kartu keputusan, dan progres bisa muncul
   berurutan; kalau hanya margin-atas yang diberi, blok pertama menempel
   ke blok sesudahnya dan terbaca seperti satu kotak yang tumpang tindih. */
.ld-banner {
    display: flex;
    gap: 13px;
    align-items: center;
    margin: 16px 0 14px;
    padding: 15px 17px;
    border-radius: 16px;
    border: 1px solid transparent;
}
.ld-banner__ico {
    width: 38px;
    height: 38px;
    border-radius: 11px;
    flex: 0 0 auto;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
}
.ld-banner__title {
    font-size: 14px;
    font-weight: 800;
}
.ld-banner__text {
    font-size: 12.5px;
    line-height: 1.55;
    opacity: 0.85;
    margin-top: 2px;
}

/* PROGRES */
.ld-prog {
    margin-top: 16px;
    background: rgba(255, 255, 255, 0.72);
    border: 1px solid rgba(226, 232, 240, 0.75);
    border-radius: 20px;
    padding: 16px 20px;
    box-shadow: 0 6px 20px rgba(15, 23, 42, 0.04);
}
.ld-prog__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 12px;
    flex-wrap: wrap;
}
.ld-prog__head .ld-seclabel {
    letter-spacing: 0.1em;
}
.ld-prog__bar {
    height: 7px;
    border-radius: 99px;
    background: #eef0f7;
    overflow: hidden;
    margin-bottom: 16px;
}
.ld-prog__bar div {
    height: 100%;
    border-radius: 99px;
    background: linear-gradient(90deg, #8b5cf6, #6366f1);
    transition: width 0.4s ease;
}
.ld-steps {
    display: flex;
    align-items: flex-start;
    overflow-x: auto;
    padding-bottom: 6px;
}
.ld-step {
    flex: 0 0 120px;
    display: flex;
    flex-direction: column;
    align-items: center;
    position: relative;
}
.ld-step__line {
    position: absolute;
    top: 14px;
    left: -50%;
    width: 100%;
    height: 3px;
}
.ld-step__node {
    position: relative;
    z-index: 1;
    width: 30px;
    height: 30px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    font-weight: 800;
    background: #eef0f7;
    color: #94a3b8;
}
.ld-step__node.is-done {
    background: linear-gradient(135deg, #8b5cf6, #6366f1);
    color: #fff;
}
.ld-step__node.is-current {
    background: #fff;
    color: #4f46e5;
    border: 2px solid #f59e0b;
    box-shadow: 0 0 0 4px rgba(245, 158, 11, 0.18);
}
.ld-step__node.is-fail {
    background: #fff;
    color: #dc2626;
    border: 2px solid #ef4444;
    box-shadow: 0 0 0 4px rgba(239, 68, 68, 0.14);
}
.ld-step__lbl {
    margin-top: 9px;
    font-size: 10.5px;
    font-weight: 700;
    text-align: center;
    line-height: 1.3;
    padding: 0 5px;
}

/* LAYOUT — single column fluid */
.ld-mainc {
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 16px;
    margin-top: 22px;
}
.ld-card {
    background: #fff;
    border: 1px solid #eef0f7;
    border-radius: 20px;
    box-shadow: 0 2px 8px rgba(15, 23, 42, 0.03);
}

/* TAHAP AKTIF */
.ld-act {
    overflow: hidden;
}
.ld-act__head {
    display: flex;
    gap: 13px;
    padding: 18px 20px;
    background: linear-gradient(180deg, rgba(99, 102, 241, 0.05), transparent);
    border-bottom: 1px solid #eef0f7;
}
.ld-act__ico {
    flex: 0 0 auto;
    width: 46px;
    height: 46px;
    border-radius: 14px;
    background: linear-gradient(135deg, #8b5cf6, #6366f1);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 10px 24px rgba(99, 102, 241, 0.28);
}
.ld-act__title {
    font-size: 17px;
    font-weight: 800;
    color: #0f172a;
    margin-top: 3px;
    letter-spacing: -0.01em;
}
.ld-act__sub {
    font-size: 12.5px;
    color: #8792a6;
    margin-top: 2px;
}
.ld-act__form {
    padding: 18px 20px;
}

/* Daftar aktivitas dalam satu tahap (tahap multi-tes) */
.ld-subtes {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin: 16px 20px 0;
}
.ld-subtes__i {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 6px 11px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 700;
    color: #334155;
    background: #f6f7fb;
    border: 1px solid #e7eaf3;
}
.ld-subtes__i > i {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: #cbd5e1;
    flex: 0 0 auto;
}
.ld-subtes__i em {
    font-style: normal;
    font-weight: 600;
    font-size: 11px;
    color: #94a3b8;
}
.ld-subtes__i.is-done > i {
    background: #10b981;
}
.ld-subtes__i.is-fail > i {
    background: #ef4444;
}
.ld-subtes__i.is-open {
    border-color: #a5b4fc;
    background: #eef0fe;
}
.ld-subtes__i.is-open > i {
    background: #6366f1;
    animation: ldPulse 2s infinite;
}
.ld-subtes__i.is-sched > i {
    background: #f59e0b;
}
.ld-subtes__i.is-tim > i {
    background: #94a3b8;
}

.ld-cred {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1px;
    background: #eef0f7;
    border: 1px solid #eef0f7;
    border-radius: 14px;
    overflow: hidden;
    margin: 18px 20px 0;
}
.ld-cred__item {
    background: #fff;
    padding: 13px 15px;
    min-width: 0;
}
.ld-cred__lbl {
    display: block;
    font-size: 10.5px;
    font-weight: 800;
    letter-spacing: 0.1em;
    color: #a2a9ba;
}
.ld-cred__val {
    display: block;
    font-size: 15.5px;
    font-weight: 800;
    color: #1e293b;
    margin-top: 4px;
    word-break: break-word;
}
.ld-cred__val.ld-mono {
    color: #4f46e5;
    letter-spacing: 0.05em;
}

.ld-notice {
    display: flex;
    gap: 11px;
    align-items: flex-start;
    margin: 14px 20px 0;
    padding: 13px 15px;
    border-radius: 14px;
    font-size: 12.5px;
    line-height: 1.55;
}
.ld-notice b {
    display: block;
    margin-bottom: 1px;
    color: #1e293b;
}
.ld-notice p {
    margin: 0;
    color: #64748b;
}
/* Kotak informasi yang jadi elemen terakhir kartu butuh jarak bawah
   sendiri — tombol yang biasanya memberi jarak itu disembunyikan pada
   sebagian keadaan, sehingga kotaknya menempel ke tepi kartu. */
.ld-notice + .ld-notice {
    margin-top: 10px;
}
.ld-notice:last-child {
    margin-bottom: 22px;
}
.ld-notice--wait {
    --ldico: #d97706;
    background: linear-gradient(135deg, #fffbeb, #fff8ec);
    border: 1px solid #f5e0a3;
}
.ld-notice--done {
    --ldico: #059669;
    background: rgba(16, 185, 129, 0.08);
    border: 1px solid rgba(16, 185, 129, 0.24);
}
.ld-notice--err {
    --ldico: #dc2626;
    background: rgba(239, 68, 68, 0.07);
    border: 1px solid rgba(239, 68, 68, 0.22);
}
/* ── Ikon status animatif ────────────────────────────────────────────────────
   Warna diambil dari --ldico milik variannya, jadi satu set markup dipakai
   ulang untuk hijau/kuning/merah tanpa menduplikasi SVG. Gerakannya halus dan
   berulang pelan; saat kartunya di-hover, temponya dipercepat sebagai umpan
   balik — bukan sekadar hiasan yang berputar terus. */
.ld-nico {
    flex: 0 0 auto;
    margin-top: -3px;
}
.ld-nico svg {
    display: block;
    overflow: visible;
}
.ld-nico__halo {
    fill: var(--ldico);
    opacity: 0.13;
    transform-origin: 22px 22px;
    animation: ldHalo 3.2s ease-in-out infinite;
}
.ld-nico__ring,
.ld-nico__check,
.ld-nico__hand,
.ld-nico__tri,
.ld-nico__bang,
.ld-nico__stroke {
    fill: none;
    stroke: var(--ldico);
    stroke-width: 2.2;
    stroke-linecap: round;
    stroke-linejoin: round;
}
.ld-nico__pin,
.ld-nico__dot,
.ld-nico__sand {
    fill: var(--ldico);
    stroke: none;
}

/* Cincin & centang menggambar dirinya sendiri sekali saat muncul. */
.ld-nico__ring {
    stroke-dasharray: 85;
    stroke-dashoffset: 85;
    animation: ldGambar 0.8s cubic-bezier(0.4, 0, 0.2, 1) forwards;
}
.ld-nico__check {
    stroke-width: 2.7;
    stroke-dasharray: 24;
    stroke-dashoffset: 24;
    animation: ldGambarKecil 0.42s 0.52s cubic-bezier(0.4, 0, 0.2, 1) forwards;
}
.ld-nico__tri {
    stroke-dasharray: 64;
    stroke-dashoffset: 64;
    animation: ldGambar 0.75s cubic-bezier(0.4, 0, 0.2, 1) forwards;
}
.ld-nico__bang,
.ld-nico__dot {
    animation: ldKedip 2.1s ease-in-out infinite;
}

/* Jam: jarum menit berputar 4s, jarum jam 24s — terbaca sebagai "berjalan". */
.ld-nico__hand {
    transform-origin: 22px 22px;
}
.ld-nico__hand--h {
    animation: ldPutar 24s linear infinite;
}
.ld-nico__hand--m {
    animation: ldPutar 4s linear infinite;
}

/* Jam pasir: dibalik dua kali per siklus supaya kembali ke 360° tanpa lompat. */
.ld-nico__glass {
    transform-origin: 22px 22px;
    animation: ldBalik 5s cubic-bezier(0.65, 0, 0.35, 1) infinite;
}
.ld-nico__sand {
    animation: ldPasir 2.5s cubic-bezier(0.45, 0, 0.9, 0.55) infinite;
}

/* Interaksi: kartunya terangkat sedikit dan animasinya dipercepat saat hover. */
.ld-notice {
    transition:
        transform 0.22s ease,
        box-shadow 0.22s ease;
}
.ld-notice:hover {
    transform: translateY(-1px);
    box-shadow: 0 10px 24px rgba(15, 23, 42, 0.07);
}
.ld-notice:hover .ld-nico__halo {
    animation-duration: 1.3s;
}
.ld-notice:hover .ld-nico__hand--m {
    animation-duration: 1.1s;
}
.ld-notice:hover .ld-nico__glass {
    animation-duration: 2.2s;
}
.ld-notice:hover .ld-nico__sand {
    animation-duration: 1.1s;
}

@keyframes ldHalo {
    0%,
    100% {
        transform: scale(1);
        opacity: 0.13;
    }
    50% {
        transform: scale(1.13);
        opacity: 0.2;
    }
}
@keyframes ldGambar {
    to {
        stroke-dashoffset: 0;
    }
}
@keyframes ldGambarKecil {
    to {
        stroke-dashoffset: 0;
    }
}
@keyframes ldPutar {
    to {
        transform: rotate(360deg);
    }
}
@keyframes ldKedip {
    0%,
    100% {
        opacity: 1;
    }
    45% {
        opacity: 0.25;
    }
}
@keyframes ldBalik {
    0%,
    30% {
        transform: rotate(0deg);
    }
    45%,
    80% {
        transform: rotate(180deg);
    }
    95%,
    100% {
        transform: rotate(360deg);
    }
}
@keyframes ldPasir {
    0%,
    6% {
        transform: translateY(-5px);
        opacity: 0;
    }
    16% {
        opacity: 1;
    }
    62% {
        transform: translateY(5px);
        opacity: 1;
    }
    72%,
    100% {
        opacity: 0;
    }
}

.ld-tabs {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin: 18px 0 14px;
    padding: 5px;
    border-radius: 14px;
    background: #f1f2f9;
}
.ld-tabs__b {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 9px 14px;
    border: 0;
    border-radius: 10px;
    background: transparent;
    font: inherit;
    font-size: 13px;
    font-weight: 700;
    color: #64748b;
    cursor: pointer;
    transition:
        background 0.16s,
        color 0.16s,
        box-shadow 0.16s;
}
.ld-tabs__b:hover {
    color: #4f46e5;
}
.ld-tabs__b.is-on {
    background: #fff;
    color: #4338ca;
    box-shadow: 0 2px 8px rgba(15, 23, 42, 0.07);
}
.ld-tabs__n {
    padding: 1px 7px;
    border-radius: 999px;
    font-size: 10.5px;
    font-weight: 800;
    background: rgba(99, 102, 241, 0.13);
    color: #4f46e5;
}

.ld-cat {
    padding-bottom: 6px;
}
.ld-cat__i {
    padding: 14px 20px;
    border-top: 1px solid #f4f5fa;
}
.ld-cat__i:first-of-type {
    border-top: 0;
}
.ld-cat__head {
    display: flex;
    align-items: center;
    gap: 11px;
}
.ld-cat__no {
    flex: none;
    width: 30px;
    height: 30px;
    border-radius: 9px;
    display: grid;
    place-items: center;
    background: #eef0fb;
    color: #4f46e5;
    font-size: 11.5px;
    font-weight: 800;
}
.ld-cat__head b {
    display: block;
    font-size: 14px;
    font-weight: 800;
    color: #1e293b;
}
.ld-cat__head small {
    display: block;
    font-size: 11px;
    color: #94a3b8;
    margin-top: 1px;
}
.ld-cat__st {
    flex: none;
    padding: 3px 10px;
    border-radius: 999px;
    font-size: 10.5px;
    font-weight: 800;
}
.ld-cat__st.is-ok {
    color: #059669;
    background: rgba(16, 185, 129, 0.12);
}
.ld-cat__st.is-err {
    color: #dc2626;
    background: rgba(239, 68, 68, 0.1);
}
/* Catatan tim — kartu berbingkai, bukan paragraf lepas. */
.ld-cat__note {
    margin: 10px 0 0 41px;
    padding: 10px 13px;
    border: 1px solid #e6e8f2;
    border-left: 3px solid #6366f1;
    border-radius: 0 12px 12px 0;
    background: #fafbff;
}
.ld-cat__note-lbl {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 9.5px;
    font-weight: 800;
    letter-spacing: 0.09em;
    color: #6366f1;
}
.ld-cat__note p {
    margin: 5px 0 0;
    font-size: 13px;
    line-height: 1.6;
    color: #475569;
    white-space: pre-line;
}

/* Catatan per aktivitas — daftar bernomor visual, tiap butir berbingkai
   supaya terbaca sebagai catatan terpisah, bukan satu blok teks panjang. */
.ld-cat__list {
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin: 10px 0 0 41px;
}
.ld-cat__sub {
    padding: 9px 12px;
    border: 1px solid #eceefb;
    border-left: 3px solid #a5b4fc;
    background: #fafaff;
    border-radius: 0 10px 10px 0;
}
.ld-cat__sub b {
    font-size: 12.5px;
    font-weight: 800;
    color: #334155;
}
.ld-cat__sub small {
    font-weight: 600;
    color: #94a3b8;
}
.ld-cat__sub p {
    margin: 3px 0 0;
    font-size: 12.5px;
    line-height: 1.6;
    color: #475569;
    white-space: pre-line;
}
.ld-cat__berkas {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 7px;
    margin: 11px 0 0 41px;
}
.ld-cat__berkas-lbl {
    width: 100%;
    font-size: 9.5px;
    font-weight: 800;
    letter-spacing: 0.09em;
    color: #a2a9ba;
}
.ld-cat__berkas button {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    max-width: 100%;
    padding: 6px 11px;
    border: 1px solid #e6e8f2;
    border-radius: 10px;
    background: #fff;
    font: inherit;
    font-size: 12.5px;
    font-weight: 700;
    color: #1e293b;
    cursor: pointer;
    transition:
        border-color 0.16s,
        background 0.16s;
}
.ld-cat__berkas button:hover {
    border-color: #a5b4fc;
    background: #f5f3ff;
}
.ld-cat__berkas .bi {
    flex: none;
    color: #dc2626;
}
.ld-cat__berkas .bi-file-earmark-image-fill {
    color: #6366f1;
}
.ld-cat__berkas span {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.ld-cat__berkas em {
    flex: none;
    font-style: normal;
    font-size: 11px;
    font-weight: 800;
    color: #4f46e5;
}

/* Jarak kartu jadwal terhadap kartu tahap; isinya milik JadwalKartu.vue. */
.ld-jdwwrap {
    margin: 16px 20px 0;
}

/* Kartu tahap (.ld-act) tidak punya padding bawah — anak-anaknya yang membawa
   margin ATAS saja. Elemen terakhir karenanya menempel persis di tepi kartu,
   dan pada tahap tatap muka (jadwal sebagai isian terakhir) hasilnya terlihat
   seperti kartu yang terpotong. */
.ld-jdwwrap:last-child,
.ld-mcu:last-child,
.ld-keadaan:last-child,
.ld-notice:last-child {
    margin-bottom: 20px;
}

.ld-mcu {
    display: flex;
    gap: 13px;
    margin: 16px 20px 0;
    padding: 15px 17px;
    border-radius: 16px;
    border: 1px solid;
}
.ld-mcu.is-fit {
    background: rgba(16, 185, 129, 0.07);
    border-color: rgba(16, 185, 129, 0.3);
}
.ld-mcu.is-fit_with_note {
    background: rgba(245, 158, 11, 0.09);
    border-color: rgba(245, 158, 11, 0.32);
}
.ld-mcu.is-unfit {
    background: rgba(239, 68, 68, 0.07);
    border-color: rgba(239, 68, 68, 0.28);
}
.ld-mcu__ico {
    flex: none;
    width: 40px;
    height: 40px;
    border-radius: 12px;
    display: grid;
    place-items: center;
    color: #fff;
    font-size: 17px;
}
.ld-mcu.is-fit .ld-mcu__ico {
    background: linear-gradient(140deg, #34d399, #10b981);
}
.ld-mcu.is-fit_with_note .ld-mcu__ico {
    background: linear-gradient(140deg, #fbbf24, #f59e0b);
}
.ld-mcu.is-unfit .ld-mcu__ico {
    background: linear-gradient(140deg, #f87171, #dc2626);
}
.ld-mcu__judul {
    font-size: 15px;
    font-weight: 800;
    color: #1e293b;
    margin-top: 2px;
}
.ld-mcu__meta {
    display: flex;
    flex-wrap: wrap;
    gap: 4px 14px;
    margin-top: 4px;
    font-size: 12px;
    color: #64748b;
}
.ld-mcu__cat {
    margin: 9px 0 0;
    font-size: 12.5px;
    line-height: 1.6;
    color: #475569;
}


/* ── KEADAAN TAHAP — satu panel tenang, bukan daftar asesmen ────────────────
   Menggantikan daftar aktivitas berikut statusnya. Bentuknya sengaja
   menyerupai pemberitahuan, bukan tabel: tidak ada yang bisa ditelusuri
   kandidat di sini, dan tabel selalu mengundang untuk ditelusuri. */
.ld-keadaan {
    display: flex; align-items: flex-start; gap: 14px;
    margin: 16px 20px 0; padding: 16px 18px;
    border-radius: 18px; border: 1px solid;
    /* --ldk = warna aksen keadaan; seluruh bagian di dalam mewarisinya, jadi
       menambah keadaan baru cukup menyetel satu variabel. */
    color: var(--ldk);
    animation: ldKeadaanMasuk .45s cubic-bezier(.22, 1, .36, 1) both;
}
@keyframes ldKeadaanMasuk { from { opacity: 0; transform: translateY(6px); } }

.ld-keadaan__ico {
    flex: none; display: grid; place-items: center;
    width: 46px; height: 46px; border-radius: 15px;
    background: color-mix(in srgb, var(--ldk) 13%, transparent);
}
.ld-keadaan__teks { min-width: 0; }
.ld-keadaan b { display: block; font-size: 14.5px; font-weight: 800; letter-spacing: -.01em; color: var(--ldk); }
.ld-keadaan p { margin: 5px 0 0; font-size: 12.5px; line-height: 1.62; color: #64748b; }

/* ── Bagian SVG: mewarisi --ldk, jadi satu set gaya melayani semua keadaan ── */
.ld-kico__halo { fill: color-mix(in srgb, var(--ldk) 12%, transparent); stroke: none; transform-origin: center; }
.ld-kico__ring,
.ld-kico__jarum,
.ld-kico__gagang,
.ld-kico__check { stroke: var(--ldk); stroke-width: 2.1; stroke-linecap: round; stroke-linejoin: round; fill: none; }
.ld-kico__body { stroke: var(--ldk); stroke-width: 2.1; fill: color-mix(in srgb, var(--ldk) 14%, transparent); }
.ld-kico__pin,
.ld-kico__pasir,
.ld-kico__play { fill: var(--ldk); stroke: none; }

/* Jam berdetak — jarum menitnya berputar penuh, jarum jamnya lambat. */
.ld-kico__jarum--m { transform-origin: 24px 24px; animation: ldPutar 6s linear infinite; }
.ld-kico__jarum--h { transform-origin: 24px 24px; animation: ldPutar 36s linear infinite; }
@keyframes ldPutar { to { transform: rotate(360deg); } }

/* Halo berdenyut pelan — menandakan proses masih hidup, bukan macet. */
.ld-keadaan .ld-kico__halo { animation: ldDenyut 2.8s ease-in-out infinite; }
@keyframes ldDenyut { 0%, 100% { opacity: .55; transform: scale(1); } 50% { opacity: 1; transform: scale(1.06); } }

/* Centang & gembok digambar sekali saat muncul. */
.ld-kico__check { stroke-dasharray: 22; stroke-dashoffset: 22; animation: ldGambar .7s .25s ease-out forwards; }
.ld-kico__gagang { stroke-dasharray: 30; stroke-dashoffset: 30; animation: ldGambar .6s .2s ease-out forwards; }
@keyframes ldGambar { to { stroke-dashoffset: 0; } }

/* Tombol putar berdenyut — satu-satunya keadaan yang meminta tindakan. */
.ld-kico__ring--denyut { animation: ldRing 2s ease-out infinite; transform-origin: center; }
@keyframes ldRing { 0% { transform: scale(.9); opacity: 1; } 100% { transform: scale(1.18); opacity: 0; } }

/* Butir pasir jatuh — proses yang berjalan tanpa perlu campur tangan. */
.ld-kico__pasir { animation: ldPasir 1.9s ease-in infinite; }
@keyframes ldPasir { 0% { transform: translateY(-6px); opacity: 0; } 30% { opacity: 1; } 100% { transform: translateY(8px); opacity: 0; } }

/* Pesawat kertas melaju & kembali — "sudah berangkat, sedang di jalan".
   Jejak garisnya digambar sekali, memakai animasi ldGambar yang sama. */
.ld-kico__pesawat { fill: var(--ldk); stroke: none; animation: ldTerbang 2.6s ease-in-out infinite; }
@keyframes ldTerbang {
    0%, 100% { transform: translate(0, 0); opacity: 1; }
    45% { transform: translate(3px, -3px); opacity: .85; }
}

/* ── Nada tiap keadaan: cukup satu variabel + latar ── */
.ld-keadaan.is-tunggu { --ldk: #475569; background: #f8fafc; border-color: #e6e9f0; }
/* TERKIRIM — biru laut: kabar baik yang sudah tuntas dari sisi kandidat,
   tapi belum jadi keputusan. Sengaja beda dari hijau "bisa dikerjakan"
   supaya tidak terbaca sebagai ajakan mengerjakan sesuatu lagi. */
.ld-keadaan.is-kirim  { --ldk: #0284c7; background: linear-gradient(135deg, rgba(14, 165, 233, .1), rgba(14, 165, 233, .03)); border-color: rgba(14, 165, 233, .3); }
.ld-keadaan.is-tinjau { --ldk: #7c3aed; background: linear-gradient(135deg, rgba(124, 58, 237, .09), rgba(124, 58, 237, .03)); border-color: rgba(124, 58, 237, .26); }
.ld-keadaan.is-jadwal { --ldk: #4f46e5; background: linear-gradient(135deg, rgba(99, 102, 241, .09), rgba(99, 102, 241, .03)); border-color: rgba(99, 102, 241, .28); }
.ld-keadaan.is-aksi   { --ldk: #059669; background: linear-gradient(135deg, rgba(16, 185, 129, .1), rgba(16, 185, 129, .03)); border-color: rgba(16, 185, 129, .3); }
.ld-keadaan.is-proses { --ldk: #b45309; background: linear-gradient(135deg, rgba(245, 158, 11, .1), rgba(245, 158, 11, .03)); border-color: rgba(245, 158, 11, .3); }

/* Ponsel: ikon mengecil & panel merapat, teks tetap terbaca penuh. */
@media (max-width: 520px) {
    .ld-keadaan { gap: 11px; padding: 14px; border-radius: 15px; }
    .ld-keadaan__ico { width: 38px; height: 38px; border-radius: 12px; }
    .ld-keadaan__ico svg { width: 25px; height: 25px; }
    .ld-keadaan b { font-size: 13.5px; }
    .ld-keadaan p { font-size: 12px; }
}

/* Gerak dihentikan bagi yang memintanya — animasi di sini hiasan, bukan isi. */
@media (prefers-reduced-motion: reduce) {
    .ld-keadaan,
    .ld-keadaan * { animation: none !important; }
    .ld-kico__check, .ld-kico__gagang { stroke-dashoffset: 0; }
}


/* ── Kartu keputusan tahap ─────────────────────────────────────────────── */
.ld-verdict {
    display: flex;
    align-items: center;
    gap: 14px;
    margin: 0 0 16px;
    padding: 16px 18px;
    border-radius: 18px;
    border: 1px solid;
}
.ld-verdict.is-lolos {
    --ldico: #059669;
    background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(16, 185, 129, 0.04));
    border-color: rgba(16, 185, 129, 0.3);
}
.ld-verdict.is-gugur {
    --ldico: #dc2626;
    background: linear-gradient(135deg, rgba(239, 68, 68, 0.09), rgba(239, 68, 68, 0.03));
    border-color: rgba(239, 68, 68, 0.28);
}
.ld-verdict.is-tunda {
    --ldico: #4338ca;
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.09), rgba(99, 102, 241, 0.03));
    border-color: rgba(99, 102, 241, 0.26);
}
.ld-verdict__eyebrow {
    font-size: 10.5px;
    font-weight: 800;
    letter-spacing: 0.1em;
    color: #94a3b8;
}
.ld-verdict__title {
    font-size: 16px;
    font-weight: 800;
    color: #0f172a;
    margin-top: 2px;
    letter-spacing: -0.01em;
}
.ld-verdict__text {
    margin: 3px 0 0;
    font-size: 12.8px;
    line-height: 1.55;
    color: #64748b;
}
.ld-verdict__badge {
    flex: 0 0 auto;
    align-self: flex-start;
    padding: 5px 11px;
    border-radius: 999px;
    font-size: 10.5px;
    font-weight: 800;
    letter-spacing: 0.06em;
    color: #fff;
    background: var(--ldico);
}
.ld-nico__cross .ld-nico__bang {
    stroke-width: 2.7;
    animation: ldGambarKecil 0.4s 0.5s cubic-bezier(0.4, 0, 0.2, 1) forwards;
    stroke-dasharray: 15;
    stroke-dashoffset: 15;
}
.ld-verdict-enter-active {
    transition:
        opacity 0.45s ease,
        transform 0.45s cubic-bezier(0.22, 1, 0.36, 1);
}
.ld-verdict-enter-from {
    opacity: 0;
    transform: translateY(-8px);
}

/* ── Kartu data kandidat ───────────────────────────────────────────────── */
.ld-profil {
    padding: 18px 20px 20px;
}
.ld-profil__head {
    display: flex;
    align-items: center;
    gap: 14px;
    padding-bottom: 15px;
    border-bottom: 1px solid #eef0f7;
}
.ld-profil__foto {
    flex: 0 0 auto;
    width: 62px;
    height: 62px;
    border-radius: 16px;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #8b5cf6, #6366f1);
    color: #fff;
    font-size: 22px;
    font-weight: 800;
    box-shadow: 0 10px 24px rgba(99, 102, 241, 0.28);
}
.ld-profil__foto img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    cursor: zoom-in;
}
.ld-profil__nama {
    font-size: 17px;
    font-weight: 800;
    color: #0f172a;
    letter-spacing: -0.015em;
}
.ld-profil__sub {
    font-size: 11.5px;
    color: #94a3b8;
    margin-top: 2px;
}
.ld-profil__grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
    gap: 2px 22px;
    margin-top: 4px;
}
.ld-profil__item {
    padding: 11px 0;
    border-bottom: 1px solid #f4f5fa;
    min-width: 0;
}
.ld-profil__lbl {
    display: block;
    font-size: 10.5px;
    font-weight: 800;
    letter-spacing: 0.08em;
    color: #a2a9ba;
    text-transform: uppercase;
}
.ld-profil__val {
    display: block;
    font-size: 13.5px;
    font-weight: 700;
    color: #1e293b;
    margin-top: 3px;
    word-break: break-word;
}

@media (max-width: 640px) {
    .ld-verdict {
        flex-wrap: wrap;
    }
    .ld-verdict__badge {
        order: -1;
    }
}

/* Hormati preferensi sistem: tanpa gerak, ikonnya tetap tampil utuh. */
@media (prefers-reduced-motion: reduce) {
    .ld-nico * {
        animation: none !important;
        stroke-dashoffset: 0 !important;
    }
    .ld-notice,
    .ld-notice:hover {
        transition: none;
        transform: none;
        box-shadow: none;
    }
}

.ld-btn-tes {
    margin: 16px 20px 20px;
    width: calc(100% - 40px);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 9px;
    padding: 14px;
    border: none;
    border-radius: 14px;
    font-family: inherit;
    font-size: 14px;
    font-weight: 800;
    cursor: pointer;
    color: #fff;
    background: linear-gradient(135deg, #8b5cf6, #6366f1);
    box-shadow: 0 12px 28px rgba(99, 102, 241, 0.3);
    transition: transform 0.16s;
}
.ld-btn-tes:hover:not(:disabled) {
    transform: translateY(-2px);
}
.ld-btn-tes:disabled {
    background: #e2e8f0;
    color: #94a3b8;
    cursor: not-allowed;
    box-shadow: none;
}

/* ── Layar antara menuju ruang ujian ─────────────────────────────────────── */
.ld-pindah { display: flex; align-items: center; gap: 14px; margin: 16px 20px 20px; padding: 15px 17px; border-radius: 16px; background: linear-gradient(135deg, rgba(99, 102, 241, .1), rgba(99, 102, 241, .03)); border: 1px solid rgba(99, 102, 241, .3); }
.ld-pindah__ring { position: relative; flex: none; display: grid; place-items: center; width: 44px; height: 44px; }
.ld-pindah__ring b { position: absolute; font-size: 14px; font-weight: 800; color: #4f46e5; font-variant-numeric: tabular-nums; }
.ld-pindah__jalur { stroke: rgba(99, 102, 241, .2); stroke-width: 3.5; }
/* Lingkaran menyusut selama tiga detik — hitungannya terlihat, bukan cuma angka. */
.ld-pindah__isi { stroke: #4f46e5; stroke-width: 3.5; stroke-linecap: round; stroke-dasharray: 119.4; transform: rotate(-90deg); transform-origin: center; animation: ldPindah 3s linear forwards; }
@keyframes ldPindah { from { stroke-dashoffset: 0; } to { stroke-dashoffset: 119.4; } }
.ld-pindah__teks { flex: 1; min-width: 0; }
.ld-pindah__teks b { display: block; font-size: 14px; font-weight: 800; color: #3730a3; }
.ld-pindah__teks p { margin: 3px 0 0; font-size: 12.5px; line-height: 1.55; color: #64748b; }
.ld-pindah__batal { flex: none; border: 1px solid #c7d2fe; background: #fff; color: #4f46e5; font-size: 12px; font-weight: 800; border-radius: 9px; padding: 7px 13px; cursor: pointer; }
.ld-pindah__batal:hover { background: #eef2ff; }
@media (max-width: 520px) {
    .ld-pindah { flex-wrap: wrap; margin-left: 14px; margin-right: 14px; }
    .ld-pindah__batal { width: 100%; }
}
@media (prefers-reduced-motion: reduce) { .ld-pindah__isi { animation: none; } }

/* Belum gilirannya — keterangan, bukan tombol mati. Nadanya netral: tidak ada
   yang salah, hanya belum saatnya. */
.ld-antre { margin: 16px 20px 20px; display: flex; align-items: flex-start; gap: 9px; padding: 13px 15px; border-radius: 14px; font-size: 13px; line-height: 1.55; color: #475569; background: #f1f5f9; border: 1px solid #e2e8f0; }
.ld-antre svg { flex: none; margin-top: 1px; color: #94a3b8; }
.ld-antre b { color: #334155; }

/* ── Jawaban baru terkirim, hasilnya belum sampai ──────────────────────────
   Menempati posisi tombol "Mulai Tes" supaya kandidat yang baru pulang dari
   ruang ujian tidak pernah melihat ajakan mengerjakannya lagi. Birunya sama
   dengan panel keadaan is-kirim — satu peristiwa, satu warna. */
.ld-terkirim { --ldt: #0284c7; margin: 16px 20px 20px; display: flex; align-items: flex-start; gap: 11px; padding: 13px 15px; border-radius: 14px; background: linear-gradient(135deg, rgba(14, 165, 233, .1), rgba(14, 165, 233, .03)); border: 1px solid rgba(14, 165, 233, .3); }
.ld-terkirim__ring { flex: 0 0 auto; margin-top: -2px; }
.ld-terkirim__ring svg { display: block; overflow: visible; }
.ld-terkirim__halo { fill: var(--ldt); opacity: .14; transform-origin: 22px 22px; animation: ldHalo 3.2s ease-in-out infinite; }
.ld-terkirim__pesawat { fill: var(--ldt); stroke: none; animation: ldTerbang 2.6s ease-in-out infinite; }
.ld-terkirim__teks { min-width: 0; }
.ld-terkirim b { display: block; font-size: 13.5px; font-weight: 800; color: #075985; }
.ld-terkirim p { margin: 4px 0 0; font-size: 12.5px; line-height: 1.6; color: #64748b; }

@media (max-width: 520px) {
    .ld-terkirim { margin: 14px 14px 18px; padding: 12px 13px; gap: 9px; }
    .ld-terkirim b { font-size: 13px; }
    .ld-terkirim p { font-size: 12px; }
}

/* Gerak hanyalah penekanan di sini — isinya tetap terbaca penuh tanpanya. */
@media (prefers-reduced-motion: reduce) {
    .ld-terkirim * { animation: none !important; }
}

/* ALUR SELEKSI · WATERFALL (gantt bertingkat) */
.ld-wf {
    padding: 18px 20px;
}
.ld-wf__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
    margin-bottom: 16px;
}
.ld-wf__legend {
    display: flex;
    align-items: center;
    gap: 14px;
}
.ld-wf__legend span {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 11.5px;
    font-weight: 700;
    color: #64748b;
}
.ld-wf__legend i {
    width: 10px;
    height: 10px;
    border-radius: 3px;
    display: inline-block;
}
.ld-wf__body {
    display: flex;
    flex-direction: column;
    gap: 12px;
}
.ld-wf__row,
.ld-wf__axis {
    display: grid;
    grid-template-columns: 220px 1fr;
    gap: 16px;
    align-items: center;
}
/* Sumbu tanggal (ruler) */
.ld-wf__axis {
    margin-bottom: 2px;
    padding-bottom: 8px;
    border-bottom: 1px solid #eef0f7;
}
.ld-wf__axisside {
    font-size: 10px;
    font-weight: 800;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    color: #aab2c5;
}
.ld-wf__axistrack {
    position: relative;
    height: 16px;
}
.ld-wf__tick {
    position: absolute;
    top: 0;
    transform: translateX(-50%);
    font-size: 10.5px;
    font-weight: 800;
    color: #94a3b8;
    white-space: nowrap;
    font-variant-numeric: tabular-nums;
}
.ld-wf__tick.is-last {
    transform: translateX(-100%);
}
.ld-wf__side {
    display: flex;
    align-items: center;
    gap: 11px;
    min-width: 0;
}
.ld-wf__node {
    flex: 0 0 auto;
    width: 30px;
    height: 30px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    font-weight: 800;
    background: #eef0f7;
    color: #94a3b8;
}
.ld-wf__node.is-done {
    background: linear-gradient(135deg, #8b5cf6, #6366f1);
    color: #fff;
}
.ld-wf__node.is-current {
    background: #fff;
    color: #b45309;
    border: 2px solid #f59e0b;
    box-shadow: 0 0 0 4px rgba(245, 158, 11, 0.16);
}
.ld-wf__node.is-fail {
    background: #fff;
    color: #dc2626;
    border: 2px solid #ef4444;
    box-shadow: 0 0 0 4px rgba(239, 68, 68, 0.14);
}
.ld-wf__name {
    font-size: 13.5px;
    font-weight: 800;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.ld-wf__st {
    font-size: 11px;
    font-weight: 700;
    margin-top: 1px;
}
.ld-wf__date {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    margin-top: 4px;
    font-size: 11px;
    font-weight: 700;
    color: #64748b;
    font-variant-numeric: tabular-nums;
}
.ld-wf__date.is-est {
    color: #a2a9ba;
    font-style: italic;
}
.ld-wf__date svg {
    flex: 0 0 auto;
}
.ld-wf__track {
    position: relative;
    height: 32px;
}
/* Garis vertikal ruler (continuous) selaras tick tanggal */
.ld-wf__grid {
    position: absolute;
    top: -6px;
    bottom: -6px;
    width: 1px;
    background: #eef0f7;
}
.ld-wf__bar {
    position: absolute;
    top: 4px;
    height: 24px;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(15, 23, 42, 0.08);
    display: flex;
    align-items: center;
    justify-content: flex-end;
    padding: 0 10px;
    transition:
        width 0.4s ease,
        margin-left 0.4s ease;
    z-index: 1;
}
.ld-wf__bar.is-empty {
    box-shadow: none;
}
.ld-wf__bar.is-empty .ld-wf__barlabel {
    color: #94a3b8;
    text-shadow: none;
}
.ld-wf__barlabel {
    font-size: 10.5px;
    font-weight: 800;
    color: #fff;
    white-space: nowrap;
    text-shadow: 0 1px 2px rgba(0, 0, 0, 0.25);
    font-variant-numeric: tabular-nums;
    letter-spacing: 0.02em;
}
.ld-wf__bar.is-glow {
    box-shadow: 0 6px 18px rgba(245, 158, 11, 0.4);
    animation: ldWfGlow 2s ease-in-out infinite;
}
@keyframes ldWfGlow {
    0%,
    100% {
        box-shadow: 0 6px 18px rgba(245, 158, 11, 0.35);
    }
    50% {
        box-shadow: 0 6px 24px rgba(245, 158, 11, 0.55);
    }
}

/* DETAIL LOWONGAN — info kaya */
.ld-info {
    padding: 18px 20px;
}
.ld-inforow {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
}
.ld-detailbtn {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    font-size: 12.5px;
    font-weight: 800;
    color: #4f46e5;
    text-decoration: none;
    padding: 8px 14px;
    border-radius: 11px;
    background: rgba(99, 102, 241, 0.09);
    border: 1px solid rgba(99, 102, 241, 0.18);
    transition: all 0.16s;
}
.ld-detailbtn:hover {
    background: rgba(99, 102, 241, 0.16);
    color: #4338ca;
}
.ld-info__desc {
    margin: 14px 0 0;
    font-size: 13.5px;
    line-height: 1.65;
    color: #64748b;
}
.ld-facts {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 16px;
}
.ld-fact {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    padding: 9px 14px;
    border-radius: 13px;
    background: #f6f7fb;
    border: 1px solid #eef0f7;
}
.ld-fact__ico {
    flex: 0 0 auto;
    display: flex;
}
.ld-fact__lbl {
    display: block;
    font-size: 10px;
    font-weight: 800;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: #a2a9ba;
}
.ld-fact__val {
    display: block;
    font-size: 13px;
    font-weight: 800;
    color: #1e293b;
    margin-top: 1px;
}
.ld-blk {
    margin-top: 20px;
}
.ld-blk__title {
    display: flex;
    align-items: center;
    gap: 9px;
    font-size: 13.5px;
    font-weight: 800;
    color: #1e293b;
    margin-bottom: 11px;
}
.ld-blk__ico {
    width: 26px;
    height: 26px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex: 0 0 auto;
}
.ld-blk__ico--green {
    background: rgba(16, 185, 129, 0.12);
    color: #059669;
}
.ld-blk__ico--amber {
    background: rgba(245, 158, 11, 0.14);
    color: #d97706;
}
.ld-blk__ico--indigo {
    background: rgba(99, 102, 241, 0.12);
    color: #6366f1;
}
.ld-list {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.ld-list li {
    display: flex;
    align-items: flex-start;
    gap: 9px;
    font-size: 13px;
    line-height: 1.55;
    color: #475569;
}
.ld-list--check li svg {
    flex: 0 0 auto;
    margin-top: 2px;
}
.ld-list--dot li span {
    flex: 0 0 auto;
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: #cbd2e0;
    margin-top: 7px;
}
.ld-skills {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}
.ld-skills span {
    font-size: 12px;
    font-weight: 700;
    color: #4f46e5;
    background: rgba(99, 102, 241, 0.1);
    border-radius: 9px;
    padding: 6px 12px;
}
.ld-benefits {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 9px 16px;
}
.ld-benefit {
    display: inline-flex;
    align-items: flex-start;
    gap: 8px;
    font-size: 13px;
    font-weight: 600;
    color: #334155;
}
.ld-benefit svg {
    flex: 0 0 auto;
    margin-top: 2px;
}

/* FORMULIR & BERKAS */
.ld-secrow { display: flex; align-items: center; justify-content: space-between; gap: 10px; margin-bottom: 12px; }
.ld-seccount { font-size: 11.5px; font-weight: 700; color: #8b93a7; background: #eef0f7; border-radius: 999px; padding: 4px 11px; }
.ld-forms { display: flex; flex-direction: column; gap: 12px; }
.ld-form { background: #fff; border: 1px solid #eef0f7; border-radius: 18px; overflow: hidden; box-shadow: 0 2px 8px rgba(15, 23, 42, 0.03); transition: border-color 0.2s; }
.ld-form.is-open { border-color: #d9def0; }
.ld-form__head { appearance: none; border: none; background: #fff; width: 100%; display: flex; align-items: center; gap: 13px; padding: 15px 18px; cursor: pointer; text-align: left; font-family: inherit; }
.ld-form.is-open .ld-form__head { background: #fbfbff; }
.ld-form__step { width: 34px; height: 34px; border-radius: 10px; background: linear-gradient(135deg, #cbd2e0, #94a3b8); color: #fff; font-size: 13px; font-weight: 800; display: flex; align-items: center; justify-content: center; flex: 0 0 auto; }
.ld-form__step.is-ok { background: linear-gradient(135deg, #8b5cf6, #6366f1); }
.ld-form__title { display: block; font-size: 14.5px; font-weight: 800; color: #1e293b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.ld-form__sub { display: block; font-size: 11.5px; color: #8b93a7; margin-top: 1px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.ld-form__pill { flex: 0 0 auto; font-size: 10.5px; font-weight: 800; padding: 4px 10px; border-radius: 999px; white-space: nowrap; }
.ld-form__pill.is-ok { background: rgba(16, 185, 129, 0.12); color: #059669; }
.ld-field__file { display: inline-flex; align-items: center; gap: 7px; max-width: 100%; padding: 5px 10px; margin-top: 2px; border: 1px solid #e6e8f2; border-radius: 9px; background: #fff; font: inherit; font-size: 13px; font-weight: 700; color: #1e293b; cursor: pointer; transition: border-color .16s, background .16s; }
.ld-field__file:hover { border-color: #a5b4fc; background: #f5f3ff; }
.ld-field__file .bi { flex: none; color: #dc2626; }
.ld-field__file .bi-file-earmark-image-fill { color: #6366f1; }
.ld-field__file span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.ld-field__file em { flex: none; font-style: normal; font-size: 11.5px; font-weight: 800; color: #4f46e5; }
.ld-form__pill.is-err { color: #dc2626; background: rgba(239, 68, 68, .1); }
.ld-form__pill.is-wait { background: rgba(245, 158, 11, 0.14); color: #b45309; }
.ld-form__chev { flex: 0 0 auto; transition: transform 0.26s; }
.ld-form.is-open .ld-form__chev { transform: rotate(180deg); }
.ld-form__body { padding: 6px 18px 18px; animation: ldAcc 0.28s cubic-bezier(0.22, 1, 0.36, 1) both; }
@keyframes ldAcc { from { opacity: 0; transform: translateY(-6px); } to { opacity: 1; transform: translateY(0); } }
/* DUA KOLOM HANYA BILA MUAT. `1fr 1fr` yang dipatok memaksa dua kolom sesempit
   apa pun layarnya — di ponsel, alamat dan uraian terjepit jadi kolom setipis
   dua kata. auto-fit + minmax menurunkannya sendiri jadi satu kolom. */
.ld-fields { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(220px, 100%), 1fr)); gap: 1px; background: #eef0f7; border: 1px solid #eef0f7; border-radius: 14px; overflow: hidden; margin-top: 8px; }
.ld-field { background: #fff; padding: 11px 14px; min-width: 0; }
.ld-field.is-panjang { grid-column: 1 / -1; }
/* ADA / TIDAK ADA — berkasnya sendiri ada di "Dokumen & Verifikasi" di bawah. */
.ld-badge { display: inline-flex; align-items: center; gap: 5px; margin-top: 3px; padding: 3px 9px; border-radius: 999px; font-size: 11.5px; font-weight: 800; }
.ld-badge.is-ada { color: #047857; background: rgba(16, 185, 129, .12); }
.ld-badge.is-kosong { color: #94a3b8; background: #f1f5f9; }
.ld-field__k { font-size: 10.5px; font-weight: 700; letter-spacing: 0.06em; color: #a2a9ba; text-transform: uppercase; }
.ld-field__v { font-size: 13.5px; font-weight: 700; color: #1e293b; margin-top: 3px; word-break: break-word; }

/* ── ISIAN BERULANG: tiap baris berdiri sendiri ──────────────────────────────
   Bernomor hanya bila lebih dari satu — nomor "1" tunggal cuma menambah bunyi
   pada baris yang sudah jelas berdiri sendirian. */
.ld-rows { display: flex; flex-direction: column; gap: 6px; margin-top: 5px; }
.ld-row { display: flex; gap: 8px; align-items: flex-start; background: #f8fafc; border: 1px solid #e8eef6; border-radius: 9px; padding: 7px 9px; }
.ld-row__no { flex: none; min-width: 18px; height: 18px; display: inline-flex; align-items: center; justify-content: center; background: #e0e7ff; color: #4338ca; border-radius: 999px; font-size: 10.5px; font-weight: 800; margin-top: 1px; }
.ld-row__isi { display: flex; flex-wrap: wrap; gap: 3px 14px; min-width: 0; }
.ld-row__p { font-size: 12.5px; font-weight: 600; color: #334155; min-width: 0; word-break: break-word; }
.ld-row__p b { display: block; font-size: 10px; font-weight: 800; letter-spacing: .03em; text-transform: uppercase; color: #94a3b8; }

@media (max-width: 640px) {
    .ld-row__isi { gap: 3px 10px; }
    .ld-row__p { font-size: 12px; }
}
.ld-docs { margin-top: 14px; display: flex; flex-direction: column; gap: 9px; }
.ld-docs__label { font-size: 11px; font-weight: 800; letter-spacing: 0.12em; color: #a2a9ba; }
.ld-doc { display: flex; align-items: center; gap: 13px; padding: 12px 14px; border: 1px solid #eef0f7; border-radius: 14px; background: #fbfbfe; }
.ld-doc__ico { width: 40px; height: 40px; border-radius: 11px; display: flex; align-items: center; justify-content: center; flex: 0 0 auto; }
.ld-doc__ico.is-img { background: rgba(99, 102, 241, 0.12); }
.ld-doc__ico.is-pdf { background: rgba(239, 68, 68, 0.1); }
.ld-doc__toprow { display: flex; align-items: center; gap: 8px; }
.ld-doc__name { font-size: 13.5px; font-weight: 800; color: #1e293b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.ld-doc__ext { font-size: 9px; font-weight: 800; letter-spacing: 0.06em; color: #8b93a7; background: #eef0f7; border-radius: 5px; padding: 2px 6px; flex: 0 0 auto; }
.ld-doc__desc { font-size: 11.5px; color: #8b93a7; margin-top: 2px; line-height: 1.4; }
.ld-doc__eye { appearance: none; border: 1px solid #d9def0; background: #fff; width: 38px; height: 38px; border-radius: 11px; display: flex; align-items: center; justify-content: center; cursor: pointer; color: #6366f1; flex: 0 0 auto; transition: all 0.16s; }
.ld-doc__eye:hover { background: #6366f1; color: #fff; border-color: #6366f1; transform: translateY(-1px); }

/* WATERFALL TAHAPAN (kiri, sticky) — garis penghubung mengalir ke bawah */
.ld-tl {
    background: rgba(255, 255, 255, 0.72);
    border: 1px solid rgba(226, 232, 240, 0.75);
    border-radius: 20px;
    padding: 20px 18px;
    position: sticky;
    top: 16px;
}
.ld-tl__list {
    position: relative;
    padding-left: 34px;
    display: flex;
    flex-direction: column;
    gap: 4px;
}
.ld-tl__item {
    position: relative;
    padding-bottom: 18px;
}
.ld-tl__item:last-child {
    padding-bottom: 0;
}
/* garis vertikal penghubung antar-node (waterfall) — warna mengikuti progres */
.ld-tl__item:not(:last-child)::before {
    content: '';
    position: absolute;
    left: -20px;
    top: 26px;
    bottom: -4px;
    width: 2.5px;
    border-radius: 3px;
    background: #eef0f7;
}
.ld-tl__item.is-done:not(:last-child)::before {
    background: linear-gradient(180deg, #8b5cf6, #a5b4fc);
}
.ld-tl__item.is-current:not(:last-child)::before {
    background: linear-gradient(180deg, #f59e0b, #eef0f7);
}
.ld-tl__item.is-fail:not(:last-child)::before {
    background: linear-gradient(180deg, #ef4444, #eef0f7);
}
.ld-tl__node {
    position: absolute;
    left: -34px;
    top: 0;
    width: 28px;
    height: 28px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #fff;
    border: 3px solid #cbd2e0;
    z-index: 1;
}
.ld-tl__node span {
    width: 9px;
    height: 9px;
    border-radius: 50%;
    display: block;
}
.ld-tl__lbl {
    font-size: 13px;
    font-weight: 800;
}
.ld-tl__meta {
    display: flex;
    align-items: center;
    gap: 7px;
    margin-top: 4px;
    flex-wrap: wrap;
}
.ld-tl__tag {
    font-size: 10px;
    font-weight: 800;
    padding: 2px 8px;
    border-radius: 6px;
    background: #f1f5f9;
    color: #64748b;
}
.ld-tl__tag--hcl {
    background: rgba(99, 102, 241, 0.1);
    color: #4f46e5;
}
.ld-tl__st {
    font-size: 10.5px;
    font-weight: 800;
    padding: 2px 8px;
    border-radius: 999px;
}

/* LIGHTBOX */
/* Lightbox HARUS di atas modal keputusan (.wca-modal-mask = 1200).
   Dulu 1090, sehingga pratinjau berkas yang dibuka DARI DALAM modal
   muncul di belakangnya — terlihat seperti tombolnya tidak berfungsi. */
.ld-lb {
    position: fixed;
    inset: 0;
    z-index: 1250;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 24px;
    background: rgba(10, 10, 20, 0.72);
    backdrop-filter: blur(6px);
    transition: opacity 0.28s;
    opacity: 0;
    pointer-events: none;
}
.ld-lb.is-on {
    opacity: 1;
    pointer-events: auto;
}
/* PDF butuh ruang baca; gambar tetap nyaman di lebar ini. */
.ld-lb__wrap.is-pdf {
    max-width: 900px;
}
.ld-lb__wrap {
    max-width: 520px;
    width: 100%;
    animation: ldPop 0.3s cubic-bezier(0.34, 1.56, 0.64, 1) both;
}
@keyframes ldPop {
    0% {
        opacity: 0;
        transform: scale(0.9);
    }
    60% {
        transform: scale(1.02);
    }
    100% {
        opacity: 1;
        transform: scale(1);
    }
}
.ld-lb__bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 14px;
}
.ld-lb__ico {
    width: 40px;
    height: 40px;
    border-radius: 11px;
    background: rgba(255, 255, 255, 0.14);
    display: flex;
    align-items: center;
    justify-content: center;
    flex: 0 0 auto;
}
.ld-lb__name {
    font-size: 15px;
    font-weight: 800;
    color: #fff;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.ld-lb__desc {
    font-size: 12px;
    color: rgba(255, 255, 255, 0.6);
}
.ld-lb__close {
    appearance: none;
    border: 1px solid rgba(255, 255, 255, 0.2);
    background: rgba(255, 255, 255, 0.08);
    width: 40px;
    height: 40px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    color: #fff;
    flex: 0 0 auto;
}
.ld-lb__close:hover {
    background: rgba(255, 255, 255, 0.18);
}
.ld-lb__card {
    background: #fff;
    border-radius: 18px;
    overflow: hidden;
    box-shadow: 0 30px 80px rgba(0, 0, 0, 0.5);
}
.ld-lb__card img {
    display: block;
    width: 100%;
    max-height: 420px;
    object-fit: contain;
    background: #0f172a;
}
.ld-lb__state {
    height: 280px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    background: linear-gradient(135deg, #f1f2f9, #e8eaf6);
    color: #64748b;
    font-size: 13.5px;
    font-weight: 700;
}
.ld-spin {
    width: 22px;
    height: 22px;
    border-radius: 50%;
    border: 2.6px solid rgba(99, 102, 241, 0.2);
    border-top-color: #6366f1;
    animation: ldSpin 0.7s linear infinite;
}
@keyframes ldSpin {
    to {
        transform: rotate(360deg);
    }
}
.ld-lb__foot {
    padding: 14px 18px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    border-top: 1px solid #eef0f7;
}
.ld-lb__pdf {
    display: block;
    width: 100%;
    height: min(74vh, 780px);
    border: 0;
    border-radius: 12px;
    background: #fff;
}
.ld-lb__ok {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
    font-weight: 800;
    color: #059669;
    flex: 0 0 auto;
}

/* TOAST */
/* z-index 1300 = DI ATAS modal EVO (.wca-modal-mask 1200), supaya toast tetap terbaca saat modal terbuka. */
/* ═══ PENAWARAN: kartu + dialog jawaban kandidat ═══ */
.ld-offer {
    margin: 16px 20px 20px;
    padding: 16px 18px;
    border-radius: 16px;
    border: 1px solid rgba(99, 102, 241, 0.3);
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.08), rgba(139, 92, 246, 0.04));
}
.ld-offer__head {
    display: flex;
    align-items: center;
    gap: 12px;
}
.ld-offer__ico {
    flex: none;
    width: 42px;
    height: 42px;
    border-radius: 13px;
    display: grid;
    place-items: center;
    font-size: 18px;
    color: #fff;
    background: linear-gradient(140deg, #818cf8, #6366f1);
}
.ld-offer__judul {
    font-size: 16px;
    font-weight: 800;
    color: #1e293b;
    margin-top: 2px;
}





/* Penawaran belum terbit — keadaan menunggu, bukan galat. */
.ld-offer__wait {
    display: flex;
    align-items: flex-start;
    gap: 11px;
    margin-top: 13px;
    padding: 13px 15px;
    border-radius: 13px;
    color: #92400e;
    background: rgba(245, 158, 11, 0.09);
    border: 1px solid rgba(245, 158, 11, 0.28);
}
.ld-offer__wait .bi {
    flex: none;
    font-size: 17px;
    margin-top: 1px;
}
.ld-offer__wait b {
    display: block;
    font-size: 13.5px;
    font-weight: 800;
}
.ld-offer__wait p {
    margin: 4px 0 0;
    font-size: 12.5px;
    line-height: 1.6;
    color: #475569;
}


@media (max-width: 560px) {
    .ld-offer {
        margin-left: 14px;
        margin-right: 14px;
    }
}

.ld-toast {
    position: fixed;
    bottom: 24px;
    right: 24px;
    z-index: 1300;
    display: flex;
    align-items: center;
    gap: 9px;
    padding: 12px 18px;
    border-radius: 13px;
    background: #0f172a;
    color: #fff;
    font-size: 13.5px;
    font-weight: 700;
    box-shadow: 0 18px 40px rgba(0, 0, 0, 0.3);
}
.ld-toast.is-err {
    background: #dc2626;
}
.ld-toast .bi {
    color: #34d399;
}
.ld-toast.is-err .bi {
    color: #fff;
}
.ld-toast-enter-active,
.ld-toast-leave-active {
    transition:
        opacity 0.25s,
        transform 0.25s;
}
.ld-toast-enter-from,
.ld-toast-leave-to {
    opacity: 0;
    transform: translateY(12px);
}

@media (max-width: 760px) {
    .ld-wf__row {
        grid-template-columns: 1fr;
        gap: 8px;
    }
    .ld-wf__track,
    .ld-wf__axis {
        display: none;
    }
}
@media (max-width: 640px) {
    .ld {
        padding: 20px 16px 40px;
    }
    .ld-cred,
    .ld-fields,
    .ld-benefits {
        grid-template-columns: 1fr;
    }
    .ld-hero__in {
        padding: 18px;
    }
    /* Kartu jadwal & hasil menempel lebih rapat ke tepi layar sempit —
       margin 20px membuat isinya tinggal separuh lebar di ponsel. */
    .ld-jdwwrap,
    .ld-mcu,
    .ld-keadaan {
        margin-left: 14px;
        margin-right: 14px;
    }
    .ld-cat__i {
        padding: 14px;
    }
    /* Indent 41px (selebar nomor tahap) tidak muat di ponsel: catatannya
       jadi kolom sempit yang setiap kalimatnya patah. */
    .ld-cat__note,
    .ld-cat__list,
    .ld-cat__berkas {
        margin-left: 0;
    }
}

/* ═══ FEEDBACK BANNER GLASSMORPHISM & CTA STYLING ═══ */
.ld-feedback-banner {
    position: relative;
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.95) 0%, rgba(254, 243, 199, 0.6) 100%);
    backdrop-filter: blur(14px);
    border: 1px solid rgba(245, 158, 11, 0.35);
    border-radius: 18px;
    padding: 18px 24px;
    margin-bottom: 20px;
    box-shadow:
        0 12px 32px rgba(245, 158, 11, 0.15),
        0 2px 8px rgba(0, 0, 0, 0.02);
    overflow: hidden;
    transition: all 0.3s ease;
}

.ld-feedback-banner--wajib {
    background: linear-gradient(135deg, rgba(236, 253, 245, 0.95) 0%, rgba(209, 250, 229, 0.7) 100%);
    border-color: rgba(16, 185, 129, 0.4);
    box-shadow: 0 12px 32px rgba(16, 185, 129, 0.15);
}

.ld-feedback-banner__glow {
    position: absolute;
    top: -50%;
    right: -20%;
    width: 200px;
    height: 200px;
    background: radial-gradient(circle, rgba(245, 158, 11, 0.25) 0%, transparent 70%);
    pointer-events: none;
}

.ld-feedback-banner__content {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    position: relative;
    z-index: 1;
    flex-wrap: wrap;
}

.ld-feedback-banner__left {
    display: flex;
    align-items: center;
    gap: 16px;
    flex: 1 1 320px;
}

.ld-feedback-banner__icon {
    width: 46px;
    height: 46px;
    border-radius: 14px;
    background: linear-gradient(135deg, #f59e0b 0%, #f43f5e 100%);
    color: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.3rem;
    flex-shrink: 0;
    box-shadow: 0 6px 18px rgba(245, 158, 11, 0.35);
}

.ld-feedback-banner__title {
    font-size: 1.05rem;
    font-weight: 800;
    color: #0f172a;
    margin: 0 0 4px;
    line-height: 1.3;
}

.ld-feedback-banner__sub {
    font-size: 0.85rem;
    color: #475569;
    margin: 0;
    line-height: 1.45;
}

.ld-feedback-banner__actions {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-shrink: 0;
}

.ld-feedback-banner__btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-family: inherit;
    font-size: 0.85rem;
    font-weight: 800;
    padding: 10px 20px;
    border-radius: 12px;
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    text-decoration: none;
    cursor: pointer;
}

.ld-feedback-banner__btn--fill {
    background: linear-gradient(135deg, #f59e0b 0%, #f43f5e 100%);
    color: #ffffff !important;
    border: none;
    box-shadow: 0 8px 20px rgba(245, 158, 11, 0.35);
    animation: fb-cta-pulse 2.2s ease-in-out infinite;
}

.ld-feedback-banner__btn--fill:hover {
    transform: translateY(-2px) scale(1.02);
    background: linear-gradient(135deg, #fbbf24 0%, #e11d48 100%);
    box-shadow: 0 12px 28px rgba(244, 63, 94, 0.45);
}

.ld-feedback-banner__btn--ghost {
    background: rgba(255, 255, 255, 0.8);
    color: #64748b;
    border: 1px solid #cbd5e1;
}

.ld-feedback-banner__btn--ghost:hover {
    background: #ffffff;
    color: #0f172a;
    border-color: #94a3b8;
}
</style>
