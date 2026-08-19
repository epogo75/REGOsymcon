<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/RegoVisuTile.php';

/**
 * Dimmen -- Zustand als Text, rechts ein schmaler Helligkeitsregler und der
 * Pillen-Schalter.
 *
 * Der Regler meldet erst beim Loslassen; während des Ziehens läuft nur die
 * Anzeige mit, sonst hängt an jedem Pixel ein Telegramm.
 */
class REGOvisuDimmen extends IPSModule
{
    use RegoVisuTile;

    public function Create(): void
    {
        parent::Create();

        $this->RegisterPropertyInteger('StatusVariable', 0);
        $this->RegisterPropertyInteger('SwitchVariable', 0);
        $this->RegisterPropertyInteger('BrightnessVariable', 0);
        $this->RegisterPropertyInteger('DimVariable', 0);

        // 2 = auch im Vollbild; aufgeklappt zeigt die Kachel die Objekte.
        $this->SetVisualizationType(2);
    }

    public function ApplyChanges(): void
    {
        parent::ApplyChanges();

        $this->RegoSyncMessages([
            $this->ReadPropertyInteger('StatusVariable'),
            $this->ReadPropertyInteger('SwitchVariable'),
            $this->ReadPropertyInteger('BrightnessVariable'),
            $this->ReadPropertyInteger('DimVariable')
        ]);

        $this->PushState();
    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data): void
    {
        if ($Message == VM_UPDATE) {
            $this->PushState();
        }
    }

    public function RequestAction($Ident, $Value): void
    {
        switch ($Ident) {
            case 'Switch':
                $target = $this->ReadPropertyInteger('SwitchVariable');
                if ($target == 0) {
                    $target = $this->ReadPropertyInteger('StatusVariable');
                }
                $this->RegoWrite($target, (bool) $Value);
                break;

            case 'Dim':
                $target = $this->ReadPropertyInteger('DimVariable');
                if ($target == 0) {
                    $target = $this->ReadPropertyInteger('BrightnessVariable');
                }
                $this->RegoWrite($target, $this->RegoCastNumber($target, (float) $Value));
                break;

            default:
                return;
        }

        $this->PushState();
    }

    public function GetVisualizationTile(): string
    {
        // Wie im Detail-Dialog: Regler oben, Knopf darunter ueber die Breite.
        $inner = $this->RegoSliderLine('rego-dim', 'regoDim')
            . $this->RegoButtons(
                '<button type="button" id="rego-onoff" class="onoff-button onoff-button-unknown" '
                . 'onclick="regoToggle()">unbekannt</button>'
            );

        $script = <<<'JS'
function regoRenderStatus(state) {
    window.regoState.Status = state;
    var button = document.getElementById('rego-onoff');
    button.className = 'onoff-button ' + (state === true ? 'onoff-button-on'
        : state === false ? 'onoff-button-off' : 'onoff-button-unknown');
    button.textContent = state === true ? 'AN' : state === false ? 'AUS' : 'unbekannt';
}
function regoRenderBrightness(value) {
    window.regoState.Brightness = value;
    if (!window.regoDragging) {
        document.querySelector('#rego-dim input').value = value === null ? 0 : value;
        regoFill('rego-dim', value);
    }
    document.getElementById('rego-dim-label').textContent =
        value === null ? '–' : regoNumber(value) + '%';
}
function regoDimPreview(value) {
    window.regoDragging = true;
    regoFill('rego-dim', parseFloat(value));
    document.getElementById('rego-dim-label').textContent = regoNumber(parseFloat(value)) + '%';
}
function regoDim(value) {
    window.regoDragging = false;
    requestAction('Dim', parseFloat(value));
}
function regoToggle() {
    var next = window.regoState.Status !== true;
    regoRenderStatus(next);
    requestAction('Switch', next);
}
window.regoHandlers['Status'] = regoRenderStatus;
window.regoHandlers['Brightness'] = regoRenderBrightness;
regoRenderStatus(window.regoState.Status);
regoRenderBrightness(window.regoState.Brightness);
JS;

        $inner .= $this->RegoObjekte(['Schalten' => $this->ReadPropertyInteger('SwitchVariable'),
            'Status' => $this->ReadPropertyInteger('StatusVariable'),
            'Dimmen' => $this->ReadPropertyInteger('DimVariable'),
            'Helligkeit' => $this->ReadPropertyInteger('BrightnessVariable')]);

        return $this->RegoTile('dimmen', $inner, $script, [
            'Status'     => $this->CurrentStatus(),
            'Brightness' => $this->CurrentBrightness()
        ]);
    }

    private function CurrentStatus(): ?bool
    {
        $value = $this->RegoValue($this->ReadPropertyInteger('StatusVariable'));
        if ($value === null) {
            // Ohne eigene Rückmeldung gilt "Helligkeit über 0" als an.
            $brightness = $this->CurrentBrightness();
            return $brightness === null ? null : ($brightness > 0);
        }
        return (bool) $value;
    }

    private function CurrentBrightness(): ?float
    {
        $source = $this->ReadPropertyInteger('BrightnessVariable');
        if ($source == 0) {
            $source = $this->ReadPropertyInteger('DimVariable');
        }
        $value = $this->RegoValue($source);
        return $value === null ? null : (float) $value;
    }

    private function PushState(): void
    {
        $this->RegoPush('Status', $this->CurrentStatus());
        $this->RegoPush('Brightness', $this->CurrentBrightness());
    }
}
