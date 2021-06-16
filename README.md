# API Gateway Drupal connector
This package gives you the connection with a Drupal CMS. The package can be used on it's own, or in combination with the stadspas-api-gateway package (^v3.0.0)

## Installation
There are a few steps to go through to be able to use everything this package has to offer.

* Add the following line (including all the locales you require to your `config/app.php` file:

```
'supported_locales' => ['en'],
``` 

* Add the following to your projects composer file

```
"repositories": { 
   "api-gateway-drupal": { 
      "type": "vcs", 
      "url": "git@github.com:IN10/api-gateway-drupal" 
    } 
}

"extra": {
    "enable-patching": true,
    "patches": {
        "": ""
    }
}
```

* Require the package in your (clean) Laravel project

`composer require in10/api-gateway-drupal`

* Publish the config files

`php artisan vendor:publish --tag="ApiGatewayDrupal"`

* Add the following line to the `$middlewareGroups api` section in the `Kernel.php` :

`\IN10\ApiGatewayDrupal\Middleware\Localization::class`

NOTE: any project specific requirements will have to be implemented in the project api layer 
