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