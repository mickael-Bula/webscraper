# installation du projet sur un nouveau poste

```sh
git clone <projet>
cd <projet>
symfony composer install    # je précise `symfony` pour utiliser la version de php spécifiée dans les variables d'environnement de symfony CLI
cp .env .env.local          # ne pas oublier de renseigner le DATABASE_URL
```

Puis création de la base de données en lien avec les identifiants de DATABASE_URL.

Ne pas oublier de créer un user dédié dans un souci de sécurisation de la BDD

```sh
symfony php bin/console d:m:m
```

## utilisation de asset() dans Twig

Pour pouvoir utiliser asset() et faciliter la résolution des paths des fichiers CSS, JS et images, il m'a d'abord fallu installer webpack.
Sans cette installation, une erreur est lancée indiquant ceci :

```text
An exception has been thrown during the rendering of a template
("Asset manifest file "C:\Users\XXX\<project>/public/build/manifest.json" does not exist.").
```

Pour résoudre ce problème et pouvoir utiliser asset(), j'ai procéder à l'installation comme ceci ([source](https://github.com/symfony/symfony/discussions/45754)) :

```sh
symfony composer require symfony/webpack-encore-bundle  # juste pour m'asurer que le bundle est présent
npm -v          # et cela pour m'assurer que node est installé
npm install     # pour installer les modules listés dans le fichier package.json
npm run build   # et le lancement du build qui crée les fichiers nécessaires
```

Après cela, asset() fonctionne sans souci.