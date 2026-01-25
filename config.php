<?php
declare(strict_types=1);

/**
 * Kompatibilitäts-Wrapper.
 *
 * Historisch gab es zwei Konfigurationsdateien:
 * - /config.php
 * - /config/config.php
 *
 * Damit die Anwendung künftig **nur noch eine** Quelle für Konfiguration und
 * Zugangsdaten hat, ist `/config/config.php` ab sofort die zentrale Datei.
 *
 * Diese Datei bleibt bestehen, damit bestehende Includes (z. B. `SessionManager`)
 * weiterhin funktionieren – sie enthält selbst keine Zugangsdaten.
 */

return require __DIR__ . '/config/config.php';
