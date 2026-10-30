<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/database.php';

$requestBody = file_get_contents('php://input');
$input = [];
if ($requestBody !== '') {
    try {
        $decodedInput = json_decode($requestBody, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($decodedInput)) {
            http_response_code(400);
            echo json_encode(['error' => 'Nieprawidłowe dane żądania.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $input = $decodedInput;
    } catch (JsonException) {
        http_response_code(400);
        echo json_encode(['error' => 'Nieprawidłowy format danych.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

$isLoginRequest = ($_GET['action'] ?? '') === 'login'
    && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
$rememberSession = $isLoginRequest
    ? ($input['remember'] ?? false) === true
    : ($_COOKIE['lfp_remember'] ?? '') === '1';
if ($rememberSession) {
    ini_set('session.gc_maxlifetime', (string) (30 * 24 * 60 * 60));
}

$isSecure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
session_set_cookie_params([
    'lifetime' => $rememberSession ? 30 * 24 * 60 * 60 : 0,
    'path' => '/',
    'secure' => $isSecure,
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function respond(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function requireCsrfToken(): void
{
    $providedToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!is_string($providedToken) || !hash_equals($_SESSION['csrf_token'], $providedToken)) {
        respond(['error' => 'Sesja wygasła. Odśwież stronę i spróbuj ponownie.'], 403);
    }
}

function currentUser(PDO $database): ?array
{
    if (!isset($_SESSION['user_id'])) {
        return null;
    }

    $statement = $database->prepare(
        'SELECT id, name, email, role, completed_lessons FROM users WHERE id = :id'
    );
    $statement->execute(['id' => $_SESSION['user_id']]);
    $user = $statement->fetch();

    if (!$user) {
        unset($_SESSION['user_id']);
        return null;
    }

    return [
        'id' => (int) $user['id'],
        'name' => $user['name'],
        'email' => $user['email'],
        'role' => $user['role'],
        'completedLessons' => (int) $user['completed_lessons'],
    ];
}

function requireUser(PDO $database): array
{
    $user = currentUser($database);
    if ($user === null) {
        respond(['error' => 'Zaloguj się, aby kontynuować.'], 401);
    }

    return $user;
}

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($action === 'session' && $method === 'GET') {
    try {
        $database = getDatabaseConnection();
        respond([
            'csrfToken' => $_SESSION['csrf_token'],
            'user' => currentUser($database),
        ]);
    } catch (PDOException $error) {
        error_log($error->getMessage());
        respond(['error' => 'Nie można połączyć się z bazą danych. Sprawdź konfigurację XAMPP i import bazy.'], 500);
    }
}

try {
    $database = getDatabaseConnection();

    if ($action === 'dashboard' && $method === 'GET') {
        $user = requireUser($database);
        if ($user['role'] === 'mentor') {
            respond(['user' => $user, 'requests' => []]);
        }

        $statement = $database->prepare(
            'SELECT id, topic, mentor_level, status, created_at
             FROM booking_requests
             WHERE user_id = :user_id OR email = :email
             ORDER BY created_at DESC LIMIT 50'
        );
        $statement->execute(['user_id' => $user['id'], 'email' => $user['email']]);
        respond(['user' => $user, 'requests' => $statement->fetchAll()]);
    }

    if ($method !== 'POST') {
        respond(['error' => 'Nieobsługiwana metoda żądania.'], 405);
    }

    requireCsrfToken();

    if ($action === 'register') {
        $nameValue = $input['name'] ?? null;
        $emailValue = $input['email'] ?? null;
        $passwordValue = $input['password'] ?? null;
        if (!is_string($nameValue) || !is_string($emailValue) || !is_string($passwordValue)) {
            respond(['error' => 'Sprawdź dane formularza i spróbuj ponownie.'], 422);
        }
        $name = trim($nameValue);
        $email = strtolower(trim($emailValue));
        $password = $passwordValue;
        $role = $input['role'] ?? '';
        $termsAccepted = ($input['terms'] ?? false) === true;

        if (strlen($name) < 2 || strlen($name) > 360) {
            respond(['error' => 'Imię i nazwisko musi mieć od 2 do 120 znaków.'], 422);
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254) {
            respond(['error' => 'Podaj prawidłowy adres e-mail.'], 422);
        }
        if (strlen($password) < 8 || strlen($password) > 255) {
            respond(['error' => 'Hasło musi mieć od 8 do 255 znaków.'], 422);
        }
        if (!in_array($role, ['student', 'mentor'], true)) {
            respond(['error' => 'Wybierz prawidłowy typ konta.'], 422);
        }
        if (!$termsAccepted) {
            respond(['error' => 'Zaakceptuj regulamin i politykę prywatności.'], 422);
        }

        $statement = $database->prepare(
            'INSERT INTO users (name, email, password_hash, role, terms_accepted_at)
             VALUES (:name, :email, :password_hash, :role, NOW())'
        );
        $statement->execute([
            'name' => $name,
            'email' => $email,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'role' => $role,
        ]);

        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $database->lastInsertId();
        respond(['user' => currentUser($database)], 201);
    }

    if ($action === 'login') {
        $emailValue = $input['email'] ?? null;
        $passwordValue = $input['password'] ?? null;
        if (!is_string($emailValue) || !is_string($passwordValue)) {
            respond(['error' => 'Nieprawidłowy e-mail, hasło lub typ konta.'], 401);
        }
        $email = strtolower(trim($emailValue));
        $password = $passwordValue;
        $role = $input['role'] ?? '';

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !in_array($role, ['student', 'mentor'], true)) {
            respond(['error' => 'Nieprawidłowy e-mail, hasło lub typ konta.'], 401);
        }

        $statement = $database->prepare(
            'SELECT id, password_hash, role FROM users WHERE email = :email'
        );
        $statement->execute(['email' => $email]);
        $account = $statement->fetch();

        if (!$account || $account['role'] !== $role || !password_verify($password, $account['password_hash'])) {
            respond(['error' => 'Nieprawidłowy e-mail, hasło lub typ konta.'], 401);
        }

        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $account['id'];
        if (($input['remember'] ?? false) === true) {
            setcookie('lfp_remember', '1', [
                'expires' => time() + 30 * 24 * 60 * 60,
                'path' => '/',
                'secure' => $isSecure,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        } else {
            setcookie('lfp_remember', '', [
                'expires' => time() - 42000,
                'path' => '/',
                'secure' => $isSecure,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }
        respond(['user' => currentUser($database)]);
    }

    if ($action === 'logout') {
        requireUser($database);
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $cookie = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $cookie['path'],
                'domain' => $cookie['domain'],
                'secure' => $cookie['secure'],
                'httponly' => $cookie['httponly'],
                'samesite' => $cookie['samesite'] ?? 'Lax',
            ]);
        }
        setcookie('lfp_remember', '', [
            'expires' => time() - 42000,
            'path' => '/',
            'secure' => $isSecure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_destroy();
        respond(['message' => 'Wylogowano.']);
    }

    if ($action === 'booking') {
        $nameValue = $input['name'] ?? null;
        $emailValue = $input['email'] ?? null;
        if (!is_string($nameValue) || !is_string($emailValue)) {
            respond(['error' => 'Sprawdź dane formularza i spróbuj ponownie.'], 422);
        }
        $name = trim($nameValue);
        $email = strtolower(trim($emailValue));
        $topic = $input['topic'] ?? '';
        $mentorLevel = $input['mentorLevel'] ?? 'undecided';
        $allowedTopics = [
            'Tworzenia stron internetowych',
            'Programowania w Pythonie',
            'Rozwoju kariery w IT',
            'Jeszcze nie wiem — chcę porozmawiać',
        ];
        $allowedMentorLevels = ['undecided', 'junior', 'mid', 'senior', 'tech-lead'];

        if (strlen($name) < 2 || strlen($name) > 360) {
            respond(['error' => 'Podaj imię (od 2 do 120 znaków).'], 422);
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254) {
            respond(['error' => 'Podaj prawidłowy adres e-mail.'], 422);
        }
        if (!in_array($topic, $allowedTopics, true)) {
            respond(['error' => 'Wybierz temat rozmowy.'], 422);
        }
        if (!in_array($mentorLevel, $allowedMentorLevels, true)) {
            respond(['error' => 'Wybierz prawidłowy poziom mentora.'], 422);
        }

        $user = currentUser($database);
        $statement = $database->prepare(
            'INSERT INTO booking_requests (user_id, name, email, topic, mentor_level)
             VALUES (:user_id, :name, :email, :topic, :mentor_level)'
        );
        $statement->execute([
            'user_id' => $user['id'] ?? null,
            'name' => $name,
            'email' => $email,
            'topic' => $topic,
            'mentor_level' => $mentorLevel,
        ]);
        respond(['message' => 'Zgłoszenie zostało zapisane. Skontaktujemy się z Tobą.'], 201);
    }

    if ($action === 'complete-lesson') {
        $user = requireUser($database);
        if ($user['role'] !== 'student') {
            respond(['error' => 'Ta funkcja jest dostępna tylko dla uczniów.'], 403);
        }

        $statement = $database->prepare(
            'UPDATE users
             SET completed_lessons = LEAST(completed_lessons + 1, 3)
             WHERE id = :id'
        );
        $statement->execute(['id' => $user['id']]);
        $updatedUser = currentUser($database);
        respond(['user' => $updatedUser]);
    }

    respond(['error' => 'Nie znaleziono żądanej funkcji.'], 404);
} catch (PDOException $error) {
    if ($action === 'register' && $error->getCode() === '23000') {
        respond(['error' => 'Konto z tym adresem e-mail już istnieje.'], 409);
    }

    error_log($error->getMessage());
    respond(['error' => 'Wystąpił błąd bazy danych. Spróbuj ponownie później.'], 500);
}
