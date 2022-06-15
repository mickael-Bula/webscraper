# Récupérer l'id de l'utilisateur connecté

Un premier problème est que l'on ne peut pas accéder aux méthodes définies manuellement depuis un user, mais seulement à celles qui se trouvent impléentées dans la UserInterface.

Le second problème, réglé, est qu'il n'y a pas de getter pour l'id. Après en avoir ajouté un, l'accès à celui-ci se fait ainsi :
```php
$userId = $this->getUser()->getId();            // le récupère l'id du user
$userRepo = $userRepository->find($userId);   // j'accède en fin à toutes les props du user
$userHigher = $userRepo->getHigher();       // je récupère le plus haut !
```

