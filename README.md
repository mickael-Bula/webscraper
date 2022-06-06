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
Ceci permet de charger la version sélectionnée dans les variables d'environnement de symfony et précisé plus haut.

Ensuite, il y a augmentation de l'espace mémoire de la version de php afin de permettre l'installation des dépendances.
Puis, je précise le path de la version de composer.phar pour le lancer, suivi de la commande `create-project`.

NOTE : ce problème n'est pas systématique, généralement l'installation se passe sans avoir à le spécifier.
