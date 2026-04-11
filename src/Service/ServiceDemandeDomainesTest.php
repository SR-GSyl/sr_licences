<?php
declare(strict_types=1);

namespace SrLicences\Service;

use InvalidArgumentException;
use SrLicences\Repository\DemandeDomainesTestRepository;
use SrLicences\Repository\LicenceRepository;

final class ServiceDemandeDomainesTest
{
    private const LIMITE_DOMAINES_TEST = 2;

    public function __construct(
        private DemandeDomainesTestRepository $demandeDomainesTestRepository,
        private LicenceRepository $licenceRepository
    ) {
    }

    public function obtenirStatistiquesTableauDeBord(): array
    {
        return $this->demandeDomainesTestRepository->obtenirStatistiquesParStatut();
    }

    public function obtenirDemandesTableauDeBord(int $limit = 100): array
    {
        return $this->demandeDomainesTestRepository->listerDemandes($limit);
    }

    public function obtenirDemandePourAdmin(int $idDemandeDomainesTest): array
    {
        if ($idDemandeDomainesTest <= 0) {
            throw new InvalidArgumentException('Identifiant de demande invalide.');
        }

        $demande = $this->demandeDomainesTestRepository->trouverDemandeParId($idDemandeDomainesTest);
        if ($demande === null) {
            throw new InvalidArgumentException('Demande de mise à jour des domaines de test introuvable.');
        }

        return $demande;
    }

    public function demanderMiseAJourDomainesTest(array $donnees): array
    {
        $codeModule = trim((string)($donnees['code_module'] ?? $donnees['module'] ?? ''));
        $cleLicence = trim((string)($donnees['cle_licence'] ?? $donnees['licence_key'] ?? ''));
        $domainePrincipal = $this->normaliserDomaine((string)($donnees['domaine_principal'] ?? $donnees['domain'] ?? $donnees['domaine'] ?? ''));
        $domainesDemandes = $this->extraireDomainesTest($donnees['domaines_test_demandes'] ?? $donnees['domaines_test'] ?? '');
        $motif = trim((string)($donnees['motif'] ?? ''));

        if ($codeModule === '') {
            throw new InvalidArgumentException('Le code module est obligatoire.');
        }

        if ($cleLicence === '') {
            throw new InvalidArgumentException('La clé de licence est obligatoire.');
        }

        if ($domainePrincipal === '') {
            throw new InvalidArgumentException('Le domaine principal est obligatoire.');
        }

        $licence = $this->licenceRepository->trouverLicenceParCleEtModule($cleLicence, $codeModule);
        if ($licence === null) {
            return [
                'ok' => false,
                'code' => 'license_not_found',
                'message' => 'Licence introuvable.',
                'checked_at' => date('c'),
            ];
        }

        $idLicence = (int)($licence['id_licence'] ?? 0);
        $domainePrincipalLicence = $this->normaliserDomaine((string)($licence['domaine_principal'] ?? ''));
        if ($idLicence <= 0 || $domainePrincipalLicence === '') {
            throw new InvalidArgumentException('Licence invalide.');
        }

        if ($domainePrincipal !== $domainePrincipalLicence) {
            return [
                'ok' => false,
                'code' => 'license_domain_not_authorized',
                'message' => 'Le domaine principal ne correspond pas à cette licence.',
                'id_licence' => $idLicence,
                'checked_at' => date('c'),
            ];
        }

        if (!$this->licencePeutModifierDomainesTest($licence)) {
            return [
                'ok' => false,
                'code' => 'license_not_active_for_test_domains',
                'message' => 'La licence doit être active ou en période de grâce pour modifier les domaines de test.',
                'id_licence' => $idLicence,
                'checked_at' => date('c'),
            ];
        }

        $domainesActuels = $this->licenceRepository->obtenirDomainesTestActifs($idLicence);

        $domainesActuels = array_values(array_filter(
            $this->normaliserListeDomaines($domainesActuels),
            fn(string $domaine): bool => $domaine !== $domainePrincipalLicence
        ));

        $domainesDemandes = array_values(array_filter(
            $this->normaliserListeDomaines($domainesDemandes),
            fn(string $domaine): bool => $domaine !== $domainePrincipalLicence
        ));

        if ($this->listesDomainesSontIdentiques($domainesActuels, $domainesDemandes)) {
            return [
                'ok' => false,
                'code' => 'test_domains_no_change_requested',
                'message' => 'Aucune modification des domaines de test n’a été demandée.',
                'id_licence' => $idLicence,
                'checked_at' => date('c'),
            ];
        }

        if (count($domainesDemandes) > self::LIMITE_DOMAINES_TEST) {
            return [
                'ok' => false,
                'code' => 'test_domains_limit_exceeded',
                'message' => 'Le nombre maximal de domaines de test autorisés est dépassé.',
                'id_licence' => $idLicence,
                'checked_at' => date('c'),
            ];
        }

        $demandeEnAttente = $this->demandeDomainesTestRepository->trouverDemandeEnAttenteParLicence($idLicence);
        if ($demandeEnAttente !== null) {
            return [
                'ok' => false,
                'code' => 'test_domains_update_request_pending',
                'message' => 'Une demande de mise à jour des domaines de test est déjà en attente.',
                'id_demande_domaines_test' => (int)($demandeEnAttente['id_demande_domaines_test'] ?? 0),
                'secret_suivi' => (string)($demandeEnAttente['secret_suivi'] ?? ''),
                'statut' => 'en_attente',
                'id_licence' => $idLicence,
                'checked_at' => date('c'),
            ];
        }

        $secretSuivi = bin2hex(random_bytes(32));

        $idDemande = $this->demandeDomainesTestRepository->insererDemande([
            'id_licence' => $idLicence,
            'cle_licence' => $cleLicence,
            'code_module' => $codeModule,
            'domaine_principal' => $domainePrincipalLicence,
            'domaines_test_actuels' => implode("\n", $domainesActuels),
            'domaines_test_demandes' => implode("\n", $domainesDemandes),
            'motif' => $motif,
            'secret_suivi' => $secretSuivi,
            'statut' => 'en_attente',
            'note_interne' => null,
        ]);

        return [
            'ok' => true,
            'code' => 'test_domains_update_request_registered',
            'message' => 'Demande de mise à jour des domaines de test enregistrée.',
            'id_demande_domaines_test' => $idDemande,
            'secret_suivi' => $secretSuivi,
            'statut' => 'en_attente',
            'id_licence' => $idLicence,
            'checked_at' => date('c'),
        ];
    }

