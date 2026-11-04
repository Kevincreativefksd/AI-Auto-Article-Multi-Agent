# Changelog

Semua perubahan penting pada proyek ini dicatat di sini.
Format mengikuti [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) dan penomoran versi mengikuti [Semantic Versioning](https://semver.org/).


## [2.3.0] - 2026-11-03

### Added
- **Visual Content Agent**: agen baru yang membuat gambar editorial 16:9 (1920 x 1080) untuk setiap artikel, dirender lokal dengan PHP GD (Imagick sebagai cadangan). Tanpa image generation, tanpa API key tambahan, tanpa gambar stok, tanpa URL gambar eksternal.
- Status antrean baru `visual_agent`. Urutan alur: SEO Researcher, Content Writer, SEO Editor, Humanizer (jika aktif), **Visual Content Agent**, Quality Gate. Tahap ini berjalan per sub-tahap (`plan`, `repair`, `render`) agar tiap request singkat.
- Gemini (lewat `AAA_API::call_gemini` yang sudah ada) menyusun data visual dan metadata SEO gambar (Image Title, Description, Alt Text, Caption) dalam JSON. JSON divalidasi, diperbaiki satu kali bila rusak, dan memakai fallback PHP bila tetap gagal.
- Penyimpanan ke Media Library (`wp_insert_attachment`, `wp_generate_attachment_metadata`), nama berkas ramah SEO, Featured Image otomatis, dan opsi menyisipkan gambar yang sama di awal artikel tanpa duplikasi.
- Optimasi ukuran bertahap (WebP diutamakan, JPEG sebagai alternatif) menuju batas yang dapat diatur (bawaan 200 KB) tanpa menurunkan resolusi.
- Menu **AI Article Agents > Visual Content Agent** (Settings API) dengan 10 pengaturan, serta panel kemampuan server (GD, WebP, Imagick, font).
- Font lokal Liberation Sans (SIL OFL 1.1) di folder `fonts/`.
- Skrip uji mandiri `tests/visual-selftest.php` (WP-CLI).

### Changed
- `class-aaa-cron.php`: satu `case 'visual_agent'` baru dan satu helper kecil yang mengarahkan tahap sesudah SEO Editor/Humanizer ke `visual_agent` bila agen aktif, jika tidak tetap ke `quality_gate`.
- `class-aaa-agents.php` (Quality Gate): dua pemanggilan satu baris, sebelum dan sesudah `wp_insert_post`, untuk menyisipkan gambar dan menetapkan Featured Image. Logika Quality Gate lainnya tidak berubah.
- `class-aaa-admin.php`: satu baris teks status untuk `visual_agent` pada tabel antrean.

### Compatibility
- Tidak ada perubahan skema database. Hasil agen disimpan di `agent_data.visual` (JSON). Tugas lama tanpa kunci itu tetap berjalan normal.
- Semua pengaturan lama dipertahankan. Pengaturan baru disimpan di satu option terpisah, `aaa_visual_settings`.
- Kegagalan gambar (Gemini, renderer, memori, penyimpanan) tidak pernah menggagalkan artikel.

## [2.2.0] - 2026-10-28

### Added
- **Agent Humanizer**: tahap baru antara SEO Editor dan Quality Gate yang menulis ulang artikel agar tidak terbaca seperti tulisan AI. Aturannya diadaptasi dari proyek [humanizer](https://github.com/blader/humanizer) v3.1.0 (MIT) ke bahasa Indonesia.
- Pengaturan "Aktifkan Humanizer" di panel Konfigurasi (aktif secara bawaan).
- Validasi hasil Humanizer: hasil dibuang bila hyperlink berubah, H1/H2 hilang, atau teks menyusut lebih dari 30%. Dalam kasus itu artikel memakai versi SEO Editor. Tanda pisah (em/en dash) dibersihkan otomatis.
- Kegagalan Humanizer tidak menggagalkan artikel; tahap itu dilewati dan alasannya dicatat di `agent_data.humanizer.note`.

### Changed
- Alur artikel menjadi Researcher, Writer, Fact Checker, SEO Editor, Humanizer, Quality Gate.
- Quality Gate memakai HTML hasil Humanizer bila tersedia, jika tidak memakai hasil SEO Editor.

## [2.1.0] - tanggal tidak tercatat

### Added
- Pemilihan kategori artikel, penyimpanan penulis (author) asli, dan konteks prompt SEO.

## [2.0.0] - tanggal tidak tercatat

### Changed
- Ditulis ulang dari satu file menjadi arsitektur multi-agent berbasis antrean (tabel database + WP-Cron) dengan tahap Researcher, Writer, Fact Checker, SEO Editor, dan Quality Gate.
- Mendukung Cluster Post.

## [1.5.4] - 2026-09-23

### Added
- Kolom **Gemini Model** di dashboard; nilai disimpan di database WordPress dan dipakai otomatis saat membuat artikel. Tidak perlu lagi mengedit file PHP untuk mengganti model.

## [1.5.3] - 2026-06-26

### Changed
- Model diganti ke `gemini-3.5-flash`.
- Batas waktu request 180 detik.

## [1.5.2] - 2026-05-03

### Changed
- Optimasi prompt untuk kualitas dan konsistensi keluaran.

## [1.5.1] - 2026-05-01

### Fixed
- Error "Model not found" dengan menyelaraskan Model ID pada endpoint v1beta (`gemini-3-flash-preview`).
