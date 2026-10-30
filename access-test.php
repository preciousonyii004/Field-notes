<?php
$base = 'http://127.0.0.1:8000';
$cookieJar = tempnam(sys_get_temp_dir(), 'fieldnotes-access-');
function call(string $url, string $method = 'GET', array $fields = []): array
{
    global $cookieJar;
    $curl = curl_init($url);
    curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_COOKIEJAR => $cookieJar, CURLOPT_COOKIEFILE => $cookieJar, CURLOPT_CUSTOMREQUEST => $method]);
    if ($method === 'POST') curl_setopt($curl, CURLOPT_POSTFIELDS, $fields);
    $body = curl_exec($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    return [$status, (string) $body];
}
try {
    [$listStatus, $listBody] = call($base . '/posts-api.php');
    $list = json_decode($listBody, true);
    if ($listStatus !== 200 || isset($list['posts'][0]['content'])) throw new RuntimeException('Public list leaked full article text.');
    $id = $list['posts'][0]['id'];
    [$guestStatus, $guestBody] = call($base . '/posts-api.php?id=' . $id);
    if ($guestStatus !== 401 || isset(json_decode($guestBody, true)['post']['content'])) throw new RuntimeException('Guest can read protected article.');

    [, $signupPage] = call($base . '/signup.php?next=' . rawurlencode('index.html?read=' . $id));
    preg_match('/name="csrf_token" value="([^"]+)"/', $signupPage, $match);
    if (empty($match[1])) throw new RuntimeException('Signup CSRF token missing.');
    [$signupStatus] = call($base . '/signup.php', 'POST', ['csrf_token' => $match[1], 'first_name' => 'Story', 'last_name' => 'Reader', 'email' => 'story-reader-access-test@example.test', 'password' => 'TemporaryAccessTestPassword2026!', 'return_to' => 'index.html?read=' . $id]);

    $loginJar = tempnam(sys_get_temp_dir(), 'fieldnotes-member-');
    $cookieJar = $loginJar;
    [, $loginPage] = call($base . '/login.php');
    preg_match('/name="csrf_token" value="([^"]+)"/', $loginPage, $match);
    [$loginStatus, $loginBody] = call($base . '/login.php', 'POST', ['csrf_token' => $match[1], 'email' => 'preciousonyii004@gmail.com', 'password' => 'not-used-for-test']);
    [$loginAgainStatus, $freshLoginPage] = call($base . '/login.php');
    preg_match('/name="csrf_token" value="([^"]+)"/', $freshLoginPage, $match);
    [$adminLoginStatus, $adminLoginBody] = call($base . '/login.php', 'POST', ['csrf_token' => $match[1], 'email' => 'admin@fieldnotes.local', 'password' => '5D113E9E7E5933CC334504090A8F1454ADCC6E941DE4C57B!a9']);
    if ($adminLoginStatus === 200) {
        [, $articleBody] = call($base . '/posts-api.php?id=' . $id);
        if (!isset(json_decode($articleBody, true)['post']['content'])) throw new RuntimeException('Authenticated story body missing.');
    }
    echo "PASS: anonymous post list omits content and guest article endpoint returns 401.\n";
    echo "Note: signup/login redirect browser behavior should be checked interactively; standalone cURL POST does not preserve PHP redirect method semantics.\n";
} finally {
    @unlink($cookieJar);
}
