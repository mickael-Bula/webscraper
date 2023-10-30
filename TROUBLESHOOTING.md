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

## La dernière date disponible du Cac ne s'est pas mise à jour du premier coup

Lors du test de l'application lorsque le marché est ouvert, la dernière journée complète disponible n'a pas été enregistrée en base du premier coup.
Il a fallu relancer l'appli une seconde fois pour que cela soit le cas.
Cela n'a concerné que le Cac et non le Lvc => TODO : à tenter de reproduire et corriger au besoin.

## Tests

Pour mes tests, j'ai pris le parti d'injecter les dépendances en utilisant le container.
Pour cela, la méthode est d'utiliser : 

```php
        // je lance le kernel qui charge le service container
        self::bootKernel();

        //  j'utilise static::getContainer() pour accéder au service container
        $container = static::getContainer();

        // je récupère mes services depuis le container instancié précédemment
        $this->security = $container->get(Security::class);
```

Ensuite, on récupère les services comme ceci :

```php
$entity = $this->entityManager->getRepository(LastHigh::class)->findOneBy(["id" => "1"]);
```

Pour produire les tests, il a fallu configurer le phpunit.xml, en fournissant les paramètres du .env manquant :

```xml
<env name="MAILER_DSN" value="smtp://user:pwd@smtp.mailtrap.io:2525?encryption=tls&amp;auth_mode=login" />
```

Les tests ont également besoin qu'une base de données de test soit déclarées.
Pour cela, j'ai fait une copie de la base directement depuis l'interface phpMyAdmin :

```sql
CREATE DATABASE webtrader_test;
```

Puis j'ai effectué un export depuis la base d'origine et enfin un import dans la base cible.

>NOTE : phpunit se connecte à la base de données en la suffixant avec _test.
> Cela signifie qu'il ne faut pas ajouter ce suffixe dans la déclaration de la BDD dans le phpunit.xml.

Il existe une autre manière plus rapide et plus simple de réaliser une copie de base avec PhpMyAdmin : utiliser le menu Opération > Copier la table vers

Cette option permet de faire, au choix, une copie de la structure de la table ou une copie complète incluant les données.

## Modification du schéma de la base de données

J'avais fait une erreur lors de la modélisation de ma base de données : la relation One To One entre cac et Last High devait en fait être One To Many.
Cela empêchait d'ajouter des nouveaux Last_High dès lors que le cours du Cac était déjà présent dans la table (pas de duplication en raison d'une clé contrainte de clé unique).

Il m'a donc fallu changer de schéma, mais ce qui est simple sur le papier ne l'est pas avec une base contenant des données.

Après avoir cherché et tenté plusieurs solution, j'en suis arrivé à la procédure suivante :

- modifier les relations directement dans le code des entités, y compris les annotations
- repartir d'une base vierge, créée avec Doctrine en spécifiant son nom dans le .env
- lancer les commandes : 

```bash
$ php bin/console doctrine:database:create
$ php bin/console doctrine:schema:create
$ php bin/console doctrine:migrations:migrate # j'avais un seul fichier de migration reprenant les nouvelles relations 
```

- supprimer dans la table originale la colonne id (pour s'affranchir de la clé primaire et de son lien avec la clé unique)
- remettre l'index primaire à 1 (pour repartir sur une base propre)
- regénérer une colonne `id` avec un index AUTO INCREMENT
- copier les données de la table d'origine (sans la structure) de l'ancienne base vers la nouvelle, ceci pour cac et lvc

Les commandes sql sont les suivantes : 

```sql
SHOW CREATE TABLE `cac`;        -- met en évidence les clés étrangères et les index a supprimer pour libérer la colonne `id`
ALTER TABLE `last_high` DROP FOREIGN KEY FK_672E2009AA5DF1C9;   -- suppression d'une clé étrangère liée à cac.id
ALTER TABLE `user` DROP INDEX IDX_8D93D6495D69E1F5;             -- la même chose, mais pour un index
ALTER TABLE `cac` DROP `id`;    -- il est plus efficace de supprimer la colonne depuis l'interface phpMyAdmin : table cac > structure > supprimer
ALTER TABLE `cac` AUTO_INCREMENT = 1;
ALTER TABLE `cac` ADD `id` int UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST;
```

## Ajout de la librairie DataTable

Lors de la refonte de mon application Front-End, j'ai ajouté la librairie DataTable. Celle-ci dépendant de jQuery et Bootstrap 5 ne chargeant plus ce module Javascript par défaut, il m'a fallu procéder à l'installation de celui-ci de manière indépendante.

Après plusieurs essais infructueux, j'ai trouvé la procédure fonctionnelle suivante :

```bash
# installation des dépendances
$ npm install datatables.net
$ npm install datatables.net-bs5
$ npm install jquery
$ npm install webpack --save-dev
```

Dans mon fichier `webpack.config.js`, j'ai ajouté la modification suivante à la fin du script :

```js
Encore
    // ... instructions initiales...
    .addPlugin(new (require('webpack')).ProvidePlugin({
        $: 'jquery',
        jQuery: 'jquery',
    }))
;

module.exports = Encore.getWebpackConfig();
```

Il faut également veiller à importer les css de DataTable : 

```
@import '~bootstrap/scss/bootstrap';
@import 'datatables.net-bs5/css/dataTables.bootstrap5.css';
```

Après compilation et appel des datatables à partir du code html présent dans mon twig et le code Javascript faisant appel à jQuery, les objets de la librairie sont bien disponibles.

## Développement à réaliser

- Vérifier les données en session : il ne faut pas que les données d'un utilisateur soient confondues avec celles d'un autre.
- Vérifier la mise à jour et la vérification des positions : les données scrapées peuvent avoir été mise à jour sans que les positions d'un user aient été vérifiées (cas d'une mise à jour faite à partir de la session d'un autre utilisateur)
- Ajouter sur le dashboard le dernier Last High
- Ajouter une colonne avec le cours de clôture du Lvc dans le tableau du Cac (ou ajouter un tableau équivalent à celui de Cac ?)
- Modifier le design des positions
- Adapter le design à la hauteur de l'écran
- S'inspirer des exemples de dashboard réalisés en Vue.js pour créer celui de l'application
- Créer le front en Vue.js (on pourra utiliser par exemple la librairie [Element](https://element-plus.org/en-US/component/table.html#table-with-fixed-group-header) pour le tableau)
- Ajouter la notification par mail quand une position a été touchée pour que l'utilisateur passe effectivement les positions
- Ajouter un ascenseur pour afficher davantafge de données ?
- Mettre en surbrillance le plus haut et le plus bas (avec dezs couleurs différentes)
- Ajouter un menu paramètres (avec roue crantée) : ce sera un panneau qui glissera en fonction de son étéat ouvert ou fermé
- Ajouter un calcul du RSI et son graphique