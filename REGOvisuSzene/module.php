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
        $this->RegisterPropertyString('Scenes', '[]');

        $this->SetVisualizationType(1);
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

        if (count($scenes) == 0) {
            return $this->RegoTile('szene', 'Keine Szene hinterlegt', '', '');
        }

        $buttons = '';
        foreach ($scenes as $scene) {
            $number = (int) ($scene['Number'] ?? 0);
            $label = trim((string) ($scene['Label'] ?? '')) ?: ('Szene ' . $number);
            $buttons .= '<button type="button" class="primary" '
                . 'onclick="requestAction(\'Scene\', ' . $number . ')">'
                . htmlspecialchars($label) . '</button>';
        }

        return $this->RegoTile('szene', '', '<span class="scenes">' . $buttons . '</span>', '');
    }
}
