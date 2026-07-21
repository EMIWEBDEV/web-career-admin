<!-- WEB CAREER — Modal reusable (header gradient gelap + ikon emas, responsive) -->
<template>
    <teleport to="body">
        <transition name="wca-modal">
            <div v-if="show" class="wca-modal-mask wca" @click.self="$emit('close')">
                <div class="wca-modal" :class="{ 'wca-modal--lg': lg }" role="dialog" aria-modal="true">
                    <div class="wca-modal__head">
                        <span class="wca-modal__icon"><i class="bi" :class="icon"></i></span>
                        <div class="wca-modal__titles">
                            <h3>{{ title }}</h3>
                            <p v-if="subtitle">{{ subtitle }}</p>
                        </div>
                        <button class="wca-modal__close" type="button" aria-label="Tutup" @click="$emit('close')">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>

                    <div class="wca-modal__body"><slot /></div>

                    <div class="wca-modal__foot">
                        <slot name="footer">
                            <button class="wca-btn wca-btn--ghost" type="button" @click="$emit('close')">
                                <i class="bi bi-x-circle"></i> {{ cancelLabel }}
                            </button>
                            <button class="wca-btn wca-btn--dark" type="button" @click="$emit('save')">
                                <i class="bi bi-plus-circle-fill"></i> {{ saveLabel }}
                            </button>
                        </slot>
                    </div>
                </div>
            </div>
        </transition>
    </teleport>
</template>

<script setup>
defineProps({
    show: { type: Boolean, default: false },
    title: { type: String, default: '' },
    subtitle: { type: String, default: '' },
    icon: { type: String, default: 'bi-window-stack' },
    lg: { type: Boolean, default: false },
    saveLabel: { type: String, default: 'Simpan Data' },
    cancelLabel: { type: String, default: 'Batal' },
});
defineEmits(['close', 'save']);
</script>
