# Mitarbeiterportal – Beispiel für Ihre Website

Damit koppeln Sie eine beliebige Website an die Zeiterfassung. Mitarbeiter
beantragen dann von zu Hause oder vom Handy ihren Urlaub und sehen ihre Zahlen.

Das hier ist **kein Rahmenwerk und keine Bibliothek**, sondern vier Dateien zum
Kopieren. Sie dürfen daran alles ändern.

---

## Was Sie brauchen

- PHP **8.1** oder neuer, mit `pdo_mysql`
- MariaDB oder MySQL
- Die Website muss aus dem Internet **erreichbar** sein (HTTPS)

Die Zeiterfassung muss das **nicht** sein. Sie ruft an, nie umgekehrt – deshalb
braucht der Firmenserver keine feste Adresse, keinen offenen Port und keine
Portweiterleitung. Das ist der ganze Grund für diesen Aufbau.

## Die vier Dateien

| Datei | Wozu |
| --- | --- |
| `portal-konfig.php` | **Die einzige, die Sie anfassen müssen.** Datenbankzugang. |
| `portal-api.php` | Der Endpunkt. Die ganze Gegenseite, eine Datei. |
| `kopplungscode.php` | Erzeugt den Code für den Handschlag. Gehört hinter Ihre Anmeldung. |
| `mitarbeiter.php` | Beispielseite für Mitarbeiter: anmelden, Zahlen sehen, Urlaub beantragen. |

Dazu `schema.sql` mit sechs Tabellen.

---

## In sechs Schritten

### 1. Tabellen anlegen

```bash
mariadb IHRE_DATENBANK < schema.sql
```

Mehrfach ausführbar, alles steht unter `IF NOT EXISTS`.

### 2. Zugang eintragen

In `portal-konfig.php` Host, Datenbank, Benutzer und Passwort eintragen.

Hat Ihre Website schon eine PDO-Verbindung? Dann ist der bessere Weg: diese
Datei wegwerfen und in `portal-api.php` und `mitarbeiter.php` Ihre vorhandene
Verbindung einsetzen. Beide brauchen nur ein fertiges `PDO`-Objekt.

### 3. Den Endpunkt erreichbar machen

Die Zeiterfassung ruft **`POST /portal-api`** auf – ohne `.php`, direkt unter
der Adresse, die Sie beim Koppeln eintragen. Mit Apache genügt eine Zeile in
der `.htaccess`:

```apache
RewriteEngine On
RewriteRule ^portal-api$ portal-api.php [L]
```

Mit nginx:

```nginx
location = /portal-api { try_files /portal-api.php =404; }
```

Liegt Ihre Website in einem **Unterverzeichnis** – etwa
`https://example.org/homepage/` –, dann ist das die Adresse, die Sie beim
Koppeln eintragen. Der Endpunkt liegt dann unter
`https://example.org/homepage/portal-api`.

**Prüfen Sie das, bevor Sie weitermachen:**

```bash
curl -i -X POST https://example.org/portal-api
```

Richtig ist eine JSON-Antwort mit `{"ok":false,"fehler":"Der Anfragekörper ist
kein gültiges JSON."}`. Kommt eine HTML-Fehlerseite oder 404, stimmt die
Umschreibung noch nicht.

### 4. Zwei Dateien schützen

`portal-konfig.php` enthält Ihr Datenbankpasswort, `kopplungscode.php` darf
nicht jeder aufrufen. In die `.htaccess` daneben:

```apache
<Files "portal-konfig.php">
    Require all denied
</Files>
```

Und in `kopplungscode.php` **oben** Ihre eigene Anmeldeprüfung einbauen. Solange
das nicht geschehen ist, sperrt sich die Datei selbst – Sie müssen die Sperre
bewusst entfernen.

### 5. Koppeln

1. `kopplungscode.php` aufrufen → **Kopplungscode erzeugen**. Acht Zeichen,
   30 Minuten gültig, genau einmal einlösbar.
2. In der Zeiterfassung: *Verwaltung → Mitarbeiterportal*. Adresse Ihrer
   Website eintragen (mit Unterverzeichnis, falls vorhanden) und den Code.
   **Koppeln** drücken.

Die Zeiterfassung gleicht sofort einmal ab. Danach stehen die freigeschalteten
Mitarbeiter in `portal_mitarbeiter`.

### 6. Mitarbeiterseite einbinden

