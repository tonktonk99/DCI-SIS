<?php
function flash_set(string $type, string $message): void
{
    $_SESSION['_flash'][$type][] = $message;
}

function flash_get(?string $type = null): array
{
    if ($type === null) {
        $all = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);
        return $all;
    }
    $msgs = $_SESSION['_flash'][$type] ?? [];
    unset($_SESSION['_flash'][$type]);
    return $msgs;
}

function flash_has(?string $type = null): bool
{
    if ($type === null) {
        return !empty($_SESSION['_flash']);
    }
    return !empty($_SESSION['_flash'][$type]);
}

function flash_render(): void
{
    if (empty($_SESSION['_flash'])) {
        return;
    }
    $all = flash_get();
    $palettes = [
        'success' => ['#2d6a4f', '#d1fae5', '#064e3b'],
        'error'   => ['#a51c30', '#fff1f1', '#7a0f1f'],
        'warning' => ['#b08a38', '#fef9ed', '#7c5800'],
        'info'    => ['#1c3a6e', '#e8edf8', '#1c3a6e'],
    ];
    foreach ($all as $type => $messages) {
        [$border, $bg, $color] = $palettes[$type] ?? ['#8a7c5e', '#f7f3ea', '#4b3a2a'];
        foreach ($messages as $msg) {
            printf(
                '<div style="padding:12px 16px;border-left:4px solid %s;background:%s;color:%s;margin-bottom:8px;font-family:Tahoma,Arial,sans-serif;font-size:13px;line-height:1.5;">%s</div>',
                $border,
                $bg,
                $color,
                htmlspecialchars((string)$msg, ENT_QUOTES, 'UTF-8')
            );
        }
    }
}
