<?php
declare(strict_types=1);

namespace SrLicences\Controleur;

use SrLicences\Config\BaseDeDonnees;
use SrLicences\Repository\DemandeActivationRepository;
use SrLicences\Repository\LicenceRepository;
use SrLicences\Service\ServiceDemandeActivation;
use SrLicences\Service\ServiceLicence;
use Throwable;

final class ControleurAccueil
{
    public function __construct(private array $config)
    {
    }

    public function afficherTableauDeBord(): void
    {
        if (empty($_SESSION['sr_licences_csrf'])) {
            $_SESSION['sr_licences_csrf'] = bin2hex(random_bytes(16));
        }

        $etatBdd = BaseDeDonnees::testerConnexion($this->config);
        $utilisateur = (string)($_SESSION['sr_licences_utilisateur'] ?? '');
        $messageSucces = (string)($_SESSION['sr_licences_message_succes'] ?? '');
        $messageErreur = (string)($_SESSION['sr_licences_message_erreur'] ?? '');

        unset($_SESSION['sr_licences_message_succes'], $_SESSION['sr_licences_message_erreur']);

        $statistiques = [
            'total' => 0,
            'active' => 0,
            'suspendue' => 0,
            'revoquee' => 0,
            'expiree' => 0,
            'invalide' => 0,
        ];

        $licences = [];
        $alertesSilence = [];
        $statistiquesDemandesActivation = [
            'total' => 0,
            'en_attente' => 0,
            'validee' => 0,
            'refusee' => 0,
            'terminee' => 0,
        ];
        $demandesActivation = [];
        $messageStatistiques = 'Compteurs non disponibles tant que la BDD n’est pas configurée.';
        $messageListe = 'Liste non disponible tant que la BDD n’est pas configurée.';
        $messageDemandesActivation = 'Demandes d’activation non disponibles tant que la BDD n’est pas configurée.';

        if (!empty($etatBdd['ok'])) {
            try {
                $pdo = BaseDeDonnees::creerDepuisConfig($this->config);
                $service = new ServiceLicence(new LicenceRepository($pdo));
                $statistiques = $service->obtenirStatistiquesTableauDeBord();
                $licences = $service->obtenirLicencesTableauDeBord(100);
                $alertesSilence = $service->obtenirAlertesSilenceVerification();
                $service->journaliserAlertesSilenceVerification($alertesSilence);
                $messageStatistiques = 'Lecture des statistiques OK.';
                $messageListe = 'Lecture de la liste OK.';

                try {
                    $serviceDemandesActivation = new ServiceDemandeActivation(
                        new DemandeActivationRepository($pdo),
                        new LicenceRepository($pdo)
                    );
                    $statistiquesDemandesActivation = $serviceDemandesActivation->obtenirStatistiquesTableauDeBord();
                    $demandesActivation = $serviceDemandesActivation->obtenirDemandesTableauDeBord(100);
                    $messageDemandesActivation = 'Lecture des demandes d’activation OK.';
                } catch (Throwable $e) {
                    $messageDemandesActivation = $e->getMessage();
                }
            } catch (Throwable $e) {
                $messageStatistiques = $e->getMessage();
                $messageListe = $e->getMessage();
                $messageDemandesActivation = $e->getMessage();
            }
        }

        header('Content-Type: text/html; charset=UTF-8');
        ?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <title>SR Licences</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    body{margin:0;font-family:Arial,sans-serif;background:#f8fafc;color:#111827}
    .page{max-width:1320px;margin:32px auto;background:#fff;border:1px solid #dbe3ea;border-radius:16px;padding:24px;box-shadow:0 6px 24px rgba(0,0,0,.06)}
    .barre{display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap}
    .badge{display:inline-block;padding:6px 10px;border-radius:999px;background:#dcfce7;color:#166534;font-weight:700;border:1px solid #86efac}
    .grille{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:14px;margin-top:22px}
    .carte{border:1px solid #dbe3ea;border-radius:14px;padding:16px;background:#fff}
    .titre{margin:0 0 8px 0;font-size:18px}
    .valeur{font-size:28px;font-weight:700}
    .valeur-mini{font-size:22px;font-weight:700}
    a.bouton{display:inline-block;padding:10px 14px;border-radius:10px;background:#111827;color:#fff;text-decoration:none;font-weight:700}
    code{background:#f1f5f9;border:1px solid #cbd5e1;padding:2px 6px;border-radius:6px}
    .muted{color:#4b5563}
    .etat-ok{color:#166534}
    .etat-ko{color:#991b1b}
    .alerte-ok,.alerte-ko{margin-top:16px;padding:12px 14px;border-radius:12px;border:1px solid}
    .alerte-ok{background:#ecfdf5;color:#166534;border-color:#a7f3d0}
    .alerte-ko{background:#fef2f2;color:#991b1b;border-color:#fecaca}
    .formulaire{margin-top:24px;padding:18px;border:1px solid #dbe3ea;border-radius:14px;background:#fbfdff}
    .formulaire h2{margin-top:0}
    .grille-form{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:14px}
    .champ label{display:block;margin-bottom:6px;font-weight:700}
    .champ input,.champ select,.champ textarea{width:100%;box-sizing:border-box;padding:10px 12px;border:1px solid #cbd5e1;border-radius:10px;font:inherit}
    .champ textarea{min-height:96px;resize:vertical}
    .actions-form{margin-top:16px}
    .actions-form button{padding:11px 16px;border:0;border-radius:10px;background:#111827;color:#fff;font-weight:700;cursor:pointer}
    .bloc-liste{margin-top:24px;padding:18px;border:1px solid #dbe3ea;border-radius:14px;background:#fff}
    .bloc-liste h2{margin-top:0}
    .table-wrap{overflow:auto;margin-top:14px}
    table{width:100%;border-collapse:collapse;font-size:14px}
    th,td{padding:10px 12px;border-bottom:1px solid #e5e7eb;text-align:left;vertical-align:top}
    th{background:#f8fafc;font-weight:700;position:sticky;top:0}
    .badge-statut{display:inline-block;padding:5px 9px;border-radius:999px;border:1px solid;font-weight:700;font-size:12px;line-height:1.2;white-space:nowrap}
    .statut-active{background:#dcfce7;color:#166534;border-color:#86efac}
    .statut-suspendue{background:#fff7ed;color:#9a3412;border-color:#fdba74}
    .statut-revoquee{background:#fee2e2;color:#991b1b;border-color:#fca5a5}
    .statut-expiree{background:#fef3c7;color:#92400e;border-color:#fcd34d}
    .statut-invalide{background:#e5e7eb;color:#374151;border-color:#cbd5e1}
    .cellule-cle{min-width:220px}
    .cellule-domaine{min-width:170px}
    .cellule-domaines-test{min-width:220px}
    .cellule-email{min-width:190px}
    .cellule-date{white-space:nowrap}
    .cellule-actions{min-width:220px}
    .actions-ligne{display:flex;flex-wrap:wrap;gap:6px}
    .actions-ligne form{margin:0}
    .btn-mini{padding:7px 10px;border:0;border-radius:8px;font-size:12px;font-weight:700;cursor:pointer}
    .btn-reactiver{background:#166534;color:#fff}
    .btn-suspendre{background:#b45309;color:#fff}
    .btn-revoquer{background:#b91c1c;color:#fff}
    .btn-voir{display:inline-block;padding:7px 10px;border:0;border-radius:8px;font-size:12px;font-weight:700;background:#1d4ed8;color:#fff;text-decoration:none;line-height:1.2}
    .bloc-actions-lot{margin:0 0 14px 0;padding:14px;border:1px solid #dbe3ea;border-radius:12px;background:#f8fafc}
    .barre-actions-lot{display:flex;gap:10px;align-items:center;flex-wrap:wrap}
    .barre-actions-lot select{padding:9px 11px;border:1px solid #cbd5e1;border-radius:10px;font:inherit;background:#fff}
    .btn-appliquer-lot{padding:10px 14px;border:0;border-radius:10px;background:#111827;color:#fff;font-weight:700;cursor:pointer}
    .resume-selection{color:#4b5563;font-size:13px;font-weight:700}
    .colonne-selection{position:sticky;left:0;background:#fff;z-index:2;min-width:112px;width:112px;white-space:nowrap;box-shadow:1px 0 0 #e5e7eb}
    th.colonne-selection{background:#f8fafc;z-index:5}
    th.colonne-selection,td.colonne-selection{padding-right:8px}
    .ligne-selection{display:flex;align-items:center;gap:6px;flex-wrap:wrap}
    .checkbox-ligne-licence,.checkbox-toutes-licences{width:18px;height:18px;cursor:pointer}
    .etiquette-tout-selectionner{display:inline-flex;align-items:center;gap:8px;font-weight:700}
    .barre-filtres-liste{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin:16px 0 14px 0;padding:14px;border:1px solid #dbe3ea;border-radius:12px;background:#f8fafc}
    .champ-filtre label{display:block;margin-bottom:6px;font-weight:700;font-size:13px}
    .champ-filtre input,.champ-filtre select{width:100%;box-sizing:border-box;padding:9px 11px;border:1px solid #cbd5e1;border-radius:10px;font:inherit;background:#fff}
    .actions-filtres{display:flex;gap:8px;align-items:end;flex-wrap:wrap}
    .btn-secondaire{padding:10px 12px;border:1px solid #cbd5e1;border-radius:10px;background:#fff;color:#111827;font-weight:700;cursor:pointer}
    .resume-filtres{margin:0 0 10px 0;color:#4b5563;font-size:13px}
    .ligne-masquee{display:none}
    .entete-accordeon{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap}
    .bouton-accordeon{padding:10px 12px;border:1px solid #cbd5e1;border-radius:10px;background:#fff;color:#111827;font-weight:700;cursor:pointer}
    .contenu-accordeon{display:none;margin-top:14px}
    .contenu-accordeon.ouvert{display:block}
  </style>
</head>
<body>
  <div class="page">
    <div class="barre">
      <div>
        <h1 style="margin:0 0 8px 0;">SR Licences</h1>
        <span class="badge">Socle admin actif</span>
      </div>
      <a class="bouton" href="/?logout=1">Se déconnecter</a>
    </div>

    <p>Utilisateur connecté : <code><?php echo htmlspecialchars($utilisateur, ENT_QUOTES, 'UTF-8'); ?></code></p>

    <?php if ($messageSucces !== ''): ?>
      <div class="alerte-ok"><?php echo htmlspecialchars($messageSucces, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <?php if ($messageErreur !== ''): ?>
      <div class="alerte-ko"><?php echo htmlspecialchars($messageErreur, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <?php if (!empty($alertesSilence)): ?>
      <div class="alerte-ko">
        <strong><?php echo (int)count($alertesSilence); ?> licence(s) sans vérification récente détectée(s).</strong>
        <div class="muted" style="margin-top:8px;">Une vérification est considérée en retard si le délai prévu est dépassé depuis plus de 120 minutes.</div>
        <ul style="margin:10px 0 0 18px;padding:0;">
          <?php foreach (array_slice($alertesSilence, 0, 5) as $alerte): ?>
            <li>
              <code><?php echo htmlspecialchars((string)($alerte['module'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></code>
              — <code><?php echo htmlspecialchars((string)($alerte['licence_key'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></code>
              — <?php echo htmlspecialchars((string)($alerte['request_domain'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
              — retard : <strong><?php echo (int)($alerte['retard_minutes'] ?? 0); ?> min</strong>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <div class="grille">
      <div class="carte">
        <h2 class="titre">Base de données</h2>
        <div class="valeur <?php echo !empty($etatBdd['ok']) ? 'etat-ok' : 'etat-ko'; ?>">
          <?php echo !empty($etatBdd['ok']) ? 'OK' : 'KO'; ?>
        </div>
        <p class="muted"><?php echo htmlspecialchars((string)($etatBdd['message'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
      </div>

      <div class="carte">
        <h2 class="titre">Licences total</h2>
        <div class="valeur"><?php echo (int)$statistiques['total']; ?></div>
        <p class="muted"><?php echo htmlspecialchars($messageStatistiques, ENT_QUOTES, 'UTF-8'); ?></p>
      </div>

      <div class="carte">
        <h2 class="titre">Licences actives</h2>
        <div class="valeur-mini"><?php echo (int)$statistiques['active']; ?></div>
        <p class="muted">Statut : <code>active</code></p>
      </div>

      <div class="carte">
        <h2 class="titre">Licences suspendues</h2>
        <div class="valeur-mini"><?php echo (int)$statistiques['suspendue']; ?></div>
        <p class="muted">Statut : <code>suspendue</code></p>
      </div>

      <div class="carte">
        <h2 class="titre">Licences révoquées</h2>
        <div class="valeur-mini"><?php echo (int)$statistiques['revoquee']; ?></div>
        <p class="muted">Statut : <code>revoquee</code></p>
      </div>

      <div class="carte">
        <h2 class="titre">Licences expirées</h2>
        <div class="valeur-mini"><?php echo (int)$statistiques['expiree']; ?></div>
        <p class="muted">Statut : <code>expiree</code></p>
      </div>

      <div class="carte">
        <h2 class="titre">Licences invalides</h2>
        <div class="valeur-mini"><?php echo (int)$statistiques['invalide']; ?></div>
        <p class="muted">Statut : <code>invalide</code></p>
      </div>

      <div class="carte">
        <h2 class="titre">API santé</h2>
        <div class="valeur-mini"><a href="/api/sante">/api/sante</a></div>
        <p class="muted">Route minimale de contrôle technique.</p>
      </div>
    </div>

    <div class="formulaire">
      <div class="entete-accordeon">
        <h2 style="margin:0;">Créer une licence</h2>
        <button type="button" class="bouton-accordeon" id="bouton_accordeon_creation">Afficher la création d’une licence</button>
      </div>

      <div class="contenu-accordeon" id="contenu_accordeon_creation">
      <form method="post" action="/licences/creer">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars((string)$_SESSION['sr_licences_csrf'], ENT_QUOTES, 'UTF-8'); ?>">

        <div class="grille-form">
          <div class="champ">
            <label for="code_module">Code module</label>
            <input type="text" id="code_module" name="code_module" value="sr_merchant_flux" required>
          </div>

          <div class="champ">
            <label for="statut">Statut initial</label>
            <select id="statut" name="statut" required>
              <option value="active">active</option>
              <option value="suspendue">suspendue</option>
              <option value="revoquee">revoquee</option>
              <option value="expiree">expiree</option>
              <option value="invalide">invalide</option>
            </select>
          </div>

          <div class="champ">
            <label for="type_licence">Type de licence</label>
            <select id="type_licence" name="type_licence" required>
              <option value="perpetuelle">perpétuelle</option>
              <option value="abonnement">abonnement</option>
            </select>
          </div>

          <div class="champ">
            <label for="nom_client">Nom client</label>
            <input type="text" id="nom_client" name="nom_client">
          </div>

          <div class="champ">
            <label for="email_client">E-mail client</label>
            <input type="email" id="email_client" name="email_client">
          </div>

          <div class="champ">
            <label for="domaine_principal">Domaine principal</label>
            <input type="text" id="domaine_principal" name="domaine_principal" placeholder="exemple.com">
          </div>

          <div class="champ">
            <label for="version_max_autorisee">Version max autorisée</label>
            <input type="text" id="version_max_autorisee" name="version_max_autorisee" placeholder="2.6.*">
          </div>

          <div class="champ" id="bloc_validite_valeur">
            <label for="validite_valeur">Durée de validité</label>
            <input type="number" min="1" step="1" id="validite_valeur" name="validite_valeur" value="1" required>
          </div>

          <div class="champ" id="bloc_validite_unite">
            <label for="validite_unite">Unité de validité</label>
            <select id="validite_unite" name="validite_unite">
              <option value="jours">jours</option>
              <option value="semaines">semaines</option>
              <option value="mois" selected>mois</option>
              <option value="annees">années</option>
            </select>
          </div>

          <div class="champ" id="bloc_grace_valeur">
            <label for="grace_valeur">Durée de grâce</label>
            <input type="number" min="0" step="1" id="grace_valeur" name="grace_valeur" value="0">
          </div>

          <div class="champ" id="bloc_grace_unite">
            <label for="grace_unite">Unité de grâce</label>
            <select id="grace_unite" name="grace_unite">
              <option value="jours" selected>jours</option>
              <option value="semaines">semaines</option>
              <option value="mois">mois</option>
              <option value="annees">années</option>
            </select>
          </div>

          <div class="champ" id="bloc_date_expiration">
            <label for="date_expiration">Date d’expiration (manuel / avancé)</label>
            <input type="datetime-local" id="date_expiration" name="date_expiration">
          </div>

          <div class="champ" id="bloc_grace_jusqu_a">
            <label for="grace_jusqu_a">Fin de grâce (manuel / avancé)</label>
            <input type="datetime-local" id="grace_jusqu_a" name="grace_jusqu_a">
          </div>

          <div class="champ" style="grid-column:1/-1;">
            <label for="domaines_test">Domaines de test</label>
            <textarea id="domaines_test" name="domaines_test" placeholder="dev.exemple.com&#10;preprod.exemple.com&#10;ou séparés par virgules / point-virgules"></textarea>
          </div>

          <div class="champ" style="grid-column:1/-1;">
            <label for="commentaire_interne">Commentaire interne</label>
            <textarea id="commentaire_interne" name="commentaire_interne"></textarea>
          </div>
        </div>

        <div class="actions-form">
          <button type="submit">Créer la licence</button>
        </div>
      </form>

      <script>
      (function () {
        var selectType = document.getElementById('type_licence');
        var idsBlocs = [
          'bloc_validite_valeur',
          'bloc_validite_unite',
          'bloc_grace_valeur',
          'bloc_grace_unite',
          'bloc_date_expiration',
          'bloc_grace_jusqu_a'
        ];
        var champs = [
          document.getElementById('validite_valeur'),
          document.getElementById('validite_unite'),
          document.getElementById('grace_valeur'),
          document.getElementById('grace_unite'),
          document.getElementById('date_expiration'),
          document.getElementById('grace_jusqu_a')
        ];
        var boutonAccordeon = document.getElementById('bouton_accordeon_creation');
        var contenuAccordeon = document.getElementById('contenu_accordeon_creation');

        if (!selectType) {
          return;
        }

        function mettreAJourVisibiliteDates() {
          var estAbonnement = selectType.value === 'abonnement';

          idsBlocs.forEach(function (idBloc) {
            var bloc = document.getElementById(idBloc);
            if (bloc) {
              bloc.style.display = estAbonnement ? '' : 'none';
            }
          });

          if (!estAbonnement) {
            champs.forEach(function (champ) {
              if (!champ) {
                return;
              }
              if (champ.tagName === 'SELECT') {
                champ.selectedIndex = 0;
              } else if (champ.id === 'grace_valeur') {
                champ.value = '0';
              } else {
                champ.value = '';
              }
            });
          }
        }

        if (boutonAccordeon && contenuAccordeon) {
          boutonAccordeon.addEventListener('click', function () {
            var ouvert = contenuAccordeon.classList.toggle('ouvert');
            boutonAccordeon.textContent = ouvert ? 'Masquer la création d’une licence' : 'Afficher la création d’une licence';
          });
        }

        selectType.addEventListener('change', mettreAJourVisibiliteDates);
        mettreAJourVisibiliteDates();
      })();
      </script>
      </div>
    </div>

    <div class="bloc-liste">
      <h2>Demandes d’activation</h2>
      <p class="muted"><?php echo htmlspecialchars($messageDemandesActivation, ENT_QUOTES, 'UTF-8'); ?></p>

      <div class="grille">
        <div class="carte">
          <h3 class="titre">Total demandes</h3>
          <div class="valeur-mini"><?php echo (int)($statistiquesDemandesActivation['total'] ?? 0); ?></div>
        </div>
        <div class="carte">
          <h3 class="titre">En attente</h3>
          <div class="valeur-mini"><?php echo (int)($statistiquesDemandesActivation['en_attente'] ?? 0); ?></div>
        </div>
        <div class="carte">
          <h3 class="titre">Validées</h3>
          <div class="valeur-mini"><?php echo (int)($statistiquesDemandesActivation['validee'] ?? 0); ?></div>
        </div>
        <div class="carte">
          <h3 class="titre">Refusées</h3>
          <div class="valeur-mini"><?php echo (int)($statistiquesDemandesActivation['refusee'] ?? 0); ?></div>
        </div>
        <div class="carte">
          <h3 class="titre">Terminées</h3>
          <div class="valeur-mini"><?php echo (int)($statistiquesDemandesActivation['terminee'] ?? 0); ?></div>
        </div>
      </div>

      <?php if (empty($demandesActivation)): ?>
        <p class="muted">Aucune demande d’activation enregistrée pour le moment.</p>
      <?php else: ?>
        <div class="table-wrap">
          <table id="tableau-demandes-activation">
            <thead>
              <tr>
                <th class="colonne-selection">Voir</th>
                <th>ID</th>
                <th>Statut</th>
                <th>Module</th>
                <th>Version</th>
                <th>Client</th>
                <th>E-mail</th>
                <th>Commande</th>
                <th>Domaine principal</th>
                <th>Domaines de test</th>
                <th>Licence liée</th>
                <th>Date création</th>
                <th>Note interne</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($demandesActivation as $demande): ?>
                <?php $statutDemande = (string)($demande['statut'] ?? ''); ?>
                <tr>
                  <td class="colonne-selection">
                    <a class="btn-voir" href="/demandes-activation/voir?id=<?php echo (int)($demande['id_demande_activation'] ?? 0); ?>">Voir</a>
                  </td>
                  <td><?php echo (int)($demande['id_demande_activation'] ?? 0); ?></td>
                  <td>
                    <span class="badge-statut <?php echo htmlspecialchars($this->obtenirClasseStatutDemandeActivation($statutDemande), ENT_QUOTES, 'UTF-8'); ?>">
                      <?php echo htmlspecialchars($statutDemande !== '' ? $statutDemande : '—', ENT_QUOTES, 'UTF-8'); ?>
                    </span>
                  </td>
                  <td><code><?php echo htmlspecialchars((string)($demande['code_module'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></code></td>
                  <td><code><?php echo htmlspecialchars((string)($demande['version_module'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></code></td>
                  <td><?php echo htmlspecialchars((string)($demande['nom_client'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                  <td class="cellule-email"><?php echo htmlspecialchars((string)($demande['email_client'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                  <td><code><?php echo htmlspecialchars((string)($demande['numero_commande'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></code></td>
                  <td class="cellule-domaine"><?php echo htmlspecialchars((string)($demande['domaine_principal'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                  <td class="cellule-domaines-test"><?php echo nl2br(htmlspecialchars((string)($demande['domaines_test'] ?? ''), ENT_QUOTES, 'UTF-8')); ?></td>
                  <td>
                    <?php if ((int)($demande['id_licence'] ?? 0) > 0): ?>
                      <a class="btn-voir" href="/licences/voir?id=<?php echo (int)($demande['id_licence'] ?? 0); ?>">Licence #<?php echo (int)($demande['id_licence'] ?? 0); ?></a>
                    <?php else: ?>
                      —
                    <?php endif; ?>
                  </td>
                  <td class="cellule-date"><?php echo htmlspecialchars($this->formaterDate((string)($demande['date_creation'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></td>
                  <td><?php echo nl2br(htmlspecialchars((string)($demande['note_interne'] ?? ''), ENT_QUOTES, 'UTF-8')); ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>

    <div class="bloc-liste">
      <h2>Licences existantes</h2>
      <p class="muted"><?php echo htmlspecialchars($messageListe, ENT_QUOTES, 'UTF-8'); ?></p>

      <?php if (empty($licences)): ?>
        <p class="muted">Aucune licence enregistrée pour le moment.</p>
      <?php else: ?>
        <div class="barre-filtres-liste" id="barre-filtres-licences">
          <div class="champ-filtre">
            <label for="filtre_licence_recherche">Recherche libre</label>
            <input type="text" id="filtre_licence_recherche" placeholder="Clé, client, e-mail, commentaire...">
          </div>
          <div class="champ-filtre">
            <label for="filtre_licence_statut">Statut</label>
            <select id="filtre_licence_statut">
              <option value="">Tous</option>
              <option value="active">active</option>
              <option value="suspendue">suspendue</option>
              <option value="revoquee">revoquee</option>
              <option value="expiree">expiree</option>
              <option value="invalide">invalide</option>
            </select>
          </div>
          <div class="champ-filtre">
            <label for="filtre_licence_type">Type</label>
            <select id="filtre_licence_type">
              <option value="">Tous</option>
              <option value="perpetuelle">perpetuelle</option>
              <option value="abonnement">abonnement</option>
            </select>
          </div>
          <div class="champ-filtre">
            <label for="filtre_licence_module">Module</label>
            <input type="text" id="filtre_licence_module" placeholder="sr_merchant_flux">
          </div>
          <div class="champ-filtre">
            <label for="filtre_licence_domaine">Domaine</label>
            <input type="text" id="filtre_licence_domaine" placeholder="exemple.com">
          </div>
          <div class="actions-filtres">
            <button type="button" class="btn-secondaire" id="btn_reinit_filtres_licences">Réinitialiser</button>
          </div>
        </div>
        <p class="resume-filtres" id="resume_filtres_licences"></p>
        <div class="bloc-actions-lot">
          <form method="post" action="/licences/statut-lot" id="form_actions_licences_lot">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars((string)$_SESSION['sr_licences_csrf'], ENT_QUOTES, 'UTF-8'); ?>">
            <div class="barre-actions-lot">
              <label class="etiquette-tout-selectionner" for="cocher_toutes_licences">
                <input type="checkbox" id="cocher_toutes_licences" class="checkbox-toutes-licences">
                <span>Tout sélectionner (visibles)</span>
              </label>

              <select name="action_statut_lot" id="action_statut_lot">
                <option value="">Choisir une action</option>
                <option value="reactiver">Réactiver</option>
                <option value="suspendre">Suspendre</option>
                <option value="revoquer">Révoquer</option>
              </select>

              <button type="submit" class="btn-appliquer-lot">Appliquer</button>
              <span class="resume-selection" id="resume_selection_licences">0 licence sélectionnée.</span>
            </div>
            <div id="ids_licence_selectionnes"></div>
          </form>
        </div>
        <div class="table-wrap">
          <table id="tableau-licences">
                        <thead>
              <tr>
                <th class="colonne-selection">Sélection / Voir</th>
                <th>ID</th>
                <th>Clé</th>
                <th>Module</th>
                <th>Statut</th>
                <th>Client</th>
                <th>E-mail</th>
                <th>Domaine principal</th>
                <th>Domaines de test</th>
                <th>Version max</th>
                <th>Type</th>
                <th>Expire le</th>
                <th>Fin de grâce</th>
                <th>Mise à jour</th>
                <th>Commentaire interne</th>
                <th>Date création</th>
                <th>Date activation</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($licences as $licence): ?>
                <?php $statut = (string)($licence['statut'] ?? ''); ?>
                <tr
                  class="ligne-licence"
                  data-filtre-global="<?php echo htmlspecialchars(implode(' ', [
                      (string)($licence['id_licence'] ?? ''),
                      (string)($licence['cle_licence'] ?? ''),
                      (string)($licence['code_module'] ?? ''),
                      (string)($licence['statut'] ?? ''),
                      (string)($licence['nom_client'] ?? ''),
                      (string)($licence['email_client'] ?? ''),
                      (string)($licence['domaine_principal'] ?? ''),
                      (string)($licence['domaines_test_actifs_texte'] ?? ''),
                      (string)($licence['version_max_autorisee'] ?? ''),
                      (string)($licence['type_licence'] ?? ''),
                      (string)($licence['commentaire_interne'] ?? '')
                  ]), ENT_QUOTES, 'UTF-8'); ?>"
                  data-filtre-statut="<?php echo htmlspecialchars((string)($licence['statut'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                  data-filtre-type="<?php echo htmlspecialchars((string)($licence['type_licence'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                  data-filtre-module="<?php echo htmlspecialchars((string)($licence['code_module'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                  data-filtre-domaine="<?php echo htmlspecialchars(trim((string)($licence['domaine_principal'] ?? '') . ' ' . (string)($licence['domaines_test_actifs_texte'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>"
                >
                  <td class="colonne-selection">
                    <div class="ligne-selection">
                      <input type="checkbox" class="checkbox-ligne-licence" value="<?php echo (int)($licence['id_licence'] ?? 0); ?>" aria-label="Sélectionner la licence #<?php echo (int)($licence['id_licence'] ?? 0); ?>">
                      <a class="btn-voir" href="/licences/voir?id=<?php echo (int)($licence['id_licence'] ?? 0); ?>">Voir</a>
                    </div>
                  </td>
                  <td><?php echo (int)($licence['id_licence'] ?? 0); ?></td>
                  <td class="cellule-cle"><code><?php echo htmlspecialchars((string)($licence['cle_licence'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></code></td>
                  <td><code><?php echo htmlspecialchars((string)($licence['code_module'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></code></td>
                  <td>
                    <span class="badge-statut <?php echo htmlspecialchars($this->obtenirClasseStatut($statut), ENT_QUOTES, 'UTF-8'); ?>">
                      <?php echo htmlspecialchars($statut !== '' ? $statut : '—', ENT_QUOTES, 'UTF-8'); ?>
                    </span>
                  </td>
                  <td><?php echo htmlspecialchars((string)($licence['nom_client'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                  <td class="cellule-email"><?php echo htmlspecialchars((string)($licence['email_client'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                  <td class="cellule-domaine"><?php echo htmlspecialchars((string)($licence['domaine_principal'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                  <td class="cellule-domaines-test"><?php echo htmlspecialchars((string)($licence['domaines_test_actifs_texte'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                  <td><?php echo htmlspecialchars((string)($licence['version_max_autorisee'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                  <td><?php echo htmlspecialchars((string)($licence['type_licence'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                  <td class="cellule-date"><?php echo htmlspecialchars($this->formaterDate((string)($licence['date_expiration'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></td>
                  <td class="cellule-date"><?php echo htmlspecialchars($this->formaterDate((string)($licence['grace_jusqu_a'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></td>
                  <td class="cellule-date"><?php echo htmlspecialchars($this->formaterDate((string)($licence['date_maj'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></td>
                  <td><?php echo nl2br(htmlspecialchars((string)($licence['commentaire_interne'] ?? ''), ENT_QUOTES, 'UTF-8')); ?></td>
                  <td class="cellule-date"><?php echo htmlspecialchars($this->formaterDate((string)($licence['date_creation'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></td>
                  <td class="cellule-date"><?php echo htmlspecialchars($this->formaterDate((string)($licence['date_activation'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></td>
</tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <script>
        (function () {
          var champRecherche = document.getElementById('filtre_licence_recherche');
          var champStatut = document.getElementById('filtre_licence_statut');
          var champType = document.getElementById('filtre_licence_type');
          var champModule = document.getElementById('filtre_licence_module');
          var champDomaine = document.getElementById('filtre_licence_domaine');
          var boutonReset = document.getElementById('btn_reinit_filtres_licences');
          var resume = document.getElementById('resume_filtres_licences');
          var lignes = document.querySelectorAll('#tableau-licences tbody tr.ligne-licence');

          if (!champRecherche || !champStatut || !champType || !champModule || !champDomaine || !boutonReset || !resume || !lignes.length) {
            return;
          }

          function normaliser(valeur) {
            return (valeur || '').toString().toLowerCase().trim();
          }

          function appliquerFiltres() {
            var recherche = normaliser(champRecherche.value);
            var statut = normaliser(champStatut.value);
            var type = normaliser(champType.value);
            var module = normaliser(champModule.value);
            var domaine = normaliser(champDomaine.value);
            var visibles = 0;

            lignes.forEach(function (ligne) {
              var filtreGlobal = normaliser(ligne.getAttribute('data-filtre-global'));
              var filtreStatut = normaliser(ligne.getAttribute('data-filtre-statut'));
              var filtreType = normaliser(ligne.getAttribute('data-filtre-type'));
              var filtreModule = normaliser(ligne.getAttribute('data-filtre-module'));
              var filtreDomaine = normaliser(ligne.getAttribute('data-filtre-domaine'));

              var ok = true;

              if (recherche !== '' && filtreGlobal.indexOf(recherche) === -1) {
                ok = false;
              }
              if (ok && statut !== '' && filtreStatut !== statut) {
                ok = false;
              }
              if (ok && type !== '' && filtreType !== type) {
                ok = false;
              }
              if (ok && module !== '' && filtreModule.indexOf(module) === -1) {
                ok = false;
              }
              if (ok && domaine !== '' && filtreDomaine.indexOf(domaine) === -1) {
                ok = false;
              }

              ligne.classList.toggle('ligne-masquee', !ok);
              if (ok) {
                visibles += 1;
              }
            });

            resume.textContent = visibles + ' licence(s) affichée(s) sur ' + lignes.length + '.';
          }

          [champRecherche, champStatut, champType, champModule, champDomaine].forEach(function (champ) {
            champ.addEventListener('input', appliquerFiltres);
            champ.addEventListener('change', appliquerFiltres);
          });

          boutonReset.addEventListener('click', function () {
            champRecherche.value = '';
            champStatut.value = '';
            champType.value = '';
            champModule.value = '';
            champDomaine.value = '';
            appliquerFiltres();
          });

          appliquerFiltres();
        })();
        </script>
      <?php endif; ?>
    </div>
  </div>

  <script>
  (function () {
    var formulaireLot = document.getElementById('form_actions_licences_lot');
    if (!formulaireLot) {
      return;
    }

    var cases = Array.prototype.slice.call(document.querySelectorAll('.checkbox-ligne-licence'));
    var caseToutes = document.getElementById('cocher_toutes_licences');
    var selectAction = document.getElementById('action_statut_lot');
    var resumeSelection = document.getElementById('resume_selection_licences');
    var conteneurIds = document.getElementById('ids_licence_selectionnes');

    if (!cases.length || !caseToutes || !selectAction || !resumeSelection || !conteneurIds) {
      return;
    }

    function ligneVisible(caseACocher) {
      var ligne = caseACocher.closest('tr');
      return !ligne || !ligne.classList.contains('ligne-masquee');
    }

    function casesVisibles() {
      return cases.filter(function (caseACocher) {
        return ligneVisible(caseACocher);
      });
    }

    function casesCochees() {
      return cases.filter(function (caseACocher) {
        return caseACocher.checked;
      });
    }

    function mettreAJourResumeSelection() {
      var visibles = casesVisibles();
      var visiblesCochees = visibles.filter(function (caseACocher) {
        return caseACocher.checked;
      });
      var totalCochees = casesCochees().length;

      caseToutes.checked = visibles.length > 0 && visiblesCochees.length === visibles.length;
      caseToutes.indeterminate = visiblesCochees.length > 0 && visiblesCochees.length < visibles.length;

      resumeSelection.textContent = totalCochees + ' licence(s) sélectionnée(s).';
    }

    caseToutes.addEventListener('change', function () {
      var cocher = caseToutes.checked;
      casesVisibles().forEach(function (caseACocher) {
        caseACocher.checked = cocher;
      });
      mettreAJourResumeSelection();
    });

    cases.forEach(function (caseACocher) {
      caseACocher.addEventListener('change', mettreAJourResumeSelection);
    });

    ['filtre_licence_recherche', 'filtre_licence_statut', 'filtre_licence_type', 'filtre_licence_module', 'filtre_licence_domaine'].forEach(function (idChamp) {
      var champ = document.getElementById(idChamp);
      if (!champ) {
        return;
      }
      champ.addEventListener('input', mettreAJourResumeSelection);
      champ.addEventListener('change', mettreAJourResumeSelection);
    });

    formulaireLot.addEventListener('submit', function (event) {
      var selection = casesCochees().map(function (caseACocher) {
        return caseACocher.value;
      });

      if (!selection.length) {
        event.preventDefault();
        alert('Coche au moins une licence avant d’appliquer une action.');
        return;
      }

      if (!selectAction.value) {
        event.preventDefault();
        alert('Choisis une action avant de valider.');
        return;
      }

      if (selectAction.value === 'reactiver') {
        event.preventDefault();
        window.location.href = '/licences/reactiver?ids=' + encodeURIComponent(selection.join(','));
        return;
      }

      var libelleAction = selectAction.options[selectAction.selectedIndex].text || selectAction.value;
      if (!window.confirm('Confirmer l’action « ' + libelleAction + ' » sur ' + selection.length + ' licence(s) ?')) {
        event.preventDefault();
        return;
      }

      conteneurIds.innerHTML = '';
      selection.forEach(function (idLicence) {
        var champ = document.createElement('input');
        champ.type = 'hidden';
        champ.name = 'ids_licence[]';
        champ.value = idLicence;
        conteneurIds.appendChild(champ);
      });
    });

    mettreAJourResumeSelection();
  })();
  </script>

</body>
</html>
        <?php
        exit;
    }


    public function gererReactivationLicences(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $csrfSession = (string)($_SESSION['sr_licences_csrf'] ?? '');
            $csrfFormulaire = (string)($_POST['csrf_token'] ?? '');

            if ($csrfSession === '' || !hash_equals($csrfSession, $csrfFormulaire)) {
                $_SESSION['sr_licences_message_erreur'] = 'Jeton de sécurité invalide.';
                header('Location: /');
                exit;
            }

            $idsBruts = $_POST['ids_licence'] ?? [];
            if (!is_array($idsBruts)) {
                $idsBruts = [$idsBruts];
            }

            $idsLicence = array_values(array_unique(array_filter(array_map('intval', $idsBruts), static fn(int $id): bool => $id > 0)));

            try {
                $pdo = BaseDeDonnees::creerDepuisConfig($this->config);
                $service = new ServiceLicence(new LicenceRepository($pdo));
                $validiteValeur = trim((string)($_POST['validite_valeur'] ?? ''));
                $validiteUnite = trim((string)($_POST['validite_unite'] ?? 'mois'));
                $graceValeur = trim((string)($_POST['grace_valeur'] ?? ''));
                $graceUnite = trim((string)($_POST['grace_unite'] ?? 'jours'));

                if ($validiteValeur === '') {
                    $validiteValeur = '1';
                }
                if ($validiteUnite === '') {
                    $validiteUnite = 'mois';
                }
                if ($graceUnite === '') {
                    $graceUnite = 'jours';
                }

                $resultats = $service->reactiverLicencesAvecPeriode($idsLicence, [
                    'validite_valeur' => $validiteValeur,
                    'validite_unite' => $validiteUnite,
                    'grace_valeur' => $graceValeur,
                    'grace_unite' => $graceUnite,
                ]);

                $_SESSION['sr_licences_message_succes'] =
                    count($resultats) . ' licence(s) réactivée(s) avec période de validité.';
                header('Location: /');
                exit;
            } catch (Throwable $e) {
                $_SESSION['sr_licences_message_erreur'] = 'Réactivation impossible : ' . $e->getMessage();
                header('Location: /licences/reactiver?ids=' . implode(',', $idsLicence));
                exit;
            }
        }

        $idsTexte = trim((string)($_GET['ids'] ?? ''));
        $idsLicence = [];
        if ($idsTexte !== '') {
            foreach (preg_split('/[,\s;]+/', $idsTexte) ?: [] as $element) {
                $id = (int)$element;
                if ($id > 0) {
                    $idsLicence[] = $id;
                }
            }
        }

        $idsLicence = array_values(array_unique($idsLicence));

        if (empty($idsLicence)) {
            $_SESSION['sr_licences_message_erreur'] = 'Aucune licence sélectionnée pour la réactivation.';
            header('Location: /');
            exit;
        }

        if (empty($_SESSION['sr_licences_csrf'])) {
            $_SESSION['sr_licences_csrf'] = bin2hex(random_bytes(16));
        }

        $messageErreur = (string)($_SESSION['sr_licences_message_erreur'] ?? '');
        unset($_SESSION['sr_licences_message_erreur']);

        try {
            $pdo = BaseDeDonnees::creerDepuisConfig($this->config);
            $service = new ServiceLicence(new LicenceRepository($pdo));
            $licences = [];
            foreach ($idsLicence as $idLicence) {
                $licences[] = $service->obtenirLicencePourAdmin($idLicence);
            }
        } catch (Throwable $e) {
            $_SESSION['sr_licences_message_erreur'] = 'Chargement impossible : ' . $e->getMessage();
            header('Location: /');
            exit;
        }

        header('Content-Type: text/html; charset=UTF-8');
        ?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <title>Réactiver des licences - SR Licences</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    body{margin:0;font-family:Arial,sans-serif;background:#f8fafc;color:#111827}
    .page{max-width:1100px;margin:32px auto;background:#fff;border:1px solid #dbe3ea;border-radius:16px;padding:24px;box-shadow:0 6px 24px rgba(0,0,0,.06)}
    .barre{display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap}
    .muted{color:#4b5563}
    .alerte-ko{margin:16px 0;padding:12px 14px;border-radius:12px;background:#fef2f2;color:#991b1b;border:1px solid #fecaca}
    .formulaire{margin-top:22px;padding:18px;border:1px solid #dbe3ea;border-radius:14px;background:#fff}
    .grille-form{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:14px}
    .champ label{display:block;margin:0 0 6px 0;font-weight:700}
    .champ input,.champ select{width:100%;box-sizing:border-box;padding:11px 12px;border:1px solid #cbd5e1;border-radius:10px;font:inherit;background:#fff}
    .bloc-actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:18px}
    .bouton-principal,.bouton-secondaire{display:inline-block;padding:11px 14px;border-radius:10px;font-weight:700;text-decoration:none;cursor:pointer}
    .bouton-principal{border:0;background:#111827;color:#fff}
    .bouton-secondaire{border:1px solid #cbd5e1;background:#fff;color:#111827}
    table{width:100%;border-collapse:collapse;font-size:14px;margin-top:16px}
    th,td{padding:10px 12px;border-bottom:1px solid #e5e7eb;text-align:left;vertical-align:top}
    th{background:#f8fafc}
    code{background:#f1f5f9;border:1px solid #cbd5e1;padding:2px 6px;border-radius:6px}
  </style>
</head>
<body>
  <div class="page">
    <div class="barre">
      <div>
        <h1 style="margin:0 0 8px 0;">Réactiver des licences</h1>
        <p class="muted" style="margin:0;">La durée est obligatoire pour les licences abonnement. Les licences perpétuelles seront simplement remises à l’état actif.</p>
      </div>
      <div>
        <a class="bouton-secondaire" href="/">Retour à la liste</a>
      </div>
    </div>

    <?php if ($messageErreur !== ''): ?>
      <div class="alerte-ko"><?php echo htmlspecialchars($messageErreur, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <table>
      <thead>
        <tr>
          <th>ID</th>
          <th>Clé</th>
          <th>Type</th>
          <th>Statut</th>
          <th>Expiration actuelle</th>
          <th>Grâce actuelle</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($licences as $licence): ?>
          <tr>
            <td><?php echo (int)($licence['id_licence'] ?? 0); ?></td>
            <td><code><?php echo htmlspecialchars((string)($licence['cle_licence'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></code></td>
            <td><?php echo htmlspecialchars((string)($licence['type_licence'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?php echo htmlspecialchars((string)($licence['statut'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?php echo htmlspecialchars($this->formaterDate((string)($licence['date_expiration'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?php echo htmlspecialchars($this->formaterDate((string)($licence['grace_jusqu_a'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <div class="formulaire">
      <form method="post" action="/licences/reactiver">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars((string)$_SESSION['sr_licences_csrf'], ENT_QUOTES, 'UTF-8'); ?>">
        <?php foreach ($idsLicence as $idLicence): ?>
          <input type="hidden" name="ids_licence[]" value="<?php echo (int)$idLicence; ?>">
        <?php endforeach; ?>

        <div class="grille-form">
          <div class="champ">
            <label for="validite_valeur">Durée de validité</label>
            <input type="number" min="1" step="1" id="validite_valeur" name="validite_valeur" value="1" required>
          </div>

          <div class="champ">
            <label for="validite_unite">Unité de validité</label>
            <select id="validite_unite" name="validite_unite">
              <option value="jours">jours</option>
              <option value="semaines">semaines</option>
              <option value="mois" selected>mois</option>
              <option value="annees">années</option>
            </select>
          </div>

          <div class="champ">
            <label for="grace_valeur">Durée de grâce</label>
            <input type="number" min="0" step="1" id="grace_valeur" name="grace_valeur" value="0">
          </div>

          <div class="champ">
            <label for="grace_unite">Unité de grâce</label>
            <select id="grace_unite" name="grace_unite">
              <option value="jours" selected>jours</option>
              <option value="semaines">semaines</option>
              <option value="mois">mois</option>
              <option value="annees">années</option>
            </select>
          </div>
        </div>

        <div class="bloc-actions">
          <button type="submit" class="bouton-principal">Réactiver les licences sélectionnées</button>
          <a class="bouton-secondaire" href="/">Annuler</a>
        </div>
      </form>
    </div>
  </div>
</body>
</html>
        <?php
        exit;
    }


    public function afficherDemandeActivation(): void
    {
        $idDemandeActivation = (int)($_GET['id'] ?? 0);

        try {
            $pdo = BaseDeDonnees::creerDepuisConfig($this->config);
            $serviceDemandesActivation = new ServiceDemandeActivation(
                new DemandeActivationRepository($pdo),
                new LicenceRepository($pdo)
            );
            $demande = $serviceDemandesActivation->obtenirDemandePourAdmin($idDemandeActivation);
        } catch (Throwable $e) {
            $_SESSION['sr_licences_message_erreur'] = 'Chargement impossible : ' . $e->getMessage();
            header('Location: /');
            exit;
        }

        header('Content-Type: text/html; charset=UTF-8');
        ?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <title>Demande d’activation #<?php echo (int)($demande['id_demande_activation'] ?? 0); ?> - SR Licences</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    body{margin:0;font-family:Arial,sans-serif;background:#f8fafc;color:#111827}
    .page{max-width:1180px;margin:32px auto;background:#fff;border:1px solid #dbe3ea;border-radius:16px;padding:24px;box-shadow:0 6px 24px rgba(0,0,0,.06)}
    .barre{display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap}
    .badge-statut{display:inline-block;padding:6px 10px;border-radius:999px;border:1px solid;font-weight:700;font-size:12px;line-height:1.2;white-space:nowrap}
    .statut-active{background:#dcfce7;color:#166534;border-color:#86efac}
    .statut-suspendue{background:#fff7ed;color:#9a3412;border-color:#fdba74}
    .statut-revoquee{background:#fee2e2;color:#991b1b;border-color:#fca5a5}
    .statut-expiree{background:#fef3c7;color:#92400e;border-color:#fcd34d}
    .statut-invalide{background:#e5e7eb;color:#374151;border-color:#cbd5e1}
    .muted{color:#4b5563}
    .grille-fiche{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:14px;margin-top:20px}
    .carte-fiche{border:1px solid #dbe3ea;border-radius:14px;padding:16px;background:#fff}
    .libelle{margin:0 0 8px 0;font-size:13px;font-weight:700;color:#4b5563;text-transform:uppercase;letter-spacing:.02em}
    .contenu{font-size:16px;line-height:1.45;word-break:break-word}
    .contenu code{background:#f1f5f9;border:1px solid #cbd5e1;padding:2px 6px;border-radius:6px}
    .contenu.commentaire{white-space:pre-wrap}
    .formulaire{margin-top:24px;padding:18px;border:1px solid #dbe3ea;border-radius:14px;background:#fbfdff}
    .grille-form{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:14px}
    .champ label{display:block;margin-bottom:6px;font-weight:700}
    .champ input,.champ select,.champ textarea{width:100%;box-sizing:border-box;padding:10px 12px;border:1px solid #cbd5e1;border-radius:10px;font:inherit}
    .champ textarea{min-height:96px;resize:vertical}
    .actions-form{margin-top:16px}
    .actions-form button{padding:11px 16px;border:0;border-radius:10px;background:#111827;color:#fff;font-weight:700;cursor:pointer}
    .bloc-actions{margin-top:22px;display:flex;gap:10px;flex-wrap:wrap}
    a.bouton-retour{display:inline-block;padding:10px 14px;border-radius:10px;background:#111827;color:#fff;text-decoration:none;font-weight:700}
  </style>
</head>
<body>
  <div class="page">
    <div class="barre">
      <div>
        <h1 style="margin:0 0 8px 0;">Demande d’activation #<?php echo (int)($demande['id_demande_activation'] ?? 0); ?></h1>
        <p class="muted" style="margin:0;">Consultation détaillée d’une demande d’activation.</p>
      </div>
      <div>
        <a class="bouton-retour" href="/">Retour à la liste</a>
      </div>
    </div>

    <?php $statutDemande = (string)($demande['statut'] ?? ''); ?>

    <div class="grille-fiche">
      <div class="carte-fiche"><div class="libelle">ID</div><div class="contenu"><?php echo (int)($demande['id_demande_activation'] ?? 0); ?></div></div>
      <div class="carte-fiche"><div class="libelle">Statut</div><div class="contenu"><span class="badge-statut <?php echo htmlspecialchars($this->obtenirClasseStatutDemandeActivation($statutDemande), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($statutDemande !== '' ? $statutDemande : '—', ENT_QUOTES, 'UTF-8'); ?></span></div></div>
      <div class="carte-fiche"><div class="libelle">Module</div><div class="contenu"><code><?php echo htmlspecialchars((string)($demande['code_module'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></code></div></div>
      <div class="carte-fiche"><div class="libelle">Version module</div><div class="contenu"><code><?php echo htmlspecialchars((string)($demande['version_module'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></code></div></div>
      <div class="carte-fiche"><div class="libelle">Nom client</div><div class="contenu"><?php echo htmlspecialchars((string)($demande['nom_client'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></div></div>
      <div class="carte-fiche"><div class="libelle">E-mail client</div><div class="contenu"><?php echo htmlspecialchars((string)($demande['email_client'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></div></div>
      <div class="carte-fiche"><div class="libelle">N° de commande</div><div class="contenu"><code><?php echo htmlspecialchars((string)($demande['numero_commande'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></code></div></div>
      <div class="carte-fiche"><div class="libelle">Domaine principal</div><div class="contenu"><?php echo htmlspecialchars((string)($demande['domaine_principal'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></div></div>
      <div class="carte-fiche" style="grid-column:1/-1;"><div class="libelle">Domaines de test</div><div class="contenu commentaire"><?php echo nl2br(htmlspecialchars((string)($demande['domaines_test'] ?? ''), ENT_QUOTES, 'UTF-8')); ?></div></div>
      <div class="carte-fiche"><div class="libelle">Date création</div><div class="contenu"><?php echo htmlspecialchars($this->formaterDate((string)($demande['date_creation'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></div></div>
      <div class="carte-fiche"><div class="libelle">Date validation</div><div class="contenu"><?php echo htmlspecialchars($this->formaterDate((string)($demande['date_validation'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></div></div>
      <div class="carte-fiche"><div class="libelle">Date refus</div><div class="contenu"><?php echo htmlspecialchars($this->formaterDate((string)($demande['date_refus'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></div></div>
      <div class="carte-fiche"><div class="libelle">Date consommation</div><div class="contenu"><?php echo htmlspecialchars($this->formaterDate((string)($demande['date_consommation'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></div></div>
      <div class="carte-fiche"><div class="libelle">Dernière mise à jour</div><div class="contenu"><?php echo htmlspecialchars($this->formaterDate((string)($demande['date_maj'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></div></div>
      <div class="carte-fiche"><div class="libelle">Licence liée</div><div class="contenu"><?php if ((int)($demande['id_licence'] ?? 0) > 0): ?><a class="bouton-retour" href="/licences/voir?id=<?php echo (int)($demande['id_licence'] ?? 0); ?>">Voir la licence #<?php echo (int)($demande['id_licence'] ?? 0); ?></a><?php else: ?>—<?php endif; ?></div></div>
      <div class="carte-fiche" style="grid-column:1/-1;"><div class="libelle">Note interne</div><div class="contenu commentaire"><?php echo nl2br(htmlspecialchars((string)($demande['note_interne'] ?? ''), ENT_QUOTES, 'UTF-8')); ?></div></div>
    </div>

    <?php if (in_array($statutDemande, ['en_attente', 'refusee'], true)): ?>
      <form method="post" action="/demandes-activation/decision" class="formulaire">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars((string)$_SESSION['sr_licences_csrf'], ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="id_demande_activation" value="<?php echo (int)($demande['id_demande_activation'] ?? 0); ?>">
        <input type="hidden" name="action_decision" value="valider">
        <h2 style="margin-top:0;">Valider la demande et créer la licence</h2>
        <div class="grille-form">
          <div class="champ"><label>Type de licence</label><select name="type_licence"><option value="perpetuelle">perpétuelle</option><option value="abonnement">abonnement</option></select></div>
          <div class="champ"><label>Version max autorisée</label><input type="text" name="version_max_autorisee" value="<?php echo htmlspecialchars((string)($demande['version_module'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" placeholder="2.6.*"></div>
          <div class="champ"><label>Durée de validité</label><input type="number" min="1" step="1" name="validite_valeur" value="12"></div>
          <div class="champ"><label>Unité de validité</label><select name="validite_unite"><option value="jours">jours</option><option value="semaines">semaines</option><option value="mois" selected>mois</option><option value="annees">années</option></select></div>
          <div class="champ"><label>Durée de grâce</label><input type="number" min="0" step="1" name="grace_valeur" value="7"></div>
          <div class="champ"><label>Unité de grâce</label><select name="grace_unite"><option value="jours" selected>jours</option><option value="semaines">semaines</option><option value="mois">mois</option><option value="annees">années</option></select></div>
          <div class="champ" style="grid-column:1/-1;"><label>Note interne de validation</label><textarea name="note_interne" placeholder="Note interne facultative"></textarea></div>
        </div>
        <div class="actions-form"><button type="submit">Valider la demande et créer la licence</button></div>
      </form>
    <?php endif; ?>

    <?php if (in_array($statutDemande, ['en_attente', 'validee'], true)): ?>
      <form method="post" action="/demandes-activation/decision" class="formulaire">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars((string)$_SESSION['sr_licences_csrf'], ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="id_demande_activation" value="<?php echo (int)($demande['id_demande_activation'] ?? 0); ?>">
        <input type="hidden" name="action_decision" value="refuser">
        <h2 style="margin-top:0;">Refuser la demande</h2>
        <div class="grille-form">
          <div class="champ" style="grid-column:1/-1;"><label>Motif / note interne de refus</label><textarea name="note_interne" placeholder="Motif facultatif"></textarea></div>
        </div>
        <div class="actions-form"><button type="submit">Refuser la demande</button></div>
      </form>
    <?php endif; ?>

    <div class="bloc-actions">
      <a class="bouton-retour" href="/">Retour à la liste</a>
    </div>
  </div>
</body>
</html>
        <?php
        exit;
    }

    public function afficherLicence(): void
    {
        $idLicence = (int)($_GET['id'] ?? 0);

        try {
            $pdo = BaseDeDonnees::creerDepuisConfig($this->config);
            $service = new ServiceLicence(new LicenceRepository($pdo));
            $licence = $service->obtenirLicencePourAdmin($idLicence);
        } catch (Throwable $e) {
            $_SESSION['sr_licences_message_erreur'] = 'Consultation impossible : ' . $e->getMessage();
            header('Location: /');
            exit;
        }

        header('Content-Type: text/html; charset=UTF-8');
        ?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <title>Fiche licence #<?php echo (int)($licence['id_licence'] ?? 0); ?> - SR Licences</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    body{margin:0;font-family:Arial,sans-serif;background:#f8fafc;color:#111827}
    .page{max-width:1180px;margin:32px auto;background:#fff;border:1px solid #dbe3ea;border-radius:16px;padding:24px;box-shadow:0 6px 24px rgba(0,0,0,.06)}
    .barre{display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap}
    .badge-statut{display:inline-block;padding:6px 10px;border-radius:999px;border:1px solid;font-weight:700;font-size:12px;line-height:1.2;white-space:nowrap}
    .statut-active{background:#dcfce7;color:#166534;border-color:#86efac}
    .statut-suspendue{background:#fff7ed;color:#9a3412;border-color:#fdba74}
    .statut-revoquee{background:#fee2e2;color:#991b1b;border-color:#fca5a5}
    .statut-expiree{background:#fef3c7;color:#92400e;border-color:#fcd34d}
    .statut-invalide{background:#e5e7eb;color:#374151;border-color:#cbd5e1}
    .grille-fiche{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:14px;margin-top:20px}
    .carte-fiche{border:1px solid #dbe3ea;border-radius:14px;padding:16px;background:#fff}
    .libelle{margin:0 0 8px 0;font-size:13px;font-weight:700;color:#4b5563;text-transform:uppercase;letter-spacing:.02em}
    .contenu{font-size:16px;line-height:1.45;word-break:break-word}
    .contenu code{background:#f1f5f9;border:1px solid #cbd5e1;padding:2px 6px;border-radius:6px}
    .contenu.commentaire{white-space:pre-wrap}
    .bloc-retour{margin-top:22px}
    a.bouton-retour{display:inline-block;padding:10px 14px;border-radius:10px;background:#111827;color:#fff;text-decoration:none;font-weight:700}
    .muted{color:#4b5563}
  </style>
</head>
<body>
  <div class="page">
    <div class="barre">
      <div>
        <h1 style="margin:0 0 8px 0;">Fiche licence #<?php echo (int)($licence['id_licence'] ?? 0); ?></h1>
        <p class="muted" style="margin:0;">Consultation détaillée d’une licence enregistrée.</p>
      </div>
      <div>
        <a class="bouton-retour" href="/">Retour à la liste</a>
      </div>
    </div>

    <?php $statut = (string)($licence['statut'] ?? ''); ?>
    <?php $typeLicence = (string)($licence['type_licence'] ?? ''); ?>
    <?php $typeLicenceLibelle = match ($typeLicence) {
        'perpetuelle' => 'perpétuelle',
        'abonnement' => 'abonnement',
        default => ($typeLicence !== '' ? $typeLicence : '—'),
    }; ?>

    <div class="grille-fiche">
      <div class="carte-fiche">
        <div class="libelle">ID</div>
        <div class="contenu"><?php echo (int)($licence['id_licence'] ?? 0); ?></div>
      </div>

      <div class="carte-fiche">
        <div class="libelle">Clé de licence</div>
        <div class="contenu"><code><?php echo htmlspecialchars((string)($licence['cle_licence'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></code></div>
      </div>

      <div class="carte-fiche">
        <div class="libelle">Module</div>
        <div class="contenu"><code><?php echo htmlspecialchars((string)($licence['code_module'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></code></div>
      </div>

      <div class="carte-fiche">
        <div class="libelle">Statut</div>
        <div class="contenu">
          <span class="badge-statut <?php echo htmlspecialchars($this->obtenirClasseStatut($statut), ENT_QUOTES, 'UTF-8'); ?>">
            <?php echo htmlspecialchars($statut !== '' ? $statut : '—', ENT_QUOTES, 'UTF-8'); ?>
          </span>
        </div>
      </div>

      <div class="carte-fiche">
        <div class="libelle">Type de licence</div>
        <div class="contenu"><?php echo htmlspecialchars($typeLicenceLibelle, ENT_QUOTES, 'UTF-8'); ?></div>
      </div>

      <div class="carte-fiche">
        <div class="libelle">Nom client</div>
        <div class="contenu"><?php echo htmlspecialchars((string)($licence['nom_client'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></div>
      </div>

      <div class="carte-fiche">
        <div class="libelle">E-mail client</div>
        <div class="contenu"><?php echo htmlspecialchars((string)($licence['email_client'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></div>
      </div>

      <div class="carte-fiche">
        <div class="libelle">Domaine principal</div>
        <div class="contenu"><?php echo htmlspecialchars((string)($licence['domaine_principal'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></div>
      </div>

      <div class="carte-fiche">
        <div class="libelle">Domaines de test</div>
        <div class="contenu"><?php echo htmlspecialchars((string)($licence['domaines_test_actifs_texte'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></div>
      </div>

      <div class="carte-fiche">
        <div class="libelle">Version max autorisée</div>
        <div class="contenu"><?php echo htmlspecialchars((string)($licence['version_max_autorisee'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></div>
      </div>

      <div class="carte-fiche">
        <div class="libelle">Date de création</div>
        <div class="contenu"><?php echo htmlspecialchars($this->formaterDate((string)($licence['date_creation'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></div>
      </div>

      <div class="carte-fiche">
        <div class="libelle">Date d’activation</div>
        <div class="contenu"><?php echo htmlspecialchars($this->formaterDate((string)($licence['date_activation'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></div>
      </div>

      <div class="carte-fiche">
        <div class="libelle">Date d’expiration</div>
        <div class="contenu"><?php echo htmlspecialchars($this->formaterDate((string)($licence['date_expiration'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></div>
      </div>

      <div class="carte-fiche">
        <div class="libelle">Fin de grâce</div>
        <div class="contenu"><?php echo htmlspecialchars($this->formaterDate((string)($licence['grace_jusqu_a'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></div>
      </div>

      <div class="carte-fiche">
        <div class="libelle">Dernière mise à jour</div>
        <div class="contenu"><?php echo htmlspecialchars($this->formaterDate((string)($licence['date_maj'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></div>
      </div>

      <div class="carte-fiche" style="grid-column:1/-1;">
        <div class="libelle">Commentaire interne</div>
        <div class="contenu commentaire"><?php echo nl2br(htmlspecialchars((string)($licence['commentaire_interne'] ?? ''), ENT_QUOTES, 'UTF-8')); ?></div>
      </div>
    </div>

    <div class="bloc-retour" style="display:flex;gap:10px;flex-wrap:wrap;">
      <a class="bouton-retour" href="/licences/modifier?id=<?php echo (int)($licence['id_licence'] ?? 0); ?>">Modifier la fiche</a>
      <a class="bouton-retour" href="/">Retour à la liste</a>
    </div>
  </div>
</body>
</html>
        <?php
        exit;
    }

    public function gererModificationLicence(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $csrfSession = (string)($_SESSION['sr_licences_csrf'] ?? '');
            $csrfFormulaire = (string)($_POST['csrf_token'] ?? '');

            if ($csrfSession === '' || !hash_equals($csrfSession, $csrfFormulaire)) {
                $_SESSION['sr_licences_message_erreur'] = 'Jeton de sécurité invalide.';
                header('Location: /');
                exit;
            }

            $idLicence = (int)($_POST['id_licence'] ?? 0);

            try {
                $pdo = BaseDeDonnees::creerDepuisConfig($this->config);
                $service = new ServiceLicence(new LicenceRepository($pdo));
                $service->modifierLicence($idLicence, [
                    'type_licence' => (string)($_POST['type_licence'] ?? 'perpetuelle'),
                    'nom_client' => (string)($_POST['nom_client'] ?? ''),
                    'email_client' => (string)($_POST['email_client'] ?? ''),
                    'domaine_principal' => (string)($_POST['domaine_principal'] ?? ''),
                    'version_max_autorisee' => (string)($_POST['version_max_autorisee'] ?? ''),
                    'validite_valeur' => (string)($_POST['validite_valeur'] ?? ''),
                    'validite_unite' => (string)($_POST['validite_unite'] ?? 'mois'),
                    'grace_valeur' => (string)($_POST['grace_valeur'] ?? ''),
                    'grace_unite' => (string)($_POST['grace_unite'] ?? 'jours'),
                    'date_expiration' => (string)($_POST['date_expiration'] ?? ''),
                    'grace_jusqu_a' => (string)($_POST['grace_jusqu_a'] ?? ''),
                    'domaines_test' => (string)($_POST['domaines_test'] ?? ''),
                    'commentaire_interne' => (string)($_POST['commentaire_interne'] ?? ''),
                ]);

                $_SESSION['sr_licences_message_succes'] = 'Licence #' . $idLicence . ' modifiée avec succès.';
                header('Location: /licences/voir?id=' . $idLicence);
                exit;
            } catch (Throwable $e) {
                $_SESSION['sr_licences_message_erreur'] = 'Modification impossible : ' . $e->getMessage();
                header('Location: /licences/modifier?id=' . $idLicence);
                exit;
            }
        }

        $idLicence = (int)($_GET['id'] ?? 0);
        $messageSucces = (string)($_SESSION['sr_licences_message_succes'] ?? '');
        $messageErreur = (string)($_SESSION['sr_licences_message_erreur'] ?? '');
        unset($_SESSION['sr_licences_message_succes'], $_SESSION['sr_licences_message_erreur']);

        try {
            if (empty($_SESSION['sr_licences_csrf'])) {
                $_SESSION['sr_licences_csrf'] = bin2hex(random_bytes(16));
            }

            $pdo = BaseDeDonnees::creerDepuisConfig($this->config);
            $service = new ServiceLicence(new LicenceRepository($pdo));
            $licence = $service->obtenirLicencePourAdmin($idLicence);
        } catch (Throwable $e) {
            $_SESSION['sr_licences_message_erreur'] = 'Chargement impossible : ' . $e->getMessage();
            header('Location: /');
            exit;
        }

        header('Content-Type: text/html; charset=UTF-8');
        ?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <title>Modifier la licence #<?php echo (int)($licence['id_licence'] ?? 0); ?> - SR Licences</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    body{margin:0;font-family:Arial,sans-serif;background:#f8fafc;color:#111827}
    .page{max-width:1180px;margin:32px auto;background:#fff;border:1px solid #dbe3ea;border-radius:16px;padding:24px;box-shadow:0 6px 24px rgba(0,0,0,.06)}
    .barre{display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap}
    .badge-statut{display:inline-block;padding:6px 10px;border-radius:999px;border:1px solid;font-weight:700;font-size:12px;line-height:1.2;white-space:nowrap}
    .statut-active{background:#dcfce7;color:#166534;border-color:#86efac}
    .statut-suspendue{background:#fff7ed;color:#9a3412;border-color:#fdba74}
    .statut-revoquee{background:#fee2e2;color:#991b1b;border-color:#fca5a5}
    .statut-expiree{background:#fef3c7;color:#92400e;border-color:#fcd34d}
    .statut-invalide{background:#e5e7eb;color:#374151;border-color:#cbd5e1}
    .grille-info{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:14px;margin-top:18px}
    .carte-info{border:1px solid #dbe3ea;border-radius:14px;padding:16px;background:#fff}
    .libelle{margin:0 0 8px 0;font-size:13px;font-weight:700;color:#4b5563;text-transform:uppercase;letter-spacing:.02em}
    .contenu{font-size:16px;line-height:1.45;word-break:break-word}
    .contenu code{background:#f1f5f9;border:1px solid #cbd5e1;padding:2px 6px;border-radius:6px}
    .formulaire{margin-top:22px;padding:18px;border:1px solid #dbe3ea;border-radius:14px;background:#fff}
    .section-formulaire{margin-top:18px;padding:16px;border:1px solid #e5e7eb;border-radius:12px;background:#fbfdff}
    .section-formulaire:first-child{margin-top:0}
    .section-formulaire h2{margin:0 0 14px 0;font-size:18px}
    .grille-form{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:14px}
    .champ label{display:block;margin:0 0 6px 0;font-weight:700}
    .champ input,.champ select,.champ textarea{width:100%;box-sizing:border-box;padding:11px 12px;border:1px solid #cbd5e1;border-radius:10px;font:inherit;background:#fff}
    .champ textarea{min-height:130px;resize:vertical}
    .bloc-actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:18px}
    .bouton-principal,.bouton-secondaire,.bouton-toggle-avance{display:inline-block;padding:11px 14px;border-radius:10px;font-weight:700;text-decoration:none;cursor:pointer}
    .bouton-principal{border:0;background:#111827;color:#fff}
    .bouton-secondaire{border:1px solid #cbd5e1;background:#fff;color:#111827}
    .bouton-toggle-avance{border:1px solid #cbd5e1;background:#f8fafc;color:#111827}
    .alerte-ok{margin:16px 0;padding:12px 14px;border-radius:12px;background:#ecfdf5;color:#166534;border:1px solid #86efac}
    .alerte-ko{margin:16px 0;padding:12px 14px;border-radius:12px;background:#fef2f2;color:#991b1b;border:1px solid #fecaca}
    .muted{color:#4b5563}
    .champ-dates-abonnement{display:block}
    .bloc-avance{display:none;margin-top:14px;padding-top:14px;border-top:1px dashed #cbd5e1}
    .bloc-avance.ouvert{display:block}
  </style>
</head>
<body>
  <div class="page">
    <div class="barre">
      <div>
        <h1 style="margin:0 0 8px 0;">Modifier la licence #<?php echo (int)($licence['id_licence'] ?? 0); ?></h1>
        <p class="muted" style="margin:0;">Modification des champs métiers utiles pour les tests et la gestion.</p>
      </div>
      <div style="display:flex;gap:10px;flex-wrap:wrap;">
        <a class="bouton-secondaire" href="/licences/voir?id=<?php echo (int)($licence['id_licence'] ?? 0); ?>">Retour à la fiche</a>
        <a class="bouton-secondaire" href="/">Retour à la liste</a>
      </div>
    </div>

    <?php if ($messageSucces !== ''): ?>
      <div class="alerte-ok"><?php echo htmlspecialchars($messageSucces, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <?php if ($messageErreur !== ''): ?>
      <div class="alerte-ko"><?php echo htmlspecialchars($messageErreur, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <?php $statut = (string)($licence['statut'] ?? ''); ?>

    <div class="grille-info">
      <div class="carte-info">
        <div class="libelle">Clé de licence</div>
        <div class="contenu"><code><?php echo htmlspecialchars((string)($licence['cle_licence'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></code></div>
      </div>
      <div class="carte-info">
        <div class="libelle">Module</div>
        <div class="contenu"><code><?php echo htmlspecialchars((string)($licence['code_module'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></code></div>
      </div>
      <div class="carte-info">
        <div class="libelle">Statut</div>
        <div class="contenu">
          <span class="badge-statut <?php echo htmlspecialchars($this->obtenirClasseStatut($statut), ENT_QUOTES, 'UTF-8'); ?>">
            <?php echo htmlspecialchars($statut !== '' ? $statut : '—', ENT_QUOTES, 'UTF-8'); ?>
          </span>
        </div>
      </div>
      <div class="carte-info">
        <div class="libelle">Date création</div>
        <div class="contenu"><?php echo htmlspecialchars($this->formaterDate((string)($licence['date_creation'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></div>
      </div>
      <div class="carte-info">
        <div class="libelle">Date activation</div>
        <div class="contenu"><?php echo htmlspecialchars($this->formaterDate((string)($licence['date_activation'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></div>
      </div>
    </div>

    <div class="formulaire">
      <form method="post" action="/licences/modifier">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars((string)$_SESSION['sr_licences_csrf'], ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="id_licence" value="<?php echo (int)($licence['id_licence'] ?? 0); ?>">

        <div class="section-formulaire">
          <h2>Informations générales</h2>
          <div class="grille-form">
            <div class="champ">
              <label for="type_licence">Type de licence</label>
              <select id="type_licence" name="type_licence" required>
                <option value="perpetuelle" <?php echo (($licence['type_licence'] ?? '') === 'perpetuelle') ? 'selected' : ''; ?>>perpetuelle</option>
                <option value="abonnement" <?php echo (($licence['type_licence'] ?? '') === 'abonnement') ? 'selected' : ''; ?>>abonnement</option>
              </select>
            </div>

            <div class="champ">
              <label for="nom_client">Nom client</label>
              <input type="text" id="nom_client" name="nom_client" value="<?php echo htmlspecialchars((string)($licence['nom_client'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
            </div>

            <div class="champ">
              <label for="email_client">E-mail client</label>
              <input type="email" id="email_client" name="email_client" value="<?php echo htmlspecialchars((string)($licence['email_client'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
            </div>

            <div class="champ">
              <label for="domaine_principal">Domaine principal</label>
              <input type="text" id="domaine_principal" name="domaine_principal" value="<?php echo htmlspecialchars((string)($licence['domaine_principal'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
            </div>

            <div class="champ">
              <label for="version_max_autorisee">Version max autorisée</label>
              <input type="text" id="version_max_autorisee" name="version_max_autorisee" value="<?php echo htmlspecialchars((string)($licence['version_max_autorisee'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
            </div>
          </div>
        </div>

        <div class="section-formulaire" id="section_abonnement_modification">
          <h2>Période d’abonnement</h2>
          <p class="muted" style="margin:0 0 14px 0;">Renseigne une nouvelle durée pour recalculer automatiquement l’échéance et la grâce. Les champs avancés manuels restent disponibles au besoin.</p>

          <div class="grille-form">
            <div class="champ champ-dates-abonnement" id="bloc_validite_valeur_modification">
              <label for="validite_valeur">Nouvelle durée de validité</label>
              <input type="number" min="1" step="1" id="validite_valeur" name="validite_valeur" placeholder="Laisser vide pour conserver l’échéance actuelle">
            </div>

            <div class="champ champ-dates-abonnement" id="bloc_validite_unite_modification">
              <label for="validite_unite">Unité de validité</label>
              <select id="validite_unite" name="validite_unite">
                <option value="jours">jours</option>
                <option value="semaines">semaines</option>
                <option value="mois" selected>mois</option>
                <option value="annees">années</option>
              </select>
            </div>

            <div class="champ champ-dates-abonnement" id="bloc_grace_valeur_modification">
              <label for="grace_valeur">Nouvelle durée de grâce</label>
              <input type="number" min="0" step="1" id="grace_valeur" name="grace_valeur" placeholder="Laisser vide pour conserver l’existant">
            </div>

            <div class="champ champ-dates-abonnement" id="bloc_grace_unite_modification">
              <label for="grace_unite">Unité de grâce</label>
              <select id="grace_unite" name="grace_unite">
                <option value="jours" selected>jours</option>
                <option value="semaines">semaines</option>
                <option value="mois">mois</option>
                <option value="annees">années</option>
              </select>
            </div>
          </div>

          <div style="margin-top:14px;">
            <button type="button" class="bouton-toggle-avance" id="bouton_toggle_avance_modification">Afficher les réglages avancés</button>
          </div>

          <div class="bloc-avance" id="bloc_avance_modification">
            <div class="grille-form">
              <div class="champ champ-dates-abonnement">
                <label for="date_expiration">Date d’expiration (manuel / avancé)</label>
                <input type="datetime-local" id="date_expiration" name="date_expiration" value="<?php echo htmlspecialchars(((string)($licence['date_expiration'] ?? '') !== '') ? date('Y-m-d\TH:i', strtotime((string)$licence['date_expiration'])) : '', ENT_QUOTES, 'UTF-8'); ?>">
              </div>

              <div class="champ champ-dates-abonnement">
                <label for="grace_jusqu_a">Fin de grâce (manuel / avancé)</label>
                <input type="datetime-local" id="grace_jusqu_a" name="grace_jusqu_a" value="<?php echo htmlspecialchars(((string)($licence['grace_jusqu_a'] ?? '') !== '') ? date('Y-m-d\TH:i', strtotime((string)$licence['grace_jusqu_a'])) : '', ENT_QUOTES, 'UTF-8'); ?>">
              </div>
            </div>
          </div>
        </div>

        <div class="section-formulaire">
          <h2>Domaines et commentaire</h2>
          <div class="grille-form">
            <div class="champ" style="grid-column:1/-1;">
              <label for="domaines_test">Domaines de test</label>
              <textarea id="domaines_test" name="domaines_test"><?php echo htmlspecialchars((string)($licence['domaines_test_actifs_texte'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
            </div>

            <div class="champ" style="grid-column:1/-1;">
              <label for="commentaire_interne">Commentaire interne</label>
              <textarea id="commentaire_interne" name="commentaire_interne"><?php echo htmlspecialchars((string)($licence['commentaire_interne'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
            </div>
          </div>
        </div>

        <div class="bloc-actions">
          <button type="submit" class="bouton-principal">Enregistrer les modifications</button>
          <a class="bouton-secondaire" href="/licences/voir?id=<?php echo (int)($licence['id_licence'] ?? 0); ?>">Annuler</a>
        </div>
      </form>
    </div>
    </div>
  </div>

  <script>
  (function () {
    var selectType = document.getElementById('type_licence');
    var champs = [
      document.getElementById('validite_valeur'),
      document.getElementById('validite_unite'),
      document.getElementById('grace_valeur'),
      document.getElementById('grace_unite'),
      document.getElementById('date_expiration'),
      document.getElementById('grace_jusqu_a')
    ];
    var sectionAbonnement = document.getElementById('section_abonnement_modification');
    var boutonAvance = document.getElementById('bouton_toggle_avance_modification');
    var blocAvance = document.getElementById('bloc_avance_modification');

    if (!selectType) {
      return;
    }

    function mettreAJourVisibiliteDates() {
      var abonnement = selectType.value === 'abonnement';

      if (sectionAbonnement) {
        sectionAbonnement.style.display = abonnement ? '' : 'none';
      }

      if (!abonnement) {
        champs.forEach(function (champ) {
          if (!champ) {
            return;
          }
          if (champ.tagName === 'SELECT') {
            champ.selectedIndex = 0;
          } else {
            champ.value = '';
          }
        });

        if (blocAvance) {
          blocAvance.classList.remove('ouvert');
        }
        if (boutonAvance) {
          boutonAvance.textContent = 'Afficher les réglages avancés';
        }
      }
    }

    if (boutonAvance && blocAvance) {
      boutonAvance.addEventListener('click', function () {
        var ouvert = blocAvance.classList.toggle('ouvert');
        boutonAvance.textContent = ouvert ? 'Masquer les réglages avancés' : 'Afficher les réglages avancés';
      });
    }

    selectType.addEventListener('change', mettreAJourVisibiliteDates);
    mettreAJourVisibiliteDates();
  })();
  </script>
</body>
</html>
        <?php
        exit;
    }

    public function traiterCreationLicence(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            header('Content-Type: text/plain; charset=UTF-8');
            echo '405 - Méthode non autorisée';
            exit;
        }

        $csrfSession = (string)($_SESSION['sr_licences_csrf'] ?? '');
        $csrfFormulaire = (string)($_POST['csrf_token'] ?? '');

        if ($csrfSession === '' || !hash_equals($csrfSession, $csrfFormulaire)) {
            $_SESSION['sr_licences_message_erreur'] = 'Jeton de sécurité invalide.';
            header('Location: /');
            exit;
        }

        try {
            $pdo = BaseDeDonnees::creerDepuisConfig($this->config);
            $service = new ServiceLicence(new LicenceRepository($pdo));

            $resultat = $service->creerLicence([
                'code_module' => (string)($_POST['code_module'] ?? ''),
                'statut' => (string)($_POST['statut'] ?? 'active'),
                'type_licence' => (string)($_POST['type_licence'] ?? 'perpetuelle'),
                'nom_client' => (string)($_POST['nom_client'] ?? ''),
                'email_client' => (string)($_POST['email_client'] ?? ''),
                'domaine_principal' => (string)($_POST['domaine_principal'] ?? ''),
                'version_max_autorisee' => (string)($_POST['version_max_autorisee'] ?? ''),
                'validite_valeur' => (string)($_POST['validite_valeur'] ?? ''),
                'validite_unite' => (string)($_POST['validite_unite'] ?? 'mois'),
                'grace_valeur' => (string)($_POST['grace_valeur'] ?? ''),
                'grace_unite' => (string)($_POST['grace_unite'] ?? 'jours'),
                'date_expiration' => (string)($_POST['date_expiration'] ?? ''),
                'grace_jusqu_a' => (string)($_POST['grace_jusqu_a'] ?? ''),
                'domaines_test' => (string)($_POST['domaines_test'] ?? ''),
                'commentaire_interne' => (string)($_POST['commentaire_interne'] ?? ''),
            ]);

            $_SESSION['sr_licences_message_succes'] =
                'Licence créée avec succès. Clé : ' . (string)($resultat['cle_licence'] ?? '');
        } catch (Throwable $e) {
            $_SESSION['sr_licences_message_erreur'] = 'Création impossible : ' . $e->getMessage();
        }

        header('Location: /');
        exit;
    }

    public function traiterDecisionDemandeActivation(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            header('Content-Type: text/plain; charset=UTF-8');
            echo '405 - Méthode non autorisée';
            exit;
        }

        $csrfSession = (string)($_SESSION['sr_licences_csrf'] ?? '');
        $csrfFormulaire = (string)($_POST['csrf_token'] ?? '');

        if ($csrfSession === '' || !hash_equals($csrfSession, $csrfFormulaire)) {
            $_SESSION['sr_licences_message_erreur'] = 'Jeton de sécurité invalide.';
            header('Location: /');
            exit;
        }

        try {
            $idDemandeActivation = (int)($_POST['id_demande_activation'] ?? 0);
            $actionDecision = trim((string)($_POST['action_decision'] ?? ''));

            $pdo = BaseDeDonnees::creerDepuisConfig($this->config);
            $service = new ServiceDemandeActivation(
                new DemandeActivationRepository($pdo),
                new LicenceRepository($pdo)
            );

            if ($actionDecision === 'valider') {
                $resultat = $service->validerDemandeActivation($idDemandeActivation, [
                    'type_licence' => (string)($_POST['type_licence'] ?? 'perpetuelle'),
                    'version_max_autorisee' => (string)($_POST['version_max_autorisee'] ?? ''),
                    'validite_valeur' => (string)($_POST['validite_valeur'] ?? ''),
                    'validite_unite' => (string)($_POST['validite_unite'] ?? 'mois'),
                    'grace_valeur' => (string)($_POST['grace_valeur'] ?? ''),
                    'grace_unite' => (string)($_POST['grace_unite'] ?? 'jours'),
                    'date_expiration' => (string)($_POST['date_expiration'] ?? ''),
                    'grace_jusqu_a' => (string)($_POST['grace_jusqu_a'] ?? ''),
                    'note_interne' => (string)($_POST['note_interne'] ?? ''),
                ]);

                $_SESSION['sr_licences_message_succes'] =
                    'Demande #' . (int)($resultat['id_demande_activation'] ?? 0) .
                    ' validée. Licence créée : ' . (string)($resultat['cle_licence'] ?? '');
            } elseif ($actionDecision === 'refuser') {
                $resultat = $service->refuserDemandeActivation(
                    $idDemandeActivation,
                    (string)($_POST['note_interne'] ?? '')
                );

                $_SESSION['sr_licences_message_succes'] =
                    'Demande #' . (int)($resultat['id_demande_activation'] ?? 0) . ' refusée.';
            } else {
                throw new \InvalidArgumentException('Action de décision invalide.');
            }
        } catch (Throwable $e) {
            $_SESSION['sr_licences_message_erreur'] = 'Décision impossible : ' . $e->getMessage();
        }

        header('Location: /');
        exit;
    }

    public function traiterChangementStatutLicencesLot(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            header('Content-Type: text/plain; charset=UTF-8');
            echo '405 - Méthode non autorisée';
            exit;
        }

        $csrfSession = (string)($_SESSION['sr_licences_csrf'] ?? '');
        $csrfFormulaire = (string)($_POST['csrf_token'] ?? '');

        if ($csrfSession === '' || !hash_equals($csrfSession, $csrfFormulaire)) {
            $_SESSION['sr_licences_message_erreur'] = 'Jeton de sécurité invalide.';
            header('Location: /');
            exit;
        }

        try {
            $actionStatut = (string)($_POST['action_statut_lot'] ?? '');
            $idsBruts = $_POST['ids_licence'] ?? [];

            if (!is_array($idsBruts)) {
                $idsBruts = [$idsBruts];
            }

            $idsLicence = [];
            foreach ($idsBruts as $idBrut) {
                $id = (int)$idBrut;
                if ($id > 0) {
                    $idsLicence[] = $id;
                }
            }

            $idsLicence = array_values(array_unique($idsLicence));

            if (empty($idsLicence)) {
                throw new \InvalidArgumentException('Aucune licence sélectionnée.');
            }

            if ($actionStatut === 'reactiver') {
                throw new \InvalidArgumentException('Utilise la réactivation avec période pour les abonnements.');
            }

            if (!in_array($actionStatut, ['suspendre', 'revoquer'], true)) {
                throw new \InvalidArgumentException('Action de statut invalide.');
            }

            $pdo = BaseDeDonnees::creerDepuisConfig($this->config);
            $service = new ServiceLicence(new LicenceRepository($pdo));

            foreach ($idsLicence as $idLicence) {
                $service->changerStatutLicence($idLicence, $actionStatut);
            }

            $_SESSION['sr_licences_message_succes'] =
                count($idsLicence) . ' licence(s) mise(s) à jour via l’action "' . $actionStatut . '".';
        } catch (Throwable $e) {
            $_SESSION['sr_licences_message_erreur'] = 'Modification en lot impossible : ' . $e->getMessage();
        }

        header('Location: /');
        exit;
    }

    public function traiterChangementStatutLicence(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            header('Content-Type: text/plain; charset=UTF-8');
            echo '405 - Méthode non autorisée';
            exit;
        }

        $csrfSession = (string)($_SESSION['sr_licences_csrf'] ?? '');
        $csrfFormulaire = (string)($_POST['csrf_token'] ?? '');

        if ($csrfSession === '' || !hash_equals($csrfSession, $csrfFormulaire)) {
            $_SESSION['sr_licences_message_erreur'] = 'Jeton de sécurité invalide.';
            header('Location: /');
            exit;
        }

        try {
            $idLicence = (int)($_POST['id_licence'] ?? 0);
            $actionStatut = (string)($_POST['action_statut'] ?? '');

            $pdo = BaseDeDonnees::creerDepuisConfig($this->config);
            $service = new ServiceLicence(new LicenceRepository($pdo));
            $resultat = $service->changerStatutLicence($idLicence, $actionStatut);

            $_SESSION['sr_licences_message_succes'] =
                'Statut de la licence #' . (int)($resultat['id_licence'] ?? 0) .
                ' mis à jour : ' . (string)($resultat['statut'] ?? '');
        } catch (Throwable $e) {
            $_SESSION['sr_licences_message_erreur'] = 'Modification impossible : ' . $e->getMessage();
        }

        header('Location: /');
        exit;
    }

    private function obtenirClasseStatut(string $statut): string
    {
        return match ($statut) {
            'active' => 'statut-active',
            'suspendue' => 'statut-suspendue',
            'revoquee' => 'statut-revoquee',
            'expiree' => 'statut-expiree',
            'invalide' => 'statut-invalide',
            default => 'statut-invalide',
        };
    }

    private function obtenirClasseStatutDemandeActivation(string $statut): string
    {
        return match ($statut) {
            'en_attente' => 'statut-expiree',
            'validee' => 'statut-active',
            'refusee' => 'statut-revoquee',
            'terminee' => 'statut-suspendue',
            default => 'statut-invalide',
        };
    }

    private function formaterDate(string $date): string
    {
        $date = trim($date);
        if ($date === '') {
            return '—';
        }

        $timestamp = strtotime($date);
        if ($timestamp === false) {
            return $date;
        }

        return date('d/m/Y H:i', $timestamp);
    }
}
