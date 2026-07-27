# FreeKliping Todo & Phase Plan

Dokumen ini menjadi pegangan kerja agar implementasi FreeKliping berjalan sesuai PRD dan `technical-flow.md`. Urutan fase dibuat dari pekerjaan dengan risiko paling rendah sampai backend video production yang membutuhkan VPS, queue worker, `yt-dlp`, `ffmpeg`, hardening, dan pertimbangan legal.

Dokumen diperbarui berdasarkan audit repository pada 20 Juli 2026. Audit menemukan bahwa beberapa checklist Phase 1 sudah selesai di kode tetapi belum ditandai, sementara sisa starter-kit Laravel masih aktif dan perlu dibersihkan secara bertahap.

## Prinsip Eksekusi

- Kerjakan fase secara berurutan, kecuali ada alasan teknis yang jelas untuk memecahnya.
- Setiap fase harus punya hasil yang bisa diuji, bukan hanya perubahan kode.
- Backend video sungguhan tidak boleh berjalan di request HTTP utama; proses berat wajib lewat queue worker.
- Karena aplikasi tanpa login, rate limit dan pembatasan durasi harus dibuat sebelum fitur generate sungguhan dibuka publik.
- Risiko legal/ToS YouTube harus diputuskan sebelum rilis publik fitur download video sungguhan.
- Cleanup starter-kit harus dilakukan dari route dan dependency terlebih dahulu, bukan langsung menghapus folder secara massal.
- Setiap tahap cleanup wajib diikuti pengecekan route, TypeScript, lint, build, dan test.

## Status Ringkas

| Fase | Fokus                                               | Status         | Prioritas |
| ---- | --------------------------------------------------- | -------------- | --------- |
| 0    | Keputusan produk, stack, dan deployment             | Selesai        | P0        |
| 1    | Prototype UI tanpa login                            | Selesai        | P0        |
| 1A   | Cleanup starter-kit auth, team, dashboard, settings | Selesai        | P0        |
| 2    | Metadata YouTube sungguhan via yt-dlp               | Selesai        | P0        |
| 3    | Pipeline generate clip sungguhan                    | Selesai        | P0        |
| 4    | VPS deployment siap worker                          | Belum dimulai  | P0        |
| 5    | Hardening anti-abuse dan cleanup output             | Selesai        | P1        |
| 6    | Export polish, subtitle, dan smart crop             | Belum dimulai  | P1/P2     |
| 7    | Storage dan cost optimization                       | Belum dimulai  | P2        |
| 8    | Free public launch dan support transparency         | Sebagian selesai | P1      |
| 9    | YouTube link ke AI clip recommendations             | Sebagian selesai | P0      |
| 10   | Production-safe content workflow                    | Sebagian selesai | P0      |
| 11   | Hybrid local worker                                 | Belum dimulai  | P1        |

## Phase 0 - Product & Technical Decision

Tujuan: mengunci arah sebelum masuk pekerjaan backend yang mahal dan berisiko.

- [x] Tentukan prinsip produk: gratis, tanpa login, tanpa watermark.
- [x] Hapus route login dan register dari pengalaman publik.
- [x] Putuskan deployment target: VPS, bukan shared cPanel.
- [x] Putuskan stack MVP: Laravel + Inertia React tetap dipakai.
- [x] Tentukan batas maksimum durasi klip production: 180 detik.
- [x] Tentukan retention file hasil: 1 jam untuk awal MVP.
- [x] Tentukan apakah fitur backend sungguhan hanya untuk demo terbatas atau rilis publik: demo terbatas/private testing dulu.
- [x] Tinjau risiko legal/ToS YouTube sebelum public launch: wajib dilakukan sebelum rilis publik, belum membuka public launch.

Exit criteria:

- Stack MVP disepakati.
- Target hosting disepakati.
- Batas durasi dan retention disepakati.
- Keputusan legal minimum sudah terdokumentasi.

## Phase 1 - Frontend Prototype

Tujuan: membuktikan UX utama sebelum investasi backend video.

### Status fitur utama

- [x] Tampilan utama mengikuti referensi ClipStudio.
- [x] User bisa paste URL YouTube.
- [x] UI menampilkan state metadata video.
- [x] Timeline visual tersedia.
- [x] Input timecode numerik tersedia dan sinkron dengan timeline.
- [x] Tambahkan input manual menit/detik eksplisit untuk Start dan End.
- [x] Tambahkan quick length preset agar user cepat memilih durasi umum.
- [x] Tombol Generate clip menampilkan feedback progres.
- [x] Tombol Download clip tersedia di prototype.
- [x] Halaman Privacy tersedia.
- [x] Halaman Terms tersedia.

