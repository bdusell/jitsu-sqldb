jitsu/sqldb
-----------

This package defines a convenient object-oriented interface to SQL databases
and SQL statements, built on top of PHP's PDO library. While PDO already
provides a unified, object-oriented API supporting multiple SQL drivers, this
library offers an API which is easier to use, adding some extra helper methods
and providing better error handling. It makes parameter binding less painful in
particular.

This package is part of [Jitsu](https://github.com/bdusell/jitsu).

## Installation

Install this package with [Composer](https://getcomposer.org/):

```sh
composer require jitsu/sqldb
```

## Namespace

All classes are defined under the namespace `Jitsu`. The database-related
classes are defined under `Jitsu\Sql`.

## Usage

Here's an example:

```php
<?php
use Jitsu\Sql\Database;
use Jitsu\Sql\MysqlDatabase;

$search_term = $_GET['query'];

// Connect to the database
$db = new MysqlDatabase('localhost', 'my_database',
                        'my_user', 'my_password');
// Run a query with named parameters
$stmt = $db->query(<<<SQL
  select `name`, `description`
  from `packages`
  where `description` like :pattern
  order by `description` = :term desc
SQL
  , [
    'pattern' => '%' . Database::escapeLike($search_term) . '%',
    'term' => $search_term
  ]);

// Iterate over results
foreach($stmt as $row) {
  echo $row->name, ': ', $row->description, "\n";
}

$user_id = $_SESSION['user_id'];

// Get a single record using a positional parameter
$user = $db->row(<<<SQL
  select `first_name`
  from `users`
  where `id` = ?
SQL
  , $user_id);
echo "Welcome back, ", $user->first_name, "\n";

// Get the first column of the first row
$exists = $db->evaluate(<<<SQL
  select exists(
    select 1
    from `bookmarks`
    join `packages` on `bookmarks`.`package_id` = `packages`.`id`
    where `bookmarks`.`user_id` = ?
    and `packages`.`name` = ?
  )
SQL
  , $user_id, $search_term);
if($exists) {
  echo "You have already bookmarked this package.\n"
} else {
  echo "You have not bookmarked this package.\n";
}
```

This package also defines a database plugin for the
[jitsu/app](https://github.com/bdusell/jitsu-app) package.
Including the trait `\Jitsu\App\Databases` in your application class adds a
`database` method which can be used to configure a database connection for
your application to use. The request handler which this `database` method
registers adds a database connection object to the request `$data` object. This
database object comes with a twist &ndash; it is lazily loaded, meaning that
the database connection will not be established until one of the object's
methods is used. This makes it easy to configure a database connection for
multiple request handlers in your application to use, but to avoid making that
connection when your application routes to a handler which does not need the
database at all (such as a page-not-found handler).

The `database` method accepts two arguments: the name of the property on the
request `$data` object which the connection object will be assigned to, and the
configuration options, which are defined in an array. For the second argument,
the `database` method will accept either an array or the name of a property on
the `$data->config` object. By default, this is the same as the first argument.
The first argument also defaults to `'database'`.

A quick example:

```php
<?php
class MyApp extends \Jitsu\App\Application {
  use \Jitsu\App\Databases;
  public function initialize() {
    $this->database('database', [
      'driver'     => 'mysql',
      'host'       => 'localhost',
      'database'   => 'my_database',
      'user'       => 'my_user',
      'password'   => 'shhhhhhh',
      'persistent' => true
    ]);
    $this->get('count-users', function($data) {
      $count = $data->database->evaluate('select count(*) from `users`');
      echo "There are $count users. Honestly, that's $count more than I expected.\n";
    });
    $this->notFound(function($data) {
      $data->response->setStatusCode(404, 'Not Found');
      echo "Nothing to see here. No database connection made.\n";
    });
  }
}
```

## API

### class Jitsu\\Sql\\Database

An object-oriented interface to a SQL database.

This is essentially a useful wrapper around the PDO library.

#### new Database($driver\_str, $username = null, $password = null, $options = array())

Connect to a database upon construction.

|   | Type | Description |
|---|------|-------------|
| **`$driver_str`** | `string` | A PDO driver string. |
| **`$username`** | `string|null` | An optional username. |
| **`$password`** | `string|null` | An optional password. |
| **`$options`** | `array` | An optional array of PDO options. |
| throws | `\Jitsu\Sql\DatabaseException` |  |

#### $database->query($query, $args,...)

Execute a SQL query.

Executes a one-shot query and returns the resulting rows in an
iterable `Statement` object. The remaining parameters may be used to
pass arguments to the query. If there is only a single array passed
as an additional argument, its contents are used as the parameters.

For example,

    $stmt = $db->query($sql_code);
    $stmt = $db->query($sql_code, $arg1, $arg2, ...);
    $stmt = $db->query($sql_code, $arg_array);
    foreach($stmt as $row) { $row->column_name ... }

|   | Type | Description |
|---|------|-------------|
| **`$query`** | `string` | The SQL query. |
| **`$args,...`** | `mixed` | Arguments to be interpolated into the query. If a single array is passed, its contents are used as the arguments. |
| returns | `\Jitsu\Sql\Statement` |  |
| throws | `\Jitsu\Sql\DatabaseException` |  |

#### $database->queryWith($query, $args)

Same as `query`, but arguments are always passed in a single `$args`
array.

|   | Type |
|---|------|
| **`$query`** | `string` |
| **`$args`** | `array` |
| returns | `\Jitsu\Sql\Statement` |
| throws | `\Jitsu\Sql\DatabaseException` |

#### $database->row($query, $args,...)

Return the first row of a query and ignore the rest.

|   | Type |
|---|------|
| **`$query`** | `string` |
| **`$args,...`** | `mixed` |
| returns | `\Jitsu\Sql\Statement` |
| throws | `\Jitsu\Sql\DatabaseException` |

#### $database->rowWith($query, $args)

Like `row`, but arguments are always passed in an array.

|   | Type |
|---|------|
| **`$query`** | `string` |
| **`$args`** | `array` |
| returns | `\Jitsu\Sql\Statement` |
| throws | `\Jitsu\Sql\DatabaseException` |

#### $database->evaluate($query, $args,...)

Return the first column of the first row and ignore everything
else.

|   | Type |
|---|------|
| **`$query`** | `string` |
| **`$args,...`** | `mixed` |
| returns | `\Jitsu\Sql\Statement` |
| throws | `\Jitsu\Sql\DatabaseException` |

#### $database->evaluateWith($query, $args)

Like `evaluate`, but arguments are always passed in an array.

|   | Type |
|---|------|
| **`$query`** | `string` |
| **`$args`** | `array` |
| returns | `\Jitsu\Sql\Statement` |
| throws | `\Jitsu\Sql\DatabaseException` |

#### $database->execute()

Execute a SQL statement.

If called with arguments, returns a `Statement`. Note that the
number of affected rows is available via
`Statement->affectedRows()`. If called with no arguments,
returns a `StatementStub` object instead, which provides only the
`affectedRows()` method.

|   | Type |
|---|------|
| **`$query`** | `string` |
| **`$arg,...`** | `mixed` |
| returns | `\Jitsu\Sql\QueryResultInterface` |
| throws | `\Jitsu\Sql\DatabaseException` |

#### $database->executeWith($statement, $args)

Like `execute`, but arguments are always passed in an array.

|   | Type |
|---|------|
| **`$query`** | `string` |
| **`$args`** | `array` |
| returns | `\Jitsu\Sql\QueryResultInterface` |
| throws | `\Jitsu\Sql\DatabaseException` |

#### $database->prepare($statement)

Prepare a SQL statement and return it as a `Statement`.

|   | Type |
|---|------|
| **`$statement`** | `string` |
| returns | `\Jitsu\Sql\Statement` |
| throws | `\Jitsu\Sql\DatabaseException` |

#### $database->quote($s)

Escape and quote a string value for interpolation in a SQL query.

Note that the result *includes* quotes added around the string.

|   | Type |
|---|------|
| **`$s`** | `string` |
| returns | `string` |
| throws | `\Jitsu\Sql\DatabaseException` |

#### Database::escapeLike($s, $esc = '\\\\')

Escape characters in a string that have special meaning in SQL
"like" patterns. Note that this should be coupled with an `ESCAPE`
clause in the SQL; for example,

    "column" LIKE '%foo\%bar%' ESCAPE '\'

A `\` is the default escape character.

|   | Type |
|---|------|
| **`$s`** | `string` |
| **`$esc`** | `string` |
| returns | `string` |

#### $database->lastInsertId()

Get the ID of the last inserted record.

*Note that the result is always a string.*

|   | Type | Description |
|---|------|-------------|
| returns | `string` | A string, which you will most likely want to cast to an integer. |
| throws | `\Jitsu\Sql\DatabaseException` |  |

#### $database->begin()

Begin a transaction.

Note that uncommitted transactions are automatically rolled back
when the script terminates.

|   | Type |
|---|------|
| throws | `\Jitsu\Sql\DatabaseException` |

#### $database->inTransaction()

Determine whether a transaction is active.

|   | Type |
|---|------|
| returns | `bool` |

#### $database->rollback()

Roll back the current transaction.

|   | Type |
|---|------|
| throws | `\Jitsu\Sql\DatabaseException` |

#### $database->commit()

Commit the current transaction.

|   | Type |
|---|------|
| throws | `\Jitsu\Sql\DatabaseException` |

#### $database->transaction($callback)

Run a callback safely in a transaction.

If the callback throws an exception, the transaction will be rolled
back, and the exception will be re-thrown.

|   | Type |
|---|------|
| **`$callback`** | `callable` |
| throws | `\Exception` |

#### $database->attribute($name)

Get a database connection attribute.

The name passed should be a string (case-insensitive) and
correspond to a PDO constant with the `PDO::ATTR_` prefix
dropped.

Possible names are:

* `autocommit`
* `case`
* `client_version`
* `connection_status`
* `driver_name`
* `errmode`
* `oracle_nulls`
* `persistent`
* `prefetch`
* `server_info`
* `server_version`
* `timeout`

|   | Type |
|---|------|
| **`$name`** | `string` |
| returns | `mixed` |
| throws | `\Jitsu\Sql\DatabaseException` |

#### $database->setAttribute($name, $value)

Set a database connection attribute.

Uses the same attribute name convention as `attribute`. The value
should be a string (case-insensitive) corresponding to a PDO
constant with the `PDO::ATTR_` prefix dropped.

|   | Type |
|---|------|
| **`$name`** | `string` |
| **`$value`** | `mixed` |
| throws | `\Jitsu\Sql\DatabaseException` |

#### $database->attributes()

Generate a mapping of all attribute names and values.

|   | Type |
|---|------|
| returns | `array` |
| throws | `\Jitsu\Sql\DatabaseException` |

#### Database::drivers()

Get a list of the available database drivers.

|   | Type |
|---|------|
| returns | `string[]` |

#### $database->connection()

Get the underlying PDO connection object.

|   | Type |
|---|------|
| returns | `\PDO` |

#### $database->setFetchMode($mode)

Set the fetch mode.

The fetch mode determines the form in which rows are fetched. Use
the `PDO::FETCH_` constants directly. The default, `PDO::FETCH_OBJ`,
causes rows to be returned as `stdClass` objects with property
names corresponding to column names.

|   | Type | Description |
|---|------|-------------|
| **`$mode`** | `mixed` | A `PDO::FETCH_` constant. |

#### $database->fetchMode($mode)

Get the fetch mode.

|   | Type |
|---|------|
| returns | `mixed` |

### class Jitsu\\Sql\\MysqlDatabase

Extends `Database`.

Specialization of `Database` for MySQL.

#### new MysqlDatabase($host, $database, $username, $password, $charset = 'utf8mb4', $options = null)

|   | Type | Description |
|---|------|-------------|
| **`$host`** | `string` | Host name. |
| **`$database`** | `string` | Database name. |
| **`$username`** | `string` | Username. |
| **`$password`** | `string` | Password. |
| **`$charset`** | `string` | Character set used by the connection. The default is `utf8mb4`, which supports all Unicode characters encoded in UTF-8. |
| **`$options`** | `array|null` | Extra PDO options. |

### class Jitsu\\Sql\\SqliteDatabase

Extends `Database`.

Specialization of `Database` for SQLite.

#### new SqliteDatabase($filename, $options = null)

Connect to a SQLite database.

Note that this always enables foreign key constraints. If for some
strange reason you actually want to turn this off, you can run

    $db = new SqliteDatabase('foo.db');
    $db->execute('pragma foreign_keys = off');

|   | Type | Description |
|---|------|-------------|
| **`$filename`** | `string` | Name of the database file. |
| **`$options`** | `array|null` | Extra PDO options. |

### class Jitsu\\Sql\\Statement

An object-oriented interface to a prepared or executed SQL statement.

This is a convenient wrapper around the PDO statement class.

#### new Statement($stmt, $mode = \\PDO::FETCH\_OBJ)

Construct a SQL statement object.

Optionally specify a fetch mode, which determines the form in which
rows are fetched. Use the `PDO::FETCH_` constants directly. The
default is `PDO::FETCH_OBJ`, which causes rows to be returned as
`stdClass` objects with property names corresponding to column
names.

|   | Type |
|---|------|
| **`$stmt`** | `\PDOStatement` |
| **`$mode`** | `mixed` |

#### $statement->bindOutput($col, &$var, $type = null, $inout = false)

Bind a result column to a variable.

The column can be 1-indexed or referenced by name.

    $stmt = $db->prepare('select id, name from users');
    $stmt->bind_output('name', $name);
    foreach($stmt as $row) echo $name, "\n";

A type may optionally be specified. The following values may be
passed as strings:

* `bool`
* `null`
* `int`
* `str`
* `lob` (large object)

The `$inout` parameter specifies whether the column is an
`INOUT` parameter for a stored procedure.

|   | Type |
|---|------|
| **`$col`** | `int|string` |
| **`$var`** | `mixed` |
| **`$type`** | `string|null` |
| **`$inout`** | `bool` |

#### $statement->bindInput($param, &$var, $type = null, $inout = false)

Bind an input parameter of a prepared statement to a variable.

The parameter can be 1-indexed or referenced by name (include the
colon).

Example 1:

    $stmt = $db->prepare('select id, name from users where phone = ?');
    $stmt->bind_input(1, $phone);
    $phone = '5551234567';
    $stmt->execute();

Example 2:

    $stmt = $db->prepare('select id, name from users where phone = :phone');
    $stmt->bind_input(':phone', $phone);

|   | Type |
|---|------|
| **`$param`** | `int|string` |
| **`$var`** | `mixed` |
| **`$type`** | `string|null` |
| **`$inout`** | `bool` |

#### $statement->assignInput($param, $value, $type = null, $inout = false)

Assign a value to an input parameter of a prepared statement.

If `$type` is null, the appropriate type is used based on the PHP
type of the value.

|   | Type |
|---|------|
| **`$param`** | `int|string` |
| **`$value`** | `mixed` |
| **`$type`** | `string|null` |
| **`$type`** | `bool` |

#### $statement->assign($values,...)

Assign an array of values to the inputs of a prepared statement.

For named parameters, pass an array mapping names (no colons) to
values. For positional parameters, pass a sequential array
(0-indexed). A positional parameter array may be passed as a single
array argument or as a variadic argument list.

    $stmt = $db->prepare('select * from users where first = ? and last = ?');
    $stmt->assign('John', 'Doe');

|   | Type |
|---|------|
| **`$values,...`** | `mixed` |

#### $statement->assignWith($values)

Like `assign`, but arguments are always passed in an array.

|   | Type |
|---|------|
| **`$values`** | `array` |

#### $statement->execute($values = null)

Execute this statement, optionally providing a 0-indexed array of
values.

The values array may be convenient in simple cases, but it only
works for positional parameters and casts all values to the SQL
string type (`'str'`). A more flexible alternative is to call
`assign` beforehand.

|   | Type |
|---|------|
| **`$values`** | `array|null` |

#### $statement->evaluate($values = null)

Equivalent to running `$this->execute()` and returning
`$this->value()`.

|   | Type |
|---|------|
| **`$values`** | `array|null` |
| returns | `mixed` |

#### $statement->close()

Ignore the rest of the records returned by this statement so as to
make it executable again.

#### $statement->first()

Return the first row and ignore the rest.

If there are no records returned, return `null`.

Note that this won't work with some fetch modes.

|   | Type |
|---|------|
| returns | `mixed` |

#### $statement->value()

Return the first cell of the first row and ignore anything else.

If no records are returned, return `null`.

|   | Type |
|---|------|
| returns | `mixed` |

#### $statement->columnCount()

Return the number of columns in the result set, or 0 if there is no
result set.

|   | Type |
|---|------|
| returns | `int` |

#### $statement->affectedRows()

Return the number of rows affected by the last execution of this
statement.

|   | Type |
|---|------|
| returns | `int` |

#### $statement->toArray()

Return all of the rows copied into an array.

|   | Type |
|---|------|
| returns | `mixed[]` |

#### $statement->map($callback)

Return all of the rows passed through a function and copied into an
array.

The columns of each row are passed as positional parameters to the
function.

|   | Type |
|---|------|
| **`$callback`** | `callable` |
| returns | `mixed[]` |

#### $statement->nextRowset()

Advance to the next set of rows returned by the query, which is
supported by some stored procedures.

#### $statement->current()

#### $statement->key()

#### $statement->next()

#### $statement->rewind()

#### $statement->valid()

#### $statement->debug()

Print debugging information to *stdout*.

|   | Type |
|---|------|
| returns | `$this` |

### class Jitsu\\Sql\\StatementStub

Implements `QueryResultInterface`.

A query result consisting only of the number of affected rows.

#### new StatementStub($affected\_rows)

#### $statement\_stub->affectedRows()

### interface Jitsu\\Sql\\QueryResultInterface

#### $query\_result\_interface->affectedRows()

The number of rows affected by the SQL statement.

|   | Type |
|---|------|
| returns | `int` |

### class Jitsu\\Sql\\DatabaseException

An exception class for database-related errors.

#### new DatabaseException($msg, $errstr, $code = null, $state = null, $sql = null)

Construct a database exception object.

|   | Type | Description |
|---|------|-------------|
| **`$msg`** | `string` | A descriptive error message. |
| **`$errstr`** | `string` | The error string returned by the database driver/library. |
| **`$code`** | `int|null` | Optional error code. |
| **`$state`** | `int|null` | Optional SQL state after the error. |
| **`$sql`** | `string|null` | Optional SQL code which caused the error. |

#### $database\_exception->getSqlErrorCode()

Get the SQL engine's error code.

|   | Type |
|---|------|
| returns | `int|null` |

#### $database\_exception->getSqlState()

Get the SQL state abbreviation.

|   | Type |
|---|------|
| returns | `string|null` |

#### $database\_exception->getErrorString()

Get the error string reported by the database driver.

|   | Type |
|---|------|
| returns | `string|null` |

#### $database\_exception->getSql()

Get the SQL code which caused the error.

|   | Type |
|---|------|
| returns | `string|null` |

#### $database\_exception->\_\_toString()

Return a suitable string representation of the database error.

|   | Type |
|---|------|
| returns | `string` |

### trait Jitsu\\App\\Databases

Mixin for `Jitsu\App\Application` which adds configurable database
connection functionality.

#### $databases->database($data\_prop = 'database', $config = null)

The configuration settings should be defined in an array. The
following keys are recognized:

* `driver`: The name of the SQL software used by the database.
  Either `'sqlite'` or `'mysql'`. Mandatory.
* `persistent`: Whether to use persistent database connections.
  Default is `false`.
* `host`: (MySQL) The name of the host hosting the database.
* `database`: (MySQL) The name of the MySQL database.
* `user`: (MySQL) The name of the MySQL user used to log in.
* `password`: (MySQL) The password used to log in.
* `charset`: (MySQL) The character set used by the connection.
  Default is `'utf8mb4'`, which supports all Unicode characters
  encoded in UTF-8.
* `file`: (SQLite) The name of the database file.

|   | Type | Description |
|---|------|-------------|
| **`$data_prop`** | `string` | Name of the property on `$data` where the database connection object will be assigned. By default, it is assigned to `$data->database`. |
| **`$config`** | `array|string|null` | Configuration settings for the database connection. Either an array or the name of a property on `$data->config`. By default, this is set to the same string as `$data_prop`. |

