// WEB CAREER — Monitoring MPP: helper tampilan (badge, tanggal, nama).
// Dipakai bersama oleh MonitoringMpp.vue, MppCard.vue, dan MppDetailPanel.vue.

const BULAN = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

/** '2026-07-01' -> '01 Jul 2026' (aman untuk null / format tak dikenal). */
export function formatTanggal(iso) {
    if (!iso) return '-';
    const m = String(iso).match(/^(\d{4})-(\d{2})-(\d{2})/);
    if (!m) return iso;
    const [, y, mm, dd] = m;
    return `${dd} ${BULAN[parseInt(mm, 10) - 1] || mm} ${y}`;
}

/** '09:00:00' -> '09:00'. */
export function formatJam(jam) {
    if (!jam) return '';
    return String(jam).slice(0, 5);
}

/** 'budi.santoso' -> 'Budi Santoso'. */
export function namaLengkap(username) {
    if (!username) return '-';
    return String(username)
        .split(/[._\s]+/)
        .filter(Boolean)
        .map((w) => w.charAt(0).toUpperCase() + w.slice(1))
        .join(' ');
}

/** Inisial 1-2 huruf dari username/nama. */
export function initials(name) {
    const parts = String(name || '')
        .split(/[._\s]+/)
        .filter(Boolean);
    if (!parts.length) return '?';
    return (parts[0].charAt(0) + (parts[1]?.charAt(0) || '')).toUpperCase();
}

/** Kelas badge untuk Status MPP (Aktif / Dibatalkan). */
export function statusBadge(status) {
    if (status === 'Aktif') return 'wca-b--green';
    if (status === 'Dibatalkan') return 'wca-b--red';
    return 'wca-b--slate';
}

/** Kelas badge untuk Flag Selesai (Selesai / Belum Selesai). */
export function flagBadge(flag) {
    return flag === 'Selesai' ? 'wca-b--green' : 'wca-b--amber';
}

/** Apakah proses MPP sudah selesai (untuk indikator titik). */
export function isSelesai(flag) {
    return flag === 'Selesai';
}
