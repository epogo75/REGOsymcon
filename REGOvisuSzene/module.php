<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/RegoVisuTile.php';

/**
 * Szene -- eine Reihe gleich breiter Knoepfe, je einer pro Szene.
 *
 * Wie in REGObaseX1 hat die Szenen-Kachel keinen Detail-Dialog und zeigt bei
 * leerer Liste "Nicht konfiguriert" statt einer leeren Flaeche.
 */
class REGOvisuSzene extends IPSModule
{
    use RegoVisuTile;

    public function Create(): void
    {
        parent::Create();

        $this->RegisterPropertyString('Title', '');
        $this->RegisterPropertyBoolean('ShowHeader', true);
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
        if (!is_array($scenes) || (count($scenes) == 0)) {
            $controls = '<p class="muted visu-szene-empty">Nicht konfiguriert</p>';
        } else {
            $buttons = '';
            foreach ($scenes as $scene) {
                $label = trim((string) ($scene['Label'] ?? ''));
                $number = (int) ($scene['Number'] ?? 0);
                if ($label === '') {
                    $label = 'Szene ' . $number;
                }
                $buttons .= '<button type="button" onclick="requestAction(\'Scene\', ' . $number . ')">'
                    . htmlspecialchars($label) . '</button>';
            }
            $controls = '<div class="visu-szene-buttons">' . $buttons . '</div>';
        }

        return $this->RegoTile('szene', $controls, '');
    }
}
