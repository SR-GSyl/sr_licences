<?php
declare(strict_types=1);

namespace SrLicences\Service;

use PDO;

final class ServiceParametreApplication
{
    public function __construct(private PDO $pdo)
    {
    }

    public function recupererValeurBrute(string $groupeParametre, string $cleParametre, ?string $valeurParDefaut = null): ?string
    {
        $groupeParametre = trim($groupeParametre);
        $cleParametre = trim($cleParametre);

        if ($groupeParametre === '' || $cleParametre === '') {
            return $valeurParDefaut;
        }

        $sql = "
            SELECT valeur_parametre
            FROM sr_parametre_application
            WHERE groupe_parametre = :groupe_parametre
              AND cle_parametre = :cle_parametre
            LIMIT 1
        ";

        $statement = $this->pdo->prepare($sql);
        $statement->execute([
            'groupe_parametre' => $groupeParametre,
            'cle_parametre' => $cleParametre,
        ]);

        $valeur = $statement->fetchColumn();

        if ($valeur === false || $valeur === null) {
            return $valeurParDefaut;
        }

        return (string)$valeur;
    }

    public function recupererValeurTexte(string $groupeParametre, string $cleParametre, string $valeurParDefaut = ''): string
    {
        $valeur = $this->recupererValeurBrute($groupeParametre, $cleParametre, $valeurParDefaut);

        return $valeur ?? $valeurParDefaut;
    }

    public function recupererValeurBooleenne(string $groupeParametre, string $cleParametre, bool $valeurParDefaut = false): bool
    {
        $valeur = $this->recupererValeurBrute($groupeParametre, $cleParametre, $valeurParDefaut ? '1' : '0');

        if ($valeur === null) {
            return $valeurParDefaut;
        }

        return in_array(strtolower(trim($valeur)), ['1', 'true', 'yes', 'oui', 'on'], true);
    }

    public function recupererValeurEntiere(string $groupeParametre, string $cleParametre, int $valeurParDefaut = 0): int
    {
        $valeur = $this->recupererValeurBrute($groupeParametre, $cleParametre, (string)$valeurParDefaut);

        if ($valeur === null || !is_numeric($valeur)) {
            return $valeurParDefaut;
        }

        return (int)$valeur;
    }

    public function recupererParametresParGroupe(string $groupeParametre): array
    {
        $groupeParametre = trim($groupeParametre);

        if ($groupeParametre === '') {
            return [];
        }

        $sql = "
            SELECT cle_parametre, valeur_parametre
            FROM sr_parametre_application
            WHERE groupe_parametre = :groupe_parametre
            ORDER BY cle_parametre ASC
        ";

        $statement = $this->pdo->prepare($sql);
        $statement->execute([
            'groupe_parametre' => $groupeParametre,
        ]);

        $resultat = [];

        while ($ligne = $statement->fetch(PDO::FETCH_ASSOC)) {
            $cle = (string)($ligne['cle_parametre'] ?? '');
            if ($cle === '') {
                continue;
            }

            $resultat[$cle] = array_key_exists('valeur_parametre', $ligne)
                ? ($ligne['valeur_parametre'] === null ? null : (string)$ligne['valeur_parametre'])
                : null;
        }

        return $resultat;
    }

    public function enregistrerValeurTexte(
        string $groupeParametre,
        string $cleParametre,
        ?string $valeurParametre,
        string $typeParametre = 'texte',
        bool $modifiableInterface = true
    ): void {
        $this->enregistrerValeur(
            $groupeParametre,
            $cleParametre,
            $valeurParametre,
            $typeParametre,
            $modifiableInterface
        );
    }

    public function enregistrerValeurBooleenne(
        string $groupeParametre,
        string $cleParametre,
        bool $valeurParametre,
        bool $modifiableInterface = true
    ): void {
        $this->enregistrerValeur(
            $groupeParametre,
            $cleParametre,
            $valeurParametre ? '1' : '0',
            'booleen',
            $modifiableInterface
        );
    }

    public function enregistrerValeurEntiere(
        string $groupeParametre,
        string $cleParametre,
        int $valeurParametre,
        bool $modifiableInterface = true
    ): void {
        $this->enregistrerValeur(
            $groupeParametre,
            $cleParametre,
            (string)$valeurParametre,
            'entier',
            $modifiableInterface
        );
    }

    private function enregistrerValeur(
        string $groupeParametre,
        string $cleParametre,
        ?string $valeurParametre,
        string $typeParametre,
        bool $modifiableInterface
    ): void {
        $groupeParametre = trim($groupeParametre);
        $cleParametre = trim($cleParametre);
        $typeParametre = trim($typeParametre);

        if ($groupeParametre === '' || $cleParametre === '') {
            throw new \InvalidArgumentException('Le groupe et la clé du paramètre sont obligatoires.');
        }

        if (!in_array($typeParametre, ['texte', 'booleen', 'entier', 'email', 'url', 'choix'], true)) {
            throw new \InvalidArgumentException('Type de paramètre non supporté : ' . $typeParametre);
        }

        $sql = "
            INSERT INTO sr_parametre_application
                (groupe_parametre, cle_parametre, valeur_parametre, type_parametre, modifiable_interface)
            VALUES
                (:groupe_parametre, :cle_parametre, :valeur_parametre, :type_parametre, :modifiable_interface)
            ON DUPLICATE KEY UPDATE
                valeur_parametre = VALUES(valeur_parametre),
                type_parametre = VALUES(type_parametre),
                modifiable_interface = VALUES(modifiable_interface),
                date_maj = CURRENT_TIMESTAMP
        ";

        $statement = $this->pdo->prepare($sql);
        $statement->execute([
            'groupe_parametre' => $groupeParametre,
            'cle_parametre' => $cleParametre,
            'valeur_parametre' => $valeurParametre,
            'type_parametre' => $typeParametre,
            'modifiable_interface' => $modifiableInterface ? 1 : 0,
        ]);
    }
}
