<?php

function input_int(array $source, string $key, ?int $default = null, ?int $min = null, ?int $max = null): ?int
{
    if (!array_key_exists($key, $source) || $source[$key] === '') {
        return $default;
    }
    $v = filter_var($source[$key], FILTER_VALIDATE_INT);
    if ($v === false) {
        return $default;
    }
    $v = (int)$v;
    if ($min !== null && $v < $min) {
        return $default;
    }
    if ($max !== null && $v > $max) {
        return $default;
    }
    return $v;
}

function input_string(array $source, string $key, string $default = '', int $maxLen = 255): string
{
    $v = trim((string)($source[$key] ?? ''));
    if ($maxLen > 0 && mb_strlen($v) > $maxLen) {
        $v = mb_substr($v, 0, $maxLen);
    }
    return $v !== '' ? $v : $default;
}

function input_bool(array $source, string $key, bool $default = false): bool
{
    return isset($source[$key]) ? (bool)$source[$key] : $default;
}

function input_date(array $source, string $key, ?string $default = null): ?string
{
    $v = trim((string)($source[$key] ?? ''));
    if ($v === '') {
        return $default;
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) {
        return $default;
    }
    [$y, $m, $d] = explode('-', $v);
    if (!checkdate((int)$m, (int)$d, (int)$y)) {
        return $default;
    }
    return $v;
}

function input_enum(array $source, string $key, array $allowed, ?string $default = null): ?string
{
    $v = (string)($source[$key] ?? '');
    return in_array($v, $allowed, true) ? $v : $default;
}

function require_int(array $source, string $key, int $min = 1): int
{
    $v = input_int($source, $key, null, $min);
    if ($v === null) {
        abort_400('Invalid or missing parameter: ' . $key);
    }
    return $v;
}

function require_enum(array $source, string $key, array $allowed): string
{
    $v = input_enum($source, $key, $allowed);
    if ($v === null) {
        abort_400('Invalid value for parameter: ' . $key);
    }
    return $v;
}

function validate_page_params(array $source = [], int $defaultPerPage = 50, int $maxPerPage = 200): array
{
    $page    = max(1, input_int($source, 'page', 1, 1) ?? 1);
    $perPage = min($maxPerPage, max(1, input_int($source, 'per_page', $defaultPerPage, 1) ?? $defaultPerPage));
    return ['page' => $page, 'per_page' => $perPage];
}
