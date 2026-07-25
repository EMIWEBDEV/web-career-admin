<template>
  <div class="fb-cards">
    <button
      v-for="(opt, i) in (pertanyaan.Opsi || [])"
      :key="i"
      :class="['fb-cards__pill fb-cards__pill--check', { 'fb-cards__pill--active': (modelValue || []).includes(opt) }]"
      @click="toggle(opt)"
      type="button"
    >
      <span v-if="(modelValue || []).includes(opt)" class="fb-cards__check">✓</span>
      {{ opt }}
    </button>
  </div>
</template>
<script setup>
const props = defineProps({ pertanyaan: Object, modelValue: Array })
const emit = defineEmits(['update:modelValue'])
function toggle(opt) {
    const current = props.modelValue || []
    const next = current.includes(opt) ? current.filter(o => o !== opt) : [...current, opt]
    emit('update:modelValue', next)
}
</script>
<style scoped>
.fb-cards { display: flex; flex-wrap: wrap; gap: 8px; }
.fb-cards__pill { padding: 10px 18px; border: 1.5px solid #e2e8f0; border-radius: 10px; background: #fff; font-size: 14px; cursor: pointer; color: #475569; transition: all 0.15s; display: flex; align-items: center; gap: 6px; }
.fb-cards__pill--active { border-color: #6366f1; background: rgba(99,102,241,0.06); color: #6366f1; font-weight: 600; }
.fb-cards__check { font-size: 12px; color: #6366f1; font-weight: 700; }
</style>
