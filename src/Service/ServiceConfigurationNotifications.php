<?php
declare(strict_types=1);

namespace SrLicences\Service;

use PDO;

final class ServiceConfigurationNotifications
{
    private ServiceParametreApplication $serviceParametreApplication;
    private ServiceSecretApplication $serviceSecretApplication;

    public function __construct(private PDO $pdo, private array $config)
    {
        $this->serviceParametreApplication = new ServiceParametreApplication($this->pdo);
        $this->serviceSecretApplication = new ServiceSecretApplication($this->pdo, $this->config);
    }

    public function recupererConfiguration(): array
    {
        $notificationsConfig = is_array($this->config['notifications'] ?? null) ? $this->config['notifications'] : [];
        $emailConfig = is_array($this->config['email'] ?? null) ? $this->config['email'] : [];
        $smtpConfig = is_array($emailConfig['smtp'] ?? null) ? $emailConfig['smtp'] : [];
        $transactionnelConfig = is_array($emailConfig['transactionnel'] ?? null) ? $emailConfig['transactionnel'] : [];

        $transport = strtolower(trim($this->serviceParametreApplication->recupererValeurTexte(
            'email',
            'transport',
            (string)($emailConfig['transport'] ?? 'mail')
        )));
        if (!in_array($transport, ['mail', 'smtp', 'transactionnel'], true)) {
            $transport = 'mail';
        }

        $chiffrement = strtolower(trim($this->serviceParametreApplication->recupererValeurTexte(
            'email_smtp',
            'chiffrement',
            (string)($smtpConfig['chiffrement'] ?? 'tls')
        )));
        if (!in_array($chiffrement, ['none', 'tls', 'ssl'], true)) {
            $chiffrement = 'tls';
        }

        return [
            'notifications' => [
                'activees' => $this->serviceParametreApplication->recupererValeurBooleenne(
                    'notifications',
                    'activees',
                    (bool)($notificationsConfig['activees'] ?? true)
                ),
                'email_destinataire' => trim($this->serviceParametreApplication->recupererValeurTexte(
                    'notifications',
                    'email_destinataire',
                    (string)($notificationsConfig['email_destinataire'] ?? '')
                )),
                'email_destinataire_activation' => trim($this->serviceParametreApplication->recupererValeurTexte(
                    'notifications',
                    'email_destinataire_activation',
                    (string)($notificationsConfig['email_destinataire_activation'] ?? '')
                )),
                'email_destinataire_domaines_test' => trim($this->serviceParametreApplication->recupererValeurTexte(
                    'notifications',
                    'email_destinataire_domaines_test',
                    (string)($notificationsConfig['email_destinataire_domaines_test'] ?? '')
                )),
                'prefixe_sujet' => $this->serviceParametreApplication->recupererValeurTexte(
                    'notifications',
                    'prefixe_sujet',
                    (string)($notificationsConfig['prefixe_sujet'] ?? '[SR Licences]')
                ),
            ],
            'email' => [
                'transport' => $transport,
                'expediteur_email' => trim($this->serviceParametreApplication->recupererValeurTexte(
                    'email',
                    'expediteur_email',
                    (string)($emailConfig['expediteur_email'] ?? '')
                )),
                'expediteur_nom' => $this->serviceParametreApplication->recupererValeurTexte(
                    'email',
                    'expediteur_nom',
                    (string)($emailConfig['expediteur_nom'] ?? 'SR Licences')
                ),
                'repondre_a_email' => trim($this->serviceParametreApplication->recupererValeurTexte(
                    'email',
                    'repondre_a_email',
                    (string)($emailConfig['repondre_a_email'] ?? '')
                )),
                'repondre_a_nom' => $this->serviceParametreApplication->recupererValeurTexte(
                    'email',
                    'repondre_a_nom',
                    (string)($emailConfig['repondre_a_nom'] ?? '')
                ),
                'smtp' => [
                    'hote' => trim($this->serviceParametreApplication->recupererValeurTexte(
                        'email_smtp',
                        'hote',
                        (string)($smtpConfig['hote'] ?? '')
                    )),
                    'port' => $this->serviceParametreApplication->recupererValeurEntiere(
                        'email_smtp',
                        'port',
                        (int)($smtpConfig['port'] ?? 587)
                    ),
                    'chiffrement' => $chiffrement,
                    'authentification' => $this->serviceParametreApplication->recupererValeurBooleenne(
                        'email_smtp',
                        'authentification',
                        (bool)($smtpConfig['authentification'] ?? true)
                    ),
                    'utilisateur' => trim($this->serviceParametreApplication->recupererValeurTexte(
                        'email_smtp',
                        'utilisateur',
                        (string)($smtpConfig['utilisateur'] ?? '')
                    )),
                    'mot_de_passe' => $this->recupererSecretOuValeurParDefaut(
                        'email_smtp',
                        'mot_de_passe',
                        (string)($smtpConfig['mot_de_passe'] ?? '')
                    ),
                    'timeout_secondes' => (int)($smtpConfig['timeout_secondes'] ?? 15),
                ],
                'transactionnel' => [
                    'fournisseur' => trim($this->serviceParametreApplication->recupererValeurTexte(
                        'email_transactionnel',
                        'fournisseur',
                        (string)($transactionnelConfig['fournisseur'] ?? '')
                    )),
                    'endpoint' => trim($this->serviceParametreApplication->recupererValeurTexte(
                        'email_transactionnel',
                        'endpoint',
                        (string)($transactionnelConfig['endpoint'] ?? '')
                    )),
                    'cle_api' => $this->recupererSecretOuValeurParDefaut(
                        'email_transactionnel',
                        'cle_api',
                        (string)($transactionnelConfig['cle_api'] ?? '')
                    ),
                    'timeout_secondes' => (int)($transactionnelConfig['timeout_secondes'] ?? 15),
                ],
            ],
        ];
    }

    private function recupererSecretOuValeurParDefaut(string $groupeSecret, string $cleSecret, string $valeurParDefaut = ''): string
    {
        if ($this->serviceSecretApplication->secretExiste($groupeSecret, $cleSecret)) {
            $secret = $this->serviceSecretApplication->recupererSecretDechiffre($groupeSecret, $cleSecret);
            if ($secret !== null) {
                return $secret;
            }
        }

        return $valeurParDefaut;
    }
}
