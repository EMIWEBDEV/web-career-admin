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
            <div v-if="showFeedbackBanner && feedbackPending" :class="['ld-feedback-banner', feedbackPending.Hasil_Akhir === 'DITERIMA' ? 'ld-feedback-banner--wajib' : 'ld-feedback-banner--optional']">
                <div class="ld-feedback-banner__content">
                    <div class="ld-feedback-banner__text">
                        <strong v-if="feedbackPending.Hasil_Akhir === 'DITERIMA'">Selamat! Mohon isi feedback untuk menyelesaikan proses.</strong>
                        <strong v-else>Bantu kami menjadi lebih baik dengan mengisi feedback.</strong>
                        <span>{{ feedbackPending.Hasil_Akhir === 'DITERIMA' ? 'Feedback wajib diisi.' : 'Hanya butuh 1-2 menit.' }}</span>
                    </div>
                    <div class="ld-feedback-banner__actions">
                        <a :href="feedbackPending.feedback_url" class="ld-feedback-banner__btn ld-feedback-banner__btn--fill">Isi Feedback →</a>
                        <button v-if="feedbackPending.Hasil_Akhir !== 'DITERIMA'" class="ld-feedback-banner__btn ld-feedback-banner__btn--ghost" @click="showFeedbackBanner = false">Nanti Saja</button>
                    </div>
                </div>
            </div>

            <Link href="/kandidat/portal" class="ld-back">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M11 18l-6-6 6-6" /></svg>
                Lamaran Saya
            </Link>

            <!-- ═══ HERO HEADER ═══ -->
            <div class="ld-hero">
                <span class="ld-hero__bar"></span>
                <div class="ld-hero__glow"></div>
                <div class="ld-hero__in">
                    <div class="ld-hero__avatar">
                        <svg v-if="lamaran.kategori === 'MT'" width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10L12 5 2 10l10 5 10-5z" /><path d="M6 12v5c3 3 9 3 12 0v-5" /></svg>
                        <svg v-else width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="7" width="18" height="13" rx="2" /><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" /></svg>
                    </div>
                    <div style="flex: 1; min-width: 0">
                        <div class="ld-hero__badges">
                            <span class="ld-chip" :style="katChipStyle">{{ katLabel(lamaran.kategori) }}</span>
                            <span class="ld-chip" :style="stChipStyle">
                                <span v-if="stKey === 'berjalan'" class="ld-pulse"></span>
                                <svg v-else-if="stKey === 'lolos'" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round"><path d="M20 6L9 17l-5-5" /></svg>
                                <svg v-else width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><circle cx="12" cy="12" r="9" /><path d="M15 9l-6 6M9 9l6 6" /></svg>
                                {{ stLabel(lamaran.status) }}
                            </span>
                        </div>
                        <h1 class="ld-hero__title">{{ lamaran.posisi || lamaran.program }}</h1>
                        <div class="ld-hero__meta">{{ lamaran.program }}<template v-if="lamaran.lokasi"> · {{ lamaran.lokasi }}</template> · Dilamar {{ fmtTanggal(lamaran.waktuLamar) }} · <span class="ld-mono">{{ lamaran.kode }}</span></div>
                    </div>
                </div>
            </div>

            <!-- ═══ BANNER STATUS ═══ -->
            <div class="ld-banner" :style="{ background: ST[stKey].bg, borderColor: ST[stKey].bg }">
                <span class="ld-banner__ico" :style="{ background: ST[stKey].dot }">
                    <svg v-if="stKey === 'lolos'" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M20 6L9 17l-5-5" /></svg>
                    <svg v-else-if="stKey === 'gugur'" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><circle cx="12" cy="12" r="9" /><path d="M15 9l-6 6M9 9l6 6" /></svg>
                    <svg v-else width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><circle cx="12" cy="12" r="9" /><path d="M12 7v5l3 2" /></svg>
                </span>
                <div style="min-width: 0">
                    <div class="ld-banner__title" :style="{ color: ST[stKey].c }">{{ banner.title }}</div>
                    <div class="ld-banner__text" :style="{ color: ST[stKey].c }">{{ banner.text }}</div>
                </div>
            </div>

            <!-- ═══ PROGRES SELEKSI ═══ -->
            <div class="ld-prog">
                <div class="ld-prog__head">
                    <span class="ld-seclabel">PROGRES SELEKSI</span>
                    <span style="font-size: 13px; font-weight: 800; color: #4f46e5">Tahap {{ posisiSekarang }} dari {{ totalTahap }} · {{ persen }}%</span>
                </div>
                <div class="ld-prog__bar"><div :style="{ width: persen + '%' }"></div></div>
                <div class="ld-steps">
                    <div v-for="(s, i) in heroSteps" :key="i" class="ld-step">
                        <div v-if="i > 0" class="ld-step__line" :style="{ background: s.line }"></div>
                        <div class="ld-step__node" :class="'is-' + s.st">
                            <svg v-if="s.st === 'done'" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5" /></svg>
                            <svg v-else-if="s.st === 'fail'" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round"><path d="M18 6L6 18M6 6l12 12" /></svg>
                            <template v-else>{{ i + 1 }}</template>
                        </div>
                        <div class="ld-step__lbl" :style="{ color: s.lbl }">{{ s.name }}</div>
                    </div>
                </div>
            </div>

            <div class="ld-mainc">
                    <!-- ── Tahap aktif: tes HCLearn ── -->
                    <div v-if="tahapTes" class="ld-card ld-act">
                        <div class="ld-act__head">
                            <span class="ld-act__ico">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10L12 5 2 10l10 5 10-5z" /><path d="M6 12v5c3 3 9 3 12 0v-5" /></svg>
                            </span>
                            <div style="min-width: 0">
                                <div class="ld-eyebrow">TAHAP AKTIF · TES ONLINE</div>
                                <div class="ld-act__title">{{ tahapTes.ujian?.namaUjian || tahapTes.label }}</div>
                                <div class="ld-act__sub">Tes ini diselenggarakan melalui platform HCLearn.</div>
                            </div>
                        </div>

                        <div v-if="tahapTes.butuhJadwal || !tahapTes.ujian?.terjadwal" class="ld-notice ld-notice--wait">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#d97706" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="flex: 0 0 auto"><path d="M5 3h14l-6 8v7l-2 1v-8L5 3z" /></svg>
                            <div><b>Menunggu penjadwalan</b><p>Tim rekrutmen belum menetapkan jadwal tes Anda. Token &amp; waktu pengerjaan akan muncul di sini setelah dijadwalkan.</p></div>
                        </div>

                        <template v-else>
                            <div class="ld-cred">
                                <div class="ld-cred__item">
                                    <span class="ld-cred__lbl">TOKEN AKSES</span>
                                    <span class="ld-cred__val ld-mono">{{ tahapTes.ujian.token || '—' }}</span>
                                </div>
                                <div class="ld-cred__item">
                                    <span class="ld-cred__lbl">KODE OTP</span>
                                    <span class="ld-cred__val ld-mono">{{ tahapTes.ujian.otp || '—' }}</span>
                                </div>
                                <div class="ld-cred__item">
                                    <span class="ld-cred__lbl">WAKTU MULAI</span>
                                    <span class="ld-cred__val">{{ fmtWaktu(tahapTes.ujian.waktuMulai) }}</span>
                                </div>
                                <div class="ld-cred__item">
                                    <span class="ld-cred__lbl">WAKTU BERAKHIR</span>
                                    <span class="ld-cred__val">{{ fmtWaktu(tahapTes.ujian.waktuSelesai) }}</span>
                                </div>
                            </div>

                            <div v-if="sudahSelesai(tahapTes)" class="ld-notice ld-notice--done">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#059669" stroke-width="2.4" stroke-linecap="round" style="flex: 0 0 auto"><path d="M20 6L9 17l-5-5" /></svg>
                                <div><b>Tes selesai dikerjakan</b><p v-if="tahapTes.ujian.nilai !== null && tahapTes.ujian.nilai !== undefined">Nilai Anda: <b>{{ tahapTes.ujian.nilai }}</b> — {{ tahapTes.ujian.kelulusan || 'menunggu keputusan' }}.</p></div>
                            </div>
                            <div v-else-if="belumMulai(tahapTes)" class="ld-notice ld-notice--wait">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#d97706" stroke-width="2.2" stroke-linecap="round" style="flex: 0 0 auto"><circle cx="12" cy="12" r="9" /><path d="M12 7v5l3 2" /></svg>
                                <div><b>Tes belum dibuka</b><p>Tombol akan aktif otomatis saat waktu mulai tiba{{ hitungMundur(tahapTes) ? ' — ' + hitungMundur(tahapTes) : '' }}.</p></div>
                            </div>
                            <div v-else-if="sudahLewat(tahapTes)" class="ld-notice ld-notice--err">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2.2" stroke-linecap="round" style="flex: 0 0 auto"><path d="M10.3 3.8L1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.8a2 2 0 0 0-3.4 0z" /><path d="M12 9v4M12 17h.01" /></svg>
                                <div><b>Waktu tes berakhir</b><p>Jendela pengerjaan sudah lewat. Hubungi tim rekrutmen bila ada kendala.</p></div>
                            </div>

                            <button class="ld-btn-tes" :disabled="!bisaAkses(tahapTes)" @click="bukaTes(tahapTes)">
                                <svg v-if="bisaAkses(tahapTes)" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h6v6M10 14L21 3M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6" /></svg>
                                <svg v-else width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><rect x="4" y="11" width="16" height="10" rx="2" /><path d="M8 11V7a4 4 0 0 1 8 0v4" /></svg>
                                {{ bisaAkses(tahapTes) ? 'Mulai Tes Sekarang' : 'Tes Terkunci' }}
                            </button>
                        </template>
                    </div>

                    <!-- ── Tahap aktif: formulir yang harus diisi ── -->
                    <div v-else-if="tugas && komponen" class="ld-card ld-act">
                        <div class="ld-act__head">
                            <span class="ld-act__ico">
                                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9" /><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z" /></svg>
                            </span>
                            <div style="min-width: 0">
                                <div class="ld-eyebrow">TAHAP AKTIF · FORMULIR</div>
                                <div class="ld-act__title">{{ tugas.label }}</div>
                                <div class="ld-act__sub">Lengkapi formulir berikut untuk melanjutkan proses.</div>
                            </div>
                        </div>
                        <div class="ld-act__form">
                            <component :is="komponen" v-model="jawaban" :konteks="konteks" label-kirim="Kirim & Lanjutkan" @kirim="kirim" @berkas="onBerkas" />
                        </div>
                    </div>

                    <!-- ── ALUR SELEKSI · WATERFALL (gantt bertingkat) ── -->
                    <div class="ld-card ld-wf">
                        <div class="ld-wf__head">
                            <div class="ld-sectitle">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#6366f1" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01" /></svg>
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
                                    <span v-for="(tk, i) in gantt.ticks" :key="i" class="ld-wf__tick" :class="{ 'is-last': i === gantt.ticks.length - 1 }" :style="{ left: tk.left + '%' }">{{ tk.label }}</span>
                                </div>
                            </div>

                            <div v-for="(w, i) in gantt.rows" :key="i" class="ld-wf__row">
                                <div class="ld-wf__side">
                                    <span class="ld-wf__node" :class="'is-' + w.state">
                                        <svg v-if="w.state === 'done'" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5" /></svg>
                                        <svg v-else-if="w.state === 'fail'" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round"><path d="M18 6L6 18M6 6l12 12" /></svg>
                                        <template v-else>{{ w.urutan }}</template>
                                    </span>
                                    <div style="min-width: 0">
                                        <div class="ld-wf__name" :style="{ color: w.state === 'todo' ? '#94a3b8' : '#1e293b' }">{{ w.name }}</div>
                                        <div class="ld-wf__st" :style="{ color: w.stColor }">{{ w.statusLabel }}</div>
                                        <div class="ld-wf__date" :class="{ 'is-est': w.est }">
                                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" /><path d="M3 10h18M8 2v4M16 2v4" /></svg>
                                            {{ w.dateText }}
                                        </div>
                                    </div>
                                </div>
                                <div class="ld-wf__track">
                                    <span v-for="(tk, gi) in gantt.ticks" :key="gi" class="ld-wf__grid" :style="{ left: tk.left + '%' }"></span>
                                    <span class="ld-wf__bar" :class="{ 'is-glow': w.glow, 'is-empty': w.state === 'todo' }" :style="{ marginLeft: w.left + '%', width: w.width + '%', background: w.bg }">
                                        <span class="ld-wf__barlabel">{{ w.barLabel }}</span>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ── Detail Lamaran: info kaya (+ tombol lihat detail lowongan) ── -->
                    <div class="ld-card ld-info">
                        <div class="ld-inforow">
                            <div class="ld-sectitle">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#6366f1" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="7" width="18" height="13" rx="2" /><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" /></svg>
                                Detail Lowongan
                            </div>
                            <a v-if="lowonganUrl" :href="lowonganUrl" target="_blank" rel="noopener" class="ld-detailbtn">
                                Lihat Selengkapnya
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M7 17L17 7M8 7h9v9" /></svg>
                            </a>
                        </div>

                        <p v-if="ringkasan" class="ld-info__desc">{{ ringkasan }}</p>

                        <!-- Fakta cepat (tipe kerja / tempat kerja / lokasi / pengalaman·durasi) -->
                        <div class="ld-facts">
                            <span v-for="f in facts" :key="f.t" class="ld-fact">
                                <span class="ld-fact__ico" v-html="f.icon"></span>
                                <span><span class="ld-fact__lbl">{{ f.t }}</span><span class="ld-fact__val">{{ f.v }}</span></span>
                            </span>
                        </div>

                        <!-- Tanggung Jawab -->
                        <div v-if="tanggungJawab.length" class="ld-blk">
                            <div class="ld-blk__title"><span class="ld-blk__ico ld-blk__ico--green"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01" /></svg></span> Tanggung Jawab</div>
                            <ul class="ld-list ld-list--check">
                                <li v-for="(t, i) in tanggungJawab" :key="i"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5" /></svg>{{ t }}</li>
                            </ul>
                        </div>

                        <!-- Persyaratan -->
                        <div v-if="persyaratan.length" class="ld-blk">
                            <div class="ld-blk__title"><span class="ld-blk__ico ld-blk__ico--amber"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3L22 4" /><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11" /></svg></span> Persyaratan</div>
                            <ul class="ld-list ld-list--dot">
                                <li v-for="(t, i) in persyaratan" :key="i"><span></span>{{ t }}</li>
                            </ul>
                        </div>

                        <!-- Skill -->
                        <div v-if="skill.length" class="ld-blk">
                            <div class="ld-blk__title"><span class="ld-blk__ico ld-blk__ico--indigo"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l1.9 5.1L19 10l-5.1 1.9L12 17l-1.9-5.1L5 10l5.1-1.9z" /></svg></span> Skill yang Dibutuhkan</div>
                            <div class="ld-skills"><span v-for="s in skill" :key="s">{{ s }}</span></div>
                        </div>

                        <!-- Benefit -->
                        <div v-if="benefit.length" class="ld-blk">
                            <div class="ld-blk__title"><span class="ld-blk__ico ld-blk__ico--green"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="8" width="18" height="4" rx="1" /><path d="M12 8v13M5 12v7a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-7" /><path d="M12 8a3 3 0 1 0-3-3c0 2 3 3 3 3zM12 8a3 3 0 1 1 3-3c0 2-3 3-3 3z" /></svg></span> Benefit &amp; Fasilitas</div>
                            <div class="ld-benefits">
                                <span v-for="(b, i) in benefit" :key="i" class="ld-benefit"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#059669" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5" /></svg>{{ b }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- ── Formulir & Berkas Saya ── -->
                    <div v-if="formulir.length">
                        <div class="ld-secrow">
                            <div class="ld-sectitle">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#6366f1" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7a2 2 0 0 1 2-2h4l2 2h6a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H4z" /></svg>
                                Formulir &amp; Berkas Saya
                            </div>
                            <span class="ld-seccount">{{ formulir.length }} formulir</span>
                        </div>
                        <div class="ld-forms">
                            <div v-for="(f, fi) in formulir" :key="f.no" class="ld-form" :class="{ 'is-open': openForm === fi }">
                                <button type="button" class="ld-form__head" @click="openForm = openForm === fi ? -1 : fi">
                                    <span class="ld-form__step" :class="{ 'is-ok': !!f.waktuKirim }">{{ String(fi + 1).padStart(2, '0') }}</span>
                                    <span style="flex: 1; min-width: 0">
                                        <span class="ld-form__title">{{ f.label }}</span>
                                        <span class="ld-form__sub">{{ f.sumber === 'PENDAFTARAN' ? 'Pendaftaran' : 'Tahap ' + (f.urutan || '—') }} · {{ f.jawaban.length }} isian<template v-if="f.berkas.length"> · {{ f.berkas.length }} berkas</template></span>
                                    </span>
                                    <span class="ld-form__pill" :class="f.waktuKirim ? 'is-ok' : 'is-wait'">{{ f.waktuKirim ? 'Lengkap' : 'Menunggu' }}</span>
                                    <svg class="ld-form__chev" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="2.4" stroke-linecap="round"><path d="M6 9l6 6 6-6" /></svg>
                                </button>
                                <div v-if="openForm === fi" class="ld-form__body">
                                    <div v-if="f.jawaban.length" class="ld-fields">
                                        <div v-for="j in f.jawaban" :key="j.key" class="ld-field">
                                            <div class="ld-field__k">{{ j.label }}</div>
                                            <div class="ld-field__v">{{ j.nilai || '—' }}</div>
                                        </div>
                                    </div>
                                    <div v-if="f.berkas.length" class="ld-docs">
                                        <div class="ld-docs__label">DOKUMEN &amp; VERIFIKASI</div>
                                        <div v-for="b in f.berkas" :key="b.field" class="ld-doc">
                                            <span class="ld-doc__ico" :class="b.isImage ? 'is-img' : 'is-pdf'">
                                                <svg v-if="b.isImage" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#6366f1" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" /><circle cx="8.5" cy="8.5" r="1.5" /><path d="M21 15l-5-5L5 21" /></svg>
                                                <svg v-else width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" /><path d="M14 2v6h6" /></svg>
                                            </span>
                                            <div style="flex: 1; min-width: 0">
                                                <div class="ld-doc__toprow">
                                                    <span class="ld-doc__name">{{ b.nama }}</span>
                                                    <span class="ld-doc__ext">{{ (b.ext || '').toUpperCase() }}</span>
                                                </div>
                                                <div class="ld-doc__desc">{{ b.field === 'foto_verifikasi' ? 'Foto verifikasi identitas yang kamu unggah.' : 'Berkas ' + b.field.replace(/[_-]/g, ' ') + ' · ' + ukuran(b.ukuran) }}</div>
                                            </div>
                                            <button type="button" class="ld-doc__eye" title="Lihat berkas" @click="bukaDok(b)">
                                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z" /><circle cx="12" cy="12" r="3" /></svg>
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
            <div v-if="lightbox" class="ld-lb__wrap" @click.stop>
                <div class="ld-lb__bar">
                    <div style="display: flex; align-items: center; gap: 11px; color: #fff; min-width: 0">
                        <span class="ld-lb__ico"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" /><circle cx="8.5" cy="8.5" r="1.5" /><path d="M21 15l-5-5L5 21" /></svg></span>
                        <div style="min-width: 0"><div class="ld-lb__name">{{ lightbox.nama }}</div><div class="ld-lb__desc">{{ lightbox.field === 'foto_verifikasi' ? 'Foto verifikasi identitas' : 'Pratinjau berkas' }}</div></div>
                    </div>
                    <button type="button" class="ld-lb__close" @click="lightbox = null"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M18 6L6 18M6 6l12 12" /></svg></button>
                </div>
                <div class="ld-lb__card">
                    <div v-if="lbLoading" class="ld-lb__state"><span class="ld-spin"></span> Memuat berkas…</div>
                    <div v-else-if="lbError" class="ld-lb__state"><svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#f87171" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="9" /><path d="M12 8v5M12 16h.01" /></svg> Gagal memuat berkas.</div>
                    <img v-show="!lbLoading && !lbError" :src="lightbox.url" :alt="lightbox.nama" @load="lbLoading = false" @error="lbLoading = false; lbError = true" />
                    <div class="ld-lb__foot">
                        <span style="font-size: 12px; color: #8b93a7">Berkas milik akunmu · pratinjau aman</span>
                        <span v-if="lightbox.status === 'TERVERIFIKASI'" class="ld-lb__ok"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M20 6L9 17l-5-5" /></svg> Terverifikasi</span>
                    </div>
                </div>
            </div>
        </div>

        <transition name="ld-toast"><div v-if="toast" class="ld-toast" :class="{ 'is-err': toastErr }"><i class="bi" :class="toastErr ? 'bi-exclamation-circle-fill' : 'bi-check-circle-fill'"></i> {{ toast }}</div></transition>
    </div>
