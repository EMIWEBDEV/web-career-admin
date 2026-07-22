<!--
  WEB CAREER — Monitoring MPP: off-canvas detail (reusable, dipicu dari card ATAU row).
  Fetch detail via /api/v1/monitoring-mpp/{no}. Tutup: tombol X, klik overlay, Escape.
-->
<template>
    <teleport to="body">
        <transition name="mpp-drawer">
            <div v-if="no" class="wca-drawer-mask wca" @click.self="$emit('close')">
                <aside ref="panel" class="wca-drawer wca-drawer--wide" role="dialog" aria-modal="true" :aria-label="`Detail MPP ${no}`">
                    <!-- Header -->
                    <div class="wca-drawer__head">
                        <span class="wca-avatar"><i class="bi bi-briefcase-fill"></i></span>
                        <div style="min-width:0">
                            <h3>{{ detail?.jabatan || 'Memuat…' }}</h3>
                            <p><i class="bi bi-hash"></i>{{ no }}</p>
                        </div>
                        <button ref="closeBtn" class="wca-drawer__close" aria-label="Tutup detail" @click="$emit('close')"><i class="bi bi-x-lg"></i></button>
                    </div>

                    <div class="wca-drawer__body">
                        <!-- Skeleton saat fetch -->
                        <template v-if="loading">
                            <div class="mpp-sk mpp-sk--grid">
                                <div v-for="n in 8" :key="n" class="mpp-sk__box"></div>
                            </div>
                            <div v-for="n in 3" :key="'s' + n" class="mpp-sk__card"></div>
                        </template>

                        <!-- Error -->
                        <div v-else-if="error" class="wca-empty">
                            <i class="bi bi-exclamation-triangle"></i>
                            <h4>Gagal memuat detail</h4>
                            <button class="wca-btn wca-btn--ghost wca-btn--sm" @click="load"><i class="bi bi-arrow-clockwise"></i> Coba lagi</button>
                        </div>

                        <!-- Konten -->
                        <template v-else-if="detail">
                            <!-- Info ringkas -->
                            <div class="wca-dsec">
                                <h4>Informasi</h4>
                                <div class="wca-dinfo">
                                    <div><small>Status</small><b><span class="wca-badge" :class="statusBadge(detail.status)">{{ detail.status }}</span></b></div>
                                    <div><small>Flag Selesai</small><b><span class="wca-badge" :class="flagBadge(detail.flag_selesai)">{{ detail.flag_selesai }}</span></b></div>
                                    <div><small>Divisi</small><b>{{ detail.divisi }}</b></div>
                                    <div><small>Sub Divisi</small><b>{{ detail.sub_divisi }}</b></div>
                                    <div><small>Level</small><b>{{ detail.level }}</b></div>
                                    <div><small>Jumlah Rekrutmen</small><b>{{ detail.jumlah_rekruitmen }} orang</b></div>
                                    <div><small>Tanggal Periode</small><b>{{ formatTanggal(detail.tanggal_periode) }}</b></div>
                                    <div><small>Penanggung Jawab</small><b>{{ namaLengkap(detail.penanggung_jawab) }}</b></div>
                                </div>
                            </div>

                            <!-- Deskripsi -->
                            <div class="wca-seccard sec-data">
                                <div class="wca-seccard__top">
                                    <span class="wca-seccard__ico"><i class="bi bi-file-earmark-text"></i></span>
                                    <strong>Deskripsi Pekerjaan</strong>
                                </div>
                                <p class="mpp-desc">{{ detail.deskripsi || '—' }}</p>
                            </div>

                            <!-- Tanggung Jawab -->
                            <div class="wca-seccard sec-say">
                                <div class="wca-seccard__top">
                                    <span class="wca-seccard__ico" style="background:rgba(16,185,129,.12);color:#059669"><i class="bi bi-list-check"></i></span>
                                    <strong>Tanggung Jawab</strong>
                                    <span class="wca-badge wca-b--slate">{{ detail.tanggung_jawab.length }}</span>
                                </div>
                                <ul class="mpp-checklist">
                                    <li v-for="(t, i) in detail.tanggung_jawab" :key="i"><i class="bi bi-check-circle-fill"></i><span>{{ t }}</span></li>
                                    <li v-if="!detail.tanggung_jawab.length" class="mpp-empty-line">Belum ada tanggung jawab.</li>
                                </ul>
                            </div>

                            <!-- Persyaratan -->
                            <div class="wca-seccard sec-upl">
                                <div class="wca-seccard__top">
                                    <span class="wca-seccard__ico" style="background:rgba(245,158,11,.12);color:#d97706"><i class="bi bi-clipboard-check"></i></span>
                                    <strong>Persyaratan</strong>
                                    <span class="wca-badge wca-b--slate">{{ detail.persyaratan.length }}</span>
                                </div>
                                <ul class="mpp-reqlist">
                                    <li v-for="(p, i) in detail.persyaratan" :key="i"><i class="bi bi-dot"></i><span>{{ p }}</span></li>
                                    <li v-if="!detail.persyaratan.length" class="mpp-empty-line">Belum ada persyaratan.</li>
                                </ul>
                            </div>

                            <!-- Skill -->
                            <div class="wca-seccard sec-tpl">
                                <div class="wca-seccard__top">
                                    <span class="wca-seccard__ico" style="background:rgba(139,92,246,.12);color:#7c3aed"><i class="bi bi-stars"></i></span>
                                    <strong>Skill yang Dibutuhkan</strong>
                                </div>
                                <div class="mpp-tags">
                                    <span v-for="(s, i) in detail.skill" :key="i" class="mpp-tag">{{ s }}</span>
                                    <span v-if="!detail.skill.length" class="mpp-empty-line">Belum ada skill.</span>
                                </div>
                            </div>

                            <!-- Benefit -->
                            <div class="wca-seccard">
                                <div class="wca-seccard__top">
                                    <span class="wca-seccard__ico" style="background:rgba(16,185,129,.12);color:#059669"><i class="bi bi-gift"></i></span>
                                    <strong>Benefit</strong>
                                </div>
                                <div class="mpp-benefits">
                                    <div v-for="(b, i) in detail.benefit" :key="i" class="mpp-benefit"><i class="bi bi-check-circle-fill"></i><span>{{ b }}</span></div>
                                    <span v-if="!detail.benefit.length" class="mpp-empty-line">Belum ada benefit.</span>
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
import { ref, watch, onBeforeUnmount, nextTick } from 'vue';
import axios from 'axios';
import { formatTanggal, namaLengkap, statusBadge, flagBadge } from './mppHelpers';

