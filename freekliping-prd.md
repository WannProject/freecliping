# FreeKliping — Dokumen Kebutuhan Produk (PRD)

| | |
|---|---|
| **Status** | Draft v1 |
| **Pemilik** | Kurniawan |
| **Domain** | freekliping.com |
| **Dokumen terkait** | Frontend Design Prompt (spek implementasi / prototipe yang sudah dibangun) |

---

## 1. Ringkasan

FreeKliping adalah tool berbasis browser, gratis, tanpa login, yang
memungkinkan siapa saja memotong sebagian klip pendek dari video YouTube
dengan menandai titik awal dan akhir, lalu mengunduh hasilnya. MVP saat ini
berupa prototipe frontend dengan data video yang di-mock; PRD ini mencakup
apa yang sudah tervalidasi di prototipe tersebut sekaligus apa yang
dibutuhkan versi produksi yang sesungguhnya.

## 2. Latar Belakang & Masalah

Kreator dan social media manager sering butuh mengambil momen 15–90 detik
dari video YouTube panjang untuk diunggah ulang sebagai Shorts/Reels/TikTok.
Tool yang sudah bagus melakukan ini (OpusClip, Klap, Vizard) dibangun di
atas AI auto-highlight, mewajibkan akun, dan harganya untuk kebutuhan tim —
kebanyakan fitur buat orang yang sebenarnya sudah tahu persis 20 detik mana
yang mau dipotong dan cuma butuh cara cepat buat motongnya. Belum ada tool
yang cepat, tanpa akun, dan kontrolnya manual yang mengisi celah itu.

## 3. Tujuan

- Membuat pengguna baru bisa dari "tempel link" sampai "klip sudah
  ditandai" dalam waktu kurang dari satu menit, tanpa perlu bikin akun.
- Menjaga tool ini tetap gratis dan hanya dibiayai dari tip sukarela
  (Saweria) — tanpa iklan, tanpa tier berbayar.
- Memvalidasi model interaksi (scrub manual + input timecode numerik)
  sebelum berinvestasi ke infrastruktur pemrosesan video yang sesungguhnya.

## 4. Bukan Tujuan (di MVP)

- Tidak ada auto-clipping berbasis AI, deteksi highlight, atau auto-reframe
- Tidak ada subtitle/caption
- Tidak ada akun, billing, atau fitur kolaborasi tim
- Tidak ada pengambilan atau rendering video sungguhan di fase ini — data
  di-mock sepenuhnya di sisi client; ini prototipe interaksi/UX, bukan
  encoder yang benar-benar berfungsi

## 5. Target Pengguna

- **Utama:** kreator konten independen dan social media manager yang
  mengolah ulang konten YouTube panjang jadi klip pendek
- **Sekunder:** pengguna kasual yang butuh satu klip cepat tanpa install
  software (mis. Premiere, CapCut Desktop) untuk kebutuhan sekali pakai

## 6. User Stories

- Sebagai pengguna, saya ingin menempelkan link YouTube dan langsung bisa
  menandai klip, tanpa perlu daftar akun.
- Sebagai pengguna, saya ingin menyeret secara visual untuk menandai awal
  dan akhir klip, supaya tidak perlu menebak-nebak timestamp.
- Sebagai pengguna, saya juga ingin field timecode numerik yang presisi,
  supaya bisa menyesuaikan sampai hitungan detik.
- Sebagai pengguna, saya ingin ada feedback yang jelas saat klip sedang
  "diproses", supaya saya tahu tool-nya sedang bekerja, bukan macet.
- Sebagai pengguna, saya ingin bisa mengunduh klip dengan satu klik tanpa
  dipaksa daftar akun di akhir prosesnya.
- Sebagai pengguna, saya ingin tahu dari awal bahwa tidak ada biaya, tidak
  ada watermark, dan tidak ada pengumpulan data, supaya saya cukup percaya
  untuk memakai tool ini.

## 7. Kebutuhan Fungsional

