<?php
declare(strict_types=1);

function google_auth_destination(string $role): string
{
    return match ($role) {
        'admin' => 'platform_dashboard',
        'owner' => 'dashboard',
        default => 'home',
    };
}

function handle_google_oauth_route(PDO $pdo, ?array $user, string $page): void
{
    if ($page === 'google_register' && $user) {
        redirect(google_auth_destination($user['role']));
    }
    if ($page === 'google_login' || $page === 'google_link') {
        $isLink = $page === 'google_link';
        if ($isLink && !$user) {
            set_flash('error', 'Masuk terlebih dahulu sebelum menautkan Google.');
            redirect('login');
        }
        if (!$isLink && $user) {
            redirect(google_auth_destination($user['role']));
        }
        $oauth = google_oauth_config();
        if (!$oauth) {
            set_flash('error', 'Login Google belum dikonfigurasi oleh administrator.');
            redirect($isLink ? 'account_settings' : 'login');
        }

        if (!$isLink) {
            unset($_SESSION['google_new_account']);
        }
        $state = bin2hex(random_bytes(32));
        $verifier = oauth_base64url(random_bytes(64));
        $_SESSION['google_oauth'] = [
            'state' => $state,
            'verifier' => $verifier,
            'intent' => $isLink ? 'link' : 'login',
            'user_id' => $isLink ? (int) $user['id'] : null,
            'created_at' => time(),
        ];
        $authorizeUrl = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
            'client_id' => $oauth['client_id'],
            'redirect_uri' => $oauth['redirect_uri'],
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,
            'code_challenge' => oauth_base64url(hash('sha256', $verifier, true)),
            'code_challenge_method' => 'S256',
            'prompt' => 'select_account',
        ], '', '&', PHP_QUERY_RFC3986);
        header('Location: ' . $authorizeUrl);
        exit;
    }

    if ($page !== 'google_callback') {
        return;
    }

    $flow = $_SESSION['google_oauth'] ?? null;
    unset($_SESSION['google_oauth']);
    $returnPage = is_array($flow) && ($flow['intent'] ?? '') === 'link'
        ? 'account_settings' : 'login';
    $receivedState = isset($_GET['state']) && is_string($_GET['state']) ? $_GET['state'] : '';
    if (!is_array($flow) || !isset($flow['state'], $flow['verifier'], $flow['created_at'])
        || !is_string($flow['state']) || !is_string($flow['verifier'])
        || time() - (int) $flow['created_at'] > 600 || $receivedState === ''
        || !hash_equals($flow['state'], $receivedState)) {
        set_flash('error', 'Sesi verifikasi Google tidak valid atau sudah kedaluwarsa. Silakan coba lagi.');
        redirect($returnPage);
    }
    if (!empty($_GET['error']) || !isset($_GET['code']) || !is_string($_GET['code'])
        || $_GET['code'] === '') {
        set_flash('error', 'Login Google dibatalkan. Silakan coba lagi atau masuk dengan metode lain.');
        redirect($returnPage);
    }

    $oauth = google_oauth_config();
    if (!$oauth) {
        set_flash('error', 'Login Google belum dikonfigurasi oleh administrator.');
        redirect($returnPage);
    }

    try {
        $token = google_oauth_request('https://oauth2.googleapis.com/token', 'POST', [
            'code' => $_GET['code'],
            'client_id' => $oauth['client_id'],
            'client_secret' => $oauth['client_secret'],
            'redirect_uri' => $oauth['redirect_uri'],
            'grant_type' => 'authorization_code',
            'code_verifier' => $flow['verifier'],
        ]);
        if (!isset($token['access_token']) || !is_string($token['access_token'])
            || $token['access_token'] === '') {
            throw new RuntimeException('Google tidak memberikan token login yang valid.');
        }
        $profile = google_oauth_request(
            'https://openidconnect.googleapis.com/v1/userinfo',
            'GET',
            [],
            $token['access_token']
        );
        $googleId = isset($profile['sub']) && is_string($profile['sub']) ? trim($profile['sub']) : '';
        $googleEmail = isset($profile['email']) && is_string($profile['email'])
            ? strtolower(trim($profile['email'])) : '';
        $googleName = isset($profile['name']) && is_string($profile['name'])
            ? trim($profile['name']) : '';
        $emailVerified = ($profile['email_verified'] ?? false) === true
            || ($profile['email_verified'] ?? '') === 'true';
        if ($googleId === '' || strlen($googleId) > 191 || !$emailVerified
            || !filter_var($googleEmail, FILTER_VALIDATE_EMAIL) || strlen($googleEmail) > 255
            || $googleName === '') {
            throw new RuntimeException('Google tidak memberikan profil dengan email terverifikasi yang lengkap.');
        }
        $nameCharacters = preg_split('//u', $googleName, -1, PREG_SPLIT_NO_EMPTY);
        if (!is_array($nameCharacters)) {
            throw new RuntimeException('Nama pada profil Google tidak valid.');
        }
        $googleName = implode('', array_slice($nameCharacters, 0, 100));

        $linkedAccount = one(
            $pdo,
            'SELECT u.id,u.role,u.status FROM user_auth_providers ap
             JOIN users u ON u.id=ap.user_id
             WHERE ap.provider=\'google\' AND ap.provider_user_id=:subject LIMIT 1',
            ['subject' => $googleId]
        );
        if (($flow['intent'] ?? '') === 'link') {
            $currentUser = current_user();
            if (!$currentUser || (int) $currentUser['id'] !== (int) ($flow['user_id'] ?? 0)) {
                throw new RuntimeException('Sesi akun berubah. Masuk kembali sebelum menautkan Google.');
            }
            if ($linkedAccount) {
                if ((int) $linkedAccount['id'] !== (int) $currentUser['id']) {
                    throw new RuntimeException('Akun Google ini sudah tertaut ke akun KosKosan lain.');
                }
                set_flash('success', 'Akun Google ini sudah tertaut.');
                redirect('account_settings');
            }
            if (one($pdo, 'SELECT id FROM user_auth_providers WHERE user_id=:id AND provider=\'google\'', [
                'id' => $currentUser['id'],
            ])) {
                throw new RuntimeException('Akun KosKosan ini sudah memiliki tautan Google.');
            }
            $pdo->prepare(
                'INSERT INTO user_auth_providers (user_id,provider,provider_user_id)
                 VALUES (:user_id,\'google\',:subject)'
            )->execute(['user_id' => $currentUser['id'], 'subject' => $googleId]);
            set_flash('success', 'Akun Google berhasil ditautkan. Role dan email akun KosKosan tidak diubah.');
            redirect('account_settings');
        }

        if ($linkedAccount) {
            if ($linkedAccount['status'] !== 'active') {
                throw new RuntimeException('Akun KosKosan ini tidak aktif.');
            }
            unset($_SESSION['google_new_account']);
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int) $linkedAccount['id'];
            redirect(google_auth_destination($linkedAccount['role']));
        }
        if (one($pdo, 'SELECT id FROM users WHERE email=:email LIMIT 1', ['email' => $googleEmail])) {
            unset($_SESSION['google_new_account']);
            set_flash('error', 'Email Google sudah terdaftar. Masuk dengan metode akun lama, lalu tautkan Google dari Pengaturan akun.');
            redirect('login');
        }
        $_SESSION['google_new_account'] = [
            'subject' => $googleId,
            'email' => $googleEmail,
            'name' => $googleName,
            'created_at' => time(),
        ];
        redirect('google_register');
    } catch (PDOException $exception) {
        error_log('Google account operation failed: ' . $exception->getMessage());
        set_flash('error', 'Akun Google tidak dapat ditautkan. Periksa apakah email atau akun Google sudah digunakan.');
        redirect($returnPage);
    } catch (RuntimeException $exception) {
        error_log('Google authentication failed: ' . $exception->getMessage());
        set_flash('error', $exception->getMessage());
        redirect($returnPage);
    }
}

