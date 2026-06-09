# Kailyn Framework

Livewire benzeri reactive component yapısına sahip, PHP 8.3+ ile yazılmış tam kapsamlı web framework.

## Requirements

- PHP 8.3+
- Composer
- PDO extensions (mysql, sqlite, pgsql)

## Kurulum

```bash
composer install
```

## Çalıştırma

```bash
# Geliştirme sunucusu ile
php bin/tulpar serve

# Veya doğrudan PHP ile
php -S localhost:8000 -t public/ public/router.php
```

---

## Tulpar CLI Komutları

### `serve`

PHP built-in development sunucusunu başlatır.

| Argüman | Varsayılan | Açıklama |
|---------|-----------|----------|
| `host` | `127.0.0.1` | Sunucu adresi |
| `--port` | `8000` | Port numarası |

```bash
php bin/tulpar serve
php bin/tulpar serve 0.0.0.0 --port=8080
```

---

### `make:model`

Yeni bir Model sınıfı oluşturur.

| Argüman | Açıklama |
|---------|----------|
| `name` | Model adı (ör: `User`, `Post`) |
| `--force` | Varsa üzerine yaz |

```bash
php bin/tulpar make:model User
php bin/tulpar make:model Post --force
```

**Oluşturulan dosyalar:**
- `app/Models/{Name}.php`
- `database/migrations/{timestamp}_create_{table}_table.php` (`-m` ile)

```php
<?php

namespace App\Models;

use Kailyn\Database\Model;

class User extends Model
{
    protected string $table = 'users';
    protected array $fillable = [];
}
```

---

### `make:controller`

Yeni bir Controller sınıfı oluşturur.

| Argüman | Açıklama |
|---------|----------|
| `name` | Controller adı |
| `--resource` | Resource (CRUD) metotları ile oluştur |

```bash
php bin/tulpar make:controller HomeController
php bin/tulpar make:controller ProductController --resource
```

**Oluşturulan dosya:** `app/Controllers/{Name}.php`

Simple controller (`--resource` olmadan):

```php
class HomeController
{
    public function index(Request $request): Response
    {
        return Response::json(['message' => 'Hello']);
    }
}
```

Resource controller (`--resource` ile birlikte `index`, `show`, `store`, `update`, `destroy` metotlarını içerir).

---

### `make:migration`

Yeni bir migration dosyası oluşturur.

| Argüman | Açıklama |
|---------|----------|
| `name` | Migration adı |
| `--create=` | Tablo oluşturma migration'ı (`Schema::create` stub) |
| `--table=` | Tablo değiştirme migration'ı (`Schema::table` stub) |

```bash
php bin/tulpar make:migration create_users_table --create=users
php bin/tulpar make:migration add_email_to_users --table=users
php bin/tulpar make:migration add_index_to_posts
```

**Oluşturulan dosya:** `database/migrations/{timestamp}_{name}.php`

```php
<?php

use Kailyn\Database\Schema;
use Kailyn\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function ($table) {
            $table->id();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::drop('users');
    }
};
```

---

### `migrate`

Bekleyen migration'ları çalıştırır.

```bash
php bin/tulpar migrate
```

---

### `migrate:rollback`

Son migration batch'lerini geri alır.

| Argüman | Varsayılan | Açıklama |
|---------|-----------|----------|
| `--step` | `1` | Geri alınacak batch sayısı |

```bash
php bin/tulpar migrate:rollback
php bin/tulpar migrate:rollback --step=3
```

---

### `migrate:fresh`

Tüm tabloları drop edip tüm migration'ları yeniden çalıştırır.

```bash
php bin/tulpar migrate:fresh
php bin/tulpar migrate:fresh --force
```

---

### `make:component`

Reactive component oluşturur (PHP sınıfı + view template).

| Argüman | Açıklama |
|---------|----------|
| `name` | Component adı |

```bash
php bin/tulpar make:component counter
php bin/tulpar make:component alert
```

**Oluşturulan dosyalar:**

- `app/Components/{Name}.php`
- `app/Views/components/{name}.html`

---

### `route:list`

Tüm kayıtlı rotaları tablo olarak listeler.

```bash
php bin/tulpar route:list
```

Çıktı:

```
+--------+-----------------+---------+
| Method | URI             | Handler |
+--------+-----------------+---------+
| GET    | /               | Closure |
| GET    | /hello/{name}   | Closure |
| POST   | /_kailyn/update | Closure |
+--------+-----------------+---------+
```

