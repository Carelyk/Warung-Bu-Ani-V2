# Warung Bu Ani V2

Sistem manajemen **inventori** dan **arus kas** untuk warung/toko, dibangun ulang (redesign) dengan tampilan modern ala Odoo/ERP. Versi ini adalah **V2** dari proyek sebelumnya, dengan antarmuka yang sepenuhnya **Bahasa Inggris**, interaktif (JavaScript), dan tata letak berbasis kartu/grid — bukan lagi tabel kaku.

> Repo ini adalah repo baru (V2). Versi lama tetap tersimpan terpisah.

---

## ✨ Fitur Utama

### 📊 Dashboard
- Statistik real-time: jumlah barang, user, pemasukan, pengeluaran, dan saldo.
- Grafik arus kas interaktif (Chart.js) dengan periode **3D / 7D / 1M / 1Y**.
- Peringatan stok menipis (low stock alerts).

### 📦 Inventory (Inventori)
- CRUD produk: tambah, edit, hapus.
- **Pencarian & filter instan** tanpa reload, dengan tampilan **List** dan **Grid**.
- **Pagination** (25/50/100 baris, lompat halaman, Prev/Next).
- **Stepper tombol − / +** untuk Quantity dan Minimum Stock (batas 0–1.000).
- **Dropdown Rack location** (A1–A5, B1–B5, …, Storage S1–S8).
- **Sub-category dropdown mengikuti Category** yang dipilih.
- Batas harga beli/jual: maks **Rp 9.999.999**.
- **Barcode** opsional — otomatis dibuat (13 digit) jika dikosongkan, dan bisa dipakai untuk mencari produk (kompatibel dengan barcode scanner).
- Stock-out (pengurangan stok) dengan validasi jumlah tidak melebihi stok.

### 💰 Finance (Keuangan)
- Catat pemasukan/pengeluaran (Income/Expense).
- Filter per tipe dan kategori, **pagination server-side** (25/50/100 baris).
- Ringkasan total pemasukan, pengeluaran, dan saldo.
- Batas jumlah transaksi: maks **Rp 1.000.000.000** (1 miliar).

### 👥 User Management
- Tambah/edit/hapus user, atur role (admin/user) dan status.
- Akun admin dilindungi (tidak bisa dihapus).

### 🔐 Autentikasi
- Login dengan password ter-hash (`password_hash()` / `password_verify()`).
- Manajemen sesi admin & user.

---

## 🛠️ Teknologi

| Bagian | Teknologi |
|---|---|
| Backend | PHP 7.4+ (native, tanpa framework) |
| Database | MySQL / MariaDB (PDO) |
| UI | Tailwind CSS (CDN) + CSS kustom |
| Ikon | Google Material Symbols Rounded |
| Font | Inter |
| Grafik | Chart.js |
| Notifikasi/konfirmasi | SweetAlert2 |
| Interaktivitas | JavaScript vanilla (`assets/js/script.js`) |

---

## 📋 Persyaratan

- PHP 7.4 atau lebih baru (ekstensi PDO MySQL)
- MySQL 5.7+ / MariaDB
- Web server Apache/Nginx (atau XAMPP/Laragon)

---

## 🚀 Instalasi (XAMPP)

1. **Salin project** ke folder web server, mis. `C:\xampp\htdocs\warung-bu-ani`.
2. **Buat database** `sistem_inventori` di phpMyAdmin/MySQL.
3. **Import** file `database.sql` (berisi struktur + data contoh).
4. **Sesuaikan koneksi** di `config/database.php` bila perlu (default: `root` tanpa password, standar XAMPP).
4. Jalankan Apache & MySQL, lalu buka:
   ```
   http://localhost/warung-bu-ani/
   ```

> Alternatif: jalankan `setup_database.php` sekali untuk membuat database + data dummy secara otomatis.

---

## 🔑 Akun Demo

| Username | Password | Role |
|---|---|---|
| `admin` | `admin123` | Admin |
| `user01` | `user123` | User |
| `user02` | `user123` | User |

> Halaman login juga menampilkan hint akun demo agar mudah dicoba oleh penguji.
> **Untuk penggunaan nyata, ganti password ini.**

---

## 📁 Struktur Folder

```
warung-bu-ani/
├── assets/
│   ├── css/style.css          # Design system (tema, sidebar, kartu, pager, stepper)
│   └── js/script.js           # Semua interaktivitas (sidebar, filter, pagination, modal, input angka)
├── config/
│   └── database.php           # Koneksi PDO + BASE_URL dinamis
├── includes/
│   └── functions.php          # Helper: format uang/tanggal, kategori, rack, sub-kategori, barcode
├── layout/
│   ├── header.php             # <head>, topbar
│   ├── sidebar.php            # Navigasi sidebar
│   └── footer.php             # Penutup + Chart.js & SweetAlert2
├── modules/
│   ├── auth/                  # login, logout
│   ├── dashboard/             # dashboard + API data grafik
│   ├── inventori/             # manajemen stok
│   ├── keuangan/              # arus kas
│   └── user_management/       # kelola user
├── index.php                  # entry point → dashboard
├── database.sql              # struktur database + data contoh
└── README.md
```

