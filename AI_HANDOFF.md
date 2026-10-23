# AI / Developer Handoff

Panduan orientasi untuk AI atau developer yang melanjutkan KosKosan. Dokumen ini
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

- Aplikasi web manajemen kos KosKosan menggunakan PHP native, PDO, dan
  MySQL/MariaDB.
- Di lingkungan WSL saat ini, proyek berada di
  `/home/alan_hatomaule/projects/ekosan`. Lingkungan yang diperiksa memakai
  PHP CLI dan MariaDB; Apache tidak terpasang. Untuk pengembangan lokal,
  jalankan `php -d upload_max_filesize=4M -d post_max_size=20M -S 127.0.0.1:8000 -t .`
  dari direktori proyek dan buka `http://localhost:8000/` melalui browser
  Windows/WSL. Batas ini diperlukan agar upload sesuai batas aplikasi
  (maksimal 4 MB per foto, hingga empat foto galeri per kiriman).
- PHP development server hanya untuk pengembangan lokal dan tidak menerapkan
  aturan `.htaccess`; jangan menganggapnya sebagai konfigurasi deployment.
- Tidak ada framework aplikasi atau sistem build frontend yang menjadi bagian
  dari pola utama proyek. Antarmuka menggunakan PHP, `style.css`, dan
  `app.js`.
- Nama produk yang tampil kepada pengguna adalah `KosKosan`; identifier
  teknis lama seperti akun database `araweb_app` dan nama repository tetap
  dipertahankan untuk kompatibilitas. Variabel `ARAWEB_REVERSE_GEOCODER_URL`
  dan `ARAWEB_MAIL_FROM` tetap diterima sebagai alias untuk konfigurasi
  deployment lama. Tema Light/Dark disimpan pada browser
  melalui `localStorage` dan kontrolnya tersedia di panel, autentikasi, serta
  halaman publik.
- Koneksi database dikonfigurasi di `config.php`; variabel lingkungan yang
  dikenali adalah `DB_HOST`, `DB_NAME`, `DB_USER`, dan `DB_PASS`. Jangan
  menaruh rahasia di source.
- README menyebut database `manajemen_kos` dan menjelaskan migrasi yang pernah
  diperlukan. Di WSL, pastikan MariaDB berjalan dan kredensial
  `DB_HOST`/`DB_NAME`/`DB_USER`/`DB_PASS` cocok dengan database lokal; nilai
  bawaan lokal saat ini adalah `localhost`/`manajemen_kos`/`araweb_app` dengan
  kata sandi kosong untuk autentikasi socket. Instalasi lokal/target dapat
  berbeda; jangan anggap seluruh migrasi sudah pernah dijalankan.

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
| `chat/` | Percakapan privat antara User dan operator |
| `notifications/` | Feed, badge, dan preferensi notifikasi akun |
| `style.css`, `app.js` | Tampilan dan interaksi frontend |
| `uploads/` | Foto properti/kamar/profil dan bukti pembayaran; perlakukan sebagai data |
| `migration_*.sql` | Perubahan skema bertahap; bukan skrip yang aman dijalankan ulang |

Operator mengelola galeri foto bersama berdasarkan gedung dan tipe kamar melalui
`index.php?page=gallery`. Tabel `room_type_gallery_photos` memisahkan foto
gedung, foto kamar, fasilitas kamar, dan fasilitas umum. Perubahan skema
memerlukan `migration_room_type_gallery.sql` satu kali; lihat urutan migrasi di
README sebelum memperbarui database target.
Pemilih galeri hanya mencantumkan tipe kamar yang benar-benar tersedia pada
gedung terpilih. Pilih foto pada satu atau beberapa kategori, tautkan foto
kamar yang baru diunggah ke unit tertentu jika diperlukan, lalu kirim semuanya
dengan tombol **Simpan**; handler membaca
`gallery_photos_{category}` dan memvalidasi maksimal empat foto per kategori.
Foto galeri kategori kamar dapat langsung dijadikan foto profil dari kartu
galeri dengan memilih unit tujuan; halaman Edit Kamar menyediakan aksi yang
sama pada foto bersama. Penghapusan foto harus melalui dialog konfirmasi
keamanan dengan checkbox, dan handler POST juga memverifikasi checkbox tersebut.
Pemilih berkas galeri dan Edit Kamar disembunyikan; gunakan tombol **Upload
foto** dan pratinjau terpilih, jangan mengembalikan input `Choose File` native.
Form edit gedung menampilkan pratinjau foto galeri segera setelah dipilih;
input file disembunyikan dan tombol **Upload foto** membuka pemilih untuk menambahkan pilihan,
dan tombol simpan gedung mengirim semua foto baru bersama perubahan data.
Perubahan gedung dan catatan galeri disimpan dalam satu transaksi database;
berkas baru dibersihkan bila penyimpanan gagal.
Daftar gedung operator memakai kartu responsif berisi sampul foto, alamat,
jumlah kamar, status, tautan lokasi, dan aksi edit/nonaktifkan. Pencarian dan
paginasi kartu berada di `app.js`; pertahankan fungsi tersebut saat mengubah
tampilan daftar.
Admin mengakses pengelolaan Gedung, Kamar, Galeri, dan Penghuni dari grup
sidebar **Managemen Properti** di bawah Manajemen akun. `rooms.php` memiliki
dropdown tampilan Tabel/Kartu yang mengingat pilihan per browser; filter tipe
kamar mengikuti gedung yang dipilih, sedangkan tampilan kartu dikelompokkan
per gedung dan tipe dalam carousel. Tipe kamar ditata dua kolom pada desktop
dan satu kolom pada ponsel; dropdown tampilan berada di sisi kanan kontrol.
Carousel kamar menampilkan tepat dua kartu pada satu waktu; panah berpindah satu
lebar track. Kartu antar tipe memakai ukuran seragam; jarak antar tipe dibuat
lega. Tipe Eksklusif membentang selebar baris dengan track rata kiri dan lebar
penuh, tidak dipusatkan; tidak ada bingkai pemisah tipe berwarna.
Kartu kos dan kamar publik, kos tersimpan, daftar gedung, dan kartu kamar
operator mengikuti pola foto besar di atas, ringkasan di bawah, label konteks
di atas foto, serta bayangan halus untuk membedakan kartu dari latar. Pertahankan
perilaku tautan/aksi, keterbacaan tema gelap, dan susunan satu kolom pada ponsel
saat menyesuaikan tampilan kartu tersebut.
Semua elemen `<select>` satu pilihan pada halaman panel dan publik ditingkatkan
menjadi dropdown aksesibel bertema; elemen native tetap menyimpan nilai form,
sementara daftar opsi menyesuaikan tema, mendukung keyboard, dan menutup saat
klik di luar. Select multiple/listbox tetap native.

