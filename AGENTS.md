# Kailyn Framework — AI Agent Guide

## Project Overview

Kailyn is a full-stack PHP 8.3+ framework with a custom template engine, Livewire-style reactive components, fluent query builder, Active Record ORM, schema builder, multi-driver cache, migration system, Symfony-based CLI (Tulpar), session/auth with CSRF, middleware pipeline, and MongoDB support.

**Version:** `0.2.0` — Semantic Versioning (MAJOR.MINOR.PATCH)
**Version file:** `VERSION` (root)
**Config:** `config/app.php` → `version` key
**Helper:** `version()` → returns current framework version

---

## Directory Structure

```
kailyn/
├── app/                          # Application code (userland)
│   ├── Components/               # #[Reactive] PHP components
│   ├── Console/                  # User CLI kernel (empty, for custom commands)
│   ├── Controllers/              # HTTP controllers
│   ├── Middleware/               # Auth, Guest, CSRF, Security, Throttle
│   ├── Models/                   # Active Record models (User, etc.)
│   └── Views/                    # Templates (auth/, components/, layouts/)
├── bin/tulpar                    # CLI entry point
├── bootstrap/app.php             # App bootstrapper (loads .env, boots app)
├── config/                       # app.php, cache.php, database.php
├── database/migrations/          # Migration files (YYYY_MM_DD_HHMMSS_name.php)
├── public/                       # Web root
│   ├── index.php                 # HTTP entry point
│   ├── router.php                # Dev server static router
│   └── js/kailyn.js              # Client-side reactive engine
├── routes/web.php                # Route definitions
├── src/Kailyn/                   # Framework source
│   ├── Cache/                    # CacheDriver, CacheManager, File/Redis/Database drivers
│   ├── Component/                # Component, ComponentManager, Attributes (Reactive/Computed/Action)
│   ├── Config/                   # Config loader, Environment (.env)
│   ├── Console/                  # Tulpar CLI (Kernel, Command, Signature, 9 built-in commands)
│   ├── Container/                # DI Container (bind/singleton/instance/make/call)
│   ├── Database/                 # Connection, QueryBuilder, Model, Schema, Migrator, Mongo*
│   ├── Foundation/               # Application (extends Container, boot/run)
│   ├── Http/                     # Kernel, Router, Request, Response, Middleware base
│   ├── Session/                  # SessionManager (PHP native sessions)
│   ├── Template/                 # Engine (render/layout/sections), Compiler (directives)
│   └── Validation/               # Validator (14 rules)
├── storage/                      # Compiled views, file cache
├── stubs/                        # Reserved for code generation stubs
├── VERSION                       # Current version: 0.2.0
└── AGENTS.md                     # This file
```

## Namespace Map (PSR-4)

| Namespace | Directory |
|-----------|-----------|
| `Kailyn\` | `src/Kailyn/` |
| `App\` | `app/` |

---

## Boot Sequence

### HTTP Request

```
public/index.php
  → Application::run()
    → boot()                          # Register singletons (Config, Router, Session, Engine, Cache, ComponentManager)
    → loadRoutes()                    # require routes/web.php
    → Http\Kernel::handle(Request)    # middleware pipeline + route dispatch
    → Response::send()
```

### CLI Command

```
bin/tulpar
  → bootstrap/app.php                 # Creates + boots Application
  → Console\Kernel::handle()          # Registers builtins + user commands, runs Symfony Console
