#!/usr/bin/env python3
"""Isolierter Integrationstest: eigene MariaDB, Git-main, SSH und zwei Terminalkopien.
Benötigt PHP+pdo_mysql+zip, MariaDB, OpenSSH, Git, tar. Keine Systemdienste ändern.
Aufruf: python3 scripts/tests/wartung_integration.py [leerer Testordner]
"""
import http.cookiejar
import json
import os
import pwd
import re
import shutil
import socket
import subprocess
import sys
import tempfile
import time
import urllib.error
import urllib.parse
import urllib.request
from pathlib import Path

SRC = Path(__file__).resolve().parents[2]
LAB = Path(sys.argv[1]).resolve() if len(sys.argv) > 1 else Path(tempfile.mkdtemp(prefix='zeit-wartung-'))
LAB.mkdir(exist_ok=True, parents=True)
if any(LAB.iterdir()):
    raise RuntimeError('Der Testordner muss leer sein.')
SOCKET_DIR = tempfile.TemporaryDirectory(prefix='zeit-wartung-sock-')
SOCKET = str(Path(SOCKET_DIR.name) / 'db.sock')
PROCS, LOGS, RESULTS = [], [], []


def run(args, **kw):
    p = subprocess.run([str(x) for x in args], text=True, capture_output=True, **kw)
    if p.returncode:
        raise RuntimeError(f'{args[0]} exit {p.returncode}: {p.stdout[-2000:]} {p.stderr[-2000:]}')
    return p.stdout


def start(args, name, **kw):
    f = open(LAB / (name + '.log'), 'w'); LOGS.append(f)
    p = subprocess.Popen([str(x) for x in args], stdout=f, stderr=f, **kw); PROCS.append(p)
    return p


def port():
    with socket.socket() as s:
        s.bind(('127.0.0.1', 0)); return s.getsockname()[1]


def check(name, ok):
    RESULTS.append({'test': name, 'ok': bool(ok)})
    print(('PASS ' if ok else 'FAIL ') + name, flush=True)
    if not ok: raise AssertionError(name)


def sql(query, db=None):
    return run(['mariadb', '--no-defaults', '--socket=' + SOCKET, '-uroot', '-N', '-B'] + ([db] if db else []), input=query)


def writephp(path, data):
    text = json.dumps(data, ensure_ascii=False).replace('\\', '\\\\').replace("'", "\\'")
    Path(path).write_text("<?php return json_decode('" + text + "', true);\n")


def php(code, app='backend'):
    return run(['php', '-r', "require 'core/Autoloader.php';" + code], cwd=LAB / app)


def cli(action, app='backend', data=None):
    return json.loads(run(['php', LAB / app / 'scripts/wartung.php', action], input=json.dumps(data) if data else None))


def config(app):
    return json.loads(php("echo json_encode(require 'config/wartung.local.php');", app))


def changeconfig(app, **kw):
    c = config(app); c.update(kw); writephp(LAB / app / 'config/wartung.local.php', c)


def job(action, commit=''):
    php("WartungAuftrag::einreichen(" + repr(action) + ',' + repr(commit) + ',1);')
    cli('verarbeiten')
    return json.loads((LAB / 'backend-status/status.json').read_text())


def request(path, fields=None, opener=None):
    data = urllib.parse.urlencode(fields).encode() if fields is not None else None
    try:
        r = (opener or urllib.request.build_opener()).open(urllib.request.Request(URL + path, data=data), timeout=20)
    except urllib.error.HTTPError as e: r = e
    return r.status, r.read().decode()


def commit(message):
    run(['git', '-C', LAB / 'origin', 'add', '.'])
    run(['git', '-C', LAB / 'origin', '-c', 'user.name=Fixture', '-c', 'user.email=fixture@localhost', 'commit', '-m', message])
    return run(['git', '-C', LAB / 'origin', 'rev-parse', 'HEAD']).strip()


