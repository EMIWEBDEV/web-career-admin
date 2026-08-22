<!-- WEB CAREER — Portal Kandidat: Profil Saya (data akun REAL dari DB/sesi). -->
<template>
    <Head><title>Profil Saya - EVO Career</title></Head>
    <div class="wca">
        <div class="wca-phead">
            <div>
                <h1>Profil Saya</h1>
                <p>Informasi akun &amp; ringkasan lamaranmu di EVO Group.</p>
            </div>
        </div>

        <!-- Belum login -->
        <div v-if="!me" class="wca-empty2">
            <div class="wca-empty2__ic"><i class="bi bi-person-lock"></i></div>
            <h3>Kamu belum masuk</h3>
            <p>Masuk untuk melihat profil &amp; akunmu.</p>
            <Link href="/login?redirect=/profil" class="wca-btn wca-btn--primary"><i class="bi bi-box-arrow-in-right"></i> Masuk</Link>
        </div>

        <div v-else class="wca-grid wca-grid--2">
            <!-- Data Akun -->
            <div class="wca-card">
                <div class="wca-card__head"><h3><i class="bi bi-person-vcard"></i> Data Akun</h3></div>
                <div class="wca-card__body">
                    <div class="wca-prof__id">
                        <span class="wca-avatar wca-prof__av">{{ initials(me.nama) }}</span>
                        <div>
                            <div class="wca-prof__nama">{{ me.nama }}</div>
                            <div class="wca-prof__badges">
                                <span class="wca-badge" :class="me.role === 'ADMIN' ? 'wca-b--gold' : 'wca-b--sky'">{{ me.role === 'ADMIN' ? 'Admin' : 'Kandidat' }}</span>
                                <span class="wca-badge" :class="me.status === 'NONAKTIF' ? 'wca-b--red' : 'wca-b--green'"><i class="bi" :class="me.status === 'NONAKTIF' ? 'bi-x-circle' : 'bi-check-circle'"></i> {{ me.status === 'NONAKTIF' ? 'Nonaktif' : 'Aktif' }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="wca-dinfo">
                        <div><small>Email</small><b>{{ me.email }}</b></div>
                        <div><small>No. HP</small><b>{{ me.no_hp || '—' }}</b></div>
                        <div><small>Klasifikasi Akun</small><b>{{ klasLabel }}</b></div>
                        <div><small>Masa Berlaku</small><b>{{ masaBerlaku }}</b></div>
                        <div><small>Terdaftar</small><b>{{ fmt(me.mulai_berlaku) }}</b></div>
                        <div><small>Login Terakhir</small><b>{{ fmtDT(me.last_login_at) }}</b></div>
                    </div>
                    <div v-if="expiringSoon" class="wca-note wca-note--warn" style="margin-top:1rem"><i class="bi bi-hourglass-split"></i><span>Masa berlaku akun tinggal <b>{{ sisaHari }} hari</b>. Perpanjang bila diperlukan.</span></div>
                </div>
            </div>

            <div>
                <!-- Ringkasan Lamaran -->
                <div class="wca-card" style="margin-bottom:1.25rem">
                    <div class="wca-card__head"><h3><i class="bi bi-bar-chart"></i> Ringkasan Lamaran</h3></div>
                    <div class="wca-card__body">
                        <div class="wca-dinfo">
                            <div><small>Total Terkirim</small><b>{{ ringkasan.total }}</b></div>
                            <div><small>Sedang Berjalan</small><b>{{ ringkasan.berjalan }}</b></div>
                            <div><small>Selesai</small><b>{{ ringkasan.selesai }}</b></div>
                        </div>
                        <Link href="/kandidat/portal" class="wca-btn wca-btn--soft" style="width:100%;margin-top:1rem"><i class="bi bi-file-earmark-text"></i> Lihat Lamaran Saya</Link>
                    </div>
                </div>

                <!-- Keamanan -->
                <div class="wca-card">
                    <div class="wca-card__head"><h3><i class="bi bi-shield-check"></i> Keamanan</h3></div>
                    <div class="wca-card__body">
                        <Link :href="'/ganti-sandi?email=' + encodeURIComponent(me.email || '')" class="wca-btn wca-btn--ghost" style="width:100%;margin-bottom:.6rem"><i class="bi bi-key"></i> Ganti Kata Sandi</Link>
                        <a href="/logout" class="wca-btn wca-btn--danger" style="width:100%"><i class="bi bi-box-arrow-right"></i> Keluar</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import { initials } from '@utils/career/admin';
import { getApps, getSession } from '@utils/career/session';

const props = defineProps({
    user: { type: Object, default: null },
});

const sessUser = ref(null);
const apps = ref([]);
onMounted(() => {
    sessUser.value = getSession();
    apps.value = getApps();
});

// Prioritas: user real dari server (DB); fallback ke sesi klien (demo).
const me = computed(() => {
    if (props.user) return props.user;
    if (sessUser.value) return { nama: sessUser.value.nama, email: sessUser.value.email, role: sessUser.value.role || 'KANDIDAT', status: 'AKTIF' };
    return null;
});

const finals = computed(() => apps.value.filter((a) => a.status === 'FINAL'));
const ringkasan = computed(() => ({
    total: finals.value.length,
    berjalan: finals.value.filter((a) => (a.result || 'BERJALAN') === 'BERJALAN').length,
    selesai: finals.value.filter((a) => a.result === 'LULUS' || a.result === 'GAGAL').length,
}));

const KLAS = { PERMANEN: 'Permanen', TRIAL_3_MINGGU: 'Trial 3 Minggu', TRIAL_3_BULAN: 'Trial 3 Bulan', TRIAL_6_BULAN: 'Trial 6 Bulan' };
const klasLabel = computed(() => KLAS[me.value?.klasifikasi] || me.value?.klasifikasi || '—');

const BULAN = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
function fmt(v) {
    if (!v) return '—';
    const d = new Date(String(v).replace(' ', 'T'));
    if (isNaN(d.getTime())) return v;
    return `${d.getDate()} ${BULAN[d.getMonth()]} ${d.getFullYear()}`;
}
function fmtDT(v) {
    if (!v) return 'Belum pernah';
    const d = new Date(String(v).replace(' ', 'T'));
    if (isNaN(d.getTime())) return v;
    return `${d.getDate()} ${BULAN[d.getMonth()]} ${d.getFullYear()} · ${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`;
}
const masaBerlaku = computed(() => (me.value?.valid_until ? `s/d ${fmt(me.value.valid_until)}` : 'Permanen (tanpa batas)'));
const sisaHari = computed(() => {
    if (!me.value?.valid_until) return null;
    const d = new Date(String(me.value.valid_until).replace(' ', 'T'));
    return Math.ceil((d.getTime() - Date.now()) / 86400000);
});
const expiringSoon = computed(() => sisaHari.value !== null && sisaHari.value > 0 && sisaHari.value <= 14);
</script>
