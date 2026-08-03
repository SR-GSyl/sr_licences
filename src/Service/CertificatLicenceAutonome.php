<?php
declare(strict_types=1);

namespace SrLicences\Service;

use InvalidArgumentException;
use RuntimeException;

final class CertificatLicenceAutonome
{
    public const FORMAT_VERSION = 1;
    public const TYPE_LICENCE = 'perpetuelle';
    public const MODE_LICENCE = 'autonome';
    public const CANAL_VENTE = 'prestashop_addons';
    public const METHODE_SIGNATURE = 'RSA-SHA256';

    private function __construct(
        private int $versionFormat,
        private string $identifiantCertificat,
        private string $emetteur,
        private string $cleLicence,
        private string $codeModule,
        private string $typeLicence,
        private string $modeLicence,
        private string $canalVente,
        private string $domainePrincipal,
        private array $domainesTest,
        private string $versionMaxAutorisee,
        private string $dateEmission,
        private string $methodeSignature,
        private string $signatureBase64
    ) {
    }

    public static function creer(
        array $donnees,
        string $signatureBase64 = ''
    ): self {
        $versionFormat = (int)($donnees['format_version'] ?? self::FORMAT_VERSION);
        if ($versionFormat !== self::FORMAT_VERSION) {
            throw new InvalidArgumentException('La version du format de certificat autonome est incompatible.');
        }

        $identifiantCertificat = self::normaliserIdentifiantCertificat(
            (string)($donnees['certificate_id'] ?? $donnees['identifiant_certificat'] ?? '')
        );
        $emetteur = self::normaliserTexteObligatoire(
            (string)($donnees['issuer'] ?? $donnees['emetteur'] ?? ''),
            191,
            'L’émetteur du certificat autonome est obligatoire.'
        );
        $cleLicence = self::normaliserCleLicence(
            (string)($donnees['license_key'] ?? $donnees['cle_licence'] ?? '')
        );
        $codeModule = self::normaliserCodeModule(
            (string)($donnees['module'] ?? $donnees['code_module'] ?? '')
        );

        $typeLicence = strtolower(trim((string)($donnees['license_type'] ?? $donnees['type_licence'] ?? '')));
        if ($typeLicence !== self::TYPE_LICENCE) {
            throw new InvalidArgumentException('Un certificat autonome doit porter une licence perpétuelle.');
        }

        $modeLicence = strtolower(trim((string)($donnees['license_mode'] ?? $donnees['mode_licence'] ?? '')));
        if ($modeLicence !== self::MODE_LICENCE) {
            throw new InvalidArgumentException('Le mode du certificat doit être autonome.');
        }

        $canalVente = strtolower(trim((string)($donnees['sales_channel'] ?? $donnees['canal_vente'] ?? '')));
        if ($canalVente !== self::CANAL_VENTE) {
            throw new InvalidArgumentException('Le certificat autonome est réservé au canal PrestaShop Addons.');
        }

        $domainePrincipal = self::normaliserDomaine(
            (string)($donnees['primary_domain'] ?? $donnees['domaine_principal'] ?? '')
        );
        if ($domainePrincipal === '') {
            throw new InvalidArgumentException('Le domaine principal du certificat autonome est obligatoire.');
        }

        $domainesTest = self::normaliserDomainesTest(
            $donnees['test_domains'] ?? $donnees['domaines_test_actifs'] ?? []
        );
        $domainesTest = array_values(array_filter(
            $domainesTest,
            static fn(string $domaine): bool => $domaine !== $domainePrincipal
        ));
        sort($domainesTest, SORT_STRING);

        $versionMaxAutorisee = self::normaliserVersionMax(
            (string)($donnees['max_version'] ?? $donnees['version_max_autorisee'] ?? '')
        );
        $dateEmission = self::normaliserDateUtc(
            (string)($donnees['issued_at'] ?? $donnees['date_emission'] ?? '')
        );

        $methodeSignature = strtoupper(trim((string)($donnees['signature_method'] ?? $donnees['methode_signature'] ?? self::METHODE_SIGNATURE)));
        if ($methodeSignature !== self::METHODE_SIGNATURE) {
            throw new InvalidArgumentException('La méthode de signature du certificat autonome doit être RSA-SHA256.');
        }

        $signatureBase64 = self::normaliserSignature($signatureBase64, true);

        return new self(
            $versionFormat,
            $identifiantCertificat,
            $emetteur,
            $cleLicence,
            $codeModule,
            $typeLicence,
            $modeLicence,
            $canalVente,
            $domainePrincipal,
            $domainesTest,
            $versionMaxAutorisee,
            $dateEmission,
            $methodeSignature,
            $signatureBase64
        );
    }

