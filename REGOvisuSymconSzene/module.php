<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/RegoVisuTile.php';

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
class REGOvisuSymconSzene extends IPSModule
{
    use RegoVisuTile;

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

            $ziel = $this->NachTyp($variableID, (string) ($mitglied['Value'] ?? ''));
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
            $mitglieder[$index]['Value'] = $this->AlsText($variableID, GetValue($variableID));
            $uebernommen++;
        }

        IPS_SetProperty($this->InstanceID, 'Members', json_encode($mitglieder));
        IPS_ApplyChanges($this->InstanceID);

        return $uebernommen;
    }

    /**
     * Uebernimmt die bedienbaren Variablen aller REGOvisu-Kacheln eines Raums
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
                    'Value' => $this->AlsText($variableID, GetValue($variableID)),
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
            if (!$this->Gleich(GetValue($variableID), $this->NachTyp($variableID, (string) ($mitglied['Value'] ?? '')))) {
                return false;
            }
            $geprueft++;
        }

        return $geprueft > 0;
    }

    /**
     * Der Zielwert steht als Text in der Liste; hier bekommt er den Typ der
     * Zielvariable. Komma und Punkt gelten beide als Dezimaltrenner.
     */
    private function NachTyp(int $variableID, string $text)
    {
        switch (IPS_GetVariable($variableID)['VariableType']) {
            case 0:
                // Zuerst die Beschriftungen des Profils ("An", "Auf",
                // "ausgelöst"), danach die ueblichen Schreibweisen.
                $profil = $this->Profil($variableID);
                if ($profil !== null) {
                    $gesucht = mb_strtolower(trim($text));
                    foreach ($profil['Associations'] as $association) {
                        $name = $association['Name'];
                        // Beide Schreibweisen gelten: die des Profils und die
                        // uebersetzte, die beim Speichern eingetragen wird.
                        if ((mb_strtolower($name) === $gesucht)
                            || (mb_strtolower(IPS_Translate($this->InstanceID, $name)) === $gesucht)) {
                            return (bool) $association['Value'];
                        }
                    }
                }
                return in_array(mb_strtolower(trim($text)), ['1', 'true', 'an', 'ja', 'ein', 'auf'], true);
            case 1:
                return (int) round((float) str_replace(',', '.', $text));
            case 2:
                return (float) str_replace(',', '.', $text);
            default:
                return $text;
        }
    }

    /**
     * Der Zielwert steht als Text in der Liste. Hat die Variable ein Profil
     * mit Beschriftungen, wird die genommen -- "An" liest sich besser als "1",
     * und beim Aufrufen findet NachTyp() sie wieder.
     */
    private function AlsText(int $variableID, $wert): string
    {
        $beschriftung = $this->AusProfil($variableID, $wert);
        if ($beschriftung !== null) {
            return $beschriftung;
        }
        if (is_bool($wert)) {
            return $wert ? '1' : '0';
        }
        if (is_float($wert)) {
            return rtrim(rtrim(number_format($wert, 3, '.', ''), '0'), '.');
        }
        return (string) $wert;
    }

    /**
     * Beschriftung eines Wertes aus dem Profil der Variable, sonst null.
     */
    private function AusProfil(int $variableID, $wert): ?string
    {
        $profil = $this->Profil($variableID);
        if ($profil === null) {
            return null;
        }
        foreach ($profil['Associations'] as $association) {
            if (((bool) $association['Value']) === ((bool) $wert)) {
                // Symcons Beschriftungen sind intern englisch ("Off"/"On")
                // und werden erst beim Anzeigen uebersetzt -- in der
                // Mitgliederliste steht sonst Englisch. Eigene Profile
                // dafuer anzulegen waere der falsche Weg.
                return IPS_Translate($this->InstanceID, $association['Name']);
            }
        }
        return null;
    }

    private function Profil(int $variableID): ?array
    {
        if (!IPS_VariableExists($variableID)) {
            return null;
        }
        $variable = IPS_GetVariable($variableID);
        if ($variable['VariableType'] != 0) {
            return null;
        }
        $name = $variable['VariableCustomProfile'];
        if ($name === '') {
            $name = $variable['VariableProfile'];
        }
        if (($name === '') || !IPS_VariableProfileExists($name)) {
            return null;
        }
        $profil = IPS_GetVariableProfile($name);
        return empty($profil['Associations']) ? null : $profil;
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
