<?php
declare(strict_types=1);

namespace SrLicences\Service;

use InvalidArgumentException;
use JsonException;
use PDO;
use RuntimeException;
use SrLicences\Repository\CertificatAutonomeRepository;
use SrLicences\Repository\DemandeActivationRepository;
use SrLicences\Repository\LicenceRepository;
use Throwable;

final class ServiceActivationLicence
{
    private DemandeActivationRepository $demandeActivationRepository;
    private LicenceRepository $licenceRepository;
    private CertificatAutonomeRepository $certificatRepository;
    private ServiceDemandeActivation $serviceDemandeActivation;
    private ServiceCertificatAutonome $serviceCertificatAutonome;

    public function __construct(
        private PDO $pdo,
        private array $config
    ) {
        $this->demandeActivationRepository =
            new DemandeActivationRepository($pdo);
        $this->licenceRepository = new LicenceRepository($pdo);
        $this->certificatRepository =
            new CertificatAutonomeRepository($pdo);
        $this->serviceDemandeActivation = new ServiceDemandeActivation(
            $this->demandeActivationRepository,
            $this->licenceRepository
        );
        $this->serviceCertificatAutonome =
            new ServiceCertificatAutonome($pdo, $config);
    }

    public function validerDemandeActivation(
        int $idDemandeActivation,
        array $donneesDecision,
        ?int $creePar = null
    ): array {
        if ($idDemandeActivation <= 0) {
            throw new InvalidArgumentException(
                'Identifiant de demande invalide.'
            );
        }

        [$transactionPropre, $pointSauvegarde] =
            $this->ouvrirPorteeTransaction();

        try {
            $this->verrouillerDemandeActivation(
                $idDemandeActivation
            );

            $resultat = $this->serviceDemandeActivation
                ->validerDemandeActivation(
                    $idDemandeActivation,
                    $donneesDecision
                );

            $idLicence = (int)($resultat['id_licence'] ?? 0);
            if ($idLicence <= 0) {
                throw new RuntimeException(
                    'La validation n’a produit aucune licence exploitable.'
                );
            }

            $licence = $this->licenceRepository
                ->trouverLicenceParId($idLicence);

            if ($licence === null) {
                throw new RuntimeException(
                    'La licence créée est introuvable avant émission du certificat.'
                );
            }

            $resultat['certificat_autonome_emis'] = false;

            if ($this->necessiteCertificatAutonome($licence)) {
                $emission = $this->serviceCertificatAutonome
                    ->emettrePourLicence($idLicence, $creePar);

                $idCertificat = (int)(
                    $emission['id_certificat_autonome'] ?? 0
                );
                $identifiantCertificat = trim((string)(
                    $emission['identifiant_certificat'] ?? ''
                ));
                $certificat = (array)(
                    $emission['certificat'] ?? []
                );

                if (
                    $idCertificat <= 0
                    || $identifiantCertificat === ''
                    || $certificat === []
                ) {
                    throw new RuntimeException(
                        'L’émission obligatoire du certificat autonome est incomplète.'
                    );
                }

                $resultat['certificat_autonome_emis'] = true;
                $resultat['id_certificat_autonome'] = $idCertificat;
                $resultat['identifiant_certificat_autonome'] =
                    $identifiantCertificat;
                $resultat['certificat_autonome'] = $certificat;
            }

            $this->validerPorteeTransaction(
                $transactionPropre,
                $pointSauvegarde
            );

            return $resultat;
        } catch (Throwable $e) {
            $this->annulerPorteeTransaction(
                $transactionPropre,
                $pointSauvegarde,
                $e
            );

            throw $e;
        }
    }

    public function verifierActivation(array $donnees): array
    {
        $resultat = $this->serviceDemandeActivation
            ->verifierActivation($donnees);

        if (
            (string)($resultat['code'] ?? '')
            !== 'activation_available'
        ) {
            return $resultat;
        }

        $cleLicence = trim((string)(
            $resultat['cle_licence']
            ?? $resultat['licence_key']
            ?? ''
        ));
        $codeModule = trim((string)(
            $resultat['code_module']
            ?? $resultat['module']
            ?? ''
        ));

        if ($cleLicence === '' || $codeModule === '') {
            throw new RuntimeException(
                'La réponse d’activation ne permet pas de retrouver la licence.'
            );
        }

        $licence = $this->licenceRepository
            ->trouverLicenceParCleEtModule(
                $cleLicence,
                $codeModule
            );

        if ($licence === null) {
            throw new RuntimeException(
                'La licence de la réponse d’activation est introuvable.'
            );
        }

        if (!$this->necessiteCertificatAutonome($licence)) {
            return $resultat;
        }

        $certificatPersistant = $this->certificatRepository
            ->trouverCertificatActifParLicence(
                (int)($licence['id_licence'] ?? 0)
            );

        $certificat = $this->validerCertificatPourRestitution(
            $certificatPersistant,
            $licence
        );

        $resultat['certificat_autonome'] = $certificat;
        $resultat['identifiant_certificat_autonome'] = (string)(
            $certificatPersistant['identifiant_certificat'] ?? ''
        );
        $resultat['date_emission_certificat_autonome'] = (string)(
            $certificatPersistant['date_emission'] ?? ''
        );

        return $resultat;
    }

