#!/usr/bin/env python3
"""Eigene MariaDB/HTTP-Instanzen, nur Terminal-PHP zwei Tage zurückstellen.
Kerneluhr, Systemdienste, echte Daten und Projektkonfiguration bleiben unberührt.
Aufruf: python3 scripts/tests/terminal_buchung.py /tmp/leerer-testordner [--vorher <Git-Stand>]
--vorher 2bab763 belegt den alten Fehler; ohne Option prüft es die Arbeitskopie.
"""
import io
from pathlib import Path
import subprocess
import sys
import tarfile
import tempfile

REPO = Path(__file__).resolve().parents[2]
vorher = '--vorher' in sys.argv
quelle = REPO
stand = None
if vorher:
    index = sys.argv.index('--vorher')
    if index + 1 >= len(sys.argv): raise RuntimeError('--vorher benötigt einen Git-Stand.')
    revision = sys.argv[index + 1]
    stand = tempfile.TemporaryDirectory(prefix='zeit-vorher-')
    quelle = Path(stand.name)
    archiv = subprocess.run(['git', '-C', REPO, 'archive', revision], capture_output=True, check=True).stdout
    with tarfile.open(fileobj=io.BytesIO(archiv)) as tar: tar.extractall(quelle, filter='data')
original = (REPO/'scripts/tests/wartung_integration.py').read_text()
aufbau = original.split(' # UI als berechtigter Mitarbeiter.')[0]
aufbau = aufbau.replace('SRC=Path(__file__).resolve().parents[2]', 'SRC=Path('+repr(str(quelle))+')')
aufbau = aufbau.replace('def start(args,name,**kw):', '''def start(args,name,**kw):
 if name=='http-terminal1':kw['env']=dict(os.environ,LD_PRELOAD=str(LAB/'zeit-vorladung.so'),ZEIT_TEST_UHRVERSATZ='-172800')''')
