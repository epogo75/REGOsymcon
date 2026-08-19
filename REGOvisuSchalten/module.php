<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/RegoVisuTile.php';

/**
 * Schalten -- der AN/AUS-Knopf aus REGObaseX1.
 *
 * Wie dort ist AN rot (--danger) und AUS neutral; ein unbekannter Zustand
 * (Variable fehlt oder wurde noch nie beschrieben) bleibt blass statt zu luegen.
 */
class REGOvisuSchalten extends IPSModule
{
    use RegoVisuTile;

    public function Create(): void
    {
        parent::Create();

        $this->RegisterPropertyString('Title', '');
        $this->RegisterPropertyBoolean('ShowHeader', true);
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

        return $this->RegoTile('schalten', $controls, $script, ['Status' => $this->CurrentState()]);
    }

    /**
     * true / false / null -- null heisst "kein verwertbarer Status".
     */
    private function CurrentState(): ?bool
    {
        $value = $this->RegoValue($this->ReadPropertyInteger('StatusVariable'));
        if ($value === null) {
            return null;
        }
        return (bool) $value;
    }
}
