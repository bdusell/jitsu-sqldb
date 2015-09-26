Jitsu SQL Database Wrapper
--------------------------

This package defines a convenient object-oriented interface to SQL databases
and SQL statements, built on top of PHP's PDO library. While PDO already
provides a unified, object-oriented API supporting multiple SQL drivers, this
library offers an API which is easier to use, adding some extra helper methods
and providing better error handling. It makes parameter binding less painful in
particular.

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

This package also defines a database plugin for the `jitsu/app` package.
Including the trait `\Jitsu\App\Databases` in your application class adds a
`database` method which can be used to configure a database connection for
your application to use. The request handler which this `database` method
registers adds a database connection object to the request `$data` object. This
database object comes with a twist &mdash; it is lazily loaded, meaning that
the database connection will not be established until one of the object's
methods is used. This makes it easy to configure a database connection for
multiple request handlers in your application to use, but to avoid making that
connection when your application routes to a handler which does not need the
database at all (such as a page-not-found handler).

The `database` method accepts two arguments: the name of the property on the
request `$data` object which the connection object will be assigned to, and the
configuration options, which are defined in an array. For the second argument,
the `database` method will accept either an array or the name of a property on
the `$data->config` object. Be default, this is the same as the first argument.
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
