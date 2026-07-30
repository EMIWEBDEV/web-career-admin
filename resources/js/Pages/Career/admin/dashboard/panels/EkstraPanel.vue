<!--
  ZONA E — EMPAT PANEL EKSTRA.

  Yang paling berharga di sini adalah kartu OPERASIONAL: dua angka yang saat ini
  tidak ditampilkan halaman mana pun. "Lamaran gagal masuk" adalah orang yang
  sudah menekan Lamar lalu hilang tanpa jejak selain baris log, dan "berkas
  menunggu verifikasi" adalah pekerjaan yang menumpuk tanpa terlihat.
-->
<template>
    <div class="wcd-grid wcd-grid--2">
        <!-- ══════ TES ══════ -->
        <div class="wcd-card wcd-card--datar">
            <h3 class="wcd-card__hd"><i class="bi bi-pencil-square"></i> Kehadiran &amp; Nilai Tes</h3>

            <KeadaanPanel v-if="!ekstra.tes.ringkas" keadaan="kosong" rapat ikon="bi-clipboard-x"
                teks="Belum ada peserta tes" ket="Muncul setelah penjadwalan tes berjalan." />
            <template v-else>
                <div class="wcd-kpi wcd-kpi--rapat ex-mini">
                    <div class="wcd-stat" :style="{ '--tone': '#0284c7' }">
                        <div class="wcd-stat__num">{{ angka(ekstra.tes.ringkas.peserta) }}</div>
                        <div class="wcd-stat__lbl">Peserta</div>
                    </div>
                    <div class="wcd-stat" :style="{ '--tone': STATUS.good.warna }">
                        <div class="wcd-stat__num">{{ angka(ekstra.tes.ringkas.selesai) }}</div>
                        <div class="wcd-stat__lbl">Selesai mengerjakan</div>
                    </div>
                    <div class="wcd-stat" :style="{ '--tone': STATUS.critical.warna }">
                        <div class="wcd-stat__num">{{ angka(ekstra.tes.ringkas.belumAkses) }}</div>
                        <div class="wcd-stat__lbl">Belum membuka tes</div>
                    </div>
                    <div class="wcd-stat" :style="{ '--tone': STATUS.warning.warna }">
                        <div class="wcd-stat__num">{{ angka(ekstra.tes.ringkas.timeout) }}</div>
                        <div class="wcd-stat__lbl">Kehabisan waktu</div>
                    </div>
                </div>

                <p v-if="ekstra.tes.ringkas.rataNilai !== null" class="ex-nota">
                    <i class="bi bi-bar-chart-line"></i>
                    Nilai rata-rata <b>{{ desimal(ekstra.tes.ringkas.rataNilai, 2) }}</b>
                    (terendah {{ desimal(ekstra.tes.ringkas.nilaiMin, 2) }},
                    tertinggi {{ desimal(ekstra.tes.ringkas.nilaiMax, 2) }})
                </p>
                <p v-if="ekstra.tes.ringkas.gagalKirim" class="ex-nota is-buruk">
                    <i class="bi" :class="STATUS.critical.ikon"></i>
                    {{ angka(ekstra.tes.ringkas.gagalKirim) }} undangan tes gagal terkirim —
                    peserta tidak menerima tautan ujiannya.
                </p>

                <div v-if="ekstra.tes.perSesi.length" class="wcd-tw ex-tw">
                    <table class="wcd-tbl">
                        <thead>
                            <tr><th>Sesi</th><th class="wcd-num">Peserta</th><th class="wcd-num">Selesai</th><th class="wcd-num">Rata nilai</th></tr>
                        </thead>
                        <tbody>
                            <tr v-for="(s, i) in ekstra.tes.perSesi" :key="i">
                                <td class="wcd-tbl__utama">
                                    {{ s.label }}
                                    <span class="wcd-tbl__sub">{{ s.mulai ? tglJam(s.mulai) : 'Tanpa jadwal' }}</span>
                                </td>
                                <td class="wcd-num">{{ angka(s.peserta) }}</td>
                                <td class="wcd-num">{{ angka(s.selesai) }}</td>
                                <td class="wcd-num">
                                    {{ s.rataNilai === null ? '—' : desimal(s.rataNilai, 2) }}
                                    <small v-if="s.ambang !== null" :title="`Ambang lulus ${s.ambang}`">/ {{ desimal(s.ambang, 0) }}</small>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </template>
        </div>

        <!-- ══════ OPERASIONAL ══════ -->
        <div class="wcd-card wcd-card--datar">
            <h3 class="wcd-card__hd">
                <i class="bi bi-tools"></i> Operasional Tersembunyi
                <small>tidak tampil di halaman lain</small>
            </h3>

            <div class="wcd-kpi wcd-kpi--rapat ex-mini">
                <div class="wcd-stat" :style="{ '--tone': ekstra.operasional.applyGagal ? STATUS.critical.warna : '#64748b' }">
                    <div class="wcd-stat__num">{{ angka(ekstra.operasional.applyGagal) }}</div>
                    <div class="wcd-stat__lbl">Lamaran gagal masuk</div>
                    <div class="wcd-stat__ket">pelamar hilang tanpa jejak</div>
                </div>
                <div class="wcd-stat" :style="{ '--tone': ekstra.operasional.applyMenunggu ? STATUS.warning.warna : '#64748b' }">
                    <div class="wcd-stat__num">{{ angka(ekstra.operasional.applyMenunggu) }}</div>
                    <div class="wcd-stat__lbl">Antre diproses</div>
                    <div class="wcd-stat__ket">menunggu job antrean</div>
                </div>
                <div class="wcd-stat" :style="{ '--tone': ekstra.operasional.berkasMenunggu ? '#d97706' : '#64748b' }">
                    <div class="wcd-stat__num">{{ angka(ekstra.operasional.berkasMenunggu) }}</div>
                    <div class="wcd-stat__lbl">Berkas menunggu verifikasi</div>
                </div>
                <div class="wcd-stat" :style="{ '--tone': '#0ca30c' }">
                    <div class="wcd-stat__num">{{ angka(ekstra.operasional.applySelesai) }}</div>
                    <div class="wcd-stat__lbl">Lamaran berhasil dibentuk</div>
                </div>
            </div>

            <p v-if="ekstra.operasional.applyGagal" class="ex-nota is-buruk">
                <i class="bi" :class="STATUS.critical.ikon"></i>
                Perlu ditindak: pelamar ini tidak ada di worklist mana pun dan tidak tahu
                lamarannya gagal. Rinciannya ada di seksi <b>Butuh Aksi Kamu</b>.
            </p>
            <p v-else class="ex-nota">
                <i class="bi" :class="STATUS.good.ikon"></i> Tidak ada lamaran yang gagal terbentuk.
            </p>
        </div>

        <!-- ══════ FEEDBACK ══════ -->
        <div class="wcd-card wcd-card--datar">
            <h3 class="wcd-card__hd">
                <i class="bi bi-chat-heart-fill"></i> Pengalaman Kandidat
                <small><a href="/karir/feedback-dashboard">Dashboard lengkap →</a></small>
            </h3>

            <KeadaanPanel v-if="!ekstra.feedback.dibuat" keadaan="kosong" rapat ikon="bi-chat-square-dots"
                teks="Belum ada formulir feedback terkirim"
                ket="Feedback dikirim otomatis setelah keputusan akhir kandidat." />
            <template v-else>
                <div class="ex-gauge">
                    <div class="ex-gauge__i">
                        <b>{{ ekstra.feedback.responseRate === null ? '—' : ekstra.feedback.responseRate + '%' }}</b>
                        <span>Response rate</span>
                        <div class="wcd-meter">
                            <span :style="{ width: (ekstra.feedback.responseRate || 0) + '%' }"></span>
                        </div>
                        <small>{{ angka(ekstra.feedback.terisi) }} terisi dari {{ angka(ekstra.feedback.dibuat) }} dikirim</small>
                    </div>
                    <div class="ex-gauge__i">
                        <b>{{ ekstra.feedback.rataRating === null ? '—' : ekstra.feedback.rataRating }}<small v-if="ekstra.feedback.rataRating !== null">/100</small></b>
                        <span>Indeks kepuasan</span>
                        <div class="wcd-meter">
                            <span :style="{ width: (ekstra.feedback.rataRating || 0) + '%', background: nadaPuas }"></span>
                        </div>
                        <!-- Skala tiap form bisa 1-5 atau 1-10, jadi nilainya
                             dinormalkan dulu ke 0-100 sebelum dirata-rata. -->
                        <small>rata-rata rating, dinormalkan ke skala 100</small>
                    </div>
                </div>
            </template>
        </div>

        <!-- ══════ TALENT POOL ══════ -->
        <div class="wcd-card wcd-card--datar">
            <h3 class="wcd-card__hd">
                <i class="bi bi-bookmark-star-fill"></i> Talent Pool
                <small><a href="/karir/talent-pool">Kelola →</a></small>
            </h3>

            <KeadaanPanel v-if="!ekstra.talent.total" keadaan="kosong" rapat ikon="bi-bookmark-dash"
                teks="Talent pool masih kosong"
                ket="Kandidat masuk ke sini saat diputus TALENT_POOL di worklist." />
            <template v-else>
                <div class="wcd-kpi wcd-kpi--rapat ex-mini">
                    <div class="wcd-stat" :style="{ '--tone': '#7c3aed' }">
                        <div class="wcd-stat__num">{{ angka(ekstra.talent.aktif) }}</div>
                        <div class="wcd-stat__lbl">Siap ditarik</div>
                    </div>
                    <div class="wcd-stat" :style="{ '--tone': STATUS.warning.warna }">
                        <div class="wcd-stat__num">{{ angka(ekstra.talent.segeraHabis) }}</div>
                        <div class="wcd-stat__lbl">Kedaluwarsa ≤ 30 hari</div>
                    </div>
                    <div class="wcd-stat" :style="{ '--tone': '#64748b' }">
                        <div class="wcd-stat__num">{{ angka(ekstra.talent.kedaluwarsa) }}</div>
                        <div class="wcd-stat__lbl">Sudah kedaluwarsa</div>
                    </div>
                    <div class="wcd-stat" :style="{ '--tone': STATUS.good.warna }">
                        <div class="wcd-stat__num">{{ angka(ekstra.talent.ditarik) }}</div>
                        <div class="wcd-stat__lbl">Pernah ditarik</div>
                    </div>
                </div>
                <p v-if="ekstra.talent.segeraHabis" class="ex-nota">
                    <i class="bi" :class="STATUS.warning.ikon"></i>
                    {{ angka(ekstra.talent.segeraHabis) }} kandidat akan kedaluwarsa dalam 30 hari —
                    setelah itu tidak bisa ditarik lagi tanpa diperpanjang.
                </p>
            </template>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { angka, desimal, tglJam, STATUS } from '../dashboardHelpers';