### 1.1 Refactor struktur frontend

- [x] Pecah `resources/js/pages/welcome.tsx` menjadi komponen yang lebih kecil.
- [x] Buat folder `resources/js/features/clip-editor/`.
- [x] Pindahkan type clip editor ke `clip-editor.types.ts`.
- [x] Pindahkan helper timecode dan range ke `clip-editor.utils.ts`.
- [x] Pindahkan daftar ratio, quality, dan preset ke `clip-editor.constants.ts`.
- [x] Pertahankan `welcome.tsx` hanya untuk state utama, request metadata, generate, polling, dan komposisi halaman.

Struktur target:

```text
resources/js/features/clip-editor/
├── components/
│   ├── url-input.tsx
│   ├── empty-state.tsx
│   ├── video-preview.tsx
│   ├── editor-panel.tsx
│   ├── clip-timeline.tsx
│   ├── time-range-controls.tsx
│   ├── manual-time-input.tsx
│   ├── quick-length-presets.tsx
│   ├── export-options.tsx
│   ├── generation-progress.tsx
│   └── clip-result-card.tsx
├── clip-editor.constants.ts
├── clip-editor.types.ts
└── clip-editor.utils.ts
```

### 1.2 Profesionalisasi UI dengan shadcn

- [x] Ganti card manual utama dengan `Card`, `CardHeader`, `CardContent`, dan `CardFooter`.
- [x] Ganti error manual dengan `Alert`, `AlertTitle`, dan `AlertDescription`.
- [x] Gunakan `Progress` untuk status generate.
- [x] Gunakan `Skeleton` untuk metadata loading agar layout tidak meloncat.
- [x] Gunakan `Separator` untuk pembagian section editor.
- [x] Gunakan `Collapsible` untuk input timecode lanjutan.
- [x] Gunakan `Tooltip` untuk icon button yang tidak memiliki label visual.
- [x] Ganti semua elemen `<button>` manual dengan komponen `Button` apabila tidak ada alasan khusus.
- [x] Hapus styling warna hardcoded utama dari komponen.
- [x] Pindahkan warna FreeKliping ke semantic token di `resources/css/app.css`.
- [x] Gunakan class seperti `bg-background`, `bg-card`, `text-foreground`, `text-muted-foreground`, `border-border`, `bg-primary`, dan `text-primary-foreground`.

Komponen tambahan yang direncanakan:

```bash
npx shadcn@latest add progress skeleton separator tooltip collapsible
```

### 1.3 Sederhanakan hierarki editor

- [x] Jadikan input menit/detik sebagai kontrol waktu utama.
- [x] Pindahkan input `MM:SS` atau `HH:MM:SS` ke bagian `Advanced timecode`.
- [x] Hapus informasi Start dan End yang tampil berulang di beberapa lokasi.
- [x] Tampilkan ringkasan tunggal seperti `00:38 → 00:58 · 20 seconds`.
- [x] Letakkan export settings setelah pemilihan range waktu.
- [x] Letakkan Generate clip di `CardFooter` sebagai primary action.
- [x] Pastikan label dan istilah UI konsisten antara bahasa Inggris atau bahasa Indonesia.

### 1.4 Quick length preset

- [x] Tambahkan active state berdasarkan durasi clip saat ini.
- [x] Tambahkan `aria-pressed` pada preset aktif.
- [x] Gunakan label singkat `15s`, `30s`, `60s`, dan `3m`.
- [x] Tambahkan preset `Max` jika durasi video lebih pendek dari batas maksimal.
- [x] Pastikan preset di dekat akhir video tetap menghasilkan durasi yang benar.

### 1.5 Pisahkan error berdasarkan konteks

- [x] Ganti satu state error global menjadi minimal `metadataError` dan `generationError`.
- [x] Tampilkan metadata error di dekat URL input.
- [x] Tampilkan generation error di dalam editor, dekat tombol Generate.
- [x] Pertahankan rename error di dalam result card.
- [x] Tambahkan tombol `Try again` untuk generation error.
- [x] Pastikan failure generate tidak menghapus pilihan range dan export user.

### 1.6 Audit responsive mobile dan tablet

Viewport minimum yang wajib diuji:

```text
320 × 568
375 × 667
390 × 844
768 × 1024
820 × 1180
1024 × 768
1440 × 900
```

Checklist:

