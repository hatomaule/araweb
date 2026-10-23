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
php -d upload_max_filesize=4M -d post_max_size=20M -S 127.0.0.1:8000 -t .
```

Batas upload PHP harus sesuai dengan batas aplikasi: 4 MB per foto dan hingga
4 foto galeri dalam satu kali penyimpanan. `post_max_size` dibuat 20 MB agar
seluruh foto beserta data formulir dapat dikirim sekaligus. Jika menjalankan
server dengan konfigurasi PHP lain, atur `upload_max_filesize` minimal `4M`
dan `post_max_size` minimal `20M`, lalu mulai ulang server PHP.

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
17. Buat/masuk menggunakan akun Admin. Owner mendaftar dari halaman publik dan kos aktifnya langsung dapat ditemukan tanpa persetujuan admin; User dapat mendaftar untuk booking. Pendaftaran meminta minimal salah satu email atau nomor HP, dan menerima foto profil opsional.
18. Untuk mengirim kode lupa password, set `KOSKOSAN_MAIL_FROM` ke alamat pengirim yang valid dan konfigurasikan transport email PHP pada mesin server. Variabel lama `ARAWEB_MAIL_FROM` tetap didukung untuk konfigurasi yang sudah ada. Untuk PHP CLI di WSL, periksa berkas konfigurasi dengan `php --ini`; jika mengubah konfigurasi, mulai ulang proses PHP development server. Kode hanya berlaku 15 menit, satu kali pakai, maksimal 5 percobaan; permintaan ulang dibatasi 60 detik. Jika email tidak dapat dimasukkan ke antrean, aplikasi mencatat kegagalan ke log server.
19. Untuk menambahkan data uji lokal, set `ARA_DEMO_PASSWORD` ke kata sandi acak minimal 12 karakter di lingkungan terminal, lalu jalankan `php seed_demo_data.php`. Seeder membuat 3 akun Owner, 3 listing berisi masing-masing 5 kamar, 9 akun User, sewa aktif, dan enam periode tagihan/pembayaran per sewa. Data rekening demo ditandai agar tidak digunakan untuk transfer nyata; semua akun demo menggunakan kata sandi yang sama dari variabel lingkungan tersebut. Seeder berhenti jika data demo sudah ada.
20. Setelah backup, jalankan `migration_account_activity_and_deletion.sql` satu kali setelah migrasi multi-owner dan business flow. Migrasi ini menambahkan log aktivitas akun dan mempertahankan histori booking/pencarian saat akun User dihapus. Jangan jalankan ulang.
21. Setelah backup, jalankan `migration_property_and_room_profile_photos.sql` satu kali setelah migrasi `migration_multi_owner_platform.sql` dan `migration_room_photos.sql`. Migrasi ini menambahkan kolom foto profil terpisah untuk kos dan kamar. Foto galeri lama tetap digunakan sebagai sampul jika foto profil belum diunggah. Jangan jalankan ulang.
22. Setelah backup, jalankan `migration_room_type_gallery.sql` satu kali setelah migrasi gedung dan tipe kamar. Migrasi ini membuat galeri foto bersama per gedung dan tipe kamar untuk foto gedung, kamar, fasilitas kamar, dan fasilitas umum. Foto gedung akan masuk ke galeri profil kos; foto kamar dapat dipilih sebagai foto profil unit bertipe sama. Jangan jalankan ulang.
23. Setelah backup, jalankan `migration_indonesia_regions.sql` satu kali setelah tabel `users`, `kos_buildings`, dan kolom alamat profil tersedia. Migrasi ini mengimpor 91.599 wilayah ke `indonesia_regions` dan menambahkan kode wilayah serta RT/RW/jalan/detail alamat pada gedung dan profil akun. Jangan jalankan ulang karena migrasi juga menambahkan kolom dan foreign key.
24. Setelah backup, jalankan `migration_integrated_property_account_features.sql` satu kali setelah tabel `users`, `tenants`, `kos_buildings`, `rooms`, `room_type_gallery_photos`, `property_reviews`, dan `user_auth_providers` tersedia. Migrasi ini menambahkan pengaturan privasi/siklus akun, klasifikasi dan permintaan verifikasi per properti, aksi simpan/laporan kos, penautan foto galeri ke unit, serta reviewer User pada ulasan. Migrasi juga membuat relasi reviewer untuk ulasan lama. Jangan jalankan ulang.
25. Jalankan `migration_notifications.sql` satu kali untuk preferensi dan notifikasi per User; lalu jalankan `migration_chat.sql` satu kali untuk percakapan User–Owner.
26. Jalankan `migration_chat_support_admin.sql` satu kali setelah `migration_chat.sql` untuk mengaktifkan percakapan bantuan User/Owner–Admin, arsip yang dapat dipulihkan, dan daftar chat Admin. Migrasi memerlukan tabel chat dari langkah sebelumnya; ambil backup sebelum menjalankan karena migrasi mengubah struktur `chat_conversations`.
27. Setelah backup, jalankan `migration_activity_log_and_web_push.sql` satu kali untuk membuat log aktivitas dan penyimpanan subscription Web Push. Migrasi memerlukan `users` dan `kos_buildings`; jalankan dengan akun database khusus yang berhak DDL, bukan akun aplikasi. Jangan jalankan ulang setelah berhasil. Pada lingkungan proyek yang diperiksa 2026-10-19, pengguna melaporkan migrasi berhasil dan mengonfirmasi kedua tabel baru tersedia.

### Notifikasi browser/perangkat dan log

- Web Push memerlukan HTTPS (kecuali localhost), service worker, izin notifikasi browser, dan konfigurasi VAPID pada server. Buat kunci P-256 di luar direktori publik; jangan masukkan private key ke Git:

  ```bash
  sudo install -d -m 700 /etc/koskosan
  sudo openssl ecparam -name prime256v1 -genkey -noout -out /etc/koskosan/vapid-private.pem
  sudo chmod 600 /etc/koskosan/vapid-private.pem
  php -r '$key=openssl_pkey_get_private(file_get_contents($argv[1])); $ec=openssl_pkey_get_details($key)["ec"]; $x=$ec["x"]; $y=$ec["y"]; if(strlen($x)===64&&ctype_xdigit($x))$x=hex2bin($x); if(strlen($y)===64&&ctype_xdigit($y))$y=hex2bin($y); $raw="\x04".$x.$y; echo rtrim(strtr(base64_encode($raw),"+/","-_"),"="),"\n";' /etc/koskosan/vapid-private.pem
  ```

  Simpan hasil public key tersebut sebagai `KOSKOSAN_VAPID_PUBLIC_KEY`, isi `KOSKOSAN_VAPID_PRIVATE_KEY` dengan PEM private key dari file terlindungi itu, dan isi `KOSKOSAN_VAPID_SUBJECT` dengan URL kontak `mailto:` atau URL layanan. Atur ketiganya melalui secret/environment manager layanan PHP, lalu mulai ulang worker/server PHP. Jangan menaruh private key di `config.php`, repository, web root, atau log.
- User dapat mengaktifkan izin browser dari **Pengaturan akun → Pemberitahuan browser dan perangkat**. Toggle **Sembunyikan notifikasi** menjadi mute aplikasi: notifikasi tetap tersimpan dan badge lonceng tetap terhitung, tetapi toast/push baru tidak dikirim. Izin browser/perangkat tetap kontrol terpisah; browser/OS dapat membisukan notifikasi. iPhone umumnya mensyaratkan situs ditambahkan ke Home Screen. Mode Focus/Do Not Disturb yang dikelola OS tidak selalu dapat dideteksi oleh aplikasi.
- Popup lonceng menampilkan lima notifikasi terbaru dan tautan ke halaman Notifikasi. Popup ikon pesan membuka Chat atau Ulasan; log aktivitas Admin dapat difilter role, akun, jenis, dan hasil; Owner/User hanya mendapat data sesuai cakupan aksesnya.
- Log mencatat tindakan dan kegagalan tanpa password, token, payload formulir mentah, atau alamat IP. Log transaksi/properti Owner hanya terlihat dalam cakupan gedung yang dimilikinya. User hanya dapat melihat log milik akunnya.
28. Pastikan migrasi fitur platform yang dibutuhkan pada langkah 24–27 sudah tersedia sebelum memakai seluruh fitur. Periksa skema aktual terlebih dahulu dan jalankan hanya migrasi yang belum diterapkan, memakai akun database berhak DDL setelah backup dapat dipulihkan. Akun aplikasi tidak memerlukan hak DDL. Entry point memeriksa kolom dan tabel wajib; jika belum lengkap, situs menampilkan petunjuk migrasi dengan status HTTP 503. Skema satu lingkungan yang telah dimigrasikan tidak membuktikan instalasi database lain sudah siap.
29. Setelah backup yang dapat dipulihkan, jalankan `migration_usernames_and_owner_role.sql` satu kali setelah `migration_account_activity_and_deletion.sql` dan `migration_activity_log_and_web_push.sql`. Migrasi ini mengisi username huruf-only yang unik untuk akun lama tanpa mengubah ID akun, mengubah role `operator` menjadi `owner` di akun dan log, lalu menetapkan indeks username case-insensitive. Jangan jalankan ulang dan jangan jalankan sebelum kedua tabel log tersebut tersedia. Aplikasi menampilkan HTTP 503 sampai skema dan enum role selesai dimigrasikan.
30. Setelah backup yang dapat dipulihkan, jalankan `migration_chat_inbox_visibility.sql` satu kali setelah migrasi chat. Migrasi ini menyiapkan arsip lama untuk folder personal; jangan jalankan ulang jika tabel `chat_conversation_hidden` sudah tersedia.
31. Setelah backup yang dapat dipulihkan, jalankan `migration_shared_owner_bank_accounts.sql` satu kali. Migrasi ini mempertahankan detail rekening per gedung, menambahkan katalog rekening bersama per Owner, dan menghubungkan rekening lama ke katalog tanpa mengubah petunjuk transfer per kos. Jangan jalankan ulang setelah kolom `bank_transfer_settings.bank_account_id` tersedia.
32. Setelah langkah 30 dan backup yang dapat dipulihkan, jalankan `migration_chat_personal_folders.sql` satu kali. Migrasi ini menambah status Kotak Masuk/Arsip/Sampah per akun dan event kedaluwarsa Sampah 30 hari. Aktifkan MySQL/MariaDB `event_scheduler` untuk pembersihan harian; request Chat juga menjalankan pembersihan sebagai fallback. Percakapan baru dihapus dari database setelah seluruh salinan yang tercatat sudah dibersihkan. Jangan jalankan ulang.
33. Setelah backup, jalankan `migration_owner_finances.sql` satu kali untuk membuat pencatatan pengeluaran Owner. Migrasi ini membutuhkan tabel `users`; jangan jalankan ulang jika kedua tabel pengeluaran sudah tersedia.

## Struktur modul

Kode aplikasi memakai satu database relasional dan sebelas modul domain yang terintegrasi. `index.php` menjadi entry point dan router inti; halaman serta aksi domain dimuat dari modul yang sesuai. Tampilan Owner merupakan bagian dari modul properti, booking, dan pembayaran—bukan modul bisnis terpisah.

- `core/`: bootstrap dan pemetaan halaman ke modul; `index.php`, `config.php`, dan `functions.php` menjadi bagian inti aplikasi.
- `accounts/`: pendaftaran, login Facebook/email, profil, manajemen password, dan pemulihan akun melalui email.
- `admin/`: Dashboard platform gabungan ringkasan dan laporan, daftar kos, manajemen akun, profil Owner/User, dan aksi admin.
- `portal/`: pencarian publik, detail kos, riwayat pencarian, dan ulasan.
- `properties/`: dashboard operasional serta pengelolaan kos/gedung, kamar, pelanggan, dan sewa.
- `bookings/`: formulir dan daftar booking pencari, permintaan Owner, serta keputusan booking.
- `payments/`: tagihan, rekening kos, pencatatan/verifikasi pembayaran, dan penyajian bukti transfer terbatas.
- `finances/`: ringkasan pemasukan terkonfirmasi, cicilan, serta pengeluaran rutin dan lainnya khusus Owner.
- `reports/`: analitik Dashboard platform/Owner, laporan keuangan User, dan log aktivitas berfilter sesuai role.
- `chat/`: widget percakapan privat User–Owner dan bantuan Admin, dengan folder Kotak Masuk, Arsip, dan Sampah per akun pada semua role.
- `notifications/`: notifikasi persisten, preview popup, preferensi senyap, dan API subscription Web Push.

Modul memakai koneksi, sesi, dan helper yang disediakan inti aplikasi. Pemetaan route berada di `core/modules.php`; form dan aksi masing-masing berada di direktori domain. Pemisahan modul tidak mengubah skema database; jalankan migrasi yang tercantum pada langkah instalasi untuk fitur yang memerlukannya.

## Fitur

- Ringkasan pelanggan aktif, kamar terisi, okupansi, dan sisa tagihan.
- Platform multi-pemilik dengan tiga peran yang tampil di aplikasi: Admin, Owner, dan User. Setiap akun memiliki satu role; kos aktif Owner langsung tampil tanpa persetujuan admin; Owner hanya dapat mengelola kos miliknya; User dapat menjelajah tanpa login.
- Username unik huruf A-Z (tanpa membedakan kapitalisasi) dapat dipakai untuk login dan diubah melalui profil. ID akun tetap dibuat sistem dan tidak dapat diedit. Akun lama mendapat username awal huruf-only saat menjalankan `migration_usernames_and_owner_role.sql`.
- Pencarian publik mencocokkan nama, alamat, wilayah administratif, jenis kos Putra/Putri, dan dapat mengurutkan semua hasil menurut lokasi browser terdekat tanpa batas radius. Semua kamar kos terpublikasi ditampilkan termasuk yang terisi; identitas penghuni tidak ditampilkan ke publik. Booking hanya ditawarkan untuk kamar tersedia.
- Beranda menampilkan carousel daftar kamar di bawah daftar kos, dengan data kamar, kos, ketersediaan, foto, dan harga.
- Booking memerlukan akun pencari. Owner menyetujui atau menolak booking; setelah disetujui, pencari memiliki waktu 24 jam untuk membayar dan mengunggah bukti transfer.
- Rekening transfer dan petunjuk pembayaran tetap terkait pada masing-masing kos. Owner dapat memilih satu rekening tersimpan untuk dipakai beberapa gedung, atau membuat rekening baru. Perubahan detail rekening bersama tersinkron ke seluruh gedung yang menggunakannya, sedangkan petunjuk transfer tetap dapat berbeda per kos. Penghuni hanya dapat melihat histori pembayaran sendiri; Owner dibatasi pada histori penghuni dan transaksi kos miliknya; admin platform dapat mengelola semua akun. Kode lupa password email khusus akun pengguna aktif.
- Profil Admin, Owner, dan User menyediakan nama, username, email, nomor HP, alamat wilayah Indonesia berjenjang, RT/RW, jalan/detail alamat, jenis kelamin, serta unggah/ganti foto profil tunggal. ID akun otomatis dari primary key ditampilkan baca-saja di profil, daftar Penghuni, dan manajemen akun tanpa migrasi ID tambahan. Avatar pria/wanita digunakan otomatis bila foto tidak tersedia. Kolom alamat dan gender ditambahkan melalui `migration_account_profiles.sql` dan `migration_account_gender.sql`.
- Pengaturan akun menyediakan preferensi notifikasi, privasi nomor/foto publik, penggantian kontak, dan siklus nonaktif/hapus akun User. Penghapusan menunggu tujuh hari, lalu identitas akun dan data kontak penghuni dianonimkan tanpa menghapus histori transaksi; pemrosesan terjadwal berjalan saat aplikasi menerima request.
- User dapat menyimpan, membagikan, atau melaporkan kos; Admin meninjau laporan dan pengajuan verifikasi properti dari satu halaman. Tipe Basic/Premium/Eksklusif adalah tingkat verifikasi keaslian, bukan klasifikasi kemewahan. Owner mengajukan verifikasi, Admin menyetujui atau menolak.
- User terdaftar dapat memberi rating bintang dan ulasan kos. Chat tampil sebagai widget kanan bawah untuk akun yang masuk: User/Owner dapat menghubungi pihak sesuai percakapan atau meminta bantuan Admin; Admin membalas percakapan bantuan dan meninjau chat User–Owner melalui halaman khusus. Tekan Enter untuk mengirim pesan dan Shift+Enter untuk membuat baris baru. Draft pada widget dipisahkan per percakapan agar tidak ikut terbawa saat berpindah chat. User, Owner, dan Admin dapat mengarsipkan chat atau memindahkannya ke Sampah tanpa mengubah folder peserta lain; Sampah dapat dipulihkan atau dikosongkan permanen kapan saja, dan entri di Sampah kedaluwarsa setelah 30 hari. Menu Chat/Notifikasi tidak ditampilkan di sidebar User/Owner; tersedia popup pesan Chat/Ulasan dan popup lonceng yang tetap dapat diklik saat pointer berpindah dari ikon. Halaman pembayaran Owner hanya menampilkan nama lengkap dan username pada hasil pencarian akun.
- Tombol WhatsApp memakai tautan `wa.me` agar perangkat membuka aplikasi WhatsApp atau WhatsApp Web sesuai perangkat. Tautan kontak tersedia untuk Owner, Admin, dan penghuni; fitur ini tidak menautkan akun WhatsApp ke sistem atau mengirim pesan otomatis.
- Dashboard Owner menyediakan Properti (Gedung dan Kamar), Penghuni, Input Pembayaran, Histori pembayaran, Booking, serta grafik pemasukan dan aktivitas per kos. Nilai pemasukan bersumber dari pembayaran terkonfirmasi; total bulanan/tahunan, pemasukan per properti, pembayaran menunggu, dan tunggakan ditampilkan terpisah. Data aktivitas dan Log dibaca dari database bersama lalu dibatasi sesuai role/izin; status listing dicatat untuk Admin, bukan ditampilkan sebagai notifikasi berulang pada dashboard Owner. Menu Kamar mengelompokkan penghuni menurut kamar; menu Penghuni menyediakan histori, reset password melalui dialog konfirmasi, pindahkan kamar, dan keluarkan. Input pembayaran dapat membuat hunian bagi akun KosKosan yang belum memiliki hunian atau akun baru, memakai harga kamar atau nominal khusus transaksi, serta mencatat metode dan periode pembayaran. Pemindahan penghuni mempertahankan sewa, tagihan, dan pembayaran; pengakhiran sewa tidak menghapus atau menonaktifkan akun.
- Dashboard User hanya menampilkan ringkasan tagihan dan pembayaran milik akun tersebut; tabel tagihan dipindahkan ke menu **Tagihan dan pembayaran** bersama riwayat transaksi, dan dapat difilter menurut kos serta ditampilkan sebagai tabel atau kartu. Beranda publik tidak menampilkan ringkasan akun atau pencarian terakhir.
- Popup pencarian tunggal terbuka saat kolom pencarian dipilih. Popup memuat filter jenis kos, destinasi populer dari data wilayah kos aktif, dan **Dekat saya**; aksi tersebut meminta izin lokasi browser lalu mengurutkan hasil berdasarkan koordinat kos. Kotak pencarian utama tetap satu input dan tombol Cari.
- Menu pesan Owner/User menyediakan Pusat Bantuan yang langsung membuka percakapan dukungan akun itu dengan Admin. Pesan dikirim melalui sistem chat yang sama dan masuk ke inbox Bantuan Admin.
- Riwayat pembayaran User menyatukan semua kos/kamar ke satu tabel dengan filter kos. Kartu tabel tetap ringkas dan mendukung tampilan ponsel.
- Pembayaran terkonfirmasi memiliki struk cetak yang dapat disimpan sebagai PDF melalui dialog cetak browser. Pengiriman email membuka aplikasi email pengguna; kirim WhatsApp membuka aplikasi/WhatsApp Web dengan ringkasan pembayaran yang telah disiapkan.
- Admin dapat mengelola akun Owner dan User dari submenu terpisah, melihat avatar, serta membuka dan mengedit profil kedua jenis akun tanpa mengubah foto profil mereka. Filter wilayah Provinsi/Kabupaten/Kecamatan/Kelurahan tidak ditampilkan pada tabel; pemilih berjenjang tetap tersedia pada formulir alamat. Lokasi Owner berasal dari gedung yang dikelolanya; lokasi User aktif berasal dari gedung huniannya, sedangkan User tanpa hunian menggunakan alamat profil. Menu **User pengguna** menampilkan akun tanpa sewa atau booking aktif. Kolom Aktivitas terakhir menampilkan akses terbaru; Owner juga dapat melihat waktu aktivitas terakhir akun penghuni. Aktivitas disimpan sebagai peristiwa berkala (maksimal satu catatan per lima menit sesi) untuk penggunaan lanjutan. Admin dapat menghapus permanen akun User yang tidak memiliki sewa atau booking berjalan; data sewa, pembayaran, booking, pencarian, dan aktivitas tetap disimpan dengan relasi akun dilepas. Akun Owner tidak dapat dihapus melalui menu ini. Aksi manajemen akun dan pengeluaran penghuni meminta konfirmasi melalui kotak centang di dialog.
- Tabel manajemen menyediakan pencarian langsung saat mengetik, pilihan tampilan Tabel/Kartu, ukuran 5/10/Semua, dan ringkasan halaman ringkas. Tampilan awal setiap tabel/daftar adalah lima baris; preferensi pilihan Tabel/Kartu tetap disimpan per tabel pada browser. Daftar kamar dan penghuni dapat difilter berdasarkan gedung; daftar akun User admin dapat difilter berdasarkan kos.
- Setiap role memiliki menu **Aktivitas** dan **Log** yang membaca data dari sumber database terintegrasi dengan batas akses berbasis akun/role. Admin memiliki satu Dashboard; tabel aktivitas publik berada di menu Aktivitas dan Verifikasi menjadi menu tersendiri. Hapus Log Admin hanya menghapus baris `activity_logs`; metrik pencarian, kunjungan, kos dilihat, dan pengunjung tetap bersumber dari `property_activity_events` dan tidak ikut terhapus.
- Daftar booking Owner menampilkan keputusan booking dan konfirmasi pembayaran dalam satu daftar. Log Admin dapat dihapus berdasarkan filter aktif setelah konfirmasi. Tabel, toolbar, dan tombol manajemen dibuat lebih ringkas; daftar Gedung mendukung tampilan Tabel/Kartu.
- Satu akun User dapat memiliki lebih dari satu sewa aktif; satu data akun/tenant tetap dapat muncul sebagai penghuni pada setiap kamar yang disewa.
- Tambah, edit, serta nonaktifkan pelanggan.
- Tambah dan edit kamar serta status ketersediaan/perawatan.
- Kelola beberapa gedung kos beserta Provinsi, Kabupaten/Kota, Kecamatan, Kelurahan/Desa, RT/RW, jalan, Detail Alamat, dan koordinat latitude/longitude; nomor kamar boleh berulang di gedung yang berbeda. Pilihan wilayah dimuat berjenjang dari database. Form gedung menyediakan peta interaktif opsional untuk memilih/memindahkan pin dan mengisi alamat dari koordinat; menyimpan pembaruan tidak mengharuskan pemilik memilih lokasi di peta.
- Daftar gedung Owner ditampilkan sebagai kartu responsif dengan foto galeri, alamat, jumlah kamar, status, pencarian, paginasi, serta aksi Edit dan Nonaktifkan/Aktifkan.
- Halaman kamar menyediakan pilihan tampilan tabel dan kartu yang diingat per browser, filter tipe yang mengikuti gedung terpilih, dan carousel kartu kecil yang dikelompokkan menurut gedung serta tipe kamar. Tampilan kartu menyusun tipe hingga tiga kolom pada desktop dan satu kolom di ponsel; pilihan tampilan berada di sisi kanan kontrol. Panah carousel berada di sisi kartu dan outline warna lembut membedakan kelompok tanpa mengubah latar Light/Dark. Dropdown satu pilihan pada panel dan halaman publik mengikuti tema aplikasi dan dapat digunakan dengan mouse maupun keyboard. Admin mengakses fitur pengelolaan Gedung, Kamar, Galeri, dan Penghuni melalui menu **Managemen Properti** di bawah Manajemen akun.
- Profil publik kos membaca kamar dari database dan menampilkan satu carousel untuk setiap tipe yang tersedia, termasuk kamar yang sedang tidak dapat dibooking. Foto fasilitas kamar ditampilkan menurut tipe, foto fasilitas umum ikut ditampilkan, dan foto profil/galeri/lokasi tetap bersumber dari data gedung yang tersimpan. Carousel tipe diurutkan Standart, Premium, lalu Eksklusif bila tipe tersebut ada.
- Setiap kamar terhubung ke satu gedung. Nama gedung ditampilkan pada daftar kamar, booking, masa sewa, tagihan, dan pembayaran.
- Owner dapat mengunggah foto profil kos dan kamar secara terpisah dari galeri. Foto profil kos tampil di banner detail kos dan kartu kos di halaman depan; foto profil kamar tampil pada kartu kamar dan detail kamar. JPG/PNG/WebP maksimal 4 MB. Galeri dan form gedung memakai tombol **Upload foto** dengan pratinjau dan pilihan untuk membuang foto sebelum menyimpan; maksimal empat foto baru per kategori dalam satu kiriman.
- Filter tipe kamar galeri hanya menampilkan tipe yang tersedia pada gedung terpilih. Pilih foto di satu atau beberapa kategori, tautkan unggahan foto kamar ke unit tertentu bila perlu, lalu tekan **Simpan** untuk menyimpan semua pilihan sekaligus. Galeri Admin dan Owner menggunakan halaman dan sumber data yang sama.
- Navigasi panel pada ponsel memakai drawer berlabel yang dapat dibuka/tutup dengan tombol menu. Bukti pembayaran dibuka dalam popup, dan kontrol tema tetap berada sebelum menu profil.
- Tautan unduh Android sengaja belum ditampilkan sampai URL resmi aplikasi tersedia.
- Peta publik memakai Leaflet dan OpenStreetMap tanpa API key, menampilkan lokasi gedung aktif yang memiliki kamar tersedia beserta tipe kamar dan harga bulanan. Geocoding alamat memakai proxy server Nominatim dengan cache berdasarkan koordinat gedung tersimpan dan pembatasan satu permintaan per detik. Saat memilih titik, koordinat dikirim ke layanan OpenStreetMap; layanan peta/geocoding memerlukan koneksi internet dan mengikuti kebijakan penggunaan serta atribusi OpenStreetMap.
- Pilih tipe kamar dari Tipe Standart, Tipe Premium, dan Tipe Eksklusif.
- Status kamar Kosong/Perawatan diatur pengelola; Terisi/Pending mengikuti masa sewa dan booking. Booking yang disetujui tampil di data kamar beserta nama User dan status pembayarannya, tetapi baru menjadi penghuni aktif dan berstatus Terisi setelah tagihan lunas.
- Pencari dapat memilih masa sewa 1–12 bulan dan mengirim permintaan booking. Pembayaran dapat dilakukan setelah Owner menyetujuinya; booking yang disetujui menahan kamar selama 24 jam, sedangkan bukti yang sudah dikirim tetap menahan kamar sampai ditinjau.
- Rekening tujuan dan petunjuk transfer diatur per listing melalui menu **Rekening**. Owner meninjau bukti booking kosnya sendiri; hanya bukti yang disetujui yang melunasi tagihan dan mengaktifkan sewa.
- Bukti transfer berupa JPG, PNG, atau WebP hingga 4 MB. Berkas hanya dapat dilihat setelah login oleh pengelola atau pelanggan pemilik booking.
- Catat masa sewa dengan validasi bentrok tanggal kamar.
- Buat tagihan untuk periode sewa.
- Catat pembayaran penuh atau bertahap dengan metode tunai, transfer, e-wallet, dan lainnya.
- Status lunas diperbarui otomatis saat jumlah pembayaran terkonfirmasi mencapai total tagihan.
- Riwayat transaksi penghuni tersedia pada menu **Riwayat pembayaran**; penghuni tidak dapat melihat pembayaran pengguna lain.
- Halaman publik KosKosan menampilkan kos yang tersedia, tipe kamar, fasilitas, lokasi, serta harga bulanan tanpa memerlukan login.
- Dari menu **Kamar → Edit**, pengelola dapat mengunggah hingga 4 foto kamar dan 4 foto fasilitas sekaligus. Format JPG, PNG, dan WebP didukung dengan batas 4 MB per foto. Foto kamar pertama menjadi sampul; foto fasilitas ditampilkan sebagai galeri di halaman publik.

Dataset pada migrasi wilayah dipatok dari `cahyadsn/wilayah` commit `d68e8d5516f969d1905d0b2940f20034becb0db7`, bertanggal 13 Februari 2026, dan berlisensi MIT (pemberitahuan ada di berkas migrasi). Sumber mencantumkan Kepmendagri No. 300.2.2-2138/2025; data belum diverifikasi sebagai rilis Kemendagri paling mutakhir. Sebelum memperbarui data, verifikasi rilis resmi, simpan backup, serta perbarui atribusi dan jumlah data pada migrasi.

Folder `uploads/rooms`, `uploads/properties`, `uploads/profiles`, dan `uploads/payments` harus dapat ditulisi oleh pengguna yang menjalankan PHP. Koordinat peta serta foto kos/kamar bersifat opsional; jika foto belum tersedia, halaman publik menggunakan ilustrasi bawaan KosKosan. Tema Light/Dark dapat dipilih pada halaman panel, autentikasi, dan publik; preferensi tema disimpan di browser. Pembayaran online bukan payment gateway: transfer tetap dilakukan manual dan bukti harus diverifikasi Owner. Penghentian atau pembatalan sewa dan penonaktifan pelanggan menggunakan status, bukan penghapusan data, agar riwayat tagihan/pembayaran tetap terjaga.

Login Facebook untuk User menggunakan OAuth. Agar tombolnya aktif, set variabel lingkungan server `FACEBOOK_APP_ID`, `FACEBOOK_APP_SECRET`, dan `FACEBOOK_REDIRECT_URI` (URL callback persis `https://domain-anda/ekosan/index.php?page=facebook_callback`) lalu daftarkan URL callback yang sama pada aplikasi Meta. Jangan menyimpan App Secret di source code. Login email tetap tersedia.
