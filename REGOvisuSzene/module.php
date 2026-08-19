<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/RegoVisuTile.php';

/**
 * Szene -- je Szene ein Knopf in Akzentfarbe.
 *
 * Eine einzelne Szene heißt schlicht "Starten"; bei mehreren steht auf jedem
 * Knopf der Szenenname. Ohne konfigurierte Szene sagt die Kachel das auch,
 * statt leer zu bleiben.
 */
class REGOvisuSzene extends IPSModule
{
    use RegoVisuTile;

    public function Create(): void
    {
        parent::Create();

        $this->RegisterPropertyInteger('SceneVariable', 0);
        $this->RegisterPropertyInteger('ModeVariable', 0);
        $this->RegisterPropertyString('Scenes', '[]');

        // 2 = auch im Vollbild; aufgeklappt zeigt die Kachel die Objekte.
        $this->SetVisualizationType(2);
    }

    public function ApplyChanges(): void
    {
        parent::ApplyChanges();

        $this->RegoSyncMessages([]);
    }

    public function RequestAction($Ident, $Value): void
    {
        if ($Ident !== 'Scene') {
            return;
        }

        // DPT 18.001 hat zwei Teile: ein Bit "Aufrufen/Speichern" und die
        // Nummer. Ohne das Bit auf "Aufrufen" wuerde ein Klick die Szene unter
        // Umstaenden ueberschreiben statt sie zu starten.
        $modus = $this->ReadPropertyInteger('ModeVariable');
        if ($modus != 0) {
            $this->RegoWrite($modus, false);
        }

        $target = $this->ReadPropertyInteger('SceneVariable');
        $number = (int) $Value;

        if (($target != 0) && IPS_VariableExists($target)
            && (IPS_GetVariable($target)['VariableType'] == 2)) {
            $this->RegoWrite($target, (float) $number);
            return;
        }

        $this->RegoWrite($target, $number);
    }

    public function GetVisualizationTile(): string
    {
        $scenes = json_decode($this->ReadPropertyString('Scenes'), true);
        if (!is_array($scenes)) {
            $scenes = [];
        }

        $objekte = $this->RegoObjekte([
            'Szenennummer' => $this->ReadPropertyInteger('SceneVariable'),
            'Aufrufen/Speichern' => $this->ReadPropertyInteger('ModeVariable'),
        ]);

        if (count($scenes) == 0) {
            return $this->RegoTile('szene',
                '<div class="line"><span class="muted">Keine Szene hinterlegt</span></div>' . $objekte, '');
        }

        $buttons = '';
        foreach ($scenes as $scene) {
            $number = (int) ($scene['Number'] ?? 0);
            $label = trim((string) ($scene['Label'] ?? '')) ?: ('Szene ' . $number);
            $buttons .= '<button type="button" onclick="requestAction(\'Scene\', ' . $number . ')">'
                . htmlspecialchars($label) . '</button>';
        }

        return $this->RegoTile('szene', $this->RegoButtons($buttons) . $objekte, '');
    }
}
