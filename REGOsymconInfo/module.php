<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/RegoSymconTile.php';

/**
 * Info -- die Kachel für die Startseite: Sonnenaufgang, Sonnenuntergang und
 * die Außentemperatur nebeneinander, im selben Werteraster wie die
 * Wetterstation.
 *
 * Sonnenauf- und -untergang stehen in Symcon als Zeitstempel in der
 * Location-Instanz; hier erscheinen sie als Uhrzeit.
 */
class REGOsymconInfo extends IPSModule
{
    use RegoSymconTile;

    public function Create(): void
    {
        parent::Create();

        $this->RegisterPropertyInteger('SunriseVariable', 0);
        $this->RegisterPropertyInteger('SunsetVariable', 0);
        $this->RegisterPropertyInteger('TemperatureVariable', 0);
        $this->RegisterPropertyInteger('Digits', 1);

        $this->SetVisualizationType(1);
    }

    public function ApplyChanges(): void
    {
        parent::ApplyChanges();

        $this->RegoSyncMessages([
            $this->ReadPropertyInteger('SunriseVariable'),
            $this->ReadPropertyInteger('SunsetVariable'),
            $this->ReadPropertyInteger('TemperatureVariable')
        ]);

        $this->PushState();
    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data): void
    {
        if ($Message == VM_UPDATE) {
            $this->PushState();
        }
    }

    public function GetVisualizationTile(): string
    {
        // Das Datum steht als Zeile direkt unter der Ueberschrift, nicht als
        // eigenes Feld -- darunter bleiben drei gleich breite Kacheln.
        $zeilen = [$this->CurrentInfo()];
        $inner = '<div class="kopfzeile" id="rego-datum">' . htmlspecialchars($this->Datum()) . '</div>'
            . $this->RegoFelder($zeilen);

        $script = $this->RegoFelderScript() . <<<'JS'
window.regoHandlers['Datum'] = function (text) {
    document.getElementById('rego-datum').textContent = text;
};
JS;

        return $this->RegoTile('info', $inner, $script,
            ['Felder' => $zeilen, 'Datum' => $this->Datum()]);
    }

    /**
     * Die Felder der Karte: Datum, Sonnenauf- und -untergang, Aussentemperatur
     * -- dieselbe Auswahl wie die Standort-Karte in REGObase, soweit Symcon
     * die Werte hat (Ort, Bundesland und Hoehe kennt es nicht).
     */
    private function CurrentInfo(): array
    {
        $felder = [];

        foreach ([
            ['SunriseVariable', 'Sonnenaufgang'],
            ['SunsetVariable', 'Sonnenuntergang'],
        ] as [$property, $label]) {
            $variableID = $this->ReadPropertyInteger($property);
            if (($variableID == 0) || !IPS_VariableExists($variableID)) {
                continue;
            }
            $zeitstempel = (int) GetValue($variableID);
            $felder[] = [
                'label' => $label,
                'text' => ($zeitstempel > 0) ? date('H:i', $zeitstempel) : '–',
            ];
        }

        $temperatur = $this->ReadPropertyInteger('TemperatureVariable');
        if (($temperatur != 0) && IPS_VariableExists($temperatur)) {
            $variable = IPS_GetVariable($temperatur);
            $profil = $variable['VariableCustomProfile'];
            if ($profil === '') {
                $profil = $variable['VariableProfile'];
            }
            $einheit = (($profil !== '') && IPS_VariableProfileExists($profil))
                ? trim((string) IPS_GetVariableProfile($profil)['Suffix'])
                : '';

            $felder[] = [
                'label' => 'Außentemperatur',
                'text' => trim(number_format((float) GetValue($temperatur),
                    $this->ReadPropertyInteger('Digits'), ',', '') . ' ' . $einheit),
            ];
        }

        return $felder;
    }

    private function Datum(): string
    {
        $wochentage = ['Sonntag', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag'];
        return $wochentage[(int) date('w')] . ', ' . date('d.m.Y');
    }

    private function PushState(): void
    {
        $this->RegoPush('Felder', [$this->CurrentInfo()]);
        $this->RegoPush('Datum', $this->Datum());
    }
}
