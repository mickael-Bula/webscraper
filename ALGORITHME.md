# ALGORITHME DE WEBTRADER

Une position est un ordre d'achat suivi d'un ordre de vente.

## 1 - Chaque jour après 18:00

Si un nouveau plus haut est réalisé :
```php
LAST_HIGH.higher = CAC.higher;
LAST_HIGH.buyLimit = LAST_HIGH.higher - 10%;
```

## 2 - On récupère toutes les POSITION.isWaiting

Cette récupération se fait sur la base des clés étrangères :
```php
$buyLimit = $cacRepository->findOneBy([], ["id" => "DESC"]);
findBy(["buyLimit" => $buyLimit, "isWaiting" => "true"]);
```
Ou bien peut-être...
```php
$lastHigh = new LastHigh();
$lastHigh = ($lastHigh->getHigher())[-1];   // pour récupérer le dernier indice du tableau
```
NOTE : ces méthodes sont à vérifier.

Ensuite, on boucle sur le tableau.
Si une position a une buyTarget inférieure à Cac.lower, alors toutes les positions récupérées précédemment reçoivent :
```php
POSITION.isRunning = true;
POSITION.isWaiting = false;
```
En outre, celles ayant une buyTarget inférieure à Cac.lower reçoivent :
```php
isActive = true;
```
Pour celles-ci, on crée immédiatement un nouvel ordre, cette fois à la vente.
L'objectif de revente est :
```php
sellTarget = buyTarget + 10%;
```
On met à jour les propriétés LAST_HIGH.higher et LAST_HIGH.buyLimit :
```php
LAST_HIGH.higher = CAC.higher
LAST_HIGH.buyLimit = LAST_HIGH.higher - 10%
```
Enfin, on renseigne la propriété buyPrice :
```php
buyPrice = buyTarget
```
NOTE : cette propriété va nous permettre de renseigner le véritable prix d'achat, lequel peut avoir été exécuté à un cours différent de celui fixé (cas d'un gap de cotation par exemple).

## 3 - On récupère toutes les POSITION.isRunning

Si parmi celles-ci il y a une POSITION.isActive qui a une saleTarget inférieure à Cac.higher, la position devient :
```php
isActive = false;
isRunning = false;
isClosed = true;
saleDate = new DateTime(now);   // date du jour
salePrice = saleTarget;         // qui pourra être ajustée à l'image de buySale
```
