<?php
if (!function_exists('__')) {
    function currentLang(): string
    {
        return $_SESSION['lang'] ?? 'th';
    }

    function loadLang(): array
    {
        static $strings = null;
        if ($strings !== null) return $strings;

        $lang = currentLang();
        $file = __DIR__ . '/../config/lang/' . $lang . '.php';
        if (!file_exists($file)) {
            $file = __DIR__ . '/../config/lang/th.php';
        }
        $strings = require $file;
        return $strings;
    }

    function __(string $key, array $replace = []): string
    {
        $strings = loadLang();
        $text = $strings[$key] ?? $key;

        foreach ($replace as $k => $v) {
            $text = str_replace(':' . $k, $v, $text);
        }

        return $text;
    }
}
