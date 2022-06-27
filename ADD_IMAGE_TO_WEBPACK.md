# procédure pour ajouter des images en utilisant webpack

L'idée est d'utiliser les services de webpack pour rendre disponibles les images dans le build.
Après quelques recherches, la procédure est finalement assez simple.

Il faut :
- charger l'image et l'enregistrer dans `assets/images/<image.jpg>`
- l'utiliser dans le scss
- l'importer dans un fichier js pour qu'il soit requis au build

```scss
 .CAC:before {
  background-image: url("../images/<image.jpg>");
}
```

```js
// assets/app.js

import './images/<image>';
```
