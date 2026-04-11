# Sybase ASE based Eloquent module extension for Laravel 

[![Packagist Version](https://img.shields.io/packagist/v/uepg/laravel-sybase.svg)](https://packagist.org/packages/uepg/laravel-sybase)
[![PHP from Packagist](https://img.shields.io/packagist/php-v/uepg/laravel-sybase.svg)](https://packagist.org/packages/uepg/laravel-sybase)
[![Packagist](https://img.shields.io/packagist/dt/uepg/laravel-sybase.svg)](https://packagist.org/packages/uepg/laravel-sybase/stats)
[![GitHub contributors](https://img.shields.io/github/contributors-anon/uepg/laravel-sybase.svg)](https://github.com/uepg/laravel-sybase/graphs/contributors)
[![GitHub](https://img.shields.io/github/license/uepg/laravel-sybase.svg)](https://github.com/uepg/laravel-sybase/blob/master/LICENSE)

* Enables use of multiple kinds of fields.
* Use default eloquent: works with odbc and dblib!

## Install

Add the following in the require section of your **composer.json**:

### Laravel 7 <=
"uepg/laravel-sybase": "~2"

### Laravel 8 >= and <= 10
"uepg/laravel-sybase": "~4"

### Laravel 11 >=
```json
"uepg/laravel-sybase": "~5"
```

Update the package dependencies executing:

```shell
composer update
```

Add the following entry to your providers array in **config/app.php** file, optional in Laravel 5.5 or above:

```php
Uepg\LaravelSybase\SybaseServiceProvider::class,
```

Add the following entry to your aliases array in **config/app.php** file, optional in Laravel 5.5 or above:

```php
'UepgBlueprint' => Uepg\LaravelSybase\Database\Schema\Blueprint::class,
```

Update your **config/database.php's** default driver with the settings for the **sybase** or your custom odbc. See the following example:

```php
<?php

...

return [
    ...

    
    'connections' => [
        ...

        'sybase' => [
            'driver' => 'sybasease',
            'host' => env('DB_HOST', 'sybase.myserver.com'),
            'port' => env('DB_PORT', '5000'),
            'database' => env('DB_DATABASE', 'mydatabase'),
            'username' => env('DB_USERNAME', 'user'),
            'password' => env('DB_PASSWORD', 'password'),
            'charset' => 'utf8',
            'prefix' => '',
            'cache_tables' => true,
            'cache_time' => 3600,
             'application_encoding' => false,
             'application_charset' => '',
        ],

        ...
    ],

    ...
]
```

Update your **.env** with the settings for the **sybase** or your custom odbc. See the following example:

```text
...

DB_CONNECTION=sybase
DB_HOST=sybase.myserver.com
DB_PORT=5000
DB_DATABASE=mydatabase
DB_USERNAME=user
DB_PASSWORD=password

...
```

## Configuration of freetds driver

In Linux systems the driver version must be set in **freetds.conf** file to the right use of charset pages.

The file is usualy found in **/etc/freetds/freetds.conf**. Set the configuration at global section as the following example:

```text
[global]
    # TDS protocol version
    tds version = 5.0
```

## Configuring the charset conversion
This package offers to method to charset conversion, it can be converted in application layer or in database layer, we offered both methods because it can be useful for debugging, to config the application layer conversion you need to set up the following entries on the `database.php` file. You can view an example of the application encoding setup below:

```
To use the database layer conversion add the property charset to connection configuration on the sybase configuration array

```charset
     'charset' => 'utf8',
     'application_encoding' => false,
     'application_charset' => '',
```



## Stored procedures (`rpc()`)

Use `rpc()` on the Sybase connection to run stored procedures that return **exactly one final result set** (multiple trailing `SELECT` statements are not supported by this helper). Typical contracts expose `cd_retorno` (`0` = success, `> 0` = application error) and `msg_retorno`.

```php
use Illuminate\Support\Facades\DB;

$rows = DB::connection('sybase')
    ->rpc('dbo.sp_exemplo')
    ->with(['cd_pessoa_p' => $id]) // keys may include or omit a leading `@`
    ->get(); // same row shape as Connection::select()

// Positional arguments (values only), in the same order as the procedure parameters:
$rows = DB::connection('sybase')
    ->rpc('dbo.sp_exemplo')
    ->with([$id, $nome])
    ->get();

$first = DB::connection('sybase')
    ->rpc('dbo.sp_exemplo')
    ->with(['@cd_pessoa_p' => $id])
    ->first();

DB::connection('sybase')
    ->rpc('dbo.sp_exemplo')
    ->with($dto) // Illuminate\Contracts\Support\Arrayable or associative array
    ->assertOk() // throws ProcedureExecutionException if cd_retorno != 0
    ->first();
```

Optional read path and fetch mode follow `select()`:

```php
DB::connection('sybase')
    ->rpc('dbo.sp_exemplo')
    ->with(['p' => 1])
    ->useReadPdo(false)
    ->fetchUsing([\PDO::FETCH_ASSOC])
    ->get();
```

`toStatement()` returns the built SQL (`EXEC ... @name = ?, ...` or `EXEC ... ?, ?, ...`) and bindings (for logging or tests) without executing the procedure. A new `with()` that switches between a list and an associative array replaces the previous argument style for that call chain.

This API does **not** cover `OUTPUT` parameters or procedures that return multiple result sets unless you handle them with a raw `select()` yourself.

## Configuring the cache
As the library consults table information whenever it receives a request, caching can be used to avoid excessive queries

To use the cache, add the property `cache_tables` to the database.php connection configuration, you can customize the time of the cache with the property `cache_time` in the same configuration
```dotenv
        'cache_tables' => true,
        'cache_time' => 3600
```

## Setting to use numeric data type

In the migration file you must replace `use Illuminate\Database\Schema\Blueprint;` with `use Uepg\LaravelSybase\Database\Schema\Blueprint;`. See the following example:

```php
<?php

use Illuminate\Support\Facades\Schema;
// use Illuminate\Database\Schema\Blueprint;
use Uepg\LaravelSybase\Database\Schema\Blueprint; // or "use UepgBlueprint as Blueprint"
use Illuminate\Database\Migrations\Migration;

class CreateTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('table_name', function (Blueprint $table) {
            $table->numeric('column_name', length, autoIncrement);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('table_name');
    }
}
```
