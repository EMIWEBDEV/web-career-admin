<!-- WEB CAREER — Modal konfirmasi reusable (hapus / aksi berbahaya). Elegan, tombol danger. -->
<template>
    <AdminModal :show="show" :title="title" :subtitle="subtitle" :icon="icon" @close="$emit('cancel')">
        <div class="cfm">
            <span class="cfm__ico" :class="danger ? 'is-danger' : 'is-warn'">
                <i class="bi" :class="danger ? 'bi-trash3' : 'bi-exclamation-triangle'"></i>
            </span>
            <p class="cfm__txt"><slot>Yakin ingin melanjutkan tindakan ini?</slot></p>
            <p v-if="note" class="cfm__note">{{ note }}</p>
        </div>
        <template #footer>
            <button class="wca-btn wca-btn--ghost" type="button" @click="$emit('cancel')"><i class="bi bi-x-circle"></i> {{ cancelLabel }}</button>
            <button class="wca-btn" :class="danger ? 'wca-btn--danger' : 'wca-btn--dark'" type="button" :disabled="busy || confirmDisabled" @click="$emit('confirm')">
                <span v-if="busy" class="wca-spin" aria-hidden="true"></span>
                <i v-else class="bi" :class="danger ? 'bi-trash' : 'bi-check-lg'"></i>
                {{ busy ? busyLabel : confirmLabel }}
            </button>
        </template>
    </AdminModal>
</template>

<script>
import AdminModal from './AdminModal.vue';

export default {
    components: { AdminModal },
    props: {
        show: { type: Boolean, default: false },
        title: { type: String, default: 'Konfirmasi' },
        subtitle: { type: String, default: 'Tindakan ini tidak dapat dibatalkan' },
        icon: { type: String, default: 'bi-exclamation-octagon' },
        note: { type: String, default: '' },
        danger: { type: Boolean, default: true },
        busy: { type: Boolean, default: false },
        // Kunci tombol konfirmasi selama syarat di dalam modal belum terpenuhi
        // (mis. centang persetujuan sebelum menggugurkan kandidat).
        confirmDisabled: { type: Boolean, default: false },
        confirmLabel: { type: String, default: 'Ya, Lanjutkan' },
        cancelLabel: { type: String, default: 'Batal' },
        busyLabel: { type: String, default: 'Memproses…' },
    },
    emits: ['confirm', 'cancel'],
};
</script>

<style scoped>
.cfm { text-align: center; padding: 8px 6px 2px; }
.cfm__ico {
    display: inline-grid;
    place-items: center;
    width: 66px;
    height: 66px;
    border-radius: 50%;
    font-size: 29px;
    margin-bottom: 16px;
}
.cfm__ico.is-danger { background: rgba(220, 38, 38, 0.1); color: #dc2626; }
.cfm__ico.is-warn { background: rgba(217, 119, 6, 0.12); color: #d97706; }
.cfm__txt { margin: 0 0 8px; font-size: 15px; color: #0f1235; line-height: 1.55; }
.cfm__txt :deep(strong) { color: #0b1033; }
.cfm__note { margin: 0; font-size: 12.5px; color: #dc2626; }
</style>
