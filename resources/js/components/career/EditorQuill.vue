<!-- ══════════════════════════════════════════════════════════
     WEB CAREER — Editor teks berformat (Quill), pembungkus v-model.
     Toolbar SENGAJA dibatasi agar HTML yang dihasilkan tetap berada di
     dalam daftar-izin App\Support\Career\HtmlBersih. Menambah tombol di
     sini tanpa menambah tag di sana = format itu akan dibuang saat simpan.
     ══════════════════════════════════════════════════════════ -->
<template>
    <div class="eq" :class="{ 'is-disabled': disabled }">
        <div ref="wadah"></div>
        <small v-if="hint" class="eq-hint"><i class="bi bi-info-circle"></i> {{ hint }}</small>
    </div>
</template>

<script>
import Quill from 'quill';
import 'quill/dist/quill.snow.css';

export default {
    props: {
        modelValue: { type: String, default: '' },
        placeholder: { type: String, default: 'Tulis penjelasan lengkap di sini…' },
        disabled: { type: Boolean, default: false },
        hint: { type: String, default: '' },
    },
    emits: ['update:modelValue'],
    data() {
        return { quill: null, dariDalam: false };
    },
    watch: {
        modelValue(nilai) {
            // Jangan tulis ulang isi editor karena perubahan yang berasal dari
            // editor itu sendiri — kursor akan melompat ke akhir setiap ketikan.
            if (this.dariDalam) {
                this.dariDalam = false;
                return;
            }
            if (this.quill && (nilai || '') !== this.quill.root.innerHTML) {
                this.quill.root.innerHTML = nilai || '';
            }
        },
        disabled(v) {
            this.quill?.enable(!v);
        },
    },
    mounted() {
        this.quill = new Quill(this.$refs.wadah, {
            theme: 'snow',
            placeholder: this.placeholder,
            modules: {
                toolbar: [
                    [{ header: [3, 4, false] }],
                    ['bold', 'italic', 'underline', 'strike'],
                    [{ list: 'ordered' }, { list: 'bullet' }],
                    ['blockquote', 'link'],
                    ['clean'],
                ],
            },
        });

        if (this.modelValue) {
            this.quill.root.innerHTML = this.modelValue;
        }
        if (this.disabled) {
            this.quill.enable(false);
        }

        this.quill.on('text-change', () => {
            const html = this.quill.getText().trim() === '' ? '' : this.quill.root.innerHTML;
            this.dariDalam = true;
            this.$emit('update:modelValue', html);
        });
    },
    beforeUnmount() {
        // Quill menaruh toolbar sebagai SIBLING wadah, di luar jangkauan Vue —
        // tanpa dibuang manual, toolbar tertinggal saat modal dibuka-tutup.
        this.quill?.getModule('toolbar')?.container?.remove();
        this.quill = null;
    },
};
</script>

<style scoped>
.eq :deep(.ql-toolbar) {
    border-radius: 0.7rem 0.7rem 0 0;
    border-color: #dcdfe6;
    background: #f8fafc;
}
.eq :deep(.ql-container) {
    border-radius: 0 0 0.7rem 0.7rem;
    border-color: #dcdfe6;
    font-family: inherit;
    font-size: 0.92rem;
}
.eq :deep(.ql-editor) {
    min-height: 170px;
    line-height: 1.7;
}
.eq :deep(.ql-editor.ql-blank::before) {
    font-style: normal;
    color: #a8abb2;
}
.eq.is-disabled {
    opacity: 0.65;
}
.eq-hint {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    margin-top: 0.4rem;
    color: var(--muted, #64748b);
    font-size: 0.78rem;
    font-weight: 600;
}
</style>
