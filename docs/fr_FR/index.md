---
layout: default
lang: fr_FR
title: Jeedom | Plugin NetatmoOpenData
---

# Description
Plugin permettant de récupérer les relevés météo des stations Netatmo (même si vous n'en possédez pas vous-même !). Le choix des stations à récupérer se fait à partir de **vos stations météo favorites** de votre compte (gratuit) Netatmo. Création et gestion sur [https://weathermap.netatmo.com](https://weathermap.netatmo.com)

# Pré-requis 
- Avoir un compte (gratuit) sur [https://weathermap.netatmo.com](https://weathermap.netatmo.com) 
- Avoir de 1 à 5 stations météo, ajoutée(s) en favoris

# Configuration

## Liaison entre Jeedom et Netatmo
Une fois le plugin installé, dans la page de Configuration du plugin (Icône "Configuration"), 2 choix s'offrent à vous pour récupérer les données de vos stations favorites. 

### Méthode n°1 : "L'application hébergée"
Cliquez sur le bouton `Autoriser l'application hébergée` pour autoriser l'application hébergée "voisins_data" a récupérer vos `tokens` d'accès. La permission demandée est "read_station" permettant uniquement la lecture des données de vos stations.

⚠️ Elle est développée et hébergée par l'auteur de ce plugin, en dehors de l'écosystème Jeedom. 

Une nouvelle fenêtre s'ouvre, vous autorisez l'application "voisins_data" sur le site de Netatmo, puis vous verrez l'icône verte : ✅. Passez à l'étape suivante : `Tester la liaison`.

### Méthode n°2 : "Votre application"
⚠️ Votre Jeedom doit avoir un accès externe configuré. 

Vous devez créer un `client_id` et un `client_secret` générés depuis : [https://dev.netatmo.com](https://dev.netatmo.com).

Pour cela, vous créez votre application (gratuitement) : [https://dev.netatmo.com/apps/createanapp#form](https://dev.netatmo.com/apps/createanapp#form)

![](../screenshot/netatmo_clientid.png)

Une fois le plugin installé, renseignez les champs :

- `Client ID` : votre Client ID
- `Client secret` : votre Client secret

Puis cliquer sur `Autoriser votre application Netatmo` pour lier votre compte.

##Tester la liaison
Toujours dans la page de Configuration, vous avez le `Statut` de la laison : `OK` ou `NOK`. Des actions sont possibles :

- `Tester la liaision` pour forcer la récupération des `tokens` d'accès.
- `Débrancher` pour supprimer les `tokens` mémorisés préalablement.

Dans le plugin, le bouton `Synchroniser` : permet à votre Jeedom de découvrir automatiquement vos stations favorites Netatmo : la votre et celles de vos voisins. 

# FAQ
- Quelle est la différence entre la liasion via "L'application hébergée" et "Votre application" ?
>Dans les 2 méthodes, les **données des stations sont récupérées directement depuis Netatmo**. (Votre Jeedom > serveur de Netatmo). 
>- La version "Votre application" requière la configuration d'un `Client ID`, d'un `Client secret` et un accès externe à Jeedom. Les flux des données se font uniquement entre votre Jeedom et les serveurs de Netatmo. Ceci est la liaison d'origine du plugin.
>- La version "L'application hébergée" s'appuie sur une application tierce - hébergée en déhors de l'écosystème Jeedom - pour obtenir vos `tokens` d'accès. Ce serveur privé stocke ces `tokens` et permet de les renouveler automatiquement pour vous. Aucune donnée liée à vos stations et vos données personnelles transitent sur ce serveur. Le script complet est publié sur [GitHub](https://github.com/jim005/jeedom-netatmoPublicData-hostedApp).

- Si le serveur de "l'application hébergée" est hors-service ? 
> Les données météo de vos stations continueront a être récupérer - tous les 15 minutes - jusqu'à expiration de vos `tokens` d'accès (ils sont valides pour 3 heures maximum).
> 
> Sans `tokens` d'accès valides, Jeedom vous notifiera d'échec de récupération via Centre de Messages. 
>
> Vous pouvez - à tout moment - changez le mode de liaison pour basculer. Pour basculer sur l'autre méthode sans perdre votre configuration Jeedom.

- Que stoke cette "'application hébergée" ? 
> Les données stockées sont : un identifiant de votre Jeedom (dédié à ce plugin), `access token`, `refresh token` et quelques dates. Le script complet est publié sur [GitHub](https://github.com/jim005/jeedom-netatmoPublicData-hostedApp).

- Puis-je changer de méthode de connexion sans permettre mes stations ?
>Oui. Vous pouvez changer de méthode comme vous voulez. Aucun impact sur vos Stations (Equipement) déjà crées et configurées dans vos Scénarii.

- Est-ce que le plugin s'appuie sur des API tiers ?
>Oui, le plugin utilise les API de Netatmo pour récupérer les données de vos stations météo favorites

- Quel est le délai de mise à jour ? 
> Toutes les 15 minutes, par la tâche Cron Jeedom. 

- Où puis-je gérer (ajouter / supprimer) des stations météos ? 
> Uniquement depuis [https://weathermap.netatmo.com](https://weathermap.netatmo.com) . Une fois connecté, vous pouvez mettre des stations dans vos *favoris*. 
>Lancer une synchronisation, et les équipements sont crées (actif + visible). 

- Si je supprime une station météo de mes *favoris* sur le site Netatmo, que se passe-t-il ? 
> Au prochain lancement de la "Synchronisation" (manuelle), l'Équipement est *désactivé*, mais reste présent dans Jeedom. Vous pouvez le supprimer manuellement.   

- Pourquoi il y une "étoile" (*) en préfix du nom de la station ?  
> Le nom initial de l'Équipement est une concaténation  : 'Nom de la ville' + 'petit nom donné par le propriétaire' + '*' afin d'indiquer que c'est le nom depuis Netatmo. 
>Vous pouvez renommer vos équipements, il sera conservé lors des prochaines `Synchronisation`.   

- Vous avez un bug JS 'Uncaught TypeError: cmd.find(…).delay(…).animate is not a function' ? 
> Ce plugin utilise le widget Rain (natif à Jeedom v4). Mais certains autres plugins rentrent en conflit avec ce nouveau widget. 
> Solution : mettre à jour vos plugins (ex : 'horlogehtc', etc.)

- Les données ne remontent pas automatiquement ? Malgrès l'activation du cron15
> Il y a un conflit (de chargement de dépendance technique) avec d'autres plugins comme NetatmoPro. 
> Solution : mettre à jour vos plugins (ex : 'NetatmoPro', etc.). 
 

# Exemple

## Dashboard (Widgets natifs v4)
![](../screenshot/NetatmoOpenData_dashboard_widget_v2.png)
![](../screenshot/NetatmoOpenData_dashboard_widget.png)

## Équipements
![](../screenshot/NetatmoOpenData_equipment.png)

## Commandes
![](../screenshot/NetatmoOpenData_command.png)

