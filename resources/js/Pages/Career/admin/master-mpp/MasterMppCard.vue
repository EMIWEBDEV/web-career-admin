<!-- Kartu ringkas MPP (Card Grid). Klik/Enter/Space → emit 'open' (panel detail).
     Ikon aksi cepat (Ubah/Selesai/Batalkan) di footer kartu. -->
<template>
    <article
        class="mmp-card"
        :class="{ 'is-off': mpp.status !== 'AKTIF', 'is-done': mpp.selesai, 'is-mt': mpp.jenisProgram === 'MT' }"
        role="button"
        tabindex="0"
        @click="$emit('open', mpp.noTransaksi)"
        @keydown.enter="$emit('open', mpp.noTransaksi)"
        @keydown.space.prevent="$emit('open', mpp.noTransaksi)"
    >
        <!-- 1. Header: Title & Badges Row -->
        <div class="mmp-card__head">
            <div class="mmp-card__title-row">
                <h3 class="mmp-card__title" :title="mpp.jabatan?.nama || 'Jabatan belum diisi'">
                    {{ mpp.jabatan?.nama || 'Jabatan belum diisi' }}
                </h3>
                <span class="wca-badge" :class="statusBadge(mpp.status)">
                    {{ statusLabel(mpp.status) }}
                </span>
            </div>

            <div class="mmp-card__sub-row">
                <span class="mmp-card__no" :title="'No Transaksi: ' + mpp.noTransaksi">
                    <i class="bi bi-hash"></i>{{ mpp.noTransaksi }}
                </span>
                <span
                    v-if="mpp.jenisProgram"
                    class="mmp-card__prog"
                    :class="{ 'is-mt': mpp.jenisProgram === 'MT' }"
                    :title="'Jenis Program: ' + jenisProgramLabel(mpp.jenisProgram)"
                >
                    <i class="bi" :class="mpp.jenisProgram === 'MT' ? 'bi-mortarboard-fill' : 'bi-person-workspace'"></i>
                    {{ jenisProgramLabel(mpp.jenisProgram) }}
                </span>
                <span class="mmp-status-flag" :class="{ 'is-done': mpp.selesai }">
                    <span class="mmp-status-flag__dot"></span>
                    {{ mpp.selesai ? 'Selesai' : 'Berjalan' }}
                </span>
            </div>
        </div>

        <!-- 2. Body: Division, Quota/Period Bar, Chips -->
        <div class="mmp-card__body">
            <div class="mmp-card__dept" :title="(mpp.divisi?.nama || '—') + (mpp.subDivisi ? ' / ' + mpp.subDivisi.nama : '')">
                <i class="bi bi-diagram-3-fill"></i>
                <span class="mmp-card__div-name">{{ mpp.divisi?.nama || '—' }}</span>
                <span v-if="mpp.subDivisi" class="mmp-card__sub-name">/ {{ mpp.subDivisi.nama }}</span>
            </div>

            <div class="mmp-card__meta-bar">
                <span class="mmp-meta-pill" title="Kuota Rekrutmen Target">
                    <i class="bi bi-people-fill"></i>
                    <strong>{{ mpp.jumlahRekrutmen }}</strong> orang
                </span>
                <span class="mmp-meta-pill" title="Tanggal Periode Target">
                    <i class="bi bi-calendar3"></i>
                    {{ formatTanggal(mpp.tanggalPeriode) }}
                </span>
            </div>

            <div class="mmp-card__tags" v-if="allTags.length">
                <span
                    v-for="t in allTags.slice(0, 2)"
                    :key="t.key"
                    class="mmp-chip"
                    :title="t.title"
                >
                    <i class="bi" :class="t.icon"></i> {{ t.label }}
                </span>

                <!-- Hover Popover untuk sisa tag (+N) -->
                <div v-if="allTags.length > 2" class="mmp-more-wrap" @click.stop>
                    <span class="mmp-chip mmp-chip--more">
                        +{{ allTags.length - 2 }}
                    </span>
                    <div class="mmp-more-popover">
                        <div class="mmp-more-popover__head">Atribut Lainnya ({{ allTags.length - 2 }})</div>
                        <div v-for="t in allTags.slice(2)" :key="t.key" class="mmp-more-popover__item">
                            <i class="bi" :class="t.icon"></i>
                            <span>{{ t.title }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 3. Footer: PIC & Actions -->
        <div class="mmp-card__foot">
            <div class="mmp-card__pj">
                <span class="wca-avatar wca-avatar--sm">{{ initials(mpp.penanggungJawab?.nama) }}</span>
                <span class="mmp-card__pj-name" :title="'Penanggung Jawab: ' + (mpp.penanggungJawab?.nama || '—')">{{ mpp.penanggungJawab?.nama || '—' }}</span>
            </div>

            <div class="mmp-card__actions">
                <button
                    class="wca-iconbtn"
                    title="Ubah Transaksi MPP"
                    @click.stop="$emit('edit', mpp)"
                >
                    <i class="bi bi-pencil"></i>
                </button>
                <button
                    class="wca-iconbtn"
                    :class="{ 'wca-iconbtn--success': !mpp.selesai }"
                    :title="mpp.selesai ? 'Tandai belum selesai' : 'Tandai selesai'"
                    @click.stop="$emit('toggle-selesai', mpp)"
                >
                    <i class="bi" :class="mpp.selesai ? 'bi-arrow-counterclockwise' : 'bi-check2-circle'"></i>
                </button>
                <button
                    v-if="mpp.status === 'AKTIF'"
                    class="wca-iconbtn wca-iconbtn--danger"
                    title="Batalkan transaksi"
                    @click.stop="$emit('batalkan', mpp)"
                >
                    <i class="bi bi-x-circle"></i>
                </button>
                <button
                    v-else
                    class="wca-iconbtn wca-iconbtn--success"
                    title="Aktifkan kembali"
                    @click.stop="$emit('aktifkan', mpp)"
                >
                    <i class="bi bi-arrow-counterclockwise"></i>
                </button>
            </div>
        </div>
    </article>
</template>

<script setup>
import { computed } from 'vue';
import { formatTanggal, initials, statusBadge, statusLabel, jenisProgramLabel } from './masterMppHelpers';

const props = defineProps({ mpp: { type: Object, required: true } });
defineEmits(['open', 'edit', 'toggle-selesai', 'batalkan', 'aktifkan']);

const allTags = computed(() => {
    const list = [];
    if (props.mpp.employmentType?.nama) {
        list.push({ key: 'emp', icon: 'bi-briefcase', label: props.mpp.employmentType.nama, title: 'Tipe Kerja: ' + props.mpp.employmentType.nama });
    }
    if (props.mpp.workplaceType?.nama) {
        list.push({ key: 'wp', icon: 'bi-laptop', label: props.mpp.workplaceType.nama, title: 'Lokasi Kerja: ' + props.mpp.workplaceType.nama });
    }
    if (props.mpp.experienceLevel?.nama) {
        list.push({ key: 'exp', icon: 'bi-stars', label: props.mpp.experienceLevel.nama, title: 'Tingkat Pengalaman: ' + props.mpp.experienceLevel.nama });
    }
    if (props.mpp.level?.nama) {
        list.push({ key: 'lvl', icon: 'bi-bar-chart-steps', label: props.mpp.level.nama, title: 'Level HRIS: ' + props.mpp.level.nama });
    }
    if (props.mpp.lokasi?.nama) {
        list.push({ key: 'loc', icon: 'bi-geo-alt', label: props.mpp.lokasi.nama, title: 'Lokasi Penempatan: ' + props.mpp.lokasi.nama });
    }
    return list;
});
</script>

<style scoped>
.mmp-card {
    display: flex;
    flex-direction: column;
    gap: 0.6rem;
    background: #ffffff;
    border: 1px solid var(--line, #e2e8f0);
    border-top: 2.5px solid transparent;
    border-radius: 0.9rem;
    padding: 1rem 1.1rem;
    cursor: pointer;
    position: relative;
    overflow: visible;
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    box-shadow: 0 2px 8px rgba(15, 23, 42, 0.03);
}

.mmp-card:hover,
.mmp-card:focus-visible {
    transform: translateY(-2px);
    box-shadow: 0 12px 28px rgba(99, 102, 241, 0.12);
    border-color: #c7d2fe;
    border-top-color: #6366f1;
    outline: none;
}

.mmp-card.is-mt:hover,
.mmp-card.is-mt:focus-visible {
    border-color: #ddd6fe;
    border-top-color: #8b5cf6;
    box-shadow: 0 12px 28px rgba(139, 92, 246, 0.12);
}

.mmp-card.is-off {
    opacity: 0.75;
    background: #f8fafc;
}

/* 1. Head */
.mmp-card__head {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
}

.mmp-card__title-row {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 0.5rem;
}

.mmp-card__title {
    margin: 0;
    font-size: 1rem;
    font-weight: 800;
    line-height: 1.3;
    color: #0f172a;
    letter-spacing: -0.01em;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    text-overflow: ellipsis;
    word-break: break-word;
}

.mmp-card__sub-row {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.35rem;
}

.mmp-card__no {
    font-family: 'JetBrains Mono', monospace;
    font-size: 0.72rem;
    font-weight: 700;
    color: #64748b;
    background: #f1f5f9;
    padding: 0.15rem 0.45rem;
    border-radius: 0.35rem;
    border: 1px solid #e2e8f0;
    display: inline-flex;
    align-items: center;
    gap: 0.15rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 140px;
}

.mmp-card__prog {
    font-size: 0.68rem;
    font-weight: 800;
    color: #4338ca;
    background: #eef2ff;
    border: 1px solid #c7d2fe;
    padding: 0.15rem 0.45rem;
    border-radius: 0.35rem;
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 140px;
}

.mmp-card__prog.is-mt {
    color: #6d28d9;
    background: #f5f3ff;
    border-color: #ddd6fe;
}

.mmp-status-flag {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    font-size: 0.7rem;
    font-weight: 800;
    color: #64748b;
    background: #f1f5f9;
    padding: 0.15rem 0.45rem;
    border-radius: 999px;
    margin-left: auto;
}

.mmp-status-flag__dot {
    width: 0.4rem;
    height: 0.4rem;
    border-radius: 50%;
    background: #94a3b8;
}

.mmp-status-flag.is-done {
    color: #15803d;
    background: #dcfce7;
}

.mmp-status-flag.is-done .mmp-status-flag__dot {
    background: #22c55e;
}

/* 2. Body */
.mmp-card__body {
    display: flex;
    flex-direction: column;
    gap: 0.45rem;
}

.mmp-card__dept {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.25rem 0.35rem;
    font-size: 0.78rem;
    font-weight: 700;
    color: #475569;
    min-width: 0;
}

.mmp-card__dept i {
    color: #6366f1;
    flex-shrink: 0;
}

.mmp-card__div-name {
    color: #1e293b;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 160px;
}

.mmp-card__sub-name {
    color: #94a3b8;
    font-weight: 600;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 160px;
}

.mmp-card__meta-bar {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.4rem 0.8rem;
    padding: 0.45rem 0.65rem;
    background: #f8fafc;
    border: 1px solid #f1f5f9;
    border-radius: 0.55rem;
}

.mmp-meta-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    font-size: 0.75rem;
    font-weight: 700;
    color: #334155;
}

.mmp-meta-pill i {
    color: #6366f1;
    font-size: 0.8rem;
}

.mmp-meta-pill strong {
    color: #0f172a;
    font-weight: 900;
}

.mmp-card__tags {
    display: flex;
    flex-wrap: wrap;
    gap: 0.3rem;
}

.mmp-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.7rem;
    font-weight: 700;
    color: #475569;
    background: #f1f5f9;
    border: 1px solid #e2e8f0;
    padding: 0.18rem 0.45rem;
    border-radius: 0.35rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 160px;
}

