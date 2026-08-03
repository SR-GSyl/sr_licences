<?php
declare(strict_types=1);

namespace SrLicences\Service;

use InvalidArgumentException;
use PDO;
use RuntimeException;
use SrLicences\Repository\CertificatAutonomeRepository;
use SrLicences\Repository\LicenceRepository;
use Throwable;

final class ServiceCertificatAutonome
{
    private LicenceRepository $licenceRepository;
    private CertificatAutonomeRepository $certificatRepository;
    private ServiceEmissionCertificatAutonome $serviceEmission;

    public function __construct(
        private PDO $pdo,
        private array $config
    ) {
        $this->licenceRepository = new LicenceRepository($pdo);
        $this->certificatRepository = new CertificatAutonomeRepository($pdo);
        $this->serviceEmission = new ServiceEmissionCertificatAutonome();
    }

    public function emettrePourLicence(
        int $idLicence,
        ?int $creePar = null,
        ?\DateTimeImmutable $dateEmission = null,
        ?string $identifiantCertificat = null
    ): array {
        if ($idLicence <= 0) {
            throw new InvalidArgumentException(
                'Identifiant de licence invalide pour l’émission du certificat autonome.'
            );
        }

        [$transactionPropre, $pointSauvegarde] =
            $this->ouvrirPorteeTransaction();

        try {
            $this->verrouillerLicence($idLicence);

            $licence = $this->licenceRepository
                ->trouverLicenceParId($idLicence);

            if ($licence === null) {
                throw new InvalidArgumentException(
                    'Licence introuvable pour l’émission du certificat autonome.'
                );
            }

            $domainesTest = $this->licenceRepository
                ->obtenirDomainesTestActifs($idLicence);

            $licence['domaines_test_actifs'] = $domainesTest;

            $emission = $this->serviceEmission->emettre(
                $licence,
                $this->config,
                $dateEmission,
                $identifiantCertificat,
                $creePar
            );

            $donneesPersistence = (array)(
                $emission['donnees_persistence'] ?? []
            );

            if (
                (int)($donneesPersistence['id_licence'] ?? 0)
                !== $idLicence
            ) {
                throw new RuntimeException(
                    'Les données d’émission ne correspondent pas à la licence demandée.'
                );
            }

            $idCertificat = $this->certificatRepository
                ->insererCertificat($donneesPersistence);

            if ($idCertificat <= 0) {
                throw new RuntimeException(
                    'L’enregistrement du certificat autonome a échoué.'
                );
            }

            $certificatsRemplaces = $this->certificatRepository
                ->remplacerCertificatsActifs(
                    $idLicence,
                    $idCertificat
                );

            $certificatPersistant = $this->certificatRepository
                ->trouverCertificatParId($idCertificat);

            $this->validerCertificatPersistant(
                $certificatPersistant,
                $idLicence,
                $idCertificat,
                $donneesPersistence
            );

            $this->validerPorteeTransaction(
                $transactionPropre,
                $pointSauvegarde
            );

            return [
                'id_certificat_autonome' => $idCertificat,
                'id_licence' => $idLicence,
                'identifiant_certificat' => (string)(
                    $donneesPersistence['identifiant_certificat'] ?? ''
                ),
                'certificat' => (array)(
                    $emission['certificat'] ?? []
                ),
                'certificat_json' => (string)(
                    $emission['certificat_json'] ?? ''
                ),
                'payload_canonique' => (string)(
                    $emission['payload_canonique'] ?? ''
                ),
                'empreinte_payload_sha256' => (string)(
                    $emission['empreinte_payload_sha256'] ?? ''
                ),
                'date_emission' => (string)(
                    $donneesPersistence['date_emission'] ?? ''
                ),
                'domaines_test_actifs' => $domainesTest,
                'certificats_remplaces' => $certificatsRemplaces,
            ];
        } catch (Throwable $e) {
            $this->annulerPorteeTransaction(
                $transactionPropre,
                $pointSauvegarde,
                $e
            );

            throw $e;
        }
    }

    private function ouvrirPorteeTransaction(): array
    {
        if (!$this->pdo->inTransaction()) {
            if (!$this->pdo->beginTransaction()) {
                throw new RuntimeException(
                    'Impossible de démarrer la transaction du certificat autonome.'
                );
            }

            return [true, null];
        }

        $pointSauvegarde = 'sr_cert_aut_'
            . bin2hex(random_bytes(6));

        $this->pdo->exec(
            'SAVEPOINT ' . $pointSauvegarde
        );

        return [false, $pointSauvegarde];
    }

    private function validerPorteeTransaction(
        bool $transactionPropre,
        ?string $pointSauvegarde
    ): void {
        if ($transactionPropre) {
            if (!$this->pdo->commit()) {
                throw new RuntimeException(
                    'Impossible de valider la transaction du certificat autonome.'
                );
            }

            return;
        }

        if ($pointSauvegarde !== null) {
            $this->pdo->exec(
                'RELEASE SAVEPOINT ' . $pointSauvegarde
            );
        }
    }

    private function annulerPorteeTransaction(
        bool $transactionPropre,
        ?string $pointSauvegarde,
        Throwable $erreurInitiale
    ): void {
        try {
            if ($transactionPropre) {
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }

                return;
            }

            if (
                $pointSauvegarde !== null
                && $this->pdo->inTransaction()
            ) {
                $this->pdo->exec(
                    'ROLLBACK TO SAVEPOINT ' . $pointSauvegarde
                );
                $this->pdo->exec(
                    'RELEASE SAVEPOINT ' . $pointSauvegarde
                );
            }
        } catch (Throwable $erreurRetourArriere) {
            throw new RuntimeException(
                'Le retour arrière du certificat autonome a échoué : '
                . $erreurRetourArriere->getMessage(),
                0,
                $erreurInitiale
            );
        }
    }

    private function verrouillerLicence(int $idLicence): void
    {
        $stmt = $this->pdo->prepare(
            'SELECT id_licence '
            . 'FROM sr_licence '
            . 'WHERE id_licence = :id_licence '
            . 'FOR UPDATE'
        );

        $stmt->execute([
            ':id_licence' => $idLicence,
        ]);

        if ($stmt->fetchColumn() === false) {
            throw new InvalidArgumentException(
                'Licence introuvable pour l’émission du certificat autonome.'
            );
        }
    }

    private function validerCertificatPersistant(
        ?array $certificat,
        int $idLicence,
        int $idCertificat,
        array $donneesPersistence
    ): void {
        if ($certificat === null) {
            throw new RuntimeException(
                'Le certificat autonome enregistré est introuvable.'
            );
        }

        if (
            (int)($certificat['id_certificat_autonome'] ?? 0)
                !== $idCertificat
            || (int)($certificat['id_licence'] ?? 0)
                !== $idLicence
            || (int)($certificat['actif'] ?? 0) !== 1
            || (string)($certificat['identifiant_certificat'] ?? '')
                !== (string)(
                    $donneesPersistence['identifiant_certificat'] ?? ''
                )
            || (string)($certificat['empreinte_payload_sha256'] ?? '')
                !== (string)(
                    $donneesPersistence['empreinte_payload_sha256'] ?? ''
                )
            || (string)($certificat['certificat_json'] ?? '')
                !== (string)(
                    $donneesPersistence['certificat_json'] ?? ''
                )
        ) {
            throw new RuntimeException(
                'Le certificat autonome persisté est incohérent.'
            );
        }
    }
}
