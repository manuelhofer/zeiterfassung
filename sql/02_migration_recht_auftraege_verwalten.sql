-- Migration: Recht `AUFTRAEGE_VERWALTEN` nachtragen
-- Patch: P-2026-08-08-08
-- Datum: 2026-08-08
--
-- Zweck:
--   Bestehende Installationen bekommen das neue Recht, mit dem Auftraege und
--   deren Arbeitsschritte im Backend angelegt und bearbeitet werden duerfen.
--   Neuinstallationen brauchen dieses Skript nicht - dort steckt das Recht
--   bereits in `sql/01_initial_schema.sql`.
--
-- Eigenschaften:
--   - Idempotent: Mehrfaches Ausfuehren ist unschaedlich (`code` ist UNIQUE,
--     die Zuordnung haengt am Primaerschluessel).
--   - Vergibt keine Rechte an normale Rollen. Der Chef ist Superuser und darf
--     ohnehin alles; wer sonst Auftraege pflegen soll, bekommt das Recht ueber
--     die Rollenverwaltung im Backend.
--
-- Ausfuehren:
--   mysql -u <USER> -p zeiterfassung < sql/02_migration_recht_auftraege_verwalten.sql
--   (oder den Inhalt in phpMyAdmin einfuegen)

-- 1) Recht anlegen, falls es fehlt.
INSERT IGNORE INTO `recht` (`code`, `name`, `beschreibung`, `aktiv`)
VALUES (
    'AUFTRAEGE_VERWALTEN',
    'Aufträge verwalten',
    'Darf Aufträge und deren Arbeitsschritte im Backend anlegen, bearbeiten und deaktivieren. Ansehen der Aufträge und Drucken der Laufkarte bleibt ohne dieses Recht möglich.',
    1
);

-- 2) Allen Superuser-Rollen zuordnen, damit die Rechteuebersicht das Recht
--    dort auch angehakt anzeigt (funktional greift der Superuser-Bypass).
INSERT IGNORE INTO `rolle_hat_recht` (`rolle_id`, `recht_id`)
SELECT r.`id`, re.`id`
  FROM `rolle` r
  JOIN `recht` re ON re.`code` = 'AUFTRAEGE_VERWALTEN'
 WHERE r.`ist_superuser` = 1;

-- 3) Kontrolle (optional):
-- SELECT re.code, r.name AS rolle
--   FROM recht re
--   LEFT JOIN rolle_hat_recht rhr ON rhr.recht_id = re.id
--   LEFT JOIN rolle r ON r.id = rhr.rolle_id
--  WHERE re.code = 'AUFTRAEGE_VERWALTEN';
