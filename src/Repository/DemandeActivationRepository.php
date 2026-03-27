<?php
declare(strict_types=1);

namespace SrLicences\Repository;

use PDO;

final class DemandeActivationRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function insererDemandeActivation(array $donnees): int
    {
        $sql = '
            INSERT INTO sr_licence_demande_activation (
                code_module,
                version_module,
                nom_client,
                email_client,
                numero_commande,
                domaine_principal,
                domaines_test,
                secret_suivi,
                statut,
                note_interne,
                date_creation,
                date_maj
            ) VALUES (
                :code_module,
                :version_module,
                :nom_client,
                :email_client,
                :numero_commande,
                :domaine_principal,
                :domaines_test,
                :secret_suivi,
                :statut,
                :note_interne,
                NOW(),
                NOW()
            )
        ';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':code_module' => (string)($donnees['code_module'] ?? ''),
            ':version_module' => $this->normaliserNullable($donnees['version_module'] ?? null),
            ':nom_client' => $this->normaliserNullable($donnees['nom_client'] ?? null),
            ':email_client' => $this->normaliserNullable($donnees['email_client'] ?? null),
            ':numero_commande' => $this->normaliserNullable($donnees['numero_commande'] ?? null),
            ':domaine_principal' => (string)($donnees['domaine_principal'] ?? ''),
            ':domaines_test' => $this->normaliserNullable($donnees['domaines_test'] ?? null),
            ':secret_suivi' => (string)($donnees['secret_suivi'] ?? ''),
            ':statut' => (string)($donnees['statut'] ?? 'en_attente'),
            ':note_interne' => $this->normaliserNullable($donnees['note_interne'] ?? null),
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    public function trouverDemandeParId(int $idDemandeActivation): ?array
    {
        $sql = '
            SELECT *
            FROM sr_licence_demande_activation
            WHERE id_demande_activation = :id_demande_activation
            LIMIT 1
        ';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':id_demande_activation' => $idDemandeActivation,
        ]);

        $ligne = $stmt->fetch();
        return is_array($ligne) ? $ligne : null;
    }

    public function trouverDemandeParIdEtSecret(int $idDemandeActivation, string $secretSuivi): ?array
    {
        $sql = '
            SELECT *
            FROM sr_licence_demande_activation
            WHERE id_demande_activation = :id_demande_activation
              AND secret_suivi = :secret_suivi
            LIMIT 1
        ';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':id_demande_activation' => $idDemandeActivation,
            ':secret_suivi' => $secretSuivi,
        ]);

        $ligne = $stmt->fetch();
        return is_array($ligne) ? $ligne : null;
    }

    public function listerDemandesActivation(int $limit = 100): array
    {
        $limit = max(1, min($limit, 200));

        $sql = sprintf(
            'SELECT *
             FROM sr_licence_demande_activation
             ORDER BY id_demande_activation DESC
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
            FROM sr_licence_demande_activation
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

    public function marquerDemandeValidee(int $idDemandeActivation, int $idLicence, ?string $noteInterne = null): void
    {
        $sql = '
            UPDATE sr_licence_demande_activation
            SET
                statut = :statut,
                id_licence = :id_licence,
                note_interne = :note_interne,
                date_validation = NOW(),
                date_maj = NOW()
            WHERE id_demande_activation = :id_demande_activation
        ';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':statut' => 'validee',
            ':id_licence' => $idLicence,
            ':note_interne' => $this->normaliserNullable($noteInterne),
            ':id_demande_activation' => $idDemandeActivation,
        ]);
    }

    public function marquerDemandeRefusee(int $idDemandeActivation, ?string $noteInterne = null): void
    {
        $sql = '
            UPDATE sr_licence_demande_activation
            SET
                statut = :statut,
                note_interne = :note_interne,
                date_refus = NOW(),
                date_maj = NOW()
            WHERE id_demande_activation = :id_demande_activation
        ';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':statut' => 'refusee',
            ':note_interne' => $this->normaliserNullable($noteInterne),
            ':id_demande_activation' => $idDemandeActivation,
        ]);
    }

    public function marquerDemandeTerminee(int $idDemandeActivation): void
    {
        $sql = '
            UPDATE sr_licence_demande_activation
            SET
                statut = :statut,
                date_consommation = COALESCE(date_consommation, NOW()),
                date_maj = NOW()
            WHERE id_demande_activation = :id_demande_activation
        ';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':statut' => 'terminee',
            ':id_demande_activation' => $idDemandeActivation,
        ]);
    }

    private function normaliserNullable(mixed $valeur): ?string
    {
        $texte = trim((string)$valeur);
        return $texte !== '' ? $texte : null;
    }
}