    public function avecSignature(string $signatureBase64): self
    {
        $signatureBase64 = self::normaliserSignature($signatureBase64, false);

        return new self(
            $this->versionFormat,
            $this->identifiantCertificat,
            $this->emetteur,
            $this->cleLicence,
            $this->codeModule,
            $this->typeLicence,
            $this->modeLicence,
            $this->canalVente,
            $this->domainePrincipal,
            $this->domainesTest,
            $this->versionMaxAutorisee,
            $this->dateEmission,
            $this->methodeSignature,
            $signatureBase64
        );
    }

    public function payloadCanonique(): string
    {
        $json = json_encode(
            $this->donneesSignees(),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        if (!is_string($json)) {
            throw new RuntimeException('Encodage impossible du payload canonique du certificat autonome.');
        }

        return $json;
    }

    public function empreintePayloadSha256(): string
    {
        return hash('sha256', $this->payloadCanonique());
    }

    public function toArray(): array
    {
        return $this->donneesSignees() + [
            'signature' => $this->signatureBase64,
        ];
    }

    public function identifiantCertificat(): string
    {
        return $this->identifiantCertificat;
    }

    public function versionFormat(): int
    {
        return $this->versionFormat;
    }

    public function emetteur(): string
    {
        return $this->emetteur;
    }

    public function codeModule(): string
    {
        return $this->codeModule;
    }

    public function methodeSignature(): string
    {
        return $this->methodeSignature;
    }

    public function signatureBase64(): string
    {
        return $this->signatureBase64;
    }

    public function dateEmission(): string
    {
        return $this->dateEmission;
    }

    private function donneesSignees(): array
    {
        return [
            'format_version' => $this->versionFormat,
            'certificate_id' => $this->identifiantCertificat,
            'issuer' => $this->emetteur,
            'license_key' => $this->cleLicence,
            'module' => $this->codeModule,
            'license_type' => $this->typeLicence,
            'license_mode' => $this->modeLicence,
            'sales_channel' => $this->canalVente,
            'primary_domain' => $this->domainePrincipal,
            'test_domains' => $this->domainesTest,
            'max_version' => $this->versionMaxAutorisee,
            'issued_at' => $this->dateEmission,
            'signature_method' => $this->methodeSignature,
        ];
    }

    private static function normaliserIdentifiantCertificat(string $identifiant): string
    {
        $identifiant = trim($identifiant);
        if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]{7,127}$/', $identifiant)) {
            throw new InvalidArgumentException('L’identifiant du certificat autonome est invalide.');
        }