function handle_auth_action(PDO $pdo, int $userCount, string $page, string $action): void
{
        if ($action === 'google_create_account') {
            if (current_user()) {
                redirect('home');
            }
            $googleAccount = $_SESSION['google_new_account'] ?? null;
            if (!is_array($googleAccount) || !isset(
                $googleAccount['subject'],
                $googleAccount['email'],
                $googleAccount['name'],
                $googleAccount['created_at']
            ) || time() - (int) $googleAccount['created_at'] > 900
                || !filter_var($googleAccount['email'], FILTER_VALIDATE_EMAIL)) {
                unset($_SESSION['google_new_account']);
                set_flash('error', 'Sesi pendaftaran Google kedaluwarsa. Silakan mulai lagi.');
                redirect('login');
            }
            $role = post_value('role');
            if (!in_array($role, ['owner', 'seeker'], true)) {
                set_flash('error', 'Pilih peran Owner atau User. Pendaftaran Admin tidak tersedia.');
                redirect('google_register');
            }
            if (one($pdo, 'SELECT id FROM users WHERE email=:email LIMIT 1', [
                'email' => $googleAccount['email'],
            ])) {
                unset($_SESSION['google_new_account']);
                set_flash('error', 'Email Google sudah terdaftar. Masuk dengan metode akun lama, lalu tautkan Google dari Pengaturan akun.');
                redirect('login');
            }

            $pdo->beginTransaction();
            try {
                $account = $pdo->prepare(
                    'INSERT INTO users (name,username,email,password_hash,role)
                     VALUES (:name,:username,:email,:password_hash,:role)'
                );
                $account->execute([
                    'name' => $googleAccount['name'],
                    'username' => generate_unique_username($pdo, $googleAccount['name']),
                    'email' => $googleAccount['email'],
                    'password_hash' => password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT),
                    'role' => $role,
                ]);
                $userId = (int) $pdo->lastInsertId();
                if ($role === 'owner') {
                    seed_default_owner_expense_categories($pdo, $userId);
                } else {
                    $pdo->prepare(
                        'INSERT INTO tenants (user_id,full_name,phone,email)
                         VALUES (:user_id,:name,\'\',:email)'
                    )->execute([
                        'user_id' => $userId,
                        'name' => $googleAccount['name'],
                        'email' => $googleAccount['email'],
                    ]);
                }
                $pdo->prepare(
                    'INSERT INTO user_auth_providers (user_id,provider,provider_user_id)
                     VALUES (:user_id,\'google\',:subject)'
                )->execute(['user_id' => $userId, 'subject' => $googleAccount['subject']]);
                $pdo->commit();
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $exception;
            }
            unset($_SESSION['google_new_account']);
            session_regenerate_id(true);
            $_SESSION['user_id'] = $userId;
            set_flash('success', $role === 'owner'
                ? 'Akun Owner berhasil dibuat. Lengkapi profil dan data gedung secara bertahap.'
                : 'Akun User berhasil dibuat.');
            redirect(google_auth_destination($role));
        }

if ($action === 'request_password_reset') {
            $email = strtolower(post_value('email'));
            if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255) {
                set_flash('error', 'Masukkan alamat email yang valid.');
                redirect('forgot_password');
            }
            $mailFrom = getenv('KOSKOSAN_MAIL_FROM') ?: (getenv('ARAWEB_MAIL_FROM') ?: '');
            if (!filter_var($mailFrom, FILTER_VALIDATE_EMAIL) || !function_exists('mail')) {
                set_flash('error', 'Layanan email belum dikonfigurasi. Hubungi administrator KosKosan.');
                redirect('forgot_password');
            }

            $account = one(
                $pdo,
                "SELECT id,email,name FROM users
                 WHERE email=:email AND role='seeker' AND status='active' LIMIT 1",
                ['email' => $email]
            );
            if ($account) {
                $lastRequest = one(
                    $pdo,
                    'SELECT requested_at > DATE_SUB(NOW(), INTERVAL 60 SECOND) AS recently_requested
                     FROM account_password_resets WHERE user_id=:user_id AND used_at IS NULL',
                    ['user_id' => $account['id']]
                );
                if ($lastRequest && (int) $lastRequest['recently_requested'] === 1) {
                    set_flash('success', 'Jika akun pengguna aktif dengan email tersebut tersedia, kode reset telah dikirim. Periksa email dan folder spam.');
                    redirect('reset_password');
                }

                $code = (string) random_int(100000, 999999);
                $codeHash = password_hash($code, PASSWORD_DEFAULT);
                $reset = $pdo->prepare(
                    'INSERT INTO account_password_resets
                     (user_id,code_hash,requested_at,expires_at,attempts,used_at)
                     VALUES (:user_id,:code_hash,NOW(),DATE_ADD(NOW(), INTERVAL 15 MINUTE),0,NULL)
                     ON DUPLICATE KEY UPDATE code_hash=VALUES(code_hash),
                       requested_at=VALUES(requested_at),expires_at=VALUES(expires_at),
                       attempts=0,used_at=NULL'
                );
                $reset->execute(['user_id' => $account['id'], 'code_hash' => $codeHash]);
                $subject = 'Kode reset password KosKosan';
                $message = "Halo {$account['name']},\n\n"
                    . "Gunakan kode berikut untuk membuat password baru: {$code}\n"
                    . "Kode berlaku selama 15 menit dan hanya dapat digunakan sekali.\n"
                    . "Jika Anda tidak meminta reset password, abaikan email ini.\n\nKosKosan";
                $headers = "From: KosKosan <{$mailFrom}>\r\n"
                    . "Content-Type: text/plain; charset=UTF-8\r\n"
                    . "X-Mailer: PHP/" . PHP_VERSION;
                if (!mail($account['email'], $subject, $message, $headers)) {
                    $pdo->prepare(
                        'DELETE FROM account_password_resets WHERE user_id=:user_id AND code_hash=:code_hash'
                    )->execute(['user_id' => $account['id'], 'code_hash' => $codeHash]);
                    error_log('KosKosan password reset email could not be queued for user ID ' . (int) $account['id']);
                    set_flash('error', 'Email kode reset gagal dikirim oleh server. Coba lagi nanti atau hubungi administrator.');
                    redirect('forgot_password');
                }
            }
            set_flash('success', 'Jika akun pengguna aktif dengan email tersebut tersedia, kode reset telah dikirim. Periksa email dan folder spam.');
            redirect('reset_password');
        }

        if ($action === 'complete_password_reset') {
            $email = strtolower(post_value('email'));
            $code = post_value('code');
            $password = post_value('password');
            if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255
                || !preg_match('/^\d{6}$/', $code) || strlen($password) < 8 || strlen($password) > 72) {
                set_flash('error', 'Periksa email, kode enam digit, dan password baru minimal 8 karakter.');
                redirect('reset_password');
            }
            $pdo->beginTransaction();
            try {
                $statement = $pdo->prepare(
                    "SELECT pr.id,pr.user_id,pr.code_hash,pr.attempts
                     FROM account_password_resets pr JOIN users u ON u.id=pr.user_id
                     WHERE u.email=:email AND u.role='seeker' AND u.status='active'
                       AND pr.used_at IS NULL AND pr.expires_at>NOW() AND pr.attempts<5
                     LIMIT 1 FOR UPDATE"
                );
                $statement->execute(['email' => $email]);
                $reset = $statement->fetch();
                if (!$reset || !password_verify($code, $reset['code_hash'])) {
                    if ($reset) {
                        $pdo->prepare(
                            'UPDATE account_password_resets SET attempts=attempts+1 WHERE id=:id'
                        )->execute(['id' => $reset['id']]);
                    }
                    $pdo->commit();
                    set_flash('error', 'Kode reset tidak valid, kedaluwarsa, atau sudah mencapai batas percobaan.');
                    redirect('reset_password');
                }
                $pdo->prepare('UPDATE users SET password_hash=:password WHERE id=:id')
                    ->execute(['password' => password_hash($password, PASSWORD_DEFAULT), 'id' => $reset['user_id']]);
                $pdo->prepare('UPDATE account_password_resets SET used_at=NOW() WHERE id=:id')
                    ->execute(['id' => $reset['id']]);
                $pdo->commit();
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $exception;
            }
            set_flash('success', 'Password berhasil diperbarui. Silakan masuk dengan password baru.');
            redirect('login');
        }

