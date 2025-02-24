[![License: CC BY-NC-SA 4.0](https://img.shields.io/badge/License-CC%20BY--NC--SA%204.0-lightgrey.svg)](https://creativecommons.org/licenses/by-nc-sa/4.0/)

# Compatibility

Website is supposed to work for Chromium 88+, Firefox 93+ and iOS 16+.

# Setting it up

After cloning the repository, install composer dependencies. (`composer install`)

Also, make sure your hosts file is configured, for example you can add :
```properties
127.0.0.1 local-teacherstory.com www.local-teacherstory.com api.local-teacherstory.com res.local-teacherstory.com oauth.local-teacherstory.com mta-sts.local-teacherstory.com ws.local-teacherstory.com
```

Most logs will be in .serv/logs.

## Using Nix (Windows(WSL) / Linux)

There's a shell.nix file at the root of the project directory.

`nix-shell --pure`

Edit the .env file so that important variables are set. (SSL, Database, Redis...)

Make sure to run the servers with the php config file, example :

`php -c $TMP/configs/php.ini startHTTPServer.php`

The configs, and other datas can be found in your temp directory.

## Windows (Without using Nix)

Tested with having MariaDB installed on Windows and having a Debian/Ubuntu22 image in WSL. This is for when you want a WSL image dedicated for working on this project.

1. To initialize your MariaDB database, run ".serv/db/create_tables.sql" and ".serv/db/init_tables.sql".
2. Execute wsl_init_full.sh to install all the necessary dependencies and set up the environment.

    On Debian: `sh .serv/scripts/debian-12/wsl_init_full.sh` then restart WSL to make sure systemd is running.

    On Ubuntu: `bash .serv/scripts/ubuntu-22/wsl_init_full.sh`
3. Make a .env file alongside ".env-example", and set important variables. (SSL, Database, Redis...).
4. Run the HTTP server with `php startHTTPServer.php`. If it can't run for some reason, check the errors in .serv/logs/php-error.log.

## Changing ports

If you're in an environment where you can't use the default ports, you can change them by setting these environment variables (using `export var=value`) :
```
TEACHERSTORY_HTTP_PORT
TEACHERSTORY_HTTPS_PORT
TEACHERSTORY_WSS_PORT
```
Also in your .env file, you can update the LD_LINK vars, example :
```properties
LD_LINK_API="https://api.local-teacherstory.com:[yourport]"
```

## Tools

You can open the contents of .serv/bruno with [Bruno](https://github.com/usebruno/bruno) to test/explore the API.

Consider using [mkcert](https://github.com/FiloSottile/mkcert) to not have the restrictions of self-signed certificates.