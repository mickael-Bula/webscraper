# Ajout d'un mode sombre

Pour modifier le thème de l'application, je décide d'utiliser la librairie `theme-toggle`.
Toutefois, cette librairie se contente de gérer l'animation et le style permettant la bascule entre les modes.
Pour compléter la fonctionnalité, il faut encore ajouter la palette css des différentes modes, ainsi que le javascript permettant de les appliquer.

Pour changer la palette, je cible l'élément `body`, auquel j'ai ajouté un dataset dont je module la valeur en javascript.

## Installation de la librairie

```bash
$ npm install theme-toggles@latest
```

J'ai rencontré des problèmes avec la checkbox qui gère de manière indépendante son état "checked", ce qui pose problème pour définir la position par défaut.
Ainsi, au changement de page, la valeur était perdue et des incohérences apparaissaient dans le rendu (l'icone ne correspondant pas toujours au thème courant).

Après quelques tentatives de résolutions infructueuses, j'ai modifié le code pour insérer le bouton proposé pour le même rendu et dont l'état est simplement géré par l'ajout d'une classe.

```css
/* importation des règles css du style utilisé dans le fichier assets/styles/app.scss */
@import '~theme-toggles/css/classic.css';
```

## Ajout d'un eventListener sur le bouton switchMode

Je récupère le thème courant dans le dataset posé sur l'élément `body` et qui vaut `light` par défaut.
Au clic sur le bouton, j'ajoute une classe sur l'élément ayant la classe `theme-toggle` pour afficher la bonne icône.
Je change également la valeur du dataset pour appliquer le thème courant.
Je lance ensuite une requête AJAX pour mettre à jour le thème en session, ceci afin de transmettre de page en page le thème choisi.

```js
if (darkMode.body.dataset.theme === "light") {
    document.querySelector(".theme-toggle").classList.add("theme-toggle--toggled");
    darkMode.body.dataset.theme = "dark";
    darkMode.updateSession("dark");
} else {
    darkMode.body.dataset.theme = "light";
    document.querySelector(".theme-toggle").classList.remove("theme-toggle--toggled");
    darkMode.updateSession("light");
}
```

Pour mettre à jour la session et récupérer la requête AJAX envoyée par `updateSession()`, j'ai créé un ThemeController :

```php
public function updateTheme(Request $request): JsonResponse
{
    $session = $request->getSession();
    $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);

    if (isset($data['theme'])) {
        $session->set('theme', $data['theme']);
    }

    return $this->json(['message' => 'Thème mis à jour avec succès.']);
}
```

## Ajout des styles pour les modes Light et Dark

Pour modifier les styles à la volée, je m'appuie sur des variables css, dont je déclare les valeurs pour les différents thèmes :

```css
[data-theme="light"] {
    --bg-color: #EDEEF1;
    --links: #000000;
}

[data-theme="dark"] {
    --bg-color: #21232f;
    --links: #FFFFFF;
}

body {
    background-color: var(--bg-color);
    font-family: 'Roboto', sans-serif;
    transition: 350ms ease-in-out;
}
```

Ne reste plus ensuite qu'à déclarer les différentes valeurs qui seront utilisées dans les thèmes clair et sombre.

>NOTE : certaines classes Bootstrap (bg-primary) ne répondent pas à la surcharge CSS

## Autre design pour le Dark Mode

Le design fourni par la librairie a un inconvénient : au changement de page en mode sombre, l'animation est jouée, bien qu'inutile...

Une source intéressante à tester pour remplacer la librairie actuelle : https://janessagarrow.com/blog/css-dark-mode-toggle/