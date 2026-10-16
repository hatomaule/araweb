# AI / Developer Handoff

Panduan orientasi untuk AI atau developer yang melanjutkan Araweb. Dokumen ini
merangkum cara mulai, arsitektur, alur bisnis, batasan, dan validasi. Kode yang
sedang berjalan tetap menjadi sumber kebenaran; periksa ulang detail di bawah
terhadap source dan database target sebelum mengambil keputusan.

## Mulai di sini

Sebelum mengubah apa pun:

1. Baca `README.md` untuk panduan instalasi, fitur, konfigurasi, serta urutan
   migrasi yang terdokumentasi.
2. Jalankan `git status --short --branch` dan periksa perubahan lokal. Jangan
   menimpa, menghapus, atau memasukkan perubahan/unggahan yang tidak terkait ke
   commit.
3. Baca `index.php`, `core/modules.php`, dan modul yang langsung menangani
   kebutuhan. Ikuti alur route sampai template dan handler aksi; jangan
   menyimpulkan perilaku hanya dari nama file atau menu.
4. Untuk perubahan database, verifikasi skema aktual pada database target dan
   cocokkan dengan source. Buat backup dan minta persetujuan sebelum perubahan
   skema atau migrasi yang berisiko.
5. Setelah perubahan, validasi jalur yang diubah, termasuk role/otorisasi dan
   kondisi tanpa data opsional. Jalankan pemeriksaan PHP yang tersedia, misalnya
   `php -l path/to/changed-file.php`, serta uji melalui aplikasi jika
   lingkungannya tersedia.

Jangan menampilkan, menyalin, atau meng-commit kredensial maupun data pribadi.
Jangan menjalankan pembayaran nyata, menghapus data produksi, atau menjalankan
migrasi hanya untuk melihat hasilnya.

## Ringkasan proyek

- Aplikasi web manajemen kos Araweb menggunakan PHP native, PDO, dan
  MySQL/MariaDB.
- Di lingkungan WSL saat ini, proyek berada di
  `/home/alan_hatomaule/projects/ekosan`. Lingkungan yang diperiksa memakai
  PHP CLI dan MariaDB; Apache tidak terpasang. Untuk pengembangan lokal,
  jalankan `php -S 127.0.0.1:8000 -t .` dari direktori proyek dan buka
  `http://localhost:8000/` melalui browser Windows/WSL.
- PHP development server hanya untuk pengembangan lokal dan tidak menerapkan
  aturan `.htaccess`; jangan menganggapnya sebagai konfigurasi deployment.
- Tidak ada framework aplikasi atau sistem build frontend yang menjadi bagian
  dari pola utama proyek. Antarmuka menggunakan PHP, `style.css`, dan
  `app.js`.
- Koneksi database dikonfigurasi di `config.php`; variabel lingkungan yang
  dikenali adalah `DB_HOST`, `DB_NAME`, `DB_USER`, dan `DB_PASS`. Jangan
  menaruh rahasia di source.
- README menyebut database `manajemen_kos` dan menjelaskan migrasi yang pernah
  diperlukan. Di WSL, pastikan MariaDB berjalan dan kredensial
  `DB_HOST`/`DB_NAME`/`DB_USER`/`DB_PASS` cocok dengan database lokal; nilai
  fallback `root` tanpa kata sandi berasal dari konfigurasi lama dan belum
  tentu bekerja. Instalasi lokal/target dapat berbeda; jangan anggap seluruh
  migrasi sudah pernah dijalankan.

## Arsitektur dan titik masuk

`index.php` adalah entry point, router, pengendali sesi halaman, dan shell
antarmuka. Secara garis besar ia:

1. Memuat bootstrap, daftar route, serta handler aksi domain.
2. Membaca parameter `page`, memeriksa route yang diizinkan, dan menjalankan
   pengendalian akses sesuai role.
3. Menangani aksi POST dan pekerjaan pemeliharaan yang terkait request.
4. Memilih modul dan template untuk merender halaman.

