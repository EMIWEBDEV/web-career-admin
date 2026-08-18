<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * WEB CAREER — PEMERIKSA JALUR SMTP.
 *
 * ══ KENAPA ADA ═════════════════════════════════════════════════════════════
 *
 * "Email tidak terkirim" punya empat sebab yang gejalanya di log nyaris sama,
 * padahal perbaikannya berbeda jauh:
 *
 *   1. host tidak bisa dijangkau     → firewall / DNS      (bukan urusan .env)
 *   2. port ditutup jaringan         → ganti port, atau tinggalkan SMTP
 *   3. TLS/SSL tidak cocok           → ganti MAIL_ENCRYPTION
 *   4. kredensial salah              → ganti MAIL_PASSWORD
 *
 * Menebaknya satu per satu lewat kirim-coba memakan waktu berjam-jam, karena
 * tiap percobaan harus menunggu timeout dan galatnya dibungkus lapis-lapis
 * exception Symfony Mailer.
 *
 * Perintah ini memisahkan keempatnya dalam satu jalan: mengetes SAMBUNGAN
 * TCP-nya dulu — bagian yang tidak butuh kredensial sama sekali — baru
 * menawarkan kirim sungguhan. Kalau langkah pertama saja gagal, tidak ada
 * gunanya membahas password.
 *
 * ══ KENAPA PERINTAH APLIKASI, BUKAN SEKADAR `telnet` ═══════════════════════
 *
 * Karena yang perlu diperiksa adalah jalur keluar milik SERVER YANG GAGAL,
 * bukan milik laptop yang memeriksanya. Di Cloud Run — dan hosting terkelola
 * mana pun tanpa akses shell — `telnet` tidak bisa dijalankan di sana. Perintah
 * ini ikut ke dalam aplikasinya, jadi ia menguji dari tempat yang benar.
 *
 * CONTOH
 *   php artisan karir:cek-smtp
 *   php artisan karir:cek-smtp --kirim=nama@contoh.com
 */
class CekSmtp extends Command
{
    protected $signature = 'karir:cek-smtp
        {--kirim= : Setelah pemeriksaan jalur, kirim email uji ke alamat ini}
        {--timeout=8 : Batas tunggu tiap sambungan, dalam detik}';

    protected $description = 'Periksa jalur keluar SMTP dari server ini — sambungan, enkripsi, lalu kirim uji';

    /** Port yang lazim dipakai, berikut artinya bila ia yang terbuka. */
    private const PORT = [
        25 => 'SMTP polos — hampir selalu diblokir penyedia cloud',
        465 => 'SMTPS (SSL implisit) — pakai MAIL_ENCRYPTION=ssl',
        587 => 'Submission (STARTTLS) — pakai MAIL_ENCRYPTION=tls',
        2525 => 'Submission alternatif — sering lolos saat 587 diblokir',
    ];