    public function verifierMiseAJourDomainesTest(array $donnees): array
    {
        $idDemande = (int)($donnees['id_demande_domaines_test'] ?? $donnees['demande_id'] ?? 0);
        $secretSuivi = trim((string)($donnees['secret_suivi'] ?? ''));

        if ($idDemande <= 0) {
            throw new InvalidArgumentException('L’identifiant de demande est obligatoire.');
        }

        if ($secretSuivi === '') {
            throw new InvalidArgumentException('Le secret de suivi est obligatoire.');
        }

        $demande = $this->demandeDomainesTestRepository->trouverDemandeParIdEtSecret($idDemande, $secretSuivi);
        if ($demande === null) {
            throw new InvalidArgumentException('Demande de mise à jour des domaines de test introuvable.');
        }

        $statut = (string)($demande['statut'] ?? 'en_attente');

        if ($statut === 'en_attente') {
            return [
                'ok' => true,
                'code' => 'test_domains_update_request_pending',
                'message' => 'Demande toujours en attente.',
                'id_demande_domaines_test' => $idDemande,
                'statut' => 'en_attente',
                'checked_at' => date('c'),
            ];
        }

        if ($statut === 'refusee') {
            return [
                'ok' => true,
                'code' => 'test_domains_update_request_refused',
                'message' => 'Demande refusée.',
                'id_demande_domaines_test' => $idDemande,
                'statut' => 'refusee',
                'checked_at' => date('c'),
            ];
        }

        if (!in_array($statut, ['validee', 'terminee'], true)) {
            throw new InvalidArgumentException('Le statut de la demande est invalide pour une vérification.');
        }

        $idLicence = (int)($demande['id_licence'] ?? 0);
        if ($idLicence <= 0) {
            throw new InvalidArgumentException('La demande validée n’est liée à aucune licence.');
        }

        $licence = $this->licenceRepository->trouverLicenceParId($idLicence);
        if ($licence === null) {
            throw new InvalidArgumentException('La licence liée à cette demande est introuvable.');
        }

        $domainesActifs = $this->licenceRepository->obtenirDomainesTestActifs($idLicence);

        return [
            'ok' => true,
            'code' => 'test_domains_update_applied',
            'message' => 'Mise à jour des domaines de test appliquée.',
            'id_demande_domaines_test' => $idDemande,
            'id_licence' => $idLicence,
            'statut' => $statut,
            'domaines_test' => $domainesActifs,
            'checked_at' => date('c'),
        ];
    }

