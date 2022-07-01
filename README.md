# Hati - A Speedy PHP Library
                          .'``'.__
                         /      \ `'"-,
        .-''''--...__..-/ .     |      \
      .'               ; :'     '.  a   |
     /                 | :.       \     =\
    ;                   \':.      /  ,-.__;.-;`

[![PHP Version Require](https://img.shields.io/badge/php-%3E%3D8.0-brightgreen?style=flat-square)](https://packagist.org/packages/rootdata21/hati)
[![Latest Stable Version](https://img.shields.io/packagist/v/rootdata21/hati.svg?style=flat-square)](https://packagist.org/packages/rootdata21/hati) 
[![Total Downloads](https://img.shields.io/packagist/dt/rootdata21/hati.svg?style=flat-square&color=blueviolet)](https://packagist.org/packages/rootdata21/hati) 
[![License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square&color=important)](https://packagist.org/packages/rootdata21/hati) 


Welcome to the Hati wiki! Hati is a simply powerful PHP library written in **PHP 8** which has various library functions and classes to make API or application development in PHP effortless. This library has great support for crafting:-
* JSON output for APIs
* Templete engine
* Email functions
* Easy file uploading
* Form data processing
* Server request validation
* Single database quries
* Time functions
* Number calculations etc.

Hati can be configured to use composer autoload. Basically Hati uses apache's dot htaccess file to include the Hati loader which seemlessly works with composer loader to resolve the loader dependencies. Many aspect of Hati can be configuared by using the configuration file called **HatiConfig**. Library functions, class names are inspired by Bengali language. Many common words from Bengali language such **Kuli**, **Biscut**, **Shomoy** are found within this library.

# Install
Install the latest version using **composer require rootdata21/hati**

Or add to your composer.json file as a requirement:

```json
{
    "require": {
        "rootdata21/hati": "~2.0.0"
    }
}
```

# Setup
1. Hati can only be used in PHP 8 or above. In order to setup the Hati, the htaccess file provided with the library needs to be configured first. Along with other htaccess commands, the Hati Loader command should point to absoluate path of the **Hati** file. 
2. The second configuration is that the root directory of project needs to be set in HatiConfig.

**As long as the htaccess can point to the path of the Hati correctly, the hati library folder can be placed anywhere within the project. The .htaccess file must be on the project root directory.**

# Demo
Below we have an API which uses Fluent class to perform database query where sql is prepared and binded behind the scene and the result is fetched using datum method. With traditional approach such task would take up to 10 lines. Hati really shrinks down the line of code you have to write over and over again. Finally, it return the output as JSON to requester.

```php
<?php

/*
* A simple API developed using Hati library. This demonstrates
* how Hati simplifies various tasks with powerful functions.
*/

// imports
use hati\Fluent;
use hati\Response;

// search the name by id from the database table using PDO extension
$id = 5;
Fluent::exePrepared("SELECT name FROM user WHERE user.id = ?", [$id]);

// get the name value from the select statement with simple datum method
$name = Fluent::datum('name');

// craft and reply JSON output
$response = new Response();
$response -> add('name', $name);
$response -> reply(status: Response::SUCCESS);
```
# Output

```json
{
    "name": "Abdul Ahad",
    "response": {
        "status": 1,
        "level": 1,
        "msg": ""
    }
}
```

# Donation/Support
If you find this project helpful then why don't you buy the developer a cup of tea? Any amount of donation is appreciated. Please follow the link to donate by using PayPal.
[PayPal Payment](https://paypal.me/rootdata21?country.x=GB&locale.x=en_GB)

# License

This project is maintained under [MIT license](https://en.wikipedia.org/wiki/MIT_License).
