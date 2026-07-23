<!-- SHELL SIDEBAR — desain "Worklist Pelamar" (Claude Design):
     RAIL ikon 68px (kiri, tetap) + SIDEBAR overlay 290px yang muncul saat
     hover / dikunci (pin), drawer penuh di mobile. Data digerakkan props
     (brand, navigation, user) yang sama dengan shell lama — semua halaman
     admin (KPI/HCIS/Career) otomatis ikut. Font Inter dari evo-theme. -->
<template>
    <!-- Scrim mobile -->
    <div class="evs-scrim" :class="{ 'is-on': shell.state.isMobile && shell.state.mobileSidebarOpen }" @click="shell.closeMobileSidebar()"></div>

    <!-- ═══ RAIL IKON ═══ -->
    <nav class="evs-rail" aria-label="Navigasi modul">
        <button type="button" class="evs-rail__logo" :class="{ 'is-pinned': shell.state.sidebarLocked }" title="Klik untuk mengunci sidebar" @click="togglePin" @mouseenter="hoverOpen">
            <img v-if="!imgErr.mainLogo" :src="brand.mainLogo" :alt="brand.mainLogoAlt || 'EVO Group'" @error="imgErr.mainLogo = true" />
            <span v-else class="evs-rail__logofb">EVO</span>
        </button>
        <div style="height: 16px"></div>

        <a class="evs-rail__btn" :class="{ 'is-active': homeLink.isActive }" :href="homeLink.url || '#'" :title="homeLink.title || 'Dashboard'" @mouseenter="hoverOpen" @click="visit($event, homeLink.url)">
            <svg width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 10.5L12 3l9 7.5" /><path d="M5 9.5V21h14V9.5" /></svg>
        </a>

        <button
            v-for="m in modules"
            :key="m.id"
            type="button"
            class="evs-rail__btn"
            :class="{ 'is-active': m.id === openModule }"
            :title="m.label || m.name"
            @mouseenter="hoverOpen"
            @click="railModule(m.id)"
        >
            <i :class="m.icon || 'bi bi-grid'" style="font-size: 19px"></i>
        </button>

        <div style="flex: 1"></div>
        <div class="evs-rail__avatar" :title="user.name">{{ initials }}</div>
    </nav>

    <!-- ═══ SIDEBAR OVERLAY ═══ -->
    <aside class="evs-sb" :class="{ 'is-open': expanded, 'is-mobile': shell.state.isMobile }" @mouseenter="hoverOpen" @mouseleave="hoverClose">
        <!-- Header -->
        <div class="evs-sb__head">
            <div class="evs-sb__brandrow">
                <button type="button" class="evs-sb__logo" title="Kunci / lepas sidebar" @click="togglePin">
                    <img v-if="!imgErr.mainLogo2" :src="brand.mainLogo" :alt="brand.mainLogoAlt || 'EVO Group'" @error="imgErr.mainLogo2 = true" />
                    <span v-else class="evs-rail__logofb">EVO</span>
                </button>
                <div class="evs-sb__brandtxt">
                    <div class="evs-sb__brandtitle">EVO GROUP</div>
                    <div class="evs-sb__brandsub">{{ sectionLabel }}</div>
                </div>
                <button type="button" class="evs-sb__pin" :class="{ 'is-pinned': shell.state.sidebarLocked }" title="Kunci / lepas sidebar" @click="togglePin">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 4h6l-1 6 3 3v2H7v-2l3-3z" /><path d="M12 15v5" /></svg>
                </button>
            </div>
            <div v-if="subsidiaries.length" class="evs-sb__subs">
                <template v-for="(s, i) in subsidiaries" :key="s.id || i">
                    <img :src="s.src" :alt="s.name" @error="$event.target.style.display = 'none'" />
                    <span v-if="i < subsidiaries.length - 1" class="evs-sb__subsdiv"></span>
                </template>
            </div>
        </div>

        <!-- Nav scroll -->
        <div class="evs-sb__scroll">
            <a class="evs-sb__home" :href="homeLink.url || '#'" @click="visit($event, homeLink.url)">
                <span class="evs-sb__homeico">
                    <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 10.5L12 3l9 7.5" /><path d="M5 9.5V21h14V9.5" /></svg>
                </span>
                <span style="min-width: 0">
                    <span class="evs-sb__hometitle">{{ homeLink.title || 'Dashboard' }}</span>
                    <span class="evs-sb__homesub">{{ homeLink.subtitle || 'Halaman Utama' }}</span>
                </span>
            </a>

            <div class="evs-sb__label">MODUL</div>

            <div class="evs-sb__mods">
                <template v-for="m in modules" :key="m.id">
                    <!-- Kepala modul -->
                    <button type="button" class="evs-mod" :class="{ 'is-open': m.id === openModule }" @click="toggleModule(m.id)">
                        <span class="evs-mod__ico"><i :class="m.icon || 'bi bi-grid'" style="font-size: 19px"></i></span>
                        <span style="flex: 1; min-width: 0">
                            <span class="evs-mod__title">{{ m.label || m.name }}</span>
                            <span class="evs-mod__sub">{{ m.subtitle || '' }}</span>
                        </span>
                        <svg class="evs-mod__chev" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M6 9l6 6 6-6" /></svg>
                    </button>

                    <!-- Isi modul: grup akordeon -->
                    <div class="evs-mod__body" :class="{ 'is-open': m.id === openModule }">
                        <div class="evs-groups">
                            <template v-for="g in m.groups || []" :key="g.id">
                                <button type="button" class="evs-grp" :class="{ 'is-active': g.id === m.activeGroupId }" @click="toggleGroup(g.id)">
                                    <span class="evs-grp__ico" :class="{ 'is-active': g.id === m.activeGroupId }">
                                        <i :class="groupIcon(g)" style="font-size: 16px"></i>
                                    </span>
                                    <span style="flex: 1; text-align: left">{{ g.title }}</span>
                                    <svg class="evs-grp__chev" :class="{ 'is-open': openGroups[g.id] }" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#aab2c5" stroke-width="2.4" stroke-linecap="round"><path d="M9 6l6 6-6 6" /></svg>
                                </button>
                                <div class="evs-grp__body" :class="{ 'is-open': openGroups[g.id] }" :style="openGroups[g.id] ? { maxHeight: (g.items || []).length * 46 + 8 + 'px' } : {}">
                                    <a v-for="it in g.items || []" :key="it.id" class="evs-item" :class="{ 'is-active': it.isActive }" :href="it.url" @click="visit($event, it.url)">
                                        <span class="evs-item__dot" :class="{ 'is-active': it.isActive }"></span>
                                        <span class="evs-item__txt">{{ it.title }}</span>
                                    </a>
                                </div>
                            </template>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        <!-- Footer user + dropdown profil (perilaku lama dipertahankan) -->
        <div class="evs-sb__foot" style="position: relative" @click.stop>
            <div class="shell-profile-menu" :class="{ 'is-visible': shell.state.showProfileMenu && expanded }" @click.stop>
                <button class="shell-profile-menu__item shell-btn" type="button" @click.stop="profileAction('/profil')">
                    <i class="bi bi-person-circle"></i>
                    <span>Profil Saya</span>
                </button>
                <button class="shell-profile-menu__item shell-btn" type="button" @click.stop="profileAction('/about')">
                    <i class="bi bi-info-circle"></i>
                    <span>Tentang</span>
                </button>
                <div class="shell-profile-divider"></div>
                <button class="shell-profile-menu__item shell-btn is-danger" type="button" @click.stop="profileAction('/logout')">
                    <i class="bi bi-box-arrow-right"></i>
                    <span>Keluar Sesi</span>
                </button>
            </div>

            <button type="button" class="evs-user" :class="{ 'is-open': shell.state.showProfileMenu }" @click.stop="toggleProfileMenu">
                <span class="evs-user__avatar">{{ initials }}</span>
                <span style="flex: 1; min-width: 0; text-align: left">
                    <span class="evs-user__name">{{ user.name || 'Pengguna' }}</span>
                    <span class="evs-user__role">{{ roleLabel }}</span>
                </span>
                <svg class="evs-user__chev" :class="{ 'is-rot': shell.state.showProfileMenu }" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#aab2c5" stroke-width="2.4" stroke-linecap="round" style="flex: 0 0 auto"><path d="M6 15l6-6 6 6" /></svg>
            </button>
        </div>
    </aside>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { useShellState } from '../../composables/useShellState';

