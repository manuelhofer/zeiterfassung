<?php
declare(strict_types=1);

/** @var array|null  $mitarbeiter */
/** @var string|null $nachricht */
/** @var string|null $fehlerText */
/** @var string|null $csrfToken */
/** @var int|null $terminalTimeoutSekunden */


$terminalTimeoutSekunden = isset($terminalTimeoutSekunden) ? (int)$terminalTimeoutSekunden : null;
$csrfToken = isset($csrfToken) && is_string($csrfToken) ? $csrfToken : '';

$seitenTitel = 'Terminal – Auftrag starten';
$seitenUeberschrift = 'Auftrag starten';
require __DIR__ . '/_layout_top.php';
?>

    <?php if (!empty($nachricht)): ?>
        <div class="meldung">
            <?php echo htmlspecialchars($nachricht, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($fehlerText)): ?>
        <div class="fehler">
            <?php echo htmlspecialchars($fehlerText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <p class="hinweis">
        Bitte erst den <strong>Auftragscode</strong> einscannen oder eingeben und danach den <strong>Arbeitsschritt-Code</strong>.
        (Maschine ist optional.)
    </p>

    <form method="post" action="terminal.php?aktion=auftrag_starten" class="login-form">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars((string)$csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
        <label for="auftragscode">Auftragscode</label>
        <input type="text" id="auftragscode" name="auftragscode" autocomplete="off" required autofocus>

        <label for="arbeitsschritt_code">Arbeitsschritt-Code</label>
        <input type="text" id="arbeitsschritt_code" name="arbeitsschritt_code" autocomplete="off" required>

        <label for="maschine_id">Maschinen-ID (optional, Barcode: id_name möglich)</label>
        <input type="text" id="maschine_id" name="maschine_id" inputmode="numeric" autocomplete="off" placeholder="z.B. 12">

        <div class="button-row">
            <button type="submit">Auftrag starten</button>
            <a href="terminal.php?aktion=start" class="button-link secondary">Zurück zum Start</a>
        </div>
    </form>

    <script>
    (function () {
      const auftrag = document.getElementById('auftragscode');
      const schritt = document.getElementById('arbeitsschritt_code');
      const maschine = document.getElementById('maschine_id');

      // Barcode-Scanner senden meistens ein "Enter" nach dem Scan.
      // Wir springen dann bequem ins naechste Feld.
      if (auftrag && schritt) {
        auftrag.addEventListener('keydown', (ev) => {
          if (ev.key === 'Enter') {
            ev.preventDefault();
            schritt.focus();
            schritt.select?.();
          }
        });
      }

      if (schritt && maschine) {
        schritt.addEventListener('keydown', (ev) => {
          if (ev.key === 'Enter') {
            // Wenn direkt noch die Maschine gescannt wird: naechstes Feld
            ev.preventDefault();
            maschine.focus();
            maschine.select?.();
          }
        });
      }

      if (maschine) {
        maschine.addEventListener('keydown', (ev) => {
          if (ev.key === 'Enter') {
            // Maschine ist optional: Enter startet/submit.
            ev.preventDefault();
            const form = maschine.closest('form');
            if (form) {
              form.submit();
            }
          }
        });
      }
    })();
    </script>

    <?php
    $logoutFormHidden = true;
    require __DIR__ . '/_logout_form.php';
    ?>

    <?php if (!empty($mitarbeiter) && is_array($mitarbeiter)): ?>
        <?php
            // Unten angedockte, platzsparende Mitarbeiter-Info (Touch/Kiosk).
            // Einheitlich zum Startscreen/Urlaub-Seiten.
            $mitarbeiterName = trim((string)($mitarbeiter['vorname'] ?? '') . ' ' . (string)($mitarbeiter['nachname'] ?? ''));
            $mitarbeiterId = (int)($mitarbeiter['id'] ?? 0);
        ?>

		<details class="status-box terminal-mitarbeiterpanel">
			<summary class="status-title">
				<a href="terminal.php?aktion=start&amp;view=arbeitszeit" style="display:flex; justify-content:space-between; width:100%; color:inherit; text-decoration:none;">
					<span>
						Mitarbeiter:
						<strong><?php echo htmlspecialchars($mitarbeiterName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></strong>
						(ID: <?php echo (int)$mitarbeiterId; ?>)
					</span>
					<span class="status-small">Arbeitszeit</span>
				</a>
			</summary>
		</details>
    <?php endif; ?>

<?php require __DIR__ . '/_layout_bottom.php'; ?>
