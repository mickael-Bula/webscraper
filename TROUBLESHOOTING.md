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
