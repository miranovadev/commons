<?php

use Composer\InstalledVersions;

// Backport the one-line upstream PSR-7 handshake fix while v0.4.1 is locked.
// https://github.com/ratchetphp/RFC6455/commit/a4c6bb885a87be3dd93e8201c0487752b140ab42
require dirname(__DIR__).'/vendor/autoload.php';

if (InstalledVersions::getPrettyVersion('ratchet/rfc6455') !== 'v0.4.1') {
    return;
}

$path = InstalledVersions::getInstallPath('ratchet/rfc6455').'/src/Handshake/ServerNegotiator.php';
$source = file_get_contents($path);
$before = "->withHeader('Sec-WebSocket-Version', \$this->getVersionNumber());";
$after = "->withHeader('Sec-WebSocket-Version', (string)\$this->getVersionNumber());";
if (str_contains($source, $after)) {
    return;
}
if (substr_count($source, $before) !== 1) {
    throw new RuntimeException('RFC6455 handshake patch no longer matches; review the upstream fix.');
}
if (file_put_contents($path, str_replace($before, $after, $source)) === false) {
    throw new RuntimeException('Could not apply the RFC6455 handshake patch.');
}
