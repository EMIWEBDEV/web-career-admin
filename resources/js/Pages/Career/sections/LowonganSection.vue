<!-- WEB CAREER — Section 3: Lowongan (search + filter + kartu) -->
<template>
    <section id="lowongan" class="wc-section">
        <div class="wc-sec-head wc-reveal">
            <span class="wc-eyebrow"><span class="wc-dot"></span> Lowongan Terbuka</span>
            <h2>Temukan peran <span class="wc-grad">terbaikmu</span>.</h2>
            <p>Cari berdasarkan posisi, departemen, atau lokasi. {{ lowongan.length }} peluang menanti.</p>
        </div>

        <!-- Filter toolbar -->
        <div class="wc-toolbar">
            <div class="wc-toolbar__search">
                <i class="bi bi-search"></i>
                <input v-model="search" type="text" placeholder="Cari posisi, skill, atau departemen…" />
                <button v-if="search" type="button" class="wc-toolbar__clear" @click="search = ''"><i class="bi bi-x-lg"></i></button>
            </div>
            <div class="wc-toolbar__filters">
                <div v-for="f in filterDefs" :key="f.key" class="wc-select">
                    <button
                        type="button"
                        class="wc-select__btn"
                        :class="{ 'is-active': f.value, 'is-open': openFilter === f.key }"
                        @click.stop="toggleFilter(f.key)"
                    >
                        <i class="bi" :class="f.icon"></i>
                        <span class="wc-select__val">{{ f.value || f.label }}</span>
                        <i class="bi bi-chevron-down wc-select__chev"></i>
                    </button>
                    <transition name="wc-pop">
                        <div v-if="openFilter === f.key" class="wc-select__menu" @click.stop>
                            <button type="button" class="wc-select__opt" :class="{ 'is-sel': !f.value }" @click="chooseFilter(f.key, '')">
                                <span>{{ f.all }}</span><i v-if="!f.value" class="bi bi-check2"></i>
                            </button>
                            <button
                                v-for="o in f.options"
                                :key="o"
                                type="button"
                                class="wc-select__opt"
                                :class="{ 'is-sel': f.value === o }"
                                @click="chooseFilter(f.key, o)"
                            >
                                <span>{{ o }}</span><i v-if="f.value === o" class="bi bi-check2"></i>
                            </button>
                        </div>
                    </transition>
                </div>
            </div>
        </div>

        <div class="wc-result-head">
            <span><strong>{{ filtered.length }}</strong> dari {{ lowongan.length }} lowongan</span>
            <div v-if="hasActiveFilter" class="wc-active">
                <button v-if="search" type="button" class="wc-pill" @click="search = ''"><i class="bi bi-search"></i> {{ truncate(search) }} <i class="bi bi-x-lg"></i></button>
                <button v-if="filterDept" type="button" class="wc-pill" @click="filterDept = ''">{{ filterDept }} <i class="bi bi-x-lg"></i></button>
                <button v-if="filterLoc" type="button" class="wc-pill" @click="filterLoc = ''">{{ filterLoc }} <i class="bi bi-x-lg"></i></button>
                <button v-if="filterType" type="button" class="wc-pill" @click="filterType = ''">{{ filterType }} <i class="bi bi-x-lg"></i></button>
                <button type="button" class="wc-reset" @click="resetFilters"><i class="bi bi-arrow-counterclockwise"></i> Reset semua</button>
            </div>
        </div>

        <TransitionGroup v-if="filtered.length" name="wc-jobs" tag="div" class="wc-job-grid" appear>
            <Link v-for="job in filtered" :key="job.id" class="wc-job" :class="{ 'is-full': isFull(job) }" :href="lowonganUrl(job.id)">
                <div class="wc-job__glow"></div>
                <div v-if="isFull(job)" class="wc-job__ribbon"><i class="bi bi-lock-fill"></i> Penuh</div>
                <div class="wc-job__top">
                    <span class="wc-badge" :class="typeClass(job.tipeKerja)">{{ job.tipeKerja }}</span>
                    <span v-if="job.unggulan" class="wc-badge wc-badge--star"><i class="bi bi-star-fill"></i> Unggulan</span>
                </div>
                <h3>{{ job.posisi }}</h3>
                <p class="wc-job__co"><i class="bi bi-building"></i> {{ job.perusahaan }}</p>
                <p class="wc-job__sum">{{ job.ringkasan }}</p>

                <div class="wc-job__meta">
                    <span><i class="bi bi-geo-alt"></i> {{ job.lokasi }} · {{ job.tempatKerja }}</span>
                    <span><i class="bi bi-bar-chart-steps"></i> {{ job.level }}</span>
                    <span><i class="bi bi-briefcase"></i> {{ job.pengalaman }}</span>
                </div>

                <div class="wc-tags">
                    <span v-for="s in job.skill.slice(0, 3)" :key="s">{{ s }}</span>
                    <span v-if="job.skill.length > 3" class="wc-tags__more">+{{ job.skill.length - 3 }}</span>
                </div>

                <div class="wc-job__foot">
                    <span class="wc-job__quota"><i class="bi bi-people"></i> {{ job.kuotaTerisi }}/{{ job.kuota }} terisi · {{ job.pelamar }} pelamar</span>
                    <span v-if="isFull(job)" class="wc-deadline soon"><i class="bi bi-lock-fill"></i> Kuota penuh</span>
                    <span v-else class="wc-deadline" :class="{ soon: daysLeft(job.tanggalTutup) <= 7 }"><i class="bi bi-clock"></i> {{ deadlineLabel(job.tanggalTutup) }}</span>
                </div>
                <span class="wc-job__cta">Lihat detail <i class="bi bi-arrow-right"></i></span>
            </Link>
        </TransitionGroup>

        <div v-else class="wc-empty">
            <i class="bi bi-clipboard-x"></i>
            <h4>Tidak ada lowongan yang cocok</h4>
            <p>Coba ubah kata kunci atau reset filter pencarian.</p>
            <button type="button" @click="resetFilters"><i class="bi bi-arrow-counterclockwise"></i> Reset filter</button>
        </div>
    </section>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { daysLeft, deadlineLabel, isFull, lowonganUrl, typeClass } from '../careerData';

