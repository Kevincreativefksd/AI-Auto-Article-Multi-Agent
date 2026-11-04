# Dokumentasi Teknis: Visual Content Agent (v2.3.0)

## 1. Ringkasan

Satu agen baru yang membuat gambar editorial 16:9 (1920 x 1080) per artikel. Pembagian tugas:

| Pihak | Tugas |
|---|---|
| Gemini (`AAA_API::call_gemini`, model dan API key yang sudah ada) | Menyusun data visual (JSON) dan metadata SEO gambar |
| `AAA_Visual_Agent` | Konteks artikel, prompt, validasi JSON, fallback, Media Library, tautan ke artikel |
| `AAA_Visual_Renderer` | Menggambar dan meng-encode gambar secara lokal (GD, cadangan Imagick) |

Tidak dipakai: image generation, DALL-E, Gemini Image, API key tambahan, gambar stok, URL gambar eksternal, browser headless.

## 2. Berkas

| Berkas | Status | Isi |
|---|---|---|
| `class-aaa-visual-agent.php` | baru | `AAA_Visual_Agent` |
| `class-aaa-visual-renderer.php` | baru | `AAA_Visual_Renderer::render_visual_image($visual_data)` |
| `class-aaa-visual-admin.php` | baru | Menu dan Settings API |
| `fonts/` | baru | Liberation Sans Bold dan Regular, `OFL.txt` |
| `tests/visual-selftest.php` | baru | Uji mandiri (WP-CLI) |
| `class-aaa-cron.php` | diubah minimal | `case 'visual_agent'`, helper `after_editing_status()` |
| `class-aaa-agents.php` | diubah minimal | 2 baris di Quality Gate |
| `class-aaa-admin.php` | diubah minimal | 1 baris teks status di JS |
| `ai-auto-article.php` | diubah minimal | versi 2.3.0, 3 berkas baru dimuat, `AAA_Visual_Admin::init()` |
| `class-aaa-api.php`, `class-aaa-db.php`, `class-aaa-humanizer.php` | tidak berubah | |

Prompt agen lama tidak disentuh.

## 3. Alur dan status antrean

```
pending > agent_2 > agent_3 > [humanizer] > visual_agent > quality_gate > completed
```

- Jika Humanizer nonaktif atau gagal: `agent_3 > visual_agent` (hasil SEO Editor dipakai, perilaku lama).
- Jika Visual Content Agent nonaktif: `... > quality_gate` (identik dengan v2.2.0).
- Tugas lama yang sedang berjalan tetap kompatibel: tidak ada skema baru, kunci `agent_data.visual` hanya ditambahkan.

Status `visual_agent` dikerjakan per sub-tahap. Satu putaran antrean = satu sub-tahap, sehingga satu request tidak panjang:

| `visual.stage` | Pekerjaan | Berikutnya |
|---|---|---|
| `plan` | Satu panggilan Gemini, parse + validasi JSON | `render` (valid), `repair` (tidak valid), `plan` lagi (error API pertama), `render` dengan fallback (error API kedua) |
| `repair` | Satu kali perbaikan JSON oleh Gemini | `render` (valid atau fallback PHP) |
| `render` | Render lokal + simpan ke Media Library | `done` lalu status `quality_gate` |

Kegagalan di sub-tahap mana pun berakhir di `quality_gate`, bukan `failed`.

### Pencegahan pemrosesan ganda
- Kunci per tugas di tabel `wp_options` (`INSERT IGNORE`, pola yang sama dengan `WP_Upgrader::create_lock`). Kunci basi (240 detik) diambil alih secara atomik.
- `process()` mengembalikan `null` bila kunci dipegang request lain; cron tidak menulis apa pun.
- Sebelum render, penghitung `render_attempts` disimpan ke database. Bila PHP mati total (mis. kehabisan memori), putaran berikutnya hanya mencoba satu kali lagi lalu melewati gambar, bukan berulang tanpa akhir.
- Attachment diberi meta `_aaa_visual_task_id`; bila sudah ada untuk tugas itu, dipakai ulang (tidak ada attachment ganda).

### Mengapa gambar dibuat sebelum artikel ada
Artikel baru dibuat oleh Quality Gate (`wp_insert_post`), sedangkan urutan yang diminta menempatkan Visual Content Agent sebelum Quality Gate. Karena itu attachment dibuat dulu dengan induk 0, lalu Quality Gate memanggil:
- `AAA_Visual_Agent::maybe_prepend_image($html, $agent_data)` sebelum `wp_insert_post` (menyisipkan `<figure>` di awal isi),
- `AAA_Visual_Agent::finalize_post($post_id, $agent_data)` sesudahnya (mengisi `post_parent` attachment dan `set_post_thumbnail`).

Keduanya tidak pernah melempar error dan mengembalikan HTML asli bila gagal.

## 4. Data tugas (`agent_data.visual`)

