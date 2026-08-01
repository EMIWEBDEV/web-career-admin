<!--
  ZONA B — ANTREAN "BUTUH AKSI KAMU" (Redesigned)
-->
<template>
    <div class="ak-container">
        <!-- Spanduk: lamaran yang gagal terbentuk. -->
        <div v-if="aksi.gagalLamar.total" class="ak-alarm">
            <div class="ak-alarm__ic">
                <i class="bi bi-exclamation-octagon-fill"></i>
            </div>
            <div class="ak-alarm__content">
                <strong>{{ angka(aksi.gagalLamar.total) }} lamaran gagal masuk ke sistem.</strong>
                <p>Pelamar sudah menekan "Lamar" tetapi datanya tidak terbentuk — mereka tidak muncul di worklist mana pun.</p>
            </div>
            <button type="button" class="ak-alarm__btn" @click="pilih = 'gagalLamar'">Lihat daftar</button>
        </div>

        <!-- Penanda kesadaran: pekerjaan yang menunggu admin. -->
        <div v-if="adaTindakan" class="ak-sadar">
            <div class="ak-sadar__kepala">
                <span class="ak-sadar__ic"><i class="bi bi-bell-fill"></i></span>
                <div class="ak-sadar__teks">
                    <strong>{{ angka(aksi.tindakanAdmin.total) }} kandidat menunggu tindakan kamu.</strong>
                    <span>Proses tidak akan berjalan otomatis — periksa antrean jenis pekerjaan di bawah:</span>
                </div>
            </div>

            <!-- Daftar "apa saja yang perlu dilakukan". -->
            <div class="ak-kerja">
                <button v-for="r in aksi.tindakanAdmin.ringkas" :key="r.kode" type="button"
                    class="ak-kerja__item" :class="{ 'is-on': pilih === 'tindakanAdmin' && saring === r.kode }"
                    :title="`Terlama menunggu ${r.terlamaHari} hari`"
                    @click="bukaKerja(r.kode)">
                    <i class="bi" :class="r.ikon"></i>
                    <span class="ak-kerja__aksi">{{ r.aksi }}</span>
                    <em>{{ angka(r.jumlah) }}</em>
                    <span v-if="r.terlamaHari >= ambang.macetHari" class="ak-kerja__tua">
                        <i class="bi bi-clock-history"></i> {{ r.terlamaHari }}h
                    </span>
                </button>
            </div>
        </div>

        <div v-if="totalSemua === 0" class="ak-bersih">
            <div class="ak-bersih__ic">
                <i class="bi bi-check-circle-fill"></i>
            </div>
            <div class="ak-bersih__txt">
                <strong>Semua Bersih &amp; Terkendali!</strong>
                <span>Tidak ada pekerjaan yang menunggumu, keputusan menggantung, tahap macet, atau pembukaan yang segera tutup.</span>
            </div>
        </div>

        <template v-else>
            <!-- Tab keranjang -->
            <div class="ak-tabs" role="tablist">
                <button v-for="k in keranjang" :key="k.id" type="button" role="tab"
                    :aria-selected="pilih === k.id" class="ak-tab" :class="{ 'is-on': pilih === k.id }"
                    :style="{ '--tone': k.warna }" @click="pilih = k.id">
                    <i class="bi" :class="k.ikon"></i>
                    <span>{{ k.label }}</span>
                    <em class="ak-tab__count">{{ angka(k.total) }}</em>
                </button>
            </div>

            <div class="ak-jelas">
                <i class="bi bi-info-circle-fill"></i>
                <span>{{ keranjangAktif.jelas }}</span>
            </div>

            <!-- ── Menunggu tindakan admin ── -->
            <div v-if="pilih === 'tindakanAdmin'">
                <div v-if="saring" class="ak-saring">
                    <span>Disaring ke: <b>{{ labelSaring }}</b></span>
                    <button type="button" @click="saring = ''"><i class="bi bi-x-circle-fill"></i> Tampilkan semua</button>
                </div>

                <div v-if="barisTindakan.length" class="wcd-tw">
                    <table class="wcd-tbl">
                        <thead>
                            <tr>
                                <th>Pelamar</th><th>Tahap</th><th>Yang perlu dilakukan</th>
                                <th class="wcd-num">Menunggu</th><th class="text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="b in barisTindakan" :key="b.id + b.tahap">
                                <td class="wcd-tbl__utama">
                                    <div class="ak-pelamar-cell">
                                        <div class="ak-avatar">{{ inisial(b.nama) }}</div>
                                        <div>
                                            <div class="ak-pelamar-nama">{{ b.nama }}</div>
                                            <span class="wcd-tbl__sub">{{ b.posisi || b.program }}</span>
                                        </div>
                                    </div>
                                </td>
                                <td><span class="ak-tahap-badge">{{ b.tahap }}</span></td>
                                <td>
                                    <span class="ak-kerja__lb">
                                        <i class="bi" :class="ikonKode(b.kode)"></i> {{ b.aksi }}
                                    </span>
                                </td>
                                <td class="wcd-num">
                                    <span class="wcd-lb" :style="nadaMenunggu(b.umurHari)">
                                        <i class="bi" :class="nadaUmur(b.umurHari, ambang.macetHari).ikon"></i>
                                        {{ b.umurHari }} hari
                                    </span>
                                </td>
                                <td class="text-right">
                                    <a :href="b.tautan" class="ak-aksi is-kerja"
                                        :title="b.tautan.includes('penjadwalan')
                                            ? 'Buka Penjadwalan untuk membuat sesi ujiannya'
                                            : 'Buka Worklist, program sudah tersaring'">
                                        Kerjakan <i class="bi bi-arrow-right-short"></i>
                                    </a>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <KeadaanPanel v-else keadaan="kosong" rapat teks="Tidak ada yang menunggu tindakanmu" />
            </div>

            <!-- ── Pembukaan segera tutup ── -->
            <div v-else-if="pilih === 'tutupSegera'" class="wcd-tw">
                <table class="wcd-tbl">
                    <thead>
                        <tr>
                            <th>Program</th><th>Tutup</th><th class="wcd-num">Kursi belum terisi</th>
                            <th class="wcd-num">Pelamar aktif</th><th>Keterangan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="b in aksi.tutupSegera.baris" :key="b.kode">
                            <td class="wcd-tbl__utama">
                                <strong>{{ b.program }}</strong>
                                <span class="wcd-tbl__sub">{{ b.kode }}</span>
                            </td>
                            <td>
                                <span class="wcd-lb" :style="nadaSisa(b.sisaHari)">
                                    <i class="bi bi-hourglass-bottom"></i>
                                    {{ b.sisaHari === 0 ? 'hari ini' : b.sisaHari + ' hari lagi' }}
                                </span>
                                <span class="wcd-tbl__sub">{{ tglJam(b.tutup) }}</span>
                            </td>
                            <td class="wcd-num"><b>{{ angka(b.sisaKursi) }}</b> <small>/ {{ angka(b.kuota) }}</small></td>
                            <td class="wcd-num">{{ angka(b.pelamarAktif) }}</td>
                            <td>
                                <span v-if="b.kandidatKurang" class="wcd-lb"
                                    :style="{ background: '#fef2f2', color: STATUS.critical.warna }">
                                    <i class="bi" :class="STATUS.critical.ikon"></i> Kandidat kurang
                                </span>
                                <span v-else-if="b.sisaKursi === 0" class="wcd-lb"
                                    :style="{ background: '#f0fdf4', color: STATUS.good.warna }">
                                    <i class="bi" :class="STATUS.good.ikon"></i> Kursi penuh
                                </span>
                                <span v-else class="wcd-lb">Cukup kandidat</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- ── Lamaran gagal masuk ── -->
            <div v-else-if="pilih === 'gagalLamar'" class="wcd-tw">
                <table class="wcd-tbl">
                    <thead>
                        <tr><th>Kandidat</th><th>Posisi</th><th>Waktu</th><th class="wcd-num">Coba</th><th>Pesan galat</th></tr>
                    </thead>
                    <tbody>
                        <tr v-for="b in aksi.gagalLamar.baris" :key="b.processId">
                            <td class="wcd-tbl__utama">
                                <strong>{{ b.nama }}</strong>
                                <span class="wcd-tbl__sub">{{ b.program || '—' }}</span>
                            </td>
                            <td>{{ b.posisi || '—' }}</td>
                            <td>{{ tglJam(b.waktu) }}</td>
                            <td class="wcd-num">{{ b.percobaan }}</td>
                            <td class="ak-galat">{{ b.pesan || 'Tidak tercatat' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- ── Tiga keranjang pelamar ── -->
            <div v-else-if="barisAktif.length" class="wcd-tw">
                <table class="wcd-tbl">
                    <thead>
                        <tr><th>Pelamar</th><th>Tahap</th><th class="wcd-num">Menunggu</th><th class="text-right">Aksi</th></tr>
                    </thead>
                    <tbody>
                        <tr v-for="b in barisAktif" :key="b.id">
                            <td class="wcd-tbl__utama">
                                <div class="ak-pelamar-cell">
                                    <div class="ak-avatar">{{ inisial(b.nama) }}</div>
                                    <div>
                                        <div class="ak-pelamar-nama">{{ b.nama }}</div>
                                        <span class="wcd-tbl__sub">{{ b.posisi || b.program }}</span>
                                    </div>
                                </div>
                            </td>
                            <td><span class="ak-tahap-badge">{{ b.tahap }}</span></td>
                            <td class="wcd-num">
                                <span class="wcd-lb" :style="nadaMenunggu(b.umurHari)">
                                    <i class="bi" :class="nadaUmur(b.umurHari, ambang.macetHari).ikon"></i>
                                    {{ b.umurHari }} hari
                                </span>
                            </td>
                            <td class="text-right">
                                <a :href="b.tautan" class="ak-aksi" title="Buka di Worklist, program sudah tersaring">
                                    Tindak <i class="bi bi-arrow-right-short"></i>
                                </a>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <KeadaanPanel v-else keadaan="kosong" rapat
                :teks="`Tidak ada ${keranjangAktif.label.toLowerCase()}`" />

            <p v-if="terpotong" class="ak-potong">
                Menampilkan {{ barisTampil }} teratas (terlama dulu) dari {{ angka(keranjangAktif.total) }}.
                Sisanya dapat diakses di Worklist.
            </p>
        </template>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { angka, tglJam, nadaUmur, inisial, STATUS } from '../dashboardHelpers';
import KeadaanPanel from '../../monitoring/KeadaanPanel.vue';

const props = defineProps({
    aksi: { type: Object, required: true },
    ambang: { type: Object, default: () => ({ macetHari: 7, sorotHari: 2 }) },
});

const DEF = [
    {
        id: 'tindakanAdmin', label: 'Menunggu tindakanmu', ikon: 'bi-bell-fill', warna: '#4f46e5',
        jelas: 'Tahap yang tidak akan bergerak sampai admin mengerjakannya: jadwal ujian yang belum dibuat, wawancara yang belum diatur, penawaran yang belum dikirim. Sengaja TANPA ambang umur — muncul sejak hari pertama.',
    },
    {
        id: 'keputusan', label: 'Keputusan menggantung', ikon: 'bi-hourglass-split', warna: '#d97706',
        jelas: 'Mesin sudah mengumpulkan semua hasil dan menunggu palu diketuk. Ini antrean yang murni ada di tangan admin.',
    },
    {
        id: 'macet', label: 'Tahap macet', ikon: 'bi-cone-striped', warna: '#ef4444',
        jelas: 'Tahap berjalan melewati ambang tanpa siap diputus — biasanya kandidat belum mengisi atau hasilnya belum dicatat.',
    },
    {
        id: 'menungguTes', label: 'Menunggu hasil tes', ikon: 'bi-clipboard-check', warna: '#0284c7',
        jelas: 'Sudah dijadwalkan, tinggal menunggu penyedia tes pihak ketiga menuntaskan penilaian.',
    },
    {
        id: 'tutupSegera', label: 'Segera tutup', ikon: 'bi-calendar-x-fill', warna: '#8b5cf6',
        jelas: 'Pendaftaran berakhir dalam 7 hari. Kolom terakhir menandai pembukaan yang kandidatnya tidak cukup untuk mengisi kursi tersisa.',
    },
    {
        id: 'gagalLamar', label: 'Gagal masuk', ikon: 'bi-exclamation-octagon-fill', warna: '#ef4444',
        jelas: 'Lamaran yang tidak berhasil dibentuk sistem. Pelamarnya tidak ada di worklist mana pun.',
    },
];

const keranjang = computed(() => DEF.map((d) => ({ ...d, total: props.aksi[d.id]?.total || 0 })));
const totalSemua = computed(() => keranjang.value.reduce((n, k) => n + k.total, 0));

const pilih = ref(DEF.find((d) => (props.aksi[d.id]?.total || 0) > 0)?.id || 'keputusan');

const keranjangAktif = computed(() => keranjang.value.find((k) => k.id === pilih.value) || keranjang.value[0]);
const barisAktif = computed(() => props.aksi[pilih.value]?.baris || []);

const adaTindakan = computed(() => (props.aksi.tindakanAdmin?.total || 0) > 0);
const saring = ref('');

const barisTindakan = computed(() => {
    const b = props.aksi.tindakanAdmin?.baris || [];
    return saring.value ? b.filter((x) => x.kode === saring.value) : b;
});
const labelSaring = computed(
    () => props.aksi.tindakanAdmin?.ringkas?.find((r) => r.kode === saring.value)?.aksi || saring.value,
);

function bukaKerja(kode) {
    saring.value = pilih.value === 'tindakanAdmin' && saring.value === kode ? '' : kode;
    pilih.value = 'tindakanAdmin';
}

function ikonKode(kode) {
    return props.aksi.tindakanAdmin?.ringkas?.find((r) => r.kode === kode)?.ikon || 'bi-three-dots';
}

const barisTampil = computed(
    () => (pilih.value === 'tindakanAdmin' ? barisTindakan.value : barisAktif.value).length,
);
const terpotong = computed(() => {
    if (pilih.value === 'tindakanAdmin' && saring.value) return false;
    return barisTampil.value > 0 && barisTampil.value < (keranjangAktif.value?.total || 0);
});

function nadaMenunggu(hari) {
    const n = nadaUmur(hari, props.ambang.macetHari);
    return { background: `color-mix(in srgb, ${n.warna} 12%, #fff)`, color: n.warna };
}

function nadaSisa(hari) {
    const n = hari <= 1 ? STATUS.critical : (hari <= 3 ? STATUS.serious : STATUS.warning);
    return { background: `color-mix(in srgb, ${n.warna} 12%, #fff)`, color: n.warna };
}
</script>

<style scoped>
.ak-alarm {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 16px 20px;
    margin-bottom: 16px;
    border-radius: 16px;
    background: linear-gradient(135deg, #fef2f2, #fff1f2);
    border: 1px solid #fecaca;
    box-shadow: 0 4px 12px rgba(239, 68, 68, 0.08);
}
.ak-alarm__ic {
    display: grid;
    place-items: center;
    width: 40px;
    height: 40px;
    border-radius: 12px;
    background: #ef4444;
    color: #ffffff;
    font-size: 20px;
    flex: none;
}
.ak-alarm__content { flex: 1; font-size: 0.82rem; color: #991b1b; }
.ak-alarm__content strong { display: block; font-size: 0.9rem; font-weight: 800; color: #7f1d1d; margin-bottom: 2px; }
.ak-alarm__content p { margin: 0; color: #b91c1c; }
.ak-alarm__btn {
    border: 0;
    padding: 8px 16px;
    border-radius: 10px;
    background: #dc2626;
    color: #fff;
    font: inherit;
    font-size: 0.78rem;
    font-weight: 800;
    cursor: pointer;
    transition: background 0.2s ease;
    flex: none;
}
.ak-alarm__btn:hover { background: #b91c1c; }

/* ══════════ PENANDA KESADARAN ══════════ */
.ak-sadar {
    padding: 18px 20px;
    margin-bottom: 18px;
    border-radius: 16px;
    background: linear-gradient(135deg, #eef2ff, #f5f3ff);
    border: 1px solid #c7d2fe;
    box-shadow: 0 4px 14px rgba(79, 70, 229, 0.06);
}
.ak-sadar__kepala { display: flex; align-items: center; gap: 14px; }
.ak-sadar__ic {
    display: grid;
    place-items: center;
    width: 38px;
    height: 38px;
    flex: none;
    border-radius: 12px;
    background: #4f46e5;
    color: #fff;
    font-size: 18px;
    box-shadow: 0 4px 10px rgba(79, 70, 229, 0.3);
}
.ak-sadar__teks { font-size: 0.82rem; color: #3730a3; }
.ak-sadar__teks strong { font-weight: 800; color: #312e81; display: block; font-size: 0.88rem; margin-bottom: 2px; }

.ak-kerja { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 14px; }
.ak-kerja__item {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 7px 14px;
    border: 1px solid #c7d2fe;
    border-radius: 999px;
    background: #fff;
    font: inherit;
    font-size: 0.78rem;
    font-weight: 800;
    color: #3730a3;
    cursor: pointer;
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}
.ak-kerja__item .bi { font-size: 14px; color: #6366f1; }
.ak-kerja__item:hover { border-color: #6366f1; background: #ffffff; transform: translateY(-1px); box-shadow: 0 4px 10px rgba(99, 102, 241, 0.15); }
.ak-kerja__item.is-on { background: #4f46e5; border-color: #4f46e5; color: #fff; box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3); }
.ak-kerja__item.is-on .bi { color: #fff; }
.ak-kerja__item em {
    font-style: normal;
    min-width: 20px;
    padding: 2px 7px;
    border-radius: 999px;
    background: #eef2ff;
    color: #4338ca;
    font-size: 0.72rem;
    text-align: center;
}
.ak-kerja__item.is-on em { background: rgba(255, 255, 255, 0.25); color: #fff; }
.ak-kerja__tua {
    display: inline-flex;
    align-items: center;
    gap: 3px;
    font-size: 0.7rem;
    font-weight: 800;
    color: #d97706;
}
.ak-kerja__item.is-on .ak-kerja__tua { color: #fef08a; }

.ak-kerja__lb {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 11px;
    border-radius: 999px;
    background: #eef2ff;
    color: #4338ca;
    font-size: 0.75rem;
    font-weight: 800;
}

.ak-saring {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin: 0 0 12px;
    padding: 8px 14px;
    background: #f8fafc;
    border-radius: 10px;
    font-size: 0.78rem;
    color: #64748b;
}
.ak-saring b { color: #1e293b; }
.ak-saring button {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    border: 0;
    background: transparent;
    font: inherit;
    font-size: 0.75rem;
    font-weight: 800;
    color: #4f46e5;
    cursor: pointer;
}

.ak-bersih {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 24px;
    border-radius: 16px;
    background: linear-gradient(135deg, #f0fdf4, #ecfdf5);
    border: 1px solid #bbf7d0;
}
.ak-bersih__ic {
    display: grid;
    place-items: center;
    width: 46px;
    height: 46px;
    border-radius: 14px;
    background: #10b981;
    color: #fff;
    font-size: 24px;
    flex: none;
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
}
.ak-bersih__txt strong { display: block; font-size: 0.95rem; font-weight: 900; color: #065f46; margin-bottom: 2px; }
.ak-bersih__txt span { color: #047857; font-size: 0.8rem; }

/* ══════════ TABS & TABLES ══════════ */
.ak-tabs {
    display: flex;
    gap: 8px;
    overflow-x: auto;
    padding-bottom: 6px;
    margin-bottom: 14px;
}
.ak-tab {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    flex: none;
    padding: 9px 16px;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    background: #fff;
    font: inherit;
    font-size: 0.8rem;
    font-weight: 800;
    color: #64748b;
    cursor: pointer;
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}
.ak-tab .bi { color: var(--tone); font-size: 14px; }
.ak-tab:hover { border-color: color-mix(in srgb, var(--tone) 45%, #e2e8f0); transform: translateY(-1px); }
.ak-tab.is-on {
    color: #fff;
    background: var(--tone);
    border-color: var(--tone);
    box-shadow: 0 4px 14px color-mix(in srgb, var(--tone) 35%, transparent);
}
.ak-tab.is-on .bi { color: #fff; }
.ak-tab__count {
    font-style: normal;
    min-width: 20px;
    padding: 1px 7px;
    border-radius: 999px;
    background: #f1f5f9;
    color: #334155;
    font-size: 0.72rem;
    text-align: center;
}
.ak-tab.is-on .ak-tab__count { background: rgba(255, 255, 255, 0.25); color: #fff; }

.ak-jelas {
    display: flex;
    align-items: center;
    gap: 8px;
    margin: 0 0 14px;
    padding: 10px 14px;
    border-radius: 12px;
    background: #f8fafc;
    border: 1px solid #f1f5f9;
    font-size: 0.78rem;
    line-height: 1.5;
    color: #475569;
}
.ak-jelas .bi { color: #6366f1; font-size: 15px; flex: none; }

.ak-pelamar-cell {
    display: flex;
    align-items: center;
    gap: 12px;
}

.ak-avatar {
    display: grid;
    place-items: center;
    width: 34px;
    height: 34px;
    border-radius: 10px;
    background: linear-gradient(135deg, #6366f1, #4f46e5);
    color: #ffffff;
    font-size: 0.75rem;
    font-weight: 900;
    flex: none;
}

.ak-pelamar-nama {
    font-weight: 800;
    color: #0f172a;
}

.ak-tahap-badge {
    display: inline-block;
    padding: 3px 9px;
    border-radius: 6px;
    background: #f1f5f9;
    color: #334155;
    font-size: 0.75rem;
    font-weight: 700;
}

.ak-aksi {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 6px 14px;
    border-radius: 8px;
    background: #eef2ff;
    color: #4338ca;
    font-size: 0.75rem;
    font-weight: 800;
    text-decoration: none;
    transition: all 0.2s ease;
}
.ak-aksi:hover { background: #4338ca; color: #fff; transform: translateX(2px); }
.ak-aksi.is-kerja { background: #4f46e5; color: #fff; }
.ak-aksi.is-kerja:hover { background: #3730a3; }

.ak-galat { white-space: normal; max-width: 340px; font-size: 0.75rem; color: #dc2626; font-weight: 600; }
.ak-potong { margin: 12px 0 0; font-size: 0.75rem; color: #94a3b8; font-weight: 500; }
.text-right { text-align: right; }
</style>