- [x] Tidak ada horizontal overflow pada lebar 320 px.
- [x] URL form nyaman dipakai pada mobile.
- [x] Video preview tidak memotong judul atau metadata penting.
- [x] Jumlah label tick timeline dikurangi atau disembunyikan pada mobile.
- [x] Start dan End input tersusun satu kolom pada mobile.
- [x] Start dan End input tersusun dua kolom pada tablet jika ruang cukup.
- [x] Length stat tidak membuat tablet layout terlalu panjang.
- [x] Ratio dan quality menggunakan Select pada mobile dan ToggleGroup pada desktop.
- [x] Quick preset memiliki tap target yang cukup besar.
- [x] Save, Download, dan New clip menjadi full-width pada mobile.
- [x] Primary action Download tampil lebih dominan pada success state.
- [x] Loading overlay tidak keluar dari editor card.
- [x] Long title, long filename, dan error panjang tidak merusak layout.

### 1.7 Keyboard navigation dan accessibility

- [x] Semua tombol, input, link, select, toggle, dan slider memiliki visible focus state.
- [x] Tidak ada tombol manual yang kehilangan `focus-visible` ring.
- [x] Urutan Tab mengikuti flow URL → editor → export → generate → result.
- [x] `Shift+Tab` bekerja tanpa focus trap.
- [x] Range slider dapat digerakkan dengan arrow key.
- [x] Enter pada URL menjalankan Load video.
- [x] Enter pada timecode menerapkan nilai.
- [x] Escape pada timecode membatalkan perubahan.
- [x] Disabled control tidak menerima fokus.
- [x] Container editor menggunakan `aria-busy` saat generating.
- [x] Progress menggunakan `role=status` atau semantic progress yang tepat.
- [x] Progress announcement menggunakan `aria-live=polite`.
- [x] Kontrol editor di belakang loading overlay tidak bisa difokuskan.
- [x] Fokus berpindah ke heading editor setelah metadata berhasil.
- [x] Fokus berpindah ke alert setelah terjadi error.
- [x] Fokus berpindah ke heading `Clip ready` setelah generate selesai.

### 1.8 State coverage UI

- [x] Empty state: URL input tampil, editor belum tampil.
- [x] Metadata loading: input disabled, skeleton atau loading card tampil.
- [x] Metadata error: alert jelas dan tombol load kembali aktif.
- [x] Ready state: preview, timeline, time controls, dan export options tampil.
- [x] Invalid range: pesan dekat time controls dan Generate disabled.
- [x] Clip too long: durasi warning dan batas maksimum terlihat.
- [x] Generating: progress terlihat dan seluruh kontrol terkait disabled.
- [x] Generation error: alert dalam editor dan tombol retry tersedia.
- [x] Success: filename, durasi, ratio, quality, Save, Download, dan New clip tampil.
- [x] Rename saving: tombol Save menampilkan loading.
- [x] Rename error: alert tampil dekat filename.
- [x] Reset: URL, metadata, range, progress, result, dan error kembali bersih.

### 1.9 Test manual range dan input

- [x] Start tidak dapat melewati End.
- [x] End tidak dapat lebih kecil dari Start.
- [x] End tidak dapat melebihi durasi video.
- [x] Seconds tidak dapat melebihi 59.
- [x] Nilai negatif menjadi 0.
- [x] Video kurang dari 15 detik tetap memiliki preset yang valid.
- [x] Timeline, slider, input menit/detik, dan timecode selalu sinkron.
- [x] Generate tidak membuat request ganda saat tombol diklik berulang.
- [x] Polling berhenti saat completed atau failed.
- [x] Polling failure menampilkan error yang bisa dipahami.
- [x] Filename kosong tidak dapat disimpan.
- [x] Rename berhasil memperbarui nama hasil.
- [x] New clip mereset seluruh flow.

### 1.10 Build validation

- [x] Jalankan `npm run format`.
- [x] Jalankan `npm run lint:check`.
- [x] Jalankan `npm run types:check`.
- [x] Jalankan `npm run build`.
- [x] Jalankan `composer run ci:check`.

Exit criteria Phase 1:

- Pengguna memahami flow paste link sampai download tanpa instruksi tambahan.
- Semua state penting terlihat jelas dan muncul dekat tindakan terkait.
- Editor nyaman digunakan pada mobile, tablet, dan desktop.
- Semua kontrol utama dapat digunakan dengan keyboard.
- Tidak ada horizontal overflow pada viewport minimum.
- Build frontend lulus tanpa error TypeScript, lint, atau format.

## Phase 1A - Cleanup Starter-kit yang Tidak Relevan

Tujuan: menghapus subsystem Laravel React starter-kit yang tidak digunakan FreeKliping tanpa merusak route clip, Wayfinder, session, build, atau test.

### 1A.1 Lepaskan route yang tidak relevan