    public function handle(): int
    {
        $host = (string) config('mail.mailers.smtp.host');
        $port = (int) config('mail.mailers.smtp.port');
        $enc = (string) (config('mail.mailers.smtp.encryption') ?? '-');
        $user = (string) config('mail.mailers.smtp.username');
        $batas = max(2, (int) $this->option('timeout'));

        if (config('mail.default') !== 'smtp') {
            $this->warn('MAIL_MAILER bukan smtp, melainkan "'.config('mail.default').'". Pemeriksaan ini hanya berlaku untuk smtp.');
        }

        $this->line('');
        $this->line('<options=bold>SETELAN SAAT INI</>');
        $this->line("  host       : {$host}");
        $this->line("  port       : {$port}");
        $this->line("  enkripsi   : {$enc}");
        $this->line('  pengguna   : '.($user ?: '<comment>kosong</comment>'));
        $this->line('  sandi      : '.(config('mail.mailers.smtp.password') ? 'terisi' : '<comment>kosong</comment>'));
        $this->line('');

        if ($host === '') {
            $this->error('MAIL_HOST kosong — tidak ada yang bisa diperiksa.');

            return self::FAILURE;
        }

        // ── 1. DNS ──────────────────────────────────────────────────────────
        // Dipisah dari uji sambungan: nama yang tak bisa diterjemahkan dan port
        // yang diblokir sama-sama berakhir "gagal menyambung", padahal yang
        // pertama diperbaiki di DNS dan yang kedua di firewall.
        $ip = gethostbyname($host);
        if ($ip === $host) {
            $this->error("DNS  : '{$host}' TIDAK BISA DITERJEMAHKAN ke alamat IP.");
            $this->line('       Periksa ejaan MAIL_HOST, atau resolver DNS server ini.');

            return self::FAILURE;
        }
        $this->line("<info>DNS  : {$host} → {$ip}</info> <fg=gray>(IPv4)</>");

        /*
         * REKAMAN AAAA — penyebab yang paling sering luput.
         *
         * Bila host punya alamat IPv6 sementara server ini tidak punya jalur
         * IPv6 keluar, PHP bisa mencoba IPv6 lebih dulu: paket keluar ke jalan
         * yang tidak ada, tak ada yang menjawab, lalu "Connection timed out".
         * Bunyinya IDENTIK dengan port yang diblokir firewall — dan
         * perbaikannya sama sekali berbeda.
         *
         * Cloud Run dengan gerbang internet bawaan TIDAK punya egress IPv6.
         * Karena itu penambahan satu rekaman AAAA di sisi penyedia email bisa
         * mematikan pengiriman tanpa satu pun perubahan di sisi kita — persis
         * pola "dulu jalan, tiba-tiba tidak".
         */
        $aaaa = @dns_get_record($host, DNS_AAAA);
        if ($aaaa) {
            $v6 = $aaaa[0]['ipv6'] ?? '?';
            $this->line("  <comment>AAAA : {$host} juga punya alamat IPv6 → {$v6}</comment>");
            $this->line('  <fg=gray>Bandingkan hasil uji IPv6 dan IPv4 di bawah — kalau IPv4 tembus</>');
            $this->line('  <fg=gray>sementara nama-hostnya habis waktu, IPv6-lah penyebabnya.</>');
        }

        // ── 1b. ALAMAT KELUAR SERVER INI ────────────────────────────────────
        $this->alamatKeluar();
        $this->line('');

        // ── 2. SAMBUNGAN TCP ────────────────────────────────────────────────
        $this->line('<options=bold>UJI SAMBUNGAN</> (tanpa kredensial — murni jalur jaringan)');
        $terbuka = [];
        foreach (self::PORT as $p => $arti) {
            $mulai = microtime(true);
            $sock = @fsockopen($host, $p, $errno, $errstr, $batas);
            $ms = (int) ((microtime(true) - $mulai) * 1000);

            if ($sock) {
                fclose($sock);
                $terbuka[] = $p;
                $tanda = $p === $port ? ' <comment>← yang dipakai sekarang</comment>' : '';
                $this->line("  <info>✓</info> {$p}\t terbuka ({$ms} ms) — {$arti}{$tanda}");

                continue;
            }

            // Pesannya dibedakan, karena artinya berbeda: DITOLAK berarti paket
            // sampai dan dijawab "tutup"; HABIS WAKTU berarti paket keluar dan
            // tidak ada yang menjawab sama sekali — tanda khas firewall yang
            // menjatuhkan paket diam-diam.
            $sebab = $ms >= ($batas * 1000 - 500) ? 'HABIS WAKTU (paket dijatuhkan diam-diam)' : "DITOLAK ({$errstr})";
            $this->line("  <fg=red>✗</> {$p}\t {$sebab}");
        }
        $this->line('');

        // ── 2b. UJI LEWAT IPv4 LANGSUNG ─────────────────────────────────────
        //
        // INILAH yang memisahkan dua sebab yang bergejala sama. Menyebut alamat
        // IPv4 apa adanya melewati resolusi nama sepenuhnya, jadi tidak ada
        // peluang PHP memilih IPv6.
        //
        //   IPv4 tembus, nama-host habis waktu → IPv6 penyebabnya
        //   dua-duanya habis waktu             → alamat asal kita yang ditolak
        $this->line('<options=bold>UJI LEWAT IPv4 LANGSUNG</> ('.$ip.') — memintas resolusi nama');
        $v4 = [];
        foreach (array_keys(self::PORT) as $p) {
            $mulai = microtime(true);
            $sock = @fsockopen($ip, $p, $errno, $errstr, $batas);
            $ms = (int) ((microtime(true) - $mulai) * 1000);
            if ($sock) {
                fclose($sock);
                $v4[] = $p;
                $this->line("  <info>✓</info> {$p}\t terbuka ({$ms} ms)");

                continue;
            }
            $this->line("  <fg=red>✗</> {$p}\t gagal ({$ms} ms)");
        }
        $this->line('');

        if ($v4 && ! $terbuka) {
            $this->warn('IPv4 TEMBUS, tapi lewat nama-host habis waktu.');
            $this->line('');
            $this->line('  Penyebabnya IPv6: server ini mencoba alamat AAAA milik host,');
            $this->line('  padahal ia tidak punya jalur IPv6 keluar. Bukan firewall, bukan');
            $this->line('  kredensial, dan bukan sesuatu yang berubah di kode Anda.');
            $this->line('');
            $this->line('  Tambal cepat — sebut alamat IPv4-nya langsung di .env server ini:');
            $this->line("    <options=bold>MAIL_HOST={$ip}</>");
            $this->line('  Sertifikat TLS-nya terbit atas nama host, bukan angka, jadi');
            $this->line('  verifikasi nama akan menolak. Untuk 465/ssl biasanya perlu');
            $this->line('  disetel agar tidak memeriksa nama — dan itu menurunkan jaminan');
            $this->line('  keamanan, jadi perlakukan sebagai penambal sementara.');
            $this->line('');
            $this->line('  Perbaikan yang benar: minta penyedia email menghapus rekaman AAAA,');
            $this->line('  atau beri layanan ini egress IPv6 (VPC + Cloud NAT ber-IPv6).');

            return self::FAILURE;
        }

        // ── 3. KESIMPULAN ───────────────────────────────────────────────────
        if (! $terbuka) {
            $this->error('SELURUH port SMTP tertutup dari server ini.');
            $this->line('');
            $this->line('  Ini firewall keluar milik penyedia hosting, bukan salah setelan.');
            $this->line('  Tidak ada nilai MAIL_PORT / MAIL_PASSWORD yang bisa memperbaikinya.');
            $this->line('  Jalan keluarnya: kirim lewat HTTP API (Resend, Brevo, Mailgun,');
            $this->line('  SendGrid) — semuanya punya driver Laravel, dan yang berubah hanya');
            $this->line('  MAIL_MAILER + satu API key. Kode pengirimannya tidak disentuh.');

            return self::FAILURE;
        }

        if (! in_array($port, $terbuka, true)) {
            $saran = in_array(587, $terbuka, true) ? [587, 'tls']
                : (in_array(465, $terbuka, true) ? [465, 'ssl'] : [$terbuka[0], 'tls']);
            $this->warn("Port yang dipakai sekarang ({$port}) TERTUTUP, tapi {$saran[0]} terbuka.");
            $this->line('');
            $this->line('  Ubah di .env server ini lalu nyalakan ulang worker:');
            $this->line("    <options=bold>MAIL_PORT={$saran[0]}</>");
            $this->line("    <options=bold>MAIL_ENCRYPTION={$saran[1]}</>");
            $this->line('    php artisan queue:restart');

            return self::FAILURE;
        }

        $this->info("Jalur ke {$host}:{$port} TERBUKA — jaringan bukan penyebabnya.");
        $this->line('  Kalau pengiriman tetap gagal, sebabnya ada di lapisan berikutnya:');
        $this->line('  enkripsi yang tidak cocok, atau kredensial yang ditolak. Uji dengan');
        $this->line('  --kirim=alamat@anda.com untuk melihat galat aslinya.');
        $this->line('');

        // ── 4. KIRIM SUNGGUHAN (opsional) ───────────────────────────────────
        $tujuan = trim((string) $this->option('kirim'));
        if ($tujuan === '') {
            return self::SUCCESS;
        }

        $this->line('<options=bold>KIRIM UJI</> ke '.$tujuan.' …');
        try {
            Mail::raw(
                "Email uji dari Web Careers.\n\nhost: {$host}:{$port} ({$enc})\nwaktu: ".now()->toDateTimeString(),
                fn ($m) => $m->to($tujuan)->subject('[UJI] Jalur email Web Careers')
            );
            $this->info('  Terkirim. Jalur SMTP sehat sepenuhnya.');

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('  GAGAL: '.$e->getMessage());
            $this->line('');
            $this->line($this->tafsir($e->getMessage()));

            return self::FAILURE;
        }
    }

