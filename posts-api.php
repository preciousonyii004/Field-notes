<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
start_auth_session();

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

function posts_json(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function posts_require_admin(bool $requireCsrf = true): array
{
    $user = auth_current_user();
    if (!$user || $user['role'] !== 'admin') {
        posts_json(401, ['error' => 'Administrator sign-in is required.']);
    }
    if ($requireCsrf && !auth_validate_csrf()) {
        posts_json(403, ['error' => 'Your session expired. Refresh the page and try again.']);
    }
    return $user;
}

function posts_image_url(?string $path): ?string
{
    return $path ? $path : null;
}

function posts_store_cover(?string $existingPath): ?string
{
    if (!isset($_FILES['cover_image']) || $_FILES['cover_image']['error'] === UPLOAD_ERR_NO_FILE) {
        return $existingPath;
    }

    $upload = $_FILES['cover_image'];
    if ($upload['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('The image upload failed. Choose another image and retry.');
    }
    if ($upload['size'] > 5 * 1024 * 1024) {
        throw new RuntimeException('Cover images must be 5 MB or smaller.');
    }
    if (!is_uploaded_file($upload['tmp_name'])) {
        throw new RuntimeException('The uploaded image could not be verified.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($upload['tmp_name']);
    $allowedTypes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];
    if (!isset($allowedTypes[$mimeType]) || @getimagesize($upload['tmp_name']) === false) {
        throw new RuntimeException('Choose a valid JPEG, PNG, or WebP image.');
    }

    $relativeDirectory = 'uploads/covers';
    $absoluteDirectory = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'covers';
    if (!is_dir($absoluteDirectory) && !mkdir($absoluteDirectory, 0755, true) && !is_dir($absoluteDirectory)) {
        throw new RuntimeException('The image upload folder could not be created.');
    }

    $fileName = bin2hex(random_bytes(18)) . '.' . $allowedTypes[$mimeType];
    if (!move_uploaded_file($upload['tmp_name'], $absoluteDirectory . DIRECTORY_SEPARATOR . $fileName)) {
        throw new RuntimeException('The image could not be saved. Check folder permissions.');
    }

    return $relativeDirectory . '/' . $fileName;
}

function posts_remove_cover(?string $path): void
{
    if (!$path || !preg_match('#^uploads/covers/[a-f0-9]{36}\.(jpg|png|webp)$#', $path)) {
        return;
    }
    $fullPath = __DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);
    if (is_file($fullPath)) {
        @unlink($fullPath);
    }
}

function posts_find(PDO $database, int $id): ?array
{
    $statement = $database->prepare('SELECT * FROM posts WHERE id = :id');
    $statement->execute([':id' => $id]);
    $post = $statement->fetch();
    return $post ?: null;
}

function posts_to_public(array $post, bool $includeContent = false): array
{
    $result = [
        'id' => (string) $post['id'],
        'title' => $post['title'],
        'excerpt' => $post['excerpt'],
        'category' => $post['category'],
        'image' => posts_image_url($post['image_path']),
        'status' => $post['status'],
        'date' => substr((string) $post['created_at'], 0, 10),
    ];
    if ($includeContent) {
        $result['content'] = $post['content'];
    }
    return $result;
}

try {
    $database = auth_database();
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $isAdminRequest = !empty($_GET['manage']) || $method !== 'GET';

    if ($method === 'GET') {
        if (isset($_GET['id'])) {
            $user = auth_current_user();
            if (!$user) {
                posts_json(401, ['error' => 'Create an account or sign in to read the full story.']);
            }
            $id = filter_var($_GET['id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if (!$id) {
                posts_json(400, ['error' => 'A valid story identifier is required.']);
            }
            $statement = $database->prepare("SELECT * FROM posts WHERE id = :id AND status = 'Published'");
            $statement->execute([':id' => $id]);
            $post = $statement->fetch();
            if (!$post) {
                posts_json(404, ['error' => 'That story could not be found.']);
            }
            posts_json(200, ['post' => posts_to_public($post, true)]);
        }
        if ($isAdminRequest) {
            posts_require_admin(false);
            $posts = $database->query('SELECT * FROM posts ORDER BY created_at DESC, id DESC')->fetchAll();
        } else {
            $posts = $database->query("SELECT * FROM posts WHERE status = 'Published' ORDER BY created_at DESC, id DESC")->fetchAll();
        }
        posts_json(200, [
            'posts' => array_map(
                static fn (array $post): array => posts_to_public($post, $isAdminRequest),
                $posts
            ),
        ]);
    }

    $admin = posts_require_admin();
    if ($method === 'POST') {
        if (($_POST['action'] ?? '') === 'import_legacy') {
            $legacyPayload = (string) ($_POST['legacy_posts'] ?? '');
            if (strlen($legacyPayload) > 2 * 1024 * 1024) {
                posts_json(413, ['error' => 'Legacy posts import is larger than 2 MB.']);
            }
            $legacyPosts = json_decode($legacyPayload, true);
            if (!is_array($legacyPosts) || count($legacyPosts) > 200) {
                posts_json(400, ['error' => 'Legacy posts payload is invalid or too large.']);
            }

            $allowedCategories = ['Living', 'Culture', 'Places', 'Practice'];
            $insert = $database->prepare(
                'INSERT INTO posts (author_id, title, excerpt, content, category, image_path, status, created_at)
                 SELECT :author_id, :title, :excerpt, :content, :category, :image_path, :status, :created_at
                 WHERE NOT EXISTS (SELECT 1 FROM posts WHERE title = :existing_title)'
            );
            $imported = 0;
            foreach ($legacyPosts as $legacyPost) {
                if (!is_array($legacyPost)) {
                    continue;
                }
                $title = trim((string) ($legacyPost['title'] ?? ''));
                $excerpt = trim((string) ($legacyPost['excerpt'] ?? ''));
                $content = trim((string) ($legacyPost['content'] ?? ''));
                $category = (string) ($legacyPost['category'] ?? '');
                $status = (string) ($legacyPost['status'] ?? 'Draft');
                $image = trim((string) ($legacyPost['image'] ?? ''));
                $date = (string) ($legacyPost['date'] ?? '');
                if ($title === '' || mb_strlen($title) > 160 || $excerpt === '' || mb_strlen($excerpt) > 240
                    || $content === '' || !in_array($category, $allowedCategories, true)
                    || !in_array($status, ['Published', 'Draft'], true)) {
                    continue;
                }
                if ($image !== '' && (!filter_var($image, FILTER_VALIDATE_URL)
                    || !in_array(strtolower((string) parse_url($image, PHP_URL_SCHEME)), ['http', 'https'], true))) {
                    $image = '';
                }
                $createdAt = preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date . ' 12:00:00' : date('Y-m-d H:i:s');
                $insert->execute([
                    ':author_id' => $admin['id'],
                    ':title' => $title,
                    ':excerpt' => $excerpt,
                    ':content' => $content,
                    ':category' => $category,
                    ':image_path' => $image !== '' ? $image : null,
                    ':status' => $status,
                    ':created_at' => $createdAt,
                    ':existing_title' => $title,
                ]);
                $imported += $insert->rowCount();
            }
            posts_json(200, ['imported' => $imported]);
        }

        if (($_POST['action'] ?? '') === 'delete') {
            $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if (!$id) {
                posts_json(400, ['error' => 'A valid story identifier is required.']);
            }
            $post = posts_find($database, $id);
            if (!$post) {
                posts_json(404, ['error' => 'That story no longer exists.']);
            }
            $statement = $database->prepare('DELETE FROM posts WHERE id = :id');
            $statement->execute([':id' => $id]);
            posts_remove_cover($post['image_path']);
            posts_json(200, ['deleted' => true]);
        }

        $idValue = trim((string) ($_POST['id'] ?? ''));
        $id = $idValue === '' ? null : filter_var($idValue, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($idValue !== '' && !$id) {
            posts_json(400, ['error' => 'Invalid story identifier.']);
        }

        $title = trim((string) ($_POST['title'] ?? ''));
        $excerpt = trim((string) ($_POST['excerpt'] ?? ''));
        $content = trim((string) ($_POST['content'] ?? ''));
        $category = (string) ($_POST['category'] ?? '');
        $status = (string) ($_POST['status'] ?? 'Draft');
        $allowedCategories = ['Living', 'Culture', 'Places', 'Practice'];
        if ($title === '' || mb_strlen($title) > 160 || $excerpt === '' || mb_strlen($excerpt) > 240 || $content === '') {
            posts_json(400, ['error' => 'Add a title, short introduction, and story content within the limits.']);
        }
        if (!in_array($category, $allowedCategories, true) || !in_array($status, ['Published', 'Draft'], true)) {
            posts_json(400, ['error' => 'Choose a valid category and publishing status.']);
        }

        $existing = $id ? posts_find($database, $id) : null;
        if ($id && !$existing) {
            posts_json(404, ['error' => 'That story no longer exists.']);
        }
        $oldImagePath = $existing['image_path'] ?? null;
        $newImagePath = posts_store_cover($oldImagePath);
        $externalImageUrl = trim((string) ($_POST['image_url'] ?? ''));
        if (empty($_FILES['cover_image']['name']) && $externalImageUrl !== '') {
            if ($externalImageUrl === $oldImagePath) {
                $newImagePath = $oldImagePath;
            } elseif (!filter_var($externalImageUrl, FILTER_VALIDATE_URL) || !in_array(strtolower((string) parse_url($externalImageUrl, PHP_URL_SCHEME)), ['http', 'https'], true)) {
                posts_json(400, ['error' => 'Enter a valid HTTP or HTTPS image URL.']);
            } else {
                $newImagePath = $externalImageUrl;
            }
        }

        if ($existing) {
            $statement = $database->prepare(
                'UPDATE posts SET title = :title, excerpt = :excerpt, content = :content, category = :category,
                    image_path = :image_path, status = :status WHERE id = :id'
            );
            $statement->execute([
                ':title' => $title,
                ':excerpt' => $excerpt,
                ':content' => $content,
                ':category' => $category,
                ':image_path' => $newImagePath,
                ':status' => $status,
                ':id' => $id,
            ]);
            if ($oldImagePath !== $newImagePath) {
                posts_remove_cover($oldImagePath);
            }
            $savedPostId = $id;
        } else {
            $statement = $database->prepare(
                'INSERT INTO posts (author_id, title, excerpt, content, category, image_path, status)
                 VALUES (:author_id, :title, :excerpt, :content, :category, :image_path, :status)'
            );
            $statement->execute([
                ':author_id' => $admin['id'],
                ':title' => $title,
                ':excerpt' => $excerpt,
                ':content' => $content,
                ':category' => $category,
                ':image_path' => $newImagePath,
                ':status' => $status,
            ]);
            $savedPostId = (int) $database->lastInsertId();
        }

        posts_json(200, ['post' => posts_to_public(posts_find($database, (int) $savedPostId))]);
    }

    header('Allow: GET, POST');
    posts_json(405, ['error' => 'Method not allowed.']);
} catch (RuntimeException $exception) {
    posts_json(400, ['error' => $exception->getMessage()]);
} catch (Throwable $exception) {
    error_log('Posts API error: ' . $exception->getMessage());
    posts_json(500, ['error' => 'The journal could not complete that request. Please try again.']);
}