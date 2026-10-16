# KosKosan

Aplikasi manajemen kamar kos KosKosan berbasis PHP native dan MySQL/MariaDB.

## Menjalankan secara lokal di WSL

Jalankan perintah berikut dari terminal WSL di direktori proyek:

```bash
cd ~/projects/ekosan
sudo service mariadb start
php -m | grep -i pdo_mysql
```

Secara bawaan, `config.php` memakai `localhost`, database `manajemen_kos`, dan
akun aplikasi lokal `araweb_app` (autentikasi socket, tanpa kata sandi), sesuai
setup WSL KosKosan. Pastikan MariaDB WSL berjalan dan akun tersebut sudah dibuat.
Untuk instalasi lain, atur `DB_HOST`, `DB_NAME`, `DB_USER`, dan `DB_PASS`
sebelum menjalankan server PHP. Jangan menganggap akun `root` tanpa kata sandi
bawaan XAMPP berlaku di WSL. Untuk menggunakan layanan reverse geocoding
Nominatim lain yang kompatibel, atur
`KOSKOSAN_REVERSE_GEOCODER_URL` ke URL endpoint `/reverse` layanan tersebut.
Variabel lama `ARAWEB_REVERSE_GEOCODER_URL` tetap didukung untuk konfigurasi
yang sudah ada.
Pastikan pula direktori `uploads/` dapat ditulisi oleh pengguna yang menjalankan PHP.

Jika memakai akun MariaDB lain, contoh mengatur koneksi untuk sesi terminal
saat ini:

```bash
export DB_HOST=localhost DB_NAME=manajemen_kos DB_USER='NAMA_AKUN_MARIADB'
read -rsp 'Kata sandi MariaDB: ' DB_PASS; export DB_PASS; printf '\n'
```

Ganti `NAMA_AKUN_MARIADB` dengan nama akun yang benar-benar sudah dibuat.
Jangan jalankan contoh dengan teks placeholder tersebut. Untuk kembali ke
koneksi bawaan WSL dan menghapus override sesi terminal, jalankan
`unset DB_HOST DB_NAME DB_USER DB_PASS`.

Uji kredensial sebelum menyalakan server:

```bash
php -r 'require "config.php"; db(); echo "Koneksi database berhasil\n";'
```

Jika tes berhasil, mulai server dari direktori proyek pada terminal yang sama:

```bash
php -S 127.0.0.1:8000 -t .
```

Biarkan server PHP tetap berjalan, lalu buka `http://localhost:8000/` dari
browser Windows atau WSL. PHP development server hanya untuk pengembangan
lokal; server ini terikat ke loopback dan tidak membaca aturan `.htaccess`.
Untuk menguji perlindungan berkas upload atau menjalankan aplikasi untuk
pengguna lain, gunakan web server yang dikonfigurasi dengan benar.

Jika memindahkan database yang sudah berisi data dari XAMPP, ekspor database
di XAMPP, impor dump ke MariaDB WSL, lalu verifikasi skema dan data sebelum
membuka aplikasi. Buat backup yang dapat dipulihkan; jangan jalankan ulang
migrasi yang sudah diterapkan.

## Persiapan database dan migrasi

