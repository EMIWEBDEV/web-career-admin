<!-- WEB CAREER — Admin: Lowongan (READ-ONLY, ditarik dari MPP desktop). List + detail accordion. -->
<template>
    <Head><title>Lowongan (MPP) - Web Career</title></Head>
    <div class="wca">
        <div class="wca-phead">
            <div>
                <h1>Lowongan</h1>
                <p>Posisi ditarik otomatis dari <b>MPP</b> (HRIS desktop) via API. Data bersifat read-only — klik untuk melihat detail.</p>
            </div>
            <div class="wca-phead__actions">
                <button class="wca-btn wca-btn--ghost" @click="notice('Sinkronisasi MPP dijalankan (demo dummy).')"><i class="bi bi-arrow-repeat"></i> Sinkron MPP</button>
            </div>
        </div>

        <div class="wca-note wca-note--info">
            <i class="bi bi-hdd-network"></i>
            <span>Sumber data: <b>MPP desktop</b>. Penambahan / perubahan posisi dilakukan di aplikasi MPP, lalu tersinkron ke sini. Alur seleksi diatur di menu <b>Binding Alur</b>.</span>
        </div>

        <div class="wca-toolbar">
            <div class="wca-search2"><i class="bi bi-search"></i><input v-model="q" type="text" placeholder="Cari posisi / departemen…" /></div>
            <div class="wca-chips">
                <button class="wca-chip" :class="{ active: kat === '' }" @click="kat = ''">Semua</button>
                <button class="wca-chip" :class="{ active: kat === 'REKRUTMEN' }" @click="kat = 'REKRUTMEN'">Rekrutmen</button>
                <button class="wca-chip" :class="{ active: kat === 'MT' }" @click="kat = 'MT'">MT</button>
            </div>
        </div>

        <div class="wca-acc">
            <div v-for="l in filtered" :key="l.id" class="wca-acc__item" :class="{ open: open === l.id }">
                <button class="wca-acc__head" @click="toggle(l.id)">
                    <span class="wca-acc__chev"><i class="bi bi-chevron-right"></i></span>
                    <span class="wca-acc__title">
                        <strong>{{ l.posisi }}</strong>
                        <small>{{ l.id }} · {{ l.mppRef }} · <i class="bi bi-geo-alt"></i> {{ l.lokasi }}</small>
                    </span>
                    <span class="wca-acc__tags">
                        <span class="wca-badge" :class="l.kategori === 'MT' ? 'wca-b--gold' : 'wca-b--sky'">{{ l.kategori }}</span>
                        <span class="wca-badge" :class="l.masaBerlaku === 'EVERGREEN' ? 'wca-b--green' : 'wca-b--slate'">{{ l.masaBerlaku === 'EVERGREEN' ? 'Evergreen' : 'Berbatas' }}</span>
                        <span class="wca-badge" :class="statusBadge(l.statusPublish)">{{ l.statusPublish }}</span>
                    </span>
                    <span class="wca-acc__kpi"><b>{{ l.pelamar }}</b><small>pelamar</small></span>
                </button>

                <div class="wca-acc__body">
                    <div class="wca-acc__inner">
                        <div class="wca-dinfo wca-dinfo--4" style="margin-bottom:1rem">
                            <div><small>Kuota</small><b>{{ l.kuota }} orang</b></div>
                            <div><small>Level</small><b>{{ l.level }}</b></div>
                            <div><small>Tempat Kerja</small><b>{{ l.tempatKerja }}</b></div>
                            <div><small>Tipe</small><b>{{ l.tipeKerja }}</b></div>
                            <div><small>Masa Berlaku</small><b>{{ l.masaBerlaku === 'EVERGREEN' ? 'Evergreen (tanpa batas)' : 'Berbatas · ' + l.tutup }}</b></div>
                            <div><small>Status Publish</small><b>{{ l.statusPublish }}</b></div>
                            <div><small>Program</small><b>{{ l.program }}</b></div>
                            <div><small>Alur Seleksi</small><b>{{ l.alurNama }}</b></div>
                        </div>

                        <div class="wca-detail-grid">
                            <div class="wca-fsection">
                                <div class="wca-fsection__label"><i class="bi bi-file-text"></i> Deskripsi</div>
                                <p style="margin:0;font-size:.85rem;font-weight:600;color:var(--slate);line-height:1.6">{{ l.deskripsi }}</p>
                            </div>
                            <div class="wca-fsection">
                                <div class="wca-fsection__label"><i class="bi bi-list-check"></i> Tanggung Jawab</div>
                                <ul class="wca-ul"><li v-for="(t, i) in l.tanggungJawab" :key="i">{{ t }}</li></ul>
                            </div>
                            <div class="wca-fsection">
                                <div class="wca-fsection__label"><i class="bi bi-clipboard-check"></i> Persyaratan</div>
                                <ul class="wca-ul"><li v-for="(t, i) in l.persyaratan" :key="i">{{ t }}</li></ul>
                            </div>
                            <div class="wca-fsection">
                                <div class="wca-fsection__label"><i class="bi bi-tags"></i> Skill</div>
                                <div class="wca-tagset"><span v-for="s in l.skill" :key="s">{{ s }}</span></div>
                            </div>
                        </div>

                        <div class="wca-acc__foot">
                            <span class="wca-muted-note"><i class="bi bi-lock"></i> Detail read-only dari MPP</span>
                            <button class="wca-btn wca-btn--sm" :class="l.statusPublish === 'TERBIT' ? 'wca-btn--ghost' : 'wca-btn--dark'" @click="togglePublish(l)">
                                <i class="bi" :class="l.statusPublish === 'TERBIT' ? 'bi-eye-slash' : 'bi-megaphone'"></i> {{ l.statusPublish === 'TERBIT' ? 'Jadikan Draft' : 'Terbitkan' }}
                            </button>
                            <Link href="/karir/program-kegiatan" class="wca-btn wca-btn--soft wca-btn--sm"><i class="bi bi-diagram-3"></i> Kelola di Program</Link>
                            <a :href="`/test/karir/landing-page/lowongan/${l.id}`" target="_blank" class="wca-btn wca-btn--ghost wca-btn--sm"><i class="bi bi-box-arrow-up-right"></i> Lihat di Situs</a>
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="!filtered.length" class="wca-empty"><i class="bi bi-clipboard-x"></i><h4>Tidak ada lowongan cocok</h4></div>
        </div>

        <transition name="wca-toast"><div v-if="toast" class="wca-toast"><i class="bi bi-check-circle-fill"></i> {{ toast }}</div></transition>
    </div>
</template>

<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';
import { statusBadge } from './careerAdmin';

const props = defineProps({
    lowongan: { type: Array, default: () => [] },
});

const list = reactive(props.lowongan.map((l) => ({ ...l })));
const q = ref('');
const kat = ref('');
const open = ref(null);
function toggle(id) {
    open.value = open.value === id ? null : id;
}

const filtered = computed(() => {
    const s = q.value.trim().toLowerCase();
    return list.filter((l) => {
        if (kat.value && l.kategori !== kat.value) return false;
        if (!s) return true;
        return (l.posisi + ' ' + l.departemen + ' ' + l.id).toLowerCase().includes(s);
    });
});

function togglePublish(l) {
    l.statusPublish = l.statusPublish === 'TERBIT' ? 'DRAFT' : 'TERBIT';
    notice(`Lowongan ${l.statusPublish === 'TERBIT' ? 'diterbitkan ke landing' : 'dijadikan draft (disembunyikan)'} (demo dummy).`);
}

const toast = ref('');
let t = null;
function notice(m) {
    toast.value = m;
    if (t) clearTimeout(t);
    t = setTimeout(() => (toast.value = ''), 3000);
}
</script>
