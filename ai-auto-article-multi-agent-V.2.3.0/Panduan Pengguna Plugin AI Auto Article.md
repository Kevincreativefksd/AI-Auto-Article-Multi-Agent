# 🤖 AI Auto Article Gemini Pro (Multi-Agent)

**Versi:** 2.3.0  
**Deskripsi:** Sistem Multi-Agent AI untuk otomasi penulisan artikel SEO berbasis WordPress antrean (*queue*) dan verifikasi kualitas. Plugin ini menggunakan model bahasa Gemini dari Google untuk melakukan riset, penulisan, dan optimasi SEO secara otomatis.

---

## ✨ Fitur Utama
Plugin ini tidak sekadar membuat artikel menggunakan satu perintah langsung. Prosesnya dipecah menjadi 5 tahap agen AI untuk memastikan kualitas maksimal:
1. **Agent 1 (SEO Researcher):** Menganalisis *keyword*, niat pencarian (*search intent*), dan menyusun *outline*.
2. **Agent 2 (Content Writer):** Menulis artikel yang komprehensif, humanis, dan SEO-friendly.
3. **Agent 3 (SEO Editor):** Memperbaiki keterbacaan, meta deskripsi, dan tag SEO artikel.
4. **Agent Humanizer (baru):** Menulis ulang hasil SEO Editor agar tidak terbaca seperti tulisan AI (menghapus pola seperti "bukan hanya X, tetapi Y", penutup dramatis, tiga serangkai paksa, tanda pisah berlebihan, dan kata klise AI) tanpa mengubah fakta, hyperlink, heading, CTA, dan FAQ. Aturannya diadaptasi dari proyek *humanizer* (MIT, berbasis Wikipedia: Signs of AI writing) untuk bahasa Indonesia.
5. **Visual Content Agent (baru):** Membuat gambar editorial 16:9 (judul artikel, kategori, nama brand, deskripsi brand) secara lokal dengan PHP, tanpa biaya image generation. Gemini hanya menyusun data visual dan metadata SEO gambar (Image Title, Description, Alt Text, Caption). Gambar disimpan ke Media Library, dijadikan Featured Image, dan (opsional) disisipkan di awal artikel.
6. **Agent 4 (Quality Gate):** Sistem PHP yang memastikan konten hasil revisi tidak kosong, lalu mempublikasikan artikel secara otomatis atau menyimpannya sebagai *draft*.

---

## ⚙️ Persyaratan Sistem
- WordPress minimal versi 5.0+
- PHP minimal versi 7.4+ (Disarankan 8.0+)
- **API Key Google Gemini** (Gratis via Google AI Studio)

---

## 📥 Panduan Instalasi
1. Unduh (download) seluruh file plugin ini ke dalam format `.zip`.
2. Login ke Dashboard WordPress Anda.
3. Buka menu **Plugins > Add New > Upload Plugin**.
4. Pilih file `.zip` plugin yang sudah diunduh, lalu klik **Install Now**.
5. Setelah selesai, klik **Activate Plugin**.
6. Menu baru bernama **"AI Article Agents"** akan muncul di *sidebar* sebelah kiri.

---

## 🛠️ Konfigurasi Awal (Wajib)
Sebelum bisa membuat artikel, Anda harus memasukkan pengaturan AI.
1. Masuk ke menu **AI Article Agents**.
2. Pada panel **Konfigurasi**, isi data berikut:
   - **API Key:** Masukkan API Key dari akun Google Gemini Anda.
   - **Model:** Biarkan default (`gemini-1.5-flash`) atau ubah ke model lain yang valid.
   - **Brand:** Nama website atau bisnis Anda.
   - **Audience:** Target pembaca Anda (misal: "Pemula di bidang investasi", "Ibu rumah tangga").
   - **Tone of Voice:** Gaya bahasa (misal: "Santai, informatif, dan kredibel").
   - **Mode Publikasi:** Pilih **Auto Publish** (langsung terbit) atau **Simpan Draft** (disimpan untuk direviu manual).
   - **Aktifkan Humanizer:** Centang untuk menjalankan tahap Humanizer (aktif secara bawaan). Hilangkan centang bila ingin langsung memakai hasil SEO Editor.
3. Klik tombol **Simpan Pengaturan**.

---

## 🖼️ Visual Content Agent
Buka **AI Article Agents > Visual Content Agent**. Pengaturan (bawaan dalam kurung):

