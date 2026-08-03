<?php
declare(strict_types=1);

namespace SrLicences\Repository;

use PDO;

final class CertificatAutonomeRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function insererCertificat(array $donnees): int
    {
        $sql = '
            INSERT INTO sr_licence_certificat_autonome (
                id_licence,
                identifiant_certificat,
                version_format,
                emetteur,
                code_module,
                methode_signature,
                payload_canonique,
                empreinte_payload_sha256,
                signature_base64,
                certificat_json,
                actif,
                id_certificat_remplacement,
                date_emission,
                date_revocation,
                motif_revocation,
                cree_par
            ) VALUES (
                :id_licence,
                :identifiant_certificat,
                :version_format,
                :emetteur,
                :code_module,
                :methode_signature,
                :payload_canonique,
                :empreinte_payload_sha256,
                :signature_base64,
                :certificat_json,
                :actif,
                :id_certificat_remplacement,
                :date_emission,
                :date_revocation,
                :motif_revocation,
                :cree_par
            )
        ';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':id_licence' => (int)($donnees['id_licence'] ?? 0),
            ':identifiant_certificat' => (string)($donnees['identifiant_certificat'] ?? ''),
            ':version_format' => (int)($donnees['version_format'] ?? 1),
            ':emetteur' => (string)($donnees['emetteur'] ?? ''),
            ':code_module' => (string)($donnees['code_module'] ?? ''),
            ':methode_signature' => (string)($donnees['methode_signature'] ?? 'RSA-SHA256'),
            ':payload_canonique' => (string)($donnees['payload_canonique'] ?? ''),
            ':empreinte_payload_sha256' => (string)($donnees['empreinte_payload_sha256'] ?? ''),
            ':signature_base64' => (string)($donnees['signature_base64'] ?? ''),
            ':certificat_json' => (string)($donnees['certificat_json'] ?? ''),
            ':actif' => !empty($donnees['actif']) ? 1 : 0,
            ':id_certificat_remplacement' => $this->normaliserEntierNullable(
                $donnees['id_certificat_remplacement'] ?? null
            ),
            ':date_emission' => (string)($donnees['date_emission'] ?? ''),
            ':date_revocation' => $this->normaliserNullable(
                $donnees['date_revocation'] ?? null
            ),
            ':motif_revocation' => $this->normaliserNullable(
                $donnees['motif_revocation'] ?? null
            ),
            ':cree_par' => $this->normaliserEntierNullable(
                $donnees['cree_par'] ?? null
            ),
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    public function trouverCertificatParId(
        int $idCertificatAutonome
    ): ?array {
        $sql = '
            SELECT *
            FROM sr_licence_certificat_autonome
            WHERE id_certificat_autonome = :id_certificat_autonome
            LIMIT 1
        ';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':id_certificat_autonome' => $idCertificatAutonome,
        ]);

        $ligne = $stmt->fetch();

        return is_array($ligne) ? $ligne : null;
    }

    public function trouverCertificatParIdentifiant(
        string $identifiantCertificat
    ): ?array {
        $sql = '
            SELECT *
            FROM sr_licence_certificat_autonome
            WHERE identifiant_certificat = :identifiant_certificat
            LIMIT 1
        ';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':identifiant_certificat' => $identifiantCertificat,
        ]);

        $ligne = $stmt->fetch();

        return is_array($ligne) ? $ligne : null;
    }

    public function trouverCertificatActifParLicence(
        int $idLicence
    ): ?array {
        $sql = '
            SELECT *
            FROM sr_licence_certificat_autonome
            WHERE id_licence = :id_licence
              AND actif = 1
            ORDER BY
                date_emission DESC,
                id_certificat_autonome DESC
            LIMIT 1
        ';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':id_licence' => $idLicence,
        ]);

        $ligne = $stmt->fetch();

        return is_array($ligne) ? $ligne : null;
    }

    public function listerCertificatsParLicence(
        int $idLicence,
        int $limit = 50
    ): array {
        $limit = max(1, min($limit, 200));

        $sql = sprintf(
            'SELECT *
             FROM sr_licence_certificat_autonome
             WHERE id_licence = :id_licence
             ORDER BY
                 date_emission DESC,
                 id_certificat_autonome DESC
             LIMIT %d',
            $limit
        );

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':id_licence' => $idLicence,
        ]);

        return $stmt->fetchAll();
    }

    public function compterCertificatsParLicence(
        int $idLicence
    ): int {
        $sql = '
            SELECT COUNT(*)
            FROM sr_licence_certificat_autonome
            WHERE id_licence = :id_licence
        ';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':id_licence' => $idLicence,
        ]);

        return (int)$stmt->fetchColumn();
    }

    public function remplacerCertificatsActifs(
        int $idLicence,
        int $idCertificatRemplacement
    ): int {
        $sql = '
            UPDATE sr_licence_certificat_autonome
            SET
                actif = 0,
                id_certificat_remplacement = :id_certificat_remplacement,
                date_maj = NOW()
            WHERE id_licence = :id_licence
              AND actif = 1
              AND id_certificat_autonome <> :id_certificat_remplacement
        ';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':id_licence' => $idLicence,
            ':id_certificat_remplacement' => $idCertificatRemplacement,
        ]);

        return $stmt->rowCount();
    }

    public function revoquerCertificat(
        int $idCertificatAutonome,
        string $motifRevocation,
        ?string $dateRevocation = null
    ): bool {
        $dateRevocation = $dateRevocation !== null
            && trim($dateRevocation) !== ''
                ? trim($dateRevocation)
                : date('Y-m-d H:i:s');

        $sql = '
            UPDATE sr_licence_certificat_autonome
            SET
                actif = 0,
                date_revocation = :date_revocation,
                motif_revocation = :motif_revocation,
                date_maj = NOW()
            WHERE id_certificat_autonome = :id_certificat_autonome
        ';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':date_revocation' => $dateRevocation,
            ':motif_revocation' => $this->normaliserNullable(
                $motifRevocation
            ),
            ':id_certificat_autonome' => $idCertificatAutonome,
        ]);

        return $stmt->rowCount() > 0;
    }

    private function normaliserNullable(mixed $valeur): ?string
    {
        if ($valeur === null) {
            return null;
        }

        $texte = trim((string)$valeur);

        return $texte !== '' ? $texte : null;
    }

    private function normaliserEntierNullable(mixed $valeur): ?int
    {
        if ($valeur === null || $valeur === '') {
            return null;
        }

        $entier = (int)$valeur;

        return $entier > 0 ? $entier : null;
    }
}
