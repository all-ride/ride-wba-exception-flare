# Ride: Web Exception

This module listens to the exception event and sends the exception to [Flareapp.io](https://flareapp.io)
It also shows an error page when the user received an uncaught exception.

You can set a route with id ```exception.<locale>``` where locale is the locale code of a localized error page.

## Parameters
* __system.exception.path__: Path to the directory where the error reports will be written. (defaults to application/data/log/exception)
* __system.exception.flare.key__ : The Flare API key


## Related Modules 

- [ride/app](https://github.com/all-ride/ride-app)
- [ride/lib-common](https://github.com/all-ride/ride-lib-common)
- [ride/lib-event](https://github.com/all-ride/ride-lib-event)
- [ride/lib-http](https://github.com/all-ride/ride-lib-http)
- [ride/lib-log](https://github.com/all-ride/ride-lib-log)
- [ride/lib-security](https://github.com/all-ride/ride-lib-security)
- [ride/lib-system](https://github.com/all-ride/ride-lib-system)
- [ride/web](https://github.com/all-ride/ride-web)
- [ride/web-base](https://github.com/all-ride/ride-web-base)
- [flare-client-php](https://github.com/facade/flare-client-php)

## Installation

You can use [Composer](http://getcomposer.org) to install this application.

```
composer require ride/wba-exception-flare
```
__The flare exception will never trigger on LOCAL development__ unless you manually enable it.

for more info check the `config` folder. There is a `prod` and `stag` folder. Which means the `ExceptionApplicationListener` will only trigger on Staging and production environments.
This off course if your folder structure has the same setup. Change when needed.


### Flare
- Create an account on (https://flareapp.io/)[https://flareapp.io/]
- Add a new project to your account and copy the key to your project.

Add your key in your `parameters.json`: __system.exception.flare.key__: "api key"