---

## Routing

`routes/web.php` dosyasında tanımlanır.

```php
$router->get('/', fn() => view('welcome', ['name' => 'Kailyn']));
$router->get('/hello/{name}', fn(string $name) => "Hello, {$name}!");
$router->get('/json', fn() => ['message' => 'Hello']);
$router->post('/data', fn(Request $request) => ['input' => $request->all()]);
```

| Metot | Kullanım |
|-------|----------|
| `GET` | `$router->get('/path', handler)` |
| `POST` | `$router->post('/path', handler)` |
| `PUT` | `$router->put('/path', handler)` |
| `PATCH` | `$router->patch('/path', handler)` |
| `DELETE` | `$router->delete('/path', handler)` |
| `match` | `$router->match(['GET', 'POST'], '/path', handler)` |
| `any` | `$router->any('/path', handler)` |

Route parametreleri:

```php
$router->get('/user/{id}', fn(string $id) => "User: {$id}");
$router->get('/post/{slug}', fn(string $slug) => "Post: {$slug}");
```

Handler tipleri:

```php
// Closure
$router->get('/', fn() => 'Hello');

// Controller@method
$router->get('/', 'App\Controllers\HomeController@index');
// veya
$router->get('/', [App\Controllers\HomeController::class, 'index']);
```

### _method override

Formlarda PUT/PATCH/DELETE kullanımı:

```html
<form method="POST" action="/post/1">
    <input type="hidden" name="_method" value="PUT">
    <input type="hidden" name="_token" value="{{ csrf_token() }}">
</form>
```

---

## Template Engine

Kailyn'in kendi template engine'i layout inheritance (extends/section/yield) destekler.
Dosya uzantısı: `.html`

### Layout Kullanımı

```html
{{-- layouts/app.html --}}
<!DOCTYPE html>
<html>
<head><title>@yield('title', 'Kailyn')</title></head>
<body>
    <header>@section('header')Header@show</header>
    <main>@yield('content')</main>
    <footer>&copy; 2026</footer>
</body>
</html>
```

```html
{{-- pages/home.html --}}
@extends('layouts.app')

@section('title', 'Home')
@section('content')
    <h1>Welcome</h1>
    <p>Hello, {{ $name }}!</p>
@endsection
```

PHP'de render:

```php
$html = $engine->render('pages.home', ['name' => 'Kailyn']);
// veya helper ile
$html = view('pages.home', ['name' => 'Kailyn']);
```

### Template Direktifleri

| Direktif | Açıklama |
|----------|----------|
| `{{ $var }}` | Escaped output |
| `{!! $var !!}` | Raw output |
| `{{-- comment --}}` | Yorum |
| `@if($cond)` / `@elseif` / `@else` / `@endif` | Koşul |
| `@unless($cond)` / `@endunless` | Ters koşul |
| `@isset($var)` / `@endisset` | Varlık kontrolü |
| `@empty($var)` / `@endempty` | Boş kontrolü |
| `@foreach($items as $item)` / `@endforeach` | Döngü |
| `@for($i=0;$i<10;$i++)` / `@endfor` | For döngüsü |
| `@while($cond)` / `@endwhile` | While döngüsü |
| `@include('partial')` | Alt view ekle |
| `@includeIf('partial')` | Varsa ekle |
| `@extends('layout')` | Layout belirt |
| `@section('name')` / `@endsection` | Section başlat/durdur |
| `@section('name', 'value')` | Tek satır section |
| `@yield('name')` | Section çıktısı |
| `@yield('name', 'default')` | Varsayılan değerli |
| `@parent` | Parent section içeriği |
| `@component('name')` | Component ekle |
| `@json($data)` | JSON encode |
| `@csrf` | CSRF token input |
| `@php` / `@endphp` | Ham PHP kodu |

---

## Reactive Component Sistemi

Livewire benzeri, AJAX ile DOM güncelleyen component sistemi.

### Component Oluşturma

```php
<?php

namespace App\Components;

use Kailyn\Component\Attributes\Reactive;
use Kailyn\Component\Component;

class Counter extends Component
{
    #[Reactive]
    public int $count = 0;

    public function increment(): void
    {
        $this->count++;
    }

    public function add(int $amount): void
    {
        $this->count += $amount;
    }
}
```

### Component View