- [x] Hapus import `DashboardController` dari `routes/web.php`.
- [x] Hapus import `TeamInvitationController` dari `routes/web.php`.
- [x] Hapus import `EnsureTeamMembership` dari `routes/web.php`.
- [x] Hapus `require __DIR__.'/auth.php';`.
- [x] Hapus route dashboard dengan prefix `{current_team}`.
- [x] Hapus route accept dan decline invitation.
- [x] Hapus `require __DIR__.'/settings.php';`.
- [x] Jalankan `php artisan route:list`.

### 1A.2 Sederhanakan bootstrap dan shared props

- [x] Hapus `SetTeamUrlDefaults` dari `bootstrap/app.php`.
- [x] Hapus `HandleAppearance` jika FreeKliping diputuskan dark-only.
- [x] Hapus cookie exception `sidebar_state`.
- [x] Hapus cookie exception `appearance` jika appearance setting dihapus.
- [x] Hapus `FortifyServiceProvider` dari `bootstrap/providers.php`.
- [x] Hapus shared prop `auth`, `sidebarOpen`, `currentTeam`, dan `teams`.
- [x] Pertahankan shared prop `name` jika masih digunakan.

### 1A.3 Bersihkan `welcome.tsx`

- [x] Hapus pembacaan `auth` dan `currentTeam`.
- [x] Hapus perhitungan `dashboardUrl`.
- [x] Hapus import route `dashboard`.
- [x] Hapus conditional link Dashboard dari header.
- [x] Pertahankan pembacaan `maxClipLength`.

### 1A.4 Hapus authentication dan Fortify

- [x] Hapus dependency `laravel/fortify`.
- [x] Jalankan `composer dump-autoload`.
- [x] Pastikan tidak ada import Fortify tersisa.

### 1A.5 Hapus dashboard starter-kit

- [x] Hapus semua file dashboard starter-kit.

### 1A.6 Hapus subsystem Teams

- [x] Hapus seluruh subsystem Teams (controllers, models, concerns, enums, data, rules, policies, notifications, requests, factories, tests, types).

### 1A.7 Hapus profile, security, dan settings

- [x] Hapus seluruh subsystem Settings (controllers, requests, pages, layouts, tests).

### 1A.8 Hapus user model, factory, dan seeder

- [x] Hapus `app/Models/User.php`.
- [x] Hapus `database/factories/UserFactory.php`.
- [x] Hapus pembuatan Test User dari `DatabaseSeeder`.
- [x] Pastikan model Clip tidak memiliki dependency ke User.

### 1A.9 Bersihkan migration

- [x] Hapus migration team, membership, invitation, current team, 2FA, dan passkey.
- [x] Hapus tabel `users` dan `password_reset_tokens` jika akun benar-benar tidak digunakan.
- [x] Pertahankan tabel `sessions` jika `SESSION_DRIVER=database`.
- [x] Untuk database existing, buat migration drop yang aman.

### 1A.10 Hapus layout sidebar dan navigation starter-kit

- [x] Hapus semua layout sidebar, navigation, auth layout, dan komponen starter-kit lainnya.
- [x] Hapus hooks yang tidak digunakan (use-appearance, use-mobile, use-initials, dll).
- [x] Hapus UI primitives yang tidak digunakan (sidebar, avatar, breadcrumb, sheet, dll).

### 1A.11 Bersihkan types dan generated Wayfinder

- [x] Hapus type auth, teams, dan navigation yang tidak digunakan.
- [x] Perbarui `resources/js/types/index.ts`.
- [x] Regenerasikan Wayfinder.
- [x] Jangan hapus generated action `ClipController`.
- [x] Pertahankan route home, privacy, terms, dan clip routes.

### 1A.12 Perbarui halaman legal

- [x] Perbarui Privacy Policy sesuai metadata backend, processing, temporary storage, retention, dan IP logging.
- [x] Perbarui Terms sesuai pemrosesan video aktual.
- [x] Tambahkan tanggung jawab user atas hak konten sumber.
- [x] Tambahkan disclaimer kebijakan pihak ketiga dan YouTube.

### 1A.13 Validasi cleanup

- [x] `composer dump-autoload`
- [x] `php artisan route:list`
- [x] `npm run format`
- [x] `npm run lint:check`
- [x] `npm run types:check`
- [x] `npm run build`
- [x] `php artisan test`

Exit criteria Phase 1A:

- Tidak ada route auth, dashboard, settings, team, atau invitation.
- Tidak ada shared prop auth/team/sidebar.
- Fortify sudah dilepas.
- Session tetap berfungsi.
- Semua test clip tetap lulus.
- TypeScript, lint, format, dan build lulus.

## Phase 2 - Real YouTube Metadata via yt-dlp

