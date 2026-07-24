<!-- WEB CAREER — Portal Kandidat: Lamaran Saya, 1:1 desain "Lamaran Saya" (Claude
     Design). Layout FLUID (tanpa container), berjalan di dalam AppShell (rail +
     navbar shell). DATA NYATA: strip statistik (props.stats dari DB) dan kartu
     LAMARAN AKTIF + stepper PROGRES SELEKSI (props.lamaran[].tahapan dari DB).
     HARDCODE sementara (sesuai kesepakatan): Riwayat Lamaran & Rekomendasi. -->
<template>
    <Head><title>Lamaran Saya - EVO Career</title></Head>

    <div class="lms">
        <div class="lms-blob lms-blob--a"></div>
        <div class="lms-blob lms-blob--b"></div>

        <div class="lms-wrap">
            <!-- ═══ HEADING ═══ -->
            <div class="lms-head">
                <div style="min-width: 0">
                    <h1 class="lms-h1">Lamaran Saya</h1>
                    <p class="lms-sub">Pantau progres seleksimu di EVO Group. Klik lamaran untuk melihat detail &amp; mengisi formulir tahap.</p>
                </div>
                <Link href="/karir/landing-page" class="lms-btn-cari">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><circle cx="11" cy="11" r="7" /><path d="M21 21l-4.3-4.3" /></svg>
                    Cari Lowongan
                </Link>
            </div>

            <!-- ═══ STAT STRIP (REAL) ═══ -->
            <div class="lms-stats">
                <div v-for="st in statTampil" :key="st.label" class="lms-stat">
                    <span class="lms-stat__ico" :style="{ background: st.bg, color: st.c }" v-html="st.icon"></span>
                    <span style="display: flex; flex-direction: column; min-width: 0">
                        <span class="lms-stat__val">{{ st.value }}</span>
                        <span class="lms-stat__lbl">{{ st.label }}</span>
                    </span>
                </div>
            </div>

            <!-- ═══ EMPTY STATE ═══ -->
            <div v-if="!lamaran.length" class="lms-hero" style="margin-top: 30px; text-align: center; padding: 52px 28px">
                <div style="width: 62px; height: 62px; border-radius: 18px; margin: 0 auto; background: rgba(99, 102, 241, 0.1); color: #6366f1; display: flex; align-items: center; justify-content: center">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="7" width="18" height="13" rx="2" /><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" /></svg>
                </div>
                <div style="font-size: 18px; font-weight: 800; color: #0f172a; margin-top: 16px">Belum ada lamaran</div>
                <div style="font-size: 13.5px; color: #8792a6; margin-top: 6px">Mulai dengan mencari lowongan yang cocok untukmu.</div>
                <Link href="/karir/landing-page" class="lms-btn-cari" style="margin-top: 18px">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><circle cx="11" cy="11" r="7" /><path d="M21 21l-4.3-4.3" /></svg>
                    Cari Lowongan
                </Link>
            </div>

            <!-- ═══ LAMARAN AKTIF (REAL) ═══ -->
            <template v-if="heroes.length">
                <div class="lms-sec-label" style="margin: 30px 2px 12px">LAMARAN AKTIF</div>
                <div v-for="(l, hi) in heroes" :key="l.id" class="lms-hero" :style="{ marginBottom: hi < heroes.length - 1 ? '16px' : '0' }">
                    <span class="lms-hero__bar"></span>
                    <div class="lms-hero__glow"></div>
                    <div class="lms-hero__in">
                        <div class="lms-hero__toprow">
                            <div style="display: flex; align-items: center; gap: 9px; flex-wrap: wrap">
                                <span class="lms-chip" :style="katChipStyle(l.kategori)">
                                    <svg v-if="l.kategori === 'MT'" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10v6M2 10l10-5 10 5-10 5z" /><path d="M6 12v5c3 3 9 3 12 0v-5" /></svg>
                                    <svg v-else width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="7" width="18" height="13" rx="2" /><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" /></svg>
                                    {{ katLabel(l.kategori) }}
                                </span>
                                <span class="lms-chip" :style="stChipStyle(l.status)">
                                    <span v-if="l.status === 'BERJALAN'" class="lms-pulse"></span>
                                    <svg v-else-if="l.status === 'LULUS'" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round"><path d="M20 6L9 17l-5-5" /></svg>
                                    <svg v-else width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><circle cx="12" cy="12" r="9" /><path d="M15 9l-6 6M9 9l6 6" /></svg>
                                    {{ stLabel(l.status) }}
                                </span>
                            </div>
                            <div style="text-align: right; color: #94a3b8; font-size: 12px">
                                <div style="font-weight: 700; color: #334155">Dilamar {{ tglLamar(l.waktuLamar) }}</div>
                                <div style="margin-top: 2px">Kode: <span class="lms-mono">{{ l.kode }}</span></div>
                            </div>
                        </div>

                        <h2 class="lms-hero__title">{{ heroJudul(l) }}</h2>
                        <p class="lms-hero__desc">{{ heroDesc(l) }}</p>

                        <div class="lms-metas">
                            <span v-for="(m, mi) in heroMeta(l)" :key="mi" class="lms-meta">
                                <span v-html="m.icon"></span>{{ m.text }}
                            </span>
                        </div>

                        <!-- PROGRES SELEKSI (stepper real dari tahapan DB) -->
                        <div class="lms-prog">
                            <div class="lms-prog__head">
                                <span class="lms-sec-label">PROGRES SELEKSI</span>
                                <span style="font-size: 13px; font-weight: 800; color: #4f46e5">Tahap {{ l.urutanTahap }} dari {{ l.totalTahap }}</span>
                            </div>
                            <div class="lms-prog__bar"><div :style="{ width: heroPct(l) + '%' }"></div></div>
                            <div class="lms-steps">
                                <div v-for="(sg, i) in heroSteps(l)" :key="i" class="lms-step">
                                    <div v-if="i > 0" class="lms-step__line" :style="{ background: sg.line }"></div>
                                    <div class="lms-step__node" :class="'is-' + sg.st">
                                        <svg v-if="sg.st === 'done'" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5" /></svg>
                                        <svg v-else-if="sg.st === 'fail'" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round"><path d="M18 6L6 18M6 6l12 12" /></svg>
                                        <template v-else>{{ i + 1 }}</template>
                                    </div>
                                    <div class="lms-step__lbl" :style="{ color: sg.lbl }">{{ sg.name }}</div>
                                </div>
                            </div>
                        </div>

                        <div v-if="l.status === 'GUGUR' && l.gugurDi" class="lms-gugurnote">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><circle cx="12" cy="12" r="9" /><path d="M15 9l-6 6M9 9l6 6" /></svg>
                            Tidak lolos di tahap: {{ l.gugurDi }}
                        </div>

                        <div style="margin-top: 18px; display: flex; align-items: center; gap: 10px; flex-wrap: wrap">
                            <Link :href="`/kandidat/lamaran/${l.id}`" class="lms-btn-detail">
                                Detail Lamaran
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6" /></svg>
                            </Link>
                            <button type="button" class="lms-btn-hapus" :disabled="menghapus === l.id" title="Hapus / batalkan lamaran ini" @click="minta(l)">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18" /><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" /><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6" /><path d="M10 11v6M14 11v6" /></svg>
                            </button>
                        </div>
                    </div>
                </div>
            </template>

            <!-- ═══ RIWAYAT LAMARAN (DUMMY sementara) ═══ -->
            <div class="lms-histrow">
                <div class="lms-sec-label">RIWAYAT LAMARAN</div>
                <div style="display: flex; gap: 7px; flex-wrap: wrap">
                    <button v-for="f in FILTERS" :key="f.key" type="button" class="lms-fbtn" :class="{ 'is-on': filter === f.key }" @click="filter = f.key">{{ f.label }}</button>
                </div>
            </div>

            <div class="lms-tl">
                <div class="lms-tl__line"></div>
                <div style="display: flex; flex-direction: column; gap: 14px">
                    <div v-for="h in histTampil" :key="h.id" class="lms-tl__item">
                        <span class="lms-tl__dot" :style="{ borderColor: ST[h.status].dot }"></span>
                        <button type="button" class="lms-hcard" @click="bukaApp(h)">
                            <span class="lms-hcard__row">
                                <span style="display: flex; flex-direction: column; min-width: 0; text-align: left">
                                    <span style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap">
                                        <span class="lms-type" :style="typeStyle(h.type)">{{ h.type }}</span>
                                        <span class="lms-mono" style="font-size: 11px; color: #a2a9ba">{{ h.code }}</span>
                                    </span>
                                    <span class="lms-hcard__title">{{ h.title }}</span>
                                    <span class="lms-hcard__meta">
                                        <span><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" /><path d="M3 10h18M8 2v4M16 2v4" /></svg>Dilamar {{ h.applied }}</span>
                                        <span><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3 8-8" /><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11" /></svg>{{ h.lastStage }}</span>
                                    </span>
                                </span>
                                <span style="display: flex; flex-direction: column; align-items: flex-end; gap: 10px; flex: 0 0 auto">
                                    <span class="lms-pill" :style="pillStyle(h.status)">{{ h.statusLabel }}</span>
                                    <span style="display: inline-flex; align-items: center; gap: 5px; font-size: 12px; font-weight: 700; color: #6366f1">
                                        Lihat detail
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M9 6l6 6-6 6" /></svg>
                                    </span>
                                </span>
                            </span>
                        </button>
                    </div>
                    <div v-if="!histTampil.length" style="padding: 30px; text-align: center; color: #a2a9ba; font-size: 13px">Tidak ada lamaran pada filter ini.</div>
                </div>
            </div>

            <!-- ═══ REKOMENDASI (DUMMY sementara) ═══ -->
            <div class="lms-recrow">
                <span class="lms-recrow__ico">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l1.9 5.1L19 10l-5.1 1.9L12 17l-1.9-5.1L5 10l5.1-1.9z" /></svg>
                </span>
                <div>
                    <div style="font-size: 16px; font-weight: 800; color: #0f172a; letter-spacing: -0.01em">Rekomendasi Untukmu</div>
                    <div style="font-size: 12px; color: #8792a6">Lowongan yang cocok dengan profil &amp; riwayatmu</div>
                </div>
            </div>
            <div class="lms-recs">
                <div v-for="r in RECS" :key="r.id" class="lms-rec">
                    <div class="lms-rec__glow" :style="{ background: `radial-gradient(circle,${r.glow},transparent 70%)` }"></div>
                    <div style="position: relative; display: flex; align-items: center; gap: 10px">
                        <span class="lms-type" :style="typeStyle(r.type)">{{ r.type }}</span>
                    </div>
                    <div class="lms-rec__title">{{ r.title }}</div>
                    <div class="lms-rec__meta">
                        <span><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21s-7-6-7-11a7 7 0 0 1 14 0c0 5-7 11-7 11z" /><circle cx="12" cy="10" r="2.5" /></svg>{{ r.location }}</span>
                        <span><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9" /><path d="M12 7v5l3 2" /></svg>{{ r.deadline }}</span>
                    </div>
                    <div class="lms-rec__match">
                        <div class="lms-rec__matchbar"><div :style="{ width: r.match + '%' }"></div></div>
                        <span style="font-size: 12px; font-weight: 800; color: #059669; white-space: nowrap">{{ r.match }}% cocok</span>
                    </div>
                    <Link href="/karir/landing-page" class="lms-rec__btn">
                        Lamar Sekarang
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6" /></svg>
                    </Link>
                </div>
            </div>

            <div style="height: 20px"></div>
        </div>

        <!-- ═══ DRAWER DETAIL (riwayat dummy) ═══ -->
        <div class="lms-overlay" :class="{ 'is-on': !!app }" @click="app = null"></div>
        <section class="lms-drawer" :class="{ 'is-on': !!app }" aria-label="Detail lamaran">
            <template v-if="app">
                <div class="lms-drawer__head">
                    <div style="display: flex; align-items: flex-start; gap: 14px">
                        <div class="lms-drawer__ico">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10L12 5 2 10l10 5 10-5z" /><path d="M6 12v5c3 3 9 3 12 0v-5" /></svg>
                        </div>
                        <div style="flex: 1; min-width: 0">
                            <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap">
                                <span class="lms-type" :style="typeStyle(app.type)">{{ app.type }}</span>
                                <span class="lms-pill" :style="pillStyle(app.status)">{{ app.statusLabel }}</span>
                            </div>
                            <div class="lms-drawer__title">{{ app.title }}</div>
                            <div style="font-size: 12px; color: #8792a6; margin-top: 3px">Dilamar {{ app.applied }} · <span class="lms-mono">{{ app.code }}</span></div>
                        </div>
                        <button type="button" class="lms-drawer__close" @click="app = null">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M18 6L6 18M6 6l12 12" /></svg>
                        </button>
                    </div>
                </div>

                <div class="lms-drawer__body">
                    <!-- banner status -->
                    <div class="lms-banner" :style="{ background: ST[app.status].bg, borderColor: ST[app.status].bg }">
                        <span class="lms-banner__ico" :style="{ background: ST[app.status].dot }">
                            <svg v-if="app.status === 'lolos'" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M20 6L9 17l-5-5" /></svg>
                            <svg v-else-if="app.status === 'gugur'" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><circle cx="12" cy="12" r="9" /><path d="M15 9l-6 6M9 9l6 6" /></svg>
                            <svg v-else width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><circle cx="12" cy="12" r="9" /><path d="M12 7v5l3 2" /></svg>
                        </span>
                        <div style="min-width: 0">
                            <div style="font-size: 14px; font-weight: 800" :style="{ color: ST[app.status].c }">{{ app.banner.title }}</div>
                            <div style="font-size: 12.5px; line-height: 1.55; opacity: 0.85; margin-top: 2px" :style="{ color: ST[app.status].c }">{{ app.banner.text }}</div>
                        </div>
                    </div>

                    <!-- timeline tahap -->
                    <div>
                        <div class="lms-sec-label" style="margin-bottom: 14px; letter-spacing: 0.12em; color: #a2a9ba">TIMELINE TAHAP SELEKSI</div>
                        <div class="lms-stl">
                            <div class="lms-stl__line"></div>
                            <div style="display: flex; flex-direction: column; gap: 16px">
                                <div v-for="(sg, si) in app.stages" :key="si" style="position: relative">
                                    <span class="lms-stl__node" :style="stlNode(sg.st)"><span :style="{ background: stlCol(sg.st) }"></span></span>
                                    <div style="display: flex; align-items: flex-start; justify-content: space-between; gap: 10px">
                                        <div style="min-width: 0">
                                            <div style="font-size: 14px; font-weight: 800" :style="{ color: sg.st === 'todo' ? '#94a3b8' : '#1e293b' }">{{ sg.name }}</div>
                                            <div style="font-size: 12px; color: #8792a6; margin-top: 2px">{{ sg.note }}</div>
                                        </div>
                                        <span class="lms-stl__tag" :style="stlTag(sg.st)">{{ stlTagLabel(sg.st) }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- formulir & berkas -->
                    <div>
                        <div style="display: flex; align-items: center; justify-content: space-between; gap: 10px; margin-bottom: 12px">
                            <div style="display: flex; align-items: center; gap: 9px; font-size: 15px; font-weight: 800; color: #1e293b">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#6366f1" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7a2 2 0 0 1 2-2h4l2 2h6a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H4z" /></svg>
                                Formulir &amp; Berkas Saya
                            </div>
                            <span style="font-size: 11.5px; font-weight: 700; color: #8b93a7; background: #eef0f7; border-radius: 999px; padding: 4px 11px; flex: 0 0 auto">{{ app.forms.length }} formulir</span>
                        </div>
                        <div style="display: flex; flex-direction: column; gap: 12px">
                            <div v-for="(f, fi) in app.forms" :key="fi" class="lms-form" :class="{ 'is-open': openForm === fi }">
                                <button type="button" class="lms-form__head" @click="openForm = openForm === fi ? -1 : fi">
                                    <span class="lms-form__step" :class="{ 'is-ok': f.pillType === 'ok' }">{{ String(fi + 1).padStart(2, '0') }}</span>
                                    <span style="flex: 1; min-width: 0">
                                        <span class="lms-form__title">{{ f.title }}</span>
                                        <span class="lms-form__sub">{{ f.subtitle }}</span>
                                    </span>
                                    <span class="lms-form__pill" :class="f.pillType === 'ok' ? 'is-ok' : 'is-wait'">{{ f.pill }}</span>
                                    <svg class="lms-form__chev" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="2.4" stroke-linecap="round"><path d="M6 9l6 6 6-6" /></svg>
                                </button>
                                <div v-if="openForm === fi" class="lms-form__body">
                                    <div v-if="f.fields.length" class="lms-fields">
                                        <div v-for="fl in f.fields" :key="fl[0]" class="lms-field">
                                            <div class="lms-field__k">{{ fl[0] }}</div>
                                            <div class="lms-field__v">{{ fl[1] }}</div>
                                        </div>
                                    </div>
                                    <div v-if="f.docs.length" style="margin-top: 14px; display: flex; flex-direction: column; gap: 9px">
                                        <div class="lms-sec-label" style="letter-spacing: 0.12em; color: #a2a9ba; font-size: 11px">DOKUMEN &amp; VERIFIKASI</div>
                                        <div v-for="d in f.docs" :key="d.name" class="lms-doc">
                                            <span class="lms-doc__ico" :class="d.kind === 'img' ? 'is-img' : 'is-pdf'">
                                                <svg v-if="d.kind === 'img'" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#6366f1" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" /><circle cx="8.5" cy="8.5" r="1.5" /><path d="M21 15l-5-5L5 21" /></svg>
                                                <svg v-else width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" /><path d="M14 2v6h6" /></svg>
                                            </span>
                                            <div style="flex: 1; min-width: 0">
                                                <div style="display: flex; align-items: center; gap: 8px">
                                                    <span class="lms-doc__name">{{ d.name }}</span>
                                                    <span class="lms-doc__ext">{{ d.ext }}</span>
                                                </div>
                                                <div class="lms-doc__desc">{{ d.desc }}</div>
                                            </div>
                                            <button type="button" class="lms-doc__eye" title="Lihat berkas" @click="lightbox = d">
                                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z" /><circle cx="12" cy="12" r="3" /></svg>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </section>

        <!-- ═══ LIGHTBOX ═══ -->
        <div class="lms-lb" :class="{ 'is-on': !!lightbox }" @click="lightbox = null">
            <div v-if="lightbox" class="lms-lb__wrap" @click.stop>
                <div class="lms-lb__bar">
                    <div style="display: flex; align-items: center; gap: 11px; color: #fff; min-width: 0">
                        <span class="lms-lb__ico">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" /><circle cx="8.5" cy="8.5" r="1.5" /><path d="M21 15l-5-5L5 21" /></svg>
                        </span>
                        <div style="min-width: 0">
                            <div style="font-size: 15px; font-weight: 800; white-space: nowrap; overflow: hidden; text-overflow: ellipsis">{{ lightbox.name }}</div>
                            <div style="font-size: 12px; color: rgba(255, 255, 255, 0.6); white-space: nowrap; overflow: hidden; text-overflow: ellipsis">{{ lightbox.desc }}</div>
                        </div>
                    </div>
                    <button type="button" class="lms-lb__close" @click="lightbox = null">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M18 6L6 18M6 6l12 12" /></svg>
                    </button>
                </div>
                <div class="lms-lb__card">
                    <div class="lms-lb__ph">
                        <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" /><circle cx="8.5" cy="8.5" r="1.5" /><path d="M21 15l-5-5L5 21" /></svg>
                        <span>Foto verifikasi</span>
                    </div>
                    <div class="lms-lb__foot">
                        <div style="font-size: 12px; color: #8b93a7">Berkas yang kamu unggah</div>
                        <div style="display: inline-flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 800; color: #059669; flex: 0 0 auto">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M20 6L9 17l-5-5" /></svg>
                            Terverifikasi
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ═══ KONFIRMASI HAPUS (fitur lama dipertahankan) ═══ -->
        <div v-if="target" class="lms-modal" @click.self="target = null">
            <div class="lms-modal__box">
                <div class="lms-modal__ic">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.3 3.8L1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.8a2 2 0 0 0-3.4 0z" /><path d="M12 9v4M12 17h.01" /></svg>
                </div>
                <h3>Hapus lamaran ini?</h3>
                <p>Lamaran <strong>{{ target.posisi || target.program }}</strong> ({{ target.kode }}) beserta isian formulirnya akan dihapus permanen. Tindakan ini tidak bisa dibatalkan.</p>
                <div class="lms-modal__act">
                    <button type="button" class="lms-modal__soft" @click="target = null">Batal</button>
                    <button type="button" class="lms-modal__danger" :disabled="!!menghapus" @click="hapus">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18" /><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" /><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6" /></svg>
                        {{ menghapus ? 'Menghapus…' : 'Ya, Hapus' }}
                    </button>
                </div>
            </div>
        </div>

        <transition name="lms-toast"><div v-if="toast" class="lms-toast" :class="{ 'is-err': toastErr }"><i class="bi" :class="toastErr ? 'bi-exclamation-circle-fill' : 'bi-check-circle-fill'"></i> {{ toast }}</div></transition>
    </div>
</template>

<script>
import axios from 'axios';
import { Head, Link, router } from '@inertiajs/vue3';

const CFG = { headers: { Accept: 'application/json' } };
const BULAN = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

// Ikon stroke (dipakai strip statistik & chip meta hero) — persis desain.
const SVG = (path, w = 21) => `<svg width="${w}" height="${w}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">${path}</svg>`;
const MSVG = (path) => `<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#8b5cf6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">${path}</svg>`;
const P = {
    koper: '<rect x="3" y="7" width="18" height="13" rx="2"/><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>',
    jam: '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
    cek: '<path d="M20 6L9 17l-5-5"/>',
    silang: '<circle cx="12" cy="12" r="9"/><path d="M15 9l-6 6M9 9l6 6"/>',
    topi: '<path d="M22 10L12 5 2 10l10 5 10-5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/>',
    pin: '<path d="M12 21s-7-6-7-11a7 7 0 0 1 14 0c0 5-7 11-7 11z"/><circle cx="12" cy="10" r="2.5"/>',
    orang: '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>',
    level: '<path d="M4 20h4V10H4zM10 20h4V4h-4zM16 20h4v-8h-4z"/>',
};

// Warna status (persis desain: berjalan/menunggu/lolos/gugur).
const ST = {
    berjalan: { c: '#b45309', bg: 'rgba(245,158,11,.14)', dot: '#f59e0b' },
    menunggu: { c: '#1d4ed8', bg: 'rgba(59,130,246,.12)', dot: '#3b82f6' },
    lolos: { c: '#059669', bg: 'rgba(16,185,129,.12)', dot: '#10b981' },
    gugur: { c: '#dc2626', bg: 'rgba(239,68,68,.1)', dot: '#ef4444' },
};

// ── DUMMY (sesuai kesepakatan: hanya riwayat & rekomendasi yang hardcode) ──
const DEMO_FORMS = [
    { title: 'Seleksi Administrasi', subtitle: 'Tahap 1 · Pendaftaran', pill: 'Lengkap', pillType: 'ok',
        fields: [['Nama', 'HAMBA ALLAH'], ['Email', 'fransbachtiar4@gmail.com'], ['Tanggal Lahir', '03 Mei 2004'], ['No. HP', '+62 821-3444-4545'], ['Jenis Kelamin', 'Laki-Laki'], ['Status', 'Mahasiswa'], ['Kampus', 'Universitas Sriwijaya'], ['IPK', '4.00']],
        docs: [{ name: 'verifikasi.jpg', ext: 'JPG', kind: 'img', desc: 'Foto selfie memegang KTP yang kamu unggah.' }] },
    { title: 'Kelengkapan Berkas', subtitle: 'Tahap 3 · Dokumen pendukung', pill: 'Menunggu', pillType: 'wait',
        fields: [],
        docs: [{ name: 'ktp.pdf', ext: 'PDF', kind: 'doc', desc: 'Belum diunggah — lengkapi saat tahap kelengkapan data.' }, { name: 'ijazah.pdf', ext: 'PDF', kind: 'doc', desc: 'Belum diunggah.' }] },
];
const HISTORY = [
    { id: 'sales', type: 'Rekrutmen', code: 'LMR-SLS-0413', title: 'Open Hiring Sales Sumsel', applied: '10 Mar 2026', status: 'gugur', statusLabel: 'Tidak Lolos', lastStage: 'Wawancara HR',
        banner: { title: 'Belum lolos pada tahap ini', text: 'Terima kasih atas partisipasimu. Jangan menyerah — banyak lowongan lain menantimu.' },
        stages: [{ name: 'Seleksi Administrasi', st: 'done', note: 'Lolos' }, { name: 'Tes Tertulis', st: 'done', note: 'Lolos' }, { name: 'Wawancara HR', st: 'fail', note: 'Tidak dilanjutkan · 28 Mar 2026' }],
        forms: DEMO_FORMS },
    { id: 'intern', type: 'Internship', code: 'LMR-INT-0087', title: 'EVO Internship Batch 5', applied: '05 Jan 2026', status: 'lolos', statusLabel: 'Diterima', lastStage: 'Onboarding',
        banner: { title: 'Selamat, kamu diterima! 🎉', text: 'Kamu menyelesaikan seluruh tahap seleksi dan resmi bergabung di EVO Internship Batch 5.' },
        stages: [{ name: 'Seleksi Administrasi', st: 'done', note: 'Lolos' }, { name: 'Wawancara', st: 'done', note: 'Lolos' }, { name: 'Onboarding', st: 'done', note: 'Selesai · 20 Jan 2026' }],
        forms: DEMO_FORMS },
    { id: 'q3', type: 'Rekrutmen', code: 'LMR-REK-2291', title: 'Rekrutmen Reguler Q3 2025', applied: '12 Agu 2025', status: 'menunggu', statusLabel: 'Menunggu Pengumuman', lastStage: 'Wawancara User',
        banner: { title: 'Menunggu pengumuman hasil', text: 'Kamu telah menyelesaikan wawancara user. Hasil akhir akan diumumkan melalui email.' },
        stages: [{ name: 'Seleksi Administrasi', st: 'done', note: 'Lolos' }, { name: 'Tes Tertulis', st: 'done', note: 'Lolos' }, { name: 'Wawancara User', st: 'done', note: 'Selesai' }, { name: 'Pengumuman', st: 'current', note: 'Sedang ditinjau' }],
        forms: DEMO_FORMS },
];
const RECS = [
    { id: 'r1', type: 'Management Trainee', title: 'Data Analyst Trainee 2026', location: 'Palembang', deadline: '14 hari lagi', match: 92, glow: 'rgba(99,102,241,.12)' },
    { id: 'r2', type: 'Rekrutmen', title: 'Sales Executive Sumsel', location: 'Palembang', deadline: '9 hari lagi', match: 85, glow: 'rgba(99,102,241,.12)' },
    { id: 'r3', type: 'Internship', title: 'HR Internship Batch 6', location: 'Palembang', deadline: '21 hari lagi', match: 78, glow: 'rgba(245,158,11,.12)' },
];

export default {
    components: { Head, Link },
    props: {
        lamaran: { type: Array, default: () => [] },
        stats: { type: Object, default: () => ({ total: 0, berjalan: 0, lulus: 0, gugur: 0 }) },
    },
    data() {
        return {
            ST, RECS,
            FILTERS: [
                { key: 'semua', label: 'Semua' },
                { key: 'berjalan', label: 'Berjalan' },
                { key: 'lolos', label: 'Lolos' },
                { key: 'gugur', label: 'Tidak Lolos' },
            ],
            filter: 'semua',
            app: null,
            openForm: 0,
            lightbox: null,
            target: null,
            menghapus: null,
            toast: '',
            toastErr: false,
            tm: null,
        };
    },
    computed: {
        // Strip statistik — NILAI REAL dari controller (props.stats).
        statTampil() {
            return [
                { value: this.stats.total, label: 'Total Lamaran', bg: 'rgba(99,102,241,.12)', c: '#6366f1', icon: SVG(P.koper) },
                { value: this.stats.berjalan, label: 'Berjalan', bg: 'rgba(245,158,11,.14)', c: '#f59e0b', icon: SVG(P.jam) },
                { value: this.stats.lulus, label: 'Diterima', bg: 'rgba(16,185,129,.12)', c: '#10b981', icon: SVG(P.cek) },
                { value: this.stats.gugur, label: 'Tidak Lolos', bg: 'rgba(239,68,68,.1)', c: '#ef4444', icon: SVG(P.silang) },
            ];
        },
        // Kartu hero = lamaran BERJALAN (real). Bila tak ada yang berjalan,
        // tampilkan lamaran terbaru agar kandidat tetap melihat status akhirnya.
        heroes() {
            const jalan = this.lamaran.filter((l) => l.status === 'BERJALAN');
            return jalan.length ? jalan : this.lamaran.slice(0, 1);
        },
        histTampil() {
            if (this.filter === 'semua') return HISTORY;
            return HISTORY.filter((h) => h.status === this.filter);
        },
    },
    methods: {
        k(l) { return l.kartu || {}; },
        katLabel(k) { return { REKRUTMEN: 'Rekrutmen', MT: 'Management Trainee', INTERNSHIP: 'Internship' }[k] || k || '—'; },
        stLabel(s) { return { BERJALAN: 'Berjalan', LULUS: 'Diterima', GUGUR: 'Tidak Lolos', MUNDUR: 'Mengundurkan Diri' }[s] || s; },
        stKey(s) { return { BERJALAN: 'berjalan', LULUS: 'lolos', GUGUR: 'gugur', MUNDUR: 'gugur' }[s] || 'berjalan'; },
        katChipStyle(kat) {
            return kat === 'MT'
                ? { background: 'rgba(245,158,11,.14)', color: '#b45309' }
                : kat === 'INTERNSHIP'
                    ? { background: 'rgba(16,185,129,.12)', color: '#059669' }
                    : { background: 'rgba(99,102,241,.12)', color: '#4f46e5' };
        },
        stChipStyle(s) { const t = ST[this.stKey(s)]; return { background: t.bg, color: t.c }; },
        typeStyle(t) {
            return t === 'Management Trainee'
                ? { background: 'rgba(245,158,11,.14)', color: '#b45309' }
                : t === 'Internship'
                    ? { background: 'rgba(16,185,129,.12)', color: '#059669' }
                    : { background: 'rgba(99,102,241,.12)', color: '#4f46e5' };
        },
        pillStyle(st) { return { background: ST[st].bg, color: ST[st].c }; },
        tglLamar(iso) {
            if (!iso) return '—';
            const d = new Date(String(iso).replace(' ', 'T'));
            if (Number.isNaN(d.getTime())) return '—';
            return `${d.getDate()} ${BULAN[d.getMonth()]} ${d.getFullYear()}`;
        },
        // ── HERO (real) ──
        heroJudul(l) { const c = this.k(l); return (l.kategori === 'MT' ? c.nama : c.posisi) || l.posisi || l.program; },
        heroDesc(l) {
            const c = this.k(l);
            return c.ringkasan || (l.kategori === 'MT' ? `Program ${l.program} — akselerasi calon pemimpin masa depan EVO Group.` : `Lowongan ${l.posisi || l.program} di EVO Group.`);
        },
        heroMeta(l) {
            const c = this.k(l);
            const m = [];
            if (l.kategori === 'MT') {
                if (c.tipeKegiatan || true) m.push({ icon: MSVG(P.topi), text: c.tipeKegiatan || 'Management Trainee' });
                if (c.penempatan || l.lokasi) m.push({ icon: MSVG(P.pin), text: c.penempatan || l.lokasi });
                if (c.durasi) m.push({ icon: MSVG(P.jam), text: c.durasi });
                if (c.kuota != null) m.push({ icon: MSVG(P.orang), text: `${c.kuota} kursi` });
            } else {
                m.push({ icon: MSVG(P.koper), text: c.tipeKerja || 'Full-time' });
                if (c.lokasi || l.lokasi) m.push({ icon: MSVG(P.pin), text: (c.lokasi || l.lokasi) + (c.tempatKerja ? ` · ${c.tempatKerja}` : '') });
                if (c.level) m.push({ icon: MSVG(P.level), text: c.level });
                if (c.pengalaman) m.push({ icon: MSVG(P.jam), text: c.pengalaman });
            }
            return m;
        },
        // Stepper real dari l.tahapan (DB); fallback nomor bila tahapan kosong.
        heroSteps(l) {
            const rows = l.tahapan?.length
                ? l.tahapan.map((t) => ({
                    name: t.label,
                    st: t.status === 'GUGUR' || t.hasil === 'GUGUR' ? 'fail'
                        : t.status === 'SELESAI' ? 'done'
                            : t.status === 'BERJALAN' ? 'current' : 'todo',
                }))
                : Array.from({ length: l.totalTahap || 0 }, (_, i) => ({
                    name: `Tahap ${i + 1}`,
                    st: i + 1 < l.urutanTahap ? 'done' : i + 1 === l.urutanTahap ? (l.status === 'GUGUR' ? 'fail' : 'current') : 'todo',
                }));
            return rows.map((r) => ({
                ...r,
                line: r.st === 'done' || r.st === 'current' ? '#a5b4fc' : r.st === 'fail' ? '#fca5a5' : '#e2e8f0',
                lbl: r.st === 'todo' ? '#94a3b8' : r.st === 'fail' ? '#dc2626' : '#334155',
            }));
        },
        heroPct(l) {
            const steps = this.heroSteps(l);
            if (!steps.length) return 0;
            const done = steps.filter((s) => s.st === 'done').length;
            const cur = steps.some((s) => s.st === 'current' || s.st === 'fail') ? 0.5 : 0;
            return Math.round(((done + cur) / steps.length) * 100);
        },
        // ── DRAWER dummy ──
        bukaApp(h) { this.app = h; this.openForm = 0; },
        stlCol(st) { return st === 'done' ? '#10b981' : st === 'current' ? '#f59e0b' : st === 'fail' ? '#ef4444' : '#cbd2e0'; },
        stlNode(st) { return { borderColor: this.stlCol(st), animation: st === 'current' ? 'lmsPulse 2s infinite' : 'none' }; },
        stlTagLabel(st) { return st === 'done' ? 'Selesai' : st === 'current' ? 'Berlangsung' : st === 'fail' ? 'Berhenti' : 'Menunggu'; },
        stlTag(st) {
            return st === 'done' ? { background: 'rgba(16,185,129,.12)', color: '#059669' }
                : st === 'current' ? { background: 'rgba(245,158,11,.14)', color: '#b45309' }
                    : st === 'fail' ? { background: 'rgba(239,68,68,.1)', color: '#dc2626' }
                        : { background: '#eef0f7', color: '#94a3b8' };
        },
        // ── Hapus lamaran (fitur lama, tetap ada) ──
        minta(l) { this.target = l; },
        async hapus() {
            if (this.menghapus || !this.target) return;
            const l = this.target;
            this.menghapus = l.id;
            try {
                await axios.delete(`/api/v1/lamaran/${l.id}`, CFG);
                this.notice('Lamaran dihapus.');
                this.target = null;
                router.reload({ only: ['lamaran', 'stats'] });
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal menghapus lamaran.', true);
            } finally {
                this.menghapus = null;
            }
        },
        notice(x, err = false) { this.toast = x; this.toastErr = err; if (this.tm) clearTimeout(this.tm); this.tm = setTimeout(() => (this.toast = ''), 3200); },
    },
};
</script>

<style scoped>
.lms-mono { font-family: 'JetBrains Mono', 'Courier New', monospace; color: #8b93a7; }
.lms-sec-label { font-size: 12px; font-weight: 800; letter-spacing: 0.14em; color: #8b93a7; }

/* ═══ HALAMAN: fluid penuh (container-fluid), latar dari shell ═══ */
/* clip (bukan hidden) agar tidak jadi scroll container → hindari scrollbar ganda. */
.lms { position: relative; margin: -1rem; padding: 28px 34px 48px; min-height: calc(100vh - 68px); overflow-x: clip; }
.lms-blob { position: absolute; border-radius: 50%; filter: blur(8px); pointer-events: none; z-index: 0; }
.lms-blob--a { top: -120px; right: 12%; width: 440px; height: 440px; background: radial-gradient(circle at 30% 30%, rgba(139, 92, 246, 0.14), rgba(139, 92, 246, 0) 70%); animation: lmsFloatA 16s ease-in-out infinite; }
.lms-blob--b { bottom: -160px; left: 6%; width: 460px; height: 460px; background: radial-gradient(circle at 60% 40%, rgba(99, 102, 241, 0.1), rgba(99, 102, 241, 0) 70%); animation: lmsFloatB 19s ease-in-out infinite; }
@keyframes lmsFloatA { 0%, 100% { transform: translate(0, 0); } 50% { transform: translate(30px, -24px); } }
@keyframes lmsFloatB { 0%, 100% { transform: translate(0, 0); } 50% { transform: translate(-26px, 22px); } }
.lms-wrap { position: relative; z-index: 2; }

/* ═══ HEADING ═══ */
.lms-head { display: flex; align-items: flex-end; justify-content: space-between; gap: 16px; flex-wrap: wrap; margin-bottom: 22px; }
.lms-h1 { margin: 0; font-size: 28px; font-weight: 800; color: #0f172a; letter-spacing: -0.025em; }
.lms-sub { margin: 7px 0 0; font-size: 14px; color: #64748b; }
.lms-btn-cari { appearance: none; cursor: pointer; font-family: inherit; font-size: 13.5px; font-weight: 800; color: #fff; padding: 12px 20px; border-radius: 14px; border: none; background: linear-gradient(135deg, #8b5cf6, #6366f1); box-shadow: 0 12px 28px rgba(99, 102, 241, 0.32); display: inline-flex; align-items: center; gap: 9px; flex: 0 0 auto; transition: transform 0.16s; text-decoration: none; }
.lms-btn-cari:hover { transform: translateY(-2px); color: #fff; }

/* ═══ STAT STRIP ═══ */
.lms-stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; background: rgba(255, 255, 255, 0.7); border: 1px solid rgba(226, 232, 240, 0.7); border-radius: 20px; padding: 16px 22px; box-shadow: 0 6px 20px rgba(15, 23, 42, 0.04); }
.lms-stat { display: flex; align-items: center; gap: 13px; padding: 6px 4px; }
.lms-stat__ico { width: 44px; height: 44px; border-radius: 13px; flex: 0 0 auto; display: flex; align-items: center; justify-content: center; }
.lms-stat__val { font-size: 24px; font-weight: 800; color: #0f172a; letter-spacing: -0.02em; line-height: 1; }
.lms-stat__lbl { font-size: 12px; color: #8792a6; font-weight: 600; margin-top: 3px; white-space: nowrap; }

/* ═══ HERO LAMARAN AKTIF ═══ */
.lms-hero { position: relative; border-radius: 24px; overflow: hidden; background: #fff; border: 1px solid #eef0f7; box-shadow: 0 12px 34px rgba(15, 23, 42, 0.07); animation: lmsRiseIn 0.5s cubic-bezier(0.22, 1, 0.36, 1) both; }
@keyframes lmsRiseIn { from { opacity: 0; transform: translateY(16px); } to { opacity: 1; transform: translateY(0); } }
.lms-hero__bar { position: absolute; left: 0; top: 0; bottom: 0; width: 5px; background: linear-gradient(180deg, #8b5cf6, #6366f1); }
.lms-hero__glow { position: absolute; top: -70px; right: -40px; width: 240px; height: 240px; border-radius: 50%; background: radial-gradient(circle at 50% 50%, rgba(99, 102, 241, 0.08), rgba(99, 102, 241, 0) 70%); pointer-events: none; }
.lms-hero__in { position: relative; padding: 24px 28px 24px 30px; }
.lms-hero__toprow { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; flex-wrap: wrap; }
.lms-chip { display: inline-flex; align-items: center; gap: 7px; padding: 6px 12px; border-radius: 999px; font-size: 11.5px; font-weight: 800; }
.lms-pulse { width: 8px; height: 8px; border-radius: 50%; background: #f59e0b; animation: lmsPulse 2s infinite; }
@keyframes lmsPulse { 0%, 100% { box-shadow: 0 0 0 0 rgba(245, 158, 11, 0.5); } 70% { box-shadow: 0 0 0 8px rgba(245, 158, 11, 0); } }
.lms-hero__title { margin: 16px 0 0; font-size: 23px; font-weight: 800; color: #1e293b; letter-spacing: -0.02em; }
.lms-hero__desc { margin: 7px 0 0; font-size: 14px; line-height: 1.6; color: #64748b; max-width: 640px; }
.lms-metas { display: flex; flex-wrap: wrap; gap: 9px; margin-top: 16px; }
.lms-meta { display: inline-flex; align-items: center; gap: 7px; padding: 8px 13px; border-radius: 12px; background: #f6f7fb; color: #64748b; font-size: 12.5px; font-weight: 600; }
.lms-meta :deep(svg) { flex: 0 0 auto; }

.lms-prog { margin-top: 20px; background: #f8f9fc; border: 1px solid #eef0f7; border-radius: 18px; padding: 16px 18px; }
.lms-prog__head { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 12px; }
.lms-prog__head .lms-sec-label { letter-spacing: 0.1em; }
.lms-prog__bar { height: 7px; border-radius: 99px; background: #eef0f7; overflow: hidden; margin-bottom: 16px; }
.lms-prog__bar div { height: 100%; border-radius: 99px; background: linear-gradient(90deg, #8b5cf6, #6366f1); }
.lms-steps { display: flex; align-items: flex-start; gap: 0; overflow-x: auto; padding-bottom: 6px; }
.lms-step { flex: 0 0 130px; display: flex; flex-direction: column; align-items: center; position: relative; }
.lms-step__line { position: absolute; top: 14px; left: -50%; width: 100%; height: 3px; }
.lms-step__node { position: relative; z-index: 1; width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 800; background: #eef0f7; color: #94a3b8; }
.lms-step__node.is-done { background: linear-gradient(135deg, #8b5cf6, #6366f1); color: #fff; }
.lms-step__node.is-current { background: #fff; color: #4f46e5; border: 2px solid #f59e0b; box-shadow: 0 0 0 4px rgba(245, 158, 11, 0.18); }
.lms-step__node.is-fail { background: #fff; color: #dc2626; border: 2px solid #ef4444; box-shadow: 0 0 0 4px rgba(239, 68, 68, 0.14); }
.lms-step__lbl { margin-top: 9px; font-size: 10.5px; font-weight: 700; text-align: center; line-height: 1.3; padding: 0 6px; }

.lms-gugurnote { display: inline-flex; align-items: center; gap: 6px; margin-top: 14px; font-size: 12.5px; font-weight: 700; color: #dc2626; background: rgba(239, 68, 68, 0.08); border-radius: 10px; padding: 8px 12px; }
.lms-btn-detail { appearance: none; cursor: pointer; font-family: inherit; font-size: 13.5px; font-weight: 800; color: #fff; background: linear-gradient(135deg, #8b5cf6, #6366f1); border: none; padding: 13px 24px; border-radius: 14px; display: inline-flex; align-items: center; gap: 9px; box-shadow: 0 10px 24px rgba(99, 102, 241, 0.3); transition: transform 0.16s; text-decoration: none; }
.lms-btn-detail:hover { transform: translateY(-2px); color: #fff; }
.lms-btn-hapus { appearance: none; cursor: pointer; width: 44px; height: 44px; border-radius: 13px; border: 1px solid #f4c9c9; background: #fff; color: #dc2626; display: inline-flex; align-items: center; justify-content: center; transition: all 0.16s; }
.lms-btn-hapus:hover:not(:disabled) { background: #dc2626; border-color: #dc2626; color: #fff; }
.lms-btn-hapus:disabled { opacity: 0.55; cursor: default; }

/* ═══ RIWAYAT ═══ */
.lms-histrow { display: flex; align-items: center; justify-content: space-between; gap: 14px; flex-wrap: wrap; margin: 34px 2px 4px; }
.lms-fbtn { appearance: none; cursor: pointer; font-family: inherit; font-size: 12px; font-weight: 700; padding: 7px 14px; border-radius: 10px; transition: all 0.16s; border: 1px solid #e6e9f3; background: #fff; color: #64748b; }
.lms-fbtn.is-on { border-color: transparent; background: linear-gradient(135deg, #8b5cf6, #6366f1); color: #fff; }
.lms-tl { position: relative; margin-top: 14px; padding-left: 34px; }
.lms-tl__line { position: absolute; left: 15px; top: 6px; bottom: 6px; width: 2px; background: linear-gradient(180deg, #e2e8f0, #eef0f7); }
.lms-tl__item { position: relative; animation: lmsRiseIn 0.45s cubic-bezier(0.22, 1, 0.36, 1) both; }
.lms-tl__dot { position: absolute; left: -27px; top: 22px; width: 16px; height: 16px; border-radius: 50%; background: #fff; border: 3px solid #94a3b8; box-shadow: 0 0 0 4px #f4f6fc; }
.lms-hcard { appearance: none; cursor: pointer; font-family: inherit; width: 100%; background: #fff; border: 1px solid #eef0f7; border-radius: 18px; padding: 16px 18px; transition: all 0.18s; box-shadow: 0 3px 12px rgba(15, 23, 42, 0.04); }
.lms-hcard:hover { border-color: #d9def0; box-shadow: 0 12px 28px rgba(15, 23, 42, 0.08); transform: translateY(-1px); }
.lms-hcard__row { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; width: 100%; }
.lms-type { display: inline-block; font-size: 9.5px; font-weight: 800; letter-spacing: 0.08em; text-transform: uppercase; padding: 4px 9px; border-radius: 7px; }
.lms-hcard__title { font-size: 16px; font-weight: 800; color: #1e293b; letter-spacing: -0.01em; margin-top: 8px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.lms-hcard__meta { display: flex; align-items: center; gap: 14px; margin-top: 7px; font-size: 12px; color: #8792a6; flex-wrap: wrap; }
.lms-hcard__meta span { display: inline-flex; align-items: center; gap: 5px; }
.lms-pill { display: inline-block; padding: 5px 12px; border-radius: 999px; font-size: 11px; font-weight: 800; white-space: nowrap; }

/* ═══ REKOMENDASI ═══ */
.lms-recrow { display: flex; align-items: center; gap: 9px; margin: 38px 2px 14px; }
.lms-recrow__ico { width: 30px; height: 30px; border-radius: 10px; background: linear-gradient(135deg, #8b5cf6, #6366f1); color: #fff; display: flex; align-items: center; justify-content: center; }
.lms-recs { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; }
.lms-rec { position: relative; background: #fff; border-radius: 20px; padding: 20px; box-shadow: 0 6px 20px rgba(15, 23, 42, 0.05); transition: all 0.2s; overflow: hidden; }
.lms-rec:hover { transform: translateY(-3px); box-shadow: 0 16px 36px rgba(15, 23, 42, 0.1); }
.lms-rec__glow { position: absolute; top: -40px; right: -40px; width: 130px; height: 130px; border-radius: 50%; pointer-events: none; }
.lms-rec__title { position: relative; font-size: 16.5px; font-weight: 800; color: #1e293b; letter-spacing: -0.01em; margin-top: 14px; }
.lms-rec__meta { position: relative; display: flex; align-items: center; gap: 12px; margin-top: 9px; font-size: 12.5px; color: #8792a6; }
.lms-rec__meta span { display: inline-flex; align-items: center; gap: 5px; }
.lms-rec__match { position: relative; display: flex; align-items: center; gap: 10px; margin-top: 16px; }
.lms-rec__matchbar { flex: 1; height: 7px; border-radius: 99px; background: #eef0f7; overflow: hidden; }
.lms-rec__matchbar div { height: 100%; border-radius: 99px; background: linear-gradient(90deg, #8b5cf6, #6366f1); }
.lms-rec__btn { position: relative; appearance: none; cursor: pointer; font-family: inherit; width: 100%; margin-top: 16px; font-size: 13.5px; font-weight: 800; color: #fff; background: linear-gradient(135deg, #8b5cf6, #6366f1); border: none; padding: 12px; border-radius: 13px; display: inline-flex; align-items: center; justify-content: center; gap: 8px; box-shadow: 0 8px 20px rgba(99, 102, 241, 0.28); transition: transform 0.16s; text-decoration: none; }
.lms-rec__btn:hover { transform: translateY(-2px); color: #fff; }

/* ═══ DRAWER ═══ */
.lms-overlay { position: fixed; inset: 0; z-index: 1055; background: rgba(15, 23, 42, 0.42); backdrop-filter: blur(2px); transition: opacity 0.32s; opacity: 0; pointer-events: none; }
.lms-overlay.is-on { opacity: 1; pointer-events: auto; }
.lms-drawer { position: fixed; top: 0; right: 0; bottom: 0; z-index: 1056; width: 560px; max-width: 100%; background: #f6f7fb; box-shadow: -30px 0 80px rgba(15, 23, 42, 0.2); overflow-y: auto; transition: transform 0.4s cubic-bezier(0.22, 1, 0.36, 1); transform: translateX(104%); }
.lms-drawer.is-on { transform: translateX(0); }
.lms-drawer__head { position: sticky; top: 0; z-index: 5; background: linear-gradient(180deg, #ffffff, #fbfbfe); border-bottom: 1px solid #eef0f7; padding: 18px 22px; }
.lms-drawer__ico { width: 50px; height: 50px; border-radius: 15px; background: linear-gradient(135deg, #8b5cf6, #6366f1); color: #fff; display: flex; align-items: center; justify-content: center; flex: 0 0 auto; box-shadow: 0 10px 24px rgba(99, 102, 241, 0.28); }
.lms-drawer__title { font-size: 18px; font-weight: 800; color: #0f172a; letter-spacing: -0.015em; margin-top: 6px; line-height: 1.25; }
.lms-drawer__close { appearance: none; border: 1px solid #e6e9f3; background: #fff; width: 38px; height: 38px; border-radius: 11px; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.16s; color: #64748b; flex: 0 0 auto; }
.lms-drawer__close:hover { background: #f1f5f9; color: #0f172a; }
.lms-drawer__body { padding: 20px 22px 40px; display: flex; flex-direction: column; gap: 20px; }
.lms-banner { display: flex; gap: 13px; padding: 15px 17px; border-radius: 16px; border: 1px solid transparent; }
.lms-banner__ico { width: 38px; height: 38px; border-radius: 11px; flex: 0 0 auto; display: flex; align-items: center; justify-content: center; color: #fff; }

.lms-stl { position: relative; padding-left: 30px; }
.lms-stl__line { position: absolute; left: 13px; top: 8px; bottom: 8px; width: 2px; background: #eef0f7; }
.lms-stl__node { position: absolute; left: -30px; top: 1px; width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; background: #fff; border: 3px solid #cbd2e0; }
.lms-stl__node span { width: 9px; height: 9px; border-radius: 50%; display: block; }
.lms-stl__tag { flex: 0 0 auto; font-size: 10.5px; font-weight: 800; padding: 4px 9px; border-radius: 999px; white-space: nowrap; }

.lms-form { background: #fff; border: 1px solid #eef0f7; border-radius: 18px; overflow: hidden; box-shadow: 0 2px 8px rgba(15, 23, 42, 0.03); transition: border-color 0.2s; }
.lms-form.is-open { border-color: #d9def0; }
.lms-form__head { appearance: none; border: none; background: #fff; width: 100%; display: flex; align-items: center; gap: 13px; padding: 15px 18px; cursor: pointer; text-align: left; font-family: inherit; }
.lms-form.is-open .lms-form__head { background: #fbfbff; }
.lms-form__step { width: 34px; height: 34px; border-radius: 10px; background: linear-gradient(135deg, #cbd2e0, #94a3b8); color: #fff; font-size: 13px; font-weight: 800; display: flex; align-items: center; justify-content: center; flex: 0 0 auto; }
.lms-form__step.is-ok { background: linear-gradient(135deg, #8b5cf6, #6366f1); }
.lms-form__title { display: block; font-size: 14.5px; font-weight: 800; color: #1e293b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.lms-form__sub { display: block; font-size: 11.5px; color: #8b93a7; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.lms-form__pill { flex: 0 0 auto; font-size: 10.5px; font-weight: 800; letter-spacing: 0.04em; padding: 4px 10px; border-radius: 999px; white-space: nowrap; }
.lms-form__pill.is-ok { background: rgba(16, 185, 129, 0.12); color: #059669; }
.lms-form__pill.is-wait { background: rgba(245, 158, 11, 0.14); color: #b45309; }
.lms-form__chev { flex: 0 0 auto; transition: transform 0.26s; }
.lms-form.is-open .lms-form__chev { transform: rotate(180deg); }
.lms-form__body { padding: 6px 18px 18px; animation: lmsAccIn 0.28s cubic-bezier(0.22, 1, 0.36, 1) both; }
@keyframes lmsAccIn { from { opacity: 0; transform: translateY(-6px); } to { opacity: 1; transform: translateY(0); } }
.lms-fields { display: grid; grid-template-columns: 1fr 1fr; gap: 1px; background: #eef0f7; border: 1px solid #eef0f7; border-radius: 14px; overflow: hidden; margin-top: 8px; }
.lms-field { background: #fff; padding: 11px 14px; min-width: 0; }
.lms-field__k { font-size: 10.5px; font-weight: 700; letter-spacing: 0.08em; color: #a2a9ba; text-transform: uppercase; }
.lms-field__v { font-size: 13.5px; font-weight: 700; color: #1e293b; margin-top: 3px; word-break: break-word; }
.lms-doc { display: flex; align-items: center; gap: 13px; padding: 12px 14px; border: 1px solid #eef0f7; border-radius: 14px; background: #fbfbfe; }
.lms-doc__ico { width: 40px; height: 40px; border-radius: 11px; display: flex; align-items: center; justify-content: center; flex: 0 0 auto; }
.lms-doc__ico.is-img { background: rgba(99, 102, 241, 0.12); }
.lms-doc__ico.is-pdf { background: rgba(239, 68, 68, 0.1); }
.lms-doc__name { font-size: 13.5px; font-weight: 800; color: #1e293b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.lms-doc__ext { font-size: 9px; font-weight: 800; letter-spacing: 0.06em; color: #8b93a7; background: #eef0f7; border-radius: 5px; padding: 2px 6px; flex: 0 0 auto; }
.lms-doc__desc { font-size: 11.5px; color: #8b93a7; margin-top: 2px; line-height: 1.4; }
.lms-doc__eye { appearance: none; border: 1px solid #d9def0; background: #fff; width: 38px; height: 38px; border-radius: 11px; display: flex; align-items: center; justify-content: center; cursor: pointer; color: #6366f1; flex: 0 0 auto; transition: all 0.16s; }
.lms-doc__eye:hover { background: #6366f1; color: #fff; border-color: #6366f1; }

/* ═══ LIGHTBOX ═══ */
.lms-lb { position: fixed; inset: 0; z-index: 1090; display: flex; align-items: center; justify-content: center; padding: 24px; background: rgba(10, 10, 20, 0.72); backdrop-filter: blur(6px); transition: opacity 0.28s; opacity: 0; pointer-events: none; }
.lms-lb.is-on { opacity: 1; pointer-events: auto; }
.lms-lb__wrap { max-width: 520px; width: 100%; animation: lmsPop 0.3s cubic-bezier(0.34, 1.56, 0.64, 1) both; }
@keyframes lmsPop { 0% { opacity: 0; transform: scale(0.9); } 60% { transform: scale(1.02); } 100% { opacity: 1; transform: scale(1); } }
.lms-lb__bar { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 14px; }
.lms-lb__ico { width: 40px; height: 40px; border-radius: 11px; background: rgba(255, 255, 255, 0.14); display: flex; align-items: center; justify-content: center; flex: 0 0 auto; }
.lms-lb__close { appearance: none; border: 1px solid rgba(255, 255, 255, 0.2); background: rgba(255, 255, 255, 0.08); width: 40px; height: 40px; border-radius: 12px; display: flex; align-items: center; justify-content: center; cursor: pointer; color: #fff; flex: 0 0 auto; transition: background 0.16s; }
.lms-lb__close:hover { background: rgba(255, 255, 255, 0.18); }
.lms-lb__card { background: #fff; border-radius: 18px; overflow: hidden; box-shadow: 0 30px 80px rgba(0, 0, 0, 0.5); }
.lms-lb__ph { width: 100%; height: 360px; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 10px; background: linear-gradient(135deg, #f1f2f9, #e8eaf6); color: #94a3b8; font-size: 13px; font-weight: 700; }
.lms-lb__foot { padding: 14px 18px; display: flex; align-items: center; justify-content: space-between; gap: 10px; border-top: 1px solid #eef0f7; }

/* ═══ MODAL HAPUS + TOAST ═══ */
.lms-modal { position: fixed; inset: 0; z-index: 1095; background: rgba(15, 23, 42, 0.45); backdrop-filter: blur(2px); display: flex; align-items: center; justify-content: center; padding: 1rem; }
.lms-modal__box { width: 100%; max-width: 26rem; background: #fff; border-radius: 20px; padding: 1.6rem; text-align: center; box-shadow: 0 24px 60px -20px rgba(15, 23, 42, 0.5); animation: lmsPop 0.3s cubic-bezier(0.34, 1.56, 0.64, 1) both; }
.lms-modal__ic { width: 52px; height: 52px; margin: 0 auto 0.9rem; border-radius: 15px; display: flex; align-items: center; justify-content: center; background: rgba(239, 68, 68, 0.1); color: #dc2626; }
.lms-modal__box h3 { margin: 0 0 0.4rem; font-size: 17px; font-weight: 800; color: #0f172a; letter-spacing: -0.015em; }
.lms-modal__box p { font-size: 13px; color: #64748b; line-height: 1.55; margin: 0 0 1.2rem; }
.lms-modal__act { display: flex; gap: 0.6rem; justify-content: center; }
.lms-modal__soft { appearance: none; cursor: pointer; font-family: inherit; font-size: 13px; font-weight: 700; padding: 11px 18px; border-radius: 12px; border: 1px solid #e6e9f3; background: #fff; color: #64748b; transition: background 0.16s; }
.lms-modal__soft:hover { background: #f6f7fb; }
.lms-modal__danger { appearance: none; cursor: pointer; font-family: inherit; font-size: 13px; font-weight: 800; padding: 11px 18px; border-radius: 12px; border: none; background: linear-gradient(135deg, #f87171, #ef4444); color: #fff; display: inline-flex; align-items: center; gap: 7px; box-shadow: 0 8px 20px rgba(239, 68, 68, 0.28); }
.lms-modal__danger:disabled { opacity: 0.6; cursor: default; }

.lms-toast { position: fixed; bottom: 24px; right: 24px; z-index: 1100; display: flex; align-items: center; gap: 9px; padding: 12px 18px; border-radius: 13px; background: #0f172a; color: #fff; font-size: 13.5px; font-weight: 700; box-shadow: 0 18px 40px rgba(0, 0, 0, 0.3); }
.lms-toast.is-err { background: #dc2626; }
.lms-toast .bi { color: #34d399; }
.lms-toast.is-err .bi { color: #fff; }
.lms-toast-enter-active, .lms-toast-leave-active { transition: opacity 0.25s, transform 0.25s; }
.lms-toast-enter-from, .lms-toast-leave-to { opacity: 0; transform: translateY(12px); }

/* ═══ RESPONSIF ═══ */
@media (max-width: 1179.98px) {
    .lms-recs { grid-template-columns: repeat(2, 1fr); }
    .lms-drawer { width: 460px; }
}
@media (max-width: 759.98px) {
    .lms { padding: 20px 16px 44px; }
    .lms-stats { grid-template-columns: 1fr 1fr; gap: 14px; }
    .lms-recs { grid-template-columns: 1fr; }
    .lms-drawer { width: 100%; }
    .lms-fields { grid-template-columns: 1fr; }
    .lms-hero__in { padding: 20px 18px; }
}
</style>