Profil kos publik pada `portal/public.php` membaca seluruh kamar beserta status
ketersediaannya, lalu menampilkan carousel terpisah untuk setiap tipe (urut
Standart, Premium, Eksklusif bila ada). Foto profil kamar atau foto kamar galeri
menjadi sampul; foto fasilitas galeri ditampilkan mengikuti tipe kamar dan foto
fasilitas umum mengikuti data galeri gedung. Foto profil/galeri kos, alamat, dan
koordinat lokasi tetap bersumber dari data gedung yang tersimpan. Halaman ini
dipakai baik pengunjung publik maupun operator/admin yang membuka profil kos.

Form galeri tipe kamar dan foto profil kos menggunakan tombol **Upload foto**
dengan pratinjau, nama/ukuran, dan opsi membuang pilihan sebelum simpan. Validasi
browser mengikuti batas server: JPG/PNG/WebP maksimal 4 MB per foto; galeri
menerima hingga empat foto baru per kategori per kiriman.

Tabel pada halaman manajemen menggunakan kontrol bersama di `app.js` untuk
pencarian per tabel, 10 baris per halaman atau semua baris, serta navigasi
sebelumnya/berikutnya. Tabel kamar, penghuni, dan akun User admin menyediakan
filter dropdown gedung/kos; jangan hilangkan metadata gedung pada baris saat
mengubah query atau markup halaman tersebut.

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
- Form edit gedung memperbarui data melalui `save_building`; pemilihan lokasi
  peta bersifat opsional. Peta memakai Leaflet/OSM, dan reverse geocoding
  diproksikan melalui `index.php?reverse_geocode=1`; endpoint membatasi akses
  ke admin/operator, memakai lock database untuk membatasi permintaan
  Nominatim maksimal satu per detik, dan memakai alamat gedung tersimpan sebagai
  cache untuk koordinat yang sama. Jika geocoding gagal, simpan tetap
  menggunakan alamat terstruktur. Ganti endpoint melalui variabel lingkungan
  `ARAWEB_REVERSE_GEOCODER_URL` dengan layanan `/reverse` yang kompatibel;
  jangan menghapus batas permintaan atau atribusi OSM tanpa mengganti layanan
  dan kebijakannya.
- Filter Provinsi/Kabupaten-Kota/Kecamatan/Kelurahan-Desa pada daftar kos admin
  menggunakan `data-region-filter` dan kode wilayah pada baris tabel, bersama
  helper tabel di `app.js`.

## Database dan migrasi

- Tabel yang tampak dalam source/migrasi mencakup akun (`users`), properti
  (`kos_buildings`), kamar (`rooms`), penghuni (`tenants`), masa sewa
  (`rentals`), tagihan (`invoices`), pembayaran (`payments`), ulasan, pencarian,
  booking, rekening, dan `indonesia_regions`.
- `migration_indonesia_regions.sql` menyediakan data wilayah berjenjang
  Provinsi/Kabupaten-Kota/Kecamatan/Kelurahan-Desa, serta kolom kode wilayah
  dan alamat rinci pada gedung dan profil akun. Migrasi memuat 91.599 baris
  dari snapshot `cahyadsn/wilayah` commit
  `d68e8d5516f969d1905d0b2940f20034becb0db7` (13 Februari 2026); sumber
  mencantumkan Kepmendagri No. 300.2.2-2138/2025, tetapi belum diverifikasi
  sebagai rilis Kemendagri terbaru. Lisensi MIT dan atribusi dipertahankan
  pada berkas migrasi.
- Filter wilayah pada manajemen akun admin memakai lokasi semua gedung milik
  Operator, lokasi gedung aktif untuk User yang sedang menghuni, dan alamat
  profil untuk User tanpa hunian. Pilihan dropdown wilayah dimuat melalui
  endpoint berjenjang; jangan kirim seluruh desa ke browser.
- Kolom fisik `address_block` tetap digunakan demi kompatibilitas, tetapi label
  antarmuka untuk nilai itu adalah “Detail Alamat”, bukan “Blok”.
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

## Catatan sesi terbaru (16 Oktober 2026)

- Merek UI dan komunikasi pengguna sekarang **KosKosan** (bukan
  `koskosan.my.id`). Nama repository GitHub, akun database `araweb_app`,
  alamat email demo `@araweb.test`, class placeholder/identifier teknis, dan
  nama variabel lama tidak otomatis diganti karena berpotensi memutus
  kompatibilitas. `KOSKOSAN_REVERSE_GEOCODER_URL` dan `KOSKOSAN_MAIL_FROM`
  menjadi nama konfigurasi baru; variabel `ARAWEB_REVERSE_GEOCODER_URL` dan
  `ARAWEB_MAIL_FROM` tetap didukung sebagai fallback.
- Pemilih tema Light/Dark memakai dropdown HTML kustom yang dibangun oleh
  `theme_switcher()` di `functions.php`; `app.js` menyimpan pilihan pada
  `localStorage` dengan key `koskosan-theme`, menyinkronkan kontrol, menutup
  dropdown dengan Escape/klik luar, dan fokus kembali ke pemilih tema. Aturan
  tema termasuk menu publik, panel, form autentikasi, dan dropdown berada di
  `style.css`. Hormati `prefers-reduced-motion`.
- Navigasi beranda publik diseragamkan dengan tombol menu berbingkai,
  dropdown tema yang lebih terarah, serta menu responsif. Tata letak browser
  telah diuji pada lebar 320–1280 px tanpa overflow horizontal; pengujian
  mencakup halaman login dan penyimpanan tema. Uji lagi dengan sesi/role yang
  dibutuhkan jika mengubah navigasi panel.
