<!--
  ZONA E — EMPAT PANEL EKSTRA (Redesigned)
-->
<template>
    <div class="wcd-grid wcd-grid--2">
        <!-- ══════ TES ══════ -->
        <div class="wcd-card">
            <h3 class="wcd-card__hd">
                <i class="bi bi-pencil-square"></i>
                <span>Kehadiran &amp; Nilai Tes</span>
            </h3>

            <KeadaanPanel v-if="!ekstra.tes.ringkas" keadaan="kosong" rapat ikon="bi-clipboard-x"
                teks="Belum ada peserta tes" ket="Muncul setelah penjadwalan tes berjalan." />
            <template v-else>
                <div class="ex-mini-grid">
                    <div class="ex-stat-item" :style="{ '--tone': '#0284c7' }">
                        <div class="ex-stat-item__num">{{ angka(ekstra.tes.ringkas.peserta) }}</div>
                        <div class="ex-stat-item__lbl">Peserta</div>
                    </div>
                    <div class="ex-stat-item" :style="{ '--tone': STATUS.good.warna }">
                        <div class="ex-stat-item__num">{{ angka(ekstra.tes.ringkas.selesai) }}</div>
                        <div class="ex-stat-item__lbl">Selesai</div>
                    </div>
                    <div class="ex-stat-item" :style="{ '--tone': STATUS.critical.warna }">
                        <div class="ex-stat-item__num">{{ angka(ekstra.tes.ringkas.belumAkses) }}</div>
                        <div class="ex-stat-item__lbl">Belum buka</div>
                    </div>
                    <div class="ex-stat-item" :style="{ '--tone': STATUS.warning.warna }">
                        <div class="ex-stat-item__num">{{ angka(ekstra.tes.ringkas.timeout) }}</div>
                        <div class="ex-stat-item__lbl">Timeout</div>
                    </div>
                </div>

                <p v-if="ekstra.tes.ringkas.rataNilai !== null" class="ex-nota">
                    <i class="bi bi-bar-chart-line-fill"></i>
                    Rata-rata: <b>{{ desimal(ekstra.tes.ringkas.rataNilai, 2) }}</b>
                    (Min {{ desimal(ekstra.tes.ringkas.nilaiMin, 2) }},
                    Max {{ desimal(ekstra.tes.ringkas.nilaiMax, 2) }})
                </p>
                <p v-if="ekstra.tes.ringkas.gagalKirim" class="ex-nota is-buruk">
                    <i class="bi" :class="STATUS.critical.ikon"></i>
                    {{ angka(ekstra.tes.ringkas.gagalKirim) }} undangan tes gagal terkirim.
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
                                    <b>{{ s.rataNilai === null ? '—' : desimal(s.rataNilai, 2) }}</b>
                                    <small v-if="s.ambang !== null" :title="`Ambang lulus ${s.ambang}`">/ {{ desimal(s.ambang, 0) }}</small>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </template>
        </div>

        <!-- ══════ OPERASIONAL ══════ -->
        <div class="wcd-card">
            <h3 class="wcd-card__hd">
                <i class="bi bi-tools"></i>
                <span>Operasional Tersembunyi</span>
                <small class="ex-hd-tag">Aktivitas Sistem</small>
            </h3>

            <div class="ex-mini-grid">
                <div class="ex-stat-item" :style="{ '--tone': ekstra.operasional.applyGagal ? STATUS.critical.warna : '#64748b' }">
                    <div class="ex-stat-item__num">{{ angka(ekstra.operasional.applyGagal) }}</div>
                    <div class="ex-stat-item__lbl">Lamaran gagal</div>
                    <div class="ex-stat-item__ket">gagal masuk</div>
                </div>
                <div class="ex-stat-item" :style="{ '--tone': ekstra.operasional.applyMenunggu ? STATUS.warning.warna : '#64748b' }">
                    <div class="ex-stat-item__num">{{ angka(ekstra.operasional.applyMenunggu) }}</div>
                    <div class="ex-stat-item__lbl">Antre diproses</div>
                    <div class="ex-stat-item__ket">menunggu job</div>
                </div>
                <div class="ex-stat-item" :style="{ '--tone': ekstra.operasional.berkasMenunggu ? '#d97706' : '#64748b' }">
                    <div class="ex-stat-item__num">{{ angka(ekstra.operasional.berkasMenunggu) }}</div>
                    <div class="ex-stat-item__lbl">Verifikasi berkas</div>
                </div>
                <div class="ex-stat-item" :style="{ '--tone': '#10b981' }">
                    <div class="ex-stat-item__num">{{ angka(ekstra.operasional.applySelesai) }}</div>
                    <div class="ex-stat-item__lbl">Berhasil dibentuk</div>
                </div>
            </div>

            <p v-if="ekstra.operasional.applyGagal" class="ex-nota is-buruk">
                <i class="bi" :class="STATUS.critical.ikon"></i>
                Perlu ditindak: pelamar ini tidak ada di worklist mana pun dan tidak tahu lamarannya gagal.
            </p>
            <p v-else class="ex-nota">
                <i class="bi" :class="STATUS.good.ikon"></i> Tidak ada lamaran yang gagal terbentuk.
            </p>
        </div>

        <!-- ══════ FEEDBACK ══════ -->
        <div class="wcd-card">
            <h3 class="wcd-card__hd">
                <i class="bi bi-chat-heart-fill"></i>
                <span>Pengalaman Kandidat</span>
                <small><a href="/karir/feedback-dashboard" class="ex-link">Dashboard →</a></small>
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
                            <span :style="{ width: (ekstra.feedback.responseRate || 0) + '%', background: '#6366f1' }"></span>
                        </div>
                        <small>{{ angka(ekstra.feedback.terisi) }} terisi dari {{ angka(ekstra.feedback.dibuat) }} dikirim</small>
                    </div>
                    <div class="ex-gauge__i">
                        <b>{{ ekstra.feedback.rataRating === null ? '—' : ekstra.feedback.rataRating }}<small v-if="ekstra.feedback.rataRating !== null">/100</small></b>
                        <span>Indeks kepuasan</span>
                        <div class="wcd-meter">
                            <span :style="{ width: (ekstra.feedback.rataRating || 0) + '%', background: nadaPuas }"></span>
                        </div>
                        <small>Rata-rata rating (skala 100)</small>
                    </div>
                </div>
            </template>
        </div>

        <!-- ══════ TALENT POOL ══════ -->
        <div class="wcd-card">
            <h3 class="wcd-card__hd">
                <i class="bi bi-bookmark-star-fill"></i>
                <span>Talent Pool</span>
                <small><a href="/karir/talent-pool" class="ex-link">Kelola →</a></small>
            </h3>

            <KeadaanPanel v-if="!ekstra.talent.total" keadaan="kosong" rapat ikon="bi-bookmark-dash"
                teks="Talent pool masih kosong"
                ket="Kandidat masuk ke sini saat diputus TALENT_POOL di worklist." />
            <template v-else>
                <div class="ex-mini-grid">
                    <div class="ex-stat-item" :style="{ '--tone': '#8b5cf6' }">
                        <div class="ex-stat-item__num">{{ angka(ekstra.talent.aktif) }}</div>
                        <div class="ex-stat-item__lbl">Siap ditarik</div>
                    </div>
                    <div class="ex-stat-item" :style="{ '--tone': STATUS.warning.warna }">
                        <div class="ex-stat-item__num">{{ angka(ekstra.talent.segeraHabis) }}</div>
                        <div class="ex-stat-item__lbl">Kedaluwarsa ≤ 30h</div>
                    </div>
                    <div class="ex-stat-item" :style="{ '--tone': '#64748b' }">
                        <div class="ex-stat-item__num">{{ angka(ekstra.talent.kedaluwarsa) }}</div>
                        <div class="ex-stat-item__lbl">Kedaluwarsa</div>
                    </div>
                    <div class="ex-stat-item" :style="{ '--tone': STATUS.good.warna }">
                        <div class="ex-stat-item__num">{{ angka(ekstra.talent.ditarik) }}</div>
                        <div class="ex-stat-item__lbl">Pernah ditarik</div>
                    </div>
                </div>
                <p v-if="ekstra.talent.segeraHabis" class="ex-nota">
                    <i class="bi" :class="STATUS.warning.ikon"></i>
                    {{ angka(ekstra.talent.segeraHabis) }} kandidat akan kedaluwarsa dalam 30 hari.
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
.wcd-grid { display: grid; gap: 16px; }
.wcd-grid--2 { grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); }

