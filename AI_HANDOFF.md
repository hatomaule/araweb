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
- Setup dan gambaran fitur: `README.md`

## Integrasi fitur dan sumber kebenaran

- Sistem tetap memakai satu database `manajemen_kos`. `properties` adalah
  sumber kebenaran gedung/kamar/foto; `accounts` mengelola preferensi dan
  siklus akun; `portal` mengelola ulasan serta aksi simpan/lapor; folder domain
  `chat/` dan `notifications/` hanya memiliki tanggung jawab percakapan dan
  pemberitahuan, tanpa menyimpan salinan data gedung/kamar/akun.
- Migrasi fitur lanjutan terpisah dari implementasi kode:
  `migration_integrated_property_account_features.sql`,
  `migration_notifications.sql`, lalu `migration_chat.sql`. Jalankan sekali
  setelah backup yang dapat dipulihkan dan hanya dengan akun database berhak
  DDL. Saat pekerjaan ini diverifikasi, akun aplikasi lokal tidak memiliki hak
  `ALTER`; migrasi tersebut **belum diterapkan**. Jangan menganggap fitur
  barunya siap digunakan terhadap database yang belum dimigrasi. Entry point
  memeriksa kolom/tabel fitur sebelum menjalankan route; bila migrasi belum
  lengkap, tampilkan status 503 beserta panduan migrasi agar kegagalan skema
  tidak tersembunyi sebagai error query umum.
- Ketergantungan skema utama: privasi/siklus akun berada di kolom `users`;
  permintaan klasifikasi berada di `property_verification_requests`, sedangkan
  klasifikasi yang ditampilkan berada di `kos_buildings`;
  `property_user_actions` menyimpan simpan/laporan; `property_reviews`
  menerima `reviewer_user_id`; foto galeri kamar opsional memiliki `room_id`;
  percakapan tersimpan di `chat_conversations`/`chat_messages`; notifikasi
  tersimpan di `notifications` dengan preferensi tunggal per User di
  `notification_preferences`.
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
- Tampilan tema berada di ujung kanan sebelum menu profil/panel. Ikon notifikasi
  membuka feed dan preferensi notifikasi harus mematikan baik jumlah badge
  maupun item feed untuk akun tersebut.
- Switch **Sembunyikan notifikasi** bernilai aktif berarti notifikasi in-app
  dimatikan; simpan preferensinya sebagai `in_app_enabled = 0` (nilai database
  adalah kebalikan langsung dari switch tersebut).

Saat dokumentasi dan kode tampak berbeda, catat perbedaan tersebut dan
verifikasi dampaknya sebelum mengubah perilaku. Perbarui panduan ini bila
arsitektur, alur inti, cara validasi, atau batas keselamatan proyek berubah.
