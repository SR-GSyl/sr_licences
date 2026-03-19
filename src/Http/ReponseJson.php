<?php
declare(strict_types=1);

namespace SrLicences\Http;

final class ReponseJson
{
    public static function envoyer(array $donnees, int $codeHttp = 200): void
    {
        http_response_code($codeHttp);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($donnees, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        exit;
    }
}
