<!-- WEB CAREER — Monitoring MPP: kartu ringkas (dipakai di Card Grid). -->
<template>
    <article class="mpp-card" role="button" tabindex="0" @click="$emit('open', mpp.no_transaksi)" @keydown.enter="$emit('open', mpp.no_transaksi)" @keydown.space.prevent="$emit('open', mpp.no_transaksi)">
        <!-- Header: jabatan + status -->
        <div class="mpp-card__head">
            <h3 class="mpp-card__title">{{ titleCase(mpp.jabatan) }}</h3>
            <span class="wca-badge" :class="statusBadge(mpp.status)">{{ mpp.status }}</span>
        </div>

        <!-- Divisi / Sub Divisi -->
        <div class="mpp-card__badges">
            <span class="wca-badge wca-b--indigo"><i class="bi bi-diagram-3"></i> {{ titleCase(mpp.divisi) }}</span>
            <span v-if="mpp.sub_divisi" class="wca-badge wca-b--sky">{{ titleCase(mpp.sub_divisi) }}</span>
        </div>

        <div class="mpp-card__no"><i class="bi bi-hash"></i>{{ mpp.no_transaksi }}</div>

        <!-- Meta: kuota, periode, level -->
        <div class="mpp-card__meta">
            <span title="Jumlah rekrutmen"><i class="bi bi-people-fill"></i> {{ mpp.jumlah_rekruitmen }} orang</span>
            <span title="Tanggal periode"><i class="bi bi-calendar3"></i> {{ formatTanggal(mpp.tanggal_periode) }}</span>
            <span title="Level"><i class="bi bi-bar-chart-steps"></i> {{ titleCase(mpp.level) }}</span>
        </div>

        <!-- Footer: penanggung jawab + flag selesai -->
        <div class="mpp-card__foot">
            <div class="mpp-card__pj">
                <span class="wca-avatar wca-avatar--sm">{{ initials(mpp.penanggung_jawab) }}</span>
                <span class="mpp-card__pj-name">{{ namaLengkap(mpp.penanggung_jawab) }}</span>
            </div>
            <span class="mpp-flag" :class="{ 'is-done': isSelesai(mpp.flag_selesai) }">
                <span class="mpp-flag__dot"></span>{{ mpp.flag_selesai }}
            </span>
        </div>
    </article>
</template>

<script setup>
import { formatTanggal, namaLengkap, initials, statusBadge, isSelesai, titleCase } from './mppHelpers';

defineProps({ mpp: { type: Object, required: true } });
defineEmits(['open']);
</script>

<style scoped>
.mpp-card {
    display: flex;
    flex-direction: column;
    gap: 0.7rem;
    background: #fff;
    border: 1px solid var(--line);
    border-radius: 1.1rem;
    padding: 1.1rem;
    cursor: pointer;
    transition: transform 0.18s ease, box-shadow 0.18s ease, border-color 0.18s ease;
    box-shadow: 0 6px 18px rgba(15, 23, 42, 0.04);
}
.mpp-card:hover,
.mpp-card:focus-visible {
    transform: translateY(-4px);
    box-shadow: 0 14px 30px rgba(79, 70, 229, 0.14);
    border-color: #c7d2fe;
    outline: none;
}
.mpp-card__head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 0.6rem;
}
.mpp-card__title {
    margin: 0;
    font-size: 1rem;
    font-weight: 900;
    line-height: 1.25;
    color: var(--ink);
}
.mpp-card__badges {
    display: flex;
    flex-wrap: wrap;
    gap: 0.35rem;
}
.mpp-card__no {
    display: inline-flex;
    align-items: center;
    gap: 0.15rem;
    font-size: 0.76rem;
    font-weight: 800;
    color: var(--muted);
    font-family: 'JetBrains Mono', monospace;
}
.mpp-card__meta {
    display: flex;
    flex-wrap: wrap;
    gap: 0.35rem 0.9rem;
    padding-top: 0.15rem;
    border-top: 1px dashed var(--line);
    margin-top: auto;
}
.mpp-card__meta span {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.78rem;
    font-weight: 700;
    color: var(--slate);
}
.mpp-card__meta i {
    color: var(--primary);
}
.mpp-card__foot {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.6rem;
    padding-top: 0.7rem;
    border-top: 1px solid var(--line);
}
.mpp-card__pj {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    min-width: 0;
}
.mpp-card__pj-name {
    font-size: 0.78rem;
    font-weight: 800;
    color: var(--slate);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.mpp-flag {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.72rem;
    font-weight: 800;
    color: #94a3b8;
    white-space: nowrap;
    flex: none;
}
.mpp-flag__dot {
    width: 0.55rem;
    height: 0.55rem;
    border-radius: 50%;
    background: #cbd5e1;
}
.mpp-flag.is-done {
    color: #15803d;
}
.mpp-flag.is-done .mpp-flag__dot {
    background: #22c55e;
    box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.18);
}
</style>
