<template>
  <div class="fb-nps">
    <div class="fb-nps__buttons">
      <button v-for="s in 10" :key="s" :class="['fb-nps__btn', { 'fb-nps__btn--active': modelValue === s }]"
              :style="{ background: npsColor(s) }" @click="$emit('update:modelValue', s)" type="button">{{ s }}</button>
    </div>
    <div class="fb-nps__labels">
      <span>Tidak mungkin</span>
      <span>Sangat mungkin</span>
    </div>
  </div>
</template>
<script setup>
defineProps({ pertanyaan: Object, modelValue: Number })
defineEmits(['update:modelValue'])
function npsColor(s) {
    if (s <= 2) return 'rgba(239,68,68,0.85)'
    if (s <= 4) return 'rgba(239,68,68,0.55)'
    if (s <= 6) return 'rgba(245,158,11,0.55)'
    if (s <= 8) return 'rgba(34,197,94,0.55)'
    return 'rgba(34,197,94,0.85)'
}
</script>
<style scoped>
.fb-nps__buttons { display: flex; gap: 6px; flex-wrap: wrap; }
.fb-nps__btn { width: 36px; height: 36px; border: none; border-radius: 8px; color: #fff; font-weight: 700; font-size: 14px; cursor: pointer; opacity: 0.6; transition: opacity 0.15s, transform 0.15s; }
.fb-nps__btn--active { opacity: 1; transform: scale(1.1); box-shadow: 0 2px 8px rgba(0,0,0,0.2); }
.fb-nps__labels { display: flex; justify-content: space-between; margin-top: 8px; font-size: 11px; color: #94a3b8; }
</style>
