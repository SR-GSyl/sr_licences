<?php
declare(strict_types=1);

namespace SrLicences\Controleur;

use InvalidArgumentException;
use SrLicences\Config\BaseDeDonnees;
use SrLicences\Http\ReponseJson;
use SrLicences\Http\Requete;
use SrLicences\Repository\DemandeActivationRepository;
use SrLicences\Repository\DemandeDomainesTestRepository;
use SrLicences\Repository\LicenceRepository;
use SrLicences\Service\ServiceDemandeActivation;
use SrLicences\Service\ServiceConfigurationNotifications;
use SrLicences\Service\ServiceDemandeDomainesTest;
use SrLicences\Service\ServiceLicence;
use SrLicences\Service\ServiceNotificationEmail;
use SrLicences\Service\ServiceSignatureLicence;
use Throwable;

final class ControleurApiLicence
{
    public function __construct(private array $config)
    {
    }

    public function sante(): void
    {
        $etatBdd = BaseDeDonnees::testerConnexion($this->config);

        ReponseJson::envoyer([
            'ok' => true,
            'application' => (string)($this->config['application_nom'] ?? 'SR Licences'),
            'php_version' => PHP_VERSION,
            'heure_utc' => gmdate('c'),
            'etat_bdd' => $etatBdd,
        ]);
    }

    public function check(): void
    {
        try {
            $pdo = BaseDeDonnees::creerDepuisConfig($this->config);
            $serviceLicence = new ServiceLicence(new LicenceRepository($pdo));
            $serviceSignature = new ServiceSignatureLicence();

            $donnees = Requete::donneesEntree();
            $resultat = $serviceLicence->verifierLicencePourApi($donnees);
            $serviceLicence->enregistrerObservationVerification($donnees, $resultat);
            $resultat = $serviceSignature->signerReponse($resultat, $this->config);

            ReponseJson::envoyer($resultat, 200);
        } catch (InvalidArgumentException $e) {
            ReponseJson::envoyer([
                'ok' => false,
                'message' => $e->getMessage(),
            ], 400);
        } catch (Throwable $e) {
            ReponseJson::envoyer([
                'ok' => false,
                'message' => 'Erreur interne de vérification de licence.',
                'detail' => $e->getMessage(),
            ], 500);
        }
    }

    public function demanderActivation(): void
    {
        try {
            $pdo = BaseDeDonnees::creerDepuisConfig($this->config);
            $service = new ServiceDemandeActivation(
                new DemandeActivationRepository($pdo),
                new LicenceRepository($pdo)
            );

            $donneesEntree = Requete::donneesEntree();
            $resultat = $service->demanderActivation($donneesEntree);

            if (!empty($resultat['ok'])) {
                $configurationNotifications = (new ServiceConfigurationNotifications($pdo, $this->config))->recupererConfiguration();
                $this->envoyerNotificationNouvelleDemandeActivation($resultat, $donneesEntree, $configurationNotifications);
            }

            ReponseJson::envoyer($resultat, 200);
        } catch (InvalidArgumentException $e) {
            ReponseJson::envoyer([
                'ok' => false,
                'message' => $e->getMessage(),
            ], 400);
        } catch (Throwable $e) {
            ReponseJson::envoyer([
                'ok' => false,
                'message' => 'Erreur interne lors de la demande d’activation.',
                'detail' => $e->getMessage(),
            ], 500);
        }
    }

    public function verifierActivation(): void
    {
        try {
            $pdo = BaseDeDonnees::creerDepuisConfig($this->config);
            $service = new ServiceDemandeActivation(
                new DemandeActivationRepository($pdo),
                new LicenceRepository($pdo)
            );

            $resultat = $service->verifierActivation(Requete::donneesEntree());
            ReponseJson::envoyer($resultat, 200);
        } catch (InvalidArgumentException $e) {
            ReponseJson::envoyer([
                'ok' => false,
                'message' => $e->getMessage(),
            ], 400);
        } catch (Throwable $e) {
            ReponseJson::envoyer([
                'ok' => false,
                'message' => 'Erreur interne lors de la vérification d’activation.',
                'detail' => $e->getMessage(),
            ], 500);
        }
    }