.wcd-card {
    background: #ffffff;
    border: 1px solid rgba(226, 232, 240, 0.9);
    border-radius: 18px;
    padding: 20px;
    box-shadow: 0 2px 8px -2px rgba(15, 23, 42, 0.04);
}

.wcd-card__hd {
    display: flex;
    align-items: center;
    gap: 10px;
    margin: 0 0 16px;
    font-size: 0.9rem;
    font-weight: 900;
    color: #0f172a;
}
.wcd-card__hd .bi { color: #6366f1; font-size: 1.1rem; }
.wcd-card__hd small { margin-left: auto; font-weight: 700; font-size: 0.76rem; }
.ex-hd-tag { color: #94a3b8; }
.ex-link { color: #4f46e5; text-decoration: none; font-weight: 800; }
.ex-link:hover { text-decoration: underline; }

.ex-mini-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
    gap: 10px;
}

.ex-stat-item {
    padding: 12px;
    background: #f8fafc;
    border: 1px solid #f1f5f9;
    border-radius: 14px;
    border-left: 3px solid var(--tone, #6366f1);
}
.ex-stat-item__num { font-size: 1.35rem; font-weight: 900; color: #0f172a; line-height: 1; }
.ex-stat-item__lbl { margin-top: 4px; font-size: 0.74rem; font-weight: 800; color: #334155; }
.ex-stat-item__ket { font-size: 0.68rem; color: #94a3b8; }

.ex-nota {
    margin: 12px 0 0;
    font-size: 0.76rem;
    line-height: 1.5;
    color: #475569;
}
.ex-nota .bi { color: #6366f1; margin-right: 4px; }
.ex-nota b { color: #0f172a; }
.ex-nota.is-buruk { color: #dc2626; font-weight: 600; }
.ex-nota.is-buruk .bi { color: #ef4444; }

.ex-tw { margin-top: 14px; max-height: 240px; overflow-y: auto; background: #fff; border-radius: 12px; }

.ex-gauge { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 12px; }
.ex-gauge__i { padding: 14px; background: #f8fafc; border: 1px solid #f1f5f9; border-radius: 14px; }
.ex-gauge__i b { display: block; font-size: 1.55rem; font-weight: 900; color: #0f172a; line-height: 1; }
.ex-gauge__i b small { font-size: 0.8rem; color: #94a3b8; font-weight: 800; }
.ex-gauge__i > span { display: block; margin: 6px 0 8px; font-size: 0.76rem; font-weight: 800; color: #334155; }
.wcd-meter { height: 6px; border-radius: 999px; background: #e2e8f0; overflow: hidden; margin-bottom: 4px; }
.wcd-meter span { display: block; height: 100%; border-radius: 999px; }
.ex-gauge__i small { display: block; margin-top: 6px; font-size: 0.7rem; color: #94a3b8; }
</style>
