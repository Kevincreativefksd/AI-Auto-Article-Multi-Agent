# Changelog

## [2.7.1] - 2026-11-23

### Ditambahkan
- **Semantic Keyword Generator untuk Mode Berita** (`AAA_News::generate_keywords()`): satu panggilan Gemini (model Penulis Berita) membaca artikel mentah dan menghasilkan tiga keyword berita yang berbeda, satu per sudut pandang, dengan bahasa jurnalistik dan hanya berdasar fakta artikel mentah. Keyword dibuat dan disimpan sebelum tugas penulisan masuk antrean.
- Fallback aman bila generator gagal atau hasilnya tidak valid (kosong, terlalu pendek atau panjang, hanya label sudut, atau kembar): keyword memakai topik editor atau baris pertama artikel mentah dengan awalan "Fakta utama:", "Dampak:", "Latar belakang:". Kegagalan dicatat lewat `error_log` (saat `WP_DEBUG` aktif) dan di `agent_data` (`keyword_source`, `keyword_note`). Tidak ada fakta baru yang dibuat.

### Diubah
- Kolom `keyword` tugas berita kini berisi keyword semantik, bukan lagi "topik [Label sudut]".
- Judul post WordPress, judul pada Featured Image, dan kata kunci Humanizer memakai keyword tersebut secara identik (satu sumber data). Judul dari JSON Penulis Berita tidak lagi dipakai untuk tugas baru; tag `<h1>` di isi berita dibuang agar judul hanya satu.
- Prompt Penulis Berita yang tersimpan tidak diubah; keyword ditambahkan ke prompt saat berjalan.