| Pengaturan | Keterangan |
|---|---|
| Visual Content Agent | Aktif/nonaktif (aktif) |
| Nama Brand | Kosong = memakai Brand dari panel Konfigurasi utama |
| Deskripsi Brand | (Ruang Literasi Ekonomi) |
| Warna Background / Teks / Aksen | (#F4F2EE / #171717 / #B92E32) |
| Featured Image | Jadikan gambar sebagai Featured Image (aktif) |
| Gambar di awal artikel | Sisipkan attachment yang sama di awal isi artikel (aktif) |
| Format Gambar | Otomatis (WebP bila didukung, jika tidak JPEG), WebP, atau JPEG |
| Batas Ukuran File | KB (200). Kualitas diturunkan bertahap, resolusi tetap 1920 x 1080 |

Catatan:
- Kategori pada gambar memakai kategori yang Anda pilih saat membuat artikel.
- Judul pada gambar adalah judul H1 artikel final. Bila tidak ada H1, dipakai keyword.
- Bila tema Anda sudah menampilkan Featured Image di atas artikel, matikan "Gambar di awal artikel" agar tidak tampil dua kali.
- Kegagalan gambar tidak membuat artikel gagal; alasannya tersimpan di data tugas (`agent_data.visual.note`).
- Tahap ini menambah 2 sampai 3 putaran antrean per artikel.

---

## 🚀 Cara Menjalankan Otomatisasi (SETTING CRON JOB)
**PENTING:** Secara bawaan, WordPress hanya menjalankan sistem antrean saat ada orang yang membuka website Anda. Agar plugin ini bisa **bekerja 100% di latar belakang** (bisa Anda tinggal tidur setelah klik eksekusi), Anda **WAJIB** mengatur *Cron Job* dari pihak ketiga. 

Cara paling mudah dan gratis adalah menggunakan **cron-job.org**:

1. Buka website [cron-job.org](https://cron-job.org) dan buat akun gratis.
2. Login dan masuk ke menu **CREATE CRONJOB**.
3. Di kolom **Title**, isi bebas (contoh: *Cron AI Article*).
4. Di kolom **URL**, masukkan URL *cron* website Anda persis seperti ini:
   👉 `https://domain-anda.com/wp-cron.php?doing_wp_cron=1`  
   *(Pastikan Anda mengubah `domain-anda.com` dengan alamat website Anda sendiri)*
5. Pada bagian **Execution schedule**, pilih **Every 2 minutes** (Setiap 2 menit).
6. Centang *Advanced* (Pengaturan Lanjutan):
   - **HTTP Method:** Pilih **GET**.
   - **Save Responses:** **WAJIB MATIKAN CENTANG** pada opsi ini (atau pilih *Do not save responses*). Ini untuk mencegah memori penuh atau error "output too large".
7. Klik **Create / Save**.

Selesai! Sekarang mesin AI Anda akan selalu hidup dan siap bekerja otomatis 24 jam.

---

## ✍️ Cara Membuat Artikel
1. Pada menu plugin, lihat panel **Buat Artikel Baru**.
2. Masukkan **Kata Kunci Utama** (Keyword) yang Anda incar.
3. (Opsional) Masukkan **Informasi Tambahan / Brief** jika ada arahan khusus untuk AI (misal: "Sebutkan produk X di akhir artikel").
4. Klik tombol **Masukkan Antrean**.
5. Kata kunci akan masuk ke **Status Antrean Live** di bagian bawah.
6. Anda bisa menutup tab *browser* atau komputer Anda, dan biarkan *Cron Job* mengeksekusinya secara otomatis dari Agent 1 hingga selesai!

---

## 💡 Troubleshooting (Pemecahan Masalah)

* **Antrean tidak jalan (terus-menerus "Menunggu giliran...")**  
  Pastikan Anda telah melakukan instruksi Setting Cron Job di atas dengan benar. Anda juga bisa menekan tombol **Paksa Eksekusi 1 Tahap Sekarang** pada panel "Mode Debugging" untuk memancing tugas pertama jalan.
* **Artikel gagal / Status `Failed`**  
  Arahkan kursor ke tulisan "Gagal", biasanya ada alasan mengapa AI gagal. Hal yang paling sering terjadi adalah **Koneksi API / Validasi Ditolak** karena batas *request* API Gemini Anda sedang habis (*rate limit*), atau **Konten HTML hasil revisi kosong** karena Gemini gagal mengembalikan hasil SEO Editor.
* **AI Tidak memberikan format yang benar**  
  Terkadang Google Gemini sedang tidak stabil dan salah memberikan format. Coba perbarui *keyword* atau perkecil informasi di kolom *brief*.

* **Hasil artikel tidak berubah setelah Humanizer aktif**  
  Plugin memvalidasi hasil Humanizer. Jika hyperlink berubah, H1/H2 hilang, atau teks menyusut lebih dari 30%, hasilnya dibuang dan artikel memakai versi SEO Editor. Alasannya tersimpan di data tugas (`agent_data.humanizer.note`). Kegagalan API pada tahap ini tidak membuat artikel berstatus *Failed*.
* **Gambar tidak muncul / artikel tanpa gambar**
  Buka **Visual Content Agent > Kemampuan Server**. Pastikan PHP GD dengan FreeType (atau Imagick) tersedia. Lihat juga `agent_data.visual.note` pada tugas tersebut. Nilai `status` yang mungkin: `ok`, `ok_over_limit` (gambar dibuat tetapi lebih besar dari batas), `failed`, `skipped`.
* **Pemakaian API bertambah**  
  Humanizer menambah 1 panggilan Gemini per artikel. Matikan opsinya di pengaturan bila kuota terbatas.
