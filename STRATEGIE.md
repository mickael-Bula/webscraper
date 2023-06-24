# Récapitulatif de la stratégie

La stratégie mise en place est détaillée dans la S 3.5.

J'en reprends ici les grandes lignes.

## Définition d'une position

J'entends par *position* l'ouverture d'un cycle d'achat de trois lignes, prises à chaque variation de -2 %.

## Définition d'une ligne

J'entends par *ligne* l'achat de LVC pour un montant de 750 €.

## Seuil d'achat

Je fixe le niveau d'achat de la première ligne d'une nouvelle position à une baisse de 6 % depuis le dernier plus haut local.

Les écarts entre les 3 lignes d'achat étant de 2%, ce seuil permet d'avoir des écarts homogènes entre les différents niveaux.

Lorsque le seuil d'achat est touché, donc à l'achat de la première ligne, ce seuil devient le nouveau plus haut.
A partir de ce plus haut, une nouvelle limite d'achat est calculée 6% plus bas, avec des achats sur 3 niveaux séparés de 2%.

## Trade

J'entends par *trade* l'achat d'une ou plusieurs lignes en rapport avec un plus haut. Un trade peut donc comporter jusqu'à 3 lignes.
Un trade est clôturé à la revente de la dernière de ces lignes.

## Trades multiples

Chaque *trade* étant lié à un plus haut particulier, et chaque entrée dans un *trade* se caractérisant par la définition d'un nouveau plus haut avec ses lignes, plusieurs *trade* peuvent coexister.

## Seuil de revente

Fixé, par symétrie, à +6 %.

## Plus haut local

J'entends par *plus haut local* la cotation la plus élevée atteinte par le CAC depuis la dernière prise de position si elle existe.

Si cette dernière n'existe pas, on pourra fixer le plus haut local original au plus haut atteint lors de la dernière cotation disponible.

Une autre solution pourra consister à la saisir directement dans un formulaire de l'interface, de manière à la fois plus arbitraire et plus fine.

## Evolutions possibles

On pourra affiner le niveau d'achat initial à l'aide du RSI. On pourra, par exemple, conditionner le LAST_HIGH à un RSI > 65.
Autrement dit, on pourrait attendre un RSI J > 65 pour confirmer un LAST_HIGH et donc lancer les ordres sur la BUY_LIMIT.