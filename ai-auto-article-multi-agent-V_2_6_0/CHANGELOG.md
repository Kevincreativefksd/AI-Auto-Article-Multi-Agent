# Changelog

Riwayat perubahan plugin **AI Auto Article Gemini Pro (Multi-Agent)**.
Format mengikuti [Keep a Changelog](https://keepachangelog.com/id-ID/1.1.0/). Basis awal: versi 2.3.0.

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
