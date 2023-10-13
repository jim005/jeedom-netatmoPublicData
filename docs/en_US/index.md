---
layout: default
lang: en_US
title: Jeedom | Plugin NetatmoOpenData
---

# Description
Plugin for retrieving weather data from Netatmo stations (even if you don't own one yourself!). You choose which stations to retrieve from **your favorite weather stations** in your (free) Netatmo account. Creation and management on [https://weathermap.netatmo.com](https://weathermap.netatmo.com)

# Prerequisites 
- Have a (free) account on [https://weathermap.netatmo.com](https://weathermap.netatmo.com) 
- Have 1 to 5 weather stations added as favorites

# Configuration

## Connection between Jeedom and Netatmo
Once the plugin has been installed, go to the Plugin Configuration page ("Configuration" icon). You have 2 choices for retrieving data from your favorite stations. 

### Method n°1: "Hosted application" (simple)
Click on the `I authorize the application to access my favorite Netatmo stations` button to authorize the NetatmoPublicData hosted application to retrieve your access `tokens`. 

A new window opens, you authorize the Netatmo application, then you'll see the green icon: ✅. Go on to the next step, "Test the connection".

### Method 2: "Your application" (expert)
Your Jeedom must have external access configured. 
You need to create a `client_id` and a `client_secret` generated from [https://dev.netatmo.com](https://dev.netatmo.com).

To do this, create your application (free of charge): [https://dev.netatmo.com/apps/createanapp#form](https://dev.netatmo.com/apps/createanapp#form)

![](../screenshot/netatmo_clientid.png)

Once you've installed the plugin, you'll need to fill in :

- Client ID: your client ID
- Client secret`: your secret client

Then click on `Association Netatmo` to link your account.

##Test the link
Still on the Configuration page, you have the `Status` of the link: `OK` or `NOK`. Different actions are possible depending on the status:

- `NOK` you can `Test the link` which attempts a recovery of the access `tokens`. 
- If `OK` is selected, you can `Unplug` to delete previously stored `tokens`.

In the plugin, the `Synchronize` button: allows your Jeedom to automatically discover your "favorite" Netatmo stations, yours and those of your neighbors. 

# FAQ
- What's the difference between the "Hosted application" method and the "Your application" version?
>In both methods, station data are retrieved directly from Netatmo. (Your Jeedom > Netatmo server). 
>- The "Your application" version requires configuration of a `Client ID`, a `Client secret` and external access to Jeedom. Data flows only between your Jeedom and Netatmo servers. This is the initial configuration method.
>- The "Hosted application" version relies on a single hosted application to obtain access `tokens`. My server stores these `tokens` and automatically renews them for you. No data linked to your stations, just your personal data in transit. The full script will be published on Github soon.

- Can I change my connection method without enabling my stations?
>Yes, you can change your connection method as you like. No impact on your Stations (Equipment) already created and configured in your Scenarios.

- Does the plugin use third-party APIs?
>Yes, the plugin uses Netatmo APIs to retrieve data from your favorite weather stations.

- How long does it take to update? 
> Every 15 minutes, via the Cron Jeedom task. 

- Where can I manage (add / delete) weather stations?
> Only from [https://weathermap.netatmo.com](https://weathermap.netatmo.com). Once connected, you can put stations in your * favorites *. Start synchronization, and the devices are created (active + visible).

- If I delete a weather station from my favorites, what happens?
> The equipment is * deactivated *, but remains present in Jeedom. You can delete it manually.

- Why equipment's name has 'star' ( * ) as prefix ? 
> Just to highlight that this name come from Netatmo. You could rename it, it won't be override on the next synchronisation.  

- You've got a JS error like 'Uncaught TypeError: cmd.find(…).delay(…).animate is not a function' ? 
> This plugin use new widget from Jeedom v4. But some others plugins create conflict with those widget. 
> Please, update yours plugins, developers have fixed it. (eg : horlogehtc )

- Data are not updated, even with cron15 enabled ?
> There are some technical background conflict with others plugins.
> Please, update yours plugins, developers have fixed it (eg : NetatmoPro ) 
 

# Example

## Dashboard (Widget natif v4)
![](../screenshot/NetatmoOpenData_dashboard_widget_v2.png)
![](../screenshot/NetatmoOpenData_dashboard_widget.png)

## Equipments
![](../screenshot/NetatmoOpenData_equipment.png)

## Commands
![](../screenshot/NetatmoOpenData_command.png)
