<?php

declare(strict_types=1);

/**
 * Gemeinsame Basis aller REGOvisu-Kacheln.
 *
 * Farben, Maße, Formen und Zustandslogik stammen aus dem REGObaseX1-Entwurf:
 * warmes Papier statt Grau, Petrol als Akzent, Bernstein für "an" mit Glühen,
 * Zeilen mit rundem Symbolfeld links und der Bedienung rechts.
 *
 * Der Name der Funktion steht bewusst NICHT in der Kachel -- die
 * Kachel-Visualisierung schreibt den Instanznamen selbst darüber, ein zweiter
 * Name würde ihn nur doppeln. In der Kachel steht deshalb nur, was Symcon
 * nicht ohnehin anzeigt: der Zustand und die Bedienung.
 */
trait RegoVisuTile
{
    // ------------------------------------------------------------------
    // Verdrahtung an fremde Symcon-Variablen
    // ------------------------------------------------------------------

    /**
     * Meldet die Kachel auf VM_UPDATE aller übergebenen Variablen an und löst
     * dabei die Anmeldungen des vorherigen Konfigurationsstandes.
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
     * Variable (KNX-Telegramm, Gerätebefehl); nur wenn keine hinterlegt ist,
     * wird der Wert direkt gesetzt, damit reine Merker auch funktionieren.
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
     * Rundet einen Prozentwert auf den Datentyp der Zielvariable.
     */
    protected function RegoCastNumber(int $variableID, float $value)
    {
        if (($variableID != 0) && IPS_VariableExists($variableID)
            && (IPS_GetVariable($variableID)['VariableType'] == 1)) {
            return (int) round($value);
        }
        return $value;
    }

    /**
     * Schickt einen Wert an das Kachel-HTML; dort verteilt ihn handleMessage()
     * anhand des Idents.
     */
    protected function RegoPush(string $ident, $value): void
    {
        $this->UpdateVisualizationValue(json_encode(['Ident' => $ident, 'Value' => $value]));
    }

    // ------------------------------------------------------------------
    // HTML
    // ------------------------------------------------------------------

    /**
     * Baut die Kachel: Stil, Zeile, Skript.
     *
     * $state landet als window.regoState im HTML, $script übernimmt das
     * Zeichnen -- so ist der Startzustand ohne zweiten Rundlauf da.
     */
    protected function RegoTile(string $type, string $value, string $controls, string $script, array $state = []): string
    {
        return $this->RegoCss()
            . '<div class="tile"><div class="row" id="rego-row" data-type="' . $type . '">'
            . '<span class="dot" id="rego-dot" aria-hidden="true"></span>'
            . '<span class="row-icon" id="rego-icon">' . $this->RegoIcon($type) . '</span>'
            . '<span class="row-main"><span class="row-value" id="rego-value">' . $value . '</span></span>'
            . '<span class="row-ctrl">' . $controls . '</span>'
            . '</div></div>'
            . '<script>'
            . 'window.regoState = ' . json_encode($state) . ';'
            . $this->RegoBoot()
            . $script
            . '</script>';
    }

    /**
     * Die Symbole des Entwurfs, unverändert übernommen.
     */
    protected function RegoIcon(string $type): string
    {
        $paths = [
            'schalten' => '<path d="M15 14c.2-1 .7-1.7 1.5-2.5 1-.9 1.5-2.2 1.5-3.5A6 6 0 0 0 6 8c0 1 .2 2.2 1.5 3.5.7.7 1.3 1.5 1.5 2.5"/><path d="M9 18h6"/><path d="M10 22h4"/>',
            'dimmen'   => '<path d="M15 14c.2-1 .7-1.7 1.5-2.5 1-.9 1.5-2.2 1.5-3.5A6 6 0 0 0 6 8c0 1 .2 2.2 1.5 3.5.7.7 1.3 1.5 1.5 2.5"/><path d="M9 18h6"/><path d="M10 22h4"/>',
            'jalousie' => '<path d="M3 3h18"/><path d="M20 7H8"/><path d="M20 11H8"/><path d="M10 19h10"/><path d="M8 15h12"/><path d="M4 3v14a2 2 0 0 0 2 2 2 2 0 0 0 2-2V3"/>',
            'klima'    => '<path d="M14 4v10.54a4 4 0 1 1-4 0V4a2 2 0 0 1 4 0Z"/>',
            'szene'    => '<path d="M9.9 2.1 8.5 6.4 4.2 7.8l4.3 1.4 1.4 4.3 1.4-4.3 4.3-1.4-4.3-1.4Z"/><path d="M18 12l-.7 2.1-2.1.7 2.1.7.7 2.1.7-2.1 2.1-.7-2.1-.7Z"/><path d="M6.5 16l-.5 1.5-1.5.5 1.5.5.5 1.5.5-1.5 1.5-.5-1.5-.5Z"/>',
        ];

        $path = $paths[$type] ?? '<circle cx="12" cy="12" r="9"/>';

        return '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" '
            . 'stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
            . $path . '</svg>';
    }

