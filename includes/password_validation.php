<?php

function get_password_requirements_message(): string
{
    return "Password must contain at least:\n• 8 characters\n• One uppercase letter\n• One lowercase letter\n• One number\n• One special character";
}

function validate_password_strength(string $password): array
{
    $password = trim($password);
    $errors = [];

    if ($password === '') {
        $errors[] = 'Password is required.';
        return ['is_valid' => false, 'errors' => $errors];
    }

    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = 'Password must contain at least one uppercase letter.';
    }
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = 'Password must contain at least one lowercase letter.';
    }
    if (!preg_match('/\d/', $password)) {
        $errors[] = 'Password must contain at least one number.';
    }
    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        $errors[] = 'Password must contain at least one special character.';
    }

    return [
        'is_valid' => empty($errors),
        'errors' => $errors,
    ];
}
