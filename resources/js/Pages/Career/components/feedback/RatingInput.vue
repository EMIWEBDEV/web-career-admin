<template>
  <div class="fb-rating">
    <button
      v-for="s in (pertanyaan?.Skala_Max || 5)"
      :key="s"
      class="fb-rating__star"
      :class="{ 'fb-rating__star--active': (hover || modelValue) >= s, 'fb-rating__star--has-val': modelValue }"
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
.fb-rating { display: flex; gap: 8px; justify-content: center; }
.fb-rating__star {
    background: none; border: none; font-size: 40px; cursor: pointer; padding: 0;
    color: #d1d5db; transition: all .2s cubic-bezier(.4,0,.2,1); line-height: 1;
    outline: none; -webkit-tap-highlight-color: transparent; user-select: none;
}
.fb-rating__star:hover { transform: scale(1.2); color: #fbbf24; }
.fb-rating__star--active { color: #f59e0b; filter: drop-shadow(0 0 4px rgba(245,158,11,.4)); }
.fb-rating__star--has-val.fb-rating__star--active { filter: drop-shadow(0 0 6px rgba(245,158,11,.5)); }
</style>
