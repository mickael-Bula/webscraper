# Installation de Glitchtip

J'utilise la version de docker de Glitchtip, ce qui signifie que l'installation de docker est un prérequis.
La procédure d'installation est disponible sur le site de [Glitchtip](https://glitchtip.com/documentation/install).

Après avoir créé le fichier `docker-compose.yaml` avec le contenu du fichier d'exmple proposé, j'y ai simplement modifié la ligne suivante :

```yaml
GLITCHTIP_DOMAIN: http://127.0.0.1:8000
```

Après avoir construit et la c"é le container avec la commande`docker-compose up -d`, j'ai déclaré le DSN récupéré après le lancement de Glitchtip et la configuration du projet :

`###> sentry/sentry-symfony ###
SENTRY_DSN='dsn_fourni_sue_le_site'
###< sentry/sentry-symfony ###`

## Troubleshooting

J'ai eu une erreur`InvalidArgumentException` au lancement de Glitchtip :

`There is no extension able to load the configuration for "sentry" (in "C:\wamp64\www\webtrader\config/packages/sentry.yaml"). Looked for namespace "sentry", found ""framework", "doctrine", "doctrine_migrations", "debug", "twig", "web_profiler", "webpack_encore", "twig_extra", "security", "monolog", "maker", "sensio_framework_extra"" in C:\wamp64\www\webtrader\config/packages/sentry.yaml (which is being imported from "C:\wamp64\www\webtrader\src\Kernel.php").`

Pour la résoudre, j'ai modifié une ligne dans le fichier `config/bundles.php` en passant la clé 'prod' à 'all' :

```php
Sentry\SentryBundle\SentryBundle::class => ['all' => true],
```

## Limitation du niveau des logs récupérés par Glitchtip

Pour alléger le nombre de logs récupérés, j'ai ajouté cette configuration dans le fichier `monolog.yaml` : 

```yaml
when@dev:
    monolog:
        handlers:
          # ...
            sentry:
                type: 'sentry'
                level: info     # limite le niveau de log
```

