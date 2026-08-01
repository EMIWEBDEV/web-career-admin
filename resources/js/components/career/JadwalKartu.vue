<!-- WEB CAREER — KARTU JADWAL AKTIVITAS (POV KANDIDAT).
     Menjawab tiga hal yang dicari kandidat begitu diundang: KAPAN, DI MANA
     (atau lewat tautan apa), dan APA yang perlu disiapkan.

     Dipakai di dua tempat pada halaman detail lamaran — kartu tahap ber-ujian
     online dan kartu tahap yang ditangani tim. Dulu markupnya disalin di
     keduanya, dan setiap perbaikan hanya sampai ke salah satunya. -->
<template>
    <div class="jdw" :class="j.daring ? 'is-daring' : 'is-luring'">
        <div class="jdw__head">
            <span class="jdw__ico"><i class="bi" :class="j.daring ? 'bi-camera-video-fill' : 'bi-geo-alt-fill'"></i></span>
            <div style="min-width: 0; flex: 1">
                <div class="jdw__eyebrow">{{ j.daring ? 'DIJADWALKAN · DARING' : 'DIJADWALKAN · TATAP MUKA' }}</div>
                <div class="jdw__judul">{{ j.label }}</div>
            </div>
        </div>

        <div class="jdw__grid">
            <div class="jdw__i">
                <span>WAKTU</span>
                <b>{{ waktu }}</b>
            </div>
            <div class="jdw__i">
                <span>{{ j.daring ? 'TAUTAN PERTEMUAN' : 'TEMPAT' }}</span>
                <a v-if="j.daring && j.link" :href="j.link" target="_blank" rel="noopener" class="jdw__link">{{ j.link }}</a>
                <b v-else>{{ j.daring ? '—' : (tempat.nama || j.lokasi || '—') }}</b>
            </div>
            <!-- PATOKAN yang diketik rekruter ("Gedung B lantai 3, temui
                 resepsionis"). Dulu tertelan begitu lokasinya dipilih dari
                 master: barisnya hanya menampilkan nama gedung, sehingga
                 petunjuk paling menentukan justru tidak pernah terbaca. -->
            <div v-if="!j.daring && j.lokasi && tempat.nama" class="jdw__i">
                <span>PATOKAN</span>
                <b>{{ j.lokasi }}</b>
            </div>
        </div>

        <!-- PETA. Alamat berupa teks menuntut kandidat menyalinnya sendiri ke
             aplikasi peta; ini langsung bisa dibuka — termasuk saat lokasinya
             diketik bebas, bukan dipilih dari Master Lokasi. -->
        <div v-if="!j.daring && peta.embed" class="jdw__peta">
            <iframe
                :src="peta.embed"
                :title="'Peta ' + (tempat.nama || j.lokasi || 'lokasi kegiatan')"
                loading="lazy"
                referrerpolicy="no-referrer-when-downgrade"
            ></iframe>
            <div class="jdw__alamat">
                <i class="bi bi-geo-alt-fill"></i>
                <div style="min-width: 0; flex: 1">
                    <b>{{ tempat.nama || j.lokasi }}</b>
                    <small v-if="tempat.alamat">{{ tempat.alamat }}</small>
                    <small v-if="wilayah">{{ wilayah }}</small>
                    <small v-if="tempat.kontakTelp"><i class="bi bi-telephone-fill"></i> {{ tempat.kontakTelp }}</small>
                </div>
                <a v-if="peta.url" :href="peta.url" target="_blank" rel="noopener" class="jdw__peta-btn">
                    <i class="bi bi-box-arrow-up-right"></i> Buka peta
                </a>
            </div>
        </div>

        <!-- CATATAN diberi judulnya sendiri. Tanpa itu kalimat seperti "bawa KTP,
             bawa KK" muncul begitu saja di dasar kartu — kandidat tidak tahu itu
             pesan dari tim, syarat masuk, atau keterangan sistem. -->
        <div v-if="j.catatan" class="jdw__cat">
            <span class="jdw__cat-lbl"><i class="bi bi-info-circle-fill"></i> CATATAN DARI TIM REKRUTMEN</span>
            <p>{{ j.catatan }}</p>
        </div>

        <a v-if="j.daring && j.link" :href="j.link" target="_blank" rel="noopener" class="jdw__btn">
            <i class="bi bi-camera-video-fill"></i> Gabung Pertemuan
        </a>
    </div>
</template>

<script>
export default {
    name: 'JadwalKartu',
    props: {
        /** Satu jadwal aktivitas: { label, daring, mulai, selesai, link, lokasi, tempat, catatan }. */
        jadwal: { type: Object, required: true },
    },
    computed: {
        j() { return this.jadwal || {}; },
        /** Lokasi dari Master Lokasi — kosong bila rekruter mengetik bebas. */
        tempat() { return this.j.tempat || {}; },
        /**
         * Kota/provinsi/kode pos — HANYA yang belum tertulis di alamat.
         *
         * Alamat lengkap biasanya sudah memuat kota dan provinsinya, sehingga
         * menampilkan keduanya apa adanya menghasilkan dua baris yang isinya
         * sama ("Banyuasin, Sumatera Selatan" lalu "Banyuasin · Sumatera
         * Selatan") — terbaca seperti data yang salah tersimpan dua kali.
         */
        wilayah() {
            const alamat = (this.tempat.alamat || '').toLowerCase();

            return [this.tempat.kota, this.tempat.provinsi, this.tempat.kodePos]
                .filter((v) => v && !alamat.includes(String(v).toLowerCase()))
                .join(' · ');
        },
        /**
         * Sumber peta: titik dari Master Lokasi lebih dulu (sudah dibentuk
         * server berikut koordinatnya), lalu teks lokasi apa adanya sebagai
         * cadangan. Jadwal lama yang lokasinya diketik bebas tetap dapat peta,
         * bukan sekadar sebaris alamat mati.
         */
        peta() {
            if (this.tempat.mapsEmbed) {
                return { embed: this.tempat.mapsEmbed, url: this.tempat.mapsUrl };
            }

            const teks = (this.j.lokasi || '').trim();
            if (!teks) return { embed: null, url: null };

            const q = encodeURIComponent(teks);

            return {
                embed: `https://www.google.com/maps?q=${q}&output=embed`,
                url: `https://www.google.com/maps/search/?api=1&query=${q}`,
            };
        },
        waktu() {
            if (!this.j.mulai) return '—';
            const d = new Date(String(this.j.mulai).replace(' ', 'T'));
            if (Number.isNaN(d.getTime())) return '—';

            const hari = d.toLocaleDateString('id-ID', { weekday: 'long', day: '2-digit', month: 'short', year: 'numeric' });
            const jam = (v) => new Date(String(v).replace(' ', 'T'))
                .toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }).replace(':', '.');

            return `${hari} · ${jam(this.j.mulai)}${this.j.selesai ? ' – ' + jam(this.j.selesai) : ''} WIB`;
        },
    },
};
</script>

