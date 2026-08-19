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
    protected function RegoTile(string $type, string $inner, string $script, array $state = []): string
    {
        return $this->RegoCss()
            . '<div class="tile" data-type="' . $type . '"><div class="stack">' . $inner . '</div></div>'
            . '<script>'
            . 'window.regoState = ' . json_encode($state) . ';'
            . $this->RegoBoot()
            . $script
            . '</script>';
    }

    /**
     * Eine Zeile mit Regler und Prozentanzeige rechts.
     */
    protected function RegoSliderLine(string $id, string $onChange): string
    {
        // Die Prozentanzeige steht links, damit die Spur bis an die rechte
        // Kante laeuft und der Griff mit den Knoepfen darunter abschliesst.
        return '<div class="line">'
            . '<span class="pct" id="' . $id . '-label">–</span>'
            . '<span class="chan"><span class="chan-track" id="' . $id . '">'
            . '<span class="chan-fill"></span><span class="chan-thumb"></span>'
            . '<input type="range" min="0" max="100" step="1" value="0" '
            . 'oninput="' . $onChange . 'Preview(this.value)" onchange="' . $onChange . '(this.value)">'
            . '</span></span>'
            . '</div>';
    }

    /**
     * Eine Reihe gleich breiter Knoepfe.
     */
    protected function RegoButtons(string $buttons): string
    {
        return '<div class="buttons">' . $buttons . '</div>';
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
            'taster'   => '<circle cx="12" cy="12" r="8"/><circle cx="12" cy="12" r="3" fill="currentColor" stroke="none"/>',
            'url'      => '<path d="M10 13a5 5 0 0 0 7.5.5l3-3a5 5 0 0 0-7-7l-1.5 1.5"/><path d="M14 11a5 5 0 0 0-7.5-.5l-3 3a5 5 0 0 0 7 7L12 19"/>',
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
    --accent:#4d7616; --accent-contrast:#ffffff; --accent-bg:rgba(77,118,22,.1);
    --danger:#cf222e; --danger-bg:rgba(207,34,46,.1); --danger-border:rgba(207,34,46,.28);
    --radius-lg:10px; --radius-md:8px; --radius-sm:6px;
    --sans:"Inter",-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",sans-serif;
}
/* Steht Symcon dunkel, nimmt die Kachel die dunkle Palette von REGObaseX1 --
   sonst stuende heller Text auf hellem Grund. */