const props = defineProps({
    user: { type: Object, default: () => ({}) },
    brand: { type: Object, default: () => ({}) },
    navigation: { type: Object, default: () => ({ home: null, modules: [] }) },
});

const shell = useShellState();
const imgErr = reactive({ mainLogo: false, mainLogo2: false });

const modules = computed(() => props.navigation?.modules || []);
const homeLink = computed(() => props.navigation?.home || {});
const subsidiaries = computed(() => props.brand?.subsidiaries || []);
const sectionLabel = computed(() => (props.navigation?.sectionLabel ? props.navigation.sectionLabel.toUpperCase() + ' SYSTEM' : 'UNIFIED PLATFORM'));

const initials = computed(() => {
    const n = (props.user?.name || 'EV').trim().split(/\s+/);
    return ((n[0]?.[0] || '') + (n[1]?.[0] || '')).toUpperCase() || 'EV';
});
const roleLabel = computed(() => props.user?.department || props.user?.nik || 'Administrator');

// Sidebar terbuka: mobile pakai drawer; desktop pakai kunci (pin) ATAU hover.
const expanded = computed(() => (shell.state.isMobile ? shell.state.mobileSidebarOpen : shell.state.sidebarLocked || shell.state.sidebarExpanded));

/* ── Akordeon modul & grup ── */
const openModule = ref(null);
const openGroups = reactive({});

