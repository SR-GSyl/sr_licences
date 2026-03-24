# Statuts des licences SR Licences

Ce document définit la signification des statuts utilisés dans SR Licences et leur comportement attendu dans les modules clients, notamment SR Merchant Flux.

## Principe général

Le statut d’une licence doit être compris de manière cohérente dans toute l’application et dans les modules qui la consomment.

## Liste des statuts

### active
La licence est valide et utilisable.

Cas typiques :
- licence perpétuelle autorisée
- abonnement encore dans sa période de validité

Effet attendu :
- le module client peut fonctionner normalement

### grace
La période de validité principale est dépassée, mais la période de grâce est encore en cours.

Cas typiques :
- abonnement expiré récemment
- tolérance temporaire accordée

Effet attendu :
- le module client reste autorisé temporairement

### expiree
La licence n’est plus valide car :
- l’abonnement est arrivé à échéance
- et la période de grâce est terminée ou absente

Effet attendu :
- le module client doit être bloqué

### suspendue
La licence existe toujours, mais son usage est temporairement bloqué par décision administrative.

Cas typiques :
- paiement en attente
- contrôle administratif
- litige provisoire

Effet attendu :
- le module client doit être bloqué

### revoquee
La licence est retirée par décision administrative forte.

Cas typiques :
- abus
- fraude
- annulation ferme
- retrait définitif du droit d’usage

Effet attendu :
- le module client doit être bloqué

### invalide
La licence n’est pas reconnue comme valable.

Cas typiques :
- clé inconnue
- module incorrect
- données incohérentes
- licence rendue invalide administrativement

Effet attendu :
- le module client doit être bloqué

## Règles métier recommandées

- `active` : autorisé
- `grace` : autorisé temporairement
- `expiree` : bloqué
- `suspendue` : bloqué
- `revoquee` : bloqué
- `invalide` : bloqué

## Rôle de l’administrateur

L’administrateur reste décisionnaire.

Cependant, pour une licence de type `abonnement`, une réactivation cohérente doit s’accompagner d’une nouvelle période de validité, afin que les dates et le statut restent alignés.

## Interface utilisateur

Dans l’interface d’administration, un rappel compact de la signification des statuts doit rester accessible à tout moment, de préférence sous forme d’un bloc d’aide repliable, afin de rester compatible avec un usage confortable sur smartphone.