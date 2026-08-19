<?php

declare(strict_types=1);

/**
 * Gemeinsame Basis aller REGOvisu-Kacheln.
 *
 * Farben, Radien, Abstaende und Markup sind aus REGObaseX1 uebernommen, damit
 * eine Symcon-Kachel neben einer REGObase-Kachel nicht auffaellt. Die Tokens
 * stehen bewusst als CSS-Variablen im Kachel-HTML und nicht in einer externen
 * Datei -- die Kachel-Visualisierung laedt das HTML in ein iframe, das nichts
 * nachladen kann ausser dem, was Symcon selbst ausliefert (/icons.js).
 */
trait RegoVisuTile
{
    // ------------------------------------------------------------------
    // Verdrahtung an fremde Symcon-Variablen
    // ------------------------------------------------------------------

    /**
     * Meldet die Kachel auf VM_UPDATE aller uebergebenen Variablen an und
     * loest dabei die Anmeldungen des vorherigen Konfigurationsstandes.
     */
    protected function RegoSyncMessages(array $variableIDs): void
    {
        foreach ($this->GetMessageList() as $senderID => $messages) {
            foreach ($messages as $message) {
                if ($message == VM_UPDATE) {
                    $this->UnregisterMessage($senderID, VM_UPDATE);
                }
            }
        }

        foreach (array_unique(array_filter($variableIDs)) as $variableID) {
            if (IPS_VariableExists($variableID)) {
                $this->RegisterMessage($variableID, VM_UPDATE);
            }
        }
    }

    /**
     * Wert einer fremden Variable, null wenn nicht (mehr) vorhanden.
     */
    protected function RegoValue(int $variableID)
    {
        if (($variableID == 0) || !IPS_VariableExists($variableID)) {
            return null;
        }
        return GetValue($variableID);
    }

    /**
     * Schreibt auf eine fremde Variable. Bevorzugt wird die Aktion der
     * Variable (KNX-Telegramm, Geraetebefehl); nur wenn keine hinterlegt ist,
     * wird der Wert direkt gesetzt, damit reine Merker-Variablen auch
     * funktionieren.
     */
    protected function RegoWrite(int $variableID, $value): bool
    {
        if (($variableID == 0) || !IPS_VariableExists($variableID)) {
            return false;
        }

        $variable = IPS_GetVariable($variableID);
        if (($variable['VariableAction'] != 0) || ($variable['VariableCustomAction'] > 1)) {
            return @RequestAction($variableID, $value);
        }

        return @SetValue($variableID, $value);
    }

    /**
     * Min/Max eines Profils, fuer Slider und Stepper-Grenzen.
     */
    protected function RegoProfileRange(int $variableID, float $fallbackMin, float $fallbackMax): array
    {
        if (($variableID == 0) || !IPS_VariableExists($variableID)) {
            return [$fallbackMin, $fallbackMax];
        }

        $variable = IPS_GetVariable($variableID);
        $profile = $variable['VariableCustomProfile'];
        if ($profile === '') {
            $profile = $variable['VariableProfile'];
        }
        if (($profile === '') || !IPS_VariableProfileExists($profile)) {
            return [$fallbackMin, $fallbackMax];
        }

        $info = IPS_GetVariableProfile($profile);
        if ($info['MaxValue'] <= $info['MinValue']) {
            return [$fallbackMin, $fallbackMax];
        }

        return [(float) $info['MinValue'], (float) $info['MaxValue']];
    }

    /**
     * Schickt einen Wert an das Kachel-HTML. Dort nimmt ihn handleMessage()
     * entgegen und verteilt ihn anhand des Idents.
     */
    protected function RegoPush(string $ident, $value): void
    {
        $this->UpdateVisualizationValue(json_encode(['Ident' => $ident, 'Value' => $value]));
    }

    // ------------------------------------------------------------------
    // HTML
    // ------------------------------------------------------------------

    /**
     * Baut die komplette Kachel: Style, Kopfzeile, Bedienelemente, Skript.
     */
    protected function RegoTile(string $type, string $controls, string $script, array $state = []): string
    {
        $head = '';
        if ($this->ReadPropertyBoolean('ShowHeader')) {
            $head =
                '<div class="visu-tile-head visu-tile-head-static">'
                . '<span class="visu-tile-head-icon">' . $this->RegoIcon($type, 18) . '</span>'
                . '<span class="visu-tile-head-name">' . htmlspecialchars($this->RegoTitle()) . '</span>'
                . '</div>';
        }

        return $this->RegoCss()
            . '<div class="funktion-tile visu-funktion-tile" data-type="' . $type . '">'
            . $head
            . '<div class="visu-tile-controls">' . $controls . '</div>'
            . '</div>'
            . '<script>'
            . 'window.regoState = ' . json_encode($state) . ';'
            . $this->RegoBoot()
            . $script
            . '</script>';
    }