`core/bootstrap.php` memulai sesi dan menyediakan koneksi PDO melalui `db()`.
`core/modules.php` memetakan nama halaman ke modul. Form/template dan handler
aksi dikelompokkan menurut domain, tetapi tampilan operator tersebar di modul
properti, booking, dan pembayaran; tidak ada modul operator tersendiri.

### Peta direktori

| Lokasi | Tanggung jawab |
| --- | --- |
| `index.php` | Entry point, routing, shell, navigasi, dan akses berbasis role |
| `core/` | Bootstrap sesi/database dan pemetaan route ke modul |
| `accounts/` | Autentikasi, pendaftaran, profil, password, dan pemulihan akun |
| `admin/` | Dashboard dan pengelolaan akun/listing untuk admin platform |
| `portal/` | Halaman publik, pencarian, detail kos/kamar, dan ulasan |
| `properties/` | Dashboard operator, gedung/kos, kamar, penghuni, dan masa sewa |
| `bookings/` | Form/list booking user dan daftar/keputusan booking operator |
| `payments/` | Tagihan, pembayaran, bukti, histori, dan konfirmasi operator |
| `reports/` | Laporan platform, operator, dan user |
| `style.css`, `app.js` | Tampilan dan interaksi frontend |
| `uploads/` | Foto properti/kamar/profil dan bukti pembayaran; perlakukan sebagai data |
| `migration_*.sql` | Perubahan skema bertahap; bukan skrip yang aman dijalankan ulang |

## Role dan akses

Role yang digunakan pada aplikasi:

- `admin`: pengelola platform dengan akses lintas akun/properti sesuai
  allowlist dan pemeriksaan akses.
- `operator`: pemilik/pengelola kos, hanya mengelola properti miliknya.
- `seeker`: role internal untuk User/pencari kos. UI menyebutnya “User”.

Nama role di database dan label yang terlihat di UI tidak selalu sama. Jangan
mengganti string role atau memperluas akses hanya dengan mengubah menu; periksa
allowlist di `index.php`, pemeriksaan role pada dispatch modul, dan otorisasi
di handler/query aksi.

## Alur bisnis penting

### Booking dan pembayaran

Alur yang diharapkan adalah User memilih kamar yang tersedia dan mengirim
booking, operator memutuskan booking, User membayar serta mengirim bukti,
operator meninjau bukti, lalu pembayaran terkonfirmasi melunasi tagihan dan
mengaktifkan masa sewa sesuai aturan yang diterapkan.

Di implementasi saat ini, `bookings/act.php` membuat permintaan booking melalui
record `rentals` dengan `status='upcoming'` dan `booking_status='pending'`.
Setelah disetujui, masa pembayaran booking dibatasi 24 jam. Upload bukti dan
keputusan pembayaran ditangani oleh `payments/act.php`; daftar konfirmasi
operator berada di `payments/confirmations.php`. Karena istilah “booking”
juga dipakai untuk struktur data/migrasi lain, telusuri kode aktif sebelum
mengubah model atau mengasumsikan sebuah tabel tertentu menjadi sumber data.

Halaman histori pembayaran adalah log; alur peninjauan bukti ada di halaman
Konfirmasi Pembayaran. Bukti pembayaran hanya boleh diakses setelah otorisasi
akun dan kepemilikan diperiksa.

### Properti, penghuni, dan laporan

- Operator mengelola kos/gedung, kamar, penghuni, sewa, rekening, booking, dan
  pembayaran milik properti sendiri.
- Admin memiliki fungsi platform lintas akun/properti, dengan pembatasan yang
  tetap perlu diverifikasi pada tiap route/aksi.
- User menjelajah properti publik, membuat booking, memantau akun, dan melihat
  histori pembayarannya sendiri.
- Laporan dapat memiliki tampilan berbeda bagi platform, operator, dan User.
  Gunakan query yang sudah membatasi kepemilikan/akun dan jangan memperluas
  cakupan data saat merapikan UI.

## Database dan migrasi