### Kompatibilitas
- Tanpa perubahan struktur database. Tugas berita lama (tanpa keyword semantik) tetap berjalan dengan judul dan keyword lama.
Riwayat perubahan plugin **AI Auto Article Gemini Pro (Multi-Agent)**.
Format mengikuti [Keep a Changelog](https://keepachangelog.com/id-ID/1.1.0/). Basis awal: versi 2.3.0.

## [2.7.0] - 2026-11-23

### Ditambahkan
- **Halaman Pengaturan Berita** (kelas baru `AAA_News_Settings`) khusus agent berita: model, prompt, panjang tulisan (kata minimum dan maksimum), struktur (piramida terbalik, kronologis, per topik), pilihan subjudul H2, catatan struktur, dan tiga sudut pandang.
- Placeholder prompt berita baru: `{tone}`, `{length}`, `{structure}`.

### Diubah
- **Gaya bahasa berita kini memakai Tone of Voice** dari halaman utama plugin, diterapkan apa adanya. Kolom "Gaya website" dihapus; opsi lama `aaa_news_style` tidak dipakai lagi. Placeholder `{style}` tetap diterima sebagai alias `{tone}` agar prompt yang sudah disimpan tidak rusak.
- Kartu Penulis Berita dipindahkan dari Pengaturan Agent ke Pengaturan Berita. Pengaturan model, prompt, dan sudut pandang yang sudah tersimpan tetap terbaca karena nama opsinya tidak berubah.
- Aturan fakta pada prompt berita tidak lagi memaksa netralitas: hanya melarang klaim, prediksi, dan kesimpulan yang tidak didukung artikel mentah, sedangkan cara penyampaian mengikuti Tone of Voice.
- Catatan berita pada Humanizer mempertahankan tone of voice situs, tidak lagi menetralkan nada.

### Catatan
- Panjang tulisan adalah arahan di prompt, bukan batas yang dipaksa plugin. Berita tidak diperpanjang dengan fakta di luar artikel mentah.

## [2.6.1] - 2026-11-23

### Diubah
- **Mode Berita kini melewati Humanizer** (jika Humanizer diaktifkan), setelah Penulis Berita dan sebelum Visual Agent atau Quality Gate. SEO Editor tetap dilewati.
- Humanizer menerima parameter opsional `$mode`. Pada mode `news`, catatan khusus ditambahkan ke prompt agar tidak menambah H1, CTA beranda, atau FAQ (aturan bawaan Humanizer untuk artikel SEO) dan tidak menambah atau mengubah fakta.
- Humanizer memakai topik berita (bukan label tugas) sebagai kata kunci.
- Hasil Humanizer pada berita **ditolak** jika menambah angka baru dibanding tulisan sebelumnya; alasannya dicatat di data tugas dan artikel memakai versi sebelum humanisasi.
- Prompt bawaan Penulis Berita tidak lagi memakai `<blockquote>` karena Humanizer hanya mengizinkan h1, h2, h3, p, ul, ol, li, strong, em, a.

## [2.6.0] - 2026-11-23

### Ditambahkan
- **Mode Berita** pada dropdown *Mode* (kelas baru `AAA_News`): tempel artikel mentah, plugin menulis ulang dengan gaya website. Satu artikel mentah menghasilkan **tiga tugas antrean**, masing-masing dengan sudut pandang dan judul berbeda (bawaan: Ringkasan, Dampak, Latar belakang).
- **Status per tugas**: Draft, Langsung terbit, atau Terjadwal (tanggal dan jam artikel pertama, serta jarak antar artikel dalam menit).
- **Kartu "Penulis Berita" di Pengaturan Agent**: model, prompt yang bisa diedit (placeholder `{raw}`, `{angle}`, `{angle_label}`, `{style}`, `{topic}`, `{brand}`, `{audience}`, `{extra_info}`), kolom *Gaya website* (tone, panjang, struktur), dan tiga sudut pandang yang bisa diubah nama dan instruksinya.
- **Aturan fakta ketat** pada prompt bawaan: hanya fakta dari artikel mentah, tanpa tambahan angka, nama, atau kutipan baru.
- **Pemeriksaan angka otomatis**: angka (2 digit atau lebih) di hasil tulisan yang tidak ada di artikel mentah ditandai, artikel dipaksa tersimpan sebagai draft, dan peringatan muncul di kolom *Error terakhir* pada halaman Antrean. Pemeriksaan ini hanya mencakup angka, bukan nama atau klaim lain, sehingga tinjauan manusia tetap diperlukan.
- Tahap antrean baru `news_write`.

### Diubah
- Alur Berita melewati Visual Agent (bila aktif) dan Quality Gate, tetapi melewati SEO Editor dan Humanizer karena keduanya dirancang untuk artikel SEO (FAQ, CTA, hyperlink beranda).
- Quality Gate memakai judul hasil penulis berita, status, dan jadwal per tugas bila tugas bertipe berita.
- Artikel mentah dihapus dari data tugas setelah berhasil ditulis untuk menghemat ruang database.

### Catatan
- Setiap tugas menulis secara terpisah, sehingga ketiga judul dijaga berbeda lewat sudut pandang, bukan dibandingkan satu sama lain.
- Artikel mentah dibatasi 30.000 karakter dan dibersihkan dari tag HTML.

## [2.5.0] - 2026-11-22

### Ditambahkan
- **Menu Pengaturan Agent** (kelas baru `AAA_Agent_Settings`) untuk mengatur setiap agent dari satu halaman.
- **Model hybrid per agent lewat dropdown**: Riset SERP, Agent 1, Agent 2, Agent 3, Humanizer, Generator Cluster, dan Visual Agent. Pilihan "Ikuti model global" tetap tersedia.
- **Tombol "Muat daftar model dari API"** (`AAA_API::refresh_models()`) yang mengambil model Gemini berkemampuan `generateContent` dan menyimpannya di opsi `aaa_model_list`. Jika belum dimuat atau gagal, daftar bawaan dipakai.
- **Edit prompt per agent** (kelas baru `AAA_Prompts`) untuk Riset SERP, Agent 1, 2, 3, Humanizer, dan Generator Cluster:
  - Placeholder seperti `{keyword}`, `{outline}`, `{html}` diganti saat dijalankan.
  - Placeholder wajib divalidasi saat simpan; prompt yang tidak lengkap ditolak dan prompt sebelumnya dipertahankan.
  - Format output (struktur JSON dan pemisah `###`) ditambahkan otomatis di akhir prompt agar hasil tetap bisa diproses.
  - Tombol "Pakai prompt bawaan" dan penanda status *bawaan* atau *disesuaikan*. Prompt yang identik dengan bawaan disimpan kosong sehingga ikut pembaruan bawaan.
- **Kategori ganda**: artikel dapat dimasukkan ke lebih dari satu kategori (checkbox, minimal satu). Kolom baru `category_ids` pada tabel antrean.

### Diubah
- Model global di halaman utama kini berupa dropdown.
- Pengaturan model, riset SERP, dan batas percobaan dipindahkan dari halaman Antrean ke Pengaturan Agent. Menu "Antrean & Model" menjadi "Antrean".
- Agent 3: struktur JSON output kini berada di akhir prompt, bukan di tengah.
- Tema blueprint dengan kontras lebih tinggi antar elemen (latar, panel, kolom isian, tombol, status). Heading memakai huruf biasa, bukan huruf kapital.
- `AAA_Humanizer::rules()` menjadi `default_rules()` (public) agar dapat dipakai sebagai prompt bawaan.
- Versi database naik ke `2.5.0` (migrasi otomatis).

### Catatan
- Prompt Agent 1 hanya memakai data riset SERP jika memuat placeholder `{serp}`.
- Prompt Visual Agent tidak dapat diedit karena formatnya terikat renderer gambar.

## [2.4.2] - 2026-11-22

### Diubah
- **Mode Single Post dan Cluster Post dipisah** lewat dropdown *Mode*:
  - *Single Post*: kolom *Kata Kunci Target*, satu artikel dengan kata kunci apa adanya.
  - *Cluster Post*: kolom *Topik Utama* dan pilihan jumlah artikel.
- Kolom mode yang tidak aktif disembunyikan dan dinonaktifkan; server mengambil kata kunci sesuai mode.

## [2.4.1] - 2026-11-22

### Ditambahkan
- **Pilihan jumlah artikel Cluster Post (2 sampai 5, bawaan 5).** `AAA_Agents::generate_cluster_keywords($keyword, $count = 5)` meminta jumlah turunan sesuai pilihan, dan hasil dipotong sesuai pilihan bila model mengembalikan lebih banyak.

## [2.4.0] - 2026-11-22

### Ditambahkan
- **Riset SERP** sebelum Agent 1 memakai Gemini dengan Google Search grounding dan API key yang sama. Hasilnya dimasukkan ke prompt Agent 1. Kegagalan riset tidak menggagalkan artikel; alasannya disimpan di `serp_note`. Status `pending` kini menjalankan riset lalu lanjut ke status `agent_1`.
- **Model per tahap** (opsi `aaa_model_{tahap}`); kosong berarti memakai model global.
- **Retry otomatis** untuk error sementara (`http_request_failed`, `api_error`, `empty_response`, `json_error`) hingga batas `aaa_max_retries` (bawaan 3).
- **Dashboard antrean** dengan tombol Coba ulang, Batalkan, dan Hapus (dilindungi nonce dan pemeriksaan hak akses).
- Kolom `attempts` dan `last_error` pada tabel antrean, dengan migrasi otomatis (`AAA_DB::maybe_upgrade()`, opsi `aaa_db_version`).
- **Tema blueprint minimalism** untuk semua halaman admin plugin.
- `AAA_API::call_gemini()` menerima parameter opsional `$model` dan `$grounded`.

### Diperbaiki
- Saat tugas gagal, `agent_data` tidak lagi ditimpa hanya dengan alasan error, sehingga hasil tahap sebelumnya tidak hilang dan tugas bisa dicoba ulang dari tahap yang gagal.

### Keamanan
- `sslverify` pada panggilan API Gemini diaktifkan (`true`).

## Catatan upgrade
- Nama plugin dan class sama di semua versi. Nonaktifkan dan hapus versi lama sebelum memasang yang baru; jika tidak, terjadi fatal error karena class dimuat dua kali. Data dan pengaturan tidak ikut terhapus.
- Setelah aktivasi pertama, pastikan tabel antrean memiliki kolom `attempts`, `last_error`, dan `category_ids`.
- Riset SERP membutuhkan model yang mendukung Google Search (bawaan `gemini-2.5-flash`). Ubah di Pengaturan Agent bila perlu.
- Jika hosting Anda bermasalah dengan sertifikat SSL, panggilan API dapat gagal setelah `sslverify` diaktifkan.
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

# Changelog

Riwayat perubahan plugin **AI Auto Article Gemini Pro (Multi-Agent)**.
Format mengikuti [Keep a Changelog](https://keepachangelog.com/id-ID/1.1.0/). Basis awal: versi 2.3.0.

## [2.5.0] - 2026-11-22

### Ditambahkan
- **Menu Pengaturan Agent** (kelas baru `AAA_Agent_Settings`) untuk mengatur setiap agent dari satu halaman.
- **Model hybrid per agent lewat dropdown**: Riset SERP, Agent 1, Agent 2, Agent 3, Humanizer, Generator Cluster, dan Visual Agent. Pilihan "Ikuti model global" tetap tersedia.
- **Tombol "Muat daftar model dari API"** (`AAA_API::refresh_models()`) yang mengambil model Gemini berkemampuan `generateContent` dan menyimpannya di opsi `aaa_model_list`. Jika belum dimuat atau gagal, daftar bawaan dipakai.
- **Edit prompt per agent** (kelas baru `AAA_Prompts`) untuk Riset SERP, Agent 1, 2, 3, Humanizer, dan Generator Cluster:
  - Placeholder seperti `{keyword}`, `{outline}`, `{html}` diganti saat dijalankan.
  - Placeholder wajib divalidasi saat simpan; prompt yang tidak lengkap ditolak dan prompt sebelumnya dipertahankan.
  - Format output (struktur JSON dan pemisah `###`) ditambahkan otomatis di akhir prompt agar hasil tetap bisa diproses.
  - Tombol "Pakai prompt bawaan" dan penanda status *bawaan* atau *disesuaikan*. Prompt yang identik dengan bawaan disimpan kosong sehingga ikut pembaruan bawaan.
- **Kategori ganda**: artikel dapat dimasukkan ke lebih dari satu kategori (checkbox, minimal satu). Kolom baru `category_ids` pada tabel antrean.

### Diubah
- Model global di halaman utama kini berupa dropdown.
- Pengaturan model, riset SERP, dan batas percobaan dipindahkan dari halaman Antrean ke Pengaturan Agent. Menu "Antrean & Model" menjadi "Antrean".
- Agent 3: struktur JSON output kini berada di akhir prompt, bukan di tengah.
- Tema blueprint dengan kontras lebih tinggi antar elemen (latar, panel, kolom isian, tombol, status). Heading memakai huruf biasa, bukan huruf kapital.
- `AAA_Humanizer::rules()` menjadi `default_rules()` (public) agar dapat dipakai sebagai prompt bawaan.
- Versi database naik ke `2.5.0` (migrasi otomatis).

### Catatan
- Prompt Agent 1 hanya memakai data riset SERP jika memuat placeholder `{serp}`.
- Prompt Visual Agent tidak dapat diedit karena formatnya terikat renderer gambar.

## [2.4.2] - 2026-11-22

### Diubah
- **Mode Single Post dan Cluster Post dipisah** lewat dropdown *Mode*:
  - *Single Post*: kolom *Kata Kunci Target*, satu artikel dengan kata kunci apa adanya.
  - *Cluster Post*: kolom *Topik Utama* dan pilihan jumlah artikel.
- Kolom mode yang tidak aktif disembunyikan dan dinonaktifkan; server mengambil kata kunci sesuai mode.

## [2.4.1] - 2026-11-22

### Ditambahkan
- **Pilihan jumlah artikel Cluster Post (2 sampai 5, bawaan 5).** `AAA_Agents::generate_cluster_keywords($keyword, $count = 5)` meminta jumlah turunan sesuai pilihan, dan hasil dipotong sesuai pilihan bila model mengembalikan lebih banyak.

## [2.4.0] - 2026-11-22

### Ditambahkan
- **Riset SERP** sebelum Agent 1 memakai Gemini dengan Google Search grounding dan API key yang sama. Hasilnya dimasukkan ke prompt Agent 1. Kegagalan riset tidak menggagalkan artikel; alasannya disimpan di `serp_note`. Status `pending` kini menjalankan riset lalu lanjut ke status `agent_1`.
- **Model per tahap** (opsi `aaa_model_{tahap}`); kosong berarti memakai model global.
- **Retry otomatis** untuk error sementara (`http_request_failed`, `api_error`, `empty_response`, `json_error`) hingga batas `aaa_max_retries` (bawaan 3).
- **Dashboard antrean** dengan tombol Coba ulang, Batalkan, dan Hapus (dilindungi nonce dan pemeriksaan hak akses).
- Kolom `attempts` dan `last_error` pada tabel antrean, dengan migrasi otomatis (`AAA_DB::maybe_upgrade()`, opsi `aaa_db_version`).
- **Tema blueprint minimalism** untuk semua halaman admin plugin.
- `AAA_API::call_gemini()` menerima parameter opsional `$model` dan `$grounded`.

### Diperbaiki
- Saat tugas gagal, `agent_data` tidak lagi ditimpa hanya dengan alasan error, sehingga hasil tahap sebelumnya tidak hilang dan tugas bisa dicoba ulang dari tahap yang gagal.

### Keamanan
- `sslverify` pada panggilan API Gemini diaktifkan (`true`).

## Catatan upgrade
- Nama plugin dan class sama di semua versi. Nonaktifkan dan hapus versi lama sebelum memasang yang baru; jika tidak, terjadi fatal error karena class dimuat dua kali. Data dan pengaturan tidak ikut terhapus.
- Setelah aktivasi pertama, pastikan tabel antrean memiliki kolom `attempts`, `last_error`, dan `category_ids`.
- Riset SERP membutuhkan model yang mendukung Google Search (bawaan `gemini-2.5-flash`). Ubah di Pengaturan Agent bila perlu.
- Jika hosting Anda bermasalah dengan sertifikat SSL, panggilan API dapat gagal setelah `sslverify` diaktifkan.