- Daftar kos Admin ada di `admin/datakost/form.php` lewat route
  `index.php?page=platform_listings`. Tautan nama kos membuka profil publik
  melalui `index.php?page=property&id=...`; avatar bulat baru juga harus
  menuju URL yang sama. Foto profil kos didahulukan, lalu foto properti
  pertama, lalu foto galeri gedung pertama; jika tidak tersedia, tampilkan
  huruf K. CSS avatar berada di `style.css`. Jangan mengubah tautan operator
  di kolom berikutnya.
- Database lokal saat diperiksa terakhir: MariaDB 11.8 aktif; `db()` pada
  konfigurasi default sukses ke `manajemen_kos` sebagai
  `araweb_app@localhost`. Pesan error database yang sempat tampil disebabkan
  override terminal `DB_USER='nama-pengguna-mariadb'` (placeholder dokumentasi)
  beserta kredensial tidak valid, bukan database mati. Hapus override dengan
  `unset DB_HOST DB_NAME DB_USER DB_PASS`, lalu tes koneksi memakai
  `php -r 'require "config.php"; db(); echo "Koneksi database berhasil\n";'`.
- Server PHP lokal sempat dijalankan di `127.0.0.1:8000`; port yang sedang
  terpakai berarti server sudah berjalan, bukan alasan menjalankan instance
  kedua. Periksa dengan `curl -I http://127.0.0.1:8000/`; untuk mengganti
  instance, hentikan proses yang sesuai dengan `Ctrl+C` pada terminal pemilik
  proses. Buka `http://localhost:8000/`.
- Pengguna mendorong commit dari terminal Linux secara manual; bila perlu,
  berikan perintah `cd ~/projects/ekosan && git push origin main`. Jangan
  mencoba push tanpa diminta.
- Perubahan lokal di `uploads/` pernah tampak sebagai penghapusan/berkas baru
  dan bukan bagian perubahan fitur. Selalu periksa status sebelum stage; jangan
  masukkan, hapus, atau pulihkan data upload ini tanpa instruksi.

## Keadaan terakhir yang diketahui

- Branch terakhir yang diketahui: `main`.
- Commit terakhir yang diketahui sebelum fitur daftar kos/handoff sesi ini:
  `ada66dc` (`Clarify local database credential setup`). Riwayat baru yang
  terkait UI/brand: `bd29c69` (`Add koskosan.my.id branding and themes`),
  `7d12e47` (`Refine KosKosan branding and interactions`), dan `ada66dc`.
- Riwayat fitur yang penting sebelum penggantian merek: `d44ec60` (lokasi peta
  dan filter wilayah), `f87c949` (posisi kontrol jumlah baris tabel), dan
  `7d16e06` (default database lokal dan cache aset). Commit sebelumnya dapat
  berisi perubahan yang sudah dikirim user; jangan mengasumsikan remote sama
  dengan lokal tanpa membandingkan.
- Pada awal sesi ini `main` dan `origin/main` sama-sama berada di `ada66dc`;
  perubahan avatar kos dan dokumentasi sedang dikerjakan. Periksa ulang
  `git status --short --branch` dan `git log -5 --oneline` ketika mulai lagi;
  jangan menganggap perubahan sudah dipush atau status branch masih sama.
- Langkah berikut untuk AI esok: verifikasi commit/status terbaru, konfirmasi
  `admin/datakost/form.php` serta README/handoff sudah tercatat, lalu buka
  `index.php?page=platform_listings` memakai sesi Admin jika tersedia. Pastikan
  setiap avatar (foto/fallback K) membuka profil properti yang sama dengan
  tautan nama, tema Light/Dark tetap tersimpan, dan filter wilayah/paginasi
  daftar kos tidak terganggu.
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
- Log/ulasan: `reports/activity_log.php`, `reports/overview.php`,
  `reviews/form.php`
- Push/log core: `core/activity_log.php`, `core/web_push.php`,
  `notifications/api.php`, `service-worker.js`,
  `migration_activity_log_and_web_push.sql`
- Setup dan gambaran fitur: `README.md`

## Integrasi fitur dan sumber kebenaran

- Sistem tetap memakai satu database `manajemen_kos`. `properties` adalah
  sumber kebenaran gedung/kamar/foto; `accounts` mengelola preferensi dan
  siklus akun; `portal` mengelola ulasan serta aksi simpan/lapor; folder domain
  `chat/` dan `notifications/` hanya memiliki tanggung jawab percakapan dan
  pemberitahuan, tanpa menyimpan salinan data gedung/kamar/akun.
- Migrasi fitur lanjutan terpisah dari implementasi kode:
  `migration_integrated_property_account_features.sql`,
  `migration_notifications.sql`, `migration_chat.sql`,
  `migration_chat_support_admin.sql`, `migration_chat_inbox_visibility.sql`, lalu
  `migration_activity_log_and_web_push.sql`.
  Jalankan hanya migrasi yang terbukti belum diterapkan, sekali, setelah backup
  yang dapat dipulihkan dan hanya dengan akun database berhak DDL. Migrasi
  chat support mengubah struktur `chat_conversations`; jangan ulangi migrasi
  one-time. Pengguna menyatakan migrasi notifikasi, chat, dan chat support
  akhirnya berhasil diterapkan. Pada 2026-10-19 pengguna juga menjalankan
  `migration_activity_log_and_web_push.sql`; query `information_schema` dari
  pengguna mengonfirmasi tabel `activity_logs` dan `web_push_subscriptions`
  tersedia. Backup sebelum migrasi dibuat di
  `~/backups/koskosan/manajemen_kos_before_activity_push_20261019_142139.sql.gz`;
  `gzip -t` berhasil, tetapi restore belum diuji. Entry point memeriksa 13
  kolom dan 8 tabel fitur sebelum menjalankan route; saat pemeriksaan setelah
  migrasi, HTTP lokal root sudah 200 dan menampilkan halaman publik KosKosan.
- Ketergantungan skema utama: privasi/siklus akun berada di kolom `users`;
  permintaan klasifikasi berada di `property_verification_requests`, sedangkan
  klasifikasi yang ditampilkan berada di `kos_buildings`;
  `property_user_actions` menyimpan simpan/laporan; `property_reviews`
  menerima `reviewer_user_id`; foto galeri kamar opsional memiliki `room_id`;
  percakapan tersimpan di `chat_conversations`/`chat_messages`; notifikasi
  tersimpan di `notifications` dengan preferensi tunggal per User di
  `notification_preferences`.
