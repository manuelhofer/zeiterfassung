-- Migration 13: Eine kopierte Datenbank ruft nicht bei der echten Website an
-- Patch: P-2026-09-05-01
-- Datum: 2026-09-05
--
-- ANLASS: Gefunden beim Durchsehen der offenen Punkte, nicht beim Bauen - und
-- es ist der gefaehrlichste Befund des ganzen Vorhabens.
--
-- `docs/lokale_entwicklungsumgebung.md`, Abschnitt 6a, beschreibt als
-- **normalen Arbeitsweg**, einen Server-Dump in die Entwicklungsdatenbank
-- einzuspielen. Sobald das Mitarbeiterportal produktiv laeuft, steht in so
-- einem Dump auch `portal_verbindung` - mit Adresse und **gueltigem
-- Schluessel** der echten Website.
--
-- Was dann passiert, ohne dass es jemand merkt: Der Entwicklungsrechner
-- beginnt binnen zwei Minuten, mit `wernig.com` zu reden. Und er redet nicht
-- nur - er **schreibt**: Er leert den Briefkasten, meldet echte Urlaubsantraege
-- als erledigt, und weil sein Spiegel nur seine eigenen (alten, halb
-- geloeschten) Mitarbeiter kennt, loescht sein erster `melden`-Aufruf jeden
-- Mitarbeiter von der Website, den er nicht kennt - samt Konten, Zahlen und
-- wartenden Antraegen.
--
-- DIE SPALTE IST DER RIEGEL: Beim Koppeln haelt `installation` fest, **welche
-- Installation** gekoppelt hat - Rechnername und Basis-URL, im Klartext.
-- Vor jedem Aufruf vergleicht `PortalVerbindungService` das mit der laufenden
-- Installation. Stimmt es nicht ueberein, wird nicht angerufen, sondern
-- abgewiesen, und in der Maske steht, warum.
--
-- WARUM RECHNERNAME UND BASIS-URL und nicht etwas Feineres: Weil beides
-- **ausserhalb** der Datenbank steht - der Rechnername im System, die
-- Basis-URL in `config/config.local.php`, und die wandert bewusst nicht mit
-- einem Dump. Genau das macht sie zum Unterscheidungsmerkmal. Ein Geheimnis
-- ist es nicht und muss es nicht sein: Der Riegel schuetzt nicht vor einem
-- Angreifer - der haette den Schluessel ohnehin -, sondern vor einem Versehen.
-- Und Versehen sind hier der wahrscheinliche Fall.
--
-- Was der Riegel ausdruecklich NICHT kann: Wer den Dump auf einem Rechner mit
-- demselben Namen und derselben Basis-URL einspielt, kommt durch. Dafuer
-- braeuchte es ein Geheimnis ausserhalb der Datenbank, und das waere ein
-- eigenes Vorhaben. Der haeufige Fall - Server-Dump auf dem Entwicklungs-
-- rechner - ist abgedeckt.
--
-- BESTEHENDE VERBINDUNGEN bleiben leer und werden dadurch **nicht** gesperrt:
-- Eine leere Kennung heisst "vor dieser Migration gekoppelt", und dafuer darf
-- niemand ausgesperrt werden. Sie fuellt sich beim naechsten Koppeln.
--
-- Idempotent: `ADD COLUMN IF NOT EXISTS`.
--
-- Ausfuehren:
--   mysql -u <USER> -p zeiterfassung < sql/13_migration_portal_installation.sql

ALTER TABLE `portal_verbindung`
  ADD COLUMN IF NOT EXISTS `installation` VARCHAR(190) NOT NULL DEFAULT ''
      COMMENT 'Rechnername und Basis-URL der Installation, die gekoppelt hat. Leer = vor Migration 13 gekoppelt.'
      AFTER `portal_id`;

-- Kontrolle (optional):
-- SELECT portal_id, installation, aktiv FROM portal_verbindung;
