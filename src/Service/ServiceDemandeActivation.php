<?php
declare(strict_types=1);

namespace SrLicences\Service;

use InvalidArgumentException;
use SrLicences\Repository\DemandeActivationRepository;
use SrLicences\Repository\LicenceRepository;

final class ServiceDemandeActivation
{
    public function __construct(
        private DemandeActivationRepository $demandeActivationRepository,
        private LicenceRepository $licenceRepository
    ) {
    }

    public function obtenirStatistiquesTableauDeBord(): array
    {
        return $this->demandeActivationRepository->obtenirStatistiquesParStatut();
    }

    public function obtenirDemandesTableauDeBord(int $limit = 100): array
    {
        return $this->demandeActivationRepository->listerDemandesActivation($limit);
    }

    public function obtenirDemandePourAdmin(int $idDemandeActivation): array
    {
        if ($idDemandeActivation <= 0) {
            throw new InvalidArgumentException('Identifiant de demande invalide.');
        }

        $demande = $this->demandeActivationRepository->trouverDemandeParId($idDemandeActivation);
        if ($demande === null) {
            throw new InvalidArgumentException('Demande d’activation introuvable.');
        }

        return $demande;
    }

    public function demanderActivation(array $donnees): array
    {
        $codeModule = trim((string)($donnees['code_module'] ?? $donnees['module'] ?? ''));
        $versionModule = trim((string)($donnees['version_module'] ?? $donnees['version'] ?? ''));
        $nomClient = trim((string)($donnees['nom_client'] ?? ''));
        $emailClient = trim((string)($donnees['email_client'] ?? ''));
        $numeroCommande = trim((string)($donnees['numero_commande'] ?? ''));
        $domainePrincipal = $this->normaliserDomaine((string)($donnees['domaine_principal'] ?? $donnees['domain'] ?? $donnees['domaine'] ?? ''));
        $domainesTest = $this->extraireDomainesTest($donnees['domaines_test'] ?? '');

        if ($codeModule === '') {
            throw new InvalidArgumentException('Le code module est obligatoire.');
        }

        if ($nomClient === '') {
            throw new InvalidArgumentException('Le nom client est obligatoire.');
        }

        if ($emailClient === '' || filter_var($emailClient, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('L’adresse e-mail client est invalide.');
        }

        if ($numeroCommande === '') {
            throw new InvalidArgumentException('Le numéro de commande est obligatoire.');
        }

        if ($domainePrincipal === '') {
            throw new InvalidArgumentException('Le domaine principal est obligatoire.');
        }

        $domainesTest = array_values(array_filter(
            $domainesTest,
            fn(string $domaine): bool => $domaine !== $domainePrincipal
        ));

        $secretSuivi = bin2hex(random_bytes(32));

        $idDemandeActivation = $this->demandeActivationRepository->insererDemandeActivation([
            'code_module' => $codeModule,
            'version_module' => $versionModule,
            'nom_client' => $nomClient,
            'email_client' => $emailClient,
            'numero_commande' => $numeroCommande,
            'domaine_principal' => $domainePrincipal,
            'domaines_test' => implode("\n", $domainesTest),
            'secret_suivi' => $secretSuivi,
            'statut' => 'en_attente',
            'note_interne' => null,
        ]);

        return [
            'ok' => true,
            'message' => 'Demande d’activation enregistrée.',
            'id_demande_activation' => $idDemandeActivation,
            'secret_suivi' => $secretSuivi,
            'statut' => 'en_attente',
            'checked_at' => date('c'),
        ];
    }

    public function verifierActivation(array $donnees): array
    {
        $idDemandeActivation = (int)($donnees['id_demande_activation'] ?? $donnees['demande_id'] ?? 0);
        $secretSuivi = trim((string)($donnees['secret_suivi'] ?? ''));

        if ($idDemandeActivation <= 0) {
            throw new InvalidArgumentException('L’identifiant de demande est obligatoire.');
        }

        if ($secretSuivi === '') {
            throw new InvalidArgumentException('Le secret de suivi est obligatoire.');
        }

        $demande = $this->demandeActivationRepository->trouverDemandeParIdEtSecret($idDemandeActivation, $secretSuivi);
        if ($demande === null) {
            throw new InvalidArgumentException('Demande d’activation introuvable.');
        }

        $statut = (string)($demande['statut'] ?? 'en_attente');

        if ($statut === 'en_attente') {
            return [
                'ok' => true,
                'message' => 'Demande toujours en attente.',
                'id_demande_activation' => $idDemandeActivation,
                'statut' => 'en_attente',
                'checked_at' => date('c'),
            ];
        }

        if ($statut === 'refusee') {
            return [
                'ok' => true,
                'message' => 'Demande refusée.',
                'id_demande_activation' => $idDemandeActivation,
                'statut' => 'refusee',
                'checked_at' => date('c'),
            ];
        }

        if (!in_array($statut, ['validee', 'terminee'], true)) {
            throw new InvalidArgumentException('Le statut de la demande est invalide pour une récupération de clé.');
        }

        $idLicence = (int)($demande['id_licence'] ?? 0);
        if ($idLicence <= 0) {
            throw new InvalidArgumentException('La demande validée n’est liée à aucune licence.');
        }

        $licence = $this->licenceRepository->trouverLicenceParId($idLicence);
        if ($licence === null) {
            throw new InvalidArgumentException('La licence liée à cette demande est introuvable.');
        }

        return [
            'ok' => true,
            'message' => 'Activation disponible.',
            'id_demande_activation' => $idDemandeActivation,
            'statut' => 'validee',
            'statut_demande' => $statut,
            'licence_key' => (string)($licence['cle_licence'] ?? ''),
            'cle_licence' => (string)($licence['cle_licence'] ?? ''),
            'module' => (string)($licence['code_module'] ?? ''),
            'code_module' => (string)($licence['code_module'] ?? ''),
            'type_licence' => (string)($licence['type_licence'] ?? ''),
            'max_version' => (string)($licence['version_max_autorisee'] ?? ''),
            'version_max_autorisee' => (string)($licence['version_max_autorisee'] ?? ''),
            'checked_at' => date('c'),
        ];
    }

    public function validerDemandeActivation(int $idDemandeActivation, array $donneesDecision): array
    {
        if ($idDemandeActivation <= 0) {
            throw new InvalidArgumentException('Identifiant de demande invalide.');
        }

        $demande = $this->demandeActivationRepository->trouverDemandeParId($idDemandeActivation);
        if ($demande === null) {
            throw new InvalidArgumentException('Demande d’activation introuvable.');
        }

        $statutActuel = (string)($demande['statut'] ?? '');
        if (!in_array($statutActuel, ['en_attente', 'refusee'], true)) {
            throw new InvalidArgumentException('Cette demande ne peut plus être validée dans son état actuel.');
        }

        $typeLicence = trim((string)($donneesDecision['type_licence'] ?? 'perpetuelle'));
        $versionMax = trim((string)($donneesDecision['version_max_autorisee'] ?? ''));
        $noteInterne = trim((string)($donneesDecision['note_interne'] ?? ''));

        $serviceLicence = new ServiceLicence($this->licenceRepository);

        $commentaire = trim(implode("\n", array_filter([
            'Créée depuis la demande d’activation #' . $idDemandeActivation . '.',
            ((string)($demande['numero_commande'] ?? '') !== '') ? 'Commande : ' . (string)$demande['numero_commande'] : '',
            $noteInterne,
        ])));

        $resultatLicence = $serviceLicence->creerLicence([
            'code_module' => (string)($demande['code_module'] ?? ''),
            'statut' => 'active',
            'type_licence' => $typeLicence,
            'nom_client' => (string)($demande['nom_client'] ?? ''),
            'email_client' => (string)($demande['email_client'] ?? ''),
            'domaine_principal' => (string)($demande['domaine_principal'] ?? ''),
            'version_max_autorisee' => $versionMax !== '' ? $versionMax : (string)($demande['version_module'] ?? ''),
            'validite_valeur' => (string)($donneesDecision['validite_valeur'] ?? ''),
            'validite_unite' => (string)($donneesDecision['validite_unite'] ?? 'mois'),
            'grace_valeur' => (string)($donneesDecision['grace_valeur'] ?? ''),
            'grace_unite' => (string)($donneesDecision['grace_unite'] ?? 'jours'),
            'date_expiration' => (string)($donneesDecision['date_expiration'] ?? ''),
            'grace_jusqu_a' => (string)($donneesDecision['grace_jusqu_a'] ?? ''),
            'domaines_test' => (string)($demande['domaines_test'] ?? ''),
            'commentaire_interne' => $commentaire,
        ]);

        $idLicence = (int)($resultatLicence['id_licence'] ?? 0);
        if ($idLicence <= 0) {
            throw new InvalidArgumentException('Création de licence impossible lors de la validation.');
        }

        $this->demandeActivationRepository->marquerDemandeValidee(
            $idDemandeActivation,
            $idLicence,
            $noteInterne !== '' ? $noteInterne : null
        );

        return [
            'id_demande_activation' => $idDemandeActivation,
            'statut' => 'validee',
            'id_licence' => $idLicence,
            'cle_licence' => (string)($resultatLicence['cle_licence'] ?? ''),
        ];
    }

    public function refuserDemandeActivation(int $idDemandeActivation, string $noteInterne = ''): array
    {
        if ($idDemandeActivation <= 0) {
            throw new InvalidArgumentException('Identifiant de demande invalide.');
        }

        $demande = $this->demandeActivationRepository->trouverDemandeParId($idDemandeActivation);
        if ($demande === null) {
            throw new InvalidArgumentException('Demande d’activation introuvable.');
        }

        $statutActuel = (string)($demande['statut'] ?? '');
        if (!in_array($statutActuel, ['en_attente', 'validee'], true)) {
            throw new InvalidArgumentException('Cette demande ne peut plus être refusée dans son état actuel.');
        }

        $this->demandeActivationRepository->marquerDemandeRefusee(
            $idDemandeActivation,
            trim($noteInterne) !== '' ? trim($noteInterne) : null
        );

        return [
            'id_demande_activation' => $idDemandeActivation,
            'statut' => 'refusee',
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