- Pengguna mengizinkan implementasi lanjutan: popup lonceng terbaru dengan link
  ke halaman Notifikasi; popup pesan Chat/Ulasan; preferensi senyap tetap
  menyimpan data dan badge; browser permission/VAPID/service worker sebagai
  transport push; multi-rental untuk satu akun User; log aktivitas terintegrasi
  dan scoped per role; halaman ulasan; laporan Admin terkelompok. Kode baru ada
  di `core/activity_log.php`, `core/web_push.php`,
  `migration_activity_log_and_web_push.sql`, `notifications/api.php`,
  `reports/activity_log.php`, `reports/overview.php`, dan `reviews/form.php`.
  Migration telah dijalankan oleh pengguna; push belum aktif sampai
  environment VAPID dan HTTPS/perizinan browser tersedia.
- Riwayat migrasi dari pengguna: `migration_notifications.sql` dan
  `migration_chat.sql` selesai; percobaan pertama `migration_chat_support_admin.sql`
  gagal karena generated column merujuk kolom yang ditambah dalam ALTER TABLE
  yang sama. Versi revisi menghapus generated column dan memakai unique key
  `(conversation_type, requester_user_id)`; pengguna melaporkan versi revisi
  selesai. Percobaan `migration_integrated_property_account_features.sql`
  berhenti pada duplicate `users.hide_phone_public`, yang berarti kolom itu
  sudah ada tetapi tidak membuktikan seluruh migrasi selesai. Pemeriksaan
  kemudian menemukan hanya empat kolom support chat yang hilang dan setelah
  itu pengguna menjalankan migrasi support revisi. Jangan mengulang migrasi
  integrated atau migrasi one-time tanpa pemeriksaan `information_schema`.
- Hapus akun memulai masa tunggu tujuh hari; request aplikasi berikutnya
  memproses identitas yang jatuh tempo, menghapus provider login, dan
  menganonimkan data kontak tenant tanpa menghapus relasi histori transaksi.
  Foto profil akun dianonimkan dengan aman. Jika aplikasi berjalan tanpa
  trafik, gunakan scheduler operasional yang memicu endpoint/maintenance task;
  jangan membuat scheduler kedua tanpa memperhitungkan handler ini.
- Foto galeri kamar baru dapat diarahkan ke unit tertentu pada saat unggah;
  `room_type_gallery_photos.room_id IS NULL` tetap berarti foto bersama per tipe
  untuk kompatibilitas foto lama.
- Pengulas adalah akun role `seeker` yang masuk. Admin/operator tidak dapat
  mengirim ulasan. Data review lama dihubungkan lewat tenant bila tersedia.
- Status penghuni publik hanya menyatakan tersedia/tidak tersedia; jangan
  menambahkan nama, foto, atau detail tenant ke query/markup portal.
- Pencarian **Terdekat** memakai izin browser secara eksplisit dan mengurutkan
  semua hasil berkoordinat dari yang terdekat tanpa batas radius; jika izin
  tidak ada atau ditolak, pencarian nama/alamat tetap bekerja. Jangan menyimpan
  koordinat pengguna ke riwayat pencarian.
- Basic/Premium/Eksklusif menandai tingkat verifikasi admin, bukan kemewahan.
  Operator hanya dapat mengajukan setelah kelengkapan properti dan jumlah foto
  minimum dipenuhi; admin menjadi satu-satunya pihak yang mengesahkan tier.
- Link Android tidak dibuat sampai pemilik memberikan URL resmi.

## Implementasi UI login, chat, pembayaran, dan notifikasi

- Login User/Operator menggunakan dialog popup di halaman publik, bukan
  navigasi ke halaman login terpisah. Submit tetap memakai autentikasi server
  yang ada; popup mencakup close/overlay, lupa password, pendaftaran, dan
  provider Facebook bila tersedia.
- Widget chat mengambang kanan bawah tampil hanya untuk akun seeker, operator,
  dan admin yang sudah login. Layout widget menempatkan daftar percakapan di
  kiri dan thread/pesan di kanan; API JSON berada pada route
  `index.php?chat_api=1`, dengan pemeriksaan role/partisipan dan CSRF pada
  operasi tulis. Pesan ditampilkan sebagai teks, bukan HTML.
- Seeker/operator dapat membuat satu percakapan bantuan Admin per akun.
  Admin membalas percakapan bantuan, meninjau chat User–Operator secara
  read-only, dan dapat mengarsipkan/memulihkan percakapan. Arsip bersifat
  soft-delete; peserta tidak dapat menghapus percakapan atau mengirim pesan
  ke thread yang diarsipkan. Menu navigasi Chat/Notifikasi dihilangkan dari
  User/Operator; Admin tetap memiliki halaman `admin_chat`.
- Ikon notifikasi pada bagian atas menggunakan SVG lonceng dan badge merah;
  notifikasi tetap dibuka dari feed notifikasi atas, tidak melalui item menu
  samping.
- Pencarian akun pada input pembayaran Operator menampilkan daftar pilihan
  dropdown berisi nama, email, dan telepon setelah hasil tersedia.
- `migration_chat_support_admin.sql` menambahkan tipe support, requester,
  metadata arsip, dan unique thread bantuan per requester melalui unique
  `(conversation_type, requester_user_id)`; requester kosong untuk chat
  properti sehingga tidak membatasi jumlah chat properti. Migrasi harus
  dijalankan setelah `migration_chat.sql`. Pengguna melaporkan migrasi
  notifications dan chat selesai, sementara chat support gagal pada
  generated-column dependency. Saat mencoba ulang migrasi integrated-property,
  MySQL mengembalikan duplicate `users.hide_phone_public`; ini memastikan
  kolom tersebut sudah ada, tetapi tidak membuktikan semua bagian migrasi telah
  diterapkan. File migrasi support kini memakai unique key komposit tanpa
  generated column. Jangan mengulang migrasi penuh; periksa daftar kolom/tabel
  fitur wajib di `information_schema`, lalu tangani hanya yang terbukti belum
  tersedia. Sebelum menjalankan migrasi support yang telah diperbaiki, pastikan
  percobaan sebelumnya tidak meninggalkan perubahan parsial.
