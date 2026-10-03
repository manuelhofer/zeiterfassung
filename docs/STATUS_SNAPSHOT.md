# Status-Snapshot

**Die einzige Stelle für den aktuellen Stand:** Projektstatus, nächster Schritt,
offene Bugs und Tasks. Wer wissen will, was ansteht, liest diese Datei und sonst
nichts.

## Projektstatus
- **FERTIG** – System ist im **Praxis-Test**.
- Weiterentwicklung nur bei **Bugs** oder **ausdrücklicher Beauftragung**.
- **Es gibt keinen Produktivbetrieb.** Keine Installation im Einsatz, keine
  Mitarbeiter, die damit stempeln, keine Daten, an denen etwas hängt.
  Produktivbetrieb beginnt **erst**, wenn Manuel ausdrücklich sagt: „Jetzt
  gehen wir in den Produktivbetrieb." Bis dahin gilt jede Formulierung wie
  „im laufenden Betrieb", „im Produktivbestand nachsehen" oder „betrifft
  Anwender" als **falsch** – sie erzeugt Dringlichkeit, die es nicht gibt,
  und Arbeit, die niemand braucht. Ein behobener Fehler betrifft den Test,
  sonst nichts.

## Nächster Schritt (konkret)

**Windows/XAMPP-Wartung erweitern und nativ abnehmen** (03.10.2026):
Ausdrücklich beauftragt: gemeinsamer Backup-/Updateablauf für Linux und Windows,
automatischer XAMPP-Installer und bestehende Linux-Terminals; Zielbild in
[spezifikation_wartung_windows.md](spezifikation_wartung_windows.md).
Linux-Integration einschließlich ZIP-Backend/tar-Terminals ist geprüft;
native Windows-Abnahme erfolgt im wegwerfbaren GitHub-Runner.
Der erste native Lauf hat laufwerksrelative Pfade im portablen XAMPP entdeckt;
der Installer setzt PHP-/Apache-/MariaDB-Pfade vor dem Start absolut und
aktiviert eine PHP-Erweiterung nur, wenn nicht bereits ihr DLL-Alias aktiv ist.
Auch Windows-versteckte Git-Dateien werden bei der Rechtevergabe berücksichtigt.
Die Pfadumstellung erhält Apache-Präfixe wie den SSL-Cache-Anbieter `shmcb:`.
Native Installation, Zugriffsschutz, Apache, ZIP-/SQL-Restore und 20 portable
Windows-Prüfungen bestehen; die Windows-Dateiprüfung testet gezielt
Austauschfreigabe statt unnötiger exklusiver Lese-/Schreibsperre.
Der Windows-Dienst lädt seine CLI-Skripte per `require`, damit der laufende
Updater und sein Takt beim Austausch ihres eigenen Quelltexts keine Sperre halten.
Skriptargumente werden nach dem PHP-Optionentrenner übergeben.
Bereits offene Dateien werden während der Vorbereitung erkannt, damit ein
Abbruch vor SQL die Buchungssperre automatisch aufhebt und erneut versucht werden kann.

**Backend-Installation nach dem Debian-Abbruch erneut prüfen** (03.10.2026):
Der Installer verschiebt Projekte mit für `www-data` gesperrtem Elternordner
(insbesondere `/root`) automatisch nach `/var/www/zeiterfassung` und erhält die
vorhandene Konfiguration und Datenbank. Pfadfehler, Umzug, Wiederanlauf und
belegtes Ziel sind isoliert im Debian-Container geprüft; vollständiger
Debian-13-Paket-/Apache-/Systemd-Durchlauf am Benutzergerät bleibt offen.

