<!--
  ZONA B — ANTREAN "BUTUH AKSI KAMU".

  Lima keranjang, diurut dari yang paling jelas tanggung jawab admin (keputusan
  menggantung) ke yang paling di luar kendalinya (menunggu penyedia tes).

  Dua hal yang membuat panel ini bukan sekadar daftar:
   1. tiap baris menautkan ke Worklist yang SUDAH tersaring ke program orangnya,
      jadi bertindak butuh satu klik, bukan menelusuri daftar program;
   2. "Lamaran gagal masuk" ditarik ke atas sebagai spanduk kalau isinya > 0 —
      itu pelamar yang benar-benar hilang, dan tidak ada halaman lain yang
      menampilkannya.
-->
<template>
    <div>
        <!-- Spanduk: lamaran yang gagal terbentuk. Diletakkan di luar sistem tab
             karena artinya beda jenis — bukan "perlu diputus", tapi "ada orang
             yang datanya hilang". -->
        <div v-if="aksi.gagalLamar.total" class="ak-alarm">
            <i class="bi bi-exclamation-octagon-fill"></i>
            <div>
                <strong>{{ angka(aksi.gagalLamar.total) }} lamaran gagal masuk ke sistem.</strong>
                Pelamar sudah menekan "Lamar" tapi datanya tidak terbentuk — mereka tidak
                muncul di worklist mana pun dan tidak tahu lamarannya gagal.
            </div>
            <button type="button" @click="pilih = 'gagalLamar'">Lihat daftar</button>
        </div>

        <div v-if="totalSemua === 0" class="ak-bersih">
            <i class="bi bi-check2-circle"></i>
            <div>
                <strong>Tidak ada yang menunggu tindakan.</strong>
                <span>Tidak ada keputusan menggantung, tahap macet, atau pembukaan yang segera tutup.</span>
            </div>
        </div>

        <template v-else>
            <!-- Tab keranjang: jumlah SEBENARNYA yang ditulis, bukan jumlah baris
                 yang dikirim — pemotongan di server tidak boleh terlihat seperti
                 "cuma ada segini". -->
            <div class="ak-tabs" role="tablist">
                <button v-for="k in keranjang" :key="k.id" type="button" role="tab"
                    :aria-selected="pilih === k.id" class="ak-tab" :class="{ 'is-on': pilih === k.id }"
                    :style="{ '--tone': k.warna }" @click="pilih = k.id">
                    <i class="bi" :class="k.ikon"></i>
                    <span>{{ k.label }}</span>
                    <em>{{ angka(k.total) }}</em>
                </button>
            </div>

            <p class="ak-jelas">
                <i class="bi bi-info-circle"></i> {{ keranjangAktif.jelas }}
            </p>

            <!-- ── Pembukaan segera tutup: bentuknya beda (tanggal + kursi) ── -->
            <div v-if="pilih === 'tutupSegera'" class="wcd-tw">
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
                                {{ b.program }}
                                <span class="wcd-tbl__sub">{{ b.kode }}</span>
                            </td>
                            <td>
                                <span class="wcd-lb" :style="nadaSisa(b.sisaHari)">
                                    <i class="bi bi-hourglass-bottom"></i>
                                    {{ b.sisaHari === 0 ? 'hari ini' : b.sisaHari + ' hari lagi' }}
                                </span>
                                <span class="wcd-tbl__sub">{{ tglJam(b.tutup) }}</span>
                            </td>
                            <td class="wcd-num">{{ angka(b.sisaKursi) }} <small>/ {{ angka(b.kuota) }}</small></td>
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
                                {{ b.nama }}
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

            <!-- ── Tiga keranjang pelamar (bentuk sama) ── -->
            <div v-else-if="barisAktif.length" class="wcd-tw">
                <table class="wcd-tbl">
                    <thead>
                        <tr><th>Pelamar</th><th>Tahap</th><th class="wcd-num">Menunggu</th><th></th></tr>
                    </thead>
                    <tbody>
                        <tr v-for="b in barisAktif" :key="b.id">
                            <td class="wcd-tbl__utama">
                                {{ b.nama }}
                                <span class="wcd-tbl__sub">{{ b.posisi || b.program }}</span>
                            </td>
                            <td>{{ b.tahap }}</td>
                            <td class="wcd-num">
                                <span class="wcd-lb" :style="nadaMenunggu(b.umurHari)">
                                    <i class="bi" :class="nadaUmur(b.umurHari, ambang.macetHari).ikon"></i>
                                    {{ b.umurHari }} hari
                                </span>
                            </td>
                            <td>
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

            <!-- Pemotongan diakui, tidak disembunyikan. -->
            <p v-if="terpotong" class="ak-potong">
                Menampilkan {{ barisTampil }} teratas (terlama dulu) dari {{ angka(keranjangAktif.total) }}.
                Sisanya ada di Worklist.
            </p>
        </template>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { angka, tglJam, nadaUmur, STATUS } from '../dashboardHelpers';
import KeadaanPanel from '../../monitoring/KeadaanPanel.vue';

const props = defineProps({
    aksi: { type: Object, required: true },
    ambang: { type: Object, default: () => ({ macetHari: 7, sorotHari: 2 }) },
});

