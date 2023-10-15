# CHANGELOG : Journal des modifications

AAAA-JJ-MM : pattern des dates

2023-09-17 : Ajoute un logger spécifique à l'application et dont on peut consulter les logs dans `var/log/myapp-yyyy-mm-aa`.

2023-09-20 : Ajoute une colonne supplémentaire dans le tableau de bord de la page Dashboard pour présenter les 10 derniers cours de clôture du Lvc.

2023-01-10 : Ajoute un champ LastCacUpdated dans la table User pour conserver une référence vers l'entité Cac en base lors de la dernière connexion de l'utilisateur. Si aucune référence n'est trouvée en base, on lui affecte par défaut la référence la plus récente.

2023-07-10 : Modifie l'alogorithme de mise à jour ou de création des positions en attente. Si le nombre de positions en attente est différent de 0 ou de 3, c'est qu'au moins une position a été passée en iRunning et on ne met pas à jour pour geler la buyLimit.

2023-07-10 : Modifie la méthode checkIsWaitingPositions() pour récupérer uniquement les positions isWaiting lorsqu'elles sont au nombre de trois pour une même buyLimit. Cela évite de supprimer des positions en attente appartenant à un cycle d'achat en cours, c'est-à-dire pour lequel au moins une position a été passée en isRunning.

2023-07-10 : Modifie la méthode saveDataInDatabase::updateIsWaitingPositions() pour transmettre à la méthode setHigher() un objet cac contemporain du lvc reçu en argument de la méthode updateIsWaitingPositions(). J'ajoute également l'enregistrement du lashHigh créé dans setHigher() après l'hydratation des données du lvc pour que la sauvegarde soit possible et que l'id puisse être transmis au user qui sauvegarde également ce lastHigh.
