<template>
    <header ref="topbarRoot" class="shell-topbar">
        <div class="shell-topbar__inner">
            <!-- LEFT: GREETING -->
            <div class="shell-topbar__left">
                <button
                    class="shell-topbar-action shell-btn d-lg-none"
                    type="button"
                    aria-label="Toggle navigation"
                    @click.stop="shell.togglePrimarySidebar()"
                >
                    <i class="bi bi-list"></i>
                </button>

                <div class="shell-greeting">
                    <div class="shell-greeting__eyebrow">
                        <i class="bi bi-stars"></i>
                        <span>{{ greetingLabel }}</span>
                    </div>
                    <div class="shell-greeting__title">Halo, {{ firstName }}</div>
                </div>
            </div>

            <!-- RIGHT: CLOCK + ACTIONS + ACTIVE USER MODULE -->
            <div class="shell-topbar__right">
                <div class="shell-clock d-none d-md-flex">
                    <div class="shell-clock__time">
                        <i class="bi bi-clock"></i>
                        <span>{{ currentTimeText }}</span>
                    </div>
                    <div class="shell-clock__date">
                        <i class="bi bi-calendar3"></i>
                        <span>{{ currentDateText }}</span>
                    </div>
                </div>

                <div class="shell-topbar-divider d-none d-md-block"></div>

                <div class="shell-action-group">
                    <!-- PUSAT UNDUHAN / NOTIFIKASI / PANDUAN
                         Ketiganya belum aktif di Web Careers: klik membuka modal "Segera Hadir"
                         alih-alih memanggil endpoint yang belum ada. -->
                    <div class="position-relative">
                        <button
                            class="shell-topbar-icon shell-btn"
                            type="button"
                            aria-label="Pusat Unduhan"
                            title="Pusat Unduhan"
                            @mousedown.stop
                            @click.stop="openComingSoon('activity')"
                        >
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                width="16"
                                height="16"
                                fill="currentColor"
                                viewBox="0 0 16 16"
                            >
                                <path
                                    fill-rule="evenodd"
                                    d="M7.646 10.854a.5.5 0 0 0 .708 0l2-2a.5.5 0 0 0-.708-.708L8.5 9.293V5.5a.5.5 0 0 0-1 0v3.793L6.354 8.146a.5.5 0 1 0-.708.708z"
                                />
                                <path
                                    d="M4.406 3.342A5.53 5.53 0 0 1 8 2c2.69 0 4.923 2 5.166 4.579C14.758 6.804 16 8.137 16 9.773 16 11.569 14.502 13 12.687 13H3.781C1.708 13 0 11.366 0 9.318c0-1.763 1.266-3.223 2.942-3.593.143-.863.698-1.723 1.464-2.383m.653.757c-.757.653-1.153 1.44-1.153 2.056v.448l-.445.049C2.064 6.805 1 7.952 1 9.318 1 10.785 2.23 12 3.781 12h8.906C13.98 12 15 10.988 15 9.773c0-1.216-1.02-2.228-2.313-2.228h-.5v-.5C12.188 4.825 10.328 3 8 3a4.53 4.53 0 0 0-2.941 1.1z"
                                />
                            </svg>
                        </button>
                    </div>

                    <div class="position-relative">
                        <button
                            class="shell-topbar-icon shell-btn"
                            type="button"
                            aria-label="Notifikasi"
                            title="Notifikasi"
                            @mousedown.stop
                            @click.stop="openComingSoon('notifications')"
                        >
                            <i class="bi bi-bell"></i>
                        </button>
                    </div>

                    <div class="position-relative">
                        <button
                            class="shell-topbar-icon shell-btn"
                            type="button"
                            aria-label="Panduan"
                            title="Panduan"
                            @mousedown.stop
                            @click.stop="openComingSoon('help')"
                        >
                            <i class="bi bi-question-circle"></i>
                        </button>
                    </div>
                </div>

                <div class="shell-topbar-divider d-none d-lg-block"></div>

                <div class="position-relative" data-tour="topbar-profile">
                    <button
                        class="shell-module-pill shell-btn"
                        :class="{ 'is-active': shell.state.showBreadcrumb }"
                        type="button"
                        @mousedown.stop
                        @click.stop="goToActiveModule"
                    >
                        <span class="shell-module-pill__left">
                            <span class="shell-module-pill__icon">
                                <i :class="activeModuleIcon"></i>
                            </span>
                            <span class="shell-module-pill__code d-none d-md-inline">{{ activeModuleDisplay }}</span>
                            <span class="shell-module-pill__code d-inline d-md-none">{{ userKodeKaryawan }}</span>
                        </span>

                        <span class="shell-module-pill__divider"></span>

                        <span class="shell-module-pill__user-wrap">
                            <span class="shell-module-pill__user">{{ userName }}</span>
                            <span class="shell-module-pill__nik">{{ userNik }}</span>
                        </span>
                    </button>

                    <div
                        :class="[
                            'shell-topbar-popover',
                            'shell-breadcrumb-popover',
                            { 'is-visible': shell.state.showBreadcrumb },
                        ]"
                        data-tour="breadcrumb-popover"
                        @mousedown.stop
                        @click.stop
                    >
                        <!-- User Identity Card -->
                        <div class="shell-user-card">
                            <div class="shell-user-card__avatar">
                                {{ userInitials }}
                            </div>
                            <div class="shell-user-card__info">
                                <div class="shell-user-card__name">{{ userName }}</div>
                                <div class="shell-user-card__meta">
                                    <span class="shell-user-card__badge shell-user-card__badge--nik">
                                        <i class="bi bi-person-badge"></i>
                                        {{ userNik }}
                                    </span>
                                    <span
                                        v-if="userDepartment"
                                        class="shell-user-card__badge shell-user-card__badge--dept"
                                    >
                                        <i class="bi bi-building"></i>
                                        {{ userDepartment }}
                                    </span>
                                </div>
                            </div>
                            <button
                                class="shell-user-card__logout shell-btn"
                                type="button"
                                title="Keluar dari Akun"
                                @click="handleLogout"
                            >
                                <i class="bi bi-box-arrow-right"></i>
                            </button>
                        </div>

                        <div class="shell-popover-head">
                            <div>
                                <div class="shell-popover-title">Jalur Navigasi Anda</div>
                                <div
                                    class="shell-popover-subtitle"
                                    style="font-size: 0.65rem; color: #64748b; margin-top: 0.15rem"
                                >
                                    Melacak posisi Anda saat ini di dalam aplikasi
                                </div>
                            </div>
                        </div>

                        <div class="shell-transit-map">
                            <!-- Platform Node -->
                            <div class="shell-transit-node is-completed">
                                <div class="shell-transit-station">
                                    <div class="shell-transit-dot"><i class="bi bi-house-door-fill"></i></div>
                                    <div class="shell-transit-line"></div>
                                </div>
                                <div class="shell-transit-info">
                                    <div class="shell-transit-label">Platform Utama</div>
                                    <button class="shell-transit-link" @click="handleModalNavigate(homeUrl)">
                                        Beranda EVO
                                        <i class="bi bi-box-arrow-up-right" style="font-size: 0.75rem"></i>
                                    </button>
                                </div>
                            </div>

                            <!-- Module Node -->
                            <div class="shell-transit-node is-active">
                                <div class="shell-transit-station">
                                    <div class="shell-transit-dot"><i :class="activeModuleIcon"></i></div>
                                    <div class="shell-transit-line"></div>
                                </div>
                                <div class="shell-transit-info">
                                    <div class="shell-transit-label">Modul Aktif</div>
                                    <div class="shell-transit-name">{{ activeModuleDisplay }}</div>

                                    <!-- Quick Jump Siblings -->
                                    <div class="shell-transit-branches" v-if="breadcrumbSiblings.length">
                                        <div class="shell-transit-branches-title">Akses Cepat (Cabang Jalur):</div>
                                        <div class="shell-transit-chips">
                                            <button
                                                v-for="sib in breadcrumbSiblings"
                                                :key="sib.id"
                                                class="shell-transit-chip shell-btn"
                                                :title="sib.title"
                                                @click="handleModalNavigate(sib.url)"
                                            >
                                                <i :class="sib.icon"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Current Page Node -->
                            <div class="shell-transit-node is-current">
                                <div class="shell-transit-station">
                                    <div class="shell-transit-dot-pulse"></div>
                                </div>
                                <div class="shell-transit-info">
                                    <div class="shell-transit-label">Titik Lokasi Anda</div>
                                    <div class="shell-transit-name" style="font-size: 1rem; color: #f59e0b">
                                        {{ activePageDisplay }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <AppComingSoonModal v-model="showComingSoon" :variant="comingSoonVariant" />
    </header>

</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { useShellState } from '../../composables/useShellState';
import AppComingSoonModal from './AppComingSoonModal.vue';

const shell = useShellState();
const page = usePage();

const props = defineProps({
    user: {
        type: Object,
        default: () => ({}),
    },
    brand: {
        type: Object,
        default: () => ({}),
    },
    layout: {
        type: Object,
        default: () => ({}),
    },
    navigation: {
        type: Object,
        default: () => ({
            home: null,
            modules: [],
        }),
    },
});

const topbarRoot = ref(null);

// ── Fitur yang belum aktif (unduhan / notifikasi / panduan) ───────────────
const showComingSoon = ref(false);
const comingSoonVariant = ref('activity');

function openComingSoon(variant) {
    shell.resetInteractionState();
    comingSoonVariant.value = variant;
    showComingSoon.value = true;
}

// ── Identitas & navigasi ─────────────────────────────────────────────────
const shellMeta = computed(() => props.layout?.shell || {});
const userName = computed(() => props.user?.name || props.user?.username || 'User');
const userNik = computed(() => props.user?.nik || '-');
const userKodeKaryawan = computed(() => props.user?.kode_karyawan || '-');
const userDepartment = computed(() => props.user?.department || null);
const userInitials = computed(() => {
    const parts = String(userName.value).trim().split(/\s+/);
    if (parts.length >= 2) return (parts[0][0] + parts[1][0]).toUpperCase();
    return String(userName.value).slice(0, 2).toUpperCase();
});
const firstName = computed(() => String(userName.value).split(' ')[0] || userName.value);
const homeUrl = computed(() => props.navigation?.home?.url || '/');

const activeModuleCode = computed(() => {
    if (shellMeta.value?.activeModule === 'HOME') {
        return 'HOME';
    }

    return shellMeta.value?.activeModule || 'APP';
});

const activeModuleDisplay = computed(() => (activeModuleCode.value === 'HOME' ? 'DASHBOARD' : activeModuleCode.value));

const activeModuleData = computed(() => {
    return (props.navigation?.modules || []).find((module) => module.code === activeModuleCode.value);
});

const activePageData = computed(() => {
    if (!activeModuleData.value) return null;
    let found = null;
    const currentPath = page.url.split('?')[0];

    (activeModuleData.value.groups || []).forEach((group) => {
        (group.items || []).forEach((item) => {
            const isMatchByCode =
                (shellMeta.value?.activeSubMenu && shellMeta.value.activeSubMenu === item.jenisPage) ||
                (shell.state.activeSubMenu && shell.state.activeSubMenu === item.jenisPage);

            let isMatchByUrl = false;
            if (item.url) {
                try {
                    const itemPath = new URL(item.url, window.location.origin).pathname;
                    // Exact match atau sub-route (mis. /karir/penjadwalan/detail di dalam /karir/penjadwalan)
                    isMatchByUrl = currentPath === itemPath || currentPath.startsWith(itemPath + '/');
                } catch (e) {}
            }

            if (isMatchByCode || Boolean(item.isActive) || isMatchByUrl) {
                found = item;
            }
        });
    });
    return found;
});

const activePageDisplay = computed(() => {
    if (activeModuleCode.value === 'HOME') return 'Beranda EVO';
    // Prioritas 1: langsung dari server (shellMeta)
    if (shellMeta.value?.activeSubMenuLabel) return shellMeta.value.activeSubMenuLabel;
    // Prioritas 2: dari pencocokan manual (field = title, bukan label)
    if (activePageData.value?.title) return activePageData.value.title;
    // Prioritas 3: dari currentPageTitle server
    if (shellMeta.value?.currentPageTitle) return shellMeta.value.currentPageTitle;
    return 'Halaman Belum Terpetakan';
});

const activeModuleIcon = computed(() => {
    if (activeModuleCode.value === 'HOME') {
        return 'bi bi-house-door-fill';
    }
    return activeModuleData.value?.icon || 'bi bi-grid-1x2-fill';
});

const breadcrumbSiblings = computed(() => {
    if (!activeModuleData.value) return [];

    const siblings = [];
    (activeModuleData.value.groups || []).forEach((group) => {
        // Abaikan grup administrator agar tidak meramaikan akses cepat
        if (group.title && group.title.toLowerCase().includes('admin')) return;

        (group.items || []).forEach((item) => {
            const titleMatch =
                item.title &&
                (item.title.toLowerCase().includes('admin') || item.title.toLowerCase().includes('pengaturan'));
            const urlMatch = item.url && item.url.toLowerCase().includes('admin');

            if (!titleMatch && !urlMatch) {
                siblings.push({
                    id: item.id || item.title,
                    title: item.title,
                    url: item.url,
                    icon: item.icon || 'bi bi-circle',
                });
            }
        });
    });
    return siblings;
});

// ── Jam & salam ──────────────────────────────────────────────────────────
const currentTime = ref(new Date());
let timer = null;

const greetingLabel = computed(() => {
    const hour = currentTime.value.getHours();
    if (hour < 11) return 'SELAMAT PAGI';
    if (hour < 15) return 'SELAMAT SIANG';
    if (hour < 19) return 'SELAMAT SORE';
    return 'SELAMAT MALAM';
});

const currentTimeText = computed(() =>
    currentTime.value.toLocaleTimeString('id-ID', {
        hour: '2-digit',
        minute: '2-digit',
    }),
);

const currentDateText = computed(() =>
    currentTime.value.toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    }),
);

