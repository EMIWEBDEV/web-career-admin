<template>
  <div class="fb-rating">
    <button
      v-for="s in 5"
      :key="s"
      class="fb-rating__star"
      :class="{ 'fb-rating__star--active': modelValue >= s }"
      @click="$emit('update:modelValue', s)"
      @mouseenter="hover = s"
      @mouseleave="hover = 0"
      type="button"
    >
      {{ (hover || modelValue) >= s ? '★' : '☆' }}
    </button>
  </div>
</template>
<script setup>
import { ref } from 'vue'
defineProps({ pertanyaan: Object, modelValue: Number })
defineEmits(['update:modelValue'])
const hover = ref(0)
</script>
<style scoped>
.fb-rating { display: flex; gap: 6px; }
.fb-rating__star {
    background: none; border: none; font-size: 32px; cursor: pointer; padding: 0;
    color: #cbd5e1; transition: color 0.15s; line-height: 1;
}
.fb-rating__star--active { color: #f59e0b; }
</style>
