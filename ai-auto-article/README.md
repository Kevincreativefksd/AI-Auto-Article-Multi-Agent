# AI Auto Article Gemini Pro

Plugin WordPress untuk membuat artikel SEO secara otomatis dengan Google Gemini lewat alur multi-agent, lengkap dengan tahap **Humanizer** yang menghapus pola tulisan khas AI.
*(English: a WordPress plugin that generates SEO articles through a multi-agent Gemini pipeline, with a Humanizer stage that removes common AI-writing patterns.)*

## Fitur

- Antrean artikel yang berjalan di latar belakang (WP-Cron), sehingga halaman admin tidak menunggu.
- Alur agen: Researcher, Writer, Fact Checker, SEO Editor, **Humanizer**, Quality Gate.
- Konteks brand (nama brand, target audience, tone of voice) dan informasi tambahan per artikel.
- Pilihan kategori, penulis tetap, dan Cluster Post.
- Model Gemini dapat diganti dari dashboard.

## Persyaratan

- WordPress 5.0 atau lebih baru
- PHP 7.4 atau lebih baru (disarankan 8.0+)
- API Key Gemini dari [Google AI Studio](https://aistudio.google.com/)

## Instalasi

1. Pastikan folder plugin bernama `ai-auto-article` (zip dari GitHub biasanya bernama `ai-auto-article-main`, jadi ganti namanya).
2. WordPress: **Plugins > Add New > Upload Plugin**, pilih zip, lalu aktifkan.
3. Buka menu **AI Article Agents**, isi API Key, nama model, dan konteks brand, lalu simpan.
4. Agar antrean berjalan stabil, jadwalkan pemanggilan `wp-cron.php` (lihat panduan).

## Dokumentasi

- [Panduan pengguna](Panduan%20Pengguna%20Plugin%20AI%20Auto%20Article.md)
- [Changelog](CHANGELOG.md)

## Batasan yang diketahui (versi 2.2.0)

Bagian ini jujur menyebut apa yang belum ideal, supaya Anda bisa menilai sebelum memakainya:

- API key dikirim sebagai parameter URL (`?key=`), sehingga dapat tercatat di log server atau proxy. Memakai header `x-goog-api-key` lebih aman.
- Verifikasi SSL pada request ke Google dimatikan (`sslverify => false`).
- Batas waktu request 60 detik tanpa percobaan ulang otomatis. Pada hosting dengan jaringan keluar lambat atau terbatas, error `cURL error 28` (timeout) bisa muncul.
- Model bawaan `gemini-1.5-flash` mungkin sudah tidak tersedia. Ganti ke model aktif di pengaturan plugin bila muncul error "model not found".
- API key disimpan sebagai teks biasa di tabel `wp_options`. Batasi akses admin dan pakai key dengan kuota terbatas.
- Hasil AI dapat memuat kesalahan fakta. Tinjau artikel sebelum diterbitkan.

Kontribusi perbaikan untuk poin-poin di atas sangat diterima.

## Lisensi

[MIT](LICENSE). Aturan Humanizer diadaptasi dari proyek [humanizer](https://github.com/blader/humanizer) (MIT, Siqi Chen); lihat [THIRD-PARTY-LICENSES.md](THIRD-PARTY-LICENSES.md).
