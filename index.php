<?php
declare(strict_types=1);

$config = require __DIR__ . '/config/config.php';

date_default_timezone_set((string)($config['timezone'] ?? 'Europe/Paris'));

require_once __DIR__ . '/src/Util/Autoload.php';

use SrLicences\Controleur\ControleurAccueil;
use SrLicences\Controleur\ControleurApiLicence;
use SrLicences\Http\Requete;

$chemin = Requete::chemin();

$nomSession = (string)($config['session']['nom'] ?? 'SRLICSESSID');
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name($nomSession);
    session_start();
}

if (isset($_GET['logout'])) {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'] ?? '/',
            $params['domain'] ?? '',
            (bool)($params['secure'] ?? false),
            (bool)($params['httponly'] ?? true)
        );
    }
    session_destroy();
    header('Location: /');
    exit;
}

$routesPubliques = [
    '/api/licence/check',
    '/api/licence/demander-activation',
    '/api/licence/verifier-activation',
];

$routeEstPublique = in_array($chemin, $routesPubliques, true);

$protectionActive = (bool)($config['protection_temporaire_active'] ?? true);
$utilisateurAttendu = (string)($config['utilisateur_admin_temporaire'] ?? '');
$hashAttendu = (string)($config['mot_de_passe_hash_temporaire'] ?? '');
$erreurConnexion = '';

if (!$routeEstPublique && $protectionActive && empty($_SESSION['sr_licences_auth'])) {
    if (Requete::methode() === 'POST') {
        $utilisateur = trim((string)($_POST['utilisateur'] ?? ''));
        $motDePasse = (string)($_POST['mot_de_passe'] ?? '');

        if ($utilisateur === $utilisateurAttendu && password_verify($motDePasse, $hashAttendu)) {
            $_SESSION['sr_licences_auth'] = true;
            $_SESSION['sr_licences_utilisateur'] = $utilisateur;
            header('Location: /');
            exit;
        }

        $erreurConnexion = 'Identifiant ou mot de passe incorrect.';
    }

    header('Content-Type: text/html; charset=UTF-8');
    ?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <title>Accès restreint - SR Licences</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    body{margin:0;font-family:Arial,sans-serif;background:#f4f6f8;color:#1f2937}
    .conteneur{max-width:420px;margin:7vh auto;background:#fff;border:1px solid #d1d5db;border-radius:14px;padding:24px;box-shadow:0 6px 24px rgba(0,0,0,.08)}
    h1{margin-top:0;font-size:24px}
    label{display:block;margin:14px 0 6px;font-weight:700}
    input{width:100%;box-sizing:border-box;padding:11px 12px;border:1px solid #cbd5e1;border-radius:10px;font-size:15px}
    button{margin-top:18px;width:100%;padding:12px 14px;border:0;border-radius:10px;background:#111827;color:#fff;font-size:15px;font-weight:700;cursor:pointer}
    .erreur{margin-top:12px;padding:10px 12px;border-radius:10px;background:#fee2e2;color:#991b1b;border:1px solid #fecaca}
    .note{margin-top:10px;color:#4b5563;font-size:14px}
  </style>
</head>
<body>
  <div class="conteneur">
    <h1>Accès restreint</h1>
    <p>Le serveur de licences est actuellement en cours de construction.</p>

    <form method="post" action="">
      <label for="utilisateur">Identifiant</label>
      <input type="text" id="utilisateur" name="utilisateur" autocomplete="username" required>

      <label for="mot_de_passe">Mot de passe</label>
      <input type="password" id="mot_de_passe" name="mot_de_passe" autocomplete="current-password" required>

      <button type="submit">Se connecter</button>
    </form>

    <?php if ($erreurConnexion !== ''): ?>
      <div class="erreur"><?php echo htmlspecialchars($erreurConnexion, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <p class="note">Accès temporairement réservé à l’administration.</p>
  </div>
</body>
</html>
    <?php
    exit;
}

switch ($chemin) {
    case '/':
        (new ControleurAccueil($config))->afficherTableauDeBord();
        break;

    case '/licences/creer':
        (new ControleurAccueil($config))->traiterCreationLicence();
        break;

    case '/licences/voir':
        (new ControleurAccueil($config))->afficherLicence();
        break;

    case '/licences/modifier':
        (new ControleurAccueil($config))->gererModificationLicence();
        break;

    case '/licences/reactiver':
        (new ControleurAccueil($config))->gererReactivationLicences();
        break;

    case '/licences/statut-lot':
        (new ControleurAccueil($config))->traiterChangementStatutLicencesLot();
        break;

    case '/demandes-activation/voir':
        (new ControleurAccueil($config))->afficherDemandeActivation();
        break;

    case '/demandes-activation/decision':
        (new ControleurAccueil($config))->traiterDecisionDemandeActivation();
        break;

    case '/licences/statut':
        (new ControleurAccueil($config))->traiterChangementStatutLicence();
        break;

    case '/api/sante':
        (new ControleurApiLicence($config))->sante();
        break;

    case '/api/licence/check':
        (new ControleurApiLicence($config))->check();
        break;

    case '/api/licence/demander-activation':
        (new ControleurApiLicence($config))->demanderActivation();
        break;

    case '/api/licence/verifier-activation':
        (new ControleurApiLicence($config))->verifierActivation();
        break;

    default:
        http_response_code(404);
        header('Content-Type: text/plain; charset=UTF-8');
        echo "404 - Route introuvable";
        exit;
}
