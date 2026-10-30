# Changelog

Semua perubahan penting pada proyek ini dicatat di sini.
Format mengikuti [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) dan penomoran versi mengikuti [Semantic Versioning](https://semver.org/).

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
