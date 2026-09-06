<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

[$script, $privatePath, $publicPath] = array_pad($argv, 3, null);
if (! is_string($privatePath) || ! is_string($publicPath) || $privatePath === '' || $publicPath === '') {
    fwrite(STDERR, "Usage: php {$script} /secure/private.key /secure/public.key\n");
    exit(1);
}

$privateDirectory = realpath(dirname($privatePath));
$publicDirectory = realpath(dirname($publicPath));
$documentRoot = realpath(dirname(__DIR__, 2));
if (! $privateDirectory || ! $publicDirectory || ! $documentRoot || str_starts_with($privateDirectory, $documentRoot.DIRECTORY_SEPARATOR) || str_starts_with($publicDirectory, $documentRoot.DIRECTORY_SEPARATOR)) {
    fwrite(STDERR, "Key paths must use existing directories outside the web project root.\n");
    exit(1);
}

$key = openssl_pkey_new(['private_key_bits' => 3072, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
if ($key === false || ! openssl_pkey_export($key, $privatePem)) {
    fwrite(STDERR, "Unable to generate the private key.\n");
    exit(1);
}
$details = openssl_pkey_get_details($key);
if (! is_array($details) || ! isset($details['key'])) {
    fwrite(STDERR, "Unable to derive the public key.\n");
    exit(1);
}

if (file_put_contents($privatePath, $privatePem, LOCK_EX) === false || file_put_contents($publicPath, $details['key'], LOCK_EX) === false) {
    fwrite(STDERR, "Unable to write key files.\n");
    exit(1);
}
chmod($privatePath, 0600);
chmod($publicPath, 0644);
fwrite(STDOUT, "OAuth signing keys created. Store, back up, and rotate them through the documented procedure.\n");