---

## 🌐 Catatan Deploy (Cloudflare Tunnel)

Aplikasi ini berbasis **PHP + MySQL**, sehingga **tidak bisa di-hosting langsung di Cloudflare Pages/Workers** (keduanya tidak menjalankan PHP/MySQL).

Cara agar bisa diakses online:

- **Cloudflare Tunnel** (`cloudflared`) — menjalankan app dari komputer lokal (XAMPP) dan memberi URL publik (`*.trycloudflare.com`). Komputer harus tetap menyala.
  ```bash
  cloudflared tunnel --url http://localhost
  # akses: https://<acak>.trycloudflare.com/warung-bu-ani/
  ```
- **Hosting PHP + MySQL** (InfinityFree, 000webhost, Railway/Render, VPS, dll.) — upload semua file + import `database.sql`, lalu sesuaikan `config/database.php`.

`config/database.php` sudah mendeteksi `X-Forwarded-Proto` sehingga `BASE_URL` otomatis memakai `https://` saat berada di belakang tunnel/proxy.

---

## 📁 Struktur Database (ringkas)

- `users` — akun & role.
- `barang` — produk (nama, kategori, sub_kategori, jumlah, satuan, harga beli/jual, min_stock, barcode, lokasi_rak, user_id).
- `keuangan` — transaksi arus kas (tanggal, keterangan, kategori, jenis masuk/keluar, metode, jumlah, user_id).

---

## 🔄 Yang Berubah dari Versi Sebelumnya (V1 → V2)

Ini adalah **redesign penuh** dari website sebelumnya. Ringkasan perubahannya:

### Tampilan / UI
- **Desain ulang total** bergaya Odoo/ERP: bersih, modern, dan **seluruh teks berbahasa Inggris**.
- **Tabel kaku (DataTables) diganti** tampilan **kartu/grid** dan **list** yang lebih fleksibel.
- Ganti **Font Awesome → Material Symbols Rounded**, font **Inter**, dan Tailwind CSS.
- **Responsif** — sidebar bisa di-collapse (desktop) dan jadi drawer penuh (mobile); bug buka/tutup sidebar diperbaiki.
- Dukungan **tema terang/gelap** (light/dark).

### Interaktivitas (JavaScript)
- **Pencarian & filter instan** tanpa reload halaman.
- **Toggle tampilan List / Grid** pada Inventory.
- **Pagination** di Inventory (client-side) dan Finance (server-side): pilihan 25/50/100 baris, lompat halaman, Prev/Next.
- **Modal** tambah/edit yang lebih rapi, notifikasi & konfirmasi pakai **SweetAlert2**.
- **Stepper tombol − / +** untuk input angka.
- **Cache-busting** aset (CSS/JS) supaya perubahan langsung tampil.

### Inventory
- **Dropdown Rack location** (A1–A5, B1–B5, …, Storage S1–S8) yang terpusat, menggantikan input teks bebas.
- **Dropdown Sub-category mengikuti Category** yang dipilih.
- **Batas input**: Quantity & Minimum stock 0–1.000, harga beli/jual maks Rp 9.999.999.
- **Barcode opsional + auto-generate** (13 digit) bila dikosongkan.
- Validasi stock-out tidak melebihi stok, di klien maupun server.

### Keuangan
- **Pagination server-side** + filter jenis/kategori yang tetap tersimpan antar halaman.
- **Batas jumlah transaksi** maks Rp 1.000.000.000.
- Label mata uang ringkas diperbaiki (**Jt / Rb / Miliar**).

### Data & Keamanan
- Kolom numerik database **diperbesar** (`jumlah`/`min_stock` → `BIGINT`, harga & jumlah keuangan → `DECIMAL(18,2)`) agar tidak cepat mentok.
- `config/database.php` kini mendukung **proxy/tunnel** (`X-Forwarded-Proto`) sehingga `BASE_URL` otomatis `https` saat online.

### Manajemen User
- Daftar user **dirapikan** (hanya menyisakan akun yang relevan), akun admin tetap dilindungi.

---

## 🔒 Catatan Perbaikan Keamanan (Security Fix Log)

Perbaikan berikut dilakukan pada **Oktober 2026**. Semuanya adalah **perbaikan nyata pada kode** (bukan menyembunyikan error): celah ditutup pada sumbernya, dan **pesan kesalahan tetap ditampilkan jelas** kepada pengguna, hanya detail teknis yang dipindah ke log server.

