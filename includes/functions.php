<?php
declare(strict_types=1);

function e(?string $value): string {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function isAdmin(): bool {
    return isset($_SESSION['role_name']) && $_SESSION['role_name'] === 'Super Admin';
}

function requireAdmin(): void {
    if (!isAdmin()) {
        header('Location: ../index.php');
        exit;
    }
}

function flash(string $key, ?string $message = null): ?string {
    if ($message !== null) {
        $_SESSION['_flash'][$key] = $message;
        return null;
    }
    $value = $_SESSION['_flash'][$key] ?? null;
    unset($_SESSION['_flash'][$key]);
    return $value;
}
