<?php

declare(strict_types=1);

spl_autoload_register(function (string $className): void {
    
    // Konvertera namespace till filväg (PSR-4)
    $file = str_replace('\\', '/', $className) . '.php';
    
    // Kontrollera om filen finns och inkludera den
    if (file_exists($file)) {
        require_once $file;
    }
});