1. Pastikan layanan MariaDB WSL berjalan dan siapkan akses database untuk PHP menggunakan variabel lingkungan di atas.
2. Untuk database baru, buat database `manajemen_kos` dan tabel awal menggunakan `kos_schema.sql` dari project sebelumnya; file skema itu tidak disertakan di repository ini. Untuk database yang dipindahkan dari XAMPP, impor dump database ke MariaDB WSL dan pertahankan data yang ada. Skema dasarnya menggunakan tabel `users`, `tenants`, `rooms`, `rentals`, `invoices`, dan `payments`.
3. Jalankan `migration_room_photos.sql` melalui klien MariaDB/MySQL di WSL untuk menambahkan tabel penyimpanan foto kamar dan fasilitas.
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
15. Koneksi database diatur melalui variabel lingkungan `DB_HOST`, `DB_NAME`, `DB_USER`, dan `DB_PASS`; nilai fallback di `config.php` adalah `localhost`, `manajemen_kos`, `araweb_app`, dan kata sandi kosong. Di WSL, akun aplikasi lokal menggunakan autentikasi socket MariaDB. Jika override DB pernah diatur pada terminal, pastikan nilainya valid atau hapus dengan `unset DB_HOST DB_NAME DB_USER DB_PASS` sebelum menjalankan PHP.
16. Buka `http://localhost:8000/` untuk melihat kos aktif dan kamar-kamarnya. Kamar terisi tetap ditampilkan dengan status tidak tersedia.
17. Buat/masuk menggunakan akun Admin. Operator mendaftar dari halaman publik dan kos aktifnya langsung dapat ditemukan tanpa persetujuan admin; User dapat mendaftar untuk booking. Pendaftaran meminta minimal salah satu email atau nomor HP, dan menerima foto profil opsional.
18. Untuk mengirim kode lupa password, set `KOSKOSAN_MAIL_FROM` ke alamat pengirim yang valid dan konfigurasikan transport email PHP pada mesin server. Variabel lama `ARAWEB_MAIL_FROM` tetap didukung untuk konfigurasi yang sudah ada. Untuk PHP CLI di WSL, periksa berkas konfigurasi dengan `php --ini`; jika mengubah konfigurasi, mulai ulang proses PHP development server. Kode hanya berlaku 15 menit, satu kali pakai, maksimal 5 percobaan; permintaan ulang dibatasi 60 detik. Jika email tidak dapat dimasukkan ke antrean, aplikasi mencatat kegagalan ke log server.
19. Untuk menambahkan data uji lokal, set `ARA_DEMO_PASSWORD` ke kata sandi acak minimal 12 karakter di lingkungan terminal, lalu jalankan `php seed_demo_data.php`. Seeder membuat 3 akun operator, 3 listing berisi masing-masing 5 kamar, 9 akun User, sewa aktif, dan enam periode tagihan/pembayaran per sewa. Data rekening demo ditandai agar tidak digunakan untuk transfer nyata; semua akun demo menggunakan kata sandi yang sama dari variabel lingkungan tersebut. Seeder berhenti jika data demo sudah ada.
20. Setelah backup, jalankan `migration_account_activity_and_deletion.sql` satu kali setelah migrasi multi-owner dan business flow. Migrasi ini menambahkan log aktivitas akun dan mempertahankan histori booking/pencarian saat akun User dihapus. Jangan jalankan ulang.
21. Setelah backup, jalankan `migration_property_and_room_profile_photos.sql` satu kali setelah migrasi `migration_multi_owner_platform.sql` dan `migration_room_photos.sql`. Migrasi ini menambahkan kolom foto profil terpisah untuk kos dan kamar. Foto galeri lama tetap digunakan sebagai sampul jika foto profil belum diunggah. Jangan jalankan ulang.
22. Setelah backup, jalankan `migration_room_type_gallery.sql` satu kali setelah migrasi gedung dan tipe kamar. Migrasi ini membuat galeri foto bersama per gedung dan tipe kamar untuk foto gedung, kamar, fasilitas kamar, dan fasilitas umum. Foto gedung akan masuk ke galeri profil kos; foto kamar dapat dipilih sebagai foto profil unit bertipe sama. Jangan jalankan ulang.
23. Setelah backup, jalankan `migration_indonesia_regions.sql` satu kali setelah tabel `users`, `kos_buildings`, dan kolom alamat profil tersedia. Migrasi ini mengimpor 91.599 wilayah ke `indonesia_regions` dan menambahkan kode wilayah serta RT/RW/jalan/detail alamat pada gedung dan profil akun. Jangan jalankan ulang karena migrasi juga menambahkan kolom dan foreign key.

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
- Beranda menampilkan carousel daftar kamar di bawah daftar kos, dengan data kamar, kos, ketersediaan, foto, dan harga.
- Booking memerlukan akun pencari. Operator menyetujui atau menolak booking; setelah disetujui, pencari memiliki waktu 24 jam untuk membayar dan mengunggah bukti transfer.
- Rekening transfer disimpan per kos. Penghuni hanya dapat melihat histori pembayaran sendiri; operator dibatasi pada histori penghuni dan transaksi kos miliknya; admin platform dapat mengelola semua akun. Kode lupa password email khusus akun pengguna aktif.
- Profil Admin, Operator, dan User menyediakan nama, email, nomor HP, alamat wilayah Indonesia berjenjang, RT/RW, jalan/detail alamat, jenis kelamin, serta unggah foto profil. ID akun otomatis dari primary key ditampilkan di profil, daftar Penghuni, dan manajemen akun tanpa migrasi ID tambahan. Avatar pria/wanita digunakan otomatis bila foto tidak tersedia. Kolom alamat dan gender ditambahkan melalui `migration_account_profiles.sql` dan `migration_account_gender.sql`.
- Panel operator menyediakan Laporan, Properti (Gedung dan Kamar), Penghuni, Input Pembayaran, Histori pembayaran, Booking, dan Analitik kos. Menu Kamar mengelompokkan penghuni menurut kamar; menu Penghuni menyediakan histori, reset password melalui dialog konfirmasi, pindahkan kamar, dan keluarkan. Input pembayaran dapat membuat hunian bagi akun KosKosan yang belum memiliki hunian atau akun baru, memakai harga kamar atau nominal khusus transaksi, serta mencatat metode dan periode pembayaran. Pemindahan penghuni mempertahankan sewa, tagihan, dan pembayaran; pengakhiran sewa tidak menghapus atau menonaktifkan akun.
- Analitik operator menampilkan pencarian/pengunjung dan aktivitas terbaru, serta mengizinkan pengelola membalas atau memoderasi ulasan. Balasan ulasan ditampilkan pada halaman publik kos.
- Admin dapat mengelola akun Operator dan User dari submenu terpisah, melihat avatar, serta membuka dan mengedit profil kedua jenis akun tanpa mengubah foto profil mereka. Ketiga submenu manajemen akun menyediakan filter Provinsi, Kabupaten/Kota, Kecamatan, dan Kelurahan/Desa. Lokasi Operator berasal dari gedung yang dikelolanya; lokasi User aktif berasal dari gedung huniannya, sedangkan User tanpa hunian difilter menurut alamat profil. Menu **User pengguna** menampilkan akun tanpa sewa atau booking aktif. Kolom Aktivitas terakhir menampilkan akses terbaru; operator juga dapat melihat waktu aktivitas terakhir akun penghuni. Aktivitas disimpan sebagai peristiwa berkala (maksimal satu catatan per lima menit sesi) untuk penggunaan lanjutan. Admin dapat menghapus permanen akun User yang tidak memiliki sewa atau booking berjalan; data sewa, pembayaran, booking, pencarian, dan aktivitas tetap disimpan dengan relasi akun dilepas. Akun Operator tidak dapat dihapus melalui menu ini. Aksi manajemen akun dan pengeluaran penghuni meminta konfirmasi melalui kotak centang di dialog.
- Tabel manajemen menyediakan pencarian, tampilan 10 baris per halaman dengan tombol sebelumnya/berikutnya, serta pilihan untuk menampilkan semua baris. Daftar kamar dan penghuni dapat difilter berdasarkan gedung; daftar akun User admin dapat difilter berdasarkan kos; Daftar kos admin dapat difilter berdasarkan Provinsi, Kabupaten/Kota, Kecamatan, dan Kelurahan/Desa.
- Laporan platform menampilkan booking, hasil pencarian, akun dan aktivitas online, kos, kamar, penghuni, pengunjung, serta grafik bulanan. Laporan operator dibatasi pada properti miliknya serta menampilkan grafik pemasukan bulanan dan tahunan berdampingan; laporan User hanya memuat pembayaran serta sisa tagihan akun tersebut.
- Tambah, edit, serta nonaktifkan pelanggan.
- Tambah dan edit kamar serta status ketersediaan/perawatan.
- Kelola beberapa gedung kos beserta Provinsi, Kabupaten/Kota, Kecamatan, Kelurahan/Desa, RT/RW, jalan, Detail Alamat, dan koordinat latitude/longitude; nomor kamar boleh berulang di gedung yang berbeda. Pilihan wilayah dimuat berjenjang dari database. Form gedung menyediakan peta interaktif opsional untuk memilih/memindahkan pin dan mengisi alamat dari koordinat; menyimpan pembaruan tidak mengharuskan pemilik memilih lokasi di peta.
- Setiap kamar terhubung ke satu gedung. Nama gedung ditampilkan pada daftar kamar, booking, masa sewa, tagihan, dan pembayaran.
- Operator dapat mengunggah foto profil kos dan kamar secara terpisah dari galeri. Foto profil kos tampil di banner detail kos dan kartu kos di halaman depan; foto profil kamar tampil pada kartu kamar dan detail kamar. JPG/PNG/WebP maksimal 4 MB.
- Filter tipe kamar galeri hanya menampilkan tipe yang tersedia pada gedung terpilih. Pilih foto di satu atau beberapa kategori, lalu tekan **Simpan foto** di bagian atas atau bawah untuk menyimpan semua pilihan sekaligus.
- Peta publik memakai Leaflet dan OpenStreetMap tanpa API key, menampilkan lokasi gedung aktif yang memiliki kamar tersedia beserta tipe kamar dan harga bulanan. Geocoding alamat memakai proxy server Nominatim dengan cache berdasarkan koordinat gedung tersimpan dan pembatasan satu permintaan per detik. Saat memilih titik, koordinat dikirim ke layanan OpenStreetMap; layanan peta/geocoding memerlukan koneksi internet dan mengikuti kebijakan penggunaan serta atribusi OpenStreetMap.
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
- Halaman publik KosKosan menampilkan kos yang tersedia, tipe kamar, fasilitas, lokasi, serta harga bulanan tanpa memerlukan login.
- Dari menu **Kamar → Edit**, pengelola dapat mengunggah hingga 4 foto kamar dan 4 foto fasilitas sekaligus. Format JPG, PNG, dan WebP didukung dengan batas 4 MB per foto. Foto kamar pertama menjadi sampul; foto fasilitas ditampilkan sebagai galeri di halaman publik.

