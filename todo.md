# FreeKliping Todo & Phase Plan

Dokumen ini menjadi pegangan kerja agar implementasi FreeKliping berjalan sesuai PRD dan `technical-flow.md`. Urutan fase dibuat dari yang paling rendah risiko sampai backend video sungguhan yang membutuhkan VPS, queue worker, `yt-dlp`, `ffmpeg`, dan pertimbangan legal.

## Prinsip Eksekusi

- Kerjakan fase secara berurutan, kecuali ada alasan teknis yang jelas untuk memecahnya.
- Setiap fase harus punya hasil yang bisa diuji, bukan hanya perubahan kode.
- Backend video sungguhan tidak boleh berjalan di request HTTP utama; proses berat wajib lewat queue worker.
- Karena aplikasi tanpa login, rate limit dan pembatasan durasi harus dibuat sebelum fitur generate sungguhan dibuka publik.
- Risiko legal/ToS YouTube harus diputuskan sebelum rilis publik fitur download video sungguhan.

## Status Ringkas

| Fase | Fokus | Status | Prioritas |
|---|---|---|---|
| 0 | Keputusan produk, stack, dan deployment | Selesai | P0 |
| 1 | Prototype UI tanpa login | Sebagian besar selesai | P0 |
| 2 | Metadata YouTube sungguhan via yt-dlp | Selesai | P0 |
| 3 | Pipeline generate clip sungguhan | Selesai | P0 |
| 4 | VPS deployment siap worker | Belum dimulai | P0 |
| 5 | Hardening anti-abuse dan cleanup | Belum dimulai | P1 |
| 6 | Export polish, subtitle, dan smart crop | Belum dimulai | P1/P2 |
| 7 | Storage dan cost optimization | Belum dimulai | P2 |
| 8 | Monetisasi dan public launch readiness | Belum dimulai | P2 |

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

- [x] Tampilan utama mengikuti referensi ClipStudio.
- [x] User bisa paste URL YouTube.
- [x] UI menampilkan state metadata video.
- [x] Timeline visual tersedia.
- [x] Input timecode numerik tersedia dan sinkron dengan timeline.
- [ ] Perbaiki UI editor agar lebih profesional memakai komponen shadcn yang tersedia.
- [ ] Tambahkan input manual menit/detik eksplisit untuk Start dan End.
- [ ] Tambahkan quick length preset agar user cepat memilih durasi umum.
- [x] Tombol Generate clip menampilkan feedback progres.
- [x] Tombol Download clip tersedia di prototype.
- [x] Halaman Privacy tersedia.
- [x] Halaman Terms tersedia.
- [ ] Audit responsive mobile/tablet untuk semua state.
- [ ] Audit keyboard navigation dan visible focus state.
- [ ] Uji state error, empty, loading, success di UI.

Exit criteria:

- Pengguna bisa memahami flow dari paste link sampai download tanpa instruksi tambahan.
- Semua state penting terlihat jelas.
- Build frontend lulus tanpa error TypeScript/lint.

## Phase 2 - Real YouTube Metadata via yt-dlp

Tujuan: mengganti metadata mock dengan metadata sungguhan memakai `yt-dlp` terlebih dahulu agar tidak perlu YouTube Data API key di fase awal private testing.

- [x] Tambahkan konfigurasi binary dan timeout metadata `yt-dlp`.
- [x] Buat parser URL YouTube untuk `youtube.com`, `youtu.be`, dan Shorts.
- [x] Buat endpoint metadata.
- [x] Validasi format URL sebelum menjalankan `yt-dlp`.
- [x] Ambil judul, channel, durasi, dan thumbnail dari `yt-dlp --dump-single-json`.
- [x] Tolak video yang tidak ditemukan, private, unavailable, atau restricted.
- [x] Sambungkan UI Load video ke endpoint metadata.
- [x] Tampilkan pesan error yang jelas saat binary `yt-dlp` belum dikonfigurasi.
- [x] Tambahkan test untuk URL valid, URL invalid, video not found, dan response metadata sukses.

Exit criteria:

- UI tidak lagi bergantung pada metadata mock.
- Metadata real muncul dari URL YouTube publik.
- Request invalid ditolak sebelum masuk proses berat.
- Test backend metadata lulus.

## Phase 3 - Real Clip Generation Pipeline

Tujuan: membuat tombol Generate clip benar-benar menghasilkan file MP4.

