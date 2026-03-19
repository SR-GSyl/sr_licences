<?php
declare(strict_types=1);

namespace SrLicences\Config;

use PDO;
use PDOException;
use Throwable;

final class BaseDeDonnees
{
    public static function creerDepuisConfig(array $config): PDO
    {
        $bdd = (array)($config['base_de_donnees'] ?? []);

        $hote = trim((string)($bdd['hote'] ?? ''));
        $port = (int)($bdd['port'] ?? 3306);
        $nomBase = trim((string)($bdd['nom_base'] ?? ''));
        $utilisateur = (string)($bdd['utilisateur'] ?? '');
        $motDePasse = (string)($bdd['mot_de_passe'] ?? '');
        $charset = trim((string)($bdd['charset'] ?? 'utf8mb4'));

        if ($hote === '' || $nomBase === '' || $utilisateur === '') {
            throw new PDOException('Configuration de base de données incomplète.');
        }

        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $hote, $port, $nomBase, $charset);

        return new PDO($dsn, $utilisateur, $motDePasse, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }

    public static function testerConnexion(array $config): array
    {
        try {
            $pdo = self::creerDepuisConfig($config);
            $version = (string)$pdo->query('SELECT VERSION()')->fetchColumn();

            return [
                'ok' => true,
                'message' => 'Connexion BDD OK',
                'version' => $version,
            ];
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'message' => $e->getMessage(),
                'version' => '',
            ];
        }
    }
}
