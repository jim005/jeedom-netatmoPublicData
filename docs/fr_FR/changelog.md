# Changelog : Netatmo OpenData

// TODO
- Ajout ( OU PAS ) du support de VOS modules intérieurs ( de type:  NAModule4 : Température and Humidité ). (Thanks to https://github.com/c4software )
- Informer si une station a de nouveaux capteurs. (ex: ajout d'un anémomètre par un gentil voisin)
- Optimisez sur 4.4 beta (qui ne gère pas encore les dépendances composer.json)

# Août 2023
- La version Jeedom Core 4.2 est un pre-réquis. Suppression du support pour les versions inférieures. 
- Suppression du SDK Netatmo au profit de l'usage des librairies génériques (natif en 4.3, embarqué ici pour 4.4).
- Nouvelle méthode d'authentification - supplémentaire - avec l'application hébergée sur mon serveur. Cette nouvelle méthode ne requière plus le CLIENT_ID, et CLIENT_SECRET !
- Corrections diverses.

# Juillet 2023
- Changement de la méthode d'authentification : ajout d'un bouton "Association". Suppression des champs mots de passe et e-mail. Merci à @thanaus ❤ pour ses morceaux de codes.️

# Juillet 2022
- Amélioration de l'interface par @Salvialf ❤️

# Mars 2021 
- Ajout d'une option pour supprimer l'envoi d'alerte dans le Centre de Message.

# Février 2021
- Réduction des valeurs maximum de la Pluie, pour les widgets. Mais l'utilisateur peut changer les valeurs, sans être écrasé par les futures "Synchronisation" (Merci [@thienell](https://community.jeedom.com/u/thienell)

## Janvier 2021
- Suppression de 90% des messages envoyés dans le Centre des Messages (ouf !)
- Le button "Synchronisation" supprime les Commandes qui ne sont pas plus disponibles (Ex : votre voisin n'a pas remis de pîles dans son module extérieur).
- Intégration des données extérieures de VOTRE station (pour les chanceux).
- Ajout d'un TimeOut natif de Jeedom de 60 min sur chaque Equipements. Vous pouvez voir les alertes dans : Analyses > Equipements > Equipements en alerte . (Exemple : votre voisin coupe le Wifi la nuit... vous n'avez pas de données pendant son sommeil).
- Refonte de la mise à jour des données.

Un grand merci à l'[université d'Aix-Marseille](http://www.gap.univ-mrs.fr/miw/) (Campus de Gap, Licence Pro Internet MIW ) qui m'a prêté une station Netatmo pour optimiser ce plugin. 

## Octobre 2020
- Ajout de traductions

## 13 Juin 2020  
- Notifications dans le Centre de Message en cas de données non-disponibles

## 07 juin 2020
- Remplacement de la date de récupération des valeurs, par celle fournis par Netatmo UTC
- Suppression de la gestion des modules additionnels, type NAModule4 ( CO2, Température et Humidité )
- Optimisation du code

## 24 mai 2020 
- Ajustement de la taille des widgets à la création
- Optimisation des widgets pour V3

## 23 mai 2020
- Ajout de nouvelles données : rafales de vent (vitesse et direction), pluie sur 1h, pluie sur la journée

## 16 mai 2020
- 1ère version in 'beta'
