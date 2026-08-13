<!--
  Master MPP — panel detail off-canvas. Baca lengkap (termasuk Tanggung Jawab/
  Persyaratan/Skill/Benefit, baca-saja — dipindah dari Monitoring MPP) PLUS
  aksi: Ubah / Batalkan-Aktifkan / Tandai Selesai langsung dari panel.
  Fetch via /api/v1/master-mpp/{no}. Tutup: tombol X, klik overlay, Escape.
-->
<template>
    <teleport to="body">
        <transition name="mmp-drawer">
            <div v-if="no" class="wca-drawer-mask wca" @click.self="$emit('close')">
                <aside ref="panel" class="wca-drawer wca-drawer--wide" role="dialog" aria-modal="true" :aria-label="`Detail MPP ${no}`">
                    <div class="wca-drawer__head">
                        <span class="wca-avatar"><i class="bi bi-briefcase-fill"></i></span>
                        <div style="min-width: 0">
                            <h3>{{ detail ? (detail.jabatan.nama || 'Jabatan belum diisi') : 'Memuat…' }}</h3>
                            <p><i class="bi bi-hash"></i>{{ no }}</p>
                        </div>
                        <button ref="closeBtn" class="wca-drawer__close" aria-label="Tutup detail" @click="$emit('close')"><i class="bi bi-x-lg"></i></button>
                    </div>

                    <div class="wca-drawer__body">
                        <template v-if="loading">
                            <div class="mmp-sk mmp-sk--grid">
                                <div v-for="n in 8" :key="n" class="mmp-sk__box"></div>
                            </div>
                            <div v-for="n in 3" :key="'s' + n" class="mmp-sk__card"></div>
                        </template>

                        <div v-else-if="error" class="wca-empty">
                            <i class="bi bi-exclamation-triangle"></i>
                            <h4>Gagal memuat detail</h4>
                            <button class="wca-btn wca-btn--ghost wca-btn--sm" @click="load"><i class="bi bi-arrow-clockwise"></i> Coba lagi</button>
                        </div>

                        <template v-else-if="detail">
                            <div class="mmp-dactions">
                                <button class="wca-btn wca-btn--dark wca-btn--sm" @click="$emit('edit', detail)"><i class="bi bi-pencil"></i> Ubah</button>
                                <button
                                    class="wca-btn wca-btn--soft wca-btn--sm"
                                    @click="$emit('toggle-selesai', detail)"
                                ><i class="bi" :class="detail.selesai ? 'bi-arrow-counterclockwise' : 'bi-check2-circle'"></i> {{ detail.selesai ? 'Belum Selesai' : 'Tandai Selesai' }}</button>
                                <button v-if="detail.status === 'AKTIF'" class="wca-btn wca-btn--ghost wca-btn--sm" @click="$emit('batalkan', detail)"><i class="bi bi-x-circle"></i> Batalkan</button>
                                <button v-else class="wca-btn wca-btn--ghost wca-btn--sm" @click="$emit('aktifkan', detail)"><i class="bi bi-arrow-counterclockwise"></i> Aktifkan Kembali</button>
                            </div>

                            <div class="wca-dsec">
                                <h4>Informasi</h4>
                                <div class="wca-dinfo">
                                    <div><small>Status</small><b><span class="wca-badge" :class="statusBadge(detail.status)">{{ statusLabel(detail.status) }}</span></b></div>
                                    <div><small>Flag Selesai</small><b><span class="mmp-flag" :class="{ 'is-done': detail.selesai }"><span class="mmp-flag__dot"></span>{{ detail.selesai ? 'Selesai' : 'Berjalan' }}</span></b></div>
                                    <div><small>Jenis Program</small><b><span class="mmp-chip-program" :class="{ 'is-mt': detail.jenisProgram === 'MT' }"><i class="bi" :class="detail.jenisProgram === 'MT' ? 'bi-mortarboard-fill' : 'bi-person-workspace'"></i> {{ jenisProgramLabel(detail.jenisProgram) }}</span></b></div>
                                    <div><small>Divisi</small><b>{{ detail.divisi.nama || '—' }}</b></div>
                                    <div><small>Departemen</small><b>{{ detail.subDivisi?.nama || '—' }}</b></div>
                                    <div><small>Level</small><b>{{ detail.level.nama || '—' }}</b></div>
                                    <div><small>Jumlah Rekrutmen</small><b>{{ detail.jumlahRekrutmen }} orang</b></div>
                                    <div><small>Tanggal Periode</small><b>{{ formatTanggal(detail.tanggalPeriode) }}</b></div>
                                    <div><small>Lokasi</small><b>{{ detail.lokasi.nama || '—' }}</b></div>
                                    <div><small>Penanggung Jawab</small><b>{{ detail.penanggungJawab.nama }}</b></div>
                                </div>
                            </div>

                            <div v-if="detail.employmentType || detail.workplaceType || detail.experienceLevel" class="wca-seccard mmp-detail-row">
                                <div class="wca-seccard__top">
                                    <span class="wca-seccard__ico" style="background: rgba(139, 92, 246, 0.1); color: #7c3aed"><i class="bi bi-info-circle"></i></span>
                                    <strong>Detail Pekerjaan</strong>
                                </div>
                                <div class="mmp-dpills">
                                    <span v-if="detail.employmentType" class="mmp-dpill mmp-dpill--emp" title="Tipe Kerja">
                                        <span class="mmp-dpill__ico"><i class="bi bi-briefcase-fill"></i></span>
                                        <span class="mmp-dpill__body"><small>Tipe Kerja</small><strong>{{ detail.employmentType.nama }}</strong></span>
                                    </span>
                                    <span v-if="detail.workplaceType" class="mmp-dpill mmp-dpill--wp" title="Lokasi Kerja">
                                        <span class="mmp-dpill__ico"><i class="bi bi-geo-alt-fill"></i></span>
                                        <span class="mmp-dpill__body"><small>Lokasi Kerja</small><strong>{{ detail.workplaceType.nama }}</strong></span>
                                    </span>
                                    <span v-if="detail.experienceLevel" class="mmp-dpill mmp-dpill--exp" title="Level Pengalaman">
                                        <span class="mmp-dpill__ico"><i class="bi bi-stars"></i></span>
                                        <span class="mmp-dpill__body"><small>Level Pengalaman</small><strong>{{ detail.experienceLevel.nama }}</strong></span>
                                    </span>
                                </div>
                            </div>

                            <div class="wca-seccard sec-data">
                                <div class="wca-seccard__top">
                                    <span class="wca-seccard__ico"><i class="bi bi-file-earmark-text"></i></span>
                                    <strong>Deskripsi Pekerjaan</strong>
                                </div>
                                <p class="mmp-desc">{{ detail.deskripsi || '—' }}</p>
                            </div>

                            <div class="wca-seccard sec-say">
                                <div class="wca-seccard__top">
                                    <span class="wca-seccard__ico" style="background: rgba(16, 185, 129, 0.12); color: #059669"><i class="bi bi-list-check"></i></span>
                                    <strong>Tanggung Jawab</strong>
                                    <span class="wca-badge wca-b--slate">{{ detail.tanggungJawab.length }}</span>
                                </div>
                                <ul class="mmp-checklist">
                                    <li v-for="(t, i) in detail.tanggungJawab" :key="i"><i class="bi bi-check-circle-fill"></i><span>{{ t }}</span></li>
                                    <li v-if="!detail.tanggungJawab.length" class="mmp-empty-line">Belum ada tanggung jawab.</li>
                                </ul>
                            </div>

                            <div class="wca-seccard sec-upl">
                                <div class="wca-seccard__top">
                                    <span class="wca-seccard__ico" style="background: rgba(245, 158, 11, 0.12); color: #d97706"><i class="bi bi-clipboard-check"></i></span>
                                    <strong>Persyaratan</strong>
                                    <span class="wca-badge wca-b--slate">{{ detail.persyaratan.length }}</span>
                                </div>
                                <ul class="mmp-reqlist">
                                    <li v-for="(p, i) in detail.persyaratan" :key="i"><i class="bi bi-dot"></i><span>{{ p }}</span></li>
                                    <li v-if="!detail.persyaratan.length" class="mmp-empty-line">Belum ada persyaratan.</li>
                                </ul>
                            </div>

                            <div class="wca-seccard sec-tpl">
                                <div class="wca-seccard__top">
                                    <span class="wca-seccard__ico" style="background: rgba(139, 92, 246, 0.12); color: #7c3aed"><i class="bi bi-stars"></i></span>
                                    <strong>Skill yang Dibutuhkan ({{ detail.skill?.length || 0 }})</strong>
                                </div>

                                <div v-if="categorizedSkills.length" class="mmp-sk-cat-list">
                                    <div v-for="cat in categorizedSkills" :key="cat.key" class="mmp-sk-cat-block">
                                        <div class="mmp-sk-cat-header">
                                            <span class="mmp-sk-cat-badge" :style="{ background: cat.warna + '18', color: cat.warna, borderColor: cat.warna + '33' }">
                                                <i class="bi" :class="cat.ikon"></i> {{ cat.nama }}
                                            </span>
                                        </div>
                                        <div class="mmp-tags">
                                            <span
                                                v-for="s in cat.items" :key="s.id" class="mmp-tag"
                                                :style="{ color: cat.warna, background: cat.warna + '12', borderColor: cat.warna + '30' }"
                                            >
                                                {{ s.nama }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <span v-else class="mmp-empty-line">Belum ada skill.</span>
                            </div>

                            <div class="wca-seccard">
                                <div class="wca-seccard__top">
                                    <span class="wca-seccard__ico" style="background: rgba(16, 185, 129, 0.12); color: #059669"><i class="bi bi-gift"></i></span>
                                    <strong>Benefit</strong>
                                </div>
                                <div class="mmp-benefits">
                                    <div v-for="b in detail.benefit" :key="b.id" class="mmp-benefit"><i class="bi bi-check-circle-fill"></i><span>{{ b.nama }}</span></div>
                                    <span v-if="!detail.benefit.length" class="mmp-empty-line">Belum ada benefit.</span>
                                </div>
                            </div>
                        </template>
                    </div>
                </aside>
            </div>
        </transition>
    </teleport>
</template>

<script setup>
import { ref, computed, watch, onBeforeUnmount, nextTick } from 'vue';
import axios from 'axios';
import { formatTanggal, statusBadge, statusLabel, jenisProgramLabel } from './masterMppHelpers';

const props = defineProps({ no: { type: String, default: null } });
const emit = defineEmits(['close', 'edit', 'toggle-selesai', 'batalkan', 'aktifkan']);

const detail = ref(null);
const loading = ref(false);
const error = ref(false);
const panel = ref(null);
const closeBtn = ref(null);

const categorizedSkills = computed(() => {
    if (!detail.value?.skill || !detail.value.skill.length) return [];
    const map = new Map();
    for (const s of detail.value.skill) {
        const key = s.kategori ? s.kategori.id : '__tanpa__';
        if (!map.has(key)) {
            map.set(key, {
                key,
                nama: s.kategori ? s.kategori.nama : 'Lainnya / Umum',
                warna: s.kategori ? (s.kategori.warna || '#6366f1') : '#94a3b8',
                ikon: s.kategori?.ikon || 'bi-stars',
                items: [],
            });
        }
        map.get(key).items.push(s);
    }
    return [...map.values()];
});

let triggerEl = null;

async function load() {
    if (!props.no) return;
    loading.value = true;
    error.value = false;
    try {
        const res = await axios.get(`/api/v1/master-mpp/${encodeURIComponent(props.no)}`, { headers: { Accept: 'application/json' } });
        detail.value = res.data.result || null;
        if (!detail.value) error.value = true;
    } catch (e) {
        error.value = true;
    } finally {
        loading.value = false;
    }
}

defineExpose({ load });

function focusables() {
    if (!panel.value) return [];
    return Array.from(
        panel.value.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])')
    ).filter((el) => !el.disabled && el.offsetParent !== null);
}

