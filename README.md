# Hati - A Speedy PHP Library
                          .'``'.__
                         /      \ `'"-,
        .-''''--...__..-/ .     |      \
      .'               ; :'     '.  a   |
     /                 | :.       \     =\
    ;                   \':.      /  ,-.__;.-;`

Welcome to the Hati wiki! Hati is a simply powerful PHP library written in **PHP 8** which has various library functions and classes to make API or application development in PHP effortless. This library has great support for crafting:-
* JSON output for APIs
* easy file uploading
* form data processing
* server request validation
* database quries
* time functions
* number calculations etc.

Hati heavily relies on apache's dot htaccess file to resolve its loader dependencies and has a very strict namespace convention for the client code. Many aspect of Hati can be configuared by using the configuration file called **HatiConfig**. Library functions, class names are inspired by Bengali language. Many common words from Bengali language such **Kuli**, **Biscut**, **Shomoy** are found within this library.

# Setup
1. Hati can only be used in PHP 8 or above. In order to setup the Hati, the htaccess file provided with the library needs to be configured first. Along with other htaccess commands, the Hati Loader command should point to absoluate path of the **Hati** file. 
2. The second configuration is that the root directory of project needs to be set in HatiConfig.

**As long as the htaccess can point to the path of the Hati correctly, the hati library folder can be placed anywhere within the project.**

For having a clear understanding of the directry structure, let's have a look at the following diagram.

![hati typcial project structure](https://user-images.githubusercontent.com/19422792/160303321-fb0fcd1d-59bf-474f-b909-589bf110b9eb.png)

Here, the hati folder can be placed anywhere within project directory, but it must be pointed correctly in the .htaccess file. The .htaccess file must be put inside the project root directory as shown in the figure above.

# Demo
Below we have an API which uses Fluent class to perform database query where sql is prepared and binded behind the scene and the result is fetched using datum method. With traditional approach such task would take up to 10 lines. Hati really shrinks down the line of code you have to write over and over again. Finally, it return the output as JSON to requester.

```
<?php

/*
*  A simple API developed using Hati library. This demonstrates
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
