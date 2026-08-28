/**
 * TANGGAL YANG DIKETIK, BUKAN DIKLIK.
 *
 * Kandidat mengetik `02/05/2026`; yang tersimpan tetap `2026-05-02` — sama
 * persis dengan yang dihasilkan el-date-picker (`value-format="YYYY-MM-DD"`).
 * Penyimpanannya tidak berubah sedikit pun, jadi seluruh laporan, penyaring,
 * dan perhitungan usia yang sudah ada tetap membaca hal yang sama.
 *
 * ── KENAPA DIKETIK ──────────────────────────────────────────────────────────
 *
 * Kalender bagus untuk tanggal yang DICARI ("Senin depan itu tanggal berapa?").
 * Tanggal lahir tidak dicari — ia sudah diingat. Memilihnya lewat kalender
 * menuntut orang menggulir puluhan tahun ke belakang hanya untuk memasukkan
 * angka yang sejak awal ada di kepalanya.
 *
 * ── KENAPA dd/mm/yyyy ───────────────────────────────────────────────────────
 *
 * Itu urutan yang dipakai KTP dan yang diucapkan orang Indonesia. Menampilkan
 * yyyy-mm-dd di layar akan membuat sebagian mengetik terbalik, dan kesalahannya
 * tidak terlihat: `02/05` dan `05/02` sama-sama tanggal yang sah.
 */

/** Ambil digit saja, potong 8 — ddmmyyyy. */
function digit(teks) {
    return String(teks ?? '').replace(/\D/g, '').slice(0, 8);
}

/**
 * Sisipkan garis miring SAAT MENGETIK.
 *
 * Dipanggil tiap ketukan. Garis miringnya ditambahkan sistem, bukan diketik
 * orang — mengetiknya sendiri akan menghasilkan `2//5/26` begitu jarinya
 * meleset, dan tidak ada yang menahannya.
 */
export function masker(teks) {
    const mentah = String(teks ?? '');

    // ── PEMISAH YANG DIKETIK SENDIRI ────────────────────────────────────────
    //
    // Orang mengetik "2/5/1998", bukan "02051998" — itu cara paling wajar
    // menyebut tanggal, dan menempelkannya dari tempat lain pun berbentuk
    // begitu. Tanpa cabang ini digitnya dirapatkan jadi "251998" lalu dipotong
    // per dua menjadi "25/19/98": tanggal yang sama sekali berbeda, tanpa satu
    // pun tanda bahwa ada yang salah.
    //
    // Jadi begitu ada pemisah, potongannya dihormati — hari dan bulan
    // dilengkapi nol di depan, bukan digeser.
    let d;

    if (/[/\-. ]/.test(mentah)) {
        const bagian = mentah.split(/[/\-. ]+/);
        const hari = (bagian[0] ?? '').replace(/\D/g, '');
        const bulan = (bagian[1] ?? '').replace(/\D/g, '');
        const tahun = (bagian[2] ?? '').replace(/\D/g, '');

        // Nol depan hanya untuk potongan yang SUDAH DITINGGALKAN — yang di
        // belakangnya sudah ada potongan lain. Memasangnya pada potongan yang
        // sedang diketik akan mengubah "1" jadi "01" tepat saat orang hendak
        // mengetik "12".
        const h = bagian.length > 1 && hari.length === 1 ? `0${hari}` : hari;
        const b = bagian.length > 2 && bulan.length === 1 ? `0${bulan}` : bulan;

        // DIGABUNG JADI SATU DERET, bukan dipotong per potongan.
        //
        // Ini yang dulu salah: tiap potongan dipangkas ke lebarnya sendiri, jadi
        // digit ke-3 yang diketik pada bagian bulan ("05/051") dibuang begitu
        // saja — kolomnya berhenti menerima ketikan tanpa sebab yang terlihat.
        // Digabung lebih dulu, luapannya mengalir sendiri ke bagian berikutnya.
        d = (h + b + tahun).slice(0, 8);
    } else {
        d = digit(mentah);
    }

    if (d.length <= 2) return d;
    if (d.length <= 4) return `${d.slice(0, 2)}/${d.slice(2)}`;

    return `${d.slice(0, 2)}/${d.slice(2, 4)}/${d.slice(4)}`;
}

/** '2026-05-02' -> '02/05/2026'. Kosong/tak dikenal -> ''. */
export function keTampilan(iso) {
    const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(String(iso ?? ''));

    return m ? `${m[3]}/${m[2]}/${m[1]}` : '';
}

/**
 * '02/05/2026' -> '2026-05-02'. Null bila belum lengkap ATAU bukan tanggal nyata.
 *
 * Kelengkapan dan kebenaran sengaja dibedakan oleh pemanggilnya: yang pertama
 * berarti "masih mengetik", yang kedua "salah". Menyamakan keduanya akan
 * memerahkan kolom sejak ketukan pertama.
 */
export function keIso(teks) {
    const d = digit(teks);
    if (d.length !== 8) {
        return null;
    }

    const hari = +d.slice(0, 2);
    const bulan = +d.slice(2, 4);
    const tahun = +d.slice(4);

    // Diperiksa lewat Date, bukan lewat batas 1-31: 31 April dan 30 Februari
    // lolos pemeriksaan batas, dan keduanya bukan tanggal. Date menormalkan
    // tanggal yang mustahil ke bulan berikutnya — jadi bila hasilnya tidak
    // sama dengan yang dimasukkan, yang dimasukkan memang tidak ada.
    const t = new Date(tahun, bulan - 1, hari);
    if (t.getFullYear() !== tahun || t.getMonth() !== bulan - 1 || t.getDate() !== hari) {
        return null;
    }

    const dua = (n) => String(n).padStart(2, '0');

    return `${tahun}-${dua(bulan)}-${dua(hari)}`;
}

/** Sudah 8 digit? Dipakai membedakan "masih mengetik" dari "salah". */
export function lengkap(teks) {
    return digit(teks).length === 8;
}

/**
 * Pesan galat, atau null bila tidak apa-apa.
 *
 * Tahun dibatasi 1900–hari ini. Batas atasnya bukan kerewelan: tanggal lahir di
 * masa depan hampir selalu salah ketik tahun (2026 jadi 2062), dan tanpa
 * penahan itu ia lolos sampai ke berkas resmi.
 */
export function galat(teks, { maksHariIni = true } = {}) {
    if (!teks) {
        return null;
    }
    if (!lengkap(teks)) {
        return 'Lengkapi tanggalnya — tulis hari/bulan/tahun, mis. 02/05/1998.';
    }

    const iso = keIso(teks);
    if (!iso) {
        return 'Tanggal itu tidak ada. Periksa lagi hari dan bulannya.';
    }

    const tahun = +iso.slice(0, 4);
    if (tahun < 1900) {
        return 'Tahunnya terlalu jauh ke belakang — periksa lagi.';
    }
    if (maksHariIni && iso > hariIniIso()) {
        return 'Tanggalnya belum terjadi. Periksa lagi tahunnya.';
    }

    return null;
}

/**
 * Hari ini sebagai YYYY-MM-DD menurut penanggalan LOKAL.
 *
 * Bukan toISOString(): di WIB (UTC+7) setiap saat sebelum pukul 07.00 masih
 * terhitung kemarin dalam UTC, dan tanggal lahir "hari ini" akan ditolak
 * sebagai masa depan — kesalahan yang hanya muncul pagi hari.
 */
function hariIniIso() {
    const d = new Date();
    const dua = (n) => String(n).padStart(2, '0');

    return `${d.getFullYear()}-${dua(d.getMonth() + 1)}-${dua(d.getDate())}`;
}
