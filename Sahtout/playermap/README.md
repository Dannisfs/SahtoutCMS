# About this fork

This fork contains the following changes:

- Support for High Elf custom race.
- Change configuration to an external .conf file, instead of having it hardcoded.
- Runs with Docker. No need to install tools on the host.

## Configure

1. Copy config/playermap.conf.dist into playermap.conf in a directory of your preference, and change the settings accordingly. Don't leave your configuration file in the app directory, it may get exposed.
2. You can change the configuration file while the app is running.

## Build

For linux, mac or WSL, run the build.sh utility:

```
cd /path/to/playermap
sh ./build.sh
```

## Run

Replace `</config/file/path>` with the directory where your playermap.conf is located:

```
docker run -d --restart unless-stopped -t -p 80:80 -v </config/file/path>:/etc/playermap playermap:latest
```

## Future work

- Allow realm selection
- Modernize the app code
- Modernize the app UI

# AzerothCore Playermap (Original Readme)

![Eastern Kingdom](https://raw.githubusercontent.com/azerothcore/playermap/master/img/showcase/eastern_kingdom.png)  
![Outland](https://raw.githubusercontent.com/azerothcore/playermap/master/img/showcase/outland.png)  
![Northrend](https://raw.githubusercontent.com/azerothcore/playermap/master/img/showcase/northrend.png)  

## Overview

This project provides a php based app that shows where all the players/playerbots are in the server. By default AzerothCore saves player position on logout and every 15 minutes.

## Configure and setup

(Removed in this fork, see section above)

Done!

## Credits

- Abracadaniel22 (This fork)
- Dustin Hendrickson (Fixed 2024)  
- Dmitry Koterov (original author)  
- Helias (old maintainer)  