function syncFromNav() {
    const aktif = modules.value.find((m) => m.isActive) || modules.value[0] || null;
    openModule.value = aktif ? aktif.id : null;
    Object.keys(openGroups).forEach((k) => delete openGroups[k]);
    if (aktif && aktif.activeGroupId) openGroups[aktif.activeGroupId] = true;
}
watch(modules, syncFromNav, { immediate: true, deep: true });

function toggleModule(id) {
    openModule.value = openModule.value === id ? null : id;
}
function toggleGroup(id) {
    openGroups[id] = !openGroups[id];
}
function railModule(id) {
    openModule.value = id;
    if (!shell.state.isMobile && !shell.state.sidebarLocked) shell.toggleDesktopSidebarLock();
}

/* ── Buka/tutup ── */
let hoverT = null;
function hoverOpen() {
    clearTimeout(hoverT);
    shell.setSidebarExpandedByHover(true);
}
function hoverClose() {
    clearTimeout(hoverT);
    hoverT = setTimeout(() => shell.setSidebarExpandedByHover(false), 150);
}
function togglePin() {
    if (shell.state.isMobile) {
        shell.toggleMobileSidebar();
        return;
    }
    shell.toggleDesktopSidebarLock();
}
function visit(e, url) {
    if (!url) return;
    e.preventDefault();
    shell.closeMobileSidebar();
    router.visit(url);
}

/* ── Dropdown profil footer (perilaku shell lama) ── */
function toggleProfileMenu() {
    shell.state.showProfileMenu = !shell.state.showProfileMenu;
}
function profileAction(url) {
    shell.resetInteractionState();
    shell.closeMobileSidebar();
    if (!url) return;
    if (url === '/logout') {
        router.get('/logout', {}, { preserveScroll: true });
        return;
    }
    router.visit(url);
}

// Klik di luar sidebar → tutup dropdown profil.
function onDocMousedown(e) {
    if (!shell.state.showProfileMenu) return;
    const root = document.querySelector('.evs-sb');
    if (root && !root.contains(e.target)) shell.resetInteractionState();
}
onMounted(() => document.addEventListener('mousedown', onDocMousedown));
onBeforeUnmount(() => document.removeEventListener('mousedown', onDocMousedown));

/* Ikon grup — fallback tematik per judul (props grup tak membawa ikon). */
function groupIcon(g) {
    const t = (g.title || '').toLowerCase();
    if (t.includes('master')) return 'bi bi-database';
    if (t.includes('operasional')) return 'bi bi-list-task';
    if (t.includes('seleksi')) return 'bi bi-clipboard-check';
    if (t.includes('pengaturan') || t.includes('setting')) return 'bi bi-gear';
    if (t.includes('lamaran')) return 'bi bi-file-earmark-text';
    if (t.includes('akun')) return 'bi bi-person-vcard';
    if (t.includes('data')) return 'bi bi-folder2';
    return (g.items && g.items[0] && g.items[0].icon) || 'bi bi-folder2';
}
</script>

