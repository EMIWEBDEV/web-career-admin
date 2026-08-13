<!-- WEB CAREER — Footer 2 tingkat (bagian dari CareerLayout)
     dengan Social Media Icons Bar & Enhanced Mobile Footer UX -->
<template>
    <footer class="wc-footer">
        <!-- Tingkat 1 -->
        <div class="wc-footer__top">
            <div class="wc-footer__brand">
                <div class="wc-footer__logo">
                    <span class="wc-footer__mark"><img src="/logo/EVOGROUP.png" alt="EVO Group" /></span>
                    <span class="wc-footer__brand-txt">
                        <strong>EVO Group</strong>
                        <small>Career Portal</small>
                    </span>
                </div>
                <p>Ekosistem people, pet &amp; manufacturing terkemuka di Sumatera Selatan. Naik level bersama kami.</p>

                <!-- Baris Ikon Media Sosial & Kontak -->
                <div class="wc-footer__social">
                    <a href="https://linkedin.com" target="_blank" rel="noopener noreferrer" title="LinkedIn"
                        ><i class="bi bi-linkedin"></i
                    ></a>
                    <a href="https://instagram.com" target="_blank" rel="noopener noreferrer" title="Instagram"
                        ><i class="bi bi-instagram"></i
                    ></a>
                    <a href="mailto:recruitment@evogroup.co.id" title="Email Rekrutmen"
                        ><i class="bi bi-envelope-fill"></i
                    ></a>
                    <a href="https://wa.me/6281234567890" target="_blank" rel="noopener noreferrer" title="WhatsApp HR"
                        ><i class="bi bi-whatsapp"></i
                    ></a>
                </div>
            </div>

            <div class="wc-footer__cols">
                <div class="wc-footer__col">
                    <strong>Karier</strong>
                    <!-- #lowongan sudah tidak ada sejak redesign — arahkan ke Fungsi Perusahaan. -->
                    <button type="button" @click="goToSection('tim')">Lowongan</button>
                    <button v-if="hasMt" type="button" @click="goToSection('mt')">Management Trainee</button>
                    <a href="/karir/lowongan">Semua Lowongan</a>
                </div>
                <div class="wc-footer__col">
                    <strong>Akses</strong>
                    <a href="/login">Masuk</a>
                    <a href="/register">Daftar Akun</a>
                    <a href="/kandidat/portal">Portal Kandidat</a>
                </div>
                <div class="wc-footer__col">
                    <strong>Tentang</strong>
                    <button type="button" @click="goToSection('achievement')">Pencapaian</button>
                    <button type="button" @click="goToSection('lokasi')">Lokasi Kami</button>
                    <a href="/karir/faq">FAQ Kandidat</a>
                </div>
                <div v-if="mapSrc" class="wc-footer__col wc-footer__col--office">
                    <iframe
                        class="wc-footer__map"
                        :src="mapSrc"
                        title="Peta lokasi kantor pusat EVO Group"
                        loading="lazy"
                        referrerpolicy="strict-origin-when-cross-origin"
                        allow="fullscreen"
                    ></iframe>
                </div>
            </div>
        </div>
        <!-- Tingkat 2 -->
        <div class="wc-footer__bar">
            <div class="wc-footer__bar-inner">
                <span>&copy; {{ tahun }} EVO Group. Seluruh hak cipta.</span>
                <span class="wc-footer__credit">Dikelola oleh <b>Tim Technology EVO Group</b></span>
            </div>
        </div>
    </footer>
</template>

<script setup>
import { computed } from 'vue';
import { goToSection } from '../careerData';

// ponytail: koordinat HO di-hardcode. Pindahkan ke kolom Latitude/Longitude
// di N_HRIS_Master_Lokasi kalau cabang lain juga perlu dipetakan.
const HO = { lat: -2.9441525942113036, lng: 104.7700541264974 };

defineProps({
    hasMt: { type: Boolean, default: false },
});

// Google Maps Embed API (resmi). Key wajib & harus dibatasi HTTP referrer di
// Google Cloud Console — key ini ikut terkirim ke browser.
const mapSrc = computed(() => {
    const key = import.meta.env.VITE_GOOGLE_MAPS_KEY;
    if (!key) return '';
    const params = new URLSearchParams({ key, q: `${HO.lat},${HO.lng}`, zoom: '15' });
    return `https://www.google.com/maps/embed/v1/place?${params}`;
});

const tahun = new Date().getFullYear();
</script>
