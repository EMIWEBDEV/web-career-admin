<!-- WEB CAREER — Pemilih ikon: SELURUH Bootstrap Icons dari /api/v1/karir/options/icons (bukan hardcode). -->
<!-- Tampil NAMA ramah (tanpa "bi-") + preview; el-select-v2 (virtualized) agar ribuan ikon tetap ringan. -->
<template>
    <el-select-v2
        :model-value="modelValue"
        :options="options"
        filterable
        clearable
        :loading="loading"
        loading-text="Memuat ikon…"
        :placeholder="placeholder"
        style="width: 100%"
        @update:model-value="$emit('update:modelValue', $event)"
    >
        <template #default="{ item }">
            <span class="iconpick__opt"><i class="bi" :class="item.value"></i> <span>{{ item.label }}</span></span>
        </template>
    </el-select-v2>
</template>

<script>
import axios from 'axios';

export default {
    props: {
        modelValue: { type: String, default: '' },
        placeholder: { type: String, default: 'Cari & pilih ikon…' },
    },
    emits: ['update:modelValue'],
    data() {
        return { options: [], loading: false };
    },
    mounted() {
        this.load();
    },
    methods: {
        // "bi-camera-video" -> "Camera Video" · "bi-whatsapp" -> "Whatsapp"
        pretty(v) {
            return String(v).replace(/^bi-/, '').split('-').map((w) => w.charAt(0).toUpperCase() + w.slice(1)).join(' ');
        },
        async load() {
            this.loading = true;
            try {
                const res = await axios.get('/api/v1/karir/options/icons', { headers: { Accept: 'application/json' } });
                this.options = (res.data.result || []).map((v) => ({ value: v, label: this.pretty(v) }));
            } catch (e) {
                this.options = [];
            } finally {
                this.loading = false;
            }
        },
    },
};
</script>

<style scoped>
.iconpick__opt { display: inline-flex; align-items: center; gap: 10px; }
.iconpick__opt i { font-size: 16px; width: 18px; text-align: center; color: #4f46e5; }
</style>
