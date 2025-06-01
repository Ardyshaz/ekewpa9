<?php
// generate_hash.php
$plainTextPassword = 'admin123'; // Gantikan dengan kata laluan baharu yang anda inginkan
$hashedPassword = password_hash($plainTextPassword, PASSWORD_DEFAULT);

echo "Kata laluan teks biasa: " . $plainTextPassword . "<br>";
echo "Kata laluan yang di-hash: " . $hashedPassword . "<br>";
?>
