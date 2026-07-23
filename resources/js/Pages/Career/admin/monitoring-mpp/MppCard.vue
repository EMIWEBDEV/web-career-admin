<!-- Kartu ringkas MPP (Card Grid). Klik/Enter/Space → emit 'open'. -->
<template>
    <article class="mpp-card" role="button" tabindex="0" @click="$emit('open', mpp.no_transaksi)" @keydown.enter="$emit('open', mpp.no_transaksi)" @keydown.space.prevent="$emit('open', mpp.no_transaksi)">
        <!-- Header: jabatan (judul bold) + status badge -->
        <div class="mpp-card__head">
            <h3 class="mpp-card__title">{{ titleCase(mpp.jabatan) }}</h3>
            <span class="wca-badge" :class="statusBadge(mpp.status)">{{ mpp.status }}</span>
        </div>

        <!-- Badge baris 1: Divisi / Sub Divisi -->
        <div class="mpp-card__badges">
            <span class="wca-badge wca-b--indigo"><i class="bi bi-diagram-3"></i> {{ titleCase(mpp.divisi) }}</span>
            <span v-if="mpp.sub_divisi" class="wca-badge wca-b--sky">{{ titleCase(mpp.sub_divisi) }}</span>
        </div>

        <!-- No Transaksi -->
        <div class="mpp-card__no"><i class="bi bi-hash"></i>{{ mpp.no_transaksi }}</div>

        <!-- Badge baris 2: Employment / Workplace / Experience (fase 2) — poppin' colored accents -->
        <div class="mpp-card__tags" v-if="mpp.employment_type || mpp.workplace_type || mpp.experience_level">
            <span v-if="mpp.employment_type" class="mpp-tag mpp-tag--emp">
                <span class="mpp-tag__dot mpp-tag__dot--emp"></span>
                <i class="bi bi-briefcase-fill"></i> {{ mpp.employment_type }}
            </span>
            <span v-if="mpp.workplace_type" class="mpp-tag mpp-tag--wp">
                <span class="mpp-tag__dot mpp-tag__dot--wp"></span>
                <i class="bi bi-geo-alt-fill"></i> {{ mpp.workplace_type }}
            </span>
            <span v-if="mpp.experience_level" class="mpp-tag mpp-tag--exp">
                <span class="mpp-tag__dot mpp-tag__dot--exp"></span>
                <i class="bi bi-stars"></i> {{ mpp.experience_level }}
            </span>
        </div>

        <!-- Meta: kuota, periode, level -->
        <div class="mpp-card__meta">
            <span title="Jumlah rekrutmen"><i class="bi bi-people-fill"></i> {{ mpp.jumlah_rekruitmen }} orang</span>
            <span title="Tanggal periode"><i class="bi bi-calendar3"></i> {{ formatTanggal(mpp.tanggal_periode) }}</span>
            <span title="Level HRIS"><i class="bi bi-bar-chart-steps"></i> {{ titleCase(mpp.level) }}</span>
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
/* ── Card shell ── */
.mpp-card {
    display: flex; flex-direction: column; gap: 0.65rem;
    background: #fff; border: 1px solid var(--line); border-radius: 1.15rem; padding: 1.1rem 1.15rem;
    cursor: pointer;
    transition: transform .2s cubic-bezier(.22,1,.36,1), box-shadow .2s ease, border-color .2s ease;
    box-shadow: 0 6px 20px rgba(15,23,42,.04);
    position: relative; overflow: hidden;
}
.mpp-card::after {
    content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px;
    background: linear-gradient(90deg, #6366f1, #8b5cf6, #6366f1);
    opacity: 0; transition: opacity .25s ease;
}
.mpp-card:hover::after,
.mpp-card:focus-visible::after { opacity: 1; }
.mpp-card:hover,
.mpp-card:focus-visible {
    transform: translateY(-5px);
    box-shadow: 0 18px 36px rgba(79,70,229,.16), 0 6px 12px rgba(15,23,42,.06);
    border-color: #c7d2fe;
    outline: none;
}

/* ── Header ── */
.mpp-card__head { display: flex; align-items: flex-start; justify-content: space-between; gap: .55rem; }
.mpp-card__title { margin: 0; font-size: 1rem; font-weight: 900; line-height: 1.25; color: var(--ink); }

/* ── Badges baris 1 (divisi/sub) ── */
.mpp-card__badges { display: flex; flex-wrap: wrap; gap: .35rem; }

/* ── No transaksi ── */
.mpp-card__no {
    display: inline-flex; align-items: center; gap: .15rem;
    font-size: .76rem; font-weight: 800; color: var(--muted);
    font-family: 'JetBrains Mono', monospace;
}

/* ── Meta (kuota / periode / level) ── */
.mpp-card__meta {
    display: flex; flex-wrap: wrap; gap: .35rem .9rem;
    padding-top: .4rem; border-top: 1px dashed var(--line); margin-top: auto;
}
.mpp-card__meta span { display: inline-flex; align-items: center; gap: .35rem; font-size: .78rem; font-weight: 700; color: var(--slate); }
.mpp-card__meta i  { color: var(--primary); }

/* ── Tags baris 2: Employment / Workplace / Experience (fase 2) ── */
.mpp-card__tags { display: flex; flex-wrap: wrap; gap: .4rem; }

.mpp-tag {
    display: inline-flex; align-items: center; gap: .4rem;
    font-size: .73rem; font-weight: 800; white-space: nowrap;
    padding: .28rem .65rem; border-radius: .65rem;
    transition: transform .12s ease, box-shadow .12s ease;
}
.mpp-tag:hover { transform: scale(1.04); }

/* tipe kerja (employment) — indigo */
.mpp-tag--emp { background: linear-gradient(135deg, rgba(99,102,241,.12), rgba(139,92,246,.08)); color: #4338ca; border: 1px solid rgba(99,102,241,.18); }
/* lokasi kerja (workplace) — teal */
.mpp-tag--wp  { background: linear-gradient(135deg, rgba(16,185,129,.10), rgba(6,182,212,.06)); color: #0f766e; border: 1px solid rgba(16,185,129,.18); }
/* exp level — amber */
.mpp-tag--exp { background: linear-gradient(135deg, rgba(245,158,11,.10), rgba(251,191,36,.06)); color: #b45309; border: 1px solid rgba(245,158,11,.2); }

.mpp-tag__dot { width: .45rem; height: .45rem; border-radius: 50%; flex: none; }
.mpp-tag__dot--emp { background: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,.2); }
.mpp-tag__dot--wp  { background: #10b981; box-shadow: 0 0 0 3px rgba(16,185,129,.2); }
.mpp-tag__dot--exp { background: #f59e0b; box-shadow: 0 0 0 3px rgba(245,158,11,.2); }

.mpp-tag i { opacity: .85; }

/* ── Footer ── */
.mpp-card__foot { display: flex; align-items: center; justify-content: space-between; gap: .6rem; padding-top: .7rem; border-top: 1px solid var(--line); }
.mpp-card__pj   { display: flex; align-items: center; gap: .45rem; min-width: 0; }
.mpp-card__pj-name { font-size: .78rem; font-weight: 800; color: var(--slate); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

/* ── Flag selesai ── */
.mpp-flag { display: inline-flex; align-items: center; gap: .35rem; font-size: .72rem; font-weight: 800; color: #94a3b8; white-space: nowrap; flex: none; }
.mpp-flag__dot { width: .55rem; height: .55rem; border-radius: 50%; background: #cbd5e1; }
.mpp-flag.is-done { color: #15803d; }
.mpp-flag.is-done .mpp-flag__dot { background: #22c55e; box-shadow: 0 0 0 3px rgba(34,197,94,.18); }
</style>