        return $identifiant;
    }

    private static function normaliserCleLicence(string $cleLicence): string
    {
        $cleLicence = strtoupper(trim($cleLicence));
        if (!preg_match('/^[A-Z0-9][A-Z0-9._:-]{7,190}$/', $cleLicence)) {
            throw new InvalidArgumentException('La clé de licence du certificat autonome est invalide.');
        }

        return $cleLicence;
    }

    private static function normaliserCodeModule(string $codeModule): string
    {
        $codeModule = strtolower(trim($codeModule));
        if (!preg_match('/^[a-z][a-z0-9_]{1,99}$/', $codeModule)) {
            throw new InvalidArgumentException('Le code module du certificat autonome est invalide.');
        }

        return $codeModule;
    }

    private static function normaliserVersionMax(string $version): string
    {
        $version = trim($version);
        if ($version === '' || strlen($version) > 50) {
            throw new InvalidArgumentException('La version maximale autorisée du certificat autonome est invalide.');
        }

        $limites = preg_split('/[\r\n,;|]+/', $version) ?: [];
        $normalisees = [];

        foreach ($limites as $limite) {
            $limite = trim((string)$limite);
            $limite = preg_replace('/^[vV]\s*/', '', $limite) ?? '';

            if ($limite === '') {
                continue;
            }

            if (preg_match('/^(\d+(?:\.\d+)*)\.(?:\*|x)$/i', $limite, $correspondance)) {
                $limite = $correspondance[1] . '.*';
            } elseif (!preg_match('/^\d+(?:\.\d+)*$/', $limite)) {
                throw new InvalidArgumentException('La version maximale autorisée du certificat autonome est invalide.');
            }

            $normalisees[] = $limite;
        }

        $normalisees = array_values(array_unique($normalisees));
        sort($normalisees, SORT_NATURAL | SORT_FLAG_CASE);

        $versionCanonique = implode('|', $normalisees);
        if ($versionCanonique === '' || strlen($versionCanonique) > 50) {
            throw new InvalidArgumentException('La version maximale autorisée du certificat autonome est invalide.');
        }

        return $versionCanonique;
    }

    private static function normaliserDomaine(string $domaine): string
    {
        $domaine = trim(strtolower($domaine));
        if ($domaine === '') {
            return '';
        }

        $domaine = preg_replace('#^https?://#i', '', $domaine);
        $domaine = preg_replace('#/.*$#', '', (string)$domaine);
        $domaine = preg_replace('#:\d+$#', '', (string)$domaine);
        $domaine = trim((string)$domaine, " \t\n\r\0\x0B/");

        if ($domaine === '' || strlen($domaine) > 253 || !preg_match('/^[a-z0-9.-]+$/', $domaine)) {
            throw new InvalidArgumentException('Un domaine du certificat autonome est invalide.');
        }

        return $domaine;
    }

    private static function normaliserDomainesTest(mixed $domaines): array
    {
        if (is_string($domaines)) {
            $domaines = preg_split('/[\r\n,;]+/', $domaines) ?: [];
        }

        if (!is_array($domaines)) {
            throw new InvalidArgumentException('Les domaines de test du certificat autonome doivent former une liste.');
        }

        $normalises = [];
        foreach ($domaines as $domaine) {
            $normalise = self::normaliserDomaine((string)$domaine);
            if ($normalise !== '') {
                $normalises[] = $normalise;
            }
        }

        return array_values(array_unique($normalises));
    }

    private static function normaliserDateUtc(string $date): string
    {
        $date = trim($date);
        if ($date === '') {
            throw new InvalidArgumentException('La date d’émission du certificat autonome est obligatoire.');
        }

        try {
            return (new \DateTimeImmutable($date))
                ->setTimezone(new \DateTimeZone('UTC'))
                ->format('Y-m-d\TH:i:s\Z');
        } catch (\Throwable $e) {
            throw new InvalidArgumentException('La date d’émission du certificat autonome est invalide.', 0, $e);
        }
    }

    private static function normaliserSignature(string $signature, bool $autoriserVide): string
    {
        $signature = trim($signature);
        if ($signature === '' && $autoriserVide) {
            return '';
        }

        $decodee = base64_decode($signature, true);
        if ($signature === '' || !is_string($decodee) || $decodee === '') {
            throw new InvalidArgumentException('La signature du certificat autonome est invalide.');
        }

        return $signature;
    }

    private static function normaliserTexteObligatoire(
        string $texte,
        int $longueurMaximale,
        string $message
    ): string {
        $texte = trim($texte);
        if ($texte === '' || strlen($texte) > $longueurMaximale) {
            throw new InvalidArgumentException($message);
        }

        return $texte;
    }
}