.mmp-chip i {
    font-size: 0.72rem;
    color: #6366f1;
    flex-shrink: 0;
}

.mmp-chip--more {
    color: #4338ca;
    background: #eef2ff;
    border-color: #c7d2fe;
    font-weight: 800;
}

/* Hover Popover untuk +N Tag */
.mmp-more-wrap {
    position: relative;
    display: inline-flex;
}

.mmp-more-wrap .mmp-chip--more {
    cursor: pointer;
    transition: all 0.15s ease;
}

.mmp-more-wrap:hover .mmp-chip--more {
    background: #6366f1;
    color: #ffffff;
    border-color: #6366f1;
}

.mmp-more-popover {
    position: absolute;
    bottom: calc(100% + 7px);
    left: 50%;
    transform: translateX(-50%) translateY(4px);
    background: #0f172a;
    color: #ffffff;
    border-radius: 0.65rem;
    padding: 0.55rem 0.75rem;
    box-shadow: 0 12px 28px rgba(15, 23, 42, 0.28);
    white-space: nowrap;
    opacity: 0;
    visibility: hidden;
    pointer-events: none;
    transition: opacity 0.18s cubic-bezier(0.16, 1, 0.3, 1), transform 0.18s cubic-bezier(0.16, 1, 0.3, 1), visibility 0.18s ease;
    z-index: 99;
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
}

