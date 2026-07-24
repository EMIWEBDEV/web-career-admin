// ══════════════════════════════════════════════════════════════════
// WEB CAREER — shared helpers (formatters, status, navigation, URLs)
// Modul portabel: seluruh fitur Web Career ada di folder `Pages/Career/`.
// Pindahkan folder ini (+ route & controller Career) untuk memindahkan modul.
// ══════════════════════════════════════════════════════════════════
import { router } from '@inertiajs/vue3';

export const CAREER_HOME = '/'; // halaman utama (landing) — route root
export const CAREER_LANDING = '/karir/landing-page'; // basis sub-route detail (lowongan/mt)
export const lowonganUrl = (id) => `${CAREER_LANDING}/lowongan/${id}`;
export const mtUrl = (id) => `${CAREER_LANDING}/mt/${id}`;

const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

export function formatDate(iso) {
    if (!iso) return '-';
    const d = new Date(String(iso).replace(' ', 'T'));
    if (Number.isNaN(d.getTime())) return iso;
    return `${d.getDate()} ${MONTHS[d.getMonth()]} ${d.getFullYear()}`;
}

// Tanggal + jam — window pendaftaran (buka/tutup) WAJIB tampil dgn waktunya.
export function formatDateTime(iso) {
    if (!iso) return '-';
    const d = new Date(String(iso).replace(' ', 'T'));
    if (Number.isNaN(d.getTime())) return iso;
    const jam = String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0');
    return `${d.getDate()} ${MONTHS[d.getMonth()]} ${d.getFullYear()} · ${jam}`;
}

export function daysLeft(iso) {
    if (!iso) return 999;
    const now = new Date();
    now.setHours(0, 0, 0, 0);
    return Math.ceil((new Date(String(iso).replace(' ', 'T')).getTime() - now.getTime()) / 86400000);
}

export function deadlineLabel(iso) {
    if (!iso) return 'Tanpa batas waktu'; // lowongan EVERGREEN (di-share terus)
    const d = daysLeft(iso);
    if (d < 0) return 'Ditutup';
    if (d === 0) return 'Tutup hari ini';
    if (d === 1) return 'Tutup besok';
    return `${d} hari lagi`;
}

export function typeClass(type) {
    if (type === 'Internship') return 'wc-badge--intern';
    if (type === 'Contract') return 'wc-badge--contract';
    return 'wc-badge--full';
}

export function stageTypeLabel(tipe) {
    return (
        {
            FORM: 'Pengisian formulir & dokumen',
            ADMIN_SCREENING: 'Seleksi administrasi',
            HCLEARN_TEST: 'Tes online (HCLearn)',
            INTERVIEW: 'Wawancara',
            DOCUMENT: 'Kelengkapan dokumen',
            DECISION: 'Keputusan & pengumuman',
            OFFERING: 'Penawaran kerja',
            ONBOARDING: 'Onboarding',
        }[tipe] || tipe
    );
}

export function isFull(item) {
    if (!item) return false;
    return item.status === 'PENUH' || (item.kuota && item.kuotaTerisi >= item.kuota);
}

export function kuotaPct(item) {
    if (!item || !item.kuota) return 0;
    return Math.min(100, Math.round(((item.kuotaTerisi || 0) / item.kuota) * 100));
}

export function statusLabel(status) {
    return { BUKA: 'Pendaftaran Dibuka', PENUH: 'Kuota Penuh', SEGERA: 'Segera Dibuka' }[status] || status;
}

export function statusClass(status) {
    return { BUKA: 'wc-st--open', PENUH: 'wc-st--full', SEGERA: 'wc-st--soon' }[status] || 'wc-st--open';
}

// ── Navigation (works from landing & from inner/detail pages) ──
function onLanding() {
    if (typeof window === 'undefined') return false;
    const p = window.location.pathname;
    return p === CAREER_HOME || p === CAREER_LANDING;
}

// Smooth-scroll berbasis requestAnimationFrame.
// Penting: pada Windows dengan "Show animations" mati (prefers-reduced-motion: reduce),
// browser MENGABAIKAN `behavior:'smooth'` & CSS `scroll-behavior:smooth` → scroll jadi lompat.
// Animasi manual ini tetap mulus tanpa bergantung pada setting OS tersebut.
let wcScrollRAF = null;
export function animateScroll(targetY, duration = 720) {
    if (typeof window === 'undefined') return;
    const startY = window.scrollY || window.pageYOffset;
    const maxY = Math.max(0, document.documentElement.scrollHeight - window.innerHeight);
    const destY = Math.max(0, Math.min(targetY, maxY));
    const dist = destY - startY;
    if (Math.abs(dist) < 2) return;
    if (wcScrollRAF) cancelAnimationFrame(wcScrollRAF);
    // easeInOutCubic — akselerasi lembut lalu melambat elegan
    const ease = (t) => (t < 0.5 ? 4 * t * t * t : 1 - Math.pow(-2 * t + 2, 3) / 2);
    let start = null;
    const step = (ts) => {
        if (start === null) start = ts;
        const p = Math.min((ts - start) / duration, 1);
        window.scrollTo(0, startY + dist * ease(p));
        if (p < 1) wcScrollRAF = requestAnimationFrame(step);
        else wcScrollRAF = null;
    };
    wcScrollRAF = requestAnimationFrame(step);
}

export function scrollToId(id, offset = 84) {
    const el = document.getElementById(id);
    if (!el) return;
    const y = el.getBoundingClientRect().top + (window.scrollY || window.pageYOffset) - offset;
    animateScroll(y);
}

// Reveal-on-scroll bersama: amati semua .wc-reveal, tambah .is-in saat masuk viewport.
// Dipakai landing & halaman detail. Aman bila IntersectionObserver tak tersedia.
export function observeReveal(selector = '.wc-reveal') {
    if (typeof window === 'undefined' || !('IntersectionObserver' in window)) return null;
    const obs = new IntersectionObserver(
        (entries) =>
            entries.forEach((e) => {
                if (e.isIntersecting) {
                    e.target.classList.add('is-in');
                    obs.unobserve(e.target);
                }
            }),
        { threshold: 0.12 },
    );
    document.querySelectorAll(selector).forEach((el) => obs.observe(el));
    return obs;
}

export function goToSection(id) {
    if (onLanding()) {
        scrollToId(id);
    } else {
        try {
            sessionStorage.setItem('wcScrollTarget', id);
        } catch (e) {
            /* ignore */
        }
        router.visit(CAREER_HOME);
    }
}

export function goHome() {
    if (onLanding()) {
        animateScroll(0);
    } else {
        router.visit(CAREER_HOME);
    }
}

export function goApply(card) {
    // Formulir pendaftaran memakai DESAIN WIZARD di ApplyForm
    // (Data Diri → Pendidikan → Verifikasi foto → Finalisasi). Route:
    // /karir/apply/{id}. Menerima objek kartu ATAU id langsung.
    const id = card && typeof card === 'object' ? card.id : card;
    router.visit(id ? `/karir/apply/${id}` : '/karir/apply/RC-2026-001');
}
