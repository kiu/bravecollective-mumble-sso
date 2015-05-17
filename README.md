Brave Collective Mumble SSO
===================
[![License](http://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](https://tldrlegal.com/license/mit-license)


What is this?
-------------
This package offers an easy to use solution managing access to a mumble server using the EVE Online Single-Sign-On mechanism.

Screenshots: demo.png

Features?
-------------

* Foolproof enduser experience
* User authentication through EVE SSO
* Attach groups to users (DB config, no UI)
* Open access or limited to configurable corporations and alliances (DB config, no UI)
* Banning based on characters, corporations and alliances (DB config, no UI)

Requirements
------------------

* PHP 5.x (webserver and cli)
* MySQL
* Python 2.6.5
* Mumble Server (murmur)

Installation
------------------

#### MySQL
* Create the schema and tables by importing `create.sql`

#### CCP
* Setup an EVE SSO application on https://developers.eveonline.com/applications

#### Webserver
* Edit `webroot/config.php`
* Point your webserver towards the webroot directory or create a symlink

#### Mumble Server
* `apt-get install mumble-server`
* Enable the Ice interface in `/etc/mumble-server.ini`
```
ice="tcp -h 127.0.0.1 -p 6502"
```

#### Mumble Authenticator
* `apt-get install python-mysqldb python-zeroc-ice`
* Edit `authenticator/mumble-sso-auth.ini`
* `cd authenticator && ./run.sh`
* It is recommended to run this script in a screen session: `screen -S mumble-sso-authenticator bash -c 'cd authenticator && ./run.sh'`

#### Character Refresher
* `cd refresher && ./run.sh`
* It is recommended to run this script in a screen session: `screen -S mumble-sso-refresher bash -c 'cd refresher && ./run.sh'`

License
------------------

Contents are MIT Licensed, see the LICENSE file for more info.