    /**
     * Angezeigter Name: eigene Bezeichnung, sonst der Instanzname.
     */
    protected function RegoTitle(): string
    {
        $title = trim($this->ReadPropertyString('Title'));
        if ($title !== '') {
            return $title;
        }
        return IPS_GetName($this->InstanceID);
    }

    /**
     * Lucide-nahe Inline-SVGs -- dieselbe Bildsprache wie in REGObaseX1
     * (dort lucide-react), aber ohne externe Abhaengigkeit.
     */
    protected function RegoIcon(string $type, int $size): string
    {
        $paths = [
            // lightbulb
            'schalten' => '<path d="M15 14c.2-1 .7-1.7 1.5-2.5 1-.9 1.5-2.2 1.5-3.5A6 6 0 0 0 6 8c0 1 .2 2.2 1.5 3.5.7.7 1.3 1.5 1.5 2.5"/><path d="M9 18h6"/><path d="M10 22h4"/>',
            'dimmen'   => '<path d="M15 14c.2-1 .7-1.7 1.5-2.5 1-.9 1.5-2.2 1.5-3.5A6 6 0 0 0 6 8c0 1 .2 2.2 1.5 3.5.7.7 1.3 1.5 1.5 2.5"/><path d="M9 18h6"/><path d="M10 22h4"/>',
            // blinds
            'jalousie' => '<path d="M3 3h18"/><path d="M20 7H8"/><path d="M20 11H8"/><path d="M10 19h10"/><path d="M8 15h12"/><path d="M4 3v14a2 2 0 0 0 2 2 2 2 0 0 0 2-2V3"/>',
            // thermometer
            'klima'    => '<path d="M14 4v10.54a4 4 0 1 1-4 0V4a2 2 0 0 1 4 0Z"/>',
            // sparkles
            'szene'    => '<path d="M9.9 2.1 8.5 6.4 4.2 7.8l4.3 1.4 1.4 4.3 1.4-4.3 4.3-1.4-4.3-1.4Z"/><path d="M18 12l-.7 2.1-2.1.7 2.1.7.7 2.1.7-2.1 2.1-.7-2.1-.7Z"/><path d="M6.5 16l-.5 1.5-1.5.5 1.5.5.5 1.5.5-1.5 1.5-.5-1.5-.5Z"/>',
        ];

        $path = $paths[$type] ?? '<circle cx="12" cy="12" r="9"/>';

        return '<svg xmlns="http://www.w3.org/2000/svg" width="' . $size . '" height="' . $size . '" '
            . 'viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" '
            . 'stroke-linecap="round" stroke-linejoin="round">' . $path . '</svg>';
    }

