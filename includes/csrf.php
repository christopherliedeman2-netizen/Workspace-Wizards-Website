<?php

// Generate or retrieve the session's CSRF token.
function csrf_token(): string
{
    if (empty($_SESSION["csrf_token"])) {
        $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
    }

    return $_SESSION["csrf_token"];
}

// Add this hidden field to forms that change data.
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' .
        htmlspecialchars(csrf_token(), ENT_QUOTES, "UTF-8") .
        '">';
}

// Validate a submitted CSRF token.
function csrf_validate(): bool
{
    return isset($_POST["csrf_token"], $_SESSION["csrf_token"])
        && is_string($_POST["csrf_token"])
        && hash_equals(
            $_SESSION["csrf_token"],
            $_POST["csrf_token"]
        );
}