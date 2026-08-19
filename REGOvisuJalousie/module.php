<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/RegoVisuTile.php';

/**
 * Jalousie -- die Dreiergruppe Auf / Stopp / Ab aus REGObaseX1.
 *
 * REGObase schreibt auf "Move" false fuer Auf und true fuer Ab (KNX DPT 1.008)
 * und benutzt "Step" mit true als Stopp. Anlagen, die andersherum verdrahtet
 * sind, koennen den Fahrbefehl in der Konfiguration invertieren.
 *
 * Der Positions-Slider erscheint nur, wenn eine Positions-Variable hinterlegt
 * ist -- in REGObase steckt er im Detail-Dialog, hier passt er direkt auf die
 * Kachel, weil Symcon-Kacheln frei skalierbar sind.
 */
class REGOvisuJalousie extends IPSModule
{
    use RegoVisuTile;

    public function Create(): void
    {
        parent::Create();

        $this->RegisterPropertyString('Title', '');
        $this->RegisterPropertyBoolean('ShowHeader', true);
        $this->RegisterPropertyInteger('MoveVariable', 0);
        $this->RegisterPropertyInteger('StepVariable', 0);
        $this->RegisterPropertyInteger('PositionVariable', 0);
        $this->RegisterPropertyInteger('PositionFeedbackVariable', 0);
        $this->RegisterPropertyBoolean('InvertMove', false);

        $this->SetVisualizationType(1);
    }

    public function ApplyChanges(): void
    {
        parent::ApplyChanges();

        $this->RegoSyncMessages([
            $this->ReadPropertyInteger('PositionVariable'),
            $this->ReadPropertyInteger('PositionFeedbackVariable')
        ]);

        $this->RegoPush('Position', $this->CurrentPosition());
    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data): void
    {
        if ($Message == VM_UPDATE) {
            $this->RegoPush('Position', $this->CurrentPosition());
        }
    }

    public function RequestAction($Ident, $Value): void
    {
        $invert = $this->ReadPropertyBoolean('InvertMove');

        switch ($Ident) {
            case 'Up':
                $this->RegoWrite($this->ReadPropertyInteger('MoveVariable'), $invert);
                break;

            case 'Down':
                $this->RegoWrite($this->ReadPropertyInteger('MoveVariable'), !$invert);
                break;

            case 'Stop':
                $this->RegoWrite($this->ReadPropertyInteger('StepVariable'), true);
                break;

            case 'Position':
                $target = $this->ReadPropertyInteger('PositionVariable');
                if ($target == 0) {
                    $target = $this->ReadPropertyInteger('PositionFeedbackVariable');
                }
                $position = (float) $Value;
                if (($target != 0) && IPS_VariableExists($target)
                    && (IPS_GetVariable($target)['VariableType'] == 1)) {
                    $position = (int) round($position);
                }
                $this->RegoWrite($target, $position);
                break;

            default:
                return;
        }

        $this->RegoPush('Position', $this->CurrentPosition());
    }

    public function GetVisualizationTile(): string
    {
        $controls =
            '<div class="visu-jalousie-buttons">'
            . '<button type="button" onclick="requestAction(\'Up\', true)">Auf</button>'
            . '<button type="button" onclick="requestAction(\'Stop\', true)">Stopp</button>'
            . '<button type="button" onclick="requestAction(\'Down\', true)">Ab</button>'
            . '</div>';

        if ($this->HasPosition()) {
            $controls .=
                '<div class="percent-slider">'
                . '<input type="range" id="rego-pos" min="0" max="100" step="1" value="0" '
                . 'oninput="regoPosPreview(this.value)" onchange="regoPos(this.value)">'
                . '<span class="muted" id="rego-pos-label">–</span>'
                . '</div>';
        }

        $script = <<<'JS'
function regoRenderPosition(value) {
    window.regoState.Position = value;
    var slider = document.getElementById('rego-pos');
    if (!slider) {
        return;
    }
    if (!window.regoDragging) {
        slider.value = value === null ? 0 : value;
    }
    document.getElementById('rego-pos-label').textContent = value === null ? '–' : Math.round(value) + ' %';
}
function regoPosPreview(value) {
    window.regoDragging = true;
    document.getElementById('rego-pos-label').textContent = Math.round(value) + ' %';
}
function regoPos(value) {
    window.regoDragging = false;
    requestAction('Position', parseFloat(value));
}
window.regoHandlers['Position'] = regoRenderPosition;
regoRenderPosition(window.regoState.Position);
JS;

        return $this->RegoTile('jalousie', $controls, $script, ['Position' => $this->CurrentPosition()]);
    }

    private function HasPosition(): bool
    {
        return ($this->ReadPropertyInteger('PositionVariable') != 0)
            || ($this->ReadPropertyInteger('PositionFeedbackVariable') != 0);
    }

    private function CurrentPosition(): ?float
    {
        $source = $this->ReadPropertyInteger('PositionFeedbackVariable');
        if ($source == 0) {
            $source = $this->ReadPropertyInteger('PositionVariable');
        }
        $value = $this->RegoValue($source);
        return $value === null ? null : (float) $value;
    }
}
