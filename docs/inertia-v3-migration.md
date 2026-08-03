# Migrating to Inertia v3 and `sirix/inertia-psr15` 2.x

This guide covers migrating an application built with Mezzio, Slim, or another PSR-15 framework from `sirix/inertia-psr15` 1.x to the upcoming 2.x release. The `2.x` branch adapts the package to the core Inertia v3 protocol.

This package is a server-side adapter. Updating the Inertia JavaScript client, Vite, and UI components must be done in the consuming application, not in this repository.

## What changes

Version 2.x contains three breaking changes:

1. Initial page data is passed through a JSON `<script>` element instead of the root `<div>` element's `data-page` attribute.
2. `Inertia::lazy()` and the `LazyProp` class have been removed. Use `Inertia::optional()` instead.
3. Partial reloads support `X-Inertia-Partial-Except` and dot-notation paths such as `auth.notifications`.

This release does not implement the complete set of new Inertia v3 server-side capabilities. In particular, the package does not yet provide APIs for `defer`, merge/scroll/once props, or history metadata. Standard page loads, navigation, asset versioning, redirects, and partial reloads remain supported.

## Requirements

- PHP 8.2 or later.
- An Inertia v3 client-side adapter:

  ```sh
  npm install @inertiajs/vue3@^3
  # or @inertiajs/react@^3 / @inertiajs/svelte@^3
  ```

- React applications require React 19+, and Svelte applications require Svelte 5+. These requirements are defined by Inertia v3 itself.

Review your frontend setup against the official [upgrade guide](https://inertiajs.com/docs/v3/getting-started/upgrade-guide) and [client-side setup](https://inertiajs.com/docs/v3/installation/client-side-setup).

## Migration steps

### 1. Upgrade the server package

Once 2.x is released, update the version constraint in your application:

```sh
composer require sirix/inertia-psr15:^2.0
```

If your application installs the package from a VCS repository, switch to the 2.x branch or tag according to your established installation method.

### 2. Replace `lazy()` with `optional()`

Before 2.x, optional props were created like this:

```php
use Sirix\InertiaPsr15\Service\Inertia;

return $inertia->render('Users/Index', [
    'users' => Inertia::lazy(fn () => $users),
]);
```

In 2.x, use:

```php
use Sirix\InertiaPsr15\Service\Inertia;

return $inertia->render('Users/Index', [
    'users' => Inertia::optional(fn () => $users),
]);
```

`optional()` does not include its value in a standard response. The value is evaluated only during a partial reload that explicitly requests the corresponding prop through `only`.

If your application imports or type-hints `Sirix\InertiaPsr15\Model\LazyProp` directly, replace it with `Sirix\InertiaPsr15\Model\OptionalProp`. In most cases, no direct model import is needed: call `Inertia::optional()` instead.

### 3. Check the root template

If the template uses the built-in Twig function, the invocation does not change:

```twig
{{ inertia(page) }}
```

In 2.x, it generates v3-compatible HTML automatically:

```html
<script data-page="app" type="application/json">{...}</script>
<div id="app"></div>
```

If you implemented the root template manually, replace the legacy markup:

```html
<div id="app" data-page="{...}"></div>
```

with a `<script type="application/json">` element and a separate `<div id="app"></div>`. Do not insert JSON into HTML manually without appropriate escaping; using the package's Twig function is preferred.

### 4. Check partial reloads

Existing top-level reloads continue to work:

```js
router.reload({ only: ['users'] })
```

Version 2.x also supports nested paths. A server-side handler may return structured props:

```php
return $inertia->render('Dashboard', [
    'auth' => fn () => [
        'user' => $user,
        'notifications' => Inertia::optional(fn () => $notifications),
    ],
]);
```

The client can request only the notifications:

```js
router.reload({ only: ['auth.notifications'] })
```

The response contains only the selected branch:

```json
{
  "props": {
    "auth": {
      "notifications": []
    }
  }
}
```

Excluding props is supported as well:

```js
router.reload({ except: ['auth.notifications'] })
```

If the client sends both `only` and `except`, `except` takes precedence, as specified by the Inertia protocol. Dot notation works for nested arrays at any depth, including containers returned by closures. When a literal prop key containing a dot exists at the current level, it takes precedence over interpreting that key as a nested path. Avoid such ambiguous names in new props.

### 5. Verify the application

After upgrading, verify at least the following:

1. The first URL load mounts the client application without an initial-page parsing error.
2. Navigation through `<Link>` or `router.visit()` receives JSON with `X-Inertia: true`.
3. `router.reload({ only: [...] })` returns only the selected props.
4. `router.reload({ except: [...] })` excludes the specified props.
5. An optional prop is absent from a standard response and only appears when explicitly requested through `only`.

For this package itself, run:

```sh
composer check
```

## Laravel guide steps that do not apply

This package does not use `inertiajs/inertia-laravel`, Blade, Artisan, or `config/inertia.php`. Therefore, the official guide's Laravel-specific steps for publishing the Inertia config, clearing Blade views, and setting Laravel middleware priority do not apply to PSR-15 applications.

Likewise, frontend event renames, the `router.cancel()` replacement, and React/Svelte requirements concern the JavaScript code of the consuming application. The server-side adapter does not call those APIs.

## Additional resources

- [Official Inertia v3 upgrade guide](https://inertiajs.com/docs/v3/getting-started/upgrade-guide)
- [Inertia v3 protocol](https://inertiajs.com/docs/v3/core-concepts/the-protocol)
- [Partial reloads](https://inertiajs.com/docs/v3/data-props/partial-reloads)
