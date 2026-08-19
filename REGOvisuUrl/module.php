<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/RegoVisuTile.php';

/**
 * URL-Aufruf -- ein Knopf, der eine Seite in einem neuen Tab öffnet.
 *
 * Diese Funktion hat in REGOdeploy keine Gruppenadresse, sondern nur eine
 * Adresse im Netz; die Kachel bedient deshalb nichts auf dem Bus.
 */
class REGOvisuUrl extends IPSModule
{
    use RegoVisuTile;

    public function Create(): void
    {
        parent::Create();

        $this->RegisterPropertyString('Url', '');
        $this->RegisterPropertyString('Label', 'Öffnen');

        $this->SetVisualizationType(1);
    }

    public function ApplyChanges(): void
    {
        parent::ApplyChanges();

        $this->RegoSyncMessages([]);
    }

    public function GetVisualizationTile(): string
    {
        $url = trim($this->ReadPropertyString('Url'));
        if ($url === '') {
            return $this->RegoTile('url',
                '<div class="line"><span class="muted">Keine Adresse hinterlegt</span></div>', '');
        }

        $label = trim($this->ReadPropertyString('Label'));
        if ($label === '') {
            $label = 'Öffnen';
        }

        // Als Verweis, nicht als Knopf mit Skript: so kann die
        // Visualisierung die Seite selbst in einem neuen Tab oeffnen.
        $inner = '<div class="buttons">'
            . '<a class="knopf-link" href="' . htmlspecialchars($url, ENT_QUOTES) . '" '
            . 'target="_blank" rel="noopener noreferrer">' . htmlspecialchars($label) . '</a>'
            . '</div>';

        return $this->RegoTile('url', $inner, '');
    }
}