- File terkait: `chat/chat.php`, `chat/act.php`, `chat/api.php`,
  `chat/widget.php`, `chat/form.php`, `chat/admin_form.php`, `app.js`,
  `style.css`, `index.php`, `core/modules.php`,
  `notifications/notifications.php`, `payments/operator_input.php`,
  `portal/public.php`.
- Status validasi sebelum migrasi: PHP lint file yang diubah lulus; parser
  JavaScript browser menerima `app.js` dan `service-worker.js`; uji lokal ES256
  VAPID serta enkripsi/dekripsi Web Push lulus. Pada 2026-10-19 setelah migrasi,
  HTTP lokal root mengembalikan 200 dan judul halaman publik KosKosan.
  Belum ada uji alur logged-in/UI atau kirim push ke provider nyata.

## Implementasi notifikasi, log, multi-rental, laporan, dan ulasan

- Popup lonceng tampil saat hover/klik, memuat lima notifikasi terbaru, dan
  memiliki tombol **Buka Notifikasi**. Menu profil menyertakan Notifikasi tepat
  sebelum Keluar. Popup pesan di samping lonceng berisi Chat dan Ulasan.
- Silent preference menghentikan push/toast baru tetapi tetap menyimpan
  notifikasi dan menghitung badge. API polling tidak mengirim item untuk toast
  saat muted; saat aktif, toast/pemberitahuan perangkat hanya diproses untuk
  notifikasi unread yang baru. Polling berjalan tiap 20 detik pada halaman
  bertopbar yang memuat menu lonceng. Browser permission dan OS tetap kontrol
  terpisah.
- Web Push memakai service worker serta VAPID dari environment
  `KOSKOSAN_VAPID_PUBLIC_KEY`, `KOSKOSAN_VAPID_PRIVATE_KEY`, dan
  `KOSKOSAN_VAPID_SUBJECT`. Kunci private tidak boleh dicommit. HTTPS
  diperlukan (localhost pengecualian); iPhone biasanya harus memasang situs ke
  Home Screen. Belum ada pengujian push nyata ke browser provider.
- Log aktivitas mencatat akses halaman/form dan error aplikasi yang
  tertangkap, tanpa menyimpan input mentah, password, token, atau IP. Admin
  dapat memfilter role, akun, kategori, dan hasil. Operator hanya melihat
  log/properti milik gedung sendiri dan pengunjung properti; User hanya melihat
  log dengan actor ID akunnya. Error login dicatat sebagai `login_failed`
  tanpa identitas login. Ini bukan jaminan semua error runtime tercatat:
  kegagalan sebelum koneksi database/logging tersedia hanya masuk ke error log
  server, dan error eksternal yang tidak tertangkap perlu ditautkan eksplisit.
- Halaman Aktivitas dan Log menggunakan satu database bersama dengan query
  yang dibatasi role/akun. Log audit berada di route `activity_log`; data
  pencarian/kunjungan berasal dari `property_activity_events` dan tidak ikut
  terhapus saat Admin membersihkan `activity_logs`. Status listing dicatat
  dalam Log Admin saja.
- Laporan Admin saat ini ringkasan: transaksi menghitung row `payments`,
  aktivitas/error menghitung row `activity_logs`, dan jumlah User/kos
  menampilkan metrik agregat. Tautan mengarah ke ringkasan/dashboard atau log;
  belum ada laporan periodik, ekspor, maupun halaman rincian transaksi
  tersendiri.
- Input pembayaran untuk akun terdaftar kini mencari rental aktif berdasarkan
  User **dan kamar**. Sewa aktif lama di kamar lain tidak lagi menghalangi
  pembuatan sewa tambahan. Tabel Penghuni tetap satu record akun/tenant dan
  menghitung semua sewa; filter gedung multi-sewa mempertimbangkan seluruh
  gedung, sedangkan halaman Kamar sudah menampilkan resident per rental.
- Migration baru `migration_activity_log_and_web_push.sql` telah diterapkan
  pengguna setelah backup; hasil `information_schema` mengonfirmasi kedua
  tabel baru ada. Aplikasi merespons HTTP 200 di halaman root setelahnya.
  Entry point tetap menampilkan petunjuk HTTP 503 jika lingkungan database
  lain belum memenuhi semua prasyarat skema. README menjelaskan setup VAPID.
- Batas cakupan audit Operator: peristiwa dengan gedung terkait dibatasi ke
  gedung milik Operator. Peristiwa tanpa gedung hanya masuk lewat daftar page
  domain Operator dan actor ID yang sama. Uji cakupan tindakan yang tidak
  mengirim ID gedung/kamar/entitas setelah migrasi; hindari kebocoran lintas
  pemilik dan duplikasi saat menambahkan audit yang lebih spesifik. Generic
  logger dapat mencatat request terpisah dari log error flash.
- Web Push API membatasi endpoint ke host provider dalam allowlist, memakai
  HTTPS, CSRF, serta pemeriksaan kepemilikan subscription. Uji provider nyata,
  audience/expiry JWT, status response/redirect, serta penghapusan subscription
  setelah logout sebelum mengandalkan pengiriman produksi. Jangan mencatat
  endpoint subscription atau private key.
- Belum diverifikasi: alur logged-in seluruh role, API notifikasi/log,
  push ke provider nyata, dan hasil visual pada perangkat mobile. Root page
  publik telah diverifikasi HTTP 200 pascamigrasi, tetapi itu tidak membuktikan
  seluruh route maupun fitur login berjalan benar. Jangan mengklaim pengujian
  push produksi.

## Standar interaksi tampilan lanjutan

- Drawer sidebar ponsel menampilkan ikon dan label, dapat di-scroll, menutup
  melalui backdrop, dan menyediakan target sentuh nyaman. Jangan kembali ke
  sidebar ikon-only/tooltip sebagai satu-satunya navigasi ponsel.