```html
{{-- app/Views/components/counter.html --}}
<div class="counter">
    <h2>Count: {{ $count }}</h2>
    <button k-on:click="decrement">-</button>
    <button k-on:click="increment">+</button>
    <button k-on:click="add(10)">+10</button>
</div>
```

### Kullanım

```php
// Sayfada component render
$router->get('/demo', fn() => view('reactive-demo'));

// View içinde component çağırma
@component('counter')
```

### Kailyn.js Client-side Directive'ler

| Direktif | Açıklama |
|----------|----------|
| `k-on:click="method"` | Click event |
| `k-on:keydown="method"` | Klavye event |
| `k-on:keydown.enter="method"` | Enter tuşu |
| `k-on:dblclick="method"` | Çift tık |
| `k-on:submit="method"` | Form submit |
| `k-model="property"` | İki yönlü input binding |
| `k-text="property"` | Text content binding |

### Component Lifecycle

1. Sayfa yüklenir → Component render edilir, `k-state` attribute'unda state tutulur
2. Kullanıcı `k-on:click` butona tıklar
3. `kailyn.js` event'i yakalar, method adını ve state'i alır
4. `POST /_kailyn/update` AJAX isteği gönderilir
5. Server component'i re-hydrate eder, method'u çalıştırır, re-render eder
6. JSON yanıt: `{html, state, result}`
7. `kailyn.js` DOM'u günceller, state'i günceller

---

## Cache Sistemi

Redis, File ve Database backend'leri ile çoklu store desteği.

### Konfigürasyon

```php
<?php // config/cache.php

return [
    'default' => env('CACHE_DRIVER', 'file'),
    'prefix'  => env('CACHE_PREFIX', 'kailyn_'),
    'stores'  => [
        'file' => [
            'driver' => 'file',
            'path'   => storage_path('cache'),
        ],
        'redis' => [
            'driver'   => 'redis',
            'host'     => env('REDIS_HOST', '127.0.0.1'),
            'port'     => env('REDIS_PORT', 6379),
            'password' => env('REDIS_PASSWORD'),
            'database' => env('REDIS_DB', 0),
        ],
        'database' => [
            'driver'     => 'database',
            'connection' => env('DB_CONNECTION', 'sqlite'),
            'table'      => 'cache',
        ],
    ],
];
```

### Kullanım

```php
// Helper
cache('key');                  // get
cache('key', 'value', 3600);  // set with TTL (saniye)
cache()->remember('key', 3600, fn() => expensiveQuery());
cache()->rememberForever('key', fn() => expensiveQuery());
cache()->pull('key');         // get + delete
cache()->has('key');          // bool
cache()->delete('key');
cache()->clear();

// Named store
cache()->store('redis')->set('key', 'value');
cache()->store('file')->get('key');
```

### Custom Driver

```php
cache()->extend('custom', function ($config) {
    return new CustomCacheDriver($config);
});
```

### Cache Driver API

| Metot | Açıklama |
|-------|----------|
| `get(key, default)` | Değer oku |
| `set(key, value, ttl)` | Değer yaz (ttl = saniye, null = kalıcı) |
| `delete(key)` | Sil |
| `clear()` | Tümünü temizle |
| `has(key)` | Varlık kontrolü |
| `remember(key, ttl, callback)` | Yoksa callback çalıştır, cache'le |
| `rememberForever(key, callback)` | Kalıcı remember |
| `pull(key, default)` | Oku + sil |

---

## Migration Sistemi

Tabloları versiyon kontrolü altında yönetir. Migration dosyaları `database/migrations/` dizininde bulunur.

### Migration Oluşturma

```php
<?php
// database/migrations/2026_01_15_000001_create_users_table.php

use Kailyn\Database\Schema;
use Kailyn\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::drop('users');
    }
};
```

### Migration'ları Çalıştırma

```bash
php bin/tulpar migrate
```

Her migration batch numarası ile kaydedilir. Aynı batch'teki migration'lar birlikte rollback edilir.

### Rollback

```bash
php bin/tulpar migrate:rollback        # son batch'i geri al
php bin/tulpar migrate:rollback --step=3  # son 3 batch'i geri al
```

### Fresh (Sıfırdan)

Tüm tabloları drop edip tüm migration'ları yeniden çalıştırır:

```bash
php bin/tulpar migrate:fresh
php bin/tulpar migrate:fresh --force  # onay sormadan
```

### Schema Builder

Migration'lar içinde kullanılan Schema Builder desteği:

