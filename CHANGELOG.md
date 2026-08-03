# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.0.0] - Unreleased

### Added
- Inertia v3 partial reload support for `X-Inertia-Partial-Except` and nested prop paths.
- `Inertia::optional()` for props that are only included when explicitly requested.

### Changed
- Twig initial-page markup now uses the Inertia v3 JSON `<script data-page="app">` element and a separate application mount point.
- `InertiaMiddleware` is now stateless, preventing request state from being retained or shared by long-running and concurrent server workers.

### Removed
- `Inertia::lazy()` and `LazyProp`; use `Inertia::optional()` instead.

## [1.1.2] - 2026-05-11

### Added
- Tools: Integration of `bamarni/composer-bin-plugin` for better dependency management of development tools.
- PHP Support: Added support for PHP 8.5.

### Changed
- PHP Support: Minimum supported PHP version bumped to 8.2 (dropped 8.1).
- Tooling: Updated PHP-CS-Fixer and Rector configurations to target PHP 8.2.
- Refactoring: Internal code cleanup using PHP 8.2 features (first-class callables).
- Tests: Rename default testsuite to `unit` in PHPUnit configuration.

## [1.1.1] - 2025-09-03

### Changed
- README: Update demo link to sirix/mezzio-inertia-svelte-demo and recommend Vite instead of Webpack, including configuration examples using Sirix\TwigViteExtension. 

## [1.1.0] - 2025-08-31

### Added
- Twig: InertiaExtensionFactory to enable creating the InertiaExtension via a container factory.

### Changed
- ConfigProvider: Registers the Twig InertiaExtension and its factory in the container configuration for easier integration.

## [1.0.1] - 2025-08-30

### Changed
- Twig: InertiaExtension now uses htmlspecialchars with ENT_QUOTES | ENT_SUBSTITUTE and explicit UTF-8 when rendering the data-page attribute for safer output and correct encoding.

## [1.0.0] - 2025-08-30

### Added
- GitHub Actions workflow for CI (coding standards, static analysis, tests).
- Tooling configurations: PHP-CS-Fixer, PHPStan, Rector.
- Documentation updates in README to reflect the maintained fork, usage, and setup.

### Changed
- Refactored tests to improve structure, readability, and maintainability.
- Namespace updated to `Sirix\\InertiaPsr15\\`.
- Package name updated to `sirix/inertia-psr15`.
- Supported PHP versions updated to `~8.1` through `~8.4`.

### Notes
- Fork initialization for sirix/inertia-psr15
- Original project by Mohammed Cherif BOUCHELAGHEM (2021). 
- This repository is a maintained fork initiated and maintained by Sirix (2025).
