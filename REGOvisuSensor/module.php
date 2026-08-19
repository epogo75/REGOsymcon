<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/RegoVisuTile.php';

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
class REGOvisuSensor extends IPSModule
{
    use RegoVisuTile;

    public function Create(): void
    {
        parent::Create();

        $this->RegisterPropertyInteger('ValueVariable', 0);
        $this->RegisterPropertyInteger('SecondaryVariable', 0);
        $this->RegisterPropertyInteger('BatteryVariable', 0);
        $this->RegisterPropertyBoolean('AlarmOnTrue', true);

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
        $inner = '<div class="sensor">'
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
        $formatiert = $this->Format($variableID);

        $badges = [];

        $zweiter = $this->ReadPropertyInteger('SecondaryVariable');
        if ($zweiter != 0) {
            $wert = $this->Format($zweiter);
            if ($wert['text'] !== '–') {
                $badges[] = [
                    'text' => IPS_GetName($zweiter) . ': ' . trim($wert['text'] . ' ' . $wert['unit']),
                    'alarm' => $wert['alarm'],
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
    private function Format(int $variableID): array
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

        $stellen = ($info !== null) ? (int) $info['Digits'] : (($variable['VariableType'] == 1) ? 0 : 1);
        $text = number_format((float) $wert, max(0, $stellen), ',', '.');
        $einheit = ($info !== null) ? trim((string) $info['Suffix']) : '';

        return ['text' => $text, 'unit' => $einheit, 'alarm' => false];
    }

    private function PushState(): void
    {
        $this->RegoPush('Reading', $this->CurrentReading());
    }
}
