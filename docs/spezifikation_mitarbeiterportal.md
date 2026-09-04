# Spezifikation: Mitarbeiterportal

> **Diese Datei steht wortgleich in beiden Projekten** – in
> `zeiterfassung/docs/spezifikation_mitarbeiterportal.md` und in
> `WernigHomepage/docs/spezifikation_mitarbeiterportal.md`. Sie ist der
> **Vertrag** zwischen ihnen: Wer eine Zeile ändert, ändert sie in beiden
> Repositorys, im selben Patch-Schritt und mit derselben Begründung. Eine
> Fassung, die nur auf einer Seite gilt, ist ein Fehler – nicht eine
> Abweichung.

---

## 1. Zielbild in fünf Sätzen

Ein Mitarbeiter soll von zu Hause oder vom Handy aus sehen, wie viel Urlaub er
noch hat, wie sein Stundenkonto steht und was er im Monat gearbeitet hat – und
er soll Urlaub beantragen können, ohne in die Halle zu laufen. Die
Zeiterfassung steht im Firmennetz und ist von außen **nicht erreichbar**; die
Homepage steht auf einem vServer mit fester Adresse. Also übernimmt die
Homepage einen Bereich `/mitarbeiter`, der wie ein **Briefkasten mit
Schaufenster** arbeitet: Anträge fallen hinein, Zahlen liegen zum Ansehen
aus. Die Zeiterfassung bleibt die **einzige Quelle der Wahrheit**; sie ruft im
Takt bei der Homepage an, leert den Briefkasten und stellt das Schaufenster
neu. Genehmigt wird ausschließlich in der Zeiterfassung – am Terminal oder im
Backend.

## 2. Warum die Zeiterfassung anruft und nicht umgekehrt

Der Firmenserver hat keine feste, von außen erreichbare Adresse. Selbst wenn er
eine hätte, wäre die Richtung falsch herum: Ein öffentlicher Webserver, der in
das Firmennetz hineinschreiben darf, ist ein Zugang von außen in die
Personaldaten – und zwar einer, der offen steht, auch wenn ihn niemand
benutzt.

Deshalb gilt in **eine** Richtung:

> **Die Zeiterfassung ruft an. Die Homepage antwortet. Die Homepage ruft
> niemals an.**

Die Homepage kennt weder die Adresse der Zeiterfassung noch ihre Datenbank.
Sie kann nichts abfragen, nichts anstoßen und nichts erzwingen. Sie kann nur
aufbewahren, was ihr gebracht wird, und weitergeben, was abgeholt wird. Fällt
der Firmenserver aus, steht das Portal still und zeigt, wie alt seine Zahlen
sind – es zeigt nie falsche Zahlen als aktuelle aus.

Der Preis dafür ist **Verzögerung**: Ein Antrag ist nicht sofort in der
Zeiterfassung, sondern beim nächsten Anruf. Der Takt ist einstellbar,
Vorgabe **zwei Minuten**. Das Portal sagt dem Mitarbeiter offen, in welchem
Zustand sein Antrag ist (Abschnitt 8).

## 3. Die beiden Seiten und ihre Aufgaben

| | Zeiterfassung (Firmenserver) | Homepage (vServer) |
| --- | --- | --- |
| Rolle | Quelle der Wahrheit | Briefkasten und Schaufenster |
| Kennt | Adresse und Schlüssel der Homepage | nichts von der Zeiterfassung |
| Schreibt | `urlaubsantrag`, alle Fachdaten | Spiegel und Ausgang |
| Rechnet | Urlaubssaldo, Stunden, PDF | nichts – sie zeigt nur an |
| Genehmigt | ja | **nie** |

**Die Homepage rechnet nichts.** Kein Urlaubstag, kein Saldo, kein Sollstunden­
wert entsteht dort. Sie zeigt Zahlen an, die die Zeiterfassung ausgerechnet
und geschickt hat. Das ist der Grund, warum die Zahl im Portal und die Zahl am
Terminal nicht auseinanderlaufen können: Es gibt nur eine Rechnung.

## 4. Kopplung – der Handschlag