- [x] Konfigurasi binary dan timeout.
- [x] Parser URL YouTube.
- [x] Endpoint metadata.
- [x] Validasi URL.
- [x] Ambil metadata nyata.
- [x] Tolak video unavailable/restricted.
- [x] Sambungkan UI.
- [x] Tampilkan error konfigurasi.
- [x] Tambahkan test backend metadata.

## Phase 3 - Real Clip Generation Pipeline

- [x] Tabel clips.
- [x] Endpoint submit.
- [x] Queue job.
- [x] yt-dlp download sections.
- [x] ffmpeg precise trim.
- [x] Temporary storage.
- [x] Status polling.
- [x] Signed download URL.
- [x] UI polling.
- [x] Rename file.
- [x] Failure handling.
- [x] Test pipeline.

## Phase 4 - VPS Deployment

- [ ] VPS minimal 2 GB RAM.
- [ ] Nginx, PHP-FPM, Composer, Node.js, database, Redis, Supervisor.
- [ ] Install ffmpeg.
- [ ] Install dan pin yt-dlp.
- [ ] Konfigurasi `.env`.
- [ ] Migration dan build production.
- [ ] Supervisor queue worker.
- [ ] Scheduler cron.
- [ ] Permission storage.
- [ ] End-to-end test.
- [ ] Dokumentasi deploy.

## Phase 5 - Abuse Control, Cleanup, and Reliability

- [x] Rate limit per IP. — DIHAPUS atas keputusan owner; klik generate dan polling status tidak lagi terkena throttle per menit.
- [x] Batasi worker concurrency. — capacity guard menolak klip baru saat queued+processing penuh (`max_concurrent_clips`) atau satu IP punya terlalu banyak in-flight (`max_pending_per_ip`). Hard concurrency sebenarnya diatur oleh jumlah worker Supervisor (Phase 4).
- [x] Cleanup output. — `clips:prune` menghapus file output yang lewat retention, dijadwalkan tiap jam (`withoutOverlapping`).
- [x] Cleanup record lama. — `clips:prune` menghapus record completed/failed yang lebih tua dari `prune_after_hours`.
- [x] Retry terbatas. — `ProcessClip` `$tries = 2` + `$backoff = [10, 30]` (sudah ada, diverifikasi).
- [x] Timeout yt-dlp dan ffmpeg. — `Process::timeout(processing_timeout)` diterapkan di kedua langkah dengan pesan ramah saat timed out (sudah ada, diverifikasi).
- [x] Logging proses. — `Log::info/error` dengan context (clip uuid, step, elapsed_ms, size) di `ProcessClip` dan `ClipProcessor`.
- [x] Pesan overload. — 503 saat global penuh, 429 saat per-IP penuh, keduanya dengan pesan Indonesia yang jelas.
- [x] Evaluasi CAPTCHA. — KEPUTUSAN: ditunda sampai sebelum public launch. Saat ini private testing, rate limit + capacity guard sudah cukup. Saat dibuka publik, pertimbangkan Cloudflare Turnstile / hCaptcha bila abuse meningkat.

### Catatan tambahan Phase 5

- Trusted proxies (`trustProxies(at: '*')`) ditambahkan agar `$request->ip()` dan rate limit akurat di belakang nginx/load balancer.
- Config baru: `max_concurrent_clips`, `max_pending_per_ip`, `prune_after_hours`.
- TODO ops (Phase 4): pasang Supervisor worker dengan `--max-jobs`/`numprocs` sesuai RAM VPS untuk concurrency fisik.

## Phase 6 - Export Polish, Subtitle, and Smart Crop

### 6A - UI Export Polish

- [x] Ratio export.
- [x] Quality export.
- [x] Rename file.
- [x] Input manual menit/detik.
- [x] Rapikan UI dengan shadcn.
- [x] Preview ringkas export.

### 6B - Subtitle YouTube Caption

- [x] Deteksi caption.
- [x] Prioritaskan manual caption.
- [x] Auto caption sebagai fallback.
- [x] Opsi enable/disable.
- [x] Convert caption.
- [x] Burn subtitle.
- [x] State caption unavailable.

### 6C - Smart Crop

- [x] Riset OpenCV, MediaPipe, atau YOLO ringan.
- [x] Center crop dan Smart crop.
- [x] Subject detection built-in.
- [x] Subject detection hook.
- [x] Crop window.
- [x] Smoothing.
- [x] Fallback center crop.
- [x] Benchmark.

### 6D - Whisper

- [x] Evaluasi faster-whisper.
- [x] Tentukan model.
- [x] Queue transcription.
- [x] Generate subtitle audio.
- [x] Cache.
- [x] Benchmark.

## Phase 7 - Storage and Cost Optimization