```json
{
  "stage": "done",
  "status": "ok | ok_over_limit | failed | skipped",
  "note": "teks diagnosis",
  "attempts": 1,
  "render_attempts": 1,
  "plan_source": "gemini | gemini_repair | fallback",
  "ctx": { "title": "...", "category": "...", "brand": "...", "desc": "...", "bg": "#F4F2EE", "fg": "#171717", "accent": "#B92E32", "format": "auto", "max_kb": 200 },
  "plan": { "...JSON visual tervalidasi (struktur di bagian 5)..." },
  "attachment_id": 123,
  "file": { "format": "webp", "bytes": 38211, "quality": 90, "width": 1920, "height": 1080, "optimized": true, "backend": "gd" }
}
```
Hasil Gemini mentah hanya disimpan sementara (`raw`, maks 6000 karakter) saat sub-tahap `repair`, lalu dibuang. Cache: bila `plan` sudah ada, Gemini tidak dipanggil lagi untuk tugas itu.

## 5. JSON Gemini dan validasi

Prompt internal memakai teks yang Anda tentukan, ditambah data pengaturan (judul, kategori, brand, deskripsi, warna), cuplikan artikel (1800 karakter, ditandai sebagai data bukan instruksi), struktur JSON, dan batas panjang. Struktur:

```json
{
  "brand_name": "ZONA EKONOMI",
  "category_label": "ANALISIS PASAR",
  "article_title": "...",
  "brand_description": "Ruang Literasi Ekonomi",
  "accent_color": "#B92E32",
  "background_color": "#F4F2EE",
  "text_color": "#171717",
  "image": { "width": 1920, "height": 1080, "format": "webp" },
  "seo": { "title": "...", "description": "...", "alt_text": "...", "caption": "..." }
}
```

Aturan validasi (`validate_plan`):
- Hanya field yang dikenal dibaca; field lain diabaikan dan tidak pernah menjadi instruksi renderer.
- Dianggap tidak valid: bukan objek, tidak ada objek `seo`, atau keempat field `seo` kosong. Tidak valid memicu satu kali perbaikan oleh Gemini, lalu fallback PHP.
- Field `seo` yang kosong sebagian diisi dari fallback. Batas: title 120, description 300, alt_text 125, caption 160 karakter; tag dibuang; spasi dirapikan; dipotong di batas kata.
- **Judul** selalu judul asli artikel. **Nama brand dan deskripsi brand** selalu dari pengaturan. **Warna** selalu dari pengaturan (divalidasi HEX `#RGB`/`#RRGGBB` saat disimpan dan dibaca); nilai warna dari Gemini diabaikan.
- **Kategori**: kategori yang dipilih saat membuat artikel dipakai persis (huruf kapital, maks 32 karakter). Label dari Gemini hanya dipakai bila artikel tidak punya kategori tersimpan; bila keduanya kosong, "ARTIKEL".

Sumber judul: H1 pada HTML final (hasil Humanizer bila dipakai, jika tidak SEO Editor). Bila tidak ada H1, `ucfirst(keyword)`, sama dengan judul post yang dibuat Quality Gate. Maksimal 160 karakter (dipotong di batas kata).

## 6. Renderer

`AAA_Visual_Renderer::render_visual_image($visual_data)` mengembalikan `['binary','mime','ext','format','quality','bytes','width','height','optimized','backend','note']` atau `WP_Error`.

Tata letak (1920 x 1080, margin 120 px, latar solid tanpa transparansi), mengikuti gambar referensi:
1. Nama brand (huruf kapital, letter spacing, tebal) di kiri atas, garis tipis di bawahnya.
2. Garis aksen merah pendek, lalu label kategori (huruf kapital, letter spacing).
3. Judul tebal, ukuran otomatis 136 px turun sampai 44 px (langkah 4), dibungkus per kata, kata yang lebih lebar dari area dipecah per huruf; blok judul dan garis aksen merah di bawahnya diposisikan di tengah zona judul.
4. Garis tipis dan deskripsi brand (font reguler) di bagian bawah.

Tidak ada teks lain (tanpa nomor halaman, tanpa ringkasan, tanpa "Geser"). Warna garis tipis dihitung otomatis (campuran 50% warna teks dan latar). Bila kontras teks/latar di bawah 3:1, warna bawaan dipakai.

Mesin tata letak terpisah dari mesin gambar: tata letak menghasilkan daftar primitif (kotak dan teks) memakai fungsi ukur, lalu digambar oleh GD atau Imagick.

GD:
- Kanvas TrueColor, font TTF lokal. Ukuran `imagettftext` adalah point; konversi px ke point dikalibrasi sekali per request dengan mengukur tinggi huruf "H" (96 dpi atau 72 dpi), sehingga hasil tidak bergantung pada build libgd.
- Letter spacing digambar per huruf; posisi dihitung dari selisih lebar (awalan + huruf) - (huruf).
- Teks dibersihkan dari karakter di luar jangkauan glyph font agar tidak muncul kotak kosong.

Pengaman server: pemeriksaan memori bebas (>= 40 MB) sebelum render; tidak mengubah konfigurasi PHP; satu kanvas di memori, dihancurkan segera setelah encode.

