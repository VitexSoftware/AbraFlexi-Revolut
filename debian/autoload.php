<?php
// Debian autoloader for abraflexi-revolut
// Load dependency autoloaders
require_once '/usr/share/php/AbraFlexi/autoload.php';
require_once '/usr/share/php/Ease/autoload.php';

// PSR-4 autoloader for application classes
spl_autoload_register(function (string $class): void {
    $prefixes = [
        'Vitexsoftware\\AbraflexiRevolut\\' => '/usr/lib/abraflexi-revolut/src/',
    ];
    foreach ($prefixes as $prefix => $baseDir) {
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            continue;
        }
        $relativeClass = substr($class, $len);
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
        if (file_exists($file)) {
            require $file;
            return;
        }
    }
});
