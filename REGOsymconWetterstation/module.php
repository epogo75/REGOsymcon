<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/RegoSymconTile.php';

/**
 * Wetterstation -- viele Werte auf einmal: oben die Messwerte als Raster mit
 * Beschriftung, darunter die Ja/Nein-Meldungen als Pillen.
 *
 * Das ist die Darstellung der Raumzusammenfassung aus REGObaseX1. Ob ein
 * Eintrag als Zahl oder als Pille erscheint, entscheidet der Typ der Variable,
 * nicht eine Namensliste.
 */
class REGOsymconWetterstation extends IPSModule
{
    use RegoSymconTile;

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
        return $this->RegoTile('wetterstation', $this->RegoRasterHtml(), $this->RegoRasterScript(),
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