```

### Singletons Registered in `boot()`

- `Config` — loads `config/*.php`
- `Router` — with internal `POST /_kailyn/update` route
- `SessionManager` — native PHP sessions
- `Engine` — template engine
- `ComponentManager` — reactive component lifecycle
- `CacheManager` — multi-store cache
- `Container::class` + `Application::class` — self-references

---

## Container (DI)

`Container/Container.php` — lightweight autowiring container.

- `bind(abstract, concrete, singleton=false)`
- `singleton(abstract, concrete)` — resolved once, cached
- `instance(abstract, instance)` — pre-existing instance
- `make(abstract)` — resolve with reflection autowiring
- `call(callable, params)` — invoke with DI

Resolution: checks resolved → binding → reflection constructor injection.

**Safety guards:**
- Blocks abstract/interface resolution (no concrete class → RuntimeException)
- Circular dependency detection via `$resolving` tracking

---

## Router

`Http/Router.php` — simple regex-based router with compiled pattern cache.

### Registration

```php
$router->get('/path', handler);
$router->post('/path', handler);
$router->match(['GET','POST'], '/path', handler);
$router->middleware(['auth'])->get('/path', handler);  // RouteRegistrar proxy
```

### Handler Types

- Closure: `fn() => 'Hello'`
- String: `'Controller@method'`
- Array: `[Controller::class, 'method']`

### Route Parameters

`{name}`, `{int}` (digits), `{slug}` (alphanumeric+hyphens), `{uuid}`.

### Dispatch

Returns `['handler', 'params', 'middleware']` or throws 404. HEAD falls back to GET.

**Performance:** Compiled regex patterns cached in `$compiledCache` to avoid recompilation per request.

### Internal Route

`POST /_kailyn/update` → `ComponentManager::handleUpdate()` (reactive AJAX).

---

## HTTP Kernel & Middleware

`Http/Kernel.php` — onion pipeline.

### Pipeline

```
middleware1 → middleware2 → route_handler → response
```

- Global middleware group `'web'`: `csrf`, `security-headers`
- Route-specific: `$router->middleware(['auth'])->get(...)`
- Middleware base: `abstract handle(Request, callable $next): Response`

### Request Method Override

`_method` field validated — only `PUT`, `PATCH`, `DELETE` allowed. Invalid values ignored.

### Error Handling

- **Debug OFF (production):** Generic "Server Error" message, stack trace logged server-side only
- **Debug ON:** Error message shown (HTML-escaped), stack trace logged server-side only

### Registered Middleware

| Name | Class | What it does |
|------|-------|-------------|
| `auth` | `AuthMiddleware` | Checks session `user_id`, redirect to `/login` |
| `guest` | `GuestMiddleware` | Redirects to `/dashboard` if authenticated |
| `csrf` | `CsrfMiddleware` | Validates `_token` / `X-CSRF-TOKEN` via `hash_equals()`, 419 on fail. **`POST /_kailyn/update` included** (was exempted, now required) |
| `security-headers` | `SecurityHeadersMiddleware` | `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy`, `Content-Security-Policy` |
| `throttle` | `ThrottleMiddleware` | 5 requests / 15 min per IP+path, 429 on exceed. **Uses CacheManager** (not sessions) |

### Security Headers

| Header | Value |
|--------|-------|
| `X-Content-Type-Options` | `nosniff` |
| `X-Frame-Options` | `SAMEORIGIN` |
| `Referrer-Policy` | `strict-origin-when-cross-origin` |
| `Permissions-Policy` | `camera=(), microphone=(), geolocation=()` |
| `Content-Security-Policy` | `default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'; connect-src 'self'; frame-ancestors 'self'` |

---

## Template Engine

`Template/Engine.php` + `Template/Compiler.php` — Blade-like syntax, compiled to PHP.

### Directives

| Directive | Output |
|-----------|--------|
| `{{ $var }}` | Escaped echo (`htmlspecialchars`) |
| `{!! $var !!}` | Raw echo |
| `@if`/`@elseif`/`@else`/`@endif` | `<?php if:` / `endif;` |
| `@unless`/`@endunless` | `<?php if (!(...)):` |
| `@foreach`/`@endforeach` | `<?php foreach:` / `endforeach;` |
| `@for`/`@endfor`, `@while`/`@endwhile` | Loop constructs |
| `@include('view', [...])` | Partial render |
| `@extends('layout')` | Set layout |
| `@section('name')` / `@endsection` | Capture section |
| `@yield('name', 'default')` | Output section |
| `@parent` | Parent section content |
| `@json($data)` | `json_encode` with security flags |
| `@csrf` | `csrf_field()` output |
| `@component('name', [...])` | Render reactive component |
| `@php`/`@endphp` | Raw PHP |
| `@dd`/`@dump` | Debug |
| `{{-- comment --}}` | PHP comment |

### Rendering Flow

```
engine->render('view', data)
  → validateViewName (no .. / \ path traversal)
  → getCompiled (checks mtime for recompile)
  → extract(data, EXTR_SKIP)
  → require compiled PHP
  → if @extends set, render layout wrapping content
```

Layout inheritance: nested sections via output buffering, `@parent` supported.

---

## Component System (Livewire-style Reactive)

`Component/Component.php` + `ComponentManager.php` — AJAX-driven reactive components.

### PHP 8 Attributes

| Attribute | Target | Purpose |
|-----------|--------|---------|
| `#[Reactive]` | Property | State tracked on client + server |
| `#[Computed]` | Method | Cached computed property (per-request) |
| `#[Action]` | Method | Callable from frontend via AJAX |

### Sensitive State

Properties listed in `$sensitiveState` are excluded from client-side JSON. Server-only data never leaks to the DOM.

### Lifecycle

```
mount(props) → boot() → render()
  rendering() → resolveView() → renderPartial(view, state) → rendered(html)
  → wraps in <div k-component k-id k-state='{json}'>
```

### AJAX Update Flow

```
POST /_kailyn/update {component, method, state, params}
  → ComponentManager::handleUpdate()
    → make component, hydrate state
    → check method is in #[Action] allowlist
    → callMethod(method, params)
      → calling() → called()
    → renderInner() → return {html, state, result} JSON
```

Batch update (multiple methods in one request):

```
POST /_kailyn/update {component, batch: [{method, params}...], state}
  → ComponentManager::handleBatchUpdate()
    → iterate batch, call each method
    → return {results: [{html, state}...]}
```

### State Management

- `#[Reactive]` properties serialized to `k-state` JSON attribute
- `hydrate()` diffs and sets state, calls lifecycle hooks
- Watchers: `updated{Property}()` magic method, `$watchers` array, `watch()` runtime

### Client (kailyn.js)

Vanilla JS reactive engine. No build step required. Livewire-like behavior.

**Core:**
- Scans `[k-component]` elements, creates `KailynComponent` instances
- Event delegation on component root element
- Method call syntax: `methodName(arg1, 'string', true, null)`
- `callMethod()` → batched AJAX → `applyUpdate()` patches innerHTML + rebinds events
- **CSRF:** `X-CSRF-TOKEN` header sent with every request

**Directives:**

| Directive | Description |
|-----------|-------------|
| `k-on:event="method"` | Event binding (click, submit, dblclick, etc.) |
| `k-on:event.mod="method"` | Event with modifiers (`.prevent`, `.stop`) |
| `k-on:keydown.keys="method"` | Keyboard event with key modifiers (`.enter`, `.escape`, `.tab`, `.space`) |
| `k-model="property"` | Two-way input binding |
| `k-model.debounce.ms="property"` | Debounced input (e.g. `k-model.debounce.300ms="query"`) |
| `k-loading="propName"` | Show loading state on element while server processes |
| `k-optimistic` | Apply state change before server confirms, revert on error |
| `k-poll.ms="method"` | Poll server at interval (e.g. `k-poll.5s="refresh"`) |
| `k-transition` | Fade animation on DOM updates |
| `k-error` | Error message container (shown on server error) |

**Modifiers:**
- `.prevent` — `e.preventDefault()`
- `.stop` — `e.stopPropagation()`
- `.enter` / `.escape` / `.tab` / `.space` / `.arrowup` / `.arrowdown` / `.delete` / `.backspace` — Key filters

**Features:**
- **Request batching:** Multiple `callMethod()` in same tick → single AJAX request
- **Loading states:** `k-loading="saving"` → button disabled + `.k-loading` class during request
- **Optimistic updates:** `k-optimistic` → state applied immediately, reverted on server error
- **Error handling:** `k-error` elements show server errors, custom `kailyn:error` event dispatched
- **Polling:** `k-poll.10s="refreshData"` → periodic server calls while component is in DOM
- **Transitions:** `k-transition` → fade-in/fade-out on DOM patches
- **Debounce:** `k-model.debounce.300ms` → debounced sync to server
- **`$wire()` helper:** `this.$wire('methodName')(params)` — programmatic method calls

---

## Database Layer

### Connection (`Database/Connection.php`)

PDO wrapper for `sqlite`, `mysql`, `pgsql`, `sqlsrv`. Methods: `select()`, `selectOne()`, `insert()`, `update()`, `delete()`, `statement()`, `transaction()`, `getDriverName()`.

### QueryBuilder (`Database/QueryBuilder.php`)

Fluent, chainable. All methods return `$this`.

```php
DB::table('users')->where('age', '>', 18)->orderBy('name')->get();
DB::table('users')->find(1);
DB::table('users')->whereIn('id', [1,2,3])->paginate(15);
DB::table('users')->insert(['name' => '...']);
DB::table('users')->where('id', 1)->update(['name' => '...']);
DB::table('users')->where('id', 1)->delete();
```

Aggregates: `count()`, `avg()`, `sum()`, `min()`, `max()`, `exists()`, `doesntExist()`.
Joins: `join()`, `leftJoin()`, `rightJoin()`.
Chunking: `chunk(size, callback)`.

### Model (`Database/Model.php`)

Active Record with `ArrayAccess` and `JsonSerializable`.

```php
class User extends Model {
    protected string $table = 'users';      // auto-derived from class name
    protected array $fillable = ['name', 'email', 'password'];
    protected array $guarded = ['*'];
    protected array $hidden = ['password'];
    protected array $visible = [];           // if non-empty, only these keys in toArray()
    protected array $appends = ['full_name'];
    protected array $with = [];              // eager-loaded relations
}
```

**Static:** `find()`, `where()`, `orWhere()`, `all()`, `count()`, `create()`.
**Instance:** `save()`, `delete()`, `update()`, `fill()`, `fresh()`, `refresh()`.
**Relations:** `hasOne()`, `hasMany()`, `belongsTo()`, `belongsToMany()` (lazy-loaded via `__get`).
**Accessors:** `get{Property}Attribute()`, `set{Property}Attribute()`.
**Eager loading:** `$with` property or `load('relation')`.
**Mass-assignment:** protected by `$fillable` / `$guarded` (checked in `forceFill`, `__set`, `offsetSet`).
**Serialization:** `toArray()` respects `$visible` (if non-empty, filters keys) and `$hidden` (removes keys).

### MongoDB

`MongoConnection`, `MongoQueryBuilder`, `MongoModel` mirror the SQL layer via `mongodb/mongodb` driver.

### Schema Builder (`Database/Schema.php`)

```php
Schema::create('users', function ($table) {
    $table->id();
    $table->string('name');
    $table->string('email')->unique();
    $table->timestamps();
});
Schema::table('users', fn($table) => $table->string('phone')->nullable()->after('email'));
Schema::drop('users');
Schema::hasTable('users');
```

Column types: `id()`, `increments()`, `bigIncrements()`, `string()`, `text()`, `integer()`, `boolean()`, `date()`, `dateTime()`, `timestamp()`, `timestamps()`, `float()`, `decimal()`, `json()`, `softDeletes()`, `rememberToken()`, `foreignId()`, `foreignIdFor()`.
Modifiers: `nullable()`, `unique()`, `default()`, `unsigned()`, `primary()`, `after()`.
Foreign keys: `$table->foreign('col')->references('id')->on('table')->onDelete('cascade')`.

---

## Cache System

### Interface (`Cache/CacheDriver.php`)

```php
get(key, default) | set(key, value, ttl?) | delete(key) | clear() | has(key)
remember(key, ttl, fn) | rememberForever(key, fn) | pull(key, default)
```

### Drivers

| Driver | Storage | Serialization | Key Format |
|--------|---------|---------------|-----------|
| `FileCacheDriver` | JSON files | `json_encode`/`json_decode` | `{prefix}{sha1(key)}.cache` |
| `RedisCacheDriver` | Redis `\Redis` extension | Native Redis | `{prefix}{key}` |
| `DatabaseCacheDriver` | SQL table | `json_encode`/`json_decode` | `{prefix}{key}` via parameterized queries |

> **Security:** All drivers use `json_encode`/`json_decode` (never `serialize`/`unserialize`) to prevent PHP object injection.

### CacheManager

```php
$cache = cache();                          // default store
$cache->store('redis')->get('key');        // named store
cache('key');                              // get
cache('key', 'value', 3600);               // set
cache()->remember('key', 3600, fn() => expensive());
cache()->pull('key');
cache()->extend('custom', fn($config) => new CustomDriver($config));  // custom driver
```

### Configuration (`config/cache.php`)

```php
'default' => env('CACHE_DRIVER', 'file'),
'prefix'  => env('CACHE_PREFIX', 'kailyn_'),
'stores'  => ['file' => [...], 'redis' => [...], 'database' => [...]]
```

---

## Migration System

### Migration Files (`database/migrations/`)

Format: `YYYY_MM_DD_HHMMSS_name.php`

```php
return new class extends \Kailyn\Database\Migration {
    public function up(): void { Schema::create('table', fn($t) => ...); }
    public function down(): void { Schema::drop('table'); }
};
```

### Migrator (`Database/Migrator.php`)

Tracks runs in `migrations` table (id, migration, batch, executed_at).

### Commands

| Command | Description |
|---------|-------------|
| `make:migration {name} {--create=} {--table=}` | Create migration file |
| `migrate {--force}` | Run all pending |
| `migrate:rollback {--step=1}` | Rollback N batches |
| `migrate:fresh {--force}` | Drop all + re-run |

`make:model Product -m` auto-creates accompanying migration.

---

## Tulpar CLI (Symfony Console)

### Entry

`bin/tulpar` → `Console/Kernel.php` (9 built-in commands + user commands from `app/Console/Kernel.php`).

### Signature Parser (`Console/Signature.php`)

Parses Laravel-style signatures: `make:model {name} {--force} {-m|--migration}`.

Syntax: `{arg}` (required), `{arg?}` (optional), `{arg=default}`, `{arg*}` (array), `{--opt}`, `{-s|--long}`, `{--opt=default}`, `{arg : description}`.

### Base Command (`Console/Command.php`)

```php
class MyCommand extends Command {
    protected static $defaultName = 'my:command';
    protected string $signature = 'my:command {name} {--force}';
    protected function handle(): int {
        $name = $this->argument('name');
        $force = $this->option('force');
        $this->info("Done!");
        return self::SUCCESS;
    }
}
```

Output helpers: `line()`, `info()`, `error()`, `warn()`, `comment()`, `question()`, `alert()`, `table()`, `newLine()`.
Interactive: `ask()`, `confirm()`, `secret()`, `choice()`, `anticipate()`.
Progress: `progressBar(max)`.
Cross-command: `call('other:command', args)`, `callSilent()`.

### Built-in Commands

- `serve {host=127.0.0.1} {--port=8000}` — PHP dev server
- `make:model {name} {--force} {-m|--migration}`
- `make:controller {name} {--resource}`
- `make:migration {name} {--create=} {--table=}`
- `make:component {name}`
- `migrate {--force}`
- `migrate:rollback {--step=1}`
- `migrate:fresh {--force}`
- `route:list`

---

## Session & Auth

### SessionManager

PHP native sessions with secure cookies (`HttpOnly`, `SameSite=Lax`, `Secure` on HTTPS).

**Lifetime:** 7200 seconds (2 hours) — set via `ini_set('session.gc_maxlifetime', 7200)`.

Methods: `start()`, `get()`, `set()`, `has()`, `remove()`, `pull()`, `destroy()`, `regenerate()`, `flash()`, `flashNow()`, `reflash()`, `keep()`, `token()`, `validateToken()`.

**`destroy()` behavior:** Clears `$_SESSION` array, destroys session, deletes session cookie.

### Auth Flow (in `App\Controllers\AuthController`)

```
Login:  email/password → User::where() → password_verify() → session set → regenerate
Register:  validate → User::create (bcrypt cost=12 via setPasswordAttribute) → auto-login
Logout:  session->destroy() → redirect
```

### CSRF

- Token stored in session (32 bytes hex)
- Validated via `hash_equals()` for all non-GET/HEAD/OPTIONS
- **`POST /_kailyn/update` requires `X-CSRF-TOKEN` header** (sent by kailyn.js)
- Helpers: `csrf_token()`, `csrf_field()` (HTML-escaped), `csrf_meta()` → `<meta name="csrf-token" content="...">`
- Middleware returns 419 on failure

---

## Validation (`Validation/Validator.php`)

```php
$v = validator($data, ['email' => 'required|email|min:3'], ['email.required' => ':field required']);
$v->passes() / $v->fails()
$v->errors() / $v->errorsFor('email') / $v->firstError('email')
$v->validated()     // throws if fails
```

**14 rules:** `required`, `email`, `min:n`, `max:n`, `numeric`, `integer`, `string`, `url`, `confirmed`, `alpha`, `alpha_num`, `alpha_dash`, `array`, `boolean`, `date`.

Custom messages use `:field`, `:param`, `:params` placeholders.

---

## Helper Functions (`src/Kailyn/helpers.php`)

| Function | Signature |
|----------|-----------|
| `env()` | `env(key, default=null)` |
| `version()` | `version() → returns framework version from VERSION file` |
| `app()` | `app(abstract=null, instance=null)` |
| `config()` | `config(key=null, default=null)` |
| `base_path()` | `base_path(path=null)` |
| `view_path()` | `view_path(path=null)` |
| `storage_path()` | `storage_path(path=null)` |
| `view()` | `view(name, data=[])` |
| `redirect()` | `redirect(url, status=302)` |
| `back()` | `back() → redirect to HTTP_REFERER (validated against app host, prevents open redirect)` |
| `session()` | `session(key=null, default=null)` |
| `validator()` | `validator(data, rules, messages=[])` |
| `csrf_token()` | `csrf_token()` |
| `csrf_field()` | `csrf_field() → HTML-escaped hidden input` |
| `csrf_meta()` | `csrf_meta() → <meta name="csrf-token"> tag` |
| `method_field()` | `method_field(method)` |
| `cache()` | `cache(key=null, value=null, ttl=null)` |

---

## Security Principles

When modifying cache drivers, use `json_encode`/`json_decode` (not `serialize`/`unserialize`) to prevent PHP object injection. When accepting user input in SQL, always use parameterized queries (never string interpolation). Route handlers can be Closures, `Class@method`, or `[Class, 'method']`. Middleware names in `$router->middleware()` are looked up in the `$routeMiddleware` map. Always validate view names with `validateViewName()` (rejects `..`, `/`, `\`). Mass-assignment is protected by `$fillable` and `$guarded` arrays.

---

## Security Fixes Applied (v0.2.0)

| Fix | File | Description |
|-----|------|-------------|
| CSRF on AJAX | `CsrfMiddleware.php`, `kailyn.js` | `/_kailyn/update` no longer exempted; JS sends `X-CSRF-TOKEN` header |
| Debug leak | `Kernel.php` | Stack traces logged server-side only, never sent to client |
| Open redirect | `helpers.php` | `back()` validates referer against app host |
| `_method` validation | `Kernel.php` | Only `PUT`, `PATCH`, `DELETE` allowed |
| `forceFill()` | `Model.php` | Now truly bypasses `$guarded` check |
| Session lifetime | `SessionManager.php` | 7200s limit via `gc_maxlifetime` |
| Rate limiting | `ThrottleMiddleware.php` | Uses CacheManager instead of sessions |
| Security headers | `SecurityHeadersMiddleware.php` | CSP + Permissions-Policy added, X-XSS-Protection removed |
| Container safety | `Container.php` | Abstract/interface blocked, circular dependency detection |
| Model `$visible` | `Model.php` | `toArray()` respects `$visible` filter |
| Router cache | `Router.php` | Compiled regex patterns cached |
| Session destroy | `SessionManager.php` | Clears `$_SESSION` + deletes cookie |
| Sensitive state | `Component.php` | `$sensitiveState` excludes props from client JSON |
| HTML-escaped CSRF | `helpers.php` | `csrf_field()` escaped, new `csrf_meta()` helper |

---

## Key Conventions

- **Models**: Active Record, singular class name → plural snake_case table
- **Views**: dot notation (`pages.home` → `app/Views/pages/home.html` or `.php`)
- **Components**: PascalCase class in `app/Components/`, view in `app/Views/components/`
- **Controllers**: PascalCase in `app/Controllers/`, methods camelCase
- **Migrations**: anonymous class returning `Migration` instance
- **Config**: PHP files returning arrays, accessed via `config('file.key')`
- **Routes**: defined in `routes/web.php`, middleware via `->middleware([...])`
- **Services**: resolved from Container via `app(Service::class)` or type-hinted constructor parameters
- **Versioning**: Semantic Versioning (MAJOR.MINOR.PATCH), tracked in `VERSION`, `config/app.php`, `composer.json`
- **Git commits**: Conventional Commits (`feat:`, `fix:`, `security:`, `chore:`, `refactor:`, `docs:`)
