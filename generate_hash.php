<?php
// Ganti password di bawah sesuai kebutuhan
$password = 'user123';

$hash = password_hash($password, PASSWORD_DEFAULT);
echo "Password: $password\n";
echo "Hash: $hash\n"; 