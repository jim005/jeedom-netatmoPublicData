---
layout: default
lang: en_US
title: Jeedom | Plugin Netatmo OpenData
---

# Description
This plugin retrieve weather reports from Netatmo stations. From favorite weather stations in your (free) Netatmo account. Creation and management on https://weathermap.netatmo.com

# Configuration

Une fois le plugin installé, il vous faut renseigner vos informations deconnexion Netatmo :

- Client ID : votre client ID (voir partie configuration)
- Client secret : votre client secret (voir partie configuration)
- Nom d’utilisateur : nom d’utilisateur de votre compte netatmo
- Mot de passe : mot de passe de votre compte Netatmo

Synchroniser : permet de synchroniser Jeedom avec votre compte Netamo pour découvrir automatiquement vos stations "favorites" Netamo, à vous ou à de vos voisins. A faire après avoir sauvegardé les paramètres précedent.

### Recupération des informations de connexion

Pour intégrer votre Welcome, vous devez posséder un `client_id` et un `client_secret` généré sur le site http://dev.netatmo.com. 

Pour cela vous devez créer votre application (gratuit) : https://dev.netatmo.com/apps/createanapp#form


![](../screenshot/netatmo_clientid.png)

# FAQ
-   Est-ce que le plugin s'appuie sur des API tiers ?

>Oui, le plugin utilise les API de Netatmo pour récupérer les données de vos stations météo favorites

-   Quel est le délai de mise à jour ? 
> Toutes les 15 minutes, par la tâche Cron Jeedom. 


- Où puis-je gérer (ajouter / supprimer) des stations météos ? 
> Uniquement depuis https://weathermap.netatmo.com . Une fois connecté, vous pouvez mettre des stations dans vos *favoris*. Lancer une synchronisation, et les équipements sont crées (actif + visible). 


- Si je supprime une station météo de mes favoris, que se fasse t-il ? 
> L'équipement est *désactivé*, mais reste présent dans Jeedom. Vous pouvez le supprimer manuellement.   


#Exemple

## Dashboard (Widget natif v4)
![](../screenshot/NetatmoOpenData_dashboard_widget.png)

## Equipements
![](../screenshot/NetatmoOpenData_equipment.png)

## Commandes
![](../screenshot/NetatmoOpenData_command.png)
