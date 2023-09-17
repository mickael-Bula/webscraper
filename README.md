# choix de la version de php et installation avec limite de mémoire

Pour faire cette installation sur le laptop avec php 7.4 mais où deux versions de php sont installées avec par défaut php 8 :
- choisir la version à exécuter
- allouer plus de mémoire pour installer les dépendances avec create-project

```bash
php -v                      # pour vérifier la version par défaut
symfony local:php:list      # pour avoir la liste des versions disponibles et la version utilisée par symfony (variable d'env)
echo 7.3.13 > .php-version  # pour enregistrer la version dans un fichier (qui doit se trouver dans le répertoire courant)
symfony php -v              # pour utiliser la version autre que par défaut (celle conservée comme variable d'env par cmd symfony)

symfony php -d memory_limit=-1 C:\ProgramData\ComposerSetup\bin\composer.phar create-project symfony/website-skeleton webtrader
```

On peut voir ci-dessus que la commande est préfixée par `symfony`.
Ceci permet de charger la version sélectionnée dans les variables d'environnement de symfony et précisée plus haut.

Ensuite, il y a augmentation de l'espace mémoire de la version de php afin de permettre l'installation des dépendances.
Puis, je précise le path de la version de composer.phar pour le lancer, suivi de la commande `create-project`.

NOTE : ce problème n'est pas systématique, généralement l'installation se passe sans avoir à le spécifier.

## utilisation du scraper de Symfony

J'ai tenté d'ajouter Goutte à Symfony, mais il n'est pas reconnu dans cet environnement.
J'ai donc ajouté ses éléments :

```php
use Symfony\Component\BrowserKit\HttpBrowser;       // à la place de use Goutte\Client;
```

## Détermination des constantes utilisées dans l'application

Les valeurs qui définissent la taille et le niveau d'achat d'une ligne se trouvent dans l'entité Position. On pourrait également les définir dans le `.env`.

## Ajout d'un logger dans l'application

Monolog est installé par défaut dans l'application Symfony --webapp (complète)
Pour ajouter le logger, j'ajoute un channel et un hander dans le fichier monolog.yaml :

```yaml
    channels:
        - deprecation # Deprecations are logged in the dedicated "deprecation" channel when it exists
        - myapp     # ajout de mon channel. Pour l'utiliser, dans le controller il suffit d'appeler le logger $myAppLogger

    when@dev:
        monolog:
            handlers:
                main:
                    type: stream
                    path: "%kernel.logs_dir%/%kernel.environment%.log"
                    level: debug
                    channels: ["!event", "!app"]    # j'exclue les logs du channel app du fichier de logs principal (évite les doublons)
                myapp:
                    type: rotating_file # j'utilise un fichier de logs rotatif : il sera nommé donc nommé myapp<yyyy-mm--dd>
                    path: '%kernel.logs_dir%/myapp.log'
                    level: debug
                    max_files: 10
                    channels: ["app"]   # je déclare que mon channel myapp récupère uniquement les logs du channel app (l'applicatif)
```

Pour utiliser mon logger aisni défini, il me suffit d'injecter dans le controller le LoggerInterface que je nomme $myAppLogger (suivant le pattern nom du channel en camel-case + Logger).