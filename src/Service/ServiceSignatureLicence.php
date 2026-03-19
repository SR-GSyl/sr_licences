<?php
declare(strict_types=1);

namespace SrLicences\Service;

use RuntimeException;

final class ServiceSignatureLicence
{
    public function signerReponse(array $reponse, array $config): array
    {
        $configSignature = (array)($config['signature'] ?? []);
        $active = (bool)($configSignature['active'] ?? false);

        if (!$active) {
            $reponse['signature_method'] = 'none';
            $reponse['signature'] = '';
            return $reponse;
        }

        $cheminClePrivee = trim((string)($configSignature['chemin_cle_privee'] ?? ''));
        if ($cheminClePrivee === '' || !is_file($cheminClePrivee)) {
            throw new RuntimeException('Clé privée introuvable pour la signature.');
        }

        $pem = file_get_contents($cheminClePrivee);
        if (!is_string($pem) || $pem === '') {
            throw new RuntimeException('Lecture impossible de la clé privée.');
        }

        $clePrivee = openssl_pkey_get_private($pem);
        if ($clePrivee === false) {
            throw new RuntimeException('Chargement impossible de la clé privée.');
        }

        $payload = $this->construirePayloadCanonique($reponse);

        $signatureBinaire = '';
        $ok = openssl_sign($payload, $signatureBinaire, $clePrivee, OPENSSL_ALGO_SHA256);

        if (is_resource($clePrivee)) {
            openssl_free_key($clePrivee);
        }

        if (!$ok) {
            throw new RuntimeException('Échec de la signature OpenSSL.');
        }

        $reponse['signature_method'] = (string)($configSignature['methode'] ?? 'RSA-SHA256');
        $reponse['signature'] = base64_encode($signatureBinaire);

        return $reponse;
    }

    private function construirePayloadCanonique(array $reponse): string
    {
        $payload = [
            'ok' => (bool)($reponse['ok'] ?? false),
            'module' => (string)($reponse['module'] ?? ''),
            'licence_key' => (string)($reponse['licence_key'] ?? ''),
            'status' => (string)($reponse['status'] ?? ''),
            'primary_domain' => (string)($reponse['primary_domain'] ?? ''),
            'test_domains' => array_values(array_map('strval', (array)($reponse['test_domains'] ?? []))),
            'request_domain' => (string)($reponse['request_domain'] ?? ''),
            'domain_match' => (bool)($reponse['domain_match'] ?? false),
            'checked_at' => (string)($reponse['checked_at'] ?? ''),
            'next_check_at' => (string)($reponse['next_check_at'] ?? ''),
            'grace_until' => (string)($reponse['grace_until'] ?? ''),
            'max_version' => (string)($reponse['max_version'] ?? ''),
            'message' => (string)($reponse['message'] ?? ''),
        ];

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($json)) {
            throw new RuntimeException('Encodage JSON impossible pour la signature.');
        }

        return $json;
    }
}