| Prioritas | Kebutuhan |
|---|---|
| P0 | Menerima dan memvalidasi URL YouTube (youtube.com, youtu.be, shorts) |
| P0 | Menampilkan metadata video (judul, channel, durasi, thumbnail) |
| P0 | Timeline visual dengan penanda in/out yang bisa diseret |
| P0 | Field timecode numerik in/out, tersinkron dengan timeline visual |
| P0 | Aksi "Generate clip" dengan feedback progres |
| P0 | Mengunduh hasil klip |
| P1 | Titik masuk dukungan/donasi (Saweria) |
| P1 | Halaman Privacy Policy dan Terms of Service |
| P2 (pasca-MVP) | Pengambilan metadata YouTube yang sesungguhnya |
| P2 (pasca-MVP) | Pemotongan dan encoding video di server yang sesungguhnya |
| P2 (pasca-MVP) | Batas panjang klip yang ditegakkan, rate limiting anti-abuse |

## 8. Kebutuhan Non-Fungsional

- Sepenuhnya responsif: desktop-first, tetap nyaman dipakai di tablet dan
  mobile
- Tanpa akun, cookie, atau analytics/tracker pihak ketiga
- Kontrol bisa dinavigasi dengan keyboard; focus state terlihat jelas di
  setiap elemen interaktif
- Setiap elemen interaktif punya state hover, focus, disabled, loading,
  error, empty, dan success
- Performa yang terasa cepat — tidak ada interaksi yang terasa menunggu
  round-trip jaringan di fase MVP, karena memang belum ada jaringan yang
  dipanggil

## 9. Referensi Desain & UX

Kebutuhan visual dan tingkat komponen dijabarkan terpisah di Frontend
Design Prompt, yang sudah diimplementasikan sebagai prototipe yang
berjalan: React 19 + TypeScript + Vite + Tailwind v4 + Framer Motion +
React Router, UI premium bertema gelap, seluruh data di-mock.

## 10. Asumsi & Ketergantungan

- Diasumsikan pengguna memiliki hak untuk memotong dan menyebarluaskan
  video sumbernya — tool ini tidak memberikan hak apa pun yang belum
  dimiliki penggunanya
- Versi nyata (tidak di-mock) bergantung pada backend pemrosesan video
  (mis. pipeline yt-dlp/ffmpeg) dan storage untuk hasil render

## 11. Risiko & Pertanyaan Terbuka

- **Risiko legal/ToS:** mengunduh atau menghosting ulang konten video
  YouTube berpotensi bertentangan dengan Terms of Service YouTube. Ini
  butuh tinjauan legal sebelum backend sungguhan dirilis — inilah alasan
  utama kenapa MVP sengaja tetap frontend-only dan sepenuhnya di-mock
  untuk saat ini.
- **Risiko penyalahgunaan:** backend sungguhan tanpa login tidak punya
  cara alami untuk membatasi atau melacak pemakaian — perlu rencana
  mitigasi (rate limit berbasis IP, CAPTCHA, dll.) sebelum diluncurkan.
- **Pertanyaan terbuka:** apakah FreeKliping akan tetap jadi proyek
  portofolio/demo, atau memang direncanakan punya backend pemrosesan
  sungguhan? Keputusan ini sangat menentukan biaya infrastruktur dan
  eksposur legal, dan sebaiknya diputuskan sebelum masuk Phase 2.
- **Pertanyaan terbuka:** apakah monetisasi lewat tip saja (Saweria) masih
  berkelanjutan begitu biaya server sungguhan (encoding, bandwidth) mulai
  berjalan?

## 12. Rencana Rilis

| Fase | Cakupan | Status |
|---|---|---|
| 1 | Prototipe frontend, sepenuhnya di-mock | Selesai |
| 2 | Pengambilan metadata YouTube sungguhan (oEmbed atau sejenisnya) | Belum dimulai |
| 3 | Backend pemrosesan klip sungguhan, menunggu tinjauan legal | Belum dimulai |

## 13. Metrik Keberhasilan

- Fase MVP: feedback usability kualitatif, waktu sampai klip pertama saat
  testing, completion rate (link ditempel → klip "diunduh")
- Fase pasca-backend: jumlah klip dibuat per minggu, tingkat penggunaan
  berulang, tip Saweria yang diterima