</template>

<script>
import axios from 'axios';
import { Head, Link, router } from '@inertiajs/vue3';
import { komponenFormulir, skemaFormulir, jawabanAwal } from '@career/formulir';

const CFG = { headers: { Accept: 'application/json' } };
const ST = {
    berjalan: { c: '#b45309', bg: 'rgba(245,158,11,.14)', dot: '#f59e0b' },
    lolos: { c: '#059669', bg: 'rgba(16,185,129,.12)', dot: '#10b981' },
    gugur: { c: '#dc2626', bg: 'rgba(239,68,68,.1)', dot: '#ef4444' },
};
// Ikon fakta cepat (stroke) — dipakai kartu Detail Lowongan.
const SVG = (path) => `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#8b5cf6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">${path}</svg>`;
const P = {
    koper: '<rect x="3" y="7" width="18" height="13" rx="2"/><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>',
    build: '<path d="M3 21h18M6 21V7l6-4 6 4v14"/><path d="M9 9h.01M15 9h.01M9 13h.01M15 13h.01"/>',
    pin: '<path d="M12 21s-7-6-7-11a7 7 0 0 1 14 0c0 5-7 11-7 11z"/><circle cx="12" cy="10" r="2.5"/>',
    jam: '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
    level: '<path d="M4 20h4V10H4zM10 20h4V4h-4zM16 20h4v-8h-4z"/>',
    topi: '<path d="M22 10L12 5 2 10l10 5 10-5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/>',
    doc: '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/>',
};

