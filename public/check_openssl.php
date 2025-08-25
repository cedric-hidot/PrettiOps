<?php
echo "<h1>OpenSSL Configuration Check</h1>";

echo "<h2>OpenSSL Extension</h2>";
if (extension_loaded('openssl')) {
    echo "✅ OpenSSL extension is loaded<br>";
    echo "OpenSSL Version: " . OPENSSL_VERSION_TEXT . "<br>";
} else {
    echo "❌ OpenSSL extension is NOT loaded<br>";
}

echo "<h2>Available Cipher Methods</h2>";
$ciphers = openssl_get_cipher_methods();
echo "Total ciphers available: " . count($ciphers) . "<br><br>";

echo "<h3>Looking for AES-256-CBC:</h3>";
if (in_array('AES-256-CBC', $ciphers)) {
    echo "✅ AES-256-CBC is available (uppercase)<br>";
} elseif (in_array('aes-256-cbc', $ciphers)) {
    echo "✅ aes-256-cbc is available (lowercase)<br>";
} else {
    echo "❌ AES-256-CBC is NOT available<br>";
}

echo "<h3>All AES ciphers available:</h3>";
echo "<pre>";
foreach ($ciphers as $cipher) {
    if (stripos($cipher, 'aes') !== false) {
        echo $cipher . "\n";
    }
}
echo "</pre>";

echo "<h3>All available ciphers:</h3>";
echo "<pre>";
print_r($ciphers);
echo "</pre>";
?>