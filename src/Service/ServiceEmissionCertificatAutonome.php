<?php
declare(strict_types=1);

namespace SrLicences\Service;

use InvalidArgumentException;
use RuntimeException;

final class ServiceEmissionCertificatAutonome
{
    public function emettre(
        array $licence,
        array $config,
        ?\DateTimeImmutable $dateEmission = null,
        ?string $identifiantCertificat = null,
        ?int $creePar = null
    ): array {
        $this->validerLicenceEligible($licence);

        $configSignature = (array)($config['signature'] ?? []);
        if (!(bool)($configSignature['active'] ?? false)) {
            throw new RuntimeException('La signature doit être activée pour émettre un certificat autonome.');
        }

        $methode = strtoupper(trim((string)($configSignature['methode'] ?? CertificatLicenceAutonome::METHODE_SIGNATURE)));
        if ($methode !== CertificatLicenceAutonome::METHODE_SIGNATURE) {
            throw new RuntimeException('La méthode de signature configurée doit être RSA-SHA256.');
        }

        $cheminClePrivee = trim((string)($configSignature['chemin_cle_privee'] ?? ''));
        if ($cheminClePrivee === '' || !is_file($cheminClePrivee) || !is_readable($cheminClePrivee)) {
            throw new RuntimeException('Clé privée introuvable ou illisible pour le certificat autonome.');
        }

        $pem = file_get_contents($cheminClePrivee);
        if (!is_string($pem) || trim($pem) === '') {
            throw new RuntimeException('Lecture impossible de la clé privée du certificat autonome.');
        }

        $clePrivee = openssl_pkey_get_private($pem);
        if ($clePrivee === false) {
            throw new RuntimeException('Chargement impossible de la clé privée du certificat autonome.');
        }

        $dateEmission = ($dateEmission ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC')))
            ->setTimezone(new \DateTimeZone('UTC'));

        $configCertificat = (array)($config['certificats_autonomes'] ?? []);
        $emetteur = trim((string)($configCertificat['emetteur'] ?? 'Soul Rebel Studios'));
        if ($emetteur === '') {
            throw new RuntimeException('L’émetteur configuré du certificat autonome est vide.');
        }

        $identifiantCertificat = $identifiantCertificat !== null
            ? trim($identifiantCertificat)
            : $this->genererIdentifiantCertificat($dateEmission);

        $certificatSansSignature = CertificatLicenceAutonome::creer([
            'format_version' => CertificatLicenceAutonome::FORMAT_VERSION,
            'certificate_id' => $identifiantCertificat,
            'issuer' => $emetteur,
            'license_key' => (string)($licence['cle_licence'] ?? ''),
            'module' => (string)($licence['code_module'] ?? ''),
            'license_type' => (string)($licence['type_licence'] ?? ''),
            'license_mode' => (string)($licence['mode_licence'] ?? ''),
            'sales_channel' => (string)($licence['canal_vente'] ?? ''),
            'primary_domain' => (string)($licence['domaine_principal'] ?? ''),
            'test_domains' => $licence['domaines_test_actifs'] ?? [],
            'max_version' => (string)($licence['version_max_autorisee'] ?? ''),
            'issued_at' => $dateEmission->format('Y-m-d\TH:i:s\Z'),
            'signature_method' => $methode,
        ]);

        $payloadCanonique = $certificatSansSignature->payloadCanonique();
        $signatureBinaire = '';
        $signatureOk = openssl_sign(
            $payloadCanonique,
            $signatureBinaire,
            $clePrivee,
            OPENSSL_ALGO_SHA256
        );

        if (!$signatureOk || $signatureBinaire === '') {
            throw new RuntimeException('Échec de la signature RSA-SHA256 du certificat autonome.');
        }

        $certificat = $certificatSansSignature->avecSignature(
            base64_encode($signatureBinaire)
        );
        $certificatArray = $certificat->toArray();
        $certificatJson = json_encode(
            $certificatArray,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        if (!is_string($certificatJson)) {
            throw new RuntimeException('Encodage JSON impossible du certificat autonome signé.');
        }

        $idLicence = (int)($licence['id_licence'] ?? 0);
        if ($idLicence <= 0) {
            throw new InvalidArgumentException('L’identifiant interne de licence est obligatoire pour l’émission.');
        }

        $dateEmissionSql = $dateEmission->format('Y-m-d H:i:s');

        return [
            'certificat' => $certificatArray,
            'payload_canonique' => $payloadCanonique,
            'empreinte_payload_sha256' => $certificat->empreintePayloadSha256(),
            'certificat_json' => $certificatJson,
            'donnees_persistence' => [
                'id_licence' => $idLicence,
                'identifiant_certificat' => $certificat->identifiantCertificat(),
                'version_format' => $certificat->versionFormat(),
                'emetteur' => $certificat->emetteur(),
                'code_module' => $certificat->codeModule(),
                'methode_signature' => $certificat->methodeSignature(),
                'payload_canonique' => $payloadCanonique,
                'empreinte_payload_sha256' => $certificat->empreintePayloadSha256(),
                'signature_base64' => $certificat->signatureBase64(),
                'certificat_json' => $certificatJson,
                'actif' => 1,
                'id_certificat_remplacement' => null,
                'date_emission' => $dateEmissionSql,
                'date_revocation' => null,
                'motif_revocation' => null,
                'cree_par' => $creePar !== null && $creePar > 0 ? $creePar : null,
            ],
        ];
    }

    private function validerLicenceEligible(array $licence): void
    {
        if ((int)($licence['id_licence'] ?? 0) <= 0) {
            throw new InvalidArgumentException('L’identifiant interne de licence est invalide.');
        }

        if (strtolower(trim((string)($licence['statut'] ?? ''))) !== 'active') {
            throw new InvalidArgumentException('Seule une licence active peut recevoir un certificat autonome.');
        }

        if (strtolower(trim((string)($licence['type_licence'] ?? ''))) !== CertificatLicenceAutonome::TYPE_LICENCE) {
            throw new InvalidArgumentException('La licence à certifier doit être perpétuelle.');
        }

        if (strtolower(trim((string)($licence['mode_licence'] ?? ''))) !== CertificatLicenceAutonome::MODE_LICENCE) {
            throw new InvalidArgumentException('La licence à certifier doit utiliser le mode autonome.');
        }

        if (strtolower(trim((string)($licence['canal_vente'] ?? ''))) !== CertificatLicenceAutonome::CANAL_VENTE) {
            throw new InvalidArgumentException('La licence à certifier doit provenir de PrestaShop Addons.');
        }
    }

    private function genererIdentifiantCertificat(\DateTimeImmutable $dateEmission): string
    {
        return sprintf(
            'SRLC-AUT-%s-%s',
            $dateEmission
                ->setTimezone(new \DateTimeZone('UTC'))
                ->format('Ymd\THis\Z'),
            strtoupper(bin2hex(random_bytes(8)))
        );
    }
}
