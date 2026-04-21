<?php
declare(strict_types=1);

namespace SrLicences\Service;

use PDO;
use RuntimeException;

final class ServiceSecretApplication
{
    private const VERSION_CHIFFREMENT = 'sodium-v1';
    private const CHEMIN_CLE_MAITRE_PAR_DEFAUT = '/etc/sr_licences/cle_maitre_notifications.hex';

    public function __construct(private PDO $pdo, private array $config)
    {
    }

    public function secretExiste(string $groupeSecret, string $cleSecret): bool
    {
        $sql = "
            SELECT COUNT(*)
            FROM sr_secret_application
            WHERE groupe_secret = :groupe_secret
              AND cle_secret = :cle_secret
        ";

        $statement = $this->pdo->prepare($sql);
        $statement->execute([
            'groupe_secret' => trim($groupeSecret),
            'cle_secret' => trim($cleSecret),
        ]);

        return (int)$statement->fetchColumn() > 0;
    }

    public function recupererSecretDechiffre(string $groupeSecret, string $cleSecret): ?string
    {
        $sql = "
            SELECT valeur_chiffree, nonce_chiffrement, version_chiffrement
            FROM sr_secret_application
            WHERE groupe_secret = :groupe_secret
              AND cle_secret = :cle_secret
            LIMIT 1
        ";

        $statement = $this->pdo->prepare($sql);
        $statement->execute([
            'groupe_secret' => trim($groupeSecret),
            'cle_secret' => trim($cleSecret),
        ]);

        $ligne = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($ligne)) {
            return null;
        }

        $version = (string)($ligne['version_chiffrement'] ?? '');
        if ($version !== self::VERSION_CHIFFREMENT) {
            throw new RuntimeException('Version de chiffrement non supportée : ' . $version);
        }

        $valeurChiffree = base64_decode((string)($ligne['valeur_chiffree'] ?? ''), true);
        $nonce = base64_decode((string)($ligne['nonce_chiffrement'] ?? ''), true);

        if ($valeurChiffree === false || $nonce === false) {
            throw new RuntimeException('Secret stocké invalide : encodage base64 incorrect.');
        }

        if (strlen($nonce) !== SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            throw new RuntimeException('Secret stocké invalide : nonce incorrect.');
        }

        $cleMaitreBinaire = $this->chargerCleMaitreBinaire();

        $secret = sodium_crypto_secretbox_open($valeurChiffree, $nonce, $cleMaitreBinaire);
        if ($secret === false) {
            throw new RuntimeException('Impossible de déchiffrer le secret.');
        }

        return $secret;
    }

    public function enregistrerSecret(string $groupeSecret, string $cleSecret, string $valeurClaire): void
    {
        $groupeSecret = trim($groupeSecret);
        $cleSecret = trim($cleSecret);

        if ($groupeSecret === '' || $cleSecret === '') {
            throw new RuntimeException('Le groupe et la clé du secret sont obligatoires.');
        }

        $cleMaitreBinaire = $this->chargerCleMaitreBinaire();
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $valeurChiffree = sodium_crypto_secretbox($valeurClaire, $nonce, $cleMaitreBinaire);

        $sql = "
            INSERT INTO sr_secret_application
                (groupe_secret, cle_secret, valeur_chiffree, nonce_chiffrement, version_chiffrement, modifiable_interface)
            VALUES
                (:groupe_secret, :cle_secret, :valeur_chiffree, :nonce_chiffrement, :version_chiffrement, 0)
            ON DUPLICATE KEY UPDATE
                valeur_chiffree = VALUES(valeur_chiffree),
                nonce_chiffrement = VALUES(nonce_chiffrement),
                version_chiffrement = VALUES(version_chiffrement),
                date_maj = CURRENT_TIMESTAMP
        ";

        $statement = $this->pdo->prepare($sql);
        $statement->execute([
            'groupe_secret' => $groupeSecret,
            'cle_secret' => $cleSecret,
            'valeur_chiffree' => base64_encode($valeurChiffree),
            'nonce_chiffrement' => base64_encode($nonce),
            'version_chiffrement' => self::VERSION_CHIFFREMENT,
        ]);
    }

    public function supprimerSecret(string $groupeSecret, string $cleSecret): void
    {
        $sql = "
            DELETE FROM sr_secret_application
            WHERE groupe_secret = :groupe_secret
              AND cle_secret = :cle_secret
            LIMIT 1
        ";

        $statement = $this->pdo->prepare($sql);
        $statement->execute([
            'groupe_secret' => trim($groupeSecret),
            'cle_secret' => trim($cleSecret),
        ]);
    }

    public function recupererCheminCleMaitre(): string
    {
        $secretsConfig = is_array($this->config['secrets_application'] ?? null) ? $this->config['secrets_application'] : [];
        $chemin = trim((string)($secretsConfig['chemin_cle_maitre'] ?? ''));

        if ($chemin !== '') {
            return $chemin;
        }

        return self::CHEMIN_CLE_MAITRE_PAR_DEFAUT;
    }

    private function chargerCleMaitreBinaire(): string
    {
        $chemin = $this->recupererCheminCleMaitre();

        if (!is_file($chemin)) {
            throw new RuntimeException('Fichier de clé maître introuvable : ' . $chemin);
        }

        if (!is_readable($chemin)) {
            throw new RuntimeException('Fichier de clé maître non lisible : ' . $chemin);
        }

        $contenu = trim((string)file_get_contents($chemin));
        if ($contenu === '') {
            throw new RuntimeException('Fichier de clé maître vide : ' . $chemin);
        }

        if (!preg_match('/^[A-Fa-f0-9]{64}$/', $contenu)) {
            throw new RuntimeException('La clé maître doit être une chaîne hexadécimale de 64 caractères.');
        }

        $cleBinaire = sodium_hex2bin($contenu);

        if (strlen($cleBinaire) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            throw new RuntimeException('Longueur de clé maître invalide.');
        }

        return $cleBinaire;
    }
}