aufbau = aufbau.replace("try:\n run(['mariadb-install-db'", "try:\n run(['cc','-shared','-fPIC','-O2','-o',LAB/'zeit-vorladung.so',"+repr(str(REPO/'scripts/tests/terminal_zeit_vorladung.c'))+",'-ldl'])\n run(['mariadb-install-db'",1)
aufbau = 'VORHER='+repr(vorher)+'\n'+aufbau
abschluss = original[original.rindex('\nfinally:'):]
probe = r'''
 sql("DELETE FROM db_injektionsqueue",'wartung_offline1')
 sql("UPDATE mitarbeiter SET rfid_code='04CAFE1234' WHERE id=123",'wartung_haupt')
 opener=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
 def token(page):return re.search(r'name="csrf_token"[^>]*value="([^"]+)"',page).group(1)
 def anmelden():
  _,page=request('/terminal.php?aktion=start',opener=opener,app='terminal1')
  return request('/terminal.php?aktion=start',{'csrf_token':token(page),'rfid_code':'04CAFE1234'},opener,'terminal1')[1]
 def buchen(typ,page):return request('/terminal.php?aktion='+typ,{'csrf_token':token(page),'rfid_code':'04CAFE1234'},opener,'terminal1')[1]
 lokal=run(['php','-r',"date_default_timezone_set('Europe/Berlin');echo date('Y-m-d');"],env=dict(os.environ,LD_PRELOAD=str(LAB/'zeit-vorladung.so'),ZEIT_TEST_UHRVERSATZ='-172800')).strip()
 heute=php("date_default_timezone_set('Europe/Berlin');echo date('Y-m-d');").strip()
 check('Nur lokaler PHP-Prozess des Terminals hat zwei Tage falsches Datum',lokal!=heute)
 page=anmelden()
 check('RFID-Anmeldung allein erzeugt keine Buchung',sql('SELECT COUNT(*) FROM zeitbuchung WHERE mitarbeiter_id=123','wartung_haupt').strip()=='0')
 page=buchen('kommen',page)
 check('Online-Kommen bestätigt Erfolg', 'Kommen gebucht um' in page)
 row=sql("SELECT DATE_FORMAT(zeitstempel,'%Y-%m-%d'),terminal_id,quelle FROM zeitbuchung WHERE mitarbeiter_id=123",'wartung_haupt').strip().split('\t')
 sid='zeitbuchungprobe123456';run(['php','-r',f"session_save_path('{LAB}/sessions');session_id('{sid}');session_start();$_SESSION['auth_mitarbeiter_id']=123;session_write_close();"])
 browser=urllib.request.build_opener();browser.addheaders=[('Cookie','PHPSESSID='+sid)]
 code,backend=request('/index.php?seite=zeit_heute',opener=browser)
 if VORHER:
  check('Fehler reproduziert: bestätigtes Kommen trägt falsches Terminaldatum',row[0]==lokal)
  check('Fehler reproduziert: Heutige Zeiten zeigt den bestätigten Stempel nicht',code==200 and 'Für dieses Datum sind keine Buchungen vorhanden.' in backend)
 else:
  # Signierter Wartungskanal muss dieselbe Zeitquelle nutzen, auch wenn die
  # lokale Agentuhr zwei Tage vorausgeht; abgelaufene Befehle bleiben verboten.
  stopagent('terminal1')
  for gueltig in (True,False):
   rpc=php("$p=Database::getInstanz()->getVerbindung();$id=bin2hex(random_bytes(16));$user=$p->query('SELECT db_benutzer FROM wartung_geraet WHERE terminal_id=1')->fetchColumn();$text=json_encode(['rpc'=>$id,'terminal_id'=>1,'bis'=>TerminalZeit::serverEpoche($p)"+(' +3600' if gueltig else ' -1')+",'anfrage'=>['aktion'=>'fixture']],JSON_THROW_ON_ERROR);$k=WartungSystem::konfig(getcwd(),true);openssl_sign($text,$sig,file_get_contents($k['status_pfad'].'/privat/signatur.key'),OPENSSL_ALGO_SHA256);$s=$p->prepare('INSERT INTO wartung_befehl(id,db_benutzer,anfrage,signatur) VALUES(?,?,?,?)');$s->execute([$id,$user,$text,base64_encode($sig)]);echo $id;").strip()
   agentcode="require 'core/Autoloader.php';$c=require 'config/config.local.php';$k=WartungSystem::konfig(getcwd(),true);$p=WartungDateien::pdo($c['db']);(new WartungKanal($p,$k,false))->agent(1,'fixture',fn($a)=>['ok'=>true]);"
   run(['php','-r',agentcode],cwd=LAB/'terminal1',env=dict(os.environ,LD_PRELOAD=str(LAB/'zeit-vorladung.so'),ZEIT_TEST_UHRVERSATZ='172800'))
   antwort=json.loads(sql("SELECT antwort FROM wartung_befehl WHERE id='"+rpc+"'",'wartung_haupt').strip())
   check('Gültiger signierter Auftrag bleibt bei falscher Agentuhr ausführbar' if gueltig else 'Abgelaufener signierter Auftrag bleibt trotz Zeitabgleich gesperrt',antwort.get('ok') is gueltig and (gueltig or 'Abgelaufene' in antwort.get('fehler','')))
  agent('terminal1')
  check('Neue Buchung trägt Backenddatum und Terminalzuordnung',row==[heute,'1','terminal'])
  check('Heutige Zeiten zeigt Kommen trotz falscher lokaler Terminaluhr',code==200 and '<td>kommen</td>' in backend and 'Für dieses Datum sind keine Buchungen vorhanden.' not in backend)
  _,uhr=request('/terminal.php?aktion=zeit',opener=opener,app='terminal1')
  zeit=json.loads(uhr)
  check('Uhr-Endpoint liefert zentrale UTC-Zeit und Anwendungszeitzone',set(zeit)=={'epoche','zeitzone'} and abs(zeit['epoche']-int(sql('SELECT UNIX_TIMESTAMP()','wartung_haupt').strip()))<3 and zeit['zeitzone']=='Europe/Berlin')
  sql("INSERT INTO db_injektionsqueue(sql_befehl,status,meta_aktion) VALUES('SELECT 1','offen','fixture');",'wartung_offline1')
  request('/terminal.php?aktion=zeit',opener=opener,app='terminal1')
  check('Uhr-Poll verarbeitet keine Queue-Einträge',sql("SELECT COUNT(*) FROM db_injektionsqueue WHERE status='offen' AND meta_aktion='fixture'",'wartung_offline1').strip()=='1')
  sql("DELETE FROM db_injektionsqueue WHERE meta_aktion='fixture'",'wartung_offline1')
  cache=LAB/'terminal1/config/terminalzeit.local.json';cachevorher=cache.read_bytes()
  (state('terminal1')/'pause.json').write_text(json.dumps({'id':'fixture','seit':'fixture'}))
  request('/terminal.php?aktion=zeit',opener=opener,app='terminal1')
  check('Uhr-Poll ändert keine Cachedatei während Wartung',cache.read_bytes()==cachevorher)
  (state('terminal1')/'pause.json').unlink()
  page=buchen('gehen',anmelden())
  check('Gehen funktioniert mit derselben zentralen Zeitquelle','Gehen gebucht um' in page and sql("SELECT COUNT(*) FROM zeitbuchung WHERE mitarbeiter_id=123 AND typ='gehen' AND DATE(zeitstempel)='"+heute+"'",'wartung_haupt').strip()=='1')
  alt=sql("SELECT id,zeitstempel FROM zeitbuchung WHERE mitarbeiter_id=123 ORDER BY id",'wartung_haupt')
  datei=LAB/'terminal1/config/config.local.php';konfigvorher=datei.read_bytes()
  c=json.loads(php("echo json_encode(require 'config/config.local.php');",'terminal1'));c['db']['pass']='absichtlich-falsch';writephp(datei,c)
  page=buchen('kommen',anmelden())
  check('Offline-Buchung bleibt bei falscher Rechneruhr nutzbar','Offline: Kommen gespeichert' in page and sql("SELECT COUNT(*) FROM db_injektionsqueue WHERE status='offen' AND meta_aktion='zeit_kommen_rfid'",'wartung_offline1').strip()=='1')
  queuezeit=sql("SELECT sql_befehl FROM db_injektionsqueue WHERE meta_aktion='zeit_kommen_rfid'",'wartung_offline1')
  check('Offline-Buchung behält Datum des letzten Zeitabgleichs',heute in queuezeit and lokal not in queuezeit)
  datei.write_bytes(konfigvorher)
  request('/terminal.php?aktion=start',opener=opener,app='terminal1')
  check('Offline-Stempel wird unter richtigem Datum nachgetragen',sql("SELECT COUNT(*) FROM zeitbuchung WHERE mitarbeiter_id=123 AND typ='kommen' AND DATE(zeitstempel)='"+heute+"'",'wartung_haupt').strip()=='2')
  neu=sql("SELECT id,zeitstempel FROM zeitbuchung WHERE mitarbeiter_id=123 ORDER BY id LIMIT 2",'wartung_haupt')
  check('Zeitabgleich verändert bereits gespeicherte Buchungen nicht',alt==neu)
  request('/terminal.php?aktion=start',opener=opener,app='terminal1')
  check('Wiederanlauf erzeugt keine zweite Offline-Buchung',sql('SELECT COUNT(*) FROM zeitbuchung WHERE mitarbeiter_id=123','wartung_haupt').strip()=='3')
'''
try:
    exec(compile(aufbau+probe+abschluss,str(REPO/'scripts/tests/terminal_buchung.py'),'exec'),
         {'__file__':str(REPO/'scripts/tests/wartung_integration.py'),'__name__':'__main__'})
finally:
    if stand:stand.cleanup()
