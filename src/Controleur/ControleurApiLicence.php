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

            $resultat = $service->demanderActivation(Requete::donneesEntree());
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
}
