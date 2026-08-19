<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/RegoVisuTile.php';

/**
 * Zähler -- Leistung, Zählerstand und Heute, dahinter die weiteren Messwerte.
 *
 * Auswahl und Zuschnitt folgen der Zähler-Karte von REGObase: drei Felder mit
 * gedämpftem Etikett und Wert. "Heute" steht in keiner Variable, es ist die
 * Differenz zum ersten aufgezeichneten Zählerstand seit Mitternacht -- ohne
 * Aufzeichnung bleibt das Feld leer.
 *
 * Bedient wird nichts; ein Zähler nimmt nichts entgegen.
 */
class REGOvisuZaehler extends IPSModule
{
    use RegoVisuTile;

    public function Create(): void
    {
        parent::Create();

        $this->RegisterPropertyInteger('PowerVariable', 0);
        $this->RegisterPropertyInteger('EnergyVariable', 0);
        $this->RegisterPropertyString('Readings', '[]');

        $this->SetVisualizationType(1);
    }

    public function ApplyChanges(): void
    {
        parent::ApplyChanges();

        $ids = [$this->ReadPropertyInteger('PowerVariable'), $this->ReadPropertyInteger('EnergyVariable')];
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
        $felder = $this->CurrentFelder();
        return $this->RegoTile('zaehler', $this->RegoFelder($felder),
            $this->RegoFelderScript(), ['Felder' => $felder]);
    }

    /**
     * Die Felder der Karte, zeilenweise: vorn Leistung, Zaehlerstand und
     * Heute, danach je Gruppe eine eigene Zeile -- die drei Spannungen bleiben
     * so nebeneinander, die drei Stroeme ebenso.
     */
    private function CurrentFelder(): array
    {
        $kopf = [];

        $leistung = $this->ReadPropertyInteger('PowerVariable');
        if (($leistung != 0) && IPS_VariableExists($leistung)) {
            $kopf[] = ['label' => 'Leistung', 'text' => $this->Formatiere($leistung, 0, 'W')];
        }

        $energie = $this->ReadPropertyInteger('EnergyVariable');
        if (($energie != 0) && IPS_VariableExists($energie)) {
            $kopf[] = ['label' => 'Zählerstand', 'text' => $this->Formatiere($energie, 0, 'kWh')];
            $kopf[] = ['label' => 'Heute', 'text' => $this->Heute($energie)];
        }

        $ohneGruppe = [];
        $gruppen = [];

        foreach ($this->Readings() as $zeile) {
            $variableID = (int) ($zeile['VariableID'] ?? 0);
            if (($variableID == 0) || !IPS_VariableExists($variableID)) {
                continue;
            }

            $feld = [
                'label' => trim((string) ($zeile['Label'] ?? '')) ?: IPS_GetName($variableID),
                'text' => $this->Formatiere($variableID, (int) ($zeile['Digits'] ?? -1),
                    trim((string) ($zeile['Unit'] ?? ''))),
            ];

            $gruppe = trim((string) ($zeile['Group'] ?? ''));
            if ($gruppe === '') {
                $ohneGruppe[] = $feld;
            } else {
                $gruppen[$gruppe][] = $feld;
            }
        }

        $zeilen = [];
        if (!empty($kopf)) {
            $zeilen[] = $kopf;
        }
        if (!empty($ohneGruppe)) {
            $zeilen[] = $ohneGruppe;
        }
        foreach ($gruppen as $felder) {
            $zeilen[] = $felder;
        }

        return $zeilen;
    }

    /**
     * Verbrauch seit Mitternacht: Zählerstand jetzt minus dem ersten
     * aufgezeichneten Wert des Tages.
     */
    private function Heute(int $variableID): string
    {
        $archive = IPS_GetInstanceListByModuleID('{43192F0B-135B-4CE7-A0A7-1475603F3060}');
        if (empty($archive) || !AC_GetLoggingStatus($archive[0], $variableID)) {
            return '–';
        }

        $mitternacht = strtotime('today midnight');
        $werte = AC_GetLoggedValues($archive[0], $variableID, $mitternacht, time(), 0);

        // Von hinten, also vom aeltesten Wert des Tages her. Nullwerte
        // ueberspringen: ein Zaehlerstand von 0 ist der Startwert, den die
        // Variable beim Anlegen bekommt, kein gemessener Stand.
        $anfang = null;
        for ($i = count($werte) - 1; $i >= 0; $i--) {
            if (((float) $werte[$i]['Value']) > 0) {
                $anfang = (float) $werte[$i]['Value'];
                break;
            }
        }
        if ($anfang === null) {
            return '–';
        }

        $verbrauch = ((float) GetValue($variableID)) - $anfang;

        return number_format(max(0, $verbrauch), 2, ',', '') . ' kWh';
    }

    private function Formatiere(int $variableID, int $stellen, string $einheit, bool $ausProfil = true): string
    {
        $variable = IPS_GetVariable($variableID);
        $profil = $variable['VariableCustomProfile'];
        if ($profil === '') {
            $profil = $variable['VariableProfile'];
        }
        $info = ($profil !== '') && IPS_VariableProfileExists($profil) ? IPS_GetVariableProfile($profil) : null;

        if ($stellen < 0) {
            $stellen = ($info !== null) ? (int) $info['Digits'] : 1;
        }
        if (($einheit === '') && $ausProfil) {
            $einheit = ($info !== null) ? trim((string) $info['Suffix']) : '';
        }

        return trim(number_format((float) GetValue($variableID), max(0, $stellen), ',', '') . ' ' . $einheit);
    }

    private function Readings(): array
    {
        $zeilen = json_decode($this->ReadPropertyString('Readings'), true);
        return is_array($zeilen) ? $zeilen : [];
    }

    private function PushState(): void
    {
        $this->RegoPush('Felder', $this->CurrentFelder());
    }
}
