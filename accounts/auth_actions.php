<?php
declare(strict_types=1);

function handle_auth_action(PDO $pdo, int $userCount, string $page, string $action): void
{
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
            $email = strtolower(post_value('email'));
            $password = post_value('password');
            if ($name === '' || strlen($name) > 100 || !filter_var($email, FILTER_VALIDATE_EMAIL)
                || strlen($email) > 255 || strlen($password) < 8) {
                set_flash('error', 'Isi nama dan email dengan benar. Kata sandi minimal 8 karakter.');
                redirect('setup');
            }

            $statement = $pdo->prepare(
                "INSERT INTO users (name, email, password_hash, role) VALUES (:name, :email, :password_hash, 'admin')"
            );
            $statement->execute([
                'name' => $name,
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
            $email = strtolower(post_value('email'));
            $phone = post_value('phone');
            $gender = post_value('gender');
            $password = post_value('password');
            $role = post_value('role') ?: 'seeker';
            if (!in_array($role, ['operator', 'seeker'], true)) {
                $role = 'seeker';
            }
            $emailValid = $email === '' || (filter_var($email, FILTER_VALIDATE_EMAIL) && strlen($email) <= 255);
            if ($name === '' || strlen($name) > 100 || !$emailValid
                || ($email === '' && $phone === '') || strlen($phone) > 30
                || !in_array($gender, ['male', 'female'], true) || strlen($password) < 8) {
                set_flash('error', 'Isi nama, minimal salah satu email atau nomor telepon, jenis kelamin, serta kata sandi minimal 8 karakter.');
                redirect('register');
            }

            $profilePath = store_profile_photo_upload();
            $pdo->beginTransaction();
            try {
                $account = $pdo->prepare(
                    'INSERT INTO users (name, email, phone, gender, profile_photo_path, password_hash, role)
                     VALUES (:name, :email, :phone, :gender, :photo, :password_hash, :role)'
                );
                $account->execute([
                    'name' => $name, 'email' => $email !== '' ? $email : null,
                    'phone' => $phone !== '' ? $phone : null, 'role' => $role,
                    'gender' => $gender, 'photo' => $profilePath,
                    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                ]);
                $userId = (int) $pdo->lastInsertId();
                if ($role === 'seeker') {
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
            set_flash('success', $role === 'operator'
                ? 'Akun operator berhasil dibuat. Anda dapat langsung mengelola data kos.'
                : 'Akun User berhasil dibuat.');
            if ($role === 'seeker' && post_value('next') === 'booking'
                && (int) post_value('next_room_id') > 0) {
                redirect('booking_form&id=' . (int) post_value('next_room_id'));
            }
            redirect($role === 'operator' ? 'dashboard' : 'home');
        }

        if ($action === 'login') {
            $login = strtolower(post_value('email'));
            $statement = $pdo->prepare(
                "SELECT id, password_hash FROM users
                 WHERE (email = :email OR phone = :phone) AND status = 'active' LIMIT 1"
            );
            $statement->execute(['email' => $login, 'phone' => post_value('email')]);
            $account = $statement->fetch();
            if (!$account || !password_verify(post_value('password'), $account['password_hash'])) {
                set_flash('error', 'Email/nomor telepon atau kata sandi salah, atau akun tidak aktif.');
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
                : ($loggedInUser['role'] === 'operator' ? 'dashboard' : 'home')));
        }

        if ($action === 'logout') {
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
                                "INSERT INTO users (name,email,password_hash,role)
                                 VALUES (:name,:email,:password_hash,'seeker')"
                            );
                            $createAccount->execute([
                                'name' => substr($facebookName, 0, 100),
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