```php
Schema::create('products', function ($table) {
    $table->id();
    $table->string('name', 100);
    $table->text('description')->nullable();
    $table->integer('price')->unsigned();
    $table->boolean('is_active')->default(true);
    $table->foreignId('user_id')->constrained()->onDelete('cascade');
    $table->timestamps();
    $table->softDeletes();
});
```

| Metot | Açıklama |
|-------|----------|
| `id()` | Big increments primary key |
| `increments(name)` | Auto increment |
| `string(name, length)` | VARCHAR |
| `text(name)` | TEXT |
| `integer(name)` | INT |
| `boolean(name)` | TINYINT(1) |
| `date(name)` / `dateTime(name)` | Tarih alanları |
| `timestamp(name)` | TIMESTAMP |
| `timestamps()` | `created_at` + `updated_at` |
| `softDeletes()` | `deleted_at` alanı |
| `float(name, precision, scale)` | Float |
| `decimal(name, precision, scale)` | Decimal |
| `json(name)` / `jsonb(name)` | JSON |
| `foreignId(name)` | Unsigned big integer (FK için) |
| `nullable()` | NULL değere izin ver |
| `default(value)` | Varsayılan değer |
| `unique()` | Unique constraint |
| `unsigned()` | Unsigned |
| `foreign(col)->references(col)->on(table)` | Foreign key |
| `index(columns)` | Index |

---

## Veritabanı

```php
use Kailyn\Database\Connection;
use Kailyn\Database\Model;

$connection = new Connection([
    'driver'   => 'mysql',
    'host'     => '127.0.0.1',
    'database' => 'kailyn',
    'username' => 'root',
    'password' => '',
]);

Model::connection($connection);
```

Veya config kullanarak:

```php
$config = app(Kailyn\Config\Config::class);
$dbConfig = $config->get('database.connections.mysql');
$connection = new Kailyn\Database\Connection($dbConfig);
Kailyn\Database\Model::connection($connection);
```

### Query Builder

```php
use Kailyn\Database\QueryBuilder;

$builder = new QueryBuilder($connection);
$builder->table('users')
    ->where('age', '>', 18)
    ->where('status', 'active')
    ->orderBy('name', 'asc')
    ->limit(10);

$users = $builder->get();
$user = $builder->first();
$count = $builder->count();
```

Metotlar:

| Metot | Açıklama |
|-------|----------|
| `select(...$cols)` | Kolon seç |
| `where(col, op, val)` | Koşul |
| `orWhere(col, op, val)` | OR koşul |
| `whereIn(col, values)` | IN sorgusu |
| `whereNull(col)` | IS NULL |
| `whereNotNull(col)` | IS NOT NULL |
| `join(table, first, op, second)` | JOIN |
| `leftJoin(...)` | LEFT JOIN |
| `orderBy(col, dir)` | Sıralama |
| `limit(n)` | Limit |
| `offset(n)` | Offset |
| `get()` | Sonuçları getir |
| `first()` | İlk sonuç |
| `find(id)` | ID ile bul |
| `value(col)` | Tek kolon değeri |
| `pluck(col, key?)` | Kolon listesi |
| `count()` | Sayı |
| `exists()` | Varlık kontrolü |
| `insert(data)` | Ekle |
| `update(data)` | Güncelle |
| `delete()` | Sil |
| `truncate()` | Tabloyu boşalt |
| `toSql()` | SQL sorgusunu göster |

### Model (ActiveRecord)

```php
class User extends Model
{
    protected string $table = 'users';
    protected array $fillable = ['name', 'email'];
}

// Sorgulama
$user = User::find(1);
$users = User::where('status', 'active')->get();
$count = User::count();

// CRUD
$user = new User(['name' => 'Alice', 'email' => 'alice@test.com']);
$user->save();

$user->name = 'Bob';
$user->save();

$user->delete();

// Mass assignment
User::create(['name' => 'Charlie', 'email' => 'charlie@test.com']);
```

---

## Validation

```php
use Kailyn\Validation\Validator;

$validator = Validator::make($request->all(), [
    'email' => 'required|email',
    'name'  => 'required|min:3|max:255',
    'age'   => 'numeric|min:18',
]);

if ($validator->fails()) {
    $errors = $validator->errors();
    $firstError = $validator->firstError();
}

$validated = $validator->validated();
```

Desteklenen kurallar:

