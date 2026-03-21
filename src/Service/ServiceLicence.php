<?php
declare(strict_types=1);

namespace SrLicences\Service;

use InvalidArgumentException;
use SrLicences\Repository\LicenceRepository;

final class ServiceLicence
{
    public function __construct(private LicenceRepository $licenceRepository)
    {
    }

    public function compterLicences(): int
    {
        return $this->licenceRepository->compterLicences();
    }

    public function obtenirStatistiquesTableauDeBord(): array
    {
        return $this->licenceRepository->obtenirStatistiquesParStatut();
    }

    public function obtenirLicencesTableauDeBord(int $limit = 100): array
    {
        $licences = $this->licenceRepository->listerLicences($limit);
        if (empty($licences)) {
            return [];
        }

        $idsLicence = [];
        foreach ($licences as $licence) {
            $idLicence = (int)($licence['id_licence'] ?? 0);
            if ($idLicence > 0) {
                $idsLicence[] = $idLicence;
            }
        }

        $domainesParLicence = $this->licenceRepository->obtenirDomainesTestActifsParLicences($idsLicence);

        foreach ($licences as &$licence) {
            $idLicence = (int)($licence['id_licence'] ?? 0);
            $domaines = $domainesParLicence[$idLicence] ?? [];
            $licence['domaines_test_actifs'] = $domaines;
            $licence['domaines_test_actifs_texte'] = implode(', ', $domaines);
        }
        unset($licence);

        return $licences;
    }

