<?php

declare(strict_types=1);

// ENVIRONMENT is intentionally NOT defined here so PHPStan treats it as
// runtime-unknown — otherwise expressions like `ENVIRONMENT === 'production'`
// resolve to `'testing' === 'production'` and trigger identical.alwaysFalse
// on legitimate environment-gated branches. The matching `Constant ENVIRONMENT
// not found` warning is suppressed in `phpstan.neon`. Mirrors the admin convention.

foreach ([
    'APPPATH'    => __DIR__ . '/app/',
    'ROOTPATH'   => __DIR__ . '/',
    'WRITEPATH'  => __DIR__ . '/writable/',
    'FCPATH'     => __DIR__ . '/public/',
    'SYSTEMPATH' => __DIR__ . '/vendor/codeigniter4/framework/system/',
] as $constant => $value) {
    if (! defined($constant)) {
        define($constant, $value);
    }
}

if (! function_exists('env')) {
    function env(string $key, mixed $default = null): mixed
    {
        return $default;
    }
}

if (! function_exists('service')) {
    function service(string $name, mixed ...$params): mixed
    {
        return null;
    }
}

if (! function_exists('config')) {
    function config(?string $name = null): mixed
    {
        return null;
    }
}

if (! function_exists('lang')) {
    function lang(string $line, array $args = [], ?string $locale = null): string
    {
        return $line;
    }
}

if (! function_exists('redirect')) {
    function redirect(?string $route = null): mixed
    {
        return null;
    }
}

if (! function_exists('base_url')) {
    function base_url(string $uri = '', ?string $protocol = null): string
    {
        return $uri;
    }
}

if (! function_exists('site_url')) {
    function site_url(string $uri = '', ?string $protocol = null): string
    {
        return $uri;
    }
}

if (! function_exists('route_to')) {
    function route_to(string $routeName, mixed ...$params): string
    {
        return '';
    }
}

if (! function_exists('session')) {
    function session(?string $val = null): mixed
    {
        return null;
    }
}

if (! function_exists('esc')) {
    function esc(mixed $data, string $context = 'html', ?string $encoding = null): mixed
    {
        return $data;
    }
}

if (! function_exists('is_cli')) {
    function is_cli(): bool
    {
        return false;
    }
}

if (! function_exists('log_message')) {
    function log_message(string $level, string $message, array $context = []): void
    {
    }
}

if (! function_exists('helper')) {
    function helper(mixed $filenames = []): void
    {
    }
}

if (! function_exists('view')) {
    function view(string $name, array $data = [], array $options = []): string
    {
        return $name;
    }
}

if (! function_exists('cache')) {
    function cache(?string $key = null): mixed
    {
        return null;
    }
}

if (! function_exists('csrf_field')) {
    function csrf_field(?string $id = null): string
    {
        return '';
    }
}

if (! function_exists('csrf_token')) {
    function csrf_token(): string
    {
        return '';
    }
}

if (! function_exists('url_is')) {
    function url_is(string $path): bool
    {
        return false;
    }
}
