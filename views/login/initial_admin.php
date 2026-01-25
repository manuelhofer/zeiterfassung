<?php
declare(strict_types=1);
/**
 * Erstinstallations-Formular zum Anlegen des ersten Admin-Benutzers.
 *
 * Erwartet:
 * - $fehlermeldung (string|null)
 */
?><!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>Erstinstallation – Zeiterfassung</title>
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
            max-width: 420px;
            width: 100%;
        }
        h1 {
            font-size: 1.3rem;
            margin-top: 0;
            margin-bottom: 0.75rem;
            text-align: center;
        }
        p.subline {
            font-size: 0.9rem;
            color: #555;
            margin-top: 0;
            margin-bottom: 1rem;
            text-align: center;
        }
        .form-group {
            margin-bottom: 0.65rem;
        }
        label {
            display: block;
            font-size: 0.85rem;
            margin-bottom: 0.25rem;
        }
        input[type="text"],
        input[type="email"],
        input[type="password"] {
            width: 100%;
            padding: 0.4rem 0.45rem;
            font-size: 0.95rem;
            border-radius: 4px;
            border: 1px solid #b0bec5;
            box-sizing: border-box;
        }
        .btn-primary {
            width: 100%;
            padding: 0.5rem 0.6rem;
            font-size: 0.95rem;
            border-radius: 4px;
            border: none;
            background-color: #37474f;
            color: #ffffff;
            cursor: pointer;
            margin-top: 0.5rem;
        }
        .btn-primary:hover {
            background-color: #263238;
        }
        .error {
            background-color: #ffebee;
            color: #c62828;
            border: 1px solid #ef9a9a;
            padding: 0.5rem 0.6rem;
            border-radius: 4px;
            font-size: 0.85rem;
            margin-bottom: 0.8rem;
        }
        .hint {
            font-size: 0.8rem;
            color: #666;
            margin-top: 0.3rem;
        }
    </style>
</head>
<body>
<div class="login-box">
    <h1>Erstinstallation</h1>
    <p class="subline">Bitte legen Sie den ersten Admin-Benutzer für die Zeiterfassung an.</p>

    <?php if (!empty($fehlermeldung)): ?>
        <div class="error">
            <?php echo htmlspecialchars($fehlermeldung, ENT_QUOTES, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <form method="post" action="?seite=login">
        <input type="hidden" name="aktion" value="initial_admin">

        <div class="form-group">
            <label for="vorname">Vorname *</label>
            <input type="text" name="vorname" id="vorname" required>
        </div>

        <div class="form-group">
            <label for="nachname">Nachname *</label>
            <input type="text" name="nachname" id="nachname" required>
        </div>

        <div class="form-group">
            <label for="benutzername">Benutzername *</label>
            <input type="text" name="benutzername" id="benutzername" required>
            <div class="hint">Diesen Benutzernamen verwenden Sie später zum Login.</div>
        </div>

        <div class="form-group">
            <label for="email">E-Mail (optional)</label>
            <input type="email" name="email" id="email">
        </div>

        <div class="form-group">
            <label for="passwort">Passwort *</label>
            <input type="password" name="passwort" id="passwort" required>
        </div>

        <div class="form-group">
            <label for="passwort_bestaetigung">Passwort (Wiederholung) *</label>
            <input type="password" name="passwort_bestaetigung" id="passwort_bestaetigung" required>
        </div>

        <button type="submit" class="btn-primary">Benutzer anlegen</button>
    </form>
</div>
</body>
</html>
