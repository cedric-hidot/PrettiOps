<?php

echo "Generating JWT keys...\n";

// Check if OpenSSL is loaded
if (!extension_loaded('openssl')) {
    die("OpenSSL extension is not loaded\n");
}

// Ensure the directory exists
if (!is_dir('config/jwt')) {
    mkdir('config/jwt', 0777, true);
}

// Try with minimal configuration
$privateKey = openssl_pkey_new();

if (!$privateKey) {
    $error = openssl_error_string();
    die("Failed to generate private key. OpenSSL error: " . $error . "\n");
}

// Export private key
if (!openssl_pkey_export($privateKey, $privateKeyPem)) {
    $error = openssl_error_string();
    die("Failed to export private key. OpenSSL error: " . $error . "\n");
}

file_put_contents('config/jwt/private.pem', $privateKeyPem);
echo "Private key generated: config/jwt/private.pem\n";

// Generate public key
$publicKeyDetails = openssl_pkey_get_details($privateKey);
if (!$publicKeyDetails) {
    die("Failed to get public key details\n");
}

file_put_contents('config/jwt/public.pem', $publicKeyDetails['key']);
echo "Public key generated: config/jwt/public.pem\n";

echo "JWT keys generated successfully!\n";
?>