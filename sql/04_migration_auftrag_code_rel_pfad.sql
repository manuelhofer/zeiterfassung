-- Migration: Konfigurationsschluessel `auftrag_qr_rel_pfad` umbenennen
-- Patch: P-2026-08-08-24
-- Datum: 2026-08-08
--
-- Zweck:
--   Die Codes fuer Auftraege, Arbeitsschritte und Katalogeintraege sind seit
--   P-2026-08-08-15 Strichcodes (Code 128), keine QR-Codes mehr. Der
--   Schluesselname `auftrag_qr_rel_pfad` war damit irrefuehrend und heisst
--   jetzt `auftrag_code_rel_pfad`.
--
--   Es aendert sich nur der Name. Der eingestellte Pfad bleibt erhalten,
--   und die Bilddateien bleiben liegen, wo sie sind.
--
-- Eigenschaften:
--   - Idempotent: Mehrfaches Ausfuehren ist unschaedlich.
--   - Ein bereits vorhandener neuer Schluessel wird nicht ueberschrieben.
--   - Auch ohne diese Migration laeuft die Anwendung weiter: Der Code liest
--     ersatzweise den alten Schluessel (siehe `BarcodeService`). Die Migration
--     raeumt lediglich auf.
--
-- Ausfuehren:
--   mysql -u <USER> -p zeiterfassung < sql/04_migration_auftrag_code_rel_pfad.sql

-- 1) Alten Wert unter neuem Namen anlegen, falls der neue noch fehlt.
INSERT IGNORE INTO `config` (`schluessel`, `wert`, `typ`, `beschreibung`)
SELECT
    'auftrag_code_rel_pfad',
    c.`wert`,
    c.`typ`,
    'Auftrags-Strichcodes: Relativer Speicherpfad unterhalb von public für die Code-Bilder von Aufträgen, Arbeitsschritten und Katalogeinträgen. Default uploads/auftrag_codes.'
FROM `config` c
WHERE c.`schluessel` = 'auftrag_qr_rel_pfad';

-- 2) Falls es weder alt noch neu gab: Standard anlegen.
INSERT IGNORE INTO `config` (`schluessel`, `wert`, `typ`, `beschreibung`)
VALUES (
    'auftrag_code_rel_pfad',
    'uploads/auftrag_codes',
    'string',
    'Auftrags-Strichcodes: Relativer Speicherpfad unterhalb von public für die Code-Bilder von Aufträgen, Arbeitsschritten und Katalogeinträgen. Default uploads/auftrag_codes.'
);

-- 3) Alten Schluessel entfernen - nur wenn der neue wirklich existiert.
DELETE FROM `config`
 WHERE `schluessel` = 'auftrag_qr_rel_pfad'
   AND EXISTS (SELECT 1 FROM (SELECT 1 FROM `config` WHERE `schluessel` = 'auftrag_code_rel_pfad') AS t);

-- Kontrolle (optional):
-- SELECT schluessel, wert FROM config WHERE schluessel LIKE 'auftrag%';
