SR LICENCES — README
====================

Rôle de SR Licences
-------------------
SR Licences est l’application serveur de gestion des licences du module SR Merchant Flux.

Son rôle principal est de :
- stocker les licences dans une base de données dédiée ;
- vérifier l’état d’une licence à partir d’une requête du module ;
- renvoyer une réponse signée ;
- permettre l’administration des licences via une interface dédiée ;
- séparer la logique de licence du back-office PrestaShop.

En pratique :
- SR Merchant Flux agit comme client ;
- SR Licences agit comme serveur de vérification et de gestion.

Organisation actuelle
---------------------
- Dépôt Git (source de travail en dev) :
  /home/sylvere/Git_depot_local/sr_licences

- Runtime Dev (instance web servie par Apache) :
  /var/www/sr_licences_dev

- URL Dev :
  http://sr-licences-dev.local/

- Base de données Dev :
  sr_licences_dev

- Dossier de déploiement reconstruit pour la prod via WinSCP :
  /mnt/c/Users/soulr/Downloads/sr_licences_deploiement_prod

- Prod :
  hébergement AMEN, mise à jour via WinSCP.

Principe de fonctionnement retenu
---------------------------------
Le dépôt Git en dev contient les fichiers de travail.

On ne pousse PAS directement tout le dépôt vers la prod.
À la place :
1. on travaille dans le dépôt Git de dev ;
2. on propage vers l’instance Dev pour tester localement ;
3. on prépare un dossier de déploiement propre pour la prod ;
4. on envoie ensuite ce dossier vers la prod avec WinSCP.

Cette méthode évite notamment d’écraser :
- config/config.php
- .well-known/
- les fichiers SQL
- les sauvegardes .bak
- les fichiers Git

Scripts utilisés
----------------
1) Script de propagation vers l’instance Dev

Nom :
propager_vers_dev.sh

Rôle :
- prendre le dépôt Git comme source ;
- recopier les fichiers utiles vers /var/www/sr_licences_dev ;
- exclure les éléments inutiles au runtime ;
- permettre de tester l’application Dev dans le navigateur.

Commande d’exécution :
./propager_vers_dev.sh

2) Script de préparation du dossier de déploiement prod

Nom :
preparer_deploiement.sh

Rôle :
- supprimer l’ancien dossier de déploiement prod ;
- recréer un dossier propre ;
- copier uniquement les fichiers utiles à la prod.

Commande d’exécution :
./preparer_deploiement.sh

3) Script de commit + préparation du dossier de déploiement prod

Nom :
preparer_commit_et_deploiement.sh

Rôle :
- se placer dans le dépôt Git de dev ;
- faire git add -A ;
- créer un commit avec le message fourni ;
- lancer ensuite la reconstruction du dossier de déploiement prod.

Commande d’exécution :
./preparer_commit_et_deploiement.sh "Mon message de commit"

Exemple :
./preparer_commit_et_deploiement.sh "Correction index.php et préparation du déploiement prod"

Fichiers exclus de l’instance Dev
---------------------------------
Le script propager_vers_dev.sh exclut actuellement :
- .git/
- .github/
- .well-known/
- sql/
- var/
- *.bak
- *.bak.*
- readme.txt
- readme_sr_licences.txt
- preparer_deploiement.sh
- preparer_commit_et_deploiement.sh
- propager_vers_dev.sh

Conséquence :
- l’instance Dev est une copie runtime du dépôt ;
- elle garde bien config/ et .htaccess, car ils sont nécessaires à son fonctionnement.

Fichiers exclus du dossier de déploiement prod
----------------------------------------------
Les scripts de préparation excluent actuellement :
- .git/
- .github/
- .well-known/
- .htaccess
- .gitignore
- config/
- sql/
- var/
- *.bak
- *.bak.*
- readme.txt
- readme_sr_licences.txt
- preparer_deploiement.sh
- preparer_commit_et_deploiement.sh
- propager_vers_dev.sh

Conséquences importantes :
- le dossier de déploiement prod n’est PAS autonome ;
- la prod doit déjà posséder son propre config/config.php ;
- la prod conserve également son propre .htaccess tant qu’on choisit de ne pas le déployer.

Contenu attendu du dossier de déploiement prod
----------------------------------------------
En l’état actuel, le dossier propre destiné à la prod doit contenir essentiellement :
- index.php
- src/

Cela correspond à une mise à jour sélective du code, et non à une installation complète vierge.

Procédure de travail recommandée
--------------------------------
1. Modifier les fichiers dans le dépôt Git de dev :
   /home/sylvere/Git_depot_local/sr_licences

2. Propager vers l’instance Dev :
   ./propager_vers_dev.sh

3. Tester dans le navigateur :
   http://sr-licences-dev.local/

4. Préparer le dossier de déploiement prod :
   ./preparer_deploiement.sh
   ou
   ./preparer_commit_et_deploiement.sh "message"

5. Vérifier le contenu de :
   /mnt/c/Users/soulr/Downloads/sr_licences_deploiement_prod

6. Ouvrir WinSCP.

7. Côté local, choisir :
   /mnt/c/Users/soulr/Downloads/sr_licences_deploiement_prod
   ou, côté Windows :
   C:\Users\soulr\Downloads\sr_licences_deploiement_prod

8. Côté distant, viser la racine du site de prod SR Licences.

9. Copier uniquement les fichiers préparés vers la prod.

Points de vigilance
-------------------
- Ne pas écraser config/config.php sans décision volontaire.
- Ne pas envoyer de dump SQL en prod via WinSCP.
- Ne pas supprimer .well-known/.
- Ne pas supposer que le dossier de déploiement suffit pour une installation neuve.
- Vérifier après envoi que l’application répond toujours correctement.
- Se souvenir que l’IP WSL peut changer ; si besoin, mettre à jour le fichier hosts de Windows pour sr-licences-dev.local.

Rappels utiles
--------------
Le dépôt Git reste la source de travail.
L’instance Dev est la copie d’exécution locale.
Le dossier de déploiement prod est un dossier tampon de livraison.

Commandes utiles
----------------
Voir les tables de la BDD Dev :
mysql -u sylvere -p -e "USE sr_licences_dev; SHOW TABLES;"

Tester l’API Dev :
curl -X POST \
  -H "Content-Type: application/json" \
  -d '{"module":"sr_merchant_flux","licence_key":"CLE_ICI","domain":"soulrebel-dev.local"}' \
  http://sr-licences-dev.local/api/licence/check
