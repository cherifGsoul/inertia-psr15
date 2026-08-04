# Sirix inertia-psr15

This is a maintained fork of https://github.com/cherifGsoul/inertia-psr15. It preserves the original goal while updating PHP versions, tooling, and namespace/package details for this fork. See “Fork notes” below for differences.

Before using this library, it’s important to know [what is Inertia.js](https://inertiajs.com/#top), [what is it for](https://inertiajs.com/who-is-it-for) and [how it works](https://inertiajs.com/how-it-works), on the [official Inertia.js website](https://inertiajs.com/).

PHP PSR-15 [InertiaJS](https://inertiajs.com/) server-side adapter, it can be used with [Mezzio](https://mezzio.dev/), [Slim](https://www.slimframework.com/) or any framework that implements PSR-15 interfaces.

The adapter is a PSR-15 middleware to detect InertiaJS requests and prepare and send Response to be read and rendered 
by InertiaJS front-end components. After installation and configuration, usage can be as simple as: 

```php

// In some RequestHandlerInterface class

$inertia = $request->getAttribute(InertiaMiddleware::INERTIA_ATTRIBUTE);
return $inertia->render('MyFrontEndComponent', [
    'someProp' => 'someProp Prop Value',
    'ohterProp' => 'ohterProp Prop Value'
]);
```



## Fork notes

- Package name: sirix/inertia-psr15
- PHP versions: ~8.2 to ~8.5 supported
- Namespace: Sirix\\InertiaPsr15\\
- Tooling added: PHP-CS-Fixer, PHPStan, Rector configs and GitHub Actions.
- This README has been adjusted to reflect the fork; code usage remains the same with updated namespaces.

## Usage:

A [small application](https://github.com/sirix777/mezzio-inertia-svelte-demo) was made to demonstrate how this adapter can be used in Mezzio application.

The adapter is designed to work with [Mezzio](https://mezzio.dev/) with little effort, in the following we assume that 
a Mezzio application was generated using [Mezzio Skeleton](https://github.com/mezzio/mezzio-skeleton) and Twig
was selected as the template engine:

### Installation:

1- Install the adapter:

```shell
composer require sirix/inertia-psr15
```

### Container requirements

The built-in factories resolve container services strictly through PSR-11. The following services must be registered with values implementing their declared interfaces: `ResponseFactoryInterface`, `StreamFactoryInterface`, `RootViewProviderInterface`, `TemplateRendererInterface`, and `InertiaFactoryInterface`.

The `config` service is optional. When present, it must be an array; `inertia_psr15.root_view` is an optional non-empty string and defaults to `app.html.twig`. Missing services, wrong service types, and invalid configuration fail with an exception that identifies the factory and required value.

2- Add the inertia middleware to the middlewares pipeline:

```php
<?php
//my-mezzio-app/config/pipeline.php

// ...
// - $app->pipe('/files', $filesMiddleware);
$app->pipe(\Sirix\InertiaPsr15\Middleware\InertiaMiddleware::class);

// Register the routing middleware in the middleware pipeline.
// This middleware registers the Mezzio\Router\RouteResult request attribute.
$app->pipe(RouteMiddleware::class);

// ...
```

3- Please refer to the [Inertia v3 client-side setup](https://inertiajs.com/docs/v3/installation/client-side-setup) to install a client-side adapter.

4- Using Vite is recommended to build the front-end application, however, to render the built JS/CSS
assets in a Twig template, the following extension can be used:

```shell
composer require sirix/twig-vite-extension
```

>> a factory might be needed to configure the Vite extension 

5- Configure the template to use Vite extension and the Inertia Twig extension shipped with the adapter
by updating `config/autoload/template.global.php` and `vite.global.php` like the following:

```php
<?php

// template.global.php

declare(strict_types=1);

use Sirix\InertiaPsr15\Twig\InertiaExtension;
use Sirix\TwigViteExtension\Twig\ViteExtension;

return [
    'templates' => [
        'paths' => [
            'error'    => [dirname(__DIR__, 2) . '/templates/error'],
            '__main__' => [dirname(__DIR__, 2) . '/templates'],
        ],
    ],
    'twig'      => [
        'extensions' => [
            ViteExtension::class,
            InertiaExtension::class,
        ],
    ],
];
```

```php
<?php

// vite.global.php

declare(strict_types=1);

return [
    'vite' => [
        'options' => [
            'is_dev_mode'      => true,
            'vite_build_dir'   => 'public/build',
            'vite_public_base' => 'build',
            'dev_server'       => 'http://localhost:5173',
        ],
    ],
];
```

6- The adapter needs just one backend template to render the application, and by default it will look for 
`templates/app.html.twig` if a default template is not configured, the app template can be like the following:

The template name can be changed through application configuration:

```php
return [
    'inertia_psr15' => [
        'root_view' => 'inertia/app.html.twig',
    ],
];
```

```html
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{% block title %}Mezzio with Inertia.js{% endblock %}</title>
    {{ vite_entry_link_tags('resources/js/app.ts') }}
</head>
<body class="font-sans antialiased">
{% block body %}
{{ inertia(page) }}
{% endblock %}

{{ vite_entry_script_tags('resources/js/app.ts') }}
</body>
</html>
```
>> The template uses Vite extension (vite_entry_link_tags) to render the assets and Inertia extension 
> `inertia(page)` to mount the front-end application.

## Inertia v3

Version 2.x targets Inertia v3 clients. The Twig extension renders the initial page object in the v3 `<script data-page="app" type="application/json">` format and provides a separate `<div id="app"></div>` mount point.

For a complete 1.x to 2.x migration guide, see [docs/inertia-v3-migration.md](docs/inertia-v3-migration.md).

Use `Inertia::optional()` for props that should only be included when explicitly requested in a partial reload:

```php
use Sirix\InertiaPsr15\Service\Inertia;

$inertia->render('Users/Index', [
    'users' => Inertia::optional(fn () => $users),
]);
```

`Inertia::lazy()` and `LazyProp` were removed in 2.x.


After successful configuration, the adapter can be used to render the front-end component instead of the HTML templates:
```php

declare(strict_types=1);

namespace App\Handler;

use Sirix\InertiaPsr15\Middleware\InertiaMiddleware;
use Sirix\InertiaPsr15\Service\InertiaInterface;
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

# Copyright
Original author: Mohammed Cherif BOUCHELAGHEM (2021)
Fork maintainer: Sirix (2025)

License: BSD-3-Clause (see LICENSE)
