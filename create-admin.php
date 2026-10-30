<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/auth.php';
$email = filter_var(getenv('FIELDNOTES_ADMIN_EMAIL') ?: '', FILTER_VALIDATE_EMAIL);
$password = getenv('FIELDNOTES_ADMIN_PASSWORD') ?: '';
$firstName = trim(getenv('FIELDNOTES_ADMIN_FIRST_NAME') ?: 'Journal');
$lastName = trim(getenv('FIELDNOTES_ADMIN_LAST_NAME') ?: 'Editor');

if (!$email || strlen($password) < 16) {
    fwrite(STDERR, "Set FIELDNOTES_ADMIN_EMAIL and FIELDNOTES_ADMIN_PASSWORD (at least 16 characters) before running this command.\n");
    exit(1);
}

$statement = auth_database()->prepare(
    'INSERT INTO users (first_name, last_name, email, password_hash, role)
     VALUES (:first_name, :last_name, :email, :password_hash, \'admin\')
         ON DUPLICATE KEY UPDATE
             first_name = VALUES(first_name),
             last_name = VALUES(last_name),
             password_hash = VALUES(password_hash),
             role = \'admin\''
);
$statement->execute([
    ':first_name' => $firstName,
    ':last_name' => $lastName,
    ':email' => $email,
    ':password_hash' => password_hash($password, PASSWORD_DEFAULT),
]);
fwrite(STDOUT, "Administrator account created or updated for {$email}.\n");