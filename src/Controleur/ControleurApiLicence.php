<?php
declare(strict_types=1);

namespace SrLicences\Controleur;

use InvalidArgumentException;
use SrLicences\Config\BaseDeDonnees;
use SrLicences\Http\ReponseJson;
use SrLicences\Http\Requete;
use SrLicences\Repository\DemandeActivationRepository;
use SrLicences\Repository\LicenceRepository;
use SrLicences\Service\ServiceDemandeActivation;
use SrLicences\Service\ServiceLicence;
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
                $this->envoyerNotificationNouvelleDemandeActivation($resultat, $donneesEntree);
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


    private function envoyerNotificationNouvelleDemandeActivation(array $resultat, array $donneesEntree): void
    {
        $notifications = is_array($this->config['notifications'] ?? null) ? $this->config['notifications'] : [];

        $destinataire = trim((string)($notifications['email_destinataire_activation'] ?? $notifications['email_destinataire'] ?? ''));
        if ($destinataire === '' || filter_var($destinataire, FILTER_VALIDATE_EMAIL) === false) {
            return;
        }

        $expediteur = trim((string)($notifications['email_expediteur'] ?? ''));
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

        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
        ];

        if ($expediteur !== '' && filter_var($expediteur, FILTER_VALIDATE_EMAIL) !== false) {
            $headers[] = 'From: ' . $expediteur;
        }

        @mail($destinataire, $this->encoderSujetUtf8($sujet), $message, implode("\r\n", $headers));
    }

    private function encoderSujetUtf8(string $sujet): string
    {
        return '=?UTF-8?B?' . base64_encode($sujet) . '?=';
    }

}