    /**
     * Tokens und Bauteile des Entwurfs.
     */
    protected function RegoCss(): string
    {
        return <<<'CSS'
<style>
:root{
    --bg:#f7f7f6; --surface:#ffffff; --surface-2:#f1f1ef; --surface-3:#e7e7e4; --surface-hover:#dedede;
    --border:rgba(0,0,0,.08); --border-strong:rgba(0,0,0,.16);
    --text:#1a1a1c; --text-muted:#65656d; --text-faint:#8b8b93;
    --accent:#4d7616; --accent-hover:#3f6111; --accent-contrast:#ffffff; --accent-bg:rgba(77,118,22,.1);
    --danger:#cf222e; --danger-bg:rgba(207,34,46,.1); --danger-border:rgba(207,34,46,.28);
    --radius-md:8px; --radius-sm:6px;
    --shadow-card:0 1px 2px rgba(0,0,0,.08);
    --sans:"Inter",-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",sans-serif;
}
/* Steht Symcon dunkel, nimmt die Kachel die dunkle Palette von REGObaseX1 --
   sonst stuende heller Text auf hellem Grund. */
:root[data-rego-theme="dark"]{
    --bg:#0c0c0e; --surface:#131316; --surface-2:#1a1a1e; --surface-3:#202024; --surface-hover:#232327;
    --border:rgba(255,255,255,.06); --border-strong:rgba(255,255,255,.14);
    --text:#f2f2f4; --text-muted:#86868f; --text-faint:#55555c;
    --accent:#b9ff5c; --accent-hover:#a3ea48; --accent-contrast:#0e1a02; --accent-bg:rgba(185,255,92,.14);
    --danger:#f85149; --danger-bg:rgba(248,81,73,.13); --danger-border:rgba(248,81,73,.32);
    --shadow-card:0 1px 2px rgba(0,0,0,.4);
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html,body{height:100%}
body{
    font-family:var(--sans)!important;
    font-size:14px; line-height:1.4; color:var(--text);
    background:transparent; margin:0!important; padding:0!important;
    -webkit-tap-highlight-color:transparent; user-select:none;
    -webkit-font-smoothing:antialiased;
}
/* Die Kachel-Visualisierung schreibt ihre Ueberschrift ueber das iframe und
   gibt die noetigen Abstaende als Query-Parameter mit. Die werden hier als
   Innenabstand gesetzt -- sonst klebt der Inhalt in der Ueberschrift. Der
   Inhalt sitzt mittig, damit er auf hohen Kacheln nicht oben festhaengt. */
.tile{
    display:flex; align-items:center; min-height:100%;
    padding:var(--pad-top,8px) var(--pad-side,8px) var(--pad-bottom,8px);
}
button{
    font-family:inherit; font-size:13px; font-weight:600; color:var(--text);
    background:var(--surface-3); border:1px solid var(--border-strong);
    border-radius:var(--radius-sm); padding:.4rem .8rem; cursor:pointer;
    transition:background .15s ease, border-color .15s ease, opacity .15s ease;
}
button:hover:not(:disabled){background:var(--surface-hover)}
button:disabled{opacity:.5;cursor:default}
svg{display:block}

/* Eine Zeile; die Hoehe kommt aus dem Inhalt, damit die Kachel nicht auf die
   volle Hoehe des Kachelplatzes aufgeblasen wird. */
.row{
    display:flex; align-items:center; gap:10px; width:100%;
    background:var(--surface); border:1px solid var(--border);
    border-radius:var(--radius-md); padding:7px 10px; box-shadow:var(--shadow-card);
}
.row-icon{
    width:28px; height:28px; border-radius:var(--radius-sm); flex:0 0 auto;
    display:grid; place-items:center;
    background:var(--surface-3); color:var(--accent);
}
.row-icon svg{width:16px;height:16px}
.row-main{min-width:0; flex:0 1 auto}
.row-value{
    display:block; font-size:14px; font-weight:600; color:var(--text-muted);
    font-variant-numeric:tabular-nums;
    white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
}
.row-ctrl{display:flex; align-items:center; gap:8px; flex:1 1 auto; justify-content:flex-end}
.dot{display:none}

/* AN/AUS -- an ist rot, wie in REGObaseX1 */
.onoff-button{
    min-width:3.5rem; min-height:2rem; padding:.3rem .7rem;
    border-radius:var(--radius-md); font-weight:700; font-size:13px; letter-spacing:.02em;
    transition:background-color .15s ease, border-color .15s ease, color .15s ease;
}
.onoff-button-on{background:var(--danger-bg); border:1px solid var(--danger-border); color:var(--danger)}
.onoff-button-off{background:var(--surface-3); border:1px solid var(--border); color:var(--text)}
.onoff-button-unknown{background:var(--surface-3); border:1px solid var(--border); color:var(--text-faint)}

/* Prozentregler mit Akzent-Griff */
.chan{position:relative; display:flex; align-items:center; flex:1 1 auto; min-width:90px}
.chan-track{
    position:relative; flex:1 1 auto; height:10px; border-radius:999px;
    background:var(--border-strong); cursor:pointer;
}
.chan-fill{position:absolute; left:0; top:0; bottom:0; border-radius:999px; background:var(--accent)}
.chan-thumb{
    position:absolute; top:50%; transform:translate(-50%,-50%);
    width:26px; height:26px; border-radius:50%; background:var(--accent);
    box-shadow:0 1px 3px rgba(0,0,0,.25);
}
.chan input[type=range]{
    position:absolute; inset:-12px 0; width:100%; height:auto; min-height:34px;
    margin:0; opacity:0; cursor:pointer;
    -webkit-appearance:none; appearance:none;
}

/* Auf / Stopp / Ab */
.jalousie{display:flex; gap:6px}
.jalousie button{min-height:2rem; padding:.3rem .6rem}

/* Soll-Temperatur */
.stepper{display:flex; align-items:center; gap:6px}
.stepper button{width:2rem; min-height:2rem; font-size:14px; display:grid; place-items:center}
.stepper span{font-variant-numeric:tabular-nums; font-weight:600; min-width:3.6rem; text-align:center}

/* Szenen */
.scenes{display:flex; align-items:center; gap:6px; flex-wrap:wrap; justify-content:flex-end}
.scenes button{min-height:2rem}
.scenes button.primary{
    background:var(--accent-bg); border-color:color-mix(in srgb,var(--accent) 30%,transparent);
    color:var(--accent); font-weight:700;
}
.scenes button.primary:hover{background:var(--accent); color:var(--accent-contrast); border-color:var(--accent)}

.muted{color:var(--text-faint); font-variant-numeric:tabular-nums}
:focus-visible{outline:2px solid var(--accent); outline-offset:1px; border-radius:var(--radius-sm)}
@media (prefers-reduced-motion: reduce){*{transition:none !important}}
</style>
CSS;
    }

    /**
     * Theme-Erkennung und Nachrichtenverteilung.
     *
     * Die Kachel-Visualisierung hängt ihre Farben als Query-Parameter an das
     * iframe; aus der Helligkeit von "cardcolor" liest die Kachel ab, ob
     * Symcon gerade hell oder dunkel dargestellt wird, und folgt dem.
     */
    protected function RegoBoot(): string
    {
        return <<<'JS'
(function () {
    var query = new URLSearchParams(window.location.search);
    var style = document.documentElement.style;
    ['top', 'bottom'].forEach(function (seite) {
        var wert = parseInt(query.get('margin' + seite), 10);
        if (!isNaN(wert)) {
            style.setProperty('--pad-' + seite, wert + 'px');
        }
    });
    var seitlich = parseInt(query.get('marginside'), 10);
    if (!isNaN(seitlich)) {
        style.setProperty('--pad-side', seitlich + 'px');
    }

    var card = query.get('cardcolor');
    if (card && /^[0-9a-fA-F]{6}$/.test(card)) {
        var r = parseInt(card.substr(0, 2), 16),
            g = parseInt(card.substr(2, 2), 16),
            b = parseInt(card.substr(4, 2), 16);
        if ((0.299 * r + 0.587 * g + 0.114 * b) <= 140) {
            document.documentElement.setAttribute('data-rego-theme', 'dark');
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
function regoLit(on) {
    document.getElementById('rego-row').classList.toggle('lit', on === true);
    document.getElementById('rego-dot').classList.toggle('on', on === true);
}
function regoValue(text) {
    document.getElementById('rego-value').textContent = text;
}
function regoNumber(value, digits) {
    return value.toFixed(digits === undefined ? 0 : digits).replace('.', ',');
}
function regoFill(id, percent) {
    var track = document.getElementById(id);
    if (!track) {
        return;
    }
    var p = Math.max(0, Math.min(100, percent === null ? 0 : percent));
    track.querySelector('.chan-fill').style.width = p + '%';
    track.querySelector('.chan-thumb').style.left = p + '%';
}
JS;
    }

    /**
     * Ein waagerechter Regler im Zuschnitt des Entwurfs: sichtbare Spur mit
     * Füllung und Griff, darüber ein unsichtbarer echter Range-Slider, damit
     * Tastatur und Touch ohne Extraarbeit funktionieren.
     */
    protected function RegoSlider(string $id, string $variant, string $onChange): string
    {
        return '<span class="chan ' . $variant . '">'
            . '<span class="chan-track" id="' . $id . '">'
            . '<span class="chan-fill"></span><span class="chan-thumb"></span>'
            . '<input type="range" min="0" max="100" step="1" value="0" '
            . 'oninput="' . $onChange . 'Preview(this.value)" onchange="' . $onChange . '(this.value)">'
            . '</span></span>';
    }
}
