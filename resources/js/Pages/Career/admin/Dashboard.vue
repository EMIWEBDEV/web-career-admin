<!-- WEB CAREER — Admin: Dashboard (memakai shell HCIS asli) -->
<template>
    <Head><title>Dashboard - Web Career</title></Head>
    <div class="wca">
        <div class="wca-phead">
            <div>
                <h1>Selamat datang, Admin Rekrutmen 👋</h1>
                <p>Ringkasan rekrutmen & Management Trainee EVO Group hari ini.</p>
            </div>
            <div class="wca-phead__actions">
                <Link href="/karir/program-kegiatan" class="wca-btn wca-btn--ghost"><i class="bi bi-megaphone"></i> Program</Link>
                <Link href="/karir/pelamar" class="wca-btn wca-btn--primary"><i class="bi bi-kanban"></i> Buka Worklist</Link>
            </div>
        </div>

        <div class="wca-stats">
            <div v-for="s in stats" :key="s.label" class="wca-stat">
                <div class="wca-stat__top">
                    <span class="wca-stat__ico"><i class="bi" :class="s.icon"></i></span>
                    <span class="wca-stat__trend" :class="s.dir">{{ s.trend }}</span>
                </div>
                <div class="wca-stat__num">{{ s.value }}</div>
                <div class="wca-stat__label">{{ s.label }}</div>
            </div>
        </div>

        <div class="wca-grid wca-grid--2">
            <div class="wca-card">
                <div class="wca-card__head">
                    <h3><i class="bi bi-filter-circle"></i> Funnel Seleksi</h3>
                    <span class="wca-badge wca-b--slate">Semua kegiatan</span>
                </div>
                <div class="wca-card__body">
                    <div class="wca-funnel">
                        <div v-for="f in funnel" :key="f.label" class="wca-funnel__row">
                            <span class="wca-funnel__label"><i class="bi" :class="f.icon"></i> {{ f.label }}</span>
                            <div class="wca-funnel__track">
                                <div class="wca-funnel__fill" :style="{ width: pct(f.value) + '%' }">{{ pct(f.value) }}%</div>
                            </div>
                            <span class="wca-funnel__val">{{ f.value }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="wca-card">
                <div class="wca-card__head">
                    <h3><i class="bi bi-calendar2-range"></i> Program Aktif</h3>
                    <Link href="/karir/program-kegiatan" class="wca-btn wca-btn--soft wca-btn--sm">Lihat semua</Link>
                </div>
                <div class="wca-card__body--flush">
                    <div class="wca-list">
                        <div v-for="k in kegiatan" :key="k.id" class="wca-listrow">
                            <span class="wca-avatar wca-avatar--sm"><i class="bi" :class="k.jenis === 'MT' ? 'bi-mortarboard' : 'bi-briefcase'"></i></span>
                            <div class="wca-listrow__main">
                                <strong>{{ k.nama }}</strong>
                                <small>{{ k.periode }} · {{ k.pelamar }} pelamar</small>
                            </div>
                            <span class="wca-badge" :class="statusBadge(k.status)">{{ k.status }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="wca-card" style="margin-top: 1.25rem">
            <div class="wca-card__head">
                <h3><i class="bi bi-clock-history"></i> Lamaran Terbaru</h3>
                <Link href="/karir/pelamar" class="wca-btn wca-btn--soft wca-btn--sm">Buka worklist</Link>
            </div>
            <div class="wca-card__body--flush">
                <div class="wca-tablewrap">
                    <table class="wca-table">
                        <thead>
                            <tr><th>Kandidat</th><th>Posisi</th><th>Jenis</th><th>Tahap</th><th>Skor</th><th>Tanggal</th></tr>
                        </thead>
                        <tbody>
                            <tr v-for="a in recent" :key="a.id">
                                <td>
                                    <div class="wca-table__name">
                                        <span class="wca-avatar wca-avatar--sm">{{ initials(a.nama) }}</span>
                                        <div><strong>{{ a.nama }}</strong><small>{{ a.email }}</small></div>
                                    </div>
                                </td>
                                <td>{{ a.posisi }}</td>
                                <td><span class="wca-badge" :class="a.jenis === 'MT' ? 'wca-b--gold' : 'wca-b--sky'">{{ a.jenis }}</span></td>
                                <td><span class="wca-badge wca-b--indigo">{{ a.stageLabel }}</span></td>
                                <td><strong>{{ a.skor ?? '—' }}</strong></td>
                                <td>{{ a.appliedAt }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { initials, statusBadge } from './careerAdmin';

const props = defineProps({
    stats: { type: Array, default: () => [] },
    funnel: { type: Array, default: () => [] },
    recent: { type: Array, default: () => [] },
    kegiatan: { type: Array, default: () => [] },
});

const max = Math.max(...(props.funnel.length ? props.funnel.map((f) => f.value) : [1]));
function pct(v) {
    return Math.max(4, Math.round((v / max) * 100));
}
</script>