    public function creerLicence(array $donnees): array
    {
        $codeModule = trim((string)($donnees['code_module'] ?? ''));
        $statut = trim((string)($donnees['statut'] ?? 'active'));
        $typeLicence = trim((string)($donnees['type_licence'] ?? 'perpetuelle'));
        $nomClient = trim((string)($donnees['nom_client'] ?? ''));
        $emailClient = trim((string)($donnees['email_client'] ?? ''));
        $domainePrincipal = $this->normaliserDomaine((string)($donnees['domaine_principal'] ?? ''));
        $versionMax = trim((string)($donnees['version_max_autorisee'] ?? ''));
        $commentaire = trim((string)($donnees['commentaire_interne'] ?? ''));
        $domainesTest = $this->extraireDomainesTest($donnees['domaines_test'] ?? '');
        $dateExpiration = $this->normaliserDateHeureNullable($donnees['date_expiration'] ?? null);
        $graceJusquA = $this->normaliserDateHeureNullable($donnees['grace_jusqu_a'] ?? null);

        if ($codeModule === '') {
            throw new InvalidArgumentException('Le code module est obligatoire.');
        }

        if (!in_array($statut, ['active', 'suspendue', 'revoquee', 'expiree', 'invalide'], true)) {
            throw new InvalidArgumentException('Le statut fourni est invalide.');
        }

        if (!in_array($typeLicence, ['perpetuelle', 'abonnement'], true)) {
            throw new InvalidArgumentException('Le type de licence fourni est invalide.');
        }

        if ($emailClient !== '' && filter_var($emailClient, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('L’adresse e-mail fournie est invalide.');
        }

        if ($domainePrincipal !== '') {
            $domainesTest = array_values(array_filter(
                $domainesTest,
                fn(string $domaine): bool => $domaine !== $domainePrincipal
            ));
        }

        if ($typeLicence === 'perpetuelle') {
            $dateExpiration = null;
            $graceJusquA = null;
        }

        $cleLicence = $this->genererCleLicence($codeModule);
        $dateActivation = $statut === 'active' ? date('Y-m-d H:i:s') : null;

        $idLicence = $this->licenceRepository->insererLicence([
            'cle_licence' => $cleLicence,
            'code_module' => $codeModule,
            'statut' => $statut,
            'type_licence' => $typeLicence,
            'nom_client' => $nomClient,
            'email_client' => $emailClient,
            'domaine_principal' => $domainePrincipal,
            'version_max_autorisee' => $versionMax,
            'date_activation' => $dateActivation,
            'date_expiration' => $dateExpiration,
            'grace_jusqu_a' => $graceJusquA,
            'commentaire_interne' => $commentaire,
        ]);

        $this->licenceRepository->remplacerDomainesTestLicence($idLicence, $domainesTest);

        return [
            'id_licence' => $idLicence,
            'cle_licence' => $cleLicence,
            'type_licence' => $typeLicence,
            'date_expiration' => $dateExpiration,
            'grace_jusqu_a' => $graceJusquA,
            'domaines_test' => $domainesTest,
        ];
    }

    public function changerStatutLicence(int $idLicence, string $actionStatut): array
    {
        if ($idLicence <= 0) {
            throw new InvalidArgumentException('Identifiant de licence invalide.');
        }

        $correspondance = [
            'reactiver' => 'active',
            'suspendre' => 'suspendue',
            'revoquer' => 'revoquee',
        ];

        if (!isset($correspondance[$actionStatut])) {
            throw new InvalidArgumentException('Action de statut invalide.');
        }

        if (!$this->licenceRepository->licenceExiste($idLicence)) {
            throw new InvalidArgumentException('Licence introuvable.');
        }

        $nouveauStatut = $correspondance[$actionStatut];
        $this->licenceRepository->mettreAJourStatutLicence($idLicence, $nouveauStatut);

        return [
            'id_licence' => $idLicence,
            'statut' => $nouveauStatut,
        ];
    }

    public function verifierLicencePourApi(array $donnees): array
    {
        $module = trim((string)($donnees['module'] ?? $donnees['code_module'] ?? ''));
        $cleLicence = trim((string)($donnees['licence_key'] ?? $donnees['cle_licence'] ?? ''));
        $domaineDemande = $this->normaliserDomaine((string)($donnees['domain'] ?? $donnees['domaine'] ?? ''));

        if ($module === '') {
            throw new InvalidArgumentException('Le paramètre module est obligatoire.');
        }

        if ($cleLicence === '') {
            throw new InvalidArgumentException('Le paramètre licence_key est obligatoire.');
        }

        $maintenant = gmdate('c');
        $prochaineVerification = gmdate('c', time() + 7 * 24 * 3600);
        $graceJusquA = gmdate('c', time() + 14 * 24 * 3600);

        $licence = $this->licenceRepository->trouverLicenceParCleEtModule($cleLicence, $module);

        if ($licence === null) {
            return [
                'ok' => true,
                'module' => $module,
                'licence_key' => $cleLicence,
                'status' => 'invalide',
                'primary_domain' => '',
                'test_domains' => [],
                'request_domain' => $domaineDemande,
                'domain_match' => false,
                'checked_at' => $maintenant,
                'next_check_at' => $prochaineVerification,
                'grace_until' => $graceJusquA,
                'max_version' => '',
                'message' => 'Licence introuvable.',
                'signature_method' => 'none',
                'signature' => '',
            ];
        }

        $idLicence = (int)($licence['id_licence'] ?? 0);
        $statutCentral = trim((string)($licence['statut'] ?? 'invalide'));
        $domainePrincipal = $this->normaliserDomaine((string)($licence['domaine_principal'] ?? ''));
        $domainesTest = array_map(
            fn(string $domaine): string => $this->normaliserDomaine($domaine),
            $this->licenceRepository->obtenirDomainesTestActifs($idLicence)
        );
        $domainesTest = array_values(array_filter($domainesTest, static fn(string $domaine): bool => $domaine !== ''));

        $controleDomaineActif = ($domainePrincipal !== '' || !empty($domainesTest));
        $domaineAutorise = false;

        if (!$controleDomaineActif) {
            $domaineAutorise = true;
        } elseif ($domaineDemande !== '') {
            $domaineAutorise = ($domainePrincipal !== '' && $domaineDemande === $domainePrincipal)
                || in_array($domaineDemande, $domainesTest, true);
        }

        $statutRetour = $statutCentral;
        $message = 'Licence ' . $statutCentral . '.';

        if (in_array($statutCentral, ['suspendue', 'revoquee', 'expiree', 'invalide'], true)) {
            $message = 'Licence ' . $statutCentral . '.';
        } elseif (!$domaineAutorise) {
            $statutRetour = 'invalide';
            if ($controleDomaineActif && $domaineDemande === '') {
                $message = 'Domaine absent dans la requête.';
            } else {
                $message = 'Domaine non autorisé pour cette licence.';
            }
        } else {
            $statutRetour = 'active';
            $message = 'Licence active.';
        }

        return [
            'ok' => true,
            'module' => $module,
            'licence_key' => $cleLicence,
            'status' => $statutRetour,
            'primary_domain' => $domainePrincipal,
            'test_domains' => $domainesTest,
            'request_domain' => $domaineDemande,
            'domain_match' => $domaineAutorise,
            'checked_at' => $maintenant,
            'next_check_at' => $prochaineVerification,
            'grace_until' => $graceJusquA,
            'max_version' => (string)($licence['version_max_autorisee'] ?? ''),
            'message' => $message,
            'signature_method' => 'none',
            'signature' => '',
        ];
    }

    private function extraireDomainesTest(mixed $valeur): array
    {
        $texte = trim((string)$valeur);
        if ($texte === '') {
            return [];
        }

        $elements = preg_split('/[\r\n,;]+/', $texte) ?: [];
        $domaines = [];

        foreach ($elements as $element) {
            $domaine = $this->normaliserDomaine((string)$element);
            if ($domaine !== '') {
                $domaines[] = $domaine;
            }
        }

        $domaines = array_values(array_unique($domaines));

        return $domaines;
    }

    private function normaliserDateHeureNullable(mixed $valeur): ?string
    {
        $texte = trim((string)$valeur);
        if ($texte === '') {
            return null;
        }

        try {
            return (new \DateTime($texte))->format('Y-m-d H:i:s');
        } catch (\Throwable $e) {
            throw new InvalidArgumentException('Le format de date/heure fourni est invalide.');
        }
    }

    private function genererCleLicence(string $codeModule): string
    {
        $prefixe = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $codeModule));
        $prefixe = substr($prefixe !== '' ? $prefixe : 'LICENCE', 0, 8);

        $segments = [];
        for ($i = 0; $i < 4; $i++) {
            $segments[] = strtoupper(bin2hex(random_bytes(2)));
        }

        return $prefixe . '-' . implode('-', $segments);
    }

    private function normaliserDomaine(string $domaine): string
    {
        $domaine = trim(mb_strtolower($domaine));
        if ($domaine === '') {
            return '';
        }

        $domaine = preg_replace('#^https?://#i', '', $domaine);
        $domaine = preg_replace('#/.*$#', '', $domaine);
        $domaine = preg_replace('#:\d+$#', '', $domaine);

        return trim((string)$domaine, " \t\n\r\0\x0B/");
    }
}
