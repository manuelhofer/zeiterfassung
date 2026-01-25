<?php
declare(strict_types=1);
/**
 * Login-Formular
 *
 * Erwartet:
 * - $fehlermeldung (string|null)
 */
?><!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>Login – Zeiterfassung</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body {
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            margin: 0;
            padding: 0;
            background-color: #eceff1;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }
        .login-box {
            background-color: #ffffff;
            padding: 1.5rem 1.75rem;
            border-radius: 6px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.15);
            max-width: 320px;
            width: 100%;
        }
        .login-box h1 {
            font-size: 1.2rem;
            margin-top: 0;
            margin-bottom: 0.75rem;
            text-align: center;
            color: #263238;
        }
        .login-box label {
            display: block;
            margin-top: 0.75rem;
            margin-bottom: 0.25rem;
            font-size: 0.9rem;
            color: #37474f;
        }
        .login-box input[type="text"],
        .login-box input[type="password"] {
            width: 100%;
            box-sizing: border-box;
            padding: 0.4rem 0.5rem;
            font-size: 0.95rem;
            border: 1px solid #b0bec5;
            border-radius: 4px;
        }
        .login-box button {
            margin-top: 1rem;
            width: 100%;
            padding: 0.5rem 0.75rem;
            font-size: 0.95rem;
            border: none;
            border-radius: 4px;
            background-color: #263238;
            color: #ffffff;
            cursor: pointer;
        }
        .login-box button:hover {
            background-color: #37474f;
        }
        .fehlermeldung {
            margin-top: 0.75rem;
            padding: 0.5rem 0.6rem;
            border-radius: 4px;
            background-color: #ffebee;
            color: #c62828;
            font-size: 0.85rem;
        }
    </style>
</head>
<body>
    <div class="login-box">
        <h1>Zeiterfassung Login</h1>

        <?php if (!empty($fehlermeldung)): ?>
            <div class="fehlermeldung">
                <?php echo htmlspecialchars((string)$fehlermeldung, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <form method="post" action="?seite=login">
            <label for="benutzername">Benutzername oder E-Mail</label>
            <input type="text" name="benutzername" id="benutzername" autocomplete="username" required>

            <label for="passwort">Passwort</label>
            <input type="password" name="passwort" id="passwort" autocomplete="current-password" required>

            <button type="submit">Anmelden</button>
        </form>
    </div>
</body>
</html>
