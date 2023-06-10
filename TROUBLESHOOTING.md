# Récupérer l'id de l'utilisateur connecté

Un premier problème est que l'on ne peut pas accéder aux méthodes définies manuellement depuis un user, mais seulement à celles qui se trouvent implémentées dans la UserInterface.

Le second problème, réglé, est qu'il n'y a pas de getter pour l'id. Après en avoir ajouté un, l'accès à celui-ci se fait ainsi :
```php
$userId = $this->getUser()->getId();            // le récupère l'id du user
$userRepo = $userRepository->find($userId);   // j'accède en fin à toutes les props du user
$userHigher = $userRepo->getHigher();       // je récupère le plus haut !
```

Il semblerait que cela ne suffise pas : pour véritablement régler le problème et éviter toute alerte de l'ide, j'ai créé la fonction suivante.
Après injection de Security dans le constructeur :
````php
public function getCurrentUser(): User
    {
        /** @var User $user */
        $user = $this->security->getUser();
        return $this->userRepository->find( $user->getId());
    }
````

## ajout des formulaires d' enregistremetn d'un nouvel utilisateur et de réinitialisation de mot de passe

J'ai ajouté ces formulaires à l'aide des bundles suivants :
- symfony composer require symfonycasts/reset-password-bundle
- symfony composer require symfonycasts/verify-email-bundle

Puis, après avoir fait les migrations, j'ai lancé la commande : 

```bash
symfony php bin/console make:registration-form
```

Ceci m'a permis d'ajouter un nouvel utilisateur pour se nouveau accéder à l'appli (impossible de modifier le mot de passe de l'utilisateur initial)