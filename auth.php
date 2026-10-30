<?php
declare(strict_types=1);

const AUTH_SESSION_NAME = 'fieldnotes_session';

function start_auth_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    session_name(AUTH_SESSION_NAME);
    session_set_cookie_params([
        'httponly' => true,
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'samesite' => 'Lax',
        'path' => '/',
    ]);
    session_start();
}

function auth_database(): PDO
{
    static $database = null;
    if ($database instanceof PDO) {
        return $database;
    }

    $host = getenv('FIELDNOTES_DB_HOST') ?: '127.0.0.1';
    $port = getenv('FIELDNOTES_DB_PORT') ?: '3306';
    $databaseName = getenv('FIELDNOTES_DB_NAME') ?: 'fieldnotes';
    $username = getenv('FIELDNOTES_DB_USER') ?: 'root';
    $password = getenv('FIELDNOTES_DB_PASSWORD') ?: '';

    if (!preg_match('/^[a-zA-Z0-9_]+$/', $databaseName)) {
        throw new RuntimeException('FIELDNOTES_DB_NAME contains unsupported characters.');
    }

    $server = new PDO(
        "mysql:host={$host};port={$port};charset=utf8mb4",
        $username,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $server->exec("CREATE DATABASE IF NOT EXISTS `{$databaseName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

    $database = new PDO(
        "mysql:host={$host};port={$port};dbname={$databaseName};charset=utf8mb4",
        $username,
        $password,
        [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
    $database->exec(
        'CREATE TABLE IF NOT EXISTS users (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            first_name VARCHAR(80) NOT NULL,
            last_name VARCHAR(80) NOT NULL,
            email VARCHAR(254) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            role ENUM(\'member\', \'admin\') NOT NULL DEFAULT \'member\',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
    $database->exec(
        'CREATE TABLE IF NOT EXISTS posts (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            author_id BIGINT UNSIGNED NULL,
            title VARCHAR(160) NOT NULL,
            excerpt VARCHAR(240) NOT NULL,
            content MEDIUMTEXT NOT NULL,
            category ENUM(\'Living\', \'Culture\', \'Places\', \'Practice\') NOT NULL,
            image_path VARCHAR(255) NULL,
            status ENUM(\'Published\', \'Draft\') NOT NULL DEFAULT \'Draft\',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT fk_posts_author FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_posts_status_created (status, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
    $starterPosts = [
        ['The quiet art of doing less', 'A gentle case for leaving a little space around the edges of your day.', 'We have become very good at filling every available inch. The calendar, the shelf, the silence between one thought and the next. But a day with a little breathing room has its own kind of usefulness. It lets an idea finish arriving. It gives the ordinary things time to become visible. Start small: take the longer way home, leave one evening unplanned, or sit with your coffee until it is gone.', 'Living', 'https://images.unsplash.com/photo-1445116572660-236099ec97a0?auto=format&fit=crop&w=1000&q=80', '2026-10-18 12:00:00'],
        ['A city is best read on foot', 'On noticing the small, unplanned details that make a place feel like itself.', 'A city reveals itself in fragments: a hand-painted sign, a window full of plants, the smell of bread at the corner you nearly missed. Walking gives these details room to find you. Leave the map in your pocket for a while and let curiosity take the lead. You will not see everything, of course. That is rather the point.', 'Places', 'https://images.unsplash.com/photo-1519608487953-e999c86e7455?auto=format&fit=crop&w=1000&q=80', '2026-10-12 12:00:00'],
        ['Objects with a second life', 'A few useful thoughts on buying less and keeping the things that earn their place.', 'The things we keep longest are rarely the newest. They are the bowl that fits your hands, the jacket with a repaired elbow, the notebook you keep returning to. Buying thoughtfully is not about turning every object into a project. It is about giving yourself a moment to ask whether something will be useful, beautiful, or simply a pleasure to live alongside.', 'Culture', 'https://images.unsplash.com/photo-1490312278390-ab64016e0aa9?auto=format&fit=crop&w=1000&q=80', '2026-10-05 12:00:00'],
        ['A notebook is a place to begin', 'Forget the perfect first page. Let your notes be useful before they are beautiful.', 'A blank page can feel like a small demand. The quickest way past it is to make the first mark unimportant: a list, a question, a line about the weather. Notes do not have to perform. They can simply hold a thought long enough for you to come back to it. The good page is the one you used.', 'Practice', 'https://images.unsplash.com/photo-1455390582262-044cdead277a?auto=format&fit=crop&w=1000&q=80', '2026-09-27 12:00:00'],
    ];
    $seed = $database->prepare(
        'INSERT INTO posts (title, excerpt, content, category, image_path, status, created_at)
         SELECT :title, :excerpt, :content, :category, :image_path, \'Published\', :created_at
         WHERE NOT EXISTS (SELECT 1 FROM posts WHERE title = :existing_title)'
    );
    foreach ($starterPosts as [$title, $excerpt, $content, $category, $image, $createdAt]) {
        $seed->execute([
            ':title' => $title,
            ':excerpt' => $excerpt,
            ':content' => $content,
            ':category' => $category,
            ':image_path' => $image,
            ':created_at' => $createdAt,
            ':existing_title' => $title,
        ]);
    }
    return $database;
}

function auth_csrf_token(): string
{
    start_auth_session();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function auth_validate_csrf(): bool
{
    start_auth_session();
    $submittedToken = $_POST['csrf_token'] ?? '';
    return is_string($submittedToken)
        && isset($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $submittedToken);
}

function auth_current_user(): ?array
{
    start_auth_session();
    return isset($_SESSION['user']) && is_array($_SESSION['user'])
        ? $_SESSION['user']
        : null;
}

function auth_sign_in(array $user): void
{
    start_auth_session();
    session_regenerate_id(true);
    $_SESSION['user'] = [
        'id' => (int) $user['id'],
        'first_name' => (string) $user['first_name'],
        'last_name' => (string) $user['last_name'],
        'email' => (string) $user['email'],
        'role' => (string) $user['role'],
    ];
}

function auth_require_admin(): array
{
    $user = auth_current_user();
    if (!$user || $user['role'] !== 'admin') {
        header('Location: login.php', true, 302);
        exit;
    }
    return $user;
}

function auth_escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function auth_safe_return_path(mixed $candidate): ?string
{
    if (!is_string($candidate) || !preg_match('#^/?index\.html(?:\?read=[1-9][0-9]*)?$#', $candidate)) {
        return null;
    }
    return ltrim($candidate, '/');
}