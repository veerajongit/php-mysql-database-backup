To backup existing database use the following script

```
include __DIR__ . "/SqlBackup.php";

$sql = new SqlBackup('localhost', 'rashmibansal', 'root', '123456', __DIR__);

if ($sql->run()) {
    echo 'Backup created successfully. File size is ' . $sql->fileSize;
}
```