    public function validerDemandeDomainesTest(int $idDemandeDomainesTest, string $noteInterne = ''): array
    {
        if ($idDemandeDomainesTest <= 0) {
            throw new InvalidArgumentException('Identifiant de demande invalide.');
        }

        $demande = $this->demandeDomainesTestRepository->trouverDemandeParId($idDemandeDomainesTest);
        if ($demande === null) {
            throw new InvalidArgumentException('Demande de mise à jour des domaines de test introuvable.');
        }

        $statutActuel = (string)($demande['statut'] ?? '');
        if (!in_array($statutActuel, ['en_attente', 'refusee'], true)) {
            throw new InvalidArgumentException('Cette demande ne peut plus être validée dans son état actuel.');
        }

        $idLicence = (int)($demande['id_licence'] ?? 0);
        if ($idLicence <= 0) {
            throw new InvalidArgumentException('Licence liée invalide.');
        }

        $licence = $this->licenceRepository->trouverLicenceParId($idLicence);
        if ($licence === null) {
            throw new InvalidArgumentException('Licence introuvable.');
        }

        $domainesDemandes = $this->extraireDomainesTest((string)($demande['domaines_test_demandes'] ?? ''));
        $domainePrincipalLicence = $this->normaliserDomaine((string)($licence['domaine_principal'] ?? ''));

        $domainesDemandes = array_values(array_filter(
            $this->normaliserListeDomaines($domainesDemandes),
            fn(string $domaine): bool => $domaine !== $domainePrincipalLicence
        ));

        if (count($domainesDemandes) > self::LIMITE_DOMAINES_TEST) {
            throw new InvalidArgumentException('Le nombre maximal de domaines de test autorisés est dépassé.');
        }

        $this->licenceRepository->remplacerDomainesTestLicence($idLicence, $domainesDemandes);
        $this->demandeDomainesTestRepository->marquerDemandeValidee(
            $idDemandeDomainesTest,
            trim($noteInterne) !== '' ? trim($noteInterne) : null
        );
        $this->demandeDomainesTestRepository->marquerDemandeTerminee($idDemandeDomainesTest);

        return [
            'id_demande_domaines_test' => $idDemandeDomainesTest,
            'id_licence' => $idLicence,
            'statut' => 'terminee',
            'domaines_test' => $domainesDemandes,
        ];
    }

    public function refuserDemandeDomainesTest(int $idDemandeDomainesTest, string $noteInterne = ''): array
    {
        if ($idDemandeDomainesTest <= 0) {
            throw new InvalidArgumentException('Identifiant de demande invalide.');
        }

        $demande = $this->demandeDomainesTestRepository->trouverDemandeParId($idDemandeDomainesTest);
        if ($demande === null) {
            throw new InvalidArgumentException('Demande de mise à jour des domaines de test introuvable.');
        }

        $statutActuel = (string)($demande['statut'] ?? '');
        if (!in_array($statutActuel, ['en_attente', 'validee'], true)) {
            throw new InvalidArgumentException('Cette demande ne peut plus être refusée dans son état actuel.');
        }

        $this->demandeDomainesTestRepository->marquerDemandeRefusee(
            $idDemandeDomainesTest,
            trim($noteInterne) !== '' ? trim($noteInterne) : null
        );

        return [
            'id_demande_domaines_test' => $idDemandeDomainesTest,
            'statut' => 'refusee',
        ];
    }

    private function licencePeutModifierDomainesTest(array $licence): bool
    {
        $statut = trim((string)($licence['statut'] ?? ''));
        if (in_array($statut, ['active', 'grace'], true)) {
            return true;
        }

        if ($statut !== 'expiree') {
            return false;
        }

        $graceJusquA = trim((string)($licence['grace_jusqu_a'] ?? ''));
        if ($graceJusquA === '') {
            return false;
        }

        try {
            $maintenant = new \DateTimeImmutable('now');
            $limite = new \DateTimeImmutable($graceJusquA);
            return $limite >= $maintenant;
        } catch (\Throwable) {
            return false;
        }
    }

    private function listesDomainesSontIdentiques(array $gauche, array $droite): bool
    {
        $gauche = $this->normaliserListeDomaines($gauche);
        $droite = $this->normaliserListeDomaines($droite);

        sort($gauche);
        sort($droite);

        return $gauche === $droite;
    }

    private function normaliserListeDomaines(array $domaines): array
    {
        $resultat = [];

        foreach ($domaines as $domaine) {
            $normalise = $this->normaliserDomaine((string)$domaine);
            if ($normalise !== '') {
                $resultat[] = $normalise;
            }
        }

        $resultat = array_values(array_unique($resultat));
        sort($resultat);

        return $resultat;
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

        return array_values(array_unique($domaines));
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
