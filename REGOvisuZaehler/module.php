<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/RegoVisuTile.php';

/**
 * Zähler -- die Messwerte eines Energiezählers im Werteraster.
 *
 * Dieselbe Darstellung wie die Wetterstation: große Zahl mit kleiner Einheit
 * und Beschriftung darunter. Bedient wird nichts, ein Zähler nimmt nichts
 * entgegen.
 */
class REGOvisuZaehler extends IPSModule
{
    use RegoVisuTile;

    public function Create(): void
    {
        parent::Create();

        $this->RegisterPropertyString('Readings', '[]');

        $this->SetVisualizationType(1);
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
        return $this->RegoTile('zaehler', $this->RegoRasterHtml(), $this->RegoRasterScript(),
            ['Raster' => $this->RegoRasterState($this->Readings())]);
    }

    private function Readings(): array
    {
        $zeilen = json_decode($this->ReadPropertyString('Readings'), true);
        return is_array($zeilen) ? $zeilen : [];
    }

    private function PushState(): void
    {
        $this->RegoPush('Raster', $this->RegoRasterState($this->Readings()));
    }
}