- [ ] Local storage untuk MVP.
- [ ] Evaluasi R2/S3.
- [ ] Migrasi output ke object storage.
- [ ] Signed object URL.
- [ ] Evaluasi CDN.
- [ ] Monitor biaya.

## Phase 8 - Free Public Launch and Support Transparency

Tujuan: menjaga FreeKliping tetap gratis untuk user publik, tetapi tetap transparan soal biaya server dan punya guardrail agar server tidak tumbang saat ramai.

### 8A - Free Public Model

- [x] Tetapkan FreeKliping sebagai produk gratis untuk MVP publik.
- [x] Pertahankan tanpa login untuk flow utama: paste link/file -> analyze -> generate.
- [x] Jangan pakai paywall wajib untuk basic usage.
- [ ] Tampilkan pesan bahwa queue bisa lambat saat server ramai.
- [x] Siapkan copy produk: gratis, tanpa watermark, didukung saweran opsional.
- [ ] Jangan menjanjikan unlimited cloud processing.

### 8B - Usage Limits for Free Public Access

- [ ] Tetapkan limit clip per IP per hari.
- [ ] Tetapkan max 1 job aktif/pending per IP.
- [ ] Tetapkan batas maksimum durasi source video untuk public mode.
- [ ] Tetapkan batas maksimum durasi output clip untuk public mode.
- [ ] Tetapkan quality default public mode, misalnya 720p.
- [ ] Batasi Smart Crop dan Whisper ketika server penuh.
- [ ] Pastikan capacity guard menolak job baru saat queued+processing melewati batas.
- [ ] Tampilkan pesan overload yang jujur dan ramah: server sedang penuh, coba lagi beberapa menit.

### 8C - Server Cost Transparency

- [x] Buat section homepage `Biaya server bulan ini`.
- [x] Tampilkan target biaya bulanan.
- [x] Tampilkan total saweran bulan berjalan.
- [x] Tampilkan progress terhadap target.
- [x] Pisahkan komponen biaya: server, storage, bandwidth, worker/video processing.
- [x] Buat config agar angka target biaya bisa diubah tanpa edit komponen UI.
- [x] Tampilkan catatan bahwa saweran dipakai untuk menjaga FreeKliping tetap aktif.

### 8D - Sawer and Supporter Ledger

- [x] Tambahkan tombol `Dukung FreeKliping` di homepage.
- [ ] Tambahkan tombol sawer setelah clip berhasil digenerate.
- [x] Simpan link support di config, misalnya Saweria/Trakteer/Ko-fi.
- [x] Buat tabel `donations` atau `supporters`.
- [x] Simpan nama supporter, nominal, pesan singkat, platform, dan waktu.
- [x] Jangan simpan atau tampilkan email, nomor HP, payment id lengkap, atau data pribadi lain.
- [ ] Buat command admin untuk input saweran manual sebagai MVP.
- [ ] Tampilkan recent supporters di homepage.
- [x] Tampilkan supporter bulan berjalan di homepage dari nominal terbesar ke terendah.
- [x] Tampilkan nama sebagai `Anonim` jika donor tidak ingin ditampilkan.

### 8E - Donation Webhook Automation

- [ ] Riset format webhook Saweria dari dashboard akun owner.
- [ ] Buat endpoint webhook dengan token/secret validation.
- [ ] Validasi event sebelum masuk ledger.
- [ ] Cegah duplicate donation berdasarkan provider event id jika tersedia.
- [ ] Simpan payload mentah secara terbatas hanya untuk debugging jika aman.
- [ ] Tambahkan test webhook success, invalid token, duplicate event, dan anonymous donor.
- [ ] Jangan aktifkan webhook production sebelum format payload final diverifikasi.

### 8F - Public-First Login Decision

- [ ] Dokumentasikan keputusan: login/register tidak wajib untuk MVP publik.
- [ ] Login hanya dipertimbangkan nanti untuk history clip, quota lebih tinggi, donor benefit, atau abuse control.
- [ ] Jangan mengembalikan route auth ke pengalaman publik sebelum ada keputusan produk baru.
- [ ] Jika login ditambahkan nanti, jadikan opsional dan bukan blocker untuk basic usage.

### 8G - Maintenance and Launch Readiness

- [ ] Privacy Policy aktual.
- [ ] Terms aktual.
- [ ] Disclaimer hak konten.
- [ ] Batas penggunaan publik.
- [ ] Legal review.
- [ ] Maintenance/status page.
- [ ] Monitoring queue pending, processing, failed jobs, CPU/RAM, dan disk usage.
- [ ] Runbook ketika server overload: kurangi worker, pause job baru, prune output, dan tampilkan status.

