<?php
declare(strict_types=1);

namespace SrLicences\Service;

use RuntimeException;

final class ServiceNotificationEmail
{
    public function __construct(private array $config)
    {
    }

    public function envoyer(string $destinataire, string $sujet, string $message): bool
    {
        $destinataire = trim($destinataire);
        if ($destinataire === '' || filter_var($destinataire, FILTER_VALIDATE_EMAIL) === false) {
            return false;
        }

        $configurationEmail = is_array($this->config['email'] ?? null) ? $this->config['email'] : [];
        $transport = strtolower(trim((string)($configurationEmail['transport'] ?? 'mail')));

        return match ($transport) {
            'mail' => $this->envoyerViaMail($destinataire, $sujet, $message, $configurationEmail),
            'smtp' => $this->envoyerViaSmtp($destinataire, $sujet, $message, $configurationEmail),
            'transactionnel' => false,
            default => false,
        };
    }

    private function envoyerViaMail(string $destinataire, string $sujet, string $message, array $configurationEmail): bool
    {
        $expediteurEmail = trim((string)($configurationEmail['expediteur_email'] ?? ''));
        $expediteurNom = trim((string)($configurationEmail['expediteur_nom'] ?? ''));
        $repondreAEmail = trim((string)($configurationEmail['repondre_a_email'] ?? ''));
        $repondreANom = trim((string)($configurationEmail['repondre_a_nom'] ?? ''));

        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
        ];

        if ($expediteurEmail !== '' && filter_var($expediteurEmail, FILTER_VALIDATE_EMAIL) !== false) {
            $headers[] = 'From: ' . $this->formatterAdresseEntete($expediteurEmail, $expediteurNom);
        }

        if ($repondreAEmail !== '' && filter_var($repondreAEmail, FILTER_VALIDATE_EMAIL) !== false) {
            $headers[] = 'Reply-To: ' . $this->formatterAdresseEntete($repondreAEmail, $repondreANom);
        }