const props = defineProps({ no: { type: String, default: null } });
const emit = defineEmits(['close']);

const detail = ref(null);
const loading = ref(false);
const error = ref(false);
const panel = ref(null);
const closeBtn = ref(null);

async function load() {
    if (!props.no) return;
    loading.value = true;
    error.value = false;
    try {
        const res = await axios.get(`/api/v1/monitoring-mpp/${encodeURIComponent(props.no)}`, { headers: { Accept: 'application/json' } });
        detail.value = res.data.result || null;
        if (!detail.value) error.value = true;
    } catch (e) {
        error.value = true;
    } finally {
        loading.value = false;
    }
}

function onKeydown(e) {
    if (e.key === 'Escape') emit('close');
}

watch(
    () => props.no,
    async (val) => {
        if (val) {
            detail.value = null;
            load();
            document.addEventListener('keydown', onKeydown);
            document.body.style.overflow = 'hidden';
            await nextTick();
            closeBtn.value?.focus();
        } else {
            document.removeEventListener('keydown', onKeydown);
            document.body.style.overflow = '';
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
/* Panel lebih lebar dari default (.wca-drawer = 460px) sesuai brief (~480–560px). */
.wca-drawer--wide {
    width: min(540px, 100%);
}
.wca-drawer__head p {
    display: inline-flex;
    align-items: center;
    gap: 0.1rem;
    font-family: 'JetBrains Mono', monospace;
}

/* Transisi slide + fade overlay */
.mpp-drawer-enter-active,
.mpp-drawer-leave-active {
    transition: opacity 0.28s ease;
}
.mpp-drawer-enter-active .wca-drawer,
.mpp-drawer-leave-active .wca-drawer {
    transition: transform 0.3s cubic-bezier(0.22, 1, 0.36, 1);
}
.mpp-drawer-enter-from,
.mpp-drawer-leave-to {
    opacity: 0;
}
.mpp-drawer-enter-from .wca-drawer,
.mpp-drawer-leave-to .wca-drawer {
    transform: translateX(100%);
}

.mpp-desc {
    margin: 0;
    font-size: 0.83rem;
    line-height: 1.6;
    color: var(--slate);
}

/* Checklist tanggung jawab */
.mpp-checklist,
.mpp-reqlist {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}
.mpp-checklist li,
.mpp-reqlist li {
    display: flex;
    gap: 0.5rem;
    font-size: 0.82rem;
    line-height: 1.5;
    color: var(--slate);
}
.mpp-checklist i {
    color: #10b981;
    margin-top: 0.15rem;
    flex: none;
}
.mpp-reqlist i {
    color: #94a3b8;
    font-size: 1.1rem;
    line-height: 1;
    flex: none;
}

/* Skill tags — ungu-biru soft */
.mpp-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 0.4rem;
}
.mpp-tag {
    padding: 0.3rem 0.7rem;
    border-radius: 999px;
    font-size: 0.75rem;
    font-weight: 800;
    color: #4338ca;
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.12), rgba(139, 92, 246, 0.14));
    border: 1px solid rgba(99, 102, 241, 0.18);
}

/* Benefit — grid 2 kolom + ikon check hijau */
.mpp-benefits {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.5rem;
}
.mpp-benefit {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    font-size: 0.8rem;
    font-weight: 700;
    color: var(--slate);
}
.mpp-benefit i {
    color: #10b981;
    flex: none;
}
.mpp-empty-line {
    font-size: 0.8rem;
    color: var(--muted);
    font-style: italic;
}

/* Skeleton */
.mpp-sk--grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.6rem;
}
.mpp-sk__box {
    height: 3rem;
    border-radius: 0.6rem;
}
.mpp-sk__card {
    height: 5.5rem;
    border-radius: 0.85rem;
}
.mpp-sk__box,
.mpp-sk__card {
    background: linear-gradient(90deg, #f1f5f9 25%, #e9eef5 37%, #f1f5f9 63%);
    background-size: 400% 100%;
    animation: mppShimmer 1.3s ease infinite;
}
@keyframes mppShimmer {
    0% {
        background-position: 100% 0;
    }
    100% {
        background-position: -100% 0;
    }
}

@media (max-width: 640px) {
    .mpp-benefits {
        grid-template-columns: 1fr;
    }
}
</style>
