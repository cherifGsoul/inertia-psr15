# inertia-psr15

Before using this library, is important to know [what is Inertia.js](https://inertiajs.com/#top), [what is it for](https://inertiajs.com/who-is-it-for) and [how it works](https://inertiajs.com/how-it-works), in the [official Inertia.js website](https://inertiajs.com/)

PHP PSR-15 [InertiaJS](https://inertiajs.com/) server-side adapter, it can be used with [Mezzio](https://mezzio.dev/), [Slim](https://www.slimframework.com/) or any framework that implements PSR-15 interfaces.

The adapter is a PSR-15 middleware to detect InertiaJS requests and prepare and send Response to be read and rendered 
by InertiaJS front-end components, the usage after installation and configuration can be easy as: 

```php

// In some RequestHandlerInterface class

$inertia = $request->getAttribute(InertiaMiddleware::INERTIA_ATTRIBUTE);
return $inertia->render('MyFrontEndComponent', [
    'someProp' => 'someProp Prop Value',
    'ohterProp' => 'ohterProp Prop Value'
]);
```

# Copyright
Mohammed Cherif BOUCHELAGHEM 2021



## Usage:

A [small application](https://github.com/cherifGsoul/mezzio-inertia-demo) was made to demonstrate how this adapter can be used in Mezzio application.

The adapter is designed to work with [Mezzio](https://mezzio.dev/) with little effort, in the following we assume that 
a Mezzio application was generated using [Mezzio Skeleton](https://github.com/mezzio/mezzio-skeleton) and Twig
was selected as the template engine:

### Installation:

1- Install the adapter:

```shell
composer require cherif/inertia-psr15
```

2- Add the inertia middleware to the middlewares pipeline:

```php
<?php
//my-mezzio-app/config/pipeline.php

// ...
// - $app->pipe('/files', $filesMiddleware);
$app->pipe(\Cherif\InertiaPsr15\Middleware\InertiaMiddleware::class);

// Register the routing middleware in the middleware pipeline.
// This middleware registers the Mezzio\Router\RouteResult request attribute.
$app->pipe(RouteMiddleware::class);

// ...
```

3- Please refer to [InertiaJS](https://inertiajs.com/client-side-setup) to install a client-side adapter.

4- Using Webpack is recommended in order to build the front-end application, however, to render the built JS/CSS
assets in a Twig template the following extension can be used:

```shell
composer require fullpipe/twig-webpack-extension
```

>> a factory might be needed to configure the Webpack extension 

5- Configure the templte to use Webpack extension and the Inertia Twig extension shipped with the adapter
by apdating `config/autoload/template.global.php` and `webpack.global.php` like the following:

```php
<?php

// template.global.php

declare(strict_types=1);

use Cherif\InertiaPsr15\Twig\InertiaExtension;
use Fullpipe\TwigWebpackExtension\WebpackExtension;

return [
    'templates' => [
        'paths' => [
            'error' => [dirname(__DIR__, 2) . '/templates/error'],
            '__main__' => [dirname(__DIR__, 2) . '/templates']
        ]
    ],
    'twig' => [
        'extensions' => [
            WebpackExtension::class,
            InertiaExtension::class
        ]
    ]
];
```

```php
<?php

// webpack.global.php

declare(strict_types=1);

return [
    'webpack' => [
        'manifest_file' => dirname(__DIR__, 2) . '/public/build/manifest.json',
        'public_dir' => dirname(__DIR__, 2) . '/build',
    ]
];
```

6- The adapter needs just one backend template to render the application and by default it will look for 
`templates/app.html.twig` if a default template is not configured, the app template can be like the following:

```html
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        {% webpack_entry_css 'build/app' %}
    </head>
    <body>
        {{ inertia(page) }}
        {% webpack_entry_js 'build/runtime' %}
        {% webpack_entry_js 'build/app' defer %}
    </body>
</html>
```
>> The template uses Webpack extension (webpack_entry_css, webpack_entry_js) to render the assets and Inertia extension 
> `inertia(page)` to mount the front-end application.


After successful configuration the adapter can be used to render the front-end component instead of the HTML templates:
```php

declare(strict_types=1);

namespace App\Handler;

use Cherif\InertiaPsr15\Middleware\InertiaMiddleware;
use Cherif\InertiaPsr15\Service\InertiaInterface;
use Mezzio\LaminasView\LaminasViewRenderer;
use Mezzio\Plates\PlatesRenderer;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class HomePageHandler implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        /** @var InertiaInterface $inertia */
        $inertia = $request->getAttribute(InertiaMiddleware::INERTIA_ATTRIBUTE);
        return $inertia->render('Home', [
            'greeting' => 'Hello Inertia PSR-15'
        ]);
    }
}

```