| Kural | Açıklama |
|-------|----------|
| `required` | Zorunlu alan |
| `email` | Geçerli email |
| `min:n` | Minimum değer/uzunluk |
| `max:n` | Maksimum değer/uzunluk |
| `numeric` | Numerik değer |
| `integer` | Tam sayı |
| `string` | String |
| `url` | Geçerli URL |
| `confirmed` | `_confirmation` alanı ile eşleşme |
| `alpha` | Sadece harf |
| `alpha_num` | Harf ve rakam |
| `alpha_dash` | Harf, rakam, tire, alt çizgi |
| `array` | Array |
| `boolean` | Boolean |
| `date` | Geçerli tarih |

---

## Session

```php
use Kailyn\Session\SessionManager;

$session = new SessionManager;
$session->start();

$session->set('user_id', 1);
$id = $session->get('user_id');
$session->has('user_id');      // bool
$session->remove('user_id');
$session->pull('user_id');     // get + remove

// Flash messages
$session->flash('success', 'Kayıt başarılı!');
$message = $session->flash('success');

// CSRF
$token = $session->token();
$session->validateToken($token);  // bool
```

Helper:

```php
session('key', 'default');
session()->set('key', 'value');
csrf_token();
csrf_field();
```

---

## Container (DI)

```php
$container->bind(Interface::class, ConcreteClass::class);
$container->singleton(Service::class);
$container->instance('key', $object);

$instance = $container->make(Service::class);
$result = $container->call([$object, 'method'], ['param' => 'value']);
```

Autowiring: constructor parametreleri otomatik çözümlenir.

---

## Config

```php
$value = config('app.name', 'default');
$value = config('database.connections.mysql.host');
```

Config dosyaları: `config/*.php`

```php
<?php // config/app.php
return [
    'name' => 'Kailyn',
    'env'  => 'development',
    'debug' => true,
];
```

---

## Mimari

```
kailyn/
├── bin/tulpar                          # Console entry point
├── bootstrap/app.php                   # App bootstrapping
├── public/
│   ├── index.php                       # HTTP entry point
│   ├── router.php                      # Static file router
│   └── js/kailyn.js                    # Reactive engine (client)
├── src/Kailyn/
│   ├── Foundation/Application.php      # App container
│   ├── Container/Container.php         # DI container
│   ├── Config/Config.php               # Config loader
│   ├── Cache/
│   │   ├── CacheDriver.php             # Cache interface
│   │   ├── CacheManager.php            # Cache manager
│   │   ├── FileCacheDriver.php         # File driver
│   │   ├── RedisCacheDriver.php        # Redis driver
│   │   └── DatabaseCacheDriver.php     # Database driver
│   ├── Http/
│   │   ├── Kernel.php                  # HTTP pipeline
│   │   ├── Request.php                 # Request wrapper
│   │   ├── Response.php                # Response builder
│   │   └── Router.php                  # Route dispatcher
│   ├── Console/
│   │   ├── Kernel.php                  # Console kernel
│   │   ├── Application.php             # Symfony app wrapper
│   │   ├── Command.php                 # Base command
│   │   ├── Signature.php               # Signature parser
│   │   └── Commands/                   # Built-in commands
│   ├── Template/
│   │   ├── Engine.php                  # Template renderer
│   │   └── Compiler.php                # Directive compiler
│   ├── Component/
│   │   ├── Component.php               # Base component
│   │   ├── ComponentManager.php        # Component lifecycle
│   │   └── Attributes/
│   │       ├── Reactive.php            # #[Reactive]
│   │       └── Computed.php            # #[Computed]
│   ├── Database/
│   │   ├── Connection.php              # PDO wrapper
│   │   ├── QueryBuilder.php            # Fluent query builder
│   │   ├── Model.php                   # ActiveRecord
│   │   ├── Migration.php               # Migration base class
│   │   ├── Migrator.php                # Migration runner
│   │   └── Schema.php                  # Schema builder
│   ├── Validation/Validator.php        # Validation
│   ├── Session/SessionManager.php      # Session management
│   └── helpers.php                     # Global helpers
├── app/
│   ├── Components/                     # Reactive components
│   ├── Controllers/                    # HTTP controllers
│   ├── Models/                         # Database models
│   └── Views/                          # Template files
├── config/
│   ├── app.php                         # App config
│   ├── cache.php                       # Cache config
│   └── database.php                    # Database config
├── database/migrations/                # Migration files
├── routes/web.php                      # Route definitions
└── storage/
    ├── cache/                          # File cache
    └── views/                          # Compiled template cache
```
