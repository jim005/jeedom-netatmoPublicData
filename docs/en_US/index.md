---
layout: default
lang: en_US
title: Jeedom | Plugin Netatmo OpenData
---

# Description
This plugin retrieve weather reports from Netatmo stations. From favorite weather stations in your (free) Netatmo account. Creation and management on [https://weathermap.netatmo.com](https://weathermap.netatmo.com)


# Configuration
Once the plugin is installed, you need to fill in your Netatmo connection information:

- `Client ID`: your client ID (see configuration section)
- `Secret client`: your secret client (see configuration section)

Then, click on `Association Netatomo` to link with your account.

Button `Synchronize`: allows your Jeedom to discover your "favorite" Netatmo stations, to you or to your neighbors. To do after having saved the previous parameters.

### Retrieving connection information
To integrate your Welcome, you must have a `client_id` and a` client_secret` generated from [https://dev.netatmo.com](https://dev.netatmo.com).

For this you must create your application (free): [https://dev.netatmo.com/apps/createanapp#form](https://dev.netatmo.com/apps/createanapp#form)


![](../screenshot/netatmo_clientid.png)

# FAQ
- Does the plugin rely on third-party APIs?
> Yes, the plugin uses the Netatmo APIs to retrieve data from your favorite weather stations

- When data is updated ?
> Every 15 minutes, by the Jeon Cron task.

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