try:
    run(['mariadb-install-db', '--no-defaults', '--datadir=' + str(LAB / 'db'), '--auth-root-authentication-method=normal', '--skip-test-db'])
    start(['mariadbd', '--no-defaults', '--datadir=' + str(LAB / 'db'), '--socket=' + SOCKET, '--pid-file=' + str(LAB / 'db.pid'), '--skip-networking', '--innodb-buffer-pool-size=32M'], 'mariadb')
    for _ in range(100):
        try: sql('SELECT 1;'); break
        except RuntimeError: time.sleep(.1)
    sql('CREATE DATABASE wartung_haupt CHARACTER SET utf8mb4; CREATE DATABASE wartung_offline1 CHARACTER SET utf8mb4; CREATE DATABASE wartung_offline2 CHARACTER SET utf8mb4;')
    sql((SRC / 'sql/01_initial_schema.sql').read_text(), 'wartung_haupt')
    for _ in range(2): sql((SRC / 'sql/14_migration_wartungsrechte.sql').read_text(), 'wartung_haupt')
    check('Neuinstallation und zweimalige Rechtemigration', sql("SELECT COUNT(*) FROM recht WHERE code IN ('BACKUP_VERWALTEN','UPDATE_VERWALTEN');", 'wartung_haupt').strip() == '2')
    for i in (1, 2):
        sql((SRC / 'sql/offline_db_schema.sql').read_text(), 'wartung_offline' + str(i))
        sql(f"INSERT INTO db_injektionsqueue (sql_befehl, status, meta_mitarbeiter_id, meta_terminal_id) VALUES ('SELECT {i}', 'offen', 123, {i});", 'wartung_offline' + str(i))
    sql("INSERT INTO terminal (id,name,modus,aktiv) VALUES (1,'Testterminal 1','terminal',1),(2,'Testterminal 2','terminal',1);", 'wartung_haupt')
    sql("CREATE USER 'wartung_app'@'localhost' IDENTIFIED BY 'fixture'; GRANT ALL ON wartung_haupt.* TO 'wartung_app'@'localhost';")
    sql("INSERT INTO mitarbeiter (id, vorname,nachname,benutzername,passwort_hash,aktiv) VALUES (123,'Test','Person','probe','x',1); INSERT INTO mitarbeiter_hat_rolle (mitarbeiter_id,rolle_id) SELECT 123,id FROM rolle WHERE name='Chef';", 'wartung_haupt')
    shutil.copytree(SRC, LAB / 'origin', ignore=shutil.ignore_patterns('.git', 'config.local.php', 'wartung.local.php', 'geraet.local.php', '__pycache__'))
    run(['git', 'init', '-b', 'main', LAB / 'origin'])
    baseline = commit('synthetische Ausgangsversion')
    for app in ('backend', 'terminal1', 'terminal2'):
        run(['git', 'clone', LAB / 'origin', LAB / app])
        (LAB / (app + '-status')).mkdir(); (LAB / (app + '-backups')).mkdir()
        terminal = app != 'backend'; tid = int(app[-1]) if terminal else None
        main = {'dsn': 'mysql:unix_socket=' + SOCKET + ';dbname=wartung_haupt;charset=utf8mb4', 'user': 'wartung_app', 'pass': 'fixture'}
        offline = {'enabled': terminal, 'dsn': 'mysql:unix_socket=' + SOCKET + ';dbname=wartung_offline' + str(tid) + ';charset=utf8mb4', 'user': 'root', 'pass': ''}
        writephp(LAB / app / 'config/config.local.php', {'app': {'installation_typ': 'terminal' if terminal else 'backend', 'base_url': ''}, 'db': main, 'offline_db': offline, 'terminal': {'id': tid, 'rfid_ws': {'enabled': False}}})
        writephp(LAB / app / 'config/wartung.local.php', {'status_pfad': str(LAB / (app + '-status')), 'backup_pfad': str(LAB / (app + '-backups')), 'repository': str(LAB / 'origin'), 'db_admin': {'haupt': {'user': 'root', 'pass': ''}, 'offline': {'user': 'root', 'pass': ''}}, 'reserve_bytes': 1048576, 'zusatz_pfade': [], 'neustart_befehl': ['/usr/bin/true'], 'terminals': {}})
        (LAB / app / 'public/uploads/lokal.txt').write_text(app + ' lokale Datei')
        cli('initialisieren', app)
    check('Alle drei Installationen mit eigenem Versionsstand initialisiert', all((LAB / (app + '-status/version.json')).exists() for app in ('backend', 'terminal1', 'terminal2')))
    # Echte, ausschließlich lokale SSH-Verbindungen. Keine Systemkonfiguration anfassen.
    run(['ssh-keygen', '-q', '-t', 'ed25519', '-N', '', '-f', LAB / 'hostkey'])
    run(['ssh-keygen', '-q', '-t', 'ed25519', '-N', '', '-f', LAB / 'clientkey'])
    sshport = port(); user = pwd.getpwuid(os.getuid()).pw_name
    (LAB / 'sshd_config').write_text(f'Port {sshport}\nListenAddress 127.0.0.1\nHostKey {LAB}/hostkey\nPidFile {LAB}/sshd.pid\nAuthorizedKeysFile {LAB}/clientkey.pub\nStrictModes no\nPasswordAuthentication no\nKbdInteractiveAuthentication no\nUsePAM no\nPermitRootLogin yes\nSubsystem sftp internal-sftp\n')
    start(['/usr/sbin/sshd', '-D', '-e', '-f', LAB / 'sshd_config'], 'ssh')
    time.sleep(.3)
    keys = run(['ssh-keyscan', '-p', str(sshport), '127.0.0.1']); (LAB / 'known_hosts').write_text(keys)
    targets = {str(i): {'ssh': user + '@127.0.0.1', 'port': sshport, 'app_pfad': str(LAB / ('terminal' + str(i))), 'status_pfad': str(LAB / ('terminal' + str(i) + '-status')), 'backup_pfad': str(LAB / ('terminal' + str(i) + '-backups'))} for i in (1, 2)}
    changeconfig('backend', terminals=targets, ssh_schluessel=str(LAB / 'clientkey'), ssh_known_hosts=str(LAB / 'known_hosts'))
    status = job('pruefen')
    check('main ohne neue Commits meldet keinen Updatebedarf', status['zustand'] == 'erfolgreich' and not json.loads((LAB / 'backend-status/angebot.json').read_text())['verfuegbar'])
    status = job('backup')
    check('Backup über echte SSH-Verbindungen für Backend und zwei Terminals', status['zustand'] == 'erfolgreich')
    backup = Path(status['backup'])
    for i in (1, 2): check(f'Terminal-{i}-Backup am Backend prüfbar', (backup / 'terminals' / str(i) / 'offline.sql').exists())
    # Restore in separate Dateibäume und Datenbanken: keinerlei Produktionsdaten.
    (LAB / 'restore').mkdir()
    run(['tar', '-xzf', backup / 'dateien.tar.gz', '-C', LAB / 'restore'])
    restored = LAB / 'restore' / str(LAB / 'backend').lstrip('/')
    check('Dateisicherung stellt lokale Konfiguration und Upload wieder her', (restored / 'config/config.local.php').read_bytes() == (LAB / 'backend/config/config.local.php').read_bytes() and (restored / 'public/uploads/lokal.txt').read_text() == 'backend lokale Datei')
    sql('CREATE DATABASE wartung_restore CHARACTER SET utf8mb4; CREATE DATABASE wartung_queue_restore CHARACTER SET utf8mb4;')
    sql((backup / 'haupt.sql').read_text(), 'wartung_restore')
    sql((backup / 'terminals/1/offline.sql').read_text(), 'wartung_queue_restore')
    check('SQL-Restore erhält Mitarbeiter und unbearbeitete Offline-Queue', sql('SELECT COUNT(*) FROM mitarbeiter WHERE id=123;', 'wartung_restore').strip() == '1' and sql('SELECT meta_mitarbeiter_id,meta_terminal_id FROM db_injektionsqueue;', 'wartung_queue_restore').strip() == '123\t1')
    # Web-UI und Rechte mit realen Sessions und HTTP.
    httpport = port(); URL = f'http://127.0.0.1:{httpport}'
    sessions = LAB / 'sessions'; sessions.mkdir()
    start(['php', '-d', 'session.save_path=' + str(sessions), '-d', 'error_reporting=32767', '-S', f'127.0.0.1:{httpport}', '-t', LAB / 'backend/public'], 'http')
    time.sleep(.3)
    check('Wartungsseite fordert Anmeldung', 'Passwort' in request('/index.php?seite=wartung')[1])
    sid = 'wartungtest123456789012345'
    run(['php', '-r', f"session_save_path('{sessions}'); session_id('{sid}'); session_start(); $_SESSION['auth_mitarbeiter_id']=123; session_write_close();"])
    opener = urllib.request.build_opener(); opener.addheaders = [('Cookie', 'PHPSESSID=' + sid)]
    code, page = request('/index.php?seite=wartung', opener=opener)
    check('Berechtigtes Backend zeigt Check-, Backup- und Statusoberfläche', code == 200 and 'Nach Updates suchen' in page and 'Backup erstellen' in page)
    code, page = request('/index.php?seite=wartung', {'aktion': 'backup', 'csrf_token': 'falsch'}, opener)
    check('Ungültiges CSRF erzeugt keinen Auftrag', not (LAB / 'backend-status/auftraege/auftrag.json').exists() and 'Formularsitzung' in page)
    token = re.search(r'name="csrf_token"[^>]*value="([^"]+)"', page).group(1)
    request('/index.php?seite=wartung', {'aktion': 'pruefen', 'csrf_token': token}, opener)
    check('Gültiges Formular stellt Auftrag in die Queue', (LAB / 'backend-status/auftraege/auftrag.json').exists())
    duplicate = False
    try: php("WartungAuftrag::einreichen('backup','',123);")
    except RuntimeError: duplicate = True
    check('Parallele Webaufträge werden abgewiesen', duplicate)
    cli('verarbeiten')
    # Separate Berechtigungen ohne Superuser: Backup darf kein Update anfordern.
    sql("INSERT INTO mitarbeiter (id,vorname,nachname,benutzername,passwort_hash,aktiv) VALUES (124,'Backup','Person','backup','x',1); INSERT INTO rolle (name,ist_superuser) VALUES ('Testbackup',0); INSERT INTO rolle_hat_recht (rolle_id,recht_id) SELECT ro.id,re.id FROM rolle ro JOIN recht re ON re.code='BACKUP_VERWALTEN' WHERE ro.name='Testbackup'; INSERT INTO mitarbeiter_hat_rolle (mitarbeiter_id,rolle_id) SELECT 124,id FROM rolle WHERE name='Testbackup';", 'wartung_haupt')
    sid2 = 'wartungbackup123456789012'
    run(['php', '-r', f"session_save_path('{sessions}'); session_id('{sid2}'); session_start(); $_SESSION['auth_mitarbeiter_id']=124; session_write_close();"])
    limited = urllib.request.build_opener(); limited.addheaders = [('Cookie', 'PHPSESSID=' + sid2)]
    _, page2 = request('/index.php?seite=wartung', opener=limited)
    csrf2 = re.search(r'name="csrf_token"[^>]*value="([^"]+)"', page2).group(1)
    denied, _ = request('/index.php?seite=wartung', {'aktion':'pruefen','csrf_token':csrf2}, limited)
    check('Backuprecht erlaubt weder Prüfung noch Update', denied == 403 and not (LAB/'backend-status/auftraege/auftrag.json').exists())
    # Fehlende Dump-/Kontenrechte dürfen keinen erfolgreichen Backupbeleg erzeugen.
    admin = config('backend')['db_admin']
    changeconfig('backend', db_admin={'haupt': {'user':'wartung_app','pass':'fixture'}})
    status = job('backup')
    check('Fehlgeschlagener SQL-Dump verhindert Erfolg und hebt Vorbereitungssperren auf', status['zustand']=='fehlgeschlagen' and not (LAB/'backend-status/pause.json').exists())
    changeconfig('backend', db_admin=admin)
    # Update mit echter zentraler und Offline-Migration.
    (LAB / 'origin/updates/teststand.txt').write_text('Version 2\n')
    (LAB / 'origin/sql/15_migration_test_haupt.sql').write_text('CREATE TABLE IF NOT EXISTS wartung_test (id INT PRIMARY KEY); INSERT IGNORE INTO wartung_test VALUES (1);\n')
    (LAB / 'origin/sql/16_migration_test_offline.sql').write_text('ALTER TABLE db_injektionsqueue ADD COLUMN IF NOT EXISTS wartung_test INT NULL;\n')
    manifest_path = LAB / 'origin/updates/migrationen.json'; manifest = json.loads(manifest_path.read_text())
    manifest['migrationen'] += [{'datei': 'sql/15_migration_test_haupt.sql', 'ziel': 'haupt', 'pruefung': 'SELECT COUNT(*) = 1 FROM wartung_test'}, {'datei': 'sql/16_migration_test_offline.sql', 'ziel': 'offline', 'pruefung': "SELECT COUNT(*) = 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='db_injektionsqueue' AND column_name='wartung_test'"}]
    manifest_path.write_text(json.dumps(manifest))
    target = commit('Testupdate mit zwei Migrationen')
    status = job('pruefen'); offer = json.loads((LAB / 'backend-status/angebot.json').read_text())
    check('Prüfung zeigt main-Commit und beide DB-Ziele ohne Installation', status['zustand'] == 'erfolgreich' and offer['commit'] == target and len(offer['migrationen']) == 2 and not (LAB / 'backend/updates/teststand.txt').exists())
    before = {app: (LAB / app / 'config/config.local.php').read_bytes() for app in ('backend', 'terminal1', 'terminal2')}
    status = job('update', target)
    check('Gesamtes Update mit zwei Terminals erfolgreich', status['zustand'] == 'erfolgreich')
    check('Alle Geräte haben exakt denselben neuen Commit', all(json.loads((LAB / (a + '-status/version.json')).read_text())['commit'] == target for a in before))
    check('Gerätekonfiguration und lokale Uploads bleiben unverändert', all((LAB / a / 'config/config.local.php').read_bytes() == before[a] and (LAB / a / 'public/uploads/lokal.txt').read_text() == a + ' lokale Datei' for a in before))
    check('Zentrale Migration und lokale Migrationen durchgeführt; Queue erhalten', sql('SELECT COUNT(*) FROM wartung_test;', 'wartung_haupt').strip() == '1' and all(sql('SELECT COUNT(*) FROM db_injektionsqueue WHERE meta_mitarbeiter_id=123 AND wartung_test IS NULL;', 'wartung_offline' + str(i)).strip() == '1' for i in (1, 2)))
    check('Wartungssperren nach Gesamterfolg aufgehoben', all(not (LAB / (a + '-status/pause.json')).exists() for a in before))
    # Dateimanifest lehnt beschädigte Backups und externe Linkziele ab.
    dump = backup/'haupt.sql'; original_dump=dump.read_bytes(); dump.write_bytes(original_dump+b'changed')
    rejected=False
    try: php("WartungBackup::pruefen("+repr(str(backup))+");")
    except RuntimeError: rejected=True
    check('Beschädigte Sicherung wird anhand der Prüfsumme erkannt',rejected)
    dump.write_bytes(original_dump)
    (LAB/'extern.txt').write_text('externes Testziel')
    (LAB/'backend/externer-link').symlink_to(LAB/'extern.txt')
    status=job('backup')
    check('Nicht erfasste externe Symlinkziele erzeugen keinen vollständigen Backupstatus', status['zustand']=='fehlgeschlagen' and 'Linkziel' in status['protokoll'][-1]['text'])
    (LAB/'backend/externer-link').unlink()
    # Fehler vor und nach destruktivem Schritt.
    old = config('backend')['terminals']; broken = dict(old); broken['2'] = dict(old['2'], port=port())
    changeconfig('backend', terminals=broken)
    status = job('backup')
    check('Nicht erreichbares Terminal verhindert falschen Gesamterfolg', status['zustand'] == 'fehlgeschlagen' and all(not (LAB / (a + '-status/pause.json')).exists() for a in before))
    changeconfig('backend', terminals=old, reserve_bytes=10**18)
    status = job('backup')
    check('Speichermangel bricht vor Sicherung und Installation ab', status['zustand'] == 'fehlgeschlagen' and 'Speicher' in status['protokoll'][-1]['text'])
    changeconfig('backend', reserve_bytes=1048576)
    (LAB / 'backend/updates/teststand.txt').write_text('lokale Abweichung')
    status = job('pruefen')
    check('Lokale Quellcodeänderung wird nicht überschrieben', status['zustand'] == 'fehlgeschlagen' and 'Lokale Änderung' in status['protokoll'][-1]['text'])
    status = job('backup')
    check('Separater Backupknopf sichert auch lokale Quellcodeänderungen', status['zustand']=='erfolgreich')
    (LAB / 'backend/updates/teststand.txt').write_text('Version 2\n')
    # Manipulierte alte Migrationen und unbekannte SQL-Änderungen blockieren Updates.
    historic = LAB/'origin/sql/14_migration_wartungsrechte.sql'; saved_sql=historic.read_text()
    historic.write_text(saved_sql+'\n-- unerlaubt geändert\n'); commit('Test alte Migration verändert')
    status=job('pruefen')
    check('Veränderte historische Migration wird abgewiesen', status['zustand']=='fehlgeschlagen')
    historic.write_text(saved_sql); commit('Test historische Migration wiederhergestellt')
    (LAB/'origin/sql/unbekannt.sql').write_text('SELECT 1;'); commit('Test unbekanntes SQL')
    status=job('pruefen')
    check('SQL ohne Migrationsvertrag wird abgewiesen', status['zustand']=='fehlgeschlagen')
    (LAB/'origin/sql/unbekannt.sql').unlink(); commit('Test unbekanntes SQL entfernt')
    (LAB / 'origin/sql/17_migration_test_fehler.sql').write_text('CREATE TABLE wartung_teilstand (id INT); ABSICHTLICH UNGUELTIG;\n')
    manifest['migrationen'].append({'datei': 'sql/17_migration_test_fehler.sql', 'ziel': 'haupt', 'pruefung': 'SELECT 1'})
    manifest_path.write_text(json.dumps(manifest)); bad = commit('Test fehlerhafte Migration')
    check('Fehlerpaket ist als Update erkannt', job('pruefen')['zustand'] == 'erfolgreich')
    status = job('update', bad)
    check('Migrationsfehler nach DDL bleibt gesperrt und besitzt Backup', status['zustand'] == 'fehlgeschlagen' and status['gesperrt_pruefen'] and Path(status['backup']).exists() and all((LAB / (a + '-status/pause.json')).exists() for a in before))
    code, body = request('/index.php?seite=dashboard')
    check('Backend weist Buchungen während Wartung mit 503 ab', code == 503 and 'Wartung läuft' in body)
    code, body = request('/index.php?seite=wartung', opener=opener)
    check('Berechtigter Benutzer sieht Fehlerstatus trotz gesperrter DB-Abläufe', code == 503 and 'fehlgeschlagen' in body)
    for i in (1,2):
        tport=port()
        start(['php','-d','session.save_path='+str(sessions),'-S',f'127.0.0.1:{tport}','-t',LAB/('terminal'+str(i))/'public'], 'terminal-http-'+str(i))
        time.sleep(.2)
        old_url=URL; URL=f'http://127.0.0.1:{tport}'
        tcode,tbody=request('/terminal.php'); URL=old_url
        check(f'Terminal {i} blockiert Buchungen bei unvollständiger Installation',tcode==503 and 'Wartung läuft' in tbody)
    sql("DROP USER 'wartung_app'@'localhost';")
    sql((backup/'haupt-benutzer.sql').read_text())
    check('Benutzersicherung stellt Passwort und Grants wieder her', php("$c=require 'config/config.local.php'; echo WartungDateien::pdo($c['db'])->query('SELECT COUNT(*) FROM mitarbeiter WHERE id=123')->fetchColumn();").strip()=='1')
    saved = (LAB / 'backend-status/status.json').read_bytes(); cli('verarbeiten')
    check('Dienst wiederholt fehlgeschlagene Migration nicht und erhält Fehlerprotokoll', (LAB / 'backend-status/status.json').read_bytes() == saved)
finally:
    for p in reversed(PROCS):
        if p.poll() is None: p.terminate()
    for p in reversed(PROCS):
        try: p.wait(timeout=10)
        except subprocess.TimeoutExpired: p.kill(); p.wait()
    for f in LOGS: f.close()
    SOCKET_DIR.cleanup()
    (LAB / 'ergebnis.json').write_text(json.dumps({'tests': RESULTS, 'prozesse_beendet': all(p.poll() is not None for p in PROCS)}, indent=2, ensure_ascii=False))
    print('Prüfergebnis:', LAB / 'ergebnis.json', flush=True)