Vorbild ist die Terminal-Kopplung (`docs/fachregeln/terminal_und_offline.md`,
Abschnitt 4), nur in die andere Richtung.

1. **Auf der Homepage**, Backend → *Mitarbeiterportal* → **Kopplungscode
   erzeugen**. Acht Zeichen aus einem Alphabet ohne verwechselbare Zeichen,
   **30 Minuten** gültig, **einmalig**, nur als Hash gespeichert.
2. **In der Zeiterfassung**, Backend → *Mitarbeiterportal* → Adresse der
   Homepage (`https://wernig.com`) und den Code eintragen, *Koppeln*.
3. Die Zeiterfassung ruft `POST https://wernig.com/portal-api` mit
   `aktion=koppeln` und dem Code auf.
4. Die Homepage löst den Code ein – **verbraucht ist er ab hier in jedem
   Fall** –, erzeugt `portal_id` und einen 64-stelligen Schlüssel, speichert
   nur dessen Hash und gibt beides **einmalig** zurück.
5. Die Zeiterfassung legt `portal_id`, Schlüssel und Adresse in
   `portal_verbindung` ab. Damit ist die Verbindung fertig.

**Entkoppeln** geht auf beiden Seiten und wirkt sofort: Die Homepage setzt die
Verbindung auf inaktiv (jede weitere Anfrage wird abgewiesen), die
Zeiterfassung löscht ihren Schlüssel. Der Spiegel auf der Homepage wird beim
Entkoppeln **gelöscht** – ohne Zeiterfassung dahinter ist er nur noch ein
Datenbestand ohne Zweck.

Recht auf der Homepage: `PORTAL_VERWALTEN`. In der Zeiterfassung: ebenfalls
`PORTAL_VERWALTEN`. Der Kopplungs-Endpunkt selbst ist ohne Anmeldung
erreichbar – der Code **ist** der Nachweis.

## 5. Das Protokoll

**Eine** Adresse: `POST /portal-api` auf der Homepage. JSON hinein, JSON
hinaus, `Content-Type: application/json`, `Cache-Control: no-store`.

Jede Anfrage **außer** `koppeln` trägt diese Kopfzeilen:

| Kopfzeile | Inhalt |
| --- | --- |
| `X-Portal-Id` | die bei der Kopplung vergebene ID |
| `X-Portal-Zeit` | Unix-Zeit der Zeiterfassung |
| `X-Portal-Nonce` | 32 Hexzeichen, je Anfrage neu |
| `X-Portal-Signatur` | siehe unten |

```
Signatur = hash_hmac('sha256', portal_id . "\n" . zeit . "\n" . nonce . "\n" . koerper, schluessel)
```

Die Homepage weist ab, wenn: die Verbindung fehlt oder inaktiv ist, die Zeit
mehr als **300 Sekunden** abweicht, die Nonce schon einmal da war, oder die
Signatur nicht stimmt (Vergleich mit `hash_equals`). Jede Abweisung geht ins
Protokoll, zu viele hintereinander bremsen den Absender aus.

**Warum HMAC und nicht einfach ein Token im Kopf:** Der Körper ist
mitunterschrieben. Ein abgefangener Aufruf lässt sich nicht mit anderem Inhalt
wiederholen, und die Nonce verhindert, dass er überhaupt ein zweites Mal
angenommen wird.

### 5.1 `abholen`

Anfrage: `{"aktion":"abholen","grenze":100}`

Antwort:

```json
{"ok":true,"stand":"2026-09-04 08:15:03","auftraege":[
  {"id":41,"art":"urlaub_antrag","mitarbeiter":7,
   "daten":{"von":"2026-10-05","bis":"2026-10-09","kommentar":"Herbstferien"}},
  {"id":42,"art":"urlaub_storno","mitarbeiter":7,"daten":{"antrag":118}},
  {"id":43,"art":"monat_pdf","mitarbeiter":7,"daten":{"jahr":2026,"monat":8}}
]}
```

