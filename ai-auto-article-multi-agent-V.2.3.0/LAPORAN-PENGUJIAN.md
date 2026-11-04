# Laporan Pengujian Visual Content Agent (v2.3.0)

Tanggal: 2026-11-04

## Pernyataan penting

**Kode PHP belum pernah dijalankan.** Lingkungan tempat plugin ini dikembangkan tidak memiliki PHP maupun WordPress, dan tidak bisa memasangnya (jaringan terkunci). Karena itu `php -l`, pengujian di WordPress, dan pengujian Media Library **belum dilakukan**. Tidak ada butir di bawah yang saya tandai lulus bila belum benar-benar dijalankan.

Yang benar-benar dijalankan:

| Pemeriksaan | Alat | Hasil |
|---|---|---|
| A. Pemeriksaan leksikal + kesetimbangan kurung pada 10 berkas PHP plugin dan 1 skrip uji | Pygments (pengurai PHP) + skrip Python | 0 token error, 0 kurung tidak seimbang. **Ini jauh lebih lemah daripada `php -l`**: tidak mendeteksi kesalahan semantik, nama fungsi salah, atau tipe. |
| B. Pencarian sintaks yang tidak kompatibel PHP 7.4 pada kode baru (`match`, `?->`, `str_contains`, enum, dll.) | `grep` | Tidak ditemukan. |
| C. Simulasi algoritma tata letak, wrap, ukuran font, dan encode WebP | Prototipe Python/Pillow dengan font yang sama (Liberation Sans) | Lihat di bawah. **Ini bukan keluaran PHP**: bukti bahwa rancangan tata letak dan angka-angkanya benar, bukan bukti bahwa kode PHP benar. |
| D. `diff` berkas lama terhadap ZIP asli | `diff` | `class-aaa-api.php`, `class-aaa-db.php`, `class-aaa-humanizer.php` identik. Perubahan pada cron, agents, admin, dan berkas utama hanya sisipan kecil (lihat CHANGELOG). |

Hasil simulasi C (WebP kualitas 90, 1920 x 1080):

| Kasus | Ukuran font | Baris | Ukuran file | Teks melewati margin? |
|---|---|---|---|---|
| Judul pendek (5 kata) | 136 px | 2 | 34 KB | Tidak |
| Judul 1 kata pendek | 136 px | 1 | 19 KB | Tidak |
| Judul panjang (~150 karakter) | 84 px | 4 | 63 KB | Tidak |
| Judul 160 karakter | 72 px | 4 | 62 KB | Tidak |
| Satu kata sangat panjang (dipecah per huruf) | 112 px | 4 | 58 KB | Tidak |
| Huruf beraksen, simbol `&` dan `%` | 116 px | 3 | 48 KB | Tidak |
| Tema warna gelap | 136 px | 2 | 28 KB | Tidak |
| Label kategori dan brand sangat panjang (dipotong elipsis) | 136 px | 2 | 41 KB | Tidak |

Catatan: simulasi pertama menemukan satu celah (kata sangat panjang melewati margin pada prototipe karena belum ada pemecah kata). Logika pemecah kata sudah ada di renderer PHP; prototipe disamakan lalu diuji ulang dan lulus.

Kisaran 19 sampai 63 KB menunjukkan target 200 KB sangat longgar untuk desain datar ini, tetapi angka itu berasal dari encoder libwebp milik Pillow, bukan GD milik PHP di server Anda.

## Status 27 butir pengujian yang diminta

Keterangan: **Belum dijalankan** = butuh PHP/WordPress; skrip `tests/visual-selftest.php` sudah mencakupnya (kolom terakhir) dan harus dijalankan di staging.