        return @mail(
            $destinataire,
            $this->encoderSujetUtf8($sujet),
            $message,
            implode("\r\n", $headers)
        );
    }

    private function envoyerViaSmtp(string $destinataire, string $sujet, string $message, array $configurationEmail): bool
    {
        $smtp = is_array($configurationEmail['smtp'] ?? null) ? $configurationEmail['smtp'] : [];

        $hote = trim((string)($smtp['hote'] ?? ''));
        $port = (int)($smtp['port'] ?? 587);
        $chiffrement = strtolower(trim((string)($smtp['chiffrement'] ?? 'tls')));
        $authentification = (bool)($smtp['authentification'] ?? true);
        $utilisateur = trim((string)($smtp['utilisateur'] ?? ''));
        $motDePasse = (string)($smtp['mot_de_passe'] ?? '');
        $timeout = max(1, (int)($smtp['timeout_secondes'] ?? 15));

        $expediteurEmail = trim((string)($configurationEmail['expediteur_email'] ?? ''));
        $expediteurNom = trim((string)($configurationEmail['expediteur_nom'] ?? ''));
        $repondreAEmail = trim((string)($configurationEmail['repondre_a_email'] ?? ''));
        $repondreANom = trim((string)($configurationEmail['repondre_a_nom'] ?? ''));

        if ($hote === '' || $port <= 0) {
            return false;
        }

        if (filter_var($expediteurEmail, FILTER_VALIDATE_EMAIL) === false) {
            return false;
        }

        $prefixeConnexion = $chiffrement === 'ssl' ? 'ssl://' : '';
        $cible = $prefixeConnexion . $hote;

        $socket = @stream_socket_client(
            $cible . ':' . $port,
            $codeErreur,
            $messageErreur,
            $timeout,
            STREAM_CLIENT_CONNECT
        );

        if (!is_resource($socket)) {
            return false;
        }

        stream_set_timeout($socket, $timeout);

        try {
            $this->verifierReponse($this->lireReponse($socket), [220]);

            $ehlo = $this->nomEhlo();
            $this->envoyerCommande($socket, 'EHLO ' . $ehlo, [250]);

            if ($chiffrement === 'tls') {
                $this->envoyerCommande($socket, 'STARTTLS', [220]);

                $cryptoActive = @stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                if ($cryptoActive !== true) {
                    throw new RuntimeException('Impossible d’activer STARTTLS.');
                }

                $this->envoyerCommande($socket, 'EHLO ' . $ehlo, [250]);
            }

            if ($authentification) {
                if ($utilisateur === '' || $motDePasse === '') {
                    throw new RuntimeException('Authentification SMTP activée mais identifiants manquants.');
                }

                $this->envoyerCommande($socket, 'AUTH LOGIN', [334]);
                $this->envoyerCommande($socket, base64_encode($utilisateur), [334]);
                $this->envoyerCommande($socket, base64_encode($motDePasse), [235]);
            }

            $this->envoyerCommande($socket, 'MAIL FROM:<' . $expediteurEmail . '>', [250]);
            $this->envoyerCommande($socket, 'RCPT TO:<' . $destinataire . '>', [250, 251]);
            $this->envoyerCommande($socket, 'DATA', [354]);

            $entetes = [
                'Date: ' . date(DATE_RFC2822),
                'From: ' . $this->formatterAdresseEntete($expediteurEmail, $expediteurNom),
                'To: ' . $destinataire,
                'Subject: ' . $this->encoderSujetUtf8($sujet),
                'MIME-Version: 1.0',
                'Content-Type: text/plain; charset=UTF-8',
                'Content-Transfer-Encoding: base64',
            ];

            if ($repondreAEmail !== '' && filter_var($repondreAEmail, FILTER_VALIDATE_EMAIL) !== false) {
                $entetes[] = 'Reply-To: ' . $this->formatterAdresseEntete($repondreAEmail, $repondreANom);
            }

            $corpsEncode = rtrim(chunk_split(base64_encode($message), 76, "\r\n"));
            $donneesMessage = implode("\r\n", $entetes) . "\r\n\r\n" . $corpsEncode . "\r\n.";

            fwrite($socket, $donneesMessage . "\r\n");
            $this->verifierReponse($this->lireReponse($socket), [250]);

            $this->envoyerCommande($socket, 'QUIT', [221]);

            return true;
        } catch (RuntimeException) {
            return false;
        } finally {
            if (is_resource($socket)) {
                fclose($socket);
            }
        }
    }

    private function envoyerCommande($socket, string $commande, array $codesAttendus): void
    {
        fwrite($socket, $commande . "\r\n");
        $this->verifierReponse($this->lireReponse($socket), $codesAttendus);
    }

    private function lireReponse($socket): string
    {
        $reponse = '';

        while (($ligne = fgets($socket, 515)) !== false) {
            $reponse .= $ligne;
            if (isset($ligne[3]) && $ligne[3] === ' ') {
                break;
            }
        }

        if ($reponse === '') {
            throw new RuntimeException('Réponse SMTP vide.');
        }

        return $reponse;
    }

    private function verifierReponse(string $reponse, array $codesAttendus): void
    {
        $code = (int)substr($reponse, 0, 3);
        if (!in_array($code, $codesAttendus, true)) {
            throw new RuntimeException('Réponse SMTP inattendue : ' . trim($reponse));
        }
    }

    private function nomEhlo(): string
    {
        $hote = gethostname();
        if (!is_string($hote) || trim($hote) === '') {
            return 'localhost';
        }

        return preg_replace('/[^A-Za-z0-9.-]/', '-', $hote) ?: 'localhost';
    }

    private function encoderSujetUtf8(string $sujet): string
    {
        return '=?UTF-8?B?' . base64_encode($sujet) . '?=';
    }

    private function formatterAdresseEntete(string $email, string $nom = ''): string
    {
        if ($nom === '') {
            return $email;
        }

        return sprintf('"%s" <%s>', $this->echapperEntete($nom), $email);
    }

    private function echapperEntete(string $valeur): string
    {
        return str_replace(["\r", "\n", '"'], ['', '', '\"'], $valeur);
    }
}
