<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/RegoSymconTile.php';

/**
 * Symcon-Szene -- eine Instanz je Szene.
 *
 * Anders als die KNX-Szene, die nur eine Nummer auf den Bus schickt und deren
 * Inhalt in den Aktoren steht, kennt diese Szene ihre Mitglieder selbst. Die
 * dürfen quer über alle Gewerke liegen: KNX-Licht, Modbus-Sollwert, ein
 * Merker.
 *
 * Geschrieben wird über die Aktion der Zielvariable, also mit echtem
 * Telegramm; nur wenn keine hinterlegt ist, wird der Wert direkt gesetzt.
 */
class REGOsymconSzene extends IPSModule
{
    use RegoSymconTile;

    public function Create(): void
    {
        parent::Create();

        $this->RegisterPropertyString('Members', '[]');
        $this->RegisterPropertyBoolean('OnlyChanged', true);
        $this->RegisterPropertyString('Label', 'Aufrufen');
        $this->RegisterPropertyInteger('RoomCategory', 0);

        $this->SetVisualizationType(1);
    }

    public function ApplyChanges(): void
    {
        parent::ApplyChanges();

        $ids = [];
        foreach ($this->Members() as $mitglied) {
            $ids[] = (int) $mitglied['VariableID'];
        }
        $this->RegoSyncMessages($ids);

        $this->RegoPush('Aktiv', $this->IstAktiv());
    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data): void
    {
        if ($Message == VM_UPDATE) {
            $this->RegoPush('Aktiv', $this->IstAktiv());
        }
    }

    public function RequestAction($Ident, $Value): void
    {
        if ($Ident === 'Apply') {
            $this->Aufrufen();
        }
    }

    /**
     * Die Beschriftungen der Zielwerte entstehen beim Anzeigen neu -- ein
     * geändertes Profil oder eine gelöschte Variable soll man in der Liste
     * sehen, ohne die Zeile anzufassen.
     */
    public function GetConfigurationForm(): string
    {
        $form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);

        foreach ($form['elements'] as $index => $element) {
            if (($element['name'] ?? '') === 'Members') {
                $form['elements'][$index]['values'] = $this->Beschriftet($this->Members());
            }
        }

