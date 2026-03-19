<?php
declare(strict_types=1);

namespace SrLicences\Http;

final class Requete
{
    public static function methode(): string
    {
        return strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    }

    public static function chemin(): string
    {
        $uri = (string)($_SERVER['REQUEST_URI'] ?? '/');
        $chemin = (string)parse_url($uri, PHP_URL_PATH);

        return $chemin !== '' ? $chemin : '/';
    }

    public static function donneesEntree(): array
    {
        $methode = self::methode();

        if ($methode === 'GET') {
            return is_array($_GET) ? $_GET : [];
        }

        $contentType = (string)($_SERVER['CONTENT_TYPE'] ?? '');

        if (stripos($contentType, 'application/json') !== false) {
            $brut = file_get_contents('php://input');
            if (is_string($brut) && $brut !== '') {
                $decode = json_decode($brut, true);
                if (is_array($decode)) {
                    return $decode;
                }
            }
        }

        if (!empty($_POST) && is_array($_POST)) {
            return $_POST;
        }

        $brut = file_get_contents('php://input');
        if (is_string($brut) && $brut !== '') {
            parse_str($brut, $donnees);
            if (is_array($donnees)) {
                return $donnees;
            }
        }

        return [];
    }
}
