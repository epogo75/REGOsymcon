<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/RegoSymconTile.php';

/**
 * Sensor -- reine Anzeige: große Zahl mit kleiner Einheit, darunter optional
 * ein zweiter Zustand und der Batteriestand als Pillen.
 *
 * Das ist die Darstellung, die REGObaseX1 in der Raumzusammenfassung benutzt;
 * eine Bedienung gibt es hier bewusst nicht.
 *
 * Formatiert wird nach dem Variablenprofil: Nachkommastellen, Einheit und die
 * Beschriftungen von Ja/Nein-Werten kommen von dort, nicht aus einer eigenen
 * Tabelle.
 */
class REGOsymconSensor extends IPSModule
{
    use RegoSymconTile;

    public function Create(): void
    {
        parent::Create();

        $this->RegisterPropertyInteger('ValueVariable', 0);
        $this->RegisterPropertyInteger('Digits', -1);
        $this->RegisterPropertyString('TextTrue', '');
        $this->RegisterPropertyString('TextFalse', '');
        $this->RegisterPropertyBoolean('AlarmOnTrue', true);
        $this->RegisterPropertyInteger('SecondaryVariable', 0);
        $this->RegisterPropertyString('SecondaryLabel', '');
        $this->RegisterPropertyString('SecondaryTextTrue', 'Ja');
        $this->RegisterPropertyString('SecondaryTextFalse', 'Nein');
        $this->RegisterPropertyInteger('BatteryVariable', 0);

        $this->SetVisualizationType(1);
    }

    public function ApplyChanges(): void
    {
        parent::ApplyChanges();

        $this->RegoSyncMessages([
            $this->ReadPropertyInteger('ValueVariable'),
            $this->ReadPropertyInteger('SecondaryVariable'),
            $this->ReadPropertyInteger('BatteryVariable')
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
        // Ein Klick oeffnet die Variable in Symcon -- dort steht der Verlauf.
        $variableID = $this->ReadPropertyInteger('ValueVariable');
        $klick = ($variableID != 0)
            ? ' class="sensor oeffnen" onclick="openObject(' . $variableID . ')" title="Verlauf öffnen"'
            : ' class="sensor"';

        $inner = '<div' . $klick . '>'
            . '<span class="sensor-value" id="rego-wert">–</span>'
            . '<span class="sensor-unit" id="rego-einheit"></span>'
            . '</div>'
            . '<div class="badges" id="rego-badges"></div>';

        $script = <<<'JS'
function regoRenderReading(daten) {
    window.regoState.Reading = daten;
    var wert = document.getElementById('rego-wert');
    wert.textContent = daten.text;
    wert.classList.toggle('alarm', daten.alarm === true);
    document.getElementById('rego-einheit').textContent = daten.unit;

    var behaelter = document.getElementById('rego-badges');
    behaelter.innerHTML = '';
    (daten.badges || []).forEach(function (badge) {
        var span = document.createElement('span');
        span.className = 'badge' + (badge.alarm ? ' badge-danger' : '');
        span.textContent = badge.text;
        behaelter.appendChild(span);
    });
}
window.regoHandlers['Reading'] = regoRenderReading;
regoRenderReading(window.regoState.Reading);
JS;


        return $this->RegoTile('sensor', $inner, $script, ['Reading' => $this->CurrentReading()]);
    }

    /**
     * Hauptwert, Einheit, Alarmzustand und die Pillen darunter.
     */
    private function CurrentReading(): array
    {
        $variableID = $this->ReadPropertyInteger('ValueVariable');
        $formatiert = $this->Format(
            $variableID,
            $this->ReadPropertyString('TextTrue'),
            $this->ReadPropertyString('TextFalse'),
            $this->ReadPropertyInteger('Digits')
        );

        $badges = [];

        $zweiter = $this->ReadPropertyInteger('SecondaryVariable');
        if ($zweiter != 0) {
            $wert = $this->Format(
                $zweiter,
                $this->ReadPropertyString('SecondaryTextTrue'),
                $this->ReadPropertyString('SecondaryTextFalse')
            );
            if ($wert['text'] !== '–') {
                $beschriftung = trim($this->ReadPropertyString('SecondaryLabel'));
                if ($beschriftung === '') {
                    $beschriftung = IPS_GetName($zweiter);
                }
                $badges[] = [
                    'text' => $beschriftung . ': ' . trim($wert['text'] . ' ' . $wert['unit']),
                    'alarm' => false,
                ];
            }
        }

        $batterie = $this->ReadPropertyInteger('BatteryVariable');
        if ($batterie != 0) {
            $wert = $this->Format($batterie);
            if ($wert['text'] !== '–') {
                $badges[] = [
                    'text' => 'Batterie ' . trim($wert['text'] . ' ' . $wert['unit']),
                    'alarm' => false,
                ];
            }
        }

        return [
            'text' => $formatiert['text'],
            'unit' => $formatiert['unit'],
            'alarm' => $formatiert['alarm'],
            'badges' => $badges,
        ];
    }

    /**
     * Formatiert eine Variable nach ihrem Profil.
     */
    private function Format(int $variableID, string $textTrue = '', string $textFalse = '', int $digits = -1): array
    {
        $leer = ['text' => '–', 'unit' => '', 'alarm' => false];

        if (($variableID == 0) || !IPS_VariableExists($variableID)) {
            return $leer;
        }

        $variable = IPS_GetVariable($variableID);
        $wert = GetValue($variableID);

        $profil = $variable['VariableCustomProfile'];
        if ($profil === '') {
            $profil = $variable['VariableProfile'];
        }
        $info = ($profil !== '') && IPS_VariableProfileExists($profil) ? IPS_GetVariableProfile($profil) : null;

        $alarm = ($variable['VariableType'] == 0) && ((bool) $wert === $this->ReadPropertyBoolean('AlarmOnTrue'));

        // Eigene Beschriftung schlägt das Profil -- die mitgelieferten
        // KNX-Profile sind englisch beschriftet ("Closed", "On").
        if ($variable['VariableType'] == 0) {
            $eigen = $wert ? $textTrue : $textFalse;
            if (trim($eigen) !== '') {
                return ['text' => $eigen, 'unit' => '', 'alarm' => $alarm];
            }
        }

        // Ja/Nein und Aufzählungen: die Beschriftung aus dem Profil.
        if ($info !== null) {
            foreach ($info['Associations'] as $association) {
                if ($variable['VariableType'] == 0) {
                    if ((bool) $association['Value'] === (bool) $wert) {
                        return ['text' => $association['Name'], 'unit' => '', 'alarm' => $alarm];
                    }
                } elseif ($association['Value'] == $wert) {
                    return ['text' => $association['Name'], 'unit' => '', 'alarm' => false];
                }
            }
        }

        if ($variable['VariableType'] == 0) {
            return ['text' => $wert ? 'Ja' : 'Nein', 'unit' => '', 'alarm' => $alarm];
        }

        if ($variable['VariableType'] == 3) {
            return ['text' => (string) $wert, 'unit' => '', 'alarm' => false];
        }

        $stellen = $digits;
        if ($stellen < 0) {
            $stellen = ($info !== null) ? (int) $info['Digits'] : (($variable['VariableType'] == 1) ? 0 : 1);
        }
        $text = number_format((float) $wert, max(0, $stellen), ',', '');
        $einheit = ($info !== null) ? trim((string) $info['Suffix']) : '';

        return ['text' => $text, 'unit' => $einheit, 'alarm' => false];
    }

    private function PushState(): void
    {
        $this->RegoPush('Reading', $this->CurrentReading());
    }
}
