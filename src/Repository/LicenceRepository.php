<?php
declare(strict_types=1);

namespace SrLicences\Repository;

use PDO;

final class LicenceRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function compterLicences(): int
    {
        $sql = 'SELECT COUNT(*) FROM sr_licence';
        return (int)$this->pdo->query($sql)->fetchColumn();
    }

    public function obtenirStatistiquesParStatut(): array
    {
        $statistiques = [
            'total' => 0,
            'active' => 0,
            'suspendue' => 0,
            'revoquee' => 0,
            'expiree' => 0,
            'invalide' => 0,
        ];

        $sql = '
            SELECT statut, COUNT(*) AS total_statut
            FROM sr_licence
            GROUP BY statut
        ';

        $lignes = $this->pdo->query($sql)->fetchAll();

        foreach ($lignes as $ligne) {
            $statut = (string)($ligne['statut'] ?? '');
            $total = (int)($ligne['total_statut'] ?? 0);

            $statistiques['total'] += $total;

            if (array_key_exists($statut, $statistiques)) {
                $statistiques[$statut] = $total;
            }
        }

        return $statistiques;
    }

    public function insererLicence(array $donnees): int
    {
        $sql = '
            INSERT INTO sr_licence (
                cle_licence,
                code_module,
                statut,
                type_licence,
                nom_client,
                email_client,
                domaine_principal,
                version_max_autorisee,
                date_creation,
                date_activation,
                date_expiration,
                grace_jusqu_a,
                commentaire_interne
            ) VALUES (
                :cle_licence,
                :code_module,
                :statut,
                :type_licence,
                :nom_client,
                :email_client,
                :domaine_principal,
                :version_max_autorisee,
                NOW(),
                :date_activation,
                :date_expiration,
                :grace_jusqu_a,
                :commentaire_interne
            )
        ';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':cle_licence' => (string)($donnees['cle_licence'] ?? ''),
            ':code_module' => (string)($donnees['code_module'] ?? ''),
            ':statut' => (string)($donnees['statut'] ?? 'active'),
            ':type_licence' => (string)($donnees['type_licence'] ?? 'perpetuelle'),
            ':nom_client' => $this->normaliserNullable($donnees['nom_client'] ?? null),
            ':email_client' => $this->normaliserNullable($donnees['email_client'] ?? null),
            ':domaine_principal' => $this->normaliserNullable($donnees['domaine_principal'] ?? null),
            ':version_max_autorisee' => $this->normaliserNullable($donnees['version_max_autorisee'] ?? null),
            ':date_activation' => !empty($donnees['date_activation']) ? (string)$donnees['date_activation'] : null,
            ':date_expiration' => !empty($donnees['date_expiration']) ? (string)$donnees['date_expiration'] : null,
            ':grace_jusqu_a' => !empty($donnees['grace_jusqu_a']) ? (string)$donnees['grace_jusqu_a'] : null,
            ':commentaire_interne' => $this->normaliserNullable($donnees['commentaire_interne'] ?? null),
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    public function listerLicences(int $limit = 100): array
    {
        $limit = max(1, min($limit, 200));

        $sql = sprintf(
            'SELECT
                id_licence,
                cle_licence,
                code_module,
                statut,
                type_licence,
                nom_client,
                email_client,
                domaine_principal,
                version_max_autorisee,
                date_creation,
                date_activation,
                date_expiration,
                grace_jusqu_a,
                date_maj,
                commentaire_interne
             FROM sr_licence
             ORDER BY id_licence DESC
             LIMIT %d',
            $limit
        );

        return $this->pdo->query($sql)->fetchAll();
    }

    public function trouverLicenceParId(int $idLicence): ?array
    {
        $sql = '
            SELECT
                id_licence,
                cle_licence,
                code_module,
                statut,
                type_licence,
                nom_client,
                email_client,
                domaine_principal,
                version_max_autorisee,
                date_creation,
                date_activation,
                date_expiration,
                grace_jusqu_a,
                date_maj,
                commentaire_interne
            FROM sr_licence
            WHERE id_licence = :id_licence
            LIMIT 1
        ';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':id_licence' => $idLicence,
        ]);

        $ligne = $stmt->fetch();

        return is_array($ligne) ? $ligne : null;
    }

    public function licenceExiste(int $idLicence): bool
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM sr_licence WHERE id_licence = :id_licence');
        $stmt->execute([':id_licence' => $idLicence]);

        return (int)$stmt->fetchColumn() > 0;
    }

    public function mettreAJourStatutLicence(int $idLicence, string $statut): void
    {
        if ($statut === 'active') {
            $sql = '
                UPDATE sr_licence
                SET
                    statut = :statut,
                    date_activation = COALESCE(date_activation, NOW()),
                    date_maj = NOW()
                WHERE id_licence = :id_licence
            ';
        } else {
            $sql = '
                UPDATE sr_licence
                SET
                    statut = :statut,
                    date_maj = NOW()
                WHERE id_licence = :id_licence
            ';
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':statut' => $statut,
            ':id_licence' => $idLicence,
        ]);
    }

    public function trouverLicenceParCleEtModule(string $cleLicence, string $codeModule): ?array
    {
        $sql = '
            SELECT
                id_licence,
                cle_licence,
                code_module,
                statut,
                nom_client,
                email_client,
                domaine_principal,
                version_max_autorisee,
                date_creation,
                date_activation,
                date_expiration,
                grace_jusqu_a
            FROM sr_licence
            WHERE cle_licence = :cle_licence
              AND code_module = :code_module
            LIMIT 1
        ';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':cle_licence' => $cleLicence,
            ':code_module' => $codeModule,
        ]);

        $ligne = $stmt->fetch();

        return is_array($ligne) ? $ligne : null;
    }

    public function obtenirDomainesTestActifs(int $idLicence): array
    {
        $sql = '
            SELECT domaine
            FROM sr_licence_domaine_test
            WHERE id_licence = :id_licence
              AND actif = 1
            ORDER BY domaine ASC
        ';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':id_licence' => $idLicence,
        ]);

        $domaines = [];
        foreach ($stmt->fetchAll() as $ligne) {
            $domaine = trim((string)($ligne['domaine'] ?? ''));
            if ($domaine !== '') {
                $domaines[] = $domaine;
            }
        }

        return $domaines;
    }

    public function remplacerDomainesTestLicence(int $idLicence, array $domaines): void
    {
        $sqlSuppression = 'DELETE FROM sr_licence_domaine_test WHERE id_licence = :id_licence';
        $stmtSuppression = $this->pdo->prepare($sqlSuppression);
        $stmtSuppression->execute([
            ':id_licence' => $idLicence,
        ]);

        if ($idLicence <= 0 || empty($domaines)) {
            return;
        }

        $sqlInsertion = '
            INSERT INTO sr_licence_domaine_test (
                id_licence,
                domaine,
                actif,
                date_creation
            ) VALUES (
                :id_licence,
                :domaine,
                1,
                NOW()
            )
        ';

        $stmtInsertion = $this->pdo->prepare($sqlInsertion);

        foreach ($domaines as $domaine) {
            $domaine = trim((string)$domaine);
            if ($domaine === '') {
                continue;
            }

            $stmtInsertion->execute([
                ':id_licence' => $idLicence,
                ':domaine' => $domaine,
            ]);
        }
    }

    public function obtenirDomainesTestActifsParLicences(array $idsLicence): array
    {
        $idsLicence = array_values(array_unique(array_map('intval', $idsLicence)));
        $idsLicence = array_values(array_filter($idsLicence, static fn(int $id): bool => $id > 0));

        if (empty($idsLicence)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($idsLicence), '?'));
        $sql = "
            SELECT id_licence, domaine
            FROM sr_licence_domaine_test
            WHERE actif = 1
              AND id_licence IN ($placeholders)
            ORDER BY id_licence ASC, domaine ASC
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($idsLicence);

        $resultat = [];
        foreach ($stmt->fetchAll() as $ligne) {
            $idLicence = (int)($ligne['id_licence'] ?? 0);
            $domaine = trim((string)($ligne['domaine'] ?? ''));
            if ($idLicence <= 0 || $domaine === '') {
                continue;
            }

            if (!isset($resultat[$idLicence])) {
                $resultat[$idLicence] = [];
            }

            $resultat[$idLicence][] = $domaine;
        }

        return $resultat;
    }

    private function normaliserNullable(mixed $valeur): ?string
    {
        $valeur = trim((string)$valeur);
        return $valeur !== '' ? $valeur : null;
    }
}
