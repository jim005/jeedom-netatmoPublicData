# Changelog : NetatmoOpenData

# November 2023 
- Code optimization when token has expired. => less message in Message Center :-) 

# August 2023
This new version brings only technical optimizations for the link with Netatmo: 
- Jeedom Core 4.2 is a prerequisite. Removal of support for lower versions. 
- Removal of the hourly Cron. No more 16 calls to Netatmo per day.  
- Netatmo SDK no longer required, instead using generic libraries (native in 4.3, embedded here for 4.4).
- New - additional - authentication method with the application hosted on my server. This new method no longer requires CLIENT_ID and CLIENT_SECRET!
- Miscellaneous corrections.

# July 2023
- Authentication method changed: "Association" button added. Removed password and e-mail fields. Thanks to @thanaus ❤ for his code snippets.️

# July 2022
- UI improvement, thanks to @Salvialf ❤️

# March 2021 
- Add option to remove alert Message, in Message Center. 

# February 2021
- Decrease maximum Rain value for widget display. User can customize it without impact during next "Synchronization" ( Thanks [@thienell](https://community.jeedom.com/u/thienell)

## January 2021
- Decrease of message sent to 'Message center'
- "Synchronization" button remove Commands which values not reachable any more
- YOUR weather station can be display (lucky guys), only for public data.
- Add Timeout of 60 min from Jeedom on each Equipment. You can see alert on this page :  Analysis >  Equipments > Equipments on alert.
- Reformat code to update values

Big Thanks to [Aix-Marseille university](http://www.gap.univ-mrs.fr/miw/) (Location : Gap. Licence Pro Internet MIW)  which lend me an Netatmo Station to optimize this plugin.

## October 2020
- Add translations

## 13 june 2020
- Notification in Message Center when data's is not reachable

## 07 june 2020
- Remove management of additional module, type NAModule4 ( CO2, Température et Humidité )
- Change update time value, with Netatmo value UTC
- Code optimization 

## 24 may 2020
- Adjust widget size on creation
- Widget's optimization for V3 

## 23 may 2020
- Add new data  : Gust Strength,  Gust Angle, Rain on the last hour and rain for the day

## 16 may 2020
- First release in 'beta'