| # | Pengujian | Status | Ada di skrip uji |
|---|---|---|---|
| 1 | PHP syntax check seluruh file yang berubah | **Belum dijalankan** (`php -l` tidak tersedia). Hanya pemeriksaan A dan B di atas yang dilakukan | Jalankan `php -l` per berkas |
| 2 | Validasi JSON Gemini | Belum dijalankan | Ya (bagian 2: valid, field tak dikenal, tanpa `seo`, `seo` kosong, pagar Markdown, non-JSON) |
| 3 | Judul artikel pendek | Hanya simulasi Pillow (lulus) | Ya |
| 4 | Judul artikel panjang | Hanya simulasi Pillow (lulus) | Ya |
| 5 | Kategori artikel | Belum dijalankan (kategori manual diutamakan, label Gemini bila kosong) | Ya |
| 6 | Nama brand | Belum dijalankan | Ya |
| 7 | Deskripsi brand | Belum dijalankan | Ya |
| 8 | Rendering gambar 16:9 | Hanya simulasi Pillow 1920 x 1080 (lulus) | Ya (cek dimensi hasil GD) |
| 9 | Pembuatan WebP | Hanya Pillow; GD belum | Ya |
| 10 | Fallback JPEG | Belum dijalankan | Ya (format dipaksa JPEG) |
| 11 | Optimasi ukuran di bawah 200 KB | Hanya Pillow (19 sampai 63 KB) | Ya (termasuk batas 20 KB untuk menguji tangga kualitas) |
| 12 | Pembuatan nama file SEO-friendly | Belum dijalankan | Ya |
| 13 | Penyimpanan ke Media Library | Belum dijalankan | Ya |
| 14 | Pengisian Image Title | Belum dijalankan | Ya |
| 15 | Pengisian Image Description | Belum dijalankan | Ya |
| 16 | Pengisian Alt Text | Belum dijalankan | Ya |
| 17 | Pengisian Caption | Belum dijalankan | Ya |
| 18 | Penetapan Featured Image | Belum dijalankan | Ya |
| 19 | Penyisipan gambar di awal artikel | Belum dijalankan | Ya (termasuk tidak menggandakan) |
| 20 | Pencegahan duplikasi attachment | Belum dijalankan | Ya (lookup meta, kunci, tahap `done`) |
| 21 | Pengujian ketika Imagick tidak tersedia | Belum dijalankan (jalur utama GD tidak memerlukan Imagick) | Tidak otomatis; cukup pada server tanpa Imagick |
| 22 | Pengujian ketika GD tidak tersedia | Belum dijalankan. Jalur Imagick dan jalur "tidak ada renderer" belum pernah dieksekusi | Tidak otomatis; butuh server tanpa GD |
| 23 | Pengujian ketika Gemini gagal | Belum dijalankan | Ya (simulasi error HTTP, JSON rusak lalu diperbaiki) |
| 24 | Pengujian ketika Visual Content Agent dinonaktifkan | Belum dijalankan | Ya |
| 25 | Pengujian task lama | Belum dijalankan. Secara kode: tugas lama tanpa `agent_data.visual` tidak terpengaruh, dan status lama tetap ditangani | Tidak; uji manual dengan tugas lama |
| 26 | Pengujian Auto Publish | Belum dijalankan | Tidak; uji manual end-to-end |
| 27 | Pengujian Draft | Belum dijalankan | Tidak; uji manual end-to-end |

Ringkasan jujur: dari 27 butir, **tidak ada yang lulus melalui eksekusi PHP**. Butir 3, 4, 8, 9, 11 memiliki bukti dari simulasi Pillow saja.

## Cara menjalankan di staging

1. Unggah plugin, aktifkan, isi API Key Gemini seperti biasa.
2. `php -l` untuk setiap berkas `.php` (butir 1).
3. Dari folder WordPress: `wp eval-file wp-content/plugins/ai-auto-article/tests/visual-selftest.php`. Skrip mensimulasikan respons Gemini (tidak memakai kuota), membuat lalu menghapus attachment dan post uji, dan memulihkan pengaturan. Keluaran: PASS/FAIL per pemeriksaan.
4. Uji manual end-to-end (butir 21 sampai 27): buat satu artikel dengan Auto Publish dan satu dengan Draft; periksa Media Library (nama berkas, Title, Description, Alt, Caption), Featured Image, gambar di awal artikel, dan `agent_data.visual` pada tugas. Ulangi dengan Visual Content Agent nonaktif, lalu dengan ekstensi GD dinonaktifkan di staging.

Skrip uji sendiri juga belum pernah dijalankan; bila ada FAIL, itu bisa berasal dari bug di plugin maupun di skrip, mohon laporkan keluarannya.

## Risiko yang paling perlu diperhatikan saat uji pertama

- Kalibrasi satuan font GD (`pt_per_px`) dan posisi huruf ber-letter-spacing adalah bagian yang paling bergantung pada perilaku libgd; periksa tampilan gambar pertama (ukuran font dan jarak antar huruf pada nama brand).
- `imagewebp` dengan keluaran ke buffer (`null`) pada build GD tertentu; bila gagal, plugin otomatis memakai JPEG dan mencatatnya di `agent_data.visual.note`.
- Perilaku tema: bila Featured Image sudah tampil di atas artikel, matikan opsi "Gambar di awal artikel".
