<template>
  <TransitionGroup
    v-if="items.length > 0"
    name="export-toast"
    tag="div"
    class="export-notification-container"
  >
    <div
      v-for="item in items"
      :key="item.Id_Export"
      class="export-toast-card"
      :class="{
        'export-toast--progress': item.Status_Export === 'DIPROSES',
        'export-toast--done': item.Status_Export === 'SELESAI',
        'export-toast--failed': item.Status_Export === 'GAGAL',
      }"
    >
      <!-- Progress item -->
      <template v-if="item.Status_Export === 'DIPROSES'">
        <div class="export-toast__header">
          <span class="export-toast__spinner"></span>
          <span class="export-toast__label">{{ item.Keterangan || 'Mengexport...' }}</span>
        </div>
        <div class="export-toast__progress-bar">
          <div
            class="export-toast__progress-fill"
            :style="{ width: progressPercent(item) + '%' }"
          ></div>
        </div>
        <div class="export-toast__progress-text">
          {{ item.Progress_Chunk || 0 }} / {{ item.Progress_Total || '?' }} chunk
        </div>
      </template>

      <!-- Done item -->
      <template v-else-if="item.Status_Export === 'SELESAI'">
        <div class="export-toast__header">
          <span class="export-toast__icon-done">✅</span>
          <span class="export-toast__label">{{ item.Keterangan || 'Export selesai' }}</span>
          <div class="export-toast__actions">
            <a
              :href="'/api/v1/karir/export/download/' + item.Id_Export"
              class="export-toast__btn export-toast__btn--download"
              title="Download"
            >
              📥
            </a>
            <button
              class="export-toast__btn export-toast__btn--dismiss"
              title="Hapus"
              @click="dismiss(item.Id_Export)"
            >
              ✕
            </button>
          </div>
        </div>
        <div class="export-toast__meta">
          Selesai · {{ item.Completed_At || item.Created_At }}
        </div>
      </template>

      <!-- Failed item -->
      <template v-else-if="item.Status_Export === 'GAGAL'">
        <div class="export-toast__header">
          <span class="export-toast__icon-failed">❌</span>
          <span class="export-toast__label">{{ item.Keterangan || 'Export gagal' }}</span>
          <div class="export-toast__actions">
            <button
              class="export-toast__btn export-toast__btn--dismiss"
              title="Hapus"
              @click="dismiss(item.Id_Export)"
            >
              ✕
            </button>
          </div>
        </div>
        <div class="export-toast__error" v-if="item.Error_Message">
          {{ item.Error_Message }}
        </div>
      </template>
    </div>
  </TransitionGroup>
</template>

<script setup>
import { useExportNotification } from '../composables/useExportNotification.js'

const { items, dismiss } = useExportNotification()

function progressPercent(item) {
    if (!item.Progress_Total || item.Progress_Total === 0) return 0
    return Math.round((item.Progress_Chunk || 0) / item.Progress_Total * 100)
}
</script>

<style scoped>
.export-notification-container {
    position: fixed;
    bottom: 24px;
    right: 24px;
    z-index: 9999;
    display: flex;
    flex-direction: column-reverse;
    gap: 10px;
    max-width: 360px;
    pointer-events: none;
}

.export-toast-card {
    pointer-events: auto;
    background: rgba(255, 255, 255, 0.92);
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
    border: 1px solid rgba(99, 102, 241, 0.15);
    border-radius: 14px;
    padding: 14px 18px;
    box-shadow: 0 8px 32px rgba(99, 102, 241, 0.12), 0 2px 8px rgba(0, 0, 0, 0.06);
    font-family: 'Inter', sans-serif;
}

.export-toast__header {
    display: flex;
    align-items: center;
    gap: 10px;
}

.export-toast__label {
    flex: 1;
    font-size: 13px;
    font-weight: 600;
    color: #334155;
}

.export-toast__actions {
    display: flex;
    gap: 6px;
}

.export-toast__btn {
    border: none;
    background: none;
    cursor: pointer;
    font-size: 15px;
    padding: 4px 6px;
    border-radius: 6px;
    transition: background 0.15s;
    line-height: 1;
}

.export-toast__btn--download {
    text-decoration: none;
    color: #6366f1;
}

.export-toast__btn--download:hover {
    background: rgba(99, 102, 241, 0.1);
}

.export-toast__btn--dismiss {
    color: #94a3b8;
}

.export-toast__btn--dismiss:hover {
    background: rgba(239, 68, 68, 0.1);
    color: #ef4444;
}

.export-toast__spinner {
    width: 16px;
    height: 16px;
    border: 2px solid #e2e8f0;
    border-top-color: #6366f1;
    border-radius: 50%;
    animation: export-spin 0.8s linear infinite;
}

@keyframes export-spin {
    to { transform: rotate(360deg); }
}

.export-toast__progress-bar {
    margin-top: 10px;
    height: 4px;
    background: #f1f5f9;
    border-radius: 2px;
    overflow: hidden;
}

.export-toast__progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #6366f1, #818cf8);
    border-radius: 2px;
    transition: width 0.5s ease;
}

.export-toast__progress-text {
    margin-top: 6px;
    font-size: 11px;
    color: #94a3b8;
}

.export-toast__meta {
    margin-top: 6px;
    font-size: 11px;
    color: #94a3b8;
}

.export-toast__error {
    margin-top: 6px;
    font-size: 11px;
    color: #ef4444;
    line-height: 1.4;
}

.export-toast--failed {
    border-color: rgba(239, 68, 68, 0.2);
}

/* Transition */
.export-toast-enter-active,
.export-toast-leave-active {
    transition: all 0.3s ease;
}

.export-toast-enter-from {
    opacity: 0;
    transform: translateX(40px);
}

.export-toast-leave-to {
    opacity: 0;
    transform: translateX(40px);
}
</style>
