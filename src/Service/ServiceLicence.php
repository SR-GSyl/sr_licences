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

    public function obtenirLicencePourAdmin(int $idLicence): array
    {
        if ($idLicence <= 0) {
            throw new InvalidArgumentException('Identifiant de licence invalide.');
        }

        $licence = $this->licenceRepository->trouverLicenceParId($idLicence);

        if ($licence === null) {
            throw new InvalidArgumentException('Licence introuvable.');
        }

        $domaines = $this->licenceRepository->obtenirDomainesTestActifs($idLicence);
        $licence['domaines_test_actifs'] = $domaines;
        $licence['domaines_test_actifs_texte'] = implode(', ', $domaines);

        return $licence;
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
        $dateExpiration = null;
        $graceJusquA = null;

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

        if ($typeLicence === 'abonnement') {
            [$dateExpiration, $graceJusquA] = $this->preparerDatesAbonnementDepuisFormulaire($donnees, null, true);
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

    public function modifierLicence(int $idLicence, array $donnees): array
    {
        if ($idLicence <= 0) {
            throw new InvalidArgumentException('Identifiant de licence invalide.');
        }

        $licenceCourante = $this->licenceRepository->trouverLicenceParId($idLicence);
        if ($licenceCourante === null) {
            throw new InvalidArgumentException('Licence introuvable.');
        }

        $typeLicence = trim((string)($donnees['type_licence'] ?? 'perpetuelle'));
        $nomClient = trim((string)($donnees['nom_client'] ?? ''));
        $emailClient = trim((string)($donnees['email_client'] ?? ''));
        $domainePrincipal = $this->normaliserDomaine((string)($donnees['domaine_principal'] ?? ''));
        $versionMax = trim((string)($donnees['version_max_autorisee'] ?? ''));
        $commentaire = trim((string)($donnees['commentaire_interne'] ?? ''));
        $domainesTest = $this->extraireDomainesTest($donnees['domaines_test'] ?? '');
        $dateExpiration = null;
        $graceJusquA = null;

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

        if ($typeLicence === 'abonnement') {
            [$dateExpiration, $graceJusquA] = $this->preparerDatesAbonnementDepuisFormulaire($donnees, $licenceCourante, false);
        }

        $this->licenceRepository->mettreAJourLicence($idLicence, [
            'type_licence' => $typeLicence,
            'nom_client' => $nomClient,
            'email_client' => $emailClient,
            'domaine_principal' => $domainePrincipal,
            'version_max_autorisee' => $versionMax,
            'date_expiration' => $dateExpiration,
            'grace_jusqu_a' => $graceJusquA,
            'commentaire_interne' => $commentaire,
        ]);

        $this->licenceRepository->remplacerDomainesTestLicence($idLicence, $domainesTest);

        return $this->obtenirLicencePourAdmin($idLicence);
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

        $licence = $this->licenceRepository->trouverLicenceParId($idLicence);
        if ($licence === null) {
            throw new InvalidArgumentException('Licence introuvable.');
        }

        if ($actionStatut === 'reactiver' && (string)($licence['type_licence'] ?? '') === 'abonnement') {
            throw new InvalidArgumentException('La réactivation d’une licence abonnement doit passer par une période de validité.');
        }

        $nouveauStatut = $correspondance[$actionStatut];
        $this->licenceRepository->mettreAJourStatutLicence($idLicence, $nouveauStatut);

        return [
            'id_licence' => $idLicence,
            'statut' => $nouveauStatut,
        ];
    }

    public function reactiverLicencesAvecPeriode(array $idsLicence, array $donnees): array
    {
        $idsLicence = array_values(array_unique(array_map('intval', $idsLicence)));
        $idsLicence = array_values(array_filter($idsLicence, static fn(int $id): bool => $id > 0));

        if (empty($idsLicence)) {
            throw new InvalidArgumentException('Aucune licence sélectionnée pour la réactivation.');
        }

        $resultats = [];

        foreach ($idsLicence as $idLicence) {
            $licence = $this->licenceRepository->trouverLicenceParId($idLicence);
            if ($licence === null) {
                throw new InvalidArgumentException('Licence introuvable : #' . $idLicence . '.');
            }

            $typeLicence = (string)($licence['type_licence'] ?? 'perpetuelle');

            if ($typeLicence === 'abonnement') {
                [$dateExpiration, $graceJusquA] = $this->preparerDatesAbonnementDepuisFormulaire($donnees, null, true);

                $this->licenceRepository->mettreAJourLicence($idLicence, [
                    'type_licence' => $typeLicence,
                    'nom_client' => (string)($licence['nom_client'] ?? ''),
                    'email_client' => (string)($licence['email_client'] ?? ''),
                    'domaine_principal' => (string)($licence['domaine_principal'] ?? ''),
                    'version_max_autorisee' => (string)($licence['version_max_autorisee'] ?? ''),
                    'date_expiration' => $dateExpiration,
                    'grace_jusqu_a' => $graceJusquA,
                    'commentaire_interne' => (string)($licence['commentaire_interne'] ?? ''),
                ]);
            } else {
                $this->licenceRepository->mettreAJourLicence($idLicence, [
                    'type_licence' => $typeLicence,
                    'nom_client' => (string)($licence['nom_client'] ?? ''),
                    'email_client' => (string)($licence['email_client'] ?? ''),
                    'domaine_principal' => (string)($licence['domaine_principal'] ?? ''),
                    'version_max_autorisee' => (string)($licence['version_max_autorisee'] ?? ''),
                    'date_expiration' => null,
                    'grace_jusqu_a' => null,
                    'commentaire_interne' => (string)($licence['commentaire_interne'] ?? ''),
                ]);
            }

            $this->licenceRepository->mettreAJourStatutLicence($idLicence, 'active');

            $resultats[] = [
                'id_licence' => $idLicence,
                'type_licence' => $typeLicence,
                'statut' => 'active',
            ];
        }

        return $resultats;
    }

    public function verifierLicencePourApi(array $donnees): array
    {
        $module = trim((string)($donnees['module'] ?? $donnees['code_module'] ?? ''));
        $versionDemandee = trim((string)($donnees['version'] ?? ''));
        $cleLicence = trim((string)($donnees['licence_key'] ?? $donnees['cle_licence'] ?? ''));
        $domaineDemande = $this->normaliserDomaine((string)($donnees['domain'] ?? $donnees['domaine'] ?? ''));

        if ($module === '') {
            throw new InvalidArgumentException('Le paramètre module est obligatoire.');
        }

        if ($cleLicence === '') {
            throw new InvalidArgumentException('Le paramètre licence_key est obligatoire.');
        }

        $maintenantDt = new \DateTimeImmutable('now');
        $maintenant = $maintenantDt->format(DATE_ATOM);
        $prochaineVerification = $maintenantDt->modify('+1 day')->format(DATE_ATOM);

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
                'grace_until' => '',
                'max_version' => '',
                'message' => 'Licence introuvable.',
                'signature_method' => 'none',
                'signature' => '',
            ];
        }

        $idLicence = (int)($licence['id_licence'] ?? 0);
        $statutCentral = trim((string)($licence['statut'] ?? 'invalide'));
        $typeLicence = trim((string)($licence['type_licence'] ?? 'perpetuelle'));
        $domainePrincipal = $this->normaliserDomaine((string)($licence['domaine_principal'] ?? ''));
        $versionMaxAutorisee = trim((string)($licence['version_max_autorisee'] ?? ''));

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

        $dateExpirationTexte = trim((string)($licence['date_expiration'] ?? ''));
        $graceJusquATexte = trim((string)($licence['grace_jusqu_a'] ?? ''));

        $dateExpiration = null;
        if ($dateExpirationTexte !== '') {
            try {
                $dateExpiration = new \DateTimeImmutable($dateExpirationTexte);
            } catch (\Throwable $e) {
                $dateExpiration = null;
            }
        }

        $graceJusquA = null;
        if ($graceJusquATexte !== '') {
            try {
                $graceJusquA = new \DateTimeImmutable($graceJusquATexte);
            } catch (\Throwable $e) {
                $graceJusquA = null;
            }
        }

        $graceUntilRetour = $graceJusquA instanceof \DateTimeImmutable ? $graceJusquA->format(DATE_ATOM) : '';
        $statutRetour = $statutCentral;
        $message = 'Licence ' . $statutCentral . '.';

        if (in_array($statutCentral, ['suspendue', 'revoquee', 'expiree', 'invalide'], true)) {
            $statutRetour = $statutCentral;
            $message = 'Licence ' . $statutCentral . '.';
        } elseif (!$domaineAutorise) {
            $statutRetour = 'invalide';
            if ($controleDomaineActif && $domaineDemande === '') {
                $message = 'Domaine absent dans la requête.';
            } else {
                $message = 'Domaine non autorisé pour cette licence.';
            }
        } elseif (!$this->versionModuleEstAutorisee($versionDemandee, $versionMaxAutorisee)) {
            $statutRetour = 'invalide';
            if ($versionMaxAutorisee === '') {
                $message = 'Version du module non transmise.';
            } elseif ($versionDemandee === '') {
                $message = 'Version du module absente alors qu’une version max autorisée est définie.';
            } else {
                $message = 'Version du module non autorisée. Version demandée : ' . $versionDemandee . '. Version max autorisée : ' . $versionMaxAutorisee . '.';
            }
        } elseif ($typeLicence === 'abonnement' && $dateExpiration instanceof \DateTimeImmutable) {
            if ($maintenantDt <= $dateExpiration) {
                $statutRetour = 'active';
                $message = 'Licence active.';
            } elseif ($graceJusquA instanceof \DateTimeImmutable && $maintenantDt <= $graceJusquA) {
                $statutRetour = 'grace';
                $message = 'Licence en grace.';
            } else {
                $statutRetour = 'expiree';
                $message = 'Licence expiree.';
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
            'grace_until' => $graceUntilRetour,
            'max_version' => $versionMaxAutorisee,
            'message' => $message,
            'signature_method' => 'none',
            'signature' => '',
        ];
    }

    private function versionModuleEstAutorisee(string $versionDemandee, string $versionMaxAutorisee): bool
    {
        $versionDemandee = $this->normaliserVersionLicence($versionDemandee);
        $versionMaxAutorisee = trim($versionMaxAutorisee);

        if ($versionMaxAutorisee === '') {
            return true;
        }

        if ($versionDemandee === '') {
            return false;
        }

        $motifs = preg_split('/[\r\n,;|]+/', $versionMaxAutorisee) ?: [];
        foreach ($motifs as $motif) {
            $motif = $this->normaliserVersionLicence($motif);
            if ($motif === '') {
                continue;
            }

            if ($this->versionCorrespondAuMotif($versionDemandee, $motif)) {
                return true;
            }
        }

        return false;
    }

    private function versionCorrespondAuMotif(string $versionDemandee, string $motif): bool
    {
        if (strpos($motif, '*') === false) {
            return $versionDemandee === $motif;
        }

        $regex = preg_quote($motif, '/');
        $regex = str_replace('\*', '.*', $regex);

        return (bool)preg_match('/^' . $regex . '$/i', $versionDemandee);
    }

    private function normaliserVersionLicence(string $version): string
    {
        $version = trim(mb_strtolower($version));
        if ($version === '') {
            return '';
        }

        if (str_starts_with($version, 'v')) {
            $version = substr($version, 1);
        }

        return trim($version);
    }

    private function preparerDatesAbonnementDepuisFormulaire(array $donnees, ?array $licenceExistante = null, bool $creation = false): array
    {
        $validiteValeur = $this->normaliserValeurPeriodeNullable($donnees['validite_valeur'] ?? null);
        $validiteUnite = $this->normaliserUnitePeriode((string)($donnees['validite_unite'] ?? 'mois'));
        $graceValeur = $this->normaliserValeurPeriodeNullable($donnees['grace_valeur'] ?? null, true);
        $graceUnite = $this->normaliserUnitePeriode((string)($donnees['grace_unite'] ?? 'jours'));

        $dateExpirationManuelle = $this->normaliserDateHeureNullable($donnees['date_expiration'] ?? null);
        $graceManuelle = $this->normaliserDateHeureNullable($donnees['grace_jusqu_a'] ?? null);

        if ($validiteValeur !== null) {
            return $this->calculerDatesDepuisPeriodes($validiteValeur, $validiteUnite, $graceValeur, $graceUnite);
        }

        if ($dateExpirationManuelle !== null || $graceManuelle !== null) {
            if ($dateExpirationManuelle === null) {
                throw new InvalidArgumentException('La date d’expiration manuelle est obligatoire si une date de grâce manuelle est fournie.');
            }

            return [$dateExpirationManuelle, $graceManuelle];
        }

        if ($licenceExistante !== null) {
            $dateExpirationExistante = $this->normaliserDateHeureNullable($licenceExistante['date_expiration'] ?? null);
            $graceExistante = $this->normaliserDateHeureNullable($licenceExistante['grace_jusqu_a'] ?? null);

            if ($dateExpirationExistante === null) {
                throw new InvalidArgumentException('Une durée de validité ou une date d’expiration est obligatoire pour une licence abonnement.');
            }

            return [$dateExpirationExistante, $graceExistante];
        }

        if ($creation) {
            throw new InvalidArgumentException('Une durée de validité ou une date d’expiration est obligatoire pour une licence abonnement.');
        }

        throw new InvalidArgumentException('Paramètres d’abonnement insuffisants.');
    }

    private function calculerDatesDepuisPeriodes(int $validiteValeur, string $validiteUnite, ?int $graceValeur, string $graceUnite): array
    {
        $base = new \DateTimeImmutable('now');
        $dateExpiration = $this->ajouterPeriode($base, $validiteValeur, $validiteUnite);
        $graceJusquA = null;

        if ($graceValeur !== null && $graceValeur > 0) {
            $graceJusquA = $this->ajouterPeriode($dateExpiration, $graceValeur, $graceUnite);
        }

        return [
            $dateExpiration->format('Y-m-d H:i:s'),
            $graceJusquA?->format('Y-m-d H:i:s'),
        ];
    }

    private function ajouterPeriode(\DateTimeImmutable $base, int $valeur, string $unite): \DateTimeImmutable
    {
        $mapping = [
            'jours' => 'days',
            'semaines' => 'weeks',
            'mois' => 'months',
            'annees' => 'years',
        ];

        if (!isset($mapping[$unite])) {
            throw new InvalidArgumentException('Unité de période invalide.');
        }

        return $base->modify('+' . $valeur . ' ' . $mapping[$unite]);
    }

    private function normaliserValeurPeriodeNullable(mixed $valeur, bool $autoriserZero = false): ?int
    {
        $texte = trim((string)$valeur);
        if ($texte === '') {
            return null;
        }

        if (!preg_match('/^\d+$/', $texte)) {
            throw new InvalidArgumentException('La valeur de période fournie est invalide.');
        }

        $entier = (int)$texte;

        if ($autoriserZero) {
            if ($entier < 0) {
                throw new InvalidArgumentException('La valeur de période fournie est invalide.');
            }
        } else {
            if ($entier <= 0) {
                throw new InvalidArgumentException('La valeur de période fournie est invalide.');
            }
        }

        return $entier;
    }

    private function normaliserUnitePeriode(string $unite): string
    {
        $unite = trim(mb_strtolower($unite));
        if (!in_array($unite, ['jours', 'semaines', 'mois', 'annees'], true)) {
            throw new InvalidArgumentException('L’unité de période fournie est invalide.');
        }

        return $unite;
    }

    public function enregistrerObservationVerification(array $donnees, array $resultat): void
    {
        $dossier = dirname(__DIR__, 2) . '/var/log';
        if (!is_dir($dossier)) {
            @mkdir($dossier, 0755, true);
        }

        $fichier = $dossier . '/licence_heartbeats.json';
        $etat = [];

        if (is_file($fichier)) {
            $json = @file_get_contents($fichier);
            if (is_string($json) && $json !== '') {
                $decode = json_decode($json, true);
                if (is_array($decode)) {
                    $etat = $decode;
                }
            }
        }

        $module = trim((string)($donnees['module'] ?? $donnees['code_module'] ?? ''));
        $licenceKey = trim((string)($donnees['licence_key'] ?? $donnees['cle_licence'] ?? ''));
        $version = trim((string)($donnees['version'] ?? ''));
        $requestDomain = $this->normaliserDomaine((string)($donnees['domain'] ?? $donnees['domaine'] ?? ''));
        $index = sha1($module . '|' . $licenceKey . '|' . $requestDomain);

        $etat[$index] = [
            'index' => $index,
            'module' => $module,
            'licence_key' => $licenceKey,
            'version' => $version,
            'request_domain' => $requestDomain,
            'status' => (string)($resultat['status'] ?? ''),
            'checked_at' => (string)($resultat['checked_at'] ?? ''),
            'next_check_at' => (string)($resultat['next_check_at'] ?? ''),
            'grace_until' => (string)($resultat['grace_until'] ?? ''),
            'max_version' => (string)($resultat['max_version'] ?? ''),
            'last_seen_server' => date('c'),
        ];

        @file_put_contents(
            $fichier,
            json_encode($etat, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            LOCK_EX
        );
    }

    public function obtenirAlertesSilenceVerification(int $margeMinutes = 120): array
    {
        $fichier = dirname(__DIR__, 2) . '/var/log/licence_heartbeats.json';
        if (!is_file($fichier)) {
            return [];
        }

        $json = @file_get_contents($fichier);
        if (!is_string($json) || trim($json) === '') {
            return [];
        }

        $etat = json_decode($json, true);
        if (!is_array($etat)) {
            return [];
        }

        $maintenant = time();
        $alertes = [];

        foreach ($etat as $entree) {
            if (!is_array($entree)) {
                continue;
            }

            $nextCheckAt = trim((string)($entree['next_check_at'] ?? ''));
            $timestampNext = $nextCheckAt !== '' ? strtotime($nextCheckAt) : false;
            if ($timestampNext === false) {
                continue;
            }

            $retardSecondes = $maintenant - ($timestampNext + ($margeMinutes * 60));
            if ($retardSecondes <= 0) {
                continue;
            }

            $entree['retard_minutes'] = (int)floor($retardSecondes / 60);
            $alertes[] = $entree;
        }

        usort($alertes, static function (array $a, array $b): int {
            return (int)($b['retard_minutes'] ?? 0) <=> (int)($a['retard_minutes'] ?? 0);
        });

        return $alertes;
    }

    public function journaliserAlertesSilenceVerification(array $alertes): void
    {
        $dossier = dirname(__DIR__, 2) . '/var/log';
        if (!is_dir($dossier)) {
            @mkdir($dossier, 0755, true);
        }

        $fichier = $dossier . '/licence_surveillance.log';
        $memoFile = $dossier . '/licence_silences_signales.json';
        $memo = [];

        if (is_file($memoFile)) {
            $json = @file_get_contents($memoFile);
            if (is_string($json) && $json !== '') {
                $decode = json_decode($json, true);
                if (is_array($decode)) {
                    $memo = $decode;
                }
            }
        }

        $actifs = [];

        foreach ($alertes as $alerte) {
            if (!is_array($alerte)) {
                continue;
            }

            $index = (string)($alerte['index'] ?? '');
            if ($index === '') {
                continue;
            }

            $signature = (string)($alerte['next_check_at'] ?? '') . '|' . (string)($alerte['status'] ?? '');
            $actifs[$index] = $signature;

            if (($memo[$index] ?? '') === $signature) {
                continue;
            }

            $ligne = sprintf(
                "[%s] [WARN] Vérification silencieuse détectée pour %s / %s / %s (retard : %d min)
",
                date('Y-m-d H:i:s'),
                (string)($alerte['module'] ?? ''),
                (string)($alerte['licence_key'] ?? ''),
                (string)($alerte['request_domain'] ?? ''),
                (int)($alerte['retard_minutes'] ?? 0)
            );
            @file_put_contents($fichier, $ligne, FILE_APPEND);
        }

        @file_put_contents(
            $memoFile,
            json_encode($actifs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            LOCK_EX
        );
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
