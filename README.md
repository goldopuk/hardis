### Introduction

Stayzen is a library that encapsulate the datababase, the business logic, the communication with others agents ofr Stayfilm.
It is means to be used, for example, by a php MVC framework to create a website or webservices on top of that lib.

### Requirements --

* php 5.3
* php extensions : curl
* composer

### External dependencies

* phpcassa

### Installation

Create the ENV file to select the correct environment

    echo "dev" > ENV

Create the log folder and set up the permission

    mkdir log
    chmod 777 log

Install composer

* Windows - install composer - go to composer website, download composer.exe and run it
* Linux - go to webiste and follw instruction
    curl -sS https://getcomposer.org/installer | php

Install dependencies

    php composer install (linux)
    composer install (windows)

Install git hooks (from staycool)
	rm .git/hooks*
	cp <staycoolpath>/scripts/hooks/* .git/hooks


### Application Configuration

The environment name defined in ENV file must have its node defined in the following configuration files:
*  config/config.php

Just copy one configuration node and rename the key to be the same as ENV file content.

### Test
There are many phpunit tests.
To run them, just run:

    ./runTest

### More information
Ask Julien !

### API Documentation
The code is documented with phpdoc

### How to use it

Include the file bootstrap.php into your framework
require_once('/path/to/stayzen/bootstrap.php');

To use a service, import the namespace and ask an instance of the service

    :::php
    use \Stayfilm\stayzen\services\UserService;
    $userServ = UserService::getInstance();
    $user = $userServ->getUserByUsername('toto@gmail.com');

