<template>
  <div class="fb-cards">
    <button
      v-for="(opt, i) in (pertanyaan.Opsi || [])"
      :key="i"
      :class="['fb-cards__pill', { 'fb-cards__pill--active': modelValue === opt }]"
      @click="$emit('update:modelValue', opt)"
      type="button"
    >{{ opt }}</button>
  </div>
</template>
<script setup>
defineProps({ pertanyaan: Object, modelValue: String })
defineEmits(['update:modelValue'])
</script>
<style scoped>
.fb-cards { display: flex; flex-wrap: wrap; gap: 8px; }
.fb-cards__pill { padding: 10px 18px; border: 1.5px solid #e2e8f0; border-radius: 10px; background: #fff; font-size: 14px; cursor: pointer; color: #475569; transition: all 0.15s; }
.fb-cards__pill--active { border-color: #6366f1; background: rgba(99,102,241,0.06); color: #6366f1; font-weight: 600; }
.fb-cards__pill:hover { border-color: #a5b4fc; }
</style>
