# Araweb

Aplikasi manajemen kamar kos berbasis PHP native dan MySQL/MariaDB.

## Menjalankan di XAMPP

1. Pastikan Apache dan MySQL berjalan.
2. Database `manajemen_kos` dan tabel-tabelnya harus sudah dibuat menggunakan `kos_schema.sql` dari project sebelumnya. Skema tersebut menggunakan tabel `users`, `tenants`, `rooms`, `rentals`, `invoices`, dan `payments`.
3. Jalankan `migration_room_photos.sql` melalui phpMyAdmin atau MySQL CLI untuk menambahkan tabel penyimpanan foto kamar dan fasilitas.
4. Untuk database lama yang masih memakai status `inactive`, jalankan `migration_room_types_and_statuses.sql` satu kali **sebelum** migrasi label tipe kamar pada langkah 9. Migrasi lama ini memetakan tipe yang belum dikenal menjadi Silver; jangan jalankan setelah langkah 9 karena nilai tipe baru akan tertimpa.
5. Jalankan `migration_customer_bookings.sql` satu kali untuk menambahkan akun pelanggan, pemesanan online, rekening tujuan, dan penyimpanan bukti transfer.
6. Jika database sudah ada sebelum dukungan gedung, jalankan `migration_buildings_and_room_locations.sql` **satu kali**. Migrasi ini membuat gedung penampung tanpa koordinat untuk kamar lama. Setelah migrasi, buka menu **Gedung kos → Edit**, lalu ganti nama/alamat dan isi koordinat sebenarnya; pindahkan kamar ke gedung yang sesuai bila ada beberapa lokasi. Jangan jalankan migrasi pada database yang sudah memiliki tabel `kos_buildings` dan kolom `rooms.building_id`.
7. Untuk database baru, gunakan skema terbaru yang sudah memuat tabel `kos_buildings` dan relasi `rooms.building_id`; migrasi gedung tidak diperlukan.
8. Sebelum memperbarui database yang sudah berisi data, buat backup. Jalankan `migration_multi_owner_platform.sql` **satu kali** setelah migrasi pelanggan dan gedung di atas. Migrasi ini menambah peran operator/User, kepemilikan properti, profil, review, riwayat pencarian, status booking, serta rekening transfer per kos. Jangan jalankan ulang pada database yang sudah memakai fitur platform.
9. Setelah backup, jalankan `migration_room_type_labels.sql` **satu kali**. Migrasi ini mengubah Silver/Diamond/Platinum menjadi Tipe Standart/Tipe Premium/Tipe Eksklusif, dan mengganti listing awal `Tiara Kos -Gambesi` menjadi `Tiara Kost`. Migrasi ini mengasumsikan nama gedung lama tersebut masih dipakai.
10. Setelah backup, jalankan `migration_account_password_resets.sql` **satu kali** untuk membuat penyimpanan kode pemulihan password.
11. Setelah backup, jalankan `migration_account_profiles.sql` **satu kali** untuk menambahkan kolom alamat profil akun pada tabel `users`.
12. Setelah backup, jalankan `migration_account_gender.sql` satu kali untuk menambahkan jenis kelamin akun. Akun lama yang belum memiliki data gender dapat melengkapinya dari halaman profil; avatar gendered digunakan setelah data tersebut dipilih.
13. Setelah backup, jalankan `migration_business_flow.sql` satu kali untuk kolom aktivitas pengguna dan tabel alur booking, rekening per kos, pembayaran booking, serta aktivitas properti. Migrasi ini khusus untuk skema platform yang telah memiliki tabel `users`, `rentals`, `rooms`, `kos_buildings`, `bank_transfer_settings`, `invoices`, dan `payments`; jangan jalankan ulang.
14. Setelah backup, jalankan `migration_operator_review_replies.sql` satu kali untuk mengaktifkan balasan pengelola pada ulasan kos. Migrasi ini membutuhkan tabel `property_reviews` dari migrasi platform; tanpa migrasi ini, halaman kos tetap dapat dibuka, tetapi balasan pengelola tidak ditampilkan.
15. Pengaturan bawaan koneksi: host `127.0.0.1`, database `manajemen_kos`, user `root`, kata sandi kosong. Ubah pengaturan di `config.php`, atau gunakan variabel lingkungan `DB_HOST`, `DB_NAME`, `DB_USER`, dan `DB_PASS`.
16. Buka `http://localhost/ekosan/` untuk melihat kos aktif dan kamar-kamarnya. Kamar terisi tetap ditampilkan dengan status tidak tersedia.
17. Buat/masuk menggunakan akun Admin. Operator mendaftar dari halaman publik dan kos aktifnya langsung dapat ditemukan tanpa persetujuan admin; User dapat mendaftar untuk booking. Pendaftaran meminta minimal salah satu email atau nomor HP, dan menerima foto profil opsional.
18. Untuk mengirim kode lupa password, set `ARAWEB_MAIL_FROM` ke alamat pengirim yang valid dan konfigurasikan transport email PHP (`SMTP`/`sendmail_path` di `php.ini`) pada mesin server. Restart Apache setelah mengubah `php.ini`. Kode hanya berlaku 15 menit, satu kali pakai, maksimal 5 percobaan; permintaan ulang dibatasi 60 detik. Jika email tidak dapat dimasukkan ke antrean, aplikasi mencatat kegagalan ke log server.
19. Untuk menambahkan data uji lokal, set `ARA_DEMO_PASSWORD` ke kata sandi acak minimal 12 karakter di lingkungan terminal, lalu jalankan `php seed_demo_data.php`. Seeder membuat 3 akun operator, 3 listing berisi masing-masing 5 kamar, 9 akun User, sewa aktif, dan enam periode tagihan/pembayaran per sewa. Data rekening demo ditandai agar tidak digunakan untuk transfer nyata; semua akun demo menggunakan kata sandi yang sama dari variabel lingkungan tersebut. Seeder berhenti jika data demo sudah ada.
20. Setelah backup, jalankan `migration_account_activity_and_deletion.sql` satu kali setelah migrasi multi-owner dan business flow. Migrasi ini menambahkan log aktivitas akun dan mempertahankan histori booking/pencarian saat akun User dihapus. Jangan jalankan ulang.

