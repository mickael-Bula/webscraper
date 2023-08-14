# Récupérer l'id de l'utilisateur connecté

Un premier problème est que l'on ne peut pas accéder aux méthodes définies manuellement depuis un user, mais seulement à celles qui se trouvent implémentées dans la UserInterface.

Le second problème, réglé, est qu'il n'y a pas de getter pour l'id. Après en avoir ajouté un, l'accès à celui-ci se fait ainsi :

```php
$userId = $this->getUser()->getId();            // je récupère l'id du user
$userRepo = $userRepository->find($userId);   // j'accède enfin à toutes les props du user
$userHigher = $userRepo->getHigher();       // je récupère le plus haut !
```

Il semblerait que cela ne suffise pas : pour véritablement régler le problème et éviter toute alerte de l'ide, j'ai créé la fonction suivante.
Après injection de Security dans le constructeur :

```php
public function getCurrentUser(): User
    {
        /** @var User $user */
        $user = $this->security->getUser();
        return $this->userRepository->find( $user->getId());
    }
```

## connexion à mysql depuis wampserver : vérification du port

J'ai rencontré un problème lors de l'importation de ma BDD sur le pc portable : ni mon utilisateur dédié ni l'utilisateur root n'étaient reconnus.
Après une minutieuse vérification de la casse, puis de longues recherches pour comprendre l'origine du problème, j'ai réalisé que le moteur mysql auquel Symfony se connectait n'était pas le bon.
En effet, ayant fait une installation de sql antérieure à celle de wampserver, le port par défaut (3306) était déjà occupé par cette instance !
Une simple modification du port (lequel est suggéré par wampserver) a réglé mon problème (port utilisé : 3308).

## La consultation de l'application avant la fermeture corrompt les données sauvegardées...

Un contrôle partiel existe, qui consiste à vérifier l'heure avant de décider si le dernier jour de cotation doit être enregistré en BDD...
Il faut lancer l'appli en mode debug et avant 18:00 pour récupérer des données et vérifier le comportement.

Pourtant, les données du dernier jour de cotation sont supprimées dès lors qu'il est moins de 18:00. **A approfondir**

**Résultat d'un test lorsque le marché est ouvert** : Datascraper::getData() l.20 -> la variable `isOpen` retourne **false** alors que le marché est ouvert.
En effet, lors du test de `$isOpen = $open == "Ouvert";`, *$open* vaut `Real-time Data`.
Une vérification de la page parsée confirme que le code a changé et que désormais ce n'est plus **ouvert** et **fermé** qui est affiché, mais **En temps Réel** (c'est-à-dire **Real-time Data** traduit).

Pour contourner cette évolution, je décide donc d'utiliser l'icone sur la même ligne et dont la couleur change en fonction de l'état du marché.
Cependant, cette icone est un svg dont la couleur est injectée sous forme de variable que je ne peux récupérer dans la code...

Finalement, le plus simple reste encore de ne pas charger les données d'un jour de semaine s'il n'est pas plus de 18:00, même si cela entraîne de ne pas charger les données du dernier jour disponible lors d'un jour férié qui tombe en semaine.
Algo : si le jour est entre lundi et vendredi et qu'il est moins de 18:00, on supprime la dernière donnée récupérée. Ce que je traduis sous la forme :

```php
$isOpen = in_array(date('w'), range(1, 5)) && date("G") <= "18";
```

