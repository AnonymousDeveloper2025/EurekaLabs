} else {
    echo '<pre>';
    echo 'REQUEST_METHOD: ' . ($_SERVER['REQUEST_METHOD'] ?? 'não definido') . PHP_EOL;
    echo '_FILES:' . PHP_EOL;
    print_r($_FILES);
    echo '</pre>';
}