Dataset pada migrasi wilayah dipatok dari `cahyadsn/wilayah` commit `d68e8d5516f969d1905d0b2940f20034becb0db7`, bertanggal 13 Februari 2026, dan berlisensi MIT (pemberitahuan ada di berkas migrasi). Sumber mencantumkan Kepmendagri No. 300.2.2-2138/2025; data belum diverifikasi sebagai rilis Kemendagri paling mutakhir. Sebelum memperbarui data, verifikasi rilis resmi, simpan backup, serta perbarui atribusi dan jumlah data pada migrasi.

Folder `uploads/rooms`, `uploads/properties`, `uploads/profiles`, dan `uploads/payments` harus dapat ditulisi oleh pengguna yang menjalankan PHP. Koordinat peta serta foto kos/kamar bersifat opsional; jika foto belum tersedia, halaman publik menggunakan ilustrasi bawaan KosKosan. Tema Light/Dark dapat dipilih pada halaman panel, autentikasi, dan publik; preferensi tema disimpan di browser. Pembayaran online bukan payment gateway: transfer tetap dilakukan manual dan bukti harus diverifikasi operator. Penghentian atau pembatalan sewa dan penonaktifan pelanggan menggunakan status, bukan penghapusan data, agar riwayat tagihan/pembayaran tetap terjaga.

Login Facebook untuk User menggunakan OAuth. Agar tombolnya aktif, set variabel lingkungan server `FACEBOOK_APP_ID`, `FACEBOOK_APP_SECRET`, dan `FACEBOOK_REDIRECT_URI` (URL callback persis `https://domain-anda/ekosan/index.php?page=facebook_callback`) lalu daftarkan URL callback yang sama pada aplikasi Meta. Jangan menyimpan App Secret di source code. Login email tetap tersedia.
