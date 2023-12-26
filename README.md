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


Welcome to the Hati wiki! Hati is a powerful PHP library written in **PHP 8** which has various library functions and classes to make API or application development in PHP effortless. This library has great support for crafting:-
* APIs development support
* Form data & request validations
* Basic Template engine
* Email functions [using PHPMailer]
* Easy file uploading
* Multiple database operations
* Simplistic Date-Timing functions
* Number crunching utility functions etc.
* Zip files creation

Hati utilizes composer autoload. You can use apache's dot htaccess file to prepend the Hati init file which will require 'vendor/autoload.php' behind the scene to resolve the loader dependencies. Many aspect of Hati can be configured by using the configuration files found on project root **hati/hati.json** & **hati/db.json**. 

Hati comes with a few tools which are found on the project root's **hati/tool** folder. These tools are used to help working with multiple database, API documentation etc.

Library functions, class names are inspired by Bengali language. Many common words from Bengali language such **Kuli**, **Biscut**, **Shomoy** are found within this library.

# Install
Install the latest version using:
```shell
composer require rootdata21/hati
```

Or add to your composer.json file as a requirement:

```json
{
    "require": {
        "rootdata21/hati": "~5.0.0"
    }
}
```

# Setup
1. Hati can only be used in PHP 8 or above. In order to set up the Hati, you can use the htaccess file provided with the library to prepend the "hati/init.php" file or manually require it using require function.
2. Adjust the "hati/hati.json" file to configure the environment. 

# Demo
Below an API is written using Fluent class to perform database query where sql is prepared and behind the scene and the result is fetched using datum method. With traditional approach such task would take up to 10 lines. Hati really shrinks down the line of code you have to write over and over again. Finally, it return the output as JSON to requester.

```php
<?php

/*
 * Register the API in the "api/hati_api_registry.php" file using
 * HatiAPIHandler::register method.
 */
\hati\api\HatiAPIHandler::register([
	'method' => 'GET',
	'path' => 'welcome/get',
	'handler' => 'v1/ExRate.php',
	'description' => 'A GET API'
]);

// Use a specific database connection
Fluent::use(DBPro::exampleDb); 

// search the name by id from the database table using PDO extension
$id = 5;
Fluent::exePrepared("SELECT name FROM user WHERE user.id = ?", [$id]);

// get the name value from the select statement with simple datum method
$name = Fluent::datum('name');

// craft and reply JSON output
$response = new Response();
$response -> add('name', $name);
$response -> reply('Operation has been done', header: [
    'X-EXAMPLE-HEADER: SOMETHING',
    'Content-Type: application/json'
]);
```
# Output

```json
{
    "name": "Abdul Ahad",
    "response": {
        "status": 1,
        "msg": "Operation has been done"
    }
}
```

# Donation/Support
If you find this project helpful then why don't you buy the developer a cup of tea? Any amount of donation is appreciated. Please follow the link to donate by using PayPal.
[PayPal Payment](https://paypal.me/rootdata21?country.x=GB&locale.x=en_GB)

# License

This project is maintained under [MIT license](https://en.wikipedia.org/wiki/MIT_License).