        return json_encode($form);
    }

    /**
     * Das Bearbeitungsformular einer Zeile.
     *
     * Der Zielwert bekommt "SelectValue" -- Symcons Bedienelement, das sich
     * nach dem Profil der Variable richtet: ein Schalter mit Aus/An, ein
     * Schieber mit Prozent, eine Auswahl mit den Beschriftungen des Profils.
     * Ohne Variable gibt es nichts zu wählen, dann bleibt es verborgen.
     */
    public function Zeilenformular(int $VariableID): string
    {
        $bekannt = ($VariableID > 0) && IPS_VariableExists($VariableID);

        return json_encode([
            [
                'type'    => 'SelectVariable',
                'name'    => 'VariableID',
                'caption' => 'Variable',
                'onChange' => 'RGSSZ_ZeileVariable($id, $VariableID);',
            ],
            [
                'type'       => 'SelectValue',
                'name'       => 'Value',
                'caption'    => 'Zielwert',
                'variableID' => $bekannt ? $VariableID : 0,
                'visible'    => $bekannt,
            ],
            [
                'type'    => 'CheckBox',
                'name'    => 'Active',
                'caption' => 'Aktiv',
            ],
            [
                'type'    => 'NumberSpinner',
                'name'    => 'Delay',
                'caption' => 'Verzögerung',
                'minimum' => 0,
                'maximum' => 60000,
                'suffix'  => 'ms',
            ],
        ]);
    }

    /**
     * Im Bearbeitungsformular wurde eine andere Variable gewählt: der
     * Zielwert richtet sich neu aus und startet mit dem aktuellen Zustand.
     */
    public function ZeileVariable(int $VariableID): void
    {
        $bekannt = ($VariableID > 0) && IPS_VariableExists($VariableID);

        $this->UpdateFormField('Value', 'variableID', $bekannt ? $VariableID : 0);
        $this->UpdateFormField('Value', 'visible', $bekannt);
        if ($bekannt) {
            $this->UpdateFormField('Value', 'value', GetValue($VariableID));
        }
    }

    /**
     * Schreibt die Zielwerte. Auch für Ereignisse und Skripte gedacht.
     */
    public function Aufrufen(): int
    {
        $nurAbweichende = $this->ReadPropertyBoolean('OnlyChanged');
        $geschrieben = 0;

        foreach ($this->Members() as $mitglied) {
            if (!($mitglied['Active'] ?? true)) {
                continue;
            }
            $variableID = (int) $mitglied['VariableID'];
            if (($variableID == 0) || !IPS_VariableExists($variableID)) {
                continue;
            }

            $ziel = $this->RegoZielwert($variableID, $mitglied['Value'] ?? '');
            if ($nurAbweichende && $this->Gleich(GetValue($variableID), $ziel)) {
                continue;
            }

            $verzoegerung = (int) ($mitglied['Delay'] ?? 0);
            if ($verzoegerung > 0) {
                IPS_Sleep($verzoegerung);
            }

            $this->RegoWrite($variableID, $ziel);
            $geschrieben++;
        }

        $this->RegoPush('Aktiv', $this->IstAktiv());
        $this->SendDebug('Szene', "$geschrieben Mitglieder geschrieben", 0);

        return $geschrieben;
    }

    /**
     * Übernimmt den aktuellen Zustand aller Mitglieder als Zielwerte.
     */
    public function Speichern(): int
    {
        $mitglieder = $this->Members();
        $uebernommen = 0;

        foreach ($mitglieder as $index => $mitglied) {
            $variableID = (int) $mitglied['VariableID'];
            if (($variableID == 0) || !IPS_VariableExists($variableID)) {
                continue;
            }
            $mitglieder[$index]['Value'] = json_encode(GetValue($variableID));
            $mitglieder[$index]['Anzeige'] = $this->RegoWertText($variableID, GetValue($variableID));
            $uebernommen++;
        }

        IPS_SetProperty($this->InstanceID, 'Members', json_encode($mitglieder));
        IPS_ApplyChanges($this->InstanceID);

        return $uebernommen;
    }

    /**
     * Uebernimmt die bedienbaren Variablen aller REGOsymcon-Kacheln eines Raums
     * als Mitglieder -- mit dem aktuellen Zustand als Zielwert.
     *
     * Bewusst nur, was eine Szene sinnvoll setzen kann: Schalten, Helligkeit,
     * Rollladenposition und Soll-Temperatur. Tastbefehle wie Auf/Ab/Stopp
     * haben keinen Zustand, Messwerte nehmen nichts entgegen.
     */
    public function MitgliederAusRaum(int $kategorieId): int
    {
        if (($kategorieId == 0) || !IPS_ObjectExists($kategorieId)) {
            return 0;
        }

        // Kachel-Modul => die Eigenschaften, die eine Szene setzen kann.
        $ausKachel = [
            '{E5F57876-C2BE-4C9B-9D1E-237D9010ADA8}' => ['SwitchVariable'],                        // Schalten
            '{A4507CF7-C921-467C-BD01-699C862B9F5C}' => ['SwitchVariable', 'DimVariable'],          // Dimmen
            '{23F455EC-9236-480B-B02F-E10CE43DBDE2}' => ['PositionVariable'],                       // Jalousie
            '{FEB37553-F02A-4F1B-A669-15BCD71E0712}' => ['SetpointVariable'],                       // Klima
        ];

        $mitglieder = $this->Members();
        $vorhanden = [];
        foreach ($mitglieder as $mitglied) {
            $vorhanden[(int) $mitglied['VariableID']] = true;
        }

        $neu = 0;
        foreach (IPS_GetChildrenIDs($kategorieId) as $kind) {
            if (!IPS_InstanceExists($kind)) {
                continue;
            }
            $modul = IPS_GetInstance($kind)['ModuleInfo']['ModuleID'];
            if (!isset($ausKachel[$modul])) {
                continue;
            }

            $config = json_decode(IPS_GetConfiguration($kind), true);
            foreach ($ausKachel[$modul] as $eigenschaft) {
                $variableID = (int) ($config[$eigenschaft] ?? 0);
                if (($variableID == 0) || !IPS_VariableExists($variableID) || isset($vorhanden[$variableID])) {
                    continue;
                }
                $vorhanden[$variableID] = true;
                $mitglieder[] = [
                    'Active' => true,
                    'VariableID' => $variableID,
                    'Anzeige' => $this->RegoWertText($variableID, GetValue($variableID)),
                    'Value' => json_encode(GetValue($variableID)),
                    'Delay' => 0,
                ];
                $neu++;
            }
        }

        if ($neu > 0) {
            IPS_SetProperty($this->InstanceID, 'Members', json_encode($mitglieder));
            IPS_ApplyChanges($this->InstanceID);
        }

        return $neu;
    }

    public function GetVisualizationTile(): string
    {
        $label = trim($this->ReadPropertyString('Label')) ?: 'Aufrufen';

        $inner = $this->RegoButtons(
            '<button type="button" id="rego-szene" onclick="requestAction(\'Apply\', true)">'
            . htmlspecialchars($label) . '</button>'
        );

        $script = <<<'JS'
function regoRenderSzene(aktiv) {
    window.regoState.Aktiv = aktiv;
    document.getElementById('rego-szene').classList.toggle('szene-aktiv', aktiv === true);
}
window.regoHandlers['Aktiv'] = regoRenderSzene;
regoRenderSzene(window.regoState.Aktiv);
JS;

        return $this->RegoTile('szene', $inner, $script, ['Aktiv' => $this->IstAktiv()]);
    }

    /**
     * Die Mitgliederliste mit frisch beschrifteter Spalte "Zielwert".
     */
    private function Beschriftet(array $mitglieder): array
    {
        foreach ($mitglieder as $index => $mitglied) {
            $variableID = (int) ($mitglied['VariableID'] ?? 0);
            $mitglieder[$index]['Anzeige'] = (($variableID > 0) && IPS_VariableExists($variableID))
                ? $this->RegoWertText($variableID, $this->RegoZielwert($variableID, $mitglied['Value'] ?? ''))
                : 'Variable fehlt';
        }

        return $mitglieder;
    }

    /**
     * Aktiv heißt: jedes aktive Mitglied steht auf seinem Zielwert.
     */
    private function IstAktiv(): bool
    {
        $geprueft = 0;

        foreach ($this->Members() as $mitglied) {
            if (!($mitglied['Active'] ?? true)) {
                continue;
            }
            $variableID = (int) $mitglied['VariableID'];
            if (($variableID == 0) || !IPS_VariableExists($variableID)) {
                continue;
            }
            if (!$this->Gleich(GetValue($variableID), $this->RegoZielwert($variableID, $mitglied['Value'] ?? ''))) {
                return false;
            }
            $geprueft++;
        }

        return $geprueft > 0;
    }

    /**
     * Fließkommazahlen nie auf Gleichheit prüfen -- eine Rückmeldung vom Bus
     * trifft den gespeicherten Wert sonst nie genau.
     */
    private function Gleich($a, $b): bool
    {
        if (is_float($a) || is_float($b)) {
            return abs(((float) $a) - ((float) $b)) < 0.001;
        }
        return $a == $b;
    }

    private function Members(): array
    {
        $liste = json_decode($this->ReadPropertyString('Members'), true);
        return is_array($liste) ? $liste : [];
    }
}
