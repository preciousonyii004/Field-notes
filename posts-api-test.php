<?php
$base = 'http://127.0.0.1:8000';
$cookieJar = tempnam(sys_get_temp_dir(), 'fieldnotes-cookie-');
$imagePath = tempnam(sys_get_temp_dir(), 'fieldnotes-image-') . '.png';
file_put_contents($imagePath, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/l5sAAAAASUVORK5CYII='));

function request_url(string $url, string $method = 'GET', array $fields = []): array
{
    global $cookieJar;
    $curl = curl_init($url);
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_COOKIEJAR => $cookieJar,
        CURLOPT_COOKIEFILE => $cookieJar,
        CURLOPT_CUSTOMREQUEST => $method,
    ]);
    if ($method === 'POST') {
        curl_setopt($curl, CURLOPT_POSTFIELDS, $fields);
    }
    $body = curl_exec($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $error = curl_error($curl);
    curl_close($curl);
    if ($body === false) {
        throw new RuntimeException($error);
    }
    return [$status, $body];
}

try {
    [, $loginPage] = request_url($base . '/login.php');
    preg_match('/name="csrf_token" value="([^"]+)"/', $loginPage, $match);
    if (empty($match[1])) throw new RuntimeException('Login CSRF token missing');
    request_url($base . '/login.php', 'POST', [
        'csrf_token' => $match[1],
        'email' => 'admin@fieldnotes.local',
        'password' => '5D113E9E7E5933CC334504090A8F1454ADCC6E941DE4C57B!a9',
    ]);

    [, $adminPage] = request_url($base . '/admin.php');
    preg_match('/id="post-csrf" value="([^"]+)"/', $adminPage, $match);
    if (empty($match[1])) throw new RuntimeException('Admin page or CSRF token unavailable');
    $csrf = $match[1];

    [$createStatus, $createdBody] = request_url($base . '/posts-api.php', 'POST', [
        'csrf_token' => $csrf,
        'title' => 'API persistence verification',
        'excerpt' => 'Temporary test story',
        'content' => 'Temporary story body.',
        'category' => 'Practice',
        'status' => 'Draft',
        'cover_image' => new CURLFile($imagePath, 'image/png', 'test.png'),
    ]);
    $created = json_decode($createdBody, true);
    if ($createStatus !== 200 || empty($created['post']['id'])) {
        throw new RuntimeException('Post creation failed: ' . $createdBody);
    }
    $postId = $created['post']['id'];
    if (!str_ends_with((string) $created['post']['image'], '.png')) {
        throw new RuntimeException('Uploaded cover path was not returned.');
    }

    [, $publicBody] = request_url($base . '/posts-api.php');
    $public = json_decode($publicBody, true);
    foreach ($public['posts'] as $post) {
        if ($post['id'] === $postId) throw new RuntimeException('Draft leaked into public posts.');
    }

    [, $adminPostsBody] = request_url($base . '/posts-api.php?manage=1');
    $adminPosts = json_decode($adminPostsBody, true);
    $found = false;
    foreach ($adminPosts['posts'] as $post) {
        if ($post['id'] === $postId) $found = true;
    }
    if (!$found) throw new RuntimeException('New draft was not persisted in MySQL.');

    [$deleteStatus, $deleteBody] = request_url($base . '/posts-api.php', 'POST', [
        'action' => 'delete', 'id' => $postId, 'csrf_token' => $csrf,
    ]);
    if ($deleteStatus !== 200 || empty(json_decode($deleteBody, true)['deleted'])) {
        throw new RuntimeException('Post delete failed: ' . $deleteBody);
    }
    echo "PASS: admin session, CSRF-protected MySQL draft persistence, private draft visibility, image upload, and delete.\n";
} finally {
    @unlink($imagePath);
    @unlink($cookieJar);
}