- Tabel yang tampak dalam source/migrasi mencakup akun (`users`), properti
  (`kos_buildings`), kamar (`rooms`), penghuni (`tenants`), masa sewa
  (`rentals`), tagihan (`invoices`), pembayaran (`payments`), ulasan, pencarian,
  booking, serta rekening.
- Daftar ini bukan dump skema lengkap dan tidak menjamin tabel/kolom tersebut
  tersedia di setiap database.
- Baca bagian instalasi di `README.md` dan migrasi terkait sebelum menyentuh
  database. Sebagian migrasi adalah migrasi satu kali, memiliki prasyarat,
  atau mengubah nilai lama; jangan menjalankan ulang tanpa memastikan kondisi
  skema.
- Beberapa fitur dirancang tetap menampilkan halaman saat kolom opsional belum
  ada. Contohnya, balasan ulasan membutuhkan
  `migration_operator_review_replies.sql`; periksa keberadaan kolom serta
  penanganan kondisi tersebut sebelum mengubah query/tampilan.
- Tidak ada asumsi bahwa database lokal sama dengan database server. Jangan
  menjalankan migrasi atau seed demo pada server nyata tanpa persetujuan
  eksplisit dan backup yang dapat dipulihkan.

## Validasi perubahan

Tidak ada suite test otomatis yang diketahui dari dokumentasi proyek. Untuk
perubahan PHP:

1. Jalankan `php -l` untuk setiap file PHP yang diubah jika PHP CLI tersedia.
2. Periksa whitespace/diff dan pastikan perubahan tidak mencakup file
   unggahan atau perubahan lokal yang tidak terkait.
3. Uji halaman melalui browser dengan role yang relevan jika akun/database
   uji tersedia. Cakup desktop dan ukuran layar ponsel untuk perubahan UI.
4. Uji akses langsung ke route dan POST action, bukan hanya apakah menu
   terlihat.
5. Gunakan data uji; jangan menekan aksi yang mengirim pembayaran atau
   mengubah data nyata.

Validasi UI terakhir yang tercatat untuk perubahan pada Oktober 2026 mencakup
halaman publik pada desktop dan lebar ponsel tanpa horizontal overflow.
Fungsionalitas internal yang memerlukan sesi operator/User belum tentu sudah
diuji di browser; ulangi pengujian sesuai ruang lingkup tugas.

## Keadaan terakhir yang diketahui

- Branch terakhir yang diketahui: `main`.
- Commit implementasi terakhir yang tercatat: `6fd0dea`
  (`Organize booking and account reporting flows`).
- Perubahan tersebut mengatur navigasi Booking/Konfirmasi Pembayaran,
  histori pembayaran, panel akun dan ulasan, laporan operator, perbaikan query
  bukti pembayaran, serta banner/logo.
- Panduan ini tidak menyatakan bahwa semua perubahan berikutnya sudah
  tervalidasi di semua role atau terhadap semua variasi database.
- Selalu periksa status Git saat mulai dan sebelum commit. Stage file yang
  diperlukan secara eksplisit; jangan memakai stage-all secara otomatis.

## File yang biasanya relevan

- Router/akses: `index.php`, `core/modules.php`
- Database/sesi: `core/bootstrap.php`, `config.php`, `functions.php`
- Portal: `portal/public.php`, `portal/form.php`, `portal/act.php`
- Properti: `properties/form.php`, `properties/act.php`,
  `properties/operator_dashboard.php`
- Booking: `bookings/form.php`, `bookings/act.php`,
  `bookings/seeker_form.php`, `bookings/seeker_list.php`,
  `bookings/operator_list.php`
- Pembayaran: `payments/form.php`, `payments/act.php`,
  `payments/proof.php`, `payments/confirmations.php`,
  `payments/my_history.php`
- Laporan: `reports/operator.php`, `reports/user.php`, `reports/platform.php`
- Setup dan gambaran fitur: `README.md`

Saat dokumentasi dan kode tampak berbeda, catat perbedaan tersebut dan
verifikasi dampaknya sebelum mengubah perilaku. Perbarui panduan ini bila
arsitektur, alur inti, cara validasi, atau batas keselamatan proyek berubah.