**Gemeinsamen Terminal-Installer am Gerät abnehmen** (01.10.2026): Ein Download
und ein Start verbinden Grundsystem, Kiosk, Peripherie und technische Vorprüfung;
Anleitung in [installationsanleitung.md](installationsanleitung.md#7-terminal-installation-optional).
Abbruch, Wiederanlauf und Bestandsschutz sind isoliert geprüft; native
Paketinstallation, Bildausgabe, Touch und echte Scans bleiben offen. USB im
Tastaturmodus, serielle Leser und direkter RC522 über Linux-SPI sind umgesetzt;
[Pinplan und Plattformgrenzen](terminal/rc522_anschluss.md) beachten. Der Installer
zeigt für bekannte Pi-Modelle die konkreten Pins; andere Platinen benötigen ihren
Hersteller-Pinplan. RC522-Protokoll und WebSocket sind mit synthetischen Daten
geprüft, tatsächliche Hardware bleibt abzunehmen. Internet ist zur Erstinstallation nötig.

**Bedienprüfung abgeschlossen** (01.10.2026): Die Grundabläufe sind nutzbar,
die Verwaltung bleibt zu technisch; konkrete Befunde, Prüfgrenzen und
vorgeschlagene Folgepatches stehen in
[bedienbarkeitspruefung.md](bedienbarkeitspruefung.md).
**Vorgabe vom 01.10.2026:** Bedienbarkeit des Backends am normalen PC-Bildschirm
hat Vorrang; alle bestehenden Funktionen müssen erhalten bleiben.
Zuerst empfohlen: verständlicher Verwaltungsstart sowie eindeutige Terminal-
und Auftragsstatusangaben; weitere Vereinfachungen sind dokumentierte Vorschläge.
Handyoptimierung ist kein aktuelles Ziel. Die halbstündliche automatische
Fortsetzung wurde auf Wunsch entfernt; weiterarbeiten nur im laufenden Auftrag.

**Backup und Updates auf den Testgeräten abnehmen** (30.09.2026):
Bedienung und automatische Vorbereitung sind umgesetzt: Die normale
Installation richtet Dienst und Sicherungszugriff ein, gekoppelte Terminals
melden sich selbst über ihren bestehenden DB-Zugang. Keine zusätzliche
Wartungskonfiguration oder SSH-Einrichtung. Gemeinsamer Backup-/Updateablauf,
Erststart, Kopplung und Fehleranzeigen sind mit synthetischen Daten geprüft;
Details in [wartung_betrieb.md](wartung_betrieb.md). Offen: normale Auslieferung
dieses ersten Stands auf die Geräte, native Paket-/Systemd-Installation,
Apache-/FPM-Neustart und Wiederherstellung auf separater Hardware.
Veröffentlichung der Änderungen auf GitHub-`main` am 01.10.2026 ausdrücklich
freigegeben; keine Installation auf Geräten durchgeführt.

**Eine stillgelegte Anmeldung ist jetzt wirklich stillgelegt** (05.09.2026,
P-2026-09-05-02). Gefunden beim Prüfen des Portals, aber älter als dieses:
Eine offene Sitzung überlebte bisher das Stilllegen **und das Löschen** eines
Mitarbeiters, weil `istAngemeldet()` nur in die Sitzung sah – und ein
Rechteentzug griff überhaupt nie, weil die Rechte dort zwischengespeichert
wurden. Beides ist behoben: Eine laufende Sitzung wird höchstens einmal je
Minute gegen die Datenbank gehalten, und dabei fällt der Rechte-Zwischenspeicher
weg. Wer stillgelegt wird, steht spätestens nach einer Minute vor der
Anmeldemaske – mit einem Satz, der sagt, warum. Ein Ausfall der Datenbank
meldet dabei niemanden ab.

**Eine kopierte Datenbank ruft nicht bei der echten Website an** (05.09.2026,
P-2026-09-05-01). Der in
[`lokale_entwicklungsumgebung.md`](lokale_entwicklungsumgebung.md) beschriebene
Weg – Server-Dump in die Entwicklungsumgebung – hätte ab dem Produktivgang des
Portals dazu geführt, dass der Entwicklungsrechner binnen zwei Minuten auf
`wernig.com` **schreibt**: Anträge als erledigt meldet und jeden Mitarbeiter
löscht, den er nicht kennt. Die Verbindung merkt sich jetzt beim Koppeln, wer
gekoppelt hat; passt es nicht, geht kein Aufruf hinaus. Nach einem eingespielten
Dump gehört trotzdem ein Blick in die Verbindung – siehe
[Wartungscheckliste](wartungscheckliste.md).

**Das Mitarbeiterportal läuft – im Testaufbau, Ende zu Ende** (04.09.2026,
T-170 fertig). Ein Mitarbeiter beantragt Urlaub auf der WERNIG-Homepage, zwei
Minuten später steht der Antrag hier unter »Urlaubsanträge«; wird er genehmigt,
steht das Ergebnis zwei Minuten später drüben, und der Resturlaub ist um die
Tage kleiner. Die ganze Abnahmekette aus Abschnitt 11 der
[`spezifikation_mitarbeiterportal.md`](spezifikation_mitarbeiterportal.md) ist
durchgespielt, **einschließlich der beiden unangenehmen Fälle**: Bei
abgeschaltetem Firmenserver zeigt das Portal seine Zahlen mit einem sichtbaren
Hinweis, wie alt sie sind, und nimmt Anträge weiter entgegen; wird eine
Freischaltung entzogen, ist der Mitarbeiter beim nächsten Abgleich samt Konto,
Zahlen und Anträgen von der Website verschwunden und seine Sitzung dort sofort
beendet.

**Die Richtung ist die ganze Architektur:** Diese Installation ruft an, die
Homepage antwortet – nie umgekehrt. Sie kennt weder Adresse noch Datenbank
dieses Servers. Nötig ist dafür genau ein Eintrag im Zeitplan
([Installationsanleitung](installationsanleitung.md), Abschnitt 8) und ein
ausgehender HTTPS-Weg; eingehend wird nichts gebraucht, kein Port, keine feste
Adresse.

Entschieden am 04.09.2026: Aktivierungscode mit eigenem Portal-Passwort (nicht
der Backend-Hash), voller Umfang bis Monatsübersicht und PDF, **Genehmigen
bleibt in der Zeiterfassung**, auf dem Handy zuerst eine installierbare
Webseite (die Android-APK kommt später).

B-106 ist erledigt (P-2026-08-17-33 offline, -34 online). Kommen, Gehen und
Auftragsstart/-stopp wurden nun im Browser mit synthetischen Daten geprüft;
die Abnahme am tatsächlichen Terminalgerät bleibt offen.

## Offene Bugs

- **B-107 Mobile Konfigurationstabelle** – Die Konfiguration verbreitert
  bei 390 Pixeln die gesamte Seite statt nur einen Tabellenbereich
  ([Befund UX-02](bedienbarkeitspruefung.md#ux-02--hoch--einige-handyansichten-schieben-die-ganze-seite-seitlich));
  zurückgestellt, da der normale PC-Bildschirm maßgeblich ist.

## Offene Tasks

Ein Satz je Task – die Begründung steht im Verlauf, nicht hier.

- **T-174 Bedienbarkeit** – Weitere einzeln zugeschnittene Verbesserungen
  anhand der [Bedienprüfung](bedienbarkeitspruefung.md) festlegen; insbesondere
  Verwaltungsstart, Urlaubssalden, Terminal-/Auftragsstatus und automatische
  Vorbereitung des Portal-Zeitplans sind Vorschläge, keine bereits geänderten Fachregeln.
- **Gerätetest am Terminal** – Kopplung und Skripte sind fertig und im Container
  geprüft, das Gerät ist frühestens ab ca. Mitte September verfügbar; Protokoll
  und Stufenplan in
  [`spezifikation_terminal_installation.md`](spezifikation_terminal_installation.md),
  Abschnitt 12 und 11.
- **T-172 Mitarbeiterportal am echten Gerät** – die Kette läuft im
  Testaufbau (localhost gegen localhost). Was auf einem Gerät noch niemand
  gesehen hat: »Zum Startbildschirm hinzufügen« auf Android und iPhone, das
  Symbol, der Start ohne Adresszeile und die Offline-Seite im Flugmodus. Die
  Registrierung des Service Workers ließ sich im Prüfbrowser gar nicht
  auslösen – er lehnt sie auch für gewöhnliche Dateien ab.
- **T-173 Erster echter Abgleich über das Internet** – bisher lief alles gegen
  `127.0.0.1`. Zu prüfen sind der Weg über HTTPS mit gültigem Zertifikat und
  die Uhren beider Server: Weichen sie mehr als 300 Sekunden voneinander ab,
  weist die Homepage jede Anfrage ab (die Meldung nennt das ausdrücklich).
- **T-142** Aus dem `SmokeTestController` sind die fachlichen und die
  PDF-Prüfungen heraus; offen bleibt `pruefeTerminalLogin`, und das erst nach
  dem Gerätetest.

**Offline-Betrieb am Terminal** – Befund und Entscheidungen in P-2026-08-16-08,
die Regeln dazu in
[`fachregeln/terminal_und_offline.md`](fachregeln/terminal_und_offline.md),
Abschnitt 5. Die Aufgabenkette daraus ist abgearbeitet, T-138 als zweiter
Schritt ebenfalls. Was daraus offen blieb:

- **Nebenaufträge offline** – der Queue-Code steht, aber sie hängen weiter an
  einer Anmeldung, und ihr Maschinenfeld hat keine Auswahl. Erst nachziehen,
  wenn der Praxis-Test zeigt, dass sie am Gerät gebraucht werden.
- **Jahreswechsel beobachten:** Beim ersten echten Jahreswechsel prüfen, ob die
  festgeschriebenen Urlaubssalden plausibel bleiben (B-080).
- **Terminal am Gerät:** Kommen/Gehen und Auftrag starten/stoppen sind online
  im Browser mit synthetischen Daten geprüft (P-2026-10-01-01); echte
  Leser-/Touchbedienung bleibt abzunehmen, Offline-Kommen samt Wiederanlauf
  wurde bereits eingegrenzt in P-2026-08-16-17 geprüft.
- Praxis-Test: Bugs und Anomalien sammeln, als Micro-Patches beheben.
- Nur bei Bedarf: Scan-Flow/UX im Auftragsmodul verfeinern, Stop-Detailmaske
  (Fallback) am Terminal vereinfachen.