if ($action === 'setup') {
            if ($userCount > 0) {
                set_flash('error', 'Akun admin sudah dibuat. Silakan masuk.');
                redirect('login');
            }

            $name = post_value('name');
            $username = normalize_username(post_value('username'));
            $email = strtolower(post_value('email'));
            $password = post_value('password');
            if ($name === '' || strlen($name) > 100 || !username_is_valid($username)
                || !username_is_available($pdo, $username) || !filter_var($email, FILTER_VALIDATE_EMAIL)
                || strlen($email) > 255 || strlen($password) < 8) {
                set_flash('error', 'Isi nama, username unik yang hanya berisi huruf, dan email dengan benar. Kata sandi minimal 8 karakter.');
                redirect('setup');
            }

            $statement = $pdo->prepare(
                "INSERT INTO users (name, username, email, password_hash, role) VALUES (:name, :username, :email, :password_hash, 'admin')"
            );
            $statement->execute([
                'name' => $name,
                'username' => $username,
                'email' => $email,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            ]);
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int) $pdo->lastInsertId();
            set_flash('success', 'Akun admin berhasil dibuat.');
            redirect('dashboard');
        }

        if ($action === 'register_account') {
            if (current_user()) {
                redirect('home');
            }
            $name = post_value('name');
            $username = normalize_username(post_value('username'));
            $email = strtolower(post_value('email'));
            $phone = post_value('phone');
            $gender = post_value('gender');
            $password = post_value('password');
            $role = post_value('role') ?: 'seeker';
            if (!in_array($role, ['owner', 'seeker'], true)) {
                $role = 'seeker';
            }
            $emailValid = $email === '' || (filter_var($email, FILTER_VALIDATE_EMAIL) && strlen($email) <= 255);
            if ($name === '' || strlen($name) > 100 || !username_is_valid($username)
                || !username_is_available($pdo, $username) || !$emailValid
                || ($email === '' && $phone === '') || strlen($phone) > 30
                || !in_array($gender, ['male', 'female'], true) || strlen($password) < 8) {
                set_flash('error', 'Isi nama, username unik yang hanya berisi huruf, minimal salah satu email atau nomor telepon, jenis kelamin, serta kata sandi minimal 8 karakter.');
                redirect('register');
            }

            $profilePath = store_profile_photo_upload();
            $pdo->beginTransaction();
            try {
                $account = $pdo->prepare(
                    'INSERT INTO users (name, username, email, phone, gender, profile_photo_path, password_hash, role)
                     VALUES (:name, :username, :email, :phone, :gender, :photo, :password_hash, :role)'
                );
                $account->execute([
                    'name' => $name, 'username' => $username, 'email' => $email !== '' ? $email : null,
                    'phone' => $phone !== '' ? $phone : null, 'role' => $role,
                    'gender' => $gender, 'photo' => $profilePath,
                    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                ]);
                $userId = (int) $pdo->lastInsertId();
                if ($role === 'owner') {
                    seed_default_owner_expense_categories($pdo, $userId);
                } else {
                    $tenant = $pdo->prepare(
                        'INSERT INTO tenants (user_id, full_name, phone, email) VALUES (:user_id, :full_name, :phone, :email)'
                    );
                    $tenant->execute([
                        'user_id' => $userId, 'full_name' => $name,
                        'phone' => $phone, 'email' => $email !== '' ? $email : null,
                    ]);
                }
                $pdo->commit();
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                if ($profilePath !== null && is_file(__DIR__ . '/../' . $profilePath)) {
                    unlink(__DIR__ . '/../' . $profilePath);
                }
                throw $exception;
            }
            session_regenerate_id(true);
            $_SESSION['user_id'] = $userId;
            set_flash('success', $role === 'owner'
                ? 'Akun Owner berhasil dibuat. Anda dapat langsung mengelola data kos.'
                : 'Akun User berhasil dibuat.');
            if ($role === 'seeker' && post_value('next') === 'booking'
                && (int) post_value('next_room_id') > 0) {
                redirect('booking_form&id=' . (int) post_value('next_room_id'));
            }
            redirect($role === 'owner' ? 'dashboard' : 'home');
        }

        if ($action === 'login') {
            $login = strtolower(post_value('login_identifier'));
            $statement = $pdo->prepare(
                "SELECT id, password_hash FROM users
                 WHERE (username = :username OR email = :email OR phone = :phone) AND status = 'active' LIMIT 1"
            );
            $statement->execute([
                'username' => $login,
                'email' => $login,
                'phone' => post_value('login_identifier'),
            ]);
            $account = $statement->fetch();
            if (!$account || !password_verify(post_value('password'), $account['password_hash'])) {
                set_flash('error', 'Username, email, nomor telepon, atau kata sandi salah, atau akun tidak aktif.');
                redirect('login');
            }

            $pdo->prepare(
                'UPDATE users SET self_deactivated_at=NULL,deletion_requested_at=NULL,deletion_due_at=NULL
                 WHERE id=:id AND deletion_due_at IS NOT NULL AND deletion_due_at>NOW()'
            )->execute(['id' => $account['id']]);
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int) $account['id'];
            $loggedInUser = current_user();
            redirect(!$loggedInUser ? 'login' : ($loggedInUser['role'] === 'admin' ? 'platform_dashboard'
                : ($loggedInUser['role'] === 'owner' ? 'dashboard' : 'home')));
        }

        if ($action === 'logout') {
            $logoutUserId = filter_var(
                $_SESSION['user_id'] ?? null,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]]
            );
            if ($logoutUserId !== false && $logoutUserId !== null) {
                $pdo->prepare('DELETE FROM web_push_subscriptions WHERE user_id=:user_id')
                    ->execute(['user_id' => $logoutUserId]);
            }
            $_SESSION = [];
            if (ini_get('session.use_cookies')) {
                $cookie = session_get_cookie_params();
                setcookie(session_name(), '', [
                    'expires' => time() - 42000, 'path' => $cookie['path'],
                    'domain' => $cookie['domain'], 'secure' => $cookie['secure'],
                    'httponly' => $cookie['httponly'], 'samesite' => 'Lax',
                ]);
            }
            session_destroy();
            redirect('login');
        }

        if ($page === 'facebook_login') {
            $oauth = facebook_oauth_config();
            if (!$oauth) {
                set_flash('error', 'Login Facebook belum dikonfigurasi. Silakan masuk dengan email atau hubungi pengelola.');
                redirect('login');
            }
            $state = bin2hex(random_bytes(32));
            $_SESSION['facebook_oauth_state'] = $state;
            $authorizeUrl = 'https://www.facebook.com/v23.0/dialog/oauth?' . http_build_query([
                'client_id' => $oauth['app_id'],
                'redirect_uri' => $oauth['redirect_uri'],
                'response_type' => 'code',
                'scope' => 'email,public_profile',
                'state' => $state,
            ], '', '&', PHP_QUERY_RFC3986);
            header('Location: ' . $authorizeUrl);
            exit;
        }

        if ($page === 'facebook_callback') {
            $oauth = facebook_oauth_config();
            $expectedState = $_SESSION['facebook_oauth_state'] ?? '';
            $receivedState = isset($_GET['state']) && is_string($_GET['state']) ? $_GET['state'] : '';
            unset($_SESSION['facebook_oauth_state']);
            if (!$oauth || !is_string($expectedState) || $expectedState === ''
                || $receivedState === '' || !hash_equals($expectedState, $receivedState)
                || !isset($_GET['code']) || !is_string($_GET['code']) || $_GET['code'] === '') {
                set_flash('error', 'Login Facebook dibatalkan atau sesi verifikasi tidak valid. Silakan coba lagi.');
                redirect('login');
            }
            if (!empty($_GET['error'])) {
                set_flash('error', 'Login Facebook dibatalkan. Anda dapat masuk menggunakan email.');
                redirect('login');
            }

            try {
                $tokenUrl = 'https://graph.facebook.com/v23.0/oauth/access_token?' . http_build_query([
                    'client_id' => $oauth['app_id'],
                    'client_secret' => $oauth['app_secret'],
                    'redirect_uri' => $oauth['redirect_uri'],
                    'code' => $_GET['code'],
                ], '', '&', PHP_QUERY_RFC3986);
                $token = facebook_graph_get($tokenUrl);
                if (!isset($token['access_token']) || !is_string($token['access_token'])
                    || $token['access_token'] === '') {
                    throw new RuntimeException('Facebook tidak memberikan token login yang valid.');
                }
                $profileUrl = 'https://graph.facebook.com/v23.0/me?' . http_build_query([
                    'fields' => 'id,name,email',
                    'access_token' => $token['access_token'],
                ], '', '&', PHP_QUERY_RFC3986);
                $facebookProfile = facebook_graph_get($profileUrl);
                $facebookId = isset($facebookProfile['id']) && is_string($facebookProfile['id'])
                    ? $facebookProfile['id'] : '';
                $facebookName = isset($facebookProfile['name']) && is_string($facebookProfile['name'])
                    ? trim($facebookProfile['name']) : '';
                $facebookNameCharacters = $facebookName !== ''
                    ? preg_split('//u', $facebookName, -1, PREG_SPLIT_NO_EMPTY) : [];
                $facebookEmail = isset($facebookProfile['email']) && is_string($facebookProfile['email'])
                    ? strtolower(trim($facebookProfile['email'])) : '';
                if ($facebookId === '' || $facebookName === '' || !is_array($facebookNameCharacters)
                    || !filter_var($facebookEmail, FILTER_VALIDATE_EMAIL)) {
                    throw new RuntimeException('Profil Facebook tidak lengkap. Pastikan akses email diizinkan, atau gunakan login email.');
                }
                $facebookName = implode('', array_slice($facebookNameCharacters, 0, 100));

                $pdo->beginTransaction();
                try {
                    $linkedAccount = one(
                        $pdo,
                        "SELECT u.id,u.role,u.status FROM user_auth_providers ap
                         JOIN users u ON u.id=ap.user_id
                         WHERE ap.provider='facebook' AND ap.provider_user_id=:provider_user_id",
                        ['provider_user_id' => $facebookId]
                    );
                    if ($linkedAccount) {
                        if ($linkedAccount['role'] !== 'seeker' || $linkedAccount['status'] !== 'active') {
                            throw new RuntimeException('Akun Facebook ini tidak dapat digunakan untuk masuk.');
                        }
                        $userId = (int) $linkedAccount['id'];
                    } else {
                        $existingAccount = one($pdo, 'SELECT id,role,status FROM users WHERE email=:email', ['email' => $facebookEmail]);
                        if ($existingAccount) {
                            if ($existingAccount['role'] !== 'seeker' || $existingAccount['status'] !== 'active') {
                                throw new RuntimeException('Email Facebook ini sudah digunakan akun yang tidak dapat masuk sebagai User.');
                            }
                            $userId = (int) $existingAccount['id'];
                        } else {
                            $createAccount = $pdo->prepare(
                                "INSERT INTO users (name,username,email,password_hash,role)
                                 VALUES (:name,:username,:email,:password_hash,'seeker')"
                            );
                            $createAccount->execute([
                                'name' => substr($facebookName, 0, 100),
                                'username' => generate_unique_username($pdo, $facebookName),
                                'email' => $facebookEmail,
                                'password_hash' => password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT),
                            ]);
                            $userId = (int) $pdo->lastInsertId();
                            $pdo->prepare(
                                "INSERT INTO tenants (user_id,full_name,phone,email) VALUES (:user_id,:name,'',:email)"
                            )->execute(['user_id' => $userId, 'name' => $facebookName, 'email' => $facebookEmail]);
                        }
                        $pdo->prepare(
                            "INSERT INTO user_auth_providers (user_id,provider,provider_user_id)
                             VALUES (:user_id,'facebook',:provider_user_id)"
                        )->execute(['user_id' => $userId, 'provider_user_id' => $facebookId]);
                    }
                    $pdo->commit();
                } catch (Throwable $exception) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    throw $exception;
                }
                session_regenerate_id(true);
                $_SESSION['user_id'] = $userId;
                redirect('home');
            } catch (PDOException $exception) {
                error_log('Facebook account registration failed: ' . $exception->getMessage());
                set_flash('error', 'Akun Facebook tidak dapat ditautkan. Periksa apakah email sudah terdaftar.');
                redirect('login');
            } catch (RuntimeException $exception) {
                error_log('Facebook authentication failed: ' . $exception->getMessage());
                set_flash('error', $exception->getMessage());
                redirect('login');
            }
        }

        
}
