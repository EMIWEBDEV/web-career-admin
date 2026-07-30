<!--
  ZONA C — AGENDA 14 HARI.

  Tiga sumber yang sifatnya berbeda digabung kronologis, tapi asalnya tetap
  dibedakan lewat ikon + label jenis — bukan warna sendirian:
   · TES    — sesi ujian yang benar-benar terjadwal (Penjadwalan_Tahap)
   · AGENDA — rencana kegiatan program (Master_Jadwal_Agenda)
   · TUTUP  — pendaftaran berakhir (Pembukaan.Tanggal_Tutup)

  Digabung karena admin memikirkannya sebagai satu minggu ke depan, bukan
  sebagai tiga tabel di tiga halaman berbeda. Dikelompokkan per hari supaya
  "besok ada apa" terjawab tanpa membaca tanggal di setiap baris.
-->
<template>
    <div v-if="!agenda.length">
        <KeadaanPanel keadaan="kosong" ikon="bi-calendar-week"
            teks="Tidak ada agenda dalam 14 hari ke depan"
            ket="Sesi tes, agenda program, dan penutupan pendaftaran akan muncul di sini." />
    </div>

    <ol v-else class="ag-list">
        <li v-for="grup in perHari" :key="grup.kunci">
            <div class="ag-hari">
                <b>{{ grup.tanggal }}</b>
                <span :class="{ 'is-kini': grup.selisih === 0 }">{{ grup.relatif }}</span>
            </div>
            <div class="ag-isi">
                <div v-for="(a, i) in grup.item" :key="i" class="ag-item" :style="{ '--tone': JENIS[a.jenis].warna }">
                    <span class="ag-ic"><i class="bi" :class="JENIS[a.jenis].ikon"></i></span>
                    <div class="ag-teks">
                        <strong>{{ a.judul }}</strong>
                        <small>
                            <span class="ag-jenis">{{ JENIS[a.jenis].label }}</span>
                            <template v-if="a.program"> · {{ a.program }}</template>
                            <template v-if="a.ket"> · {{ a.ket }}</template>
                        </small>
                    </div>
                    <span class="ag-jam">{{ jamAtauRentang(a) }}</span>
                </div>
            </div>
        </li>
    </ol>
</template>

<script setup>
import { computed } from 'vue';
import { tanggal } from '../dashboardHelpers';
import KeadaanPanel from '../../monitoring/KeadaanPanel.vue';

const props = defineProps({ agenda: { type: Array, default: () => [] } });

const JENIS = {
    TES: { label: 'Sesi tes', ikon: 'bi-pencil-square', warna: '#0284c7' },
    AGENDA: { label: 'Agenda program', ikon: 'bi-calendar-event-fill', warna: '#6366f1' },
    TUTUP: { label: 'Pendaftaran ditutup', ikon: 'bi-calendar-x-fill', warna: '#d03b3b' },
};

function keDate(v) {
    if (!v) return null;
    const d = new Date(String(v).replace(' ', 'T'));
    return Number.isNaN(d.getTime()) ? null : d;
}

const perHari = computed(() => {
    const hariIni = new Date();
    hariIni.setHours(0, 0, 0, 0);
    const peta = new Map();

    props.agenda.forEach((a) => {
        const d = keDate(a.mulai);
        if (!d) return;
        const kunci = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
        if (!peta.has(kunci)) {
            const nol = new Date(d);
            nol.setHours(0, 0, 0, 0);
            const selisih = Math.round((nol - hariIni) / 86400000);
            peta.set(kunci, {
                kunci,
                tanggal: tanggal(kunci),
                selisih,
                relatif: selisih === 0 ? 'Hari ini' : (selisih === 1 ? 'Besok' : `${selisih} hari lagi`),
                item: [],
            });
        }
        peta.get(kunci).item.push(a);
    });

    return [...peta.values()].sort((a, b) => a.selisih - b.selisih);
});

/**
 * Jam ditampilkan hanya kalau memang ada jamnya. Agenda program tersimpan
 * sebagai DATE (tanpa jam), jadi menuliskannya "00:00" akan mengarang
 * ketepatan yang tidak ada di datanya.
 */
function jamAtauRentang(a) {
    const m = keDate(a.mulai);
    if (!m) return '—';
    const punyaJam = /\d{2}:\d{2}/.test(String(a.mulai)) && !String(a.mulai).includes('00:00:00');
    if (!punyaJam) return 'Sepanjang hari';

    const jam = (d) => d.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
    const s = keDate(a.akhir);
    return s && s > m ? `${jam(m)}–${jam(s)}` : jam(m);
}
</script>

<style scoped>
.ag-list { list-style: none; margin: 0; padding: 0; display: grid; gap: 16px; }

.ag-hari { display: flex; align-items: baseline; gap: 9px; margin-bottom: 8px; }
.ag-hari b { font-size: 0.79rem; font-weight: 900; color: #0f172a; }
.ag-hari span {
    font-size: 0.7rem;
    font-weight: 800;
    color: #94a3b8;
    padding: 1px 8px;
    border-radius: 999px;
    background: #f1f5f9;
}
.ag-hari span.is-kini { background: #eef2ff; color: #4338ca; }

.ag-isi { display: grid; gap: 7px; }
.ag-item {
    display: flex;
    align-items: center;
    gap: 11px;
    padding: 10px 12px;
    border-radius: 12px;
    border: 1px solid #eef2f7;
    border-left: 3px solid var(--tone);
    background: #fff;
}
.ag-ic {
    display: grid;
    place-items: center;
    width: 28px;
    height: 28px;
    flex: none;
    border-radius: 9px;
    font-size: 13px;
    color: var(--tone);
    background: color-mix(in srgb, var(--tone) 12%, #fff);
}
.ag-teks { flex: 1; min-width: 0; }
.ag-teks strong { display: block; font-size: 0.79rem; font-weight: 800; color: #0f172a; }
.ag-teks small { display: block; margin-top: 1px; font-size: 0.71rem; color: #64748b; }
.ag-jenis { font-weight: 800; color: var(--tone); }
.ag-jam {
    flex: none;
    font-size: 0.73rem;
    font-weight: 800;
    color: #475569;
    font-variant-numeric: tabular-nums;
}

@media (max-width: 560px) {
    .ag-item { flex-wrap: wrap; }
    .ag-jam { margin-left: 39px; }
}
</style>