## Struktur modul

Kode aplikasi dibagi ke dalam delapan modul fungsional. `index.php` menjadi entry point dan router inti; halaman serta aksi domain dimuat dari modul yang sesuai. Tampilan operator merupakan bagian dari modul properti, booking, dan pembayaran—bukan modul bisnis terpisah.

- `core/`: bootstrap dan pemetaan halaman ke modul; `index.php`, `config.php`, dan `functions.php` menjadi bagian inti aplikasi.
- `accounts/`: pendaftaran, login Facebook/email, profil, manajemen password, dan pemulihan akun melalui email.
- `admin/`: Dashboard platform gabungan ringkasan dan laporan, daftar kos, manajemen akun, profil operator/User, dan aksi admin.
- `portal/`: pencarian publik, detail kos, riwayat pencarian, dan ulasan.
- `properties/`: dashboard operasional serta pengelolaan kos/gedung, kamar, pelanggan, dan sewa.
- `bookings/`: formulir dan daftar booking pencari, permintaan operator, serta keputusan booking.
- `payments/`: tagihan, rekening kos, pencatatan/verifikasi pembayaran, dan penyajian bukti transfer terbatas.
- `reports/`: komponen analitik Dashboard platform, laporan keuangan operator, dan laporan pembayaran pribadi User.

Modul memakai koneksi, sesi, dan helper yang disediakan inti aplikasi. Pemetaan route berada di `core/modules.php`; form dan aksi masing-masing berada di direktori domain. Pemisahan modul tidak mengubah skema database; jalankan migrasi yang tercantum pada langkah instalasi untuk fitur yang memerlukannya.

