<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/RegoVisuTile.php';

/**
 * Jalousie -- Position als Text, rechts Auf / Stopp / Ab.
 *
 * Der Entwurf zeigt in der Zeile nur Auf und Ab; Stopp steckt dort im
 * Detailfenster. Da eine Symcon-Kachel kein Detailfenster hat und ein
 * Behang ohne Stopp nicht bedienbar ist, steht der dritte Taster hier mit
 * in der Zeile.
 *
 * Geschrieben wird wie in REGObaseX1: "Move" false für Auf, true für Ab
 * (KNX DPT 1.008), "Step" true als Stopp. Anlagen, die andersherum
 * verdrahtet sind, können den Fahrbefehl invertieren.
 */
class REGOvisuJalousie extends IPSModule
{
    use RegoVisuTile;

    public function Create(): void
    {
        parent::Create();

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
                $this->RegoWrite($target, $this->RegoCastNumber($target, (float) $Value));
                break;

            default:
                return;
        }

        $this->RegoPush('Position', $this->CurrentPosition());
    }

    public function GetVisualizationTile(): string
    {
        $inner = $this->RegoSliderLine('rego-pos', 'regoPos')
            . $this->RegoButtons(
                '<button type="button" onclick="requestAction(\'Up\', true)">Auf</button>'
                . '<button type="button" onclick="requestAction(\'Stop\', true)">Stopp</button>'
                . '<button type="button" onclick="requestAction(\'Down\', true)">Ab</button>'
            );

        $script = <<<'JS'
function regoRenderPosition(value) {
    window.regoState.Position = value;
    if (!window.regoDragging) {
        document.querySelector('#rego-pos input').value = value === null ? 0 : value;
        regoFill('rego-pos', value);
    }
    document.getElementById('rego-pos-label').textContent =
        value === null ? '–' : regoNumber(value) + '%';
}
function regoPosPreview(value) {
    window.regoDragging = true;
    regoFill('rego-pos', parseFloat(value));
    document.getElementById('rego-pos-label').textContent = regoNumber(parseFloat(value)) + '%';
}
function regoPos(value) {
    window.regoDragging = false;
    requestAction('Position', parseFloat(value));
}
window.regoHandlers['Position'] = regoRenderPosition;
regoRenderPosition(window.regoState.Position);
JS;

        return $this->RegoTile('jalousie', $inner, $script, ['Position' => $this->CurrentPosition()]);
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
