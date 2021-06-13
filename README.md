# OCB, the Bootstrapper and Docker container for October CMS 

OCB, the fastest way to get ready for your next octobercms v2 project development.

`ocb` is a command line tool and a docker container that enables you to reconstruct an October CMS installation
from a single configuration file and run it in place.

It can be used to quickly bootstrap a local development environment for a project.

Since version 2.0, it uses php8 with php-fpm and nginx. 

If you are looking for a wintercms Docker container, use [foersterwerbung/wcb](https://github.com/foerster-werbung/wcb) instead.

## Features

### ocb
* Installs and updates private and public plugins (via Git or Marketplace)
* Makes sure only necessary files are in your git repo by intelligently managing your `.gitignore` file
* Built in support for GitLab CI deployments
* Built in support for shared configuration file templates
* Sets sensible configuration defaults using `.env` files for production and development environments
* Enable to seed/migrate database and storage folder

### Container
* Runs ocb in docker container 
* Automatic install/update/seed the project during startup of the container
* Makes sure that everything inside of OctoberCMS is in a correct state, and if not it reports you back
* Enable/Disable XDebug with a single variable
* Can be easily shared in your team
* Uses php-fpm


## Versions
| OCB Version |  Supported Octobercms version | php Version | Webserver | Module   | Depending on |
| ----------- | ----------------------------- | ----------- | --------- | -------- | ------------ |
| 1.x         | 1.0, 1.1                      | 7.4         | Apache    | mod_php  | [aspendigital/docker-octobercms](https://github.com/aspendigital/docker-octobercms) |
| 2.x-8.0     | 2.0                           | 8.0         | Nginx     | php-fpm  |  [dwchiang/nginx-php-fpm](https://github.com/dwchiang/nginx-php-fpm/tree/master/alpine3.12) |


## Docker image

An official Docker image that bundles `ocb`, `composer` and `Envoy` is available on [hub.docker.com](https://hub.docker.com/r/foersterwerbung/ocb) as `foersterwerbung/ocb`.


## Usage

### Quickstart

#### 1. Create a `docker-compose.yaml` file in your project root:
```
version: '3'
services:
  web:
    image: foersterwerbung/ocb:latest
    ports:
      - 80:80
    environment:
      - OCB_INSTALL=true
    depends_on:
      - mysql
    volumes:
      - "./:/var/www/html"

  mysql:
    image: mysql:5.7
    ports:
      - 3306:3306
    environment:
      - MYSQL_ROOT_PASSWORD=octobercms
      - MYSQL_DATABASE=octobercms
```
#### 2. Start the web container.

In your project directory you'll find an `october.yaml` file. Edit its contents.

Copy your license key under `october.licenseKey` and  add a theme name.

Start the `web` container again. Check the output of the container to see whats happening.

If `OCB_INSTALL=true` is set, it will download a fresh copy of October Cms.
It will also install the defined plugins and themes that you have 
configured in your `october.yaml`.

After the init phase, a nginx server runs in the background with php-fpm.

## Migration
### OCB 1 -> 2
When you want to move from ocb 1.x to 2, add 
```yaml
october:
    licenseKey: KEY
```
to your october.yaml.
After that, upgrade your `docker-compose.yaml` ocb version to version `2.x` or recreate the `:latest` ocb container.

### Octobercms 1 -> 2
Before you start, backup your whole project, including your database!

If you want to migrate to octobercms v2, you have to upgrade to OCB 2 (see above).
Fill out the license key and delete the vendor, plugin, config, modules directory.
Trash your database.
After that, simply start the container and copy back your plugins. 
Restart the container, and you should be ready to go.

### From oc-bootstrap
1. Add the example `docker-compose.yaml` file in Quickstart 1. 
1. Make sure that you have `OCB_INSTALL=true` in your docker web container environment set   
1. Add the october licenseKey in your october.yaml file (see Migration OCB 1 -> 2)
1. Start the container.

### From aspendigital/docker-octobercms
1. Change your `docker-compose.yaml` file like in the Quickstart 1.
2. You have cron enabled, use `OCB_CRON=true` instead.
3. Use `OCB_INSTALL=false` if you don't want octobercms automatically to be installed.
4. Start the container, you should find an `october.yaml` file in your working directory.
5. If you `OCB_INSTALL=false`, continue; Otherwise you should configure the plugin section, theme name and octobercms license key.
6. Cut all `DB_*` from the web environment and paste it to the `october.yaml` file. 
6. Restart the container.

## Configuration

### Theme and Plugin syntax

`ocb` enables you to install plugins and themes from your own git repo. Simply
append your repo's address in `()` to tell `ocb` to check it out for you.
If no repo is defined the plugins are loaded from the October Marketplace.


#### Examples

```yaml
# Install a plugin from the official October Marketplace
- Offline.Mall 

# Install a plugin from a git repository. The plugin will be cloned
# into your local repository and become part of it. You can change the
# plugin and modify it to your needs. It won't be checked out again (no updates).
- Offline.Mall (https://github.com/Offline-GmbH/oc-mall-plugin.git)

# The ^ marks this plugin as updateable. It will be removed and checked out again
# during each call to `october install`. Local changes will be overwritten.
# This plugin will stay up to date with the changes of your original plugin repo.
- ^Offline.Mall (https://github.com/Offline-GmbH/oc-mall-plugin.git)

# Install a specific branch of a plugin. Keep it up-to-date.
- ^Offline.Mall (https://github.com/Offline-GmbH/oc-mall-plugin.git#develop)
```

### Project seeding

You can seed a project with 
```
ocb seed
```
You have to set a database or a storage folder that should be applied.
```
seed:
    database: dev/migrations
    stroage: dev/storage
```
It executes all `.sql` files in the database folder and copies the storage folder into the current project root

You can set 
```
    environment:
      - OCB_SEED=true
```
to apply the `odb seed` command once during the init phase.
 
 
### Scheduler and Queues
 
 To enable a scheduler for Ocbtober CMS just add `OCB_CRON=true` to your `docker-composer.yaml` file.
 
 ```
     environment:
       - OCB_CRON=true
 ```
 
 Make sure that your commands configured in the `Plugin.php`
 
 ```
 class Plugin extends PluginBase {

    public function registerSchedule($schedule)
    {
        $schedule->command('queue:work --tries=25 --once')->everyMinute();
    }
}
 ```
 
 
### Variables for the container
#### OCB Variables
| Variable | Default | Action |
| -------- | ------- | ------ |
| OCB_INSTALL | false | `true` installs octobercms and dependencies during the first startup. |
| OCB_SEED | false | `true` executes `ocb seed` during the startup once. |
| OCB_CRON | false | `true` starts a cron process within the container which enables the scheduler for October CMS |


#### Docker October CMS Variables
| Variable | Default | Action |
| -------- | ------- | ------ |
| FWD_REMOTE_IP | false | `true` enables remote IP forwarding from proxy (NGINX) |
| PHP_DISPLAY_ERRORS | off | Override value for `display_errors` in docker-oc-php.ini |
| PHP_MEMORY_LIMIT | 128M | Override value for `memory_limit` in docker-oc-php.ini |
| PHP_POST_MAX_SIZE | 32M | Override value for `post_max_size` in docker-oc-php.ini |
| PHP_UPLOAD_MAX_FILESIZE | 32M | Override value for `upload_max_filesize` in docker-oc-php.ini |
| UNIT_TEST |  | `true` runs all October CMS unit tests. Pass test filename to run a specific test. |
| VERSION_INFO | false | `true` outputs container current commit, php version, and dependency info on start |
| XDEBUG_ENABLE | false | `true` enables the Xdebug PHP extension |
| XDEBUG_REMOTE_HOST | host.docker.internal | Override value for `xdebug.remote_host` in docker-xdebug-php.ini |



#### Install additional plugins

If at any point in time you need to install additional plugins, simply add them to your `october.yaml` and re-run 
`ocb install`. Missing plugins will be installed.

You can also restart the container.
  
### Update October CMS

If you want to update the installation you can run

```
ocb update
```

### Push changes to remote git repo

To push local changes to the current git remote run 

```
ocb push
```

## Files that should be tracked with git
This is the minimal set that you need for your team:
```
.gitignore
docker-compose.yaml
october.yaml
```

If you have custom plugins or themes with migrations and storage folder:
```
dev
    migrations
    storage
plugins
    custom-vendor  
        custom-plugin
storage
    app
    cms
    framework
    logs
    .gitignore   
themes
    my-theme
.gitignore
docker-compose.yaml
october.yaml
```
with this `.gitignore`:
```
*
!.gitignore
!composer.*
composer.phar
!.env.*
!october.yaml
!Envoy.blade.php
!.gitlab-ci.yml
!themes
!themes/**/*
!plugins
!plugins/custom-vendor
!plugins/custom-vendor/custom-plugin
!plugins/custom-vendor/custom-plugin/*
!plugins/custom-vendor/custom-plugin/**/*
!dev
!dev/**/*
!docker-compose.yaml
!storage
!storage/**/*
```



## Credits
`ocb` is a fork of [OFFLINE-GmbH/oc-bootstrapper](https://github.com/OFFLINE-GmbH/oc-bootstrapper).

`oc-bootstrap`'s `october.yaml` is compatible with `ocb`. No changes required.

The v1 docker container is build on top of [aspendigital/docker-octobercms](https://github.com/aspendigital/docker-octobercms). The PHP options are still the same for version 2.

The v2 docker container is build on top of [dwchiang/nginx-php-fpm](https://github.com/dwchiang/nginx-php-fpm/tree/master/alpine3.12) with alpline.

## TODO

* Deployments 
* `ocb` as composer binary that can be used outside of this docker container..