Die Homepage merkt sich, dass die Zeilen abgeholt wurden (`abgeholt_am`), lässt
sie aber stehen, bis eine Rückmeldung kommt. Ein Anruf, der unterwegs
abbricht, führt deshalb höchstens zu einer zweiten Zustellung – nie zum
Verlust. Gegen die doppelte Ausführung schützt die Zeiterfassung mit
`portal_eingang` (Abschnitt 6).

### 5.2 `melden`

Die Zeiterfassung schickt in einem Aufruf beides: das Ergebnis je Auftrag und
den neuen Stand des Schaufensters.

```json
{"aktion":"melden",
 "ergebnisse":[{"id":41,"status":"angenommen","hinweis":"","antrag":119},
               {"id":43,"status":"angenommen","datei":"…base64…","dateiname":"august-2026.pdf"}],
 "spiegel":{ … siehe Abschnitt 7 … }}
```

`status` ist `angenommen` oder `abgelehnt`; bei `abgelehnt` steht in `hinweis`
der Klartext, den der Mitarbeiter zu sehen bekommt („Der Zeitraum überschneidet
sich mit einem bereits genehmigten Urlaub.").

Der Spiegel darf **teilweise** kommen: Was nicht im Aufruf steht, bleibt
unverändert stehen. Nur so lässt sich der Monatsteil seltener schicken als der
Urlaubsteil.

## 6. Genau einmal ausführen

Ein Auftrag darf nicht zweimal zu einem Urlaubsantrag werden. Deshalb:

- Die Homepage vergibt je Ausgangszeile eine **fortlaufende ID**, die sich nie
  wiederholt.
- Die Zeiterfassung führt `portal_eingang` mit `(portal_id, fremd_id)` als
  eindeutigem Schlüssel. Sie schreibt **zuerst** die Eingangszeile und
  **danach** den Fachdatensatz, beides in einer Transaktion. Eine ID, die schon
  dasteht, wird still übersprungen und nur erneut rückgemeldet.

Damit ist der Ablauf gegen jeden Abbruch dicht: Bricht es vor dem Schreiben ab,
kommt der Auftrag beim nächsten Mal wieder. Bricht es nach dem Schreiben, aber
vor dem Melden ab, kommt er auch wieder – und wird als Doppel erkannt.

## 7. Was im Schaufenster liegt

Der Spiegel enthält **nur, was das Portal anzeigt**, und nichts darüber
hinaus. Ausdrücklich **nicht** dabei: Geburtsdatum, Lohnkorrekturen,
Stundenlöhne, RFID-Codes, E-Mail-Adressen, Auftragszeiten, andere Mitarbeiter.

| Teil | Inhalt |
| --- | --- |
| `mitarbeiter` | Fremd-ID, Anzeigename, Portal-Kennung, aktiv, Aktivierungscode als Hash mit Frist |
| `urlaub` | je Mitarbeiter: Jahr, Übertrag, Jahresanspruch, verbraucht, übrig |
| `antraege` | je Mitarbeiter: eigene Anträge mit Zeitraum, Tagen, Status, Kommentar |
| `stunden` | je Mitarbeiter: Saldo Über-/Minusstunden, Rest-Sollstunden des Monats |
| `monate` | je Mitarbeiter und Monat: Tagesliste mit Kommen/Gehen, Pause, Ist, Soll, Kennzeichen |
| `betriebsferien` | firmenweit: Zeitraum und Beschreibung |
| `feiertage` | firmenweit: Datum und Name |

**Nur freigeschaltete Mitarbeiter** stehen im Spiegel. Die Freischaltung ist
ein Haken je Mitarbeiter in der Zeiterfassung (`mitarbeiter.portal_aktiv`).
Wird er entfernt, verschwindet der Mitarbeiter beim nächsten Anruf vollständig
von der Homepage – Konto, Zahlen, Anträge, angeforderte Dateien.

**Monats-PDF auf Anforderung, nicht auf Vorrat.** Ein PDF entsteht erst, wenn
ein Mitarbeiter im Portal darauf drückt; der Auftrag `monat_pdf` läuft durch
denselben Briefkasten wie ein Urlaubsantrag. Die fertige Datei liegt **sieben
Tage** auf der Homepage und wird dann vom Zeitplandienst weggeräumt. Der Grund
ist Sparsamkeit in beide Richtungen: Dreizehn Mitarbeiter mal zwölf Monate
wären 156 PDFs, die alle zwei Minuten neu geschrieben würden – für Dateien, die
kaum jemand ansieht.

## 8. Der Mitarbeiterbereich `/mitarbeiter`

### 8.1 Erstanmeldung mit Aktivierungscode

Ein Mitarbeiter bekommt aus der Zeiterfassung einen **Aktivierungscode** –
zwölf Zeichen, ausdruckbar, **14 Tage** gültig, einmalig. Im Portal gibt er
**Kennung + Code** ein und setzt sich dann selbst ein Passwort (mindestens zehn
Zeichen). Ab da meldet er sich mit **Kennung + Passwort** an.

**Die Kennung ist nicht die Personalnummer.** Sie wäre der naheliegende
Anmeldename, aber im Bestand hat sie **genau ein** Mitarbeiter von dreizehn –
die Anmeldung daran aufzuhängen hieße, zwölf Personalnummern zu erfinden, nur
damit sich jemand anmelden kann. Stattdessen vergibt die Zeiterfassung bei der
Freischaltung eine **Portal-Kennung** (`mitarbeiter.portal_kennung`, eindeutig,
ASCII, klein geschrieben). Sie wird vorgeschlagen – Personalnummer, wenn es
eine gibt, sonst `vorname.nachname`, sonst `m<id>` – und lässt sich in der
Maske ändern. Der Mitarbeiter bekommt sie zusammen mit dem Aktivierungscode auf
denselben Zettel gedruckt.

**Warum nicht das Passwort aus der Zeiterfassung spiegeln:** Dann läge der Hash
des Firmenzugangs auf einem öffentlichen Server. Das Portal-Passwort gilt nur
für das Portal; wer es errät, sieht Urlaubstage und Stunden **eines**
Mitarbeiters und kann einen Urlaubsantrag stellen, den ein Mensch genehmigen
muss. Er kommt damit nicht an die Zeiterfassung. Zweiter Grund: Von dreizehn
Mitarbeitern haben sieben überhaupt ein Passwort im Backend – die übrigen
hätten erst eines bekommen müssen, nur damit das Spiegeln funktioniert.

Der Code selbst liegt auf der Homepage **nur als Hash**. Wer die Datenbank der
Homepage liest, kann sich damit kein Konto aktivieren.

Fehlversuche: fünf in fünfzehn Minuten, dann Sperre – dieselbe Regel wie beim
Kundenkonto. Sitzung: acht Stunden ohne Aktivität, dann Abmeldung.

### 8.2 Was der Mitarbeiter sieht

- **Übersicht:** Resturlaub (aufgeschlüsselt nach Übertrag und laufendem
  Jahr), Stundensaldo, Rest-Sollstunden des Monats, eigene Anträge mit Status,
  Betriebsferien, Feiertage.
- **Urlaub beantragen:** Von- und Bis-Datum, Kommentar. Das Portal zeigt vor
  dem Abschicken an, wie viele Arbeitstage das ungefähr sind – gerechnet wird
  es aber erst in der Zeiterfassung, und deren Zahl gilt.
- **Stornieren:** solange der Antrag `offen` ist.
- **Monatsübersicht:** Tagesliste des gewählten Monats, dazu der Knopf
  *PDF anfordern*.

**Der Zustand eines Antrags ist immer sichtbar**, und zwar ehrlich:

| Zustand | Was dasteht |
| --- | --- |
| `wartet` | „Wird an die Zeiterfassung übermittelt" |
| `abgeholt` | „Bei der Zeiterfassung eingegangen" |
| `offen` | „Liegt zur Genehmigung vor" |
| `genehmigt` / `abgelehnt` | Ergebnis mit Kommentar des Genehmigers |
| `fehlgeschlagen` | Klartext, warum – der Antrag wurde **nicht** angelegt |

Auf jeder Seite steht, **wann der letzte Abgleich war**. Liegt er mehr als
fünfzehn Minuten zurück, steht dort ein sichtbarer Hinweis, dass die
Zeiterfassung gerade nicht erreichbar ist. Ein Portal, das alte Zahlen als
aktuelle ausgibt, wäre schlimmer als eines, das gar nichts zeigt.

### 8.3 Keine Seite hier steht im Index

`noindex,nofollow` auf allen Ansichten unter `/mitarbeiter`, kein Eintrag in
der Sitemap, kein Link aus dem Menü. Wer die Adresse nicht kennt, hat dort
nichts zu suchen.

## 9. Auf dem Handy

Stufe eins ist eine **installierbare Webseite** (PWA): Manifest, Symbol,
Startfarbe, ein Service Worker, der ausschließlich den Rahmen und eine
Offline-Seite vorhält – **nie** Personendaten. Android und iPhone bieten damit
„Zum Startbildschirm hinzufügen" an; danach startet das Portal im eigenen
Fenster, ohne Adresszeile, mit eigenem Symbol.

Stufe zwei – **eine Android-App als APK** – ist vorgesehen, aber nicht Teil
dieser Etappe. Sie wird ein einziges WebView-Fenster auf dieselbe Adresse
sein. Damit sie später ohne Änderung am Portal möglich ist, gilt schon jetzt:
keine Abhängigkeit von Browser-Eigenheiten, alle Wege auch mit Fingern
bedienbar, und die Anmeldung überlebt das Schließen des Fensters.

## 10. Was auf dem öffentlichen Server liegt – und was das bedeutet

Auf dem vServer liegen dauerhaft: Name, Portal-Kennung, Portal-Passwort-Hash,
Urlaubszahlen, Stundensalden und Tageslisten der **freigeschalteten**
Mitarbeiter, dazu angeforderte PDFs für sieben Tage. Das ist echter
Personenbezug auf einem Rechner im Internet, und das gehört in die
Datenschutzerklärung der Homepage – als eigener Abschnitt, nicht als Nebensatz.

Was dort **nicht** liegt und auch nicht hinkommen darf: Geburtsdaten,
Lohndaten, Krankheitsgründe, RFID-Codes, Zugangsdaten zur Zeiterfassung, Daten
nicht freigeschalteter Mitarbeiter.

Wer den vServer übernimmt, sieht Urlaubstage und Arbeitszeiten von dreizehn
Menschen. Er kommt damit **nicht** in die Zeiterfassung: Er kennt ihre Adresse
nicht, hat keinen Zugang, und selbst mit dem Portal-Schlüssel könnte er nur
Anfragen **beantworten**, nie welche stellen. Der einzige Weg nach innen wäre
ein gefälschter Urlaubsantrag – und der landet auf dem Schreibtisch eines
Menschen, der ihn genehmigen muss.

## 11. Abnahme

Ein Durchgang gilt als bestanden, wenn diese Kette ohne Handgriff dazwischen
läuft:

1. Homepage: Kopplungscode erzeugen.
2. Zeiterfassung: Adresse und Code eintragen, koppeln – beide Seiten zeigen
   „verbunden".
3. Zeiterfassung: einen Mitarbeiter freischalten, Kennung und Aktivierungscode
   drucken.
4. Abgleich läuft – der Mitarbeiter steht auf der Homepage.
5. Portal: mit Kennung und Code aktivieren, Passwort setzen, anmelden.
6. Portal: Resturlaub und Stundensaldo stimmen mit dem Backend überein.
7. Portal: Urlaub beantragen – Zustand `wartet`.
8. Nach dem nächsten Abgleich: Der Antrag steht in der Zeiterfassung unter
   „Urlaubsanträge" und im Portal auf `offen`.
9. Backend: genehmigen. Nach dem nächsten Abgleich steht im Portal
   `genehmigt`, und der Resturlaub ist um die Tage kleiner.
10. Firmenserver abschalten: Das Portal zeigt weiter die alten Zahlen **mit
    dem Hinweis**, wie alt sie sind, und nimmt Anträge weiter entgegen.
11. Freischaltung entziehen: Nach dem nächsten Abgleich ist der Mitarbeiter
    samt Konto und Zahlen von der Homepage verschwunden.