<style scoped>
/* ═══ RAIL ═══ */
/* Susunan z-index MENGIKUTI SHELL LAMA: seluruh shell ≤ 1030 supaya progress
   bar bawaan Inertia (NProgress, z-index 1031) selalu tampil utuh di atasnya. */
.evs-rail {
    position: fixed;
    inset: 0 auto 0 0;
    z-index: 1028;
    width: 68px;
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 16px 0;
    background: rgba(255, 255, 255, 0.72);
    backdrop-filter: blur(14px);
    -webkit-backdrop-filter: blur(14px);
    border-right: 1px solid rgba(226, 232, 240, 0.7);
}
.evs-rail__logo {
    appearance: none;
    cursor: pointer;
    padding: 0;
    width: 46px;
    height: 46px;
    border-radius: 14px;
    background: linear-gradient(135deg, #f4f2ff, #eef2ff);
    display: flex;
    align-items: center;
    justify-content: center;
    transition: box-shadow 0.18s;
    border: 2px solid transparent;
}
.evs-rail__logo.is-pinned {
    border-color: #8b5cf6;
    box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.18);
}
.evs-rail__logo img {
    width: 30px;
    height: 30px;
    object-fit: contain;
}
.evs-rail__logofb {
    font-size: 12px;
    font-weight: 900;
    color: #4f46e5;
}
.evs-rail__btn {
    appearance: none;
    border: none;
    cursor: pointer;
    width: 44px;
    height: 44px;
    border-radius: 13px;
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.16s;
    background: transparent;
    color: #94a3b8;
    text-decoration: none;
}
.evs-rail__btn:hover {
    background: #eef0f7;
    color: #4f46e5;
}
.evs-rail__btn.is-active {
    background: linear-gradient(135deg, #8b5cf6, #6366f1);
    color: #fff;
    box-shadow: 0 8px 20px rgba(99, 102, 241, 0.34);
}
.evs-rail__avatar {
    width: 42px;
    height: 42px;
    border-radius: 13px;
    background: linear-gradient(135deg, #8b5cf6, #6366f1);
    color: #fff;
    font-size: 13px;
    font-weight: 800;
    display: flex;
    align-items: center;
    justify-content: center;
}

/* ═══ SCRIM ═══ */
.evs-scrim {
    position: fixed;
    inset: 0;
    z-index: 1029;
    background: rgba(15, 23, 42, 0.42);
    backdrop-filter: blur(2px);
    transition: opacity 0.3s;
    opacity: 0;
    pointer-events: none;
}
.evs-scrim.is-on {
    opacity: 1;
    pointer-events: auto;
}

/* ═══ SIDEBAR OVERLAY ═══ */
.evs-sb {
    position: fixed;
    top: 0;
    left: 0;
    bottom: 0;
    z-index: 1030; /* sama seperti shell lama — tetap di bawah NProgress (1031) */
    width: 290px;
    display: flex;
    flex-direction: column;
    background: #fff;
    border-right: 1px solid #eef0f7;
    box-shadow: 0 30px 90px rgba(15, 23, 42, 0.22);
    transition: transform 0.3s cubic-bezier(0.22, 1, 0.36, 1), opacity 0.3s;
    transform: translateX(-14px);
    opacity: 0;
    pointer-events: none;
}
.evs-sb.is-open {
    transform: translateX(0);
    opacity: 1;
    pointer-events: auto;
}
.evs-sb.is-mobile {
    width: min(300px, 90vw);
    transform: translateX(-104%);
    opacity: 1;
    transition: transform 0.34s cubic-bezier(0.22, 1, 0.36, 1);
}
.evs-sb.is-mobile.is-open {
    transform: translateX(0);
}

.evs-sb__head {
    padding: 18px 18px 14px;
    flex: 0 0 auto;
}
.evs-sb__brandrow {
    display: flex;
    align-items: center;
    gap: 12px;
}
.evs-sb__logo {
    appearance: none;
    cursor: pointer;
    padding: 0;
    width: 52px;
    height: 52px;
    border-radius: 16px;
    background: linear-gradient(135deg, #f4f2ff, #eef2ff);
    border: 1px solid #e7e3fb;
    display: flex;
    align-items: center;
    justify-content: center;
    flex: 0 0 auto;
}
.evs-sb__logo img {
    width: 34px;
    height: 34px;
    object-fit: contain;
}
.evs-sb__brandtxt {
    min-width: 0;
    flex: 1;
}
.evs-sb__brandtitle {
    font-size: 16px;
    font-weight: 900;
    color: #0f172a;
    letter-spacing: -0.02em;
    line-height: 1;
}
.evs-sb__brandsub {
    font-size: 10px;
    font-weight: 800;
    letter-spacing: 0.18em;
    color: #8b93a7;
    margin-top: 3px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.evs-sb__pin {
    appearance: none;
    cursor: pointer;
    width: 34px;
    height: 34px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex: 0 0 auto;
    transition: all 0.16s;
    border: 1px solid #e6e9f3;
    background: #fff;
    color: #94a3b8;
}
.evs-sb__pin.is-pinned {
    border-color: transparent;
    background: linear-gradient(135deg, #8b5cf6, #6366f1);
    color: #fff;
}
.evs-sb__subs {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-top: 14px;
    padding: 10px 12px;
    border-radius: 14px;
    background: #f8f9fc;
    border: 1px solid #eef0f7;
}
.evs-sb__subs img {
    height: 19px;
    width: auto;
    object-fit: contain;
    opacity: 0.9;
    flex: 1;
    min-width: 0;
}
.evs-sb__subsdiv {
    width: 1px;
    height: 18px;
    background: #e2e8f0;
    flex: 0 0 auto;
}

.evs-sb__scroll {
    flex: 1;
    overflow-y: auto;
    padding: 6px 12px 12px;
}
.evs-sb__home {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 11px 12px;
    border-radius: 13px;
    transition: background 0.16s;
    text-decoration: none;
}
.evs-sb__home:hover {
    background: #f4f5fb;
}
.evs-sb__homeico {
    width: 36px;
    height: 36px;
    border-radius: 11px;
    background: #f1f2f9;
    color: #64748b;
    display: flex;
    align-items: center;
    justify-content: center;
    flex: 0 0 auto;
}
.evs-sb__hometitle {
    display: block;
    font-size: 14px;
    font-weight: 800;
    color: #1e293b;
}
.evs-sb__homesub {
    display: block;
    font-size: 11px;
    color: #94a3b8;
}
.evs-sb__label {
    font-size: 10px;
    font-weight: 800;
    letter-spacing: 0.18em;
    color: #aab2c5;
    padding: 16px 12px 8px;
}
.evs-sb__mods {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

/* Modul (level 1) */
.evs-mod {
    position: relative;
    appearance: none;
    cursor: pointer;
    font-family: inherit;
    width: 100%;
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px;
    border-radius: 14px;
    transition: all 0.18s;
    border: 1px solid transparent;
    background: transparent;
}
.evs-mod.is-open {
    border-color: rgba(99, 102, 241, 0.18);
    background: linear-gradient(135deg, rgba(139, 92, 246, 0.1), rgba(99, 102, 241, 0.1));
    box-shadow: inset 3px 0 0 #6366f1;
}
.evs-mod__ico {
    width: 38px;
    height: 38px;
    border-radius: 11px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex: 0 0 auto;
    background: #f1f2f9;
    color: #8792a6;
}
.evs-mod.is-open .evs-mod__ico {
    background: linear-gradient(135deg, #8b5cf6, #6366f1);
    color: #fff;
    box-shadow: 0 8px 18px rgba(99, 102, 241, 0.34);
}
.evs-mod__title {
    display: block;
    font-size: 14.5px;
    font-weight: 900;
    letter-spacing: -0.01em;
    color: #1e293b;
    text-align: left;
}
.evs-mod.is-open .evs-mod__title {
    color: #4338ca;
}
.evs-mod__sub {
    display: block;
    font-size: 11px;
    font-weight: 600;
    color: #94a3b8;
    text-align: left;
}
.evs-mod.is-open .evs-mod__sub {
    color: #7c74b0;
}
.evs-mod__chev {
    flex: 0 0 auto;
    color: #8b83c9;
    transition: transform 0.26s;
}
.evs-mod.is-open .evs-mod__chev {
    transform: rotate(180deg);
}
.evs-mod__body {
    overflow: hidden;
    transition: all 0.32s ease;
    padding-left: 8px;
    max-height: 0;
    opacity: 0;
    margin: 0;
}
.evs-mod__body.is-open {
    max-height: 1600px;
    opacity: 1;
    margin: 2px 0 6px;
}
.evs-groups {
    display: flex;
    flex-direction: column;
    gap: 2px;
    padding-top: 4px;
}

/* Grup (level 2) */
.evs-grp {
    appearance: none;
    cursor: pointer;
    font-family: inherit;
    width: 100%;
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 11px 12px;
    border-radius: 13px;
    border: none;
    background: transparent;
    font-size: 13.5px;
    font-weight: 800;
    color: #5b6478;
    transition: background 0.16s;
}
.evs-grp:hover,
.evs-grp.is-active {
    background: #f4f5fb;
}
.evs-grp__ico {
    width: 34px;
    height: 34px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex: 0 0 auto;
    background: #f1f2f9;
    color: #8792a6;
}
.evs-grp__ico.is-active {
    background: rgba(99, 102, 241, 0.12);
    color: #6366f1;
}
.evs-grp__chev {
    flex: 0 0 auto;
    transition: transform 0.24s;
}
.evs-grp__chev.is-open {
    transform: rotate(90deg);
}
.evs-grp__body {
    overflow: hidden;
    transition: all 0.26s ease;
    padding-left: 12px;
    display: flex;
    flex-direction: column;
    gap: 1px;
    max-height: 0;
    opacity: 0;
    margin: 0;
}
.evs-grp__body.is-open {
    opacity: 1;
    margin: 2px 0 4px;
}

/* Item (level 3) */
.evs-item {
    display: flex;
    align-items: center;
    gap: 11px;
    padding: 9px 12px 9px 14px;
    border-radius: 11px;
    font-size: 13px;
    font-weight: 600;
    color: #6b7488;
    transition: all 0.14s;
    text-decoration: none;
}
.evs-item:hover {
    background: #f4f5fb;
    color: #4338ca;
}
.evs-item.is-active {
    font-weight: 800;
    color: #4338ca;
    background: rgba(99, 102, 241, 0.1);
}
.evs-item__dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: #c3cad8;
    flex: 0 0 auto;
}
.evs-item__dot.is-active {
    width: 7px;
    height: 7px;
    background: #6366f1;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.18);
}
.evs-item__txt {
    min-width: 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* Footer */
.evs-sb__foot {
    flex: 0 0 auto;
    padding: 12px;
    border-top: 1px solid #eef0f7;
}
.evs-user {
    appearance: none;
    cursor: pointer;
    width: 100%;
    display: flex;
    align-items: center;
    gap: 11px;
    padding: 9px 11px;
    border-radius: 14px;
    border: 1px solid #eef0f7;
    background: #fff;
    transition: background 0.16s;
}
.evs-user:hover {
    background: #f8f9fc;
}
.evs-user__avatar {
    width: 40px;
    height: 40px;
    border-radius: 12px;
    background: linear-gradient(135deg, #8b5cf6, #6366f1);
    color: #fff;
    font-size: 14px;
    font-weight: 800;
    display: flex;
    align-items: center;
    justify-content: center;
    flex: 0 0 auto;
}
.evs-user__name {
    display: block;
    font-size: 13px;
    font-weight: 800;
    color: #1e293b;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.evs-user__role {
    display: block;
    font-size: 11px;
    color: #94a3b8;
}
.evs-user.is-open {
    background: #f4f5fb;
}
.evs-user__chev {
    transition: transform 0.24s;
}
.evs-user__chev.is-rot {
    transform: rotate(180deg);
}

@media (max-width: 991.98px) {
    .evs-rail {
        display: none;
    }
}
</style>