### 1. Perlindungan CSRF (Cross-Site Request Forgery)
**Masalah.** Seluruh form (login, tambah/edit/hapus produk, transaksi, kelola user) dikirim tanpa token CSRF. Situs lain bisa mengirim form atas nama user yang sedang login.

**Perbaikan.** Ditambahkan mekanisme token per-sesi di `includes/functions.php`:
- `csrfToken()` — membuat/mengambil token 32-byte acak per sesi.
- `csrfField()` — merender `<input type="hidden" name="csrf_token">`.
- `requireCsrf()` — menolak permintaan tanpa token yang valid; user diarahkan kembali dengan pesan *"Sesi kedaluwarsa atau permintaan tidak valid."*

Token dipasang di **semua** form POST (login, 6 form modal CRUD, tombol sign-out), dan dicek sekali di awal tiap modul (`inventori`, `keuangan`, `user_management`).

### 2. Logout Tidak Lagi Bisa Dipicu Lewat GET
**Masalah.** `logout.php` memproses logout lewat **GET**. Satu tag `<img src=".../logout.php">` di halaman mana pun cukup untuk memaksa user keluar.

**Perbaikan.** `logout.php` kini **hanya menerima POST + token CSRF** (GET → `405 Method Not Allowed`). Link sign-out di `sidebar.php` dan `header.php` diubah menjadi form POST.

### 3. Aksi Hapus/Nonaktifkan Tidak Lagi Lewat GET
**Masalah.** Hapus produk, hapus transaksi, aktif/nonaktifkan user dieksekusi dari sebuah URL GET (`?action=delete&id=...`). Ini bisa dipicu tanpa persetujuan user.

**Perbaikan.** Handler kini mensyaratkan `REQUEST_METHOD === 'POST'`. Helper JavaScript `confirmDelete()` / `confirmAction()` di `assets/js/script.js` diubah dari `window.location.href = url` menjadi **submit form POST** (`window.postAction()`) yang menyertakan token CSRF — konfirmasi SweetAlert2 tetap sama.

### 4. Pengerasan Sesi (Session Hardening)
**Perbaikan** di `includes/functions.php` dan `modules/auth/login.php`:
- Cookie sesi kini `HttpOnly` + `SameSite=Lax` (dan otomatis `Secure` saat HTTPS).
- `session_regenerate_id(true)` dipanggil **setelah login berhasil**, mencegah *session fixation*.
- Saat logout, cookie sesi dihapus eksplisit sebelum `session_destroy()`.

### 5. Kebocoran Detail Database
**Masalah.** `config/database.php` memakai `die($e->getMessage())`, menampilkan host/user/detail server ke pengunjung saat koneksi gagal.

**Perbaikan.** Pesan mentah driver **dicatat ke `error_log()` server**, sedangkan pengguna menerima pesan yang bisa ditindaklanjuti (host/database/user yang dipakai + saran menjalankan `setup_database.php`). Koneksi juga kini membaca `DB_HOST/DB_NAME/DB_USER/DB_PASS` dari environment (default tetap XAMPP).

### 6. Perbaikan Bug Tambahan
- **`generateKodeBarang()`** memakai interpolasi `"$prefix%"` langsung ke SQL. Diganti **prepared statement** dengan parameter terikat.
- **`clean()`** melakukan `htmlspecialchars()` pada **input**, lalu hasilnya disimpan ke database (double-encoding). Kini `clean()` hanya menormalkan input; escaping dilakukan di titik **output** lewat helper baru `e()`. Perilaku `htmlspecialchars()` yang sudah dipakai di template tetap dipertahankan.

### Verifikasi
Diuji pada PHP 8.2.12 + MySQL (XAMPP):

| Uji | Hasil |
|---|---|
| Lint PHP (semua file) | **0 gagal** |
| Login dengan token | **berhasil** (302 → dashboard) |
| Login tanpa token | **ditolak** (sesi tidak terautentikasi) |
| POST modul tanpa token | **ditolak**, data tidak berubah (barang 130, keuangan 1498 tetap) |
| GET hapus/nonaktifkan | **tidak mengubah data** |
| GET logout | **405** |
| POST logout tanpa token | **sesi tetap hidup** (tidak diputus) |
| POST logout dengan token | **sesi diputus** (kembali ke login) |
| Session ID setelah login | **berganti** (anti session fixation) |
| POST add/delete dengan token | **berfungsi** (130 → 131 → 130) |
| Halaman render | **0 PHP error** di semua modul |

> Catatan desain: permintaan tanpa token yang valid **dialihkan kembali dengan pesan ramah** (bukan halaman 403 kosong), supaya pengguna tahu penyebabnya. Yang penting, permintaan itu **tidak pernah** mengubah data atau memutus sesi.

---

## 📄 Lisensi

Dirilis di bawah lisensi **MIT**. Bebas dipakai dan dimodifikasi untuk keperluan belajar.