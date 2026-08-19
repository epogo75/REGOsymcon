<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/RegoVisuTile.php';

/**
 * Dimmen -- AN/AUS-Knopf plus Prozent-Slider, genau die Kombination, die
 * REGObaseX1 fuer den Funktionstyp "dimmen" zeigt (dort "Dimmen absolut" zum
 * Schreiben, "Zustandswert" als Rueckmeldung).
 *
 * Der Slider meldet erst beim Loslassen (change), waehrend des Ziehens laeuft
 * nur die Beschriftung mit -- sonst haengt bei jedem Pixel ein Telegramm am Bus.
 */
class REGOvisuDimmen extends IPSModule
{
    use RegoVisuTile;

    public function Create(): void
    {
        parent::Create();

        $this->RegisterPropertyString('Title', '');
        $this->RegisterPropertyBoolean('ShowHeader', true);
        $this->RegisterPropertyInteger('StatusVariable', 0);
        $this->RegisterPropertyInteger('SwitchVariable', 0);
        $this->RegisterPropertyInteger('BrightnessVariable', 0);
        $this->RegisterPropertyInteger('DimVariable', 0);

        $this->SetVisualizationType(1);
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
                $this->RegoWrite($target, $this->CastToVariable($target, (float) $Value));
                break;

            default:
                return;
        }

        $this->PushState();
    }

    public function GetVisualizationTile(): string
    {
        $controls =
            '<button type="button" id="rego-onoff" class="onoff-button onoff-button-unknown" '
            . 'onclick="regoToggle()">unbekannt</button>'
            . '<div class="percent-slider">'
            . '<input type="range" id="rego-dim" min="0" max="100" step="1" value="0" '
            . 'oninput="regoDimPreview(this.value)" onchange="regoDim(this.value)">'
            . '<span class="muted" id="rego-dim-label">–</span>'
            . '</div>';

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
    var slider = document.getElementById('rego-dim');
    if (!window.regoDragging) {
        slider.value = value === null ? 0 : value;
    }
    document.getElementById('rego-dim-label').textContent = value === null ? '–' : Math.round(value) + ' %';
}
function regoDimPreview(value) {
    window.regoDragging = true;
    document.getElementById('rego-dim-label').textContent = Math.round(value) + ' %';
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

        return $this->RegoTile('dimmen', $controls, $script, [
            'Status'     => $this->CurrentStatus(),
            'Brightness' => $this->CurrentBrightness()
        ]);
    }

    private function CurrentStatus(): ?bool
    {
        $value = $this->RegoValue($this->ReadPropertyInteger('StatusVariable'));
        if ($value === null) {
            // Ohne eigene Status-Rueckmeldung gilt "Helligkeit > 0" als an.
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

    /**
     * KNX-Dimmwerte liegen je nach Datenpunkt als Integer (0..100) oder als
     * Float vor -- ohne Anpassung lehnt Symcon das Schreiben ab.
     */
    private function CastToVariable(int $variableID, float $value)
    {
        if (($variableID != 0) && IPS_VariableExists($variableID)) {
            if (IPS_GetVariable($variableID)['VariableType'] == 1) {
                return (int) round($value);
            }
        }
        return $value;
    }

    private function PushState(): void
    {
        $this->RegoPush('Status', $this->CurrentStatus());
        $this->RegoPush('Brightness', $this->CurrentBrightness());
    }
}
