#!/usr/bin/env python3
"""Eigene MariaDB, normale Kopplung, automatische Dienste, zwei Terminalkopien.
Keine Systemdienste verändern. Die Systemd-Takte und der Webserver-Neustart
werden im Test durch lokale Prozesse bzw. true ersetzt. Keine Wartungsdatei
wird vom Benutzer angelegt. Aufruf: python3 scripts/tests/wartung_integration.py
"""
import hashlib, http.cookiejar, json, os, pwd, re, shutil, signal, socket
import subprocess, sys, tempfile, time, urllib.request, urllib.parse, urllib.error
from pathlib import Path
SRC=Path(__file__).resolve().parents[2]
LAB=Path(sys.argv[1]).resolve() if len(sys.argv)>1 else Path(tempfile.mkdtemp(prefix='zeit-auto-'))
LAB.mkdir(parents=True,exist_ok=True)
if any(LAB.iterdir()): raise RuntimeError('Testordner muss leer sein.')
SOCKDIR=tempfile.TemporaryDirectory(prefix='zeit-auto-sock-'); SOCK=str(Path(SOCKDIR.name)/'db.sock')
PROCS=[]; LOGS=[]; RESULTS=[]; STATES={}; URLS={}; AGENTS={}
(LAB/'ini').mkdir()
(LAB/'ini/99-test.ini').write_text('pdo_mysql.default_socket='+SOCK+'\nmysqli.default_socket='+SOCK+'\nerror_reporting=32767\n')
# Alle PHP-Unterprozesse benutzen ausschließlich den privaten Testsocket.
os.environ['PHP_INI_SCAN_DIR']=os.environ.get('PHP_INI_SCAN_DIR','')+':'+str(LAB/'ini')

def run(args,**kw):
 p=subprocess.run([str(x) for x in args],capture_output=True,text=True,**kw)
 if p.returncode: raise RuntimeError(f'{args[0]} exit {p.returncode}: {p.stdout[-2500:]} {p.stderr[-2500:]}')
 return p.stdout

def start(args,name,**kw):
 f=open(LAB/(name+'.log'),'w');LOGS.append(f)
 p=subprocess.Popen([str(x) for x in args],stdout=f,stderr=f,**kw);PROCS.append(p);return p

def check(name,ok):
 RESULTS.append({'test':name,'ok':bool(ok)});print(('PASS ' if ok else 'FAIL ')+name,flush=True)
 if not ok: raise AssertionError(name)

def sql(q,db=None): return run(['mariadb','--no-defaults','--socket='+SOCK,'-uroot','-N','-B']+([db] if db else []),input=q)
def php(code,app='backend'): return run(['php','-r',"require 'core/Autoloader.php';"+code],cwd=LAB/app)
def cli(app='backend'): return json.loads(run(['php',LAB/app/'scripts/wartung.php','dienst']))
def state(app='backend'): return STATES[app]
def read(name,app='backend'): return json.loads((state(app)/name).read_text())
def port():
 with socket.socket() as s: s.bind(('127.0.0.1',0));return s.getsockname()[1]
def writephp(path,d): Path(path).write_text("<?php return json_decode('"+json.dumps(d).replace('\\','\\\\').replace("'","\\'")+"',true);\n")
def request(path,fields=None,opener=None,app='backend'):
 req=urllib.request.Request(URLS[app]+path,data=urllib.parse.urlencode(fields).encode() if fields is not None else None)
 try:r=(opener or urllib.request.build_opener()).open(req,timeout=30)
 except urllib.error.HTTPError as e:r=e
 return r.status,r.read().decode()
def waitfor(fn,seconds=120):
 end=time.time()+seconds
 while time.time()<end:
  if fn():return
  time.sleep(.15)
 raise AssertionError('Zeitlimit beim Warten')
def commit(msg):
 run(['git','-C',LAB/'origin','add','.']);run(['git','-C',LAB/'origin','-c','user.name=Fixture','-c','user.email=fixture@localhost','commit','-m',msg]);return run(['git','-C',LAB/'origin','rev-parse','HEAD']).strip()
def agent(app):
 p=start([sys.executable,LAB/'takt.py',LAB/app/'scripts/wartung.php'],'dienst-'+app);AGENTS[app]=p;return p
def stopagent(app):
 p=AGENTS[app];p.terminate();p.wait(timeout=30)