- Tabel manajemen umum menyediakan dropdown pilihan Tabel/Kartu dengan ikon dan
  label pada trigger maupun daftar pilihan, jumlah 5/10/Semua,
  pencarian/filter, dan ringkasan `1–10 dari N`; pilihan tampilan disimpan per
  tabel. Dropdown pilihan diletakkan setelah jumlah data di ujung kanan kontrol.
  Pemilih jumlah data disembunyikan bila hasil filter berjumlah lima atau
  kurang; gedung dan kamar
  mengikuti aturan yang sama. Kartu memakai data-label dari header sehingga
  tetap punya label aksesibel; aksi/form existing harus tetap di node tabel yang
  sama.
- Di halaman Kamar, kelompok tombol Sebelumnya/Berikutnya harus tetap sejajar
  dan ditempatkan berdampingan dengan dropdown Tabel/Kartu di toolbar; pada
  layar kecil, susun responsif tanpa memisahkan tombol navigasi dari pemilih
  tampilan.
- Dropdown tema Light/Dark menampilkan ikon dan label pada nilai terpilih
  maupun setiap pilihan; dropdown Tabel/Kartu mengikuti pola yang sama.
- Form galerinya satu aksi **Simpan**. Target kamar dikirim bersama upload
  memakai form id yang sama; jangan mengembalikan tombol “Terapkan ke kamar”.
- Bukti pembayaran ditampilkan melalui `<dialog>` yang bisa ditutup; endpoint
  `payments/proof.php` tetap memeriksa kepemilikan dan tidak boleh dilewati.
- Tombol Simpan, Bagikan, dan Laporkan hanya memberi aksi stateful kepada
  pengguna login yang berhak. Tautan WhatsApp publik wajib mematuhi pengaturan
  sembunyikan nomor; chat sistem tetap tersedia untuk User dan operator.
- Semua halaman tetap memakai dropdown bertema dan kontrol tombol/interaksi
  yang sudah distandardisasi; jangan menambah pola kontrol baru tanpa
  menyelaraskannya pada tema gelap, keyboard, fokus, sentuh, dan layar ponsel.
- Tampilan tema berada di ujung kanan sebelum menu profil/panel. Preferensi
  senyap hanya menghentikan pemberitahuan baru melalui toast/push; notifikasi
  persisten, feed, dan badge unread tetap tampil dan terhitung.
- Switch **Sembunyikan notifikasi** bernilai aktif berarti pemberitahuan
  eksternal/toast disenyapkan; simpan preferensinya sebagai
  `in_app_enabled = 0`. Notifikasi tetap masuk ke database dan halaman feed,
  serta unread badge tetap dihitung. Nilai database adalah kebalikan switch.

Saat dokumentasi dan kode tampak berbeda, catat perbedaan tersebut dan
verifikasi dampaknya sebelum mengubah perilaku. Perbarui panduan ini bila
arsitektur, alur inti, cara validasi, atau batas keselamatan proyek berubah.

  ## Username dan role Owner (implementasi 2026-10-19)

  - Kode saat ini memakai nilai role internal `owner`; label antarmuka untuk
    role tersebut adalah **Owner**. Route/file/action lama yang memuat
    `operator` masih dipertahankan sebagai identifier kompatibilitas, bukan
    sebagai nilai role. Migration lama yang menyebut enum `operator` adalah
    riwayat dan tidak boleh diedit atau dijalankan ulang.
  - Database belum dimigrasikan oleh implementasi kode ini. Sebelum mengaktifkan
    aplikasi yang diperbarui, buat backup yang dapat dipulihkan lalu jalankan
    `migration_usernames_and_owner_role.sql` satu kali setelah tabel
    `activity_logs` dan `user_activity_events` tersedia. Migration tersebut
    backfill username huruf-only dari ID akun lama, menambahkan unique index
    ASCII case-insensitive, lalu mengubah role akun dan log dari `operator` ke
    `owner`. Jangan menjalankan migration atas nama pengguna; periksa dulu
    skema target, backup, dan urutannya. Entry point mengembalikan HTTP 503
    sampai kolom, tabel, serta enum role yang diperlukan sudah siap.
  - Username hanya menerima huruf ASCII A-Z, 1–32 karakter, tersimpan lowercase
    dan unik tanpa membedakan kapitalisasi. User dapat mengganti username dari
    Profil; ID akun tetap ID sistem dan tidak dapat diedit. Username berlaku
    untuk login semua role, sedangkan email dan nomor telepon tetap dapat dipakai
    sebagai opsi login. Form setup/registrasi dan profil mengecek ketersediaan
    username melalui endpoint `username_check`; validasi server dan unique index
    tetap menjadi sumber kebenaran. Akun baru yang dibuat otomatis oleh Facebook,
    Owner dari pembayaran/penghuni, dan seeder memperoleh username unik berbasis
    nama; form pembuatan penghuni/pembayaran juga dapat menerima username pilihan.
  - Hasil pencarian akun pembayaran hanya memuat ID internal (untuk submit),
    nama lengkap, dan username; jangan menambahkan kembali telepon/email ke
    hasil popup. Formulir backend tetap mengambil ulang detail akun yang
    dibutuhkan dari database setelah akun dipilih.
  - Form Profil menaruh pratinjau avatar/foto di bagian atas dengan kontrol
    upload kustom, satu foto maksimal, penggantian, dan pilihan avatar default.
    Avatar gender tetap fallback jika tidak ada foto. ID hanya tampil sebagai
    baca-saja. Form registrasi bukan halaman Profil dan boleh memiliki alur
    upload tersendiri.
  - Popup notifikasi/pesan membuka saat hover, menahan diri terbuka ketika pointer
    masuk ke popup melalui bridge CSS dan delay penutupan, serta tetap mendukung
    click, keyboard, Escape, dan sentuh. Menu Laporan terkait diberi label
    Dashboard. Dropdown Tabel/Kartu, daftar Gedung, dan Kamar dimulai pada lima
    baris; opsi 10/Semua tetap tersedia.
  - Perbarui cache-buster frontend saat mengubah `app.js` atau `style.css`.
    Validasi setiap PHP yang tersentuh dengan `php -l`, JavaScript dengan
    pemeriksaan sintaks Node bila tersedia, `git diff --check`, tes terpilih,
    dan smoke test route. Jangan menjalankan migration atau mengubah database
    target dari sesi implementasi.

## Dashboard, Pusat Bantuan, dan pencarian

