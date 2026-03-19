<?php
declare(strict_types=1);

spl_autoload_register(static function (string $classe): void {
    $prefixe = 'SrLicences\\';
    if (strncmp($classe, $prefixe, strlen($prefixe)) !== 0) {
        return;
    }

    $relative = substr($classe, strlen($prefixe));
    $fichier = __DIR__ . '/../' . str_replace('\\', '/', $relative) . '.php';

    if (is_file($fichier)) {
        require_once $fichier;
    }
});