## Phase 9 - YouTube Link to AI Clip Recommendations

Tujuan: menjadikan fitur utama FreeKliping sebagai `paste YouTube link -> muncul beberapa rekomendasi klip`, sebelum user memilih klip mana yang akan dirender.

### 9A - Transcript-first Analysis Flow

- [x] User paste link YouTube.
- [x] Sistem ambil metadata video.
- [x] Sistem ambil transcript/caption jika tersedia.
- [x] Jika caption YouTube tidak tersedia, arahkan ke Phase 6D Whisper.
- [x] Jangan langsung render video setelah paste link.
- [x] Pecah transcript menjadi kandidat segmen 20-90 detik.
- [x] Pastikan setiap kandidat punya konteks cukup agar tidak menyesatkan.
- [x] Simpan kandidat sementara untuk preview dan pemilihan user.

### 9B - Viral Moment Scoring

- [x] Buat scoring hook 3 detik pertama.
- [x] Buat scoring emosi: penasaran, marah, setuju, terkejut, ingin debat.
- [x] Buat scoring kategori: kontroversial, argumen kuat, lucu/sarkastis, emosional, solusi Indonesia.
- [x] Buat scoring pacing berdasarkan kepadatan kata dan jeda.
- [x] Buat scoring standalone context agar clip tetap bisa dipahami tanpa video lengkap.
- [x] Hindari kandidat yang kehilangan konteks atau mengubah maksud pembicara.
- [x] Outputkan skor total 0-100.

### 9C - Recommendation Gallery UI

- [x] Tampilkan daftar rekomendasi klip setelah transcript dianalisis.
- [x] Untuk setiap rekomendasi tampilkan timestamp awal dan akhir.
- [x] Tampilkan judul/hook klip.
- [x] Tampilkan alasan bagian tersebut berpotensi viral.
- [x] Tampilkan target emosi audiens.
- [x] Tampilkan saran teks pembuka di layar.
- [x] Tampilkan caption media sosial.
- [x] Tambahkan tombol `Generate 9:16`, `Generate 1:1`, dan `Generate 16:9`.
- [x] User bisa edit timestamp sebelum render.
- [ ] User bisa pilih beberapa rekomendasi untuk batch render.

### 9D - Backend Recommendation API

- [x] Buat endpoint analyze video.
- [x] Buat tabel atau struktur penyimpanan analysis session.
- [x] Buat status polling analysis: queued, processing, completed, failed.
- [x] Jalankan analysis lewat queue, bukan request HTTP utama.
- [x] Cache hasil analysis per video agar paste link yang sama tidak diproses ulang penuh.
- [x] Batasi durasi video yang bisa dianalisis pada MVP.
- [x] Tambahkan error state untuk caption unavailable, transcript failed, dan video terlalu panjang.
- [x] Tambahkan test backend analysis flow.

Exit criteria Phase 9:

- User paste link YouTube dan mendapat beberapa rekomendasi klip tanpa render video terlebih dahulu.
- Setiap rekomendasi punya timestamp, hook, alasan viral, target emosi, dan caption.
- User bisa memilih rekomendasi lalu generate clip dari rekomendasi tersebut.
- Hasil rekomendasi tidak mengambil potongan yang menyesatkan atau kehilangan konteks penting.

## Phase 10 - Production-safe Content Workflow

Tujuan: tetap mempertahankan fitur paste link YouTube, tetapi menurunkan risiko legal/ToS sebelum public production.

- [ ] Jadikan upload file milik user sebagai flow production paling aman.
- [x] Untuk YouTube URL, tampilkan konfirmasi bahwa user punya hak/izin memproses konten.
- [x] Pisahkan mode `analysis only` dan `render/export`.
- [x] Jangan memosisikan produk sebagai YouTube downloader.
- [x] Update Terms dengan tanggung jawab user atas hak konten sumber.
- [x] Update Privacy dengan penjelasan metadata, transcript, IP, output sementara, dan retention.
- [x] Tambahkan retention/delete otomatis untuk analysis dan output.
- [x] Tambahkan DMCA/takedown contact sebelum public launch.
- [ ] Evaluasi YouTube OAuth untuk channel milik user sendiri.
- [x] Batasi render server-side untuk konten yang user upload sendiri atau user konfirmasi punya hak.
- [ ] Catat keputusan legal final sebelum launch publik.

Exit criteria Phase 10:

- Public production punya jalur upload file yang aman.
- YouTube link tetap ada, tetapi render/export dilindungi konfirmasi hak konten dan Terms yang jelas.
- Produk tidak dipasarkan sebagai downloader video publik.

## Phase 11 - Hybrid Local Worker

