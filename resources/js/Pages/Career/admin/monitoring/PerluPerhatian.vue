<!--
  Panel "PERLU PERHATIAN" — sinyal atensi untuk atasan, urut aging terlama:
   - SIAP_DIPUTUS : semua hasil terkumpul, keputusan menggantung
   - MENUNGGU_TES : tes pihak ke-3 belum ada hasil melewati ambang macet
   - MACET        : tahap manual berjalan melebihi ambang macet
-->
<template>
    <div v-if="items.length" class="wca-card wcm-warncard">
        <button type="button" class="wcm-warnhead" @click="open = !open">
            <span class="wcm-warnhead__ttl"><i class="bi bi-exclamation-triangle-fill"></i> Perlu Perhatian
                <span class="wcm-warnhead__n">{{ items.length }}</span></span>
            <span class="wcm-warnhead__hint">Macet &gt; {{ meta.macetHari }} hari · sorot merah &gt; {{ meta.sorotHari }} hari
                <i class="bi" :class="open ? 'bi-chevron-up' : 'bi-chevron-down'"></i></span>
        </button>
        <div v-show="open" class="wcm-warnbody">
            <div v-for="g in grup" :key="g.jenis" class="wcm-warngrp">
                <div class="wcm-warngrp__ttl"><i class="bi" :class="g.icon"></i> {{ g.label }} ({{ g.rows.length }})</div>
                <div class="wcm-warnrows">
                    <button v-for="r in g.rows" :key="r.lamaranId + r.tahapLabel" type="button" class="wcm-warnrow"
                        :class="{ 'is-red': r.agingHari > meta.sorotHari }"
                        :title="`Buka papan ${r.programNama} & perjalanan ${r.nama}`"
                        @click="$emit('open', r)">
                        <span class="wca-avatar wca-avatar--sm">{{ initials(r.nama) }}</span>
                        <span class="wcm-warnrow__nama">{{ r.nama || '—' }}</span>
                        <span class="wcm-warnrow__info">{{ r.programNama }} · {{ r.tahapLabel }}</span>
                        <span class="wcm-warnrow__aging">{{ formatAging(r.agingHari) }}</span>
                        <i class="bi bi-arrow-right-short wcm-warnrow__go"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
    <!-- Saat tidak ada yang lewat ambang, panel ini tidak menampilkan apa pun:
         halaman monitoring menyajikan fakta, bukan menyatakan keadaan aman. -->
</template>

<script setup>
import { ref, computed } from 'vue'
import { initials } from '../careerAdmin'
import { formatAging, jenisPerhatian } from './monitoringHelpers'

const props = defineProps({
    items: { type: Array, default: () => [] },
    meta: { type: Object, default: () => ({ macetHari: 7, sorotHari: 2 }) },
})
defineEmits(['open'])

const open = ref(true)

const grup = computed(() => {
    const urutan = ['SIAP_DIPUTUS', 'MACET', 'MENUNGGU_TES']
    return urutan
        .map((jenis) => ({
            jenis,
            ...jenisPerhatian(jenis),
            rows: props.items.filter((i) => i.jenis === jenis),
        }))
        .filter((g) => g.rows.length)
})
</script>

<style scoped>
.wcm-warncard {
    margin-bottom: 20px;
    border-radius: 20px;
    border: 1px solid rgba(245, 158, 11, 0.3);
    background: linear-gradient(135deg, #fffdf5 0%, #fef3c7 100%);
    box-shadow: 0 14px 36px rgba(245, 158, 11, 0.08);
    overflow: hidden;
}
.wcm-warnhead {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    width: 100%;
    border: 0;
    background: transparent;
    padding: 16px 20px;
    cursor: pointer;
}
.wcm-warnhead__ttl {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    font-size: 0.88rem;
    font-weight: 800;
    color: #92400e;
}
.wcm-warnhead__ttl .bi { color: #f59e0b; font-size: 1.05rem; }
.wcm-warnhead__n {
    background: #f59e0b;
    color: #ffffff;
    border-radius: 99px;
    padding: 2px 10px;
    font-size: 0.72rem;
    font-weight: 800;
}
.wcm-warnhead__hint {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-size: 0.76rem;
    color: #b45309;
    font-weight: 600;
}
.wcm-warnbody {
    padding: 0 20px 18px;
    display: flex;
    flex-direction: column;
    gap: 14px;
}
.wcm-warngrp__ttl {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.73rem;
    font-weight: 800;
    color: #78350f;
    margin-bottom: 8px;
    text-transform: uppercase;
    letter-spacing: 0.06em;
}
.wcm-warnrows { display: flex; flex-direction: column; gap: 6px; }
.wcm-warnrow {
    display: flex;
    align-items: center;
    gap: 11px;
    width: 100%;
    text-align: left;
    padding: 9px 13px;
    border-radius: 14px;
    background: rgba(255, 255, 255, 0.85);
    border: 1px solid rgba(245, 158, 11, 0.25);
    font-size: 0.82rem;
    cursor: pointer;
    transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
}
.wcm-warnrow:hover {
    transform: translateX(4px);
    border-color: rgba(245, 158, 11, 0.5);
    box-shadow: 0 4px 14px rgba(146, 102, 10, 0.12);
}
.wcm-warnrow__go { color: #d97706; font-size: 1.1rem; }
.wcm-warnrow.is-red {
    border-color: rgba(244, 63, 94, 0.35);
    background: #fff5f5;
}
.wcm-warnrow.is-red .wcm-warnrow__aging { color: #e11d48; }
.wcm-warnrow__nama { font-weight: 800; color: #0f172a; white-space: nowrap; }
.wcm-warnrow__info { color: #64748b; flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-weight: 600; }
.wcm-warnrow__aging { font-weight: 800; color: #d97706; white-space: nowrap; }
@media (max-width: 640px) {
    .wcm-warnhead__hint { display: none; }
    .wcm-warnrow__info { display: none; }
}
</style>
