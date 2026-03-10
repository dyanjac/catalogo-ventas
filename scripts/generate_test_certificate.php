<?php

$pass = $argv[1] ?? 'MaestroTest2026!';
$baseDir = dirname(__DIR__);
$dir = $baseDir . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'certificados';

if (! is_dir($dir)) {
    mkdir($dir, 0777, true);
}

$dn = [
    'countryName' => 'PE',
    'stateOrProvinceName' => 'LIMA',
    'localityName' => 'LIMA',
    'organizationName' => 'MAESTRO PANADERO TEST',
    'organizationalUnitName' => 'IT',
    'commonName' => '20123456789',
    'emailAddress' => 'test@example.com',
];

$priv = openssl_pkey_new([
    'private_key_type' => OPENSSL_KEYTYPE_RSA,
    'private_key_bits' => 2048,
]);

if (! $priv) {
    fwrite(STDERR, "No se pudo generar clave privada.\n");
    exit(1);
}

$csr = openssl_csr_new($dn, $priv, ['digest_alg' => 'sha256']);
if (! $csr) {
    fwrite(STDERR, "No se pudo generar CSR.\n");
    exit(1);
}

$cert = openssl_csr_sign($csr, null, $priv, 3650, ['digest_alg' => 'sha256']);
if (! $cert) {
    fwrite(STDERR, "No se pudo firmar certificado.\n");
    exit(1);
}

$certPath = $dir . DIRECTORY_SEPARATOR . 'greenter-test-cert.pem';
$keyPath = $dir . DIRECTORY_SEPARATOR . 'greenter-test-key.pem';
$bundlePath = $dir . DIRECTORY_SEPARATOR . 'greenter-test-bundle.pem';
$pfxPath = $dir . DIRECTORY_SEPARATOR . 'greenter-test.pfx';

openssl_x509_export($cert, $certOut);
openssl_pkey_export($priv, $keyOut, $pass);
openssl_pkcs12_export_to_file($cert, $pfxPath, $priv, $pass);

file_put_contents($certPath, $certOut);
file_put_contents($keyPath, $keyOut);
file_put_contents($bundlePath, $certOut . PHP_EOL . $keyOut);

echo "CERT_BUNDLE_PATH={$bundlePath}\n";
echo "CERT_PFX_PATH={$pfxPath}\n";
echo "CERT_PASSWORD={$pass}\n";
