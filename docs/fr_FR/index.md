---
layout: default
lang: fr_FR
title: Jeedom | Plugin Netatmo OpenData
---

# Description
Plugin permettant de récupérer les relevés météo des stations Netatmo. A partir des stations météo favorites de votre compte (gratuit) Netatmo. Création et gestion sur https://weathermap.netatmo.com 

# Configuration

Une fois le plugin installé, il vous faut renseigner vos informations de connexion Netatmo :

- Client ID : votre client ID (voir partie configuration)
- Client secret : votre client secret (voir partie configuration)
- Nom d’utilisateur : nom d’utilisateur de votre compte Netatmo
- Mot de passe : mot de passe de votre compte Netatmo

Synchroniser : permet de synchroniser Jeedom avec votre compte Netamo pour découvrir automatiquement vos stations "favorites" Netamo, à vous ou à de vos voisins. A faire après avoir sauvegardé les paramètres précédents.

### Recupération des informations de connexion

Pour intégrer votre Welcome, vous devez posséder un `client_id` et un `client_secret` généré sur le site http://dev.netatmo.com. 

Pour cela vous devez créer votre application (gratuit) : https://dev.netatmo.com/apps/createanapp#form


![](../screenshot/netatmo_clientid.png)

# FAQ
- Est-ce que le plugin s'appuie sur des API tiers ?

>Oui, le plugin utilise les API de Netatmo pour récupérer les données de vos stations météo favorites

- Quel est le délai de mise à jour ? 
> Toutes les 15 minutes, par la tâche Cron Jeedom. 


- Où puis-je gérer (ajouter / supprimer) des stations météos ? 
> Uniquement depuis https://weathermap.netatmo.com . Une fois connecté, vous pouvez mettre des stations dans vos *favoris*. 
>Lancer une synchronisation, et les équipements sont crées (actif + visible). 

- Si je supprime une station météo de mes favoris, que se passe-t-il ? 
> L'équipement est *désactivé*, mais reste présent dans Jeedom. Vous pouvez le supprimer manuellement.   

- Pourquoi il y une "étoile" * en préfix du nom de la station ?  
> Afin d'indiquer que c'est le nom depuis Netatmo. Vous pouvez renommer vos équipements, 
>le nom ne sera pas altérer lors de la prochaine synchronisation.   

- Vous avez un bug JS 'Uncaught TypeError: cmd.find(…).delay(…).animate is not a function' ? 
> Ce plugin utilise le widget Rain (natif à Jeedom v4). Mais certains autres plugins rentrent en conflit avec ce nouveau widget. 
> Solution : mettre à jour vos plugins (ex : 'horlogehtc', etc.)

- Les données ne remontent pas automatiquement ? Malgrès l'activation du cron15
> Il y a un conflit (de chargement de dépendance technique) avec d'autres plugins comme NetatmoPro. 
> Solution : mettre à jour vos plugins (ex : 'NetatmoPro', etc.). 
 

# Exemple

## Dashboard (Widgets natifs v4)
![](../screenshot/NetatmoOpenData_dashboard_widget.png)

## Equipements
![](../screenshot/NetatmoOpenData_equipment.png)

## Commandes
![](../screenshot/NetatmoOpenData_command.png)