import KeadaanPanel from '../../monitoring/KeadaanPanel.vue';

const props = defineProps({ ekstra: { type: Object, required: true } });

const nadaPuas = computed(() => {
    const v = props.ekstra.feedback.rataRating;
    if (v === null) return '#cbd5e1';
    if (v >= 75) return STATUS.good.warna;
    if (v >= 50) return STATUS.warning.warna;
    return STATUS.critical.warna;
});
</script>

<style scoped>
.ex-mini .wcd-stat { padding: 11px 13px; }
.ex-mini .wcd-stat__num { font-size: 1.3rem; }
.ex-mini .wcd-stat__lbl { font-size: 0.71rem; }
.ex-mini .wcd-stat__ket { font-size: 0.67rem; }

.ex-nota {
    margin: 11px 0 0;
    font-size: 0.73rem;
    line-height: 1.6;
    color: #475569;
}
.ex-nota .bi { color: #94a3b8; }
.ex-nota b { color: #0f172a; }
.ex-nota.is-buruk { color: #b91c1c; }
.ex-nota.is-buruk .bi { color: #d03b3b; }
.ex-nota.is-buruk b { color: #7f1d1d; }

.ex-tw { margin-top: 12px; max-height: 230px; overflow-y: auto; background: #fff; }

.wcd-card__hd small a { color: #4f46e5; text-decoration: none; font-weight: 800; }
.wcd-card__hd small a:hover { text-decoration: underline; }

.ex-gauge { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 14px; }
.ex-gauge__i { padding: 12px 13px; background: #fff; border: 1px solid #eef2f7; border-radius: 13px; }
.ex-gauge__i b { display: block; font-size: 1.5rem; font-weight: 900; color: #0f172a; line-height: 1; }
.ex-gauge__i b small { font-size: 0.8rem; color: #94a3b8; font-weight: 800; }
.ex-gauge__i > span { display: block; margin: 4px 0 8px; font-size: 0.73rem; font-weight: 700; color: #475569; }
.ex-gauge__i small { display: block; margin-top: 6px; font-size: 0.68rem; color: #94a3b8; }
</style>