## Fitur

- Ringkasan pelanggan aktif, kamar terisi, okupansi, dan sisa tagihan.
- Platform multi-pemilik dengan tiga peran yang tampil di aplikasi: Admin, Operator, dan User. Setiap akun memiliki satu role; kos aktif operator langsung tampil tanpa persetujuan admin; operator hanya dapat mengelola kos miliknya; User dapat menjelajah tanpa login.
- Pencarian publik mencocokkan nama kos atau alamat dan mengelompokkan hasil per properti. Semua kamar kos terpublikasi ditampilkan termasuk yang terisi; kartu ringkas dapat dibuka untuk detail kamar berisi status, foto, harga, fasilitas, kos induk, dan pengelola. Booking hanya ditawarkan untuk kamar tersedia.
- Booking memerlukan akun pencari. Operator menyetujui atau menolak booking; setelah disetujui, pencari memiliki waktu 24 jam untuk membayar dan mengunggah bukti transfer.
- Rekening transfer disimpan per kos. Penghuni hanya dapat melihat histori pembayaran sendiri; operator dibatasi pada histori penghuni dan transaksi kos miliknya; admin platform dapat mengelola semua akun. Kode lupa password email khusus akun pengguna aktif.
- Profil Admin, Operator, dan User menyediakan nama, email, nomor HP, alamat, jenis kelamin, serta unggah foto profil. ID akun otomatis dari primary key ditampilkan di profil, daftar Penghuni, dan manajemen akun tanpa migrasi ID tambahan. Avatar pria/wanita digunakan otomatis bila foto tidak tersedia. Kolom alamat dan gender ditambahkan melalui `migration_account_profiles.sql` dan `migration_account_gender.sql`.
- Panel operator menyediakan Laporan, Properti (Gedung dan Kamar), Penghuni, Input Pembayaran, Histori pembayaran, Booking, dan Analitik kos. Menu Kamar mengelompokkan penghuni menurut kamar; menu Penghuni menyediakan histori, reset password melalui dialog konfirmasi, pindahkan kamar, dan keluarkan. Input pembayaran dapat membuat hunian bagi akun Araweb yang belum memiliki hunian atau akun baru, memakai harga kamar atau nominal khusus transaksi, serta mencatat metode dan periode pembayaran. Pemindahan penghuni mempertahankan sewa, tagihan, dan pembayaran; pengakhiran sewa tidak menghapus atau menonaktifkan akun.
- Analitik operator menampilkan pencarian/pengunjung dan aktivitas terbaru, serta mengizinkan pengelola membalas atau memoderasi ulasan. Balasan ulasan ditampilkan pada halaman publik kos.
- Admin dapat mengelola akun Operator dan User dari submenu terpisah, melihat avatar, serta membuka dan mengedit profil kedua jenis akun tanpa mengubah foto profil mereka. Menu **User pengguna** menampilkan akun tanpa sewa atau booking aktif. Kolom Aktivitas terakhir menampilkan akses terbaru; operator juga dapat melihat waktu aktivitas terakhir akun penghuni. Aktivitas disimpan sebagai peristiwa berkala (maksimal satu catatan per lima menit sesi) untuk penggunaan lanjutan. Admin dapat menghapus permanen akun User yang tidak memiliki sewa atau booking berjalan; data sewa, pembayaran, booking, pencarian, dan aktivitas tetap disimpan dengan relasi akun dilepas. Akun Operator tidak dapat dihapus melalui menu ini. Aksi manajemen akun dan pengeluaran penghuni meminta konfirmasi melalui kotak centang di dialog.
- Laporan platform menampilkan booking, hasil pencarian, akun dan aktivitas online, kos, kamar, penghuni, pengunjung, serta grafik bulanan. Laporan operator dibatasi pada properti miliknya serta menampilkan grafik pemasukan bulanan dan tahunan berdampingan; laporan User hanya memuat pembayaran serta sisa tagihan akun tersebut.
- Tambah, edit, serta nonaktifkan pelanggan.
- Tambah dan edit kamar serta status ketersediaan/perawatan.
- Kelola beberapa gedung kos beserta alamat dan koordinat latitude/longitude; nomor kamar boleh berulang di gedung yang berbeda.
- Setiap kamar terhubung ke satu gedung. Nama gedung ditampilkan pada daftar kamar, booking, masa sewa, tagihan, dan pembayaran.
- Peta publik memakai Leaflet dan OpenStreetMap tanpa API key, menampilkan lokasi gedung aktif yang memiliki kamar tersedia beserta tipe kamar dan harga bulanan. Isi alamat serta koordinat gedung dari menu **Gedung kos**. Pustaka peta dan petanya memerlukan koneksi internet.
- Pilih tipe kamar dari Tipe Standart, Tipe Premium, dan Tipe Eksklusif.
- Status kamar Kosong/Perawatan diatur pengelola; Terisi/Pending mengikuti masa sewa dan booking. Booking yang disetujui tampil di data kamar beserta nama User dan status pembayarannya, tetapi baru menjadi penghuni aktif dan berstatus Terisi setelah tagihan lunas.
- Pencari dapat memilih masa sewa 1–12 bulan dan mengirim permintaan booking. Pembayaran dapat dilakukan setelah operator menyetujuinya; booking yang disetujui menahan kamar selama 24 jam, sedangkan bukti yang sudah dikirim tetap menahan kamar sampai ditinjau.
- Rekening tujuan dan petunjuk transfer diatur per listing melalui menu **Rekening**. Operator meninjau bukti booking kosnya sendiri; hanya bukti yang disetujui yang melunasi tagihan dan mengaktifkan sewa.
- Bukti transfer berupa JPG, PNG, atau WebP hingga 4 MB. Berkas hanya dapat dilihat setelah login oleh pengelola atau pelanggan pemilik booking.
- Catat masa sewa dengan validasi bentrok tanggal kamar.
- Buat tagihan untuk periode sewa.
- Catat pembayaran penuh atau bertahap dengan metode tunai, transfer, e-wallet, dan lainnya.
- Status lunas diperbarui otomatis saat jumlah pembayaran terkonfirmasi mencapai total tagihan.
- Riwayat transaksi penghuni tersedia pada menu **Riwayat pembayaran**; penghuni tidak dapat melihat pembayaran pengguna lain.
- Halaman publik Araweb menampilkan kos yang tersedia, tipe kamar, fasilitas, lokasi, serta harga bulanan tanpa memerlukan login.
- Dari menu **Kamar → Edit**, pengelola dapat mengunggah hingga 4 foto kamar dan 4 foto fasilitas sekaligus. Format JPG, PNG, dan WebP didukung dengan batas 4 MB per foto. Foto kamar pertama menjadi sampul; foto fasilitas ditampilkan sebagai galeri di halaman publik.

Folder `uploads/rooms`, `uploads/properties`, `uploads/profiles`, dan `uploads/payments` harus dapat ditulisi oleh Apache/PHP. Koordinat peta serta foto kos/kamar bersifat opsional; jika foto belum tersedia, halaman publik menggunakan ilustrasi bawaan Araweb. Pembayaran online bukan payment gateway: transfer tetap dilakukan manual dan bukti harus diverifikasi operator. Penghentian atau pembatalan sewa dan penonaktifan pelanggan menggunakan status, bukan penghapusan data, agar riwayat tagihan/pembayaran tetap terjaga.

Login Facebook untuk User menggunakan OAuth. Agar tombolnya aktif, set variabel lingkungan server `FACEBOOK_APP_ID`, `FACEBOOK_APP_SECRET`, dan `FACEBOOK_REDIRECT_URI` (URL callback persis `https://domain-anda/ekosan/index.php?page=facebook_callback`) lalu daftarkan URL callback yang sama pada aplikasi Meta. Jangan menyimpan App Secret di source code. Login email tetap tersedia.