const DEF = [
    {
        id: 'keputusan', label: 'Keputusan menggantung', ikon: 'bi-hourglass-split', warna: '#d97706',
        jelas: 'Mesin sudah mengumpulkan semua hasil dan menunggu palu diketuk. Ini antrean yang murni ada di tangan admin.',
    },
    {
        id: 'macet', label: 'Tahap macet', ikon: 'bi-cone-striped', warna: '#d03b3b',
        jelas: 'Tahap berjalan melewati ambang tanpa siap diputus — biasanya kandidat belum mengisi atau hasilnya belum dicatat.',
    },
    {
        id: 'menungguTes', label: 'Menunggu hasil tes', ikon: 'bi-clipboard-check', warna: '#0284c7',
        jelas: 'Menunggu penyedia tes pihak ketiga menuntaskan penilaian. Di luar kendali admin, tapi perlu dikejar kalau terlalu lama.',
    },
    {
        id: 'tutupSegera', label: 'Segera tutup', ikon: 'bi-calendar-x-fill', warna: '#7c3aed',
        jelas: 'Pendaftaran berakhir dalam 7 hari. Kolom terakhir menandai pembukaan yang kandidatnya tidak cukup untuk mengisi kursi tersisa — hanya sekarang masih bisa ditindak.',
    },
    {
        id: 'gagalLamar', label: 'Gagal masuk', ikon: 'bi-exclamation-octagon-fill', warna: '#d03b3b',
        jelas: 'Lamaran yang tidak berhasil dibentuk sistem. Pelamarnya tidak ada di worklist mana pun — perlu dihubungi atau didaftarkan ulang manual.',
    },
];

const keranjang = computed(() => DEF.map((d) => ({ ...d, total: props.aksi[d.id]?.total || 0 })));
const totalSemua = computed(() => keranjang.value.reduce((n, k) => n + k.total, 0));

/* Bawaan: keranjang berisi pertama menurut urutan prioritas, bukan selalu yang
   pertama — supaya panel tidak terbuka pada tab kosong saat yang lain penuh. */
const pilih = ref(DEF.find((d) => (props.aksi[d.id]?.total || 0) > 0)?.id || 'keputusan');

const keranjangAktif = computed(() => keranjang.value.find((k) => k.id === pilih.value) || keranjang.value[0]);
const barisAktif = computed(() => props.aksi[pilih.value]?.baris || []);
const barisTampil = computed(() => (props.aksi[pilih.value]?.baris || []).length);
const terpotong = computed(() => barisTampil.value > 0 && barisTampil.value < (keranjangAktif.value?.total || 0));

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
    gap: 12px;
    padding: 12px 14px;
    margin-bottom: 14px;
    border-radius: 14px;
    background: #fef2f2;
    border: 1px solid #fecaca;
    font-size: 0.78rem;
    color: #7f1d1d;
    line-height: 1.55;
}
.ak-alarm > .bi { font-size: 20px; color: #d03b3b; flex: none; }
.ak-alarm div { flex: 1; }
.ak-alarm strong { display: block; color: #991b1b; }
.ak-alarm button {
    flex: none;
    border: 0;
    padding: 7px 13px;
    border-radius: 10px;
    background: #d03b3b;
    color: #fff;
    font: inherit;
    font-size: 0.74rem;
    font-weight: 800;
    cursor: pointer;
}

.ak-bersih {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 20px 16px;
    border-radius: 14px;
    background: #f0fdf4;
    border: 1px solid #bbf7d0;
    font-size: 0.8rem;
    color: #166534;
}
.ak-bersih > .bi { font-size: 26px; color: #0ca30c; }
.ak-bersih strong { display: block; }
.ak-bersih span { color: #15803d; font-size: 0.76rem; }

.ak-tabs { display: flex; gap: 8px; overflow-x: auto; padding-bottom: 4px; margin-bottom: 12px; }
.ak-tab {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    flex: none;
    padding: 8px 13px;
    border: 1px solid #e8edf5;
    border-radius: 12px;
    background: #fff;
    font: inherit;
    font-size: 0.76rem;
    font-weight: 800;
    color: #475569;
    cursor: pointer;
}
.ak-tab .bi { color: var(--tone); font-size: 13px; }
.ak-tab:hover { border-color: color-mix(in srgb, var(--tone) 45%, #e8edf5); }
.ak-tab.is-on {
    color: #fff;
    background: var(--tone);
    border-color: var(--tone);
}
.ak-tab.is-on .bi { color: #fff; }
.ak-tab em {
    font-style: normal;
    min-width: 20px;
    padding: 1px 6px;
    border-radius: 999px;
    background: #eef2f7;
    color: #334155;
    font-size: 0.68rem;
    text-align: center;
}
.ak-tab.is-on em { background: rgba(255, 255, 255, 0.26); color: #fff; }

.ak-jelas {
    margin: 0 0 12px;
    padding: 9px 12px;
    border-radius: 10px;
    background: #f8fafc;
    font-size: 0.74rem;
    line-height: 1.6;
    color: #475569;
}
.ak-jelas .bi { color: #94a3b8; }

.ak-aksi {
    display: inline-flex;
    align-items: center;
    gap: 2px;
    padding: 5px 10px;
    border-radius: 9px;
    background: #eef2ff;
    color: #4338ca;
    font-size: 0.73rem;
    font-weight: 800;
    text-decoration: none;
}
.ak-aksi:hover { background: #4338ca; color: #fff; }

.ak-galat { white-space: normal; max-width: 340px; font-size: 0.73rem; color: #b91c1c; }

.ak-potong { margin: 10px 0 0; font-size: 0.73rem; color: #94a3b8; }
</style>
