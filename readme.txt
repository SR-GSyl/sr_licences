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
- Préprod locale :
  /home/sylvere/Git_depot_local/sr_licences

- Dossier de déploiement reconstruit pour WinSCP :
  /mnt/c/Users/soulr/Downloads/sr_licences_deploiement

- Prod :
  hébergement AMEN, mise à jour via WinSCP.

Principe de déploiement retenu
------------------------------
Le dépôt Git local contient les fichiers de travail.

On ne pousse PAS directement tout le dépôt vers la prod.
À la place :
1. on prépare un dossier de déploiement propre ;
2. ce dossier exclut les éléments sensibles ou inutiles ;
3. on envoie ensuite ce dossier vers la prod avec WinSCP.

Cette méthode évite notamment d’écraser :
- config/config.php
- .well-known/
- les fichiers SQL
- les sauvegardes .bak
- les fichiers Git

Scripts utilisés
----------------
1) Script de préparation du dossier de déploiement

Nom conseillé :
preparer_deploiement.sh

Rôle :
- supprimer l’ancien dossier de déploiement ;
- recréer un dossier propre ;
- copier uniquement les fichiers utiles à la prod.

Commande d’exécution :
/home/sylvere/Git_depot_local/sr_licences/preparer_deploiement.sh

2) Script de commit + préparation du dossier de déploiement

Nom conseillé :
preparer_commit_et_deploiement.sh

Rôle :
- se placer dans le dépôt local ;
- faire git add -A ;
- créer un commit avec le message fourni ;
- supprimer l’ancien dossier de déploiement ;
- reconstruire un nouveau dossier propre pour WinSCP.

Commande d’exécution :
/home/sylvere/Git_depot_local/sr_licences/preparer_commit_et_deploiement.sh "Mon message de commit"

Exemple :
/home/sylvere/Git_depot_local/sr_licences/preparer_commit_et_deploiement.sh "Correction index.php et préparation du déploiement"

Fichiers exclus du dossier de déploiement
-----------------------------------------
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
- preparer_deploiement.sh
- preparer_commit_et_deploiement.sh

Conséquence importante :
- le dossier de déploiement n’est PAS autonome ;
- la prod doit déjà posséder son propre config/config.php ;
- la prod conserve également son propre .htaccess tant qu’on choisit de ne pas le déployer.

Contenu attendu du dossier de déploiement
-----------------------------------------
En l’état actuel, le dossier propre destiné à la prod doit contenir essentiellement :
- index.php
- src/

Cela correspond à une mise à jour sélective du code, et non à une installation complète vierge.

Procédure de travail recommandée
--------------------------------
1. Modifier les fichiers dans le dépôt local :
   /home/sylvere/Git_depot_local/sr_licences

2. Lancer soit :
   - preparer_deploiement.sh
   ou
   - preparer_commit_et_deploiement.sh "message"

3. Vérifier le contenu de :
   /mnt/c/Users/soulr/Downloads/sr_licences_deploiement

4. Ouvrir WinSCP.

5. Côté local, choisir :
   /mnt/c/Users/soulr/Downloads/sr_licences_deploiement
   ou, côté Windows :
   C:\Users\soulr\Downloads\sr_licences_deploiement

6. Côté distant, viser la racine du site de prod SR Licences.

7. Copier uniquement les fichiers préparés vers la prod.

Points de vigilance
-------------------
- Ne pas écraser config/config.php sans décision volontaire.
- Ne pas envoyer de dump SQL en prod via WinSCP.
- Ne pas supprimer .well-known/.
- Ne pas supposer que le dossier de déploiement suffit pour une installation neuve.
- Vérifier après envoi que l’application répond toujours correctement.

Rappel utile
------------
Le dossier de déploiement est un dossier tampon de livraison.
Il ne remplace pas le dépôt Git.
Le dépôt Git reste la source de travail.
Le dossier de déploiement sert uniquement à préparer ce qui doit partir en prod.
