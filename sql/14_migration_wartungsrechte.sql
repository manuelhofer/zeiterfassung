-- Wartungsoberfläche: getrennte Rechte für Sicherungen und Softwareupdates.
INSERT INTO recht (code, name, beschreibung, aktiv)
SELECT 'BACKUP_VERWALTEN', 'Sicherungen erstellen', 'Darf vollständige Sicherungen von Backend und Terminals anfordern.', 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM (SELECT code FROM recht) r WHERE r.code = 'BACKUP_VERWALTEN');

INSERT INTO recht (code, name, beschreibung, aktiv)
SELECT 'UPDATE_VERWALTEN', 'Updates installieren', 'Darf main prüfen und nach automatischer Sicherung auf Backend und Terminals installieren.', 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM (SELECT code FROM recht) r WHERE r.code = 'UPDATE_VERWALTEN');

INSERT INTO rolle_hat_recht (rolle_id, recht_id)
SELECT ro.id, re.id FROM rolle ro JOIN recht re ON re.code IN ('BACKUP_VERWALTEN', 'UPDATE_VERWALTEN')
WHERE ro.name = 'Chef' AND NOT EXISTS (
    SELECT 1 FROM (SELECT rolle_id, recht_id FROM rolle_hat_recht) x WHERE x.rolle_id = ro.id AND x.recht_id = re.id
);