Optimasi: render sekali, encode ke buffer memori, turunkan kualitas bertahap sampai <= batas.
- WebP: 90, 82, 74, 66, 58, 50, 42. JPEG (progresif): 90, 85, 80, 75, 70, 65, 60, 55.
- Resolusi tidak diturunkan. Bila batas tidak tercapai di kualitas terendah, hasil terkecil disimpan dan dicatat (`status: ok_over_limit`, catatan di `note`).
- WebP dipakai bila GD mendukung `imagewebp` dan WordPress mengizinkan `image/webp`; jika tidak atau gagal, JPEG. Gagal JPEG dicatat sebagai error dan gambar dilewati. PNG tidak dipakai.
- Karena hasil encode hanya puluhan KB, ditampung di memori; tidak ada file sementara. Berkas final ditulis satu kali setelah optimasi selesai.
- Imagick hanya dipakai bila GD (dengan FreeType) tidak tersedia.

## 7. Media Library dan metadata

1. Berkas ditulis ke `wp_upload_dir()` (jalur dicek berada di dalam direktori uploads), nama via `wp_unique_filename`. Nama dari judul: huruf kecil, tanda hubung, tanpa karakter khusus, maks ~70 karakter (contoh: `iran-buka-peluang-negosiasi-dengan-as.webp`); judul non-Latin memakai `visual-editorial-{id tugas}`.
2. `wp_insert_attachment()` dengan data di-`wp_slash`: `post_title` = Image Title, `post_content` = Description, `post_excerpt` = Caption.
3. `wp_generate_attachment_metadata()` dan `wp_update_attachment_metadata()`.
4. Alt Text: `update_post_meta($id, '_wp_attachment_image_alt', ...)`.
5. Pemeriksaan ulang: bila Title/Description/Caption/Alt kosong setelah disimpan, diperbaiki satu kali.
6. Meta penanda: `_aaa_visual_task_id`, `_aaa_visual_agent`.

Penyisipan di awal artikel: `wp_get_attachment_image($id, 'large', ...)` (alt dan srcset otomatis) dibungkus `<figure class="aaa-visual-figure ...">` dengan `figcaption` dari Caption. Tidak disisipkan bila `wp-image-{id}` atau `aaa-visual-figure` sudah ada.

## 8. Pengaturan

Option `aaa_visual_settings` (array) lewat Settings API (`register_setting` + `settings_fields`); nonce dan capability `manage_options` ditangani `options.php`, dan callback sanitasi memeriksa `current_user_can('manage_options')`. Output di-escape (`esc_attr`, `esc_html`). Pengaturan lama tidak diubah.

| Kunci | Bawaan |
|---|---|
| `enabled` | `1` |
| `brand_name` | kosong (memakai `aaa_brand_name`, lalu nama situs) |
| `brand_description` | Ruang Literasi Ekonomi |
| `bg_color`, `text_color`, `accent_color` | `#F4F2EE`, `#171717`, `#B92E32` |
| `featured`, `insert` | `1`, `1` |
| `format` | `auto` (`webp`, `jpeg`) |
| `max_kb` | `200` (rentang 50 sampai 2000) |

Rasio 16:9 dan resolusi 1920 x 1080 tetap (tampil sebagai keterangan).

## 9. Penanganan kegagalan

| Kondisi | Perilaku |
|---|---|
| Agen dinonaktifkan | Dilewati, `status: skipped` |
| API Key kosong / error API / timeout Gemini | Satu percobaan ulang pada putaran berikut, lalu fallback PHP (judul, kategori, deskripsi brand, metadata SEO dari PHP); gambar tetap dibuat |
| JSON tidak valid | Satu kali perbaikan Gemini, lalu fallback PHP |
| Judul kosong | `ucfirst(keyword)` |
| Kategori kosong | Label Gemini, atau "ARTIKEL" |
| Brand kosong | `aaa_brand_name`, lalu nama situs, lalu "Zona Ekonomi" |
| WebP gagal / tidak didukung | JPEG otomatis |
| JPEG gagal | Dicatat, gambar dilewati |
| GD dan Imagick tidak ada | Dicatat, gambar dilewati |
| Memori tidak cukup | Dicatat, gambar dilewati |
| Gagal menulis / insert attachment | Berkas dihapus, dicatat, gambar dilewati |
| Pengecualian tak terduga | Ditangkap (`\Throwable`), gambar dilewati |

Pada semua kasus di atas artikel tetap menuju Quality Gate dan dipublikasikan (Auto Publish) atau disimpan sebagai Draft sesuai pengaturan.

## 10. Catatan perilaku

- Tombol "Paksa Eksekusi 1 Tahap Sekarang" menjalankan satu sub-tahap Visual Content Agent (plan, repair, atau render).
- Menambah 1 panggilan Gemini per artikel (2 bila perbaikan JSON diperlukan) dan 2 sampai 3 putaran antrean.
- `AAA_API::call_gemini` dipakai apa adanya. Perilaku lamanya (`sslverify => false`, API key di URL, timeout 60 detik) tidak diubah karena di luar lingkup fitur ini; lihat "Batasan yang diketahui" di README.
- Font gambar Liberation Sans (metrik mirip Arial) menggantikan Roboto pada referensi, karena Roboto tidak tersedia saat pengembangan.
