#!/usr/bin/env python3
"""Bootstrap-Vertrag in einer Sandbox: keine Pakete, Dienste oder echte DBs.
Die absoluten Systempfade einer Testkopie werden auf TemporaryDirectory gelegt;
Git, Download, systemctl und Installationsstufen sind kontrollierte Attrappen.
"""
import os
from pathlib import Path
import pty
import subprocess
import tempfile
import unittest

ROOT = Path(__file__).resolve().parents[2]


class Installer(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory(prefix='terminal-installer-')
        self.addCleanup(self.tmp.cleanup)
        self.base = Path(self.tmp.name)
        self.target = self.base / 'opt/zeiterfassung'
        self.target.parent.mkdir()
        self.state = self.base / 'state'
        self.bin = self.base / 'bin'
        self.bin.mkdir()
        self.calls = self.base / 'calls'
        self.env = dict(os.environ, PATH=f'{self.bin}:/usr/bin:/bin',
                        TEST_ROOT=str(self.base), TEST_CALLS=str(self.calls))
        source = (ROOT / 'scripts/terminal/installieren.sh').read_text()
        source = source.replace('/opt/zeiterfassung', str(self.target))
        source = source.replace('/var/lib/zeiterfassung-terminal-installation', str(self.state))
        source = source.replace('/run/systemd/system', str(self.base))
        source = source.replace('/proc/device-tree/model', str(self.base / 'model'))
        self.script = self.base / 'installieren.sh'
        self.script.write_text(source)
        self.fake('id', 'echo "${TEST_UID:-0}"')
        # Production checks root ownership. Test files belong to the invoking user.
        self.fake('stat', 'echo 0:700')
        self.fake('systemctl', '''echo "systemctl $*" >> "$TEST_CALLS"
if [ "${TEST_FAIL:-}" = kiosk ] && [ "$1" = start ]; then exit 1; fi''')
        self.fake('curl', '''[ "${TEST_FAIL:-}" != download ] || exit 22
while [ "$#" -gt 0 ]; do
  if [ "$1" = -o ]; then cp "$TEST_ROOT/helper" "$2"; exit; fi
  shift
done
exit 1''')
        (self.base / 'helper').write_text('erkenne_paketfamilie() { return 0; }\n')
        self.fake('git', '''echo "git $*" >> "$TEST_CALLS"
[ "${TEST_FAIL:-}" != git ] || exit 128
ziel="${@: -1}"
mkdir -p "$ziel/scripts/terminal" "$ziel/config"
cp "$TEST_ROOT/stages/"* "$ziel/scripts/terminal/"
''')
        stages = self.base / 'stages'
        stages.mkdir()
        for name in ['install_terminal.sh', 'install_kiosk.sh', 'install_peripherie.sh', 'selbsttest.sh']:
            (stages / name).write_text('''#!/bin/bash
set -eu
name="$(basename "$0")"
echo "$name $*" >> "$TEST_CALLS"
[ "${TEST_FAIL:-}" != "$name" ] || exit 1
if [ "${TEST_FAIL:-}" = neustart ] && [ "$name" = install_peripherie.sh ]; then exit 20; fi
. "$1"
[ "$ZIEL_VERZEICHNIS" = "$TEST_ROOT/opt/zeiterfassung" ]
[ "$RFID_VARIANTE" != '' ]
[ ! -t 0 ]
[ "$(umask)" = 0022 ]
''')

    def fake(self, name, text):
        path = self.bin / name
        path.write_text('#!/bin/bash\nset -eu\n' + text + '\n')
        path.chmod(0o755)

    def run_installer(self, *args, fail='', terminal_input=None):
        env = dict(self.env, TEST_FAIL=fail)
        if terminal_input is None:
            return subprocess.run(['bash', str(self.script), *args], env=env,
                                  stdin=subprocess.DEVNULL, stdout=subprocess.PIPE,
                                  stderr=subprocess.STDOUT, text=True, timeout=10)
        master, slave = pty.openpty()
        try:
            proc = subprocess.Popen(['bash', str(self.script), *args], env=env,
                                    stdin=slave, stdout=subprocess.PIPE, stderr=subprocess.STDOUT,
                                    text=True)
            os.close(slave)
            slave = -1
            os.write(master, terminal_input.encode())
            output, _ = proc.communicate(timeout=10)
            return subprocess.CompletedProcess(proc.args, proc.returncode, output)
        finally:
            os.close(master)
            if slave >= 0:
                os.close(slave)

    def log(self):
        return self.calls.read_text() if self.calls.exists() else ''

    def test_standard_order_and_private_answers(self):
        result = self.run_installer('--standard')
        self.assertEqual(result.returncode, 0, result.stdout)
        lines = self.log().splitlines()
        self.assertEqual([s.split()[0] for s in lines],
                         ['git', 'install_terminal.sh', 'install_kiosk.sh',
                          'install_peripherie.sh', 'selbsttest.sh', 'systemctl', 'systemctl'])
        self.assertIn('--ohne-scan --vor-kopplung', lines[4])
        self.assertEqual(self.state.stat().st_mode & 0o777, 0o700)
        self.assertEqual((self.state / 'terminal.conf').stat().st_mode & 0o777, 0o600)
        self.assertIn('SPI_AKTIVIEREN=nein', (self.state / 'terminal.conf').read_text())

    def test_each_failed_stage_stops_and_can_resume_without_download(self):
        for name in ['install_terminal.sh', 'install_kiosk.sh', 'install_peripherie.sh', 'selbsttest.sh']:
            with self.subTest(stage=name):
                self.calls.unlink(missing_ok=True)
                result = self.run_installer('--standard', fail=name)
                self.assertNotEqual(result.returncode, 0)
                self.assertNotIn('systemctl start', self.log())
                self.assertIn(name, self.log().splitlines()[-1])
                self.calls.unlink()
                result = self.run_installer('--standard')
                self.assertEqual(result.returncode, 0, result.stdout)
                self.assertNotIn('git ', self.log())

    def test_download_failures_leave_no_target_and_retry(self):
        for failure in ['download', 'git']:
            result = self.run_installer('--standard', fail=failure)
            self.assertNotEqual(result.returncode, 0)
            self.assertFalse(self.target.exists())
            self.assertEqual(list(self.state.glob('download.*')), [])
        self.assertEqual(self.run_installer('--standard').returncode, 0)

    def test_existing_project_is_untouched(self):
        self.target.mkdir()
        sentinel = self.target / 'important'
        sentinel.write_text('keep')
        result = self.run_installer('--standard')
        self.assertNotEqual(result.returncode, 0)
        self.assertEqual(sentinel.read_text(), 'keep')
        self.assertFalse(self.state.exists())
        self.assertEqual(self.log(), '')

    def test_coupled_terminal_refused_even_with_resume_marker(self):
        self.assertEqual(self.run_installer('--standard').returncode, 0)
        config = self.target / 'config/config.local.php'
        config.write_text('preserve offline and central settings')
        self.calls.unlink()
        result = self.run_installer('--standard')
        self.assertNotEqual(result.returncode, 0)
        self.assertEqual(config.read_text(), 'preserve offline and central settings')
        self.assertEqual(self.log(), '')

    def test_no_terminal_requires_explicit_defaults(self):
        result = self.run_installer()
        self.assertNotEqual(result.returncode, 0)
        self.assertFalse(self.target.exists())
        self.assertEqual(self.log(), '')

    def test_help_and_unknown_flags_change_nothing(self):
        self.assertEqual(self.run_installer('--help').returncode, 0)
        self.assertNotEqual(self.run_installer('--typo').returncode, 0)
        self.assertFalse(self.state.exists())

    def test_root_required(self):
        self.env['TEST_UID'] = '1000'
        self.assertNotEqual(self.run_installer('--standard').returncode, 0)
        self.assertFalse(self.state.exists())

    def test_start_failure_is_reported(self):
        result = self.run_installer('--standard', fail='kiosk')
        self.assertNotEqual(result.returncode, 0)
        self.assertIn('konnte nicht gestartet', result.stdout)

    def test_unknown_spi_without_interface_refused_before_system_changes(self):
        result = self.run_installer(terminal_input='4\n/dev/spidev0.0\n')
        self.assertNotEqual(result.returncode, 0)
        self.assertIn('Kein nutzbarer SPI-Anschluss', result.stdout)
        self.assertEqual(self.log(), '')
        self.assertFalse(self.target.exists())

    def test_pi_rc522_pin_mapping_and_configuration(self):
        (self.base / 'model').write_text('Raspberry Pi 5 Model B Rev 1.0\0')
        result = self.run_installer(terminal_input='4\nja\nde\nnormal\n')
        self.assertEqual(result.returncode, 0, result.stdout)
        self.assertIn('GPIO8 / SPI0 CE0', result.stdout)
        self.assertIn('17                              3,3 V', result.stdout)
        answers = (self.state / 'terminal.conf').read_text()
        self.assertIn('RFID_VARIANTE=rc522', answers)
        self.assertIn('RFID_GERAET=/dev/spidev0.0', answers)
        self.assertIn('SPI_AKTIVIEREN=ja', answers)
        self.assertTrue((self.state / 'rc522-anschluss.txt').exists())

    def test_reboot_pause_and_resume(self):
        (self.base / 'model').write_text('Raspberry Pi 4 Model B Rev 1.5')
        result = self.run_installer(fail='neustart', terminal_input='4\nja\nde\nnormal\n')
        self.assertEqual(result.returncode, 20, result.stdout)
        self.assertIn('sudo reboot', result.stdout)
        self.assertIn('systemctl disable --now zeiterfassung-kiosk.service', self.log())
        self.assertNotIn('selbsttest.sh', self.log())
        self.calls.unlink()
        self.assertEqual(self.run_installer().returncode, 0)
        self.assertNotIn('git ', self.log())

    def test_wiring_preview_needs_no_root_and_changes_nothing(self):
        self.env['TEST_UID'] = '1000'
        for model in ['Raspberry Pi Zero 2 W Rev 1.0', 'Raspberry Pi Model B Plus Rev 1.2']:
            (self.base / 'model').write_text(model)
            result = self.run_installer('--anschluss')
            self.assertEqual(result.returncode, 0)
            self.assertIn('GPIO8 / SPI0 CE0', result.stdout)
            self.assertFalse(self.state.exists())

    def test_serial_rotation_and_answers_on_retry(self):
        result = self.run_installer(terminal_input='2\n/dev/serial/by-id/reader-1\n115200\nus\nlinks\n')
        self.assertEqual(result.returncode, 0, result.stdout)
        answers = (self.state / 'terminal.conf').read_text()
        for expected in ['RFID_VARIANTE=bridge', 'RFID_BAUD=115200', 'KIOSK_ANZEIGE=x11', 'TASTATURLAYOUT=us']:
            self.assertIn(expected, answers)
        self.assertEqual(self.run_installer('--standard').returncode, 0)
        self.assertEqual((self.state / 'terminal.conf').read_text(), answers)

    def test_hardware_can_be_corrected_without_editing_files(self):
        self.assertEqual(self.run_installer('--standard').returncode, 0)
        result = self.run_installer('--hardware', terminal_input='3\nus\nrechts\n')
        self.assertEqual(result.returncode, 0, result.stdout)
        answers = (self.state / 'terminal.conf').read_text()
        self.assertIn('RFID_VARIANTE=keine', answers)
        self.assertIn('KIOSK_ANZEIGE=x11', answers)
        self.assertEqual(self.log().count('git clone'), 1)

    def test_shell_code_in_serial_input_is_rejected(self):
        marker = self.base / 'injected'
        result = self.run_installer(terminal_input=f'2\n/dev/ttyUSB0;touch {marker}\n9600\nde\nnormal\n')
        self.assertNotEqual(result.returncode, 0)
        self.assertFalse(marker.exists())
        self.assertEqual(self.log(), '')

    def test_symlink_target_refused(self):
        other = self.base / 'other'
        other.mkdir()
        self.target.symlink_to(other)
        self.assertNotEqual(self.run_installer('--standard').returncode, 0)
        self.assertEqual(self.log(), '')


class Selbsttest(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory(prefix='terminal-selbsttest-')
        self.addCleanup(self.tmp.cleanup)
        self.base = Path(self.tmp.name)
        self.bin = self.base / 'bin'
        self.bin.mkdir()
        self.env = dict(os.environ, PATH=f'{self.bin}:/usr/bin:/bin')
        self.target = self.base / 'projekt'
        (self.target / 'config').mkdir(parents=True)
        (self.target / 'config/geraet.local.php').write_text('synthetic fixture')
        script = (ROOT / 'scripts/terminal/selbsttest.sh').read_text()
        for old, new in {
            '/opt/zeiterfassung': str(self.target),
            '/var/log/zeiterfassung-terminal-setup.log': str(self.base / 'test.log'),
            '/etc/zeiterfassung-peripherie.conf': str(self.base / 'peripherie.conf'),
            '/etc/zeiterfassung-kiosk.conf': str(self.base / 'kiosk.conf'),
            '/run/systemd/system': str(self.base),
        }.items():
            script = script.replace(old, new)
        self.script = self.base / 'selbsttest.sh'
        self.script.write_text(script)
        self.fake('systemctl', '[ "$1" = is-enabled ]')
        self.fake('php', 'echo "${TEST_DB:-OK}"')
        self.fake('curl', """case "$*" in
 *redirect_url*) echo http://localhost/terminal.php ;;
 *index.php*) echo 302 ;;
 *aktion=health*) echo '{"zeit":"synthetic","queue_offen":0,"queue_fehler":0}' ;;
 *) echo 200 ;;
esac""")

    def fake(self, name, text):
        path = self.bin / name
        path.write_text('#!/bin/bash\n' + text + '\n')
        path.chmod(0o755)

    def run_test(self, *args):
        return subprocess.run(['bash', str(self.script), '--ohne-scan', *args],
                              env=self.env, stdin=subprocess.DEVNULL, stdout=subprocess.PIPE,
                              stderr=subprocess.STDOUT, text=True, timeout=10)

    def test_before_pairing_allows_only_expected_missing_steps(self):
        result = self.run_test('--vor-kopplung')
        self.assertEqual(result.returncode, 0, result.stdout)
        self.assertIn('Kopplung und Geraetetest stehen noch aus', result.stdout)
        self.assertNotIn('Das Geraet ist einsatzbereit.', result.stdout)

    def test_normal_test_still_requires_pairing_and_running_kiosk(self):
        result = self.run_test()
        self.assertNotEqual(result.returncode, 0)
        self.assertIn('Geraet ist noch nicht gekoppelt', result.stdout)
        self.assertIn('Kiosk laeuft nicht', result.stdout)

    def test_offline_database_failure_is_never_ignored(self):
        self.env['TEST_DB'] = 'FEHLER'
        self.assertNotEqual(self.run_test('--vor-kopplung').returncode, 0)

    def test_child_exit_contract(self):
        # Fuehrt nur den wirklichen Abschluss der drei Installer aus, nie die
        # Systeminstallation. Die Fehlerzaehler werden absichtlich vorgegeben.
        for name in ['install_terminal.sh', 'install_kiosk.sh', 'install_peripherie.sh']:
            text = (ROOT / 'scripts/terminal' / name).read_text()
            ending = text[text.rindex('# Der gemeinsame Installer'):]
            for missing in [0, 1, 3]:
                result = subprocess.run(['bash', '-c', f'ERGEBNIS_FEHLT={missing}\n' + ending])
                self.assertEqual(result.returncode, 0 if missing == 0 else 1, name)


if __name__ == '__main__':
    unittest.main(verbosity=2)
