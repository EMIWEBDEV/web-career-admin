<!-- WEB CAREER — Footer 2 tingkat (bagian dari CareerLayout) -->
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
                <p>Bergabunglah dengan ekosistem people, pet, &amp; manufacturing EVO Group. Naik level bersama kami.</p>
                <div class="wc-footer__social">
                    <a href="#" @click.prevent aria-label="Instagram"><i class="bi bi-instagram"></i></a>
                    <a href="#" @click.prevent aria-label="LinkedIn"><i class="bi bi-linkedin"></i></a>
                    <a href="#" @click.prevent aria-label="Website"><i class="bi bi-globe"></i></a>
                </div>
            </div>
            <div class="wc-footer__cols">
                <div class="wc-footer__col">
                    <strong>Karier</strong>
                    <button type="button" @click="goToSection('lowongan')">Lowongan</button>
                    <button v-if="hasMt" type="button" @click="goToSection('mt')">Management Trainee</button>
                    <button type="button" @click="goToSection('lokasi')">Lokasi Kami</button>
                </div>
                <div class="wc-footer__col">
                    <strong>Perusahaan</strong>
                    <a href="/about">Tentang Kami</a>
                    <button type="button" @click="goToSection('achievement')">Pencapaian</button>
                    <a href="#" @click.prevent>Budaya Kerja</a>
                </div>
                <div class="wc-footer__col">
                    <strong>Portal &amp; Bantuan</strong>
                    <a href="/kandidat/portal">Portal Kandidat</a>
                    <a href="/karir">Panel Admin</a>
                    <a href="#" @click.prevent>FAQ Rekrutmen</a>
                </div>
                <div class="wc-footer__col wc-footer__col--office">
                    <strong>Kantor Kami</strong>
                    <div v-for="o in offices" :key="o.kota" class="wc-footer__office">
                        <i class="bi bi-geo-alt-fill"></i>
                        <span><b>{{ o.kota }}</b><small>{{ o.tipe }}</small></span>
                    </div>
                </div>
            </div>
        </div>
        <!-- Tingkat 2 -->
        <div class="wc-footer__bar">
            <div class="wc-footer__bar-inner">
                <span>&copy; 2026 EVO Group Career. Halaman demo (dummy).</span>
                <span class="wc-footer__credit">Dibuat dengan <i class="bi bi-heart-fill"></i> oleh <b>Tim Technology</b></span>
            </div>
        </div>
    </footer>
</template>

<script setup>
import { goToSection } from '../careerData';

defineProps({
    hasMt: { type: Boolean, default: false },
    offices: { type: Array, default: () => [] },
});
</script>
