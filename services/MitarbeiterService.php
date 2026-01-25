<?php
declare(strict_types=1);

/**
 * MitarbeiterService
 *
 * Kapselt gemeinsame Geschäftslogik rund um Mitarbeiter,
 * Rollen und Genehmiger.
 */
class MitarbeiterService
{
    private static ?MitarbeiterService $instanz = null;

    private MitarbeiterModel $mitarbeiterModel;
    private RolleModel $rolleModel;
    private MitarbeiterGenehmigerModel $genehmigerModel;

    private function __construct()
    {
        $this->mitarbeiterModel  = new MitarbeiterModel();
        $this->rolleModel        = new RolleModel();
        $this->genehmigerModel   = new MitarbeiterGenehmigerModel();
    }

    public static function getInstanz(): MitarbeiterService
    {
        if (self::$instanz === null) {
            self::$instanz = new self();
        }

        return self::$instanz;
    }

    /**
     * Holt einen Mitarbeiter mit seinen Rollen und Genehmigern.
     *
     * Rückgabeformat:
     * [
     *   'mitarbeiter' => [...],
     *   'rollen'      => [...],
     *   'genehmiger'  => [...],
     * ]
     *
     * @return array<string,mixed>|null
     */
    public function holeMitarbeiterMitRollenUndGenehmigern(int $mitarbeiterId): ?array
    {
        try {
            $mitarbeiter = $this->mitarbeiterModel->holeNachId($mitarbeiterId);
        } catch (\Throwable $e) {
            if (class_exists('Logger')) {
                Logger::error('Mitarbeiter konnte nicht geladen werden', [
                    'mitarbeiter_id' => $mitarbeiterId,
                    'exception'      => $e->getMessage(),
                ], $mitarbeiterId, null, 'mitarbeiter');
            }
            return null;
        }

        if ($mitarbeiter === null) {
            return null;
        }

        try {
            $rollen = $this->rolleModel->holeRollenFuerMitarbeiter($mitarbeiterId);
        } catch (\Throwable $e) {
            $rollen = [];
            if (class_exists('Logger')) {
                Logger::warn('Rollen für Mitarbeiter konnten nicht geladen werden', [
                    'mitarbeiter_id' => $mitarbeiterId,
                    'exception'      => $e->getMessage(),
                ], $mitarbeiterId, null, 'mitarbeiter');
            }
        }

        try {
            $genehmiger = $this->genehmigerModel->holeGenehmigerFuerMitarbeiter($mitarbeiterId);
        } catch (\Throwable $e) {
            $genehmiger = [];
            if (class_exists('Logger')) {
                Logger::warn('Genehmiger für Mitarbeiter konnten nicht geladen werden', [
                    'mitarbeiter_id' => $mitarbeiterId,
                    'exception'      => $e->getMessage(),
                ], $mitarbeiterId, null, 'mitarbeiter');
            }
        }

        return [
            'mitarbeiter' => $mitarbeiter,
            'rollen'      => $rollen,
            'genehmiger'  => $genehmiger,
        ];
    }

    /**
     * Hilfsfunktion für Logins: Holt einen loginfähigen Mitarbeiter
     * (aktiv, ist_login_berechtigt) anhand Benutzername oder E-Mail.
     *
     * @return array<string,mixed>|null
     */
    public function holeLoginFaehigenMitarbeiterNachKennung(string $kennung): ?array
    {
        try {
            return $this->mitarbeiterModel->holeLoginFaehigenNachKennung($kennung);
        } catch (\Throwable $e) {
            if (class_exists('Logger')) {
                Logger::error('Fehler beim Laden eines loginfähigen Mitarbeiters', [
                    'kennung'   => $kennung,
                    'exception' => $e->getMessage(),
                ], null, null, 'mitarbeiter');
            }

            return null;
        }
    }

    /**
     * Hilfsfunktion für Terminal-Login per RFID.
     *
     * @return array<string,mixed>|null
     */
    public function holeAktivenMitarbeiterNachRfid(string $rfidCode): ?array
    {
        try {
            return $this->mitarbeiterModel->holeNachRfidCode($rfidCode);
        } catch (\Throwable $e) {
            if (class_exists('Logger')) {
                Logger::error('Fehler beim Laden eines Mitarbeiters per RFID', [
                    'rfid'      => $rfidCode,
                    'exception' => $e->getMessage(),
                ], null, null, 'mitarbeiter');
            }

            return null;
        }
    }
}