- [x] Buat tabel `clips` untuk menyimpan job clip.
- [x] Simpan source URL, video ID, start/end seconds, status, progress, error, output path, dan expiry.
- [x] Buat endpoint submit clip.
- [x] Validasi durasi clip tidak melebihi batas maksimum.
- [x] Validasi `end_seconds` tidak melebihi durasi video.
- [x] Dispatch Laravel queued job setelah validasi metadata berhasil.
- [x] Buat worker job untuk menjalankan `yt-dlp`.
- [x] Gunakan `--download-sections` dengan buffer kecil di sekitar start/end.
- [x] Buat tahap `ffmpeg` untuk trim presisi dan encode MP4.
- [x] Simpan hasil ke storage sementara.
- [x] Buat endpoint status polling.
- [x] Buat signed download URL untuk hasil yang sudah selesai.
- [x] Sambungkan UI Generate clip ke endpoint submit dan polling status.
- [x] Ganti dummy download dengan signed URL backend.
- [x] Tambahkan handling failed job dengan pesan error yang bisa tampil di UI.
- [x] Tambahkan test untuk validasi durasi, dispatch job, status response, dan download URL.

Exit criteria:

- Generate clip berjalan async lewat queue, bukan blocking request.
- UI bisa polling status sampai completed atau failed.
- File MP4 hasil bisa diunduh melalui signed URL.
- Jika `yt-dlp` atau `ffmpeg` gagal, UI menampilkan error yang jelas.

## Phase 4 - VPS Deployment

Tujuan: menjalankan aplikasi di environment yang cocok untuk video processing.

- [ ] Siapkan VPS minimal 2 GB RAM.
- [ ] Install Nginx, PHP-FPM, Composer, Node.js, database, Redis, Supervisor.
- [ ] Install `ffmpeg`.
- [ ] Install dan pin versi `yt-dlp`.
- [ ] Konfigurasi `.env` production.
- [ ] Jalankan migration.
- [ ] Jalankan build frontend production.
- [ ] Konfigurasi Supervisor untuk Laravel queue worker.
- [ ] Konfigurasi Laravel scheduler via cron.
- [ ] Pastikan upload/output directory writable.
- [ ] Uji end-to-end dari URL YouTube sampai file download.
- [ ] Dokumentasikan perintah deploy minimal untuk server ini.

Exit criteria:

- Aplikasi bisa diakses dari domain.
- Queue worker tetap hidup setelah restart.
- `yt-dlp` dan `ffmpeg` bisa dieksekusi oleh user aplikasi.
- Clip berhasil dibuat di server production/staging.

## Phase 5 - Abuse Control, Cleanup, and Reliability

Tujuan: mencegah biaya server bocor dan menjaga sistem tetap stabil.

- [ ] Tambahkan rate limit per IP untuk metadata dan generate clip.
- [ ] Batasi concurrency worker, rekomendasi awal: 1 job video sekaligus di VPS kecil.
- [ ] Tambahkan cleanup file hasil sesuai retention window.
- [ ] Tambahkan cleanup job record lama.
- [ ] Tambahkan retry terbatas untuk job gagal.
- [ ] Tambahkan timeout proses `yt-dlp` dan `ffmpeg`.
- [ ] Batasi ukuran/durasi video sumber jika diperlukan.
- [ ] Tambahkan logging durasi job, ukuran output, dan failure reason.
- [ ] Siapkan pesan overload: "coba lagi nanti".
- [ ] Evaluasi CAPTCHA ringan jika abuse mulai muncul.

Exit criteria:

- File lama otomatis terhapus.
- Job berat tidak berjalan tanpa batas.
- Server tetap responsif saat ada job gagal atau lambat.
- Ada log cukup untuk debugging masalah production.

## Phase 6 - Export Polish, Subtitle, and Smart Crop

Tujuan: meningkatkan hasil export agar cocok untuk Short/Reels/TikTok tanpa membuat MVP awal terlalu berat.

### 6A - UI Export Polish (P1)

- [x] Tambahkan pilihan rasio export: Original, 16:9, 9:16, 1:1.
- [x] Tambahkan pilihan kualitas export: Source, 480p, 720p, 1080p.
- [x] Tambahkan rename file setelah clip selesai.
- [ ] Rapikan UI editor dengan komponen shadcn agar lebih profesional dan mudah dipakai.
- [ ] Tambahkan input manual menit/detik untuk menentukan bagian video.
- [ ] Tambahkan preview ringkas pilihan export sebelum generate.

Exit criteria:

- User bisa memilih waktu, rasio, kualitas, dan nama file tanpa kebingungan.
- UI editor nyaman dipakai di desktop dan mobile.
- Build frontend lulus tanpa error TypeScript/lint.

### 6B - Subtitle dari YouTube Caption (P1)

- [ ] Cek ketersediaan subtitle/caption via `yt-dlp`.
- [ ] Prioritaskan manual caption jika tersedia.
- [ ] Gunakan auto caption dari YouTube sebagai fallback jika tersedia.
- [ ] Tambahkan opsi enable/disable subtitle sebelum generate.
- [ ] Convert caption ke format yang bisa dipakai `ffmpeg`.
- [ ] Burn subtitle ke video export jika user memilih subtitle.
- [ ] Tampilkan status jika video tidak punya caption.

Exit criteria:

- Video dengan caption YouTube bisa diexport dengan subtitle.
- Video tanpa caption tetap bisa diproses tanpa error.
- User mendapat pesan jelas saat subtitle tidak tersedia.

