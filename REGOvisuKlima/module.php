<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/RegoVisuTile.php';

/**
 * Klima -- "21,5 °C · Soll 22,0 °C", rechts minus und plus.
 *
 * Schrittweite 0,5 K und das Runden auf halbe Grad sind aus REGObaseX1
 * übernommen.
 */
class REGOvisuKlima extends IPSModule
{
    use RegoVisuTile;

    public function Create(): void
    {
        parent::Create();

        $this->RegisterPropertyInteger('ActualVariable', 0);
        $this->RegisterPropertyInteger('SetpointVariable', 0);
        $this->RegisterPropertyInteger('SetpointFeedbackVariable', 0);
        $this->RegisterPropertyFloat('StepSize', 0.5);
        $this->RegisterPropertyFloat('MinValue', 5);
        $this->RegisterPropertyFloat('MaxValue', 30);

        // 2 = auch im Vollbild rendern; dort ist Platz fuer den Verlauf.
        $this->SetVisualizationType(2);
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
        // Wie im Detail-Dialog: links der Istwert, rechts der Sollwert-Steller.
        $inner = '<div class="line">'
            . '<span class="readout">'
            . '<span class="readout-label">Ist</span>'
            . '<span class="readout-value" id="rego-ist">–</span>'
            . '</span>'
            . '<span class="stepper">'
            . '<button type="button" aria-label="Soll-Temperatur senken" '
            . 'onclick="requestAction(\'Setpoint\', -1)">−</button>'
            . '<span id="rego-soll">–</span>'
            . '<button type="button" aria-label="Soll-Temperatur erhöhen" '
            . 'onclick="requestAction(\'Setpoint\', 1)">+</button>'
            . '</span>'
            . '</div>'
            . $this->RegoChart($this->ReadPropertyInteger('ActualVariable'));

        $script = <<<'JS'
function regoRenderActual(value) {
    window.regoState.Actual = value;
    document.getElementById('rego-ist').textContent =
        value === null ? '–' : regoNumber(value, 1) + ' °C';
}
function regoRenderSetpoint(value) {
    window.regoState.Setpoint = value;
    document.getElementById('rego-soll').textContent =
        value === null ? '–' : regoNumber(value, 1) + ' °C';
}
window.regoHandlers['Actual'] = regoRenderActual;
window.regoHandlers['Setpoint'] = regoRenderSetpoint;
regoRenderActual(window.regoState.Actual);
regoRenderSetpoint(window.regoState.Setpoint);
JS;

        return $this->RegoTile('klima', $inner, $script, [
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
