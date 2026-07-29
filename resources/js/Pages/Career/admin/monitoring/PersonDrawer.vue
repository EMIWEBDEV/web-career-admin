<!--
  Drawer kanan detail SATU orang, dibuka dari papan Spotlight.
  Papan tetap terlihat di belakang → atasan bisa langsung klik orang lain
  tanpa menutup drawer (isi ikut berganti karena lamaranId reaktif).
-->
<template>
    <teleport to="body">
        <div class="wcm-dw">
            <aside class="wcm-dw__panel" role="dialog" aria-label="Detail pelamar">
                <header class="wcm-dw__head">
                    <span class="wcm-dw__ttl"><i class="bi bi-person-badge"></i> Detail Pelamar</span>
                    <button class="wca-iconbtn" title="Tutup (Esc)" @click="$emit('close')"><i class="bi bi-x-lg"></i></button>
                </header>
                <div class="wcm-dw__body">
                    <DetailPerjalanan :lamaran-id="lamaranId" bisa-buka
                        @open-stage="$emit('open-stage', $event)" />
                </div>
            </aside>
        </div>
    </teleport>
</template>

<script setup>
import DetailPerjalanan from './DetailPerjalanan.vue'
import { useLapisEsc } from '../../../../composables/useLapisEsc'

defineProps({ lamaranId: { type: String, required: true } })
const emit = defineEmits(['close', 'open-stage'])

useLapisEsc(() => emit('close'))
</script>

<style scoped>
/* Offcanvas LEVEL HALAMAN: menempel tepi layar, tinggi penuh viewport, dan
   berada di atas modal Spotlight — bukan panel di dalam modal.
   Tanpa backdrop yang menangkap klik, sehingga papan di belakang tetap bisa
   diklik dan atasan berpindah orang tanpa menutup offcanvas lebih dulu. */
.wcm-dw { position: fixed; inset: 0; z-index: 1200; display: flex; justify-content: flex-end; pointer-events: none; }
.wcm-dw__panel { pointer-events: auto; width: min(480px, 100vw); height: 100vh; background: #fff; box-shadow: -24px 0 70px rgba(15, 23, 42, 0.34); display: flex; flex-direction: column; animation: wcmSlide 0.22s cubic-bezier(0.22, 1, 0.36, 1); }
@keyframes wcmSlide { from { transform: translateX(30px); opacity: 0.4; } to { transform: translateX(0); opacity: 1; } }
.wcm-dw__head { display: flex; align-items: center; justify-content: space-between; gap: 10px; padding: 14px 17px; border-bottom: 1px solid #eef0f7; flex: none; }
.wcm-dw__ttl { display: inline-flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 800; color: #374151; }
.wcm-dw__ttl .bi { color: #6366f1; }
.wcm-dw__body { flex: 1; overflow-y: auto; padding: 16px 17px 28px; }
</style>