`mitarbeiter.php` läuft so, wie sie ist – schmucklos, damit Sie das Gerüst
sehen. Der PHP-Teil oben ist vom HTML getrennt; setzen Sie Ihr Layout darum.

---

## Wie es danach weiterläuft

Die Zeiterfassung ruft in ihrem Takt an, üblicherweise alle zwei Minuten:

1. **`abholen`** – sie nimmt mit, was in `portal_auftrag` mit Status `offen`
   liegt.
2. Sie **entscheidet** – Ihre Website prüft nichts und genehmigt nichts.
3. **`melden`** – sie schickt die Ergebnisse und den neuen Stand.

Es gibt keinen Zeitplan auf Ihrer Seite und nichts, was Sie starten müssten.

---

## Das Protokoll, falls Sie es selbst bauen

**Eine** Adresse, `POST /portal-api`, JSON hinein und hinaus.

Jede Anfrage **außer `koppeln`** trägt vier Kopfzeilen:

| Kopfzeile | Inhalt |
| --- | --- |
| `X-Portal-Id` | die beim Koppeln vergebene ID |
| `X-Portal-Zeit` | Unix-Zeit der Zeiterfassung |
| `X-Portal-Nonce` | 32 Hexzeichen, je Anfrage neu |
| `X-Portal-Signatur` | 64 Hexzeichen, siehe unten |

```php
$signatur = hash_hmac('sha256', $portalId . "\n" . $zeit . "\n" . $nonce . "\n" . $koerper,
                      hash('sha256', $schluessel));
```

> **Hier stolpert jede Nachbildung:** Unterschrieben wird mit dem **Hash** des
> Schlüssels, nicht mit dem Schlüssel. Ihre Seite speichert ohnehin nur
> `hash('sha256', $schluessel)` – und genau dieser gespeicherte Wert ist der
> HMAC-Schlüssel. Der Schlüssel im Klartext existiert nur einmal, in der
> Zeiterfassung.

Abweisen müssen Sie, wenn: die Verbindung fehlt oder inaktiv ist, die Zeit mehr
als **300 Sekunden** abweicht, die Nonce schon einmal da war, oder die Signatur
nicht stimmt (`hash_equals`, nicht `===`).

### Die vier Aktionen

| Aktion | Hinein | Heraus |
| --- | --- | --- |
| `koppeln` | `{"aktion":"koppeln","code":"ABCD2345"}` | `{"ok":true,"portal_id":"…","schluessel":"…"}` |
| `hallo` | `{"aktion":"hallo"}` | `{"ok":true,"stand":"…"}` |
| `abholen` | `{"aktion":"abholen","grenze":100}` | `{"ok":true,"auftraege":[…]}` |
| `melden` | `{"aktion":"melden","ergebnisse":[…],"spiegel":{…}}` | `{"ok":true,"zustand":[…]}` |

Der Schlüssel geht bei `koppeln` **einmal** hinaus und wird bei Ihnen nie
gespeichert – nur sein Hash.

### Aufträge (`abholen`)

```json
{"id": 41, "mitarbeiter": 7, "art": "urlaub_antrag",
 "daten": {"von": "2026-10-05", "bis": "2026-10-09", "kommentar": ""}}
```

| `art` | `daten` |
| --- | --- |
| `urlaub_antrag` | `von`, `bis`, `kommentar` |
| `urlaub_storno` | `antrag` (die ID aus dem Spiegel) |
| `monat_pdf` | `jahr`, `monat` |

### Ergebnisse (`melden`)

```json
{"id": 41, "status": "angenommen", "hinweis": "", "antrag": 119}
```

`status` ist `angenommen` oder `abgelehnt`; bei `abgelehnt` steht in `hinweis`
der Klartext für den Mitarbeiter. Bei `monat_pdf` kommen zusätzlich `datei`
(base64) und `dateiname`.

### Der Spiegel (`melden`)

Er darf **teilweise** kommen: Was nicht dabei ist, bleibt unverändert stehen.
So lässt sich der Monatsteil seltener schicken als der Urlaubsteil.