    public function demanderDomainesTest(): void
    {
        try {
            $pdo = BaseDeDonnees::creerDepuisConfig($this->config);
            $service = new ServiceDemandeDomainesTest(
                new DemandeDomainesTestRepository($pdo),
                new LicenceRepository($pdo)
            );

            $donneesEntree = Requete::donneesEntree();
            $resultat = $service->demanderMiseAJourDomainesTest($donneesEntree);

            if (!empty($resultat['ok'])) {
                $configurationNotifications = (new ServiceConfigurationNotifications($pdo, $this->config))->recupererConfiguration();
                $this->envoyerNotificationNouvelleDemandeDomainesTest($resultat, $donneesEntree, $configurationNotifications);
            }

            ReponseJson::envoyer($resultat, 200);
        } catch (InvalidArgumentException $e) {
            ReponseJson::envoyer([
                'ok' => false,
                'message' => $e->getMessage(),
            ], 400);
        } catch (Throwable $e) {
            ReponseJson::envoyer([
                'ok' => false,
                'message' => 'Erreur interne lors de la demande de mise à jour des domaines de test.',
                'detail' => $e->getMessage(),
            ], 500);
        }
    }

    public function verifierDomainesTest(): void
    {
        try {
            $pdo = BaseDeDonnees::creerDepuisConfig($this->config);
            $service = new ServiceDemandeDomainesTest(
                new DemandeDomainesTestRepository($pdo),
                new LicenceRepository($pdo)
            );

            $resultat = $service->verifierMiseAJourDomainesTest(Requete::donneesEntree());
            ReponseJson::envoyer($resultat, 200);
        } catch (InvalidArgumentException $e) {
            ReponseJson::envoyer([
                'ok' => false,
                'message' => $e->getMessage(),
            ], 400);
        } catch (Throwable $e) {
            ReponseJson::envoyer([
                'ok' => false,
                'message' => 'Erreur interne lors de la vérification des domaines de test.',
                'detail' => $e->getMessage(),
            ], 500);
        }
    }


    private function envoyerNotificationNouvelleDemandeActivation(array $resultat, array $donneesEntree, array $configurationNotifications): void
    {
        $notifications = is_array($configurationNotifications['notifications'] ?? null) ? $configurationNotifications['notifications'] : [];
        if (!((bool)($notifications['activees'] ?? true))) {
            return;
        }

        $destinataire = trim((string)($notifications['email_destinataire_activation'] ?? $notifications['email_destinataire'] ?? ''));
        if ($destinataire === '' || filter_var($destinataire, FILTER_VALIDATE_EMAIL) === false) {
            return;
        }

        $prefixeSujet = trim((string)($notifications['prefixe_sujet'] ?? '[SR Licences]'));

        $idDemande = (int)($resultat['id_demande_activation'] ?? 0);
        $codeModule = trim((string)($donneesEntree['code_module'] ?? $donneesEntree['module'] ?? ''));
        $versionModule = trim((string)($donneesEntree['version_module'] ?? $donneesEntree['version'] ?? ''));
        $nomClient = trim((string)($donneesEntree['nom_client'] ?? ''));
        $emailClient = trim((string)($donneesEntree['email_client'] ?? ''));
        $numeroCommande = trim((string)($donneesEntree['numero_commande'] ?? ''));
        $domainePrincipal = trim((string)($donneesEntree['domaine_principal'] ?? $donneesEntree['domain'] ?? $donneesEntree['domaine'] ?? ''));
        $domainesTest = trim((string)($donneesEntree['domaines_test'] ?? ''));

        $baseAdmin = trim((string)($this->config['application_url_admin'] ?? $this->config['application_url'] ?? ''));
        $lienDemande = $baseAdmin !== ''
            ? rtrim($baseAdmin, '/') . '/demandes-activation/voir?id=' . $idDemande
            : '';

        $sujet = trim($prefixeSujet . ' Nouvelle demande d’activation #' . $idDemande);

        $message = implode("\n", array_filter([
            'Une nouvelle demande d’activation a été enregistrée.',
            '',
            'ID : ' . $idDemande,
            $codeModule !== '' ? 'Module : ' . $codeModule : '',
            $versionModule !== '' ? 'Version module : ' . $versionModule : '',
            $nomClient !== '' ? 'Client : ' . $nomClient : '',
            $emailClient !== '' ? 'E-mail client : ' . $emailClient : '',
            $numeroCommande !== '' ? 'Commande : ' . $numeroCommande : '',
            $domainePrincipal !== '' ? 'Domaine principal : ' . $domainePrincipal : '',
            $domainesTest !== '' ? 'Domaines de test : ' . $domainesTest : '',
            $lienDemande !== '' ? 'Lien admin : ' . $lienDemande : '',
        ]));

        $serviceNotificationEmail = new ServiceNotificationEmail($configurationNotifications);
        $serviceNotificationEmail->envoyer($destinataire, $sujet, $message);
    }

