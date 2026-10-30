<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/auth-view.php';
start_auth_session();

$error = '';
$success = isset($_GET['created']) ? 'Your account is ready. Sign in to continue.' : '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!auth_validate_csrf()) {
        http_response_code(400);
        $error = 'This form expired. Please try again.';
    } else {
        $email = filter_var(trim((string) ($_POST['email'] ?? '')), FILTER_VALIDATE_EMAIL);
        $password = (string) ($_POST['password'] ?? '');
        $statement = auth_database()->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
        $statement->execute([':email' => $email ?: '']);
        $user = $statement->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            $error = 'Email or password is incorrect.';
        } else {
            if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
                $update = auth_database()->prepare('UPDATE users SET password_hash = :password_hash WHERE id = :id');
                $update->execute([
                    ':password_hash' => password_hash($password, PASSWORD_DEFAULT),
                    ':id' => $user['id'],
                ]);
            }
            auth_sign_in($user);
            $returnPath = auth_safe_return_path($_POST['return_to'] ?? null);
            $destination = $user['role'] === 'admin'
                ? 'admin.php'
                : ($returnPath ? $returnPath . '&signed-in=1' : 'index.html?signed-in=1');
            header('Location: ' . $destination, true, 303);
            exit;
        }
    }
}

$token = auth_csrf_token();
$returnPath = auth_safe_return_path($_GET['next'] ?? $_POST['return_to'] ?? null);
$signupHref = 'signup.php' . ($returnPath ? '?next=' . rawurlencode($returnPath) : '');
auth_page_start('Sign in', 'WELCOME BACK', 'Good to see you <em>again.</em>', 'Sign in to return to your reading list and the stories you keep close.', 'New to Fieldnotes?', 'signup.php');
if ($success !== '') {
    echo '<div class="auth-message success" role="status"><i class="fa-solid fa-circle-check" aria-hidden="true"></i><span>' . auth_escape($success) . '</span><button class="auth-message-close" type="button" aria-label="Dismiss message"><i class="fa-solid fa-xmark"></i></button></div>';
}
if ($error !== '') {
    echo '<div class="auth-message" role="alert"><i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i><span>' . auth_escape($error) . '</span><button class="auth-message-close" type="button" aria-label="Dismiss message"><i class="fa-solid fa-xmark"></i></button></div>';
}
?>
                    <form class="auth-form" method="post" action="login.php" autocomplete="on">
            <input type="hidden" name="csrf_token" value="<?= auth_escape($token) ?>" />
                        <input type="hidden" name="return_to" value="<?= auth_escape($returnPath ?? '') ?>" />
            <label class="auth-field">Email address<span class="auth-input-wrap"><i class="fa-regular fa-envelope"></i><input type="email" name="email" autocomplete="username" maxlength="254" value="<?= auth_escape((string) ($_POST['email'] ?? '')) ?>" placeholder="you@example.com" required /></span></label>
            <label class="auth-field">Password<span class="auth-input-wrap"><i class="fa-solid fa-lock"></i><input type="password" name="password" autocomplete="current-password" placeholder="Your password" required /></span></label>
            <button class="auth-submit" type="submit">Sign in <i class="fa-solid fa-arrow-right"></i></button>
          </form>
          <p class="auth-switch">Don't have an account yet? <a href="<?= auth_escape($signupHref) ?>">Create one</a></p>
<?php auth_page_end(); ?>