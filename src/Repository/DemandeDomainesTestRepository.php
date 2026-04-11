<?php
declare(strict_types=1);

namespace SrLicences\Repository;

use PDO;

final class DemandeDomainesTestRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function insererDemande(array $donnees): int
    {
        $sql = '
            INSERT INTO sr_licence_demande_domaines_test (
                id_licence,
                cle_licence,
                code_module,
                domaine_principal,
                domaines_test_actuels,
                domaines_test_demandes,
                motif,
                secret_suivi,
                statut,
                note_interne,
                date_creation,
                date_maj
            ) VALUES (
                :id_licence,
                :cle_licence,
                :code_module,
                :domaine_principal,
                :domaines_test_actuels,
                :domaines_test_demandes,
                :motif,
                :secret_suivi,
                :statut,
                :note_interne,
                NOW(),
                NOW()
            )
        ';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':id_licence' => (int)($donnees['id_licence'] ?? 0),
            ':cle_licence' => (string)($donnees['cle_licence'] ?? ''),
            ':code_module' => (string)($donnees['code_module'] ?? ''),
            ':domaine_principal' => (string)($donnees['domaine_principal'] ?? ''),
            ':domaines_test_actuels' => $this->normaliserNullable($donnees['domaines_test_actuels'] ?? null),
            ':domaines_test_demandes' => $this->normaliserNullable($donnees['domaines_test_demandes'] ?? null),
            ':motif' => $this->normaliserNullable($donnees['motif'] ?? null),
            ':secret_suivi' => (string)($donnees['secret_suivi'] ?? ''),
            ':statut' => (string)($donnees['statut'] ?? 'en_attente'),
            ':note_interne' => $this->normaliserNullable($donnees['note_interne'] ?? null),
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    public function trouverDemandeParId(int $idDemandeDomainesTest): ?array
    {
        $sql = '
            SELECT *
            FROM sr_licence_demande_domaines_test
            WHERE id_demande_domaines_test = :id_demande_domaines_test
            LIMIT 1
        ';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':id_demande_domaines_test' => $idDemandeDomainesTest,
        ]);

        $ligne = $stmt->fetch();
        return is_array($ligne) ? $ligne : null;
    }

    public function trouverDemandeParIdEtSecret(int $idDemandeDomainesTest, string $secretSuivi): ?array
    {
        $sql = '
            SELECT *
            FROM sr_licence_demande_domaines_test
            WHERE id_demande_domaines_test = :id_demande_domaines_test
              AND secret_suivi = :secret_suivi
            LIMIT 1
        ';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':id_demande_domaines_test' => $idDemandeDomainesTest,
            ':secret_suivi' => $secretSuivi,
        ]);

        $ligne = $stmt->fetch();
        return is_array($ligne) ? $ligne : null;
    }

    public function trouverDemandeEnAttenteParLicence(int $idLicence): ?array
    {
        $sql = '
            SELECT *
            FROM sr_licence_demande_domaines_test
            WHERE id_licence = :id_licence
              AND statut = :statut
            ORDER BY id_demande_domaines_test DESC
            LIMIT 1
        ';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':id_licence' => $idLicence,
            ':statut' => 'en_attente',
        ]);

        $ligne = $stmt->fetch();
        return is_array($ligne) ? $ligne : null;
    }

    public function listerDemandes(int $limit = 100): array
    {
        $limit = max(1, min($limit, 200));

        $sql = sprintf(
            'SELECT *
             FROM sr_licence_demande_domaines_test
             ORDER BY id_demande_domaines_test DESC
             LIMIT %d',
            $limit
        );

        return $this->pdo->query($sql)->fetchAll();
    }

    public function obtenirStatistiquesParStatut(): array
    {
        $statistiques = [
            'total' => 0,
            'en_attente' => 0,
            'validee' => 0,
            'refusee' => 0,
            'terminee' => 0,
        ];

        $sql = '
            SELECT statut, COUNT(*) AS total_statut
            FROM sr_licence_demande_domaines_test
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

    public function marquerDemandeValidee(int $idDemandeDomainesTest, ?string $noteInterne = null): void
    {
        $sql = '
            UPDATE sr_licence_demande_domaines_test
            SET
                statut = :statut,
                note_interne = :note_interne,
                date_validation = NOW(),
                date_maj = NOW()
            WHERE id_demande_domaines_test = :id_demande_domaines_test
        ';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':statut' => 'validee',
            ':note_interne' => $this->normaliserNullable($noteInterne),
            ':id_demande_domaines_test' => $idDemandeDomainesTest,
        ]);
    }

    public function marquerDemandeRefusee(int $idDemandeDomainesTest, ?string $noteInterne = null): void
    {
        $sql = '
            UPDATE sr_licence_demande_domaines_test
            SET
                statut = :statut,
                note_interne = :note_interne,
                date_refus = NOW(),
                date_maj = NOW()
            WHERE id_demande_domaines_test = :id_demande_domaines_test
        ';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':statut' => 'refusee',
            ':note_interne' => $this->normaliserNullable($noteInterne),
            ':id_demande_domaines_test' => $idDemandeDomainesTest,
        ]);
    }

    public function marquerDemandeTerminee(int $idDemandeDomainesTest): void
    {
        $sql = '
            UPDATE sr_licence_demande_domaines_test
            SET
                statut = :statut,
                date_consommation = COALESCE(date_consommation, NOW()),
                date_maj = NOW()
            WHERE id_demande_domaines_test = :id_demande_domaines_test
        ';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':statut' => 'terminee',
            ':id_demande_domaines_test' => $idDemandeDomainesTest,
        ]);
    }

    private function normaliserNullable(mixed $valeur): ?string
    {
        $texte = trim((string)$valeur);
        return $texte !== '' ? $texte : null;
    }
}