- Menu pesan Owner/User menautkan **Pusat Bantuan** ke
  `index.php?page=chat&support=1`. `chat/form.php` memastikan percakapan bantuan
  akun tersebut terbuka, lalu memakai alur kirim pesan dan notifikasi Admin
  yang sama seperti chat widget. Jangan membuat tabel/jenis percakapan baru.
- Form pencarian publik hanya memiliki satu kolom pencarian; popup terbuka
  ketika kolom dipilih dan memuat filter jenis kos, destinasi populer, serta
  aksi Dekat saya. Gunakan parameter `gender_policy`, `latitude`, dan
  `longitude`; lokasi browser hanya diminta setelah pengguna memilih Dekat saya.
- Beranda User tidak lagi menyisipkan ringkasan akun atau pencarian terakhir.
  Dashboard User adalah route `user_reports`, diakses dari sidebar User, dan
  seluruh query dibatasi ke ID akun aktif. Ringkasan berada di Dashboard;
  tabel tagihan dipindahkan ke `my_payments` bersama riwayat transaksi.
- `payments/my_history.php` menyajikan semua pembayaran dalam satu tabel dengan
  nama kos/kamar dan filter gedung. Gunakan atribut `data-building-filter`
  karena `app.js` menyediakan pencarian, paginasi 5 baris awal, dan pilihan
  tabel/kartu untuk tabel tersebut.
- Dashboard Owner tidak menyertakan tabel laporan performa atau baris log.
  Dashboard menampilkan kartu statistik dan grafik batang horizontal dengan
  angka transaksi/pemasukan tetap terlihat. Kartu chart bagian luar transparan,
  sedangkan kotak data internal tetap terlihat. Detail statistik berada pada
  `activity`; log audit berada pada `activity_log`. Nilai pending dan tunggakan
  bukan bagian dari total pemasukan terkonfirmasi.
- Status folder chat bersifat personal per akun: Kotak Masuk, Arsip, dan
  Sampah. Arsip/Hapus hanya memindahkan salinan pengguna tanpa konfirmasi;
  hapus permanen tersedia lewat pengosongan Sampah dengan konfirmasi. Setelah
  30 hari di Sampah, status menjadi `purged`. Event harian dan fallback pada
  request Chat menghapus percakapan fisik hanya setelah seluruh peserta dan
  seluruh status personal yang tercatat telah dihapus permanen. Terapkan
  `migration_chat_personal_folders.sql` setelah `migration_chat_inbox_visibility.sql`
  dan aktifkan `event_scheduler`; jangan menjalankan migrasi otomatis.
  Tekan Enter untuk mengirim pesan, Shift+Enter untuk baris baru. Draft widget
  disimpan per ID percakapan.
- Rekening bank pelanggan dibaca dari `bank_transfer_settings` agar data lama
  dan petunjuk transfer per gedung tetap kompatibel. Katalog
  `owner_bank_accounts` menyimpan detail rekening yang dapat digunakan bersama
  oleh beberapa gedung milik Owner; perubahan detail disinkronkan ke semua
  pengaturan yang mereferensikan rekening, sedangkan petunjuk transfer tetap
  per gedung. `migration_shared_owner_bank_accounts.sql` harus diterapkan
  setelah backup; jangan jalankan migrasi otomatis.
- Sidebar memakai warna hangat/olive dari palet halaman publik. Header panel
  desktop dipendekkan dan header mobile memakai baris terpisah agar judul tidak
  bertumpuk dengan tombol. Label Kelola akun berada di atas tiga aksi saat
  tabel akun menggunakan tampilan kartu.
- Role Admin hanya memiliki satu link Dashboard; Verifikasi adalah menu
  tersendiri. Tabel manajemen tidak menampilkan filter wilayah. Pencarian
  tabel generik berjalan pada event `input`.
- Modal autentikasi hanya ditutup oleh klik backdrop yang dimulai dan berakhir
  di backdrop. Interaksi pointer yang dimulai pada input/dialog tidak boleh
  menutup modal, agar pemilihan atau pengeditan password tetap aman.
- Setelah mengubah `app.js`/`style.css`, perbarui cache-buster `20261019-19`
  pada halaman publik/panel. Validasi semua file PHP yang berubah dengan
  `php -l`, pemeriksaan JavaScript bila runtime tersedia, dan uji route bila
  database lokal lolos prasyarat skema. Jangan jalankan migrasi database.

## Handoff sesi 2026-10-19 — UI terpadu dan folder Chat

- Implementasi permintaan UI gabungan ada di worktree, belum di-commit.
  Perubahan mencakup pilihan Tabel/Kartu Gedung, kartu akun/penghuni,
  penggabungan booking dan konfirmasi pembayaran, penghapusan Log sesuai filter,
  pemadatan toolbar/tabel/tombol serta layout, dan palet/sidebar responsif.
  File implementasi utama: `properties/buildings.php`,
  `properties/tenants.php`, `admin/accounts.php`, `bookings/operator_list.php`,
  `reports/activity_log.php`, `core/activity_log.php`, `admin/act.php`,
  `app.js`, `style.css`, `index.php`, `payments/act.php`, dan
  `payments/form.php`. Periksa `git status` sebelum mengubah atau men-stage
  apa pun: worktree berisi perubahan lain dan file upload lokal yang tidak
  boleh dihapus/revert atau ikut commit tanpa permintaan.
- Chat kini memiliki folder personal Kotak Masuk/Arsip/Sampah untuk semua role.
  Arsip dan Hapus memindahkan salinan akun tanpa konfirmasi; pengosongan Sampah
  minta konfirmasi. Sampah kedaluwarsa 30 hari. Filter Chat Admin langsung
  submit saat mengetik/mengubah pilihan. Styles Chat, cache-buster frontend,
  README, dan catatan handoff ikut diperbarui.