<style scoped>
.jdw { padding: 15px 17px; border-radius: 16px; border: 1px solid; }
.jdw.is-daring { background: linear-gradient(135deg, rgba(99, 102, 241, .07), rgba(139, 92, 246, .04)); border-color: rgba(99, 102, 241, .28); }
.jdw.is-luring { background: linear-gradient(135deg, rgba(245, 158, 11, .09), rgba(245, 158, 11, .03)); border-color: rgba(245, 158, 11, .3); }

.jdw__head { display: flex; align-items: center; gap: 12px; }
.jdw__ico { flex: none; width: 40px; height: 40px; border-radius: 12px; display: grid; place-items: center; color: #fff; font-size: 16px; }
.jdw.is-daring .jdw__ico { background: linear-gradient(140deg, #818cf8, #6366f1); }
.jdw.is-luring .jdw__ico { background: linear-gradient(140deg, #fbbf24, #f59e0b); }
.jdw__eyebrow { font-size: 10.5px; font-weight: 800; letter-spacing: .1em; color: #a2a9ba; }
.jdw__judul { font-size: 15px; font-weight: 800; color: #1e293b; margin-top: 2px; }

.jdw__grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px 20px; margin-top: 14px; }
.jdw__i { min-width: 0; }
.jdw__i span { display: block; font-size: 10.5px; font-weight: 800; letter-spacing: .08em; color: #a2a9ba; }
.jdw__i b { display: block; font-size: 13.5px; font-weight: 800; color: #1e293b; margin-top: 3px; line-height: 1.45; }
.jdw__link { display: block; margin-top: 3px; font-size: 13px; font-weight: 700; color: #4f46e5; word-break: break-all; }

.jdw__peta { margin-top: 14px; border: 1px solid rgba(15, 23, 42, .1); border-radius: 13px; overflow: hidden; background: #fff; }
.jdw__peta iframe { display: block; width: 100%; height: 230px; border: 0; }
.jdw__alamat { display: flex; align-items: flex-start; gap: 9px; padding: 12px 13px; border-top: 1px solid #eef0f7; }
.jdw__alamat > .bi { flex: none; color: #dc2626; margin-top: 2px; }
.jdw__alamat b { display: block; font-size: 13px; font-weight: 800; color: #1e293b; }
.jdw__alamat small { display: block; font-size: 11.5px; color: #64748b; margin-top: 2px; line-height: 1.5; }
.jdw__alamat small .bi { color: #94a3b8; }
.jdw__peta-btn { flex: none; align-self: center; display: inline-flex; align-items: center; gap: 6px; padding: 7px 12px; border-radius: 9px; font-size: 11.5px; font-weight: 800; color: #4f46e5; background: rgba(99, 102, 241, .1); text-decoration: none; }
.jdw__peta-btn:hover { background: rgba(99, 102, 241, .16); }

.jdw__cat { margin: 13px 0 0; padding: 10px 13px; border: 1px solid rgba(99, 102, 241, .18); border-left: 3px solid rgba(99, 102, 241, .55); border-radius: 0 12px 12px 0; background: rgba(255, 255, 255, .78); }
.jdw__cat-lbl { display: inline-flex; align-items: center; gap: 6px; font-size: 9.5px; font-weight: 800; letter-spacing: .09em; color: #6366f1; }
.jdw__cat p { margin: 5px 0 0; font-size: 12.5px; line-height: 1.6; color: #475569; white-space: pre-line; }

.jdw__btn { display: inline-flex; align-items: center; gap: 8px; margin-top: 14px; padding: 11px 18px; border-radius: 12px; background: linear-gradient(135deg, #818cf8, #6366f1); color: #fff; font-size: 13.5px; font-weight: 800; text-decoration: none; box-shadow: 0 10px 24px -12px rgba(79, 70, 229, .9); }

/* Ponsel: peta lebih pendek supaya isian di bawahnya tetap terlihat. */
@media (max-width: 560px) {
    .jdw { padding: 13px 14px; }
    .jdw__peta iframe { height: 180px; }
    .jdw__alamat { flex-wrap: wrap; }
    .jdw__peta-btn { align-self: stretch; justify-content: center; width: 100%; }
}
</style>