Tujuan: memberi power mode seperti aplikasi local clipping, menurunkan biaya server, dan tetap mempertahankan web dashboard FreeKliping.

### 11A - Local Worker MVP

- [x] Tentukan bentuk local worker: CLI-compatible manifest dulu, bisa dinaikkan menjadi desktop/background app.
- [ ] Tentukan packaging awal: Docker/local worker CLI sebelum desktop app.
- [x] Bundle atau auto-detect FFmpeg sebagai requirement manifest worker.
- [x] Bundle atau auto-detect yt-dlp sebagai requirement manifest worker.
- [ ] Worker berjalan di komputer user untuk download, trim, crop, subtitle, dan render.
- [x] Web app mengirim job instruction ke worker lokal lewat manifest.
- [x] Worker mengirim progress dan output path kembali ke web app lewat callback token.
- [ ] Jika user memilih sync, output bisa diupload ke server.
- [x] Jika tidak sync, output tetap berada di komputer user sebagai `local_output_path`.

### 11B - Local Link Processing

- [x] Support YouTube link di local worker manifest.
- [ ] Evaluasi TikTok dan Instagram link hanya untuk local worker.
- [x] Tambahkan disclaimer local mode: user bertanggung jawab atas hak dan kepatuhan platform.
- [x] Simpan credential/cookie hanya lokal jika suatu platform membutuhkan login.
- [x] Jangan kirim cookie user ke server.

### 11C - Local AI Pipeline

- [ ] Evaluasi faster-whisper local.
- [ ] Evaluasi model kecil untuk device rendah.
- [ ] Gunakan transcript local untuk scoring rekomendasi.
- [ ] Cache transcript lokal agar video sama tidak diproses ulang.
- [ ] Benchmark CPU-only dan GPU jika tersedia.

### 11D - Free Local Mode Positioning

- [ ] Dokumentasikan Local Mode sebagai opsi gratis untuk pemakaian berat.
- [ ] Jelaskan bahwa proses berat berjalan di komputer user, bukan server FreeKliping.
- [ ] Tampilkan requirement lokal: FFmpeg, yt-dlp, Python, faster-whisper, dan OpenCV jika smart crop dipakai.
- [ ] Pisahkan limit Cloud Free dan Local Free.
- [ ] Pastikan user paham output local tetap tersimpan di komputer sendiri jika sync tidak aktif.

Exit criteria Phase 11:

- User bisa menjalankan worker lokal dan memproses link/file di komputer sendiri.
- Web dashboard tetap menjadi tempat memilih rekomendasi dan memantau progress.
- Biaya render/transcription server turun karena proses berat pindah ke device user.

## Urutan Kerja Terdekat

1. Mulai Phase 8A-8C: free public model, batas penggunaan, dan section transparansi biaya server di homepage.
2. Buat ledger saweran manual dulu sebelum webhook otomatis.
3. Tambahkan tombol `Dukung FreeKliping` di homepage dan success state.
4. Perbarui Terms/Privacy untuk flow paste link, transcript, rekomendasi, output sementara, dan saweran opsional.
5. Siapkan monitoring minimal untuk queue, failed jobs, CPU/RAM, dan disk usage.
6. Catat keputusan legal final sebelum launch publik.
7. Setelah public flow stabil, lanjutkan Phase 11 Local Mode untuk pemakaian berat tanpa membebani server.

## Catatan Keputusan Saat Ini

- Stack MVP: Laravel + Inertia React.
- UI: shadcn/ui style `new-york`.
- Queue production: Redis.
- Queue local/dev: database queue.
- Hosting: VPS.
- Storage awal: local disk.
- Storage lanjutan: S3-compatible/R2.
- Metadata: yt-dlp.
- Generate: queued yt-dlp + ffmpeg.
- Download: signed URL.
- Login/register: tidak digunakan.
- Model produk: gratis/public-first, didukung saweran opsional dan transparansi biaya server.
- Monetisasi wajib/paywall: tidak dipakai untuk MVP.
- Donor benefit: boleh dipertimbangkan nanti, tetapi tidak boleh mengunci basic usage.
- Crop awal: center crop.
- Subtitle awal: YouTube caption.
- Rekomendasi klip: paste link YouTube -> transcript -> AI/rule scoring -> gallery rekomendasi -> render setelah user memilih.
- Production-safe default: upload file milik user sebagai flow paling aman.
- YouTube URL: tetap dipertahankan, tetapi perlu konfirmasi hak konten sebelum render/export public production.
- Local worker: direkomendasikan sebagai power mode jangka menengah, bukan pengganti web app.
- Smart crop dan Whisper: advanced feature.
- Privacy dan Terms harus diperbarui.
