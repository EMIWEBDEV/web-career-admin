<!-- WEB CAREER — Modal reusable, kulit desain "Modal Jadwal Kegiatan" (Claude
     Design): header gradien lavender + tile ikon ungu + tombol tutup berputar,
     body bergradasi lembut, footer catatan kiri + tombol Batal/Simpan gradien.
     Struktur slot & emit TIDAK berubah — semua halaman pemakai tetap kompatibel. -->
<template>
    <teleport to="body">
        <transition name="wca-modal">
            <div v-if="show" class="wca-modal-mask wca" @click.self="$emit('close')">
                <div class="wca-modal" :class="{ 'wca-modal--lg': lg, 'wca-modal--xl': xl }" role="dialog" aria-modal="true">
                    <div class="wca-modal__head">
                        <div class="wca-modal__headglow"></div>
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
                        <div v-if="footNote" class="wca-modal__footnote">
                            <i class="bi bi-shield-check"></i>
                            <span>{{ footNote }}</span>
                        </div>
                        <div class="wca-modal__footbtns">
                            <slot name="footer">
                                <button class="wca-btn wca-btn--ghost" type="button" @click="$emit('close')">
                                    <i class="bi bi-x-lg"></i> {{ cancelLabel }}
                                </button>
                                <button class="wca-btn wca-btn--dark" type="button" @click="$emit('save')">
                                    <i class="bi bi-save"></i> {{ saveLabel }}
                                </button>
                            </slot>
                        </div>
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
    xl: { type: Boolean, default: false },
    saveLabel: { type: String, default: 'Simpan Data' },
    cancelLabel: { type: String, default: 'Batal' },
    footNote: { type: String, default: 'Periksa kembali data sebelum disimpan.' },
});
defineEmits(['close', 'save']);
</script>
