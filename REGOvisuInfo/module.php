<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/RegoVisuTile.php';

/**
 * Info -- die Kachel für die Startseite: Sonnenaufgang, Sonnenuntergang und
 * die Außentemperatur nebeneinander, im selben Werteraster wie die
 * Wetterstation.
 *
 * Sonnenauf- und -untergang stehen in Symcon als Zeitstempel in der
 * Location-Instanz; hier erscheinen sie als Uhrzeit.
 */
class REGOvisuInfo extends IPSModule
{
    use RegoVisuTile;

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
        $inner = '<div class="werte" id="rego-werte"></div>';

        $script = <<<'JS'
function regoRenderInfo(eintraege) {
    window.regoState.Info = eintraege;
    var werte = document.getElementById('rego-werte');
    werte.innerHTML = '';
    (eintraege || []).forEach(function (eintrag) {
        var block = document.createElement('span');
        block.className = 'wert';

        var zahl = document.createElement('span');
        zahl.className = 'wert-zahl';
        zahl.textContent = eintrag.text;
        if (eintrag.unit) {
            var einheit = document.createElement('span');
            einheit.className = 'wert-einheit';
            einheit.textContent = eintrag.unit;
            zahl.appendChild(einheit);
        }

        var label = document.createElement('span');
        label.className = 'wert-label';
        label.textContent = eintrag.label;

        block.appendChild(zahl);
        block.appendChild(label);
        werte.appendChild(block);
    });
}
window.regoHandlers['Info'] = regoRenderInfo;
regoRenderInfo(window.regoState.Info);
JS;

        return $this->RegoTile('info', $inner, $script, ['Info' => $this->CurrentInfo()]);
    }

    private function CurrentInfo(): array
    {
        $eintraege = [];

        foreach ([
            ['SunriseVariable', 'Sonnenaufgang'],
            ['SunsetVariable', 'Sonnenuntergang'],
        ] as [$property, $label]) {
            $variableID = $this->ReadPropertyInteger($property);
            if (($variableID == 0) || !IPS_VariableExists($variableID)) {
                continue;
            }
            $zeitstempel = (int) GetValue($variableID);
            $eintraege[] = [
                'text' => ($zeitstempel > 0) ? date('H:i', $zeitstempel) : '–',
                'unit' => '',
                'label' => $label,
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

            $eintraege[] = [
                'text' => number_format((float) GetValue($temperatur), $this->ReadPropertyInteger('Digits'), ',', ''),
                'unit' => $einheit,
                'label' => 'Außentemperatur',
            ];
        }

        return $eintraege;
    }

    private function PushState(): void
    {
        $this->RegoPush('Info', $this->CurrentInfo());
    }
}