export default {
    components: { Head, Link },
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
            mengirim: false,
            openForm: 0,
            lightbox: null,
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
        // [feat/feedback]
        feedbackPending() { return this.$page.props.feedbackPending; },
        komponen() { return this.tugas ? komponenFormulir(this.tugas.komponen) : null; },
        totalTahap() { return this.lamaran.totalTahap || this.tahap.length || 0; },
        stKey() { return { BERJALAN: 'berjalan', LULUS: 'lolos', GUGUR: 'gugur' }[this.lamaran.status] || 'berjalan'; },
        katChipStyle() {
            return this.lamaran.kategori === 'MT'
                ? { background: 'rgba(245,158,11,.14)', color: '#b45309' }
                : this.lamaran.kategori === 'INTERNSHIP'
                    ? { background: 'rgba(16,185,129,.12)', color: '#059669' }
                    : { background: 'rgba(99,102,241,.12)', color: '#4f46e5' };
        },
        stChipStyle() { const t = ST[this.stKey]; return { background: t.bg, color: t.c }; },
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
        tahapTes() { return this.tahap.find((t) => t.status === 'BERJALAN' && t.provider === 'THIRD_PARTY') || null; },
        banner() {
            const cur = this.tahap.find((t) => t.status === 'BERJALAN');
            if (this.stKey === 'lolos') return { title: 'Selamat, kamu diterima! 🎉', text: 'Seluruh tahap seleksi telah kamu selesaikan. Tim rekrutmen akan menghubungimu.' };
            if (this.stKey === 'gugur') return { title: 'Belum lolos pada tahap ini', text: this.lamaran.alasanGugur || 'Terima kasih atas partisipasimu. Jangan menyerah — banyak peluang lain menantimu.' };
            return { title: 'Lamaranmu sedang diproses', text: cur ? `Saat ini di tahap ${cur.label}. Pantau halaman ini untuk perkembangannya.` : 'Pantau halaman ini untuk perkembangan seleksimu.' };
        },
        k() { return this.kartu || {}; },
        ringkasan() { return this.k.ringkasan || this.k.deskripsi || ''; },
        tanggungJawab() { return this.k.tanggungJawab || []; },
        persyaratan() { return this.k.persyaratan || []; },
        skill() { return this.k.skill || []; },
        benefit() { return [...(this.k.benefit || []), ...(this.k.fasilitas || [])]; },
        // Fakta cepat — adaptif MT vs rekrutmen.
        facts() {
            const k = this.k;
            const out = [];
            const push = (t, v, icon) => { if (v) out.push({ t, v, icon: SVG(icon) }); };
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
            const src = this.tahap.length ? this.tahap : Array.from({ length: this.totalTahap }, (_, i) => ({ urutan: i + 1, label: `Tahap ${i + 1}`, status: 'MENUNGGU' }));
            return src.map((t) => {
                const st = t.status === 'SELESAI' ? (t.hasil === 'GUGUR' ? 'fail' : 'done')
                    : t.status === 'BERJALAN' ? (this.lamaran.status === 'GUGUR' ? 'fail' : 'current') : 'todo';
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
            const DAY = 86400000, DEF = 3, GAP = 1;
            const parse = (v) => { if (!v) return null; const d = new Date(String(v).replace(' ', 'T')); return Number.isNaN(d.getTime()) ? null : d.getTime(); };
            const applyMs = parse(this.lamaran.waktuLamar) || Date.now();
            const bgOf = (s) => s === 'done' ? 'linear-gradient(90deg,#8b5cf6,#6366f1)' : s === 'current' ? 'linear-gradient(90deg,#fbbf24,#f59e0b)' : s === 'fail' ? 'linear-gradient(90deg,#f87171,#ef4444)' : '#dfe4ee';
            const stColorOf = (s) => s === 'done' ? '#6366f1' : s === 'current' ? '#b45309' : s === 'fail' ? '#dc2626' : '#94a3b8';

            let cursor = applyMs;
            const rows = this.tahap.map((t) => {
                const state = this.tlState(t);
                const rg = this.tglTahap(t);
                let start = parse(rg.start), end = parse(rg.end), est = false;
                if (start === null && end === null) { est = true; start = cursor; end = start + DEF * DAY; }
                else if (start === null) { start = end - DEF * DAY; }
                else if (end === null) { end = start + DEF * DAY; }
                if (est && start < cursor) { start = cursor; end = start + DEF * DAY; }
                cursor = Math.max(cursor, end) + GAP * DAY;
                return { urutan: t.urutan, name: t.label, statusLabel: this.stepStatus(t), state, start, end, est };
            });
            if (!rows.length) return { rows: [], ticks: [] };

            const min = Math.min(...rows.map((r) => r.start));
            const max = Math.max(...rows.map((r) => r.end));
            const span = Math.max(DAY, max - min);
            rows.forEach((r) => {
                r.left = +((r.start - min) / span * 100).toFixed(2);
                r.width = Math.max(6, +((r.end - r.start) / span * 100).toFixed(2));
                r.bg = bgOf(r.state);
                r.stColor = stColorOf(r.state);
                r.glow = r.state === 'current';
                r.dateText = (r.est ? '± ' : '') + this.fmtRangeMs(r.start, r.end);
                r.barLabel = this.fmtMs(r.end, true);
            });

            const N = 4;
            const ticks = [];
            for (let i = 0; i <= N; i++) ticks.push({ left: (i / N) * 100, label: this.fmtMs(min + span * i / N, true) });
            return { rows, ticks };
        },
    },
    mounted() {
        if (this.tugas) this.jawaban = jawabanAwal(skemaFormulir(this.tugas.komponen), this.profil);
        this.jam = setInterval(() => { this.now = Date.now(); }, 15000);
    },
    beforeUnmount() {
        if (this.jam) clearInterval(this.jam);
        if (this.tm) clearTimeout(this.tm);
    },
    methods: {
        katLabel(k) { return { REKRUTMEN: 'Rekrutmen', MT: 'Management Trainee', INTERNSHIP: 'Internship' }[k] || k || '—'; },
        stLabel(s) { return { BERJALAN: 'Berjalan', LULUS: 'Diterima', GUGUR: 'Tidak Lolos' }[s] || s; },
        stepStatus(t) {
            if (t.status === 'SELESAI') return t.hasil === 'GUGUR' ? 'Gugur' : 'Lulus';
            if (t.status === 'BERJALAN') return 'Berlangsung';
            return 'Menunggu';
        },
        // Timeline node styling (design).
        tlState(t) {
            if (t.status === 'SELESAI') return t.hasil === 'GUGUR' ? 'fail' : 'done';
            if (t.status === 'BERJALAN') return this.lamaran.status === 'GUGUR' ? 'fail' : 'current';
            return 'todo';
        },
        tlTodo(t) { return t.status !== 'SELESAI' && t.status !== 'BERJALAN'; },
        tlCol(t) {
            if (t.status === 'SELESAI') return t.hasil === 'GUGUR' ? '#ef4444' : '#10b981';
            if (t.status === 'BERJALAN') return this.lamaran.status === 'GUGUR' ? '#ef4444' : '#f59e0b';
            return '#cbd2e0';
        },
        tlNode(t) { return { borderColor: this.tlCol(t), animation: t.status === 'BERJALAN' && this.lamaran.status !== 'GUGUR' ? 'ldPulse 2s infinite' : 'none' }; },
        tlTagStyle(t) {
            if (t.status === 'SELESAI') return t.hasil === 'GUGUR' ? { background: 'rgba(239,68,68,.1)', color: '#dc2626' } : { background: 'rgba(16,185,129,.12)', color: '#059669' };
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
            return d.toLocaleDateString('id-ID', short ? { day: '2-digit', month: 'short' } : { day: '2-digit', month: 'short', year: 'numeric' });
        },
        fmtMs(ms, short) {
            const d = new Date(ms);
            if (Number.isNaN(d.getTime())) return '';
            return d.toLocaleDateString('id-ID', short ? { day: '2-digit', month: 'short' } : { day: '2-digit', month: 'short', year: 'numeric' });
        },
        fmtRangeMs(a, b) {
            const da = this.fmtMs(a), db = this.fmtMs(b);
            return da === db ? da : `${this.fmtMs(a, true)} – ${db}`;
        },
        ukuran(b) {
            if (!b) return '';
            return b > 1048576 ? (b / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(b / 1024)) + ' KB';
        },
        bukaDok(b) {
            if (b.isImage) { this.lightbox = b; this.lbLoading = true; this.lbError = false; }
            else window.open(b.url, '_blank', 'noopener');
        },
        // ── Gerbang waktu tes (fungsi asli, dipertahankan) ──
        belumMulai(t) { return t.ujian?.waktuMulai ? this.now < new Date(t.ujian.waktuMulai).getTime() : false; },
        sudahLewat(t) { return t.ujian?.waktuSelesai ? this.now > new Date(t.ujian.waktuSelesai).getTime() : false; },
        sudahSelesai(t) { return t.ujian?.statusPengerjaan === 'selesai'; },
        bisaAkses(t) {
            const u = t.ujian;
            if (!u || !u.link || this.sudahSelesai(t)) return false;
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
        bukaTes(t) { if (this.bisaAkses(t)) window.open(t.ujian.link, '_blank', 'noopener'); },
        fmtWaktu(iso) {
            if (!iso) return '—';
            return new Date(iso).toLocaleString('id-ID', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
        },
        fmtTanggal(v) {
            if (!v) return '—';
            const d = new Date(String(v).replace(' ', 'T'));
            return Number.isNaN(d.getTime()) ? '—' : d.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
        },
        onBerkas(e) {
            if (e.galat) { this.notice(e.galat, true); return; }
            if (e.file) this.berkas[e.field.key] = e.file;
        },
        async kirim(nilai) {
            if (this.mengirim || !this.tugas) return;
            this.mengirim = true;
            try {
                const res = await axios.post(`/api/v1/lamaran/tahap/${this.tugas.tahapId}/kirim`, { jawaban: nilai }, CFG);
                this.notice(res.data?.message || 'Formulir terkirim.');
                setTimeout(() => router.reload(), 800);
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal mengirim formulir.', true);
            } finally {
                this.mengirim = false;
            }
        },
        notice(x, err = false) { this.toast = x; this.toastErr = err; if (this.tm) clearTimeout(this.tm); this.tm = setTimeout(() => (this.toast = ''), 4000); },
    },
};
</script>

<style scoped>
.ld-mono { font-family: 'JetBrains Mono', 'Courier New', monospace; font-weight: 700; color: #8b93a7; }
.ld-seclabel { font-size: 12px; font-weight: 800; letter-spacing: 0.14em; color: #8b93a7; }
.ld-eyebrow { font-size: 11px; font-weight: 800; letter-spacing: 0.14em; color: #8b5cf6; }
.ld-sectitle { display: flex; align-items: center; gap: 9px; font-size: 15px; font-weight: 800; color: #1e293b; }

/* overflow-x: clip mengeklip blob horizontal TANPA menjadikan .ld scroll
   container (hidden akan memaksa overflow-y:auto → scrollbar ganda). */
.ld { position: relative; margin: -1rem; padding: 26px 30px 44px; min-height: calc(100vh - 68px); overflow-x: clip; }
.ld-blob { position: absolute; border-radius: 50%; filter: blur(8px); pointer-events: none; z-index: 0; }
.ld-blob--a { top: -120px; right: 10%; width: 420px; height: 420px; background: radial-gradient(circle at 30% 30%, rgba(139, 92, 246, 0.13), rgba(139, 92, 246, 0) 70%); animation: ldFloatA 16s ease-in-out infinite; }
.ld-blob--b { bottom: -160px; left: 6%; width: 440px; height: 440px; background: radial-gradient(circle at 60% 40%, rgba(99, 102, 241, 0.1), rgba(99, 102, 241, 0) 70%); animation: ldFloatB 19s ease-in-out infinite; }
@keyframes ldFloatA { 0%, 100% { transform: translate(0, 0); } 50% { transform: translate(28px, -22px); } }
@keyframes ldFloatB { 0%, 100% { transform: translate(0, 0); } 50% { transform: translate(-24px, 20px); } }
/* Fluid: mengisi penuh lebar shell-content (bukan container terpusat). */
.ld-wrap { position: relative; z-index: 2; width: 100%; }

.ld-back { display: inline-flex; align-items: center; gap: 7px; font-size: 13px; font-weight: 700; color: #64748b; text-decoration: none; margin-bottom: 14px; transition: color 0.16s; }
.ld-back:hover { color: #4f46e5; }

/* HERO */
.ld-hero { position: relative; border-radius: 24px; overflow: hidden; background: #fff; border: 1px solid #eef0f7; box-shadow: 0 12px 34px rgba(15, 23, 42, 0.07); animation: ldRise 0.5s cubic-bezier(0.22, 1, 0.36, 1) both; }
@keyframes ldRise { from { opacity: 0; transform: translateY(14px); } to { opacity: 1; transform: translateY(0); } }
.ld-hero__bar { position: absolute; left: 0; top: 0; bottom: 0; width: 5px; background: linear-gradient(180deg, #8b5cf6, #6366f1); }
.ld-hero__glow { position: absolute; top: -70px; right: -40px; width: 240px; height: 240px; border-radius: 50%; background: radial-gradient(circle at 50% 50%, rgba(99, 102, 241, 0.08), rgba(99, 102, 241, 0) 70%); pointer-events: none; }
.ld-hero__in { position: relative; display: flex; align-items: flex-start; gap: 16px; padding: 22px 26px 22px 28px; }
.ld-hero__avatar { width: 52px; height: 52px; border-radius: 15px; background: linear-gradient(135deg, #8b5cf6, #6366f1); color: #fff; display: flex; align-items: center; justify-content: center; flex: 0 0 auto; box-shadow: 0 10px 24px rgba(99, 102, 241, 0.28); }
.ld-hero__badges { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.ld-chip { display: inline-flex; align-items: center; gap: 6px; padding: 5px 11px; border-radius: 999px; font-size: 11px; font-weight: 800; }
.ld-pulse { width: 8px; height: 8px; border-radius: 50%; background: #f59e0b; animation: ldPulse 2s infinite; }
@keyframes ldPulse { 0%, 100% { box-shadow: 0 0 0 0 rgba(245, 158, 11, 0.5); } 70% { box-shadow: 0 0 0 8px rgba(245, 158, 11, 0); } }
.ld-hero__title { margin: 11px 0 0; font-size: 23px; font-weight: 800; color: #0f172a; letter-spacing: -0.02em; }
.ld-hero__meta { font-size: 12.5px; color: #8792a6; margin-top: 5px; }

/* BANNER */
.ld-banner { display: flex; gap: 13px; align-items: center; margin-top: 16px; padding: 15px 17px; border-radius: 16px; border: 1px solid transparent; }
.ld-banner__ico { width: 38px; height: 38px; border-radius: 11px; flex: 0 0 auto; display: flex; align-items: center; justify-content: center; color: #fff; }
.ld-banner__title { font-size: 14px; font-weight: 800; }
.ld-banner__text { font-size: 12.5px; line-height: 1.55; opacity: 0.85; margin-top: 2px; }

/* PROGRES */
.ld-prog { margin-top: 16px; background: rgba(255, 255, 255, 0.72); border: 1px solid rgba(226, 232, 240, 0.75); border-radius: 20px; padding: 16px 20px; box-shadow: 0 6px 20px rgba(15, 23, 42, 0.04); }
.ld-prog__head { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 12px; flex-wrap: wrap; }
.ld-prog__head .ld-seclabel { letter-spacing: 0.1em; }
.ld-prog__bar { height: 7px; border-radius: 99px; background: #eef0f7; overflow: hidden; margin-bottom: 16px; }
.ld-prog__bar div { height: 100%; border-radius: 99px; background: linear-gradient(90deg, #8b5cf6, #6366f1); transition: width 0.4s ease; }
.ld-steps { display: flex; align-items: flex-start; overflow-x: auto; padding-bottom: 6px; }
.ld-step { flex: 0 0 120px; display: flex; flex-direction: column; align-items: center; position: relative; }
.ld-step__line { position: absolute; top: 14px; left: -50%; width: 100%; height: 3px; }
.ld-step__node { position: relative; z-index: 1; width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 800; background: #eef0f7; color: #94a3b8; }
.ld-step__node.is-done { background: linear-gradient(135deg, #8b5cf6, #6366f1); color: #fff; }
.ld-step__node.is-current { background: #fff; color: #4f46e5; border: 2px solid #f59e0b; box-shadow: 0 0 0 4px rgba(245, 158, 11, 0.18); }
.ld-step__node.is-fail { background: #fff; color: #dc2626; border: 2px solid #ef4444; box-shadow: 0 0 0 4px rgba(239, 68, 68, 0.14); }
.ld-step__lbl { margin-top: 9px; font-size: 10.5px; font-weight: 700; text-align: center; line-height: 1.3; padding: 0 5px; }

/* LAYOUT — single column fluid */
.ld-mainc { min-width: 0; display: flex; flex-direction: column; gap: 16px; margin-top: 22px; }
.ld-card { background: #fff; border: 1px solid #eef0f7; border-radius: 20px; box-shadow: 0 2px 8px rgba(15, 23, 42, 0.03); }

/* TAHAP AKTIF */
.ld-act { overflow: hidden; }
.ld-act__head { display: flex; gap: 13px; padding: 18px 20px; background: linear-gradient(180deg, rgba(99, 102, 241, 0.05), transparent); border-bottom: 1px solid #eef0f7; }
.ld-act__ico { flex: 0 0 auto; width: 46px; height: 46px; border-radius: 14px; background: linear-gradient(135deg, #8b5cf6, #6366f1); color: #fff; display: flex; align-items: center; justify-content: center; box-shadow: 0 10px 24px rgba(99, 102, 241, 0.28); }
.ld-act__title { font-size: 17px; font-weight: 800; color: #0f172a; margin-top: 3px; letter-spacing: -0.01em; }
.ld-act__sub { font-size: 12.5px; color: #8792a6; margin-top: 2px; }
.ld-act__form { padding: 18px 20px; }

.ld-cred { display: grid; grid-template-columns: 1fr 1fr; gap: 1px; background: #eef0f7; border: 1px solid #eef0f7; border-radius: 14px; overflow: hidden; margin: 18px 20px 0; }
.ld-cred__item { background: #fff; padding: 13px 15px; min-width: 0; }
.ld-cred__lbl { display: block; font-size: 10.5px; font-weight: 800; letter-spacing: 0.1em; color: #a2a9ba; }
.ld-cred__val { display: block; font-size: 15.5px; font-weight: 800; color: #1e293b; margin-top: 4px; word-break: break-word; }
.ld-cred__val.ld-mono { color: #4f46e5; letter-spacing: 0.05em; }

.ld-notice { display: flex; gap: 11px; align-items: flex-start; margin: 16px 20px 0; padding: 13px 15px; border-radius: 14px; font-size: 12.5px; line-height: 1.55; }
.ld-notice b { display: block; margin-bottom: 1px; color: #1e293b; }
.ld-notice p { margin: 0; color: #64748b; }
.ld-notice--wait { background: linear-gradient(135deg, #fffbeb, #fff8ec); border: 1px solid #f5e0a3; }
.ld-notice--done { background: rgba(16, 185, 129, 0.08); border: 1px solid rgba(16, 185, 129, 0.24); }
.ld-notice--err { background: rgba(239, 68, 68, 0.07); border: 1px solid rgba(239, 68, 68, 0.22); }

.ld-btn-tes { margin: 16px 20px 20px; width: calc(100% - 40px); display: inline-flex; align-items: center; justify-content: center; gap: 9px; padding: 14px; border: none; border-radius: 14px; font-family: inherit; font-size: 14px; font-weight: 800; cursor: pointer; color: #fff; background: linear-gradient(135deg, #8b5cf6, #6366f1); box-shadow: 0 12px 28px rgba(99, 102, 241, 0.3); transition: transform 0.16s; }
.ld-btn-tes:hover:not(:disabled) { transform: translateY(-2px); }
.ld-btn-tes:disabled { background: #e2e8f0; color: #94a3b8; cursor: not-allowed; box-shadow: none; }

/* ALUR SELEKSI · WATERFALL (gantt bertingkat) */
.ld-wf { padding: 18px 20px; }
.ld-wf__head { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; margin-bottom: 16px; }
.ld-wf__legend { display: flex; align-items: center; gap: 14px; }
.ld-wf__legend span { display: inline-flex; align-items: center; gap: 6px; font-size: 11.5px; font-weight: 700; color: #64748b; }
.ld-wf__legend i { width: 10px; height: 10px; border-radius: 3px; display: inline-block; }
.ld-wf__body { display: flex; flex-direction: column; gap: 12px; }
.ld-wf__row, .ld-wf__axis { display: grid; grid-template-columns: 220px 1fr; gap: 16px; align-items: center; }
/* Sumbu tanggal (ruler) */
.ld-wf__axis { margin-bottom: 2px; padding-bottom: 8px; border-bottom: 1px solid #eef0f7; }
.ld-wf__axisside { font-size: 10px; font-weight: 800; letter-spacing: 0.1em; text-transform: uppercase; color: #aab2c5; }
.ld-wf__axistrack { position: relative; height: 16px; }
.ld-wf__tick { position: absolute; top: 0; transform: translateX(-50%); font-size: 10.5px; font-weight: 800; color: #94a3b8; white-space: nowrap; font-variant-numeric: tabular-nums; }
.ld-wf__tick.is-last { transform: translateX(-100%); }
.ld-wf__side { display: flex; align-items: center; gap: 11px; min-width: 0; }
.ld-wf__node { flex: 0 0 auto; width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 800; background: #eef0f7; color: #94a3b8; }
.ld-wf__node.is-done { background: linear-gradient(135deg, #8b5cf6, #6366f1); color: #fff; }
.ld-wf__node.is-current { background: #fff; color: #b45309; border: 2px solid #f59e0b; box-shadow: 0 0 0 4px rgba(245, 158, 11, 0.16); }
.ld-wf__node.is-fail { background: #fff; color: #dc2626; border: 2px solid #ef4444; box-shadow: 0 0 0 4px rgba(239, 68, 68, 0.14); }
.ld-wf__name { font-size: 13.5px; font-weight: 800; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.ld-wf__st { font-size: 11px; font-weight: 700; margin-top: 1px; }
.ld-wf__date { display: inline-flex; align-items: center; gap: 5px; margin-top: 4px; font-size: 11px; font-weight: 700; color: #64748b; font-variant-numeric: tabular-nums; }
.ld-wf__date.is-est { color: #a2a9ba; font-style: italic; }
.ld-wf__date svg { flex: 0 0 auto; }
.ld-wf__track { position: relative; height: 32px; }
/* Garis vertikal ruler (continuous) selaras tick tanggal */
.ld-wf__grid { position: absolute; top: -6px; bottom: -6px; width: 1px; background: #eef0f7; }
.ld-wf__bar { position: absolute; top: 4px; height: 24px; border-radius: 8px; box-shadow: 0 4px 12px rgba(15, 23, 42, 0.08); display: flex; align-items: center; justify-content: flex-end; padding: 0 10px; transition: width 0.4s ease, margin-left 0.4s ease; z-index: 1; }
.ld-wf__bar.is-empty { box-shadow: none; }
.ld-wf__bar.is-empty .ld-wf__barlabel { color: #94a3b8; text-shadow: none; }
.ld-wf__barlabel { font-size: 10.5px; font-weight: 800; color: #fff; white-space: nowrap; text-shadow: 0 1px 2px rgba(0, 0, 0, 0.25); font-variant-numeric: tabular-nums; letter-spacing: 0.02em; }
.ld-wf__bar.is-glow { box-shadow: 0 6px 18px rgba(245, 158, 11, 0.4); animation: ldWfGlow 2s ease-in-out infinite; }
@keyframes ldWfGlow { 0%, 100% { box-shadow: 0 6px 18px rgba(245, 158, 11, 0.35); } 50% { box-shadow: 0 6px 24px rgba(245, 158, 11, 0.55); } }

/* DETAIL LOWONGAN — info kaya */
.ld-info { padding: 18px 20px; }
.ld-inforow { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; }
.ld-detailbtn { display: inline-flex; align-items: center; gap: 7px; font-size: 12.5px; font-weight: 800; color: #4f46e5; text-decoration: none; padding: 8px 14px; border-radius: 11px; background: rgba(99, 102, 241, 0.09); border: 1px solid rgba(99, 102, 241, 0.18); transition: all 0.16s; }
.ld-detailbtn:hover { background: rgba(99, 102, 241, 0.16); color: #4338ca; }
.ld-info__desc { margin: 14px 0 0; font-size: 13.5px; line-height: 1.65; color: #64748b; }
.ld-facts { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 16px; }
.ld-fact { display: inline-flex; align-items: center; gap: 10px; padding: 9px 14px; border-radius: 13px; background: #f6f7fb; border: 1px solid #eef0f7; }
.ld-fact__ico { flex: 0 0 auto; display: flex; }
.ld-fact__lbl { display: block; font-size: 10px; font-weight: 800; letter-spacing: 0.08em; text-transform: uppercase; color: #a2a9ba; }
.ld-fact__val { display: block; font-size: 13px; font-weight: 800; color: #1e293b; margin-top: 1px; }
.ld-blk { margin-top: 20px; }
.ld-blk__title { display: flex; align-items: center; gap: 9px; font-size: 13.5px; font-weight: 800; color: #1e293b; margin-bottom: 11px; }
.ld-blk__ico { width: 26px; height: 26px; border-radius: 8px; display: flex; align-items: center; justify-content: center; flex: 0 0 auto; }
.ld-blk__ico--green { background: rgba(16, 185, 129, 0.12); color: #059669; }
.ld-blk__ico--amber { background: rgba(245, 158, 11, 0.14); color: #d97706; }
.ld-blk__ico--indigo { background: rgba(99, 102, 241, 0.12); color: #6366f1; }
.ld-list { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 8px; }
.ld-list li { display: flex; align-items: flex-start; gap: 9px; font-size: 13px; line-height: 1.55; color: #475569; }
.ld-list--check li svg { flex: 0 0 auto; margin-top: 2px; }
.ld-list--dot li span { flex: 0 0 auto; width: 6px; height: 6px; border-radius: 50%; background: #cbd2e0; margin-top: 7px; }
.ld-skills { display: flex; flex-wrap: wrap; gap: 8px; }
.ld-skills span { font-size: 12px; font-weight: 700; color: #4f46e5; background: rgba(99, 102, 241, 0.1); border-radius: 9px; padding: 6px 12px; }
.ld-benefits { display: grid; grid-template-columns: 1fr 1fr; gap: 9px 16px; }
.ld-benefit { display: inline-flex; align-items: flex-start; gap: 8px; font-size: 13px; font-weight: 600; color: #334155; }
.ld-benefit svg { flex: 0 0 auto; margin-top: 2px; }

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
.ld-form__pill.is-wait { background: rgba(245, 158, 11, 0.14); color: #b45309; }
.ld-form__chev { flex: 0 0 auto; transition: transform 0.26s; }
.ld-form.is-open .ld-form__chev { transform: rotate(180deg); }
.ld-form__body { padding: 6px 18px 18px; animation: ldAcc 0.28s cubic-bezier(0.22, 1, 0.36, 1) both; }
@keyframes ldAcc { from { opacity: 0; transform: translateY(-6px); } to { opacity: 1; transform: translateY(0); } }
.ld-fields { display: grid; grid-template-columns: 1fr 1fr; gap: 1px; background: #eef0f7; border: 1px solid #eef0f7; border-radius: 14px; overflow: hidden; margin-top: 8px; }
.ld-field { background: #fff; padding: 11px 14px; min-width: 0; }
.ld-field__k { font-size: 10.5px; font-weight: 700; letter-spacing: 0.06em; color: #a2a9ba; text-transform: uppercase; }
.ld-field__v { font-size: 13.5px; font-weight: 700; color: #1e293b; margin-top: 3px; word-break: break-word; }
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
.ld-tl { background: rgba(255, 255, 255, 0.72); border: 1px solid rgba(226, 232, 240, 0.75); border-radius: 20px; padding: 20px 18px; position: sticky; top: 16px; }
.ld-tl__list { position: relative; padding-left: 34px; display: flex; flex-direction: column; gap: 4px; }
.ld-tl__item { position: relative; padding-bottom: 18px; }
.ld-tl__item:last-child { padding-bottom: 0; }
/* garis vertikal penghubung antar-node (waterfall) — warna mengikuti progres */
.ld-tl__item:not(:last-child)::before { content: ''; position: absolute; left: -20px; top: 26px; bottom: -4px; width: 2.5px; border-radius: 3px; background: #eef0f7; }
.ld-tl__item.is-done:not(:last-child)::before { background: linear-gradient(180deg, #8b5cf6, #a5b4fc); }
.ld-tl__item.is-current:not(:last-child)::before { background: linear-gradient(180deg, #f59e0b, #eef0f7); }
.ld-tl__item.is-fail:not(:last-child)::before { background: linear-gradient(180deg, #ef4444, #eef0f7); }
.ld-tl__node { position: absolute; left: -34px; top: 0; width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; background: #fff; border: 3px solid #cbd2e0; z-index: 1; }
.ld-tl__node span { width: 9px; height: 9px; border-radius: 50%; display: block; }
.ld-tl__lbl { font-size: 13px; font-weight: 800; }
.ld-tl__meta { display: flex; align-items: center; gap: 7px; margin-top: 4px; flex-wrap: wrap; }
.ld-tl__tag { font-size: 10px; font-weight: 800; padding: 2px 8px; border-radius: 6px; background: #f1f5f9; color: #64748b; }
.ld-tl__tag--hcl { background: rgba(99, 102, 241, 0.1); color: #4f46e5; }
.ld-tl__st { font-size: 10.5px; font-weight: 800; padding: 2px 8px; border-radius: 999px; }

/* LIGHTBOX */
.ld-lb { position: fixed; inset: 0; z-index: 1090; display: flex; align-items: center; justify-content: center; padding: 24px; background: rgba(10, 10, 20, 0.72); backdrop-filter: blur(6px); transition: opacity 0.28s; opacity: 0; pointer-events: none; }
.ld-lb.is-on { opacity: 1; pointer-events: auto; }
.ld-lb__wrap { max-width: 520px; width: 100%; animation: ldPop 0.3s cubic-bezier(0.34, 1.56, 0.64, 1) both; }
@keyframes ldPop { 0% { opacity: 0; transform: scale(0.9); } 60% { transform: scale(1.02); } 100% { opacity: 1; transform: scale(1); } }
.ld-lb__bar { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 14px; }
.ld-lb__ico { width: 40px; height: 40px; border-radius: 11px; background: rgba(255, 255, 255, 0.14); display: flex; align-items: center; justify-content: center; flex: 0 0 auto; }
.ld-lb__name { font-size: 15px; font-weight: 800; color: #fff; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.ld-lb__desc { font-size: 12px; color: rgba(255, 255, 255, 0.6); }
.ld-lb__close { appearance: none; border: 1px solid rgba(255, 255, 255, 0.2); background: rgba(255, 255, 255, 0.08); width: 40px; height: 40px; border-radius: 12px; display: flex; align-items: center; justify-content: center; cursor: pointer; color: #fff; flex: 0 0 auto; }
.ld-lb__close:hover { background: rgba(255, 255, 255, 0.18); }
.ld-lb__card { background: #fff; border-radius: 18px; overflow: hidden; box-shadow: 0 30px 80px rgba(0, 0, 0, 0.5); }
.ld-lb__card img { display: block; width: 100%; max-height: 420px; object-fit: contain; background: #0f172a; }
.ld-lb__state { height: 280px; display: flex; align-items: center; justify-content: center; gap: 10px; background: linear-gradient(135deg, #f1f2f9, #e8eaf6); color: #64748b; font-size: 13.5px; font-weight: 700; }
.ld-spin { width: 22px; height: 22px; border-radius: 50%; border: 2.6px solid rgba(99, 102, 241, 0.2); border-top-color: #6366f1; animation: ldSpin 0.7s linear infinite; }
@keyframes ldSpin { to { transform: rotate(360deg); } }
.ld-lb__foot { padding: 14px 18px; display: flex; align-items: center; justify-content: space-between; gap: 10px; border-top: 1px solid #eef0f7; }
.ld-lb__ok { display: inline-flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 800; color: #059669; flex: 0 0 auto; }

/* TOAST */
.ld-toast { position: fixed; bottom: 24px; right: 24px; z-index: 1100; display: flex; align-items: center; gap: 9px; padding: 12px 18px; border-radius: 13px; background: #0f172a; color: #fff; font-size: 13.5px; font-weight: 700; box-shadow: 0 18px 40px rgba(0, 0, 0, 0.3); }
.ld-toast.is-err { background: #dc2626; }
.ld-toast .bi { color: #34d399; }
.ld-toast.is-err .bi { color: #fff; }
.ld-toast-enter-active, .ld-toast-leave-active { transition: opacity 0.25s, transform 0.25s; }
.ld-toast-enter-from, .ld-toast-leave-to { opacity: 0; transform: translateY(12px); }

@media (max-width: 760px) {
    .ld-wf__row { grid-template-columns: 1fr; gap: 8px; }
    .ld-wf__track, .ld-wf__axis { display: none; }
}
@media (max-width: 640px) {
    .ld { padding: 20px 16px 40px; }
    .ld-cred, .ld-fields, .ld-benefits { grid-template-columns: 1fr; }
    .ld-hero__in { padding: 18px; }
}
</style>