.mmp-more-popover::after {
    content: '';
    position: absolute;
    top: 100%;
    left: 50%;
    transform: translateX(-50%);
    border-width: 5px;
    border-style: solid;
    border-color: #0f172a transparent transparent transparent;
}

.mmp-more-wrap:hover .mmp-more-popover {
    opacity: 1;
    visibility: visible;
    pointer-events: auto;
    transform: translateX(-50%) translateY(0);
}

.mmp-more-popover__head {
    font-size: 0.64rem;
    font-weight: 800;
    color: #94a3b8;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    padding-bottom: 0.25rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.12);
}

.mmp-more-popover__item {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.74rem;
    font-weight: 700;
    color: #f1f5f9;
}

.mmp-more-popover__item i {
    color: #a5b4fc;
    font-size: 0.8rem;
}

/* 3. Footer */
.mmp-card__foot {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    padding-top: 0.5rem;
    border-top: 1px solid #f1f5f9;
}

.mmp-card__pj {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    min-width: 0;
    flex: 1;
}

.mmp-card__pj-name {
    font-size: 0.76rem;
    font-weight: 800;
    color: #334155;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 150px;
}

.mmp-card__actions {
    display: flex;
    align-items: center;
    gap: 0.3rem;
    flex-shrink: 0;
}

.mmp-card__actions .wca-iconbtn--success:hover:not(:disabled) {
    color: #059669;
    border-color: rgba(16, 185, 129, 0.35);
    background: #ecfdf5;
}
</style>
