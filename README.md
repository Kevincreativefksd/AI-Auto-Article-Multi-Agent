# ai-auto-article-multi-agent

Plugin WordPress untuk membuat artikel SEO secara otomatis dengan Google Gemini lewat alur multi-agent, lengkap dengan tahap **Humanizer** yang menghapus pola tulisan khas AI.
*(English: a WordPress plugin that generates SEO articles through a multi-agent Gemini pipeline, with a Humanizer stage that removes common AI-writing patterns.)*

## Fitur

- Antrean artikel yang berjalan di latar belakang (WP-Cron), sehingga halaman admin tidak menunggu.
<<<<<<< HEAD
<<<<<<< HEAD
- Alur agen: Researcher, Writer, Fact Checker, SEO Editor, **Humanizer**, **Visual Content Agent**, Quality Gate.
- **Visual Content Agent**: gambar editorial 16:9 otomatis (judul, kategori, brand) yang dirender lokal dengan PHP GD, dilengkapi metadata SEO gambar, Featured Image, dan penyisipan di awal artikel. Tanpa biaya image generation.
=======
- Alur agen: Researcher, Writer, Fact Checker, SEO Editor, **Humanizer**, Quality Gate.
>>>>>>> 10a0a232d5ef32e17dae34b6c3a30733a78c99b4
=======
- Alur agen: Researcher, Writer, Fact Checker, SEO Editor, **Humanizer**, Quality Gate.
>>>>>>> 10a0a232d5ef32e17dae34b6c3a30733a78c99b4
- Konteks brand (nama brand, target audience, tone of voice) dan informasi tambahan per artikel.
- Pilihan kategori, penulis tetap, dan Cluster Post.
- Model Gemini dapat diganti dari dashboard.

## Persyaratan

- WordPress 5.0 atau lebih baru
- PHP 7.4 atau lebih baru (disarankan 8.0+)
<<<<<<< HEAD
<<<<<<< HEAD
- Untuk Visual Content Agent: ekstensi PHP GD dengan dukungan FreeType (atau Imagick). Tanpa keduanya, gambar dilewati dan artikel tetap diproses. WebP otomatis dipakai bila server dan WordPress mendukung.
=======
>>>>>>> 10a0a232d5ef32e17dae34b6c3a30733a78c99b4
=======
>>>>>>> 10a0a232d5ef32e17dae34b6c3a30733a78c99b4
- API Key Gemini dari [Google AI Studio](https://aistudio.google.com/)

## Instalasi

1. Pastikan folder plugin bernama `ai-auto-article` (zip dari GitHub biasanya bernama `ai-auto-article-main`, jadi ganti namanya).
2. WordPress: **Plugins > Add New > Upload Plugin**, pilih zip, lalu aktifkan.
3. Buka menu **AI Article Agents**, isi API Key, nama model, dan konteks brand, lalu simpan.
4. Agar antrean berjalan stabil, jadwalkan pemanggilan `wp-cron.php` (lihat panduan).

## Dokumentasi

- [Panduan pengguna](Panduan%20Pengguna%20Plugin%20AI%20Auto%20Article.md)
<<<<<<< HEAD
<<<<<<< HEAD
- [Dokumentasi teknis Visual Content Agent](DOKUMENTASI-TEKNIS-VISUAL-AGENT.md)
- [Laporan pengujian](LAPORAN-PENGUJIAN.md)
- [Changelog](CHANGELOG.md)

## Batasan yang diketahui (versi 2.3.0)
=======
- [Changelog](CHANGELOG.md)

## Batasan yang diketahui (versi 2.2.0)
>>>>>>> 10a0a232d5ef32e17dae34b6c3a30733a78c99b4
=======
- [Changelog](CHANGELOG.md)

## Batasan yang diketahui (versi 2.2.0)
>>>>>>> 10a0a232d5ef32e17dae34b6c3a30733a78c99b4

Bagian ini jujur menyebut apa yang belum ideal, supaya Anda bisa menilai sebelum memakainya:

- API key dikirim sebagai parameter URL (`?key=`), sehingga dapat tercatat di log server atau proxy. Memakai header `x-goog-api-key` lebih aman.
- Verifikasi SSL pada request ke Google dimatikan (`sslverify => false`).
- Batas waktu request 60 detik tanpa percobaan ulang otomatis. Pada hosting dengan jaringan keluar lambat atau terbatas, error `cURL error 28` (timeout) bisa muncul.
- Model bawaan `gemini-1.5-flash` mungkin sudah tidak tersedia. Ganti ke model aktif di pengaturan plugin bila muncul error "model not found".
- API key disimpan sebagai teks biasa di tabel `wp_options`. Batasi akses admin dan pakai key dengan kuota terbatas.
- Hasil AI dapat memuat kesalahan fakta. Tinjau artikel sebelum diterbitkan.
<<<<<<< HEAD
<<<<<<< HEAD
- Visual Content Agent menambah 1 panggilan Gemini per artikel (2 bila JSON perlu diperbaiki) dan 2 sampai 3 putaran antrean. Panggilan Gemini-nya memakai `AAA_API::call_gemini` yang sama sehingga batasan SSL dan API key di atas berlaku juga di sini.
- Font gambar adalah Liberation Sans (mirip Arial). Karakter di luar Latin (mis. aksara China atau emoji pada judul) dibuang dari gambar agar tidak muncul kotak kosong.
=======
>>>>>>> 10a0a232d5ef32e17dae34b6c3a30733a78c99b4
=======
>>>>>>> 10a0a232d5ef32e17dae34b6c3a30733a78c99b4

Kontribusi perbaikan untuk poin-poin di atas sangat diterima.

## Lisensi

[MIT](LICENSE). Aturan Humanizer diadaptasi dari proyek [humanizer](https://github.com/blader/humanizer) (MIT, Siqi Chen); lihat [THIRD-PARTY-LICENSES.md](THIRD-PARTY-LICENSES.md).