def job(action,commit=''):
 old=read('status.json').get('id') if (state()/'status.json').exists() else None
 php("WartungAuftrag::einreichen("+repr(action)+','+repr(commit)+',123);')
 waitfor(lambda:(state()/'status.json').exists() and read('status.json').get('id')!=old and read('status.json').get('zustand') in ('erfolgreich','fehlgeschlagen','unterbrochen'),180)
 return read('status.json')
def systemchange(app,**kw):
 laufend=app in AGENTS and AGENTS[app].poll() is None
 if laufend:
  waitfor(lambda:not (state(app)/'aktiv.json').exists())
  stopagent(app)
 c=read('system.json',app);c.update(kw);(state(app)/'system.json').write_text(json.dumps(c))
 if laufend:agent(app)

try:
 run(['mariadb-install-db','--no-defaults','--datadir='+str(LAB/'db'),'--auth-root-authentication-method=normal','--skip-test-db'])
 start(['mariadbd','--no-defaults','--datadir='+str(LAB/'db'),'--socket='+SOCK,'--pid-file='+str(LAB/'db.pid'),'--skip-networking','--innodb-buffer-pool-size=32M'],'mariadb')
 for i in range(100):
  try:sql('SELECT 1');break
  except RuntimeError:time.sleep(.1)
 sql('CREATE DATABASE wartung_haupt CHARACTER SET utf8mb4;CREATE DATABASE wartung_offline1 CHARACTER SET utf8mb4;CREATE DATABASE wartung_offline2 CHARACTER SET utf8mb4;')
 sql((SRC/'sql/01_initial_schema.sql').read_text(),'wartung_haupt')
 for _ in range(2):
  sql((SRC/'sql/14_migration_wartungsrechte.sql').read_text(),'wartung_haupt');sql((SRC/'sql/15_migration_wartung_kopplung.sql').read_text(),'wartung_haupt')
 check('Neuinstallation und Migrationen 14/15 jeweils zweimal erfolgreich',True)
 # Normaler Backendzugang wie vom Installer erzeugt, kein besonderer Wartungszugang.
 sql("DROP USER IF EXISTS ''@'localhost';CREATE USER 'wartung_app'@'localhost' IDENTIFIED BY 'fixture';GRANT ALL ON wartung_haupt.* TO 'wartung_app'@'localhost' WITH GRANT OPTION;GRANT CREATE USER ON *.* TO 'wartung_app'@'localhost';")
 sql("INSERT INTO terminal(id,name,modus,aktiv) VALUES(1,'Halle 1','terminal',1),(2,'Halle 2','terminal',1);INSERT INTO config(schluessel,wert,typ) VALUES('terminal_db_host_extern','localhost','string');INSERT INTO mitarbeiter(id,vorname,nachname,benutzername,passwort_hash,aktiv) VALUES(123,'Test','Person','probe','x',1);INSERT INTO mitarbeiter_hat_rolle(mitarbeiter_id,rolle_id) SELECT 123,id FROM rolle WHERE name='Chef';",'wartung_haupt')
 for i in (1,2):
  sql((SRC/'sql/offline_db_schema.sql').read_text(),'wartung_offline'+str(i))
  sql(f"INSERT INTO db_injektionsqueue(sql_befehl,status,meta_mitarbeiter_id,meta_terminal_id) VALUES('SELECT {i}','offen',123,{i});",'wartung_offline'+str(i))
 shutil.copytree(SRC,LAB/'origin',ignore=shutil.ignore_patterns('.git','config.local.php','wartung.local.php','geraet.local.php','__pycache__'))
 run(['git','init','-b','main',LAB/'origin']);baseline=commit('Automatischer Ausgangsstand')
 # Auch die DB-/Konfigurationsanlage der normalen Backendinstallation ausführen.
 run(['git','clone',LAB/'origin',LAB/'neuinstallation'])
 run(['php',LAB/'neuinstallation/scripts/backend_einrichten.php',pwd.getpwuid(os.getuid()).pw_name])
 configneu=(LAB/'neuinstallation/config/config.local.php').read_bytes()
 run(['php',LAB/'neuinstallation/scripts/backend_einrichten.php',pwd.getpwuid(os.getuid()).pw_name])
 check('Backendinstaller legt Datenbank und Konfiguration automatisch und wiederholbar an',configneu==(LAB/'neuinstallation/config/config.local.php').read_bytes() and php("$c=require 'config/config.local.php';echo WartungDateien::pdo($c['db'])->query('SELECT COUNT(*) FROM wartung_system')->fetchColumn();",'neuinstallation')=='0')
 (LAB/'sessions').mkdir()
 for app in ('backend','terminal1','terminal2'):
  run(['git','clone',LAB/'origin',LAB/app])
  if app=='backend':
   writephp(LAB/app/'config/config.local.php',{'app':{'installation_typ':'backend','base_url':''},'db':{'host':'localhost','dbname':'wartung_haupt','user':'wartung_app','pass':'fixture'},'offline_db':{'enabled':False}})
  else:
   writephp(LAB/app/'config/geraet.local.php',{'offline_db':{'enabled':True,'host':'localhost','dbname':'wartung_offline'+app[-1],'user':'root','pass':''},'terminal':{'rfid_ws':{'enabled':False}}})
  # Derselbe Einrichtungseinstieg wie in den normalen Installern. Kein Aufruf initialisieren.
  run(['php',LAB/app/'scripts/wartung_einrichten.php',pwd.getpwuid(os.getuid()).pw_name,'apache2'])
  STATES[app]=Path(php('echo WartungSystem::pfad(getcwd());',app))
  # Ausschließlich Testadapter: keinen echten Webserver/Systemdienst neu starten.
  systemchange(app,neustart_befehl=['/usr/bin/true'],reserve_bytes=1048576,antwort_timeout=20)
  (LAB/app/'public/uploads/lokal.txt').write_text(app+' lokal')
  hp=port();URLS[app]=f'http://127.0.0.1:{hp}'
  start(['php','-d','session.save_path='+str(LAB/'sessions'),'-S',f'127.0.0.1:{hp}','-t',LAB/app/'public'],'http-'+app)
 check('Normale Einrichtung erzeugt automatisch Ordner, Schlüssel und Backendversionsstand', (state()/'version.json').exists() and (state()/'privat/signatur.key').exists())
 keyvorher=(state()/'privat/signatur.key').read_bytes();versionvorher=(state()/'version.json').read_bytes()
 run(['php',LAB/'backend/scripts/wartung_einrichten.php',pwd.getpwuid(os.getuid()).pw_name,'apache2'])
 check('Wiederholte Einrichtung erhält Schlüssel und installierten Versionsstand',keyvorher==(state()/'privat/signatur.key').read_bytes() and versionvorher==(state()/'version.json').read_bytes())
 check('Ungekoppelte Terminals benötigen keine Wartungskonfiguration',not (state('terminal1')/'version.json').exists() and all(not (LAB/a/'config/wartung.local.php').exists() for a in STATES))
 # Scheduler ersetzt nur Systemd im Labor und lädt bei jedem Takt frischen PHP-Code.
 (LAB/'takt.py').write_text('''import subprocess,sys,time,signal
running=True
child=None
def stop(*args):
 global running
 running=False
 if child and child.poll() is None:child.terminate()
signal.signal(signal.SIGTERM,stop)
while running:
 child=subprocess.Popen(['php',sys.argv[1],'dienst'],stdout=subprocess.PIPE,stderr=subprocess.PIPE,text=True)
 out,err=child.communicate()
 if child.returncode:print(out,err,flush=True)
 time.sleep(.2)
''')
 for app in STATES:agent(app)
 time.sleep(.5)
 # Kopplung durch dasselbe Formular wie am echten Gerät.
 for i in (1,2):
  app='terminal'+str(i)
  code=php(f'echo TerminalKopplungService::getInstanz()->erzeugeCode({i});').strip()
  opener=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
  _,page=request('/terminal.php',opener=opener,app=app)
  csrf=re.search(r'name="csrf_token"[^>]*value="([^"]+)"',page).group(1)
  _,page=request('/terminal.php',{'serveradresse':URLS['backend'],'kopplungscode':code,'csrf_token':csrf},opener,app)
  check(f'Terminal {i} koppelt sich über das normale Formular', (LAB/app/'config/config.local.php').exists())
  waitfor(lambda:(state(app)/'version.json').exists())
 waitfor(lambda:sql('SELECT COUNT(*) FROM wartung_geraet WHERE gesehen IS NOT NULL','wartung_haupt').strip()=='2')
 check('Beide Terminals melden sich ohne SSH oder zusätzliche Geräteliste automatisch bereit',True)
 # Rechte auf Wartungsviews müssen auf den eigenen DB-Benutzer begrenzt sein.
 visibility=json.loads(php("$c=require 'config/config.local.php';$p=WartungDateien::pdo($c['db']);$r=['ids'=>$p->query('SELECT terminal_id FROM wartung_mein_geraet')->fetchAll(PDO::FETCH_COLUMN)];foreach(['SELECT * FROM wartung_geraet','UPDATE wartung_mein_befehl SET anfrage=anfrage','SELECT passwort_hash FROM mitarbeiter LIMIT 0'] as $s){try{$p->exec($s);$r['verboten'][]=false;}catch(Throwable $e){$r['verboten'][]=true;}}echo json_encode($r);",'terminal1'))
 check('Terminal sieht nur sich selbst und kann keine Befehle oder Passwortdaten ändern',visibility['ids']==[1] and all(visibility['verboten']))
 # UI als berechtigter Mitarbeiter.
 sid='wartungauto123456789012';run(['php','-r',f"session_save_path('{LAB}/sessions');session_id('{sid}');session_start();$_SESSION['auth_mitarbeiter_id']=123;session_write_close();"])
 browser=urllib.request.build_opener();browser.addheaders=[('Cookie','PHPSESSID='+sid)]
 code,page=request('/index.php?seite=wartung',opener=browser)
 check('Bedienoberfläche ist ohne Wartungssetup bedienbar',code==200 and 'Backup erstellen' in page and 'Nach Updates suchen' in page and 'docs/wartung_betrieb.md' not in page)
 (LAB/'ansicht-bereit.html').write_text(page)
 _,page=request('/index.php?seite=wartung',{'aktion':'backup','csrf_token':'falsch'},browser)
 check('CSRF-Schutz bleibt wirksam',not (state()/'auftraege/auftrag.json').exists() and 'Formularsitzung' in page)
 csrf=re.search(r'name="csrf_token"[^>]*value="([^"]+)"',page).group(1)
 check('Prüfung von main benötigt keine zusätzliche Gitkonfiguration',job('pruefen')['zustand']=='erfolgreich' and not read('angebot.json')['verfuegbar'])
 # Backup explizit durch den sichtbaren Knopf anfordern.
 old=read('status.json')['id'];request('/index.php?seite=wartung',{'aktion':'backup','csrf_token':csrf},browser)
 waitfor(lambda:read('status.json').get('id')!=old and read('status.json').get('zustand') in ('erfolgreich','fehlgeschlagen'),180)
 result=read('status.json');check('Backupknopf sichert Backend und zwei normal gekoppelte Terminals',result['zustand']=='erfolgreich')
 backup=Path(result['backup'])
 for i in (1,2):check(f'Offline-Sicherung von Terminal {i} liegt am Backend',(backup/'terminals'/str(i)/'offline.sql').exists())
 check('Dateitransport wird nach Erfolg aufgeräumt',sql('SELECT COUNT(*) FROM wartung_dateiteil','wartung_haupt').strip()=='0')
 (LAB/'restore').mkdir();run(['tar','-xzf',backup/'dateien.tar.gz','-C',LAB/'restore'])
 restored=LAB/'restore'/str(LAB/'backend').lstrip('/')
 check('Dateien, Konfiguration und Upload sind wiederherstellbar',(restored/'config/config.local.php').read_bytes()==(LAB/'backend/config/config.local.php').read_bytes() and (restored/'public/uploads/lokal.txt').read_text()=='backend lokal')
 sql('CREATE DATABASE wartung_restore;CREATE DATABASE wartung_queue_restore;')
 sql((backup/'haupt.sql').read_text(),'wartung_restore');sql((backup/'terminals/1/offline.sql').read_text(),'wartung_queue_restore')
 check('SQL-Restore enthält Mitarbeiter und offene Offline-Buchung',sql('SELECT COUNT(*) FROM mitarbeiter WHERE id=123','wartung_restore').strip()=='1' and sql('SELECT COUNT(*) FROM db_injektionsqueue WHERE meta_mitarbeiter_id=123','wartung_queue_restore').strip()=='1')
 (LAB/'beschaedigt').mkdir()
 shutil.copy(backup/'manifest.json',LAB/'beschaedigt/manifest.json')
 (LAB/'beschaedigt/dateien.tar.gz').write_bytes(b'kaputt')
 rejected=False
 try:php('WartungBackup::pruefen('+repr(str(LAB/'beschaedigt'))+');')
 except RuntimeError:rejected=True
 check('Beschädigte Sicherung wird nicht als gültig angenommen',rejected)
 # Dienst wirklich anhalten: abgelaufener Heartbeat lehnt neue Aufträge ab.
 stopagent('backend');heartbeat=read('dienst.json');heartbeat['zeit']=0;(state()/'dienst.json').write_text(json.dumps(heartbeat))
 blocked=False
 try:php("WartungAuftrag::einreichen('backup','',123);")
 except RuntimeError:blocked=True
 check('Gestoppter Dienst nimmt keinen neuen Auftrag an',blocked and not (state()/'auftraege/auftrag.json').exists())
 expired={'aktion':'backup','commit':'','mitarbeiter_id':123,'gueltig_bis':0}
 (state()/'auftraege/auftrag.json').write_text(json.dumps(expired));saved=read('status.json')['id'];agent('backend');waitfor(lambda:not (state()/'auftraege/auftrag.json').exists())
 check('Abgelaufener Auftrag wird nach Dienststart nicht heimlich ausgeführt',read('status.json')['id']==saved)
 # Manipulierter Datenbankbefehl besitzt keine Serversignatur und darf nicht laufen.
 user=sql('SELECT db_benutzer FROM terminal WHERE id=1','wartung_haupt').strip()
 sql("INSERT INTO wartung_befehl(id,db_benutzer,anfrage,signatur) VALUES('"+'a'*32+"','"+user+"','{}','ungueltig')",'wartung_haupt')
 waitfor(lambda:sql("SELECT zustand FROM wartung_befehl WHERE id='"+'a'*32+"'",'wartung_haupt').strip()=='erledigt')
 check('Unsignierter Updateauftrag wird abgewiesen', 'signiert' in sql("SELECT antwort FROM wartung_befehl WHERE id='"+'a'*32+"'",'wartung_haupt') and not (state('terminal1')/'pause.json').exists())
 sql('DELETE FROM wartung_befehl','wartung_haupt')
 # Neues main mit zentraler und lokaler Migration; alle Dateien kommen nur vom Backend.
 (LAB/'origin/updates/teststand.txt').write_text('Version 2\n')
 (LAB/'origin/sql/16_migration_test_haupt.sql').write_text('CREATE TABLE IF NOT EXISTS wartung_test(id INT PRIMARY KEY);INSERT IGNORE INTO wartung_test VALUES(1);')
 (LAB/'origin/sql/17_migration_test_offline.sql').write_text('ALTER TABLE db_injektionsqueue ADD COLUMN IF NOT EXISTS wartung_test INT NULL;')
 manifest_path=LAB/'origin/updates/migrationen.json';manifest=json.loads(manifest_path.read_text());manifest['migrationen'] += [{'datei':'sql/16_migration_test_haupt.sql','ziel':'haupt','pruefung':'SELECT COUNT(*)=1 FROM wartung_test'},{'datei':'sql/17_migration_test_offline.sql','ziel':'offline','pruefung':"SELECT COUNT(*)=1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='db_injektionsqueue' AND column_name='wartung_test'"}];manifest_path.write_text(json.dumps(manifest));target=commit('Update mit Datenbankänderungen')
 check('Updateprüfung erkennt zwei Datenbankmigrationen',job('pruefen')['zustand']=='erfolgreich' and len(read('angebot.json')['migrationen'])==2)
 _,page=request('/index.php?seite=wartung',opener=browser);check('Ein klarer Updateknopf und verständlicher DB-Hinweis','Jetzt aktualisieren' in page and 'Die Datenbank wird ebenfalls aktualisiert' in page)
 before={a:(LAB/a/'config/config.local.php').read_bytes() for a in STATES}
 csrf=re.search(r'name="csrf_token"[^>]*value="([^"]+)"',page).group(1)
 old=read('status.json')['id'];request('/index.php?seite=wartung',{'aktion':'update','commit':target,'csrf_token':csrf},browser)
 waitfor(lambda:(state()/'pause.json').exists())
 code,page=request('/index.php?seite=wartung',opener=browser);(LAB/'ansicht-fortschritt.html').write_text(page)
 check('Wartungsanzeige bleibt verständlich statt rohem JSON',code==503 and 'Fortschritt' in page and '<pre>' not in page)
 waitfor(lambda:read('status.json').get('id')!=old and read('status.json').get('zustand') in ('erfolgreich','fehlgeschlagen'),180)
 result=read('status.json');check('Ein Klick aktualisiert Backend und beide Terminals',result['zustand']=='erfolgreich')
 check('Alle Geräte haben denselben Commit',all(read('version.json',a)['commit']==target for a in STATES))
 check('Konfigurationen und Uploads bleiben erhalten',all((LAB/a/'config/config.local.php').read_bytes()==before[a] and (LAB/a/'public/uploads/lokal.txt').read_text()==a+' lokal' for a in STATES))
 check('Lokale Migrationen erhalten offene Offline-Buchungen',all(sql('SELECT COUNT(*) FROM db_injektionsqueue WHERE meta_mitarbeiter_id=123 AND wartung_test IS NULL','wartung_offline'+str(i)).strip()=='1' for i in (1,2)))
 check('Buchungen sind nach Gesamterfolg wieder freigegeben',all(not (state(a)/'pause.json').exists() for a in STATES))
 stopagent('terminal2');sql('UPDATE wartung_geraet SET gesehen=NULL WHERE terminal_id=2','wartung_haupt')
 check('Fehlendes Gerät wird mit Namen gemeldet',job('backup')['zustand']=='fehlgeschlagen' and 'Halle 2' in read('status.json')['protokoll'][-1]['text'])
 agent('terminal2');waitfor(lambda:sql('SELECT gesehen IS NOT NULL FROM wartung_geraet WHERE terminal_id=2','wartung_haupt').strip()=='1')
 systemchange('backend',reserve_bytes=10**18);check('Speichermangel verhindert Installation',job('backup')['zustand']=='fehlgeschlagen');systemchange('backend',reserve_bytes=1048576)
 # Teilweise DDL: keine Freigabe, kein automatisches Wiederholen.
 (LAB/'origin/sql/18_migration_test_fehler.sql').write_text('CREATE TABLE wartung_teilstand(id INT);ABSICHTLICH UNGUELTIG;')
 manifest['migrationen'].append({'datei':'sql/18_migration_test_fehler.sql','ziel':'haupt','pruefung':'SELECT 1'});manifest_path.write_text(json.dumps(manifest));bad=commit('Defekte Migration')
 check('Fehlerpaket wurde geprüft',job('pruefen')['zustand']=='erfolgreich')
 result=job('update',bad);check('Fehler nach DDL erhält Backup und alle Wartungssperren',result['zustand']=='fehlgeschlagen' and Path(result['backup']).exists() and all((state(a)/'pause.json').exists() for a in STATES))
 saved=(state()/'status.json').read_bytes();time.sleep(1)
 check('Fehlgeschlagene Migration wird nicht erneut ausgeführt',(state()/'status.json').read_bytes()==saved)
 for app in STATES:
  code,page=request('/terminal.php' if app!='backend' else '/index.php?seite=wartung',opener=browser if app=='backend' else None,app=app)
  check(app+' meldet den Abbruch und bleibt gesperrt',code==503 and 'Wartung unterbrochen' in page and 'Bitte kurz warten.' not in page)
 check('Keine zusätzliche Wartungskonfigurationsdatei auf einem Gerät',all(not (LAB/a/'config/wartung.local.php').exists() for a in STATES))
finally:
 for p in reversed(PROCS):
  if p.poll() is None:p.terminate()
 for p in reversed(PROCS):
  try:p.wait(timeout=15)
  except subprocess.TimeoutExpired:p.kill();p.wait()
 for f in LOGS:f.close()
 SOCKDIR.cleanup()
 (LAB/'ergebnis.json').write_text(json.dumps({'tests':RESULTS,'prozesse_beendet':all(p.poll() is not None for p in PROCS)},indent=2,ensure_ascii=False))
 print('Prüfergebnis:',LAB/'ergebnis.json',flush=True)