    private function envoyerNotificationNouvelleDemandeDomainesTest(array $resultat, array $donneesEntree, array $configurationNotifications): void
    {
        $notifications = is_array($configurationNotifications['notifications'] ?? null) ? $configurationNotifications['notifications'] : [];
        if (!((bool)($notifications['activees'] ?? true))) {
            return;
        }

        $destinataire = trim((string)($notifications['email_destinataire_domaines_test'] ?? $notifications['email_destinataire'] ?? ''));
        if ($destinataire === '' || filter_var($destinataire, FILTER_VALIDATE_EMAIL) === false) {
            return;
        }

        $prefixeSujet = trim((string)($notifications['prefixe_sujet'] ?? '[SR Licences]'));

        $idDemande = (int)($resultat['id_demande_domaines_test'] ?? 0);
        $codeModule = trim((string)($donneesEntree['code_module'] ?? $donneesEntree['module'] ?? ''));
        $cleLicence = trim((string)($donneesEntree['cle_licence'] ?? $donneesEntree['licence_key'] ?? ''));
        $domainePrincipal = trim((string)($donneesEntree['domaine_principal'] ?? $donneesEntree['domain'] ?? $donneesEntree['domaine'] ?? ''));
        $domainesDemandes = trim((string)($donneesEntree['domaines_test_demandes'] ?? $donneesEntree['domaines_test'] ?? ''));
        $motif = trim((string)($donneesEntree['motif'] ?? ''));

        $baseAdmin = trim((string)($this->config['application_url_admin'] ?? $this->config['application_url'] ?? ''));
        $lienDemande = $baseAdmin !== ''
            ? rtrim($baseAdmin, '/') . '/demandes-domaines-test/voir?id=' . $idDemande
            : '';

        $sujet = trim($prefixeSujet . ' Nouvelle demande de domaines de test #' . $idDemande);

        $message = implode("\n", array_filter([
            'Une nouvelle demande de mise à jour des domaines de test a été enregistrée.',
            '',
            'ID : ' . $idDemande,
            $codeModule !== '' ? 'Module : ' . $codeModule : '',
            $cleLicence !== '' ? 'Clé de licence : ' . $cleLicence : '',
            $domainePrincipal !== '' ? 'Domaine principal : ' . $domainePrincipal : '',
            $domainesDemandes !== '' ? 'Domaines demandés : ' . $domainesDemandes : '',
            $motif !== '' ? 'Motif : ' . $motif : '',
            $lienDemande !== '' ? 'Lien admin : ' . $lienDemande : '',
        ]));

        $serviceNotificationEmail = new ServiceNotificationEmail($configurationNotifications);
        $serviceNotificationEmail->envoyer($destinataire, $sujet, $message);
    }

    private function encoderSujetUtf8(string $sujet): string
    {
        return '=?UTF-8?B?' . base64_encode($sujet) . '?=';
    }

}