function onKeydown(e) {
    if (e.key === 'Escape') {
        emit('close');
        return;
    }
    if (e.key !== 'Tab') return;
    const items = focusables();
    if (!items.length) return;
    const first = items[0];
    const last = items[items.length - 1];
    if (e.shiftKey && document.activeElement === first) {
        e.preventDefault();
        last.focus();
    } else if (!e.shiftKey && document.activeElement === last) {
        e.preventDefault();
        first.focus();
    }
}

watch(
    () => props.no,
    async (val) => {
        if (val) {
            triggerEl = document.activeElement;
            detail.value = null;
            load();
            document.addEventListener('keydown', onKeydown);
            document.body.style.overflow = 'hidden';
            await nextTick();
            closeBtn.value?.focus();
        } else {
            document.removeEventListener('keydown', onKeydown);
            document.body.style.overflow = '';
            if (triggerEl && typeof triggerEl.focus === 'function') triggerEl.focus();
            triggerEl = null;
        }
    },
    { immediate: true }
);

onBeforeUnmount(() => {
    document.removeEventListener('keydown', onKeydown);
    document.body.style.overflow = '';
});
</script>

<style scoped>
.wca-drawer--wide { width: min(540px, 100%); }
.wca-drawer__head p { display: inline-flex; align-items: center; gap: 0.1rem; font-family: 'JetBrains Mono', monospace; }