    /**
     * Design-Tokens und Bedienelemente aus REGObaseX1.
     */
    protected function RegoCss(): string
    {
        return <<<'CSS'
<style>
:root{
    --bg:#0c0c0e; --surface:#131316; --surface-2:#1a1a1e; --surface-3:#202024;
    --surface-hover:#232327; --border:rgba(255,255,255,.06); --border-strong:rgba(255,255,255,.14);
    --text:#f2f2f4; --text-muted:#86868f; --text-faint:#55555c;
    --accent:#b9ff5c; --accent-hover:#a3ea48; --accent-contrast:#0e1a02; --accent-bg:rgba(185,255,92,.14);
    --danger:#f85149; --danger-bg:rgba(248,81,73,.13); --danger-border:rgba(248,81,73,.32);
    --radius-lg:10px; --radius-md:8px; --radius-sm:6px;
    --font-sans:"Inter",-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",sans-serif;
}
:root[data-rego-theme="light"]{
    --bg:#f7f7f6; --surface:#ffffff; --surface-2:#f1f1ef; --surface-3:#e7e7e4;
    --surface-hover:#dedede; --border:rgba(0,0,0,.08); --border-strong:rgba(0,0,0,.16);
    --text:#1a1a1c; --text-muted:#65656d; --text-faint:#8b8b93;
    --accent:#4d7616; --accent-hover:#3f6111; --accent-contrast:#ffffff; --accent-bg:rgba(77,118,22,.1);
    --danger:#cf222e; --danger-bg:rgba(207,34,46,.1); --danger-border:rgba(207,34,46,.28);
}
*,*:before,*:after{box-sizing:border-box}
html,body{height:100%;-webkit-tap-highlight-color:transparent}
body{
    margin:0!important;padding:0!important;
    font-family:var(--font-sans)!important;font-size:14px;
    color:var(--text);background:transparent;
    -webkit-font-smoothing:antialiased;text-rendering:optimizeLegibility;
    user-select:none;
}
.funktion-tile{
    display:flex;flex-direction:column;gap:.7rem;height:100%;
    padding:1.1rem 1.3rem;background:var(--surface);
    border:1px solid var(--border);border-radius:var(--radius-md);
}
.visu-tile-head{display:flex;align-items:center;gap:.55rem;width:100%;color:var(--text)}
.visu-tile-head-icon{
    display:inline-flex;align-items:center;justify-content:center;flex:0 0 auto;
    width:30px;height:30px;border-radius:var(--radius-sm);
    background:var(--surface-3);color:var(--accent);
}
.visu-tile-head-name{
    font-weight:700;font-size:.95rem;min-width:0;
    overflow:hidden;text-overflow:ellipsis;white-space:nowrap;
}
.visu-tile-controls{display:flex;align-items:center;gap:.6rem;flex-wrap:wrap}
button{
    font-family:inherit;font-size:.8rem;font-weight:600;color:var(--text);
    background:var(--surface-3);border:1px solid var(--border-strong);
    border-radius:var(--radius-sm);padding:.42rem .85rem;cursor:pointer;
    transition:background .15s ease,border-color .15s ease,opacity .15s ease;
}
button:hover{background:var(--surface-hover)}
button:disabled{opacity:.5;cursor:not-allowed}
.muted{color:var(--text-faint);font-size:.8rem}
.onoff-button{
    min-width:4.5rem;min-height:2.4rem;padding:.4rem .9rem;border-radius:var(--radius-md);
    font-weight:700;font-size:.85rem;letter-spacing:.02em;cursor:pointer;
    transition:background-color .15s ease,border-color .15s ease,color .15s ease;
}
.onoff-button-on{background:var(--danger-bg);border:1px solid var(--danger-border);color:var(--danger)}
.onoff-button-off{background:var(--surface-3);border:1px solid var(--border);color:var(--text)}
.onoff-button-unknown{background:var(--surface-3);border:1px solid var(--border);color:var(--text-faint)}
.percent-slider{display:flex;align-items:center;gap:.35rem;flex:1 1 auto;min-width:0}
.percent-slider .muted{min-width:2.8rem;text-align:right;font-variant-numeric:tabular-nums}
.percent-slider input[type=range]{width:auto;flex:1 1 auto;min-width:4rem;max-width:9rem}
input[type=range]{
    height:24px;margin:0;background:transparent;cursor:pointer;
    -webkit-appearance:none;-moz-appearance:none;appearance:none;
}
input[type=range]::-webkit-slider-runnable-track{height:6px;border-radius:999px;background:var(--border-strong)}
input[type=range]::-moz-range-track{height:6px;border-radius:999px;background:var(--border-strong)}
input[type=range]::-webkit-slider-thumb{
    -webkit-appearance:none;appearance:none;width:20px;height:20px;margin-top:-7px;
    border-radius:50%;background:var(--accent);
}
input[type=range]::-moz-range-thumb{width:20px;height:20px;border:none;border-radius:50%;background:var(--accent)}
input[type=range]:focus,input[type=range]:focus-visible,button:focus,button:focus-visible{outline:none;box-shadow:none}
.visu-jalousie-buttons{display:flex;gap:.5rem;width:100%}
.visu-jalousie-buttons button{flex:1;min-height:2.4rem}
.visu-klima-ist{white-space:nowrap}
.visu-klima-stepper{display:flex;align-items:center;gap:.5rem;margin-left:auto}
.visu-klima-stepper button{flex:0 0 auto;width:2.5rem;min-height:2.25rem;font-size:1.05rem}
.visu-klima-soll{font-weight:600;font-variant-numeric:tabular-nums;min-width:3.6rem;text-align:center}
.visu-szene-buttons{display:flex;gap:.5rem;width:100%;flex-wrap:wrap}
.visu-szene-buttons button{flex:1 1 auto;min-width:0;min-height:2.4rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.visu-szene-empty{font-size:.85rem}
.rego-error{color:var(--danger);font-size:.8rem}
</style>
CSS;
    }

    /**
     * Theme-Erkennung und Nachrichtenverteilung.
     *
     * Die Kachel-Visualisierung haengt ihre Farben als Query-Parameter an die
     * iframe-URL; aus der Helligkeit von "cardcolor" laesst sich ableiten, ob
     * Symcon gerade hell oder dunkel dargestellt wird. So folgt die Kachel dem
     * Symcon-Theme statt dem Betriebssystem-Theme.
     */
    protected function RegoBoot(): string
    {
        return <<<'JS'
(function () {
    var card = new URLSearchParams(window.location.search).get('cardcolor');
    if (card && /^[0-9a-fA-F]{6}$/.test(card)) {
        var r = parseInt(card.substr(0, 2), 16),
            g = parseInt(card.substr(2, 2), 16),
            b = parseInt(card.substr(4, 2), 16);
        if ((0.299 * r + 0.587 * g + 0.114 * b) > 140) {
            document.documentElement.setAttribute('data-rego-theme', 'light');
        }
    }
})();
window.regoHandlers = window.regoHandlers || {};
function handleMessage(data) {
    var message = typeof data === 'string' ? JSON.parse(data) : data;
    var handler = window.regoHandlers[message.Ident];
    if (handler) {
        handler(message.Value);
    }
}
JS;
    }
}
