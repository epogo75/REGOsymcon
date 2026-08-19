<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/RegoVisuTile.php';

/**
 * Wetterstation -- viele Werte auf einmal: oben die Messwerte als Raster mit
 * Beschriftung, darunter die Ja/Nein-Meldungen als Pillen.
 *
 * Das ist die Darstellung der Raumzusammenfassung aus REGObaseX1. Ob ein
 * Eintrag als Zahl oder als Pille erscheint, entscheidet der Typ der Variable,
 * nicht eine Namensliste.
 */
class REGOvisuWetterstation extends IPSModule
{
    use RegoVisuTile;

    public function Create(): void
    {
        parent::Create();

        $this->RegisterPropertyString('Readings', '[]');

        // 2 = auch im Vollbild; aufgeklappt zeigt die Kachel die Objekte.
        $this->SetVisualizationType(2);
    }

    public function ApplyChanges(): void
    {
        parent::ApplyChanges();

        $ids = [];
        foreach ($this->Readings() as $zeile) {
            $ids[] = (int) $zeile['VariableID'];
        }
        $this->RegoSyncMessages($ids);

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
        $eintraege = [];
        foreach ($this->Readings() as $zeile) {
            $label = trim((string) ($zeile['Label'] ?? ''));
            $eintraege[$label !== '' ? $label : 'Messwert'] = (int) ($zeile['VariableID'] ?? 0);
        }

        $inner = '<div class="werte" id="rego-werte"></div>'
            . '<div class="badges" id="rego-badges"></div>'
            . $this->RegoObjekte($eintraege);

        $script = <<<'JS'
function regoRenderStation(daten) {
    window.regoState.Station = daten;

    var werte = document.getElementById('rego-werte');
    werte.innerHTML = '';
    (daten.values || []).forEach(function (eintrag) {
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

    var badges = document.getElementById('rego-badges');
    badges.innerHTML = '';
    (daten.badges || []).forEach(function (badge) {
        var span = document.createElement('span');
        span.className = 'badge' + (badge.alarm ? ' badge-danger' : '');
        span.textContent = badge.text;
        badges.appendChild(span);
    });
}
window.regoHandlers['Station'] = regoRenderStation;
regoRenderStation(window.regoState.Station);
JS;

        return $this->RegoTile('wetterstation', $inner, $script, ['Station' => $this->CurrentStation()]);
    }

    private function Readings(): array
    {
        $zeilen = json_decode($this->ReadPropertyString('Readings'), true);
        return is_array($zeilen) ? $zeilen : [];
    }

    /**
     * Zahlen ins Raster, Ja/Nein-Werte in die Pillenzeile.
     */
    private function CurrentStation(): array
    {
        $values = [];
        $badges = [];

        foreach ($this->Readings() as $zeile) {
            $variableID = (int) ($zeile['VariableID'] ?? 0);
            if (($variableID == 0) || !IPS_VariableExists($variableID)) {
                continue;
            }

            $variable = IPS_GetVariable($variableID);
            $label = trim((string) ($zeile['Label'] ?? ''));
            if ($label === '') {
                $label = IPS_GetName($variableID);
            }
            $wert = GetValue($variableID);

            if ($variable['VariableType'] == 0) {
                $badges[] = [
                    'text' => $label . ': ' . ($wert ? 'Ja' : 'Nein'),
                    'alarm' => ((bool) $wert) && ((bool) ($zeile['Alarm'] ?? false)),
                ];
                continue;
            }

            $profil = $variable['VariableCustomProfile'];
            if ($profil === '') {
                $profil = $variable['VariableProfile'];
            }
            $info = ($profil !== '') && IPS_VariableProfileExists($profil) ? IPS_GetVariableProfile($profil) : null;

            if ($variable['VariableType'] == 3) {
                $values[] = ['text' => (string) $wert, 'unit' => '', 'label' => $label];
                continue;
            }

            $stellen = (int) ($zeile['Digits'] ?? -1);
            if ($stellen < 0) {
                $stellen = ($info !== null) ? (int) $info['Digits'] : (($variable['VariableType'] == 1) ? 0 : 1);
            }

            $values[] = [
                'text' => number_format((float) $wert, max(0, $stellen), ',', ''),
                'unit' => ($info !== null) ? trim((string) $info['Suffix']) : '',
                'label' => $label,
            ];
        }

        return ['values' => $values, 'badges' => $badges];
    }

    private function PushState(): void
    {
        $this->RegoPush('Station', $this->CurrentStation());
    }
}
