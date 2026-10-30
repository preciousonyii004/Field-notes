<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/auth-view.php';
start_auth_session();

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!auth_validate_csrf()) {
        http_response_code(400);
        $error = 'This form expired. Please try again.';
    } else {
        $firstName = trim((string) ($_POST['first_name'] ?? ''));
        $lastName = trim((string) ($_POST['last_name'] ?? ''));
        $email = filter_var(trim((string) ($_POST['email'] ?? '')), FILTER_VALIDATE_EMAIL);
        $password = (string) ($_POST['password'] ?? '');

        if ($firstName === '' || $lastName === '' || !$email) {
            $error = 'Enter your name and a valid email address.';
        } elseif (strlen($password) < 12) {
            $error = 'Choose a password with at least 12 characters.';
        } else {
            try {
                $statement = auth_database()->prepare(
                    'INSERT INTO users (first_name, last_name, email, password_hash) VALUES (:first_name, :last_name, :email, :password_hash)'
                );
                $statement->execute([
                    ':first_name' => $firstName,
                    ':last_name' => $lastName,
                    ':email' => $email,
                    ':password_hash' => password_hash($password, PASSWORD_DEFAULT),
                ]);
                $user = auth_database()->query('SELECT * FROM users WHERE id = ' . (int) auth_database()->lastInsertId())->fetch();
                auth_sign_in($user);
                $returnPath = auth_safe_return_path($_POST['return_to'] ?? null);
                header('Location: ' . ($returnPath ? $returnPath . '&welcome=1' : 'index.html?welcome=1'), true, 303);
                exit;
            } catch (PDOException $exception) {
                if (str_contains(strtolower($exception->getMessage()), 'unique')) {
                    $error = 'An account with that email already exists. Sign in instead.';
                } else {
                    http_response_code(500);
                    $error = 'We could not create your account right now. Please try again.';
                }
            }
        }
    }
}

$token = auth_csrf_token();
$returnPath = auth_safe_return_path($_GET['next'] ?? $_POST['return_to'] ?? null);
$loginHref = 'login.php' . ($returnPath ? '?next=' . rawurlencode($returnPath) : '');
auth_page_start('Create account', 'JOIN THE JOURNAL', 'A little more room for <em>good things.</em>', 'Create an account to make your place in the Fieldnotes community.', 'Already a member?', 'login.php');
if ($error !== '') {
    echo '<div class="auth-message" role="alert"><i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i><span>' . auth_escape($error) . '</span><button class="auth-message-close" type="button" aria-label="Dismiss message"><i class="fa-solid fa-xmark"></i></button></div>';
}
?>
          <form class="auth-form" method="post" action="signup.php" autocomplete="on">
            <input type="hidden" name="csrf_token" value="<?= auth_escape($token) ?>" />
                        <input type="hidden" name="return_to" value="<?= auth_escape($returnPath ?? '') ?>" />
            <label class="auth-field">First name<span class="auth-input-wrap"><i class="fa-regular fa-user"></i><input type="text" name="first_name" autocomplete="given-name" maxlength="80" value="<?= auth_escape((string) ($_POST['first_name'] ?? '')) ?>" placeholder="Your first name" required /></span></label>
            <label class="auth-field">Last name<span class="auth-input-wrap"><i class="fa-regular fa-user"></i><input type="text" name="last_name" autocomplete="family-name" maxlength="80" value="<?= auth_escape((string) ($_POST['last_name'] ?? '')) ?>" placeholder="Your last name" required /></span></label>
            <label class="auth-field">Email address<span class="auth-input-wrap"><i class="fa-regular fa-envelope"></i><input type="email" name="email" autocomplete="email" maxlength="254" value="<?= auth_escape((string) ($_POST['email'] ?? '')) ?>" placeholder="you@example.com" required /></span></label>
            <label class="auth-field">Password<span class="auth-input-wrap"><i class="fa-solid fa-lock"></i><input type="password" name="password" autocomplete="new-password" minlength="12" placeholder="At least 12 characters" required /></span></label>
            <p class="auth-help">Use at least 12 characters. Your password is stored securely as a hash.</p>
            <button class="auth-submit" type="submit">Create account <i class="fa-solid fa-arrow-right"></i></button>
          </form>
          <p class="auth-switch">Already have an account? <a href="<?= auth_escape($loginHref) ?>">Sign in</a></p>
<?php auth_page_end(); ?>