### 6C - Smart Crop Subject Detection (P2)

- [ ] Riset pendekatan subject detection yang cocok untuk VPS murah: OpenCV, MediaPipe, atau YOLO ringan.
- [ ] Buat mode export baru: Center crop dan Smart crop.
- [ ] Detect posisi wajah/orang pada sample frame.
- [ ] Hitung crop window untuk rasio 9:16 atau 1:1.
- [ ] Tambahkan smoothing agar crop tidak bergerak patah-patah.
- [ ] Pastikan fallback ke center crop jika subject tidak terdeteksi.
- [ ] Benchmark durasi proses dan penggunaan CPU/RAM di VPS target.

Exit criteria:

- Smart crop hanya aktif jika user memilih mode tersebut.
- Kalau deteksi gagal, hasil tetap aman dengan center crop.
- Beban processing masih masuk akal untuk VPS murah.

### 6D - Auto Subtitle via Whisper (P2)

- [ ] Evaluasi `faster-whisper` atau alternatif transcription ringan.
- [ ] Tentukan model default yang realistis untuk VPS murah.
- [ ] Tambahkan queue step transcription setelah clip source tersedia.
- [ ] Generate subtitle dari audio jika YouTube caption tidak tersedia.
- [ ] Tambahkan cache subtitle per clip/job bila diperlukan.
- [ ] Benchmark waktu proses untuk clip 30s, 60s, dan 180s.

Exit criteria:

- Subtitle otomatis tetap opsional.
- Server tidak overload saat transcription berjalan.
- Ada fallback dan pesan error yang jelas jika transcription gagal.

Catatan keputusan:

- Untuk MVP, gunakan center crop dulu untuk rasio vertical/square.
- Subtitle dari YouTube caption lebih dulu daripada Whisper karena lebih murah dan cepat.
- Smart crop dan Whisper masuk advanced feature setelah flow dasar stabil.

## Phase 7 - Storage and Cost Optimization

Tujuan: menekan biaya setelah penggunaan mulai nyata.

- [ ] Mulai dengan local storage untuk MVP kecil.
- [ ] Evaluasi Cloudflare R2 atau S3-compatible storage saat storage/bandwidth mulai naik.
- [ ] Pindahkan output clip ke object storage jika local disk mulai membatasi.
- [ ] Gunakan signed temporary URL dari object storage.
- [ ] Evaluasi CDN hanya jika download mulai besar.
- [ ] Monitor biaya VPS, storage, bandwidth, dan CPU encoding.

Exit criteria:

- Biaya operasional bisa diprediksi.
- Storage tidak memenuhi disk VPS.
- Download tetap cepat tanpa membebani server web utama.

## Phase 8 - Monetization and Public Launch Readiness

Tujuan: memastikan produk siap dipakai publik tanpa mengorbankan prinsip gratis.

- [ ] Tambahkan link Saweria/donasi yang tidak mengganggu flow utama.
- [ ] Perbarui Privacy Policy sesuai backend sungguhan.
- [ ] Perbarui Terms of Service sesuai risiko pemrosesan video.
- [ ] Tambahkan disclaimer hak penggunaan konten.
- [ ] Tentukan batas penggunaan publik.
- [ ] Lakukan review legal/ToS sebelum promosi publik.
- [ ] Siapkan halaman status sederhana atau pesan maintenance.

Exit criteria:

- Pengguna memahami batasan dan tanggung jawab penggunaan.
- Kebijakan privacy/terms sesuai perilaku aplikasi sebenarnya.
- Biaya server punya jalur pendanaan minimal.

## Urutan Kerja Terdekat

1. Selesaikan UI editor dari Phase 1 dan Phase 6A.
2. Siapkan Phase 4: deploy ke VPS dengan worker.
3. Buka Phase 5 sebelum aplikasi dipakai publik.
4. Implement subtitle dari YouTube caption di Phase 6B.
5. Evaluasi smart crop dan Whisper setelah MVP stabil.

## Catatan Keputusan Saat Ini

- Stack MVP: Laravel + Inertia React.
- Queue production: Redis direkomendasikan.
- Queue local/dev: database queue masih boleh untuk sederhana.
- Hosting: VPS murah lebih cocok daripada shared cPanel.
- Storage awal: local disk.
- Storage lanjutan: S3-compatible, Cloudflare R2 kandidat murah.
- Metadata awal: `yt-dlp`, tanpa YouTube Data API key.
- Generate clip: Laravel queued job menjalankan `yt-dlp` + `ffmpeg`.
- Download hasil: signed URL dari backend.
- Login/register: tidak dipakai untuk pengalaman publik.
- Crop rasio vertical/square awal: center crop.
- Subtitle otomatis awal: ambil dari YouTube caption jika tersedia.
- Smart crop subject detection dan Whisper: advanced feature, bukan MVP awal.
