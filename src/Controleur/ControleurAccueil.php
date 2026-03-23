<?php
declare(strict_types=1);

namespace SrLicences\Controleur;

use SrLicences\Config\BaseDeDonnees;
use SrLicences\Repository\LicenceRepository;
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
        $messageStatistiques = 'Compteurs non disponibles tant que la BDD n’est pas configurée.';
        $messageListe = 'Liste non disponible tant que la BDD n’est pas configurée.';

        if (!empty($etatBdd['ok'])) {
            try {
                $pdo = BaseDeDonnees::creerDepuisConfig($this->config);
                $service = new ServiceLicence(new LicenceRepository($pdo));
                $statistiques = $service->obtenirStatistiquesTableauDeBord();
                $licences = $service->obtenirLicencesTableauDeBord(100);
                $messageStatistiques = 'Lecture des statistiques OK.';
                $messageListe = 'Lecture de la liste OK.';
            } catch (Throwable $e) {
                $messageStatistiques = $e->getMessage();
                $messageListe = $e->getMessage();
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
    .barre-filtres-liste{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin:16px 0 14px 0;padding:14px;border:1px solid #dbe3ea;border-radius:12px;background:#f8fafc}
    .champ-filtre label{display:block;margin-bottom:6px;font-weight:700;font-size:13px}
    .champ-filtre input,.champ-filtre select{width:100%;box-sizing:border-box;padding:9px 11px;border:1px solid #cbd5e1;border-radius:10px;font:inherit;background:#fff}
    .actions-filtres{display:flex;gap:8px;align-items:end;flex-wrap:wrap}
    .btn-secondaire{padding:10px 12px;border:1px solid #cbd5e1;border-radius:10px;background:#fff;color:#111827;font-weight:700;cursor:pointer}
    .resume-filtres{margin:0 0 10px 0;color:#4b5563;font-size:13px}
    .ligne-masquee{display:none}
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
      <h2>Créer une licence</h2>

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

          <div class="champ" id="bloc_date_expiration">
            <label for="date_expiration">Date d’expiration</label>
            <input type="datetime-local" id="date_expiration" name="date_expiration">
          </div>

          <div class="champ" id="bloc_grace_jusqu_a">
            <label for="grace_jusqu_a">Fin de grâce</label>
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
        var blocExpiration = document.getElementById('bloc_date_expiration');
        var blocGrace = document.getElementById('bloc_grace_jusqu_a');
        var champExpiration = document.getElementById('date_expiration');
        var champGrace = document.getElementById('grace_jusqu_a');

        if (!selectType || !blocExpiration || !blocGrace || !champExpiration || !champGrace) {
          return;
        }

        function mettreAJourVisibiliteDates() {
          var estAbonnement = selectType.value === 'abonnement';

          blocExpiration.style.display = estAbonnement ? '' : 'none';
          blocGrace.style.display = estAbonnement ? '' : 'none';

          if (!estAbonnement) {
            champExpiration.value = '';
            champGrace.value = '';
          }
        }

        selectType.addEventListener('change', mettreAJourVisibiliteDates);
        mettreAJourVisibiliteDates();
      })();
      </script>
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
        <div class="table-wrap">
          <table id="tableau-licences">
            <thead>
              <tr>
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
                <th>Actions</th>
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
                  <td class="cellule-actions">
                    <div class="actions-ligne">
                      <form method="post" action="/licences/statut">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars((string)$_SESSION['sr_licences_csrf'], ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="id_licence" value="<?php echo (int)($licence['id_licence'] ?? 0); ?>">
                        <button class="btn-mini btn-reactiver" type="submit" name="action_statut" value="reactiver">Réactiver</button>
                      </form>

                      <form method="post" action="/licences/statut">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars((string)$_SESSION['sr_licences_csrf'], ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="id_licence" value="<?php echo (int)($licence['id_licence'] ?? 0); ?>">
                        <button class="btn-mini btn-suspendre" type="submit" name="action_statut" value="suspendre">Suspendre</button>
                      </form>

                      <form method="post" action="/licences/statut">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars((string)$_SESSION['sr_licences_csrf'], ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="id_licence" value="<?php echo (int)($licence['id_licence'] ?? 0); ?>">
                        <button class="btn-mini btn-revoquer" type="submit" name="action_statut" value="revoquer">Révoquer</button>
                      </form>
                    </div>
                  </td>
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