const props = defineProps({
    lowongan: { type: Array, default: () => [] },
    departments: { type: Array, default: () => [] },
    locations: { type: Array, default: () => [] },
});

const search = ref('');
const filterDept = ref('');
const filterLoc = ref('');
const filterType = ref('');
const openFilter = ref(null);

const workTypes = computed(() => [...new Set(props.lowongan.map((j) => j.tipeKerja))]);
const hasActiveFilter = computed(() => !!(search.value || filterDept.value || filterLoc.value || filterType.value));

const filterDefs = computed(() => [
    { key: 'dept', label: 'Divisi', all: 'Semua Divisi', icon: 'bi-diagram-3', options: props.departments, value: filterDept.value },
    { key: 'loc', label: 'Lokasi', all: 'Semua Lokasi', icon: 'bi-geo-alt', options: props.locations, value: filterLoc.value },
    { key: 'type', label: 'Tipe Kerja', all: 'Semua Tipe', icon: 'bi-clock-history', options: workTypes.value, value: filterType.value },
]);

const filtered = computed(() => {
    const q = search.value.trim().toLowerCase();
    return props.lowongan.filter((job) => {
        if (filterDept.value && job.departemen !== filterDept.value) return false;
        if (filterLoc.value && job.lokasi !== filterLoc.value) return false;
        if (filterType.value && job.tipeKerja !== filterType.value) return false;
        if (!q) return true;
        return [job.posisi, job.departemen, job.perusahaan, job.ringkasan, ...(job.skill || [])].join(' ').toLowerCase().includes(q);
    });
});

function toggleFilter(key) {
    openFilter.value = openFilter.value === key ? null : key;
}
function chooseFilter(key, val) {
    if (key === 'dept') filterDept.value = val;
    else if (key === 'loc') filterLoc.value = val;
    else if (key === 'type') filterType.value = val;
    openFilter.value = null;
}
function resetFilters() {
    search.value = '';
    filterDept.value = '';
    filterLoc.value = '';
    filterType.value = '';
}
function truncate(s, n = 18) {
    return s && s.length > n ? s.slice(0, n) + '…' : s;
}
function closeFilters() {
    openFilter.value = null;
}
function onKeydown(e) {
    if (e.key === 'Escape') openFilter.value = null;
}

onMounted(() => {
    document.addEventListener('click', closeFilters);
    document.addEventListener('keydown', onKeydown);
});
onUnmounted(() => {
    document.removeEventListener('click', closeFilters);
    document.removeEventListener('keydown', onKeydown);
});
</script>
