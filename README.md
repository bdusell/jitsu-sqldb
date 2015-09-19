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