// ── Aksi ─────────────────────────────────────────────────────────────────
function handleModalNavigate(url) {
    shell.resetInteractionState();
    router.visit(url);
}

function handleLogout() {
    shell.resetInteractionState();
    // Tanpa overlay — langsung akhiri sesi & alihkan ke halaman masuk.
    router.get('/logout', {}, { preserveScroll: true });
}

function toggleBreadcrumb() {
    if (shell.state.showBreadcrumb) {
        shell.state.showBreadcrumb = false;
    } else {
        shell.resetInteractionState();
        shell.state.showBreadcrumb = true;
    }
}

function goToActiveModule() {
    toggleBreadcrumb();
}

function handleOutsideClick(event) {
    if (!topbarRoot.value) {
        return;
    }

    if (!topbarRoot.value.contains(event.target)) {
        shell.resetInteractionState();
    }
}

onMounted(() => {
    timer = window.setInterval(() => {
        currentTime.value = new Date();
    }, 1000);

    document.addEventListener('mousedown', handleOutsideClick);
});

onBeforeUnmount(() => {
    if (timer) {
        window.clearInterval(timer);
    }

    document.removeEventListener('mousedown', handleOutsideClick);
});
</script>

<style scoped>
.bi {
    display: inline-table;
}.shell-topbar,
.shell-topbar * {
    box-sizing: border-box;
}.shell-topbar {
    position: sticky;
    top: 0;
    z-index: 1020;
    height: 4rem;
    background: rgba(255, 255, 255, 0.52);
    backdrop-filter: blur(12px);
    border-bottom: 1px solid rgba(226, 232, 240, 0.62);
    color: #0f172a;
    font-size: 13px;
    transition: all 500ms cubic-bezier(0.2, 0.8, 0.2, 1);
}.shell-topbar__inner {
    height: 100%;
    padding: 0 2.5rem 0 1.25rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1.5rem;
}.shell-btn {
    border: 0;
    outline: 0;
    background: transparent;
    font: inherit;
    cursor: pointer;
    -webkit-tap-highlight-color: transparent;
}.shell-topbar__left,
.shell-topbar__right,
.shell-action-group {
    display: flex;
    align-items: center;
}.shell-topbar__left {
    min-width: 0;
    gap: 1rem;
}.shell-topbar__right {
    margin-left: auto;
    gap: 1.5rem;
    flex-shrink: 0;
}.shell-action-group {
    gap: 0.25rem;
}.shell-topbar-action {
    width: 2.5rem;
    height: 2.5rem;
    border-radius: 0.9rem;
    color: #64748b;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: all 260ms ease;
}.shell-topbar-action:hover {
    background: #f1f5f9;
    color: #4f46e5;
}.shell-greeting {
    min-width: 0;
    display: flex;
    flex-direction: column;
    text-align: left;
}.shell-greeting__eyebrow {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    margin-bottom: 0.32rem;
    color: #94a3b8;
    font-size: 0.625rem;
    line-height: 1;
    font-weight: 950;
    letter-spacing: 0.18em;
    text-transform: uppercase;
}.shell-greeting__eyebrow i {
    color: #f59e0b;
    font-size: 0.88rem;
    letter-spacing: 0;
}.shell-greeting__title {
    color: #0f172a;
    font-size: 1rem;
    line-height: 1;
    font-weight: 950;
    letter-spacing: -0.025em;
}.shell-clock {
    flex-direction: column;
    align-items: flex-end;
}.shell-clock__time {
    display: flex;
    align-items: center;
    gap: 0.42rem;
    color: #0f172a;
    font-size: 0.875rem;
    line-height: 1;
    font-weight: 950;
}.shell-clock__time i {
    color: #4f46e5;
    font-size: 0.82rem;
}.shell-clock__date {
    display: flex;
    align-items: center;
    gap: 0.34rem;
    margin-top: 0.38rem;
    color: #94a3b8;
    font-size: 0.68rem;
    line-height: 1;
    font-weight: 800;
}.shell-clock__date i {
    font-size: 0.68rem;
}.shell-topbar-divider {
    width: 1px;
    height: 2.5rem;
    background: rgba(226, 232, 240, 0.78);
}.shell-topbar-icon {
    position: relative;
    width: 2.5rem;
    height: 2.5rem;
    border-radius: 0.8rem;
    color: #64748b;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 1.16rem;
    line-height: 1;
    transition:
        background 240ms ease,
        color 240ms ease,
        box-shadow 240ms ease,
        transform 240ms ease;
}.shell-topbar-icon .bi {
    display: inline-flex;
    align-items: center;
    justify-content: center;
}.shell-topbar-icon:hover {
    background: #f1f5f9;
    color: #4f46e5;
}.shell-topbar-icon.is-active {
    color: #ffffff;
    box-shadow: 0 14px 28px rgba(15, 23, 42, 0.12);
}.shell-topbar-popover {
    position: absolute;
    top: calc(100% + 0.75rem);
    right: 0;
    z-index: 1;
    width: min(22rem, calc(100vw - 1.5rem));
    border-radius: 1.25rem;
    border: 1px solid rgba(226, 232, 240, 0.95);
    background: rgba(255, 255, 255, 0.96);
    backdrop-filter: blur(18px);
    box-shadow: 0 28px 60px rgba(15, 23, 42, 0.18);
    overflow: hidden;
}.shell-popover-head {
    min-height: 3.25rem;
    padding: 0.85rem 1rem;
    border-bottom: 1px solid #f1f5f9;
    background: linear-gradient(180deg, rgba(248, 250, 252, 0.45) 0%, rgba(255, 255, 255, 0) 100%);
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
}.shell-popover-title {
    color: #0f172a;
    font-size: 0.825rem;
    line-height: 1.25;
    font-weight: 850;
    text-transform: none;
    letter-spacing: -0.015em;
    display: flex;
    align-items: center;
    gap: 0.55rem;
}/* ── Breadcrumb User Card ──────────────────────────────────────────────── */
.shell-user-card {
    display: flex;
    align-items: center;
    gap: 0.85rem;
    padding: 1rem 1rem 0.85rem;
    background: linear-gradient(135deg, rgba(79, 70, 229, 0.04), rgba(99, 102, 241, 0.02));
    border-bottom: 1px solid #f1f5f9;
}.shell-user-card__avatar {
    width: 2.75rem;
    height: 2.75rem;
    border-radius: 0.9rem;
    background: linear-gradient(135deg, #4f46e5, #6366f1);
    color: #ffffff;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.9rem;
    font-weight: 950;
    flex-shrink: 0;
    box-shadow: 0 6px 16px rgba(79, 70, 229, 0.28);
    letter-spacing: 0.02em;
}.shell-user-card__info {
    min-width: 0;
    flex: 1;
}.shell-user-card__name {
    color: #0f172a;
    font-size: 0.82rem;
    font-weight: 900;
    line-height: 1.2;
    letter-spacing: -0.01em;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}.shell-user-card__meta {
    display: flex;
    flex-wrap: wrap;
    gap: 0.35rem;
    margin-top: 0.4rem;
}.shell-user-card__badge {
    display: inline-flex;
    align-items: center;
    gap: 0.28rem;
    padding: 0.22rem 0.55rem;
    border-radius: 999px;
    font-size: 0.58rem;
    font-weight: 800;
    line-height: 1;
    white-space: nowrap;
    max-width: 11rem;
    overflow: hidden;
    text-overflow: ellipsis;
}.shell-user-card__badge i {
    font-size: 0.62rem;
    flex-shrink: 0;
}.shell-user-card__badge--nik {
    background: rgba(79, 70, 229, 0.08);
    color: #4f46e5;
    border: 1px solid rgba(79, 70, 229, 0.14);
}.shell-user-card__badge--dept {
    background: rgba(16, 185, 129, 0.08);
    color: #059669;
    border: 1px solid rgba(16, 185, 129, 0.14);
    /* max-width: 9rem; */
    overflow: hidden;
    text-overflow: ellipsis;
}/* ── User Card Logout Icon Button ──────────────────────────────────────── */
.shell-user-card__logout {
    width: 2rem;
    height: 2rem;
    border-radius: 0.6rem;
    background: rgba(239, 68, 68, 0.08);
    color: #ef4444;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    line-height: 1;
    flex-shrink: 0;
    transition: all 200ms ease;
}.shell-user-card__logout:hover {
    background: #ef4444;
    color: #ffffff;
    box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
}.shell-user-card__logout .bi {
    display: flex;
    align-items: center;
    justify-content: center;
}.shell-module-pill {
    min-height: 3rem;
    padding: 0.5rem 0.95rem 0.5rem 0.75rem;
    border-radius: 1rem;
    border: 1px solid #dcfce7;
    background: rgba(240, 253, 244, 0.72);
    box-shadow: 0 8px 18px rgba(15, 23, 42, 0.04);
    display: inline-flex;
    align-items: center;
    gap: 0.75rem;
    transition: all 240ms ease;
}.shell-module-pill:hover {
    background: rgba(220, 252, 231, 0.72);
}.shell-module-pill__left {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding-right: 0.65rem;
    border-right: 1px solid rgba(187, 247, 208, 0.9);
}.shell-module-pill__icon {
    width: 1.35rem;
    height: 1.35rem;
    border-radius: 0.45rem;
    background: #ffffff;
    color: #4f46e5;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.78rem;
    box-shadow: 0 4px 10px rgba(15, 23, 42, 0.05);
}.shell-module-pill__code {
    color: #15803d;
    font-size: 0.68rem;
    line-height: 1;
    font-weight: 950;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    max-width: 8rem;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}.shell-module-pill__divider {
    display: none;
}.shell-module-pill__user-wrap {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    line-height: 1;
}.shell-module-pill__user {
    max-width: 8rem;
    color: #0f172a;
    font-size: 0.62rem;
    line-height: 1;
    font-weight: 950;
    text-transform: uppercase;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}.shell-module-pill__nik {
    margin-top: 0.26rem;
    color: #64748b;
    font-size: 0.5rem;
    line-height: 1;
    font-weight: 800;
}.shell-topbar button:focus-visible {
    outline: 2px solid rgba(79, 70, 229, 0.28);
    outline-offset: 2px;
}

@media (max-width: 1199.98px) {.shell-topbar__inner {
        padding: 0 1.25rem 0 1rem;
    }.shell-topbar__right {
        gap: 0.75rem;
    }}

@media (max-width: 767.98px) {.shell-topbar {
        height: auto;
        min-height: 3.5rem;
    }.shell-greeting {
        display: none;
    }.shell-greeting__eyebrow {
        gap: 0;
        margin: 0;
    }.shell-topbar__left {
        gap: 0.3rem;
    }.shell-topbar__inner {
        padding: 0.55rem 1rem;
        gap: 0.8rem;
    }.shell-greeting__title {
        font-size: 0.92rem;
    }.shell-topbar__right {
        gap: 0.55rem;
    }.shell-module-pill {
        padding: 0rem 0.35rem;
        min-height: 2rem;
    }.shell-module-pill__user-wrap {
        display: none;
    }.shell-module-pill__left {
        padding-right: 0;
        border-right: 0;
    }.shell-topbar-popover {
        position: fixed;
        top: 4.75rem;
        right: 1rem;
        width: min(24rem, calc(100vw - 2rem));
    }}.shell-popover-title {
    font-weight: 600;
    font-size: 0.95rem;
    color: #1e293b;
    display: flex;
    align-items: center;
}
</style>
