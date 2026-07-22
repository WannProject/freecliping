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
| 5    | Hardening anti-abuse dan cleanup output             | Belum dimulai  | P1        |
| 6    | Export polish, subtitle, dan smart crop             | Belum dimulai  | P1/P2     |
| 7    | Storage dan cost optimization                       | Belum dimulai  | P2        |
| 8    | Monetisasi dan public launch readiness              | Belum dimulai  | P2        |

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
- [ ] Gunakan `Tooltip` untuk icon button yang tidak memiliki label visual.
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
- [ ] Fokus berpindah ke heading editor setelah metadata berhasil.
- [ ] Fokus berpindah ke alert setelah terjadi error.
- [ ] Fokus berpindah ke heading `Clip ready` setelah generate selesai.

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
- [ ] Untuk database existing, buat migration drop yang aman.

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

- [ ] Rate limit per IP.
- [ ] Batasi worker concurrency.
- [ ] Cleanup output.
- [ ] Cleanup record lama.
- [ ] Retry terbatas.
- [ ] Timeout yt-dlp dan ffmpeg.
- [ ] Logging proses.
- [ ] Pesan overload.
- [ ] Evaluasi CAPTCHA.

## Phase 6 - Export Polish, Subtitle, and Smart Crop

### 6A - UI Export Polish

- [x] Ratio export.
- [x] Quality export.
- [x] Rename file.
- [x] Input manual menit/detik.
- [ ] Rapikan UI dengan shadcn.
- [ ] Preview ringkas export.

### 6B - Subtitle YouTube Caption

- [ ] Deteksi caption.
- [ ] Prioritaskan manual caption.
- [ ] Auto caption sebagai fallback.
- [ ] Opsi enable/disable.
- [ ] Convert caption.
- [ ] Burn subtitle.
- [ ] State caption unavailable.

### 6C - Smart Crop

- [ ] Riset OpenCV, MediaPipe, atau YOLO ringan.
- [ ] Center crop dan Smart crop.
- [ ] Subject detection.
- [ ] Crop window.
- [ ] Smoothing.
- [ ] Fallback center crop.
- [ ] Benchmark.

### 6D - Whisper

- [ ] Evaluasi faster-whisper.
- [ ] Tentukan model.
- [ ] Queue transcription.
- [ ] Generate subtitle audio.
- [ ] Cache.
- [ ] Benchmark.

## Phase 7 - Storage and Cost Optimization

- [ ] Local storage untuk MVP.
- [ ] Evaluasi R2/S3.
- [ ] Migrasi output ke object storage.
- [ ] Signed object URL.
- [ ] Evaluasi CDN.
- [ ] Monitor biaya.

## Phase 8 - Monetization and Public Launch Readiness

- [ ] Saweria/donasi.
- [ ] Privacy Policy aktual.
- [ ] Terms aktual.
- [ ] Disclaimer hak konten.
- [ ] Batas penggunaan publik.
- [ ] Legal review.
- [ ] Maintenance/status page.

## Urutan Kerja Terdekat

1. Buat branch Phase 1 dan Phase 1A.
2. Pecah `welcome.tsx`.
3. Pisahkan metadata error dan generation error.
4. Sederhanakan editor dan migrasikan ke semantic shadcn tokens.
5. Audit responsive, keyboard, dan seluruh state.
6. Jalankan build validation.
7. Cleanup starter-kit secara bertahap.
8. Perbarui Privacy dan Terms.
9. Validasi cleanup.
10. Siapkan VPS deployment.
11. Hardening sebelum public use.
12. Subtitle YouTube caption.
13. Smart crop dan Whisper setelah MVP stabil.

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
- Crop awal: center crop.
- Subtitle awal: YouTube caption.
- Smart crop dan Whisper: advanced feature.
- Privacy dan Terms harus diperbarui.