:root[data-rego-theme="dark"]{
    --bg:#0c0c0e; --surface:#131316; --surface-2:#1a1a1e; --surface-3:#202024; --surface-hover:#232327;
    --border:rgba(255,255,255,.06); --border-strong:rgba(255,255,255,.14);
    --text:#f2f2f4; --text-muted:#86868f; --text-faint:#55555c;
    --accent:#b9ff5c; --accent-contrast:#0e1a02; --accent-bg:rgba(185,255,92,.14);
    --danger:#f85149; --danger-bg:rgba(248,81,73,.13); --danger-border:rgba(248,81,73,.32);
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
/* Die Kachel-Visualisierung schreibt Raum und Name ueber das iframe und gibt
   die noetigen Abstaende als Query-Parameter mit -- die stehen hier als
   Innenabstand, sonst klebt der Inhalt in der Ueberschrift. Die Kachel selbst
   bringt keinen eigenen Rahmen mit: Symcons Karte ist schon der Rahmen. */
.tile{
    display:flex; align-items:center; min-height:100%;
    padding:var(--pad-top,8px) var(--pad-side,8px) var(--pad-bottom,8px);
}
.stack{display:flex; flex-direction:column; gap:9px; width:100%}
.line{display:flex; align-items:center; gap:12px; width:100%}

button{
    font-family:inherit; font-size:14px; font-weight:600; color:var(--text);
    background:var(--surface-3); border:1px solid var(--border);
    border-radius:var(--radius-lg); padding:.5rem .8rem; cursor:pointer;
    transition:background .15s ease, border-color .15s ease, color .15s ease;
}
button:hover:not(:disabled){background:var(--surface-hover)}
button:disabled{opacity:.5;cursor:default}
svg{display:block}

/* Knopfreihe: gleich breit ueber die ganze Zeile, wie im Detail-Dialog */
.buttons{display:flex; gap:8px; width:100%}
.buttons button{flex:1 1 0; min-width:0; min-height:2.5rem}

/* Verweis, der wie ein Knopf aussieht (URL-Aufruf) */
.knopf-link{
    flex:1 1 0; min-height:2.5rem;
    display:flex; align-items:center; justify-content:center;
    background:var(--surface-3); border:1px solid var(--border);
    border-radius:var(--radius-lg); color:var(--text);
    font-size:14px; font-weight:600; text-decoration:none;
    transition:background .15s ease, border-color .15s ease, color .15s ease;
}
.knopf-link:hover{background:var(--surface-hover); border-color:var(--accent); color:var(--accent)}

/* AN/AUS -- an ist rot */
.onoff-button{min-height:2.5rem; font-weight:700; letter-spacing:.02em}
.onoff-button-on{background:var(--danger-bg); border-color:var(--danger-border); color:var(--danger)}
.onoff-button-off{background:var(--surface-3); border-color:var(--border); color:var(--text)}
.onoff-button-unknown{background:var(--surface-3); border-color:var(--border); color:var(--text-faint)}

/* Regler: breite Spur, grosser runder Griff in Akzentfarbe */
.chan{position:relative; display:flex; align-items:center; flex:1 1 auto; min-width:60px}
.chan-track{
    position:relative; flex:1 1 auto; height:10px; border-radius:999px;
    background:var(--surface-3); border:1px solid var(--border); cursor:pointer;
}
.chan-fill{display:none}
.chan-thumb{
    position:absolute; top:50%; transform:translate(-50%,-50%);
    width:26px; height:26px; border-radius:50%; background:var(--accent);
}
.chan input[type=range]{
    position:absolute; inset:-12px 0; width:100%; height:auto; min-height:34px;
    margin:0; opacity:0; cursor:pointer;
    -webkit-appearance:none; appearance:none;
}
.pct{
    flex:0 0 auto; min-width:3.2rem; text-align:left;
    font-size:15px; font-weight:600; color:var(--text-faint);
    font-variant-numeric:tabular-nums;
}

/* Messwert: grosse Zahl mit kleiner Einheit, wie in der Raumzusammenfassung
   von REGObaseX1 */
.sensor{display:flex; align-items:baseline; flex-wrap:wrap; gap:0 .25rem; width:100%}
.sensor-value{
    font-size:1.9rem; font-weight:700; line-height:1.05; letter-spacing:-.02em;
    font-variant-numeric:tabular-nums; color:var(--text);
}
.sensor-value.alarm{color:var(--danger)}
.sensor-unit{font-size:.85rem; font-weight:500; color:var(--text-muted)}
.badges{display:flex; flex-wrap:wrap; gap:.4rem; width:100%}
.badge{
    display:inline-flex; align-items:center; gap:.3rem;
    padding:.18rem .6rem; border:1px solid var(--border); border-radius:999px;
    background:var(--surface-2); font-size:.8rem; color:var(--text-muted);
    font-variant-numeric:tabular-nums;
}
.badge-danger{background:var(--danger-bg); border-color:var(--danger-border); color:var(--danger)}

/* Werteraster: mehrere Messwerte nebeneinander, jeder mit Beschriftung */
.werte{display:flex; flex-wrap:wrap; gap:.5rem 1.4rem; width:100%}
.wert{display:flex; flex-direction:column; gap:.1rem; min-width:0}
.wert-zahl{
    font-size:1.25rem; font-weight:700; line-height:1.1; letter-spacing:-.02em;
    font-variant-numeric:tabular-nums; color:var(--text); white-space:nowrap;
}
.wert-einheit{margin-left:.2rem; font-size:.75rem; font-weight:500; color:var(--text-muted)}
.wert-label{
    font-size:.65rem; font-weight:700; text-transform:uppercase; letter-spacing:.05em;
    color:var(--text-muted); white-space:nowrap;
}

/* Klima: links der Istwert, rechts der Sollwert-Steller */
.readout{display:flex; flex-direction:column; gap:2px; flex:1 1 auto; min-width:0}
.readout-label{
    font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:.06em;
    color:var(--text-muted);
}
.readout-value{font-size:17px; font-weight:700; font-variant-numeric:tabular-nums}
.stepper{display:flex; align-items:center; gap:8px; flex:0 0 auto}
.stepper button{width:2.5rem; min-height:2.5rem; font-size:16px; display:grid; place-items:center}
.stepper span{font-size:16px; font-weight:700; font-variant-numeric:tabular-nums; min-width:4rem; text-align:center}

.muted{color:var(--text-faint); font-variant-numeric:tabular-nums}

/* Anzeige-Kacheln oeffnen per Klick das Objekt in Symcon -- dort gibt es den
   Verlauf, den die Kachel selbst nicht zeichnen soll. */
.oeffnen{cursor:pointer}
.oeffnen:hover .sensor-value,.oeffnen:hover .wert-zahl{color:var(--accent)}

/* Werteraster: mehrere Messwerte nebeneinander, jeder mit Beschriftung */
.werte{display:flex; flex-wrap:wrap; gap:.5rem 1.4rem; width:100%}
.wert{display:flex; flex-direction:column; gap:.1rem; min-width:0}
.wert-zahl{
    font-size:1.25rem; font-weight:700; line-height:1.1; letter-spacing:-.02em;
    font-variant-numeric:tabular-nums; color:var(--text); white-space:nowrap;
}
.wert-einheit{margin-left:.2rem; font-size:.75rem; font-weight:500; color:var(--text-muted)}
.wert-label{
    font-size:.65rem; font-weight:700; text-transform:uppercase; letter-spacing:.05em;
    color:var(--text-muted); white-space:nowrap;
}

/* Klima: links der Istwert, rechts der Sollwert-Steller */
.readout{display:flex; flex-direction:column; gap:2px; flex:1 1 auto; min-width:0}
.readout-label{
    font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:.06em;
    color:var(--text-muted);
}
.readout-value{font-size:17px; font-weight:700; font-variant-numeric:tabular-nums}
.stepper{display:flex; align-items:center; gap:8px; flex:0 0 auto}
.stepper button{width:2.5rem; min-height:2.5rem; font-size:16px; display:grid; place-items:center}
.stepper span{font-size:16px; font-weight:700; font-variant-numeric:tabular-nums; min-width:4rem; text-align:center}

.muted{color:var(--text-faint); font-variant-numeric:tabular-nums}

/* Anzeige-Kacheln oeffnen per Klick das Objekt in Symcon -- dort gibt es den
   Verlauf, den die Kachel selbst nicht zeichnen soll. */
.oeffnen{cursor:pointer}
.oeffnen:hover .sensor-value,.oeffnen:hover .wert-zahl{color:var(--accent)}

/* Die beteiligten Objekte: in der Kachel versteckt, sichtbar sobald sie
   aufgeklappt ist -- dann ist das iframe hoch genug. Symcon kennt keinen
   Schalter dafuer, die Hoehe ist das einzige verlaessliche Merkmal. */
.objekte{display:none}
@media (min-height: 300px){
    .objekte{
        display:flex; flex-direction:column; width:100%;
        margin-top:.5rem; padding-top:.5rem; border-top:1px solid var(--border);
    }
    .objekt{
        display:flex; align-items:center; gap:.6rem; width:100%;
        padding:.45rem .2rem; background:none; border:0; border-radius:var(--radius-sm);
        color:var(--text); font:inherit; font-size:13px; text-align:left; cursor:pointer;
    }
    .objekt:hover{background:var(--surface-2)}
    .objekt-label{
        flex:0 0 auto; min-width:9rem;
        font-size:.68rem; font-weight:700; text-transform:uppercase; letter-spacing:.05em;
        color:var(--text-muted);
    }
    .objekt-name{
        flex:1 1 auto; min-width:0;
        overflow:hidden; text-overflow:ellipsis; white-space:nowrap; color:var(--text-muted);
    }
    .objekt-pfeil{flex:0 0 auto; color:var(--text-faint)}
    .objekt:hover .objekt-pfeil,.objekt:hover .objekt-name{color:var(--accent)}
}
:focus-visible{outline:2px solid var(--accent); outline-offset:2px; border-radius:var(--radius-sm)}
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
function regoNumber(value, digits) {
    return value.toFixed(digits === undefined ? 0 : digits).replace('.', ',');
}
function regoFill(id, percent) {
    var track = document.getElementById(id);
    if (!track) {
        return;
    }
    var p = Math.max(0, Math.min(100, percent === null ? 0 : percent));
    // Der Griff laeuft um seinen halben Durchmesser eingerueckt, sonst haengt
    // er bei 0 und 100 Prozent ueber die Spur hinaus.
    track.querySelector('.chan-thumb').style.left =
        'calc(13px + (100% - 26px) * ' + (p / 100) + ')';
}
JS;
    }

}
