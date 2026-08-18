<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| DIAGNOSTIK — dijalankan lewat peramban, bukan terminal
|--------------------------------------------------------------------------
|
| Cloud Run tidak menyediakan shell. Tidak ada tempat mengetik
| `php artisan …` di sana, padahal justru dari SANA-lah pemeriksaan harus
| dijalankan: yang perlu diuji adalah jalur keluar milik mesin yang gagal,
| bukan milik laptop yang memeriksanya.
|
| Maka perintahnya dipanggil lewat HTTP dan keluarannya dikirim apa adanya
| sebagai teks. Sama persis dengan yang tampil di terminal, hanya salurannya
| yang berbeda.
|
| ══ PENJAGAAN ═════════════════════════════════════════════════════════════
|
| Keluarannya menyebut host, nama pengguna SMTP, dan alamat IP keluar server.
| Bukan rahasia besar, tapi jelas bukan konsumsi publik — halaman ini memetakan
| permukaan jaringan sistem bagi siapa pun yang membacanya.
|
| Karena itu SUPERADMIN saja, lewat sesi login yang sama dengan panel admin.
| Kata sandi TIDAK PERNAH ikut tercetak; perintahnya hanya melaporkan
| "terisi" atau "kosong".
*/
Route::middleware(['career.auth', 'career.role:SUPERADMIN'])
    ->prefix('karir/diagnostik')
    ->name('career.diagnostik.')
    ->group(function () {
        /*
         * Pemeriksa jalur SMTP.
         *
         * ?kirim=alamat@contoh.com  — sekalian kirim email uji sungguhan.
         * Sengaja lewat query, bukan POST: yang memakainya sedang menelusuri
         * gangguan lewat bilah alamat, dan menuntut borang di tengah itu cuma
         * menambah langkah tanpa menambah keamanan — gerbangnya sudah di sesi.
         */
        /*
         * Penanda hidup. Dibuka lebih dulu saat halaman /smtp tampil kosong:
         * ia menjawab seketika, jadi ia memisahkan "rutenya belum ter-deploy /
         * sesi ditolak" dari "perintahnya jalan tapi mati di tengah".
         */
        Route::get('/ping', fn () => response(
            "DIAGNOSTIK HIDUP\n"
            .'mesin      : '.gethostname()."\n"
            .'waktu      : '.now()->toDateTimeString()."\n"
            .'app_env    : '.config('app.env')."\n"
            .'mail_host  : '.config('mail.mailers.smtp.host').':'.config('mail.mailers.smtp.port')."\n"
            .'batas eksekusi PHP : '.(ini_get('max_execution_time') ?: '?')." detik\n",
            200,
            ['Content-Type' => 'text/plain; charset=utf-8']
        ))->name('ping');

        Route::get('/smtp', function (\Illuminate\Http\Request $request) {
            /*
             * Pemeriksaan ini memang LAMBAT — itu sifatnya, bukan cacatnya.
             * Setiap port yang diblokir menghabiskan seluruh jatah tunggunya,
             * dan justru lamanya itulah datanya.
             *
             * PHP membunuh skrip yang melewati max_execution_time TANPA menulis
             * apa pun ke badan respons. Halaman kosong tanpa galat — persis
             * gejala yang bikin bingung, karena ia terbaca seperti rute yang
             * tidak ada. Batasnya dilepas di sini saja, sebatas permintaan ini.
             */
            @set_time_limit(0);
            @ini_set('max_execution_time', '0');

            $argumen = [];

            $kirim = trim((string) $request->query('kirim', ''));
            // Divalidasi di sini, bukan diserahkan ke perintahnya: alamat asal
            // ketik akan berakhir sebagai percobaan kirim yang gagal dengan
            // galat yang membingungkan, dan itu justru menambah satu misteri
            // baru di tengah penelusuran misteri lama.
            if ($kirim !== '' && filter_var($kirim, FILTER_VALIDATE_EMAIL)) {
                $argumen['--kirim'] = $kirim;
            }

            // Bawaannya 4 detik, bukan 8. Delapan port × 8 detik bisa menembus
            // batas waktu permintaan Cloud Run; empat detik sudah lebih dari
            // cukup untuk membedakan "terbuka" (puluhan milidetik) dari
            // "dijatuhkan" (selalu mentok di batas).
            $timeout = (int) $request->query('timeout', 4);
            $argumen['--timeout'] = (string) min(20, max(2, $timeout));

            $mulai = microtime(true);
            $galat = null;

            try {
                Artisan::call('karir:cek-smtp', $argumen);
                $keluaran = Artisan::output();
            } catch (\Throwable $e) {
                // Perintah yang melempar tidak boleh berakhir jadi halaman
                // kosong. Yang sedang menelusuri gangguan justru paling butuh
                // membaca lemparannya.
                $keluaran = Artisan::output();
                $galat = get_class($e).': '.$e->getMessage();
            }

            // Kode warna ANSI dibuang — di peramban ia tampil sebagai sampah
            // "[32m" yang menutupi isi yang mau dibaca.
            $keluaran = preg_replace('/\e\[[0-9;]*m/', '', (string) $keluaran);
            $detik = round(microtime(true) - $mulai, 1);

            return response(
                "PEMERIKSAAN JALUR SMTP\n"
                .'dijalankan dari: '.gethostname()."\n"
                .'waktu          : '.now()->toDateTimeString().' ('.config('app.timezone').")\n"
                .'lama proses    : '.$detik." detik\n"
                .str_repeat('=', 64)."\n"
                .($keluaran !== '' ? $keluaran : "(perintah tidak menghasilkan keluaran apa pun)\n")
                .($galat ? "\n".str_repeat('-', 64)."\nGALAT: ".$galat."\n" : ''),
                200,
                ['Content-Type' => 'text/plain; charset=utf-8']
            );
        })->name('smtp');
    });