- **Status migrasi sudah terverifikasi aktif pada 2026-10-19.** Tabel
  `chat_conversation_user_state` ada; pengguna menjalankan SQL pembuatan event
  melalui MariaDB admin. Output `SHOW EVENTS` yang diberikan pengguna
  menunjukkan `chat_purge_expired_trash_event` berstatus `ENABLED`, mulai
  2026-10-20 22:55:56 dan berulang harian. Pemeriksaan koneksi aplikasi
  menunjukkan `@@event_scheduler=ON`; root lokal merespons HTTP 200.
  Jangan jalankan ulang `migration_chat_personal_folders.sql` atau membuat
  event duplikat. Catatan sebelumnya bahwa event belum ada sudah digantikan
  status ini. Scheduler global ON belum dibuktikan bertahan setelah MariaDB
  restart; jika dibutuhkan, pengguna perlu mengaktifkannya permanen pada
  konfigurasi MariaDB.
- Pembersihan fisik hanya dilakukan bila semua state pengguna yang tercatat
  sudah `purged`; percakapan tidak ikut dihapus selagi ada salinan user lain
  yang belum dibersihkan. Event berlaku untuk entri Trash yang mencapai 30
  hari; pengosongan manual tetap tersedia. Jangan melakukan uji destruktif
  terhadap data percakapan database.
- Validasi selesai: `php -l` untuk semua file PHP berubah dan
  `git -c core.whitespace=cr-at-eol diff --check` lulus. Browser menerima
  sintaks `app.js`; uji filter Chat Admin mengonfirmasi satu submit untuk
  perubahan input dan satu untuk pilihan select; stylesheet Chat berhasil
  dimuat. Homepage menghasilkan HTTP 200 setelah perubahan skema.
  Pemeriksaan halaman terautentikasi/visual per role dan alur chat terhadap
  database belum dilakukan. Node.js tidak tersedia; validasi sintaks JS
  memakai browser.
- README kini menjelaskan migrasi folder Chat, expiry/event scheduler,
  penggabungan daftar booking/pembayaran dan perubahan UI. Pertahankan cache
  asset `koskosan-ui-20261019-19` kecuali dinaikkan saat mengubah CSS/JS.
- Besok, lanjutkan dengan meninjau perubahan dari `git status` dan menguji
  route yang sudah login untuk Admin, Owner, dan User serta folder Chat
  (Arsip, Sampah, restore, empty trash) tanpa menghapus data sungguhan.
  Jangan jalankan migration ulang. Jika UI berjalan, verifikasi tampilan ponsel
  dan alur booking/pembayaran/log; pertahankan semua file upload dan perubahan
  lokal yang tidak berkaitan.

## Handoff sesi 2026-10-23 — UI, pembayaran, Keuangan, dan WhatsApp

- Permintaan UI dan fitur pengguna telah diimplementasikan: kartu kamar lebih
  ringkas dengan carousel dan satu kolom kelompok tipe; selector Kartu/Tabel
  diratakan ke kanan; kartu penghuni menempatkan foto di kiri dan ID, username,
  serta email di kanan; kartu booking/penghuni dipersempit; filter Log, Chat
  Admin, dan Keuangan diterapkan otomatis. Filter galeri dan pemilih gedung
  yang sudah langsung submit dipertahankan.
- Riwayat pembayaran menyediakan struk transaksi terkonfirmasi, cetak/simpan
  PDF melalui dialog browser, serta tautan email/WhatsApp. Email membuka
  aplikasi email dan meminta pengguna melampirkan PDF; WhatsApp membuka chat
  berisi ringkasan transaksi, bukan mengirim lampiran otomatis.
- Menu Keuangan Owner menampilkan pembayaran terkonfirmasi, sisa tagihan,
  pengeluaran rutin/lainnya, dan filter periode. Sebelum dipakai, backup lalu
  jalankan `migration_owner_finances.sql` satu kali. Pada 2026-10-23 pengguna
  mengonfirmasi tabel `owner_expense_categories` dan `owner_expenses` sudah
  tersedia setelah migrasi dijalankan dengan hak admin MariaDB.
- Tautan WhatsApp memakai `wa.me` untuk membuka aplikasi atau WhatsApp Web.
  Penautan sesi/QR login, WhatsApp Web tidak resmi, Cloud API, dan pengiriman
  pesan otomatis sengaja tidak dibuat.
- Chat menampilkan avatar bulat dan popup profil ringkas; halaman profil chat
  hanya memaparkan nama, ID, username, dan email, tanpa nomor telepon/alamat.
  Aksi Arsip/Hapus berada di sisi kanan daftar chat. Tema biru dan tipografi
  diperbarui. README menjelaskan perilaku WhatsApp dan struk.
- Validasi pada sesi ini: `php -l` lulus untuk seluruh berkas PHP yang
  disentuh; helper WhatsApp diuji untuk normalisasi nomor dan input invalid;
  `git -c core.whitespace=cr-at-eol diff --check` lulus. Node.js tidak
  tersedia dan uji visual/browser serta end-to-end database belum dilakukan.
- Perubahan implementasi belum diverifikasi dengan migrasi/database lokal.
  Commit `97669c4` dibuat di branch `main`, berisi perubahan kode, migrasi, dan
  dokumentasi; berkas upload serta berkas lokal bernama tidak biasa sengaja
  tidak dimasukkan. Push ke `origin/main` gagal karena HTTPS Git tidak
  memperoleh kredensial (`unable to get password from user`). Percobaan SSH
  juga tidak dilanjutkan karena verifikasi host GitHub belum tersedia.
  Setelah autentikasi Git diatur dengan aman, push commit lokal ke `origin/main`
  lalu pastikan branch remote memuat commit tersebut.
- Tindak lanjut setelah commit: kartu penghuni Owner kini memakai lebar penuh
  untuk baris aksi agar tombol merata dan label Reset password/Pindahkan/
  Keluarkan tidak terpecah; panel daftar akun Admin memakai pola serupa. Teks
  chat Admin User–Owner menggantikan User–operator. Teks panduan dan demo
  diperbarui ke Owner untuk sebutan peran; identifier route, kolom, dan nilai
  migrasi lama yang bernama `operator` sengaja dipertahankan agar kompatibel.
  Cache asset untuk panel, publik, dan autentikasi dinaikkan ke
  `koskosan-ui-20261023-2`. Perubahan tindak lanjut ini akan dicatat dalam
  commit terpisah; akses GitHub saat ini masih gagal autentikasi saat push.
  Pengguna mengirim pesan bernomor “3” tanpa uraian; minta rincian sebelum
  menambahkan perubahan lain.
