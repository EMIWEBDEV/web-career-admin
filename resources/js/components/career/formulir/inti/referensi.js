/**
 * WEB CAREER — Pengambil opsi referensi untuk field bertipe `referensi`.
 *
 * Sumber opsi TIDAK lagi dikirim di muka lewat props. Master Kampus berisi
 * ratusan ribu baris, jadi daftar dicari ke server sambil kandidat mengetik.
 *
 * Berkas ini hanya urusan pengambilan data: singgahan (cache) supaya daftar
 * pendek seperti jenjang tidak diminta berulang kali, dan pembatalan permintaan
 * lama supaya jawaban yang datang terlambat tidak menimpa hasil ketikan terbaru.
 */
import axios from 'axios';

const DASAR = '/api/v1/referensi';

/**
 * Singgahan daftar induk (jenjang, jenis institusi, dan daftar awal tanpa
 * kata pencarian). Kunci = url lengkap.
 *
 * Hasil PENCARIAN sengaja tidak disinggah: kandidat mengetik banyak variasi
 * yang tiap-tiapnya cuma dipakai sekali, jadi menyimpannya hanya menggelembungkan
 * memori tanpa menghemat permintaan.
 */
const singgahan = new Map();
const SINGGAHAN_MAKS = 40;

/** Permintaan yang sedang berjalan per field, supaya bisa dibatalkan. */
const berjalan = new Map();

function kunci(sumber, param) {
    const q = Object.entries(param)
        .filter(([, v]) => v !== '' && v !== null && v !== undefined)
        .sort(([a], [b]) => a.localeCompare(b))
        .map(([k, v]) => `${k}=${encodeURIComponent(v)}`)
        .join('&');
    return q ? `${DASAR}/${sumber}?${q}` : `${DASAR}/${sumber}`;
}

/**
 * Ambil opsi satu sumber.
 *
 * @param {string} sumber  jenjang | jenis_institusi | kampus | prodi
 * @param {object} param   { cari, jenjang, jenis }
 * @param {string} token   penanda pemanggil — permintaan lama dengan token
 *                         sama dibatalkan begitu ada yang baru
 * @returns {Promise<Array<{nilai:string,label:string,ket:?string}>>}
 */
export async function ambilOpsi(sumber, param = {}, token = sumber) {
    const url = kunci(sumber, param);
    const bolehSinggah = !String(param.cari || '').trim();

    if (bolehSinggah && singgahan.has(url)) return singgahan.get(url);

    berjalan.get(token)?.abort();
    const kendali = new AbortController();
    berjalan.set(token, kendali);

    try {
        const { data } = await axios.get(url, { signal: kendali.signal });
        const hasil = Array.isArray(data?.result) ? data.result : [];
        if (bolehSinggah) {
            if (singgahan.size >= SINGGAHAN_MAKS) singgahan.delete(singgahan.keys().next().value);
            singgahan.set(url, hasil);
        }
        return hasil;
    } catch (e) {
        // Permintaan yang sengaja dibatalkan bukan kegagalan — jangan
        // menimpa daftar yang sedang tampil dengan array kosong.
        if (axios.isCancel?.(e) || e?.code === 'ERR_CANCELED' || e?.name === 'CanceledError') {
            return null;
        }
        return [];
    } finally {
        if (berjalan.get(token) === kendali) berjalan.delete(token);
    }
}

/** Tunda pemanggilan supaya tiap ketikan huruf tidak jadi satu permintaan. */
export function tunda(fn, ms = 280) {
    let timer = null;
    return (...args) => {
        clearTimeout(timer);
        timer = setTimeout(() => fn(...args), ms);
    };
}