.mmp-drawer-enter-active, .mmp-drawer-leave-active { transition: opacity 0.28s ease; }
.mmp-drawer-enter-active .wca-drawer, .mmp-drawer-leave-active .wca-drawer { transition: transform 0.3s cubic-bezier(0.22, 1, 0.36, 1); }
.mmp-drawer-enter-from, .mmp-drawer-leave-to { opacity: 0; }
.mmp-drawer-enter-from .wca-drawer, .mmp-drawer-leave-to .wca-drawer { transform: translateX(100%); }

.mmp-dactions { display: flex; flex-wrap: wrap; gap: 0.5rem; margin-bottom: 1.1rem; }

.mmp-flag { display: inline-flex; align-items: center; gap: 0.35rem; font-size: 0.78rem; font-weight: 800; color: #94a3b8; }
.mmp-flag__dot { width: 0.55rem; height: 0.55rem; border-radius: 50%; background: #cbd5e1; }
.mmp-flag.is-done { color: #15803d; }
.mmp-flag.is-done .mmp-flag__dot { background: #22c55e; box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.18); }

.mmp-chip-program { display: inline-flex; align-items: center; gap: 0.35rem; font-size: 0.78rem; font-weight: 800; color: #475569; }
.mmp-chip-program.is-mt { color: #7c3aed; }

.mmp-desc { margin: 0; font-size: 0.83rem; line-height: 1.6; color: var(--slate); }

.mmp-checklist, .mmp-reqlist { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 0.5rem; }
.mmp-checklist li, .mmp-reqlist li { display: flex; gap: 0.5rem; font-size: 0.82rem; line-height: 1.5; color: var(--slate); }
.mmp-checklist i { color: #10b981; margin-top: 0.15rem; flex: none; }
.mmp-reqlist i { color: #94a3b8; font-size: 1.1rem; line-height: 1; flex: none; }

.mmp-tags { display: flex; flex-wrap: wrap; gap: 0.4rem; }
.mmp-tags .mmp-tag {
    display: inline-flex; align-items: center; gap: 0.35rem;
    padding: 0.3rem 0.7rem; border-radius: 999px; font-size: 0.75rem; font-weight: 800; color: #4338ca;
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.12), rgba(139, 92, 246, 0.14));
    border: 1px solid rgba(99, 102, 241, 0.18);
}

.mmp-sk-cat-list {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    margin-top: 0.4rem;
}

.mmp-sk-cat-block {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
}

.mmp-sk-cat-header {
    display: flex;
    align-items: center;
}

.mmp-sk-cat-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    font-size: 0.7rem;
    font-weight: 800;
    padding: 0.15rem 0.5rem;
    border-radius: 0.4rem;
    border: 1px solid transparent;
    text-transform: uppercase;
    letter-spacing: 0.03em;
}

.mmp-benefits { display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem; }
.mmp-benefit { display: flex; align-items: center; gap: 0.45rem; font-size: 0.8rem; font-weight: 700; color: var(--slate); }
.mmp-benefit i { color: #10b981; flex: none; }
.mmp-empty-line { font-size: 0.8rem; color: var(--muted); font-style: italic; }

.mmp-sk--grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0.6rem; }
.mmp-sk__box { height: 3rem; border-radius: 0.6rem; }
.mmp-sk__card { height: 5.5rem; border-radius: 0.85rem; }
.mmp-sk__box, .mmp-sk__card {
    background: linear-gradient(90deg, #f1f5f9 25%, #e9eef5 37%, #f1f5f9 63%);
    background-size: 400% 100%;
    animation: mmpShimmer 1.3s ease infinite;
}
@keyframes mmpShimmer {
    0% { background-position: 100% 0; }
    100% { background-position: -100% 0; }
}

@media (max-width: 640px) {
    .mmp-benefits { grid-template-columns: 1fr; }
}

.mmp-detail-row { border-left-color: #7c3aed; }
.mmp-dpills { display: flex; flex-wrap: wrap; gap: 0.6rem; }
.mmp-dpill { display: flex; align-items: flex-start; gap: 0.55rem; padding: 0.55rem 0.7rem; border-radius: 0.75rem; flex: 1 1 150px; min-width: 140px; transition: transform 0.12s ease; }
.mmp-dpill:hover { transform: translateY(-1px); }
.mmp-dpill--emp { background: linear-gradient(135deg, rgba(99, 102, 241, 0.08), rgba(139, 92, 246, 0.04)); border: 1px solid rgba(99, 102, 241, 0.15); }
.mmp-dpill--wp { background: linear-gradient(135deg, rgba(16, 185, 129, 0.08), rgba(6, 182, 212, 0.04)); border: 1px solid rgba(16, 185, 129, 0.15); }
.mmp-dpill--exp { background: linear-gradient(135deg, rgba(245, 158, 11, 0.08), rgba(251, 191, 36, 0.04)); border: 1px solid rgba(245, 158, 11, 0.18); }
.mmp-dpill__ico { width: 2rem; height: 2rem; border-radius: 0.55rem; display: grid; place-items: center; flex: none; font-size: 0.88rem; }
.mmp-dpill--emp .mmp-dpill__ico { background: rgba(99, 102, 241, 0.15); color: #4338ca; }
.mmp-dpill--wp .mmp-dpill__ico { background: rgba(16, 185, 129, 0.15); color: #0f766e; }
.mmp-dpill--exp .mmp-dpill__ico { background: rgba(245, 158, 11, 0.15); color: #b45309; }
.mmp-dpill__body { display: flex; flex-direction: column; gap: 0.1rem; min-width: 0; }
.mmp-dpill__body small { font-size: 0.68rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.03em; color: var(--muted); line-height: 1; }
.mmp-dpill__body strong { font-size: 0.84rem; font-weight: 900; color: var(--ink); line-height: 1.2; }
</style>