| Teil | Felder je Eintrag |
| --- | --- |
| `mitarbeiter` | `id`, `kennung`, `anzeigename`, `aktiv`, `aktivierung_hash`, `aktivierung_bis` |
| `urlaub` | `mitarbeiter`, `jahr`, `uebertrag`, `anspruch`, `verbraucht`, `uebrig` |
| `antraege` | `mitarbeiter`, `id`, `von`, `bis`, `tage`, `status`, `kommentar_mitarbeiter`, `kommentar_genehmiger`, `antrags_datum`, `entscheidungs_datum` |
| `stunden` | `mitarbeiter`, `saldo`, `rest_soll_monat` |
| `monate` | `mitarbeiter`, `jahr`, `monat`, `tage` (Tagesliste) |
| `abwesenheiten` | `mitarbeiter`, Zeitraum und Art |
| `betriebsferien`, `feiertage` | firmenweit, ohne `mitarbeiter` |

**Die Liste `mitarbeiter` ist immer vollständig**, wenn sie kommt. Wer nicht
mehr darin steht, ist im Betrieb nicht mehr freigeschaltet und muss bei Ihnen
restlos verschwinden – Konto, Zahlen, Anträge. `portal-api.php` macht das.

### Was zurückfließt

Die Antwort auf `melden` trägt den einzigen Teil, den nur Sie wissen:

```json
{"ok":true,"zustand":[{"mitarbeiter":7,"aktiviert_am":"…","letzte_anmeldung_am":"…"}]}
```

Ob ein Aktivierungscode je eingelöst wurde und wann jemand zuletzt da war,
entsteht bei Ihnen – die Zeiterfassung sieht keine Anmeldung. Sie braucht es
trotzdem: Wer einen Code nachdrucken will, muss erkennen, dass der erste nie
benutzt wurde.

---

## Aktivierungscodes

Ein neuer Mitarbeiter bekommt im Betrieb einen Zettel mit Kennung und einem
Aktivierungscode. Bei Ihnen liegt davon nur `hash('sha256', CODE)` – Sie
könnten ihn nicht ausdrucken, selbst wenn Sie wollten.

Beim Prüfen: Groß-/Kleinschreibung und Trennzeichen wegwerfen
(`strtoupper`, `[^A-Za-z0-9]` entfernen), dann `hash('sha256', …)` und
`hash_equals`.

**Ein eingelöster Code muss verbraucht bleiben.** Die Zeiterfassung schickt ihn
beim nächsten Abgleich wieder mit, sie weiß ja nichts von der Anmeldung. Dafür
ist `aktivierung_hash_verbraucht` da. Ohne dieses Feld wäre ein längst
benutzter Zettel wieder gültig.

---

## Was Sie nicht tun sollten

- **Nichts selbst entscheiden.** Ihre Website sammelt ein und zeigt an. Ob ein
  Urlaub geht, weiß nur die Zeiterfassung – sie kennt Feiertage,
  Betriebsferien, Vertretungen und Salden.
- **Keine Zahlen nachrechnen.** Was im Spiegel steht, wird angezeigt, nicht
  überprüft.
- **Den Mitarbeiterbereich nicht in Suchmaschinen lassen.** `noindex,nofollow`
  steht in `mitarbeiter.php`; ergänzen Sie Ihre `robots.txt`.
- **Keine Daten sammeln, die nicht kommen.** Im Spiegel steht bewusst kein
  Geburtsdatum, kein Lohn, keine E-Mail-Adresse und kein RFID-Code. Die
  Website ist im Internet; was dort nicht liegen muss, liegt dort nicht.

## Wenn es klemmt

| Beobachtung | Ursache |
| --- | --- |
| Beim Koppeln HTTP 404 mit HTML-Fehlerseite | Die Umschreibung auf `/portal-api` fehlt, oder das Unterverzeichnis fehlt in der Adresse. |
| »Die Signatur stimmt nicht« | Fast immer der Schlüssel: Es wird mit `hash('sha256', $schluessel)` unterschrieben, nicht mit `$schluessel`. |
| »Die Uhren laufen auseinander« | Zeitdienst auf einem der beiden Server prüfen. 300 Sekunden sind erlaubt. |
| Mitarbeiter meldet »Kennung oder Aktivierungscode stimmt nicht«, obwohl beides stimmt | Seit dem Freischalten lief kein Abgleich – Ihre Seite kennt ihn noch nicht. In der Zeiterfassung *Jetzt abgleichen*. |
| `portal_mitarbeiter` bleibt leer | Es ist niemand freigeschaltet, oder der Abgleich läuft nicht. |

Die vollständige Beschreibung steht in
`docs/spezifikation_mitarbeiterportal.md` der Zeiterfassung.
