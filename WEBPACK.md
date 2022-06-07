# intégration de Sass et Bootstrap

Pour installer Sass et Bootstrap, j'ai configuré de la sorte :

```sh
mv assets/styles/app.css assets/styles/app.scss
yarn add node-sass sass-loader --dev
```

J'ai décommenté la ligne suivante dans webpack.config.js :

```js
.enableSassLoader()
```

Puis installation de Bootstrap :

```sh
yarn add bootstrap @popperjs/core bs-custom-file-input --dev
```

Et ajout dans le fichier assets/styles/app.scss :

```sh
@import '~bootstrap/scss/bootstrap';
```

Enfin, configuration des formulaires pour utiliser les styles de Bootstrap dans le fichier config/packages/twig.yaml :

```yaml
twig:
    form_themes: ['bootstrap_5_layout.html.twig']
```

Pour lancer la compilation à chaque changement et générer les assets :

```sh
symfony run -d yarn dev --watch
```