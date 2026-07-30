<!-- ══════════════════════════════════════════════════════════
     WEB CAREER — Baris aksi di dalam panel jawaban FAQ.
     Dua hal: menyalin tautan langsung ke pertanyaan ini, dan menilai
     apakah jawabannya membantu (bahan HR memperbaiki jawaban yang
     sering dibaca tapi dinilai tidak membantu).
     Dipakai lewat slot #aksi di FaqAccordion.
     ══════════════════════════════════════════════════════════ -->
<template>
    <div class="fa">
        <div class="fa-nilai">
            <template v-if="status === 'selesai'">
                <span class="fa-terima"><i class="bi bi-check-circle-fill"></i> Terima kasih atas penilaian Anda</span>
            </template>
            <template v-else-if="status === 'gagal'">
                <span class="fa-gagal"><i class="bi bi-exclamation-triangle-fill"></i> Penilaian gagal dikirim</span>
            </template>
            <template v-else>
                <span class="fa-tanya">Jawaban ini membantu?</span>
                <button
                    type="button"
                    class="fa-btn"
                    :disabled="status === 'mengirim'"
                    @click="$emit('vote', { id: item.id, membantu: true })"
                >
                    <i class="bi bi-hand-thumbs-up"></i> Ya
                </button>
                <button
                    type="button"
                    class="fa-btn"
                    :disabled="status === 'mengirim'"
                    @click="$emit('vote', { id: item.id, membantu: false })"
                >
                    <i class="bi bi-hand-thumbs-down"></i> Tidak
                </button>
            </template>
        </div>

        <button type="button" class="fa-salin" :class="{ 'is-ok': tersalin }" @click="$emit('salin', item.slug)">
            <i class="bi" :class="tersalin ? 'bi-check2' : 'bi-link-45deg'"></i>
            {{ tersalin ? 'Tautan disalin' : 'Salin tautan' }}
        </button>
    </div>
</template>

<script setup>
defineProps({
    item: { type: Object, required: true },
    // undefined | 'mengirim' | 'selesai' | 'gagal'
    status: { type: String, default: '' },
    tersalin: { type: Boolean, default: false },
});

defineEmits(['vote', 'salin']);
</script>

<style scoped>
.fa {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    margin-top: 1.1rem;
    padding-top: 0.9rem;
    border-top: 1px dashed rgba(226, 232, 240, 0.9);
}
.fa-nilai {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.5rem;
}
.fa-tanya {
    color: #64748b;
    font-size: 0.85rem;
    font-weight: 600;
}
.fa-btn,
.fa-salin {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.4rem 0.85rem;
    border-radius: 999px;
    border: 1px solid rgba(226, 232, 240, 0.95);
    background: #ffffff;
    color: #475569;
    font-size: 0.82rem;
    font-weight: 700;
    cursor: pointer;
    transition: border-color 0.2s ease, color 0.2s ease, transform 0.2s ease;
}
.fa-btn:hover:not(:disabled),
.fa-salin:hover {
    border-color: rgba(139, 92, 246, 0.55);
    color: #4f46e5;
    transform: translateY(-1px);
}
.fa-btn:disabled {
    opacity: 0.55;
    cursor: default;
}
.fa-salin.is-ok {
    border-color: rgba(22, 163, 74, 0.45);
    color: #16a34a;
}
.fa-terima {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    color: #16a34a;
    font-size: 0.85rem;
    font-weight: 700;
}
.fa-gagal {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    color: #d97706;
    font-size: 0.85rem;
    font-weight: 700;
}
</style>
