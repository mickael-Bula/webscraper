# CHANGELOG : Journal des modifications

2023-09/17 : Ajoute un logger spécifique à l'application et dont on peut consulter les logs dans `var/log/myapp-yyyy-mm-aa`.

2023-09-20 : Ajoute une colonne supplémentaire dans le tableau de bord de la page Dashboard pour présenter les 10 derniers cours de clôture du Lvc.

2023-10-01 : Ajoute un champ LastCacU^dated dans la table User pour conserver une référence vers l'entité Cac en base lors de la dernière connexion de l'utilisateur. Si aucune référence n'est trouvée en base, on lui affecte par défaut la référence la plus récente.

