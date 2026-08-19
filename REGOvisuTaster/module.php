<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/RegoVisuTile.php';

/**
 * Taster -- ein Knopf über die ganze Breite, der auslöst.
 *
 * Ein Taster hat keinen Zustand: die Kachel zeigt deshalb keinen, sondern
 * schickt beim Drücken einmal "wahr" auf die Adresse (KNX DPT 1.017).
 */
class REGOvisuTaster extends IPSModule
{
    use RegoVisuTile;

    public function Create(): void
    {
        parent::Create();

        $this->RegisterPropertyInteger('TriggerVariable', 0);
        $this->RegisterPropertyString('Label', 'Auslösen');

        $this->SetVisualizationType(1);
    }

    public function ApplyChanges(): void
    {
        parent::ApplyChanges();

        $this->RegoSyncMessages([]);
    }

    public function RequestAction($Ident, $Value): void
    {
        if ($Ident !== 'Trigger') {
            return;
        }

        $this->RegoWrite($this->ReadPropertyInteger('TriggerVariable'), true);
    }

    public function GetVisualizationTile(): string
    {
        $label = trim($this->ReadPropertyString('Label'));
        if ($label === '') {
            $label = 'Auslösen';
        }

        $inner = $this->RegoButtons(
            '<button type="button" onclick="requestAction(\'Trigger\', true)">'
            . htmlspecialchars($label) . '</button>'
        );

        return $this->RegoTile('taster', $inner, '');
    }
}
