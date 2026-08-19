<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/RegoVisuTile.php';

/**
 * Klima -- "Ist 21.5 °C" links, rechts der Stepper minus / Soll / plus.
 *
 * Die Schrittweite von 0,5 K und das Runden auf halbe Grad sind aus
 * REGObaseX1 uebernommen (dort Math.round((soll + delta) * 2) / 2).
 */
class REGOvisuKlima extends IPSModule
{
    use RegoVisuTile;

    public function Create(): void
    {
        parent::Create();

        $this->RegisterPropertyString('Title', '');
        $this->RegisterPropertyBoolean('ShowHeader', true);
        $this->RegisterPropertyInteger('ActualVariable', 0);
        $this->RegisterPropertyInteger('SetpointVariable', 0);
        $this->RegisterPropertyInteger('SetpointFeedbackVariable', 0);
        $this->RegisterPropertyFloat('StepSize', 0.5);
        $this->RegisterPropertyFloat('MinValue', 5);
        $this->RegisterPropertyFloat('MaxValue', 30);

        $this->SetVisualizationType(1);
    }

    public function ApplyChanges(): void
    {
        parent::ApplyChanges();

        $this->RegoSyncMessages([
            $this->ReadPropertyInteger('ActualVariable'),
            $this->ReadPropertyInteger('SetpointVariable'),
            $this->ReadPropertyInteger('SetpointFeedbackVariable')
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
        if ($Ident !== 'Setpoint') {
            return;
        }

        $step = $this->ReadPropertyFloat('StepSize');
        if ($step <= 0) {
            $step = 0.5;
        }

        $current = $this->CurrentSetpoint();
        if ($current === null) {
            return;
        }

        $target = round(($current + ((float) $Value * $step)) / $step) * $step;
        $target = max($this->ReadPropertyFloat('MinValue'), min($this->ReadPropertyFloat('MaxValue'), $target));

        $this->RegoWrite($this->ReadPropertyInteger('SetpointVariable'), $target);
        $this->PushState();
    }

    public function GetVisualizationTile(): string
    {
        $controls =
            '<span class="muted visu-klima-ist" id="rego-ist">Ist –</span>'
            . '<div class="visu-klima-stepper">'
            . '<button type="button" onclick="requestAction(\'Setpoint\', -1)">−</button>'
            . '<span class="visu-klima-soll" id="rego-soll">–</span>'
            . '<button type="button" onclick="requestAction(\'Setpoint\', 1)">+</button>'
            . '</div>';

        $script = <<<'JS'
function regoTemp(value) {
    return (value === null || value === undefined) ? '—' : value.toFixed(1) + '°C';
}
function regoRenderActual(value) {
    window.regoState.Actual = value;
    document.getElementById('rego-ist').textContent = 'Ist ' + regoTemp(value);
}
function regoRenderSetpoint(value) {
    window.regoState.Setpoint = value;
    document.getElementById('rego-soll').textContent = regoTemp(value);
}
window.regoHandlers['Actual'] = regoRenderActual;
window.regoHandlers['Setpoint'] = regoRenderSetpoint;
regoRenderActual(window.regoState.Actual);
regoRenderSetpoint(window.regoState.Setpoint);
JS;

        return $this->RegoTile('klima', $controls, $script, [
            'Actual'   => $this->CurrentActual(),
            'Setpoint' => $this->CurrentSetpoint()
        ]);
    }

    private function CurrentActual(): ?float
    {
        $value = $this->RegoValue($this->ReadPropertyInteger('ActualVariable'));
        return $value === null ? null : (float) $value;
    }

    private function CurrentSetpoint(): ?float
    {
        $source = $this->ReadPropertyInteger('SetpointFeedbackVariable');
        if ($source == 0) {
            $source = $this->ReadPropertyInteger('SetpointVariable');
        }
        $value = $this->RegoValue($source);
        return $value === null ? null : (float) $value;
    }

    private function PushState(): void
    {
        $this->RegoPush('Actual', $this->CurrentActual());
        $this->RegoPush('Setpoint', $this->CurrentSetpoint());
    }
}