    private function necessiteCertificatAutonome(
        array $licence
    ): bool {
        return strtolower(trim((string)(
            $licence['mode_licence'] ?? ''
        ))) === CertificatLicenceAutonome::MODE_LICENCE
            && strtolower(trim((string)(
                $licence['canal_vente'] ?? ''
            ))) === CertificatLicenceAutonome::CANAL_VENTE;
    }

    private function validerCertificatPourRestitution(
        ?array $certificatPersistant,
        array $licence
    ): array {
        if ($certificatPersistant === null) {
            throw new RuntimeException(
                'Aucun certificat autonome actif n’est disponible pour cette licence.'
            );
        }

        if (
            (int)($certificatPersistant['actif'] ?? 0) !== 1
            || (int)($certificatPersistant['id_licence'] ?? 0)
                !== (int)($licence['id_licence'] ?? 0)
        ) {
            throw new RuntimeException(
                'Le certificat autonome actif est incohérent avec la licence.'
            );
        }

        $certificatJson = trim((string)(
            $certificatPersistant['certificat_json'] ?? ''
        ));
        $payloadCanonique = (string)(
            $certificatPersistant['payload_canonique'] ?? ''
        );
        $empreintePersistante = strtolower(trim((string)(
            $certificatPersistant['empreinte_payload_sha256'] ?? ''
        )));
        $signaturePersistante = trim((string)(
            $certificatPersistant['signature_base64'] ?? ''
        ));

        if (
            $certificatJson === ''
            || $payloadCanonique === ''
            || !preg_match('/^[a-f0-9]{64}$/', $empreintePersistante)
            || $signaturePersistante === ''
        ) {
            throw new RuntimeException(
                'Le certificat autonome actif est incomplet.'
            );
        }

        try {
            $certificat = json_decode(
                $certificatJson,
                true,
                512,
                JSON_THROW_ON_ERROR
            );
        } catch (JsonException $e) {
            throw new RuntimeException(
                'Le certificat autonome actif contient un JSON invalide.',
                0,
                $e
            );
        }

        if (!is_array($certificat)) {
            throw new RuntimeException(
                'Le certificat autonome actif ne contient pas un objet JSON.'
            );
        }

        $signatureCertificat = trim((string)(
            $certificat['signature'] ?? ''
        ));

        $modele = CertificatLicenceAutonome::creer(
            $certificat,
            $signatureCertificat
        );

        if (
            $modele->payloadCanonique() !== $payloadCanonique
            || !hash_equals(
                $empreintePersistante,
                $modele->empreintePayloadSha256()
            )
            || !hash_equals(
                $signaturePersistante,
                $modele->signatureBase64()
            )
            || $modele->toArray() !== $certificat
        ) {
            throw new RuntimeException(
                'Le certificat autonome actif ne correspond pas aux données persistées.'
            );
        }

        if (
            (string)($certificat['certificate_id'] ?? '')
                !== (string)(
                    $certificatPersistant['identifiant_certificat'] ?? ''
                )
            || (string)($certificat['license_key'] ?? '')
                !== (string)($licence['cle_licence'] ?? '')
            || (string)($certificat['module'] ?? '')
                !== (string)($licence['code_module'] ?? '')
            || (string)($certificat['primary_domain'] ?? '')
                !== (string)($licence['domaine_principal'] ?? '')
        ) {
            throw new RuntimeException(
                'Le certificat autonome actif ne correspond pas aux droits de la licence.'
            );
        }

        return $certificat;
    }

    private function ouvrirPorteeTransaction(): array
    {
        if (!$this->pdo->inTransaction()) {
            if (!$this->pdo->beginTransaction()) {
                throw new RuntimeException(
                    'Impossible de démarrer la transaction de validation d’activation.'
                );
            }

            return [true, null];
        }

        $pointSauvegarde = 'sr_activation_'
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
                    'Impossible de valider la transaction d’activation.'
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
                'Le retour arrière de la validation d’activation a échoué : '
                . $erreurRetourArriere->getMessage(),
                0,
                $erreurInitiale
            );
        }
    }

    private function verrouillerDemandeActivation(
        int $idDemandeActivation
    ): void {
        $stmt = $this->pdo->prepare(
            'SELECT id_demande_activation '
            . 'FROM sr_licence_demande_activation '
            . 'WHERE id_demande_activation = :id_demande_activation '
            . 'FOR UPDATE'
        );

        $stmt->execute([
            ':id_demande_activation' => $idDemandeActivation,
        ]);

        if ($stmt->fetchColumn() === false) {
            throw new InvalidArgumentException(
                'Demande d’activation introuvable.'
            );
        }
    }
}
