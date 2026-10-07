# Web Careers — Admin (zona dalam)

Panel tim rekrutmen EVO Group: program & pembukaan, worklist seleksi,
penjadwalan & integrasi CAT/HCLearn, monitoring, talent pool, feedback, master
data, dan hak akses. Situs kandidat (landing, lowongan, daftar/masuk kandidat,
portal lamaran, konfirmasi kehadiran, surat jadwal, isian feedback) adalah
project terpisah: **web-careers-pengguna**.

## Aturan panel

- **Khusus staf.** Login hanya untuk peran `ADMIN`/`SUPERADMIN`; akun kandidat
  ditolak dan diarahkan ke situs kandidat. Reset kata sandi juga khusus staf.
- **Tanpa SEO.** Tidak ada meta sosial, sitemap, robots dinamis, atau gambar OG.
  Setiap jawaban membawa `X-Robots-Tag: noindex, nofollow` (middleware `TanpaIndeks`).
- **Tautan untuk kandidat** di surel (konfirmasi kehadiran, surat jadwal,
  feedback, verifikasi, portal) dibuat `App\Support\Sinkron\TautanPengguna` dan
  menunjuk ke `PENGGUNA_URL`. Tanda tangannya memakai `TAUTAN_KUNCI` yang sama
  dengan project pengguna.
- **CSRF** hanya dikecualikan untuk pemanggil mesin tanpa sesi (Cloud Tasks,
  webhook CAT). Endpoint panel bersesi selalu memeriksa token.

## Sinkron dua zona (Sync Worker)

Panel ini sumber kebenaran; situs kandidat punya database sendiri (publik).

- **Masuk:** aksi kandidat → Outbox publik → Pub/Sub `wc-masuk` → langganan
  `wc-masuk-sinkron` → kotak masuk (`N_WEB_CAREERS_Sinkron_Masuk`) → kode panel
  yang sudah ada (`app/Support/Sinkron/Penangan`), urut per akun, tepat sekali.
- **Keluar:** potret portal kandidat, hasil peristiwa, dokumen, status akun
  (`N_WEB_CAREERS_Sinkron_Keluar`) dan salinan 49 tabel master → database publik.
- **Penggerak:** Cloud Scheduler tiap menit `POST /api/tugas/sinkron` (antrean
  `wc-sinkron`), atau `php artisan sinkron:jalan`. Langganan push opsional:
  `POST /api/pubsub/wc-masuk` (token OIDC Google).
- **Perawatan:** `sinkron:status`, `sinkron:ulang`, `sinkron:potret`,
  `sinkron:salinan`, `sinkron:migrasi-awal` (sekali, saat pindah).

Panduan GCP & rilis: `docs/06-10-2026/01-PANDUAN-SYNC-WORKER.md`.

## Kunci `.env` penting

| Kunci | Keterangan |
|---|---|
| `DB_*` | database admin (`Web_HRIS`) |
| `PENGGUNA_URL` | alamat situs kandidat |
| `TAUTAN_KUNCI`, `SINKRON_KUNCI_RAHASIA` | **sama persis** dengan project pengguna |
| `DB_PENGGUNA_*` | database publik situs kandidat (koneksi `pengguna`) — peran `wc_publik_worker` |
| `PUBSUB_*`, `GCS_BUCKET_KARANTINA`, `GCS_BUCKET_PUBLIK` | Sync Worker (lihat `config/sinkron.php`) |
| `HCLEARN_*`, `WC_PUBLIC_URL`, `WC_CALLBACK_SECRET` | integrasi CAT/HCLearn |
| `SURAT_*` | EVO Mail Server (seluruh surel) |
| `TUGAS_TOKEN`, `GEMBOK_SECRET`, `LOG_VIEWER_SECRET` | setara kredensial — buat baru per lingkungan |

## Menjalankan lokal

```bash
composer install
npm ci
npm run dev        # atau: npm run build
```

## Pengujian

```bash
php vendor/bin/phpunit
```

`phpunit.xml` memaksa SQLite memori — pengujian tidak pernah menyentuh database
dari `.env` (yang bisa saja menunjuk staging/produksi).
