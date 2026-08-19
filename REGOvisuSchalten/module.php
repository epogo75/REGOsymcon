<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/RegoVisuTile.php';

/**
 * Schalten -- Zustand als Text, rechts der Pillen-Schalter.
 *
 * Eingeschaltet glüht die Zeile bernsteinfarben, wie im Entwurf. Ein
 * unbekannter Zustand (Variable fehlt oder wurde nie beschrieben) zeigt einen
 * blassen Schalter statt zu behaupten, es sei aus.
 */
class REGOvisuSchalten extends IPSModule
{
    use RegoVisuTile;

    public function Create(): void
    {
        parent::Create();

        $this->RegisterPropertyInteger('StatusVariable', 0);
        $this->RegisterPropertyInteger('SwitchVariable', 0);

        $this->SetVisualizationType(1);
    }

    public function ApplyChanges(): void
    {
        parent::ApplyChanges();

        $this->RegoSyncMessages([
            $this->ReadPropertyInteger('StatusVariable'),
            $this->ReadPropertyInteger('SwitchVariable')
        ]);

        $this->RegoPush('Status', $this->CurrentState());
    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data): void
    {
        if ($Message == VM_UPDATE) {
            $this->RegoPush('Status', $this->CurrentState());
        }
    }

    public function RequestAction($Ident, $Value): void
    {
        if ($Ident !== 'Switch') {
            return;
        }

        $target = $this->ReadPropertyInteger('SwitchVariable');
        if ($target == 0) {
            $target = $this->ReadPropertyInteger('StatusVariable');
        }

        $this->RegoWrite($target, (bool) $Value);
        $this->RegoPush('Status', $this->CurrentState());
    }

    public function GetVisualizationTile(): string
    {
        $controls = '<button type="button" id="rego-onoff" class="onoff-button onoff-button-unknown" '
            . 'onclick="regoToggle()">unbekannt</button>';

        $script = <<<'JS'
function regoRender(state) {
    window.regoState.Status = state;
    var button = document.getElementById('rego-onoff');
    button.className = 'onoff-button ' + (state === true ? 'onoff-button-on'
        : state === false ? 'onoff-button-off' : 'onoff-button-unknown');
    button.textContent = state === true ? 'AN' : state === false ? 'AUS' : 'unbekannt';
}
function regoToggle() {
    var next = window.regoState.Status !== true;
    regoRender(next);
    requestAction('Switch', next);
}
window.regoHandlers['Status'] = regoRender;
regoRender(window.regoState.Status);
JS;

        return $this->RegoTile('schalten', '', $controls, $script, ['Status' => $this->CurrentState()]);
    }

    private function CurrentState(): ?bool
    {
        $value = $this->RegoValue($this->ReadPropertyInteger('StatusVariable'));
        return $value === null ? null : (bool) $value;
    }
}