    /**
     * IP yang DILIHAT DUNIA LUAR saat server ini menghubungi mereka.
     *
     * Tidak bisa dibaca dari dalam. Alamat pada antarmuka jaringan server —
     * yang dilaporkan `ifconfig` atau `$_SERVER` — adalah alamat privatnya;
     * yang sampai ke penyedia email adalah alamat setelah melewati NAT, dan
     * satu-satunya cara mengetahuinya adalah bertanya kepada pihak di seberang.
     *
     * Dipakai dua layanan berbeda, karena satu jawaban tidak membuktikan
     * apa-apa: Cloud Run TIDAK punya IP keluar tetap kecuali egress-nya
     * dirutekan lewat VPC + Cloud NAT. Dua jawaban yang BERBEDA justru temuan
     * pentingnya — artinya alamatnya memang berganti-ganti, dan meminta
     * penyedia email memasukkannya ke daftar putih tidak akan pernah berhasil.
     *
     * Sekalian ini menguji egress HTTPS. Bila bagian ini berhasil sementara
     * seluruh port SMTP habis waktu, terbukti yang diblokir SMTP-nya saja —
     * bukan jalan keluar server ini secara keseluruhan.
     */
    private function alamatKeluar(): void
    {
        $sumber = [
            'https://api.ipify.org' => 'ipify',
            'https://ifconfig.me/ip' => 'ifconfig.me',
        ];

        $jawab = [];
        foreach ($sumber as $url => $nama) {
            $ctx = stream_context_create(['http' => ['timeout' => 6, 'ignore_errors' => true]]);
            $ip = @file_get_contents($url, false, $ctx);
            $ip = trim((string) $ip);
            if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP)) {
                $jawab[$nama] = $ip;
            }
        }

        if (! $jawab) {
            $this->line('  <fg=red>✗</> alamat keluar tidak terbaca — egress HTTPS pun bermasalah,');
            $this->line('    atau layanan pengecek IP tidak bisa dijangkau dari sini.');

            return;
        }

        foreach ($jawab as $nama => $ip) {
            $this->line("  <info>IP keluar</info> : {$ip} <fg=gray>(menurut {$nama})</>");
        }

        if (count(array_unique($jawab)) > 1) {
            $this->line('  <comment>Kedua sumber melaporkan IP BERBEDA — alamat keluarnya tidak tetap.</comment>');
            $this->line('  <comment>Daftar putih di sisi penyedia email tidak akan pernah cocok.</comment>');
        }

        $this->line('  <fg=gray>Egress HTTPS berfungsi. Bandingkan dengan hasil uji port di bawah.</>');
    }

    /**
     * Galat mentah Symfony Mailer → kalimat yang menunjuk apa yang harus diubah.
     *
     * Mengenali POLA, bukan kelas exception-nya: pesan yang sama bisa datang
     * membungkus exception berbeda tergantung versi transport.
     */
    private function tafsir(string $pesan): string
    {
        $l = strtolower($pesan);

        if (str_contains($l, '535') || str_contains($l, 'authentication')) {
            return '  → KREDENSIAL ditolak. Periksa MAIL_USERNAME & MAIL_PASSWORD.'
                ."\n".'    Beberapa penyedia menuntut alamat email penuh sebagai username.';
        }

        if (str_contains($l, 'certificate') || str_contains($l, 'ssl') || str_contains($l, 'tls')) {
            return '  → ENKRIPSI tidak cocok. Untuk port 587 pakai MAIL_ENCRYPTION=tls,'
                ."\n".'    untuk 465 pakai ssl. Keduanya tidak bisa ditukar.';
        }

        if (str_contains($l, '550') || str_contains($l, 'relay') || str_contains($l, 'sender')) {
            return '  → ALAMAT PENGIRIM ditolak server. MAIL_FROM_ADDRESS harus alamat'
                ."\n".'    yang benar-benar dimiliki akun MAIL_USERNAME.';
        }

        if (str_contains($l, 'timed out') || str_contains($l, 'could not be established')) {
            return '  → Sambungan putus di tengah jalan meski portnya sempat terbuka.'
                ."\n".'    Biasanya rate-limit di sisi penyedia email, atau firewall yang'
                ."\n".'    hanya menjatuhkan sesi panjang.';
        }

        return '  → Galat belum dikenali. Salin pesan di atas apa adanya saat melapor.';
    }
}
